<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

$arg_app_id = intval (@$_REQUEST['app_id']);
$arg_show_all = intval (@$_REQUEST['show_all']);

pstart ();

$body .= mklink ("home", "/");

$questions = get_questions ();

$application = NULL;

if ($arg_app_id) {
    $body .= "<div class='admin'>"
          ."[ADMIN MODE: you may override the original answers;"
          ." you can cancel your override by pasting in the original answer"
          ."]"
          ."</div>\n";
    $application = get_application ($arg_app_id);
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

function make_schedule ($application, $question_id) {
    $curvals = @$application->curvals[$question_id];
    $input_id = sprintf ("i_%s", $question_id);

    $ret = "<div class='schedule'>\n";

    $ret .= "<input id='sched_any' type='checkbox'>"
         ." Any time during the festival\n";

    $day_names = array ("", "Friday", "Saturday", "Sunday");

    $rows = array ();

    $cols = array ();
    for ($day = 2; $day <= 3; $day++) {
        $cols[] = sprintf ("<input type='checkbox'"
                           ." class='sched_all_day'"
                           ." data-day='%d'> Any time %s",
                           $day, $day_names[$day]);
    }
    $rows[] = $cols;

    for ($hour = 10; $hour <= 23; $hour++) {
        $from = h24_to_12 ($hour);
        $to = h24_to_12 ($hour + 1);

        $cols = array ();
        for ($day = 2; $day <= 3; $day++) {
            $text = sprintf ("%s to %s", $from, $to);

            if ($day == 1) {
                if ($hour < 19)
                    $text = "";
            } else if ($day == 3) {
                if ($hour == 16) {
                    $text = "4pm to 5:30pm";
                } else if ($hour > 16) {
                    $text = "";
                }
            }

            $code = $day * 100000 + $hour * 100;
            $html = "";
            if ($text) {
                $c = "";
                if ($curvals && array_search ($code, $curvals) !== FALSE)
                    $c = "checked='checked'";
                $html = sprintf ("<input type='checkbox' class='sched_item' $c"
                                 ." data-day='%d'"
                                 ." name='%s[]' value='%d' /> %s\n", 
                                 $day, $input_id, $code, $text);
            }

            $cols[] = $html;
        }
        $rows[] = $cols;
    }

    $ret .= mktable (array ("Saturday", "Sunday"), $rows);

    $ret .= "</div>\n";
    
    return ($ret);
}

function make_room_sound ($application, $question_id) {
    $curval = @$application->curvals[$question_id];

    $input_id = sprintf ("i_%s", $question_id);

    $ret = "<div class='room_sound'>\n";

    $kinds = array (
        array ("stage_with", "An auditorium stage with amplification" ),
        array ("stage_without", "An auditorium stage with NO amplification"),
        array ("double_with", "A double classroom with amplification" ),
        array ("double_without", "A double classroom with NO amplification" ),
        array ("single_mic", 
               "A single classroom with a single performer-operated mic"),
        array ("single_without", 
               "A single classroom with NO sound equipment")
    );

    $rows = array ();
    foreach ($kinds as $kind) {
        $id = $kind[0];
        $val = $kind[1];
        
        $cols = array ();
        $cols[] = h($val);

        $choices = array ("yes", "if_necessary", "no");
        foreach ($choices as $choice) {
            $c = "";
            if (strcmp (@$curval[$id], $choice) == 0)
                $c = "checked='checked'";
            $cols[] = sprintf ("<input type='radio' $c"
                               ." name='%s[%s]'"
                               ." value='%s' />",
                               $input_id, h($id),
                               h($choice));
        }
        $rows[] = $cols;
    }
    $ret .= mktable (array ("Room type", "Yes", "If necessary", "No"), $rows);
    
    $ret .= "</div>\n";

    return ($ret);
}

$body .= "<div>\n";
$body .= mklink ("All questions as plain text page", "plain.php");
$body .= "</div>\n";


$body .= "<p>\n"
      ."Several questions ask for names of performers or groups.  It's"
      ." important to make sure these entries match the NEFFA Performer"
      ." Index for existing performers.  When you type a name in these"
      ." fields, the system will pop up a box of possible matches, if"
      ." any are found.  If you can't find someone who you think should"
      ." be in the master database, it may help if you visit this page"
      ." and hunt around: "
      .mklink_nw ("NEFFA Performer Index",
                  "https://cgi.neffa.org//public/showperf.pl?INDEX=ALL")
      ."</p>\n"
      ;

$body .= "<form id='apply_form' action='save.php' method='post'>\n";

/* prevent ENTER in text field from submitting the form ... users
   have to use the real submit button */
$body .= "<button type='submit' onclick='return false' style='display:none'>"
      ."</button>\n";

if ($username) {
    $body .= "<div class='debug_box'>\n";

    $body .= "<input type='submit' value='Submit' />\n";

    $body .= mklink ("[admin]", "admin.php");

    $body .= "<div>testing options</div>\n";
    $body .= "<div>\n";
    $c = "";
    if (getsess ("all_optional"))
        $c = "checked='checked'";
    $body .= "<input type='checkbox' $c id='all_optional' />"
          ." allow submissions with missing required fields";
    $body .= "</div>\n";
    $body .= "<div>\n";
    $c = "";
    if (getsess ("show_all"))
        $c = "checked='checked'";
    $body .= "<input type='checkbox' $c id='show_all' />"
          ." show all questions";
    $body .= "</div>\n";

    $body .= "</div>\n";
}

$body .= sprintf ("<input type='hidden' name='app_id' value='%d' />\n",
                  $arg_app_id);

foreach ($questions as $question) {
    $question_id = $question['id'];
    $section_id = sprintf ("s_%s", $question_id);
    $input_id = sprintf ("i_%s", $question_id);
    
    $body .= sprintf ("<div class='question', id='%s'>\n", $section_id);

    $body .= "<div class='debug debug_box'>\n";
    $body .= sprintf ("id: %s", h($question_id));
    if (@$question['show_if']) {
        $body .= sprintf (" &nbsp;|&nbsp; show_if: %s\n", 
                          h(json_encode ($question['show_if'])));
    }
    $body .= "</div>\n";
              

    $body .= "<h3>";
    $body .= h($question['q']);
    if (! @$question['optional']) {
        $body .= sprintf (" <span class='required_marker'>*</span>");
        $body .= " <span class='required_text'></span>";
    }
    $body .= "</h3>\n";

    if (($desc = @$question['desc_pre']) != "") {
        if (strncmp ($desc, "<", 1) == 0)
            $body .= $desc;
        else
            $body .= sprintf ("<div>%s</div>\n", h($desc));
    }


    $body .= "<div class='input_wrapper'>\n";
    
    if ($question_id == "availability") {
        $body .= make_schedule ($application, $question_id);
    } else if ($question_id == "room_sound") {
        $body .= make_room_sound ($application, $question_id);
    } else if (@$question['choices']) {
        foreach ($question['choices'] as $choice) {
            $body .= "<div>\n";
            $c = "";
            if ($choice['val'] == @$application->curvals[$question_id])
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

            $body .= sprintf (" <span class='debug'>%s</span>\n",
                              h($choice['val']));
            $body .= "</div>\n";
        }

    } else if (@$question['textarea']) {
        $body .= sprintf ("<textarea cols='70' rows='5' id='%s' name='%s'>",
                          $input_id, $input_id);
        $body .= h(@$application->curvals[$question_id]);
        $body .= "</textarea>\n";

    } else if (@$question['array_val']) {
        $c = "";
        if (@$question['class'])
            $c = sprintf ("class=%s", $question['class']);
        
        $cur = @$application->curvals[$question_id];
        if (is_string ($cur)) {
            $cur = array ($cur);
        } else if (! is_array ($cur)) {
            $cur = array ();
        }
        if (count ($cur) == 0)
            $cur = array ("");

        $need_delete = 0;
        if (@$cur[0])
            $need_delete = 1;

        foreach ($cur as $str) {
            $body .= "<div>\n";
            $body .= "<span>\n";
            $cur = @$application->curvals[$question_id];
            $body .= sprintf ("<input "
                              ." type='text' id='%s' name='%s%s' %s"
                              ." size='40' value='%s'/>\n",
                              $input_id, 
                              $input_id, @$question['array_val'] ? "[]" : "",
                              $c,
                              h($str));
    
            $s = "style='display:none'";
            if ($need_delete)
                $s = "";
            
            $body .= "<button type='button' $s class='del_button'>"
                  ."delete</button>\n";
            $body .= "</span>\n";
            $body .= "</div>\n";
        }
    } else {
        $c = "";
        if (@$question['class'])
            $c = sprintf ("class=%s", $question['class']);
        $body .= "<span>\n";
        $cur = @$application->curvals[$question_id];
        $body .= sprintf ("<input "
                          ." type='text' id='%s' name='%s' %s"
                          ." size='40' value='%s'/>\n",
                          $input_id, $input_id, $c, h($cur));
        $body .= "</span>\n";
    }

    $body .= "</div>\n"; /* input_wrapper */
    
    if ($question_id == "busy_people") {
        $body .= "<div>\n";
        $body .= "<button type='button' id='add_another'>Add another</button>\n";
        $body .= "</div>\n";
    }

    if (($desc = @$question['desc']) != "") {
        if (strncmp ($desc, "<", 1) == 0)
            $body .= $desc;
        else
            $body .= sprintf ("<div>%s</div>\n", h($desc));
    }

    $patches = @$application->patches[$question_id];
    if ($patches) {
        $body .= "<div class='orig_answer'>\n";
        $body .= "<h3>Changes made by admins</h3>\n";
        $rows = array ();
        foreach ($patches as $patch) {
            $cols = array ();
            $cols[] = h($patch->ts);
            $cols[] = h($patch->username);
            $txt = '';
            if (is_array ($patch->oldval)) {
                $txt = implode ("; ", $patch->oldval);
            } else {
                $txt = $patch->oldval;
            }
            $cols[] = h($txt);
            $rows[] = $cols;
        }
        $body .= mktable (array ("timestamp", "user", "from val"), $rows);
        $body .= "</div>\n";
    }

    $body .= "</div>\n"; /* question */

}

$body .= "<input type='submit' value='Submit' />\n";

$body .= "</form>\n";


pfinish ();
