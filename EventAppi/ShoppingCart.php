<?php namespace EventAppi;

use EventAppi\Helpers\Logger;

/**
 * Class ShoppingCart
 *
 * @package EventAppi
 */
class ShoppingCart
{

    /**
     * @var ShoppingCart|null
     */
    private static $singleton = null;

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return ShoppingCart|null
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
        add_action('init', array($this, 'addToCart'));
    }
    
    public function addToCart()
    {
        // TODO: TOKEN TO BE ADDED HERE
        
        if (! isset($_POST['ticket_api_id'])) {
            return;
        }
                
        $data = $_POST;
                
        global $wpdb;
        
        $tableName = PluginManager::instance()->tables['cart'];
        $items     = count($data['ticket_api_id']);
        
        $session = session_id();
                
        for ($i = 0; $i < $items; $i++) {
            $quantity    = intval($data['quantity'][$i]);
            $ticketApiId = intval($data['ticket_api_id'][$i]); // API Ticket ID
            $ticketId    = intval($data['ticket_id'][$i]); // Ticket Post ID
            $ticketName  = stripslashes($data['ticket_name'][$i]);

            $isPurchasable = TicketPostType::instance()->isPurchasable(
                array('id' => $ticketId, 'qty' => $quantity, 'api_id' => $ticketApiId)
            );

            if (! $isPurchasable) {
                continue;
            }
            
            $price = get_post_meta($ticketId, EVENTAPPI_TICKET_POST_NAME.'_price', true); // Price for one ticket
                        
            if ((strlen($ticketApiId) > 10 || $ticketApiId === 0)
               || (strlen($quantity) > 10 || strlen($price) > 10 || strlen($ticketName) > 255) ) {
                exit();
            }
            
            $sql = <<<INSERTSQL
INSERT INTO {$tableName}
    (`session`, `ticket_id`, `ticket_api_id`, `ticket_name`, `ticket_quantity`, `ticket_price`, `timestamp`)
VALUES
    (%s, %d, %d, %s, %d, %d, UNIX_TIMESTAMP(NOW()) )
ON DUPLICATE KEY UPDATE
    ticket_quantity = VALUES(ticket_quantity),
    ticket_price = VALUES(ticket_price),
    timestamp = VALUES(timestamp)
INSERTSQL;

            $wpdb->query(
                $wpdb->prepare(
                    $sql,
                    $session,
                    $ticketId,
                    $ticketApiId,
                    $ticketName,
                    $quantity,
                    $price
                )
            );

            //update all tickets timestamp for that user
            $sql = <<<TIMESTAMPSQL
UPDATE {$tableName}
SET timestamp = UNIX_TIMESTAMP(NOW())
WHERE `session` = %s
TIMESTAMPSQL;
            $wpdb->query(
                $wpdb->prepare(
                    $sql,
                    $session
                )
            );
        }

        $_SESSION[EVENTAPPI_PLUGIN_NAME.'_empty_cart'] = false;
        
        // Redirect the user/guest to the Cart Page
        if (! Settings::instance()->isPage('cart')) {
            wp_redirect(get_permalink(Settings::instance()->getPageId('cart')));
            exit;
        }
    }

    public function ajaxRemoveFromCartHandler()
    {
        //check token if fails it dies.
        check_ajax_referer(EVENTAPPI_PLUGIN_NAME . '_ajax_mode');

        $session = session_id();

        header('Content-Type: text/plain');
        
        global $wpdb;
        
        $tableName = PluginManager::instance()->tables['cart'];
        $data      = $_POST;

        if (isset($data['id'])) {
            $ticketId = intval($data['id']);

            if (strlen($ticketId) > 10) {
                exit();
            }

            $sql = <<<REMOVESQL
DELETE FROM {$tableName}
WHERE session = %s
AND ticket_id = %d
REMOVESQL;
            $wpdb->query(
                $wpdb->prepare(
                    $sql,
                    $session,
                    $ticketId
                )
            );
            
            // See if the cart is empty
            $countItemsSql = <<<COUNTSQL
SELECT COUNT(*) FROM `'.$tableName.'` WHERE session = %s and ticket_id = %d
COUNTSQL;
            
            $totalCartItems = $wpdb->get_var(
                $wpdb->prepare(
                    $countItemsSql,
                    $session,
                    $ticketId
                )
            );
            
            // The cart is empty
            if ($totalCartItems < 1) {
                $_SESSION[EVENTAPPI_PLUGIN_NAME.'_empty_cart'] = true;
            }
            
            echo '1';
            exit();
        }
        exit();
    }
}
