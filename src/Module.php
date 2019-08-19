<?php

namespace Megaads\Clara;

use Megaads\Clara\Event\EventManager;
use Megaads\Clara\Utils\ModuleUtil;

class Module extends EventManager
{
    /**
     * Get all modules.
     */
    public function all()
    {
        $moduleConfigs = ModuleUtil::getAllModuleConfigs();
        return $moduleConfigs['modules'];
    }
    /**
     * Get module is calling current function.
     */
    public function getCaller()
    {
        $retval = null;
        $traces = debug_backtrace();
        foreach ($traces as $trace) {
            $matches = array();
            if (!array_key_exists('file', $trace)) {
                continue;
            }
            preg_match('/app\/Modules\/([A-Z0-9a-z-_]+)/', $trace['file'], $matches);
            if (count($matches) == 2) {
                $retval = $matches[1];
                break;
            }
        }
        return $retval;
    }
    public function getOption($option = "")
    {
        $retval = null;
        $module = $this->getCaller();
        $key = $module == null ? $option : $module . '.' . $option;
        $retval = \DB::table("clara_option")->where("key", "=", $key)->first();
        if ($retval != null) {
            $retval = $retval->value;
        }
        return $retval;
    }
}
