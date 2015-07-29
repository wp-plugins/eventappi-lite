<?php
namespace EventAppi;

use EventAppi\Helpers\Options;
use EventAppi\Helpers\DisplayField;

/**
 * Class Settings
 *
 * @package EventAppi
 */
class Settings
{
    /**
     * @var Settings|null
     */
    private static $singleton = null;

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return Settings|null
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
        add_action('admin_init', array($this, 'registerSettings'));
    }


    public function settingsPage()
    {
        if (!current_user_can('manage_' . EVENTAPPI_PLUGIN_NAME)) {
            wp_die(__('You do not have sufficient permissions to access this page.', EVENTAPPI_PLUGIN_NAME));
        }

        $settings  = $this->getSettingsArray();
        $tabHeader = array();

        // set up some bits for the template
        $tab = '';
        if (array_key_exists('tab', $_GET) && !empty($_GET['tab'])) {
            $tab = $_GET['tab'];
        } else {
            $tab = __('General', EVENTAPPI_PLUGIN_NAME);
        }

        foreach ($settings as $section => $data) {
            $class = 'nav-tab';
            if ($section === $tab) {
                $class .= ' nav-tab-active';
            }

            $link = add_query_arg(array('tab' => $section));
            if (isset($_GET['settings-updated'])) {
                $link = remove_query_arg('settings-updated', $link);
            }

            $url                 = site_url() . $link;
            $tabHeader[$section] = "<a href=\"{$url}\" class=\"{$class}\">{$data['title']}</a>";
        }

        include __DIR__ . '/Templates/PluginSettings.php';
    }

    public function registerSettings()
    {
        $settings = $this->getSettingsArray();

        if (array_key_exists('tab', $_REQUEST)) {
            $current_section = $_REQUEST['tab'];
        } else {
            $current_section = __('General', EVENTAPPI_PLUGIN_NAME);
        }

        foreach ($settings as $section => $data) {
            if ($current_section !== $section) {
                continue;
            }

            add_settings_section(
                $section,
                null,
                array($this, 'settingsSection'),
                EVENTAPPI_PLUGIN_NAME . '_settings'
            );

            foreach ($data['fields'] as $field) {
                $validation = '';
                if (array_key_exists('callback', $field)) {
                    $validation = $field['callback'];
                }

                register_setting(
                    EVENTAPPI_PLUGIN_NAME . '_settings',
                    EVENTAPPI_PLUGIN_NAME . '_' . $field['id'],
                    $validation
                );

                if ($field['id'] === 'license_key') {
                    $license_key = Options::instance()->getPluginOption($field['id']);

                    if (
                        $license_key !== false &&
                        $license_key !== '' &&
                        LicenseKeyManager::instance()->checkLicenseKey()
                    ) {
                        $field['description'] = __('Your plugin is registered and currently <span style="color:#009900;font-weight:bold;">Active</span>', EVENTAPPI_PLUGIN_NAME);

                        if (!$_POST) {
                            add_settings_error(
                                'license_key',
                                'settings_updated',
                                __('Your plugin was successfully Activated.', EVENTAPPI_PLUGIN_NAME),
                                'updated'
                            );
                        }
                    } else {
                        $field['description'] = __('Your LicenseKey is currently <span style="color:#990000;font-weight:bold;">Inactive</span>', EVENTAPPI_PLUGIN_NAME);

                        if (!$_POST) {
                            add_settings_error(
                                'license_key',
                                'settings_updated',
                                __('The license key you provided is invalid.<br>Please try again or contact EventAppi Support, using the following contact details:<br><ul><li>Telephone: 020202020202</li><li>Email: admin@webplunder.com</li></ul>', EVENTAPPI_PLUGIN_NAME)
                            );
                        }
                    }
                }

                add_settings_field(
                    $field['id'],
                    $field['label'],
                    array(DisplayField::instance(), 'displayField'),
                    EVENTAPPI_PLUGIN_NAME . '_settings',
                    $section,
                    array('field' => $field, 'prefix' => EVENTAPPI_PLUGIN_NAME . '_')
                );
            }

            if (!$current_section) {
                break;
            }
        }
    }

    public function settingsSection($section)
    {
        $settings = $this->getSettingsArray();

        if (array_key_exists('tab', $_GET)) {
            $tab = $_GET['tab'];
        } else {
            $tab = __('General', EVENTAPPI_PLUGIN_NAME);
        }
        $html = '<h2 class="nav-tab-wrapper">';

        foreach ($settings as $section => $data) {
            $class = 'nav-tab';
            if ($section == $tab) {
                $class .= ' nav-tab-active';
            }

            $link = add_query_arg(array('tab' => $section));
            if (isset($_GET['settings-updated'])) {
                $link = remove_query_arg('settings-updated', $link);
            }

            $html .= "<a href=\"{$link}\" class=\"{$class}\">{$data['title']}</a>";
        }

        $html .= '</h2>';

        if (strtolower($tab) == 'pages') {
            $html .= '<p>'.
                __('Make sure the right associations are made for the EventAppi pages.', EVENTAPPI_PLUGIN_NAME).
            '</p>';
        }

        echo $html;
    }

    public function getSettingsArray()
    {
        $currentCurrencySymbol = Currency::instance()->getCurrencySymbol(get_option(EVENTAPPI_PLUGIN_NAME.'_currency'));

        $settingsArray = array(
            'General' => array(
                'title'       => __('General', EVENTAPPI_PLUGIN_NAME),
                'description' => __('Here you can activate your EventAppi Plugin.', EVENTAPPI_PLUGIN_NAME),
                'fields'      => array(
                    array(
                        'id'          => 'license_key',
                        'label'       => __('License Key', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Enter your License Key here.', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => __('License Key', EVENTAPPI_PLUGIN_NAME)
                    ),
                    array(
                        'id'          => 'api_endpoint',
                        'label'       => __('EventAppi API Endpoint', EVENTAPPI_PLUGIN_NAME),
                        'description' => __(
                            'You should only change this if instructed to do so by our support team.',
                            EVENTAPPI_PLUGIN_NAME
                        ),
                        'type'        => 'text',
                        'default'     => 'https://rest.eventappi.com/api/v1',
                        'placeholder' => 'https://rest.eventappi.com/api/v1'
                    ),
                    array(
                        'id'          => 'license_key_checkpoint',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => '1426768000',
                        'force'       => true
                    ),
                    array(
                        'id'          => 'license_key_status',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'invalid'
                    ),
                    // Outlines Section
                    array(
                        'id'          => 'upgrade',
                        'label'       => '<h3>' . __('Upgrade', EVENTAPPI_PLUGIN_NAME) . '</h3>',
                        'description' => __('You can view upgrade and pricing plans availiable to you at: <a href="http://eventappi.com/pricing">http://eventappi.com/pricing</a>', EVENTAPPI_PLUGIN_NAME),
                        'type'        => '',
                        'placeholder' => '',
                    ),


                )
            ),
            'Gateway' => array(
                'title'       => __('Gateway', EVENTAPPI_PLUGIN_NAME),
                'description' => __('Here you can choose your gateway settings.', EVENTAPPI_PLUGIN_NAME),
                'fields'      => array(
                    array(
                        'id'          => 'gateway',
                        'label'       => __('Select a payment gateway', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Choose your gateway.', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'select',
                        'options'     => array(
                            ''               => '-- SELECT --',
                            'twocheckout'    => __('2Checkout', EVENTAPPI_PLUGIN_NAME),
                            'authorizenet'   => __('Authorize.Net', EVENTAPPI_PLUGIN_NAME),
                            'buckaroo'       => __('Buckaroo', EVENTAPPI_PLUGIN_NAME),
                            'cardsave'       => __('CardSave', EVENTAPPI_PLUGIN_NAME),
                            'coinbase'       => __('Coinbase', EVENTAPPI_PLUGIN_NAME),
                            'dummy'          => __('Dummy', EVENTAPPI_PLUGIN_NAME),
                            'eway'           => __('eWAY', EVENTAPPI_PLUGIN_NAME),
                            'firstdata'      => __('First Data', EVENTAPPI_PLUGIN_NAME),
                            'gocardless'     => __('GoCardless', EVENTAPPI_PLUGIN_NAME),
                            'manual'         => __('Manual', EVENTAPPI_PLUGIN_NAME),
                            'migs'           => __('Migs', EVENTAPPI_PLUGIN_NAME),
                            'mollie'         => __('Mollie', EVENTAPPI_PLUGIN_NAME),
                            'multisafepay'   => __('MultiSafepay', EVENTAPPI_PLUGIN_NAME),
                            'netaxept'       => __('Netaxept (BBS)', EVENTAPPI_PLUGIN_NAME),
                            'netbanx'        => __('Netbanx', EVENTAPPI_PLUGIN_NAME),
                            'payfast'        => __('PayFast', EVENTAPPI_PLUGIN_NAME),
                            'payflow'        => __('Payflow', EVENTAPPI_PLUGIN_NAME),
                            'paymentexpress' => __('PaymentExpress (DPS)', EVENTAPPI_PLUGIN_NAME),
                            'paypal'         => __('PayPal Rest', EVENTAPPI_PLUGIN_NAME),
                            'paypalpro'      => __('PayPal Pro', EVENTAPPI_PLUGIN_NAME),
                            'paypalexp'      => __('PayPal Express', EVENTAPPI_PLUGIN_NAME),
                            'pin'            => __('Pin Payments', EVENTAPPI_PLUGIN_NAME),
                            'sagepay'        => __('Sage Pay', EVENTAPPI_PLUGIN_NAME),
                            'securepay'      => __('SecurePay', EVENTAPPI_PLUGIN_NAME),
                            'stripe'         => __('Stripe', EVENTAPPI_PLUGIN_NAME),
                            'targetpay'      => __('TargetPay', EVENTAPPI_PLUGIN_NAME),
                            'worldpay'       => __('WorldPay', EVENTAPPI_PLUGIN_NAME)
                        ),
                        'default'     => __('PayPal', EVENTAPPI_PLUGIN_NAME),
                        'placeholder' => __('Gateway', EVENTAPPI_PLUGIN_NAME)
                    ),
                    array(
                        'id'          => 'gateway_twocheckout_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => __('TwoCheckout', EVENTAPPI_PLUGIN_NAME)
                    ),
                    array(
                        'id'          => 'gateway_twocheckout_accountNumber',
                        'label'       => __('Account number', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your 2Checkout account number.', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_twocheckout_secretWord',
                        'label'       => __('Secret word', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your 2Checkout secret word', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_twocheckout_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Process all transactions on the test gateway', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_authorizenet_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'AuthorizeNet_AIM'
                    ),
                    array(
                        'id'          => 'gateway_authorizenet_apiLoginId',
                        'label'       => __('API Login ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Authorize.net API login ID', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_authorizenet_transactionKey',
                        'label'       => __('Transaction Key', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Authorize.net transaction key', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_authorizenet_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Process all transactions on the test gateway', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_authorizenet_developerMode',
                        'label'       => __('Developer mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Authorize.net Developer Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_buckaroo_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Buckaroo'
                    ),
                    array(
                        'id'          => 'gateway_buckaroo_websiteKey',
                        'label'       => __('Website Key', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Buckaroo website key', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_buckaroo_secretKey',
                        'label'       => __('Secret Key', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Buckaroo secret key', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_buckaroo_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Buckaroo Developer Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_cardsave_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'CardSave'
                    ),
                    array(
                        'id'          => 'gateway_cardsave_merchantId',
                        'label'       => __('Merchant ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Cardsave merchant ID', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_cardsave_password',
                        'label'       => __('Password', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your CardSave password', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_coinbase_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Coinbase'
                    ),
                    array(
                        'id'          => 'gateway_coinbase_apiKey',
                        'label'       => __('API Key', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Coinbase API key', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_coinbase_secret',
                        'label'       => __('Secret', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Coinbase secret', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_coinbase_accountId',
                        'label'       => __('Account ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Coinbase account ID', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_dummy_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Dummy'
                    ),

                    /*
                    array(
                        'id'          => 'gateway_dummy_info',
                        'label'       => 'Note:',
                        'description' => 'Card numbers ending in even numbers should result in successful payments. Payments with cards ending in odd numbers should fail.',
                        'type'        => 'hidden',
                        'placeholder' => ''
                    ),
                     *
                     */
                    array(
                        'id'          => 'gateway_eway_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Eway_Rapid'
                    ),
                    array(
                        'id'          => 'gateway_eway_apiKey',
                        'label'       => __('API Key', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your eWAY API key', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_eway_password',
                        'label'       => __('Password', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your eWAY password', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_eway_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('eWAY Test Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_firstdata_storeId',
                        'label'       => __('Store ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your FirstData store ID', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_firstdata_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'FirstData_Connect'
                    ),
                    array(
                        'id'          => 'gateway_firstdata_sharedSecret',
                        'label'       => __('Shared Secret', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your FirstData shared secret', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_firstdata_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('First Data Test Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_gocardless_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'GoCardless'
                    ),
                    array(
                        'id'          => 'gateway_gocardless_appId',
                        'label'       => __('App ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your GoCardless App ID', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_gocardless_appSecret',
                        'label'       => __('App Secret', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your GoCardless App secret', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_gocardless_merchantId',
                        'label'       => __('Merchant ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your GoCardless merchant ID', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_gocardless_accessToken',
                        'label'       => __('Access Token', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your GoCardless access token', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_gocardless_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('GoCardless Test Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_migs_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Migs_TwoParty'
                    ),
                    array(
                        'id'          => 'gateway_migs_merchantId',
                        'label'       => __('Merchant ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your MIGS merchant ID', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_migs_merchantAccessCode',
                        'label'       => __('Merchant Access Code', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your MIGS merchant access code', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_migs_secureHash',
                        'label'       => __('Secure Hash', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your MIGS secure hash', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_mollie_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Mollie'
                    ),
                    array(
                        'id'          => 'gateway_mollie_apiKey',
                        'label'       => __('API Key', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Mollie API key', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_multisafepay_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'MultiSafepay'
                    ),
                    array(
                        'id'          => 'gateway_multisafepay_accountId',
                        'label'       => __('Account ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your MultiSafepay account ID', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_multisafepay_siteId',
                        'label'       => __('Site ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your MultiSafepay site ID', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_multisafepay_siteCode',
                        'label'       => __('Site Code', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your MultiSafepay site code', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_multisafepay_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('MultiSafepay Test Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_netaxept_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Netaxept'
                    ),
                    array(
                        'id'          => 'gateway_netaxept_merchantId',
                        'label'       => __('Merchant ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Netaxept merchant ID', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_netaxept_password',
                        'label'       => __('Password', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Netaxept password', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_netaxept_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Netaxept Test Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_netbanx_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'NetBanx'
                    ),
                    array(
                        'id'          => 'gateway_netbanx_accountNumber',
                        'label'       => __('Account Number', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your NetBanx account number', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_netbanx_storeId',
                        'label'       => __('Store ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your NetBanx store ID', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_netbanx_storePassword',
                        'label'       => __('Store Password', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your NetBanx store password', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_netbanx_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('NetBanx Test Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_payfast_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'PayFast'
                    ),
                    array(
                        'id'          => 'gateway_payfast_merchantId',
                        'label'       => __('Merchant ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your PayFast merchant ID', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_payfast_merchantKey',
                        'label'       => __('Merchant Key', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your PayFast merchant key', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_payfast_pdtKey',
                        'label'       => __('PDT Key', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your PayFast PDT key', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_payfast_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('PayFast Test Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_payflow_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Payflow_Pro'
                    ),
                    array(
                        'id'          => 'gateway_payflow_username',
                        'label'       => __('Username', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Payflow username', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_payflow_password',
                        'label'       => __('Password', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Payflow password', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_payflow_vendor',
                        'label'       => __('Vendor', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Payflow vendor', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_payflow_partner',
                        'label'       => __('Partner', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Payflow partner', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_payflow_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Payflow Test Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_paymentexpress_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'PaymentExpress_PxPost'
                    ),
                    array(
                        'id'          => 'gateway_paymentexpress_username',
                        'label'       => __('Username', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your PaymentExpress user name', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_paymentexpress_password',
                        'label'       => __('Password', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your PaymentExpress password', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_paypal_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'PayPal_Rest'
                    ),
                    array(
                        'id'          => 'gateway_paypal_clientId',
                        'label'       => __('Client ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your PayPal Rest client ID', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_paypal_secret',
                        'label'       => __('Secret', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your PayPal Rest secret', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_paypal_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('PayPal Test Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id' => 'gateway_paypalpro_username',
                        'label' => __('User Name', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your PayPal Pro user name', EVENTAPPI_PLUGIN_NAME),
                        'type' => 'text',
                        'default' => '',
                        'placeholder' => 'e.g. john@example.com'
                    ),
                    array(
                        'id' => 'gateway_paypalpro_password',
                        'label' => __('Password', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your PayPal Pro password', EVENTAPPI_PLUGIN_NAME),
                        'type' => 'password',
                        'default' => ''
                    ),
                    array(
                        'id' => 'gateway_paypalpro_signature',
                        'label' => __('Signature', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your PayPal Pro Signature', EVENTAPPI_PLUGIN_NAME),
                        'type' => 'password',
                        'default' => ''
                    ),
                    array(
                        'id' => 'gateway_paypalpro_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('PayPal Test Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id' => 'gateway_paypalexp_username',
                        'label' => __('User Name', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your PayPal Express user name', EVENTAPPI_PLUGIN_NAME),
                        'type' => 'text',
                        'default' => '',
                        'placeholder' => 'e.g. john@example.com'
                    ),
                    array(
                        'id' => 'gateway_paypalexp_password',
                        'label' => __('Password', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your PayPal Express password', EVENTAPPI_PLUGIN_NAME),
                        'type' => 'password',
                        'default' => ''
                    ),
                    array(
                        'id' => 'gateway_paypalexp_signature',
                        'label' => __('Signature', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your PayPal Express Signature', EVENTAPPI_PLUGIN_NAME),
                        'type' => 'password',
                        'default' => ''
                    ),
                    array(
                        'id' => 'gateway_paypalexp_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('PayPal Test Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_pin_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Pin'
                    ),
                    array(
                        'id'          => 'gateway_pin_secretKey',
                        'label'       => __('Secret Key', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Pin secret key', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_pin_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Pin Test Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_sagepay_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'SagePay_Server'
                    ),
                    array(
                        'id'          => 'gateway_sagepay_vendor',
                        'label'       => __('Vendor', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Sage Pay vendor', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_sagepay_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Sage Pay Test Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_sagepay_simulatorMode',
                        'label'       => __('Simulator mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('SagePay simulator mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_securepay_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'SecurePay'
                    ),
                    array(
                        'id'          => 'gateway_securepay_merchantId',
                        'label'       => __('Merchant ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Securepay merchant id', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_securepay_transactionPassword',
                        'label'       => __('Transaction Password', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Securepay transaction password', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_securepay_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Secure Pay Test Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    ),
                    array(
                        'id'          => 'gateway_stripe_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'Stripe'
                    ),
                    array(
                        'id'          => 'gateway_stripe_apiKey',
                        'label'       => __('API Key', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Stripe API key', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_targetpay_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'TargetPay_Ideal'
                    ),
                    array(
                        'id'          => 'gateway_targetpay_subAccountId',
                        'label'       => __('Sub Account ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your TargetPay sub-account ID', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_worldpay_fullGatewayName',
                        'label'       => '',
                        'description' => '',
                        'type'        => 'hidden',
                        'default'     => 'WorldPay'
                    ),
                    array(
                        'id'          => 'gateway_worldpay_installationId',
                        'label'       => __('Installation ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your WorldPay installation ID', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_worldpay_accountId',
                        'label'       => __('Account ID', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your WorldPay account id', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_worldpay_secretWord',
                        'label'       => __('Secret Word', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Worldpay secret word', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_worldpay_callbackPassword',
                        'label'       => __('Callback Password', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('Your Worldpay callback password', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'password',
                        'default'     => '',
                        'placeholder' => ''
                    ),
                    array(
                        'id'          => 'gateway_worldpay_testMode',
                        'label'       => __('Test mode', EVENTAPPI_PLUGIN_NAME),
                        'description' => __('WorldPay Test Mode', EVENTAPPI_PLUGIN_NAME),
                        'type'        => 'radio',
                        'options'     => array(true => 'Yes', false => 'No'),
                        'default'     => true
                    )
                )
            )
        );

        // Get list of all published pages
        $pages = get_pages(array(
            'post_type' => 'page',
            'post_status' => 'publish'
        ));

        $pagesList = array('' => __('-- SELECT --', EVENTAPPI_PLUGIN_NAME));

        foreach ($pages as $val) {
            $pagesList[$val->ID] = $val->post_title;
        }

        $pagesFields = array();

        foreach (PluginManager::instance()->customPages() as $val) {
            $pagesFields[] = array(
                'id'          => $val['id'].'_id',
                'label'       => __($val['label'], EVENTAPPI_PLUGIN_NAME),
                'description' => '',
                'type'        => 'select',
                'default'     => '',
                'options'     => $pagesList
            );
        }

        $settingsArray['Pages'] = array(
            'title'       => __('Pages', EVENTAPPI_PLUGIN_NAME),
            'description' => __('Here you can associate the pages to their right location.', EVENTAPPI_PLUGIN_NAME),
            'fields'      => $pagesFields
        );

        return $settingsArray;
    }

    // Returns the Post ID for the EventAppi Page
    public function getPageId($eaPageId)
    {
        return get_option(EVENTAPPI_PLUGIN_NAME.'_'.$eaPageId.'_id');
    }

    // Check if the current page is an EventAppi page
    // If $post is not available (for earlier calls)
    // the URL will be checked (it can have any slug)
    public function isPage($eaPageId)
    {
        global $post;

        if (! empty($post)) {
            $pageId = $post->ID;
        } else {
            $url = explode('?', 'http://'.$_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

            if (function_exists('wp_rewrite_rules')) {
                $pageId = url_to_postid($url[0]);
            } else {
                global $wpdb;
                $pageId = $wpdb->get_var('SELECT ID FROM `'.$wpdb->posts."` WHERE post_name='".basename($url[0])."'");
            }
        }

        return ( $this->getPageId($eaPageId) == $pageId );
    }
}
