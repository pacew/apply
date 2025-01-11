<?php

require_once("app.php");

$arg_app_id = intval (@$_REQUEST['app_id']);
$arg_show_all = intval (@$_REQUEST['show_all']);

if ($arg_app_id == 0)
    $anon_ok = 1;

pstart ();

$requests = array();

if ($arg_app_id > 0) {
    $q = query ("select request_id, ts, username, val, dismissed"
        ." from requests"
        ." where app_id = ?"
        ." order by request_id",
        $arg_app_id);
    while (($r = fetch ($q)) != NULL) {
        $req = (object)NULL;
        $req->request_id = intval($r->request_id);
        $req->ts = $r->ts;
        $req->username = trim($r->username);
        $req->val = json_decode($r->val, TRUE);
        $req->dismissed = intval($r->dismissed);
        $requests[] = $req;
    }
}

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
    if (($application = get_application ($arg_app_id)) == NULL)
        fatal ("can't find application");

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

    $body .= mklink ("[applications]", "admin.php");

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

        if (@$application->pcode) {
            $body .= " | ";
            $t = sprintf ("https://cgi.neffa.org/performer/confirm2.pl?P=%s",
                $application->pcode);
            $body .= mklink ("[view performer confirmation page]", $t);
        }
    }    

    if (@$application->confirmed) {
        $body .= sprintf ("<div>confirmation sent %s</div>\n",
            db_time_to_eastern ($application->confirmed));
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
        $body .= sprintf ("<div>year %d test %d app_id %d evid '%s'</div>\n",
                          $application->fest_year,
                          $application->test_flag,
                          $arg_app_id,
                          $application->evid);
    }

    if (count($requests) > 0) {
        $body .= "<h2>requests</h2>\n";
        $rows = array();
        foreach($requests as $req) {
            $cols = array();
            $cols[] = db_time_to_eastern($req->ts);
            $cols[] = $req->username;

            $changes = "";
            foreach ($req->val as $key => $val) {
                if ($key == "availability") {
                    $changes .= " availability ";
                } else {
                    $changes .= sprintf ("<div><strong>%s</strong> %s</div>\n",
                        h($key), h($val));
                }
            }
            $cols[] = $changes;
            $c = "";
            if ($req->dismissed)
                $c = "checked='checked'";
            $dismiss_id = sprintf ("dismiss_%d", $req->request_id);
            /* 
             * checkboxes don't get sent if they aren't checked
             * so if not checked, save.php will see the hidden 0.
             * if checked, the checkbox will override the hidden 0
             */
            $cols[] = sprintf ("<input type='hidden' name='%s' value='0' />\n"
                ." <input type='checkbox' $c name='%s' value='1' />",
                $dismiss_id, $dismiss_id);

            $rows[] = $cols;
        }
        $body .= mktable(array("time", "user", "change request", "dismissed"),
            $rows);
    }



    $body .= "</div>\n";

}

function format_availability ($avail) {
    $ret = "";
    $ret .= "<table>\n";
    $ret .= "<tr>";
    $ret .= "<th></th>";
    for ($hour = 10; $hour <= 22; $hour++) {
        $hour12 = $hour;
        if ($hour12 > 12)
            $hour12 -= 12;
        $ret .= sprintf ("<th>%d</th>", $hour12);
    }
    $ret .= "</tr>\n";

    for ($day = 1; $day <= 3; $day++) {
        $ret .= "<tr>\n";
        $days = array (1 => "Fri", 2 => "Sat", 3 => "Sun");
        $ret .= sprintf ("<th>%s</th>", @$days[$day]);

        for ($hour = 10; $hour <= 22; $hour++) {
            $ret .= "<td>";
            $code = $day * 100000 + $hour * 100;
            $ret .= @$avail[$code];
            $ret .= "</td>";
        }
        
        $ret .= "</tr>\n";
    }
    
    $ret .= "</table>\n";
    return ($ret);
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
    if (@$question['admin']) {
        $body .= " &nbsp;|&nbsp; admin\n"; 
    }
    $body .= "</div>\n";
              

    $body .= "<h3>";
    $body .= autoquote($question['q']);

    if (! @$question['optional'] && !@$question['admin']) {
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
        $body .= make_schedule ($application, $question_id, 0);
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
                if (($neffa_id = name_to_id ($cur)) == 0) {
                    $body .= "<span class='initial_attention attention'>"
                          ."not found in NEFFA database</span>\n";
                    $t = sprintf ("admin.php?refresh_idx=1&return_to_app=%d",
                                  $arg_app_id);
                    if ($username)
                        $body .= mklink ("[refresh]", $t);
                } else {
                    $body .= sprintf("<span"
                        ." class='initial_attention attention_good'>"
                        ."matched in database!  (%d)</span>",
                        $neffa_id);
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

    $reqs = array ();
    foreach ($requests as $req) {
        if (isset($req->val[$question_id])) {
            $reqs[] = $req;
        }
    }

    if ($patches || count($reqs) > 0) {
        $body .= "<div class='orig_answer'>\n";
    }
        
    if ($patches) {
        $body .= "<h3>Prior values</h3>\n";
        $rows = array ();
        foreach ($patches as $patch) {
            $cols = array ();
            $cols[] = h(db_time_to_eastern($patch->ts));
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
        $body .= mktable (array ("timestamp", "user", "old val"), $rows);
    }

    if (count($reqs) > 0) {
        $rows = array();
        foreach ($reqs as $request) {
            $cols = array();
            $cols[] = h(db_time_to_eastern($request->ts));
            if ($question_id == "availability") {
                $cols[] = sprintf ("<td>%s</td>\n",
                    format_availability($request->val[$question_id]));
            } else if ($question_id == "P_notes") {
                /* skip */
            } else {
                $cols[] = h($request->val[$question_id]);
            }
            if ($request->dismissed) {
                $txt = "dismissed";
            } else {
                $txt = "";
            }
            $cols[] = h($txt);
            $rows[] = $cols;
        }

        if ($question_id == "P_notes") {
            $hdr = array("timestamp", "dismissed?");
        } else {
            $hdr = array("timestamp", "change request", "dismissed?");
        }

        $body .= mktable($hdr, $rows);
        $body .= "<div>(you can change the dismissed flag at the top"
            ." of the application)</div>";
    }
        
    if ($patches || count($reqs) > 0) {
        $body .= "</div>\n";
    }

    $body .= "</div>\n"; /* question */

}

if ($arg_app_id == 0) {
    $body .= "<input id='submit_button'"
        ." name='submit' type='submit' value='Submit' />\n";
} else {
    $body .= "<input name='submit' type='submit'"
        ." value='Update (will not send email)' />\n";
}

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
