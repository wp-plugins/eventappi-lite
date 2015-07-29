jQuery(document).ready(function ($) {
    
    // Are we in the front-end? Assign the right value
    if (typeof eventappi_ajax_admin_obj === 'undefined') {
        eventappi_ajax_admin_obj = eventappi_ajax_obj;
    }
    
    var LANG = eventappi_ajax_admin_obj.text;

    // Apply only for Tickets in Edit Event Page
    if($('#accordion-event-tickets').length) {
        
        // Initiate Accordion
        $( "#accordion-event-tickets" ).accordion({
            collapsible: true,
            active: false,
            header: "> div.group > h3",
            activate: function( event, ui ) {
                $area = $('.ui-accordion-content-active').find('.event-ticket');
                
                if( ! $('.ui-accordion-content-active').hasClass('panelLoaded') ) {
                        
                    // Fetch the ticket information and show it as editable
                    var data = {
                        'action': eventappi_ajax_admin_obj.plugin_name + '_load_edit_ticket',
                        'ticket_id': $area.data('id'),
                        'is_ajax': 1
                    };

                    // We can also pass the url value separately from ajaxurl for front end AJAX implementations
                    jQuery.post(eventappi_ajax_admin_obj.ajax_url, data, function(output) {
                        $area.prev('.loading').hide();
                        $area.html(output);
                        initRegFieldsAcc();
                    });                    
                    
                    $('.ui-accordion-content-active').addClass('panelLoaded');
                }
            }     
        }).sortable({
            axis: "y",
            handle: "h3",
            placeholder: "ui-state-highlight",
            stop: function( event, ui ) {
                $('#ea-sort-spinner').css({'visibility':'visible'}).fadeIn();
                
                // IE doesn't register the blur when sorting
                // so trigger focusout handlers to remove .ui-state-focus
                ui.item.children( "h3" ).triggerHandler( "focusout" );
                
                var tickets_pos = '';
                
                $('.ea-accordion-title[data-ea-ticket-id]').each(function(index, val) {
                    tickets_pos += $(val).attr('data-ea-ticket-id') + ',';
                });
                
                // Update Tickets' Position                
                $.post(eventappi_ajax_admin_obj.ajax_url, {
                    'action': eventappi_ajax_admin_obj.plugin_name + '_update_tickets_pos',
                    'tickets_pos': tickets_pos
                }, function() {
                    $('#ea-sort-spinner').css({'visibility':'hidden'}).fadeOut();
                });
                
                // Refresh accordion to handle new order
                $( this ).accordion( "refresh" );
            }
        });
    }
    
    var eaTbTarget = $('.ea-add-new-ticket-btn').find('a.ea-add-btn.thickbox');
    
    $(eaTbTarget).click(function() {
        triggerTbClick();
    }); // adjust frame
    
    function triggerTbClick() {
        if( ! eaTbTarget.hasClass('clicked') ) {
            eaTbTarget.addClass('clicked').click();
        }
    }
    
    // Event Post Type Page (Update Ticket Button is Clicked)
    // One ticket is updated at a time
    if($('body').hasClass('post-type-'+ eventappi_ajax_admin_obj.event_post_name)) {
        
        var target;
    
        if( $('.start_date.eventappi').length > 0 || $('.end_date.eventappi').length > 0 ) {

            $('body').on('focus', '.start_date', function() {            
                $( this ).datepicker({
                    'dateFormat' : eventappi_ajax_admin_obj.date_format,

                    onClose: function( selectedDate ) {
                        if($(this).hasClass('ticket')) { // Ticket Range
                            target = $(this).parent().parent().parent().next('div').find( ".end_date" );
                        } else { // Event Range
                            target = $( ".end_date.event" );    
                        }

                        target.datepicker( "option", "minDate", selectedDate );
                    }
                });
            });

            $('body').on('focus', '.end_date', function() {
                $( this ).datepicker({
                    'dateFormat' : eventappi_ajax_admin_obj.date_format,

                    onClose: function( selectedDate ) {
                        if($(this).hasClass('ticket')) { // Ticket Range
                            target = $(this).parent().parent().parent().prev('div').find( ".start_date" );
                        } else { // Event Range
                            target = $( ".start_date.event" );    
                        }                    

                        target.datepicker( "option", "maxDate", selectedDate );
                    }
                });
            });

        }        
        
        // ---------------------
        // EDIT TICKET ACTION
        // ---------------------
        $('div#accordion-event-tickets').on('click', '.ea-update-ticket-btn', function() {
            var ticket_id = $(this).data('ticket-id');

            // Check if a ticket type was selected
            if( ! $('#ticket-fields-'+ ticket_id).find('[name="eventappi_ticket_type[cmb-field-0]"]').is(':checked') ) {
                alert(LANG.ticket_select_type);
                return false;
            }

            // If the ticket type selection is made for "Sale" and the price is 0, show a warning to the user
            if( $('#t'+ ticket_id +'_'+ eventappi_ajax_admin_obj.ticket_post_name +'_price-cmb-field-0').val() <= 0
                  && $('#t'+ ticket_id +'_'+ eventappi_ajax_admin_obj.ticket_post_name +'_type-cmb-field-0-item-sale').is(':checked') ) {
                alert(LANG.ticket_for_sale_zero_price);
                return false;                    
            }

            // If the ticket type selection is made for "Free" and the price is bigger than 0, show a warning to the user
            if( $('#t'+ ticket_id +'_'+ eventappi_ajax_admin_obj.ticket_post_name +'_price-cmb-field-0').val() > 0
                  && $('#t'+ ticket_id +'_'+ eventappi_ajax_admin_obj.ticket_post_name +'_type-cmb-field-0-item-free').is(':checked') ) {
                alert(LANG.ticket_for_free_bigger_zero);
                return false;
            }
            
            // No Errors? Make the AJAX call to save the ticket's details
            // First Update the Input value to be in the HTML
            $('#edit-ticket-area-'+ ticket_id).find('input, select, textarea').each(function() {
                if($(this).is("[type='checkbox']")) {
                    $(this).attr("checked", $(this).attr("checked"));
                } else if($(this).is("textarea")) {
                    $(this).html($(this).val());
                } else {
                    $(this).attr("value", $(this).val()); 
                }
            });                
            
            var $edit_area_html = $('#edit-ticket-area-'+ ticket_id).html();
            
            // Append form to BODY for the serialize
            $('body').append('<form class="ea-hidden" id="edit-ticket-form-'+ ticket_id +'">'+ $edit_area_html +'</form>');
            
            // Show the loading message and hide the update button
            $('button[data-ticket-id="'+ ticket_id +'"]').hide();
            $('#ticket-updating-'+ ticket_id).show();
            
            // Hide the confirmation message if it isn't hidden yet
            $('#ticket-updated-'+ ticket_id).addClass('ea-hidden').html('');
            
            var data = {
                'action': eventappi_ajax_admin_obj.plugin_name + '_edit_ticket',
                'ticket_id': ticket_id,
                'event_id': $('#post_ID').val(),                
                'data': $('#edit-ticket-form-'+ ticket_id).serialize()
            };
            
            // Remove appended form
            $('#edit-ticket-form-'+ ticket_id).remove();
            
            // We can also pass the url value separately from ajaxurl for front end AJAX implementations
            $.post(eventappi_ajax_admin_obj.ajax_url, data, function(response) {
                var res_obj = jQuery.parseJSON(response);
                
                // Hide the loading message and show the update button again
                $('button[data-ticket-id="'+ ticket_id +'"]').show();
                $('#ticket-updating-'+ ticket_id).hide();
                
                if(res_obj.status == 'success') {
                    // Show the success updated message
                    $('#ticket-updated-'+ ticket_id).html(res_obj.message).removeClass('ea-hidden');
                    
                    // Update the Accordion's title with the Ticket's Title from the input box
                    $('.event-ticket[data-id="'+ ticket_id +'"]').parent().prev('h3').html('<span class="ui-accordion-header-icon ui-icon ui-icon-triangle-1-e"></span>'+ $('#'+ eventappi_ajax_admin_obj.plugin_name +'_ticket_title_'+ ticket_id).val());
                    
                    // Hide the message after a few seconds
                    setTimeout(function() { $('#ticket-updated-'+ ticket_id).addClass('ea-hidden').html(''); }, 6000);                        
                } else {
                    alert(response); // No Success Message? Show any possible errors!
                }
            });                 
            
            return false;
        });
        
        // -------------------
        // ADD TICKET ACTION
        // -------------------            
        
        $('#ea-add-new-ticket-btn').click(function() {

            // Check if there is a ticket title 
            if( $('#TB_ajaxContent').find('#ea-add-ticket-fields').find('[name="eventappi_ticket_title"]').val() == '' ) {
                alert(LANG.ticket_add_title);
                return false;
            }
            
            // Check if a ticket type was selected
            if( ! $('#TB_ajaxContent').find('#ea-add-ticket-fields').find('[name="eventappi_ticket_type[cmb-field-0]"]').is(':checked') ) {
                alert(LANG.ticket_select_type);
                return false;
            }

            // If the ticket type selection is made for "Sale" and the price is 0, show a warning to the user
            if( $('#TB_ajaxContent').find('#'+ eventappi_ajax_admin_obj.ticket_post_name +'_price-cmb-field-0').val() <= 0
                  && $('#'+ eventappi_ajax_admin_obj.ticket_post_name +'_type-cmb-field-0-item-sale').is(':checked') ) {
                alert(LANG.ticket_for_sale_zero_price);
                return false;                    
            }

            // Number available
            if( $('#TB_ajaxContent').find('#'+ eventappi_ajax_admin_obj.ticket_post_name +'_no_available-cmb-field-0').val() <= 0 ) {
                alert(LANG.ticket_add_qty);
                return false;
            }  

            // If the ticket type selection is made for "Free" and the price is bigger than 0, show a warning to the user
            if( $('#TB_ajaxContent').find('#'+ eventappi_ajax_admin_obj.ticket_post_name +'_price-cmb-field-0').val() > 0
                  && $('#'+ eventappi_ajax_admin_obj.ticket_post_name +'_type-cmb-field-0-item-free').is(':checked') ) {
                alert(LANG.ticket_for_free_bigger_zero);
                return false;
            }  
            
            // --- No errors found? Make the AJAX call to Insert this Ticket into the Database ---
            var data = {
                'action': eventappi_ajax_admin_obj.plugin_name + '_add_ticket',
                'event_id': $('#post_ID').val(),                
                'data': $('#ea-add-ticket-form').serialize()
            };

            // Show the loading message and hide the add button
            $('#ea-ticket-adding').show();
            $('#ea-add-new-ticket-btn').hide();

            // We can also pass the url value separately from ajaxurl for front end AJAX implementations
            $.post(eventappi_ajax_admin_obj.ajax_url, data, function(response) {
                var res_obj = jQuery.parseJSON(response);
                
                // Hide the loading message and show the add button
                $('#ea-ticket-adding').hide();
                $('#ea-add-new-ticket-btn').show();
                 
                if(res_obj.status == 'success') {
                    // Show the success updated message
                    $('#ea-ticket-added').html(res_obj.message).fadeIn().removeClass('ea-hidden');

                    // Append a new panel to the accordion 
                    // having the ticket's title and the meta box fields ready to edit
                    eaAddTicketToAccordion(res_obj.ticket_id);
                    
                    // Clear the fields within the form
                    $('#ea-add-ticket-form').find(':input').each(function() {
                        switch(this.type) {
                            case 'password':
                            case 'select-multiple':
                            case 'select-one':
                            case 'text':
                            case 'number':
                            case 'textarea':
                                $(this).val('');
                            break;
                            
                            case 'checkbox':
                            case 'radio':
                                this.checked = false;
                        }
                    });
                    
                    // Close the thickbox
                    $('#TB_closeWindowButton').click();
                    
                    // Hide the message after a few seconds
                    setTimeout(function() {
                        $('#ea-ticket-added').fadeOut(function() {
                            $(this).addClass('ea-hidden').html('');
                        });                        
                    }, 4000);

                } else if(res_obj.status == 'error') {
                    alert(res_obj.message); // No Success Message? Show any possible errors!
                } else {
                    alert(response);
                }
            });          
            
            return false; // Prevent any refresh of the page
        });
        
    }

    $('div#accordion-event-tickets').on('click', '.ea-remove-ticket', function(e) {
        e.preventDefault();
        
        if( confirm($(this).data('confirm-msg')) ) {
            var ticket_id = $(this).data('ticket-id');
            
            // Make an AJAX call to delete the ticket
            var data = {
                'ea_nonce': eventappi_ajax_admin_obj.nonce,
                'action': eventappi_ajax_admin_obj.plugin_name + '_del_ticket',
                'ticket_id': ticket_id,    
                'event_id': $('#post_ID').val()
            };
            
            $.post(eventappi_ajax_admin_obj.ajax_url, data, function(res) { 
                res_obj = $.parseJSON(res);
                
                if(res_obj.status == 'success') {
                    var target_acc_el = $('.event-ticket[data-id="'+ ticket_id +'"]').parent('.ui-accordion-content');
                    
                    target_acc_el.fadeOut();
                    target_acc_el.prev('h3').fadeOut();
                    
                    if(res_obj.tickets_left < 1) {
                        // Show the TimeZone Edit
                        $('#ea-timezone-show').hide();
                        $('.timezone-edit').show().prop('disabled', false);
                    }
                } else {
                    alert(LANG.ticket_del_error);
                }
            });                
        }    
    });

    $('body').on('click', '.ea-remove-field', function(e) {
        e.preventDefault();
        
        if( confirm($(this).data('confirm-msg')) ) {
            var reg_field_u_id = $(this).data('u-id'),
                ticket_id = $(this).data('ticket-id');
            
            // Make an AJAX call to delete the ticket
            var data = {
                'ea_nonce': eventappi_ajax_admin_obj.nonce,
                'action': eventappi_ajax_admin_obj.plugin_name + '_del_reg_field',
                'ticket_id': ticket_id,    
                'u_id': reg_field_u_id
            };
                        
            $.post(eventappi_ajax_admin_obj.ajax_url, data, function(res) {            
                if(res == 1) {
                    var target_acc_el = $('.reg-field[data-reg-field-u-id="'+ reg_field_u_id +'"]').parent();
                    
                    target_acc_el.fadeOut();
                    target_acc_el.prev('h3').fadeOut();
                    
                    if( $.trim($('#accordion-tickets-reg-fields-'+ ticket_id).html()) == '' ) {
                        $('#ea-reg-fields-preview-action-'+ ticket_id).hide();
                    } else {
                        $('#ea-reg-fields-preview-action-'+ ticket_id).show();
                    }
                }
            });                
        }    
    });

    function eaAddTicketToAccordion(ticket_id) {
        var data = {
            'action': eventappi_ajax_admin_obj.plugin_name + '_append_to_accordion_ticket',
            'ticket_id': ticket_id,    
            'event_id': $('#post_ID').val()
        };

        $.post(eventappi_ajax_admin_obj.ajax_url, data, function(accordionPanel) {            
            $('#accordion-event-tickets').append(accordionPanel).accordion('refresh');
            
            // Make TimeZone Uneditable and show the existent value
            $('#ea-timezone-show').fadeIn();
            $('.timezone-edit').hide().prop('disabled', true);
        });
    }
    
    // --- Add Registration Field - For Ticket ---
    
    // Required Area
    $('body').on('change', '.ea-reg-required', function() {
        if($(this).val() == 1) { // Yes
            $(this).parent().next().fadeIn();
        } else {
            $(this).parent().next().fadeOut();
        }
    });
    
    // Options Area - For Type: select (multiple), radio, checkbox
    $('#ea-reg-field-type').change(function() {
        
        if($(this).val() == 'select' || $(this).val() == 'select_m'
         || $(this).val() == 'radio' || $(this).val() == 'checkbox') {

            $('#ea-reg-options-area').fadeIn().removeClass('ea-hidden');

            // Show number of columns area for Radios and Checkboxes only
            if($(this).val() == 'radio' || $(this).val() == 'checkbox') {
                $('#ea-reg-columns-area').fadeIn().removeClass('ea-hidden');
            }

        } else {
            $('#ea-reg-options-area, #ea-reg-columns-area').fadeOut().addClass('ea-hidden');
        }
        
    });
    
    // Add New Registration Field - Button pressed
    $('#accordion-event-tickets').on('click', '.ea-add-new-reg-field-btn', function() {
        // Update Ticket ID
        $('#ea-add-ticket-reg-field-area').data('ea-ticket-id', $(this).data('ea-ticket-id'));
    });

    // Add New Registration Field - Form Submit
    $('#ea-add-ticket-reg-field-form').submit(function() {
        var ticket_id = $('#ea-add-ticket-reg-field-area').data('ea-ticket-id');

        // Dashboard - Edit Ticket Post
        if( ! ticket_id ) {
            ticket_id = $('#post_ID').val();
        }
        
        var data = {
            'action': eventappi_ajax_admin_obj.plugin_name + '_add_ticket_reg_field',
            'ticket_id': ticket_id,
            'post': $(this).serialize()
        }, form_obj = $(this), res_obj;

        $.post(eventappi_ajax_admin_obj.ajax_url, data, function(response) {
            res_obj = $.parseJSON(response);
            
            if(res_obj.status == 'success') {
                console.log(ticket_id);

                // Append a new panel to the accordion 
                // having the ticket's title and the meta box fields ready to edit
                eaAddTicketRegFieldToAccordion(res_obj.u_id, ticket_id);

                // Close the thickbox and hide the confirmation message after a few seconds
                $('#TB_closeWindowButton').click();
                
                $('#ea-added-reg-field-'+ ticket_id).fadeIn().removeClass('ea-hidden');
                
                // Show preview link
                $('#ea-reg-fields-preview-action-'+ ticket_id).removeClass('ea-hidden');
                
                setTimeout(function() {
                    $('#ea-added-reg-field-'+ ticket_id).fadeOut().addClass('ea-hidden');
                }, 4000);            
                
                // Clear the fields within the form
                form_obj.find(':input').each(function() {                    
                    switch(this.type) {
                        case 'password':
                        case 'select-multiple':
                        case 'select-one':
                        case 'text':
                        case 'number':
                        case 'textarea':
                            $(this).val('');
                        break;
                        
                        case 'checkbox':
                        case 'radio':
                            this.checked = false;
                    }
                });
            } else if(res_obj.status == 'error') {
                alert(res_obj.message); // No Success Message? Show any possible errors!
            } else {
                alert(response);
            }        
        });

        return false;                  
    });

    // Ticket Registration Fields Accordion
    if($('.accordion-tickets-reg-fields').length) {  

        function initRegFieldsAcc() {
            // Initiate Accordion
            $('.accordion-tickets-reg-fields').accordion({
                header: "> div > h3",
                collapsible: true,
                active: false
            }).sortable({
                axis: "y",
                handle: "h3",
                placeholder: "ui-state-highlight",
                stop: function( event, ui ) {

                    var ticket_id = ui.item.parent().data('ea-ticket-id');

                    $('#ea-sort-spinner-'+ ticket_id).css({'visibility':'visible'}).fadeIn();
                    
                    // IE doesn't register the blur when sorting
                    // so trigger focusout handlers to remove .ui-state-focus
                    ui.item.children( "h3" ).triggerHandler( "focusout" );
                               
                    var reg_fields_pos = '';
                    
                    $('.reg-field[data-ticket-id="'+ ticket_id +'"]').each(function(index, val) {
                        reg_fields_pos += $(val).attr('data-reg-field-u-id') + ',';
                    });
                    
                    // Update Registration Fields' Position           
                    $.post(eventappi_ajax_admin_obj.ajax_url, {
                        'action': eventappi_ajax_admin_obj.plugin_name + '_update_reg_fields_pos',
                        'reg_fields_pos': reg_fields_pos,
                        'ticket_id': ticket_id
                    }, function() {
                        $('#ea-sort-spinner-'+ ticket_id).css({'visibility':'hidden'}).fadeOut();
                    });                
                    
                    // Refresh accordion to handle new order
                    $( this ).accordion( "refresh" );
                }
            });
        }

        initRegFieldsAcc();

        function eaAddTicketRegFieldToAccordion(u_id, ticket_id) {
            var data = {
                'action': eventappi_ajax_admin_obj.plugin_name + '_append_to_accordion_reg_field',
                'u_id': u_id,
                'ticket_id': ticket_id
            };

            $.post(eventappi_ajax_admin_obj.ajax_url, data, function(accordionPanel) {            
                $('#accordion-tickets-reg-fields-'+ ticket_id).append(accordionPanel).accordion('refresh');
            });
        }        
    }
    
});