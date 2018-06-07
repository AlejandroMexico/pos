<?php
/* Copyright (C) 2013 		Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2013-2017 	Ferran Marcet		  <fmarcet@2byte.es>
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
 * or see http://www.gnu.org/
 */

/**
 *	    \file       htdocs/esaeb/admin/about.php
 *      \ingroup    esaeb
 *      \brief      Page about
 */

$res=@include("../../main.inc.php");					// For root directory
if (! $res) $res=@include("../../../main.inc.php");		// For "custom" directory
dol_include_once("/pos/backend/lib/pos.lib.php");

global $user, $langs, $db;

if (!$user->admin) accessforbidden();


$langs->load("admin");
$langs->load("other");
$langs->load("pos@pos");


/**
 * View
 */

$helpurl='EN:Module_DoliPos|FR:Module_DoliPos_FR|ES:M&oacute;dulo_DoliPos';
llxHeader('',$langs->trans("About"), $helpurl);

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("About"),$linkback,'title_setup');

$head=posadmin_prepare_head();

dol_fiche_head($head, 'about', $langs->trans("POS"), 0, 'pos@pos');

print $langs->trans("AboutInfo").'<br>';
print '<br>';

print $langs->trans("MoreModules").'<br>';
print '&nbsp; &nbsp; &nbsp; '.$langs->trans("MoreModulesLink").'<br>';
print '&nbsp; &nbsp; &nbsp; '.$langs->trans("ModulesHelp");
print '<br>';
print '<br>';
$url='https://shop.2byte.es/';
print '<a href="'.$url.'" target="_blank"><img border="0" src="'.dol_buildpath('/pos/img/logo.png',1).'"></a>';


print '<br>';

dol_fiche_end();


llxFooter();

$db->close();

