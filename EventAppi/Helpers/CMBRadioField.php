<?php namespace EventAppi\Helpers;

class CMBRadioField extends CMBGlobal
{
    public function html()
    {
        if ( $this->has_data_delegate() ) {
            $this->args['options'] = $this->get_delegate_data();
        }

        $id_prefix = '';

        if(isset($this->args['id_prefix']) && $this->args['id_prefix'] != '') {
            $id_prefix = $this->args['id_prefix'];
        }
        
        foreach ( $this->args['options'] as $key => $value ): ?>

            <input <?php $this->id_attr( $id_prefix . 'item-' . $key ); ?> <?php $this->boolean_attr(); ?> <?php $this->class_attr(); ?> type="radio" <?php $this->name_attr(); ?>  value="<?php echo esc_attr( $key ); ?>" <?php checked( $key, $this->get_value() ); ?> />
            <label <?php $this->for_attr( $id_prefix . 'item-' . $key ); ?> style="margin-right: 20px;">
                    <?php echo esc_html( $value ); ?>
            </label>

        <?php endforeach;
    }
    
    public function title()
    {
        if ( $this->title ) {
            $id_prefix = '';

            if(isset($this->args['id_prefix']) && $this->args['id_prefix'] != '') {
                $id_prefix = $this->args['id_prefix'];
            }      
        ?>
            <div class="field-title">
                <label <?php printf( 'for="%s"', $id_prefix . esc_attr( $this->get_the_id_attr( null ) ) ); ?>>
                    <?php echo esc_html( $this->title ); ?>
                    <?php if(isset($this->args['attributes']['required']) && $this->args['attributes']['required'] == 'required') { echo '<span class="ea-error">*</span> &nbsp;'; } ?>
                </label>
            </div>
        <?php }
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