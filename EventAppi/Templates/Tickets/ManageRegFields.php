<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! isset($_GET['post']) && ($_GET['post_type'] != 'eventappi_ticket') ) { // Useless for the Dashboard as we have the meta box title
?>
<div class="cmb_metabox">
    <div class="field-title">
        <label><?php _e('Ticket Registraton Fields Management (Add / Edit / Delete)', EVENTAPPI_PLUGIN_NAME); ?></label>
    </div>
</div>
<?php
}

if($data['show']) {
?>
    <div class="ea-add-new-ticket-reg-field-btn">
        <a data-ea-ticket-id="<?php echo $data['ticket_id']; ?>" href="#TB_inline?width=800&height=850&inlineId=ea-add-ticket-reg-field-area" class="ea-add-btn ea-add-new-reg-field-btn thickbox">
            <?php _e('+ Add New Registration Field', EVENTAPPI_PLUGIN_NAME); ?>
        </a>
        <div class="spinner ea-hidden"><img src="<?php echo admin_url().'/images/spinner.gif'; ?>" alt="" /></div>
    </div>

    <div id='ea-added-reg-field-<?php echo $data['ticket_id']; ?>' class='ea-hidden ea-note-ok'><?php _e('The new registration field was added.', EVENTAPPI_PLUGIN_NAME);?></div>

    <div class='accordion-tickets-reg-fields' id="accordion-tickets-reg-fields-<?php echo $data['ticket_id']; ?>" data-ea-ticket-id="<?php echo $data['ticket_id']; ?>">
        <?php
        if( ! empty($data['fields']) ) {
            foreach($data['fields'] as $fieldPos => $field) {
                echo $field['panel'];
            }
        }
        ?>
    </div>

    <div id="ea-reg-fields-preview-action-<?php echo $data['ticket_id']; ?>"
         class="alignright ea-reg-fields-preview-action actions<?php if(empty($data['fields'])) { echo ' ea-hidden'; } ?>">
            <br />
            <a target="_blank" href="<?php echo $data['prev_reg_fields_page'].'?ticket_id='.$data['ticket_id']; ?>"><strong><?php _e('PREVIEW Ticket Registration Fields FORM', EVENTAPPI_PLUGIN_NAME); ?></strong></a>
            <br />
    </div>
<?php
} else {
    _e($data['message'], EVENTAPPI_PLUGIN_NAME);
}
?>
<div class="ea-clear"></div>