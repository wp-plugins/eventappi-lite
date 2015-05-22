<?php namespace EventAppi;

use EventAppi\Helpers\Logger;

/**
 * Class CouponPostType
 *
 * @package EventAppi
 */
class CouponPostType
{
    /**
     * @var CouponPostType|null
     */
    private static $singleton = null;

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return CouponPostType|null
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
        add_action('init', array($this, 'createPostType'));
        add_action('save_post', array($this, 'savePost'));

        add_action('add_meta_boxes', array($this, 'addCouponMetabox'));

        add_filter('cmb_meta_boxes', array($this, 'postTypeMetaBoxes'));

        // Custom Number Field
        add_filter('cmb_field_types', function ($cmb_field_types) {
            $cmb_field_types['number'] = 'EventAppi\Helpers\CMBNumberField';
            return $cmb_field_types;
        });

        // a filter for hidden meta fields
        add_filter('cmb_field_types', function ($cmb_field_types) {
            $cmb_field_types['hidden'] = 'EventAppi\Helpers\CMBHiddenField';

            return $cmb_field_types;
        });

        add_action('admin_enqueue_scripts', array($this, 'loadScripts'));

        // AJAX action
        add_action('wp_ajax_load_ticket_options', array($this, 'loadTicketOptionsCallback'));
    }

    public function createPostType()
    {
        $labels = array(
            'name'               => _x('Event Coupons', 'post type general name', EVENTAPPI_PLUGIN_NAME),
            'singular_name'      => _x('Coupon', 'post type singular name', EVENTAPPI_PLUGIN_NAME),
            'add_new'            => _x('Add new Coupon', 'event', EVENTAPPI_PLUGIN_NAME),
            'add_new_item'       => __('Add new Coupon', EVENTAPPI_PLUGIN_NAME),
            'edit_item'          => __('Edit Coupon', EVENTAPPI_PLUGIN_NAME),
            'new_item'           => __('New Coupon', EVENTAPPI_PLUGIN_NAME),
            'view_item'          => __('View Coupon', EVENTAPPI_PLUGIN_NAME),
            'search_items'       => __('Search Coupons', EVENTAPPI_PLUGIN_NAME),
            'not_found'          => __('No coupon found', EVENTAPPI_PLUGIN_NAME),
            'not_found_in_trash' => __('No coupon found in Trash', EVENTAPPI_PLUGIN_NAME),
        );

        $taxonomies = array();

        $args = array(
            'labels'            => $labels,
            'description'       => '',
            'menu_position'     => 5,
            'menu_icon'         => 'dashicons-tickets-alt',
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_nav_menus' => false,
            'show_in_admin_bar' => true,
            'supports'          => array('title', 'editor'),
            'taxonomies'        => $taxonomies,
            'has_archive'       => true,
            'public'            => false,
            'rewrite'           => false, // No URL visible below the title as it's not needed/used
            'query_var'         => 'coupons'
        );

        register_post_type(EVENTAPPI_COUPON_POST_NAME, $args);
    }

    public function postTypeMetaBoxes(array $meta)
    {
        $couponFields = array(
            array(
                'name' => __('Numeric Value', EVENTAPPI_PLUGIN_NAME),
                'id' => EVENTAPPI_COUPON_POST_NAME . '_val',
                'type' => 'number',
                'cols' => 2,
                'attributes' => array('min' => 0)
            ),
            array(
                'name' => __('Discount Type', EVENTAPPI_PLUGIN_NAME),
                'id'   => EVENTAPPI_COUPON_POST_NAME . '_type',
                'type' => 'select',
                'cols' => 2,
                'options' => array(
                    'percentage' => __('Percentage (%)', EVENTAPPI_PLUGIN_NAME),
                    'fixed'      => __('Fixed Amount', EVENTAPPI_PLUGIN_NAME),
                )
            ),
            array(
                'name' => __('Number of usages', EVENTAPPI_PLUGIN_NAME),
                'id' => EVENTAPPI_COUPON_POST_NAME . '_usages',
                'type' => 'number',
                'cols' => 2,
                'attributes' => array('min' => 0)
            )
        );

        $meta[] = array(
            'title'    => __('Coupon Options', EVENTAPPI_PLUGIN_NAME),
            'pages'    => EVENTAPPI_COUPON_POST_NAME,
            'context'  => 'normal',
            'priority' => 'high',
            'fields'   => $couponFields
        );

        return $meta;
    }

    public function addCouponMetabox()
    {
        add_meta_box(
            EVENTAPPI_COUPON_POST_NAME . '_coupons',
            __('Coupon To Tickets', EVENTAPPI_COUPON_POST_NAME),
            array($this, 'prepareCouponMetabox'),
            EVENTAPPI_COUPON_POST_NAME
        );
    }

    public function prepareCouponMetabox($post)
    {
        wp_nonce_field(EVENTAPPI_COUPON_POST_NAME . '_meta_box', EVENTAPPI_COUPON_POST_NAME . '_tickets_nonce');

        $args = array(
            'post_type'      => EVENTAPPI_POST_NAME,
            'posts_per_page' => - 1,
            'post_status'    => 'publish',
            'order'          => 'DESC',
            'orderby'        => 'meta_value',
            'meta_query'     => array(
                array(
                    'key' => EVENTAPPI_POST_NAME . '_start_date'
                )
            )
        );

        $data = get_posts($args);

        echo Parser::instance()->parseEventAppiTemplate('CouponMetaBoxes', $data);
    }

    public function savePost($postId)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (get_post_type($postId) !== EVENTAPPI_COUPON_POST_NAME) {
            return;
        }

        if ($_REQUEST['action'] === 'trash') {
            // TODO: we probably need to deal with deletions at some point
            return;
        }

        if (is_array($_POST['event_tickets'])) { // Multiple select, having [] at the end of the name in the front-end

            foreach ($_POST['event_tickets'] as $event_id => $ticket_list) {
                update_post_meta($postId, 'event_tickets_' . (int)$event_id, $ticket_list);
            }
        }
    }

    public function loadScripts()
    {
        global $post;

        // Only load the scripts if we are on add/edit coupon page
        if (!isset($post) || EVENTAPPI_COUPON_POST_NAME != $post->post_type) {
            return;
        }

        wp_enqueue_script('jquery-ui-accordion');

        wp_enqueue_style('chosen', EVENTAPPI_PLUGIN_ASSETS_URL . '/js/chosen/chosen.css');
        wp_enqueue_script(
            'chosen',
            EVENTAPPI_PLUGIN_ASSETS_URL . '/js/chosen/chosen.jquery.min.js',
            array('jquery'),
            false
        );

        // in JavaScript, object properties are accessed as ajax_object.ajax_url
        wp_localize_script('eventappi-admin', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
    }

    public function loadTicketOptionsCallback()
    {
        $post_id  = (int) $_POST['post_id'];
        $event_id = (int) $_POST['event_id'];

        // Get all tickets for this event and print them for the <select>
        $tickets = get_the_terms($event_id, $taxonomy = 'ticket');

        $output = '';

        if (!empty($tickets)) {
            // Fetch the saved tickets and show them as selected
            $ticket_ids = get_post_meta($post_id, 'event_tickets_' . $event_id);

            if( ! is_array($ticket_ids[0]) ) {
                $ticket_ids = array(0 => array()); // No tickets? Set an empty one for the verification below to avoid errors
            }

            foreach ($tickets as $val) {
                $selected = (in_array($val->term_id, $ticket_ids[0])) ? 'selected="selected"' : '';
                $output .= '<option ' . $selected . ' value="' . $val->term_id . '">' . $val->name . '</option>';
            }
        }

        if ($output) {
            echo json_encode(['success' => 1, 'output' => $output]);
        } else {
            echo json_encode([
                'success' => 0,
                'output'  => __('There are no tickets associated with this event', EVENTAPPI_PLUGIN_NAME)
            ]);
        }

        wp_die();
    }
}
