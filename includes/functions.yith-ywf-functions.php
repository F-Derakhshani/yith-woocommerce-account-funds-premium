<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

if ( !function_exists( 'ywf_get_min_fund_rechargeable' ) ) {

    /**
     * returns the minimum rechargeable funds
     * @author YITHEMES
     * @since 1.0.0
     * @return float
     */
    function ywf_get_min_fund_rechargeable()
    {

        $min_rech = get_option( 'yith_funds_min_value' );
        $min_rech =  empty( $min_rech ) ? 0 : $min_rech;

        return apply_filters( 'yith_min_deposit', $min_rech );
    }
}


if ( !function_exists( 'ywf_get_max_fund_rechargeable' ) ) {
    /**
     * returns the maximum  rechargeable funds
     * @author YITHEMES
     * @since 1.0.0
     * @return bool|float
     */
    function ywf_get_max_fund_rechargeable()
    {

        $max_rech = get_option( 'yith_funds_max_value' );
        $max_rech = empty( $max_rech ) ? '' : $max_rech;

        return apply_filters( 'yith_max_deposit', $max_rech );
    }
}

if( !function_exists('ywf_enable_discount')){
    /**
     * check if is enable discount
     * @author YITHEMES
     * @since 1.0.0
     * @return bool
     */
    function ywf_enable_discount(){

        $enable_discount = get_option('yith_discount_enable_discount');

        return $enable_discount=='yes';

    }
}

if( !function_exists( 'ywf_get_discount_type' ) ){
    /**
     * @author YITHEMES
     * @since 1.0.0
     * @return string
     */
    function ywf_get_discount_type(){

        $discount_type =  get_option( 'yith_discount_type_discount' );

        return $discount_type;
    }
}


if( !function_exists( 'ywf_get_discount_value' ) ){

    /**
     * @author YITHEMES
     * @since 1.0.0
     * @return string
     */
    function ywf_get_discount_value(){

        $discount_value = get_option('yith_discount_value');

        return wc_format_decimal( $discount_value );
    }
}

if( !function_exists('ywf_partial_payment_enabled')){
    /**
     * check if partial payment is enabled
     * @author YITHEMES
     * @since 1.0.0
     * @return bool
     */
    function ywf_partial_payment_enabled(){

        $partial_payment = get_option('yith_enable_partial_payment','no');

        return $partial_payment =='yes';
    }
}

if( !function_exists('ywf_get_fund_endpoint_name' ) ){
    /**
     * @author YITHEMES
     * @since 1.0.0
     * @param string $endpoint_id
     * @return string
     */
    function ywf_get_fund_endpoint_name( $endpoint_id ){

        return get_option( $endpoint_id ,'' );
    }
}

if( !function_exists('ywf_get_fund_endpoint_slug')){
    /**
     * get endpoint slug
     * @author YITHEMES
     * @since 1.0.0
     * @param string $endpoint_id
     * @return string
     */
    function ywf_get_fund_endpoint_slug( $endpoint_id ){

        $endpoint_name = ywf_get_fund_endpoint_name( $endpoint_id );

        $endpoint_slug = strtolower( $endpoint_name );
        $endpoint_slug = trim( $endpoint_slug );
        $endpoint_slug = preg_replace( '/[^a-z]/', '-', $endpoint_slug );

        return $endpoint_slug;
    }
}

if( !function_exists('ywf_order_has_deposit') ) {
    /**
     * check if order is a deposit
     * @author YITHEMES
     * @since 1.0.0
     * @param WC_Order $order
     * @return bool
     */
    function ywf_order_has_deposit( $order )
    {

        $has_deposit = yit_get_prop( $order, '_order_has_deposit' );
        return $has_deposit == 'yes';


    }
}

if( !function_exists(  'ywf_get_endpoint_url' ) ){

    function ywf_get_endpoint_url( $type, $args = array() ){

        $option_name = '';
        if( 'make_a_deposit' == $type ){
            $option_name = 'ywf_make_a_deposit';
            
        }elseif('view_history'){
            $option_name = 'ywf_view_income_expenditure_history';
        }
        $endpoint = ywf_get_fund_endpoint_slug( $option_name );

      
        if( count( $args ) > 0 ) {
            $url = esc_url( add_query_arg( $args, wc_get_page_permalink( 'myaccount' ) . $endpoint ) );
        }
        else{
            $url = esc_url( wc_get_page_permalink( 'myaccount' ) . $endpoint );
        }

        return apply_filters( 'ywf_get_endpoint_url',$url, $type, $args );
    }
}

if( !function_exists('ywf_get_customize_my_account_menu') ){
    
    function ywf_get_customize_my_account_menu(){

        $position = get_option( 'yith-wcmap-menu-position', 'left' );
        $tab = get_option( 'yith-wcmap-menu-style', 'sidebar' ) == 'tab' ? '-tab' : '';
        $menu = '<div id="my-account-menu' . $tab . '" class="yith-wcmap position-' . $position .'">' . YITH_WCMAP_Frontend()->my_account_menu() . '</div>';

        return $menu;
    }
}

if( !function_exists('ywf_is_make_deposit' ) ){
    function ywf_is_make_deposit(){

        global $is_make_a_deposit_form, $post ;

        $current_endpoint = WC()->query->get_current_endpoint();
        $make_deposit_endpoint = apply_filters( 'ywf_make_deposit_slug', 'make-a-deposit' );
        $shortcode = '[yith_ywf_make_a_deposit_endpoint]';

        if( isset( $post) ) {
            $post_content = $post->post_content;
            preg_match( '/\[yith_ywf_make_a_deposit_endpoint[^\]]*\]/', $post_content, $shortcode );
        }
            return  ( $current_endpoint == $make_deposit_endpoint ) || ( isset( $is_make_a_deposit_form ) && $is_make_a_deposit_form ) || ( isset( $shortcode[0] ) );
    }
}

if( !function_exists(  'ywf_get_gateway' ) ){

    function ywf_get_gateway(){
        $payment = WC()->payment_gateways->payment_gateways();
        $gateways = array();
        foreach($payment as $gateway){
            if ( $gateway->enabled == 'yes' && $gateway->id != 'yith_funds' ){
                $gateways[$gateway->id] = $gateway->title;
            }
        }
        return $gateways;
    }
}

if( !function_exists('ywf_get_user_currency') ) {
    function ywf_get_user_currency( $user_id )
    {

        $args = array(
            'numberposts' => 1,
            'meta_query' => array(
                array(
                    'key' => '_customer_user',
                    'value' => $user_id,
                    'compare' => '=',
                    'type' => 'numeric'
                , ),
                array(
                    'key' => '_order_has_deposit',
                    'value' => 'yes',
                    'compare' => 'LIKE'
                ),

            ),
            'post_type' => 'shop_order',
            'post_status' => 'wc-completed',
            'fields' => 'ids'
        );

        $order_id = get_posts( $args );

        return isset( $order_id[0] ) ? $order_id[0] : -1;
    }
}

if( !function_exists( 'ywf_get_date_created_order' ) ){
    /**
     * @param WC_Order $order
     * @param string $context
     */
    function ywf_get_date_created_order( $order, $context = 'view' ){
        
        global $YITH_FUNDS;
        
        $order_date = '';
        if( $YITH_FUNDS->is_wc_2_7 ){
            $order_date = $order->get_date_created( $context );
            
        }else{
            
            $order_date = $order->post->post_date;
        }
        
        return $order_date;
    }
}