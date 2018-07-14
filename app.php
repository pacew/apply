<?php

require_once ($_SERVER['APP_ROOT'] . "/common.php");

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

function get_perf_name ($perf_id) {
    global $index;
    get_neffa_index ();
    return (@$index['perfs'][$perf_id]['name']);
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
    $questions = get_questions ();
    
    $orig_vals = array ();
    $override_vals = NULL;
    $cur_vals = array ();

    $colnames = array ();
    
    foreach ($questions as $question) {
        $id = $question['id'];
        $colnames[] = $id;
    }

    $stmt = sprintf ("select perf_id, perf_name, %s"
                     ." from applications"
                     ." where app_id = ?",
                     implode (',', $colnames));
    $q = query ($stmt, $app_id);

    if (($r = fetch ($q)) == NULL)
        return (NULL);

    $orig_vals['perf_id'] = trim ($r->perf_id);
    $orig_vals['perf_name'] = trim ($r->perf_name);

    foreach ($questions as $question) {
        $id = $question['id'];
        $orig_vals[$id] = trim ($r->$id);
    }

    $stmt = sprintf ("select perf_id, perf_name, %s"
                     ." from overrides"
                     ." where app_id = ?",
                     implode (',', $colnames));
    $q = query ($stmt, $app_id);

    if (($r = fetch ($q)) != NULL) {
        $override_vals = array ();
        $override_vals['perf_id'] = trim ($r->perf_id);
        $override_vals['perf_name'] = trim ($r->perf_name);

        foreach ($questions as $question) {
            $id = $question['id'];
            $override_vals[$id] = trim ($r->$id);
        }
    }
    
    if (($cur_vals['perf_id'] = @$override_vals['perf_id']) == "")
        $cur_vals['perf_id'] = $orig_vals['perf_id'];

    if (($cur_vals['perf_name'] = @$override_vals['perf_name']) == "")
        $cur_vals['perf_name'] = $orig_vals['perf_name'];
    
    foreach ($questions as $question) {
        $id = $question['id'];
        if (($cur_vals[$id] = @$override_vals[$id]) == "")
            $cur_vals[$id] = $orig_vals[$id];
    }

    $ret = (object)NULL;
    $ret->orig_vals = $orig_vals;
    $ret->override_vals = $override_vals;
    $ret->cur_vals =$cur_vals;
        
    return ($ret);
}
