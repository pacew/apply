<?php

$anon_ok = 1;

require_once ($_SERVER['APP_ROOT'] . "/app.php");

pstart ();

putsess ("username2", NULL);
putsess ("admin", NULL);

redirect ("/");
