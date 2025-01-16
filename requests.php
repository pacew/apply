<?php

require_once ("app.php");

pstart();

$q = query ("select request_id, app_id, ts, val, dismissed"
    ." from requests"
    ." where fest_year = ?"
    ."   and test_flag = ?"
    ." order by request_id",
    array($submit_year, $submit_test_flag));
$requests = array();
while (($r = fetch ($q)) != NULL) {
  $req = (object)NULL;
  $req->request_id = intval($r->request_id);
  $req->app_id = intval($r->app_id);
  $req->ts = trim($r->ts);
  $req->val = trim($r->val);
  $req->dismissed = intval($r->dismissed);
  $requests[] = $req;
}

$rows = array();
foreach ($requests as $req) {
    $cols = [];
    $t = sprintf ("index.php?app_id=%d", $req->app_id);
    $cols[] = mklink ($req->app_id, $t);
    $cols[] = db_time_to_eastern($req->ts);
    $cols[] = $req->dismissed;

    $cols[] = h(substr($req->val, 0, 50)) . "...";

    $rows[] = $cols;
}

$body .= mktable(array("app", "timestamp", "dismissed", "contents"), $rows);

pfinish();
