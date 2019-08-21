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
        $this->value = isset($args[0]) ? $args[0] : ''; // get the value, the first argument is always the value
        if (!is_string($this->value)) {
            $this->value = '';
        }
        if (count($this->getListeners()->where('hook', $action)) > 0) {
            $this->value = '';
            $this->getListeners()->where('hook', $action)->each(function ($listener) use ($action, $args, $isMultiLayer) {
                $parameters = [];
                // $args[0] = $this->value;
                for ($i = 0; $i < $listener['arguments']; $i++) {
                    if (isset($args[$i])) {
                        $value = $args[$i];
                        $parameters[] = $value;
                    } else {
                        $parameters[] = [];
                        break;
                    }
                }
                if ($isMultiLayer || $isMultiLayer === null) {
                    $this->value .= call_user_func_array($this->getFunction($listener['callback']), $parameters);
                } else if ($isMultiLayer === false) {
                    $this->value = call_user_func_array($this->getFunction($listener['callback']), $parameters);
                    return false;
                }
            });
        }

        return $this->value;
    }
}
