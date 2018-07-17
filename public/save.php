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

$questions = get_questions ();

$newvals = array ();
$newvals['app_id'] = $app_id;
foreach ($questions as $question) {
    $id = $question['id'];
    $input_id = sprintf ("i_%s", $id);
    
    $raw_val = @$_REQUEST[$input_id];
    if (is_array ($raw_val)) {
        $trimmed = array ();
        foreach ($raw_val as $key => $val) {
            $val = trim ($val);
            if ($val !== "") {
                if (preg_match ('/^[0-9][0-9]*$/', $key)) {
                    $trimmed[] =$val;
                } else {
                    $trimmed[$key] = $val;
                }
            }
        }
        $store_val = $trimmed;
    } else {
        $store_val = trim ($raw_val);
    }
        
    $newvals[$id] = $store_val;
}

if ($need_patch == 0) {
    query ("insert into json (app_id, ts, username, val)"
           ." values (?,current_timestamp,?,?)",
           array ($app_id, $username, json_encode ($newvals)));
    redirect ("thanks.php");
}

if (($application = get_application ($app_id)) == NULL)
    fatal ("can't find base application to update");

$diff = mikemccabe\JsonPatch\JsonPatch::diff($application->curvals, $newvals);

query ("insert into json (app_id, ts, username, val)"
       ." values (?,current_timestamp,?,?)",
       array ($app_id, $username, json_encode ($diff)));

redirect ("admin.php");

pfinish ();
