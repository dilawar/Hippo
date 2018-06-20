<?php
require_once BASEPATH.'autoload.php';
require_once BASEPATH.'extra/jc.php';

trait JC 
{
    // VIEWS
    public function jc( string $arg = '', string $arg2 = '' )
    {
        $this->load_user_view( 'user_jc' );
    }

    public function jc_presentation_requests( )
    {
        $this->load_user_view("user_manages_jc_presentation_requests");
    }

    public function jc_update_presentation( )
    {
        $this->load_user_view( "user_manages_jc_update_presentation" );
    }

    // ACTION.
    public function jc_action( string $action  )
    {
        if( $action == 'Unsubscribe' )
        {
            $_POST[ 'status' ] = 'UNSUBSCRIBED';
            $res = updateTable(
                'jc_subscriptions'
                , 'login,jc_id', 'status', $_POST
            );
            if( $res )
            {
                // Send email to jc-admins.
                $jcAdmins = getJCAdmins( $_POST[ 'jc_id' ] );
                $tos = implode( ","
                    , array_map(
                        function( $x ) { return getLoginEmail( $x['login'] ); }, $jcAdmins )
                    );
                $user = whoAmI( );
                $subject = $_POST[ 'jc_id' ] . " | $user has unsubscribed ";
                $body = "<p> Above user has unsubscribed from your JC. </p>";
                sendHTMLEmail( $body, $subject, $tos, 'jccoords@ncbs.res.in' );
                flashMessage( 'Successfully unsubscribed from ' . $_POST['jc_id'] );
            }
            else
                flashMessage( "Failed to unsubscribe from JC." );
        }
        else if( $action == 'Subscribe' )
        {
            $_POST[ 'status' ] = 'VALID';
            $res = insertOrUpdateTable('jc_subscriptions', 'login,jc_id', 'status',  $_POST);
            if( $res )
                flashMessage( 'Successfully subscribed to ' . $_POST['jc_id'] );
        }
        else
            flashMessage( "unknown action $action." );

        redirect( 'user/jc' );
    }


    public function jc_update_action( )
    {
        if( __get__( $_POST, 'response', '' ) == 'Add My Vote' )
        {
            $_POST[ 'status' ] = 'VALID';
            $_POST[ 'voted_on' ] = dbDate( 'today' );
            $res = insertOrUpdateTable( 'votes', 'id,voter,voted_on'
                , 'status,voted_on', $_POST );
            if( $res )
                echo printInfo( 'Successfully voted.' );
        }
        else if( __get__( $_POST, 'response', '' ) == 'Remove My Vote' )
        {
            $_POST[ 'status' ] = 'CANCELLED';
            $res = updateTable( 'votes', 'id,voter', 'status', $_POST );
            if( $res )
                echo printInfo( 'Successfully removed  your vote.' );
        }
        else if( __get__( $_POST, 'response', '' ) == 'Acknowledge' )
        {
            $_POST[ 'acknowledged' ] = 'YES';
            $res = updateTable( 'jc_presentations', 'id', 'acknowledged', $_POST );
            if( $res )
                echo printInfo( 'Successfully acknowleged  your JC presentation.' );
        }
        else if( __get__( $_POST, 'response', '' ) == 'Save' )
        {
            $res = updateTable( 'jc_presentations', 'id', 'title,description,url,presentation_url', $_POST );
            if( $res )
                echo printInfo( 'Successfully edited  your JC presentation.' );
        }
        else
            echo alertUser( 'This action ' . $_POST[ 'response' ] . ' is not supported yet');

        redirect( "user/jc" );
    }
}

?>
