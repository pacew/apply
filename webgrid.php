<?php

require_once("app.php");

$arg_year = intval (@$_REQUEST['year']);


$webgrid = array ();
$webgrid[] = array("M74a", array(2661, 1466));
$webgrid[] = array("F1344", array(12852));

$group_to_group_leader = array ();
$group_to_group_leader[7824] = 2661;

pstart ();

$body .= "hello";

$apps = get_applications($arg_year);
add_evids($apps);

$evid_map = array();
foreach ($apps as $app) {
    $evid_map[$app->evid] = $app;
}

$notify_ids = array();
$errs = array();

function notify_event($evid) {
    global $errs;
    if (($app = @$evid_map[$evid]) == NULL) {
        $errs[] = sprintf ("%s: can't find app", $evid);
        return;
    }

    if (($group_name = $app->curvals['group_name']) != "") {
        $group_name_id = name_to_id ($group_name);
        if ($group_name_id == 0) {
            $errs[] = sprintf("%s: can't find group_id for %s",
                              $app->evid, $group_name);
            return;
        }

        global $group_to_group_leader;
        $notify_id = intval($group_to_group_leader[$group_name_id]);
        if ($notify_id == 0) {
            $errs[] = sprintf("%s: can't find group leader for %s",
                              $app->evid, $group_name);
            return;
        }

        global $notify_ids;
        $notify_ids[$notify_id] = 1;
    }
}

$f = fopen("webgrid.tsv", "r");
while (($row = fgets ($f)) != NULL) {
    $cols = explode("\t", $row);
    $evid = $cols[0];
    notify_event($evid);
    for ($idx = 6; $idx <= 9; $idx++) {
        $name_id = intval($cols[$idx]);
        if ($name_id) {
            global $notify_ids;
            $notify_ids[$name_id] = 1;
        }
    }
}

foreach ($notify_ids as $key => $id) {
    $body .= sprintf ("%d ", $key);
}
pfinish();





foreach ($webgrid as $wg) {
    $wg_evid = $wg[0];
    $wg_name_ids = $wg[1];

    $app = $evid_map[$wg_evid];

    $name_id = who_to_notify($app);
    var_dump($name_id);
    var_dump($errs);
    break;
}



pfinish ();

