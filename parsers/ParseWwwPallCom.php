<?php
class SiteParsers_ParseWwwPallCom extends Extractor {
	
	function parse_product_content($page_html = '', $url)
	{
		// scrape sku
		$matches = array();
		preg_match("/<div(.*?)id=\"ds_section_9\">(.*?)><\/div>/si", $page_html, $order_info);
                    
                 // scrape title
                $matches = array();
                preg_match_all("/<th(.*?)>(.*?)<\/th>/si", $order_info[2], $products_name);
                foreach($products_name[2] as $key => $product_name){
                    
                    $title = $product_name;
                    
                    preg_match("/<th style=\"padding-top: 15px; font-weight: bold; border-bottom: #000000 solid 1px;\" colSpan=\"5\" align=\"left\" colspan=\"5\">$title<\/th>(.*?)<tr(.*?)class=\"content_row(.*?)\"(.*?)>(.*?)<th(.*?)>/si", $order_info[2], $matches);
                    preg_match_all("/<td(.*?)>(.*?)<\/td>/si", $matches[5], $matches);
                    print_R($matches[1]);
                    echo "xxxxxx";
                    foreach($matches[1] as $key=>$matchClassTd){
                         
                         if(strstr($matchClassTd, "partNumber")){
                             $sku = $matches[2][$key];
                             $basic_desc = "";
                             if(isset($match[$key+1])) $basic_desc = $matches[2][$key+1];
                             if(isset($match[$key+2])) $basic_desc .= " " . $matches[2][$key+2];
                          }
                          else continue;

                          echo($sku) . "<br>";

                            // we know what the name of manufacturer, no need for scrape
                            $manufacturer = "Pall Life Sciences";

                            // scrape marketing description
                            $matches = array();
                            preg_match("/<div id=\"ds_section_1\">(.*?)<div(.*?)class=\"productblock\"(.*?)>(.*?)<div(.*?)id=\"ds_section_3\"(.*?)>/si", $page_html, $matches);
                            if (isset($matches[0]) && $matches[0] != "") {
                                    $desc = preg_replace("/<div\\b[^>]*>/s", "", preg_replace("/<\/div>/s", "", preg_replace("/<a\\b[^>]*>(.*?)<\\/a>/s", "", preg_replace("/<div\\b[^>]*>(.*?)<\\/div>/s", "", $matches[0]))));
                            } else {
                                    $desc = "";
                            }

                            if($basic_desc != ""){
                                $desc = $basic_desc . "<br>" . $desc;
                            }

                            // scrape tehnical description
                            $matches = array();
                            preg_match("/<div(.*?)id=\"ds_section_3\"(.*?)>(.*?)<div(.*?)class=\"productblock\"(.*?)>(.*?)<div(.*?)id=\"ds_section_(.*?)\">/si", $page_html, $matches);
            //print_R($matches);
                            if (isset($matches[7]) && $matches[7] != "") {
                                    $technical = preg_replace("/<div\\b[^>]*>/s", "", preg_replace("/<\/div>/s", "", preg_replace("/<div class=\"productblock\">(.*?)<\/div>(.*?)<\/div>/s", "", preg_replace('/\'/', '\"', preg_replace('/\s+/', ' ', preg_replace("/<script\\b[^>]*>(.*?)<\\/script>/s", "", preg_replace("/<style\\b[^>]*>(.*?)<\\/style>/s", "", $matches[7])))))));
                            } else {
                                    $technical = "";
                            }
                            //die($technical);

                            //scraping generic cateegories and tag which defined generic cateogories
                            if ($technical!="") {

                                    $fields_categories = array();
                                    $fields_categories_matched = array();
                                    preg_match_all("/<h2>(.*?)<\/h2>/si", $technical, $fields_categories_matched);

                                    if(!(is_array($fields_categories_matched[1]) && count($fields_categories_matched[1])>0)) {
                                            preg_match_all("/<h3>(.*?)<\/h3>/si", $technical, $fields_categories_matched);

                                            if(!(is_array($fields_categories_matched[1]) && count($fields_categories_matched[1])>0)) {
                                                    preg_match_all("/<p class=\"header3\">(.*?)<\/p>/si", $technical, $fields_categories_matched);

                                                    if(!(is_array($fields_categories_matched[1]) && count($fields_categories_matched[1])>0)) {
                                                            preg_match_all("/<strong>\">(.*?)<\/strong>/si", $technical, $fields_categories_matched);
                                                            if(!(is_array($fields_categories_matched[1]) && count($fields_categories_matched[1])>0)) {
                                                                    $matched_tag = "";
                                                            }
                                                            else{
                                                                    $matched_tag = "strong";
                                                            }

                                                    }else{
                                                            $matched_tag = "p";
                                                    }

                                            }else{
                                                    $matched_tag = "h3";
                                            }
                                    }else{
                                            $matched_tag = "h2";
                                    }

            //echo $matched_tag;

                                    //defined array key=generic category name,  value array variable in php
                                    if(is_array($fields_categories_matched[1])) {
                                            foreach ($fields_categories_matched[1] as $fields_category_matched) {
                                                    $fields_category_matched = trim(str_replace(":", "", $fields_category_matched));
                                                    $fields_categories[$fields_category_matched] = strtolower(str_replace(" ", "_", $fields_category_matched));
                                            }
                                    }

                                    $matches = array();
                                    foreach ($fields_categories as $fields_category => $var) {

                                            ${$var} = array();

                                            preg_match("/<$matched_tag(.*?)>" . $fields_category . "(.*?)<\/$matched_tag>(.*?)<$matched_tag(.*?)\">/si", $technical, $matches);

                                            if(isset($matches[3])){
                                                    //sometimes generic category is generic field
                                                    if(preg_match_all("/<li>(.*?)<\/li>/", $matches[3], $fields)){

                                                            foreach($fields[1] as $field){

                                                                    $key_value = explode(":", $field);
                                                                    if(isset($key_value[1])) {
                                                                            $field = substr(trim($key_value[0], 0, 30));
                                                                            ${$var}[$field] = trim($key_value[1]);
                                                                    }else{
                                                                            $field = substr($fields_category,0, 30);
                                                                            if(!isset(${$var}[$field])) ${$var}[$field] = "";
                                                                            ${$var}[$field] = ${$var}[$field] .  trim($key_value[0]) . ", ";
                                                                    }

                                                            }

                                                    }
                                                    //generic categoies (key : value)
                                                    else if(preg_match_all("/<td(.*?)>(.*?)<\/td>/", $matches[3], $fields)){

                                                            foreach($fields[2] as $key => $field){

                                                                    if($field == "&nbsp;") continue;

                                                                    if(strstr($fields[1][$key], "colspan")){

                                                                            $value = trim(preg_replace('/&nbsp;/', '', preg_replace('/<[^>]*>/', '', (preg_replace('/\'/', '\"', preg_replace('/\s+/', ' ', $fields[2][$key+1]))))));

                                                                            $field = substr($field, 0, 30);
                                                                            ${$var}[$field] = $value;

                                                                    }else if(strstr($fields[1][$key], "rowspan")){

                                                                            preg_match('/[\d\.]+/', $fields[1][$key], $number);
                                                                            for($i=0; $i < $number[0]; $i=$i+2){

                                                                                    $value = trim(preg_replace('/&nbsp;/', '', preg_replace('/<[^>]*>/', '', (preg_replace('/\'/', '\"', preg_replace('/\s+/', ' ', $fields[2][$key+$i+2]))))));

                                                                                    $field = $field . " " . $fields[2][$key+$i+1];
                                                                                    $field = substr($field, 0, 30);
                                                                                    ${$var}[$field] = $value;
                                                                            }
                                                                    }
                                                            }
                                                    }
                                                    else{
                                                            $value = trim(preg_replace('/&nbsp;/', '', preg_replace('/<[^>]*>/', '', (preg_replace('/\'/', '\"', preg_replace('/\s+/', ' ', $matches[3]))))));
                                                            $field = substr($fields_category, 0, 30);
                                                            ${$var}[$field] = $value;
                                                    }
                                            }

                                            $matches = array();
                                    }
                            }

                            $width = "";

                            $height = "";

                            $length = "";

                            $weight = "";

                            // scrape image link
                            $matches = array();
                            preg_match("/<div class=\"productblock\"(.*?)<img(.*?)src=\"(.*?)\"/si", $page_html, $matches);

                            if (isset($matches[3]) && $matches[3] != "") {
                                    $image = "http://www.pall.com" . $matches[3];
                            }
                            else{
                                    $image = "";
                            }

                            $matches = array();
                            preg_match("/<div id=\"breadcrumbnavbar\">(.*?)<\/div>/si", $page_html, $matches);

                            preg_match_all("/<a(.*?)>(.*?)<\/a>/si", $matches[1], $categories);


                            // scrape categories
                            $mincategory  = "";
                            $subcategory  = "";
                            $subcategory2 = "";
                            $subcategory3 = '';

                            if(is_array($categories[2])){
                                    $categories[2] = array_slice($categories[2], 0, count($categories[2]) - 1);

                                    if(isset($categories[2][0]) && $categories[2][0]!="Products" && $categories[2][0]!="Product Details"){
                                            $mincategory  = $categories[2][0];
                                    }
                                    if(isset($categories[2][1]) && $categories[2][1]!="Products" && $categories[2][1]!="Product Details"){
                                            $subcategory  = $categories[2][1];
                                    }
                                    if(isset($categories[2][2]) && $categories[2][2]!="Products" && $categories[2][2]!="Product Details"){
                                            $subcategory2 = $categories[2][2];
                                    }
                                    if(isset($categories[2][3]) && $categories[2][3]!="Products" && $categories[2][3]!="Product Details"){
                                            $subcategory3 = $categories[2][3];
                                    }
                            }

                            // main fields
                            $products[]  = array (
                                    'title'            => $title,
                                    'SKU'              => $sku,
                                    'description'      => $desc,
                                    'technical'        => $technical,
                                    'mincategory'      => $mincategory,
                                    'subcategory'      => $subcategory,
                                    'subcategory2'     => $subcategory2,
                                    'subcategory3'     => $subcategory3,
                                    'manufacturer'     => $manufacturer,
                                    'mainimage'        => $image,
                                    "weight"           => $weight,
                                    'width'			   => $width,
                                    'height'		   => $height,
                                    'length'           => $length,
                            );

            //		die(print_r($product));

                            // additional fields
                            $prod_additional_fields = array();
                            foreach($fields_categories as $fields_category => $var) {
                                    $prod_additional_fields = array_merge($prod_additional_fields, ${$var});
                            }

                            $prods_additional_fields[] = $prod_additional_fields;

                        }
                    }
                
                die(print_R($products));
                return array("products" => $products, "products_additional_fields" => $prods_additional_fields);
	}
	// method for scraping category link and pushing it to queue
	function get_categories_urls($page_html, $url) {

		preg_match("/solr.total(.*?);/si", $page_html, $number_products);
		preg_match('/[\d\.]+/', $number_products[1], $number_products);

		$number_pages = intVal($number_products[0] / 10);
		for($i=1; $i < $number_pages; $i++){
			$start = $i * 10;
			$url_category = array($url .  "?start=" . $start);
			$this->push_urls('cat', $url_category);
		}
	}
	// method for scraping product link and pushing it to queue
	function get_products_urls($page_html, $url)
	{
		preg_match_all("/<div(.*?)class=\"lisPName\"(.*?)>(.*?)<\/div>/si", $page_html, $matches);

		if(isset($matches[3]) && count($matches[3]) > 0){
			foreach($matches[3] as $match){
				preg_match("/<a href=\"(.*?)\">(.*?)<\/a>/si", $match, $product_link);
				$this->push_urls('prod', array("http://www.pall.com/".	$product_link[1]));
			}
		}
	}

	private function _calculate_weight($weight_text){
		if(strstr($weight_text, "lb") && strstr($weight_text, "lbs")){
			preg_match('/[\d\.]+/', $weight_text, $weight);
			return $weight[0];
		}else if(strstr($weight_text, "kg")){
			preg_match('/[\d\.]+/', $weight_text, $weight);
			return $weight[0] * 2.2046;
		}else{
			return false;
		}
	}
	private function _calculate_dimension($dimension){

		if(strstr($dimension, "mm")){
			preg_match('/[\d\.]+/', $dimension, $number);
			// mm to inch
			$dimension = $number[0] * 0.039370;
		}
		else if(strstr($dimension, "cm")){
			preg_match('/[\d\.]+/', $dimension, $number);
			// cm to inch
			$dimension = $number[0] * 0.3937;
		}
		else if(strstr($dimension, "m")){
			preg_match('/[\d\.]+/', $dimension, $number);
			//m to inch
			$dimension = $number[0] * 39.370;
		}
		else if(strstr($dimension, "in")){
			//inches
			preg_match('/[\d\.]+/', $dimension, $number);
			$dimension = $number[0];
		}
		else{
			$dimension = false;
		}

		return $dimension;
	}
}

