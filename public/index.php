<?php

require_once ($_SERVER['APP_ROOT'] . "/common.php");

pstart ();

$title_html = "Apply";

$body .= "<form action='store.php' method='post'>\n";

$body .= "Name ";
$body .= "<input type='text' name='name' />\n";

$body .= "<input type='submit' value='Submit' />\n";

$body .= "</form>\n";

$body .= mklink ("list", "list.php");

pfinish ();

