<?php namespace EventAppi\Helpers;

use WP_User;

/**
 * Class AdminBar
 *
 * @package EventAppi\Helpers
 */
class AdminBar
{

    /**
     * @var AdminBar|null
     */
    private static $singleton = null;

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return AdminBar|null
     */
    public static function instance()
    {
        if (is_null(self::$singleton)) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

    /**
     * Initialise the demo settings
     */
    public function init()
    {
        add_action('after_setup_theme', array($this, 'hideAdminBar'));
    }

    /**
     * No admin bar for organiser and attendee
     */
    public function hideAdminBar()
    {
        $userdata = wp_get_current_user();
        $user     = new WP_User($userdata->ID);
        if (! empty($user->roles) &&
             is_array($user->roles) &&
             ($user->roles[0] === 'event_organiser' || $user->roles[0] === 'attendee')) {
            show_admin_bar(false);
        }
    }
}
