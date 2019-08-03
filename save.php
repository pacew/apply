<?php

require_once("app.php");

$anon_ok = 1;

$app_id = intval (@$_REQUEST['app_id']);

pstart ();

if ($app_id == 0) {
    $need_patch = 0;
    $app_id = get_seq ();
} else {
    $need_patch = 1;
}

$want_email = 0;
if (@$_REQUEST['submit'] == "Submit") {
    $want_email = 1;
}

$questions = get_questions ();

$newvals = array ();
$newvals['app_id'] = $app_id;
foreach ($questions as $question) {
    $id = $question['id'];
    $input_id = sprintf ("i_%s", $id);
    
    $raw_val = @$_REQUEST[$input_id];
    if (is_array ($raw_val)) {
        $clean = array ();
        if (associative_array ($raw_val)) {
            foreach ($raw_val as $key => $val) {
                $clean[$key] = preg_replace ("/[\|=]/", "~", $val);
            }
        } else {
            foreach ($raw_val as $val) {
                $clean[] = preg_replace ("/[\|]/", "~", $val);
            }
        }
        $newvals[$id] = $clean;
    } else {
        $newvals[$id] = $raw_val;
    }

    foreach (array ("availability", "room_sound") as $arr_name) {
        if (! is_array (@$newvals[$arr_name]))
            $newvals[$arr_name] = array ();
    }
}

$needs_attention = 0;
foreach ($questions as $question) {
    $question_id = $question['id'];

    if (! active_question ($question_id, $newvals))
        continue;

    $class = @$question['class'];

    if ($class == "lookup_individual" || $class == "lookup_group") {
        $val = trim ($newvals[$question_id]);
        if ($val != "" && name_to_id ($val) == 0) {
            $needs_attention = 1;
            break;
        }
    }
}

function make_access_code () {
    return (generate_urandom_string (8));
}


if ($need_patch == 0) {
    $access_code = make_access_code ();
    
    query ("insert into json (app_id, ts, username, val, access_code,"
           ."   fest_year, test_flag)"
           ." values (?,current_timestamp,?,?,?,?,?)",
           array ($app_id, 
                  $username, 
                  json_encode ($newvals),
                  $access_code,
                  $submit_year, $submit_test_flag));
} else {
    if (($application = get_application ($app_id)) == NULL)
        fatal ("can't find base application to update");

    $access_code = $application->access_code;

    $diff = mikemccabe\JsonPatch\JsonPatch::diff($application->curvals, 
                                                 $newvals);

    query ("insert into json (app_id, ts, username, val, fest_year, test_flag)"
           ." values (?,current_timestamp,?,?,?,?)",
           array ($app_id, $username, json_encode ($diff),
                  $application->fest_year,
                  $application->test_flag));

}

query (
    "update json set attention = ? where app_id = ?",
    array ($needs_attention, $app_id));


do_commits ();

if (($application = get_application ($app_id)) == NULL)
    fatal ("can't re-read application");



$to_email = trim (strtolower ($application->curvals['email']));
if (! preg_match ("/\S+@\S+\.\S+/", $to_email)) {
    $body .= "<p>BAD EMAIL</p>\n";
    $want_email = 0;
}

if (preg_match ("/@example.com/", $to_email)) {
    $body .= "<p>[email suppressed because @example.com]</p>\n";
    $want_email = 0;
}

$q = query ("select count(*) as count"
            ." from email_history"
            ." where email = ?",
            $to_email);
$r = fetch ($q);
$email_count = intval ($r->count);
$email_limit = 5;
if ($email_count >= $email_limit) {
    $t = sprintf ("thanks.php?a=%s&email_suppressed=1", 
                  rawurlencode ($application->access_code));
    redirect ($t);
}

$subject = sprintf ("NEFFA %d Performer application confirmation",
                    $submit_year);

$heading = sprintf ("Thanks for submitting your NEFFA %d Performer application",
                    $submit_year);

$msg1 = "You can use the following link to review the data that you"
      ." submitted.  You can't change any of the answers until"
      ." we notify you that we've completed the initial processing"
      ." of your application.  After that, you can use this"
      ." link to keep your contact information up to date.";

$target = sprintf ("https://%s/thanks.php?a=%s", 
                   $_SERVER['HTTP_HOST'],
                   rawurlencode ($application->access_code));

$info_email = "applications@neffa.org";
$msg2_html = sprintf ("If you have any questions, please write "
                      ."<a href='mailto:%s'>%s</a>",
                      fix_target ($info_email), h($info_email));
$msg2_text = sprintf ("If you have any questions, please write %s",
                      $info_email);

$msg3 = "If you did not recently submit a NEFFA application,"
      ." someone else must have entered your email address"
      ." into our form.  You can ignore this message.";

$html = "<h1>" . $heading . "</h1>\n"
      ."<p>" . $msg1 . "</p>\n"
      .mklink ($target, $target)
      ."<p>" . $msg2_html . "</p>\n"
      ."<p>" . $msg3 . "</p>\n";

$plain = $heading . "\n\n"
       .$msg1 . "\n\n"
       .$target . "\n\n"
       .$msg2_text . "\n\n"
       .$msg3 . "\n";

if (! $want_email) {
    if ($email_count >= $email_limit) 
    $body .= "<p>[admin: saving without sending email ... here's what"
          ." would have been sent]</p>\n";

    $body .= "<div class='html_email'>\n";
    $body .= sprintf ("<pre>To: %s\n"
                      ."Subject: %s</pre>\n", $to_email, $subject);
    $body .= $html;
    $body .= "</div>\n";

    $body .= "<div class='plain_email'>\n";
    $body .= "<pre>\n";
    $body .= sprintf ("To: %s\n", $to_email);
    $body .= sprintf ("Subject: %s\n\n", $subject);
    $body .= h($plain);
    $body .= "</pre>\n";
    $body .= "</div>\n";
    pfinish ();
}
    

// require_once ("libphp-phpmailer/autoload.php");

$path = sprintf ("%s/PHPMailer/src", dirname($cfg['src_dir']));
require_once($path . "/Exception.php");
require_once($path . "/PHPMailer.php");
require_once($path . "/SMTP.php");
use PHPMailer\PHPMailer\PHPMailer;

$q = query ("select val"
            ." from vars"
            ." where var = 'smtp_cred'");
if (($r = fetch ($q)) == NULL)
    fatal ("no smtp credentials");
$smtp_cred = $r->val;
$arr = preg_split ('/ /', $smtp_cred);
$smtp_user = $arr[0];
$smtp_password = $arr[1];
    
$mail = new PHPMailer;
$mail->isSMTP();
$mail->Host = 'email-smtp.us-east-1.amazonaws.com';
    $mail->Username = $smtp_user;
$mail->Password = $smtp_password;
$mail->SMTPAuth = true;
$mail->SMTPSecure = 'tls';
$mail->Port = 587;

$mail->setFrom('applications@neffa.org', 'NEFFA Applications');
$mail->addAddress($to_email);
$mail->Subject = $subject;

$mail->Body = $html;
$mail->isHTML(true);
$mail->AltBody = preg_replace("/\\n/", "\r\n", $plain);

query ("insert into email_history (email, sent) values (?, current_timestamp)",
       $to_email);
do_commits ();


if(!$mail->send()) {
    fatal ("Application submitted, but error sending confirmation email: "
           . $mail->ErrorInfo);
}

$t = sprintf ("thanks.php?a=%s", rawurlencode ($application->access_code));

redirect ($t);
