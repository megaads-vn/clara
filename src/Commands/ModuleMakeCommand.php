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
        $exampleModuleDir = __DIR__ . '/../ExampleModule';
        if (File::isDirectory($moduleDir)) {
            File::makeDirectory($moduleDir, 0777, true, true);
        }
        foreach ($names as $name) {
            $currentModuleDir = $moduleDir . $name;
            if (File::isDirectory($currentModuleDir)) {
                $this->error("Make $name module failed. The module's existed.");
            } else {
                File::copyDirectory($exampleModuleDir, $moduleDir . $name);
                $moduleFiles = File::allFiles($moduleDir . $name);
                foreach ($moduleFiles as $moduleFile) {
                    $moduleSlug = strtolower(preg_replace('/\B([A-Z])/', '-$1', $name));
                    $this->replaceInFile($moduleFile->getPathname(), 'Modules\\Example', 'Modules\\' . $name);
                    $this->replaceInFile($moduleFile->getPathname(), 'ExampleModule', $name . 'Module');
                    $this->replaceInFile($moduleFile->getPathname(), 'Example Module', $name . ' Module');                    
                    $this->replaceInFile($moduleFile->getPathname(), 'example-module', $moduleSlug . '-module');
                    $this->replaceInFile($moduleFile->getPathname(), 'exampleModule', $moduleSlug . 'Module');
                    $this->replaceInFile($moduleFile->getPathname(), 'example::', $moduleSlug . '::');
                }
                system('composer dump-autoload');
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
    private function replaceInFile($filePath, $findString, $replaceString)
    {
        $fileContent = file_get_contents($filePath);
        $fileContent = str_replace($findString, $replaceString, $fileContent);
        file_put_contents($filePath, $fileContent);
    }
}
