<?php

$anon_ok = 1;

require_once ($_SERVER['APP_ROOT'] . "/app.php");

pstart ();

$arg_username = trim (@$_REQUEST['username']);
$arg_password = trim (@$_REQUEST['password']);

if ($arg_username) {
    if ($arg_password == "preview") {
        putsess ("username2", $arg_username);
        putsess ("admin", 1);
        redirect ("admin.php");
    }
    $body .= "<p>invalid login</p>";
}
    

$body .= "<form action='login.php' method='post'>\n";
$body .= "<table class='twocol'>\n";
$body .= "<tr><th>Username</th><td>";
$body .= "<input type='text' name='username' />\n";
$body .= "</td></tr>\n";
$body .= "<tr><th>Password</th><td>";
$body .= "<input type='password' name='password' />\n";
$body .= "</td></tr>\n";
$body .= "<tr><th></th><td><input type='submit' value='Login' /></td></tr>\n";
$body .= "</table>\n";

$body .= "</form>\n";

pfinish ();

