<?php
namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Megaads\Clara\Utils\ModuleUtil;

class ModuleMakeCommand extends AbtractCommand
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
            $moduleNamespace = $this->buildNamespace($name);
            $currentModuleDir = $moduleDir . $name;
            if (File::isDirectory($currentModuleDir)) {
                $this->response([
                    "status" => "fail",
                    "message" => "Make $name module failed. The module's existed.",
                    "module" => [
                        "name" => $name,
                        "namespace" => $moduleNamespace,
                    ],
                ]);
            } else {
                File::copyDirectory($exampleModuleDir, $moduleDir . $name);
                $moduleFiles = File::allFiles($moduleDir . $name);
                foreach ($moduleFiles as $moduleFile) {
                    $this->replaceInFile($moduleFile->getPathname(), 'Modules\\Example', 'Modules\\' . $name);
                    $this->replaceInFile($moduleFile->getPathname(), 'Example Module', $name . ' Module');
                    $this->replaceInFile($moduleFile->getPathname(), 'example::', $moduleNamespace . '::');
                    $this->replaceInFile($moduleFile->getPathname(), '{{MODULE_NAME}}', $name);
                    $this->replaceInFile($moduleFile->getPathname(), '{{MODULE_NAMESPACE}}', $moduleNamespace);
                }
                $moduleConfigs = ModuleUtil::getAllModuleConfigs();
                $moduleConfigs['modules'][$moduleNamespace] = [
                    'name' => $name,
                    'namespace' => $moduleNamespace,
                    'status' => 'enable',
                ];
                ModuleUtil::setModuleConfig($moduleConfigs);
                system('composer dump-autoload');
                $this->response([
                    "status" => "successful",
                    "message" => "Make $name module successfully.",
                    "module" => [
                        "name" => $name,
                        "namespace" => $moduleNamespace,
                    ],
                ]);
                \Module::action("module_made", $moduleConfigs['modules'][$moduleNamespace]);
            }
        }
    }
}
