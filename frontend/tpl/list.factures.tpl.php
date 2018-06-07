<p><?php echo $langs->trans($title); ?></p>

<table class="liste_articles">
	<tr class="titres">
		<th><?php echo $langs->trans("Facture"); ?></th>
		<th><?php echo $langs->trans("Total"); ?></th>
	</tr>
<?php if (is_array($factures)) {
	$subtotal = 0;
	foreach ($factures as $facture) {?>
	<tr>
		<td align="left"><?php echo $facture['facture'];?> </td>
		<td align="right"><?php echo price($facture['amount']); $subtotal+=$facture['amount'];?> </td>
	</tr>	
<?php }?>
</table>
<table class="totaux">	
	<tr>
		<th nowrap="nowrap"><?php echo $langs->trans($total_title); ?></th>
		<td nowrap="nowrap"><?php echo price($subtotal)." ".$currency;?>"</td>
	</tr>
<?php } else {?>
	<tr>
		<td align="left"><?php echo $langs->Trans($no_info); ?></td>
	</tr>
<?php }?>		
</table>
<br>
