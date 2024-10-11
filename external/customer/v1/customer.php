<?php

/*********************************************************
Author:     Roobiya M
Copyright:  Roobiya M
Version:    1.0
Date:       22-Dec-22
FileName:   customer.php
Description:For MereApne API
**********************************************************/

header('Content-Type:application/json');
error_reporting(E_ALL);

require_once '../../../include/const.inc.php';
require_once '../../../utilities/helpers.php';
require_once '../../../utilities/redisutilities.php';
require_once '../../../utilities/JwtHelper.php';
require_once '../../../utilities/smsutilities.php';

//release changes
require_once ('../../../utilities/deviceutilities.php');
//end


function restGet($uri, $customer)
{
    $request = $_GET;
    hlog("customer get data>>>>>>>>>>>>>>>>>>>>>>>>".json_encode($_GET),VHITS_MERE_APNE_CUSTOMER_LOGS);
    if(!empty($customer))
    {    
         $customer = (array)$customer;
         hlog("customer token data>>>>>>>>>>>".json_encode($customer),VHITS_MERE_APNE_CUSTOMER_LOGS);
    }    
    switch ($uri) {
        case 'login':
            $params = ['customer_id', 'initiator'];
            checkRequiredParam($request, $params);
            
            if ($request['initiator'] !== 'MA') {
                $result = [
					'errorcode' => 2000,
					'error' => 'Invalid initiator value'
				];
				commonError($result);
            }
            
            $customerDetail = getCustomerDetail(VHITS_HANSEN_SUPER_USER_NAME, $request['customer_id'],null,null,null,null,null,false);
            if (isset($customerDetail['error'])) {
                $response = [
                    'error' => 'Invalid/Inactive customer',
                    'errorcode' => 1013
                ];
                commonError($response);
            }

            if($customerDetail['customerEntityType'] != 'LCO')
            {
              commonError(formatError(17009));
            }  
            //send otp to customer
            $smsData = sendMereApneOtp($request['customer_id'], $customerDetail['mobileno']);
            hlog("smsdata>>>>>>>>>>>".json_encode($smsData),VHITS_MERE_APNE_CUSTOMER_LOGS);
            if (isset($smsData['error'])) {
                $response = [
                    'error' => 'Could not send SMS',
                    'errorcode' => 1006
                ];
                commonError($response);
            }
            $result = ['success' => $request['customer_id']];
            break;

        case 'profile':

               if(($profiledata = getCustomerDetail(VHITS_HANSEN_SELFCARE_SUPER_USER_NAME,$customer['customer_id']))&&isset($profiledata['error']))
               {
                    error_log(json_encode($profiledata));
                    $response = [
                    'error' => $profiledata['error'],
                    'errorcode' => $profiledata['errorcode']
                    ];
                    commonError($response);
               }

               if($profiledata['customerEntityType'] != 'LCO')
               {
                  commonError(formatError(17009));
               } 

                error_log("profile data>>>>>>>>>>>>>>".json_encode($profiledata));
                hlog("customer profile data>>>>>>>>>>".json_encode($profiledata),VHITS_MERE_APNE_CUSTOMER_LOGS);

                $customerprofiledata = array();

                $customerprofiledata['customer_id']     = $profiledata['customerid']; 
                $customerprofiledata['customer_number'] = $profiledata['serialno'];
                $customerprofiledata['customer_name']   = $profiledata['firstname'].' '.$profiledata['surname'];
                $customerprofiledata['mobile_no']       = $profiledata['mobileno'];
                $customerprofiledata['email']           = $profiledata['email'];
                $customerprofiledata['customer_type']   = 'LCO';
                $customerprofiledata['type']            = $profiledata['customertype'];
                $customerprofiledata['lcoid']           = $profiledata['lcoid'];
                //release changes
                $address = [];                
                $address_key = ['address1', 'address2', 'postalcode', 'city', 'state'];
                for($a = 0; $a < count($address_key); $a++) {
                    if (array_key_exists($address_key[$a],$profiledata) ) {
                        $address[] = $profiledata[$address_key[$a]];
                    }
                }
                if (!empty($address)) {
                    $customerprofiledata['address'] = implode(", ", $address);
                }
                //end
                $result = $customerprofiledata;

              break; 

          case 'planactive':

                //$result = getCustomerActivePlans($customer['customer_id']);
                if(($customerdetail = getCustomerDetail(VHITS_HANSEN_SELFCARE_SUPER_USER_NAME,$customer['customer_id']))&&isset($customerdetail['error']))
                 {
                    commonError($customerdetail);
                    
                 }
                 else
                 {
                    $customer['subscriber_id'] = $customerdetail['customerid'];
                    $customer['customer_type'] = $customerdetail['hwproductname'] === 'High Definition' ? 'HD' : 'SD';
                 }

                 if($customerdetail['customerEntityType'] != 'LCO')
                 {
                    commonError(formatError(17009));
                 } 

                 $planData = getMereApneCustomerPlanDeatils($customer);
                 if ($planData['status'] !== 200) {
                        commonError($planData);
                 }

                //$ticketCount = getSubscriptionTicketCount($customer['subscriber_id']);
                $data = [
                        'subscriber_id' =>  $customerdetail['customerid'], 
                        'customer_type' => $customer['customer_type']
                    ];
                //$subscriptionData = getTraiSubscriptionPrice($data);
                //$isExistRequest = $ticketCount > 0 ? 'true' : 'false';
                $result = [
                    //'status' => 200,
                    'menu_update_on' => (string)strtotime(date('Y-m-d H:i:s')),
                    //'is_existing_request' => $isExistRequest,
                    'total_channels' => $planData['totalChannels'],
                    'total_free_channels' => $planData['totalFreeChannels'],
                    'total_paid_channels' => $planData['totalPaidChannels'],
                    'total_bouquet' => count($planData['bouquetChannels']),
                    'total_alacarte' => count($planData['singleChannels']),
                    'subscription_price' => moneyFormat($planData['totalPrice']),
                    'avail_balance' => moneyFormat($customerdetail['subscriberbalance']),
                    //'activationdate' => $subscriptionData['activation_date'],
                    'bouquet' => $planData['bouquetChannels'],
                    'channels' => $planData['singleChannels']
                ];
                hlog("customer active plan data>>>>>>>>>>>".json_encode($result),VHITS_MERE_APNE_CUSTOMER_LOGS);

              break; 
              //release changes      
        case 'verify':
            $params = ['customer_id'];
            checkRequiredParam($request, $params);
            if(($customerDetail = getCustomerDetail(VHITS_HANSEN_SELFCARE_SUPER_USER_NAME,$request['customer_id']))&&isset($customerDetail['error']))
            {
                error_log(json_encode($customerDetail));
                $response = [
                'error' => $customerDetail['error'],
                'errorcode' => $customerDetail['errorcode']
                ];
                commonError($response);
            }
            if(($lcoData = getRegisteredLcoDetailsByFieldName('lcoid', $customerDetail['lcoid'])) && (!empty($lcoData['error'])))
            {
                commonError($lcoData);
            }
            //data for JWT token
            $tokenData = [
                'customer_id' => $customerDetail['customerid'],
                'stb_number' => $customerDetail['serialno'],
                'customer_name' => $customerDetail['firstname'].' '.$customerDetail['surname'],
                'mobile_number' => $customerDetail['mobileno'],
                'email' => $customerDetail['email'],
                'customer_type' => $customerDetail['customerEntityType'],
                'lcoid' => $customerDetail['lcoid'],
                'lconame' => $lcoData['firstname'].' '.$lcoData['surname'],
                'lcousername' => $lcoData['username']
            ];
            
            $jwtHelper = new JwtHelper();
            $accessToken = $jwtHelper::getInstance()
                ->setData($tokenData)
                ->setPayload()
                ->generateToken();

            $customerData = [
                'customer_id' => $customerDetail['customerid'],
                'lcoid' => $customerDetail['lcoid'],
                'lconame' => $lcoData['firstname'].' '.$lcoData['surname'],
                'customer_type' => $customerDetail['customerEntityType']
            ];

            $result = ['status' => 'success', 'data'=>$customerData,'access_token' => $accessToken];
        break;  
        //end       
        default: 
            commonError();
            break;
    }
    
    return asJson($result);
}

function restPost($uri, $customer)
{
    
    hlog("customer jwt token data>>>>>".json_encode($customer),VHITS_MERE_APNE_CUSTOMER_LOGS);
    hlog("customer activation data>>>>>".json_encode($_POST),VHITS_MERE_APNE_CUSTOMER_LOGS);
    $customer = (array)$customer;
    switch ($uri) {
        case 'verifyotp':

            /*if((empty($request['customer_id'])) || (empty($request['otp_pin'])))
            {
                //HTTPFailWithCode(400,"Missing Parameters");
                $response = [
                    'error' => "Missing Parameters",
                    'errorcode' => 400,
                    'httpcode' => 400
                    ];
                commonError($response);
            } */

            $request = $_POST;
            $params = ['customer_id','otp_pin'];
            checkRequiredParam($request, $params); 

            $customerDetail = getCustomerDetail(VHITS_HANSEN_SELFCARE_SUPER_USER_NAME, $request['customer_id'],null,null,null,null,null,false);
            if (isset($customerDetail['error'])) {
                $response = [
                    'error' => 'Invalid data provided, please try again',
                    'errorcode' => 1013
                ];
                commonError($response);
            }  

            if($customerDetail['customerEntityType'] != 'LCO')
            {
                commonError(formatError(17009));
            } 


            //verify customer OTP
            $data = verifyMereApneOtp($request['customer_id'], $request['otp_pin']);
            if (isset($data['error'])) {
                commonError($data);
            }

            //data for JWT token
            $tokenData = [
				'customer_id' => $customerDetail['customerid'],
                'stb_number' => $customerDetail['serialno'],
                'customer_name' => $customerDetail['firstname'].' '.$customerDetail['surname'],
                'mobile_number' => $customerDetail['mobileno'],
                'email' => $customerDetail['email'],
                'customer_type' => 'FR',
                'type' => $customerDetail['customertype'],
				'lcoid' => $customerDetail['lcoid']
			];
			
			$jwtHelper = new JwtHelper();
			$accessToken = $jwtHelper::getInstance()
				->setData($tokenData)
				->setPayload()
                ->generateToken();
            
            $result = ['status' => 'success', 'access_token' => $accessToken];
            break;
        case 'plan':
             
            error_log("token data====".json_encode($customer));
            
            /*if((empty($_POST['activation_type'])) || (empty($_POST['planlist'])))
            {
                $response = [
                    'error' => "Missing Parameters",
                    'errorcode' => 400,
                    'httpcode' => 400
                    ];
                commonError($response);
                
            }*/

            $request = $_POST;
            $params = ['activation_type','planlist'];
            checkRequiredParam($request, $params); 

            /*if(!in_array($_POST['initiator'], PARTNERS_INITIATORS))
            {
                $result = formatError(17010);
                commonError($result);
            }*/    
            //validate customer before proceeding
            if(($customerDetail = getCustomerDetail(VHITS_HANSEN_SELFCARE_SUPER_USER_NAME, $customer['customer_id'],null,null,null,null,null,false)) && (!empty($customerDetail['error'])))
            {
                    commonError($customerDetail);
            }

            if($customerDetail['customerEntityType'] != 'LCO')
            {
                commonError(formatError(17009));
            } 

            switch ($_POST['activation_type']) {
                case 'NEW':
                      //format the input as per our need
                       $formatteddata = array();
                     
                       $planlist = json_decode($_POST['planlist'],true);
                       $newplanlist = array();
                       foreach ($planlist as $value) {
                            $newlist = array();
                            $newlist['productid'] = $value['plan_code'];
                            $newplanlist[] = $newlist;
                       }

                       $formatteddata['productlist'] = json_encode($newplanlist);
                      $response = productSubscribeHandlerForCustomer($customer['customer_id'],$formatteddata,'NEW','MA');
                      if(!empty($response['error']))
                      {
                        commonError($response);
                      }  

                      error_log("activation response>>>>>>>>>>>".json_encode($response));
                     
                      $result = array();
                      foreach ($response as $value) {
                        $newoutput = array();
                        $newoutput['plan_code'] = $value['productid'];
                        $newoutput['plan_name'] = $value['productname'];
                        $newoutput['plan_type'] = $value['producttype'];
                        $newoutput['contract_id'] = $value['contractid'];
                        $newoutput['expiry'] = $value['expiry'];
                        $newoutput['status'] = $value['productstatus'];
                        $newoutput['errorcode'] = $value['errorcode'];
                        $newoutput['reason'] = $value['error'];

                        $result[] = $newoutput;

                      }
                      hlog("activation result>>>>>".json_encode($result),VHITS_MERE_APNE_CUSTOMER_LOGS);
                    break;

                case 'RENEW':
                case 'EXPIRED':
                    $formatteddata = array();
                     
                    $planlist = json_decode($_POST['planlist'],true);
                    $newplanlist = array();
                    foreach ($planlist as $value) {
                        if (empty($value['plan_code']) || empty($value['contractid'])) {
                            $response = [
                                'error' => "Missing Paramaters",
                                'errorcode' => 400,
                                'httpcode' => 400
                            ];
                            commonError($response);
                            break;
                        }
                        $newlist = array();
                        $newlist['productid'] = $value['plan_code'];
                        $newlist['contractid'] = $value['contractid'];
                        $newplanlist[] = $newlist;
                    }

                    $formatteddata['productlist'] = json_encode($newplanlist);
                    $response = productSubscribeHandlerForCustomer($customer['customer_id'],$formatteddata,$_POST['activation_type'],'MA');
                    hlog("renewal result>>>>>".json_encode($response),VHITS_MERE_APNE_CUSTOMER_LOGS);

                    if(!empty($response['error']))
                    {
                        commonError($response);
                    }  

                    error_log("renewal response>>>>>>>>>>>".json_encode($response));
                    
                    $result = array();
                    foreach ($response as $value) {
                        $newoutput = array();
                        $newoutput['plan_code'] = $value['productid'];
                        $newoutput['plan_name'] = $value['productname'];
                        $newoutput['plan_type'] = $value['producttype'];
                        $newoutput['contract_id'] = $value['contractid'];
                        $newoutput['expiry'] = $value['expiry'];
                        $newoutput['status'] = $value['productstatus'];
                        $newoutput['errorcode'] = $value['errorcode'];
                        $newoutput['reason'] = $value['error'];

                        $result[] = $newoutput;

                    }
                    break;    

                case 'PKCHANGE':
                       
                       
                      if(($customerDetail = getCustomerDetail(VHITS_HANSEN_SELFCARE_SUPER_USER_NAME, $customer['customer_id'],null,null,null,null,null,false)) && (!empty($customerDetail['error'])))
                      {
                            commonError($customerDetail);
                      } 

                      $changebasepackdata = array();
                      $changebasepackdata['customerid'] = $customer['customer_id'];
                      $changebasepackdata['productid'] = $_POST['planlist']['plan_code'];
                      $changebasepackdata['lcoid'] = $customerDetail['lcoid'];
                      $changebasepackdata['initiator'] = 'MA';

                      $result = scheduleChangeBasePack($changebasepackdata,$customer['customer_id'],'CUSTOMER');
                      hlog("customer changebasepack result>>>>>".json_encode($result),VHITS_MERE_APNE_CUSTOMER_LOGS);
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
            
            break;

            /*case 'changebasepack':

                if((empty($_POST['new_productid'])) && (empty($_POST['initiator'])))
                {
                    $response = [
                    'error' => "Missing Parameters",
                    'errorcode' => 400,
                    'httpcode' => 400
                    ];
                    commonError($response);
                } 

                 if(!in_array($_POST['initiator'], PARTNERS_INITIATORS))
                 {
                    $result = formatError(17010);
                    commonError($result);
                 }      
          
                //validate customer before proceeding
                if(($customerDetail = getCustomerDetail(VHITS_HANSEN_SELFCARE_SUPER_USER_NAME, $customer['customer_id'],null,null,null,null,null,false)) && (!empty($customerDetail['error'])))
                {
                    commonError($customerDetail);
                } 

                $changebasepackdata = array();
                $changebasepackdata['customerid'] = $customer['customer_id'];
                $changebasepackdata['productid'] = $_POST['new_productid'];
                $changebasepackdata['lcoid'] = $customerDetail['lcoid'];
                $changebasepackdata['initiator'] = 'MA';

                $result = scheduleChangeBasePack($changebasepackdata,$customer['customer_id'],'CUSTOMER');
                hlog("customer changebasepack result>>>>>".json_encode($result),VHITS_MERE_APNE_CUSTOMER_LOGS);
            break;*/
        case 'retrack':

            $request = $_POST;
            $statusType = FALSE;
            if(isset($_POST['statustype'])&&($_POST['statustype']==='DEACTIVE'))
            {
                $statusType = TRUE;
            }

            $lcoid = $customer['lcoid'];
            $username = VHITS_HANSEN_SELFCARE_SUPER_USER_NAME;
            $result = deviceRetrack($lcoid,$customer['customer_id'],$username,$statusType);
            if (!empty($result['error'])) {
                commonError($result);
            }
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


    return asJson($result);
}

function restPut()
{
    restError();
}

function restDelete()
{
    restError();
}

function restError()
{
    $response = [
        'errorcode' => 405,
        'error' => 'This method is not allowed',
        'httpcode' => 405
    ];
    commonError($response);
}

if (!isset($_SERVER['REQUEST_METHOD']) || !isset($_SERVER['REQUEST_URI'])) {
    commonError();
}

$method = $_SERVER['REQUEST_METHOD'];
$requestUrl = $_SERVER['REQUEST_URI'];
$parseUrl = parse_url($requestUrl);
$urlArray = pathinfo($parseUrl['path']);
$uri = $urlArray['filename'];

$customer = null;
//release changes
if ($uri !== 'login' && $uri !== 'verifyotp' && $uri !== 'verify') {
    $jwtHelper = new JwtHelper();

    $tokenData = $jwtHelper::getInstance()
      ->setToken()
	  ->isValid();
	$customer = (object) $tokenData['data'];
}

switch ($method) {
    case 'GET':
        restGet($uri, $customer);
        break;
    case 'POST':
        restPost($uri, $customer);
        break;
    case 'PUT':
        restPut();
        break;
    case 'DELETE':
        restDelete();
        break;
    default:
        restError();
        break;
}