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
        //token key
        $title_nonce = wp_create_nonce(EVENTAPPI_PLUGIN_NAME . '_world');

        //gives .js files a eventappi_ajax_obj that contains two parameters, ajax_url and nonce(csrf token)
        wp_localize_script(EVENTAPPI_PLUGIN_NAME . '-admin', EVENTAPPI_PLUGIN_NAME . '_ajax_obj', array(
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => $title_nonce,
            'plugin_name' => EVENTAPPI_PLUGIN_NAME,
            'home_url'    => home_url(),
            'date_format' => Format::instance()->formatDate(get_option('date_format')),
            'time_format' => Format::instance()->formatDate(get_option('time_format')),
            'post'        => isset($_GET['post']) ? $_GET['post'] : ''
        ));
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

        // gives .js files a eventappi_ajax_obj that contains useful data for use in JS
        wp_localize_script(EVENTAPPI_PLUGIN_NAME . '-frontend', EVENTAPPI_PLUGIN_NAME . '_ajax_obj', array(
            'ajax_url'                      => admin_url('admin-ajax.php'),
            EVENTAPPI_PLUGIN_NAME . '_cart' => get_permalink(get_page_by_path(EVENTAPPI_PLUGIN_NAME . '-cart')),
            'plugin_name'                   => EVENTAPPI_PLUGIN_NAME,
            'nonce'                         => $title_nonce,
            'user_id'                       => get_current_user_id(),
            'email'                         => $email,
            'date_format'                   => Format::instance()->formatDate(get_option('date_format')),
            'time_format'                   => Format::instance()->formatDate(get_option('time_format')),
            'post'                          => isset($_GET['post']) ? $_GET['post'] : '',
            'event_id'                      => $event_id,
            'my_account'                    => get_permalink(get_page_by_path(EVENTAPPI_PLUGIN_NAME . '-my-account')),
        ));

        wp_localize_script('highslide.config', EVENTAPPI_PLUGIN_NAME . '_ajax_obj', array(
            'assets_url' => EVENTAPPI_PLUGIN_ASSETS_URL
        ));
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
            EVENTAPPI_PLUGIN_NAME . '-datatable',
            '//cdn.datatables.net/1.10.5/css/jquery.dataTables.min.css'
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
        $pathAndPrefix = EVENTAPPI_PLUGIN_ASSETS_URL . 'js/' . EVENTAPPI_PLUGIN_NAME;

        // register our scripts
        wp_register_script(EVENTAPPI_PLUGIN_NAME . '-frontend-reports', $pathAndPrefix . '-frontend-reports.js');

        // enqueue scripts
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core', null, array('jquery'));
        wp_enqueue_script('jquery-ui-tabs', null, array('jquery'));
        wp_enqueue_script('jquery-ui-dialog', null, array('jquery'));
        wp_enqueue_script('jquery-ui-datepicker', null, array('jquery'));
        wp_enqueue_script('jquery-ui-autocomplete', null, array('jquery'));

        wp_enqueue_script('highcharts', '//code.highcharts.com/highcharts.js', null, array('jquery'));
        wp_enqueue_script('highcharts-3d', '//code.highcharts.com/highcharts-3d.js', null, array('jquery'));
        wp_enqueue_script('highcharts-data', '//code.highcharts.com/modules/data.js', null, array('jquery'));
        wp_enqueue_script('highcharts-exporting', '//code.highcharts.com/modules/exporting.js', null, array('jquery'));

        wp_enqueue_script(
            EVENTAPPI_PLUGIN_NAME . '-datatables',
            '//cdn.datatables.net/1.10.5/js/jquery.dataTables.min.js',
            null,
            array('jquery')
        );

        wp_enqueue_script(
            EVENTAPPI_PLUGIN_NAME . '-frontend-reports',
            $pathAndPrefix . '-frontend-reports.js',
            array('jquery'),
            EVENTAPPI_PLUGIN_VERSION
        );

        wp_enqueue_script(
            EVENTAPPI_PLUGIN_NAME . '-timepicker',
            '//cdnjs.cloudflare.com/ajax/libs/jquery-timepicker/1.6.8/jquery.timepicker.min.js',
            null,
            array('jquery')
        );
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
}
