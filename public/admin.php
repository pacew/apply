<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

pstart ();

$arg_download_csv = intval (@$_REQUEST['download_csv']);

if ($arg_download_csv) {
    $f = tmpfile ();

    $colnames = array ("app_id", "perf_id", "perf_name", "email");
    fputcsv ($f, $colnames);

    $stmt = sprintf ("select %s from applications order by app_id",
                     implode (',', $colnames));

    $q = query ($stmt);
    while (($r = fetch ($q)) != NULL) {
        $cols = array ();
        foreach ($colnames as $colname) {
            $cols[] = $r->$colname;
        }
        fputcsv ($f, $cols);
    }
    rewind ($f);
    
    ob_end_clean ();
    header ("Content-Type: application/csv");
    header ("Content-Disposition: attachment; filename=applications.csv");
    fpassthru ($f);
    exit ();
}

$body .= "<h2>Admin page</h2>\n";

$body .= "<div>";
$body .= mklink ("home", "/");
$body .= "</div>\n";

$body .= "<form action='admin.php'>\n";
$body .= "<input type='hidden' name='download_csv' value='1' />\n";
$body .= "<input type='submit' value='Download csv' />\n";
$body .= "</form>\n";


$q = query ("select app_id, perf_id, perf_name, email"
            ." from applications"
            ." order by app_id");

$rows = array ();
while (($r = fetch ($q)) != NULL) {
    $cols = array ();
    $cols[] = intval ($r->app_id);
    $cols[] = intval ($r->perf_id);
    $cols[] = h($r->perf_name);
    $cols[] = h($r->email);
    $rows[] = $cols;
}

$body .= mktable (array ("app_id", "perf_id", "perf_name", "email"),
                  $rows);

pfinish ();

