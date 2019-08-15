<?php
namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\InputArgument;

class ModuleRemoveCommand extends Command
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
    protected $description = 'Remove a module.';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $names = $this->argument('name');
        foreach ($names as $name) {
            $moduleDir = app_path() . '/Modules/' . $name;
            if (File::isDirectory($moduleDir)) {
                File::deleteDirectory($moduleDir);
                system('composer dump-autoload');
                $this->line("Remove $name module successfully.");
            } else {
                $this->error("Remove $name module failed. The module's not existed.");
            }
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
