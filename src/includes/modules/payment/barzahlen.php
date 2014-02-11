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

class barzahlen {

  const APIDOMAIN = 'https://api.barzahlen.de/v1/transactions/'; //!< call domain (productive use)
  const APIDOMAINSANDBOX = 'https://api-sandbox.barzahlen.de/v1/transactions/'; //!< sandbox call domain
  const HASHSEPARATOR = ';'; //!< hash separator for hash string
  const HASHALGORITHM = 'sha512'; //!< algorithm for hash generation

  /**
   * Constructor class, sets the settings.
   */
  function barzahlen() {

    $this->code = 'barzahlen';
    $this->version = '1.0.0';
    $this->title = MODULE_PAYMENT_BARZAHLEN_TEXT_TITLE;
    $this->description = '<div align="center">' . tep_image('http://cdn.barzahlen.de/images/barzahlen_logo.png', MODULE_PAYMENT_BARZAHLEN_TEXT_TITLE) . '</div><br>' . MODULE_PAYMENT_BARZAHLEN_TEXT_DESCRIPTION;
    $this->sort_order = MODULE_PAYMENT_BARZAHLEN_SORT_ORDER;
    $this->enabled = (MODULE_PAYMENT_BARZAHLEN_STATUS == 'True') ? true : false;
    $this->connectAttempts = 0;

    if(MODULE_PAYMENT_BARZAHLEN_SANDBOX == 'False') {
      $this->callDomain = self::APIDOMAIN.'create';
    }
    else {
      $this->callDomain = self::APIDOMAINSANDBOX.'create';
    }

    $this->cert = DIR_FS_CATALOG . 'ext/modules/payment/barzahlen/ca-bundle.crt';
    $this->logFile = DIR_FS_CATALOG . 'ext/modules/payment/barzahlen/barzahlen.log';
    $this->currencies = array('EUR');
  }

  /**
   * Settings update. Not used in this module.
   *
   * @return false
   */
  function update_status() {
    return false;
  }

  /**
   * Javascript code. Not used in this module.
   *
   * @return false
   */
  function javascript_validation() {
    return false;
  }

  /**
   * Sets information for checkout payment selection page.
   *
   * @return array with payment module information
   */
  function selection() {
    global $order;

    if(!preg_match('/^(1000(\.00?)?|\d{1,3}(\.\d\d?)?)$/', MODULE_PAYMENT_BARZAHLEN_MAXORDERTOTAL)) {
      $this->_bzLog('Maximum order amount ('.MODULE_PAYMENT_BARZAHLEN_MAXORDERTOTAL.') is not valid.'.
                    ' Should be between 0.00 and 1000.00 Euros.');
      return false;
    }

    if($order->info['total'] < MODULE_PAYMENT_BARZAHLEN_MAXORDERTOTAL && in_array($order->info['currency'], $this->currencies)) {
      $title = $this->title;
      $description = str_replace('{{image}}', tep_image('http://cdn.barzahlen.de/images/barzahlen_logo.png'), MODULE_PAYMENT_BARZAHLEN_TEXT_FRONTEND_DESCRIPTION);

      if(MODULE_PAYMENT_BARZAHLEN_SANDBOX == 'True') {
        $title .= ' [SANDBOX]';
        $description .= MODULE_PAYMENT_BARZAHLEN_TEXT_FRONTEND_SANDBOX;
      }

      $description .= MODULE_PAYMENT_BARZAHLEN_TEXT_FRONTEND_PARTNER;

      for($i = 1; $i <= 10; $i++) {
        $count = str_pad($i,2,"0",STR_PAD_LEFT);
        $description .= '<img src="http://cdn.barzahlen.de/images/barzahlen_partner_'.$count.'.png" alt="" />';
      }

      return array('id' => $this->code , 'module' => $title , 'fields' => array(array('field' => $description)));
    }
    else {
      return false;
    }
  }

  /**
   * Actions before confirmation. Not used in this module.
   *
   * @return false
   */
  function pre_confirmation_check() {
    return false;
  }

  /**
   * Payment method confirmation. Not used in this module.
   *
   * @return false
   */
  function confirmation() {
    return false;
  }

  /**
   * Module start via button. Not used in this module.
   *
   * @return false
   */
  function process_button() {
    return false;
  }

  /**
   * Actions before core process. Not used in this module.
   *
   * @return false
   */
  function before_process() {
    return false;
  }

  /**
   * Updates datasets for the new order after successful payment slip generation.
   */
  function after_process() {
    global $order, $insert_id;

    // Increase connect attempts and build transaction array.
    $this->connectAttempts++;
    $TransArray = array();
    $TransArray['shop_id'] = MODULE_PAYMENT_BARZAHLEN_SHOPID;
    $TransArray['customer_email'] = $order->customer['email_address'];
    $TransArray['amount'] = (string)round($order->info['total'], 2);
    $TransArray['currency'] = $order->info['currency'];
    $TransArray['language'] = $_SESSION['languages_code'];
    $TransArray['order_id'] = $insert_id;
    $TransArray['custom_var_0'] = '';
    $TransArray['custom_var_1'] = '';
    $TransArray['custom_var_2'] = '';
    $TransArray['paymentkey'] = MODULE_PAYMENT_BARZAHLEN_PAYMENTKEY;
    $TransArray['hash'] = $this->_getHash($TransArray);
    unset($TransArray['paymentkey']);

    // request and parse xml response from Barzahlen
    $this->_bzDebug('Sending transaction array to server - '.serialize(array($this->callDomain, $TransArray)));
    $xmlResponse = $this->_sendTransArray($TransArray);
    $this->_bzDebug('Received xml response, parsing now - '.serialize($xmlResponse));
    $xmlArray = $this->_getResponseData($xmlResponse);
    $this->_bzDebug('Finished parsing, xml array ready - '.serialize($xmlArray));
    $xmlData = array('payment-slip-link', 'infotext-1', 'infotext-2', 'expiration-notice');

    // if there's a positive answer, set session variables and insert transaction into databaste
    if($xmlArray != null) {
      $_SESSION['payment-slip-link']  = $xmlArray['payment-slip-link'];
      $_SESSION['infotext-1']  = $xmlArray['infotext-1'];
      $_SESSION['infotext-2']  = $xmlArray['infotext-2'];
      $_SESSION['expiration-notice']  = $xmlArray['expiration-notice'];

      // update order status
      tep_db_query (
        "UPDATE ". TABLE_ORDERS ."
         SET barzahlen_transaction_id = '".$xmlArray['transaction-id']."' ,
             barzahlen_transaction_state = 'pending',
             orders_status = '".MODULE_PAYMENT_BARZAHLEN_NEW_STATUS."'
         WHERE orders_id = '".$insert_id."'");

      // check if the last order history status has a comment
      $query = tep_db_query("SELECT orders_status_history_id, comments FROM ". TABLE_ORDERS_STATUS_HISTORY ."
                             WHERE orders_id = '".$insert_id."'
                             ORDER BY orders_status_history_id DESC");

      $last = tep_db_fetch_array($query);

      // set order status history with status for a new order and comment
      if($last['comments'] == '') {
        tep_db_query("UPDATE ". TABLE_ORDERS_STATUS_HISTORY ."
                      SET orders_status_id = '".MODULE_PAYMENT_BARZAHLEN_NEW_STATUS."',
                          comments = '". MODULE_PAYMENT_BARZAHLEN_TEXT_X_ATTEMPT_SUCCESS ."'
                      WHERE orders_status_history_id = '".$last['orders_status_history_id']."'");
      }
      else {
        tep_db_query("INSERT INTO ". TABLE_ORDERS_STATUS_HISTORY ."
                      (orders_id, orders_status_id, date_added, customer_notified, comments)
                      VALUES
                      ('".$insert_id."', '".MODULE_PAYMENT_BARZAHLEN_NEW_STATUS."',
                      now(), '1', '". MODULE_PAYMENT_BARZAHLEN_TEXT_X_ATTEMPT_SUCCESS ."')");
      }
    }

    // if request fails, check if a retry is possible
    else if ($this->connectAttempts < 2) {
      tep_db_query("UPDATE ". TABLE_ORDERS_STATUS_HISTORY ."
                    SET comments = '". MODULE_PAYMENT_BARZAHLEN_TEXT_FIRST_ATTEMPT_FAILED ."'
                    WHERE orders_id = '".$TransArray['order_id']."'");

      $this->after_process();
    }

    // if both attempts failed, cancel order and send user back to payment method selection
    else {
      tep_db_query("UPDATE ". TABLE_ORDERS ."
                    SET orders_status = '".MODULE_PAYMENT_BARZAHLEN_EXPIRED_STATUS."'
                    WHERE orders_id = '".$TransArray['order_id']."'");
      tep_db_query("INSERT INTO ". TABLE_ORDERS_STATUS_HISTORY ."
                    (orders_id, orders_status_id, date_added, customer_notified, comments)
                    VALUES
                    ('".$TransArray['order_id']."', '".MODULE_PAYMENT_BARZAHLEN_EXPIRED_STATUS."',
                    now(), '1', '". MODULE_PAYMENT_BARZAHLEN_TEXT_SECOND_ATTEMPT_FAILED ."')");

      foreach ($xmlData as $data) {
        if(array_key_exists($data ,$_SESSION)) {
          unset($_SESSION[$data]);
        }
      }
      tep_redirect(DIR_WS_CATALOG . FILENAME_CHECKOUT_PAYMENT . '?payment_error=barzahlen&' . session_name() . '=' . session_id());
    }
  }

  /**
   * Extracts and returns error.
   *
   * @return array with error information
   */
  function get_error() {

    $error = false;
    if (! empty($_GET['payment_error'])) {
      $error = array('title' => MODULE_PAYMENT_BARZAHLEN_TEXT_ERROR ,
                     'error' => MODULE_PAYMENT_BARZAHLEN_TEXT_PAYMENT_ERROR);
    }

    return $error;
  }

  /**
   * Error output. Not used in this module.
   *
   * @return false
   */
  function output_error() {
    return false;
  }

  /**
   * Checks if Barzahlen payment module is installed.
   *
   * @return 1 if installed, 0 if not
   */
  function check() {

    if (!isset($this->_check)) {
      $check_query = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . "
                                   WHERE configuration_key = 'MODULE_PAYMENT_BARZAHLEN_STATUS'");
      $this->_check = tep_db_num_rows($check_query);
    }
    return $this->_check;
  }

  /**
   * Install sql queries.
   */
  function install() {

    if (!defined('MODULE_PAYMENT_BARZAHLEN_TEXT_TITLE')) {
      require_once('../' . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/barzahlen.php');
    }

    tep_db_query (
      "INSERT INTO ". TABLE_CONFIGURATION ."
      (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
      VALUES
      ('".MODULE_PAYMENT_BARZAHLEN_STATUS_TITLE."', 'MODULE_PAYMENT_BARZAHLEN_STATUS', 'False', '".MODULE_PAYMENT_BARZAHLEN_STATUS_DESC."', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now()),
      ('".MODULE_PAYMENT_BARZAHLEN_SANDBOX_TITLE."', 'MODULE_PAYMENT_BARZAHLEN_SANDBOX', 'True', '".MODULE_PAYMENT_BARZAHLEN_SANDBOX_DESC."', '6', '2', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now()),
      ('".MODULE_PAYMENT_BARZAHLEN_DEBUG_TITLE."', 'MODULE_PAYMENT_BARZAHLEN_DEBUG', 'False', '".MODULE_PAYMENT_BARZAHLEN_DEBUG_DESC."', '6', '12', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");

    tep_db_query (
      "INSERT INTO ". TABLE_CONFIGURATION ."
      (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
      VALUES
      ('".MODULE_PAYMENT_BARZAHLEN_ALLOWED_TITLE."', 'MODULE_PAYMENT_BARZAHLEN_ALLOWED', 'DE', '".MODULE_PAYMENT_BARZAHLEN_ALLOWED_DESC."', '6', '0', now()),
      ('".MODULE_PAYMENT_BARZAHLEN_SHOPID_TITLE."', 'MODULE_PAYMENT_BARZAHLEN_SHOPID', '', '".MODULE_PAYMENT_BARZAHLEN_SHOPID_DESC."', '6', '3', now()),
      ('".MODULE_PAYMENT_BARZAHLEN_PAYMENTKEY_TITLE."', 'MODULE_PAYMENT_BARZAHLEN_PAYMENTKEY', '', '".MODULE_PAYMENT_BARZAHLEN_PAYMENTKEY_DESC."', '6', '4', now()),
      ('".MODULE_PAYMENT_BARZAHLEN_NOTIFICATIONKEY_TITLE."', 'MODULE_PAYMENT_BARZAHLEN_NOTIFICATIONKEY', '', '".MODULE_PAYMENT_BARZAHLEN_NOTIFICATIONKEY_DESC."', '6', '5', now()),
      ('".MODULE_PAYMENT_BARZAHLEN_MAXORDERTOTAL_TITLE."', 'MODULE_PAYMENT_BARZAHLEN_MAXORDERTOTAL', '1000', '".MODULE_PAYMENT_BARZAHLEN_MAXORDERTOTAL_DESC."', '6', '6', now()),
      ('".MODULE_PAYMENT_BARZAHLEN_SORT_ORDER_TITLE."', 'MODULE_PAYMENT_BARZAHLEN_SORT_ORDER', '-1', '".MODULE_PAYMENT_BARZAHLEN_SORT_ORDER_DESC."', '6', '11', now())");

    tep_db_query (
      "INSERT INTO ".TABLE_CONFIGURATION."
      (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
      VALUES
      ('".MODULE_PAYMENT_BARZAHLEN_NEW_STATUS_TITLE."', 'MODULE_PAYMENT_BARZAHLEN_NEW_STATUS', '0', '".MODULE_PAYMENT_BARZAHLEN_NEW_STATUS_DESC."', '6', '8', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now()),
      ('".MODULE_PAYMENT_BARZAHLEN_PAID_STATUS_TITLE."', 'MODULE_PAYMENT_BARZAHLEN_PAID_STATUS', '0', '".MODULE_PAYMENT_BARZAHLEN_PAID_STATUS_DESC."', '6', '9', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now()),
      ('".MODULE_PAYMENT_BARZAHLEN_EXPIRED_STATUS_TITLE."', 'MODULE_PAYMENT_BARZAHLEN_EXPIRED_STATUS', '0', '".MODULE_PAYMENT_BARZAHLEN_EXPIRED_STATUS_DESC."', '6', '10', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");

    $query = tep_db_query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE table_name = '".TABLE_ORDERS."'
                             AND table_schema = '".DB_DATABASE."'
                             AND column_name = 'barzahlen_transaction_id'");

    if(tep_db_num_rows($query) == 0) {
      tep_db_query("ALTER TABLE `".TABLE_ORDERS."` ADD `barzahlen_transaction_id` int(11) NOT NULL default '0';");
    }

    $query = tep_db_query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE table_name = '".TABLE_ORDERS."'
                             AND table_schema = '".DB_DATABASE."'
                             AND column_name = 'barzahlen_transaction_state'");

    if(tep_db_num_rows($query) == 0) {
      tep_db_query("ALTER TABLE `".TABLE_ORDERS."` ADD `barzahlen_transaction_state` varchar(7) NOT NULL default '';");
    }
  }

  /**
   * Uninstall sql queries.
   */
  function remove() {

    $parameters = $this->keys();
    $parameters[] = 'MODULE_PAYMENT_BARZAHLEN_ALLOWED';
    tep_db_query("DELETE FROM ". TABLE_CONFIGURATION ." WHERE configuration_key IN ('". implode("', '", $parameters) ."')");
  }

  /**
   * All necessary configuration attributes for the payment module.
   *
   * @return array with configuration attributes
   */
  function keys() {

    return array('MODULE_PAYMENT_BARZAHLEN_STATUS',
                 'MODULE_PAYMENT_BARZAHLEN_SANDBOX',
                 'MODULE_PAYMENT_BARZAHLEN_SHOPID',
                 'MODULE_PAYMENT_BARZAHLEN_PAYMENTKEY',
                 'MODULE_PAYMENT_BARZAHLEN_NOTIFICATIONKEY',
                 'MODULE_PAYMENT_BARZAHLEN_MAXORDERTOTAL',
                 'MODULE_PAYMENT_BARZAHLEN_NEW_STATUS',
                 'MODULE_PAYMENT_BARZAHLEN_PAID_STATUS',
                 'MODULE_PAYMENT_BARZAHLEN_EXPIRED_STATUS',
                 'MODULE_PAYMENT_BARZAHLEN_SORT_ORDER',
                 'MODULE_PAYMENT_BARZAHLEN_DEBUG');
  }

  /**
   * Generates the sha512 hash out of the transaction array.
   *
   * @param array $array transaction array
   * @return sha512 hash
   */
  function _getHash(array $array) {
    $HashString = implode(self::HASHSEPARATOR, $array);
    return hash(self::HASHALGORITHM, $HashString);
  }

  /**
   * Sends the build transaction array to the Barzahlen server.
   *
   * @param array $TransArray build transaction array
   * @return string with xml answer
   * @return null, if an error occurred
   */
  function _sendTransArray(array $TransArray) {
    try {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $this->callDomain);
      curl_setopt($ch, CURLOPT_POST, count($TransArray));
      curl_setopt($ch, CURLOPT_POSTFIELDS, $TransArray);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
      curl_setopt($ch, CURLOPT_CAINFO, $this->cert);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 7);
      curl_setopt($ch, CURLOPT_HTTP_VERSION, 1.1);
      $return = curl_exec($ch);
      curl_close($ch);
      return $return;
    }
    catch(Exception $e) {
      $this->_bzLog($e);
      return null;
    }
  }

  /**
   * Extracts the data out of the xml answer and verfies them.
   *
   * @param string $xmlResponse received xml answer
   * @return string with error-message, if an error occured
   * @return null if data is not valid
   * @return array with received and valid data
   */
  function _getResponseData($xmlResponse) {

    try {

      $simpleXML = new SimpleXMLElement($xmlResponse);

      if($simpleXML->{'result'} != 0) {
        $this->_bzLog($simpleXML->{'error-message'});
        return null;
      }

      $nodes = array('transaction-id', 'payment-slip-link', 'expiration-notice', 'infotext-1', 'infotext-2', 'result', 'hash');
      $xmlArray = array();
      foreach ($nodes as $node) {
        $xmlArray[$node] = (string)$simpleXML->{$node};
      }
    }
    catch(Exception $e) {
      $this->_bzLog($e);
      return null;
    }

    if(!$this->_verifyHash($xmlArray)) {
      $this->_bzLog('Hash not valid - ' . serialize($xmlArray));
      return null;
    }

    return $xmlArray;
  }

  /**
   * Verifies that the hash and therefore the xml answer is valid.
   *
   * @param array $xmlArray extracted xml data
   * @return boolean (TRUE if hash is valid, FALSE if not)
   */
  function _verifyHash(array $xmlArray) {

    $responseHash = $xmlArray['hash'];
    unset($xmlArray['hash']);
    $xmlArray['paymentkey'] = MODULE_PAYMENT_BARZAHLEN_PAYMENTKEY;
    $generatedHash = $this->_getHash($xmlArray);

    return $responseHash == $generatedHash;
  }

  /**
   * Logs errors into Barzahlen log file.
   *
   * @param string $message error message
   */
  function _bzLog($message) {
    $time = date("[Y-m-d H:i:s] ");
    error_log($time . $message . "\r\r", 3, $this->logFile);
  }

  /**
   * Writes transaction steps into Barzahlen log file, if enabled.
   *
   * @param string $message debug message
   */
  function _bzDebug($message) {
    if(MODULE_PAYMENT_BARZAHLEN_DEBUG == 'True') {
      $time = date("[Y-m-d H:i:s] ");
      error_log($time. $message . "\r\r", 3, $this->logFile);
    }
  }
}
?>