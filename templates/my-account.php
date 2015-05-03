<div id="eventappi-wrapper" class="wrap">
    <div id="my-account">
        <h2><?php echo $data['updateStatus']; ?></h2>
        <table border="0">
            <tr>
                <td><?php echo $data['avatar']; ?></td>
                <td><?php echo $data['actions']; ?><br><a href="<?php echo wp_logout_url('/'); ?>">Logout</a></td>
            </tr>
        </table>
        <hr>
        <h3>Profile:</h3>

        <form id="your-profile" method="post" novalidate="novalidate"<?php echo do_action('user_edit_form_tag'); ?>>
            <p>
                <label for="first_name">First Name</label>
                <input type="text" name="first_name" id="first_name"
                       value="<?php echo esc_attr($data['user']->user_firstname); ?>" class="regular-text"/>
            </p>

            <p>
                <label for="last_name">Last Name</label>
                <input type="text" name="last_name" id="last_name" class="regular-text"
                       value="<?php echo esc_attr($data['user']->last_name); ?>"/>
            </p>

            <p>
                <label for="email">E-mail <span class="description">(required)</span></label>
                <input type="email" name="email" id="email" class="regular-text ltr"
                       value="<?php echo esc_attr($data['user']->data->user_email); ?>"/>
            </p>
            <?php $groupLabel = ''; ?>
            <?php foreach ($data['extraProfileFields'] as $method) : ?>
                <?php if (array_key_exists('group', $method) && $groupLabel != $method['group']) : ?>
                    <h4><?php echo $method['group']; ?></h4>
                    <?php $groupLabel = $method['group']; ?>
                <?php endif; ?>
                <label for="<?php echo $method['id']; ?>"><?php echo $method['name']; ?></label>
                <input type="text" name="<?php echo $method['id']; ?>" id="<?php echo $method['id']; ?>"
                       value="<?php echo get_user_meta($data['user']->ID, $method['id'], true); ?>"
                       class="regular-text ltr"/>
            <?php endforeach; ?>

                <h4>Password Change</h4>
                <p>
                    <label for="pass1">New Password</label>
                    <input type="password" name="pass1" id="pass1" class="regular-text" size="16" value=""
                           autocomplete="off"/>
                </p>
                <p>
                    <label for="pass2">Repeat New Password</label>
                    <input name="pass2" type="password" id="pass2" class="regular-text" size="16" value=""
                           autocomplete="off"/>
                </p>

            <input type="submit" name="submit" id="submit" class="button button-primary" value="Update Profile">
        </form>


        <?php if (count($data['ticketList']) > 0) : ?>
            <h3 id="user-profile-tickets">Tickets:</h3>
            <table id="eventappi-ticket-list">
                <?php foreach ($data['ticketList'] as $index => $ticket) : ?>
                    <tr>
                        <td>
                            <?php echo $ticket['ticketName']; ?> (<?php echo $ticket['eventTitle']; ?>)<br>
                            <em><?php echo $ticket['ticketDesc']; ?></em>
                        </td>
                        <td><?php echo $ticket['status']; ?><?php echo $ticket['actionLinks']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <div id="dialog-form-send" title="Send Ticket">
            <p class="validateTips">Where should I send this ticket?</p>

            <form id="form-send-ticket">
                <fieldset>
                    <label for="name">Name</label>
                    <input type="text" name="name" id="name" value="" class="text ui-widget-content ui-corner-all">
                    <label for="email">Email</label>
                    <input type="text" name="recipient" id="recipient" value=""
                           class="text ui-widget-content ui-corner-all">
                    <input type="hidden" name="hash" id="hash">
                    <input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
                </fieldset>
            </form>
        </div>

        <div id="dialog-form-assign" title="Assign Ticket">
            <p class="validateTips">Who should I assign this ticket to?</p>

            <p class="info">The ticket will be emailed to <?php echo $data['user']->data->user_email; ?></p>

            <form id="form-assign-ticket">
                <fieldset>
                    <label for="name">Name</label>
                    <input type="text" name="name" id="name" value="" class="text ui-widget-content ui-corner-all">
                    <?php foreach ($data['extraProfileFields'] as $method) : ?>
                        <?php if ($method['requireForTicket'] === true) : ?>
                            <label for="<?php echo $method['id']; ?>"><?php echo $method['name']; ?></label>
                            <input type="text" name="<?php echo $method['id']; ?>" id="<?php echo $method['id']; ?>"
                                   value="<?php echo get_user_meta($data['user']->ID, $method['id'], true); ?>"
                                   class="text ui-widget-content ui-corner-all">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="hidden" name="hash" id="hash">
                    <input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
                </fieldset>
            </form>
        </div>

        <div id="dialog-form-claim" title="Claim Ticket">
            <p class="validateTips">Please complete the form to claim your ticket.</p>

            <p class="info">The ticket will be emailed to <?php echo $data['user']->data->user_email; ?></p>

            <form id="form-claim-ticket">
                <fieldset>
                    <label for="name">Name</label>
                    <input type="text" name="name" id="name" value="" class="text ui-widget-content ui-corner-all">
                    <?php foreach ($data['extraProfileFields'] as $method) : ?>
                        <?php if ($method['requireForTicket'] === true) : ?>
                            <label for="<?php echo $method['id']; ?>"><?php echo $method['name']; ?></label>
                            <input type="text" name="<?php echo $method['id']; ?>" id="<?php echo $method['id']; ?>"
                                   value="<?php echo get_user_meta($data['user']->ID, $method['id'], true); ?>"
                                   class="text ui-widget-content ui-corner-all">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="hidden" name="hash" id="hash">
                    <input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
                </fieldset>
            </form>
        </div>
    </div>
</div>
