<?php namespace EventAppi;

use EventAppi\Helpers\CountryList;
use EventAppi\Helpers\Logger;
use EventAppi\Helpers\Sanitizer;
use EventAppi\Helpers\Validator;

/**
 * Class EventPostType
 *
 * @package EventAppi
 */
class EventPostType
{
    /**
     *
     */
    const REGEX_SHA_1 = '/^[0-9a-f]{40}$/i'; // exactly 40 hex characters

    /**
     * @var bool
     */
    public $alreadyFiltered = false;

    /**
     * @var EventPostType|null
     */
    private static $singleton = null;
    /**
     * @var
     */
    private $sortArrayKey;

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
        add_action('init', array($this, 'postTypeAndTaxonomies'));

        /* Event MetaBoxes */
        add_filter('cmb_meta_boxes', array($this, 'postTypeMetaBoxes')); // Details
        add_action('add_meta_boxes', array(TicketPostType::instance(), 'addTicketsMetabox')); // Tickets
        add_action('add_meta_boxes', array($this, 'addTimeZoneMetabox')); // TimeZone

        add_action('save_post', array($this, 'savePost'));

        add_action('post_updated', array($this, 'postUpdated'), 10, 3);


        // Manage the media files per user: each one will only see his/her own files
        add_filter('parse_query', array($this, 'ownMediaFiles'));

        /*
         * ------------------------
         * OVERVIEW PAGE ACTIONS
         * ------------------------
        */

        if (isset($_GET['post_type']) && $_GET['post_type'] == EVENTAPPI_POST_NAME) {
            // Custom Columns
            add_filter('manage_edit-' . EVENTAPPI_POST_NAME . '_columns', array($this, 'editColumns'));
            add_action('manage_posts_columns', array($this, 'prepareColumns'));
            add_action('manage_posts_custom_column', array($this, 'prepareCustomColumns'));

            // Make Columns Sortable
            add_filter('manage_edit-'.EVENTAPPI_POST_NAME.'_sortable_columns', array($this, 'sortableColumns'));

            // Sort and Filter
            add_action('pre_get_posts', array($this, 'customList'));
            add_filter('wp_count_posts', array($this, 'countsFix'), 10, 1000000);
        }

        add_action('template_redirect', array($this, 'overridePostDates'));

        // a filter for hidden meta fields
        add_filter(
            'cmb_field_types',
            function ($cmb_field_types) {
                $cmb_field_types['hidden'] = 'EventAppi\Helpers\CMBHiddenField';
                return $cmb_field_types;
            }
        );

        // A filter for DATE meta fields
        add_filter(
            'cmb_field_types',
            function ($cmb_field_types) {
                $cmb_field_types['date_ea'] = 'EventAppi\Helpers\CMBDateField';
                return $cmb_field_types;
            }
        );

        // A filter for TIME meta fields
        add_filter(
            'cmb_field_types',
            function ($cmb_field_types) {
                $cmb_field_types['time_ea'] = 'EventAppi\Helpers\CMBTimeField';
                return $cmb_field_types;
            }
        );

        add_filter('the_content', array($this, 'filterThePostContent'), 10000);

        add_action('wp_enqueue_scripts', array($this, 'loadScriptsFrontend'));
        add_action('admin_enqueue_scripts', array($this, 'loadScriptsAdmin'));

        add_action('admin_footer', array(TicketPostType::instance(), 'addTicketArea'));

        /* Alter Menu */
        add_filter('admin_menu', array($this, 'updateMenuSelectedItem'), 1000000); // late call to catch all items ;-)
        add_filter('admin_body_class', array($this, 'updateBodyClass'), 1000000);
    }

    /**
     *
     */
    public function addTimeZoneMetabox()
    {
        add_meta_box(
            EVENTAPPI_POST_NAME . '_timezone',
            __('Event TimeZone', EVENTAPPI_PLUGIN_NAME),
            array($this, 'eventTimeZoneArea'),
            EVENTAPPI_POST_NAME
        );
    }

    /**
     *
     */
    public function eventTimeZoneArea()
    {
        global $post;

        $data = array();

        $timeZoneNoEdit = (TicketPostType::instance()->getTickets($post->ID, 'count') > 0);
        $func = ($timeZoneNoEdit) ? 'update_post_meta' : 'delete_post_meta';
        $func($post->ID, EVENTAPPI_POST_NAME.'_timezone_no_edit', true);

        $data['no_edit'] = $timeZoneNoEdit;

        $data['timezone'] = get_post_meta($post->ID, EVENTAPPI_POST_NAME.'_timezone_string', true);

        echo Parser::instance()->parseEventAppiTemplate('Events/EventTimeZone', $data);
    }

    /**
     *
     */
    public function overridePostDates()
    {
        // the global WordPress post object
        //global $post;

        //if (is_object($post) && $post->post_type === EVENTAPPI_POST_NAME) {
        //            $date            = date_format(
        //                get_option('date_format'),
        //                get_post_meta( $post->ID, EVENTAPPI_POST_NAME . '_start_date' )[0]
        //            );
        //            $time            = date_format(
        //                'H:i:s',
        //                get_post_meta( $post->ID, EVENTAPPI_POST_NAME . '_start_time' )[0]
        //            );
        //            $post->post_date = date;
        //}
    }

    /**
     *
     */
    public function postTypeAndTaxonomies()
    {
        if (LicenseKeyManager::instance()->checkLicenseKey()) {
            $this->createPostType();

            EventVenueTax::instance()->createVenueTaxonomy();
            EventCatTax::instance()->createCategoryTaxonomy();

            $this->setupActiveMenu();

            if (is_admin()) {
                EventVenueTax::instance()->setupCustomVenueMeta();
            }
        } else {
            User::instance()->addUserNotice(['type' => 'LicenseKeyNotice']);
            $this->setupInactiveMenu();
        }
    }

    /**
     *
     */
    public function createPostType()
    {
        $labels = array(
            'name'               => _x('Events', 'post type general name', EVENTAPPI_PLUGIN_NAME),
            'singular_name'      => EVENTAPPI_PLUGIN_NICE_NAME . ' ' . _x('Event', 'post type singular name', EVENTAPPI_PLUGIN_NAME),
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

    /**
     * @param array $meta
     *
     * @return array
     */
    public function postTypeMetaBoxes(array $meta)
    {
        $eventFields = array(
            array(
                'name' => __('Start Date', EVENTAPPI_PLUGIN_NAME),
                'desc' => __('Event start date', EVENTAPPI_PLUGIN_NAME),
                'id'   => EVENTAPPI_POST_NAME . '_start_date',
                'type' => 'date_ea',
                'class' => 'start_date event eventappi',
                'attributes' => array('required' => 'required'),
                'cols' => '6'
            ),
            array(
                'name' => __('Start Time', EVENTAPPI_PLUGIN_NAME),
                'desc' => __('Event start time', EVENTAPPI_PLUGIN_NAME),
                'id'   => EVENTAPPI_POST_NAME . '_start_time',
                'type' => 'time_ea',
                'attributes' => array('required' => 'required'),
                'cols' => '6'
            ),
            array(
                'name' => __('End Date', EVENTAPPI_PLUGIN_NAME),
                'desc' => __('Event end date', EVENTAPPI_PLUGIN_NAME),
                'id'   => EVENTAPPI_POST_NAME . '_end_date',
                'type' => 'date_ea',
                'class' => 'end_date event eventappi',
                'cols' => '6'
            ),
            array(
                'name' => __('End Time', EVENTAPPI_PLUGIN_NAME),
                'desc' => __('Event end time', EVENTAPPI_PLUGIN_NAME),
                'id'   => EVENTAPPI_POST_NAME . '_end_time',
                'type' => 'time',
                'cols' => '6'
            ),
            array(
                'id'         => EVENTAPPI_POST_NAME . '_venue_select',
                'name'       => __('Select Venue', EVENTAPPI_PLUGIN_NAME),
                'type'       => 'taxonomy_select',
                'allow_none' => true,
                'taxonomy'   => 'venue',
                'multiple'   => false,
                'cols'       => '10'
            ),
            array(
                'id'         => EVENTAPPI_POST_NAME. '_email_reminder',
                'name'       => __('Email Reminder (days before event)', EVENTAPPI_PLUGIN_NAME),
                'type'       => 'number',
                'allow_none' => false,
                'cols'       => '8'
            )
        );

        $meta[] = array(
            'title'    => __('Event Details', EVENTAPPI_PLUGIN_NAME),
            'pages'    => EVENTAPPI_POST_NAME,
            'context'  => 'normal',
            'priority' => 'high',
            'fields'   => $eventFields
        );

        return $meta;
    }

    // [START] Overview Page Actions
    /**
     * @param $columns
     *
     * @return array
     */
    public function editColumns($columns)
    {
        $columns = array(
            'cb'                                    => '<input id="cb-select-all-1" type="checkbox">',
            'title'                                 => __('Event', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_thumb'          => __('Featured Image', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_categories'     => __('Categories', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_start_date'     => __('Start Date', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_start_time'     => __('Start Time', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_end_date'       => __('End Date', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_end_time'       => __('End Time', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_venue'          => __('Venue', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_email_reminder' => __('Email Reminder', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_POST_NAME . '_actions'        => __('Actions', EVENTAPPI_PLUGIN_NAME)
        );

        return $columns;
    }

    /**
     * @param $column
     */
    public function prepareCustomColumns($column)
    {
        global $post;
        $custom = get_post_custom();

        switch ($column) {
            case EVENTAPPI_POST_NAME . '_thumb':
                echo get_the_post_thumbnail($post->ID, array(64, 64)).
                (! $this->hasApiId($post->ID) ? '<br /><em class="ea-error">'.__(
                    'This event has no API ID.<br>Re-save the event to correct.',
                    EVENTAPPI_PLUGIN_NAME
                ).'</em>' : '');
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

            case EVENTAPPI_POST_NAME . '_email_reminder':
                $emailReminder = '';
                if (isset($custom[EVENTAPPI_POST_NAME . '_email_reminder'][0])) {
                    $emailReminder = $custom[EVENTAPPI_POST_NAME . '_email_reminder'][0];
                }
                echo '<em>' . $emailReminder . '</em>';
                break;

            case EVENTAPPI_POST_NAME . '_actions':
                $adminUrl = get_admin_url();
                $theLinks = "<a href=\"{$adminUrl}edit.php?post_type=" . EVENTAPPI_POST_NAME .
                            "&page=" . EVENTAPPI_PLUGIN_NAME . "-attendees&post={$post->ID}\">" .
                            "<span title=\"".__('Attendees', EVENTAPPI_PLUGIN_NAME)."\" class=\"dashicons dashicons-groups\"></span>" .
                            "</a>&nbsp;<a href=\"{$adminUrl}edit.php?post_type=" .
                            EVENTAPPI_POST_NAME . "&page=" . EVENTAPPI_PLUGIN_NAME . "-purchases&post={$post->ID}\">" .
                            "<span title=\"".__('Purchases', EVENTAPPI_PLUGIN_NAME)."\" class=\"dashicons dashicons-cart\"</span> </a>";
                echo $theLinks;
                break;
        }
    }

    /**
     * @param $columns
     *
     * @return mixed
     */
    public function sortableColumns($columns)
    {
        $columns[EVENTAPPI_POST_NAME.'_start_date'] = 'start_date';
        $columns[EVENTAPPI_POST_NAME.'_end_date'] = 'end_date';

        return $columns;
    }

    /**
     * @param $query
     */
    public function customList($query)
    {
        global $current_user;

        // Any Sorting Requested?
        $orderby = $query->get('orderby');

        if ($orderby != '') {
            if ('start_date' == $orderby) {
                $query->set('meta_key', EVENTAPPI_POST_NAME.'_start_date');
                $query->set('orderby', 'meta_value_num');
            }

            if ('end_date' == $orderby) {
                $query->set('meta_key', EVENTAPPI_POST_NAME.'_end_date');
                $query->set('orderby', 'meta_value_num');
            }
        }

        // Let's see if the user is Admin - if not, filter the events listed
        if (! in_array('administrator', $current_user->roles)) {
            $query->query_vars['author'] = $current_user->ID;
        }
    }

    /* As WordPress (due to an internal bug) does not update the total results based on author
     * we have to do it ourselves */
    /**
     * @param $counts
     *
     * @return mixed
     */
    public function countsFix($counts)
    {
        global $current_user, $wpdb;

        if (! in_array('administrator', $current_user->roles)) {
            $query = $wpdb->prepare(
                'SELECT COUNT(*) as count, post_status FROM `'.$wpdb->posts."` "
                . " WHERE post_status IN ('draft', 'publish', 'private', 'pending', 'trash') "
                . " && post_type = '%s' && (post_author = %d) "
                . " GROUP BY post_status "
                . " ORDER BY post_status", // important for the order of items in the array
                EVENTAPPI_POST_NAME, // Post Type
                $current_user->ID // Post Author
            );

            $results = $wpdb->get_results($query, ARRAY_A);

            $countsAll = (array)$counts;
            $countsUpdated = array();

            foreach ($results as $arr) {
                // We only have one row with the same status
                // The list can have one or more results / We go through all possible statuses
                if ($arr['post_status'] == 'draft') {
                    $counts->draft = $arr['count'];
                    $countsUpdated[] = 'draft';
                }

                if ($arr['post_status'] == 'publish') {
                    $counts->publish = $arr['count'];
                    $countsUpdated[] = 'publish';
                }

                if ($arr['post_status'] == 'private') {
                    $counts->private = $arr['count'];
                    $countsUpdated[] = 'private';
                }

                if ($arr['post_status'] == 'pending') {
                    $counts->pending = $arr['count'];
                    $countsUpdated[] = 'pending';
                }

                if ($arr['post_status'] == 'trash') {
                    $counts->trash = $arr['count'];
                    $countsUpdated[] = 'trash';
                }
            }

            foreach (array_keys($countsAll) as $postStatus) {
                if (! in_array($postStatus, $countsUpdated)) {
                    unset($counts->$postStatus);
                }
            }
        }

        return $counts;
    }
    // [END] Overview Page Actions

    /*
     * This method is called when a post is created in the front-end
     * either by an existing user or a guest (post status will be set to private)
     */
    /**
     * @param array $eventData
     *
     * @return int|\WP_Error
     */
    public function createPost($eventData = array())
    {
        if (empty($eventData)) {
            $eventData = $_POST;
        }

        // If no user ID is set, then assign it to the first admin
        // Set the status of the post


            $postStatus = 'publish';


        // we're creating a new event Custom Post...
        $data = array(
            'post_content'   => $eventData['desc'],
            'post_name'      => strtolower(str_replace(' ', '-', $eventData[EVENTAPPI_POST_NAME.'_name'])),
            'post_title'     => $eventData[EVENTAPPI_POST_NAME.'_name'],
            'post_status'    => $postStatus,
            'post_type'      => EVENTAPPI_POST_NAME,
            'post_author'    => $eventData['user_id'],
            'ping_status'    => 'closed',
            'post_parent'    => 0,
            'menu_order'     => 0,
            'to_ping'        => '',
            'pinged'         => '',
            'post_password'  => '',
            'post_excerpt'   => $eventData['desc'],
            'comment_status' => 'closed',
            'post_category'  => $eventData['post_category'],
            'tags_input'     => '',
            'tax_input'      => array(EVENTAPPI_POST_NAME . '_categories' => $eventData['post_category'])
        );

        return wp_insert_post($data, true);
    }

    /**
     * @param $postId
     */
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

        if (!array_key_exists('post_title', $_REQUEST) && !array_key_exists(EVENTAPPI_POST_NAME.'_name', $_POST)) {
            // we don't have enough data to create an event.
            return;
        }

        /* [START] Update TimeZone */
        // Continue if the timezone is editable
        $timeZoneNoEdit = get_post_meta($postId, EVENTAPPI_POST_NAME.'_timezone_no_edit', true);

        if (! $timeZoneNoEdit) {
            $timeZone = $_POST[EVENTAPPI_POST_NAME.'_timezone_string'];

            if (! $timeZone) {
                // None set? Take the one from the WP Settings
                $timeZone = get_option('timezone_string');
            }

            update_post_meta($postId, EVENTAPPI_POST_NAME.'_timezone_string', $timeZone);
        }
        /* [END] Update TimeZone */

        if (array_key_exists('post_title', $_REQUEST) && array_key_exists('content', $_REQUEST)) {
            $eventName = $_REQUEST['post_title'];
            $eventDesc = $_REQUEST['content'];
        } elseif (array_key_exists(EVENTAPPI_POST_NAME.'_name', $_POST) && array_key_exists('desc', $_POST)) {
            $eventName = $_POST[EVENTAPPI_POST_NAME.'_name'];
            $eventDesc = $_POST['desc'];
        }

        if (array_key_exists(EVENTAPPI_POST_NAME.'_venue_name', $_POST)
            && !empty($_POST[EVENTAPPI_POST_NAME.'_venue_name'])
            && empty($_POST[EVENTAPPI_POST_NAME.'_venue_select'][0])) {
                EventVenueTax::instance()->insertVenue($postId, $_POST);

                $_POST['taxonomy'] = EventVenueTax::TAX_NAME;
                $_POST['tag-name'] = $_POST[EventVenueTax::instance()->venueKeys['name']];
        }

        $startDate = $_REQUEST[EVENTAPPI_POST_NAME . '_start_date'][0];
        $endDate = $_REQUEST[EVENTAPPI_POST_NAME . '_end_date'][0];

        if (array_key_exists('all_day', $_POST) && $_POST['all_day'] === '1') {
            $endDate = $startDate;
        }

        $startTime = $_REQUEST[EVENTAPPI_POST_NAME . '_start_time'][0];
        $endTime = $_REQUEST[EVENTAPPI_POST_NAME . '_end_time'][0];

        if (array_key_exists('all_day', $_POST) && $_POST['all_day'] === '1') {
            $endTime = '23:59:59';
        }


        $emailReminder = $_REQUEST[EVENTAPPI_POST_NAME . '_email_reminder'][0];
        if (is_null($emailReminder)) {
            $emailReminder = 0;
        }

        // Dashboard
        $venueId = $_REQUEST[EVENTAPPI_POST_NAME . '_venue_select']['cmb-field-0'];

        // Frontend
        if (array_key_exists('ea_p_a', $_POST)) {
            $venueId = $_REQUEST[EVENTAPPI_POST_NAME . '_venue_select'][0];

            update_post_meta($postId, EVENTAPPI_POST_NAME.'_start_date', strtotime($startDate));
            update_post_meta($postId, EVENTAPPI_POST_NAME.'_start_time', $startTime);
            update_post_meta($postId, EVENTAPPI_POST_NAME.'_end_date', strtotime($endDate));
            update_post_meta($postId, EVENTAPPI_POST_NAME.'_end_time', $endTime);
            update_post_meta($postId, EVENTAPPI_POST_NAME.'_venue_select', $venueId);
            update_post_meta($postId, EVENTAPPI_POST_NAME.'_email_reminder', $emailReminder);
        }

        if (array_key_exists('thumbId', $_POST)) {
            set_post_thumbnail($postId, $_POST['thumbId']);
        }

        // Create/Update API Data for the Current User and the Event
        if ($current_user->data->ID) {
            $eventInfo = array(
                'postId'    => $postId,
                'eventName' => $eventName,
                'eventDesc' => $eventDesc,
                'startDate' => $startDate,
                'endDate'   => $endDate,
                'startTime' => $startTime,
                'endTime'   => $endTime,
                'venueId'   => $venueId,
                'user'      => $current_user,
                'emailReminder' => $emailReminder
            );

            $this->updateApiData($eventInfo);
        }
    }

    /**
     * @param $post_ID
     * @param $post_after
     * @param $post_before
     */
    public function postUpdated($post_ID, $post_after, $post_before)
    {
        // Only load this for the event post type
        if (get_post_type($post_ID) !== EVENTAPPI_POST_NAME) {
            return;
        }
    }

    /**
     * @param $eventInfo
     */
    public function updateApiData($eventInfo)
    {
        global $wpdb;

        // First Update API User Key
        $apiUserKey = get_user_meta($eventInfo['user']->data->ID, EVENTAPPI_PLUGIN_NAME.'_user_id', true);

        if (empty($apiUserKey)) {
            Logger::instance()->log(
                __FILE__,
                __FUNCTION__,
                '$apiUserKey is empty',
                Logger::LOG_LEVEL_DEBUG
            );
            $apiUserKey = User::instance()->addUserToApi($eventInfo['user'], false);

            Logger::instance()->log(
                __FILE__,
                __FUNCTION__,
                "\$apiUserKey is {$apiUserKey}",
                Logger::LOG_LEVEL_DEBUG
            );
        }

        // Then Update API Event Using the API User Key
        $start = date('Y-m-d H:i:s', strtotime($eventInfo['startDate'] . ' ' . $eventInfo['startTime']));
        $end   = date('Y-m-d H:i:s', strtotime($eventInfo['endDate'] . ' ' . $eventInfo['endTime']));

        $venueTable  = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_venues';

        $sql = <<<CHECKVENUESQL
SELECT `api_id` FROM {$venueTable}
WHERE `wp_id` = %d
CHECKVENUESQL;
        $apiVenueKey = $wpdb->get_var(
            $wpdb->prepare(
                $sql,
                $eventInfo['venueId']
            )
        );

        $bannerImageUrl = '';

        $image = wp_get_attachment_image_src(get_post_thumbnail_id($eventInfo['postId']), 'single-post-thumbnail');
        if (isset($image[0])) {
            $bannerImageUrl = $image[0];
        }

        $eventData = array(
            'user_id'          => $apiUserKey,
            'name'             => $eventInfo['eventName'],
            'description'      => $eventInfo['eventDesc'],
            'banner_image_url' => $bannerImageUrl, // we pass in the URL to the featured image
            'venue'            => $apiVenueKey,
            'start'            => $start,
            'end'              => $end,
            'email_reminder'   => $eventInfo['emailReminder']
        );
        Logger::instance()->log(
            __FILE__,
            __FUNCTION__,
            ['message' => __('We have our Event Data.', EVENTAPPI_PLUGIN_NAME), 'data' => $eventData],
            Logger::LOG_LEVEL_DEBUG
        );

        $apiEventId = get_post_meta($eventInfo['postId'], EVENTAPPI_POST_NAME.'_api_id', true);

        if ($apiEventId) {
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

            add_post_meta($eventInfo['postId'], EVENTAPPI_POST_NAME.'_api_id', $newEvent['data']['id'], true);
        }
    }

    /**
     *
     */
    private function setupActiveMenu()
    {
        add_action('admin_menu', array($this, 'activeMenu'));
    }

    /**
     *
     */
    public function activeMenu()
    {
        global $current_user;

        $capability = EVENTAPPI_PLUGIN_NAME . '_menu';
        $menu_slug  = 'edit.php?post_type=' . EVENTAPPI_PLUGIN_NAME . '_event';

        // Add Tickets Page
        add_submenu_page(
            $menu_slug,
            __('EventAppi Tickets', EVENTAPPI_PLUGIN_NAME),
            __('Tickets', EVENTAPPI_PLUGIN_NAME),
            $capability,
            'edit.php?post_type='.EVENTAPPI_TICKET_POST_NAME
        );



        // Add Stats Page
        add_submenu_page(
            $menu_slug,
            __('EventAppi Reports', EVENTAPPI_PLUGIN_NAME),
            __('Reports', EVENTAPPI_PLUGIN_NAME),
            $capability,
            EVENTAPPI_PLUGIN_NAME . '-analytics',
            array(Analytics::instance(), 'analyticsPage')
        );

        // Add Attendees Page
        add_submenu_page(
            null,
            __('EventAppi Attendees', EVENTAPPI_PLUGIN_NAME),
            __('Attendees', EVENTAPPI_PLUGIN_NAME),
            $capability,
            EVENTAPPI_PLUGIN_NAME . '-attendees',
            array(Attendees::instance(), 'attendeesPage')
        );

        // Add Attendees-Download Page
        add_submenu_page(
            null,
            __('Download EventAppi Attendees', EVENTAPPI_PLUGIN_NAME),
            __('Download Attendees', EVENTAPPI_PLUGIN_NAME),
            $capability,
            EVENTAPPI_PLUGIN_NAME . '-download-attendees',
            array($this, 'attendeesExport')
        );

        // Add Purchases Page
        add_submenu_page(
            null,
            __('EventAppi Purchases', EVENTAPPI_PLUGIN_NAME),
            __('Purchases', EVENTAPPI_PLUGIN_NAME),
            $capability,
            EVENTAPPI_PLUGIN_NAME . '-purchases',
            array($this, 'purchasesPage')
        );

        global $current_user;

        if (array_key_exists('administrator', $current_user->caps)) {
            // Add Settings Page
            add_submenu_page(
                $menu_slug,
                __('EventAppi Settings', EVENTAPPI_PLUGIN_NAME),
                __('Settings', EVENTAPPI_PLUGIN_NAME),
                $capability,
                EVENTAPPI_PLUGIN_NAME . '-settings',
                array(Settings::instance(), 'settingsPage')
            );
        }

        // Add Help Page
        add_submenu_page(
            $menu_slug,
            __('EventAppi Help', EVENTAPPI_PLUGIN_NAME),
            __('Help', EVENTAPPI_PLUGIN_NAME),
            $capability,
            EVENTAPPI_PLUGIN_NAME . '-help',
            array(Help::instance(), 'helpPage')
        );
    }

    /**
     *
     */
    private function setupInactiveMenu()
    {
        add_action('admin_menu', array($this, 'inactiveMenu'));
    }

    /**
     *
     */
    public function inactiveMenu()
    {
        global $current_user;
        $capability = EVENTAPPI_PLUGIN_NAME . '_menu';

        add_menu_page(
            __('Events', EVENTAPPI_PLUGIN_NAME),
            __('Events', EVENTAPPI_PLUGIN_NAME),
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
                __('EventAppi Settings', EVENTAPPI_PLUGIN_NAME),
                __('Settings', EVENTAPPI_PLUGIN_NAME),
                $capability,
                EVENTAPPI_PLUGIN_NAME . '-settings',
                array(Settings::instance(), 'settingsPage')
            );
        }

        // Add Help Page
        add_submenu_page(
            EVENTAPPI_POST_NAME,
            __('EventAppi Help', EVENTAPPI_PLUGIN_NAME),
            __('Help', EVENTAPPI_PLUGIN_NAME),
            $capability,
            EVENTAPPI_PLUGIN_NAME . '-help',
            array(Help::instance(), 'helpPage')
        );
    }

    /**
     *
     */
    public function inactiveNotice()
    {
        wp_die(__('Looks like the EventAppi plugin is inactive. Please check your license key in the Settings menu.', EVENTAPPI_PLUGIN_NAME));
    }

    /**
     *
     */
    public function purchasesPage()
    {
        if (!current_user_can('manage_' . EVENTAPPI_PLUGIN_NAME)) {
            wp_die(__('You do not have sufficient permissions to access this page.', EVENTAPPI_PLUGIN_NAME));
        }

        $eventPostID = (int)$_GET['post'];

        $data = $this->getPurchasesData($eventPostID);

        echo Parser::instance()->parseEventAppiTemplate('Events/ListEventPurchases', $data);
    }

    /**
     * @param $eventPostID
     *
     * @return array
     */
    public function getPurchasesData($eventPostID)
    {
        global $wpdb, $post;

        $resultsPerPage = 10;

        $data = array();

        $data['customPost']     = get_post_type_object(EVENTAPPI_POST_NAME);

        if (is_admin()) { // Dashboard
            $data['postUrl'] = get_admin_url() . 'edit.php'.
                              '?post_type=' . EVENTAPPI_POST_NAME . '&page=' . EVENTAPPI_PLUGIN_NAME . "-purchases&post={$eventPostID}";
            $data['purchasesLabel'] = __('Purchases', EVENTAPPI_PLUGIN_NAME);
            $sqKey = 's';

        } else { // Front-end
            $page = get_query_var('page', 1);

            if ($page == 0) {
                $page = 1;
            }

            $sqKey = 'sf';

            $data['postUrl'] = $data['postUrlRoot'] = get_permalink($post->ID).'?id='.$eventPostID;

            // Append any existing query strings
            if ($_GET['assigned'] != '') {
                $data['postUrl'] .= '&assigned='.htmlspecialchars($_GET['assigned']);
            }

            if ($_GET['sf'] != '') {
                $data['postUrl'] .= '&sf='.urlencode($_GET['sf']);
            }
        }

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
AND is_claimed = '0' AND is_assigned = '0' AND is_sent = '0'
PURCHASEAVAILSQL;
        $data['purchaseAvail'] = $wpdb->get_var($sql);

        $purchaseQuery = <<<PURCHASEQUERYSQL
SELECT a.id, a.user_id, a.purchased_ticket_hash, a.assigned_to, a.is_claimed, a.is_checked_in, u.user_email,
       u.display_name, mf.meta_value as first_name, ml.meta_value as last_name, a.sent_to
FROM {$purchasesTable} AS a
    LEFT JOIN {$usersTable} AS u ON a.user_id = u.ID
    LEFT JOIN {$usersMetaTable} AS mf ON u.ID = mf.user_id AND mf.meta_key = 'first_name'
    LEFT JOIN {$usersMetaTable} AS ml ON u.ID = ml.user_id AND ml.meta_key = 'last_name'
WHERE a.event_id = {$eventPostID}
PURCHASEQUERYSQL;

        // cater for search criteria
        if (array_key_exists($sqKey, $_GET)) {
            $purchaseQuery .= <<<SEARCHCRTERIASQL
    AND (u.user_email  LIKE '%%%s%%' OR
         a.assigned_to  LIKE '%%%s%%' OR
         mf.meta_value LIKE '%%%s%%' OR
         ml.meta_value LIKE '%%%s%%')
SEARCHCRTERIASQL;

        } elseif (array_key_exists('assigned', $_GET)) {
            if ($_GET['assigned'] == 'yes') {
                $purchaseQuery .= " AND (a.assigned_to IS NOT NULL OR is_claimed = 1) ";
            } else {
                $purchaseQuery .= " AND (a.assigned_to IS NULL AND is_claimed = 0) ";
            }
        }

        $purchaseQuery .= ' GROUP BY a.purchased_ticket_hash ';

        if (array_key_exists($sqKey, $_GET)) {
            $purchaseQuery = $wpdb->prepare(
                $purchaseQuery,
                $_GET[$sqKey],
                $_GET[$sqKey],
                $_GET[$sqKey],
                $_GET[$sqKey]
            );
        }

        // All Results - To determine total number of pages
        $purchasesAllResults = count($wpdb->get_results($purchaseQuery));

        // Page?
        if ($page != '') {
            $offset = (($page - 1) * $resultsPerPage);
            $purchaseQuery .= ' LIMIT '.$offset.', '.$resultsPerPage;
        }

        $data['purchases'] = $wpdb->get_results($purchaseQuery);

        foreach ($data['purchases'] as $index => $purchase) {
            if (current_user_can('edit_user', $purchase->user_id)) {
                $data['purchases'][$index]->can_edit = true;
                $data['purchases'][$index]->edit_user_url = get_permalink(Settings::instance()->getPageId('user-profile')).'?id='.$purchase->user_id;
            }
        }

        $data[$sqKey] = htmlspecialchars($_GET[$sqKey]);

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
        $data['total_pages'] = ceil($purchasesAllResults / $resultsPerPage);

        // Current page
        $data['page'] = $page;

        return $data;
    }

    /**
     * @param $content
     *
     * @return string
     */
    public function filterThePostContent($content)
    {
        if ($this->alreadyFiltered) {
            return $content;
        }

        global $wpdb;

        if (is_post_type_archive() || !is_single()) {
            return $content; // unmolested (it is not a single post type)
        }

        $thePost = get_post();

        if ($thePost->post_type !== EVENTAPPI_POST_NAME) {
            return $content; // unmolested (has to be an event post type)
        }

        $theCats = EventCatTax::instance()->getList($thePost->ID);
        $thePostMeta = get_post_meta($thePost->ID);

        // Only get the venue's info if there is a Venue ID associated with the Event
        $venueId = (int)$thePostMeta[EVENTAPPI_POST_NAME.'_venue_select'][0];
        $venueInfo = EventVenueTax::instance()->getInfo($venueId);

        // Fetch the tickets
        $ticketsQuery = "SELECT p.ID, p.post_title, p.post_content FROM ".$wpdb->posts." p "
                . " LEFT JOIN ".$wpdb->postmeta." pm ON (p.ID = pm.post_id) "
                . " LEFT JOIN ".$wpdb->postmeta." pm2 ON (p.ID = pm2.post_id) "
                . " WHERE p.post_type='".EVENTAPPI_TICKET_POST_NAME."' "
                . " AND ((pm.meta_key='".EVENTAPPI_TICKET_POST_NAME."_event_id' AND pm.meta_value='".$thePost->ID."') "
                        . "AND (pm2.meta_key='".EVENTAPPI_TICKET_POST_NAME."_api_id' AND pm2.meta_value > 0)) "
                . " AND p.post_status='publish'"
                . " ORDER BY pm.meta_value DESC";

        $theTickets = $wpdb->get_results($ticketsQuery);

        foreach ($theTickets as $tIndex => $ticket) {
            $ticketMeta = get_post_meta($ticket->ID);

            // Needs to be on sale and have an API ID
            if (TicketPostType::instance()->isOnSale($ticketMeta)) {
                $total      = intval($ticketMeta[EVENTAPPI_TICKET_POST_NAME.'_no_available'][0]);
                $sold       = intval($ticketMeta[EVENTAPPI_TICKET_POST_NAME.'_no_sold'][0]);
                $avail      = $total - $sold;

                $price      = money_format('%i', $ticketMeta[EVENTAPPI_TICKET_POST_NAME.'_price'][0]);

                $theTickets[$tIndex]->ticketId    = $ticket->ID;
                $theTickets[$tIndex]->ticketApiId = $ticketMeta[EVENTAPPI_TICKET_POST_NAME.'_api_id'][0];
                $theTickets[$tIndex]->cost        = $ticketMeta[EVENTAPPI_TICKET_POST_NAME.'_price'][0];
                $theTickets[$tIndex]->avail       = $avail;
                $theTickets[$tIndex]->soldOut     = ($avail < 1);
                $theTickets[$tIndex]->price       = $price;
            } else {
                unset($theTickets[$tIndex]);
            }
        }

        $data = array(
            'ID'           => $thePost->ID,
            'formAction'   => get_permalink(get_page_by_path(EVENTAPPI_PLUGIN_NAME . '-cart')),
            'startDate'    => date(get_option('date_format'), $thePostMeta[EVENTAPPI_POST_NAME.'_start_date'][0]),
            'startTime'    => date(get_option('time_format'), strtotime($thePostMeta[EVENTAPPI_POST_NAME.'_start_time'][0])),
            'endDate'      => date(get_option('date_format'), $thePostMeta[EVENTAPPI_POST_NAME.'_end_date'][0]),
            'endTime'      => date(get_option('time_format'), strtotime($thePostMeta[EVENTAPPI_POST_NAME.'_end_time'][0])),
            'theVenue'     => $venueInfo['venue'],
            'theCats'      => $theCats,
            'theAddress'   => $venueInfo['addr'],
            'theAdrLink'   => $venueInfo['addr_link'],
            'theTickets'   => $theTickets
        );

        /* Tickets Area */
        $data['ticketsArea'] = (! empty($data['theTickets']))
            ? Parser::instance()->parseTemplate('single-event-tickets', $data, false) : '';

        return $content . Parser::instance()->parseTemplate('single-event', $data);
    }

    /**
     * @param null $postId
     *
     * @return string
     */
    public function createAnEventOnTheFrontend($postId = null)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        if (! is_user_logged_in()) {
            return $this->loginPage();
        }

        $nonceAction = EVENTAPPI_PLUGIN_NAME . '_create_event';
        $nonce = wp_create_nonce($nonceAction); // nonce to be added as hidden field

        global $current_user;

        if (!current_user_can('publish_events')) {
            return __('You are not permitted to create new events.', EVENTAPPI_PLUGIN_NAME);
        }

        $eventData = array(
            'postLink' => get_permalink(Settings::instance()->getPageId('create-event')),
            'user'     => $current_user
        );

        $eventData['is_logged_in'] = is_user_logged_in();
        $eventData['is_admin'] = in_array('administrator', $current_user->roles);

        $eventData['submitErrors'] = array(); // none by default

        if (array_key_exists(EVENTAPPI_POST_NAME.'_name', $_POST)
            && array_key_exists('ea_post_type', $_POST)
        ) {
            // It's validation time
            $errors = array();

            $post = Sanitizer::instance()->arrayMapRecursive('trim', $_POST); // strip white spaces

            // Let's check the nonce (value from hidden field) against the one created above
            if (! wp_verify_nonce($post['ea_nonce'], $nonceAction)) {
                // The nonce is either invalid or it has expired
                $errors[] = __('Your session has expired due to inactivity. Please try creating the event again.', EVENTAPPI_PLUGIN_NAME);
            }

            if (! $eventData['is_logged_in']) {
                // Email
                if (! filter_var($post[EVENTAPPI_PLUGIN_NAME.'_email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = __('The email address does not seem to be a valid one.', EVENTAPPI_PLUGIN_NAME);
                }
            }

            // Start Date
            if (! Validator::instance()->isValidDate($post[EVENTAPPI_POST_NAME.'_start_date'])) {
                $errors[] = __('The START DATE does not seem to be valid. Please add it using the datepicker.', EVENTAPPI_PLUGIN_NAME);
            }

            // End Date
            if (! Validator::instance()->isValidDate($post[EVENTAPPI_POST_NAME.'_end_date'])) {
                $errors[] = __('The END DATE does not seem to be valid. Please add it using the datepicker.', EVENTAPPI_PLUGIN_NAME);
            }

            // Start Time
            if ($post[EVENTAPPI_POST_NAME.'_start_time'][0] === '') {
                $errors[] = __('Please specify a start time.', EVENTAPPI_PLUGIN_NAME);
            }

            // End Time
            if ($post[EVENTAPPI_POST_NAME.'_end_time'][0] === '') {
                $errors[] = __('Please specify an end time.', EVENTAPPI_PLUGIN_NAME);
            }


            // Venue
            if (! $post[EVENTAPPI_POST_NAME.'_venue_select'][0] && ! $post[EVENTAPPI_POST_NAME.'_venue_name']) {
                $errors[] = __('The event location was not set. Please select one from the drop-down below.', EVENTAPPI_PLUGIN_NAME);
            }

            // Promo Image is optional but if it's sent for upload, it will be validated
            if ($_FILES['ea_banner_image']['name'] != '') {
                $movePI = Media::instance()->handleUpload($_FILES['ea_banner_image']);

                if (isset($movePI['error'])) {
                    $errors[] = $movePI['error'];
                } elseif (! in_array($movePI['type'], Media::instance()->allowedImageMimeTypes()) ) {
                    $errors[] = __('You have not uploaded a valid promo image. Please try again.', EVENTAPPI_PLUGIN_NAME);
                } else {
                    $eventData['imageUploaded'] = $movePI;
                }
            }

            // We have errors, don't post anything and notify the user
            // The existing typed values from the form will show
            if (! empty($errors)) {
                $eventData['submitErrors'] = $errors;
                $eventData['post'] = Sanitizer::instance()->arrayMapRecursive('sanitize_text_field', $post); // Sanitize user input

                if (! filter_var($eventData['post'][EVENTAPPI_PLUGIN_NAME.'_email'], FILTER_VALIDATE_EMAIL)) {
                    $eventData['post'][EVENTAPPI_PLUGIN_NAME.'_email'] = ''; // do not print an invalid email address
                }

                $eventData['post'][EVENTAPPI_POST_NAME.'_start_date'] = $post[EVENTAPPI_POST_NAME.'_start_date'][0] ?: date(get_option('date_format'));
                $eventData['post'][EVENTAPPI_POST_NAME.'_end_date'] = $post[EVENTAPPI_POST_NAME.'_end_date'][0] ?: date(get_option('date_format'));

                $eventData['post'][EVENTAPPI_POST_NAME.'_start_time'] = $post[EVENTAPPI_POST_NAME.'_start_time'][0] ?: date(get_option('time_format'));
                $eventData['post'][EVENTAPPI_POST_NAME.'_end_time'] = $post[EVENTAPPI_POST_NAME.'_end_time'][0] ?: date(get_option('time_format'));

                $eventData['timezone'] = $eventData['post'][EVENTAPPI_POST_NAME.'_timezone_string'] ?: get_option('timezone_string');
            } else { // start [no errors area]
                // a work-around for tax meta boxes
                $_POST['post_type'] = EVENTAPPI_POST_NAME;

                $eventCreateData = $_POST;

                if (! empty($current_user)) {
                    $eventCreateData['user_id'] = $current_user->ID;
                }

                $postId = EventPostType::instance()->createPost($eventCreateData);

                if (is_a($postId, 'WP_Error')) {
                    wp_die(__('There was an error adding your event.', EVENTAPPI_PLUGIN_NAME));
                } else {
                    // Now we have the Post ID and we can set the featured image to it (if it was uploaded)
                    if($eventData['imageUploaded']) {
                        $imageId = Media::instance()->addFileToPost($eventData['imageUploaded']['file'], $eventData['imageUploaded']['type'], $postId);
                        set_post_thumbnail($postId, $imageId);
                    }
                }


                $eventData['postCreated'] = post_permalink($postId);
            } // end [no errors area]
        } else { // Create Event - View Mode (no post data sent)
            // Default TimeZone
            $eventData['timezone'] = get_option('timezone_string');
        }

        if (!empty($postId)) {
            $eventData['thePost'] = get_post($postId, ARRAY_A);
        }
        $eventData['categoryList'] = get_terms(EVENTAPPI_POST_NAME . '_categories', 'hide_empty=0');
        $eventData['countryList'] = CountryList::instance()->getCountryList();

        if (is_user_logged_in()) {
            // If the user is an Event Organiser, show only the events added by him/her
            $eventData['venueList'] = EventVenueTax::instance()->filterVenuesFrontend($current_user);
        }

        $eventData['nonce'] = $nonce;

        return Parser::instance()->parseTemplate('create-new-event', $eventData);
    }

    /**
     * @return string
     */
    public function editAnEventOnTheFrontend()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        global $current_user;

        // Anyone has to be logged in to edit any event
        if (! is_user_logged_in()) {
            return $this->loginPage();
        }

        if (!current_user_can('edit_events')) {
            return __('You are not permitted to edit events.', EVENTAPPI_PLUGIN_NAME);
        }

        if (!array_key_exists('event_id', $_GET) || empty($_GET['event_id'])) {
            return '';
        }

        $msgUpdateError = $msgAccessError = false; // default

        $templateName = 'edit-event';

        $eventId = (int)$_GET['event_id'];

        if ($eventId < 1) {
            $msgAccessError = __('No Event ID [event_id] was requested in the URL.', EVENTAPPI_PLUGIN_NAME);
        } else {
            // Let's check if the ID exists and belongs indeed to an event post type
            $postType = get_post_type($eventId);

            if (! $postType) {
                $msgAccessError = __('The requested Event ID is not valid.', EVENTAPPI_PLUGIN_NAME);
            } elseif ($postType != EVENTAPPI_POST_NAME) {
                $msgAccessError = __('The requested ID does not belong to any event.', EVENTAPPI_PLUGIN_NAME);
            }
        }

        // Do not continue if there are access errors
        if ($msgAccessError) {
            return Parser::instance()->parseTemplate($templateName, array('msg_access_error' => $msgAccessError));
        }

        $nonceAction = EVENTAPPI_PLUGIN_NAME . '_edit_event';
        $nonce = wp_create_nonce($nonceAction); // nonce to be added as hidden field

        $isAdmin = in_array('administrator', $current_user->roles);

        $editData = array(
            'postAction' => get_permalink(Settings::instance()->getPageId('edit-event')),
            'user'       => $current_user,
            'is_admin'   => $isAdmin
        );

        if (array_key_exists(EVENTAPPI_POST_NAME . '_name', $_POST)
            && array_key_exists('ea_post_type', $_POST)
        ) {
            // Let's check the nonce (value from hidden field) against the one created above
            $postedNonce = $_POST['ea_nonce'];

            if (! wp_verify_nonce($postedNonce, $nonceAction)) {
                $msgUpdateError = __('You session has expired due to a long time of inactivity. Please go back and try to do the update again.', EVENTAPPI_PLUGIN_NAME);
            } else {
                // A work-around for tax meta boxes
                $_POST['post_type'] = EVENTAPPI_POST_NAME;

                // Update Event Post
                $data = array(
                    'ID'             => $eventId,
                    'post_content'   => $_POST['desc'],
                    'post_name'      => strtolower(str_replace(' ', '-', $_POST[EVENTAPPI_POST_NAME.'_name'])),
                    'post_title'     => $_POST[EVENTAPPI_POST_NAME.'_name'],
                    'post_status'    => 'publish',
                    'post_type'      => EVENTAPPI_POST_NAME,
                    'post_author'    => $current_user->ID,
                    'ping_status'    => 'closed',
                    'post_parent'    => 0,
                    'menu_order'     => 0,
                    'post_excerpt'   => $_POST['desc'],
                    'comment_status' => 'closed',
                    'post_category'  => $_POST['post_category'],
                    'tax_input'      => array(EVENTAPPI_POST_NAME . '_categories' => $_POST['post_category'])
                );

                $result = wp_update_post($data, true);

                if (is_a($result, 'WP_Error')) {
                    $msgUpdateError = __('There was an error updating your event.', EVENTAPPI_PLUGIN_NAME);
                }

                // Clear Promo Image from the Event
                if (isset($_POST['ea_clear_promo_image']) && $_POST['ea_clear_promo_image'] == 1) {
                    if (! delete_post_thumbnail($eventId)) {
                        $msgUpdateError = __('The event was updated. However, the promo image could not be removed.', EVENTAPPI_PLUGIN_NAME);
                    }
                }

                // Update Time Zone
                update_post_meta($eventId, EVENTAPPI_POST_NAME.'_timezone_string', $_POST[EVENTAPPI_POST_NAME.'_timezone_string']);

                $editData['postUpdated'] = post_permalink($eventId);
            }
        }

        if ($eventId > 0) {
            $editData['thePost']        = get_post($eventId, ARRAY_A);
            $editData['thePostMeta']    = get_post_meta($eventId);

            $startDate = date(get_option('date_format'), $editData['thePostMeta'][EVENTAPPI_POST_NAME . '_start_date'][0] ?: time());
            $startTime = date(get_option('time_format'), strtotime($editData['thePostMeta'][EVENTAPPI_POST_NAME . '_start_time'][0] ?: time()));

            $endDate = date(get_option('date_format'), $editData['thePostMeta'][EVENTAPPI_POST_NAME . '_end_date'][0] ?: time());
            $endTime = date(get_option('time_format'), strtotime($editData['thePostMeta'][EVENTAPPI_POST_NAME . '_end_time'][0] ?: time()));

            $editData['startDate'] = $startDate;
            $editData['startTime'] = $startTime;
            $editData['endDate'] = $endDate;
            $editData['endTime'] = $endTime;

            $editData['thumbId']        = get_post_thumbnail_id($eventId);
            $editData['postCategories'] = get_the_terms($eventId, EVENTAPPI_POST_NAME . '_categories');

            // Get Venue's ID (if any)
            $editData['VenueID']        = get_post_meta($eventId, EVENTAPPI_POST_NAME.'_venue_select', true);

            $editData['postTickets']    = get_the_terms($eventId, 'ticket');
        }

        $editData['categoryList'] = get_terms(EVENTAPPI_POST_NAME . '_categories', 'hide_empty=0');
        $editData['countryList']  = CountryList::instance()->getCountryList();
        $editData['venueList']    = get_terms('venue', 'hide_empty=0');

        // Time Zone
        $timezone = get_post_meta($eventId, EVENTAPPI_POST_NAME.'_timezone_string', true);

        if (! $timezone) { // None set? Assign the global one
            $timezone = get_option('timezone_string');
        }

        $editData['timezone'] = $timezone;

        $timeZoneNoEdit = (TicketPostType::instance()->getTickets($eventId, 'count') > 0);

        $func = ($timeZoneNoEdit) ? 'update_post_meta' : 'delete_post_meta';
        $func($eventId, EVENTAPPI_POST_NAME.'_timezone_no_edit', true);

        $editData['timezone_no_edit'] = $timeZoneNoEdit;

        // Show list of editable tickets as an accordion
        $editData['thePost']['frontend'] = 1;
        $editData['ticketsAccordion'] = TicketPostType::instance()->prepareTicketMetabox((object)$editData['thePost']);

        $editData['nonce'] = $nonce;

        $data['msg_update_error'] = $msgUpdateError;
        $data['msg_access_error'] = ''; // none

        return Parser::instance()->parseTemplate($templateName, $editData);
    }


    /**
     *
     */
    public function loadScriptsFrontend()
    {
        global $post;

        // Only load the scripts if we are on add/edit event page
        if (!isset($post) && !PluginManager::instance()->isPage('edit-event')
            && !PluginManager::instance()->isPage('create-event')
        ) {
            return;
        }
    }

    /**
     *
     */
    public function loadScriptsAdmin()
    {
        global $post;

        // Only load the scripts if we are on add/edit coupon/ticket page
        if (!isset($post) || !in_array($post->post_type, array(EVENTAPPI_POST_NAME))) {
            return;
        }

        wp_enqueue_script('jquery-ui-accordion');
        wp_enqueue_script('jquery-ui-sortable');
    }


    /**
     * @param $eventId
     *
     * @return bool
     */
    public function hasApiId($eventId)
    {
        global $wpdb;

        return (intval($wpdb->get_var(
            'SELECT meta_value FROM `'.$wpdb->postmeta.'` WHERE meta_key=\''.EVENTAPPI_POST_NAME.'_api_id\' && post_id='.$eventId
        )) > 0);
    }

    /* The event organiser should only manage his own media files */
    /**
     * @param $wpQuery
     */
    public function ownMediaFiles($wpQuery)
    {
        global $current_user, $pagenow;

        // Only do the filtering in the right locations
        if (! in_array($pagenow, array('upload.php', 'admin-ajax.php'))) {
            return;
        }

        // Filter by the author ID
        if (in_array('event_organiser', $current_user->roles)) {
            $wpQuery->set('author', $current_user->ID);
        }
    }

    /**
     *
     */
    public function updateMenuSelectedItem()
    {
        global $menu, $submenu;

        // ORGANISERS LIST
        if (isset($_GET['role']) && $_GET['role'] === 'event_organiser') {
            // Expand the 'Events' Menu
            foreach ($menu as $key => $val) {
                if ($val[0] == __('Events', EVENTAPPI_PLUGIN_NAME)) {
                    $menu[$key][4] = 'wp-has-current-submenu wp-ea-organisers';
                }
            }

            // Update 'Organisers'
            foreach ($submenu as $target => $list) {
                if ($target == 'edit.php?post_type='.EVENTAPPI_POST_NAME) {
                    foreach ($list as $key => $val) {
                        if (in_array('users.php?role=event_organiser', $val)) {
                            // TO DO HERE
                            break 2;
                        }
                    }
                }
            }
        }

        // EDIT TICKET
        if (isset($_GET['post'])) {
            $postId = (int)$_GET['post'];

            if (get_post_type($postId) === EVENTAPPI_TICKET_POST_NAME) {
                // Expand the 'Events' Menu
                foreach ($menu as $key => $val) {
                    if ($val[0] == __('Events', EVENTAPPI_PLUGIN_NAME)) {
                        $menu[$key][4] = 'wp-has-submenu wp-has-current-submenu wp-menu-open menu-top menu-icon-'.EVENTAPPI_POST_NAME;
                    }
                }
            }
        }
    }

    /**
     * @param $classes
     *
     * @return string
     */
    public function updateBodyClass($classes)
    {
        if (isset($_GET['role']) && $_GET['role'] == 'event_organiser') {
            $classes .= ' users-organisers';
        }
        return $classes;
    }

    /**
     * @param string $couponId
     *
     * @return array
     */
    public function getEventsForCoupon($couponId = '')
    {
        global $current_user, $wpdb;

        $args = array(
            'post_type' => EVENTAPPI_POST_NAME,
            'posts_per_page' => -1,

            'post_status' => 'publish',

            'order' => 'DESC',
            'orderby' => 'meta_value',

            'meta_query' => array(
                array(
                    'key' => EVENTAPPI_POST_NAME . '_start_date'
                )
            )
        );

        // Filter if NOT admin
        if (! in_array('administrator', $current_user->roles)) {
            $args['author'] = $current_user->ID;
        }

        // Mark the events that have tickets associated for coupons
        $posts = get_posts($args);

        if (! $couponId) {
            return $posts; // Add Mode
        }

        $postsFinal = array();

        foreach ($posts as $key => $val) {
            $val->marked = 0;
            if ($metaValue = $wpdb->get_var('SELECT meta_value FROM `'.$wpdb->postmeta."` WHERE post_id='".$couponId."' && meta_key='event_tickets_".$val->ID."'")) {
                $checkForEmpty = unserialize($metaValue);
                if (! empty($checkForEmpty)) {
                    $val->marked = (! empty($checkForEmpty) );
                }
            }
            $postsFinal[] = $val;
        }

        return $postsFinal;
    }

    /**
     *
     */
    public function deleteEventCallback()
    {
        // No external requests allowed
        check_ajax_referer(EVENTAPPI_PLUGIN_NAME . '_ajax_mode', 'ea_nonce');

        if (is_user_logged_in()) { // No guests allowed
            global $current_user, $wpdb;

            $eventId = (int)$_POST['event_id'];

            // See if the ID belongs to an Event
            $postType = get_post_type($eventId);

            if ($postType == EVENTAPPI_POST_NAME) {
                // So far, so good - If the user is not an admin and makes a deletion
                // then the event must be associated with him/her in order to allow this
                if (! in_array('administrator', $current_user->roles)) {
                    $userId = $wpdb->get_var('SELECT post_author FROM `'.$wpdb->posts.'` WHERE ID='.$eventId);

                    if ($userId != $current_user->ID) {
                        exit;
                    }
                }

                $eventDeleted = is_object(wp_delete_post($eventId, true));

                if ($eventDeleted) {
                    // Disassociate Any Coupon with the Event
                    // We're technically cleaning the database as the functionality is fine
                    $wpdb->delete($wpdb->postmeta, array('meta_key' => 'event_tickets_'.$eventId));

                    // Now let's also delete the tickets associated with the event
                    $ticketIds = $wpdb->get_results(
                        'SELECT post_id FROM `'.$wpdb->postmeta."` WHERE meta_key='".EVENTAPPI_TICKET_POST_NAME."_event_id' && meta_value='".$eventId."'"
                    );

                    if (! empty($ticketIds)) {
                        foreach ($ticketIds as $res) {
                            wp_delete_post($res->post_id, true);
                        }
                    }

                    // TODO: HERE WE CAN PLACE THE CALL TO REMOVE THE EVENT FROM THE API IF NEEDED
                    // [START] API CALL

                    // [END] API CALL

                    echo 1; // AJAX response
                }
            }
        }

        exit;
    }

    /**
     *
     */
    public function showNoApiErrorCallback()
    {
        $eventId = (int)$_POST['event_id'];

        if (! $this->hasApiId($eventId)) {
            _e('Tickets can not be bought at this time due to an internal error. Please contact the administrator/event organiser if you would like to get tickets for this event.', EVENTAPPI_PLUGIN_NAME);
        }

        exit;
    }

}
