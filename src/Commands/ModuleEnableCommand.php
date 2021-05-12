<?php
namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;
use Megaads\Clara\Utils\ModuleUtil;

class ModuleEnableCommand extends AbtractCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:enable';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable the given module.';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $names = $this->argument('name');
        foreach ($names as $name) {
            $moduleNamespace = $this->buildNamespace($name);
            $moduleConfigs = ModuleUtil::getAllModuleConfigs();
            if ($moduleConfigs['modules'][$moduleNamespace] == null) {
                $this->response([
                    "status" => "fail",
                    "message" => "Enable $name module failed. The module's existed.",
                    "module" => [
                        "name" => $name,
                        "namespace" => $moduleNamespace,
                    ],
                ]);
            } else {
                // link module assets
                ModuleUtil::linkModuleAssets($moduleConfigs['modules'][$moduleNamespace]);
                // set module configs
                $moduleConfigs['modules'][$moduleNamespace]['status'] = 'enable';
                ModuleUtil::setModuleConfig($moduleConfigs);
                $this->response([
                    "status" => "successful",
                    "message" => "Enable $name module successfully.",
                    "module" => [
                        "name" => $name,
                        "namespace" => $moduleNamespace,
                    ],
                ]);
                \Module::action("module_enabled", $moduleConfigs['modules'][$moduleNamespace]);
            }
        }
    }
}
