<?php

namespace Megaads\Clara\Event;

class IncommingRequestEvent
{
    private $secs;

    public function __construct($secs)
    {
        $this->secs = $secs;
    }

    public function getSecs()
    {
        return $this->secs;
    }
}