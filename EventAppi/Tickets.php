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
        if (! is_email($_POST['recipient']) || empty($_POST['name'])) {
            return false;
        }

        $data = array(
            'recipient_email' => $_POST['recipient'],
            'recipient_name'  => $_POST['name'],
            'login_url'       => get_permalink(Settings::instance()->getPageId('my-account'))
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
                'is_sent' => '1',
                'sent_to' => stripslashes($_POST['recipient'])
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
}
