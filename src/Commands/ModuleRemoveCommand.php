<?php
namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;
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
                File::deleteDirectory($moduleDir);
                $moduleConfigs = ModuleUtil::getAllModuleConfigs();
                unset($moduleConfigs['modules'][$namespace]);
                ModuleUtil::setModuleConfig($moduleConfigs);
                system('composer dump-autoload');
                $this->info("Remove $name module successfully.");
            } else {
                $this->error("Remove $name module failed. The module's not existed.");
            }
        }
    }
}
