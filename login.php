<?php

require_once ("app.php");

$anon_ok = 1;

pstart ();

$arg_username = trim (@$_REQUEST['username']);
$arg_password = trim (@$_REQUEST['password']);
$arg_redirect_to = trim (@$_REQUEST['redirect_to']);

if ($arg_username) {
    if (password_verify ($arg_password, getvar ("admin_passwd"))) {
        putsess ("username2", $arg_username);
        putsess ("admin", 1);

        if ($arg_redirect_to != "")
            redirect($arg_redirect_to);

        redirect ("admin.php");
    }
    $body .= "<p>invalid login</p>";
}
    

$body .= "<form action='login.php' method='post'>\n";
$body .= sprintf ("<input type='hidden' name='redirect_to' value='%s' />\n",
    h($arg_redirect_to));
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

