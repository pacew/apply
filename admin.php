<?php

require_once("app.php");

pstart ();

$arg_refresh_idx = intval (@$_REQUEST['refresh_idx']);
$arg_just_new = intval (@$_REQUEST['just_new']);
$arg_set_year = intval (@$_REQUEST['set_year']);
$arg_desired_year = intval (@$_REQUEST['desired_year']);
$arg_desired_test_flag = intval (@$_REQUEST['desired_test_flag']);
$arg_return_to_app = intval (@$_REQUEST['return_to_app']);

if ($arg_set_year == 1) {
    $view_year = $arg_desired_year;
    putsess ("view_year", $view_year);
    $view_test_flag = $arg_desired_test_flag;
    putsess ("view_test_flag", $view_test_flag);

    redirect ("admin.php");
}

if ($arg_refresh_idx) {
    $cmd = sprintf ("sh -c 'cd %s; ./mkindex 2>&1'", $cfg['src_dir']);
    $body .= sprintf ("<div>running %s</div>\n", h($cmd));
    $val = exec ($cmd, $output, $rc);
    if ($rc != 0) {
        $body .= sprintf ("<div>error running: %s</div>\n", h($cmd));
    }
    $body .= "<pre>\n";
    $body .= h(implode ("\n", $output));
    $body .= "</pre>\n";

    $body .= "<div>\n";
    if ($arg_return_to_app) {
        $text = sprintf ("back to application %d", $arg_return_to_app);
        $t = sprintf ("index.php?app_id=%d", $arg_return_to_app);
        $body .= mklink ($text, $t);
    } else {
        $body .= mklink ("back to admin page", "admin.php");
    }
    $body .= "</div>\n";
    pfinish ();
}

$body .= "<h2>Admin page</h2>\n";

$body .= "<div>";
$body .= mklink ("home", "/");
$body .= " | ";
$body .= mklink ("show all", "admin.php");
$body .= " | ";
$body .= mklink ("new performers", "admin.php?just_new=1");
$body .= " | ";
$body .= mklink ("view data", "download.php?view_data=1");
$body .= " | ";
$body .= mklink ("view csv", "download.php?view_csv=1");
$body .= " | ";
$body .= mklink ("download csv", "download.php?download_csv=1");
$body .= " | ";
$body .= mklink ("test lookup", "lookup_individual.php?term=willisson");
$body .= "</div>\n";

$body .= "<div>\n";
$body .= "<form action='admin.php'>\n";
$body .= "<input type='hidden' name='set_year' value='1' />\n";
$body .= "View for festival year ";
$body .= "<select name='desired_year'>\n";
$body .= "<option value=''>--select--</option>\n";
make_option ($cur_year, $view_year, $cur_year);
make_option ($last_year, $view_year, $last_year);
$body .= "</select>\n";
$body .= "<select name='desired_test_flag'>\n";
make_option (0, $view_test_flag, "production data");
make_option (1, $view_test_flag, "test data");
$body .= "</select>\n";
$body .= "<input type='submit' value='set' />\n";
$body .= "</form>\n";
$body .= "</div>\n";

$key = getvar ("download_key");

if ($key != "") {
    $body .= "<div class='direct_download'>\n";
    $body .= "<p>Direct csv download link.  Includes secret access key."
        ." Protect like a password.</p>\n";

    foreach (array ($cur_year, $last_year) as $year) {
        $t = sprintf ("/download.php?direct_download=%s&year=%d", 
                      rawurlencode ($key), $year);
        $url = make_absolute ($t);
        $body .= "<div>\n";
        $body .= sprintf ("fest year %d: ", $year);
        $body .= sprintf ("<input type='text' readonly='readonly' "
                          ." size='%d' value='%s'/>\n", 
                          strlen($url) + 10, h($url));
        $body .= "</div>\n";
    }
    

    $body .= "</div>\n";
}

$idx_name = sprintf ("%s/neffa_idx.json", $cfg['aux_dir']);
$mtime = filemtime ($idx_name);
$body .= sprintf (
    "<div>neffa performer index last updated %s</div>\n",
    strftime ("%Y-%m-%d %H:%M:%S", $mtime));

$body .= "<form action='admin.php' method='post'>\n";
$body .= "<input type='hidden' name='refresh_idx' value='1' />\n";
/* prevent accidental form submission */
$body .= "<button type='submit' onclick='return false' style='display:none'>"
      ."</button>\n";

$body .= "<input type='submit' value='Refresh performer index' />\n";
$body .= "</form>\n";

$apps = get_applications ();

$body .= sprintf ("<h2>%d applications [%s]</h2>\n", 
                  count($apps), mklink ("graph", "graph.php"));

//$q = query ("select app_id, $ts_col,"
//            ."   username, val, attention, fest_year, test_flag"

$rows = array ();
foreach ($apps as $app) {
    $target = sprintf ("index.php?app_id=%d", $app->app_id);

    if ($app->attention) {
        $css = "attention";
    } else {
        if ($arg_just_new)
            continue;
        $css = "";
    }
    $cols = array ();
    $cols[] = mklink_span ($app->app_id, $target, $css);
    $cols[] = mklink_span ($app->ts, $target, $css);

    $txt = "";
    $curvals = $app->curvals;
    $sep = "";
    if (@$curvals['group_name']) {
        $txt .= sprintf ("%sG: %s", $sep, h($curvals['group_name']));
        $sep = "<br/>";
    }
    if (@$curvals['event_title']) {
        $txt .= sprintf ("%sT: %s", $sep, h($curvals['event_title']));
        $sep = "<br/>";
    }
    if (@$curvals['name']) {
        $txt .= sprintf ("%sN: %s", $sep, h($curvals['name']));
        $sep = "<br/>";
    }
    
    $cols[] = $txt;

    $cols[] = h($app->confirmed);

    $t = sprintf ("download.php?view_csv=1&app_id=%d", $app->app_id);
    $cols[] = mklink ("raw data", $t);

    $rows[] = $cols;
}

if (count ($rows) == 0) {
    $body .= "<p>no data to display</p>\n";
} else {
    $body .= mktable (array ("app_id", "ts", "group / title / name", "confirmation", ""),
    $rows);
}


pfinish ();

