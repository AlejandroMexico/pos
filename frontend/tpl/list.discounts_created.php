<?php 
dol_include_once('/pos/class/pos_discount.class.php');
$pos_discount = new PosDiscount($db);
$factures = $pos_discount->fetchCreatedInRange($terminal, $date_start, $date_end);
$title = "DevolutionsCreated";
$total_title = "TotalDevolutions";
$no_info = "NoDevolution";
require(dol_buildpath('/pos/frontend/tpl/list.factures.tpl.php'));
$factures = $pos_discount->fetchUsedInRange($terminal, $date_start, $date_end);
$title = "DevolutionsApplied";
$total_title = "TotalCouponsAplplied";
require(dol_buildpath('/pos/frontend/tpl/list.factures.tpl.php'));
?>