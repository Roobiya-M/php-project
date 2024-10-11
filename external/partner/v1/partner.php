<?php

/**
 * *******************************************************
 * Author: Roobiya M
 * Copyright:  IT Solution Private Limited
 * Version: 1.0
 * Date: 22-December-2023
 * FileName: partner.php
 * Description: Routine to provide access to third party team to access our api
 * ********************************************************
 */

header("Content-Type:application/json");
error_reporting(E_ALL);

require_once ('../../../include/const.inc.php');
require_once ('../../../utilities/helpers.php');
require_once ('../../../utilities/redisutilities.php');
require_once ('../../../utilities/JwtHelper.php');
require_once ('../../../utilities/partnerutilities.php');
require_once ('../../../utilities/mereapneutilities.php');
require_once ('../../../utilities/productutilities.php');




function rest_get($request)
{
    $parts = parse_url($request);
	$path_parts = pathinfo($parts['path']);
	$id = $path_parts['filename'];

    hlog("partners authorization data".json_encode($_GET),PARTNERS_LOGS);
	switch ($id) {
		case 'authorization':
			  
			  /*if(empty($_GET['data']))
			  {
			  	 $response = [
                    'error' => "Missing Parameters",
                    'errorcode' => 400,
                    'httpcode' => 400
                    ];
                commonError($response);
			  }	*/

			  $request = $_GET;
              $params = ['data'];
              checkRequiredParam($request, $params);

			  error_log(json_encode($_GET));

              $decodedata = base64_decode($_GET['data']);
              $decodedata = json_decode($decodedata,true);

              error_log(json_encode($decodedata));

              if((empty($decodedata['username'])) || (empty($decodedata['password'])) || (empty($decodedata['key'])))
              {
              	 HTTPFailWithCode(400,'Encoded data dosent contain all mandatory Parameters');
              }	

              hlog("partners authorization decoded data".json_encode($decodedata),PARTNERS_LOGS);

			  $result = partnerAuthorization($decodedata);
			  hlog("partner authorization result".json_encode($result),PARTNERS_LOGS);

			break;

		case 'planoffering':
             
              $jwtHelper = new JwtHelper();

			  $tokenData = $jwtHelper::getInstance()
			      ->setToken()
				  ->isValid();
			  $customer = (object) $tokenData['data'];
			  $customer = (array)$customer;

			  $request = $_GET;
              $params = ['page'];
              checkRequiredParam($request, $params);

			  $partnerdetails['username'] = $customer['username'];
			  $partnerdetails['password'] = $customer['password'];

			  /*if(($partners = getPartnersDetails($partnerdetails)) && (!empty($partners['error'])))
			  {
			  	 commonError($partners);
			  }*/
              //verify partner before accessing to our plans
			  if(($partners = getPartnersDetails($partnerdetails,NULL,TRUE)) && (!empty($partners['error'])))
			  {
			  	 commonError($partners);
			  }

			  $result = getMereApnePlans($_GET);



		break;	
		
		default:
			 $response = [
                    'error' => "Forbidden Request",
                    'errorcode' => 403,
                    'httpcode' => 403
                    ];
                    commonError($response);
			break;
	}

	print json_encode($result);
	exit(0);
}

function rest_error()
{
	HTTPFailWithCode(405,VHITS_METHOD_NOT_ALLOWED_ERROR);
}

//First check what is the method
if(!isset($_SERVER['REQUEST_METHOD'])||!isset($_SERVER['REQUEST_URI']))
{

	HTTPFailWithCode(400,'HTTP Method or request URI is not set');
}

$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];

switch($method)
{
	case 'GET':
		rest_get($request);
		break;
	default:
		rest_error($request);
		break;
}
exit(0);

 ?>