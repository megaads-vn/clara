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
use Megaads\Clara\Commands\ModuleRemoveAllCommand;
use Megaads\Clara\Commands\ModuleRemoveCommand;
use Megaads\Clara\Commands\ModuleAssetLinkCommand;
use Megaads\Clara\Commands\ModuleSubmitCommand;
use Megaads\Clara\Module;
use Megaads\Clara\Utils\ModuleUtil;

class ModuleServiceProvider extends ServiceProvider
{
    protected $files;
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
    ];
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Add a directive in Blade for actions
        Blade::directive('action', function ($expression) {
            return "<?php Module::action({$expression}); ?>";
        });
        // Add a directive in Blade for views
        Blade::directive('view', function ($expression) {
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
                $routeDir = $currentModuleDir . '/Routes';
                if ($this->files->isDirectory($routeDir)) {
                    $this->loadRoutes($routeDir, $module);
                }
                $configDir = $currentModuleDir . '/Config';
                if ($this->files->isDirectory($configDir)) {
                    $this->loadConfig($configDir, $moduleNamespace);
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
            $routeFiles = $this->app['files']->files($routeDir);
            foreach ($routeFiles as $file) {
                \Route::prefix('')
                    ->namespace('Modules\\' . $module . '\\Controllers')
                    ->group($file);
                // foreach ($route_files as $route_file) {
                //     if ($this->files->exists($route_file)) {
                //         include $file;
                //     }
                // }
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
            // special case for files named config.php (config keyword is omitted)
            // if ($name === 'config') {
            foreach ($config as $key => $value) {
                $this->app['config']->set($moduleNamespace . $key, $value);
            }
            // }
            $this->app['config']->set($moduleNamespace . $name, $config);
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
}
