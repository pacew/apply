<?php

require_once("app.php");

$anon_ok = 1;

pstart ();

$arg_name = trim (@$_REQUEST['name']);

get_neffa_index ();

$ret = (object)NULL;


$match = NULL;

$perfs = $index['perfs'];
foreach ($perfs as $p) {
    if (strcasecmp ($p['name'], $arg_name) == 0) {
        $match = $p;
        break;
    }
}

if ($match != NULL) {
    $ret->id = intval ($match['id']);
    $ret->name = $match['name'];
    $members = array ();

    if (@$match['group']) {
        $ret->group = 1;
        $members_raw = @$index['groups'][$match['id']];
        $contact_id = NULL;
        $ids = array ();
        foreach ($members_raw as $id) {
            $id = intval ($id);
            if ($id < 0) {
                $id = abs ($id);
                $contact_id = $id;
            }
            $ids[$id] = 1;
        }
        foreach ($perfs as $p) {
            $id = intval ($p['id']);
            if (@$ids[$id]) {
                $name = $p['name'];
                if ($id == $contact_id)
                    $name .= " (contact)";
                $members[] = $name;
            }
        }

        sort ($members);
        $ret->members = $members;
    }
}

ob_end_clean ();
header ("Content-Type: application/json");
echo (json_encode ($ret));
exit ();

