<?php
/**
 * @package Facebook Inventory
 * based on Google Merchant Center Feeder Copyright 2007 Numinix Technology (www.numinix.com)
 * @copyright Copyright 2011-2023 webchills (www.webchills.at)
 * @copyright Portions Copyright 2003-2022 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license https://www.zen-cart-pro.at/license/3_0.txt GNU General Public License V3.0
 * @version $Id: facebookinventory.php 2023-12-08 17:56:54Z webchills $
 */
 
  class facebookinventory {
    // writes out the code into the feed file
    function facebookinventory_fwrite($output='', $mode='') {
      global $outfile;
      $output = implode("\n", $output);
      if(strtolower(CHARSET) != 'utf-8') {
        $output = utf8_encode($output);
      } else {
        $output = $output;
      }
      $fp = fopen($outfile, $mode);
      $retval = fwrite($fp, $output, FACEBOOKINVENTORY_OUTPUT_BUFFER_MAXSIZE);
      return $retval;
    }
    
      
    // trims the value of each element of an array
    function trim_array($x) {
      if (is_array($x)) {
         return array_map('trim_array', $x);
      } else {
       return trim($x);
      }
    } 

    // determines if the feed should be generated
    function get_feed($feed_parameter) {
      switch($feed_parameter) {
        case 'fy':
          $feed = 'yes';
          break;
        case 'fn':
          $feed = 'no';
          break;
        default:
          $feed = 'no';
          break;
      }
      return $feed;
    }

    // determines if the feed should be automatically uploaded to Google Base
    function get_upload($upload_parameter) {
      switch($upload_parameter) {
        case 'uy':
          $upload = 'yes';
          break;
        case 'un':
          $upload = 'no';
          break;
        default:
          $upload = 'no';
          break;
      }
      return $upload;
    }
    
    // returns the type of feed
    function get_type($type_parameter) {
      switch($type_parameter) {
        case 'tp':
          $type = 'products';
          break;
        case 'td':
          $type = 'documents';
          break;
        case 'tn':
          $type = 'news';
          break;
        default:
          $type = 'products';
          break;
      }
      return $type;
    }
    
    // performs a set of functions to see if a product is valid
    function check_product($products_id) {
      if ($this->included_categories_check(FACEBOOKINVENTORY_POS_CATEGORIES, $products_id) && !$this->excluded_categories_check(FACEBOOKINVENTORY_NEG_CATEGORIES, $products_id) && $this->included_manufacturers_check(FACEBOOKINVENTORY_POS_MANUFACTURERS, $products_id) && !$this->excluded_manufacturers_check(FACEBOOKINVENTORY_NEG_MANUFACTURERS, $products_id)) {
        return true;
      } else {
        return false;
      }
    }
    
    // check to see if a product is inside an included category
    function included_categories_check($categories_list, $products_id) {
      if ($categories_list == '') {
        return true;
      } else {
        $categories_array = explode(',', $categories_list);
        $match = false;
        foreach($categories_array as $category_id) {
          if (zen_product_in_category($products_id, $category_id)) {
            $match = true;
            break;
          }
        }
        if ($match == true) {
          return true;
        } else {
          return false;
        }
      }
    }
    
    // check to see if a product is inside an excluded category
    function excluded_categories_check($categories_list, $products_id) {
      if ($categories_list == '') {
        return false;
      } else {
        $categories_array = explode(',', $categories_list);
        $match = false;
        foreach($categories_array as $category_id) {
          if (zen_product_in_category($products_id, $category_id)) {
            $match = true;
            break;
          }
        }
        if ($match == true) {
          return true;
        } else {
          return false;
        }
      }
    }
    
    // check to see if a product is from an included manufacturer
    function included_manufacturers_check($manufacturers_list, $products_id) {
      if ($manufacturers_list == '') {
        return true;
      } else {
        $manufacturers_array = explode(',', $manufacturers_list);
        $products_manufacturers_id = zen_get_products_manufacturers_id($products_id);
        if (in_array($products_manufacturers_id, $manufacturers_array)) {
          return true;
        } else {
          return false;
        }
      }
    }
    
    function excluded_manufacturers_check($manufacturers_list, $products_id) {
      if ($manufacturers_list == '') {
        return false;
      } else {
        $manufacturers_array = explode(',', $manufacturers_list);
        $products_manufacturers_id = zen_get_products_manufacturers_id($products_id);
        if (in_array($products_manufacturers_id, $manufacturers_array)) {
          return true;
        } else {
          return false;
        }
      }
    }
    
    // returns an array containing the category name and cPath
    function facebookinventory_get_category($products_id) {
      global $categories_array, $db;
      static $p2c;
      if(!$p2c) {
        $q = $db->Execute("SELECT *
                          FROM " . TABLE_PRODUCTS_TO_CATEGORIES);
        while (!$q->EOF) {
          if(!isset($p2c[$q->fields['products_id']]))
            $p2c[$q->fields['products_id']] = $q->fields['categories_id'];
          $q->MoveNext();
        }
      }
      if(isset($p2c[$products_id])) {
        $retval = $categories_array[$p2c[$products_id]]['name'] ?? '';
        $cPath = $categories_array[$p2c[$products_id]]['cPath'] ?? '';
      } else {
        $cPath = $retval =  "";
      }
      return array($retval, $cPath);
    }
    
    // builds the category tree
    function facebookinventory_category_tree($id_parent=0, $cPath='', $cName='', $cats=array()){
      global $db, $languages;
      $cat = $db->Execute("SELECT c.categories_id, c.parent_id, cd.categories_name
                           FROM " . TABLE_CATEGORIES . " c
                             LEFT JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd on c.categories_id = cd.categories_id
                           WHERE c.parent_id = '" . (int)$id_parent . "'
                           AND cd.language_id='" . (int)$languages->fields['languages_id'] . "'
                           AND c.categories_status= '1'",
                           '', false, 150);
      while (!$cat->EOF) {
        $cats[$cat->fields['categories_id']]['name'] = (zen_not_null($cName) ? $cName . ', ' : '') . trim($cat->fields['categories_name']); // previously used zen_froogle_sanita instead of trim
        $cats[$cat->fields['categories_id']]['cPath'] = (zen_not_null($cPath) ? $cPath . '_' : '') . $cat->fields['categories_id'];
        if (zen_has_category_subcategories($cat->fields['categories_id'])) {
          $cats = $this->facebookinventory_category_tree($cat->fields['categories_id'], $cats[$cat->fields['categories_id']]['cPath'], $cats[$cat->fields['categories_id']]['name'], $cats);
        }
        $cat->MoveNext();
      }
      return $cats;
    }
    
    function facebookinventory_sanita($str, $rt=false) {
      
      
      $str = str_replace(array("\t" , "\n", "\r", "&nbsp;", "<li>", "</li>", "<p>", "</p>", "<br />", "<blockquote>", "</blockquote>", "<tr>", "</tr>", "•"), ' ', $str);
      $str = strip_tags($str);
      $str = preg_replace('/\s\s+/', ' ', $str);
      
      // keep quotes as char
      $str = str_replace("&quot;", "\"", $str);
      $str = str_replace("ä", "ä", $str);
      $str = str_replace("ü", "ü", $str);
	  $str = str_replace("ö", "ö", $str);
      // preserve &amp;
      
      $str = str_replace(array("&amp;", "&"), "AMPERSAN", $str);
      
      $str = preg_replace('/AMPERSAN[A-Za-z0-9#]{1,10};/', '', $str); // remove all entities, shouldn't be longer than 10 characters?
      
      // readd &amp;
      $str = str_replace("AMPERSAN", "&", $str);
     
      $_cleaner_array = array(">" => "> ", "®" => "(r)", "™" => "(tm)", "©" => "(c)", "‘" => "'", "’" => "'", "—" => "-", "–" => "-", "&" => "&amp;", "&amp;amp;" => "&amp;", "“" => "\"", "”" => "\"", "…" => "...");
      $str = strtr($str, $_cleaner_array);
      return $str;
    }
    
    function facebookinventory_taxonomysanita($str, $rt=false) {
      //for taxonomy
      
      $str = str_replace(array("\t" , "\n", "\r", "&nbsp;", "<li>", "</li>", "<p>", "</p>", "<br />", "<blockquote>", "</blockquote>", "<tr>", "</tr>", "•"), ' ', $str);
      $str = strip_tags($str);
      $str = preg_replace('/\s\s+/', ' ', $str);      
      // keep quotes as char
      $str = str_replace("&quot;", "\"", $str);
      $str = str_replace("ä", "ä", $str);
      $str = str_replace("ü", "ü", $str);
	    $str = str_replace("ö", "ö", $str);
      // preserve &amp;
      
      $str = str_replace(array("&amp;", "&"), "AMPERSAN", $str);
      
      $str = preg_replace('/AMPERSAN[A-Za-z0-9#]{1,10};/', '', $str); // remove all entities, shouldn't be longer than 10 characters?
     
      // readd &amp;
      $str = str_replace("AMPERSAN", "&", $str);
      // could be finetuned in upcoming versions
      $_cleaner_array = array(">" => "&gt; ", "®" => "(r)", "™" => "(tm)", "©" => "(c)", "‘" => "'", "’" => "'", "—" => "-", "–" => "-", "&" => "&amp;", "&amp;amp;" => "&amp;", "“" => "\"", "”" => "\"", "…" => "...");
      $str = strtr($str, $_cleaner_array);
      return $str;
    }
    
    
    
    function facebookinventory_xml_sanitizer($str, $cdata = false) {
      $str = $this->facebookinventory_sanita($str);
      $length = strlen($str);
      $out = '';
      for ($i = 0; $i < $length; $i++) {
        $current = ord($str[$i]);
        if ((($current == 0x9) || ($current == 0xA) || ($current == 0xD) || (($current >= 0x20) && ($current <= 0xD7FF)) || (($current >= 0xE000) && ($current <= 0xFFFD)) || (($current >= 0x10000) && ($current <= 0x10FFFF))) && ($current > 10) ) {
          $out .= chr($current);
        } else {
          $out .= " ";
        }
      }
      $str = trim($str);
     
      return $str;
    }
    
    // creates the url for the products_image
    function facebookinventory_image_url($products_image) {
      if($products_image == "") return "";
      if (defined('FACEBOOKINVENTORY_ALTERNATE_IMAGE_URL') && FACEBOOKINVENTORY_ALTERNATE_IMAGE_URL != '') {
        return FACEBOOKINVENTORY_ALTERNATE_IMAGE_URL . $products_image; 
      }
      $products_image_extension = substr($products_image, strrpos($products_image, '.'));
      $products_image_base = preg_replace('/'.$products_image_extension . '$/', '', $products_image);
      $products_image_medium = $products_image_base . IMAGE_SUFFIX_MEDIUM . $products_image_extension;
      $products_image_large = $products_image_base . IMAGE_SUFFIX_LARGE . $products_image_extension;

      // check for a medium image else use small
      if (!file_exists(DIR_WS_IMAGES . 'medium/' . $products_image_medium)) {
        $products_image_medium = DIR_WS_IMAGES . $products_image;
      } else {
        $products_image_medium = DIR_WS_IMAGES . 'medium/' . $products_image_medium;
      }
      // check for a large image else use medium else use small
      if (!file_exists(DIR_WS_IMAGES . 'large/' . $products_image_large)) {
        if (!file_exists(DIR_WS_IMAGES . 'medium/' . $products_image_medium)) {
          $products_image_large = DIR_WS_IMAGES . $products_image;
        } else {
          $products_image_large = DIR_WS_IMAGES . 'medium/' . $products_image_medium;
        }
      } else {
        $products_image_large = DIR_WS_IMAGES . 'large/' . $products_image_large;
      }
      if ((function_exists('handle_image')) && (FACEBOOKINVENTORY_IMAGE_HANDLER == 'true')) {
        $image_ih = handle_image($products_image_large, '', LARGE_IMAGE_MAX_WIDTH, LARGE_IMAGE_MAX_HEIGHT, '');
        $retval = (HTTP_SERVER . DIR_WS_CATALOG . $image_ih[0]);
      } else {
        $retval = (HTTP_SERVER . DIR_WS_CATALOG . $products_image_large);
      }
      return $retval;
    }
    
    
    function facebookinventory_expiration_date($base_date) {
      if(FACEBOOKINVENTORY_EXPIRATION_BASE == 'now')
        $expiration_date = time();
      else
        $expiration_date = strtotime($base_date);
      $expiration_date += FACEBOOKINVENTORY_EXPIRATION_DAYS*24*60*60;
      $retval = (date('Y-m-d', $expiration_date));
      return $retval;
    }
 
// PRICE FUNCTIONS

// Actual Price Retail
// Specials and Tax Included
  function google_get_products_actual_price($products_id) {
    global $db, $currencies;
    $product_check = $db->Execute("select products_tax_class_id, products_price, products_priced_by_attribute, product_is_free, product_is_call from " . TABLE_PRODUCTS . " where products_id = '" . (int)$products_id . "'" . " limit 1");

    $show_display_price = '';
    $display_normal_price = $this->google_get_products_base_price($products_id);
    
    $display_special_price = $this->google_get_products_special_price($products_id, $display_normal_price, true);
    
    $display_sale_price = $this->google_get_products_special_price($products_id, $display_normal_price, false);
   
    $products_actual_price = $display_normal_price;

    if ($display_special_price) {
      $products_actual_price = $display_special_price;
    }
    if ($display_sale_price) {
      $products_actual_price = $display_sale_price;
    }

    // If Free, Show it
    if ($product_check->fields['product_is_free'] == '1') {
      $products_actual_price = 0;
    }
    

    return $products_actual_price;
  }

// computes products_price + option groups lowest attributes price of each group when on
  function google_get_products_base_price($products_id) {
    global $db;
      $product_check = $db->Execute("select products_price, products_priced_by_attribute from " . TABLE_PRODUCTS . " where products_id = '" . (int)$products_id . "'");

// is there a products_price to add to attributes
      $products_price = $product_check->fields['products_price'];

      // do not select display only attributes and attributes_price_base_included is true
      $product_att_query = $db->Execute("select options_id, price_prefix, options_values_price, attributes_display_only, attributes_price_base_included from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int)$products_id . "' and attributes_display_only != '1' and attributes_price_base_included='1' and options_values_price > 0". " order by options_id, price_prefix, options_values_price");
      $the_options_id= 'x';
      $the_base_price= 0;
// add attributes price to price
      if ($product_check->fields['products_priced_by_attribute'] == '1' and $product_att_query->RecordCount() >= 1) {
        while (!$product_att_query->EOF) {
          if ( $the_options_id != $product_att_query->fields['options_id']) {
            $the_options_id = $product_att_query->fields['options_id'];
            $the_base_price += $product_att_query->fields['options_values_price'];
            
          }
          $product_att_query->MoveNext();
        }

        $the_base_price = $products_price + $the_base_price;
      } else {
        $the_base_price = $products_price;
      }
      //echo $the_base_price;
      return $the_base_price;
  }
  
//get specials price or sale price
  function google_get_products_special_price($product_id, $product_price, $specials_price_only=false) {
    global $db;
    $product = $db->Execute("select products_price, products_model, products_priced_by_attribute from " . TABLE_PRODUCTS . " where products_id = '" . (int)$product_id . "'");


    $specials = $db->Execute("select specials_new_products_price from " . TABLE_SPECIALS . " where products_id = '" . (int)$product_id . "' and status='1'");
    if ($specials->RecordCount() > 0) {

        $special_price = $specials->fields['specials_new_products_price'];
    } else {
      $special_price = false;
    }

    if(substr($product->fields['products_model'], 0, 4) == 'GIFT') {    //Never apply a salededuction to Ian Wilson's Giftvouchers
      if (zen_not_null($special_price)) {
        return $special_price;
      } else {
        return false;
      }
    }

// return special price only
    if ($specials_price_only==true) {
      if (zen_not_null($special_price)) {
        return $special_price;
      } else {
        return false;
      }
    } else {
// get sale price

   $category = $product_to_categories->fields['categories_id']??'';


      $product_to_categories = $db->Execute("select master_categories_id from " . TABLE_PRODUCTS . " where products_id = '" . $product_id . "'");
      $category = $product_to_categories->fields['master_categories_id'];

      $sale = $db->Execute("select sale_specials_condition, sale_deduction_value, sale_deduction_type from " . TABLE_SALEMAKER_SALES . " where sale_categories_all like '%," . $category . ",%' and sale_status = '1' and (sale_date_start <= now() or sale_date_start = '0001-01-01') and (sale_date_end >= now() or sale_date_end = '0001-01-01') and (sale_pricerange_from <= '" . $product_price . "' or sale_pricerange_from = '0') and (sale_pricerange_to >= '" . $product_price . "' or sale_pricerange_to = '0')");
      if ($sale->RecordCount() < 1) {
         return $special_price;
      }

      if (!$special_price) {
        $tmp_special_price = $product_price;
      } else {
        $tmp_special_price = $special_price;
      }
      switch ($sale->fields['sale_deduction_type']) {
        case 0:
          $sale_product_price = $product_price - $sale->fields['sale_deduction_value'];
          $sale_special_price = $tmp_special_price - $sale->fields['sale_deduction_value'];
          break;
        case 1:
          $sale_product_price = $product_price - (($product_price * $sale->fields['sale_deduction_value']) / 100);
          $sale_special_price = $tmp_special_price - (($tmp_special_price * $sale->fields['sale_deduction_value']) / 100);
          break;
        case 2:
          $sale_product_price = $sale->fields['sale_deduction_value'];
          $sale_special_price = $sale->fields['sale_deduction_value'];
          break;
        default:
          return $special_price;
      }

      if ($sale_product_price < 0) {
        $sale_product_price = 0;
      }

      if ($sale_special_price < 0) {
        $sale_special_price = 0;
      }

      if (!$special_price) {
        return number_format($sale_product_price, 4, '.', '');
      } else {
        switch($sale->fields['sale_specials_condition']){
          case 0:
            return number_format($sale_product_price, 4, '.', '');
            break;
          case 1:
            return number_format($special_price, 4, '.', '');
            break;
          case 2:
            return number_format($sale_special_price, 4, '.', '');
            break;
          default:
            return number_format($special_price, 4, '.', '');
        }
      }
    }
  }
    function ftp_get_error_from_ob() {
      $out = ob_get_contents();
      ob_end_clean();
      $out = str_replace(array('\\', '<!--error-->', '<br>', '<br />', "\n", 'in <b>'),array('/', '', '', '', '', ''),$out);
      if(strpos($out, DIR_FS_CATALOG) !== false){
        $out = substr($out, 0, strpos($out, DIR_FS_CATALOG));
      }
      return $out;
    }

    function microtime_float() {
       list($usec, $sec) = explode(" ", microtime());
       return ((float)$usec + (float)$sec);
    }
  }
