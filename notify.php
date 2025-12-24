<?php

require_once("app.php");

$arg_notify_id = intval(@$_REQUEST['notify_id']);
$arg_reload = intval (@$_REQUEST['reload']);
$arg_upload = intval (@$_REQUEST['upload']);
$arg_return_json = intval(@$_REQUEST['return_json']);
$arg_show_rejected = intval(@$_REQUEST['show_rejected']);

$arg_upload_passwd = trim(@$_REQUEST['upload_passwd']);
$arg_by_prefix = intval (@$_REQUEST['by_prefix']);

if ($arg_upload_passwd) {
    $expect = getvar("webgrid_upload_passwd");
    if (strcmp ($arg_upload_passwd, $expect) != 0) {
        $ret = (object)NULL;
        $ret->status = 'not-authorized';
        json_finish($ret);
    }
    $anon_ok = 1;
}

pstart ();

if (isset ($_REQUEST['set_confirmed_filter'])) {
    putsess("confirmed_filter", intval($_REQUEST['set_confirmed_filter']));
    redirect("notify.php");
}

$confirmed_filter = intval(getsess("confirmed_filter"));



if ($arg_reload == 1) {
    query ("update notify set scheduled = 0");
    redirect ("notify.php");
}

$webgrid_file = sprintf ("%s/webgrid.tsv", $cfg['aux_dir']);

if ($arg_upload == 1) {
    if (@$_FILES['webgrid']['tmp_name'] != "") {
        $webgrid = file_get_contents($_FILES['webgrid']['tmp_name']);
        file_put_contents($webgrid_file, $webgrid);
    }

    if ($arg_return_json) {
        $ret = (object)NULL;
        $ret->status = 'ok';
        json_finish($ret);
    }

    flash ("Success.  File is staged.  Click reload (below) to start using");
    redirect("notify.php");
}


read_notify_info();

$q = query ("select interaction_id, ts, name_id"
    ." from interactions"
    ." where fest_year = ?"
    ."   and test_flag = ?"
    ." order by interaction_id",
    array($submit_year, $submit_test_flag));
$interactions = array();
while (($r = fetch ($q)) != NULL) {
    $inter = (object)NULL;
    $inter->interaction_id = intval($r->interaction_id);
    $inter->ts = trim($r->ts);
    $inter->name_id = intval($r->name_id);
    $interactions[$inter->name_id] = $inter;
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


function we_need_to_notify ($name_id, $evid) {
    global $notify, $notify_by_name_id, $view_year;
    global $notify_by_notify_id, $notify_by_email;
    
    if (($elt = @$notify_by_name_id[$name_id]) != NULL) {
        if ($elt->scheduled == 0) {
            $elt->scheduled = 1;
            query ("update notify set scheduled = 1 where notify_id = ?",
                $elt->notify_id);
        }
        if (! isset ($elt->evids))
            $elt->evids = array();
        $elt->evids[$evid] = 1;
        return (0);
    }
    
    global $errs, $performers;
    if (($perf = @$performers[$name_id]) == NULL)
        return (-1);

    if (($email = get_email($perf, $errs)) == "")
        return (-1);

    $elt = (object)NULL;
    $elt->notify_id = get_seq();
    $elt->name_id = $name_id;
    $elt->email = $email;
    $elt->fest_year = $view_year;
    $elt->scheduled = 1;
    query("insert into notify(notify_id, fest_year, name_id, email, scheduled)"
        ." values(?, ?, ?, ?, ?)",
        array($elt->notify_id, $view_year, $elt->name_id, $elt->email,
            $elt->scheduled));

    if (! isset ($elt->evids))
        $elt->evids = array();
    $elt->evids[$evid] = 1;
    
    $notify[] = $elt;
    $notify_by_notify_id[$elt->notify_id] = $elt;
    $notify_by_name_id[$elt->name_id] = $elt;
    $notify_by_email[strtolower($elt->email)] = $elt;

    return (0);
}

function walk_grid() {
    global $webgrid, $group_to_group_leader;
    foreach ($webgrid as $webgrid_elt) {
        $success = [];
        $fails = [];
        $evid_not_found = [];
        foreach ($webgrid_elt->evids as $evid) {
            global $apps_by_evid;
            if (($app = @$apps_by_evid[$evid]) == NULL) {
                $evid_not_found[] = $evid;
                continue;
            }

            global $name_id_to_pcode;

            $msg = "";
            if (strcmp ($app->curvals['main_performer'], "Group") == 0) {
                $group_name = $app->curvals['group_name'];
                $group_id = name_to_id ($group_name);
                $leader_id = @$group_to_group_leader[$group_id];
                if ($leader_id == 0) {
                    $msg .= sprintf("<div>can't find leader_id"
                        ." for group %s</div>\n", h($group_name));
                } else if (we_need_to_notify($leader_id, $evid) < 0) {
                    $msg .= sprintf ("<div>can't find email for leader %d of"
                        ." %s</div>\n", $leader_id, h($group_name));

                    $msg .= "<div>probably, the leader of this group"
                        ." did not themselves make an application this year"
                        ."</div>\n";
                }
            } else {
                if (we_need_to_notify($app->neffa_id, $evid) < 0) {
                    $msg .= sprintf ("<div>can't find email for individual"
                        ." %d</div>\n", $app->name_id);
                }
            }

            if ($msg) {
                global $errs;
                $err = "<div>\n";
                $err .= sprintf ("<div>problems with %s</div>", 
                    make_evid_link($evid));
                $err .= "</div>\n";
                $err .= $msg;
                $errs[] = $err;
            }
        }
    }
    do_commits();
}

walk_grid();

if ($arg_notify_id != 0) {
    $body .= "<div class='admin_box'>\n";
    $body .= sprintf ("<div>details for %d</div>\n", $arg_notify_id);
    if (($elt = @$notify_by_notify_id[$arg_notify_id]) == NULL) {
        $body .= "<div>not found</div>\n";
        pfinish();
    }
    $body .= sprintf ("<div>%s</div>\n", mklink("[back]", "notify.php"));

    if ($elt->scheduled == 0) {
        $body .= "<div class='attention'>"
            ." this performer is in the notify table"
            ." but not webgrid ... maybe a weird"
            ." error due to a late webgrid update"
            ."</div>\n";
    }

    if (($perf = @$performers[$elt->name_id]) == NULL) {
        $body .= "<div>can't find performer db entry for this person</div>\n";
        pfinish();
    }
    
    if (($pcode = @$name_id_to_pcode[$elt->name_id]) == NULL) {
        $body .= "<div>can't find pcode for this person</div>\n";
        pfinish();
    }

    $confirm2_link = make_confirm2_link($pcode);
    $body .= sprintf ("<p>%s</p>\n", mklink($confirm2_link, $confirm2_link));

    $rows = array();
    foreach ($webgrid as $webgrid_elt) {
        foreach ($webgrid_elt->evids as $evid) {
            if (($app = @$apps_by_evid[$evid]) == NULL)
                continue;

            if (strcmp($app->curvals['main_performer'], "Group") == 0) {
                $group_name = $app->curvals['group_name'];
                $group_id = name_to_id ($group_name);
                $name_id = @$group_to_group_leader[$group_id];
            } else {
                $name_id = $app->neffa_id;
            }

            if ($name_id == $elt->name_id) {
                $eventid = $webgrid_elt->eventid;

                $cols = array();

                $t = sprintf ("index.php?app_id=%d", $app->app_id);
                $cols[] = mklink ($app->evid, $t);

                if (strcmp($app->curvals['main_performer'], "Group") == 0) {
                    $txt = $app->curvals['group_name'];
                } else {
                    $txt = $app->curvals['event_title'];
                }
                
                $cols[] = h($txt);

                $t = sprintf("performer.php?pcode=%s&eventid=%s",
                    rawurlencode($pcode), rawurlencode($eventid));
                $cols[] = mklink ($eventid, $t);
                $rows[] = $cols;
            }
        }
    }
    $body .= mktable (array ("evid", "title", "performer edit"), $rows);


    $q = query ("select interaction_id, ts, event"
        ." from interactions"
        ." where name_id = ?"
        ."   and fest_year = ?"
        ."   and test_flag = ?"
        ." order by interaction_id",
        array($elt->name_id, $submit_year, $submit_test_flag));
    $rows = array();
    while (($r = fetch ($q)) != NULL) {
        $cols = array();
        $cols[] = db_time_to_eastern($r->ts);
        $cols[] = h($r->event);
        $rows[] = $cols;
    }
    $body .= "<h3>email notifications</h3>\n";
    if (count ($rows) > 0) {
        $body .= mktable (array ("timestamp", "event"), $rows);
    } else {
        $body .= "<div>(none)</div>\n";
    }


    $body .= "</div>\n"; /* admin_box */

    $em = prepare_notify_email($elt->email, $perf, $pcode);

    $body .= "<div class='notify_email'>\n";
    $body .= sprintf ("<p>To: %s<br/>\n", h($em->to_email));
    $body .= sprintf ("Subject: %s</p>\n", h($em->subject));

    $body .= $em->html;
    $body .= "<hr/>\n";
    $body .= "<pre>\n";
    $body .= h($em->plain);
    $body .= "</pre>\n";
    $body .= "</div>\n";
    
    pfinish ();
}

function do_rejects () {
    global $notify_by_name_id, $notify_by_email, $webgrid_by_evid;
    global $body;
    
    $rows = array ();
    foreach (get_applications() as $app) {
        if (@$app->curvals['do_not_import'] == "Suppress")
            continue;
        
        if (@$notify_by_name_id[$app->neffa_id])
            continue;

        if (@$webgrid_by_evid[$app->evid])
            continue;

        $email = trim(strtolower($app->curvals['email']));
        if (@$notify_by_email[$email])
            continue;
        
        reject_performer ($email, $app);
    }
}

if ($arg_show_rejected) {
    global $rejected;
    
    $error_count = 0;
    
    do_rejects();
    
    $done = array();
    $q = query ("select email, ts"
        ." from rejected"
        ." where fest_year = ? and test_flag = ?"
        ." order by rejected_id",
        array($submit_year, $submit_test_flag));
    while (($r = fetch ($q)) != NULL) {
        $email = trim(strtolower($r->email));
        $ts = $r->ts;
        $done[$email] = $ts;
    }

    $body .= "<h1>Rejected performers</h1>\n";
    $body .= sprintf ("<div>%s</div>\n",
        mklink ("back to notify list", "notify.php"));

    global $group_filter;
    if ($group_filter) {
        $body .= "<div class='attention'>\n";
        $body .= "A group filter is in effect.  Go back to ";
        $body .= mklink ("applications", "admin.php");
        $body .= " to change the filter setting.";
        $body .= "</div>\n";
    }

    $groups = array ();

    foreach ($rejected as $rej) {
        $visible_apps = 0;
        foreach ($rej->apps as $app) {
            if (passes_group_filter ($app))
                $visible_apps += 1;
        }

        if ($visible_apps == 0)
            continue;

        $item = "";
        $item .= sprintf ("<h2>%s</h2>\n", h($rej->email));

        $reasons = array ();
        $last_reason = "";
        
        foreach ($rej->apps as $app) {
            $reason = trim (@$app->curvals['C_rejection_reason']);
            $last_reason = $reason;
            if ($reason)
                $reasons[$reason] = 1;
        }
        
        if (count($reasons) == 1) {
            $have_common_reason = 1;
        } else {
            $have_common_reason = 0;
            $error_count += 1;
            $item .= "<div class='attention'>ERROR: these app(s) don't"
                ." have a single common "
                ." rejection reason</div>\n";
        }

        foreach ($rej->apps as $app) {
            $t = sprintf ("index.php?app_id=%d", $app->app_id);
            $item .= "<div>\n";

            $txt = sprintf ("%s ", $app->evid);
            if (@$app->curvals['group_name'])
                $txt .= sprintf (" G:%s ", $app->curvals['group_name']);

            if (@$app->curvals['event_title'])
                $txt .= sprintf (" T:%s", $app->curvals['event_title']);

            if (@$app->curvals['name'])
                $txt .= sprintf (" N: %s", $app->curvals['name']);

            $item .= mklink ($txt, $t);

            $item .= "</div>\n";

            if ($have_common_reason == 0) {
                $reason = trim (@$app->curvals['C_rejection_reason']);
                $item .= sprintf ("<div>%s</div>\n", $reason);
            }
        }

        if ($have_common_reason) {
            $item .= sprintf ("<div>%s</div>\n", $last_reason);

            $first_name = preg_replace ('/^[^,]*,/', "", 
                $app->curvals['name']);

            $email = trim(strtolower($rej->email));

            $item .= "<form action='rejection.php'>\n";
            $item .= sprintf ("<input type='hidden'"
                ." name='email' value='%s' />\n", 
                rawurlencode($email));
            $item .= sprintf ("<input type='hidden'"
                ." name='first_name' value='%s' />\n", 
                rawurlencode($first_name));
            $item .= sprintf ("<input type='hidden'"
                ." name='text' value='%s' />\n", rawurlencode($last_reason));
            $item .= "<input type='submit' value='View rejection email' />\n";

            if (($ts = @$done[$email]) != NULL) {
                $item .= sprintf ("sent %s", db_time_to_eastern($ts));
            } else {
                $item .= "<span class='attention'>pending</span>\n";
            }
            $item .= "</form>\n";
        }

        $prefix = $app->evid[0];
        if (! isset ($groups[$prefix])) {
            $groups[$prefix] = array ();
        }
        $groups[$prefix][] = $item;
    }

    $body .= sprintf ("<div>error count %d</div>\n", $error_count);

    $desired_order = array ("T", "P", "R", "X", "M", "F", "J");

    foreach ($desired_order as $prefix) {
        if (isset ($groups[$prefix])) {
            $body .= "<hr/>\n";
            foreach ($groups[$prefix] as $item) {
                $body .= $item;
            }
            $groups[$prefix] = array ();
        }
    }

    foreach ($groups as $group) {
        foreach ($group as $item) {
            $body .= $item;
        }
    }

    $body .= sprintf ("<div>error count %d</div>\n", $error_count);

    pfinish ();
}


$body .= "<div class='admin_box'>\n";
$body .= "<form action='notify.php' method='post'"
    ." enctype='multipart/form-data'>\n";

$body .= "<input type='hidden' name='upload' value='1' />\n";
$body .= "Manually upload a new copy of the tsv file\n";
$body .= "<input type='file' name='webgrid' />\n";
$body .= "<input type='submit' value='upload' />\n";
$body .= "</form>\n";


$body .= mklink ("reload webgrid", "notify.php?reload=1");
$body .= "</div>\n";

$unsched = "";
foreach ($notify as $elt) {
    if ($elt->scheduled == 0) {
        $t = sprintf ("notify.php?notify_id=%d", $elt->notify_id);
        $unsched .= sprintf ("<div>%s %s</div>\n", 
            mklink ($elt->notify_id, $t),
            h($elt->email));
    }
}
if ($unsched != "") {
    $errs[] = "<h1>people in notify table but not in webgrid</h1>\n"
        ."<p>may happen due to late webgrid change</p>\n"
        . $unsched;
}


if (count($errs) > 0) {
    $body .= "<h1 style='color:red'>see end of page for errors</h1>\n";
}

$body .= "<div style='padding:1em'>\n";
$body .= "<div>\n";
$t = "notify.php?show_rejected=1";
$body .= mklink ("go to rejected", $t);
$body .= "</div>\n";
$body .= "<div>\n";
$body .= mklink ("ungrouped", "notify.php");
$body .= " | ";
$body .= mklink ("group by prefix", "notify.php?by_prefix=1");
$body .= "</div>\n";
$body .= sprintf ("<div>%s</div>\n", mklink ("set nag text", "nag.php"));

$body .= "<div>\n";
$body .= "confirmed filter: ";

$t = "notify.php?set_confirmed_filter=0";
if ($confirmed_filter == 0)
    $t = "";
$body .= mklink ("all", $t);
$body .= " | ";
$t = "notify.php?set_confirmed_filter=-1";
if ($confirmed_filter == -1)
    $t = "";
$body .= mklink ("unconfirmed", $t);
$body .= " | ";
$t = "notify.php?set_confirmed_filter=2";
if ($confirmed_filter == 2)
    $t = "";
$body .= mklink ("confirmed", $t);
$body .= " | ";
$t = "notify.php?set_confirmed_filter=3";
if ($confirmed_filter == 3)
    $t = "";
$body .= mklink ("declined", $t);

$body .= "</div>\n";

$body .= "<div>\n";

$body .= "</div>\n";


$body .= "</div>\n";


$groups = array ();

$all_rows = array();
foreach ($notify as $elt) {
    if ($elt->scheduled == 0)
        continue;
    $perf = @$performers[$elt->name_id];
    $cols = array();

    $item = sprintf ("<input type='checkbox'"
        ." name='notify_ids[]' value='%d' />\n",
        $elt->notify_id);
    $t = sprintf("notify.php?notify_id=%d", $elt->notify_id);
    $item .= mklink($elt->notify_id, $t);
    $cols[] = $item;
    $cols[] = h($elt->name_id);
    $cols[] = h(@$perf->name);
    $t = sprintf ("mailto:%s", $elt->email);
    $cols[] = mklink($elt->email, $t);

    get_email($perf);
    $cols[] = h(@$perf->possible_phone);


    $pcode = neffa_id_to_pcode($elt->name_id);
    $cols[] = mklink("magic", make_confirm2_link($pcode));

    $ccode = 0;
    if (($conf = @$confirmations[$elt->name_id]) != NULL) {
        $ccode = intval($conf->confirm);
    }

    $c = "";
    if ($confirmed_filter == -1) {
        if ($ccode != 0)
            continue;
    } else if ($confirmed_filter > 0) {
        if ($ccode != $confirmed_filter)
            continue;
    }

    switch ($ccode) {
    case 0:
        $c = "";
        break;
    case 2:
        $c = "confirmed";
        break;
    case 3:
        $c = "declined";
        break;
    default:
        $c = sprintf ("code %d", $conf->confirm);
        break;
    }
    $cols[] = h($c);

    $rec = "";
    if (@$conf->record > 0)
        $rec = sprintf ("%d", $conf->record);
    $cols[] = h($rec);

    $sent = "";
    if (($inter = @$interactions[$elt->name_id]) != NULL) {
        $sent = db_time_to_eastern($inter->ts);
    }
    $cols[] = h($sent);

    $all_rows[] = $cols;

    if (isset ($elt->evids)) {
        foreach ($elt->evids as $evid => $dummy) {
            $prefix = $evid[0];
            if (! isset ($groups[$prefix])) {
                $groups[$prefix] = array();
            }
            $groups[$prefix][$elt->notify_id] = $cols;
        }
    }
}
            
$body .= "<form action='email.php' method='post' />";

$body .= "<input type='submit'"
    ." value='prepare emails to marked performers' />\n";

$body .= sprintf("<div>%d performers</div>\n", count($notify));

$header = array("notify_id", "name_id", "name", "email", "possible phone",
    "magic", "confirmed", "record", "sent");

$body .= "<div>the phone numbers are taken from an application associated"
    ." with the performer, but might be for a different person if"
    ." the applicant has been changed or if a group is involved</div>\n";

if ($arg_by_prefix == 0) {
    $body .= mktable($header, $all_rows);
} else {
    $desired_order = array ("T", "P", "R", "X", "M", "F", "J");

    foreach ($desired_order as $prefix) {
        if (isset ($groups[$prefix])) {
            $body .= "<hr/>\n";
            $rows = array();
            foreach ($groups[$prefix] as $cols) {
                $rows[] = $cols;
            }
            $body .= sprintf ("<h2>prefix %s</h2>\n", $prefix);
            $body .= mktable($header, $rows);
            $groups[$prefix] = array ();
        }
    }

    $rows = array ();
    foreach ($groups as $key => $items) {
        foreach ($items as $cols) {
            if (count ($cols) > 0)
                $rows[] = $cols;
        }
    }

    if (count($rows) > 0) {
        $body .= "<hr/>\n";
        $body .= "<h2>stray prefix</h2>\n";
        $body .= mktable($header, $rows);
    }
}

$body .= "</form>\n";

if (count($errs) > 0) {
    $body .= "<h1 style='color:red'>errors</h1>\n";
    foreach ($errs as $err) {
        $body .= sprintf ("<div class='notify_error'>%s</div>\n", $err);
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

