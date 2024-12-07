<?php

require_once("app.php");

$anon_ok = 1;

$arg_pcode = trim(@$_REQUEST['pcode']);
$arg_eventid = trim (@$_REQUEST['eventid']);
$arg_save = intval (@$_REQUEST['save']);
$arg_app_id = intval (@$_REQUEST['app_id']);
$arg_event_title = trim (@$_REQUEST['event_title']);

pstart ();

$body .= "<p>REMEMBER AUTHENTICATION</p>";

$fields = array("event_title");

if ($arg_save) {
    $app = get_application($arg_app_id);

    $req = array();
    foreach ($fields as $field) {
        $oldval = $app->curvals[$field];
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

$body .= "<div class='admin_box'>";
$body .= "<p>admin box</p>\n";
$t = sprintf ("/index.php?app_id=%d", $app->app_id);
$body .= sprintf ("<p>link to app %s</p>\n", mklink ($app->evid, $t));

$magic_link = sprintf("https://cgi.neffa.org/performer/confirm2.pl"
    ."?P=%s", rawurlencode($arg_pcode));

$body .= sprintf ("<p>on save, will redirect to %s</p>\n", 
    mklink ($magic_link, $magic_link));

$t = sprintf("https://k.pacew.org:26534/performer.php"
    ."?pcode=%s"
    ."&eventid=%s",
    rawurlencode($arg_pcode),
    rawurlencode($arg_eventid));
$body .= sprintf ("<p>bounce to dev site %s</p>\n", mklink($t, $t));


$body .= "</div>\n";

$body .= "<p>Here is the current information for your event.  You"
    ." can use this form to request changes.</p>\n";

$body .= "<form action='performer.php'>\n";
$body .= "<input type='hidden' name='save' value='1' />\n";
$body .= sprintf("<input type='hidden' name='pcode' value='%s' />\n", 
    h($arg_pcode));
$body .= sprintf("<input type='hidden' name='eventid' value='%s' />\n",
    h($arg_eventid));
$body .= sprintf("<input type='hidden' name='app_id' value='%d' />\n",
    $app->app_id);
$body .= "<table class='twocol'>\n";
$body .= "<tr><th>Event title</th><td>";
$body .= sprintf ("<input type='text' size='50'"
    ." name='event_title' value='%s' />\n",
    h($app->curvals['event_title']));
$body .= "</td></tr>\n";
    
$body .= "<tr><th></th><td><input type='submit' value='Submit' />\n";
$t = sprintf ("performer.php?pcode=%s&eventid=%s",
    rawurlencode($arg_pcode), rawurlencode($arg_eventid));
$body .= mklink ("cancel", $t);
$body .= "</td></tr>\n";
$body .= "</table>\n";
$body .= "</form>\n";



pfinish ();
