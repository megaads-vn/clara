<?php

namespace Megaads\Clara\Listeners;

use Illuminate\Support\Facades\Config;
use Megaads\Clara\Event\IncommingRequestEvent;
use Megaads\Clara\Utils\Traffic;
use Traffic\Events\IncomingRequestEvent;

class IncommingRequestListener
{
    private $traffic;

    public function __construct(Traffic $traffic)
    {
        $this->traffic = $traffic;
    }

    public function handle(IncommingRequestEvent $event)
    {
        if (Config::get('clara.logs.text')) {
            $this->traffic->text($this->traffic->getPayload());
        }

        if (Config::get('clara.logs.json')) {
            $payload = $this->traffic->getPayload();
            $moduleName = array_pop($payload);
            $payload['secs'] = $event->getSecs();
            $payload['module'] = $moduleName;
            $this->traffic->log($payload);
        }
    }
}