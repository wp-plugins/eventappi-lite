<div id="eventappi-wrapper" class="wrap">

    <h2><?php echo $data['customPost']->labels->name; ?> - <?php echo $data['attendeesLabel']; ?>
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
    <div class="tablenav top">
        <div class="alignleft actions">
            <a href="<?php echo $data['exportUrl']; ?>">
                <span class="dashicons dashicons-download"></span>
                Export Attendees
            </a>
        </div>
        <div class="alignright actions">
            <form id="attendees-filter" action="<?php echo $data['postUrl']; ?>" method="get">
                <p class="search-box">
                    <input type="hidden" id="attendee-search-url" name="su" value="<?php echo $data['postUrl']; ?>">
                    <input type="text" id="attendee-search-input" name="s" value="<?php echo $data['s']; ?>">
                    <input type="submit" name="" id="event-attendee-search-submit" class="button"
                           value="Search Event Attendeess">
                </p>
            </form>
        </div>
        <br class="clear">
    </div>
    <table class="wp-list-table widefat fixed posts">
        <thead>
        <tr>
            <th scope="col" id="title" class="manage-column column-title"><span>Purchaser First Name</span></th>
            <th scope="col" id="title" class="manage-column column-title"><span>Purchaser Last Name</span></th>
            <th scope="col" id="title" class="manage-column column-title"><span>Purchaser Email</span></th>
            <th scope="col" id="title" class="manage-column column-title"><span>Assigned To</span></th>
            <th scope="col" id="title" class="manage-column column-title"><span>Additional Attendee Data</span></th>
            <th scope="col" id="title" class="manage-column column-title"><span>Status</span></th>
        </tr>
        </thead>
        <tfoot>
        <tr>
            <th scope="col" id="title" class="manage-column column-title"><span>Purchaser First Name</span></th>
            <th scope="col" id="title" class="manage-column column-title"><span>Purchaser Last Name</span></th>
            <th scope="col" id="title" class="manage-column column-title"><span>Purchaser Email</span></th>
            <th scope="col" id="title" class="manage-column column-title"><span>Assigned To</span></th>
            <th scope="col" id="title" class="manage-column column-title"><span>Additional Attendee Data</span></th>
            <th scope="col" id="title" class="manage-column column-title"><span>Status</span></th>
        </tr>
        </tfoot>
        <tbody id="the-list">
        <?php foreach ($data['attendees'] as $index => $attendee) : ?>
            <tr class="hentry <?php if ($index % 2 == 0) : ?>alternate <?php endif; ?>level-0">
                <td class="post-title page-title column-title">
                    <strong><?php echo $attendee->first_name; ?></strong>
                </td>
                <td class=""><?php echo $attendee->last_name; ?></td>
                <td class=""><?php echo $attendee->user_email; ?></td>
                <td class="">
                    <?php echo ($attendee->isClaimed == '1') ? 'CLAIMED' : $attendee->assignedTo; ?>
                </td>
                <td class="">
                    <?php
                    $extraInfo = '';
                    if (!empty($attendee->additionalAttendeeData)) {
                        $additionalData = unserialize($attendee->additionalAttendeeData);
                        foreach ($data['extraDataFields'] as $i => $field) {
                            if ($i > 0) {
                                $extraInfo .= '<br>';
                            }
                            $extraInfo .= '<em>' . $field['name'] . '</em><br>' . $additionalData[$field['id']];
                        }
                    }
                    ?><?php echo $extraInfo; ?>
                </td>
                <td class="">
                    <?php $checkInState = ($attendee->isCheckedIn === '1') ? 'Out' : 'In' ?>
                    <a href="<?php echo $data['postUrl']; ?>&check=<?php echo $attendee->purchased_ticket_hash; ?>&state=<?php echo $checkInState; ?>">
                        Check <?php echo $checkInState; ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
