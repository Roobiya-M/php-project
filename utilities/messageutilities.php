<?php
/**
 * *******************************************************
 * Author: Safiqul Alam
 * Copyright: Mobiotics IT Solution Private Limited
 * Version: 1.0
 * Date: 27-Jan-2016
 * FileName: messageutilities.php
 * Description: Utilities for alert messages
 * ********************************************************
 */
error_reporting(E_ALL);
function getAlertMessage($page,$entitytype = null,$entityid = null,$pagelimit=VHITS_DB_PAGE_LIMIT,$data)
{
	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	$query = sprintf("SELECT * FROM alert");
	$query_count = sprintf("SELECT count(idalert) as count FROM alert");
	if((!empty($entitytype))&&(!empty($entityid)))
	{
		if($entitytype!="CUSTOMER")
		{
			$prob = getDBConnectorPhrase($query) .sprintf("alerttype in ('BUSINESS','GENERAL')");
			$query .= $prob;
			$query_count .= $prob;
		}
		else
		{
			if(($token = getTokenByInitiatorid('custtoken'))&&(!empty($token['error'])))
			{
				return $token;
			}
			if(($customerdetils = Get_Accounts($entityid,$token))&&(!empty($customerdetils['error'])))
			{
				return $customerdetils;
			}
			else
			{
				$lcoid = $customerdetils['lcoid'];
				$msoid = $customerdetils['msoid'];
				$prob = getDBConnectorPhrase($query).sprintf(" entityid IN ('%s','%s') OR entityid is NULL AND alerttype IN ('GENERAL','CUSTOMER')",mysql_fix_string($lcoid,$mysqlcon),mysql_fix_string($msoid,$mysqlcon));
				$query .= $prob;
				$query_count .= $prob;
			}
		}
	}

	if(!empty($data['alerttype']))
	{
		 $prob = getDBConnectorPhrase($query).sprintf("messagecategory = '%s'",mysql_fix_string($data['alerttype'],$mysqlcon));
		 $query .= $prob;
		 $query_count .= $prob;
	}
	
	$prob = " ORDER BY weight ASC,created DESC";
	$query .= $prob;
	$query_count .= $prob;
	
	$count = 0;
	$limit = $pagelimit;
	
	if($datacount = getRecordsCount($query_count,$mysqlcon))
	{
		$count = $datacount;
	}

	$query .= sprintf(" LIMIT %u , %u",($page-1)*$limit,$limit);

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
	$finalresult = array();
	$bucket = TVBUDDY_KYC_BUCKET;
	$folderpath = TVBUDDY_S3_ALERT_PATH;
	
	while($row = $result->fetch_assoc())
	{

		if(($row['entitytype']=='LCO')||($row['entitytype']=='DPO'))
		{
			if(($lcodetails = getLcoDetailsByFieldName('idlco',$row['entityid']))&&(!empty($lcodetails['error'])))
			{
				return $lcodetails;
			}
			else
			{
				$row['entityid'] = $lcodetails['username'];
			}
		}
		if($row['entitytype']=='MSO')
		{
			if(($msodetails = getMSODetailsByFieldName('idmso',$row['entityid']))&&(!empty($msodetails['error'])))
			{
				return $msodetails;
			}
			else
			{
				$row['entityid'] = $msodetails['username'];
			}
		}
		
		$iconurl = VHITS_BASE_URL.'assets/ALERTICON/';
		switch($row['messagetype'])
		{
			case 'MAINTENANCE':
				$iconurl = $iconurl.'Maintenance-NXTDIGITAL-icon(96x96).png';
				break;
			case 'NEW_FEATURE':
				$iconurl = $iconurl.'New-Features-NXTDigital-icon(96x96).png';
				break;
			case 'NEW_OFFER':
				$iconurl = $iconurl.'New-Offer-NXTDIGITAL-icon(96x96).png';
				break;
			case 'NXTUDAAN':
				
				$iconurl = $iconurl.'NXTDIGITALNXTUDAANicon.png';
				
				if(!empty($row['iconimage']))
				{
					$iconurl = $iconurl.$row['iconimage'];
				}
				break;
			case 'SUPER_ACHIEVERS_LCO':
				$iconurl = $iconurl.'Super-Achievers-logo.png';
				break;
		}
		
		$row['url'] = $iconurl;
		
		if(!empty($entityid))
		{
			$badge = getAlertBadge($entityid,$row['idalert']);
			$row['clicked'] = 'NO';
			if(empty($badge['error']))
			{
				$row['clicked'] = $badge['clicked'];
			}
		}
		
		
		if(!empty($row['image']))
		{
			$url = "url/".$bucket.'/'.$folderpath.'/'.$row['image'];
			$row['url'] = $url;
		}
		
		$row['alerttype'] = $row['messagecategory'];
		
		$val[] = $row;
		
	}
	$finalresult['count'] = $count;
	$finalresult['data'] = $val;
	$result->close();
	$mysqlcon->close();

	return $finalresult;
}
function deleteAlertMessage($id)
{
	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}

	$query = sprintf("DELETE FROM alert WHERE idalert='%s'",mysql_fix_string($id,$mysqlcon));

	error_log($query);

	if(!($stmt = $mysqlcon->prepare($query)))
	{
		error_log("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		return formatError(1001);
	}

	if(!($stmt->execute()))
	{
		error_log("SQL Query Failed: (".$stmt->errno.") ".$stmt->error);
		return formatError(1001);
	}

	if($stmt->affected_rows==0)
	{
		error_log("No data to delete");
		return formatError(1022);
	}
	else
	{
		$mysqlcon->close();
		return array ('success'=>$id );
	}
}
function addAlertMessage($data)
{
	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}
	error_log(json_encode($data));
	if(!empty($data['image']))
	{
		if(($uploadimage = uploadImage($data['image'],'alert'))&&(!empty($uploadimage['error'])))
		{
			return $uploadimage;
		}
	}
	
	date_default_timezone_set(INDIAN_TIME_ZONE);
	$data['created'] = 'NOW()';
	$newdata = array ();
	$newdata['weight'] = $data['priority'];
	$newdata['alerttype'] = $data['alerttype'];
	$newdata['messagecategory'] = 'ALERT';
	if(!empty($data['messagetype']))
	{
		$newdata['messagetype'] = $data['messagetype'];
	}
	if(!empty($data['image']))
	{
		$newdata['image'] = $data['image'];
	}
	if(!empty($data['messagecategory']))
	{
		$newdata['messagecategory'] = $data['messagecategory'];
	}
	if($data['alerttype']=="CUSTOMER")
	{
		if(($data['entitytype']=="LCO")||($data['entitytype']=="DPO"))
		{
			$newdata['entitytype'] = $data['entitytype'];
			if(($lcodetails = getLcoDetailsByFieldName("username",$data['lcousername']))&&(!empty($lcodetails['error'])))
			{
				return $lcodetails;
			}
			else
			{
				$newdata['entityid'] = $lcodetails['lcoid'];
			}
		}
		if($data['entitytype']=="MSO")
		{
			$newdata['entitytype'] = $data['entitytype'];
			if(($msodetails = getMSODetailsByFieldName("username",$data['msousername']))&&(!empty($msodetails['error'])))
			{
				return $msodetails;
			}
			else
			{
				$newdata['entityid'] = $msodetails['msoid'];
			}
		}
	}
	$keys = array ();
	$values = array ();

	foreach($newdata as $key => $value)
	{
		$key = strip_tags($key);
		$keys[] = mysql_fix_string($key,$mysqlcon);
		$value = strip_tags($value);
		$values[] = mysql_fix_string($value,$mysqlcon);
	}

	$query = sprintf("INSERT INTO alert (".implode(",",$keys).",created,message) VALUES ('".implode("','",$values)."',NOW(),'%s')",mysql_fix_string($data['description'],$mysqlcon));

	error_log($query);

	unset($keys);
	unset($values);

	if(!($stmt = $mysqlcon->prepare($query)))
	{
		error_log("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		return formatError(1001);
	}

	if(!($stmt->execute()))
	{
		error_log("SQL Query Failed: (".$stmt->errno.") ".$stmt->error);
		return formatError(1001);
	}

	if($stmt->affected_rows==0)
	{
		error_log("No message update");
		return formatError(1022);
	}

	$messageid = $mysqlcon->insert_id;
	$stmt->close();

	return array ("success"=>$messageid );
}
function addScroll($message)
{
	$requestdata['message'] = $message;
	$result = processCurlHitsWebsite("POST",VHITS_HITS_WEBSITE_SCROLL_API,$requestdata);

	hlog_errorlog("scroll api Response===>".json_encode($result));

	if(!empty($result['error']))
	{
		return $result;
	}

	return $result;
}

function getAlertBadge($lcoid,$alertid = null,$alertidsarray = null)
{
	if(!$mysqlcon = getHITSDBConnection())
	{
		return formatError(10001);
	}
	
	if($alertidsarray)
	{
		$query = sprintf("SELECT * FROM alert_badge where lcoid = '%s' and alertid in (%s) ",mysql_fix_string($lcoid,$mysqlcon),mysql_fix_string(implode(',',$alertidsarray),$mysqlcon));
	}
	else
	{
		$query = sprintf("SELECT * FROM alert_badge where lcoid = '%s' and alertid = '%s' ",mysql_fix_string($lcoid,$mysqlcon),mysql_fix_string($alertid,$mysqlcon));
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
	return $row;
}
function addAlertBadge($lcoid,$alertid)
{
	$mysqlcon = getHITSDBConnection();
	
	$query = sprintf("INSERT INTO alert_badge (lcoid,alertid,clicked,created) VALUES ('%s','%s','YES',now())",mysql_fix_string($lcoid,$mysqlcon),mysql_fix_string($alertid,$mysqlcon));
	
	error_log($query);
	
	if(!($stmt = $mysqlcon->prepare($query)))
	{
		error_log("SQL Query Failed: (".$mysqlcon->errno.") ".$mysqlcon->error);
		return formatError(1001);
	}
	
	if(!($stmt->execute()))
	{
		error_log("SQL Query Failed: (".$stmt->errno.") ".$stmt->error);
		return formatError(1001);
	}
	
	if($stmt->affected_rows==0)
	{
		error_log("No message update");
		return formatError(1022);
	}
	
	$stmt->close();
	
	return array ('success'=>true );
}

?>
