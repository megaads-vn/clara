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
     * The module version extract from console command.
     * @var string
     */
    protected $specificedVersion = '';
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
                    $moduleNamespace = $moduleConfig['namespace'];
                    $moduleVersion = $this->getModuleVersion($moduleNamespace, $moduleConfigs);
                    $moduleURL = $this->getModuleDownloadURL($moduleConfig['namespace']);
                    $this->buildModuleDownloadUrlWithVersion($moduleURL, $moduleVersion);
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
                        $moduleVersion = $this->getModuleVersion($moduleNamespace, $moduleConfigs);
                        $isInstall = true;
                        if ($isInstall) {
                            $moduleURL = $this->getModuleDownloadURL($moduleNamespace);
                            $this->buildModuleDownloadUrlWithVersion($moduleURL, $moduleVersion);
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

    /**
     * @param $moduleNamespace
     * @return string
     */
    private function getModuleDownloadURL($moduleNamespace)
    {
        return config('clara.app_store_url', '') . '/download/' . $moduleNamespace;
    }

    /**
     * @param $modulePath
     * @return void
     */
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
                'version' => ''
            ];
            // link module assets
            ModuleUtil::linkModuleAssets($moduleConfig);
            // set module configs
            $moduleConfigs = ModuleUtil::getAllModuleConfigs();
            $moduleLock = [];
            if (isset($moduleConfigs['modules'][$currentModuleNamespace])) {
                $beforeInstallConfig = $moduleConfigs['modules'][$currentModuleNamespace];
                unset($beforeInstallConfig['name']);
                unset($beforeInstallConfig['namespace']);
                unset($beforeInstallConfig['status']);
                if (isset($beforeInstallConfig['version'])) {
                    unset($moduleConfig['version']);
                }
                $moduleConfig = $moduleConfig + $beforeInstallConfig;
                $latestVersion = $this->getCurrentModuleVersion($currentModuleName);
                $moduleLock = $moduleConfig;
                $moduleLock['version'] = $latestVersion;
                $moduleConfig['version'] = $this->updateModuleVersion($moduleConfig['version'], $latestVersion);
            }

            $moduleConfigs['modules'][$currentModuleNamespace] = $moduleConfig;
            ModuleUtil::setModuleConfig($moduleConfigs);

            $this->makeModuleJsonLock($moduleLock);
            Artisan::call("module:providers");
            // system('composer update');
            // migrate module
            ModuleUtil::runMigration($moduleConfig);
            $this->response([
                "status" => "successful",
                "message" => "Install $currentModuleName module successfully. \nCurrent version is {$latestVersion}",
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

    /**
     * @param $moduleDownloadURL
     * @param $moduleDir
     * @return mixed|string|null
     */
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
            $this->displayMessage('Downloading module from: ' . $moduleDownloadURL . '...');
            if (filter_var($moduleDownloadURL, FILTER_VALIDATE_URL) === false) {
                File::copy($moduleDownloadURL, $moduleTmpZipPath);
            } else {
                file_put_contents($moduleTmpZipPath, fopen($moduleDownloadURL, 'r', false, stream_context_create($opts)));
            }
        } catch (\Throwable $th) {
            $errorMsg = "";
            foreach ($http_response_header as $item) {
                if (preg_match('/X-CUSTOM-MESSAGE:\s+(.*)$/i', $item, $matches)) {
                    $errorMsg = isset($matches[1]) ? $matches[1] : "";
                    break;
                }
            }
            $this->response([
                "status" => "fail",
                "message" => "Cannot download module from: $moduleDownloadURL" . PHP_EOL . "With error: " . $errorMsg,
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

    /**
     * @param $name
     * @param $moduleNamespace
     * @param $moduleConfigs
     * @return false|mixed|string
     */
    private function getModuleVersion(&$moduleNamespace, $moduleConfigs) {
        $retval = '';
        if (strpos($moduleNamespace, ':') !== false) {
            $getVersion = explode(':', $moduleNamespace);
            $moduleNamespace = preg_replace('/:(.*?)$/i', '', $moduleNamespace);
            $retval = end($getVersion);
            if (preg_match('/(\d+)\.(\d+)\.(\d+)/i', $retval, $matches)) {
                $this->specificedVersion = $retval;
            }
        }
        if (array_key_exists($moduleNamespace, $moduleConfigs['modules'])
        && isset($moduleConfigs['modules'][$moduleNamespace]['version'] )
        && $retval == '') {
            $retval = $moduleConfigs['modules'][$moduleNamespace]['version'];
        }
        return $retval;
    }

    /**
     * @return void
     */
    private function makeModuleJsonLock($moduleData)
    {
        $this->displayMessage("Make or update module.lock file.");
        $basePath = base_path();
        $lockFile = 'module.lock';
        $fullFilePath = "{$basePath}/{$lockFile}";
        if (!file_exists($fullFilePath)) {
            $dataFile = [
                "_readme" => ["This file locks the dependencies of your project to a known state", "This file is @generated automatically by clara"],
                "content-hash" => "",
                "modules" => []
            ];
        } else {
            $dataFile = json_decode(file_get_contents($fullFilePath), true);
        }
        $namespace = $moduleData['namespace'];
        $updatedAt = new \DateTime("now", new \DateTimeZone('Asia/Ho_Chi_Minh'));
        unset($moduleData['namespace']);
        $moduleData['updated_at'] = $updatedAt->format('Y-m-d H:i:s');
        $modules = $dataFile['modules'];
        $modules[$namespace] = $moduleData;
        $dataFile['modules'] = $modules;

        $prettyContent = json_encode($dataFile, JSON_PRETTY_PRINT);
        $contentHash = md5($prettyContent);
        $dataFile["content-hash"] = $contentHash;
        file_put_contents("{$basePath}/{$lockFile}", json_encode($dataFile, JSON_PRETTY_PRINT));
        return false;
    }

    /**
     * @param $current
     * @param $newest
     * @return mixed|string
     */
    private function updateModuleVersion($current, $newest)
    {
        if ($this->specificedVersion == '' && preg_match('/(\d+)\.(\d+)\.(\d+)/i', $newest, $matches)) {
            $current = "{$matches[1]}.{$matches[2]}.*";
        } else if (!preg_match('/(\d+)\.(\d+)\.(\d+)/i', $newest, $matches)
            && $current != $newest) {
            $current = str_replace('dev-', '', $newest);
        } else if (!empty($this->specificedVersion)) {
            $current = $this->specificedVersion;
        }
        return $current;
    }

    /**
     * @param $moduleName
     * @return string
     */
    private function getCurrentModuleVersion($moduleName)
    {
        $retVal = "";
        $modulePath = app_path("Modules/{$moduleName}");
        $versionFile = "version.json";
        $fullFilePath = "{$modulePath}/{$versionFile}";
        if (file_exists($fullFilePath)) {
            $fileContent = json_decode(file_get_contents($fullFilePath));
            $retVal = $fileContent->version;
        }
        return $retVal;
    }

    /**
     * @param $moduleURL
     * @param $moduleVersion
     * @return void
     */
    private function buildModuleDownloadUrlWithVersion(&$moduleURL, $moduleVersion)
    {
        if ($moduleVersion !== '' && preg_match('/(\d+)\.(\d+)\.(\d+)/i', $moduleVersion, $matches)) {
            $moduleURL .= '?version=' . $moduleVersion;
        } else if ($moduleVersion !== '' && preg_match('/(dev-)/i', $moduleVersion, $matches)) {
            $moduleURL .= '?version=' . $moduleVersion;
        } else if ($moduleVersion !== ''
            && !preg_match('/(\d+)\.(\d+)\.(\d+)/i', $moduleVersion, $matches)
            && !preg_match('/(\d+)\.(\d+)\.*/i', $moduleVersion, $matchesAsterisk)) {
            $moduleURL .= '?version=dev-' . $moduleVersion;
        }
    }
}
