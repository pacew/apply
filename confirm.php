<?php

require_once("app.php");

$arg_app_id = trim (@$_REQUEST['app_id']);

pstart ();

$title_html = "Confirm";

$app = get_application ($arg_app_id);
if (count($app->curvals) == 0)
    fatal ("can't find application");
$curvals = $app->curvals;

if (($neffa_id = name_to_id ($curvals['name'])) == 0) {
    $body .= "can't find neffa_id";
    pfinish ();
}

$q = query ("select pcode"
            ." from pcodes"
            ." where id = ?",
            $neffa_id);
if (($r = fetch ($q)) == NULL) {
    $body .= "can't find pcode";
    pfinish ();
}

$curvals['pcode'] = $r->pcode;

$html = file_get_contents ("confirm.html");

preg_match_all ('/\[\[\[([-_A-Za-z0-9 ]+)\]\]\]/', 
                $html, $matches, PREG_OFFSET_CAPTURE | PREG_PATTERN_ORDER);

$placeholders = array_reverse ($matches[0]);
$names = array_reverse ($matches[1]);

for ($idx = 0; $idx < count ($placeholders); $idx++) {
    $place = $placeholders[$idx];
    $name = $names[$idx][0];

    $len = strlen ($place[0]);
    $start = $place[1];
           
    if (isset ($curvals[$name])) {
        $before = substr ($html, 0, $start);
        $newval = $curvals[$name];
        $after = substr ($html, $start + $len);

        $html = $before . $newval . $after;
    }
}

$text = strip_tags ($html);

$to = $curvals['email'];
$subject = "NEFFA access code";


$body .= sprintf ("<p>To: %s</p>\n", h($to));
$body .= sprintf ("<p>Subject: %s</p>\n", h($subject));

$body .= $html;

if (0) {
    $body .= "<pre>\n";
    $body .= $text;
    $body .= "</pre>\n";
}



pfinish ();

