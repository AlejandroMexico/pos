<?php
/* Copyright (C) 2017      Boris Duin <borisduin@gmail.com>
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
 *		\file       pos/class/discount.class.php
 * 		\ingroup    core propal facture commande
 *		\brief      File of class to manage absolute discounts
 */


/**
 *		\class      DiscountAbsolute
 *		\brief      Class to manage absolute discounts
 */
// require_once  '/schema/schema_pos_discount.class.php';

class PosDiscount {

public $db; 

public $fk_discount;
public $fk_cash_c;
public $date_c;
public $fk_user_c;
public $fk_cash_l;
public $date_l;
public $fk_user_l;
public $error;
public $fk_discount_source = null;


    public function __construct($db){
        $this->db = $db;
    }
    
    /**
     *  Load object from database into memory
     *
     *  @param      int     $id              id discount to load
     *  @return     int                         <0 if KO, =0 if not found, >0 if OK
     */
    function fetchCreatedInRange($cash_c, $date_start, $date_end) {
        $discounts = array();
        $discount = array();
        $sql = "SELECT  f.facnumber AS facture, f2.facnumber AS facture_origin, d.amount_ttc AS amount,  pd.fk_user_c AS user";
        $sql.= " FROM ".MAIN_DB_PREFIX."pos_discount AS pd";
        $sql.= " INNER JOIN ".MAIN_DB_PREFIX."societe_remise_except AS d";
        $sql.= " ON pd.fk_discount = d.rowid";
        $sql.= " INNER JOIN ".MAIN_DB_PREFIX."facture AS f";
        $sql.= " ON d.fk_facture_source = f.rowid";
        $sql.= " INNER JOIN ".MAIN_DB_PREFIX."facture AS f2";
        $sql.= " ON f.fk_facture_source = f2.rowid";
        $sql.= " WHERE pd.date_c >= '" .$date_start."'";
        $sql.= " AND pd.date_c <= '" .$date_end."'";
        $sql.= " AND pd.fk_cash_c = " .$cash_c;
        dol_syslog(get_class($this)."::fetchCreatedInRange", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql){
            while ( $obj = $this->db->fetch_object($resql) ) {
                $discount['facture'] = $obj->facture ." <- ". $obj->facture_origin; 
                $discount['amount'] = $obj->amount;
                $discount['user'] = $obj->user;
                $discounts[] = $discount;
            }
            $this->db->free($resql);
            return $discounts;

        } else {
            $this->error=$this->db->error();
            return -1;
        }
    }

    /**
     *  Load object from database into memory
     *
     *  @param      int     $id              id discount to load
     *  @return     int                         <0 if KO, =0 if not found, >0 if OK
     */
    function fetchUsedInRange($cash_l, $date_start, $date_end) {
        $discounts = array();
        $discount = array();
        $sql = "SELECT  f.facnumber AS facture, f2.facnumber AS facture_origin, d.amount_ttc AS amount,  pd.fk_user_l AS user";
        $sql.= " FROM ".MAIN_DB_PREFIX."pos_discount AS pd";
        $sql.= " INNER JOIN ".MAIN_DB_PREFIX."societe_remise_except AS d";
        $sql.= " ON pd.fk_discount = d.rowid";
        $sql.= " INNER JOIN ".MAIN_DB_PREFIX."facture AS f";
        $sql.= " ON d.fk_facture = f.rowid";
        $sql.= " INNER JOIN ".MAIN_DB_PREFIX."facture AS f2";
        $sql.= " ON d.fk_facture_source = f2.rowid";
        $sql.= " WHERE pd.date_l >= '" .$date_start."'";
        $sql.= " AND pd.date_l <= '" .$date_end."'";
        $sql.= " AND pd.fk_cash_l = " .$cash_l;
        dol_syslog(get_class($this)."::fetchUsedInRange", LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql){
            while ( $obj = $this->db->fetch_object($resql) ) {
                $discount['facture'] = $obj->facture ." <- ". $obj->facture_origin;
                $discount['amount'] = $obj->amount;
                $discount['user'] = $obj->user;
                $discounts[] = $discount;
            }
            $this->db->free($resql);
            return $discounts;
        } else {
            $this->error=$this->db->error();
            return -1;
        }
    }

    /**
     *      Create a pos discount into database
     *
     *      @return     int                 <0 if KO, >0 if OK
     */
    function create() {
        // Insert request
        $fk_discount_source = !is_null($this->fk_discount_source)?$this->fk_discount_source:"NULL";
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."pos_discount";
        $sql.= " (fk_discount, fk_cash_c, date_c, fk_user_c, fk_discount_source)";
        $sql.= " VALUES (".$this->fk_discount.", ".$this->fk_cash_c.",'".$this->db->idate($this->date_c!=''?$this->date_c:dol_now())."', ".$this->fk_user_c.", ".$fk_discount_source.")";

        dol_syslog(get_class($this)."::create", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if (!$resql){
            $this->error=$this->db->lasterror().' - sql='.$sql;
            return -1;
        }
        return $this->fk_discount;
    }

    /**
     *      link discount to cash / user applied to
     *
     *      @return     int                 <0 if KO, >0 if OK
     */
    function link_to($cash, $user) {
        // Insert request

        $sql = "UPDATE ".MAIN_DB_PREFIX."pos_discount";
        $sql.= " set fk_cash_l = ".$cash.",  fk_user_l = ".$user.", date_l ='".$this->db->idate($this->date_c!=''?$this->date_c:dol_now())."'";
        $sql.= " WHERE fk_discount = " . $this->fk_discount;
        
        dol_syslog(get_class($this)."::link", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if (!$resql)
        {
            $this->error=$this->db->lasterror().' - sql='.$sql;
            return -1;
        }
        return $this->fk_discount;
    }
}
