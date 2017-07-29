<?php
if( !defined( 'ABSPATH' ) )
    exit;

if( !class_exists( 'YITH_YWF_Deposit_Fund_Checkout' ) ){

    class YITH_YWF_Deposit_Fund_Checkout{

        protected static  $instance;

        public function __construct()
        {
            add_filter( 'woocommerce_available_payment_gateways', array( $this, 'available_payment_gateways' ), 20 );

            //deposit checkout

            //add_filter( 'woocommerce_product_class', array( $this, 'get_product_deposit_class' ), 20 ,4 );

            add_action( 'woocommerce_before_checkout_process', array( $this, 'deposit_checkout_process' ) );

            //check user profile
            add_action( 'before_make_a_deposit_form', array( $this, 'display_available_user_funds' ), 10 );
            add_action( 'woocommerce_customer_save_address', array( $this, 'redirect_to_make_a_deposit' ) );
            
            
        }

                /**
         * @author YITHEMES
         * @since 1.0.0
         * @return YITH_Funds unique access
         */
        public static function get_instance()
        {

            if ( is_null( self::$instance ) ) {

                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * check if amount is right and set the session
         * @author YITHEMES
         * @since 1.0.0
         */
        public function validate_amount(){
            $amount = wc_format_decimal( $_REQUEST['amount_deposit'] );

            if( ''=== $amount || !is_numeric( $amount ) ){
                wc_add_notice( __( 'Enter a price', 'yith-woocommerce-account-funds' ), 'error' );
                return false;
            }

            $amount = floatval( $amount );
            $min = floatval( wc_format_decimal( ywf_get_min_fund_rechargeable() ) );
            $max =  ywf_get_max_fund_rechargeable() ;


            if ( $amount < $min ) {
                wc_add_notice( sprintf( '%s %s', __( 'Minimum deposit amount is', 'yith-woocommerce-account-funds' ), wc_price( $min ) ), 'error' );
                return false;
            }

            if( $max!='' ) {
                $max = floatval( wc_format_decimal( $max ) );

                if ( $amount > $max ) {
                    wc_add_notice( sprintf( '%s %s', __( 'Maximum deposit amount is', 'yith-woocommerce-account-funds' ), wc_price( $max ) ), 'error' );
                    return false;
                }
            }

            return $amount;
        }


        /**
         * custom process checkout
         * @author YITHEMES
         * @since 1.0.0
         */
        public function deposit_checkout_process(){
            if ( ! isset( $_POST['amount_deposit'] ) ) {
                return;
            }

            $posted = array();
            $posted['payment_method'] = isset( $_POST['payment_method'] ) ? stripslashes( $_POST['payment_method'] ) : '';
            $posted['deposit_amount'] = $this->validate_amount();

            WC()->session->set( 'chosen_payment_method', $posted['payment_method'] );

            // Payment Method
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();

            if ( ! isset( $available_gateways[ $posted['payment_method'] ] ) ) {
                $payment_method = '';
                wc_add_notice( __( 'Invalid payment method.', 'woocommerce' ), 'error' );
            } else {
                /** @var WC_Payment_Gateway $payment_method */
                $payment_method = $available_gateways[ $posted['payment_method'] ];
                $payment_method->validate_fields();
            }

            $posted['payment_method'] = $payment_method;

            // Abort if errors are present
            if ( wc_notice_count( 'error' ) > 0 )
                throw new Exception();

            $order_id = $this->create_order( $posted );

            if ( is_wp_error( $order_id ) ) {
                throw new Exception( $order_id->get_error_message() );
            }

            // Store Order ID in session so it can be re-used after payment failure
            WC()->session->order_deposit_awaiting_payment = $order_id;

            // Process Payment
            $result = $posted['payment_method']->process_payment( $order_id );

            // Redirect to success/confirmation/payment page
            if ( isset( $result['result'] ) && 'success' === $result['result'] ) {

                $result = apply_filters( 'woocommerce_payment_successful_result', $result, $order_id );

                if ( is_ajax() ) {
                    wp_send_json( $result );
                } else {
                    wp_redirect( $result['redirect'] );
                    exit;
                }

            }

            // If we reached this point then there were errors
            if ( is_ajax() ) {

                // only print notices if not reloading the checkout, otherwise they're lost in the page reload
                if ( ! isset( WC()->session->reload_checkout ) ) {
                    ob_start();
                    wc_print_notices();
                    $messages = ob_get_clean();
                }

                $response = array(
                    'result'	=> 'failure',
                    'messages' 	=> isset( $messages ) ? $messages : '',
                    'refresh' 	=> isset( WC()->session->refresh_totals ) ? 'true' : 'false',
                    'reload'    => isset( WC()->session->reload_checkout ) ? 'true' : 'false'
                );

                unset( WC()->session->refresh_totals, WC()->session->reload_checkout );

                wp_send_json( $response );
            }

        }

        /**
         * Create the order for deposit
         * @author YITHEMES
         * @since 1.0.0
         * @param $posted array
         * @return int|WP_Error
         */
        public function create_order( $posted ) {
            try {
                // Start transaction if available
                wc_transaction_query( 'start' );
                $customer_id = apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() );

                $order_data = array(
                    'status'        => apply_filters( 'woocommerce_default_order_status', 'pending' ),
                    'customer_id'   => $customer_id,
                    'customer_note' => '',
                    'created_via'   => 'checkout'
                );

                $order_id = absint( WC()->session->order_deposit_awaiting_payment );

                // Resume the unpaid order if its pending
                if ( $order_id > 0 && ( $order = wc_get_order( $order_id ) ) && $order->has_status( array( 'pending', 'failed' ) ) ) {

                    $order_data['order_id'] = $order_id;
                    $order                  = wc_update_order( $order_data );

                    if ( is_wp_error( $order ) ) {
                        throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce' ), 522 ) );
                    } else {
                        $order->remove_order_items();
                        do_action( 'woocommerce_resume_order', $order_id );
                    }

                } else {

                    $order = wc_create_order( $order_data );

                    if ( is_wp_error( $order ) ) {
                        throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce' ), 520 ) );
                    } elseif ( false === $order ) {
                        throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce' ), 521 ) );
                    } else {
                        $order_id = yit_get_prop( $order, 'id', 'edit' );
                        do_action( 'woocommerce_new_order', $order_id );
                    }
                }

                global $YITH_FUNDS;

                // Add the product
                $product_id = get_option( '_ywf_deposit_id' );

                $product = wc_get_product( $product_id );

               // $product->{$function_name}($posted['deposit_amount']);
                
                $item_id = $this->add_deposit_product( $order, $product, $posted['deposit_amount'] );

                if ( ! $item_id ) {
                    throw new Exception( sprintf( __( 'Error %d: Unable to create order. Please try again.', 'woocommerce' ), 525 ) );
                }

                // Allow plugins to add order item meta
                do_action( 'woocommerce_add_deposit_order_item_meta', $item_id, array( 'data' => $product, 'amount' => $posted['deposit_amount'] ) );

                $this->set_order_details( $order, $posted );
                
                // Let plugins add meta
                do_action( 'woocommerce_checkout_update_order_deposit_meta', $order_id, $posted );

                // If we got here, the order was created without problems!
                wc_transaction_query( 'commit' );

            }
            catch ( Exception $e ) {
                // There was an error adding order data!
                wc_transaction_query( 'rollback' );
                return new WP_Error( 'checkout-error', $e->getMessage() );
            }

            return $order_id;
        }

        /**
         * @param WC_Order $order
         * @param WC_Product $product
         * @param string $amount
         * @since 1.0.8
         */
        public function add_deposit_product( $order, $product, $amount ){
            global $YITH_FUNDS;
            if( $YITH_FUNDS->is_wc_2_7 ){
                $item_id = $order->add_product(
                    $product,
                    1,
                    array(
                        'subtotal' => $amount,
                        'subtotal_tax' => 0,
                        'total' => $amount,
                        'tax' => 0,
                    )

                );
            }else {

                $item_id = $order->add_product(
                    $product,
                    1,
                    array(
                        'totals' => array(
                            'subtotal' => $amount,
                            'subtotal_tax' => 0,
                            'total' => $amount,
                            'tax' => 0,
                            //'tax_data'     => array() // Since 2.2
                        )
                    )
                );
            }

            return $item_id;
        }

        /**
         * @param WC_Order $order
         * @param array $posted
         * @since 1.0.8
         */
        public function set_order_details( $order, $posted ){

            global $YITH_FUNDS;
            // Set order details
            $order->set_payment_method( $posted['payment_method'] );
            $order->set_total( $posted['deposit_amount'] );



            if( $YITH_FUNDS->is_wc_2_7 ) {
                yit_set_prop( $order, array( 'order_tax' => 0, 'order_shipping_tax' => 0, 'order_shipping' => 0 ) );
                $order->save();
            }else{
                yit_save_prop( $order, array( '_order_tax' => 0, '_order_shipping_tax' => 0, '_order_shipping' => 0 ) );
            }
        }
      

        /**
         * return custom product deposit class
         * @author YITHEMES
         * @since 1.0.0
         * @param $classname
         * @param $product_type
         * @param $post_type
         * @param $product_id
         * @return string
         */
        public function get_product_deposit_class($classname, $product_type, $post_type, $product_id ){

            global $YITH_FUNDS;

            if( !$YITH_FUNDS->is_wc_2_7 ) {

                $page_id = wc_get_page_id( 'myaccount' );

                if( $page_id === $product_id ) {
                    $classname = 'YITH_YWF_Product_Deposit';
                }
            }

         
            return $classname;
        }

        /**
         *
         * @author YITHEMES
         * @since 1.0.0
         */
        public function display_available_user_funds(){

            wc_get_template('view-customer-fund.php', array('text_align' => 'left', 'font_weight'=>'normal'), '', YITH_FUNDS_TEMPLATE_PATH );
        }

        /**
         * @author YITHEMES
         * @since 1.0.0
         */
        public function redirect_to_make_a_deposit(){

            $make_deposit_endpoint = apply_filters( 'ywf_make_deposit_slug', 'make-a-deposit' );
            if( isset( $_GET['return_to'] ) && $make_deposit_endpoint === $_GET['return_to'] ){

                $url = wc_get_page_permalink('myaccount');
                $endpoint_url = esc_url( wc_get_endpoint_url( $make_deposit_endpoint,'',$url ) );

                wp_redirect( $endpoint_url );
                exit;
            }
        }


        /**set gateways 
         * @author YIThemes
         * @since 1.0.8
         * @param $gateways
         * @return mixed
         */
        public function available_payment_gateways( $gateways ){

            $current_end_point = WC()->query->get_current_endpoint();

            if ( 'make-a-deposit' == $current_end_point ) {

                $deposit_payments = get_option('ywf_select_gateway');

                if ( !empty( $deposit_payments ) ) {

                    foreach ( $gateways as $key => $gateway) {
                        if ( !in_array( $key, $deposit_payments ) ) {
                            unset( $gateways[$key] );
                        }
                    }
                }
            }
            return $gateways;
        }

    }
}
function YITH_YWF_Deposit_Fund_Checkout(){

    return YITH_YWF_Deposit_Fund_Checkout::get_instance();
}
