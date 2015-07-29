<?php namespace EventAppi\Helpers;

class CMBTimeField extends CMBGlobal
{

    public function html()
    {
        $name  = $this->name;
        $value = esc_attr($this->get_value());

        $attr_html = '';
        
        if( ! empty($this->args['attributes']) ) {
            
            foreach($this->args['attributes'] as $attr_name => $attr_value) {
                $attr_html .= $attr_name.'="'.esc_attr($attr_value).'" ';
            }
            
            $attr_html = trim($attr_html);
        }
        ?>
        <input <?php echo $attr_html; ?> id='<?php echo esc_attr($this->get_the_id_attr()); ?>' class='cmb_text_small cmb_timepicker' type='text' name='<?php echo $name; ?>' value='<?php echo $value; ?>' />
        <?php
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