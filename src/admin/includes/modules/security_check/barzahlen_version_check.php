<?php
/**
 * Barzahlen Payment Module (osCommerce)
 *
 * @copyright   Copyright (c) 2015 Cash Payment Solutions GmbH (https://www.barzahlen.de)
 * @author      Mathias Hertlein
 * @author      Alexander Diebler
 * @license     http://opensource.org/licenses/GPL-2.0  GNU General Public License, version 2 (GPL-2.0)
 */

class securityCheck_barzahlen_version_check
{
    public $type = 'warning';
    private $barzahlenNewestVersion;
    private $barzahlenNewestVersionUrl;

    public function pass()
    {
        require_once(DIR_FS_ADMIN . "/includes/modules/barzahlen/BarzahlenHttpClient.php");
        require_once(DIR_FS_ADMIN . "/includes/modules/barzahlen/BarzahlenPluginCheckRequest.php");
        require_once(DIR_FS_ADMIN . "/includes/modules/barzahlen/BarzahlenVersionCheck.php");

        $httpClient = new BarzahlenHttpClient();
        $barzahlenVersionCheckRequest = new BarzahlenPluginCheckRequest($httpClient);
        $barzahlenVersionCheck = new BarzahlenVersionCheck($barzahlenVersionCheckRequest);

        try {
            if (MODULE_PAYMENT_BARZAHLEN_STATUS == "True" && !$barzahlenVersionCheck->isCheckedInLastWeek()) {
                $barzahlenVersionCheck->check(MODULE_PAYMENT_BARZAHLEN_SHOPID, tep_get_version());
                $displayUpdateAvailableMessage = $barzahlenVersionCheck->isNewVersionAvailable();
                $this->barzahlenNewestVersion = $barzahlenVersionCheck->getNewestVersion();
                $this->barzahlenNewestVersionUrl = $barzahlenVersionCheck->getNewestVersionUrl();
            } else {
                $displayUpdateAvailableMessage = false;
            }
        } catch (Exception $e) {
            error_log('barzahlen/versioncheck: ' . $e, 3, DIR_FS_CATALOG . 'logfiles/barzahlen.log');
            $displayUpdateAvailableMessage = false;
        }

        return !$displayUpdateAvailableMessage;
    }

    public function getMessage()
    {
        global $language;
        require_once(DIR_FS_CATALOG_LANGUAGES . $language . "/modules/payment/barzahlen.php");

        return sprintf(MODULE_PAYMENT_BARZAHLEN_NEW_VERSION, $this->barzahlenNewestVersion, $this->barzahlenNewestVersionUrl);
    }
}

?>
