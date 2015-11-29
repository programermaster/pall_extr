<?php


class ParseSite {
	var $site; 

	function ParseSite ($options = array()) {
		if (!empty($options['site'])) {
			$this->site = $options['site'];
			// dynamically create class based on site and send it username and pass for login
			$class = "SiteParsers_Parse".preg_replace("/ /", "", ucwords(preg_replace("/\W/"," ",$this->site)));
			$this->parser = new $class($options);
		}
	}

	function keep_logged_in () {
		$d = dir(__DIR__."/SiteParsers");
		while (false !== ($entry = $d->read())) {
			if (preg_match("/^(Parse.*)\.php$/", $entry, $m) && $entry != "ParseSite.php") {
				$class = "SiteParsers_".$m[1];
				preg_match_all("/([A-Z][^A-Z]+)/", $class, $m);
				$site = '';
				for ($i=1;$i<count($m[1]); $i++) {
					$site .= strtolower($m[1][$i]);
					if ($i<count($m[1])-1) $site .= '.';
				}
				$parser = new $class(array('site'=>$site));
				if (method_exists($parser, 'login') && method_exists($parser, 'get_stock'))
					$parser->login();
			}
		}
	}

	function get_tmp_dir () {
		return ($this->parser->tmpdir);
	}

	function login () {
		return $this->parser->login();

	}

	function get_last_error () {
		if (!empty($this->parser->last_error))
			return $this->parser->last_error;
		else return '';
	}

	function get_tracking () {
		$tracking = $this->parser->get_tracking();
		//print "checking for zero tracking numbers\n";
		foreach ($tracking as $key=>$ship) {
			if (!empty($ship['carrier'])) 
				foreach ($ship['carrier'] as $track) {
					if (preg_match("/^\s*0+\s*$/", $track['tracking'])) {
						//print "killing ".print_r($ship, true);
						unset ($tracking[$key]);	
					}
				}
		}
		return $tracking;
	}

	function enter_po ($po) {
		//print_r($po);exit;
		if (!empty($po['address']['ATTN_NAME'])) {
			$po['address']['FIRST_NAME'] = $po['address']['ATTN_NAME'];
			$po['address']['LAST_NAME'] = $po['address']['ATTN_NAME'];
		}
		return $this->parser->enter_po($po);
	}

	function get_stock ($sku, $i=0) {
		//locking mechanism - two users can't access the same site at the same time
		$FeedsDbObj = Config::GetDBName(Config::DATABASE_FEEDS);
		$FeedsDbObj->StartTransaction();
		$lock = $FeedsDbObj->SelectAndReturnRow("select site from extractor_lock where site='{$this->site}' and created>date_add(now(), interval -2 minute)", TRUE);

		if (empty($lock)) {
			$FeedsDbObj->InsertRow("insert into extractor_lock (site, created) values ('{$this->site}', now())");
			$FeedsDbObj->EndTransaction();
			$res = $this->parser->get_stock($sku);
			$FeedsDbObj->DeleteRow("delete from extractor_lock where site='{$this->site}'");
		} else {
			$FeedsDbObj->EndTransaction();
			sleep (7);
			if ($i>10) return "Server busy, try again";
			$res = $this->get_stock($sku, $i+1);
		}
		return $res;
	}

	function get_prod($sku, $locking = true, $i = 0) {
		if ($locking == true) {
			//locking mechanism - two users can't access the same site at the same time
			$FeedsDbObj = Config::GetDBName ( Config::DATABASE_FEEDS );
			//$FeedsDbObj->StartTransaction ();
			$lock = $FeedsDbObj->SelectAndReturnRow ( "select site from extractor_lock where site='{$this->site}' and created>date_add(now(), interval -2 minute)", TRUE );
			
			if (empty ( $lock )) {
				$FeedsDbObj->InsertRow ( "insert into extractor_lock (site, created) values ('{$this->site}', now())" );
				//$FeedsDbObj->EndTransaction ();
				$res = $this->parser->get_prod ( $sku );
				$FeedsDbObj->DeleteRow ( "delete from extractor_lock where site='{$this->site}'" );
			} else {
				//$FeedsDbObj->EndTransaction ();
				sleep ( 7 );
				if ($i > 10)
					return "Server busy, try again";
				$res = $this->get_prod ( $sku, $i + 1 );
			}
		} else {
			$res = $this->parser->get_prod ( $sku );
		}
		
		return $res;
	}
	
}

?>
