<?php

require_once("app.php");

$arg_notify_id = intval(@$_REQUEST['notify_id']);
$arg_reload = intval (@$_REQUEST['reload']);
$arg_upload = intval (@$_REQUEST['upload']);
$arg_return_json = intval(@$_REQUEST['return_json']);
$arg_show_rejected = intval(@$_REQUEST['show_rejected']);

$arg_upload_passwd = trim(@$_REQUEST['upload_passwd']);


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

if ($arg_reload == 1) {
    query ("delete from notify");
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

// might return empty string
function get_email($perf) {
    if (@$perf->best_email)
        return ($perf->best_email);

    $perf_email = trim(@$pref->apps[0]->curvals['email']);

    $emails = [];
    if ($perf_email)
        $emails[strtolower($perf_email)] = 1;
    $first_app_email = "";
    foreach ($perf->apps as $app) {
        $app_email = trim($app->curvals['email']);
        if ($app_email) {
            if ($first_app_email == "")
                $first_app_email = $app_email;
            $emails[strtolower($app_email)] = 1;
        }
    }

    if ($first_app_email)
        $perf->best_email = $first_app_email;
    else 
        $perf->best_email = $perf_email;

    if (count($emails) > 1) {
        $msg = "<div>\n";
        $msg .= sprintf ("<div>performer %d has multiple emails</div>\n",
            $perf->number);
        if ($perf_email) {
            $msg .= sprintf ("<div>from performer db: %s</div>\n",
                h($perf_email));
        } else {
            $msg .= sprintf("<div>not set in performer db</div>\n");
        }
        foreach ($perf->apps as $app) {
            $msg .= sprintf ("<div>%s in %s</div>\n",
                h($app->curvals['email']),
                h($app->curvals['event_title']));
        }

        $msg .= sprintf ("<div>used: '%s'</div>\n", h($perf->best_email));
        $msg .= "</div>\n";
        global $info;
        $info[] = $msg;
    }

    return ($perf->best_email);
}

function we_need_to_notify ($name_id) {
    global $notify, $notify_by_name_id, $view_year;
    global $notify_by_notify_id, $notify_by_email;
    
    if (isset ($notify_by_name_id[$name_id]))
        return (0);
    
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
    query("insert into notify(notify_id, fest_year, name_id, email)"
        ." values(?, ?, ?, ?)",
        array($elt->notify_id, $view_year, $elt->name_id, $elt->email));
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
                } else if (we_need_to_notify($leader_id) < 0) {
                    $msg .= sprintf ("<div>can't find email for leader %d of"
                        ." %s</div>\n", $leader_id, h($group_name));

                    $msg .= "<div>probably, the leader of this group"
                        ." did not themselves make an application this year"
                        ."</div>\n";
                }
            } else {
                if (we_need_to_notify($app->neffa_id) < 0) {
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

    if (($perf = @$performers[$elt->name_id]) == NULL) {
        $body .= "<div>can't find performer db entry for this person</div>\n";
        pfinish();
    }
    
    if (($pcode = @$name_id_to_pcode[$elt->name_id]) == NULL) {
        $body .= "<div>can't find pcode for this person</div>\n";
        pfinish();
    }

    $confirm2_link = sprintf("https://cgi.neffa.org/performer/confirm2.pl?P=%s",
        rawurlencode($pcode));
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


    $body .= "</div>\n"; /* admin_box */

    $vals = [];

    $vals['first_name'] = preg_replace ('/^[^,]*,/', "", $perf->name);
    $vals['pcode_link'] = mklink($confirm2_link, $confirm2_link);

    $body .= "<div class='notify_email'>\n";
    $body .= populate_template("notify.html", $vals);
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
                $txt .= sprintf (" G:%s ", h($app->curvals['group_name']));

            if (@$app->curvals['event_title'])
                $txt .= sprintf (" T:%s", h($app->curvals['event_title']));

            if (@$app->curvals['name'])
                $txt .= sprintf (" N: %s", h($app->curvals['name']));

            $item .= mklink ($txt, $t);

            $item .= "</div>\n";

            if ($have_common_reason == 0) {
                $reason = trim (@$app->curvals['C_rejection_reason']);
                $item .= sprintf ("<div>%s</div>\n", $reason);
            }
        }

        if ($have_common_reason) {
            $item .= sprintf ("<div>%s</div>\n", $last_reason);
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

if (count($errs) > 0) {
    $body .= "<h1 style='color:red'>see end of page for errors</h1>\n";
}

$body .= "<div>\n";
$t = "notify.php?show_rejected=1";
$body .= mklink ("show rejected", $t);
$body .= "</div>\n";

$rows = array();
foreach ($notify as $elt) {
    $perf = @$performers[$elt->name_id];
    $cols = array();
    $t = sprintf("notify.php?notify_id=%d", $elt->notify_id);
    $cols[] = mklink($elt->notify_id, $t);
    $cols[] = h($elt->name_id);
    $cols[] = h(@$perf->name);
    $cols[] = h($elt->email);

    $pcode = neffa_id_to_pcode($elt->name_id);
    $t = sprintf("https://cgi.neffa.org/performer/confirm2.pl?P=%s",
        rawurlencode($pcode));
    $cols[] = mklink("magic", $t);


    $rows[] = $cols;
    
}
            
$body .= sprintf("<div>%d rows</div>\n", count($notify));
$body .= mktable(array(
    "notify_id", "name_id", "name", "email", "magic"),
    $rows);

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

