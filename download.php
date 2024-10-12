<?php

require_once("app.php");

$anon_ok = 1;

pstart ();

$arg_direct_download = trim (@$_REQUEST['direct_download']);
if ($arg_direct_download != "") {

    $key = getvar ("download_key");
    if (strcmp ($arg_direct_download, $key) != 0) {
        echo ("invalid");
        exit ();
    }
} else {
    if ($username == "")
        redirect ("login.php");
}

$arg_view_data = intval (@$_REQUEST['view_data']);
$arg_view_csv = intval (@$_REQUEST['view_csv']);
$arg_download_csv = intval (@$_REQUEST['download_csv']);
$arg_app_id = intval (@$_REQUEST['app_id']);
$arg_year = intval (@$_REQUEST['year']);

$apps = get_applications ($arg_year);

add_evids($apps);

$body .= "<div>\n";
$body .= mklink ("home", "/");
$body .= " | ";
$body .= mklink ("[admin]", "admin.php");
$body .= "</div>\n";

if ($arg_view_data) {
    $body .= sprintf ("<div>year %d test_flag %d</div>\n",
                      $view_year, $view_test_flag);

    foreach ($apps as $app) {
        $curvals = $app->curvals;
        if (@$curvals['do_not_import'] == "Suppress")
            continue;
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

$output_order = array (
    "BLANK",
	"evid",
	"event_title",
	"app_id",
    "name",
	"main_performer",
	"group_name",
    "performer2",
	"busy",
	"app_category",
	"dance_style",
	"specific_dance_style",
	"music_pref",
	"preferred_band",
	"recorded_type",
	"preferred_caller",
	"indoor_ritual",
	"conflicts",
    "availability",
	"event_desc",
	"num_dancers",
	"how_long",
	"showcased",
	"music_begins",
	"lights_on",
	"lights_on_other",
	"lights_off",
	"lights_off_other",
	"lighting_mood",
	"costume_colors",
	"props",
	"enter_from",
	"event_type",
	"level",
    "room_sound",
	"piano",
	"sound_needs",
	"shared",
	"email",
	"phone",
	"url",
	"notes",
    "format",
    "inperson",
    "online",
    "room_size",
	"fms_category"
);

$csvhdr = array ();
foreach ($output_order as $question_id) {
    if ($question_id == "BLANK" 
    || $question_id == "evid" 
    || $question_id == "app_id") {
        $csvhdr[] = $question_id;
        continue;
    }

    $question = @$questions_by_id[$question_id];
    $class = @$question['class'];

    if ($class == "lookup_individual") {
        $csvhdr[] = sprintf ("%s_last", $question_id);
        $csvhdr[] = sprintf ("%s_first", $question_id);
        $csvhdr[] = sprintf ("%s_id", $question_id);
        $csvhdr[] = sprintf ("%s_link", $question_id);
        $csvhdr[] = sprintf ("%s_pcode", $question_id);
    } else if ($class == "lookup_group") {
        $csvhdr[] = sprintf ("%s", $question_id);
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
    if (@$curvals['do_not_import'] == "Suppress")
        continue;

    if ($arg_app_id && $arg_app_id != $app->app_id)
        continue;
    $cols = array ();
    foreach ($output_order as $question_id) {
        if ($question_id == "BLANK") {
            $cols[] = "";
            continue;
        }
        if ($question_id == "evid") {
            $cols[] = $app->evid;
            continue;
        }
        if ($question_id == "app_id") {
            $cols[] = $app->app_id;
            continue;
        }
        
        $question = @$questions_by_id[$question_id];
        $class = @$question['class'];
        $val = @$curvals[$question_id];

        if ($class == "lookup_individual") {
            if (preg_match ('/^([^,]*),(.*)$/', $val, $parts)) {
                $lname = $parts[1];
                $fname = $parts[2];
            } else {
                $lname = $val;
                $fname = "";
            }
            $cols[] = $lname;
            $cols[] = $fname;
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
        } else if ($class == "lookup_group") {
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
        } else if ($question_id == "event_title") {
            $cols[] = convert_event_title ($curvals);
        } else {
            $cols[] = $val;
        }
    }
    $rows[] = $cols;
}

if ($arg_app_id) {
    $t = sprintf ("index.php?app_id=%d", $arg_app_id);
    $body .= mklink ("edit", $t);

    if (! $rows) {
        $body .= "<div>no data found (maybe app is Do Not Import ?)</div>";
        pfinish();
    }

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
    
if ($arg_direct_download) {
    ob_end_clean();
    header ("Content-Type: application/csv");
    header ("Content-Disposition: inline; filename=applications.csv");
    fpassthru ($outf);
    exit();
}



if ($arg_view_csv) {
    $body .= sprintf ("<div>year %d test_flag %d</div>\n",
                      $view_year, $view_test_flag);
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
