<?php
namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\InputArgument;

class ModuleMakeCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:make';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new module.';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $names = $this->argument('name');
        $moduleDir = app_path() . '/Modules/';
        $exampleModuleDir = __DIR__ . '/../ModuleExample';
        if (File::isDirectory($moduleDir)) {
            File::makeDirectory($moduleDir, 0777, true, true);
        }
        foreach ($names as $name) {
            $currentModuleDir = $moduleDir . $name;
            if (File::isDirectory($currentModuleDir)) {
                $this->error("Make $name module failed.");
            } else {
                File::copyDirectory($exampleModuleDir, $moduleDir . $name);
                $this->line("Make $name module successfully.");
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
