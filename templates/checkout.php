<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * The data array for this template contains:
 * - total     : the basket total
 * - actionUrl : the url to use for the form action
 * - userMeta  : user data for billing if present
 * - countries : a list of countries for the drop-down
 */
?>

<div id="eventappi-wrapper" class="wrap">

    <h3><?php _e('Total: $', EVENTAPPI_PLUGIN_NAME); ?><?php echo money_format('%i', $data['total']); ?></h3>

    <form role="form" action="<?php echo $data['actionUrl']; ?>" method="post">
        <div class="row-fluid">
            <div class="col-lg-6">
                <div class="form-group">
                    <label><?php _e('Email', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input type="text" name="email" class="form-control paymentFormEmail" id="" placeholder="" />
                </div>
                <div class="form-group">
                    <label><?php _e('Billing Address line 1', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input type="text" name="billing_address_1" class="form-control" id="" placeholder="" value="<?php echo (is_array($data['userMeta'])) ? $data['userMeta']['address_1'] : null; ?>" />
                </div>

                <div class="form-group">
                    <label><?php _e('Billing Address line 2', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input type="text" name="billing_address_2" class="form-control" id="" placeholder="" value="<?php echo (is_array($data['userMeta'])) ? $data['userMeta']['address_2'] : null; ?>" />
                </div>

                <div class="form-group">
                    <label><?php _e('City', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input type="text" name="billing_city" class="form-control" id="" placeholder=""
                           value="<?php echo (is_array($data['userMeta'])) ? $data['userMeta']['city'] : null; ?>">
                </div>

                <div class="form-group">
                    <label><?php _e('Zip / Postal code', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input type="text" name="billing_postcode" class="form-control" id="" placeholder="" value="<?php echo (is_array($data['userMeta'])) ? $data['userMeta']['postcode'] : null; ?>" />
                </div>

                <div class="form-group">
                    <label><?php _e('Country', EVENTAPPI_PLUGIN_NAME); ?></label><br />
                    <select name="billing_country">
                        <?php foreach ($data['countries'] as $code => $country) : ?>
                            <?php
                            if (is_array($data['userMeta']) && strlen($data['userMeta']['country']) == 2) {
                                $selectCode = $data['userMeta']['country'];
                            } else {
                                $selectCode = 'US'; // default to US addresses
                            }
                            $selected = ($code == $selectCode) ? ' selected="selected"' : ''; ?>
                            <option value="<?php echo $code; ?>"<?php echo $selected; ?>>
                                <?php echo $country; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php if (intval($data['total']) > 0) : ?>
            <div class="col-lg-6">
                <div class="form-group">
                    <label><?php _e('Credit card number', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input name="card" type="text" class="form-control" id="" placeholder="" value="<?php echo $extraData['test_card_number']; ?>" />
                </div>

                <div class="form-group">
                    <label><?php _e('First name on card', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input name="firstName" type="text" class="form-control" id="" placeholder="" value="<?php echo $extraData['test_card_name']; ?>">
                </div>

                <div class="form-group">
                    <label><?php _e('Last name on card', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input name="lastName" type="text" class="form-control" id="" placeholder="" value="<?php echo $extraData['test_card_last']; ?>" />
                </div>

                <div class="form-group">
                    <label><?php _e('CVV', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input name="cvv" type="text" class="form-control" placeholder="" value="<?php echo $extraData['test_card_cvv']; ?>" id="" />
                </div>

                <div class="form-group">
                    <label><?php _e('Start date (if applicable)', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input name="start_date" type="text" class="form-control" id="" placeholder="" />
                </div>

                <div class="form-group">
                    <label><?php _e('Expiry date', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input name="expiry_date" type="text" class="form-control" value="<?php echo $extraData['test_card_exp']; ?>" id="" placeholder="" />
                </div>

                <div class="form-group">
                    <label><?php _e('Issue number (if applicable)', EVENTAPPI_PLUGIN_NAME); ?></label>
                    <input name="issueNumber" type="text" class="form-control" id="" placeholder="" />
                </div>
            </div>
            <?php endif; ?>
        </div>
        <hr>
        <div class="row-fluid">
            <div class="col-lg-6">
            </div>
            <div class="col-lg-6">
                <button id="pay" class="btn btn-primary btn-lg pay"><?php _e('Pay', EVENTAPPI_PLUGIN_NAME); ?></button>
            </div>
        </div>
        <input name="amount" type="hidden" class="form-control" value="<?php echo $data['total']; ?>">
        <input name="currency" type="hidden" class="form-control" value="USD" />
        <input name="items" type="hidden" class="form-control" value="array" />
    </form>
</div>
