var $ = jQuery,
    LANG = eventappi_ajax_admin_obj.text;

function hideHiddenOptionElements() {
    $('input[id=license_key_status]').closest('tr').hide();
    $('input[id=license_key_checkpoint]').closest('tr').hide();
}

function clearOmnipayGatewaySelection() {
    $('input[id^="gateway_"]').closest('tr').hide();
    var $selector = 'gateway_' + $('#eventappi_settings').find('select#gateway').val() + '_';
    $('input[id^="' + $selector + '"]').closest('tr').show();
    $('input[id="' + $selector + 'fullGatewayName"]').closest('tr').hide();
}

jQuery(document).ready(function ($) {
    var self = $(this);

    self.find('#event-attendee-search-submit').on('click', function (event) {
        event.preventDefault();
        var attFilter = $('#attendees-filter');
        location.assign(attFilter.find('#attendee-search-url').val() + '&s=' + attFilter.find('#attendee-search-input').val());
    });

    self.find('#event-attendee-export-submit').on('click', function (event) {
        event.preventDefault();
        var attExport  = $('#attendees-export');
        var exportType = attExport.find('#export-selector-type').val();
        if (exportType != -1) {
            location.assign(attExport.find('#attendee-search-url').val() + '&e=' + exportType);
        }
    });

    self.find('.assign-ticket-purchase').each(function () {
        $(this).on('click', function (event) {
            event.preventDefault();
            var loc = location;
            var mailValue = $('#emailfor' + $(this).attr('data-hash')).val();
            if (mailValue == '' || mailValue == null) {
                alert(LANG.assign_ticket_specify_email);
                exit();
            }
            $.ajax({
                url: eventappi_ajax_admin_obj.ajax_url,
                type: 'POST',
                data: [
                    {'name': 'action', 'value': eventappi_ajax_admin_obj.plugin_name + '_send_ticket'},
                    {'name': '_ajax_nonce', 'value': eventappi_ajax_admin_obj.nonce},
                    {'name': 'recipient', 'value': mailValue},
                    {'name': 'name', 'value': mailValue},
                    {'name': 'hash', 'value': '#' + $(this).attr('data-hash')}
                ]
            });
            location.assign(loc);
        });
    });

    hideHiddenOptionElements();
    clearOmnipayGatewaySelection();
    $('#eventappi_settings').find('select#gateway').change(function () {
        clearOmnipayGatewaySelection();
    });

    $('body').on('click', 'ul.ticket-tabs li', function () {
        var tab_id = $(this).attr('data-tab');

        $('ul.ticket-tabs li').removeClass('current');
        $('.ticket-tabs-content').removeClass('current');

        $(this).addClass('current');
        $("#" + tab_id).addClass('current');
    });

    var target;

    $( ".start_date" ).datepicker({
        'dateFormat' : eventappi_ajax_admin_obj.date_format,

        onClose: function( selectedDate ) {
            if($(this).hasClass('ticket')) { // Ticket Range
                target = $(this).parent().parent().next('div').find( ".end_date" );
            } else { // Event Range
                target = $( ".end_date.event" );
            }

            target.datepicker( "option", "minDate", selectedDate );
        }
    });

    $( ".end_date" ).datepicker({
        'dateFormat' : eventappi_ajax_admin_obj.date_format,

        onClose: function( selectedDate ) {
            if($(this).hasClass('ticket')) { // Ticket Range
                target = $(this).parent().parent().prev('div').find( ".start_date" );
            } else { // Event Range
                target = $( ".start_date.event" );
            }

            target.datepicker( "option", "maxDate", selectedDate );
        }
    });

    $('#add-ticket-tabs').on('click', function (e) {
        e.preventDefault();

        $('.tickets-container .ticket-tabs-content, .ticket-tabs li').removeClass('current');

        var tickets_num = $('.ticket-tabs li').length + 1;
        var ticket_new_tab_title = LANG.new_ticket_tab_title.replace('%d', tickets_num);

        $('.ticket-tabs').append('<li class="tab-link current" data-tab="tab-' + tickets_num + '">'+ ticket_new_tab_title +'</li>');

        var clonedTicketForm = $('.ticket-tabs-content').first().clone(true);
        clonedTicketForm.find("input[type='text']").val('');
        clonedTicketForm.attr('id', 'tab-' + tickets_num).addClass('current');
        clonedTicketForm.appendTo('.tickets-container');

        $('.cmb_datepicker.ticket').each(function(index) {
            $(this).attr( 'id', '' ).removeClass( 'hasDatepicker' ).removeData( 'datepicker' ).unbind().datepicker({
                'dateFormat' : eventappi_ajax_admin_obj.date_format,

                onClose: function(selectedDate) {
                    if($(this).hasClass('start_date')) {
                        target = $(this).parent().parent().next('div').find( ".end_date" );
                        target.datepicker( "option", "minDate", selectedDate );
                    } else {
                        target = $(this).parent().parent().prev('div').find( ".start_date" );
                        target.datepicker( "option", "maxDate", selectedDate );
                    }
                }
            });
        });
    });

});
