<?php
namespace EventAppi\Helpers;

class CMBDateField extends CMBGlobal
{
    public function html()
    {
        $name  = $this->name;
        $value = (int)($this->get_value());

        // If the value is an integer one, convert it (from Unix Timestamp) into a readable format such as the one from the WordPress' Settings
        if( $value > 0 ) {
            $value = date(Format::getJSCompatibleDateFormatString(get_option('date_format')), $value);
        } else {
            $value = ''; // Do not show 0 in the field input by default
        }

        $attr_html = '';

        // cmb_datepicker has to be in the 'class' - append it if it was missed
        if( ! isset($this->args['attributes']['class']) ) {
            $this->args['attributes']['class'] = ' eventappi cmb_datepicker';
        } elseif ( ! preg_match('/cmb_datepicker/i', $this->args['attributes']['class']) ) {
            $this->args['attributes']['class'] .= ' eventappi cmb_datepicker';
        }

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
        <input <?php echo $attr_html; ?> id='<?php echo $id_prefix . esc_attr($this->get_the_id_attr()); ?>' type='text' name='<?php echo $name; ?>' value='<?php echo $value; ?>' />
    <?php
    }

    // Convert String to Unix Timestamp
    public function parse_save_values() {
        if(preg_match('/'.EVENTAPPI_TICKET_POST_NAME.'_sale_from/i', $this->name) && $this->values[0] == '') {
            $this->values[0] = date(get_option('date_format'), strtotime('now'));
        }

        if(preg_match('/'.EVENTAPPI_TICKET_POST_NAME.'_sale_to/i', $this->name) && $this->values[0] == '') {
            // Get Event Start Date
            $eventId = (int)$_POST['event_id'];

            if($eventId) {
                $event_start_date = get_post_meta($eventId, EVENTAPPI_POST_NAME.'_start_date', true);

                $this->values[0] = date(get_option('date_format'), (($event_start_date != '') ? $event_start_date : strtotime('now')));
            }
        }

        $this->values[0] = strtotime($this->values[0]);
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
                    <?php echo esc_html( $this->title ); ?>
                    <?php if(isset($this->args['attributes']['required']) && $this->args['attributes']['required'] == 'required') { echo '<span class="ea-error">*</span> &nbsp;'; } ?>
                </label>
            </div>
        <?php }
    }
}
