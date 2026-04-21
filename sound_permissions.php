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
    if (strcmp ($webgrid_elt->room, "Seminar") != 0)
        continue;

    $evids = "";
    foreach ($webgrid_elt->name_ids as $name_id) {
        if (($perf = @$performers[$name_id]) == NULL)
            continue;
        
        $record = "";
        if (($conf = @$confirmations[$name_id]) != NULL)
            $record = intval($conf->record);

        $group_name = "";
        $leader_id = 0;
        $name_id = 0;
        foreach ($webgrid_elt->evids as $evid) {
            if (($app = @$apps_by_evid[$evid]) != NULL) {
                $t = sprintf ("index.php?app_id=%d", $app->app_id);
                $evids .= mklink ($evid, $t);
                $evids .= " ";

                $name_id = $app->neffa_id;
                if (strcmp ($app->curvals['main_performer'], "Group") == 0) {
                    $group_name = $app->curvals['group_name'];
                    $group_id = name_to_id ($group_name);
                    $leader_id = @$group_to_group_leader[$group_id];

                }
            }
        }
    }

    if ($leader_id)
        $name_id = $leader_id;

    $contact_name = "";
    if (($perf = @$performers[$name_id]) != NULL) {
        $contact_name = $perf->name;
    }

    $evt = (object)NULL;
    $evt->title = $webgrid_elt->title;
    $evt->eventid = $webgrid_elt->eventid;
    $evt->evids = $evids;
    $evt->record = $record;
    $evt->group_name = $group_name;
    $evt->contact_name = $contact_name;
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
    $cols[] = h($evt->contact_name);
    $cols[] = h($evt->group_name);
    $cols[] = $evt->evids;
    $rows[] = $cols;
}

$body .= "<p>record response 3 means 'No'</p>";
$body .= mktable (array("eventid", 
        "record response", 
        "title", 
        "contact",
        "group",
        "evids"), $rows);

pfinish();
