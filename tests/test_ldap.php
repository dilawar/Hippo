<?php

require_once BASEPATH .'extra/ldap.php';

function test_ldap()
{
    var_dump( getUserInfoFromLdap( 'deblina' ) );
    var_dump( getUserInfoFromLdap( 'sankarshant' ) );
}
