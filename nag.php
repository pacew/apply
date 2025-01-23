<?php

require_once("app.php");

$arg_set_nag = intval(@$_REQUEST['set_nag']);
$arg_nag = trim(@$_REQUEST['nag']);

pstart();

if ($arg_set_nag == 1) {
    setvar("nag", $arg_nag);
    redirect ("notify.php");
}

$nag = getvar('nag');

$body .= "<form action='nag.php' method='post'/>\n";
$body .= "<input type='hidden' name='set_nag' value='1' />\n";
$body .= "<h2>nag text (will be first paragraph of email)</h2>\n";
$body .= "<div><textarea name='nag' rows='8' cols='80'>\n";
$body .= h($nag);
$body .= "</textarea></div>\n";
$body .= "<input type='submit' value='Save' />\n";
$body .= mklink ("cancel", "notify.php");
$body .= "</form>\n";

pfinish();

