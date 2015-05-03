<?php namespace EventAppi;

use Tax_Meta_Class;
use EventAppi\Helpers\CountryList as CountryHelper;
use EventAppi\Helpers\Logger;
use EventAppi\Helpers\Options;

/**
 * Class EventPostType
 *
 * @package EventAppi
 */
class EventPostType
{
    const MAX_LITE_TICKETS = 500;
    const REGEX_SHA_1 = '/^[0-9a-f]{40}$/i'; // excatly 40 hex characters

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
        add_action('admin_init', array($this, 'registerSettings'));

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

        add_filter('the_content', array($this, 'filterThePostContent'));
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
            User::instance()->addUserNotice([
                'class'   => 'update-nag',
                'message' => 'Please enter your EventAppi license key and payment gateway settings to use the plugin.<br>' .
                             'Need a license key? Please visit <a href="http://eventappi.com/pricing/">EventAppi.com</a> for details.'
            ]);
            $this->setupInactiveMenu();
        }
    }

    public function addTicketsMetabox()
    {
        add_meta_box(
            EVENTAPPI_POST_NAME . '_tickets',
            __('Tickets Management (add / edit / remove)', EVENTAPPI_POST_NAME),
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
                'name' => 'Start Date',
                'desc' => 'Event start date',
                'id'   => EVENTAPPI_POST_NAME . '_start_date',
                'type' => 'date',
                'cols' => '6'
            ),
            array(
                'name' => 'Start Time',
                'desc' => 'Event start time',
                'id'   => EVENTAPPI_POST_NAME . '_start_time',
                'type' => 'time',
                'cols' => '6'
            ),
            array(
                'name' => 'End Date',
                'desc' => 'Event end date',
                'id'   => EVENTAPPI_POST_NAME . '_end_date',
                'type' => 'date',
                'cols' => '6'
            ),
            array(
                'name' => 'End Time',
                'desc' => 'Event end time',
                'id'   => EVENTAPPI_POST_NAME . '_end_time',
                'type' => 'time',
                'cols' => '6'
            ),
            array(
                'id'       => EVENTAPPI_POST_NAME . '_venue_select',
                'name'     => 'Select Venue',
                'type'     => 'taxonomy_select',
                'taxonomy' => 'venue',
                'multiple' => false,
                'cols'     => '10'
            ),
            array(
                'id'   => EVENTAPPI_POST_NAME . '_api_id',
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

    function editColumns($columns)
    {
        $columns = array(
            'cb'                                => '<input id="cb-select-all-1" type="checkbox">',
            'title'                             => 'Event',
            EVENTAPPI_POST_NAME . '_thumb'      => 'Featured Image',
            EVENTAPPI_POST_NAME . '_categories' => 'Categories',
            EVENTAPPI_POST_NAME . '_start_date' => 'Start Date',
            EVENTAPPI_POST_NAME . '_start_time' => 'Start Time',
            EVENTAPPI_POST_NAME . '_end_date'   => 'End Date',
            EVENTAPPI_POST_NAME . '_end_time'   => 'End Time',
            EVENTAPPI_POST_NAME . '_venue'      => 'Venue',
            EVENTAPPI_POST_NAME . '_actions'    => 'Actions'
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
                    $start_date = date(get_option('date_format'), strtotime($start_date));
                }
                echo '<em>' . $start_date . '</em>';
                break;

            case EVENTAPPI_POST_NAME . '_start_time':
                $start_time = '';
                if (isset($custom[EVENTAPPI_POST_NAME . '_start_time'][0])) {
                    $start_time = $custom[EVENTAPPI_POST_NAME . '_start_time'][0];
                    $start_time = date(get_option('time_format'), strtotime($start_time));
                }
                echo '<em>' . $start_time . '</em>';
                break;

            case EVENTAPPI_POST_NAME . '_end_date':
                $end_date = '';
                if (isset($custom[EVENTAPPI_POST_NAME . '_end_date'][0])) {
                    $end_date = $custom[EVENTAPPI_POST_NAME . '_end_date'][0];
                    $end_date = date(get_option('date_format'), strtotime($end_date));
                }
                echo '<em>' . $end_date . '</em>';
                break;

            case EVENTAPPI_POST_NAME . '_end_time':
                $end_time = '';
                if (isset($custom[EVENTAPPI_POST_NAME . '_end_time'][0])) {
                    $end_time = $custom[EVENTAPPI_POST_NAME . '_end_time'][0];
                    $end_time = date(get_option('time_format'), strtotime($end_time));
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

        if ($_REQUEST['action'] === 'trash') {
            // TODO: we probably need to deal with deletions at some point
            return;
        }

        if (!array_key_exists('post_title', $_REQUEST) && !array_key_exists('eventappi_event_name', $_POST)) {
            // we don't have enough data to create an event.
            return;
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
        } elseif (array_key_exists('eventappi_event_name', $_POST) && array_key_exists('desc', $_POST)) {
            $eventName = $_POST['eventappi_event_name'];
            $eventDesc = $_POST['desc'];
        }

        $venueId  = 0;
        $venueIdA = $_REQUEST[EVENTAPPI_POST_NAME . '_venue_select'];
        if (!is_null($venueIdA)) {
            foreach ($venueIdA as $vId) {
                $venueId = $vId;
            }
        }

        if (array_key_exists('eventappi_event_venue_name', $_POST) &&
            !empty($_POST['eventappi_event_venue_name'])
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
            update_post_meta($postId, 'eventappi_event_start_date', $startDate);
            update_post_meta($postId, 'eventappi_event_start_time', $startTime);
            update_post_meta($postId, 'eventappi_event_end_date', $endDate);
            update_post_meta($postId, 'eventappi_event_end_time', $endTime);
            update_post_meta($postId, 'eventappi_event_venue_select', $venueId);
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

        $apiEventId = get_post_meta($postId, 'eventappi_event_id', true);
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

            update_post_meta($postId, 'eventappi_event_id', $newEvent['data']['id']);
        }

        $this->saveTickets($postId);
    }

    public function saveTickets($postId)
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

        /* OK, it's safe for us to save the data now. */
        $tickets = $_POST[EVENTAPPI_POST_NAME . '_ticket_name'];

        $ticketTotal = 0;
        foreach ($tickets as $index => $ticket) {
            $ticketTotal += intval($_POST[EVENTAPPI_POST_NAME . '_ticket_available'][$index]);
        }
        if ($ticketTotal > self::MAX_LITE_TICKETS) {
            User::instance()->addUserNotice([
                'class' => 'error',
                'message' => 'You cannot have more than ' . self::MAX_LITE_TICKETS . ' tickets per event.'
            ]);
            return;
        }

        foreach ($tickets as $index => $ticket) {

            $term = wp_insert_term(
                $ticket,
                'ticket',
                array(
                    'description' => $_POST[EVENTAPPI_POST_NAME . '_ticket_description'][$index],
                    'slug'        => $postId . '-ticket-' . $index
                )
            );
            if (is_a($term, 'WP_Error')) {
                $term = get_term_by('slug', $postId . '-ticket-' . $index, 'ticket', ARRAY_A);
            }

            wp_set_object_terms(
                $postId,
                $term['term_id'],
                'ticket',
                ($index > 0)
            );

            $saleStart = $_POST[EVENTAPPI_POST_NAME . '_ticket_sale_start'][$index];
            if (empty($saleStart)) {
                $saleStart = date('m/d/Y', strtotime('now'));
            }
            update_tax_meta(
                $term['term_id'],
                EVENTAPPI_POST_NAME . '_ticket_sale_start',
                $saleStart
            );

            $saleEnd = $_POST[EVENTAPPI_POST_NAME . '_ticket_sale_end'][$index];
            if (empty($saleEnd)) {
                $eventStartDate = get_post_meta($postId, EVENTAPPI_POST_NAME . '_start_date', true);
                if (empty($eventStartDate)) {
                    $eventStartDate = $_POST[EVENTAPPI_POST_NAME . '_start_date']['cmb-field-0'];
                }
                $saleEnd = date('m/d/Y', strtotime($eventStartDate));
            }
            update_tax_meta(
                $term['term_id'],
                EVENTAPPI_POST_NAME . '_ticket_sale_end',
                $saleEnd
            );

            update_tax_meta(
                $term['term_id'],
                EVENTAPPI_POST_NAME . '_ticket_cost',
                intval(floatval($_POST[EVENTAPPI_POST_NAME . '_ticket_cost'][$index]) * 100)
            );
            update_tax_meta(
                $term['term_id'],
                EVENTAPPI_POST_NAME . '_ticket_available',
                $_POST[EVENTAPPI_POST_NAME . '_ticket_available'][$index]
            );
            update_tax_meta(
                $term['term_id'],
                EVENTAPPI_POST_NAME . '_ticket_sold',
                $_POST[EVENTAPPI_POST_NAME . '_ticket_sold'][$index]
            );
            update_tax_meta(
                $term['term_id'],
                EVENTAPPI_POST_NAME . '_ticket_price_type',
                $_POST[EVENTAPPI_POST_NAME . '_ticket_price_type'][$index]
            );

            $apiStartTime = date('Y-m-d H:i:s', strtotime(
                get_tax_meta($term['term_id'], EVENTAPPI_POST_NAME . '_ticket_sale_start') . ' 00:00:00'
            ));
            $apiEndTime   = date('Y-m-d H:i:s', strtotime(
                get_tax_meta($term['term_id'], EVENTAPPI_POST_NAME . '_ticket_sale_end') . ' 23:59:59'
            ));
            $ticketArray  = array(
                'event_id'    => $apiEventId,
                'name'        => $ticket,
                'description' => $_POST[EVENTAPPI_POST_NAME . '_ticket_description'][$index],
                'cost'        => intval(floatval($_POST[EVENTAPPI_POST_NAME . '_ticket_cost'][$index]) * 100),
                'available'   => $_POST[EVENTAPPI_POST_NAME . '_ticket_available'][$index],
                'sold'        => $_POST[EVENTAPPI_POST_NAME . '_ticket_sold'][$index],
                'price_type'  => $_POST[EVENTAPPI_POST_NAME . '_ticket_price_type'][$index],
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
                array($this, 'settingsPage')
            );
        }

        // Add Help Page
        add_submenu_page(
            $menu_slug,
            'EventAppi Help',
            'Help',
            $capability,
            EVENTAPPI_PLUGIN_NAME . '-help',
            array($this, 'helpPage')
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
                array($this, 'settingsPage')
            );
        }

        // Add Help Page
        add_submenu_page(
            EVENTAPPI_POST_NAME,
            'EventAppi Help',
            'Help',
            $capability,
            EVENTAPPI_PLUGIN_NAME . '-help',
            array($this, 'helpPage')
        );
    }

    public function inactiveNotice()
    {
        wp_die('Looks like the EventAppi plugin is inactive. Please check your license key in the Settings menu.');
    }

    public function helpPage()
    {
        if (!current_user_can('manage_' . EVENTAPPI_PLUGIN_NAME)) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        include 'Templates/pluginHelp.php';
    }

    public function settingsPage()
    {
        if (!current_user_can('manage_' . EVENTAPPI_PLUGIN_NAME)) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        $settings  = $this->getSettingsArray();
        $tabHeader = array();

        // set up some bits for the template
        $tab = '';
        if (array_key_exists('tab', $_GET) && !empty($_GET['tab'])) {
            $tab = $_GET['tab'];
        } else {
            $tab = 'General';
        }

        foreach ($settings as $section => $data) {
            $class = 'nav-tab';
            if ($section === $tab) {
                $class .= ' nav-tab-active';
            }

            $link = add_query_arg(array('tab' => $section));
            if (isset($_GET['settings-updated'])) {
                $link = remove_query_arg('settings-updated', $link);
            }

            $url                 = site_url() . $link;
            $tabHeader[$section] = "<a href=\"{$url}\" class=\"{$class}\">{$data['title']}</a>";
        }

        include __DIR__ . '/Templates/PluginSettings.php';
    }

    public function getSettingsArray()
    {
        return array(
            'General' => array(
                'title'       => __('General', EVENTAPPI_PLUGIN_NAME),
                'description' => __('Here you can activate your EventAppi Plugin.', EVENTAPPI_PLUGIN_NAME),
                'fields'      => array(
                    array(
                        'id'          => 'license_key',
                        'label'       => __('License Key', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Enter your License Key here.', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => __('License Key', EVENTAPPI_PLUGIN_NAME)
                    ),
                    array(
                        'id'          => 'api_endpoint',
                        'label'       => __('EventAppi API Endpoint', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('This can be found on your license key confirmation email.',
                            EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => 'https://eventappi.com/api/v1',
                        'placeholder' => 'https://eventappi.com/api/v1'
                    ),
                    array(
                        'id'          => 'license_key_checkpoint',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => '1426768000',
                        'force'       => true
                    ),
                    array(
                        'id'          => 'license_key_status',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'invalid'
                    ),
                    //Outlines Section
                    array(
                        'id'          => 'upgrade',
                        'label'       => '<h3>' . __('Upgrade', EVENTAPPI_PLUGIN_NAME) . '</h3>',
                        'description' => 'You can view upgrade and pricing plans availiable to you at: <a href="http://eventappi.com/pricing">http://eventappi.com/pricing</a>',
                        'type'        => '',
                        'placeholder' => '',
                    )
                )
            ),
            'Gateway' => array(
                'title'       => __('Gateway', EVENTAPPI_PLUGIN_NAME),
                'description' => __('Here you can choose your gateway settings.', EVENTAPPI_PLUGIN_NAME),
                'fields'      => array(
                    array(
                        'id'          => 'gateway',
                        'label'       => __('Select a payment gateway', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Choose your gateway.', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'select',
                        'options'     => array(
                            ''               => '-- SELECT --',
                            'twocheckout'    => '2Checkout',
                            'authorizenet'   => 'Authorize.Net',
                            'buckaroo'       => 'Buckaroo',
                            'cardsave'       => 'CardSave',
                            'coinbase'       => 'Coinbase',
                            'dummy'          => 'Dummy',
                            'eway'           => 'eWAY',
                            'firstdata'      => 'First Data',
                            'gocardless'     => 'GoCardless',
                            'manual'         => 'Manual',
                            'migs'           => 'Migs',
                            'mollie'         => 'Mollie',
                            'multisafepay'   => 'MultiSafepay',
                            'netaxept'       => 'Netaxept (BBS)',
                            'netbanx'        => 'Netbanx',
                            'payfast'        => 'PayFast',
                            'payflow'        => 'Payflow',
                            'paymentexpress' => 'PaymentExpress (DPS)',
                            'paypal'         => 'PayPal Rest',
                            'pin'            => 'Pin Payments',
                            'sagepay'        => 'Sage Pay',
                            'securepay'      => 'SecurePay',
                            'stripe'         => 'Stripe',
                            'targetpay'      => 'TargetPay',
                            'worldpay'       => 'WorldPay'
                        ),
                        'default'     => 'PayPal',
                        'placeholder' => __('Gateway', EVENTAPPI_PLUGIN_NAME)
                    ),
                    array(
                        'id'          => 'gateway_twocheckout_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'TwoCheckout'
                    ),
                    array(
                        'id'          => 'gateway_twocheckout_accountNumber',
                        'label'       => 'Account number',
                        'description' => 'Your 2Checkout account number.',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_twocheckout_secretWord',
                        'label'       => 'Secret word',
                        'description' => 'Your 2Checkout secret word',
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_twocheckout_testMode',
                        'label'       => 'Test mode',
                        'description' => 'Process all transactions on the test gateway',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_authorizenet_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'AuthorizeNet_AIM'
                    ),
                    array(
                        'id'          => 'gateway_authorizenet_apiLoginId',
                        'label'       => 'API Login ID',
                        'description' => 'Authorize.net API login ID',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_authorizenet_transactionKey',
                        'label'       => 'Transaction Key',
                        'description' => 'Authorize.net transaction key',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_authorizenet_testMode',
                        'label'       => 'Test mode',
                        'description' => 'Process all transactions on the test gateway',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_authorizenet_developerMode',
                        'label'       => 'Developer mode',
                        'description' => 'Authorize.net Developer Mode',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_buckaroo_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Buckaroo'
                    ),
                    array(
                        'id'          => 'gateway_buckaroo_websiteKey',
                        'label'       => 'Website Key',
                        'description' => 'Your Buckaroo website key',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_buckaroo_secretKey',
                        'label'       => 'Secret Key',
                        'description' => 'Your Buckaroo secret key',
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_buckaroo_testMode',
                        'label'       => 'Test mode',
                        'description' => 'Buckaroo Developer Mode',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_cardsave_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'CardSave'
                    ),
                    array(
                        'id'          => 'gateway_cardsave_merchantId',
                        'label'       => 'Merchant ID',
                        'description' => 'Your Cardsave merchant ID',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_cardsave_password',
                        'label'       => 'Password',
                        'description' => 'Your CardSave password',
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_coinbase_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Coinbase'
                    ),
                    array(
                        'id'          => 'gateway_coinbase_apiKey',
                        'label'       => 'API Key',
                        'description' => 'Your Coinbase API key',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_coinbase_secret',
                        'label'       => 'Secret',
                        'description' => 'Your Coinbase secret',
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_coinbase_accountId',
                        'label'       => 'Account ID',
                        'description' => 'Your Coinbase account ID',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_dummy_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Dummy'
                    ),
                    array(
                        'id'          => 'gateway_dummy_info',
                        'label'       => 'Note:',
                        'description' => 'Card numbers ending in even numbers should result in successful payments. Payments with cards ending in odd numbers should fail.',
                        'type'        => 'hidden',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_eway_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Eway_Rapid'
                    ),
                    array(
                        'id'          => 'gateway_eway_apiKey',
                        'label'       => 'API Key',
                        'description' => 'Your eWAY API key',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_eway_password',
                        'label'       => 'Password',
                        'description' => 'Your eWAY password',
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_eway_testMode',
                        'label'       => 'Test mode',
                        'description' => 'eWAY Test Mode',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_firstdata_storeId',
                        'label'       => 'Store ID',
                        'description' => 'Your FirstData store ID',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_firstdata_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'FirstData_Connect'
                    ),
                    array(
                        'id'          => 'gateway_firstdata_sharedSecret',
                        'label'       => 'Shared Secret',
                        'description' => 'Your FirstData shared secret',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_firstdata_testMode',
                        'label'       => 'Test mode',
                        'description' => 'First Data Test Mode',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_gocardless_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'GoCardless'
                    ),
                    array(
                        'id'          => 'gateway_gocardless_appId',
                        'label'       => 'App ID',
                        'description' => 'Your GoCardless App ID',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_gocardless_appSecret',
                        'label'       => 'App Secret',
                        'description' => 'Your GoCardless App secret',
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_gocardless_merchantId',
                        'label'       => 'Merchant ID',
                        'description' => 'Your GoCardless merchant ID',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_gocardless_accessToken',
                        'label'       => 'Access Token',
                        'description' => 'Your GoCardless access token',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_gocardless_testMode',
                        'label'       => 'Test mode',
                        'description' => 'GoCardless Test Mode',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_migs_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Migs_TwoParty'
                    ),
                    array(
                        'id'          => 'gateway_migs_merchantId',
                        'label'       => 'Merchant ID',
                        'description' => 'Your MIGS merchant ID',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_migs_merchantAccessCode',
                        'label'       => 'Merchant Access Code',
                        'description' => 'Your MIGS merchant access code',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_migs_secureHash',
                        'label'       => 'Secure Hash',
                        'description' => 'Your MIGS secure hash',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_mollie_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Mollie'
                    ),
                    array(
                        'id'          => 'gateway_mollie_apiKey',
                        'label'       => 'API Key',
                        'description' => 'Your Mollie API key',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_multisafepay_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'MultiSafepay'
                    ),
                    array(
                        'id'          => 'gateway_multisafepay_accountId',
                        'label'       => 'Account ID',
                        'description' => 'Your MultiSafepay account ID',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_multisafepay_siteId',
                        'label'       => 'Site ID',
                        'description' => 'Your MultiSafepay site ID',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_multisafepay_siteCode',
                        'label'       => 'Site Code',
                        'description' => 'Your MultiSafepay site code',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_multisafepay_testMode',
                        'label'       => 'Test mode',
                        'description' => 'MultiSafepay Test Mode',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_netaxept_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Netaxept'
                    ),
                    array(
                        'id'          => 'gateway_netaxept_merchantId',
                        'label'       => 'Merchant ID',
                        'description' => 'Your Netaxept merchant ID',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_netaxept_password',
                        'label'       => 'Password',
                        'description' => 'Your Netaxept password',
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_netaxept_testMode',
                        'label'       => 'Test mode',
                        'description' => 'Netaxept Test Mode',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_netbanx_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'NetBanx'
                    ),
                    array(
                        'id'          => 'gateway_netbanx_accountNumber',
                        'label'       => 'Account Number',
                        'description' => 'Your NetBanx account number',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_netbanx_storeId',
                        'label'       => 'Store ID',
                        'description' => 'Your NetBanx store ID',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_netbanx_storePassword',
                        'label'       => 'Store Password',
                        'description' => 'Your NetBanx store password',
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_netbanx_testMode',
                        'label'       => 'Test mode',
                        'description' => 'NetBanx Test Mode',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_payfast_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'PayFast'
                    ),
                    array(
                        'id'          => 'gateway_payfast_merchantId',
                        'label'       => 'Merchant ID',
                        'description' => 'Your PayFast merchant ID',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_payfast_merchantKey',
                        'label'       => 'Merchant Key',
                        'description' => 'Your PayFast merchant key',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_payfast_pdtKey',
                        'label'       => 'PDT Key',
                        'description' => 'Your PayFast PDT key',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_payfast_testMode',
                        'label'       => 'Test mode',
                        'description' => 'PayFast Test Mode',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_payflow_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Payflow_Pro'
                    ),
                    array(
                        'id'          => 'gateway_payflow_username',
                        'label'       => 'Username',
                        'description' => 'Your Payflow username',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_payflow_password',
                        'label'       => 'Password',
                        'description' => 'Your Payflow password',
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_payflow_vendor',
                        'label'       => 'Vendor',
                        'description' => 'Your Payflow vendor',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_payflow_partner',
                        'label'       => 'Partner',
                        'description' => 'Your Payflow partner',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_payflow_testMode',
                        'label'       => 'Test mode',
                        'description' => 'Payflow Test Mode',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_paymentexpress_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'PaymentExpress_PxPost'
                    ),
                    array(
                        'id'          => 'gateway_paymentexpress_username',
                        'label'       => 'Username',
                        'description' => 'Your PaymentExpress user name',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_paymentexpress_password',
                        'label'       => 'Password',
                        'description' => 'Your PaymentExpress password',
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_paypal_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'PayPal_Rest'
                    ),
                    array(
                        'id'          => 'gateway_paypal_clientId',
                        'label'       => 'Client ID',
                        'description' => 'Your PayPal Rest client ID',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_paypal_secret',
                        'label'       => 'Secret',
                        'description' => 'Your PayPal Rest secret',
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_paypal_testMode',
                        'label'       => 'Test mode',
                        'description' => 'PIN Test Mode',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_pin_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Pin'
                    ),
                    array(
                        'id'          => 'gateway_pin_secretKey',
                        'label'       => 'Secret Key',
                        'description' => 'Your Pin secret key',
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_pin_testMode',
                        'label'       => 'Test mode',
                        'description' => 'Pin Test Mode',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_sagepay_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'SagePay_Server'
                    ),
                    array(
                        'id'          => 'gateway_sagepay_vendor',
                        'label'       => 'Vendor',
                        'description' => 'Your Sage Pay vendor',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_sagepay_testMode',
                        'label'       => 'Test mode',
                        'description' => 'Sage Pay Test Mode',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_sagepay_simulatorMode',
                        'label'       => 'Simulator mode',
                        'description' => 'SagePay simulator mode',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_securepay_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'SecurePay'
                    ),
                    array(
                        'id'          => 'gateway_securepay_merchantId',
                        'label'       => 'Merchant ID',
                        'description' => 'Your Securepay merchant id',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_securepay_transactionPassword',
                        'label'       => 'Transaction Password',
                        'description' => 'Your Securepay transaction password',
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_securepay_testMode',
                        'label'       => 'Test mode',
                        'description' => 'Secure Pay Test Mode',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_stripe_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Stripe'
                    ),
                    array(
                        'id'          => 'gateway_stripe_apiKey',
                        'label'       => 'API Key',
                        'description' => 'Your Stripe API key',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_targetpay_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'TargetPay_Ideal'
                    ),
                    array(
                        'id'          => 'gateway_targetpay_subAccountId',
                        'label'       => 'Sub Account ID',
                        'description' => 'Your TargetPay sub-account ID',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_worldpay_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'WorldPay'
                    ),
                    array(
                        'id'          => 'gateway_worldpay_installationId',
                        'label'       => 'Installation ID',
                        'description' => 'Your WorldPay installation ID',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_worldpay_accountId',
                        'label'       => 'Account ID',
                        'description' => 'Your WorldPay account id',
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_worldpay_secretWord',
                        'label'       => 'Secret Word',
                        'description' => 'Your Worldpay secret word',
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_worldpay_callbackPassword',
                        'label'       => 'Callback Password',
                        'description' => 'Your Worldpay callback password',
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_worldpay_testMode',
                        'label'       => 'Test mode',
                        'description' => 'WorldPay Test Mode',
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    )
                )
            )
        );
    }

    public function registerSettings()
    {
        $settings = $this->getSettingsArray();

        if (array_key_exists('tab', $_REQUEST)) {
            $current_section = $_REQUEST['tab'];
        } else {
            $current_section = 'General';
        }

        foreach ($settings as $section => $data) {
            if ($current_section !== $section) {
                continue;
            }

            add_settings_section(
                $section,
                null,
                array($this, 'settingsSection'),
                EVENTAPPI_PLUGIN_NAME . '_settings'
            );

            foreach ($data['fields'] as $field) {
                $validation = '';
                if (array_key_exists('callback', $field)) {
                    $validation = $field['callback'];
                }

                register_setting(
                    EVENTAPPI_PLUGIN_NAME . '_settings',
                    EVENTAPPI_PLUGIN_NAME . '_' . $field['id'],
                    $validation
                );

                if ($field['id'] === 'license_key') {
                    $license_key = Options::instance()->getPluginOption($field['id']);

                    if (
                        $license_key !== false &&
                        $license_key !== '' &&
                        LicenseKeyManager::instance()->checkLicenseKey()
                    ) {
                        $field['description'] = 'Your plugin is registered and currently ' .
                                                '<span style="color:#009900;font-weight:bold;">Active</span>';

                        if (!$_POST) {
                            add_settings_error(
                                'license_key',
                                'settings_updated',
                                'Your plugin was successfully Activated.',
                                'updated'
                            );
                        }
                    } else {
                        $field['description'] = 'Your LicenseKey is currently ' .
                                                '<span style="color:#990000;font-weight:bold;">Inactive</span>';

                        if (!$_POST) {
                            add_settings_error(
                                'license_key',
                                'settings_updated',
                                'The license key you provided is invalid.<br>Please try again or contact EventAppi Support,' .
                                ' using the following contact details:<br><ul><li>Telephone: 020202020202</li>' .
                                '<li>Email: admin@webplunder.com</li></ul>'
                            );
                        }
                    }
                }

                add_settings_field(
                    $field['id'],
                    $field['label'],
                    array($this, 'displayField'),
                    EVENTAPPI_PLUGIN_NAME . '_settings',
                    $section,
                    array('field' => $field, 'prefix' => EVENTAPPI_PLUGIN_NAME . '_')
                );
            }

            if (!$current_section) {
                break;
            }
        }
    }

    public function settingsSection($section)
    {
        $settings = $this->getSettingsArray();

        if (array_key_exists('tab', $_GET)) {
            $tab = $_GET['tab'];
        } else {
            $tab = 'General';
        }
        $html = '<h2 class="nav-tab-wrapper">';

        foreach ($settings as $section => $data) {
            $class = 'nav-tab';
            if ($section == $tab) {
                $class .= ' nav-tab-active';
            }

            $link = add_query_arg(array('tab' => $section));
            if (isset($_GET['settings-updated'])) {
                $link = remove_query_arg('settings-updated', $link);
            }

            $html .= "<a href=\"{$link}\" class=\"{$class}\">{$data['title']}</a>";
        }

        $html .= '</h2>';

        echo $html;
    }

    public function displayField($data)
    {
        $field       = $data['field'];
        $option_name = $data['prefix'] . $field['id'];

        $option = get_option($option_name);

        if (array_key_exists('default', $field) &&
            ((array_key_exists('force', $field) && $field['force'] === true) ||
             empty($option))
        ) {
            $option = $field['default'];
        }

        if (array_key_exists('class', $field)) {
            $class = $field['class'];
        } else {
            $class = '';
        }

        $html = '';
        switch ($field['type']) {

            case 'text':
            case 'url':
            case 'email':
                $html .= '<input id="' . esc_attr($field['id']) . '" class="' . esc_attr($class) .
                         '" type="text" name="' . esc_attr($option_name) . '" placeholder="' .
                         esc_attr($field['placeholder']) . '" value="' . esc_attr($option) . '" />';
                break;

            case 'password':
            case 'number':
            case 'hidden':
                $min = '';
                if (isset($field['min'])) {
                    $min = ' min="' . esc_attr($field['min']) . '"';
                }

                $max = '';
                if (isset($field['max'])) {
                    $max = ' max="' . esc_attr($field['max']) . '"';
                }
                $html .= '<input id="' . esc_attr($field['id']) . '" type="' .
                         esc_attr($field['type']) . '" name="' . esc_attr($option_name) .
                         '" placeholder="' . esc_attr($field['placeholder']) . '" value="' .
                         esc_attr($option) . '"' . $min . '' . $max . '/>';
                break;

            case 'text_secret':
                $html .= '<input id="' . esc_attr($field['id']) . '" type="text" name="' .
                         esc_attr($option_name) . '" placeholder="' .
                         esc_attr($field['placeholder']) . '" value="" />';
                break;

            case 'textarea':
                $html .= '<textarea id="' . esc_attr($field['id']) . '" rows="5" cols="50" name="' .
                         esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) .
                         '">' . $option . '</textarea><br/>';
                break;

            case 'checkbox':
                $checked = '';
                if ($option && 'on' == $option) {
                    $checked = 'checked="checked"';
                }
                $html .= '<input id="' . esc_attr($field['id']) . '" type="' . esc_attr($field['type']) .
                         '" name="' . esc_attr($option_name) . '" ' . $checked . '/>' . "\n";
                break;

            case 'radio':
                foreach ($field['options'] as $k => $v) {
                    $checked = false;
                    if ($k == $option) {
                        $checked = true;
                    }
                    $html .= '<label for="' . esc_attr($field['id'] . '_' . $k) . '"><input type="radio" ' .
                             checked($checked, true, false) . ' name="' . esc_attr($option_name) .
                             '" value="' . esc_attr($k) . '" id="' . esc_attr($field['id'] . '_' . $k) .
                             '" /> ' . $v . '</label> ';
                }
                break;

            case 'select':
                $html .= '<select name="' . esc_attr($option_name) . '" id="' . esc_attr($field['id']) . '">';
                foreach ($field['options'] as $k => $v) {
                    $html .= '<option ' . selected($k, $option,
                            false) . ' value="' . esc_attr($k) . '">' . $v . '</option>';
                }
                $html .= '</select> ';
                break;

            case 'select_multi':
                $html .= '<select name="' . esc_attr($option_name) . '[]" id="' . esc_attr($field['id']) .
                         '" multiple="multiple">';
                foreach ($field['options'] as $k => $v) {
                    $selected = false;
                    if (in_array($k, $option)) {
                        $selected = true;
                    }
                    $html .= '<option ' . selected($selected, true,
                            false) . ' value="' . esc_attr($k) . '">' . $v . '</option>';
                }
                $html .= '</select> ';
                break;

            case 'button':
                $html .= '<button name="' . esc_attr($option_name) . '" id="' . esc_attr($field['id']) . '"> ' .
                         esc_attr($field['label']) . ' </button>';
                break;

            case 'image':
                $image_thumb = '';
                if ($option) {
                    $image_thumb = wp_get_attachment_thumb_url($option);
                }
                $html .= '<img id="' . $option_name . '_preview" class="image_preview" src="' . $image_thumb . '" /><br/>' . "\n";
                $html .= '<input id="' . $option_name . '_button" type="button" data-uploader_title="' . __('Upload an image',
                        EVENTAPPI_PLUGIN_NAME) . '" data-uploader_button_text="' . __('Use image',
                        EVENTAPPI_PLUGIN_NAME) . '" class="image_upload_button button" value="' . __('Upload new image',
                        EVENTAPPI_PLUGIN_NAME) . '" />' . "\n";
                $html .= '<input id="' . $option_name . '_delete" type="button" class="image_delete_button button" value="' . __('Remove image',
                        EVENTAPPI_PLUGIN_NAME) . '" />' . "\n";
                $html .= '<input id="' . $option_name . '" class="image_data_field" type="hidden" name="' . $option_name . '" value="' . $option . '"/><br/>' . "\n";
                break;

            case 'color':
                ?>
                <div class="color-picker" style="position:relative;">
                    <input type="text" name="<?php esc_attr_e($option_name, EVENTAPPI_PLUGIN_NAME);?>" class="color"
                           value="<?php esc_attr_e($option, EVENTAPPI_PLUGIN_NAME);?>"/>

                    <div style="position:absolute;background:#FFF;z-index:99;border-radius:100%;"
                         class="colorpicker"></div>
                </div>
                <?php
                break;

        }

        switch ($field['type']) {

            case 'radio':
            case 'select_multi':
                $html .= '<br/><span class="description">' . $field['description'] . '</span>';
                break;

            default:
                $html .= '<label for="' . esc_attr($field['id']) . '">' .
                         '<span class="description">' . $field['description'] . '</span>' .
                         '</label>';
                break;
        }
        echo $html;
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

    public function updateAttendeeCheckinStatus($eventId, $purchasedTicketHash, $status)
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
                User::instance()->addUserNotice([
                    'class'   => 'error',
                    'message' => 'The attendee is already checked in, and cannot be checked in again.'
                ]);
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
                'Email'        => $attendee->user_email,
                'First Name'   => $attendee->first_name,
                'Last Name'    => $attendee->last_name,
                'Display Name' => $attendee->display_name,
                'Checked In'   => ($attendee->isCheckedIn === '1') ? 'Yes' : 'No',
                'Assigned To'  => $attendee->assignedTo
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
            wp_die('You do not have sufficient permissions to access this page.');
        }

        global $wpdb;

        $data        = array();
        $eventPostID = $_GET['post'];

        $this->updateAllAttendeesCheckinStatusForEvent($eventPostID);

        $data['customPost']     = get_post_type_object(EVENTAPPI_POST_NAME);
        $data['postUrl']        = get_admin_url() . 'edit.php?post_type=' . EVENTAPPI_POST_NAME .
                                  '&page=' . EVENTAPPI_PLUGIN_NAME . "-attendees&post={$eventPostID}";
        $data['attendeesLabel'] = __('Attendees', EVENTAPPI_PLUGIN_NAME);
        $data['exportUrl']      = get_admin_url() . 'link.php?post_type=' . EVENTAPPI_POST_NAME .
                                  '&page=' . EVENTAPPI_PLUGIN_NAME . "-download-attendees&post={$eventPostID}";
        $data['eventPost']      = get_post($eventPostID);

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

        if (array_key_exists('s', $_GET)) {
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

        if (array_key_exists('s', $_GET)) {
            $attendeeQuery = $wpdb->prepare(
                $attendeeQuery,
                $eventPostID,
                $_GET['s'],
                $_GET['s'],
                $_GET['s'],
                $_GET['s']
            );
        } else {
            $attendeeQuery = $wpdb->prepare(
                $attendeeQuery,
                $eventPostID
            );
        }

        $data['attendees'] = $wpdb->get_results($attendeeQuery);
        $data['s']         = $_GET['s'];

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

        Parser::instance()->parseTemplate('list-event-attendees', $data, true);
    }

    public function purchasesPage()
    {
        if (!current_user_can('manage_' . EVENTAPPI_PLUGIN_NAME)) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        global $wpdb;
        $data = array();

        $eventPostID = $_GET['post'];

        $data['customPost']     = get_post_type_object(EVENTAPPI_POST_NAME);
        $data['purchasesLabel'] = __('Purchases', EVENTAPPI_PLUGIN_NAME);
        $data['postUrl']        = get_admin_url() . 'edit.php?post_type=' . EVENTAPPI_POST_NAME .
                                  '&page=' . EVENTAPPI_PLUGIN_NAME . "-purchases&post={$eventPostID}";
        $data['eventPost']      = get_post($eventPostID);

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
        if (array_key_exists('s', $_GET)) {
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

        if (array_key_exists('s', $_GET)) {
            $purchaseQuery = $wpdb->prepare(
                $purchaseQuery,
                $_GET['s'],
                $_GET['s'],
                $_GET['s'],
                $_GET['s']
            );
        }

        $data['purchases'] = $wpdb->get_results($purchaseQuery);
        $data['s']         = $_GET['s'];

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

        Parser::instance()->parseTemplate('list-event-purchases', $data, true);
    }

    private function ticketIsOnSale(array $ticketMeta)
    {
        $saleStart = $ticketMeta[EVENTAPPI_POST_NAME . '_ticket_sale_start'];
        if ($saleStart === false) {
            $saleStart = strtotime('yesterday');
        } else {
            $saleStart = strtotime($saleStart . ' 00:00:01');
        }

        $saleEnd = $ticketMeta[EVENTAPPI_POST_NAME . '_ticket_sale_end'];
        if ($saleEnd === false) {
            $saleEnd = strtotime('tomorrow');
        } else {
            $saleEnd = strtotime($saleEnd . ' 23:59:59');
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

        $theVenue     = get_term($thePostMeta['eventappi_event_venue_select'][0], 'venue', ARRAY_A);
        $theVenueMeta = get_tax_meta_all($thePostMeta['eventappi_event_venue_select'][0]);
        $theAddress   = implode(', ', $theVenueMeta);
        $theAdrLink   = str_replace(' ', '%20', $theAddress);

        $theTickets = wp_get_object_terms($thePost->ID, 'ticket');
        foreach ($theTickets as $tixIndex => $ticket) {
            $theTixMeta = get_tax_meta_all($ticket->term_id);
            $total      = intval($theTixMeta['eventappi_event_ticket_available']);
            $sold       = intval($theTixMeta['eventappi_event_ticket_sold']);
            $avail      = $total - $sold;
            $price      = money_format('%i', (intval($theTixMeta['eventappi_event_ticket_cost']) / 100));

            if ($this->ticketIsOnSale($theTixMeta)) {
                $theTickets[$tixIndex]->ticket_id = $theTixMeta['eventappi_event_ticket_api_id'];
                $theTickets[$tixIndex]->cost      = $theTixMeta['eventappi_event_ticket_cost'];
                $theTickets[$tixIndex]->avail     = $avail;
                $theTickets[$tixIndex]->price     = $price;
            } else {
                unset($theTickets[$tixIndex]);
            }
        }

        $data = array(
            'thePostId'  => $thePost->ID,
            'eventId'    => $thePostMeta['eventappi_event_id'][0],
            'formAction' => get_permalink(get_page_by_path(EVENTAPPI_PLUGIN_NAME . '-cart')),
            'startDate'  => date(get_option('date_format'), strtotime($thePostMeta['eventappi_event_start_date'][0])),
            'startTime'  => date(get_option('time_format'), strtotime($thePostMeta['eventappi_event_start_time'][0])),
            'endDate'    => date(get_option('date_format'), strtotime($thePostMeta['eventappi_event_end_date'][0])),
            'endTime'    => date(get_option('time_format'), strtotime($thePostMeta['eventappi_event_end_time'][0])),
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
            if (substr($key, 0, 22) === 'eventappi_event_venue_') {
                $keyParts     = explode('_venue_', $key);
                $var          = $keyParts[1];
                $$var         = $value;
                $weHaveAVenue = true;
            }
        }

        if (!$weHaveAVenue) {
            return;
        }

        if (array_key_exists('eventappi_event_venue_name', $_POST) && !empty($_POST['eventappi_event_venue_name'])) {
            $venueName = $_POST['eventappi_event_venue_name'];
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
}
