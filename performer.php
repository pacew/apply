<?php

require_once("app.php");

$anon_ok = 1;

$arg_pcode = trim(@$_REQUEST['pcode']);
$arg_evid1 = trim(@$_REQUEST['evid1']);
$arg_evid2 = trim(@$_REQUEST['evid2']);

pstart ();

$apps = get_applications();

$win = 0;
foreach ($apps as $app) {
    if ($app->evid == $arg_evid1) {
        $win = 1;
        break;
    }
}

if (! $win) {
    $body .= "<p>evid not found</p>\n";
    pfinish();
}

$t = sprintf ("index.php?app_id=%d", $app->app_id);

$body .= sprintf ("<p style='color:red'>"
    ." let the person edit a few fields in %s</p>\n",
    mklink_nw ($app->app_id, $t));

$magic_link = sprintf("https://cgi.neffa.org/performer/confirm2.pl"
    ."?P=%s", rawurlencode($arg_pcode));

$body .= sprintf ("<p>on save, redirect to %s</p>\n", 
    mklink ($magic_link, $magic_link));


pfinish ();
