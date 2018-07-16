<?php

require_once ($_SERVER['APP_ROOT'] . "/common.php");
require_once ($_SERVER['APP_ROOT'] . "/JsonPatch.php");

$title_html = "NEFFA Performer Application 2019";

$index = NULL;

function get_neffa_index () {
    global $index;
    if ($index == NULL) {
        $filename = "/tmp/neffa_idx.json";
        $index = json_decode (file_get_contents ($filename), TRUE);
    }
    return ($index);
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
        if ($p->pct < 50)
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

function get_questions () {
    global $questions;
    if ($questions == NULL) {
        $filename = sprintf ("%s/questions.json", $_SERVER['APP_ROOT']);
        $questions = json_decode (file_get_contents ($filename), TRUE);
        if (json_last_error ()) {
            $msg = json_last_error_msg ();
            fatal ("syntax error in questions.json - try jq: " . $msg);
        }
    }
    return ($questions);
}

function get_application ($app_id) {
    $q = query ("select ts, username, val"
                ." from json"
                ." where app_id = ?"
                ." order by ts",
                $app_id);
    
    $curvals = array ();
    $patches = array ();
    while (($r = fetch ($q)) != NULL) {
        if (strncmp ($r->val, "{", 1) == 0) {
            $curvals = json_decode ($r->val, TRUE);
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
    $application->curvals = $curvals;
    $application->patches = $patches;
    return ($application);
}

