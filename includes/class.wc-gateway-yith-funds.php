<?php
if ( !defined( 'ABSPATH' ) )
    exit;

if ( !class_exists( 'WC_Gateway_YITH_Funds' ) ) {

    class WC_Gateway_YITH_Funds extends WC_Payment_Gateway
    {


        public function __construct()
        {
            $this->id = 'yith_funds';
            $this->icon = '';
            $this->has_fields = false;
            $this->method_title = __( 'YITH Funds', 'yith-woocommerce-account-funds' );
            $this->method_description = __( 'Allow credit payments (use available funds)', 'yith-woocommerce-account-funds' );
            $this->supports           = array(
                'refunds'
            );
            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option( 'title' );

            $user_id = get_current_user_id();

            if ( $user_id ) {
                $customer = new YITH_YWF_Customer( $user_id );

                $funds = apply_filters( 'yith_show_available_funds', $customer->get_funds() );

                $message = sprintf( '%s %s.', __( 'Available funds', 'yith-woocommerce-account-funds' ), wc_price( $funds ) );

                if ( ywf_enable_discount() ) {

                    $discount_type = ywf_get_discount_type();
                    $discount_value = apply_filters( 'yith_discount_value', ywf_get_discount_value(), $discount_type );

                    if ( $discount_type == 'fixed_cart' ) {
                        $discount_value = wc_price( $discount_value );
                    } else {
                        $discount_value = wc_format_localized_decimal( $discount_value );
                        $discount_value .= '%';
                    }

                    $message .= sprintf( '<br>%s %s %s.', __( 'If you choose to pay through your available funds and funds are enough to cover the whole order amount, you can get a','yith-woocommerce-account-funds'), $discount_value,__('discount', 'yith-woocommerce-account-funds' ) );
                }
                $this->description = $message;
            }

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        /**
         * process payment
         * @param int $order_id
         * @return array|void
         */
        public function process_payment( $order_id )
        {

            if ( !is_user_logged_in() ) {
                wc_add_notice( __( 'Payment error:', 'yith-woocommerce-account-funds' ) . ' ' . __( 'You must be logged in to use this payment method', 'yith-woocommerce-account-funds' ), 'error' );
                return;
            }
            $order = wc_get_order( $order_id );
            $user_id = $order->get_user_id();
            $customer = new YITH_YWF_Customer( $user_id );
            $funds = apply_filters( 'yith_show_available_funds', $customer->get_funds());
            $order_total =yit_get_prop( $order, 'total', true,'edit' );
            
           
            if ( $funds < $order_total ) {
                if ( ywf_partial_payment_enabled() ) {
                    WC()->session->reload_checkout = false;
                    WC()->session->ywf_partial_payment = 'yes';
                    return;
                } else {
                    wc_add_notice( __( 'Payment error:', 'yith-woocommerce-account-funds' ) . ' ' . __( 'Insufficient account balance', 'yith-woocommerce-account-funds' ), 'error' );
                    return;
                }
            } else {

                $order_total_base_currency = apply_filters( 'yith_admin_order_total', $order_total, $order_id );
                yit_save_prop( $order, array(
                    '_order_funds'=> $order_total_base_currency ,
                    '_order_fund_removed' => 'no' )
                );
                
                global $YITH_FUNDS;

                if( !$YITH_FUNDS->is_wc_2_7 ){

                    $order->set_total( 0 );
                }
                $order->payment_complete();

                WC()->cart->empty_cart();
                // Return thankyou redirect
                return array( 'result' => 'success', 'redirect' => $this->get_return_url( $order ) );
            }
        }

        public function process_refund( $order_id, $amount = null, $reason = '' )
        {
            
            try{
                
                $order = wc_get_order( $order_id );
                $funds_used = get_post_meta( $order_id, '_order_funds', true );
                $funds_refunded = get_post_meta( $order_id,'_order_funds_refunded', true );
                $funds_refunded = $funds_refunded === '' ? 0 : $funds_refunded;

                $customer = new YITH_YWF_Customer( $order->get_user_id() );
                $amount_base_currency = apply_filters('yith_refund_amount_base_currency', $amount , $order_id );

                $customer->add_funds( $amount_base_currency );


                $funds_refunded = wc_format_decimal( $funds_refunded+$amount_base_currency , wc_get_price_decimals() );
                update_post_meta( $order_id, '_order_funds_refunded', $funds_refunded );

                $order_note = sprintf( __( 'Add %s funds to customer #%s', 'yith-woocommorce-funds' ), wc_price( $amount , array( 'currency' => get_post_meta( $order_id, '_order_currency', true ) ) ), $order->get_user_id() );
                $order->add_order_note( $order_note );

                $default = array(
                    'user_id' => $order->get_user_id(),
                    'order_id' => $order_id,
                    'fund_user'	=> $amount_base_currency,
                    'type_operation' => 'restore',
                    'description' => $reason
                );
                do_action('ywf_add_user_log', $default );

			/*if ( ! $amount || $max_refund < $amount || 0 > $amount ) {
                throw new exception( __( 'Invalid refund amount', 'woocommerce' ) );
            }*/
                
                return true;
                
            }catch( Exception $e ){ new WP_Error( 'refund_order_funds', $e->getMessage() );}
        }

        /**
         * check if this gateway is available
         * @author YITHEMES
         * @since 1.0.0
         * @return bool
         */
        public function is_available()
        {

            $is_available = false;
            $user_id = get_current_user_id();

            if ( $user_id ) {
                $is_available = ( 'yes' === $this->enabled );
                $customer = new YITH_YWF_Customer( $user_id );
                $funds = $customer->get_funds();


                if( apply_filters('ywf_is_available_fund_gateway', $funds<=0, $funds, $user_id )  ){
                    $is_available = false;
                }


                $endpoint = WC()->query->get_current_endpoint();
                $make_deposit_endpoint = apply_filters( 'ywf_make_deposit_slug', 'make-a-deposit' );

                if( ( isset( WC()->session->ywf_partial_payment ) && WC()->session->ywf_partial_payment === 'yes' ) || ( $make_deposit_endpoint === $endpoint ) || ywf_is_make_deposit() || !empty( $wp->query_vars['order-pay'] ) ) {
                    $is_available = false;
                }
            }

            return $is_available;
        }

        public function  init_form_fields()
        {
            $this->form_fields = array( 'enabled' => array( 'title' => __( 'Enable/Disable', 'yith-woocommerce-account-funds' ), 'type' => 'checkbox', 'label' => __( 'Enable customers to use their funds as payment gateway', 'yith-woocommerce-account-funds' ), 'default' => 'yes' ), 'title' => array( 'title' => __( 'Title', 'woocommerce' ), 'type' => 'text', 'description' => __( 'This controls the title that users sees during checkout.', 'yith-woocommerce-account-funds' ), 'default' => __( 'Funds', 'yith-woocommerce-account-funds' ), 'desc_tip' => true, ),

            );
        }
    }
}