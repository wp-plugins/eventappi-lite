<?php namespace EventAppi\Helpers;

use CMB_Field;

class CMBHiddenField extends CMB_Field
{

    public function html()
    {
        $name  = $this->name;
        $value = esc_attr($this->get_value());

        echo "<input type='hidden' name='{$name}' value='{$value}'>";
    }
}
