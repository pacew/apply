<?php

$anon_ok = 1;
require_once ($_SERVER['APP_ROOT'] . "/app.php");

@ob_end_clean();
header ("HTTP/1.0 404 Not found");
echo ("not found");
exit();


