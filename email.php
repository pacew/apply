<?php

require_once("app.php");

pstart ();

$arg_notify_ids = $_REQUEST['notify_ids'];
$arg_send_email = intval (@$_REQUEST['send_email']);

$erecs = array ();

foreach ($arg_notify_ids as $notify_id) {
    $q = query ("select name_id, confirm, record,"
        ."  email, sent_dttm, responded_dttm"
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
    $erec->confirm = $r->confirm;
    $erec->record = $r->record;
    $erec->email = trim($r->email);
    $erec->sent_dttm = trim($r->sent_dttm);
    $erec->responded_dttm = trim($r->responded_dttm);

    $erecs[] = $erec;
}

$development_emails = array();
$development_emails['pace.willisson+ntest@gmail.com'] = 1;

if ($arg_send_email) {
    read_notify_info();

    foreach ($erecs as $erec) {
        if (($perf = @$performers[$erec->name_id]) == NULL
            || ($pcode = neffa_id_to_pcode($perf->number)) == "") {
            $body .= sprintf ("can't find performer info or pcode for %d %s",
                $erec->name_id, h($erec->email));
            pfinish();
        }

        $to_email = $erec->email;
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
        if ($really_send_email) {
            if (! isset ($development_emails[$args->to_email])) {
                $body .= sprintf ("<div>not allowed to send to %s</div>\n",
                    h($args->to_email));
                pfinish();
            }

            send_email($args);
        }
 
        $event = "acceptance_notification";
        if (strcmp ($to_email, $erec->email) != 0) {
            $event .= sprintf (" really sent to %s", $to_email);
        }
    
        $interaction_id = get_seq();
        query ("insert into interactions ("
            ." interaction_id, ts, name_id, event"
            ." ) values (?, current_timestamp, ?, ?)",
            array ($interaction_id, $erec->name_id, $event));
        do_commits();
        
        $body .= sprintf ("<div>%s: success</div>\n", h($to_email));
    }
    $body .= sprintf ("<div>%s</div>\n",
        mklink ("back to notify page", "notify.php"));

    pfinish ();
}


$rows = array();
foreach ($erecs as $erec) {
    $cols = array();
    $t = sprintf ("notify.php?notify_id=%d", $erec->notify_id);
    $cols[] = mklink($erec->notify_id, $t);
    $cols[] = $erec->name_id;
    $cols[] = h($erec->confirm);
    $cols[] = h($erec->record);
    $cols[] = h($erec->email);
    $cols[] = db_time_to_eastern($erec->sent_dttm);
    $cols[] = db_time_to_eastern($erec->responded_dttm);
    $rows[] = $cols;
}

$body .= mktable(array ("notify_id", "name", "confirm", "record_ok", "email",
        "notified", "responded"), $rows);

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

pfinish();
