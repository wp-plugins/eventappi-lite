<?php
/*
* This is a table row appended to the EDIT EVENT taxonomy page
* showing information about the User associated with the Venue
* */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<tr class="form-field term-slug-wrap">
    <th scope="row">
        <label><?php _e('Event Organiser', EVENTAPPI_PLUGIN_NAME); ?></label>

        <?php
        if(isset($data->data->ID)) {
        ?>
            <p class="description"><?php _e('User ID:', EVENTAPPI_PLUGIN_NAME); ?> <?php echo $data->data->ID; ?></p>
        <?php
        }
        ?>

    </th>
    <td>
        <?php        
        if($data->first_name || $data->last_name || $data->user_email || $data->data->ID) {
            $userDetails = $data->first_name .' '. $data->last_name;

            if($data->user_email) { // We should have it all the time!
                $userDetails .= '&#10095; (<a href="mailto:'.$data->user_email.'">'.$data->user_email.'</a>)';  
            }

            echo $userDetails;

            if( ! $data->user_email ) {
            ?>
            <p><span class="ea-error"><?php _e('* This event organiser does not have any email address on his account.', EVENTAPPI_PLUGIN_NAME); ?></span></p>
            <?php
            }
            ?>

            <a href="user-edit.php?user_id=<?php echo $data->data->ID; ?>"><?php _e('Edit User', EVENTAPPI_PLUGIN_NAME); ?></a>
        <?php
        } else {
        ?>
            <p><span class="ea-error"><?php _e('This venue is not associated with any event organiser.', EVENTAPPI_PLUGIN_NAME); ?></span></p>
        <?php
        }
        ?>
    </td>
</tr>