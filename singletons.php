<?php

require_once("app.php");

pstart ();

$arg_leader_id = intval(@$_REQUEST['leader_id']);
$arg_send = intval(@$_REQUEST['send']);

$group_to_members = NULL;

populate_group_to_members();
read_notify_info();

$problems = [];
foreach ($webgrid as $webgrid_elt) {
    foreach ($webgrid_elt->evids as $evid) {
        global $apps_by_evid;
        if (($app = @$apps_by_evid[$evid]) == NULL)
            continue;
        if (strcmp ($app->curvals['main_performer'], "Group") == 0) {
            $group_name = $app->curvals['group_name'];
            $group_id = name_to_id ($group_name);
            $leader_id = @$group_to_group_leader[$group_id];
            if ($leader_id == 0)
                continue;
            $members = $group_to_members[$group_id];
            if (count($members) >= 2)
                continue;
            $badgroup = (object)NULL;
            $badgroup->leader_id = $leader_id;
            $badgroup->group_id = $group_id;
            $badgroup->group_name = $group_name;

            if (! isset($problems[$leader_id]))
                $problems[$leader_id] = [];
            $problems[$leader_id][] = $badgroup;
        }
    }
}
$leader_id = -1;

if ($arg_leader_id) {
    $body .= "<div>\n";
    $body .= mklink("[back to singltons]", "singletons.php");
    $body .= "</div>\n";

    $perf = @$performers[$arg_leader_id];
    $to_email = @get_email($perf);

    if (($badgroups = @$problems[$arg_leader_id]) == NULL 
        || $perf == NULL
        || $to_email == ""
    ) {
        $body .= "<p>internal error</p>\n";
        pfinish();
    }

    $pcode = $name_id_to_pcode[$perf->number];
    
    $vals['first_name'] = preg_replace ('/^[^,]*,/', "", $perf->name);
    $vals['pcode'] = $pcode;

    $groups = [];
    foreach ($badgroups as $badgroup) {
        $groups[$badgroup->group_name] = 1;
    }
    $group_names = "";
    foreach ($groups as $group_name => $dummy) {
        $group_names .= sprintf ("<div>&nbsp;&nbsp;&nbsp;&nbsp;%s</div>\n", 
            h($group_name));
    }

    $vals['group_names'] = $group_names;

    $html = populate_template("singleton.html", $vals);

    $em = (object)NULL;
    $em->to_email = $to_email;
    $em->subject = "IMPORTANT: Please update your NEFFA group membership ASAP!";
    $em->body_html = $html;
    $em->body_text = preg_replace ("/&nbsp;/", " ", strip_tags($em->body_html));

    if ($arg_send) {
        $em->to_email = "pace.willisson@gmail.com";
        $em->no_history = 1;
        send_email ($em);

        query ("insert into group_nag(email, ts)"
            ." values (?, current_timestamp)",
            $to_email);

        $t = sprintf ("singletons.php?leader_id=%d", $arg_leader_id);
        redirect ($t);
    }

    $body .= "<div class='admin_box'>\n";

    $q = query ("select ts from group_nag"
        ." where email = ?"
        ." order by ts",
        $to_email);
        
    $rows = [];
    while (($r = fetch($q)) != NULL) {
        $cols = [];
        $cols[] = h($r->ts);
        $rows[] = $cols;
    }
    if (count($rows) == 0) {
        $cols = ["not nagged yet"];
        $rows[] = $cols;
    }
    $body .= "<div>previous nags</div>\n";
    $body .= mktable(array(""), $rows);


    $body .= "<form action='singletons.php' method='post'>\n";
    $body .= sprintf ("<input type='hidden' name='leader_id' value='%d' />\n",
        $arg_leader_id);
    $body .= "<input type='hidden', name='send' value='1' />\n";
    $body .= "<input type='submit' value='Send this email' />\n";
    $body .= "[but we're in testing mode and this won't really send,"
        ." it will just update the history]";
    $body .= "</form>\n";
    $body .= "</div>\n";

    $body .= "<div class='notify_email'>\n";
    $body .= sprintf ("<p>To: %s<br/>\n", h($em->to_email));
    $body .= sprintf ("Subject: %s</p>\n", h($em->subject));

    $body .= $em->body_html;
    $body .= "</div>\n";

    pfinish();
}

$group_nag = [];
$q = query ("select email, ts"
    ." from group_nag"
    ." order by email, ts");
while (($r = fetch ($q)) != NULL) {
    $email = trim($r->email);
    $ts = trim($r->ts);
    $group_nag[$email] = $ts;
}

$rows = [];
$count = 0;
foreach ($problems as $leader_id => $badgroups) {
    if (($perf = @$performers[$leader_id]) != NULL) {
        $perf_name = $perf->name;
    } else {
        $perf_name = h($leader_id);
    }

    $to_email = @get_email($perf);


    $count++;
    $cols = [];
    $cols[] = $count;
    $t = sprintf ("singletons.php?leader_id=%d", $leader_id);
    $cols[] = mklink($perf_name, $t);

    if ($to_email == "") {
        $text = "can't find email";
    } else {
        $text = h(@$group_nag[$to_email]);
    }
    $cols[] = $text;

    $groups = "";
    $sep = "";
    foreach ($badgroups as $badgroup) {
        $groups .= $sep . h($badgroup->group_name);
        $sep = " | ";
    }
    $cols[] = $groups;
    $rows[] = $cols;
}

$body .= mktable(array("", "leader", "nagged", "groups"), $rows);

pfinish();

    
