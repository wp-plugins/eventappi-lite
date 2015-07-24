<?php
namespace EventAppi\Helpers;

class CMBSelectField extends CMBGlobal {

    public function __construct() {
        $args = func_get_args();

        call_user_func_array( array( 'parent', '__construct' ), $args );
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
    
    /**
     * Return the default args for the Select field.
     *
     * @return array $args
     */
    public function get_default_args() {
        return array_merge(
            parent::get_default_args(),
            array(
                    'options'         => array(),
                    'multiple'        => false,
                    'select2_options' => array(),
            )
        );
    }

    public function parse_save_values(){
        if ( isset( $this->parent ) && isset( $this->args['multiple'] ) && $this->args['multiple'] ) {
            $this->values = array( $this->values );
        }
    }

    public function get_options() {
        if ( $this->has_data_delegate() )
                $this->args['options'] = $this->get_delegate_data();

        return $this->args['options'];
    }

    public function enqueue_scripts() {
        parent::enqueue_scripts();

        wp_enqueue_script( 'select2', trailingslashit( CMB_URL ) . 'js/vendor/select2/select2.js', array( 'jquery' ) );
        wp_enqueue_script( 'field-select', trailingslashit( CMB_URL ) . 'js/field.select.js', array( 'jquery', 'select2', 'cmb-scripts' ) );
    }

    public function enqueue_styles() {
        parent::enqueue_styles();
        wp_enqueue_style( 'select2', trailingslashit( CMB_URL ) . 'js/vendor/select2/select2.css' );
    }

    public function html() {
        if ( $this->has_data_delegate() ) {
            $this->args['options'] = $this->get_delegate_data();
        }
        
        $this->output_field();
        $this->output_script();
    }

    public function output_field() {
        $val = (array) $this->get_value();

        $name = $this->get_the_name_attr();
        $name .= ! empty( $this->args['multiple'] ) ? '[]' : null;

        $none = is_string( $this->args['allow_none'] ) ? $this->args['allow_none'] : __('None', 'cmb' );
        
        $attr_html = '';
        
        if( ! empty($this->args['attributes']) ) {
            
            foreach($this->args['attributes'] as $attr_name => $attr_value) {
                $attr_html .= $attr_name.'="'.esc_attr($attr_value).'" ';
            }
            
            $attr_html = trim($attr_html);
        }
        ?>

        <select <?php echo $attr_html; ?>
                <?php $this->id_attr(); ?>
                <?php $this->boolean_attr(); ?>
                <?php printf( 'name="%s"', esc_attr( $name ) ); ?>
                <?php printf( 'data-field-id="%s" ', esc_attr( $this->get_js_id() ) ); ?>
                <?php echo ! empty( $this->args['multiple'] ) ? 'multiple' : '' ?>
                <?php $this->class_attr( 'cmb_select' ); ?>
                style="width: 100%"
        >
                <?php if ( $this->args['allow_none'] ) : ?>
                        <option value=""><?php echo $none; ?></option>
                <?php endif; ?>
                <?php foreach ( $this->args['options'] as $value => $name ): ?>
                   <option <?php selected( in_array( $value, $val ) ) ?> value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $name ); ?></option>
                <?php endforeach; ?>
        </select>
        <?php
    }

    public function output_script() {
        $options = wp_parse_args( $this->args['select2_options'], array(
            'placeholder' => __('Type to search', 'cmb'),
            'allowClear'  => true,
        ) );
        ?>

        <script type="text/javascript">
            (function($) {

                    var options = <?php echo  json_encode( $options ); ?>

                    if ( 'undefined' === typeof( window.cmb_select_fields ) )
                            window.cmb_select_fields = {};

                    var id = <?php echo json_encode( $this->get_js_id() ); ?>;
                    window.cmb_select_fields[id] = options;

            })( jQuery );
        </script>
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
?>