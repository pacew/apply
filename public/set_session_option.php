<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

pstart ();

$arg_var = trim (@$_REQUEST['var']);
$arg_val = trim (@$_REQUEST['val']);

if ($username) {
    putsess ($arg_var, $arg_val);
    json_finish ("ok");
} else {
    $body .= "invalid request";
    pfinish ();
}
