<?php
// Paul Symansky, copyright 2010 - 2012
// Created Nov. 8, 2010
// Last updated Oct. 29, 2012

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// THE FOLLOWING SECTIONS NEED YOUR ATTENTION
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// First database information
// Enter the relevent information about the database that stores your osCommerce data below.

// Database name
$db1_name = "db183326312";

// Database location
$db1_loc = "db381.perfora.net";

// Database username
$db1_user = "dbo183326312";

// Database password
$db1_pwd = "amo1cb10";

// Second database information
// Enter the relevent information about the database that stores the state and country information tables you created with 'setup.sql'.
// These tables can be located in the same database as your osCommerce, in which case you copy the information from above. If they're 
//  located elsewhere, enter the appropriate information here.

// Database name
$db2_name = "db249647416";

// Database location
$db2_loc = "db1540.perfora.net";

// Database username
$db2_user = "dbo249647416";

// Database password
$db2_pwd = "BFhtvv5g";

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// NO FURTHER SECTIONS REQUIRE YOUR ATTENTION
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

error_reporting(E_ERROR | E_WARNING | E_PARSE);

// Connect to osCommerce DB
$link = mysql_connect($db1_loc, $db1_user, $db1_pwd) or die(mysql_error());
mysql_select_db($db1_name, $link) or die(mysql_error());

	// Die if there is no order number provided
	if(!isset($_GET['oID'])){
		die();
	}

// Retrieve the relevent data from 'orders'
$result = mysql_query("SELECT customers_id, delivery_name, delivery_company, delivery_street_address, delivery_suburb, delivery_city, delivery_postcode, delivery_state, delivery_country 
	FROM orders WHERE orders_id = " . $_GET['oID'])
		or die(mysql_error());  
$order_row = mysql_fetch_array($result);

	// Die if there is no such order
	if(!$order_row){
		die();
	}

// Retrieve the relevent data from 'customers'
$result = mysql_query("SELECT customers_email_address, customers_telephone 
	FROM customers WHERE customers_id = " . $order_row['customers_id'])
		or die(mysql_error());  
$customer_row = mysql_fetch_array($result);

// Retrieve the relevent data from 'orders_total'
$result = mysql_query("SELECT text 
	FROM orders_total WHERE orders_id = " . $_GET['oID'] . " AND class='ot_subtotal'")
		or die(mysql_error());  
$order_subtotal = mysql_result($result, 0);
$order_subtotal = str_replace("$", "", $order_subtotal);
$result = mysql_query("SELECT text 
	FROM orders_total WHERE orders_id = " . $_GET['oID'] . " AND class='ot_total'")
		or die(mysql_error());  
$order_total = mysql_result($result, 0);
$order_total = str_replace("$", "", $order_total);

$result = mysql_query("SELECT text 
	FROM orders_total WHERE orders_id = " . $_GET['oID'] . " AND (class='ot_shipping' OR LOWER(title) LIKE '%shipping%')")
		or die(mysql_error());
$order_shipping = mysql_result($result, 0);
$order_shipping = str_replace("$", "", strip_tags($order_shipping));

$result = mysql_query("SELECT text 
	FROM orders_total WHERE orders_id = " . $_GET['oID'] . " AND (class='ot_insurance' OR LOWER(title) LIKE '%insurance%')")
		or die(mysql_error());  
$order_insurance = false;
$order_insurance = (mysql_num_rows($result) > 0) ? "TRUE" : "FALSE";

// Close MySQL link
mysql_close($link);


// Connect to general site DB
$link = mysql_connect($db2_loc, $db2_user, $db2_pwd) or die(mysql_error());
mysql_select_db($db2_name, $link) or die(mysql_error());

// Find the user's country code and state code (if in US)
$order_row['delivery_country'] = strtoupper(trim($order_row['delivery_country']));
$result = mysql_query("SELECT * FROM bc_countries WHERE MATCH (country) AGAINST ('" . $order_row['delivery_country'] . "')") 
		or die(mysql_error()); 
$country_info = (!mysql_num_rows($result)) ? false : mysql_fetch_array($result);
$country_code = $country_info['iso_country_code'];
$country = $country_info['country'];
$usps_country_code = $country_info['new_usps_country_code'];
$country_err = "false";

$order_row['delivery_state'] = strtoupper(trim($order_row['delivery_state']));
$state = $order_row['delivery_state'];
$state_err = "false";

// The country isn't listed in the USPS database, it must be a US territory or there's an error
if(!$country_info){
	switch($order_row['delivery_country']){
		case "AMERICAN SAMOA": 						$country = "UNITED STATES";	$country_code = "US";	$usps_country_code = "840";	$state = "AS"; break;
		case "GUAM": 								$country = "UNITED STATES";	$country_code = "US";	$usps_country_code = "840";	$state = "GU"; break;
		case "MARSHALL ISLANDS":		 			$country = "UNITED STATES";	$country_code = "US";	$usps_country_code = "840";	$state = "MH"; break;
		case "MICRONESIA, FEDERATED STATES OF":		$country = "UNITED STATES";	$country_code = "US";	$usps_country_code = "840";	$state = "FM"; break;
		case "NORTHERN MARIANA ISLANDS":		 	$country = "UNITED STATES";	$country_code = "US";	$usps_country_code = "840";	$state = "MP"; break;
		case "PUERTO RICO":		 					$country = "UNITED STATES";	$country_code = "US";	$usps_country_code = "840";	$state = "PR"; break;
		case "PALAU":		 						$country = "UNITED STATES";	$country_code = "US";	$usps_country_code = "840";	$state = "PW"; break;
		case "VIRGIN ISLANDS (U.S.)": 		 		$country = "UNITED STATES";	$country_code = "US";	$usps_country_code = "840";	$state = "VI"; break;
		default: $country = $order_row['delivery_country']; $country_code = ""; $country_err = "true";
	}
}

	// Take care of US states
if(strcasecmp($country, "UNITED STATES") == 0){																									// The customer used a two letter state code
	if(strlen($state) == 2){
		if(!mysql_num_rows(mysql_query("SELECT * FROM bc_states WHERE state_code = '" . $state . "'"))){										// The two letter state code was invalid
			$state = $order_row['delivery_state'];
			$state_err = "true";
		}
	}else{																																		// The customer used a full state name
		$result = mysql_query("SELECT state_code FROM bc_states WHERE MATCH (state) AGAINST ('" . $order_row['delivery_state'] . "')") 			// Find the corresponding two letter state code
			or die(mysql_error());  
		$state = (!mysql_num_rows($result)) ? false : mysql_result($result, 0);

		if(!state){																																// The full state name was invalid
			$state = $order_row['delivery_state'];
			$state_err = "true";
		}
	}
}

// Close MySQL link
mysql_close($link);


// Format the telephone number
$customer_row['customers_telephone'] = preg_replace('[\D]', '', $customer_row['customers_telephone']);


// Print the contents of the entries 
$xml = new DOMDocument('1.0', 'ASCII');
$xml->formatOutput = true;

$order = $xml->createElement("order");
$xml->appendChild($order);

$delivery_name = $xml->createElement("delivery_name");																							// Delivery name
$delivery_name->appendChild($xml->createTextNode(xml_encode($order_row['delivery_name'])));
$order->appendChild($delivery_name);

$delivery_company = $xml->createElement("delivery_company");																					// Delivery company
$delivery_company->appendChild($xml->createTextNode(xml_encode($order_row['delivery_company'])));
$order->appendChild($delivery_company);

$delivery_street_address = $xml->createElement("delivery_street_address");																		// Delivery street address
$delivery_street_address->appendChild($xml->createTextNode(xml_encode($order_row['delivery_street_address'])));
$order->appendChild($delivery_street_address);

$delivery_street_address2 = $xml->createElement("delivery_street_address2");																	// Delivery street address 2
if($state != "PR"){
	$delivery_street_address2->appendChild($xml->createTextNode(xml_encode($order_row['delivery_suburb'])));
}
$order->appendChild($delivery_street_address2);

$delivery_street_address3 = $xml->createElement("delivery_street_address3");																	// Delivery street address 3/urbanization
if($state == "PR"){
	$delivery_street_address3->appendChild($xml->createTextNode(xml_encode($order_row['delivery_suburb'])));
}
$order->appendChild($delivery_street_address3);

$delivery_city = $xml->createElement("delivery_city");																							// Delivery city
$delivery_city->appendChild($xml->createTextNode(xml_encode($order_row['delivery_city'])));
$order->appendChild($delivery_city);

$delivery_postcode = $xml->createElement("delivery_postcode");																					// Delivery post code
$delivery_postcode->appendChild($xml->createTextNode(xml_encode($order_row['delivery_postcode'])));
$order->appendChild($delivery_postcode);

$delivery_state = $xml->createElement("delivery_state");																						// Delivery state
$delivery_state->appendChild($xml->createTextNode(xml_encode($state)));
$delivery_state->setAttribute("error", $state_err);
$order->appendChild($delivery_state);

$delivery_country = $xml->createElement("delivery_country");																					// Delivery country
$delivery_country->appendChild($xml->createTextNode(xml_encode($country)));
$delivery_country->setAttribute("error", $country_err);
$order->appendChild($delivery_country);

$delivery_country_code = $xml->createElement("delivery_country_code");																			// Delivery country code
$delivery_country_code->appendChild($xml->createTextNode(xml_encode($country_code)));
$order->appendChild($delivery_country_code);

$delivery_usps_country_code = $xml->createElement("delivery_usps_country_code");																// USPS country code
$delivery_usps_country_code->appendChild($xml->createTextNode(xml_encode($usps_country_code)));
$order->appendChild($delivery_usps_country_code);

$customers_email_address = $xml->createElement("customers_email_address");																		// Customer's email address
$customers_email_address->appendChild($xml->createTextNode(utf8_encode($customer_row['customers_email_address'])));
$order->appendChild($customers_email_address);

$customers_telephone = $xml->createElement("customers_telephone");																				// Customer's telephone number
$customers_telephone->appendChild($xml->createTextNode(xml_encode($customer_row['customers_telephone'])));
$order->appendChild($customers_telephone);

$shipping = $xml->createElement("shipping");																									// Shipping
$shipping->appendChild($xml->createTextNode(xml_encode($order_shipping)));
$order->appendChild($shipping);

$insurance = $xml->createElement("insurance");																									// Insurance
$insurance->appendChild($xml->createTextNode(xml_encode($order_insurance)));
$order->appendChild($insurance);

$subtotal = $xml->createElement("subtotal");																									// Order subtotal
$subtotal->appendChild($xml->createTextNode(xml_encode($order_subtotal)));
$order->appendChild($subtotal);

$total = $xml->createElement("total");																											// Order total
$total->appendChild($xml->createTextNode(xml_encode(strip_tags($order_total))));
$order->appendChild($total);

$weight = $xml->createElement("weight");																										// Order weight
$weight->appendChild($xml->createTextNode(xml_encode("0.00")));
$order->appendChild($weight);

$weight = $xml->createElement("weight_g");																										// Order weight (g)
$weight->appendChild($xml->createTextNode(xml_encode("0.00")));
$order->appendChild($weight);

$weight = $xml->createElement("weight_lbs");																									// Order weight (lbs)
$weight->appendChild($xml->createTextNode(xml_encode("0")));
$order->appendChild($weight);

$weight = $xml->createElement("weight_oz");																										// Order weight (oz)
$weight->appendChild($xml->createTextNode(xml_encode("0.0")));
$order->appendChild($weight);

header("Content-Type: text/xml");
echo $xml->saveXML();



// Encode characters to be DOM compatible (non-international characters only) and upper case
function xml_encode($str){
	/*$trans = array(
		"À" => "A", 
		"Á" => "A", 
		"Â" => "A",
		"Ã" => "A",
		"Ä" => "A",
		"Å" => "A",
		"Æ" => "AE",
		"Ç" => "C",
		"È" => "E",
		"É" => "E",
		"Ê" => "E",
		"Ë" => "E",
		"Ì" => "I",
		"Í" => "I",
		"Î" => "I",
		"Ï" => "I",
		"Ð" => "D",
		"Ñ" => "N",
		"Ò" => "O",
		"Ó" => "O",
		"Ô" => "O",
		"Õ" => "O",
		"Ö" => "O",
		"Ø" => "O",
		"Ù" => "U",
		"Ú" => "U",
		"Û" => "U",
		"Ü" => "U",
		"Ý" => "Y",
		"Þ" => "TH",
		"ß" => "S",
		"à" => "a",
		"á" => "a",
		"â" => "a",
		"ã" => "a",
		"ä" => "a",
		"å" => "a",
		"æ" => "ae",
		"ç" => "c",
		"è" => "e",
		"é" => "e",
		"ê" => "e",
		"ë" => "e",
		"ì" => "i",
		"í" => "i",
		"î" => "i",
		"ï" => "i",
		"ð" => "o",
		"ñ" => "n",
		"ò" => "o",
		"ó" => "o",
		"ô" => "o",
		"õ" => "o",
		"ö" => "o",
		"ø" => "o",
		"ù" => "u",
		"ú" => "u",
		"û" => "u",
		"ü" => "u",
		"ý" => "y",
		"þ" => "th",
		"ÿ" => "y");*/

	//return utf8_encode(mb_strtoupper(strtr($str, $trans)));
	return utf8_encode(mb_strtoupper($str));
}

?>