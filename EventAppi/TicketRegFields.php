<?php
namespace EventAppi;

use EventAppi\Helpers\Sanitizer;


/**
 * Class EventPostType
 *
 * @package EventAppi
 */
class TicketRegFields
{
	/**
	 * @var null
	 */
	private static $singleton = null;


	/**
	 * @var string
	 */
    public $baseFieldName = 'ea_reg_field';

	/**
	 *
	 */
	private function __construct()
    {
    }

	/**
	 * @return TicketRegFields|null
	 */
	public static function instance()
    {
        if (is_null(self::$singleton)) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

	/**
	 *
	 */
	public function init()
    {
    }



    /**
     * @return array
     */
    public function regFieldTypes()
    {
        $fieldTypes = array(
            'input_text' => __('Input Text', EVENTAPPI_PLUGIN_NAME),
            'input_email' => __('Input E-Mail', EVENTAPPI_PLUGIN_NAME),
        );

        return $fieldTypes;
    }


    /**
     * Gets the Ticket and Event names as well as generating the registration fields array
     *
     * @param $ticketId
     * @param string $status
     *
     * @return array
     */
    public function generateRegFields($ticketId, $status = '')
    {
        // Ticket Name
        $ticketName = get_the_title($ticketId);

        // Event Name
        $eventId = get_post_meta($ticketId, EVENTAPPI_TICKET_POST_NAME.'_event_id', true);
        $eventName = ($eventId > 0) ? get_the_title($eventId) : '';

        // Get the Registration Fields
        $regFields = get_post_meta($ticketId, EVENTAPPI_TICKET_POST_NAME.'_reg_fields', true);

        // Set Default Fields
        $fields = $this->getDefaultRegFields();

        if ($status == 'assign') {
            unset($fields['email']); // only the recipient name is needed
        } elseif ($status == 'claim') {
            $fields = array(); // none needed
        }


        return array('fields' => $fields, 'ticket_name' => $ticketName, 'event_name' => $eventName);
    }

    /* These fields will show in the registration page and they will be preceded by the custom fields */
    /**
    * @return array
    */
    public function getDefaultRegFields()
    {
        $fields = array();

        # Name
        $fields['name'] = array(
            'id' => 'f_name_'.uniqid('name'),
            'title' => __('Name', EVENTAPPI_PLUGIN_NAME),
            'name' => $this->baseFieldName.'[name]',
            'type' => 'input_text',
            'type_attr' => 'text',
            'req' => 1,
            'attrs_list' => 'required="required"'
        );

        # E-Mail
        $fields['email'] = array(
            'id' => 'f_email_'.uniqid('email'),
            'title' => __('E-Mail', EVENTAPPI_PLUGIN_NAME),
            'name' => $this->baseFieldName.'[email]',
            'type' => 'input_email',
            'type_attr' => 'email',
            'req' => 1,
            'attrs' => array('required' => 'required')
        );

        return $fields;
    }


    /**
    * @param $attrs
    *
    * @return string
    */
    public function buildRegFieldAttrs($attrs)
    {
        $attr_html = '';

        if (! empty($attrs)) {
            foreach ($attrs as $attr_name => $attr_value) {
                $attr_html .= $attr_name.'="'.esc_attr($attr_value).'" ';
            }
        }

        return trim($attr_html);
    }

    /* Page to show to the person accessing the Ticket Registration */
    /**
     * @return string
     */
    public function regPage()
    {
        global $wpdb;

        $data = $submitErrors = $postData = $uploadedFiles = array();
        $accessError = $regSuccess = $alreadySent = $noAccess  = false;

        $accessErrorMsg   = __('The ticket registration page is not available as the URL is not valid or you are not logged in. Please contact the administrator for more clarification.', EVENTAPPI_PLUGIN_NAME);
        $regSuccessMsg    = __('The registration was sent. Thank you!', EVENTAPPI_PLUGIN_NAME);
        $alreadySentMsg   = __('The registration was already done.', EVENTAPPI_PLUGIN_NAME);

        $purchaseDbId     = (int)$_REQUEST['ea_reg_id'];
        $regAccessCodeGet = trim($_REQUEST['ea_reg_code']);
        $status           = $_REQUEST['ea_status'];

        // Check first if the right data is requested (no valid form is shown if any of the conditions below is met)
        if (! is_user_logged_in() || ! $purchaseDbId || ! $regAccessCodeGet
            || ! in_array($status, array('claim', 'assign'))) {
            $noAccess = true;
        } else {
            // Now check if the data is valid
            $regAccess = $this->checkRegAccess($purchaseDbId);
            $noAccess  = (! $status || (isset($regAccess['code']) && ($regAccess['code'] != $regAccessCodeGet)));
        }

        if ($noAccess) {
            $accessError = $accessErrorMsg;
        } elseif (is_serialized($regAccess['reg_data'])) {
            $alreadySent = $alreadySentMsg;
        } else {
            $fieldsData  = $this->generateRegFields($regAccess['ticket_id'], $status);

            // Was the form submitted?
            $firstFieldKey = array_keys($fieldsData['fields'])[0];

            if (isset($_POST[$this->baseFieldName][$firstFieldKey])) { // Checks if the first default field was posted
                // Sanitize input first
                $eaRegFieldPost = Sanitizer::instance()->arrayMapRecursive(
                    'sanitize_text_field',
                    $_POST[$this->baseFieldName]
                );

                // Go through the submitted registration fields
                foreach ($eaRegFieldPost as $fName => $fValue) {
                    // Is the field a required one?
                    if (isset($fieldsData['fields'][$fName]['req']) && $fieldsData['fields'][$fName]['req'] == 1) {
                        # Basic Validation
                        if (! $fValue) {
                            $submitErrors[] = $fieldsData['fields'][$fName]['req_error']
                                ?: sprintf(
                                    __('`%s` is a required field.', EVENTAPPI_PLUGIN_NAME),
                                    $fieldsData['fields'][$fName]['title']
                                );
                        }

                        # E-Mail Validation
                        if ($fName == 'email' && ! filter_var($fValue, FILTER_VALIDATE_EMAIL)) {
                            $submitErrors[] = __('The e-mail you submitted does not seem to be valid. Please type it again.', EVENTAPPI_PLUGIN_NAME);
                        }
                        $postData = $eaRegFieldPost;
                    }
                }


                // No errors found? Great, we'll insert the post data into the database ;-)
                // Only the title, value and req values are stored
                if (empty($submitErrors)) {
                    $regData = array();

                    // Now we're looping through All Registration Fields
                    foreach ($fieldsData['fields'] as $fName => $fValue) {
                            $regDataValue = $eaRegFieldPost[$fName];
                        $regData[] = array(
                            'title' => $fValue['title'], 'value' => $regDataValue, 'req' => $fValue['req']
                        );
                    }

                    $ticketId = (int)$_REQUEST['ea_ticket_id'];

                    if ($status == 'claim') {
                        $isClaimed = 1;
                        $isAssigned = 0;
                        TicketPostType::instance()->claimTicket($purchaseDbId);
                    } elseif ($status == 'assign') {
                        $isClaimed = 0;
                        $isAssigned = 1;
                        TicketPostType::instance()->assignTicket($purchaseDbId);
                    }

                    // Store it
                    $wpdb->update(
                        PluginManager::instance()->tables['purchases'],
                        array(
                            'reg_data' => maybe_serialize($regData)
                        ),
                        array(
                            'id'          => $purchaseDbId,
                            'ticket_id'   => $ticketId,
                            'is_claimed'  => $isClaimed,
                            'is_assigned' => $isAssigned
                        )
                    );

                    $regSuccess = $regSuccessMsg;
                }
            } else {
                foreach (array_keys($fieldsData['fields']) as $fName) {
                    $postData[$fName] = ''; // Prevent Notice / Illegal offset errors ;-)
                }
            }

            // Ticket Name
            $data['ticket_name'] = $fieldsData['ticket_name'];

            // Event Name
            $data['event_name']  = $fieldsData['event_name'];

            // List of Fields
            $data['fields']      = $fieldsData['fields'];
        }

        $data['access_error']  = $accessError;
        $data['already_sent']  = $alreadySent;
        $data['reg_success']   = $regSuccess;

        // Pass the ID, Access Code, Status & Ticket ID as we need them for security
        $data['ea_reg_id']     = $purchaseDbId;
        $data['ea_reg_code']   = $regAccessCodeGet;
        $data['ea_status']     = $status;

        $data['ea_ticket_id']  = $regAccess['ticket_id'];

        // If any
        $data['post']          = $postData;
        $data['submit_errors'] = $submitErrors;

        return Parser::instance()->parseTemplate('ticket-reg-live', $data);
    }

    /* This code will get appended to the URL bar
       so the purchaser will be able to fill the ticket registration fields */
    /**
    * @param $purchaseDbId
    *
    * @return array|bool
    */
    public function checkRegAccess($purchaseDbId)
    {
        global $wpdb;

        $tableName = PluginManager::instance()->tables['purchases'];

        $purDataSql = <<<GETPURCHASEDATA
SELECT user_id, purchase_id, purchase_ticket_id, purchased_ticket_hash, ticket_id, reg_data
FROM `{$tableName}` WHERE id = %d
GETPURCHASEDATA;

        $info = $wpdb->get_row($wpdb->prepare($purDataSql, $purchaseDbId), ARRAY_A);

        if (! empty($info)) {
            $userId = $info['user_id'];
            $purApiId = $info['purchase_id'];
            $purTicketApiId = $info['purchase_ticket_id'];
            $purTicketHash = $info['purchased_ticket_hash'];

            $regAccessCode = strrev(sha1($userId . $purApiId . $purTicketApiId));
            $regAccessCode .= '.' . strrev(sha1($purTicketHash) . '.' . md5(strrev($purTicketHash)));

            return array('code' => $regAccessCode, 'ticket_id' => $info['ticket_id'], 'reg_data' => $info['reg_data']);
        }

        return false;
    }
}
