<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

$arg_perf_id = intval (@$_REQUEST['perf_id']);

pstart ();

if ($arg_perf_id) {
    $perf_name = get_perf_name ($arg_perf_id);

    $body .= sprintf ("<h1>Application for %s</h1>\n", h($perf_name));
}

$filename = sprintf ("%s/questions.json", $_SERVER['APP_ROOT']);
$questions = json_decode (file_get_contents ($filename), TRUE);
if (json_last_error ()) {
    $msg = json_last_error_msg ();
    fatal ("syntax error in questions.json - try jq: " . $msg);
}

$body .= sprintf ("<script type='text/javascript'>\n");
$body .= sprintf ("var questions = %s;\n", json_encode ($questions));
$body .= "</script>\n";

if (0 && $arg_perf_id == 0) {
    $body .= "<tr><th>Performer name</th><td>"
          ."<input type='text' size='40' name='perf_name' />"
          ."</td></tr>\n";
}

$body .= "<form action='save.php' method='post'>\n";
$body .= sprintf ("<input type='hidden' name='perf_id' value='%d' />\n",
                  $arg_perf_id);

foreach ($questions as $question) {
    $section_id = sprintf ("s_%s", $question['id']);
    $input_id = sprintf ("i_%s", $question['id']);
    
    $body .= sprintf ("<div class='question', id='%s'>\n", $section_id);

    $body .= sprintf ("<h3>%s</h3>\n", h($question['q']));

    if (@$question['desc']) {
        $body .= sprintf ("<div>%s</div>\n", h($question['desc']));
    }
    if (@$question['choices']) {
        foreach ($question['choices'] as $choice) {
            $body .= "<div>\n";
            $body .= sprintf ("<input type='radio' name='%s' value='%s' />\n",
                              $input_id,
                              h($choice['val']));
            if (@$choice['desc']) {
                $body .= h($choice['desc']);
            } else {
                $body .= h($choice['val']);
            }
            $body .= "</div>\n";
        }
    } else {
        $body .= sprintf ("<input type='text' id='%s' name='%s' size='40' />\n",
                          $input_id, $input_id);
    }
    $body .= "</div>\n"; /* question */
}


$body .= "</form>\n";


pfinish ();
