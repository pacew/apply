<?php

require_once("app.php");

$anon_ok = 1;

$arg_pcode = trim(@$_REQUEST['pcode']);
$arg_confirm = intval(@$_REQUEST['confirm']);
$arg_record = intval(@$_REQUEST['record']);

pstart ();

function need_help() {
    global $body;

    $body .= "<h1>Internal error</h1>\n";
    $body .= "<p>There is a technical problem with your application."
        ." Please send email to ";
    $email = "mailto:program@neffa.org";
    $body .= mklink ($email, $email);
    $body .= " to request assistance.";
    $body .="<p>\n";
    pfinish();
}

$q = query("select id"
    ." from pcodes"
    ." where pcode = ?",
    $arg_pcode);
if (($r = fetch ($q)) == NULL)
    need_help();

$neffa_id = intval(@$r->id);

$old_confirm = 0;
$old_record = 0;

$q = query ("select confirm, record"
    ." from confirmations"
    ." where name_id = ?"
    ."   and fest_year = ?"
    ."   and test_flag = ?",
    array($neffa_id, $submit_year, $submit_test_flag));
if (($r = fetch ($q)) != NULL) {
    $old_confirm = intval($r->confirm);
    $old_record = intval($r->record);
} else {
    query ("insert into confirmations ("
        ." name_id, fest_year, test_flag, confirm, record"
        .") values (?, ?, ?, ?, ?)",
        array ($neffa_id, $submit_year, $submit_test_flag,
            0, 0));
}

if (($new_confirm = $arg_confirm) != 0) {
    query ("update confirmations set confirm = ?, updated = current_timestamp"
        ." where name_id = ? and fest_year = ? and test_flag = ?",
        array($new_confirm, $neffa_id, $submit_year, $submit_test_flag));
} else {
    $new_confirm = $old_confirm;
}

if (($new_record = $arg_record) != 0) {
    query ("update confirmations set record = ?, updated = current_timestamp"
        ." where name_id = ? and fest_year = ? and test_flag = ?",
        array($new_record, $neffa_id, $submit_year, $submit_test_flag));
} else {
    $new_record = $old_record;
}

$event = sprintf ("performer_response confirm=%d record=%d",
    $new_confirm, $new_record);
$interaction_id = get_seq();
query ("insert into interactions ("
    ." interaction_id, fest_year, test_flag, ts, name_id, event"
    ." ) values (?, ?, ?, current_timestamp, ?, ?)",
    array ($interaction_id, 
        $submit_year, $submit_test_flag,
        $neffa_id, $event));
do_commits();


$body .= sprintf ("<div>pcode %s</div>\n", h($arg_pcode));
$body .= sprintf ("<div>neffa_id %d</div>\n", $neffa_id);

$apps = get_applications();
foreach ($apps as $app) {
    if ($app->neffa_id == $neffa_id) {

        $t = sprintf ("index.php?app_id=%d", $app->app_id);

        $body .= "<div>";
        $body .= mklink(h($app->curvals['name']), $t);
        $body .= " - ";
        $body .= mklink($app->evid, $t);
        $body .= " - ";
        $body .= mklink($app->app_id, $t);
        $body .= "</div>\n";
    }
}


$rows = [];
$rows[] = array("confirm", $old_confirm, $new_confirm);
$rows[] = array("record", $old_record, $new_record);

$body .= mktable(array ("field", "old", "new"), $rows);

$t = sprintf ("%s&confirm=%d&record=%d",
    make_confirm2_link($arg_pcode),
    $new_confirm,
    $new_record);

if ($cfg['conf_key'] == "production") {
    redirect ($t);
} else {
    $body .= sprintf ("<div>redirect will go to %s</div>",
        mklink ($t, $t));
}

pfinish ();
