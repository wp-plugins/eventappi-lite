<?php namespace EventAppi;

use WP_User;
use EventAppi\Helpers\CountryList;
use EventAppi\Helpers\Logger;
use EventAppi\Helpers\Sanitizer;
use EventAppi\Helpers\Session;

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
    private $sortArrayKey;

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
        // shortcode definitions
        add_shortcode(EVENTAPPI_PLUGIN_NAME . '_login', array($this, 'loginPage'));
        add_shortcode(EVENTAPPI_PLUGIN_NAME . '_my_account', array($this, 'myAccountPage'));
        add_shortcode(EVENTAPPI_PLUGIN_NAME . '_cart', array($this, 'cartPage'));
        add_shortcode(EVENTAPPI_PLUGIN_NAME . '_checkout', array($this, 'checkoutPage'));
    }

    public function loginPage()
    {
        get_currentuserinfo();

        if (is_user_logged_in()) {
            return $this->myAccountPage();
        }

        $data = [];
        if (isset($_GET['failed_login'])) {
            $data['status'] = 'Failed Login.';
        }

        return Parser::instance()->parseTemplate('login-frontend', $data);
    }


    public function myAccountPage()
    {
        get_currentuserinfo();

        if (!is_user_logged_in()) {
            return $this->loginPage();
        }

        global $current_user;

        $update_status = '';

        //grab users purchased tickets
        global $wpdb;
        $table_name = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases';
        $sql        = <<<GETPURCHASESSQL
SELECT * FROM {$table_name} WHERE user_id = '{$current_user->ID}'
GETPURCHASESSQL;
        $result     = $wpdb->get_results($sql);

        $event_list = '';


        if ($_POST) {

            $update_status             = __('Profile Updated.', EVENTAPPI_PLUGIN_NAME);
            $password_change_requested = false;
            $update_array              = array();
            $api_update_array          = array();

            $first_name = Sanitizer::instance()->sanitize($_POST['first_name'], 'string', 255);
            $last_name  = Sanitizer::instance()->sanitize($_POST['last_name'], 'string', 255);
            $email      = Sanitizer::instance()->sanitize($_POST['email'], 'string', 255);
            $pass1      = Sanitizer::instance()->sanitize($_POST['pass1'], 'string', 255);
            $pass2      = Sanitizer::instance()->sanitize($_POST['pass2'], 'string', 255);

            if ($pass1 !== '') {
                if ($pass1 !== $pass2) {
                    $update_status = __(
                        'Sorry, but your New Password and Repeat New Password do not match.',
                        EVENTAPPI_PLUGIN_NAME
                    );

                    return $this->myAccount($current_user, $update_status, $event_list);
                } else {
                    $api_update_array['password'] = $pass1;
                    $password_change_requested    = true;
                }
            }

            $update_array['ID']         = $current_user->ID;
            $update_array['first_name'] = $first_name;
            $update_array['last_name']  = $last_name;
            $update_array['email']      = $email;

            foreach (User::instance()->getAdditionalContactMethods() as $method) {
                if (!empty($_POST[$method['id']])) {
                    $value = Sanitizer::instance()->sanitize($_POST[$method['id']], 'string', 500);

                    $update_array[$method['id']] = $value;
                }
            }

            $user_id = wp_update_user($update_array);
            if (is_wp_error($user_id)) {
                // There was an error, probably that user doesn't exist.
                $update_status = __('Sorry your profile could not be updated.', EVENTAPPI_PLUGIN_NAME);

            } else {

                $apiId = get_user_meta($current_user->ID, EVENTAPPI_PLUGIN_NAME . '_user_id', true);
                if (!empty($apiId)) {
                    $api_update_array['full_name']      = "{$first_name} {$last_name}";
                    $api_update_array['preferred_name'] = $first_name;
                    $api_update_array['email']          = $email;

                    $updateResult = ApiClient::instance()->updateUser($apiId, $api_update_array);
                    if (!array_key_exists('code', $updateResult) ||
                        $updateResult['code'] != ApiClientInterface::RESPONSE_OK
                    ) {
                        // if we can't update the user on the API, rather display an error than
                        // try to continue resetting the password on WordPress
                        $password_change_requested = false;
                        $update_status = __(
                            'There was an error updating your details on the EventAppi API.',
                            EVENTAPPI_PLUGIN_NAME
                        );
                    }
                }

                if ($password_change_requested) {
                    wp_set_password($pass1, $current_user->ID);
                    wp_redirect(get_permalink(Settings::instance()->getPageId('login')));
                }
            }
        }

        return $this->myAccount($current_user, $update_status, $event_list);
    }

    private function buildActionLink($ticketHash, $action)
    {
        $linkText  = ucwords($action);
        $linkClass = str_replace(' ', '-', $action);

        return "<a href='javascript:void(0)' data-hash='#{$ticketHash}' class='{$linkClass}'> {$linkText} </a>";
    }

    public function myAccount($user, $updateStatus, $eventList)
    {
        global $wpdb;


        $data = array(
            'updateStatus'          => $updateStatus,
            'avatar'                => get_avatar($user->ID, 64),
            'user'                  => $user,
            'extraProfileFields'    => User::instance()->getAdditionalContactMethods(),
            'eventList'             => $eventList,
            'ticketList'            => array()
        );

        if (array_key_exists('attendee', $user->caps) ||
            array_key_exists('manage_' . EVENTAPPI_PLUGIN_NAME, $user->allcaps)
        ) {
            $table_name    = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases';
            $sql           = <<<CLAIMEDSQL
SELECT event_id FROM {$table_name}
WHERE (`user_id`={$user->ID}
OR (`isSent`=1 AND `sentTo`='{$user->data->user_email}'))
AND isClaimed = '1';
CLAIMEDSQL;
            $result        = $wpdb->get_results($sql);
            $claimedEvents = array();
            foreach ($result as $event) {
                $claimedEvents[] = $event->event_id;
            }

            $sql       = <<<TICKETSSQL
SELECT * FROM {$table_name}
WHERE `user_id`={$user->ID}
OR (`isSent`=1 AND `sentTo`='{$user->data->user_email}')
ORDER BY `timestamp` DESC;
TICKETSSQL;
            $purchases = $wpdb->get_results($sql);

            if (count($purchases) > 0) {
                foreach ($purchases as $purchase) {
                    $thisTicket = get_term($purchase->ticket_id, 'ticket');
                    $thisEvent  = get_post($purchase->event_id);
                    $ticket     = array(
                        'ticketName'  => $thisTicket->name,
                        'ticketDesc'  => $thisTicket->description,
                        'eventTitle'  => $thisEvent->post_title,
                        'status'      => '',
                        'actionLinks' => ''
                    );

                    if ($purchase->isClaimed === '1' && is_null($purchase->assignedTo)) {
                        $ticket['status'] = __('Claimed. This is your ticket.', EVENTAPPI_PLUGIN_NAME);
                    } elseif ($purchase->isClaimed) {
                        $ticket['status'] = sprintf(__('Claimed by %s'), $purchase->assignedTo);
                    } elseif ($purchase->isAssigned === '1') {
                        $ticket['status'] = sprintf(__('This ticket is assigned to %s.'), $purchase->assignedTo);
                    } elseif ($purchase->isSent === '1' && $purchase->sentTo != $user->data->user_email) {
                        $ticket['status'] = sprintf(__('This ticket has been sent to %s.'), $purchase->sentTo);
                    } else {
                        if ($purchase->isSent === '1') {
                            $ticket['status'] = __('This ticket has been sent to you.', EVENTAPPI_PLUGIN_NAME).'<br>';
                        }
                        if (is_string($purchase->purchased_ticket_hash) &&
                            strlen($purchase->purchased_ticket_hash) > 2
                        ) {
                            if (!in_array($purchase->event_id, $claimedEvents)) {
                                $ticket['actionLinks'] = $this->buildActionLink(
                                    $purchase->purchased_ticket_hash,
                                    'claim'
                                ) . '<br>';
                            }
                            $ticket['actionLinks'] .= $this->buildActionLink(
                                $purchase->purchased_ticket_hash,
                                'assign'
                            ) . '<br>';
                            $ticket['actionLinks'] .= $this->buildActionLink(
                                $purchase->purchased_ticket_hash,
                                'send'
                            );
                        } else {
                            $ticket['actionLinks'] .= __('There is an error with this ticket.', EVENTAPPI_PLUGIN_NAME);
                        }
                    }
                    $data['ticketList'][] = $ticket;
                }
            }
        }

        return Parser::instance()->parseTemplate('my-account', $data);
    }


    /**
     * @param  int $pageSize
     *
     * @return int
     */
    protected function getEventPageCount($pageSize)
    {
        $events      = $this->loadDatabaseEvents();
        $totalEvents = count($events);
        $totalPages  = ceil($totalEvents / $pageSize);

        return $totalPages;
    }

    /**
     * @param  int|null $pageSize - If supplied, limit results by page size
     * @param  int|null $pageNumber - If supplied, limit results by page number (starting at page 1)
     *
     * @return array
     */
    protected function loadDatabaseEvents($pageSize = null, $pageNumber = null)
    {
        global $current_user;

        if (!isset($current_user->ID)) {
            return;
        }

        if (isset($current_user->roles) && is_array($current_user->roles)) {
            if (!in_array('event_organiser', $current_user->roles) &&
                !in_array('administrator', $current_user->roles)
            ) {
                return;
            }
        } else {
            return;
        }

        $offset = 0; // Default (Page 1 or All)

        if ($pageSize && $pageNumber > 1) {
            $offset = (int)(($pageNumber - 1) * $pageSize);
        }

        if (!$pageSize) {
            $pageSize = -1; // Get all if no number is set
        }

        // See if the request is being made for upcoming events or the past ones & append the arguments accordingly

        // Upcoming or Past Events?
        $type = ($_GET['type']);

        $args = array(
            'post_type'      => EVENTAPPI_POST_NAME,
            'posts_per_page' => $pageSize,
            'offset'         => $offset,
            'orderby'        => 'meta_value_num',
            'order'          => ($type == 'upcoming') ? 'ASC' : 'DESC',
            'post_status'    => 'publish'
        );

        // Upcoming = Today and in the future
        // Past = Yesterday and in the past

        $args['meta_query'] = array(
            array(
                'key'   => EVENTAPPI_POST_NAME . '_start_date',
                'value' => mktime(0, 0, 0, date('m'), date('d'), date('Y')), // Today Unix Timestamp
                'compare' => ($type == 'upcoming') ? '>=' : '<'
            )
        );

        return get_posts($args);
    }


    public function cartPage()
    {
        $session = session_id();

        if ($session !== null) {
            global $wpdb;
            $table_name = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_cart';

            $sql    = <<<CARTSQL
SELECT event_id, ticket_id, post_id, term, ticket_quantity, ticket_price, ticket_name
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
