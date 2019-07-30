<?php

require_once("app.php");

$anon_ok = 1;

pstart ();

putsess ("username2", NULL);
putsess ("admin", NULL);

redirect ("/");
