<?php

include_once BASEPATH . 'autoload.php';

function test_methods()
{
    $pat = constructRepeatPattern('Mon', 'All', 2);
    print_r($pat);
    print_r(repeatPatToDays($pat, dbDate('today')));

    $pat = constructRepeatPattern('Wed', 'second', 5);
    print_r($pat);
    print_r(repeatPatToDays($pat, dbDate('last month')));

    echo ' <br />';

    //echo json_encode(splitNameIntoParts( 'Prof. Upinder Singh Bhalla') );
    //echo '<br />';
    //echo json_encode(splitNameIntoParts( 'Upinder Singh Bhalla') );
    //echo '<br />';
    //echo json_encode(splitNameIntoParts( 'Upinder Bhalla') );
    //echo '<br />';
    //echo json_encode(splitNameIntoParts( 'Dr. Upinder Bhalla') );
    //echo '<br />';
    //echo json_encode(splitNameIntoParts( 'Dr. Dilawar Singh') );
    //echo '<br />';
}
