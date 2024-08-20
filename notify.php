<?php

require_once("app.php");

$arg_import = intval(@$_REQUEST['import']);
$arg_year = intval (@$_REQUEST['year']);
$arg_notify_id = intval(@$_REQUEST['notify_id']);

$webgrid = array ();
$webgrid[] = array("M74a", array(2661, 1466));
$webgrid[] = array("F1344", array(12852));

pstart ();

$body .= "<div>\n";
$body .= mklink ("import webgrid", "notify.php?import=1");
$body .= " | ";
$body .= "</div>\n";

$body .= "<div>west gallery 3976 ; bruce 1642</div>";

$pdb = get_db ("neffa_pdb", $pdb_params);

$q = query_db ($pdb,
    "select groupNumber, memberNumber"
    ." from annotated_members"
    ." where type = 'C'"
    ." order by groupNumber");
$group_to_group_leader = [];
$group_leader_to_groups = [];
while (($r = fetch ($q)) != NULL) {
    $group_number = intval($r->groupNumber);
    $leader_number = intval($r->memberNumber);
    $group_to_group_leader[$group_number] = $leader_number;
    if (! isset ($group_leader_to_groups[$leader_number]))
        $group_leader_to_groups[$leader_number] = [];
    $group_leader_to_groups[$leader_number][] = $group_number;
}

$performers = array();

$q = query_db ($pdb,
    "select number, performerName, email"
    ." from performers");

while (($r = fetch($q)) != NULL) {
    $perf = (object)NULL;
    $perf->number = intval($r->number);
    $perf->name = trim($r->performerName);
    $perf->email = trim($r->email);
    $performers[$perf->number] = $perf;
}

$apps = get_applications($arg_year);
add_evids($apps);

$evid_map = array();
foreach ($apps as $app) {
    $evid_map[$app->evid] = $app;
}

$name_id_to_email = array();

foreach ($apps as $app) {
    $app_name_id = intval($app->ei->evid_key);
    $email = $app->curvals['email'];
    $name_id_to_email[$app_name_id] = $email;
}

if (($year = $arg_year) == 0)
    $year = $view_year;

$notify = array();
$nofify_by_name_id = array();
$notify_by_notify_id = array();
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
    $elt->group_contact_id = intval(@$group_to_group_leader[$elt->name_id]);

    $notify[] = $elt;
    $notify_by_notify_id[$elt->notify_id] = $elt;
    $notify_by_name_id[$elt->name_id] = $elt;
}

$errs = array();

function we_need_to_notify ($name_id) {
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
        $notify_by_notify_id[$elt->notify_id] = $elt;
        $notify_by_name_id[$elt->name_id] = $elt;
    }
}

function notify_event($evid) {
    global $errs;
    if (($app = @$evid_map[$evid]) == NULL) {
        $errs[] = sprintf ("can't find application for evid %s", $evid);
        return;
    }

    if (($group_name = $app->curvals['group_name']) != "") {
        $group_name_id = name_to_id ($group_name);
        if ($group_name_id == 0) {
            $errs[] = sprintf("can't find group_id for %s in evid %s",
                $group_name, $app->evid);
            return;
        }

        global $group_to_group_leader;
        $leader_name_id = intval($group_to_group_leader[$group_name_id]);
        if ($leader_name_id == 0) {
            $errs[] = sprintf("can't find group leader for %s for evid %s",
                $group_name, $app->evid);
            return;
        }

        we_need_to_notify($leader_id);
    } else {
        var_dump($app);
        exit();
    }
}

if ($arg_notify_id != 0) {
    $body .= sprintf ("<div>details for %d</div>\n", $arg_notify_id);
    if (($elt = @$notify_by_notify_id[$arg_notify_id]) == NULL) {
        $body .= "<div>not found</div>\n";
        pfinish();
    }
    $body .= sprintf ("<div>%s</div>\n", mklink("[back]", "notify.php"));

    $body .= "<table class='twocol'>\n";
    $body .= "<tr><th>notify_id</th><td>";
    $body .= sprintf ("%d", $elt->notify_id);
    $body .= "</td></tr>\n";
    $body .= "<tr><th>name_id</th><td>";
    $body .= sprintf ("%d", $elt->name_id);
    $body .= "</td></tr>\n";
    $body .= "<tr><th>email</th><td>";
    $body .= h($elt->email);
    $body .= "</td></tr>\n";
    $body .= "<tr><th>sent_dttm</th><td>";
    $body .= h($elt->sent_dttm);
    $body .= "</td></tr>\n";
    $body .= "<tr><th>repsonded_dttm</th><td>";
    $body .= h($elt->responded_dttm);
    $body .= "</td></tr>\n";
    $body .= "</table>\n";

    $body .= sprintf ("<div>%s</div>\n", mklink("[back]", "notify.php"));

    $rows = array();
    foreach ($apps as $app) {
        if ($app->ei->evid_core == $elt->name_id) {
            $cols = array();
            $t = sprintf("index.php?app_id=%d", $app->app_id);
            $cols[] = mklink($app->app_id, $t);
            $cols[] = h($app->curvals['name']);
            $cols[] = h($app->curvals['email']);

            $rows[] = $cols;
        }
    }

    $body .= "<h3>apps</h3>\n";
    $body .= mktable(array("app_id", "name", "email"), $rows);
    
    $rows = array();
    if (isset ($group_leader_to_groups[$elt->name_id])) {
        foreach ($group_leader_to_groups[$elt->name_id] as $group_id) {
            $group = @$performers[$group_id];

            $cols = [];
            $cols[] = $group_id;
            $cols[] = @$group->name;
            $rows[] = $cols;
        }
    }
    if ($rows) {
        $body .= "<h3>groups</h3>\n";
        $body .= mktable(array("group_id", "group_name"), $rows);
    }

    pfinish ();
}


if ($arg_import == 1) {
    $f = fopen("webgrid.tsv", "r");
    while (($row = fgets ($f)) != NULL) {
        $cols = explode("\t", $row);
        $evid = $cols[0];

        // notify_event($evid);

        for ($idx = 7; $idx <= 10; $idx++) {
            $name_id = intval($cols[$idx]);
            if ($name_id) {
                $leader_id = @$group_to_group_leader[$name_id];
                if ($leader_id) {
                    we_need_to_notify($leader_id);
                } else {
                    we_need_to_notify($name_id);
                }
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
    $perf = @$performers[$elt->name_id];
    $cols = array();
    $t = sprintf("notify.php?notify_id=%d", $elt->notify_id);
    $cols[] = mklink($elt->notify_id, $t);
    $cols[] = h($elt->name_id);
    $cols[] = h(@$perf->name);
    $cols[] = h($elt->email);
    $cols[] = h($elt->group_contact_id);
    $cols[] = h($elt->sent_dttm);
    $cols[] = h($elt->responded_dttm);
    $rows[] = $cols;
    
}
            
$body .= sprintf("<div>%d rows</div>\n", count($notify));
$body .= mktable(array(
    "notify_id", "name_id", "name", "email", 
    "contact", "sent_dttm", "responded_dttm"),
    $rows);

pfinish();

