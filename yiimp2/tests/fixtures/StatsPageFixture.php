<?php

namespace app\tests\fixtures;

use yii\test\Fixture;

/**
 * Fixture for stats page testing
 * 
 * Provides mock data and responses for stats page chart functionality tests
 */
class StatsPageFixture extends Fixture
{
    /**
     * Mock stats page HTML response
     */
    public function getStatsPageResponse()
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Stats</title>
</head>
<body>
    <div id="resume_update_button" class="resume-update-button d-none" align="center">
        <b>Auto refresh is paused - Click to resume</b>
    </div>
    
    <div align="right">
        Select Algo: <select id="algo_select">
            <option value="sha256" selected>sha256</option>
            <option value="scrypt">scrypt</option>
        </select>
    </div>
    
    <table width="100%">
        <tr>
            <td valign="top" width="33%">
                <div class="main-left-box">
                    <div class="main-left-title">Last 48 Hours</div>
                    <div class="main-left-inner">
                        <div id='graph_results_1' class='chart-container-240'></div>
                        <div id='graph_results_2' class='chart-container-240'></div>
                        <div id='graph_results_3' class='chart-container-240'></div>
                    </div>
                </div>
            </td>
            <td valign="top" width="33%">
                <div class="main-left-box">
                    <div class="main-left-title">Last 7 Days</div>
                    <div class="main-left-inner">
                        <div id='graph_results_4' class='chart-container-240'></div>
                        <div id='graph_results_5' class='chart-container-240'></div>
                        <div id='graph_results_6' class='chart-container-240'></div>
                    </div>
                </div>
            </td>
            <td valign="top" width="33%">
                <div class="main-left-box">
                    <div class="main-left-title">Last 30 Days</div>
                    <div class="main-left-inner">
                        <div id='graph_results_7' class='chart-container-240'></div>
                        <div id='graph_results_8' class='chart-container-240'></div>
                        <div id='graph_results_9' class='chart-container-240'></div>
                    </div>
                </div>
            </td>
        </tr>
    </table>
    
    <script>
        // Time range boundaries for charts
        var dtMin1 = 1640995200 * 1000;
        var dtMax1 = 1641081600 * 1000;
        var dtMin2 = 1640390400 * 1000;
        var dtMax2 = 1641081600 * 1000;
        var dtMin3 = 1638489600 * 1000;
        var dtMax3 = 1641081600 * 1000;

        function page_refresh() {
            main_refresh_1();
            main_refresh_2();
            main_refresh_3();
            main_refresh_4();
            main_refresh_5();
            main_refresh_6();
            main_refresh_7();
            main_refresh_8();
            main_refresh_9();
        }

        // Chart refresh functions
        function main_ready_1(data) { graph_init_1(data); }
        function main_refresh_1() { $.get("/stats/graph_results_1", '', main_ready_1); }

        function main_ready_2(data) { graph_init_2(data); }
        function main_refresh_2() { $.get("/stats/graph_results_2", '', main_ready_2); }

        function main_ready_3(data) { graph_init_3(data); }
        function main_refresh_3() { $.get("/stats/graph_results_3", '', main_ready_3); }

        function main_ready_4(data) { graph_init_4(data); }
        function main_refresh_4() { $.get("/stats/graph_results_4", '', main_ready_4); }

        function main_ready_5(data) { graph_init_5(data); }
        function main_refresh_5() { $.get("/stats/graph_results_5", '', main_ready_5); }

        function main_ready_6(data) { graph_init_6(data); }
        function main_refresh_6() { $.get("/stats/graph_results_6", '', main_ready_6); }

        function main_ready_7(data) { graph_init_7(data); }
        function main_refresh_7() { $.get("/stats/graph_results_7", '', main_ready_7); }

        function main_ready_8(data) { graph_init_8(data); }
        function main_refresh_8() { $.get("/stats/graph_results_8", '', main_ready_8); }

        function main_ready_9(data) { graph_init_9(data); }
        function main_refresh_9() { $.get("/stats/graph_results_9", '', main_ready_9); }

        // Chart initialization functions using Chart.js
        function graph_init_1(data) {
            var t = JSON.parse(data);
            ChartHelper.createLineChart('graph_results_1', t, {
                title: 'Hashrate (Mh/s)',
                xAxisMin: dtMin1,
                xAxisMax: dtMax1,
                xAxisFormat: 'HH:mm',
                yAxisMin: 0,
                fill: true,
                backgroundColor: 'rgba(78, 180, 180, 0.3)',
                borderColor: 'rgba(78, 180, 180, 0.8)'
            });
        }

        function graph_init_2(data) {
            var t = JSON.parse(data);
            ChartHelper.createBarChart('graph_results_2', t, {
                title: 'BTC/Day',
                xAxisMin: dtMin1,
                xAxisMax: dtMax1,
                xAxisFormat: 'HH:mm',
                yAxisMin: 0
            });
        }

        function graph_init_3(data) {
            var t = JSON.parse(data);
            ChartHelper.createBarChart('graph_results_3', t, {
                title: 'BTC/Mh/d',
                xAxisMin: dtMin1,
                xAxisMax: dtMax1,
                xAxisFormat: 'HH:mm',
                yAxisMin: 0
            });
        }

        function graph_init_4(data) {
            var t = JSON.parse(data);
            ChartHelper.createLineChart('graph_results_4', t, {
                title: 'Hashrate (Mh/s)',
                xAxisMin: dtMin2,
                xAxisMax: dtMax2,
                xAxisFormat: 'MMM d',
                yAxisMin: 0,
                fill: true,
                backgroundColor: 'rgba(78, 180, 180, 0.3)',
                borderColor: 'rgba(78, 180, 180, 0.8)'
            });
        }

        function graph_init_5(data) {
            var t = JSON.parse(data);
            ChartHelper.createBarChart('graph_results_5', t, {
                title: 'BTC/Day',
                xAxisMin: dtMin2,
                xAxisMax: dtMax2,
                xAxisFormat: 'MMM d',
                yAxisMin: 0
            });
        }

        function graph_init_6(data) {
            var t = JSON.parse(data);
            ChartHelper.createBarChart('graph_results_6', t, {
                title: 'BTC/Mh/d',
                xAxisMin: dtMin2,
                xAxisMax: dtMax2,
                xAxisFormat: 'MMM d',
                yAxisMin: 0
            });
        }

        function graph_init_7(data) {
            var t = JSON.parse(data);
            ChartHelper.createLineChart('graph_results_7', t, {
                title: 'Hashrate (Mh/s)',
                xAxisMin: dtMin3,
                xAxisMax: dtMax3,
                xAxisFormat: 'MM/dd',
                yAxisMin: 0,
                fill: true,
                backgroundColor: 'rgba(78, 180, 180, 0.3)',
                borderColor: 'rgba(78, 180, 180, 0.8)'
            });
        }

        function graph_init_8(data) {
            var t = JSON.parse(data);
            ChartHelper.createLineChart('graph_results_8', t, {
                title: 'BTC/Day',
                xAxisMin: dtMin3,
                xAxisMax: dtMax3,
                xAxisFormat: 'MM/dd',
                yAxisMin: 0
            });
        }

        function graph_init_9(data) {
            var t = JSON.parse(data);
            ChartHelper.createLineChart('graph_results_9', t, {
                title: 'BTC/Mh/d',
                xAxisMin: dtMin3,
                xAxisMax: dtMax3,
                xAxisFormat: 'MM/dd',
                yAxisMin: 0
            });
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            var resumeBtn = document.getElementById('resume_update_button');
            if (resumeBtn) {
                resumeBtn.addEventListener('click', function() {
                    auto_page_resume();
                });
            }
            
            var algoSelect = document.getElementById('algo_select');
            if (algoSelect) {
                algoSelect.addEventListener('change', function(event) {
                    var algo = this.value;
                    window.location.href = '/site/algo?algo='+algo+'&r=/stats';
                });
            }
        });
    </script>
</body>
</html>
HTML;
    }

    /**
     * Mock chart data responses
     */
    public function getChartDataResponse($endpoint)
    {
        // Generate mock time series data
        $data = [];
        $startTime = time() - 86400; // 24 hours ago
        
        for ($i = 0; $i < 24; $i++) {
            $timestamp = ($startTime + ($i * 3600)) * 1000; // Convert to milliseconds
            $value = rand(100, 1000) / 100; // Random value between 1-10
            $data[] = [$timestamp, $value];
        }
        
        return json_encode($data);
    }
}