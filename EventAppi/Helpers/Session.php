<?php
namespace EventAppi\Helpers;

use EventAppi\Settings;

/**
 * Class Session
 *
 * @package EventAppi
 */
class Session
{

    /**
     * @var Session|null
     */
    private static $singleton = null;

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return Session|null
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
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        add_action('init', array($this, 'initSession'), 1);
        add_action('wp_logout', array($this, 'endSessionAndRedirect'));
        add_action('wp_login', array($this, 'endSession'));

        add_filter('login_redirect', array($this, 'redirectToProfile'), 10, 3);
    }

    /**
     * @param bool $admin
     */
    public function initSession($admin = false)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        if (! isset($_SESSION)) {
            session_start();
        }
    }

    /**
     *
     */
    public function endSession()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        session_destroy();
    }

    /**
     *
     */
    public function endSessionAndRedirect()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $this->endSession();

        wp_redirect(get_permalink(Settings::instance()->getPageId('my-account')));
        exit(); // prevent WordPress from dragging us back to the WP login box.
    }

    /**
     * Redirect user after successful login.
     *
     * @param string $redirectTo URL to redirect to.
     * @param string $request    URL the user is coming from.
     * @param object $user       Logged user's data.
     *
     * @return string
     */
    public function redirectToProfile($redirectTo, $request, $user)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        //is there a user to check?
        global $user;

        // If login was successful
        if (isset($user->roles) && is_array($user->roles)) {

            // If user is an administrator
            if (in_array('administrator', $user->roles)) {
                if (isset($_SERVER['HTTP_REFERER'])) {
                    if (strpos($_SERVER['HTTP_REFERER'], '/wp-login.php') !== false
                    ) {  // If user logged in via the backend
                        return admin_url();
                    }
                }
            }

            return get_permalink(Settings::instance()->getPageId('my-account'));

        } else {
            //if credentials are incorrect and user comes from Frontend Login form redirect him back to frontend login form
            if (isset($_SERVER['HTTP_REFERER'])) {
                if (strpos($_SERVER['HTTP_REFERER'], EVENTAPPI_PLUGIN_NAME . '-login') !== false) {
                    wp_redirect(get_permalink(Settings::instance()->getPageId('login')) . '?failed_login=1');
                    exit();
                }
            }

            return $redirectTo;
        }
    }

    /**
     * Load a param from the session or the query string
     *
     * @param  string $paramName
     *
     * @return int|null
     */
    public function sessionOrQsParam($paramName)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        // Try the query string first
        if (isset($_GET[$paramName])) {
            $value = intval($_GET[$paramName]);
        } else {
            $value = '';
        }

        // Otherwise, use the session
        if ( ! is_int($value)) {
            $value = $_SESSION[$paramName];
        }

        return $value;
    }

    /**
     * Store a param in the session
     *
     * @param string $paramName
     * @param int    $paramValue
     */
    public function sessionParam($paramName, $paramValue)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $_SESSION[$paramName] = $paramValue;
    }
}
