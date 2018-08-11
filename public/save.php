<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");
require_once ($_SERVER['APP_ROOT'] . "/JsonPatch.php");

$app_id = intval (@$_REQUEST['app_id']);

pstart ();

if ($app_id == 0) {
    $need_patch = 0;
    $app_id = get_seq ();
} else {
    $need_patch = 1;
}

$want_email = 0;
if (@$_REQUEST['submit'] == "Submit")
    $want_email = 1;

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
}

function make_access_code () {
    return (generate_urandom_string (8));
}


if ($need_patch == 0) {
    $access_code = make_access_code ();
    
    query ("insert into json (app_id, ts, username, val, access_code)"
           ." values (?,current_timestamp,?,?,?)",
           array ($app_id, 
                  $username, 
                  json_encode ($newvals),
                  $access_code));
} else {
    if (($application = get_application ($app_id)) == NULL)
        fatal ("can't find base application to update");

    $access_code = $application->access_code;

    $diff = mikemccabe\JsonPatch\JsonPatch::diff($application->curvals, 
                                                 $newvals);

    query ("insert into json (app_id, ts, username, val)"
           ." values (?,current_timestamp,?,?)",
           array ($app_id, $username, json_encode ($diff)));
}


$t = sprintf ("thanks.php?a=%s&send_email=%d", 
              rawurlencode ($access_code),
              $want_email);
redirect ($t);

pfinish ();
