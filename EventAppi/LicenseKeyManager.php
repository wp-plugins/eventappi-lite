<?php namespace EventAppi;

use EventAppi\Helpers\Logger;
use EventAppi\Helpers\Options;

/**
 * Class LicenseKeyManager
 *
 * @package EventAppi
 */
class LicenseKeyManager
{

    /**
     * @var null
     */
    private static $singleton = null;

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return LicenseKeyManager|null
     */
    public static function instance()
    {
        if (is_null(self::$singleton)) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

    public function init()
    {
        add_action('init', array($this, 'checkLicenseKey'));
    }

    /**
     * @return bool
     */
    private function keyCheckRequired()
    {
        $checkPoint = Options::instance()->getPluginOption('license_key_checkpoint');
        if ($checkPoint === false || (((time() - $checkPoint) / 60) >= 10)) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function checkLicenseKey()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $options = Options::instance();

        $licenseKey = $options->getPluginOption('license_key');
        if ($licenseKey === false) {
            return false;
        }

        $apiEndpoint = $options->getPluginOption('api_endpoint');
        if ($apiEndpoint === false) {
            return false;
        }

        if (! $this->keyCheckRequired()) {
            return (bool) $options->getPluginOption('license_key_status');
        }

        Logger::instance()->log(__FILE__, __FUNCTION__, __('Check License Key on the API.', EVENTAPPI_PLUGIN_NAME), Logger::LOG_LEVEL_INFO);

        $apiClient = ApiClient::instance();
        $apiClient->setApiEndpoint($apiEndpoint);
        $result = $apiClient->checkEventAppiLicenseKey($licenseKey);

        if (isset($result['data']['status']) && $result['data']['status'] === 'active') {
            $keyType = $result['data']['tier'];
            $status  = $result['data']['status'];

            $options->setPluginOption('license_key_checkpoint', time());
            $options->setPluginOption('license_key_status', $status);
            $options->setPluginOption('license_key_type', $keyType);

            return true;
        }

        return false;
    }
}
