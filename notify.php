<?php

require_once("app.php");

$arg_notify_id = intval(@$_REQUEST['notify_id']);
$arg_reload = intval (@$_REQUEST['reload']);

pstart ();

if ($arg_reload == 1) {
    query ("delete from notify");
    redirect ("notify.php");
}

read_notify_info();

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

    $t = sprintf ("response.php?pcode=%s", rawurlencode($pcode));
    $body .= sprintf ("<p>cgi will send performer to: %s</p>\n",
        mklink($t, $t));

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

function we_need_to_notify ($kind, $webgrid_elt, $name_id) {
    global $notify, $notify_by_name_id, $view_year;
    global $notify_by_notify_id;
    
    if (isset ($notify_by_name_id[$name_id]))
        return;
    
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

    return (0);
}


function walk_grid() {
    global $webgrid, $group_to_group_leader;
    foreach ($webgrid as $webgrid_elt) {
        $success = [];
        $fails = [];
        foreach ($webgrid_elt->name_ids as $name_id) {
            $leader_id = @$group_to_group_leader[$name_id];
            if ($leader_id) {
                if (we_need_to_notify("leader", 
                        $webgrid_elt, $leader_id) < 0) {
                    $fails[] = $leader_id;
                } else {
                    $success[] = $leader_id;
                }
            } else {
                if (we_need_to_notify("individual", 
                        $webgrid_elt, $name_id) < 0) {
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
                    make_evid_link($webgrid_elt->evid));
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
                    make_evid_link($webgrid_elt->evid));
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

$body .= "<div class='admin_box'>\n";
$body .= mklink ("reload webgrid [for debugging]", "notify.php?reload=1");
$body .= "</div>\n";

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

