<?php

namespace Megaads\Clara\Event;

class Action extends AbtractEvent
{
    /**
     * Filters a value.
     *
     * @param string $action Name of action
     * @param array  $args   Arguments passed to the filter
     *
     * @return string Always returns the value
     */
    public function fire($action, $args)
    {
        if ($this->getListeners()) {
            $this->getListeners()->where('hook', $action)->each(function ($listener) use ($action, $args) {
                $parameters = [];
                for ($i = 0; $i < $listener['arguments']; $i++) {
                    if (isset($args[$i])) {
                        $parameters[] = $args[$i];
                    }
                }
                $fn = $this->getFunction($listener['callback']);
                if (is_string($fn) && strpos($fn, '@')) {
                    \App::call($fn, $parameters);
                } else {
                    call_user_func_array($fn, $parameters);
                }
            });
        }
    }
}
