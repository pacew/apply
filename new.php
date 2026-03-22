<?php

require_once("app.php");

pstart ();

$body .= "<h2>New performers</h2>\n";

$arg_download = intval(@$_REQUEST['download']);
$arg_view_csv = intval(@$_REQUEST['view_csv']);

$body .= "<div>\n";
$body .= mklink ("download", "new.php?download=1");
$body .= " | ";
$body .= mklink ("view csv", "new.php?view_csv=1");
   
$body .= "</div>\n";

$group_to_members = NULL;

populate_group_to_members();
read_notify_info();

$csvhdr = [];
$csvhdr[] = "eventid";
$csvhdr[] = "name";
$csvhdr[] = "email";
$csvhdr[] = "title";
$csvhdr[] = "c_notes";

$out_rows = [];

foreach ($webgrid as $webgrid_elt) {
    $evids = "";
    $counts = "";
    $needs = "";
    $names = "";
    
    foreach ($webgrid_elt->evids as $evid) {
        global $apps_by_evid;
        if (($app = @$apps_by_evid[$evid]) == NULL)
            continue;
        if (! preg_match('/NEW IN PDB/i', $app->curvals['C_notes']))
            continue;

        $out = (object)NULL;
        $out->eventid = $webgrid_elt->eventid;
        $out->name = $app->curvals['name'];
        $out->email = $app->curvals['email'];
        $out->title = $webgrid_elt->title;
        $out->c_notes = $app->curvals['C_notes'];
        $out_rows[] = $out;
    }
}

function cmp_email($a, $b) {
    return (strcmp (strtolower($a->email), strtolower($b->email)));
}
    
usort ($out_rows, 'cmp_email');


if ($arg_download || $arg_view_csv) {
    $outf = tmpfile ();

    fputcsv ($outf, $csvhdr);

    foreach ($out_rows as $out) {
        $cols = [];
        $cols[] = $out->eventid;
        $cols[] = $out->name;
        $cols[] = $out->email;
        $cols[] = $out->title;
        $cols[] = $out->c_notes;
        
        fputcsv ($outf, $cols);
    }

    rewind ($outf);
    
    if ($arg_download) {
        ob_end_clean();
        header ("Content-Type: application/csv");
        header ("Content-Disposition: inline; filename=new-performers.csv");
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

$rows = [];
foreach ($out_rows as $out) {
        $cols = [];
        $cols[] = h($out->eventid);
        $cols[] = h($out->name);
        $cols[] = h($out->email);
        $cols[] = h($out->title);
        $cols[] = h($out->c_notes);
        $rows[] = $cols;
}
$body .= mktable($csvhdr, $rows);

pfinish();

    
