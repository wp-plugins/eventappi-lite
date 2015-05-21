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
        check_ajax_referer(EVENTAPPI_PLUGIN_NAME . '_world');

        $session = session_id();

        $data = $_POST;
        global $wpdb;

        // up to this point, in order to avoid rounding errors, we have been dealing with
        // all amounts as integers (i.e. in cents rather than dollars), now we need to switch
        $centsAmount    = $data['amount'];
        $data['amount'] = number_format(intval($data['amount']) / 100, 2, '.', '');

        //get items from cart
        $table_name    = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_cart';
        $ticket_result = $wpdb->get_results(
            "SELECT event_id, ticket_id, post_id, term, ticket_quantity FROM $table_name WHERE `session` = '$session';"
        );

        $tickets = array();

        if (is_array($ticket_result) && ! empty($ticket_result)) {

            foreach ($ticket_result as $key => $value) {
                $tickets[$key]['ticket_id'] = $value->ticket_id;
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
            if (!empty($result)) {
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

        if (isset($data['start_date']) &&
            $data['start_date'] !== '' &&
            strpos($data['start_date'], '/') !== false
        ) {
            $start_date = explode('/', $data['start_date']);
            $startMonth = $start_date[0];
            $startYear  = $start_date[1];
        }

        if (isset($data['expiry_date']) &&
            $data['expiry_date'] !== '' &&
            strpos($data['expiry_date'], '/') !== false
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

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
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

            if ($data['amount'] === '0.00' && $gateway->supportsAuthorize()) {
                $data['amount'] = '1.00';
                $response       = $gateway->authorize($data)->send();
                $data['amount'] = '0.00';

                if ($response->isSuccessful()) {
                    $success = true;
                }
            } elseif ($data['amount'] === '0.00') {
                // zero value, but no authorize function on the gateway!
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

                $table_name = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_cart';
                $sql        = <<<REMOVESQL
DELETE FROM {$table_name}
WHERE session = %s
REMOVESQL;
                $wpdb->query(
                    $wpdb->prepare(
                        $sql,
                        $session
                    )
                );

                $table_name = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases';

                // now, for the API we need to switch back to cents-only currency values
                $data['amount']    = $centsAmount;
                $data['login_url'] = get_permalink(Settings::instance()->getPageId('my-account'));
                $returnTicket      = ApiClient::instance()->storePurchase($data);

                foreach ($ticket_result as $key => $value) {
                    $theTixMeta = get_tax_meta_all($value->term);
                    $numSold    = intval($theTixMeta['eventappi_event_ticket_sold']);
                    if (empty($numSold)) {
                        $numSold = 0;
                    }
                    $numSold = intval($numSold) + intval($value->ticket_quantity);
                    update_tax_meta($value->term, 'eventappi_event_ticket_sold', $numSold);

                    $i = 0;
                    while ($i < $value->ticket_quantity) {
                        $purchId         = $returnTicket['data']['id'];
                        $purchTicketId   = $returnTicket['data']['tickets'][$key]['id'];
                        $purchTicketHash = $returnTicket['data']['tickets'][$key]['hashes'][$i];
                        $time            = time();
                        $sql             = <<<PURCHASESAVESQL
INSERT INTO {$table_name} (`user_id`, `purchase_id`, `purchase_ticket_id`, `purchased_ticket_hash`,
        `event_id`, `ticket_id`, `isClaimed`, `isAssigned`, `isSent`, `timestamp`)
     VALUES (%d, %d, %d, %s, %d, %d, 0, 0, 0, %s)
PURCHASESAVESQL;
                        $wpdb->query(
                            $wpdb->prepare(
                                $sql,
                                $user->ID,
                                $purchId,
                                $purchTicketId,
                                $purchTicketHash,
                                $value->post_id,
                                $value->term,
                                $time
                            )
                        );
                        $i ++;
                    }
                }

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
