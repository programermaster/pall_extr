<?php
include '../lib/Config.php';
include '../lib/cURL.php';
include '../lib/Extractor.php';
include '../lib/parseCSV.php';
include '../lib/Database.php';
include '../lib/MysqlStatement.php';
include '../lib/Registry.php';

class AdditionalContent {

	// Product Attributes
	private $SKU;				        // Manufacturers part number
	private $UPC;						// Items UPC code if valud and not a dupe, else NULL
	private $GTIN;						// Items GTIN (EAN) code if valud and not a dupe, else NULL
	private $ManufacturerName;			// Name of the products manufacturer, as specified by the supplier
	private $ProductName;				// Product name (short name)
	private $MarketingDescription;		// Marketing description, hopefully long!
	private $TechnicalDescription;		// Technical description, typically specs
	private $MAP;						// MAP price for the product, NULL if none specified
	private $Weight;					// Product weight (NULL is none specified or 0)
	private $MSRP;						// The MSRP for the product, NULL if none specified
	private $MainCategory;				// MainCategory product is in
	private $SubCategory;				// SubCategory product is in
	private $SubCategory2;				// SubCategory2 product is in
	private $SubCategory3;				// SubCategory3 product is in
	private $Length;					// Length dimension
	private $Width;						// Width dimension
	private $Height;					// Height dimension
	private $MainImage;					// URL to the image file, if any
	private $Warranty;					// Warranty, if any
	private $ProductID;			        // Product ID from ofbiz database, NULL if new product
	private $ManufacturerID;			// Manufacturer ID from ofbiz database, NULL if new manufacturer
	private $MD5;						// md5 Hash
	private $Content_Source_ID;			// Content Source ID

	public function __construct($options = array()) {

		if (!empty($options['URL'])) $this->URL                         = $options['URL'];
		if (!empty($options['Name'])) $this->Name                       = $options['Name'];
		if (!empty($options['Type'])) $this->Type                       = $options['Type'];
		if (!empty($options['Status'])) $this->Status                   = $options['Status'];
		if (!empty($options['Script'])) $this->Script                   = $options['Script'];
		if (!empty($options['Server'])) $this->Server                   = $options['Server'];
		if (!empty($options['Frequency'])) $this->Frequency             = $options['Frequency'];
		if (!empty($options['Manufacturer_ID'])) $this->Manufacturer_ID = $options['Manufacturer_ID'];
		$this->Status = 'ok';

		// Setup db object for using class on host8
		#$this->dbObject = Config::GetDBName(Config::DATABASE_MYDB); 

		// Setup db object for using class on scrape servers	
		$dbObject = Registry::getDB('additional_content');
		$NoUTF8   = FALSE;
		$port     = NULL;
		$socket   = NULL;
		$dbFlags  = NULL;
		$host     = 'localhost';
		$database = 'additional_content';
		$user     = 'root';
		$password = 'root';
		$this->dbObject = new Database($database, $host, $user, $password, $port, $socket, $dbFlags, $NoUTF8);
		Registry::set('additional_content', $this->dbObject);

		// Check if Source is already defined in our db
		$sqlStmt = "SELECT Source_ID FROM Additional_Content_Sources WHERE Name = '{$this->Name}'";
		$results = $this->dbObject->SelectAndReturnRow($sqlStmt, true);

		if (empty($results["Source_ID"])) {
			// Insert new source
			$sqlStmt = "INSERT INTO Additional_Content_Sources (Manufacturer_ID, URL, Name, Type, Status, Script, Server, Frequency) VALUES ('{$this->Manufacturer_ID}', '{$this->URL}', '{$this->Name}', '{$this->Type}', '{$this->Status}', '{$this->Script}', '{$this->Server}', '{$this->Frequency}')";
			$this->Content_Source_ID = $this->dbObject->InsertRow($sqlStmt, true);
		}else{
			$this->Content_Source_ID = $results["Source_ID"];
		}

		$list = array(
			'SKU',
			'UPC',
			'GTIN',
			'Manufacturer',
			'Name',
			'MarketingDescr',
			'Manufacturer_ID',
			'TechnicalDescr',
			'MAP',
			'Weight',
			'MSRP',
			'MainCategory',
			'Categories',
			'Warranty',
			'SubCategory',
			'SubCategory2',
			'SubCategory3',
			'Length',
			'Width',
			'Height',
			'length',
			'width',
			'height',
			'MainImage',
			'Product_ID',
			'Ofbiz_Mfr_ID',
			'MD5',
			'Content_Source_ID',
			'CREATED_DATE',
			'UPDATED_DATE',
			'manufacturer',
			'title',
			'description',
			'technical',
			'mincategory',
			'subcategory',
			'subcategory2',
			'subcategory3',
			'mainimage',
			'weight'
		);

		// Get all additional fields that are already in db
		$sqlStmt = "SELECT Field_Name FROM Additional_Content_Fields";
		$results = $this->dbObject->SelectAndReturnAllRows($sqlStmt, true);

		$additional_fields_list = array();
		// Add additional fields we have into list array
		foreach ($results as $result) {
			$list[] = $result['Field_Name'];
			$additional_fields_list[] = $result['Field_Name'];
		}
		$this->fields = $list;
		$this->additional_fields = $additional_fields_list;
	}
	public function save($prod = array(), $prod_additional_fields = array()) {


		// set marketing description if only technical
		if (empty($prod['description']) && !empty($prod['technical'])) {
			$prod['description'] = $prod['technical'];
			$prod['technical'] = '';
		}

		// remove phone numbers and emails
		if (!empty($prod['description'])) $prod['description'] = preg_replace("/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}[^.]*\b/",' ',$prod['description']);
		if (!empty($prod['technical'])) $prod['technical'] = preg_replace("/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}[^.]*\b/",' ',$prod['technical']);
		if (!empty($prod['description'])) $prod['description'] = preg_replace("/\b\d{3,3}\D\d{3,3}\D\d{3,4}\b/",' ',$prod['description']);
		if (!empty($prod['technical'])) $prod['technical'] = preg_replace("/\b\d{3,3}\D\d{3,3}\D\d{3,4}\b/",' ',$prod['technical']);

		// reformat HTML
		if (!empty($prod['description'])) $prod['description'] = $this->reformat_html ($prod['description']);
		if (!empty($prod['technical'])) $prod['technical'] = $this->reformat_html ($prod['technical']);

		// trim & control char remove
		foreach ($prod as $key=>$value) {
			if (!empty($prod[$key])) {
				$prod[$key] = preg_replace('/^\s*|\s*$/s','', $prod[$key]);
				$prod[$key] = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $prod[$key]);
			}
		}

		// Get values for product attributes from a given array
		$this->SKU                  = @trim($prod['SKU']);
		$this->UPC                  = @trim($prod['UPC']);
		$this->GTIN                 = @trim($prod['GTIN']);
		$this->ManufacturerName     = @trim($prod['manufacturer']);
		$this->ProductName          = @trim($prod['title']);
		$this->MarketingDescription = @trim($prod['description']);
		$this->TechnicalDescription = @trim($prod['technical']);
		$this->MainCategory         = @trim($prod['mincategory']);
		$this->SubCategory          = @trim($prod['subcategory']);
		$this->SubCategory2         = @trim($prod['subcategory2']);
		$this->SubCategory3         = @trim($prod['subcategory3']);
		$this->MainImage            = @trim($prod['mainimage']);
		$this->Warranty             = @trim($prod['warranty']);
		$this->Weight            	= @trim($prod['weight']);
		$this->MSRP            		= @trim($prod['msrp']);
		$this->MAP            		= @trim($prod['map']);
		$this->Length            	= @trim($prod['length']);
		$this->Width            	= @trim($prod['width']);
		$this->Height            	= @trim($prod['height']);

		// Check for required fields	
		if (empty($this->SKU)) {
			$this->error("ERROR: NO SKU", $prod);
			return false;
		}

		if (empty($this->ManufacturerName)) {
			$this->error("ERROR: NO MANUFACTURER", $prod);
			return false;
		}

		if (empty($this->ProductName)) {
			$this->error("ERROR: NO TITLE", $prod);
			return false;
		}

		if (empty($this->MarketingDescription)) {
			$this->error("ERROR: NO MARKETING DESCRIPTION", $prod);
			return false;
		}

		if (empty($this->MainCategory)) {
			$this->error("ERROR: NO MAIN CATEGORY", $prod);
			return false;
		}

		// Escape required fields
		$sku                  = $this->dbObject->EscapeField($this->SKU);
		$manufacturerName     = $this->dbObject->EscapeField($this->ManufacturerName);
		$productName          = $this->dbObject->EscapeField($this->ProductName);
		$marketingDescription = $this->dbObject->EscapeField($this->MarketingDescription);
		$mainCategory         = $this->dbObject->EscapeField($this->MainCategory);


		// Verify if required fields have more than 2 characters
		$this->verify_string($sku, $prod);
		$this->verify_string($manufacturerName, $prod);
		$this->verify_string($productName, $prod);
		$this->verify_string($marketingDescription, $prod);
		$this->verify_string($mainCategory, $prod);

		// Escape other fields
		$technicalDescription = empty($this->TechnicalDescription) ? "" : $this->TechnicalDescription;
		$upcCode              = empty($this->UPC) ? "" : $this->UPC;
		$gtinCode             = empty($this->GTIN) ? "" : $this->GTIN;
		$mainImage            = empty($this->MainImage) ? "" : $this->MainImage;
		$warranty             = empty($this->Warranty) ? "" : $this->Warranty;
		$weight               = empty($this->Weight) ? "" : $this->Weight;
		$msrp                 = empty($this->MSRP) ? "" : $this->MSRP;
		$map                  = empty($this->MAP) ? "" : $this->MAP;
		$length               = empty($this->Length) ? "" : $this->Length;
		$width                = empty($this->Width) ? "" : $this->Width;
		$height               = empty($this->Height) ? "" : $this->Height;
		$subCategory          = empty($this->SubCategory) ? "" : $this->SubCategory;
		$subCategory2         = empty($this->SubCategory2) ? "" : $this->SubCategory2;
		$subCategory3         = empty($this->SubCategory3) ? "" : $this->SubCategory3;

		// strip html and make string uppercase
		$sku                  = strtoupper(strip_tags($sku));
		$clean_sku            = $this->CleanName($this->StripInvalidCharacters($sku));

		// set to null if no value or empty string
		$msrp                 = !empty($msrp) ? "'$msrp'" : "NULL";
		$map                  = !empty($map) ? "'$map'" : "NULL";
		$upcCode              = !empty($upcCode) ? "'$upcCode'" : "NULL";
		$gtinCode             = !empty($gtinCode) ? "'$gtinCode'" : "NULL";
		$subCategory          = !empty($subCategory) ? "'$subCategory'" : "";
		$subCategory2         = !empty($subCategory2) ? "'$subCategory2'" : "";
		$subCategory3         = !empty($subCategory3) ? "'$subCategory3'" : "";

		$arr = array();
		if (!empty($mainCategory)) {
			$arr[] = trim(str_replace(">", '',$mainCategory));
		}
		if (!empty($subCategory)) {
			$arr[] = trim(str_replace(">", '',$subCategory));
		}
		if (!empty($subCategory2)) {
			$arr[] = trim(str_replace(">", '',$subCategory2));
		}
		if (!empty($subCategory3)) {
			$arr[] = trim(str_replace(">", '',$subCategory3));
		}
		$categories           = implode(" > ",$arr);
		$categories           = str_replace("'",'',$categories);
		$length               = !empty($length) ? "'$length'" : "NULL";
		$width                = !empty($width) ? "'$width'" : "NULL";
		$height               = !empty($height) ? "'$height'" : "NULL";
		$weight               = !empty($weight) ? "'$weight'" : "NULL";
		$mainImage            = !empty($mainImage) ? "'$mainImage'" : "NULL";
		$warranty             = !empty($warranty) ? "'$warranty'" : "NULL";
		$technicalDescription = !empty($technicalDescription) ? "$technicalDescription" : "NULL";

		$md5 = md5($gtinCode.$upcCode.$manufacturerName.$productName.$marketingDescription.$technicalDescription.$map.$weight.$msrp.$mainCategory.$subCategory.$subCategory2.$subCategory3.$length.$width.$height.$mainImage);

		// SQL query for inserting a new product
		$this->SaveProductSQL = "INSERT INTO `Additional_Content` 
								(	`SKU`, 
									`Clean_SKU`,
									`UPC`, 
									`GTIN`,									
									`Manufacturer_ID`,
									`Manufacturer`,
									`Name`, 
									`MarketingDescr`, 
									`TechnicalDescr`, 
									`MAP`, 
									`Weight`, 
									`MSRP`, 
									`Categories`,
									`Length`, 
									`Width`, 
									`Height`, 
									`MainImage`, 
									`Warranty`,
									`Product_ID`, 									
									`MD5`, 
									`Content_Source_ID`, 
									`CREATED_DATE`, 
									`UPDATED_DATE`
								) 
								VALUES 
								(
									{$sku},
									'{$clean_sku}', 
									$upcCode, 
									$gtinCode,
									'{$this->Manufacturer_ID}',
									{$manufacturerName}, 
									{$productName}, 
									{$marketingDescription}, 
									'$technicalDescription', 
									$map, 
									$weight, 
									$msrp, 
									'{$categories}',
									$length, 
									$width, 
									$height, 
									$mainImage, 
									$warranty,
									NULL,									
									'{$md5}',
									'{$this->Content_Source_ID}', 
									now(), 
									NULL
								)";

		echo "\r\n"."\r\n"."\r\n" .  $this->SaveProductSQL ."\r\n"."\r\n"."\r\n";

		if (!empty($this->SKU)) {
			// Check if product exists in a db
			$sqlStmt = "SELECT SKU, MD5 FROM Additional_Content WHERE SKU = '{$this->SKU}' AND `Content_Source_ID`= '{$this->Content_Source_ID}'";
			$results = $this->dbObject->SelectAndReturnRow($sqlStmt, true);
			if (empty($results["SKU"])) {
				// Save new product
				$this->External_ID = $this->dbObject->InsertRow($this->SaveProductSQL, true);
				// Save new additional fields
				$this->additional_field_names($prod_additional_fields, $this->fields, $this->additional_fields, $this->External_ID);
				// Save values for existing additional fields
				$this->additional_field_values($prod_additional_fields, $this->additional_fields, $this->External_ID);

				print "SAVING {$this->SKU}\n";
			}else{
				// Check if content has changed...if so let's update the record
				if ($results["MD5"] != $md5) {
					// SQL query for updating an old product
					$UpdateProductSQL = "UPDATE `Additional_Content` SET	
												`UPC`            = $upcCode,
												`GTIN`           = $gtinCode,
												`Manufacturer`   = {$manufacturerName},
												`Name`           = {$productName}, 
												`MarketingDescr` = {$marketingDescription}, 
												`TechnicalDescr` = '$technicalDescription', 
												`MAP`            = $map, 
												`Weight`         = $weight, 
												`MSRP`           = $msrp, 
												`Categories`     = '{$categories}', 
												`Length`         = $length, 
												`Width`          = $width, 
												`Height`         = $height, 
												`MainImage`      = $mainImage,
												`Warranty`       = $warranty,									
												`MD5`            = '{$md5}',
												`UPDATED_DATE`   = now() WHERE SKU = '{$this->SKU}' AND `Content_Source_ID`= '{$this->Content_Source_ID}'";

					echo "\r\n"."\r\n"."\r\n" .  $UpdateProductSQL ."\r\n"."\r\n"."\r\n";

					$this->dbObject->UpdateRow($UpdateProductSQL);
					print "UPDATING {$this->SKU}\n";

				}
			}
			print "PRODUCT {$this->SKU} PROCESSED\n";
		}
	}
	// logs extraction error to a file
	private function error ($text, $prod) {
		print "$text\n";
		print_r($prod);
	}
	private function verify_string($string, $prod){
		if(strlen($string) < 2){
			$this->error("String {$string} is less that 2 charachters", $prod);
			return false;
		}
	}
	private function additional_field_names($prod_additional_fields, $list, $additional_fields, $content_id){

		// Filter array
		$new_prod_additional_fields = array();
		foreach ($prod_additional_fields as $key => $value) {
			if (!is_array($value)) {
				$new_prod_additional_fields[$key] = $value;
			}
		}

		// Find diff, that is fields that are in an array we get from scrape script, but not in a default fields list
		$array1 = array_keys($new_prod_additional_fields);
		$array2 = $list;
		$diffs  = array_diff($array1, $array2);
		// Add field names 
		foreach ($diffs as $diff) {
			$this->Field_Name          = $diff;

			$sqlStmt = "SELECT Field_Name FROM Additional_Content_Fields WHERE Field_Name = '{$this->Field_Name}' AND `External_ID`= '{$content_id}'";
			$results = $this->dbObject->SelectAndReturnRow($sqlStmt, true);
			if (empty($results["Field_Name"])) {
				$sqlStmt                   = "INSERT INTO Additional_Content_Fields (`Field_Name`) VALUES ('{$this->Field_Name}')";
				$this->Field_ID            = $this->dbObject->InsertRow($sqlStmt, true);
			}
			$this->fields[]            = $diff;
			$this->additional_fields[] = $diff;

		}
	}
	private function additional_field_values($prod_additional_fields, $additional_fields, $content_id){

		// Filter array
		$new_prod_additional_values = array();
		foreach ($prod_additional_fields as $key => $value) {
			if (!is_array($value)) {
				$new_prod_additional_values[$key] = $value;
			}
		}
		// Compute the intersection of arrays using keys for comparison
		$arr1 = $new_prod_additional_values;
		$arr2 = array_flip($additional_fields);
		$intersect_keys  = array_intersect_key($arr1, $arr2);
		// Add values
		foreach ($intersect_keys as $key=>$value) {
			$sqlStmt = "SELECT Field_ID FROM Additional_Content_Fields WHERE Field_Name = '{$key}'";
			$results = $this->dbObject->SelectAndReturnRow($sqlStmt, true);
			if (!empty($new_prod_additional_values[$key])) {

				$sqlStmt = "SELECT Field_ID FROM Additional_Content_Values WHERE Field_ID = '{$results['Field_ID']}' AND `External_ID`= '{$content_id}'";
				$results1 = $this->dbObject->SelectAndReturnRow($sqlStmt, true);
				if (empty($results1["Field_ID"])) {
					$sqlStmt = "INSERT INTO Additional_Content_Values (`External_ID`,`Content_Source_ID`, `Field_ID`, `Value`) VALUES ('{$content_id}', '{$this->Content_Source_ID}', '{$results['Field_ID']}', '{$new_prod_additional_values[$key]}')";
					$this->dbObject->InsertRow($sqlStmt);
				}
			}
		}
	}
	// reformat HTML for display on site
	private function reformat_html($html) {
		$html = preg_replace(array(
			'/<h\d[^<>\/]*>/i',
			'/<strong[^<>\/]*>/i',
			'/<li[^<>\/]*>/i',
			'/<ul[^<>\/]*>/i',
			'/<p[^<>\/]*>/i',
			'/<br[^<>]*>/i',
			'/<\/div>/i',
			'/<\/\s*h\d*\s*>/i',
			'/<\/\s*li[^<>]*>/i',
			'/<\/\s*ul[^<>]*>/i',
			'/<\/\s*p\s*>/i',
			'/<tr[^<>]*>/i',
			'/<\/strong[^<>\/]*>/i'
		), array (
			'BHAHAHA',
			'BHAHAHA',
			'BLILILI',
			'BULULUL',
			'BPEPEPE',
			'BRBRBR',
			'BRBRBR',
			'EHAHAHA',
			'ELILILI',
			'EULULUL',
			'EPEPEPE',
			'BRBRBR',
			'EBEBEBE'
		), $html);

		$html = preg_replace('/<[^<>]*>/', ' ', $html);
		$html = preg_replace('/&nbsp;/i', ' ', $html);
		$html = preg_replace('/Add to Cart/i', ' ', $html);
		$html = preg_replace('/\s+/', ' ', $html);
		$html = preg_replace('/BRBRBR\s*BRBRBR/', 'BRBRBR', $html);
		$html = preg_replace('/BRBRBR\s*BRBRBR/', 'BRBRBR', $html);
		$html = preg_replace('/BRBRBR\s*BRBRBR/', 'BRBRBR', $html);
		$html = preg_replace('/BRBRBR\s*BRBRBR/', 'BRBRBR', $html);

		$html = preg_replace(array(
			'/BRBRBR/',
			'/BHAHAHA/',
			'/BLILILI/',
			'/BULULUL/',
			'/BPEPEPE/',
			'/EHAHAHA/',
			'/EBEBEBE/',
			'/ELILILI/',
			'/EULULUL/',
			'/EPEPEPE/',
			'/^\s*|\s*$/s',
			'/&amp;/',
			'/&quot;/',
			'/^\s*<br \/>/s',
			'/<br \/>\s*$/s'
		), array (
			"<br />\n",
			'<b>',
			'<li>',
			'<ul>',
			"<br />\n",
			'</b><br />',
			'</b>',
			'</li>',
			'</ul>',
			'',
			'',
			'&',
			"'",
			'',
			''
		), $html);

		if (
			preg_match('/<ul>/', $html) &&
			!preg_match('/<\/ul>/', $html)
		) $html .= "</ul>";

		return ($html);

	}
	public function delete_content(){
		$sqlStmt1 = "DELETE FROM Additional_Content WHERE Content_Source_ID = '{$this->Content_Source_ID}'";
		$this->dbObject->Query($sqlStmt1);

		$sqlStmt2 = "DELETE FROM Additional_Content_Values WHERE Content_Source_ID = '{$this->Content_Source_ID}'";
		$this->dbObject->Query($sqlStmt2);
	}
	public function StripInvalidCharacters($Unclean) {
		return preg_replace('/[^(\x20-\x7E)]*/', '', $Unclean);
	}
	public function CleanName($Name) {
		return strtoupper(preg_replace("/[^a-zA-Z0-9\+=]/", "", $Name));
	}

}