<?php
/**
 * *******************************************************
 * Author : Roobiya M
 * Copyright : Roobiya
 * Version : 1.0
 * Date : 09-aug-2022
 * FileName : activation.php
 * Description: Routine for listing lco activations.
 * ********************************************************
 */
header("Content-Type:application/json");
error_reporting(E_ALL);

require_once ('../../include/const.inc.php');
require_once ('../../utilities/helpers.php');
require_once ('../../utilities/redisutilities.php');
require_once ('../../utilities/lcoutilities.php');
require_once ('../../utilities/productutilities.php');
function rest_get($request)
{

	// Get Subscriber Id
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

	error_log("adminactivationsearchdata===>".json_encode($_GET));

	if(isset($_GET['fromdate']))
	{
		$start = $_GET['fromdate'];
		if(strtotime($start)<strtotime(HITS_ARCIVE_DATE))
		{
			error_log("get===>s");
			print json_encode(array ('error'=>'Historic data before '.HITS_ARCIVE_DATE.' is not available. Please download the data as archive',"errorcode"=>'1022' ));
			exit(0);
		}
	}

	if(isset($_GET['todate']))
	{
		$end = $_GET['todate'];
		if(strtotime($end)<strtotime(HITS_ARCIVE_DATE))
		{
			error_log("get===>e");
			print json_encode(array ('error'=>'Historic data before '.HITS_ARCIVE_DATE.' is not available. Please download the data as archive',"errorcode"=>'1022' ));
			exit(0);
		}
	}

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

	foreach($_GET as $key => $value)
	{
		if((array_key_exists($key,$param))&&(!empty($value)))
		{
			$param[$key] = $value;
		}
	}

	$page = 1;
	if(!empty($_GET['page']))
	{
		$page = intval($_GET['page']);
	}

	$postfix = NULL;

	if(!empty($_GET['postfix']))
	{
		$postfix = $_GET['postfix'];
	}
	switch($id)
	{
		case 'activation':
			$result = listActivation($param,$page,$postfix);
			break;

		case 'archivedactivation':
			$result = listArchivedActivation($param,$page);
			break;

		case 'count':
			$result = getActivationCount($param,$postfix);
			break;

		case 'tablecount':
			$result = array ("success"=>VHITS_SHARDING_ACTIVATION_TABLE_NO );
			break;

		default:
			HTTPFailWithCode(400,'Invalid request');
			break;
	}

	if(!empty($result['error'])&&$result['error']==='EMPTY')
	{
		return;
	}
	print json_encode($result);
}
function rest_error()
{
	HTTPFailWithCode(405,VHITS_METHOD_NOT_ALLOWED_ERROR);
}

// First check what is the method
if(!isset($_SERVER['REQUEST_METHOD'])||!isset($_SERVER['REQUEST_URI']))
{

	HTTPFailWithCode(400,'HTTP Method or request URI is not set');
}

$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];

// if morethan one method Then use SWITCH statement.

if($method=='GET')
{
	rest_get($request);
}
else
{
	rest_error();
}

exit(0);
?>
