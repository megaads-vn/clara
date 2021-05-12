<?php

namespace Megaads\Clara\Utils;

use Illuminate\Support\Facades\File;

class ModuleUtil
{
    /**
     * Add config value to module setting
     */
    public static function setModuleConfig($configs = [])
    {
        $data = [];
        if (File::exists(base_path('module.json'))) {
            $jsonString = file_get_contents(base_path('module.json'));
            $data = json_decode($jsonString, true);
        }
        // Update Key
        foreach ($configs as $key => $value) {
            $data[$key] = $value;
        }
        // Write File
        $newJsonString = json_encode($data, JSON_PRETTY_PRINT);
        file_put_contents(base_path('module.json'), stripslashes($newJsonString));
    }
    /**
     * Get all module config values
     */
    public static function getAllModuleConfigs($configs = [])
    {
        $retval = [];
        if (File::exists(base_path('module.json'))) {
            $jsonString = file_get_contents(base_path('module.json'));
            $retval = json_decode($jsonString, true);
        }
        return $retval;
    }

    public static function getModuleSpecs($modulePath)
    {
        $retval = [];
        $moduleSpecFile = $modulePath . '/module.json';
        if (File::exists($moduleSpecFile)) {
            $jsonString = file_get_contents($moduleSpecFile);
            $retval = json_decode($jsonString, true);
        }
        return $retval;
    }

    public static function linkModuleAssets($moduleConfig = null)
    {
        $retval = false;
        if ($moduleConfig !== null) {
            // make asset directory
            $assetDir = public_path() . '/modules';
            if (!File::isDirectory($assetDir)) {
                File::makeDirectory($assetDir);
                $retval = true;
            }
            // create tmp link
            $srcAssetDir = app_path() . '/Modules' . '/' . $moduleConfig['name'] . '/Resources/Assets';
            if (!File::isDirectory($assetDir . '/tmp')) {
                File::makeDirectory($assetDir . '/tmp');
            }
            // link module assets and remove tmp link
            if (File::isDirectory($srcAssetDir)) {
                if (!windows_os()) {
                    system('ln -s ' . $srcAssetDir . ' ' . $assetDir . '/tmp');
                } else {
                    $mode = $this->isDirectory($target) ? 'J' : 'H';
                    exec("mklink /{$mode} \"{$assetDir}\" \"{$srcAssetDir}\"");
                }
                File::move($assetDir . '/tmp/Assets', $assetDir . '/' . $moduleConfig['namespace']);
                $retval = true;
            }
        }
        return $retval;
    }

    public static function unlinkModuleAssets($moduleConfig = null)
    {
        $retval = false;
        $assetDir = public_path() . '/modules';
        if ($moduleConfig !== null) {
            $assetDir .= '/' . $moduleConfig['namespace'];
            if (File::exists($assetDir)) {
                File::delete($assetDir);
                $retval = true;
            }
        }
        return $retval;
    }
}
