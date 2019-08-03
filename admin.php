<?php

require_once("app.php");

pstart ();

$arg_refresh_idx = intval (@$_REQUEST['refresh_idx']);
$arg_just_new = intval (@$_REQUEST['just_new']);
$arg_set_year = intval (@$_REQUEST['set_year']);
$arg_desired_year = intval (@$_REQUEST['desired_year']);


if ($arg_set_year == 1) {
    $view_year = $arg_desired_year;
    putsess ("view_year", $view_year);
    redirect ("admin.php");
}

if ($arg_refresh_idx) {
    $cmd = sprintf ("sh -c 'cd %s; ./mkindex'", $cfg['srcdir']);
    $body .= sprintf ("<div>running %s</div>\n", h($cmd));
    $val = exec ($cmd, $output, $rc);
    if ($rc != 0) {
        $body .= sprintf ("<div>error running: %s</div>\n", h($cmd));
    }
    $body .= "<pre>\n";
    $body .= h(implode ("\n", $output));
    $body .= "</pre>\n";

    $body .= sprintf (
        "<div>%s</div>\n", 
        mklink ("back to admin page", "admin.php"));
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
$body .= mklink ("view json", "download.php?view_json=1");
$body .= " | ";
$body .= mklink ("download json", "download.php?download_json=1");
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
$body .= "<input type='submit' value='set' />\n";
$body .= "</form>\n";
$body .= "</div>\n";

$key = getvar ("download_key");

if ($key != "") {
    $body .= "<div class='direct_download'>\n";
    $body .= "<p>Direct csv download link.  Includes secret access key."
        ." Protect like a password.</p>\n";
    $t = sprintf ("/download.php?direct_download=%s", rawurlencode ($key));
    $url = make_absolute ($t);
    $body .= sprintf ("<input type='text' readonly='readonly' "
        ." size='%d' value='%s'/>\n", strlen($url) + 10, h($url));
    
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

if ($options['db'] == "postgres") {
    $ts_col = "to_char (ts, 'YYYY-MM-DD HH24:MI:SS') as ts";
} else {
    $ts_col = "ts";
}

$q = query ("select app_id, $ts_col,"
            ."   username, val, attention, fest_year, test_flag"
            ." from json"
            ." where fest_year = ?"
            ." order by app_id, ts",
            $view_year);

$rows = array ();
while (($r = fetch ($q)) != NULL) {
    if (! $show_test_data && $r->app_id < $first_prod_app_id)
        continue;

    $target = sprintf ("index.php?app_id=%d", $r->app_id);

    /* ignore patches */
    if (strncmp ($r->val, "[", 1) == 0)
        continue;

    if ($r->attention) {
        $css = "attention";
    } else {
        if ($arg_just_new)
            continue;
        $css = "";
    }
    $cols = array ();
    $cols[] = $r->fest_year;
    $cols[] = $r->test_flag;
    $cols[] = mklink_span ($r->app_id, $target, $css);
    $cols[] = mklink_span ($r->ts, $target, $css);

    $txt = "";
    $val = @json_decode ($r->val, TRUE);
    $txt = sprintf ("%s / %s", h(@$val['name']), h(@$val['event_title']));
    $cols[] = $txt;

    $t = sprintf ("download.php?view_csv=1&app_id=%d", $r->app_id);
    $cols[] = mklink ("raw data", $t);

    $rows[] = $cols;
}

if (count ($rows) == 0) {
    $body .= "<p>no data to display</p>\n";
} else {
    $body .= mktable (array ("year", "test", "app_id", "ts", "name / title", ""),
    $rows);
}


pfinish ();

