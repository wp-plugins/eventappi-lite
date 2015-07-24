<?php namespace EventAppi\Helpers;

class CMBNumberField extends CMBGlobal
{
    public function html()
    {
        $name  = $this->name;
        $value = ($this->get_value() == '') ? '' : (int)$this->get_value();
        
        $attr_html = '';
        
        if( ! empty($this->args['attributes']) ) {
            
            foreach($this->args['attributes'] as $attr_name => $attr_value) {
                $attr_html .= $attr_name.'="'.esc_attr($attr_value).'" ';
            }
            
            $attr_html = trim($attr_html);
        }
        
        $id_prefix = '';
        
        if(isset($this->args['id_prefix']) && $this->args['id_prefix'] != '') {
            $id_prefix = $this->args['id_prefix'];
        }        
    ?>
        <input <?php echo $attr_html; ?> id='<?php echo $id_prefix . esc_attr($this->get_the_id_attr()); ?>' type='number' name='<?php echo $name; ?>' value='<?php echo $value; ?>' />
    <?php
    }
    
    public function title() {        
        if ( $this->title ) {
            $id_prefix = '';

            if(isset($this->args['id_prefix']) && $this->args['id_prefix'] != '') {
                $id_prefix = $this->args['id_prefix'];
            }      
        ?>
            <div class="field-title">
                <label <?php printf( 'for="%s"', $id_prefix . esc_attr( $this->get_the_id_attr( null ) ) ); ?>>
                    <?php echo esc_html( $this->title ); ?>&nbsp;
                    <?php if(isset($this->args['attributes']['required']) && $this->args['attributes']['required'] == 'required') { echo '<span class="ea-error">*</span> &nbsp;'; } ?>
                </label>
            </div>
        <?php }
    }
    
    // Convert String to Unix Timestamp
    public function parse_save_values() {
        $type = $_POST[EVENTAPPI_COUPON_POST_NAME.'_type']['cmb-field-0'];
        
        // Add some validation: if percentage is > 100
        if( $this->name == 'eventappi_coupon_val[]' && $type == 'percentage' && $this->values[0] > 100 ) {
            $this->values[0] = 100; // no more than 100%
        }
    }

    public function save($post_id, $values)
    {
        // Do not save the values in WooCommerce as well (it's redundant)
        if (get_post_type($post_id) == 'product') {
            return;
        }

        // Don't save readonly values.
        if ($this->args['readonly']) {
            return;
        }

        $this->values = $values;
        $this->parse_save_values();

        // Allow override from args
        if (! empty($this->args['save_callback'])) {
            call_user_func($this->args['save_callback'], $this->values, $post_id);
            return;
        }

        // If we are not on a post edit screen
        if (! $post_id) {
            return;
        }

        delete_post_meta($post_id, str_replace('[]', '', $this->id));

        foreach ($this->values as $v) {
            $this->value = $v;
            $this->parse_save_value();

            if ($this->value || $this->value === '0') {
                add_post_meta($post_id, str_replace('[]', '', $this->id), $this->value);
            }
        }
    }
}