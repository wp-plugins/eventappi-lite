<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div id="edit-ticket-area-<?php echo $data['ticket_id']; ?>">
    <div id="ticket-fields-<?php echo $data['ticket_id']; ?>">
        <input type="hidden" name="ea_nonce" value="<?php echo $data['nonce']; ?>" />
        
        <!-- Title and Description (Content) -->
        <div class="cmb_metabox">
            <div class="cmb-row">
                <!-- Title -->
                <div class="cmb-cell-5">
                    <div class="field">						
                        <div class="field-title">
                            <label for="eventappi_ticket_title_<?php echo $data['ticket_id']; ?>">
                                <?php _e('Title', EVENTAPPI_PLUGIN_NAME); ?> <span class="ea-error">*</span>
                            </label>
                        </div>

                        <div class="field-item" style="position: relative; ">
                            <input id="eventappi_ticket_title_<?php echo $data['ticket_id']; ?>"
                                type="text" name="eventappi_ticket_title" value="<?php echo $data['ticket_title']; ?>">
                        </div>
                    </div>  
                </div>
                <!-- Description -->
                <div class="cmb-cell-5">
                    <div class="field">						
                        <div class="field-title">
                            <label for="eventappi_ticket_desc_<?php echo $data['ticket_id']; ?>">
                                <?php _e('Description', EVENTAPPI_PLUGIN_NAME); ?>
                            </label>
                        </div>

                        <div class="field-item" style="position: relative; ">
                            <textarea rows="3" id="eventappi_ticket_desc_<?php echo $data['ticket_id']; ?>"
                                name="eventappi_ticket_desc"><?php echo $data['ticket_desc']; ?></textarea>
                        </div>
                    </div>  
                </div>            
            </div>
        </div>
        <!-- Meta Fields -->
        <?php echo $data['cmb_fields_area']; ?>

        <!-- Registration Fields Management -->
        <?php echo $data['reg_fields_area']; ?>        
    </div>
    
    <hr />
    
    <button class="ea-update-btn ea-update-ticket-btn" data-ticket-id="<?php echo $data['ticket_id']; ?>" name="update">
        <?php _e('Update Ticket', EVENTAPPI_PLUGIN_NAME); ?>
    </button>
    
    <div class="alignright actions">
        <a data-ticket-id="<?php echo $data['ticket_id']; ?>"
           data-confirm-msg="<?php _e('Are you sure you want to delete this ticket?', EVENTAPPI_PLUGIN_NAME); ?>"
           class="ea-remove ea-remove-ticket" href="#">
            <?php _e('Delete Ticket', EVENTAPPI_PLUGIN_NAME); ?>
        </a>
    </div>

    <div id="ticket-updating-<?php echo $data['ticket_id']; ?>" class="ea-hidden">
        <img src="<?php echo get_bloginfo('siteurl'); ?>/wp-admin/images/wpspin_light.gif" alt="" />
        <?php _e('Loading...', EVENTAPPI_PLUGIN_NAME); ?>
    </div>
    <div id="ticket-updated-<?php echo $data['ticket_id']; ?>" class="ea-hidden ea-note-ok"></div>
</div>