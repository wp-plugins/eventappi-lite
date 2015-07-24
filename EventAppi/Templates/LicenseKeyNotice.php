<?php
$linkToEventAppi = sprintf(
    __('Need a license key? Please visit %sEventAppi.com%s for details.', EVENTAPPI_PLUGIN_NAME),
    '<a href="http://eventappi.com/pricing/">',
    '</a>'
);
$linkToSettings = sprintf(
    __('Please see the %sSettings page%s.'),
    '<a href="admin.php?page=eventappi-settings">',
    '</a>'
);
?>
<div class="eventappi-notice update-nag">
    <p><?php
        _e(
            'Please enter your EventAppi license key and payment gateway settings to use the plugin.',
            EVENTAPPI_PLUGIN_NAME
        ); ?></p>
    <p><?php echo $linkToSettings; ?></p>
    <p><?php echo $linkToEventAppi; ?></p>
</div>
