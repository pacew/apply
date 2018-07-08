<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

$arg_perf_id = intval (@$_REQUEST['perf_id']);
$arg_perf_name = trim (@$_REQUEST['perf_name']);
$arg_email = trim (@$_REQUEST['email']);

pstart ();

$app_id = get_seq ();

if ($arg_perf_id) {
    $perf_name = get_perf_name ($arg_perf_id);
} else {
    $perf_name = $arg_perf_name;
}

query ("insert into applications (app_id, perf_id, perf_name, email)"
       ." values (?, ?, ?, ?)",
       array ($app_id, $arg_perf_id, $perf_name, $arg_email));

redirect ("thanks.php");

pfinish ();
