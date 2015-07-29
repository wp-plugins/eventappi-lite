<?php namespace EventAppi;

use EventAppi\Helpers\Logger;
use EventAppi\Helpers\Options;
use Omnipay\Omnipay;

/**
 * Class Payment
 *
 * @package EventAppi
 */
class Payment
{

    /**
     * @var Payment|null
     */
    private static $singleton = null;

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return Payment|null
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

    public function ajaxPayHandler()
    {
        check_ajax_referer(Parser::instance()->nonceAjaxAction);

        $session = session_id();

        // TODO: improve the way the data is taken
        $data = $_POST;
        global $wpdb;

        // up to this point, in order to avoid rounding errors, we have been dealing with
        // all amounts as integers (i.e. in cents rather than dollars), now we need to switch
        $centsAmount    = ($data['amount'] * 100);
        $data['amount'] = number_format($data['amount'], 2, '.', '');

        //get items from cart
        $sql = "SELECT ticket_id, ticket_api_id, ticket_quantity FROM `".PluginManager::instance()->tables['cart']."` WHERE `session` = '$session';";
        $ticketResult = $wpdb->get_results($sql);

        $tickets = array();

        if (is_array($ticketResult) && ! empty($ticketResult)) {
            foreach ($ticketResult as $key => $value) {
                // This data gets sent to the API
                $tickets[$key]['ticket_id'] = $value->ticket_api_id;
                $tickets[$key]['quantity']  = $value->ticket_quantity;
            }
        }

        // load the omnipay selected gateway and that gateway's options
        $options = $wpdb->prefix . 'options';

        $gatewaySelected = Options::instance()->getPluginOption('gateway');
        $gatewaySettings = array();
        if ($gatewaySelected === false) {
            $gatewaySelected = 'Dummy';
        } else {
            $selector = EVENTAPPI_PLUGIN_NAME . "_gateway_{$gatewaySelected}_";
            $sql      = "SELECT option_name, option_value FROM {$options} WHERE option_name LIKE '{$selector}%'";
            $result   = $wpdb->get_results($sql);
            if (! empty($result)) {
                foreach ($result as $key => $value) {
                    $optKey                   = str_replace($selector, '', $value->option_name);
                    $gatewaySettings[$optKey] = $value->option_value;
                }
            }
            $gatewaySelected = $gatewaySettings['fullGatewayName'];
            unset($gatewaySettings['fullGatewayName']);
        }

        $data['items'] = $tickets;
        $startMonth    = '';
        $startYear     = '';
        $expiryMonth   = '';
        $expiryYear    = '';

        if (isset($data['start_date']) && $data['start_date'] !== '' && strpos($data['start_date'],
                '/') !== false
        ) {
            $start_date = explode('/', $data['start_date']);
            $startMonth = $start_date[0];
            $startYear  = $start_date[1];
        }

        if (isset($data['expiry_date']) && $data['expiry_date'] !== '' && strpos($data['expiry_date'],
                '/') !== false
        ) {
            $expiry_date = explode('/', $data['expiry_date']);
            $expiryMonth = $expiry_date[0];
            $expiryYear  = $expiry_date[1];
        }

        if (isset($expiryYear) && strlen($expiryYear) === 2) {
            $expiryYear = substr(date('Y'), 0, 2) . $expiryYear;
        }

        if (isset($startYear) && strlen($startYear) === 2) {
            $startYear = substr(date('Y'), 0, 2) . $startYear;
        }

        if ( ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            exit();
        }

        //get WP user
        $user = get_user_by('email', $data['email']);

        $card = array(
            'firstName'   => $data['firstName'],
            'lastName'    => $data['lastName'],
            'number'      => $data['card'],
            'expiryMonth' => $expiryMonth,
            'expiryYear'  => $expiryYear,
            'cvv'         => $data['cvv'],
            'issueNumber' => $data['issueNumber'],
            'startMonth'  => isset($data['startMonth']) ? $data['startMonth'] : '',
            'startYear'   => isset($data['startYear']) ? $data['startYear'] : '',
        );

        $card['billingAddress1'] = $data['billing_address_1'];
        $card['billingCountry']  = $data['billing_country'];
        $card['billingCity']     = $data['billing_city'];
        $card['billingPostcode'] = $data['billing_postcode'];

        $data['card'] = $card;

        // payment time....
        $gateway = Omnipay::create($gatewaySelected);
        foreach ($gatewaySettings as $key => $value) {
            $method = 'set' . ucfirst($key);
            $gateway->$method($value);
        }

        try {
            $success = false;

            if ($data['amount'] === '0.00') {
                $success = true;
            } else {
                $response = $gateway->purchase($data)->send();

                if ($response->isSuccessful()) {
                    $success = true;
                }
            }

            if ($success === true) {
                //add tickets to purchase table
                global $wpdb;

                $tableName = PluginManager::instance()->tables['cart'];
                $sql        = "DELETE FROM {$tableName} WHERE session = %s";

                $wpdb->query(
                    $wpdb->prepare(
                        $sql,
                        $session
                    )
                );

                $tableName = PluginManager::instance()->tables['purchases'];

                // now, for the API we need to switch back to cents-only currency values
                $data['amount']    = $centsAmount;
                $data['login_url'] = get_permalink(Settings::instance()->getPageId('my-account'));

                $returnTicket      = ApiClient::instance()->storePurchase($data);

                if(isset($returnTicket['error']) && is_array($returnTicket['error'])) {
                    exit(sprintf(__('The purchase could not be made due to the following error: %s - Please empty the cart and add the items again. If the problem persists, consider contacting the administrator.', EVENTAPPI_PLUGIN_NAME), $returnTicket['error']['message']));
                } else {
                    if( ! empty($ticketResult) ) {

                        foreach ($ticketResult as $key => $value) {
                            $ticketId = $value->ticket_id;
                            $eventId = get_post_meta($ticketId, EVENTAPPI_TICKET_POST_NAME . '_event_id', true);

                            $ticketMeta = get_post_meta($ticketId);

                            // Update Tickets Sold
                            $numSold = (int)$ticketMeta[EVENTAPPI_TICKET_POST_NAME . '_no_sold'][0] + intval($value->ticket_quantity);
                            update_post_meta($ticketId, EVENTAPPI_TICKET_POST_NAME . '_no_sold', $numSold);

                            $i = 0;
                            while ($i < $value->ticket_quantity) {
                                $purchId         = $returnTicket['data']['id'];
                                $purchTicketId   = $returnTicket['data']['tickets'][$key]['id'];
                                $purchTicketHash = $returnTicket['data']['tickets'][$key]['hashes'][$i];

                                $time            = time();
                                $sql             = <<<PURCHASESAVESQL
INSERT INTO {$tableName} (`user_id`, `purchase_id`, `purchase_ticket_id`, `purchased_ticket_hash`,
        `event_id`, `ticket_id`, `is_claimed`, `is_assigned`, `is_sent`, `timestamp`)
     VALUES (%d, %d, %d, %s, %s, %d, %d, 0, 0, 0, %s)
PURCHASESAVESQL;

                                $wpdb->query(
                                    $wpdb->prepare(
                                        $sql,
                                        $user->ID,
                                        $purchId,
                                        $purchTicketId,
                                        $purchTicketHash,
                                        $eventId,
                                        $ticketId,
                                        $time
                                    )
                                );
                                $i ++;
                            }
                        }
                    } else {
                        echo __('The purchase could not be made as the cart is empty. Please try adding items back to the cart. If the problem persists, consider contacting the administrator.', EVENTAPPI_PLUGIN_NAME);
                        exit;
                    }

                }

                $_SESSION[EVENTAPPI_PLUGIN_NAME.'_empty_cart'] = true;

                echo __('Thank you, your payment was successful.', EVENTAPPI_PLUGIN_NAME);
                exit();
            }
            $msg = $response->getMessage();
        } catch (Exception $e) {
            $msg = sprintf(__('There was an error communicating with the Omipay Gateway.<br>%s', $e->getMessage()));
        }
        echo __('Sorry, your payment was unsuccessful.', EVENTAPPI_PLUGIN_NAME)."<br><em>{$msg}</em>";
        exit();
    }
}
