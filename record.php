<?php

require_once("app.php");

$arg_download = intval(@$_REQUEST['download']);

pstart ();

read_notify_info();

function cmp_wg($a, $b) {
    if (($ret = strcmp ($a->day, $b->day)) != 0)
        return ($ret);
    if (($ret = $a->time - $b->time) != 0)
        return ($ret);
    return (0);
}

usort($webgrid, 'cmp_wg');

$rows = [];
foreach ($webgrid as $wg) {
    if ($wg->room != 'Seminar')
        continue;

    $cols = [];

    $cols[] = $wg->day;
    $cols[] = $wg->time;

    $txt = "";
    foreach ($wg->evids as $evid) {
        $txt .= sprintf (" %s", h($evid));
    }
    $cols[] = $txt;
    $cols[] = $wg->title;
    $cols[] = $wg->codes;

    $txt = "";
    foreach ($wg->name_ids as $name_id) {
        if (($perf = @$performers[$name_id]) != NULL) {
            $txt .= sprintf (" %s", h($perf->name));
        }
        if (($conf = @$confirmations[$name_id]) != NULL) {
        }
    }
    $cols[] = $txt;

    $txt = "";
    foreach ($wg->name_ids as $name_id) {
        if (($conf = @$confirmations[$name_id]) != NULL) {
            $txt .= sprintf (" %s", h($conf->record));
        }
    }
    $cols[] = $txt;

    $rows[] = $cols;
}

$header = array("day", "time", "evids", "title", "codes", "name", "record");

if ($arg_download) {
    $outf = tmpfile();
    fputcsv($outf, $header);
    foreach ($rows as $row) {
        fputcsv ($outf, $row);
    }
    rewind ($outf);
    
    ob_end_clean();
    header ("Content-Type: application/csv");
    header ("Content-Disposition: inline; filename=record-consent.csv");
    fpassthru ($outf);
    exit();
}

$body .= "<div>\n";
$body .= mklink ("download", "record.php?download=1");
$body .= "</div>\n";

$body .= mktable($header, $rows);

pfinish();

