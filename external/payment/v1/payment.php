<?php
/**
 * *******************************************************
 * Author: Roobiya M
 * Copyright: IT Solution Private Limited
 * Version: 1.0
 * Date: 28-November-2023
 * FileName: payment.php
 * Description: Routine to provide payment related details.
 * ********************************************************
 */

header('Content-Type:application/json');
error_reporting(E_ALL);

require_once ('../../../include/const.inc.php');
require_once ('../../../utilities/helpers.php');
require_once ('../../../utilities/redisutilities.php');
require_once ('../../../utilities/mailutilities.php');
require_once ('../../../utilities/smsutilities.php');


function restPost($uri)
{
    $request = $_POST;
    hlog("payment post data>>>>>>>>>>>>>>>>>>>>>>>>>>".json_encode($_POST),VHITS_MERE_APNE_LCO_LOGS);
    switch ($uri) {
        case 'make-payment':
            $params = ['customerid','transactionid','amount','purpose','initiator'];
            checkRequiredParam($request, $params);
            if(($bank = getActiveBankWithFieldName('bankid',$request['initiator']))&&isset($bank['error']))
            {
                error_log("Invalid bank");
                $response = [
                    'error' => 'Invalid Initiator',
                    'errorcode' => 400
                ];
                commonError($response);
            }
            $data = [
                'initiator'=> $request['initiator'],
                'customerid'=> $request['customerid'],
                'transactionid'=> $request['transactionid'],
                'amount'=> (float)$request['amount'],
                'purpose'=> $request['purpose'],
                'paymentmode'=> 'OP',
                'reason' => 'Whatsapp Chatbot bill payment posting'
            ];
            if($data['purpose'] == 'NXTSTB')
            {
                $data['purpose'] = 'STB';
            }
            if($data['purpose'] == 'NXTSUB')
            {
                $data['purpose'] = 'SUB';
            }
            $result = GenericReversePayment($data);
        
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

// $lco = null;
// if ($uri !== 'authorize') {
//     $jwtHelper = new JwtHelper();
//     $tokenData = $jwtHelper::getInstance()
//       ->setToken()
//       ->isValid();
//     $lco = (object) $tokenData['data'];
// }

switch ($method) {
    case 'GET':
        restGet();
        break;
    case 'POST':
        restPost($uri);
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
