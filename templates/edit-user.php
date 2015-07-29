<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div id="eventappi-wrapper"<?php if(!$data['msg_error']) { ?> class="wrap edit-user"<?php } ?>>
    <?php
    if($data['msg_error']) {
    ?>
        <div class="ea-note-error"><?php echo $data['msg_error']; ?></div>
    <?php
    } else {
    ?>   
      <div id="edit-user">   
        <h2><?php echo $data['updateStatus']; ?></h2>
        <div>
            <div class="alignleft title"><h3><?php _e('Profile:', EVENTAPPI_PLUGIN_NAME); ?></h3></div>
            <div class="alignright"><?php echo $data['avatar']; ?></div>
        </div>
        <div class="clear"></div>
        
        <form id="your-profile" method="post" <?php echo do_action('user_edit_form_tag'); ?>>
            <?php
            if(isset($_GET['id'])) {
            ?>
            <input type="hidden" name="<?php echo EVENTAPPI_PLUGIN_NAME; ?>_user_id" value="<?php echo (int)$_GET['id']; ?>" />
            <?php
            }
            ?>
            <p>
                <label for="first_name"><?php _e('First Name', EVENTAPPI_PLUGIN_NAME); ?></label>
                <input type="text" name="<?php echo EVENTAPPI_PLUGIN_NAME; ?>_first_name" id="<?php echo EVENTAPPI_PLUGIN_NAME; ?>_first_name" value="<?php echo esc_attr($data['user']->user_firstname); ?>" class="regular-text"/>
            </p>

            <p>
                <label for="last_name"><?php _e('Last Name', EVENTAPPI_PLUGIN_NAME); ?></label>
                <input type="text" name="<?php echo EVENTAPPI_PLUGIN_NAME; ?>_last_name" id="<?php echo EVENTAPPI_PLUGIN_NAME; ?>_last_name" class="regular-text" value="<?php echo esc_attr($data['user']->last_name); ?>"/>
            </p>

            <p>
                <label for="email"><?php _e('E-mail', EVENTAPPI_PLUGIN_NAME); ?> <span class="description"><?php _e('(required)', EVENTAPPI_PLUGIN_NAME); ?></span></label>
                <input type="email" required="required" name="<?php echo EVENTAPPI_PLUGIN_NAME; ?>_email" class="regular-text ltr" value="<?php echo esc_attr($data['user']->data->user_email); ?>"/>
            </p>
            <?php $groupLabel = ''; ?>
            <?php foreach ($data['extraProfileFields'] as $method) : ?>
                <?php if (array_key_exists('group', $method) && $groupLabel != $method['group']) : ?>
                    <h4><?php echo $method['group']; ?></h4>
                    <?php $groupLabel = $method['group']; ?>
                <?php endif; ?>
                <label for="<?php echo $method['id']; ?>"><?php echo $method['name']; ?></label>
                <input type="text" name="<?php echo $method['id']; ?>" value="<?php echo get_user_meta($data['user']->ID, $method['id'], true); ?>" class="regular-text ltr" />
            <?php endforeach; ?>

                <h4><?php _e('Password Change', EVENTAPPI_PLUGIN_NAME); ?></h4>
                <p>
                    <label for="eventappi_pass1"><?php _e('New Password', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input type="password" name="<?php echo EVENTAPPI_PLUGIN_NAME; ?>_pass1" id="eventappi_pass1" class="regular-text" size="16" value="" autocomplete="off"/>
                </p>
                <p>
                    <label for="eventappi_pass2"><?php _e('Repeat New Password', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input type="password" name="<?php echo EVENTAPPI_PLUGIN_NAME; ?>_pass2" id="eventappi_pass2" class="regular-text" size="16" value="" autocomplete="off"/>
                </p>
            <br /><br />
            
            <p>
                <label for="eventappi_notes"><?php _e('Notes', EVENTAPPI_PLUGIN_NAME); ?></label>
                <?php wp_editor( $data['notes'], EVENTAPPI_PLUGIN_NAME.'_notes', array('editor_height' => 200) ); ?>
            </p>
            
            <input type="submit" name="submit" class="button button-primary" value="<?php _e('Update Profile', EVENTAPPI_PLUGIN_NAME); ?>">
            <input type="hidden" name="ea_nonce" value="<?php echo $data['nonce']; ?>" />
        </form>
      </div>
    <?php
    }
    ?>
</div>
