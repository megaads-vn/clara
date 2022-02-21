<?php

namespace Megaads\Clara\Listeners;

use Megaads\Clara\Event\RoutePerformanceEvent;
use Megaads\Clara\Utils\Traffic;

class RoutePerformanceListener
{
    private $traffic;

    public function __construct(Traffic $traffic)
    {
        $this->traffic = $traffic;
    }

    public function handle(RoutePerformanceEvent $event)
    {
        $secs = $event->getSecs();
        $req = [
            'path' => request()->path(),
            'secs' => $secs,
            'module' => $this->traffic->getRouteInfo()
        ];

        if (config('clara.logs.text')) {
            $this->traffic->text($req, 'performance');
        }

        if (config('clara.logs.json')) {
            $this->traffic->log($req, 'performance');
        }
    }
}