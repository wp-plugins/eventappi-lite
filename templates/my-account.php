<div id="eventappi-wrapper" class="wrap">
    <div id="my-account">
        <h2><?php echo $data['updateStatus']; ?></h2>
        <table border="0">
            <tr>
                <td><?php echo $data['avatar']; ?></td>
                <td><?php echo $data['actions']; ?><br /><a href="<?php echo wp_logout_url('/'); ?>"><?php _e('Logout', EVENTAPPI_PLUGIN_NAME); ?></a></td>
            </tr>
        </table>
        <hr>
        <h3><?php _e('Profile:', EVENTAPPI_PLUGIN_NAME); ?></h3>

        <form id="your-profile" method="post" novalidate="novalidate"<?php echo do_action('user_edit_form_tag'); ?>>
            <p>
                <label for="first_name"><?php _e('First Name', EVENTAPPI_PLUGIN_NAME); ?></label>
                <input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($data['user']->user_firstname); ?>" class="regular-text"/>
            </p>

            <p>
                <label for="last_name"><?php _e('Last Name', EVENTAPPI_PLUGIN_NAME); ?></label>
                <input type="text" name="last_name" id="last_name" class="regular-text" value="<?php echo esc_attr($data['user']->last_name); ?>"/>
            </p>

            <p>
                <label for="email"><?php _e('E-mail', EVENTAPPI_PLUGIN_NAME); ?> <span class="description"><?php _e('(required)', EVENTAPPI_PLUGIN_NAME); ?></span></label>
                <input type="email" name="email" id="email" class="regular-text ltr" value="<?php echo esc_attr($data['user']->data->user_email); ?>"/>
            </p>
            <?php $groupLabel = ''; ?>
            <?php foreach ($data['extraProfileFields'] as $method) : ?>
                <?php if (array_key_exists('group', $method) && $groupLabel != $method['group']) : ?>
                    <h4><?php echo $method['group']; ?></h4>
                    <?php $groupLabel = $method['group']; ?>
                <?php endif; ?>
                <label for="<?php echo $method['id']; ?>"><?php echo $method['name']; ?></label>
                <input type="text" name="<?php echo $method['id']; ?>" id="<?php echo $method['id']; ?>" value="<?php echo get_user_meta($data['user']->ID, $method['id'], true); ?>" class="regular-text ltr" />
            <?php endforeach; ?>

                <h4><?php _e('Password Change', EVENTAPPI_PLUGIN_NAME); ?></h4>
                <p>
                    <label for="pass1"><?php _e('New Password', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input type="password" name="pass1" id="pass1" class="regular-text" size="16" value="" autocomplete="off"/>
                </p>
                <p>
                    <label for="pass2"><?php _e('Repeat New Password', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input name="pass2" type="password" id="pass2" class="regular-text" size="16" value="" autocomplete="off"/>
                </p>

            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Update Profile', EVENTAPPI_PLUGIN_NAME); ?>">
        </form>


        <?php if (count($data['ticketList']) > 0) : ?>
            <h3 id="user-profile-tickets"><?php _e('Tickets:', EVENTAPPI_PLUGIN_NAME); ?></h3>
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

        <div id="dialog-form-send" title="<?php _e('Send Ticket', EVENTAPPI_PLUGIN_NAME); ?>">
            <p class="validateTips"><?php _e('Where should I send this ticket?', EVENTAPPI_PLUGIN_NAME); ?></p>

            <form id="form-send-ticket">
                <fieldset>
                    <label for="name"><?php _e('Name', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input type="text" name="name" id="name" value="" class="text ui-widget-content ui-corner-all">
                    <label for="email"><?php _e('Email', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input type="text" name="recipient" id="recipient" value="" class="text ui-widget-content ui-corner-all">
                    <input type="hidden" name="hash" id="hash">
                    <input type="submit" tabindex="-1" style="position:absolute; top:-1000px;">
                </fieldset>
            </form>
        </div>

        <div id="dialog-form-assign" title="<?php _e('Assign Ticket', EVENTAPPI_PLUGIN_NAME); ?>">
            <p class="validateTips"><?php _e('Who should I assign this ticket to?', EVENTAPPI_PLUGIN_NAME); ?></p>

            <p class="info"><?php echo sprintf(__('The ticket will be emailed to %s'), $data['user']->data->user_email); ?></p>

            <form id="form-assign-ticket">
                <fieldset>
                    <label for="name"><?php _e('Name', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input type="text" name="name" id="name" value="" class="text ui-widget-content ui-corner-all">
                    <?php foreach ($data['extraProfileFields'] as $method) : ?>
                        <?php if ($method['requireForTicket'] === true) : ?>
                            <label for="<?php echo $method['id']; ?>"><?php echo $method['name']; ?></label>
                            <input type="text" name="<?php echo $method['id']; ?>" id="<?php echo $method['id']; ?>" value="<?php echo get_user_meta($data['user']->ID, $method['id'], true); ?>" class="text ui-widget-content ui-corner-all" />
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="hidden" name="hash" id="hash">
                    <input type="submit" tabindex="-1" style="position:absolute; top:-1000px">
                </fieldset>
            </form>
        </div>

        <div id="dialog-form-claim" title="<?php _e('Claim Ticket', EVENTAPPI_PLUGIN_NAME); ?>">
            <p class="validateTips"><?php _e('Please complete the form to claim your ticket.'); ?></p>

            <p class="info"><?php echo sprintf(__('The ticket will be emailed to %s'), $data['user']->data->user_email); ?></p>

            <form id="form-claim-ticket">
                <fieldset>
                    <label for="name"><?php _e('Name', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input type="text" name="name" id="name" value="" class="text ui-widget-content ui-corner-all">
                    <?php foreach ($data['extraProfileFields'] as $method) : ?>
                        <?php if ($method['requireForTicket'] === true) : ?>
                            <label for="<?php echo $method['id']; ?>"><?php echo $method['name']; ?></label>
                            <input type="text" name="<?php echo $method['id']; ?>" id="<?php echo $method['id']; ?>" value="<?php echo get_user_meta($data['user']->ID, $method['id'], true); ?>" class="text ui-widget-content ui-corner-all" />
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="hidden" name="hash" id="hash" />
                    <input type="submit" tabindex="-1" style="position:absolute; top:-1000px" />
                </fieldset>
            </form>
        </div>
    </div>
</div>
