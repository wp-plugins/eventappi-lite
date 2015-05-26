<?php
/*
 * Plugin Name: EventAppi LITE - Happy Event Management
 * Plugin URI: http://eventappi.com/
 * Version: 1.0.7
 * Description: Ticketing and Event Management For The Win
 * Author: EventAppi Development Team
 * Author URI: http://www.eventappi.com
 * Text Domain: eventappi
 * Domain Path: /lang/
 *
 * Requires at least: 4.0
 * Tested up to: 4.1.1
 *
 * Copyright (c) 2014-2015 EventAppi  All Rights Reserved.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

//Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/.env.php')) {
    include_once(__DIR__ . '/.env.php');

    if ($eventAppiEnv['error_reporting'] === true) {
        // Enable WP_DEBUG mode
        define('WP_DEBUG', true);

        // Enable Debug logging to the /wp-content/debug.log file
        define('WP_DEBUG_LOG', true);

        error_reporting($eventAppiEnv['error_reporting_level']);
        ini_set('display_errors', 1);
    } else {
        error_reporting(0);
        ini_set('display_errors', 0);
    }
    unset($eventAppiEnv['error_reporting']);
    unset($eventAppiEnv['error_reporting_level']);

    foreach ($eventAppiEnv as $envKey => $envVal) {
        putenv("{$envKey}={$envVal}");
    }
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

if (!function_exists('eventappi_version')) {
    // Load composer libraries and init PSR-4 autoload
    require(__DIR__ . '/vendor/autoload.php');

    /**
     * @return string
     */
    function eventappi_version()
    {
        return '1.0.7';
    }
}

use EventAppi\ClassLoader as ClassLoader;

// make sure WP_CONTENT_DIR is defined
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

// some useful constants for paths etc.
define('EVENTAPPI_PLUGIN_NAME', 'eventappi');
define('EVENTAPPI_PLUGIN_VERSION', eventappi_version());
define('EVENTAPPI_PLUGIN_PATH', '/' . plugin_basename(dirname(__FILE__)) . '/');
define('EVENTAPPI_PLUGIN_FULL_PATH', WP_PLUGIN_DIR . EVENTAPPI_PLUGIN_PATH);
define('EVENTAPPI_PLUGIN_FILE_ABS', __FILE__);
define('EVENTAPPI_PLUGIN_DIR_ABS', __DIR__);

define('EVENTAPPI_WPRESS_PLUGIN_PATH', plugin_basename(__FILE__));

define('EVENTAPPI_POST_NAME', EVENTAPPI_PLUGIN_NAME.'_event');


$wp_plugin_url  = WP_PLUGIN_URL;
$wp_content_url = WP_CONTENT_URL;

$upload_path          = WP_CONTENT_DIR . '/uploads';
$eventappi_upload_dir = "{$upload_path}/" . EVENTAPPI_PLUGIN_NAME . "/";

define('EVENTAPPI_UPLOAD_DIR', $eventappi_upload_dir);
define('EVENTAPPI_PLUGIN_FULL_URL', $wp_plugin_url . EVENTAPPI_PLUGIN_PATH);
define('EVENTAPPI_PLUGIN_ASSETS_PATH', EVENTAPPI_PLUGIN_FULL_PATH . 'assets/');
define('EVENTAPPI_PLUGIN_ASSETS_URL', EVENTAPPI_PLUGIN_FULL_URL . 'assets/');
define('EVENTAPPI_PLUGIN_TEMPLATE_PATH', EVENTAPPI_PLUGIN_FULL_PATH . 'templates/');

ClassLoader::instance()->load();
