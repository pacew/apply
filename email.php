<?php

require_once("app.php");

pstart ();

$arg_notify_ids = $_REQUEST['notify_ids'];
$arg_send_email = intval (@$_REQUEST['send_email']);

$erecs = array ();

foreach ($arg_notify_ids as $notify_id) {
    $q = query ("select name_id, email"
        ." from notify"
        ." where notify_id = ?",
        $notify_id);
    if (($r = fetch($q)) == NULL) {
        $body .= sprintf ("<h2>notify_id %d not found</h2>\n", $notify_id);
        continue;
    }
    $erec = (object)NULL;
    $erec->notify_id = $notify_id;
    $erec->name_id = intval($r->name_id);
    $erec->email = trim($r->email);
    $erecs[] = $erec;
}

$development_emails = array();
$development_emails['pace.willisson+ntest@gmail.com'] = 1;
$development_emails['lynnoel@lynnoel.com'] = 1;

read_notify_info();

if ($arg_send_email) {
    foreach ($erecs as $erec) {
        if (($perf = @$performers[$erec->name_id]) == NULL
            || ($pcode = neffa_id_to_pcode($perf->number)) == "") {
            $body .= sprintf ("can't find performer info or pcode for %d %s",
                $erec->name_id, h($erec->email));
            pfinish();
        }

        $to_email = $erec->email;

        if ($cfg['conf_key'] != "production")
            $to_email = "pace.willisson+ntest@gmail.com";

        $em = prepare_notify_email($to_email, $perf, $pcode);

        if (0) {
            $body .= "<hr/>\n";
            $body .= "<pre>\n";
            $body .= sprintf ("To: %s\n", h($em->to_email));
            $body .= sprintf ("Subject: %s\n", h($em->subject));
            $body .= "\n";
            $body .= h($em->html);
            $body .= "</pre>\n";
        }

        $args = (object)NULL;
        $args->to_email = $to_email;
        $args->subject = $em->subject;
        $args->body_html = $em->html;
        $args->body_text = $em->plain;

        $really_send_email = 0;
        if ($cfg['conf_key'] == "production")
            $really_send_email = 1;

        if ($really_send_email) {
            if (! isset ($development_emails[$args->to_email])) {
                $body .= sprintf ("<div>not allowed to send to %s</div>\n",
                    h($args->to_email));
                pfinish();
            }

            send_email($args);
            $body .= sprintf ("<div>%s: success</div>\n", h($to_email));
        } else {
            $body .= sprintf ("<div>%s: skipped due to test mode</div>\n",
                h($to_email));
        }
 
        $event = "acceptance_notification";
        if (strcmp ($to_email, $erec->email) != 0) {
            $event .= sprintf (" really sent to %s", $to_email);
        }
    
        $interaction_id = get_seq();
        query ("insert into interactions ("
            ." interaction_id, fest_year, test_flag, ts, name_id, event"
            ." ) values (?, ?, ?, current_timestamp, ?, ?)",
            array ($interaction_id, 
                $submit_year, $submit_test_flag,
                $erec->name_id, $event));
        do_commits();
    }
    $body .= sprintf ("<div>%s</div>\n",
        mklink ("back to notify page", "notify.php"));

    pfinish ();
}

$display_confirm = array();
$display_confirm[2] = "yes";
$display_confirm[3] = "declined";

$display_record = array();
$display_record[2] = "yes";
$display_record[3] = "no";

$rows = array();
foreach ($erecs as $erec) {
    $q = query ("select ts, event"
        ." from interactions"
        ." where name_id = ?"
        ."   and fest_year = ?"
        ."   and test_flag = ?"
        ." order by interaction_id",
        array($erec->name_id, $submit_year, $submit_test_flag));
    $prior = "";
    while (($r = fetch($q)) != NULL) {
        $prior .= sprintf ("<div>%s %s</div>\n",
            db_time_to_eastern($r->ts), h($r->event));
    }

    $confirm = "";
    $record = "";
    if (($conf = @$confirmations[$erec->name_id]) != NULL) {
        if ($conf->confirm == 2)
            $confirm = "yes";
        else if ($conf->confirm == 3)
            $confirm = "declined";
        else
            $confirm = intval($conf->confirm);

        if ($conf->record == 2)
            $record = "yes";
        else if ($conf->record == 3)
            $record = "no";
        else
            $record = intval($conf->record);
    }


    $cols = array();
    $t = sprintf ("notify.php?notify_id=%d", $erec->notify_id);
    $cols[] = mklink($erec->notify_id, $t);
    $cols[] = $erec->name_id;
    $cols[] = h($erec->email);
    $cols[] = $confirm;
    $cols[] = $record;
    $cols[] = $prior;
    $rows[] = $cols;
}

$body .= sprintf ("<div>%s</div>\n", 
    mklink ("back to notification page", "notify.php"));


$body .= "<h1>emails for this batch</h1>\n";

$body .= mktable(array ("notify_id", "name", "email", 
        "confirm", "record", "prior emails"), 
    $rows);

$body .= "<form action='email.php' method='post'>\n";
$body .= "<input type='hidden' name='send_email' value='1' />\n";

foreach ($arg_notify_ids as $notify_id) {
    $body .= sprintf ("<input type='hidden'"
        ." name='notify_ids[]' value='%d' />\n",
        $notify_id);
}
$body .= "<input type='submit' value='send email to this batch' />\n";
$body .= "(this may take a while ... don't be impatient with reload)";
$body .= "</form>\n";
$body .= sprintf ("<div style='margin-top:3em'>%s</div>\n", 
    mklink ("back to notification page", "notify.php"));

pfinish();
