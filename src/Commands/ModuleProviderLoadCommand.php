<?php 
namespace Megaads\Clara\Commands;

use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Megaads\Clara\Utils\ModuleUtil;

class ModuleProviderLoadCommand extends AbtractCommand 
{

    /**
     * This parameter store content of file config/app.php
     * 
     */
    protected $appConfig = [];

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'module:providers
                    {module=n/a : Name of module run migration}
                    {--action=n/a : Specific action}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run module migration with multiple localization if it enable';
    
    /**
     * Execute the console command.
     */
    public function handle() {
        $provder = "";
        $single = false;
        $moduleProviders = $this->getModulesProvides();
        $action = $this->option('action');
        $module = $this->argument('module');
        if ($module !== 'n/a') {
            $moduleDir = app_path() . '/Modules/' . $module;
            if (file_exists($moduleDir)) {
                $namespace = $this->buildNamespace($module);
                $jsonFile = $moduleDir . '/module.json';
                if (file_exists($jsonFile)) {
                    $inclued = json_decode(file_get_contents($jsonFile));
                    if (isset($inclued->providers)) {
                        $provder = $inclued->providers->class;
                        $single = true;
                    }
                }
            }
        }
        $providers = $this->readCoreProviders();
        $appFile = base_path() . '/config/app.php';
        $appConfig = file_get_contents($appFile);
        $changed = false;
        foreach ($moduleProviders as $item) {
            $exists = $this->checkProviderExists($appConfig, $item->class);
            if ($exists && $single && $module !== 'n/a' && $action == 'remove') {
                $this->removeLoadedProviderClass($appConfig, $provder);
                break;
            } else if ($exists) {
                continue;
            }
            if (isset($item->after)) {
                $afterProvider = str_replace('"', '', json_encode($item->after));
                $classNeedInsert = $item->class;
                $findLineReg = "/^((?!\/\/).)*($afterProvider)/m";
                if (preg_match($findLineReg, $appConfig, $matches)) {
                    $this->appendNewLoadedProviders($appConfig, $matches[0], $classNeedInsert);
                    $changed = true;
                }
            } else {
                $lastProviders = end($providers) . "::class";
                $this->appendNewLoadedProviders($appConfig, $lastProviders, $item->class);
                $changed = true;
            }
        }
        if ($changed) {
            $retval = $this->writeNewConfigContent($appConfig);
            $this->info($retval);
        }
    }

    /**
     * 
     * @param array moduleProviders
     */
    protected function getModulesProvides() {
        // Load enabled modules
        $moduleProviders = [];
        $moduleDir = app_path() . '/Modules/';
        if (is_dir($moduleDir)) {
            $moduleConfigs = ModuleUtil::getAllModuleConfigs();
            $moduleProviders = [];
            foreach ($moduleConfigs['modules'] as $moduleConfig) {
                if ($moduleConfig['status'] == 'disable') {
                    continue;
                }
                $module = $moduleConfig['name'];
                $currentModuleDir = app_path() . '/Modules/' . $module;
                $jsonFile = $currentModuleDir . '/module.json';
                if (file_exists($jsonFile)) {
                    $inclued = json_decode(file_get_contents($jsonFile));
                    if (isset($inclued->providers)) {
                        $moduleProviders[] = $inclued->providers;
                    }
                }
            }
        }
        return $moduleProviders;
    }

    /**
     * @param
     * 
     * @return array providers
     */
    protected function readCoreProviders() {
        $providers = [];
        $appFile = base_path() . '/config/app.php';
        if (file_exists($appFile)) {
            $appData = include $appFile;
            $this->appConfig = $appData;
            if (isset($appData['providers'])) {
                $providers = $appData['providers'];
            }
        }
        return $providers;
    }

    /**
     * 
     */
    protected function writeNewConfigContent($content) {
        $retval = 'Success! New content appended.';
        $appFile = base_path() . '/config/app.php';
        // echo $content;die;
        try {
            $myfile = fopen($appFile, "w");
            fwrite($myfile, $content);
            fclose($myfile);
        } catch (Exception $ex) {
            $retval = $ex->getMessage();
        }
        return $retval;
    }

    /**
     * 
     * @param array content
     * @param string foundString
     * @param string insert
     * 
     */
    protected function appendNewLoadedProviders(&$content, $foundString, $insert) {
        $pos = strripos($content, $foundString);
        $pos = $pos + strlen($foundString);
        if (false !== $pos) {
            $content = substr($content, 0, $pos) .  ", " . PHP_EOL . "\t\t" . $insert . substr($content, $pos);
        }  
    }

    /**
     * 
     * 
     */
    protected function removeLoadedProviderClass(&$content, $removeProvider) {
        $removeProvider = str_replace('"', '', json_encode($removeProvider));
        $removeLineReg = "/^((?!\/\/).)*($removeProvider)/m";
        
        if (preg_match($removeLineReg, $content, $matches)) {
            $content = preg_replace($removeLineReg, "", $content);
            $retval = $this->writeNewConfigContent($content);
        }
    }

    /**
     * 
     * @param string needed
     * @param string haystack
     */
    protected function checkProviderExists($needed, $haystack) {
        $retval = false;
        $haystack = str_replace('"', '', json_encode($haystack));
        $reg = "/^((?!\/\/).)*$haystack/m";
        if (preg_match($reg, $needed, $matches)) {
            $retval = true;
        }
        return $retval;
    }

    /**
     * 
     * @param array array
     * @param string insert
     * @param string|integer position
     * 
     * @return null
     */
    protected function insertArrayAtPosition( &$array, $insert, $position ) {
        if (is_int($position)) {
            array_splice($array, ($position + 1), 0, $insert);
        } else {
            $pos   = array_search($position, array_keys($array));
            $array = array_merge(
                array_slice($array, 0, $pos),
                $insert,
                array_slice($array, $pos)
            );
        }
    }
}