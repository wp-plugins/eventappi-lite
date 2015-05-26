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

    /**
     *
     */
    private function __construct()
    {
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
    }

    public function parseOutput($output)
    {
        if ( ! is_admin()) {
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
        if ( ! file_exists($templateFile)) {
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
        if ( ! file_exists($templateFile)) {
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
        $title_nonce = wp_create_nonce(EVENTAPPI_PLUGIN_NAME . '_world');

        global $current_user;

        $email = '';
        if (isset($current_user->user_email)) {
            $email = $current_user->user_email;
        }

        $event_id = 0;
        if (isset($_GET['event_id'])) {
            $event_id = Sanitizer::instance()->sanitize($_GET['event_id'], 'integer', 10);
        }

        $localize_list = array(
            'ajax_url'    => admin_url('admin-ajax.php'),
            'plugin_name' => EVENTAPPI_PLUGIN_NAME,
            'nonce'       => $title_nonce,
            'user_id'     => get_current_user_id(),
            'email'       => $email,
            'date_format' => Format::getJSDateFormatString(get_option('date_format')),
            'time_format' => Format::getJSDateFormatString(get_option('time_format')),
            'post'        => isset($_GET['post']) ? $_GET['post'] : '',
            'event_id'    => $event_id,
            'text'        => $this->jsLangText('admin')
        );

        //gives .js files a eventappi_ajax_obj that contains two parameters, ajax_url and nonce(csrf token)
        wp_localize_script(EVENTAPPI_PLUGIN_NAME . '-admin', EVENTAPPI_PLUGIN_NAME . '_ajax_admin_obj', $localize_list);
    }

    public function publicAjaxUrl()
    {
        $title_nonce = wp_create_nonce(EVENTAPPI_PLUGIN_NAME . '_world');

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
            'ajax_url'    => admin_url('admin-ajax.php'),
            'plugin_name' => EVENTAPPI_PLUGIN_NAME,
            'nonce'       => $title_nonce,
            'user_id'     => get_current_user_id(),
            'email'       => $email,
            'date_format' => Format::getJSDateFormatString(get_option('date_format')),
            'time_format' => Format::getJSDateFormatString(get_option('time_format')),
            'post'        => isset($_GET['post']) ? $_GET['post'] : '',
            'event_id'    => $event_id
        );

        // Add translatable Text into the list - will be added to the JS file
        $localize_list['text'] = $this->jsLangText();

        // Add the URLs to the EventAppi pages
        foreach(PluginManager::instance()->customPages() as $page) {
            $page_index = str_replace('-', '_', $page['id']) . '_url';
            $localize_list[$page_index] = get_permalink(Settings::instance()->getPageId($page['id']));
        }

        wp_localize_script(EVENTAPPI_PLUGIN_NAME . '-frontend', EVENTAPPI_PLUGIN_NAME . '_ajax_obj', $localize_list);

        /*
         * LOCALIZE FRONTEND REPORTS JS
         */

        $localize_list_reports = array_merge(array(
            'text' => $this->jsLangText('frontend-reports')
        ), $localize_common);

        wp_localize_script(EVENTAPPI_PLUGIN_NAME . '-frontend-reports', EVENTAPPI_PLUGIN_NAME . '_reports_ajax_obj', $localize_list_reports);
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
            array(Tickets::instance(), 'ajaxAssignTicketHandler')
        );

        // ticket handler - CLAIM
        add_action(
            $prefix . EVENTAPPI_PLUGIN_NAME . '_claim_ticket',
            array(Tickets::instance(), 'ajaxClaimTicketHandler')
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
    }

    private function enqueueCommonScripts()
    {
        global $post;

        $pathAndPrefix = EVENTAPPI_PLUGIN_ASSETS_URL . 'js/' . EVENTAPPI_PLUGIN_NAME;

        // enqueue scripts
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core', null, array('jquery'));
        wp_enqueue_script('jquery-ui-autocomplete', null, array('jquery'));
        wp_enqueue_script('jquery-ui-dialog', null, array('jquery'));

        // Register & Enqueue Reports ONLY on the page(s) we need
        // for the Front-end as well as the Dashboard

        if(
            ( isset($_GET['page']) && ($_GET['page'] == EVENTAPPI_PLUGIN_NAME.'-analytics') && is_admin() ) ) {

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

        // Used when you Create/Edit Event

        if(
            ( ($_GET['post_type'] == EVENTAPPI_POST_NAME || get_post_type($post->ID) == EVENTAPPI_POST_NAME) && is_admin() ) ) {

            wp_enqueue_script('jquery-ui-tabs', null, array('jquery'));
            wp_enqueue_script('jquery-ui-datepicker', null, array('jquery'));

            wp_enqueue_script(
                EVENTAPPI_PLUGIN_NAME . '-timepicker',
                '//cdnjs.cloudflare.com/ajax/libs/jquery-timepicker/1.6.8/jquery.timepicker.min.js',
                null,
                array('jquery')
            );

        }
    }

    private function enqueueAdminScripts()
    {
        $pathAndPrefix = EVENTAPPI_PLUGIN_ASSETS_URL . 'js/' . EVENTAPPI_PLUGIN_NAME;

        // register our scripts
        wp_register_script(
            EVENTAPPI_PLUGIN_NAME . '-admin',
            EVENTAPPI_PLUGIN_ASSETS_URL . 'js/' . EVENTAPPI_PLUGIN_NAME . '-admin.js'
        );

        // enqueue scripts
        wp_enqueue_script(
            EVENTAPPI_PLUGIN_NAME . '-admin',
            $pathAndPrefix . '-admin.js',
            array('jquery'),
            EVENTAPPI_PLUGIN_VERSION
        );

    }

    private function enqueuePublicScripts()
    {
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
    }

    private function jsLangText($get = 'frontend') {

        if($get == 'frontend') {

            return array(
                'assign_ticket_error' => __('Please fill in all values before claiming the ticket.', EVENTAPPI_PLUGIN_NAME),
                'claim_ticket_error' => __('Please fill in all values before claiming the ticket.', EVENTAPPI_PLUGIN_NAME),
                'not_enough_tickets' => __('There are not enough tickets available. Extra tickets have been removed.', EVENTAPPI_PLUGIN_NAME),
                'cat_not_created' => __('Category could not be created. Please try again.', EVENTAPPI_PLUGIN_NAME),
                'choose_venue_event' => __('Choose Venue for this Event', EVENTAPPI_PLUGIN_NAME),
                'show_form' => __('Show Form', EVENTAPPI_PLUGIN_NAME),
                'venue_name_exists' => __('This venue name already exists.', EVENTAPPI_PLUGIN_NAME),
                'event_name_req' => __('An event name is required.', EVENTAPPI_PLUGIN_NAME),
                'new_ticket' => __('New Ticket', EVENTAPPI_PLUGIN_NAME),
                'remove_ticket' => __('remove this ticket', EVENTAPPI_PLUGIN_NAME),
                'purchase_successful_title' => __('Purchase Successful!', EVENTAPPI_PLUGIN_NAME),
                'thank_you_purchase' => __('Thank you for your purchase your confirmation of your order will be emailed to you.', EVENTAPPI_PLUGIN_NAME),
                'cart_item_fail_del' => __('Sorry we could not remove this item, please try again.', EVENTAPPI_PLUGIN_NAME)
            );

        } elseif($get == 'frontend-reports') {

            return array(
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

        } else if($get == 'admin') {

            return array(
                'assign_ticket_specify_email' => __('Please specify an email address to send the ticket to.', EVENTAPPI_PLUGIN_NAME),
                'no_tickets_found_dd' => __('Oops, nothing found!', EVENTAPPI_PLUGIN_NAME),
                'new_ticket_tab_title' => __('Ticket %d', EVENTAPPI_PLUGIN_NAME)
            );

        }
    }
}
