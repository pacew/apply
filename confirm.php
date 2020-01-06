<?php

require_once("app.php");

$arg_app_id = trim (@$_REQUEST['app_id']);
$arg_send_email = intval (@$_REQUEST['send_email']);

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
$curvals['festival_year'] = $cur_year;

$curvals['first_name'] = preg_replace ('/^[^,]*,/', "", $curvals['name']);
$curvals['title_for_confirm'] = convert_event_title($curvals);


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


$args = (object)NULL;
$args->to_email = trim (strtolower ($curvals['email']));
$args->subject = "NEFFA access code";
$args->body_html = $html;
$args->body_text = strip_tags ($html);

if ($arg_send_email) {
    send_email ($args);

    $body .= "<p>Sent</p>\n";
    
    query ("update json set confirmed = ? where app_id = ?", 
           array (strftime ("%Y-%m-%d %H:%M:%S", time()),
                  $arg_app_id));

    $t = sprintf ("index.php?app_id=%d", $arg_app_id);
    $body .= mklink ("back to application", $t);
    pfinish ();
}

$body .= "<form action='confirm.php'>\n";
$body .= "<input type='hidden' name='send_email' value='1' />\n";
$body .= sprintf ("<input type='hidden' name='app_id' value='%d' />\n", 
                  $arg_app_id);
$body .= "<button type='submit' onclick='return false' style='display:none'>"
      ."</button>\n";

$body .= sprintf ("<input type='submit' value='Send this email to %s' />\n",
                  h($args->to_email));

$t = sprintf ("index.php?app_id=%d", $arg_app_id);
$body .= mklink ("[cancel]", $t);

$body .= "</form>\n";

$body .= sprintf ("<p>To: %s</p>\n", h($args->to_email));
$body .= sprintf ("<p>Subject: %s</p>\n", h($args->subject));

$body .= $args->body_html;

$body .= "<hr/>\n"; 

$body .= "<pre>\n";
$body .= h($args->body_text);
$body .= "</pre>\n";




pfinish ();

