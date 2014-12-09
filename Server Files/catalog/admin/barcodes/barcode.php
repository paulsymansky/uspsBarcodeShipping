<?php
// barcode.php
//
// Paul Symansky, copyright 2010
//
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// GPLv3 License
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
//    This file is part of Barcode USPS Shipping.
//
//    Barcode USPS Shipping is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 3 of the License, or
//    (at your option) any later version.
//
//    Barcode USPS Shipping is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with Barcode USPS Shipping.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	require("php-barcode.php");
	
	$oID = $_GET['oID'];
	$oID_string = str_pad($oID, 12, '0', STR_PAD_LEFT);
	
	$bars = barcode_encode($oID_string, 'EAN');
	barcode_outimage($bars['text'],$bars['bars'], 1, 'PNG', 30);
?>