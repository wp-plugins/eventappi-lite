<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div id="eventappi-wrapper">
	<h2><?php _e('Tickets', EVENTAPPI_PLUGIN_NAME); ?></h2>

		<form action="<?php echo $data['formAction']; ?>" method="post">
			<table class="table tickets" id="ev_tickets">
				<thead>
				<tr>
					<th><?php _e('Ticket name', EVENTAPPI_PLUGIN_NAME); ?></th>
					<th><?php _e('Price', EVENTAPPI_PLUGIN_NAME); ?></th>
					<th><?php _e('Quantity', EVENTAPPI_PLUGIN_NAME); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ($data['theTickets'] as $ticketKey => $ticket) : ?>
					<tr>
						<td><?php echo $ticket->post_title; ?><br />
							<small><em><?php echo $ticket->post_content; ?></em></small>
						</td>
						<td>$<?php echo $ticket->price; ?></td>
						<td>
							<?php if( ! $ticket->soldOut) { ?>
								<input type="number" name="quantity[<?php echo $ticketKey; ?>]" class="form-control ticket-quantity" min="1"
								       value="" placeholder="<?php echo sprintf(__('%s available', EVENTAPPI_PLUGIN_NAME), $ticket->avail); ?>">
							<?php } else { ?>
								<strong>SOLD OUT</strong>
							<?php } ?>
							<input type="hidden" name="ticket_api_id[<?php echo $ticketKey; ?>]" value="<?php echo $ticket->ticketApiId; ?>">
							<input type="hidden" name="ticket_id[<?php echo $ticketKey; ?>]" value="<?php echo $ticket->ID; ?>">
							<input type="hidden" name="ticket_name[<?php echo $ticketKey; ?>]" value="<?php echo $ticket->post_title; ?>">
							<input type="hidden" class="ticket-price" value="<?php echo $ticket->price; ?>">
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<div class="alignleft">
				<input type="submit" id="proceed-to-cart" value="<?php _e('View Cart', EVENTAPPI_PLUGIN_NAME); ?>" class="btn btn-primary">
			</div>

			<div class="alignright">
				<input type="submit" class="ea-add-to-cart" value="<?php _e('Add to Cart', EVENTAPPI_PLUGIN_NAME); ?>" class="btn btn-default">
			</div>

			<div class="ea-clear"></div>
		</form>
</div>