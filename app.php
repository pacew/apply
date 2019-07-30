<?php

// prevent Warning: preg_match(): JIT compilation failed: no more memory in ...
ini_set ("pcre.jit", 0); 

require_once ($_SERVER['PSITE_PHP']);
require_once ($_SERVER['APP_ROOT'] . "/JsonPatch.php");

switch ($cfg['conf_key']) {
case "pace":
    $show_test_data = 1;
    $first_prod_app_id = 105;
    break;
case "aws":
    $show_test_data = 0;
    $first_prod_app_id = 116;
    break;
default:
    $show_test_data = 1;
    $first_prod_app_id = 0;
    break;
}

$title_html = "NEFFA Performer Application 2019";
$username = "";

function pstart () {
    psite_session ();

    ini_set ("display_errors", "1");

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

    $pg .= "<h1 class='banner_title'>2019 Performer Application</h1>\n";

    echo ($pg);
    $pg = "";

    global $body;
    $pg .= $body;

    $pg .= "</div>\n";

    $pg .= sprintf ("<script src='scripts.js?c=%s.js'></script>\n",
                    get_cache_defeater ());

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

function get_application ($app_id) {
    $q = query ("select ts, username, val, access_code"
                ." from json"
                ." where app_id = ?"
                ." order by ts",
                $app_id);
    
    $curvals = array ();
    $patches = array ();
    $access_code = NULL;
    while (($r = fetch ($q)) != NULL) {
        if (strncmp ($r->val, "{", 1) == 0) {
            $curvals = json_decode ($r->val, TRUE);
            $access_code = $r->access_code;
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

if (! get_option ("flat") && ! @$cli_mode) {
    require (router());
    /* NOTREACHED */
}

