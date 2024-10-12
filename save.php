<?php

require_once("app.php");

$anon_ok = 1;

$app_id = intval (@$_REQUEST['app_id']);

pstart ();

if (@$_REQUEST['checkfield'] != "") {
    redirect ("/");
}

$want_email = 0;

if ($app_id == 0) {
    $need_patch = 0;
    $app_id = get_seq ();
    $want_email = 1;
} else {
    $need_patch = 1;
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
            ." where email = ?"
	    ." and year(sent) = year(curdate()) ",
            $to_email);
$r = fetch ($q);
$email_count = intval ($r->count);
$email_limit = 11;
if ($email_count >= $email_limit) {
    $t = sprintf ("thanks.php?a=%s&email_suppressed=1", 
                  rawurlencode ($application->access_code));
    redirect ($t);
}

$subject = sprintf ("NEFFA %d Performer application confirmation",
                    $submit_year);

$msg = sprintf ("<h1>Thanks for submitting your"
                ." NEFFA %d Performer application</h1>\n",
                $submit_year);

$msg .= "<p>You can use the following link to review the data"
     ." that you submitted. You can't change any of the answers"
     ." until we notify you that we've completed the initial processing"
     ." of your application. After that, you can use this link"
     ." to keep your contact information up to date.</p>\n";

$target = sprintf ("https://%s/thanks.php?a=%s", 
                   $_SERVER['HTTP_HOST'],
                   rawurlencode ($application->access_code));

$msg .= sprintf ("<p><a href='%s'>%s</a></p>", $target, $target);

$msg .= "<p>If you have any questions, please contact ";
$info_email = "program@neffa.org";
$msg .= sprintf ("<a href='mailto:%s'>%s</a>", $info_email, $info_email);
$msg .= "</p>\n";

$msg .= "<p>If you did not recently submit a NEFFA application,"
      ." someone else must have entered your email address"
      ." into our form.  You can ignore this message.</p>";

$html = $msg;
$plain = strip_tags ($html, "<p>");
$plain = preg_replace (":<p>:", "\n", $plain);
$plain = preg_replace (":</p>:", "\n", $plain);

if (! $want_email) {
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
    

$args = (object)NULL;
$args->to_email = $to_email;
$args->subject = $subject;
$args->body_html = $html;
$args->body_text = $plain;

send_email ($args);

$t = sprintf ("thanks.php?a=%s", rawurlencode ($application->access_code));

redirect ($t);
