<?php
/**
 * *******************************************************
 * Author: Roobiya M
 * Copyright: IT Solution Private Limited
 * Version: 1.0
 * Date: 28-November-2023
 * FileName: create.php
 * Description: Routine to provide create ticket
 * ********************************************************
 */

header('Content-Type:application/json');
error_reporting(E_ALL);

require_once ('../../../include/const.inc.php');
require_once ('../../../utilities/helpers.php');
require_once ('../../../utilities/redisutilities.php');
require_once ('../../../utilities/mailutilities.php');
require_once ('../../../utilities/smsutilities.php');
require_once ('../../../utilities/soapclientutilities.php');
require_once ('../../../utilities/hansenutilities.php');
require_once ('../../../utilities/lcoutilities.php');
require_once ('../../../utilities/msoutilities.php');
require_once ('../../../utilities/msoutilities.php');
require_once ('../../../utilities/paymentutilities.php');
require_once ('../../../utilities/bslutilities.php');
require_once ('../../../utilities/bslrestapiutilities.php');
require_once ('../../../utilities/s3utilities.php');
require_once ('../../../utilities/JwtHelper.php');
require_once ('../../../utilities/productutilities.php');
require_once ('../../../utilities/customerutilities.php');
require_once ('../../../utilities/partnerutilities.php');
require_once ('../../../utilities/ticketutilities.php');
require_once ('../../../utilities/mereapneutilities.php');
require_once ('../../../utilities/deviceutilities.php');

function restPost($uri, $customer)
{

    hlog("retrack post data>>>>>>>>>>>>>>>>>>>>>>>>>>".json_encode($_POST),VHITS_MERE_APNE_LCO_LOGS);
    hlog("customer token data>>>>>>>>>>>>>>>>>>>>>>>>>>".json_encode($customer),VHITS_MERE_APNE_LCO_LOGS);
    
    $request = $_POST;
    $params = ['serialno'];
    checkRequiredParam($request, $params);

    if (strlen($request['serialno']) > 10) {
        if ((!empty($customer->stb_number)) && ($customer->stb_number != $request['serialno'])) {
            error_log("Serial No Mismatch");
            $response = [
                'error' => 'Invalid STB Number',
                'errorcode' => 400
            ];
            commonError($response);
        }
    } else {
        if ((!empty($customer->customer_id)) && ($customer->customer_id != $request['serialno'])) {
            error_log("Customer ID Mismatch");
            $response = [
                'error' => 'Invalid CustomerID',
                'errorcode' => 400
            ];
            commonError($response);
        }
    }
    $statusType = FALSE;
    if(isset($_POST['statustype'])&&($_POST['statustype']==='DEACTIVE'))
    {
        $statusType = TRUE;
    }

    $lcoid = $customer->lcoid;
    $username = strtolower($customer->lcousername);

    $customerType = null;
    if(!empty($customer->customer_type)){
        $customerType = $customer->customer_type;
    }

    $result = deviceRetrack($lcoid,$_POST['serialno'],$username,$statusType,null,null,false,$customerType);
    if (!empty($response['error'])) {
        commonError($response);
    }
		
    return asJson($result);
}
function restGet()
{
    restError();
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
$jwtHelper = new JwtHelper();
$tokenData = $jwtHelper::getInstance()
    ->setToken()
    ->isValid();
$customer = (object) $tokenData['data'];

switch ($method) {
    case 'GET':
        restGet();
        break;
    case 'POST':
        restPost($uri,$customer);
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