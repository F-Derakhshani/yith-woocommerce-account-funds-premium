<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'woocommerce_cart_needs_payment', '__return_true', 20 );
add_filter( 'woocommerce_checkout_show_terms', '__return_false', 20 );?>
<div class="make_a_deposit_checkout">
<?php

    /**
     * @Hooks
     * display_available_user_funds - 10
     */
     do_action('before_make_a_deposit_form' );

    if( $user_profile_complete ){

        $hide_form = ( isset( $amount ) && $amount!=='' );
        $min = ywf_get_min_fund_rechargeable();
        $max = ywf_get_max_fund_rechargeable();
        $currency = get_woocommerce_currency();

        $amount = apply_filters( 'yith_amount_to_deposit', $amount, $currency );
        $hide_form = $hide_form && ( $amount>=$min && ( $max==='' || $amount<=$max ) );

        $message = sprintf('<p>%s <strong>%s</strong><a href="" class="ywf_show_form">%s</a></p>',__('You are loading','yith-woocommerce-account-funds'), wc_price( $amount ),__('Enter a different amount','yith-woocommerce-account-funds'));
        ?>
        <div class="ywf_message_amount <?php echo $hide_form ? 'ywf_show' : '';?>">
            <?php echo $message;?>
        </div>
        <div class="ywf_make_a_deposit_container">
            <?php
            $price_format = get_woocommerce_price_format();
            $currency = get_woocommerce_currency_symbol();
            $input_number = sprintf('<input type="number" name="amount_deposit" placeholder="%s" class="ywf_deposit" min="%s" %s value="%s" step="any">', __('Enter amount','yith-woocommerce-account-funds'),$min,$max,$amount);
            ?>
            <form id="make-a-deposit" class="checkout woocommerce-checkout" name="make_a_deposit" method="post" >
                <p class="ywf_amount_input_container <?php echo $hide_form ? 'ywf_hide' : '';?>">
                    <label for="amount_deposit"><?php _e( 'Amount', 'yith-woocommerce-account-funds' ); ?></label>
                    <?php
                    $price_format = get_woocommerce_price_format();
                    $currency = '<span class="ywf_currency_symbol">'.get_woocommerce_currency_symbol().'</span>';
                    $input_number = '<input type="number" name="amount_deposit" placeholder="'.__('Enter amount','yith-woocommerce-account-funds').'"class="ywf_deposit " min="'. $min.'"'.$max.' value="'.$amount.'" step="any">';
                    echo '<span class="ywf_deposit_content">'. sprintf($price_format, $currency, $input_number ).'</span>';
                    ?>

                </p>
                <?php wc_get_template( 'checkout/payment.php', $payment ); ?>
            </form>
        </div>
    <?php
    }else{

        $url = wc_get_page_permalink('myaccount');
        $make_deposit_endpoint = apply_filters( 'ywf_make_deposit_slug', 'make-a-deposit' );
        $endpoint_url = esc_url( add_query_arg( array('return_to' => $make_deposit_endpoint ) , wc_get_endpoint_url('edit-address/billing/','',$url)));
        $button = sprintf('<a href="%s" class="button wc-foward">%s</a>',$endpoint_url,__('Complete your profile','yith-woocommerce-account-funds' ));
        $message = sprintf('%s %s ', $button,__('In order to make a new deposit, you must have completed your user profile. Complete it now!','yith-woocommerce-account-funds')  );
        $messages  = array( $message );
        $messages['messages'] = $messages;
        wc_get_template('notices/error.php',$messages );
    }
    do_action('after_make_a_deposit_form' );
    ?>

    </div>
<?php
remove_filter( 'woocommerce_checkout_show_terms', '__return_false', 20 );
remove_filter( 'woocommerce_cart_needs_payment', '__return_true',20 );