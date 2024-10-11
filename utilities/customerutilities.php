<?php
/**
 * *******************************************************
 * Author: Manish Giri
 * Copyright: Mobiotics IT Solution Private Limited
 * Version: 1.0
 * Date: 28-Jul-2015
 * FileName: customerutilities.php
 * Description: Utilities to access customer Data
 * ********************************************************
 */
error_reporting(E_ALL);
date_default_timezone_set(INDIAN_TIME_ZONE);
require_once VHITS_HOME_SOURCE_PATH.'html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Exception\ExceptionFormatter;
function createCustomerValidation($initatorid,$data,$username,$usertype = 'LCO')
{
	error_log('validation function');
	/*
	 * $customerMandatoryData = array('firstname','surname','addressid','bigcity',
	 * 'smallcity','postalcode','hwserialno','hwdeviceid','hwproductid',
	 * 'swproductid','mobileno','titleid','provinceid','countryid',
	 * 'customertypeid','customerclassid','address1');
	 */

	$customerMandatoryData = array ('firstname','surname','addressid','bigcity','smallcity','postalcode','hwserialno','hwdeviceid','hwproductid','swproductid','mobileno','titleid','provinceid','countryid','customertypeid','address1' );

	foreach($customerMandatoryData as $mandatorydata)
	{
		if(empty($data[$mandatorydata]))
		{
			hlog_errorlog("Empty value for===>".$mandatorydata);
			return formatError(2000);
		}
		if($mandatorydata=='email')
		{
			if(!filter_var($data[$mandatorydata],FILTER_VALIDATE_EMAIL))
			{
				return formatError(1050);
			}
		}
		if(($mandatorydata=='firstname')||($mandatorydata=='surname'))
		{
			$regex = '/^[A-Za-z]{2}/';
			if(!preg_match($regex,$data[$mandatorydata]))
			{
				return formatError(2070);
			}
		}
		if($mandatorydata=='mobileno')
		{
			if((!is_numeric($data[$mandatorydata]))||(strlen($data[$mandatorydata])!=10))
			{
				return formatError(1051);
			}
		}
	}
	if($usertype=='DPO')
	{
		$dpocustomerdata = array ("customerclassid","entityid" );
		foreach($dpocustomerdata as $dpodata)
		{
			if(empty($data[$dpodata]))
			{
				return formatError(2000);
			}
			if($dpodata=='customerclassid')
			{
				if(($data[$dpodata]!=3)&&($data[$dpodata]!=49))
				{
					return formatError(11001);
				}
			}
			if($dpodata=='entityid')
			{
				hlog_errorlog(json_encode($data));
				if(($entities = listDpoEntity($initatorid,null,$data[$dpodata]))&&(!empty($entities['error'])))
				{
					return formatError(11003);
				}
				hlog_errorlog(json_encode($entities));

				if(empty($entities[0]['dpoentitycustomerid']))
				{
					if(($entitycustomerid = updateDpoEntity($initatorid,$data[$dpodata],$data['entitycustomerid']))&&(!empty($entitycustomerid['error'])))
					{
						return $entitycustomerid;
					}
				}
			}
		}
	}
	return array ("success"=>"Valid" );
}
function createCustomerInitiate($lcoid,$data,$usertype = 'LCO',$username,$jobid = null)
{
	//1. validation
	if(($validation = createCustomerValidation($lcoid,$data,$usertype))&&(!empty($validation['error'])))
	{
		return $validation;
	}

	$lcodetail = array ();
	//2. initatorvadation
	if($usertype=='DPO')
	{
		$lcodetail['username'] = $data['entityid'];
		$lcodetail['lcoid'] = $lcoid;
		$lcodetail['initiatortype'] = 'DPO';
		$lcodetail['postalcode'] = $data['postalcode'];
		$dpousername = $username;
		$username = $data['entityid'];
	}
	else
	{
		if(($lcodetail = getRegisteredLcoDetailsByFieldName('lcoid',$lcoid,$jobid))&&(!empty($lcodetail['error'])))
		{
			return $lcodetail;
		}

		if(($msodetail = getRegisteredMSODetailsByFieldName('msoid',$lcodetail['msoid'],$jobid))&&(!empty($msodetail['error'])))
		{
			error_log($msodetail['error']);
		}
	}

	//2. duplicate mobile number validation
	//if(($mobileno = listCustomerDetails(array("mobileno"=>$data['mobileno']),1)) && (!isset($mobileno['error'])))
	//{
	//return formatError(1020);
	//}

	//3. duplicate email validation
	if(!empty($data['email']))
	{
		if(($email = listCustomerDetails(array ("lcoid"=>$lcoid,"email"=>$data['email'] ),1,$jobid))&&(!isset($email['error'])))
		{
			return formatError(1021);
		}
	}

	//5. get hardware product detail
	if(($hwdata = getProductDetailByProductid($lcoid,$data['hwproductid'],$jobid))&&(!empty($hwdata['error'])))
	{
		return $hwdata;
	}
	else
	{
		//6. adding deviceid and device serialnumber
		$hwdata['deviceid'] = $data['hwdeviceid'];
		$hwdata['serialno'] = $data['hwserialno'];
	}

	//7. get software product detail
	if(($plandata = getProductDetailByProductid($lcoid,$data['swproductid'],$jobid))&&(!empty($plandata['error'])))
	{
		return $plandata;
	}
	else
	{
		$plandata['activationtype'] = "NEW";
		$plandata['activationstatus'] = "PENDING";
	}

	if(empty($data['initiatortype']))
	{
		$data['initiatortype'] = 'LCO';
	}

	if($usertype=='DPO')
	{
		$data['initiatortype'] = 'DPO';
		$lcodetail['username'] = $data['entityid'];
	}

	hlog_errorlog("initior=====>".$data['initiatortype']);

	if(($productdetail = getLcoProducts($lcodetail['username'],$data['swproductid'],$jobid,$data['initiatortype'],$lcodetail['postalcode']))&&(!empty($productdetail['error'])))
	{
		return $productdetail;
	}
	$planprice = 0;
	//$productdetail = json_decode($productdetail,true);

	error_log("============>".json_encode($productdetail));
	foreach($productdetail as $key => $value)
	{
		if($value['productid']==$data['swproductid'])
		{
			hlog_errorlog(json_encode($value),$jobid);
			$planprice = $value['totallcoprice'];
		}
	}
	if($usertype!=='DPO')
	{
		if($lcodetail['balance']<$planprice)
		{
			return formatError(2058);
		}
	}

	//removed as per thomas,
	/*
	 * if((isset($msodetail['amount'])) && (!empty($msodetail['msotype']) && ($msodetail['msotype'] !== 'GIL')))
	 * {
	 * if($msodetail['amount'] < $planprice)
	 * {
	 * return formatError(2065);
	 * }
	 * }
	 */

	$customerdata = array ();

	if(!empty($data['birthdate']))
	{
		$customerdata['birthdate'] = $data['birthdate']."T01:01:01.001";
	}

	$customerdata['countryid'] = $data['countryid'];
	$customerdata['bigcity'] = $data['bigcity'];
	$customerdata['firstname'] = $data['firstname'];
	$customerdata['postalcode'] = $data['postalcode'];
	$customerdata['smallcity'] = $data['smallcity'];
	$customerdata['surname'] = $data['surname'];
	$customerdata['addressid'] = $data['addressid'];
	$customerdata['customerclassid'] = VHITS_CUSTOMER_CLASS_ID;

	if(!empty($data['customerclassid']))
	{
		$customerdata['customerclassid'] = $data['customerclassid'];
		/*
		 * if($data['customerclassid']==48)
		 * {
		 * $customerdata['customertype'] = 'PREPAID';
		 * }
		 */
		//hot patch done by sunil on request by thomas to change class id to 3 for prepaid customer on 15-06-18
		if($data['customerclassid']==3)
		{
			$customerdata['customertype'] = 'PREPAID';
		}
		if($data['customerclassid']==49)
		{
			$customerdata['customertype'] = 'POSTPAID';
		}
	}
	if(substr($username,0,3)=='LCO')
	{
		$customerdata['accounttypeid'] = 21;
	}
	if(substr($username,0,3)=='DPO')
	{
		$customerdata['customertypeid'] = 3;
	}
	if($usertype=='DPO')
	{
		$customerdata['customertypeid'] = 3;
	}
	$customerdata['customertypeid'] = $data['customertypeid'];
	if($usertype=='DPO')
	{
		$customerdata['customertypeid'] = 3;
	}
	$customerdata['mobileno'] = $data['mobileno'];
	$customerdata['provinceid'] = $data['provinceid'];
	$customerdata['titleid'] = $data['titleid'];
	$customerdata['lcousername'] = $lcodetail['username'];

	if(!empty($data['entityid']))
	{
		$customerdata['dpoentity'] = $data['entityid'];
	}
	$customerdata['entityid'] = $lcoid;
	$customerdata['entitytype'] = 'LCO';

	if($data['initiatortype']=='DPO')
	{
		$customerdata['entityid'] = $lcoid;
		$customerdata['entitytype'] = 'DPO';
	}

	if($data['initiatortype']=='ADMIN')
	{
		$initiatortype = 'ADMIN';
		$customerdata['initiator'] = 'ADMIN';
	}
	else
	{
		if($data['initiatortype']!='DPO')
		{
			$initiatortype = substr($username,0,3);
		}
		else
		{
			$initiatortype = 'DPO';
		}
		$customerdata['initiator'] = $username;
	}

	if(!empty($data['email']))
	{
		$customerdata['email'] = $data['email'];
	}

	if(!empty($data['address1']))
	{
		$customerdata['address1'] = $data['address1'];
	}

	if(!empty($data['address2']))
	{
		$customerdata['address2'] = $data['address2'];
	}

	if(!empty($data['idproof']))
	{
		$customerdata['idproof'] = $data['idproof'];
		$customerdata['addressproof'] = $data['addressproof'];
		$customerdata['crf'] = $data['crf'];
	}

	if(!empty($data['jobid']))
	{
		$customerdata['jobid'] = $data['jobid'];
	}
	// for dpo customer, exemption code should be 0, Thomas told us to amde changes on 4th of june 2019.
	if($data['initiatortype']=='DPO')
	{
		$customerdata['exemptioncode'] = 0;
	}
	else
	{
		// As per Thomas exemptioncode is 0 if GST number is der. 09/10/2018
		$customerdata['exemptioncode'] = 1;
		//check if the LCO has entered his GST number and it is valid. If valid, ExemptionCodeId = 0, else ExemptionCodeId = 0
		if((!empty($lcodetail['gstno']))&&(strlen($lcodetail['gstno'])==15))
		{
			//GST number is valid
			$customerdata['exemptioncode'] = 0;
		}
	}

	//deduct money
	/*
	 * $totalprice = 0;
	 * $zeroprice = 0;
	 *
	 * //get lco financial details
	 * if(($lcocreditdetail = getFinancialAccount($lcoid,$lcoid)) && (!empty($lcocreditdetail['error'])))
	 * {
	 * return $lcocreditdetail;
	 * }
	 *
	 * //price validation
	 * $totalprice = floatval($plandata['price']) + floatval($hwdata['price']);
	 *
	 * if(floatval($totalprice) > floatval($zeroprice))
	 * {
	 * //make payment
	 * error_log("========Price Validation==========");
	 * error_log("LCOID===>".$lcoid."===>CREDIT===>".$lcocreditdetail['balance']."===>CHARGE===>".$totalprice);
	 *
	 * if(floatval($totalprice) > floatval($lcocreditdetail['balance']))
	 * {
	 * return formatError(1098);
	 * }
	 * }
	 */

	// if(($customercreatestatus = createCustomerByProcessBundle($customerdata,$lcodetail,$hwdata,$plandata)) && isset($customercreatestatus['error']))
	// {
	// 	hlog($lcodetail['lcoid'].LLOG_SEPARATOR."NULL".LLOG_SEPARATOR.$customerdata['firstname'].LLOG_SEPARATOR."CREATED".LLOG_SEPARATOR."FAILED",VHITS_CUSTOMER_LOG);
	// 	hlog($lcodetail['lcoid'].LLOG_SEPARATOR."NULL".LLOG_SEPARATOR."FAILED".LLOG_SEPARATOR.json_encode($data).LLOG_SEPARATOR.json_encode($customercreatestatus),VHITS_CUSTOMER_CREATED_DATA_LOG);
	// 	return $customercreatestatus;
	// }
	$lcouser = $username;
	if($usertype=="DPO")
	{
		$lcodetail['lcoid'] = $lcodetail['lcoid'];
		$lcodetail['usertype'] = $usertype;
	}
	if($initiatortype=='ADMIN')
	{
		$lcouser = $username;
		$username = 'ADMIN';
	}
	if($initiatortype=='DPO')
	{
		$username = $dpousername;
	}

	if(($customercreatestatus = craeteCustomerByOperator($username,$customerdata,$hwdata,$plandata,$initiatortype,$jobid))&&isset($customercreatestatus['error']))
	{
		hlog($lcodetail['lcoid'].LLOG_SEPARATOR."NULL".LLOG_SEPARATOR.$customerdata['firstname'].LLOG_SEPARATOR."CREATED".LLOG_SEPARATOR."FAILED",VHITS_CUSTOMER_LOG,VHITS_LOGLEVEL_CRITICAL);
		hlog($lcodetail['lcoid'].LLOG_SEPARATOR."NULL".LLOG_SEPARATOR."FAILED".LLOG_SEPARATOR.json_encode($data).LLOG_SEPARATOR.json_encode($customercreatestatus),VHITS_CUSTOMER_CREATED_DATA_LOG,VHITS_LOGLEVEL_CRITICAL);
		return $customercreatestatus;
	}

	$username = $lcouser;
	hlog($lcodetail['lcoid'].LLOG_SEPARATOR.$customercreatestatus['customerid'].LLOG_SEPARATOR.$customerdata['firstname'].LLOG_SEPARATOR."CREATED".LLOG_SEPARATOR."SUCCESS",VHITS_CUSTOMER_LOG,VHITS_LOGLEVEL_CRITICAL);
	hlog($lcodetail['lcoid'].LLOG_SEPARATOR.$customercreatestatus['customerid'].LLOG_SEPARATOR."SUCCESS".LLOG_SEPARATOR.json_encode($data).LLOG_SEPARATOR.json_encode($customercreatestatus),VHITS_CUSTOMER_CREATED_DATA_LOG,VHITS_LOGLEVEL_CRITICAL);
	$username = $lcouser;
	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	$mysqlcon->query("START TRANSACTION");

	//1. insert into customer table
	if(($customerinsertstatus = insertIntoCustomerTable($lcodetail,$customerdata,$customercreatestatus,$mysqlcon,$jobid))&&(!empty($customerinsertstatus['error'])))
	{
		$mysqlcon->query("ROLLBACK");
		$mysqlcon->close();
		hlog($lcodetail['lcoid'].LLOG_SEPARATOR.$customercreatestatus['customerid'].LLOG_SEPARATOR.$customerdata['firstname'].LLOG_SEPARATOR."INSERT".LLOG_SEPARATOR."FAILED",VHITS_CUSTOMER_LOG,VHITS_LOGLEVEL_CRITICAL);
		return $customerinsertstatus;
	}

	if(!empty($data['idproof']))
	{
		//2. insert into customerdoc table
		if(($customerdocinsertstatus = insertIntoCustomerdocTable($lcodetail,$data,$customercreatestatus,$mysqlcon,$jobid))&&(!empty($customerinsertstatus['error'])))
		{
			$mysqlcon->query("ROLLBACK");
			$mysqlcon->close();
			hlog($lcodetail['lcoid'].LLOG_SEPARATOR.$customercreatestatus['customerid'].LLOG_SEPARATOR."IDPROOF".LLOG_SEPARATOR.$data['idproof'].LLOG_SEPARATOR."INSERT".LLOG_SEPARATOR."FAILED",VHITS_CUSTOMER_DOC_LOG,VHITS_LOGLEVEL_CRITICAL);
			hlog($lcodetail['lcoid'].LLOG_SEPARATOR.$customercreatestatus['customerid'].LLOG_SEPARATOR."ADDRESSPROOF".LLOG_SEPARATOR.$data['addressproof'].LLOG_SEPARATOR."INSERT".LLOG_SEPARATOR."FAILED",VHITS_CUSTOMER_DOC_LOG,VHITS_LOGLEVEL_CRITICAL);
			hlog($lcodetail['lcoid'].LLOG_SEPARATOR.$customercreatestatus['customerid'].LLOG_SEPARATOR."CRF".LLOG_SEPARATOR.$data['crf'].LLOG_SEPARATOR."INSERT".LLOG_SEPARATOR."FAILED",VHITS_CUSTOMER_DOC_LOG,VHITS_LOGLEVEL_CRITICAL);
			return $customerdocinsertstatus;
		}
	}
	//4. insert into activation
	$jobdata = array ();
	$initiatordetail = array ();
	$initiatordetail['activationtype'] = "NEW";
	$initiatordetail['username'] = $username;
	$initiatordetail['entityid'] = $lcoid;
	switch($data['initiatortype'])
	{
		case 'ADMIN':
			$jobdata['initiatorid'] = $data['adminid'];
			$jobdata['initiatortype'] = "ADMIN";
			$initiatordetail['initiatorid'] = $data['adminid'];
			$initiatordetail['initiatortype'] = "ADMIN";
			$initiatordetail['entitytype'] = "LCO";
			break;
		case 'LCO':
			$jobdata['initiatorid'] = $lcoid;
			$jobdata['initiatortype'] = "LCO";
			$initiatordetail['initiatorid'] = $lcoid;
			$initiatordetail['initiatortype'] = "LCO";
			$initiatordetail['entitytype'] = "LCO";
			break;
		case 'MSO':
			$jobdata['initiatorid'] = $lcoid;
			$jobdata['initiatortype'] = "MSO";
			$initiatordetail['initiatorid'] = $lcoid;
			$initiatordetail['initiatortype'] = "LCO";
			$initiatordetail['entitytype'] = "LCO";
			break;

		case 'DPO':
			$jobdata['initiatorid'] = $lcoid;
			$jobdata['initiatortype'] = "DPO";
			$initiatordetail['initiatorid'] = $lcoid;
			$initiatordetail['initiatortype'] = "DPO";
			$initiatordetail['entitytype'] = "DPO";
			$initiatordetail['type'] = "activation";
			break;
		default:
			$jobdata['initiatorid'] = $lcoid;
			$jobdata['initiatortype'] = "LCO";
			$initiatordetail['initiatorid'] = $lcoid;
			$initiatordetail['initiatortype'] = "LCO";
			$initiatordetail['entitytype'] = "LCO";
			break;
	}

	//3. insert into device
	if(($deviceinsertstatus = insertIntoDeviceTable($lcodetail,$hwdata,$customercreatestatus,$mysqlcon,$jobid))&&(!empty($deviceinsertstatus['error'])))
	{
		$mysqlcon->query("ROLLBACK");
		$mysqlcon->close();
		hlog_errorlog($lcodetail['lcoid'].LLOG_SEPARATOR.$hwdata['deviceid'].LLOG_SEPARATOR.$hwdata['serialno'].LLOG_SEPARATOR.$customercreatestatus['customerid'].LLOG_SEPARATOR.$hwdata['productid'].LLOG_SEPARATOR."ACTIVATION".LLOG_SEPARATOR."FAILED",VHITS_DEVICE_LOG,VHITS_LOGLEVEL_CRITICAL);
		return $deviceinsertstatus;
	}

	if(($activationinsertstatus = insertIntoActivationTable($lcodetail,$plandata,$customercreatestatus,$initiatordetail,$mysqlcon,$jobid))&&(!empty($activationinsertstatus['error'])))
	{
		$mysqlcon->query("ROLLBACK");
		$mysqlcon->close();
		hlog($lcodetail['lcoid'].LLOG_SEPARATOR."NULL".LLOG_SEPARATOR.$customercreatestatus['customerid'].LLOG_SEPARATOR.$plandata['productid'].LLOG_SEPARATOR."ACTIVATION".LLOG_SEPARATOR."FAILED",VHITS_ACTIVATION_LOG,VHITS_LOGLEVEL_CRITICAL);
		return $activationinsertstatus;
	}
	$activationid = $activationinsertstatus['success'];
	$mysqlcon->query("COMMIT");
	//5. update customer table ==>initactivation,initdevice
	$updatecustomerdata = array ();
	$updatecustomerdata['initactivation'] = $activationinsertstatus['success'];
	$updatecustomerdata['initdevice'] = $deviceinsertstatus['success'];
	if(($updatecustomerstatus = updateCustomerTable($customercreatestatus['customerid'],$updatecustomerdata,NULL,NULL,$lcodetail['lcoid']))&&isset($updatecustomerstatus['error']))
	{
		hlog($customercreatestatus['customerid'].LLOG_SEPARATOR.$customerdata['firstname'].LLOG_SEPARATOR."UPDATE",VHITS_ACTIVATION_LOG,VHITS_LOGLEVEL_CRITICAL);
		return $updatecustomerstatus;
	}

	$jobdata['jobtype'] = VHITS_PROCESS_ACTIVATE_CUSTOMER;
	$jobdata['username'] = $username;
	$jobdata['lcoid'] = $lcodetail['lcoid'];
	$jobdata['metadata'] = $customercreatestatus['customerid'];
	$jobdata['queuename'] = VHITS_ACTIVATE_CUSTOMER_QUEUE_NAME;
	$jobdata['numtotal'] = 1;
	$jobdata['customerclassid'] = $customerdata['customerclassid'];

	if(($bulkreportentry = insertIntoBulkreortTable($jobdata,null,$jobid))&&(!empty($bulkreportentry['error'])))
	{
		return $bulkreportentry;
	}
	else
	{
		$jobdata['initiatortype'] = $initiatortype;
		$jobdata['jobid'] = $bulkreportentry['success'];
		$jobdata['initiatortype'] = $initiatortype;
		//Put into Queue
		$result = setDataToQueue($jobdata,$jobdata['queuename']);
		hlog($lcoid.LLOG_SEPARATOR.$customercreatestatus['customerid'].LLOG_SEPARATOR.$jobdata['jobid'].LLOG_SEPARATOR."INITIATED",VHITS_BULK_REPORT_LOG,VHITS_LOGLEVEL_CRITICAL);
	}
	//}

	return array ("customerid"=>$customercreatestatus['customerid'],"customerstatus"=>"PENDING" );
}
function bulkCreateCustomerInitiate($lcoid,$data,$username,$initiatedby = NULL)
{
	if(empty($data['filename']))
	{
		return formatError(2000);
	}
	$filename = $data['filename'];
	$originalfilename = $filename;
	$filename = VHITS_LOCAL_TEMP_FOLDER.$filename;
	$initiatortype = $initiatedby;

	error_log("This File Name===>".$filename);

	if(!file_exists($filename))
	{
		error_log("No file exist currently===>".$filename);
		return formatError(2007);
	}
	$initiatorid = $lcoid;

	//deduct money
	$totalprice = 0;
	$zeroprice = 0;

	$file = fopen($filename,"r");

	switch($initiatortype)
	{
		case 'ADMIN':
			$validation = adminLcoMsoCreateCustomerValidtion($file,$originalfilename,$username,$initiatorid,"ADMIN");
			break;
		case 'LCO':
			$validation = adminLcoMsoCreateCustomerValidtion($file,$originalfilename,$username,$initiatorid,"LCO");
			break;
		case 'MSO':
			$validation = adminLcoMsoCreateCustomerValidtion($file,$originalfilename,$username,$initiatorid,"MSO");
			break;
		case 'DPO':
			$validation = dpoCreateCustomerValidation($file,$originalfilename,$username,$initiatorid);
			break;
		default:
			return formatError(11014);
	}

	if(!empty($validation['error']))
	{
		return $validation;
	}
	return array ("success"=>$lcoid );
}
function adminLcoMsoCreateCustomerValidtion($file,$originalfilename,$username,$initiatorid,$initiatortype = 'LCO')
{
	$rowcount = 1;
	$firstrow = TRUE;
	$secondrow = TRUE;

	while(!feof($file))
	{
		$thisrow = fgetcsv($file);

		error_log("This Row Data===>".json_encode($thisrow));

		if(empty($thisrow))
		{
			break;
		}

		if($firstrow)
		{
			if(($thisrow[0]!="titleid")||($thisrow[1]!="firstname")||($thisrow[2]!="surname")||($thisrow[3]!="customertypeid")||($thisrow[4]!="postalcode")||($thisrow[5]!="hwproductid")||($thisrow[6]!="swproductid")||($thisrow[7]!="serialno")||($thisrow[8]!="mobileno")||($thisrow[9]!="address1"))
			{
				return array ("error"=>"Wrong header","errorcode"=>2000 );
			}

			$firstrow = FALSE;
			continue;
		}

		if(empty($thisrow[0])||empty($thisrow[1])||empty($thisrow[2])||empty($thisrow[3])||empty($thisrow[4])||empty($thisrow[5])||empty($thisrow[6])||empty($thisrow[7])||empty($thisrow[8])||empty($thisrow[9]))
		{
			return array ("error"=>"Missing parameter in row : ".$rowcount,"errorcode"=>2000 );
		}
		else
		{
			$secondrow = FALSE;
		}

		//titleid verification
		/*
		 * if(($titlestatus = listCustomerTitle($thisrow[0],$page=1)) && (!empty($titlestatus['error'])))
		 * {
		 * return array("error"=>$titlestatus['error']." in row : ".$rowcount,"errorcode"=>$titlestatus['errorcode']);
		 *
		 * }
		 * $lcoid = $initiatorid;
		 * if(($initiatortype == 'ADMIN') || ($initiatortype == 'MSO'))
		 * {
		 * $username = $initiatortype;
		 * $lcoid = "ADMIN";
		 * }
		 *
		 * if(($devicedetail = getAvailableDeviceidFromSerialNumber($thisrow[7],$lcoid)) && (!empty($devicedetail['error'])))
		 * {
		 * return array("error"=>$devicedetail['error']." in row : ".$rowcount,"errorcode"=>$devicedetail['errorcode']);
		 * }
		 *
		 * error_log($devicedetail["lcoid"]);
		 * $lcoid = $devicedetail['lcoid'];
		 *
		 * if(($lcodetail = getLcoDetailsByFieldName('idlco', $lcoid)) && (!empty($lcodetail['error'])))
		 * {
		 * return array("error"=>$lcodetail['error']." in row : ".$rowcount,"errorcode"=>$lcodetail['errorcode']);
		 * }
		 * $username = $lcodetail['username'];
		 *
		 * $param = array();
		 * $param['customertypeid'] = $thisrow[3];
		 *
		 * if(($lcovalidation = createCustomerParamValidation($lcoid,$param,$thisrow,$username,$rowcount)) && (!empty($lcovalidation['error'])))
		 * {
		 * return $lcovalidation;
		 * }
		 */

		$rowcount++;
	}

	if($secondrow)
	{
		return array ("error"=>"Missing parameter in row : ".$rowcount,"errorcode"=>2000 );
	}

	error_log("Total Row Count===>".$rowcount);

	if(($settocreatecustomerworker = setToCreateCustomerWorker($username,$originalfilename,$rowcount,$initiatorid,$initiatortype))&&(!empty($settocreatecustomerworker['error'])))
	{
		return $settocreatecustomerworker;
	}
	return array ("success"=>$username );
}
function setToCreateCustomerWorker($username,$originalfilename,$rowcount,$initiatorid,$initiatortype)
{
	$jobdata = array ();
	$jobdata['jobtype'] = VHITS_PROCESS_BULK_CREATE_CUSTOMER;
	$jobdata['username'] = $username;
	$jobdata['metadata'] = $originalfilename;
	$jobdata['queuename'] = VHITS_BULK_CUSTOMER_QUEUE_NAME;
	$jobdata['numtotal'] = $rowcount-1;
	$jobdata['initiatorid'] = $initiatorid;
	$jobdata['initiatortype'] = $initiatortype;
	if(($initiatortype!='DPO')&&($initiatortype!='LCO'))
	{
		$jobdata['initiatedfor'] = "LCO";
	}

	if(($bulkreportentry = insertIntoBulkreortTable($jobdata))&&(!empty($bulkreportentry['error'])))
	{
		return $bulkreportentry;
	}
	else
	{
		$jobdata['jobid'] = $bulkreportentry['success'];
		//put data in queue
		$result = setDataToQueue($jobdata,$jobdata['queuename']);
		hlog($initiatorid.LLOG_SEPARATOR.$originalfilename.LLOG_SEPARATOR.$jobdata['jobid'].LLOG_SEPARATOR."INITIATED",VHITS_BULK_REPORT_LOG,VHITS_LOGLEVEL_CRITICAL);
	}

	return true;
}
function dpoCreateCustomerValidation($file,$originalfilename,$username,$initiatorid)
{
	$rowcount = 1;
	$firstrow = TRUE;
	$secondrow = TRUE;

	while(!feof($file))
	{
		$thisrow = fgetcsv($file);

		error_log("This Row Data===>".json_encode($thisrow));

		if(empty($thisrow))
		{
			break;
		}

		if($firstrow)
		{
			if(($thisrow[0]!="titleid")||($thisrow[1]!="firstname")||($thisrow[2]!="surname")||($thisrow[3]!="customerclass")||($thisrow[4]!="postalcode")||($thisrow[5]!="hwproductid")||($thisrow[6]!="swproductid")||($thisrow[7]!="serialno")||($thisrow[8]!="mobileno")||($thisrow[9]!="address1")||($thisrow[10]!="email")||($thisrow[11]!="address2")||($thisrow[12]!="dpoentity")||($thisrow[13]!="dpoentityid"))
			{
				return array ("error"=>"Wrong header","errorcode"=>2000 );
			}
			$firstrow = FALSE;
			continue;
		}

		if(empty($thisrow[0])||empty($thisrow[1])||empty($thisrow[2])||empty($thisrow[3])||empty($thisrow[4])||empty($thisrow[5])||empty($thisrow[6])||empty($thisrow[7])||empty($thisrow[8])||empty($thisrow[9])||empty($thisrow[12])||(empty($thisrow[13])))
		{
			return array ("error"=>"Missing parameter in row : ".$rowcount,"errorcode"=>2000 );
		}
		else
		{
			$secondrow = FALSE;
		}

		//titleid verification
		if(($titlestatus = listCustomerTitle($thisrow[0],$page = 1))&&(!empty($titlestatus['error'])))
		{
			return array ("error"=>$titlestatus['error']." in row : ".$rowcount,"errorcode"=>$titlestatus['errorcode'] );
		}

		if(($lcodetail = getLcoDetailsByFieldName('idlco',$initiatorid))&&(!empty($lcodetail['error'])))
		{
			return array ("error"=>$lcodetail['error']." in row : ".$rowcount,"errorcode"=>$lcodetail['errorcode'] );
		}
		$username = $lcodetail['username'];
		$dpoentity = $thisrow[12];
		$dpoentityid = $thisrow[13];
		if(!(is_numeric($thisrow[13]))||(strlen($thisrow[13])!=10))
		{
			return array ("error"=>"Dpo entityid should be 10 digit valid customer id of DPO user ".$rowcount,"errorcode"=>2000 );
		}
		if(($dpoentityvalidation = getDpoUser($username,array ("dpoentityname"=>$dpoentity )))&&(!empty($dpoentityvalidation['error'])))
		{
			return array ("error"=>"dpo entity does not belongs to this user in row ".$rowcount,"errorcode"=>$dpoentityvalidation['errorcode'] );
		}

		//dpo customer type id will be 3
		$param = array ();
		$param['customertypeid'] = 3;
		$param['customerclass'] = $thisrow[3];

		if(($lcovalidation = createCustomerParamValidation($lcodetail['lcoid'],$param,$thisrow,$username,$rowcount))&&(!empty($lcovalidation['error'])))
		{
			return $lcovalidation;
		}

		$rowcount++;
	}

	if($secondrow)
	{
		return array ("error"=>"Missing parameter in row : ".$rowcount,"errorcode"=>2000 );
	}

	error_log("Total Row Count===>".$rowcount);

	if(($settocreatecustomerworker = setToCreateCustomerWorker($username,$originalfilename,$rowcount,$initiatorid,"DPO"))&&(!empty($settocreatecustomerworker['error'])))
	{
		return $settocreatecustomerworker;
	}

	return array ("success"=>$lcodetail['lcoid'] );
}
function createCustomerParamValidation($lcoid,$param,$thisrow,$username,$rowcount)
{
	if(empty($param['customerclass']))
	{
		if(($customerclass = listLCOCustomerType($lcoid,$param,$page = 1))&&(!empty($customerclass['error'])))
		{
			return array ("error"=>$customerclass['error']." in row : ".$rowcount,"errorcode"=>$customerclass['errorcode'] );
		}
	}

	if(!empty($thisrow[13]))
	{
		if(($dpodetails = getCustomerDetail($username,$thisrow[13],'DPO',null,null,null,'DPO'))&&(!empty($dpodetails['error'])))
		{
			return array ("error"=>"Invalid dpo entityid provided in row ".$rowcount );
		}
		if($dpodetails['username']!=$thisrow[12])
		{
			return formatError(11019);
		}
		$username = $dpodetails['username'];
		/*
		 * if($thisrow[4] != $dpodetails['postalcode'])
		 * {
		 * return array("error"=>"Invaid postalcode in row : ".$rowcount,"errorcode"=>1073);
		 * }
		 */
	}
	else
	{
		//get address detail
		if(($addressdetail = getValidAddressByPostalcodeForLCO($lcoid,$thisrow[4]))&&(!empty($addressdetail['error'])))
		{
			return array ("error"=>$addressdetail['error']." in row : ".$rowcount,"errorcode"=>$addressdetail['errorcode'] );
		}
	}

	//validate duplicate email
	if(!empty($thisrow[11]))
	{
		if(($email = listCustomerDetails(array ('lcoid'=>$lcoid,"email"=>$thisrow[11] ),1))&&(!isset($email['error'])))
		{
			return array ("error"=>"Duplicate Email in row : ".$rowcount,"errorcode"=>"1021" );
		}
	}

	//get hardware product detail
	if(!empty($thisrow[13]))
	{
		if(($hwdata = getLcoProducts($username,$thisrow[5],null,'DPO',$thisrow[4]))&&(!empty($hwdata['error'])))
		{
			return array ("error"=>"Invalid hwproductid in row : ".$rowcount,"errorcode"=>"1074" );
		}
	}
	else
	{
		if(($hwdata = getLcoProducts($username,$thisrow[5]))&&(!empty($hwdata['error'])))
		{
			return array ("error"=>"Invalid hwproductid in row : ".$rowcount,"errorcode"=>"1074" );
		}
	}

	if($thisrow[6]==40)
	{
		return array ("error"=>"Invalid swproductid in row : ".$rowcount,"errorcode"=>"1074" );
	}

	//get software product detail
	if(!empty($thisrow[13]))
	{
		if(($plandata = getLcoProducts($username,$thisrow[6],null,'DPO',$thisrow[4]))&&(!empty($plandata['error'])))
		{
			return array ("error"=>"Invalid swproductid in row : ".$rowcount,"errorcode"=>"1074" );
		}
	}
	else
	{
		if(($plandata = getLcoProducts($username,$thisrow[6]))&&(!empty($plandata['error'])))
		{
			return array ("error"=>"Invalid swproductid in row : ".$rowcount,"errorcode"=>"1074" );
		}
	}

	if((!empty($param['customerclass'])))
	{
		if(trim(strtoupper($param['customerclass']))=='PREPAID')
		{
			foreach($plandata as $key => $value)
			{
				if($value['productid']==$thisrow[6])
				{
					if(strpos($value['productname'],VHITS_POSTPAID_BASEPACK_PREFIX)!==false)
					{
						return array ("error"=>"Postpaid swproductid in row : ".$rowcount,"errorcode"=>"1074" );
					}
				}
			}
		}
		if(trim(strtoupper($param['customerclass']))=='POSTPAID')
		{
			foreach($plandata as $key => $value)
			{
				if($value['productid']==$thisrow[6])
				{
					if(strpos($value['productname'],VHITS_POSTPAID_BASEPACK_PREFIX)===false)
					{
						return array ("error"=>"Prepaid swproductid in row : ".$rowcount,"errorcode"=>"1074" );
					}
				}
			}
		}
	}
	return true;
}
function listLCOCustomerKycRejected($lcoid,$param,$page = 1)
{
	$param['lcoid'] = $lcoid;

	$result = listCustomerKycRejectedDetails($param,$page);

	return $result;
}
function listCustomerKycRejectedDetails($param,$page)
{
	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	date_default_timezone_set(INDIAN_TIME_ZONE);

	$fromdate = NULL;
	$todate = NULL;

	$param['kycstatus'] = "REJECTED";

	if(!empty($param['fromdate']))
	{
		$fromdate = $param['fromdate'];
		unset($param['fromdate']);
	}

	if(!empty($param['todate']))
	{
		$todate = $param['todate'];
		unset($param['todate']);
	}

	if(!empty($param['firstname']))
	{
		$firstname = $param['firstname'];
		unset($param['firstname']);
	}

	if(!empty($param['serialno']))
	{
		$serialno = $param['serialno'];
		unset($param['serialno']);

		if(($customer = getCustomerByDeviceSerialNumberBySuperUser($serialno))&&isset($customer['error']))
		{
			return $customer;
		}

		$param['customerid'] = $customer['customerid'];
	}

	if(($customertablename = getCustomerTableName($param['lcoid']))&&(!empty($customertablename['error'])))
	{
		return $customertablename;
	}
	if(($activationtablename = getActivationTableName($param['lcoid']))&&(!empty($activationtablename['error'])))
	{
		return $activationtablename;
	}

	// 	$query = sprintf("SELECT * FROM customer_view");

	$query = sprintf("SELECT c.idcustomer AS customerid, c.firstname AS firstname, c.surname AS surname, c.mobileno AS mobileno, c.aadhaar AS aadhaar, c.email AS email, c.jobid AS jobid, c.lcoid AS lcoid, c.reason AS reason, l.firstname AS lconame, c.customerstatus AS customerstatus, c.kycstatus AS kycstatus, c.customertypeid AS customertypeid, c.customerclassid AS customerclassid, c.registeredon AS registeredon, c.initactivation AS initactivationid, a.productid AS swproductid, a.productname AS swproductname, a.activationstatus AS activationstatus, a.contractid AS swcontractid, a.price AS price, a.initiatorid AS initiatorid, a.initiatortype AS initiatortype, c.initdevice AS initdeviceid, d.productid AS hwproductid, d.productname AS hwproductname, d.serialno AS serialno, d.devicestatus AS devicestatus, d.contractid AS hwcontractid, c.customertype AS customertype, c.errorcode AS errorcode, c.created AS created FROM (((".$customertablename." c LEFT JOIN lco l ON ((c.lcoid = l.idlco))) LEFT JOIN ".$activationtablename." a ON ((c.initactivation = a.idactivation))) LEFT JOIN device d ON ((c.initdevice = d.iddevice)))");

	foreach($param as $key => $value)
	{
		if(!empty($value))
		{
			$query .= getDBConnectorPhrase($query).sprintf("c.%s",mysql_fix_string($key,$mysqlcon));
			$query .= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
		}
	}

	if(!empty($fromdate))
	{
		$datearray = array_reverse(explode("-",$fromdate));
		//$query.=getDBConnectorPhrase($query).sprintf(" DATE(c.created)>='%s'",mysql_fix_string(implode("-",$datearray), $mysqlcon));
		$query .= getDBConnectorPhrase($query).sprintf(" c.created>='%s 00:00:00'",mysql_fix_string(implode("-",$datearray),$mysqlcon));
	}

	if(!empty($todate))
	{
		$datearray = array_reverse(explode("-",$todate));
		//$query.=getDBConnectorPhrase($query).sprintf(" DATE(c.created)<='%s'",mysql_fix_string(implode("-",$datearray), $mysqlcon));
		$query .= getDBConnectorPhrase($query).sprintf(" c.created<='%s 23:59:59'",mysql_fix_string(implode("-",$datearray),$mysqlcon));
	}

	if(!empty($firstname))
	{
		$query .= getDBConnectorPhrase($query).sprintf(" firstname LIKE '%%%s%%'",mysql_fix_string($firstname,$mysqlcon));
	}

	$query .= " ORDER BY created DESC";

	$query .= sprintf(" LIMIT %u , %u",($page-1)*VHITS_DB_PAGE_LIMIT,VHITS_DB_PAGE_LIMIT);

	error_log($query);

	if(!($result = $mysqlcon->query($query)))
	{
		error_log("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		$mysqlcon->close();
		return formatError(1001);
	}

	if($result->num_rows==0)
	{
		$mysqlcon->close();
		return formatError(1022);
	}

	$val = array ();

	while($row = $result->fetch_assoc())
	{
		$row['document'] = 'YES';
		$row['created'] = date("Y-m-d H:i:s",strtotime($row['created']));
		$val[] = $row;
	}

	$result->close();
	$mysqlcon->close();

	return $val;
}
function listLCOCustomer($lcoid,$param,$page = 1)
{
	$param['lcoid'] = $lcoid;

	$result = listCustomerDetails($param,$page);

	return $result;
}
function getCustomerDetails($customerid,$jobid = null,$lcoid)
{
	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	date_default_timezone_set(INDIAN_TIME_ZONE);

	if(($customertablename = getCustomerTableName($lcoid))&&(!empty($customertablename['error'])))
	{
		return $customertablename;
	}
	if(($activationtablename = getActivationTableName($lcoid))&&(!empty($activationtablename['error'])))
	{
		return $activationtablename;
	}
	if(($devicetablename = getDeviceTableName($lcoid))&&(!empty($devicetablename['error'])))
	{
		return $devicetablename;
	}

	$query = sprintf("SELECT c.idcustomer AS customerid, c.firstname AS firstname, c.surname AS surname, c.mobileno AS mobileno, c.aadhaar AS aadhaar, c.email AS email, c.jobid AS jobid, c.lcoid AS lcoid, c.reason AS reason, l.firstname AS lconame, c.customerstatus AS customerstatus, c.kycstatus AS kycstatus, c.customertypeid AS customertypeid, c.customerclassid AS customerclassid, c.registeredon AS registeredon, c.initactivation AS initactivationid, a.productid AS swproductid, a.productname AS swproductname, a.activationstatus AS activationstatus, a.contractid AS swcontractid, a.price AS price, a.initiatorid AS initiatorid, a.initiatortype AS initiatortype, c.initdevice AS initdeviceid, d.productid AS hwproductid, d.productname AS hwproductname, d.serialno AS serialno, d.devicestatus AS devicestatus, d.contractid AS hwcontractid, c.customertype AS customertype, c.errorcode AS errorcode, c.created AS created FROM (((".$customertablename." c LEFT JOIN lco l ON ((c.lcoid = l.idlco))) LEFT JOIN ".$activationtablename." a ON ((c.initactivation = a.idactivation))) LEFT JOIN ".$devicetablename." d ON ((c.initdevice = d.iddevice)))");
	$query .= sprintf(" WHERE c.idcustomer = '%s'",mysql_fix_string($customerid,$mysqlcon));

	hlog_errorlog($query,$jobid);

	if(!($result = $mysqlcon->query($query)))
	{
		hlog_errorlog("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error,$jobid);
		$mysqlcon->close();
		return formatError(1001);
	}

	if($result->num_rows==0)
	{
		$mysqlcon->close();
		return formatError(1022);
	}

	$customerdetail = $result->fetch_assoc();
	$customerdetail['document'] = TRUE;

	if(($customerdocdetail = getCustomerDocData($customerid,$jobid))&&(!empty($customerdocdetail['error'])))
	{
		$customerdetail['document'] = FALSE;
	}

	$result->close();
	$mysqlcon->close();

	return $customerdetail;
}
function listCustomerDetails($param,$page = 1,$jobid = null,$postfix = NULL)
{
	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	date_default_timezone_set(INDIAN_TIME_ZONE);

	$fromdate = NULL;
	$todate = NULL;

	if(!empty($param['fromdate']))
	{
		$fromdate = $param['fromdate'];
		unset($param['fromdate']);
	}

	if(!empty($param['todate']))
	{
		$todate = $param['todate'];
		unset($param['todate']);
	}

	if(!empty($param['firstname']))
	{
		$firstname = $param['firstname'];
		unset($param['firstname']);
	}

	if(!empty($param['page']))
	{
		$page = $param['page'];
		unset($param['page']);
	}
	$pagelimit = VHITS_DB_PAGE_LIMIT;
	if(!empty($param['pagelimit']))
	{
		$pagelimit = $param['pagelimit'];
		unset($param['pagelimit']);
	}

	if(!empty($param['serialno']))
	{
		$serialno = $param['serialno'];
		unset($param['serialno']);

		if(($customer = getCustomerByDeviceSerialNumberBySuperUser($serialno,$jobid))&&isset($customer['error']))
		{
			return $customer;
		}

		$param['customerid'] = $customer['customerid'];
	}

	if(!empty($param['lcoid']))
	{
		$tablename = getCustomerTableName($param['lcoid']);
	}
	else
	{
		$tablename = getCustomerTableName(NULL,$postfix);
	}
	if(!empty($tablename['error']))
	{
		return $tablename;
	}
	if(!empty($param['lcoid']))
	{
		$activationtablename = getActivationTableName($param['lcoid']);
	}
	else
	{
		$activationtablename = getActivationTableName(NULL,$postfix);
	}
	if(!empty($activationtablename['error']))
	{
		return $activationtablename;
	}

	if(!empty($param['lcoid']))
	{
		$devicetablename = getDeviceTableName($param['lcoid']);
	}
	else
	{
		$devicetablename = getDeviceTableName(NULL,$postfix);
	}
	if(!empty($devicetablename['error']))
	{
		return $devicetablename;
	}

	$filterc = array (
			'customerid'=>'idcustomer',
			'firstname'=>'firstname',
			'surname'=>'surname',
			'mobileno'=>'mobileno',
			'aadhaar'=>'aadhaar',
			'email'=>'email',
			'jobid'=>'jobid',
			'lcoid'=>'lcoid',
			'reason'=>'reason',
			'kycstatus'=>'kycstatus',
			'customertypeid'=>'customertypeid',
			'customerclassid'=>'customerclassid',
			'registeredon'=>'registeredon',
			'initactivationid'=>'initactivation',
			'initdeviceid'=>'initdevice',
			'customertype'=>'customertype',
			'errorcode'=>'errorcode',
			'dpoentity'=>'dpoentity',
			'customerstatus'=>'customerstatus',
			'entitytype'=>'entitytype' );

	$filterl = array ('lconame'=>'firstname' );
	$filtera = array ('swproductid'=>'productid','swproductname'=>'productname','price'=>'price','initiatorid'=>'initiatorid','initiatortype'=>'initiatortype' );
	$filterd = array ('hwproductid'=>'productid','hwproductname'=>'productname','serialno'=>'serialno','devicestatus'=>'devicestatus','hwcontractid'=>'contractid' );

	//$query = sprintf("SELECT * FROM customer_view ");
	$query = sprintf("SELECT c.idcustomer AS customerid, c.firstname AS firstname, c.surname AS surname, c.mobileno AS mobileno, c.aadhaar AS aadhaar, c.email AS email, c.jobid AS jobid, c.lcoid AS lcoid, c.reason AS reason, l.firstname AS lconame, c.customerstatus AS customerstatus, c.kycstatus AS kycstatus, c.customertypeid AS customertypeid, c.customerclassid AS customerclassid, c.registeredon AS registeredon, c.initactivation AS initactivationid, a.productid AS swproductid, a.productname AS swproductname, a.activationstatus AS activationstatus, a.contractid AS swcontractid, a.price AS price, a.initiatorid AS initiatorid, a.initiatortype AS initiatortype, c.initdevice AS initdeviceid, d.productid AS hwproductid, d.productname AS hwproductname, d.serialno AS serialno, d.devicestatus AS devicestatus, d.contractid AS hwcontractid, c.customertype AS customertype,c.entitytype AS entitytype ,c.errorcode AS errorcode,c.dpoentity as dpoentity, c.created AS created FROM (((".$tablename." c left join lco l ON ((c.lcoid = l.idlco))) left join ".$activationtablename." a ON ((c.initactivation = a.idactivation))) left join ".$devicetablename." d ON ((c.initdevice = d.iddevice)))");

	$query_count = sprintf("SELECT count(c.idcustomer) as count FROM (((".$tablename." c left join lco l ON ((c.lcoid = l.idlco))) left join ".$activationtablename." a ON ((c.initactivation = a.idactivation))) left join ".$devicetablename." d ON ((c.initdevice = d.iddevice)))");
	
	foreach($param as $key => $value)
	{
		if(!empty($value))
		{
			if(!empty($filterc[$key]))
			{
				$prob = getDBConnectorPhrase($query).sprintf("c.%s",mysql_fix_string($filterc[$key],$mysqlcon));
				$prob .= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
				$query .= $prob;
				$query_count .=$prob;
			}
			else if(!empty($filterl[$key]))
			{
				$prob = getDBConnectorPhrase($query).sprintf("l.%s",mysql_fix_string($filterl[$key],$mysqlcon));
				$prob .= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
				$query .= $prob;
				$query_count .=$prob;
			}
			else if(!empty($filtera[$key]))
			{
				$prob = getDBConnectorPhrase($query).sprintf("a.%s",mysql_fix_string($filtera[$key],$mysqlcon));
				$prob .= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
				$query .= $prob;
				$query_count .=$prob;
			}
			else if(!empty($filterd[$key]))
			{
				$prob = getDBConnectorPhrase($query).sprintf("d.%s",mysql_fix_string($filterd[$key],$mysqlcon));
				$prob .= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
				$query .= $prob;
				$query_count .=$prob;
			}
			else
			{
				return formatError(1013);
			}
		}
	}

	if(!empty($fromdate))
	{
		$datearray = array_reverse(explode("-",$fromdate));
		$prob = getDBConnectorPhrase($query).sprintf(" c.created>='%s 00:00:00'",mysql_fix_string(implode("-",$datearray),$mysqlcon));
		$query .= $prob;
		$query_count .=$prob;
	}

	if(!empty($todate))
	{
		$datearray = array_reverse(explode("-",$todate));
		$prob = getDBConnectorPhrase($query).sprintf(" c.created<='%s 23:59:59'",mysql_fix_string(implode("-",$datearray),$mysqlcon));
		$query .= $prob;
		$query_count .=$prob;
	}

	if(!empty($firstname))
	{
		$prob = getDBConnectorPhrase($query).sprintf(" c.firstname LIKE '%%%s%%'",mysql_fix_string($firstname,$mysqlcon));
		$query .= $prob;
		$query_count .=$prob;
	}

	$prob = " ORDER BY c.created DESC";
	$query .= $prob;
	$query_count .=$prob;
	
	$count = 0;
	if($datacount = getRecordsCount($query_count,$mysqlcon))
	{
		$count = $datacount;
	}
	
	$query .= sprintf(" LIMIT %u , %u",($page-1)*$pagelimit,$pagelimit);

	hlog_errorlog($query,$jobid);

	if(!($result = $mysqlcon->query($query)))
	{
		hlog_errorlog($jobid.LLOG_SEPARATOR."SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		$mysqlcon->close();
		return formatError(1001);
	}

	if($result->num_rows==0)
	{
		$mysqlcon->close();
		return formatError(1022);
	}

	$val = array ();
	$temp = array();

	while($row = $result->fetch_assoc())
	{
		$row['document'] = 'NO';

		if(($customerdocdetail = getCustomerDocData($row['customerid'],$jobid))&&(!empty($customerdocdetail['error'])))
		{
			$row['document'] = 'YES';
		}

		$row['created'] = date("Y-m-d H:i:s",strtotime($row['created']));
		$temp[] = $row;
	}
	$val['count'] = $count;
	$val['data'] = $temp;

	$result->close();
	$mysqlcon->close();

	return $val;
}
function listLCOKYCDownload($lcoid,$param)
{
	$param['lcoid'] = $lcoid;

	$result = listKYCDownload($param);

	return $result;
}
function listKYCDownload($param)
{
	if(!empty($param['lcoid']))
	{
		$tablename = getCustomerTableName($param['lcoid']);
	}
	if(!empty($tablename['error']))
	{
		return $tablename;
	}
	if(!empty($param['lcoid']))
	{
		$activationtablename = getActivationTableName($param['lcoid']);
	}
	if(!empty($activationtablename['error']))
	{
		return $$activationtablename;
	}

	if(!empty($param['lcoid']))
	{
		$devicetablename = getDeviceTableName($param['lcoid']);
	}
	if(!empty($devicetablename['error']))
	{
		return $devicetablename;
	}

	error_log("customer download===".json_encode($param));

	date_default_timezone_set(INDIAN_TIME_ZONE);

	$filename = 'KYCReject';

	$csvString1 = "Firstname,Surname,Customerid,Lcoid,Lconame,STBnumber,KYCstatus,Rejectreason,Mobileno,Email,Created\n";

	if(!empty($param['fromdate'])&&!empty($param['todate']))
	{
		$filename .= $param['fromdate']."_".$param['todate'];
	}

	header("Content-Disposition: attachment;filename=".$filename.".csv");

	print $csvString1;

	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	$fromdate = NULL;
	$todate = NULL;

	if(!empty($param['fromdate']))
	{
		$fromdate = $param['fromdate'];
		unset($param['fromdate']);
	}

	if(!empty($param['todate']))
	{
		$todate = $param['todate'];
		unset($param['todate']);
	}

	if(!empty($param['firstname']))
	{
		$firstname = $param['firstname'];
		unset($param['firstname']);
	}

	if(!empty($param['customerid']))
	{
		$customerid = $param['customerid'];
		unset($param['customerid']);
	}

	if(!empty($param['mobileno']))
	{
		$mobileno = $param['mobileno'];
		unset($param['mobileno']);
	}

	if(!empty($param['serialno']))
	{
		$serialno = $param['serialno'];
		unset($param['serialno']);
	}

	$filterc = array (
			'customerid'=>'idcustomer',
			'firstname'=>'firstname',
			'surname'=>'surname',
			'mobileno'=>'mobileno',
			'aadhaar'=>'aadhaar',
			'email'=>'email',
			'jobid'=>'jobid',
			'lcoid'=>'lcoid',
			'reason'=>'reason',
			'kycstatus'=>'kycstatus',
			'customertypeid'=>'customertypeid',
			'customerclassid'=>'customerclassid',
			'registeredon'=>'registeredon',
			'initactivationid'=>'initactivation',
			'initdeviceid'=>'initdevice',
			'customertype'=>'customertype',
			'errorcode'=>'errorcode' );
	$filterl = array ('lconame'=>'firstname' );
	$filtera = array ('swproductid'=>'productid','swproductname'=>'productname','price'=>'price','initiatorid'=>'initiatorid','initiatortype'=>'initiatortype' );
	$filterd = array ('hwproductid'=>'productid','hwproductname'=>'productname','serialno'=>'serialno','devicestatus'=>'devicestatus','hwcontractid'=>'contractid' );

	//$query = sprintf("SELECT firstname,surname,customerid,lcoid,lconame,serialno,kycstatus,reason,mobileno,email,created FROM customer_view WHERE kycstatus = 'REJECTED'");

	$query = sprintf("SELECT c.firstname,c.surname,c.idcustomer AS customerid,c.lcoid,l.firstname AS lconame,d.serialno AS serialno,c.kycstatus,c.reason,c.mobileno,c.email,c.created FROM ".$tablename." c left join lco l ON c.lcoid = l.idlco left join ".$activationtablename." a ON c.initactivation = a.idactivation left join ".$devicetablename." d ON c.initdevice = d.iddevice WHERE c.kycstatus = 'REJECTED'");

	foreach($param as $key => $value)
	{
		if(!empty($value))
		{
			if(!empty($filterc[$key]))
			{
				$query .= getDBConnectorPhrase($query).sprintf("c.%s",mysql_fix_string($filterc[$key],$mysqlcon));
				$query .= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
			}
			else if(!empty($filterl[$key]))
			{
				$query .= getDBConnectorPhrase($query).sprintf("l.%s",mysql_fix_string($filterl[$key],$mysqlcon));
				$query .= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
			}
			else if(!empty($filtera[$key]))
			{
				$query .= getDBConnectorPhrase($query).sprintf("a.%s",mysql_fix_string($filtera[$key],$mysqlcon));
				$query .= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
			}
			else if(!empty($filterd[$key]))
			{
				$query .= getDBConnectorPhrase($query).sprintf("d.%s",mysql_fix_string($filterd[$key],$mysqlcon));
				$query .= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
			}
			else
			{
				return formatError(1013);
			}
			// 			$query.= getDBConnectorPhrase($query).sprintf(" %s",mysql_fix_string($key,$mysqlcon));
			// 			$query.= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
		}
	}

	if(!empty($fromdate))
	{
		$datearray = array_reverse(explode("-",$fromdate));
		$query .= getDBConnectorPhrase($query).sprintf(" c.created>='%s 00:00:00'",mysql_fix_string(implode("-",$datearray),$mysqlcon));
	}

	if(!empty($todate))
	{
		$datearray = array_reverse(explode("-",$todate));
		$query .= getDBConnectorPhrase($query).sprintf(" c.created<='%s 23:59:59'",mysql_fix_string(implode("-",$datearray),$mysqlcon));
	}
	if(!empty($firstname))
	{
		$query .= getDBConnectorPhrase($query).sprintf(" c.firstname LIKE '%%%s%%'",mysql_fix_string($firstname,$mysqlcon));
	}

	if(!empty($customerid))
	{
		$query .= getDBConnectorPhrase($query).sprintf(" c.idcustomer LIKE '%%%s%%'",mysql_fix_string($customerid,$mysqlcon));
	}

	if(!empty($serialno))
	{
		$query .= getDBConnectorPhrase($query).sprintf(" d.serialno LIKE '%%%s%%'",mysql_fix_string($serialno,$mysqlcon));
	}

	$query .= " ORDER BY c.created DESC";

	error_log($query);

	if(!($result = $mysqlcon->query($query)))
	{
		error_log("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		$mysqlcon->close();
		return;
	}

	if($result->num_rows==0)
	{
		$mysqlcon->close();
		error_log("No customer to Download");
		return;
	}

	while($row = $result->fetch_assoc())
	{
		$csvString = implode(",",array_values($row));
		$csvString .= "\n";
		print $csvString;
		flush();
		ob_flush();
	}

	$result->close();
	$mysqlcon->close();

	return;
}
function listLCOCustomerDownload($lcoid,$param)
{
	$param['lcoid'] = $lcoid;

	$result = listCustomerDownload($param);

	return $result;
}
function listCustomerDownload($param,$postfix = null)
{
	error_log("customer download===".json_encode($param));

	date_default_timezone_set(INDIAN_TIME_ZONE);

	$filename = 'customer';

	$csvString1 = "Customerid,Firstname,Surname,Mobileno,Email,Customerstatus,KYCstatus,Customertypeid,Customerclassid,EntityType,CustomerType,Created\n";

	if(!empty($param['fromdate'])&&!empty($param['todate']))
	{
		$filename .= $param['fromdate']."_".$param['todate'];
	}

	header("Content-Disposition: attachment;filename=".$filename.".csv");
	ob_end_clean();
	print $csvString1;

	if(!$mysqlcon = getHITSBACKUPDBConnection())
	{
		if(!$mysqlcon = getHITSDBConnection())
		{
			return formatError(10001);
		}
	}
	$page = 1;
	$pagedata = 300;
	$continuelisting = TRUE;
	$fromdate = NULL;
	$todate = NULL;

	if(!empty($param['fromdate']))
	{
		$fromdate = $param['fromdate'];
		unset($param['fromdate']);
	}

	if(!empty($param['todate']))
	{
		$todate = $param['todate'];
		unset($param['todate']);
	}

	if(!empty($param['firstname']))
	{
		$firstname = $param['firstname'];
		unset($param['firstname']);
	}

	if(!empty($param['lcoid']))
	{
		$tablename = getCustomerTableName($param['lcoid']);
	}
	else
	{
		$tablename = getCustomerTableName(NULL,$postfix);
	}

	while($continuelisting)
	{

		$query = sprintf("SELECT idcustomer as customerid,firstname,surname,mobileno,email,customerstatus,kycstatus,customertypeid,customerclassid,entitytype,customertype,created FROM  ".$tablename);

		foreach($param as $key => $value)
		{
			if(!empty($value))
			{
				$query .= getDBConnectorPhrase($query).sprintf(" %s",mysql_fix_string($key,$mysqlcon));
				$query .= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
			}
		}

		if(!empty($fromdate))
		{
			$datearray = array_reverse(explode("-",$fromdate));
			//$query.=getDBConnectorPhrase($query).sprintf(" DATE(created)>='%s'",mysql_fix_string(implode("-",$datearray), $mysqlcon));
			$query .= getDBConnectorPhrase($query).sprintf(" created>='%s 00:00:00'",mysql_fix_string(implode("-",$datearray),$mysqlcon));
		}

		if(!empty($todate))
		{
			$datearray = array_reverse(explode("-",$todate));
			//$query.=getDBConnectorPhrase($query).sprintf(" DATE(created)<='%s'",mysql_fix_string(implode("-",$datearray), $mysqlcon));
			$query .= getDBConnectorPhrase($query).sprintf(" created<='%s 23:59:59'",mysql_fix_string(implode("-",$datearray),$mysqlcon));
		}
		if(!empty($firstname))
		{
			$query .= getDBConnectorPhrase($query).sprintf(" firstname LIKE '%%%s%%'",mysql_fix_string($firstname,$mysqlcon));
		}

		$query .= " ORDER BY created DESC";

		error_log("Page===>".$page);

		$pagestart = ($page-1)*$pagedata;

		$query .= sprintf(" LIMIT %s,%s",$pagestart,$pagedata);

		error_log($query);

		if(!($result = $mysqlcon->query($query,MYSQLI_USE_RESULT)))
		{
			error_log("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
			$mysqlcon->close();
			$continuelisting = FALSE;
			return;
		}

		$thiscount = 0;

		while($row = $result->fetch_assoc())
		{
			$thiscount++;
			$csvString = implode(",",array_values($row));
			$csvString .= "\n";
			print $csvString;
			flush();
			ob_flush();
		}
		if($thiscount<$pagedata)
		{
			$continuelisting = FALSE;
		}

		$page++;
	}
	$result->close();
	$mysqlcon->close();

	return;
}
function insertIntoCustomerTable($lcodetail,$customerdata,$customercreatestatus,$mysqlcon = NULL,$jobid = null)
{
	$close = false;

	if(empty($mysqlcon))
	{
		if(!$mysqlcon = getHITSDBConnection())
		{
			return formatError(10001);
		}
		$close = true;
	}

	$customer = array ();
	$customer['idcustomer'] = $customercreatestatus['customerid'];
	$customer['firstname'] = $customerdata['firstname'];
	$customer['surname'] = $customerdata['surname'];
	$customer['mobileno'] = $customerdata['mobileno'];
	$customer['lcoid'] = $lcodetail['lcoid'];
	$customer['customerstatus'] = "PENDING";
	$customer['kycstatus'] = "PENDING";
	$customer['customerclassid'] = $customerdata['customerclassid'];
	$customer['customertypeid'] = $customerdata['customertypeid'];
	$customer['initiatorname'] = $customerdata['initiator'];
	$customer['entityid'] = $customerdata['entityid'];
	$customer['entitytype'] = $customerdata['entitytype'];

	if(!empty($customerdata['email']))
	{
		$customer['email'] = $customerdata['email'];
	}

	if(!empty($customerdata['jobid']))
	{
		$customer['jobid'] = $customerdata['jobid'];
	}

	if(!empty($customerdata['customertype']))
	{
		$customer['customertype'] = $customerdata['customertype'];
	}

	if(!empty($customerdata['dpoentity']))
	{
		$customer['dpoentity'] = $customerdata['dpoentity'];
	}
	$keys = array ();
	$values = array ();

	foreach($customer as $key => $value)
	{
		$key = strip_tags($key);
		$keys[] = mysql_fix_string($key,$mysqlcon);
		$value = strip_tags($value);
		$values[] = mysql_fix_string($value,$mysqlcon);
	}

	if(($tablename = getCustomerTableName($lcodetail['lcoid']))&&(!empty($tablename['error'])))
	{
		return $tablename;
	}
	$query = "INSERT INTO  ".$tablename." (".implode(",",$keys).",created) VALUES ('".implode("','",$values)."',NOW())";

	hlog_errorlog($query,$jobid);

	unset($keys);
	unset($values);

	if(!($stmt = $mysqlcon->prepare($query)))
	{
		hlog_errorlog("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error,$jobid);
		return formatError(1001);
	}

	if(!$stmt->execute())
	{
		hlog_errorlog("SQL Query Failed: (".$stmt->errno.") ".$stmt->error,$jobid);
		hlog_errorlog("SQL Error Number===".$stmt->errno,$jobid);
		$errorno = 1033;

		if($stmt->errno==1062)
		{
			$errorno = 1017;
		}

		return formatError($errorno);
	}

	if($stmt->affected_rows==0)
	{
		hlog_errorlog("SQL Query Failed: (".$stmt->errno.") ".$stmt->error,$jobid);
		return formatError(1033);
	}

	$stmt->close();

	if($close)
	{
		$mysqlcon->close();
	}

	hlog($lcodetail['lcoid'].LLOG_SEPARATOR.$customercreatestatus['customerid'].LLOG_SEPARATOR.$customerdata['firstname'].LLOG_SEPARATOR."INSERT".LLOG_SEPARATOR."SUCCESS",VHITS_CUSTOMER_LOG,VHITS_LOGLEVEL_CRITICAL);

	return array ("success"=>$customercreatestatus['customerid'] );
}
function insertIntoCustomerDocTable($lcodetail,$data,$customercreatestatus,$mysqlcon = NULL)
{
	$close = false;

	if(empty($mysqlcon))
	{
		if(!$mysqlcon = getHITSDBConnection())
		{
			return formatError(10001);
		}
		$close = true;
	}

	$query = sprintf("INSERT INTO customerdoc (customerid,type,filename,created) VALUES ");

	$query .= "('".$customercreatestatus['customerid']."','IDPROOF','".$data['idproof']."',NOW())";
	$query .= ",('".$customercreatestatus['customerid']."','ADDRESSPROOF','".$data['addressproof']."',NOW())";
	$query .= ",('".$customercreatestatus['customerid']."','CRF','".$data['crf']."',NOW())";

	hlog_errorlog($query);

	if(!($stmt = $mysqlcon->prepare($query)))
	{
		hlog_errorlog("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		return formatError(1001);
	}

	if(!$stmt->execute())
	{
		hlog_errorlog("SQL Query Failed: (".$stmt->errno.") ".$stmt->error);
		hlog_errorlog("SQL Error Number===".$stmt->errno);
		$errorno = 1035;

		if($stmt->errno==1062)
		{
			$errorno = 1017;
		}

		return formatError($errorno);
	}

	if($stmt->affected_rows==0)
	{
		hlog_errorlog("SQL Query Failed: (".$stmt->errno.") ".$stmt->error);
		return formatError(1035);
	}

	$stmt->close();

	$subscriberdocid = $mysqlcon->insert_id;

	hlog_errorlog("subscriberdocid===>".$subscriberdocid);

	if($close)
	{
		$mysqlcon->close();
	}

	hlog($lcodetail['lcoid'].LLOG_SEPARATOR.$customercreatestatus['customerid'].LLOG_SEPARATOR."IDPROOF".LLOG_SEPARATOR.$data['idproof'].LLOG_SEPARATOR."INSERT".LLOG_SEPARATOR."SUCCESS",VHITS_CUSTOMER_DOC_LOG,VHITS_LOGLEVEL_CRITICAL);
	hlog($lcodetail['lcoid'].LLOG_SEPARATOR.$customercreatestatus['customerid'].LLOG_SEPARATOR."ADDRESSPROOF".LLOG_SEPARATOR.$data['addressproof'].LLOG_SEPARATOR."INSERT".LLOG_SEPARATOR."SUCCESS",VHITS_CUSTOMER_DOC_LOG,VHITS_LOGLEVEL_CRITICAL);
	hlog($lcodetail['lcoid'].LLOG_SEPARATOR.$customercreatestatus['customerid'].LLOG_SEPARATOR."CRF".LLOG_SEPARATOR.$data['crf'].LLOG_SEPARATOR."INSERT".LLOG_SEPARATOR."SUCCESS",VHITS_CUSTOMER_DOC_LOG,VHITS_LOGLEVEL_CRITICAL);

	return array ("success"=>$customercreatestatus['customerid'] );
}
function getCustomerDocData($customerid,$jobid = null)
{
	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	$query = sprintf("SELECT * FROM customerdoc ");
	$query .= sprintf(" WHERE customerid = '%s'",mysql_fix_string($customerid,$mysqlcon));

	hlog_errorlog($query,$jobid);

	if(!($result = $mysqlcon->query($query)))
	{
		hlog_errorlog($jobid.LLOG_SEPARATOR."SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		$mysqlcon->close();
		return formatError(1001);
	}

	if($result->num_rows==0)
	{
		$mysqlcon->close();
		return formatError(1022);
	}

	$val = array ();

	while($row = $result->fetch_assoc())
	{
		$val[] = $row;
	}

	$result->close();
	$mysqlcon->close();

	return $val;
}
function updateCustomerTable($customerid,$updatecustomerdata,$mysqlcon = NULL,$jobid = null,$lcoid)
{
	if(!empty($updatecustomerdata['Extra4']))
	{
		unset($updatecustomerdata['Extra4']);
	}

	if(!empty($updatecustomerdata['titleid']))
	{
		unset($updatecustomerdata['titleid']);
	}

	if(!empty($updatecustomerdata['address1']))
	{
		unset($updatecustomerdata['address1']);
	}

	if(!empty($updatecustomerdata['address2']))
	{
		unset($updatecustomerdata['address2']);
	}

	if(isset($updatecustomerdata['exemptioncode']))
	{
		unset($updatecustomerdata['exemptioncode']);
	}

	if(empty($updatecustomerdata))
	{
		return array ("success"=>$customerid );
	}

	$close = false;

	if(empty($mysqlcon))
	{
		if(!$mysqlcon = getHITSDBConnection())
		{
			return formatError(10001);
		}
		$close = true;
	}

	$keys = array_keys($updatecustomerdata);
	$last = $keys[count($keys)-1];

	if(($tablename = getCustomerTableName($lcoid))&&(!empty($tablename['error'])))
	{
		return $tablename;
	}
	$query = "UPDATE ".$tablename." SET ";

	foreach($updatecustomerdata as $key => $value)
	{
		if($key==="registeredon")
		{
			$query .= $key."=".$value;
		}
		else
		{
			$query .= $key."='".mysql_fix_string($value,$mysqlcon)."'";
		}

		if($key!==$last)
		{
			$query .= " , ";
		}
	}

	$query .= sprintf(" WHERE idcustomer ='%s'",mysql_fix_string($customerid,$mysqlcon));

	hlog_errorlog($query,$jobid);

	if(!($stmt = $mysqlcon->prepare($query)))
	{
		hlog_errorlog("Update customer failed: (".$mysqlcon->errno.") ".$mysqlcon->error,$jobid);
		return formatError(1001);
	}

	if(!($stmt->execute()))
	{
		hlog_errorlog("Update customer failed: (".$stmt->errno.") ".$stmt->error,$jobid);
		hlog_errorlog("SQL Error Number===".$stmt->errno,$jobid);
		$errorno = 1034;

		if($stmt->errno==1062)
		{
			$errorno = 1017;
		}

		return formatError($errorno);
	}

	if($stmt->affected_rows==0)
	{
		hlog_errorlog("No change in customer",$jobid);
		//return formatError(1071);
		return array ("success"=>$customerid );
	}

	$stmt->close();

	if($close)
	{
		$mysqlcon->close();
	}

	return array ("success"=>$customerid );
}
function updateCustomerDocTable($customerid,$type,$filename,$mysqlcon = NULL)
{
	$close = false;

	if(empty($mysqlcon))
	{
		if(!$mysqlcon = getHITSDBConnection())
		{
			return formatError(10001);
		}
		$close = true;
	}
	error_log('query==>'.$customerid);
	error_log('query==>'.json_encode($type));
	error_log('query==>'.$filename);
	$query = sprintf("REPLACE INTO customerdoc (customerid,type,filename,created) VALUES ('");
	$query .= $customerid."','".$type."','".$filename."',NOW())";

	//$query="UPDATE customerdoc SET ";
	//$query.= " filename = '".$filename."'";
	//$query.=sprintf(" WHERE customerid ='%s'",mysql_fix_string($customerid, $mysqlcon));
	//$query.=sprintf(" AND type ='%s'",mysql_fix_string($type, $mysqlcon));

	error_log($query);

	if(!($stmt = $mysqlcon->prepare($query)))
	{
		error_log("Update customerdoc failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		return formatError(1001);
	}

	if(!($stmt->execute()))
	{
		error_log("Update customerdoc failed: (".$stmt->errno.") ".$stmt->error);
		error_log("SQL Error Number===".$stmt->errno);
		$errorno = 1036;

		if($stmt->errno==1062)
		{
			$errorno = 1017;
		}

		return formatError($errorno);
	}

	if($stmt->affected_rows==0)
	{
		error_log("No change in customerdoc");
		return formatError(1071);
		//return array("success"=>$customerid);
	}

	$stmt->close();

	if($close)
	{
		$mysqlcon->close();
	}

	return array ("success"=>$customerid );
}
function listLCOCustomerType($lcoid,$param,$page = 1)
{
	//$param['lcoid'] = $lcoid;
	$param['customerclass'] = "Customer";

	$result = listCustomerTypeDetails($param,$page);

	return $result;
}
function listCustomerTypeDetails($param,$page = 1)
{
	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	$query = sprintf("SELECT * FROM customerclass ");

	if(!empty($param['page']))
	{
		$page = $param['page'];
		unset($param['page']);
	}

	foreach($param as $key => $value)
	{
		if(!empty($value))
		{
			$query .= getDBConnectorPhrase($query).sprintf(" %s",mysql_fix_string($key,$mysqlcon));
			$query .= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
		}
	}

	$query .= " ORDER BY customerclass ASC";

	$query .= sprintf(" LIMIT %u , %u",($page-1)*VHITS_DB_PAGE_LIMIT,VHITS_DB_PAGE_LIMIT);

	error_log($query);

	if(!($result = $mysqlcon->query($query)))
	{
		error_log("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		$mysqlcon->close();
		return formatError(1001);
	}

	if($result->num_rows==0)
	{
		$mysqlcon->close();
		return formatError(1022);
	}

	$val = array ();

	while($row = $result->fetch_assoc())
	{
		if($row['customertype'] == 'Child')
		{
			continue;
		}
		$val[] = $row;
	}

	$result->close();
	$mysqlcon->close();

	return $val;
}
function getCustomerTypeDetails($customertype,$jobid = null)
{
	if($customertype=="Indirect")
	{
		hlog_errorlog("customertype Indirect to Normal",$jobid);
		$customertype = "Normal";
	}

	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	$query = sprintf("SELECT * FROM customerclass ");

	$query .= sprintf(" WHERE customertype = '%s'",mysql_fix_string($customertype,$mysqlcon));

	hlog_errorlog($query,$jobid);

	if(!($result = $mysqlcon->query($query)))
	{
		hlog_errorlog("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error,$jobid);
		$mysqlcon->close();
		return formatError(1001);
	}

	if($result->num_rows==0)
	{
		$mysqlcon->close();
		return formatError(1022);
	}

	$val = $result->fetch_assoc();

	$result->close();
	$mysqlcon->close();

	return $val;
}
function updateLCOCustomer($lcoid,$customerid,$updatecustomerdata,$username)
{
	//mobileno validation
	if(!empty($updatecustomerdata['mobileno']))
	{
		if((!is_numeric($updatecustomerdata['mobileno']))||(strlen($updatecustomerdata['mobileno'])!=10))
		{
			return formatError(1051);
		}
	}

	//email validation
	if(!empty($updatecustomerdata['email']))
	{
		if(!filter_var($updatecustomerdata['email'],FILTER_VALIDATE_EMAIL))
		{
			return formatError(1050);
		}
	}

	//get LCO details
	if(($lcodetail = getRegisteredLcoDetailsByFieldName('lcoid',$lcoid))&&(!empty($lcodetail['error'])))
	{
		return $lcodetail;
	}

	$updatecustomerdata['exemptioncode'] = 1;
	//check if the LCO has entered his GST number and it is valid. If valid, ExemptionCodeId = 0, else ExemptionCodeId = 0
	if((!empty($lcodetail['gstno']))&&(strlen($lcodetail['gstno'])==15))
	{
		//GST number is valid
		$updatecustomerdata['exemptioncode'] = 0;
	}

	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	$mysqlcon->query("START TRANSACTION");

	$documentlist = NULL;

	if(!empty($updatecustomerdata['documentlist']))
	{
		$documentlist = $updatecustomerdata['documentlist'];
		unset($updatecustomerdata['documentlist']);
	}

	if(!empty($updatecustomerdata))
	{
		//update customer table
		if(($updatecustomer = updateCustomerTable($customerid,$updatecustomerdata,$mysqlcon,NULL,$lcodetail['lcoid']))&&isset($updatecustomer['error']))
		{
			$mysqlcon->query("ROLLBACK");
			$mysqlcon->close();
			return $updatecustomer;
		}

		//update customer detail to hansen sms
		if(($updatecustomertohansenstatus = updateCustomerData($username,$customerid,$updatecustomerdata))&&isset($updatecustomertohansenstatus['error']))
		{
			$mysqlcon->query("ROLLBACK");
			$mysqlcon->close();
			return $updatecustomertohansenstatus;
		}
	}

	//update customerdoc table
	if(!empty($documentlist))
	{
		/*
		 * if(($updatecustomerdoc = updateCustomerDocTable($customerid,$documentlist,$mysqlcon)) && isset($updatecustomerdoc['error']))
		 * {
		 * $mysqlcon->query("ROLLBACK");
		 * $mysqlcon->close();
		 * return $updatecustomerdoc;
		 * }
		 */

		//update kyc document.
		error_log("documentlist========>".json_encode($documentlist));
		foreach($documentlist as $value)
		{
			error_log(json_encode($value));
			if((!empty($value['type']))&&(!empty($value['document'])))
			{
				$type = $value['type'];
				$type = strip_tags($type);
				$type = trim($type);

				$filename = $value['document'];
				$filename = strip_tags($filename);
				$filename = trim($filename);

				if(($updatecustomerdoc = updateCustomerDocTable($customerid,$type,$filename,$mysqlcon))&&isset($updatecustomerdoc['error']))
				{
					$mysqlcon->query("ROLLBACK");
					$mysqlcon->close();
					return $updatecustomerdoc;
				}

				if(($updatekycdocumentstatus = updateCustomerKycDocument($customerid,$type,$filename))&&isset($updatekycdocumentstatus['error']))
				{
					$mysqlcon->query("ROLLBACK");
					$mysqlcon->close();
					return $updatekycdocumentstatus;
				}
			}
		}
	}

	$mysqlcon->query("COMMIT");
	$mysqlcon->close();

	if(!empty($documentlist))
	{
		foreach($documentlist as $value)
		{
			if(!empty($value['document']))
			{
				unlink(VHITS_LOCAL_TEMP_FOLDER.$value['document']);
			}
		}
	}

	return array ("success"=>$customerid );
}
function getStateDetail($provinceid = NULL,$page = 1,$jobid = null)
{
	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	$query = sprintf("SELECT idprovince AS provinceid,provincename FROM province ");

	if(!empty($provinceid))
	{
		$query .= sprintf(" WHERE idprovince= '%s'",mysql_fix_string($provinceid,$mysqlcon));
	}

	$query .= " ORDER BY idprovince ASC";

	$query .= sprintf(" LIMIT %u , %u",($page-1)*VHITS_DB_PAGE_LIMIT,VHITS_DB_PAGE_LIMIT);

	hlog_errorlog($query,$jobid);

	if(!($result = $mysqlcon->query($query)))
	{
		hlog_errorlog("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error,$jobid);
		$mysqlcon->close();
		return formatError(1001);
	}

	if($result->num_rows==0)
	{
		$mysqlcon->close();
		return formatError(1022);
	}

	if($result->num_rows==1)
	{
		$val = $result->fetch_assoc();
	}
	else
	{
		$val = array ();

		while($row = $result->fetch_assoc())
		{
			$val[] = $row;
		}
	}

	$result->close();
	$mysqlcon->close();

	return $val;
}
function listCustomerTitle($titleid = NULL,$page = 1)
{
	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	$query = sprintf("SELECT idtitle AS titleid,titlename FROM title ");

	if(!empty($titleid))
	{
		$query .= sprintf(" WHERE idtitle= '%s'",mysql_fix_string($titleid,$mysqlcon));
	}

	$query .= " ORDER BY idtitle ASC";

	$query .= sprintf(" LIMIT %u , %u",($page-1)*VHITS_DB_PAGE_LIMIT,VHITS_DB_PAGE_LIMIT);

	error_log($query);

	if(!($result = $mysqlcon->query($query)))
	{
		error_log("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		$mysqlcon->close();
		return formatError(1001);
	}

	if($result->num_rows==0)
	{
		$mysqlcon->close();
		return formatError(1022);
	}

	$val = array ();

	while($row = $result->fetch_assoc())
	{
		$val[] = $row;
	}

	$result->close();
	$mysqlcon->close();

	return $val;
}
function getCustomerDetail($username,$id,$initiatortype = NULL,$jobid = null,$initiatorid = null,$preactivation = null,$customertype = null,$suspension = FALSE)
{
	$len = strlen($id);
	if($len>10)
	{
		if(($customerdata = getCustomerByDeviceSerialNumberBySuperUser($id,$jobid,$initiatortype))&&(!empty($customerdata['error'])))
		{
			return formatError(1055);
			//return $customerdata;
		}
		$id = $customerdata['customerid'];
	}

	if(($customerdata = getAccountByOperator($username,$id,$initiatortype,$jobid,$preactivation,$customertype))&&(!empty($customerdata['error'])))
	{
		hlog_errorlog("Trying to get customer by device serial number===>".$id,$jobid);

		if(($customerdata = getCustomerByDeviceSerialNumberBySuperUser($id,$jobid,$initiatortype))&&(!empty($customerdata['error'])))
		{
			return $customerdata;
		}
		else
		{
			if(($customerdata['customerstatus']!="ACTIVE")&&(!$suspension))
			{
				return formatError(1055);
			}
			else
			{
				if(($customerdata = getAccountByOperator($username,$customerdata['customerid'],$initiatortype,$jobid,$preactivation))&&(!empty($customerdata['error'])))
				{
					return formatError(1055);
				}
				else
				{
					if(($initiatorid)&&(($initiatortype!=='ADMIN')&&($initiatortype!=='DPO')))
					{
						if((int) $customerdata['lcoid']!==(int) ($initiatorid))
						{
							return formatError(2062);
						}
					}
					return $customerdata;
				}
			}
		}
	}
	else
	{
		if(($initiatorid)&&(($initiatortype!=='ADMIN')&&($initiatortype!=='CUSTOMER')&&($initiatortype!=='DPO')))
		{
			if((int) $customerdata['lcoid']!==(int) ($initiatorid))
			{
				return formatError(2062);
			}
		}
		return $customerdata;
	}
}
function getCustomerDetailBySuperUser($id)
{
	error_log("Trying to get customer by customer Id===>".$id);
	if(($customerdata = getCustomerByCustomeridBySuperUser($id))&&(!empty($customerdata['error'])))
	{
		error_log("Trying to get customer by device serial number===>".$id);
		if(($customerdata = getCustomerByDeviceSerialNumberBySuperUser($id))&&(!empty($customerdata['error'])))
		{
			return $customerdata;
		}
	}

	if((!isset($customerdata['customerstatus']))||(!isset($customerdata['kycstatus'])))
	{
		return formatError(1055);
	}

	if($customerdata['customerstatus']!="ACTIVE")
	{
		return formatError(1055);
	}

	if($customerdata['kycstatus']!="VERIFIED")
	{
		return formatError(1086);
	}

	/*
	 * error_log("Trying to get customer agreement detail===>".$customerdata['customerid']);
	 * if(($customeragreementdetail = getCustomerAgreementBySuperUser($customerdata['customerid'])) && (!empty($customeragreementdetail['error'])))
	 * {
	 * return $customeragreementdetail;
	 * }
	 * else
	 * {
	 * $customerdata['agreementid'] = $customeragreementdetail['agreementid'];
	 * }
	 */

	return $customerdata;
}
function getCustomerCount($param,$postfix = NULL)
{
	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	date_default_timezone_set(INDIAN_TIME_ZONE);

	$fromdate = NULL;
	$todate = NULL;

	if(!empty($param['fromdate']))
	{
		$fromdate = $param['fromdate'];
		unset($param['fromdate']);
	}

	if(!empty($param['todate']))
	{
		$todate = $param['todate'];
		unset($param['todate']);
	}

	if(!empty($param['firstname']))
	{
		$firstname = $param['firstname'];
		unset($param['firstname']);
	}

	if(!empty($param['lcoid']))
	{
		$tablename = getCustomerTableName($param['lcoid']);
	}
	else
	{
		$tablename = getCustomerTableName(NULL,$postfix);
	}
	if(!empty($tablename['error']))
	{
		return $tablename;
	}
	if(!empty($param['lcoid']))
	{
		$activationtablename = getActivationTableName($param['lcoid']);
	}
	else
	{
		$activationtablename = getActivationTableName(NULL,$postfix);
	}
	if(!empty($activationtablename['error']))
	{
		return $activationtablename;
	}

	if(!empty($param['lcoid']))
	{
		$devicetablename = getDeviceTableName($param['lcoid']);
	}
	else
	{
		$devicetablename = getDeviceTableName(NULL,$postfix);
	}
	if(!empty($devicetablename['error']))
	{
		return $devicetablename;
	}

	$filterc = array (
			'customerid'=>'idcustomer',
			'firstname'=>'firstname',
			'surname'=>'surname',
			'mobileno'=>'mobileno',
			'aadhaar'=>'aadhaar',
			'email'=>'email',
			'jobid'=>'jobid',
			'lcoid'=>'lcoid',
			'reason'=>'reason',
			'kycstatus'=>'kycstatus',
			'customertypeid'=>'customertypeid',
			'customerclassid'=>'customerclassid',
			'registeredon'=>'registeredon',
			'initactivationid'=>'initactivation',
			'initdeviceid'=>'initdevice',
			'customertype'=>'customertype',
			'errorcode'=>'errorcode',
			'customerstatus'=>'customerstatus' );
	$filterl = array ('lconame'=>'firstname' );
	$filtera = array ('swproductid'=>'productid','swproductname'=>'productname','price'=>'price','initiatorid'=>'initiatorid','initiatortype'=>'initiatortype' );
	$filterd = array ('hwproductid'=>'productid','hwproductname'=>'productname','serialno'=>'serialno','devicestatus'=>'devicestatus','hwcontractid'=>'contractid' );

	//$query = sprintf("SELECT * FROM customer_view ");
	$query = sprintf("SELECT COUNT(*) as count FROM (((".$tablename." c left join lco l ON ((c.lcoid = l.idlco))) left join ".$activationtablename." a ON ((c.initactivation = a.idactivation))) left join ".$devicetablename." d ON ((c.initdevice = d.iddevice)))");

	foreach($param as $key => $value)
	{
		if(!empty($value))
		{
			if(!empty($filterc[$key]))
			{
				$query .= getDBConnectorPhrase($query).sprintf("c.%s",mysql_fix_string($filterc[$key],$mysqlcon));
				$query .= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
			}
			else if(!empty($filterl[$key]))
			{
				$query .= getDBConnectorPhrase($query).sprintf("l.%s",mysql_fix_string($filterl[$key],$mysqlcon));
				$query .= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
			}
			else if(!empty($filtera[$key]))
			{
				$query .= getDBConnectorPhrase($query).sprintf("a.%s",mysql_fix_string($filtera[$key],$mysqlcon));
				$query .= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
			}
			else if(!empty($filterd[$key]))
			{
				$query .= getDBConnectorPhrase($query).sprintf("d.%s",mysql_fix_string($filterd[$key],$mysqlcon));
				$query .= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
			}
			else
			{
				return formatError(1013);
			}

			// 			$query.= getDBConnectorPhrase($query).sprintf(" %s",mysql_fix_string($key,$mysqlcon));
			// 			$query.= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
		}
	}

	// 	$query = sprintf("SELECT COUNT(*) as count FROM customer_view ");

	// 	foreach($param as $key=>$value)
	// 	{
	// 		if(!empty($value))
	// 		{
	// 			$query.= getDBConnectorPhrase($query).sprintf(" %s",mysql_fix_string($key,$mysqlcon));
	// 			$query.= sprintf(" = '%s'",mysql_fix_string($value,$mysqlcon));
	// 		}
	// 	}

	if(!empty($fromdate))
	{
		$datearray = array_reverse(explode("-",$fromdate));
		$query .= getDBConnectorPhrase($query).sprintf(" c.created>='%s 00:00:00'",mysql_fix_string(implode("-",$datearray),$mysqlcon));
	}

	if(!empty($todate))
	{
		$datearray = array_reverse(explode("-",$todate));
		$query .= getDBConnectorPhrase($query).sprintf(" c.created<='%s 23:59:59'",mysql_fix_string(implode("-",$datearray),$mysqlcon));
	}

	if(!empty($firstname))
	{
		$query .= getDBConnectorPhrase($query).sprintf(" c.firstname LIKE '%%%s%%'",mysql_fix_string($firstname,$mysqlcon));
	}

	error_log($query);

	if(!($result = $mysqlcon->query($query)))
	{
		error_log("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		$mysqlcon->close();
		return formatError(1001);
	}

	if($result->num_rows==0)
	{
		$mysqlcon->close();
		return formatError(1022);
	}

	$row = $result->fetch_assoc();

	$result->close();
	$mysqlcon->close();

	return $row;
}
function documentUploadInitiate($lcoid,$data,$username)
{
	//validate lcoid
	if(($lcodetail = getRegisteredLcoDetailsByFieldName('lcoid',$lcoid))&&(!empty($lcodetail['error'])))
	{
		return $lcodetail;
	}

	//Validate customer
	if(($customerdetail = getCustomerDetail($username,$data['customerid']))&&(!empty($customerdetail['error'])))
	{
		return $customerdetail;
	}

	// $jobdata = array();
	// $jobdata['jobtype'] = VHITS_PROCESS_ACTIVATE_CUSTOMER;
	// $jobdata['initiatorid'] = $lcoid;
	// $jobdata['initiatortype'] ="LCO";
	// $jobdata['metadata'] = $data['customerid'];
	// $jobdata['queuename'] = VHITS_ACTIVATE_CUSTOMER_QUEUE_NAME;
	// $jobdata['numtotal'] = 1;

	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	$mysqlcon->query("START TRANSACTION");

	// if(($bulkreportentry = insertIntoBulkreortTable($jobdata,$mysqlcon)) && (!empty($bulkreportentry['error'])))
	// {
	// 	$mysqlcon->query("ROLLBACK");
	// 	$mysqlcon->close();
	// 	return $bulkreportentry;
	// }
	// else
	// {
	//$jobdata['jobid'] = $bulkreportentry['success'];
	//insert into customerdoc table
	if(($insertdocstatus = insertIntoCustomerDocTable($lcodetail,$data,$customerdetail,$mysqlcon))&&(!empty($insertdocstatus['error'])))
	{
		$mysqlcon->query("ROLLBACK");
		$mysqlcon->close();
		return $insertdocstatus;
	}
	else
	{
		if(($uploaddocstatus = uploadCustomerKycDocumentBySuperUser($data['customerid'],$data))&&(!empty($uploaddocstatus['error'])))
		{
			$mysqlcon->query("ROLLBACK");
			$mysqlcon->close();
			return $uploaddocstatus;
		}
		else
		{
			$mysqlcon->query("COMMIT");
			$mysqlcon->close();
			//Put into Queue
			//$result = setDataToQueue($jobdata,$jobdata['queuename']);
			//hlog($lcoid.LLOG_SEPARATOR.$data['customerid'].LLOG_SEPARATOR.$jobdata['jobid'].LLOG_SEPARATOR."INITIATED",VHITS_BULK_REPORT_LOG);
		}
	}
	//}

	return array ("success"=>$lcoid );
}
function activatecustomerrabbitmqProcessing($initiatorid,$initiatortype,$customerid,$username,$lcoid = NULL,$jobid = null,$customerclassid = null)
{
	//Get Customer detail
	if(($customerdata = getCustomerDetails($customerid,$jobid,$lcoid))&&isset($customerdata['error']))
	{
		return $customerdata;
	}

	if($customerdata['swproductid']==40)
	{
		return formatError(1074);
	}

	//Get Lco detail
	if(($lcodata = getRegisteredLcoDetailsByFieldName("lcoid",$customerdata['lcoid'],$jobid))&&(!empty($lcodata['error'])))
	{
		return $lcodata;
	}

	//kycstatus update in customer table
	if($customerdata['kycstatus']=="PENDING")
	{
		if(($kycupdatestatus = updateCustomerKycStatusBySuperUser($customerid,$jobid))&&(!empty($kycupdatestatus['error'])))
		{
			return $kycupdatestatus;
		}
		else
		{
			$updatecustomerdata = array ();
			$updatecustomerdata['customerstatus'] = "ACTIVE";
			$updatecustomerdata['registeredon'] = "NOW()";
			$updatecustomerdata['kycstatus'] = "VERIFIED";
			if(($updatecustomerstatus = updateCustomerTable($customerid,$updatecustomerdata,null,$jobid,$lcodata['lcoid']))&&(!empty($updatecustomerstatus['error'])))
			{
				return $updatecustomerstatus;
			}
		}
	}

	//renew and update activation, device and customer table
	if($customerdata['activationstatus']=="PENDING")
	{
		$initiatordetails = array ();
		$initiatordetails['initiatorid'] = $initiatorid;
		$initiatordetails['initiatortype'] = $initiatortype;
		$initiatordetails['username'] = $username;
		if(($initiatortype=='DPO'))
		{
			if((($renewstatus = completeActivationProcess($initiatorid,$customerid,$customerdata['swproductid'],$customerdata['initactivationid'],$initiatordetails,$jobid,$customerclassid))&&(!empty($renewstatus['error']))))
			{
				return $renewstatus;
			}
		}
		else
		{
			if(($renewstatus = completePendingAddProductSubscription($initiatorid,$customerdata['initactivationid'],$customerid,$customerdata['swcontractid'],$initiatortype,FALSE,$username,$jobid,$customerdata['lcoid']))&&(!empty($renewstatus['error'])))
			{
				return $renewstatus;
			}
		}

		//Update device as ACTIVE
		$updatedevicedata = array ();
		$updatedevicedata['devicestatus'] = "ACTIVE";

		if(($updatedevicestatus = updateDeviceTable($customerdata['initdeviceid'],$updatedevicedata,null,$jobid,$lcoid))&&isset($updatedevicestatus['error']))
		{
			return $updatedevicestatus;
		}
	}

	//Get Updated Customer detail
	if(($updatedcustomerdata = getCustomerDetails($customerid,$jobid,$lcoid))&&(empty($updatedcustomerdata['error'])))
	{
		if(($updatedcustomerdata['devicestatus']=="ACTIVE")&&($updatedcustomerdata['activationstatus']=="ACTIVE"))
		{
			//STB activation message sending to lco
			$search = array ("{stb}" );
			$replace = array ($updatedcustomerdata['serialno'] );
			$message = str_replace($search,$replace,VHITS_SMS_CUSTOMER_STB_ACTIVATION_SUCCESS_MESSAGE_TO_LCO);
			sendSMS($lcodata['mobileno'],$message,$jobid);

			//product activation message sending to customer
			$search = array ("{productname}" );
			$replace = array ($updatedcustomerdata['swproductname'] );
			$message = str_replace($search,$replace,VHITS_SMS_CUSTOMER_ADD_PRODUCT_SUCCESS_MESSAGE);
			sendSMS($updatedcustomerdata['mobileno'],$message,$jobid);
		}
	}

	return array ("success"=>$customerid );
}
function getCustomerPayments($data,$lcoid = NULL,$customerpaymentlist = NULL)
{
	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	$tablename = getActivationTableName($lcoid);

	if(!empty($tablename['error']))
	{
		return $tablename;
	}

	//$query = sprintf("SELECT * FROM ".$tablename);

	if(!empty($customerpaymentlist))
	{
		$query = sprintf("SELECT idactivation,productid,tariffperiod,productname,producttypename,contractid,price,MAX(created) as created FROM ".$tablename);
	}
	else
	{
		$query = sprintf("SELECT * FROM ".$tablename);
	}

	if(!empty($data['customerid']))
	{
		$query .= getDBConnectorPhrase($query).sprintf(" customerid= '%s'",mysql_fix_string($data['customerid'],$mysqlcon));
	}
	if(!empty($data['fromdate']))
	{
		$fromdate = $data['fromdate'];
		$datearray = array_reverse(explode("-",$fromdate));
		//$query.=getDBConnectorPhrase($query).sprintf(" DATE(created)>='%s'",mysql_fix_string(implode("-",$datearray), $mysqlcon));
		$query .= getDBConnectorPhrase($query).sprintf(" created>='%s 00:00:00'",mysql_fix_string(implode("-",$datearray),$mysqlcon));
	}

	if(!empty($data['todate']))
	{
		$todate = $data['todate'];
		$datearray = array_reverse(explode("-",$todate));
		//$query.=getDBConnectorPhrase($query).sprintf(" DATE(created)<='%s'",mysql_fix_string(implode("-",$datearray), $mysqlcon));
		$query .= getDBConnectorPhrase($query).sprintf(" created<='%s 23:59:59'",mysql_fix_string(implode("-",$datearray),$mysqlcon));
	}

	$query .= getDBConnectorPhrase($query).sprintf(" activationstatus IN ('ACTIVE','PENDING')");

	if(!empty($customerpaymentlist))
	{
		$query .= "GROUP BY productid";
	}
	else
	{
		$page = 1;
		$query .= "ORDER BY created DESC";
	}

	if(!empty($data['page']))
	{
		$page = $data['page'];
	}
	if(!empty($page))
	{
		$query .= sprintf(" LIMIT %u , %u",($page-1)*VHITS_DB_PAGE_LIMIT,VHITS_DB_PAGE_LIMIT);
	}

	/*
	 * $query.= " ORDER BY created DESC";
	 * $page = 1;
	 * if(!empty($data['page']))
	 * {
	 * $page = $data['page'];
	 * }
	 * $query.=sprintf(" LIMIT %u , %u",($page-1)*VHITS_DB_PAGE_LIMIT,VHITS_DB_PAGE_LIMIT);
	 */

	error_log($query);

	if(!($result = $mysqlcon->query($query)))
	{
		error_log("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		$mysqlcon->close();
		return formatError(1001);
	}

	if($result->num_rows==0)
	{
		$mysqlcon->close();
		return formatError(1022);
	}

	$val = array ();

	while($row = $result->fetch_assoc())
	{
		$val[] = $row;
	}

	$result->close();
	$mysqlcon->close();

	return $val;
}
function getCustomerDetailById($username,$id,$lcoid=null)
{
	if(($result = getCustomerDetail($username,$id))&&(isset($result['error'])))
	{
		return $result;
	}

	if(!empty($lcoid))
	{
	    if($result['lcoid'] != $lcoid)
	    {
	    	return formatError(2062);
	    }	
	}	

	/*
	 * if(($data = getCustomerDetailsByFieldName('idcustomer',$result['customerid'],$result['lcoid'])) && (isset($data['error'])))
	 *
	 * {
	 * return $data;
	 * }
	 * $result['crf'] = NULL;
	 * $filename = $result['customerid'].".pdf";
	 * //check if crf available, get the link and add it here.
	 * $sourcefile = VHITS_CRF_FOLDER_PATH.$filename;
	 * if(file_exists($sourcefile))
	 * {
	 * hlog_errorlog("File exist!!!!!");
	 * //move the file to html temp folder
	 * $destination = VHITS_TEMP_FOLDER.$filename;
	 * if(copy($sourcefile, $destination))
	 * {
	 * hlog_errorlog("Copied!!!!!");
	 * $result['crf'] = VHITS_BASE_URL_LCO."temp/".$filename;
	 * }
	 * }
	 * $result['aadhar']= $data['aadhaar'];
	 */
	return $result;
}
function createCRF($data,$username)
{
	//data validation
	if(($crfValidate = CRFDataValidation($data))&&(isset($crfValidate['error'])))
	{
		return $crfValidate;
	}

	//get customer details

	if(($customerDetails = getCustomerDetail($username,$data['customerid']))&&(!empty($customerDetails['error'])))
	{
		return $customerDetails;
	}

	error_log("Customer details ==> ".json_encode($customerDetails));

	//get LCO details
	if(($lcoDetails = getLcoDetailsByFieldName('idlco',$customerDetails['lcoid']))&&(!empty($lcoDetails['error'])))
	{
		return $lcoDetails;
	}

	//get MSO details
	if(($msoDetails = getMSODetailsByFieldName('idmso',$customerDetails['msoid']))&&(!empty($msoDetails['error'])))
	{
		//return $msoDetails;
	}

	//get device details
	if(($device = getDeviceBySerialNumberBySuperUser($customerDetails['serialno']))&&(isset($device['error'])))
	{
		return $device;
	}

	error_log("Device details ==> ".json_encode($device));

	$pdfFormData = array ();
	$pdfFormData['firstname'] = $customerDetails['firstname'];
	$pdfFormData['lastname'] = $customerDetails['surname'];
	$pdfFormData['middlename'] = "---";
	$pdfFormData['doorno'] = "---";
	$pdfFormData['building'] = "---";
	$pdfFormData['area'] = "---";
	$pdfFormData['landmark'] = "---";
	$pdfFormData['city'] = $customerDetails['city'];
	$pdfFormData['landline'] = "---";
	$pdfFormData['residingfrom'] = $data['residingfrom'];
	$pdfFormData['mobileno'] = $customerDetails['mobileno'];
	$pdfFormData['email'] = $customerDetails['email'];
	$pdfFormData['address1'] = $customerDetails['address1'];
	$pdfFormData['postalcode'] = $customerDetails['postalcode'];
	$pdfFormData['serialno'] = $customerDetails['serialno'];
	$pdfFormData['lcoid'] = $customerDetails['lcoid'];
	$pdfFormData['lconame'] = $lcoDetails['firstname'];
	$pdfFormData['msoid'] = $customerDetails['msoid'];
	$pdfFormData['msoname'] = '---';
	if(!empty($msoDetails['firstname']))
	{
		$pdfFormData['msoname'] = $msoDetails['firstname'];
	}
	$pdfFormData['smcno'] = $device['macaddress'];
	$pdfFormData['customerid'] = $customerDetails['customerid'];
	$pdfFormData['residence'] = $data['residence'];
	$pdfFormData['idproof'] = $data['idproof'];
	$pdfFormData['addressproof'] = $data['addressproof'];
	$pdfFormData['subscribercategory'] = $data['subscribercategory'];
	$pdfFormData['subscribercategoryname'] = "---";
	if(isset($data['subscribercategoryname']))
		$pdfFormData['subscribercategoryname'] = $data['subscribercategoryname'];
	$pdfFormData['paymentmode'] = $data['paymentmode'];
	$pdfFormData['chequeno'] = '---';
	$pdfFormData['bankname'] = '---';
	$pdfFormData['chequedate'] = '---';
	$pdfFormData['chequeamount'] = '---';
	if(isset($data['chequenumber']))
		$pdfFormData['chequeno'] = $data['chequenumber'];
	if(isset($data['bankname']))
		$pdfFormData['bankname'] = $data['bankname'];
	if(isset($data['chequedate']))
		$pdfFormData['chequedate'] = $data['chequedate'];
	if(isset($data['chequeamount']))
		$pdfFormData['chequeamount'] = $data['chequeamount'];
	$pdfFormData['swproductname'] = $customerDetails['swproductname'];
	$pdfFormData['swproductprice'] = ($customerDetails['swproductprice']*VHITS_PRODUCT_TAX_RATE);
	$pdfFormData['distid'] = '---';
	$pdfFormData['distname'] = '---';
	$pdfFormData['hwproductname'] = $customerDetails['hwproductname'];
	$pdfFormData['hwproductprice'] = 0;
	$pdfFormData['signature'] = NULL;
	if(isset($data['signature']))
		$pdfFormData['signature'] = $data['signature'];

	error_log('pdf data ==>'.json_encode($pdfFormData));

	try
	{
		ob_start();

		//include dirname(__FILE__).'/res/exemple00.php';
		include dirname(__FILE__).'/../html2pdf/res/exemple01.php';
		$content = ob_get_clean();

		$html2pdf = new Html2Pdf('P','A4','fr');
		$html2pdf->setDefaultFont('Arial');

		$html2pdf->writeHTML($content);

		// $html2pdf->output('exemple00.pdf');
		//$html2pdf->output('firstPage.pdf','D');

		$html2pdf->output(VHITS_CRF_FOLDER_PATH.$data['customerid'].'.pdf','F');

		error_log("Success");
	}
	catch(Html2PdfException $e)
	{
		$formatter = new ExceptionFormatter($e);
		error_log($formatter->getHtmlMessage());
	}
	/*
	 * $reader = new RtfReader();
	 * $rtf = file_get_contents(VHITS_EMAIL_TEMPLATE_FOLDER."caf_template.rtf"); // or use a string
	 * $reader->Parse($rtf);
	 * //$reader->root->dump();
	 * date_default_timezone_set(INDIAN_TIME_ZONE);
	 * $search=array("serialno}}","{{firstname}}","{{lastname}}","{{address1}}","{{address2}}","{{landline}}","{{mobileno}}","{{email}}","residence}}","{{residingfrom}}","idproof}}","{{addressproof}}","subscribercategory}}","{{msocode}}","{{frcode}}","{{rs}}","{{can}}","{{stbno}}","{{childaccount}}");
	 *
	 * $replace=array('312315646544','Zakaria','KM','HSR Layout','Bangalore','0802255664','9008656212','zakaria.km@mobiotics.com','Rented','03/05/2010','Aadhar Card','Tell. Bill','Individual','1122535645','2354212365','150.00','12345678','30000000000095','N');
	 *
	 *
	 * $formatter = new RtfHtml();
	 * $html=$formatter->Format($reader->root);
	 * $html=str_replace($search, $replace, $html);
	 *
	 * error_log($html);
	 * //echo $html;
	 * $dompdf = new DOMPDF();
	 * $dompdf->load_html($html);
	 * $dompdf->render();
	 * //$dompdf->stream("HITS_CRF.pdf");
	 *
	 * $output = $dompdf->output();
	 * file_put_contents(VHITS_LOCAL_TEMP_FOLDER.'CRF_'.$data['customerid'].'.pdf', $output);
	 */

	return array ('success'=>$data['customerid'] );
}
function CRFDataValidation($data)
{
	//validation
	$allowedAddressProof = array ('elecbill','telbill','bankstatement','rationcard','leaseagreement' );
	if(!in_array($data['addressproof'],$allowedAddressProof))
	{
		return formatError(7002);
	}

	$allowedIDProof = array ('aadhaar','passport','voterid','dl','pancard' );
	if(!in_array($data['idproof'],$allowedIDProof))
	{
		return formatError(7003);
	}

	$allowedResidence = array ('owned','rented' );
	if(!in_array($data['residence'],$allowedResidence))
	{
		return formatError(7004);
	}

	$allowedPaymentmode = array ('cash','cheque' );
	if(!in_array($data['paymentmode'],$allowedPaymentmode))
	{
		return formatError(7005);
	}

	$allowedSubscriberCategory = array ('individual','institution','hotel','housing','office','other' );
	if(!in_array($data['subscribercategory'],$allowedSubscriberCategory))
	{
		return formatError(7006);
	}

	if(($data['subscribercategory']=='other')&&(empty($data['subscribercategoryname'])))
	{
		return formatError(2000);
	}

	if(($data['paymentmode']=='cheque')&&(empty($data['chequenumber'])||empty($data['chequeamount'])||empty($data['bankname'])||empty($data['chequedate'])))
	{
		return formatError(2000);
	}

	return true;
}
function listDpoEntity($lcoid = null,$dpoentity = null,$dpoentityname = null)
{
	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	$query = sprintf("SELECT dpoentity,dpoentityname,dpoentitycustomerid FROM dpoentity ");

	if(!empty($lcoid))
	{
		$query .= getDBConnectorPhrase($query).sprintf(" dpoid= '%s'",mysql_fix_string($lcoid,$mysqlcon));
	}
	if(!empty($dpoentity))
	{
		$query .= getDBConnectorPhrase($query).sprintf(" dpoentity= '%s'",mysql_fix_string($dpoentity,$mysqlcon));
	}
	if(!empty($dpoentityname))
	{
		$query .= getDBConnectorPhrase($query).sprintf(" dpoentityname= '%s'",mysql_fix_string($dpoentityname,$mysqlcon));
	}

	$query .= " ORDER BY dpoid ASC";

	//$query.=sprintf(" LIMIT %u , %u",($page-1)*VHITS_DB_PAGE_LIMIT,VHITS_DB_PAGE_LIMIT);

	hlog_errorlog($query);

	if(!($result = $mysqlcon->query($query)))
	{
		error_log("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		$mysqlcon->close();
		return formatError(1001);
	}

	if($result->num_rows==0)
	{
		$mysqlcon->close();
		return formatError(1022);
	}

	$val = array ();

	while($row = $result->fetch_assoc())
	{
		$val[] = $row;
	}

	$result->close();
	$mysqlcon->close();

	return $val;
}
function updateNewDeviceIdInCustomerTable($lcoid,$customerid,$newdeviceid)
{
	$close = false;

	if(empty($mysqlcon))
	{
		if(!$mysqlcon = getHITSDBConnection())
		{
			return formatError(10001);
		}
		$close = true;
	}
	$tablename = getCustomerTableName($lcoid);
	hlog_errorlog('query==>'.$customerid);
	$query = sprintf("UPDATE ".$tablename." SET initdevice = '%s' where idcustomer = '%s'",mysql_fix_string($newdeviceid,$mysqlcon),mysql_fix_string($customerid,$mysqlcon));

	hlog_errorlog($query);

	if(!($stmt = $mysqlcon->prepare($query)))
	{
		error_log("Update customer failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		return formatError(1001);
	}

	if(!($stmt->execute()))
	{
		error_log("Update customer failed: (".$stmt->errno.") ".$stmt->error);
		error_log("SQL Error Number===".$stmt->errno);
		$errorno = 1036;

		if($stmt->errno==1062)
		{
			$errorno = 1017;
		}

		return formatError($errorno);
	}

	if($stmt->affected_rows==0)
	{
		error_log("No change in customerdoc");
		return formatError(1071);
		//return array("success"=>$customerid);
	}

	$stmt->close();

	if($close)
	{
		$mysqlcon->close();
	}

	return array ("success"=>$customerid );
}
function bulkCustomerClassTypeChange($data,$initiatorid,$initiatortype = 'ADMIN')
{
	if(empty($data['filename']))
	{
		return formatError(2000);
	}

	$filename = $data['filename'];
	$originalfilename = $filename;
	$filename = VHITS_LOCAL_TEMP_FOLDER.$filename;

	error_log("This File Name===>".$filename);
	if(!file_exists($filename))
	{
		error_log("No file exist currently===>".$filename);
		return formatError(2007);
	}

	$rowcount = 1;
	$firstrow = TRUE;
	$secondrow = TRUE;

	// read uplded file
	$file = fopen($filename,"r");

	while(!feof($file))
	{
		$thisrow = fgetcsv($file);

		error_log("This Row Data===>".json_encode($thisrow));

		if(empty($thisrow))
		{
			break;
		}

		// check header is correct or not
		if($firstrow)
		{
			if(($thisrow[0]!="cannumber")||($thisrow[1]!="newtype")||($thisrow[2]!="newclass"))
			{
				return array ("error"=>"Wrong header in row : ".$rowcount,"errorcode"=>2000 );
			}

			$firstrow = FALSE;
			continue;
		}

		if((empty($thisrow[0]))||((empty($thisrow[1]))&&(empty($thisrow[2]))))
		{
			return array ("error"=>"Invalid data in row : ".$rowcount,"errorcode"=>2000 );
		}
		$thisrow[0] = trim($thisrow[0]);

		if(!is_numeric($thisrow[0]))
		{
			return array ("error"=>"Invalid Customer number Specified in row ==> ".$rowcount,"errorcode"=>2000 );
		}
		if(!empty($thisrow[1]))
		{
			$thisrow[1] = trim(strtoupper($thisrow[1]));
			if(($thisrow[1]!='DIRECT')&&($thisrow[1]!='INDIRECT')&&($thisrow[1]!='PRIMARY')&&($thisrow[1]!='SECONDARY'))
			{
				return array ("error"=>"Invalid TYPE Specified in row ==> ".$rowcount,"errorcode"=>2000 );
			}
		}
		if(!empty($thisrow[2]))
		{
			$thisrow[2] = trim(strtoupper($thisrow[2]));
			if(($thisrow[2]!='PREPAID')&&($thisrow[2]!='POSTPAID')&&($thisrow[2]!='HOTEL')&&($thisrow[2]!='CUSTOMER'))
			{
				return array ("error"=>"Invalid CLASS Specified in row ==> ".$rowcount,"errorcode"=>2000 );
			}
		}
		if((!empty($thisrow[2]))&&(!empty($thisrow[1])))
		{
			if(($thisrow[1]=="INDIRECT")&&($thisrow[2]=='POSTPAID'))
			{
				return array ("error"=>"Postpaid customer can't be indirect type in row ".$rowcount,"errorcode"=>2000 );
			}
		}

		$secondrow = FALSE;

		$rowcount++;
	}
	if($secondrow)
	{
		return array ("error"=>"Missing parameter in row : ".$rowcount,"errorcode"=>2000 );
	}

	$username = $initiatortype;

	$jobdata = array ();
	$jobdata['jobtype'] = VHITS_PROCESS_BULK_CUSTOMER_CLASS_AND_TYPE_CHANGE;
	$jobdata['initiatorid'] = $initiatorid;
	$jobdata['initiatortype'] = $initiatortype;
	$jobdata['metadata'] = $originalfilename;
	$jobdata['queuename'] = VHITS_BULK_CUSTOMER_CLASS_AND_TYPE_CHANGE_QUEUE_NAME;
	$jobdata['numtotal'] = $rowcount-1;
	$jobdata['username'] = $username;
	if(($bulkreportentry = insertIntoBulkreortTable($jobdata))&&(!empty($bulkreportentry['error'])))
	{
		return $bulkreportentry;
	}
	else
	{
		$jobdata['jobid'] = $bulkreportentry['success'];
		// Put into Queue
		$result = setDataToQueue($jobdata,$jobdata['queuename']);
		hlog($initiatorid.LLOG_SEPARATOR.$originalfilename.LLOG_SEPARATOR.$jobdata['jobid'].LLOG_SEPARATOR."INITIATED",VHITS_CUSTOMER_CLASS_AND_TYPE_CHANGE_LOG,VHITS_LOGLEVEL_CRITICAL);
	}
	return array ("success"=>$bulkreportentry['success'] );
}
function bulkAddFinancialAccount($initiatorid,$initiatortype,$data,$username)
{
	if(empty($data['filename']))
	{
		return formatError(2000);
	}

	$filename = $data['filename'];
	$originalfilename = $filename;
	$filename = VHITS_LOCAL_TEMP_FOLDER.$filename;

	error_log("This File Name===>".$filename);

	if(!file_exists($filename))
	{
		error_log("No file exist currently===>".$filename);
		return formatError(2007);
	}

	$rowcount = 1;
	$firstrow = TRUE;
	$secondrow = TRUE;
	$file = fopen($filename,"r");
	while(!feof($file))
	{
		$thisrow = fgetcsv($file);

		error_log("This Row Data===>".json_encode($thisrow));

		if(empty($thisrow))
		{
			break;
		}

		if($firstrow)
		{
			if(($thisrow[0]!="customerid"))
			{
				return array ("error"=>"Wrong header in row : ".$rowcount,"errorcode"=>2000 );
			}

			if(($thisrow[1]!="financialaccounttype"))
			{
				return array ("error"=>"Wrong header in row : ".$rowcount,"errorcode"=>2000 );
			}

			$firstrow = FALSE;
			continue;
		}

		if(empty($thisrow[0])||empty($thisrow[1]))
		{
			return array ("error"=>"Missing parameter in row : ".$rowcount,"errorcode"=>2000 );
		}

		if(!empty($thisrow[1]))
		{
			$thisrow[1] = trim(strtoupper($thisrow[1]));
			if(($thisrow[1]!='PREPAID')&&($thisrow[1]!='POSTPAID'))
			{
				return array ("error"=>"Invalid Financial Account Type Specified in row ==> ".$rowcount,"errorcode"=>2000 );
			}
		}

		$secondrow = FALSE;

		$rowcount++;
	}
	if($secondrow)
	{
		return array ("error"=>"Missing parameter in row : ".$rowcount,"errorcode"=>2000 );
	}

	$username = $initiatortype;

	$jobdata = array ();
	$jobdata['jobtype'] = VHITS_PROCESS_BULK_ADD_FINANCIAL_ACCOUNT;
	$jobdata['initiatorid'] = $initiatorid;
	$jobdata['username'] = $username;
	$jobdata['initiatortype'] = $initiatortype;
	$jobdata['metadata'] = $originalfilename;
	$jobdata['queuename'] = VHITS_BULK_ADD_FINANCIAL_ACCOUNT_QUEUE_NAME;
	$jobdata['numtotal'] = $rowcount-1;
	if(($bulkreportentry = insertIntoBulkreortTable($jobdata))&&(!empty($bulkreportentry['error'])))
	{
		return $bulkreportentry;
	}
	else
	{
		$jobdata['jobid'] = $bulkreportentry['success'];
		// Put into Queue
		//Put into Queue
		$result = setDataToQueue($jobdata,$jobdata['queuename']);
		hlog($initiatorid.LLOG_SEPARATOR.$originalfilename.LLOG_SEPARATOR.$jobdata['jobid'].LLOG_SEPARATOR."INITIATED",VHITS_BULK_REPORT_LOG,VHITS_LOGLEVEL_CRITICAL);
	}
	return array ("success"=>$bulkreportentry['success'] );
}
function updateCustomerClassAndType($initiatorid,$customerid,$class = null,$type = null,$username,$initiatortype,$jobid)
{
	$updatecustomerdata = array ();

	if(!empty($type))
	{
		switch($type)
		{
			case 'PRIMARY':
				$type = VHITS_CUSTOMER_PRIMARY_TYPE_ID;
				break;
			case 'SECONDARY':
				$type = VHITS_CUSTOMER_SECONDARY_TYPE_ID;
				break;
			case 'DIRECT':
				$type = VHITS_CUSTOMER_DIRECT_TYPE_ID;
				break;
			default:
				$type = VHITS_CUSTOMER_INDIRECT_TYPE_ID;
				break;
		}
		$updatecustomerdata['customertypeid'] = $type;
	}

	$classid = null;
	if(!empty($class))
	{

		switch($class)
		{
			case 'PREPAID':
				$classid = VHITS_CUSTOMER_PREPAID_CLASS_ID;
				break;
			case 'POSTPAID':
				$classid = VHITS_CUSTOMER_POSTPAID_CLASS_ID;
				break;
			case 'HOTEL':
				$classid = VHITS_CUSTOMER_HOTEL_CLASS_ID;
				break;
			case 'CUSTOMER':
				$classid = VHITS_CUSTOMER_CUSTOMER_CLASS_ID;
				break;
			default:
				$classid = VHITS_CUSTOMER_PREPAID_CLASS_ID;
				break;
		}

		$updatecustomerdata['customerclassid'] = $classid;
		$updatecustomerdata['customertype'] = $class;
	}

	if(($updatecustomer = updateCustomerClassAndTypeBySuperuser($customerid,$classid,$type,$jobid))&&(!empty($updatecustomer['error'])))
	{
		hlog('update customer class and type failed for customer '.$customerid.' class '.$class.' type'.$type.' because of reason '.json_encode($updatecustomer),VHITS_CUSTOMER_CLASS_AND_TYPE_CHANGE_LOG.$jobid);
		return $updatecustomer;
	}

	if(($customerdeatils = getCustomerDetail($username,$customerid,$initiatortype))&&(!empty($customerdeatils['error'])))
	{
		hlog("Failed to get customer details===>".json_encode($customerdeatils),VHITS_CUSTOMER_CLASS_AND_TYPE_CHANGE_LOG.$jobid);
	}
	else
	{
		hlog_errorlog('lcoid=====>'.$customerdeatils['lcoid']);
		$lcoid = $customerdeatils['lcoid'];
		if($updatelocalcustomerdetails = updateCustomerTable($customerid,$updatecustomerdata,null,$jobid,$lcoid))
		{
			hlog("Failed to update customer in our databse customer details===>".json_encode($updatelocalcustomerdetails),VHITS_CUSTOMER_CLASS_AND_TYPE_CHANGE_LOG.$jobid);
		}
	}
	return $updatecustomer;
}
function addFinancialAccount($initiatorid,$customerid,$financialtype,$username,$initatrtype,$jobid = NULL)
{
	$financialtypename = trim(strtoupper($financialtype));

	$typeid = $financialtypename=="PREPAID"?1:23;

	if(($addFinancialAccount = addFinancialAccountSoap($customerid,$typeid))&&(!empty($addFinancialAccount['error'])))
	{
		hlog($customerid.LLOG_SEPARATOR."Failled to add financial account",VHITS_BULK_ADD_FINANCIAL_ACCOUNT_LOG.$jobid,VHITS_LOGLEVEL_CRITICAL);
		return $addFinancialAccount;
	}

	hlog_errorlog("Financial Account added  successfully".$customerid);
	hlog("financial id ====>".json_encode($addFinancialAccount),VHITS_BULK_ADD_FINANCIAL_ACCOUNT_LOG);
	return array ("success"=>$customerid );
}
function createAgreementId($customerid,$financialacountid,$contractperiod,$jobid = NULL)
{
	date_default_timezone_set(INDIAN_TIME_ZONE);
	$date = date("Y-m-dTH:i:s");
	hlog_errorlog("date========>".$date);
	if(($createagreement = createAgreementSoap($customerid,$financialacountid,$contractperiod,$date))&&(!empty($createagreement['error'])))
	{
		hlog($customerid.LLOG_SEPARATOR."Failled to create  agreement for financial id, ".$financialacountid,VHITS_BULK_ADD_FINANCIAL_ACCOUNT_LOG.$jobid,VHITS_LOGLEVEL_CRITICAL);
		return $createagreement;
	}

	hlog_errorlog("agreement  added  successfully".$customerid);
	hlog("Agreement======>".json_encode($createagreement),VHITS_BULK_ADD_FINANCIAL_ACCOUNT_LOG);
	return array ("success"=>$customerid );
}

// this function is to get only customer primary data, if you want plan details then call getCustomerInformation().
// and here customerid should be can number.
function getCustomerPrimaryDetails($username,$customerid,$role)
{
	if($redis = getRedis())
	{
		if($redis->exists('primary:'.$customerid))
		{
			$result = json_decode($redis->get('primary:'.$customerid),true);
			$redis->close();
			return $result;
		}
		else
		{
			if(($customer = getCustomerDetail($username,$customerid,$role))&&(!empty($customer['error'])))
			{
				$redis->close();
				return $customer;
			}
			$value = json_encode($customer);
			$redis->setex('primary:'.$customerid,1800,$value);
			$redis->close();
			return $customer;
		}
	}
}

function getCustomerActivePlans($customerid)
{
	if(($customerdetails = getCustomerDetail(VHITS_HANSEN_SELFCARE_SUPER_USER_NAME,$customerid))&&isset($customerdetails['error']))
    {
         return $customerdetails;
    }

    $param['lcoid'] = $customerdetails['lcoid'];
	$param['customerid'] = $customerid;
	$param['page'] = 1;

    if(($customerproductdetail = listCustomerSubscribedProduct(VHITS_HANSEN_SELFCARE_SUPER_USER_NAME,$param))&&isset($customerproductdetail['error']))
	{
		return $customerproductdetail;
	}

	$newproductlist = array();

	foreach ($customerproductdetail as $key => $value) {
		
	    if($value['productstatus'] != 'ACTIVE')
	    {
	    	unset($customerproductdetail[$key]);
	    }
	    else
	    {
	    	$newproductlist[] = $customerproductdetail[$key];
	    }	
	}

	if(empty($newproductlist))
	{
		return formatError(17011);
	}	
	else
	{
		return $newproductlist;
	}	
}
?>
