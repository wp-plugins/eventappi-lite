<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div id="eventappi-wrapper" class="wrap">

    <h2><?php echo $data['customPost']->labels->name; ?> - <?php echo $data['purchasesLabel']; ?>
        - <?php echo $data['eventPost']->post_title; ?></h2>
    <ul class="subsubsub">
        <?php foreach ($data['counters'] as $index => $counter) : ?>
            <li class="">
                <a href="<?php echo $data['postUrl']; ?><?php echo $counter['link']; ?>" class="">
                    <?php echo $counter['name']; ?> <span class="count">(<?php echo $counter['count']; ?>)</span>
                </a><?php if ($index != count($data['counters']) - 1) : ?> |<?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <form id="attendees-filter" action="<?php echo $data['postUrl']; ?>" method="get">
        <p class="search-box">
            <input type="hidden" id="attendee-search-url" name="su" value="<?php echo $data['postUrl']; ?>">
            <input type="text" id="attendee-search-input" name="s" value="<?php echo $data['s']; ?>">
            <input type="submit" name="" id="event-attendee-search-submit" class="button" value="<?php _e('Search Purchases', EVENTAPPI_PLUGIN_NAME); ?>">
        </p>
    </form>
    <table class="wp-list-table widefat fixed posts">
        <thead>
        <tr>
            <th scope="col" class="manage-column column-title"><span><?php _e('Purchaser First Name', EVENTAPPI_PLUGIN_NAME); ?></span></th>
            <th scope="col" class="manage-column column-title"><span><?php _e('Purchaser Last Name', EVENTAPPI_PLUGIN_NAME); ?></span></th>
            <th scope="col" class="manage-column column-title"><span><?php _e('Purchaser Email', EVENTAPPI_PLUGIN_NAME); ?></span></th>
            <th scope="col" class="manage-column column-title"><span><?php _e('Assign To', EVENTAPPI_PLUGIN_NAME); ?></span></th>
            <th scope="col" class="manage-column column-title"><span><?php _e('Action', EVENTAPPI_PLUGIN_NAME); ?></span></th>
        </tr>
        </thead>
        <tfoot>
        <tr>
            <th scope="col" class="manage-column column-title"><span><?php _e('Purchaser First Name', EVENTAPPI_PLUGIN_NAME); ?></span></th>
            <th scope="col" class="manage-column column-title"><span><?php _e('Purchaser Last Name', EVENTAPPI_PLUGIN_NAME); ?></span></th>
            <th scope="col" class="manage-column column-title"><span><?php _e('Purchaser Email', EVENTAPPI_PLUGIN_NAME); ?></span></th>
            <th scope="col" class="manage-column column-title"><span><?php _e('Assign To', EVENTAPPI_PLUGIN_NAME); ?></span></th>
            <th scope="col" class="manage-column column-title"><span><?php _e('Action', EVENTAPPI_PLUGIN_NAME); ?></span></th>
        </tr>
        </tfoot>
        <tbody id="the-list">
        <?php foreach ($data['purchases'] as $index => $purchase) : ?>
            <tr class="hentry <?php if ($index % 2 == 0) : ?>alternate <?php endif; ?>level-0">
                <td class="post-title page-title column-title"><strong><?php echo $purchase->first_name; ?></strong></td>
                <td><?php echo $purchase->last_name; ?></td>
                <td><?php echo $purchase->user_email; ?></td>
                <td>
                    <?php
                    $assignee = ($purchase->is_claimed == '1') ? __('CLAIMED', EVENTAPPI_PLUGIN_NAME) : $purchase->assigned_to;
                    ?>
                    <?php if (is_null($assignee)) : ?>
                        <input name="emailfor<?php echo $purchase->purchased_ticket_hash; ?>"
                               id="emailfor<?php echo $purchase->purchased_ticket_hash; ?>"
                               type="text" placeholder="e.g. email@add.com"
                               value="<?php echo $purchase->sent_to; ?>">
                    <?php else: ?>
                        <?php echo $assignee; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (is_null($assignee)) : ?>
                        <a href="<?php echo $data['postUrl']; ?>&send=<?php echo $purchase->id; ?>&email="
                           data-hash="<?php echo $purchase->purchased_ticket_hash; ?>" class="assign-ticket-purchase"><?php __('Send', EVENTAPPI_PLUGIN_NAME); ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>

        </tbody>
    </table>
</div>
