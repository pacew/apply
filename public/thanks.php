<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

$arg_access_code = trim (@$_REQUEST['a']);
$arg_send_email = intval (@$_REQUEST['send_email']);
$arg_sent = intval (@$_REQUEST['sent']);

pstart ();

$send_email = 0;

if ($arg_send_email)
    $send_email = 1;

if ($arg_sent)
    $send_email = 0;

$title_html = "Thanks";

$q = query ("select app_id"
            ." from json"
            ." where access_code = ?",
            $arg_access_code);
if (($r = fetch ($q)) == NULL) {
    $body .= "missing access code";
    pfinish ();
}

$app_id = intval ($r->app_id);

if (($application = get_application ($app_id)) == NULL)
    fatal ("can't find base application to update");

$subject = "Thanks";

if (! $send_email) {
    $t = sprintf ("thanks.php?a=%s&send_email=1",
                  rawurlencode ($arg_access_code));
    $body .= sprintf ("<p>%s</p>\n", mklink ("send email", $t));
}

$body .= "<p>thanks</p>";

$html = "";
$plain = "";

$questions = get_questions ();

function add_simple ($txt) {
    global $html, $plain;

    $html .= "<p>";
    $html .= h($txt);
    $html .= "</p>\n";

    $plain .= trim ($txt);
    $plain .= "\n\n";
}

add_simple ("Thank you for applying.");

add_simple ("After your application is processed, you will be able"
            ." to update your contact information"
            ." using this access link:");

$t = sprintf ("http://example.neffa.org/update/%s", 
              rawurlencode ($arg_access_code));

$html .= sprintf ("<p>%s</p>\n", mklink ($t, $t));
$plain .= sprintf ("%s\n\n", $t);


$t = sprintf ("https://%s/index.php?app_id=%d", $_SERVER['HTTP_HOST'],
              $application->curvals['app_id']);
                      $html .= sprintf ("<p>[%s]</p>\n", mklink ("debug link", $t));
$plain .= sprintf ("debug link: %s\n\n", $t);

add_simple ("These are the responses you provided:");


$s = array ();
$html .= sprintf ("<table style='%s'>\n",
                  implode (";", $s));
foreach ($questions as $question) {
    $question_id = $question['id'];

    $q_text = $question['q'];
    $answer = $application->curvals[$question_id];
  
    if ($answer == null) {
        $val = "";
    } else if (is_string ($answer)) {
        $val = $answer;
    } else if (is_array ($answer)) {
        $strs = array ();
        foreach ($answer as $elt) {
            if (is_string ($elt)) {
                $strs[] = $elt;
            } else {
                $strs[] = "TYPE?";
            }
        }
        $val = sprintf ("[%s]", implode (",", $strs));
    } else {
        $val = "TYPE?";
    }


    if ($val == "")
        continue;

    $html .= "<tr>\n";

    $s = array ();
    $s[] = "width: 20em";
    $s[] = "border: 1px solid #eee";
    $html .= sprintf ("<td style='%s'>%s</td>\n", 
                      implode (";", $s),
                      h($q_text));

    $s = array ();
    $s[] = "width: 20em";
    $s[] = "border: 1px solid #eee";
    $html .= sprintf ("<td style='%s'>%s</td>\n", 
                      implode (";", $s),
                      h($val));
    $html .= "</tr>\n";

    $plain .= sprintf ("%s\n", trim ($q_text));
    $plain .= sprintf ("%s\n", trim ($val));
    $plain .= "\n";
}
$html .= "</table>\n";
$plain .= "\n\n";

$body .= "<div class='html_email'>\n";
$body .= $html;
$body .= "</div>\n";

$body .= "<div class='plain_email'>\n";
$body .= sprintf ("<pre>%s</pre>\n", h($plain));
$body .= "</div>\n";


require_once ("libphp-phpmailer/autoload.php");

$q = query ("select val"
            ." from vars"
            ." where var = 'smtp_cred'");
if (($r = fetch ($q)) == NULL)
    fatal ("no smtp credentials");
$smtp_cred = $r->val;
$arr = preg_split ('/ /', $smtp_cred);
$smtp_user = $arr[0];
$smtp_password = $arr[1];

if ($send_email) {
    $mail = new PHPMailer;
    $mail->isSMTP();
    $mail->Host = 'email-smtp.us-east-1.amazonaws.com';
    $mail->Username = $smtp_user;
    $mail->Password = $smtp_password;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('pace@pacew.org', 'Pace Willisson');
    $mail->addAddress('pace.willisson@gmail.com', 'Pace Willisson');
    $mail->Subject = $subject;
    
    $mail->Body = $html;
    $mail->isHTML(true);

    $mail->AltBody = preg_replace("/\\n/", "\r\n", $plain);

    if(!$mail->send())
        fatal ("Error sending email: " . $mail->ErrorInfo);

    $t = sprintf ("thanks.php?a=%s&sent=1", rawurlencode ($arg_access_code));
    redirect ($t);
}

pfinish ();

