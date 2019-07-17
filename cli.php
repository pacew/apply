<?php

$cli_mode = 1;
$cfg = json_decode (file_get_contents ("./cfg.json"), TRUE);
$_SERVER['PSITE_PHP'] = $cfg['psite_php'];
$_SERVER['APP_ROOT'] = ".";

require_once ("app.php");

