<?php

namespace Megaads\Clara\Controllers;

use Illuminate\Http\Request;

class TrafficController extends BaseController
{
    public function today($group)
    {
        $this->validateResource($group);

        $logs = sprintf("%s/traffic-%s.log", storage_path("logs/clara/$group/log"), date('Y-m-d'));

        if (!is_file($logs)) {
            return $this->error("$logs doesnt exists!");
        }
        $content = $this->readFileLog($logs);
        
        return $this->success($content, "Today's logs");
    }

    public function fetchSince(Request $request, $group, $day)
    {
        $this->validateResource($group);
        $groupByModule = false;
        if ($request->has('group_by') && $request->get('group_by') == 'module') {
            $groupByModule = true;
        }

        $groupDir = sprintf("%s", storage_path("logs/clara/$group/log"));

        if (!is_dir($groupDir)) {
            return $this->error("$groupDir doesnt exists!");
        }
        $now = date('Y-m-d');
        $since = strtotime("{$now} - $day days");
        $logs = array_diff(scandir($groupDir), array('.', '..'));

        $response = [];
        $tempResponse = [];

        foreach ($logs as $key => $log) {
            $fileName = "{$groupDir}/$log";
            $logsDate = $this->getDate($log);
            if (strtotime($logsDate) > $since) {
                $response['lists'][$logsDate] = $this->readFileLog($fileName);
                if ($groupByModule) {
                    $tempResponse[] = $this->readFileLog($fileName);
                }
            }
        }

        $since = date('Y-m-d', $since);
        if ($request->has('chart_data') && $request->get('chart_data') == 1) {
            $this->buildChartData($response);
        }
        if ($groupByModule) {
            $response['lists'] = $tempResponse[0];
        }
        return $this->success($response, "logs since {$since}");
    }


    protected function readFileLog($filePath)
    {
        $lines = [];
        $handle = fopen($filePath, "r") or die("Couldn't get handle");
        if ($handle) {
            while (!feof($handle)) {
                $lines[] = fgets($handle, 4096);
            }
            fclose($handle);
        }
        $data = [];
        if (count($lines) > 0) {
            foreach ($lines as $line) {
                if ($line) {
                    $columns = explode("|", $line);
                        $module = array_pop($columns);
                    $module = str_replace("\n", "", $module);
                    $data[$module][] = $this->buildParams($columns);
                }
            }
        }
        return $data;
    }

    protected function buildParams($params)
    {
        return [
            'time' => $params[0],
            'host' => $params[1],
            'port' => $params[2],
            'schema' => $params[3],
            'path' => $params[4],
            'method' => $params[5],
            'ajax' => $params[6],
            'action'  => $params[7],
            'params' => $params[8],
            'user' => $params[9],
            'ip' => $params[10],
            'user_agent' => $params[11],
            'performance' => $params[12],
        ];
    }

    protected function buildChartData(&$data)
    {
        $categories = [];
        $series = [];
        foreach ($data['lists'] as $logDate => $items) {
            $categories[] = $logDate;
            foreach ($items as $module => $moduleItem) {
                $findIndex = $this->findInObject($series, $module, 'name');
                if ($findIndex >= 0) {
                    $serieItem = $series[$findIndex];
                    $serieItem['data'][] = count($moduleItem);
                    $series[$findIndex] = $serieItem;
                } else {
                    $serieItem = [];
                    $serieItem['name'] = $module;
                    $serieItem['data'][] = count($moduleItem);
                    array_push($series, $serieItem);
                }
            }
        }
        $data['chart'] = [
            'categories' => $categories,
            'series' => $series
        ];
    }

    protected function findInObject(array $array, $needed, string $column)
    {
        $retval = -1;
        if (count($array) > 0) {
            foreach ($array as $index => $item) {
                if (isset($item[$column]) && $item[$column] == $needed) {
                    $retval = $index;
                    break;
                }
            }
        }
        return $retval;
    }
}