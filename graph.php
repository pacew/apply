<?php

require_once("app.php");

pstart ();

$arg_png = intval (@$_REQUEST['png']);

if ($arg_png) {
    $filename = sprintf ("%s/rate.png", $cfg['aux_dir']);
    ob_end_clean();
    header ("Content-Type: image/png");
    readfile ($filename);
    exit();
}    


$cmd = sprintf ("PSITE_DIR='%s' ./mkgraph", $_SERVER['PSITE_DIR']);
exec ($cmd, $output, $rc);

$body .= "<img style='width=100%' src='graph.php?png=1' alt='' />\n";

pfinish ();
