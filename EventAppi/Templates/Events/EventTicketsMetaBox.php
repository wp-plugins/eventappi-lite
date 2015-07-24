<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ($data['not_publish'] && empty($data['tickets'])) {
    _e('Please publish this event before adding tickets to it.', EVENTAPPI_PLUGIN_NAME);
} elseif(! $data['has_api_id']) {
    _e('You can not add tickets because the event does not have an API ID set. Please update this event and if this does not fix the problem, consider contacting the administrator.');
} else {
    // We have to check if the total number of tickets per event was reached - LITE
    if ($data['used_tickets'] < $data['max_tickets']) {
?>
    <div class="ea-add-new-ticket-btn">
        <a href="#TB_inline?width=800&height=850&inlineId=ea-add-ticket-area" class="ea-add-btn thickbox">
            <?php _e('+ Create New Ticket', EVENTAPPI_PLUGIN_NAME); ?>
        </a>
        <span id="ea-sort-spinner" class="spinner"></span>
    </div>

    <div id='ea-ticket-added' class='ea-hidden ea-note-ok'><?php _e('The new ticket was added.', EVENTAPPI_PLUGIN_NAME); ?></div>

    <?php
    } else {
        echo '<p>'.sprintf(__('You have reached the maximum number of %d tickets per event.'), $data['max_tickets']).'</p>';
    }
    ?>

    <div id="accordion-event-tickets">
    <?php
    if( ! empty($data['tickets']) ) {
        foreach($data['tickets'] as $val) {
            echo $val->panel;
        }
        ?>
    </div>
    <?php
    }
}
