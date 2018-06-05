<?php

require_once BASEPATH.'autoload.php';

// Get the referece page. These tasks are shared by both ADMIN_ACAD and
// ADMINBMV. Controller must set controller parameter.
$ref = $controller;

echo userHTML( );

$symbEdit = ' <i class="fa fa-pencil fa-2x"></i>';
$symbDelete = ' <i class="fa fa-trash fa-2x"></i>';
$symbCalendar = ' <i class="fa fa-calendar fa-2x"></i>';
$symbCancel = ' <i class="fa fa-trash fa-2x"></i>';


// Logic for POST requests.
$speaker = array( 
    'first_name' => '', 'middle_name' => '', 'last_name' => '', 'email' => ''
    , 'department' => '', 'institute' => '', 'title' => '', 'id' => ''
    , 'homepage' => ''
    );

// Get talks only in future.
$whereExpr = "status!='INVALID' ORDER BY created_on ASC";
$talks = getTableEntries( 'talks', '', $whereExpr );

$upcomingTalks = array( );

/* Filter talk which have not been delivered yet. */
foreach( $talks as $t )
{
    // If talk has been delivered, then dont display.
    $event = getEventsOfTalkId( $t['id'] );
    if( $event )
        if( strtotime($event[ 'date' ] ) <= strtotime( 'yesterday' ) )
            // This talk has been delivered successfully.
            continue;
    array_push( $upcomingTalks, $t );
}

echo goBackToPageLink( "$ref/home", "Go back" );

echo "<h1>Upcoming talks</h1>";

// Show upcoming talks to user. She has edit, delete or schedule them.
echo '<div style="font-size:x-small">';
// Outer table
echo '<table class="table_in_table">';
foreach( $upcomingTalks as $t )
{
    echo '<tr>';
    /***************************************************************************
     * FIRST COLUMN: Speaker picture.
     */
    echo '<td>';
    echo "Speaker ID: " . $t['speaker_id'] . '<br />';
    echo inlineImageOfSpeakerId( $t['speaker_id'], $height = '100px', $width = '100px' );
    echo '</td>';

    /***************************************************************************
     * SECOND COLUMN: Talk information.
     */
    $tid = $t['id'];

    echo '<td>';
    echo '<form method="post" action="'.site_url("$ref/updatetalk/$tid").'">';
    echo arrayToVerticalTableHTML( $t, 'info', '', 'speaker_id');
    echo '</form>';

    // Put an edit button. 
    echo '<form method="post" action="'.site_url("$ref/edittalk/$tid").'">';
    echo '<button style="float:right" title="Edit this talk"
            name="response" value="edit">' . $symbEdit . '</button>';
    echo '</form>';

    echo '<form method="post" action="'.site_url("$ref/deletetalk/$tid").'">';
    echo '<input type="hidden" name="id" value="' . $t[ 'id' ] . '" />
        <button onclick="AreYouSure(this)" name="response" 
            title="Delete this talk" >' . $symbDelete . '</button>';
    echo '</form>';
    echo '</td>';

    /***************************************************************************
     * THIRD COLUMN: Booking related to this talk.
     */

    // Check if this talk has already been approved or in pending approval.
    $externalId = getTalkExternalId( $t );
    $event = getTableEntry( 'events', 'external_id,status'
        , array( 'external_id' => $externalId, 'status' => 'VALID' )
        );

    $request = getTableEntry( 'bookmyvenue_requests', 'external_id,status'
        , array( 'external_id' => $externalId, 'status'  => 'PENDING' )
        );

    // If either a request of event is found, don't let user schedule the talk. 
    // Here we disable the schedule button.

    if( ! ($request || $event ) )
    {
        echo '<td>';
        echo '<form method="post" action="'.site_url("$ref/scheduletalk/$tid").'">';
        echo '<input type="hidden" name="id" value="' . $t[ 'id' ] . '" />';
        echo '<button title="Schedule this talk" 
            name="response" value="schedule">' . $symbCalendar . '</button>';
        echo '</form>';
        echo '</td>';
    }
    else
    {
        echo '<td>';
        if( $event )
        {
            // If event is already approved, show it here.
            echo alertUser( "<strong>This talk is confirmed.</strong>", false );

            $html = arrayToVerticalTableHTML( $event, 'events', 'lightyellow'
                , 'eid,class,url,modified_by,timestamp,calendar_id' . 
                ',status,calendar_event_id,last_modified_on' );

            /* PREPARE email template */
            $talkid = explode( '.', $event[ 'external_id' ])[1];
            $talk = getTableEntry( 'talks', 'id', array( 'id' => $talkid ) );
            if( ! $talk )
                continue;

            $talkHTML = talkToHTML( $talk, false );

            $subject = __ucwords__( $talk[ 'class' ] ) . " by " . $talk['speaker'] . ' on ' .
                humanReadableDate( $event[ 'date' ] );

            $hostInstitite = emailInstitute( $talk[ 'host' ] );

            $templ = emailFromTemplate(
                "this_event" 
                , array( 'EMAIL_BODY' => $talkHTML
                        , 'HOST_INSTITUTE' => strtoupper( $hostInstitite )
                    ) 
                );
            $templ = htmlspecialchars( json_encode( $templ ) );

            $html .= '<form method="post" action="'.site_url("$ref/send_email") .'">';
            $html .= '<input type="hidden" name="subject" value="'. $subject . '" >';
            $html .= '<input type="hidden" name="template" value="'. $templ . '" >';


            $html .= "<p>You can send email: ";
            $html .= '<button title="Send email" name="response" value="send email">Email</button>';
            $html .= '</p>';
            $html .= '</form>';
            echo $html;
        }
        // Else there might be a pending request.
        else if( $request )
        {
            echo alertUser( 
                "Shown below is the booking request pending review for above talk."
                , false
            );

            $gid = $request[ 'gid' ];

            echo arrayToVerticalTableHTML( $request, 'requests', ''
                , 'eid,class,external_id,url,modified_by,timestamp,calendar_id' . 
                ',status,calendar_event_id,last_modified_on' );

            echo '<form method="post" action="'.site_url("$ref/update_requests").'">';
            echo "<button onclick=\"AreYouSure(this)\" 
                name=\"response\" title=\"Cancel this request\"> 
                $symbCancel </button>";
            echo "<button name=\"response\" title=\"Edit this request\"
                value=\"edit\"> $symbEdit </button>";
            echo "<input type=\"hidden\" name=\"gid\" value=\"$gid\">";
            echo '</form>';
        }
        echo '</td>';
    }
    echo '</tr>';
}
echo '</table>';
echo '</div>';
    
echo goBackToPageLink( "$ref/home", "Go back" );

?>
