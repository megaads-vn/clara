<?php
namespace Megaads\Clara\Middlewares;

use Closure;
use Megaads\Clara\Event\IncommingRequestEvent;
use Megaads\Clara\Event\RoutePerformanceEvent;

class TrafficMonitoring
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!preg_match('/traffic/i', $request->path())) {
//            event(new IncommingRequestEvent());
            $startTime = microtime(true);
            $response = $next($request);
            $timeTakenInSeconds = number_format((microtime(true) - $startTime), 4);
            event(new IncommingRequestEvent($timeTakenInSeconds));
        } else {
            $response = $next($request);
        }
        return $response;
    }
}