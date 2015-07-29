<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ($data['is_guest']) {
?>
    <div class="ea-note-error"><?php echo __('You have to be logged in to access this page.', EVENTAPPI_PLUGIN_NAME); ?></div>
<?php
} else {
    // Ticket ID was appended - Show basic information
    if ($data['ticket_name'] != '') {
    ?>
    <div class="ea-note-ok">
        <ul>
            <?php
            if ($data['event_name']) {
            ?>
            <li><?php _e('Event:', EVENTAPPI_PLUGIN_NAME); ?> <strong><?php echo $data['event_name']; ?></strong></li>
            <?php
            }
            ?>
            <li><?php _e('Ticket:', EVENTAPPI_PLUGIN_NAME); ?> <strong><?php echo $data['ticket_name']; ?></strong></li>
        </ul>
    </div>
    <?php
    }
    ?>
    <p>
        <small><?php _e('* The first fields are the basic/required ones, followed by the extra/custom ones.', EVENTAPPI_PLUGIN_NAME); ?></small>
    </p>
    <?php
    // There are fields to show
    if (! empty($data['fields'])) {
    ?>
        <div id="ea-reg-fields-wrap">
            <?php
            foreach ($data['fields'] as $val) {
                $id = $val['id'];
                $title = $val['title'];
                $type = $val['type'];
                $typeAttr = $val['type_attr'];
                $req = $val['req'];
                $attrsList = $val['attrs_list'];
                $options = $val['options'];
            ?>   
            <div class="field-wrap">
                <?php
                if ($val['no_label'] === false) {
                ?>
                    <label for="<?php echo $id; ?>"><?php echo $title; ?>
                        <?php if ($req == 1) { ?><em class="req">*</em><?php } ?>
                    </label>
                <?php
                } else {
                ?>
                    <div><?php echo $title; ?> <?php if ($req == 1) { ?><em class="req">*</em><?php } ?></div>
                <?php
                }
                ?>

                <?php
                // Input & Input (Date)
                if (in_array($type, array('input_text', 'input_date', 'input_email'))) {
                ?>
                    <div>
                        <input <?php echo $attrsList; ?>
                            type="<?php echo $typeAttr; ?>" name="<?php echo $name; ?>"
                            id="<?php echo $id; ?>" value="" />
                    </div>
                <?php
                // Select & Multiple Selections
                } elseif (in_array($type, array('select', 'select_m'))) {
                ?>
                    <div>
                        <select <?php echo $attrsList; ?> name="<?php echo $name; ?>" id="<?php echo $id; ?>">
                            <option value="" style="color: #777;">[select]</option>
                            <?php
                            if (! empty($options)) {
                                foreach ($options as $option) {
                                ?>       
                                    <option value="<?php echo $option; ?>"><?php echo $option; ?></option>
                                <?php
                                }
                            }
                            ?>
                        </select>                
                    </div>
                <?php
                // Textarea
                } elseif ($type == 'textarea') {
                ?>
                    <div><textarea <?php echo $attrsList; ?> name="<?php echo $name; ?>" id="<?php echo $id; ?>">
                        </textarea></div>
                <?php
                // Radios
                } elseif ($type == 'radio') {
                    echo $val['radios_area'];
                // Checkboxes
                } elseif ($type == 'checkbox') {
                    echo $val['checkboxes_area'];
                } elseif ($type == 'file') {
                ?>
                    <div>
                        <input <?php echo $attrsList; ?>
                            type="file" name="<?php echo $name; ?>" id="<?php echo $id; ?>" />
                        &nbsp; <a href="#" class="ea-reg-file-clear"><?php _e('(Clear)', EVENTAPPI_PLUGIN_NAME); ?></a>
                    </div>
                <?php
                }
                ?>
            </div>
            <?php
            }
            ?>
        </div>
    <?php
    // In this case the ticket ID was requested but there are no registration fields added
    } elseif (empty($data['fields']) && $data['ticket_name']) {
    ?>
        <div class="ea-note-error"><?php _e('There are no registration fields associated with the ticket.', EVENTAPPI_PLUGIN_NAME); ?></div>
    <?php
    // No Ticket ID requested
    } else {
    ?>
        <div class="ea-note-error"><?php printf(__('You can not preview any registration fields without appending the `ticket_id` parameter to the URL (e.g. /eventappi-preview-ticket-registration-fields/?ticket_id=%sTICKET_ID_HERE%s).', EVENTAPPI_PLUGIN_NAME), '<small><em>', '</em></small>'); ?></div>
    <?php
    }
}
?>