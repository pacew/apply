<?php

function get_neffa_index () {
    global $index;
    $filename = sprintf ("%s/neffa_idx.json", $_SERVER['APP_ROOT']);
    $index = json_decode (file_get_contents ($filename), TRUE);
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
    if (! isset ($index))
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
    $limit = 100;
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
