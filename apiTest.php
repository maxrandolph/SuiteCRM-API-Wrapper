<?php
require_once('./suiteAPI.php');
ini_set('xdebug.var_display_max_depth', -1);
ini_set('xdebug.var_display_max_children', -1);
ini_set('xdebug.var_display_max_data', -1);

$api = new SuiteAPI\SuiteAPI;

$api->newSession(); // instantiate the session. $test->session_id is set to the accesstoken
$searchtest       = $api->search("83577","custnum_c");
$searchemailtest  = $api->emailSearch("lisacuster1@aol.com");
$assigncalltest   = $api->assignCallToContact("2a04ecd3-67b5-a755-403f-580129938e7c","10003fd3-efd7-e7ea-6005-58000af86c5e");
$maketasktest     = $api->makeTask("2016-10-15 18:45:00","dcd380d0-b01a-f359-13e8-57ffedd49fdc","Sample note","sample task name","sample description","123123","1");
$makecalltest     = $api->makeCall("2016-10-15 18:45:00","dcd380d0-b01a-f359-13e8-57ffedd49fdc","1","123123","Call this person sample text");
$testnewrecord    = $api->newRecord("Contacts",array("fname"=>"test","lname"=>"testy","email"=>"testy@test.com","custnum"=>"123123123","phone"=>"2134412323","addr"=>"123 test st","city"=>"test city","zip"=>"55555","state"=>"XX"));
$testupdaterecord = $api->updateRecord("Contacts",array("id"=>"918eef50-5af2-f6d4-c3b5-5800044a4959","fname"=>"M","lname"=>"AA","email"=>"testy@test.com","custnum"=>"123123123","phone"=>"2134412323","addr"=>"123 test st","city"=>"test city","zip"=>"55555","state"=>"XX")); //you can update as many fields as will give you satisfaction. Just add to array.
$testnewrecords   = $api->newRecords("Contacts",array(
                                    array("fname"=>"test","lname"=>"testy","email"=>"testy@test.com","custnum"=>"123123123","phone"=>"2134412323","addr"=>"123 test st","city"=>"test city","zip"=>"55555","state"=>"XX","comment"=>"blah blah sample comment"),
                                    array("fname"=>"test2","lname"=>"testy2","email"=>"testy2@test.com","custnum"=>"1223123123","phone"=>"2135412323","addr"=>"2123 test st","city"=>"test city","zip"=>"55555","state"=>"XX","comment"=>"second sample comment")));
echo($api->session_id);
var_Dump($searchtest);
var_dump($searchemailtest);
var_dump($assigncalltest);
// Any of these functions can be var_dumped to show the effect that calling them has had.

?>
