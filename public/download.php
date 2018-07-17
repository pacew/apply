<?php

require_once ($_SERVER['APP_ROOT'] . "/app.php");

pstart ();

$arg_view_data = intval (@$_REQUEST['view_data']);
$arg_view_json = intval (@$_REQUEST['view_json']);
$arg_download_json = intval (@$_REQUEST['download_json']);
$arg_view_csv = intval (@$_REQUEST['view_csv']);
$arg_download_csv = intval (@$_REQUEST['download_csv']);

$apps = array ();

$q = query ("select app_id, val"
            ." from json"
            ." order by app_id, ts");
while (($r = fetch ($q)) != NULL) {
    $app_id = intval ($r->app_id);
    if (strncmp ($r->val, "{", 1) == 0) {
        $apps[$app_id] = json_decode ($r->val, TRUE);
    } else {
        $patch = json_decode ($r->val, TRUE);
        if (isset ($apps[$app_id])) {
            $curvals = @$apps[$app_id];
            $curvals = mikemccabe\JsonPatch\JsonPatch::patch($curvals, $patch);
            $apps[$app_id] = $curvals;
        }
    }
}

$body .= "<div>\n";
$body .= mklink ("home", "/");
$body .= " | ";
$body .= mklink ("[admin]", "admin.php");
$body .= "</div>\n";

if ($arg_view_data) {
    foreach ($apps as $app) {
        $body .= "<div class='display_data'>\n";
        $body .= sprintf ("<h2>%d</h2>\n", $app['app_id']);
        foreach ($app as $key => $val) {
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

$questions = get_questions ();

$outf = tmpfile ();

$colnames = array ();
$colnames[] = "app_id";
foreach ($questions as $question) {
    $colnames[] = $question['id'];
}
fputcsv ($outf, $colnames);

foreach ($apps as $app) {
    $cols = array ();
    foreach ($colnames as $question_id) {
        $val = @$app[$question_id];
        $col = "";
        if (! is_array ($val)) {
            $col = trim ($val);
        } else {
            $elts = array ();
            if (isset ($val[0])) {
                foreach ($val as $elt) {
                    $elt = preg_replace ('/\|=/', "~", $elt);
                    $elts[] = $elt;
                }
            } else {
                foreach ($val as $key => $elt) {
                    $key = preg_replace ("/\|=/", "~", $key);
                    $elt = preg_replace ("/\|=/", "~", $elt);
                    $elts[] = $key . "=" . $elt;
                }
            }
            $col = implode ("|", $elts);
        }
        $cols[] = $col;
    }
    fputcsv ($outf, $cols);
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

