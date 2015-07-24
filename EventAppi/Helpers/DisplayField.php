<?php
namespace EventAppi\Helpers;

/**
 * Class Format
 *
 * @package EventAppi\Helpers
 */
class DisplayField
{
    /**
     * @var null
     */
    private static $singleton = null;

    /**
     *
     */
    private function __construct()
    {
    }

    /**
     * @return Format|null
     */
    public static function instance()
    {
        if (is_null(self::$singleton)) {
            self::$singleton = new self();
        }

        return self::$singleton;
    }

    public function displayField($data)
    {        
        $field       = $data['field'];
        $option_name = $data['prefix'] . $field['id'];

        $option = get_option($option_name);

        if (array_key_exists('default', $field) &&
            ((array_key_exists('force', $field) && $field['force'] === true) ||
             empty($option))
        ) {
            $option = $field['default'];
        }

        if (array_key_exists('class', $field)) {
            $class = $field['class'];
        } else {
            $class = '';
        }

        $html = '';
        switch ($field['type']) {

            case 'text':
            case 'url':
            case 'email':
                $html .= '<input id="' . esc_attr($field['id']) . '" class="' . esc_attr($class) .
                         '" type="text" name="' . esc_attr($option_name) . '" placeholder="' .
                         esc_attr($field['placeholder']) . '" value="' . esc_attr($option) . '" />';
                break;

            case 'password':
            case 'number':
            case 'hidden':
                $min = '';
                if (isset($field['min'])) {
                    $min = ' min="' . esc_attr($field['min']) . '"';
                }

                $max = '';
                if (isset($field['max'])) {
                    $max = ' max="' . esc_attr($field['max']) . '"';
                }
                $html .= '<input id="' . esc_attr($field['id']) . '" type="' .
                         esc_attr($field['type']) . '" name="' . esc_attr($option_name) .
                         '" placeholder="' . esc_attr($field['placeholder']) . '" value="' .
                         esc_attr($option) . '"' . $min . '' . $max . '/>';
                break;

            case 'text_secret':
                $html .= '<input id="' . esc_attr($field['id']) . '" type="text" name="' .
                         esc_attr($option_name) . '" placeholder="' .
                         esc_attr($field['placeholder']) . '" value="" />';
                break;

            case 'textarea':
                $html .= '<textarea id="' . esc_attr($field['id']) . '" rows="5" cols="50" name="' .
                         esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) .
                         '">' . $option . '</textarea><br/>';
                break;

            case 'checkbox':
                $checked = '';
                if ($option && 'on' == $option) {
                    $checked = 'checked="checked"';
                }
                $html .= '<input id="' . esc_attr($field['id']) . '" type="' . esc_attr($field['type']) .
                         '" name="' . esc_attr($option_name) . '" ' . $checked . '/>' . "\n";
                break;

            case 'radio':
                foreach ($field['options'] as $k => $v) {
                    $checked = false;
                    if ($k == $option) {
                        $checked = true;
                    }
                    $html .= '<label for="' . esc_attr($field['id'] . '_' . $k) . '"><input type="radio" ' .
                             checked($checked, true, false) . ' name="' . esc_attr($option_name) .
                             '" value="' . esc_attr($k) . '" id="' . esc_attr($field['id'] . '_' . $k) .
                             '" /> ' . $v . '</label> ';
                }
                break;

            case 'select':
                $html .= '<select name="' . esc_attr($option_name) . '" id="' . esc_attr($field['id']) . '">';
                foreach ($field['options'] as $k => $v) {
                    $html .= '<option ' . selected($k, $option,
                            false) . ' value="' . esc_attr($k) . '">' . $v . '</option>';
                }
                $html .= '</select> ';
                break;

            case 'select_multi':
                $html .= '<select name="' . esc_attr($option_name) . '[]" id="' . esc_attr($field['id']) .
                         '" multiple="multiple">';
                foreach ($field['options'] as $k => $v) {
                    $selected = false;
                    if (in_array($k, $option)) {
                        $selected = true;
                    }
                    $html .= '<option ' . selected($selected, true,
                            false) . ' value="' . esc_attr($k) . '">' . $v . '</option>';
                }
                $html .= '</select> ';
                break;

            case 'button':
                $html .= '<button name="' . esc_attr($option_name) . '" id="' . esc_attr($field['id']) . '"> ' .
                         esc_attr($field['label']) . ' </button>';
                break;

            case 'image':
                $image_thumb = '';
                if ($option) {
                    $image_thumb = wp_get_attachment_thumb_url($option);
                }
                $html .= '<img id="' . $option_name . '_preview" class="image_preview" src="' . $image_thumb . '" /><br/>' . "\n";
                $html .= '<input id="' . $option_name . '_button" type="button" data-uploader_title="' . __('Upload an image',
                        EVENTAPPI_PLUGIN_NAME) . '" data-uploader_button_text="' . __('Use image',
                        EVENTAPPI_PLUGIN_NAME) . '" class="image_upload_button button" value="' . __('Upload new image',
                        EVENTAPPI_PLUGIN_NAME) . '" />' . "\n";
                $html .= '<input id="' . $option_name . '_delete" type="button" class="image_delete_button button" value="' . __('Remove image',
                        EVENTAPPI_PLUGIN_NAME) . '" />' . "\n";
                $html .= '<input id="' . $option_name . '" class="image_data_field" type="hidden" name="' . $option_name . '" value="' . $option . '"/><br/>' . "\n";
                break;

            case 'color':
                ?>
                <div class="color-picker" style="position:relative;">
                    <input type="text" name="<?php esc_attr_e($option_name, EVENTAPPI_PLUGIN_NAME);?>" class="color"
                           value="<?php esc_attr_e($option, EVENTAPPI_PLUGIN_NAME);?>"/>

                    <div style="position:absolute;background:#FFF;z-index:99;border-radius:100%;"
                         class="colorpicker"></div>
                </div>
                <?php
                break;

        }

        switch ($field['type']) {

            case 'radio':
            case 'select_multi':
                $html .= '<br/><span class="description">' . $field['description'] . '</span>';
                break;

            default:
                $html .= '<label for="' . esc_attr($field['id']) . '">' .
                         '<span class="description">' . $field['description'] . '</span>' .
                         '</label>';
                break;
        }
        echo $html;
    }
}