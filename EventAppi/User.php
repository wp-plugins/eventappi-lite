<?php
namespace EventAppi;

use EventAppi\Helpers\Sanitizer;

/**
 * Class User
 *
 * @package EventAppi
 */
class User
{
    /**
     *
     */
    const USER_TYPE_ENTERPRISE = 1;
    /**
     *
     */
    const USER_TYPE_MANAGER    = 2;
    /**
     *
     */
    const USER_TYPE_ORGANISER  = 3;
    /**
     *
     */
    const USER_TYPE_ATTENDEE   = 4;

    /**
     * @var User|null
     */
    private static $singleton = null;
    /**
     * @var
     */
    private $updateStatus;
    /**
     * @var string
     */
    private $nonceEditAction;

    /**
     * @var array
     */
    public $passwordStore = array();
    /**
     * @var bool
     */
    public $alreadyCreated = false;
    /**
     *
     */
    private function __construct()
    {
        $this->nonceEditAction = EVENTAPPI_PLUGIN_NAME . '_edit_user';
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

    /**
     *
     */
    public function init()
    {
        add_action('init', array($this, 'pageRedirect'));



        // Update API when User Profile from the Dashboard is updated
        // either own account or someone else's if an administrator performs the update
        add_action('personal_options_update', array($this, 'checkForUserProfileUpdate'));
        add_action('edit_user_profile_update', array($this, 'checkForUserProfileUpdate'));

        add_action('user_register', array($this, 'registrationSave'), 10, 1);

        add_filter('user_contactmethods', array($this, 'addContactMethods'));

        if (is_admin()) {
            add_action('admin_notices', array($this, 'showAndRemoveAdminNotice'));

        }
    }


    /**
     * @param $user_id
     */
    public function registrationSave($user_id)
    {
        /**
         * This callback function can be called in the following cases:
         * 1. WordPress adds a user via the dashboard
         * 2. We register an attendee when they purchase tickets
         * 3. User confirmed registration via email URL
         *
         * In the first case, the 'email' and 'pass1' POST vars
         * will be present because WP uses those to create the user,
         * but in the other case they do not because we are generating
         * the password. Intercept the password in the first case so
         * that we can add the user to the API.
         **/

        if (array_key_exists('email', $_POST)
            && is_string($_POST['email'])
            && array_key_exists('pass1', $_POST)
            && is_string($_POST['pass1'])
        ) {
            $this->addToPasswordStore($_POST['email'], $_POST['pass1']);
        }

        $user = get_user_by('id', $user_id);

        if ($user !== false) {
            $this->addUserToApi($user);
        }
    }

    /**
     * @param $userId
     *
     * @return int
     */
    private function getApiUserType($userId)
    {
        $userData = get_user_by('id', $userId);

        return (in_array('event_organiser', $userData->roles) || in_array('administrator', $userData->roles))
            ? self::USER_TYPE_ORGANISER
            : self::USER_TYPE_ATTENDEE;
    }

    /**
     * @param $userId
     */
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

        if (! empty($apiId)) {
            ApiClient::instance()->updateUser($apiId, $api_update_array);
        }
    }

    /**
     *
     */
    public function ajaxUserCreateHandler()
    {
        $this->addNewEventAppiUser($_POST['email']);
    }

    /**
     * @param $email
     * @param bool|true $exitOnApiError
     */
    public function addNewEventAppiUser($email, $exitOnApiError = true, $return = false)
    {
        $skip_wp = false;
        $wpUser  = null;
        $result  = 0;

        // check if email is set
        if (! isset($email)) {
            echo $result;
            exit();
        }

        // validate email
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
            if (! $skip_wp) {
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
                if (! array_key_exists('existing_user_email', $wpUser->errors)) {
                    echo $wpUser->get_error_message();

                    if ($exitOnApiError) {
                        exit();
                    }
                }
            }

            $user = ApiClient::instance()->showUser($email);
        }

        if (isset($user['data']['id'])) {
            $oldUser = array(
                'user_id' => $user['data']['id'],
                'new'     => 0
            );

            if ($return) {
                return json_encode($oldUser);
            } else {
                echo json_encode($oldUser);
            }

            if ($exitOnApiError) {
                exit();
            }
        }

        if ($return) {
            return 'error';
        } else {
            echo 'error';
        }

        if ($exitOnApiError) {
            exit();
        }
    }

    /**
     * @param $userData
     * @param bool|true $failOnNoPassword
     *
     * @return int
     */
    public function addUserToApi($userData, $failOnNoPassword = true)
    {
        if (array_key_exists($userData->data->user_email, $this->passwordStore)) {
            $newPass = $this->passwordStore[$userData->data->user_email];
            wp_new_user_notification($userData->data->ID, $newPass);
        } else {
            if ($failOnNoPassword) {
                wp_die(__('Unable to set the user password.', EVENTAPPI_PLUGIN_NAME));
            }

            $newPass = wp_generate_password(); // temp password ?
        }

        $newData = array(
            'type_id'           => $this->getApiUserType($userData->data->ID),
            'full_name'         => $userData->data->display_name,
            'preferred_name'    => $userData->data->display_name,
            'email'             => $userData->data->user_email,
            'password'          => $newPass,
            'confirmation_link' => get_permalink(Settings::instance()->getPageId('my-account'))
        );

        // check whether the user exists
        $response = ApiClient::instance()->showUser($userData->data->user_email);
        if (! array_key_exists('data', $response)) {
            $response = ApiClient::instance()->storeUser($newData);

            if (! isset($response['data']['id'])) {
                return 0;
            }
        }

        $api_user_id = $response['data']['id'];
        update_user_meta($userData->ID, 'eventappi_user_id', $api_user_id);

        return $api_user_id;
    }

    /**
     * @param bool|false $filter
     *
     * @return array
     */
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

    /**
     * @return bool|mixed
     */
    private function getUserNotices()
    {
        $notices = get_user_meta(get_current_user_id(), EVENTAPPI_PLUGIN_NAME . '_user_notices', true);

        if ($notices !== false && is_array($notices)) {
            return $notices;
        }

        return false;
    }

    /**
     * @param array $newNotice
     */
    public function addUserNotice(array $newNotice)
    {
        if (is_array($newNotice)) {
            $notices = $this->getUserNotices();
            if (!is_array($notices)) {
                $notices = array();
            }

            if (array_key_exists('class', $newNotice) && array_key_exists('message', $newNotice)) {
                $newNotice = [
                    'class'   => $newNotice['class'],
                    'message' => htmlspecialchars($newNotice['message'])
                ];
            } elseif (array_key_exists('type', $newNotice)) {
                $newNotice = [
                    'type' => $newNotice['type']
                ];
            } else {
                return;
            }

            $notices[md5(serialize($newNotice))] = $newNotice;

            update_user_meta(get_current_user_id(), EVENTAPPI_PLUGIN_NAME . '_user_notices', $notices);
        }
    }

    /**
     *
     */
    public function showAndRemoveAdminNotice()
    {
        $notices = $this->getUserNotices();

        if (is_array($notices)) {
            foreach ($notices as $notice) {
                $outputMessage = '';

                if (in_array($notice['class'], ['updated', 'error', 'update-nag'])) {
                    $outputMessage = htmlspecialchars($notice['message']);
                } elseif (array_key_exists('type', $notice)) {
                    $outputMessage = Parser::instance()->parseEventAppiTemplate($notice['type']);
                }

                echo "<div class='{$notice['class']}'><p>{$outputMessage}</p></div>";
            }
        }

        update_user_meta(get_current_user_id(), EVENTAPPI_PLUGIN_NAME . '_user_notices', array());
    }

    /**
     * @param $contactMethods
     *
     * @return mixed
     */
    public function addContactMethods($contactMethods)
    {
        $methods = $this->getAdditionalContactMethods();

        foreach ($methods as $method) {
            $contactMethods[$method['id']] = $method['name'];
        }

        return $contactMethods;
    }

    /**
     * @param $user
     */
    public function addNoteText($user)
    {
        echo Parser::instance()->parseEventAppiTemplate(
            'UserNote',
            array('notes' => get_user_meta($user->ID, EVENTAPPI_PLUGIN_NAME.'_notes', true))
        );
    }

    /**
     * @param $userId
     *
     * @return bool
     */
    public function saveNoteText($userId)
    {
        if (!current_user_can('edit_user', $userId)) {
            return false;
        }

        $fieldName = EVENTAPPI_PLUGIN_NAME.'_notes';

        update_user_meta($userId, $fieldName, $_POST[$fieldName]);
    }


    /**
     * @param $userId
     *
     * @return mixed
     */
    public function idExists($userId)
    {
        global $wpdb;
        return $wpdb->get_var('SELECT COUNT(*) FROM `'.$wpdb->users."` WHERE ID='".(int)$userId."'");
    }

    /**
     *
     */
    public function pageRedirect()
    {
        global $current_user;

        $userId = (int)$_GET['id'];

        // Check if the user ID is the same as the current logged in user id - IF User Profile Page is accessed
        // Also check if the user is accessing login page and he is logged in
        // If any condition is met, the user gets redirected to "My Account" page
        if (($userId != 0 && $userId === $current_user->ID && Settings::instance()->isPage('user-profile'))
            || (is_user_logged_in() && Settings::instance()->isPage('login'))
        ) {
            wp_redirect(get_permalink(Settings::instance()->getPageId('my-account')));
            exit;
        }

        // Access to the `Users/Organisers` is ONLY for the Administrator
        if (! in_array('administrator', $current_user->roles)) {
            $baseUri = basename($_SERVER['REQUEST_URI']);
            $usersPage = 'users.php';

            if (strpos($baseUri, $usersPage, 0) === 0) { // Redirect to 'Events' overview page
                wp_redirect('edit.php?post_type='.EVENTAPPI_POST_NAME);
                exit;
            }
        }
    }
}
