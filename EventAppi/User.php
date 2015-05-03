<?php namespace EventAppi;

use EventAppi\Helpers\Sanitizer;

/**
 * Class User
 *
 * @package EventAppi
 */
class User
{
    const USER_TYPE_ENTERPRISE = 1;
    const USER_TYPE_MANAGER    = 2;
    const USER_TYPE_ORGANISER  = 3;
    const USER_TYPE_ATTENDEE   = 4;

    /**
     * @var User|null
     */
    private static $singleton = null;

    /**
     * @var array
     */
    public $passwordStore = array();

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return User|null
     */
    public static function instance()
    {
        if (is_null(self::$singleton)) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

    /**
     * @param $email
     * @param $password
     */
    public function addToPasswordStore($email, $password)
    {
        $this->passwordStore[$email] = $password;
    }

    public function init()
    {
        add_action('edit_user_profile_update', array($this, 'checkForUserProfileUpdate'));
        add_action('user_register', array($this, 'registrationSave'), 10, 1);

        add_filter('user_contactmethods', array($this, 'addContactMethods'));

        if (is_admin()) {
            add_action('admin_notices', array($this, 'showAndRemoveAdminNotice'));
        }
    }

    public function registrationSave($user_id)
    {
        /**
         * This callback function can be called in two cases:
         * 1. WordPress adds a user via the dashboard
         * 2. We register an attendee when they purchase tickets
         *
         * In the first case, the 'email' and 'pass1' POST vars
         * will be present because WP uses those to create the user,
         * but in the other case they do not because we are generating
         * the password. Intercept the password in the first case so
         * that we can add the user to the API.
         **/

        if (array_key_exists('email', $_POST) &&
            is_string($_POST['email']) &&
            array_key_exists('pass1', $_POST) &&
            is_string($_POST['pass1'])
        ) {
            $this->addToPasswordStore($_POST['email'], $_POST['pass1']);
        }

        $user = get_user_by('id', $user_id);

        if ($user !== false) {
            $this->addUserToApi($user);
        }
    }

    private function getApiUserType($userId)
    {
        $userData = get_user_by('id', $userId);

        return (in_array('event_organiser', $userData->roles) || in_array('administrator', $userData->roles))
            ? self::USER_TYPE_ORGANISER
            : self::USER_TYPE_ATTENDEE;
    }

    public function checkForUserProfileUpdate($userId)
    {
        $first_name = Sanitizer::instance()->sanitize($_POST['first_name'], 'string', 255);
        $last_name  = Sanitizer::instance()->sanitize($_POST['last_name'], 'string', 255);
        $email      = Sanitizer::instance()->sanitize($_POST['email'], 'string', 255);
        $pass1      = Sanitizer::instance()->sanitize($_POST['pass1'], 'string', 255);
        $pass2      = Sanitizer::instance()->sanitize($_POST['pass2'], 'string', 255);

        $api_update_array = array();

        if ($pass1 !== '' && $pass1 === $pass2) {
            $api_update_array['password'] = $pass1;
        }

        $api_update_array['type_id']        = $this->getApiUserType($userId);
        $api_update_array['full_name']      = "{$first_name} {$last_name}";
        $api_update_array['preferred_name'] = $first_name;
        $api_update_array['email']          = $email;

        $apiId = get_user_meta($userId, EVENTAPPI_PLUGIN_NAME . '_user_id', true);
        if ( ! empty($apiId)) {
            ApiClient::instance()->updateUser($apiId, $api_update_array);
        }
    }

    public function ajaxUserCreateHandler()
    {
        $this->addNewEventAppiUser($_POST['email']);
    }

    public function addNewEventAppiUser($email)
    {
        $skip_wp = false;
        $wpUser  = null;
        $result  = 0;

        // check if email is set
        if ( ! isset($email)) {
            echo $result;
            exit();
        }

        // validate email
        if ( ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo $result;
            exit();
        }

        // query user by email
        $user = ApiClient::instance()->showUser($email);

        // new User or existing, logged out user
        if (email_exists($email)) {
            $skip_wp = true;
        }

        // new User!
        if (isset($user['error'])) {

            if ( ! $skip_wp) {
                $password = wp_generate_password();

                $this->addToPasswordStore($email, $password);

                $userdata = array(
                    'user_login'           => $email,
                    'user_pass'            => $password,
                    'user_email'           => $email,
                    'first_name'           => $email,
                    'nickname'             => $email,
                    'show_admin_bar_front' => 'false',
                    'role'                 => 'attendee',
                );

                $wpUser = wp_insert_user($userdata);

            } else {
                $wpUser = get_user_by('email', $email);
                $this->addUserToApi($wpUser);
            }

            if (is_wp_error($wpUser)) {
                if ( ! array_key_exists('existing_user_email', $wpUser->errors)) {
                    echo $wpUser->get_error_message();
                    exit();
                }
            }

            $user = ApiClient::instance()->showUser($email);
        }

        if (isset($user['data']['id'])) {
            $oldUser = array(
                'user_id' => $user['data']['id'],
                'new'     => 0
            );
            echo json_encode($oldUser);
            exit();
        }

        echo 'error';
        exit();
    }

    public function addUserToApi($userData, $failOnNoPassword = true)
    {
        if (array_key_exists($userData->data->user_email, $this->passwordStore)) {

            $newPass = $this->passwordStore[$userData->data->user_email];
            wp_new_user_notification($userData->data->ID, $newPass);

        } else {

            if ($failOnNoPassword) {
                wp_die('Unable to set the user password.');
            }

            $newPass = wp_generate_password(); // temp password ?
        }

        $newData = array(
            'type_id'           => $this->getApiUserType($userData->data->ID),
            'full_name'         => $userData->data->display_name,
            'preferred_name'    => $userData->data->display_name,
            'email'             => $userData->data->user_email,
            'password'          => $newPass,
            'confirmation_link' => PluginManager::instance()->getPageId('eventappi-my-account')
        );

        // check whether the user exists
        $response = ApiClient::instance()->showUser($userData->data->user_email);
        if ( ! array_key_exists('data', $response)) {
            $response = ApiClient::instance()->storeUser($newData);

            if ( ! isset($response['data']['id'])) {
                return 0;
            }
        }

        $api_user_id = $response['data']['id'];
        update_user_meta($userData->ID, 'eventappi_user_id', $api_user_id);

        return $api_user_id;
    }

    public function getAdditionalContactMethods($filter = false)
    {
        $theFields = array(
            array(
                'id'               => EVENTAPPI_PLUGIN_NAME . '_home_address',
                'name'             => __('Home Address', EVENTAPPI_PLUGIN_NAME),
                'requireForTicket' => true
            ),
            array(
                'id'               => EVENTAPPI_PLUGIN_NAME . '_mobile_phone',
                'name'             => __('Phone number', EVENTAPPI_PLUGIN_NAME),
                'requireForTicket' => true
            ),
            array(
                'id'               => EVENTAPPI_PLUGIN_NAME . '_billing_address_1',
                'name'             => __('Billing Address 1', EVENTAPPI_PLUGIN_NAME),
                'requireForTicket' => false,
                'group'            => __('Billing Info', EVENTAPPI_PLUGIN_NAME)
            ),
            array(
                'id'               => EVENTAPPI_PLUGIN_NAME . '_billing_address_2',
                'name'             => __('Billing Address 2', EVENTAPPI_PLUGIN_NAME),
                'requireForTicket' => false,
                'group'            => __('Billing Info', EVENTAPPI_PLUGIN_NAME)
            ),
            array(
                'id'               => EVENTAPPI_PLUGIN_NAME . '_billing_city',
                'name'             => __('Billing City', EVENTAPPI_PLUGIN_NAME),
                'requireForTicket' => false,
                'group'            => __('Billing Info', EVENTAPPI_PLUGIN_NAME)
            ),
            array(
                'id'               => EVENTAPPI_PLUGIN_NAME . '_billing_postcode',
                'name'             => __('Billing Zip / Postal code', EVENTAPPI_PLUGIN_NAME),
                'requireForTicket' => false,
                'group'            => __('Billing Info', EVENTAPPI_PLUGIN_NAME)
            ),
            array(
                'id'               => EVENTAPPI_PLUGIN_NAME . '_billing_country',
                'name'             => __('Billing Country Code', EVENTAPPI_PLUGIN_NAME),
                'requireForTicket' => false,
                'group'            => __('Billing Info', EVENTAPPI_PLUGIN_NAME)
            ),
            array(
                'id'               => EVENTAPPI_PLUGIN_NAME . '_billing_phone',
                'name'             => __('Billing Telephone', EVENTAPPI_PLUGIN_NAME),
                'requireForTicket' => false,
                'group'            => __('Billing Info', EVENTAPPI_PLUGIN_NAME)
            )
        );

        if ($filter !== false) {
            $filtered = array();
            foreach ($theFields as $field) {
                if ($field['requireForTicket'] === true) {
                    $filtered[] = $field;
                }
            }
            $theFields = $filtered;
        }

        return $theFields;
    }

    private function getUserNotices()
    {
        $notices = get_user_meta(get_current_user_id(), EVENTAPPI_PLUGIN_NAME . '_user_notices', true);

        if ($notices !== false && is_array($notices)) {
            return $notices;
        }

        return false;
    }

    public function addUserNotice(array $notice)
    {
        if (is_array($notice) && array_key_exists('class', $notice) && array_key_exists('message', $notice)) {
            $notice = ['class' => $notice['class'], 'message' => $notice['message']];

            $notices = $this->getUserNotices();
            if (!is_array($notices)) {
                $notices = array();
            }

            $notices[] = $notice;

            update_user_meta(get_current_user_id(), EVENTAPPI_PLUGIN_NAME . '_user_notices', $notices);
        }
    }

    public function showAndRemoveAdminNotice()
    {
        $notices = $this->getUserNotices();

        if (is_array($notices)) {
            foreach ($notices as $notice) {
                if (in_array($notice['class'], ['updated', 'error', 'update-nag'])) {
                    $outputMessage = $notice['message'];

                    echo "<div class='{$notice['class']}'><p>{$outputMessage}</p></div>";
                }
            }
        }

        update_user_meta(get_current_user_id(), EVENTAPPI_PLUGIN_NAME . '_user_notices', array());
    }

    public function addContactMethods($contactMethods)
    {
        $methods = $this->getAdditionalContactMethods();

        foreach ($methods as $method) {
            $contactMethods[$method['id']] = $method['name'];
        }

        return $contactMethods;
    }
}
