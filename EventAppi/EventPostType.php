<?php namespace EventAppi;

use Tax_Meta_Class;
use EventAppi\Helpers\CountryList as CountryHelper;
use EventAppi\Helpers\Logger;

/**
 * Class EventPostType
 *
 * @package EventAppi
 */
class EventPostType
{
    const MAX_LITE_TICKETS = 500;
    const REGEX_SHA_1 = '/^[0-9a-f]{40}$/i'; // exactly 40 hex characters

    /**
     * @var EventPostType|null
     */
    private static $singleton = null;

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return EventPostType|null
     */
    public static function instance()
    {
        if (is_null(self::$singleton)) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

    /**
     *
     */
    public function init()
    {
        add_action('init', array($this, 'handleAttendeeCheckin'));
        add_action('init', array($this, 'postTypeAndTaxonomies'));
        add_action('add_meta_boxes', array($this, 'addTicketsMetabox'));
        add_action('admin_menu', array($this, 'removeDefaultVenueMetaBox'));
        add_action('save_post', array($this, 'savePost'));
        add_action('post_updated', array($this, 'postUpdated'), 10, 3);

        add_action('edit_venue', array($this, 'saveVenueEntry'), 10, 1);
        add_action('create_venue', array($this, 'saveVenueEntry'), 10, 1);

        add_filter('manage_edit-' . EVENTAPPI_POST_NAME . '_columns', array($this, 'editColumns'));
        add_action('manage_posts_custom_column', array($this, 'prepareColumns'));

        add_action('template_redirect', array($this, 'overridePostDates'));

        add_filter('cmb_meta_boxes', array($this, 'postTypeMetaBoxes'));

        // a filter for hidden meta fields
        add_filter('cmb_field_types', function ($cmb_field_types) {
            $cmb_field_types['hidden'] = 'EventAppi\Helpers\CMBHiddenField';

            return $cmb_field_types;
        });

        // A filter for DATE meta fields
        add_filter('cmb_field_types', function ($cmb_field_types) {
            $cmb_field_types['date_ea'] = 'EventAppi\Helpers\CMBDateField';

            return $cmb_field_types;
        });

        // A filter for TIME meta fields
        add_filter('cmb_field_types', function ($cmb_field_types) {
            $cmb_field_types['time_ea'] = 'EventAppi\Helpers\CMBTimeField';

            return $cmb_field_types;
        });

        add_filter('the_content', array($this, 'filterThePostContent'));


        add_action('wp_enqueue_scripts', array($this, 'loadScripts'));
    }

    public function overridePostDates()
    {
        // the global WordPress post object
        global $post;

        if (is_object($post) && $post->post_type === EVENTAPPI_POST_NAME) {

//            $date            = date_format(
//                get_option('date_format'),
//                get_post_meta( $post->ID, EVENTAPPI_POST_NAME . '_start_date' )[0]
//            );
//            $time            = date_format(
//                'H:i:s',
//                get_post_meta( $post->ID, EVENTAPPI_POST_NAME . '_start_time' )[0]
//            );
//            $post->post_date = date;
        }
    }

    public function removeDefaultVenueMetaBox()
    {
        remove_meta_box('tagsdiv-venue', EVENTAPPI_POST_NAME, 'normal');
        remove_meta_box('tagsdiv-venue', EVENTAPPI_POST_NAME, 'side');
        remove_meta_box('tagsdiv-venue', EVENTAPPI_POST_NAME, 'advanced');
    }

    public function postTypeAndTaxonomies()
    {
        if (LicenseKeyManager::instance()->checkLicenseKey()) {
            $this->createPostType();

            $this->createVenueTaxonomy();
            $this->createTicketTaxonomy();
            $this->createCategoryTaxonomy();

            $this->setupActiveMenu();

            if (is_admin()) {
                $this->customMetaBoxes();
            }
        } else {
            User::instance()->addUserNotice(['type' => 'LicenseKeyNotice']);
            $this->setupInactiveMenu();
        }
    }

    public function addTicketsMetabox()
    {
        add_meta_box(
            EVENTAPPI_POST_NAME . '_tickets',
            __('Tickets Management (add / edit / remove)', EVENTAPPI_PLUGIN_NAME),
            array($this, 'prepareTicketMetabox'),
            EVENTAPPI_POST_NAME
        );
    }

    public function prepareTicketMetabox($post)
    {
        wp_nonce_field(EVENTAPPI_POST_NAME . '_meta_box', EVENTAPPI_POST_NAME . '_tickets_nonce');

        echo Parser::instance()->parseEventAppiTemplate('TicketMetaBoxes');
    }

    public function createPostType()
    {
        $labels = array(
            'name'               => _x('Events', 'post type general name', EVENTAPPI_PLUGIN_NAME),
            'singular_name'      => _x('Event', 'post type singular name', EVENTAPPI_PLUGIN_NAME),
            'add_new'            => _x('Add new Event', 'event', EVENTAPPI_PLUGIN_NAME),
            'add_new_item'       => __('Add new Event', EVENTAPPI_PLUGIN_NAME),
            'edit_item'          => __('Edit Event', EVENTAPPI_PLUGIN_NAME),
            'new_item'           => __('New Event', EVENTAPPI_PLUGIN_NAME),
            'view_item'          => __('View Event', EVENTAPPI_PLUGIN_NAME),
            'search_items'       => __('Search Events', EVENTAPPI_PLUGIN_NAME),
            'not_found'          => __('No events found', EVENTAPPI_PLUGIN_NAME),
            'not_found_in_trash' => __('No events found in Trash', EVENTAPPI_PLUGIN_NAME),
        );

        $taxonomies = array(
            EVENTAPPI_POST_NAME . '_categories',
            'venue'
        );

        $args = array(
            'labels'        => $labels,
            'description'   => 'An event with ticketing',
            'public'        => true,
            'menu_position' => 5,
            'menu_icon'     => 'dashicons-tickets-alt',
            'supports'      => array('title', 'editor', 'thumbnail'),
            'taxonomies'    => $taxonomies,
            'has_archive'   => true,
            'rewrite'       => array('slug' => __('events', EVENTAPPI_PLUGIN_NAME)),
            'query_var'     => 'events' // EVENTAPPI_POST_NAME
        );

        register_post_type(EVENTAPPI_POST_NAME, $args);
    }

    private function createVenueTaxonomy()
    {
        $labels = array(
            'name'                       => _x('Venues', 'taxonomy general name', EVENTAPPI_PLUGIN_NAME),
            'singular_name'              => _x('Venue', 'taxonomy singular name', EVENTAPPI_PLUGIN_NAME),
            'search_items'               => __('Search Venues', EVENTAPPI_PLUGIN_NAME),
            'popular_items'              => __('Popular Venues', EVENTAPPI_PLUGIN_NAME),
            'all_items'                  => __('All Venues', EVENTAPPI_PLUGIN_NAME),
            'parent_item'                => __('Venue', EVENTAPPI_PLUGIN_NAME),
            'parent_item_colon'          => __('Venue:', EVENTAPPI_PLUGIN_NAME),
            'edit_item'                  => __('Edit Venue', EVENTAPPI_PLUGIN_NAME),
            'update_item'                => __('Update Venue', EVENTAPPI_PLUGIN_NAME),
            'add_new_item'               => __('Add New Venue', EVENTAPPI_PLUGIN_NAME),
            'new_item_name'              => __('New Venue Name', EVENTAPPI_PLUGIN_NAME),
            'separate_items_with_commas' => __('Separate venues with commas', EVENTAPPI_PLUGIN_NAME),
            'add_or_remove_items'        => __('Add or remove venues', EVENTAPPI_PLUGIN_NAME),
            'choose_from_most_used'      => __('Choose from the most used venues', EVENTAPPI_PLUGIN_NAME),
            'not_found'                  => __('No venue found.', EVENTAPPI_PLUGIN_NAME),
            'menu_name'                  => __('Venues', EVENTAPPI_PLUGIN_NAME),
        );

        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            // TODO     'update_count_callback' => '_update_post_term_count',
            'query_var'         => true,
            'rewrite'           => array('slug' => 'venues'),
        );

        register_taxonomy('venue', EVENTAPPI_POST_NAME, $args);
    }


    private function createTicketTaxonomy()
    {
        $labels = array(
            'name'                       => _x('Tickets', 'taxonomy general name', EVENTAPPI_PLUGIN_NAME),
            'singular_name'              => _x('Ticket', 'taxonomy singular name', EVENTAPPI_PLUGIN_NAME),
            'search_items'               => __('Search Tickets', EVENTAPPI_PLUGIN_NAME),
            'popular_items'              => __('Popular Tickets', EVENTAPPI_PLUGIN_NAME),
            'all_items'                  => __('All Tickets', EVENTAPPI_PLUGIN_NAME),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __('Edit Ticket', EVENTAPPI_PLUGIN_NAME),
            'update_item'                => __('Update Ticket', EVENTAPPI_PLUGIN_NAME),
            'add_new_item'               => __('Add New Ticket', EVENTAPPI_PLUGIN_NAME),
            'new_item_name'              => __('New Ticket Name', EVENTAPPI_PLUGIN_NAME),
            'separate_items_with_commas' => __('Separate tickets with commas', EVENTAPPI_PLUGIN_NAME),
            'add_or_remove_items'        => __('Add or remove tickets', EVENTAPPI_PLUGIN_NAME),
            'choose_from_most_used'      => __('Choose from the most used tickets', EVENTAPPI_PLUGIN_NAME),
            'not_found'                  => __('No ticket found.', EVENTAPPI_PLUGIN_NAME),
            'menu_name'                  => __('Tickets', EVENTAPPI_PLUGIN_NAME),
        );

        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            // TODO     'update_count_callback' => '_update_post_term_count',
            'query_var'         => true,
            'rewrite'           => array('slug' => 'tickets'),
        );

        register_taxonomy('ticket', null, $args);
    }

    private function createCategoryTaxonomy()
    {
        $labels = array(
            'name'                       => _x('Categories', 'taxonomy general name', EVENTAPPI_PLUGIN_NAME),
            'singular_name'              => _x('Category', 'taxonomy singular name', EVENTAPPI_PLUGIN_NAME),
            'search_items'               => __('Search Categories', EVENTAPPI_PLUGIN_NAME),
            'popular_items'              => __('Popular Categories', EVENTAPPI_PLUGIN_NAME),
            'all_items'                  => __('All Categories', EVENTAPPI_PLUGIN_NAME),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __('Edit Category', EVENTAPPI_PLUGIN_NAME),
            'update_item'                => __('Update Category', EVENTAPPI_PLUGIN_NAME),
            'add_new_item'               => __('Add New Category', EVENTAPPI_PLUGIN_NAME),
            'new_item_name'              => __('New Category Name', EVENTAPPI_PLUGIN_NAME),
            'separate_items_with_commas' => __('Separate categories with commas', EVENTAPPI_PLUGIN_NAME),
            'add_or_remove_items'        => __('Add or remove categories', EVENTAPPI_PLUGIN_NAME),
            'choose_from_most_used'      => __('Choose from the most used categories', EVENTAPPI_PLUGIN_NAME),
            'not_found'                  => __('No category found.', EVENTAPPI_PLUGIN_NAME),
            'menu_name'                  => __('Categories', EVENTAPPI_PLUGIN_NAME)
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'event_category')
        );

        register_taxonomy(EVENTAPPI_POST_NAME . '_categories', EVENTAPPI_POST_NAME, $args);
    }

    private function customMetaBoxes()
    {
        $this->customVenueMetaBoxes();
        $this->customTicketMetaBoxes();
    }

    private function customVenueMetaBoxes()
    {
        $config = array(
            'id'             => EVENTAPPI_POST_NAME . '_venue_meta_box',
            'title'          => 'Venues',
            'pages'          => array('venue'),
            'context'        => 'advanced',
            'fields'         => array(),
            'local_images'   => false,
            'use_with_theme' => false
        );

        $meta = new Tax_Meta_Class($config);

        $meta->addText(EVENTAPPI_POST_NAME . '_venue_address_1', array('name' => 'Address line 1'));
        $meta->addText(EVENTAPPI_POST_NAME . '_venue_address_2', array('name' => 'Address line 2'));
        $meta->addText(EVENTAPPI_POST_NAME . '_venue_city', array('name' => 'City'));
        $meta->addText(EVENTAPPI_POST_NAME . '_venue_postcode', array('name' => 'Zip / Postal code'));
        $meta->addSelect(
            EVENTAPPI_POST_NAME . '_venue_country',
            CountryHelper::instance()->getCountryList(),
            array('name' => 'Country', 'std' => 'US')
        );
        $meta->addHidden(EVENTAPPI_POST_NAME . '_venue_api_id', array('name' => 'API Venue ID'));

        $meta->Finish();
    }

    private function customTicketMetaBoxes()
    {
        $config = array(
            'id'             => EVENTAPPI_POST_NAME . '_ticket_meta_box',
            'title'          => 'Tickets',
            'pages'          => array('ticket'),
            'context'        => 'normal',
            'fields'         => array(),
            'local_images'   => false,
            'use_with_theme' => false
        );

        $meta = new Tax_Meta_Class($config);

        $meta->addDate(EVENTAPPI_POST_NAME . '_ticket_sale_start', array('name' => 'On Sale From'));
        $meta->addDate(EVENTAPPI_POST_NAME . '_ticket_sale_end', array('name' => 'On Sale To'));
        $meta->addText(EVENTAPPI_POST_NAME . '_ticket_cost', array('name' => 'Cost'));
        $meta->addText(EVENTAPPI_POST_NAME . '_ticket_available', array('name' => 'Available'));
        $meta->addText(EVENTAPPI_POST_NAME . '_ticket_sold', array('name' => 'Sold'));
        $meta->addSelect(
            EVENTAPPI_POST_NAME . '_ticket_price_type',
            array('fixed' => 'Fixed', 'free' => 'Free'),
            array('name' => 'Price Type', 'std' => array('fixed'))
        );
        $meta->addHidden(EVENTAPPI_POST_NAME . '_ticket_api_id', array('name' => 'API Ticket ID'));

        $meta->Finish();
    }

    public function postTypeMetaBoxes(array $meta)
    {
        $eventFields = array(
            array(
                'name'       => 'Start Date',
                'desc'       => 'Event start date',
                'id'         => EVENTAPPI_POST_NAME . '_start_date',
                'type'       => 'date_ea',
                'class'      => 'start_date event',
                'attributes' => array('required' => 'required'),
                'cols'       => '6'
            ),
            array(
                'name'       => 'Start Time',
                'desc'       => 'Event start time',
                'id'         => EVENTAPPI_POST_NAME . '_start_time',
                'type'       => 'time_ea',
                'attributes' => array('required' => 'required'),
                'cols'       => '6'
            ),
            array(
                'name'  => 'End Date',
                'desc'  => 'Event end date',
                'id'    => EVENTAPPI_POST_NAME . '_end_date',
                'type'  => 'date_ea',
                'class' => 'end_date event',
                'cols'  => '6'
            ),
            array(
                'name' => 'End Time',
                'desc' => 'Event end time',
                'id'   => EVENTAPPI_POST_NAME . '_end_time',
                'type' => 'time',
                'cols' => '6'
            ),
            array(
                'id'         => EVENTAPPI_POST_NAME . '_venue_select',
                'name'       => 'Select Venue',
                'type'       => 'taxonomy_select',
                'allow_none' => true,
                'taxonomy'   => 'venue',
                'multiple'   => false,
                'cols'       => '10'
            ),
            array(
                'id'   => EVENTAPPI_POST_NAME . '_id',
                'name' => '',
                'type' => 'hidden',
                'cols' => '1'
            )
        );

        $meta[] = array(
            'title'    => 'Event Details',
            'pages'    => EVENTAPPI_POST_NAME,
            'context'  => 'normal',
            'priority' => 'high',
            'fields'   => $eventFields
        );

        return $meta;
    }

    public function editColumns($columns)
    {
        $columns = array(
            'cb'                                => '<input id="cb-select-all-1" type="checkbox">',
            'title'                             => __('Event', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_thumb'      => __('Featured Image', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_categories' => __('Categories', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_start_date' => __('Start Date', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_start_time' => __('Start Time', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_end_date'   => __('End Date', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_end_time'   => __('End Time', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_venue'      => __('Venue', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_actions'    => __('Actions', EVENTAPPI_PLUGIN_NAME)
        );

        return $columns;
    }


    public function prepareColumns($column)
    {
        global $post;
        $custom = get_post_custom();

        switch ($column) {
            case EVENTAPPI_POST_NAME . '_thumb':
                echo get_the_post_thumbnail($post->ID, array(64, 64));
                break;

            case EVENTAPPI_POST_NAME . '_categories':
                $categories = get_the_terms($post->ID, EVENTAPPI_POST_NAME . '_categories');
                $cats       = array();

                if ($categories) {
                    foreach ($categories as $category) {
                        $cats[$category->name] = $category->name;
                    }
                    echo implode(', ', $cats);
                } else {
                    echo 'none';
                }
                break;

            case EVENTAPPI_POST_NAME . '_start_date':
                $start_date = '';
                if (isset($custom[EVENTAPPI_POST_NAME . '_start_date'][0])) {
                    $start_date = $custom[EVENTAPPI_POST_NAME . '_start_date'][0];
                    $start_date = date(get_option('date_format'), $start_date);
                }
                echo '<em>' . $start_date . '</em>';
                break;

            case EVENTAPPI_POST_NAME . '_start_time':
                $start_time = '';
                if (isset($custom[EVENTAPPI_POST_NAME . '_start_time'][0])) {
                    $start_time = $custom[EVENTAPPI_POST_NAME . '_start_time'][0];
                    $start_time = date(get_option('time_format'), $start_time);
                }
                echo '<em>' . $start_time . '</em>';
                break;

            case EVENTAPPI_POST_NAME . '_end_date':
                $end_date = '';
                if (isset($custom[EVENTAPPI_POST_NAME . '_end_date'][0])) {
                    $end_date = $custom[EVENTAPPI_POST_NAME . '_end_date'][0];
                    $end_date = date(get_option('date_format'), $end_date);
                }
                echo '<em>' . $end_date . '</em>';
                break;

            case EVENTAPPI_POST_NAME . '_end_time':
                $end_time = '';
                if (isset($custom[EVENTAPPI_POST_NAME . '_end_time'][0])) {
                    $end_time = $custom[EVENTAPPI_POST_NAME . '_end_time'][0];
                    $end_time = date(get_option('time_format'), $end_time);
                }
                echo '<em>' . $end_time . '</em>';
                break;

            case EVENTAPPI_POST_NAME . '_venue':
                $venue = get_the_terms($post->ID, 'venue');
                if (is_array($venue)) {
                    foreach ($venue as $eventVenue) {
                        echo $eventVenue->name;
                    }
                } else {
                    echo 'none';
                }

                break;

            case EVENTAPPI_POST_NAME . '_actions':
                $adminUrl = get_admin_url();
                $theLinks = "<a href=\"{$adminUrl}edit.php?post_type=" . EVENTAPPI_POST_NAME .
                            "&page=" . EVENTAPPI_PLUGIN_NAME . "-attendees&post={$post->ID}\">" .
                            "<span title=\"Attendees\" class=\"dashicons dashicons-groups\"></span>" .
                            "</a>&nbsp;<a href=\"{$adminUrl}edit.php?post_type=" .
                            EVENTAPPI_POST_NAME . "&page=" . EVENTAPPI_PLUGIN_NAME . "-purchases&post={$post->ID}\">" .
                            "<span title=\"Purchases\" class=\"dashicons dashicons-cart\"</span> </a>";
                echo $theLinks;

                break;
        }
    }

    public function savePost($postId)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        global $current_user, $wpdb;

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (get_post_type($postId) !== EVENTAPPI_POST_NAME) {
            return;
        }

        if (array_key_exists('action', $_REQUEST) && $_REQUEST['action'] === 'trash') {
            // TODO: we probably need to deal with deletions at some point
            return;
        }

        if (!array_key_exists('post_title', $_REQUEST) && !array_key_exists(EVENTAPPI_POST_NAME . '_name', $_POST)) {
            // we don't have enough data to create an event.
            return;
        }

        foreach (array_keys($_POST) as $pKey) {
            $findOne = '_sale_start';
            $findTwo = '_sale_end';

            if (
                (substr($pKey, - strlen($findOne)) === $findOne) ||
                (substr($pKey, - strlen($findTwo)) === $findTwo) &&
                is_array($_POST[$pKey])
            ) {
                foreach (array_keys($_POST[$pKey]) as $pKey2) {
                    $_POST[$pKey][$pKey2] = strtotime($_POST[$pKey][$pKey2]);
                }
            }
        }

        $apiUserKey = get_user_meta($current_user->data->ID, 'eventappi_user_id', true);
        if (empty($apiUserKey)) {
            Logger::instance()->log(
                __FILE__,
                __FUNCTION__,
                '$apiUserKey is empty',
                Logger::LOG_LEVEL_DEBUG
            );
            $apiUserKey = User::instance()->addUserToApi($current_user, false);

            Logger::instance()->log(
                __FILE__,
                __FUNCTION__,
                "\$apiUserKey is {$apiUserKey}",
                Logger::LOG_LEVEL_DEBUG
            );
        }

        if (array_key_exists('post_title', $_REQUEST) && array_key_exists('content', $_REQUEST)) {
            $eventName = $_REQUEST['post_title'];
            $eventDesc = $_REQUEST['content'];
        } elseif (array_key_exists(EVENTAPPI_POST_NAME . '_name', $_POST) && array_key_exists('desc', $_POST)) {
            $eventName = $_POST[EVENTAPPI_POST_NAME . '_name'];
            $eventDesc = $_POST['desc'];
        }

        $venueId  = 0;
        $venueIdA = $_REQUEST[EVENTAPPI_POST_NAME . '_venue_select'];
        if (!is_null($venueIdA)) {
            foreach ($venueIdA as $vId) {
                $venueId = $vId;
            }
        }

        if (array_key_exists(EVENTAPPI_POST_NAME . '_venue_name', $_POST) &&
            !empty($_POST[EVENTAPPI_POST_NAME . '_venue_name'])
        ) {
            // we create a new venue
            $term = wp_insert_term($_POST[EVENTAPPI_POST_NAME . '_venue_name'], 'venue');
            if (is_a($term, 'WP_Error')) {
                wp_die('Unable to add the new venue');
            }

            $venueId = $term['term_id'];

            wp_set_object_terms($postId, $venueId, 'venue', false);
            update_tax_meta(
                $venueId,
                EVENTAPPI_POST_NAME . '_venue_address_1',
                $_POST[EVENTAPPI_POST_NAME . '_venue_address_1']
            );
            update_tax_meta(
                $venueId,
                EVENTAPPI_POST_NAME . '_venue_address_2',
                $_POST[EVENTAPPI_POST_NAME . '_venue_address_2']
            );
            update_tax_meta(
                $venueId,
                EVENTAPPI_POST_NAME . '_venue_city',
                $_POST[EVENTAPPI_POST_NAME . '_venue_city']
            );
            update_tax_meta(
                $venueId,
                EVENTAPPI_POST_NAME . '_venue_postcode',
                $_POST[EVENTAPPI_POST_NAME . '_venue_postcode']
            );
            update_tax_meta(
                $venueId,
                EVENTAPPI_POST_NAME . '_venue_country',
                $_POST[EVENTAPPI_POST_NAME . '_venue_country']
            );

            $_POST['taxonomy'] = 'venue';
            $_POST['tag-name'] = $_POST[EVENTAPPI_POST_NAME . '_venue_name'];
        }

        $venueTable  = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_venues';
        $sql         = <<<CHECKVENUESQL
SELECT `api_id` FROM {$venueTable}
WHERE `wp_id` = %d
CHECKVENUESQL;
        $apiVenueKey = $wpdb->get_var(
            $wpdb->prepare(
                $sql,
                $venueId
            )
        );

        $startDate  = 0;
        $startDateA = $_REQUEST[EVENTAPPI_POST_NAME . '_start_date'];
        if (!is_null($startDateA)) {
            foreach ($startDateA as $vId) {
                $startDate = $vId;
            }
        }
        $endDate  = 0;
        $endDateA = $_REQUEST[EVENTAPPI_POST_NAME . '_end_date'];
        if (!is_null($endDateA)) {
            foreach ($endDateA as $vId) {
                $endDate = $vId;
            }
        }
        if (array_key_exists('all_day', $_POST) && $_POST['all_day'] === '1') {
            $endDate = $startDate;
        }
        $startTime  = 0;
        $startTimeA = $_REQUEST[EVENTAPPI_POST_NAME . '_start_time'];
        if (!is_null($startTimeA)) {
            foreach ($startTimeA as $vId) {
                $startTime = $vId;
            }
        }
        $endTime  = 0;
        $endTimeA = $_REQUEST[EVENTAPPI_POST_NAME . '_end_time'];
        if (!is_null($endTimeA)) {
            foreach ($endTimeA as $vId) {
                $endTime = $vId;
            }
        }
        if (array_key_exists('all_day', $_POST) && $_POST['all_day'] === '1') {
            $endTime = '23:59:59';
        }

        if (array_key_exists('ea_p_a', $_POST)) {
            update_post_meta($postId, EVENTAPPI_POST_NAME . '_start_date', strtotime($startDate));
            update_post_meta($postId, EVENTAPPI_POST_NAME . '_start_time', $startTime);
            update_post_meta($postId, EVENTAPPI_POST_NAME . '_end_date', strtotime($endDate));
            update_post_meta($postId, EVENTAPPI_POST_NAME . '_end_time', $endTime);
            update_post_meta($postId, EVENTAPPI_POST_NAME . '_venue_select', $venueId);
        }

        $start = date('Y-m-d H:i:s', strtotime($startDate . ' ' . $startTime));
        $end   = date('Y-m-d H:i:s', strtotime($endDate . ' ' . $endTime));

        $banner_image_url = '';
        if (array_key_exists('thumbId', $_POST)) {
            set_post_thumbnail($postId, $_POST['thumbId']);
        }
        $image = wp_get_attachment_image_src(get_post_thumbnail_id($postId), 'single-post-thumbnail');
        if (isset($image[0])) {
            $banner_image_url = $image[0];
        }

        $eventData = array(
            'user_id'          => $apiUserKey,
            'name'             => $eventName,
            'description'      => $eventDesc,
            'banner_image_url' => $banner_image_url, // we pass in the URL to the featured image
            'venue'            => $apiVenueKey,
            'start'            => $start,
            'end'              => $end
        );
        Logger::instance()->log(
            __FILE__,
            __FUNCTION__,
            ['message' => 'We have our Event Data.', 'data' => $eventData],
            Logger::LOG_LEVEL_DEBUG
        );

        $apiEventId = get_post_meta($postId, EVENTAPPI_POST_NAME . '_id', true);
        if (!empty($apiEventId)) {
            // we have something on the API
            $updateEvent = ApiClient::instance()->updateEvent($apiEventId, $eventData);
            if (!array_key_exists('code', $updateEvent)) {
                return;
            }
        } else {
            $newEvent = ApiClient::instance()->storeEvent($eventData);
            if (!array_key_exists('data', $newEvent)) {
                return;
            }

            update_post_meta($postId, EVENTAPPI_POST_NAME . '_id', $newEvent['data']['id']);
        }

        $tickets = $_POST[EVENTAPPI_POST_NAME . '_ticket_name'];

        if (!empty($tickets)) {
            $this->saveTickets($postId, $tickets);
        }
    }

    public function postUpdated($post_ID, $post_after, $post_before)
    {
        // Only load this for the event post type
        if (get_post_type($post_ID) !== EVENTAPPI_POST_NAME) {
            return;
        }
    }

    public function saveTickets($postId, $tickets)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions.
        if (isset($_POST['post_type']) && EVENTAPPI_POST_NAME === $_POST['post_type']) {

            if (!current_user_can('edit_page', $postId)) {
                return;
            }

        } else {
            return;
        }

        if (array_key_exists('post_ID', $_REQUEST)) {
            $postId = $_REQUEST['post_ID'];
        }
        $apiEventId = get_post_meta($postId, EVENTAPPI_POST_NAME . '_id', true);

        $ticketTotal = 0;
        foreach ($tickets as $index => $ticket) {
            $ticketTotal += intval($_POST[EVENTAPPI_POST_NAME . '_ticket_available'][$index]);
        }
        if ($ticketTotal > self::MAX_LITE_TICKETS) {
            User::instance()->addUserNotice([
                'class'   => 'error',
                'message' => sprintf(
                    __('You cannot have more than %d tickets per event.', EVENTAPPI_PLUGIN_NAME),
                    self::MAX_LITE_TICKETS
                )
            ]);

            return;
        }

        $wp_date_format = get_option('date_format');

        foreach ($tickets as $index => $ticket) {
            $inputPostIndex = array_search($ticket, $_POST[EVENTAPPI_POST_NAME . '_ticket_name']);

            $term = wp_insert_term(
                $ticket,
                'ticket',
                array(
                    'description' => $_POST[EVENTAPPI_POST_NAME . '_ticket_description'][$inputPostIndex],
                    'slug'        => $postId . '-ticket-' . $inputPostIndex
                )
            );
            if (is_a($term, 'WP_Error')) {
                $term = get_term_by('slug', $postId . '-ticket-' . $inputPostIndex, 'ticket', ARRAY_A);
            }

            wp_set_object_terms(
                $postId,
                $term['term_id'],
                'ticket',
                ($index > 0)
            );

            $saleStart = $_POST[EVENTAPPI_POST_NAME . '_ticket_sale_start'][$inputPostIndex];

            if (empty($saleStart)) {
                $saleStart = date($wp_date_format, strtotime('now'));
            }

            update_tax_meta(
                $term['term_id'],
                EVENTAPPI_POST_NAME . '_ticket_sale_start',
                $saleStart
            );

            $saleEnd = $_POST[EVENTAPPI_POST_NAME . '_ticket_sale_end'][$inputPostIndex];
            if (empty($saleEnd)) {
                $eventStartDate = get_post_meta($postId, EVENTAPPI_POST_NAME . '_start_date', true);
                if (empty($eventStartDate)) {
                    $eventStartDate = $_POST[EVENTAPPI_POST_NAME . '_start_date']['cmb-field-0'];
                }
                $saleEnd = date($wp_date_format, strtotime($eventStartDate));
            }
            update_tax_meta(
                $term['term_id'],
                EVENTAPPI_POST_NAME . '_ticket_sale_end',
                $saleEnd
            );

            update_tax_meta(
                $term['term_id'],
                EVENTAPPI_POST_NAME . '_ticket_cost',
                intval(floatval($_POST[EVENTAPPI_POST_NAME . '_ticket_cost'][$inputPostIndex]) * 100)
            );
            update_tax_meta(
                $term['term_id'],
                EVENTAPPI_POST_NAME . '_ticket_available',
                $_POST[EVENTAPPI_POST_NAME . '_ticket_available'][$inputPostIndex]
            );
            update_tax_meta(
                $term['term_id'],
                EVENTAPPI_POST_NAME . '_ticket_sold',
                $_POST[EVENTAPPI_POST_NAME . '_ticket_sold'][$inputPostIndex]
            );
            update_tax_meta(
                $term['term_id'],
                EVENTAPPI_POST_NAME . '_ticket_price_type',
                $_POST[EVENTAPPI_POST_NAME . '_ticket_price_type'][$inputPostIndex]
            );

            $apiStartTime = date(
                'Y-m-d H:i:s',
                get_tax_meta($term['term_id'], EVENTAPPI_POST_NAME . '_ticket_sale_start')
            );
            $apiEndTime   = date(
                'Y-m-d H:i:s',
                get_tax_meta($term['term_id'], EVENTAPPI_POST_NAME . '_ticket_sale_end')
            );
            $ticketArray  = array(
                'event_id'    => $apiEventId,
                'name'        => $ticket,
                'description' => $_POST[EVENTAPPI_POST_NAME . '_ticket_description'][$inputPostIndex],
                'cost'        => intval(floatval($_POST[EVENTAPPI_POST_NAME . '_ticket_cost'][$inputPostIndex]) * 100),
                'available'   => $_POST[EVENTAPPI_POST_NAME . '_ticket_available'][$inputPostIndex],
                'sold'        => intval($_POST[EVENTAPPI_POST_NAME . '_ticket_sold'][$inputPostIndex]),
                'price_type'  => $_POST[EVENTAPPI_POST_NAME . '_ticket_price_type'][$inputPostIndex],
                'sale_start'  => $apiStartTime,
                'sale_end'    => $apiEndTime
            );

            $apiTicketId = get_tax_meta($term['term_id'], EVENTAPPI_POST_NAME . '_ticket_api_id', true);
            if (!empty($apiTicketId)) {
                // we have something on the API
                $updateTicket = ApiClient::instance()->updateTicket($apiEventId, $apiTicketId, $ticketArray);
                if (!array_key_exists('code', $updateTicket)) {
                    return;
                }
            } else {
                $newTicket = ApiClient::instance()->storeTicket($apiEventId, $ticketArray);
                if (!array_key_exists('data', $newTicket)) {
                    return;
                }

                update_tax_meta($term['term_id'], EVENTAPPI_POST_NAME . '_ticket_api_id', $newTicket['data']['id']);
            }
        }
    }

    private function setupActiveMenu()
    {
        add_action('admin_menu', array($this, 'activeMenu'));
    }

    public function activeMenu()
    {
        $capability = EVENTAPPI_PLUGIN_NAME . '_menu';
        $menu_slug  = 'edit.php?post_type=' . EVENTAPPI_PLUGIN_NAME . '_event';

        // Add Stats Page
        add_submenu_page(
            $menu_slug,
            'EventAppi Reports',
            'Reports',
            $capability,
            EVENTAPPI_PLUGIN_NAME . '-analytics',
            array(Analytics::instance(), 'analyticsPage')
        );

        // Add Attendees Page
        add_submenu_page(
            null,
            'EventAppi Attendees',
            'Attendees',
            $capability,
            EVENTAPPI_PLUGIN_NAME . '-attendees',
            array($this, 'attendeesPage')
        );

        // Add Attendees-Download Page
        add_submenu_page(
            null,
            'Download EventAppi Attendees',
            'Download Attendees',
            $capability,
            EVENTAPPI_PLUGIN_NAME . '-download-attendees',
            array($this, 'attendeesExport')
        );

        // Add Purchases Page
        add_submenu_page(
            null,
            'EventAppi Purchases',
            'Purchases',
            $capability,
            EVENTAPPI_PLUGIN_NAME . '-purchases',
            array($this, 'purchasesPage')
        );

        global $current_user;

        if (array_key_exists('administrator', $current_user->caps)) {
            // Add Settings Page
            add_submenu_page(
                $menu_slug,
                'EventAppi Settings',
                'Settings',
                $capability,
                EVENTAPPI_PLUGIN_NAME . '-settings',
                array(Settings::instance(), 'settingsPage')
            );
        }

        // Add Help Page
        add_submenu_page(
            $menu_slug,
            'EventAppi Help',
            'Help',
            $capability,
            EVENTAPPI_PLUGIN_NAME . '-help',
            array(Help::instance(), 'helpPage')
        );
    }

    private function setupInactiveMenu()
    {
        add_action('admin_menu', array($this, 'inactiveMenu'));
    }

    public function inactiveMenu()
    {
        global $current_user;
        $capability = EVENTAPPI_PLUGIN_NAME . '_menu';

        add_menu_page(
            'Events',
            'Events',
            'manage_' . EVENTAPPI_PLUGIN_NAME,
            EVENTAPPI_POST_NAME,
            array($this, 'inactiveNotice'),
            'dashicons-tickets-alt',
            9
        );

        // Add Settings Page
        if (array_key_exists('administrator', $current_user->caps)) {
            add_submenu_page(
                EVENTAPPI_POST_NAME,
                'EventAppi Settings',
                'Settings',
                $capability,
                EVENTAPPI_PLUGIN_NAME . '-settings',
                array(Settings::instance(), 'settingsPage')
            );
        }

        // Add Help Page
        add_submenu_page(
            EVENTAPPI_POST_NAME,
            'EventAppi Help',
            'Help',
            $capability,
            EVENTAPPI_PLUGIN_NAME . '-help',
            array(Help::instance(), 'helpPage')
        );
    }

    public function inactiveNotice()
    {
        wp_die(__(
            'Looks like the EventAppi plugin is inactive. Please check your license key in the Settings menu.',
            EVENTAPPI_PLUGIN_NAME
        ));
    }

    private function csvFormattedLine($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $element) {
                if (!is_string($element)) {
                    $element = (string) $element;
                }
                if (strpos($element, ',') !== false) {
                    if (strpos($element, '"') !== false) {
                        $element = str_replace('"', '\"', $element);
                    }
                    $data[$key] = '"' . $element . '"';
                }
            }
            $data = implode(',', $data);
        }

        return $data . "\n";
    }

    public function handleAttendeeCheckin()
    {
        if (array_key_exists('check', $_GET) && array_key_exists('state', $_GET) && array_key_exists('post', $_GET)) {
            /**
             * Before allowing WordPress to continue loading, we need to short-circuit the
             * attendee check-in status change so that we can be sure to update the status
             * as early as possible.
             *
             * @see EventPostType::updateAttendeeCheckinStatus()
             */
            $this->updateAttendeeCheckinStatus($_GET['post'], $_GET['check'], $_GET['state']);
        }
    }

    public function updateAttendeeCheckinStatus($eventId, $purchasedTicketHash, $status, $ajax = false)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $apiOrganiserId = get_user_meta(
            get_post_field('post_author', $eventId),
            EVENTAPPI_PLUGIN_NAME . '_user_id',
            true
        );
        $apiEventId     = get_post_meta($eventId, EVENTAPPI_PLUGIN_NAME . '_event_id', true);
        $status         = (strtolower($status) === 'in') ? 'in' : 'out';

        if (preg_match(self::REGEX_SHA_1, $purchasedTicketHash)) {
            $result = ApiClient::instance()->setAttendeeCheckinStatus(
                $apiOrganiserId,
                $apiEventId,
                $purchasedTicketHash,
                $status
            );

            if (array_key_exists('error', $result) &&
                $result['code'] === ApiClientInterface::RESPONSE_ALREADY_CHECKED_IN
            ) {
                $error = array(
                    'class'   => 'error',
                    'message' => __(
                        'The attendee is already checked in, and cannot be checked in again.',
                        EVENTAPPI_PLUGIN_NAME
                    )
                );

                if ($ajax) {
                    // If there is an AJAX call, output the result in JSON format
                    echo json_encode($error);
                    exit;
                } else {
                    // No AJAX call is made, the page was refreshed in the Dashboard
                    User::instance()->addUserNotice($error);

                    return;
                }
            }

            // AJAX call and no error? Echo the success in JSON format
            if ($ajax) {
                echo json_encode(array(
                    'class'     => 'success',
                    'state'     => (($status == 'in') ? 'Out' : 'In'),
                    'link_text' => (($status == 'in')
                        ? __('Check Out', EVENTAPPI_PLUGIN_NAME)
                        : __('Check In', EVENTAPPI_PLUGIN_NAME)),
                    'message'   => __('The attendee\'s status was changed', EVENTAPPI_PLUGIN_NAME)
                ));
            }
        }
    }

    public function updateAllAttendeesCheckinStatusForEvent($eventId)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $apiOrganiserId = get_user_meta(
            get_post_field('post_author', $eventId),
            EVENTAPPI_PLUGIN_NAME . '_user_id',
            true
        );
        $apiEventId     = get_post_meta($eventId, EVENTAPPI_PLUGIN_NAME . '_event_id', true);

        global $wpdb;
        $attendeeTable = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases';

        $attendeeList = ApiClient::instance()->listEventAttendees($apiOrganiserId, $apiEventId);

        if (empty($attendeeList)) {
            return;
        }

        if (array_key_exists('data', $attendeeList)) {
            $checkedInAttendees = array();
            $attendeeList       = $attendeeList['data'];

            foreach ($attendeeList as $attendee) {
                if ($attendee['checkedIn'] === true) {
                    // make sure the hashes are quoted for the SQL query
                    $checkedInAttendees[] = "'{$attendee['hash']}'";
                }
            }

            $checkoutAllAttendeesSql = <<<CHECKOUTALLSQL
UPDATE {$attendeeTable}
SET isCheckedIn = 0
WHERE event_id = %d
CHECKOUTALLSQL;
            $wpdb->query(
                $wpdb->prepare(
                    $checkoutAllAttendeesSql,
                    $eventId
                )
            );

            if (count($checkedInAttendees) > 0) {
                $checkedInAttendees = implode(',', $checkedInAttendees);
                $checkinUpdateSql   = <<<CHECKINUPDATESQL
UPDATE {$attendeeTable}
SET isCheckedIn = 1
WHERE purchased_ticket_hash in ({$checkedInAttendees})
CHECKINUPDATESQL;
                $wpdb->query(
                    $checkinUpdateSql
                );
            }
        }
    }

    public function attendeesExport()
    {
        global $wpdb;

        $eventPostID    = $_GET['post'];
        $attendeeTable  = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases';
        $usersTable     = $wpdb->prefix . 'users';
        $usersMetaTable = $wpdb->prefix . 'usermeta';
        $post           = get_post($eventPostID);

        $this->updateAllAttendeesCheckinStatusForEvent($eventPostID);

        $attendeeQuery = <<<ATTENDEEQUERY
SELECT a.assignedTo, a.isCheckedIn, a.additionalAttendeeData, u.user_email,
       u.display_name, mf.meta_value as first_name, ml.meta_value as last_name
FROM {$attendeeTable} AS a
    LEFT JOIN {$usersTable} AS u ON a.user_id = u.ID
    LEFT JOIN {$usersMetaTable} AS mf ON u.ID = mf.user_id AND mf.meta_key = 'first_name'
    LEFT JOIN {$usersMetaTable} AS ml ON u.ID = ml.user_id AND ml.meta_key = 'last_name'
WHERE a.event_id = %d AND (a.isClaimed = '1' OR a.isAssigned = '1')
GROUP BY a.purchased_ticket_hash
ORDER BY ml.meta_value ASC, mf.meta_value ASC
ATTENDEEQUERY;

        $queryResults           = $wpdb->get_results(
            $wpdb->prepare(
                $attendeeQuery,
                $eventPostID
            )
        );
        $attendeesForExport     = array();
        $additionalDataElements = User::instance()->getAdditionalContactMethods();
        $additionalDataKeys     = array();

        foreach ($additionalDataElements as $dataKey) {
            $additionalDataKeys[$dataKey['id']] = $dataKey['name'];
        }

        foreach ($queryResults as $attendee) {
            $attendeeData = array(
                __('Email', EVENTAPPI_PLUGIN_NAME)        => $attendee->user_email,
                __('First Name', EVENTAPPI_PLUGIN_NAME)   => $attendee->first_name,
                __('Last Name', EVENTAPPI_PLUGIN_NAME)    => $attendee->last_name,
                __('Display Name', EVENTAPPI_PLUGIN_NAME) => $attendee->display_name,
                __('Checked In', EVENTAPPI_PLUGIN_NAME)   => ($attendee->isCheckedIn === '1') ? __('Yes', EVENTAPPI_PLUGIN_NAME) : __('No', EVENTAPPI_PLUGIN_NAME),
                __('Assigned To', EVENTAPPI_PLUGIN_NAME)  => $attendee->assignedTo
            );
            $extra        = unserialize($attendee->additionalAttendeeData);
            foreach ($extra as $key => $value) {
                $attendeeData[$additionalDataKeys[$key]] = $value;
            }
            $attendeesForExport[] = $attendeeData;
        }

        header('Content-type: text/csv');
        header('Content-Disposition: attachment; filename="event' . $post->ID . '-attendees.csv"');

        if (count($attendeesForExport) > 0) {
            echo $this->csvFormattedLine(array_keys($attendeesForExport[0]));
            foreach ($attendeesForExport as $attendee) {
                echo $this->csvFormattedLine($attendee);
            }
        } else {
            echo __('There are no attendees for this event', EVENTAPPI_PLUGIN_NAME);
        }

        exit();
    }

    public function attendeesPage()
    {
        if (!current_user_can('manage_' . EVENTAPPI_PLUGIN_NAME)) {
            wp_die(__('You do not have sufficient permissions to access this page.', EVENTAPPI_PLUGIN_NAME));
        }

        $eventPostID = (int) $_GET['post'];

        $this->updateAllAttendeesCheckinStatusForEvent($eventPostID);

        $data = $this->getAttendeesData($eventPostID);

        echo Parser::instance()->parseEventAppiTemplate('ListEventAttendees', $data);
    }

    public function getAttendeesData($eventPostID)
    {

        global $wpdb, $post;

        $data = array();

        $results_per_page = 2;

        $data['customPost'] = get_post_type_object(EVENTAPPI_POST_NAME);

        if (is_admin()) { // Dashboard

            $data['postUrl']        = get_admin_url() . 'edit.php?post_type=' . EVENTAPPI_POST_NAME .
                                      '&page=' . EVENTAPPI_PLUGIN_NAME . "-attendees&post={$eventPostID}";
            $data['attendeesLabel'] = __('Attendees', EVENTAPPI_PLUGIN_NAME);
            $sq_key                 = 's';

        } else { // Front-end
            $page = get_query_var('page', 1);

            if ($page == 0) {
                $page = 1;
            }

            $sq_key = 'sf';

            $data['postUrl'] = $data['postUrlRoot'] = get_permalink($post->ID) . '?id=' . $eventPostID;

            // Append any existing query strings
            if ($_GET['checked'] != '') {
                $data['postUrl'] .= '&checked=' . htmlspecialchars($_GET['checked']);
            }

            if ($_GET['sf'] != '') {
                $data['postUrl'] .= '&sf=' . urlencode($_GET['sf']);
            }
        }

        $data['exportUrl'] = get_admin_url() . 'link.php?post_type=' . EVENTAPPI_POST_NAME .
                             '&page=' . EVENTAPPI_PLUGIN_NAME . '-download-attendees&post=' . $eventPostID;

        $data['eventPost'] = get_post($eventPostID);

        $attendeeTable  = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases';
        $usersTable     = $wpdb->prefix . 'users';
        $usersMetaTable = $wpdb->prefix . 'usermeta';

        $sql           = <<<ATTENDEECOUNTSQL
SELECT COUNT(id) FROM {$attendeeTable}
WHERE event_id = {$eventPostID}
AND (isClaimed = '1' OR isAssigned = '1')
ATTENDEECOUNTSQL;
        $attendeeCount = $wpdb->get_var($sql);

        $sql           = <<<ATTENDEECHECKSQL
SELECT COUNT(id) FROM {$attendeeTable}
WHERE event_id = {$eventPostID}
AND (isClaimed = '1' OR isAssigned = '1')
AND isCheckedIn = '1'
ATTENDEECHECKSQL;
        $attendeeCheck = $wpdb->get_var($sql);

        $attendeeQuery = <<<ATTENDEEQUERY
SELECT a.id, a.assignedTo, a.isClaimed, a.isCheckedIn, a.additionalAttendeeData, u.user_email,
       u.display_name, mf.meta_value as first_name, ml.meta_value as last_name, a.purchased_ticket_hash
FROM {$attendeeTable} AS a
    LEFT JOIN {$usersTable} AS u ON a.user_id = u.ID
    LEFT JOIN {$usersMetaTable} AS mf ON u.ID = mf.user_id AND mf.meta_key = 'first_name'
    LEFT JOIN {$usersMetaTable} AS ml ON u.ID = ml.user_id AND ml.meta_key = 'last_name'
    WHERE a.event_id = %d AND (a.isClaimed = '1' OR a.isAssigned = '1')
ATTENDEEQUERY;

        if (array_key_exists($sq_key, $_GET)) {
            $attendeeQuery .= <<<SEARCHCLAUSE
    AND (u.user_email LIKE  '%%%s%%' OR
         a.assignedTo LIKE '%%%s%%' OR
         mf.meta_value LIKE '%%%s%%' OR
         ml.meta_value LIKE '%%%s%%')
SEARCHCLAUSE;

        } elseif (array_key_exists('checked', $_GET)) {
            $attendeeQuery .= " AND a.isCheckedIn = ";
            $attendeeQuery .= ($_GET['checked'] == 'yes') ? '1 ' : '0 ';
        }

        $attendeeQuery .= " GROUP BY a.purchased_ticket_hash ";

        if (array_key_exists($sq_key, $_GET)) {
            $attendeeQuery = $wpdb->prepare(
                $attendeeQuery,
                $eventPostID,
                $_GET[$sq_key],
                $_GET[$sq_key],
                $_GET[$sq_key],
                $_GET[$sq_key]
            );
        } else {
            $attendeeQuery = $wpdb->prepare(
                $attendeeQuery,
                $eventPostID
            );
        }

        // All Results - To determine total number of pages
        $attendeeAllResults = count($wpdb->get_results($attendeeQuery));

        // Page?
        if ($page != '') {
            $offset = (($page - 1) * $results_per_page);
            $attendeeQuery .= ' LIMIT ' . $offset . ', ' . $results_per_page;
        }

        $data['attendees'] = $wpdb->get_results($attendeeQuery);
        $data[$sq_key]     = htmlspecialchars($_GET[$sq_key]);

        if (is_null($attendeeCheck)) {
            $attendeeCheck = 0;
        }

        $data['counters'] = array(
            array(
                'name'  => __('All', EVENTAPPI_PLUGIN_NAME),
                'count' => $attendeeCount,
                'link'  => ''
            ),
            array(
                'name'  => __('Checked In', EVENTAPPI_PLUGIN_NAME),
                'count' => $attendeeCheck,
                'link'  => '&checked=yes'
            ),
            array(
                'name'  => __('Not Checked In', EVENTAPPI_PLUGIN_NAME),
                'count' => $attendeeCount - $attendeeCheck,
                'link'  => '&checked=no'
            )
        );

        $data['extraDataFields'] = User::instance()->getAdditionalContactMethods(true);

        // Total Pages
        $data['total_pages'] = ceil($attendeeAllResults / $results_per_page);

        // Current page
        $data['page'] = $page;

        return $data;

    }

    public function purchasesPage()
    {
        if (!current_user_can('manage_' . EVENTAPPI_PLUGIN_NAME)) {
            wp_die(__('You do not have sufficient permissions to access this page.', EVENTAPPI_PLUGIN_NAME));
        }

        global $wpdb;

        $eventPostID = (int) $_GET['post'];

        $data = $this->getPurchasesData($eventPostID);

        echo Parser::instance()->parseEventAppiTemplate('ListEventPurchases', $data);
    }

    public function getPurchasesData($eventPostID)
    {
        global $wpdb, $post;

        $results_per_page = 4;

        $data = array();

        $data['customPost'] = get_post_type_object(EVENTAPPI_POST_NAME);

        if (is_admin()) { // Dashboard

            $data['postUrl']        = get_admin_url() . 'edit.php?post_type=' . EVENTAPPI_POST_NAME .
                                      '&page=' . EVENTAPPI_PLUGIN_NAME . '-purchases&post=' . $eventPostID;
            $data['purchasesLabel'] = __('Purchases', EVENTAPPI_PLUGIN_NAME);
            $sq_key                 = 's';

        } else { // Front-end
            $page = get_query_var('page', 1);

            if ($page == 0) {
                $page = 1;
            }

            $sq_key = 'sf';

            $data['postUrl'] = $data['postUrlRoot'] = get_permalink($post->ID) . '?id=' . $eventPostID;

            // Append any existing query strings
            if ($_GET['assigned'] != '') {
                $data['postUrl'] .= '&assigned=' . htmlspecialchars($_GET['assigned']);
            }

            if ($_GET['sf'] != '') {
                $data['postUrl'] .= '&sf=' . urlencode($_GET['sf']);
            }
        }

        $data['eventPost'] = get_post($eventPostID);

        $purchasesTable = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases';
        $usersTable     = $wpdb->prefix . 'users';
        $usersMetaTable = $wpdb->prefix . 'usermeta';

        $sql                   = <<<PURCHASECOUNTSQL
SELECT COUNT(id) FROM {$purchasesTable}
WHERE event_id = {$eventPostID}
PURCHASECOUNTSQL;
        $data['purchaseCount'] = $wpdb->get_var($sql);

        $sql                   = <<<PURCHASEAVAILSQL
SELECT COUNT(id) FROM {$purchasesTable}
WHERE event_id = {$eventPostID}
AND isClaimed = '0' AND isAssigned = '0' AND isSent = '0'
PURCHASEAVAILSQL;
        $data['purchaseAvail'] = $wpdb->get_var($sql);

        $purchaseQuery = <<<PURCHASEQUERYSQL
SELECT a.id, a.purchased_ticket_hash, a.assignedTo, a.isClaimed, a.isCheckedIn, u.user_email,
       u.display_name, mf.meta_value as first_name, ml.meta_value as last_name, a.sentTo
FROM {$purchasesTable} AS a
    LEFT JOIN {$usersTable} AS u ON a.user_id = u.ID
    LEFT JOIN {$usersMetaTable} AS mf ON u.ID = mf.user_id AND mf.meta_key = 'first_name'
    LEFT JOIN {$usersMetaTable} AS ml ON u.ID = ml.user_id AND ml.meta_key = 'last_name'
WHERE a.event_id = {$eventPostID}
PURCHASEQUERYSQL;

        // cater for search criteria
        if (array_key_exists($sq_key, $_GET)) {
            $purchaseQuery .= <<<SEARCHCRTERIASQL
    AND (u.user_email  LIKE '%%%s%%' OR
         a.assignedTo  LIKE '%%%s%%' OR
         mf.meta_value LIKE '%%%s%%' OR
         ml.meta_value LIKE '%%%s%%')
SEARCHCRTERIASQL;

        } elseif (array_key_exists('assigned', $_GET)) {
            if ($_GET['assigned'] == 'yes') {
                $purchaseQuery .= " AND (a.assignedTo IS NOT NULL OR isClaimed = 1) ";
            } else {
                $purchaseQuery .= " AND (a.assignedTo IS NULL AND isClaimed = 0) ";
            }
        }

        $purchaseQuery .= ' GROUP BY a.purchased_ticket_hash ';

        if (array_key_exists($sq_key, $_GET)) {
            $purchaseQuery = $wpdb->prepare(
                $purchaseQuery,
                $_GET[$sq_key],
                $_GET[$sq_key],
                $_GET[$sq_key],
                $_GET[$sq_key]
            );
        }

        // All Results - To determine total number of pages
        $purchasesAllResults = count($wpdb->get_results($purchaseQuery));

        // Page?
        if ($page != '') {
            $offset = (($page - 1) * $results_per_page);
            $purchaseQuery .= ' LIMIT ' . $offset . ', ' . $results_per_page;
        }

        $data['purchases'] = $wpdb->get_results($purchaseQuery);
        $data[$sq_key]     = htmlspecialchars($_GET[$sq_key]);

        if (is_null($data['purchaseAvail'])) {
            $data['purchaseAvail'] = 0;
        }

        $data['counters'] = array(
            array(
                'name'  => __('All', EVENTAPPI_PLUGIN_NAME),
                'count' => $data['purchaseCount'],
                'link'  => ''
            ),
            array(
                'name'  => __('Anonymous', EVENTAPPI_PLUGIN_NAME),
                'count' => $data['purchaseAvail'],
                'link'  => '&assigned=no'
            ),
            array(
                'name'  => __('Claimed or Assigned', EVENTAPPI_PLUGIN_NAME),
                'count' => $data['purchaseCount'] - $data['purchaseAvail'],
                'link'  => '&assigned=yes'
            )
        );

        // Total Pages
        $data['total_pages'] = ceil($purchasesAllResults / $results_per_page);

        // Current page
        $data['page'] = $page;

        return $data;
    }

    private function ticketIsOnSale(array $ticketMeta)
    {
        $saleStart = $ticketMeta[EVENTAPPI_POST_NAME . '_ticket_sale_start'];
        if ($saleStart === false) {
            $saleStart = strtotime('yesterday');
        }

        $saleEnd = $ticketMeta[EVENTAPPI_POST_NAME . '_ticket_sale_end'];
        if ($saleEnd === false) {
            $saleEnd = strtotime('tomorrow');
        }

        if ($saleStart < strtotime('now') && $saleEnd > strtotime('now')) {
            return true;
        }

        return false;
    }

    public function filterThePostContent($content)
    {
        if (is_post_type_archive()) {
            return $content; // unmolested
        }

        $thePost = get_post();

        if ($thePost->post_type !== EVENTAPPI_POST_NAME) {
            return $content; // unmolested
        }

        $metaContent      = '';
        $objectTaxonomies = get_object_taxonomies($thePost->post_type, 'objects');
        foreach ($objectTaxonomies as $taxonomy_slug => $taxonomy) {
            if ($taxonomy->label === 'Categories') {
                $terms = get_the_terms($thePost->ID, $taxonomy_slug);
                if (!empty($terms)) {
                    $metaContent .= '<label>' . $taxonomy->label . ':</label>';
                    $i = 0;
                    foreach ($terms as $term) {
                        $metaContent .= ($i > 0) ? ', ' : '';
                        $metaContent .= $term->name;
                        $i ++;
                    }
                }
            }
        }

        $thePostMeta = get_post_meta($thePost->ID);

        // Only get the term if there is a Venue ID associated with the Event
        $venueId      = (int)$thePostMeta[EVENTAPPI_POST_NAME.'_venue_select'][0];

        if($venueId > 0) {
            $theVenue = get_term($venueId, 'venue', ARRAY_A);
            $theVenueMeta = get_tax_meta_all($venueId);
            $theAddress   = implode(', ', $theVenueMeta);
            $theAdrLink   = str_replace(' ', '%20', $theAddress);
        }

        $theTickets = wp_get_object_terms($thePost->ID, 'ticket');
        foreach ($theTickets as $tixIndex => $ticket) {
            $theTixMeta = get_tax_meta_all($ticket->term_id);
            $total      = intval($theTixMeta[EVENTAPPI_POST_NAME . '_ticket_available']);
            $sold       = intval($theTixMeta[EVENTAPPI_POST_NAME . '_ticket_sold']);
            $avail      = $total - $sold;
            $price      = money_format('%i', (intval($theTixMeta[EVENTAPPI_POST_NAME . '_ticket_cost']) / 100));

            if ($this->ticketIsOnSale($theTixMeta)) {
                $theTickets[$tixIndex]->ticket_id = $theTixMeta[EVENTAPPI_POST_NAME . '_ticket_api_id'];
                $theTickets[$tixIndex]->cost      = $theTixMeta[EVENTAPPI_POST_NAME . '_ticket_cost'];
                $theTickets[$tixIndex]->avail     = $avail;
                $theTickets[$tixIndex]->price     = $price;
            } else {
                unset($theTickets[$tixIndex]);
            }
        }

        $data = array(
            'thePostId'  => $thePost->ID,
            'eventId'    => $thePostMeta[EVENTAPPI_POST_NAME . '_id'][0],
            'formAction' => get_permalink(get_page_by_path(EVENTAPPI_PLUGIN_NAME . '-cart')),
            'startDate'  => date(
                get_option('date_format'),
                $thePostMeta[EVENTAPPI_POST_NAME . '_start_date'][0]
            ),
            'startTime'  => date(
                get_option('time_format'),
                strtotime($thePostMeta[EVENTAPPI_POST_NAME . '_start_time'][0])
            ),
            'endDate'    => date(
                get_option('date_format'),
                $thePostMeta[EVENTAPPI_POST_NAME . '_end_date'][0]
            ),
            'endTime'    => date(
                get_option('time_format'),
                strtotime($thePostMeta[EVENTAPPI_POST_NAME . '_end_time'][0])
            ),
            'theVenue'   => $theVenue,
            'theAddress' => $theAddress,
            'theAdrLink' => $theAdrLink,
            'theTickets' => $theTickets
        );

        return $content . Parser::instance()->parseTemplate('single-event', $data);
    }

    public function saveVenueEntry($venueId)
    {
        global $wpdb;
        $weHaveAVenue = false;

        foreach ($_REQUEST as $key => $value) {
            if (substr($key, 0, 22) === EVENTAPPI_POST_NAME . '_venue_') {
                $keyParts     = explode('_venue_', $key);
                $var          = $keyParts[1];
                $$var         = $value;
                $weHaveAVenue = true;
            }
        }

        if (!$weHaveAVenue) {
            return;
        }

        if (array_key_exists(EVENTAPPI_POST_NAME . '_venue_name', $_POST) &&
            !empty($_POST[EVENTAPPI_POST_NAME . '_venue_name'])
        ) {
            $venueName = $_POST[EVENTAPPI_POST_NAME . '_venue_name'];
        } elseif (array_key_exists('tag-name', $_REQUEST)) {
            $venueName = $_REQUEST['tag-name'];
        } else {
            $venueName = $_REQUEST['name'];
        }

        $data = array(
            'name'      => $venueName,
            'address_1' => ${'address_1'},
            'address_2' => ${'address_2'},
            'address_3' => ${'city'},
            'address_4' => null,
            'postcode'  => ${'postcode'},
            'country'   => ${'country'}
        );

        $venueTable = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_venues';
        $sql        = <<<CHECKVENUESQL
SELECT `api_id` FROM {$venueTable}
WHERE `wp_id` = %d
CHECKVENUESQL;
        $apiKey     = $wpdb->get_var(
            $wpdb->prepare(
                $sql,
                $venueId
            )
        );

        $apiVenue = array();
        if (!is_null($apiKey)) {
            $apiVenue = ApiClient::instance()->showVenue($apiKey);
        }

        if (array_key_exists('data', $apiVenue)) {
            // we already have something saved on the API - let's update it
            $data['slug'] = $_REQUEST['slug'];
            $updateVenue  = ApiClient::instance()->updateVenue($apiKey, $data);
            if (array_key_exists('code', $updateVenue) &&
                $updateVenue['code'] === ApiClientInterface::RESPONSE_OK
            ) {
                $sql = <<<UPDATEVENUESQL
UPDATE {$venueTable}
SET `address_1` = %s,
    `address_2` = %s,
    `city` = %s,
    `postcode` = %s,
    `country` = %s
WHERE `wp_id` = %d AND `api_id` = %d
UPDATEVENUESQL;
                $wpdb->query(
                    $wpdb->prepare(
                        $sql,
                        ${'address_1'},
                        ${'address_2'},
                        ${'city'},
                        ${'postcode'},
                        ${'country'},
                        $venueId,
                        $apiKey
                    )
                );
            }
        } else {
            // Store a new venue on the API
            $newVenue = ApiClient::instance()->storeVenue($data);
            if (array_key_exists('data', $newVenue)) {
                $newVenue = $newVenue['data'];
                $sql      = <<<NEWVENUESQL
INSERT INTO {$venueTable} (`wp_id`, `api_id`, `address_1`, `address_2`, `city`, `postcode`, `country`)
VALUES (%d, %d, %s, %s, %s, %s, %s)
NEWVENUESQL;
                $wpdb->query(
                    $wpdb->prepare(
                        $sql,
                        $venueId,
                        $newVenue['id'],
                        ${'address_1'},
                        ${'address_2'},
                        ${'city'},
                        ${'postcode'},
                        ${'country'}
                    )
                );
            }
        }
    }

    public function loadScripts()
    {
        global $post;

        // Only load the scripts if we are on add/edit event page
        if (!isset($post) || EVENTAPPI_POST_NAME != $post->post_type) {
            return;
        }

//        wp_localize_script(
//            'eventappi-frontend',
//            EVENTAPPI_PLUGIN_NAME . '_ajax_obj',
//            array('ajax_url' => admin_url('admin-ajax.php'))
//        );
    }

    public function loadUpdateAttendeeStatusCallback()
    {
        $event_id = (int) $_POST['event_id'];
        $check    = sanitize_text_field($_POST['check']);
        $state    = sanitize_text_field($_POST['state']);

        if ($event_id && $check && $state) {
            // The method will output the JSON to the front-end
            $this->updateAttendeeCheckinStatus($event_id, $check, $state, true);
            exit;
        }
    }
}
