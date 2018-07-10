<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

$arg_app_id = intval (@$_REQUEST['app_id']);
$arg_perf_id = intval (@$_REQUEST['perf_id']);
$arg_perf_name = trim (@$_REQUEST['perf_name']);
$arg_email = trim (@$_REQUEST['email']);

pstart ();

function do_insert ($table_name, $vals) {
    global $questions;
    
    $colnames = array ();
    $sql_vals = array ();
    $placeholders = array ();
    
    $colnames[] = "app_id";
    $sql_vals[] = $vals['app_id'];
    $placeholders[] = "?";

    $colnames[] = "perf_id";
    $sql_vals[] = $vals['perf_id'];
    $placeholders[] = "?";

    $colnames[] = "perf_name";
    $sql_vals[] = $vals['perf_name'];
    $placeholders[] = "?";

    foreach ($questions as $question) {
        $id = $question['id'];
        $colnames[] = $id;
        $sql_vals[] = $vals[$id];
        $placeholders[] = "?";
    }

    $stmt = sprintf ("insert into %s (%s) values (%s)",
                     $table_name,
                     implode (',', $colnames),
                     implode (',', $placeholders));
    query ($stmt, $sql_vals);
}

function do_update ($table_name, $vals) {
    global $questions;

    $exprs = array ();
    $sql_vals = array ();
    
    $exprs[] = "perf_id = ?";
    $sql_vals[] = @$vals['perf_id'];

    $exprs[] = "perf_name = ?";
    $sql_vals[] = @$vals['perf_name'];

    foreach ($questions as $question) {
        $id = $question['id'];
        $exprs[] = sprintf ("%s = ?", $id);
        $sql_vals[] = @$vals[$id];
    }

    $sql_vals[] = $vals['app_id'];

    $stmt = sprintf ("update %s"
                     ." set %s"
                     ." where app_id = ?",
                     $table_name,
                     implode (',', $exprs));
    query ($stmt, $sql_vals);
}

$questions = get_questions ();

$new_vals = array ();
foreach ($questions as $question) {
    $id = $question['id'];
    $input_id = sprintf ("i_%s", $id);
    $new_vals[$id] = trim (@$_REQUEST[$input_id]);
}

if ($arg_app_id == 0) {
    $new_vals['app_id'] = get_seq ();
    $new_vals['perf_id'] = $arg_perf_id;
    if ($arg_perf_id) {
        $new_vals['perf_name'] = get_perf_name ($arg_perf_id);
    } else {
        $new_vals['perf_name'] = $arg_perf_name;
    }

    do_insert ("applications", $new_vals);

} else {
    /* overriding an old application */
    
    if (! getsess ("admin")) {
        $body .= "invalid request";
        pfinish ();
    }
    $new_vals['app_id'] = $arg_app_id;
    $new_vals['perf_id'] = $arg_perf_id;
    $new_vals['perf_name'] = $arg_perf_name;

    if (($application = get_application ($arg_app_id)) == NULL) {
        $body .= "not found";
        pfinish ();
    }

    foreach ($questions as $question) {
        $id = $question['id'];
        $orig_val = $application->orig_vals[$id];

        if (strcmp ($orig_val, $new_vals[$id]) == 0)
            $new_vals[$id] = "";
    }

    if ($application->override_vals == NULL) {
        do_insert ("overrides", $new_vals);
    } else {
        do_update ("overrides", $new_vals);
    }
}



redirect ("thanks.php");

pfinish ();
