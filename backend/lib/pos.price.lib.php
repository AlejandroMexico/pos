<?php
/* Copyright (C) 2017  Boris Duin   <borisduin@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *   \brief    Library with functions to calculate prices optimized for POS. 
 */

function calcule_product_price($product)
{
	$result=array();	
	// Clean parameters
	$txtva= (empty($product["tva_tx"]))?0:$product["tva_tx"];
	// initialize total
	$pu_ht =  $product["pu_ht"] * (1 - ($product["discount"] / 100));
	$pu_ht =  $pu_ht * (1 - ($product["discount_global"] / 100));
	$result["pu_min"] = price2num($pu_ht,'MU');
	$result["pu_tva"] = price2num($pu_ht * ($txtva / 100),'MU');
	$result["pu_ttc"] = price2num($pu_ht * (1 + ($txtva / 100)), 'MU');  
	$result["total_ht"] = price2num($pu_ht * $product["qty"],'MT');
	$result["total_tva"] = price2num($result["total_ht"] * ($txtva / 100),'MT');		
	$result["total_ttc"] = price2num($result["total_ht"] * (1 + ($txtva / 100)),'MU');
	$result["tot_price_ttc"] = price2num($product["price"] * $product["qty"] * (1 + ($txtva / 100)),'MU');
    
    $total_price_localtax =0;
    $pu_localtax =0;
    $result["total_localtax1"] = 0;
    $result["total_localtax2"] = 0;
    if (($product["uselocaltax1_rate"]) && (check_apply_tax($product['localtax1_type'],$product["type"]))) {

  		$result["total_localtax1"] = price2num(($result["total_ttc"] * (1 + ( $product['localtax1_tx'] / 100))) - $result["total_ttc"], 'MT');
  		$total_price_localtax += ($result["tot_price_ttc"] * (1 + ( $product['localtax1_tx'] / 100))) - $result["tot_price_ttc"];
  		$pu_localtax += ($result["pu_ttc"] * (1 + ( $product['localtax1_tx'] / 100))) - $result["pu_ttc"];
    }

    if (($product["uselocaltax2_rate"]) && (check_apply_tax($product['localtax2_type'],$product["type"]))) {
  	
  		$result["total_localtax2"] = price2num(($result["total_ttc"] * (1 + ( $product['localtax2_tx'] / 100))) - $result["total_ttc"], 'MT');
  		$total_price_localtax += ($result["tot_price_ttc"] * (1 + ( $product['localtax2_tx'] / 100))) - $result["tot_price_ttc"];
  		$pu_localtax += ($result["pu_ttc"] * (1 + ( $product['localtax2_tx'] / 100))) - $result["pu_ttc"];
    }

    $result["pu_ttc"] 			= price2num($result["pu_ttc"] + $pu_localtax,'MT');
    $result["total_ttc"]		= price2num($result["total_ttc"] + $result["total_localtax1"] + $result["total_localtax2"],'MT');
    $result["tot_price_ttc"]	= price2num($result["tot_price_ttc"] + $total_price_localtax,'MT');

    $pu_ht_devise = $pu * $multicurrency_tx;
    if(isset($product['multicurrency_tx'])){
    	$result["pu_ht_devise"]  = $pu_ht * $product['multicurrency_tx'];
    	$result["multicurrency_total_ht"] = $result["total_ht"] * $product['multicurrency_tx'];
    	$result["multicurrency_total_tva"] = $result["total_tva"] * $product['multicurrency_tx'];
    	$result["multicurrency_total_ttc"] = $result["total_ttc"] * $product['multicurrency_tx'];
    }
	// If rounding is not using base 10 (rare)
	if (! empty($conf->global->MAIN_ROUNDING_RULE_TOT))
	{		
		foreach ($result as $i => $valor) {
			$result[$i]=round($valor/$conf->global->MAIN_ROUNDING_RULE_TOT, 0)*$conf->global->MAIN_ROUNDING_RULE_TOT;
		}		
	}
	
	dol_syslog('Price.lib::calcule_product_price MAIN_ROUNDING_RULE_TOT='.$conf->global->MAIN_ROUNDING_RULE_TOT.' pu='.$pu_ht.' qty='.$product["qty"].' price_base_type='.$product["price"].' total_ht='.$result["total_ht"] .'-total_vat='.$result["total_tva"].'-total_ttc='.$result["total_ttc"]);

	return $result;
}

function check_apply_tax($tax_type, $type) {
	switch($tax_type) {
      	case '1':     // localtax on product or service
        	return  true;
        	break;
       	case '2':     // localtax on product or service
        	return  true;
        	break;  
      	case '3':     // localtax on product
        	if ($type == 0) return  true;
        	break;
      	case '4':     // localtax on product
        	if ($type == 0) return  true;
        	break;
      	case '5':     // localtax on service
        	if ($type == 1) return  true;
        	break;
      	case '6':     // localtax on service
        	if ($type == 1) return  true;
        	break;
    }
    return false;
}