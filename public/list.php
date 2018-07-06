<?php

require_once ($_SERVER['APP_ROOT'] . "/common.php");

pstart ();

$body .= sprintf ("<div>%s</div>\n", mklink ("home", "/"));

$q = query ("select app_id, name"
            ." from apps"
            ." order by app_id");
$rows = array ();
while (($r = fetch ($q)) != NULL) {
    $cols = array ();
    $cols[] = $r->app_id;
    $cols[] = h($r->name);
    $rows[] = $cols;
}
$body .= mktable (array ("id", "name"),
                  $rows);

pfinish ();

