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
    }

    public function ajaxAddToCartHandler()
    {
        //check token if fails it dies.
        check_ajax_referer(EVENTAPPI_PLUGIN_NAME . '_world');
        $data = $_POST;

        header('Content-Type: text/plain');
        global $wpdb;
        $table_name = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_cart';
        $items      = count($data['id']);

        $session = session_id();

        for ($i = 0; $i < $items; $i ++) {

            $id       = intval($data['id'][$i]); // API Ticket ID
            $event    = intval($data['event'][$i]); // API Event ID
            $post     = intval($data['post_id'][$i]); // the WP post ID
            $quantity = intval($data['quantity'][$i]);
            $price    = intval($data['price'][$i]); // Price for one ticket
            $term     = intval($data['term'][$i]); // the meta term ID
            $name     = stripslashes($data['name'][$i]);

            if ($quantity === 0) {
                continue;
            }

            if (strlen($id) > 10 || $id === 0) {
                exit();
            }

            if (strlen($id) > 10 || $event === 0) {
                exit();
            }

            if (strlen($quantity) > 10) {
                exit();
            }

            if (strlen($price) > 10) {
                exit();
            }

            if (strlen($name) > 255) {
                exit();
            }

            //delete all outdated(longer than 10 minutes) items from cart
            $sql = <<<DELETESQL
DELETE FROM {$table_name}
WHERE timestamp < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 10 MINUTE))
DELETESQL;
            $wpdb->query($sql);

            $sql = <<<INSERTSQL
INSERT INTO {$table_name}
    (`session`, `event_id`, `post_id`, `term`, `ticket_id`, `ticket_name`, `ticket_quantity`, `ticket_price`, `timestamp`)
VALUES
    (%s, %d, %d, %d, %d, %s, %d, %d, UNIX_TIMESTAMP(NOW()) )
ON DUPLICATE KEY UPDATE
    ticket_quantity = VALUES(ticket_quantity),
    ticket_price = VALUES(ticket_price),
    timestamp = VALUES(timestamp)
INSERTSQL;
            $wpdb->query(
                $wpdb->prepare(
                    $sql,
                    $session,
                    $event,
                    $post,
                    $term,
                    $id,
                    $name,
                    $quantity,
                    $price
                )
            );

            //update all tickets timestamp for that user
            $sql = <<<TIMESTAMPSQL
UPDATE {$table_name}
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

        echo '1';
        exit();
    }

    public function ajaxRemoveFromCartHandler()
    {
        //check token if fails it dies.
        check_ajax_referer(EVENTAPPI_PLUGIN_NAME . '_world');

        $session = session_id();

        header('Content-Type: text/plain');
        global $wpdb;
        $table_name = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_cart';
        $data       = $_POST;

        if (isset($data['id'])) {

            $ticket_id = intval($data['id']);

            if (strlen($ticket_id) > 10) {
                exit();
            }

            $sql = <<<REMOVESQL
DELETE FROM {$table_name}
WHERE session = %s
AND ticket_id = %d
REMOVESQL;
            $wpdb->query(
                $wpdb->prepare(
                    $sql,
                    $session,
                    $ticket_id
                )
            );

            echo '1';
            exit();
        }
        exit();
    }
}
