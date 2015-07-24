<?php
namespace EventAppi\Helpers;
/**
 * Class Media
 *
 * @package Media
 */
class Meta
{
    /**
     * @var Media|null
     */
    private static $singleton = null;
    private $parentN;

    /**
     *
     */
    private function __construct()
    {
        $this->parentN = explode('\\', __NAMESPACE__)[0];
    }

    /**
     * @return Media|null
     */
    public static function instance()
    {
        Logger::instance()->log(__FILE__, __FUNCTION__, '', Logger::LOG_LEVEL_TRACE);

        if (is_null(self::$singleton)) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }
    
    public function generateMetaFields($args = array(), $type = 'ticket') {
        $postId = '';
        
        // If there is any post id passed then we are on EDIT mode
        if (isset($args['post_id'])) {
            $postId = (int)$args['post_id'];
        }
        
        $cmbFields = array();
        
        $fields = $this->getFields($type);
        
        if (! empty($fields)) {
            foreach ( $fields as $field ) {
                // No values by default; We could be in ADD mode
                $values = array();

                $fArgs = $field;

                unset($fArgs['id']);
                unset($fArgs['type']);
                unset($fArgs['name']);

                $class = _cmb_field_class_for_type($field['type']);

                // Get metadata value of the field for this post / FOR EDIT MODE
                if ( $postId ) {
                    $values = (array)get_post_meta( $postId, $field['id'], false);
                }      

                if (class_exists($class)) {
                    $fArgs['id_prefix'] = ($type == 'ticket') ? (($postId) ? 't'.$postId.'_' : '') : '';
                    $cmbFields[] = new $class($field['id'], $field['name'], (array)$values, $fArgs);
                }
            }
        }
        
        return $cmbFields;
    }    

    public function updateMetaBox($postId, $type = 'ticket') {    
        $fields = $this->getFields($type);
        
        if( empty($fields) ) { 
            return false;
        }
        
        foreach ( $fields as $field ) {
            // Verify this meta box was shown on the page
            if ( ! isset( $_POST['_cmb_present_' . $field['id'] ] ) ) {
                continue;
            }

            if ( isset( $_POST[ $field['id'] ] ) ) {
                $value = (array) $_POST[ $field['id'] ];
            } else {
                $value = array();
            }

            $value = $this->stripRepeatable( $value );

            if ( ! $class = _cmb_field_class_for_type( $field['type'] ) ) {
                do_action( 'cmb_save_' . $field['type'], $field, $value );
            }

            $field_obj = new $class( $field['id'], $field['name'], $value, $field );

            $field_obj->save( $postId, $value );
        }  
    }    
    
    public function getFields($type) {
        if($type == 'ticket') {
            $className = $this->parentN.'\TicketPostType';
        } elseif($type == 'coupon') {
            $className = $this->parentN.'\CouponPostType';
        } else {
            return array(); // Invalid Request
        }
        
        return $className::instance()->getBaseFields();        
    }
    
    public function stripRepeatable($values) {
        foreach ( $values as $key => $value ) {
            if ( false !== strpos( $key, 'cmb-group-x' ) || false !==  strpos( $key, 'cmb-field-x' ) ) {
                unset( $values[$key] );
            } elseif ( is_array( $value ) ) {
                $values[$key] = $this->stripRepeatable( $value );
            }
        }
        return $values;
    }    
}
?>