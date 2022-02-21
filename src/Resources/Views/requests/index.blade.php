@extends('system.layout.main',[
'ngController' => 'TrafficRequestController'
])
@section('title')
    <title>Clara Module Monitoring | System</title>
@endsection
@section('content')

    <style type="text/css">
        #dashboard-container .box-title {
            font-weight: normal;
        }
    </style>
    <div class="content" id="dashboard-container" ng-cloak>
        <!-- <div class="row">
            <div class="col-md-12 text-left" style="margin-bottom: 20px">
                <form class="form-inline">
                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-group-addon">Từ <span class="hidden-xs">ngày</span></span>
                            <input type="text" class="form-control" id="date-from" placeholder="yyyy-mm-dd" />
                            <span class="input-group-addon">đến <span class="hidden-xs">ngày</span></span>
                            <input type="text" class="form-control" id="date-to" placeholder="yyyy-mm-dd" />
                        </div>
                    </div>

                    <div class="form-group text-center">
                        <button type="button" class="btn btn-primary" ng-click="getReport()">Xem thống kê</button>
                    </div>
                </form>
            </div>
        </div> -->
        <div class="row">
            <div class="col-xs-12 col-sm-12">
                <div class="box box-default">
                    <div class="box-body" style="min-height: 400px;width:100%;overflow:auto">
                        <figure class="highcharts-figure">
                            <div id="request-monitoring-chart"></div>
                        </figure>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-xs-12 col-sm-12">
                <div class="box box-default">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-xs-6 col-sm-6">
                                @include('clara::requests.inc.list-log')
                            </div>
                            <div class="col-xs-6 col-sm-6">
                                @include('clara::requests.inc.detail')
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('css')
    <link rel="stylesheet" href="/clara/assets/css/traffic-style.css?v=<?= time() ?>">
@endsection
@section('script')
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="/clara/assets/js/traffic-controller.js?v=<?= time() ?>" charset="utf-8"></script>
@endsection