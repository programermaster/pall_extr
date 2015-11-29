<?php

/*
  03/30/2010

  By Steve Fatula of 5 Diamond IT Consulting

  This class handles acquiring and managing supplier product feeds. It will load them into the raw import table.

  Error Number definitions:
  	900 = item dropped

  *****Performance Improvement*****
	Create empty new table just like the old table, no keys, Supplier_Product_Master and Supplier_Inventory
	Save records  for supplier in a csv file
	When supplier is done, use load data infile to load them into new table with no keys
	At end of all supplier feeds, copy from old table to new the stuff we are missing.
	That would be all products in old that are not in new, and
		left(product_id, 1) <> N and
		Has at least one Supplier_Inventory record
		And is not too old, see FeedsWeekly and remove that logic from there
	Delete these sql statements from prefeeds
	But, we also need to copy over other fields not set
		product_id
		etilize_id
		And we also need to reset product_id to null if supplier_product_master.product_id is not null, but, the product does not exist in ofbiz_products
	Once done, use a double rename to put the new table into place and add primary key and other key for product_id
	The same concept would be applied to supplier_inventory
*/

class SupplierFeeds {

// Constants
const MINIMUMVENDORPRICE = .05;		// Drop all items cheaper than this
private $ValidFileTypes = array("TAB", "TABQ", "CSV", "XLS", "ZIPCSV", "ZIPTAB", "ZIPTABQ", "BAR", "DBF", "ZIPDBF", "ZIPBAR", "TIL", "ZIPTIL", "FIXED", "ZIPFIXED");

// Class variables
private $FileSpecs = array(); 		// Array of array of input file specifications
private $FileCategories = array();	// Value is count of files having the filetype (key)
private $FilesMerged = FALSE;
private $PartyID;					// Party ID of the supplier
private $TableName;					// Name of the MySQL raw table
private $UPCFieldName = NULL;		// Name of the field containing the UPC number, if any
private $DuplicateUPCs;				// Array of duplicate UPC numbers
private $FeedsDbObj;				// Read FEEDS database object
private $FeedsInsertObj;			// Insert/Update feeds object
private $OfbizReadDB;				// Ofbiz object
private static $ManufacturerList;	// List of all current OFBiz MFRs
private static $ManufacturerXref;	// List of all current MFR cross-references
private static $SupplierCategoryList; // List of all supplier categories known
private $SupplierErrors;			// Array of array of errors
private $SaveProductSQL = "";		// Holds the multiple row save statement
private $SaveInventorySQL = "";		// Holds the multiple row save statement
private $ErrorSql = "";				// Holds the multiple row Supplier_Errors statement
private $ErrorCounter = 0;			// Number of records in ErrorSql
private $SaveCounter = 0;			// Number of records in SaveProductSQL
private $LoadID;					// The feed Load_ID
private $MinimumRecords;			// Minimum # of records to be a valid feed
private $FilesToPurge;				// An array of files to be purged at the end, KEY = file to purge
private $CookieJar = NULL;			// Used to access web pages get cURL
private $IsMiniMSRP = FALSE;		// If TRUE, this is a Mini/MSRP feed
private $GenericFeed = FALSE;		// If TRUE, this is a generic feed run
private $MiniFeed = FALSE;			// If TRUE, this is a mini feed run
private $TechSecondTime = FALSE;
private $SupplierMfrDisallow = array(); // A list of supplier manufacturers we should drop
private $SupplierPrefixes = array(); // A list of prefixes to remove for manufacturers of the supplier
private $StartDateTime;				// For real time inventory, this is the time the feed was started

// Feed counters
private $StartSeconds;				// Unix start time of the feed run
private $SqlStart;					// Start time of the feed run in MySQL format
private $TotalProducts = 0;			// Number of items in the feed
private $ItemsWithErrors = 0;		// Number of items dropped due to errors

// Product Attributes
private $SupplierSKU;				// Suppliers part number
private $ManufacturerSKU;			// Manufacturers part number
private $CleanMfrSKU;				// Cleaned manufacturer part number
private $UPC;						// Items UPC code if valud and not a dupe, else NULL
private $ManufacturerName;			// Name of the products manufacturer, as specified by the supplier
private $OfbizMfrName;				// Name of the products manufacturer in ofbiz
private $ManufacturerID;			// Manufacturer ID from ofbiz database, NULL if new manufacturer
private $ProductName;				// Product name (short name)
private $SupplierProductName;		// Suppliers product name
private $MarketingDescription;		// Marketing description, hopefully long!
private $TechnicalDescription;		// Technical description, typically specs
private $SupplierPrice;				// Products price for the item
private $Weight;					// Product weight (NULL is none specified or 0)
private $MSRP;						// The MSRP for the product, NULL if none specified
private $MAP;						// MAP price for the product, NULL if none specified
private $InsideDeliveryCharge;		// Extra charge for delivery of the product inside the house
private $OutsideDeliveryCharge;		// Extra charge for delivery of the product outside the house
private $WholesalePrice;
private $PromoPrice;
private $Categories;				// Full Category product is in
private $CategoryID;				// Category ID of the full category
private $Length;					// Length dimension
private $Width;						// Width dimension
private $Height;					// Height dimension
private $Restricted;				// True if the item is a restricted item
private $Returnable;				// True if the item is returnable back to the supplier
private $Refurbished;				// True if the item is not new and is refurbished
private $MainImage;					// URL to the image file, if any
private $AlternateImage;			// URL to an alternate image file, if any
private $ImageReferrer;				// URL used as referrer when obtaining images
private $Warranty;					// Warranty text
private $InventoryData;				// Array of (Key = Facility) (Value = array("ETADATE", "QTYONHAND", "AVAILTOPROMISE"
private $TotalQuantity;				// Total quantity (real) for the supplier
private $VirtualInventory;			// Quantity in virtual (made up) inventory

// MySQL Prepared Statements
private $InsertMfrXref;

// PartyID is NULL for MSRP/MAP feeds
public function __construct($PartyID, $TableName, $GenericFeed = FALSE, $MiniFeed = FALSE) {
	$this->TableName = $TableName;
	$this->PartyID = $PartyID;
	$this->GenericFeed = $GenericFeed;
	$this->MiniFeed = $MiniFeed;
	$this->FeedsDbObj = Config::GetDBName(Config::DATABASE_FEEDS);
	$this->FeedsInsertObj = Config::GetDBName(Config::DATABASE_FEEDS2);
	$this->OfbizReadDB = Config::GetDBName(Config::DATABASE_OFBIZREADONLY);

	// Obtain current date and time from server, we use this for inventory timestamp
	$sqlStmt = "select now() as work_date";
	$sqlResult = $this->FeedsDbObj->SelectAndReturnRow($sqlStmt);
	$this->StartDateTime = $sqlResult["work_date"];

	if (is_null($PartyID)) {
		$this->IsMiniMSRP = TRUE;
	} else {
		// Check the supplier party id, and, if it does not exist, add it
		$SupplierObj = new OfbizSuppliers();
		$SupplierName = $SupplierObj->GetSupplierName($PartyID);
		if (is_null($SupplierName)) $SupplierObj->CreateNewSupplier($PartyID, $PartyID);
		if (!$MiniFeed) {
			$this->StartSeconds = time();
			$this->SqlStart = date("Y-m-d H:i:s");
			$sqlStmt = "select Current_Load_ID, Minimum_Records from Supplier_Control where Supplier_Party_ID = '{$PartyID}'";
			$results = $this->FeedsInsertObj->SelectAndReturnRow($sqlStmt);
			$this->LoadID = $results["Current_Load_ID"];
			$this->MinimumRecords = $results["Minimum_Records"];
			$sqlStmt = "insert ignore into Supplier_History (Supplier_Party_ID, Load_ID, Pull_Start) values ('{$this->PartyID}', {$this->LoadID}, '{$this->SqlStart}')";
			$this->FeedsInsertObj->InsertRow($sqlStmt);

			// Gather the list of disallowed supplier manufacturers
			$sqlStmt = "select smx.Supplier_Mfr_Name from Supplier_Manufacturer_Disallow smd, Supplier_Manufacturer_Xref smx where (smd.Supplier_Party_ID = '{$PartyID}' or smd.Supplier_Party_ID = 'ALL') and smd.MANUFACTURER_PARTY_ID = smx.Ofbiz_Manufacturer_ID and smx.Supplier_Party_ID = '{$PartyID}'";
			$MfrList = $this->FeedsDbObj->SelectAndReturnAllRows($sqlStmt, False, True);
			foreach ($MfrList as $MfrInfo) {
				$workName = trim($MfrInfo["Supplier_Mfr_Name"]);
				$workName = StripInvalidCharacters($workName);
				if (empty($workName)) continue;
				$workName = htmlspecialchars_decode($workName, ENT_QUOTES);
				$this->SupplierMfrDisallow[] = $workName;
			}

			// Gather the list of prefixes to be removed for this Supplier Party
			$sqlStmt = "select pr.prefix, smx.Supplier_Mfr_Name from Prefix_Remover pr, Supplier_Manufacturer_Xref smx where pr.supplier_id = '{$PartyID}' and smx.Ofbiz_Manufacturer_ID = pr.manufacturer_id and smx.Supplier_Party_ID = pr.supplier_id";
			$PrefixList = $this->FeedsDbObj->SelectAndReturnAllRows($sqlStmt, False, True);
			foreach ($PrefixList as $PrefixInfo) {
				$workName = trim($PrefixInfo["Supplier_Mfr_Name"]);
				$workName = StripInvalidCharacters($workName);
				if (empty($workName)) continue;
				$workName = htmlspecialchars_decode($workName, ENT_QUOTES);
				if (array_key_exists($workName, $this->SupplierPrefixes)) {
					$this->SupplierPrefixes[$workName][] = "/^" . $PrefixInfo["prefix"] . "[-]*/";
				} else {
					$this->SupplierPrefixes[$workName] = array("/^" . $PrefixInfo["prefix"] . "[-]*/");
				}
			}
			echo "Our prefix remover array is " . print_r($this->SupplierPrefixes);
		}
	}
	$this->FilesToPurge = array();
}

private function SetError($ErrorMessage, $ErrorPriority) {
	if (!empty($this->ErrorSql)) $this->ErrorSql .= ",";
	$supplierSKU = $this->FeedsDbObj->EscapeField($this->SupplierSKU);
	$manufacturerSKU = $this->FeedsDbObj->EscapeField($this->ManufacturerSKU);
	$manufacturerName = $this->FeedsDbObj->EscapeField($this->ManufacturerName);
	$message = $this->FeedsDbObj->EscapeField($ErrorMessage);
	$this->ErrorSql .= " (\"{$this->PartyID}\", {$supplierSKU}, {$manufacturerSKU},  {$manufacturerName}, {$ErrorPriority}, {$message})";
	$this->ErrorCounter++;
	$this->ItemsWithErrors++;
	if ($this->ErrorCounter >= 100) {
		$this->WriteOutErrors();
		$this->ErrorCounter = 0;
		$this->ErrorSql = "";
	}
	throw new exception($ErrorMessage, $ErrorPriority);
}

private function WriteOutErrors() {
	if ($this->ErrorCounter == 0) return;
	$this->ErrorSql = "insert into Supplier_Errors (Supplier_Party_ID, Supplier_SKU, Manufacturer_SKU, Supplier_Mfr_Name, Error_Priority, Error_Message) values" . $this->ErrorSql;
	$this->FeedsInsertObj->Query($this->ErrorSql);
}

/* This method takes an XML file, and, converts it to a delimited file so that it canbe used in RawInputFile

Parameters:
	$XMLFile - The xml file from the supplier
	$XSLText - The XS: code to convert the xml file
	$Unzip - Type of unzip (if any), G = GUNZIP, Z - UNZIP

Returns: The file name of the converted file
*/
public function ConvertXML($XMLFile, $XSLText, $Unzip) {
	// Error Checking of parameters
	if (!is_readable($XMLFile)) trigger_error("Filename {$XMLFile} does not exist or cannot be read", E_USER_ERROR);
	if (empty($XSLText)) trigger_error("XSL Text is a required parameter", E_USER_ERROR);

	if ($Unzip == "G") {
		$newXMLFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
		$command =  "cat {$XMLFile} | " . Config::GUnzip . " >{$newXMLFile}";
		$error = exec($command, $output, $status);
		if ($status != 0) trigger_error("gunzip failed on command {$command} {$output} {$status}", E_USER_ERROR);
		$this->FilesToPurge[$XMLFile] = NULL;
		$XMLFile = $newXMLFile;
	}
	$newFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
	$XSLFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
	if (file_put_contents($XSLFile, $XSLText) === FALSE) trigger_error("Could not save xsl into temp file", E_USER_ERROR);
	$command =  Config::XSLTProc . " -o {$newFile} {$XSLFile} {$XMLFile}";
	$error = exec($command, $output, $status);
	if ($status != 0) trigger_error("xsltproc failed on command {$command} {$output} {$status}", E_USER_ERROR);
	$this->FilesToPurge[$XMLFile] = NULL;
	$this->FilesToPurge[$XSLFile] = NULL;
	return $newFile;
}

/* This method is called potentially multiple times to specify the list of downloaded supplier feed lookup tables to be processed. The output CSV filename is returned for the supplier feed program to use as necessary

Parameters:
	$FileName - name of the local feed file to be processed, downloaded from supplier
	$FileType - Type of file, TAB or CSV, etc.
	$Fields - Array of field names. The key specifies the fieldname you want to keep from the FileName
	$SkipLines - how many lines to skip at the beginning of the file (perhaps a header row)
	$FileToExtract - File from the archive (if appropriate) to be extracted
*/
public function TableInputFile($FileName, $FileType, $Fields, $SkipLines, $FileToExtract = NULL) {
	// Error Checking of parameters
	if (!is_readable($FileName)) trigger_error("Filename {$FileName} does not exist or cannot be read", E_USER_ERROR);
	if (in_array($FileType, $this->ValidFileTypes) === FALSE) trigger_error("Invalid file type passed, {$FileType}", E_USER_ERROR);
	if (!is_array($Fields)) trigger_error("The fields parameter must be an array, {$Fields} passed", E_USER_ERROR);
	if (!is_numeric($SkipLines)) trigger_error("SkipLines must be numeric, {$SkipFirst} passed", E_USER_ERROR);

	$newFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
	if (asort($Fields) === FALSE) trigger_error("Unable to sort fields array", E_USER_ERROR);
	$this->FilesToPurge[$FileName] = NULL;
	$this->FilesToPurge[$newFile] = NULL;

	if (substr($FileType,0,3) == "ZIP") {
		$zip = new ZipArchive();
		if ($zip->open($FileName, ZipArchive::CREATE) !== TRUE) trigger_error("Could not open the Zip File Archive {$FileName}", E_USER_ERROR);
		// Get filename only, ignore rest of path
		$newFileName = pathinfo($newFile, PATHINFO_BASENAME);
		if (is_numeric($FileToExtract)) {
			$zip->renameIndex($FileToExtract, $newFileName);
		} else {
			$zip->renameName($FileToExtract, $newFileName);
		}
		$UnZipDir = Config::getTmpDir() . "/";
		if ($zip->extractTo($UnZipDir, $newFileName) === FALSE) trigger_error("Could not unzip the Zip File Archive {$FileName} file {$newFileName}", E_USER_ERROR);
		$zip->close();
		$FileName = $newFile;
		$newFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
		$this->FilesToPurge[$newFile] = NULL;
	}

	// Based on the file type, we will convert to CSV and remove headers.
	switch($FileType) {
		case "XLS":
			$newFile = $this->HandleXLSFile($FileName, $newFile, $Fields, $SkipLines, TRUE);
			break;
		case "ZIPCSV":
		case "CSV":
			$newFile = $this->HandleCSVFile($FileName, $newFile, $Fields, $SkipLines, TRUE);
			break;
		case "DBF":
		case "ZIPDBF":
			$newFile = $this->HandleDBFFile($FileName, $newFile, $Fields, $SkipLines, TRUE);
			break;
		case "TAB":
		case "ZIPTAB":
			$newFile = $this->HandleTabFile($FileName, $newFile, $Fields, $SkipLines, TRUE, FALSE);
			break;
		case "TABQ":
		case "ZIPTABQ":
			$newFile = $this->HandleTabFile($FileName, $newFile, $Fields, $SkipLines, TRUE, TRUE);
			break;
		case "BAR":
		case "ZIPBAR":
			$newFile = $this->HandleDelimitedFile($FileName, $newFile, $Fields, $SkipLines, "|", TRUE);
			break;
		case "TIL":
		case "ZIPTIL":
			$newFile = $this->HandleDelimitedFile($FileName, $newFile, $Fields, $SkipLines, "~", TRUE);
			break;
	}
	return $newFile;
}

/* This method is used to execute a custom csvfix command against a file and return a new file
*/
public function ExecuteCSVFix($OldFile, $Command, $KeepOldFile) {
	$newFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
	if (!$KeepOldFile) $this->FilesToPurge[$OldFile] = NULL;
	$commandToRun =  $Command . " -o {$newFile}";
	$error = exec($commandToRun, $output, $status);
	if ($status != 0) trigger_error("Custom CSV failed on command {$commandToRun}", E_USER_ERROR);
	return $newFile;
}

/* This method is used to execute a custom sed command against a file and return a new file
*/
public function ExecuteSEDFix($OldFile, $Command, $KeepOldFile) {
	$newFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
	if (!$KeepOldFile) $this->FilesToPurge[$OldFile] = NULL;
	$commandToRun =  $Command . " {$OldFile} >{$newFile}";
	$error = exec($commandToRun, $output, $status);
	if ($status != 0) trigger_error("Custom SED failed on command {$commandToRun}", E_USER_ERROR);
	return $newFile;
}


/* This method is called potentially multiple times to specify the list of downloaded supplier feed files to be processed.

Parameters:
	$FileName - name of the local feed file to be processed, downloaded from supplier
	$FileType - Type of file, TAB or CSV, etc.
	$Fields - Array of field names. The key specifies the fieldname you want to keep from the FileName, which must be unique but is an arbitrary name (will be used in the processing phase and eventually saved to MySQL table), the value specifies the column number in the file.
	$SkipLines - how many lines to skip at the beginning of the file (perhaps a header row)
	$FileCategory - Arbitrary ID for a file. All files with the same FileCategory will be merged (joined)
	$MergeFieldNumber - Which field will be the merge field if more than 1 file
	$OuterJoin - If TRUE, when we join files, keep items from the first file missing in the second file
	$TrimData - If TRUE, will trim leading and trailing blanks for each field
	$FileToExtract - File from the archive (if appropriate) to be extracted, 0 based if using number
*/
public function RawInputFile($FileName, $FileType, $Fields, $SkipLines, $FileCategory, $MergeFieldNumber, $UPCFieldName, $OuterJoin, $TrimData = FALSE, $FileToExtract = NULL, $KeepOldFile = FALSE) {
	// Error Checking of parameters
	if (!is_readable($FileName)) trigger_error("Filename {$FileName} does not exist or cannot be read", E_USER_ERROR);
	if (in_array($FileType, $this->ValidFileTypes) === FALSE) trigger_error("Invalid file type passed, {$FileType}", E_USER_ERROR);
	if (!is_array($Fields)) trigger_error("The fields parameter must be an array, {$Fields} passed", E_USER_ERROR);
	if (!is_numeric($SkipLines)) trigger_error("SkipLines must be numeric, {$SkipFirst} passed", E_USER_ERROR);
	if (strlen($FileCategory) == 0) trigger_error("FileType parameter is required", E_USER_ERROR);

	// Erase the supplier errors table
	$sqlStmt = "delete from Supplier_Errors where Supplier_Party_ID = '{$this->PartyID}'";
	$this->FeedsDbObj->DeleteRow($sqlStmt);

	echo "Input file for {$FileCategory} is {$FileName}\n";

	// Store the file specifications
	if (is_null($this->UPCFieldName)) $this->UPCFieldName = $UPCFieldName;
	if (isset($this->FileCategories[$FileCategory])) {
		$this->FileCategories[$FileCategory]++;
	} else {
		$this->FileCategories[$FileCategory] = 1;
	}

	$newFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
	if (asort($Fields) === FALSE) trigger_error("Unable to sort fields array", E_USER_ERROR);
	if ($this->IsMiniMSRP === FALSE && $this->GenericFeed === FALSE && $this->MiniFeed === FALSE && $KeepOldFile === FALSE) $this->FilesToPurge[$FileName] = NULL;
	$this->FilesToPurge[$newFile] = NULL;

	if (substr($FileType,0,3) == "ZIP") {
		$zip = new ZipArchive();
		if ($zip->open($FileName, ZipArchive::CREATE) !== TRUE) trigger_error("Could not open the Zip File Archive {$FileName}", E_USER_ERROR);
		// Get filename only, ignore rest of path
		$newFileName = pathinfo($newFile, PATHINFO_BASENAME);
		if (is_numeric($FileToExtract)) {
			$zip->renameIndex($FileToExtract, $newFileName);
		} else {
			$zip->renameName($FileToExtract, $newFileName);
		}
		$UnZipDir = Config::getTmpDir() . "/";
		if ($zip->extractTo($UnZipDir, $newFileName) === FALSE) trigger_error("Could not unzip the Zip File Archive {$FileName} file {$newFileName}", E_USER_ERROR);
		$zip->close();
		$FileName = $newFile;
		$newFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
		$this->FilesToPurge[$newFile] = NULL;
	}

	// Based on the file type, we will convert to CSV and remove headers.
	switch($FileType) {
		case "XLS":
			$newFile = $this->HandleXLSFile($FileName, $newFile, $Fields, $SkipLines, $TrimData);
			break;
		case "ZIPCSV":
		case "CSV":
			$newFile = $this->HandleCSVFile($FileName, $newFile, $Fields, $SkipLines, $TrimData);
			break;
		case "DBF":
		case "ZIPDBF":
			$newFile = $this->HandleDBFFile($FileName, $newFile, $Fields, $SkipLines, $TrimData);
			break;
		case "TAB":
		case "ZIPTAB":
			$newFile = $this->HandleTabFile($FileName, $newFile, $Fields, $SkipLines, $TrimData, FALSE);
			break;
		case "TABQ":
		case "ZIPTABQ":
			$newFile = $this->HandleTabFile($FileName, $newFile, $Fields, $SkipLines, $TrimData, TRUE);
			break;
		case "BAR":
		case "ZIPBAR":
			$newFile = $this->HandleDelimitedFile($FileName, $newFile, $Fields, $SkipLines, "|", $TrimData);
			break;
		case "TIL":
		case "ZIPTIL":
			$newFile = $this->HandleDelimitedFile($FileName, $newFile, $Fields, $SkipLines, "~", $TrimData);
			break;
		case "FIXED":
		case "ZIPFIXED":
			$newFile = $this->HandleFixedFile($FileName, $newFile, $Fields, $SkipLines, $TrimData);
	}

	// Re-sequence the columns
	// For fixed file format, we have to redo the fields array since it is not a list of fields. It's a list of starting positions:length
	$i = 1;
	foreach($Fields as $Key => $Value) {
		if ($FileType == "FIXED" || $FileType == "ZIPFIXED") {
			if ($i == $MergeFieldNumber) $MergeFieldNumber = $i;
		} else {
			if ($Value == $MergeFieldNumber) $MergeFieldNumber = $i;
		}
		$Fields[$Key] = $i;
		$i++;
	}

	echo "New file for {$FileCategory} is {$newFile}\n";

	$SpecsArray = array();
	$SpecsArray["FileName"] = $newFile;
	$SpecsArray["Fields"] = $Fields;
	$SpecsArray["FileCategory"] = $FileCategory;
	$SpecsArray["MergeFieldNumber"] = $MergeFieldNumber;
	$SpecsArray["OuterJoin"] = $OuterJoin;
	$this->FileSpecs[] = $SpecsArray;
}

private function HandleDBFFile($OldFile, $NewFile, $Fields, $SkipLines, $TrimData) {
	// Rename source file to .dbf for conversion program, output file will end in csv
	$TempOldName = $OldFile . ".dbf";
	if (rename($OldFile, $TempOldName) === FALSE) trigger_error("Can't rename {$OldFile} to {$TempOldName}", E_USER_ERROR);
	$command = Config::getPerl() . " " . Config::Dbf2Csv . " " . $TempOldName;
	echo "Executing command {$command}\n";
	$output = exec($command, $op, $return);
	if ($return != 0) trigger_error("Could not convert dbf file via {$command}", E_USER_ERROR);
	if (rename($TempOldName, $OldFile) === FALSE) trigger_error("Can't rename {$TempOldName} to {$OldFile}", E_USER_ERROR);
	$OldFile = $OldFile . ".csv";
	$this->FilesToPurge[$OldFile] = NULL;
	$this->HandleCSVFile($OldFile, $NewFile, $Fields, $SkipLines, $TrimData);
	return $NewFile;
}

private function HandleXLSFile($OldFile, $NewFile, $Fields, $SkipLines, $TrimData) {
	// Convert the xls file into a CSV file we can easily use
	$XLStoCSVCommand = Config::GetXLS2CSV() . " -x '{$OldFile}' -b WINDOWS-1252 -c '{$NewFile}' -a US-ASCII >/dev/null";
	echo "Executing command {$XLStoCSVCommand}\n";
	system($XLStoCSVCommand, $return);
	if ($return != 0) trigger_error("Error converting xls file to csv file via {$XLStoCSVCommand}", E_USER_ERROR);
	$FileName = $NewFile;
	$newFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
	$this->FilesToPurge[$newFile] = NULL;
	$this->HandleCSVFile($FileName, $newFile, $Fields, $SkipLines, $TrimData);
	return $newFile;
}

private function HandleCSVFile($OldFile, $NewFile, $Fields, $SkipLines, $TrimData) {
	$maxField = max($Fields);
	$fieldsString = implode(",", $Fields);
	$validateFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
	if (file_put_contents($validateFile, "fields\t*\t{$maxField}:999\n") === FALSE) trigger_error("Could not write to the validate file {$validateFile}", E_USER_ERROR);
	$this->FilesToPurge[$validateFile] = NULL;
	// Read in the file and ignore blank lines and skip header.
	// Also sort on the join field, and, eliminate duplicates
	if ($TrimData) $trimCommand = Config::CSVFix . " trim | ";
		else $trimCommand = "";
	if ($SkipLines > 0) {
		$command = Config::Awk . " 'NR>{$SkipLines}' {$OldFile} | " . Config::GetSED() . ' -e \'s/\\\\""/""/g\' | ' . Config::CSVFix . " validate -vf {$validateFile} -om pass | " . $trimCommand . Config::CSVFix . " order -f {$fieldsString} -o {$NewFile}";
	} else {
		$command =  Config::CSVFix . " validate -vf {$validateFile} -om pass {$OldFile} | " . $trimCommand . Config::GetSED() . ' -e \'s/\\\\""/""/g\' | ' . Config::CSVFix . " order -f {$fieldsString} -o {$NewFile}";
	}
	echo "Executing command {$command}\n";
	$error = exec($command, $output, $status);
	if ($status != 0) trigger_error("Convert file failed on command {$command}", E_USER_ERROR);
	return $NewFile;
}

private function HandleTabFile($OldFile, $NewFile, $Fields, $SkipLines, $TrimData, $RemoveQuotes) {
	$countField = count($Fields);
	$fieldsString = implode(",", $Fields);
	$validateFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
	if (file_put_contents($validateFile, "fields\t*\t{$countField}:{$countField}\n") === FALSE) trigger_error("Could not write to the validate file {$validateFile}", E_USER_ERROR);
	$this->FilesToPurge[$validateFile] = NULL;
	// Read in the file and convert to CSV, ignoring blank lines and skip header.
	// Also sort on the join field, and, eliminate duplicates
	if ($TrimData) $trimCommand = Config::CSVFix . " trim | ";
		else $trimCommand = "";
	if ($RemoveQuotes) $RemoveOption = "-csv";
		else $RemoveOption = "";
	if ($SkipLines > 0) {
		$command = Config::Awk . " 'NR>{$SkipLines}' {$OldFile} | " . Config::GetSED() . " -e 's/\\\\\\\\\\\\//g' | " . Config::CSVFix . " read_dsv -f {$fieldsString} -s '\\t' {$RemoveOption} | " . $trimCommand . Config::CSVFix . " validate -vf {$validateFile} -om pass -o {$NewFile}";
	} else {
		$command =  Config::GetSED() . " -e 's/\\\\\\\\\\\\//g' {$OldFile} | " . Config::CSVFix . " read_dsv -f {$fieldsString} -s '\\t' {$RemoveOption} | " . $trimCommand . Config::CSVFix . " validate -vf {$validateFile} -om pass -o {$NewFile}";
	}
	echo "Executing command {$command}\n";
	$error = exec($command, $output, $status);
	if ($status != 0) trigger_error("Convert file from tab delimited failed on command {$command}", E_USER_ERROR);
	return $NewFile;
}

private function HandleDelimitedFile($OldFile, $NewFile, $Fields, $SkipLines, $Delimiter, $TrimData) {
	$countField = count($Fields);
	$fieldsString = implode(",", $Fields);
	$validateFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
	if (file_put_contents($validateFile, "fields\t*\t{$countField}:{$countField}\n") === FALSE) trigger_error("Could not write to the validate file {$validateFile}", E_USER_ERROR);
	$this->FilesToPurge[$validateFile] = NULL;
	// Read in the file and convert to CSV, ignoring blank lines and skip header.
	// Also sort on the join field, and, eliminate duplicates
	if ($TrimData) $trimCommand = Config::CSVFix . " trim | ";
		else $trimCommand = "";
	if ($SkipLines > 0) {
		$command = Config::Awk . " 'NR>{$SkipLines}' {$OldFile} | " . Config::GetSED() . " -e 's/\\\\\\\\\\\\//g' | " . Config::GetSED() . " -e 's/\\\\$//g' | " . Config::CSVFix . " read_dsv -f {$fieldsString} -s '{$Delimiter}' | " . $trimCommand . Config::CSVFix . " validate -vf {$validateFile} -om pass -o {$NewFile}";
	} else {
		$command =  Config::GetSED() . " -e 's/\\\\\\\\\\\\//g' {$OldFile} | " . Config::GetSED() . " -e 's/\\\\$//g' | " . Config::CSVFix . " read_dsv -f {$fieldsString} -s '{$Delimiter}' | " . $trimCommand . Config::CSVFix . " validate -vf {$validateFile} -om pass -o {$NewFile}";
	}
	echo "Executing command {$command}\n";
	$error = exec($command, $output, $status);
	if ($status != 0) trigger_error("Convert file from tab delimited failed on command {$command}", E_USER_ERROR);
	return $NewFile;
}

private function HandleFixedFile($OldFile, $NewFile, $Fields, $SkipLines, $TrimData) {
	$countField = count($Fields);
	$fieldsString = implode(",", $Fields);
	$validateFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
	if (file_put_contents($validateFile, "fields\t*\t{$countField}:{$countField}\n") === FALSE) trigger_error("Could not write to the validate file {$validateFile}", E_USER_ERROR);
	$this->FilesToPurge[$validateFile] = NULL;
	// Read in the file and convert to CSV, ignoring blank lines and skip header.
	// Also sort on the join field, and, eliminate duplicates
	if ($TrimData) $trimCommand = Config::CSVFix . " trim | ";
		else $trimCommand = "";
	if ($SkipLines > 0) {
		$command = Config::Awk . " 'NR>{$SkipLines}' {$OldFile} | " . Config::GetSED() . " -e 's/\\\\\\\\\\\\//g' | " . Config::CSVFix . " read_fixed -f {$fieldsString}' | " . $trimCommand . Config::CSVFix . " validate -vf {$validateFile} -om pass -o {$NewFile}";
	} else {
		$command =  Config::GetSED() . " -e 's/\\\\\\\\\\\\//g' {$OldFile} | " . Config::CSVFix . " read_fixed -f {$fieldsString} | " . $trimCommand . Config::CSVFix . " validate -vf {$validateFile} -om pass -o {$NewFile}";
	}
	echo "Executing command {$command}\n";
	$error = exec($command, $output, $status);
	if ($status != 0) trigger_error("Convert file from tab delimited failed on command {$command}", E_USER_ERROR);
	return $NewFile;
}

// This method merges files together and joins them as needed
private function JoinInputFiles() {

	// If only one file input, nothing to do!
	if (count($this->FileSpecs) == 1) return $this->FileSpecs[0]["FileName"];

	// First, we want to merge files of the same type to the first file of the group
	$NewFileSpecArray = array();
	foreach($this->FileCategories as $FileCategory => $FileCount) {
		$firstFile = NULL;
		foreach($this->FileSpecs as $SpecArray) {
			if ($SpecArray["FileCategory"] == $FileCategory) {
				if (is_null($firstFile)) {
					$firstFile = $SpecArray["FileName"];
					$NewFileSpecArray[] = $SpecArray;
					continue;
				}
				$output=array();
				$status=0;
				$command = "cat {$SpecArray['FileName']} >> {$firstFile}";
				$error = exec($command, $output, $status);
				if ($status != 0) trigger_error("Merge files failed on command {$command}", E_USER_ERROR);
			}
		}
	}
	$this->FileSpecs = NULL;
	// Now, we are left with one entry in $NewFileSpecArray for each file category
	if (count($NewFileSpecArray) == 1) {
		$this->FileSpecs = $NewFileSpecArray;
		return $NewFileSpecArray[0]["FileName"];
	}

	// We need to join the files if more than one file left. Join requires one at a time
	$numberFiles = count($NewFileSpecArray);
	$currentFile = 0;
	$newFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
	$this->FilesToPurge[$newFile] = NULL;
	foreach($NewFileSpecArray as $SpecArray) {
		$currentFile++;
		if ($currentFile == 1) {
			$fieldSpecs = $SpecArray["Fields"];
			$numberFields = count($fieldSpecs);
			$mergeField = $SpecArray["MergeFieldNumber"];
			$command = "cat {$SpecArray['FileName']} | ";
			continue;
		}
		$OuterJoin = $SpecArray["OuterJoin"];
		$currentField = 0;
		foreach($SpecArray["Fields"] as $Key => $Value) {
			$currentField++;
			if ($currentField == $SpecArray["MergeFieldNumber"]) continue;
			$numberFields++;
			$fieldSpecs[$Key] = $numberFields;
		}
		if ($currentFile == $numberFiles) {
			if ($OuterJoin) {
				$command .= Config::CSVFix . " join -oj -f {$mergeField}:{$SpecArray['MergeFieldNumber']} - {$SpecArray['FileName']} | " . Config::CSVFix . " pad -n {$numberFields} -o {$newFile} -";
			} else {
				$command .= Config::CSVFix . " join -f {$mergeField}:{$SpecArray['MergeFieldNumber']} -o {$newFile} - {$SpecArray['FileName']}";
			}
		} else {
			if ($OuterJoin) {
				$command .= Config::CSVFix . " join -oj -f {$mergeField}:{$SpecArray['MergeFieldNumber']} - {$SpecArray['FileName']} | " . Config::CSVFix . " pad -n {$numberFields} - | ";
			} else {
				$command .= Config::CSVFix . " join -f {$mergeField}:{$SpecArray['MergeFieldNumber']} - {$SpecArray['FileName']} | ";
			}
		}
	}
	$output = array();
	$status = 0;
	$error = exec($command, $output, $status);
	if ($status != 0) trigger_error("Join csv files failed on command {$command}", E_USER_ERROR);

	// Create our new final file specifications
	$this->FileSpecs = array();
	$this->FileSpecs[] = array(
		"FileName" => $newFile,
		"Fields" => $fieldSpecs);
	echo "Joined file is {$newFile}\n";
	return $newFile;
}

// This method takes the merged and joined input files and writes it out to a MySQL table
private function CreateMySQLRawTable() {

	// We need to know the largest field size for each input field so we can create the table accordingly
	$input = fopen($this->FileSpecs[0]["FileName"], "r");
	$largestFieldSize = array();
	for ($i = 0; $i < count($this->FileSpecs[0]["Fields"]); $i++) $largestFieldSize[$i] = 1;
	while ($fileRecord = fgetcsv($input)) {
		foreach($fileRecord as $Key => $Value) {
			$fieldLength = strlen($Value);
			if (empty($largestFieldSize[$Key])) NeobitsLogError("WRONG FEED FIELD COUNT, key: $Key\nfileRecord: ".print_r($fileRecord, true)."largestFieldSize: ".print_r($largestFieldSize, true)."this FileSpecs[0] ".print_r($this->FileSpecs[0], true));
			if ($fieldLength > $largestFieldSize[$Key]) $largestFieldSize[$Key] = $fieldLength;
		}
	}
	fclose($input);

	// Now, we can build our MySQL table
	$sqlStmt = "drop table if exists " . $this->TableName;
	$this->FeedsDbObj->Query($sqlStmt);
	$sqlStmt = "";
	foreach($this->FileSpecs[0]["Fields"] as $Name => $Value) {
		$fieldSize = $largestFieldSize[$Value - 1];
		$sqlStmt .= ", {$Name} varchar({$fieldSize})";
	}
	$sqlStmt = "create table {$this->TableName} (" . substr($sqlStmt,2) . ")";
	$this->FeedsDbObj->Query($sqlStmt);

	// Load the data into the MySQL raw feed table
	$sqlStmt = "load data local infile '{$this->FileSpecs[0]['FileName']}' into table {$this->TableName} fields terminated by ',' optionally enclosed by '\"' escaped by '\\\\' lines terminated by '\\n'";
	$this->FeedsDbObj->Query($sqlStmt);

	// Create our list of duplicate UPC numbers for later use, if we can
	if (!is_null($this->UPCFieldName)) {
		if (isset($this->FileSpecs[0]["Fields"][$this->UPCFieldName])) {
			$upcFieldNumber = $this->FileSpecs[0]["Fields"][$this->UPCFieldName];
			$uniqueName = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
			$this->FilesToPurge[$uniqueName] = NULL;
			$command = Config::CSVFix . " order -f {$upcFieldNumber} {$this->FileSpecs[0]['FileName']} | " . Config::CSVFix . " unique -f 1 -d | " . Config::CSVFix . " unique -f 1 -o $uniqueName";
			$output = array();
			$status = 0;
			$error = exec($command, $output, $status);
			if ($status != 0) trigger_error("Finding duplicate UPCs failed on command {$command}", E_USER_ERROR);
			$this->DuplicateUPCs = file($uniqueName);
			if ($this->DuplicateUPCs === FALSE) trigger_error("Reading duplicate UPCs failed on file {$uniqueName}", E_USER_ERROR);
			foreach ($this->DuplicateUPCs as $Key => $Value) {
				$this->DuplicateUPCs[$Key] = str_replace('"', "", trim($Value));
			}
		} else {
			trigger_error("Could not determine UPC field number", E_USER_ERROR);
		}
	}
}

// This method Joins all of the input files, writes them into a MySQL database table, and, also retrieves records one at a time for processing.
public function GetNextRecord() {
	if (!$this->FilesMerged) {
		$this->FilesMerged = TRUE;
		$this->JoinInputFiles();
		$this->CreateMySQLRawTable($this->TableName);
		$sqlStmt = "select SQL_NO_CACHE * from {$this->TableName}";
		$this->FeedsDbObj->SetUpSerialRead($sqlStmt);
	}
	$row = $this->FeedsDbObj->GetNextRow();
	/*
	if ($row === FALSE && $this->TableName == "TECHFEED" && $this->TotalProducts < 1000 && $this->TechSecondTime == FALSE) {
		$this->TotalProducts = $this->ItemsWithErrors = 0;
		$this->TechSecondTime = TRUE;
		$sqlStmt = "select * from {$this->TableName}";
		$this->FeedsDbObj->SetUpSerialRead($sqlStmt);
		$row = $this->FeedsDbObj->GetNextRow();
		echo "Had to redo Tech Feed as it had a premature end of file\n";
	}
	*/
	if ($row === FALSE) {
		$this->WriteOutErrors();
		if ($this->IsMiniMSRP || $this->MiniFeed) $this->ExecuteSaveMiniSQL();
			else $this->ExecuteSaveSQL();
		foreach ($this->FilesToPurge as $File => $Ignore) {
			@unlink($File);
		}
		$CompleteFeed = TRUE;
		if (!is_null($this->MinimumRecords)) {
			$GoodRecords = $this->TotalProducts - $this->ItemsWithErrors;
			if ($GoodRecords < $this->MinimumRecords) {
				$sqlStmt = "update Supplier_Control set Incomplete_Feed = 1 where Supplier_Party_ID = '{$this->PartyID}'";
				$this->FeedsInsertObj->UpdateRow($sqlStmt);
				$CompleteFeed = FALSE;
			}
		}
		if ($this->IsMiniMSRP === FALSE && $this->MiniFeed === FALSE) {
			$endTime = date("Y-m-d H:i:s");
			$elapsed = time() - $this->StartSeconds;
			if ($CompleteFeed) $FinalStatus = "Success";
			else $FinalStatus = "Incomplete";
			$sqlStmt = "update Supplier_History set Pull_End = '{$endTime}', Pull_Elapsed = {$elapsed}, Total_Products = {$this->TotalProducts}, Product_Errors = {$this->ItemsWithErrors}, Supplier_Status = '{$FinalStatus}' where Supplier_Party_ID = '{$this->PartyID}' and Load_ID = {$this->LoadID}";
			$this->FeedsInsertObj->UpdateRow($sqlStmt);
			$socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
			if (@socket_connect($socket, Config::GetFeedSocket(), 0) === FALSE) trigger_error("Can't connect to socket file", E_USER_ERROR);
			$pid = getmypid();
			socket_write($socket, pack('N', strlen($pid)) . $pid);
			socket_close($socket);
		}
		return FALSE;
	}
	$this->TotalProducts++;

	// Clear out all our product attributes
	$this->ManufacturerSKU = NULL;
	$this->CleanMfrSKU = NULL;
	$this->UPC = NULL;
	$this->SupplierSKU = NULL;
	$this->ManufacturerName = NULL;
	$this->OfbizMfrName = NULL;
	$this->ManufacturerID = NULL;
	$this->ProductName = NULL;
	$this->SupplierProductName = NULL;
	$this->MarketingDescription = NULL;
	$this->TechnicalDescription = NULL;
	$this->SupplierPrice = NULL;
	$this->Weight = NULL;
	$this->MSRP = NULL;
	$this->MAP = NULL;
	$this->InsideDeliveryCharge = NULL;
	$this->OutsideDeliveryCharge = NULL;
	$this->WholesalePrice = NULL;
	$this->PromoPrice = NULL;
	$this->Categories = NULL;
	$this->CategoryID = NULL;
	$this->Length = NULL;
	$this->Width = NULL;
	$this->Height = NULL;
	$this->Restricted = FALSE;
	$this->MainImage = NULL;
	$this->AlternateImage = NULL;
	$this->ImageReferrer = NULL;
	$this->Returnable = TRUE;
	$this->Refurbished = FALSE;
	$this->Warranty = NULL;
	$this->InventoryData = NULL;
	$this->IsQty5Supplier = FALSE;
	$this->TotalQuantity = 0;
	$this->VirtualInventory = NULL;
	return $row;
}

private function GetCookieFile() {
	if (is_null($this->CookieJar)) {
		$this->CookieJar = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
		$this->FilesToPurge[$this->CookieJar] = NULL;
	}
}

private function GetHTTPFileAttempt($URL, $PostFields, $Headers) {

	$downloadFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
	$this->GetCookieFile();

	$c = curl_init();
	$fn = fopen($downloadFile, "w");
	curl_setopt($c, CURLOPT_COOKIEJAR, $this->CookieJar);
	curl_setopt($c, CURLOPT_COOKIEFILE, $this->CookieJar);
	if (is_null($PostFields)) {
		curl_setopt($c, CURLOPT_HTTPGET, TRUE);
		curl_setopt($c, CURLOPT_POST, FALSE);
	} else {
		curl_setopt($c, CURLOPT_HTTPGET, FALSE);
		curl_setopt($c, CURLOPT_POST, TRUE);
		curl_setopt($c, CURLOPT_POSTFIELDS, $PostFields);
	}
	curl_setopt($c, CURLOPT_RETURNTRANSFER, FALSE);
	curl_setopt($c, CURLOPT_BINARYTRANSFER, TRUE);
	curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($c, CURLOPT_FOLLOWLOCATION, TRUE);
	if (!is_null($Headers)) curl_setopt($c, CURLOPT_HTTPHEADER, $Headers);
	curl_setopt($c, CURLOPT_AUTOREFERER, TRUE);
	curl_setopt($c, CURLOPT_FILE, $fn);
	curl_setopt($c, CURLOPT_URL, $URL);
	curl_setopt($c, CURLOPT_FAILONERROR, TRUE);
	$return = curl_exec($c);
	fclose($fn);
	if ($return === FALSE) {
		$erroNo = curl_errno($c);
		$errMsg = curl_error($c);
		echo "Could not access web page {$URL}, curl error # {$erroNo} {$errMsg}";
		unlink($downloadFile);
		curl_close($c);
		unset($c);
		return FALSE;
	}
	curl_close($c);
	unset($c);
	return $downloadFile;
}

public function GetHTTPFile($URL, $PostFields=NULL, $Headers=NULL) {

	echo "Getting file {$URL} via http at " .  date("Y-m-d H:i:s") . "\n";
	$attempt = $this->GetHTTPFileAttempt($URL, $PostFields, $Headers);
	if ($attempt !== FALSE) {
		echo "End file {$URL} via http at " .  date("Y-m-d H:i:s") . "\n";
		return $attempt;
	}
	sleep(120);
	$attempt = $this->GetHTTPFileAttempt($URL, $PostFields, $Headers);
	if ($attempt !== FALSE) {
		echo "End file {$URL} via http at " .  date("Y-m-d H:i:s") . "\n";
		return $attempt;
	}
	sleep(300);
	$attempt = $this->GetHTTPFileAttempt($URL, $PostFields, $Headers);
	if ($attempt === FALSE) trigger_error("File at URL {$URL} could not be downloaded", E_USER_ERROR);
	echo "End file {$URL} via http at " .  date("Y-m-d H:i:s") . "\n";
	return $attempt;
}

public function GetHTTPPage($URL, $PostFields=NULL, $Follow=TRUE, $Headers=NULL) {
	$this->GetCookieFile();
	$c = curl_init();
	curl_setopt($c, CURLOPT_COOKIEJAR, $this->CookieJar);
	curl_setopt($c, CURLOPT_COOKIEFILE, $this->CookieJar);
	curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($c, CURLOPT_FOLLOWLOCATION, $Follow);
	curl_setopt($c, CURLOPT_URL, $URL);
	curl_setopt($c, CURLOPT_FAILONERROR, TRUE);
	curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($c, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
	if (!is_null($Headers)) curl_setopt($c, CURLOPT_HTTPHEADER, $Headers);
	curl_setopt($c, CURLOPT_AUTOREFERER, TRUE);
	if (is_null($PostFields)) {
		curl_setopt($c, CURLOPT_HTTPGET, TRUE);
		curl_setopt($c, CURLOPT_POST, FALSE);
	} else {
		curl_setopt($c, CURLOPT_HTTPGET, FALSE);
		curl_setopt($c, CURLOPT_POST, TRUE);
		curl_setopt($c, CURLOPT_POSTFIELDS, $PostFields);
	}
	$return = curl_exec($c);
	if ($return === FALSE) {
		$erroNo = curl_errno($c);
		$errMsg = curl_error($c);
		curl_close($c);
		unset($c);
		trigger_error("Could not access web page {$URL}, curl error # {$erroNo} {$errMsg}", E_USER_ERROR);
	}
	curl_close($c); // Must close to get cookies dumped to file for use on other pages
	unset($c);
	return $return;
}

public function GetWineFile($Command, $DownloadFileName) {

	echo "Getting file {$DownloadFileName} via wine at " .  date("Y-m-d H:i:s") . "\n";
	$command = "cd " . Config::getTmpDir() . "; " . Config::GetWine() . " " . $Command;
	$NewFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
	$error = exec($command, $output, $status);
	if ($status != 0) trigger_error("Wine download failed on command {$command}", E_USER_ERROR);
	$DownloadedFileName = Config::getTmpDir() . "/" . $DownloadFileName;
	if (@rename($DownloadedFileName, $NewFile) === FALSE) trigger_error("Could not rename {$DownloadFileName} to {$NewFile}", E_USER_ERROR);
	$this->FilesToPurge[$DownloadFileName] = NULL;
	echo "End file {$DownloadFileName} via wine at " .  date("Y-m-d H:i:s") . "\n";
	return $NewFile;

}

public function GetFTPFile($Host, $User, $Password, $FilePath, $Binary = FALSE) {

	echo "Getting file {$FilePath} via ftp at " .  date("Y-m-d H:i:s") . "\n";
	$attempt = $this->GetFTPFileAttempt($Host, $User, $Password, $FilePath, $Binary);
	if ($attempt !== FALSE) {
		echo "End file {$FilePath} via ftp at " .  date("Y-m-d H:i:s") . "\n";
		return $attempt;
	}
	sleep(120);
	$attempt = $this->GetFTPFileAttempt($Host, $User, $Password, $FilePath, $Binary);
	if ($attempt !== FALSE) {
		echo "End file {$FilePath} via ftp at " .  date("Y-m-d H:i:s") . "\n";
		return $attempt;
	}
	sleep(120);
	$attempt = $this->GetFTPFileAttempt($Host, $User, $Password, $FilePath, $Binary);
	if ($attempt === FALSE) trigger_error("Could not download file {$FilePath}", E_USER_ERROR);
	echo "End file {$FilePath} via ftp at " .  date("Y-m-d H:i:s") . "\n";
	return $attempt;
}

public function GetFTPFileAttempt($Host, $User, $Password, $FilePath, $Binary) {

	if (FALSE === $FtpServer = ftp_connect($Host)) return FALSE;
	if (@ftp_login($FtpServer, $User, $Password) === FALSE) trigger_error("Could not log in to {$Host} server with user {$User} and password {$Password}", E_USER_ERROR);
	ftp_pasv($FtpServer, TRUE);
	$downloadFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
	if ($Binary) {
		if (@ftp_get($FtpServer, $downloadFile, $FilePath, FTP_BINARY) === FALSE) {
			$this->FilesToPurge[$downloadFile] = NULL;
			ftp_close($FtpServer);
			return FALSE;
		}
	} else {
		if (@ftp_get($FtpServer, $downloadFile, $FilePath, FTP_ASCII) === FALSE) {
			$this->FilesToPurge[$downloadFile] = NULL;
			ftp_close($FtpServer);
			return FALSE;
		}
	}
	ftp_close($FtpServer);
	return $downloadFile;
}

public function GetSFTPFile($Host, $User, $Password, $FilePath, $Port = 22) {
	echo "Getting file {$FilePath} via sftp at " .  date("Y-m-d H:i:s") . "\n";
	$attempt = $this->GetSFTPFileAttempt($Host, $User, $Password, $FilePath, $Port);
	if ($attempt !== FALSE) {
		echo "End file {$FilePath} via sftp at " .  date("Y-m-d H:i:s") . "\n";
		return $attempt;
	}
	sleep(120);
	$attempt = $this->GetSFTPFileAttempt($Host, $User, $Password, $FilePath, $Port);
	if ($attempt !== FALSE) {
		echo "End file {$FilePath} via sftp at " .  date("Y-m-d H:i:s") . "\n";
		return $attempt;
	}
	sleep(120);
	$attempt = $this->GetSFTPFileAttempt($Host, $User, $Password, $FilePath, $Port);
	if ($attempt === FALSE) trigger_error("Could not download file {$FilePath}", E_USER_ERROR);
	echo "End file {$FilePath} via sftp at " .  date("Y-m-d H:i:s") . "\n";
	return $attempt;
}

public function GetSFTPFileAttempt($Host, $User, $Password, $FilePath, $Port) {
	$downloadFile = tempnam(Config::getTmpDir(), "{$this->PartyID}-");
	$command = "/usr/bin/expect -f " . Config::IncludePath() . "/sftp_expect {$Host} {$Port} {$User} {$Password} {$FilePath} {$downloadFile}";
	$output = exec($command, $op, $return);
	if ($return != 0) return FALSE;
	return $downloadFile;
}
public function SetSupplierSku($SKU) {
	$workSKU = trim($SKU);
	if (empty($workSKU)) throw new exception("Supplier SKU is blank", 999); // Cannot store this error
	$this->SupplierSKU = StripInvalidCharacters($workSKU);
}

public function SetUPC($UPC) {
	$workUPC = trim($UPC);
	if (empty($workUPC)) return;
	if (strlen($workUPC) == 13) $workUPC = substr($workUPC,1);
	if (IsValidUPC($workUPC)) {
		if (in_array($workUPC, $this->DuplicateUPCs)) return;
		$this->UPC = $workUPC;
	}
}

private function RemoveMfrPrefix() {
	if (!empty($this->ManufacturerSKU) && !empty($this->ManufacturerName)) {
		if (array_key_exists($this->ManufacturerName, $this->SupplierPrefixes) !== FALSE) {
			$Patterns = $this->SupplierPrefixes[$this->ManufacturerName];
			$this->ManufacturerSKU = preg_replace($Patterns, '', $this->ManufacturerSKU);
		}
	}
	$this->CleanMfrSKU = CleanName($this->ManufacturerSKU);
}

public function SetManufacturerSku($SKU) {
	$workSKU = trim($SKU);
	if (empty($workSKU)) $this->SetError("Mfr SKU is blank", 999);
	$this->ManufacturerSKU = strtoupper(StripInvalidCharacters($workSKU));

	// Remove any manufacturer prefixes
	$this->RemoveMfrPrefix();
	if (empty($this->ManufacturerSKU)) $this->SetError("Mfr SKU is blank", 999);
}

public function SetManufacturerName($Name) {
	$workName = trim($Name);
	$workName = StripInvalidCharacters($workName);
	if (empty($workName)) $this->SetError("No manufacturer name", 999);
	$workName = htmlspecialchars_decode($workName, ENT_QUOTES);
	$this->ManufacturerName = $workName;

	// Remove any manufacturer prefixes
	$this->RemoveMfrPrefix();

	// Check is this manufacturer is disallowed for the supplier
	if (in_array($workName, $this->SupplierMfrDisallow) === True) $this->SetError("Manufacturer is disallowed for this supplier", 999);

	// Cache the manufacturer list in memory for speed
	if (!is_array(self::$ManufacturerList)) {
		self::$ManufacturerList = array();
		$sqlStmt = "select SQL_NO_CACHE pg.GROUP_NAME, pg.PARTY_ID from PARTY_GROUP pg, PARTY_ROLE pr where pr.PARTY_ID = pg.PARTY_ID and pr.ROLE_TYPE_ID = 'OEM' and pg.PARTY_ID is not null";
		$mfrList = $this->OfbizReadDB->SelectAndReturnAllRows($sqlStmt);
		foreach($mfrList as $value) {
			self::$ManufacturerList[$value["PARTY_ID"]] = $value["GROUP_NAME"];
		}
	}

	// Cache the manufacturer xref table in memory for speed
	if (!is_array(self::$ManufacturerXref)) {
		self::$ManufacturerXref = array();
		$sqlStmt = "select Supplier_Party_ID, Supplier_Mfr_Name, Ofbiz_Manufacturer_ID from Supplier_Manufacturer_Xref";
		$xrefList = $this->FeedsInsertObj->SelectAndReturnAllRows($sqlStmt, TRUE);
		if (!is_null($xrefList)) {
			foreach($xrefList as $value) {
				$arrayKey = $value["Supplier_Party_ID"] . $value["Supplier_Mfr_Name"];
				self::$ManufacturerXref[$arrayKey] = $value["Ofbiz_Manufacturer_ID"];
			}
		}
	}

	// Lookup in xref table
	$lookupKey = $this->PartyID . $workName;
	if (array_key_exists($lookupKey, self::$ManufacturerXref)) {
		$this->ManufacturerID = self::$ManufacturerXref[$lookupKey];
		if (empty($this->ManufacturerID)) {
			$this->ManufacturerID = NULL;
			$this->OfbizMfrName = NULL;
		} else {
			if (!array_key_exists($this->ManufacturerID, self::$ManufacturerList)) $this->SetError("Mfr ID {$this->ManufacturerID} not in ofbiz yet is mapped", 999);
			$this->OfbizMfrName = self::$ManufacturerList[$this->ManufacturerID];
		}
	} else {
		if (is_null($this->InsertMfrXref)) $this->InsertMfrXref = new MysqlStatement($this->FeedsInsertObj, "insert into Supplier_Manufacturer_Xref (Supplier_Party_ID, Supplier_Mfr_Name, Ofbiz_Manufacturer_ID) values (?, ?, null)", array("supplierparty" => MysqlStatement::TYPE_STRING, "mfrname" => MysqlStatement::TYPE_STRING));
		$this->InsertMfrXref->SetParameters(array("supplierparty" => $this->PartyID, "mfrname" => $workName));
		$this->InsertMfrXref->InsertRow();
		self::$ManufacturerXref[$lookupKey] = NULL;
		$this->ManufacturerID = NULL;
		$this->OfbizMfrName = NULL;
	}
}

public function SetManufacturerID($ID) {
	if (empty($ID)) $this->SetError("No manufacturer id passed", 999);
	$this->ManufacturerID = $ID;
}

public function SetProductName($Name) {
	$workName = trim($Name);
	if (empty($workName) && !$this->MiniFeed) $this->SetError("Product name is empty", 999);
	$this->SupplierProductName = strip_tags(StripInvalidCharacters($workName));
	$this->SupplierProductName = CutToMaxLength($this->SupplierProductName, 175);
	// Remove extra spaces, and, make sure a single space follows any comma
	$this->ProductName = str_replace(",", ", ", preg_replace("/[,]\s+/", ",", preg_replace("/\s\s+/", " ", $workName)));
	if (strtoupper($this->ProductName) == $this->ProductName) $this->ProductName = ucwords(strtolower($this->ProductName));
	$this->ProductName = CutToMaxLength($this->ProductName, 255);
}

public function SetMarketingDescription($Description) {
	if (empty($this->ProductName)) trigger_error("You must set the Product Name before calling this function", E_USER_ERROR);
	$workName = trim($Description);
	if (empty($workName)) {
		$this->MarketingDescription = $this->ProductName;
	} else {
		$tempDescr = strip_tags($workName, "<p><a><img><ul><li><br><i><b><strong>");
		$tempDescr = str_replace("\n", " ", $tempDescr);
		$tempDescr = StripInvalidCharacters($tempDescr);
		// Remove extra spaces, and, make sure a single space follows any comma
		$this->MarketingDescription = str_replace(",", ", ", preg_replace("/[,]\s+/", ",", preg_replace("/\s\s+/", " ", $tempDescr)));
		if (empty($this->MarketingDescription)) $this->MarketingDescription = $this->ProductName;
	}

	$this->MarketingDescription = AddMissingULTag($this->MarketingDescription);
	if (strtoupper($this->MarketingDescription) == $this->MarketingDescription) $this->MarketingDescription = SentenceMixCase($this->MarketingDescription);
}

public function SetTechnicalDescription($Description) {
	$workName = trim($Description);
	if (empty($workName)) return;
	$tempDescr = strip_tags($workName, "<p><ul><li><br><i><b><strong>");
	$tempDescr = str_replace("\n", " ", $tempDescr);
	$tempDescr = StripInvalidCharacters($tempDescr);
	// Remove extra spaces, and, make sure a single space follows any comma
	$this->TechnicalDescription = str_replace(",", ", ", preg_replace("/[,]\s+/", ",", preg_replace("/\s\s+/", " ", $tempDescr)));

	$this->TechnicalDescription = AddMissingULTag($this->TechnicalDescription);
	if (strtoupper($this->TechnicalDescription) == $this->TechnicalDescription) $this->TechnicalDescription = SentenceMixCase($this->TechnicalDescription);
}

public function SetSupplierPrice($Price) {
	if (empty($this->SupplierSKU)) trigger_error("You must set the Supplier SKU before calling this function", E_USER_ERROR);
	$workPrice = trim($Price);
	$workPrice = trim(str_replace(array(",", "$"), "", $workPrice));
	if (!is_numeric($workPrice)) $workPrice = 0;
	if ($workPrice <= self::MINIMUMVENDORPRICE) $this->SetError("Price not greater than " . self::MINIMUMVENDORPRICE, 999);
	$this->SupplierPrice = round((float) $workPrice, 2);
}

public function SetWeight($Weight, $AlternateWeight = NULL) {
	// Remove any non numeric characters
	$workWeight = preg_replace('/[^0-9\.]/', '', $Weight);
	$workAlternateWeight = trim($AlternateWeight);
	$workAlternateWeight = str_replace(",", "", $workAlternateWeight);
	$useWeight = NULL;
	if (is_numeric($workWeight)) {
		if (is_numeric($workAlternateWeight)) {
			if ($workAlternateWeight > $workWeight) $useWeight = $workAlternateWeight;
				else $useWeight = $workWeight;
		} else $useWeight = $workWeight;
	} else if (is_numeric($workAlternateWeight)) $useWeight = $workAlternateWeight;
	if (is_numeric($useWeight) && (float) $useWeight != 0) $this->Weight = $useWeight;
}

public function SetMSRP($MSRP) {
	$workMSRP = trim($MSRP);
	$workMSRP = trim(str_replace(array(",", "$", '"'), "", $workMSRP));
	if (is_numeric($workMSRP) && $workMSRP != 0) $this->MSRP = round((float) $workMSRP, 2);
}

public function SetMAP($MAP) {
	$workMAP = trim($MAP);
	$workMAP = trim(str_replace(array(",", "$", '"'), "", $workMAP));
	if (is_numeric($workMAP) && $workMAP != 0) $this->MAP = round((float) $workMAP, 2);
}

public function SetWholesalePrice($Price) {
	$workWholesale = trim($Price);
	$workWholesale = trim(str_replace(array(",", "$", '"'), "", $workWholesale));
	if (is_numeric($workWholesale) && $workWholesale != 0) $this->WholesalePrice = round((float) $workWholesale, 2);
}

public function SetPromoPrice($Price) {
	$workPromo = trim($Price);
	$workPromo = trim(str_replace(array(",", "$", '"'), "", $workPromo));
	if (is_numeric($workPromo) && $workPromo != 0) $this->PromoPrice = round((float) $workPromo, 2);
}

public function SetCategory($CategoryArray) {
	if (empty($this->SupplierSKU)) trigger_error("You must set the Supplier SKU before calling this function", E_USER_ERROR);
	if (!is_array($CategoryArray)) trigger_error("SetCategory requires a category array, array was not passed on SKU [{$this->SupplierSKU}]", E_USER_ERROR);

	// Cache the category list in memory for speed
	if (!is_array(self::$SupplierCategoryList)) {
		self::$SupplierCategoryList = array();
		$sqlStmt = "select Full_Category, Category_Unique_ID from Supplier_Categories where Supplier_Party_ID = '{$this->PartyID}'";
		$catList = $this->FeedsInsertObj->SelectAndReturnAllRows($sqlStmt, TRUE);
		if (is_array($catList)) {
			foreach($catList as $value) {
				$key = $value["Full_Category"];
				self::$SupplierCategoryList[$key] = $value["Category_Unique_ID"];
			}
		}
	}

	if (count($CategoryArray) == 0) {
		$CategoryArray[] = "To Be Categorized";
	} else {
		$allEmpty = TRUE;
		foreach ($CategoryArray as $Category) {
			$cleanCategory = preg_replace("/[^a-zA-Z0-9 &]/", "", $Category);
			$workCategory = trim($cleanCategory);
			if (!empty($workCategory)) $allEmpty = FALSE;
		}
		if ($allEmpty) $CategoryArray = array("To Be Categorized");
	}
	$this->Categories = "";
	foreach ($CategoryArray as $Category) {
		$cleanCategory = strtoupper(preg_replace("/[^a-zA-Z0-9 &]/", "", $Category));
		$cleanCategory = trim($cleanCategory);
		if (empty($cleanCategory)) continue;
		if (empty($this->Categories)) {
			$this->Categories = $cleanCategory;
		} else {
			$this->Categories .= " > {$cleanCategory}";
		}
	}
	if (empty($this->Categories)) $this->SetError("No assigned category", 999);

	// Find the category in our cache list
	if (isset(self::$SupplierCategoryList[$this->Categories])) {
		$this->CategoryID = self::$SupplierCategoryList[$this->Categories];
	} else {
		// Add new category to table and array list
		$sqlStmt = "insert into Supplier_Categories (Supplier_Party_ID, Full_Category) values ('{$this->PartyID}', '{$this->Categories}')";
		$newID = $this->FeedsInsertObj->InsertRow($sqlStmt, TRUE);
		self::$SupplierCategoryList[$this->Categories] = $newID;
		$this->CategoryID = $newID;
	}
}

public function SetLength($Length) {
	$workLength = trim($Length);
	if (is_numeric($workLength) && $workLength != 0) {
		$Length = (float) $workLength;
		if ($Length != 0) $this->Length = $Length;
	}
}

public function SetWidth($Width) {
	$workWidth = trim($Width);
	if (is_numeric($workWidth) && $workWidth != 0) {
		$Width = (float) $workWidth;
		if ($Width != 0) $this->Width = $Width;
	}
}

public function SetHeight($Height) {
	$workHeight = trim($Height);
	if (is_numeric($workHeight) && $workHeight != 0) {
		$Height = (float) $workHeight;
		if ($Height != 0) $this->Height = $Height;
	}
}

public function SetRestricted($Restricted) {
	if ($Restricted) $this->Restricted = TRUE;
		else $this->Restricted = FALSE;
}

public function SetMainImage($MainImage) {
	$workImage = trim($MainImage);
	if (!empty($workImage)) $this->MainImage = $workImage;
}

public function SetImageReferrer($URL) {
	$workReferrer = trim($URL);
	if (!empty($workReferrer)) $this->ImageReferrer = $workReferrer;
}

public function SetAlternateImage($AlternateImage) {
	$workImage = trim($AlternateImage);
	if (!empty($workImage)) $this->AlternateImage = $workImage;
}

public function SetReturnable($Returnable) {
	if ($Returnable === TRUE) $Returnable = "Y";
	if ($Returnable === FALSE) $Returnable = "N";
	switch($Returnable) {
		case "Y":
			$this->Returnable = TRUE;
			break;
		case "N":
			$this->Returnable = FALSE;
			break;
		case "":
			$this->Returnable = TRUE;
			break;
		default:
			$this->Returnable = TRUE;
			break;
	}
}

public function SetRefurbished($Refurbished) {
	$Refurb = strtoupper(trim($Refurbished));
	switch($Refurb) {
		case "Y":
			$this->Refurbished = TRUE;
			break;
		case "N":
		case "NO":
			$this->Refurbished = FALSE;
			break;
		case "":
			$this->Refurbished = FALSE;
			break;
		case FALSE:
			$this->Refurbished = FALSE;
			break;
		default:
			$this->Refurbished = TRUE;
			break;
	}
}

public function SetWarranty($Warranty) {
	$workWarranty = trim($Warranty);
	if (!empty($workWarranty)) {
		if (strtoupper($workWarranty) != "PENDING" && strtoupper($workWarranty) != "UNAVAILABLE" && !is_numeric($workWarranty)) $this->Warranty = StripInvalidCharacters($workWarranty);
	}
}

public function SetDeliveryCharges($InsideCharge, $OutsideCharge) {
	$workInside = trim($InsideCharge);
	$workInside = trim(str_replace(array(",", "$", '"'), "", $workInside));
	if (is_numeric($workInside) && $workInside != 0) $this->InsideDeliveryCharge = (float) $workInside;
	$workOutside = trim($OutsideCharge);
	$workOutside = trim(str_replace(array(",", "$", '"'), "", $workOutside));
	if (is_numeric($workOutside) && $workOutside != 0) $this->OutsideDeliveryCharge = (float) $workOutside;
}

public function SetInventoryInfo($Facility, $Quantity, $ETADate = NULL, $Available = NULL, $TimeStamp = NULL) {

	if (is_null($Quantity)) {
		$this->VirtualInventory = 1;
		return;
	}

	if (empty($Facility)) {
		$Facility = trim(substr($this->PartyID, 0, 15)) . "Fclty";
	}

	if (is_null($this->InventoryData)) $this->InventoryData = array();

	$this->InventoryData[$Facility]["ETADATE"] = NULL;
	if (!empty($ETADate)) {
		$year = substr($ETADate, 0,4);
		$month = substr($ETADate, 5, 2);
		$day = substr($ETADate, 8, 2);
		if (is_numeric($year) && is_numeric($month) && is_numeric($day)) $this->InventoryData[$Facility]["ETADATE"] = $ETADate;
	}

	$Quantity = trim($Quantity);
	if (is_numeric($Quantity)) {
		$workQuantity = (float) $Quantity;
		if ($workQuantity < 0) $workQuantity = 0;
		$this->InventoryData[$Facility]["QTYONHAND"] = $workQuantity;
		$this->TotalQuantity += $workQuantity;
	} else $this->InventoryData[$Facility]["QTYONHAND"] = 0;

	if (is_numeric($Available)) $this->InventoryData[$Facility]["AVAILTOPROMISE"] = (float) $Available;
		else $this->InventoryData[$Facility]["AVAILTOPROMISE"] = NULL;
	if (is_null($TimeStamp)) $this->InventoryData[$Facility]["TIMESTAMP"] = $this->StartDateTime;
	else $this->InventoryData[$Facility]["TIMESTAMP"] = $TimeStamp;
}

public function Save() {

	// Check for required fields
	if (is_null($this->PartyID)) trigger_error("Supplier party id is required", E_USER_ERROR);
	if (is_null($this->SupplierSKU)) trigger_error("Supplier SKU is required", E_USER_ERROR);
	if (is_null($this->ManufacturerSKU)) trigger_error("Manufacturer SKU is required", E_USER_ERROR);
	if (is_null($this->CleanMfrSKU)) trigger_error("Clean Manufacturer SKU is required", E_USER_ERROR);
	if (is_null($this->ManufacturerName)) trigger_error("Manufacturer name is required", E_USER_ERROR);
	if (is_null($this->ProductName)) trigger_error("Product name is required", E_USER_ERROR);
	if (is_null($this->SupplierProductName)) trigger_error("Supplier product name is required", E_USER_ERROR);
	if (is_null($this->MarketingDescription)) trigger_error("Marketing description is required", E_USER_ERROR);
	if (is_null($this->SupplierPrice)) trigger_error("Supplier price is required", E_USER_ERROR);
	if (is_null($this->Categories)) trigger_error("Full category is required", E_USER_ERROR);
	if (is_null($this->CategoryID)) trigger_error("Category ID is required", E_USER_ERROR);
	if (is_null($this->Restricted)) trigger_error("Restricted item flag is required", E_USER_ERROR);
	if (is_null($this->Returnable)) trigger_error("Returnable flag is required", E_USER_ERROR);
	if (is_null($this->Refurbished)) trigger_error("Refurbished flag is required", E_USER_ERROR);
	if (is_null($this->LoadID)) trigger_error("Load ID is required", E_USER_ERROR);
	
	// Acquire the list of MAP price manufacturers to ignore
	$sqlStmt = "select PARTY_ID from Feeds_Mfr_Data where Value = 'Y' and Config_id = 85";
	$MAPMfrList = $this->FeedsInsertObj->SelectAndReturnAllRows($sqlStmt, False, True);
	$MapIgnoreList = array();
	foreach ($MAPMfrList as $MfrInfo) {
		$MapIgnoreList[] = $MfrInfo["PARTY_ID"];
	}
	
	if (!empty($this->ManufacturerID) && in_array($this->ManufacturerID, $MapIgnoreList)) {
		$this->MAP = Null;
	}

	$partyID = $this->FeedsDbObj->EscapeField($this->PartyID);
	$supplierSKU = $this->FeedsDbObj->EscapeField($this->SupplierSKU);
	$manufacturerSKU = $this->FeedsDbObj->EscapeField($this->ManufacturerSKU);
	$upcCode = $this->FeedsDbObj->EscapeField($this->UPC);
	$manufacturerName = $this->FeedsDbObj->EscapeField($this->ManufacturerName);
	$ofbizMfrName = $this->FeedsDbObj->EscapeField($this->OfbizMfrName);
	$manufacturerID = $this->FeedsDbObj->EscapeField($this->ManufacturerID);
	$productName = $this->FeedsDbObj->EscapeField($this->ProductName);
	$supplierProductName = $this->FeedsDbObj->EscapeField($this->SupplierProductName);
	$marketingDescription = $this->FeedsDbObj->EscapeField($this->MarketingDescription);
	$technicalDescription = $this->FeedsDbObj->EscapeField($this->TechnicalDescription);
	$categories = $this->FeedsDbObj->EscapeField($this->Categories);
	$warranty = $this->FeedsDbObj->EscapeField($this->Warranty);
	$mainImage = $this->FeedsDbObj->EscapeField($this->MainImage);
	$alternateImage = $this->FeedsDbObj->EscapeField($this->AlternateImage);
	$imageReferrer = $this->FeedsDbObj->EscapeField($this->ImageReferrer);
	if (is_null($this->Weight)) $weight = 'NULL'; else $weight = $this->Weight;
	if (is_null($this->MSRP)) $msrp = 'NULL'; else $msrp = $this->MSRP;
	if (is_null($this->MAP)) $map = 'NULL'; else $map = $this->MAP;
	if (is_null($this->WholesalePrice)) $wholesale = 'NULL'; else $wholesale = $this->WholesalePrice;
	if (is_null($this->Length)) $length = 'NULL'; else $length = $this->Length;
	if (is_null($this->Width)) $width = 'NULL'; else $width = $this->Width;
	if (is_null($this->Height)) $height = 'NULL'; else $height = $this->Height;
	
	// Calculate dimensional weight if we have dimensions
	$IsOversizeGround = False;
	$FreightOnly = False;
	if (is_null($this->InsideDeliveryCharge) && is_null($this->OutsideDeliveryCharge)) {
		if (!empty($this->Length) && !empty($this->Width) && !empty($this->Height)) {
			$LengthFloat = (float) $this->Length;
			$WidthFloat = (float) $this->Width;
			$HeightFloat = (float) $this->Height;
			if (empty($this->Weight)) $WeightFloat = 0;
			else $WeightFloat = (float) $this->Weight;
			if ($LengthFloat >= $WidthFloat && $LengthFloat >= $HeightFloat) {
				$OverLength = $LengthFloat;
				$Girth = (2 * $WidthFloat) + (2 * $HeightFloat);
			}
			if ($WidthFloat >= $LengthFloat && $WidthFloat >= $HeightFloat) {
				$OverLength = $WidthFloat;
				$Girth = (2 * $LengthFloat) + (2 * $HeightFloat);
			}
			if ($HeightFloat >= $WidthFloat && $HeightFloat >= $LengthFloat) {
				$OverLength = $HeightFloat;
				$Girth = (2 * $WidthFloat) + (2 * $LengthFloat);
			}
			if (!isset($Girth)) echo "Party ID {$partyID} sku {$supplierSKU} Girth not set\n";
			$OversizeDims = $OverLength + $Girth;
			if ($OversizeDims > 120) $IsOversizeGround = True;

			// If oversize then, compute dimensional weight
			$CubicInches = $LengthFloat * $WidthFloat * $HeightFloat;
			if ($CubicInches > 5184) {
				$DimensionalWeight = round($CubicInches / 166, 0);
				if ($DimensionalWeight > $WeightFloat) {
					if ($DimensionalWeight > 3 * $WeightFloat) $weight = 3 * $WeightFloat;
					else $weight = $DimensionalWeight;
				}
			}

			// See if a package must ship via freight
			if ($OverLength > 84) $FreightOnly = True;
		}
		if ($weight > 150 || $FreightOnly) $this->OutsideDeliveryCharge = 125;
		else {
			if ($IsOversizeGround) $this->OutsideDeliveryCharge = 65;
		}
	}

	if (is_null($this->InsideDeliveryCharge)) $insideCharge = 'NULL'; else $insideCharge = $this->InsideDeliveryCharge;
	if (is_null($this->OutsideDeliveryCharge)) $outsideCharge = 'NULL'; else $outsideCharge = $this->OutsideDeliveryCharge;
	$restricted = ($this->Restricted ? 1 : 0);
	$returnable = ($this->Returnable ? 1 : 0);
	$refurbished = ($this->Refurbished ? 1 : 0);
	$qty5 = ($this->TotalQuantity > 4 ? 1 : 0);

	if (!empty($this->SaveProductSQL)) $this->SaveProductSQL .= ",";
	$this->SaveProductSQL .= " ({$partyID}, {$supplierSKU}, {$manufacturerSKU}, '{$this->CleanMfrSKU}', {$upcCode}, {$manufacturerName}, {$ofbizMfrName}, {$manufacturerID}, {$productName}, {$supplierProductName}, {$marketingDescription}, {$technicalDescription}, {$this->SupplierPrice}, {$weight}, {$msrp}, {$map}, {$wholesale}, {$insideCharge}, {$outsideCharge}, {$categories}, {$this->CategoryID}, {$length}, {$width}, {$height}, {$restricted}, {$returnable}, {$refurbished}, {$warranty}, {$mainImage}, {$alternateImage}, {$imageReferrer}, now(), {$this->LoadID}, {$qty5})";
	$this->SaveCounter++;
	if (!is_null($this->InventoryData)) {
		foreach($this->InventoryData as $Facility => $Inventory) {
			if (!empty($this->SaveInventorySQL)) $this->SaveInventorySQL .= ",";
			$facility = $this->FeedsDbObj->EscapeField($Facility);
			$etaDATE = $this->FeedsDbObj->EscapeField($Inventory['ETADATE']);
			if (is_null($Inventory['AVAILTOPROMISE'])) $availToPromise = 'NULL'; else $availToPromise = $Inventory['AVAILTOPROMISE'];
			$TimeStamp = $Inventory['TIMESTAMP'];
			$this->SaveInventorySQL .= " ({$partyID}, {$supplierSKU}, {$facility}, {$etaDATE}, {$Inventory['QTYONHAND']}, {$availToPromise}, {$this->LoadID}, '{$TimeStamp}')";
		}
	}

	if (!is_null($this->VirtualInventory)) {
		$facility = $this->FeedsDbObj->EscapeField(substr("Virtual{$this->PartyID}", 0, 20));
		if (!empty($this->SaveInventorySQL)) $this->SaveInventorySQL .= ",";
		$this->SaveInventorySQL .= " ({$partyID}, {$supplierSKU}, {$facility}, NULL, {$this->VirtualInventory}, NULL, {$this->LoadID}, '{$this->StartDateTime}')";
	}

	if ($this->SaveCounter >= 100) {
		$this->ExecuteSaveSQL();
		$this->SaveCounter = 0;
		$this->SaveProductSQL = "";
		$this->SaveInventorySQL = "";
	}
}

private function ExecuteSaveSQL() {

	if ($this->SaveCounter == 0) return;
	$this->SaveProductSQL = "insert into Supplier_Product_Master (Supplier_Party_ID, Supplier_SKU, Manufacturer_SKU, Clean_Manufacturer_SKU, UPC_Code, Supplier_Mfr_Name, Ofbiz_Mfr_Name, Manufacturer_ID, Product_Name, Supplier_Product_Name, Marketing_Description, Technical_Description, Cost, Weight, MSRP, MAP, Wholesale_Price, Inside_Delivery_Charge, Outside_Delivery_Charge, Full_Category, Category_ID, Length, Width, Height, Restricted_Item, Returnable, Refurbished, Warranty, Main_Image_URL, Alternate_Image_URL, Referrer_URL, Last_Updated_Date, Load_ID, Is_Qty5_Supplier) values" . $this->SaveProductSQL . " on duplicate key update Manufacturer_SKU = values(Manufacturer_SKU), Clean_Manufacturer_SKU = values(Clean_Manufacturer_SKU), UPC_Code = values(UPC_Code), Supplier_Mfr_Name = values(Supplier_Mfr_Name), Ofbiz_Mfr_Name = values(Ofbiz_Mfr_Name), Manufacturer_ID = values(Manufacturer_ID), Product_Name = values(Product_Name), Supplier_Product_Name = values(Supplier_Product_Name), Marketing_Description = values(Marketing_Description), Technical_Description = values(Technical_Description), Cost = values(Cost), Weight = values(Weight), MSRP = values(MSRP), MAP = values(MAP), Wholesale_Price = values(Wholesale_Price), Inside_Delivery_Charge = values(Inside_Delivery_Charge), Outside_Delivery_Charge = values(Outside_Delivery_Charge),Full_Category = values(Full_Category), Category_ID = values(Category_ID), Full_Category = values(Full_Category), Length = values(Length), Width = values(Width), Height = values(Height), Restricted_Item = values(Restricted_Item), Returnable = values(Returnable), Refurbished = values(Refurbished), Warranty = values(Warranty), Main_Image_URL = values(Main_Image_URL), Alternate_Image_URL = values(Alternate_Image_URL), Referrer_URL = values(Referrer_URL), Last_Updated_Date = now(), Load_ID = values(Load_ID), Is_Qty5_Supplier = values(Is_Qty5_Supplier)";
	$this->FeedsInsertObj->Query($this->SaveProductSQL);
	if (!empty($this->SaveInventorySQL)) {
		$this->SaveInventorySQL = "insert into Supplier_Inventory (Supplier_Party_ID, Supplier_SKU, Facility_ID, ETA_Date, Quantity_On_Hand, Available_To_Promise, Load_ID, Last_Updated_Date) values" . $this->SaveInventorySQL . " on duplicate key update ETA_Date = values(ETA_Date), Quantity_On_Hand = values(Quantity_On_Hand), Available_To_Promise = values(Available_To_Promise), Load_ID = values(Load_ID), Last_Updated_Date = values(Last_Updated_Date)";
		$this->FeedsInsertObj->Query($this->SaveInventorySQL);
	}
}

// MSRP/MAP and Mini feed save
public function SaveMini() {

	// Check for required fields
	if (is_null($this->ManufacturerSKU)) trigger_error("Manufacturer SKU is required", E_USER_ERROR);
	if (is_null($this->CleanMfrSKU)) trigger_error("Clean Manufacturer SKU is required", E_USER_ERROR);
	if (is_null($this->ManufacturerID)) trigger_error("Manufacturer id is required", E_USER_ERROR);
	if ($this->MiniFeed) {
		if (is_null($this->SupplierPrice)) $this->SetError("Supplier price is required", 999);
		if (!empty($this->WholesalePrice) && $this->WholesalePrice <= $this->SupplierPrice) $this->SetError("Wholesale price must be greater than Supplier price", 999);
	}

	$partyID = $this->FeedsDbObj->EscapeField($this->PartyID);
	$manufacturerID = $this->FeedsDbObj->EscapeField($this->ManufacturerID);
	$manufacturerSKU = $this->FeedsDbObj->EscapeField($this->ManufacturerSKU);
	$supplierSKU = $this->FeedsDbObj->EscapeField($this->SupplierSKU);
	$productName = $this->FeedsDbObj->EscapeField($this->ProductName);
	if (is_null($this->SupplierPrice)) $supplierPrice = 'NULL'; else $supplierPrice = $this->SupplierPrice;
	$upcCode = $this->FeedsDbObj->EscapeField($this->UPC);
	if (is_null($this->InventoryData)) {
		$quantity = 'NULL';
		$facilityID = 'NULL';
		$InventoryTime = 'NULL';
	} else {
		$Facility = key($this->InventoryData);
		$InventoryData = current($this->InventoryData);
		$facilityID = $this->FeedsDbObj->EscapeField($Facility);
		$quantity = $InventoryData["QTYONHAND"];
		$InventoryTime = $InventoryData["TIMESTAMP"];
	}
	if (is_null($this->MSRP)) $msrp = 'NULL'; else $msrp = $this->MSRP;
	if (is_null($this->MAP)) $map = 'NULL'; else $map = $this->MAP;
	if (is_null($this->WholesalePrice)) $wholesale = 'NULL'; else $wholesale = $this->WholesalePrice;
	if (is_null($this->PromoPrice)) $promo = 'NULL'; else $promo = $this->PromoPrice;
	$returnable = ($this->Returnable ? 1 : 0);

	if (!empty($this->SaveProductSQL)) $this->SaveProductSQL .= ",";
	$this->SaveProductSQL .= " ({$partyID}, {$manufacturerID}, {$manufacturerSKU}, '{$this->CleanMfrSKU}', {$supplierSKU}, {$productName}, {$supplierPrice}, {$upcCode}, {$quantity}, {$msrp}, {$map}, {$wholesale}, {$promo}, {$returnable}, {$facilityID}, '{$InventoryTime}')";
	$this->SaveCounter++;

	if ($this->SaveCounter >= 100) {
		$this->ExecuteSaveMiniSQL();
		$this->SaveCounter = 0;
		$this->SaveProductSQL = "";
	}
}

public function ExecuteSaveMiniSQL() {

	if ($this->SaveCounter == 0) return;
	$this->SaveProductSQL = "insert into Mini_MSRP (Supplier_Party_ID, Manufacturer_ID, Manufacturer_SKU, Clean_Manufacturer_SKU, Supplier_SKU, Product_Name, Cost, UPC_Code, Quantity_On_Hand, MSRP, MAP, Wholesale_Price, Promo_Price, Returnable, Facility_ID, Last_Updated_Date) values" . $this->SaveProductSQL;
	$this->FeedsInsertObj->Query($this->SaveProductSQL);
}



}