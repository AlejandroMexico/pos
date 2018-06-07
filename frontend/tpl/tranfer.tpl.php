<?php


$res=@include("../../../main.inc.php");                                   // For root directory
if (! $res) $res=@include("../../../../main.inc.php");                // For "custom" directory

require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
global $langs, $db, $mysoc, $conf;

$langs->load("main");
$langs->load("pos@pos");
$langs->load('users');
header("Content-type: text/html; charset=".$conf->file->character_set_client);
$from=GETPOST('from','int');
$to=GETPOST('to', 'int');
?>
<html>
<head>
<title><?php echo $langs->trans("Tranfer") ?></title>
<link rel="stylesheet" type="text/css" href="../css/cash.tpl.css">
</head>

<body>

<?php
	$linefrom=new AccountLine($db);
	$linefrom->fetch($from);

	$lineto=new AccountLine($db);
	$lineto->fetch($to);

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
			$userstatic=new User($db);
			$userstatic->fetch($linefrom->fk_user_author);
			echo $langs->trans("User").': '.$userstatic->firstname.' '.$userstatic->lastname.'</p>';
			echo'<p class="date_heure">'.dol_print_date(($linefrom->datec),'dayhourtextshort').'</p>';
		?>
		<p><?php echo $linefrom->label; ?> </p>
	</div>
</div>	
<table class="totaux">
	<?php
	
	echo '<tr><th nowrap="nowrap">'. $langs->trans("From").': </th><td nowrap="nowrap">'.$linefrom->bank_account_ref."-".$linefrom->bank_account_label."</td></tr>";
	echo '<tr><th nowrap="nowrap">'. $langs->trans("to").': </th><td nowrap="nowrap">'.$lineto->bank_account_ref."-".$lineto->bank_account_label."</td></tr>";
	echo '<tr><th nowrap="nowrap">'. $langs->trans("Amount").': </th><td nowrap="nowrap">'.price($lineto->amount)." ".$currency."</td></tr>";

	?>
</table>

<script type="text/javascript">

	window.print();
	<?php if($conf->global->POS_CLOSE_WIN){?>
	window.close();
	<?php }?>
	
</script>

</body>