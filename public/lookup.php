<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

pstart ();

$arg_name = trim (@$_REQUEST['name']);

$body .= "<h2>Performer lookup</h2>\n";

$body .= "<p>If you see the name of the group or performer that this"
      ." application is for listed below, click on it.  Otherwise,"
      ." please try searching a few times to try to find the"
      ." appropriate record in the NEFFA database.  If you are brand"
      ." new or can't find your old record, click the 'Create new' button."
      ."</p>\n";

$body .= make_lookup_form ($arg_name);

$body .= "<div>\n";
$body .= "<form action='apply.php'>\n";
$body .= "<input type='hidden' name='perf_id' value='0' />\n";
$body .= "<input type='submit' value='Create new' />\n";
$body .= "</form>\n";
$body .= "</div>\n";


if ($arg_name) {
    $poss = performer_lookup ($arg_name);

    $body .= sprintf ("<h2>%s</h2>\n", h($arg_name));

    $rows = NULL;
    foreach ($poss as $p) {
        $perf_id = intval ($p->perf_id);

        $cols = array ();
        $cols[] = $perf_id;
        $t = sprintf ("apply.php?perf_id=%d", $perf_id);
        $cols[] = mklink ($p->name, $t);
        $rows[] = $cols;
    }

    $body .= mktable (array ("Performer ID", "name"), $rows);
}

pfinish ();
