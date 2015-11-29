<?php

/*
  08/11/2009, Rewritten 02/17/2010

  By Steve Fatula of 5 Diamond IT Consulting

  This class handles configuration information

  PHP programs should be run with:

  Environment variables PHP programs may use:
  	DBNAME_HOST - The host for database DBNAME to connect to
  	DBNAME_PORT - The port to connect to database DBNAME
  	DBNAME_DBFLAGS - The database open flags for database DBNAME
  	DBNAME_SOCKET - The socket file to use for database DBNAME
  	DEFAULT_HOST - The default host for any database, and, Solr
  	DEFAULT_PORT - The default port to connect to for any database
  	DEFAULT_DBFLAGS - The default database open flags for any database
  	DISPLAY_ERRORS - Set to 1 to enable display of error messages, not for production
  	MEMORY_LIMIT - Set to the memory size wanted
  	EMAIL_ERRORS_TO - Set email address to email all errors to
  	SOLRPORT - Port number for SOLR
  	SOLRHOST - Host name for SOLR
  	ISTEST - Set to "TRUE" if this is running on test
  	CONFIGFILE - Set to the name of a local configuration file, default is config.local

*/


class WsdlInfo {
	public $WsdlUri;
	public $Username;
	public $Pass;
}

abstract class Config {

const DefaultTaxGeoID = "SUNNYVALE";
const DefaultTaxRateSeqID = "11546";
const DefaultTaxRateDescr = "CA tax for city";
const DefaultTaxRate = 7.50;
const NEOBITSSTATECODE = "CA";
const NEOBITSCOUNTRYCODE = "US";
const NEOBITSZIPCODE = "94086";

const FeedTotalsKey = "FeedTotals";
const FeedsSocket = "/Feed.sock";
const ErrorHandlerException = 999999;
const CSVFix = "/usr/local/bin/csvfix";
const HeadProg = "/usr/bin/head";
const GUnzip = "/usr/bin/gunzip";
const Unzip = "/usr/bin/unzip";
const XSLTProc = "/usr/bin/xsltproc";
const Awk = "/usr/bin/awk";
const Dbf2Csv = "/usr/local/bin/dbf2csv.pl";
const Curl = "/usr/bin/curl";
const Scp = "/usr/bin/scp";
const WSDLCONFIGFILE = "wsdl.conf";
const ETILIZEPARTYID = "ETILIZE";

// Database Names
const DATABASE_MYDB = "mydb";
const DATABASE_OFBIZ = "ofbiz";
const DATABASE_OFBIZREADONLY = "ofbiz";
const DATABASE_OFBIZ2 = "ofbiz2";
const DATABASE_OFBIZ_FED = "ofbizfed";
const DATABASE_OFBIZ_REPLICATED = "ofbizrepl";
const DATABASE_OFBIZWORK = "ofbizwork";
const DATABASE_OFBIZEXTRA = "ofbizextra";
const DATABASE_OFBIZEXTRA_REPLICATED = "ofbizextrarepl";
const DATABASE_SCREENS = "screens";
const DATABASE_SESSIONS = "sessions";
const DATABASE_FEEDS = "feeds";
const DATABASE_FEEDS2 = "feeds2";
const DATABASE_FEEDS_FED= "feedsfed";
const DATABASE_ETILIZE = "etilize";
const DATABASE_POWERSYSTEMS = "powersystemsdirect";
const DATABASE_POWERSYSTEMSZEN = "powersystems";
const DATABASE_SOHOPBXWORLD = "sohopbxworld";
const DATABASE_VARSCAPE = "varscape";
const DATABASE_STOREFEED = "storefeed";
const DATABASE_NEOBITS = "neobits";
const DATABASE_CONSULTING = "sohopbxconsulting";
const DATABASE_PHPLISTVARSCAPE = "phplist_varscape";
const DATABASE_EXTRACTION = "extraction";
const DATABASE_SHIPPING = "shipping";
const DATABASE_OFBIZNICK = "ofbiznick";

// Database users and passwords
const DATABASE_MYDB_USER = "root";
const DATABASE_MYDB_PASSWORD = "";
const DATABASE_OFBIZREADONLY_USER = "ofbiz";
const DATABASE_OFBIZREADONLY_PASSWORD = "0fbiz!!";
const OFBIZ_USER = "ofbiz";
const OFBIZ_PASSWORD = "0fbiz!!";
const OFBIZWORK_USER = "ofbiz";
const OFBIZWORK_PASSWORD = "0fbiz!!";
const OFBIZEXTRA_USER = "ofbiz";
const OFBIZEXTRA_PASSWORD = "0fbiz!!";
const OFBIZNICK_USER = "ofbiz";
const OFBIZNICK_PASSWORD = "0fbiz!!";
const FEEDS_USER = "ofbiz";
const FEEDS_PASSWORD = "0fbiz!!";
const ETILIZE_USER = "ofbiz";
const ETILIZE_PASSWORD = "0fbiz!!";
const POWERSYSTEMS_USER = "powersystemsdire";
const POWERSYSTEMS_PASSWORD = "agoel427";
const SOHOPBXWORLD_USER = "sohopbxworld";
const SOHOPBXWORLD_PASSWORD = "Ne0b1ts0fb1z@#";
const VARSCAPE_USER = "varscape";
const VARSCAPE_PASSWORD = "OjIyBU8tZ7Kr";
const STOREFEED_USER = "storeuser";
const STOREFEED_PASSWORD = "ry7r3yfg";
const SCREENS_USER = "ofbiz";
const SCREENS_PASSWORD = "0fbiz!!";
const NEOBITS_USER = "root";
const NEOBITS_PASSWORD = "ne0bits";
const CONSULTING_USER = "root";
const CONSULTING_PASSWORD = "ne0bits";
const EXTRACTION_USER = "extraction";
const EXTRACTION_PASSWORD = "bljdfskebv443";
const SHIPPING_USER = "ofbiz";
const SHIPPING_PASSWORD = "0fbiz!!";
const ADMIN_USER = "root";
const ADMIN_PASSWORD = "ne0bits";
const TEST_HTTPUSER = "browse";
const TEST_HTTPPASSWORD = "df4ws3gbi";
const TEST_OFBIZADMINUSER = "admin";
const TEST_OFBIZADMINPASSWORD = "newpass";
const LIVE_HTTPUSER = null;
const LIVE_HTTPPASSWORD = null;
const LIVE_OFBIZADMINUSER = "admin";
const LIVE_OFBIZADMINPASSWORD = "newpass";

// Soap service names
const SHIPPINGQUOTES = "ShippingQuote";

// Scrape constants
const SCRAPE_REPORT = 'https://www.neobits.com/phpsecurenew/ScrapeTracker.php';
const SCRAPE_REPORT_LOGIN = 'agoel:ewdf9hisd';
const OFBIZKEY = "/root/id_ofbiz";
const SCRAPE_CSV_TARGET = 'ofbiz@host8.commservhost.com:/home/feeds/newuploads/GENERIC';
const SCRAPE_PERL_EXEC = '/home/scrapes/control/execute.pl';
const SCRAPE_CACHE_TIME = 3600;

// Google Checkout
const GOOGLE_MERCHANT_ID = "220202491476460";
const GOOGLE_MERCHANT_KEY = "nQLq4Q4M0NsD1-qoVP-Bnw";

// Some ofbiz constants
private static $enumPhoneType = array("PHONE_ASSISTANT"=>1, "PHONE_BILLING"=>2, "PHONE_DID"=>3, "PHONE_HOME"=>4, "PHONE_MOBILE"=>5, "PHONE_PAYMENT"=>6, "PHONE_QUICK"=>7, "PHONE_SHIPPING"=>8, "PHONE_SHIP_ORIG"=>9, "PHONE_WORK"=>10, "PHONE_WORK_SEC"=>11, "FAX_NUMBER"=>12, "FAX_NUMBER_SEC"=>13);
private static $enumEmailType = array("BILLING_EMAIL"=>1, "ORDER_EMAIL"=>2, "OTHER_EMAIL"=>3, "PAYMENT_EMAIL"=>4, "PRIMARY_EMAIL"=>5);

private static $IsWeb = FALSE;
private static $ApplicationName = "Default Application";
private static $DebugDisplays = FALSE;
private static $IncludePath = NULL;
private static $OfbizHome = "/home/ofbiz/ofbiz";
private static $ImagePathPrefix = "/home/ofbiz/public_html";
private static $TmpDir = NULL;
private static $XLS2CSV = NULL;
private static $SED = NULL;
private static $WGET = NULL;
private static $WINE = NULL;
private static $MYSQL = NULL;
private static $PHPPATH = NULL;
private static $PERL = null;
private static $EmailErrorsTo = NULL;
private static $SiteID = NULL;
private static $HomeDir = NULL;
private static $ErrorLog = NULL;
private static $LogDir = NULL;
private static $SolrPort = NULL;
private static $SolrHost = NULL;
private static $LocalURL = NULL;
public static $Host = NULL;
public static $IsTest = NULL;
private static $WsdlArray = NULL;
private static $ScreenMenuXML = NULL;			// SimpleXML menu structure and configuration
private static $ScreenProgramFile = NULL;		// Program file name for the current screen
private static $ScreenXMLObj = NULL;			// XML settings for the current screen
private static $LoginXMLObj = NULL;				// XML settings for the Login.php screen
private static $ScreenEnvironment = NULL;		// XML environment for the screen
private static $SessionsObj = NULL;				// Holds the MySQL sessions object, if a screen
private static $ScreenUser = False;				// Logged in screen user
private static $CookieDomain = NULL;			// Domain to use for cookies
private static $UserScreenAccess = NULL;		// Array of screens a user has access to

public static function init($ErrorHandler, $OverrideEmail='') {

	if (!empty($_SERVER['ISWEB']) && $_SERVER['ISWEB'] == "TRUE") self::$IsWeb = TRUE;
	self::$Host = "localhost";
	if (self::$IsWeb) {
		if (isset($_SERVER["DEFAULT_HOST"])) self::$Host = $_SERVER["DEFAULT_HOST"];
	} else {
		if (getenv("DEFAULT_HOST") !== FALSE) self::$Host = getenv("DEFAULT_HOST");
	}
	error_reporting(E_ALL);
	set_error_handler($ErrorHandler);
	$ConfigFileName = "Config.local";
	if (self::$IsWeb) {
		if (isset($_SERVER["ISTEST"])) self::$IsTest = ($_SERVER["ISTEST"] == "TRUE" ? TRUE : FALSE);
		if (isset($_SERVER["MEMORY_LIMIT"])) ini_set('memory_limit', $_SERVER["MEMORY_LIMIT"]);
		if (isset($_SERVER["DISPLAY_ERRORS"])) self::$DebugDisplays = TRUE;
		if (isset($_SERVER["EMAIL_ERRORS_TO"])) self::$EmailErrorsTo = $_SERVER["EMAIL_ERRORS_TO"];
		if (isset($_SERVER["PHPERRORLOG"])) self::$ErrorLog = $_SERVER["PHPERRORLOG"];
		if (isset($_SERVER["SOLRPORT"])) self::$SolrPort = $_SERVER["SOLRPORT"];
		if (isset($_SERVER["SOLRHOST"])) self::$SolrHost = $_SERVER["SOLRHOST"];
		if (isset($_SERVER["CONFIGFILE"])) $ConfigFileName = $_SERVER["CONFIGFILE"];
	} else {
		if (getenv("ISTEST") !== FALSE) self::$IsTest = (getenv("ISTEST") == "TRUE" ? TRUE : FALSE);
		if (getenv("MEMORY_LIMIT") !== FALSE) ini_set('memory_limit', getenv("MEMORY_LIMIT"));
		if (getenv("DISPLAY_ERRORS") !== FALSE) self::$DebugDisplays = TRUE;
		if (getenv("EMAIL_ERRORS_TO") !== FALSE) self::$EmailErrorsTo = getenv("EMAIL_ERRORS_TO");
		if (getenv("SOLRPORT") !== FALSE) self::$SolrPort = getenv("SOLRPORT");
		if (getenv("SOLRHOST") !== FALSE) self::$SolrHost = getenv("SOLRHOST");
		if (getenv("CONFIGFILE")) $ConfigFileName = getenv("CONFIGFILE");
	}
	if (!empty($OverrideEmail)) self::$EmailErrorsTo = $OverrideEmail;
	require($ConfigFileName);
	self::$MYSQL = $MysqlBin;
	self::$XLS2CSV = $XLS2CSV;
	self::$SED = $SED;
	self::$WINE = $WINE;
	self::$WGET = $WGET;
	self::$PERL = $PERL;
	$PHPConfgPath = get_cfg_var('cfg_file_path');
	if ($PHPConfgPath === FALSE) $PHPConfgPath = "";
	else $PHPConfgPath = " -c {$PHPConfgPath}";
	self::$PHPPATH = $PHPPATH . $PHPConfgPath;
	self::$LocalURL = $LOCALURL;
	self::$ScreenMenuXML = new SimpleXMLElement($ScreenMenuXML);
	self::$CookieDomain = $COOKIEDOMAIN;
	if (empty(self::$EmailErrorsTo)) self::$EmailErrorsTo = $DefaultEmailErrorsTo;
	return;
}

// Parse the XML menu data and obtain information about this screen.
private static function GetScreenSettings($ProgramFileName) {
	$xmlObj = self::$ScreenMenuXML;

	// Loop through all of the XML looking for the screen
	$ScreenNodes = $xmlObj->xpath('//Screen'); // Find all screens
	$ScreenXMLObj = Null;
	foreach ($ScreenNodes as $ScreenNode) {
		if (!$ScreenNode->{'FileName'}) continue;
		$FileName = trim((string) $ScreenNode->{'FileName'});
		if ($FileName == $ProgramFileName) {
			$ScreenXMLObj = $ScreenNode;
			break;
		}
	}

	return $ScreenXMLObj;
}

public static function GetAllScreens() {
	$xmlObj = self::$ScreenMenuXML;
	$Screens = array();

	// Loop through all of the XML looking for the screens
	$ScreenNodes = $xmlObj->xpath('//Screen'); // Find all screens
	foreach ($ScreenNodes as $ScreenNode) {
		if (!$ScreenNode->{'FileName'}) continue;
		if (!$ScreenNode->{'Title'}) continue;
		$FileName = trim((string) $ScreenNode->{'FileName'});
		$Title = trim((string) $ScreenNode->{'Title'});
		$Screens[$FileName] = $Title;
	}

	return $Screens;
}

public static function CloseSessionHandler() {
	if (is_null(self::$SessionsObj)) return;
	session_write_close();
	self::$SessionsObj = Null;
	return;
}

public static function GetUserScreenAccess($PartyID) {
	$ScreensDbObj = Config::GetDBName(Config::DATABASE_SCREENS);
	$CleanParty = $ScreensDbObj->EscapeField($PartyID);
	$sqlStmt = "select Screen_Name from Screen_Access where Party_ID = {$CleanParty}";
	$Results = $ScreensDbObj->SelectAndReturnAllRows($sqlStmt, False, True);
	$UserScreenList = array();
	foreach($Results as $Screen) {
		$UserScreenList[$Screen["Screen_Name"]] = null;
	}
	if (isset($_SESSION["USER"])) {
		$ScreenUser = trim($_SESSION["USER"]);
		if ($ScreenUser == $PartyID) self::$UserScreenAccess = $UserScreenList;
	}
	return $UserScreenList;
}

public static function GetLoggedInScreenAccess() {
	return self::$UserScreenAccess;
}

public static function EndLoggedInSession() {
	unset($_SESSION["USER"]);
	unset($_SESSION["SCREENLIST"]);
}

public static function initScreens($ErrorHandler, $ProgramFileName) {
	self::init($ErrorHandler);
	// if (!self::$IsWeb) trigger_error("You should not be calling Config::initScreens unless run from Apache", E_USER_ERROR);
	if (empty($ProgramFileName)) trigger_error("Must pass screen program file name to Config::initScreens", E_USER_ERROR);
	self::$ScreenProgramFile = basename($ProgramFileName);
	self::$LoginXMLObj = self::GetScreenSettings("Login.php");
	if (empty(self::$LoginXMLObj)) trigger_error("Cannot find configuration in the screen XML file for Login.php", E_USER_ERROR);
	if (!self::$LoginXMLObj->{'Environment'}) trigger_error("Must specify the location of the screens database in menu XML for Login.php", E_USER_ERROR);

	// We need to start session handling and check if the user is already logged in
	// Screen Environment is temporarily set here for Login.php just to get the login database info
	// For MySQLSessions
	self::$ScreenEnvironment = self::$LoginXMLObj->{'Environment'};
	if (self::$LoginXMLObj->{'Cache'} !== False) $Cache = (string) self::$LoginXMLObj->{'Cache'};
	else $Cache = "True";
	if ($Cache == "False") session_cache_limiter("nocache");
	self::$SessionsObj = new MySQLSessions();
	session_set_save_handler(
		array(self::$SessionsObj, "open"),
		array(self::$SessionsObj, "close"),
		array(self::$SessionsObj, "read"),
		array(self::$SessionsObj, "write"),
		array(self::$SessionsObj, "destroy"),
		array(self::$SessionsObj, "gc"));
	session_name("Neobits");
	ini_set('session.gc_maxlifetime', 28800);
	session_set_cookie_params(0, "/",  self::$CookieDomain); // 8 Hour Expiration
	session_start();

	if (isset($_SESSION["USER"])) {
		// Already logged in
		if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 28800)) {
    		// Last request was more than 8 hours ago, log user out for inactivity
    		self::EndLoggedInSession();
			if (!self::$LoginXMLObj->{'URL'}) trigger_error("URL not found in XML file for Login.php", E_USER_ERROR);
			$LoginURL = (string) self::$LoginXMLObj->{'URL'};
			self::CloseSessionHandler();
			header("Location: {$LoginURL}");
			exit;
		}
		self::$ScreenUser = $_SESSION["USER"];
		if (isset($_SESSION["SCREENLIST"])) self::$UserScreenAccess = $_SESSION["SCREENLIST"];
		else self::$UserScreenAccess = self::GetUserScreenAccess(self::$ScreenUser);

		// Check if the user has security for the screen
		if (array_key_exists(self::$ScreenProgramFile, self::$UserScreenAccess) === False) {
			$LoginURL = (string) self::$LoginXMLObj->{'URL'};
			self::CloseSessionHandler();
			header("Location: {$LoginURL}");
			exit;
		}
	} else {
		// Not logged in, check what screen we are on
		if (self::$ScreenProgramFile == "Login.php") {
			// Logging in
		} else {
			// Redirect to login screen
			if (!self::$LoginXMLObj->{'URL'}) trigger_error("URL not found in XML file for Login.php", E_USER_ERROR);
			$LoginURL = (string) self::$LoginXMLObj->{'URL'};
			self::CloseSessionHandler();
			header("Location: {$LoginURL}");
			exit;
		}
	}
	$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp

	// Now get the environment for the user screen
	self::$ScreenXMLObj = self::GetScreenSettings(self::$ScreenProgramFile);
	self::$ScreenEnvironment = self::$ScreenXMLObj->{'Environment'};
}

// Returns False is not logged in
public static function GetLoggedInUser() {
	return self::$ScreenUser;
}

public static function GetMenuXML() {
	return self::$ScreenMenuXML;
}

public static function IsWeb() {
	return self::$IsWeb;
}

public static function GetSolrHost() {
	if (is_null(self::$SolrHost)) return False;
	return self::$SolrHost;
}

public static function getSolrPort() {
	if (is_null(self::$SolrPort)) return False;
	return self::$SolrPort;
}

public static function IncludePath() {
	if (is_null(self::$IncludePath)) self::$IncludePath = dirname(__FILE__);;
	return self::$IncludePath;
}

public static function GetWSDLFullPath($WSDL) {
	if (self::$IsTest) return self::IncludePath() . "/WSDL/Development/" . $WSDL;
	else return self::IncludePath() . "/WSDL/Production/" . $WSDL;
}

public static function GetLocalURL($Secure=FALSE) {
	if ($Secure) $HTTP = "https";
	else $HTTP = "http";
	return $HTTP . "://" . self::$LocalURL;
}

public static function GetPaymentsHttpSecurity() {
	// Since this can badly impact live vs test financial orders, let's force ISTEST
	if (is_null(self::$IsTest)) trigger_error("ISTEST Must be set for this program. Set to the string TRUE for test, anything else for live", E_USER_ERROR);
	if (self::$IsTest) {
		$Security = array(
			"HttpUser" => self::TEST_HTTPUSER,
			"HttpPassword" => self::TEST_HTTPPASSWORD,
			"LoginUser" => self::TEST_OFBIZADMINUSER,
			"LoginPassword" => self::TEST_OFBIZADMINPASSWORD);
	} else {
		$Security = array(
			"HttpUser" => self::LIVE_HTTPUSER,
			"HttpPassword" => self::LIVE_HTTPPASSWORD,
			"LoginUser" => self::LIVE_OFBIZADMINUSER,
			"LoginPassword" => self::LIVE_OFBIZADMINPASSWORD);
	}
	return $Security;
}

public static function ApplicationName () {
	return self::$ApplicationName;
}

public static function DebugDisplays() {
	return self::$DebugDisplays;
}

public static function GetEmailErrorsTo() {
	if (!empty(self::$ScreenEnvironment) && self::$ScreenEnvironment->{"EMAIL_ERRORS_TO"}) self::$EmailErrorsTo = trim((string) self::$ScreenEnvironment->{"EMAIL_ERRORS_TO"});
	return self::$EmailErrorsTo;
}

public static function enumEmailType() {
	return self::$enumEmailType;
}

public static function enumPhoneType() {
	return self::$enumPhoneType;
}

public static function setApplicationName($ApplicationName) {
	self::$ApplicationName = $ApplicationName;
}

public static function setSiteID($SiteID, $HomeDir) {
	self::$SiteID = $SiteID;
	self::$HomeDir = $HomeDir;
}

public static function GetFeedSocket() {
	return "/tmp/" . self::FeedsSocket;
}

public static function getOfbizHome() {
	return self::$OfbizHome;
}

public static function getErrorLogName() {
	if (is_null(self::$ErrorLog)) self::$ErrorLog = ini_get("error_log");
	return self::$ErrorLog;
}

public static function getLogDir() {
	if (is_null(self::$LogDir)) self::$LogDir = dirname(self::getErrorLogName());
	return self::$LogDir;
}

public static function getImagePathPrefix() {
	return self::$ImagePathPrefix;
}

public static function getTmpDir() {
	if (is_null(self::$TmpDir)) self::$TmpDir = ini_get("upload_tmp_dir");
	return self::$TmpDir;

}

public static function GetXLS2CSV() {
	return self::$XLS2CSV;
}

public static function GetSED() {
	return self::$SED;
}

public static function GetMySQL() {
	return self::$MYSQL;
}

public static function GetWine() {
	return self::$WINE;
}

public static function GetWget() {
	return self::$WGET;
}

public static function GetPHP() {
	return self::$PHPPATH;
}

public static function getPerl() {
	return self::$PERL;
}

public static function GetDBName($DBName) {

	$DBName = strtolower($DBName);
	$dbObject = Registry::getDB($DBName);
	$NoUTF8 = FALSE;
	if (is_null($dbObject)) {
		switch($DBName) {
		  case self::DATABASE_MYDB:
		  	$database = self::DATABASE_MYDB;
			$user = self::DATABASE_MYDB_USER;
			$password = self::DATABASE_MYDB_PASSWORD;
			break;
		  case self::DATABASE_OFBIZ:
		  	$database = self::DATABASE_OFBIZ;
			$user = self::OFBIZ_USER;
			$password = self::OFBIZ_PASSWORD;
			break;
		  case self::DATABASE_OFBIZ2:			// Allow second open
		  	$database = self::DATABASE_OFBIZ;
			$user = self::OFBIZ_USER;
			$password = self::OFBIZ_PASSWORD;
			break;
		  case self::DATABASE_OFBIZ_REPLICATED:
		  	$database = self::DATABASE_OFBIZ;
			$user = self::NEOBITS_USER;
			$password = self::NEOBITS_PASSWORD;
			break;
		  case self::DATABASE_OFBIZ_FED:
		  	$database = self::DATABASE_OFBIZ_FED;
			$user = self::OFBIZ_USER;
			$password = self::OFBIZ_PASSWORD;
			break;
		  case self::DATABASE_OFBIZWORK:
		  	$database = self::DATABASE_OFBIZWORK;
			$user = self::OFBIZWORK_USER;
			$password = self::OFBIZWORK_PASSWORD;
			break;
		  case self::DATABASE_OFBIZEXTRA:
		  	$database = self::DATABASE_OFBIZEXTRA;
			$user = self::OFBIZEXTRA_USER;
			$password = self::OFBIZEXTRA_PASSWORD;
			break;
		  case self::DATABASE_OFBIZNICK:
		  	$database = self::DATABASE_OFBIZNICK;
			$user = self::OFBIZNICK_USER;
			$password = self::OFBIZNICK_PASSWORD;
			break;
		  // We use root user for replicated so we can do function CopyTableAcrossSevers
		  // This requires creating a temporary table on read only MySQL, which is ok
		  // But it won't allow adding of records due to MySQL bug.
		  // Replicated copy is read-only, and, ofbiz user does not have SUPER
		  case self::DATABASE_OFBIZEXTRA_REPLICATED:
		  	$database = self::DATABASE_OFBIZEXTRA;
			$user = self::NEOBITS_USER;
			$password = self::NEOBITS_PASSWORD;
			break;
		  case self::DATABASE_SCREENS:
		  	$database = self::DATABASE_SCREENS;
			$user = self::SCREENS_USER;
			$password = self::SCREENS_PASSWORD;
			break;
		  case self::DATABASE_SESSIONS:
		  	$database = self::DATABASE_SCREENS;
			$user = self::SCREENS_USER;
			$password = self::SCREENS_PASSWORD;
			break;
		  case self::DATABASE_FEEDS:
		  	$database = self::DATABASE_FEEDS;
			$user = self::FEEDS_USER;
			$password = self::FEEDS_PASSWORD;
			break;
		  case self::DATABASE_FEEDS2:				// Allow second open of Feeds database
		  	$database = self::DATABASE_FEEDS;
			$user = self::FEEDS_USER;
			$password = self::FEEDS_PASSWORD;
			break;
		  case self::DATABASE_FEEDS_FED:
		  	$database = self::DATABASE_FEEDS_FED;
			$user = self::FEEDS_USER;
			$password = self::FEEDS_PASSWORD;
			break;
		  case self::DATABASE_ETILIZE:
		  	$database = self::DATABASE_ETILIZE;
			$user = self::ETILIZE_USER;
			$password = self::ETILIZE_PASSWORD;
			break;
		  case self::DATABASE_POWERSYSTEMS:
		  	$database = self::DATABASE_POWERSYSTEMS;
			$user = self::POWERSYSTEMS_USER;
			$password = self::POWERSYSTEMS_PASSWORD;
			$NoUTF8 = TRUE;
			break;
		  case self::DATABASE_POWERSYSTEMSZEN:
		  	$database = self::DATABASE_POWERSYSTEMSZEN;
			$user = self::POWERSYSTEMS_USER;
			$password = self::POWERSYSTEMS_PASSWORD;
			break;
		  case self::DATABASE_SOHOPBXWORLD:
		  	$database = self::DATABASE_SOHOPBXWORLD;
			$user = self::SOHOPBXWORLD_USER;
			$password = self::SOHOPBXWORLD_PASSWORD;
			break;
		  case self::DATABASE_VARSCAPE:
		  	$database = self::DATABASE_VARSCAPE;
			$user = self::VARSCAPE_USER;
			$password = self::VARSCAPE_PASSWORD;
			break;
		  case self::DATABASE_PHPLISTVARSCAPE:
		  	$database = self::DATABASE_PHPLISTVARSCAPE;
			$user = self::VARSCAPE_USER;
			$password = self::VARSCAPE_PASSWORD;
			break;
		  case self::DATABASE_STOREFEED:
		  	$database = self::DATABASE_STOREFEED;
			$user = self::STOREFEED_USER;
			$password = self::STOREFEED_PASSWORD;
			break;
		  case self::DATABASE_NEOBITS:
		  	$database = self::DATABASE_NEOBITS;
			$user = self::NEOBITS_USER;
			$password = self::NEOBITS_PASSWORD;
			break;
		  case self::DATABASE_CONSULTING:
		  	$database = self::DATABASE_CONSULTING;
			$user = self::CONSULTING_USER;
			$password = self::CONSULTING_PASSWORD;
			break;
		  case self::DATABASE_EXTRACTION:
		  	$database = self::DATABASE_EXTRACTION;
			$user = self::EXTRACTION_USER;
			$password = self::EXTRACTION_PASSWORD;
			break;
		  case self::DATABASE_SHIPPING:
		  	$database = self::DATABASE_SHIPPING;
			$user = self::SHIPPING_USER;
			$password = self::SHIPPING_PASSWORD;
			break;
		  default:
			trigger_error("Database {$DBName} not configured in Config class, please fix", E_USER_ERROR);
		}
		$port = NULL;
		$socket = NULL;
		$dbFlags = NULL;
		if (self::$IsWeb) {
			if (isset($_SERVER["DEFAULT_HOST"])) $host = $_SERVER["DEFAULT_HOST"];
			if (isset($_SERVER["DEFAULT_PORT"])) $port = $_SERVER["DEFAULT_PORT"];
			if (isset($_SERVER["DEFAULT_DBFLAGS"])) $dbFlags = $_SERVER["DEFAULT_DBFLAGS"];
		} else {
			if (getenv("DEFAULT_HOST") !== FALSE) $host = getenv("DEFAULT_HOST");
			if (getenv("DEFAULT_PORT") !== FALSE) $port = getenv("DEFAULT_PORT");
			if (getenv("DEFAULT_DBFLAGS") !== FALSE) $dbFlags = getenv("DEFAULT_DBFLAGS");
		}
		if (self::$IsWeb) {
			if (isset($_SERVER["DEFAULT_HOST"])) $host = $_SERVER["DEFAULT_HOST"];
			if (isset($_SERVER["{$DBName}_HOST"])) $host = $_SERVER["{$DBName}_HOST"];
			if (isset($_SERVER["DEFAULT_PORT"])) $port = $_SERVER["DEFAULT_PORT"];
			if (isset($_SERVER["{$DBName}_PORT"])) $port = $_SERVER["{$DBName}_PORT"];
			if (isset($_SERVER["{$DBName}_SOCKET"])) $socket = $_SERVER["{$DBName}_SOCKET"];
			if (isset($_SERVER["DEFAULT_DBFLAGS"])) $dbFlags = $_SERVER["DEFAULT_DBFLAGS"];
			if (isset($_SERVER["{$DBName}_DBFLAGS"])) $dbFlags = $_SERVER["{$DBName}_DBFLAGS"];
		} else {
			if (getenv("DEFAULT_HOST") !== FALSE) $host = getenv("DEFAULT_HOST");
			if (getenv("{$DBName}_HOST") !== FALSE) $host = getenv("{$DBName}_HOST");
			if (getenv("DEFAULT_PORT") !== FALSE) $port = getenv("DEFAULT_PORT");
			if (getenv("{$DBName}_PORT") !== FALSE) $port = getenv("{$DBName}_PORT");
			if (getenv("{$DBName}_SOCKET") !== FALSE) $socket = getenv("{$DBName}_SOCKET");
			if (getenv("DEFAULT_DBFLAGS") !== FALSE) $dbFlags = getenv("DEFAULT_DBFLAGS");
			if (getenv("{$DBName}_DBFLAGS") !== FALSE) $dbFlags = getenv("{$DBName}_DBFLAGS");
		}
		if (!empty(self::$ScreenEnvironment)) {
			if (self::$ScreenEnvironment->{"{$DBName}_HOST"}) $host = trim((string) self::$ScreenEnvironment->{"{$DBName}_HOST"});
			if (self::$ScreenEnvironment->{"{$DBName}_PORT"}) $port = trim((string) self::$ScreenEnvironment->{"{$DBName}_PORT"});
			if (self::$ScreenEnvironment->{"{$DBName}_SOCKET"}) $socket = trim((string) self::$ScreenEnvironment->{"{$DBName}_SOCKET"});
			if (self::$ScreenEnvironment->{"{$DBName}_DBFLAGS"}) $dbFlags = trim((string) self::$ScreenEnvironment->{"{$DBName}_DBFLAGS"});
		}
		if (!isset($host)) $host = self::$Host;
		$dbObject = new Database($database, $host, $user, $password, $port, $socket, $dbFlags, $NoUTF8);
		Registry::set($DBName, $dbObject);
	}
	return $dbObject;
}

// This method read the wsdl.conf configuration file (first time only) and maintains a list of Soap services
// Along with their WSDL files, usernames, and passwords. It returns that information for any given service
public static function GetWSDLInfo($ServiceName) {

	// First time we need it, set up the list of wsdl paths
	if (is_null(self::$WsdlArray)) {
		self::$WsdlArray = array();
		$WsdlConfigFile = dirname(__FILE__) . "/" . self::WSDLCONFIGFILE;
		if (is_readable($WsdlConfigFile)) {
			if (($handle = fopen($WsdlConfigFile, "r")) !== FALSE) {
				$FirstTime = TRUE;
    			while (($data = fgetcsv($handle)) !== FALSE) {
    				// Skip first record as it is a header row
    				if ($FirstTime) {
    					$FirstTime = FALSE;
    					continue;
    				}
    				$WsdlObj = new WsdlInfo();
    				$WsdlObj->WsdlUri = trim($data[1]);
    				$WsdlObj->Username = trim($data[2]);
    				$WsdlObj->Pass = trim($data[3]);
    				self::$WsdlArray[strtolower(trim($data[0]))] = $WsdlObj;
    			}
    		}
		}
	}

	$ServiceName = strtolower(trim($ServiceName));
	if (array_key_exists($ServiceName, self::$WsdlArray)) return self::$WsdlArray[$ServiceName];
	else trigger_error("Soap service name {$ServiceName} not defined in wsdl.conf", E_USER_ERROR);
}

}
?>
