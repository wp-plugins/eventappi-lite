<?php namespace EventAppi\Helpers;

use CMB_Field;

class CMBNumberField extends CMB_Field
{
    public function html()
    {
        $name  = $this->name;
        $value = (int)($this->get_value());
        
        $attr_html = '';
        
        if( ! empty($this->args['attributes']) ) {
            
            foreach($this->args['attributes'] as $attr_name => $attr_value) {
                $attr_html .= $attr_name.'="'.$attr_value.'" ';
            }
            
            $attr_html = trim($attr_html);
        }
    ?>
        <input <?php echo $attr_html; ?> <?php $this->class_attr('cmb_text_small'); ?> id='<?php echo esc_attr($this->get_the_id_attr()); ?>' type='number' name='<?php echo $name; ?>' value='<?php echo $value; ?>' />
    <?php
    }
    
    // Convert String to Unix Timestamp
    public function parse_save_values() {
        $type = $_POST[EVENTAPPI_COUPON_POST_NAME.'_type']['cmb-field-0'];
        
        // Add some validation: if percentage is > 100
        if( $this->name == 'eventappi_coupon_val[]' && $type == 'percentage' && $this->values[0] > 100 ) {
            $this->values[0] = 100; // no more than 100%
        }
    }
}