<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * The data object for this is the contents of the cart for this user
 */
$total = 0;
?>
<div id="eventappi-wrapper" class="wrap">
    <?php if (count($data) === 0) : ?>
        <h3><?php _e('Your cart is empty.', EVENTAPPI_PLUGIN_NAME); ?></h3><br>
    <?php else: ?>

        <form role="form" id="eventappi-cart">
            <table class="table" id="ev_tickets">
                <thead>
                <tr>
                    <th><?php _e('Item', EVENTAPPI_PLUGIN_NAME); ?></th>
                    <th><?php _e('Price', EVENTAPPI_PLUGIN_NAME); ?></th>
                    <th><?php _e('Quantity', EVENTAPPI_PLUGIN_NAME); ?></th>
                    <th><?php _e('Sub total', EVENTAPPI_PLUGIN_NAME); ?></th>
                    <th>&nbsp;</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($data as $key => $ticket) : ?>
                    <?php if (intval($ticket->ticket_quantity) > 0) : ?>
                        <tr>
                            <td><?php echo $ticket->ticket_name; ?></td>
                            <td>
                                $<span><?php echo money_format('%i', $ticket->ticket_price); ?></span>
                            </td>
                            <td>
                                <input type="qty" name="quantity[]"  class="form-control ticket-quantity" readonly="readonly" value="<?php echo $ticket->ticket_quantity; ?>" placeholder="<?php _e('e.g. 120', EVENTAPPI_PLUGIN_NAME); ?>">
                                <input type="hidden" name="event[]" value="<?php echo $ticket->event_id; ?>">
                                <input type="hidden" name="id[]" value="<?php echo $ticket->ticket_id; ?>">
                                <input type="hidden" name="name[]" value="<?php echo $ticket->ticket_name; ?>">
                                <input type="hidden" name="post_id[]" value="<?php echo $ticket->post_id; ?>">
                                <input type="hidden" name="term[]" value="<?php echo $ticket->term; ?>">
                                <input type="hidden" class="ticket-price" name="price[]" value="<?php echo $ticket->ticket_price; ?>">
                            </td>
                            <td class="full-price">
                                $<span><?php echo money_format('%i', ($ticket->ticket_quantity * $ticket->ticket_price)); ?></span>
                            </td>
                            <td>
                                <a class="remove" data-id="<?php echo $ticket->ticket_id; ?>" href="javascript:void(0)">
                                    <span title="<?php _e('Remove', EVENTAPPI_PLUGIN_NAME); ?>" class="dashicons dashicons-no"></span>
                                </a>
                            </td>
                        </tr>
                        <?php $total += ($ticket->ticket_price * $ticket->ticket_quantity); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3"></td>
                    <td class="text-right">
                        <?php _e('Total:', EVENTAPPI_PLUGIN_NAME); ?> <br />
                    </td>
                    <td>
                        <b>$<span id="cart-total"><?php echo money_format('%i', $total); ?></span></b>
                    </td>
                </tr>
                <tr>
                    <td colspan="3"></td>
                    <td colspan="2">
                        <button id="go-to-checkout" class="btn btn-primary"><?php _e('Proceed to checkout', EVENTAPPI_PLUGIN_NAME); ?></button>
                    </td>
                </tr>
                </tbody>
            </table>
        </form>
    <?php endif; ?>
</div>