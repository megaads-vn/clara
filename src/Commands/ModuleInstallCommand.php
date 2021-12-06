<?php
namespace Megaads\Clara\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
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
                        $moduleVersion = $this->getModuleVersion($module);
                        $moduleNamespace = $this->buildNamespace($module);
                        $moduleConfigs = ModuleUtil::getAllModuleConfigs();
                        $isInstall = true;
                        if (array_key_exists($moduleNamespace, $moduleConfigs['modules'])) {
                            if ($moduleConfigs['modules'][$moduleNamespace]['status'] !== 'enable') {
                                $isInstall = false;
                            }
                            if (isset($moduleConfigs['modules'][$moduleNamespace]['version'])
                            && $moduleVersion == '') {
                                $moduleVersion = $moduleConfigs['modules'][$moduleNamespace]['version'];
                            }
                        }
                        if ($isInstall) {
                            $moduleURL = $this->getModuleDownloadURL($moduleNamespace);
                            if ($moduleVersion !== '') {
                                $moduleURL .= '?version=' . $moduleVersion;
                            }
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
            if (isset($moduleConfigs['modules'][$currentModuleNamespace])) {
                $beforeInstallConfig = $moduleConfigs['modules'][$currentModuleNamespace];
                unset($beforeInstallConfig['name']);
                unset($beforeInstallConfig['namespace']);
                unset($beforeInstallConfig['status']);
                $moduleConfig = $moduleConfig + $beforeInstallConfig;
            }
            $moduleConfigs['modules'][$currentModuleNamespace] = $moduleConfig;
            ModuleUtil::setModuleConfig($moduleConfigs);
            Artisan::call("module:providers");
            // system('composer update');
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
        $opts=array(
            "ssl"=>array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ),
        );
        try {
            echo 'Downloading module from: ' . $moduleDownloadURL . '...';
            if (filter_var($moduleDownloadURL, FILTER_VALIDATE_URL) === false) {
                File::copy($moduleDownloadURL, $moduleTmpZipPath);
            } else {
                file_put_contents($moduleTmpZipPath, fopen($moduleDownloadURL, 'r', false, stream_context_create($opts)));
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
    private function getModuleVersion(&$name) {
        $retval = '';
        if (strpos($name, ':') !== false) {
            $getVersion = explode(':', $name);
            $name = preg_replace('/:(.*?)$/i', '', $name);
            $retval = end($getVersion);
        }
        return $retval;
    }
}
