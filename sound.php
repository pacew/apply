<?php

require_once("app.php");

pstart ();

$arg_download = intval(@$_REQUEST['download']);
$arg_view_csv = intval(@$_REQUEST['view_csv']);

$body .= "<div>\n";
$body .= mklink ("download", "sound.php?download=1");
$body .= " | ";
$body .= mklink ("view csv", "sound.php?view_csv=1");
   
$body .= "</div>\n";

$group_to_members = NULL;

populate_group_to_members();
read_notify_info();

$csvhdr = [];
$csvhdr[] = "eventid";
$csvhdr[] = "title";
$csvhdr[] = "evid";
$csvhdr[] = "group_size";
$csvhdr[] = "sond_needs";
$csvhdr[] = "group_name";

$rows = [];
$rownum = 0;
foreach ($webgrid as $webgrid_elt) {
    $evids = "";
    $counts = "";
    $needs = "";
    $names = "";
    
    foreach ($webgrid_elt->evids as $evid) {
        global $apps_by_evid;
        if (($app = @$apps_by_evid[$evid]) == NULL)
            continue;
        if (strcmp ($app->curvals['sound_needs'], "none") == 0)
            continue;
        $group_name = $app->curvals['group_name'];
        $group_id = name_to_id ($group_name);
        $leader_id = @$group_to_group_leader[$group_id];
        if ($leader_id == 0)
            continue;
        $members = $group_to_members[$group_id];
        $num_members = count($members);

        if ($evids)
            $evids .= " ";
        $evids .= $evid;
        
        if ($counts)
            $counts .= " | ";
        $counts .= $num_members;

        if ($needs)
            $needs .= " | ";
        $needs .= $app->curvals['sound_needs'];

        if ($names)
            $names .= " | ";
        $names .= $app->curvals['group_name'];
    }

    if ($evids) {
        $cols = [];
        $cols[] = h($webgrid_elt->eventid);
        $cols[] = h($webgrid_elt->title);
        $cols[] = h($evids);
        $cols[] = h($counts);
        $cols[] = h($needs);
        $cols[] = h($names);
        $rows[] = $cols;
    }
}

if ($arg_download || $arg_view_csv) {
    $outf = tmpfile ();

    fputcsv ($outf, $csvhdr);

    foreach ($rows as $row) {
        fputcsv ($outf, $row);
    }

    rewind ($outf);
    
    if ($arg_download) {
        ob_end_clean();
        header ("Content-Type: application/csv");
        header ("Content-Disposition: inline; filename=sound.csv");
        fpassthru ($outf);
        exit();
    }

    if ($arg_view_csv) {
        $body .= sprintf ("<div>year %d test_flag %d</div>\n",
            $view_year, $view_test_flag);
        $body .= "<pre>\n";
        $body .= h(fread ($outf, 100000));
        $body .= "</pre>\n";
        pfinish ();
    }
}

$body .= mktable($csvhdr, $rows);

pfinish();

    
