<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
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
<table id="ea-event-date-info" class="table event">
    <tbody>
    <tr>
        <th><?php _e('Start Date:', EVENTAPPI_PLUGIN_NAME); ?></th>
        <td><?php echo $data['startDate']; ?></td>
        <th><?php _e('Start Time:', EVENTAPPI_PLUGIN_NAME); ?></th>
        <td><?php echo $data['startTime']; ?></td>
    </tr>
    <tr>
        <th><?php _e('End Date:', EVENTAPPI_PLUGIN_NAME); ?></th>
        <td><?php echo $data['endDate']; ?></td>
        <th><?php _e('End Time:', EVENTAPPI_PLUGIN_NAME); ?></th>
        <td><?php echo $data['endTime']; ?></td>
    </tr>
    </tbody>
</table>

<div id="ea-event-no-tickets-error" data-event-id="<?php echo $data['ID']; ?>" class="ea-note-error"></div>

<?php
if( ! empty($data['theVenue']) ) {
?>
<p>
    <label><?php _e('Venue:', EVENTAPPI_PLUGIN_NAME); ?></label> <i><?php echo $data['theVenue']['name']; ?></i><br /><?php echo $data['theAddress']; ?>
</p>
<?php
}

if( ! empty($data['theCats']['names']) ) {
?>
<p>
    <label><?php echo $data['theCats']['label']; ?>:</label>
    <em><?php echo implode(', ', $data['theCats']['names']); ?></em>
</p>
<?php
}

echo $data['ticketsArea'];

if (! empty($data['theVenue'])) {
?>
<hr>
<h2><?php _e('Map', EVENTAPPI_PLUGIN_NAME); ?></h2>
<div class="gMaps">
    <iframe src="https://www.google.com/maps/embed/v1/search?q=<?php echo $data['theAdrLink']; ?>&amp;key=AIzaSyAIfYytyWIykkzlsU-FpAhVURPKZd2Ro10" width="100%" height="500" frameborder="0" style="border:0"></iframe>
</div>
<?php } ?>