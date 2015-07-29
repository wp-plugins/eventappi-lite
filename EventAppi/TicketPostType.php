<?php
namespace EventAppi;

use EventAppi\Helpers\Logger;
use EventAppi\Helpers\Meta;

/**
 * Class EventPostType
 *
 * @package EventAppi
 */
class TicketPostType
{
    /**
     *
     */
    const MAX_LITE_TICKETS = 500;

    /**
     * @var EventPostType|null
     */
    private static $singleton = null;

    /**
     * @var string
     */
    private $nonceAdd;

    /**
     *
     */
    private function __construct()
    {
        $this->nonceAdd = EVENTAPPI_PLUGIN_NAME . '_add_ticket';
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
        add_action('init', array($this, 'postTypeAndTaxonomies')); // Register Post Type & Create Events Meta Box
        add_filter('cmb_meta_boxes', array($this, 'addDetailsMetaBox'));
        add_action('admin_notices', array($this, 'postNotices'));

        // Text Type Field - Filter
        add_filter('cmb_field_types', function ($cmb_field_types) {
            $cmb_field_types['text_ea'] = 'EventAppi\Helpers\CMBTextField';
            return $cmb_field_types;
        });

        // Date Type Field - Filter
        add_filter('cmb_field_types', function ($cmb_field_types) {
            $cmb_field_types['date_ea'] = 'EventAppi\Helpers\CMBDateField';
            return $cmb_field_types;
        });

        // Radio Type - Filter
        add_filter('cmb_field_types', function ($cmb_field_types) {
            $cmb_field_types['radio'] = 'EventAppi\Helpers\CMBRadioField';
            return $cmb_field_types;
        });

        // Custom Number Field
        add_filter('cmb_field_types', function ($cmb_field_types) {
            $cmb_field_types['number'] = 'EventAppi\Helpers\CMBNumberField';
            return $cmb_field_types;
        });

        add_action('save_post', array($this, 'savePost'), 1, 2); // Save the meta data

        // Triggers if the ticket is deleted from the database
        add_action('delete_post', array($this, 'deleteTicket'), 10);

        /*
         * ------------------------
         * OVERVIEW PAGE ACTIONS
         * ------------------------
        */

        if (isset($_GET['post_type']) && $_GET['post_type'] == EVENTAPPI_TICKET_POST_NAME) {
            // Custom Columns
            add_filter('manage_edit-' . EVENTAPPI_TICKET_POST_NAME . '_columns', array($this, 'editColumns'));
            add_action('manage_posts_custom_column', array($this, 'prepareColumns'));

            // Make Columns Sortable
            add_filter('manage_edit-'.EVENTAPPI_TICKET_POST_NAME.'_sortable_columns', array($this, 'sortableColumns'));

            // Filter by Event
            add_action('restrict_manage_posts', array($this, 'addEventFilterOnOverview'));
            add_action('pre_get_posts', array($this, 'customList'));

            //add_action( 'pre_get_posts', array($this, 'preGetPosts') );
        }

        // Replace default "Enter title here" with a custom one
        add_filter('enter_title_here', array($this, 'updateTicketTitlePlaceholder'));

        if (isset($_GET['event_id'])) {
            add_action('wp_footer', array($this, 'addTicketArea'));
        }
    }

    /**
     * @return array
     */
    public function getBaseFields()
    {
        return array(
            array(
                'name' => __('On Sale From', EVENTAPPI_PLUGIN_NAME),
                'desc' => 'Format: '.get_option('date_format').' (e.g. '.date(get_option('date_format'), time()).')',
                'id'   => EVENTAPPI_TICKET_POST_NAME.'_sale_from',
                'type' => 'date_ea',
                'cols' => '5',
                'attributes' => array(
                    'placeholder' => __('Now', EVENTAPPI_PLUGIN_NAME),
                    'class' => 'start_date eventappi ticket'
                )
            ),

            array(
                'name' => __('On Sale To', EVENTAPPI_PLUGIN_NAME),
                'desc' => 'Format: '.get_option('date_format').' (e.g. '.date(get_option('date_format'), time()).')',
                'id'   => EVENTAPPI_TICKET_POST_NAME.'_sale_to',
                'type' => 'date_ea',
                'cols' => '5',
                'attributes' => array(
                    'placeholder' => __('Event Start Date', EVENTAPPI_PLUGIN_NAME),
                    'class' => 'end_date eventappi ticket'
                )
            ),

            array(
                'name' => __('Ticket Type', EVENTAPPI_PLUGIN_NAME),
                'id' => EVENTAPPI_TICKET_POST_NAME.'_type',
                'type' => 'radio',
                'allow_none' => true,
                'options' => $this->getTicketTypes()
            ),

            array(
                'name' => __('Ticket Price', EVENTAPPI_PLUGIN_NAME),
                'id'   => EVENTAPPI_TICKET_POST_NAME.'_price',
                'type' => 'text_ea',
                'cols' => '5',
                'attributes' => array(
                    'placeholder' => __('e.g. 50.00', EVENTAPPI_PLUGIN_NAME)
                 )
            ),

            array(
                'name' => __('Number available', EVENTAPPI_PLUGIN_NAME),
                'id'   => EVENTAPPI_TICKET_POST_NAME.'_no_available',
                'type' => 'number',
                'cols' => '5',
                'attributes' => array(
                    'placeholder' => __('e.g. 1000', EVENTAPPI_PLUGIN_NAME),
                    'min' => 0
                )
            ),

            /* Number of tickets sold: 0 by default */
            array(
                'name'    => '',
                'id'      => EVENTAPPI_TICKET_POST_NAME.'_no_sold',
                'type'    => 'hidden',
                'default' => '0'
            )
        );
    }

    /**
     * @return array
     */
    public function getTicketTypes()
    {
        return array(
            'sale' => __('For Sale', EVENTAPPI_PLUGIN_NAME),
            'free' => __('Free', EVENTAPPI_PLUGIN_NAME)
        );
    }

    // [START] Overview Page Actions
    /**
     * @param $columns
     *
     * @return array
     */
    public function editColumns($columns)
    {
        unset($columns['date']);

        $columns['title'] = __('Name', EVENTAPPI_PLUGIN_NAME);

        $newColumns = array(
            EVENTAPPI_TICKET_POST_NAME.'_thumb'        => __('Featured Image', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_TICKET_POST_NAME.'_event'        => __('Event', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_TICKET_POST_NAME.'_sale_from'    => __('On Sale From', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_TICKET_POST_NAME.'_sale_to'      => __('On Sale To', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_TICKET_POST_NAME.'_type'         => __('Ticket Type', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_TICKET_POST_NAME.'_price'        => __('Ticket Price', EVENTAPPI_PLUGIN_NAME),
            EVENTAPPI_TICKET_POST_NAME.'_no_available' => __('Number available', EVENTAPPI_PLUGIN_NAME)
        );

        $eventId = (isset($_GET['eventId'])) ? (int)$_GET['eventId'] : 0;

        if ($eventId) { // Hide Event Name column as it is selected in the filter drop-down
            unset($newColumns[EVENTAPPI_TICKET_POST_NAME.'_event']);
        }

        $newColumns['date'] = __('Date Created', EVENTAPPI_PLUGIN_NAME);

        return array_merge($columns, $newColumns);
    }

    /**
     * @param $column
     */
    public function prepareColumns($column)
    {
        global $post;

        $meta = get_post_custom();

        switch ($column) {
            case EVENTAPPI_TICKET_POST_NAME . '_thumb':
                echo get_the_post_thumbnail($post->ID, array(80, 80)).
                    (! $this->hasApiId($post->ID)) ? '<br /><em class="ea-error">'.__('The ticket has no EVENT API ID.', EVENTAPPI_PLUGIN_NAME).'</em>' : '';
                break;

            case EVENTAPPI_TICKET_POST_NAME.'_event':
                $eventId = get_post_meta($post->ID, EVENTAPPI_TICKET_POST_NAME.'_event_id', true);
                echo (($eventId) ? '<a href="'.  get_permalink($eventId).'">'.get_the_title($eventId).'</a>' : '');
                break;

            case EVENTAPPI_TICKET_POST_NAME . '_sale_from':
            case EVENTAPPI_TICKET_POST_NAME . '_sale_to':
                echo ($meta[$column][0]) ? date(get_option('date_format'), $meta[$column][0]) : '';
                break;

            case EVENTAPPI_TICKET_POST_NAME . '_type':
                echo $this->getTicketTypes()[get_post_meta($post->ID, $column, true)];
                break;

            case EVENTAPPI_TICKET_POST_NAME . '_price':
            case EVENTAPPI_TICKET_POST_NAME . '_no_available':
                echo get_post_meta($post->ID, $column, true);
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
        $columns[EVENTAPPI_TICKET_POST_NAME.'_sale_from'] = 'sale_from';
        $columns[EVENTAPPI_TICKET_POST_NAME.'_sale_to'] = 'sale_to';
        $columns[EVENTAPPI_TICKET_POST_NAME.'_price'] = 'price';
        $columns[EVENTAPPI_TICKET_POST_NAME.'_no_available'] = 'no_available';

        return $columns;
    }

    /**
     *
     */
    public function addEventFilterOnOverview()
    {
        $eventId = (isset($_GET['event_id'])) ? (int)$_GET['event_id'] : 0;

        $data = array();

        // Get Events and List them
        $args = array(
            'post_type'        => EVENTAPPI_POST_NAME,
            'posts_per_page'   => -1,
            'offset'           => 0,
            'meta_key'         => EVENTAPPI_POST_NAME . '_start_date',
            'orderby'          => 'meta_value_num',
            'order'            => 'DESC',
            'post_status'      => 'publish'
        );

        $data['events'] = get_posts($args);

        // Get saved event (if any)
        $data['event_id'] = $eventId;

        echo Parser::instance()->parseEventAppiTemplate('Events/EventsDdFilter', $data);
    }

    /**
     * @param $query
     */
    public function customList($query)
    {
        $eventId = (isset($_GET['event_id'])) ? (int)$_GET['event_id'] : 0;

        // Only show tickets from a specific event if the filter was used
        if ($query->query['post_type'] == EVENTAPPI_TICKET_POST_NAME && $eventId > 0) {
            $query->set('meta_query', array(
                'relation' => 'AND',
                array(
                    'key'     => EVENTAPPI_TICKET_POST_NAME.'_event_id',
                    'value'   => $eventId,
                    'compare' => '='
                )
            ));
        }

        // Any Sorting Requested?
        $orderby = $query->get('orderby');

        if ($orderby != '') {
            if ('sale_from' == $orderby) {
                $query->set('meta_key', EVENTAPPI_TICKET_POST_NAME.'_sale_from');
                $query->set('orderby', 'meta_value_num');
            }

            if ('sale_to' == $orderby) {
                $query->set('meta_key', EVENTAPPI_TICKET_POST_NAME.'_sale_to');
                $query->set('orderby', 'meta_value_num');
            }

            if ('price' == $orderby) {
                $query->set('meta_key', EVENTAPPI_TICKET_POST_NAME.'_price');
                $query->set('orderby', 'meta_value_num');
            }

            if ('no_available' == $orderby) {
                $query->set('meta_key', EVENTAPPI_TICKET_POST_NAME.'_no_available');
                $query->set('orderby', 'meta_value_num');
            }

        }
    }
    // [END] Overview Page Actions

    /**
     *
     */
    public function postTypeAndTaxonomies()
    {
        $labels = array(
            'name'               => EVENTAPPI_PLUGIN_NICE_NAME.' '._x( ' Tickets', 'Post Type General Name', EVENTAPPI_PLUGIN_NAME),
            'singular_name'      => EVENTAPPI_PLUGIN_NICE_NAME.' '._x( ' Ticket', 'Post Type Singular Name', EVENTAPPI_PLUGIN_NAME),
            'menu_name'          => __('Tickets', EVENTAPPI_PLUGIN_NAME),
            'name_admin_bar'     => EVENTAPPI_PLUGIN_NICE_NAME .' '. __('Ticket', EVENTAPPI_PLUGIN_NAME),
            'parent_item_colon'  => __('Parent Ticket:', EVENTAPPI_PLUGIN_NAME),
            'all_items'          => __('All Tickets', EVENTAPPI_PLUGIN_NAME),
            'add_new_item'       => __('Add New EventAppi Ticket', EVENTAPPI_PLUGIN_NAME),
            'add_new'            => __('Add New', EVENTAPPI_PLUGIN_NAME),
            'new_item'           => __('New Ticket', EVENTAPPI_PLUGIN_NAME),
            'edit_item'          => __('Edit Ticket', EVENTAPPI_PLUGIN_NAME),
            'update_item'        => __('Update Ticket', EVENTAPPI_PLUGIN_NAME),
            'view_item'          => __('View Ticket', EVENTAPPI_PLUGIN_NAME),
            'search_items'       => __('Search Ticket', EVENTAPPI_PLUGIN_NAME),
            'not_found'          => __('Not found', EVENTAPPI_PLUGIN_NAME),
            'not_found_in_trash' => __('Not found in Trash', EVENTAPPI_PLUGIN_NAME)
        );

        $args = array(
            'label'                => __('ticket', EVENTAPPI_PLUGIN_NAME),
            'description'          => __('Post Type Description', EVENTAPPI_PLUGIN_NAME),
            'labels'               => $labels,
            'supports'             => array('thumbnail', 'title', 'editor'),
            'hierarchical'         => false,
            'public'               => false,
            'show_ui'              => true,
            'show_in_menu'         => false,
            'menu_position'        => 5,
            'show_in_admin_bar'    => true,
            'show_in_nav_menus'    => true,
            'can_export'           => true,
            'has_archive'          => true,
            'exclude_from_search'  => false,
            'publicly_queryable'   => true,
            'rewrite'              => false,
            'capability_type'      => 'page',

            'register_meta_box_cb' => array($this, 'addEventsMetaBox') // Events Meta Box
        );

        register_post_type(EVENTAPPI_TICKET_POST_NAME, $args);
    }

     /**
     *
     */
    public function addEventsMetaBox()
    {
        add_meta_box(EVENTAPPI_PLUGIN_NAME.'-events',
            sprintf(__('This ticket is for the following event: %s', EVENTAPPI_PLUGIN_NAME), '<span class="ea-error">*</span>'),
            array($this, 'listEventsSelect'),
            EVENTAPPI_TICKET_POST_NAME,
            'normal',
            'default'
        );
    }

    /**
     * @param array $meta
     *
     * @return array
     */
    public function addDetailsMetaBox(array $meta)
    {
        $meta[] = array(
            'title'    => __('Ticket Details', EVENTAPPI_PLUGIN_NAME),
            'pages'    => EVENTAPPI_TICKET_POST_NAME,
            'context'  => 'normal',
            'priority' => 'low',
            'fields'   => $this->getBaseFields()
        );

        return $meta;
    }

    /* This is shown when editing an event */
    /**
     *
     */
    public function addTicketsMetabox()
    {
        add_meta_box(
            EVENTAPPI_POST_NAME . '_tickets',
            __('Tickets Management (Add / Edit / Remove)', EVENTAPPI_PLUGIN_NAME),
            array($this, 'prepareTicketMetabox'),
            EVENTAPPI_POST_NAME
        );
    }

    /**
     * @param $post
     *
     * @return string
     */
    public function prepareTicketMetabox($post)
    {
        $data = array();

        $notPublish = false;
        $eventId = $post->ID;

        if ($post->post_status != 'publish') {
            $notPublish = true;
        } else {
            wp_nonce_field(EVENTAPPI_POST_NAME . '_meta_box', EVENTAPPI_POST_NAME . '_tickets_nonce');

            $data = array();

            $data['tickets'] = $this->getTickets($post->ID);
        }

        $data['used_tickets'] = $this->getTickets($post->ID, 'totalStock');
        $data['max_tickets']  = self::MAX_LITE_TICKETS;

        if (! empty($data['tickets'])) {
            foreach ($data['tickets'] as $key => $val) {
                $data['tickets'][$key]->panel = $this->appendToAccordionTicket($val->ID, false);

                if ($val->post_title == '') {
                    unset($data['tickets'][$key]);
                }
            }
        }

        $hasApiId = false;

        if ($eventId) {
            $hasApiId = EventPostType::instance()->hasApiId($eventId);
        }


        $data['has_api_id'] = $hasApiId;
        $data['not_publish'] = $notPublish;

        $output = Parser::instance()->parseEventAppiTemplate('Events/EventTicketsMetaBox', $data);

        $echo = (! isset($post->frontend));

        if ($echo) {
            echo $output;
        } else {
            return $output;
        }
    }

    /**
     * @param $ticketId
     *
     * @return null|string
     */
    public function isValidTicket($ticketId)
    {
        global $wpdb;

        $ticketPostType = EVENTAPPI_TICKET_POST_NAME;

        $sql = <<<CHECKTICKET
SELECT COUNT(*) FROM `{$wpdb->posts}`
WHERE ID='{$ticketId}' && post_type='{$ticketPostType}' && post_status='publish'
CHECKTICKET;

        return $wpdb->get_var($sql);
    }

    /**
     *
     */
    public function postNotices()
    {
        global $post;

        if ($post->post_type != EVENTAPPI_TICKET_POST_NAME || !isset($_GET['post'])) {
            return;
        }

        // Check if the Ticket has an EVENT API ID
        if (! $this->hasApiId($post->ID)) { ?>
            <div class="ea-note error">
                <?php _e('This ticket does not have an EVENT API ID or it is not associated with any event, thus it does not appear in the store. Please update the ticket and if you still see this message, you can contact the administrator in order to fix this.', EVENTAPPI_PLUGIN_NAME); ?>
            </div>
        <?php
        }
    }

    /**
     * @param $ticketId
     *
     * @return bool
     */
    public function hasApiId($ticketId)
    {
        global $wpdb;

        return (intval($wpdb->get_var(
            'SELECT meta_value FROM `'.$wpdb->postmeta.'` WHERE meta_key=\''.EVENTAPPI_TICKET_POST_NAME.'_api_id\' && post_id='.$ticketId)) > 0
        );
    }

    /**
     *
     */
    public function listEventsSelect()
    {
        global $post;

        $data = array();

        // Get Events and List them
        $args = array(
            'post_type'      => EVENTAPPI_POST_NAME,
            'posts_per_page' => -1,
            'offset'         => 0,
            'meta_key'       => EVENTAPPI_POST_NAME . '_start_date',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'post_status'    => 'publish'
        );

        $data['events'] = get_posts($args);

        // Get saved event (if any)
        $data['event_id'] = get_post_meta($post->ID, EVENTAPPI_TICKET_POST_NAME.'_event_id', true);

        // Determine if we are on CREATE mode
        $isCreateMode = false;

        $baseRU = basename($_SERVER['REQUEST_URI']);
        $startsWith = 'post-new.php';

        if (isset($_GET['post_type']) && $_GET['post_type'] == EVENTAPPI_TICKET_POST_NAME
            && (substr($baseRU, 0, strlen($startsWith)) == $startsWith)) {
            $isCreateMode = true;
        }

        $data['is_create_mode'] = $isCreateMode;

        echo Parser::instance()->parseEventAppiTemplate('Events/EventsMetaBox', $data);
    }

    /**
     * @param $postId
     * @param $post
     */
    public function savePost($postId, $post)
    {
        if (empty($_POST)) {
            return;
        }

        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $ticketId = $post->ID;

        /* Should trigger only when we save a ticket post type */
        if (get_post_type($postId) !== EVENTAPPI_TICKET_POST_NAME) {
            return;
        }

        if (! current_user_can('edit_post', $ticketId)) {
            return $post->ID;
        }

        // We have permission to save the data
        $eventId = (int)$_POST['event_id'];

        // Associate the ticket with the selected event
        update_post_meta($ticketId, EVENTAPPI_TICKET_POST_NAME.'_event_id', $eventId);

        // Create/Update API entry for the ticket
        // Let's see if we have an API ID and determine if it's whether a CREATE or UPDATE mode
        $apiTicketId = get_post_meta($ticketId, EVENTAPPI_TICKET_POST_NAME . '_api_id', true);

        $data = array(
            'eventId'     => $eventId,
            'ticketId'    => $ticketId,
            'ticketApiId' => $apiTicketId,
            'ticketTitle' => $post->post_title,
            'ticketDesc'  => $post->post_content,
            'cost'        => $_POST[EVENTAPPI_TICKET_POST_NAME . '_price'][0],
            'price_type'  => $_POST[EVENTAPPI_TICKET_POST_NAME . '_type'][0],
            'available'   => $_POST[EVENTAPPI_TICKET_POST_NAME . '_no_available'][0]
        );

        $this->updateToApi($data);

        // Update Registration Fields (if any)
        // No Action will be taken if there aren't any (usually when you create a ticket)
        TicketRegFields::instance()->updateRegFields($ticketId);
    }

    /*
     * CREATE OR UPDATE THE API DATA FOR THE SUBMITTED TICKET
     *
    /**
     * @param $data
     */
    public function updateToApi($data)
    {
        // Save to API
        $apiEventId = get_post_meta($data['eventId'], EVENTAPPI_POST_NAME . '_api_id', true);

        if (! $apiEventId) {
            return;
        }

        $startTimeUnix = get_post_meta($data['ticketId'], EVENTAPPI_TICKET_POST_NAME.'_sale_from', true);
        $endTimeUnix = get_post_meta($data['ticketId'], EVENTAPPI_TICKET_POST_NAME.'_sale_to', true);

        if ($startTimeUnix) {
            $apiStartTime = date('Y-m-d H:i:s', $startTimeUnix);
        }

        if ($endTimeUnix) {
            $apiEndTime = date('Y-m-d H:i:s', $endTimeUnix);
        }

        $noSold = get_post_meta($data['ticketId'], EVENTAPPI_TICKET_POST_NAME.'_no_sold', true);

        $ticketArray  = array(
            'event_id'    => $apiEventId,
            'name'        => $data['ticketTitle'],
            'description' => $data['ticketDesc'],
            'cost'        => intval(floatval($data['cost']) * 100),
            'available'   => $data['available'],
            'sold'        => $noSold,
            'price_type'  => $data['price_type'],
            'sale_start'  => $apiStartTime,
            'sale_end'    => $apiEndTime
        );

        if ($data['ticketApiId'] === '') {
            $newTicket = ApiClient::instance()->storeTicket($apiEventId, $ticketArray);

            if (!array_key_exists('data', $newTicket)) {
                return;
            }

            add_post_meta($data['ticketId'], EVENTAPPI_TICKET_POST_NAME . '_api_id', $newTicket['data']['id'], true);
        } else {
            // We have something on the API
            $updateTicket = ApiClient::instance()->updateTicket($apiEventId, $data['ticketApiId'], $ticketArray);

            if (!array_key_exists('code', $updateTicket)) {
                return;
            }
        }
    }

    /**
     * @param $title
     *
     * @return string|void
     */
    public function updateTicketTitlePlaceholder($title)
    {
        $screen = get_current_screen();

        if (EVENTAPPI_TICKET_POST_NAME == $screen->post_type) {
            $title = __('e.g. General Admission', EVENTAPPI_PLUGIN_NAME);
        }

        return $title;
    }

    /**
     *
     */
    public function addTicketArea()
    {
        // Add Fields - Meta Box
        $data = array(
            'cmb_fields_area' => Parser::instance()->parseEventAppiTemplate(
                'Tickets/EditTicketLayout',
                array('fields' => Meta::instance()->generateMetaFields()),
                false
            )
        );

        $data['nonce'] = wp_create_nonce($this->nonceAdd);

        // Add Ticket Area - All Fields
        echo Parser::instance()->parseEventAppiTemplate('Tickets/AddTicketArea', $data);
    }

    /**
     * @param $eventId
     * @param string $type
     * @param array $excludeIds
     *
     * @return mixed|null|string
     */
    public function getTickets($eventId, $type = 'fetch', $excludeIds = array())
    {
        global $wpdb;

        $ticketsQuery = 'SELECT ';

        if ($type == 'fetch') {
            $ticketsQuery .= ' p.ID, p.post_title, ';
            $ticketsQuery .= '(SELECT meta_value FROM `' . $wpdb->postmeta . "` WHERE meta_key='" . EVENTAPPI_TICKET_POST_NAME . "_pos' AND post_id=p.ID) AS ticket_pos ";
        } elseif ($type === 'totalStock') {
            $ticketsQuery .= ' SUM(tc.meta_value) ';
        } else {
            $ticketsQuery .= ' COUNT(*) ';
        }

        $ticketsQuery .= " FROM ".$wpdb->posts." p "
                . " LEFT JOIN ".$wpdb->postmeta." pm ON (p.ID = pm.post_id) ";

        if ($type === 'totalStock') {
            $ticketsQuery .= " LEFT JOIN ".$wpdb->postmeta." tc ON (p.ID = tc.post_id) ";
        }

        $ticketsQuery .= " WHERE p.post_type='".EVENTAPPI_TICKET_POST_NAME."' "
                . " AND pm.meta_key='".EVENTAPPI_TICKET_POST_NAME."_event_id' "
                . " AND pm.meta_value='".$eventId."' "
                . " AND p.post_status='publish' ";

        if (! empty($excludeIds)) {
            $ticketsQuery .= 'AND p.ID NOT IN ('.implode(', ', $excludeIds).')';
        }

        if ($type == 'fetch') {
            $ticketsQuery .= ' ORDER BY ticket_pos ASC';
        }

        if ($type == 'fetch') {
            $tickets = $wpdb->get_results($ticketsQuery);
        } else {
            $tickets = $wpdb->get_var($ticketsQuery);
        }

        return $tickets;
    }

    /**
     * Get Ticket Details and show them to the user in Edit Mode
     *
     * @param int $ticketId
     * @param int $isAjax
     *
     * @return string
     */
    public function loadEditTicketCallback($ticketId = 0, $isAjax = 1)
    {
        if (! $ticketId) {
            $ticketId = (int)$_POST['ticket_id'];
        }

        $data = array();

        $cmbFields = Meta::instance()->generateMetaFields(array('post_id' => $ticketId));

        $data['ticket_id'] = $ticketId;

        // Title, Description
        $ticketData = get_post($ticketId);

        $data['ticket_title'] = $ticketData->post_title;
        $data['ticket_desc'] = $ticketData->post_content;

        // Meta Box Data
        $dataFields = array();
        $dataFields['fields'] = $cmbFields;

        $data['cmb_fields_area'] = Parser::instance()->parseEventAppiTemplate(
            'Tickets/EditTicketLayout',
            $dataFields,
            true
        );

        // Registration Fields Accordion
        $data['reg_fields_area'] = TicketRegFields::instance()->manageRegFields($ticketId, true);

        // Nonce for Security
        $data['nonce'] = wp_create_nonce(EVENTAPPI_PLUGIN_NAME.'_ajax_mode');

        $output = Parser::instance()->parseEventAppiTemplate('Tickets/EditTicket', $data, false);

        if ($isAjax) {
            exit($output);
        } else {
            return $output;
        }
    }

    /**
     * Update Ticket Details
     */
    public function editTicketCallback()
    {
        $eventId = (int)$_POST['event_id'];
        $ticketId = (int)$_POST['ticket_id'];

        // Both should be passed
        if (!$eventId || !$ticketId) {
            exit;
        }

        parse_str($_POST['data'], $post);

        if (! wp_verify_nonce($post['ea_nonce'], EVENTAPPI_PLUGIN_NAME . '_ajax_mode')) {
            exit;
        }

        // For Human Made Meta Boxes Processing
        $_POST = $post;

        // 1) Update the Title and Description
        $ticketPost = array(
            'ID'           => $ticketId,
            'post_title'   => $_POST[EVENTAPPI_PLUGIN_NAME.'_ticket_title'],
            'post_content' => $_POST[EVENTAPPI_PLUGIN_NAME.'_ticket_desc'],
        );

        wp_update_post($ticketPost);

        // 2) Update Meta Box Information
        Meta::instance()->updateMetaBox($ticketId);

        // Make sure the Event ID is kept and not reverted to 0
        update_post_meta($ticketId, EVENTAPPI_TICKET_POST_NAME.'_event_id', $eventId);

        $response = array(
            'status' => 'success',
            'message' => __('The ticket information has been updated.', EVENTAPPI_PLUGIN_NAME)
        );

        exit(json_encode($response));
    }

    /**
     * Update Ticket Details
     */
    public function addTicketCallback()
    {
        global $wpdb;

        $eventId = (int)$_POST['event_id'];

        parse_str($_POST['data'], $post);

        $errors = array();

        // Do not process the edit
        if ($this->getTickets($eventId, 'totalStock') >= self::MAX_LITE_TICKETS) {
            $errors[] = __('The maximum number of tickets per event has been reached. You can not add further tickets.', EVENTAPPI_PLUGIN_NAME);
        }

        if (! wp_verify_nonce($post['ea_nonce'], $this->nonceAdd)) {
            $errors[] = __('Your session has expired. Please reload this page and try adding the ticket again.', EVENTAPPI_PLUGIN_NAME);
        }

        // Declare the POST for the meta box values that are fetched by Human Made ;-)
        foreach ($post as $pKey => $pVal) {
            $_POST[$pKey] = $pVal;
        }

        if (! empty($errors)) {
            $errorsMsg = '';

            foreach ($errors as $error) {
                $errorsMsg .= $error."\n";
            }

            $response = array(
                'status' => 'error',
                'message' => $errorsMsg
            );

            exit(json_encode($response));
        }

        $ticketTitle = wp_strip_all_tags($_POST[EVENTAPPI_PLUGIN_NAME.'_ticket_title']);
        $ticketDesc = $_POST[EVENTAPPI_PLUGIN_NAME.'_ticket_desc'];

        // First, create the post and then use CMB to save the meta data
        $ticketPost = array(
            'post_title'   => $ticketTitle,
            'post_content' => $ticketDesc,
            'post_status'  => 'publish',
            'post_author'  => 1,
            'post_type'    => EVENTAPPI_TICKET_POST_NAME
        );

        // Get the Ticket ID
        $ticketId = wp_insert_post($ticketPost);

        // Save Meta Data (CMB Fields)
        Meta::instance()->updateMetaBox($ticketId);

        // Associate the Event with the Ticket
        update_post_meta($ticketId, EVENTAPPI_TICKET_POST_NAME.'_event_id', $eventId);

        // Add the ticket's position as the last one
        $maxPos = $wpdb->get_var("SELECT MAX(meta_value) FROM `".$wpdb->postmeta."` WHERE meta_key='".EVENTAPPI_TICKET_POST_NAME."_pos'");
        update_post_meta($ticketId, EVENTAPPI_TICKET_POST_NAME.'_pos', ($maxPos + 1));

        // Event TimeZone becomes uneditable
        update_post_meta($eventId, EVENTAPPI_POST_NAME.'_timezone_no_edit', true);

        $response = array(
            'status' => 'success',
            'ticket_id' => $ticketId,
            'message' => __('The ticket was added to the event.', EVENTAPPI_PLUGIN_NAME)
        );

        exit(json_encode($response));
    }

    /**
     *
     */
    public function deleteTicketCallback()
    {
        $nonce = $_POST['ea_nonce'];

        if (! wp_verify_nonce($nonce, EVENTAPPI_PLUGIN_NAME . '_ajax_mode')) {
            exit;
        }

        $ticketId = (int)$_POST['ticket_id'];

        // Get Event ID first
        $eventId = get_post_meta($ticketId, EVENTAPPI_TICKET_POST_NAME.'_event_id', true);

        // Delete Ticket Post Type including the meta fields
        $deleted = wp_delete_post($ticketId);

        if ($deleted->ID) {
            $ticketsLeft = $this->getTickets($eventId, 'count');

            // Event TimeZone becomes editable
            if ($ticketsLeft < 1) {
                delete_post_meta($eventId, EVENTAPPI_POST_NAME.'_timezone_no_edit');
            }

            echo json_encode(array(
                'status' => 'success',
                'tickets_left' => $ticketsLeft
            ));
        }

        exit;
    }

    /**
     * Append to Accordion Ticket after it was successfully added
     * This method is also used to build the edit ticket area when the accordion is created
     *
     * @param int $ticketId
     * @param int $isAjax
     *
     * @return string
     */
    public function appendToAccordionTicket($ticketId = 0, $isAjax = 1)
    {
        if (! $ticketId) {
            $ticketId = (int)$_POST['ticket_id'];
        }

        $data['ticket_id'] = $ticketId;
        $data['ticket_title'] = get_the_title($ticketId);

        $data['edit_ticket_area'] = $this->loadEditTicketCallback($ticketId, false);

        $output = Parser::instance()->parseEventAppiTemplate('Tickets/AppendTicketAccordion', $data);

        if ($isAjax) {
            exit($output);
        } else {
            return $output;
        }
    }

    /* Update the order in which the tickets are listed */
    /**
     *
     */
    public function updateTicketsPosCallback()
    {
        $ticketsPos = trim($_POST['tickets_pos'], ',');

        if ($ticketsPos != '') {
            $ticketIds = explode(',', $ticketsPos);

            foreach ($ticketIds as $pos => $ticketId) {
                $pos = $pos + 1; // let's start from 1

                update_post_meta($ticketId, EVENTAPPI_TICKET_POST_NAME.'_pos', $pos);
            }
        }
        exit;
    }

    /**
     * @param array $ticketMeta
     *
     * @return bool
     */
    public function isOnSale(array $ticketMeta)
    {
        $saleStart = $ticketMeta[EVENTAPPI_TICKET_POST_NAME . '_sale_from'][0];

        if ($saleStart === false) {
            $saleStart = strtotime('yesterday');
        }

        $saleEnd = $ticketMeta[EVENTAPPI_TICKET_POST_NAME . '_sale_to'][0];

        if ($saleEnd === false) {
            $saleEnd = strtotime('tomorrow');
        }

        if ($saleStart < strtotime('now') && $saleEnd > strtotime('now')) {
            return true;
        }

        return false;
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    public function isPurchasable($data = array())
    {
        // Quantity has to be equal or bigger than 1
        if ($data['qty'] < 1) {
            return false;
        }

        $ticketMeta = get_post_meta($data['id']);

        // Does it have an API ID?
        if (intval($data['api_id']) < 1) {
            return false;
        }

        // Is it purchased at the right time?
        if (! $this->isOnSale($ticketMeta)) {
            return false;
        }

        // Are there any tickets left?
        $total = intval($ticketMeta[EVENTAPPI_TICKET_POST_NAME.'_no_available'][0]);

        if ($total < 1) {
            return false;
        }

        // None of the conditions above met, the ticket can be purchased
        return true;
    }

    /* Check if more tickets can be added to the event */
    /**
     *
     */
    public function checkMaxTickets()
    {
        $eventId = (int)$_POST['event_id'];
        $eventIdCur = (int)$_POST['event_id_cur'];

        if ($eventId != $eventIdCur) {
            $totalTickets = $this->getTickets($eventId, 'totalStock');

            $return = array();
            $return['status'] = ($totalTickets >= self::MAX_LITE_TICKETS) ? 'no_publish' : 'do_publish';
        } else {
            $return['status'] = 'do_publish';
        }

        exit(json_encode($return));
    }

    /**
     * @param $purchaseDbId
     *
     * @return bool
     */
    public function assignTicket($purchaseDbId)
    {
        global $wpdb;

        $user = new \WP_User(get_current_user_id());

        $recipientName = $_POST[TicketRegFields::instance()->baseFieldName]['name'];

        $data = array(
            'recipient_email' => $user->data->user_email,
            'recipient_name'  => $recipientName
        );

        $purchasesTable = PluginManager::instance()->tables['purchases'];

        $hash = $wpdb->get_var($wpdb->prepare(
            'SELECT purchased_ticket_hash FROM `'.$purchasesTable.'` WHERE id = %d',
            $purchaseDbId
        ));

        $result = ApiClient::instance()->emailPurchasedTicket($hash, $data);

        if ($result['message'] !== 'OK') {
            return false;
        }

        $wpdb->update(
            $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases',
            array(
                'is_assigned' => '1',
                'assigned_to' => stripslashes($recipientName)
            ),
            array(
                'purchased_ticket_hash' => $hash
            )
        );

        return true;
    }

    /**
     * @param $purchaseDbId
     *
     * @return bool
     */
    public function claimTicket($purchaseDbId)
    {
        global $wpdb;

        $user = new \WP_User(get_current_user_id());

        $data = array(
            'recipient_email' => $user->data->user_email,
            'recipient_name'  => $user->data->display_name
        );

        $purchasesTable = PluginManager::instance()->tables['purchases'];

        $hash = $wpdb->get_var($wpdb->prepare(
            'SELECT purchased_ticket_hash FROM `'.$purchasesTable.'` WHERE id = %d',
            $purchaseDbId
        ));

        $result = ApiClient::instance()->emailPurchasedTicket($hash, $data);

        if ($result['message'] !== 'OK') {
            return false;
        }

        $wpdb->update(
            $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases',
            array(
                'is_claimed' => '1'
            ),
            array(
                'purchased_ticket_hash' => $hash
            )
        );

        return true;
    }

    /**
     * @param $ticketId
     */
    public function deleteTicket($ticketId)
    {
        if (get_post_type($ticketId) != EVENTAPPI_TICKET_POST_NAME || !current_user_can('delete_posts')) {
            return;
        }

        global $wpdb;

        // Remove it from the Plugin's Purchases Table
        $wpdb->delete(PluginManager::instance()->tables['purchases'], array('ticket_id' => $ticketId));
    }
}
