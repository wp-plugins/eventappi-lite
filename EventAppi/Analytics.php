<?php namespace EventAppi;

use EventAppi\Helpers\Logger;

/**
 * Class Analytics
 *
 * @package EventAppi
 */
class Analytics
{

    /**
     * @var null
     */
    private static $singleton = null;

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return Analytics|null
     */
    public static function instance()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        if (is_null(self::$singleton)) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

    public function init()
    {
    }

    public function analyticsPage()
    {
        if (! is_user_logged_in()) {
            return Shortcodes::instance()->loginPage();
        }

        if (! current_user_can('manage_' . EVENTAPPI_PLUGIN_NAME)) {
            wp_die(__('You do not have sufficient permissions to view this page.', EVENTAPPI_PLUGIN_NAME));
        }

        $theOutput = '<div id="eventappi-wrapper" class="wrap"><div id="ea-reports"></div></div>';

        return Parser::instance()->parseOutput($theOutput);
    }

    /**
     * Handler for "eventappi_frontend_stats_ticket_sales" Ajax action
     */
    public function ajaxApiStatsTicketSalesHandler()
    {
        check_ajax_referer(Parser::instance()->nonceAjaxAction);

        $dateStart = $_GET['date_start'];
        $dateEnd   = $_GET['date_end'];
        $pattern   = '/^[12]\d{3}-[01]\d-[0123]\d/';   // YYYY-MM-DD

        if (preg_match($pattern, $dateStart) === 1 && preg_match($pattern, $dateEnd) === 1) {
            $response = ApiClient::instance()->showEventTicketSales($dateStart, $dateEnd);
            // TODO: Filter for 'stale' API events?
            $output   = $response['data'];
        } else {
            header(' ', true, 400);  // HTTP status code (Bad Request)
            $output = ['dates' => [], 'events' => []];  // Empty data
        }

        echo json_encode($output);
        exit();
    }

    public function ajaxApiTicketStatsHandler()
    {
        global $wpdb;

        check_ajax_referer(Parser::instance()->nonceAjaxAction);

        $result = ApiClient::instance()->showEventStats();

        $tickets_per_event = $result['data']['tickets_per_event'];

        // filter out stale API events
        $filtered_tickets_per_event = array();
        $metaTable                  = $wpdb->prefix . 'postmeta';
        $sql                        = <<<FINDWPEVENTID
SELECT post_id
FROM {$metaTable}
WHERE meta_key = 'eventappi_event_id'
AND meta_value = %d
FINDWPEVENTID;

        foreach ($tickets_per_event as $event) {
            $wpEventId = $wpdb->get_var(
                $wpdb->prepare(
                    $sql,
                    $event['id']
                )
            );

            if (is_numeric($wpEventId)) {
                $filtered_tickets_per_event[] = $event;
            }
        }

        echo json_encode($filtered_tickets_per_event);
        exit();
    }
}
