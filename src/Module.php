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
    public function option($option = "")
    {
        return getModuleOption($option);
    }
    public function allOptions($module = null)
    {
        return getAllModuleOptions($module);
    }
}
