<?php
/**
 * The data array for this template contains:
 * - total     : the basket total
 * - actionUrl : the url to use for the form action
 * - userMeta  : user data for billing if present
 * - countries : a list of countries for the drop-down
 */
/**
 * for testing only - set environment vars to prevent having to re-enter CC data
 */
if (getenv('test_card_number') !== false) {
    $extraData['test_card_number'] = getenv('test_card_number');
    $extraData['test_card_name']   = getenv('test_card_name');
    $extraData['test_card_last']   = getenv('test_card_last');
    $extraData['test_card_cvv']    = getenv('test_card_cvv');
    $extraData['test_card_exp']    = getenv('test_card_exp');
}
?>

<div id="eventappi-wrapper" class="wrap">

    <h3>Total: $<?php echo money_format('%i', ($data['total'] / 100)); ?></h3>

    <form role="form" action="<?php echo $data['actionUrl']; ?>" method="post">
        <div class="row-fluid">
            <div class="col-lg-6">
                <div class="form-group">
                    <label>Email</label>
                    <input type="text" name="email" class="form-control paymentFormEmail" id="" placeholder="">
                </div>
                <div class="form-group">
                    <label>Billing Address line 1</label>
                    <input type="text" name="billing_address_1" class="form-control" id="" placeholder=""
                           value="<?php echo (is_array($data['userMeta'])) ? $data['userMeta']['address_1'] : null; ?>">
                </div>

                <div class="form-group">
                    <label>Billing Address line 2</label>
                    <input type="text" name="billing_address_2" class="form-control" id="" placeholder=""
                           value="<?php echo (is_array($data['userMeta'])) ? $data['userMeta']['address_2'] : null; ?>">
                </div>

                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="billing_city" class="form-control" id="" placeholder=""
                           value="<?php echo (is_array($data['userMeta'])) ? $data['userMeta']['city'] : null; ?>">
                </div>

                <div class="form-group">
                    <label>Zip / Postal code</label>
                    <input type="text" name="billing_postcode" class="form-control" id="" placeholder=""
                           value="<?php echo (is_array($data['userMeta'])) ? $data['userMeta']['postcode'] : null; ?>">
                </div>

                <div class="form-group">
                    <label>Country</label>
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
            <div class="col-lg-6">
                <div class="form-group">
                    <label>Credit card number</label>
                    <input name="card" type="text" class="form-control" id="" placeholder=""
                           value="<?php echo $extraData['test_card_number']; ?>">
                </div>

                <div class="form-group">
                    <label>First name on card</label>
                    <input name="firstName" type="text" class="form-control" id="" placeholder=""
                           value="<?php echo $extraData['test_card_name']; ?>">
                </div>

                <div class="form-group">
                    <label>Last name on card</label>
                    <input name="lastName" type="text" class="form-control" id="" placeholder=""
                           value="<?php echo $extraData['test_card_last']; ?>">
                </div>

                <div class="form-group">
                    <label>CVV</label>
                    <input name="cvv" type="text" class="form-control" placeholder=""
                           value="<?php echo $extraData['test_card_cvv']; ?>" id="">
                </div>

                <div class="form-group">
                    <label>Start date(if applicable)</label>
                    <input name="start_date" type="text" class="form-control" id="" placeholder="">
                </div>

                <div class="form-group">
                    <label>Expiry date</label>
                    <input name="expiry_date" type="text" class="form-control"
                           value="<?php echo $extraData['test_card_exp']; ?>"
                           id="" placeholder="">
                </div>

                <div class="form-group">
                    <label>Issue number (if applicable)</label>
                    <input name="issueNumber" type="text" class="form-control" id="" placeholder="">
                </div>
            </div>
        </div>
        <hr>
        <div class="row-fluid">
            <div class="col-lg-6">
            </div>
            <div class="col-lg-6">
                <button id="pay" class="btn btn-primary btn-lg pay">Pay</button>
            </div>
        </div>
        <input name="amount" type="hidden" class="form-control" value="<?php echo $data['total']; ?>">
        <input name="currency" type="hidden" class="form-control" value="USD">
        <input name="items" type="hidden" class="form-control" value="array">
    </form>
</div>
