<?php

// prevent Warning: preg_match(): JIT compilation failed: no more memory in ...
ini_set ("pcre.jit", 0); 

require_once ($_SERVER['PSITE_PHP']);
require_once ($_SERVER['APP_ROOT'] . "/JsonPatch.php");

$cur_year = intval(strftime("%Y"));
if (strftime("%m") > 6) {
    $cur_year++;
}
$last_year = $cur_year - 1;

$submit_year = $cur_year;
$submit_test_flag = 1;

$title_html = sprintf ("NEFFA Performer Application %d", $submit_year);
$username = "";

function pstart () {
    global $pstart_timestamp;
    $pstart_timestamp = microtime (TRUE);

    psite_session ();

    ini_set ("display_errors", "1");

    global $view_year, $cur_year;
    $view_year = intval(getsess ("view_year"));
    if ($view_year == 0) {
        $view_year = $cur_year;
        putsess ("view_year", $view_year);
    }
    global $view_test_flag;
    $val = getsess("view_test_flag");
    if ($val === NULL)
        $val = 1;
    $view_test_flag = intval($val);

    global $body;
    $body = "";

    global $username;
    $username = getsess ("username2");
    global $anon_ok;
    if (! @$anon_ok && $username == "") {
        redirect ("login.php");
    }
}

function pfinish () {
    $pg = "";

    $pg .= "<!doctype html>\n"
        ."<html lang='en'>\n"
        ."<head>\n"
        ."<meta charset='utf-8'>\n"
        ."<meta name='viewport' content='width=device-width,initial-scale=1'>\n";
    
    global $title_html;
    $pg .= "<title>";
    $pg .= $title_html;
    $pg .= "</title>\n";

    $pg .= sprintf ("<link rel='stylesheet' href='reset.css?c=%s' />\n",
                    get_cache_defeater ());
    $pg .= sprintf ("<link rel='stylesheet' href='style.css?c=%s' />\n",
                    get_cache_defeater ());

    $pg .= "<script src='https://ajax.googleapis.com"
        ."/ajax/libs/jquery/2.1.4/jquery.min.js'></script>\n";

    $pg .= "<link rel='stylesheet'"
        ." href='https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css'>\n";
    $pg .= "<script src='https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js'></script>\n";
    
    $pg .= "<script type='text/javascript' src='https://www.neffa.org/wp-content/themes/NEFFA_2017/js/Iframe-Resize/iframeResizer.contentWindow.min.js'></script>\n";

    global $cfg;
    $pg .= "<script>\n";
    $pg .= sprintf ("var cfg = %s;\n", json_encode ($cfg));
    $pg .= "</script>\n";


    $pg .= "<link rel='icon' href='https://www.neffa.org/wp-content/uploads/2017/12/cropped-favicon-32x32.png' sizes='32x32' />\n";
    $pg .= "<link rel='icon' href='https://www.neffa.org/wp-content/uploads/2017/12/cropped-favicon-192x192.png' sizes='192x192' />\n";

    $pg .= "</head>\n";
    
                    

    $pg .= "<body>\n";

    $pg .= "<div class='banner'>\n";
    $pg .= "<div class='login_link'>";
    $pg .= "<span class='nav'>\n";
    global $username;
    if (@$username == "") {
        $pg .= "<a class='login_anchor' target='_blank' href='login.php'>[ admin login ]</a>";
    } else {
        $pg .= "[ ";
        $pg .= "<a href='admin.php'>applications</a>";
        $pg .= " | ";
        $pg .= "<a href='logout.php'>logout</a>";
        $pg .= " ]";
    }
    $pg .= "</span>\n";
    $pg .= "</div>\n";
    $pg .= "<div style='clear:both'></div>\n";
    $pg .= "</div>\n";

    $pg .= "<div class='content'>\n";

    $pg .= sprintf ("<h1 class='banner_title'>%s</h1>\n",
                    $title_html);

    echo ($pg);
    $pg = "";

    global $body;
    $pg .= $body;

    $pg .= "</div>\n";

    $pg .= sprintf ("<script src='scripts.js?c=%s.js'></script>\n",
                    get_cache_defeater ());

    if ($username) {
        global $pstart_timestamp;
        $secs = microtime(TRUE) - $pstart_timestamp;
        $pg .= sprintf ("<div id='generation_time'>%.0f msecs</div>\n",
                        $secs * 1000);
    }

    $pg .= "</body>\n";
    $pg .= "</html>\n";
    echo ($pg);


    do_commits ();
    exit (0);
}




$index = NULL;
function get_neffa_index () {
    global $index, $cfg;
    if ($index == NULL) {
        $filename = $cfg['aux_dir'] . "/neffa_idx.json";
        if (($val = file_get_contents ($filename)) == "") {
            echo ("neffa_idx not found\n");
            exit ();
        }
        $index = json_decode ($val, TRUE);
    }
    return ($index);
}

function name_to_id ($name) {
    global $index;
    
    get_neffa_index ();

    return (@$index['name_to_id'][$name]);
}

function cmp_pct ($a, $b) {
    if ($a->pct < $b->pct)
        return (1);
    else if ($a->pct > $b->pct)
        return (-1);
    return (0);
}

function lookup_individual ($str) {
    return (do_lookup ($str, 0));
}

function lookup_group ($str) {
    return (do_lookup ($str, 1));
}

function do_lookup ($str, $group_flag) {
    global $index;
    
    get_neffa_index ();

    $words = preg_split ('/[\s,]+/', strtolower ($str));
    sort ($words);
    $my_normalized_str = implode (" ", $words);

    $poss = array ();
    foreach ($words as $word) {
        $s = soundex ($word);
        if (($perf_ids = @$index['by_soundex'][$s]) == NULL)
            continue;
        foreach ($perf_ids as $perf_id) {
            if (! isset ($poss[$perf_id])) {
                $entry = $index['perfs'][$perf_id];
                if ($group_flag == 0) {
                    if (@$entry['group'])
                        continue;
                } else {
                    if (! @$entry['group'])
                        continue;
                }

                $p = (object)NULL;
                $p->perf_id = $perf_id;
                $poss[$perf_id] = $p;
            }
        }
    }

    foreach ($poss as $p) {
        $norm = $index['perfs'][$p->perf_id]['norm'];
        $pct = 0;
        $p->score = similar_text ($norm, $my_normalized_str, $pct);
        $p->pct = $pct;
    }

    usort ($poss, 'cmp_pct');

    $ret = array ();
    $limit = 10;
    $count = 0;
    foreach ($poss as $p) {
        $count++;
        if ($count >= $limit)
            break;
        if ($p->pct < 30)
            break;
        $r = (object)NULL;
        $r->perf_id = $p->perf_id;
        $r->score = $p->score;
        $r->name = $index['perfs'][$p->perf_id]['name'];
        $r->pct = $p->pct;
        
        $ret[] = $r;
    }

    return ($ret);
}

$questions = NULL;
$questions_by_id = NULL;

function get_questions () {
    global $questions, $questions_by_id;
    if ($questions == NULL) {
        $filename = sprintf ("%s/questions.json", $_SERVER['APP_ROOT']);
        $questions = json_decode (file_get_contents ($filename), TRUE);
        if (json_last_error ()) {
            $msg = json_last_error_msg ();
            fatal ("syntax error in questions.json - try jq: " . $msg);
        }
        $questions_by_id = array ();
        foreach ($questions as $question) {
            $question_id = $question['id'];
            $questions_by_id[$question_id] = $question;
        }
    }
    return ($questions);
}

function get_applications () {
    global $view_year, $view_test_flag;
    
    $apps = array ();

    if (get_option("db") == "postgres") {
        $ts_col = "to_char (ts, 'YYYY-MM-DD HH24:MI:SS') as ts";
    } else {
        $ts_col = "ts";
    }

    $q = query ("select app_id, val, attention, $ts_col"
                ." from json"
                ." where fest_year = ?"
                ."   and test_flag = ?"
                ." order by app_id, ts",
                array ($view_year, $view_test_flag));
    while (($r = fetch ($q)) != NULL) {
        $app_id = intval ($r->app_id);

        if (strncmp ($r->val, "{", 1) == 0) {
            $app = (object)NULL;
            $app->app_id = $app_id;
            $app->attention = intval($r->attention);
            $app->ts = $r->ts;
            $app->curvals = json_decode ($r->val, TRUE);
            if ($app->curvals == NULL) {
                $curvals = array();
                $oops = sprintf ("oops%d", $app_id);
                $curvals['name'] = $oops;
                $curvals['email'] = $oops;
                $curvals['app_category'] = $oops;
                $curvals['dance_style'] = $oops;
                $app->curvals = $curvals;
            }
            $apps[$app_id] = $app;
        } else {
            $patch = json_decode ($r->val, TRUE);
            if (($app = @$apps[$app_id]) != NULL) {
                $app->curvals = mikemccabe\JsonPatch\JsonPatch::patch(
                    $app->curvals,
                    $patch);
            }
        }
    }

    return ($apps);
}

function get_application ($app_id) {
    $q = query ("select ts, username, val, access_code, fest_year, test_flag"
                ." from json"
                ." where app_id = ?"
                ." order by ts",
                $app_id);
    
    $curvals = array ();
    $patches = array ();
    $access_code = NULL;
    $fest_year = 0;
    $test_flag = 0;
    while (($r = fetch ($q)) != NULL) {
        if (strncmp ($r->val, "{", 1) == 0) {
            $curvals = json_decode ($r->val, TRUE);
            $access_code = $r->access_code;
            $fest_year = intval($r->fest_year);
            $test_flag = intval($r->test_flag);
        } else {
            $p_arr = json_decode ($r->val, TRUE);
            $before_vals = array ();
            foreach ($p_arr as $patch) {
                $path = $patch['path'];
                if (! preg_match ('|/?([^/]*)|', $patch['path'], $matches))
                    continue;
                $question_id = $matches[1];
                if (!isset ($before_vals[$question_id]))
                    $before_vals[$question_id] = @$curvals[$question_id];
            }
            
            foreach ($before_vals as $question_id => $oldval) {
                $patch = (object)NULL;
                $patch->ts = $r->ts;
                $patch->username = $r->username;
                $patch->oldval = $oldval;
                if (! isset ($patches[$question_id]))
                    $patches[$question_id] = array ();
                $patches[$question_id][] = $patch;
            }

            $curvals = mikemccabe\JsonPatch\JsonPatch::patch($curvals, $p_arr);
        }
    }

    $application = (object)NULL;
    $application->fest_year = $fest_year;
    $application->test_flag = $test_flag;
    $application->access_code = $access_code;
    $application->curvals = $curvals;
    $application->patches = $patches;
    return ($application);
}

function all_digits ($val) {
    return (preg_match ('/^[0-9][0-9]*$/', $val));
}

function associative_array ($arr) {
    if (is_array ($arr) && count ($arr) > 0 && ! isset ($arr[0]))
        return (1);
    return (0);
}

function show_if_test ($condition, $curvals) {
    global $questions_by_id;
    
    $target_id = $condition[0];
    $target_question = $questions_by_id[$target_id];
    
    $val = $curvals[$target_id];
    if (@$target_question['choices']) {
        return (array_search ($val, $condition) !== FALSE);
    } else {
        return ($val != "");
    }
}

function active_question ($question_id, $curvals) {
    global $questions_by_id;

    $question = $questions_by_id[$question_id];

    if (($show_if = @$question['show_if']) != NULL) {
        if (is_array ($show_if[0])) {
            foreach ($show_if as $condition) {
                if (! show_if_test ($condition, $curvals))
                    return (FALSE);
            }
        } else {
            if (! show_if_test ($show_if, $curvals))
                return (FALSE);
        }
    }
    
    return (TRUE);
}

// require_once ("libphp-phpmailer/autoload.php");
$path = sprintf ("%s/PHPMailer/src", dirname($cfg['src_dir']));
require_once($path . "/Exception.php");
require_once($path . "/PHPMailer.php");
require_once($path . "/SMTP.php");
use PHPMailer\PHPMailer\PHPMailer;

function send_email ($args) {
    if (($smtp_cred = getvar ("smtp_cred")) == "")
        fatal ("no smtp credentials");
        
    $arr = preg_split ('/ /', $smtp_cred);
    $smtp_user = $arr[0];
    $smtp_password = $arr[1];
    
    $mail = new PHPMailer;
    $mail->isSMTP();
    $mail->Host = 'email-smtp.us-east-1.amazonaws.com';
    $mail->Username = $smtp_user;
    $mail->Password = $smtp_password;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('applications@neffa.org', 'NEFFA Applications');
    $mail->addAddress($args->to_email);
    $mail->Subject = $args->subject;

    $mail->Body = $args->body_html;
    $mail->isHTML(true);

    $mail->AltBody = preg_replace("/\\n/", "\r\n", $args->body_text);

    query ("insert into email_history (email, sent)"
           ." values (?, current_timestamp)",
           $args->to_email);
    do_commits ();

    if(!$mail->send()) {
        fatal ("Application submitted, but error sending confirmation email: "
               . $mail->ErrorInfo);
    }

    return (TRUE);
}



if (! get_option ("flat") && ! @$cli_mode) {
    require (router());
    /* NOTREACHED */
}

