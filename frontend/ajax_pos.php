<?php
/* Copyright (C) 2011       Juanjo Menent   <jmenent@2byte.es>
 * Copyright (C) 2011       Jorge Donet
 * Copyright (C) 2012-2017  Ferran Marcet   <fmarcet@2byte.es>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU  *General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 */

/**
 *	\file       htdocs/pos/ajax_pos.php
 *	\ingroup    ticket
 *	\brief      Tickets home page
 *	\version    $Id: ajax_pos.php,v 1.2 2011-06-30 11:00:41 jdonet Exp $
*/
$res=@include("../../main.inc.php");                                   // For root directory
if (! $res) $res=@include("../../../main.inc.php");                // For "custom" directory

require_once(DOL_DOCUMENT_ROOT."/core/lib/functions.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formfile.class.php");
require_once(DOL_DOCUMENT_ROOT ."/core/class/notify.class.php");
dol_include_once('/pos/class/pos.class.php');

global $langs;

//if (!$user->rights->pos->lire) accessforbidden();
$data = file_get_contents('php://input');
$data = json_decode($data, true);
$langs->load("pos@pos");
$html = '';
$action = GETPOST('action');
$category = GETPOST('category');
$ticketstate = GETPOST('ticketstate');
//$parentcategory = GETPOST('parentcategory');
$product_id = GETPOST('product');

if(empty($_SESSION["TERMINAL_ID"])){
	$fm["data"]=0;
	$fm["error"]["desc"] = $langs->trans("ErrSession");
	$fm["error"]["value"] = 99;
	echo json_encode($fm);
}

$ok = POS::checkTerminal();

if (! $ok > 0 ){
	$fm["data"]=0;
	$fm["error"]["desc"] = $langs->trans("ErrSession");
	$fm["error"]["value"] = 99;
	echo json_encode($fm);
}

else if($action=='doTranfer')
{
		$result = POS::doTranfer($data['data']['ammount']);
		echo json_encode($result);
}
else if($action=='getProducts')
{
		$products = POS::getProductsbyCategory($category,0, $ticketstate);
		echo json_encode($products, JSON_NUMERIC_CHECK);
}
else if($action=='getMoreProducts')
{
	$pag = intval(GETPOST('pag','int'));
	$categories = POS::getProductsbyCategory($category,$pag, $ticketstate);
	echo json_encode($categories);
}
else if($action=='getCategories')
{
	//$parentcategory = intval($data['data']);
	$parentcategory = intval(GETPOST('parentcategory','int'));
	$categories = POS::getCategories($parentcategory);
	echo json_encode($categories,JSON_NUMERIC_CHECK);	
}
elseif($action=='newTicket')
{
		//$html.=	POS::CreateTicket();
		//$jorge = $html;
}
elseif($action=='getProduct')
{
	if(isset($data['data']))
	{
		$product = POS::getProductbyId($data['data']);
		echo json_encode($product,JSON_NUMERIC_CHECK);
	}
}
elseif($action=='getTicket')
{
	if(count($data))
	{
		$ticketId = $data['data'];
		$ticket = POS::getTicket($ticketId);
		echo json_encode($ticket, JSON_NUMERIC_CHECK);
	}
}
elseif($action=='getFacture')
{
	if(count($data))
	{
		$ticketId = $data['data'];
		$ticket = POS::getFacture($ticketId);
		echo json_encode($ticket ,JSON_NUMERIC_CHECK);
	}
}
elseif($action=='getHistory')
{
	$searchValue = '';
	if(count($data))
	{
		$searchValue = $data['data']['search'];
		$stat = $data['data']['stat'];
	}
	$history = POS::getHistoric($searchValue,$stat);
	echo json_encode($history);
}
elseif($action=='getHistoryFac')
{
	$searchValue = '';
	if(count($data))
	{
		$searchValue = $data['data']['search'];
		$stat = $data['data']['stat'];
	}
	$history = POS::getHistoricFac($searchValue,$stat);
	echo json_encode($history);
}
elseif($action=='countHistory')
{
	$history = POS::countHistoric();
	echo json_encode($history);
}
elseif($action=='countHistoryFac')
{
	$history = POS::countHistoricFac();
	echo json_encode($history);
}
elseif($action=='saveTicket')
{
	$result = POS::SetTicket($data);
	echo json_encode($result);
}
elseif($action=='searchProducts')
{
	if(count($data))
	{
		$searchValue = $data['data']['search'];
		$warehouse = $data['data']['warehouse'];
		$ticketstate = $data['data']['ticketstate'];
		$customerId = $data['data']['customer'];
		$result = POS::SearchProduct($searchValue, false, $warehouse,1, $ticketstate, $customerId);
		echo json_encode($result);
		
	}
}
elseif($action=='countProduct')
{
	$warehouseId = $data['data'];
	$stock = POS::countProduct($warehouseId);
	echo json_encode($stock);
}
elseif($action=='searchStocks')
{
	if(count($data))
	{
		$searchValue = $data['data']['search'];
		$mode = $data['data']['mode'];
		$warehouse = $data['data']['warehouse'];
		$ticketstate = 0;
		$customerId = 0;
		$result = POS::SearchProduct($searchValue,true,$warehouse,$mode, $ticketstate, $customerId);
		echo json_encode($result);
		
	}
}
elseif($action=='searchCustomer')
{
	if(count($data))
	{
		$searchValue = $data['data'];
		$result = POS::SearchCustomer($searchValue,false);
		echo json_encode($result);
		
	}
}
elseif($action=='addCustomer')
{
	if(count($data))
	{
		$customer = $data['data'];
		$result = POS::SetCustomer($customer);
		echo json_encode($result);
		
	}
}
elseif($action=='addNewProduct')
{
	if(count($data))
	{
		$product = $data['data'];
		$result = POS::SetProduct($product);
		echo json_encode($result);
		
	}
}
elseif($action=='getMoneyCash')
{
	$result = POS::getMoneyCash();
	echo json_encode($result, JSON_NUMERIC_CHECK);
}
elseif($action=='getConfig')
{
	$result = POS::getConfig();
	echo json_encode($result ,JSON_NUMERIC_CHECK);
}
elseif($action=='closeCash')
{
	if(count($data))
	{
		$cash = $data['data'];
		$result = POS::setControlCash($cash);
		echo json_encode($result);
		
	}
}
elseif($action=='getPlaces')
{
	$places = POS::getPlaces();
	echo json_encode($places);
	
}
elseif($action=='SendMail')
{
	$email = $data['data'];
	$result = POS::sendMail($email);
	echo json_encode($result);

}
elseif($action=='deleteTicket')
{
	$idticket = $data['data'];
	$result = POS::Delete_Ticket($idticket);
	echo json_encode($result);

}
elseif($action=='Translate')
{
	if(count($data))
	{
		echo json_encode($langs->trans($data['data']));
	}
}
elseif($action=='calculePrice')
{
	if(count($data))
	{
		$product = $data['data'];
		$result = POS::calculePrice($product);
		echo json_encode($result, JSON_NUMERIC_CHECK);
	}
}
elseif($action=='getLocalTax')
{
	if(count($data))
	{
		$data = $data['data'];
		$result = POS::getLocalTax($data);
		echo json_encode($result);
	}
}
elseif($action=='getNotes')
{
	$mode = $data['data'];
	$result = POS::getNotes($mode);
	echo json_encode($result);
}
elseif($action=='getWarehouse')
{
	$result = POS::getWarehouse();
	echo json_encode($result);
}
elseif($action=='checkPassword')
{
	$pass = $data['data']['pass'];
	$login = $data['data']['login'];
	$userid = $data['data']['userid'];
	$result = POS::checkPassword($login, $pass, $userid);
	echo json_encode($result);
}
elseif($action=='searchCoupon')
{
	$customerId = $data['data'];
	$result = POS::searchCoupon($customerId);
	echo json_encode($result);
}
elseif($action=='addPrint')
{
	$addprint = $data['data'];
	$result = POS::addPrint($addprint);
	echo json_encode($result);
}
elseif($action=='getWarehosuesTo')
{
	echo json_encode(POS::get_warehouse_to($data['data']));
}

elseif($action=='stockTranfer')
{
	echo json_encode(POS::stockTranfer($data['data']),JSON_NUMERIC_CHECK);
}
elseif($action=='CheckProductsReturned')
{
	//dol_include_once('/pos/class/pos_facture.class.php');
	dol_include_once('/pos/class/facturesim.class.php');
	echo json_encode(facturesim::SearchProductsReturned($data['data']),JSON_NUMERIC_CHECK);
}
elseif($action=='GetCouponByRef')
{
	//dol_include_once('/pos/class/pos_facture.class.php');
	dol_include_once('/pos/class/facturesim.class.php');
	echo json_encode(facturesim::getAvailableDiscounts($data['data']),JSON_NUMERIC_CHECK);
}
elseif($action=='getProductStock')
{
	$productId 		= $data['data']['productId'];
	$warehouseId	= $data['data']['warehouseId'];
	$type			= $data['data']['type'];
	echo json_encode(array ('stock'=> POS::getProductStock($productId, $warehouseId, $type)),JSON_NUMERIC_CHECK);
}


echo $html;
