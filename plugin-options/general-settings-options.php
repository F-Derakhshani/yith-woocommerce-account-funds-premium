<?php
if( !defined('ABSPATH'))
    exit;

$discount_symbol = get_option( 'yith_discount_type_discount' ) == 'fixed_cart' ? get_woocommerce_currency_symbol(): '%';
$settings   =   array(
    'general-settings' => array(

        'funds_settings_section_start' => array(
            'name' => __('General settings', 'yith-woocommerce-account-funds' ),
            'type'   => 'title',
        ),

        'funds_min_value' => array(
            'name' => sprintf('%s (%s)',__('Minimum deposit amount', 'yith-woocommerce-account-funds' ),$discount_symbol ),
            'type' => 'number',
            'custom_attributes' => array(
                'min' => 0,
                'step' => 0.1
            ),
            'default' => 0,
            'id' => 'yith_funds_min_value',
            'desc' => __('Set a minimum required amount for deposits', 'yith-woocommerce-account-funds'),
            'css' => 'width:80px;'
        ),

        'funds_max_value' => array(
            'name' => sprintf('%s (%s)',__('Maximum deposit amount','yith-woocommerce-account-funds' ),$discount_symbol ),
            'type'   => 'number',
            'custom_attributes' => array(
                'min' => 0,
                'step' => 0.1
            ),
            'default' => '',
            'id' => 'yith_funds_max_value',
            'desc' => __('Set the maximum amount for each individual deposit. Leave it blank to make it unlimited.', 'yith-woocommerce-account-funds'),
            'css' => 'width:80px;'

        ),
        'funds_product_image' => array(
            'name' => __('Funds product image','yith-woocommerce-account-funds'),
            'type' => 'image-select',
            'id' => 'yith_funds_product_image',
            'default' => '',
            'desc' => ''
        ),

      /*  'funds_settings_section_end' => array(
            'type'   => 'sectionend'
        ),

        'discount_settings_section_start' => array(
            'name' => __('Funds discount settings', 'yith-woocommerce-account-funds'),
            'type'   => 'title',

        ),*/

        'discount_enable_discount' => array(
            'name' => __('Enable discount', 'yith-woocoomerce-funds'),
            'type' => 'checkbox',
            'default' => 'no',
            'desc' => __('Apply a discount if customers use their funds to pay', 'yith-woocommerce-account-funds'),
            'id' => 'yith_discount_enable_discount'

        ),
        'discount_type_discount' => array(
            'name' => __('Discount type', 'yith-woocoomerce-funds'),
            'type' => 'select',
            'options' => array( 'fixed_cart' => __('Fixed price', 'yith-woocommerce-account-funds' ), 'percent' => __('Percentage','yith-woocommerce-account-funds')),
            'default' => 'fixed_cart',
            'id' => 'yith_discount_type_discount'
        ),
        'discount_value' => array(
            'name' =>__('Discount amount','yith-woocommerce-account-funds' ) ,
            'type'   => 'number',
            'custom_attributes' => array(
                'min' => 0,
                'step' => 0.5
            ),
            'default' => 0,
            'id' => 'yith_discount_value',
            'desc' => __('Enter a value. Based on to the above selection, it will be calculated either as fixed amount or as percentage.', 'yith-woocommerce-account-funds'),
            'css' => 'width:80px;'

        ),
        'select_gateway'    =>  array(
            'name'  =>  __( 'Payment method', 'yith-woocommerce-account-funds' ),
            'desc'  =>  __( 'Select payment method for deposits. Leave this field empty if you want to allow all gateways enabled in WooCommerce', 'yith-woocommerce-account-funds'),
            'type'  =>  'multiselect',
            'class' =>  'chosen_select',
            'id'    =>  'ywf_select_gateway',
            'options'   => ywf_get_gateway(),
            'std'   =>  '',
            'default'   =>  '',
            'css'       =>  'width:50%;'

        ),
        'discount_settings_section_end' => array(
            'type'   => 'sectionend',
        ),

        /*
    'payment_settings_section_start' => array(
        'name' => __('Payment settings', 'yith-woocommerce-account-funds'),
        'type'   => 'title',
        'id' => 'yith_payment_settings_section_start'
    ),

    'payment_enable_partial'=> array(
      'name' => __('Enable split payment', 'yith-woocommerce-account-funds'),
        'desc' => __('Allow customers to pay the order using their available funds and pay the rest using a different payment method', 'yith-woocommerce-account-funds'),
       'type' => 'checkbox',
        'default' => 'no',
        'id' => 'yith_enable_partial_payment'
    ),
    'payment_settings_section_end' => array(

        'type'   => 'sectionend',
        'id' => 'yith_payment_settings_section_end'
    ),
*/
    )

);

return $settings;