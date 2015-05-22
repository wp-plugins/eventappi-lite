<?php namespace EventAppi;

use EventAppi\Helpers\AdminBar;
use EventAppi\Helpers\Options;
use EventAppi\Helpers\Session;

/**
 * Class ClassLoader
 *
 * @package EventAppi
 */
class ClassLoader
{

    /**
     * @var ClassLoader|null
     */
    private static $singleton = null;

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return ClassLoader|null
     */
    public static function instance()
    {
        if (is_null(self::$singleton)) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

    public function load()
    {
        if (array_key_exists('page', $_GET) && $_GET['page'] === EVENTAPPI_PLUGIN_NAME . '-download-attendees') {
            /**
             * Go directly to the export function - there is no need to allow WordPress to
             * load any further at this point - simply serve up the requested CSV and be done.
             *
             * @see EventPostType::attendeesExport()
             */
            EventPostType::instance()->attendeesExport();
        }

        Parser::instance()->init();
        PluginManager::instance()->init();

        Settings::instance()->init();

        EventPostType::instance()->init();


        LicenseKeyManager::instance()->init();

        Session::instance()->init();
        Options::instance()->init();

        Shortcodes::instance()->init();

        AdminBar::instance()->init();
        User::instance()->init();
        Analytics::instance()->init();

    }
}
