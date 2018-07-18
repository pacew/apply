<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

pstart ();

$body .= mklink ("home", "/");

$questions = get_questions ();

$body .= "<div class='plain'>\n";

foreach ($questions as $question) {
    $body .= "<div class='plain_question'>\n";

    $question_id = $question['id'];
    
    $body .= sprintf ("<h1>%s <span>(%s)</span></h1>\n", h($question['q']),
                      @$question['optional'] 
                      ? "optional" 
                      : "required");


    if (($desc = @$question['desc_pre']) != "") {
        if (strncmp ($desc, "<", 1) == 0)
            $body .= $desc;
        else
            $body .= sprintf ("<div>%s</div>\n", h($desc));
    }

    if ($question_id == "availability") {
    } else if ($question_id == "room_sound") {
    } else if (@$question['choices']) {
        foreach ($question['choices'] as $choice) {
            if (@$choice['desc']) {
                $body .= sprintf ("____ %s\n", h($choice['desc']));
            } else {
                $body .= sprintf ("____ %s\n", h($choice['val']));
            }
        }
    } else if (@$question['textarea']) {

    } else if (@$question['array_val']) {
    } else {
    }

    if (($desc = @$question['desc']) != "") {
        if (strncmp ($desc, "<", 1) == 0)
            $body .= $desc;
        else
            $body .= sprintf ("<div>%s</div>\n", h($desc));
    }
    
    $body .= "<pre>\n\n\n\n</pre>\n";

    $body .= "</div>\n"; /* plain_question */
}

$body .= "</div>\n"; /* plain */

pfinish ();
