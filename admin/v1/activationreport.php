<?php
/**
 * *******************************************************
 * Author: Roobiya M
 * Copyright: Roobiya M
 * Version: 1.0
 * Date: 09-AUG-2022
 * FileName: lcoreport.php
 * Description: Routine for download lco list
 * ********************************************************
 */
ob_start();
header('Content-Type: application/csv');
header("Cache-Control: no-cache,no-store,must-revalidate"); // HTTP 1.1
header("Pragma:no-cache"); // HTTP 1.0
header("Expires:0"); // Proxies

require_once ('../../include/const.inc.php');
require_once ('../../utilities/helpers.php');
require_once ('../../utilities/redisutilities.php');
require_once ('../../utilities/lcoutilities.php');
require_once ('../../utilities/productutilities.php');
function rest_get($request)
{
	$parts = parse_url($request);
	$path_parts = pathinfo($parts['path']);
	$id = $path_parts['filename'];

	if(!(sessionValidate(VHITS_ADMIN_ROLEID)))
	{
		HTTPFailWithCode(401,'No valid session exist');
	}

	if($_SESSION[VHITS_ADMIN_TYPE]=='SUBUSER')
	{
		$permission = explode(':',$_SESSION[VHITS_ADMIN_PERMISSION]);
		error_log("permission===>".json_encode($permission));
		if(!in_array('report',$permission))
		{
			HTTPFailWithCode(402,'No permission to perform this operation');
		}
	}

	error_log("adminactivationdownloaddata===>".json_encode($_GET));

	$param = array ();
	$param['username'] = NULL;
	$param['customerid'] = NULL;
	$param['activationtype'] = NULL;
	$param['productid'] = NULL;
	$param['productname'] = NULL;
	$param['activationstatus'] = NULL;
	$param['initiatortype'] = NULL;
	$param['fromdate'] = NULL;
	$param['todate'] = NULL;
	$param['archive'] = NULL;

	foreach($_GET as $key => $value)
	{
		if((array_key_exists($key,$param))&&(!empty($value)))
		{
			$param[$key] = $value;
		}
	}

	if (empty($param['fromdate']) && !empty($_GET['start'])) {
		$param['fromdate'] = $_GET['start'];
	}
	if (empty($param['todate']) && !empty($_GET['end'])) {
		$param['todate'] = $_GET['end'];
	}

	$postfix = NULL;

	if(!empty($_GET['postfix']))
	{
		$postfix = $_GET['postfix'];
	}

	switch($id)
	{
		case 'activationreport':
			listActivationDownload($param,$postfix);
			break;

		case 'archivedactivationreport':
			listArchivedActivationDownload($param);
			break;

		default:
			HTTPFailWithCode(400,'Invalid request');
			break;
	}

	exit(0);
}

// First check what is the method
if(!isset($_SERVER['REQUEST_METHOD'])||!isset($_SERVER['REQUEST_URI']))
{
	HTTPFailWithCode(400,'HTTP Method or request URI is not set');
}

$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];

if($method!='GET')
{
	HTTPFailWithCode(405,'HTTP Method not allowed');
}

echo rest_get($request);

exit(0);
?>