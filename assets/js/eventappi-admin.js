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
            
    if( $('#post').length ) {
        
        $('#post').submit(function() {
            
            // Event Post Type Page
            if($('body').hasClass('post-type-'+ eventappi_ajax_admin_obj.event_post_name)) {
                if($('[name="'+ eventappi_ajax_admin_obj.event_post_name +'_venue_select[cmb-field-0]"]').val() == '') {
                    alert(LANG.venue_not_selected);
                    return false;
                }
            }
        
            // Ticket Post Type Page
            if($('body').hasClass('post-type-'+ eventappi_ajax_admin_obj.ticket_post_name)) {
                // Check if a ticket type was selected
                if( ! $('[name="eventappi_ticket_type[cmb-field-0]"]').is(':checked') ) {
                    alert(LANG.ticket_select_type);
                    return false;
                }
                
                // If the ticket type selection is made for "Sale" and the price is 0, show a warning to the user
                if( $('#'+ eventappi_ajax_admin_obj.ticket_post_name +'_price-cmb-field-0').val() <= 0
                      && $('#'+ eventappi_ajax_admin_obj.ticket_post_name +'_type-cmb-field-0-item-sale').is(':checked') ) {
                    alert(LANG.ticket_for_sale_zero_price);
                    return false;                    
                }
                
                // If the ticket type selection is made for "Free" and the price is bigger than 0, show a warning to the user
                if( $('#'+ eventappi_ajax_admin_obj.ticket_post_name +'_price-cmb-field-0').val() > 0
                      && $('#'+ eventappi_ajax_admin_obj.ticket_post_name +'_type-cmb-field-0-item-free').is(':checked') ) {
                    alert(LANG.ticket_for_free_bigger_zero);
                    return false;
                }      
                
                // If there is no event selected
                if( $('[name="event_id"]').val() == '' ) {
                    alert(LANG.ticket_select_event);
                    return false;
                }    
            }
            
        });
    }
    
    if( $('body').hasClass('post-type-'+ eventappi_ajax_admin_obj.event_post_name)
        && $('body').hasClass('post-php') ) {
        $('.select2').select2();
    }
    
    if($('#event_ids').length > 0) {
        $('#event_ids').chosen().change(function() {
            // Make an AJAX call to see
            var data = {
                'action': eventappi_ajax_admin_obj.plugin_name + '_check_event_max_tickets',
                'event_id': $(this).val(),
                'event_id_cur': $('#event_id_cur').val()
            };

            $('#publishing-action').find('.spinner').show().css({'visibility':'visible'});
            $('#publish').hide();

            // We can also pass the url value separately from ajaxurl for front end AJAX implementations
            jQuery.post(eventappi_ajax_admin_obj.ajax_url, data, function(response) {
                var obj = $.parseJSON(response);

                $('#publishing-action').find('.spinner').hide().css({'visibility':'hidden'});
                $('#publish').show();

                // It's publishable/updateable
                if(obj.status == 'do_publish') {
                    $('#submitdiv').show();
                    $('#event-ticket-max-error').hide();
                } else if(obj.status == 'no_publish') {
                    $('#submitdiv').hide();
                    $('#event-ticket-max-error').show();
                }
            });            
        });

        // If the form is submitted without pressing the "Update" button (which should be hidden anyway)
        // we will show an alert in case there are submit errors
        $('#post').submit(function() {
            if($('#event-ticket-max-error').is(':visible')) {
                alert( $.trim($('#event-ticket-max-error').html()) );
                return false;
            }
        });

        // Add New Registration Field (Click Action)
        $('#ea-add-new-reg-field').click(function(e) {
            e.preventDefault();
            
            
        });
    }
        
    if($('.eventappi.cmb_datepicker').length > 0) {        
        $('.eventappi.cmb_datepicker')
          .removeClass('hasDatepicker').removeData('datepicker')
          .unbind().datepicker({
              'dateFormat' : eventappi_ajax_admin_obj.date_format
          });
    }

    // Add "current" class to 'Organisers'
    if($('.wp-has-current-submenu.wp-ea-organisers').length > 0) {
        $('.wp-has-current-submenu.wp-ea-organisers').find('ul.wp-submenu > li').each(function(index) {
            if($(this).text() == LANG.organisers) {
                $(this).addClass('current');
            }
        });

        $('#menu-users').removeClass('wp-has-current-submenu').addClass('wp-not-current-submenu')
                        .find('.menu-icon-users').removeClass('wp-has-current-submenu').addClass('wp-not-current-submenu')

        $('#menu-users').find('ul.wp-submenu > li.current').removeClass('current');
        $('#menu-users').show();
    }
});