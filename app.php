<?php

// prevent Warning: preg_match(): JIT compilation failed: no more memory in ...
ini_set ("pcre.jit", 0); 

require_once ($_SERVER['PSITE_PHP']);
require_once ($_SERVER['APP_ROOT'] . "/JsonPatch.php");

// access to neffa_pdb database (the "performer" database)
$pdb_params['dbtype'] = "mysql";
$pdb_params['host'] = 'neffaprog.neffa.dreamhosters.com';
$pdb_params['user'] = 'pace_willisson';

$file = sprintf ("%s/neffadb_passwd", $cfg['aux_dir']);
$pdb_params['password'] = trim (file_get_contents ($file));


if (! @$cli_mode 
    && @$_SERVER['HTTPS'] == "" 
    && @$cfg['ssl_url'] != "") {
    $url = preg_replace ('|/$|', '', $cfg['ssl_url']);
    $path = preg_replace ('|^/|', '', $_SERVER['REQUEST_URI']);
    $t = sprintf ("%s/%s", $url, $path);
    header ("Location: $t");
    exit ();
}


$cur_year = intval(strftime("%Y"));
if (strftime("%m") >= 9) {
    $cur_year++;
}
$last_year = $cur_year - 1;

$submit_year = $cur_year;


function mmdd_to_timestamp ($mmdd, $start_of_day_flag) {
    global $last_year, $cur_year, $submit_year;
    
    if (sscanf ($mmdd, "%d/%d", $month, $mday) != 2)
        fatal (sprintf ("can't parse mmdd %s", $mmdd));

    if ($month > 5)
        $year = $submit_year - 1;
    else
        $year = $submit_year;
    
    if ($start_of_day_flag)
        $hms = "00:00:00";
    else
        $hms = "23:59:59";

    $t = sprintf ("%d-%02d-%02d %s", $year, $month, $mday, $hms);
    return (strtotime ($t));
}    

$app_window_start = mmdd_to_timestamp ("9/15", 1);
$general_app_close = mmdd_to_timestamp ("10/15", 0);
$dance_app_close = mmdd_to_timestamp ("1/5", 0);
$ritual_app_close = mmdd_to_timestamp ("1/15", 0);

$effective_time = time ();
if (0 && $cfg['conf_key'] != "production") {
    $effective_time = strtotime ("2/10/2020");
}


if ($effective_time < $app_window_start) {
    $deadline_status = 0;
} else if ($effective_time < $general_app_close) {
    $deadline_status = 1;
} else if ($effective_time < $dance_app_close) {
    $deadline_status = 2;
} else if ($effective_time < $ritual_app_close) {
    $deadline_status = 3;
} else {
    $deadline_status = 4;
}

/* don't really stop accepting applications */
if ($deadline_status >= 1 && $deadline_status < 4)
	$deadline_status = 1;

if ($deadline_status == 0) {
    $submit_test_flag = 1;
} else {
    $submit_test_flag = 0;
}


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
        $val = 0;
    $view_test_flag = intval($val);

    global $body;
    $body = "";

    global $username;
    $username = getsess ("username2");
    global $anon_ok;
    if (! @$anon_ok && $username == "") {
        $t = sprintf ("login.php?redirect_to=%s", 
            rawurlencode($_SERVER['REQUEST_URI']));
        redirect ($t);
    }

    $flash = trim (@$_SESSION['flash']);
    @$_SESSION['flash'] = "";
    if ($flash) {
		$body .= "<div class='flash'>";
		$body .= $flash;
		$body .= "</div>\n";
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
    
    global $cfg;
    $pg .= "<script>\n";
    $pg .= sprintf ("var cfg = %s;\n", json_encode ($cfg));
    $pg .= "</script>\n";


    $pg .= "<link rel='icon' href='https://www.neffa.org/wp-content/uploads/2017/12/cropped-favicon-32x32.png' sizes='32x32' />\n";
    $pg .= "<link rel='icon' href='https://www.neffa.org/wp-content/uploads/2017/12/cropped-favicon-192x192.png' sizes='192x192' />\n";

    $pg .= "</head>\n";
    
                    

    $pg .= "<body>\n";

    $pg .= "<header id='masthead'>\n";
    $pg .= "<div class='site-branding'>\n";
    $pg .= "<a href='https://www.neffa.org/'>";
    $pg .= "<img src='https://www.neffa.org/wp-content/uploads/2017/11/logo-neffa-white.svg' alt='NEFFA' />\n";
    $pg .= "</a>\n";
    $pg .= "</div>\n"; /* site-branding */
    $pg .= "</header>\n";

    $pg .= "<div class='banner'>\n";
    $pg .= "<div class='login_link'>";
    $pg .= "<span class='nav'>\n";

    $pg .= "[";

    global $username;
    if (@$username == "") {
        $pg .= "<a class='login_anchor' target='_blank' href='login.php'>"
            ."admin login</a>";
    } else {
        $pg .= "<a href='admin.php'>applications</a>";
    }

    if (@$username || getsess ("beta_tester")) {
        $pg .= " | ";
        $pg .= "<a href='logout.php'>logout</a>";
    }
    $pg .= "]";
    
    $pg .= "</span>\n";
    $pg .= "</div>\n";
    $pg .= "<div style='clear:both'></div>\n";
    $pg .= "</div>\n";

    global $submit_test_flag;

    if ($submit_test_flag) {
        $pg .= "<div class='beta_banner'>\n";
        $pg .= "TESTING MODE ... SUBMITTED DATA WILL NOT BE PROCESSED";
        $pg .= "</div>\n";
    }

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

$cached_apps = NULL;
function get_applications ($year = 0, $test_flag = 0) {
    global $cached_apps, $view_year, $view_test_flag;
    
    if ($cached_apps)
        return ($cached_apps);

    if ($year == 0)
        $year = $view_year;
    if ($test_flag == 0)
        $test_flag = $view_test_flag;

    $apps = array ();
    $q = query ("select app_id, val, attention, ts, confirmed, evid"
                ." from json"
                ." where fest_year = ?"
                ."   and test_flag = ?"
                ." order by app_id, ts",
                array ($year, $test_flag));
    while (($r = fetch ($q)) != NULL) {
        $app_id = intval ($r->app_id);

        if (strncmp ($r->val, "{", 1) == 0) {
            $app = (object)NULL;
            $app->app_id = intval($app_id);
            $app->attention = intval($r->attention);
            $app->ts = trim($r->ts);
            $app->confirmed = trim($r->confirmed);
            $app->evid = trim($r->evid);
            $app->curvals = json_decode ($r->val, TRUE);
            if ($app->curvals == NULL) {
                $curvals = array();
                $oops = sprintf ("oops%d", $app_id);
                $curvals['name'] = $oops;
                $curvals['email'] = $oops;
                $curvals['app_category'] = $oops;
                $curvals['dance_style'] = $oops;
                $curvals['fms_category'] = $oops;
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

    foreach ($apps as $app) {
        $app->neffa_id = name_to_id ($app->curvals['name']);
    }

    foreach ($apps as $app) {
        if ($app->evid == "")
            update_evid ($apps, $app);
    }

    $cached_apps = $apps;
    return ($apps);
}

/* === evid === */


$evid_neffa_id_to_core = array ();
$evid_cores_used = array ();
query ("delete from evid_info where evid_key not regexp '^[0-9].*'");
$q = query ("select evid_key, evid_core from evid_info");
while (($r = fetch ($q)) != NULL) {
    $neffa_id = intval($r->evid_key);
    $evid_core = intval ($r->evid_core);
    $evid_neffa_id_to_core[$neffa_id] = $evid_core;
    $evid_cores_used[$evid_core] = 1;
}

function neffa_id_to_evid_core ($neffa_id) {
    global $evid_neffa_id_to_core, $evid_cores_used;

    if (($evid_core = intval(@$evid_neffa_id_to_core[$neffa_id])) != 0)
        return ($evid_core);
    
    $evid_core = 10;
    while (isset ($evid_cores_used[$evid_core]))
        $evid_core += 1;
        
    $evid_cores_used[$evid_core] = 1;
    $evid_neffa_id_to_core[$neffa_id] = $evid_core;
    query ("insert into evid_info (evid_key, evid_core)"
        ." values (?, ?)",
        array ($neffa_id, $evid_core));
    return ($evid_core);
}

function evid_prefix_for_app($app) {
    $curvals = $app->curvals;
    if ($curvals['app_category'] == "Band" 
        || $curvals['app_category'] == "Band_Solo" 
        || $curvals['app_category'] == "Caller") {
        switch (@$curvals['dance_style']) {
        case "American": $prefix = "T"; break;
        case "English": $prefix = "P"; break;
        case "Couples": $prefix = "P"; break;
        case "English_Couples": $prefix = "P"; break;
        case "Int_Line": $prefix = "R"; break;
        default: $prefix = "X"; break;
        }
    } else if ($curvals['app_category'] == "Performance") {
        $prefix = "F";
    } else if ($curvals['app_category'] == "Ritual") {
        $prefix = "J";
    } else {
        $prefix = "M";
    }

    return ($prefix);
}

function extract_evid_prefix($evid) {
    if ($evid == "")
        return ("");
    return ($evid[0]);
}

function extract_evid_core($evid) {
    if (preg_match('/^.([0-9]+)/', $evid, $parts))
        return (intval($parts[1]));
    return (0);
}

// M123a -> 1 ; M123-99 -> 99
function extract_evid_seq($evid) {
    if (preg_match('/^.*([a-z])$/', $evid, $parts)) {
        $suffix = $parts[1];
        return (ord($suffix) - ord("a") + 1);
    } else if (preg_match('/^.*-([0-9]*)$/', $evid, $parts)) {
        return (int($parts[1]));
    } else {
        return (1);
    }
}

function next_evid_seq($apps, $neffa_id) {
    $max_seq = 0;
    foreach ($apps as $app) {
        if ($app->neffa_id == $neffa_id) {
            $seq = extract_evid_seq($app->evid);
            if ($seq > $max_seq)
                $max_seq = $seq;
        }
    }
    return ($max_seq + 1);
}

function build_evid($prefix, $core, $seq) {
    if ($seq <= 26) {
        return (sprintf("%s%d%s", $prefix, $core, chr(ord("a") + $seq - 1)));
    } else {
        return (sprintf("%s%d-%d", $prefix, $core, $seq));
    }
}

function update_evid($apps, $app) {
    $old_evid = $app->evid;
    $old_prefix = extract_evid_prefix($app->evid);
    $old_core = extract_evid_core($app->evid);
    $old_seq = extract_evid_seq($app->evid);

    $new_prefix = evid_prefix_for_app($app);

    if ($app->neffa_id) {
        $new_core = neffa_id_to_evid_core($app->neffa_id);
    } else {
        $new_core = intval(sprintf("9999%05d", $app->app_id));
    }

    if ($new_core == $old_core) {
        $new_evid = build_evid($new_prefix, $new_core, $old_seq);
    } else {
        if ($app->neffa_id) {
            $new_seq = next_evid_seq($apps, $app->neffa_id);
        } else {
            $new_seq = 1;
        }
        $new_evid = build_evid($new_prefix, $new_core, $new_seq);
    }

    if (strcmp ($new_evid, $old_evid) != 0) {
        $app->evid = $new_evid;
        query ("update json set evid = ? where app_id = ?",
            array ($new_evid, $app->app_id));
    }
}

/* === evid === */

function get_application ($app_id) {
    $q = query ("select ts, username, val, access_code, fest_year, test_flag,"
                ."   confirmed, evid"
                ." from json"
                ." where app_id = ?"
                ." order by ts",
                $app_id);
    
    $curvals = NULL;
    $patches = array ();
    $access_code = NULL;
    $fest_year = 0;
    $test_flag = 0;
    $confirmed = "";
    $evid = "";
    while (($r = fetch ($q)) != NULL) {
        if (strncmp ($r->val, "{", 1) == 0) {
            $curvals = json_decode ($r->val, TRUE);
            $access_code = $r->access_code;
            $fest_year = intval($r->fest_year);
            $test_flag = intval($r->test_flag);
            $confirmed = trim($r->confirmed);
            $evid = trim($r->evid);
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

    if ($curvals == NULL)
        return (NULL);

    $app = (object)NULL;
    $app->fest_year = $fest_year;
    $app->test_flag = $test_flag;
    $app->access_code = $access_code;

    $app->curvals = $curvals;
    $app->neffa_id = name_to_id ($app->curvals['name']);

    $app->patches = $patches;
    $app->confirmed = $confirmed;
    $app->evid = $evid;

    return ($app);
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
    if ($target_id == "false")
        return 0;
    
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

    global $username;
    if (@$question['admin'] && $username == "")
        return (FALSE);

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
    if (preg_match ('/@example.com/', $args->to_email)) {
        /* skip mail to example.com */
        return;
    }

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

    $mail->setFrom('program@neffa.org', 'NEFFA Applications');
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

function convert_event_title($curvals) {
    switch (@$curvals['app_category']) {
    case "Band":
        return ("playing for a dance");
        break;
    case "Ritual":
        return ("ritual dance");
        break;
    case "Performance":
        return ("dance performance");
        break;
    default:
        return ($curvals['event_title']);
        break;
    }
}

function autoquote($x) {
    if (preg_match('/</', $x))
        return ($x);
    return (h ($x));
}


function populate_template($template_file, $vals) {
    $html = file_get_contents ($template_file);

    preg_match_all ('/\[\[\[([-_A-Za-z0-9 ]+)\]\]\]/', 
        $html, $matches, PREG_OFFSET_CAPTURE | PREG_PATTERN_ORDER);

    $placeholders = array_reverse ($matches[0]);
    $names = array_reverse ($matches[1]);

    for ($idx = 0; $idx < count ($placeholders); $idx++) {
        $place = $placeholders[$idx];
        $name = $names[$idx][0];

        $len = strlen ($place[0]);
        $start = $place[1];
           
        if (isset ($vals[$name])) {
            $before = substr ($html, 0, $start);
            $newval = $vals[$name];
            $after = substr ($html, $start + $len);

            $html = $before . $newval . $after;
        }
    }

    return ($html);
}

function make_cgi_pcode_link($pcode) {
    return sprintf("https://cgi.neffa.org/performer/index.pl"
        ."?P=%s", rawurlencode($pcode));
}


function canonical_evid($evid) {
    $key = strtolower($evid);
    if (! preg_match('/[a-z]$/', $key))
        $key .= "a";
    return($key);
}

function evid_to_app($evid) {
    global $evid_map;
    return (@$evid_map[canonical_evid($evid)]);
}

// might return empty string
function get_email($perf) {
    if (@$perf->best_email)
        return ($perf->best_email);

    $pdb->best_email = trim(@$pref->apps[0]->curvals['email']);
    if ($app_email == "")
        $pdb->best_email = $perf->pdb_email;

    $emails = [];
    $emails[strtolower($perf->pdb_email)] = 1;
    foreach ($perf->apps as $app) {
        $emails[strtolower(trim($app->curvals['email']))] = 1;
    }
    if (count($emails) > 1) {
        $msg = "<div>\n";
        $msg .= sprintf ("<div>performer %d has multiple emails</div>\n",
            $perf->number);
        if ($perf->pdb_email) {
            $msg .= sprintf ("<div>from performer db: %s</div>\n",
                h($perf->pdb_email));
        } else {
            $msg .= sprintf("<div>not set in performer db</div>\n");
        }
        foreach ($perf->apps as $app) {
            $msg .= sprintf ("<div>%s in %s</div>\n",
                h($app->curvals['email']),
                h($app->curvals['event_title']));
        }

        $msg .= sprintf ("<div>used: %s</div>\n", h($perf->best_email));
        $msg .= "</div>\n";
        $info[] = $msg;
    }

    return ($perf->best_email);
}

function read_notify_info() {
    global $errs, $info, $stray_secondaries;
    $errs = [];
    $info = [];
    $stray_secondaries = [];

    global $pdb_params;
    $pdb = get_db ("neffa_pdb", $pdb_params);

    global $name_id_to_pcode;
    global $pcode_to_name_id;
    $name_id_to_pcode = [];
    $pcode_to_name_id = [];
    $q = query ("select id, pcode from pcodes");
    while (($r = fetch ($q)) != NULL) {
        $name_id = intval($r->id);
        $pcode = trim($r->pcode);
        $name_id_to_pcode[$name_id] = $pcode;
        $pcode_to_name_id[$pcode] = $name_id;
    }

    $q = query_db ($pdb,
        "select groupNumber, memberNumber"
        ." from annotated_members"
        ." where type = 'C'"
        ." order by groupNumber");
    global $group_to_group_leader, $group_leader_to_groups;

    $group_to_group_leader = [];
    $group_leader_to_groups = [];
    while (($r = fetch ($q)) != NULL) {
        $group_number = intval($r->groupNumber);
        $leader_number = intval($r->memberNumber);
        if (intval(@$group_to_group_leader[$group_number]) > 0) {
            $pcode = @$name_id_to_pcode[$group_number];
            if ($pcode) {
                $t = make_cgi_pcode_link($pcode);
            } else {
                $t = "";
            }

            global $errs;
            $errs[] = sprintf ("group %s has more than one leader", 
                mklink_nw($group_number, $t));
        } else {
            $group_to_group_leader[$group_number] = $leader_number;
            if (! isset ($group_leader_to_groups[$leader_number]))
                $group_leader_to_groups[$leader_number] = [];
            $group_leader_to_groups[$leader_number][] = $group_number;
        }
    }

    // columns
    // 1 evid
    // 2 title
    // 3 description
    // 4 codes like D S, G S, T B N S
    // 5 F, U, or S
    // 6 room
    // 7 time HHMM
    // 8 to end: performer id's
    $f = fopen("webgrid.tsv", "r");
    global $webgrid;
    $webgrid = [];
    while (($row = fgets ($f)) != NULL) {
        $cols = explode("\t", $row);
        $elt = (object)NULL;
        $elt->evid = trim($cols[0]);
        $elt->title = trim($cols[1]);
        $elt->desc = trim($cols[2]);
        $elt->codes = trim($cols[3]);
        $elt->day = trim($cols[4]); // F U S for fri sat sun
        $elt->room = trim($cols[5]);
        $elt->time = trim($cols[6]); // HHMM
        $elt->name_ids = [];
        for ($idx = 7; $idx < count($cols); $idx++) {
            $name_id = intval(@$cols[$idx]);
            if ($name_id)
                $elt->name_ids[] = intval($cols[$idx]);
        }
        $webgrid[] = $elt;
    }

    global $performers;
    $performers = array();
    $q = query_db ($pdb,
        "select number, performerName, email"
        ." from performers");

    while (($r = fetch($q)) != NULL) {
        $perf = (object)NULL;
        $perf->number = intval($r->number); // known in apply as name_id 
        $perf->name = trim($r->performerName);
        $perf->pdb_email = trim($r->email);
        $perf->apps = [];
        $performers[$perf->number] = $perf;
    }

    global $view_year;
    $apps = get_applications($view_year);

    global $evid_map;
    $evid_map = array();
    foreach ($apps as $app) {
        $evid_map[canonical_evid($app->evid)] = $app;
    }

    foreach ($apps as $app) {
        $name_id = name_to_id($app->curvals['name']);
        if (($perf = @$performers[$name_id]) != NULL) {
            $perf->apps[] = $app;
        }
    }

    global $notify, $notify_by_notify_id, $notify_by_name_id;

    $notify = array();
    $nofify_by_name_id = array();
    $notify_by_notify_id = array();
    $q = query ("select notify_id, name_id, email"
        ." from notify"
        ." where fest_year = ?"
        ." order by notify_id",
        $view_year);
    while (($r = fetch ($q)) != NULL) {
        $elt = (object)NULL;
        $elt->notify_id = intval($r->notify_id);
        $elt->name_id = intval($r->name_id);
        $elt->email = trim($r->email);

        $notify[] = $elt;
        $notify_by_notify_id[$elt->notify_id] = $elt;
        $notify_by_name_id[$elt->name_id] = $elt;
    }
}

function walk_grid() {
    global $webgrid, $group_to_group_leader;
    foreach ($webgrid as $webgrid_elt) {
        $success = [];
        $fails = [];
        foreach ($webgrid_elt->name_ids as $name_id) {
            $leader_id = @$group_to_group_leader[$name_id];
            if ($leader_id) {
                if (we_need_to_notify("leader", $webgrid_elt, $leader_id) < 0) {
                    $fails[] = $leader_id;
                } else {
                    $success[] = $leader_id;
                }
            } else {
                if (we_need_to_notify("individual", $webgrid_elt, $name_id) < 0) {
                    $fails[] = $name_id;
                } else {
                    $success[] = $name_id;
                }
            }
        }
        
        global $performers, $name_id_to_pcode;

        if (count($fails) > 0) {
            if (count($success) > 0) {
                $msg = sprintf("<div>event %s</div>\n",
                    make_evid_link($webgrid_elt->evid));
                $msg .= "<ul class='notify_err'>\n";
                $msg .= "<li>";
                $msg .= "notified ";
                foreach ($success as $name_id) {
                    $p = @$performers[$name_id];
                    $pcode = @$name_id_to_pcode[$name_id];
                    if ($p && $pcode) {
                        $t = make_cgi_pcode_link($pcode);
                        $msg .= sprintf(" %s", mklink_nw($p->name, $t));
                    } else {
                        $msg .= sprintf(" %d", $name_id);
                    }
                }
                $msg .= "</li>\n";
                $msg .= "<li>";
                $msg .= "skipped ";
                foreach ($fails as $name_id) {
                    $p = @$performers[$name_id];
                    $pcode = @$name_id_to_pcode[$name_id];
                    if ($p && $pcode) {
                        $t = make_cgi_pcode_link($pcode);
                        $msg .= sprintf(" %s", mklink_nw($p->name, $t));
                    } else {
                        $msg .= sprintf(" %d", $name_id);
                    }
                }
                $msg .= "</li>\n";
                $msg .= "</ul>\n";
                global $stray_secondaries;
                $stray_secondaries[] = $msg;
            } else {
                $msg = sprintf ("<div>can't find email for event %s</div>",
                    make_evid_link($webgrid_elt->evid));
                $msg .= "<li>";
                $msg .= "skipped ";
                foreach ($fails as $name_id) {
                    $p = @$performers[$name_id];
                    $pcode = @$name_id_to_pcode[$name_id];
                    if ($p && $pcode) {
                        $t = make_cgi_pcode_link($pcode);
                        $msg .= sprintf(" %s", mklink_nw($p->name, $t));
                    } else {
                        $msg .= sprintf(" %d", $name_id);
                    }
                }
                $msg .= "</li>\n";
                $msg .= "</ul>\n";

                global $errs;
                $errs[] = $msg;
            }
        }
    }
    do_commits();
}

if (! get_option ("flat") && ! @$cli_mode) {
    require (router());
    /* NOTREACHED */
}

