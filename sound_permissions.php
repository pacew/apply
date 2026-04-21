<?php

require_once("app.php");

pstart ();

$body .= "<h2>Sound permissions</h2>\n";

$arg_download = intval(@$_REQUEST['download']);
$arg_view_csv = intval(@$_REQUEST['view_csv']);


$group_to_members = NULL;
populate_group_to_members();
read_notify_info();

$evts = [];

foreach ($webgrid as $webgrid_elt) {
    if (strcmp ($webgrid_elt->room, "Sterling") != 0)
        continue;

    $evids = "";
    foreach ($webgrid_elt->name_ids as $name_id) {
        if (($perf = @$performers[$name_id]) == NULL)
            continue;
        
        $record = "";
        if (($conf = @$confirmations[$name_id]) != NULL)
            $record = intval($conf->record);

        foreach ($webgrid_elt->evids as $evid) {
            if (($app = @$apps_by_evid[$evid]) != NULL) {
                $t = sprintf ("index.php?app_id=%d", $app->app_id);
                $evids .= mklink ($evid, $t);
                $evids .= " ";
            }
        }
    }

    $evt = (object)NULL;
    $evt->title = $webgrid_elt->title;
    $evt->eventid = $webgrid_elt->eventid;
    $evt->evids = $evids;
    $evt->record = $record;
    $evts[] = $evt;

}

function cmp_evt ($a, $b) {
    return (strcmp ($a->eventid, $b->eventid));
}

usort ($evts, 'cmp_evt');

$rows = [];
foreach ($evts as $evt) {
    $cols = [];
    $cols[] = h($evt->eventid);
    $cols[] = h($evt->record);
    $cols[] = h($evt->title);
    $cols[] = $evt->evids;
    $rows[] = $cols;
}

$body .= mktable (array(), $rows);

pfinish();
