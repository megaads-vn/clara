<?php
namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Megaads\Clara\Utils\ModuleUtil;

class ModuleRemoveCommand extends AbtractCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:remove';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the given module.';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $names = $this->argument('name');
        foreach ($names as $name) {
            $namespace = $this->buildNamespace($name);
            $moduleDir = app_path() . '/Modules/' . $name;
            if (File::isDirectory($moduleDir)) {                
                $moduleConfigs = ModuleUtil::getAllModuleConfigs();
                $moduleConfig = $moduleConfigs['modules'][$namespace];
                // delete module asset directory
                ModuleUtil::unlinkModuleAssets($moduleConfig);
                // rollback module migration
                // ModuleUtil::resetMigration($moduleConfig);
                Artisan::call("module:providers", [
                    'module' => $name,
                    '--action' => 'remove'
                ]);
                // delete module directory
                \Module::action("module_removed", $moduleConfig);
                File::deleteDirectory($moduleDir);
                unset($moduleConfigs['modules'][$namespace]);
                ModuleUtil::setModuleConfig($moduleConfigs);
                system('COMPOSER_MEMORY_LIMIT=-1 composer update -vvv');                
                $this->response([
                    "status" => "successful",
                    "message" => "Remove $name module successfully.",
                    "module" => [
                        "name" => $name,
                        "namespace" => $namespace,
                    ],
                ]);
            } else {
                $this->response([
                    "status" => "fail",
                    "message" => "Remove $name module failed. The module's not existed.",
                    "module" => [
                        "name" => $name,
                        "namespace" => $namespace,
                    ],
                ]);
            }
        }
    }
}
