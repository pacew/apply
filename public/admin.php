<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

pstart ();

$body .= "<h2>Admin page</h2>\n";

$body .= "<div>";
$body .= mklink ("home", "/");
$body .= " | ";
$body .= mklink ("view data", "download.php?view=1");
$body .= " | ";
$body .= mklink ("view json", "download.php?view_json=1");
$body .= " | ";
$body .= mklink ("download json", "download.php?download_json=1");
$body .= "</div>\n";

$body .= mklink ("debug questions", "index.php?show_all=1");



$q = query ("select app_id, ts, username, val"
            ." from json"
            ." order by app_id, ts");

$rows = array ();
while (($r = fetch ($q)) != NULL) {
    $target = sprintf ("index.php?app_id=%d", $r->app_id);

    /* ignore patches */
    if (strncmp ($r->val, "[", 1) == 0)
        continue;

    $cols = array ();
    $cols[] = mklink ($r->app_id, $target);
    $cols[] = mklink ($r->ts, $target);
    $cols[] = mklink ($r->username, $target);

    $txt = "";
    $val = @json_decode ($r->val, TRUE);
    $txt = sprintf ("%s / %s", h(@$val['name']), h(@$val['event_title']));

    $cols[] = $txt;
    $rows[] = $cols;
}

$body .= mktable (array ("app_id", "ts", "username", "json"),
                  $rows);


pfinish ();

