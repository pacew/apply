<?php

require_once ($_SERVER['APP_ROOT'] . "/common.php");

pstart ();

$title_html = "Thanks";

$body .= "<p>thanks</p>";

$body .= mklink ("home", "/");
pfinish ();

