<?php
namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;
use Megaads\Clara\Utils\ModuleUtil;

class ModuleListCommand extends AbtractCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:list';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List module info';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $moduleConfigs = ModuleUtil::getAllModuleConfigs();
        $this->info(json_encode($moduleConfigs['modules']));
    }
}
