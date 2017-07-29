<?php
if( !defined('ABSPATH')){
    exit;
}

$price_format = get_woocommerce_price_format();

$currency = '<span class="ywf_currency_symbol">'.get_woocommerce_currency_symbol().'</span>';
$input_number = sprintf('<input type="number" name="amount" placeholder="%s" class="ywf_deposit " min="%s" %s step="any">', __('Enter amount','yith-woocommerce-account-funds'),$min, $max );
?>
<div class="ywf_make_a_deposit_form">
    <form method="get" action="<?php echo ywf_get_endpoint_url('make_a_deposit');?>">
        <p><?php echo sprintf($price_format, $currency, $input_number );?></p>
        <input type="submit" class="button" value="<?php _e('Deposit now','yith-woocommece-funds');?>" >
    </form>
</div>
<?php
