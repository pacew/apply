<?php

# performer is directed here to update their info

require_once("app.php");

$arg_pcode = trim(@$_REQUEST['pcode']);
$arg_cancel = intval(@$_REQUEST['cancel']);

pstart();

read_notify_info();

if (($name_id = @$pcode_to_name_id[$arg_pcode]) == 0) {
    $body .= "<div>pcode not found</div>\n";
    pfinish();
}

if (($perf = @$performers[$name_id]) == NULL) {
    $body .= "<div>performer not found</div>\n";
    pfinish();
}

$msg = "";

$title_html = sprintf("Welcome to NEFFA %d Performer Confirmation!",
    $submit_year);

if ($username) {
    $body .= "<div class='admin_box'>\n";
    $body .= mklink ("main notify page", "notify.php");
    if (($elt = @$notify_by_name_id[$name_id]) != NULL) {
        $body .= " | ";
        $t = sprintf ("notify.php?notify_id=%d", $elt->notify_id);
        $body .= mklink ("perfomer notify page", $t);
    }
    $body .= "</div>\n";
}




$body .= sprintf("<p>Performer: %s &lt;%s&gt;</p>\n", 
    h($perf->name), h($perf->pdb_email));

if ($msg == "") {
    $body .= "[internal error - no events found]";
} else {
    $body .= "<p>You are associated with these events which"
          ." have been scheduled:</p>\n";
    $body .= $msg;
}



$pcode_link = make_cgi_pcode_link($arg_pcode);

$body .= "<p>TODO: webgrid events this person is responsible for</p>\n";

if ($arg_cancel == 1) {
    $body .= "<p>We are glad you applied, and sorry your plans have"
          ." changed so that you will be unable to join us this year!"
          ." Please consider joining our email list"
          ." The NEFFA Loop to receive Festival updates including"
          ." future application deadlines.</p>\n";
    
    $body .= "<form action='response.php'>\n";
    $body .= sprintf ("<input type='hidden' name='pcode' value='%s' />\n",
        rawurlencode($arg_pcode));
    $body .= "<input type='hidden' name='cancel' value='2' />\n";
    $body .= "<button type='submit'>"
          ." click here to confirm your cancellation"
          ."</button>\n";
    $body .= "</form>\n";

    $body .= "<div>\n";
    $t = sprintf ("response.php?pcode=%s", rawurlencode($arg_pcode));
    $body .= mklink ("go back to the main confirm page", $t);
    $body .= "</div>\n";

    pfinish();
}

if ($arg_cancel == 2) {
    $body .= "<div>\n";
    $body .= "We have recorded your request to cancel.  It may take a few"
          ." days for all of our systems to be updated.  Please feel free"
          ." to contact <a href=mailto:program@neffa.org>program@neffa.org</a>"
          ." with any further concerns.";
    $body .= "</div>\n";
    pfinish();
}


$body .= sprintf ("<p>Are you still available to perform at NEFFA %d?</p>\n",
    $submit_year);
$body .= "<p>Please click one of the following</p>\n";

$body .= "<div>\n";
$body .= "<form action='https://cgi.neffa.org/performer/index.pl'>\n";
$body .= sprintf ("<input type='hidden' name='P' value='%s' />\n",
    rawurlencode($arg_pcode));
$body .= "<button type='submit'>"
      ." YES - I want to continue the confirmation"
      ." process for one or more events"
      ."</button>\n";
$body .= "</form>\n";
$body .= "</div>\n";

$body .= "<div>\n";
$body .= "<form action='response.php'>\n";
$body .= sprintf ("<input type='hidden' name='pcode' value='%s' />\n",
    rawurlencode($arg_pcode));
$body .= "<input type='hidden' name='cancel' value='1' />\n";
$body .= "<button type='submit'>"
      ." NO - I need to cancel <strong>all</strong> my/our events"
      ." as I am/we are unable to attend"
      ."</button>\n";
$body .= "</form>\n";
$body .= "</div>\n";

pfinish();
