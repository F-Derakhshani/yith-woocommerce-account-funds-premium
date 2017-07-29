<?php
if( !defined('ABSPATH')) {
    exit;
}

if( !class_exists( 'WC_Product_YWF_Deposit' ) ){

    class WC_Product_YWF_Deposit extends WC_Product{

        public function __construct( $product )
        {
            parent::__construct( $product );
            $this->product_type = 'ywf_deposit';
            
        }

        
        public function get_type(){
            return 'ywf_deposit';
        }
        /**
         * product isn't visible
         * @author YITHEMES
         * @since 1.0.0
         * @return bool
         */
        public function is_visible()
        {
            return false;
        }

        /**
         * @author YITHEMES
         * @since 1.0.1
         * @return bool
         */
        public function is_downloadable()
        {
            return true;
        }

        /**
         * deposit is virtual
         * @author YITHEMES
         * @since 1.0.1
         * @return bool
         */
        public function is_virtual()
        {
            return true;

        }

        public function get_tax_class( $context = 'view' )
        {
            return '';
        }

        /**
         * product is always purchasable
         * @author YITHEMES
         * @since 1.0.0
         * @return bool
         */
        public function is_purchasable()
        {
           return true;
        }

        /**
         * product exists
         * @author YITHEMES
         * @since 1.0.0
         * @return bool
         */
        public function exists()
        {
            return true;
        }

       

        /**
         * Returns the main product image.
         * @author YITHEMES
         * @since 1.0.0
         * @param string $size
         * @param array $attr
         * @return string
         */
        public function get_image( $size = 'shop_thumbnail', $attr = array(), $placeholder=true )
        {
            $image_id = get_option( 'yith_funds_product_image', 0 );
            
            if( 0 == $image_id ){

                $image = wc_placeholder_img();
            }else{

                $image = wp_get_attachment_image( $image_id, $size, $attr );
            }


            return $image;
        }

        /**
         * @author YITHEMES
         * @since 1.0.0
         * @return string
         */
        public function get_title()
        {
            return __('User deposit', 'yith-woocommerce-account-funds');
        }
    }
}