<?php

namespace Megaads\Clara\Providers;

use \Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Megaads\Clara\Event\IncommingRequestDataEvent;
use Megaads\Clara\Event\IncommingRequestEvent;
use Megaads\Clara\Event\RoutePerformanceEvent;
use Megaads\Clara\Listeners\IncommingRequestListener;
use Megaads\Clara\Listeners\RoutePerformanceListener;

class TrafficEventServiceProvider extends EventServiceProvider
{
    protected $listen = [
        IncommingRequestEvent::class => [
            IncommingRequestListener::class
        ]
    ];

    public function boot()
    {
        parent::boot();
    }

    public function register()
    {
        
    }
}