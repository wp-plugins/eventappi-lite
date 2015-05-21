<?php namespace EventAppi\Helpers;

use CMB_Field;

class CMBTimeField extends CMB_Field
{

    public function html()
    {
        $name  = $this->name;
        $value = esc_attr($this->get_value());

        $required = ($this->args['attributes']['required'] == 'required') ? 'required="required"' : '';

        echo "<input " . $required . " id='" .
             esc_attr($this->get_the_id_attr()) .
             "' class='cmb_text_small cmb_timepicker' type='text' name='{$name}' value='{$value}' />";
    }
}
