<?php

/*
  03/19/2012

  By Steve Fatula of 5 Diamond IT Consulting

  This Class manages interactions with cURL

*/
ini_set('memory_limit', '-1');
class cURL {

private $Postfields = null;
private $HTTPAuth = null;
private $Follow = null;
private $Headers = null;
private $UseCookies = FALSE;

private $CookieJar = null;

private function GetFTPFileAttempt($URL, $File, $Ascii) {
	$c = curl_init();
	$FileOpen = fopen($File, "w");
	curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($c, CURLOPT_URL, $URL);
	curl_setopt($c, CURLOPT_TRANSFERTEXT, ($Ascii ? TRUE : FALSE));
	curl_setopt($c, CURLOPT_RETURNTRANSFER, FALSE);
	curl_setopt($c, CURLOPT_FILE, $FileOpen);
	$return = curl_exec($c);
	if ($return === FALSE) {
		$erroNo = curl_errno($c);
		$errMsg = curl_error($c);
		curl_close($c);
		unset($c);
		throw new exception("Could not access ftp file {$URL}, curl error # {$erroNo} {$errMsg}", FeedTotals::ProductError);
	}
	curl_close($c);
	unset($c);
	fclose($FileOpen);
	return;
}

// Returns TRUE if the file was downloaded ok, else, error message in the return value
public function GetFTPFile($URL, $File, $Ascii) {
	$tries = 1;
	$Result = FALSE;
	while ($tries <= 5) {
		if ($tries != 1) sleep(60);
		try {
			$this->GetFTPFileAttempt($URL, $File, $Ascii);
			$Result = TRUE;
		} catch (Exception $e) {
			$Result = $e->getMessage();
		}
		if ($Result === TRUE) break;
		$tries++;
	}
	return $Result;
}

private function GetCookieFile() {
	if (is_null($this->CookieJar)) {
		$this->CookieJar = tempnam(Config::getTmpDir(), "cURL-");
	}
}

public function SetPostFields($Post) {
	$this->Postfields = $Post;
}

public function SetHttpAuthentication($User, $Password) {
	$this->HTTPAuth = "{$User}:{$Password}";
}

public function GetHTTPPage($URL, $UseCookies = FALSE) {
	if ($UseCookies) $this->GetCookieFile();
	$c = curl_init();
	if ($UseCookies)  {
		curl_setopt($c, CURLOPT_COOKIEJAR, $this->CookieJar);
		curl_setopt($c, CURLOPT_COOKIEFILE, $this->CookieJar);
	}
	curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($c, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($c, CURLOPT_URL, $URL);
	curl_setopt($c, CURLOPT_FAILONERROR, TRUE);
	curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($c, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($c, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; rv:21.0) Gecko/20100101 Firefox/21.0");
	// curl_setopt($c, CURLOPT_VERBOSE, TRUE);
	if (!is_null($this->Headers)) curl_setopt($c, CURLOPT_HTTPHEADER, $this->Headers);
	if (!is_null($this->HTTPAuth)) curl_setopt($c, CURLOPT_USERPWD, $this->HTTPAuth);
	curl_setopt($c, CURLOPT_AUTOREFERER, TRUE);
	if (is_null($this->Postfields)) {
		curl_setopt($c, CURLOPT_HTTPGET, TRUE);
		curl_setopt($c, CURLOPT_POST, FALSE);
	} else {
		curl_setopt($c, CURLOPT_HTTPGET, FALSE);
		curl_setopt($c, CURLOPT_POST, TRUE);
		curl_setopt($c, CURLOPT_POSTFIELDS, $this->Postfields);
		$this->Postfields = NULL;
	}
	$return = curl_exec($c);
	if ($return === FALSE) {
		$erroNo = curl_errno($c);
		$errMsg = curl_error($c);
		curl_close($c);
		unset($c);
		throw new Exception("Could not access web page {$URL}, curl error # {$erroNo} {$errMsg}");
	}
	curl_close($c); // Must close to get cookies dumped to file for use on other pages
	unset($c);
	return $return;
}

public function GetHTTPFile($URL, $FileName, $Binary, $UseCookies = FALSE) {
	if ($UseCookies) $this->GetCookieFile();
	$fn = fopen($FileName, "w");
	$c = curl_init();
	if ($UseCookies)  {
		curl_setopt($c, CURLOPT_COOKIEJAR, $this->CookieJar);
		curl_setopt($c, CURLOPT_COOKIEFILE, $this->CookieJar);
	}
	curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($c, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($c, CURLOPT_URL, $URL);
	curl_setopt($c, CURLOPT_FAILONERROR, TRUE);
	curl_setopt($c, CURLOPT_RETURNTRANSFER, FALSE);
	if ($Binary) curl_setopt($c, CURLOPT_BINARYTRANSFER, TRUE);
	else curl_setopt($c, CURLOPT_BINARYTRANSFER, FALSE);
	curl_setopt($c, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($c, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
	if (!is_null($this->Headers)) curl_setopt($c, CURLOPT_HTTPHEADER, $this->Headers);
	curl_setopt($c, CURLOPT_AUTOREFERER, TRUE);
	if (is_null($this->Postfields)) {
		curl_setopt($c, CURLOPT_HTTPGET, TRUE);
		curl_setopt($c, CURLOPT_POST, FALSE);
	} else {
		curl_setopt($c, CURLOPT_HTTPGET, FALSE);
		curl_setopt($c, CURLOPT_POST, TRUE);
		curl_setopt($c, CURLOPT_POSTFIELDS, $this->Postfields);
		$this->Postfields = NULL;
	}
	curl_setopt($c, CURLOPT_FILE, $fn);

	$return = curl_exec($c);
	fclose($fn);
	if ($return === FALSE) {
		$erroNo = curl_errno($c);
		$errMsg = curl_error($c);
		curl_close($c);
		unset($c);
		trigger_error("Could not access web page {$URL}, curl error # {$erroNo} {$errMsg}", E_USER_ERROR);
	}
	curl_close($c); // Must close to get cookies dumped to file for use on other pages
	unset($c);
	return;
}

// function AdvancedGetPage - gets a page or an array of pages with options
// ===== parameters:
// $urls - can be string or array of urls. Returns array of results we've got in parallel threads if array sent.
// $opt - options
// ===== keys of $opt:
// data - if present, a POST request will be executed with the data from this key
// login - if present cookies will be reset
// proxystatus - if present, the request will be directed through 'proxy' key.
// proxy - required if proxystatus is set
// ref_url - content of Referer header
// display_headers - display sent headers for purpose of debugging
// json - set the headers to accept the json request
// bind_ip - IP to work from
// random_ip - if true, a random IP to bind to will be chosen
// ips - array of IPs to choose random ip from
// userpwd - http basic authentication, format "$username:$password"
// debug - true if you want debug information including writing the last.html containing last page fetched
// sleep - how many seconds to sleep after a request

function AdvancedGetPage($urls,$opt = array()){
	$array = true;
	$tmpdir = '';
	$cookie_file = "cookie.txt";
	if (!empty($opt['cookie_file'])) $cookie_file = $opt['cookie_file'];
	if (!empty($opt['tmpdir'])) $tmpdir=$opt['tmpdir'];
	if (!is_array($urls)) {
		if ($urls == Config::SCRAPE_REPORT) $opt['userpwd']=Config::SCRAPE_REPORT_LOGIN;
		$urls = array($urls);
		$array = false;

	}
	if(!empty($opt['login'])) {
		if (!empty($opt['debug'])) print "deleting cookie file\n";
		$fp = fopen($tmpdir.$cookie_file, "w");
		fclose($fp);
	}
	$mh = curl_multi_init();
	$curl_array = array();
	foreach ($urls as $url) {
		if (!empty($opt['debug'])) print "getting $url\n";
		$ch = curl_init();
		if (!empty($opt['display_headers'])) curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $tmpdir.$cookie_file);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $tmpdir.$cookie_file);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_2) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.95 Safari/537.11");
		if (!empty($opt['random_ip'])) {
			$rand_ip = $opt['ips'][rand(0,count($opt['ips'])-1)];
			if (!empty($opt['debug'])) print "setting random IP $rand_ip\n";
			curl_setopt($ch, CURLOPT_INTERFACE, $rand_ip);
		}
		if (!empty($opt['bind_ip'])) {
			$bind = '';
			if (!empty($opt['bind_ip'])) $bind = $opt['bind_ip'];
			if (!empty($opt['debug'])) print "binding to IP {$bind}\n";
			curl_setopt($ch, CURLOPT_INTERFACE, $bind);
		}
		if (!empty($opt['json'])) curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Accept: application/json, text/javascript, */*; q=0.01',
			'Accept-Charset:ISO-8859-1,utf-8;q=0.7,*;q=0.3',
			'Accept-Language:en-US,en;q=0.8',
			'X-Requested-With:XMLHttpRequest'
		));
		if (!empty($opt['faces'])) curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Faces-Request: partial/ajax'
		));
		curl_setopt($ch, CURLOPT_TIMEOUT, 120);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		if (!empty($opt['proxystatus']) && !empty($opt['proxy'])) {
			curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, TRUE);
			curl_setopt($ch, CURLOPT_PROXY, $opt['proxy']);
			print "using proxy: '{$opt['proxy']}'\n";
		}
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if (!empty($opt['ref_url'])) curl_setopt($ch, CURLOPT_REFERER, $opt['ref_url']);
		if (!empty($opt['userpwd'])) curl_setopt($ch, CURLOPT_USERPWD, $opt['userpwd']);
		if (!empty($opt['headers'])) curl_setopt($ch, CURLOPT_HEADER, TRUE);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

		if (!empty($opt['data'])) {
			if (!empty($opt['debug'])) 
				if (is_array($opt['data']))
					print_r($opt['data']);
				else 
					print (preg_replace("/&/","\n",$opt['data'])."\n");
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $opt['data']);
		}
		if (!empty($opt['content-type'])) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: ".$opt['content-type']));
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_multi_add_handle($mh,$ch);
		$curl_array[] = $ch;
	}
	$running = NULL;
	do {
		usleep(100000);
		curl_multi_exec($mh,$running);
	} while($running > 0);

	$res = array();
	foreach($curl_array as $ch) {
		if (!empty($opt['display_headers'])) print_r(curl_getinfo($ch, CURLINFO_HEADER_OUT));
		$url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		$res[$url] = curl_multi_getcontent($ch);
		if (!empty($opt['debug'])) {
			$fp = fopen ($tmpdir.'last.html', 'w');
			fwrite ($fp, $res[$url]);
			fclose($fp);
		}

	}

	foreach($curl_array as $ch) {
		curl_multi_remove_handle($mh, $ch);
	}
	curl_multi_close($mh);

	if (!empty($opt['sleep'])) {
		if (!empty($opt['debug'])) print "sleeping {$opt['sleep']}s\n";
		sleep((int)$opt['sleep']);
	}

 	if ($array) {
		return $res;
	} else {
		return array_shift($res);
	} 
}

}
?>
