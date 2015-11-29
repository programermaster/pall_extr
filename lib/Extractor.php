<?php

class Extractor {
	var $init_options;
	var $err_cnt;
	var $pdo;
	var $sleep;
	var $random_ip;
	var $bind_ip;
	var $ips;
	var $userpwd;
	var $headers;
	var $scrape_report;
	var $remove_sentence;
	var $curl;
	var $xls;
	var $csvout;
	var $tmpdir;
	var $db_type;
	var $db_name;
	var $proxy;
	var $db_username = 'root';
	var $db_password = 'kimosvonda75';

	var $logged_in = false;


	
	
	// set the ips to bind to
	function set_ips($value) {
		$this->ips = $value;
	}

	// set sleep for get_page 
	function set_sleep($value) {
		$this->sleep = $value;
	}

	// initialize extractor, setup sqlite database table if missing
	function Extractor($options = array()) {
		$this->curl = new cURL;
		$this->csvout = new parseCSV();
		//$this->xls = new SpreadsheetExcelReader();
		$this->scrape_report = Config::SCRAPE_REPORT;
		$this->remove_sentence = array('Authorized Reseller', 'HP Care Pack Services', 'CHECKOUT', 'rebate','promotional','requires authorization','authorization required','www','http','reseller');
		if (!empty($options['proxy'])) $this->proxy = $options['proxy'];
		if (!empty($options['headers'])) $this->headers = true;
		if (!empty($options['sleep'])) $this->sleep = $options['sleep'];
		if (!empty($options['userpwd'])) $this->userpwd = $options['userpwd'];
		if (!empty($options['bind_ip'])) $this->bind_ip = $options['bind_ip'];
		if (!empty($options['random_ip'])) $this->random_ip = $options['random_ip'];
		if (!empty($options['ips'])) {
			$this->random_ip = true;
			$this->ips = $options['ips'];
		}
		$this->init_options = $options;
		$this->err_cnt=0;

		// setup temporary directory for cookies and database
		if (empty($options['tmpdir'])) { 
			$this->tmpdir = Config::getTmpDir();
			if (!file_exists($this->tmpdir)) $this->tmpdir = "";
		}
		else 
			$this->tmpdir = $options['tmpdir'];

		$this->tmpdir .= "extraction";
		if (!file_exists($this->tmpdir)) mkdir($this->tmpdir);

		if (!empty($options['site'])) {
			$tmpdir_suffix = preg_replace("/\W/", "_", $options['site']);
			$this->tmpdir .= "/".$tmpdir_suffix;
			if (!file_exists($this->tmpdir)) mkdir($this->tmpdir);
		}
		$this->tmpdir .= "/";

		if (! empty ( $options ['mysql'] ) && ! empty ( $options ['mysql_db'] )) {
			$this->db_type = 'mysql';
			$this->db_name = $options ['mysql_db'];
			if (isset ( $options ['mysql_username'] ))
				$this->db_username = $options ['mysql_username'];
			if (isset ( $options ['mysql_password'] ))
				$this->db_password = $options ['mysql_password'];

			$havedb = $this->database_exists ( $this->db_name );
			if (! $havedb)
				$this->pdo->exec ( "CREATE DATABASE IF NOT EXISTS {$this->db_name};" );
				
			$this->pdo = new PDO ( 'mysql:host=localhost;dbname=' . $this->db_name, $this->db_username, $this->db_password );
			
		} else {
			$this->db_type = 'sqlite';
			$this->db_name = 'db';
			
			if (file_exists ( $this->tmpdir . $this->db_name ))
				$havedb = true;
			else
				$havedb = false;
			
			$this->pdo = new PDO ( 'sqlite:' . $this->tmpdir . $this->db_name );
		}
		
		if (! $havedb) {
			$this->pdo->exec ( "
				CREATE TABLE errors (error varchar(255), dump text, page longtext, url varchar(255));
				CREATE TABLE msrp (sku varchar(100), price varchar(50));
				CREATE TABLE urls(url varchar(255), type varchar(20), visited int);
				CREATE TABLE visited(url varchar(255));
				CREATE TABLE vars(`key` varchar(255), `value` varchar(255));
				INSERT INTO vars(`key`,`value`) VALUES ('csv_file', '" . $this->tmpdir . "out.csv');
				INSERT INTO vars(`key`,`value`) VALUES ('username', '');
				INSERT INTO vars(`key`,`value`) VALUES ('password', '');
				INSERT INTO vars(`key`,`value`) VALUES ('multi', '1');
				CREATE TABLE cache (`key` varchar(255), `value` varchar(255), `time` int(11));
			" );
		}
		
		$this->pdo->setAttribute ( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		if (empty ( $options ['noreset'] )) {
			$fp = fopen ( $this->tmpdir . "cookie.txt", "w" );
			fclose ( $fp );
			$this->pdo->exec ( "delete from visited" );
			$this->pdo->exec ( "delete from urls" );
			$this->pdo->exec ( "delete from msrp" );
			$this->pdo->exec ( "delete from errors" );
			if ($this->table_exists ( 'csv' ))
				$this->pdo->exec ( "update csv set processed=0" );
			if (! empty ( $options ['push_url'] ))
				$this->push_urls ( 'cat', array ($options ['push_url'] ) );
			$csv = fopen ( $this->get_var ( 'csv_file' ), "w" );
			fwrite ( $csv, "Supplier SKU,Manufacturer SKU,UPC,Manufacturer Name,Product Name,Marketing Description,Technical Description,Cost,Weight in pounds,MSRP,Minimum advertised price,Wholesale Price,Main Category,Sub Category 1,Sub Category 2,Sub Category 3,Length in inches,Width in inches,Height in inches,Returnable Flag,Refurbished Flag,Warranty,Image URL,Quantity on hand,ETA Date if out of stock,Virtual Warehouse\n" );
			fclose ( $csv );
		}
		
		// get vars from scrape configuration or from cache table, and send existing vars
		if (! empty ( $options ['site'] )) {
			$vars = array();
			$now = time ();
			if ($this->table_exists ( 'cache' )) {
				$res = $this->pdo->query ( "select * from cache" );
				$variables = array ();
				while ( $d = $res->fetch () ) {
					$obj = new stdClass ();
					$obj->key = $d ['key'];
					$obj->value = $d ['value'];
					$obj->time = $d ['time'];
					$variables [] = $obj;
				}
						
				if (! empty ( $variables ) && isset ( $variables [0]->time )) {
					$cache_time = $variables [0]->time;
					$diff = (int) $now - $cache_time;
					if ($diff < Config::SCRAPE_CACHE_TIME) {
						$vars = $variables;
					} else {
						$vars = $this->get_script_vars ( $options ['site'] );
						if (! empty ( $vars )) {
							foreach ( $vars as $v ) {
								if (! empty ( $v->key ) && in_array ( $v->key, array ('username', 'password', 'multi' ) )) {
									$this->pdo->exec ( "update vars set `value`='{$v->value}' where `key`='{$v->key}'" );
									$this->pdo->exec ( "update cache set `value`='{$v->value}', `time`='{$now}' where `key`='{$v->key}'" );
								}
							}
						}
					}
				} else {
					$vars = $this->get_script_vars ( $options ['site'] );
					if (! empty ( $vars )) {
						foreach ( $vars as $v ) {
							if (! empty ( $v->key ) && in_array ( $v->key, array ('username', 'password', 'multi' ) )) {
								$this->pdo->exec ( "update vars set `value`='{$v->value}' where `key`='{$v->key}'" );
								$this->pdo->exec ( "insert into cache (`key`, `value`, `time`) values ('{$v->key}', '{$v->value}', '{$now}')" );
							}
						}
					}
				}
			} else {
				$this->pdo->exec ( "create table if not exists cache (`key` varchar(255), `value` varchar(255), `time` int(11))" );
				$vars = $this->get_script_vars ( $options ['site'] );
				if (! empty ( $vars )) {
					$this->pdo->exec("delete from cache");
					foreach ( $vars as $v ) {
						if (! empty ( $v->key ) && in_array ( $v->key, array ('username', 'password', 'multi' ) )) {
							$this->pdo->exec ( "update vars set `value`='{$v->value}' where `key`='{$v->key}'" );
							$this->pdo->exec ( "insert into cache (`key`, `value`, `time`) values ('{$v->key}', '{$v->value}', '{$now}')" );
						}
					}
				}
			}
			if (DEBUG == TRUE)
				print_r ( $vars );
			
			return $vars;
		}
	}

	function get_tmpdir() {
		return $this->tmpdir;
	}

	function get_script_vars($site) {
		$vars = $this->get_page($this->scrape_report."?site=".$site,
			array(
				'userpwd'=>Config::SCRAPE_REPORT_LOGIN,
				'cookie_file'=>'cookie_extractor.txt'
		));
		if (preg_match("/>Login Form</", $vars)) {
			$this->scrape_tracker_login(); 
			$vars = $this->get_page($this->scrape_report."?site=".$site, array('cookie_file'=>'cookie_extractor.txt'));
		}

		return json_decode($vars);
	}
	
	function scrape_tracker_login() {
		$vars = $this->get_page ( "https://www.neobits.com/phpsecurenew/Login.php", array (
			'cookie_file' => 'cookie_extractor.txt', 
			'data' => array (
				'submitted' => '1', 
				'username' => '81650', 
				'password' => 'sc@cpd3v', 
				'Submit' => 'Submit' 
			) 
		));
	}
	
	// check if table exists
	function table_exists($table) {
		if ($this->db_type == "sqlite") {
			$res = $this->pdo->query ( "SELECT name FROM sqlite_master WHERE type='table' AND name='$table';" );
			$d = $res->fetch ();
			if (empty ( $d ['name'] ))
				return false;
			else
				return true;
		} else {
			$res = $this->pdo->query ( "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '{$this->db_name}' AND TABLE_NAME = '$table'" );
			$d = $res->fetch ();
			if (empty ( $d ['TABLE_NAME'] ))
				return false;
			else
				return true;
		}
	}

	// check if database exists
	function database_exists($name) {
		if ($this->db_type == "mysql") {
			$this->pdo = new PDO ( 'mysql:host=localhost', $this->db_username, $this->db_password );
			$res = $this->pdo->query ( "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$name'" );
			$d = $res->fetch ();
			if (empty ( $d ['SCHEMA_NAME'] ))
				return false;
			else
				return true;
		} else {
			NeobitsLogError ( "db type {$this->db_type} not handled in database_exists" );
		}
	}
	
	// export unprocessed csv items to data file
	// if a key has fn_ prefix, its the name of the function used to process the data in that key
	// the function should be located in the main php file
	function export_unprocessed($map, $table = 'csv') {
		print "exporting unprocessed\n";
		$sql = "select * from $table where processed=0";
		$res = $this->pdo->query ( $sql );
		
		$row = 0;
		while ( $data = $res->fetch () ) {
			$prod = array ();
			foreach ( $map as $key => $csv_key ) {
				if (empty ( $map ['fn_' . $key] ) && ! preg_match ( '/^fn_/', $key ))
					$prod [$key] = $data [$csv_key];
				elseif (! empty ( $map ['fn_' . $key] ))
					$prod [$key] = $map ['fn_' . $key] ( $data [$csv_key] );
			}
			$this->save_prod ( $prod, print_r ( $data, true ), 'csv' );
			if ($row % 1000 == 0 && DEBUG)
				print "exported $row rows\n";
			$row ++;
		}
		$res->closeCursor ();
		print "exported unprocessed\n";
	}

	// reset visited urls before rerun, but dont delete product urls. 
	function reset_urls() {
		$this->pdo->exec("update urls set visited=0");
		$this->pdo->exec("delete from visited");
	}

	function finish() {
		print "!!!end!!!";
	}

	// check if an url is visited, add it to the visited list
	function visited($url) {
		$sql = "select count(*) as cnt from visited where url=".$this->pdo->quote($url);
		$res = $this->pdo->query($sql) ;

		$d = $res->fetch();
		$res->closeCursor();
		if (empty($d['cnt'])) {
			$sth = $this->pdo->prepare("insert into visited(url) values(".$this->pdo->quote($url).")");
			$sth->execute();
			return false;
		} else {
			if (DEBUG) print "skipping $url, visited\n";
			return true;
		}
	}

	// push an array of $urls of $type (can be whatever) for processing
	function push_urls($type, $urls) {
		$cnt = 0;
		foreach ($urls as $url) {
			if (!preg_match('/^http/', $url)) continue;
			$res = $this->pdo->query("select count(*) as cnt from urls where url=".$this->pdo->quote($url));
			$du = $res->fetch();
			$res->closeCursor();
			$res = $this->pdo->query("select count(*) as cnt from visited where url=".$this->pdo->quote($url));
			$dv = $res->fetch();
			$res->closeCursor();
			if (empty($du['cnt']) && empty($dv['cnt'])){ 
				print "pushed $type $url\n";
				$this->pdo->query("insert into urls(url, type, visited) values(".$this->pdo->quote($url).", '{$type}', 0)");
				$cnt++;
			}
		}
		return $cnt;
	}

	// pop $count of urls of $type for processing
	function pop_urls($type, $count = 1) {
		$sql = "select * from urls where type='$type' and visited!=1 limit $count";
		$res = $this->pdo->query($sql);

		$urls = array();
		while ($data = $res->fetch()){
			if (!$this->visited($data['url'])) 
				$urls[] = $data['url'];
		}
		$res->closeCursor();

		foreach ($urls as $url) {
			$sql = 'update urls set visited=1 where url='.$this->pdo->quote($url);
			$this->pdo->exec($sql);
		}

		return $urls;
	}

	// check if there are more urls to be processed, used for looping. 
	function more_urls($type) {
		$res = $this->pdo->query("select count(*) as cnt from urls where type='$type' and visited!=1");
		$d = $res->fetch();
		if (DEBUG) print "number of $type urls in queue: ".$d['cnt']."\n";
		if (empty($d['cnt'])) return false;
		else return true;
	}

	// get variable
	function get_var($var) {
		$res = $this->pdo->query("select value from vars where `key`='$var'");
		$d = $res->fetch();
		return $d['value'];
	}

	// save a product, implementing many rules. You should provide only what you have, 
	// this method will figure out how to fill in the rest. Prod is an array, look 
	// for keys specs below. Values are mostly results from preg_match, which means 
	// the value is in array(1=>$value) format.  
	function save_prod($prod, $page='', $url='', $msrp=false) {
		// enable input of both preg_match results and simple strings
		foreach ($prod as $key=>$value) 
			if (!is_array($value)) $prod[$key] = array(1=>$value);


		// if no manufacturer SKU, copy the distributor SKU and vice versa. 
		if (empty($prod['SKU'][1]) && !empty($prod['mfr_SKU'][1])) $prod['SKU'] = array(1=>$prod['mfr_SKU'][1]);
		if (empty($prod['mfr_SKU'][1]) && !empty($prod['SKU'][1])) $prod['mfr_SKU'] = array(1=>$prod['SKU'][1]);

		// check if visited
		if (!empty($prod['SKU'][1]) && $this->visited($prod['SKU'][1])) {print "not saving, visited\n";return false;}

		// check if sku exists
		if (empty($prod['SKU'][1])) {
			print "ERROR: no SKU\n";
			print_r($prod);
			return false;
		}

		//kill commas in prices
		if (!empty($prod['msrp'][1])) $prod['msrp'][1] = preg_replace(array("/,/", '/^\W*|\W*$/'),'',$prod['msrp'][1]);
		if (!empty($prod['cost'][1])) $prod['cost'][1] = preg_replace(array("/,/", '/^\W*|\W*$/'),'',$prod['cost'][1]);

		// is it a logged out run to collect msrps only?
		if ($msrp) {
			if (!empty($prod['cost'][1])) {
				$this->pdo->exec("insert into msrp values (".$this->pdo->quote($prod['SKU'][1]).", ".$this->pdo->quote($prod['cost'][1]).")");
			}
		} else { // its a regular product save
			// is there an existing msrp saved?
			$res = $this->pdo->query("select price from msrp where sku=".$this->pdo->quote($prod['SKU'][1]));
			$msrp = $res->fetch();
			$res->closeCursor();
			if (!empty($msrp['price']) && empty($prod['msrp'])) $prod['msrp']=array(1=>$msrp['price']);

			// test for errors in data
			if (empty($prod['cost'][1]) || !preg_match('/^[0-9\.]+$/', $prod['cost'][1])) {
				$this->error("ERROR: NO COST OR MALFORMED COST", $prod, $page, $url);
				return false;
			}
			if (!empty($prod['msrp'][1]) && !preg_match('/^[0-9\.]+$/', $prod['msrp'][1])) {
				$this->error("ERROR: MALFORMED MSRP", $prod, $page, $url);
				return false;
			}
			if (empty($prod['cat'][0])) {
				$this->error("ERROR: NO MAIN CATEGORY", $prod, $page, $url);
				return false;
			}
			if (empty($prod['manufacturer'][1])) {
				$this->error("ERROR: NO MANUFACTURER", $prod, $page, $url);
				return false;
			}
			if (empty($prod['title'][1])) {
				$this->error("ERROR: NO TITLE", $prod, $page, $url);
				return false;
			}

			// set marketing description if only technical
			if (empty($prod['description'][1]) && !empty($prod['technical'][1])) {
				$prod['description'][1] = $prod['technical'][1];
				$prod['technical'][1] = '';
			}

			// remove distributor names
			if (!empty($this->init_options['remove'])) {
				foreach ($this->init_options['remove'] as $name) {
					if (!empty($prod['description'][1])) $prod['description'][1] = preg_replace("/$name/i",'we',$prod['description'][1]);
					if (!empty($prod['technical'][1])) $prod['technical'][1] = preg_replace("/$name/i",'we',$prod['technical'][1]);
				}

			}
			if (!empty($this->init_options['remove_sentence'])) {
				foreach ($this->init_options['remove_sentence'] as $name) {
					if (!empty($prod['description'][1])) 
						$prod['description'][1] = $this->remove_sentences($prod['description'][1], $name);
					if (!empty($prod['technical'][1])) 
						$prod['technical'][1] = $this->remove_sentences($prod['technical'][1], $name);
				}

			}

			foreach ($this->remove_sentence as $name) {
				if (!empty($prod['description'][1])) 
					$prod['description'][1] = $this->remove_sentences($prod['description'][1], $name);
				if (!empty($prod['technical'][1])) 
					$prod['technical'][1] = $this->remove_sentences($prod['technical'][1], $name);
			}


			// remove phone numbers and emails
			if (!empty($prod['description'][1])) $prod['description'][1] = preg_replace("/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}[^.]*\b/",' ',$prod['description'][1]);
			if (!empty($prod['technical'][1])) $prod['technical'][1] = preg_replace("/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}[^.]*\b/",' ',$prod['technical'][1]);
			if (!empty($prod['description'][1])) $prod['description'][1] = preg_replace("/\b\d{3,3}\D\d{3,3}\D\d{3,4}\b/",' ',$prod['description'][1]);
			if (!empty($prod['technical'][1])) $prod['technical'][1] = preg_replace("/\b\d{3,3}\D\d{3,3}\D\d{3,4}\b/",' ',$prod['technical'][1]);

			// reformat HTML
			if (!empty($prod['description'][1])) $prod['description'][1] = $this->reformat_html ($prod['description'][1]);
			if (!empty($prod['technical'][1])) $prod['technical'][1] = $this->reformat_html ($prod['technical'][1]);

			// trim & control char remove
			foreach ($prod as $key=>$value) {
				if (!empty($prod[$key][1])) {
					$prod[$key][1] = preg_replace('/^\s*|\s*$/s','', $prod[$key][1]);
					$prod[$key][1] = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $prod[$key][1]);
				}
				
			}

			// refurnbished
			if (empty($prod['refurbished'][1])) $prod['refurbished']=array(1=>'N');
			if (preg_match('/open\s+box/i', $prod['title'][1])) $prod['refurbished']=array(1=>'Y');


			// write to CSV
			if (DEBUG) print "SAVING '{$prod['SKU'][1]}'\n";
			//$csv = fopen ($this->get_var('csv_file'), "a");
			//fputcsv($csv, array(
			$this->csvout->save($this->get_var('csv_file'), array(0=>array(
				$prod['SKU'][1],
				(empty($prod['mfr_SKU'][1])?'':$prod['mfr_SKU'][1]),
				(empty($prod['UPC'][1])?'':$prod['UPC'][1]),
				(empty($prod['manufacturer'][1])?'':$prod['manufacturer'][1]),
				(empty($prod['title'][1])?'':$prod['title'][1]),
				(empty($prod['description'][1])?'':$prod['description'][1]),
				(empty($prod['technical'][1])?'':$prod['technical'][1]),
				(empty($prod['cost'][1])?'':$prod['cost'][1]),
				(empty($prod['weight'][1])?'0':$prod['weight'][1]),
				(empty($prod['msrp'][1])?'':$prod['msrp'][1]),
				(empty($prod['map'][1])?'':$prod['map'][1]),
				(empty($prod['wholesale'][1])?'':$prod['wholesale'][1]),
				(empty($prod['cat'][0])?'':trim($prod['cat'][0])),
				(empty($prod['cat'][1])?'':trim($prod['cat'][1])),
				(empty($prod['cat'][2])?'':trim($prod['cat'][2])),
				(empty($prod['cat'][3])?'':trim($prod['cat'][3])),
				(empty($prod['length'][1])?'':$prod['length'][1]),
				(empty($prod['width'][1])?'':$prod['width'][1]),
				(empty($prod['height'][1])?'':$prod['height'][1]),
				(empty($prod['returnable'][1])?'Y':$prod['returnable'][1]),
				$prod['refurbished'][1],
				(empty($prod['warranty'][1])?'':$prod['warranty'][1]),
				(empty($prod['image'][1])?'':$prod['image'][1]),
				(isset($prod['quantity'][1])?$prod['quantity'][1]:''),
				(empty($prod['eta'][1])?'':$prod['eta'][1]),
				(empty($prod['date'][1])?'':$prod['date'][1]),
				(empty($prod['virtual'][1])?'':$prod['virtual'][1])
			)), true);
		}
	}

	function remove_sentences ($text, $name) {
		$search = "/(?:https?:\/\/)?(?:[\w]+\.)(?:\.?[\w]{2,})+/s";
		$text = preg_replace($search ," ",$text);
		$text = preg_replace("/[^.<>]*{$name}[^.<>]*\./si",'',$text);
		$text = preg_replace("/[^.<>]*{$name}[^.<>]*<[^<>]+>[^.<>]*\./si",'',$text);
		$text = preg_replace("/[^.<>]*{$name}[^.<>]*<[^<>]+>[^.<>]*<[^<>]+>[^.<>]*\./si",'',$text);
		$text = preg_replace("/[^.<>]*{$name}[^.<>]*<[^<>]+>[^.<>]*<[^<>]+>[^.<>]*<[^<>]+>[^.<>]*\./si",'',$text);
		$text = preg_replace("/[^.<>]*{$name}[^.<>]*<[^<>]+>[^.<>]*<[^<>]+>[^.<>]*<[^<>]+>[^.<>]*<[^<>]+>[^.<>]*\./si",'',$text);
		$text = preg_replace("/[^.<>]*{$name}[^.<>]*<[^<>]+>[^.<>]*<[^<>]+>[^.<>]*<[^<>]+>[^.<>]*<[^<>]+>[^.<>]*<[^<>]+>[^.<>]*\./si",'',$text);
		$text = preg_replace("/[^.<>]*{$name}[^.<>]*<[^<>]+>[^.<>]*<[^<>]+>[^.<>]*<[^<>]+>[^.<>]*<[^<>]+>[^.<>]*<[^<>]+>[^.<>]*<[^<>]+>[^.<>]*\./si",'',$text);
		// this above is for various number of html tags in the sentence
		$text = preg_replace("/{$name}/si",'',$text);
		return ($text);
	}

	// logs extraction error to a file
	function error ($text, $prod, $page, $url) {
		print "$text\n";
		print_r($prod);
		$sql = "insert into errors(error, dump, page, url) values(".$this->pdo->quote($text).", ".$this->pdo->quote(print_r($prod, true)).", ".$this->pdo->quote($page).", ".$this->pdo->quote($url).")";
		$this->pdo->exec($sql);
	}

	// reformat HTML for display on site
	function reformat_html($html) {
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

	function get_csv($key, $value, $table = 'csv') {
		$value = $this->pdo->quote ( $value );
		$this->pdo->exec ( "update $table set processed=1 where $key=$value" );
		$res = $this->pdo->query ( "select * from $table where $key=$value" );
		$d = $res->fetch ();
		if (empty ( $d ) && DEBUG)
			print "not found record in $table for $key=$value\n";
		return $d;
	}
	
	function import_csv($file, $skip = 0, $table = 'csv') {
		print "importing from $file\n";
		$this->pdo->exec ( "drop table if exists $table" );
		$fp = fopen ( $file, 'r' );
		while ( $skip > 0 ) {
			fgetcsv ( $fp );
			$skip --;
		}
		$fields = fgetcsv ( $fp );
		$fdata = array ();
		$sqlfields = '';
		foreach ( $fields as $id => $field ) {
			if (! empty ( $sqlfields ))
				$sqlfields .= ', ';
			$field = preg_replace ( '/^\W*|\W*$/', '', $field );
			$fname = preg_replace ( '/\W+/', '_', strtolower ( trim ( $field ) ) );
			if (preg_match ( '/^\d/', $fname ))
				$fname = "d" . $fname;
			if (empty ( $fname ))
				$fname = "field" . $id;
			$sqlfields .= $fname . " varchar(255)";
			$fdata [$id] = $fname;
		}
		$sql = "create table $table ($sqlfields, processed int default 0)";
		if (DEBUG)
			print $sql . "\n";
		$this->pdo->exec ( $sql );
		$row = 0;
		while ( $data = fgetcsv ( $fp ) ) {
			$sqlfields = '';
			$sqldata = '';
			foreach ( $fdata as $id => $name ) {
				if (! empty ( $sqlfields ))
					$sqlfields .= ', ';
				if (! empty ( $sqldata ))
					$sqldata .= ', ';
				$sqlfields .= $name;
				$sqldata .= $this->pdo->quote ( $data [$id] );
			}
			$this->pdo->exec ( "insert into $table ($sqlfields) values ($sqldata)" );
			$row ++;
			if ($row % 100 == 0 && DEBUG)
				print "imported $row rows\n";
		}
	}

	// set all parameters for cURL::AdvancedGetPage from local settings and get the page
	function get_page($urls,$opt = array()){
		if (!empty($this->random_ip) && empty($opt['random_ip'])) $opt['random_ip']=$this->random_ip;
		if (!empty($this->ips) && empty($opt['ips'])) $opt['ips']=$this->ips;
		if (!empty($this->sleep) && empty($opt['sleep'])) $opt['sleep']=$this->sleep;
		if (!empty($this->bind_ip) && empty($opt['bind_ip'])) $opt['bind_ip']=$this->bind_ip;
		if (!empty($this->userpwd) && empty($opt['userpwd'])) $opt['userpwd']=$this->userpwd;
		if (!empty($this->headers) && empty($opt['headers'])) $opt['headers']=$this->headers;
		if (!empty($this->proxy) && empty($opt['proxy'])) {
			$opt['proxystatus']=true;
			$opt['proxy']=$this->proxy;
		}
		if (!empty($this->tmpdir)) $opt['tmpdir'] = $this->tmpdir;
		$opt['debug']=DEBUG;

		return $this->curl->AdvancedGetPage($urls, $opt);
	}

	function get_hidden_data($page){
		@preg_match_all('/(<input[^>]*type=["\']?hidden["\']?[^>]*>)/ims',$page,$matches);
		$ret=array();
		if(!empty($matches[0])){
			if(is_array($matches[0])){
				foreach($matches[0] as $k=>$v){
				
					if(preg_match('/name=[\'"]?([^"\']*)[\'"]?/ims',$v,$match)){
						$name=$match[1];
					}
					if(preg_match('/value=[\'"]?([^"\']*)[\'"]?/ims',$v,$match)){
						$value=$match[1];
					}

					$arr=array('name'=>$name,'value'=>$value);
					$ret[]=$arr;
				}
			}
		}
		return $ret;
	}

	function write_cookie($cookieLine=false){
		if(!$cookieLine) return 0;
		$cf = fopen ($this->tmpdir."cookie.txt", "a");
		fwrite ($cf, "$cookieLine\n");
		fclose ($cf);
		return 1;
	}

	function ftp_upload ($content, $csv_dir, $csv_file) {
		// upload file and verify
		$command = "curl -T {$csv_dir}{$csv_file} ftp://{$this->ftp} --user {$this->username}:{$this->password}";
		$res = shell_exec($command);
		$command = "curl -u {$this->username}:{$this->password} 'ftp://{$this->ftp}/{$csv_file}' -o {$csv_dir}{$csv_file}.test";
		$res = shell_exec($command);
		$test = file_get_contents($csv_dir.$csv_file.".test");
		if ($test==$content) return TRUE;
		else return FALSE;
	}

	function save_tmp_file ($content, $dir1, $dir2, $filename) {
		if (!file_exists(Config::getTmpDir())) {
			NeobitsLogError ("no tmp dir!");
			return FALSE;
		}
		$csv_tmp = Config::getTmpDir()."/".$dir1;
		if (!file_exists($csv_tmp)) {
			if (!mkdir($csv_tmp)) {
				NeobitsLogError ("cannot create csv dir $csv_tmp");
				return FALSE;
			}
		}

		if (!file_exists($csv_tmp."/".$dir2)) {
			if (!mkdir($csv_tmp."/".$dir2)) {
				NeobitsLogError ("cannot create csv dir $csv_tmp"."/".$dir2);
				return FALSE;
			}
		}

		$csv_dir = $csv_tmp."/".$dir2."/";
		$csv_file = $filename;
		$fp = fopen($csv_dir.$csv_file, "w");
		fwrite ($fp, $content);
	        fclose ($fp);	
		return $csv_dir;
	}
	
	function import_tsv($file, $skip = 0, $table = 'csv') {
		print "importing from $file\n";
		$this->pdo->exec ( "drop table if exists $table" );
	
		$fcontents = file ( $file );
		$sqlfields = '';
		$colfields = '';
		
		// create table
		$line = trim ( $fcontents [$skip] );
		$cols = explode ( "\t", $line );
		foreach ( $cols as $k => $v ) {
			if (! empty ( $sqlfields ))
				$sqlfields .= ', ';
			if (! empty ( $colfields ))
				$colfields .= ', ';
				
			$v = preg_replace ( '/^\W*|\W*$/', '', $v );
			$col = preg_replace ( '/\W+/', '_', strtolower ( trim ( $v ) ) );
			$sqlfields .= $col . " varchar(255)";
			$colfields .= $col;
		}
		$sql = "create table $table ($sqlfields, processed int default 0)";
		$this->pdo->exec ( $sql );
		
		if (DEBUG)
			print $sql . "\n";
		
		//	insert data
		$j = $skip + 1;
		$row = 0;
		for($i = $j; $i < sizeof ( $fcontents ); $i ++) {
			$line = $fcontents [$i];
			$arr = explode ( "\t", $line );
			$sqldata = '';
			foreach ( $arr as $k => $v ) {
				if (! empty ( $sqldata ))
					$sqldata .= ', ';
				$sqldata .= $this->pdo->quote ( $v );
			}
			$this->pdo->exec ( "insert into $table ($colfields) values ($sqldata)" );
			
			$row ++;
			if ($row % 100 == 0 && DEBUG)
				print "imported $row rows\n";
		}
	}
	
	function import_xls($file, $table = 'csv') {
		$this->xls->read ( $file );
		
		print "importing from $file\n";
		$this->pdo->exec ( "drop table if exists $table" );
		
		$sqlfields = '';
		$colfields = '';
		$sql_array = array ();
		
		// create table
		$x = 1;
		$y = 1;
		while ( $y <= $this->xls->sheets [0] ['numCols'] ) {
			if (! empty ( $sqlfields ))
				$sqlfields .= ', ';
			if (! empty ( $colfields ))
				$colfields .= ', ';
			
			$cell = isset ( $this->xls->sheets [0] ['cells'] [$x] [$y] ) ? $this->xls->sheets [0] ['cells'] [$x] [$y] : '';
			$cell = preg_replace ( '/^\W*|\W*$/', '', $cell );
			$cell = preg_replace ( '/\W+/', '_', strtolower ( trim ( $cell ) ) );
			
			if (in_array ( $cell, $sql_array ))
				$cell = $cell . '_' . $y;
			
			$sql_array [] = $cell;
			$sqlfields .= $cell . " varchar(255)";
			$colfields .= $cell;
			
			$y ++;
		}
		$sql = "create table $table ($sqlfields, processed int default 0)";
		$this->pdo->exec ( $sql );
		
		if (DEBUG)
			print $sql . "\n";
			
		// insert data
		$x = 2;
		while ( $x <= $this->xls->sheets [0] ['numRows'] ) {
			$y = 1;
			$sqldata = '';
			while ( $y <= $this->xls->sheets [0] ['numCols'] ) {
				if (! empty ( $sqldata ))
					$sqldata .= ', ';
		
				$cell = isset ( $this->xls->sheets [0] ['cells'] [$x] [$y] ) ? $this->xls->sheets [0] ['cells'] [$x] [$y] : '';
				$sqldata .= $this->pdo->quote ( $cell );
		
				$y ++;
			}
			$this->pdo->exec ( "insert into $table ($colfields) values ($sqldata)" );
			
			$x ++;
			if ($x % 100 == 0 && DEBUG)
				print "imported $x rows\n";
		}
	}

}
?>
