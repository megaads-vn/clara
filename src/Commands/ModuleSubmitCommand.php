<?php 
namespace Megaads\Clara\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ModuleSubmitCommand extends AbtractCommand 
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'module:submit 
                            {--module= : [string] Module name}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Submit a new module or Update exist module';
    /**
     * Default repository branch name
     *
     * @var string
     */
    protected $defaultBranch = 'master';
    /**
     * Execute the console command.
     */
    public function handle() {
        $options = $this->options();
        $moduleName = $options['module'];
        if (!$moduleName) {
            $moduleName = $this->ask("What's name of module want to submit?");
        }
        $modulePath = __DIR__.'/../../../../../app/Modules/' . $moduleName;
        $moduleJson = $modulePath . '/module.json';
        $moduleData = NULL;
        $submitRs = NULL;
        if (file_exists($moduleJson)) {
            $jsonContent = file_get_contents($moduleJson);
            $jsonContent = json_decode($jsonContent);
            $moduleData = $this->prepareModuleInfo($jsonContent);
            $zippedFile = $this->compressDirectory($moduleData['name_space'], $modulePath);
            $moduleData['package_url'] = $this->uploadModule($moduleData['name_space'], $zippedFile);
            if (!empty($moduleData['package_url'])) {
                $submitRs = $this->saveOrUpdateModule($moduleData);
            }
        }
        if (isset($submitRs->status) && $submitRs->status == 'successfull') {
            $this->info("Module $moduleName was submited successfully.");
            $this->removeDirectory($moduleName);
        } else {
            $msg = '';
            if (isset($submitRs->message)) {
                $msg = $submitRs->message;
            }
            $this->error("Has something wrong when submit module $moduleName. \n $msg");
        }
    }

    private function prepareModuleInfo($moduleInfo) {
        $retval = [
            'name' => '',
            'description' => '', 
            'name_space' => '',
            'package_url' => '',
            'image_url' => '',
            'content' => '', 
            'price' => 0,
            'category_id' => 1,
            'author_email' => '',
            'author_name' => '',
            'repository' => '',
            'status' => 'active',
            'meta' => []
        ];

        if (isset($moduleInfo->name)) {
            $retval['name'] = $moduleInfo->name;
            $retval['name_space'] = $this->formatModuleFolderName($moduleInfo->name, false);
        }
        if (isset($moduleInfo->image_url)) {
            $retval['image_url'] = $moduleInfo->image_url;
        }
        if (isset($moduleInfo->description)) {
            $retval['description'] = $moduleInfo->description;
        }
        if (isset($moduleInfo->require)) {
            $retval['meta'] = [
                'require' => $moduleInfo->require
            ];
        }
        if (isset($moduleInfo->author_email)) {
            $retval['author_email'] = $moduleInfo->author_email;
        }
        if (isset($moduleInfo->author_name)) {
            $retval['author_name'] = $moduleInfo->author_name;
        }
        if (isset($moduleInfo->repository)) {
            $retval['repository'] = $moduleInfo->repository;
        }
        return $retval;
    }

    /**
     * Validate repository url format
     *
     * @param [type] $repoUrl
     * @return void
     */
    private function validateRepoUrl($repoUrl) {
        $retval = [
            'type' => '',
            'status' => false
        ];
        // Check repo is bitbucket
        if (preg_match('/bitbucket.org/i', $repoUrl)) {
            $retval['type']='bitbucket';
        }
        // Or repo is github
        if (preg_match('/github.com/i', $repoUrl)) {
            $retval['type'] = 'github';
        }
        if (strpos($repoUrl, '.git') !== false) {
            $retval['status'] = true;
        }
        return $retval;
    }
    /**
     * Auto generate module name from repository url.
     *
     * @param [type] $repoUrl
     * @param [type] $type
     * @return void
     */
    private function getModuleName($repoUrl, $type) {
        $retval = '';
        $urls = explode('/', $repoUrl);
        $lastPath = end($urls);
        $lastPath = str_replace('.git', '', $lastPath);
        $arrLastPath = explode('-', $lastPath);
        if (count($arrLastPath) > 1) {
            foreach ($arrLastPath as $item) {
                $retval .= ucfirst($item);
            }
        } else {
            $retval = ucfirst($arrLastPath[0]);
        }
        return $retval;
    }
    /**
     * Process to download module from repository
     *
     * @param [type] $repo
     * @param [type] $branch
     * @param [type] $name
     * @return void
     */
    private function downloadPackage($repo, $branch, $name) {
        //Check module is exists on modules directory
        $modulePath = $this->formatModuleFolderName($name);
        $result = $this->checkModuleDirectory($modulePath);
        $findGitLib = new Process("which git");
        $findGitLib->run();
        $gitPath = $findGitLib->getOutput();
        $gitPath = str_replace("\n", "", $gitPath);
        if ($gitPath) {
           $processResult = $this->processWithRepository($result, $repo, $modulePath, $branch, $gitPath);
           if ($processResult) {
                $this->compressDirectory($name);
                $this->removeDirectory($name);
           }
        }
    }
    /**
     * Save or Update a module to database
     *
     * @param [type] $params
     * @return void
     */
    private function saveOrUpdateModule($params) {
        $retval = NULL;
        $retval = $this->sendRequest('module/create', 'POST', $params);
        return $retval;
    }
    /**
     * Compress module directory
     *
     * @param [type] $moduleName
     * @return void
     */
    private function compressDirectory($moduleNamespace, $modulePath) {
        $storage = Storage::disk('public');
        if (!$storage->exists('modules')) {
            $storage->makeDirectory('modules');
        }
        $zipFileName = $moduleNamespace . ".zip";
        $zipFile = new \ZipArchive();
        $zipFile->open(storage_path('app/public/modules/' . $zipFileName), \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modulePath));
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = $moduleNamespace . '/' . substr($filePath, strlen($modulePath) + 1);
                $zipFile->addFile($filePath, $relativePath);
            }
        }
        $zipFile->close();
        return storage_path('app/public/modules/' . $zipFileName);
    }
    /**
     * Delete module directory after compress as zip file
     *
     * @param [type] $moduleName
     * @return void
     */
    private function removeDirectory($moduleName) {
        $moduleName = $this->formatModuleFolderName($moduleName, false);
        $modulePath = storage_path('app/public/modules/' . $moduleName . '.zip');
        $deleteDir = new Process("rm -rf $modulePath");
        $deleteDir->run();
    }
    /**
     * Clone or pull update from repository url
     *
     * @param [type] $type
     * @param [type] $repo
     * @param [type] $path
     * @param [type] $branch
     * @param [type] $gitPath
     * @return void
     */
    private function processWithRepository($type, $repo, $path, $branch, $gitPath) {
        $retval = true;
        if ($type == 'created') {
            echo "Cloning....\n";
            $command = "$gitPath clone $repo $path";
            $clonePackage = new Process($command);
            $clonePackage->run();
            if (!$clonePackage->isSuccessful()) {
                $retval = false;
            }
            if ($branch != $this->defaultBranch) {
                $this->processWithRepository('existed', $repo, $path, $branch, $gitPath);
            }
        } else {
            echo "Checking out branch $branch....\n";
            $checkoutCommand = "cd $path && $gitPath fetch && $gitPath checkout $branch";
            $output = shell_exec($checkoutCommand);
        }
        return $retval;
    }
    /**
     * Check module is exists in public/modules directory
     *
     * @param [type] $modulePath
     * @return void
     */
    private function checkModuleDirectory($modulePath) {
        $retval = 'created';
        if (!file_exists($modulePath)) {
            mkdir($modulePath, 0777);
        } else {
            $retval = 'existed';
        }
        return $retval;
    }
    /**
     * Auto generate module namespace from module name
     *
     * @param [type] $moduleName
     * @return void
     */
    private function buildModuleNamespace($moduleName) {
        $retval = '';
        $pieces = preg_split('/(?=[A-Z])/', $moduleName);
        foreach($pieces as $piece) {
            if ($piece !== '') {
                $retval .= strtolower($piece) . '-';
            }
        }
        return rtrim($retval, '-');
    }

    private function formatModuleFolderName($moduleName, $getPath = true) {
        $moduleName = preg_replace('/\s/i', '-', $moduleName);
        $retval = public_path('/modules/' . $moduleName);
        if (!$getPath) {
            $retval = $moduleName;
        }
        return strtolower($retval);
    }

    private function uploadModule($fileName, $filePath) {
        $result = NULL;
        $url = config('clara.app_store_url', '');
        if (empty($url)) {
            $this->error("Please provide app_store_url on configuration file.");
            return NULL;
        }
        $fullUploadUrl = $url . 'module/upload';
        $headers = ["Content-Type:multipart/form-data"];
        $postData = [
            'file' => curl_file_create("$filePath"),
            'fileName' => $fileName
        ];
        $ch = curl_init($fullUploadUrl);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        $response = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response);
        if (isset($response->status) && $response->status == 'successful') {
            $result = $response->file_url;
        }
        return $result;
    }

    private function sendRequest($endPoint, $method = 'GET', $params, $headers = []) {
        $url = config('clara.app_store_url', '');
        if (empty($url)) {
            $this->error("Please provide app_store_url on configuration file.");
            return NULL;
        }
        if (empty($headers)) {
            $headers = [
                'Content-Type: application/json'
            ];
        }
        $ch = curl_init($url . '/' . $endPoint);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POST, true);
        if (strtolower($method) == 'post') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }

        $response = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response);
        return $response;
    }
}