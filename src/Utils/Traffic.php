<?php

namespace Megaads\Clara\Utils;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;

class Traffic
{
    private $fileSystem;
    private $requestsLogs;
    private $performanceLogs;
    private $day;

    public function __construct(Filesystem $fileSystem)
    {
        $this->fileSystem = $fileSystem;
        $this->requestsLogs = sprintf('%s/%s', Config::get('clara.logs.dir'), 'requests');
        $this->performanceLogs = sprintf('%s/%s', Config::get('clara.logs.dir'), 'performance');
        $this->day = Config::get('clara.logs.days');
    }

    public function getPayload()
    {
        $controlerAction = 'unknown';
        if (request()->route() && isset(request()->route()->action)) {
            $routeAction = request()->route()->action;
            if (isset($routeAction['uses'])) {
                $controlerAction = json_encode($routeAction['uses']);
            }
        }
        return [
            'time' => date("Y-m-d H:i:s"),
            'host' => request()->getHost(),
            'port' => request()->getPort(),
            'schema' => request()->getScheme(),
            'path' => request()->path(),
            'method' => request()->method(),
            'ajax' => request()->ajax(),
            'action'  => $controlerAction,
            'params' => json_encode(request()->all()),
            'user' => request()->user()->email ?? '',
            'ip' => request()->ip(),
            'user-agent' => request()->userAgent(),
            'module' => $this->routeInfo()
        ];
    }

    public function getRouteInfo()
    {
        return $this->routeInfo();
    }

    public function text($message, $type = 'requests')
    {
        $directory = $this->{$type."Logs"};
        $textLogs = sprintf("%s/text", $directory);

        if (!is_dir($textLogs)) {
            mkdir($textLogs, 0755, true);
        }

        $fileName = sprintf("%s/%s-%s.log",$textLogs,'traffic', date('Y-m-d'));
        $message = json_encode($message);
        $timeStamp = date('Y-m-d H:i:s');
        $message = "[{$timeStamp}] .INFO: {$message}";
        file_put_contents($fileName, $message, FILE_APPEND | LOCK_EX);
    }

    public function json($message, $type = 'requests')
    {
        $params = $this->joinLogWithGroup($message);
        $message = $params[0];
        $module = $params[1];

        $directory = $this->{$type."Logs"};
        $jsonLogs = sprintf("%s/json", $directory);

        if (!is_dir($jsonLogs)) {
            mkdir($jsonLogs, 0755, true);
        }

        $fileName = sprintf("%s/%s-%s.json",$jsonLogs,'traffic', date('Y-m-d'));

        if(is_file($fileName)) {

            $content = file_get_contents($fileName);
            $decoded = json_decode($content, true);

            if (!empty($decoded)) {
                if ($module !== '') {
                    $found = false;
                    foreach ($decoded as $key => $item) {
                        if (isset($message[$key])) {
                            $decoded[$key][] = $message[$key][0];
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $decoded[$module] = $message[$module];
                    }
                } else {
                    $decoded[] = $message;
                }
                $content = json_encode($decoded);
                unlink($fileName);
            }

        }else{
            $content = json_encode($message);
        }

        file_put_contents($fileName, $content, FILE_APPEND | LOCK_EX);
    }

    public function log($message, $type = "requests")
    {
        $directory = $this->{$type."Logs"};
        $jsonLogs = sprintf("%s/log", $directory);

        if (!is_dir($jsonLogs)) {
            mkdir($jsonLogs, 0755, true);
        }

        $fileName = sprintf("%s/%s-%s.log",$jsonLogs,'traffic', date('Y-m-d'));
        
        $content = join("|", $message);
        $content =  $content . "\n";
        file_put_contents($fileName, $content, FILE_APPEND | LOCK_EX);
    }

    public function generateChartData()
    {
        
    }

    //todo: remove logs older then $this->days
    // the idea here is that it will be called on each request which isn't a very bright solution
    private function removeLogs($dir)
    {
    }

    private function routeInfo()
    {
        $retVal = 'Core';
        $route = \Route::getRoutes()->match(request());
        $routeAction = $route->getAction();
        if (isset($routeAction['namespace'])) {
            $explodeNamespace = explode('\\', $routeAction['namespace']);
            if ($explodeNamespace[1] !== 'Http') {
                $retVal = $explodeNamespace[1];
            }
        }
        return $retVal;
    }

    private function joinLogWithGroup($message) {
        $retval = $message;
        $module = '';
        if (isset($message['module'])) {
            $retval = [];
            $module = $message['module'];
            $retval[$module][] = $message;
        }
        return [$retval, $module];
    }
}