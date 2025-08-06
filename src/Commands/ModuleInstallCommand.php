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
                    list($moduleName, $moduleTmpPath, $moduleBackupPath) = $this->downloadModule($moduleURL, $moduleDir);
                    if ($moduleName != null) {
                        $this->installModule($moduleDir . '/' . $moduleName);
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
                            list($moduleName, $moduleTmpPath, $moduleBackupPath) = $this->downloadModule($moduleURL, $moduleDir);
                            if ($moduleName != null) {
                                // $this->installModule($moduleDir . '/' . $moduleName);
                                $this->installModuleV2($moduleName, $moduleDir, $moduleTmpPath, $moduleBackupPath);
                            }
                        }
                        break;
                    }
                case self::TYPE_URL:
                    {
                        list($moduleName, $moduleTmpPath, $moduleBackupPath) = $this->downloadModule($module, $moduleDir);
                        if ($moduleName != null) {
                            $this->installModule($moduleDir . '/' . $moduleName);
                        }
                        break;
                    }
                case self::TYPE_PATH:
                    {
                        list($moduleName, $moduleTmpPath, $moduleBackupPath) = $this->downloadModule($module, $moduleDir);
                        if ($moduleName != null) {
                            $this->installModule($moduleDir . '/' . $moduleName);
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
        try {
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
             system('COMPOSER_MEMORY_LIMIT=-1 composer update -vvv');
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

            if (file_exists(app_path("Modules/{$currentModuleName}/output.txt"))) {
                // $outputContent = file_get_contents(app_path("Modules/{$currentModuleName}/output.txt"));
                $this->response([
                    "status" => "warning",
                    "message" => "",
                    "module" => [
                        "name" => $currentModuleName,
                        "namespace" => $currentModuleNamespace,
                    ],
                ]);
            }
            \Module::action("module_made", $moduleConfigs['modules'][$currentModuleNamespace]);
        } catch (\Exception $ex) {
            $this->displayMessage("Error when install module: " . $ex->getMessage(), 'e');
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
        $tmpName = uniqid('module_tmp_');
        $moduleTmpPath = storage_path("modules/tmp");
        if (!file_exists($moduleTmpPath)) {
            File::makeDirectory($moduleTmpPath);
        }
        $extractUrl = explode('/', $moduleDownloadURL);
        $tmpModuleName = end($extractUrl);
        $tmpModuleName = preg_replace('/\?version=dev-(.*)$/i', '', $tmpModuleName);
        $extractSlugModuleName = explode('-', $tmpModuleName);
        $tmpModuleName = "";
        foreach ($extractSlugModuleName as $item) {
            $tmpModuleName .= ucfirst($item);
        }
        // $this->removeTemporaryModule("{$moduleTmpPath}{$tmpName}", true);
        $moduleTmpZipPath = $moduleTmpPath . "/{$tmpName}.zip";
        $moduleBackupPath = "";
        $opts = array(
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
            ),
        );
        try {
            $this->displayMessage('Downloading module from: ' . $moduleDownloadURL . '...');
            if (filter_var($moduleDownloadURL, FILTER_VALIDATE_URL) === false) {
                // Nếu là file local
                File::copy($moduleDownloadURL, $moduleTmpZipPath);
            } else {
                // Nếu là endpoint trả về log streaming
                $buffer = '';
                $isFirstOutput = true;
                $isDone = false;
                $downloadUrl = "";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $moduleDownloadURL);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$buffer, &$isFirstOutput, &$downloadUrl) {
                    $buffer = $data;
                    
                    // Kiểm tra xem có dòng DONE=URL không
                    if (strpos($buffer, 'DONE=') !== false) {
                        $lines = explode("\n", $buffer);
                        foreach ($lines as $line) {
                            if (strpos($line, 'DONE=') === 0) {
                                $downloadUrl = trim(substr($line, 5)); // Cắt bỏ "DONE="
                                break;
                            }
                        }
                    }
                    
                    // Tìm dòng đầu tiên có nội dung thực sự
                    if ($isFirstOutput) {
                        $lines = explode("\n", $buffer);
                        foreach ($lines as $line) {
                            $trimmedLine = trim($line);
                            if (!empty($trimmedLine) && strpos($trimmedLine, 'DONE=') !== 0) {
                                $this->displayMessage($trimmedLine);
                                $isFirstOutput = false;
                                break;
                            }
                        }
                        // Nếu chưa tìm thấy dòng có nội dung, tiếp tục buffer
                        if ($isFirstOutput) {
                            return strlen($data);
                        }
                    }
                    
                    // Sau khi đã in dòng đầu, in tất cả data tiếp theo (trừ dòng DONE=)
                    if (!$isFirstOutput) {
                        $lines = explode("\n", $data);
                        foreach ($lines as $line) {
                            if (strpos($line, 'DONE=') !== 0) {
                                $this->displayMessage($line);
                            }
                        }
                    }
                    @ob_flush();
                    @flush();
                    return strlen($data);
                });
                curl_exec($ch);
                curl_close($ch);
                
                // Nếu có URL download, tải file zip về
                if (!empty($downloadUrl)) {
                    $this->displayMessage('📦 Downloading...');
                    file_put_contents($moduleTmpZipPath, fopen($downloadUrl, 'r', false, stream_context_create($opts)));
                    
                    // Lấy expected hash từ server (nếu có)
                    $hashData = $this->getExpectedHashFromServer($downloadUrl);
                    
                    $this->displayMessage('✅ Download completed', 's');
                }
            }
        } catch (\Throwable $th) {
            $errorMsg = "";
            if (isset($http_response_header)) {
                foreach ($http_response_header as $item) {
                    if (preg_match('/X-CUSTOM-MESSAGE:\s+(.*)$/i', $item, $matches)) {
                        $errorMsg = isset($matches[1]) ? $matches[1] : "";
                        break;
                    }
                }
            }
            if ($errorMsg == "") {
                $errorMsg = $th->getMessage();
            }
            $this->displayMessage("Cannot download module from: $moduleDownloadURL" . PHP_EOL . "With error: " . $errorMsg, 'e');
        }
        // Sau khi tải xong, kiểm tra tính toàn vẹn và giải nén
        if (file_exists($moduleTmpZipPath)) {
            // Kiểm tra tính toàn vẹn của file zip
            try {
                if ($this->verifyZipIntegrity($moduleTmpZipPath, $hashData)) {
                    // Kiểm tra chi tiết từng file trong zip (nếu có hash data)
                    if ($hashData && $this->verifyZipFilesIntegrity($moduleTmpZipPath, $hashData)) {
                        $zipArchive = new \ZipArchive();
                        $result = $zipArchive->open($moduleTmpZipPath);
                        if ($result === true) {
                            $moduleName = explode('/', $zipArchive->getNameIndex(0))[0];
                            $zipArchive->extractTo($moduleTmpPath);
                            $zipArchive->close();
                            File::delete($moduleTmpZipPath);
                            
                            // Tạo hash file cho module đã cài đặt
                            $modulePath = $moduleTmpPath . '/' . $moduleName;
                            $this->createModuleHashFile($modulePath);
                            $moduleBackupPath = $this->backupModule($moduleName);
                            $this->displayMessage('✅ Module installed successfully with integrity verified', 's');
                        } else {
                            $this->displayMessage('❌ Failed to open zip file', 'e');
                            File::delete($moduleTmpZipPath);
                        }
                    } else {
                        $this->displayMessage('❌ File-level integrity check failed', 'e');
                        $this->displayMessage("📋 Do nothing. Please check your download link or content of error file. ", 'w');
                        File::delete($moduleTmpZipPath);
                    }
                } else {
                    $this->displayMessage('❌ Zip file integrity check failed', 'e');
                    File::delete($moduleTmpZipPath);
                }
            } catch(\Exception $ex) {
                $this->displayMessage('❌ Failed to verify zip file integrity', 'e');
                File::delete($moduleTmpZipPath);
            }
        }
        return [$moduleName, $moduleTmpPath, $moduleBackupPath];
    }

    /**
     * Kiểm tra loại đối số module
     * @param string $moduleArg Đối số module
     * @return string Trả về loại đối số module
     */
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
        try {
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
        } catch (\Exception $ex) {
            throw $ex;
        }
        return false;
    }

    /**
     * @param $current
     * @param $newest
     * @return mixed|string
     */
    private function updateModuleVersion($current, $newest)
    {
        try {
            if ($this->specificedVersion == '' && preg_match('/(\d+)\.(\d+)\.(\d+)/i', $newest, $matches)) {
                $current = "{$matches[1]}.{$matches[2]}.*";
            } else if (!preg_match('/(\d+)\.(\d+)\.(\d+)/i', $newest, $matches)
                && $current != $newest) {
                $current = str_replace('dev-', '', $newest);
            } else if (!empty($this->specificedVersion)) {
                $current = $this->specificedVersion;
            }
        } catch (\Exception $ex) {
            throw $ex;
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
        try {
            $modulePath = app_path("Modules/{$moduleName}");
            $versionFile = "version.json";
            $fullFilePath = "{$modulePath}/{$versionFile}";
            if (file_exists($fullFilePath)) {
                $fileContent = json_decode(file_get_contents($fullFilePath));
                $retVal = $fileContent->version;
            }
        } catch (\Exception $ex) {
            throw $ex;
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
    

    /**
     * Backup module before download
     * @param string $module Tên module
     * @return void
     */
    private function backupModule($module) {
        $retVal = "";
        $this->displayMessage("✅ Backing up module {$module} before install new version.");
        $storagePath = storage_path("/modules/backup/{$module}");
        $modulePath = app_path("Modules/{$module}");
        // Kiểm tra và tạo thư mục backup nếu chưa tồn tại
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0777, true);
        }
        // Nén thư mục module
        try {
            $zip = new \ZipArchive();
            $zipFileName = "{$storagePath}/{$module}_" . date('Ymd_His') . ".zip";
            if ($zip->open($zipFileName, \ZipArchive::CREATE) === TRUE) {
                $this->addFolderToZip($modulePath, $zip);
                $zip->close();
            } else {
                
            }
        
            // Kiểm tra số lượng file backup và xóa file cũ nhất nếu vượt quá 3
            $backupFiles = glob("{$storagePath}/*.zip");
            if (count($backupFiles) > 3) {
                usort($backupFiles, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                unlink($backupFiles[0]);
            }
            $retVal = $zipFileName;
        } catch (\Exception $ex) {
            throw $ex;
        }
        
        return $retVal;
    }
    
    /**
     * Lấy hash từ server (nếu có)
     * @param string $downloadURL URL download
     * @return array|null Trả về array chứa module_hash và files hash
     */
    private function getExpectedHashFromServer($downloadURL)
    {
        $hashURL = str_replace('/get', '.sha256', $downloadURL);
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $hashURL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && !empty($response)) {
                $responseData = json_decode($response, true);
                
                if ($responseData && isset($responseData['status']) && $responseData['status'] == 'successful') {
                    $data = $responseData['data'];
                    
                    $this->displayMessage("📋 Found hash data for module: " . $data['module_name']);
                    $this->displayMessage("📋 Module hash: " . $data['module_hash']);
                    $this->displayMessage("📋 Total files: " . $data['total_files']);
                    
                    return [
                        'module_hash' => $data['module_hash'],
                        'files' => $data['files'] ?? [],
                        'module_info' => [
                            'name' => $data['module_name'],
                            'namespace' => $data['module_namespace'],
                            'version' => $data['version'],
                            'created_at' => $data['created_at']
                        ]
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->displayMessage("⚠️ Could not fetch hash from: " . basename($hashURL) . " - " . $e->getMessage());
        }
        
        $this->displayMessage("⚠️ No hash data found on server");
        return null;
    }

    /**
     * Kiểm tra tính toàn vẹn của file zip
     * @param string $zipPath Đường dẫn đến file zip
     * @param array|null $hashData Dữ liệu hash từ server (tùy chọn)
     * @return bool
     */
    private function verifyZipIntegrity($zipPath, $hashData = null)
    {
        $this->displayMessage('🔍 Verifying zip file integrity...');
        
        // Kiểm tra 1: File có tồn tại và có thể đọc được không
        if (!file_exists($zipPath) || !is_readable($zipPath)) {
            $this->displayMessage('❌ Zip file not found or not readable', 'e');
            return false;
        }
        
        // Kiểm tra 2: File có phải là zip hợp lệ không
        $zipArchive = new \ZipArchive();
        $result = $zipArchive->open($zipPath);
        
        if ($result !== true) {
            $this->displayMessage('❌ Invalid zip file format', 'e');
            return false;
        }
        
        // Kiểm tra 3: Kiểm tra từng file trong zip có thể đọc được không
        $fileCount = $zipArchive->numFiles;
        $corruptedFiles = [];
        
        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = $zipArchive->getNameIndex($i);
            $fileStats = $zipArchive->statIndex($i);
            
            // Kiểm tra file có bị lỗi không
            if ($fileStats === false) {
                $corruptedFiles[] = $fileName;
            }
        }
        
        $zipArchive->close();
        
        if (!empty($corruptedFiles)) {
            $this->displayMessage('❌ Found corrupted files in zip: ' . implode(', ', $corruptedFiles), 'e');
            return false;
        }
        
        // Kiểm tra 4: Tính hash của file để so sánh
        $fileHash = hash_file('sha256', $zipPath);
        $this->displayMessage("📋 File hash (SHA256): $fileHash");
        
        // So sánh với expected hash nếu có
        if ($hashData && isset($hashData['module_hash'])) {
            $expectedHash = $hashData['module_hash'];
            if ($fileHash !== $expectedHash) {
                $this->displayMessage("❌ Hash mismatch! Expected: $expectedHash, Got: $fileHash", 'e');
                
                // Phân tích chi tiết sự khác biệt
                $this->compareHashDetails($zipPath, $hashData);
                
                // Kiểm tra ảnh hưởng của thứ tự file
                $this->analyzeFileOrderImpact($zipPath, $hashData);
                
                return false;
            } else {
                $this->displayMessage("✅ Hash verification passed", 's');
            }
        }
        
        $this->displayMessage('✅ Zip file integrity verified successfully', 's');
        return true;
    }

    /**
     * Kiểm tra tính toàn vẹn của từng file trong zip với hash từ server
     * @param string $zipPath Đường dẫn đến file zip
     * @param array $hashData Dữ liệu hash từ server
     * @return bool
     */
    private function verifyZipFilesIntegrity($zipPath, $hashData)
    {
        if (!isset($hashData['files']) || empty($hashData['files'])) {
            $this->displayMessage("⚠️ No file hash data available for detailed verification");
            return true;
        }

        $this->displayMessage('🔍 Verifying individual files in zip...');
        
        $zipArchive = new \ZipArchive();
        $result = $zipArchive->open($zipPath);
        
        if ($result !== true) {
            $this->displayMessage('❌ Cannot open zip file for file verification', 'e');
            return false;
        }

        $corruptedFiles = [];
        $missingFiles = [];
        $verifiedFiles = 0;
        $ingoreCheck = config('clara.ignore_check_files', []);

        foreach ($hashData['files'] as $filePath => $fileInfo) {
            if (in_array($filePath, $ingoreCheck)) {
                continue;
            }
            $expectedHash = $fileInfo['hash'];
            
            // Tìm file trong zip (thử cả path gốc và path với prefix)
            $zipIndex = $zipArchive->locateName($filePath);
            
            // Nếu không tìm thấy, thử với prefix module name
            if ($zipIndex === false) {
                $moduleName = $this->extractModuleNameFromZip($zipArchive);
                if ($moduleName) {
                    $prefixedPath = $moduleName . '/' . $filePath;
                    $zipIndex = $zipArchive->locateName($prefixedPath);
                }
            }
            
            if ($zipIndex === false) {
                $missingFiles[] = $filePath;
                continue;
            }

            // Đọc nội dung file từ zip
            $fileContent = $zipArchive->getFromIndex($zipIndex);
            if ($fileContent === false) {
                $corruptedFiles[] = $filePath;
                continue;
            }

            // Tính hash của file
            $actualHash = hash('sha256', $fileContent);
            if ($actualHash !== $expectedHash) {
                $this->displayMessage("CONTENT=\n" . $fileContent);
                $this->displayMessage("❌ Hash mismatch for file $filePath: Expected $expectedHash, Got $actualHash");
                $corruptedFiles[] = $filePath;
            } else {
                $verifiedFiles++;
            }
        }

        $zipArchive->close();

        if (!empty($missingFiles)) {
            $this->displayMessage("❌ Missing files in zip: " . implode(', ', $missingFiles), 'e');
            return false;
        }

        if (!empty($corruptedFiles)) {
            $this->displayMessage("❌ Corrupted files in zip: " . implode(', ', $corruptedFiles), 'e');
            return false;
        }

        $this->displayMessage("✅ File verification completed: $verifiedFiles files verified successfully", 's');
        return true;
    }

    /**
     * So sánh chi tiết giữa hash local và server
     * @param string $zipPath Đường dẫn đến file zip
     * @param array $hashData Dữ liệu hash từ server
     * @return void
     */
    private function compareHashDetails($zipPath, $hashData)
    {
        $this->displayMessage('🔍 Comparing hash details...');
        
        // Tính hash của file zip local
        $localHash = hash_file('sha256', $zipPath);
        $serverHash = $hashData['module_hash'] ?? 'N/A';
        
        $this->displayMessage("📋 Local hash: $localHash");
        $this->displayMessage("📋 Server hash: $serverHash");
        
        if ($localHash !== $serverHash) {
            $this->displayMessage("❌ Hash mismatch detected!", 'e');
            
            // Phân tích chi tiết
            $this->analyzeHashDifference($zipPath, $hashData);
        } else {
            $this->displayMessage("✅ Hash match!", 's');
        }
    }

    /**
     * Phân tích sự khác biệt giữa local và server
     * @param string $zipPath Đường dẫn đến file zip
     * @param array $hashData Dữ liệu hash từ server
     * @return void
     */
    private function analyzeHashDifference($zipPath, $hashData)
    {
        $zipArchive = new \ZipArchive();
        $result = $zipArchive->open($zipPath);
        
        if ($result !== true) {
            $this->displayMessage('❌ Cannot open zip for analysis', 'e');
            return;
        }

        $localFiles = [];
        $serverFiles = $hashData['files'] ?? [];
        
        // Lấy danh sách file trong zip local
        for ($i = 0; $i < $zipArchive->numFiles; $i++) {
            $fileName = $zipArchive->getNameIndex($i);
            $fileStats = $zipArchive->statIndex($i);
            
            if ($fileStats !== false) {
                $fileContent = $zipArchive->getFromIndex($i);
                $fileHash = hash('sha256', $fileContent);
                $localFiles[$fileName] = [
                    'hash' => $fileHash,
                    'size' => $fileStats['size'],
                    'modified' => date('Y-m-d H:i:s', $fileStats['mtime'])
                ];
            }
        }
        
        $zipArchive->close();

        // So sánh số lượng file
        $localFileCount = count($localFiles);
        $serverFileCount = count($serverFiles);
        
        $this->displayMessage("📊 File count comparison:");
        $this->displayMessage("   - Local files: $localFileCount");
        $this->displayMessage("   - Server files: $serverFileCount");
        
        if ($localFileCount !== $serverFileCount) {
            $this->displayMessage("❌ File count mismatch!", 'e');
            
            // Tìm file có ở local nhưng không có ở server
            $localOnly = array_diff_key($localFiles, $serverFiles);
            if (!empty($localOnly)) {
                $this->displayMessage("📋 Files in local but not in server:");
                foreach (array_keys($localOnly) as $file) {
                    $this->displayMessage("   + $file");
                }
            }
            
            // Tìm file có ở server nhưng không có ở local
            $serverOnly = array_diff_key($serverFiles, $localFiles);
            if (!empty($serverOnly)) {
                $this->displayMessage("📋 Files in server but not in local:");
                foreach (array_keys($serverOnly) as $file) {
                    $this->displayMessage("   - $file");
                }
            }
        }

        // So sánh hash của từng file
        $differentFiles = [];
        $sameFiles = 0;
        
        foreach ($serverFiles as $filePath => $serverFileInfo) {
            if (isset($localFiles[$filePath])) {
                $localHash = $localFiles[$filePath]['hash'];
                $serverHash = $serverFileInfo['hash'];
                
                if ($localHash !== $serverHash) {
                    $differentFiles[] = $filePath;
                } else {
                    $sameFiles++;
                }
            }
        }
        
        $this->displayMessage("📊 Hash comparison:");
        $this->displayMessage("   - Same files: $sameFiles");
        $this->displayMessage("   - Different files: " . count($differentFiles));
        
        if (!empty($differentFiles)) {
            $this->displayMessage("❌ Files with different hash:");
            foreach ($differentFiles as $file) {
                $localHash = $localFiles[$file]['hash'];
                $serverHash = $serverFiles[$file]['hash'];
                $this->displayMessage("   📄 $file");
                $this->displayMessage("      Local:  $localHash");
                $this->displayMessage("      Server: $serverHash");
            }
        }
    }

    /**
     * Kiểm tra thứ tự file trong zip có ảnh hưởng đến hash
     * @param string $zipPath Đường dẫn đến file zip
     * @param array $hashData Dữ liệu hash từ server
     * @return void
     */
    private function analyzeFileOrderImpact($zipPath, $hashData)
    {
        $this->displayMessage('🔍 Analyzing file order impact on hash...');
        
        $zipArchive = new \ZipArchive();
        $result = $zipArchive->open($zipPath);
        
        if ($result !== true) {
            $this->displayMessage('❌ Cannot open zip for analysis', 'e');
            return;
        }

        // Lấy danh sách file theo thứ tự trong zip
        $fileOrder = [];
        for ($i = 0; $i < $zipArchive->numFiles; $i++) {
            $fileName = $zipArchive->getNameIndex($i);
            $fileOrder[] = $fileName;
        }
        
        $zipArchive->close();

        $this->displayMessage("📋 File order in zip:");
        foreach ($fileOrder as $index => $file) {
            $this->displayMessage("   " . ($index + 1) . ". $file");
        }

        // So sánh với thứ tự từ server (nếu có)
        $serverFiles = array_keys($hashData['files'] ?? []);
        
        if (!empty($serverFiles)) {
            $this->displayMessage("📋 Expected file order from server:");
            foreach ($serverFiles as $index => $file) {
                $this->displayMessage("   " . ($index + 1) . ". $file");
            }
            
            // Kiểm tra thứ tự có khớp không
            $orderMatch = ($fileOrder === $serverFiles);
            if ($orderMatch) {
                $this->displayMessage("✅ File order matches server", 's');
            } else {
                $this->displayMessage("❌ File order differs from server", 'e');
                
                // Tìm sự khác biệt trong thứ tự
                $this->findOrderDifferences($fileOrder, $serverFiles);
            }
        }
    }

    /**
     * Tìm sự khác biệt trong thứ tự file
     * @param array $localOrder Thứ tự file local
     * @param array $serverOrder Thứ tự file server
     * @return void
     */
    private function findOrderDifferences($localOrder, $serverOrder)
    {
        $this->displayMessage("🔍 Analyzing order differences...");
        
        // Chuẩn hóa paths để so sánh
        $normalizedLocalOrder = $this->normalizePaths($localOrder);
        $normalizedServerOrder = $this->normalizePaths($serverOrder);
        
        // Tìm file có ở local nhưng không có ở server
        $localOnly = array_diff($normalizedLocalOrder, $normalizedServerOrder);
        if (!empty($localOnly)) {
            $this->displayMessage("📋 Files in local but not in server:");
            foreach ($localOnly as $file) {
                $this->displayMessage("   + $file");
            }
        }
        
        // Tìm file có ở server nhưng không có ở local
        $serverOnly = array_diff($normalizedServerOrder, $normalizedLocalOrder);
        if (!empty($serverOnly)) {
            $this->displayMessage("📋 Files in server but not in local:");
            foreach ($serverOnly as $file) {
                $this->displayMessage("   - $file");
            }
        }
        
        // Tìm file có thứ tự khác nhau
        $commonFiles = array_intersect($normalizedLocalOrder, $normalizedServerOrder);
        $orderDifferences = [];
        
        foreach ($commonFiles as $file) {
            $localIndex = array_search($file, $normalizedLocalOrder);
            $serverIndex = array_search($file, $normalizedServerOrder);
            
            if ($localIndex !== $serverIndex) {
                $orderDifferences[] = [
                    'file' => $file,
                    'local_position' => $localIndex + 1,
                    'server_position' => $serverIndex + 1
                ];
            }
        }
        
        if (!empty($orderDifferences)) {
            $this->displayMessage("📋 Files with different positions:");
            foreach ($orderDifferences as $diff) {
                $this->displayMessage("   📄 {$diff['file']}: Local #{$diff['local_position']}, Server #{$diff['server_position']}");
            }
        }
        
        // Hiển thị mapping giữa local và server paths
        $this->displayPathMapping($localOrder, $serverOrder);
    }

    /**
     * Chuẩn hóa paths để so sánh
     * @param array $paths Danh sách paths
     * @return array
     */
    private function normalizePaths($paths)
    {
        $normalized = [];
        foreach ($paths as $path) {
            // Loại bỏ prefix module name nếu có
            $normalizedPath = $this->removeModulePrefix($path);
            $normalized[] = $normalizedPath;
        }
        return $normalized;
    }

    /**
     * Loại bỏ prefix module name từ path
     * @param string $path
     * @return string
     */
    private function removeModulePrefix($path)
    {
        // Tìm module name từ path (thường là thư mục đầu tiên)
        $parts = explode('/', $path);
        if (count($parts) > 1) {
            // Loại bỏ phần đầu tiên nếu nó có thể là module name
            array_shift($parts);
            return implode('/', $parts);
        }
        return $path;
    }

    /**
     * Hiển thị mapping giữa local và server paths
     * @param array $localPaths Paths local
     * @param array $serverPaths Paths server
     * @return void
     */
    private function displayPathMapping($localPaths, $serverPaths)
    {
        $this->displayMessage("📋 Path mapping analysis:");
        
        // Tìm module name từ local paths
        $moduleName = $this->extractModuleName($localPaths);
        if ($moduleName) {
            $this->displayMessage("   🏷️ Detected module name: $moduleName");
        }
        
        // Hiển thị một số ví dụ mapping
        $examples = array_slice($localPaths, 0, 5);
        foreach ($examples as $localPath) {
            $normalizedPath = $this->removeModulePrefix($localPath);
            $this->displayMessage("   📄 Local: $localPath");
            $this->displayMessage("      → Normalized: $normalizedPath");
        }
        
        // Kiểm tra xem có path nào trong server match với normalized local không
        $matchedPaths = 0;
        foreach ($localPaths as $localPath) {
            $normalizedPath = $this->removeModulePrefix($localPath);
            if (in_array($normalizedPath, $serverPaths)) {
                $matchedPaths++;
            }
        }
        
        $this->displayMessage("   📊 Path matching: $matchedPaths/" . count($localPaths) . " files match after normalization");
    }

    /**
     * Trích xuất tên module từ paths
     * @param array $paths
     * @return string|null
     */
    private function extractModuleName($paths)
    {
        foreach ($paths as $path) {
            $parts = explode('/', $path);
            if (count($parts) > 1) {
                return $parts[0];
            }
        }
        return null;
    }

    /**
     * Trích xuất tên module từ zip archive
     * @param \ZipArchive $zipArchive
     * @return string|null
     */
    private function extractModuleNameFromZip($zipArchive)
    {
        for ($i = 0; $i < $zipArchive->numFiles; $i++) {
            $fileName = $zipArchive->getNameIndex($i);
            $parts = explode('/', $fileName);
            if (count($parts) > 1) {
                return $parts[0];
            }
        }
        return null;
    }

    /**
     * Tạo file hash cho module đã cài đặt
     * @param string $modulePath Đường dẫn đến module
     * @return void
     */
    private function createModuleHashFile($modulePath)
    {
        if (!is_dir($modulePath)) {
            return;
        }

        $hashFile = $modulePath . '/.module_hash';
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modulePath),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $hashes = [];
        foreach ($files as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($modulePath . '/', '', $file->getPathname());
                $fileHash = hash_file('sha256', $file->getPathname());
                $hashes[$relativePath] = $fileHash;
            }
        }

        $hashData = [
            'created_at' => date('Y-m-d H:i:s'),
            'files' => $hashes
        ];

        file_put_contents($hashFile, json_encode($hashData, JSON_PRETTY_PRINT));
        $this->displayMessage("📋 Module hash file created: $hashFile");
    }

    /**
     * Kiểm tra tính toàn vẹn của module đã cài đặt
     * @param string $modulePath Đường dẫn đến module
     * @return bool
     */
    private function verifyModuleIntegrity($modulePath)
    {
        $hashFile = $modulePath . '/.module_hash';
        
        if (!file_exists($hashFile)) {
            $this->displayMessage("⚠️ No hash file found for module", 'w');
            return false;
        }

        $hashData = json_decode(file_get_contents($hashFile), true);
        if (!$hashData || !isset($hashData['files'])) {
            $this->displayMessage("❌ Invalid hash file format", 'e');
            return false;
        }

        $this->displayMessage("🔍 Verifying module integrity...");
        $corruptedFiles = [];
        $missingFiles = [];

        foreach ($hashData['files'] as $relativePath => $expectedHash) {
            $filePath = $modulePath . '/' . $relativePath;
            
            if (!file_exists($filePath)) {
                $missingFiles[] = $relativePath;
                continue;
            }

            $currentHash = hash_file('sha256', $filePath);
            if ($currentHash !== $expectedHash) {
                $corruptedFiles[] = $relativePath;
            }
        }

        if (!empty($missingFiles)) {
            $this->displayMessage("❌ Missing files: " . implode(', ', $missingFiles), 'e');
            return false;
        }

        if (!empty($corruptedFiles)) {
            $this->displayMessage("❌ Corrupted files: " . implode(', ', $corruptedFiles), 'e');
            return false;
        }

        $this->displayMessage("✅ Module integrity verified successfully", 's');
        return true;
    }

    private function addFolderToZip($folder, &$zip, $folderInZip = '') {
        if (!is_dir($folder)) {
            return;
        }
        $handle = opendir($folder);
        while (false !== ($entry = readdir($handle))) {
            if ($entry != '.' && $entry != '..') {
                $path = "{$folder}/{$entry}";
                $pathInZip = $folderInZip ? "{$folderInZip}/{$entry}" : $entry;
                if (is_dir($path)) {
                    $zip->addEmptyDir($pathInZip);
                    $this->addFolderToZip($path, $zip, $pathInZip);
                } else {
                    $zip->addFile($path, $pathInZip);
                }
            }
        }
        closedir($handle);
    }

    /**
     * @param $modulePath
     * @return void
     */
    private function installModuleV2($moduleName, $modulePath, $moduleTmpPath, $moduleBackupPath, $isRollback = false)
    {
        try {
            $fullTmpPath = "{$moduleTmpPath}/{$moduleName}";
            $fullModulePath = "{$modulePath}/{$moduleName}";
            if (!$isRollback) {
                $this->copyTmpToRealPath($fullTmpPath, $fullModulePath, true);  
            }
            $moduleSpecs = ModuleUtil::getModuleSpecs($fullTmpPath);

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

            // $this->makeModuleJsonLock($moduleLock);
            Artisan::call("module:providers");
            // system('composer update');
            // migrate module
            ModuleUtil::runMigration($moduleConfig, $isRollback);
            $this->response([
                "status" => "successful",
                "message" => "✅ Install $currentModuleName module successfully. \n📋 Current version is {$latestVersion}",
                "module" => [
                    "name" => $currentModuleName,
                    "namespace" => $currentModuleNamespace,
                ],
            ]);
            \Module::action("module_made", $moduleConfigs['modules'][$currentModuleNamespace]);
        } catch (\Exception $ex) {
            $this->displayMessage("❌ Error when install module: " . $ex->getMessage(), 'e');
            $this->displayMessage("⏪ Starting rollback module...");
            $this->rollback($moduleName, $modulePath, $moduleBackupPath);
        }
    }

    /**
     * Copy tmp module files to real path
     * 
     * @param string $sourcePath
     * @param string $destinationPath
     * @return void
     */
    private function copyTmpToRealPath($sourcePath, $destinationPath, $first = false) {
        if (!file_exists($sourcePath)) {
            throw new \Exception("Source path not exists [{$sourcePath}]");
        }
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }
        if ($first) {
            $this->displayMessage("✅ Move from temporary to module source.");
        }
        $files = scandir($sourcePath);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $sourceFile = "{$sourcePath}/{$file}";
            $destinationFile = "{$destinationPath}/{$file}";
            if (is_dir($sourceFile)) {
                $this->copyTmpToRealPath($sourceFile, $destinationFile);
            } else {
                copy($sourceFile, $destinationFile);
            }
        }
        // $this->displayMessage("✅ Copy module files to real path successfully", 's');
    }

    /**
     * Remove temporary module files
     * 
     * @param string $tmpPath
     * @return void
     */
    private function removeTemporaryModule($tmpPath, $isFirst = false) {
        try {
            if ($isFirst) {
                $this->displayMessage("✅ Remove temporary module files.");
            }
            if (is_dir($tmpPath)) {
                $files = scandir($tmpPath);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    $filePath = "{$tmpPath}/{$file}";
                    if (is_dir($filePath)) {
                        $this->removeTemporaryModule($filePath);
                    } else {
                        unlink($filePath);
                    }
                }
                rmdir($tmpPath);
            }
        } catch (\Exception $ex) {
            throw new \Exception("Error when remove temporary module files: " . $ex->getMessage());
        }
    }

    /**
     * Rollback module files
     * 
     * @param string $moduleName
     * @param string $modulePath
     * @param string $moduleBackupPath
     * @return void
     */
    private function rollback($moduleName, $modulePath, $moduleBackupPath)
    {
        try {
            $fullModulePath = "{$modulePath}/{$moduleName}";
            $this->displayMessage("Rollback module to: " . $fullModulePath);
            if (file_exists($moduleBackupPath)) {
                // Xóa thư mục module hiện tại nếu tồn tại
                if (file_exists($fullModulePath)) {
                    $this->rrmdir($fullModulePath);
                    $this->displayMessage("✅ Removed existing module directory: {$fullModulePath}");
                }

                 // Tạo thư mục module nếu chưa tồn tại
                if (!file_exists($fullModulePath)) {
                    mkdir($fullModulePath, 0755, true);
                }
                $rollbackTo = $this->getVersionFromBackupZip($moduleBackupPath);
                $this->displayMessage("📋 Rollback this module to version: {$rollbackTo}. ");
                // Giải nén file backup
                $zipArchive = new \ZipArchive();
                $result = $zipArchive->open($moduleBackupPath);
                if ($result === true) {
                    $zipArchive->extractTo($fullModulePath);
                    $zipArchive->close();
                    $this->displayMessage("✅ Module restored successfully from backup: {$moduleBackupPath}", 's');
                    $this->displayMessage("✅ Starting install module...");
                    $this->installModuleV2($moduleName, $modulePath, app_path("Modules"), "", true);
                    return true;
                } else {
                    throw new \Exception("Failed to open backup zip file: {$moduleBackupPath}");
                }
            } else {
                throw new \Exception("Backup file not found: {$moduleBackupPath}");
            }
        } catch (\Exception $ex) {
            throw new \Exception("Error when rollback module files: " . $ex->getMessage());
        }
    }


    /**
     * 
     */
    private function getVersionFromBackupZip($zipPath)
    {
        $version = null;
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) === true) {
            // Tìm file version.json trong zip
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $fileName = $zip->getNameIndex($i);
                if (preg_match('/version\.json$/', $fileName)) {
                    $content = $zip->getFromIndex($i);
                    $json = json_decode($content, true);
                    if (isset($json['version'])) {
                        $version = $json['version'];
                    }
                    break;
                }
            }
            $zip->close();
        }
        return $version;
    }

    /**
     * Xóa thư mục và tất cả nội dung bên trong một cách đệ quy
     * 
     * @param string $dir Đường dẫn thư mục cần xóa
     * @return void
     */
    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->rrmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
