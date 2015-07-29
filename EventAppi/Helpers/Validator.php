<?php
namespace EventAppi\Helpers;
/**
 * Class Validator
 *
 * @package EventAppi
 */
class Validator
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
    
    public function isValidDate($value) {
        if(is_array($value) && count($value) == 1) {
            reset($value);               
            return (strtotime(trim($value[key($value)])) !== false);
        } else {
            return (strtotime(trim($value)) !== false);
        }
    }
}
?>

