<?php
namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Megaads\Clara\Utils\ModuleUtil;

class ModuleInstallCommand extends AbtractCommand
{
    const TYPE_URL = "URL";
    const TYPE_PATH = "Path";
    const TYPE_NAME = "Name";
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'module:install
        {module=n/a : The name/path/URL of module will be installed.}
        {--force=false : Force overwriting existing module}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install a new module';
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $module = $this->argument('module');
        $isForce = $this->option('force') == null || $this->option('force') == true || $this->option('force') == 'true' ? true : false;
        $moduleDir = app_path() . '/Modules';
        if (!File::isDirectory($moduleDir)) {
            File::makeDirectory($moduleDir, 0777, true, true);
        }
        if ($module == null || $module == 'n/a') {
            $moduleConfigs = ModuleUtil::getAllModuleConfigs();
            foreach ($moduleConfigs['modules'] as $moduleNamespace => $moduleConfig) {
                if ($moduleConfig['status'] === 'enable') {
                    $moduleURL = $this->getModuleDownloadURL($moduleConfig['namespace']);
                    $downloadedModuleName = $this->downloadModule($moduleURL, $moduleDir);
                    if ($downloadedModuleName != null) {
                        $this->installModule($moduleDir . '/' . $downloadedModuleName);
                    }
                }
            }
        } else {
            $moduleType = $this->checkModuleArgType($module);
            switch ($moduleType) {
                case self::TYPE_NAME:
                    {
                        $moduleNamespace = $this->buildNamespace($module);
                        $moduleConfigs = ModuleUtil::getAllModuleConfigs();
                        if (array_key_exists($moduleNamespace, $moduleConfigs['modules']) && $moduleConfigs['modules'][$moduleNamespace]['status'] === 'enable') {
                            $moduleURL = $this->getModuleDownloadURL($moduleConfigs['modules'][$moduleNamespace]['namespace']);
                            $downloadedModuleName = $this->downloadModule($moduleURL, $moduleDir);
                            if ($downloadedModuleName != null) {
                                $this->installModule($moduleDir . '/' . $downloadedModuleName);
                            }
                        }
                        break;
                    }
                case self::TYPE_URL:
                    {
                        $downloadedModuleName = $this->downloadModule($module, $moduleDir);
                        if ($downloadedModuleName != null) {
                            $this->installModule($moduleDir . '/' . $downloadedModuleName);
                        }
                        break;
                    }
                case self::TYPE_PATH:
                    {
                        $downloadedModuleName = $this->downloadModule($module, $moduleDir);
                        if ($downloadedModuleName != null) {
                            $this->installModule($moduleDir . '/' . $downloadedModuleName);
                        }
                        break;
                    }
                default:
                    break;
            }
        }
    }
    private function getModuleDownloadURL($moduleNamespace)
    {
        return config('clara.app_store_url', '') . '/download/' . $moduleNamespace;
    }
    private function installModule($modulePath)
    {
        if (true) {
            $moduleSpecs = ModuleUtil::getModuleSpecs($modulePath);
            $currentModuleNamespace = $moduleSpecs['namespace'];
            $currentModuleName = $moduleSpecs['name'];
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
            system('composer update');
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
    private function downloadModule($moduleDownloadURL, $moduleDir)
    {
        $moduleName = null;
        $moduleTmpZipPath = $moduleDir . '/module_tmp.zip';
        try {
            echo 'Downloading module from: ' . $moduleDownloadURL . '...';
            if (filter_var($moduleDownloadURL, FILTER_VALIDATE_URL) === false) {
                File::copy($moduleDownloadURL, $moduleTmpZipPath);
            } else {
                file_put_contents($moduleTmpZipPath, fopen($moduleDownloadURL, 'r'));
            }
        } catch (\Throwable $th) {
            $this->response([
                "status" => "fail",
                "message" => "Cannot download module from: $moduleDownloadURL",
            ]);
            return $moduleName;
        }
        $zipArchive = new \ZipArchive();
        $result = $zipArchive->open($moduleTmpZipPath);
        if ($result === true) {
            $moduleName = explode('/', $zipArchive->getNameIndex(0))[0];
            $zipArchive->extractTo($moduleDir);
            $zipArchive->close();
            File::delete($moduleTmpZipPath);
        }
        return $moduleName;
    }
    private function checkModuleArgType($moduleArg = '')
    {
        $retval = self::TYPE_NAME;
        if (filter_var($moduleArg, FILTER_VALIDATE_URL) == true) {
            $retval = self::TYPE_URL;
        } else if (strpos($moduleArg, '/') !== false || strpos($moduleArg, '\\') !== false) {
            $retval = self::TYPE_PATH;
        }
        return $retval;
    }
}
