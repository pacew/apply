<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

$arg_app_id = intval (@$_REQUEST['app_id']);
$arg_perf_id = intval (@$_REQUEST['perf_id']);

pstart ();

$app_id = 0;

$perf_id = 0;
$perf_name = "";

$questions = get_questions ();

$application = NULL;

if ($arg_app_id) {
    if (! getsess ("admin")) {
        $body .= "invalid request";
        pfinish ();
    }

    $app_id = $arg_app_id;

    if (($application = get_application ($app_id)) == NULL) {
        $body .= "not found";
        pfinish ();
    }

    $perf_id = $application->cur_vals['perf_id'];
    $perf_name = $application->cur_vals['perf_name'];
} else if ($arg_perf_id) {
    $perf_id = $arg_perf_id;
    $perf_name = get_perf_name ($perf_id);
}

if ($perf_name) {
    $body .= sprintf ("<h1>Application for %s</h1>\n", h($perf_name));
}

if ($app_id) {
    $body .= "<div class='admin'>"
          ."[ADMIN MODE: you may override the original answers;"
          ." you can cancel your override by pasting in the original answer"
          ."]"
          ."</div>\n";
    $body .= mklink ("home", "/");
}

$body .= sprintf ("<script type='text/javascript'>\n");
$body .= sprintf ("var questions = %s;\n", json_encode ($questions));
$body .= "</script>\n";

function h24_to_12 ($hour) {
    if ($hour < 12) {
        return (sprintf ("%dam", $hour));
    } else if ($hour == 12) {
        return ("noon");
    } else if ($hour < 24) {
        return (sprintf ("%dpm", $hour - 12));
    } else {
        return ("11:30pm");
    }
}

function make_schedule () {
    $ret = "<div class='question'>\n";

    $ret .= "<h2>Availability</h2>\n";
    
    $ret .= "<p>These hours refer to the times that you are available"
         ." to perform, and NOT your preferred times. Please note that"
         ." the greater your availability, the greater the likelihood"
         ." that you will be scheduled. </p>\n";

    $ret .= "<p style='color:red'>"
         ." [DEVELOPMENT NOTE: this isn't animated yet.  if we go with"
         ." this concept, clicking an aggregate box will automatically"
         ." set all the boxes in the appropriate group.</p>\n";
    
    $ret .= "<input type='checkbox'> Any time during the festival\n";

    $rows = array ();

    $cols = array ();
    for ($day = 0; $day < 3; $day++) {
        $days = array ("Friday", "Saturday", "Sunday");
        $cols[] = sprintf ("<input type='checkbox'> Any time %s",
                           $days[$day]);
    }
    $rows[] = $cols;

    for ($hour = 10; $hour <= 23; $hour++) {
        $from = h24_to_12 ($hour);
        $to = h24_to_12 ($hour + 1);

        $cols = array ();
        for ($day = 0; $day < 3; $day++) {
            $text = sprintf ("%s to %s", $from, $to);

            if ($day == 0 && $hour < 19)
                $text = "";
            
            if ($day == 2) {
                if ($hour == 16) {
                    $text = "4pm to 5:30pm";
                } else if ($hour > 16) {
                    $text = "";
                }
            }

            $html = "";
            if ($text) {
                $html = sprintf ("<input type='checkbox' /> %s\n", $text);
            }

            $cols[] = $html;
        }
        $rows[] = $cols;
    }

    $ret .= mktable (array ("Friday", "Saturday", "Sunday"), $rows);

    $ret .= "</div>\n";
    
    return ($ret);
}


$body .= "<form id='apply_form' action='save.php' method='post'>\n";

/* prevent ENTER in text field from submitting the form ... users
   have to use the real submit button */
$body .= "<button type='submit' onclick='return false' style='display:none'>"
      ."</button>\n";

if ($cfg['conf_key'] == "pace") 
    $body .= "<input type='submit' value='Submit' />\n";

$body .= mklink ("[admin]", "admin.php");

$body .= sprintf ("<input type='hidden' name='perf_id' value='%d' />\n",
                  $perf_id);

$body .= sprintf ("<input type='hidden' name='app_id' value='%d' />\n",
                  $app_id);

foreach ($questions as $question) {
    $question_id = $question['id'];
    $section_id = sprintf ("s_%s", $question_id);
    $input_id = sprintf ("i_%s", $question_id);
    
    $body .= sprintf ("<div class='question', id='%s'>\n", $section_id);

    $body .= "<h3>";
    $body .= h($question['q']);
    if (! @$question['optional']) {
        $body .= sprintf (" <span class='required_marker'>*</span>");
        $body .= " <span class='required_text'></span>";
    }
    $body .= "</h3>\n";

    if (@$question['choices']) {
        foreach ($question['choices'] as $choice) {
            $body .= "<div>\n";
            $c = "";
            if ($choice['val'] == @$application->cur_vals[$question_id])
                $c = "checked='checked'";
            $body .= sprintf ("<input type='radio' name='%s' value='%s' %s />\n",
                              $input_id,
                              h($choice['val']),
                              $c);
            if (@$choice['desc']) {
                $body .= h($choice['desc']);
            } else {
                $body .= h($choice['val']);
            }
            $body .= "</div>\n";
        }

    } else if (@$question['textarea']) {
        $body .= sprintf ("<textarea cols='70' rows='5' id='%s' name='%s'>",
                          $input_id, $input_id);
        $body .= h(@$application->cur_vals[$question_id]);
        $body .= "</textarea>\n";

    } else {
        $c = "";
        switch (@$question['lookup']) {
        case "individual":
            $c = "class='lookup_individual'";
            break;
        case "group":
            $c = "class='lookup_group'";
            break;
        }
        $body .= sprintf ("<input "
                          ." type='text' id='%s' name='%s' %s"
                          ." size='40' value='%s'/>\n",
                          $input_id, $input_id, $c,
                          h(@$application->cur_vals[$question_id]));
    

    }

    if (($desc = @$question['desc']) != "") {
        if (strncmp ($desc, "<", 1) == 0)
            $body .= $desc;
        else
            $body .= sprintf ("<div>%s</div>\n", h($question['desc']));
    }

    if (@$application->override_vals[$question_id]) {
        $body .= "<div class='orig_answer'>\n";
        $body .= "<h3>Original answer:</h3>\n";
        $orig = trim ($application->orig_vals[$question_id]);
        if ($orig == "")
            $orig = "(blank)";
        if (@$question['textarea']) {
            $body .= "<textarea cols='60' rows='5' readonly='readonly'>\n";
            $body .= h($orig);
            $body .= "</textarea>\n";
        } else {
            $body .= sprintf ("<input type='text' readonly='readonly' size='40'"
                              ." value='%s' />\n", 
                              h($orig));
        }
        $body .= "</div>\n";
    }

    $body .= "</div>\n"; /* question */

    
    if ($question['id'] == "event_desc")
        $body .= make_schedule ();
}

$body .= "<input type='submit' value='Submit' />\n";

$body .= "</form>\n";


pfinish ();
