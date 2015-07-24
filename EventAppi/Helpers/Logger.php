<?php namespace EventAppi\Helpers;

/**
 * Class Logger
 *
 * @package EventAppi
 */
class Logger
{
    /**
     * Define codes for use in the plugin
     */
    const LOG_LEVEL_DISABLED = 0;
    const LOG_LEVEL_ERROR    = 1;
    const LOG_LEVEL_WARNING  = 2;
    const LOG_LEVEL_INFO     = 3;
    const LOG_LEVEL_DEBUG    = 4;
    const LOG_LEVEL_TRACE    = 5;
    /**
     * @var null
     */
    private static $singleton = null;
    /**
     * @var null|string
     */
    private $file = null;

    /**
     *
     */
    private function __construct()
    {
        $uploads = wp_upload_dir();
        if ( ! is_dir(EVENTAPPI_UPLOAD_DIR) && is_writable($uploads['baseurl'])) {
            mkdir(EVENTAPPI_UPLOAD_DIR);
        }
        if ( ! is_dir(EVENTAPPI_UPLOAD_DIR . 'logs') && is_writable(EVENTAPPI_UPLOAD_DIR)) {
            mkdir(EVENTAPPI_UPLOAD_DIR . 'logs');
        }

        $this->file = EVENTAPPI_UPLOAD_DIR . 'logs/' . EVENTAPPI_PLUGIN_NAME . '_log.txt';

        if (is_writable(EVENTAPPI_UPLOAD_DIR . 'logs') && ! file_exists($this->file)) {
            touch($this->file);
        }
    }

    /**
     * @return Logger|null
     */
    public static function instance()
    {

        if (is_null(self::$singleton)) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

    /**
     * @param $logLevel
     *
     * @return bool
     */
    public static function isLogLevelValid($logLevel)
    {
        return (
            is_numeric($logLevel) &&
            intval($logLevel) >= self::LOG_LEVEL_DISABLED &&
            intval($logLevel) <= self::LOG_LEVEL_TRACE
        );
    }

    /**
     * @param null $logLevel
     *
     * @return mixed
     */
    public function getLogLevelTranslatable($logLevel = null)
    {
        if (!self::isLogLevelValid($logLevel)) {
            $logLevel = Options::instance()->getLogEnabled();
        }

        $logDescriptors = array(
            self::LOG_LEVEL_DISABLED => __('Disabled', EVENTAPPI_PLUGIN_NAME),
            self::LOG_LEVEL_ERROR    => __('Error', EVENTAPPI_PLUGIN_NAME),
            self::LOG_LEVEL_WARNING  => __('Warning', EVENTAPPI_PLUGIN_NAME),
            self::LOG_LEVEL_INFO     => __('Info', EVENTAPPI_PLUGIN_NAME),
            self::LOG_LEVEL_DEBUG    => __('Debug', EVENTAPPI_PLUGIN_NAME),
            self::LOG_LEVEL_TRACE    => __('Trace', EVENTAPPI_PLUGIN_NAME)
        );

        return $logDescriptors[$logLevel];
    }

    /**
     * In order to log activity in this plugin, set the value of eventappi_full_logging to true
     * in the WP database ```update_option('eventappi_full_logging', 1);```
     *
     * @param string $file
     * @param string $function
     * @param string $message
     * @param int    $logLevel
     */
    public function log($file, $function, $message, $logLevel = self::LOG_LEVEL_ERROR)
    {
        $logThreshold = Options::instance()->getLogEnabled();
        
        if ($logThreshold > self::LOG_LEVEL_DISABLED) {
            if (is_writable($this->file)) {
                $logFile = fopen($this->file, 'a') or wp_die( sprintf(__('Cannot open EventAppi log file: %s', EVENTAPPI_PLUGIN_NAME), $this->file) );

                $file = ! empty($file) ? basename($file) : '';

                if ( ! empty($file)) {
                    if ($logLevel <= $logThreshold) {
                        $plugin    = EVENTAPPI_PLUGIN_NAME . ' v' . EVENTAPPI_PLUGIN_VERSION . '';
                        $function  = ! empty($function) ? ' > ' . basename($function) : '';
                        $message   = ! empty($message) ? " {\n\t" . print_r($message, true) . "\n}\n" : '';
                        $logLevelT = $this->getLogLevelTranslatable();

                        fwrite(
                            $logFile,
                            "\n[" . date("Y-m-d H:i:s") . " - {$logLevelT}]  {$plugin} {$file} {$function} {$message}\n"
                        );
                    }
                }
                fclose($logFile);
            } else {
                global $notices;
                $notices['errors'][] = sprintf(
                    __(
                        'Your log file is not writable. Check if your server is able to write to %s.',
                        EVENTAPPI_PLUGIN_NAME
                    ),
                    $this->file
                );
            }
        }
    }

    /**
     *
     */
    public function __clone()
    {
        trigger_error(
            __('Cloning of the EventAppi singleton objects is not permitted.', EVENTAPPI_PLUGIN_NAME),
            E_USER_ERROR
        );
    }
}
