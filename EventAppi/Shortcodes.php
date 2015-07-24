<?php namespace EventAppi;

use WP_Query;
use WP_User;
use EventAppi\Helpers\Logger;
use EventAppi\Helpers\Session;
use EventAppi\Helpers\CountryList;
/**
 * Class Shortcodes
 *
 * @package EventAppi
 */
class Shortcodes
{
    /**
     * @var Shortcodes|null
     */
    private static $singleton = null;

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return Shortcodes|null
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
        // Shortcode definitions
        add_shortcode(EVENTAPPI_PLUGIN_NAME, array($this, 'listEvents'));
        add_shortcode(EVENTAPPI_PLUGIN_NAME . '_login', array($this, 'loginPage'));
        add_shortcode(EVENTAPPI_PLUGIN_NAME . '_my_account', array($this, 'myAccountPage'));


        add_shortcode(EVENTAPPI_PLUGIN_NAME . '_cart', array($this, 'cartPage'));
        add_shortcode(EVENTAPPI_PLUGIN_NAME . '_checkout', array($this, 'checkoutPage'));


        add_shortcode(EVENTAPPI_PLUGIN_NAME . '_ticket_reg', array(TicketRegFields::instance(), 'regPage'));
    }

    public function listEvents()
    {
        $args = [
            'post_type'        => EVENTAPPI_POST_NAME,
            'post_status'      => 'publish',
            'is_archive'       => true
        ];

        $listEventsContent = '';

        $eventQuery = new WP_Query($args);
        if ($eventQuery->have_posts()) {
            while ($eventQuery->have_posts()) {
                $eventQuery->the_post();
                $listEventsContent .= Parser::instance()->parseTemplate('event-list');
            }
        }
        wp_reset_query();

        echo $listEventsContent;
    }

    public function loginPage()
    {
        // Redirect to My Account page if the user is already logged in
        if (isset($_GET['failed_login'])) {
            $status = 'error';
            $msg = __('Failed Login.', EVENTAPPI_PLUGIN_NAME);
        } elseif (isset($_GET['active'])) {
            $status = 'success';
            $msg = __('Your account was activated and the event published. Your login details were sent to your email address.', EVENTAPPI_PLUGIN_NAME);
        } elseif (isset($_GET['already_active'])) {
            $status = 'success';
            $msg = __('Your account was already activated. You can login below with the credentials that were sent to your email address.', EVENTAPPI_PLUGIN_NAME);
        }

        $data = array(
            'status' => $status,
            'msg'    => $msg
        );

        return Parser::instance()->parseTemplate('login-frontend', $data);
    }

    public function myAccountPage()
    {
        get_currentuserinfo();

        if (!is_user_logged_in()) {
            return $this->loginPage();
        }

        global $current_user;

        if (isset($_POST[EVENTAPPI_PLUGIN_NAME.'_email'])) {
            global $current_user;
            $current_user = get_userdata($current_user->ID);
        }

        //grab users purchased tickets
        global $wpdb;
        $table_name = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases';
        $sql        = <<<GETPURCHASESSQL
SELECT * FROM {$table_name} WHERE user_id = '{$current_user->ID}'
GETPURCHASESSQL;
        $result     = $wpdb->get_results($sql);

        $eventList = '';


        return $this->myAccount($current_user, $eventList);
    }

    private function buildActionLink($ticketHash, $action, $purchaseDbId = false)
    {
        $linkText  = ucwords($action);
        $linkClass = str_replace(' ', '-', $action);

        // Go to Ticket Registration Page (Assign or Claim)
        if ($purchaseDbId) {
            $regAccess = TicketRegFields::instance()->checkRegAccess($purchaseDbId);
            $regPageUrl = get_permalink(
                Settings::instance()->getPageId('ticket-reg')
            ).'?ea_reg_id='.$purchaseDbId.'&ea_reg_code='.$regAccess['code'].'&ea_status='.$action;

            return "<a href='".$regPageUrl."'>{$linkText}</a>";
        }

        // Send
        return "<a href='javascript:void(0)' data-hash='#{$ticketHash}' class='{$linkClass}'>{$linkText}</a>";
    }

    public function myAccount($user, $eventList)
    {
        global $wpdb;


        $data = array(
            'avatar'                => get_avatar($user->ID, 64),
            'user'                  => $user,
            'extraProfileFields'    => User::instance()->getAdditionalContactMethods(),
            'eventList'             => $eventList,
            'ticketList'            => array()
        );

        if (array_key_exists('attendee', $user->caps) || array_key_exists('manage_'.EVENTAPPI_PLUGIN_NAME, $user->allcaps)) {
            $tblPurchases    = PluginManager::instance()->tables['purchases'];

            $sql           = <<<CLAIMEDSQL
SELECT event_id FROM {$tblPurchases}
WHERE (`user_id`={$user->ID}
OR (`is_sent`=1 AND `sent_to`='{$user->data->user_email}'))
AND is_claimed = '1';
CLAIMEDSQL;
            $claimedEvents = $wpdb->get_col($sql, ARRAY_A);

            $sql       = <<<TICKETSSQL
SELECT pur.*, p.post_title AS ticket_name, p.post_content AS ticket_desc
FROM {$tblPurchases} pur
INNER JOIN {$wpdb->posts} p ON (pur.ticket_id = p.ID)
WHERE pur.user_id = {$user->ID}
OR (pur.is_sent=1 AND pur.sent_to='{$user->data->user_email}')
ORDER BY `timestamp` DESC;
TICKETSSQL;

            $purchases = $wpdb->get_results($sql);

            if (count($purchases) > 0) {
                foreach ($purchases as $purchase) {
                    $eventTitle = get_the_title($purchase->event_id) ?: __('untitled', EVENTAPPI_PLUGIN_NAME);

                    $ticket = array(
                        'ticketName'  => $purchase->ticket_name,
                        'ticketDesc'  => $purchase->ticket_desc,
                        'ticketHash'  => $purchase->purchased_ticket_hash,
                        'status'      => '',
                        'actionLinks' => ''
                    );

                    if ($purchase->is_claimed === '1' && is_null($purchase->assigned_to)) {
                        $ticket['status'] = __('Claimed. This is your ticket.', EVENTAPPI_PLUGIN_NAME);
                    } elseif ($purchase->is_claimed) {
                        $ticket['status'] = sprintf(__('Claimed by %s'), $purchase->assigned_to);
                    } elseif ($purchase->is_assigned === '1') {
                        $ticket['status'] = sprintf(__('This ticket is assigned to %s.'), $purchase->assigned_to);
                    } elseif ($purchase->is_sent === '1' && $purchase->sent_to != $user->data->user_email) {
                        $ticket['status'] = sprintf(__('This ticket has been sent to %s.'), $purchase->sent_to);
                    } else {
                        if ($purchase->is_sent === '1') {
                            $ticket['status'] = __('This ticket has been sent to you.', EVENTAPPI_PLUGIN_NAME).'<br>';
                        }
                        if (is_string($purchase->purchased_ticket_hash) &&
                            strlen($purchase->purchased_ticket_hash) > 2
                        ) {
                            if (! in_array($purchase->event_id, $claimedEvents)) {
                                $ticket['actionLinks'] = $this->buildActionLink(
                                    $purchase->purchased_ticket_hash,
                                    'claim',
                                    $purchase->id
                                ) . '<br>';
                            }
                            $ticket['actionLinks'] .= $this->buildActionLink(
                                $purchase->purchased_ticket_hash,
                                'assign',
                                $purchase->id
                            ) . '<br>';
                            $ticket['actionLinks'] .= $this->buildActionLink(
                                $purchase->purchased_ticket_hash,
                                'send'
                            );
                        } else {
                            $ticket['actionLinks'] .= __('There is an error with this ticket.', EVENTAPPI_PLUGIN_NAME);
                        }
                    }
                    $data['ticketList'][$eventTitle][] = $ticket;
                }
            }
        }

        // empty by default
        $afterDel = false;

        if (isset($_GET['del']) && ($_GET['del'] == 1) && isset($_GET['title'])) {
            $afterDel = htmlspecialchars(stripslashes(urldecode(trim($_GET['title']))));
        }

        $data['after_del'] = $afterDel;

        return Parser::instance()->parseTemplate('my-account', $data);
    }



    public function cartPage()
    {
        $session = session_id();

        if ($session !== null) {
            global $wpdb;
            $table_name = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_cart';

            $sql    = <<<CARTSQL
SELECT ticket_id, ticket_api_id, ticket_name, ticket_quantity, ticket_price
FROM {$table_name}
WHERE `session` = %s
CARTSQL;
            $result = $wpdb->get_results(
                $wpdb->prepare(
                    $sql,
                    $session
                )
            );

            return Parser::instance()->parseTemplate('cart', $result);
        }

        return '';
    }

    public function checkoutPage()
    {
        global $current_user;

        $session  = session_id();
        $userMeta = null;

        if (is_user_logged_in()) {
            $userMeta = array(
                'first_name' => get_user_meta($current_user->ID, EVENTAPPI_PLUGIN_NAME . '_billing_last_name')[0],
                'last_name'  => get_user_meta($current_user->ID, EVENTAPPI_PLUGIN_NAME . '_billing_last_name')[0],
                'address_1'  => get_user_meta($current_user->ID, EVENTAPPI_PLUGIN_NAME . '_billing_address_1')[0],
                'address_2'  => get_user_meta($current_user->ID, EVENTAPPI_PLUGIN_NAME . '_billing_address_2')[0],
                'city'       => get_user_meta($current_user->ID, EVENTAPPI_PLUGIN_NAME . '_billing_city')[0],
                'postcode'   => get_user_meta($current_user->ID, EVENTAPPI_PLUGIN_NAME . '_billing_postcode')[0],
                'country'    => get_user_meta($current_user->ID, EVENTAPPI_PLUGIN_NAME . '_billing_country')[0],
                'phone'      => get_user_meta($current_user->ID, EVENTAPPI_PLUGIN_NAME . '_billing_phone')[0]
            );
        }

        if ($session !== null) {
            global $wpdb;
            $table_name = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_cart';

            $sql       = <<<CARTSQL
SELECT SUM(`ticket_quantity` * `ticket_price`) AS total
FROM {$table_name}
WHERE `session` = %s
CARTSQL;
            $cartTotal = $wpdb->get_var($wpdb->prepare($sql, $session));

            if (!is_null($cartTotal)) {
                $countries = CountryList::instance()->getCountryList();

                return Parser::instance()->parseTemplate(
                    'checkout',
                    [
                        'total'     => $cartTotal,
                        'actionUrl' => get_permalink(get_page_by_path(EVENTAPPI_PLUGIN_NAME . '-thank-you')),
                        'userMeta'  => $userMeta,
                        'countries' => $countries
                    ]
                );
            }
        }

        return null;
    }
}
