<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

pstart ();

$poss = performer_lookup (@$_REQUEST['term']);

$ret = array ();
foreach ($poss as $p) {
    $ret[] = $p->name;
}

ob_end_clean ();
header ("Content-Type: application/json");
echo (json_encode ($ret));
exit ();

