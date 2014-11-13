<?php
/**
 * Barzahlen Payment Module (osCommerce)
 *
 * @copyright   Copyright (c) 2014 Cash Payment Solutions GmbH (https://www.barzahlen.de)
 * @author      Alexander Diebler
 * @license     http://opensource.org/licenses/GPL-2.0  GNU General Public License, version 2 (GPL-2.0)
 */

require_once('src/includes/languages/german/modules/payment/barzahlen.php');
$_SESSION['languages_id'] = '1';
$_SESSION['language'] = 'german';
$_SESSION['payment_method_messages'] = '';
define('DEFAULT_LANGUAGE', 'de');

define('DIR_FS_CATALOG', 'src/');
define('DIR_WS_CATALOG', '../');
define('DIR_WS_LANGUAGES', 'includes/languages/');
define('FILENAME_CHECKOUT_PAYMENT', '');
define('DB_HOST', 'localhost');
define('DB_USER', 'oscommerce');
define('DB_PASSWORD', 'oscommerce');
define('DB_DATABASE', 'oscommerce_copy');

define('TABLE_CONFIGURATION', 'configuration');
define('TABLE_LANGUAGES', 'languages');
define('TABLE_ORDERS', 'orders');
define('TABLE_ORDERS_STATUS_HISTORY', 'orders_status_history');
define('TABLE_ORDERS_TOTAL', 'orders_total');

define('MODULE_PAYMENT_BARZAHLEN_SANDBOX', 'True');
define('MODULE_PAYMENT_BARZAHLEN_DEBUG', 'True');
define('MODULE_PAYMENT_BARZAHLEN_STATUS', 'True');
define('MODULE_PAYMENT_BARZAHLEN_NEW_STATUS', '1');
define('MODULE_PAYMENT_BARZAHLEN_PAID_STATUS', '2');
define('MODULE_PAYMENT_BARZAHLEN_EXPIRED_STATUS', '3');
define('MODULE_PAYMENT_BARZAHLEN_ALLOWED', 'DE');
define('MODULE_PAYMENT_BARZAHLEN_SHOPID', '10003');
define('MODULE_PAYMENT_BARZAHLEN_PAYMENTKEY', '20a7e7235b2de0e0fda66ff8ae06665fb2470b69');
define('MODULE_PAYMENT_BARZAHLEN_NOTIFICATIONKEY', '20bc75e9ca4b72f4b216bf623299295a5a814541');
define('MODULE_PAYMENT_BARZAHLEN_MAXORDERTOTAL', '999.99');
define('MODULE_PAYMENT_BARZAHLEN_SORT_ORDER', '0');

define('SHOPID', '10483');
define('PAYMENTKEY', 'de74310368a4718a48e0e244fbf3e22e2ae117f2');
define('NOTIFICATIONKEY', 'e5354004de1001f86004090d01982a6e05da1c12');

/**
 * DB-Handler
 */
class db_handler
{
    /**
     * Sets up database before every test.
     */
    public function __construct()
    {
        exec('mysql -h' . DB_HOST . ' -u' . DB_USER . ' --password=' . DB_PASSWORD . ' ' . DB_DATABASE . '< tests/oscommerce_copy.sql');

        mysql_connect('localhost', DB_USER, DB_PASSWORD);
        mysql_select_db(DB_DATABASE);
    }

    /**
     * Removes all database data after a test.
     */
    public function __destruct()
    {
        mysql_query("TRUNCATE TABLE " . TABLE_CONFIGURATION);
        mysql_query("TRUNCATE TABLE " . TABLE_ORDERS);
        mysql_query("TRUNCATE TABLE " . TABLE_ORDERS_STATUS_HISTORY);
        mysql_query("TRUNCATE TABLE " . TABLE_ORDERS_TOTAL);
        mysql_close();
    }
}

/**
 * osCommerce DB functions
 */
function tep_db_query($query)
{
    return mysql_query($query);
}

function tep_db_num_rows($query)
{
    return mysql_num_rows($query);
}

function tep_db_fetch_array($query)
{
    return mysql_fetch_array($query);
}

function tep_db_perform($table, $array)
{
    $keys = "";
    $values = "";
    foreach($array as $key => $value) {
        $keys .= $key . ", ";
        $values .= "'" . $value . "', ";
    }
    $query = "INSERT INTO " . $table . " (" . substr($keys, 0, -2) . ") VALUES (" . substr($values, 0, -2) . ");";

    return mysql_query($query);
}

/**
 * other osCommerce methods
 */
function tep_image($path)
{
    return $path;
}

function tep_redirect($path)
{
    return $path;
}

function tep_href_link($file, $settings, $ssl)
{
    return $file;
}

function tep_get_version()
{
    return '2.3.1';
}

/**
 * Fake order.
 */
class order
{
    var $customer = array();
    var $info = array();
    var $billing = array(array());

    function order($id)
    {
        switch ($id) {
            case '5':
                $this->customer['email_address'] = 'foo@bar.com';
                $this->info['total'] = '122.07';
                $this->info['currency'] = 'EUR';
                $this->customer['street_address'] = 'Musterstr. 1a';
                $this->customer['postcode'] = '12345';
                $this->customer['city'] = 'Musterstadt';
                $this->customer['country'] = array('iso_code_2' => 'DE');
                $this->billing['country']['iso_code_2'] = 'de';
            default:
                break;
        }
    }
}

global $order;
$order = new order('5');
global $insert_id;
$insert_id = '5';
