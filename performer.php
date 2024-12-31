<?php

require_once("app.php");

$anon_ok = 1;

$arg_pcode = trim(@$_REQUEST['pcode']);
$arg_eventid = trim (@$_REQUEST['eventid']);
$arg_save = intval (@$_REQUEST['save']);
$arg_app_id = intval (@$_REQUEST['app_id']);
$arg_event_title = trim (@$_REQUEST['event_title']);
$arg_P_notes = trim (@$_REQUEST['P_notes']);

pstart ();

$magic_link = sprintf("https://cgi.neffa.org/performer/confirm2.pl"
    ."?P=%s", rawurlencode($arg_pcode));

$fields = array("event_title");

if ($arg_save) {
    $app = get_application($arg_app_id);

    $req = array();
    foreach ($fields as $field) {
        $oldval = @$app->curvals[$field];
        $newval = @$_REQUEST[$field];

        if ($oldval != $newval) {
            $req[$field] = $newval;
        }
    }

    if (count($req) > 0) {
        $request_id = get_seq();
        query ("insert into requests (request_id,"
            ." app_id, fest_year, test_flag, ts, username, val"
            ." ) values (?, ?, ?, ?, current_timestamp, ?, ?)",
            array($request_id, $arg_app_id, $submit_year, $submit_test_flag,
                $username, json_encode($req)));
    }

    $oldval = @$app->curvals['P_notes'];
    $newval = @$_REQUEST['P_notes'];
    if ($oldval != $newval) {
        /* based on save.php */
        
        $newvals = $app->curvals;
        $newvals['P_notes'] = @$_REQUEST['P_notes'];

        $diff = mikemccabe\JsonPatch\JsonPatch::diff($app->curvals, 
            $newvals);

        if (count($diff) > 0) {
            query ("insert into json (app_id, ts, username, val, fest_year,"
                ."   test_flag)"
                ." values (?,current_timestamp,?,?,?,?)",
                array ($arg_app_id, "performer-response", json_encode ($diff),
                    $app->fest_year,
                    $app->test_flag));
        }
    }

    if ($cfg['conf_key'] == "production")
        redirect ($magic_link);

    $t = sprintf("index.php?app_id=%d", $arg_app_id);
    $body .= mklink($t, $t);
    pfinish();
}

$apps = get_applications();

read_notify_info();

if (($wg = @$webgrid_by_eventid[$arg_eventid]) == NULL) {
    $body .= sprintf("<p>eventid %s not found</p>\n", h($arg_eventid));
    pfinish();
}

function find_app($evids, $pcode) {
    global $apps_by_evid;
    foreach ($evids as $evid) {
        if (($app = @$apps_by_evid[$evid]) != NULL) {
            $app_pcode = neffa_id_to_pcode($app->neffa_id);
            if (strcmp ($app_pcode, $pcode) == 0)
                return ($app);
        }
    }

    return (NULL);
}

if (($app = find_app($wg->evids, $arg_pcode)) == NULL) {
    $body .= sprintf ("<p>error: can't find application for %s %s</p>\n",
        h($arg_pcode), h($arg_eventid));
    pfinish ();
}

$body .= "<table class='twocol'>\n";
$body .= "<tr><th>Applicant</th><td>\n";
$body .= sprintf ("%s<br/>%s<br/>%s",
    h($app->curvals['name']),
    h($app->curvals['email']),
    h($app->curvals['phone']));
$body .= "</td></tr>\n";

if (@$app->curvals['group_name']) {
    $body .= "<tr><th>Group name</th><td>\n";
    $body .= h($app->curvals['group_name']);
    $body .= "</td></tr>\n";
}

$body .= "</table>\n";

$body .= "<h1>Change Request Form</h1>\n";

$body .= "<p>The form below allows you to request changes to some details"
    ." about your event.</p>"
    ."<p>You can use the <strong>Performer notes</strong>"
    ." field to request a more complex change.</p>"
    ."<p>You request will be reviewed"
    ." by a member of the program committee, and they will you know by email"
    ." about the status of your request.</p>"
    ."<p>If you don't receive a response"
    ." after a few days, please email "
    ."<a href='mailto:program@neffa.org'>program@neffa.org</a>"
    ." and describe what you need.</p>\n";

$body .= "<form action='performer.php'>\n";
$body .= "<input type='hidden' name='save' value='1' />\n";
$body .= sprintf("<input type='hidden' name='pcode' value='%s' />\n", 
    h($arg_pcode));
$body .= sprintf("<input type='hidden' name='eventid' value='%s' />\n",
    h($arg_eventid));
$body .= sprintf("<input type='hidden' name='app_id' value='%d' />\n",
    $app->app_id);
$body .= "<table class='twocol'>\n";

if (category_uses_title(@$app->curvals['app_category'])) {
    $body .= "<tr><th>Event title</th><td>";
    $body .= sprintf ("<input type='text' size='50'"
        ." name='event_title' value='%s' />\n",
        h($app->curvals['event_title']));
    $body .= "</td></tr>\n";
}

$body .= "<tr><th>Performer notes</th><td>";
$body .= sprintf ("<textarea rows='10' cols='80' name='P_notes' />\n");
$body .= h(@$app->curvals['P_notes']);
$body .= "</textarea>\n";
$body .= "</td></tr>\n";
    
$body .= "<tr><th></th><td><input type='submit' value='Submit' />\n";
$t = sprintf ("performer.php?pcode=%s&eventid=%s",
    rawurlencode($arg_pcode), rawurlencode($arg_eventid));
$body .= mklink ("cancel", $t);
$body .= "</td></tr>\n";
$body .= "</table>\n";
$body .= "</form>\n";

$body .= "<div>note: you may not request a change just to be able"
    ." to attend another event</div>\n";

$body .= "<div class='admin_box'>";
$body .= "<p>admin box</p>\n";
$t = sprintf ("/index.php?app_id=%d", $app->app_id);
$body .= sprintf ("<p>link to app %s</p>\n", mklink ($app->evid, $t));

$body .= sprintf ("<p>on save, will redirect to %s</p>\n", 
    mklink ($magic_link, $magic_link));

$t = sprintf("https://k.pacew.org:26534/performer.php"
    ."?pcode=%s"
    ."&eventid=%s",
    rawurlencode($arg_pcode),
    rawurlencode($arg_eventid));
$body .= sprintf ("<p>bounce to dev site %s</p>\n", mklink($t, $t));


$body .= "</div>\n";



pfinish ();
