<?php
if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (empty($data['events'])) {
    _e('There are no EventAppi Events. Please add at least one event, before creating any tickets.',
        EVENTAPPI_PLUGIN_NAME);
} else {
    ?>
    <input type="hidden" name="<?php echo EVENTAPPI_POST_NAME; ?>_nonce"
           value="<?php echo wp_create_nonce(plugin_basename(__FILE__)); ?>"/>
    <select class="chosen" name="event_id" id="event_ids">
        <option value=""><?php _e('-- SELECT EVENT --', EVENTAPPI_PLUGIN_NAME); ?></option>
        <?php
        $required = isset($data['event_id']);

        foreach ($data['events'] as $val) {
            if ($val->post_title == '') {
                continue;
            }

            if ($data['event_id'] == $val->ID) {
                $selected = 'selected="selected"';
            } else {
                $selected = '';
            }
            ?>
            <option <?php echo $selected; ?> value="<?php echo $val->ID; ?>"><?php echo $val->post_title; ?></option>
        <?php
        }
        ?>
    </select>

    <input type="hidden" id="event_id_cur" value="<?php echo $data['event_id']; ?>"/>

    <div class="alignleft actions" id="event-ticket-max-error">
        <?php _e('Please choose another event as the selected one already has the maximum number of tickets allowed.',
            EVENTAPPI_PLUGIN_NAME); ?>
    </div>
    <div class="ea-clear"></div>
<?php
}
?>
<input type="hidden" name="is_create_mode" value="<?php echo $data['is_create_mode']; ?>"/>
