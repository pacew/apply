<?php

require_once("app.php");

pstart ();

read_notify_info();

$rows = [];
foreach ($webgrid as $wg) {
    if ($wg->room != 'Seminar')
        continue;

    $cols = [];

    $txt = "";
    foreach ($wg->evids as $evid) {
        $txt .= sprintf (" %s", h($evid));
    }
    $cols[] = $txt;
    $cols[] = $wg->title;
    $cols[] = $wg->codes;
    $cols[] = $wg->day;
    $cols[] = $wg->time;

    $txt = "";
    foreach ($wg->name_ids as $name_id) {
        $txt .= sprintf (" %s", h($name_id));
    }
    $cols[] = $txt;
    $rows[] = $cols;
}

$body .= mktable(array("evids", "title", "codes", "day", "time", "name_ids"),
 $rows);

pfinish();

