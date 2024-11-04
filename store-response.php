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

$q = query ("select notify_id, confirm, record"
    ." from notify"
    ." where fest_year = ?"
    ."   and test_flag = ?"
    ."   and name_id = ?",
    array($view_year, $view_test_flag, $neffa_id));
if (($r = fetch ($q)) != NULL) {
    $notify_id = intval($r->notify_id);
    $old_confirm = intval($r->confirm);
    $old_record = intval($r->record);
} else {
    $notify_id = get_seq();
    query ("insert into notify (notify_id, fest_year, test_flag, name_id)"
        ." values (?, ?, ?, ?)",
        array ($notify_id, $view_year, $view_test_flag, $neffa_id));
}

if (($new_confirm = $arg_confirm) != 0) {
    query ("update notify set confirm = ?"
        ." where notify_id = ?",
        array ($new_confirm, $notify_id));
} else {
    $new_confirm = $old_confirm;
}

if (($new_record = $arg_record) != 0) {
    query ("update notify set record = ?"
        ." where notify_id = ?",
        array ($new_record, $notify_id));
} else {
    $new_record = $old_record;
}


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

$t = sprintf ("https://cgi.neffa.org/performer/confirm2.pl"
    ."?P=%s"
    ."&confirm=%d"
    ."&record=%d",
    rawurlencode($arg_pcode),
    $new_confirm,
    $new_record);

$body .= sprintf ("<div>redirect will go to %s</div>",
    mklink ($t, $t));

pfinish ();
