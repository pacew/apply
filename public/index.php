<?php

require_once ($_SERVER['APP_ROOT'] . "/common.php");

pstart ();

$title_html = "Apply";

$body .= "<div>\n";
$body .= "<form action='store.php' method='post'>\n";

$body .= "Name ";
$body .= "<input type='text' name='name' />\n";

$body .= "<input type='submit' value='Submit' />\n";

$body .= "</form>\n";
$body .= "</div>\n";

$body .= "<div>\n";
$body .= "<form action='lookup.php'>\n";

$body .= "Name ";
$body .= "<input type='text' name='name' />\n";

$body .= "<input type='submit' value='Submit' />\n";

$body .= "</form>\n";
$body .= "</div>\n";

$body .= mklink ("list", "list.php");
$body .= " | ";
$body .= mklink ("old-list", "old_list.php");
$body .= " | ";
$body .= mklink ("make-index", "mkindex.php");


pfinish ();

