<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div id="eventappi-wrapper" class="wrap">
    <h2><?php echo $data['customPost']->labels->name; ?> - <?php echo $data['attendeesLabel']; ?>
        - <?php echo $data['eventPost']->post_title; ?></h2>
    <ul class="subsubsub">
        <?php foreach ($data['counters'] as $index => $counter) : ?>
            <li>
                <a href="<?php echo $data['postUrl']; ?><?php echo $counter['link']; ?>">
                    <?php echo $counter['name']; ?> <span class="count">(<?php echo $counter['count']; ?>)</span>
                </a><?php if ($index != count($data['counters']) - 1) : ?> |<?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="tablenav top">
        <div class="alignleft actions">
            <a href="<?php echo $data['exportUrl']; ?>">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export Attendees', EVENTAPPI_PLUGIN_NAME); ?>
            </a>
        </div>
        <div class="alignright actions">
            <form id="attendees-filter" action="<?php echo $data['postUrl']; ?>" method="get">
                <p class="search-box">
                    <input type="hidden" id="attendee-search-url" name="su" value="<?php echo $data['postUrl']; ?>">
                    <input type="text" id="attendee-search-input" name="s" value="<?php echo $data['s']; ?>">
                    <input type="submit" name="" id="event-attendee-search-submit" class="button" value="<?php _e('Search Event Attendeess', EVENTAPPI_PLUGIN_NAME); ?>" />
                </p>
            </form>
        </div>
        <br class="clear">
    </div>
    <table class="wp-list-table widefat fixed posts">
        <thead>
        <tr>
            <th scope="col" class="manage-column column-title"><span><?php _e('Purchaser First Name', EVENTAPPI_PLUGIN_NAME); ?></span></th>
            <th scope="col" class="manage-column column-title"><span><?php _e('Purchaser Last Name', EVENTAPPI_PLUGIN_NAME); ?></span></th>
            <th scope="col" class="manage-column column-title"><span><?php _e('Purchaser Email', EVENTAPPI_PLUGIN_NAME); ?></span></th>
            <th scope="col" class="manage-column column-title"><span><?php _e('Assigned To', EVENTAPPI_PLUGIN_NAME); ?></span></th>
            <th scope="col" class="manage-column column-title"><span><?php _e('Status', EVENTAPPI_PLUGIN_NAME); ?></span></th>
        </tr>
        </thead>
        <tfoot>
        <tr>
            <th scope="col" class="manage-column column-title"><span><?php _e('Purchaser First Name', EVENTAPPI_PLUGIN_NAME); ?></span></th>
            <th scope="col" class="manage-column column-title"><span><?php _e('Purchaser Last Name', EVENTAPPI_PLUGIN_NAME); ?></span></th>
            <th scope="col" class="manage-column column-title"><span><?php _e('Purchaser Email', EVENTAPPI_PLUGIN_NAME); ?></span></th>
            <th scope="col" class="manage-column column-title"><span><?php _e('Assigned To', EVENTAPPI_PLUGIN_NAME); ?></span></th>
            <th scope="col" class="manage-column column-title"><span><?php _e('Status', EVENTAPPI_PLUGIN_NAME); ?></span></th>
        </tr>
        </tfoot>
        <tbody id="the-list">
        <?php foreach ($data['attendees'] as $index => $attendee) : ?>
            <tr class="hentry <?php if ($index % 2 == 0) : ?>alternate <?php endif; ?>level-0">
                <td class="post-title page-title column-title">
                    <strong><?php echo $attendee->first_name; ?></strong>
                </td>
                <td><?php echo $attendee->last_name; ?></td>
                <td><?php echo $attendee->user_email; ?></td>
                <td>
                    <?php echo ($attendee->is_claimed == '1') ? __('CLAIMED', EVENTAPPI_PLUGIN_NAME) : $attendee->assigned_to; ?>
                </td>
                <td>
                    <?php
                    $checkInState = ($attendee->is_checked_in === '1') ? 'Out' : 'In';
                    $checkInStateText = ($attendee->is_checked_in === '1') ? __('Check Out', EVENTAPPI_PLUGIN_NAME) : __('Check In', EVENTAPPI_PLUGIN_NAME);
                    ?>
                    <a href="<?php echo $data['postUrl']; ?>&check=<?php echo $attendee->purchased_ticket_hash; ?>&state=<?php echo $checkInState; ?>">
                        <?php echo $checkInStateText; ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>