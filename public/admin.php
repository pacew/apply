<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

pstart ();

$arg_refresh_idx = intval (@$_REQUEST['refresh_idx']);

if ($arg_refresh_idx) {
    $cmd = sprintf ("sh -c 'cd %s; ./mkindex'", $cfg['srcdir']);
    $body .= sprintf ("<div>running %s</div>\n", h($cmd));
    $val = exec ($cmd, $output, $rc);
    if ($rc != 0) {
        $body .= sprintf ("<div>error running: %s</div>\n", h($cmd));
    }
    $body .= "<pre>\n";
    $body .= h(implode ("\n", $output));
    $body .= "</pre>\n";

    $body .= sprintf (
        "<div>%s</div>\n", 
        mklink ("back to admin page", "admin.php"));
    pfinish ();
}

$body .= "<h2>Admin page</h2>\n";

$body .= "<div>";
$body .= mklink ("home", "/");
$body .= " | ";
$body .= mklink ("view data", "download.php?view_data=1");
$body .= " | ";
$body .= mklink ("view json", "download.php?view_json=1");
$body .= " | ";
$body .= mklink ("download json", "download.php?download_json=1");
$body .= " | ";
$body .= mklink ("view csv", "download.php?view_csv=1");
$body .= " | ";
$body .= mklink ("download csv", "download.php?download_csv=1");
$body .= " | ";
$body .= mklink ("test lookup", "lookup_individual.php?term=willisson");
$body .= "</div>\n";

$idx_name = sprintf ("%s/neffa_idx.json", $cfg['auxdir']);
$mtime = filemtime ($idx_name);
$body .= sprintf (
    "<div>neffa performer index last updated %s</div>\n",
    strftime ("%Y-%m-%d %H:%M:%S", $mtime));

$body .= "<form action='admin.php' method='post'>\n";
$body .= "<input type='hidden' name='refresh_idx' value='1' />\n";
/* prevent accidental form submission */
$body .= "<button type='submit' onclick='return false' style='display:none'>"
      ."</button>\n";

$body .= "<input type='submit' value='Refresh performer index' />\n";
$body .= "</form>\n";

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

    $txt = "";
    $val = @json_decode ($r->val, TRUE);
    $txt = sprintf ("%s / %s", h(@$val['name']), h(@$val['event_title']));
    $cols[] = $txt;

    $t = sprintf ("download.php?view_csv=1&app_id=%d", $r->app_id);
    $cols[] = mklink ("raw data", $t);

    $rows[] = $cols;
}

$body .= mktable (array ("app_id", "ts", "name / title", ""),
                  $rows);


pfinish ();

