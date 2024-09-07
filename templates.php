<?php

require_once("app.php");

$arg_t = @$_REQUEST['t'];

pstart();

$body .= "<div>";
$body .= mklink("preface", "templates.php?t=preface");
$body .= " | ";
$body .= mklink("confirm", "templates.php?t=confirm");
$body .= "</div>\n";
$body .= "<hr/>\n";

if ($arg_t == "preface") {
    $body .= sprintf("<h1>%s</h1>\n", h($arg_t));
    $body .= file_get_contents("preface.html");
    pfinish();
}

if ($arg_t == "confirm") {
    $body .= sprintf("<h1>%s</h1>\n", h($arg_t));
    $body .= file_get_contents("confirm.html");
    pfinish();
}



pfinish();
