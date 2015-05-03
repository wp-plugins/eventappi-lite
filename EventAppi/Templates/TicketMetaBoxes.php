<?php
$terms = get_the_terms($post, $taxonomy = 'ticket');
if ($terms === false) {
    $term          = new stdClass();
    $term->name    = null;
    $term->term_id = null;
    $terms         = array($term);
}
$tabCount = 1;
?>
<div class="tickets-container">
    <a href="#" id="add-ticket-tabs">Add <?php echo (count($terms) > 0) ? 'another ' : 'a '; ?>ticket</a>

    <ul class="ticket-tabs">
        <?php foreach ($terms as $index => $term) : ?>
            <li class="tab-link <?php if ($tabCount === 1) {
                echo 'current';
            } ?>" data-tab="tab-<?php echo $tabCount ++; ?>">
                Ticket <?php echo $index + 1; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php $tabCount = 1; ?>
    <?php foreach ($terms as $term) : ?>
        <div class="ticket-tabs-content <?php if ($tabCount === 1) {
            echo 'current';
        } ?>" id="tab-<?php echo $tabCount ++; ?>">
            <div class="form-horizontal">
                <div class="form-group">
                    <label for="inputName" class="control-label col-xs-2">Ticket Name:</label>

                    <div class="col-xs-10">
                        <input class="form-control" name="eventappi_event_ticket_name[]" type="text"
                               value="<?php echo $term->name; ?>" placeholder="e.g. General Admission">
                    </div>
                </div>

                <div class="form-group">
                    <label for="inputDescription" class="control-label col-xs-2">Description:</label>

                    <div class="col-xs-10">
                        <input class="form-control" name="eventappi_event_ticket_description[]" type="text"
                               value="<?php echo $term->description; ?>" placeholder="e.g. A description of the ticket type.">
                    </div>
                </div>

                <div class="form-group">
                    <label for="inputSaleStart" class="control-label col-xs-2">On sale from:</label>

                    <div class="col-xs-10">
                        <input class="form-control cmb_datepicker" name="eventappi_event_ticket_sale_start[]"
                               type="text" placeholder="Now"
                               value="<?php echo get_tax_meta($term->term_id, 'eventappi_event_ticket_sale_start') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="inputSaleEnd" class="control-label col-xs-2">On sale to:</label>

                    <div class="col-xs-10">
                        <input class="form-control cmb_datepicker" name="eventappi_event_ticket_sale_end[]"
                               type="text" placeholder="Event start date"
                               value="<?php echo get_tax_meta($term->term_id, 'eventappi_event_ticket_sale_end'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="inputPrice" class="control-label col-xs-2">Ticket Price:</label>

                    <div class="col-xs-10">
                        <input class="form-control" name="eventappi_event_ticket_cost[]" type="text"
                               value="<?php echo number_format(intval(get_tax_meta($term->term_id,
                                       'eventappi_event_ticket_cost')) / 100, 2, '.', ''); ?>"
                               placeholder="e.g. 50.00">
                    </div>
                </div>

                <div class="form-group">
                    <label for="inputAvailable" class="control-label col-xs-2">Number available:</label>

                    <div class="col-xs-10">
                        <input class="form-control" name="eventappi_event_ticket_available[]" type="text"
                               value="<?php echo get_tax_meta($term->term_id, 'eventappi_event_ticket_available'); ?>"
                               placeholder="e.g. 1000">
                    </div>
                </div>

                <div class="form-group">
                    <label for="inputSold" class="control-label col-xs-2">Number sold:</label>

                    <div class="col-xs-10">
                        <input class="form-control" name="eventappi_event_ticket_sold[]" type="text"
                               value="<?php echo get_tax_meta($term->term_id, 'eventappi_event_ticket_sold'); ?>"
                               placeholder="0" readonly="readonly">
                    </div>
                </div>

                <div class="form-group">
                    <label for="inputPriceType" class="control-label col-xs-2">Ticket Type:</label>

                    <div class="col-xs-10">
                        <select name="eventappi_event_ticket_price_type[]">
                            <option value="fixed" <?php
                            echo (get_tax_meta($term->term_id, 'eventappi_event_ticket_price_type') == 'fixed')
                                ? 'selected="selected"' : ''; ?>>For Sale
                            </option>
                            <option value="free" <?php
                            echo (get_tax_meta($term->term_id, 'eventappi_event_ticket_price_type') == 'free')
                                ? 'selected="selected"' : ''; ?>>Free
                            </option>
                        </select>
                    </div>
                </div>
                <input name="eventappi_event_ticket_api_id[]" type="hidden">
            </div>
        </div>
    <?php endforeach; ?>
</div>
