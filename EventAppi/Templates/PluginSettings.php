<div class="wrap" id="eventappi_settings">
    <h2><?php echo __('Plugin Settings', EVENTAPPI_PLUGIN_NAME); ?></h2>

    <form method="post" action="options.php" enctype="multipart/form-data">
        <?php
        settings_fields(EVENTAPPI_PLUGIN_NAME . '_settings');
        do_settings_sections(EVENTAPPI_PLUGIN_NAME . '_settings');
        ?>
        <p class="submit">
            <input type="hidden" name="tab" value="<?php echo $tab ?>"/>
            <input name="submit" type="submit" class="button-primary"
                   value="<?php echo __('Save Settings', EVENTAPPI_PLUGIN_NAME) ?>"/>
        </p>
    </form>
</div>
