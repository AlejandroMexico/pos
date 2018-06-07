<?php
/* Copyright (C) 2011 Juanjo Menent           <jmenent@2byte.es>
 * Copyright (C) 2012-2017 Ferran Marcet      <fmarcet@2byte.es>
 * Copyright (C) 2013 Iván Casco              <admin@gestionintegraltn.com>
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

require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");
require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");
dol_include_once('/pos/class/ticket.class.php');
dol_include_once('/pos/class/payment.class.php');
require_once(DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php");
dol_include_once('/pos/class/cash.class.php');
dol_include_once('/pos/backend/lib/errors.lib.php');
dol_include_once('/pos/class/place.class.php');
require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/date.lib.php");
require_once (DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php');
dol_include_once('/pos/class/facturesim.class.php');
dol_include_once('/rewards/class/rewards.class.php');
require_once (DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php');
require_once(DOL_DOCUMENT_ROOT."/product/stock/class/entrepot.class.php");

/**
 *	\class      POS
 *	\brief      Class for POS gestion
 */
class POS extends CommonObject
{


	/**
	 *  Return Categories list
	 * @param        int $idCat Id of Category, if 0 return Principal cats
	 * @return      array|int                Array with categories
	 */
	public  function getCategories($idCat = 0)
	{
		global $db;
		switch ($idCat) {
			case 0: //devolver las categorias con nivel 1
				$objCat = new Categorie($db);
				if (version_compare(DOL_VERSION, 3.8) >= 0) {
					$cats = $objCat->get_full_arbo('product');
				} else {
					$cats = $objCat->get_full_arbo(0);
				}
				//$cats = $objCat->get_full_arbo($idCat);

				if (count($cats) > 0) {
					$retarray = array();
					foreach ($cats as $key => $val) {
						if ($val['level'] < 2) {
							$val['image'] = self::getImageCategory($val['id'], false);
							//$val['thumb']= self::getImageCategory($val['id'],true);
							$retarray[] = $val;
						}
					}
					return $retarray;
				}
				break;

			case ($idCat > 0):
				$objCat = new Categorie($db);

				$result = $objCat->fetch($idCat);
				if ($result > 0) {
					$cats = $objCat->get_filles();
					//$cats = self::get_filles($idCat);
					if (is_array($cats) && count($cats) > 0) {
						$retarray = array();
						foreach ((array)$cats as $val) {
							$cat['id'] = $val->id;
							$cat['label'] = $val->label;
							$cat['fulllabel'] = $val->label;
							$cat['fullpath'] = '_' . $val->id;
							$cat['image'] = self::getImageCategory($val->id, false);
							//$cat['thumb']=self::getImageCategory($val->id,true);
							$retarray[] = $cat;
						}
						return $retarray;
					}
				}

				break;

			default:
				return -1;
				break;
		}
	}

	/**
	 *  Return path of a catergory image
	 * @param        int  $idCat Id of Category
	 * @param        bool $thumb If enabled use thumb
	 * @return      string                Image path
	 */
	public  function getImageCategory($idCat, $thumb)
	{
		global $conf, $db;

		$extName = "_small";
		$extImgTarget = ".png";
		$outDir = "thumbs";
		$maxWidth = 90;
		$maxHeight = 90;
		$quality = 50;

		if ($idCat > 0) {
			$objCat = new Categorie($db);
			$objCat->fetch($idCat);

			$pdir = get_exdir($idCat, 2, 0, 0, (object)$objCat, 'category') . "/" . $idCat . "/photos/";
			$dir = $conf->categorie->multidir_output[$objCat->entity] . '/' . $pdir;

            $realpath = null;

			foreach ($objCat->liste_photos($dir, 1) as $key => $obj) {
				$filename = $dir . $obj['photo'];
				$filethumbs = $dir . $obj['photo_vignette'];

				/*$fileName = preg_replace('/(\.gif|\.jpeg|\.jpg|\.png|\.bmp)$/i','',$filethumbs);
				$fileName = basename($fileName);
				$imgThumbName = $dir.$outDir.'/'.$fileName.$extName.$extImgTarget;

				$file_osencoded=$imgThumbName;*/
				if (!dol_is_file($filethumbs)) {
					require_once(DOL_DOCUMENT_ROOT . "/core/lib/images.lib.php");
					vignette($filename, $maxWidth, $maxHeight, $extName, $quality, $outDir, 3);
					$filethumbs = preg_replace('/(\.gif|\.jpeg|\.jpg|\.png|\.bmp)$/i', '', $obj['photo']);
					$filethumbs = basename($filethumbs);
					$obj['photo_vignette'] = $outDir . '/' . $filethumbs . $extName . $extImgTarget;
				}

				if (!$thumb) {
					$filename = $obj['photo'];
				} else {
					$filename = $obj['photo_vignette'];
				}

				$realpath = DOL_URL_ROOT . '/viewimage.php?modulepart=category&entity=' . $objCat->entity . '&file=' . urlencode($pdir . $filename);

			}
			if (!$realpath) {
				$realpath = DOL_URL_ROOT . '/viewimage.php?modulepart=product&file=' . urlencode('noimage.jpg');
			}
			return $realpath;
		}

	}

	/**
	 *  Return products by a category
	 * @param        int $idCat       Id of Category
	 * @param        int $more        list products position
	 * @param        int $ticketstate Ticket state (2= return)
	 * @return      array                List of products
	 */
	public  function getProductsbyCategory($idCat, $more, $ticketstate)
    {
        global $db, $conf;

        if ($idCat) //Productos de la categoría
        {
            $object = new Categorie($db);
            $result = $object->fetch($idCat);
            if ($result > 0) {
                if ($object->type == 0) {
                    return self::get_prod($idCat, $more, $ticketstate);


                }
            }
        } else //Productos sin categorías
        {

            $sql = "SELECT o.rowid as id, o.ref, o.label, o.description, o.price_ttc, o.price_min_ttc,";
            $sql .= " o.fk_product_type";
            $sql .= " FROM " . MAIN_DB_PREFIX . "product as o";

            if ($conf->global->POS_STOCK || $ticketstate == 1) {
                $sql .= " WHERE rowid NOT IN ";
                $sql .= " (SELECT fk_product FROM " . MAIN_DB_PREFIX . "categorie_product as cp, " . MAIN_DB_PREFIX . "categorie as c WHERE c.rowid = cp.fk_categorie AND entity IN (" . getEntity("category",
                        1) . "))";
                $sql .= " AND tosell=1";
                $sql .= " AND entity IN (" . getEntity("product", 1) . ")";
                if (!$conf->global->POS_SERVICES) {
                    $sql .= " AND o.fk_product_type = 0";
                }
            } else {
                $cashid = $_SESSION['TERMINAL_ID'];
                $cash = new Cash($db);
                $cash->fetch($cashid);
                $warehouse = $cash->fk_warehouse;

                $sql .= ", " . MAIN_DB_PREFIX . "product_stock as ps";
                $sql .= " WHERE o.rowid NOT IN ";
                $sql .= " (SELECT fk_product FROM " . MAIN_DB_PREFIX . "categorie_product as cp, " . MAIN_DB_PREFIX . "categorie as c WHERE c.rowid = cp.fk_categorie AND entity IN (" . getEntity("category",
                        1) . "))";
                $sql .= " AND tosell=1";
                $sql .= " AND entity IN (" . getEntity("product", 1) . ")";
                $sql .= " AND o.rowid = ps.fk_product";
                $sql .= " AND ps.fk_entrepot = " . $warehouse;
                $sql .= " AND ps.reel > 0";
                if ($conf->global->POS_SERVICES) {
                    $sql .= " union select o.rowid as id, o.ref, o.label, o.description, o.price_ttc, o.price_min_ttc, 	";
                    $sql .= " o.fk_product_type";
                    $sql .= " FROM " . MAIN_DB_PREFIX . "product as o";
                    $sql .= " WHERE o.rowid NOT IN ";
                    $sql .= " (SELECT fk_product FROM " . MAIN_DB_PREFIX . "categorie_product)";
                    $sql .= " AND tosell=1";
                    $sql .= " AND fk_product_type=1";
                    $sql .= " AND entity IN (" . getEntity("product", 1) . ")";
                }
            }
            if ($more >= 0) {
                $sql .= " LIMIT " . $more . ",10 ";
            }

            $res = $db->query($sql);

            if ($res) {
                $num = $db->num_rows($res);
                $i = 0;

                while ($i < $num) {
                    $objp = $db->fetch_object($res);

                    $ret[$objp->id]["id"] = $objp->id;
                    $ret[$objp->id]["ref"] = $objp->ref;
                    $ret[$objp->id]["label"] = $objp->label;
                    $ret[$objp->id]["price_ttc"] = $objp->price_ttc;
                    $ret[$objp->id]["price_min_ttc"] = $objp->price_min_ttc;
                    $ret[$objp->id]["description"] = $objp->description;

                    $ret[$objp->id]["image"] = self::getImageProduct($objp->id, false);
                    $ret[$objp->id]["thumb"] = self::getImageProduct($objp->id, true);

                    $i++;

                }
                return $ret;
            } else {
                return -1;
            }
        }
        return -1;
    }

	/**
	 *  Return a catergory
	 * @param        int $idCat Id of Category
	 * @return      array                Category info
	 */
	public  function getCategorybyId($idCat)
	{
		global $db;
		$objCat = new Categorie($db);
		$result = $objCat->fetch($idCat);
		if ($result > 0) {
			return $objCat;
		}
		return -1;
	}
	/**
	 *  Return product stock
	 * @param        int $productId Id of Product
	 * @param        int $warehouseId Id of Product
	 * @return        variant , int product stock, or "all" for  
	 */
	public  function getProductStock($productId, $warehouseId, $type)
	{
		global $db, $conf;
		if (!$conf->global->POS_STOCK) {
			if (($conf->global->STOCK_SUPPORTS_SERVICES && $type == 1) || $type == 0) {
				$sql = "SELECT reel FROM " . MAIN_DB_PREFIX . "product_stock WHERE fk_product =".$productId." and fk_entrepot = ".$warehouseId.";";
 				$result = $db->query($sql);
        		if (($result) && ($db->num_rows($result))) {
            		$obj = $db->fetch_object($result);
            		return $obj->reel;
        		} else{
           			return 0;
        		}
			} else {
				return "all";
			}
		} else {
			return "all";
		}				
	}

	/**
	 *  Return product info
	 * @param        int $idProd Id of Product
	 * @return      array                Product info
	 */
	public  function getProductbyId($data)
	{

		global $db, $conf, $mysoc;
		
		$idProd 		= intval($data['product']);
		$idCust 		= intval($data['customer']);
		$idWarehouse 	= intval($data['warehouse']);
		

		if ($conf->global->PRODUIT_MULTIPRICES) {
			$sql = "SELECT price_level";
			$sql .= " FROM " . MAIN_DB_PREFIX . "societe";
			$sql .= " WHERE rowid = " . $idCust;
			$res = $db->query($sql);
			if ($res) {
				$obj = $db->fetch_object($res);
				if ($obj->price_level == null) {
					$pricelevel = 1;
				} else {
					$pricelevel = $obj->price_level;
				}
			}
		} else {
			$pricelevel = 1;
		}

		$function = "getProductbyId";

		$objp = new Product($db);
		$objp->fetch($idProd);

        $societe = new Societe($db);
        $societe->fetch($idCust);
        
		$ret[0]["id"] = $objp->id;
		$ret[0]["ref"] = $objp->ref;
		$ret[0]["label"] = $objp->label;
		$ret[0]["description"] = $objp->description;
		$ret[0]["type"] = $objp->type;
		$ret[0]["diff_price"] = 0;
		$ret[0]["uselocaltax1_rate"]=0;
        $ret[0]["uselocaltax2_rate"]=0;
        $ret[0]["discount_global"] = $societe->remise_percent;
        if (isset($data["discount_global"])) {
        	$discount_global = intval($data["discount_global"]);
        	if ($discount_global > $societe->remise_percent) {
        		$ret[0]["discount_global"] = $discount_global; 
        	}
        }
        $use_product_vat = false;
        

        if ($societe->tva_assuj){
        	$use_product_vat = true;
        	$ret[0]["uselocaltax1_rate"]=$societe->localtax1_assuj;
        	$ret[0]["uselocaltax2_rate"]=$societe->localtax2_assuj;
	    }
		if (!empty($objp->multiprices[$pricelevel]) && $objp->multiprices[$pricelevel] > 0) {
			$ret[0]["price"] = $objp->multiprices[$pricelevel];
			$ret[0]["price_ttc"] = $objp->multiprices_ttc[$pricelevel];
			$ret[0]["price_min"] = $objp->multiprices_min[$pricelevel];
			$ret[0]["price_min_ttc"] = $objp->multiprices_min_ttc[$pricelevel];
			$ret[0]["price_base_type"] = $objp->multiprices_base_type[$pricelevel];
			$ret[0]["tva_tx"] = ($use_product_vat)?$objp->multiprices_tva_tx[$pricelevel]:0;
			if ($ret[0]["price_base_type"] == "TTC" ) {
				$ret[0]["price"] =  $ret[0]["price_ttc"] / (1 + ($ret[0]["tva_tx"]/100) );
				$ret[0]["price_min"] = $ret[0]["price_min_ttc"] / (1 + ($ret[0]["tva_tx"]/100) );
				$ret[0]["price_base_type"] = "HT";
			}
			
		} else {
			if ($conf->global->PRODUIT_CUSTOMER_PRICES) {

				require_once DOL_DOCUMENT_ROOT . '/product/class/productcustomerprice.class.php';
				$prodcustprice = new Productcustomerprice($db);
				$filter = array('t.fk_product' => $objp->id, 't.fk_soc' => $idCust);

				$result = $prodcustprice->fetch_all('ASC', 't.rowid', 0, 0, $filter);
				if ($result >= 0) {
					if (count($prodcustprice->lines) > 0) {
						$ret[0] = array_merge($ret[0], POS::get_product_prices($objp->lines[0], $use_product_vat));
					} else {
						$ret[0] = array_merge($ret[0], POS::get_product_prices($objp, $use_product_vat));
					}
				}
			} else {
				$ret[0] = array_merge($ret[0], POS::get_product_prices($objp, $use_product_vat));
				if ($conf->global->PRODUIT_MULTIPRICES) {
					$ret[0]["diff_price"] = 1;
				}
				
			}
		}
		$ret[0]["localtax1_type"] 	= $objp->localtax1_type;
		$ret[0]["localtax1_tx"] 	= $objp->localtax1_tx;
		$ret[0]["localtax2_type"] 	= $objp->localtax2_type;
		$ret[0]["localtax2_tx"] 	= $objp->localtax2_tx;
		

		
		//TODO controla si estamos vendiendo sin stock y controla que haya al menos una unidad
		$ret[0]["stock"] = self::getProductStock($idProd, $idWarehouse, $objp->type);
		
		$ret[0]["is_promo"] = 0;
		$ret[0]["socid"] = $idCust;
		$ret[0]["qty"] = 1;
		$ret[0]["idProduct"] = $idProd;
		$ret[0]["pu_ht"] = $ret[0]["price"];
		if ($conf->discounts->enabled) {
 		    dol_include_once('/discounts/lib/discounts.lib.php');
			dol_include_once('/discounts/class/discounts.class.php');
			$promo_price = calcul_discount_pos($product);
			if (!empty($promo_price)) {
				$ret[0]["pu_ht"] = $promo_price;
				$ret[0]["is_promo"] = 1;
				$ret[0]["discount_global"]  = 0;
			}	              
		}
		$ret[0]["image"] = self::getImageProduct($objp->id, false);
		$ret[0]["thumb"] = self::getImageProduct($objp->id, true);
		// Por si está activo el modulo de promciones
		
		// Calcular los precios
		$ret[0] = array_merge($ret[0], self::calculePrice($ret[0]));

		return Errorcontrol($ret, $function);
	}


	function get_product_prices($objp,$use_product_vat) {
            $result = array("price" => 0);
			$result["price"] 				= $objp->price;
			$result["price_ttc"] 			= $objp->price_ttc;
			$result["price_min"] 			= $objp->price_min;
			$result["price_min_ttc"] 		= $objp->price_min_ttc;
			$result["price_base_type"] 		= $objp->price_base_type;
			$result["tva_tx"] 				= ($use_product_vat)?$objp->tva_tx:0;
			if ($result["price_base_type"] == "TTC" ) {
				$result["price"] 					=  $result["price_ttc"] / (1 + ($result["tva_tx"]/100) );
				$result["price_min"] 				=  $result["price_min_ttc"] / (1 + ($result["tva_tx"]/100) );
				$result["price_base_type"] = "HT";
			}
			return $result;
	}



	/**
	 *  Return product info
	 *
	 * @param        string  $idSearch    Part of code, label or barcode
	 * @param        boolean $stock       Return stocks of products into info
	 * @param        int     $warehouse   Warehouse id
	 * @param                int          mode                Mode of search
	 * @param        int     $ticketstate Ticket state
	 * @return      array                    Product info
	 */
	public  function SearchProduct(
			$idSearch,
			$stock = false,
			$warehouse,
			$mode = 0,
			$ticketstate = 0,
			$customerId
	) {
		global $db, $conf, $user;

		$ret = array();
		$function = "getProductbyId";

		if (dol_strlen($idSearch) != 0 && dol_strlen($idSearch) < $conf->global->PRODUIT_USE_SEARCH_TO_SELECT && $mode != -5 && $mode != -6) {
			return ErrorControl(-2, $function);
		}

		$prefix = empty($conf->global->PRODUCT_DONOTSEARCH_ANYWHERE) ? '%' : '';    // Can use index if PRODUCT_DONOTSEARCH_ANYWHERE is on

		$limit = $conf->global->POS_SEARCH_PRODUCT_LIMIT ? $conf->global->POS_SEARCH_PRODUCT_LIMIT : 100;
		if ($mode >= 0) {
			if ($stock) {
				$sql = "SELECT distinct p.rowid, p.ref, p.label ,";
				$sql .= "(select w.reel from " . MAIN_DB_PREFIX . "product_stock w left join " . MAIN_DB_PREFIX . "entrepot e on w.fk_entrepot = e.rowid";
				$sql .= " where w.fk_product = p.rowid and e.rowid=ep.rowid) as stock";
				$sql .= " , ep.label as warehouse, ep.rowid as warehouseId";
				$sql .= " FROM " . MAIN_DB_PREFIX . "product p, " . MAIN_DB_PREFIX . "entrepot ep ";

			} else {
				if ($conf->global->PRODUIT_MULTIPRICES) {
					$sql = "SELECT price_level";
					$sql .= " FROM " . MAIN_DB_PREFIX . "societe";
					$sql .= " WHERE rowid = " . $customerId;
					$res = $db->query($sql);
					if ($res) {
						$obj = $db->fetch_object($res);
						if ($obj->price_level == null) {
							$pricelevel = 1;
						} else {
							$pricelevel = $obj->price_level;
						}
					}
				} else {
					$pricelevel = 1;
				}
				$sql = "SELECT p.rowid, p.ref, p.label, ep.rowid as warehouseId";
				$sql .= " FROM " . MAIN_DB_PREFIX . "product as p, " . MAIN_DB_PREFIX . "product_stock as w, " . MAIN_DB_PREFIX . "entrepot as ep ";
			}

			$sql .= " WHERE p.tosell = 1 AND ep.statut = 1";
			$sql .= " AND p.entity IN (" . getEntity("product", 1) . ")";
			$sql .= " AND ep.entity IN (" . getEntity("stock", 1) . ")";
			if (!$user->rights->pos->show_warehouses && $warehouse > 0) {
				$sql .= " AND ep.rowid = " . $warehouse;
			}
			if (!$stock) {
				if (!$conf->global->POS_STOCK && $ticketstate != 1) {
                    $sql .= " AND w.fk_product = p.rowid AND ep.rowid=w.fk_entrepot ";
					$sql .= " AND w.reel > 0";
				}
			}

			if (!$conf->global->POS_SERVICES || $stock) {
				$sql .= " AND p.fk_product_type = 0";
			}

			$sql .= " AND (p.ref LIKE '" . $prefix . $db->escape(trim($idSearch)) . "%' OR p.label LIKE '" . $prefix . $db->escape(trim($idSearch)) . "%' ";

			if ($conf->barcode->enabled) {
				$sql .= " OR p.barcode='" . $db->escape(trim($idSearch)) . "')";
			} else {
				$sql .= ")";
			}

			if (!$stock && $conf->global->POS_SERVICES) {
				$sql = "SELECT p.rowid, p.ref, p.label, ep.rowid as warehouseId";
				$sql .= " FROM " . MAIN_DB_PREFIX . "product as p left join " . MAIN_DB_PREFIX . "product_stock as w on w.fk_product = p.rowid, " . MAIN_DB_PREFIX . "entrepot as ep ";
				$sql .= " WHERE (p.tosell = 1 AND  p.entity IN (" . getEntity("product", 1) . ")";
				if (!$conf->global->POS_STOCK) {
					$sql .= " AND ep.rowid=w.fk_entrepot ";
				}
				$sql .= " AND (p.ref LIKE '" . $prefix . $db->escape(trim($idSearch)) . "%' OR p.label LIKE '" . $prefix . $db->escape(trim($idSearch)) . "%' ";
				if ($conf->barcode->enabled) {
					$sql .= " OR p.barcode='" . $db->escape(trim($idSearch)) . "')";
				} else {
					$sql .= ")";
				}
				if (!$conf->global->POS_STOCK && $ticketstate != 1) {
					$sql .= " AND ep.rowid = " . $warehouse . " AND w.reel > 0";
				}

				$sql .= " ) OR (p.tosell = 1 AND p.entity IN (" . getEntity("product",
								1) . ") AND p.fk_product_type = 1";
				$sql .= " AND (p.ref LIKE '" . $prefix . $db->escape(trim($idSearch)) . "%' OR p.label LIKE '" . $prefix . $db->escape(trim($idSearch)) . "%' ";
				if ($conf->barcode->enabled) {
					$sql .= " OR p.barcode='" . $db->escape(trim($idSearch)) . "')";
				} else {
					$sql .= ")";
				}
				if (!$conf->global->POS_STOCK) {
					$sql .= " AND ep.rowid = " . $warehouse;
				}
				$sql .= ")";
			}

			if (!$stock && $conf->global->POS_STOCK) {
				$sql .= " GROUP BY p.label, p.rowid, ep.rowid";
			} else {
				$sql .= " GROUP BY p.rowid, ep.label";
				$sql .= " ORDER BY p.label, ep.rowid";
			}
			$sql .= " LIMIT ". $limit;
		} else {
			$sql = "SELECT distinct p.rowid, p.ref, p.label , w.reel as stock, w.fk_entrepot as warehouseId, e.label as warehouse ";
			$sql .= " FROM " . MAIN_DB_PREFIX . "product p INNER JOIN " . MAIN_DB_PREFIX . "product_stock w ON w.fk_product=p.rowid ";
			$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid=w.fk_entrepot";
			$sql .= " WHERE p.entity IN (" . getEntity("product", 1) . ")";
			$sql .= " AND w.fk_entrepot=" . $warehouse;
			if (!$conf->global->POS_SERVICES) {
				$sql .= " AND p.fk_product_type = 0";
			}
			$sql .= " AND (p.ref LIKE '" . $prefix . $db->escape(trim($idSearch)) . "%' OR p.label LIKE '" . $prefix . $db->escape(trim($idSearch)) . "%' ";
			if ($conf->barcode->enabled) {
				$sql .= " OR p.barcode='" . $db->escape(trim($idSearch)) . "')";
			} else {
				$sql .= ")";
			}
			if ($mode == -1) {//no sell
				$sql .= " AND p.tosell = 0";
				$sql .= " LIMIT ". $limit;
			}
			if ($mode == -2) {//sell
				$sql .= " AND p.tosell = 1";
				$sql .= " LIMIT ". $limit;
			}
			if ($mode == -3) {//with stock
				$sql .= " AND w.reel > 0";
				$sql .= " LIMIT ". $limit;
			}
			if ($mode == -4) {//no stock
				$sql .= " AND w.reel <= 0";
				$sql .= " LIMIT ". $limit;
			}
			if ($mode == -5) {//best sell
				$sql = "SELECT SUM(fd.qty) as qty, pr.rowid, pr.ref, pr.label, ";
				$sql .= "	(select w.reel from " . MAIN_DB_PREFIX . "product_stock w left join " . MAIN_DB_PREFIX . "entrepot e on w.fk_entrepot = e.rowid";
				$sql .= " where w.fk_product = pr.rowid and e.rowid=ep.rowid) as stock, ep.label as warehouse, ep.rowid as warehouseId";
				$sql .= " FROM " . MAIN_DB_PREFIX . "facturedet as fd, " . MAIN_DB_PREFIX . "facture as f, " . MAIN_DB_PREFIX . "product as pr,";
				$sql .= " " . MAIN_DB_PREFIX . "entrepot as ep, " . MAIN_DB_PREFIX . "pos_facture as pf ";
				$sql .= " WHERE ep.rowid = " . $warehouse . " and pr.tosell = 1 AND f.rowid = fd.fk_facture AND f.entity = " . $conf->entity . " and pr.rowid = fd.fk_product";
				$sql .= " AND (pr.ref LIKE '" . $prefix . $db->escape(trim($idSearch)) . "%' OR pr.label LIKE '" . $prefix . $db->escape(trim($idSearch)) . "%' ";
				if ($conf->barcode->enabled) {
					$sql .= " OR pr.barcode='" . $db->escape(trim($idSearch)) . "')";
				} else {
					$sql .= ")";
				}
				if (!$conf->global->POS_SERVICES) {
					$sql .= " AND pr.fk_product_type = 0";
				}
				$sql .= " and pf.fk_facture = f.rowid GROUP BY fd.fk_product ORDER BY qty DESC limit 10";
			}
			if ($mode == -6) {//worst sell

				$sql = "SELECT 0 as qty, pr.rowid, pr.ref, pr.label, (select w.reel";
				$sql .= " from " . MAIN_DB_PREFIX . "product_stock w left join " . MAIN_DB_PREFIX . "entrepot e on w.fk_entrepot = e.rowid";
				$sql .= " where w.fk_product = pr.rowid and e.rowid=ep.rowid) as stock,";
				$sql .= " ep.label as warehouse, ep.rowid as warehouseId";
				$sql .= " from " . MAIN_DB_PREFIX . "product as pr, " . MAIN_DB_PREFIX . "entrepot as ep";
				$sql .= " where pr.rowid not in ( SELECT p.rowid";
				$sql .= " FROM " . MAIN_DB_PREFIX . "facturedet as fd, " . MAIN_DB_PREFIX . "facture as f, " . MAIN_DB_PREFIX . "product as p, " . MAIN_DB_PREFIX . "pos_facture as pf";
				$sql .= " WHERE p.tosell = 1 AND f.rowid = fd.fk_facture AND f.entity = " . $conf->entity . " and p.rowid = fd.fk_product";
				$sql .= " AND (pr.ref LIKE '" . $prefix . $db->escape(trim($idSearch)) . "%' OR pr.label LIKE '" . $prefix . $db->escape(trim($idSearch)) . "%' ";
				if ($conf->barcode->enabled) {
					$sql .= " OR pr.barcode='" . $db->escape(trim($idSearch)) . "')";
				} else {
					$sql .= ")";
				}
				if (!$conf->global->POS_SERVICES) {
					$sql .= " AND p.fk_product_type = 0";
				}
				$sql .= " and pf.fk_facture = f.rowid group by fd.fk_product ) AND ep.rowid = " . $warehouse;
				if (!$conf->global->POS_SERVICES) {
					$sql .= " AND pr.fk_product_type = 0";
				}
				$sql .= " ORDER BY qty ASC limit 10";
			}
		}
		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);
			$i = 0;
            $warehouse = new Entrepot($db);
			while ($i < $num) {
				$objp = $db->fetch_object($resql);

				$ret[$i]["id"] = $objp->rowid;
				$ret[$i]["ref"] = $objp->ref;
				$ret[$i]["label"] = $objp->label;
				$ret[$i]["warehouseId"] = $objp->warehouseId;
                $warehouse->fetch($objp->warehouseId);
                $ret[$i]["warehouseName"] = $warehouse->libelle;

				if ($stock) {
					$ret[$i]["warehouse"] = $objp->warehouse;
					if ($objp->stock) {
						$ret[$i]["stock"] = $objp->stock;
					} else {
						$ret[$i]["stock"] = 0;
					}
				} else {
					$prod = new Product($db);
					$prod->fetch($objp->rowid);

					if (!empty($prod->multiprices[$pricelevel]) && $prod->multiprices[$pricelevel] > 0) {
						$ret[$i]["price_ttc"] = $prod->multiprices_ttc[$pricelevel];
					} else {
						$ret[$i]["price_ttc"] = $prod->price_ttc;
					}
				}
				$i++;

			}

			if ($mode == -6 && $num < 10) {
				$resto = 10 - $num;
				$sql = "SELECT SUM(facd.qty) as qty, p.rowid, p.ref, p.label, (select wa.reel";
				$sql .= " from " . MAIN_DB_PREFIX . "product_stock wa left join " . MAIN_DB_PREFIX . "entrepot entr on wa.fk_entrepot = entr.rowid";
				$sql .= " where wa.fk_product = p.rowid and entr.rowid=en.rowid) as stock,";
				$sql .= " en.label as warehouse, en.rowid as warehouseId";
				$sql .= " FROM " . MAIN_DB_PREFIX . "facturedet as facd, " . MAIN_DB_PREFIX . "facture as fac, " . MAIN_DB_PREFIX . "product as p, " . MAIN_DB_PREFIX . "entrepot as en, " . MAIN_DB_PREFIX . "pos_facture as pfac";
				$sql .= " WHERE p.tosell = 1 AND fac.rowid = facd.fk_facture AND fac.entity = " . $conf->entity;
				$sql .= "AND facd.fk_product != 'NULL'and p.rowid = facd.fk_product AND pfac.fk_facture = fac.rowid and en.rowid = " . $warehouse;
				$sql .= " group by facd.fk_product";
				$sql .= " order by qty ASC limit " . $resto;

				$resql = $db->query($sql);
				if ($resql) {
					$num2 = $db->num_rows($resql);
					$i = $num;

					while ($i < $num2) {
						$objp = $db->fetch_object($resql);

						$ret[$i]["id"] = $objp->rowid;
						$ret[$i]["ref"] = $objp->ref;
						$ret[$i]["label"] = $objp->label;
						$ret[$i]["warehouseId"] = $objp->warehouseId;

						if ($stock) {
							$ret[$i]["warehouse"] = $objp->warehouse;
							if ($objp->stock) {
								$ret[$i]["stock"] = $objp->stock;
							} else {
								$ret[$i]["stock"] = 0;
							}
						}
						$i++;

					}
				}
			}
		}
		if (!$stock) {
			usort($ret, function($a, $b) {return $a["price_ttc"] - $b["price_ttc"];});
		}
		return ErrorControl(count($ret)>0?$ret:-1, $function);
	}

	public  function CountProduct($warehouseId)
	{
		global $db;

		$ret = -1;
		$function = "getProductbyId";

		$sql = "select(select count(p.rowid) from " . MAIN_DB_PREFIX . "product p, " . MAIN_DB_PREFIX . "product_stock ps where p.tosell = 0 and p.fk_product_type = 0 and ps.fk_entrepot = " . $warehouseId . " and ps.fk_product = p.rowid) as no_venta, ";
		$sql .= "(select count(p.rowid) from " . MAIN_DB_PREFIX . "product p, " . MAIN_DB_PREFIX . "product_stock ps where p.tosell = 1 and p.fk_product_type = 0 and ps.fk_entrepot = " . $warehouseId . " and ps.fk_product = p.rowid) as en_venta, ";
		$sql .= "(select count(p.rowid) from " . MAIN_DB_PREFIX . "product p, " . MAIN_DB_PREFIX . "product_stock ps where p.fk_product_type = 0 ";
		$sql .= "and ps.fk_entrepot = " . $warehouseId . " and ps.reel > 0 and ps.fk_product = p.rowid) as con_stock, ";
		$sql .= "(select count(p.rowid) from " . MAIN_DB_PREFIX . "product p, " . MAIN_DB_PREFIX . "product_stock ps where p.fk_product_type = 0 ";
		$sql .= " and ps.fk_entrepot = " . $warehouseId . " and ps.reel <= 0 and ps.fk_product = p.rowid) as sin_stock";

		$res = $db->query($sql);

		if ($res) {
			$obj = $db->fetch_object($res);

			$result["no_sell"] = $obj->no_venta;
			$result["sell"] = $obj->en_venta;
			$result["stock"] = $obj->con_stock;
			$result["no_stock"] = $obj->sin_stock;
			$result["best_sell"] = 10;
			$result["worst_sell"] = 10;

			return ErrorControl($result, $function);
		} else {
			return ErrorControl($ret, $function);
		}


	}


	/**
	 *  Return customer info
	 *
	 * @param        string  $idSearch Part of code, name, firstname, idprof1
	 * @param        boolean $extended Return more info
	 * @return      array                    Customer info
	 */
	public  function SearchCustomer($idSearch, $extended = false)
	{
		global $db, $conf, $user;

		$ret = array();
		$function = "SearchCustomer";

		if (dol_strlen($idSearch) <= $conf->global->COMPANY_USE_SEARCH_TO_SELECT) {
			return ErrorControl(-2, $function);
		}

		$prefix = empty($conf->global->COMPANY_DONOTSEARCH_ANYWHERE) ? '%' : '';    // Can use index if COMPANY_DONOTSEARCH_ANYWHERE is on

		$sql = "SELECT c.rowid, c.nom, c.code_client, c.siren, c.remise_client";
		$sql .= " FROM " . MAIN_DB_PREFIX . "societe as c";
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON c.rowid = sc.fk_soc AND sc.fk_user = " . $user->id;
		} // We need this table joined to the select in order to filter by sale
		$sql .= " WHERE c.client IN (1,3)";
		$sql .= " AND c.entity IN (" . getEntity("societe",1).")";
		$sql .= " AND (c.nom LIKE '" . $prefix . $db->escape(trim($idSearch)) . "%' OR c.code_client LIKE '" . $prefix . $db->escape(trim($idSearch)) . "%' OR c.siren LIKE '" . $prefix . $db->escape(trim($idSearch)) . "%' ";
		$sql .= ")";
		$sql .= " ORDER BY c.nom";

		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);
			$i = 0;
			$soc = new Societe($db);
			unset($ret);

			while ($i < $num) {
				$objp = $db->fetch_object($resql);
				$ret[$i]['points'] = null;
				if ($conf->global->REWARDS_POS && !empty($conf->rewards->enabled)) {
					$rew = new Rewards($db);
					$res = $rew->getCustomerReward($objp->rowid);
					if ($res) {
						$ret[$i]['points'] = $rew->getCustomerPoints($objp->rowid);
					}
				}
				$soc->fetch($objp->rowid);
				//$ret[$i]["coupon"] = $soc->getAvailableDiscounts();
				$ret[$i]["id"] = $objp->rowid;
				$ret[$i]["nom"] = $objp->nom;
				$ret[$i]["profid1"] = $objp->siren;
				$ret[$i]["remise"] = $objp->remise_client;
				$i++;

			}
		}
		return ErrorControl(count($ret)>0?$ret:-1, $function);
	}

	/**
	 *  Return path of a catergory image
	 *
	 * @param        int $idCat Id of Category
	 * @return      string                Image path
	 */
	public  function getImageProduct($idProd, $thumb = false)
	{
		global $conf, $db;

		$extName = "_small";
		$extImgTarget = ".png";
		$outDir = "thumbs";
		$maxWidth = 90;
		$maxHeight = 90;
		$quality = 50;

		if ($idProd > 0) {
			$objProd = new Product($db);
			$objProd->fetch($idProd);

			if (!empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO)) {
				$pdir[0] = get_exdir($objProd->id, 2, 0, 0, $objProd, 'product') . "/" . $objProd->id . "/photos/";
				$pdir[1] = dol_sanitizeFileName($objProd->ref) . '/';
			} else {
				$pdir[0] = dol_sanitizeFileName($objProd->ref) . '/';
				$pdir[1] = get_exdir($objProd->id, 2, 0, 0, $objProd, 'product') . "/" . $objProd->id . "/photos/";
			}
			$arephoto = false;
			foreach ($pdir as $midir) {
				if (!$arephoto) {
					$dir = $conf->product->multidir_output[$objProd->entity] . '/' . $midir;

					foreach ($objProd->liste_photos($dir, 1) as $key => $obj) {
						$filename = $dir . $obj['photo'];
						$filethumbs = $dir . $obj['photo_vignette'];

						/*$fileName = preg_replace('/(\.gif|\.jpeg|\.jpg|\.png|\.bmp)$/i','',$filethumbs);
						$fileName = basename($fileName);
						$imgThumbName = $dir.$outDir.'/'.$fileName.$extName.$extImgTarget;

						$file_osencoded=$imgThumbName;
						"\.jpg|\.jpeg|\.bmp|\.gif|\.png|\.tiff" */
						if (!dol_is_file($filethumbs)) {
							require_once(DOL_DOCUMENT_ROOT . "/core/lib/images.lib.php");
							vignette($filename, $maxWidth, $maxHeight, $extName, $quality, $outDir, 3);
							$filethumbs = preg_replace('/(\.gif|\.jpeg|\.jpg|\.png|\.bmp)$/i', '', $obj['photo']);
							$filethumbs = basename($filethumbs);
							$obj['photo_vignette'] = $outDir . '/' . $filethumbs . $extName . $extImgTarget;
						}

						if (!$thumb) {
							$filename = $obj['photo'];
						} else {
							$filename = $obj['photo_vignette'];
						}

						$realpath = DOL_URL_ROOT . '/viewimage.php?modulepart=product&entity=' . $objProd->entity . '&file=' . urlencode($midir . $filename);
						$arephoto = true;
					}
				}
			}
			if (!$realpath) {
				$realpath = DOL_URL_ROOT . '/viewimage.php?modulepart=product&file=' . urlencode('noimage.jpg');
			}
			return $realpath;
		}

	}

	/**
	 *  Returns internal users of Dolibarr
	 * @param        string $selected RowId of user for select
	 * @param        string $htmlname name for object
	 * @return      array                    Dolibarr internal users
	 */
	public  function select_Users($selected = '', $htmlname = 'users')
	{
		global $db, $conf;

		$sql = "SELECT rowid, lastname, firstname, login";
		$sql .= " FROM " . MAIN_DB_PREFIX . "user";
		$sql .= " WHERE entity IN (0," . $conf->entity . ")";
		if ($conf->global->POS_USER_TERMINAL) {
			$sql .= " AND rowid IN(";
			$sql .= "SELECT fk_object";
			$sql .= " FROM " . MAIN_DB_PREFIX . "pos_users";
			$sql .= " WHERE fk_terminal = " . $_SESSION["TERMINAL_ID"];
			//$sql.= " AND fk_object = ".$_SESSION["uid"];
			$sql .= " AND objtype = 'user'";

			$sql .= "UNION SELECT u.rowid";
			$sql .= " FROM " . MAIN_DB_PREFIX . "pos_users as pu";
			$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "usergroup_user as g ON pu.fk_object = g.fk_usergroup";
			$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user as u ON u.rowid = g.fk_user";
			$sql .= " WHERE pu.fk_terminal = " . $_SESSION["TERMINAL_ID"];
			//$sql.= " AND g.fk_user = ".$_SESSION["uid"];
			$sql .= " AND pu.objtype = 'group')";
		}

		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);
			$var = true;
			$i = 0;

			while ($i < $num) {
				$obj = $db->fetch_object($resql);
				$var = !$var;
				if (!$obj->fk_societe) {
					$userstatic = new User($db);
					$userstatic->fetch($obj->rowid);
					$userstatic->getrights('pos');
					$dir = $conf->user->dir_output;
					$file = '';

					if ($userstatic->rights->pos->frontend) {
						$username = $obj->firstname . ' ' . $obj->lastname;
						$internalusers[$i]['code'] = $obj->rowid;
						$internalusers[$i]['label'] = $username;
						$internalusers[$i]['login'] = $obj->login;

						if ($userstatic->photo) {
							$file = get_exdir($userstatic->id, 2, 0, 1, $userstatic, 'user') . "/" . $userstatic->photo;
						}
						if ($file && file_exists($dir . "/" . $file)) {
							$internalusers[$i]['photo'] = DOL_URL_ROOT . '/viewimage.php?modulepart=userphoto&entity=' . $userstatic->entity . '&file=' . urlencode($file);
						} else {
							if (version_compare(DOL_VERSION, 3.8) >= 0) {

								if ($userstatic->gender == "woman") {

									$internalusers[$i]['photo'] = DOL_URL_ROOT . '/public/theme/common/user_woman.png';
								} else {

									$internalusers[$i]['photo'] = DOL_URL_ROOT . '/public/theme/common/user_man.png';
								}

							} else {

								$internalusers[$i]['photo'] = DOL_URL_ROOT . '/theme/common/nophoto.jpg';
							}

						}
					}
				}

				$i++;
			}
			$db->free($resql);
		}

		return $internalusers;
	}

	/**
	 * Returns the type payments
	 *
	 * @return        array                    type of payments
	 */
	public  function select_Type_Payments()
	{
		global $db, $langs;

		$cash = new Cash($db);

		$terminal = $_SESSION['TERMINAL_ID'];
		$cash->fetch($terminal);

		$sql = "SELECT id, code, libelle, type";
		$sql .= " FROM " . MAIN_DB_PREFIX . "c_paiement";
		$sql .= " WHERE active > 0 and (id = " . $cash->fk_modepaycash . " or id =" . $cash->fk_modepaybank . " or id =" . $cash->fk_modepaybank_extra . ")";
		$sql .= " ORDER BY id";

		$resql = $db->query($sql);

		if ($resql) {
			$langs->load("bills");
			$num = $db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$obj = $db->fetch_object($resql);

				$j = $obj->id == $cash->fk_modepaycash ? 0 : ($obj->id == $cash->fk_modepaybank ? 1 : 2);

				$libelle = ($langs->trans("PaymentTypeShort" . $obj->code) != ("PaymentTypeShort" . $obj->code) ? $langs->trans("PaymentTypeShort" . $obj->code) : ($obj->libelle != '-' ? $obj->libelle : ''));
				$payments[$j]['id'] = $obj->id;
				$payments[$j]['code'] = $obj->code;
				$payments[$j]['label'] = $libelle;
				$payments[$j]['type'] = $obj->type;
				$i++;
			}
			$db->free($resql);
		}


		return $payments;

	}

	/**
	 *    Get object and lines from database
	 * @param        int $idTicket Id of ticket
	 * @return        Object            object if OK, <0 if KO
	 */
	public  function fetch($idTicket)
	{
		global $db;
		$object = new Ticket($db);
		$res = $object->fetch($idTicket);
		if ($res) {
			return $object;
		} else {
			return -1;
		}
	}

	/**
	 *
	 * Set Ticket into DB
	 *
	 * @param        array $aryTicket Ticket object
	 * @return        array    $result        Result
	 */
	public  function SetTicket($aryTicket)
	{
		$function = "SetTicket";
		$res = 0;

		$data = $aryTicket['data'];

		if (count($data) > 0) {
			if ($data['mode'] == 0) {
				if ($data['id']) {
					$res = self::UpdateTicket($aryTicket);
				} else {
					$res = self::CreateTicket($aryTicket);
				}
			} else {
				$res = self::CreateFacture($aryTicket);
			}

		}

		return ErrorControl($res, $function);
	}

	/**
	 *
	 * Get Ticket from DB
	 *
	 * @param    int $id Id Ticket to load
	 * @return    array            Array with data
	 */
	public  function GetTicket($id)
	{
		$function = "GetTicket";
		$res = 0;

		if ($id) {
			$ret = self::LoadTicket($id);
			return ErrorControl($ret, $function);
		} else {
			return ErrorControl($res, $function);
		}
	}

	/**
	 *
	 * Get Facture from DB
	 *
	 * @param    int $id Id Ticket to load
	 * @return    array            Array with data
	 */
	public  function GetFacture($id)
	{
		$function = "GetTicket";
		$res = 0;

		if ($id) {
			$ret = self::LoadFacture($id);
			return ErrorControl($ret, $function);
		} else {
			return ErrorControl($res, $function);
		}
	}

	/**
	 *
	 * Load Ticket from DB
	 *
	 * @param    int $id Id of ticket
	 * @return    array            Array with ticket data
	 */
	private  function LoadTicket($id)
	{
		global $db, $conf;
		$dataticket = array();

		$data = array();

		$object = new Ticket($db);
		$res = $object->fetch($id);

		if ($res) {
			require_once(DOL_DOCUMENT_ROOT . "/societe/class/societe.class.php");

			$data['id'] = $object->id;
			$data['ref'] = $object->ref;
			$data['type'] = $object->type;
			$data['customerId'] = $object->socid;
			// hay que cargar nombre
			$data["uselocaltax1_rate"]=0;
        	$data["uselocaltax2_rate"]=0;

			$soc = new Societe($db);
			$soc->fetch($object->socid);
			$data['customerName'] = $soc->name;
			$data['points'] = null;
			
			if ($soc->tva_assuj){
    			$use_product_vat = true;
    			$data["uselocaltax1_rate"]=$soc->localtax1_assuj;
    			$data["uselocaltax1_rate"]=$soc->localtax2_assuj;
    		}        

			/*if($conf->global->REWARDS_POS && ! empty($conf->rewards->enabled)){
				$rew= new Rewards($db);
				$res = $rew->getCustomerReward($object->socid);
				if($res){
					$data['points'] = $rew->getCustomerPoints($object->socid);
				}
			}*/
			//$data['coupon'] = $soc->getAvailableDiscounts();
			$data['state'] = $object->statut;
			$data['discount_global'] = $object->remise_percent;
			$data['discount_qty'] = $object->remise_absolut;
			$data['payment_type'] = $object->mode_reglement_id;
			$data['customerpay'] = $object->customer_pay;
			$data['difpayment'] = $object->diff_payment;
			$data['total_ttc'] = $object->total_ttc;
			$data['id_place'] = $object->fk_place;
			$data['note'] = $object->note;
			$data['lines'] = self::LoadTicketLines($object->lines);

			$data['ret_points'] = $object->getSommePaiement();

			$sql = "SELECT sum(pf.amount) as amount FROM " . MAIN_DB_PREFIX . "pos_paiement_ticket as pf, " . MAIN_DB_PREFIX . "pos_ticket as f";
			$sql .= " WHERE f.entity = " . $conf->entity . " AND f.rowid = pf.fk_ticket AND f.fk_ticket_source = " . $id;
			$resql = $db->query($sql);
			$obj = $db->fetch_object($resql);

			$data['ret_points'] = price2num($data['ret_points'] + $obj->amount, 'MT');

			$dataticket['data'] = $data;
			return $dataticket;
		} else {
			return $res;
		}
	}

	/**
	 *
	 * Load Facture from DB
	 *
	 * @param    int $id Id of facture
	 * @return    array            Array with ticket data
	 */
	private  function LoadFacture($id)
	{
		global $db, $conf;
		$dataticket = array();

		$data = array();

		$object = new Facture($db);
		$res = $object->fetch($id);

		if ($res) {
			require_once(DOL_DOCUMENT_ROOT . "/societe/class/societe.class.php");

			$data['id'] = $object->id;
			$data['ref'] = $object->ref;
			$data['type'] = $object->type;
			$data['customerId'] = $object->socid;
			//hay que cargar nombre
			$soc = new Societe($db);
			$soc->fetch($object->socid);
			$data['customerName'] = $soc->name;
			$data['state'] = $object->statut;
			$data['discount_global'] = $object->remise_percent;
			$data['discount_qty'] = $object->remise_absolue;
			$data['payment_type'] = $object->mode_reglement_id;
			$data['total_ttc'] = $object->total_ttc;
			$data['lines'] = self::LoadFactureLines($object->lines);
			if ($soc->tva_assuj){
        		$data["uselocaltax1_rate"]=$soc->localtax1_assuj;
        		$data["uselocaltax2_rate"]=$soc->localtax2_assuj;
	    	}
			$listofpayments = $object->getListOfPayments();
			foreach ($listofpayments as $paym) {
				// This payment might be this one or a previous one
				if ($paym['type'] != 'PNT') {
					$data['ret_points'] += $paym['amount'];
				}
			}
			$sql = "SELECT sum(pf.amount) as amount FROM " . MAIN_DB_PREFIX . "paiement_facture as pf, " . MAIN_DB_PREFIX . "facture as f";
			$sql .= " WHERE f.entity = " . $conf->entity . " AND f.rowid = pf.fk_facture AND f.fk_facture_source = " . $id;
			$resql = $db->query($sql);
			$obj = $db->fetch_object($resql);

			$data['ret_points'] = price2num($data['ret_points'] + $obj->amount, 'MT');

			$dataticket['data'] = $data;
			return $dataticket;
		} else {
			return $res;
		}
	}

	/**
	 *
	 * Load lines of a ticket.
	 *
	 * @param    array $lines Lines into database
	 * @return    array                Lines for front end
	 */
	private  function LoadTicketLines($lines)
	{
		global $conf;
		$aryLines = array();
		$i = 0;
		foreach ($lines as $line) {
			if (count($line) > 0) {
				$aryLines[$i]['id'] = $line->rowid;
				$aryLines[$i]['ref'] = $line->ref;
				$aryLines[$i]['label'] = $line->libelle;
				$aryLines[$i]['pu_ht'] = $line->subprice;
				$aryLines[$i]['price'] = $line->price;
				$aryLines[$i]['qty'] = $line->qty;
				$aryLines[$i]['tva_tx'] = $line->tva_tx;
				$aryLines[$i]["localtax1_type"] 	= $line->localtax1_type;
				$aryLines[$i]["localtax1_tx"] 	= $line->localtax1_tx;
				$aryLines[$i]["localtax2_type"] 	= $line->localtax2_type;
				$aryLines[$i]["localtax2_tx"] 	= $line->localtax2_tx;
				$aryLines[$i]['idProduct'] = $line->fk_product;
				$aryLines[$i]['discount'] = $line->remise_percent;
				$aryLines[$i]['total_ttc'] = $line->total_ttc;
				$aryLines[$i]['remise'] = $line->remise;
				$aryLines[$i]['type'] = $line->fk_product_type;
				if ($line->note != 'null') {
					$aryLines[$i]['note'] = $line->note;
				} else {
					$aryLines[$i]['note'] = '';
				}
				if (($conf->global->POS_STOCK == 1 ) || ($line->fk_product_type == 1 && !$conf->global->STOCK_SUPPORTS_SERVICES)) {
					$aryLines[$i]['stock'] = "all";
				}
				$i++;
			}
		}
		return $aryLines;
	}

	/**
	 *
	 * Load lines of a facture.
	 *
	 * @param    array $lines Lines into database
	 * @return    array                Lines for front end
	 */
	private  function LoadFactureLines($lines)
	{
		global $db;
		$i = 0;
		$aryLines = array();
		foreach ($lines as $line) {
			if (count($line) > 0) {
				$aryLines[$i]['ref'] = $line->ref;
				$aryLines[$i]['label'] = $line->label;
				$aryLines[$i]['desc'] = $line->desc;
				$aryLines[$i]['id'] = $line->rowid;
				$aryLines[$i]['pu_ht'] = $line->subprice;
				$aryLines[$i]['pu_ttc'] = $line->total_ttc/$line->qty;
				$aryLines[$i]['price'] = $line->price;
				$aryLines[$i]['qty'] = $line->qty;
				$aryLines[$i]['tva_tx'] = $line->tva_tx;
				$aryLines[$i]["localtax1_type"] 	= $line->localtax1_type;
				$aryLines[$i]["localtax1_tx"] 	= $line->localtax1_tx;
				$aryLines[$i]["localtax2_type"] 	= $line->localtax2_type;
				$aryLines[$i]["localtax2_tx"] 	= $line->localtax2_tx;
				$aryLines[$i]['idProduct'] = $line->fk_product;
				$aryLines[$i]['discount'] = $line->remise_percent;
				$aryLines[$i]['discount_qty'] = $line->remise;
				$aryLines[$i]['total_ht'] = $line->total_ht;
				$aryLines[$i]['total_tva'] = $line->total_tva;
				$aryLines[$i]['total_localtax1'] = $line->total_localtax1;
				if ($line->total_localtax1 != 0) {
					$aryLines[$i]['uselocaltax1_rate'] = 1;
				}
				$aryLines[$i]['total_localtax2'] = $line->total_localtax2;
				if ($line->total_localtax2 != 0) {
					$aryLines[$i]['uselocaltax2_rate'] = 1;
				}
				$aryLines[$i]['total_ttc'] = $line->total_ttc;
				$aryLines[$i]["tot_price_ttc"] = self::calcule_total_ttc_without_discount($aryLines[$i]);
				$i++;
			}
		}
		return $aryLines;
	}

    /**
     *
     * Create ticket into Database
     *
     * @param    array $aryTicket Ticket object
     * @return int
     */
	private  function CreateTicket($aryTicket)
	{
		global $db, $user;
		$data = $aryTicket['data'];
		if ($data['idsource'] > 0) {
			$prods_returned = self::testSource($aryTicket);
			if (count($prods_returned) > 0) {
				return -6;
			}
			$vater = self::fetch($data['idsource']);
			$data['payment_type'] = $vater->mode_reglement_id;
		}
		$cash = new Cash($db);
		$terminal = $data["cashId"];
		$cash->fetch($terminal);
		if (!$data['customerId']) {
			$socid = $cash->fk_soc;
			$data['customerId'] = $socid;
		} else {
			$socid = $data['customerId'];
		}
		if (!$data['employeeId']) {
			$employee = $_SESSION['uid'];
		} else {
			$employee = $data['employeeId'];
		}
		$employ = new User($db);
		$employ->fetch($employee);
		$db->begin();	
		$object = new Ticket($db);
		$object->type = $data['type'];
		$object->socid = $socid;
		$object->statut = $data['state'];
		$object->fk_cash = $terminal;
		$object->remise_percent = $data['discount_global'];
		$object->remise_absolut = $data['discount_qty'];
		if ($data['customerpay1'] > 0) {
			$object->mode_reglement_id = $cash->fk_modepaycash;
		} else {
			if ($data['customerpay2'] > 0) {
				$object->mode_reglement_id = $cash->fk_modepaybank;
			} else {
				$object->mode_reglement_id = $cash->fk_modepaybank_extra;
			}
		}
		$object->fk_place = $data['id_place'];
		$object->note = $data['note'];
		$object->customer_pay = $data['customerpay'];
		$object->diff_payment = $data['difpayment'];
		$object->id_source = $data['idsource'];

		
		$idTicket = $object->create($employee, 1, 0);
		$data['ref'] = $object->ref;

		if ($idTicket < 0) {
			$db->rollback();
			return -1;
		} else {
			//Adding lines
			$data['id'] = $idTicket;
			if ($data['id_place']) {
				$place = new Place($db);
				$place->fetch($data['id_place']);
			}
			$idLines = self::addTicketLines($object, $data['lines']);

			if ($idLines < 0) {
				$db->rollback();
				return -2;
			} else {
				if ($object->statut != 0) {
					//Adding Payments
					$payment = self::addPayment($data);
					if (!$payment) {
						$db->rollback();
						return -3;
					} else {
						if ($object->diff_payment <= 0) {
							$object->set_paid($employee);
						}
					}
					//Decrease stock
					$stock = self::quitSotck($object, $cash, $user, $data['lines'], ($object->type == 1 ? true : false));
					if ($stock) {
						$db->rollback();
						return -4;
					}
					// liberar puesto
					if ($place) {
						$place->free_place();
					}
				} else {
					// usar puesto
					if ($place) {
						$place->set_place($idTicket);
					}
				}
			}
		}


		$db->commit();

		return $idTicket;
	}

    /**
     *
     * Create facture into Database
     *
     * @param    array $aryTicket Ticket object
     * @return int
     */
	
	private  function CreateFacture($aryTicket)
	{
		global $db, $user, $conf, $langs;
		$data = $aryTicket['data'];
		$idTicket = $data["id"];
		if ($data['idsource'] > 0) {
			$prods_returned = self::testSourceFac($aryTicket);
			if (count($prods_returned) > 0) {
				return -6;
			}
			$vater = new Facture($db);
			$vater->fetch($data['idsource']);
			$data['payment_type'] = $vater->mode_reglement_id;
			if ($conf->numberseries->enabled && $conf->global->NUMBERSERIES_POS) {
                $data['serie'] = $vater->array_options['options_serie'];
            }
		}
		$cash = new Cash($db);
		$terminal = $data['cashId'];
		$cash->fetch($terminal);
		if (!$data['customerId']) {
			$socid = $cash->fk_soc;
			$data['customerId'] = $socid;
		} else {
			$socid = $data['customerId'];
		}
		if (!$data['employeeId']) {
			$employee = $_SESSION['uid'];
		} else {
			$employee = $data['employeeId'];
		}
		//if ($data['mode'] == 1) {
			$object = new Facturesim($db);
		/*} else {
			$object = new Facture($db);
		}*/
		$object->type = ($data['type'] == 0 ? 0 : 2);
		$object->socid = $socid;
		$object->statut = $data['state'];
		$object->fk_cash = $terminal;
		$object->remise_percent = $data['discount_global'];
		$object->remise_absolue = $data['discount_qty'];
		if ($data['customerpay1'] > 0) {
			$object->mode_reglement_id = $cash->fk_modepaycash;
		} else {
			if ($data['customerpay2'] > 0) {
				$object->mode_reglement_id = $cash->fk_modepaybank;
			} else {
				$object->mode_reglement_id = $cash->fk_modepaybank_extra;
			}
		}
		$object->fk_place = $data['id_place'];
		$object->note_private = $data['note'];
		$object->customer_pay = $data['customerpay'];
		if ($object->customer_pay > 0) {
			$object->diff_payment = $data['difpayment'];
		} else {
			$object->diff_payment = $data['total_ttc'];
		}
		$object->fk_facture_source = $data['idsource'];
		$employ = new User($db);
		$employ->fetch($employee);
		$employ->getrights();
		$now = dol_now();
		$object->date = $now;
		if ($conf->numberseries->enabled && $conf->global->NUMBERSERIES_POS) {
            $object->array_options['options_serie'] = $data['serie'];
        }
		$soc = new Societe($db);
		$soc->fetch($socid);
		if (!$soc->idprof1 && !empty($conf->global->SOCIETE_IDPROF1_INVOICE_MANDATORY)) {
			return -7;
		}
		$db->begin();
		$idFacture = $object->create($employ);
		if ($idFacture < 0) {
			$db->rollback();
			return -1;
		}
		if ($object->statut == 1 || $object->type == 2) {
			$num = $object->getNextNumRef($soc);
			// Validate
			$sql = 'UPDATE ' . MAIN_DB_PREFIX . 'facture';
			$sql .= " SET facnumber='" . $num . "', fk_statut = 1, fk_user_valid = " . $employ->id . ", date_valid = '" . $db->idate($now) . "'";
			if (!empty($conf->global->FAC_FORCE_DATE_VALIDATION))    // If option enabled, we force invoice date
			{
				$sql .= ', datef=' . $db->idate($now);
				$sql .= ', date_lim_reglement=' . $db->idate($now);
			}
			$sql .= ' WHERE rowid = ' . $object->id;
			dol_syslog(__METHOD__ . "::validate sql=" . $sql);
			$resql = $db->query($sql);
			$object->ref = $num;
		}
		$sql = 'INSERT INTO ' . MAIN_DB_PREFIX . 'pos_facture (fk_cash, fk_place,fk_facture,customer_pay) VALUES (' . $object->fk_cash . ',' . ($object->fk_place ? $object->fk_place : 'null') . ',' . $idFacture . ',' . $object->customer_pay . ')';
		dol_syslog("pos_facture::update sql=" . $sql);
		$resql = $db->query($sql);
		if (!$resql) {
			$db->rollback();
			return -1;
		}
		$data['ref'] = $object->ref;
		
		$idLines = self::addFactureLines($object, $data['lines']);
		if ($idLines < 0 ) {
			$db->rollback();
			return -2;
		}
		if (abs($object->total_ttc - $data['total_ttc']) > 0.2 ) {
			dol_syslog("pos_facture::Error. Check Product Price Calculation. Total Acumulated=" + $object->total_ttc + "vs received from POS = " + $data['total_ttc'] );
			$db->rollback();
			return -2;
		}
        //introducir descuentos debería ponerse como pago, no como línea
        if(self::ApplyDiscounts($data, $object, $user)  < 0) {
        	$db->rollback();
			return -5;
        }

        $object->fetch($idFacture);
		$payment = self::addPaymentFac($object, $cash, $employ, $data);
		if ($payment < 0) {
			$db->rollback();
			return -3;
		}
		$stock = self::quitSotck($object, $cash, $user, $data['lines'], ($object->type == 2 ? true : false));
		if ($stock) {
			$db->rollback();
			return -4;
		}
		if ($object->statut == '1'){
            $paiement = $object->getSommePaiement();
            $creditnotes=$object->getSumCreditNotesUsed();
            $deposits=$object->getSumDepositsUsed();
            $remaintopay=price2num($object->total_ttc - $paiement - $creditnotes - $deposits,'MT');
            if ($remaintopay == '0'){
                $object->set_paid($employ);
            }
        }
		if ($idTicket) {
			$ticket = new Ticket($db);
			$ticket->fetch($idTicket);
			$ticket->delete_ticket();
		}
		$db->commit();
		return $idFacture;
	}

		/**
	 *
	 * Update Discuounts into Database
	 * @param    array $data Ticket 
	 * @param    object $object facture
	 * @param    object $user 
	 */
	private  function ApplyDiscounts($data, $object, $user){
		global $db;
		$resp = 1;
		if ($data['total_coupons'] > 0) {
			$diffpending = 0; 
			if ($data['total_coupons'] > $data['total_ttc']) {
				$diffpending = $data['total_coupons'] - $data['total_ttc'];
			}	
        	require_once DOL_DOCUMENT_ROOT . '/core/class/discount.class.php';
        	
        	$discount = new DiscountAbsolute($db);
        	foreach ($data['coupons'] as $fk_facture_source => $coupons) {
    			foreach ($coupons as  $coupon) {
    				$discount->fetch($coupon["id"]);
    				if (!is_null($discount->fk_facture)) {
    					return -1;
    				}
    				if ($diffpending > $discount->amount_ttc) {
    					$diffpending -= $discount->amount_ttc;
    				}
    				else {
    					if ($diffpending > 0) {
    						$newAmount = $discount->amount_ttc - $diffpending;
    						if (Facturesim::update_discount_amount($discount, $newAmount) < 0)
    							return -1;					
    						$newdiscount = clone $discount;
    						Facturesim::recalcule_discount_amount($newdiscount, $diffpending);
    						$new_discount_id = $newdiscount->create($user);
    						if ( $new_discount_id< 0)
    							return -1;
    						if ( self::createPosDiscount($new_discount_id, $data['cashId'], $data['employeeId'], $discount->id) < 0)
    							return -1;
    						$diffpending = 0;
    					}
    					if (preg_match('/\(CREDIT_NOTE\)/', $discount->description)) {
        					$resp = $discount->link_to_invoice(0, $object->id);
        					if($resp > 0) {
        						$resp = self::updatePosDiscount($discount->id, $data['cashId'], $data['employeeId']);
        					}
    					}
			            else {
			                $resp = $object->insert_discount($coupon["id"]);
			            }
    				}
    			}	
        	} 
        }
        return $resp;
     } 


     private  function createPosDiscount($discount_id, $cash_id, $employ_id, $discount_source = null){
     	global $db;
     	require_once 'pos_discount.class.php';
     	$pos_discount = new PosDiscount($db);
     	$pos_discount->fk_discount= $discount_id;
     	$pos_discount->fk_cash_c = $cash_id;
     	$pos_discount->fk_user_c = $employ_id;
     	$pos_discount->fk_discount_source = $discount_source;
     	return $pos_discount->create();
     }


     private  function updatePosDiscount($discount_id, $cash_id, $employ_id){
     	global $db;
     	require_once 'pos_discount.class.php';
     	$pos_discount = new PosDiscount($db);
     	$pos_discount->fk_discount= $discount_id;
     	return $pos_discount->link_to($cash_id, $employ_id);
     }
  	/**
	 *
	 * Update Ticket into Database
	 * @param    array $aryTicket Ticket object
	 */
	private  function UpdateTicket($aryTicket)
	{
		global $db, $user;
		$data 		= $aryTicket['data'];
		$lines 		= $data['lines'];
		$idTicket 	= $data['id'];
		$cash 		= new Cash($db);
		$terminal 	= $_SESSION['TERMINAL_ID'];
		$cash->fetch($terminal);

		if (!$data['customerId']) {
			$socid = $cash->fk_soc;
		} else {
			$socid = $data['customerId'];
		}
		if (!$data['employeeId']) {
			$employee = $_SESSION['uid'];
		} else {
			$employee = $data['employeeId'];
		}
		$object = new Ticket($db);
		$object->fetch($idTicket);
		$object->type = $data['type'];
		$object->socid = $socid;
		$object->statut = $data['state'];
		$object->fk_cash = $_SESSION['TERMINAL_ID'];
		$object->remise_percent = $data['discount_global'];
		$object->remise_absolut = $data['discount_qty'];
		$object->mode_reglement_id = $data['payment_type'];
		$object->fk_place = $data['id_place'];
		$object->note = $data['note'];
		if ($data['payment_type'] != $cash->fk_modepaycash) {
			if ($data['points'] > 0) {
				$object->customer_pay = $data['total_with_points'];
			} else {
				$object->customer_pay = $data['total_ttc'];
			}
		} else {
			$object->customer_pay = $data['customerpay'];
		}
		$data['customerpay'] = $object->customer_pay;
		$object->diff_payment = $data['difpayment'];
		$object->id_source = $data['idsource'];
		$userstatic = new User($db);
		$userstatic->fetch($employee);

		$db->begin();

		$res = $object->update($userstatic->id);
		$data['ref'] = $object->ref;
		if ($res < 0) {
			$db->rollback();
			return -5;
		} else {
			//Adding lines
            $idLines = self::addTicketLines($object, $lines);
            if ($idLines < 0) {

				$db->rollback();
				return -2;
			} else {
				if ($data['id_place']) {
					$place = new Place($db);
					$place->fetch($object->fk_place);
				}	

				if ($object->statut != 0) {
					//Adding Payments
					$payment = self::addPayment($data);
					if (!$payment) {
						$db->rollback();
						return -3;
					} else {
						if ($object->diff_payment <= 0) {
							$object->set_paid($user);
						}
					}
					//Decrease stock
					$stock = self::quitSotck($object, $cash, $user, $data['lines'], ($object->type == 1 ? true : false));
					if ($stock) {
						$db->rollback();
						return -4;
					}
					// liberar puesto
					if ($data['id_place'])  $place->free_place();

				} else {
					// usar puesto
					if ($data['id_place']) $place->set_place($idTicket);

				}
			}
		}

		$db->commit();
		return $idTicket;

	}

	/**
	 *    Delete ticket
	 * @param        int $idTicket Id of ticket to delete
	 * @return        int                    <0 if KO, >0 if OK
	 */
	/*public static function DeleteTicket($idTicket=0)
	{
		global $db;

		$object= new Ticket($db);
		$db->begin;
		$res=$object->delete($idTicket);

		if ($res==1)
		{
			$reslines=deleteTicketLines($idTicket);
			if($reslines==1)
			{
				$db->commit();
			}
			else
			{
				$db->rollback();
				$res=-1;
			}
		}
		else
		{
			$db->rollback;
		}

		return $res;
	}*/

	/**
	 *        Add ticket line into database (linked to product/service or not)
	 * @param        array $lines Ticket Lines
	 * @return        array                        Result of adding
	 */
	private  function addTicketLines($ticket, $lines)
	{
		global $db;
		$res = 0;
		if ( (!is_array($lines)) || (count($lines) == 0)) {
			return -1;
		}
		self::deleteTicketLines($ticket->id);
		
		$ticket->total_ht 	= 0;
        $ticket->total_tva	= 0;
        $ticket->localtax1 	= 0;
        $ticket->localtax2 	= 0;
        $ticket->total_ttc 	= 0;
		$i = 1;
		$ret = 1;
		foreach ($lines as $line) {
			$product_static = new Product($db);
			$product_static->id = $line['idProduct'];
			$product_static->load_stock();
			if (($line['stock'] != "all") && ($product_static->stock_reel < 1 || $product_static->stock_reel < $line['cant'])) {		 
				$ret = -4;
				break;
			}
			// $line = array_merge($line,calcule_product_price($line));		
			dol_syslog("Ticket::type  " . $ticket->type, LOG_DEBUG);
        
			if ($ticket->type == 1) {
				$line['qty'] *= -1;
				$line['pu_ht'] *= -1;
				$line['total_ht']*= -1;
				$line['total_tva']*= -1;
				$line['total_localtax1']*= -1;
				$line['total_localtax2']*= -1;
				$line['total_ttc']*= -1;
				$line['price']*= -1;
				$line['discount_qty']*= -1;
			}
			$line['rang'] = $i;
			$line['desc'] = $line['label'];
			$res = $ticket->addline2($line);
			if ($res > 0) {
				$ticket->total_ht 	+= $line['total_ht']; 
		        $ticket->total_tva	+= $line['total_tva'];
		        $ticket->localtax1 	+= $line['total_localtax1'];
		        $ticket->localtax2 	+= $line['total_localtax2'];
		        $ticket->total_ttc 	+= $line['total_ttc'];
		    }
			else{
				$ret = -1;
				break;	
			}
			$i++;	
		}

		if ($ret > 0) {
			return Pos::update_obj_price($ticket);
		}
		return $ret;
	}

	/**
	 *        Add ticket line into database (linked to product/service or not)
	 * @param        array $lines Ticket Lines
	 * @return        array                        Result of adding
	 */
	private  function addFactureLines($facture, $lines){
		global $db, $conf, $user;
		$ret = 1;
		$facture->total_ht 		= 0; 
        $facture->tva			= 0;
        $facture->localtax1 	= 0;
        $facture->localtax2 	= 0;
        $facture->total_ttc 	= 0;
		if ( (!is_array($lines)) || (count($lines) == 0)) {
			return -1;
		}
		$i = 1;
		foreach ($lines as $line) {
			if ((count($line) > 0) && ($line['idProduct'] > 0)) {
				$product_static = new Product($db);
				$product_static->id = $line['idProduct'];
				$product_static->load_stock();
				if ((!empty($conf->global->STOCK_MUST_BE_ENOUGH_FOR_INVOICE)) && ($facture->type == 0 || $product_static->stock_reel < $line['cant'])) {	
					$ret = -4;
					break;
				}
				$line['description'] = $line['description'] . " " . $line['note'];
				$line['number'] = $i;
				//$res = $facture->addline2($line);
				$res = $facture->addline2($line);
				if ($res > 0) {
					if ($conf->discounts->enabled && $line['is_promo'] == 1) {
					dol_include_once('/discounts/class/discount_doc.class.php');
						$dis_doc = new Discounts_doc($db);
						$dis_doc->type_doc = 3;//Factura
						$dis_doc->fk_doc = $res;
						$dis_doc->ori_subprice = $line['price'];
						$dis_doc->ori_totalht = $line['price'] * $line['qty'];
						$dis_doc->create($user);
					}
					$facture->total 		+= $line['total_ht']; 
			        $facture->tva			+= $line['total_tva'];
			        $facture->localtax1 	+= $line['total_localtax1'];
			        $facture->localtax2 	+= $line['total_localtax2'];
			        $facture->total_ttc 	+= $line['total_ttc'];	# code...
				} else {
					$ret = -3;
					break;	
				}
				$i++;
			}
		}	 
		if ($ret > 0) {
			return Pos::update_obj_price($facture);
		}
		return $ret;
	}
	/**
	 *    Delete line in database
	 * @param        int $idTicket Id Ticket to delete lines
	 * @return        int                        <0 if KO, >0 if OK
	 */
	public  function deleteTicketLines($idTicket)
	{
		global $db;

		$sql = "SELECT rowid";
		$sql .= " FROM " . MAIN_DB_PREFIX . "pos_ticketdet";
		$sql .= " WHERE  fk_ticket= " . $idTicket;

		$resql = $db->query($sql);

		if ($resql) {
			$num = $db->num_rows($resql);
			$i = 0;
			$object = new Ticket($db);

			while ($i < $num) {
				$objp = $db->fetch_object($resql);
				$res = $object->deleteline($objp->rowid);
				if ($res != 1) {
					return -1;
				}

				$i++;
			}

		}
		return 1;
	}

	/**
	 *
	 * Returns terminals of POS
	 */
	public  function select_Terminals()
	{
		global $db, $conf;

		$sql = "SELECT rowid, code, name, fk_device, is_used, fk_user_u, tactil";
		$sql .= " FROM " . MAIN_DB_PREFIX . "pos_cash";
		$sql .= " WHERE entity = " . $conf->entity;
		$sql .= " AND is_used = 0 OR (is_used=1 AND is_closed=1)";

		$res = $db->query($sql);

		if ($res) {
			$terms = array();
			$i = 0;
			while ($record = $db->fetch_array($res)) {
				foreach ($record as $cle => $valeur) {
					$terms[$i][$cle] = $valeur;
				}
				$i++;
			}
			return $terms;
		} else {
			return -1;
		}
	}

	/**
	 *
	 * Returns terminals of POS
	 */
	public  function checkUserTerminal($userid, $terminalid)
	{
		global $db, $conf;

		if ($conf->global->POS_USER_TERMINAL) {

			$sql = "SELECT rowid";
			$sql .= " FROM " . MAIN_DB_PREFIX . "pos_users";
			$sql .= " WHERE fk_terminal = " . $terminalid;
			$sql .= " AND fk_object = " . $userid;
			$sql .= " AND objtype = 'user'";

			$sql .= "UNION SELECT g.rowid";
			$sql .= " FROM " . MAIN_DB_PREFIX . "pos_users as pu";
			$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "usergroup_user as g ON pu.fk_object = g.fk_usergroup	";
			$sql .= " WHERE pu.fk_terminal = " . $terminalid;
			$sql .= " AND g.fk_user = " . $userid;
			$sql .= " AND pu.objtype = 'group'";

			$res = $db->query($sql);

			if ($res) {
				$num = $db->num_rows($res);

				if ($num > 0) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		}
		return true;
	}

	/**
	 *
	 * Return Ticket history
	 *
	 * @param    string $ticketnumber ticket number for filter
	 * @param    int    $stat         status of ticket
	 * @param  int      $mode         0, count rows; 1, get rows
	 * @param    string $terminal     terminal for filter
	 * @param    string $seller       seller user for filter
	 * @param    string $client       client for filter
	 * @param    float  $amount       amount for filter
	 * @param    int    $month        month for filter
	 * @param    int    $year         year for filter
	 */
	public  function getHistoric(
			$ticketnumber = '',
			$stat,
			$terminal = '',
			$seller = '',
			$client = '',
			$amount = '',
			$months = 0,
			$years = 0
	) {
		global $db, $conf, $user, $langs;

		$ret = -1;
		$function = "GetHistoric";

		$sql = ' SELECT ';

		$sql .= ' f.rowid as ticketid, f.ticketnumber, f.total_ttc,';
		$sql .= ' f.date_closed, f.fk_user_close, f.date_creation as datec,';
		$sql .= ' f.fk_statut, f.customer_pay, f.difpayment, f.fk_place, ';
		$sql .= ' s.nom, s.rowid as socid,';
		$sql .= ' u.firstname, u.lastname,';
		$sql .= ' t.name, f.fk_cash, f.type';

		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'societe as s';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON s.rowid = sc.fk_soc AND sc.fk_user = " . $user->id;
		} // We need this table joined to the select in order to filter by sale
		$sql .= ', ' . MAIN_DB_PREFIX . 'pos_ticket as f';
		$sql .= ', ' . MAIN_DB_PREFIX . 'pos_cash as t';
		$sql .= ', ' . MAIN_DB_PREFIX . 'user as u';
		$sql .= ' WHERE f.fk_soc = s.rowid';
		$sql .= " AND f.entity = " . $conf->entity;
		$sql .= " AND f.fk_cash = t.rowid";

		if ($conf->global->POS_USER_TERMINAL) {
			$sql .= " AND f.fk_cash IN (";
			$sql .= "SELECT pu.fk_terminal FROM " . MAIN_DB_PREFIX . "pos_users as pu WHERE pu.fk_object = " . $_SESSION["uid"] . " AND pu.objtype = 'user'";
			$sql .= " UNION SELECT pu.fk_terminal FROM " . MAIN_DB_PREFIX . "pos_users as pu LEFT JOIN " . MAIN_DB_PREFIX . "usergroup_user as ug ON pu.fk_object = ug.fk_usergroup";
			$sql .= " WHERE ug.fk_user = " . $_SESSION["uid"] . " AND pu.objtype = 'group')";
		}

		if ($stat >= 0 && $stat != 4 && $stat <= 99) {
			$sql .= " AND f.fk_statut = " . $stat;
			$sql .= " AND f.type = 0";
		}
		if ($stat == 4) {
			$sql .= " AND f.type = 1";
		}

		//if ($socid) $sql.= ' AND s.rowid = '.$socid;

		if ($ticketnumber) {
			$sql .= ' AND f.ticketnumber LIKE \'%' . $db->escape(trim($ticketnumber)) . '%\'';
		}
		if ($months > 0) {
			if ($years > 0) {
				$sql .= " AND f.date_ticket BETWEEN '" . $db->idate(dol_get_first_day($years, $months,
								false)) . "' AND '" . $db->idate(dol_get_last_day($years, $months, false)) . "'";
			} else {
				$sql .= " AND date_format(f.date_ticket, '%m') = '" . $months . "'";
			}
		} else {
			if ($years > 0) {
				$sql .= " AND f.date_ticket BETWEEN '" . $db->idate(dol_get_first_day($years, 1,
								false)) . "' AND '" . $db->idate(dol_get_last_day($years, 12, false)) . "'";
			}
		}
		$now = dol_now();
		$time = dol_getdate($now);
		$day = $time['mday'];
		$month = $time['mon'];
		$year = $time['year'];

		if ($stat == 100) {//Today
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
			$sql .= " AND f.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 101) {//Yesterday
			$time = dol_get_prev_day($day, $month, $year);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $time['day'], 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $time['day'], 23, 59, 59);
			$sql .= " AND f.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 102) {//This week
			$time = dol_get_first_day_week($day, $month, $year);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $time['first_day'], 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
			$sql .= " AND f.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 103) {//Last week
			$time = dol_get_first_day_week($day, $month, $year);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time['prev_year'], $time['prev_month'], $time['prev_day'], 0, 0,
					0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $year,
					$time['first_day'] - 1 == 0 ? $time['prev_month'] : $month,
					$time['first_day'] - 1 == 0 ? $time['prev_day'] + 6 : $time['first_day'] - 1, 23, 59, 59);
			$sql .= " AND f.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 104) {//Two weeks ago
			$time = dol_get_prev_week($day, '', $month, $year);
			$time2 = dol_get_prev_week($time['day'], '', $time['month'], $time['year']);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time2['year'], $time2['month'], $time2['day'], 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'],
					$time['day'] - 1 == 0 ? $time2['month'] : $time['month'],
					$time['day'] - 1 == 0 ? $time2['day'] + 6 : $time['day'] - 1, 23, 59, 59);
			$sql .= " AND f.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 105) {//Three weeks ago
			$time = dol_get_prev_week($day, '', $month, $year);
			$time = dol_get_prev_week($time['day'], '', $time['month'], $time['year']);
			$time2 = dol_get_prev_week($time['day'], '', $time['month'], $time['year']);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time2['year'], $time2['month'], $time2['day'], 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'],
					$time['day'] - 1 == 0 ? $time2['month'] : $time['month'],
					$time['day'] - 1 == 0 ? $time2['day'] + 6 : $time['day'] - 1, 23, 59, 59);
			$sql .= " AND f.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 106) {//This month
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, 01, 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
			$sql .= " AND f.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 107) {//One month ago
			$time = dol_get_prev_month($month, $year);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $day, 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
			$sql .= " AND f.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 108) {//Last month
			$time = dol_get_prev_month($month, $year);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], 01, 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], 31, 0, 0, 0);
			$sql .= " AND f.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($terminal) {
			$sql .= ' AND t.name LIKE \'%' . $db->escape(trim($terminal)) . '%\'';
		}
		if ($seller) {
			$sql .= ' AND (u.firstname LIKE \'%' . $db->escape(trim($seller)) . '%\'';
			$sql .= ' OR u.lastname LIKE \'%' . $db->escape(trim($seller)) . '%\')';
		}
		if ($client) {
			$prefix = empty($conf->global->COMPANY_DONOTSEARCH_ANYWHERE) ? '%' : '';    // Can use index if COMPANY_DONOTSEARCH_ANYWHERE is on
			$sql .= ' AND s.nom LIKE \'' . $prefix . $db->escape(trim($client)) . '%\'';
		}

		if ($amount) {
			$sql .= ' AND f.total_ttc = \'' . $db->escape(trim($amount)) . '\'';
		}

		$sql .= ' GROUP BY f.rowid, u.firstname, u.lastname';

		$sql .= ' ORDER BY ';
		$sql .= ' datec DESC ';
		$sql .= 'LIMIT 0,50';

		$res = $db->query($sql);

		if ($res) {
			$num = $db->num_rows($res);
			$i = 0;
			$ticketstatic = new Ticket($db);
			$tickets = array();
			while ($i < $num) {
				$obj = $db->fetch_object($res);

				$tickets[$i]["id"] = $obj->ticketid;
				$tickets[$i]["type"] = $obj->type;
				$tickets[$i]["ticketnumber"] = $obj->ticketnumber;
				$tickets[$i]["date_creation"] = dol_print_date($db->jdate($obj->datec), 'dayhour');
				$tickets[$i]["date_close"] = dol_print_date($db->jdate($obj->date_closed), 'dayhour');
				$tickets[$i]["fk_place"] = $obj->fk_place;

				$cash = new Cash($db);
				$cash->fetch($obj->fk_cash);
				$tickets[$i]["terminal"] = $cash->name;

				$userstatic = new User($db);
				$userstatic->fetch($obj->fk_user_close);
				$tickets[$i]["seller"] = $userstatic->getFullName($langs);

				$tickets[$i]["client"] = $obj->nom;
				$tickets[$i]["amount"] = $obj->total_ttc;
				$tickets[$i]["customer_pay"] = $obj->customer_pay;
				$tickets[$i]["statut"] = $obj->fk_statut;
				$tickets[$i]["statutlabel"] = $ticketstatic->LibStatut($obj->fk_statut, 0);

				$i++;
			}
			return ErrorControl($tickets, $function);


		} else {
			return ErrorControl($ret, $function);
		}

	}

	/**
	 *
	 * Return Facture history
	 *
	 * @param    string $ticketnumber ticket number for filter
	 * @param    int    $stat         status of ticket
	 * @param  int      $mode         0, count rows; 1, get rows
	 * @param    string $terminal     terminal for filter
	 * @param    string $seller       seller user for filter
	 * @param    string $client       client for filter
	 * @param    float  $amount       amount for filter
	 * @param    int    $month        month for filter
	 * @param    int    $year         year for filter
	 */
	public  function getHistoricFac(
			$ticketnumber = '',
			$stat,
			$terminal = '',
			$seller = '',
			$client = '',
			$amount = '',
			$months = 0,
			$years = 0
	) {
		global $db, $conf, $user, $langs;

		$ret = -1;
		$function = "GetHistoric";

		$sql = ' SELECT ';

		$sql .= ' f.rowid as ticketid, f.facnumber, f.total_ttc,';
		$sql .= ' f.fk_user_valid, f.datec as datec,';
		$sql .= ' f.fk_statut, pf.fk_place, ';
		$sql .= ' s.nom, s.rowid as socid,';
		$sql .= ' u.firstname, u.lastname,';
		$sql .= ' t.name, pf.fk_cash, f.type';

		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'societe as s';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON s.rowid = sc.fk_soc AND sc.fk_user = " . $user->id;
		} // We need this table joined to the select in order to filter by sale
		$sql .= ', ' . MAIN_DB_PREFIX . 'facture as f';
		$sql .= ', ' . MAIN_DB_PREFIX . 'pos_facture as pf';
		$sql .= ', ' . MAIN_DB_PREFIX . 'pos_cash as t';
		$sql .= ', ' . MAIN_DB_PREFIX . 'user as u';
		$sql .= ' WHERE f.fk_soc = s.rowid';
		$sql .= " AND f.entity = " . $conf->entity;
		$sql .= " AND pf.fk_cash = t.rowid";
		$sql .= " AND pf.fk_facture = f.rowid";
		$sql .= " AND u.rowid = f.fk_user_valid";

		if ($conf->global->POS_USER_TERMINAL) {
			$sql .= " AND pf.fk_cash IN (";
			$sql .= "SELECT pu.fk_terminal FROM " . MAIN_DB_PREFIX . "pos_users as pu WHERE pu.fk_object = " . $_SESSION["uid"] . " AND pu.objtype = 'user'";
			$sql .= " UNION SELECT pu.fk_terminal FROM " . MAIN_DB_PREFIX . "pos_users as pu LEFT JOIN " . MAIN_DB_PREFIX . "usergroup_user as ug ON pu.fk_object = ug.fk_usergroup";
			$sql .= " WHERE ug.fk_user = " . $_SESSION["uid"] . " AND pu.objtype = 'group')";
		}

		if ($stat >= 0 && $stat != 4 && $stat <= 99) {
			$sql .= " AND f.fk_statut = " . $stat;
			$sql .= " AND f.type = 0";
		}
		if ($stat == 4) {
			$sql .= " AND f.type = 2";
		}

		//if ($socid) $sql.= ' AND s.rowid = '.$socid;

		if ($ticketnumber) {
			$sql .= ' AND f.facnumber LIKE \'%' . $db->escape(trim($ticketnumber)) . '%\'';
		}
		if ($months > 0) {
			if ($years > 0) {
				$sql .= " AND f.datec BETWEEN '" . $db->idate(dol_get_first_day($years, $months,
								false)) . "' AND '" . $db->idate(dol_get_last_day($years, $months, false)) . "'";
			} else {
				$sql .= " AND date_format(f.datec, '%m') = '" . $months . "'";
			}
		} else {
			if ($years > 0) {
				$sql .= " AND f.datec BETWEEN '" . $db->idate(dol_get_first_day($years, 1,
								false)) . "' AND '" . $db->idate(dol_get_last_day($years, 12, false)) . "'";
			}
		}
		$now = dol_now();
		$time = dol_getdate($now);
		$day = $time['mday'];
		$month = $time['mon'];
		$year = $time['year'];

		if ($stat == 100) {//Today
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
			$sql .= " AND f.datec BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 101) {//Yesterday
			$time = dol_get_prev_day($day, $month, $year);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $time['day'], 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $time['day'], 23, 59, 59);
			$sql .= " AND f.datec BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 102) {//This week
			$time = dol_get_first_day_week($day, $month, $year);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $time['first_day'], 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
			$sql .= " AND f.datec BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 103) {//Last week
			$time = dol_get_first_day_week($day, $month, $year);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time['prev_year'], $time['prev_month'], $time['prev_day'], 0, 0,
					0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $year,
					$time['first_day'] - 1 == 0 ? $time['prev_month'] : $month,
					$time['first_day'] - 1 == 0 ? $time['prev_day'] + 6 : $time['first_day'] - 1, 23, 59, 59);
			$sql .= " AND f.datec BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 104) {//Two weeks ago
			$time = dol_get_prev_week($day, '', $month, $year);
			$time2 = dol_get_prev_week($time['day'], '', $time['month'], $time['year']);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time2['year'], $time2['month'], $time2['day'], 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'],
					$time['day'] - 1 == 0 ? $time2['month'] : $time['month'],
					$time['day'] - 1 == 0 ? $time2['day'] + 6 : $time['day'] - 1, 23, 59, 59);
			$sql .= " AND f.datec BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 105) {//Three weeks ago
			$time = dol_get_prev_week($day, '', $month, $year);
			$time = dol_get_prev_week($time['day'], '', $time['month'], $time['year']);
			$time2 = dol_get_prev_week($time['day'], '', $time['month'], $time['year']);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time2['year'], $time2['month'], $time2['day'], 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'],
					$time['day'] - 1 == 0 ? $time2['month'] : $time['month'],
					$time['day'] - 1 == 0 ? $time2['day'] + 6 : $time['day'] - 1, 23, 59, 59);
			$sql .= " AND f.datec BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 106) {//This month
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, 01, 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
			$sql .= " AND f.datec BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 107) {//One month ago
			$time = dol_get_prev_month($month, $year);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $day, 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
			$sql .= " AND f.datec BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 108) {//Last month
			$time = dol_get_prev_month($month, $year);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], 01, 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], 31, 0, 0, 0);
			$sql .= " AND f.datec BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($terminal) {
			$sql .= ' AND t.name LIKE \'%' . $db->escape(trim($terminal)) . '%\'';
		}
		if ($seller) {
			$sql .= ' AND (u.firstname LIKE \'%' . $db->escape(trim($seller)) . '%\'';
			$sql .= ' OR u.lastname LIKE \'%' . $db->escape(trim($seller)) . '%\')';
		}
		if ($client) {
			$prefix = empty($conf->global->COMPANY_DONOTSEARCH_ANYWHERE) ? '%' : '';    // Can use index if COMPANY_DONOTSEARCH_ANYWHERE is on
			$sql .= ' AND s.nom LIKE \'' . $prefix . $db->escape(trim($client)) . '%\'';
		}

		if ($amount) {
			$sql .= ' AND f.total_ttc = \'' . $db->escape(trim($amount)) . '\'';
		}

		$sql .= ' GROUP BY f.rowid';

		$sql .= ' UNION SELECT ';

		$sql .= ' p.rowid as ticketid, p.ticketnumber, p.total_ttc,';
		$sql .= ' p.fk_user_close, p.date_creation as datec,';
		$sql .= ' p.fk_statut, p.fk_place, ';
		$sql .= ' s.nom, s.rowid as socid,';
		$sql .= ' u.firstname, u.lastname,';
		$sql .= ' t.name, p.fk_cash, p.type';

		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'societe as s';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON s.rowid = sc.fk_soc AND sc.fk_user = " . $user->id;
		} // We need this table joined to the select in order to filter by sale
		$sql .= ', ' . MAIN_DB_PREFIX . 'pos_ticket as p';
		$sql .= ', ' . MAIN_DB_PREFIX . 'pos_cash as t';
		$sql .= ', ' . MAIN_DB_PREFIX . 'user as u';
		$sql .= ' WHERE p.fk_soc = s.rowid';
		$sql .= " AND p.entity = " . $conf->entity;
		$sql .= " AND p.fk_cash = t.rowid";
		$sql .= " AND p.fk_statut = 0";
		$sql .= " AND u.rowid = p.fk_user_author";

		if ($conf->global->POS_USER_TERMINAL) {
			$sql .= " AND p.fk_cash IN (";
			$sql .= "SELECT pu.fk_terminal FROM " . MAIN_DB_PREFIX . "pos_users as pu WHERE pu.fk_object = " . $_SESSION["uid"] . " AND pu.objtype = 'user'";
			$sql .= " UNION SELECT pu.fk_terminal FROM " . MAIN_DB_PREFIX . "pos_users as pu LEFT JOIN " . MAIN_DB_PREFIX . "usergroup_user as ug ON pu.fk_object = ug.fk_usergroup";
			$sql .= " WHERE ug.fk_user = " . $_SESSION["uid"] . " AND pu.objtype = 'group')";
		}

		if ($stat >= 0 && $stat != 4 && $stat <= 99) {
			$sql .= " AND p.fk_statut = " . $stat;
		}
		if ($stat == 4) {
			$sql .= " AND p.type = 1";
		}

		//if ($socid) $sql.= ' AND s.rowid = '.$socid;

		if ($ticketnumber) {
			$sql .= ' AND p.ticketnumber LIKE \'%' . $db->escape(trim($ticketnumber)) . '%\'';
		}
		if ($months > 0) {
			if ($years > 0) {
				$sql .= " AND p.date_ticket BETWEEN '" . $db->idate(dol_get_first_day($years, $months,
								false)) . "' AND '" . $db->idate(dol_get_last_day($years, $months, false)) . "'";
			} else {
				$sql .= " AND date_format(p.date_ticket, '%m') = '" . $months . "'";
			}
		} else {
			if ($years > 0) {
				$sql .= " AND p.date_ticket BETWEEN '" . $db->idate(dol_get_first_day($years, 1,
								false)) . "' AND '" . $db->idate(dol_get_last_day($years, 12, false)) . "'";
			}
		}
		$now = dol_now();
		$time = dol_getdate($now);
		$day = $time['mday'];
		$month = $time['mon'];
		$year = $time['year'];

		if ($stat == 100) {//Today
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
			$sql .= " AND p.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 101) {//Yesterday
			$time = dol_get_prev_day($day, $month, $year);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $time['day'], 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $time['day'], 23, 59, 59);
			$sql .= " AND p.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 102) {//This week
			$time = dol_get_first_day_week($day, $month, $year);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $time['first_day'], 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
			$sql .= " AND p.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 103) {//Last week
			$time = dol_get_first_day_week($day, $month, $year);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time['prev_year'], $time['prev_month'], $time['prev_day'], 0, 0,
					0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $year,
					$time['first_day'] - 1 == 0 ? $time['prev_month'] : $month,
					$time['first_day'] - 1 == 0 ? $time['prev_day'] + 6 : $time['first_day'] - 1, 23, 59, 59);
			$sql .= " AND p.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 104) {//Two weeks ago
			$time = dol_get_prev_week($day, '', $month, $year);
			$time2 = dol_get_prev_week($time['day'], '', $time['month'], $time['year']);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time2['year'], $time2['month'], $time2['day'], 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'],
					$time['day'] - 1 == 0 ? $time2['month'] : $time['month'],
					$time['day'] - 1 == 0 ? $time2['day'] + 6 : $time['day'] - 1, 23, 59, 59);
			$sql .= " AND p.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 105) {//Three weeks ago
			$time = dol_get_prev_week($day, '', $month, $year);
			$time = dol_get_prev_week($time['day'], '', $time['month'], $time['year']);
			$time2 = dol_get_prev_week($time['day'], '', $time['month'], $time['year']);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time2['year'], $time2['month'], $time2['day'], 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'],
					$time['day'] - 1 == 0 ? $time2['month'] : $time['month'],
					$time['day'] - 1 == 0 ? $time2['day'] + 6 : $time['day'] - 1, 23, 59, 59);
			$sql .= " AND p.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 106) {//This month
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, 01, 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
			$sql .= " AND p.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 107) {//One month ago
			$time = dol_get_prev_month($month, $year);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $day, 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
			$sql .= " AND p.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($stat == 108) {//Last month
			$time = dol_get_prev_month($month, $year);
			$ini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], 01, 0, 0, 0);
			$fin = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], 31, 0, 0, 0);
			$sql .= " AND p.date_ticket BETWEEN '" . $ini . "' AND '" . $fin . "'";
		}
		if ($terminal) {
			$sql .= ' AND t.name LIKE \'%' . $db->escape(trim($terminal)) . '%\'';
		}
		if ($seller) {
			$sql .= ' AND (u.firstname LIKE \'%' . $db->escape(trim($seller)) . '%\'';
			$sql .= ' OR u.lastname LIKE \'%' . $db->escape(trim($seller)) . '%\')';
		}
		if ($client) {
			$prefix = empty($conf->global->COMPANY_DONOTSEARCH_ANYWHERE) ? '%' : '';    // Can use index if COMPANY_DONOTSEARCH_ANYWHERE is on
			$sql .= ' AND s.nom LIKE \'' . $prefix . $db->escape(trim($client)) . '%\'';
		}

		if ($amount) {
			$sql .= ' AND p.total_ttc = \'' . $db->escape(trim($amount)) . '\'';
		}

		$sql .= ' GROUP BY p.rowid';

		$sql .= ' ORDER BY ';
		$sql .= ' datec DESC ';
		$sql .= 'LIMIT 0,50';

		$res = $db->query($sql);

		if ($res) {
			$num = $db->num_rows($res);
			$i = 0;
			$ticketstatic = new Ticket($db);
			while ($i < $num) {
				$obj = $db->fetch_object($res);

				$tickets[$i]["id"] = $obj->ticketid;
				$tickets[$i]["type"] = ($obj->type == 2 ? 1 : $obj->type);
				$tickets[$i]["ticketnumber"] = $obj->facnumber;
				$tickets[$i]["date_creation"] = dol_print_date($db->jdate($obj->datec), 'dayhour');
				$tickets[$i]["date_close"] = dol_print_date($db->jdate($obj->date_closed), 'dayhour');
				$tickets[$i]["fk_place"] = $obj->fk_place;

				$cash = new Cash($db);
				$cash->fetch($obj->fk_cash);
				$tickets[$i]["terminal"] = $cash->name;

				$userstatic = new User($db);
				$userstatic->fetch($obj->fk_user_valid);
				$tickets[$i]["seller"] = $userstatic->getFullName($langs);

				$tickets[$i]["client"] = $obj->nom;
				$tickets[$i]["amount"] = $obj->total_ttc;
				$tickets[$i]["customer_pay"] = $obj->customer_pay;
				$tickets[$i]["statut"] = $obj->fk_statut;
				$tickets[$i]["statutlabel"] = $ticketstatic->LibStatut($obj->fk_statut, 0);

				$i++;
			}
			return ErrorControl($tickets, $function);


		} else {
			return ErrorControl($ret, $function);
		}

	}

	/**
	 *
	 * Count Ticket history
	 *
	 */
	public  function countHistoric()
	{
		global $db, $conf, $user;

		$ret = -1;
		$function = "GetHistoric";

		$sql = 'SELECT (SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'pos_ticket as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $_SESSION["uid"];
		} // We need this table joined to the select in order to filter by sale
		$sql .= ' WHERE f.entity = ' . $conf->entity;

		if ($conf->global->POS_USER_TERMINAL) {
			$sql2 = " AND f.fk_cash IN (";
			$sql2 .= "SELECT pu.fk_terminal FROM " . MAIN_DB_PREFIX . "pos_users as pu WHERE pu.fk_object = " . $_SESSION["uid"] . " AND pu.objtype = 'user'";
			$sql2 .= " UNION SELECT pu.fk_terminal FROM " . MAIN_DB_PREFIX . "pos_users as pu LEFT JOIN " . MAIN_DB_PREFIX . "usergroup_user as ug ON pu.fk_object = ug.fk_usergroup";
			$sql2 .= " WHERE ug.fk_user = " . $_SESSION["uid"] . " AND pu.objtype = 'group')";
		}

		$sql .= $sql2;

		$now = dol_now();
		$time = dol_getdate($now);
		$day = $time['mday'];
		$month = $time['mon'];
		$year = $time['year'];

		//Today
		$todayini = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 0, 0, 0);
		$todayfin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
		$sql .= " AND f.date_ticket BETWEEN '" . $todayini . "' AND '" . $todayfin . "' ) as today, ";

		$sql .= '(SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'pos_ticket as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $_SESSION["uid"];
		} // We need this table joined to the select in order to filter by sale
		$sql .= ' WHERE f.entity = ' . $conf->entity;

		$sql .= $sql2;

		//Yesterday
		$time = dol_get_prev_day($day, $month, $year);
		$yestini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $time['day'], 0, 0, 0);
		$yestfin = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $time['day'], 23, 59, 59);
		$sql .= " AND f.date_ticket BETWEEN '" . $yestini . "' AND '" . $yestfin . "' ) as yesterday, ";

		$sql .= '(SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'pos_ticket as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $_SESSION["uid"];
		} // We need this table joined to the select in order to filter by sale
		$sql .= ' WHERE f.entity = ' . $conf->entity;

		$sql .= $sql2;

		//This week
		$time = dol_get_first_day_week($day, $month, $year);
		$weekini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $time['first_day'], 0, 0, 0);
		$weekfin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
		$sql .= " AND f.date_ticket BETWEEN '" . $weekini . "' AND '" . $weekfin . "' ) as thisweek, ";

		$sql .= '(SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'pos_ticket as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $_SESSION["uid"];
		} // We need this table joined to the select in order to filter by sale
		$sql .= ' WHERE f.entity = ' . $conf->entity;

		$sql .= $sql2;

		//Last week
		$time = dol_get_first_day_week($day, $month, $year);
		$lweekini = sprintf("%04d%02d%02d%02d%02d%02d", $time['prev_year'], $time['prev_month'], $time['prev_day'], 0,
				0, 0);
		$lweekfin = sprintf("%04d%02d%02d%02d%02d%02d", $year,
				$time['first_day'] - 1 == 0 ? $time['prev_month'] : $month,
				$time['first_day'] - 1 == 0 ? $time['prev_day'] + 6 : $time['first_day'] - 1, 23, 59, 59);
		$sql .= " AND f.date_ticket BETWEEN '" . $lweekini . "' AND '" . $lweekfin . "' ) as lastweek, ";

		$sql .= '(SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'pos_ticket as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $_SESSION["uid"];
		} // We need this table joined to the select in order to filter by sale
		$sql .= ' WHERE f.entity = ' . $conf->entity;

		$sql .= $sql2;

		//Two weeks ago
		$time = dol_get_prev_week($day, '', $month, $year);
		$time2 = dol_get_prev_week($time['day'], '', $time['month'], $time['year']);
		$ini2week = sprintf("%04d%02d%02d%02d%02d%02d", $time2['year'], $time2['month'], $time2['day'], 0, 0, 0);
		$fin2week = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'],
				$time['day'] - 1 == 0 ? $time2['month'] : $time['month'],
				$time['day'] - 1 == 0 ? $time2['day'] + 6 : $time['day'] - 1, 23, 59, 59);
		$sql .= " AND f.date_ticket BETWEEN '" . $ini2week . "' AND '" . $fin2week . "' ) as twoweek, ";

		$sql .= '(SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'pos_ticket as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $_SESSION["uid"];
		} // We need this table joined to the select in order to filter by sale
		$sql .= ' WHERE f.entity = ' . $conf->entity;

		$sql .= $sql2;

		//Three weeks ago
		$time = dol_get_prev_week($day, '', $month, $year);
		$time = dol_get_prev_week($time['day'], '', $time['month'], $time['year']);
		$time2 = dol_get_prev_week($time['day'], '', $time['month'], $time['year']);
		$ini3week = sprintf("%04d%02d%02d%02d%02d%02d", $time2['year'], $time2['month'], $time2['day'], 0, 0, 0);
		$fin3week = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'],
				$time['day'] - 1 == 0 ? $time2['month'] : $time['month'],
				$time['day'] - 1 == 0 ? $time2['day'] + 6 : $time['day'] - 1, 23, 59, 59);
		$sql .= " AND f.date_ticket BETWEEN '" . $ini3week . "' AND '" . $fin3week . "' ) as threeweek, ";

		$sql .= '(SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'pos_ticket as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $_SESSION["uid"];
		} // We need this table joined to the select in order to filter by sale
		$sql .= ' WHERE f.entity = ' . $conf->entity;

		$sql .= $sql2;

		//This month
		$monthini = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, 01, 0, 0, 0);
		$monthfin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
		$sql .= " AND f.date_ticket BETWEEN '" . $monthini . "' AND '" . $monthfin . "' ) as thismonth, ";

		$sql .= '(SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'pos_ticket as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $_SESSION["uid"];
		} // We need this table joined to the select in order to filter by sale
		$sql .= ' WHERE f.entity = ' . $conf->entity;

		$sql .= $sql2;

		//One month ago
		$time = dol_get_prev_month($month, $year);
		$monthagoini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $day, 0, 0, 0);
		$monthagofin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
		$sql .= " AND f.date_ticket BETWEEN '" . $monthagoini . "' AND '" . $monthagofin . "' ) as monthago, ";

		$sql .= '(SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'pos_ticket as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $_SESSION["uid"];
		} // We need this table joined to the select in order to filter by sale
		$sql .= ' WHERE f.entity = ' . $conf->entity;

		$sql .= $sql2;

		//Last month
		$time = dol_get_prev_month($month, $year);
		$lmonthini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], 01, 0, 0, 0);
		$lmonthfin = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], 31, 0, 0, 0);
		$sql .= " AND f.date_ticket BETWEEN '" . $lmonthini . "' AND '" . $lmonthfin . "' ) as lastmonth";

		$res = $db->query($sql);

		if ($res) {
			$obj = $db->fetch_object($res);

			$result["today"] = $obj->today;
			$result["yesterday"] = $obj->yesterday;
			$result["thisweek"] = $obj->thisweek;
			$result["lastweek"] = $obj->lastweek;
			$result["twoweek"] = $obj->twoweek;
			$result["threeweek"] = $obj->threeweek;
			$result["thismonth"] = $obj->thismonth;
			$result["monthago"] = $obj->monthago;
			$result["lastmonth"] = $obj->lastmonth;

			return ErrorControl($result, $function);
		} else {
			return ErrorControl($ret, $function);
		}

	}

	/**
	 *
	 * Count Facture history
	 *
	 */
	public  function countHistoricFac()
	{
		global $db, $conf, $user;

		$ret = -1;
		$function = "GetHistoric";

		$sql = 'SELECT (SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $user->id;
		} // We need this table joined to the select in order to filter by sale
		$sql .= ', ' . MAIN_DB_PREFIX . 'pos_facture as pf';
		$sql .= ' WHERE f.entity = ' . $conf->entity;
		$sql .= ' AND pf.fk_facture = f.rowid';

		if ($conf->global->POS_USER_TERMINAL) {
			$sql2 = " AND pf.fk_cash IN (";
			$sql2 .= "SELECT pu.fk_terminal FROM " . MAIN_DB_PREFIX . "pos_users as pu WHERE pu.fk_object = " . $_SESSION["uid"] . " AND pu.objtype = 'user'";
			$sql2 .= " UNION SELECT pu.fk_terminal FROM " . MAIN_DB_PREFIX . "pos_users as pu LEFT JOIN " . MAIN_DB_PREFIX . "usergroup_user as ug ON pu.fk_object = ug.fk_usergroup";
			$sql2 .= " WHERE ug.fk_user = " . $_SESSION["uid"] . " AND pu.objtype = 'group')";
		}

		$sql .= $sql2;

		$now = dol_now();
		$time = dol_getdate($now);
		$day = $time['mday'];
		$month = $time['mon'];
		$year = $time['year'];

		//Today
		$todayini = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 0, 0, 0);
		$todayfin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
		$sql .= " AND f.datec BETWEEN '" . $todayini . "' AND '" . $todayfin . "' ) as today, ";

		$sql .= '(SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $user->id;
		} // We need this table joined to the select in order to filter by sale
		$sql .= ', ' . MAIN_DB_PREFIX . 'pos_facture as pf';
		$sql .= ' WHERE f.entity = ' . $conf->entity;
		$sql .= ' AND pf.fk_facture = f.rowid';

		$sql .= $sql2;

		//Yesterday
		$time = dol_get_prev_day($day, $month, $year);
		$yestini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $time['day'], 0, 0, 0);
		$yestfin = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $time['day'], 23, 59, 59);
		$sql .= " AND f.datec BETWEEN '" . $yestini . "' AND '" . $yestfin . "' ) as yesterday, ";

		$sql .= '(SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $user->id;
		} // We need this table joined to the select in order to filter by sale
		$sql .= ', ' . MAIN_DB_PREFIX . 'pos_facture as pf';
		$sql .= ' WHERE f.entity = ' . $conf->entity;
		$sql .= ' AND pf.fk_facture = f.rowid';

		$sql .= $sql2;

		//This week
		$time = dol_get_first_day_week($day, $month, $year);
		$weekini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $time['first_day'], 0, 0, 0);
		$weekfin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
		$sql .= " AND f.datec BETWEEN '" . $weekini . "' AND '" . $weekfin . "' ) as thisweek, ";

		$sql .= '(SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $user->id;
		} // We need this table joined to the select in order to filter by sale
		$sql .= ', ' . MAIN_DB_PREFIX . 'pos_facture as pf';
		$sql .= ' WHERE f.entity = ' . $conf->entity;
		$sql .= ' AND pf.fk_facture = f.rowid';

		$sql .= $sql2;

		//Last week
		$time = dol_get_first_day_week($day, $month, $year);
		$lweekini = sprintf("%04d%02d%02d%02d%02d%02d", $time['prev_year'], $time['prev_month'], $time['prev_day'], 0,
				0, 0);
		$lweekfin = sprintf("%04d%02d%02d%02d%02d%02d", $year,
				$time['first_day'] - 1 == 0 ? $time['prev_month'] : $month,
				$time['first_day'] - 1 == 0 ? $time['prev_day'] + 6 : $time['first_day'] - 1, 23, 59, 59);
		$sql .= " AND f.datec BETWEEN '" . $lweekini . "' AND '" . $lweekfin . "' ) as lastweek, ";

		$sql .= '(SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $user->id;
		} // We need this table joined to the select in order to filter by sale
		$sql .= ', ' . MAIN_DB_PREFIX . 'pos_facture as pf';
		$sql .= ' WHERE f.entity = ' . $conf->entity;
		$sql .= ' AND pf.fk_facture = f.rowid';

		$sql .= $sql2;

		//Two weeks ago
		$time = dol_get_prev_week($day, '', $month, $year);
		$time2 = dol_get_prev_week($time['day'], '', $time['month'], $time['year']);
		$ini2week = sprintf("%04d%02d%02d%02d%02d%02d", $time2['year'], $time2['month'], $time2['day'], 0, 0, 0);
		$fin2week = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'],
				$time['day'] - 1 == 0 ? $time2['month'] : $time['month'],
				$time['day'] - 1 == 0 ? $time2['day'] + 6 : $time['day'] - 1, 23, 59, 59);
		$sql .= " AND f.datec BETWEEN '" . $ini2week . "' AND '" . $fin2week . "' ) as twoweek, ";

		$sql .= '(SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $user->id;
		} // We need this table joined to the select in order to filter by sale
		$sql .= ', ' . MAIN_DB_PREFIX . 'pos_facture as pf';
		$sql .= ' WHERE f.entity = ' . $conf->entity;
		$sql .= ' AND pf.fk_facture = f.rowid';

		$sql .= $sql2;

		//Three weeks ago
		$time = dol_get_prev_week($day, '', $month, $year);
		$time = dol_get_prev_week($time['day'], '', $time['month'], $time['year']);
		$time2 = dol_get_prev_week($time['day'], '', $time['month'], $time['year']);
		$ini3week = sprintf("%04d%02d%02d%02d%02d%02d", $time2['year'], $time2['month'], $time2['day'], 0, 0, 0);
		$fin3week = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'],
				$time['day'] - 1 == 0 ? $time2['month'] : $time['month'],
				$time['day'] - 1 == 0 ? $time2['day'] + 6 : $time['day'] - 1, 23, 59, 59);
		$sql .= " AND f.datec BETWEEN '" . $ini3week . "' AND '" . $fin3week . "' ) as threeweek, ";

		$sql .= '(SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $user->id;
		} // We need this table joined to the select in order to filter by sale
		$sql .= ', ' . MAIN_DB_PREFIX . 'pos_facture as pf';
		$sql .= ' WHERE f.entity = ' . $conf->entity;
		$sql .= ' AND pf.fk_facture = f.rowid';

		$sql .= $sql2;

		//This month
		$monthini = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, 01, 0, 0, 0);
		$monthfin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
		$sql .= " AND f.datec BETWEEN '" . $monthini . "' AND '" . $monthfin . "' ) as thismonth, ";

		$sql .= '(SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $user->id;
		} // We need this table joined to the select in order to filter by sale
		$sql .= ', ' . MAIN_DB_PREFIX . 'pos_facture as pf';
		$sql .= ' WHERE f.entity = ' . $conf->entity;
		$sql .= ' AND pf.fk_facture = f.rowid';

		$sql .= $sql2;

		//One month ago
		$time = dol_get_prev_month($month, $year);
		$monthagoini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], $day, 0, 0, 0);
		$monthagofin = sprintf("%04d%02d%02d%02d%02d%02d", $year, $month, $day, 23, 59, 59);
		$sql .= " AND f.datec BETWEEN '" . $monthagoini . "' AND '" . $monthagofin . "' ) as monthago, ";

		$sql .= '(SELECT COUNT(f.rowid)';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture as f';
		if (!$user->rights->societe->client->voir && !$user->socid) {
			$sql .= " RIGHT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON f.fk_soc = sc.fk_soc AND sc.fk_user = " . $user->id;
		} // We need this table joined to the select in order to filter by sale
		$sql .= ', ' . MAIN_DB_PREFIX . 'pos_facture as pf';
		$sql .= ' WHERE f.entity = ' . $conf->entity;
		$sql .= ' AND pf.fk_facture = f.rowid';

		$sql .= $sql2;

		//Last month
		$time = dol_get_prev_month($month, $year);
		$lmonthini = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], 01, 0, 0, 0);
		$lmonthfin = sprintf("%04d%02d%02d%02d%02d%02d", $time['year'], $time['month'], 31, 0, 0, 0);
		$sql .= " AND f.datec BETWEEN '" . $lmonthini . "' AND '" . $lmonthfin . "' ) as lastmonth";

		$res = $db->query($sql);

		if ($res) {
			$obj = $db->fetch_object($res);

			$result["today"] = $obj->today;
			$result["yesterday"] = $obj->yesterday;
			$result["thisweek"] = $obj->thisweek;
			$result["lastweek"] = $obj->lastweek;
			$result["twoweek"] = $obj->twoweek;
			$result["threeweek"] = $obj->threeweek;
			$result["thismonth"] = $obj->thismonth;
			$result["monthago"] = $obj->monthago;
			$result["lastmonth"] = $obj->lastmonth;

			return ErrorControl($result, $function);
		} else {
			return ErrorControl($ret, $function);
		}

	}

    /**
     *
     * Add ticket Payment
     *
     * @param array $aryTicket Ticket data array
     * @return int
     */
	private  function addPayment($aryTicket)
	{
		global $db, $langs;

		require_once(DOL_DOCUMENT_ROOT . '/compta/paiement/class/paiement.class.php');
		$now = dol_now();
		$userstatic = new User($db);
		if (!$aryTicket['employeeId']) {
			$employee = $_SESSION['uid'];

		} else {
			$employee = $aryTicket['employeeId'];
		}
		$userstatic->fetch($employee);

		if ($aryTicket['type'] == 1) {
			$aryTicket['total_ttc'] = $aryTicket['total_ttc'] * -1;
			$aryTicket['customerpay1'] = $aryTicket['customerpay1'] * -1;
			$aryTicket['customerpay2'] = $aryTicket['customerpay2'] * -1;
			$aryTicket['customerpay3'] = $aryTicket['customerpay3'] * -1;
		}

		$cash = new Cash($db);

		$terminal = $_SESSION['TERMINAL_ID'];
		$cash->fetch($terminal);

		if ($aryTicket['customerpay1'] != 0) {
			$bankaccountid[1] = $cash->fk_paycash;
			$modepay[1] = $cash->fk_modepaycash;
			$amount[1] = $aryTicket['customerpay1'] + ($aryTicket['difpayment'] < 0 ? $aryTicket['difpayment'] : 0);
		}
		if ($aryTicket['customerpay2'] != 0) {
			$bankaccountid[2] = $cash->fk_paybank;
			$modepay[2] = $cash->fk_modepaybank;
			$amount[2] = $aryTicket['customerpay2'];
		}
		if ($aryTicket['customerpay3'] != 0) {
			$bankaccountid[3] = $cash->fk_paybank_extra;
			$modepay[3] = $cash->fk_modepaybank_extra;
			$amount[3] = $aryTicket['customerpay3'];
		}

		$i = 1;

		$payment = new Payment($db);
		$error = 0;
		while ($i <= 3) {
			$payment->datepaye = $now;
			$payment->bank_account = $bankaccountid[$i];
			$payment->amounts[$aryTicket['id']] = $amount[$i];
			$payment->note = $langs->trans("Payment") . ' ' . $langs->trans("Ticket") . ' ' . $aryTicket['ref'];
			$payment->paiementid = $modepay[$i];
			$payment->num_paiement = '';

			if ($amount[$i] != 0) {
				$paiement_id = $payment->create($userstatic);
				if ($paiement_id > 0) {
					$result = $payment->addPaymentToBank($userstatic, 'payment', '(CustomerFacturePayment)',
							$bankaccountid[$i], $aryTicket['customerId'], '', '');
					if (!$result > 0) {
						$error++;
					}
				} else {
					$error++;
				}
			}
			$i++;
		}
		if ($error) {
			return -1;
		} else {
			return $paiement_id;
		}
	}

	/**
	 *
	 * Add facture Payment
	 *
	 * @param array $aryTicket Ticket data array
	 */
	private  function addPaymentFac($facture, $cash, $employ, $aryTicket)
	{
		
		dol_syslog(get_class($facture) . "::Add Facture Payments", LOG_DEBUG);
		
		global $db, $langs, $conf, $user;
		//$aryTicket['total_ttc'] = $facture->total_ttc;
		require_once(DOL_DOCUMENT_ROOT . '/compta/paiement/class/paiement.class.php');
		$now = dol_now();
		$error = 0;
		$max_ite = 3;
		
		if ($aryTicket['convertDis']) {
			dol_syslog("Convertir a Descuento futuro", LOG_DEBUG);
			require_once DOL_DOCUMENT_ROOT . '/core/class/discount.class.php';
			//$facture->fetch_thirdparty();
			// Check if there is already a discount (protection to avoid duplicate creation when resubmit post)
			$discountcheck = new DiscountAbsolute($db);
			$result = $discountcheck->fetch(0, $facture->id);

			$canconvert = 0;

            if ($facture->type == Facture::TYPE_DEPOSIT && $facture->paye == 1 && empty($discountcheck->id)) {
                $canconvert = 1;    // we can convert deposit into discount if deposit is payed completely and not already converted (see real condition into condition used to show button converttoreduc)
            }
            if ($facture->type == Facture::TYPE_CREDIT_NOTE && $facture->paye == 0 && empty($discountcheck->id)) {
				$canconvert = 1;
			}    // we can convert credit note into discount if credit note is not payed back and not already converted and amount of payment is 0 (see real condition into condition used to show button converttoreduc)
			if ($canconvert) {
				// Boucle sur chaque taux de tva
				$i = 0;
				$amount_ht = array();
				$amount_tva = array();
				$amount_ttc = array();
				foreach ($facture->lines as $line) {
					$amount_ht [$line->tva_tx] += $line->total_ht;
					$amount_tva [$line->tva_tx] += $line->total_tva;
					$amount_ttc [$line->tva_tx] += $line->total_ttc;
					$i++;
				}

				// Insert one discount by VAT rate category
				$discount = new DiscountAbsolute($db);
				if ($facture->type == Facture::TYPE_CREDIT_NOTE)
                    $discount->description = '(CREDIT_NOTE)';
                elseif ($facture->type == Facture::TYPE_DEPOSIT)
                    $discount->description = '(DEPOSIT)';

				$discount->fk_soc = $facture->socid;
				$discount->fk_facture_source = $facture->id;

				$error = 0;
				foreach ($amount_ht as $tva_tx => $xxx) {
					$discount->amount_ht = abs($amount_ht [$tva_tx]);
					$discount->amount_tva = abs($amount_tva [$tva_tx]);
					$discount->amount_ttc = abs($amount_ttc [$tva_tx]);
					$discount->tva_tx = abs($tva_tx);

					$paiement_id = $discount->create($employ);
					if ($paiement_id < 0) {
						$error++;
						break;
					} else {
						if (self::createPosDiscount($paiement_id, $cash->id, $employ->id) <0){
							$error++;
							break;		
						}
					}
				}
				if (!$error) {
					$facture->set_paid($employ);
					$facture->statut = Facture::STATUS_CLOSED;
				}

			}
		} else {
			if ($aryTicket['type'] == 1) {
				if (($aryTicket['total_ttc'] > $aryTicket['customerpay']) && ($aryTicket['difpayment'] == 0)) {
					if ($conf->global->REWARDS_POS && !empty($conf->rewards->enabled)) {
						dol_include_once('/rewards/class/rewards.class.php');
						$reward = new Rewards($db);
						$modepay[4] = dol_getIdFromCode($db, 'PNT', 'c_paiement');
						$amount[4] = ($aryTicket['total_ttc'] - $aryTicket['customerpay']);
						$result = $reward->create($facture, (price2num($amount[4]) / $conf->global->REWARDS_DISCOUNT));
						$max_ite++;
						$amount[4] = $amount[4] * -1;
					} else {
						return -1;
					}	
					//TODO tot molt bonico, pero que pasa si no gaste punts?
				}
				$aryTicket['total_ttc'] = $aryTicket['total_ttc'] * -1;
				$aryTicket['customerpay1'] = $aryTicket['customerpay1'] * -1;
				$aryTicket['customerpay2'] = $aryTicket['customerpay2'] * -1;
				$aryTicket['customerpay3'] = $aryTicket['customerpay3'] * -1;
			}
			if ($aryTicket['customerpay1'] != 0) {
				$bankaccountid[1] = $cash->fk_paycash;
				$modepay[1] = $cash->fk_modepaycash;
				$amount[1] = $aryTicket['customerpay1'] + ($aryTicket['difpayment'] < 0 ? $aryTicket['difpayment'] : 0);
			}
			if ($aryTicket['customerpay2'] != 0) {
				$bankaccountid[2] = $cash->fk_paybank;
				$modepay[2] = $cash->fk_modepaybank;
				$amount[2] = $aryTicket['customerpay2'];
			}
			if ($aryTicket['customerpay3'] != 0) {
				$bankaccountid[3] = $cash->fk_paybank_extra;
				$modepay[3] = $cash->fk_modepaybank_extra;
				$amount[3] = $aryTicket['customerpay3'];
			}
			//Añadir el posible pago de puntos
			if ($aryTicket['points'] > 0) {
				if ($conf->global->REWARDS_POS && !empty($conf->rewards->enabled)) {
					dol_include_once('/rewards/class/rewards.class.php');
					$reward = new Rewards($db);
					$res = $reward->usePoints($facture, $aryTicket['points']);
				} else {
					return -1;
				}
			}
			$i = 1;
			while ($i <= $max_ite) {
				
				if ($amount[$i] != 0) {
					dol_syslog(get_class($facture) . "::Payment ammount ".$amount[$i]." to ".$bankaccountid[$i]." mode ".$modepay[$i], LOG_DEBUG);		
					$payment = new Paiement($db);
					$payment->datepaye = $now;
					$payment->bank_account = $bankaccountid[$i];
					$payment->amounts[$facture->id] = $amount[$i];
					$payment->note = $langs->trans("Payment") . ' ' . $langs->trans("Invoice") . ' ' . $aryTicket['ref'];
					$payment->paiementid = $modepay[$i];
					$payment->num_paiement = '';
					$paiement_id = $payment->create($employ, 1);
					if ($paiement_id > 0) {
						if ($payment->paiementid != dol_getIdFromCode($db, 'PNT', 'c_paiement')) {
							$result = $payment->addPaymentToBank($userstatic, 'payment', '(CustomerFacturePayment)',
									$bankaccountid[$i], $aryTicket['customerId'], '', '');
							if ($result < 0) {
								$error++;
							}
						}
					} else {
						$error++;
					}
				}
				$i++;
			}
		}
		if ($error > 0) {
			return -1;
		} else {
			return 1;
		}//$paiement_id;
	}

	private  function quitSotck($object, $cash, $user, $lines, $isreturn = false)
	{
		global $db, $langs;
		require_once(DOL_DOCUMENT_ROOT . "/product/stock/class/mouvementstock.class.php");
		$error = 0;
		$warehouse = $cash->fk_warehouse;

		foreach ($lines as $line) {
			if (count($line) > 0) {
				if ($line['idProduct']) {
					$mouvP = new MouvementStock($db);
					// We decrease stock for product
                    if ($object->element == 'facture'){
                        $mouvP->origin = new Facture($db);
                        $mouvP->origin->id = $object->id;
                    }
					if (!$isreturn) {
						$result = $mouvP->livraison($user, $line['idProduct'], $warehouse, $line['qty'],
								$line['price_ht'], $langs->trans("TicketCreatedInDolibarr"));
					} else {
						$result = $mouvP->reception($user, $line['idProduct'], $warehouse, $line['qty'],
								$line['price_ht'], $langs->trans("TicketCreatedInDolibarr"));
					}
					if ($result < 0) {
						$error++;
					}
				}
			}
		}
		return $error;
	}

	/**
	 *
	 * Get user POS
	 *
	 * @return    array    User and terminal name
	 */

	public  function getLogin()
	{
		global $db, $langs;

		$error = 0;
		$function = "getLogin";

		$userstatic = new User($db);
		if ($userstatic->fetch($_SESSION['uid']) != 0) {
			$error++;
		}

		$cash = new Cash($db);
		$terminal = $_SESSION['TERMINAL_ID'];
		if ($cash->fetch($terminal) < 0) {
			$error++;
		}

		if (!$error) {
			$ret['User'] = $userstatic->getFullName($langs);
			$ret['Terminal'] = $cash->name;
			return ErrorControl($ret, $function);
		} else {
			$error = $error * -1;
			return ErrorControl($error, $function);
		}
	}

	/**
	 *
	 * Create Customer into DB
	 *
	 * @param        array $aryCustomer Customer object
	 * @return        array    $result        Result
	 */
	public  function SetCustomer($aryCustomer)
	{
		require_once(DOL_DOCUMENT_ROOT . "/societe/class/societe.class.php");

		global $conf, $db, $user, $mysoc, $langs;
		$function = "SetCustomer";

		$object = new Societe($db);

		if ($aryCustomer["idprof1"] && $object->id_prof_verifiable(1)) {
			if ($object->id_prof_exists(1, $aryCustomer["idprof1"])) {
				$res = -5;
				return ErrorControl($res, $function);
			}
		}

		if (!$aryCustomer["idprof1"] && !empty($conf->global->SOCIETE_IDPROF1_MANDATORY)) {
			$res = -6;
			return ErrorControl($res, $function);
		}


		//We use code creation
		$module = $conf->global->SOCIETE_CODECLIENT_ADDON;

		if (substr($module, 0, 15) == 'mod_codeclient_' && substr($module, -3) == 'php') {
			$module = substr($module, 0, dol_strlen($module) - 4);
		}
		require_once(DOL_DOCUMENT_ROOT . "/core/modules/societe/" . $module . ".php");
		$modCodeClient = new $module;

		$object->particulier = 1;
		$object->typent_id = 8; // TODO predict another method if the field "special" change of rowid
		$object->client = 1;
		$object->fournisseur = 0;
		$object->tva_assuj = 1;
		$object->status = 1;
		$object->country_id = $mysoc->country_id;

		$object->name = $conf->global->MAIN_FIRSTNAME_NAME_POSITION ? trim($aryCustomer['prenom'] . ' ' . $aryCustomer["nom"]) : trim($aryCustomer["nom"] . ' ' . $aryCustomer["prenom"]);
		$object->idprof1 = $aryCustomer["idprof1"];
		$object->address = $aryCustomer["address"];
		$object->town = $aryCustomer["town"];
		$object->zip = $aryCustomer["zip"];
		$object->phone = $aryCustomer["tel"];
		$object->email = $aryCustomer["email"];
		if ($modCodeClient->code_auto) {
			$tmpcode = $modCodeClient->getNextValue($object, 0);
		}
		$object->code_client = $tmpcode;
		$res = $object->create($user);
		if ($res > 0) {
			$user = new User($db);
			$user->fetch($_SESSION['uid']);
			if (isset($aryCustomer["discount"]) && $aryCustomer["discount"] > 0) {
				$object->set_remise_client($aryCustomer["discount"], $langs->trans("Client discount via POS"),$user);
			}
		}
		//Si opción de configuración, asignar como comercial el usuario activo.
		if ($conf->global->POS_COMERCIAL) {
			$object->add_commercial($user, $user->id);

			if ($conf->global->POS_USER_TERMINAL) {
				$sql = "SELECT u.rowid as id";
				$sql .= " FROM " . MAIN_DB_PREFIX . "user as u";
				$sql .= " , " . MAIN_DB_PREFIX . "pos_users as pu";
				$sql .= " WHERE pu.fk_terminal =" . $_SESSION["TERMINAL_ID"];
				$sql .= " AND pu.fk_object = u.rowid";
				$sql .= " AND objtype = 'user'";

				$sql .= " UNION SELECT DISTINCT v.fk_user as id";
				$sql .= " FROM " . MAIN_DB_PREFIX . "usergroup as ug";
				$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "usergroup_user as v ON v.fk_usergroup = ug.rowid";
				$sql .= " , " . MAIN_DB_PREFIX . "pos_users as pu";
				$sql .= " WHERE pu.fk_terminal =" . $_SESSION["TERMINAL_ID"];
				$sql .= " AND pu.fk_object = ug.rowid";
				$sql .= " AND objtype = 'group'";
				$resql = $db->query($sql);
				if ($resql) {
					$num = $db->num_rows($resql);
					$i = 0;
					while ($i < $num) {
						$obj = $db->fetch_object($resql);
						$object->add_commercial($user, $obj->id);
						$i++;
					}
					$db->free($resql);
				} else {
					dol_print_error($db);
				}
			}
		}
		return ErrorControl($res, $function);

	}

	/**
	 *
	 * Create product into DB
	 *
	 * @param        array $aryProduct Product object
	 * @return        array    $result        Result
	 */
	public  function SetProduct($aryProduct)
	{
		require_once(DOL_DOCUMENT_ROOT . "/product/class/product.class.php");

		global $db, $mysoc;

		$code_pays = "'" . $mysoc->country_code . "'";

		$function = "SetProduct";

		$sql = "SELECT DISTINCT t.taux";
		$sql .= " FROM " . MAIN_DB_PREFIX . "c_tva as t, " . MAIN_DB_PREFIX . "c_country as p";
		$sql .= " WHERE t.fk_pays = p.rowid";
		$sql .= " AND t.active = 1";
		$sql .= " AND t.rowid = " . $aryProduct['tax'];
		$sql .= " AND p.code in (" . $code_pays . ")";
		$sql .= " ORDER BY t.taux DESC";

		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);
			if ($num) {
				for ($i = 0; $i < $num; $i++) {
					$obj = $db->fetch_object($resql);

				}
			}
		}

		$myproduct = new Product($db);

		$myproduct->ref = $aryProduct['ref'];
		$myproduct->libelle = $aryProduct['label'];
		$myproduct->label = $aryProduct['label'];
		$myproduct->price_ttc = $aryProduct['price_ttc'];
		$myproduct->price_base_type = 'TTC';
		$myproduct->tva_tx = $obj->taux;
		$myproduct->type = 0;
		$myproduct->status = 1;

		$userstatic = new User($db);
		$userstatic->fetch($_SESSION['uid']);

		$res = $myproduct->create($userstatic);

		return ErrorControl($res, $function);
	}

	/**
	 *
	 * Return the VAT list
	 *
	 * @return        array        Applicable VAT
	 */
	public  function select_VAT()
	{
		global $db, $mysoc;

		$code_pays = "'" . $mysoc->country_code . "'";

		$sql = "SELECT DISTINCT t.rowid, t.taux";
		$sql .= " FROM " . MAIN_DB_PREFIX . "c_tva as t, " . MAIN_DB_PREFIX . "c_country as p";
		$sql .= " WHERE t.fk_pays = p.rowid";
		$sql .= " AND t.active = 1";
		$sql .= " AND p.code in (" . $code_pays . ")";
		$sql .= " ORDER BY t.taux DESC";

		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);
			if ($num) {
				for ($i = 0; $i < $num; $i++) {
					$obj = $db->fetch_object($resql);
					$vat[$i]['id'] = $obj->rowid;
					$vat[$i]['label'] = $obj->taux . '%';
				}
			}
		}

		return $vat;
	}

	/**
	 *
	 * Return the money in cash
	 *
	 * @return        array        Applicable VAT
	 */
	public  function getMoneyCash($open = false)
	{
		global $db;

		$terminal = $_SESSION['TERMINAL_ID'];

		$cash = new ControlCash($db, $terminal);

		return $cash->getMoneyCash($open);

	}

	/**
	 *
	 * Enter description here ...
	 * @param $aryClose
	 */
	public  function setControlCash($aryClose)
	{
		global $db;

		$function = "closeCash";
		$error = 0;

		$terminalid = $_SESSION['TERMINAL_ID'];
		$userpos = new User($db);
		$userpos->fetch($aryClose['employeeId']);
		$userpos->getrights('pos');
		if ($userpos->rights->pos->closecash || !$aryClose['type']) {


			$cash = new ControlCash($db, $terminalid);

			$data['userid'] = $aryClose['employeeId'];
			$data['amount_reel'] = $aryClose['moneyincash'];
			$data['amount_teoric'] = $cash->getMoneyCash();
			$data['amount_diff'] = $data['amount_reel'] - $data['amount_teoric'];
			$data['type_control'] = $aryClose['type'];
			$data['print'] = $aryClose['print'];

			$res = $cash->create($data);

			if ($res > 0) {
				$terminal = new Cash($db);
				$userstatic = new User($db);
				$userstatic->fetch($data['userid']);
				$terminal->fetch($terminalid);

				if ($aryClose['type'] == 1) {
					if (!$terminal->set_used($userstatic)) {
						$error++;
					}
				} elseif ($aryClose['type'] == 2) {
					if (!$terminal->set_unused($userstatic)) {
						$error++;
					}
				}
			} else {
				$error++;
			}
		} else {
			$error = 2;
		}

		if ($error == 0) {
			$error = $res;
		} else {
			$error = $error * -1;
		}

		return ErrorControl($error, $function);

	}

	/**
	 *
	 * Return POS Config
	 *
	 * @return    array        Array with config
	 */
	public  function getConfig()
	{
		global $db, $conf, $langs;

		$cash = new Cash($db);

		$terminal = $_SESSION['TERMINAL_ID'];
		$cash->fetch($terminal);

		$userstatic = new User($db);
		$userstatic->fetch($_SESSION['uid']);

		$soc = new Societe($db, $cash->fk_soc);
		$soc->fetch($cash->fk_soc);
		$name = $soc->name ? $soc->name : $soc->nom;
		$ret['error']['value'] = 0;
		$ret['error']['desc'] = '';
		$ret['data']['terminal']['id'] = $cash->id;
		$ret['data']['terminal']['name'] = $cash->name;
		$ret['data']['terminal']['tactil'] = $cash->tactil;
		$ret['data']['terminal']['warehouse'] = $cash->fk_warehouse;
		$ret['data']['terminal']['barcode'] = $cash->barcode;
		$ret['data']['terminal']['mode_info'] = 0;
		$ret['data']['terminal']['faclimit'] = $conf->global->POS_MAX_TTC;
		$ret['data']['module']['places'] = $conf->global->POS_PLACES;
		$ret['data']['module']['print'] = $conf->global->POS_PRINT;
		$ret['data']['module']['mail'] = $conf->global->POS_MAIL;
		$ret['data']['module']['points'] = $conf->global->REWARDS_DISCOUNT;
		$ret['data']['module']['ticket'] = $conf->global->POS_TICKET;
		$ret['data']['module']['facture'] = $conf->global->POS_FACTURE;
		$ret['data']['module']['print_mode'] = $conf->global->POS_PRINT_MODE;
		$ret['data']['module']['credit_sell'] = $conf->global->POS_SELL_CREDIT;
		$ret['data']['module']['pos_stock'] = $conf->global->POS_STOCK;
		$ret['data']['module']['series']= 0;
		if ($conf->global->FACTURE_ADDON == 'mod_facture_numberseries' && $conf->numberseries->enabled && $conf->global->NUMBERSERIES_POS) {
            $ret['data']['module']['series'] = 1;
        }
		$ret['data']['user']['id'] = $userstatic->id;
		$ret['data']['user']['name'] = $userstatic->getFullName($langs);
		$dir = $conf->user->dir_output;
		if ($userstatic->photo) {
			$file = get_exdir($userstatic->id, 2, 0, 1, $userstatic, 'user') . "/" . $userstatic->photo;
		}
		if ($file && file_exists($dir . "/" . $file)) {
			$ret['data']['user']['photo'] = DOL_URL_ROOT . '/viewimage.php?modulepart=userphoto&entity=' . $userstatic->entity . '&file=' . urlencode($file);
		} else {
			if (version_compare(DOL_VERSION, 3.8) >= 0) {
				if ($userstatic->gender == "woman") {

					$ret['data']['user']['photo'] = DOL_URL_ROOT . '/public/theme/common/user_woman.png';
				} else {

					$ret['data']['user']['photo'] = DOL_URL_ROOT . '/public/theme/common/user_man.png';
				}
			} else {

				$ret['data']['user']['photo'] = DOL_URL_ROOT . '/theme/common/nophoto.jpg';
			}
		}
		$ret['data']['customer']['id'] = $soc->id;
		$ret['data']['customer']['name'] = $name;
		$ret['data']['customer']['remise'] = $soc->remise_percent;
		//$ret['data']['customer']['coupon'] = $soc->getAvailableDiscounts();
		$ret['data']['customer']['points'] = null;
		if ($soc->tva_assuj){
			$use_product_vat = true;
			$ret['data']['customer']["uselocaltax1_rate"]=$soc->localtax1_assuj;
			$ret['data']['customer']["uselocaltax1_rate"]=$soc->localtax2_assuj;
		}
		if ($conf->global->REWARDS_POS && !empty($conf->rewards->enabled)) {
			$rew = new Rewards($db);
			$res = $rew->getCustomerReward($soc->id);
			if ($res) {
				$ret['data']['customer']['points'] = $rew->getCustomerPoints($soc->id);
			}
		}
		$ret['data']['decrange']['unit'] = $conf->global->MAIN_MAX_DECIMALS_UNIT;
		$ret['data']['decrange']['tot'] = $conf->global->MAIN_MAX_DECIMALS_TOT;
		$ret['data']['decrange']['maxshow'] = $conf->global->MAIN_MAX_DECIMALS_SHOWN;
		return $ret;
	}

	public  function testSource($aryTicket)
	{
		global $db;

		$data = $aryTicket['data'];
		$lines = $data['lines'];

		//Compare
		$i = 0;
		foreach ($lines as $line) {
			if (count($line) > 0) {
				if ($line['idProduct'] > 0) {
					//Returned products for Source ticket
					$sql = "SELECT td.qty from " . MAIN_DB_PREFIX . "pos_ticketdet td";
					$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "pos_ticket t";
					$sql .= " WHERE td.fk_ticket = t.rowid";
					$sql .= " AND t.rowid= " . $data['idsource'];
					$sql .= " AND td.fk_product = " . $line['idProduct'];

					$resql = $db->query($sql);

					if ($resql) {
						//Compare quantity returned
						if ($db->num_rows($resql)) {
							$obj = $db->fetch_object($resql);

							$vendidas = $obj->qty;

						}
					}

					//Returned products for Source ticket
					$sql = "SELECT sum(td.qty) as qty from " . MAIN_DB_PREFIX . "pos_ticketdet td";
					$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "pos_ticket t";
					$sql .= " WHERE td.fk_ticket = t.rowid";
					$sql .= " AND t.fk_ticket_source= " . $data['idsource'];
					$sql .= " AND td.fk_product = " . $line['idProduct'];

					$resql = $db->query($sql);

					if ($resql) {
						//Compare quantity returned
						if ($db->num_rows($resql)) {
							$obj = $db->fetch_object($resql);
							if ($vendidas - abs($obj->qty) < $line['cant']) {
								$prods_returns[$i] = $line['idProduct'];
								$i++;
							}
						}
					}
				}
			}
		}


		return $prods_returns;
	}

	public  function testSourceFac($aryTicket)
	{
		global $db;

		$data = $aryTicket['data'];
		$lines = $data['lines'];

		//Compare
		$i = 0;
		foreach ($lines as $line) {
			if (count($line) > 0) {
				if ($line['idProduct'] > 0) {
					$sql = "SELECT fd.qty from " . MAIN_DB_PREFIX . "facturedet fd";
					$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "facture f";
					$sql .= " WHERE fd.fk_facture = f.rowid";
					$sql .= " AND f.rowid= " . $data['idsource'];
					$sql .= " AND fd.fk_product = " . $line['idProduct'];

					$resql = $db->query($sql);
					if ($resql) {

						//Compare quantity returned
						if ($db->num_rows($resql)) {
							$obj = $db->fetch_object($resql);

							$vendidas = $obj->qty;

						}
					}
					//Returned products for Source ticket
					$sql = "SELECT sum(fd.qty) as qty from " . MAIN_DB_PREFIX . "facturedet fd";
					$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "facture f";
					$sql .= " WHERE fd.fk_facture = f.rowid";
					$sql .= " AND f.fk_facture_source= " . $data['idsource'];
					$sql .= " AND fd.fk_product = " . $line['idProduct'];

					$resql = $db->query($sql);

					if ($resql) {
						//Compare quantity returned
						if ($db->num_rows($resql)) {
							$obj = $db->fetch_object($resql);
							if ($vendidas - abs($obj->qty) < $line['cant']) {
								$prods_returns[$i] = $line['idProduct'];
								$i++;
							}
						}
					}

				}
			}
		}


		return $prods_returns;
	}

	/**
	 * Return the places of the company
	 *
	 * @return array        return <0 if KO; array of places
	 */
	public  function getPlaces()
	{
		global $db, $conf;

		$sql = 'SELECT rowid,';
		$sql .= 'name, ';
		$sql .= 'description, ';
		$sql .= 'status, ';
		$sql .= 'fk_ticket ';
		$sql .= 'From ' . MAIN_DB_PREFIX . 'pos_places p';
		$sql .= ' WHERE p.entity =' . $conf->entity;

		$resql = $db->query($sql);

		if ($resql) {
			$places = array();
			$num = $db->num_rows($resql);
			$i = 0;

			while ($i < $num) {
				$obj = $db->fetch_object($resql);

				$places[$i]["id"] = $obj->rowid;
				$places[$i]["name"] = $obj->name;
				$places[$i]["description"] = $obj->description;
				$places[$i]["fk_ticket"] = $obj->fk_ticket;
				$places[$i]["status"] = $obj->status;

				$i++;
			}
		}
		return $places;
	}

	/**
	 * Fill the body of email's message with a ticket
	 *
	 * @param int $id
	 *
	 * @return string        String with ticket data
	 */
	public  function fillMailTicketBody($id)
	{
		global $db, $conf, $langs, $mysoc;

		$ticket = new Ticket($db);
		$res = $ticket->fetch($id);
		$mysoc = new Societe($db);
		$mysoc->fetch($ticket->socid);
		$userstatic = new User($db);
		$userstatic->fetch($ticket->user_close);

		$label = $ticket->ref;
		$facture = new Facture($db);
		if ($ticket->fk_facture) {
			$facture->fetch($ticket->fk_facture);
			$label = $facture->ref;
		}

		$message = $conf->global->MAIN_INFO_SOCIETE_NOM . " \n" . $conf->global->MAIN_INFO_SOCIETE_ADRESSE . " \n" . $conf->global->MAIN_INFO_SOCIETE_CP . ' ' . $conf->global->MAIN_INFO_SOCIETE_VILLE . " \n\n";

		$message .= $label . " \n" . dol_print_date($ticket->date_closed, 'dayhourtext') . " \n";
		$message .= $langs->transnoentities("Vendor") . ': ' . $userstatic->firstname . " " . $userstatic->lastname . "\n";
		if (!empty($ticket->fk_place)) {
			$place = new Place($db);
			$place->fetch($ticket->fk_place);
			$message .= $langs->trans("Place") . ': ' . $place->name . "\n";
		}

		$message .= "\n";
		$message .= $langs->transnoentities("Label") . "\t\t\t\t\t\t\t\t\t" . $langs->transnoentities("Qty") . "/" . $langs->transnoentities("Price") . "\t\t" ./*$langs->transnoentities("DiscountLineal")."\t\t".*/
				$langs->transnoentities("Total") . "\n";
		//$ticket->getLinesArray();
		if (!empty($ticket->lines)) {
			//$subtotal=0;
			foreach ($ticket->lines as $line) {
				$espacio = '';
				$totalline = $line->qty * $line->subprice;
				$subtotal = array();
				$subtotaltva = array();
				while (dol_strlen(dol_trunc($line->libelle, 30) . $espacio) < 29) {
					$espacio .= "    \t";
				}
				$message .= dol_trunc($line->libelle, 33) . $espacio;
				$message .= "\t\t" . $line->qty . " * " . price($line->total_ttc / $line->qty) . "\t\t" ./*$line->remise_percent."%\t\t\t".*/
						price($line->total_ttc) . ' ' . $langs->trans(currency_name($conf->currency)) . "\n";
				$subtotal[$line->tva_tx] += $line->total_ht;;
				$subtotaltva[$line->tva_tx] += $line->total_tva;
				if (!empty($line->total_localtax1)) {
					$localtax1 = $line->localtax1_tx;
				}
				if (!empty($line->total_localtax2)) {
					$localtax2 = $line->localtax2_tx;
				}
			}
		} else {
			$message .= $langs->transnoentities("ErrNoArticles") . "\n";
		}
		$message .= $langs->transnoentities("TotalTTC") . ":\t" . price($ticket->total_ttc) . " " . $langs->trans(currency_name($conf->currency)) . "\n";

		$message .= '\n' . $langs->trans("TotalHT") . "\t" . $langs->trans("VAT") . "\t" . $langs->trans("TotalVAT") . "\n";

		if (!empty($subtotal)) {
			foreach ($subtotal as $totkey => $totval) {
				$message .= price($subtotal[$totkey]) . "\t\t\t" . price($totkey) . "%\t" . price($subtotaltva[$totkey]) . "\n";
			}
		}
		$message .= "-------------------------------\n";
		$message .= price($ticket->total_ht) . "\t\t\t----\t" . price($ticket->total_tva) . "\n";
		if ($ticket->total_localtax1 != 0) {
			$message .= $langs->transcountrynoentities("TotalLT1",
							$mysoc->country_code) . " " . price($localtax1) . "%\t" . price($ticket->total_localtax1) . " " . $langs->trans(currency_name($conf->currency)) . "\n";
		}
		if ($ticket->total_localtax2 != 0) {
			$message .= $langs->transcountrynoentities("TotalLT2",
							$mysoc->country_code) . " " . price($localtax2) . "%\t" . price($ticket->total_localtax2) . " " . $langs->trans(currency_name($conf->currency)) . "\n";
		}

		$message .= "\n\n";

		$terminal = new Cash($db);
		$terminal->fetch($ticket->fk_cash);

		$pay = $ticket->getSommePaiement();

		if ($ticket->customer_pay > $pay) {
			$pay = $ticket->customer_pay;
		}


		$diff_payment = $ticket->total_ttc - $pay;
		$listofpayments = $ticket->getListOfPayments();
		foreach ($listofpayments as $paym) {
			if ($paym['type'] != 'LIQ') {
				$message .= $terminal->select_Paymentname(dol_getIdFromCode($db, $paym['type'],
								'c_paiement')) . "\t" . price($paym['amount']) . " " . $langs->trans(currency_name($conf->currency)) . "\n";
			} else {
				$message .= $terminal->select_Paymentname(dol_getIdFromCode($db, $paym['type'],
								'c_paiement')) . "\t" . price($paym['amount'] - ($diff_payment < 0 ? $diff_payment : 0)) . " " . $langs->trans(currency_name($conf->currency)) . "\n";
			}
		}

		$message .= ($diff_payment < 0 ? $langs->trans("CustomerRet") : $langs->trans("CustomerDeb")) . "\t" . price(abs($diff_payment)) . " " . $langs->trans(currency_name($conf->currency)) . "\n";

		$message .= $conf->global->POS_PREDEF_MSG;
		return $message;
	}

	/**
	 * Fill the body of email's message with a facture
	 *
	 * @param int $id
	 *
	 * @return string        String with ticket data
	 */
	public  function FillMailFactureBody($id)
	{
		global $db, $conf, $langs;

		$facture = new Facture($db);
		$res = $facture->fetch($id);
		$facture->fetch_thirdparty();
		$userstatic = new User($db);
		$userstatic->fetch($facture->user_valid);


		$sql = "SELECT label, topic, content, lang";
		$sql .= " FROM " . MAIN_DB_PREFIX . 'c_email_templates';
		$sql .= " WHERE type_template='facture_send'";
		$sql .= " AND entity IN (" . getEntity("c_email_templates") . ")";
		$sql .= " AND (fk_user is NULL or fk_user = 0 or fk_user = " . $userstatic->id . ")";
		$sql .= $db->order("lang,label", "ASC");
		//print $sql;

		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);    // Get first found
			if ($obj) {
				$defaultmessage = $obj->content;
			} else {
				$langs->load("other");
				$defaultmessage = $langs->transnoentities("PredefinedMailContentSendInvoice");
			}

			$db->free($resql);
		}

		$substit['__FACREF__'] = $facture->ref;
		$substit['__REF__'] = $facture->ref;
		$substit['__SIGNATURE__'] = $userstatic->signature;
		$substit['__REFCLIENT__'] = $facture->ref_client;
		$substit['__THIRPARTY_NAME__'] = $facture->thirdparty->name;
		$substit['__PROJECT_REF__'] = (is_object($facture->projet) ? $facture->projet->ref : '');
		$substit['__PERSONALIZED__'] = '';
		$substit['__CONTACTCIVNAME__'] = '';

		// Find the good contact adress
		$custcontact = '';
		$contactarr = $facture->liste_contact(-1, 'external');

		if (is_array($contactarr) && count($contactarr) > 0) {
			foreach ($contactarr as $contact) {
				if ($contact['libelle'] == $langs->trans('TypeContact_facture_external_BILLING')) {    // TODO Use code and not label

					require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';

					$contactstatic = new Contact($db);
					$contactstatic->fetch($contact ['id']);
					$custcontact = $contactstatic->getFullName($langs, 1);
				}
			}

			if (!empty($custcontact)) {
				$substit['__CONTACTCIVNAME__'] = $custcontact;
			}
		}

		// Complete substitution array
		if (!empty($conf->paypal->enabled) && !empty($conf->global->PAYPAL_ADD_PAYMENT_URL)) {
			require_once DOL_DOCUMENT_ROOT . '/paypal/lib/paypal.lib.php';

			$langs->load('paypal');

			if ($facture->param["models"] == 'facture_send') {
				$url = getPaypalPaymentUrl(0, 'invoice', $substit['__FACREF__']);
				$substit['__PERSONALIZED__'] = str_replace('\n', "\n",
						$langs->transnoentitiesnoconv("PredefinedMailContentLink", $url));
			}
		}

		$defaultmessage = str_replace('\n', "\n", $defaultmessage);

		// Deal with format differences between message and signature (text / HTML)
		if (dol_textishtml($defaultmessage) && !dol_textishtml($substit['__SIGNATURE__'])) {
			$substit['__SIGNATURE__'] = dol_nl2br($substit['__SIGNATURE__']);
		} else {
			if (!dol_textishtml($defaultmessage) && dol_textishtml($substit['__SIGNATURE__'])) {
				$defaultmessage = dol_nl2br($defaultmessage);
			}
		}

		$defaultmessage = make_substitutions($defaultmessage, $substit);
		// Clean first \n and br (to avoid empty line when CONTACTCIVNAME is empty)
		$defaultmessage = preg_replace("/^(<br>)+/", "", $defaultmessage);
		$defaultmessage = preg_replace("/^\n+/", "", $defaultmessage);

		return $defaultmessage;
	}

	/**
	 * Fill the body of email's message with a close cash
	 *
	 * @param int $id
	 *
	 * @return string        String with ticket data
	 */
	public  function FillMailCloseCashBody($id)
	{
		global $db, $conf, $langs;

		$sql = "select fk_user, date_c, fk_cash, ref";
		$sql .= " from " . MAIN_DB_PREFIX . "pos_control_cash";
		$sql .= " where rowid = " . $id;
		$result = $db->query($sql);

		if ($result) {
			$objp = $db->fetch_object($result);
			$date_end = $objp->date_c;
			$fk_user = $objp->fk_user;
			$terminal = $objp->fk_cash;
			$ref = $objp->ref;
		}

		$sql = "select date_c";
		$sql .= " from " . MAIN_DB_PREFIX . "pos_control_cash";
		$sql .= " where fk_cash = " . $terminal . " AND date_c < '" . $date_end . "' AND type_control = 1";
		$sql .= " ORDER BY date_c DESC";
		$sql .= " LIMIT 1";
		$result = $db->query($sql);

		if ($result) {
			$objd = $db->fetch_object($result);
			$date_start = $objd->date_c;
		}

		$message = $conf->global->MAIN_INFO_SOCIETE_NOM . " \n" . $conf->global->MAIN_INFO_SOCIETE_ADRESSE . " \n" . $conf->global->MAIN_INFO_SOCIETE_CP . ' ' . $conf->global->MAIN_INFO_SOCIETE_VILLE . " \n\n";
		$message .= $langs->transnoentities("CloseCashReport") . ': ' . $ref . "\n";
		$cash = new Cash($db);
		$cash->fetch($terminal);
		$message .= $langs->transnoentities("Terminal") . ': ' . $cash->name . "\n";

		$userstatic = new User($db);
		$userstatic->fetch($fk_user);
		$message .= $langs->transnoentities("User") . ': ' . $userstatic->firstname . ' ' . $userstatic->lastname . "\n";
		$message .= dol_print_date($db->jdate($date_end), 'dayhourtext') . "\n\n";

		$message .= $langs->transnoentities("TicketsCash") . "\n";
		$message .= $langs->transnoentities("Ticket") . "\t\t\t\t\t" . $langs->transnoentities("Total") . "\n";

		$sql = "SELECT t.ticketnumber, p.amount, t.type";
		$sql .= " FROM " . MAIN_DB_PREFIX . "pos_ticket as t, " . MAIN_DB_PREFIX . "pos_paiement_ticket as pt, " . MAIN_DB_PREFIX . "paiement as p";
		$sql .= " WHERE t.fk_cash=" . $terminal . " AND p.fk_paiement=" . $cash->fk_modepaycash . " AND t.fk_statut > 0 AND p.datep > '" . $date_start . "' AND p.datep < '" . $date_end . "'";
		$sql .= " AND p.rowid = pt.fk_paiement AND t.rowid = pt.fk_ticket ";

		$sql .= " UNION SELECT f.facnumber, p.amount, f.type";
		$sql .= " FROM " . MAIN_DB_PREFIX . "pos_facture as pf," . MAIN_DB_PREFIX . "facture as f, " . MAIN_DB_PREFIX . "paiement_facture as pfac, " . MAIN_DB_PREFIX . "paiement as p ";
		$sql .= " WHERE pf.fk_cash=" . $terminal . " AND p.fk_paiement=" . $cash->fk_modepaycash . " AND pf.fk_facture = f.rowid and f.fk_statut > 0 AND p.datep > '" . $date_start . "' AND p.datep < '" . $date_end . "'";
		$sql .= " AND p.rowid = pfac.fk_paiement AND f.rowid = pfac.fk_facture";

		$result = $db->query($sql);

		if ($result) {
			$num = $db->num_rows($result);
			if ($num > 0) {
				$i = 0;
				$subtotalcash = 0;
				while ($i < $num) {
					$objp = $db->fetch_object($result);

					$message .= $objp->ticketnumber . "\t\t" . price($objp->amount) . "\n";
					$i++;
					$subtotalcash += $objp->amount;
				}
			} else {
				$message .= $langs->transnoentities("NoTickets") . "\n";
			}
		}

		$message .= $langs->trans("TotalCash") . "\t" . price($subtotalcash) . " " . $langs->trans(currency_name($conf->currency)) . "\n";
		$message .= $langs->trans("TicketsCreditCard") . "\n";

		$message .= $langs->trans("Ticket") . "\t\t" . $langs->trans("Total") . "\n";

		// Credit card
		$sql = "SELECT t.ticketnumber, p.amount, t.type";
		$sql .= " FROM " . MAIN_DB_PREFIX . "pos_ticket as t, " . MAIN_DB_PREFIX . "pos_paiement_ticket as pt, " . MAIN_DB_PREFIX . "paiement as p";
		$sql .= " WHERE t.fk_cash=" . $terminal . " AND (p.fk_paiement=" . $cash->fk_modepaybank . " OR p.fk_paiement=" . $cash->fk_modepaybank_extra . ")AND t.fk_statut > 0 AND p.datep > '" . $date_start . "' AND p.datep < '" . $date_end . "'";
		$sql .= " AND p.rowid = pt.fk_paiement AND t.rowid = pt.fk_ticket ";

		$sql .= " UNION SELECT f.facnumber, p.amount, f.type";
		$sql .= " FROM " . MAIN_DB_PREFIX . "pos_facture as pf," . MAIN_DB_PREFIX . "facture as f, " . MAIN_DB_PREFIX . "paiement_facture as pfac, " . MAIN_DB_PREFIX . "paiement as p ";
		$sql .= " WHERE pf.fk_cash=" . $terminal . " AND (p.fk_paiement=" . $cash->fk_modepaybank . " OR p.fk_paiement=" . $cash->fk_modepaybank_extra . ") AND pf.fk_facture = f.rowid and f.fk_statut > 0 AND p.datep > '" . $date_start . "' AND p.datep < '" . $date_end . "'";
		$sql .= " AND p.rowid = pfac.fk_paiement AND f.rowid = pfac.fk_facture";

		$result = $db->query($sql);

		if ($result) {
			$num = $db->num_rows($result);
			if ($num > 0) {
				$i = 0;
				$subtotalcard = 0;
				while ($i < $num) {
					$objp = $db->fetch_object($result);

					$message .= $objp->ticketnumber . "\t\t" . price($objp->amount) . "\n";
					$i++;
					$subtotalcard += $objp->amount;
				}
			} else {
				$message .= $langs->transnoentities("NoTickets") . "\n";
			}
		}

		$message .= $langs->trans("TotalCard") . "\t" . price($subtotalcard) . " " . $langs->trans(currency_name($conf->currency)) . "\n";

		if (!empty($conf->rewards->enabled)) {
			$message .= $langs->trans("Points") . "\n";

			$message .= $langs->trans("Ticket");
			"\t\t" . $langs->trans("Total") . "\n";

			$sql = " SELECT f.facnumber, p.amount, f.type";
			$sql .= " FROM " . MAIN_DB_PREFIX . "pos_facture as pf," . MAIN_DB_PREFIX . "facture as f, " . MAIN_DB_PREFIX . "paiement_facture as pfac, " . MAIN_DB_PREFIX . "paiement as p ";
			$sql .= " WHERE pf.fk_cash=" . $terminal . " AND p.fk_paiement= 100 AND pf.fk_facture = f.rowid and f.fk_statut > 0 AND p.datep > '" . $date_start . "' AND p.datep < '" . $date_end . "'";
			$sql .= " AND p.rowid = pfac.fk_paiement AND f.rowid = pfac.fk_facture";

			$result = $db->query($sql);

			if ($result) {
				$num = $db->num_rows($result);
				if ($num > 0) {
					$i = 0;
					$subtotalpoint = 0;
					while ($i < $num) {
						$objp = $db->fetch_object($result);
						$message .= $objp->facnumber . "\t\t" . price($objp->amount) . "\n";
						$i++;
						$subtotalpoint += $objp->amount;
					}
				} else {
					$message .= $langs->transnoentities("NoTickets") . "\n";
				}
			}


			$message .= $langs->trans("TotalPoints") . "\t" . price($subtotalpoint) . " " . $langs->trans(currency_name($conf->currency)) . "\n\n\n";
		}
		/*$sql = "SELECT t.ticketnumber, t.type, l.total_ht, l.tva_tx, l.total_tva, l.total_localtax1, l.total_localtax2, l.total_ttc";
	$sql .=" FROM ".MAIN_DB_PREFIX."pos_ticket as t left join ".MAIN_DB_PREFIX."pos_ticketdet as l on l.fk_ticket= t.rowid";
	$sql .=" WHERE t.fk_control = ".$id." AND t.fk_cash=".$terminal." AND t.fk_statut > 0";

	$sql .= " UNION SELECT f.facnumber, f.type, fd.total_ht, fd.tva_tx, fd.total_tva, fd.total_localtax1, fd.total_localtax2, fd.total_ttc";
	$sql .=" FROM ".MAIN_DB_PREFIX."pos_facture as pf,".MAIN_DB_PREFIX."facture as f left join ".MAIN_DB_PREFIX."facturedet as fd on fd.fk_facture= f.rowid";
	$sql .=" WHERE pf.fk_control_cash = ".$id." AND pf.fk_cash=".$terminal." AND pf.fk_facture = f.rowid and f.fk_statut > 0";

	$result=$db->query($sql);

	if ($result)
	{
		$num = $db->num_rows($result);
		if($num>0)
		{
			$i = 0;
			$subtotalcardht=0;
			while ($i < $num)
			{
				$objp = $db->fetch_object($result);
				$i++;
				if($objp->type == 1){
					$objp->total_ht= $objp->total_ht * -1;
					$objp->total_tva= $objp->total_tva * -1;
					$objp->total_ttc= $objp->total_ttc * -1;
					$objp->total_localtax1= $objp->total_localtax1 * -1;
					$objp->total_localtax2= $objp->total_localtax2 * -1;
				}

				$subtotalcardht+=$objp->total_ht;
				$subtotalcardtva[$objp->tva_tx] += $objp->total_tva;
				$subtotalcardttc += $objp->total_ttc;
				$subtotalcardlt1 += $objp->total_localtax1;
				$subtotalcardlt2 += $objp->total_localtax2;
			}
		}

	}
	$message .= "------------------\n";
	if(! empty($subtotalcardht))$message .= $langs->trans("TotalHT")."\t".price($subtotalcardht)." ".$langs->trans(currency_name($conf->currency))."\n";
	if(! empty($subtotalcardtva)){
		foreach($subtotalcardtva as $tvakey => $tvaval){
			if($tvakey > 0)
				$message .= $langs->trans("TotalVAT").' '.round($tvakey).'%'."\t".price($tvaval)." ".$langs->trans(currency_name($conf->currency))."\n";
		}
	}
	if($subtotalcardlt1)
		$message .= $langs->transcountrynoentities("TotalLT1",$mysoc->country_code)."\t".price($subtotalcardlt1)." ".$langs->trans(currency_name($conf->currency))."\n";
	if($subtotalcardlt2)
		$message .= $langs->transcountrynoentities("TotalLT2",$mysoc->country_code)."\t".price($subtotalcardlt2)." ".$langs->trans(currency_name($conf->currency))."\n";

	$message .= $langs->trans("TotalPOS")."\t".price($subtotalcardttc)." ".$langs->trans(currency_name($conf->currency))."\n";
	*/
		return $message;
	}


	/**
	 * Send mail with ticket data
	 * @param  $email
	 * @return int            <0 if KO; >0 if OK
	 */
	public  function sendMail($email)
	{
		global $db, $conf, $langs;
		$function = "sendMail";


		require_once(DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php');
		if ($email["idTicket"]) {
			$ticket = new Ticket($db);
			$ticket->fetch($email["idTicket"]);
			$subject = $conf->global->MAIN_INFO_SOCIETE_NOM . ': ' . $langs->trans("CopyOfTicket") . ' ' . $ticket->ticketnumber;
			$message = self::FillMailTicketBody($ticket->id);
		}
		if ($email["idFacture"]) {
			$facture = new Facture($db);
			$facture->fetch($email["idFacture"]);
			$subject = $conf->global->MAIN_INFO_SOCIETE_NOM . ': ' . $langs->trans("CopyOfFacture") . ' ' . $facture->ref;
			$message = self::FillMailFactureBody($facture->id);

			$ref = dol_sanitizeFileName($facture->ref);
			include_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
			$fileparams = dol_most_recent_file($conf->facture->dir_output . '/' . $ref, preg_quote($ref, '/'));
			$file = $fileparams ['fullname'];

			// Build document if it not exists
			if (!$file || !is_readable($file)) {
				$result = $facture->generateDocument($facture->modelpdf, $langs,
						(!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0),
						(!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0),
						(!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0));
				if ($result <= 0) {
					dol_print_error($db, $result);
					exit();
				}
				$fileparams = dol_most_recent_file($conf->facture->dir_output . '/' . $ref, preg_quote($ref, '/'));
				$file = $fileparams ['fullname'];
			}

		}
		if ($email["idCloseCash"]) {
			$subject = $conf->global->MAIN_INFO_SOCIETE_NOM . ': ' . $langs->trans("CopyOfCloseCash") . ' ' . $email["idCloseCash"];
			$message = self::FillMailCloseCashBody($email["idCloseCash"]);
		}
		$from = $conf->global->MAIN_INFO_SOCIETE_NOM . "<" . $conf->global->MAIN_MAIL_EMAIL_FROM . ">";
		if (!empty($file)) {

			$filename_list[] = $file;
			$mimetype_list[] = dol_mimetype($file);
			$mimefilename_list[] = basename($file);

		}


		$mailfile = new CMailFile($subject, $email["mail_to"], $from, $message, $filename_list, $mimetype_list,
				$mimefilename_list);
		if ($mailfile->error) {
			$mesg = '<div class="error">' . $mailfile->error . '</div>';
			$res = -1;
		} else {
			$res = $mailfile->sendfile();
		}

		return ErrorControl($res, $function);
	}

	/**
	 *    Delete ticket
	 * @param        int $idTicket Id of ticket to delete
	 * @return        int                    <0 if KO, >0 if OK
	 */
	public  function Delete_Ticket($idTicket)
	{
		global $db;

		$function = "deleteTicket";

		$object = new Ticket($db);
		$object->fetch($idTicket);
		$db->begin;
		$res = $object->delete_ticket();

		if ($res) {
			$db->commit();
		} else {
			$db->rollback();
		}

		return ErrorControl($res, $function);
	}

	public  function calculePrice($product)
	{
		global $mysoc, $conf;
		//require_once(DOL_DOCUMENT_ROOT . "/core/lib/price.lib.php");
		dol_include_once('/pos/backend/lib/pos.price.lib.php');
		if ($product["is_promo"]) {
			$product["discount"] = 0;
			$product["discount_global"] = 0;	
		} else {
			$product["discount"] = ($product["discount"]) ? $product["discount"] : 0;	
			$product["discount_global"] = ($product["discount_global"]) ? $product["discount_global"] : 0;
		}
		if ($product["uselocaltax1_rate"] < 0) $product["uselocaltax1_rate"] = 0;
		if ($product["uselocaltax2_rate"] < 0) $product["uselocaltax2_rate"] = 0;
		
		$product["type"] = $product["type"] ? $product["type"] : 0;

		return calcule_product_price($product);

	}

	public  function getLocalTax($data)
	{
		global $db;
		require_once(DOL_DOCUMENT_ROOT . "/core/lib/functions.lib.php");
		$customer = new Societe($db);
		$customer->fetch($data["customer"]);
		$localtax['1'] = get_localtax($data["tva"], 1, $customer);
		$localtax['2'] = get_localtax($data["tva"], 2, $customer);
		return $localtax;
	}

	public  function getNotes($mode)
	{
		global $db;

		$ret = -1;
		$function = "GetNotes";
		if ($mode) {
			$sql = 'SELECT f.rowid as ticketid, f.ticketnumber, fd.description, f.note as ticketNote, fd.note as lineNote';
		} else {
			$sql = 'SELECT count(*)';
		}
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'pos_ticket as f';
		$sql .= ', ' . MAIN_DB_PREFIX . 'pos_ticketdet as fd';
		$sql .= ' WHERE f.fk_statut = 0';
		$sql .= ' AND f.rowid = fd.fk_ticket';
		$sql .= ' AND (f.note is not null';
		$sql .= ' OR fd.note is not null)';
		if ($mode == 0) {
			$sql .= 'GROUP BY f.ticketnumber';
		}

		$res = $db->query($sql);
		if ($res) {
			$num = $db->num_rows($res);
			if ($mode) {
				$i = 0;
				$j = 0;
				$id = 0;
				while ($i < $num) {
					$obj = $db->fetch_object($res);

					if ($id != $obj->ticketid) {
						$id = $obj->ticketid;
						$tickets[$j]["id"] = $j;
						$tickets[$j]["ticketid"] = $obj->ticketid;
						$tickets[$j]["ticketnumber"] = $obj->ticketnumber;
						$tickets[$j]["description"] = '';
						$tickets[$j]["note"] = $obj->ticketNote ? $obj->ticketNote : '';
						$j++;
					}
					if ($obj->lineNote) {
						$tickets[$j]["id"] = $j;
						$tickets[$j]["ticketid"] = $obj->ticketid;
						$tickets[$j]["ticketnumber"] = '';
						$tickets[$j]["description"] = $obj->description;
						$tickets[$j]["note"] = $obj->lineNote;
						$j++;
					}

					$i++;
				}
				return $tickets;
			} else {
				return $num;
			}

		} else {
			return ErrorControl($ret, $function);
		}
	}

	/**
	 *  Return list of all warehouses
	 *
	 * @param    int $status Status
	 * @return array                Array list of warehouses
	 */
	public  function getWarehouse($status = 1)
	{
		global $db;
		$liste = array();

		$sql = "SELECT rowid, lieu";
		$sql .= " FROM " . MAIN_DB_PREFIX . "entrepot";
		$sql .= " WHERE entity IN (" . getEntity('stock', 1) . ")";
		$sql .= " AND statut = " . $status;

		$result = $db->query($sql);
		$i = 0;
		$num = $db->num_rows($result);
		if ($result) {
			while ($i < $num) {
				$row = $db->fetch_row($result);
				$liste[$i]["id"] = $row[0];
				$liste[$i]["lieu"] = $row[1];
				$i++;
			}
			$db->free($result);
		}
		return $liste;
	}

	/**
	 *    Reconstruit l'arborescence des categories sous la forme d'un tableau
	 *    Renvoi un tableau de tableau('id','id_mere',...) trie selon arbre et avec:
	 *                id = id de la categorie
	 *                id_mere = id de la categorie mere
	 *                id_children = tableau des id enfant
	 *                label = nom de la categorie
	 *                fulllabel = nom avec chemin complet de la categorie
	 *                fullpath = chemin complet compose des id
	 *
	 * @param      string $type        Type of categories (0=product, 1=suppliers, 2=customers, 3=members)
	 * @param      int    $markafterid Mark all categories after this leaf in category tree.
	 * @return        array|int                      Array of categories
	 */
	public function get_full_arbo($type)
	{
		global $db;

		$categorie = new Categorie($db);

		$categorie->cats = array();

		// Init $this->cats array
		$sql = "SELECT DISTINCT c.rowid, c.label, c.description, c.fk_parent";    // Distinct reduce pb with old tables with duplicates
		$sql .= " FROM " . MAIN_DB_PREFIX . "categorie as c";
		$sql .= " WHERE c.entity IN (" . getEntity('category', 1) . ")";
		$sql .= " AND c.type = " . $type;
		$sql .= " AND fk_parent = 0";

		dol_syslog(get_class($categorie) . "::get_full_arbo get category list sql=" . $sql, LOG_DEBUG);
		$resql = $db->query($sql);
		if ($resql) {
			$i = 0;
			while ($obj = $db->fetch_object($resql)) {
				$categorie->cats[$obj->rowid]['rowid'] = $obj->rowid;
				$categorie->cats[$obj->rowid]['id'] = $obj->rowid;
				$categorie->cats[$obj->rowid]['fk_parent'] = $obj->fk_parent;
				$categorie->cats[$obj->rowid]['label'] = $obj->label;
				$categorie->cats[$obj->rowid]['description'] = $obj->description;
				$i++;
			}
		} else {
			dol_print_error($db);
			return -1;
		}

		// We add the fullpath property to each elements of first level (no parent exists)
		dol_syslog(get_class($categorie) . "::get_full_arbo call to build_path_from_id_categ", LOG_DEBUG);
		foreach ($categorie->cats as $key => $val) {
			$categorie->build_path_from_id_categ($key,
					0);    // Process a branch from the root category key (this category has no parent)
		}

		dol_syslog(get_class($categorie) . "::get_full_arbo dol_sort_array", LOG_DEBUG);
		$categorie->cats = dol_sort_array($categorie->cats, 'fulllabel', 'asc', true, false);

		//$this->debug_cats();

		return $categorie->cats;
	}
    /**
     *    Return list of contents of a category
     *
     * @param $idCat
     * @param $more
     * @param $ticketstate
     * @return array|int
     * @internal param string $field Field name for select in table. Full field name will be fk_field.
     * @internal param string $classname PHP Class of object to store entity
     * @internal param string $category_table Table name for select in table. Full table name will be PREFIX_categorie_table.
     * @internal param string $object_table Table name for select in table. Full table name will be PREFIX_table.
     */
	public  function get_prod($idCat, $more, $ticketstate)
	{
		global $db, $conf;
		$objs = array();

		$sql = "SELECT o.rowid as id, o.ref, o.label, o.description, ";
		$sql .= " o.fk_product_type";
		$sql .= " FROM " . MAIN_DB_PREFIX . "categorie_product as c";
		$sql .= ", " . MAIN_DB_PREFIX . "product as o";
		if ($conf->global->POS_STOCK || $ticketstate == 1) {
			$sql .= " WHERE o.entity IN (" . getEntity("product", 1) . ")";
			$sql .= " AND c.fk_categorie = " . $idCat;
			$sql .= " AND c.fk_product = o.rowid";
			$sql .= " AND o.tosell = 1";
			if (!$conf->global->POS_SERVICES) {
				$sql .= " AND o.fk_product_type = 0";
			}
		} else {
			$cashid = $_SESSION['TERMINAL_ID'];
			$cash = new Cash($db);
			$cash->fetch($cashid);
			$warehouse = $cash->fk_warehouse;

			$sql .= ", " . MAIN_DB_PREFIX . "product_stock as ps";
			$sql .= " WHERE o.entity IN (" . getEntity("product", 1) . ")";
			$sql .= " AND c.fk_categorie = " . $idCat;
			$sql .= " AND c.fk_product = o.rowid";
			$sql .= " AND o.tosell = 1";
			$sql .= " AND o.rowid = ps.fk_product";
			$sql .= " AND ps.fk_entrepot = " . $warehouse;
			$sql .= " AND ps.reel > 0";
			if ($conf->global->POS_SERVICES) {
				$sql .= " union select o.rowid as id, o.ref, o.label, o.description,	";
				$sql .= " o.fk_product_type";
				$sql .= " FROM " . MAIN_DB_PREFIX . "categorie_product as c,";
				$sql .= MAIN_DB_PREFIX . "product as o";
				$sql .= " where c.fk_categorie = " . $idCat;
				$sql .= " AND c.fk_product = o.rowid";
				$sql .= " AND o.tosell = 1";
				$sql .= " AND fk_product_type=1";
			}
		}
		if ($more >= 0) {
			$sql .= " LIMIT " . $more . ",10 ";
		}

		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);
			$i = 0;

			while ($i < $num) {
				$objp = $db->fetch_object($resql);

				$objs[$objp->id]["id"] = $objp->id;
				$objs[$objp->id]["ref"] = $objp->ref;
				$objs[$objp->id]["label"] = $objp->label;
				$objs[$objp->id]["description"] = $objp->description;
				$objs[$objp->id]["type"] = $objp->fk_product_type;

				$objs[$objp->id]["image"] = self::getImageProduct($objp->id, false);
				$objs[$objp->id]["thumb"] = self::getImageProduct($objp->id, true);
				$i++;
			}
			return $objs;
		} else {
			return -1;
		}
	}
	public  function checkPassword($login, $password, $userid)
	{
		dol_include_once('/pos/class/auth.class.php');
		global $db;
		$function = "checkPassword";

		$auth = new Auth($db);
		$res = $auth->verif($login, $password);

		if ($res >= 0) {
			$_SESSION['uid'] = $userid;
			$_SESSION['uname'] = $login;

			$sql = "UPDATE " . MAIN_DB_PREFIX . "pos_cash";
			$sql .= " SET fk_user_u = " . $_SESSION['uid'];
			$sql .= " WHERE is_used = 1 AND rowid = " . $_SESSION["TERMINAL_ID"];

			$resql = $db->query($sql);
		}

		return ErrorControl($res, $function);
	}
	public  function checkTerminal()
	{
		global $conf, $db;
		$sql = "SELECT rowid";
		$sql .= " FROM " . MAIN_DB_PREFIX . "pos_cash";
		$sql .= " WHERE entity = " . $conf->entity;
		$sql .= " AND is_used = 1 AND rowid = " . $_SESSION["TERMINAL_ID"] . " AND fk_user_u = " . $_SESSION['uid'];

		$resql = $db->query($sql);

		$num = $db->num_rows($resql);

		return $num;
	}
	public  function searchCoupon($customerId)
	{
		global $db, $langs;
        $coupon=array();

		$sql = "SELECT rc.rowid, rc.amount_ttc,";
		$sql .= "  rc.description, fa.facnumber as ref";
		$sql .= " FROM  " . MAIN_DB_PREFIX . "societe_remise_except as rc";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture as fa ON rc.fk_facture_source = fa.rowid";
		$sql .= " WHERE rc.fk_soc =" . $customerId;
		$sql .= " AND (rc.fk_facture_line IS NULL AND rc.fk_facture IS NULL)";
		$sql .= " ORDER BY rc.datec DESC";

		$resql = $db->query($sql);
		if ($resql) {
			$i = 0;
			$num = $db->num_rows($resql);
			while ($i < $num) {
				$obj = $db->fetch_object($resql);
				$coupon[$i]['id'] = $obj->rowid;
				$coupon[$i]['amount_ttc'] = $obj->amount_ttc;

                if (preg_match('/\(CREDIT_NOTE\)/',$obj->description))
                {
                    $coupon[$i]['description'] = preg_replace('/\(CREDIT_NOTE\)/',$langs->trans("CreditNote"),$obj->description).' '.$obj->ref;
                }
                elseif (preg_match('/\(DEPOSIT\)/',$obj->description))
                {
                    $coupon[$i]['description'] = preg_replace('/\(DEPOSIT\)/',$langs->trans("InvoiceDeposit"),$obj->description).' '.$obj->ref;
                }
                else
                {
                    $coupon[$i]['description'] = $obj->description;
                }

				$i++;
			}
		}
		return $coupon;
	}

	public  function addPrint($addprint)
	{
		require_once(DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php");
		global $db, $conf;

		$res = dolibarr_set_const($db, "POS_PENDING_PRINT", $conf->global->POS_PENDING_PRINT . $addprint . ',',
				'chaine', 0, '');

		return $res;
	}

	public  function getSeries()
    {
        global $conf, $db;

        if ($conf->numberseries->enabled && $conf->global->NUMBERSERIES_POS) {
            $sql = "SELECT rowid, ref FROM " . MAIN_DB_PREFIX . "numberseries WHERE typedoc = 1 AND entity = " . $conf->entity . " ORDER BY defaultserie DESC";
            $resql = $db->query($sql);
            if ($resql) {
                $series = array();
                $i = 0;
                while ($obj = $db->fetch_object($resql)) {
                    $series[$i]['rowid'] = $obj->rowid;
                    $series[$i]['ref'] = $obj->ref;
                    $i++;
                }
                return $series;
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }

     public  function doTranfer($ammount)
    {
    	$function = "doTranfer";
    	global $conf, $db, $langs, $user;

    	$terminal = $_SESSION["TERMINAL_ID"];
    	$cash = new Cash($db);
        $cash->fetch($terminal);
        $account_from = $cash->fk_paycash;
        $ControlCash= new ControlCash($db, $terminal);
    	$total_money_available = $ControlCash->getMoneyFromAccount($account_from);
    	if ($ammount > $total_money_available){
    		return Errorcontrol(-1,$function);	
    	}

    	$sql = "SELECT fk_bank  FROM " . MAIN_DB_PREFIX . "pos_cash_bank_tranfer WHERE fk_cash =  ".$terminal." and status = 1";
 		$result = $db->query($sql);
        if (($result) && ($db->num_rows($result))) {
                $obj = $db->fetch_object($result);
                $account_to = $obj->fk_bank;
        } else{
           	return Errorcontrol(-2,$function);
        }	
    	if($account_from == $account_to){
    		return Errorcontrol(-3,$function);
    	}
    	require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

		$accountfrom=new Account($db);
		$accountfrom->fetch($account_from);

		$accountto=new Account($db);
		$accountto->fetch($account_to);

        if ($accountto->currency_code != $accountfrom->currency_code) {
        	return Errorcontrol(-4,$function);		
		}
		$label = $langs->trans("TranferFromPOS") .":". $cash->name ;
		$dateo = dol_mktime(12,0,0,date("m"),date("d"),date("Y"));
		$db->begin();

		$error=0;
		$bank_line_id_from=0;
		$bank_line_id_to=0;
		$result=0;
		$typefrom='LIQ';
		$typeto='LIQ';
		$bank_line_id_from = $accountfrom->addline($dateo, 'LIQ', $label, -1*price2num($ammount), '', '', $user);
		if (! ($bank_line_id_from > 0)) $error++;
		if (! $error) $bank_line_id_to = $accountto->addline($dateo, 'LIQ', $label, price2num($ammount), '', '', $user);
		if (! ($bank_line_id_to > 0)) $error++;

	    if (! $error) $result=$accountfrom->add_url_line($bank_line_id_from, $bank_line_id_to, DOL_URL_ROOT.'/compta/bank/ligne.php?rowid=', '(banktransfert)', 'banktransfert');
		if (! ($result > 0)) $error++;
	    if (! $error) $result=$accountto->add_url_line($bank_line_id_to, $bank_line_id_from, DOL_URL_ROOT.'/compta/bank/ligne.php?rowid=', '(banktransfert)', 'banktransfert');
		if (! ($result > 0)) $error++;

		if (! $error)
		{
			$mesgs = $langs->trans("TransferFromToDone","<a href=\"bankentries.php?id=".$accountfrom->id."\">".$accountfrom->label."</a>","<a href=\"bankentries.php?id=".$accountto->id."\">".$accountto->label."</a>",$tranfer['ammount'],$langs->transnoentities("Currency".$conf->currency));
			setEventMessages($mesgs, null, 'mesgs');
			$db->commit();
			$ret['error']['value'] = 0;
            $ret['error']['desc'] = $langs->trans("msgTranferOK");
            $ret['data'] ['line_from']= $bank_line_id_from;
            $ret['data'] ['line_to']= $bank_line_id_to;
			return $ret;
		} 
		else
		{
			setEventMessages($accountfrom->error.' '.$accountto->error, null, 'errors');
			$db->rollback();
			return Errorcontrol(-5,$function);
		}
    }

	 function update_obj_price($object)
	{
	
		global $db;
		$multicurrency_tx = !empty($object->multicurrency_tx) ? $object->multicurrency_tx : 1;
        $object->total_ttc   += isset($object->revenuestamp)?$object->revenuestamp:0;

		$mult = 1;
        if ($object->type == facture::TYPE_CREDIT_NOTE) {
        	$mult = -1;
        }
        $fieldht		='total_ht';
        $fieldtva		='tva';
        $fieldlocaltax1	='localtax1';
        $fieldlocaltax2	='localtax2';
        $fieldttc		='total_ttc';
        $fieldobjtva  	= $fieldtva;
        if ($object->element == 'facture' || $object->element == 'facturerec')  $fieldht='total';
        if ($object->element == 'facture_fourn' || $object->element == 'invoice_supplier') $fieldtva='total_tva';
        if ($object->element == 'ticket' ) $fieldobjtva ='total_tva';

        $object->multicurrency_total_ht		= $object->$fieldht 	* $mult * 	$multicurrency_tx;
        $object->multicurrency_total_tva	= $object->$fieldobjtva * $mult *	$multicurrency_tx;
        $object->multicurrency_total_ttc	= $object->$fieldttc 	* $mult * 	$multicurrency_tx;
        
        $sql = 'UPDATE '.MAIN_DB_PREFIX.$object->table_element.' SET';
        $sql .= " ".$fieldht."=".price2num($object->$fieldht * $mult, 'MT', 1).",";
        $sql .= " ".$fieldtva."=".price2num($object->$fieldobjtva * $mult, 'MT', 1).",";
        $sql .= " ".$fieldlocaltax1."=".price2num($object->$fieldlocaltax1 * $mult, 'MT', 1).",";
        $sql .= " ".$fieldlocaltax2."=".price2num($object->$fieldlocaltax2 * $mult, 'MT', 1).",";
        $sql .= " ".$fieldttc."=".price2num($object->$fieldttc * $mult)."";
		$sql .= ", multicurrency_total_ht=".price2num($object->multicurrency_total_ht, 'MT', 1);
		$sql .= ", multicurrency_total_tva=".price2num($object->multicurrency_total_tva, 'MT', 1);
		$sql .= ", multicurrency_total_ttc=".price2num($object->multicurrency_total_ttc, 'MT', 1);
        $sql .= ' WHERE rowid = '.$object->id;
       $db->begin(); 	
        $resql=$object->db->query($sql);
        if (! $resql)
        {
            $db->rollback();
            return -1;
            $object->error=$object->db->lasterror();
            $object->errors[]=$object->db->lasterror();
        }
        $db->commit(); 
        return 1;
    }
    
     function calcule_total_ttc_without_discount($product)
    {
		dol_include_once('/pos/backend/lib/pos.price.lib.php');
		$txtva= (empty($product["tva_tx"]))?0:$product["tva_tx"];
		$tot_price_ttc = $product["price"] * $product["qty"] * (1 + ($txtva / 100));
	    	$total_price_localtax = 0;
	    if (($product["uselocaltax1_rate"]) && (check_apply_tax($product['localtax1_type'],$product["type"]))) {
			$total_price_localtax += ($tot_price_ttc * (1 + ( $product['localtax1_tx'] / 100))) - $tot_price_ttc;
	  	}
	  	if (($product["uselocaltax2_rate"]) && (check_apply_tax($product['localtax2_type'],$product["type"]))) {
	  		$total_price_localtax += ($tot_price_ttc * (1 + ( $product['localtax2_tx'] / 100))) - $tot_price_ttc;
	  	}
	  	return round($tot_price_ttc + $total_price_localtax,2);
	}

	 function get_warehouse_to($data)
    {
    	global $db;
		require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
		$formproduct=new FormProduct($db);
		$ret = $formproduct->selectWarehouses(0, $data['id'], 'warehouseopen,warehouseinternal', 1, 0, 0, '', 0, 1, array(), 'minwidth200imp', array(0=>$data['warehouse']));
		return array('select' => $ret);
	}

	 function stockTranfer($data)
    {
    	$ret['error']['value'] = -1;
    	$ret['data'] = -1;
        if (($data['wh_from'] <> $data['wh_to']) && is_numeric($data['qty']) && $data['id_product'])
    	{
    		$function = "stockTranfer";
    		global $db, $conf, $langs;
    		$user = new User($db);
    		if (!$data['id_employee']) {
				$user->fetch($_SESSION['uid']);
			} else {
				$user->fetch($data['id_employee']);
			}
	    	require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
			$product = new Product($db);
			$product->fetch($data['id_product']);
			if (empty($conf->productbatch->enabled) || ! $product->hasbatch())	
			{
				$product->load_stock('novirtual nobatch');	// Load array product->stock_warehouse
				$pricesrc=0;
				if (! empty($product->pmp)) $pricesrc=$product->pmp;
				$pricedest=$pricesrc;
				$result1=$product->correct_stock($user,$data['wh_from'],$data['qty'],1,$langs->trans("tranferStockFromPOS"),$pricesrc,"");
				if ($result1 >=  0) {
				// Add stock
					$result2=$product->correct_stock($user,$data['wh_to'],$data['qty'],0,$langs->trans("tranferStockFromPOS"),$pricedest, "");
					if ($result2 < 0) return Errorcontrol(-2,$function);
				} else{
					$ret['error']['desc'] = $langs->trans("errorStockTransferFrom");
					return $ret;
				}
			} else {
				$ret['error']['desc'] = $langs->trans("errorStockTransferTo");
				return $ret;
			}
		} else{
			$ret['error']['desc'] = $langs->trans("badValues");
				return $ret;
		}
		$product->load_stock('novirtual nobatch');
		$ret['error']['value'] = 0;
		$ret['data'] = $product->stock_warehouse;
        $ret['error']['desc'] = $langs->trans("msgTranferOK");
		return $ret;
	}	
}