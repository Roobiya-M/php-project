<?php
/**
 * *******************************************************
 * Author: Roobiya M
 * Copyright: IT Solution Private Limited
 * Version: 1.0
 * Date: 14-December-2020
 * FileName: lco.php
 * Description: Routine to provide lco activation/renewals/changebasepack access to thrid aprty
 * ********************************************************
 */

header('Content-Type:application/json');
error_reporting(E_ALL);

require_once ('../../../include/const.inc.php');
require_once ('../../../utilities/helpers.php');
require_once ('../../../utilities/redisutilities.php');
require_once ('../../../utilities/mailutilities.php');
require_once ('../../../utilities/smsutilities.php');


function restGet($uri, $lco)
{  
   hlog("lco token data>>>>>>>>>>".json_encode($lco),VHITS_MERE_APNE_LCO_LOGS);
   hlog("lco get data>>>>>>>>>>".json_encode($_GET),VHITS_MERE_APNE_LCO_LOGS);
   switch($uri)
   {
        case 'authorize':
            $request = $_GET;
            $params = ['hash', 'data', 'initiator'];
            checkRequiredParam($request, $params);
            
            $decodeData = base64_decode($request['data']);
            $data = json_decode($decodeData, TRUE);
            if (!is_array($data) || empty($data['lcousername'])) {
                $result = [
                    'errorcode' => 2000,
                    'error' => 'Invalid data value'
                ];
                commonError($result);
            }
            
            if (sha1($decodeData) !== $request['hash']) {
                $result = [
                    'errorcode' => 2000,
                    'error' => 'Invalid hash value'
                ];
                commonError($result);
            }

            if ($request['initiator'] !== 'MA') {
                $result = [
                    'errorcode' => 2000,
                    'error' => 'Invalid initiator value'
                ];
                commonError($result);
            }
            
            $authenticationdata['username'] = VHITS_HANSEN_ADMIN_SUPER_USER_NAME;
            $authenticationdata['password'] = VHITS_HANSEN_ADMIN_SUPER_USER_PROOF;
            if(($authentication = authenticateOperator($authenticationdata))&&isset($authentication['error']))
            {
                commonError($authentication);
            }
            
            $userDetail = getRegisteredLcoDetailsByFieldName('username', $data['lcousername']);
            if (!empty($userDetail['error'])) {
                $result = [
                    'errorcode' => 1002,
                    'error' => 'Invalid data provided, please try again'
                ];
                commonError($result);
            }
            
            if ($userDetail['lcostatus'] === "INACTIVE" || $userDetail['lcostatus'] === "VERIFY") {
                $result = [
                    'errorcode' => 1003,
                    'error' => 'Invalid/Inactive lco provided'
                ];
                commonError($result); 
            }
            
            $response = [
                'lconame' => $userDetail['firstname'],
                'lcousername' => $userDetail['username'],
                'balance' => $userDetail['balance'],
                'lcoid' => $userDetail['lcoid'],
            ];
            $tokenData = [
                'lcoid' => $userDetail['lcoid'],
                'lcousername' => $userDetail['username'],
                //release changes
                'customer_type' => 'LCO'
            ];
            
            $jwtHelper = new JwtHelper();
            $accessToken = $jwtHelper::getInstance()
                ->setData($tokenData)
                ->setPayload()
                ->generateToken();
            
            $response = [
                'data' => base64_encode(json_encode($response)),
                'access_token' => $accessToken
            ];
            break;
        case 'wallet':
            $lcoData = getRegisteredLcoDetailsByFieldName('username', $lco->lcousername);
            if (isset($lcoData['error'])) {
                $response = [
                    'error' => 'Invalid data provided, please try again',
                    'errorcode' => 1013
                ];
                commonError($response);
            }
            
            $response = [
                'balance' => $lcoData['balance'],
                'currency' => 'INR'
            ];
            break;

        case 'planactive':

             /*if(empty($_GET['customer_id']))
             {
                $response = [
                    'error' => "Missing Parameters",
                    'errorcode' => 400,
                    'httpcode' => 400
                    ];
                commonError($response);
             }*/    

             /*$page = 1;

             

                error_log("Customer Id===>".$customerid);
             
             
             $response = listLCOCustomerProduct($lco->lcoid,$customerid,$param,$page,$lco->lcousername,'MA');*/

             $request = $_GET;
             $params = ['customer_id'];
             checkRequiredParam($request, $params);

             if(($lcoData = getRegisteredLcoDetailsByFieldName('username', $lco->lcousername)) && (!empty($lcoData['error'])))
             {
                commonError($lcoData);
             }  

             if(($customerdetail = getCustomerDetail($lco->lcousername,$_GET['customer_id']))&&isset($customerdetail['error']))
             {
                commonError($customerdetail);
                
             }
             else
             {
                $customer['subscriber_id'] = $customerdetail['customerid'];
                $customer['customer_type'] = $customerdetail['hwproductname'] === 'High Definition' ? 'HD' : 'SD';
             }

             if($customerdetail['lcoid'] != $lcoData['lcoid'])
             {
                commonError(formatError(2062));
             }  

			 $planData = getMereApneCustomerPlanDeatilsNew($lcoData,$customerdetail);
            
			 $response = [
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

			
			 
		    //  $planData = getMereApneCustomerPlanDeatils($customer);
	        //  if ($planData['status'] !== 200) {
	        //         commonError($planData);
	        //  }

            // //$ticketCount = getSubscriptionTicketCount($customer['subscriber_id']);
            // $data = [
            //         'subscriber_id' =>  $customerdetail['customerid'], 
            //         'customer_type' => $customer['customer_type']
            //     ];
            // //$subscriptionData = getTraiSubscriptionPrice($data);
            // //$isExistRequest = $ticketCount > 0 ? 'true' : 'false';
            // $response = [
            //     //'status' => 200,
            //     'menu_update_on' => (string)strtotime(date('Y-m-d H:i:s')),
            //     //'is_existing_request' => $isExistRequest,
            //     'total_channels' => $planData['totalChannels'],
            //     'total_free_channels' => $planData['totalFreeChannels'],
            //     'total_paid_channels' => $planData['totalPaidChannels'],
            //     'total_bouquet' => count($planData['bouquetChannels']),
            //     'total_alacarte' => count($planData['singleChannels']),
            //     'subscription_price' => moneyFormat($planData['totalPrice']),
            //     'avail_balance' => moneyFormat($customerdetail['subscriberbalance']),
            //     //'activationdate' => $subscriptionData['activation_date'],
            //     'bouquet' => $planData['bouquetChannels'],
            //     'channels' => $planData['singleChannels']
            // ];	

		break;

		case 'planofferings':

             /*if(empty($_GET['page']))
             {
                $response = [
                    'error' => "Missing Parameters",
                    'errorcode' => 400,
                    'httpcode' => 400
                    ];
                commonError($response);
             }  */

             $request = $_GET;
             $params = ['page'];
             checkRequiredParam($request, $params);

             if(($lcoData = getRegisteredLcoDetailsByFieldName('username', $lco->lcousername)) && (!empty($lcoData['error'])))
             {
                $response = [
                    'error' => $lcoData['error'],
                    'errorcode' => $lcoData['errorcode'],
                    ];
                commonError($response);
             }  

             $response = getMereApnePlans($_GET);

		break;   
	    case 'product-disconnect' :
		case 'product-reconnect' :
				$request = $_GET;
				$params = ['customer_id'];
				checkRequiredParam($request, $params);

				$param = [
					'productname' => NULL,
					'producttypename' => NULL,
					'producttypeid' => NULL,
					'customerclassid' => NULL,
					'customertypeid' => NULL,
					'postalcode' => NULL,
					'producttype' => NULL,
					'purpose' => 'cancel'
				];

				$customerid = NULL;
				
				if(($customerdetail = getCustomerDetail($lco->lcousername,$_GET['customer_id']))&&isset($customerdetail['error']))
				{
					commonError($customerdetail);
					
				}
				else
				{
					$customerid = $customerdetail['customerid'];
					$param['customerclassid'] = $customerdetail['customerclassid'];
					$param['customertypeid'] = $customerdetail['customertypeid'];
					$param['postalcode'] = $customerdetail['postalcode'];
				}
				error_log("Customer Id===>".$customerid);
				foreach($_GET as $key => $value)
				{
					if(array_key_exists($key,$param))
					{
						if(!empty($value))
						{
							$param[$key] = $value;
						}
					}
				}

				$page = 1;
				if(!empty($_GET['page']))
				{
					$page = intval($_GET['page']);
				}

				$standalone = false;
				if(!empty($_GET['standalone']))
				{
					if($_GET['producttype']=='ALACARTE')
					{
						$standalone = true;
					}
				}
				if(empty($customerid))
				{
					HTTPFailWithCode(400,"Missing Parameter");
				}
				if($uri == 'product-disconnect')
				{
					$result = listProductToDisconnect($lco->lcoid,$customerid,$param,$page,$lco->lcousername);
					$response = array ();
					foreach ($result as $thisnewproduct) {
						if (strtolower($thisnewproduct['producttypename']) === 'ott standalone') {
							continue;
						}
						$response[] = $thisnewproduct;
					}
					error_log("Disconnect Product List====>".json_encode($response));
				} else {
					$response = listProductToReconnect($lco->lcoid,$customerid,$param,$page,$lco->lcousername,$customerdetail,'MA');
					error_log("Reconnect Product List====>".json_encode($response));
				}
		break;
		case 'subscriberdetails':
			$request = $_GET;
			$params = ['customer_id'];
			checkRequiredParam($request, $params);
            //release changes
            if ((!empty($lco->customer_id)) && ($lco->customer_id != $request['customer_id'])) {
                error_log("Customer ID Mismatch");
                $response = [
                    'error' => 'Invalid CustomerID',
                    'errorcode' => 400
                ];
                commonError($response);
            }
            //end
			if (
				($lcoData = getRegisteredLcoDetailsByFieldName('username', $lco->lcousername)) && 
				(!empty($lcoData['error']))
			) {
			 	commonError($lcoData);
			}
			if(
				($profiledata = getCustomerDetail($lco->lcousername, $request['customer_id'])) &&
				isset($profiledata['error'])
			) {
				error_log(json_encode($profiledata));
				$response = [
				'error' => $profiledata['error'],
				'errorcode' => $profiledata['errorcode']
				];
				commonError($response);
            }

			error_log("profile data>>>>>>>>>>>>>>".json_encode($profiledata));
			hlog("customer profile data>>>>>>>>>>".json_encode($profiledata),VHITS_MERE_APNE_CUSTOMER_LOGS);

			$customerprofiledata = array();
			$customerprofiledata['customer_id']     = $profiledata['customerid']; 
			$customerprofiledata['customer_number'] = $profiledata['serialno'];
			$customerprofiledata['customer_name']   = $profiledata['firstname'].' '.$profiledata['surname'];
			$customerprofiledata['mobile_no']       = $profiledata['mobileno'];
			$customerprofiledata['email']           = $profiledata['email'];
            //release changes
			// $customerprofiledata['customer_type']   = 'LCO';
            $customerprofiledata['customer_type']   = $profiledata['customerEntityType'];
            //end
			$customerprofiledata['type']            = $profiledata['customertype'];
			$customerprofiledata['lcoid']           = $profiledata['lcoid'];
			
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

			$planData = getMereApneCustomerPlanDeatilsNew($lcoData, $profiledata);
            //release changes
            $bouquetChannels =[];
            foreach($planData['bouquetChannels'] as $item) {
                $itemArray = [];
                $itemArray += ["bouquet_id"=>$item['bouquet_id'],"bouquet_name"=>$item['bouquet_name'],"category"=>$item['category'],"expiry_date"=>$item['expiry_date'],"status"=>$item['status']];
                // array_push($itemArray,$item['bouquet_id'],$item['bouquet_name'],$item['expiry_date'],$item['status']);
                array_push($bouquetChannels,$itemArray);

            }
            $singleChannels =[];
            foreach($planData['singleChannels'] as $channelItem) {
                $itemArray = [];
                $itemArray += ["channel_id"=>$channelItem['channel_id'],"channel_name"=>$channelItem['channel_name'],"category"=>$channelItem['category'],"expiry_date"=>$channelItem['expiry_date'],"status"=>$channelItem['status']];
                // array_push($itemArray,$channelItem['channel_id'],$channelItem['channel_name'],$channelItem['expiry_date'],$channelItem['status']);
                array_push($singleChannels,$itemArray);

            }
			$products = [
				// 'bouquet' => $planData['bouquetChannels'],
				// 'channels' => $planData['singleChannels']
                'bouquet' => $bouquetChannels,
                'channels' => $singleChannels 
			];
			$customerprofiledata['productlist'] = $products;	
			$response = $customerprofiledata;
		break;	
		default:
			commonError();
			break;
   }
   
   return asJson($response);
}

function restPost($uri, $lco)
{

    hlog("lco activationdata>>>>>>>>>>>>>>>>>>>>>>>>>>".json_encode($_POST),VHITS_MERE_APNE_LCO_LOGS);
    hlog("lco token data>>>>>>>>>>>>>>>>>>>>>>>>>>".json_encode($lco),VHITS_MERE_APNE_LCO_LOGS);
    switch ($uri) {
        case 'plan':

        /*if((empty($_POST['activation_type'])) || (empty($_POST['planlist']))  || (empty($_POST['customer_id'])))
        {
            $response = [
                    'error' => "Missing Parameters",
                    'errorcode' => 400,
                    'httpcode' => 400
                    ];
                commonError($response);
        }*/ 

        /*if(!in_array($_POST['initiator'], PARTNERS_INITIATORS))
        {
            $result = formatError(17010);
            commonError($result);
        }*/
        
        $request = $_POST;
        $params = ['activation_type','planlist','customer_id'];
        checkRequiredParam($request, $params);

        if(($lcoData = getRegisteredLcoDetailsByFieldName('username', $lco->lcousername)) && (!empty($lcoData['error'])))
        {
            commonError($lcoData);
        }

        if(($customerdetail = getCustomerDetail($lco->lcousername,$_POST['customer_id']))&&isset($customerdetail['error']))
        {
            commonError($customerdetail);
                
        }

        if($customerdetail['lcoid'] != $lcoData['lcoid'])
        {
            commonError(formatError(2062));
        }   

        switch ($_POST['activation_type']) {
            case 'NEW':

                $initiatordetail = array ();
                $initiatordetail['initiatorid'] = $lco->lcoid;
                $initiatordetail['initiatortype'] = "LCO";
                $initiatordetail['username'] = $lco->lcousername;

                 //format the input as per our need
                 $formatteddata = array();
                 $formatteddata ['customerid'] = $_POST['customer_id'];
                 
                 $planlist = json_decode($_POST['planlist'],true);
                 $newplanlist = array();
                 foreach ($planlist as $value) {
                    $newlist = array();
                    $newlist['productid'] = $value['plan_code'];
                    $newplanlist[] = $newlist;
                 }

                 $formatteddata['productlist'] = json_encode($newplanlist);

                 $response = productSubscribeHandler($lco->lcoid,$formatteddata,"NEW",$initiatordetail,false,true,'MA');
                 
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

                 hlog("lco activation result>>>>>>>>>>>>>>>>>>>".json_encode($result),VHITS_MERE_APNE_LCO_LOGS);

                break;

			case 'RENEW':
			case 'EXPIRED':

                $initiatordetail = array ();
                $initiatordetail['initiatorid'] = $lco->lcoid;
                $initiatordetail['initiatortype'] = "LCO";
                $initiatordetail['username'] = $lco->lcousername;

				$formatteddata = array();
	            $formatteddata ['customerid'] = $_POST['customer_id'];
	             
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
					 if (empty($value['plan_code']) || empty($value['contractid'])) {
						$response = [
							'error' => "Missing Paramaters",
							'errorcode' => 400,
							'httpcode' => 400
						];
						commonError($response);
						break;
					}

	             	$newplanlist[] = $newlist;
				}

                $formatteddata['productlist'] = json_encode($newplanlist);

			    $response = productSubscribeHandler($lco->lcoid,$formatteddata,$_POST['activation_type'],$initiatordetail,false,true,'MA');
			     hlog("lco renewal result>>>>>>>>>>>>>>>>".json_encode($response),VHITS_MERE_APNE_LCO_LOGS);

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

                    $changebasepackdata = array();
                    $changebasepackdata['customerid'] = $_POST['customer_id'];
                    $changebasepackdata['productid'] = $_POST['planlist']['plan_code'];
                    $changebasepackdata['initiator'] = 'MA';

                    $result = scheduleChangeBasePack($changebasepackdata,$lco->lcoid,'LCO');
                    hlog("lco change base pack result>>>>>>>>>>".json_encode($result),VHITS_MERE_APNE_LCO_LOGS);

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

            if((empty($_POST['customer_id']))||(empty($_POST['new_productid'])) || ((empty($_POST['initiator']))))
            {
               $response = [
                    'error' => "Missing Parameters",
                    'errorcode' => 400,
                    'httpcode' => 400
                    ];
                commonError($response);
            }

            $changebasepackdata = array();
            $changebasepackdata['customerid'] = $_POST['customer_id'];
            $changebasepackdata['productid'] = $_POST['new_productid'];
            $changebasepackdata['initiator'] = 'MA';

            $result = scheduleChangeBasePack($changebasepackdata,$lco->lcoid,'LCO');
            hlog("lco change base pack result>>>>>>>>>>".json_encode($result),VHITS_MERE_APNE_LCO_LOGS);


		break;*/
		case 'product-disconnect':
		case 'product-reconnect':
			$initiatordetail = array ();
			$initiatordetail['initiatorid'] = $lco->lcoid;
			$initiatordetail['initiatortype'] = "MA";
			$initiatordetail['username'] = $lco->lcousername;

			if($uri == 'product-reconnect')
			{
				$initiatordetail['activationtype'] = "RECONNECT";
			} else {
				$initiatordetail['activationtype'] = "DISCONNECT";
			}
			 
			error_log("Params====>".json_encode($_POST));
			error_log("initiator detail====>".json_encode($initiatordetail));
			$page = 1;
			if(!empty($_GET['page']))
			{
				$page = intval($_GET['page']);
			}
			$customerid = $_POST['customerid'];
			$param =['customer_id'=>$_POST['customerid']];
			$disconnectlist = listProductToReconnect($lco->lcoid,$customerid,$param,$page,$lco->lcousername,null,'MA');
			hlog("disconnected plan list>>>>>>>>>>".json_encode($disconnectlist),VHITS_MERE_APNE_LCO_LOGS);			
			if (isset($_POST['productlist']) && $uri == 'product-disconnect') {
				$productList = json_decode($_POST['productlist'],true);
				foreach($productList as $thisproduct){
					foreach($disconnectlist as $prodval){
						if($prodval['productid'] == $thisproduct['productid']){
							hlog('already disconnected',VHITS_MERE_APNE_LCO_LOGS);
							$msg = 'The product with ID '.$thisproduct['productid'].' is already disconnected!';
							$response = [
									'error' => $msg,
									'errorcode' => 400,
									'httpcode' => 400
									];
							commonError($response);
						}

					}

				}
			}
				
			$result = productSubscribeHandler($lco->lcoid,$_POST,$initiatordetail['activationtype'],$initiatordetail);
		break;
		case 'createtkt':
			$request = $_POST;
			$params = [
				'category', 
				'subcategoryid', 
				'subcategoryid2', 
				'categoryname', 
				'subcategoryname', 
				'subsubcategoryname', 
				'description', 
				'contactmethod'
			];
			checkRequiredParam($request, $params);
			$result = createTicket($lco->lcoid, $request);
		break;
		case 'retrack':

			$request = $_POST;
			$params = ['serialno'];
			checkRequiredParam($request, $params);

			$statusType = FALSE;
			if(isset($_POST['statustype'])&&($_POST['statustype']==='DEACTIVE'))
			{
				$statusType = TRUE;
			}

			$lcoid = $lco->lcoid;
			$username = strtolower($lco->lcousername);
			$result = deviceRetrack($lcoid,$_POST['serialno'],$username,$statusType);
			if (!empty($response['error'])) {
				commonError($response);
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

$lco = null;
if ($uri !== 'authorize') {
    $jwtHelper = new JwtHelper();
    $tokenData = $jwtHelper::getInstance()
      ->setToken()
      ->isValid();
    $lco = (object) $tokenData['data'];
}

switch ($method) {
    case 'GET':
        restGet($uri, $lco);
        break;
    case 'POST':
        restPost($uri,$lco);
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
