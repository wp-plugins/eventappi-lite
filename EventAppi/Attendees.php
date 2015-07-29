<?php
namespace EventAppi;

use EventAppi\Helpers\Logger;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Attendees
 *
 * @author gabriel
 */
class Attendees
{
    /**
     * @var Attendees|null
     */
    private static $singleton = null;

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return Payment|null
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
        add_action('init', array($this, 'handleAttendeeCheckin'));
    }
    

    public function handleAttendeeCheckin()
    {
        if (array_key_exists('check', $_GET) && array_key_exists('state', $_GET) && array_key_exists('post', $_GET)) {
            /**
             * Before allowing WordPress to continue loading, we need to short-circuit the
             * attendee check-in status change so that we can be sure to update the status
             * as early as possible.
             *
             * @see EventPostType::updateAttendeeCheckinStatus()
             */
            $this->updateAttendeeCheckinStatus($_GET['post'], $_GET['check'], $_GET['state']);
        }
    }

    public function updateAttendeeCheckinStatus($eventId, $purchasedTicketHash, $status, $ajax = false)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $apiOrganiserId = get_user_meta(
            get_post_field('post_author', $eventId),
            EVENTAPPI_PLUGIN_NAME . '_user_id',
            true
        );

        $apiEventId     = get_post_meta($eventId, EVENTAPPI_TICKET_POST_NAME . '_api_id', true);
        $status         = (strtolower($status) === 'in') ? 'in' : 'out';

        if (preg_match(EventPostType::REGEX_SHA_1, $purchasedTicketHash)) {
            $result = ApiClient::instance()->setAttendeeCheckinStatus(
                $apiOrganiserId,
                $apiEventId,
                $purchasedTicketHash,
                $status
            );

            if (array_key_exists('error', $result)
                && $result['code'] === ApiClientInterface::RESPONSE_ALREADY_CHECKED_IN) {
                $error = array(
                    'class'   => 'error',
                    'message' => __('The attendee is already checked in, and cannot be checked in again.', EVENTAPPI_PLUGIN_NAME)
                );
               
                if ($ajax) {
                    // If there is an AJAX call, output the result in JSON format
                    echo json_encode($error);
                    exit;
                } else {
                    // No AJAX call is made, the page was refreshed in the Dashboard
                    User::instance()->addUserNotice($error);
                    return;
                }
            }
            
            // AJAX call and no error? Echo the success in JSON format
            if ($ajax) {
                echo json_encode(array(
                    'class' => 'success',
                    'state' => (($status == 'in') ? 'Out' : 'In'),
                    'link_text' => (($status == 'in') ? __('Check Out', EVENTAPPI_PLUGIN_NAME) : __('Check In', EVENTAPPI_PLUGIN_NAME)),
                    'message' => __('The attendee\'s status was changed', EVENTAPPI_PLUGIN_NAME)
                ));
            }
        }
    }

    public function updateAllAttendeesCheckinStatusForEvent($eventId)
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        $apiOrganiserId = get_user_meta(
            get_post_field('post_author', $eventId),
            EVENTAPPI_PLUGIN_NAME . '_user_id',
            true
        );
        $apiEventId     = get_post_meta($eventId, EVENTAPPI_TICKET_POST_NAME . '_api_id', true);

        global $wpdb;
        $attendeeTable = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases';

        $attendeeList = ApiClient::instance()->listEventAttendees($apiOrganiserId, $apiEventId);
        
        if (empty($attendeeList)) {
            return;
        }
        
        if (array_key_exists('data', $attendeeList)) {
            $checkedInAttendees = array();
            $attendeeList       = $attendeeList['data'];

            foreach ($attendeeList as $attendee) {
                if ($attendee['checkedIn'] === true) {
                    // make sure the hashes are quoted for the SQL query
                    $checkedInAttendees[] = "'{$attendee['hash']}'";
                }
            }

            $checkoutAllAttendeesSql = <<<CHECKOUTALLSQL
UPDATE {$attendeeTable}
SET is_checked_in = 0
WHERE event_id = %d
CHECKOUTALLSQL;
            $wpdb->query(
                $wpdb->prepare(
                    $checkoutAllAttendeesSql,
                    $eventId
                )
            );

            if (count($checkedInAttendees) > 0) {
                $checkedInAttendees = implode(',', $checkedInAttendees);
                $checkinUpdateSql   = <<<CHECKINUPDATESQL
UPDATE {$attendeeTable}
SET is_checked_in = 1
WHERE purchased_ticket_hash in ({$checkedInAttendees})
CHECKINUPDATESQL;
                $wpdb->query(
                    $checkinUpdateSql
                );
            }
        }
    }

    public function attendeesExport()
    {
        global $wpdb;

        $eventPostID    = $_GET['post'];
        $attendeeTable  = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases';
        $usersTable     = $wpdb->prefix . 'users';
        $usersMetaTable = $wpdb->prefix . 'usermeta';
        $post           = get_post($eventPostID);

        $this->updateAllAttendeesCheckinStatusForEvent($eventPostID);

        $attendeeQuery = <<<ATTENDEEQUERY
SELECT a.assigned_to, a.is_checked_in, u.user_email,
       u.display_name, mf.meta_value as first_name, ml.meta_value as last_name
FROM {$attendeeTable} AS a
    LEFT JOIN {$usersTable} AS u ON a.user_id = u.ID
    LEFT JOIN {$usersMetaTable} AS mf ON u.ID = mf.user_id AND mf.meta_key = 'first_name'
    LEFT JOIN {$usersMetaTable} AS ml ON u.ID = ml.user_id AND ml.meta_key = 'last_name'
WHERE a.event_id = %d AND (a.is_claimed = '1' OR a.is_assigned = '1')
GROUP BY a.purchased_ticket_hash
ORDER BY ml.meta_value ASC, mf.meta_value ASC
ATTENDEEQUERY;

        $queryResults           = $wpdb->get_results(
            $wpdb->prepare(
                $attendeeQuery,
                $eventPostID
            )
        );
        $attendeesForExport     = array();
        $additionalDataElements = User::instance()->getAdditionalContactMethods();
        $additionalDataKeys     = array();

        foreach ($additionalDataElements as $dataKey) {
            $additionalDataKeys[$dataKey['id']] = $dataKey['name'];
        }

        foreach ($queryResults as $attendee) {
            $attendeeData = array(
                __('Email', EVENTAPPI_PLUGIN_NAME)        => $attendee->user_email,
                __('First Name', EVENTAPPI_PLUGIN_NAME)   => $attendee->first_name,
                __('Last Name', EVENTAPPI_PLUGIN_NAME)    => $attendee->last_name,
                __('Display Name', EVENTAPPI_PLUGIN_NAME) => $attendee->display_name,
                __('Checked In', EVENTAPPI_PLUGIN_NAME)   => ($attendee->is_checked_in === '1') ? __('Yes', EVENTAPPI_PLUGIN_NAME) : __('No', EVENTAPPI_PLUGIN_NAME),
                __('Assigned To', EVENTAPPI_PLUGIN_NAME)  => $attendee->assigned_to
            );
            $attendeesForExport[] = $attendeeData;
        }

        header('Content-type: text/csv');
        header('Content-Disposition: attachment; filename="event' . $post->ID . '-attendees.csv"');

        if (count($attendeesForExport) > 0) {
            echo $this->csvFormattedLine(array_keys($attendeesForExport[0]));
            foreach ($attendeesForExport as $attendee) {
                echo $this->csvFormattedLine($attendee);
            }
        } else {
            echo __('There are no attendees for this event', EVENTAPPI_PLUGIN_NAME);
        }

        exit();
    }

    public function attendeesPage()
    {
        if (! current_user_can('manage_' . EVENTAPPI_PLUGIN_NAME)) {
            wp_die(__('You do not have sufficient permissions to access this page.', EVENTAPPI_PLUGIN_NAME));
        }

        $eventPostID = (int)$_GET['post'];

        $this->updateAllAttendeesCheckinStatusForEvent($eventPostID);

        $data = $this->getAttendeesData($eventPostID);

        echo Parser::instance()->parseEventAppiTemplate('Events/ListEventAttendees', $data);
    }
    
    
    public function getAttendeesData($eventPostID)
    {
        global $wpdb, $post;
        
        $data = array();
        
        $results_per_page = 2;
        
        $data['customPost']     = get_post_type_object(EVENTAPPI_POST_NAME);
        
        if (is_admin()) { // Dashboard
            $data['postUrl'] = get_admin_url() . 'edit.php?post_type=' . EVENTAPPI_POST_NAME .
                              '&page=' . EVENTAPPI_PLUGIN_NAME . "-attendees&post={$eventPostID}";
            $data['attendeesLabel'] = __('Attendees', EVENTAPPI_PLUGIN_NAME);
            $sq_key = 's';
        } else { // Front-end
            $page = get_query_var('page', 1);
            
            if ($page == 0) {
                $page = 1;
            }
            
            $sq_key = 'sf';
            
            $data['postUrl'] = $data['postUrlRoot'] = get_permalink($post->ID).'?id='.$eventPostID;
            
            // Append any existing query strings
            if ($_GET['checked'] != '') {
                $data['postUrl'] .= '&checked='.htmlspecialchars($_GET['checked']);
            }
            
            if ($_GET['sf'] != '') {
                $data['postUrl'] .= '&sf='.urlencode($_GET['sf']);
            }
        }
        
        $data['exportUrl'] = get_admin_url() . 'link.php?post_type=' . EVENTAPPI_POST_NAME . '&page=' . EVENTAPPI_PLUGIN_NAME . "-download-attendees&post={$eventPostID}";
        
        $data['eventPost']      = get_post($eventPostID);

        $attendeeTable  = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_purchases';
        $usersTable     = $wpdb->prefix . 'users';
        $usersMetaTable = $wpdb->prefix . 'usermeta';

        $sql           = <<<ATTENDEECOUNTSQL
SELECT COUNT(id) FROM {$attendeeTable}
WHERE event_id = {$eventPostID}
AND (is_claimed = '1' OR is_assigned = '1')
ATTENDEECOUNTSQL;
        $attendeeCount = $wpdb->get_var($sql);

        $sql           = <<<ATTENDEECHECKSQL
SELECT COUNT(id) FROM {$attendeeTable}
WHERE event_id = {$eventPostID}
AND (is_claimed = '1' OR is_assigned = '1')
AND is_checked_in = '1'
ATTENDEECHECKSQL;
        $attendeeCheck = $wpdb->get_var($sql);

        $attendeeQuery = <<<ATTENDEEQUERY
SELECT a.id, a.user_id, a.assigned_to, a.is_claimed, a.is_checked_in, u.user_email,
       u.display_name, mf.meta_value as first_name, ml.meta_value as last_name, a.purchased_ticket_hash
FROM {$attendeeTable} AS a
    LEFT JOIN {$usersTable} AS u ON a.user_id = u.ID
    LEFT JOIN {$usersMetaTable} AS mf ON u.ID = mf.user_id AND mf.meta_key = 'first_name'
    LEFT JOIN {$usersMetaTable} AS ml ON u.ID = ml.user_id AND ml.meta_key = 'last_name'
    WHERE a.event_id = %d AND (a.is_claimed = '1' OR a.is_assigned = '1')
ATTENDEEQUERY;

        if (array_key_exists($sq_key, $_GET)) {
            $attendeeQuery .= <<<SEARCHCLAUSE
    AND (u.user_email LIKE  '%%%s%%' OR
         a.assigned_to LIKE '%%%s%%' OR
         mf.meta_value LIKE '%%%s%%' OR
         ml.meta_value LIKE '%%%s%%')
SEARCHCLAUSE;

        } elseif (array_key_exists('checked', $_GET)) {
            $attendeeQuery .= " AND a.is_checked_in = ";
            $attendeeQuery .= ($_GET['checked'] == 'yes') ? '1 ' : '0 ';
        }

        $attendeeQuery .= " GROUP BY a.purchased_ticket_hash ";
        
        if (array_key_exists($sq_key, $_GET)) {
            $attendeeQuery = $wpdb->prepare(
                $attendeeQuery,
                $eventPostID,
                $_GET[$sq_key],
                $_GET[$sq_key],
                $_GET[$sq_key],
                $_GET[$sq_key]
            );
        } else {
            $attendeeQuery = $wpdb->prepare(
                $attendeeQuery,
                $eventPostID
            );
        }
        
        // All Results - To determine total number of pages
        $attendeeAllResults = count($wpdb->get_results($attendeeQuery));
      
        // Page?
        if ($page != '') {
            $offset = (($page - 1) * $results_per_page);
            $attendeeQuery .= ' LIMIT '.$offset.', '.$results_per_page;
        }

        $data['attendees'] = $wpdb->get_results($attendeeQuery);
                
        foreach ($data['attendees'] as $index => $attendee) {
            if (current_user_can('edit_user', $attendee->user_id)) {
                $data['attendees'][$index]->can_edit = true;
                $data['attendees'][$index]->edit_user_url = get_permalink(Settings::instance()->getPageId('user-profile')).'?id='.$attendee->user_id;
            }
        }
        
        $data[$sq_key] = htmlspecialchars($_GET[$sq_key]);

        if (is_null($attendeeCheck)) {
            $attendeeCheck = 0;
        }

        $data['counters'] = array(
            array(
                'name'  => __('All', EVENTAPPI_PLUGIN_NAME),
                'count' => $attendeeCount,
                'link'  => ''
            ),
            array(
                'name'  => __('Checked In', EVENTAPPI_PLUGIN_NAME),
                'count' => $attendeeCheck,
                'link'  => '&checked=yes'
            ),
            array(
                'name'  => __('Not Checked In', EVENTAPPI_PLUGIN_NAME),
                'count' => $attendeeCount - $attendeeCheck,
                'link'  => '&checked=no'
            )
        );

        $data['extraDataFields'] = User::instance()->getAdditionalContactMethods(true);
        
        // Total Pages
        $data['total_pages'] = ceil($attendeeAllResults / $results_per_page);
        
        // Current page
        $data['page'] = $page;
                
        return $data;
    }
    
    public function loadUpdateAttendeeStatusCallback()
    {
        $eventId = (int)$_POST['event_id'];
        $check = sanitize_text_field($_POST['check']);
        $state = sanitize_text_field($_POST['state']);
        
        if ($eventId && $check && $state) {
            // The method will output the JSON to the front-end
            $this->updateAttendeeCheckinStatus($eventId, $check, $state, true);
            exit;
        }
    }
    
    private function csvFormattedLine($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $element) {
                if (!is_string($element)) {
                    $element = (string) $element;
                }
                if (strpos($element, ',') !== false) {
                    if (strpos($element, '"') !== false) {
                        $element = str_replace('"', '\"', $element);
                    }
                    $data[$key] = '"' . $element . '"';
                }
            }
            $data = implode(',', $data);
        }

        return $data . "\n";
    }
}
