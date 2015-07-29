<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div id="ea-timezone-show" <?php if( ! $data['no_edit'] ) { echo 'class="ea-hidden"'; } ?>><?php echo $data['timezone']; ?></div>

<select style="width: 300px;" class="<?php if($data['no_edit']) { echo 'ea-hidden'; } else { echo 'select2'; } ?> timezone-edit" id="timezone_string" name="<?php echo EVENTAPPI_POST_NAME.'_timezone_string'; ?>" aria-describedby="timezone-description">
<?php echo wp_timezone_choice($data['timezone']); ?>
</select>