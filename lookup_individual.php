<?php

$anon_ok = 1;

pstart ();

$poss = lookup_individual (@$_REQUEST['term']);

$ret = array ();
foreach ($poss as $p) {
    $ret[] = $p->name;
}

ob_end_clean ();
header ("Content-Type: application/json");
echo (json_encode ($ret));
exit ();

