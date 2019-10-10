<?php
function getCallerModule()
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
function getModuleOption($option = "")
{
    $retval = null;
    $module = getCallerModule();
    $key = $module == null ? $option : $module . '.' . $option;
    $retval = \Megaads\Clara\Models\Option::where("key", "=", $key)->first();
    if ($retval != null) {
        $retval = $retval->value;
    }
    return $retval;
}
function setModuleOption($option = "", $value = "")
{
    $retval = null;
    $module = getCallerModule();
    $key = $module == null ? $option : $module . '.' . $option;
    $option = new \Megaads\Clara\Models\Option();
    $option->key = $key;
    $option->value = $value;
    if ($option->save()) {
        $retval = $option;
    }
    return $retval;
}
function getAllModuleOptions($module = null)
{
    $retval = [];
    if ($module == null) {
        $module = getCallerModule();
    }
    if ($module != null) {
        $retval = \Megaads\Clara\Models\Option::where("key", "LIKE", $module . ".%")->get()->toArray();
    }
    return $retval;
}
