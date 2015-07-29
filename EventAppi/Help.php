<?php
namespace EventAppi;

/**
 * Class Help
 *
 * @package EventAppi
 */
class Help
{
    /**
     * @var Help|null
     */
    private static $singleton = null;

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return Help|null
     */
    public static function instance()
    {
        if (is_null(self::$singleton)) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

    public function helpPage()
    {
        if (!current_user_can('manage_' . EVENTAPPI_PLUGIN_NAME)) {
            wp_die(__('You do not have sufficient permissions to access this page.', EVENTAPPI_PLUGIN_NAME));
        }

        echo Parser::instance()->parseEventAppiTemplate('PluginHelp');
    }
}
