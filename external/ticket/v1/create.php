<?php
/**
 * *******************************************************
 * Author: Roobiya M
 * Copyright:IT Solution Private Limited
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


function restPost($uri, $customer)
{

    hlog("ticket post data>>>>>>>>>>>>>>>>>>>>>>>>>>".json_encode($_POST),VHITS_MERE_APNE_LCO_LOGS);
    hlog("customer token data>>>>>>>>>>>>>>>>>>>>>>>>>>".json_encode($customer),VHITS_MERE_APNE_LCO_LOGS);
    
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
    $customerType = null;
    if(!empty($customer->customer_type)){
        $customerType = $customer->customer_type;
    }
     $result = createTicket($customer->lcoid, $request, $customerType);
		
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