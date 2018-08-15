<?php

$anon_ok = 1;
require_once ($_SERVER['APP_ROOT'] . "/app.php");

pstart ();

$poss = lookup_group (@$_REQUEST['term']);

$ret = array ();
foreach ($poss as $p) {
    $ret[] = $p->name;
}

ob_end_clean ();
header ("Content-Type: application/json");
echo (json_encode ($ret));
exit ();

