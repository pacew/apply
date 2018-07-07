<?php

require_once ($_SERVER['APP_ROOT'] . "/common.php");
require_once ($_SERVER['APP_ROOT'] . "/app.php");

pstart ();

$arg_name = trim (@$_REQUEST['name']);

$body .= "<div>\n";
$body .= "<form action='lookup.php'>\n";

$body .= "Name ";
$body .= "<input type='text' name='name' />\n";

$body .= "<input type='submit' value='Submit' />\n";

$body .= "</form>\n";
$body .= "</div>\n";


if ($arg_name) {
    $poss = performer_lookup ($arg_name);

    $body .= sprintf ("<h2>%s</h2>\n", h($arg_name));

    $rows = NULL;
    foreach ($poss as $p) {
        $cols = array ();
        $cols[] = intval ($p->perf_id);
        $cols[] = h($p->score);
        $cols[] = h($p->name);
        $rows[] = $cols;
    }

    $body .= count($poss);
    $body .= mktable (array ("perf_id", "score", "name"), $rows);
}

$body .= mklink ("home", "/");

pfinish ();
