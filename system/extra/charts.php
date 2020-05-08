<?php

function getCharts(): array
{
    // default styles.
    $charts = [];

     /*  This section count the publications from NCBS and PUBMED. */
    $pubMed = getTableEntries('publications', 'date', "source='PUBMED' AND date < NOW()");
    $pubYearWisePUBMED = [];
    foreach ($pubMed as $e) {
        $year = intval(date('Y', strtotime($e['date'])));
        $sha = $e['sha512'];
        if ($year > 1990)
            $pubYearWisePUBMED[$year] = __get__($pubYearWisePUBMED, $year, 0) + 1;
    }

    $data = [];
    foreach($pubYearWisePUBMED as $key => $val )
        $data[] = [$key, $val];


    $charts['Number of publications (PUBMED)'] = [
        'type' => 'line',
        'xlabel' => 'year',
        'ylabel' => 'Count',
        'title' => 'No of publications (source: PUBMED)',
        'series' => [['data'=> $data, 'name'=> 'Publications (source: PUBMED)']],
    ];

    $upto = dbDate('tomorrow');
    $requests = getTableEntries('bookmyvenue_requests', 'date'
        , "date >= '2017-02-28' AND date <= '$upto'"
        , 'date,status,start_time,end_time,last_modified_on');
    $nApproved = 0;
    $nRejected = 0;
    $nCancelled = 0;
    $nPending = 0;
    $nOther = 0;
    $timeForAction = array( );

    $firstDate = $requests[0]['date'];
    $lastDate = end($requests)['date'];
    $timeInterval = strtotime($lastDate) - strtotime($firstDate);

    foreach ($requests as $r) {
        if ($r[ 'status' ] == 'PENDING') {
            $nPending += 1;
        } elseif ($r[ 'status' ] == 'APPROVED') {
            $nApproved += 1;
        } elseif ($r[ 'status' ] == 'REJECTED') {
            $nRejected += 1;
        } elseif ($r[ 'status' ] == 'CANCELLED') {
            $nCancelled += 1;
        } else {
            $nOther += 1;
        }

        // Time take to approve a request, in hours
        if ($r[ 'last_modified_on' ]) {
            $time = strtotime($r['date'] . ' ' . $r[ 'start_time' ])
                - strtotime($r['last_modified_on']);
            $time = $time / (24 * 3600.0);
            array_push($timeForAction, array($time, 1));
        }
    }

    // rate per day.
    $rateOfRequests = 24 * 3600.0 * count($requests) / (1.0 * $timeInterval);

    /* Venue usage time.  */
    $events = getTableEntries(
        'events',
        'date',
        "status='VALID' AND date < '$upto'",
        'date,start_time,end_time,venue,class'
    );

    $venueUsageTime = array( );

    // How many events, as per class.
    $eventsByClass = array( );
    foreach ($events as $e) {
        $time = (strtotime($e[ 'end_time' ]) - strtotime($e[ 'start_time' ])) / 3600.0;
        $venue = $e[ 'venue' ];
        $venueUsageTime[ $venue ] = __get__($venueUsageTime, $venue, 0.0) + $time;
        $eventsByClass[ $e[ 'class' ] ] = __get__($eventsByClass, $e['class'], 0) + 1;
    }

    $allVenues = array_keys($venueUsageTime);

    // AWS to this list.
    $eventsByClass[ 'ANNUAL WORK SEMINAR' ] = count(
        getTableEntries('annual_work_seminars', 'date', "date>'2017-03-21'", 'date')
    );

    // Add courses events generated by Hippo.
    $eventsByClass[ 'CLASS' ] = __get__($eventsByClass, 'CLASS', 0)
        + totalClassEvents();

    $bookingTableChart = [ 
        'type' => 'pie',
        'title' => 'Booking requests',
        'xlabel' => '',
        'ylabel' => '',
        'series' => [],
    ];

    // $charts['Booking rates'] = $bookingTableChart;

    // Pie chart.
    $eventsByClassPie = array_map( function($value, $key) { 
        return [ $key, $value ];
    }, $eventsByClass, array_keys($eventsByClass));

    $charts['Events by class'] = [ 
        'type' => 'pie',
        'title' => 'Events by class',
        'xlabel' => 'class',
        'ylabel' => 'Count',
        'data'=> $eventsByClassPie,
    ];

    return $charts;
}
?>
