<?php
/* Copyright (C) 2002-2007 Rodolphe Quiedeville  <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012 Laurent Destailleur   <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Sebastien Di Cintio   <sdicintio@ressource-toi.org>
 * Copyright (C) 2004      Benoit Mortier        <benoit.mortier@opensides.be>
 * Copyright (C) 2005      Marc Barilley / Ocebo <marc@ocebo.com>
 * Copyright (C) 2005-2012 Regis Houssin         <regis.houssin@capnetworks.com>
 * Copyright (C) 2006      Andre Cianfarani      <acianfa@free.fr>
 * Copyright (C) 2007      Franky Van Liedekerke <franky.van.liedekerke@telenet.be>
 * Copyright (C) 2010-2012 Juanjo Menent         <jmenent@2byte.es>
 * Copyright (C) 2012      Christophe Battarel   <christophe.battarel@altairis.fr>
 * Copyright (C) 2012      Marcos García         <marcosgdf@gmail.com>
 * Copyright (C) 2012-2013 Ferran Marcet         <fmarcet@2byte.es>
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
 *	\file       htdocs/compta/facture/class/facture.class.php
 *	\ingroup    facture
 *	\brief      File of class to manage invoices
 */

include_once DOL_DOCUMENT_ROOT.'/core/class/commoninvoice.class.php';
include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT .'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT .'/societe/class/client.class.php';


/**
 *	Class to manage invoices
 */
class Facturesim extends Facture
{

    public $fk_cash;

    /**
     *      Return next reference of ticket not already used (or last reference)
     *      according to numbering module defined into constant FACSIM_ADDON
     * @param       object soc                   objet company
     * @param     string mode                    'next' for next value or 'last' for last value
     * @return    string                  free ref or last ref
     */
    public function getNextNumRef($soc, $mode = 'next')
    {
        global $conf, $langs;
        $langs->load("bills");

        // Clean parameters (if not defined or using deprecated value)
        if (empty($conf->global->FACSIM_ADDON)) $conf->global->FACSIM_ADDON = 'mod_facsim_alcoy';
        else if ($conf->global->FACSIM_ADDON == 'alcoy') $conf->global->FACSIM_ADDON = 'mod_facsim_alcoy';

        $mybool = false;

        $file = $conf->global->FACSIM_ADDON . ".php";
        $classname = $conf->global->FACSIM_ADDON;
        // Include file with class
        foreach ($conf->file->dol_document_root as $dirroot) {
            $dir = $dirroot . "/pos/backend/numerotation/numerotation_facsim/";
            // Load file with numbering class (if found)
            $mybool |= @include_once($dir . $file);
        }

        // For compatibility
        if (!$mybool) {
            $file = $conf->global->FACSIM_ADDON . "/" . $conf->global->FACSIM_ADDON . ".modules.php";
            $classname = "mod_facsim_" . $conf->global->FACSIM_ADDON;
            // Include file with class
            foreach ($conf->file->dol_document_root as $dirroot) {
                $dir = $dirroot . "/pos/backend/numerotation/numerotation_facsim/";
                // Load file with numbering class (if found)
                $mybool |= @include_once($dir . $file);
            }
        }
        //print "xx".$mybool.$dir.$file."-".$classname;

        if (!$mybool) {
            dol_print_error('', "Failed to include file " . $file);
            return '';
        }

        $obj = new $classname();

        $numref = $obj->getNumRef($soc, $this, $mode);

        if ($numref != "") {
            return $numref;
        } else {
            //dol_print_error($db,"Ticket::getNextNumRef ".$obj->error);
            return false;
        }
    }

    public function addline2($line)
    {
        //include_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
        //$line =  array_merge($line ,calcule_product_price($line));
        $exclude = array("id");
        dol_syslog("Factureim::Addline", LOG_DEBUG);
        $this->line = new FactureLigne($this->db);
        foreach ($this->line as $property => $value) {
            if (isset($line[$property]) AND !in_array($property, $exclude)) {
                $this->line->$property = $line[$property];
            }
        }    
        $this->line->fk_facture =   $this->id;
        $this->line->context    =   $this->context;
        $this->line->fk_product =   $line['idProduct'];
        $this->line->desc       =   $line['description'];
        $this->line->product_type=  $line['type'];
        $this->line->subprice   =   $line['pu_ht'];
        $this->line->price      =   $line['price'];    
        $this->line->remise_percent=$line['discount'];;
        $this->line->rang       = $line['number'];
        $this->line->situation_percent = 100;    
        // infos marge
        //$this->line->fk_fournprice = $fk_fournprice;
        $this->line->pa_ht = 0;
        // Multicurrency
        $this->line->fk_multicurrency           = $this->fk_multicurrency;
        $this->line->multicurrency_code         = $this->multicurrency_code;
        if(isset($line['multicurrency_tx'])){
            $this->line->multicurrency_subprice     = $line["pu_ht_devise"];
        }
        $this->check_credit_note();
        $this->db->begin();
        $result=$this->line->insert();
        if ($result > 0) {
            $this->db->commit();
            return $this->line->rowid;
        } else {
            $this->error=$this->db->error();
            $this->db->rollback();
            return -1;
        }
    
    }    
    function check_credit_note()
    {
        if ($this->type==self::TYPE_CREDIT_NOTE || $this->line->qty < 0) 
        {
            $this->line->qty        =  abs($this->line->qty);
            $this->line->subprice   = -abs($this->line->subprice);
            $this->line->price      = -abs($this->line->price);    // For credit note, unit price always negative, always positive otherwise
            $this->line->total_ht   = -abs($this->line->total_ht);    // For credit note and if qty is negative, total is negative
            $this->line->total_ttc  = -abs($this->line->total_ttc);  // For credit note and if qty is negative, total is negative
            $this->line->total_tva  = -abs($this->line->total_tva);  // For credit note and if qty is negative, total is negative
            $this->line->total_localtax1= -abs($this->line->total_localtax1);
            $this->line->total_localtax2= -abs($this->line->total_localtax2);
        }
    }

     public function getAvailableDiscounts($ref)
    {
        global $db, $conf;
        $ret = array();
        $coupons = array();
        $ret['total_coupons'] = 0;
        $sql  = "SELECT rc.rowid as id, rc.description , rc.amount_ttc as amount";
        $sql.= " FROM ".MAIN_DB_PREFIX."societe_remise_except as rc LEFT JOIN ".MAIN_DB_PREFIX."facture as f";
        $sql.= " ON rc.fk_facture_source = f.rowid";
        $sql.= " WHERE rc.entity = " . $conf->entity;
        $sql.= " AND (rc.fk_facture IS NULL AND rc.fk_facture_line IS NULL)";   // Available
        $sql.= " AND (f.facnumber = '".$ref."')";
        if (is_object($company)) $sql.= " AND rc.fk_soc = ".$company->id;
        if (is_object($user))    $sql.= " AND rc.fk_user = ".$user->id;
        
        dol_syslog("Factureim::getAvailableDiscounts for ". $ref, LOG_DEBUG);
        $resql=$db->query($sql);
        if ($resql)
        {
            $num = $db->num_rows($res);
            $i = 0;

            while ($i < $num) {
                $objp = $db->fetch_object($res);
                $coupons[$i]['id'] = $objp->id;
                $coupons[$i]['desc'] = $objp->description;
                $coupons[$i]['amount'] = $objp->amount;
                $ret['total_coupons'] += $objp->amount;
                $i++;
            }
            $ret['coupons'] = $coupons;
            return $ret;
        }
        return -1;
    }

    public  function SearchProductsReturned($idFacture) {
        global $db;
        $idFacture = $db->escape(trim($idFacture));
        $sql = "SELECT d.fk_product AS product, SUM(d.qty) AS qty FROM ";
        $sql .= MAIN_DB_PREFIX . "facturedet AS d LEFT JOIN ". MAIN_DB_PREFIX ."facture AS f "; 
        $sql .= "ON  d.fk_facture = f.rowid ";
        $sql .= "LEFT JOIN ". MAIN_DB_PREFIX ."product AS p ";
        $sql .= "ON  d.fk_product = p.rowid ";
        $sql .= "WHERE f.fk_facture_source = '".$idFacture."' ";
        $sql .= "AND p.fk_product_type = 0 ";
        $sql .= "GROUP BY d.fk_product";

        $res = $db->query($sql);

        if ($res) {
            $num = $db->num_rows($res);
            $i = 0;
            while ($i < $num) {
                $objp = $db->fetch_object($res);
                $ret[$objp->product]['qty'] = $objp->qty;
                
                $i++;
            }
        } else {
            return -1;    
        }

        $sql = "SELECT  SUM(f.total_ttc) AS total_ttc FROM ";
        $sql .= MAIN_DB_PREFIX ."facture AS f "; 
        $sql .= "WHERE f.fk_facture_source = '".$idFacture."' ";
        
        $res = $db->query($sql);

        if ($res) {
            $obj = $db->fetch_object($res);
            $ret['total_ttc'] = $obj->total_ttc;
            return $ret;    
        }
        return -1;
    }

     public function getDiscounts($ref)
    {
        global $db, $conf;

        $sql  = "SELECT rc.rowid";
        $sql.= " FROM ".MAIN_DB_PREFIX."societe_remise_except as rc LEFT JOIN ".MAIN_DB_PREFIX."facture as f";
        $sql.= " ON rc.fk_facture_source = f.rowid";
        $sql.= " WHERE rc.entity = " . $conf->entity;
        $sql.= " AND (rc.fk_facture IS NULL AND rc.fk_facture_line IS NULL)";   // Available
        $sql.= " AND (f.facnumber = '".$ref."')";
        
        dol_syslog("Factureim::getDiscounts for ". $ref, LOG_DEBUG);
        $res = $db->query($sql);
        if ($res) {
        
            $num = $db->num_rows($res);
            $i = 0;
            while ($i < $num) {
                $obj = $db->fetch_object($res);
                $obj->amount;
        }
        return -1;
        }
    }

     public function update_discount_amount($discount, $amount_ttc){
        global $db;
        self::recalcule_discount_amount($discount,$amount_ttc);
        $sql ="UPDATE ".MAIN_DB_PREFIX."societe_remise_except";
        $sql.=" SET amount_ttc = ".$discount->amount_ttc;
        $sql.=" , amount_ht = ".$discount->amount_ht;
        $sql.=" , amount_tva = ".$discount->amount_tva;        
        $sql.=" WHERE rowid = ".$discount->id;

        dol_syslog("DiscountAbsolute::update_discount_amount  for ".$discount->id, LOG_DEBUG);
        $resql = $db->query($sql);
        if ($resql)
            return 1;
        else
            return -3;
    }

     public function recalcule_discount_amount($discount,$amount_ttc){
        $discount->amount_ttc = price2num($amount_ttc);
        $discount->amount_ht  = price2num($amount_ttc / (1 + ($discount->tva_tx / 100)));
        $discount->amount_tva = price2num($discount->amount_ttc - $discount->amount_ht);     
    }
}