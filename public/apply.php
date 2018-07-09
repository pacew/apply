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


$body .= "<form action='save.php' method='post'>\n";

/* prevent ENTER in text field from submitting the form ... users
   have to use the real submit button */
$body .= "<button type='submit' onclick='return false' style='display:none'>"
      ."</button>\n";

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
    } else if (@$question['textarea']) {
        $body .= sprintf ("<textarea cols='70' rows='5' id='%s' name='%s'>\n"
                          ."</textarea>\n",
                          $input_id, $input_id);
    } else {
        $body .= sprintf ("<input type='text' id='%s' name='%s' size='40' />\n",
                          $input_id, $input_id);
    }
    $body .= "</div>\n"; /* question */

    
    if ($question['id'] == "event_desc")
        $body .= make_schedule ();
}

$body .= "<input type='submit' value='Submit' />\n";

$body .= "</form>\n";


pfinish ();
