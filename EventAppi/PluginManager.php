<?php namespace EventAppi;

use EventAppi\Helpers\Logger;

/**
 * Class PluginManager
 *
 * @package EventAppi
 */
class PluginManager
{

    /**
     * @var PluginManager|null
     */
    private static $singleton = null;

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return PluginManager|null
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
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(EVENTAPPI_PLUGIN_FILE_ABS), [$this, 'addSettingsLink']);

        add_action('plugins_loaded', array($this, 'loadLocalisation'));

        register_activation_hook(EVENTAPPI_PLUGIN_FILE_ABS, array($this, 'activate'));
        register_deactivation_hook(EVENTAPPI_PLUGIN_FILE_ABS, array($this, 'deactivate'));
    }

    /**
     * Load plugin localisation
     *
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function loadLocalisation()
    {
        load_plugin_textdomain(
            EVENTAPPI_PLUGIN_NAME,
            false,
            dirname(plugin_basename(EVENTAPPI_PLUGIN_FILE_ABS)) . '/lang/'
        );
    }

    /**
     * Add settings link to plugin list table
     *
     * @param  array $links Existing links
     *
     * @return array        Modified links
     */
    public function addSettingsLink($links)
    {
        array_unshift($links, '<a href="edit.php?post_type=eventappi_event&page=' . EVENTAPPI_PLUGIN_NAME .
                              '-settings">' . __('Settings', EVENTAPPI_PLUGIN_NAME) . '</a>');

        return $links;
    }

    /**
     * Activate our plugin
     */
    public function activate()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        EventPostType::instance()->createPostType();

        $this->checkCompatibility();

        $this->createPages();
        $this->createRoles();
        $this->createDatabase();
        $this->checkForUpgradeChanges();

        flush_rewrite_rules();
    }

    /**
     * Deactivate our plugin
     */
    public function deactivate()
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'posts';
        $sql       = <<<DEACSQL
DELETE FROM {$tableName} WHERE post_title LIKE 'EventAppi %' AND post_type = 'page'
DEACSQL;
        $wpdb->query($sql);

        $tableName = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_cart';
        $sql       = <<<DEACSQL
DROP TABLE {$tableName}
DEACSQL;
        $wpdb->query($sql);

        flush_rewrite_rules();
    }

    /**
     * Check that the system we're installing into can handle the majesty
     */
    private function checkCompatibility()
    {
        // First we check the PHP version - we need >= 5.4
        if ( ! version_compare(phpversion(), '5.4.0', '>=')) {
            // no - we don't have what it takes
            $this->pluginActivationError('PHP version >= 5.4', phpversion());
        }

        // We should also verify the WordPress version
        if ( ! version_compare($GLOBALS['wp_version'], '4.0', '>=')) {
            $this->pluginActivationError('WordPress version >= 4.0', $GLOBALS['wp_version']);
        }

        // Also check extensions. We need MCrypt
        if ( ! extension_loaded('MCrypt')) {
            $this->pluginActivationError('MCrypt', null);
        }
    }

    /**
     * WP Die with activation error message
     * Print an activation message and abort / reverse the activation
     *
     * @param $require : The thing we require that currently prevents activation
     * @param $actual  : The thing we currently have instead
     */
    private function pluginActivationError($require, $actual)
    {
        Logger::instance()->log(
            __FILE__,
            __FUNCTION__,
            'Activation failed because we require ' . $require,
            Logger::LOG_LEVEL_ERROR
        );

        if (is_null($actual)) {
            $actual = __('nothing', EVENTAPPI_PLUGIN_NAME);
        }
        $data = get_plugin_data(EVENTAPPI_PLUGIN_FILE_ABS, false, true);
        deactivate_plugins(plugin_basename(__DIR__));
        wp_die(
            "<h2>{$data['Name']}</h2><p>This plugin <strong>requires</strong> {$require}.</p>" .
            "<p>The plugin requires {$require}, but you have {$actual}.</p>" .
            "<p><small>Plugin version: {$data['Version']}</small></p>",
            'Plugin Activation Error',
            array('response' => 200, 'back_link' => true)
        );
    }

    /**
     * Creates Pages for Plugin use
     *
     * @return void
     */
    private function createPages()
    {
        $customPages = array(
            array(
                'post_title'   => 'EventAppi Cart',
                'post_content' => '[' . EVENTAPPI_PLUGIN_NAME . '_cart]'
            ),
            array(
                'post_title'   => 'EventAppi Checkout',
                'post_content' => '[' . EVENTAPPI_PLUGIN_NAME . '_checkout]'
            ),
            array(
                'post_title'   => 'EventAppi Analytics',
                'post_content' => '[' . EVENTAPPI_PLUGIN_NAME . '_analytics]'
            ),
            array(
                'post_title'   => 'EventAppi Login',
                'post_content' => '[' . EVENTAPPI_PLUGIN_NAME . '_login]'
            ),
            array(
                'post_title'   => 'EventAppi My Account',
                'post_content' => '[' . EVENTAPPI_PLUGIN_NAME . '_my_account]'
            )
        );

        foreach ($customPages as $page) {
            $this->createPage($page);
        }
    }

    /**
     * @param $data - the data we need to create the page
     */
    private function createPage($data)
    {
        if ( ! get_page_by_title($data['post_title'])) {

            // we override a few settings to be sure the pages we need are in place
            $data['post_name']      = strtolower(str_replace(' ', '-', $data['post_title']));
            $data['post_status']    = 'publish';
            $data['post_author']    = 1;
            $data['post_type']      = 'page';
            $data['post_category']  = array();
            $data['comment_status'] = 'closed';

            wp_insert_post($data);
        }
    }

    public function getPageId($pageSlug)
    {
        $posts = get_posts([
            'name'           => $pageSlug,
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 1
        ]);

        return get_page_link($posts[0]->ID);
    }

    /**
     * Create roles and capabilities
     */
    private function createRoles()
    {
        global $wp_roles;

        if (class_exists('WP_Roles')) {
            if ( ! isset($wp_roles)) {
                $wp_roles = new WP_Roles();
            }
        }

        if (is_object($wp_roles)) {

            // Attendee role
            add_role('attendee', __('Attendee', EVENTAPPI_PLUGIN_NAME), array(
                'read'         => true,
                'edit_posts'   => false,
                'delete_posts' => false
            ));

            // Event Organiser role
            add_role('event_organiser', __('Event Organiser', EVENTAPPI_PLUGIN_NAME), array(
                'manage_network'                          => false,
                'manage_sites'                            => false,
                'manage_network_users'                    => false,
                'manage_network_plugins'                  => false,
                'manage_network_themes'                   => false,
                'manage_network_options'                  => false,
                'unfiltered_html'                         => false,
                'activate_plugins'                        => false,
                'create_users'                            => false,
                'delete_plugins'                          => false,
                'delete_themes'                           => false,
                'delete_users'                            => false,
                'edit_files'                              => true,
                'edit_plugins'                            => false,
                'edit_theme_options'                      => false,
                'edit_themes'                             => false,
                'edit_users'                              => false,
                'export'                                  => false,
                'import'                                  => false,
                'install_plugins'                         => false,
                'install_themes'                          => false,
                'list_users'                              => true,
                'manage_options'                          => false,
                'promote_users'                           => false,
                'remove_users'                            => false,
                'switch_themes'                           => false,
                'update_core'                             => false,
                'update_plugins'                          => false,
                'update_themes'                           => false,
                'edit_dashboard'                          => false,
                'moderate_comments'                       => true,
                'manage_categories'                       => true,
                'manage_links'                            => true,
                'edit_others_posts'                       => true,
                'edit_pages'                              => true,
                'edit_others_pages'                       => true,
                'edit_published_pages'                    => true,
                'publish_pages'                           => true,
                'delete_pages'                            => true,
                'delete_others_pages'                     => true,
                'delete_published_pages'                  => true,
                'delete_others_posts'                     => true,
                'delete_private_posts'                    => true,
                'edit_private_posts'                      => true,
                'read_private_posts'                      => true,
                'delete_private_pages'                    => true,
                'edit_private_pages'                      => true,
                'read_private_pages'                      => true,
                'edit_published_posts'                    => true,
                'upload_files'                            => true,
                'publish_posts'                           => true,
                'delete_published_posts'                  => true,
                'edit_posts'                              => true,
                'delete_posts'                            => true,
                'read'                                    => true,
                'level_10'                                => false,
                'level_9'                                 => false,
                'level_8'                                 => false,
                'level_7'                                 => true,
                'level_6'                                 => true,
                'level_5'                                 => true,
                'level_4'                                 => true,
                'level_3'                                 => true,
                'level_2'                                 => true,
                'level_1'                                 => true,
                'level_0'                                 => true,
                EVENTAPPI_PLUGIN_NAME . '_manage_options' => true
            ));

            $capabilities = $this->getCoreCapabilities();

            foreach ($capabilities as $cap_group) {
                foreach ($cap_group as $cap) {
                    $wp_roles->add_cap('event_organiser', $cap);
                    $wp_roles->add_cap('administrator', $cap);
                }
            }
        }
    }

    /**
     * Get capabilities for eventappi
     * - these are assigned to admin/Event Organiser during installation or reset
     *
     * @return array
     */
    private function getCoreCapabilities()
    {
        $capabilities = array();

        $capabilities['core'] = array(
            'manage_' . EVENTAPPI_PLUGIN_NAME,
            EVENTAPPI_PLUGIN_NAME . '_menu'
        );

        $capability_types = array(EVENTAPPI_PLUGIN_NAME, EVENTAPPI_POST_NAME, 'event', 'ticket', 'venue');

        foreach ($capability_types as $capability_type) {

            $capabilities[$capability_type] = array(

                // Post type
                "manage_{$capability_type}",
                "edit_{$capability_type}",
                "read_{$capability_type}",
                "delete_{$capability_type}",
                "edit_{$capability_type}s",
                "edit_others_{$capability_type}s",
                "publish_{$capability_type}s",
                "read_private_{$capability_type}s",
                "delete_{$capability_type}s",
                "delete_private_{$capability_type}s",
                "delete_published_{$capability_type}s",
                "delete_others_{$capability_type}s",
                "edit_private_{$capability_type}s",
                "edit_published_{$capability_type}s",
                // Terms
                "manage_{$capability_type}_terms",
                "edit_{$capability_type}_terms",
                "delete_{$capability_type}_terms",
                "assign_{$capability_type}_terms"
            );
        }

        return $capabilities;
    }

    /**
     * create the database tables we need
     */
    private function createDatabase()
    {
        global $wpdb;

        $logger = Logger::instance();

        // require this so we can use dbDelta
        $logger->log(
            __FILE__,
            __FUNCTION__,
            'Requiring ' . ABSPATH . 'wp-admin/includes/upgrade.php',
            Logger::LOG_LEVEL_INFO
        );
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charsetCollate = $wpdb->get_charset_collate();

        $tableName          = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_cart';
        $createCartTableSql = <<<TABLESQL
CREATE TABLE {$tableName} (
    session VARCHAR(64) NOT NULL,
    event_id INT(10) UNSIGNED NOT NULL,
    ticket_id INT(10) UNSIGNED NOT NULL,
    post_id INT(10) UNSIGNED NOT NULL,
    term INT(10) UNSIGNED NOT NULL,
    ticket_name VARCHAR(255) NOT NULL,
    ticket_quantity INT(10) UNSIGNED NOT NULL,
    ticket_price INT(10) UNSIGNED NOT NULL,
    timestamp BIGINT(10) UNSIGNED NOT NULL,
    UNIQUE KEY `unique` (`session`,`ticket_id`)
) {$charsetCollate}
TABLESQL;
        $logger->log(
            __FILE__,
            __FUNCTION__,
            'Run: dbDelta( ' . $createCartTableSql . ' )',
            Logger::LOG_LEVEL_INFO
        );
        dbDelta($createCartTableSql);

        $tableName               = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases';
        $createPurchasesTableSql = <<<TABLESQL
CREATE TABLE {$tableName} (
    id INT(10) AUTO_INCREMENT NOT NULL,
    user_id INT(10) UNSIGNED NOT NULL,
    purchase_id INT(10) UNSIGNED NOT NULL,
    purchase_ticket_id INT(10) UNSIGNED NOT NULL,
    purchased_ticket_hash VARCHAR(40) NOT NULL,
    event_id INT(10) UNSIGNED NOT NULL,
    ticket_id INT(10) UNSIGNED NOT NULL,
    isClaimed SMALLINT(1) UNSIGNED NOT NULL,
    isAssigned SMALLINT(1) UNSIGNED NOT NULL,
    assignedTo VARCHAR(255),
    sentTo VARCHAR(255),
    isSent SMALLINT(1) UNSIGNED NOT NULL,
    isCheckedIn SMALLINT(1) UNSIGNED NOT NULL,
    additionalAttendeeData MEDIUMTEXT,
    timestamp BIGINT(10) UNSIGNED NOT NULL,
    PRIMARY KEY (id)
) {$charsetCollate}
TABLESQL;
        $logger->log(
            __FILE__,
            __FUNCTION__,
            'Run: dbDelta( ' . $createPurchasesTableSql . ' )',
            Logger::LOG_LEVEL_INFO
        );
        dbDelta($createPurchasesTableSql);

        // not strictly required any more
        $tableName            = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_venues';
        $createVenuesTableSql = <<<TABLESQL
CREATE TABLE {$tableName} (
    wp_id INT(10) UNSIGNED NOT NULL,
    api_id INT(10) UNSIGNED NOT NULL,
    address_1 VARCHAR(255),
    address_2 VARCHAR(255),
    city VARCHAR(255),
    postcode VARCHAR(25),
    country VARCHAR(125),
    UNIQUE INDEX `unique` (wp_id, api_id)
) {$charsetCollate}
TABLESQL;
        $logger->log(
            __FILE__,
            __FUNCTION__,
            'Run: dbDelta( ' . $createVenuesTableSql . ' )',
            Logger::LOG_LEVEL_INFO
        );
        dbDelta($createVenuesTableSql);
    }

    /**
     * When upgrading make sure we clean up behind ourselves...
     */
    private function checkForUpgradeChanges()
    {
        // first we look at the option table for the old 'chirrpy' prefix...
        global $wpdb;

        $optionTable = $wpdb->prefix . 'options';
        $sql         = <<<OPTIONSQL
SELECT option_name, option_value FROM {$optionTable} WHERE option_name LIKE 'chirrpy%';
OPTIONSQL;
        $options     = $wpdb->get_results($sql);

        foreach ($options as $option) {
            $optionName = substr($option->option_name, 7);
            $this->renameWordPressOption(
                'chirrpy' . $optionName,
                EVENTAPPI_PLUGIN_NAME . $optionName
            );

            if (strpos($optionName, '-') !== false) {
                $optionNewName = str_replace('-', '_', $optionName);
                $this->renameWordPressOption(
                    EVENTAPPI_PLUGIN_NAME . $optionName,
                    EVENTAPPI_PLUGIN_NAME . $optionNewName
                );
            }
        }

        // the next thing would be to get the relevant data from the now defunct eventappi_users table
        $usersTable = $wpdb->prefix . 'eventappi_users';
        $sql        = <<<USERSSQL
SELECT * FROM {$usersTable} ORDER BY `api_id` ASC
USERSSQL;
        $users      = $wpdb->get_results($sql);

        foreach ($users as $user) {
            add_user_meta($user->wp_id, 'eventappi_user_id', $user->api_id, true);
        }

        // now remove the users table
        $sql = <<<USERTABLESQL
DROP TABLE IF EXISTS {$usersTable}
USERTABLESQL;
        $wpdb->query($sql);

        // and remove the chirrpy_cart table if that's still around
        $tableName = $wpdb->prefix . 'chirrpy_cart';
        $sql       = <<<DEACSQL
DROP TABLE IF EXISTS {$tableName}
DEACSQL;
        $wpdb->query($sql);

        // we have an unused 'last_event' table
        $lastEventTable = $wpdb->prefix . 'eventappi_last_event';
        $sql            = <<<LASTTABLESQL
DROP TABLE IF EXISTS {$lastEventTable}
LASTTABLESQL;
        $wpdb->query($sql);

        // and an unused events table
        $eventsTableName = $wpdb->prefix . 'eventappi_events';
        $sql             = <<<EVENTTABLESQL
DROP TABLE IF EXISTS {$eventsTableName}
EVENTTABLESQL;
        $wpdb->query($sql);

        // and also an unused tickets table
        $ticketsTableName = $wpdb->prefix . 'eventappi_tickets';
        $sql              = <<<TICKETTABLESQL
DROP TABLE IF EXISTS {$ticketsTableName}
TICKETTABLESQL;
        $wpdb->query($sql);

        // we have changed the way we store venues
        $venueTable = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_venues';

        // ADDRESS_1
        $sql       = <<<VENUESQL
SELECT option_name, option_value FROM {$optionTable}
WHERE option_name LIKE 'eventappi_event_%_venue_address_1'
VENUESQL;
        $updateSql = <<<UPDATESQL
UPDATE {$venueTable} SET address_1 = %s
WHERE wp_id = %d
UPDATESQL;
        $venues    = $wpdb->get_results($sql);
        foreach ($venues as $venue) {
            $venueId = intval(substr($venue->option_name, 16, - 16));
            $wpdb->query($wpdb->prepare($updateSql, $venue->option_value, $venueId));
            delete_option($venue->option_name);
        }

        // ADDRESS_2
        $sql       = <<<VENUESQL
SELECT option_name, option_value FROM {$optionTable}
WHERE option_name LIKE 'eventappi_event_%_venue_address_2'
VENUESQL;
        $updateSql = <<<UPDATESQL
UPDATE {$venueTable} SET address_2 = %s
WHERE wp_id = %d
UPDATESQL;
        $venues    = $wpdb->get_results($sql);
        foreach ($venues as $venue) {
            $venueId = intval(substr($venue->option_name, 16, - 16));
            $wpdb->query($wpdb->prepare($updateSql, $venue->option_value, $venueId));
            delete_option($venue->option_name);
        }

        // CITY
        $sql       = <<<VENUESQL
SELECT option_name, option_value FROM {$optionTable}
WHERE option_name LIKE 'eventappi_event_%_venue_city'
VENUESQL;
        $updateSql = <<<UPDATESQL
UPDATE {$venueTable} SET city = %s
WHERE wp_id = %d
UPDATESQL;
        $venues    = $wpdb->get_results($sql);
        foreach ($venues as $venue) {
            $venueId = intval(substr($venue->option_name, 16, - 11));
            $wpdb->query($wpdb->prepare($updateSql, $venue->option_value, $venueId));
            delete_option($venue->option_name);
        }

        // POSTCODE
        $sql       = <<<VENUESQL
SELECT option_name, option_value FROM {$optionTable}
WHERE option_name LIKE 'eventappi_event_%_venue_postcode'
VENUESQL;
        $updateSql = <<<UPDATESQL
UPDATE {$venueTable} SET postcode = %s
WHERE wp_id = %d
UPDATESQL;
        $venues    = $wpdb->get_results($sql);
        foreach ($venues as $venue) {
            $venueId = intval(substr($venue->option_name, 16, - 15));
            $wpdb->query($wpdb->prepare($updateSql, $venue->option_value, $venueId));
            delete_option($venue->option_name);
        }

        // COUNTRY
        $sql       = <<<VENUESQL
SELECT option_name, option_value FROM {$optionTable}
WHERE option_name LIKE 'eventappi_event_%_venue_country'
VENUESQL;
        $updateSql = <<<UPDATESQL
UPDATE {$venueTable} SET country = %s
WHERE wp_id = %d
UPDATESQL;
        $venues    = $wpdb->get_results($sql);
        foreach ($venues as $venue) {
            $venueId = intval(substr($venue->option_name, 16, - 14));
            $wpdb->query($wpdb->prepare($updateSql, $venue->option_value, $venueId));
            delete_option($venue->option_name);
        }
    }

    /**
     * If it is necessary to change an option name, this will handle that for us
     *
     * @param $from - the old option name
     * @param $to   - the new option name
     */
    private function renameWordPressOption($from, $to)
    {
        $optionFromValue = get_option($from);
        $optionToValue   = get_option($to);
        if ($optionToValue === false) {
            update_option($to, $optionFromValue->option_value);
        }
        delete_option($from);
    }
}
