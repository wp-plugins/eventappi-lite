<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div id="ea-add-ticket-area">
    <form id="ea-add-ticket-form" method="post" action="">
        <input type="hidden" name="ea_nonce" value="<?php echo $data['nonce']; ?>" />

        <div id="ea-add-ticket-fields">
            <!-- Title and Description (Content) -->
            <div class="cmb_metabox">
                <div class="cmb-row">
                    <!-- Title -->
                    <div class="cmb-cell-5">
                        <div class="field">						
                            <div class="field-title">
                                <label for="eventappi_ticket_title">
                                    <?php _e('Title', EVENTAPPI_PLUGIN_NAME); ?>
                                    <span class="ea-error">*</span>
                                </label>
                            </div>

                            <div class="field-item" style="position: relative; ">
                                <input id="eventappi_ticket_title"
                                    required="required"
                                    type="text"
                                    name="eventappi_ticket_title"
                                    value="<?php echo $data['ticket_title']; ?>">
                            </div>
                        </div>  
                    </div>
                    <!-- Description -->
                    <div class="cmb-cell-5">
                        <div class="field">						
                            <div class="field-title">
                                <label for="eventappi_ticket_desc">
                                    <?php _e('Description', EVENTAPPI_PLUGIN_NAME); ?>
                                </label>
                            </div>

                            <div class="field-item" style="position: relative; ">
                                <textarea rows="3" id="eventappi_ticket_desc"
                                    name="eventappi_ticket_desc"></textarea>
                            </div>
                        </div>  
                    </div>            
                </div>
            </div>
            <!-- Meta Fields -->
            <?php echo $data['cmb_fields_area']; ?>
        </div>

        <input id="ea-add-new-ticket-btn" class="ea-update-btn ea-update-btn-big ea-update-ticket-btn" name="add"
               type="submit" value="<?php esc_attr_e('+ Add Ticket', EVENTAPPI_PLUGIN_NAME); ?>" />

        <input type="hidden" name="is_create_mode" value="1" />
        <input type="hidden" name="is_from_modal" value="1" />

        <div id="ea-ticket-adding" class="ea-hidden">
            <img src="<?php echo get_bloginfo('siteurl'); ?>/wp-admin/images/wpspin_light.gif" alt="" />
            <?php _e('Loading...', EVENTAPPI_PLUGIN_NAME); ?>
        </div>
        <div id="ea-ticket-added" class="ea-hidden ea-note-ok"></div>
    </form>
</div>