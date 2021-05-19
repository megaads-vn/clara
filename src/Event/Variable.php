<?php

namespace Megaads\Clara\Event;

class Variable extends AbtractEvent
{
    protected $retVal = [
        'status' => 'fail',
        'result' => ''
    ];
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
                $this->retVal['status'] = 'successful';
                $this->retVal['result'] = call_user_func_array($this->getFunction($listener['callback']), $parameters);
            });
        }

        return $this->retVal;
    }

}
