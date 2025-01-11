<?php

require_once("app.php");

pstart();

$arg_first_name = rawurldecode(@$_REQUEST['first_name']);
$arg_text = rawurldecode(@$_REQUEST['text']);

$vals['first_name'] = $arg_first_name;
$vals['rejection_reason'] = $arg_text;

$body .= "<div class='notify_email'>\n";

$subject = "Your NEFFA application(s)";
$body .= sprintf ("<p>Subject: %s</p>\n", h($subject));

// https://docs.google.com/document/d/1u1PRH3-UYUEPgtsNS6IrLPzPlRfGLcv-Koc4puSzj84
$body .= populate_template("rejection.html", $vals);
$body .= "</div>\n";



pfinish();
