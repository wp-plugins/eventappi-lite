<?php namespace EventAppi;

use WP_User;
use EventAppi\Helpers\Logger;

/**
 * Class Tickets
 *
 * @package EventAppi
 */
class Tickets
{

    /**
     * @var Tickets|null
     */
    private static $singleton = null;

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return Tickets|null
     */
    public static function instance()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        if (is_null(self::$singleton)) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

    public function ajaxSendTicketHandler()
    {
        // $user = new WP_User( get_current_user_id() );
        if ( ! is_email($_POST['recipient']) || empty($_POST['name'])) {
            return false;
        }

        $data = array(
            'recipient_email' => $_POST['recipient'],
            'recipient_name'  => $_POST['name'],
            'login_url'       => PluginManager::instance()->getPageId('eventappi-my-account')
        );
        $hash = substr($_POST['hash'], 1);

        $result = ApiClient::instance()->sendTicketToThirdParty($hash, $data);
        if ($result['message'] !== 'OK') {
            return false;
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases',
            array(
                'isSent' => '1',
                'sentTo' => stripslashes($_POST['recipient'])
            ),
            array(
                // not always done by purchaser - admin can do too
                // 'user_id'            => $user->ID,
                'purchased_ticket_hash' => $hash
            )
        );

        User::instance()->addNewEventAppiUser($_POST['recipient']);

        return true;
    }

    public function ajaxAssignTicketHandler()
    {
        $user = new WP_User(get_current_user_id());
        $data = array(
            'recipient_email' => $user->data->user_email,
            'recipient_name'  => $_POST['name']
        );
        $hash = substr($_POST['hash'], 1);

        $result = ApiClient::instance()->emailPurchasedTicket($hash, $data);
        if ($result['message'] !== 'OK') {
            return false;
        }

        global $wpdb;
        $extraMethods = User::instance()->getAdditionalContactMethods(true);
        $extraData    = array();
        foreach ($extraMethods as $dataKey) {
            $extraData[$dataKey['id']] = $_POST[$dataKey['id']];
        }
        $extraData = serialize($extraData);
        $wpdb->update(
            $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases',
            array(
                'isAssigned'             => '1',
                'assignedTo'             => stripslashes($_POST['name']),
                'additionalAttendeeData' => $extraData
            ),
            array(
                'purchased_ticket_hash' => $hash
            )
        );

        return true;
    }

    public function ajaxClaimTicketHandler()
    {
        $user = new WP_User(get_current_user_id());
        $data = array(
            'recipient_email' => $user->data->user_email,
            'recipient_name'  => $user->data->display_name
        );
        $hash = substr($_POST['hash'], 1);

        $result = ApiClient::instance()->emailPurchasedTicket($hash, $data);
        if ($result['message'] !== 'OK') {
            return false;
        }

        global $wpdb;
        $extraMethods = User::instance()->getAdditionalContactMethods(true);
        $extraData    = array();
        foreach ($extraMethods as $dataKey) {
            $extraData[$dataKey['id']] = $_POST[$dataKey['id']];
        }
        $extraData  = serialize($extraData);
        $wpdb->update(
            $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases',
            array(
                'isClaimed' => '1',
                'additionalAttendeeData' => $extraData
            ),
            array(
                'purchased_ticket_hash' => $hash
            )
        );

        return true;
    }
}
