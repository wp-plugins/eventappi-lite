<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="group">
    <h3 data-ea-ticket-id="<?php echo $data['ticket_id']; ?>" class="ea-accordion-title main"><span class="ui-icon ui-icon-arrowthick-2-n-s"></span> <?php echo $data['ticket_title']; ?></h3>
    <div class="ticket-area" aria-hidden="true" style="display: none;">
        <div class="loading hidden"><img src="<?php echo get_bloginfo('siteurl'); ?>/wp-admin/images/wpspin_light.gif" alt="" /> <?php _e('Loading...', EVENTAPPI_PLUGIN_NAME); ?></div>
        <div class="event-ticket" data-id="<?php echo $data['ticket_id']; ?>">
            <?php echo $data['edit_ticket_area']; ?>
        </div>
    </div>
</div>