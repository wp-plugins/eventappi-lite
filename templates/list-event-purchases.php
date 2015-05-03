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
            <input type="submit" name="" id="event-attendee-search-submit" class="button" value="Search Purchases">
        </p>
    </form>
    <table class="wp-list-table widefat fixed posts">
        <thead>
        <tr>
            <th scope="col" id="title" class="manage-column column-title" style=""><span>Purchaser First Name</span>
            </th>
            <th scope="col" id="title" class="manage-column column-title" style=""><span>Purchaser Last Name</span>
            </th>
            <th scope="col" id="title" class="manage-column column-title" style=""><span>Purchaser Email</span></th>
            <th scope="col" id="title" class="manage-column column-title" style=""><span>Assign To</span></th>
            <th scope="col" id="title" class="manage-column column-title" style=""><span>Action</span></th>
        </tr>
        </thead>
        <tfoot>
        <tr>
            <th scope="col" id="title" class="manage-column column-title" style=""><span>Purchaser First Name</span>
            </th>
            <th scope="col" id="title" class="manage-column column-title" style=""><span>Purchaser Last Name</span>
            </th>
            <th scope="col" id="title" class="manage-column column-title" style=""><span>Purchaser Email</span></th>
            <th scope="col" id="title" class="manage-column column-title" style=""><span>Assign To</span></th>
            <th scope="col" id="title" class="manage-column column-title" style=""><span>Action</span></th>
        </tr>
        </tfoot>
        <tbody id="the-list">
        <?php foreach ($data['purchases'] as $index => $purchase) : ?>
            <tr class="hentry <?php if ($index % 2 == 0) : ?>alternate <?php endif; ?>level-0">
                <td class="post-title page-title column-title">
                    <strong><?php echo $purchase->first_name; ?></strong>
                </td>
                <td class=""><?php echo $purchase->last_name; ?></td>
                <td class=""><?php echo $purchase->user_email; ?></td>
                <td class="">
                    <?php
                    $assignee = ($purchase->isClaimed == '1') ? 'CLAIMED' : $purchase->assignedTo;
                    ?>
                    <?php if (is_null($assignee)) : ?>
                        <input name="emailfor<?php echo $purchase->purchased_ticket_hash; ?>"
                               id="emailfor<?php echo $purchase->purchased_ticket_hash; ?>"
                               type="text" placeholder="e.g. email@add.com"
                               value="<?php echo $purchase->sentTo; ?>">
                    <?php else: ?>
                        <?php echo $assignee; ?>
                    <?php endif; ?>
                </td>
                <td class="">
                    <?php if (is_null($assignee)) : ?>
                        <a href="<?php echo $data['postUrl']; ?>&send=<?php echo $purchase->id; ?>&email="
                           data-hash="<?php echo $purchase->purchased_ticket_hash; ?>" class="assign-ticket-purchase">Send
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>

        </tbody>
    </table>
</div>
