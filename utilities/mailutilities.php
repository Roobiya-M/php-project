<?php
/**
 * *******************************************************
 * Author: Manish Giri
 * Copyright: Mobiotics IT Solution Private Limited
 * Version: 1.0
 * Date: 28-Jul-2015
 * FileName: mailutilities.php
 * Description: Functions to manage Email
 * ********************************************************
 */
error_reporting(E_ALL);
require_once ("vendor/autoload.php");

function sendEmailToUser($fromaddress,$toaddress,$ccaddress,$bccaddress,$subject,$datatxt,$datahtml = NULL,$filename = NULL)
{
	date_default_timezone_set(LOCAL_TIME_ZONE);
	$sendarray = array ();
	if(!empty($toaddress))
	{
		if(!is_array($toaddress))
		{
			$toaddress = array ($toaddress );
			$sendarray['ToAddresses'] = $toaddress;
		}
		else
		{
			$sendarray['ToAddresses'] = $toaddress;
		}
	}

	if(!empty($ccaddress))
	{
		if(!is_array($ccaddress))
		{
			$ccaddress = array ($ccaddress );
			$sendarray['CcAddresses'] = $ccaddress;
		}
		else
		{
			$sendarray['CcAddresses'] = $ccaddress;
		}
	}

	if(!empty($bccaddress))
	{
		if(!is_array($bccaddress))
		{
			$bccaddress = array ($bccaddress );
			$sendarray['BccAddresses'] = $bccaddress;
		}
		else
		{
			$sendarray['BccAddresses'] = $bccaddress;
		}
	}
	$destination = $sendarray;

	hlog_errorlog("destination====>".json_encode($destination));
	/*
	 * $client = SesClient::factory(array(
	 
	 
	 * ));
	 * $result = $client->sendEmail(array(
	 * // Source is required
	 * 'Source' => $fromaddress,
	 * // Destination is required
	 * 'Destination' => $destination,
	 * // Message is required
	 * 'Message' => array(
	 * // Subject is required
	 * 'Subject' => array(
	 * // Data is required
	 * 'Data' => $subject,
	 * 'Charset' => 'UTF-8',
	 * ),
	 *
	 * // Body is required
	 * 'Body' => array(
	 * 'Text' => array(
	 * // Data is required
	 * 'Data' => $datatxt,
	 * 'Charset' => 'UTF-8',
	 * ),
	 * 'Html' => array(
	 * // Data is required
	 * 'Data' => $datahtml,
	 * 'Charset' => 'UTF-8',
	 * ),
	 * ),
	 * )
	 * ));
	 *
	 * error_log(json_encode($result));
	 *
	 * return true;
	 */

	// using normal send mail funtion insted of aws.
	try
	{
		$result = sendSMTPmail($fromaddress,$destination,$subject,$datatxt,$datahtml,$filename);

		hlog_errorlog("sending true====>".json_encode($result));

		return true;
	}
	catch(Exception $e)
	{
		hlog_errorlog("failed to send mail reason:".json_encode($e->getMessage()));
		return false;
	}
}
function sendRawEmail($email,$subject,$template,$search,$replace,$filelocation,$filename = "test",$contenttype = "pdf")
{
	$mimetype = getMimeTypeForExtension($contenttype);
	$filename = $filename.".".$contenttype;

	error_log("Content Type===>".$contenttype);
	error_log("Mime Type===>".$mimetype);
	error_log("File Name===>".$filename);
	error_log("File Location===>".$filelocation);

	$createdhtml = file_get_contents(VHITS_EMAIL_TEMPLATE_FOLDER.$template.'.html');
	$createdtxt = file_get_contents(VHITS_EMAIL_TEMPLATE_FOLDER.$template.'.txt');

	$createdhtml = str_replace($search,$replace,$createdhtml);
	$createdtxt = str_replace($search,$replace,$createdtxt);

	//First load the template
	$basetxt = file_get_contents(VHITS_EMAIL_TEMPLATE_FOLDER.VHITS_BASE_MAIL_TEMPLATE_FILE.'.txt');
	$basehtml = file_get_contents(VHITS_EMAIL_TEMPLATE_FOLDER.VHITS_BASE_MAIL_TEMPLATE_FILE.'.html');

	$basetxt = str_replace("{livetv_website}",VHITS_WEBSITE,$basetxt);
	$basehtml = str_replace("{livetv_website}",VHITS_WEBSITE,$basehtml);

	if(!$createdtxt)
	{
		error_log('Could not load template file');
		return false;
	}

	$search = array ('{mail_content}','{mail_action}' );
	$replace = array ($createdhtml,'' );
	$createdhtml = str_replace($search,$replace,$basehtml);

	$replace = array ($createdtxt,'' );
	$createdtxt = str_replace($search,$replace,$basetxt);

	error_log($email);

	$dest = $email;
	$src = VHITS_SYSTEM_MAIL_SENDER;

	$boundary = sha1(rand().time().'imcl');

	$message = "To: ".$dest."\n";
	$message .= "From: ".$src."\n";
	$message .= "Subject: ".$subject." \n";
	$message .= "MIME-Version: 1.0\n";
	$message .= 'Content-Type: multipart/mixed; boundary="'.$boundary.'"';
	$message .= "\n\n";
	$message .= "--".$boundary."\n";
	$message .= 'Content-Type: text/html; charset="utf-8"';
	$message .= "\n";
	$message .= "Content-Transfer-Encoding: 7bit\n";
	$message .= "Content-Disposition: inline\n";
	$message .= "\n";
	$message .= $createdhtml;
	$message .= "\n\n";
	$message .= "--".$boundary."\n";
	$message .= "Content-ID: \<".$boundary."_@lco.com\>\n";
	$message .= 'Content-Type: '.$mimetype.'; name="'.$filename.';';
	$message .= "\n";
	$message .= "Content-Transfer-Encoding: base64\n";
	$message .= 'Content-Disposition: attachment; filename="'.$filename.'"';
	$message .= "\n";
	$message .= base64_encode(file_get_contents($filelocation));
	$message .= "\n";
	$message .= "--".$boundary."--\n";

	//error_log("Email Message===>".$message);

	$client = SesClient::factory(array ('key'=>'key','d'=>'V/key','region'=>'us-east-1' ));

	try
	{
		$result = $client->sendRawEmail(array ('Source'=>$src,'Destinations'=>array ($dest ),'RawMessage'=>array ('Data'=>base64_encode($message) ) ));

		error_log("sendRawEmail===".$result['MessageId']);

		return true;
	}
	catch(Exception $e)
	{
		error_log("SOME ERROR OCCURED");
		error_log("Error: ".$e->getMessage());
		error_log("File: ".$e->getFile());
		error_log("Line: ".$e->getLine());
		return false;
	}
}
function prepareAndSendMail($addressList,$subject,$template,$search,$replace,$fileperm = false,$filename = null)
{
	$createdhtml = file_get_contents(VHITS_EMAIL_TEMPLATE_FOLDER.$template.'.html');
	$createdtxt = file_get_contents(VHITS_EMAIL_TEMPLATE_FOLDER.$template.'.txt');

	$createdhtml = str_replace($search,$replace,$createdhtml);
	$createdtxt = str_replace($search,$replace,$createdtxt);

	//First load the template

	$basetxt = file_get_contents(VHITS_EMAIL_TEMPLATE_FOLDER.VHITS_BASE_MAIL_TEMPLATE_FILE.'.txt');
	$basehtml = file_get_contents(VHITS_EMAIL_TEMPLATE_FOLDER.VHITS_BASE_MAIL_TEMPLATE_FILE.'.html');

	$basetxt = str_replace("{livetv_website}",VHITS_WEBSITE,$basetxt);
	$basehtml = str_replace("{livetv_website}",VHITS_WEBSITE,$basehtml);

	if(!$createdtxt)
	{
		hlog_errorlog('Could not load template file');
		return false;
	}

	$bcc = NULL;
	$cc = NULL;
	if(count($addressList)>1)
	{
		$bcc = $addressList;
		$addressList = array_splice($addressList,0,1);
		array_splice($bcc,0,1);
	}

	$search = array ('{mail_content}','{mail_action}' );
	$replace = array ($createdhtml,'' );
	$createdhtml = str_replace($search,$replace,$basehtml);

	$replace = array ($createdtxt,'' );
	$createdtxt = str_replace($search,$replace,$basetxt);

	error_log("Email Content Text===".$createdtxt);
	//error_log("Email Content Html===".$createdhtml);

	if(!sendEmailToUser(VHITS_SYSTEM_MAIL_SENDER,$addressList,$cc,$bcc,$subject,$createdtxt,$createdhtml,$filename))
	{
		return false;
	}

	if($fileperm)
	{
		hlog(json_encode($addressList).LLOG_SEPARATOR.count($addressList),VHITS_MAIL_LOG,VHITS_LOGLEVEL_CRITICAL);
	}
	else
	{
		hlog(json_encode($addressList).LLOG_SEPARATOR.count($addressList),VHITS_MAIL_LOG,VHITS_LOGLEVEL_CRITICAL);
	}

	return true;
}
function populateMailInlineValues($subscriptionplan)
{
	if(empty($subscriptionplan))
	{
		return false;
	}
	date_default_timezone_set(INDIAN_TIME_ZONE);
	$date = new DateTime("now");
	$date = $date->format("Y-m-d");

	$table_row = "<tr><td style=\"padding:8px;line-height:30px;width:16.666%;text-align:center;border:1px solid #cccccc\">{planname}</td><td style=\"padding:8px;line-height:30px;width:16.666%;text-align:center;border:1px solid #cccccc\">{plantype}</td><td style=\"padding:8px;line-height:30px;width:16.666%;text-align:center;border:1px solid #cccccc\">{expiry}</td><td style=\"padding:8px;line-height:30px;width:16.666%;text-align:center;border:1px solid #cccccc\">{amount}</td><td style=\"padding:8px;line-height:30px;width:16.666%;text-align:center;border:1px solid #cccccc\">{discount}</td><td style=\"padding:8px;line-height:30px;width:16.666%;text-align:center;border:1px solid #cccccc\">{paid}</td></tr>";
	$table_description = "";
	foreach($subscriptionplan as $value)
	{

		$plantype = getPlanField($value['planid'],'plantype');
		$planname = getPlanField($value['planid'],'planname');
		if(!($value['trialperioddays']>0))
		{
			$planinterval = getPlanField($value['planid'],'planinterval');
			$expiry = date('Y-m-d',strtotime($date."+".getIntervalInDays($planinterval)." days"));
		}
		else
		{
			$expiry = date('Y-m-d',strtotime($date."+".$value['trialperioddays']." days"));
		}

		$search = array ("{planname}","{plantype}","{status}","{amount}","{discount}","{paid}","{expiry}" );
		$replace = array ($planname,$plantype,$value['planstatus'],($value['amount']/100),($value['discount']/100),(($value['amount']-$value['discount'])/100),$expiry );
		$table_description .= str_replace($search,$replace,$table_row);
	}

	return $table_description;
}
function populateInvoiceMail($subscriptionplan)
{
	if(empty($subscriptionplan))
	{
		return false;
	}
	date_default_timezone_set(INDIAN_TIME_ZONE);
	$date = new DateTime("now");
	$date = $date->format("Y-m-d");

	$table_row = "<tr><td style=\"padding:8px;line-height:30px;width:16.666%;text-align:center;border:1px solid #cccccc\">{planname}</td><td style=\"padding:8px;line-height:30px;width:16.666%;text-align:center;border:1px solid #cccccc\">{plantype}</td><td style=\"padding:8px;line-height:30px;width:16.666%;text-align:center;border:1px solid #cccccc\">{billfrom}</td><td style=\"padding:8px;line-height:30px;width:16.666%;text-align:center;border:1px solid #cccccc\">{billto}</td><td style=\"padding:8px;line-height:30px;width:16.666%;text-align:center;border:1px solid #cccccc\">{amount}</td></tr>";
	$table_description = "";
	foreach($subscriptionplan as $value)
	{

		$plantype = getPlanField($value['planid'],'plantype');
		$planname = getPlanField($value['planid'],'planname');
		if(!($value['trialperioddays']>0))
		{
			$planinterval = getPlanField($value['planid'],'planinterval');
			$expiry = date('Y-m-d',strtotime($date."+".getIntervalInDays($planinterval)." days"));
		}
		else
		{
			$expiry = date('Y-m-d',strtotime($date."+".$value['trialperioddays']." days"));
		}

		$search = array ("{planname}","{plantype}","{billfrom}","{billto}","{amount}","{total}" );
		$replace = array ($planname,$plantype,$value['billfrom'],$value['billto'],round($value['amount']/100,2) );
		$table_description .= str_replace($search,$replace,$table_row);
	}

	return $table_description;
}
function prepareAndSend($addresslist,$subject,$content,$fileperm = false)
{
	//First load the template
	$createdtxt = file_get_contents(VHITS_EMAIL_TEMPLATE_FOLDER.VHITS_BASE_MAIL_TEMPLATE_FILE.'.txt');
	$createdhtml = file_get_contents(VHITS_EMAIL_TEMPLATE_FOLDER.VHITS_BASE_MAIL_TEMPLATE_FILE.'.html');

	$basetxt = str_replace("{livetv_website}",VHITS_WEBSITE,$createdtxt);
	$basehtml = str_replace("{livetv_website}",VHITS_WEBSITE,$createdtxt);

	if(!$createdtxt)
	{
		error_log('Could not load template file');
		return false;
	}

	$search = array ('{mail_content}','{mail_action}' );
	$replace = array ("<p style='font-size:17px'>".$content."</p>",'' );

	$createdhtml = str_replace($search,$replace,$createdhtml);
	$createdtxt = str_replace($search,$replace,$createdtxt);
	$bcc = NULL;
	$cc = NULL;

	if(count($addresslist)>1)
	{
		$bcc = $addresslist;
		$addresslist = NULL;
		//$addresslist = array_splice($addresslist,0,1);
		//array_splice($bcc,0,1);
	}

	//error_log(json_encode($bcc));
	//error_log(json_encode($addresslist));

	if(!sendEmailToUser(VHITS_SYSTEM_MAIL_SENDER,$addresslist,$cc,$bcc,$subject,$createdtxt,$createdhtml))
	{
		error_log('PrepareAndSend: Could not send bulk email');
		return false;
	}

	if($fileperm)
	{
		hlog(json_encode($addresslist).LLOG_SEPARATOR.count($addresslist),VHITS_MAIL_LOG,VHITS_LOGLEVEL_CRITICAL);
	}
	else
	{
		hlog(json_encode($addresslist).LLOG_SEPARATOR.count($addresslist),VHITS_MAIL_LOG,VHITS_LOGLEVEL_CRITICAL);
	}

	return true;
}
function sendSMTPmail($fromaddress,$destination,$subject,$datatxt,$datahtml,$filename = NULL)
{
	if(VHITS_PHPMAILER_SEND)
	{
		$mail = new PHPMailer();
	}
	else
	{
		$mail = new PHPMailer\PHPMailer\PHPMailer();
	}
	$mail->IsSMTP();
	//$mail->SMTPDebug = 1;
	$mail->Host = VHITS_SMTP_HOST;
	$mail->SMTPAuth = true;
	$mail->SMTPOptions = array ('ssl'=>array ('verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true ) );
	$mail->Port = 465;
	$mail->Username = VHITS_SMTP_USERNAME;
	$mail->Password = VHITS_SMTP_PASSWORD;

	if(!empty($destination['ToAddresses']))
	{
		foreach($destination['ToAddresses'] as $thisemail)
		{
			$mail->addAddress($thisemail);
		}
	}

	if(!empty($destination['BccAddresses']))
	{
		foreach($destination['BccAddresses'] as $thisemail)
		{
			$mail->addAddress($thisemail);
		}
	}

	$mail->SetFrom($fromaddress);
	$mail->Subject = $subject;
	$mail->Body = $datahtml;
	$mail->AltBody = $datatxt;
	if(!empty($filename))
	{
		$mail->addAttachment($filename);
	}

	if(!$mail->send())
	{
		hlog_errorlog("MailError===>".$mail->ErrorInfo);
		return false;
	}
	else
	{
		hlog_errorlog("mail sent");
		return true;
	}
}
?>