<?php namespace EventAppi\Helpers;

use CMB_Field;

class CMBDateField extends CMB_Field
{
    public function html()
    {
        $name  = $this->name;
        $value = (int)($this->get_value());

        if ($value > 0) {
            $value = date(Format::getJSCompatibleDateFormatString(get_option('date_format')), $value);
        }

        if (array_key_exists('attributes', $this->args) &&
            array_key_exists('required', $this->args['attributes']) &&
            $this->args['attributes']['required'] === 'required'
        ) {
            $required = 'required="required"';
        } else {
            $required = '';
        }
        ?>
        <input <?php
               echo $required; ?> <?php
               $this->class_attr('cmb_text_small cmb_datepicker'); ?> id='<?php
               echo esc_attr($this->get_the_id_attr()); ?>' type='text' name='<?php
               echo $name; ?>' value='<?php
               echo $value; ?>' />
        <?php
    }

    public function parse_save_values() {
        $this->values[0] = strtotime($this->values[0]);
    }
}
