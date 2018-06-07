<?php


$res=@include("../../../main.inc.php");                                   // For root directory
if (! $res) $res=@include("../../../../main.inc.php");                // For "custom" directory

dol_include_once('/pos/class/ticket.class.php');
dol_include_once('/pos/class/cash.class.php');
require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");
global $langs, $db, $mysoc, $conf;

$langs->load("main");
$langs->load("pos@pos");
$langs->load('users');
header("Content-type: text/html; charset=".$conf->file->character_set_client);
$id=GETPOST('id');
?>
<html>
<head>
<title>Print Arq Cash</title>
<link rel="stylesheet" type="text/css" href="../css/cash.tpl.css">
</head>

<body>

<?php

		// Cash
		
		$sql = "select ref, fk_user, date_c, fk_cash, amount_teor, amount_real";
    	$sql .=" from ".MAIN_DB_PREFIX."pos_control_cash";
    	$sql .=" where rowid = ".$id;
    	$result=$db->query($sql);
		
		if ($result)
		{
			$objp = $db->fetch_object($result);
        	$date_end = $objp->date_c;
        	$fk_user = $objp->fk_user;
        	$ref = $objp->ref;
        	$terminal = $objp->fk_cash;
        	$amount_teor = $objp->amount_teor;
        	$amount_real = $objp->amount_real;
        }
        $currency = $langs->trans(currency_name($conf->currency));
	?>

<div class="entete">
	<div class="logo">
	<?php print '<img src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=companylogo&amp;file='.urlencode('/thumbs/'.$mysoc->logo_small).'">'; ?>
	</div>
	<div class="infos">
		<p class="adresse"><?php echo $mysoc->name; ?><br>
		<?php echo $mysoc->idprof1; ?><br>
		<?php echo $mysoc->address; ?><br>
		<?php echo $mysoc->zip.' '.$mysoc->town; ?></p>
		<?php
			print '<p>'.$langs->trans("ArqCashReport").': '.$ref.'<br>';
			$cash = new Cash($db);
			$cash->fetch($terminal);
			print $langs->trans("Terminal").': '.$cash->name.'<br>';
			
			$userstatic=new User($db);
			$userstatic->fetch($fk_user);
			print $langs->trans("User").': '.$userstatic->firstname.' '.$userstatic->lastname.'</p>';
			print '<p class="date_heure">'.dol_print_date(($date_end),'dayhour').'</p>';
		?>
	</div>
</div> 	
<table class="totaux">
	<?php	
	echo '<tr><th nowrap="nowrap">'. $langs->trans("CashMoney").': </th><td nowrap="nowrap">'.price($amount_teor)." ".$currency."</td></tr>\n";	
	echo '<tr><th nowrap="nowrap">'. $langs->trans("MoneyInCash").': </th><td nowrap="nowrap">'.price($amount_real)." ".$currency."</td></tr>\n";
	echo '<tr><th nowrap="nowrap">'. $langs->trans("Diff").': </th><td nowrap="nowrap">'.price($amount_teor - $amount_real)." ".$currency."</td></tr>\n";
	?>
</table>

<script type="text/javascript">

	window.print();
	<?php if($conf->global->POS_CLOSE_WIN){?>
	window.close();
	<?php }?>
	
</script>

</body>