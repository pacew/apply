<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

$arg_perf_id = intval (@$_REQUEST['perf_id']);

pstart ();

if ($arg_perf_id) {
    $perf_name = get_perf_name ($arg_perf_id);

    $body .= sprintf ("<h1>Application for %s</h1>\n", h($perf_name));
}


$body .= "<form action='save.php' method='post'>\n";
$body .= sprintf ("<input type='hidden' name='perf_id' value='%d' />\n",
                  $arg_perf_id);

$body .= "<table class='twocol'>\n";

if ($arg_perf_id == 0) {
    $body .= "<tr><th>Performer name</th><td>"
          ."<input type='text' size='40' name='perf_name' />"
          ."</td></tr>\n";
}

$body .= "<tr><th>Email for contact</th><td>"
                 ."<input type='text' size='40' name='email' />"
                 ."</td></tr>\n";

$body .= "<tr><th></th><td><input type='submit' value='Submit' /></td></tr>\n";
$body .= "</table>\n";

$body .= "</form>\n";


pfinish ();
