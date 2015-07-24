jQuery(document).ready(function($) {
    function blockTheUI() {
        $.blockUI({
            css: {
                border: 'none',
                padding: '15px',
                backgroundColor: '#000',
                '-webkit-border-radius': '10px',
                '-moz-border-radius': '10px',
                opacity: .5,
                color: '#fff',
                'z-index': 9000
            }
        });
    }

    function unBlockTheUI() {
        setTimeout($.unblockUI, 100);
    }


    var self = $(this);


    if (self.find('#eventappi-wrapper').length) {

        var self = self.find('#eventappi-wrapper');

        if(self.find('#your-profile').length) {

            $('#your-profile').submit(function() {
                /* If either the new or confirm password field is filled */
                if($('#eventappi_pass1').val() != '' || $('#eventappi_pass2').val() != '') {
                    if( $('#eventappi_pass1').val() != $('#eventappi_pass2').val() ) {
                        alert(eventappi_ajax_obj.text.pass_not_match_error);
                        return false;
                    }
                }
            });
        }

        //START DIALOG
        self.find('.send').on('click', function () {
            event.preventDefault();
            $('#dialog-form-send').find('#form-send-ticket').find('#hash-st').val($(this).attr('data-hash'));
            sendDialog.dialog('open');
        });

        self.find('.assign').on('click', function (event) {
            event.preventDefault();
            $('#dialog-form-assign').find('#form-assign-ticket').find('#hash-at').val($(this).attr('data-hash'));
            assignDialog.dialog('open');
        });

        self.find('.claim').on('click', function (event) {
            event.preventDefault();
            $('#dialog-form-claim').find('#form-claim-ticket').find('#hash-ct').val($(this).attr('data-hash'));
            claimDialog.dialog('open');
        });

        function sendTicket() {
            blockTheUI();
            var sendData = [];
            var dialogFormSendTicket = $('#dialog-form-send').find('#form-send-ticket');
            sendData.push({'name': '_ajax_nonce', 'value': eventappi_ajax_obj.nonce});
            sendData.push({'name': 'action', 'value': eventappi_ajax_obj.plugin_name + '_send_ticket'});
            dialogFormSendTicket.find('input').each(function () {
                sendData.push({'name': $(this).attr('name'), 'value': $(this).val()});
            });

            $.ajax({
                url: eventappi_ajax_obj.ajax_url,
                type: 'POST',
                data: sendData
            });

            //location.assign(eventappi_ajax_obj.my_account_url);
        }

        if($('#dialog-form-send').length > 0) {
            var sendDialog = $('#dialog-form-send').dialog({
                autoOpen: false,
                height: 500,
                width: 650,
                modal: true,
                buttons: {
                    'Send': sendTicket,
                    Cancel: function () {
                        sendDialog.dialog('close');
                    }
                },
                close: function () {
                    sendDialog.dialog('close');
                }
            });
        }

        function assignTicket() {
            var assignData = [];
            var dialogFormAssignTicket = $('#dialog-form-assign').find('#form-assign-ticket');
            assignData.push({'name': '_ajax_nonce', 'value': eventappi_ajax_obj.nonce});
            assignData.push({'name': 'action', 'value': eventappi_ajax_obj.plugin_name + '_assign_ticket'});
            dialogFormAssignTicket.find('input').each(function () {
                var theName = $(this).attr('name');
                var theValue = $(this).val();
                if (theName && !theValue) {
                    alert(eventappi_ajax_obj.text.assign_ticket_error);
                    throw '';
                }
                assignData.push({'name': theName, 'value': theValue});
            });

            $.ajax({
                url: eventappi_ajax_obj.ajax_url,
                type: 'POST',
                data: assignData
            });
            location.assign(eventappi_ajax_obj.my_account_url);
        }

        if($('#dialog-form-assign').length > 0) {
            var assignDialog = $('#dialog-form-assign').dialog({
                autoOpen: false,
                height: 500,
                width: 650,
                modal: true,
                buttons: {
                    'Assign': assignTicket,
                    Cancel: function () {
                        assignDialog.dialog('close');
                    }
                },
                close: function () {
                    assignDialog.dialog('close');
                }
            });
        }

        function claimTicket() {
            var claimData = [];
            var dialogFormClaimTicket = $('#dialog-form-claim').find('#form-claim-ticket');
            claimData.push({'name': '_ajax_nonce', 'value': eventappi_ajax_obj.nonce});
            claimData.push({'name': 'action', 'value': eventappi_ajax_obj.plugin_name + '_claim_ticket'});
            dialogFormClaimTicket.find('input').each(function () {
                var theName = $(this).attr('name');
                var theValue = $(this).val();
                if (theName && !theValue) {
                    alert(eventappi_ajax_obj.text.claim_ticket_error);
                    throw '';
                }
                claimData.push({'name': theName, 'value': theValue});
            });

            $.ajax({
                url: eventappi_ajax_obj.ajax_url,
                type: 'POST',
                data: claimData
            });
            location.assign(eventappi_ajax_obj.my_account_url);
        }

        if($('#dialog-form-claim').length > 0) {
            var claimDialog = $('#dialog-form-claim').dialog({
                autoOpen: false,
                height: 500,
                width: 650,
                modal: true,
                buttons: {
                    'Claim': claimTicket,
                    Cancel: function () {
                        claimDialog.dialog('close');
                    }
                },
                close: function () {
                    claimDialog.dialog('close');
                }
            });
        }
        //END OF DIALOG

        self.find('.show-form').on('click', function (event) {
            event.preventDefault();

            if ($(this).attr('data-target') === 'venue') {
                self.find('select[name="venue"]').hide();
            }

            var newForm = self.find('#' + $(this).attr('data-target')).show();
            $(this).after(newForm);
            $(this).remove();
        });

        self.find('#pay').on('click', function (event) {
            event.preventDefault();

            blockTheUI();

            var paymentForm = $(this).parents('form:first').serializeArray();
            var email = '';

            $.each(paymentForm, function (index, value) {
                if (value.name === 'email') {
                    email = value.value;
                    return false;
                }
            });

            if (email !== '') {
                var userId = null;

                $.ajax({
                    url: eventappi_ajax_obj.ajax_url,
                    type: 'POST',
                    data: {
                        _ajax_nonce: eventappi_ajax_obj.nonce,
                        action: eventappi_ajax_obj.plugin_name + '_user_create',
                        email: email,
                        type_id: 4
                    },
                    success: function (data) {
                        var json = JSON.parse(data);
                        if (json == 'require login') {
                            location.assign(eventappi_ajax_obj.login_url);
                        }
                        userId = json.user_id;
                    }
                }).done(function () {
                    if (userId !== null) {

                        paymentForm.push({'name': '_ajax_nonce', 'value': eventappi_ajax_obj.nonce});
                        paymentForm.push({'name': 'action', 'value': eventappi_ajax_obj.plugin_name + '_pay'});
                        paymentForm.push({'name': 'user_id', 'value': userId});

                        var result;

                        $.ajax({
                            url: eventappi_ajax_obj.ajax_url,
                            type: 'POST',
                            data: paymentForm,
                            success: function (data) {
                                result = data;
                            }
                        }).done(function () {
                            $('body').find('#eventappi-wrapper').html('<h4>' + result + '</h4>')
                                .animate({scrollTop: $('#eventappi-wrapper').offset().top}, 1000);
                            unBlockTheUI();
                        });
                    }
                });
            }
        });

        if (self.find('.ticket-quantity').length) {
            self.find('.ticket-quantity').on('keyup', function () {
                var avail = $(this).attr("placeholder").replace(' available', '');
                var wants = $(this).val();
                if (wants == undefined || wants == '') {
                    wants = 0;
                }
                if (parseInt(wants) > parseInt(avail)) {
                    alert(eventappi_ajax_obj.text.not_enough_tickets);
                    $(this).parent().find('.ticket-quantity').val(avail);
                    return false;
                }

                // Only in the Cart Page
                if($('#cart-total').length > 0) {
                    var ticketPrice = $(this).parent().find('.ticket-price').val();
                    var ticketQuantity = $(this).parent().find('.ticket-quantity').val();

                    var ticketPriceSpan = $(this).parent().parent().find('.full-price span');
                    var newTicketPrice = ( (ticketPrice * ticketQuantity) / 100 ).toFixed(2);
                    ticketPriceSpan.text(newTicketPrice);

                    var totalPrice = 0;

                    self.find('.full-price span').each(function (index, value) {
                        totalPrice += parseFloat(self.find(value).text().replace(',', ''));
                    });

                    self.find('#cart-total').text(totalPrice.toFixed(2));
                }
            });
        }


        if (self.find('#event-list').length) {
            self.find('#content article header, #content article .entry-content').css('max-width', '100%');
        }

        if (self.find('.tickets').length) {
            self.find('.event').after('<div id="dialog" title="'+ eventappi_ajax_obj.text.purchase_successful_title +'"><p>'+ eventappi_ajax_obj.text.thank_you_purchase +'</p></div>');
        }

        self.find('.go-back').on('click', function (event) {
            event.preventDefault();
            window.history.back();
            return false;
        });

        self.find('.go-back-to-cart').on('click', function (event) {
            event.preventDefault();
            location.assign(eventappi_ajax_obj.cart_url);
            return false;
        });

        if (self.find('#eventappi-cart').length) {

            self.find('#go-to-checkout').on('click', function (event) {
                event.preventDefault();
                location.assign(eventappi_ajax_obj.checkout_url);
            });

            self.find('#eventappi-cart .remove').on('click', function (event) {
                event.preventDefault();

                var ticketQuantity = $(this).parent().parent().find('.ticket-quantity').val();
                var ticketPrice = (parseFloat($(this).parent().parent().find('.ticket-price').val()));
                var cartTotal = (parseFloat($(this).parent().parent().parent().find('#cart-total').text()) * 100);

                var cartTotalSpan = $(this).parent().parent().parent().find('#cart-total');
                var newTotal = cartTotal - ( ticketQuantity * ticketPrice );
                newTotal = (newTotal / 100).toFixed(2);
                cartTotalSpan.text(newTotal);

                var target = $(this);
                var dataObject = [];

                dataObject.push({'name': 'id', 'value': target.attr('data-id')});
                dataObject.push({
                    'name': 'action',
                    'value': eventappi_ajax_obj.plugin_name + '_shopping_cart_remove'
                });
                dataObject.push({'name': '_ajax_nonce', 'value': eventappi_ajax_obj.nonce});

                $.ajax({
                    url: eventappi_ajax_obj.ajax_url,
                    type: 'POST',
                    data: dataObject,
                    success: function (data) {
                        if (data == '1') {
                            var oldTotal = parseFloat(self.find('.total').text());
                            oldTotal = oldTotal - parseFloat(target.closest('tr').find('.full-price span').text());
                            self.find('.total').text(oldTotal);
                            target.closest('tr').remove();
                        } else {
                            alert('1: ' + eventappi_ajax_obj.text.cart_item_fail_del);
                        }
                    },
                    error: function () {
                        alert('2: '+ eventappi_ajax_obj.text.cart_item_fail_del);
                    }
                });
            });
        }


    }

    var eaErrArea = $('#ea-event-no-tickets-error');

    if(eaErrArea.length > 0) {
        jQuery.post(eventappi_ajax_obj.ajax_url, {
            'action': eventappi_ajax_obj.plugin_name + '_check_event_api',
            'event_id': eaErrArea.data('event-id')
        }, function(output) {
            if(output !== '') {
                eaErrArea.html(output).fadeIn();
            }
        });
    }

    // Clear Image - Create Event
    $('.ea-reg-file-clear').click(function(e) {
        e.preventDefault();
        var regFile = $(this).prev('input');
        regFile.replaceWith( regFile.clone( true ) );
    });
});
