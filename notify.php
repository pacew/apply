<?php

require_once("app.php");

$arg_year = intval (@$_REQUEST['year']);
$arg_notify_id = intval(@$_REQUEST['notify_id']);

$webgrid = array ();
$webgrid[] = array("M74a", array(2661, 1466));
$webgrid[] = array("F1344", array(12852));

pstart ();

$errs = [];
$info = [];
$stray_secondaries = [];

$pdb = get_db ("neffa_pdb", $pdb_params);

$name_id_to_pcode = [];
$q = query ("select id, pcode from pcodes");
while (($r = fetch ($q)) != NULL) {
    $name_id = intval($r->id);
    $pcode = trim($r->pcode);
    $name_id_to_pcode[$name_id] = $pcode;
}

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
    if (intval(@$group_to_group_leader[$group_number]) > 0) {
        $pcode = @$name_id_to_pcode[$group_number];
        if ($pcode) {
            $t = make_cgi_pcode_link($pcode);
        } else {
            $t = "";
        }

        $errs[] = sprintf ("group %s has more than one leader", 
            mklink_nw($group_number, $t));
    } else {
        $group_to_group_leader[$group_number] = $leader_number;
        if (! isset ($group_leader_to_groups[$leader_number]))
            $group_leader_to_groups[$leader_number] = [];
        $group_leader_to_groups[$leader_number][] = $group_number;
    }
}

// columns
// 1 evid
// 2 title
// 3 description
// 4 codes like D S, G S, T B N S
// 5 F, U, or S
// 6 room
// 7 time HHMM
// 8 to end: performer id's
$f = fopen("webgrid.tsv", "r");
$webgrid = [];
while (($row = fgets ($f)) != NULL) {
    $cols = explode("\t", $row);
    $elt = (object)NULL;
    $elt->evid = trim($cols[0]);
    $elt->title = trim($cols[1]);
    $elt->desc = trim($cols[2]);
    $elt->codes = trim($cols[3]);
    $elt->day = trim($cols[4]); // F U S for fri sat sun
    $elt->room = trim($cols[5]);
    $elt->time = trim($cols[6]); // HHMM
    $elt->name_ids = [];
    for ($idx = 7; $idx < count($cols); $idx++) {
        $name_id = intval(@$cols[$idx]);
        if ($name_id)
            $elt->name_ids[] = intval($cols[$idx]);
    }
    $webgrid[] = $elt;
}

$performers = array();
$q = query_db ($pdb,
    "select number, performerName, email"
    ." from performers");

while (($r = fetch($q)) != NULL) {
    $perf = (object)NULL;
    $perf->number = intval($r->number); // known in apply as name_id 
    $perf->name = trim($r->performerName);
    $perf->pdb_email = trim($r->email);
    $perf->apps = [];
    $performers[$perf->number] = $perf;
}



$apps = get_applications($arg_year);
add_evids($apps);

function canonical_evid($evid) {
    $key = strtolower($evid);
    if (! preg_match('/[a-z]$/', $key))
        $key .= "a";
    return($key);
}

$evid_map = array();
foreach ($apps as $app) {
    $evid_map[canonical_evid($app->evid)] = $app;
}

function evid_to_app($evid) {
    global $evid_map;
    return (@$evid_map[canonical_evid($evid)]);
}

foreach ($apps as $app) {
    $name_id = name_to_id($app->curvals['name']);
    if (($perf = @$performers[$name_id]) != NULL) {
        $perf->apps[] = $app;
    }
}

// might return empty string
function get_email($perf) {
    if (@$perf->best_email)
        return ($perf->best_email);

    $app_email = trim(@$apps[0]->curvals['email']);
    if ($app_email != "") {
        $perf->best_email = $app_email;
    } else {
        $perf->best_email = $perf->pdb_email;
    }

    $emails = [];
    $emails[strtolower($perf->pdb_email)] = 1;
    foreach ($perf->apps as $app) {
        $emails[strtolower(trim($app->curvals['email']))] = 1;
    }
    if (count($emails) > 1) {
        $msg = "<div>\n";
        $msg .= sprintf ("<div>performer %d has multiple emails</div>\n",
            $perf->number);
        if ($perf->pdb_email) {
            $msg .= sprintf ("<div>from performer db: %s</div>\n",
                h($perf->pdb_email));
        } else {
            $msg .= sprintf("<div>not set in perforer db</div>\n");
        }
        foreach ($perf->apps as $app) {
            $msg .= sprintf ("<div>%s in %s</div>\n",
                h($app->curvals['email']),
                h($app->curvals['event_title']));
        }

        $msg .= sprintf ("<div>used: %s</div>\n", h($perf->best_email));
        $msg .= "</div>\n";
        $info[] = $msg;
    }

    return ($perf->best_email);
}

if (($year = $arg_year) == 0)
    $year = $view_year;

$notify = array();
$nofify_by_name_id = array();
$notify_by_notify_id = array();
$q = query ("select notify_id, name_id, email"
    ." from notify"
    ." where fest_year = ?"
    ." order by notify_id",
    $year);
while (($r = fetch ($q)) != NULL) {
    $elt = (object)NULL;
    $elt->notify_id = intval($r->notify_id);
    $elt->name_id = intval($r->name_id);
    $elt->email = trim($r->email);

    $notify[] = $elt;
    $notify_by_notify_id[$elt->notify_id] = $elt;
    $notify_by_name_id[$elt->name_id] = $elt;
}

function we_need_to_notify ($kind, $name_id) {
    global $notify, $notify_by_name_id, $year;
    
    if (isset ($notify_by_name_id[$name_id]))
        return;
    
    global $errs, $performers;
    if (($perf = @$performers[$name_id]) == NULL)
        return (-1);

    if (($email = get_email($perf)) == "")
        return (-1);

    $elt = (object)NULL;
    $elt->notify_id = get_seq();
    $elt->name_id = $name_id;
    $elt->email = $email;
    $elt->fest_year = $year;
    query("insert into notify(notify_id, fest_year, name_id, email)"
        ." values(?, ?, ?, ?)",
        array($elt->notify_id, $year, $elt->name_id, $elt->email));
    $notify[] = $elt;
    $notify_by_notify_id[$elt->notify_id] = $elt;
    $notify_by_name_id[$elt->name_id] = $elt;

    return (0);
}

if ($arg_notify_id != 0) {
    $body .= sprintf ("<div>details for %d</div>\n", $arg_notify_id);
    if (($elt = @$notify_by_notify_id[$arg_notify_id]) == NULL) {
        $body .= "<div>not found</div>\n";
        pfinish();
    }
    $body .= sprintf ("<div>%s</div>\n", mklink("[back]", "notify.php"));

    if (($perf = @$performers[$elt->name_id]) == NULL) {
        $body .= "<div>can't find performer db entry for this person</div>\n";
        pfinish();
    }
    
    if (($pcode = @$name_id_to_pcode[$elt->name_id]) == NULL) {
        $body .= "<div>can't find pcode for this person</div>\n";
        pfinish();
    }

    $t = make_cgi_pcode_link($pcode);
    $pcode_link = mklink($t, $t);
    
    $vals = [];

    $vals['first_name'] = preg_replace ('/^[^,]*,/', "", $perf->name);
    $vals['pcode_link'] = $pcode_link;



    $body .= "<div class='notify_email'>\n";
    $body .= populate_template("notify.html", $vals);
    $body .= "</div>\n";
    
    pfinish ();
}

function make_evid_link($evid) {
    global $evid_map;
    if (($app = evid_to_app($evid)) == NULL)
        return sprintf ("[stray evid %s]", h($evid));
        
    if (($title = $app->curvals['event_title']) == "")
        $title = $app->curvals['group_name'];

    $label = sprintf ("%s %s", $evid, $title);

    $t = sprintf ("index.php?app_id=%d", $app->app_id);
    return (mklink($label, $t));
}


function walk_grid() {
    global $webgrid, $group_to_group_leader;
    foreach ($webgrid as $elt) {
        $success = [];
        $fails = [];
        foreach ($elt->name_ids as $name_id) {
            $leader_id = @$group_to_group_leader[$name_id];
            if ($leader_id) {
                if (we_need_to_notify("leader", $leader_id) < 0) {
                    $fails[] = $leader_id;
                } else {
                    $success[] = $leader_id;
                }
            } else {
                if (we_need_to_notify("individual", $name_id) < 0) {
                    $fails[] = $name_id;
                } else {
                    $success[] = $name_id;
                }
            }
        }
        
        global $performers, $name_id_to_pcode;

        if (count($fails) > 0) {
            if (count($success) > 0) {
                $msg = sprintf("<div>event %s</div>\n",
                    make_evid_link($elt->evid));
                $msg .= "<ul class='notify_err'>\n";
                $msg .= "<li>";
                $msg .= "notified ";
                foreach ($success as $name_id) {
                    $p = @$performers[$name_id];
                    $pcode = @$name_id_to_pcode[$name_id];
                    if ($p && $pcode) {
                        $t = make_cgi_pcode_link($pcode);
                        $msg .= sprintf(" %s", mklink_nw($p->name, $t));
                    } else {
                        $msg .= sprintf(" %d", $name_id);
                    }
                }
                $msg .= "</li>\n";
                $msg .= "<li>";
                $msg .= "skipped ";
                foreach ($fails as $name_id) {
                    $p = @$performers[$name_id];
                    $pcode = @$name_id_to_pcode[$name_id];
                    if ($p && $pcode) {
                        $t = make_cgi_pcode_link($pcode);
                        $msg .= sprintf(" %s", mklink_nw($p->name, $t));
                    } else {
                        $msg .= sprintf(" %d", $name_id);
                    }
                }
                $msg .= "</li>\n";
                $msg .= "</ul>\n";
                global $stray_secondaries;
                $stray_secondaries[] = $msg;
            } else {
                $msg = sprintf ("<div>can't find email for event %s</div>",
                    make_evid_link($elt->evid));
                $msg .= "<li>";
                $msg .= "skipped ";
                foreach ($fails as $name_id) {
                    $p = @$performers[$name_id];
                    $pcode = @$name_id_to_pcode[$name_id];
                    if ($p && $pcode) {
                        $t = make_cgi_pcode_link($pcode);
                        $msg .= sprintf(" %s", mklink_nw($p->name, $t));
                    } else {
                        $msg .= sprintf(" %d", $name_id);
                    }
                }
                $msg .= "</li>\n";
                $msg .= "</ul>\n";

                global $errs;
                $errs[] = $msg;
            }
        }
    }
    do_commits();
}

walk_grid();

if (count($errs) > 0) {
    $body .= "<h1 style='color:red'>see end of page for errors</h1>\n";
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
    $rows[] = $cols;
    
}
            
$body .= sprintf("<div>%d rows</div>\n", count($notify));
$body .= mktable(array(
    "notify_id", "name_id", "name", "email"),
    $rows);

if (count($errs) > 0) {
    $body .= "<h1 style='color:red'>errors</h1>\n";
    foreach ($errs as $err) {
        $body .= sprintf ("<div>%s</div>\n", $err);
    }
}
        
if (count($stray_secondaries) > 0) {
    $body .= "<h1>stray secondaries</h1>\n";
    $body .= "<p>For these events, we have a addresses for some but not"
          ." all of the people in webgrid</p>\n";

    foreach ($stray_secondaries as $elt) {
        $body .= sprintf ("<div>%s</div>\n", $elt);
    }
}


if (count($info) > 0) {
    $body .= "<h1>info messages</h1>\n";
    foreach ($info as $elt) {
        $body .= sprintf ("<div>%s</div>\n", $elt);
    }
}

pfinish();

