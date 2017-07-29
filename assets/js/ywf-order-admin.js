/**
 * Created by salvatore on 13/04/16.
 */
jQuery(document).ready(function($){

    woocommerce_admin['ywf_error_message_refund'] =  ywf_params.error_message_refund;

$('.button.refund-items').on('click',function(e){

    if( $('.ywf_available_user_fund').length ){

        var refund_items = $('.wc-order-refund-items'),
            refund_total = $('.ywf_available_user_fund').val(),
            tr_total_av_refund = refund_items.find('tr:nth-child(3)'),
            td_total_av_refund = tr_total_av_refund.find('td.total'),
            td_total_av_refund_label = tr_total_av_refund.find('td.label'),
            label = td_total_av_refund_label.html(),
            old_amount = td_total_av_refund.find('span.amount').text(),
            format_refund = accounting.formatMoney( refund_total, {
               symbol:    woocommerce_admin_meta_boxes.currency_format_symbol,
               decimal:   woocommerce_admin_meta_boxes.currency_format_decimal_sep,
               thousand:  woocommerce_admin_meta_boxes.currency_format_thousand_sep,
               precision: woocommerce_admin_meta_boxes.currency_format_num_decimals,
               format:    woocommerce_admin_meta_boxes.currency_format
           } );

        

        if( !td_total_av_refund.find('span.ywf_ref_amount').length ) {

            var old_amount_value = accounting.unformat( old_amount, woocommerce_admin.mon_decimal_point );
            if( old_amount_value > refund_total ) {
                td_total_av_refund.html('<span class="amount ywf_ref_amount"><del>' + old_amount + '</del> ' + format_refund);
            }
            td_total_av_refund_label.html('<span class="woocommerce-help-tip" data-tip="' + ywf_params.tot_av_refund_tip + '"></span> ' +label);

            // Tooltips
            var tiptip_args = {
                'attribute': 'data-tip',
                'fadeIn': 50,
                'fadeOut': 50,
                'delay': 200
            };
            $('.woocommerce-help-tip').tipTip(tiptip_args);
        }

    }
});

    $('.wc-order-refund-items').on('change keyup','#refund_amount', function (e){

        var max_user_funds_ref = $('.ywf_available_user_fund'),
            refund_amount = $(this).val();
        if( max_user_funds_ref.length ){

            var user_funds = max_user_funds_ref.val(),
                value_refund = accounting.unformat( refund_amount, woocommerce_admin.mon_decimal_point );


            if( value_refund > user_funds ){
               $('.refund-actions button').attr('disabled','disabled');
              
                $( document.body ).triggerHandler( 'wc_add_error_tip', [ $(this),  'ywf_error_message_refund' ] );
            } 
            else{
                $( document.body ).triggerHandler( 'wc_remove_error_tip', [ $(this), 'ywf_error_message_refund'  ] );
                $('.refund-actions button').attr('disabled',false );
               
            }

        }
    }
    );
});