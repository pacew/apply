<?php

require_once ($_SERVER['APP_ROOT'] . "/common.php");

pstart ();

$title_html = "Store";

$app_id = get_seq ();

query ("insert into apps (app_id, name)"
       ." values (?, ?)",
       array ($app_id,
              $_REQUEST['name']));

redirect ("thanks.php");

