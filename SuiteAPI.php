<?php
namespace SuiteAPI;
require_once('../SugarHttpClient.php');

class SuiteAPI
{
  private $url        = '{PATH TO REST.PHP WITHIN YOUR SUITECRM}';
  public  $session_id = '';  //This is essentially the access token
  public  $module     = '';

  /**
   * Make a curl call specifically for SuiteCRM
   *
   * @param string $method The type of REST call to make
   * @param array $parameters The array of data to accompany the api call
   * @param string $url The REST php file for suitecrm.
   *
   * @return object The result of the API call
   */
  protected function myCall($method, $parameters, $url){

  		ob_start();
          $curl_request = curl_init();
          curl_setopt($curl_request, CURLOPT_URL, $url);
          curl_setopt($curl_request, CURLOPT_POST, 1);
          curl_setopt($curl_request, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
          curl_setopt($curl_request, CURLOPT_HEADER, 1);
          curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, 0);
          curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($curl_request, CURLOPT_FOLLOWLOCATION, 0);
          $jsonEncodedData = json_encode($parameters);
          $post = array(
               "method" => $method,
               "input_type" => "JSON",
               "response_type" => "JSON",
               "rest_data" => $jsonEncodedData
          );
          curl_setopt($curl_request, CURLOPT_POSTFIELDS, $post);
          $result = curl_exec($curl_request);
          curl_close($curl_request);
          $result = explode("\r\n\r\n", $result, 2);
          $response = json_decode($result[1]);
          ob_end_flush();
          return $response;
      }
  /**
   * Generate a new SuiteCRM Session token after auth.
   *
   * @return string The session id.
   */
  public function newSession(){

    $client = new \SugarHttpClient;

    // Set Suite username and password here
    $parameters = array(
        'user_auth' => array(
          'user_name' => '{YOUR ADMIN USERNAME}',
          'password' => md5('{YOUR ADMIN PASSWORD}')
        )
    );

    $json = json_encode($parameters);

    $postArgs = array(
        'method' => 'login',
        'input_type' => 'JSON',
        'response_type' => 'JSON',
        'rest_data' => $json,
    );

    $postArgs = http_build_query($postArgs);

    // Make the REST call, returning the result
    $response = $client->callRest($this->url, $postArgs);

    if ( $response === false )
    {
        die("Request failed.\n");
    }

    // Convert the result from JSON format to a PHP array
    $result = json_decode($response);

    if ( !is_object($result) )
    {
        die("Error hanedling result.\n");
    }

    if ( !isset($result->id) )
    {
        die("Error: {$result->name} - {$result->description}\n.");
    }

    $this->session_id = $result->id;

    return($result->id);
    }
  /**
   * Search via any field besides email
   *
   * @param string $query The string to search by, can be a phone or first name, last name, or any custom field
   * @param qtype $qtype The field you want to search by, e.g. "phone_home", "first_name", "custnum_c" Must equal the column name you want to search by
   * @param string $max_results How many results you want back maximum. Default is 1. Needs to be in string.
   *
   * @return array The result(s) of your search
   */
  public function search($query, $qtype, $max_results='1'){

    $client = new \SugarHttpClient;
    $get_entry_list_parameters = array(
        'session' => $this->session_id,            // Session id
        'module_name' => "Contacts",         // The name of the module from which to retrieve records
        'query' => $qtype." = '".$query."'", // The SQL WHERE clause without the word "where"
        'order_by' => "",                    // Example: accounts.id
        'offset' => '0',                     // The record offset from which to start.
        'select_fields' => array(            // List of fields to include in the results.
            'id',
			      'first_name',
            'name',
            'case_number',
            'status',
			'custnum_c',
			'phone_home',
		    'email1',
        ),
        'max_results' => $max_results,  // The maximum number of results to return.
        'deleted' => '0',      // To exclude deleted records
        'Favorites' => false,  // If only records marked as favorites should be returned.
    );

    $get_entry_list_result = $this->myCall('get_entry_list', $get_entry_list_parameters, $this->url);

  	if ($get_entry_list_result && $get_entry_list_result->result_count > 0 ){ // check if result is found

      $id_found = $get_entry_list_result->entry_list[0]->id;
  		$custnum_found = $get_entry_list_result->entry_list[0]->name_value_list->custnum_c;

      return array($id_found,$custnum_found,$get_entry_list_result);        // return id and custnum
  	}else{
  		return 0;
  	}
  }

  /**
   * Search via email address
   *
   * @param string $email_string The email to search.
   *
   * @return array The result(s) of your search
   */
  public function emailSearch($email_string){
    $url = $this->url;
    $session_id = $this->session_id;
    $search_by_module_parameters = array(
        "session" => $session_id,
        'search_string' => $email_string,
        'modules' => array(
            'Accounts',
            'Contacts',
            'Leads',
        ),
        'offset' => 0,
        'max_results' => 1,
        'assigned_user_id' => '',
        'select_fields' => array('id'),
        'unified_search_only' => false,
        'favorites' => false
    );
    $search_by_module_results = $this->myCall('search_by_module', $search_by_module_parameters, $url);
    $record_ids = array();

    foreach ($search_by_module_results->entry_list as $results){
        $module = $results->name;
        foreach ($results->records as $records){
            foreach($records as $record){
                if ($record->name = 'id'){
                    $record_ids[$module][] = $record->value;
                    //skip any additional fields
                    break;
                }
            }
        }
    }

    $get_entries_results = array();
    $modules = array_keys($record_ids);

    foreach($modules as $module){
        $get_entries_parameters = array(
            'session' => $session_id,
            'module_name' => $module,
            'ids' => $record_ids[$module],
            'select_fields' => array(
              'id',
			        'first_name',
              'name',
              'case_number',
              'status',
			        'custnum_c',
			        'phone_home',
			        'email1',
            ),
            //A list of link names and the fields to be returned for each link name
            'link_name_to_fields_array' => array(
                array(
                    'name' => 'home_phone',
                    'value' => array(
                        'email_address',
                        'opt_out',
                        'primary_address'
                    ),
                ),
            ),
            //Flag the record as a recently viewed item
            'track_view' => false);
        $get_entries_results[$module] = $this->myCall('get_entries', $get_entries_parameters, $url);
		    $get_entries_results=$get_entries_results[$module];

	      if (!count($get_entries_results)){
			    return 0;
		    }else{
		      return $get_entries_results;
	      }
      }
    }
  /**
   * Assign a call with $call_id to a user or array of users
   *
   * @param string $call_id The string of the call id that you want to assign
   * @param array $contact_id This is an array of either 1 or more user id's that you want to assign the call to
   *
   * @return void Nothing is returned but page will die on failure.
   */
 public function assignCallToContact($call_id,$contact_id){
   $url = $this->url;
   $client = new \SugarHttpClient;
   $parameters = array(
     'session' => $this->session_id,
     'module_name' => "Calls",
     'module_id' => $call_id,
     'link_field_name' => 'contacts',
     'related_ids' => $contact_id,
     'name_value_list' => array(
       array(
         'name' => 'contact_role',
         'value' => 'Other'
       )
     ),
     'delete'=>0
   );
   $json = json_encode($parameters);
   $postArgs = array(
     'method' => 'set_relationship',
     'input_type' => 'JSON',
     'response_type' => 'JSON',
     'rest_data'=> $json,
   );
   $postArgs = http_build_query($postArgs);
   $response = $client->callRest($url, $postArgs);   // Make the REST call, returning the result
   if ($response === false){die("Request failed.\n");}

   $result = json_decode($response); // Convert the result from JSON format to a PHP array
   if (!is_object($result)){
     die("Error handling result.\n");
   }
   return($result);
 }
 /**
  * Create a task and assign it to specified user_id
  *
  * @param string $due_date String of date task should be completed by
  * @param string $custid string of id that the task is related to.
  * @param string $note string of additional note to include in task description
  * @param string $task name of task
  * @param string $custnum optional to include custnum assigned for including in task description
  * @param string $custid string of id that the task is related to.
  * @param string $assigned_user id of the user to whom the task will be assigned.
  *
  * @return array The newly created task is returned in an array.
  */
 public function makeTask($due_date,$custid,$note="",$task="Default Task",$description="Default task desc.",$custnum="",$assigned_user="1"){
     // specify the REST web service to interact with
     $url = $this->url;
     $client = new \SugarHttpClient;
     $parameters = array(
     'session' => $this->session_id, //Session ID
     'module' => "Tasks",  //Module name
     'name_value_list' => array (
             array('name' => 'name', 'value' => $custnum.$task),
             array('name' => 'description', 'value' => $description),
             array('name' => 'assigned_user_id', 'value' => $assigned_user),
 			array('name' => 'date_due', 'value' => $due_date),
 			array('name' => 'contact_id', 'value' => $custid),
 			array('name' => 'status', 'value' => "Not Started")  //High priority fix this. Default parameter = not started
         ),
     );
 	   $json = json_encode($parameters);
 	   $postArgs = array(
 	     'method' => 'set_entry',
 	     'input_type' => 'JSON',
 	     'response_type' => 'JSON',
 	     'rest_data'=> $json,
 	   );
     $postArgs = http_build_query($postArgs);
     // Make the REST call, returning the result
     $response = $client->callRest($url, $postArgs);

     if ( $response === false )
     {
         die("Request failed.\n");
     }

     $result = json_decode($response);      // Convert the result from JSON format to a PHP array
     if (!is_object($result)){
 		      die("Error handling result.\n");
     }
     return($result);
  }
  /**
   * Create a call and assign it to specified user_id
   *
   * @param string $due_date String of date task should be completed by
   * @param string $contact_id id of the contact to whom the call is related
   * @param string $note string of additional note to include in call description
   * @param string $custnum optional to include custnum assigned information
   * @param string $assigned_user_id id of the user to whom the task will be assigned. Default is administrator.
   *
   * @return array The newly created call is returned in an array.
   */
  public function makeCall($due_date, $contact_id, $assigned_user_id="1", $custnum="", $note=""){
    // specify the REST web service to interact with
    $url = $this->url;
    $client = new \SugarHttpClient;
    $parameters = array(
    'session' => $this->session_id, //Session ID
    'module' => "Calls",  //Module name
    'name_value_list' => array (
            array('name' => 'name', 'value' => "Phone Cust #".$custnum),
            array('name' => 'description', 'value' => "Phone follow up regarding order with customer number: ".$custnum." ".$note),
            array('name' => 'assigned_user_id', 'value' => $assigned_user_id),
      array('name' => 'date_start', 'value' => $due_date),
      array('name' => 'direction', 'value' => "Outbound"),
      array('name' => 'duration_minutes', 'value' => 15),
      ),
    );
    $json = json_encode($parameters);
    $postArgs = array(
      'method' => 'set_entry',
      'input_type' => 'JSON',
      'response_type' => 'JSON',
      'rest_data'=> $json,
    );

    $postArgs = http_build_query($postArgs);
    $response = $client->callRest($url, $postArgs);

    if ( $response === false ){die("Request failed.\n");}

    $result = json_decode($response);     // Convert the result from JSON format to a PHP array

    if ( !is_object($result) ){
    die("Error handling result.\n");
    }

  $this->assignCallToContact($result->id,array($contact_id)); //assign the call to the contact
  return($result);
  }
  /**
   * Create an object in suitecrm, could be account or contact or lead
   *
   * @param string $module String of module to add entry to ("Contacts", "Accounts", "Leads")
   * @param array  $infoArray Array that contains all the information for creating the Suitecrmentry
   *
   * @return array The newly created entry is returned in an array.
   */
  public function newRecord($module, $infoArray){
    $url = $this->url;

    $client = new \SugarHttpClient;
    $parameters = array(
      'session' => $this->session_id, //Session ID
      'module' => $module,  //Module name

      'name_value_list' => array (
			      array('name' => 'first_name', 'value' => $infoArray["fname"]),
            array('name' => 'last_name', 'value' => $infoArray["lname"]),
			      array('name' => 'email1', 'value' => $infoArray["email"]),
            array('name' => 'account_name', 'value' => ''),
            array('name' => 'custnum_c', 'value' => $infoArray["custnum"]),
			      array('name' => 'phone_home', 'value' => $infoArray["phone"]),
			      array('name' => 'primary_address_street', 'value' => $infoArray["addr"]),
            array('name' => 'primary_address_city', 'value' => $infoArray["city"]),
            array('name' => 'primary_address_state', 'value' => $infoArray["state"]),
            array('name' => 'primary_address_postalcode', 'value' => $infoArray["zip"],
            array('name' => 'email1', 'value' => $infoArray["email"]))
          )
  );
	 $json = json_encode($parameters);
   $postArgs = array(
    'method' => 'set_entry',
    'input_type' => 'JSON',
    'response_type' => 'JSON',
    'rest_data'=> $json
    );
    $postArgs = http_build_query($postArgs);
    // Make the REST call, returning the result
    $response = $client->callRest($url, $postArgs);
    if ( $response === false )
    {
      die("Request failed.\n");
    }
    // Convert the result from JSON format to a PHP array
    $result = json_decode($response);

    if (!is_object($result)){
		  die("Error handling result.\n");
    }
	return($result);
  }
  /**
   * Update an object in suitecrm, could be account or contact or lead
   *
   * @param string $module S0tring of module to update entry to ("Contacts", "Accounts", "Leads")
   * @param array  $infoArray Array that contains all the information for creating the Suitecrmentry
   *
   * @return array The newly updated entry is returned in an array.
   */
 public function updateRecord($module,$infoArray){
    $url = $this->url;
    $client = new \SugarHttpClient;
    $parameters = array(
     'session' => $this->session_id, //Session ID
     'module' => $module,  //Module name
     'name_value_list' => array (
             array('name' => 'id', 'value' => $infoArray['id']),
 			       array('name' => 'first_name', 'value' => $infoArray['fname']),
             array('name' => 'last_name', 'value' => $infoArray['lname']),
             array('name' => 'email1', 'value' => $infoArray['email']),
             array('name' => 'custnum_c', 'value' => $infoArray['custnum']),
             array('name' => 'primary_address_street', 'value' => $infoArray['addr']),
             array('name' => 'primary_address_city', 'value' => $infoArray['city']),
             array('name' => 'primary_address_state', 'value' => $infoArray['state']),
             array('name' => 'primary_address_postalcode', 'value' => $infoArray['zip']),
             array('name' => 'phone_home', 'value' => $infoArray['phone'])
             ),
     );
 	  $json = json_encode($parameters);
 	  $postArgs = array(
 	    'method' => 'set_entry',
 	    'input_type' => 'JSON',
 	    'response_type' => 'JSON',
 	    'rest_data'=> $json,
 	  );
    $postArgs = http_build_query($postArgs);
    // Make the REST call, returning the result
    $response = $client->callRest($url, $postArgs);
    if ( $response === false ){
       die("Request failed.\n");
     }
     // Convert the result from JSON format to a PHP array
     $result = json_decode($response);

     if ( !is_object($result) )
     {
       die("Error handling result.\n");
     }
     if ( !isset($result->id) )
     {
         die("Error: {$result->name} - {$result->description}\n.");
     }
 	return($result);
  }
  /**
   * Make several new records within SuiteCRM
   *
   * @param string $module S0tring of module to update entry to ("Contacts", "Accounts", "Leads")
   * @param array  $infoArray Array that contains all the information for creating the SuiteCRM entries. Array of arrays
   *
   * @return array The newly updated entry is returned in an array.
   */
  function newRecords($module,$infoArray){
      function maxStrLen($str, $length)
      {
        if(strlen($str)<=$length){
          return $str;
        }else{
          $shortStr=substr($str,0,$length) . '...';
          return $shortStr;
        }
      }
     $url = $this->url;
     $client = new \SugarHttpClient;
     $name_value_array = array();
     $duplicate_email_array = array();

     foreach($infoArray as $i => $object){

       if(empty($object)||strlen($object['custnum'])<1){continue;}else{echo($object['custnum']."<br>");}

       $duplicate_counter = 0;
       $my_email = strtolower(iconv("UTF-8", "ISO-8859-1//IGNORE", $object['email']));

       while(True){

         if (strlen($my_email)<1){break;}

         if (!in_array(strtolower($my_email),$duplicate_email_array)){
           $duplicate_email_array[] = strtolower($my_email);
           break;

         }else{

           $duplicate_counter+=1;
           $my_email = "duplicate_".$duplicate_counter.iconv("UTF-8", "ISO-8859-1//IGNORE", strtolower($object['email']));
         }
       }
     $my_fname = $object['fname'];
     $my_lname = $object['lname'];
     $my_lname = iconv("UTF-8", "ISO-8859-1//IGNORE", $my_lname);
     $my_custnum = iconv("UTF-8", "ISO-8859-1//IGNORE", $object['custnum']);
     $my_phone = $object['phone'];
     $name_value_array[$i]= array(
       array('name' => 'first_name','value' => $my_fname),
       array('name' => 'last_name', 'value' => $my_lname),
       array('name' => 'email1', 'value' => $my_email),
       array('name' => 'custnum_c', 'value' => $object['custnum']),
       array('name' => 'phone_home', 'value' => $object["phone"]),
       array('name' => 'primary_address_street', 'value' =>$object["addr"]),
       array('name' => 'primary_address_city', 'value' =>$object["city"]),
       array('name' => 'primary_address_state', 'value' => $object["state"]),
       array('name' => 'description', 'value' => maxStrLen($object["comment"], 200)),
       array('name' => 'primary_address_postalcode', 'value' => $object["zip"])
     );
   }
     $parameters = array(
       'session' => $this->session_id, //Session ID
       'module' => $module,  //Module name
       'name_value_list' => $name_value_array
     );

   $json = json_encode($parameters);
   if(!$json){
     var_dump($name_value_array);
   }
   $postArgs = array(
     'method' => 'set_entries',
     'input_type' => 'JSON',
     'response_type' => 'JSON',
     'rest_data'=> $json,
    );
   $response = $client->callRest($url, $postArgs);
   if ( $response === false ){
     die("Request failed.\n");
  }
   $result = json_decode($response);
   if (!is_object($result)) {
     die("Error handling result.");
   }
   return($result);
  }

}



 ?>
