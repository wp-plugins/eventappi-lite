<?php
use EventAppi\Helpers\Format;

$terms = get_the_terms($post, $taxonomy = 'ticket');

if ($terms === false) {
    $term              = new stdClass();
    $term->name        = null;
    $term->term_id     = null;
    $term->description = null;
    $terms             = array($term);
}

$tabCount = 1;

$addTicketText = (count($terms) > 0) ? __('Add another ticket', EVENTAPPI_PLUGIN_NAME) : __('Add a ticket', EVENTAPPI_PLUGIN_NAME);
?>
<div class="tickets-container">
    <a href="#" id="add-ticket-tabs"><?php echo $addTicketText; ?></a>

    <ul class="ticket-tabs">
        <?php
        foreach ($terms as $index => $term): ?>
            <li class="tab-link <?php
                                if ($tabCount === 1) {
                                    echo 'current';
                                } ?>" data-tab="tab-<?php echo $tabCount ++; ?>">
                <?php
                if ($term->name) {
                    echo $term->name;
                } else {
                     echo sprintf(__('Ticket %d'), ($index + 1));
                }
                ?>
            </li>
        <?php
        endforeach; ?>
    </ul>

    <?php
    $tabCount = 1;

    foreach ($terms as $term): ?>
        <div class="ticket-tabs-content <?php
                                        if ($tabCount === 1) {
                                            echo 'current';
                                        } ?>" id="tab-<?php echo $tabCount ++; ?>">
            <div class="form-horizontal">
                <div class="form-group">
                    <label for="inputName" class="control-label col-xs-2"><?php _e('Ticket Name:', EVENTAPPI_PLUGIN_NAME); ?></label>

                    <div class="col-xs-10">
                        <input class="form-control" name="eventappi_event_ticket_name[]" type="text" value="<?php echo $term->name; ?>" placeholder="<?php _e('e.g. General Admission', EVENTAPPI_PLUGIN_NAME); ?>" />
                    </div>
                </div>

                <div class="form-group">
                    <label for="inputDescription" class="control-label col-xs-2"><?php _e('Description:', EVENTAPPI_PLUGIN_NAME); ?></label>

                    <div class="col-xs-10">
                        <input class="form-control" name="eventappi_event_ticket_description[]" type="text" value="<?php echo $term->description; ?>" placeholder="<?php _e('e.g. A description of the ticket type.', EVENTAPPI_PLUGIN_NAME); ?>" />
                    </div>
                </div>

                <div class="form-group">
                    <label for="inputSaleStart" class="control-label col-xs-2"><?php _e('On sale from:', EVENTAPPI_PLUGIN_NAME); ?></label>

                    <div class="col-xs-10">

                        <input class="form-control cmb_datepicker start_date ticket" name="eventappi_event_ticket_sale_start[]" type="text" placeholder="<?php _e('Now', EVENTAPPI_PLUGIN_NAME); ?>" value="<?php echo date(Format::getJSCompatibleDateFormatString(get_option('date_format')), get_tax_meta($term->term_id, 'eventappi_event_ticket_sale_start')); ?>" />

                    </div>
                </div>

                <div class="form-group">
                    <label for="inputSaleEnd" class="control-label col-xs-2"><?php _e('On sale to:', EVENTAPPI_PLUGIN_NAME); ?></label>

                    <div class="col-xs-10">
                        <input class="form-control cmb_datepicker end_date ticket" name="eventappi_event_ticket_sale_end[]" type="text" placeholder="<?php _e('Event start date', EVENTAPPI_PLUGIN_NAME); ?>" value="<?php echo date(Format::getJSCompatibleDateFormatString(get_option('date_format')), get_tax_meta($term->term_id, 'eventappi_event_ticket_sale_end')); ?>" />
                    </div>
                </div>

                <div class="form-group">
                    <label for="inputPrice" class="control-label col-xs-2"><?php _e('Ticket Price:', EVENTAPPI_PLUGIN_NAME); ?></label>

                    <div class="col-xs-10">
                        <?php
                        $thisTicketPrice = number_format(intval(get_tax_meta($term->term_id, 'eventappi_event_ticket_cost')) / 100, 2, '.', '');
                        if ($thisTicketPrice === '0.00') {
                            $thisTicketPrice = '';
                        }
                        ?>
                        <input class="form-control" name="eventappi_event_ticket_cost[]" type="text" value="<?php echo $thisTicketPrice; ?>" placeholder="<?php _e('e.g. 50.00', EVENTAPPI_PLUGIN_NAME); ?>" />
                    </div>
                </div>

                <div class="form-group">
                    <label for="inputAvailable" class="control-label col-xs-2"><?php _e('Number available:', EVENTAPPI_PLUGIN_NAME); ?></label>

                    <div class="col-xs-10">
                        <input class="form-control" name="eventappi_event_ticket_available[]" type="text" value="<?php echo get_tax_meta($term->term_id, 'eventappi_event_ticket_available'); ?>" placeholder="<?php _e('e.g. 1000', EVENTAPPI_PLUGIN_NAME); ?>" />
                    </div>
                </div>

                <div class="form-group">
                    <label for="inputSold" class="control-label col-xs-2"><?php _e('Number sold:', EVENTAPPI_PLUGIN_NAME); ?></label>

                    <div class="col-xs-10">
                        <input class="form-control" name="eventappi_event_ticket_sold[]" type="text" value="<?php echo get_tax_meta($term->term_id, 'eventappi_event_ticket_sold'); ?>" placeholder="0" readonly="readonly" />
                    </div>
                </div>

                <div class="form-group">
                    <label for="inputPriceType" class="control-label col-xs-2">Ticket Type:</label>

                    <div class="col-xs-10">
                        <select name="eventappi_event_ticket_price_type[]">
                            <option value="fixed" <?php echo (get_tax_meta($term->term_id, 'eventappi_event_ticket_price_type') == 'fixed') ? 'selected="selected"' : ''; ?>><?php _e('For Sale', EVENTAPPI_PLUGIN_NAME); ?></option>
                            <option value="free" <?php echo (get_tax_meta($term->term_id, 'eventappi_event_ticket_price_type') == 'free') ? 'selected="selected"' : ''; ?>><?php _e('Free', EVENTAPPI_PLUGIN_NAME); ?></option>
                        </select>
                    </div>
                </div>
                <input name="eventappi_event_ticket_api_id[]" type="hidden" />
            </div>
        </div>
    <?php
    endforeach; ?>
</div>
