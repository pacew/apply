<?php

require_once ($_SERVER['APP_ROOT'] . "/common.php");

$title_html = "NEFFA Performer Application 2019";

$index = NULL;

function get_neffa_index () {
    global $index;
    if ($index == NULL) {
        $filename = sprintf ("%s/neffa_idx.json", $_SERVER['APP_ROOT']);
        $index = json_decode (file_get_contents ($filename), TRUE);
    }
}

function get_perf_name ($perf_id) {
    global $index;
    get_neffa_index ();
    return (@$index['data'][$perf_id]);
}

function records_cmp ($a, $b) {
    if ($a->score < $b->score)
        return (1);
    else if ($a->score > $b->score)
        return (-1);
    return (0);
}
    
function performer_lookup ($str) {
    global $index;
    
    get_neffa_index ();

    $words = preg_split ('/[\s,]/', strtolower ($str));
    $poss = array ();
    foreach ($words as $word) {
        $s = soundex ($word);
        if (($perf_ids = @$index['by_soundex'][$s]) == NULL)
            continue;
        $rare = $index['rareness'][$s];
        foreach ($perf_ids as $perf_id) {
            if (($p = @$poss[$perf_id]) == NULL) {
                $p = (object)NULL;
                $p->perf_id = $perf_id;
                $p->score = 0;
                $poss[$perf_id] = $p;
            }
            $orig_words = $index['lcwords'][$perf_id];
            $best = 1000000;
            foreach ($orig_words as $orig_word) {
                $val = levenshtein ($orig_word, $word);
                if ($val < $best)
                    $best = $val;
            }
            $best++;
            
            $p->score += 10 + $rare * (1 / $best);
        }
    }

    usort ($poss, 'records_cmp');

    $ret = array ();
    $n = count ($poss);
    $limit = 25;
    if ($n > $limit)
        $n = $limit;
    for ($i = 0; $i < $n; $i++) {
        $p = $poss[$i];
        $r = (object)NULL;
        $r->perf_id = $p->perf_id;
        $r->score = $p->score;
        $r->name = $index['data'][$p->perf_id];
        
        $ret[] = $r;
    }

    return ($ret);
}

function make_lookup_form ($name = "") {
    $ret = "";
    
    $ret .= "<div>\n";
    $ret .= "<form action='lookup.php'>\n";
    
    $ret .= "Name of group of individual performer: ";
    $ret .= sprintf ("<input type='text' size='50' name='name' value='%s' />\n",
                     h($name));
    
    $ret .= "<input type='submit' value='Submit' />\n";
    
    $ret .= "</form>\n";
    $ret .= "</div>\n";

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
