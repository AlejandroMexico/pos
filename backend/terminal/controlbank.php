<?php
/* Copyright (C) 2011 		Juanjo Menent <jmenent@2byte.es>
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 */

/**
 *	\file       htdocs/pos/backend/terminal/cash.php
 *	\ingroup    pos
 *	\brief      Page to show a terminal
 *	\version    $Id: cash.php,v 1.4 2011-08-19 07:54:24 jmenent Exp $
 */

$res=@include("../../../main.inc.php");                                   // Forcustom" directory
if (! $res) $res=@include("../../../../main.inc.php");                // For "
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
dol_include_once('/pos/class/cash.class.php');

global $user,$langs, $conf,$db, $bc;

$action=GETPOST('action');
// Security check
$cashid = GETPOST("cashid");

if ($user->socid) $socid=$user->socid;

//$result = restrictedArea($user,'societe',$socid,'');
if (!$user->rights->pos->terms)

accessforbidden();
$search_name=trim(GETPOST('search_name'));
$search_user=trim(GETPOST('search_user'));
$cashname=trim(GETPOST('cashname'));
$sortfield = GETPOST('sortfield');
$sortorder = GETPOST('sortorder');
$page=GETPOST('page');

if (! $sortorder) $sortorder="ASC";
if (! $sortfield) $sortfield="name";
if ($page == -1) { $page = 0 ; }

$offset = $conf->liste_limit * $page ;
$pageprev = $page - 1;
$pagenext = $page + 1;

$langs->load("pos@pos");
$langs->load('users');

/*
 * Actions
 */

//Confirm

if ($action == 'confirm')
{
    $error=0;

    // Create account
    $id				= trim(GETPOST('id'));
    $account 		= trim(GETPOST('cash'.$id));
    $datec 			= dol_mktime(12,0,0,date("m"),date("d"),date("Y"));

    if (empty($account) or $account == '-1')
    {
        setEventMessage($langs->trans("ErrorFieldRequired",$langs->transnoentities("bank")),"errors");
            $error++;
    } else {
		$sql = "delete from ";
		$sql.=  MAIN_DB_PREFIX."pos_cash_bank_tranfer ";
		$sql.= " WHERE fk_cash = ".$id;

		$resql = $db->query($sql);
		
		$sql = "INSERT INTO ";
		$sql.=  MAIN_DB_PREFIX."pos_cash_bank_tranfer ";
		$sql.= " (fk_cash , fk_bank, fk_user, date_c, status ) VALUES (";
		$sql.= $id . "," . $account . "," . $user->id .",".$db->idate($datec).", 1) ";

		$resql = $db->query($sql);

	}	
}
// Recherche
$mode=GETPOST('mode');
$modesearch=GETPOST('mode-search');

/*
 * View
 */

$form=new Form($db);
$cashstatic=new Cash($db);
$helpurl='EN:Module_DoliPos|FR:Module_DoliPos_FR|ES:M&oacute;dulo_DoliPos';
llxHeader('',$langs->trans("Cash"),$helpurl);

// Do we click on purge search criteria ?
if (GETPOST("button_removefilter_x"))
{

    $cashname="";
	$search_name="";
}

if ($cashname)
{
	$search_name=$cashname;
}

/*
 * Mode Liste
 */

$title=$langs->trans("ListOfCash");

$sql = "SELECT c.rowid, c.name, c.fk_user_u, cp.fk_bank as fk_bank";
$sql.= ', u.firstname, u.lastname';
$sql.= " FROM ".MAIN_DB_PREFIX."pos_cash c";
$sql.= " left join  ".MAIN_DB_PREFIX."pos_cash_bank_tranfer cp";
$sql.= " ON c.rowid = cp.fk_cash";
$sql.= ' left join '.MAIN_DB_PREFIX.'user as u';
$sql.= " ON c.fk_user_u = u.rowid";

$sql.= " WHERE c.entity = ".$conf->entity;

if ($cashid)	$sql.= " AND rowid = ".$cashid;

if ($search_name)
{
	$sql.= " AND (";
	$sql.= "name LIKE '%".$db->escape($search_name)."%'";
	$sql.= ")";
}


// Count total nb of records
$nbtotalofrecords = 0;

if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST))
{
	$result = $db->query($sql);
	$nbtotalofrecords = $db->num_rows($result);
}

$sql.= $db->order($sortfield,$sortorder);
$sql.= $db->plimit($conf->liste_limit+1, $offset);

$resql = $db->query($sql);
if ($resql)
{
	$num = $db->num_rows($resql);
	$i = 0;

	$params = "&amp;cashname=".$socname."&amp;search_namem=".$search_name;

	print_barre_liste($title, $page, $_SERVER["PHP_SELF"],$params,$sortfield,$sortorder,'',$num,$nbtotalofrecords);

	$langs->load("other");
	$textprofid=array();
	

	print '<table class="liste" width="100%">';

    
    // Lines of titles
    print '<tr class="liste_titre">';
	print_liste_field_titre($langs->trans("CashS"),$_SERVER["PHP_SELF"],"name","",$params,"",$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Account"),$_SERVER["PHP_SELF"],"is_used","",$params,'',$sortfield,$sortorder);
	print "</tr>\n";

	print '<tr class="liste_titre">';
	print '<td class="liste_titre">';
	print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" name="formfilter">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
	print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
	print '<input class="flat" type="text" name="search_name" value="'.$search_name.'">';
	print '</td>';
	print '<td colspan="2" class="liste_titre" align="right">';
	print '<input type="image" class="liste_titre" name="button_search" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/search.png" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '&nbsp; ';
	print '<input type="image" class="liste_titre" name="button_removefilter" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/searchclear.png" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
	print '</form>';
	print '</td>';
	print "</tr>\n";

	$var=True;

	while ($i < min($num,$conf->liste_limit))
	{

		$obj = $db->fetch_object($resql);
		
		$cashwil = new Cash($db);
		$cashwil->name  = $obj->name;
		
		$var=!$var;
		print "<tr $bc[$var]>";
		
		print '<td width="30%">'.$cashwil->getNomUrl(1).'</td>';
		
        
        print '<td colspan=2>';
		
    	print '<form action="'.$_SERVER["PHP_SELF"].'" name="confirm" method="POST">';
		$form->select_comptes($obj->fk_bank,'cash'.$obj->rowid, 2, 'courant=2', 1);
		print ' <input type="hidden" name="id" value="'.$obj->rowid.'">'	;
		print '<input type="hidden" name="action" value="confirm">';
		print '<input class="button" type="submit" value="'.$langs->trans("Confirm").'">';
		print '</form>';	
		print '</td>';
	   	print '</tr>'."\n";
		$i++;
	}

	$db->free($resql);

	print "</table>";

	

}
else
{
	dol_print_error($db);
}


llxFooter();

$db->close();
