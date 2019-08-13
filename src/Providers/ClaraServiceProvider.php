<?php
namespace Megaads\Clara\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Megaads\Clara\Commands\ModuleMakeCommand;
use Megaads\Clara\Event\Events;

class ClaraServiceProvider extends ServiceProvider
{
    protected $files;
    protected $commands = [
        ModuleMakeCommand::class,
    ];
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $moduleDir = app_path() . '/Modules/';
        if (is_dir($moduleDir)) {
            $modules = array_map('class_basename', $this->files->directories($moduleDir));
            foreach ($modules as $module) {
                $currentModuleDir = app_path() . '/Modules/' . $module;
                $moduleName = strtolower(preg_replace('/\B([A-Z])/', '-$1', $module));
                $appFile = $currentModuleDir . '/app.php';
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
                    $this->loadConfig($configDir, $moduleName);
                }
                $viewDir = $currentModuleDir . '/Resources/Views';
                if ($this->files->isDirectory($viewDir)) {
                    $this->loadViewsFrom($viewDir, $moduleName);
                }
            }
        }
        /*
         * Adds a directive in Blade for actions
         */
        Blade::directive('action', function ($expression) {
            return "<?php Clara::action({$expression}); ?>";
        });
        /*
         * Adds a directive in Blade for views
         */
        Blade::directive('view', function ($expression) {
            return "<?php echo Clara::view({$expression}); ?>";
        });
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
    private function loadConfig($configDir, $module = null)
    {
        $files = $this->app['files']->files($configDir);
        $module = $module ? $module . '::' : '';
        foreach ($files as $file) {
            $config = $this->app['files']->getRequire($file);
            $name = $this->app['files']->name($file);
            // special case for files named config.php (config keyword is omitted)
            // if ($name === 'config') {
            foreach ($config as $key => $value) {
                $this->app['config']->set($module . $key, $value);
            }
            // }
            $this->app['config']->set($module . $name, $config);
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
        $this->app->singleton('Clara', function ($app) {
            return new Events();
        });
        $this->commands($this->commands);
    }
    /**
     * Register the "make:module" console command.
     *
     * @return Console\ModuleMakeCommand
     */
    protected function registerMakeCommand()
    {
        $this->commands('modules.make');
        $bind_method = method_exists($this->app, 'bindShared') ? 'bindShared' : 'singleton';
        $this->app->{$bind_method}('modules.make', function ($app) {
            return new Megaads\Clara\Commands($this->files);
        });
    }
    /**
     * @return array
     */
    public function provides()
    {
        $provides = $this->commands;
        return $provides;
    }
}
