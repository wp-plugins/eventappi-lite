<?php
namespace EventAppi;

use Tax_Meta_Class;
use EventAppi\Helpers\CountryList as CountryHelper;
use EventAppi\Helpers\Logger;
use stdClass;

/**
 * Class EventVenueTax
 *
 * @package EventAppi
 * Handles the Venue Taxonomy (For the Events)
 */
class EventVenueTax
{
    /**
     * @var EventVenueTax|null
     */
    private static $singleton = null;
    public $venueTable;
    public $venueKeys;
    
    const TAX_NAME = 'venue';
    
    /**
     *
     */
    private function __construct()
    {
        global $wpdb;
        
        $this->venueTable = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_venues';
        
        $this->venueKeys = array(
            'name'    => EVENTAPPI_POST_NAME . '_venue_name',
            'addr'    => EVENTAPPI_POST_NAME . '_venue_address_1',
            'addr2'   => EVENTAPPI_POST_NAME . '_venue_address_2',
            'city'    => EVENTAPPI_POST_NAME . '_venue_city',
            'code'    => EVENTAPPI_POST_NAME . '_venue_postcode',
            'country' => EVENTAPPI_POST_NAME . '_venue_country'
        );
    }

    /**
     * @return EventVenueTax|null
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
        add_action('edit_'.self::TAX_NAME, array($this, 'saveUpdateVenueEntry'), 10, 1);
        add_action('create_'.self::TAX_NAME, array($this, 'saveUpdateVenueEntry'), 10, 1);
                
        if (isset($_GET['taxonomy']) && $_GET['taxonomy'] == self::TAX_NAME
            && isset($_GET['post_type']) && $_GET['post_type'] == EVENTAPPI_POST_NAME
        ) {
            add_filter('list_terms_exclusions', array($this, 'filterVenuesDashboard'), 100000, 2);

            // Show the Event Organiser that added the venue (only for the administrator)
            if (isset($_GET['action']) && $_GET['action'] == 'edit') {
                add_action(self::TAX_NAME.'_edit_form_fields', array($this, 'venueEditPageUserInfo'), 10, 2);
            }
        }
        
        add_action('admin_menu', array($this, 'removeDefaultVenueMetaBox'));
    }
    
    public function createVenueTaxonomy()
    {
        $labels = array(
            'name'                       => _x('Venues', 'taxonomy general name', EVENTAPPI_PLUGIN_NAME),
            'singular_name'              => _x('Venue', 'taxonomy singular name', EVENTAPPI_PLUGIN_NAME),
            'search_items'               => __('Search Venues', EVENTAPPI_PLUGIN_NAME),
            'popular_items'              => __('Popular Venues', EVENTAPPI_PLUGIN_NAME),
            'all_items'                  => __('All Venues', EVENTAPPI_PLUGIN_NAME),
            'parent_item'                => __('Venue', EVENTAPPI_PLUGIN_NAME),
            'parent_item_colon'          => __('Venue:', EVENTAPPI_PLUGIN_NAME),
            'edit_item'                  => __('Edit Venue', EVENTAPPI_PLUGIN_NAME),
            'update_item'                => __('Update Venue', EVENTAPPI_PLUGIN_NAME),
            'add_new_item'               => __('Add New Venue', EVENTAPPI_PLUGIN_NAME),
            'new_item_name'              => __('New Venue Name', EVENTAPPI_PLUGIN_NAME),
            'separate_items_with_commas' => __('Separate venues with commas', EVENTAPPI_PLUGIN_NAME),
            'add_or_remove_items'        => __('Add or remove venues', EVENTAPPI_PLUGIN_NAME),
            'choose_from_most_used'      => __('Choose from the most used venues', EVENTAPPI_PLUGIN_NAME),
            'not_found'                  => __('No venue found.', EVENTAPPI_PLUGIN_NAME),
            'menu_name'                  => __('Venues', EVENTAPPI_PLUGIN_NAME),
        );

        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'public'            => false,
            // TODO     'update_count_callback' => '_update_post_term_count',
            'query_var'         => true,
            'rewrite'           => false,
        );

        register_taxonomy(self::TAX_NAME, EVENTAPPI_POST_NAME, $args);
    }
    
    public function setupCustomVenueMeta()
    {
        $config = array(
            'id'             => EVENTAPPI_POST_NAME . '_venue_meta_box',
            'title'          => 'Venues',
            'pages'          => array('venue'),
            'context'        => 'advanced',
            'fields'         => array(),
            'local_images'   => false,
            'use_with_theme' => false
        );

        $meta = new Tax_Meta_Class($config);

        $meta->addText(EVENTAPPI_POST_NAME . '_venue_address_1', array('name' => 'Address line 1'));
        $meta->addText(EVENTAPPI_POST_NAME . '_venue_address_2', array('name' => 'Address line 2'));
        $meta->addText(EVENTAPPI_POST_NAME . '_venue_city', array('name' => 'City'));
        $meta->addText(EVENTAPPI_POST_NAME . '_venue_postcode', array('name' => 'Zip / Postal code'));
        $meta->addSelect(
            EVENTAPPI_POST_NAME . '_venue_country',
            CountryHelper::instance()->getCountryList(),
            array('name' => 'Country', 'std' => 'US')
        );
        $meta->addHidden(EVENTAPPI_POST_NAME . '_venue_api_id', array('name' => 'API Venue ID'));

        $meta->Finish();
    }
    
    /* This method is called when you create venues in the front end as a logged in user
       as well as when a guest user confirms the activation of his/her account
    **/
    public function insertVenue($postId, $values, $userId = false, $isPost = true)
    {
        $vKeys = $this->venueKeys;
      
        if(! $isPost ) {
            $name = $values['name'];
            $addr = $values['addr'];
            $addr2 = $values['addr2'];
            $city = $values['city'];
            $code = $values['code'];
            $country = $values['country'];
            // Was it a POST request?
        } else {
            $name = $values[$vKeys['name']];
            $addr = $values[$vKeys['addr']];
            $addr2 = $values[$vKeys['addr2']];
            $city = $values[$vKeys['city']];
            $code = $values[$vKeys['code']];
            $country = $values[$vKeys['country']];            
        }
        
        // we create a new venue
        $term = wp_insert_term($name, self::TAX_NAME);
        if (is_a($term, 'WP_Error')) {
            wp_die(__('Unable to add the new venue', EVENTAPPI_PLUGIN_NAME));
        }
        
        $venueId = $term['term_id'];

        wp_set_object_terms($postId, $venueId, self::TAX_NAME, false);
        
        update_tax_meta($venueId, $vKeys['addr'], $addr);
        update_tax_meta($venueId, $vKeys['addr2'], $addr2);
        update_tax_meta($venueId, $vKeys['city'], $city);
        update_tax_meta($venueId, $vKeys['code'], $code);
        update_tax_meta($venueId, $vKeys['country'], $country);
        
        // Guest confirmed account & event creation
        if (! $isPost) {
            global $wpdb;
                        
            // New Venue added after the guest confirmed the account
            // Let's add it to the API
            $data = array(
                'name'      => $name,
                'address_1' => $addr,
                'address_2' => $addr2,
                'address_3' => $city,
                'address_4' => null,
                'postcode'  => $code,
                'country'   => $country
            );
        
            $newVenue = ApiClient::instance()->storeVenue($data);
            
            if (array_key_exists('data', $newVenue)) {
                $newVenue = $newVenue['data'];
                $sql      = <<<NEWVENUESQL
INSERT INTO {$this->venueTable} (`wp_id`, `api_id`, `user_id`, `address_1`, `address_2`, `city`, `postcode`, `country`)
VALUES (%d, %d, %d, %s, %s, %s, %s, %s)
NEWVENUESQL;
                $wpdb->query(
                    $wpdb->prepare(
                        $sql,
                        $venueId,
                        $newVenue['id'],
                        $userId,
                        $addr,
                        $addr2,
                        $city,
                        $code,
                        $country
                    )
                );
            }
        }
    }
    
    public function saveUpdateVenueEntry($venueId)
    {
        global $wpdb, $current_user;
            
        $weHaveAVenue = false;

        foreach ($_REQUEST as $key => $value) {
            if (substr($key, 0, 22) === EVENTAPPI_POST_NAME.'_venue_') {
                $keyParts     = explode('_venue_', $key);
                $var          = $keyParts[1];
                $$var         = $value;
                $weHaveAVenue = true;
            }
        }

        if (! $weHaveAVenue) {
            return;
        }

        if (array_key_exists(EVENTAPPI_POST_NAME.'_venue_name', $_POST)
            && !empty($_POST[EVENTAPPI_POST_NAME.'_venue_name'])
        ) {
            $venueName = $_POST[EVENTAPPI_POST_NAME.'_venue_name'];
        } elseif (array_key_exists('tag-name', $_REQUEST)) {
            $venueName = $_REQUEST['tag-name'];
        } else {
            $venueName = $_REQUEST['name'];
        }

        $data = array(
            'name'      => $venueName,
            'address_1' => ${'address_1'},
            'address_2' => ${'address_2'},
            'address_3' => ${'city'},
            'address_4' => null,
            'postcode'  => ${'postcode'},
            'country'   => ${'country'}
        );

        $sql        = <<<CHECKVENUESQL
SELECT `api_id` FROM {$this->venueTable}
WHERE `wp_id` = %d
CHECKVENUESQL;
        $apiKey     = $wpdb->get_var(
            $wpdb->prepare(
                $sql,
                $venueId
            )
        );

        $apiVenue = array();
        if (!is_null($apiKey)) {
            $apiVenue = ApiClient::instance()->showVenue($apiKey);
        }

        if (array_key_exists('data', $apiVenue)) {
            // we already have something saved on the API - let's update it
            $data['slug'] = $_REQUEST['slug'];
            $updateVenue  = ApiClient::instance()->updateVenue($apiKey, $data);
            if (array_key_exists('code', $updateVenue)
                && $updateVenue['code'] === ApiClientInterface::RESPONSE_OK
            ) {
                $sql = <<<UPDATEVENUESQL
UPDATE {$this->venueTable}
SET `address_1` = %s,
    `address_2` = %s,
    `city` = %s,
    `postcode` = %s,
    `country` = %s
WHERE `wp_id` = %d AND `api_id` = %d
UPDATEVENUESQL;
                $wpdb->query(
                    $wpdb->prepare(
                        $sql,
                        ${'address_1'},
                        ${'address_2'},
                        ${'city'},
                        ${'postcode'},
                        ${'country'},
                        $venueId,
                        $apiKey
                    )
                );
            }
        } else {
            // Store a new venue on the API
            $newVenue = ApiClient::instance()->storeVenue($data);
            if (array_key_exists('data', $newVenue)) {
                $newVenue = $newVenue['data'];
                $sql      = <<<NEWVENUESQL
INSERT INTO {$this->venueTable} (`wp_id`, `api_id`, `user_id`, `address_1`, `address_2`, `city`, `postcode`, `country`)
VALUES (%d, %d, %d, %s, %s, %s, %s, %s)
NEWVENUESQL;
                $wpdb->query(
                    $wpdb->prepare(
                        $sql,
                        $venueId,
                        $newVenue['id'],
                        $current_user->ID,
                        ${'address_1'},
                        ${'address_2'},
                        ${'city'},
                        ${'postcode'},
                        ${'country'}
                    )
                );
            }
        }
    }
    
    public function filterVenuesDashboard($exclusions, $args)
    {
        global $current_user, $wpdb;
        
        // If the user is an administrator, all the venues will be shown
        if (in_array('administrator', $current_user->roles)) {
            return $exclusions;
        }
        
        $userId = $current_user->ID;
        
        // Get Venue IDs that are belonging only to the logged in user (non admin)  
        $userVenueA = $wpdb->get_results(
            'SELECT wp_id FROM `'.$this->venueTable.'` WHERE user_id = '.$userId,
            ARRAY_N
        );
               
        if (! empty($userVenueA)) {
            $userVenueIdsList = '';
        
            foreach ($userVenueA as $val) {
                $userVenueIdsList .= $val[0].',';
            }
            
            $exclusions .= ' AND t.term_id IN ('.trim($userVenueIdsList, ',').') ';
        }
                
        return $exclusions;
    }
    
    public function filterVenuesFrontend($user)
    {
        global $wpdb;
        
        $sql = 'SELECT t.term_id, t.name FROM `'.$wpdb->terms.'` t'
        . ' INNER JOIN `'.$wpdb->term_taxonomy.'` tt ON t.term_id = tt.term_id '
        . ' INNER JOIN `'.$this->venueTable.'` v ON (v.wp_id = t.term_id) '
        . ' WHERE tt.taxonomy IN (\'venue\') ';
        
        // No admin? Filter the venues only to the ones added by the user
        if(! in_array('administrator', $user->roles) ) {
            $sql .= ' && v.user_id='.$user->ID.' ';
        }
        
        $sql .= ' ORDER BY t.name';
        
        return $wpdb->get_results($sql, OBJECT);
    }
    
    // Show 'Event Organiser' User Information to the Administrator
    public function venueEditPageUserInfo()
    {
        get_currentuserinfo();
        global $current_user, $wpdb;
                
        // Only the admin can view this
        if (! in_array('administrator', $current_user->roles)) {
            return;
        }
        
        $venueId = (isset($_GET['tag_ID'])) ? (int)$_GET['tag_ID'] : '';
        $venueTable = $wpdb->prefix . EVENTAPPI_PLUGIN_NAME . '_venues';
        
        $venueUserId = $wpdb->get_var('SELECT user_id FROM `'.$venueTable.'` WHERE wp_id = '.$venueId);
                
        if (! $venueUserId) {
            $venueUserInfo = new stdClass();
        } else {
            $venueUserInfo = get_userdata($venueUserId);
        }
        
        echo Parser::instance()->parseEventAppiTemplate('VenueUserInfo', $venueUserInfo);
    }
    
    public function getInfo($venueId)
    {
        if ($venueId > 0) {
            $theVenue = get_term($venueId, 'venue', ARRAY_A);
            $theVenueMeta = get_tax_meta_all($venueId);
            $theAddress   = implode(', ', $theVenueMeta);
            $theAdrLink   = str_replace(' ', '%20', $theAddress);

            return array(
                'venue' => $theVenue,
                'addr' => $theAddress,
                'addr_link' => $theAdrLink
            );
        } else {
            return array(
                'venue' => '',
                'addr' => '',
                'addr_link' => ''
            );
        }
    }
    
    public function removeDefaultVenueMetaBox()
    {
        remove_meta_box('tagsdiv-venue', EVENTAPPI_POST_NAME, 'normal');
        remove_meta_box('tagsdiv-venue', EVENTAPPI_POST_NAME, 'side');
        remove_meta_box('tagsdiv-venue', EVENTAPPI_POST_NAME, 'advanced');
    }
    
}
