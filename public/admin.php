<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

pstart ();

$arg_download_csv = intval (@$_REQUEST['download_csv']);

if ($arg_download_csv) {
    $questions = get_questions ();
    $colnames = array ();
    $colnames[] = "app_id";
    $colnames[] = "perf_id";
    $colnames[] = "perf_name";
    foreach ($questions as $question)
        $colnames[] = $question['id'];

    $rows = array ();

    $stmt = sprintf ("select %s"
                     ." from applications"
                     ." order by app_id",
                     implode (',', $colnames));
    $q = query ($stmt);
    $app_ids = array ();
    while (($r = fetch ($q)) != NULL) {
        $app_id = $r->app_id;

        $app_ids[] = $app_id;
        
        $cols = array ();
        $cols[] = $r->app_id;
        $cols[] = $r->perf_id;
        $cols[] = $r->perf_name;
        foreach ($questions as $question) {
            $id = $question['id'];
            $cols[] = $r->$id;
        }

        $rows[$app_id] = $cols;
    }

    $stmt = sprintf ("select %s"
                     ." from overrides",
                     implode (',', $colnames));                     
    $q = query ($stmt);
    while (($r = fetch ($q)) != NULL) {
        $app_id = $r->app_id;

        if (($cols = @$rows[$app_id]) == NULL)
            continue;

        $idx = 0;

        $cols[$idx] = $app_id;
        $idx++;
        
        if ($r->perf_id)
            $cols[$idx] = $r->perf_id;
        $idx++;
                     
        if ($r->perf_name)
            $cols[$idx] = $r->perf_name;
        $idx++;

        foreach ($questions as $question) {
            $id = $question['id'];
            if ($r->$id) {
                $cols[$idx] = $r->$id;
            }
            $idx++;
        }

        $rows[$app_id] = $cols;
    }

    $f = tmpfile ();

    fputcsv ($f, $colnames);

    foreach ($app_ids as $app_id) {
        if (($cols = @$rows[$app_id]) == NULL)
            continue;
        fputcsv ($f, $cols);
    }
    rewind ($f);
    
    if (1) {
        ob_end_clean ();
        header ("Content-Type: application/csv");
        header ("Content-Disposition: attachment; filename=applications.csv");
        fpassthru ($f);
        exit ();
    } else {
        $body .= "<pre>\n";
        $body .= h(fread ($f, 100000));
        $body .= "</pre>\n";
        pfinish ();
    }
}

$body .= "<h2>Admin page</h2>\n";

$body .= "<div>";
$body .= mklink ("home", "/");
$body .= "</div>\n";

$body .= "<form action='admin.php'>\n";
$body .= "<input type='hidden' name='download_csv' value='1' />\n";
$body .= "<input type='submit' value='Download csv' />\n";
$body .= "</form>\n";


$body .= mklink ("debug questions", "index.php?show_all=1");



$q = query ("select app_id, perf_id, perf_name"
            ." from applications"
            ." order by app_id");

$rows = array ();
while (($r = fetch ($q)) != NULL) {
    $target = sprintf ("index.php?app_id=%d", $r->app_id);


    $cols = array ();
    $cols[] = mklink ($r->app_id, $target);
    $cols[] = mklink ($r->perf_id, $target);
    $cols[] = mklink ($r->perf_name, $target);
    $rows[] = $cols;
}

$body .= mktable (array ("app_id", "perf_id", "perf_name"),
                  $rows);


pfinish ();

