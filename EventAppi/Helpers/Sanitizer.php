<?php namespace EventAppi\Helpers;

/**
 * Class Sanitizer
 *
 * @package EventAppi\Helpers
 */
class Sanitizer
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
     * @return Sanitizer|null
     */
    public static function instance()
    {
        if (is_null(self::$singleton)) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

    /**
     * Santize function helps you sanitize all user input
     *
     * @param  mixed   $data
     * @param  string  $type
     * @param  integer $maxLength
     *
     * @return mixed
     */
    public function sanitize($data, $type = 'string', $maxLength = null)
    {
        if ($type === 'integer' && ! is_numeric($data)) {
            return '';
        }

        if ($type !== 'string') {
            return '';
        }

        $data = sanitize_text_field($data);

        if ($maxLength !== null && strlen($data) > $maxLength) {
            return '';
        }

        return $data;
    }
    
    function arrayMapRecursive(callable $func, array $arr) {
        array_walk_recursive($arr, function(&$v) use ($func) {
            $v = $func($v);
        });
        return $arr;
    }    
}
