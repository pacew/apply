<?php

require_once ("app.php");

$anon_ok = 1;

pstart ();

$arg_access_code = trim (@$_REQUEST['access_code']);

if (strcmp ($arg_access_code, getvar ("access_code")) == 0) {
    putsess ("beta_tester", 1);
    redirect ("/");
}

flash ("invalid access code");
redirect ("/");


pfinish ();

