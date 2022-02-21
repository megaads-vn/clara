if (typeof angular !== 'undefined' && typeof system !== 'undefined') {
    system.controller('TrafficRequestController', TrafficRequestController);

    function TrafficRequestController($rootScope, $scope, $http) {
        const self = this;
        $scope.lists = {};
        $scope.listKeys = [];
        $scope.detailItems = {};
        $scope.filters = {
            days: 1
        };

        this.initialize = function() {
            $scope.fecthTrafficLog();
        }

        $scope.fecthTrafficLog = function() {
            $http.get(`/traffic/api/logs/requests/since/${$scope.filters.days}`, {params: {chart_data: 1, group_by: 'module'}})
                .then(res => {
                    var result = res.data;
                    if (result.status == 'successful') {
                        $scope.lists = result.data.lists;
                        $scope.listKeys = Object.keys($scope.lists);
                        var series = result.data.chart.series;
                        var categories = result.data.chart.categories;
                        self.monitoringChart(series, categories);
                    }
                });
        }

        $scope.showDetail = function(item) {
            if ($scope.lists[item]) {
                $scope.detailItems = $scope.lists[item];
            }
        }

        this.monitoringChart = function (series, categories) {
            var width= 1000;
            Highcharts.chart('request-monitoring-chart', {
                chart: {
                    type: 'column'
                },
                title: {
                    text: 'Module Requests'
                },
                subtitle: {
                    text: 'Source: Printerval.com'
                },
                xAxis: {
                    categories: categories,
                    crosshair: true
                },
                yAxis: {
                    min: 0,
                    title: {
                        text: 'Requests'
                    }
                },
                tooltip: {
                    headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
                    pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
                        '<td style="padding:0"><b>{point.y} request</b></td></tr>',
                    footerFormat: '</table>',
                    shared: true,
                    useHTML: true
                },
                plotOptions: {
                    column: {
                        pointPadding: 0.2,
                        borderWidth: 0
                    }
                },
                series: series
            });
        }
        this.initialize();
    }
}