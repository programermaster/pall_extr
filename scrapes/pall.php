<?php
putenv ( "EMAIL_ERRORS_TO=rhalili@gmail.com" );
define ( 'DEBUG', true );
date_default_timezone_set ( 'America/Los_Angeles' );

// These lines should go at the top of all PHP scripts (when script is on server)
/*include ("functions.php");
Config::init ( "NeobitsErrors" );
Config::setApplicationName ( "Additional Content" ); // Some name for our application*/

// Use this when you use extractor on localhost
include '../lib/AdditionalContent.php';
include '../parsers/ParseWwwPallCom.php';

class PallExtractor
{
	private $_additional_content;
	private $_parser;

	public function __construct(){
		// initialize extractor using the home url, and the array containing the distributor names to strip
		$this->_parser = new SiteParsers_ParseWwwPallCom( array (
			// CHANGE THIS - push_url is the start url to get categories from,
			// remove_sentence should contain all variations of distributor name,
			// site - this is the site domain
			// noreset should be true only if you're testing and you don't want the urls queued in sqlite file to be erased initially
			'push_url' => 'http://www.pall.com/main/product-list.page',
			'remove_sentence' => array (),
			'noreset' => false,
			'site' => 'www.pall.com',
			'proxy'=> false
		) );

		// initialize AdditionalContent using the home Url, Name, Type...and other required parameters
		$this->_additional_content = new AdditionalContent ( array (
			'URL'             => 'http://www.pall.com/', // mfr website url
			'Name'            => 'Pall Life Sciences', // name
			'Manufacturer_ID' => '37405', // mfr ID (ask Mika for this info)
			'Type'            => 'scrape', // scrape or feed
			'Script'          => '/home/scrapes/pall/pall.php', // path to this script
			'Server'          => '2', // server on which this script will work
			'Frequency'       => 'daily' // run script 'daily','weekly','monthly'
		) );
	}


	// get all categories and products page URLs
	protected function get_all_categories() {
		// this will push all category and product urls to processing queue.
		$urls = $this->_parser->pop_urls('cat', $this->_parser->get_var('multi'));
		$pages = $this->_parser->get_page($urls);

		//$this->_parser->get_categories_urls(current($pages), key($pages));
		$this->_parser->get_products_urls(current($pages), key($pages));

		do {

			$urls = $this->_parser->pop_urls('cat', $this->_parser->get_var('multi')); // second parameter is the number of threads
			$pages = $this->_parser->get_page($urls); // if you send get_page an array of urls you'll get an array of pages which it got in parallel threads

			foreach ($pages as $url => $page) {
				//$this->_parser->get_products_urls($page, $url);
			}

			$this->_parser->more_urls('prod'); // this will just print the number of urls in queue

		} while ($this->_parser->more_urls('cat'));
	}

	protected function get_products() {
		$prodcnt = 0;
		do {
			$prodcnt ++;
			echo "pop-up product urls\n";
			$urls = $this->_parser->pop_urls ('prod', $this->_parser->get_var ( 'multi' ) );

			//$urls =  array("http://www.pall.com//main/Industrial-Manufacturing/product.page?lid=gri78lre");
			//$urls =  array("http://www.pall.com/main/biopharmaceuticals/product.page?lid=gu7uclgl");
			//$urls =  array("http://www.pall.com/main/biopharmaceuticals/product.page?lid=hdf24174");
			//$urls =  array("http://www.pall.com/main/laboratory/product.page?lid=gri78mb1");
			$urls =  array("http://www.pall.com/main/laboratory/product.page?lid=gri78m6o");


			$pages = $this->_parser->get_page($urls);

			foreach ( $pages as $url => $page ) {
				print "parse product page\n";
				if (! empty ( $page )){
					$this->parse_product($page, $url);
				}
				else {
					print "empty page, retrying\n";
					$page = $this->_parser->get_page ( $url );
					if (! empty ( $page )){
						$this->parse_product($page, $url);
					}
					else{
						print "ERROR: empty page\n";
					}
				}
			}
		} while ( ! empty ( $urls ) && $this->_parser->more_urls ( 'prod' ) );
	}

	protected function parse_product($page, $url) {
		$product = $this->_parser->parse_product_content($page, $url);
		//die(print_r($product));
		$this->_additional_content->save($product["product"], $product["product_additional_fields"]);
	}

	public function finish(){
		$this->_parser->finish();
	}

	public function run(){

		//$this->get_all_categories();

		// visit each product page URL, dont process MSRP separately (second parameter is false)
		$this->get_products();

		// write extraction stats
		$this->finish();
	}
}

$pall = new PallExtractor();
$pall->run();











