<?php
/**
 * Barzahlen Payment Module (osCommerce)
 *
 * NOTICE OF LICENSE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the License
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @copyright   Copyright (c) 2012 Zerebro Internet GmbH (http://www.barzahlen.de)
 * @author      Alexander Diebler
 * @license     http://opensource.org/licenses/GPL-2.0  GNU General Public License, version 2 (GPL-2.0)
 */

class bzSuccess {

  var $result;

  /**
   * Constructor which loads payment method and looks if redirect is in order.
   *
   * @param string $order_id last order id
   */
  function bzSuccess($order_id) {

    $query = tep_db_query("SELECT payment_method FROM ". TABLE_ORDERS ."
                           WHERE orders_id = '". $order_id ."'");

    $this->result = tep_db_fetch_array($query);

    if ($this->result['payment_method'] == 'Barzahlen' && !isset($_SESSION['payment-slip-link'])) {
      tep_redirect(DIR_WS_CATALOG . FILENAME_ACCOUNT_HISTORY_INFO . '?order_id=' . $order_id);
    }
  }

  /**
   * Outputs html code for payment slip link.
   */
  function getPaymentSlip() {

    require_once(DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/barzahlen.php');

    if ($this->result['payment_method'] == 'Barzahlen' && isset($_SESSION['payment-slip-link'])) {

      echo '<iframe src="'.$_SESSION['payment-slip-link'].'" width="0" height="1" frameborder="0"></iframe>
            <img src="http://cdn.barzahlen.de/images/barzahlen_logo.png" height="57" width="168" alt="" style="padding:0; margin:0; margin-bottom: 10px;"/>
            <hr/>
            <br/>
            <div style="width:100%;">
              <div style="position: relative; float: left; width: 180px; text-align: center;">
                <a href="'.$_SESSION['payment-slip-link'].'" target="_blank" style="color: #63A924; text-decoration: none; font-size: 1.2em;">
                  <img src="http://cdn.barzahlen.de/images/barzahlen_checkout_success_payment_slip.png" height="192" width="126" alt="" style="margin-bottom: 5px;"/><br/>
                  <strong>Download PDF</strong>
                </a>
              </div>
              <span style="font-weight: bold; color: #63A924; font-size: 1.5em;">'.MODULE_PAYMENT_BARZAHLEN_TEXT_FRONTEND_SUCCESS_TITLE.'</span>
              <p>'.$_SESSION['infotext-1'].'</p>
              <p>'.$_SESSION['expiration-notice'].'</p>
              <div style="width:100%;">
                <div style="position: relative; float: left; width: 50px;"><img src="http://cdn.barzahlen.de/images/barzahlen_mobile.png" height="52" width="41" alt="" style="float: left;"/></div>
                <p>'.$_SESSION['infotext-2'].'</p>
              </div>
              <br style="clear:both;" /><br/>
            </div>
            <hr/><br/>';

      unset($_SESSION['payment-slip-link']);
      unset($_SESSION['infotext-1']);
      unset($_SESSION['infotext-2']);
      unset($_SESSION['expiration-notice']);
    }
  }
}
?>