@extends('layouts.app')

@section('content')

<div class="container">
    <h1>{!! get_dialogs('User Stats', $active_language) !!}</h1>

    <script type="text/javascript" src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <form action="" method="GET" role="search" class="row gy-2 gx-3 align-items-center">
        <div class="mb-3">
            <input type="text" name="date_range" id="date_range" value="" />
            <button class="btn btn-outline-secondary btn-sm" type="submit">{!! get_dialogs('Submit', $active_language) !!}</button>
        </div>
    </form>
    <script type="text/javascript">
        $(function() {

            $('#date_range').daterangepicker({
                "showDropdowns": true,
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                },
                "alwaysShowCalendars": true,
                "startDate": "<?php echo $start_date; ?>",
                "endDate": "<?php echo $end_date; ?>",
                locale: {
                    format: 'YYYY-MM-DD'
                }
            });

        });
    </script>

    <div class="row">
        <div class="col-md-12">
            <div class="float-end"><strong>{!! get_dialogs('Total submissions:', $active_language) !!}</strong> {{$collaborative_fields->count()}} {!! get_dialogs('(includes overwritten)', $active_language) !!}</div>
        </div>
    </div>
</div>
<div class="container">
    <div id="flash-message" class="alert d-none">
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <?php
                    $begin = new DateTime($start_date);
                    $end = new DateTime($end_date);
                    $interval = DateInterval::createFromDateString('1 day');
                    $period = new DatePeriod($begin, $interval, $end);

                    // json_encode(), not a raw single-quoted string: these go straight into a
                    // JS array literal, and an admin-edited dialog text containing a quote
                    // would otherwise break out of it (same class of bug as an earlier fix
                    // this session, for a dialog embedded raw in a confirm() call).
                    $graph_array = '[' . json_encode(get_dialogs_simple('Day', $active_language))
                        . ',' . json_encode(get_dialogs_simple('submission_status_deleted', $active_language))
                        . ',' . json_encode(get_dialogs_simple('submission_status_pending', $active_language))
                        . ',' . json_encode(get_dialogs_simple('submission_status_validated', $active_language))
                        . ',' . json_encode(get_dialogs_simple('submission_status_rejected', $active_language))
                        . '],';

                    foreach ($period as $dt) {
                        $day = $dt->format("Y-m-d");
                        $day_start = $dt->format("Y-m-d H:i:s");
                        $day_end = $dt->add(new DateInterval('PT23H59M59S'))->format("Y-m-d H:i:s");

                        $deleted = $collaborative_fields->where('status', '=', 0)->where('created_at', '>=', $day_start)->where('created_at', '<=', $day_end)->count();
                        $pending = $collaborative_fields->where('status', '=', 1)->where('created_at', '>=', $day_start)->where('created_at', '<=', $day_end)->count();
                        $validated = $collaborative_fields->where('status', '=', 2)->where('created_at', '>=', $day_start)->where('created_at', '<=', $day_end)->count();
                        $rejected = $collaborative_fields->where('status', '=', 3)->where('created_at', '>=', $day_start)->where('created_at', '<=', $day_end)->count();
                        $graph_array .= "['$day', $deleted, $pending, $validated, $rejected],";
                    }

                    ?>
                    {{$collaborative_fields->where('status', '=', 2)->count()}}

                    <!--Load the AJAX API-->
                    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
                    <script type="text/javascript">
                        google.charts.load('current', {
                            'packages': ['corechart']
                        });
                        google.charts.setOnLoadCallback(drawChart);

                        function drawChart() {
                            var data = google.visualization.arrayToDataTable([
                                <?php echo $graph_array; ?>
                            ]);

                            var options = {
                                title: <?php echo json_encode(get_dialogs_simple('Submissions stats', $active_language)); ?>,
                                hAxis: {
                                    title: <?php echo json_encode(get_dialogs_simple('Day', $active_language)); ?>,
                                    titleTextStyle: {
                                        color: '#333'
                                    }
                                },
                                vAxis: {
                                    minValue: 0
                                },
                                isStacked: false
                            };

                            var chart = new google.visualization.AreaChart(document.getElementById('chart_div'));
                            chart.draw(data, options);
                        }
                    </script>
                    <div id="chart_div"></div>
                </div>
            </div>
        </div>
    </div>
</div>


@endsection