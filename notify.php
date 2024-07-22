<?php

require_once("app.php");

$arg_import = intval(@$_REQUEST['import']);
$arg_year = intval (@$_REQUEST['year']);


$webgrid = array ();
$webgrid[] = array("M74a", array(2661, 1466));
$webgrid[] = array("F1344", array(12852));

$group_to_group_leader = array ();
$group_to_group_leader[7824] = 2661;

pstart ();

$body .= "<div>\n";
$body .= mklink ("import webgrid", "notify.php?import=1");
$body .= " | ";
$body .= "</div>\n";

$apps = get_applications($arg_year);
add_evids($apps);

$evid_map = array();
foreach ($apps as $app) {
    $evid_map[$app->evid] = $app;
}

$name_id_to_email = array();

foreach ($apps as $app) {
    $app_name_id = $app->ei->evid_core;
    $email = $app->curvals['email'];
    $name_id_to_email[$app_name_id] = $email;
}

if (($year = $arg_year) == 0)
    $year = $view_year;

$notify = array();
$nofify_by_name_id = array();
$q = query ("select notify_id, name_id, email, sent_dttm, responded_dttm"
    ." from notify"
    ." where fest_year = ?"
    ." order by notify_id",
    $year);
while (($r = fetch ($q)) != NULL) {
    $elt = (object)NULL;
    $elt->notify_id = intval($r->notify_id);
    $elt->name_id = intval($r->name_id);
    $elt->email = trim($r->email);
    $elt->sent_dttm = $r->sent_dttm;
    $elt->responded_dttm = $r->responded_dttm;

    $notify[] = $elt;
    $notify_by_name_id[intval($r->name_id)] = $elt;
}

$errs = array();

function notify_name_id ($name_id) {
    global $notify, $notify_by_name_id, $year;
    global $name_id_to_email;
    
    if (! isset ($notify_by_name_id[$name_id])) {
        $elt = (object)NULL;
        $elt->notify_id = get_seq();
        $elt->name_id = $name_id;
        $elt->email = @$name_id_to_email[$name_id];
        $elt->fest_year = $year;
        query("insert into notify(notify_id, fest_year, name_id, email)"
            ." values(?, ?, ?, ?)",
            array($elt->notify_id, $year, $elt->name_id, $elt->email));
        $notify[] = $elt;
        $notify_by_name_id[$elt->name_id] = $elt;
    }
}

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
        $leader_name_id = intval($group_to_group_leader[$group_name_id]);
        if ($leader_name_id == 0) {
            $errs[] = sprintf("%s: can't find group leader for %s",
                              $app->evid, $group_name);
            return;
        }

        notify_name_id($leader_id);
    }
}

if ($arg_import == 1) {
    $f = fopen("webgrid.tsv", "r");
    while (($row = fgets ($f)) != NULL) {
        $cols = explode("\t", $row);
        $evid = $cols[0];
        notify_event($evid);
        for ($idx = 6; $idx <= 9; $idx++) {
            $name_id = intval($cols[$idx]);
            if ($name_id) {
                notify_name_id($name_id);
            }
        }
    }
    do_commits();

    $body .= "<div>import done</div>\n";
    $body .= mklink("[back]", "notify.php");
    pfinish();
}

$rows = array();
foreach ($notify as $elt) {
    $cols = array();
    $cols[] = h($elt->notify_id);
    $cols[] = h($elt->name_id);
    $cols[] = h($elt->email);
    $cols[] = h($elt->sent_dttm);
    $cols[] = h($elt->responded_dttm);
    $rows[] = $cols;
    
}
            
$body .= sprintf("<div>%d rows</div>\n", count($notify));
$body .= mktable(array(
    "notify_id", "name_id", "email", "sent_dttm", "responded_dttm"),
    $rows);

pfinish();

