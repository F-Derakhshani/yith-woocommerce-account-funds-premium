<?php
if( !defined('ABSPATH' ) )
    exit;

$endpoints = array(

    'endpoints-settings' => array(

        'endpoints-section-start' => array(
            'type' => 'title',
            'name' => __('Funds endpoints','yith-woocommerce-account-funds'),
           
        ),
        'fund-make-a-deposit-endpoint' => array(
            'name' => __('"Make a deposit" endpoint','yith-woocommerce-account-funds'),
            'type' => 'text',
            'default' => __('Make a deposit', 'yith-woocommerce-account-funds'),
            'id' => 'ywf_make_a_deposit'
        ),

        'fund-view-income-expenditure-history-endpoint' => array(
            'name' => __('Income/Expenditure History endpoint', 'yith-woocommerce-account-funds'),
            'type' => 'text',
            'default' =>__('Income/Expenditure History', 'yith-woocommerce-account-funds'),
            'id' => 'ywf_view_income_expenditure_history'
        ),
        'endpoint-section-end' => array(
            'type'  => 'sectionend',
           
        )
    )
);

return apply_filters( 'ywf_endpoints_settings', $endpoints );