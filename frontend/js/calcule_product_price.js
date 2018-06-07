
function calcule_product_price(product, ticket) {
  
  var result = new Object();
    if(typeof product["tva_tx"] == 'undefined'){
    	var txtva =0;
    } else {
    	var txtva = product["tva_tx"]
    }
    var pu_ht =  product["pu_ht"] * (1 - (product["discount"] / 100));
	pu_ht =  pu_ht * (1 - (ticket["discount_global"] / 100));
	result["pu_min"] = Math.round10(parseFloat(pu_ht),-2);
	result["pu_tva"] = Math.round10(parseFloat(pu_ht * (txtva / 100)),-2);
	result["pu_ttc"] = pu_ht * (1 + (txtva / 100));  

	result["total_ht"] = Math.round10(parseFloat(pu_ht * product["qty"]),-2);
	result["total_tva"] = Math.round10(parseFloat(result["total_ht"] * (txtva / 100)),-2);		
	result["total_ttc"] = result["total_ht"] * (1 + (txtva / 100));
	result["tot_price_ttc"] = product["price"] * product["qty"] * (1 + (txtva / 100));
    
    total_price_localtax =0;
    pu_localtax =0;
    result["total_localtax1"] = 0;
    result["total_localtax2"] = 0;
    if ((ticket["uselocaltax1_rate"] > 0) && (check_apply_tax(product['localtax1_type'],product["type"]))) {

  		result["total_localtax1"] = Math.round10(parseFloat((result["total_ttc"] * (1 + ( product['localtax1_tx'] / 100))) - result["total_ttc"]),-2);
  		total_price_localtax = (result["tot_price_ttc"] * (1 + ( product['localtax1_tx'] / 100))) - result["tot_price_ttc"];
  		pu_localtax = (result["pu_ttc"] * (1 + ( product['localtax1_tx'] / 100))) - result["pu_ttc"];
    }

    if ((ticket["uselocaltax2_rate"] > 0) && (check_apply_tax(product['localtax2_type'],product["type"]))) {
  	
  		result["total_localtax2"] = Math.round10(parseFloat((result["total_ttc"] * (1 + ( product['localtax2_tx'] / 100))) - result["total_ttc"]),-2);
  		total_price_localtax =  total_price_localtax + ($result["tot_price_ttc"] * (1 + ( $product['localtax2_tx'] / 100))) - $result["tot_price_ttc"];
  		pu_localtax = pu_localtax + (result["pu_ttc"] * (1 + ( product['localtax2_tx'] / 100))) - result["pu_ttc"];
    }

    result["pu_ttc"] 		= Math.round10(parseFloat(result["pu_ttc"] + pu_localtax),-2);
    result["total_ttc"]	= Math.round10(parseFloat(result["total_ttc"] + result["total_localtax1"] + result["total_localtax2"]),-2);
    result["tot_price_ttc"]	= Math.round10(parseFloat(result["tot_price_ttc"] + total_price_localtax),-2);
    return result;
}

function check_apply_tax(tax_type, type) {
	switch(tax_type) {
      	case 1:     // localtax on product or service
        	return  true;
        	break;
       	case 2:     // localtax on product or service
        	return  true;
        	break;  
      	case 3:     // localtax on product
        	if (type == 0) return  true;
        	break;
      	case 4:     // localtax on product
        	if (type == 0) return  true;
        	break;
      	case 5:     // localtax on service
        	if (type == 1) return  true;
        	break;
      	case 6:     // localtax on service
        	if (type == 1) return  true;
        	break;
    }
    return false;
}