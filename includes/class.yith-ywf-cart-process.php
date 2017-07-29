<?php
if ( !defined( 'ABSPATH' ) )
    exit;

if ( !class_exists( 'YITH_YWF_Cart_Process' ) ) {

    class YITH_YWF_Cart_Process
    {
        protected static $_instance;


        public function __construct()
        {
            add_action( 'wp_enqueue_scripts', array( $this, 'include_checkout_script' ) );


            if ( ywf_enable_discount() ) {

                if( version_compare( WC()->version,'2.7.0', '<' ) ) {
                    add_filter( 'woocommerce_get_shop_coupon_data', array( $this, 'get_shop_coupon_data' ), 15, 2 );
                }
                add_filter( 'woocommerce_coupon_message', array( $this, 'get_discount_applied_message' ), 15, 3 );
                add_filter( 'woocommerce_cart_totals_coupon_label', array( $this, 'coupon_label' ) );
                add_filter( 'woocommerce_cart_totals_coupon_html', array( $this, 'coupon_html' ), 10, 2 );
                add_action( 'woocommerce_before_cart', array( $this, 'show_discount_message' ) );
                add_action('woocommerce_before_single_product', array( $this, 'show_discount_message' ) );
                add_action( 'woocommerce_before_checkout_form', array( $this, 'show_recharge_message' ) );
                add_filter( 'woocommerce_apply_individual_use_coupon', array( $this,'apply_individual_use_coupon' ), 10, 3 );
                add_filter( 'woocommerce_apply_with_individual_use_coupon', array( $this,'apply_with_individual_use_coupon', 10, 2 ) );
                add_action( 'ywdpd_before_cart_process_discounts', array( $this, 'remove_discount' ), 10 );
                add_action( 'woocommerce_before_checkout_process', array( $this, 'remove_discount' ), 10 );

            }

            add_action( 'woocommerce_cart_totals_before_order_total', array( $this, 'add_funds_row' ) );
            add_action( 'woocommerce_review_order_after_cart_contents', array( $this, 'add_funds_row' ) );

          //  add_filter( 'woocommerce_calculated_total', array( $this, 'calculate_total' ) );
           // add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'calculate_totals' ) );
            add_action( 'woocommerce_cart_updated', array( $this, 'calculate_totals' ),99 );
            add_action( 'init', array( $this, 'clear_session' ) );
        }


        /**
         * @author YITHEMES
         * @since 1.0.0
         * @return YITH_Funds unique access
         */
        public static function get_instance()
        {

            if ( is_null( self::$_instance ) ) {

                self::$_instance = new self();
            }

            return self::$_instance;
        }

        public function clear_session()
        {
            if ( isset( $_GET[ 'remove_yith_funds' ] ) ) {

              
                WC()->session->ywf_partial_payment = 'no';
                WC()->session->ywf_fund_used = null;
                foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $gateway ) {
                    WC()->session->chosen_payment_method = $gateway->id;
                    break;
                }
                wp_redirect( esc_url_raw( remove_query_arg( 'remove_yith_funds' ) ) );
                exit();
            }
        }

        /**
         * include checkout script
         * @author YITHEMES
         * @since 1.0.0
         */
        public function include_checkout_script()
        {
            $suffix = ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ) ? '' : '.min';

          
            if( ( is_checkout() || is_checkout_pay_page() ) && !is_account_page()) {
                
               
                wp_enqueue_script( 'ywf_funds_script', YITH_FUNDS_ASSETS_URL . '/js/ywf-funds' . $suffix . '.js', YITH_FUNDS_VERSION, true );
                $params = array( 'admin_url' => admin_url( 'admin-ajax.php', is_ssl() ? 'https' : 'http' ), 'plugin' => YITH_FUNDS_SLUG, 'action' => array( 'set_flag_to_session' => 'set_flag_to_session' ) );
                wp_localize_script( 'ywf_funds_script', 'ywf_params', $params );
            }
        }

        /**
         * check if is funds gateway is chosen
         * @author YITHEMES
         * @since 1.0.0
         * @return bool
         */
        public function fund_payment_chosen()
        {
            $all_payments = WC()->payment_gateways()->get_available_payment_gateways();

            return ( ( isset( $all_payments[ 'yith_funds' ] ) && $all_payments[ 'yith_funds' ]->chosen === true ) || ( isset( $_POST[ 'payment_method' ] ) && 'yith_funds' === $_POST[ 'payment_method' ] ) );
        }

        /**
         * generate the coupon data for user that use funds
         * @author YITHEMES
         * @since 1.0.0
         * @param $data
         * @param $coupon_code
         * @return mixed
         */
        public function get_shop_coupon_data( $data, $coupon_code )
        {

            if ( strtolower( $coupon_code ) == strtolower( $this->get_coupon_code() ) ) {
                $data = array( 'discount_type' => ywf_get_discount_type(), 'amount' => floatval( ywf_get_discount_value() ), 'individual_use' => 'yes', 'product_ids' => array(), 'exclude_product_ids' => array(), 'usage_limit' => '', 'usage_limit_per_user' => '', 'limit_usage_to_x_items' => '', 'usage_count' => '', 'expiry_date' => '', 'free_shipping' => 'no', 'product_categories' => array(), 'exclude_product_categories' => array(), 'exclude_sale_items' => 'no', 'minimum_amount' => '', 'maximum_amount' => '', 'customer_email' => array() );
            }
            return $data;

        }


        /**
         * return current coupon is user pay with funds
         * @author YITHEMES
         * @since 1.0.0
         * @return mixed
         */
        public function get_coupon_code()
        {
            return isset( WC()->session->yith_fund_coupon ) ? WC()->session->yith_fund_coupon : false;
        }

        /**
         * @author YITHEMES
         * @since 1.0.0
         */
        public function generate_coupon_code()
        {

            $coupon_code = sprintf( 'yith_funds_coupon_%s', get_current_user_id() );
            WC()->session->yith_fund_coupon = $coupon_code;
            return $coupon_code;
        }


        public function apply_discount_coupon()
        {
            $coupon_code = $this->get_coupon_code();
            if ( is_null( WC()->cart ) || !ywf_enable_discount() || (  !empty( $coupon_code ) && WC()->cart->has_discount( $this->get_coupon_code() ) ) || !$this->fund_payment_chosen() )
                return;

            $cart_total = WC()->cart->total;
            $current_user = get_current_user_id();

            if ( $current_user ) {

                $customer = new YITH_YWF_Customer( $current_user );
                $funds = apply_filters( 'yith_show_available_funds', $customer->get_funds() );

                if ( $funds >= $cart_total ) {

                    global $YITH_FUNDS;

                    $coupon_code =  $this->generate_coupon_code();

                    if( $YITH_FUNDS->is_wc_2_7 ){

                        $coupon = new WC_Coupon( $coupon_code );
                        $coupon_data = $this->get_shop_coupon_data( '', $coupon_code );

                        if( $coupon->is_valid() ){
                            
                            $coupon->set_amount( $coupon_data['amount'] );
                            $coupon->set_discount_type( $coupon_data['discount_type'] );
                        }else{

                            $coupon->read_manual_coupon( $coupon_code, $coupon_data );
                        }


                        $coupon->save();

                    }

                    WC()->cart->add_discount( $coupon_code );
                  

                }
            }

        }


        /**
         * Change the "Coupon applied successfully" message to "Discount Applied Successfully"
         *
         * @since 1.0
         * @param string $message the message text
         * @param string $message_code the message code
         * @param WC_Coupon $coupon
         * @return string the modified messages
         */
        public function get_discount_applied_message( $message, $message_code, $coupon )
        {
            global $YITH_FUNDS;
            
            $coupon_code = $YITH_FUNDS->is_wc_2_7 ? $coupon->get_code() : $coupon->code;
            
            if ( $message_code === WC_Coupon::WC_COUPON_SUCCESS && $coupon_code === $this->get_coupon_code() ) {
                $message = __( 'Discount applied for having used account funds!', 'yith-woocommerce-account-funds' );
            }
            return $message;

        }

        /**
         * Make the label for the coupon look nicer
         * @param  string $label
         * @return string
         */
        public function coupon_label( $label )
        {
           
            if ( strstr( strtoupper( $label ), strtoupper( 'yith_funds_coupon' ) ) ) {
                $label = esc_html( __( 'Discount', 'yith-woocommerce-account-funds' ) );
            }
            return $label;
        }

        /**
         * Make the html for the coupon look nicer
         * @param  string $html
         * @param WC_Coupon $coupon
         * @return string
         */
        public function coupon_html( $html, $coupon )
        {
            global $YITH_FUNDS;

            $coupon_code = $YITH_FUNDS->is_wc_2_7 ? $coupon->get_code() : $coupon->code;
            if ( $coupon_code === $this->get_coupon_code()  ) {
                $html = current( explode( '<a ', $html ) );
            }
            return $html;
        }

        /**
         * Calculated total
         * @param  float $total
         * @return float
         */
        public function calculate_total( $total )
        {
            $customer_id = get_current_user_id();

            if ( $customer_id && ( isset( WC()->session->ywf_partial_payment ) && WC()->session->ywf_partial_payment === 'yes' ) ) {
                $customer_fund = new YITH_YWF_Customer( $customer_id );
                $funds = apply_filters( 'yith_show_available_funds', $customer_fund->get_funds() );
                $funds_used = min( $total, $funds );

               if ( $funds < $total && ywf_partial_payment_enabled() ) {
                    $total = $total - $funds_used;
                }
                WC()->session->ywf_fund_used = $funds_used;

                $message = array();
                $message[] = sprintf('%s', __('Your funds have been used successfully. Pay the rest now!'), 'yith-woocomerce-funds');

                $messages[ 'messages' ] = $message;

                wc_get_template( 'notices/success.php', $messages );
            }

            return $total;
        }

        /**
         * Calculate totals
         */
        public function calculate_totals()
        {
            //WC()->session->ywf_partial_payment = 'no';
            if ( $this->fund_payment_chosen() ) {
                
                $this->apply_discount_coupon();
              
            } else {

                if ( $this->get_coupon_code() && WC()->cart->has_discount( $this->get_coupon_code() ) ) {
                    WC()->cart->remove_coupon( $this->get_coupon_code() );
                }

              /*  if( WC()->session->ywf_partial_payment === 'yes'  ){

                     $this->add_funds_coupon();
                  
                }else{

                    WC()->cart->remove_coupon('yith_funds_used_data');

                }*/
            }
        }

        public function remove_funds_coupon(){

            if( WC()->session->ywf_partial_payment === 'yes' ){
//                if( WC()->cart->has_discount('yith_funds_used_data')){
//                    WC()->cart->remove_coupon( 'yith_funds_used_data' );
//                }


                if( WC()->cart->has_discount('yith_funds_used_data')){

                    $discount_applied = WC()->cart->get_applied_coupons();
                    $applied_coupon = array();

                    foreach( $discount_applied as $key=>$code ){

                        if( $code === 'yith_funds_used_data' ){
                           continue;
                        }

                        $applied_coupon[]=$code;
                    }

                    WC()->cart->applied_coupons = $applied_coupon;
                }
            }
        }

        /**
         * @param WC_Order $order
         */
        public function display_used_funds( $order )
        {
            $funds_used = yit_get_prop( $order, '_order_funds' );
            if ( 'yith_funds' === $order->payment_method || !empty( $funds_used ) ) {
                ?>
                <tr class="ywf-funds-used">
                    <td class="product-name">
                        <?php _e( 'Funds used', 'yith-woocommerce-account-funds' ) . '&nbsp;'; ?>
                    </td>
                    <td class="product-total">
                        <?php echo wc_price( $funds_used ); ?>
                    </td>
                </tr>
                <?php
            }
        }

        public function add_funds_row()
        {

            if ( isset( WC()->session->ywf_partial_payment ) && WC()->session->ywf_partial_payment === 'yes' ) {

                $funds_amount = is_null( WC()->session->ywf_fund_used ) ? 0 : WC()->session->ywf_fund_used;

                if ( $funds_amount > 0 ) {
                    ?>
                    <tr class="order-discount">
                        <th><?php _e( 'User funds', 'yith-woocommerce-account-funds' ); ?></th>
                        <td>-<?php echo wc_price( $funds_amount ); ?> <a
                                href="<?php echo esc_url( add_query_arg( 'remove_yith_funds', true, get_permalink( is_cart() ? wc_get_page_id( 'cart' ) : wc_get_page_id( 'checkout' ) ) ) ); ?>"><?php _e( '[Remove]', 'woocommerce-account-funds' ); ?></a>
                        </td>
                    </tr>
                    <?php
                }
            }
        }

        /**
         * show customer message
         * @author YITHEMES
         * @since 1.0.0
         */
        public function show_discount_message(){

            $type_discount = ywf_get_discount_type();
            $discount = apply_filters( 'yith_discount_value', ywf_get_discount_value(), $type_discount );
            if( 'fixed_cart' === $type_discount ){

                $price_label = sprintf('<strong>%s</strong>', wc_price($discount) );
            }else{

                $price_label = sprintf('<strong>%s</strong>', $discount.'%' );
            }
            $message = sprintf('%s %s %s',__('Pay the order using your account funds and get a','yith-woocommerce-account-funds'), $price_label, __('discount on your cart','yith-woocommerce-account-funds') );


            wc_add_notice( $message, 'success' );

        }

        /**
         * show recharge message
         * @author YITHEMES
         * @since 1.0.0
         */
        public function show_recharge_message(){


            if( is_user_logged_in() ) {
                $customer_id = get_current_user_id();

                $yith_cust = new YITH_YWF_Customer( $customer_id );
                $funds = apply_filters( 'yith_show_available_funds', $yith_cust->get_funds() );
                $cart_total = WC()->cart->total;

                if( $funds >= 0 && $funds< $cart_total ){

                   $amount_rech = $cart_total-$funds;
                   $min = ywf_get_min_fund_rechargeable();
                   $min = ( $min!='' ) ? floatval( wc_format_decimal( $min ) ) : 0;
                   $max = ywf_get_max_fund_rechargeable();

                   if( $max=='' || $amount_rech< $max ) {
                       if ( $amount_rech < $min ) {

                           $amount_rech = $min;
                       }
                       $url = wc_get_page_permalink( 'myaccount' );
                       $make_deposit_endpoint = apply_filters( 'ywf_make_deposit_slug', 'make-a-deposit' );
                       $endpoint_url = esc_url( add_query_arg( array( 'amount' => $amount_rech ), wc_get_endpoint_url( $make_deposit_endpoint, '', $url ) ) );
                       $button = sprintf( '<a href="%s" class="button wc-foward">%s</a>', $endpoint_url, __( 'Deposit', 'yith-woocommerce-account-funds' ) );
                       $message = sprintf( '%s %s %s %s ', $button, __( 'Deposit at least', 'yith-woocommerce-account-funds' ), wc_price( $amount_rech ), __( 'to get the available discount' ) );
                       $messages = array( $message );

                       $messages[ 'messages' ] = $messages;

                       wc_get_template( 'notices/notice.php', $messages );
                   }
                }
            }
        }

        /**
         * support to YITH Dynamic Pricing and Discounts Premium
         * @since 1.0.16
         * @author YITHEMES
         */
        public function remove_discount(){


            if( WC()->cart->has_discount( $this->get_coupon_code() ) ){

                WC()->cart->remove_coupon( $this->get_coupon_code() );

            }
        }

        public function apply_individual_use_coupon( $applied, $coupon_code, $coupons  ) {

            $cart = !empty( WC()->cart ) ? WC()->cart : false;

            if( $coupon_code !==  $this->get_coupon_code() || ( $cart && $cart->has_discount( $coupon_code ) ) ) {
                $applied = $coupons;
            }
            return $applied;
        }

        public function  apply_with_individual_use_coupon( $skip, $coupon_code ){

            global $YITH_FUNDS;
            $coupon_code = $YITH_FUNDS->is_wc_2_7 ? $coupon_code->get_code() : $coupon_code->code;

            if( $coupon_code == $this->get_coupon_code() ) {
                $skip = true;
            }
            return $skip;

        }

    }
}


function YITH_YWF_Cart_Process()
{
    return YITH_YWF_Cart_Process::get_instance();
}