<?php
namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;
use Megaads\Clara\Utils\ModuleUtil;

class ModuleAssetLinkCommand extends AbtractCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'module:asset:link
        {name=n/a : The names of modules will be linked.}
        {--all=false : Create asset link to all modules.}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create module asset link';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $isAll = $this->option('all');
        if ($name != null && $name !== 'n/a') {
            $this->link($name);
        } else if ($isAll === null) {
            $moduleConfigs = ModuleUtil::getAllModuleConfigs();
            foreach ($moduleConfigs['modules'] as $moduleNamespace => $moduleConfig) {
                if ($moduleConfig['status'] != 'disable') {
                    $this->link($moduleConfig['name']);
                }
            }
        }
    }
    private function link($moduleName)
    {
        $moduleNamespace = $this->buildNamespace($moduleName);
        $moduleConfigs = ModuleUtil::getAllModuleConfigs();
        $failMessage = [
            "status" => "fail",
            "message" => "Link $moduleName module asset failed. The module's not existed or disabled.",
            "module" => [
                "name" => $moduleName,
                "namespace" => $moduleNamespace,
            ],
        ];
        if (!array_key_exists($moduleNamespace, $moduleConfigs['modules']) || $moduleConfigs['modules'][$moduleNamespace] == null) {
            $failMessage['message'] = "Link $moduleName module asset failed. The module's not existed.";
            $this->response($failMessage);
        } else if ($moduleConfigs['modules'][$moduleNamespace] !== null && $moduleConfigs['modules'][$moduleNamespace]['status'] == 'disable') {
            $failMessage['message'] = "Link $moduleName module asset failed. The module's disabled.";
            $this->response($failMessage);
        } else {
            // link module assets
            ModuleUtil::linkModuleAssets($moduleConfigs['modules'][$moduleNamespace]);
            $this->response([
                "status" => "successful",
                "message" => "$moduleName module assets linked successfully.",
                "module" => [
                    "name" => $moduleName,
                    "namespace" => $moduleNamespace,
                ],
            ]);
        }
    }
}
