<?php

require_once("app.php");

$anon_ok = 1;

@ob_end_clean();
header ("HTTP/1.0 404 Not found");
echo ("not found");
exit();


