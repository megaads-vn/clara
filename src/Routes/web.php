<?php

use Illuminate\Routing\Router;

Route::name('traffic')
    ->prefix('traffic')
    ->middleware('web')
    ->group(function ($router) {
        $router->any('/api/logs/{group}', [
            'as' => 'logs.today',
            'uses' => 'Megaads\Clara\Controllers\TrafficController@today'
        ]);
        $router->any('/api/logs/{group}/since/{days}', [
            'as' => 'logs.since.days',
            'uses' => 'Megaads\Clara\Controllers\TrafficController@fetchSince'
        ]);

        $router->get('/requests', [
            'as' => 'views.requests',
            'uses' => 'Megaads\Clara\Controllers\HomeController@requestIndex'
        ]);
//        $router->fallback(function() {
//            return sprintf("[Traffic]: %s leads to nowhere!", request()->path());
//        });
    });