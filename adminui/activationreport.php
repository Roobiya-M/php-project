<?php

/**
 * *******************************************************
 * Author: Roobiya M
 * Copyright: Roobiya M
 * Version: 1.0
 * Date: 08-Oct-2022
 * FileName: activationreport.php
 * Description: Cron to get the discrepancy of lcos
 * ********************************************************
 */
error_reporting(E_ALL);

//$baseurl = '/var/www/html/dp_qa/';
$baseurl = '/var/www/html/';
require_once ($baseurl.'include/const.inc.php');
require_once ($baseurl.'utilities/helpers.php');
require_once ($baseurl.'utilities/redisutilities.php');
require_once ($baseurl.'utilities/mailutilities.php');
require_once ($baseurl.'utilities/smsutilities.php');
require_once ($baseurl.'utilities/lcoutilities.php');
require_once ('/usr/share/php/vendor/autoload.php');

if((empty($argv[1]))||(empty($argv[2])))
{
	exit('PLease pass lcoid and filename');
}

$idlco = $argv[1];
$filename = $argv[2];

date_default_timezone_set(INDIAN_TIME_ZONE);
if(($lco = getBasicLCODetails($idlco))&&(!empty($lcos['error'])))
{
	exit("NO Lco to list");
}
$activationtable = getActivationTableName($lco['region'],$lco['frcode']);
error_log("activation table==>".$activationtable);
if(!$fp = fopen('/var/local/tvbuddy/temp/activationreport.csv','w+'))
	return FALSE;

$header = array ('LCOID','ACTIVATIONID','CUSTOMER_ID','CUSTOMER_NAME','PLANID','PLAN_NAME','AMOOUNT','DATE','TIME' );
fputcsv($fp,$header);

$filename = '/var/local/tvbuddy/temp/'.$filename;
$file = fopen($filename,'r');
$lcoarray = array ();
while(!feof($file))
{
	// Read buffer-size bytes
	$thisrow = fgetcsv($file);
	$lcoid = $thisrow[0];
	$amount = $thisrow[1];
	$date = $thisrow[2]." ".$thisrow[3];
	if(empty($lcoarray[$lcoid]))
	{
		if(($lco = getBasicLCODetails($lcoid))&&(!empty($lcos['error'])))
		{
			exit("NO Lco to list");
		}
		$lcoarray[$lcoid] = $lco;
	}
	$lco = $lcoarray[$lcoid];
	$activationtable = getActivationTableName($lco['region'],$lco['frcode']);
	error_log("activation table==>".$activationtable);

	$activationtable = getActivationTableName($lco['region'],$lco['frcode']);
	error_log("activation table==>".$activationtable);

	// Read buffer-size bytes

	if(($activations = getActivationRecord($activationtable,$lcoid,$amount,$date))&&(empty($activations['error'])))
	{
		foreach($activations as $activation)
		{
			fputcsv($fp,array ("lcoid=>".$lco['lcoid'],"activationid"=>$activation['idactivation'],"customerid"=>$activation['subscriberid'],"customername"=>$activation['customername'],"planid"=>$activation['idplan'],"planname"=>$activation['planname'],"amount"=>$activation['amount'],"date"=>$activation['date'],"time"=>$activation['time'] ));
		}
	}
}
fclose($file);

$toaddress = array ("rukmini.patel@mobiotics.com" );
$filename = "activationreport.csv";
$filelocation = "/var/local/tvbuddy/temp/";
/*
 * $zipName = "failedactivation.zip";
 *
 * $zip = new ZipArchive;
 * $zip->open($zipPath.$zipName, ZipArchive::CREATE);
 * $zip->addFile($filename, basename($filename));
 * $zip->close();
 *
 * error_log("Zip filename ====>".$zipPath.$zipName);
 */

$subject = 'Analysis Report';
$message = 'Please find Attachment.';
error_log(filesize($filelocation.$filename));

sendRawEmailNew($subject,$filelocation,$filename,$message,$toaddress);

error_log('*********done**********');
exit(0);
function getActivationRecord($activationtable,$lcoid,$amount,$date)
{
	date_default_timezone_set(INDIAN_TIME_ZONE);

	$mysqlcon = getLCODBConnection();
	//error_log($date);
	$date = date('Y-m-d H:i:s',strtotime('-5 hour -30 minutes',strtotime($date)));
	$fromdate = date('Y-m-d H:i:s',strtotime('-0 hour -0 minutes -15 seconds',strtotime($date)));
	/*
	 * error_log($date);
	 * error_log($fromdate);
	 */

	$query = sprintf("SELECT a.lcoid,a.idactivation,a.subscriberid,a.customername,p.idplan,p.planname,a.actlcoprice as amount, DATE_ADD(a.created,INTERVAL '5:30' HOUR_MINUTE) as created FROM $activationtable a join plan p on (p.idplan = a.planid) where a.lcoid = '%s' and a.created >= '%s' and a.created <= '%s' and a.actlcoprice = %s  and a.status = 'ACTIVE'",mysql_fix_string($lcoid,$mysqlcon),mysql_fix_string($fromdate,$mysqlcon),mysql_fix_string($date,$mysqlcon),mysql_fix_string($amount,$mysqlcon));

	error_log($query);

	if(!($result = $mysqlcon->query($query)))
	{
		error_log("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		return formatError("failed to get table names");
	}

	if($result->num_rows==0)
	{
		error_log("No tables");
		return formatError("No Tables");
	}

	$values = array ();
	while($row = $result->fetch_assoc())
	{
		$created = explode(' ',$row['created']);
		$row['date'] = $created[0];
		$row['time'] = $created[1];
		$values[] = $row;
	}

	$result->close();
	$mysqlcon->close();
	error_log(json_encode($values));
	return $values;
}

?>
