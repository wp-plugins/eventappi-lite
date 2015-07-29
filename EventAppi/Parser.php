<?php namespace EventAppi;

use EventAppi\Helpers\Format;
use EventAppi\Helpers\Sanitizer;

/**
 * Class Parser
 *
 * @package EventAppi
 */
class Parser
{

    /**
     * @var Parser|null
     */
    private static $singleton = null;

    public $nonceAjaxAction;

    public $isCreateEventPage;
    public $isEditEventPage;
    public $isCreateCouponPage;
    public $isEditCouponPage;

    /**
     *
     */
    private function __construct()
    {
        $this->tplCaller = $this;
        $this->nonceAjaxAction = EVENTAPPI_PLUGIN_NAME.'_ajax_mode';
    }

    /**
     * @return Parser|null
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
        add_action('admin_enqueue_scripts', array($this, 'stylesAndScriptsForAdmin'));
        add_action('wp_enqueue_scripts', array($this, 'stylesAndScriptsForPublic'));
        add_action('admin_init', array($this, 'ajaxActions'));

        add_action('init', array($this, 'checkoutToCart'));

        add_filter('body_class', array($this, 'bodyClasses'));

        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
    }

    public function parseOutput($output)
    {
        if (! is_admin()) {
            ob_start();
            echo $output;
            $result = ob_get_contents();
            ob_end_clean();

            return $result;
        } else {
            echo $output;

            return '';
        }
    }

    public function parseTemplate($name, $data = [], $echo = false)
    {
        $templateFile = EVENTAPPI_PLUGIN_TEMPLATE_PATH . $name . '.php';
        if (! file_exists($templateFile)) {
            wp_die("Template {$name} not found.");
        }

        ob_start();
        include $templateFile;
        $result = ob_get_contents();
        ob_end_clean();

        if (is_admin() || $echo) {
            echo $result;
            return '';
        }

        return $result;
    }

    public function parseEventAppiTemplate($name, $data = [], $echo = false)
    {
        $templateFile = __DIR__ . '/Templates/' . $name . '.php';
        if (! file_exists($templateFile)) {
            wp_die("Template {$name} not found.");
        }

        ob_start();
        include $templateFile;
        $result = ob_get_contents();
        if (is_admin() || $echo) {
            echo $result;
        }
        ob_end_clean();

        return $result;
    }

    public function stylesAndScriptsForAdmin()
    {
        $this->enqueueCommonScripts();
        $this->enqueueCommonStyles();
        $this->enqueueAdminScripts();
        $this->enqueueAdminStyles();
        $this->ajaxActions();
        $this->adminAjaxUrl();
        $this->publicAjaxUrl();
    }

    public function stylesAndScriptsForPublic()
    {
        $this->isCreateEventPage = Settings::instance()->isPage('create-event');
        $this->isEditEventPage = Settings::instance()->isPage('edit-event');
        $this->isCreateCouponPage = Settings::instance()->isPage('create-coupon');
        $this->isEditCouponPage = Settings::instance()->isPage('edit-coupon');
        $this->isAnalyticsPage = Settings::instance()->isPage('analytics');

        $this->enqueueCommonScripts();
        $this->enqueueCommonStyles();
        $this->enqueuePublicScripts();
        $this->enqueuePublicStyles();
        $this->ajaxActions();
        $this->adminAjaxUrl();
        $this->publicAjaxUrl();
    }

    public function ajaxActions()
    {
        $this->addAjaxActions('wp_ajax_');
        $this->addAjaxActions('wp_ajax_nopriv_');
    }

    public function adminAjaxUrl()
    {
        $this->eaNonce = wp_create_nonce($this->nonceAjaxAction);

        $localize_list = array(
            'ajax_url'         => admin_url('admin-ajax.php'),
            'plugin_name'      => EVENTAPPI_PLUGIN_NAME,
            'event_post_name'  => EVENTAPPI_POST_NAME,
            'ticket_post_name' => EVENTAPPI_TICKET_POST_NAME,
            'nonce'            => $this->eaNonce,
            'user_id'          => get_current_user_id(),
            'date_format'      => Format::getJSDateFormatString(get_option('date_format')),
            'time_format'      => Format::getJSDateFormatString(get_option('time_format')),
            'post'             => isset($_GET['post']) ? $_GET['post'] : '',
            'text'             => $this->jsLangText('admin')
        );

        // gives .js files a eventappi_ajax_obj that contains two parameters, ajax_url and nonce (CSRF token)
        wp_localize_script(EVENTAPPI_PLUGIN_NAME . '-admin', EVENTAPPI_PLUGIN_NAME . '_ajax_admin_obj', $localize_list);
    }

    public function publicAjaxUrl()
    {
        global $current_user;

        $email = '';
        if (isset($current_user->user_email)) {
            $email = $current_user->user_email;
        }

        $event_id = 0;
        if (isset($_GET['event_id'])) {
            $event_id = Sanitizer::instance()->sanitize($_GET['event_id'], 'integer', 10);
        }

        /*
         * LOCALIZE FRONTEND JS
         */

        // gives .js files a eventappi_ajax_obj that contains useful data for use in JS
        $localize_list = $localize_common = array(
            'ajax_url'        => admin_url('admin-ajax.php'),
            'coupons_url'     => get_permalink(Settings::instance()->getPageId('coupons')),
            'list_events_url' => get_permalink(Settings::instance()->getPageId('my-account')),
            'plugin_name'     => EVENTAPPI_PLUGIN_NAME,
            'event_post_name' => EVENTAPPI_POST_NAME,
            'nonce'           => $this->eaNonce, // declared in adminAjaxUrl()
            'user_id'         => get_current_user_id(),
            'email'           => $email,
            'date_format'     => Format::getJSDateFormatString(get_option('date_format')),
            'time_format'     => Format::getJSDateFormatString(get_option('time_format')),
            'post'            => isset($_GET['post']) ? $_GET['post'] : '',
            'event_id'        => $event_id
        );

        // Add Text into the list that is translatable
        // will be added to the JS file (e.g. eventappi_ajax_obj.text.not_enough_tickets)
        $localize_list['text'] = $this->jsLangText('frontend');

        // Add the URLs to the EventAppi pages
        foreach (PluginManager::instance()->customPages() as $vals) {
            $pageLocSlug = str_replace('-', '_', $vals['id']).'_url';
            $localize_list[$pageLocSlug] = get_permalink(Settings::instance()->getPageId($vals['id']));
        }

        wp_localize_script(EVENTAPPI_PLUGIN_NAME . '-frontend', EVENTAPPI_PLUGIN_NAME . '_ajax_obj', $localize_list);

        /*
         * LOCALIZE FRONTEND REPORTS JS
         */

        $localize_list_reports = array_merge(array(
            'text' => $this->jsLangText('frontend-reports')
        ), $localize_common);

        wp_localize_script(
            EVENTAPPI_PLUGIN_NAME . '-frontend-reports',
            EVENTAPPI_PLUGIN_NAME . '_ajax_obj_reports',
            $localize_list_reports
        );
    }

    private function addAjaxActions($prefix)
    {
        // User creation for ticket sales
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME . '_user_create',
            array(User::instance(), 'ajaxUserCreateHandler')
        );

        // Add items to the cart
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME . '_shopping_cart_add',
            array(ShoppingCart::instance(), 'ajaxAddToCartHandler')
        );

        // remove items from the cart
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME . '_shopping_cart_remove',
            array(ShoppingCart::instance(), 'ajaxRemoveFromCartHandler')
        );


        // ticket sales stats
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME . '_frontend_stats_ticket_sales',
            array(Analytics::instance(), 'ajaxApiStatsTicketSalesHandler')
        );

        // ticket availability stats
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME . '_ticket_stats',
            array(Analytics::instance(), 'ajaxApiTicketStatsHandler')
        );


        // ticket payment
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME . '_pay',
            array(Payment::instance(), 'ajaxPayHandler')
        );

        // ticket handler - SEND
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME . '_send_ticket',
            array(Tickets::instance(), 'ajaxSendTicketHandler')
        );

        // ticket handler - ASSIGN
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME . '_assign_ticket',
            array(Tickets::instance(), 'assignTicketHandler')
        );

        // ticket handler - CLAIM
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME . '_claim_ticket',
            array(Tickets::instance(), 'claimTicketHandler')
        );

        // Attendees Update Status
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME.'_update_attendee_status',
            array(Attendees::instance(), 'loadUpdateAttendeeStatusCallback')
        );

        // Load Edit Ticket Area
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME.'_load_edit_ticket',
            array(TicketPostType::instance(), 'loadEditTicketCallback')
        );

        // Edit Ticket
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME.'_edit_ticket',
            array(TicketPostType::instance(), 'editTicketCallback')
        );

        // Add Ticket
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME.'_add_ticket',
            array(TicketPostType::instance(), 'addTicketCallback')
        );

        // Delete Ticket
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME.'_del_ticket',
            array(TicketPostType::instance(), 'deleteTicketCallback')
        );

        // Append Created Ticket to the Accordion
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME.'_append_to_accordion_ticket',
            array(TicketPostType::instance(), 'appendToAccordionTicket')
        );

        // Update Tickets Positions (Sorting is done via Drag & Drop)
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME.'_update_tickets_pos',
            array(TicketPostType::instance(), 'updateTicketsPosCallback')
        );

        // Add Ticket Registration Field
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME.'_add_ticket_reg_field',
            array(TicketRegFields::instance(), 'addRegFieldCallback')
        );

        // Append Created Registration Field to the Accordion
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME.'_append_to_accordion_reg_field',
            array(TicketRegFields::instance(), 'appendToAccordionRegField')
        );

        // Delete Ticket Registration Field
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME.'_del_reg_field',
            array(TicketRegFields::instance(), 'deleteRegFieldCallback')
        );

        // Update Registration Fields Positions (Sorting is done via Drag & Drop)
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME.'_update_reg_fields_pos',
            array(TicketRegFields::instance(), 'updateRegFieldsPosCallback')
        );

        // Check if we can add more tickets to the selected events
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME.'_check_event_max_tickets',
            array(TicketPostType::instance(), 'checkMaxTickets')
        );


        // Check if Event has API ID and show an error if it doesn't
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME.'_check_event_api',
            array(EventPostType::instance(), 'showNoApiErrorCallback')
        );
    }

    private function enqueueCommonStyles()
    {
        // register our stylesheets
        wp_register_style(
            EVENTAPPI_PLUGIN_NAME . '-common',
            EVENTAPPI_PLUGIN_ASSETS_URL . 'css/' . EVENTAPPI_PLUGIN_NAME . '-common.css',
            array(),
            EVENTAPPI_PLUGIN_VERSION,
            'all'
        );

        // enqueue styles
        wp_enqueue_style(
            EVENTAPPI_PLUGIN_NAME . '-jquery-ui',
            '//ajax.googleapis.com/ajax/libs/jqueryui/1.11.3/themes/smoothness/jquery-ui.css'
        );
        wp_enqueue_style(
            EVENTAPPI_PLUGIN_NAME . '-timepicker',
            '//cdnjs.cloudflare.com/ajax/libs/jquery-timepicker/1.6.8/jquery.timepicker.min.css'
        );
        wp_enqueue_style(EVENTAPPI_PLUGIN_NAME . '-common');
    }

    private function enqueueAdminStyles()
    {
        // register our stylesheets
        wp_register_style(
            EVENTAPPI_PLUGIN_NAME . '-admin',
            EVENTAPPI_PLUGIN_ASSETS_URL . 'css/' . EVENTAPPI_PLUGIN_NAME . '-admin.css',
            array(),
            EVENTAPPI_PLUGIN_VERSION,
            'all'
        );

        // enqueue styles
        wp_enqueue_style(EVENTAPPI_PLUGIN_NAME . '-admin');
        wp_enqueue_style('chosen', EVENTAPPI_PLUGIN_ASSETS_URL . '/js/chosen/chosen.css');
    }

    private function enqueuePublicStyles()
    {
        // register our stylesheets
        wp_register_style(
            EVENTAPPI_PLUGIN_NAME . '-frontend',
            EVENTAPPI_PLUGIN_ASSETS_URL . 'css/' . EVENTAPPI_PLUGIN_NAME . '-frontend.css'
        );

        // enqueue styles
        wp_enqueue_style(EVENTAPPI_PLUGIN_NAME . '-frontend');

        wp_enqueue_style('dashicons');

        if ($this->isEditCouponPage || $this->isCreateCouponPage) {
            wp_enqueue_style('chosen', EVENTAPPI_PLUGIN_ASSETS_URL . '/js/chosen/chosen.css');
        }
    }

    private function enqueueCommonScripts()
    {
        global $post;

        $pathAndPrefix = EVENTAPPI_PLUGIN_ASSETS_URL . 'js/' . EVENTAPPI_PLUGIN_NAME;

        // enqueue scripts
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core', null, array('jquery'));
        wp_enqueue_script('jquery-ui-autocomplete', null, array('jquery'));

        if (
            ( isset($_GET['page']) && ($_GET['page'] == EVENTAPPI_PLUGIN_NAME.'-purchases') && is_admin() ) ) {
            wp_enqueue_script('jquery-ui-dialog', null, array('jquery'));
        }

        // Register & Enqueue Reports ONLY on the page(s) we need
        // for the Front-end as well as the Dashboard

        if(
            (isset($_GET['page']) && ($_GET['page'] == EVENTAPPI_PLUGIN_NAME.'-analytics') && is_admin())) {

            wp_enqueue_script('highcharts', '//code.highcharts.com/highcharts.js', null, array('jquery'));
            wp_enqueue_script('highcharts-3d', '//code.highcharts.com/highcharts-3d.js', null, array('jquery'));
            wp_enqueue_script('highcharts-data', '//code.highcharts.com/modules/data.js', null, array('jquery'));
            wp_enqueue_script('highcharts-exporting', '//code.highcharts.com/modules/exporting.js', null, array('jquery'));

            wp_register_script(EVENTAPPI_PLUGIN_NAME . '-frontend-reports', $pathAndPrefix . '-frontend-reports.js');

            wp_enqueue_script(
                EVENTAPPI_PLUGIN_NAME . '-frontend-reports',
                $pathAndPrefix . '-frontend-reports.js',
                array('jquery'),
                EVENTAPPI_PLUGIN_VERSION
            );
        }

        if (
            ( ( in_array($_GET['post_type'], array(EVENTAPPI_POST_NAME, EVENTAPPI_TICKET_POST_NAME))
             || in_array(get_post_type($post->ID), array(EVENTAPPI_POST_NAME, EVENTAPPI_TICKET_POST_NAME)) ) && is_admin() ) ) {

            wp_enqueue_script('jquery-ui-tabs', null, array('jquery'));
            wp_enqueue_script('jquery-ui-datepicker', null, array('jquery'));

            // Not available on Create Event Page
            if (! $this->isCreateEventPage) {
                wp_enqueue_script('jquery-ui-accordion', null, array('jquery'));

                // If not edit coupon page
                if (! $this->isEditCouponPage) {
                    wp_enqueue_script('jquery-ui-sortable', null, array('jquery'));
                }

                if ($this->isCreateCouponPage || $this->isEditCouponPage) {
                    // If edit coupon page
                    wp_enqueue_script(
                        'chosen',
                        EVENTAPPI_PLUGIN_ASSETS_URL . '/js/chosen/chosen.jquery.min.js',
                        array('jquery'),
                        false
                    );
                }
            }

            wp_enqueue_script(
                EVENTAPPI_PLUGIN_NAME . '-timepicker',
                '//cdnjs.cloudflare.com/ajax/libs/jquery-timepicker/1.6.8/jquery.timepicker.min.js',
                null,
                array('jquery')
            );

            add_thickbox();
        }

        // enqueue scripts
        wp_enqueue_script(
            EVENTAPPI_PLUGIN_NAME . '-common',
            $pathAndPrefix . '-common.js',
            array('jquery'),
            EVENTAPPI_PLUGIN_VERSION
        );

        // We need some elements from the common CSS
        wp_enqueue_style(
            EVENTAPPI_PLUGIN_NAME . '-common',
            EVENTAPPI_PLUGIN_ASSETS_URL . 'css/' . EVENTAPPI_PLUGIN_NAME . '-common.css',
            array(),
            EVENTAPPI_PLUGIN_VERSION,
            'all'
        );
    }

    private function enqueueAdminScripts()
    {
        global $wpdb;

        $pathAndPrefix = EVENTAPPI_PLUGIN_ASSETS_URL . 'js/' . EVENTAPPI_PLUGIN_NAME;

        // enqueue scripts
        wp_enqueue_script(
            EVENTAPPI_PLUGIN_NAME . '-admin',
            $pathAndPrefix . '-admin.js',
            array('jquery'),
            EVENTAPPI_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'chosen',
            EVENTAPPI_PLUGIN_ASSETS_URL . '/js/chosen/chosen.jquery.min.js',
            array('jquery'),
            false
        );

        // Load Thickbox if we are on Add/Edit Event Page (For the Tickets)
        $isNewEventPage = (isset($_GET['post_type']) && $_GET['post_type'] == EVENTAPPI_POST_NAME);
        $isEditEventPage = false; // default value

        // Only proceed if the first condition is false to avoid extra queries
        if (! $isNewEventPage && isset($_GET['post']) && isset($_GET['action']) && ($_GET['action'] == 'edit')) {
            $postId = (int)$_GET['post'];
            $isEditEventPage = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID='".$postId."' && post_type='".EVENTAPPI_POST_NAME."'"
            );
        }

        // Is any of these conditions met? Load the Thickbox
        if ($isNewEventPage || $isEditEventPage) {
            add_thickbox();
        }
    }

    private function enqueuePublicScripts()
    {
        $loadDatePicker = false; // default

        // register our scripts
        wp_register_script(
            EVENTAPPI_PLUGIN_NAME . '-frontend',
            EVENTAPPI_PLUGIN_ASSETS_URL . 'js/' . EVENTAPPI_PLUGIN_NAME . '-frontend.js',
            array('jquery'),
            EVENTAPPI_PLUGIN_VERSION
        );

        // enqueue scripts
        wp_enqueue_script(EVENTAPPI_PLUGIN_NAME . '-frontend');
        wp_enqueue_script(EVENTAPPI_PLUGIN_NAME . '-block-ui', '//malsup.github.io/jquery.blockUI.js');

        // Load the datepicker for Preview Registration Fields Page (if there is any date field added)
        $eaRegPrevPageId = Settings::instance()->isPage('preview-reg-fields');

        if ($eaRegPrevPageId && isset($_GET['ticket_id']) && $_GET['ticket_id'] > 0) {
            $regFields = serialize(
                get_post_meta((int)$_GET['ticket_id'], EVENTAPPI_TICKET_POST_NAME.'_reg_fields', true)
            );

            if (preg_match('/"input_date"/i', $regFields)) {
                $loadDatePicker = true;
            }
        }

        if (! $loadDatePicker) {
            // Load the datepicker for Add/Create Coupon Frontend Page
            if ((Settings::instance()->isPage('edit-coupon') && isset($_GET['coupon_id']) && $_GET['coupon_id'] > 0)
               || (Settings::instance()->isPage('create-coupon')) ) {
                $loadDatePicker = true;
            }
        }

        // Has the state changed? Load the Date Picker ;-)
        if ($loadDatePicker) {
            wp_enqueue_script('jquery-ui-datepicker', null, array('jquery'));
        }
    }

    /* This is the text that is shown inside the .JS files (alerts, messages etc.)  */
    private function jsLangText($get = 'frontend')
    {
        $array = array();

        if ($get == 'frontend') {
            $array = array(
                'assign_ticket_error' => __('Please fill in all values before claiming the ticket.', EVENTAPPI_PLUGIN_NAME),
                'claim_ticket_error' => __('Please fill in all values before claiming the ticket.', EVENTAPPI_PLUGIN_NAME),
                'not_enough_tickets' => __('There are not enough tickets available. Extra tickets have been removed.', EVENTAPPI_PLUGIN_NAME),
                'cat_not_created' => __('Category could not be created. Please try again.', EVENTAPPI_PLUGIN_NAME),
                'choose_venue_event' => __('Choose Venue for this Event', EVENTAPPI_PLUGIN_NAME),
                'show_form' => __('Show Form', EVENTAPPI_PLUGIN_NAME),
                'pass_not_match_error' => __('The passwords entered do not match.', EVENTAPPI_PLUGIN_NAME)."\n".
                 __('If you do not want to update the password, please leave both "New Password" and "Confirm Password" fields empty.', EVENTAPPI_PLUGIN_NAME),
                'venue_name_exists' => __('This venue name already exists.', EVENTAPPI_PLUGIN_NAME),
                'event_name_req' => __('An event name is required.', EVENTAPPI_PLUGIN_NAME),
                'new_ticket' => __('New Ticket', EVENTAPPI_PLUGIN_NAME),
                'remove_ticket' => __('remove this ticket', EVENTAPPI_PLUGIN_NAME),
                'purchase_successful_title' => __('Purchase Successful!', EVENTAPPI_PLUGIN_NAME),
                'thank_you_purchase' => __('Thank you for your purchase your confirmation of your order will be emailed to you.', EVENTAPPI_PLUGIN_NAME),
                'cart_item_fail_del' => __('Sorry we could not remove this item, please try again.', EVENTAPPI_PLUGIN_NAME),
                'event_start_date_req' =>  __('Please pick a start date for the event.', EVENTAPPI_PLUGIN_NAME),
                'event_start_time_req' => __('Please pick a start time for the event.', EVENTAPPI_PLUGIN_NAME),

            );
        } elseif ($get == 'frontend-reports') {
            $array = array(
                // Tickets Sold
                'tickets_sold' => __('Tickets sold', EVENTAPPI_PLUGIN_NAME),
                'ticket_sales' => __('Ticket sales', EVENTAPPI_PLUGIN_NAME),

                'tickets_sold_range_week' => __('Tickets sold per event per day for this week', EVENTAPPI_PLUGIN_NAME),
                'tickets_sold_range_month' => __('Tickets sold per event per week for this month', EVENTAPPI_PLUGIN_NAME),
                'tickets_sold_range_day' => __('Tickets sold per event per day', EVENTAPPI_PLUGIN_NAME),

                // Revenue
                'revenue_range_week' => __('Revenue per event for this week', EVENTAPPI_PLUGIN_NAME),
                'revenue_range_month' => __('Revenue per event for this month', EVENTAPPI_PLUGIN_NAME),
                'revenue_range_custom' => __('Revenue per event', EVENTAPPI_PLUGIN_NAME),
                'revenue_in_currency' => __('Revenue in $', EVENTAPPI_PLUGIN_NAME),

                // Ticket(s) Availability
                'ticket_availability' => __('Ticket availability', EVENTAPPI_PLUGIN_NAME),
                'ticket_availability_per_event' => __('Ticket availability per event', EVENTAPPI_PLUGIN_NAME),
                'tickets_available' => __('Tickets available', EVENTAPPI_PLUGIN_NAME),

                'events' => __('Events', EVENTAPPI_PLUGIN_NAME),

                'week' => __('Week', EVENTAPPI_PLUGIN_NAME),
                'month' => __('Month', EVENTAPPI_PLUGIN_NAME),
                'custom' => __('Custom', EVENTAPPI_PLUGIN_NAME),

                // Month names
                'jan' => __('Jan', EVENTAPPI_PLUGIN_NAME),
                'feb' => __('Feb', EVENTAPPI_PLUGIN_NAME),
                'mar' => __('Mar', EVENTAPPI_PLUGIN_NAME),
                'apr' => __('Apr', EVENTAPPI_PLUGIN_NAME),
                'may' => __('May', EVENTAPPI_PLUGIN_NAME),
                'jun' => __('Jun', EVENTAPPI_PLUGIN_NAME),
                'jul' => __('Jul', EVENTAPPI_PLUGIN_NAME),
                'aug' => __('Aug', EVENTAPPI_PLUGIN_NAME),
                'sep' => __('Sep', EVENTAPPI_PLUGIN_NAME),
                'oct' => __('Oct', EVENTAPPI_PLUGIN_NAME),
                'nov' => __('Nov', EVENTAPPI_PLUGIN_NAME),
                'dec' => __('Dec', EVENTAPPI_PLUGIN_NAME)
            );
        } elseif ($get == 'admin') {
            // admin text here
            $array = array(
                'organisers' => 'Organisers'
            );
        }

        // These are used for both front and back end
        $common = array(
            'assign_ticket_specify_email' => __('Please specify an email address to send the ticket to.', EVENTAPPI_PLUGIN_NAME),
            'no_tickets_found_dd' => __('Oops, nothing found!', EVENTAPPI_PLUGIN_NAME),
            'new_ticket_tab_title' => __('Ticket %d', EVENTAPPI_PLUGIN_NAME),
            'venue_not_selected' => __('Please make sure you select a venue.', EVENTAPPI_PLUGIN_NAME),
            'ticket_add_title' => __('Please type a title.', EVENTAPPI_PLUGIN_NAME),
            'ticket_add_qty' => __('Please specify how many tickets are available.', EVENTAPPI_PLUGIN_NAME),
            'ticket_for_sale_zero_price' => __('You have selected a "Sale" ticket, but the price is zero. Please correct this.', EVENTAPPI_PLUGIN_NAME),
            'ticket_for_free_bigger_zero' => __('You have selected a "Free" ticket, but the price is bigger than zero. Please correct this.', EVENTAPPI_PLUGIN_NAME),
            'ticket_select_event' => __('You have to select an event that will be associated with the ticket.', EVENTAPPI_PLUGIN_NAME),
            'ticket_select_type' => __('Please select a ticket type.', EVENTAPPI_PLUGIN_NAME),
            'ticket_del_error' => __('Due to an internal error, the ticket could not be deleted. Please try again later!'),
        );

        return array_merge($array, $common);
    }

    public function bodyClasses($classes)
    {
        if (Settings::instance()->isPage('create-event') || Settings::instance()->isPage('edit-event')) {
            $classes[] = 'post-type-'.EVENTAPPI_POST_NAME;
        }

        return $classes;
    }

    /* If the cart is empty, redirect the user to the Cart page if they access Checkout page */
    public function checkoutToCart()
    {
        if ($_SESSION[EVENTAPPI_PLUGIN_NAME.'_empty_cart']) {
            if (Settings::instance()->isPage('checkout')) {
                wp_redirect(get_permalink(Settings::instance()->getPageId('cart')));
                exit;
            }
        }
    }
}
