<?php

require_once("app.php");

pstart ();

$arg_refresh_idx = intval (@$_REQUEST['refresh_idx']);
$arg_just_new = intval (@$_REQUEST['just_new']);
$arg_set_year = intval (@$_REQUEST['set_year']);
$arg_desired_year = intval (@$_REQUEST['desired_year']);
$arg_desired_test_flag = intval (@$_REQUEST['desired_test_flag']);
$arg_return_to_app = intval (@$_REQUEST['return_to_app']);
$arg_set_filter = intval (@$_REQUEST['set_filter']);
$arg_filter = trim (@$_REQUEST['filter']);
$arg_doc = intval (@$_REQUEST['doc']);
$arg_set_group_filter = intval(@$_REQUEST['set_group_filter']);
$arg_group_filter = intval(@$_REQUEST['group_filter']);

if ($arg_set_group_filter)
    putsess("group_filter", $arg_group_filter);

$group_filter = intval(getsess("group_filter"));

if ($arg_doc == 1) {
        

    // get the first app on the list to make an example
    $apps = get_applications ();
    $examples = array();

    foreach ($apps as $app) {
        if ($app->curvals['name'] == "Brown,Dean")
            $examples[] = $app;
    }

    if (count($examples) == 0) {
        $body .= "can't find example applications";
        pfinih();
    }

    $app = $examples[0];
    $neffa_id = name_to_id ($app->curvals['name']);
    $pcode = neffa_id_to_pcode($neffa_id);

    $body .="<h2>example admin link to edit app (will force login):</h2> ";
    $body .= "<div>\n";
    $path = sprintf("/index.php?app_id=%d", $app->app_id);
    $target = make_absolute($path);
    $body .= mklink($target, $target);
    $body .= "</div>\n";

    $body .= "<hr/>\n";

    $host = $_SERVER['HTTP_HOST'];
    $magic_link = sprintf("https://%s/response.php?pcode=%s",
        $host, rawurlencode($pcode));
    
    $confirm2_link = sprintf("https://cgi.neffa.org/performer/confirm2.pl"
        ."?P=%s", rawurlencode($pcode));

    $body .= "<h2>example magic link to response page"
        ." (mailed to performer)</h2>\n";
    $body .= "<div>\n";
    $body .= mklink ($magic_link, $magic_link);
    $body .= "</div>\n";
    $body .= "<div>this will immediately redirect to confirm2.pl with"
        ." the current values of the confirm and record parameters</div>\n";

    $body .= "<hr/>\n";

    $body .= "<h2>cgi program redirects to these links"
        ." to record confirmation</h2>\n";
    $body .= "<table class='twocol'>\n";
    $body .= "<tr><th>query</th><td>";
    $t = sprintf("https://%s/response.php?pcode=%s", 
        $host, rawurlencode($pcode));
    $body .= mklink ($t, $t);
    $body .= "</td></tr>\n";
    $body .= "<tr><th>I will perform, record ok</th><td>";
    $t = sprintf("https://%s/response.php?pcode=%s&confirm=2&record=2", 
        $host, rawurlencode($pcode));
    $body .= mklink ($t, $t);
    $body .= "</td></tr>\n";
    $body .= "<tr><th>I will perform, no record</th><td>";
    $t = sprintf("https://%s/response.php?pcode=%s&confirm=2&record=3", 
        $host, rawurlencode($pcode));
    $body .= mklink ($t, $t);
    $body .= "</td></tr>\n";
    $body .= "<tr><th>I will not perform</th><td>";
    $t = sprintf("https://%s/response.php?pcode=%s&confirm=3", 
        $host, rawurlencode($pcode));
    $body .= mklink ($t, $t);
    $body .= "</td></tr>\n";
    $body .= "</table>\n";

    $body .= sprintf ("<p>will be redirected back to %s plus args</p>",
        h($confirm2_link));

    $body .= "<hr/>\n";

    $body .= "<h2>cgi program sends a performer here to edit an event</h2>\n";
    $body .= "<div>\n";
    $t = sprintf("https://%s/performer.php"
        ."?pcode=%s"
        ."&eventid=S_BallroomAB_1200",
        $host, rawurlencode($pcode));
    $body .= mklink ($t, $t);
    $body .= "</div>\n";

    $body .= "<p>apply.neffa.org will use eventid to find the right row"
        ." in webgrid.tsv, then use group leader logic to find the right"
        ." app_id for this pcode.  "
        ." if there's not a clear answer, it will tell the performer to email"
        ." program@neffa.org</p>\n";
    $body .= "<p>eventid is formed with the equivalent of"
        ." '_'.join([ Day, Room.replace(' ', ''), StartTime]) using"
        ." the values from webgrid.tsv</p>\n";
    $body .= "<p>eventid is compared exactly (case sensitive) to avoid"
        ." trouble if some future room name has"
        ." exotic utf8 characters</p>\n";


    $body .= "<hr/>\n";

    $body .= "<h2>uploading TSV</h2>\n";

    $body .= "<p>Make a post with the equivalent of the following:</p>\n";
    $body .= "<p>There's an interactive version of this form at ";
    $body .= mklink("notify.php", "notify.php");
    $body .= "</p>\n";
    $form = "<form action='notify.php' method='post'"
        ." enctype='multipart/form-data'>\n";
    $form .= "<input type='hidden' name='upload' value='1' />\n";
    $form .= "<input type='hidden' name='return_json' value='1' />\n";
    $form .= "<input type='file' name='webgrid' />\n";
    $form .= "<input type='submit' value='upload' />\n";
    $form .= "</form>\n";

    $body .= "<pre>\n";
    $body .= h($form);
    $body .= "</pre>\n";



    pfinish();
}

if ($arg_set_year == 1) {
    $view_year = $arg_desired_year;
    putsess ("view_year", $view_year);
    $view_test_flag = $arg_desired_test_flag;
    putsess ("view_test_flag", $view_test_flag);

    redirect ("admin.php");
}

if ($arg_set_filter == 1) {
    putsess ("filter", $arg_filter);
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
$body .= " | ";
$body .= mklink ("templates", "templates.php");
$body .= " | ";
$body .= mklink ("webgrid notify", "notify.php");
$body .= " | ";
$body .= mklink ("technical_doc", "admin.php?doc=1");
$body .= "</div>\n";

$body .= "<div>\n";
$body .= "<form action='admin.php'>\n";
$body .= "<input type='hidden' name='set_year' value='1' />\n";
$body .= "View for Festival year ";
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

$filters = array ("all", "unconfirmed", "show-suppressed");
$cur_filter = getsess ("filter");
if (array_search ($cur_filter, $filters) === FALSE)
    $cur_filter = "all";

$body .= "<form action='admin.php'>\n";
$body .= "<input type='hidden' name='set_filter' value='1' />\n";
$body .= "Show: ";
foreach ($filters as $filter) {
    $body .= " &nbsp;&nbsp;&nbsp; ";
    $c = "";
    if ($cur_filter == $filter)
        $c = "checked='checked'";
    $body .= sprintf ("<input type='radio' name='filter' value='%s' %s />\n",
                      $filter, $c);
    $body .= $filter;
}
$body .= " &nbsp;&nbsp;&nbsp; ";

$body .= "<input type='hidden' name='set_group_filter' value='1' />\n";
$body .= "<select name='group_filter'>\n";
make_option (0, $group_filter, "any");
make_option (1, $group_filter, "ritual dance");
make_option (2, $group_filter, "dance performance");
make_option (3, $group_filter, "american");
make_option (4, $group_filter, "english");
make_option (5, $group_filter, "international");
make_option (6, $group_filter, "jam");
make_option (7, $group_filter, "song");
make_option (8, $group_filter, "concert");
make_option (9, $group_filter, "spoken_word");
$body .= "</select>\n";

$body .= "<input type='submit' value='change filter' />\n";
$body .= "</form>\n";

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

    $cols[] = mklink_span ($app->evid, $target, $css);

    $date = preg_replace("/ .*/", "", $app->ts);
    $cols[] = mklink_span ($date, $target, $css);

    $curvals = $app->curvals;

    $cols[] = sprintf ("<span class='app_category cat_%s'>%s</span>\n",
                       strtolower ($curvals['app_category']),
                       $curvals['app_category']);

    $txt = "";
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

    $full_notes = trim(@$curvals['C_notes']);
    $notes = preg_replace("/\n.*/", "", $full_notes);
    $suffix = "";
    if (strcmp ($full_notes, $notes) != 0) {
        $t = sprintf ("index.php?app_id=%d", $app->app_id);
        $suffix = sprintf (" ...%s", mklink("[more]", $t));
    }
    $cols[] = h($notes) . $suffix;

    if (0) {
        $t = sprintf ("download.php?view_csv=1&app_id=%d", $app->app_id);
        $cols[] = mklink ("raw data", $t);
    }

    $show = 1;
    
    if ($cur_filter == "unconfirmed" && $app->confirmed != "") {
        $show = 0;
    }

    if (@$app->curvals['do_not_import'] && $cur_filter != "show-suppressed") {
        $show = 0;
    }

    switch ($group_filter) {
    case 1:
        if ($curvals['app_category'] != 'Ritual')
            $show = 0;
        break;
    case 2:
        if ($curvals['app_category'] != 'Performance')
            $show = 0;
        break;
    case 3:
        $show = 0;
        if ($curvals['app_category'] == "Band"
            || $curvals['app_category'] == "Band_Solo"
            || $curvals['app_category'] == "Caller") {
            if ($curvals['dance_style'] == "American")
                $show = 1;
        }
        break;
    case 4:
        $show = 0;
        if ($curvals['app_category'] == "Band"
            || $curvals['app_category'] == "Band_Solo"
            || $curvals['app_category'] == "Caller") {
            if ($curvals['dance_style'] == "English_Couples")
                $show = 1;
        }
        break;
    case 5:
        $show = 0;
        if ($curvals['app_category'] == "Band"
            || $curvals['app_category'] == "Band_Solo"
            || $curvals['app_category'] == "Caller") {
            if ($curvals['dance_style'] == "Int_Line")
                $show = 1;
        }
        break;
    case 6:
        if ($curvals['app_category'] != 'Other' 
            || $curvals['fms_category'] != "jam")
            $show = 0;
        break;
    case 7:
        if ($curvals['app_category'] != 'Other' 
            || $curvals['fms_category'] != "song")
            $show = 0;
        break;
    case 8:
        if ($curvals['app_category'] != 'Other' 
            || $curvals['fms_category'] != "concert")
            $show = 0;
        break;
    case 9:
        if ($curvals['app_category'] != 'Other' 
            || $curvals['fms_category'] != "spoken_word")
            $show = 0;
        break;
    }

    if ($show)
        $rows[] = $cols;
}

if (count ($rows) == 0) {
    $body .= "<p>no data to display</p>\n";
} else {
    $body .= "<p class='admin'>click the evid or submit date"
        ." to view or edit an application</p>\n";
    $body .= mktable (array ("app_id", "evid", "submit date",
            "category",
            "group / title / name", 
            "confirmation",
            "Committee notes<br/>(first line)"),
        $rows);
}


pfinish ();

