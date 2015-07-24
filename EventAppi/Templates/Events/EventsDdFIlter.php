<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( !empty($data['events']) ) {
?>
    <div class="alignleft actions">
        <select class="chosen" name="event_id" id="event_ids">
            <option value=""><?php _e('ALL EVENTS', EVENTAPPI_PLUGIN_NAME); ?></option>
            <?php
            $required = isset($data['event_id']);

            foreach($data['events'] as $val) {
                if($val->post_title == '') {
                    continue;
                }

                if($data['event_id'] == $val->ID) {
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
    </div>
<?php
}
?>