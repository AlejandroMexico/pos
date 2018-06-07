<?php
/* Copyright (C) 2017 Boris Duin           <borisduin@gmail.com>
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
 *	\class      POS
 *	\brief      Class for POS gestion
 */
require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");
dol_include_once('/pos/class/facturesim.class.php');
class Facture extends CommonObject
{
	public static function SearchProductsReturned($idFacture) {
	/*	global $db;
		$sql = "SELECT d.fk_product AS product, SUM(d.qty) AS qty FROM ";
		$sql .= MAIN_DB_PREFIX . "facturedet AS d LEFT JOIN ". MAIN_DB_PREFIX ."facture AS f "; 
		$sql .= "ON  d.fk_facture = f.rowid ";
		$sql .= "LEFT JOIN ". MAIN_DB_PREFIX ."product AS p ";
		$sql .= "ON  d.fk_product = p.rowid ";
		$sql .= "WHERE f.fk_facture_source = '".$db->escape(trim($idFacture))."' ";
		$sql .= "AND p.fk_product_type = 0 ";
		$sql .=	"GROUP BY d.fk_product";

		$res = $db->query($sql);

        if ($res) {
            $num = $db->num_rows($res);
            $i = 0;

            while ($i < $num) {
                $objp = $db->fetch_object($res);
                $ret[$objp->product] = $objp->qty;
                $i++;
        	}
        	return $ret;    
    	}
    	return -1; */
        return facturesim::getAvailableDiscounts($ref);
    }

    static public function getDiscountsByFacture($ref) {
        return facturesim::getAvailableDiscounts($ref);
    }

}