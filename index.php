<?php

require_once("app.php");

$anon_ok = 1;

$arg_app_id = intval (@$_REQUEST['app_id']);
$arg_show_all = intval (@$_REQUEST['show_all']);

pstart ();


if ($cfg['conf_key'] != "production") {
    $body .= sprintf ("<p class='debug_box'>effective time %s</p>\n", 
                      strftime ("%Y-%m-%d %H:%M:%S", $effective_time));
}

$body .= "<p>Information about the Festival is available at ";
$t="https://neffa.org/";
$body .= mklink_nw ($t, $t);
$body .= "</p>\n";
    
if ($deadline_status == 0) {
    $body .= sprintf ("<p><strong>"
                      ."Applications may be submitted starting %s"
                      ."</strong></p>\n",
                      strftime ("%B %e", $app_window_start));

    if (! $username && ! getsess ("beta_tester")) {
        $body .= "<div>If you are a beta tester,"
              ." enter your access code here:</div>\n";
        $body .= "<form action='beta.php'>\n";
        $body .= "<input type='password' name='access_code' />\n";
        $body .= "<input type='submit' value='login' />\n";
        $body .= "</form>\n";
        pfinish ();
    }
}


function deadline_msg ($end) {
    global $effective_time;

    $msg = strftime ("%B %e, %Y", $end);
    if ($effective_time > $end)
        $msg .= " <span class='attention'>(past)</span>";

    return ($msg);
}

$body .= "<h2>Timeline</h2>\n";

$rows = array ();

$cols = array ();
$cols[] = "<strong>General</strong>";
$cols[] = deadline_msg ($general_app_close);
$rows[] = $cols;

if (1) {
	$cols = array ();
	$cols[] = "<strong>Dance performance</strong>";
	$cols[] = deadline_msg ($dance_app_close);
	$rows[] = $cols;
}

$cols = array ();
$cols[] = "<strong>Ritual/Morris dance</strong>";
$cols[] = deadline_msg ($ritual_app_close);
$rows[] = $cols;

$body .= mktable (array ("Type", "Applications accepted until"), $rows);

$questions = get_questions ();

$application = NULL;

if ($arg_app_id) {
    $body .= "<div class='admin'>"
          ."[ADMIN MODE: you may override the original answers;"
          ." you can reverse your override by pasting in the original answer"
          ."]"
          ."</div>\n";
    $application = get_application ($arg_app_id);
    if ($application->fest_year != $submit_year) {
        $title_html = sprintf ("Previous year app %d %s",
                               $application->fest_year,
                               $application->test_flag ? "(test)" : "");
    }
}

$body .= sprintf ("<script>\n");
$body .= "//<![CDATA[\n";
$body .= sprintf ("var questions = %s;\n", json_encode ($questions));

if ($username)
    $val = "true";
else
    $val = "false";
$body .= sprintf ("var admin_mode = %s;\n", $val);
$body .= "//]]>\n";
$body .= "</script>\n";

function h24_to_12 ($hour) {
    if ($hour < 12) {
        return (sprintf ("%dam", $hour));
    } else if ($hour == 12) {
        return ("noon");
    } else if ($hour < 23) {
        return (sprintf ("%dpm", $hour - 12));
    } else {
        return ("11:30pm");
    }
}

function make_schedule ($application, $question_id) {
    $curvals = @$application->curvals[$question_id];
    $input_id = sprintf ("i_%s", $question_id);

    $full_from_day = 1;
    $full_to_day = 3;
    $full_from_hour = array (24, 19, 10, 10);
    $full_to_hour =   array ( 0, 22, 22, 16);

    $core_from_day = 2;
    $core_to_day = 3;
    $core_from_hour = array (24, 24, 10, 10);
    $core_to_hour =   array ( 0,  0, 17, 15);

    $table_from_hour = min ($full_from_hour);
    $table_to_hour = max ($full_to_hour);

    $core_min_from_hour = min ($core_from_hour);
    $core_max_to_hour = max ($core_to_hour);

    $day_names = array ("", "Friday", "Saturday", "Sunday");

    $ret = "<div class='schedule'>\n";

    $ret .= "<p><input type='checkbox' id='sched_any'>"
         ." Any time during the Festival is OK</p>\n";

    $hdr1 = "";
    $hdr2 = "";
    $hdr3 = "";
    $hdr4 = "";
    for ($day = $full_from_day; $day <= $full_to_day; $day++) {
        $classes = array ();
        if ($day == 1)
            $classes[] = "sched_fri";
        
        if ($day == 2)
            $classes[] = "group2";

        if ($core_from_day <= $day && $day <= $core_to_day) {
            $classes[] = "sched_core";
        } else {
            $classes[] = "sched_ext";
        }
        
        $class_str = implode (' ', $classes);
        
        $hdr1 .= sprintf ("<th colspan='3' class='%s'>%s</th>\n",
                          $class_str, $day_names[$day]);

        $hdr2 .= sprintf ("<th colspan='3' class='%s'>"
                          ."<input class='sched_all_day' type='checkbox'"
                          ." data-day='%d' />"
                          ." Any time today"
                          ."</th>\n",
                          $class_str, $day);

        $hdr3 .= sprintf ("<th colspan='3' class='%s'>"
                          ."<input class='sched_not_day' type='checkbox'"
                          ." data-day='%d' />"
                          ." No time today"
                          ."</th>\n",
                          $class_str, $day);

        $hdr4 .= sprintf ("<th class='%s'>No</th>\n"
                          ."<th class='%s'>OK</th>\n"
                          ."<th class='%s'>Preferred</th>\n",
                          $class_str,
                          $class_str,
                          $class_str);
                          
    }


    $ret .= "<table class='boxed sched'>\n";
    $ret .= "<thead>\n";
    $ret .= "<tr class='boxed_header'>\n";
    $ret .= "<th>Time</th>\n";
    $ret .= $hdr1;
    $ret .= "</tr>\n";

    $ret .= "<tr class='boxed_header'>\n";
    $ret .= "<th></th>\n";
    $ret .= $hdr2;
    $ret .= "</tr>\n";

    $ret .= "<tr class='boxed_header'>\n";
    $ret .= "<th></th>\n";
    $ret .= $hdr3;
    $ret .= "</tr>\n";

    $ret .= "<tr class='boxed_header'>\n";
    $ret .= "<th></th>\n";
    $ret .= $hdr4;
    $ret .= "</tr>\n";

    $ret .= "</thead>\n";

    $ret .= "<tbody>\n";
    for ($hour = $table_from_hour; $hour <= $table_to_hour; $hour++) {
        $from = h24_to_12 ($hour);
        $to = h24_to_12 ($hour + 1);
        
        $classes = [];
        if ($core_min_from_hour <= $hour && $hour <= $core_max_to_hour) {
            $classes[] = "sched_core";
        } else {
            $classes[] = "sched_ext";
        }

		if ($hour < 12 || $hour > 15)
				$classes[] = "sched_exclude_performance";

        $ret .= sprintf ("<tr class='%s'>\n", join(' ', $classes));
        $ret .= sprintf ("<td>%s to %s</td>\n", $from, $to);
        for ($day = $full_from_day; $day <= $full_to_day; $day++) {
            $classes = array ();
            if ($day == 1)
                $classes[] = "sched_fri";
            
            if ($day == 2)
                $classes[] = "group2";

            if ($core_from_hour[$day] <= $hour
                && $hour <= $core_to_hour[$day]) {
                $classes[] = "sched_core";
            } else {
                $classes[] = "sched_ext";
            }
            
            $class_str = implode (' ', $classes);

            $code = $day * 100000 + $hour * 100;
            
            $name = sprintf ("%s[%d]", $input_id, $code);
            
            foreach (array ("N", "Y", "P") as $val) {
                $checked = "";
                if (isset ($curvals[$code]) && $curvals[$code] == $val)
                    $checked = "checked='checked'";
                
                $ret .= sprintf ("<td class='%s'>", $class_str);

                if ($full_from_hour[$day] <= $hour
                    && $hour <= $full_to_hour[$day]) {
                    $ret .= sprintf (
                        "<input class='sched_item' type='radio' data-day='%d'"
                        ." name='%s' value='%s' %s>",
                        $day, $name, $val, $checked);
                }
                
                $ret .= "</td>\n";
            }
        }
        $ret .= "</tr>\n";
    }
    $ret .= "</tbody>\n";
    $ret .= "</table>\n";
    
    $ret .= "</div>\n";
    
    return ($ret);
}

$body .= "<div class='preface'>\n";
$body .= file_get_contents ($_SERVER['APP_ROOT'] . "/preface.html");
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

    $cfg['all_optional'] = 1; /* will be sent to javascript */

    $body .= "<input name='submit' type='submit' value='Save-no-email' />\n";

    $body .= mklink ("[admin]", "admin.php");

    $body .= " | ";
    $t = sprintf ("download.php?view_csv=1&app_id=%d", $arg_app_id);
    $body .= mklink ("[view raw data]", $t);

    if (isset ($application->access_code)) {
        $body .= " | ";
        $t = sprintf ("thanks.php?a=%s", 
                      rawurlencode ($application->access_code));
        $body .= mklink ("[view thanks page]", $t);

        $body .= " | ";
        $t = sprintf ("confirm.php?app_id=%d", $arg_app_id);
        $body .= mklink ("[view confirm page]", $t);
    }    

    if (@$application->confirmed) {
        $body .= sprintf ("<div>confirmation sent %s</div>\n",
                          $application->confirmed);
    } else {
        $body .= "<div>confirmation not yet sent</div>\n";
    }

    $body .= "<div>testing options</div>\n";

    $body .= "<div>\n";
    $c = "";
    if (getsess ("show_all"))
        $c = "checked='checked'";
    $body .= "<input type='checkbox' $c id='show_all' />"
          ." show all questions";
    $body .= "</div>\n";

    if ($application) {
        $body .= sprintf ("<div>year %d test %d</div>\n",
                          $application->fest_year,
                          $application->test_flag);
    }

    $body .= "</div>\n";
}

$body .= sprintf ("<input type='hidden' name='app_id' value='%d' />\n",
                  $arg_app_id);

foreach ($questions as $question) {
    $question_id = $question['id'];
    $class = @$question['class'];
    $section_id = sprintf ("s_%s", $question_id);
    $input_id = sprintf ("i_%s", $question_id);
    
    $body .= sprintf ("<div class='question' id='%s'>\n", $section_id);

    $body .= "<div class='debug debug_box'>\n";
    $body .= sprintf ("id: %s", h($question_id));
    if (@$question['show_if']) {
        $body .= sprintf (" &nbsp;|&nbsp; show_if: %s\n", 
                          h(json_encode ($question['show_if'])));
    }
    $body .= "</div>\n";
              

    $body .= "<h3>";
    $body .= autoquote($question['q']);

    if (! @$question['optional']) {
        $body .= sprintf (" <span class='required_marker'>*</span>");
        $body .= " <span class='required_text'></span>";
    } else {
        $body .= " <span class='optional_text'>(optional)</span>";
    }
    $body .= "</h3>\n";

    if ($class == "lookup_individual") {
        $body .= "<p><em>For our convenience, please use the format Lastname COMMA Firstname (as in Cannon,Jon with no embedded space).</em></p>\n";
    } else if ($class == "lookup_group") {
        $body .= "<p><em>For our convenience, please write group names that start with &quot;The&quot; in the format Beatles,The or Talking Heads,The.</em></p>\n";
    }

    if (($desc = @$question['desc_pre']) != "") {
        if (preg_match ("/</", $desc))
            $body .= $desc;
        else
            $body .= sprintf ("<div>%s</div>\n", h($desc));
    }


    $body .= "<div class='input_wrapper'>\n";
    
    if ($question_id == "availability") {
        $body .= make_schedule ($application, $question_id);
    } else if (@$question['choices']) {
        foreach ($question['choices'] as $choice) {
            $passed = 0;
            if (@$choice['deadline'] && $choice['deadline'] < $deadline_status) 
                $passed = 1;

            $body .= "<div>\n";
            $c = "";
            if ($choice['val'] == @$application->curvals[$question_id])
                $c = "checked='checked'";
            $d = "";
            if ($username == "" && $passed)
                $d = "disabled='disabled'";
            $body.=sprintf("<input type='radio' name='%s' value='%s'"
                           ." %s %s />\n",
                           $input_id,
                           h($choice['val']),
                           $c, $d);
            if (@$choice['desc']) {
                $body .= autoquote($choice['desc']);
            } else {
                $body .= h($choice['val']);
            }

            $body .= sprintf (" <span class='debug'>%s</span>\n",
                              h($choice['val']));

            if ($passed) {
                $body .= " <span class='attention'>"
                      ." (selection disabled: deadline passed)</span>";
            }

            $body .= "</div>\n";
        }

    } else if (@$question['textarea']) {
        $body .= sprintf ("<textarea cols='70' rows='5' id='%s' name='%s'>",
                          $input_id, $input_id);
        $body .= h(@$application->curvals[$question_id]);
        $body .= "</textarea>\n";

    } else {
        $body .= "<span>\n";
        $cur = @$application->curvals[$question_id];
        $body .= sprintf ("<input "
                          ." type='text' id='%s' name='%s' class='%s'"
                          ." size='40' value='%s'/>\n",
                          $input_id, $input_id, $class, h($cur));

        if ($class == "lookup_individual" || $class == "lookup_group") {
            if ($cur) {
                if (name_to_id ($cur) == 0) {
                    $body .= "<span class='initial_attention attention'>"
                          ."not found in NEFFA database</span>\n";
                    $t = sprintf ("admin.php?refresh_idx=1&return_to_app=%d",
                                  $arg_app_id);
                    if ($username)
                        $body .= mklink ("[refresh]", $t);
                } else {
                    $body .= "<span class='initial_attention attention_good'>"
                          ."matched in database!</span>";
                }
            }
        }
        $body .= "</span>\n";
    }

    $body .= "</div>\n"; /* input_wrapper */
    
    if ($class == "lookup_individual" || $class == "lookup_group") {
        if (($cur = @$application->curvals[$question_id]) != "") {
            if (($id = name_to_id ($cur)) != 0) {
                $q = query ("select pcode from pcodes where id = ?", $id);
                if (($r = fetch ($q)) == NULL) {
                    $pcode = "(missing)";
                } else {
                    $pcode = $r->pcode;
                }
                $body .= "<div class='debug_box'>\n";
                $body .= "<strong>pcode</strong>\n";
                $body .= sprintf (
                    "<input class='pcode' type='text' readonly='readonly'"
                    ." value='%s' />\n", h($pcode));

                $t = make_cgi_pcode_link($pcode);
                $body .= mklink_nw ("link to cgi", $t);
                $body .= "</div>\n";
            }
        }
    }

    if (($desc = @$question['desc']) != "") {
        $body .= "<div class='desc'>\n";
        if (strncmp ($desc, "<", 1) == 0)
            $body .= $desc;
        else
            $body .= sprintf ("<div>%s</div>\n", h($desc));
        $body .= "</div>\n";
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
            if (is_array ($patch->oldval)) {
                if (associative_array ($patch->oldval)) {
                    $avals = array ();
                    foreach ($patch->oldval as $key => $val) {
                        $avals[] = sprintf ("%s=%s", $key, $val);
                    }
                    $txt = implode ("; ", $avals);
                } else {
                    $txt = implode ("; ", $patch->oldval);
                }
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

$body .= "<input id='submit_button'"
    ." name='submit' type='submit' value='Submit' />\n";

$body .= "<div id='submit_button_warning' style='display:none'>\n";
$body .= "<p><strong>ERROR:</strong> You application can't be"
    ." submitted yet because a required"
    ." field is missing, or a field has in invalid value.  Please scroll"
    ." up to review the questions and look for a red"
    ." <span class='required_text'>required</span>"
    ." label.  After you've tried a new value for that field, click"
    ." the <strong>Submit</strong> button again.</p>";
$body .= "</div>\n";

$body .= "<div id='checkarea'>Do not write below here</div>";
$body .= "<hr/>";
$body .= "<div>";
$body .= "<input type='text' id='checkfield' name='checkfield' size='40' />";
$body .= "</div>\n";

$body .= "</form>\n";

pfinish ();
