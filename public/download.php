<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

pstart ();

$arg_view_data = intval (@$_REQUEST['view_data']);
$arg_view_json = intval (@$_REQUEST['view_json']);
$arg_download_json = intval (@$_REQUEST['download_json']);
$arg_view_csv = intval (@$_REQUEST['view_csv']);
$arg_download_csv = intval (@$_REQUEST['download_csv']);
$arg_app_id = intval (@$_REQUEST['app_id']);

$apps = array ();

$q = query ("select app_id, val"
            ." from json"
            ." order by app_id, ts");
while (($r = fetch ($q)) != NULL) {
    $app_id = intval ($r->app_id);

    if (! $show_test_data && $app_id < $first_prod_app_id)
        continue;

    if (strncmp ($r->val, "{", 1) == 0) {
        $app = (object)NULL;
        $app->app_id = $app_id;
        $app->curvals = json_decode ($r->val, TRUE);
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

$evid_info = array ();

$q = query ("select max(evid_core) as evid_core from evid_info");
$r = fetch ($q);
$max_evid_core = intval ($r->evid_core);
if ($max_evid_core < 10)
    $max_evid_core = 10;

function get_evid_info ($key, $evid_core) {
    global $evid_info, $max_evid_core;

    if (($ei = @$evid_info[$key]) != NULL)
        return ($ei);
    
    if ($evid_core == 0) {
        $max_evid_core++;
        $evid_core = $max_evid_core;

        query (
            "insert into evid_info (key, evid_core) values (?,?)",
            array ($key, $evid_core));
        do_commits ();
    }

    $ei = (object)NULL;
    $ei->key = $key;
    $ei->evid_core = $evid_core;
    $ei->seq = 0;
    $evid_info[$key] = $ei;

    return ($ei);
}

$q = query ("select key, evid_core from evid_info");
while (($r = fetch ($q)) != NULL) {
    $key = trim ($r->key);
    $evid_core = intval ($r->evid_core);
    if ($evid_core > $max_evid_core)
        $max_evid_core = $evid_core;
    get_evid_info ($key, $evid_core);
}

foreach ($apps as $app) {
    if (($neffa_id = name_to_id ($app->curvals['name'])) != 0) {
        $key = $neffa_id;
    } else if (($email = @$app->curvals['email']) != "") {
        $key = $email;
    } else {
        $key = sprintf ("oops%d", $app->app_id);
    }

    $ei = get_evid_info ($key, 0);
    $ei->seq++;

    $app->ei = $ei;
    $app->evid_seq = $ei->seq;
}

foreach ($apps as $app) {
    $curvals = $app->curvals;
    if (
        $curvals['app_category'] == "Band" 
        || $curvals['app_category'] == "Band_Solo" 
        || $curvals['app_category'] == "Caller") {
        switch (@$curvals['dance_style']) {
        case "American": $prefix = "T"; break;
        case "English": $prefix = "P"; break;
        case "Couples": $prefix = "P"; break;
        case "English_Couples": $prefix = "P"; break;
        case "Int_Line": $prefix = "R"; break;
        default: $prefix = "oops"; break;
        }
    } else {
        $prefix = "M";
    }

    if (($ei = @$app->ei) == NULL) {
        $evid_core = 0;
        $suffix = "";
    } else {
        $evid_core = $ei->evid_core;
        if ($ei->seq == 1) {
            $suffix = "";
        } else if ($app->evid_seq <= 26) {
            $suffix = chr (ord ("a") - 1 + $app->evid_seq);
        } else {
            $suffix = sprintf ("-%d", $app->evid_seq);
        }
    }

    $app->evid = $prefix . $evid_core . $suffix;
}    

$body .= "<div>\n";
$body .= mklink ("home", "/");
$body .= " | ";
$body .= mklink ("[admin]", "admin.php");
$body .= "</div>\n";

if ($arg_view_data) {
    foreach ($apps as $app) {
        $curvals = $app->curvals;
        $body .= "<div class='display_data'>\n";
        $body .= sprintf ("<h2>%d</h2>\n", $app->app_id);
        $body .= sprintf ("evid=<strong>%s</strong> ", $app->evid);
        foreach ($curvals as $key => $val) {
            if (is_array ($val)) {
                if (count ($val) > 0) {
                    $body .= sprintf ("%s=", h($key));
                    if (isset ($val[0])) {
                        $body .= "[";
                        foreach ($val as $elt) {
                            if (trim ($elt) != "")
                                $body .= sprintf ("<strong>%s</strong>; ", 
                                                  h($elt));
                        }
                        $body .= "]";
                    } else {
                        $body .= "[";
                        foreach ($val as $key => $elt) {
                            if (trim ($elt) != "")
                                $body .= sprintf ("<strong>%s=%s</strong>; ", 
                                                  h($key), h($elt));
                        }
                        $body .= "]";
                    }
                }
            } else {
                if (trim ($val) != "") {
                    $body .= sprintf ("%s=", h($key));
                    $body .= sprintf ("<strong>%s</strong>", h($val));
                }
            }
            $body .= " ";
        }
        $body .= "</div>\n";
    }
    pfinish ();
}

if ($arg_view_json) {
    $body .= h(json_encode ($apps, JSON_PRETTY_PRINT));
    pfinish ();
}

if ($arg_download_json) {
    ob_end_clean ();
    header ("Content-Type: application/json");
    header ("Content-Disposition: attachment; filename=applications.json");
    echo (json_encode ($apps));
    exit ();
}

$q = query ("select id, pcode from pcodes");
$pcodes = array ();
while (($r = fetch ($q)) != NULL) {
    $pcodes[$r->id] = $r->pcode;
}

$questions = get_questions ();

$sched_codes = array ();
for ($day = 1; $day <= 3; $day++) {
    for ($hour = 10; $hour <= 22; $hour++) {
        $code = $day * 100000 + $hour * 100;
        if ($day == 1 && $hour < 19)
            $code = 0;
        if ($day == 3 && $hour > 16)
            $code = 0;
        if ($code)
            $sched_codes[] = $code;
    }
}

$room_sound_choices = array (
    "stage_with",
    "stage_without",
    "double_with",
    "double_without",
    "single_mic",
    "single_without" 
);

$csvhdr = array ();
$csvhdr[] = "evid";
$csvhdr[] = "app_id";
foreach ($questions as $question) {
    $question_id = $question['id'];
    $class = @$question['class'];

    if ($class == "lookup_individual" || $class == "lookup_group") {
        $csvhdr[] = $question_id;
        $csvhdr[] = sprintf ("%s_id", $question_id);
        $csvhdr[] = sprintf ("%s_link", $question_id);
        $csvhdr[] = sprintf ("%s_pcode", $question_id);
    } else if ($question_id == "availability") {
        foreach ($sched_codes as $code) {
            $day = floor ($code / 100000);
            $hour = floor ($code / 100) % 100;
            $dnames = array ("", "fri", "sat", "sun");
            if ($hour < 12) {
                $text = sprintf ("%s%da", $dnames[$day], $hour);
            } else if ($hour == 12) {
                $text = sprintf ("%s%dp", $dnames[$day], $hour);
            } else {
                $text = sprintf ("%s%dp", $dnames[$day], $hour - 12);
            }
            $csvhdr[] = $text;
        }
    } else if ($question_id == "room_sound") {
        foreach ($room_sound_choices as $choice)
            $csvhdr[] = $choice;
    } else {
        $csvhdr[] = $question_id;
    }
}

$rows = array ();
foreach ($apps as $app) {
    $curvals = $app->curvals;
    if ($arg_app_id && $arg_app_id != $app->app_id)
        continue;
    $cols = array ();
    $cols[] = $app->evid;
    $cols[] = $app->app_id;
    foreach ($questions as $question) {
        $question_id = $question['id'];
        $class = @$question['class'];
        $val = @$curvals[$question_id];

        if ($class == "lookup_individual" || $class == "lookup_group") {
            $cols[] = $val;
            $neffa_id = name_to_id ($val);
            $cols[] = $neffa_id;
            if ($neffa_id) {
                $link = sprintf (
                    "https://cgi.neffa.org//public/showperf.pl?P=%d",
                    $neffa_id);
                $pcode = @$pcodes[$neffa_id];
            } else {
                $link = "";
                $pcode = "";
            }
            $cols[] = $link;
            $cols[] = $pcode;
        } else if ($question_id == "availability") {
            foreach ($sched_codes as $code) {
                $cols[] = @$val[$code];
            }
        } else if ($question_id == "room_sound") {
            foreach ($room_sound_choices as $choice) {
                $cols[] = @$val[$choice];
            }
        } else {
            $cols[] = $val;
        }
    }
    $rows[] = $cols;
}

if ($arg_app_id) {
    $t = sprintf ("index.php?app_id=%d", $arg_app_id);
    $body .= mklink ("edit", $t);

    foreach ($rows as $row) {
        $body .= "<table class='twocol'>\n";
        for ($idx = 0; $idx < count ($csvhdr); $idx++) {
            $body .= "<tr>\n";
            $body .= sprintf ("<th>%s</th>\n", h($csvhdr[$idx]));
            $body .= "<td>";
            $val = $row[$idx];
            if (preg_match ("|^https?://|", $val)) {
                $body .= mklink_nw ($val, $val);
            } else {
                $body .= h($val);
            }
            $body .= "</td>";
            $body .= "</tr>\n";
        }
        $body .= "</table>\n";
    }
    pfinish ();
}

$outf = tmpfile ();

$colnames = array ();
fputcsv ($outf, $csvhdr);

foreach ($rows as $row) {
    fputcsv ($outf, $row);
}

rewind ($outf);
    
if ($arg_view_csv) {
    $body .= "<pre>\n";
    $body .= h(fread ($outf, 100000));
    $body .= "</pre>\n";
    pfinish ();
}

if ($arg_download_csv) {
    ob_end_clean ();
    header ("Content-Type: application/csv");
    header ("Content-Disposition: attachment; filename=applications.csv");
    fpassthru ($outf);
    exit ();
}
