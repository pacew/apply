<?php

require_once ($_SERVER['APP_ROOT'] . "/common.php");
require_once ($_SERVER['APP_ROOT'] . "/app.php");

pstart ();

$arg_name = trim (@$_REQUEST['name']);

$index = NULL;

function make_index () {
    global $index;

    $db = get_db ("neffa");

    $index = (object)NULL;

    $data = array ();
    $lcwords = array ();
    $by_soundex = array ();

    $q = query_db ($db,
                   "select number, p.\"performerName\""
                   ." from performers p");
    while (($r = fetch ($q)) != NULL) {
        $perf_id = intval ($r->number);
        $name = $r->performerName;

        $data[$perf_id] = $name;

        $words = preg_split ('/[\s,]+/', strtolower ($name));
        $lcwords[$perf_id] = $words;

        foreach ($words as $word) {
            $s = soundex ($word);
            if (! isset ($by_soundex[$s]))
                $by_soundex[$s] = array ();
            $by_soundex[$s][] = $perf_id;
        }
    }

    $maxlen = 0;
    foreach ($by_soundex as $s => $perfs) {
        sort ($by_soundex[$s], SORT_NUMERIC);

        $n = count ($by_soundex[$s]);
        if ($n > $maxlen)
            $maxlen = $n;
    }

    $rareness = array ();
    foreach ($by_soundex as $s => $perf_ids) {
        $n = count ($by_soundex[$s]);
        $rareness[$s] = 1 - ($n / $maxlen);
    }

    $index = (object)NULL;
    $index->data = $data;
    $index->lcwords = $lcwords;
    $index->by_soundex = $by_soundex;
    $index->rareness = $rareness;
}



make_index ();
file_put_contents ("/tmp/neffa_idx.json",
                   json_encode ($index, JSON_PRETTY_PRINT));



pfinish ();
