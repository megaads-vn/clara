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
     * Check if the module is active.
     */
    public function isActive($moduleName)
    {
        $retval = false;
        $moduleConfigs = ModuleUtil::getAllModuleConfigs();
        foreach ($moduleConfigs['modules'] as $item) {
            if ($item['name'] === $moduleName && $item['status'] === "enable") {
                $retval = true;
                break;
            }
        }
        return $retval;
    }
    /**
     * Get the current module detail.
     */
    public function this()
    {
        $retval = null;
        $moduleConfigs = ModuleUtil::getAllModuleConfigs();
        $moduleName = $this->getCaller();
        foreach ($moduleConfigs['modules'] as $item) {
            if ($item['name'] === $moduleName) {
                $retval = $item;
                break;
            }
        }
        return $retval;
    }
    /**
     * Get module is calling current function.
     */
    public function getCaller()
    {
        return getCallerModule();
    }
    public function option($option = "", $value = null)
    {
        if ($value == null) {
            return getModuleOption($option);
        } else {
            return setModuleOption($option, $value);
        }
    }
    public function allOptions($module = null)
    {
        return getAllModuleOptions($module);
    }
}
