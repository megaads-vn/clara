<?php

namespace Megaads\Clara\Event;

class View extends AbtractEvent
{
    protected $value = '';

    /**
     * Filters a value.
     *
     * @param string $action Name of filter
     * @param array $args Arguments passed to the filter
     *
     * @return string Always returns the value
     */
    public function fire($action, $args = [], $isMultiLayer = true)
    {
        $this->value = isset($args[0]) ? $args[0] : ''; 
        if (!is_string($this->value)) {
            $this->value = '';
        }
        // Initialize a stack to store the result of each sub-view
        $bufferStackArr = [];
        // Check the event
        if (count($this->getListeners()->where('hook', $action)) > 0) {
            $this->getListeners()->where('hook', $action)->each(function ($listener) use ($action, $args, $isMultiLayer, &$bufferStackArr) {
                $parameters = [];
                // Get the necessary parameters
                for ($i = 0; $i < $listener['arguments']; $i++) {
                    $parameters[] = $args[$i] ?? [];
                }
                $result = $this->callListenerFunction($listener, $parameters);
                // Add the result to the stack
                $bufferStackArr[$action][] = $result;
            });
        }

        // Get the final result from the stack
        if ($isMultiLayer || $isMultiLayer === null) {
            // Instead of concatenating each time, just get the result from the last element of the stack
            $bufferStack = isset($bufferStackArr[$action]) ? $bufferStackArr[$action] : [];
            $this->value = join('', $bufferStack);
        } else if ($isMultiLayer === false) {
            $bufferStack = isset($bufferStackArr[$action]) ? $bufferStackArr[$action] : [];
            $this->value = reset($bufferStack);
        }
        return $this->value;
    }

    private function callListenerFunction($listener, $parameters)
    {
        $retval = null;
        $fn = $this->getFunction($listener['callback']);
        if (is_string($fn) && strpos($fn, '@')) {
            $retval = \App::call($fn, $parameters);
        } else {
            $retval = call_user_func_array($fn, $parameters);
        }
        return $retval;
    }
}
