<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<h3><?php _e('EventAppi Notes', EVENTAPPI_PLUGIN_NAME); ?></h3>

<?php
wp_editor( $data['notes'], EVENTAPPI_PLUGIN_NAME.'_notes', array('editor_height' => 200) );
?>