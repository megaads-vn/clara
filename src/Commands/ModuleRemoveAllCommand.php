<?php
namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\InputArgument;

class ModuleRemoveAllCommand extends Command
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
            File::deleteDirectory($moduleDir);
            system('composer dump-autoload');
            $this->line("Remove all modules successfully.");
        } else {
            $this->error("Remove all modules failed. There are not any modules.");
        }
    }
    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::IS_ARRAY, 'The names of modules will be created.'],
        ];
    }
}
