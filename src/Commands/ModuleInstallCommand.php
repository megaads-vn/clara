<?php
namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Megaads\Clara\Utils\ModuleUtil;
use Symfony\Component\Console\Input\InputArgument;

class ModuleInstallCommand extends AbtractCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'module:install';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install a module from .zip file';
    /**
     * Get the console command arguments.
     *
     * @return array
     */
    public function getArguments()
    {
        return [
            ['url', InputArgument::IS_ARRAY, 'The urls of modules will be installed.'],
        ];
    }
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $urls = $this->argument('url');
        $moduleDir = app_path() . '/Modules/';
        $moduleTmpZip = app_path() . '/Modules/module_tmp.zip';
        if (!File::isDirectory($moduleDir)) {
            File::makeDirectory($moduleDir, 0777, true, true);
        }
        foreach ($urls as $url) {
            $currentModuleName = basename($url, ".zip");
            $currentModuleDir = app_path() . '/Modules/' . $currentModuleName;
            if (File::isDirectory($currentModuleDir)) {
                $moduleSpecs = ModuleUtil::getModuleSpecs($currentModuleDir);
                $currentModuleNamespace = $moduleSpecs['namespace'];
                $this->response([
                    "status" => "fail",
                    "message" => "Install $currentModuleName module failed. The module's existed.",
                    "module" => [
                        "name" => $currentModuleName,
                        "namespace" => $currentModuleNamespace,
                    ],
                ]);
            } else {
                if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                    File::copy($url, $moduleTmpZip);
                } else {
                    file_put_contents($moduleTmpZip, fopen($url, 'r'));
                }
                $zipArchive = new \ZipArchive();
                $result = $zipArchive->open($moduleTmpZip);
                if ($result === true) {
                    $zipArchive->extractTo($moduleDir);
                    $zipArchive->close();
                    File::delete($moduleTmpZip);
                    $moduleSpecs = ModuleUtil::getModuleSpecs($currentModuleDir);
                    $currentModuleNamespace = $moduleSpecs['namespace'];
                    $moduleConfig = [
                        'name' => $currentModuleName,
                        'namespace' => $currentModuleNamespace,
                        'status' => 'enable',
                    ];
                    // link module assets
                    ModuleUtil::linkModuleAssets($moduleConfig);
                    // set module configs
                    $moduleConfigs = ModuleUtil::getAllModuleConfigs();
                    $moduleConfigs['modules'][$currentModuleNamespace] = $moduleConfig;
                    ModuleUtil::setModuleConfig($moduleConfigs);
                    system('composer dump-autoload');
                    // migrate module
                    ModuleUtil::runMigration($moduleConfig);
                    $this->response([
                        "status" => "successful",
                        "message" => "Install $currentModuleName module successfully.",
                        "module" => [
                            "name" => $currentModuleName,
                            "namespace" => $currentModuleNamespace,
                        ],
                    ]);
                    \Module::action("module_made", $moduleConfigs['modules'][$currentModuleNamespace]);
                } else {
                    $this->response([
                        "status" => "fail",
                        "message" => "Install $currentModuleName module failed.",
                        "module" => [
                            "name" => $currentModuleName,
                            "namespace" => $currentModuleNamespace,
                        ],
                    ]);
                }
            }
        }
    }
}
