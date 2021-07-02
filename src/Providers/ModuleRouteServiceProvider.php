<?php 

namespace Megaads\Clara\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as BaseRouteServiceProvider;
use Megaads\Clara\Utils\ModuleUtil;

class ModuleRouteServiceProvider extends BaseRouteServiceProvider {

    protected $files;

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }


    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->files = new Filesystem();
        // Load enabled modules
        $moduleDir = app_path() . '/Modules/';
        if (is_dir($moduleDir)) {
            $moduleConfigs = ModuleUtil::getAllModuleConfigs();
            foreach ($moduleConfigs['modules'] as $moduleConfig) {
                if ($moduleConfig['status'] == 'disable') {
                    continue;
                }
                $module = $moduleConfig['name'];
                $currentModuleDir = app_path() . '/Modules/' . $module;
                $routeDir = $currentModuleDir . '/Routes';
                if ($this->files->isDirectory($routeDir)) {
                    $this->mapModuleRoute($routeDir, $module);
                }
            }
        }
    }

    protected function mapModuleRoute($routeDir, $module)
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
            
            if (file_exists($moduleJsonFile)) {
                $moduleContent = json_decode(file_get_contents($moduleJsonFile));
                if (isset($moduleContent->routes)) {
                    foreach ($moduleContent->routes as $item)
                    $ignoreRouteNamespace[$item->name] = $item->namespace;
                }
            }
            
            $routeFiles = $this->app['files']->files($routeDir);
            foreach ($routeFiles as $file) {
               $route = \Route::prefix($locale);
               $fileName = $this->getFileName($file);
               if (isset($ignoreRouteNamespace[$fileName])) {
                   $route->namespace($ignoreRouteNamespace[$fileName]);
               } else {
                   $route->namespace('Modules\\' . $module . '\\Controllers');
               }
                $route->group($file);
            }
        }
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
}
