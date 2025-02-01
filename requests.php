<?php

require_once ("app.php");

pstart();

$arg_show_open = intval(@$_REQUEST['show_open']);
$arg_show_all = intval(@$_REQUEST['show_all']);

if ($arg_show_open == 1) {
    putsess ("requests_filter", "open");
    redirect ("requests.php");
}

if ($arg_show_all == 1) {
    putsess ("requests_filter", "");
    redirect ("requests.php");
}

$requests_filter = getsess ("requests_filter");

$body .= "<div>\n";
$t = "requests.php?show_all=1";
if ($requests_filter == "")
    $t = "";
$body .= mklink ("show all", $t);

$body .= " | ";

$t = "requests.php?show_open=1";
if ($requests_filter == "open")
    $t = "";
$body .= mklink ("show open", $t);

$body .= "</div>\n";

$apps = get_applications();
$apps_by_app_id = array();
foreach ($apps as $app) {
    $apps_by_app_id[$app->app_id] = $app;
}

$q = query ("select request_id, app_id, ts, val, dismissed"
    ." from requests"
    ." where fest_year = ?"
    ."   and test_flag = ?"
    ." order by request_id",
    array($submit_year, $submit_test_flag));
$requests = array();
while (($r = fetch ($q)) != NULL) {
    /* 
     * I can't easily find how these no-op records are getting in,
     * so just don't display them for now...
     */
    if ($r->val == '{"P_notes":""}')
        continue;

    $req = (object)NULL;
    $req->request_id = intval($r->request_id);
    $req->app_id = intval($r->app_id);
    $req->ts = trim($r->ts);
    $req->val = trim($r->val);
    $req->dismissed = intval($r->dismissed);

    /* may be ok to ignore request if bad app_id */
    $req->app = @$apps_by_app_id[$req->app_id];

    $requests[] = $req;
}

$groups = array();

foreach ($requests as $req) {
    if ($requests_filter == "open" && $req->dismissed)
        continue;

    $cols = [];
    $t = sprintf ("index.php?app_id=%d", $req->app_id);

    if ($req->app) {
        $txt = $req->app->evid;
    } else {
        $txt = sprintf ("%d", $req->app_id);
    }
    $cols[] = mklink ($txt, $t);
    $cols[] = db_time_to_eastern($req->ts);
    $cols[] = $req->dismissed;

    if ($req->app) {
        $cols[] = h($req->app->curvals['name']);
        if (($title = $req->app->curvals['event_title']) == "")
            $title = $req->app->curvals['group_name'];
        $cols[] = h($title);

        $full_notes = trim(@$req->app->curvals['C_notes']);
        $notes = preg_replace("/\n.*/", "", $full_notes);
        if (strcmp ($full_notes, $notes) != 0) {
            $notes .= "...";
        }
        $cols[] = h($notes);
    } else {
        $cols[] = "";
        $cols[] = "";
        $cols[] = "";
    }
    
    $cols[] = h(substr($req->val, 0, 50)) . "...";

    if (($prefix = @$req->app->evid[0]) == "")
        $prefix = "X";
    
    if (! isset ($groups[$prefix])) {
        $groups[$prefix] = array ();
    }
    $groups[$prefix][] = $cols;
}

$desired_order = array ("T", "P", "R", "X", "M", "F", "J");

foreach ($desired_order as $prefix) {
    if (isset ($groups[$prefix])) {
        $body .= mktable(array("evid", "timestamp", "dismissed", 
                "name", "event", "C_notes", "contents"), 
            $groups[$prefix]);

        $groups[$prefix] = array ();
    }
}

foreach ($groups as $group) {
    $body .= mktable(array("evid", "timestamp", "dismissed", 
            "name", "event", "C_notes", "contents"), 
        $group);
}



pfinish();
