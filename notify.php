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

function we_need_to_notify ($kind, $webgrid_elt, $name_id) {
    global $notify, $notify_by_name_id, $view_year;
    global $notify_by_notify_id;
    
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
    $elt->fest_year = $view_year;
    query("insert into notify(notify_id, fest_year, name_id, email)"
        ." values(?, ?, ?, ?)",
        array($elt->notify_id, $view_year, $elt->name_id, $elt->email));
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

