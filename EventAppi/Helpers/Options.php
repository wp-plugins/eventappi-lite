<?php namespace EventAppi\Helpers;

/**
 * Class Options
 *
 * @package EventAppi
 */
class Options
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
     * @return Options|null
     */
    public static function instance()
    {
        if (is_null(self::$singleton)) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

    public function init()
    {
        add_action('wp_head', array($this, 'metaGeneratorHeader'));
    }

    /**
     * @return bool
     */
    public function getLogEnabled()
    {
        $logValue = get_option(EVENTAPPI_PLUGIN_NAME . '_full_logging');

        if (Logger::isLogLevelValid($logValue)) {
            return intval($logValue);
        }

        return Logger::LOG_LEVEL_DISABLED; // default to OFF
    }

    /**
     *
     */
    public function metaGeneratorHeader()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);
        print("<meta name='generator' content='EventAppi Version " . EVENTAPPI_PLUGIN_VERSION . "' />");
    }

    /**
     * @param $optionName
     *
     * @return mixed|void
     */
    public function getPluginOption($optionName)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        return get_option(EVENTAPPI_PLUGIN_NAME . '_' . $optionName);
    }

    /**
     * @param $optionName
     * @param $optionValue
     *
     * @return mixed|void
     */
    public function setPluginOption($optionName, $optionValue)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        return update_option(EVENTAPPI_PLUGIN_NAME . '_' . $optionName, $optionValue);
    }
}
