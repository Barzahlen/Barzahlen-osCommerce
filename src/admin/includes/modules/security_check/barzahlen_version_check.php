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
 * @copyright   Copyright (c) 2013 Zerebro Internet GmbH (http://www.barzahlen.de)
 * @author      Mathias Hertlein
 * @license     http://opensource.org/licenses/GPL-2.0  GNU General Public License, version 2 (GPL-2.0)
 */

class securityCheck_barzahlen_version_check
{
    public $type = 'warning';
    private $barzahlenNewestVersion;

    public function pass()
    {
        require_once(DIR_FS_ADMIN . "/includes/modules/barzahlen/BarzahlenHashCreate.php");
        require_once(DIR_FS_ADMIN . "/includes/modules/barzahlen/BarzahlenHttpClient.php");
        require_once(DIR_FS_ADMIN . "/includes/modules/barzahlen/BarzahlenPluginCheckRequest.php");
        require_once(DIR_FS_ADMIN . "/includes/modules/barzahlen/BarzahlenVersionCheck.php");
        require_once(DIR_FS_ADMIN . "/includes/modules/barzahlen/BarzahlenConfigRepository.php");

        $httpClient = new BarzahlenHttpClient();
        $barzahlenVersionCheckRequest = new BarzahlenPluginCheckRequest($httpClient);
        $barzahlenRepository = new BarzahlenConfigRepository();

        $barzahlenVersionCheck = new BarzahlenVersionCheck($barzahlenVersionCheckRequest, $barzahlenRepository);

        try {
            if (MODULE_PAYMENT_BARZAHLEN_STATUS == "True" && !$barzahlenVersionCheck->isCheckedInLastWeek(time())) {
                $barzahlenVersionCheck->check(MODULE_PAYMENT_BARZAHLEN_SHOPID, MODULE_PAYMENT_BARZAHLEN_PAYMENTKEY, tep_get_version());
                $displayUpdateAvailableMessage = $barzahlenVersionCheck->isNewVersionAvailable();
                $this->barzahlenNewestVersion = $barzahlenVersionCheck->getNewestVersion();
            } else {
                $displayUpdateAvailableMessage = false;
            }
        } catch (Exception $e) {
            $displayUpdateAvailableMessage = false;
        }

        return !$displayUpdateAvailableMessage;
    }

    public function getMessage()
    {
        require_once(DIR_FS_CATALOG_LANGUAGES . $_SESSION['language'] . "/modules/payment/barzahlen.php");

        return sprintf(MODULE_PAYMENT_BARZAHLEN_NEW_VERSION, $this->barzahlenNewestVersion);
    }
}

?>
