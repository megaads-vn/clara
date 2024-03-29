<?php
namespace Megaads\Clara\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Megaads\Clara\Commands\ModuleDisableCommand;
use Megaads\Clara\Commands\ModuleDownloadCommand;
use Megaads\Clara\Commands\ModuleInstallCommand;
use Megaads\Clara\Commands\ModuleEnableCommand;
use Megaads\Clara\Commands\ModuleListCommand;
use Megaads\Clara\Commands\ModuleMakeCommand;
use Megaads\Clara\Commands\ModuleMigrationMakeCommand;
use Megaads\Clara\Commands\ModuleMigrationStatusCommand;
use Megaads\Clara\Commands\ModuleRemoveAllCommand;
use Megaads\Clara\Commands\ModuleRemoveCommand;
use Megaads\Clara\Commands\ModuleAssetLinkCommand;
use Megaads\Clara\Commands\ModuleMigrationCommand;
use Megaads\Clara\Commands\ModuleProviderLoadCommand;
use Megaads\Clara\Commands\ModuleSubmitCommand;
use Megaads\Clara\Commands\PackagePublishCommand;
use Megaads\Clara\Module;
use Megaads\Clara\Utils\ModuleUtil;

class ModuleServiceProvider extends ServiceProvider
{
    protected $files;
    protected $appVersion;
    protected $commands = [
        ModuleMakeCommand::class,
        ModuleRemoveCommand::class,
        ModuleRemoveAllCommand::class,
        ModuleEnableCommand::class,
        ModuleDisableCommand::class,
        ModuleListCommand::class,
        ModuleDownloadCommand::class,
        ModuleInstallCommand::class,
        ModuleAssetLinkCommand::class,
        ModuleSubmitCommand::class,
        ModuleMigrationCommand::class,
        ModuleProviderLoadCommand::class,
        PackagePublishCommand::class,
        ModuleMigrationMakeCommand::class,
        ModuleMigrationStatusCommand::class
    ];

    public function __construct($app)
    {
        parent::__construct($app);
        $this->appVersion = (float) $app->version();
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        /** PACKAGE REGISTER */
        if ($this->appVersion >= 5.3) {
            $this->middlewareRegister('Megaads\Clara\Middlewares\TrafficMonitoring');
            $this->app->register(TrafficEventServiceProvider::class);
        }
        $this->publishPackageRerources();
        $this->packageRouteRegister();
        $this->packageViewRegister();
        $this->registryPackageMenu();
        /** END OF PACKAGE REGIESTER */
        // Add a directive in Blade for actions
        Blade::directive('action', function ($expression) {
            return "<?php Module::action({$expression}); ?>";
        });
        // Add a directive in Blade for views
        Blade::directive('view', function ($expression) {
            $expression = str_replace('(', '', $expression);
            $expression = str_replace(')', '', $expression);
            return "<?php echo Module::view({$expression}); ?>";
        });
        // Add a directive in Blade for assets
        Blade::directive('asset', function ($expression) {
            return "<?php echo Module::asset({$expression}); ?>";
        });
        // Add Directive Blade for variable
        Blade::directive('variable', function ($expression) {
            $expression = str_replace(', ', ',', $expression);
            $pos = strpos($expression, ',');
            if ($pos >= 0) {
                $variable = substr($expression, 0, $pos);
                $variable = preg_replace("/[\"']+/", '', $variable);
                $params = substr($expression, $pos + 1);
                return "<?php \${$variable} = Module::variable({$params}); ?>";
            }
        });
        // Load enabled modules
        $moduleDir = app_path() . '/Modules/';
        if (is_dir($moduleDir)) {
            $moduleConfigs = ModuleUtil::getAllModuleConfigs();
            foreach ($moduleConfigs['modules'] as $moduleConfig) {
                if ($moduleConfig['status'] == 'disable') {
                    continue;
                }
                $moduleNamespace = $moduleConfig['namespace'];
                $module = $moduleConfig['name'];
                $currentModuleDir = app_path() . '/Modules/' . $module;
                $appFile = $currentModuleDir . '/start.php';
                if ($this->files->exists($appFile)) {
                    include $appFile;
                }
                if ($this->files->exists($currentModuleDir . '/Kernel.php')) {
                    $this->loadKernel($module);
                }
                $configDir = $currentModuleDir . '/Config';
                if ($this->files->isDirectory($configDir)) {
                    $this->loadConfig($configDir, $moduleNamespace);
                }
                $routeDir = $currentModuleDir . '/Routes';
                if ($this->files->isDirectory($routeDir)) {
                    $this->loadRoutes($routeDir, $module);
                }
                $viewDir = $currentModuleDir . '/Resources/Views';
                if ($this->files->isDirectory($viewDir)) {
                    $this->loadViewsFrom($viewDir, $moduleNamespace);
                }
                \Module::action("module_loaded", $moduleConfig);
            }
        }
        $this->publishConfig();
    }
    private function loadKernel($module)
    {
        $this->app->singleton(
            \Illuminate\Contracts\Http\Kernel::class,
            '\\Modules\\' . $module . '\\Kernel'
        );
    }
    
    private function loadRoutes($routeDir, $module)
    {
        if (!$this->app->routesAreCached()) {
            $locale = '';
            $isLocalization = env('LOCALIZATION', false);
            $appLang = env('APP_LOCALE', '');
            if ($isLocalization && $appLang !== '') {
                $locale = '{locale?}';
            }
            $ignoreRouteNamespace = [];
            $moduleJsonFile = app_path() . '/Modules/' . $module . '/module.json';
            $moduleContent = [];
            $isRequireRoute = true;
            if (file_exists($moduleJsonFile)) {
                $moduleContent = json_decode(file_get_contents($moduleJsonFile));   
            }
            if (isset($moduleContent->routes)) {
                foreach ($moduleContent->routes as $item)
                $ignoreRouteNamespace[$item->name] = $item->namespace;
            }
            if (isset($moduleContent->routed) && !$moduleContent->routed) {
                $isRequireRoute = false;
            }
            if ($isRequireRoute) {
                $routeFiles = $this->app['files']->files($routeDir);
                foreach ($routeFiles as $file) {
                    $route = \Route::prefix($locale);
                    $fileName = $this->getFileName($file);
                    $namespace = 'Modules\\' . $module . '\\Controllers';
                    if (isset($ignoreRouteNamespace[$fileName])) {
                        $namespace = $ignoreRouteNamespace[$fileName];
                    }
                    $routeFilePath = app_path('Modules/' . $module . '/Routes/' . $fileName . '.php');
                    $route->namespace($namespace);
                    $route->group(function() use ($routeFilePath) {
                        require $routeFilePath;
                    });
                }
            }
        }
    }

    private function loadConfig($configDir, $moduleNamespace = null)
    {
        $files = $this->app['files']->files($configDir);
        $moduleNamespace = $moduleNamespace ? $moduleNamespace . '::' : '';
        foreach ($files as $file) {
            $config = $this->app['files']->getRequire($file);
            $name = $this->app['files']->name($file);
            if (is_array($config)) {
                foreach ($config as $key => $value) {
                    $this->app['config']->set($moduleNamespace . $key, $value);
                }
                $this->app['config']->set($moduleNamespace . $name, $config);
            }
        }
    }
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->files = new Filesystem();
        $this->app->singleton('Module', function ($app) {
            return new Module();
        });
        $this->commands($this->commands);
    }
    /**
     * @return array
     */
    public function provides()
    {
        $provides = $this->commands;
        return $provides;
    }
    /**
     * @return null
     */
    private function publishConfig()
    {
        if (function_exists('config_path')) {
            $path = $this->getConfigPath();
            $this->publishes([$path => config_path('clara.php')], 'config');
        }
    }

    /**
     * @return string
     */
    private function getConfigPath()
    {
        return __DIR__.'/../Config/clara.php';
    }

    /**
     * @return string
     */
    private function getFileName($filePath) {
        $file = '';
        $file = explode('/', $filePath);
        $file = end($file);
        return str_replace('.php', '', $file);
    }

    /**
     * @param $middleware
     * @return void
     */
    protected function middlewareRegister($middleware)
    {
        $kernel = $this->app['Illuminate\Contracts\Http\Kernel'];
        $kernel->pushMiddleware($middleware);
    }

    /**
     * @return void
     */
    protected function packageRouteRegister() {
        if (!$this->app->routesAreCached() && $this->appVersion >= 5.3) {
            include __DIR__ . '/../Routes/web.php';
        }
    }

    /**
     * @return void
     */
    protected function packageViewRegister() {
        $this->loadViewsFrom(__DIR__.'/../Resources/Views', 'clara');
    }

    /**
     * @return void
     */
    protected function publishPackageRerources()
    {
        if (function_exists('public_path')) {
            $assetsPath = __DIR__ . '/../Resources/Assets/';
            $listFiles = array_diff(scandir($assetsPath), array('.', '..'));
            $listPaths = [];
            foreach ($listFiles as $item) {
                $extension = explode('.', $item);
                $extension = array_pop($extension);
                $listPaths[__DIR__ . '/../Resources/Assets/' . $item] = public_path('clara/assets/' . $extension . '/' . $item);
            }
            if (!empty($listPaths)) {
                $this->publishes($listPaths, 'assets');
            }
        }
    }

    /**
     * @return void
     */
    protected function registryPackageMenu()
    {
        \Module::onView('system.menu', function () {
            return view('clara::inc.menu');
        });
//        \Module::onVariable('setting_menu', function(&$data) {
//            $data[] = [
//                'url' => '/traffic/requests',
//                'icon' => 'fa-wrench',
//                'title' => 'Page options',
//                'subtitle' => 'Cấu hình trang tùy chỉnh',
//                'route' => [
//                    'name' => 'system::submodule',
//                    'params' => [
//                        'module' => 'settings',
//                        'subModule' => 'page-options'
//                    ]
//                ],
//            ];
//        });
    }
}
