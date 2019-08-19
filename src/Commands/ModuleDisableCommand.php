<?php
namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;
use Megaads\Clara\Utils\ModuleUtil;

class ModuleDisableCommand extends AbtractCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:disable';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disable the given module.';
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
                $this->error("Disable $name module failed. The module's existed.");
            } else {
                $moduleConfigs['modules'][$moduleNamespace]['status'] = 'disable';
                ModuleUtil::setModuleConfig($moduleConfigs);
                $this->info("Disable $name module successfully.");
            }
        }
    }
}
