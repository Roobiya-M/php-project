<?php
/**
 * *******************************************************
 * Author: Roobiya M
 * Copyright:IT Solution Private Limited
 * Version: 1.0
 * Date: 11-Aug-2022
 * FileName: const.inc.php
 * Description: constant definition file
 * ********************************************************
 */
require_once ("password.php");
require_once ("errorcodes.php");

error_log("testing");
require_once("testconst.inc.php");
//require_once ("liveconst.inc.php"); // for LIVE LCO portal
//require_once("liveconst_dpo.inc.php"); // for LIVE DPO Portal 
//require_once("liveconst_selfcare.inc.php"); // for LIVE Selfcare Portal

$hostname = gethostname();
//CSS and Javascript Release Version related
define('VHITS_RELEASE_VERSION_NUMBER',xyz);

//Logging related
define('VHITS_LOG_LOCATION','/var/url/logs/');
define('VHITS_HLOG','hlog');
define('VHITS_LCO_LOG','lcolog');
define('VHITS_CUSTOMER_LOG','customerlog');
define('VHITS_DEVICE_LOG','devicelog');
define('VHITS_ACTIVATION_LOG','activationlog');
define('VHITS_SMS_LOG','smslog');
define('VHITS_EMAIL_LOG','emaillog');
define('VHITS_CUSTOMER_DOC_LOG','customerdoclog');
define('VHITS_PRODUCT_LOG','productlog');
define("VHITS_CONNECTION_LOG",'connectionlog');

define("VHITS_LOGLEVEL_CRITICAL",1);
define("VHITS_LOGLEVEL_WARNING",2);
define("VHITS_LOGLEVEL_INFO",3);

define('VHITS_MAIL_LOG','maillog');
define('REDIS_EXPIRY_TIME',21600);

//admin related
define('VHITS_ADMIN_NAME','SESS_ADMIN_NAME');
define('VHITS_ADMIN_EMAIL','SESS_ADMIN_EMAIL');
define('VHITS_ADMIN_ROLE','SESS_ADMIN_ROLE');
define('VHITS_ADMIN_TYPE','SESS_ADMIN_TYPE');
define('VHITS_ADMIN_PERMISSION','SESS_ADMIN_PERMISSION');
define('ADMIN_MAINTENANCE_PASSWORD','test');

//SESSION Related
define('VHITS_USER_NAME','SESS_USER_NAME');
define('VHITS_TOKEN','SESS_USER_TOKEN');
define('VHITS_SELFCARE_TOKEN','SESS_SELFCARE_TOKEN');

//Roles
define('VHITS_ADMIN_ROLEID','SESS_ADMIN_ID');
define('VHITS_LCO_ROLEID','SESS_LCO_ID');
define('VHITS_CUSTOMER_ROLEID','SESS_CUSTOMER_ID');
define('VHITS_MSO_ROLEID','SESS_MSO_ID');
define('VHITS_MANAGEMENT_ROLEID','SESS_MANAGEMENT_ID');
define('VHITS_SUBSCRIBER_ROLEID','SESS_SUBSCRIBER_ID');
define('VHITS_DPO_ROLEID','SESS_DPO_ID');

//subuser related

define('LCO_SUBUSER_PERMISSION','customer:payment:product:report:device:ticket');
define('ADMIN_SUBUSER_PERMISSION','lco:mso:product:report:bulk:maintenance:subuser');


//Time Related
define('DEFAULT_TIME_ZONE','UTC');
define('LOCAL_TIME_ZONE','America/New_York');
define('INDIAN_TIME_ZONE','Asia/Calcutta');
define('UTC_OFFSET_TIME','+00:00');


//Archivedata Related
define('VHITS_DAYSTOARCHIVE','-90 days');

//sms template message
define('VHITS_SMS_LCO_OTP_MESSAGE','Dear LCO, Thanks for Signing Up for NxtDigital LCO portal. Please use OTP {otp} for verification.');
define('VHITS_SMS_MSO_OTP_MESSAGE','Dear MSO, Thanks for Signing Up for NxtDigital MSO portal. Please use OTP {otp} for verification');
define('VHITS_SMS_LCO_REVERIFY_MESSAGE','Dear LCO, your mobile no has been updated. Please reverify at NXTDigital LCO Portal using OTP {otp}');
define('VHITS_SMS_MSO_REVERIFY_MESSAGE','Dear MSO, your mobile no has been updated. Please reverify at NXTDigital MSO Portal using OTP {otp}');
define('VHITS_SMS_LCO_ADD_PRODUCT_SUCCESS_MESSAGE','Dear LCO, Product {productname} for customer {customername} is activated');
define('VHITS_SMS_LCO_ADD_PRODUCT_PENDING_MESSAGE','Dear LCO, Product {productname} for customer {customername} is PENDING and shall be activated soon.  For support contact {contactno}');
define('VHITS_SMS_MSO_PAYMENT_SUCCESS','Dear MSO, Thank you for payment of Rs. {amount} on {date}. Total Avbl. Bal. is Rs. {totalamount}. NXTDigital');



//pagination related
define('VHITS_DB_RESULT_LIMIT',64);
define('VHITS_DB_PAGE_LIMIT',15);
define('TVHITS_LIST_PAGE_LIMIT',50);

//CHAR TYPES
define('CHAR_TYPE_NUMERIC','CHAR_TYPE_NUMERIC');
define('CHAR_TYPE_ALPHABETS','CHAR_TYPE_ALPHABETS');
define('CHAR_TYPE_ALPHANUMERIC','CHAR_TYPE_ALPHANUMERIC');

//MEMCACHE definitions
define('MEM_CACHE_SERVER_NAME','localhost');
define('MEM_CACHE_SERVER_PORT','port');

//File type
define('FILE_TYPE_IMAGE','image');

//MIMETYPE RELATED
define('MIMETYPE_IMAGE_PNG','image/png');
define('MIMETYPE_IMAGE_JPG','image/jpeg');
define('MIMETYPE_IMAGE_BMP','image/bmp');
define('MIMETYPE_TEXT_PDF','application/pdf');
define('MIMETYPE_TEXT_PLAIN','text/plain');
define('MIMETYPE_XML','application/xml');
define('MIMETYPE_JSON','application/json');
define('MIMETYPE_AUDIO_MPEG','audio/mpeg');
define('MIMETYPE_AUDIO_MP4','audio/mp4');
define('MIMETYPE_VIDEO_MKV','video/x-matroska');
define('MIMETYPE_VIDEO_MP4','video/mp4');
define('MIMETYPE_TEXT_CSV','text/csv');

//EXTENSION RELATED
define('IMAGE_JPEG_EXTN','.jpg');
define('IMAGE_PNG_EXTN','.png');
define('IMAGE_BMP_EXTN','.bmp');
define('TEXT_PDF_EXTN','.pdf');
define('TEXT_PLAIN_EXTN','.txt');
define('VIDEO_MKV_EXTN','mkv');
define('VIDEO_MP4_EXTN','mp4');
define('TEXT_CSV_EXTN','.csv');

//Image related
define('IMAGE_SUBSCRIBER_PROFILE_WIDTH',360);
define('IMAGE_SUBSCRIBER_PROFILE_HEIGHT',270);
define('IMAGE_CHANEL_ICON_SIZE',256);
define('IMAGE_PROGRAM_WIDTH',480);
define('IMAGE_PROGRAM_HEIGHT',270);
define('IMAGE_QUALITY_HIGH',85);
define('IMAGE_QUALITY_FAIR',65);
define('IMAGE_QUALITY_LOW',50);
define('IMAGE_THUMB_SIZE',128);
define('PROFILE_MAX_WIDTH',64);
define('PROFILE_MAX_HEIGHT',64);

//template file name
define('VHITS_BASE_MAIL_TEMPLATE_FILE','envelope');
define('EMAIL_HANSEN_STATUS_TEMPLATE','hansenstatus');
define('VHITS_BULK_ACTIVATION_STATUS_TEMPLATE','bulkactivationstatus');
define('VHITS_BULK_AUTORENEW_STATUS_TEMPLATE','bulkautorenewstatus');
define('VHITS_BULK_CANCEL_STATUS_TEMPLATE','bulkcancelproduct');

//Curl Related
define("VHITS_CURL_CONNECTION_TIMEOUT",20000);
define("VHITS_CURL_EXECUTION_TIMEOUT",40000);

//Sleep Execution Related
define("VHITS_SLEEP_EXECUTION_TWO_SECONED",2);
define("VHITS_SLEEP_EXECUTION_FIVE_SECONED",5);
define("VHITS_SLEEP_EXECUTION_TEN_SECONED",10);

//Features Enabled/Disabled Related (FALSE = disabled)
define("VHITS_SMS_SENDING",TRUE);

//redirect related
define('VHITS_CHANGE_PASSWORD_PAGE','changepassword.php');

//Process Name related
define('VHITS_PROCESS_ACTIVATE_CUSTOMER','activatecustomer');
define('VHITS_PROCESS_BULK_BASE_PACK_SUBSCRIPTION','bulkbasepacksubscription');
define('VHITS_PROCESS_BULK_CREATE_CUSTOMER','bulkcreatecustomer');
define('VHITS_PROCESS_BULK_DISCONNECT','bulkdisconnect');
define('VHITS_PROCESS_BULK_DISCONNECT_SCHEDULE','bulkdisconnectschedule');
define('VHITS_PROCESS_BULK_RECONNECT','bulkreconnect');
define('VHITS_PROCESS_BULK_RENEWAL','bulkrenewal');

define('LOG_LEVEL_INFO', 'INFO');
define('LOG_LEVEL_ERROR', 'ERROR');
define('LOG_LEVEL_WARNING', 'WARNING');
define('ENABLED_LOG_LEVEL', 'WARNING');
