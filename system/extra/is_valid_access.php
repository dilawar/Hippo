<?php

require_once BASEDIR . "extra/methods.php";

$validAccces = FALSE;
if( array_key_exists( 'AUTHENTICATED', $_SESSION) )
{
   if( $_SESSION['AUTHENTICATED'] ) 
        $validAccces = TRUE;
}


if( ! $validAccces )
{
    echo ( '<p class="error"> You should not be here. Not authenticated.</p>' );
    redirect( "/welcome" );
}

?>
