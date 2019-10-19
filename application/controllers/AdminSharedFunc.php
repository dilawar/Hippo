<?php
require_once BASEPATH.'autoload.php';

function admin_update_talk( $data )
{
    $res = updateTable( 'talks', 'id'
                , 'class,host,host_extra,coordinator,title,description'
                , $data 
            );

    if( $res )
    {
        // TODO: Update the request or event associated with this entry as well.
        $externalId = getTalkExternalId( $data );

        $talk = getTableEntry( 'talks', 'id', $data );
        assert( $talk );

        $success = true;

        $event = getEventsOfTalkId( $data[ 'id' ] ); 
        $request = getBookingRequestOfTalkId( $data[ 'id' ] );

        if( $event )
        {
            echo printInfo( "Updating event related to this talk" );
            $event[ 'title' ] = talkToEventTitle( $talk );
            $event[ 'description' ] = $talk[ 'description' ];
            $res = updateTable( 'events', 'gid,eid', 'title,description', $event );
            if( $res )
                echo printInfo( "... Updated successfully" );
            else
                $success = false;
        }
        else if( $request )
        {
            echo printInfo( "Updating booking request related to this talk" );
            $request[ 'title' ] = talkToEventTitle( $talk );
            $request[ 'description' ] = $talk[ 'description' ];
            $res = updateTable( 'bookmyvenue_requests', 'gid,rid', 'title,description', $request );
        }
    }

    if(! $res)
    {
        printErrorSevere( "Failed to update talk" );
        return true;
    }
    else
    {
        flashMessage( 'Successfully updated entry' );
        return true;
    }
}

function admin_send_email( array $data ) : array
{
    $res = [ 'error' => '', 'message' => ''];

    $to = $data[ 'recipients' ];
    $msg = $data[ 'email_body' ];
    $cclist = $data[ 'cc' ];
    $subject = $data[ 'subject' ];

    $res['message'] =  "<h2>Email content are following</h2>";
    $mdfile = html2Markdown( $msg, true );
    $md = file_get_contents( trim($mdfile) );

    if( $md )
    {
        $res['message'] .= printInfo( "Sending email to $to ($cclist ) with subject $subject" );
        sendHTMLEmail( $msg, $subject, $to, $cclist );
    }
    else
        $res['error'] = p("Could not find email text.");

    return $res;
}

function admin_update_speaker( array $data ) : array
{
    $final = [ 'message' => '', 'error' => '' ];

    if( $data['response'] == 'DO_NOTHING' )
    {
        $final['error'] = "User said do nothing.";
        return $final;
    }

    if( $data['response'] == 'delete' )
    {
        // We may or may not get email here. Email will be null if autocomplete was
        // used in previous page. In most cases, user is likely to use autocomplete
        // feature.
        if( strlen($data[ 'id' ]) > 0 )
            $res = deleteFromTable( 'speakers', 'id', $data );
        else
            $res = deleteFromTable( 'speakers', 'first_name,last_name,institute', $data );

        if( $res )
             $final['message'] = "Successfully deleted entry";
        else
            $final['error'] = minionEmbarrassed( "Failed to delete speaker from database" );

        return $final;
    }

    if( $data['response'] == 'submit' )
    {
        $ret = addUpdateSpeaker($data);
        if( $ret['success'] )
            $final['message'] .= 'Updated/Inserted speaker. <br />' . $ret['msg'];
        else
            $final['error'] .= printInfo( "Failed to update/insert speaker" ) . $ret['msg'];

        return $final;
    }

    $final['error'] .= alertUser( "Unknown/unsupported operation " . $data[ 'response' ] );
    return $final;
}

/* --------------------------------------------------------------------------*/
/**
    * @Synopsis  venue actions are shared between admin and bmvadmin.
    *
    * @Param $arg
    *
    * @Returns   
 */
/* ----------------------------------------------------------------------------*/
function admin_venue_actions(array $data, string &$msg) : bool
{
    $response = __get__( $data, 'response', '' );
    $editables = 'name,institute,building_name,floor,location,type,strength,' 
        . 'latitude,longitude,' 
        . 'has_projector,suitable_for_conference,quota,has_skype'
        . ',allow_booking_on_hippo,note_to_user';

    if( $response == 'update' ) {
        $res = updateTable('venues', 'id', $editables, $data);
        if( $res ) {
            $msg = "Venue " . $data[ 'id' ] . ' is updated successful';
            return true;
        }
        else {
            $msg = 'Failed to update venue ' . $data[ 'id ' ];
            return false;
        }
    }
    else if( $response == 'add new' ) {
        if( strlen( $data[ 'id' ] ) < 2  ) {
            $msg =  "The venue id is too short to be legal.";
            return false;
        }
        else {
            $res = insertIntoTable('venues', "id,$editables", $data);
            if($res) {
                $msg = "Venue " . $data[ 'id' ] . ' is successfully added.';
                return true;
            }
            else {
                $msg = 'Failed to added venue ' . $data[ 'id ' ];
                return false;
            }
        }
    }
    else if( $response == 'delete' ) {
        $res = deleteFromTable( 'venues' , 'id' , $data);
        if( $res ) { 
            $msg = "Venue " . $data[ 'id' ] . ' is successfully deleted.';
            return true;
        }
        else {
            $msg = 'Failed to added venue ' . $data[ 'id ' ];
            return false;
        }
    }
    else if( $response == 'DO_NOTHING' ) {
        $msg = "User said DO NOTHING. So going back!";
        return false;
    }
    else {
        $msg = "Unknown command from user $response.";
        return false;
    }
    return false;
}

function admin_delete_booking( )
{
    // Admin is deleting booking.

}

?>
