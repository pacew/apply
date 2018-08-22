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

$sound = array ();
$sound['stage_with'] = "Stage with sound system";
$sound['stage_without'] = "Stage without sound system";
$sound['double_with'] = "Double classroom with sound system";
$sound['double_without'] = "Double classroom without sound system";
$sound['single_mic'] = "Single classroom with self service mic";
$sound['single_without'] = "Single classroom without sound system";

function show_if_test ($question_id, $condition) {
    global $questions_by_id, $application;
    
    $target_id = $condition[0];
    $target_question = $questions_by_id[$target_id];
    
    $val = $application->curvals[$target_id];
    if (@$target_question['choices']) {
        return (array_search ($val, $condition) !== FALSE);
    } else {
        return ($val != "");
    }
}

$rows = array ();
foreach ($questions as $question) {
    $question_id = $question['id'];

    $q_text = $question['q'];
    $answer = $application->curvals[$question_id];
  
    $want_field = true;
    if (($show_if = @$question['show_if']) != NULL) {
        if (is_array ($show_if[0])) {
            foreach ($show_if as $condition) {
                if (! show_if_test ($question_id, $condition))
                    $want_field = false;
            }
        } else {
            if (! show_if_test ($question_id, $show_if))
                $want_field = false;
        }
    }
    if (! $want_field)
        continue;

    if ($question_id == "availability") {
        $dnames = array ("", "Fri", "Sat", "Sun");

        $days = array ();
        $saw_preferred = 0;

        for ($day = 1; $day <= 3; $day++) {
            $hours = array ();
            for ($hour = 10; $hour <= 22; $hour++) {
                $key = $day * 100000 + $hour * 100;
                if (@$answer[$key]) {
                    if ($hour < 12) {
                        $htext = $hour;
                    } else if ($hour == 12) {
                        $htext = "noon";
                    } else {
                        $htext = sprintf ("%dp", $hour - 12);
                    }
                    if (@$answer[$key] == 2) {
                        $saw_preferred = 1;
                        $htext .= "*";
                    }
                    $hours[] = $htext;
                }
            }
            if (count ($hours)) {
                $txt = sprintf ("<strong>%s</strong> %s", 
                $dnames[$day], implode (",", $hours));
            } else {
                $txt = sprintf ("%s none", $dnames[$day]);
            }
            $days[] = $txt;
        }
        $val = implode ("<br/>", $days);
        if ($saw_preferred) {
            $val .= "<br/>( * means preferred)";
        }
    } else if ($question_id == "room_sound") {
        $val = "";
        foreach ($sound as $key => $txt) {
            $val .= sprintf (
                "%s: %s<br/>\n", 
                h($sound[$key]),
                h($answer[$key]));
        }
    } else {
        $val = h($answer);
    }

    if ($val == "")
        continue;

    $cols = array ();
    $cols[] = h($q_text);

    $cols[] = $val;

    $rows[] = $cols;
}


$body .= mktable (
    array (
        "<th style='width:30em'>Question</th>", 
        "Answer"), 
    $rows);

pfinish ();

