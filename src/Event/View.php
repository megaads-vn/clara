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
    public function fire($action, $args)
    {
        $this->value = isset($args[0]) ? $args[0] : ''; // get the value, the first argument is always the value
        if (!is_string($this->value)) {
            $this->value = '';
        }
        if ($this->getListeners()) {
            $this->getListeners()->where('hook', $action)->each(function ($listener) use ($action, $args) {
                $parameters = [];
                // $args[0] = $this->value;
                for ($i = 0; $i < $listener['arguments']; $i++) {
                    $value = $args[$i];
                    $parameters[] = $value;
                }
                $this->value = call_user_func_array($this->getFunction($listener['callback']), $parameters);
            });
        }

        return $this->value;
    }
}
