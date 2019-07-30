<?php

$cli_mode = 1;
$cfg = json_decode (file_get_contents ("./cfg.json"), TRUE);
$_SERVER['PSITE_PHP'] = sprintf ("%s/psite.php", $cfg['psite_dir']);
$_SERVER['APP_ROOT'] = ".";

require_once ("app.php");

