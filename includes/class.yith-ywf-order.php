<?php
if ( !defined( 'ABSPATH' ) )
    exit;

if ( !class_exists( 'YITH_YWF_Order' ) ) {

    class YITH_YWF_Order
    {
        /**
         * YITH_YWF_Order constructor.
         */
        public function __construct()
        {
            add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta' ), 10, 2 );
            

            add_action( 'woocommerce_order_status_changed', array( $this, 'manage_order_funds' ), 10, 3 );

            add_action( 'woocommerce_admin_order_totals_after_tax', array( $this, 'woocommerce_admin_order_totals_show_user_funds' ) );
            add_action( 'woocommerce_admin_order_totals_after_total', array( $this, 'woocommerce_admin_order_totals_user_funds_available' ) );
            add_action( 'woocommerce_payment_complete', array( $this, 'clear_session' ) );
            add_filter( 'woocommerce_get_order_item_totals', array( $this,'get_order_fund_item_total' ), 10, 2 );
            add_action( 'woocommerce_order_refunded', array( $this, 'remove_deposit_order' ),10 ,2 );
            add_action( 'woocommerce_refund_deleted', array( $this, 'refund_deleted_order_funds' ),10,2 );
            add_filter( 'woocommerce_order_get_total',array( $this,'show_order_total_include_funds'),20,2 );

            //update order deposit meta
            add_action( 'woocommerce_checkout_update_order_deposit_meta', array( $this, 'update_order_deposit_meta' ),10,2 );

            //order again
            add_action( 'woocommerce_order_details_after_order_table', array( $this, 'deposit_again' ), 5, 1 );
            
            add_filter( 'woocommerce_ajax_calc_line_taxes', array( $this,  'remove_deposit_from_items' ), 10 , 3 );
            if( is_admin() ) {

                add_filter( 'views_edit-shop_order', array( $this, 'add_order_deposit_view' ) );
                add_action( 'pre_get_posts', array( $this, 'filter_order_deposit_for_view' ) );

            }
        }

        /**
         * clear session if payment is complete
         * @author YITHEMES
         * @since 1.0.0
         * @param $order_id
         */
        public function clear_session( $order_id )
        {

            if ( !is_null( WC()->session ) ) {
                WC()->session->ywf_fund_used = null;
                WC()->session->ywf_partial_payment = 'no';
                WC()->session->order_deposit_awaiting_payment = false;
            }
        }

        
        /**
         * @author YITHEMES
         * @since 1.0.0
         * @param $order_id
         * @param $old_status
         * @param $new_status
         */
        public function manage_order_funds( $order_id, $old_status, $new_status )
        {
            $this->clear_session( $order_id );

            $order = wc_get_order( $order_id );

            if( ywf_order_has_deposit( $order ) ){

                switch( $new_status ){

                    case 'completed':
                        $this->add_deposit_order( $order );
                        break;
                }
            }else {

                $funds_order = yit_get_prop( $order, '_order_funds' );

                $funds_order_remove = yit_get_prop( $order, '_order_fund_removed' );

                if( !empty( $funds_order ) ) {
                    switch ( $new_status ) {

                        case 'completed':
                        case 'processing':
                        case 'pending':
                        case 'on-hold':
                            $this->add_fund_order( $order, $funds_order_remove, $funds_order );
                            break;
                        case 'cancelled':
                            $this->remove_fund_order( $order, $funds_order_remove, $funds_order );
                            break;
                    }
                }
            }
        }


        /**
         * add order fund and decrement user fund
         * @author YITHEMES
         * @since 1.0.0
         * @param WC_Order $order
         * @param $has_removed
         * @param $funds
         */
        public function add_fund_order( $order, $has_removed, $funds )
        {

            $order_id = yit_get_prop( $order, 'id', true, 'edit' );
            $customer_id = $order->get_user_id();
            $customer_fund = new YITH_YWF_Customer( $customer_id );
            $total_fund_refunded =   yit_get_prop( $order, '_order_funds_refunded' );
            
            if ( ( empty( $has_removed ) || 'no' === $has_removed ) && !empty( $funds ) ) {
                $customer_fund->decrement_funds( $funds );
                $funds_show_to_order_currency = apply_filters( 'yith_show_funds_used_into_order_currency', $funds, $order_id );
                yit_save_prop( $order, '_order_fund_removed', 'yes' );
                $order_note = sprintf( __( 'Removed %s funds from customer #%s account', 'yith-woocommorce-funds' ), wc_price( $funds_show_to_order_currency ), $customer_id );
                $order->add_order_note( $order_note );

                $default = array(
                    'user_id' => $customer_id,
                    'order_id' => $order_id,
                    'fund_user'	=> $funds-$total_fund_refunded,
                    'type_operation' => 'pay'
                );
                do_action('ywf_add_user_log', $default );
            }
           

        }

        /**
         * remove order fund and increment user fund
         * @author YITHEMES
         * @param WC_Order $order
         * @param $has_removed
         * @param $funds
         */
        public function remove_fund_order( $order, $has_removed, $funds )
        {

            $order_id = yit_get_prop( $order, 'id', true, 'edit' );
            $customer_id = $order->get_user_id();
            $customer_fund = new YITH_YWF_Customer( $customer_id );
            $total_fund_refunded =   yit_get_prop( $order, '_order_funds_refunded' );
            
            if ( 'yes' === $has_removed && $funds ) {

                $customer_fund->add_funds( $funds );
                yit_save_prop( $order, '_order_fund_removed', 'no' );
                $funds_show_to_order_currency = apply_filters( 'yith_show_funds_used_into_order_currency', $funds, $order_id );
                $order_note = sprintf( __( 'Added %s funds to customer #%s account', 'yith-woocommorce-funds' ), wc_price( $funds_show_to_order_currency ), $customer_id );
                $order->add_order_note( $order_note );

                $default = array(
                    'user_id' => $customer_id,
                    'order_id' => $order_id,
                    'fund_user'	=> $funds-$total_fund_refunded,
                    'type_operation' => 'restore'
                );
                do_action('ywf_add_user_log', $default );
            }

        }

        /**
         * add funds to customer
         * @author YITHEMES
         * @since 1.0.0
         * @param WC_Order $order
         */
        public function add_deposit_order( $order ){

            $total =  $this->get_order_deposit_total( $order );
            $order_id = yit_get_prop ( $order, 'id', true , 'edit' );

            $user_id = $order->get_user_id();
            $fund_deposited = yit_get_prop( $order, '_fund_deposited' );

         
            if( empty( $fund_deposited ) || $fund_deposited == 'no' ){

                $customer_fund = new YITH_YWF_Customer( $user_id );
                yit_save_prop( $order, '_fund_deposited', 'yes' );
                $order_note = sprintf( __( 'Added %s funds to customer #%s account', 'yith-woocommorce-funds' ), wc_price( $total ), $user_id );
                $order->add_order_note( $order_note );

                $total = apply_filters( 'yith_admin_deposit_funds', $total, $order_id );
                $customer_fund->add_funds( $total );
                $default = array(
                    'user_id' => $user_id,
                    'order_id' => $order_id,
                    'fund_user'	=> $total,
                    'type_operation' => 'deposit'
                );
                do_action('ywf_add_user_log', $default );
            }
        }

        /**
         * remove fund to customer if order is cancelled or refunded
         * @author YITHEMES
         * @since 1.0.0
         * @param $order_id
         */
        public function remove_deposit_order( $order_id , $refund_id ){

            $order = wc_get_order( $order_id );
            $user_id = $order->get_user_id();
            $fund_deposited = yit_get_prop( $order, '_fund_deposited' );

            if( !empty( $fund_deposited ) || $fund_deposited == 'yes' ){

                $refund = wc_get_order( $refund_id );
                $total = abs( $refund->get_total() );
                $customer_fund = new YITH_YWF_Customer( $user_id );

                $order_note = sprintf( __( 'Removed %s funds from customer #%s account', 'yith-woocommorce-funds' ), wc_price( $total ), $user_id );
                $order->add_order_note( $order_note );

                $total = apply_filters( 'yith_admin_deposit_funds', $total, $order_id );
                $customer_fund->decrement_funds( $total );
                $default = array(
                    'user_id' => $user_id,
                    'order_id' => $order_id,
                    'fund_user'	=> $total,
                    'type_operation' => 'remove'
                );
                do_action('ywf_add_user_log', $default );
            }
        }

        /**
         * save fund used in order meta
         * @author YITHEMES
         * @since 1.0.0
         * @param $order_id
         * @param $posted
         */
        public function update_order_meta( $order_id, $posted )
        {
            if ( $posted[ 'payment_method' ] !== 'yith_funds' && isset( WC()->session->ywf_partial_payment ) && WC()->session->ywf_partial_payment == 'yes' && isset( WC()->session->ywf_fund_used ) ) {

                $funds_used = WC()->session->ywf_fund_used;
                $order = wc_get_order( $order_id );

                if ( !is_null( $funds_used ) ) {
                    yit_save_prop( $order, array( 
                                    '_order_funds' => $funds_used,
                                    '_order_fund_removed' => 'no'
                        )
                    );
                   
                    
                }
            }
        }

        /**
         * save order deposit meta
         * @author YITHEMES
         * @since 1.0.0
         * @param $order_id
         * @param $posted
         */
        public function update_order_deposit_meta( $order_id, $posted ){

            $order = wc_get_order( $order_id );

            yit_save_prop( $order, array(
                '_order_has_deposit' => 'yes',
                '_order_deposit_amount' => $posted['deposit_amount']
                )
            );
           
        }

        /**
         * print custom order details in admin
         * @author YITHEMES
         * @since 1.0.0
         * @param $order_id
         */
        public function woocommerce_admin_order_totals_show_user_funds( $order_id )
        {
            $order = wc_get_order( $order_id );
            $order_funds = yit_get_prop( $order, '_order_funds' );
            
            //remove_filter( 'woocommerce_order_amount_total',array( $this,'show_order_total_include_funds'),20  );
            if ( $order_funds ) {

                ?>
                <tr>
                    <td class="label"><?php echo wc_help_tip( __( 'Funds used by the customer to pay this order.', 'yith-woocommerce-account-funds' ) ); ?> <?php _e( 'Funds used', 'yith-woocommerce-account-funds' ); ?>
                    </td>
                    <?php if( version_compare( WC()->version, '2.6.0','<') ):?>
                    <td class="total">
                        <?php echo $this->get_formatted_order_total( $order ) ; ?>
                    </td>
                    <td width="1%"></td>
                    <?php else:?>
                        <td width="1%"></td>
                        <td class="total">
                            <?php echo $this->get_formatted_order_total( $order ) ; ?>
                        </td>

                    <?php endif;?>
                </tr>
                <?php

            }
        }

        public function woocommerce_admin_order_totals_user_funds_available( $order_id ){

            $order = wc_get_order( $order_id  );
            if( ywf_order_has_deposit( $order) ){

               
                $user_funds = new YITH_YWF_Customer( $order->get_user_id() );
                $tot_funds_av = apply_filters( 'yith_admin_order_totals_user_available' , $user_funds->get_funds(), $order_id );
                ?>
                <input type="hidden" class="ywf_available_user_fund" value="<?php echo $tot_funds_av;?>">
<?php
            }
        }

        /**
         * @param WC_Order $order
         * @param string $tax_display
         * @param bool $display_refunded
         * @return mixed|void
         */
        public function get_formatted_order_total( $order, $tax_display = '', $display_refunded = true ) {

            global  $YITH_FUNDS;
            
            $order_id = yit_get_prop( $order, 'id', true, 'edit' );
            $total = apply_filters( 'yith_show_funds_used_into_order_currency', yit_get_prop( $order, '_order_funds' ), $order_id );

            $currency = $YITH_FUNDS->is_wc_2_7 ? $order->get_currency() : $order->get_order_currency();
            $formatted_total = wc_price( -$total, array( 'currency' => $currency ) );
            $order_total    = $total;
            $total_refunded = apply_filters( 'yith_show_funds_used_into_order_currency', yit_get_prop( $order, '_order_funds_refunded' ), $order_id );
            $tax_string     = '';

            // Tax for inclusive prices
            if ( wc_tax_enabled() && 'incl' == $tax_display ) {
                $tax_string_array = array();

                if ( 'itemized' == get_option( 'woocommerce_tax_total_display' ) ) {
                    foreach ( $order->get_tax_totals() as $code => $tax ) {
                        $tax_amount         = ( $total_refunded && $display_refunded ) ? wc_price( WC_Tax::round( $tax->amount - $order->get_total_tax_refunded_by_rate_id( $tax->rate_id ) ), array( 'currency' => $order->get_order_currency() ) ) : $tax->formatted_amount;
                        $tax_string_array[] = sprintf( '%s %s', $tax_amount, $tax->label );
                    }
                } else {
                    $tax_amount         = ( $total_refunded && $display_refunded ) ? $order->get_total_tax() - $order->get_total_tax_refunded() : $order->get_total_tax();
                    $tax_string_array[] = sprintf( '%s %s', wc_price( $tax_amount, array( 'currency' => $currency ) ), WC()->countries->tax_or_vat() );
                }
                if ( ! empty( $tax_string_array ) ) {
                    $tax_string = ' ' . sprintf( __( '(Includes %s)', 'woocommerce' ), implode( ', ', $tax_string_array ) );
                }
            }

            if ( $total_refunded && $display_refunded ) {
                $formatted_total = '<del>' . strip_tags( $formatted_total ) . '</del> <ins>' . wc_price( ($order_total - $total_refunded), array( 'currency' => $currency ) ) . $tax_string . '</ins>';
            } else {
                $formatted_total .= $tax_string;
            }

            return apply_filters( 'woocommerce_get_formatted_order_funds_total', $formatted_total, $order );
        }

        /**
         * return order total with funds
         * @author YITHEMES
         * @since 1.0.0
         * @param $total
         * @param WC_Order $order
         * @return mixed
         */
        public function show_order_total_include_funds( $total, $order ){

            $order_id = yit_get_prop( $order, 'id', true,'edit' );
            $funds = apply_filters( 'yith_show_funds_used_into_order_currency', yit_get_prop( $order, '_order_funds' ), $order_id );

            if( !empty( $funds ) && !ywf_order_has_deposit( $order ) && $total == 0 ){

                return $total+floatval( $funds );
            }

           /* if( did_action('wp_ajax_woocommerce_refund_line_items') ){
                remove_filter( 'woocommerce_order_amount_total',array( $this,'show_order_total_include_funds'),20  );
            }*/
            return $total;
        }

        /**
         * add order amount total filter
         * @author YITHEMES
         * @since 1.0.0
         * @param $order_id
         */
        public function add_include_order_total_with_fund_filter( $order_id ){

            add_filter( 'woocommerce_order_amount_total',array( $this,'show_order_total_include_funds'),20,2 );
        }


        public function remove_order_total_with_fund_filter(){

            remove_filter( 'woocommerce_order_amount_total',array( $this,'show_order_total_include_funds'),20  );
        }


        /**
         * add order item line into email
         * @author YITHEMES
         * @since 1.0.0
         * @param array $total_rows
         * @param WC_Order $order
         * @return array
         */
        public function get_order_fund_item_total( $total_rows, $order ){

            $order_id = yit_get_prop( $order, 'id', true,'edit' );
            $fund = apply_filters( 'yith_show_funds_used_into_order_currency',yit_get_prop( $order, '_order_funds' ), $order_id );

            if( !empty( $fund ) ){
                
                global $YITH_FUNDS;
                $index = array_search('order_total', array_keys( $total_rows ) );
                $currency = $YITH_FUNDS->is_wc_2_7 ? $order->get_currency() : $order->get_order_currency();
                $total_rows = array_slice($total_rows, 0,$index, true) +
                    array("ywf_funds_used" => array(
                        'label' => __('Funds used', 'yith-woocommerce-account-funds'),
                        'value' => wc_price( -$fund , $currency )
                    ) ) +
                    array_slice($total_rows, $index, count($total_rows) - 1, true) ;

                /*$total_rows['ywf_funds_used'] = array(
                  'label' => __('Funds used', 'yith-woocommerce-account-funds'),
                    'value' => wc_price( $fund , $order->get_order_currency() )
                );*/


            }
            return $total_rows;
        }
        
        public function refund_deleted_order_funds( $refund_id, $order_id ){
            
            $order = wc_get_order( $order_id );
            $payment_method = $order->payment_method;
            $customer_id = $order->get_user_id();


            if( 'yith_funds' === $payment_method ){

                $funds_refunded = apply_filters( 'yith_show_funds_used_into_order_currency',yit_get_prop( $order,'_order_funds_refunded' ), $order_id );

                $total_refund =  $order->get_total_refunded() ;
                $how_refund = wc_format_decimal( $funds_refunded-$total_refund, wc_get_price_decimals() );

                $customer = new YITH_YWF_Customer( $customer_id );

                $how_refund_base_currency = apply_filters( 'yith_how_refund_base_currency', $how_refund, $order_id );
                $total_refund_base_currency = apply_filters( 'yith_how_refund_base_currency', $total_refund, $order_id );
                $customer->decrement_funds( $how_refund_base_currency );

                yit_save_prop( $order, '_order_funds_refunded', $total_refund_base_currency );
                $order_note = sprintf( __( 'Removed %s funds from customer #%s account', 'yith-woocommorce-funds' ), wc_price( $how_refund ), $order->get_user_id() );
                $order->add_order_note( $order_note );

                $default = array(
                    'user_id' => $customer_id,
                    'order_id' => $order_id,
                    'fund_user'	=> $how_refund_base_currency,
                    'type_operation' => 'pay'
                );
                

                do_action('ywf_add_user_log', $default );
                
            }
        }

        /**
         * add custom view in order table
         * @author YITHEMES
         * @since 1.0.0
         * @param $views
         * @return mixed
         */
        public function add_order_deposit_view( $views ){

            $tot_order = $this->count_order_deposit();

            if( $tot_order > 0 ){
                $filter_url = esc_url( add_query_arg( array( 'post_type' => 'shop_order', 'ywf_order_deposit' => true ), admin_url( 'edit.php' ) ) );
                $filter_class = isset( $_GET['ywf_order_deposit'] ) ? 'current' : '';

                $views[ 'ywf_order_deposit' ] = sprintf( '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>', $filter_url, $filter_class, __( 'Deposit', 'yith-woocommerce-account-funds' ), $tot_order );
            }

            return $views;
        }

        /**
         * customize query
         * @author YITHEMES
         * @since 1.0.0
         */
        public function filter_order_deposit_for_view(){

            if( isset( $_GET['ywf_order_deposit'] ) && $_GET['ywf_order_deposit'] ){
                add_filter( 'posts_join', array( $this, 'filter_order_join_for_view' ) );
                add_filter( 'posts_where', array( $this, 'filter_order_where_for_view' ) );
            }
        }

        /**
         * add joins to order view query
         * @author YITHEMES
         * @since 1.0.0
         * @param $join
         * @return string
         */
        public function filter_order_join_for_view( $join ){

            global $wpdb;

            $join .= " LEFT JOIN {$wpdb->prefix}postmeta as pm ON {$wpdb->posts}.ID = pm.post_id";

            return $join;
        }

        /**
         * Add conditions to order view query
         * @author YITHEMES
         * @since 1.0.0
         * @param $where string Original where query section
         * @return string filtered where query section
         * @since 1.0.0
         */
        public function filter_order_where_for_view( $where ) {
            global $wpdb;

            $where .= $wpdb->prepare( " AND pm.meta_key = %s AND pm.meta_value = %s", array( '_order_has_deposit', 'yes' ) );

            return $where;
        }


        /**
         * count order with deposit
         * @author YITHEMES
         * @since 1.0.0
         * @return int
         */
        public function count_order_deposit(){
            global $wpdb;
            $query = $wpdb->prepare("SELECT DISTINCT COUNT(*) FROM {$wpdb->posts} INNER JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id
                                     WHERE {$wpdb->posts}.post_type = %s AND ( {$wpdb->postmeta}.meta_key=%s AND {$wpdb->postmeta}.meta_value = %s )",
                                    'shop_order' , '_order_has_deposit', 'yes' );
            $result = $wpdb->get_var( $query );
            return $result ;
        }

        /**
         * @param WC_Order $order
         */
        public function deposit_again( $order ){

            if( ywf_order_has_deposit( $order ) ) {

                remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button', 10 );

                $total = $this->get_order_deposit_total( $order );

                $args = array( 'text' => __( 'Deposit again', 'yith-woocommerce-account-funds' ),
                    'type' => 'button',
                    'amount' => $total );

                echo YITH_YWF_Shortcodes::make_a_deposit_small( $args );

            }else {
                add_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button', 10, 1 );
            }
        }

        /**
         * @author YITHEMES
         * @since 1.0.11
         * Return the order total excluding fees
         * @param WC_Order $order
         * @return float
         */ 
        public function get_order_deposit_total( $order ){

            $total = 0;

            $has_deposit = yit_get_prop( $order, '_order_has_deposit' );

            if( 'yes' == $has_deposit ){

                $total = yit_get_prop( $order, '_order_deposit_amount' );
            }
            return $total;
        }

        /**
         * remove deposit form calculate tax procedure
         * @author YITHEMES
         * @since 1.0.0
         * @param array $items
         * @param int $order_id
         * @param string $country
         * @return array 
         */
        public function remove_deposit_from_items( $items, $order_id, $country ){
            
            $order = wc_get_order( $order_id );
            if( ywf_order_has_deposit( $order ) ){

               
                global $YITH_FUNDS;
                $order_item_id = $items['order_item_id'];
               foreach( $order_item_id as $key=>$item_id ){
                   
                 $product_id =  $YITH_FUNDS->is_wc_2_7 ? wc_get_order_item_meta( $item_id ,'_product_id', true ): $order->get_item_meta( $item_id, '_product_id', true );
                 $product = wc_get_product( $product_id );

                   if( $product->is_type( 'ywf_deposit' ) ){
                       unset( $items['order_item_id'][$key] );
                       break;
                   }

               }
            }
            
            return $items;
        }
    }
}
function YITH_YWF_Order()
{
    new YITH_YWF_Order();
}