<?php
/**
 * *******************************************************
 * Author: Manish Giri
 * Copyright: Mobiotics IT Solution Private Limited
 * Version: 1.0
 * Date: 03-Aug-2015
 * FileName: utilities.php
 * Description: Utility Functions
 * ********************************************************
 */
error_reporting(E_ALL);
use Firebase\JWT\JWT;

// Log and error log file to be used for Hive Analysis
function hlog_errorlog($msg,$jobid = null)
{
	$currentuser = exec('whoami');

	// error_log("hlog_errorlog_user===>".$currentuser);
	if(in_array($currentuser,array ("root" )))
	{
		// tvbuddy hlog
		hlog(json_encode($msg).LLOG_SEPARATOR.$jobid,VHITS_HLOG,VHITS_LOGLEVEL_CRITICAL);
	}
	else
	{
		error_log($msg);
	}
}

/*
 * function hlog($msg,$logfile,$setperm=false)
 * {
 * date_default_timezone_set(INDIAN_TIME_ZONE);
 * $today = date("Y-m-d");
 * $filename = VHITS_LOG_LOCATION.$logfile.'_'.$today.'.csv';
 *
 * $fd = fopen($filename, "a");
 * $timestamp = round(microtime(true));
 * fwrite($fd, $timestamp.",".$msg . PHP_EOL);
 * fclose($fd);
 * if($setperm)
 * {
 * //File permission Reset
 * chmod($filename,0777);
 * }
 * }
 */
function dlog($msg,$logfile,$ext)
{
	$filename = VHITS_LOG_LOCATION.$logfile.$ext;
	$fd = fopen($filename,"a");
	$timestamp = round(microtime(true));
	fwrite($fd,$timestamp.' '.$msg.PHP_EOL);
	fclose($fd);
}
function mysql_fix_string($string,$mysqlcon)
{
	if(get_magic_quotes_gpc())
	{
		$string = stripslashes($string);
	}

	return $mysqlcon->real_escape_string($string);
}

// Fail with error json
function HTTPFail($message)
{
	hlog_errorlog($message);
	print json_encode(array ('error'=>$message ));
	exit(0);
}

// Fail with HTTP Code
function HTTPFailWithCode($code,$message)
{
	header(reasonForCode($code));
	exit($message);
}
function HTTPRedirect($location)
{
	print json_encode(array ('redirect'=>$location ));
	exit(0);
}

// HTTP reason codes
function reasonForCode($code)
{
	switch($code)
	{
		case 100:
			$text = 'Continue';
			break;
		case 101:
			$text = 'Switching Protocols';
			break;
		case 200:
			$text = 'OK';
			break;
		case 201:
			$text = 'Created';
			break;
		case 202:
			$text = 'Accepted';
			break;
		case 203:
			$text = 'Non-Authoritative Information';
			break;
		case 204:
			$text = 'No Content';
			break;
		case 205:
			$text = 'Reset Content';
			break;
		case 206:
			$text = 'Partial Content';
			break;
		case 300:
			$text = 'Multiple Choices';
			break;
		case 301:
			$text = 'Moved Permanently';
			break;
		case 302:
			$text = 'Moved Temporarily';
			break;
		case 303:
			$text = 'See Other';
			break;
		case 304:
			$text = 'Not Modified';
			break;
		case 305:
			$text = 'Use Proxy';
			break;
		case 400:
			$text = 'Bad Request';
			break;
		case 401:
			$text = 'Unauthorized';
			break;
		case 402:
			$text = 'Payment Required';
			break;
		case 403:
			$text = 'Forbidden';
			break;
		case 404:
			$text = 'Not Found';
			break;
		case 405:
			$text = 'Method Not Allowed';
			break;
		case 406:
			$text = 'Not Acceptable';
			break;
		case 407:
			$text = 'Proxy Authentication Required';
			break;
		case 408:
			$text = 'Request Time-out';
			break;
		case 409:
			$text = 'Conflict';
			break;
		case 410:
			$text = 'Gone';
			break;
		case 411:
			$text = 'Length Required';
			break;
		case 412:
			$text = 'Precondition Failed';
			break;
		case 413:
			$text = 'Request Entity Too Large';
			break;
		case 414:
			$text = 'Request-URI Too Large';
			break;
		case 415:
			$text = 'Unsupported Media Type';
			break;
		case 500:
			$text = 'Internal Server Error';
			break;
		case 501:
			$text = 'Not Implemented';
			break;
		case 502:
			$text = 'Bad Gateway';
			break;
		case 503:
			$text = 'Service Unavailable';
			break;
		case 504:
			$text = 'Gateway Time-out';
			break;
		case 505:
			$text = 'HTTP Version not supported';
			break;
		default:
			$text = 'Unknown Error';
			break;
	}

	return 'HTTP/1.1'.' '.$code.' '.$text;
}

// Only alphanumeric characters and underscore permitted
function gen_uuid($len = 8)
{
	$hex = md5("tvbuddy2_mobiotics".uniqid("",true));

	$pack = pack('H*',$hex);

	$uid = base64_encode($pack); // max 22 chars

	$uid = preg_replace("/[^a-z0-9_]+/i","",$uid); // mixed case

	if($len<4)
		$len = 4;
	if($len>128)
		$len = 128; // prevent silliness, can remove

	while(strlen($uid)<$len)
		$uid = $uid.gen_uuid(22); // append until length achieved

	return substr($uid,0,$len);
}
function sessionValidate($roleid,$id = NULL)
{
	$status = session_status();

	if($status==PHP_SESSION_NONE)
	{
		// There is no active session
		session_start();
	}

	if(!isset($_SESSION[$roleid]))
	{

		return false;
	}

	if(!empty($id)&&$_SESSION[$roleid]!==$id)
	{

		return false;
	}

	$id = $_SESSION[$roleid];

	hlog_errorlog("CurrentTime===>".time()."===>SeesionTime===>".$_SESSION[VHITS_LOGIN_TIME]);

	if((time()-$_SESSION[VHITS_LOGIN_TIME])>=SESSION_EXPIRY_TIME)
	{
		hlog_errorlog('Session Expired: '.$id.' from '.$_SERVER['REMOTE_ADDR']);
		session_destroy();
		return false;
	}

	// $_SESSION[VHITS_LOGIN_TIME] = time();

	return $id;
}
function getMemcache()
{
	$memcache = new MemCache();
	$memcache->connect(MEM_CACHE_SERVER_NAME,MEM_CACHE_SERVER_PORT);
	return $memcache;
}
function getHITSDBConnection()
{
	// Now create db connection
	$mysqlcon = new mysqli(VHITS_HITSDB_SERVER,VHITS_HITSDB_USER_NAME,VHITS_HITSDB_PASSWORD,VHITS_HITSDB_NAME);

	if($mysqlcon->connect_errno)
	{
		// if connection fails, sleep and retry
		hlog_errorlog("Connection failed. Trying to connect for 2nd time");
		sleep(1);

		$mysqlcon = new mysqli(VHITS_HITSDB_SERVER,VHITS_HITSDB_USER_NAME,VHITS_HITSDB_PASSWORD,VHITS_HITSDB_NAME);

		if($mysqlcon->connect_errno)
		{
			hlog_errorlog("Connection failed 2nd time. Trying to connect for 3rd time");
			sleep(1);

			$mysqlcon = new mysqli(VHITS_HITSDB_SERVER,VHITS_HITSDB_USER_NAME,VHITS_HITSDB_PASSWORD,VHITS_HITSDB_NAME);

			if($mysqlcon->connect_errno)
			{

				hlog_errorlog("Failed to connect to MySQL: (".$mysqlcon->connect_errno.") ".$mysqlcon->connect_error);
				return false;
			}
		}
	}

	return $mysqlcon;
}
function getHITSBACKUPDBConnection()
{
	hlog_errorlog("downloading from backup database");
	// Now create db connection
	$mysqlcon = new mysqli(VHITS_HITSBACKUPDB_SERVER,VHITS_HITSDB_USER_NAME,VHITS_HITSDB_PASSWORD,VHITS_HITSDB_NAME);

	if($mysqlcon->connect_errno)
	{
		// if connection fails, sleep and retry
		hlog_errorlog("Connection failed. Trying to connect for 2nd time");
		sleep(1);

		$mysqlcon = new mysqli(VHITS_HITSBACKUPDB_SERVER,VHITS_HITSDB_USER_NAME,VHITS_HITSDB_PASSWORD,VHITS_HITSDB_NAME);

		if($mysqlcon->connect_errno)
		{
			hlog_errorlog("Connection failed 2nd time. Trying to connect for 3rd time");
			sleep(1);

			$mysqlcon = new mysqli(VHITS_HITSBACKUPDB_SERVER,VHITS_HITSDB_USER_NAME,VHITS_HITSDB_PASSWORD,VHITS_HITSDB_NAME);

			if($mysqlcon->connect_errno)
			{

				hlog_errorlog("Failed to connect to MySQL: (".$mysqlcon->connect_errno.") ".$mysqlcon->connect_error);
				return false;
			}
		}
	}

	return $mysqlcon;
}
function getHITSArchiveDBConnection()
{
	// Now create db connection
	$mysqlcon = new mysqli(VHITS_HITSDB_SERVER,VHITS_HITSDB_USER_NAME,VHITS_HITSDB_PASSWORD,VHITS_HITSARCHIVEDB_NAME);

	if($mysqlcon->connect_errno)
	{

		hlog_errorlog("Failed to connect to MySQL: (".$mysqlcon->connect_errno.") ".$mysqlcon->connect_error);
		return false;
	}

	return $mysqlcon;
}
function createRandomString($length = 8,$type,$prefix = NULL)
{
	$characters = '';

	switch($type)
	{
		case CHAR_TYPE_NUMERIC:
			$characters = '0123456789';
			break;

		case CHAR_TYPE_ALPHABETS:
			$characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			break;

		case CHAR_TYPE_ALPHANUMERIC:
		default:
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			break;
	}

	$randomString = '';

	for($i = 0;$i<$length;$i++)
	{
		$randomString .= $characters[rand(0,strlen($characters)-1)];
	}

	if(!empty($prefix))
	{
		return $prefix.$randomString;
	}

	return $randomString;
}
function getMimeTypeForExtension($extn)
{
	$extn = strtolower($extn);
	switch($extn)
	{
		case 'png':
			return MIMETYPE_IMAGE_PNG;
		case 'jpg':
			return MIMETYPE_IMAGE_JPG;
		case 'jpeg':
			return MIMETYPE_IMAGE_JPG;
		case 'mp3':
			return MIMETYPE_AUDIO_MPEG;
		case 'm4a':
			return MIMETYPE_AUDIO_MP4;
		case 'csv':
			return MIMETYPE_TEXT_CSV;
		case 'pdf':
			return MIMETYPE_TEXT_PDF;
		default:
			return false;
	}
}
function cleanRedisData($data)
{
	foreach($data as $key => $value)
	{
		if(empty($value))
		{
			$data[$key] = NULL;
		}
	}
	return $data;
}
function formatError($number)
{
	$error = 'Unknown Error';

	if(!empty($GLOBALS['errorcodes'][$number]))
	{
		$error = $GLOBALS['errorcodes'][$number];
	}

	hlog_errorlog($number."===>".$error);

	return array ("errorcode"=>$number,'error'=>$error );
}
function formatErrorMessage($message)
{
	$error = array ('error'=>$message );
	hlog_errorlog($message);
	return $error;
}
function getLanguageCode($code,$full = FALSE)
{
	$code2array = array ('ur','mr','bn','hi','en','gu','kn','ml','de','fr','pa','bh','ta','te','or','ko','ne','as','ks','sa','sd' );
	$code3array = array ('urd','mar','ben','hin','eng','kan','mal','deu','fra','pan','bih','tel','ori','kor','nep','asm','kas','san','snd' );

	$languagearray = array ('Urdu','Marathi','Bengali','Hindi','English','Gujarathi','Kannada','Malayalam','German','French','Panjabi','Bihari','Tamil','Telugu','Oriya','Korean','Nepali','Assamese','Kashmiri','Sanskrit','Sindhi' );

	// Get index
	if(strlen($code)===2)
	{
		if(!($index = array_search($code,$code2array)))
		{
			return false;
		}

		if($full)
		{
			return $languagearray[$index];
		}

		return $code3array[$index];
	}

	// Get index
	if(strlen($code)===3)
	{
		if(!($index = array_search($code,$code3array)))
		{
			return false;
		}

		if($full)
		{
			return $languagearray[$index];
		}

		return $code2array[$index];
	}
}
function toMonthString($num)
{
	$months = array ('JANUARY','FEBRUARY','MARCH','APRIL','MAY','JUNE','JULY','AUGUST','SEPTEMBER','OCTOBER','NOVEMBER','DECEMBER' );

	if($num<1 or $num>12)
	{
		return false;
	}

	return $months[$num-1];
}
function getDBConnectorPhrase($query)
{
	if(!stristr($query,'WHERE'))
	{
		return " WHERE ";
	}
	else
	{
		return " AND ";
	}
}
function loadCSVDataWithKeys($csvfile,$stripempty,$linelength)
{
	$storedata = array ();
	ini_set('auto_detect_line_endings',TRUE);

	if(($handle = fopen($csvfile,"r"))!==FALSE)
	{
		$keys = fgetcsv($handle,$linelength);

		do
		{
			$data = array_combine($keys,fgetcsv($handle,$linelength));

			// Remove empty lines
			if(!empty($data))
			{

				if($stripempty)
				{
					foreach($data as $key => $value)
					{
						if(empty($value))
						{
							unset($data[$key]);
						}
					}
				}
				$ndata[] = $data;
			}
		}while(!feof($handle));
	}

	if(empty($ndata))
	{
		return false;
	}

	return $ndata;
}
function uploadFilePOST($dest,$filename)
{
	// hlog_errorlog(json_encode($_FILES));
	/*
	 * if ($_FILES[$filename]['size'] > 6192742) {
	 * hlog_errorlog('File size is more than 6 MB ' . $filename);
	 * return false;
	 * }
	 */
	if($_FILES[$filename]['size']>2097152)
	{
		hlog_errorlog('File size is more than 2 MB '.$filename);
		return false;
	}

	if(!move_uploaded_file($_FILES[$filename]['tmp_name'],$dest))
	{

		hlog_errorlog('Could not upload '.$filename);
		return false;
	}

	return true;
}
function getExtensionForMimeType($mimetype)
{
	switch($mimetype)
	{
		case MIMETYPE_IMAGE_PNG:
			return IMAGE_PNG_EXTN;
		case MIMETYPE_IMAGE_JPG:
			return IMAGE_JPEG_EXTN;
		case MIMETYPE_IMAGE_BMP:
			return IMAGE_BMP_EXTN;
		case MIMETYPE_TEXT_PDF:
			return TEXT_PDF_EXTN;
		case MIMETYPE_TEXT_CSV:
			return TEXT_CSV_EXTN;
		case MIMETYPE_TEXT_PLAIN:
			return TEXT_PLAIN_EXTN;
		case MIMETYPE_VIDEO_MP4:
			return VIDEO_MP4_EXTN;
		case MIMETYPE_AUDIO_MPEG:
			return AUDIO_MP3_EXTN;
		case MIMETYPE_AUDIO_MPEG3:
			return AUDIO_MP3_EXTN;
		case MIMETYPE_AUDIO_X_MPEG3:
			return AUDIO_MP3_EXTN;
		case MIMETYPE_AUDIO_MP3:
			return AUDIO_MP3_EXTN;
		default:
			return false;
	}
}
function processCurl($method,$url,$params,$token = NULL,$jobid = null,$fcm=null)
{
	$time_start_new = microtime(true);
	if($method==="GET")
	{
		$i = 0;
		foreach($params as $key => $value)
		{
			if($i==0)
			{
				$url .= "?".$key."=".$value;
			}
			else
			{
				$url .= "&".$key."=".$value;
			}
			$i++;
		}
	}

	try
	{
		$curl = curl_init($url);

		hlog_errorlog("Curl Init URL===>".$url,$jobid);
		hlog_errorlog("Curl Init Method===>".$method,$jobid);
		error_log("Curl Init Data===>".json_encode($params),$jobid);

		if(!$curl)
		{
			throw new Exception('Invalid Request');
		}

		$headers = array ();
		$headers[] = 'Cache-Control: no-cache';
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Data-Type: application/json';

		if(!empty($token))
		{
			$headers[] = 'Authorization:'.$token;
		}

		curl_setopt($curl,CURLOPT_VERBOSE,FALSE);
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_HEADER,TRUE);
		// curl_setopt($curl,CURLOPT_FOLLOWLOCATION, TRUE);
		// curl_setopt($curl,CURLINFO_HEADER_OUT, TRUE);
		curl_setopt($curl,CURLOPT_HTTPHEADER,$headers);
		curl_setopt($curl,CURLOPT_CONNECTTIMEOUT_MS,VHITS_CURL_CONNECTION_TIMEOUT);
		curl_setopt($curl,CURLOPT_TIMEOUT_MS,VHITS_CURL_EXECUTION_TIMEOUT);
		// curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, FALSE);
		// curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);

		if($fcm)
		{
			curl_setopt($curl,CURLOPT_HTTPHEADER,array ("Content-Type : application/json","Authorization :key=".$token."" ));
		}
		
		switch($method)
		{
			case "POST":
				curl_setopt($curl,CURLOPT_POST,TRUE);
				curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($params));
				break;
			case "PUT":
				curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"PUT");
				$params = json_encode($params);
				// $params = addslashes($params);
				hlog_errorlog("Curl Init PUT Data===>".$params);
				curl_setopt($curl,CURLOPT_POSTFIELDS,$params);
				break;
			case "DELETE":
				curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"DELETE");
				curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($params));
				break;
			case "GET":
				curl_setopt($curl,CURLOPT_HTTPGET,TRUE);
				break;
			default:
				HTTPFailWithCode(405,'This method is not allowed');
				break;
		}

		$response = curl_exec($curl);

		$errNo = curl_errno($curl);

		if($redis = getRedis())
		{
			// Redis key
			$redisKey = "ICC_BSL_API";

			if(!$redis->exists($redisKey))
			{
				$redis->hMSet($redisKey,array ('total'=>0,'timeout'=>0,'status'=>"NORMAL" ));
			}

			$redis->hIncrBy($redisKey,'total','1');

			if($errNo)
			{
				error_log("Curl_Execution_Error_Number===>".$errNo);
				$redis->hIncrBy($redisKey,'timeout','1');
			}

			$redis->close();
		}

		if($errNo)
		{
			hlog_errorlog("Curl_Execution_Error_Number===>".$errNo,$jobid);
			throw new Exception('Request Timeout');
		}

		$responsestatus = curl_getinfo($curl);

		hlog_errorlog("Curl Response Status===>".json_encode($responsestatus),$jobid);

		$responsehttpcode = $responsestatus['http_code'];

		hlog_errorlog("Curl Response HTTP Code===>".$responsehttpcode,$jobid);

		$header_size = $responsestatus['header_size'];
		$responseheader = substr($response,0,$header_size);
		$responsebody = substr($response,$header_size);

		hlog_errorlog("Header Response===>".$responseheader,$jobid);
		hlog_errorlog("Body Response===>".$responsebody,$jobid);

		curl_close($curl);
		$execution_time_new = round((microtime(true)-$time_start_new),2);
		error_log("TotalTime===>".$url."======>".$execution_time_new." seconds");
		if($responsehttpcode==204)
		{
			$headerresponsedata = explode("\r\n",$responseheader);

			$headerdatakeyvalue = array ();

			foreach($headerresponsedata as $headervalue)
			{
				$thisdata = explode(":",$headervalue);
				$thiskey = trim($thisdata[0]);

				if(!empty($thisdata[1]))
				{
					$thisvalue = trim($thisdata[1]);
					$headerdatakeyvalue[$thiskey] = $thisvalue;
				}
			}

			hlog_errorlog("Header Data===>".json_encode($headerdatakeyvalue),$jobid);
			hlog_errorlog("token====>>>".$headerdatakeyvalue['Authorization'],$jobid);
			return array ("token"=>$headerdatakeyvalue['Authorization'] );
		}
		else if($responsehttpcode==200)
		{
			return $responsebody;
		}
		else
		{
			$httperror = reasonForCode($responsehttpcode);
			$httperror = substr($httperror,13);
			$responsebody = json_decode($responsebody,TRUE);

			if(empty($responsebody['Errorcode']))
			{
				return formatError(2019);
			}
			else
			{
				return array ("errorcode"=>$responsebody['Errorcode'],"error"=>$responsebody['ErrorMsg'] );
			}
		}
	}
	catch(Exception $e)
	{
		$message = $e->getMessage();
		hlog_errorlog("Curl Exception===>".$message,$jobid);
		return formatError(2017);
	}
}
function processCurlSelfcare($method,$url,$params,$token = NULL)
{
	if($method==="GET")
	{
		$i = 0;
		foreach($params as $key => $value)
		{
			if($i==0)
			{
				$url .= "?".$key."=".$value;
			}
			else
			{
				$url .= "&".$key."=".$value;
			}
			$i++;
		}
	}

	try
	{
		$curl = curl_init($url);

		hlog_errorlog("Curl Init URL===>".$url);
		hlog_errorlog("Curl Init Method===>".$method);
		hlog_errorlog("Curl Init Data===>".json_encode($params));

		if(!$curl)
		{
			throw new Exception('Invalid Request');
		}

		$headers = array ();
		$headers[] = 'Cache-Control: no-cache';
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Data-Type: application/json';

		if(!empty($token))
		{
			$headers[] = 'Authorization:'.$token;
		}

		curl_setopt($curl,CURLOPT_VERBOSE,FALSE);
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_HEADER,TRUE);
		// curl_setopt($curl,CURLOPT_FOLLOWLOCATION, TRUE);
		// curl_setopt($curl,CURLINFO_HEADER_OUT, TRUE);
		curl_setopt($curl,CURLOPT_HTTPHEADER,$headers);
		curl_setopt($curl,CURLOPT_CONNECTTIMEOUT_MS,VHITS_CURL_CONNECTION_TIMEOUT);
		curl_setopt($curl,CURLOPT_TIMEOUT_MS,VHITS_CURL_EXECUTION_TIMEOUT);
		// curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, FALSE);
		// curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);

		switch($method)
		{
			case "POST":
				curl_setopt($curl,CURLOPT_POST,TRUE);
				curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($params));
				break;
			case "PUT":
				curl_setopt($curl,CURLOPT_PUT,TRUE);
				curl_setopt($curl,CURLOPT_POSTFIELDS,$params);
				break;
			case "DELETE":
				curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"DELETE");
				curl_setopt($curl,CURLOPT_POSTFIELDS,$params);
				break;
			case "GET":
				curl_setopt($curl,CURLOPT_HTTPGET,TRUE);
				break;
			default:
				HTTPFailWithCode(405,'This method is not allowed');
				break;
		}

		$response = curl_exec($curl);

		$errNo = curl_errno($curl);

		if($errNo)
		{
			hlog_errorlog("Curl Execution Error Number===>".$errNo);
			throw new Exception('Request Timeout');
		}

		$responsestatus = curl_getinfo($curl);

		// hlog_errorlog("Curl Response Status===>".json_encode($responsestatus));

		$responsehttpcode = $responsestatus['http_code'];

		hlog_errorlog("Curl Response HTTP Code===>".$responsehttpcode);

		$header_size = $responsestatus['header_size'];
		$responseheader = substr($response,0,$header_size);
		$responsebody = substr($response,$header_size);

		hlog_errorlog("Header Response===>".$responseheader);
		hlog_errorlog("Body Response===>".$responsebody);

		curl_close($curl);

		if($responsehttpcode==200)
		{
			$headerresponsedata = explode("\r\n",$responseheader);

			$headerdatakeyvalue = array ();

			foreach($headerresponsedata as $headervalue)
			{
				$thisdata = explode(":",$headervalue);
				$thiskey = trim($thisdata[0]);

				if(!empty($thisdata[1]))
				{
					$thisvalue = trim($thisdata[1]);
					$headerdatakeyvalue[$thiskey] = $thisvalue;
				}
			}

			hlog_errorlog("Header Data===>".json_encode($headerdatakeyvalue));
			hlog_errorlog("token====>>>".$headerdatakeyvalue['Authorization']);
			$responsebody = json_decode($responsebody,TRUE);
			$responsebody['token'] = $headerdatakeyvalue['Authorization'];
			return $responsebody;

			// return array("token"=>$headerdatakeyvalue['Authorization']);
		}
		else
		{
			$httperror = reasonForCode($responsehttpcode);
			$httperror = substr($httperror,13);
			$responsebody = json_decode($responsebody,TRUE);
			// return json_encode(array("errorcode"=>$responsebody['Errorcode'],"error"=>$responsebody['ErrorMsg']));
			return array ("errorcode"=>$responsebody['Errorcode'],"error"=>$responsebody['ErrorMsg'] );
			// HTTPFailWithCode($responsehttpcode, $httperror);
		}
	}
	catch(Exception $e)
	{
		$message = $e->getMessage();
		hlog_errorlog("Curl Exception===>".$message);
		return formatError(2017);
	}
}

// Hits Website curl
function processCurlHitsWebsite($method,$url,$params)
{
	try
	{
		$ch = curl_init($url);

		hlog_errorlog("Curl Init URL===>".$url);
		hlog_errorlog("Curl Init Method===>".$method);
		hlog_errorlog("Curl Init Data===>".json_encode($params));

		if(!$ch)
		{
			throw new Exception('Invalid Request');
		}

		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_POST,1);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		// curl_setopt($ch, CURLOPT_HTTPHEADER,array('Content-Type: application/application/json'));

		switch($method)
		{
			case "POST":
				curl_setopt($ch,CURLOPT_POSTFIELDS,$params);
				break;
			case "PUT":
				HTTPFailWithCode(405,'This method is not allowed');
				;
				break;
			case "DELETE":
				HTTPFailWithCode(405,'This method is not allowed');
				break;
				HTTPFailWithCode(405,'This method is not allowed');
				break;
			default:
				HTTPFailWithCode(405,'This method is not allowed');
				break;
		}

		$response = curl_exec($ch);
		$errNo = curl_errno($ch);

		if($errNo)
		{
			hlog_errorlog("Curl Execution Error Number===>".$errNo);
			throw new Exception('Request Timeout');
		}

		$responsestatus = curl_getinfo($ch);

		$responsehttpcode = $responsestatus['http_code'];

		hlog_errorlog("Curl Response HTTP Code===>".$responsehttpcode);

		$header_size = $responsestatus['header_size'];
		$responseheader = substr($response,0,$header_size);
		$responsebody = substr($response,$header_size);

		// hlog_errorlog("Header Response===>".$responseheader);
		// hlog_errorlog("Body Response===>".$responsebody);
		hlog_errorlog("Response===>".$response);

		curl_close($ch);

		if($responsehttpcode==200)
		{
			return json_decode($response,TRUE);
		}
		else
		{
			$httperror = reasonForCode($responsehttpcode);
			$httperror = substr($httperror,13);
			$responsebody = json_decode($responsebody,TRUE);
			return array ("errorcode"=>$responsehttpcode,"error"=>$httperror );
			// return json_encode(array("errorcode"=>$responsebody['Errorcode'],"error"=>$responsebody['ErrorMsg']));
			// return array("errorcode"=>$responsebody['Errorcode'],"error"=>$responsebody['ErrorMsg']);
			// HTTPFailWithCode($responsehttpcode, $httperror);
		}
	}
	catch(Exception $e)
	{
		$message = $e->getMessage();
		hlog_errorlog("Curl Exception===>".$message);
		return formatError(2017);
	}
}

// Citrus Payment Check Curl
function processCurlCitrus($method,$url,$params,$refund = false)
{
	try
	{
		$curl = curl_init($url);

		hlog_errorlog("Curl Init URL===>".$url);
		hlog_errorlog("Curl Init Method===>".$method);
		hlog_errorlog("Curl Init Data===>".json_encode($params));

		if(!$curl)
		{
			throw new Exception('Invalid Request');
		}

		$headers = array ();
		$headers[] = 'cache-control:no-cache';

		if((!empty($params['txnType']))&&($params['txnType']=="Refund"))
		{
			$headers[] = 'Content-Type:application/json';
		}
		else
		{
			$headers[] = 'accept:application/json';
		}
		$headers[] = 'access_key:'.CITRUS_ACCESS_KEY;
		if(!empty($params['signature']))
		{
			$headers[] = 'signature:'.$params['signature'];
			unset($params['signature']);
		}

		curl_setopt($curl,CURLOPT_VERBOSE,FALSE);
		curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl,CURLOPT_HEADER,TRUE);
		// curl_setopt($curl,CURLOPT_FOLLOWLOCATION, TRUE);
		// curl_setopt($curl,CURLINFO_HEADER_OUT, TRUE);
		curl_setopt($curl,CURLOPT_HTTPHEADER,$headers);
		curl_setopt($curl,CURLOPT_CONNECTTIMEOUT_MS,VHITS_CURL_CONNECTION_TIMEOUT);
		curl_setopt($curl,CURLOPT_TIMEOUT_MS,VHITS_CURL_EXECUTION_TIMEOUT);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,FALSE);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);

		switch($method)
		{
			case "POST":
				curl_setopt($curl,CURLOPT_POST,TRUE);
				curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($params));
				break;
			case "PUT":
				curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"PUT");
				$params = json_encode($params);
				// $params = addslashes($params);
				hlog_errorlog("Curl Init PUT Data===>".$params);
				curl_setopt($curl,CURLOPT_POSTFIELDS,$params);
				break;
			case "DELETE":
				curl_setopt($curl,CURLOPT_CUSTOMREQUEST,"DELETE");
				curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($params));
				break;
			case "GET":
				curl_setopt($curl,CURLOPT_HTTPGET,TRUE);
				break;
			default:
				HTTPFailWithCode(405,'This method is not allowed');
				break;
		}

		$response = curl_exec($curl);

		$errNo = curl_errno($curl);

		if($errNo)
		{
			hlog_errorlog("Curl Execution Error Number===>".$errNo);
			$err = curl_error($curl);
			throw new Exception($err);
		}

		$responsestatus = curl_getinfo($curl);

		// hlog_errorlog("Curl Response Status===>".json_encode($responsestatus));

		$responsehttpcode = $responsestatus['http_code'];

		hlog_errorlog("Curl Response HTTP Code===>".$responsehttpcode);

		$header_size = $responsestatus['header_size'];
		$responseheader = substr($response,0,$header_size);
		$responsebody = substr($response,$header_size);

		hlog_errorlog("Header Response===>".$responseheader);
		// hlog_errorlog("Body Response===>".$responsebody);
		$responsebody = json_decode($responsebody,TRUE);

		curl_close($curl);

		if($responsehttpcode==200)
		{
			return $responsebody;
		}
		else
		{
			$httperror = reasonForCode($responsehttpcode);
			$httperror = substr($httperror,13);
			return array ("errorcode"=>$responsehttpcode,"error"=>$httperror );
		}
	}
	catch(Exception $e)
	{
		$message = $e->getMessage();
		hlog_errorlog("Curl Exception===>".$message);
		return formatError(2017);
	}
}

// get date list using redis wildcard
function getDateList($key,$redis)
{
	date_default_timezone_set(INDIAN_TIME_ZONE);
	$today = date("Y-m-d");

	$days = array ();

	if($keys = $redis->keys($key.':*'))
	{
		foreach($keys as $key)
		{
			$list = explode(":",$key);
			// Remove today's data as it is still not final

			if(!($today===trim($list[1])))
			{
				$days[] = $list[1];
			}
		}

		return $days;
	}
	else
	{
		return false;
	}
}
function read_docx($filename)
{
	$striped_content = '';
	$content = '';

	$zip = zip_open($filename);

	if(!$zip||is_numeric($zip))
		return false;

	while($zip_entry = zip_read($zip))
	{

		if(zip_entry_open($zip,$zip_entry)==FALSE)
			continue;

		if(zip_entry_name($zip_entry)!="word/document.xml")
			continue;

		$content = zip_entry_read($zip_entry,zip_entry_filesize($zip_entry));
		echo $content."<br>";
		zip_entry_close($zip_entry);
	} // end while

	zip_close($zip);

	$content = str_replace('</w:r></w:p></w:tc><w:tc>'," ",$content);
	$content = str_replace('</w:r></w:p>',"\r\n",$content);
	$striped_content = strip_tags($content);

	// return $content;
}
function readZippedXML($archiveFile,$dataFile = "word/document.xml")
{
	// Create new ZIP archive
	$zip = new ZipArchive();
	// Open received archive file
	if(true===$zip->open($archiveFile))
	{
		// If done, search for the data file in the archive
		if(($index = $zip->locateName($dataFile))!==false)
		{
			// If found, read it to the string
			$data = $zip->getFromIndex($index);
			// Close archive file
			$zip->close();
			// Load XML from a string
			// Skip errors and warnings
			$xml = DOMDocument::loadXML($data,LIBXML_NOENT|LIBXML_XINCLUDE|LIBXML_NOERROR|LIBXML_NOWARNING);
			// Return data without XML formatting tags

			return strip_tags($xml->saveXML());
		}
		$zip->close();
	}

	// In case of failure return empty string
	return "";
}
function rtf2text($filename)
{
	// Read the data from the input file.
	$text = file_get_contents($filename);
	if(!strlen($text))
		return "";

	// Create empty stack array.
	$document = "";
	$stack = array ();
	$j = -1;
	// Read the data character-by- character
	for($i = 0, $len = strlen($text);$i<$len;$i++)
	{
		$c = $text[$i];

		// Depending on current character select the further actions.
		switch($c)
		{
			// the most important key word backslash
			case "\\":
				// read next character
				$nc = $text[$i+1];

				// If it is another backslash or nonbreaking space or hyphen,
				// then the character is plain text and add it to the output stream.
				if($nc=='\\'&&rtf_isPlainText($stack[$j]))
					$document .= '\\';
				elseif($nc=='~'&&rtf_isPlainText($stack[$j]))
					$document .= ' ';
				elseif($nc=='_'&&rtf_isPlainText($stack[$j]))
					$document .= '-';
				// If it is an asterisk mark, add it to the stack.
				elseif($nc=='*')
					$stack[$j]["*"] = true;
				// If it is a single quote, read next two characters that are the hexadecimal notation
				// of a character we should add to the output stream.
				elseif($nc=="'")
				{
					$hex = substr($text,$i+2,2);
					if(rtf_isPlainText($stack[$j]))
						$document .= html_entity_decode("&#".hexdec($hex).";");
					// Shift the pointer.
					$i += 2;
					// Since, we've found the alphabetic character, the next characters are control word
					// and, possibly, some digit parameter.
				}
				elseif($nc>='a'&&$nc<='z'||$nc>='A'&&$nc<='Z')
				{
					$word = "";
					$param = null;

					// Start reading characters after the backslash.
					for($k = $i+1, $m = 0;$k<strlen($text);$k++, $m++)
					{
						$nc = $text[$k];
						// If the current character is a letter and there were no digits before it,
						// then we're still reading the control word. If there were digits, we should stop
						// since we reach the end of the control word.
						if($nc>='a'&&$nc<='z'||$nc>='A'&&$nc<='Z')
						{
							if(empty($param))
								$word .= $nc;
							else
								break;
							// If it is a digit, store the parameter.
						}
						elseif($nc>='0'&&$nc<='9')
							$param .= $nc;
						// Since minus sign may occur only before a digit parameter, check whether
						// $param is empty. Otherwise, we reach the end of the control word.
						elseif($nc=='-')
						{
							if(empty($param))
								$param .= $nc;
							else
								break;
						}
						else
							break;
					}
					// Shift the pointer on the number of read characters.
					$i += $m-1;

					// Start analyzing what we've read. We are interested mostly in control words.
					$toText = "";
					switch(strtolower($word))
					{
						// If the control word is "u", then its parameter is the decimal notation of the
						// Unicode character that should be added to the output stream.
						// We need to check whether the stack contains \ucN control word. If it does,
						// we should remove the N characters from the output stream.
						case "u":
							$toText .= html_entity_decode("&#x".dechex($param).";");
							$ucDelta = @$stack[$j]["uc"];
							if($ucDelta>0)
								$i += $ucDelta;
							break;
						// Select line feeds, spaces and tabs.
						case "par":
						case "page":
						case "column":
						case "line":
						case "lbr":
							$toText .= "\n";
							break;
						case "emspace":
						case "enspace":
						case "qmspace":
							$toText .= " ";
							break;
						case "tab":
							$toText .= "\t";
							break;
						// Add current date and time instead of corresponding labels.
						case "chdate":
							$toText .= date("m.d.Y");
							break;
						case "chdpl":
							$toText .= date("l, j F Y");
							break;
						case "chdpa":
							$toText .= date("D, j M Y");
							break;
						case "chtime":
							$toText .= date("H:i:s");
							break;
						// Replace some reserved characters to their html analogs.
						case "emdash":
							$toText .= html_entity_decode("&mdash;");
							break;
						case "endash":
							$toText .= html_entity_decode("&ndash;");
							break;
						case "bullet":
							$toText .= html_entity_decode("&#149;");
							break;
						case "lquote":
							$toText .= html_entity_decode("&lsquo;");
							break;
						case "rquote":
							$toText .= html_entity_decode("&rsquo;");
							break;
						case "ldblquote":
							$toText .= html_entity_decode("&laquo;");
							break;
						case "rdblquote":
							$toText .= html_entity_decode("&raquo;");
							break;
						// Add all other to the control words stack. If a control word
						// does not include parameters, set &param to true.
						default:
							$stack[$j][strtolower($word)] = empty($param)?true:$param;
							break;
					}
					// Add data to the output stream if required.
					if(rtf_isPlainText($stack[$j]))
						$document .= $toText;
				}

				$i++;
				break;
			// If we read the opening brace {, then new subgroup starts and we add
			// new array stack element and write the data from previous stack element to it.
			case "{":
				array_push($stack,$stack[$j++]);
				break;
			// If we read the closing brace }, then we reach the end of subgroup and should remove
			// the last stack element.
			case "}":
				array_pop($stack);
				$j--;
				break;
			// Skip trash.
			case '\0':
			case '\r':
			case '\f':
			case '\n':
				break;
			// Add other data to the output stream if required.
			default:
				if(rtf_isPlainText($stack[$j]))
					$document .= $c;
				break;
		}
	}
	// Return result.
	return $document;
}
function createCSV($msg,$logfile)
{
	date_default_timezone_set(INDIAN_TIME_ZONE);
	$today = date("Y-m-d");
	$filename = VHITS_LOG_LOCATION.$logfile.'_'.$today.'.csv';

	$fd = fopen($filename,"a");
	fwrite($fd,$msg.PHP_EOL);
	fclose($fd);
}
function createCSVNEW($msg,$logfile)
{
	date_default_timezone_set(INDIAN_TIME_ZONE);
	$today = date("Y-m-d");
	$filename = VHITS_LOCAL_TEMP_FOLDER.$logfile.'_'.$today.'.csv';

	$fd = fopen($filename,"a");
	fwrite($fd,$msg.PHP_EOL);
	fclose($fd);
}
function bulkFileSizeRestriction($filename,$totalline = 5000)
{
	$file = $filename;
	$totalLines = intval(exec("wc -l '$file'"));
	hlog_errorlog("Total CSV Lines===>".$totalLines);

	if($totalLines>$totalline)
	{
		return false;
	}
	else
	{
		return true;
	}
}
function getTotalLineFromFile($filename)
{
	$file = $csvfilename;
	$totalLines = intval(exec("wc -l '$file'"));
	hlog_errorlog("Total File Lines===>".$totalLines);
	return $totalLines;
}
function readFileLineByLine($filename,$line)
{
	$cmd = "head -n ".$line." ".$filename." | tail -1";
	hlog_errorlog("readFileLineByLine===>".$cmd);
	$lineData = exec($cmd);
	$filedata = explode(",",$lineData);
	$filedata = array_map('trim',$filedata);
	hlog_errorlog("readFileLineByLine Data===>".json_encode($filedata));
	return $filedata;
}
function xmlstring_to_array($xmlstring)
{
	$doc = new DOMDocument();
	$doc->loadXML($xmlstring);
	return domnode_to_array($doc->documentElement);
}
function domnode_to_array($node)
{
	$output = array ();

	switch($node->nodeType)
	{
		case XML_CDATA_SECTION_NODE:

		case XML_TEXT_NODE:
			$output = trim($node->textContent);
			break;

		case XML_ELEMENT_NODE:

			for($i = 0, $m = $node->childNodes->length;$i<$m;$i++)
			{
				$child = $node->childNodes->item($i);
				$v = domnode_to_array($child);

				if(!empty($child->tagName))
				{
					$t = $child->tagName;

					if(!isset($output[$t]))
					{
						$output[$t] = array ();
					}

					$output[$t][] = $v;
				}
				elseif($v)
				{
					$output = (string) $v;
				}
			}

			if(is_array($output))
			{
				if($node->attributes->length)
				{
					$a = array ();

					foreach($node->attributes as $attrName => $attrNode)
					{
						$a[$attrName] = (string) $attrNode->value;
					}

					$output['@attributes'] = $a;
				}

				foreach($output as $t => $v)
				{
					if(is_array($v)&&count($v)==1&&$t!='@attributes')
					{
						$output[$t] = $v[0];
					}
				}
			}

			break;
	}

	return $output;
}
function getcssnjsversion()
{
	$timestamp = round(microtime(true));
	// $timestamp = VHITS_RELEASE_VERSION_NUMBER;
	date_default_timezone_set(INDIAN_TIME_ZONE);
	// $timestamp = strtotime("today");
	return $timestamp;
}
function getMessageFromCITRUSCode($code)
{
	switch($code)
	{
		case "ERROR002":
			return formatError(6002);
			break;
		case "ERROR003":
			return formatError(6003);
			break;
		case "ERROR005":
			return formatError(6005);
			break;
		case "ERROR006":
			return formatError(6006);
			break;
		case "ERROR008":
			return formatError(6008);
			break;
		case "ERROR009":
			return formatError(6009);
			break;
		case "ERROR022":
			return formatError(6022);
			break;
		case "ERROR023":
			return formatError(6023);
			break;
		case "ERROR027":
			return formatError(6027);
			break;
		case "ERROR033":
			return formatError(6033);
			break;
		case "ERROR035":
			return formatError(6035);
			break;
		case "ERROR037":
			return formatError(6037);
			break;
		case "ERROR039":
			return formatError(6039);
			break;
		case "ERROR061":
			return formatError(6061);
			break;
		case "ERROR063":
			return formatError(6063);
			break;
		case "ERROR064":
			return formatError(6064);
			break;
		case "ERROR065":
			return formatError(6065);
			break;
		case "ERROR066":
			return formatError(6066);
			break;
		case "ERROR067":
			return formatError(6067);
			break;
		case "ERROR068":
			return formatError(6068);
			break;
		case "ERROR069":
			return formatError(6069);
			break;
		case "ERROR070":
			return formatError(6070);
			break;
		case "ERROR071":
			return formatError(6071);
			break;
		case "ERROR072":
			return formatError(6072);
			break;
		case "ERROR073":
			return formatError(6073);
			break;
		case "ERROR074":
			return formatError(6074);
			break;
		case "ERROR075":
			return formatError(6075);
			break;
		case "ERROR077":
			return formatError(6077);
			break;
		case "ERROR078":
			return formatError(6078);
			break;
		case "ERROR079":
			return formatError(6079);
			break;
		case "ERROR080":
			return formatError(6080);
			break;
		case "ERROR081":
			return formatError(6081);
			break;
		case "ERROR082":
			return formatError(6082);
			break;
		case "ERROR083":
			return formatError(6083);
			break;
		case "ERROR084":
			return formatError(6084);
			break;
		case "ERROR085":
			return formatError(6085);
			break;
		case "ERROR086":
			return formatError(6086);
			break;
		case "ERROR087":
			return formatError(6087);
			break;
		case "ERROR088":
			return formatError(6088);
			break;
		case "ERROR089":
			return formatError(6089);
			break;
		case "ERROR090":
			return formatError(6090);
			break;
		case "ERROR000":
			return formatError(6000);
			break;
		case "ERROR091":
			return formatError(6091);
			break;
		case "ERROR0101":
			return formatError(60101);
			break;
		case "ERROR300":
			return array ("success"=>"Refund successfully submitted" );
			break;
		case "ERROR301":
			return formatError(6301);
			break;
		case "ERROR302":
			return formatError(6302);
			break;
		case "ERROR303":
			return formatError(6303);
			break;
		case "ERROR304":
			return formatError(6304);
			break;
		case "ERROR305":
			return formatError(6305);
			break;
		case "ERROR306":
			return formatError(6306);
			break;
		case "ERROR307":
			return formatError(6307);
			break;
		case "ERROR308":
			return formatError(6308);
			break;
		case "0300":
			return array ("success"=>"Transaction Success" );
			break;
		case "0392":
			return formatError(60392);
			break;
		case "0395":
			return formatError(60395);
			break;
		case "0396":
			return formatError(60396);
			break;
		case "0397":
			return formatError(60397);
			break;
		case "0399":
			return formatError(60399);
			break;
		case "0400":
			return array ("success"=>"Refund Successfull" );
			break;
		case "0401":
			return array ("success"=>"Refund Successfull" );
			break;
		case "0402":
			return array ("success"=>"Refund Successfull" );
			break;
		case "0499":
			return formatError(60499);
			break;
		case "9999":
			return formatError(69999);
			break;
		default:
			return array ("success"=>$code );
			break;
	}
}
function mobileNumberValidator($mobileno)
{
	$mobileno = str_replace('+91','',$mobileno);

	$mobileformat = "/^[789][0-9]{9}$/";

	if(!preg_match($mobileformat,$mobileno))
	{
		return false;
	}
	else
	{
		return $mobileno;
	}
}
function deleteRedisKeyPattern($keypattern)
{
	hlog_errorlog("This Redis Key Pattern===>".$keypattern);
	$successcount = 0;

	if($redis = getRedis())
	{
		if($keyslist = $redis->keys($keypattern))
		{
			foreach($keyslist as $thiskey)
			{
				hlog_errorlog("This Redis Key===>".$thiskey);
				$redis->del($thiskey);
				$successcount++;
			}

			return array ("success"=>$successcount );
		}
		else
		{
			$redis->close();
			return formatError(1022);
		}
	}
	else
	{
		return formatError(1002);
	}
}

// Log file to be used for Hive Analysis
function hlog($msg,$logfile,$level = 1)
{
	if($level>VHITS_SYSTEM_LOGLEVEL)
	{
		// No need to log
		return;
	}

	$levelstr = 'CRITICAL';

	switch($level)
	{
		case VHITS_LOGLEVEL_CRITICAL:
			$levelstr = 'CRITICAL';
			break;

		case VHITS_LOGLEVEL_WARNING:
			$levelstr = 'WARNING';
			break;

		case VHITS_LOGLEVEL_INFO:
			$levelstr = 'INFO';
			break;
	}

	$currentuser = exec('whoami');

	if(in_array($currentuser,array ("root" )))
	{
		$logfile = $logfile."_root";
	}
	$logfile = $logfile.'_'.HITS_MACHINE_NAME;
	date_default_timezone_set(DEFAULT_TIME_ZONE);
	$today = date("Y-m-d");
	$filename = VHITS_LOG_LOCATION.$logfile.'_'.$today.'.csv';
	$fd = fopen($filename,"a");
	$timestamp = round(microtime(true));
	fwrite($fd,$timestamp.LLOG_SEPARATOR.$levelstr.LLOG_SEPARATOR.$msg.PHP_EOL);
	fclose($fd);

	$currentuser = exec('whoami');

	error_log("hlog_user===>".$currentuser);

	if(in_array($currentuser,array ("root" )))
	{
		chmod($filename,0777);
	}
}
function findNextActivationDate($validity)
{
	date_default_timezone_set(INDIAN_TIME_ZONE);
	error_log('validation====>'.$validity);
	$today = date('Y-m-d');
	// find how many days validity is remaing.
	$diff = (strtotime($validity)-strtotime($today));
	$days = $diff/(60*60*24);
	hlog_errorlog("diff====>".$days);

	// if validity is less than or equal to 0 then throw error
	if($days<=0)
	{
		return formatError(2076);
	}
	// if validity is more than 7 days then next activation date should be 7 days before expiry
	if($days>7)
	{
		$nextactivation = date('Y-m-d',strtotime($validity.' - 7 days'));
		hlog_errorlog('nextactivation====>'.$nextactivation);
	} // if validity is less than 7 days then next activation date should be next day
	else if($days<=7)
	{
		$nextactivation = date('Y-m-d',strtotime($today.' +1 days'));
		hlog_errorlog('nextactivation====>'.$nextactivation);
	}
	return $nextactivation;
}
function findTwoDatesDiff($date1,$date2)
{
	/*
	 * $diff = abs(strtotime($date1) - strtotime($date2));
	 * $years = floor($diff / (365*60*60*24));
	 * $months = floor(($diff - $years * 365*60*60*24) / (30*60*60*24));
	 * $days = floor(($diff - $years * 365*60*60*24 - $months*30*60*60*24)/ (60*60*24));
	 * error_log("months==>".$months."years==>".$years."days====>".$days);
	 * if($days >= 29)
	 * {
	 * $months = 2;
	 * }
	 * if($years == 1)
	 * {
	 * $months = 12;
	 * }
	 */
	
	 error_log($date1);
	 error_log($date2);
	$date1 = date_create(date('Y-m-d',strtotime($date1)));
	$date2 = date_create(date('Y-m-d',strtotime($date2)));
	error_log(json_encode($date1));
	error_log(json_encode($date2));
	$diff = date_diff($date1,$date2);
	error_log(json_encode($diff));
	$days = $diff->d;
	$months = $diff->m;
	$years = $diff->y;
	$totalmonths = ($years*12)+$months;
	error_log($totalmonths);
	$totaldays = $diff->days;
	return array ("days"=>$days,"months"=>$totalmonths,"years"=>$years,"totalmonths"=>$totalmonths,"totaldays"=>$totaldays );
}
function handle_non_ascii_string_encode($string)
{
	$string = preg_replace('/[^[:print:]]/','',$string);
	$string = str_replace("'","\'",$string);
	$string = str_replace("\\","",$string);
	$string = str_replace("\\\\","",$string);
	return $string;
}
function urlStringToArray($url)
{
	hlog_errorlog("urlStringToArray===>".$url);

	$result = array ();

	$decryptValues = explode('&',$url);
	$dataSize = sizeof($decryptValues);

	for($i = 0;$i<$dataSize;$i++)
	{
		$information = explode('=',$decryptValues[$i]);

		if(!empty($information[1]))
		{
			$information[0] = ltrim($information[0],"?");
			$result[$information[0]] = $information[1];
		}
	}

	hlog_errorlog("urlStringToArray===>".json_encode($result));

	return $result;
}
function getActivationTableName($initiatorid,$postfix = NULL)
{
	// consider only the last 4 digits
	if(!empty($initiatorid))
	{
		$initiatorid = (int) substr($initiatorid,-4);
		$tablenumber = ($initiatorid%VHITS_SHARDING_ACTIVATION_TABLE_NO)+1;
		$tablename = "activation_".$tablenumber;
		hlog_errorlog("INITIATOR ID ==> ".$initiatorid." Table name ==> ".$tablename);
		return $tablename;
	}
	elseif(!empty($postfix))
	{
		$tablename = "activation_".$postfix;
		hlog_errorlog("Table name ==>".$tablename);
		return $tablename;
	}
	else
	{
		hlog_errorlog(("Both initiatorid and postfix cannot be null"));
		return formatError(7007);
	}
}
function getCustomerTableName($lcoid,$postfix = NULL)
{
	// consider only the last 4 digits
	if(!empty($lcoid))
	{
		$lcoid = (int) substr($lcoid,-4);
		$tablenumber = ($lcoid%VHITS_SHARDING_CUSTOMER_TABLE_NO)+1;
		$tablename = "customer_".$tablenumber;
		hlog_errorlog("LCO ID ==> ".$lcoid." Table name ==> ".$tablename);
		return $tablename;
	}
	elseif(!empty($postfix))
	{
		$tablename = "customer_".$postfix;
		hlog_errorlog("Table name ==>".$tablename);
		return $tablename;
	}
	else
	{
		hlog_errorlog(("Both lcoid and postfix cannot be null"));
		return formatError(7007);
	}
}
function getDeviceTableName($lcoid = NULL,$postfix = NULL)
{
	// consider only the last 4 digits
	if(!empty($lcoid))
	{
		$lcoid = (int) substr($lcoid,-4);
		$tablenumber = ($lcoid%VHITS_SHARDING_DEVICE_TABLE_NO)+1;
		return "device_".$tablenumber;
	}
	else if(!empty($postfix))
	{
		return "device_".$postfix;
	}
}
function copyToNas($srcfolder,$filename,$destintionfolder = null)
{
	if(!empty($destintionfolder))
	{
		$destintionfolder = "BACKUP/".$destintionfolder."/";
	}
	else
	{
		$destintionfolder = "BACKUP/";
	}
	$dest = VHITS_NAS_FOLDER.$destintionfolder.$filename;
	$src = $srcfolder.$filename;
	if(!copy($src,$dest))
	{
		error_log('failed to upload file'.$filename.'to nas drive');
		hlog('failed to upload file'.$filename.'to nas drive',VHITS_COPY_FILE_TO_NAS_LOG);
		return formatError(2054);
	}
	else
	{
		return array ("success"=>$filename );
	}
}
function getDpoEntities($roleid,$id = NULL)
{
	$status = session_status();

	if($status==PHP_SESSION_NONE)
	{
		// There is no active session
		session_start();
	}
	hlog_errorlog(json_encode($_SESSION));
	hlog_errorlog($roleid);
	if($_SESSION[VHITS_DPO_ROLEID]!=$roleid)
	{
		return false;
	}

	$entities = $_SESSION[VHITS_DP_ENTITIES];
	error_log("entities====>".$entities);
	$entities = json_decode($entities,true);

	return $entities;
}
function checkBasicAuthorization()
{
	$token = null;
	$headers = apache_request_headers();
	if(isset($headers['Authorization']))
	{
		$token = $headers['Authorization'];
	}

	try
	{
		
		return true;
	}
	catch(Exception $e)
	{
		return false;
	}
}
function blockRenewal($productid,$expiry,$masking = true)
{
	date_default_timezone_set(INDIAN_TIME_ZONE);

	if($masking)
	{
		$productToMaskForRenew = unserialize(VHITS_PRODUCTS_TO_BLOCK_FROM_RENEWAL);

		error_log("Masking product list=============>".json_encode($productToMaskForRenew));
		error_log("Productid==============>".$productid);
		error_log("Expiry=============>".$expiry);
		error_log("Renew Validatity date====>".VHITS_RENEW_VALIDATION_DATE);
		if(in_array($productid,$productToMaskForRenew))
		{
			if($expiry>=VHITS_RENEW_VALIDATION_DATE)
			{
				return formatError(130012);
			}
		}
	}
	else
	{
		if($expiry>VHITS_RENEW_VALIDATION_EXPIRY_DATE)
		{
			return formatError(130013);
		}
	}
}
function setAndGetRedisdata($rediskey,$value = null)
{
	if(!empty($value))
	{
		if($redis = getRedis())
		{
			$redis->set($rediskey,json_encode($value));
			error_log("Redis data set successfully====>:".$rediskey);
			$redis->expire($rediskey,100);
			$redis->close();
			return true;
		}
	}

	if(($redis = getRedis())&&($redis->exists($rediskey)))
	{
		$result = $redis->get($rediskey);
		$result = json_decode($result,true);
		$redis->close();
		return $result;
	}

	return false;
}
function getWsdlToken($entity = 'LCO')
{
	//get Redis connection
	if(!$redis = getRedis())
	{
		return formatError(1002);
	}

	//Get Respected Username and Password
	switch($entity)
	{
		case 'LCO':
			$username = VHITS_HANSEN_SUPER_USER_NAME;
			$password = VHITS_HANSEN_SUPER_USER_PROOF;
			break;
		case 'ADMIN':
			$username = VHITS_HANSEN_ADMIN_SUPER_USER_NAME;
			$password = VHITS_HANSEN_ADMIN_SUPER_USER_PROOF;
			break;
		case 'PREACTIVATION':
			$username = VHITS_HANSEN_ADMIN_SUPER_USER_NAME_FOR_PREACTIVATION;
			$password = VHITS_HANSEN_ADMIN_SUPER_USER_PROOF_FOR_PREACTIVATION;
			break;
		case 'SELFCARE':
		case 'CUSTOMER':
			$username = VHITS_HANSEN_SELFCARE_SUPER_USER_NAME;
			$password = VHITS_HANSEN_SELFCARE_SUPER_USER_PROOF;
			break;
		default:
			$username = VHITS_HANSEN_SUPER_USER_NAME;
			$password = VHITS_HANSEN_SUPER_USER_PROOF;
			break;
	}
	//Redis key
	$rediskey = 'wsdltoken:'.$username;

	//Check For Redis key Existence, if it present then Return the value.
	if($redis->exists($rediskey))
	{
		$token = $redis->get($rediskey);
		$redis->close();
		return $token;
	}

	//If it is not there then create new token
	if(($token = authenticateByProofBySuperUser($username,$password))&&(!empty($token['error'])))
	{
		$redis->close();
		return $token;
	}

	if(empty($token['token']))
	{
		$redis->close();
		return formatError(1015);
	}
	//set Token and return the same.
	$redis->set($rediskey,$token['token']);
	$redis->expire($rediskey,WSLD_TOKEN_EXPIRY_TIME);
	$redis->close();
	return $token['token'];
}
function removeDulicateFromCSV($filename)
{
	$file = fopen($filename,"r");
	// array to hold all "seen" lines
	$lines = array ();

	// loop till end of the file
	while(!feof($file))
	{
		// fetch row
		$thisrow = fgetcsv($file);

		if(is_array($thisrow))
		{	
			if(count(array_filter($thisrow))!=0)
			{
				// build a "line" from the parsed data
				$line = join(",",$thisrow);

				// if the line has been seen, skip it
				if(isset($lines[$line]))
					continue;

				// save the line
				$lines[$line] = true;
			}
	    }
	}
	fclose($file);

	// build the new content-data
	// save it to a new file
	$newfilename = array_reverse(explode('/',$filename));
	$newfilename = $newfilename[0];
	$newfilename = explode('.',$newfilename);
	$newfilename = $newfilename[0];

	$contents = '';
	foreach($lines as $line => $bool)
		$contents .= $line."\r\n";

	unset($lines);
	unlink($filename);

	file_put_contents("/var/local/tvbuddy/temp/".$newfilename."unique.txt",$contents);
	$newfilename = $newfilename.'unique';
	error_log($newfilename);
	return $newfilename;
}
function checkEligibleForLdr($initiatorid,$data,$initiatortype,$jobid)
{
	if(($customersubscribedproducts = listCustomerSubscribedProduct($initiatorid,$data,$initiatortype,$jobid))&&(!empty($customersubscribedproducts['error'])))
	{
		return $customersubscribedproducts;
	}
	else
	{
		error_log("customer subscribed products===========".json_encode($customersubscribedproducts));

		$today = strtotime(date('Y-m-d'));

		foreach($customersubscribedproducts as $basepackdetails)
		{
			if(($basepackdetails['producttypename']=='Base Packs')&&($basepackdetails['tariffperiod']!='Monthly')&&($basepackdetails['productstatus']=='ACTIVE'))
			{
				error_log($basepackdetails['expiry']);

				$expirydate = strtotime(date($basepackdetails['expiry']));
				$validity = ($expirydate-$today)/(60*60*24);
				error_log("validity days============".$validity);

				if($validity>30)
				{
					//return array('error'=>"Not Eligible for LDR");
					return array ('success'=>'Eligible for LDR' );
				}
				else
				{
					return array ('error'=>"Not Eligible for LDR" );
				}
			}
		}
	}
}
function deleteOldWsdlTokens()
{
	if(!$redis = getRedis())
	{
		return formatError(1002);
	}
	//$keys = $redis->keys('wsdl*');
	$keys = array ('wsdltoken:GILMOB_API','wsdltoken:GILMOB_ADM_API','wsdltoken:Selfcare_user' );
	foreach($keys as $key)
	{
		$redis->del($key);
	}
	$redis->close();
	return array ('success'=>true );
}

function userValidation()
{
	
	if(!$lcoid = sessionValidate(VHITS_LCO_ROLEID))
	{
		HTTPFailWithCode(401,'No valid session exist');
	}
	
	if($_SESSION[VHITS_USER_TYPE]=='LCO')
	{
		$username = $_SESSION[VHITS_USER_NAME];
		error_log("lcoid===>".json_encode($lcoid));
	}
	else if($_SESSION[VHITS_USER_TYPE]=='SUBUSER')
	{
		$username = $lcoid;
		$lcoid = $_SESSION[VHITS_LCO_ID];
		$permission = explode(':',$_SESSION[VHITS_USER_PERMISSION]);
		error_log("permission===>".json_encode($permission));
		if(!in_array('product',$permission))
		{
			HTTPFailWithCode(402,'No permission to perform this operation');
		}
	}
	else
	{
		$username = $lcoid;
		$lcoid = $_SESSION[VHITS_LCO_ID];
		error_log("master user spoted===>".$username);
	}
	
	return array ("lcoid"=>$lcoid,"username"=>$username);
}

function getRecordsCount($query,$mysqlcon = null)
{
	error_log($query);
	$close = false;
	if(!$mysqlcon)
	{
		if(!$mysqlcon = getHITSDBConnection())
		{
			return false;
		}
		$close = true;
	}
	
	if(!($result = $mysqlcon->query($query)))
	{
		error_log("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		return false;
	}
	
	if($result->num_rows==0)
	{
		error_log("No Payment");
		return false;
	}
	
	$val = $result->fetch_assoc();
	$result->close();
	
	if($close)
	{
		$mysqlcon->close();
	}
	
	return $val['count'];
}

function uploadImage($filename,$type = 'promo',$folder=null)
{
	if(!file_exists(VHITS_LOCAL_TEMP_FOLDER.$filename))
	{
		error_log("File Not Available===>".$filename,2);
		return formatError(2007);
	}
	
	$fileextention = explode('.',$filename);
	$fileextention = $fileextention[count($fileextention)-1];
	$fileextention = strtolower($fileextention);
	// error_log("Extension===>".$fileextention,3);
	
	if(($fileextention!="png")&&($fileextention!="jpg")&&($fileextention!="jpeg") && ($fileextention!="pdf"))
	{
		return formatError(16002);
	}
	$filepath = TVBUDDY_S3_PROMO_PATH.'/'.$filename;
	if($type=='alert')
	{
		$filepath = TVBUDDY_S3_ALERT_PATH.'/'.$filename;
	}
	
	if($type == 'custom')
	{
		$filepath = $folder.'/'.$filename;
	}
	
	if($type == 'profile')
	{
		$filepath = TVBUDDY_S3_PROFILE_PATH.$filename;
	}
	
	$source = VHITS_LOCAL_TEMP_FOLDER.$filename;
	$bucket = TVBUDDY_KYC_BUCKET;
	if(!addFiletoS3WithPublic($filepath,$bucket,$source))
	{
		// if failed to add the skip this and continue for next
		error_log('failed to upload'.$filename);
		return formatError("Failed to add Image to S3");
	}
	return array ("success"=>$filename );
}

function amountFormat($amount,$scale=2)
{
    $amount = number_format((round(($amount),2)),$scale,'.','');

    return $amount;
}

function getLcnAndGenerReport($filetype)
{
	switch($filetype)
	{
		case 'Region':
			$file = 'file';
			break;
		case 'Redtv':
			$file = 'file';
			break;
		default:
			$file = 'hfile';
			break;
	}
	return $file;
}

/**
 * @param null $error
 *
 * @throws Object
 */
function commonError($data = [])
{
	$httpStatus = 200;
	if (isset($data['httpcode']) && !empty($data['httpcode'])) {
		$httpStatus = $data['httpcode'];
	}
    if (!isset($data['errorcode']) || empty($data['errorcode'])) {
		$data['errorcode'] = 402;
		$httpStatus = 402;
    }
    /*if($redis->exists($key))
    {
        $result = json_decode(($redis->get($key)),true);
        $redis->close();
    }
    else
    {
        $result = formatError("Failed to get Data");
    }*/
	// $result = formatError("Failed to get Data");
    echo json_encode($data);
	exit;
}

/**
 * @param array $request
 * @param $params
 *
 * @return object $response
 */
function checkRequiredParam(array $request, $params)
{
    $params = (array)$params;
    
    foreach ($params as $param) {
        if (!array_key_exists($param, $request)) {
            $response = [
                'errorcode' => 2000,
                'error' => 'Missing Parameters'
            ];
            commonError($response);
            exit;
        }
        else
        { 
        	if(empty($request[$param]))
        	{
        		$response = [
                'errorcode' => 2000,
                'error' => 'Missing Parameters'
	            ];
	            commonError($response);
	            exit;
        	}	
        }	
    }
    return TRUE;
}

/**
 *@param array $response
 *
 * @return object $response
 */
function asJson($response)
{
	error_log(json_encode($response));
    echo json_encode($response); exit;
}

/**
 *@param string $customerId, $mobileNo
 *
 * @return array $response
 */
function sendMereApneOtp($customerId, $mobileNo)
{
	$response = [];
	$redis = getRedis();

    if (!$redis) {
        $response['errorcode'] = 1002;
		$response['error'] = 'Failed to connect to redis, please try again';
		goto skip;
    }

	$otp = createRandomString(6, CHAR_TYPE_NUMERIC);
	hlog("mereapne otp>>>>>>>>>>>".json_encode($otp),VHITS_MERE_APNE_CUSTOMER_LOGS);
    error_log("-----------MereApne OTP Log Start-----------\n\n");
    error_log('----------------'.$otp.'------------------');
    error_log("\n\n");

	if ($redis->exists('MAotplogin:' . $customerId)) {
		$redis->del('MAotplogin:' . $customerId);
	}
    $redis->set('MAotplogin:' . $customerId, $otp);
    $redis->expire('MAotplogin:' . $customerId, 300);
    
    $string = ['{otp}'];
	$replace = [$otp];    
	//$message = str_replace($string, $replace, VHITS_SMS_MA_CUSTOMER_OTP_MESSAGE);
	$message = str_replace($string, $replace, VHITS_SMS_CUSTOMER_OTP_MESSAGE);
	$smsData = sendSMS($mobileNo, $message);
	hlog("send sms success/failed>>>>>>>>>>>".$smsData,VHITS_MERE_APNE_CUSTOMER_LOGS);
	if (!$smsData) {
		$response = [
			'error' => 'Could not send SMS',
			'errorcode' => 1006
		];
		goto skip;
	}

	$response = ['status' => 200];

	skip:
	return $response;
}

/**
 *@param string $customerId, $otp
 *
 * @return array $response
 */
function verifyMereApneOtp($customerId, $otp)
{
	$response = [];
	$redis = getRedis();

    if (!$redis) {
        $response['errorcode'] = 1002;
		$response['error'] = 'Failed to connect to redis, please try again';
		goto skip;
	}

	if (!$redis->exists('MAotplogin:' . $customerId)) {
		$response['errorcode'] = 40056;
		$response['error'] = 'OTP Expired/Customer has already logged in';
		goto skip;
	}
	
	$existOtp = $redis->get('MAotplogin:' . $customerId);

	if ($existOtp !== $otp) {
		$response['errorcode'] = 40055;
		$response['error'] = 'Invalid OTP';
		goto skip;
	}
	else
	{
	   $redis->del('MAotplogin:' . $customerId);
	}	

	$response['status'] = 200;
	
	skip:
	return $response;
}

function createImageUrl($image)
{
    return sprintf('%s%s', S3_IMAGE_URL, $image);
}

function moneyFormat($value, $scale = 2)
{
	$value = round($value, $scale);
	return $value;
}

function numberTowords(float $amount)
{
   $amount_after_decimal = round($amount - ($num = floor($amount)), 2) * 100;
   // Check if there is any number after decimal
   $amt_hundred = null;
   $count_length = strlen($num);
   $x = 0;
   $string = array();
   $change_words = array(0 => '', 1 => 'One', 2 => 'Two',
     3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
     7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
     10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
     13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
     16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
     19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
     40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
     70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety');
  $here_digits = array('', 'Hundred','Thousand','Lakh', 'Crore');
  while( $x < $count_length ) {
       $get_divider = ($x == 2) ? 10 : 100;
       $amount = floor($num % $get_divider);
       $num = floor($num / $get_divider);
       $x += $get_divider == 10 ? 1 : 2;
       if ($amount) {
         $add_plural = (($counter = count($string)) && $amount > 9) ? 's' : null;
         $amt_hundred = ($counter == 1 && $string[0]) ? ' and ' : null;
         $string [] = ($amount < 21) ? $change_words[$amount].' '. $here_digits[$counter]. $add_plural.' 
         '.$amt_hundred:$change_words[floor($amount / 10) * 10].' '.$change_words[$amount % 10]. ' 
         '.$here_digits[$counter].$add_plural.' '.$amt_hundred;
         }else $string[] = null;
       }
   $implode_to_Rupees = implode('', array_reverse($string));
   $get_paise = ($amount_after_decimal > 0) ? "And " . ($change_words[$amount_after_decimal / 10] . " 
   " . $change_words[$amount_after_decimal % 10]) . ' Paise' : '';
   return ($implode_to_Rupees ? $implode_to_Rupees . 'Rupees ' : '') . $get_paise;
}

function sendActivationStatusMereApne($customerid,$productstatus,$initiatortype,$initiatorid,$productname)
{

      if($initiatortype == 'CUSTOMER')
      {
	      	if (($customerdetails = getCustomerDetail(VHITS_HANSEN_SELFCARE_SUPER_USER_NAME, $customerid)) && (isset($customerdetails['error'])))
		    {
			    error_log($customerdetails['error']);
			    return $customerdetails;
					           
	        }
			else
		    {
			    $emailid = $customerdetails['email'];
			    $name = $customerdetails['firstname'];
		    }
      }	
      else if ($initiatortype == 'LCO')
      {
      	 if(($lcodetails = getLcoDetailsByFieldName('idlco',$initiatorid)) && (!empty($lcodetails['error'])))
      	 {
      	 	return $lcodetails;
      	 }	

      	 $emailid = $lcodetails['email'];
      	 $name = $lcodetails['firstname'];
      }	


      if($productstatus == 'ACTIVE')
      {
      	 $message = "Product has been been activated successfully";
      }
      else if($productstatus == 'CANCELLED' || $productstatus == 'FAILED')
      {
         $message = "Failed to Activate Product";
      }	
    
      
	  $subject = "Activation status";
	  $datatxt = 'mereapneactivationstatus';
	  $search = array ("{customerid}","{productname}","{productstatus}","{message}","{initiatorname}");
	  $replace = array ($customerid,$productname,$productstatus,$message,$name);


     if(!empty($emailid))
     {
     	 $addressList = array ($emailid);
         if(prepareAndSendMail($addressList,$subject,$datatxt,$search,$replace))
		 {
	          error_log("email sent successfully");      

	          return array("success"=>"Email sent successfully");        
		 }
		 else
		 {
	         error_log("failed to send email");
	         return array("error"=>"Failed to send Email");
		 }	

	 }
	 else
	 {
	 	return array("error"=>"Failed to get mail id");
	 }	

				
}
//release changes
function customLog($msg, $level = LOG_LEVEL_INFO, $fileName = null) 
{
	error_log($msg);
	return true;
}
function checkRequiredField($params) 
{	
	$response = true;
	foreach ($params as $key => $fieled) {
		if ($params[$key] === "") { 
			$response = false;
			break;
		}
	}
	return $response;
}
?>

