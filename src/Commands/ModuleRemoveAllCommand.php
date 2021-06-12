<?php
namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Megaads\Clara\Utils\ModuleUtil;

class ModuleRemoveAllCommand extends AbtractCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:remove-all';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove all modules.';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $moduleDir = app_path() . '/Modules';
        if (File::isDirectory($moduleDir)) {
            $moduleConfigs = ModuleUtil::getAllModuleConfigs();
            foreach ($moduleConfigs['modules'] as $key => $item) {
                // delete assets directory
                ModuleUtil::unlinkModuleAssets($item);
                // rollback module migration
                ModuleUtil::resetMigration($item);
            }
            // delete module directory
            File::deleteDirectory($moduleDir);
            // remove all module configs
            \Module::action("module_removed_all", $moduleConfigs['modules']);
            $moduleConfigs['modules'] = json_decode("{}");
            ModuleUtil::setModuleConfig($moduleConfigs);
            system('composer dump-autoload');
            $this->response([
                "status" => "successful",
                "message" => "Remove all modules successfully.",
            ]);
        } else {
            $this->response([
                "status" => "fail",
                "message" => "Remove all modules failed. There are not any modules.",
            ]);
        }
    }
}
