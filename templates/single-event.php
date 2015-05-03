<?php
/*
Template Name: Single Event
*/
/**
 * This template renders the event specific data for the eventappi_event CPT.
 * The rendered HTML will be added to the end of the single post rendered by
 * your theme.
 *
 * This template receives a single data object ($data) containing the following
 * data relevant to the eventappi_event Custom Post Type:
 *  - thePostId
 *  - eventId
 *  - formAction : The action for the Cart form
 *  - startDate
 *  - startTime
 *  - endDate
 *  - endTime
 *  - theVenue   : the Venue taxonomy item
 *  - theAddress : imploded Venue address fields for one-line display
 *  - theAdrLink : URL safe imploded address (for map integration)
 *  - theTickets : an array of tickets for the event
 */
?>
<p></p>
<table class="table event">
    <tbody>
    <tr>
        <th>Start Date:</th>
        <td><?php echo $data['startDate']; ?></td>
        <th>Start Time:</th>
        <td><?php echo $data['startTime']; ?></td>
    </tr>
    <tr>
        <th>End Date:</th>
        <td><?php echo $data['endDate']; ?></td>
        <th>End Time:</th>
        <td><?php echo $data['endTime']; ?></td>
    </tr>
    </tbody>
</table>
<p>
    <label>Venue:</label><i><?php echo $data['theVenue']['name']; ?></i><br><?php echo $data['theAddress']; ?>
</p>
<div id="eventappi-wrapper">
    <h2>Tickets</h2>
    <table class="table tickets" id="ev_tickets">
        <thead>
        <tr>
            <th>Ticket name</th>
            <th>Price</th>
            <th>Quantity</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($data["theTickets"] as $ticket) : ?>
            <tr>
                <td><?php echo $ticket->name; ?><br>
                    <small><em><?php echo $ticket->description; ?></em></small>
                </td>
                <td>$ <?php echo $ticket->price; ?></td>
                <td>
                    <input name="quantity[]" type="qty" class="form-control ticket-quantity" value=""
                           placeholder="<?php echo $ticket->avail; ?> available">
                    <input type="hidden" name="post_id[]" value="<?php echo $data['thePostId']; ?>">
                    <input type="hidden" name="term[]" value="<?php echo $ticket->term_id; ?>">
                    <input type="hidden" name="event[]" value="<?php echo $data['eventId']; ?>">
                    <input type="hidden" name="id[]" value="<?php echo $ticket->ticket_id; ?>">
                    <input type="hidden" name="name[]" value="<?php echo $ticket->name; ?>">
                    <input type="hidden" class="ticket-price" name="price[]" value=" <?php echo $ticket->cost; ?>">
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <form action="<?php echo $data['formAction']; ?>" method="post">
        <input type="submit" id="proceed-to-cart" value="Proceed to Cart" class="btn btn-primary">
    </form>
</div>
<hr>
<h2>Map</h2>
<div class="gMaps">
    <iframe
        src="https://www.google.com/maps/embed/v1/search?q=<?php echo $data['theAdrLink']; ?>&amp;key=AIzaSyAIfYytyWIykkzlsU-FpAhVURPKZd2Ro10"
        width="100%" height="500" frameborder="0" style="border:0">
    </iframe>
</div>
