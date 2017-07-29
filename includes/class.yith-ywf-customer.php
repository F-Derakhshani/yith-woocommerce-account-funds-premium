<?php
if ( !defined( 'ABSPATH' ) )
    exit;

if ( !class_exists( 'YITH_YWF_Customer' ) ) {

    class YITH_YWF_Customer
    {


        public function __construct( $customer_id )
        {
            $this->customer_id = $customer_id;
            $this->meta_key = '_customer_fund';
        }


        /**
         * static function for get customer fund
         * @author YITHEMES
         * @since 1.0.0
         * @return float
         */
        public function get_funds()
        {

            $funds = get_user_meta( $this->customer_id, $this->meta_key, true );

            return empty( $funds ) ? 0 : floatval( $funds );
        }

        /**
         * set a new funds for customer
         * @author YITHEMES
         * @since 1.0.0
         * @param $funds
         */
        public function set_funds( $funds )
        {
            update_user_meta( $this->customer_id, $this->meta_key, $funds );

            $user_funds_limiter =  wc_format_decimal( get_option('ywf_email_limit') ) ;
           
            if( $funds<=$user_funds_limiter ){
              
                WC()->mailer();
                do_action( 'ywf_send_user_fund_email_notification', $this->customer_id );
            }else{
                update_user_meta(  $this->customer_id, '_user_mail_send', 'no' );
            }
        }

        /**
         * increase the customer funds
         * @author YITHEMES
         * @since 1.0.0
         * @param $new_funds
         */
        public function add_funds( $new_funds )
        {
            $old_funds = $this->get_funds();
            $old_funds += floatval( $new_funds );
            $this->set_funds( $old_funds );

        }

        /**
         * decrement the customer funds
         * @author YITHEMES
         * @since 1.0.0
         * @param $new_funds
         */
        public function decrement_funds( $new_funds )
        {

            $old_funds = $this->get_funds();
            $old_funds -= floatval( $new_funds );
            $this->set_funds( $old_funds );
        }


    }
}