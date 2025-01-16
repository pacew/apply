<?php

require_once("app.php");

pstart();

$arg_email = rawurldecode(@$_REQUEST['email']);
$arg_first_name = rawurldecode(@$_REQUEST['first_name']);
$arg_text = rawurldecode(@$_REQUEST['text']);
$arg_send_email = intval(@$_REQUEST['send_email']);

$email = trim(strtolower($arg_email));

$q = query ("select ts, username, reason"
    ." from rejected"
    ." where email = ?"
    ."   and fest_year = ?"
    ."   and test_flag = ?"
    ." order by rejected_id desc"
    ." limit 1",
    array($email, $submit_year, $submit_test_flag));

$already_done = 0;

if (($r = fetch ($q)) != NULL) {
    $already_done = 1;

    $body .= "<h1>rejection email sent</h1>\n";
    $body .= "<table class='twocol'>\n";
    $body .= sprintf ("<tr><th>when</th><td>%s</td></tr>\n",
        h(db_time_to_eastern($r->ts)));
    $body .= sprintf ("<tr><th>who</th><td>%s</td></tr>\n",
        h($r->username));
    $body .= sprintf ("<tr><th>message</th><td>%s</td></tr>\n",
        h($r->reason));
    $body .= "</table>\n";
}

$subject = "Your NEFFA application(s)";

$vals['email'] = $email;
$vals['first_name'] = $arg_first_name;
$vals['rejection_reason'] = $arg_text;
$body_html = populate_template("rejection.html", $vals);

if ($arg_send_email == 1) {
    $args = (object)NULL;
    $args->to_email = $email;
    $args->subject = $subject;
    $args->body_html = $body_html;
    $args->body_text = strip_tags($body_html);

    send_email($args);

    $rejected_id = get_seq();
    query ("insert into rejected (rejected_id, email, fest_year, test_flag,"
        ." ts, username, reason"
        .") values (?,?,?,?,current_timestamp,?,?)",
        array($rejected_id, $email, $submit_year, $submit_test_flag,
            $username, $arg_text));

    $t = sprintf ("rejection.php"
        ."?email=%s"
        ."&first_name=%s",
        rawurlencode($email),
        rawurlencode($arg_first_name));

    redirect ($t);

    $body .= mklink ($t, $t);
    pfinish();
}

if ($already_done == 0) {
    $body .= "<form action='rejection.php' method='post'>\n";
    $body .= "<input type='hidden' name='send_email' value='1' />\n";
    $body .= sprintf ("<input type='hidden' name='email' value='%s' />\n",
        h($email));
    $body .= sprintf ("<input type='hidden' name='first_name' value='%s' />\n",
        h($arg_first_name));
    $body .= sprintf ("<input type='hidden'"
        ." name='text' value='%s' />\n", rawurlencode($arg_text));
    $body .= "<input type='submit' value='send this email' />\n";
    $body .= "</form>\n";
}

$body .= "<div class='notify_email'>\n";

$body .= sprintf ("<p>To: %s<br/>\n", h($email));
$body .= sprintf ("Subject: %s</p>\n", h($subject));

// https://docs.google.com/document/d/1u1PRH3-UYUEPgtsNS6IrLPzPlRfGLcv-Koc4puSzj84
$body .= $body_html;
$body .= "</div>\n";



pfinish();
