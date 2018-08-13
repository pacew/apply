<?php

$anon_ok = 1;
require_once ($_SERVER['APP_ROOT'] . "/app.php");

$arg_access_code = trim (@$_REQUEST['a']);
$arg_email_suppressed = trim (@$_REQUEST['email_suppressed']);

pstart ();

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

$questions = get_questions ();

if (getsess ("admin")) {
    $t = sprintf ("https://%s/index.php?app_id=%d", $_SERVER['HTTP_HOST'],
                  $application->curvals['app_id']);
    $body .= sprintf ("<p class='debug_box'>[%s]</p>\n", 
                      mklink ("debug link", $t));
}


$body .= "<p>Thank you for applying.  You can review your responses"
      ." below.  After NEFFA has completed the initial processing"
      ." of your application, you will be able to use this page to keep your"
      ." contact information up to date.  You can bookmark this"
      ." page or save this link:</p>\n";

$target = sprintf ("https://%s/thanks.php?a=%s", 
                   $_SERVER['HTTP_HOST'],
                   rawurlencode ($application->access_code));
$body .= sprintf ("<p>%s</p>\n", mklink ($target, $target));

if ($arg_email_suppressed) {
    $body .= "<p style='color:red'>"
          ." Normally, we'd send you email with a copy of this link"
          ." but this email address has already been used a number"
          ." of times.  We are not allowed to send an unlimiited"
          ." number of emails to the same address, so you will"
          ." not receive a message for this particular application."
          ." So, please be sure to save the access link given above."
          ."</p>\n";
}

$body .= "<p>If you want to submit an application for another event,"
      ." please follow this link:</p>\n";
$target = sprintf ("https://%s", $_SERVER['HTTP_HOST']);
$body .= sprintf ("<p>%s</p>\n", mklink ($target, $target));


$body .= "<p>Here are the responses you provided:</p>";


$rows = array ();
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
                if ($elt != "") {
                    $strs[] = $elt;
                }
            } else {
                $strs[] = "TYPE?";
            }
        }
        if (count ($strs) > 0) {
            $val = sprintf ("[%s]", implode (",", $strs));
        } else {
            $val = "";
        }
    } else {
        $val = "TYPE?";
    }

    if ($val == "")
        continue;

    $cols = array ();
    $cols[] = h($q_text);

    $cols[] = h($val);

    $rows[] = $cols;
}


$body .= mktable (array ("Question", "Answer"), $rows);

pfinish ();

