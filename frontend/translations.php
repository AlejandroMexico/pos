<?php 
$tranlations = Array();
$tranlations[] = "ErrTranferAmmountExceedAvailable";
$tranlations[] = "ErrTranferAmmountis0";
$tranlations[] = "DiffPrice";
$tranlations[] = "PriceMinError";
$tranlations[] = "NoStockEnough";
$tranlations[] = "CouponAdded";
$tranlations[] = "Warehouse";
$tranlations[] = "Units";
$tranlations[] = "Send";
$tranlations[] = "ReturnTicket";
$tranlations[] = "CreditSellNotAllowed";
$tranlations[] = "ChangeAmmountCantByMoreThanCashPayment";
$tranlations[] = "NoItemsToReturn";
$tranlations[] = "InvoiceIsAReturnInvocice";
$tranlations[] = "ReturnAmmountMustBeExact";

foreach ($tranlations as $key => $text) {
	echo "_TPV.translations['".$text."'] =\"".$langs->trans($text)."\";\n";	
}