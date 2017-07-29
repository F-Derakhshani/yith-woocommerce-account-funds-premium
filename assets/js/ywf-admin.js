/**
 * Created by Your Inspiration on 05/04/2016.
 */
jQuery(document).ready(function ($) {

    var image_frame = null;

    $('.ywf_fund_image_select').on('click', '.upload_button', function (e) {

        var t = $(this),
            field_id = t.parent().find('.ywf_att_id'),
            image_url = t.parent().find('.upload_img_url'),
            image_prev = t.parent().parent().find('.upload_img_preview img'),
            downloadable_file_states = [
                // Main states.
                new wp.media.controller.Library({
                    library: wp.media.query({type: 'image'}),
                    multiple: false,
                    title: t.data('choose'),
                    priority: 20,
                    filterable: 'all'
                })
            ];

        // Create the media frame.
        image_frame = wp.media.frames.downloadable_file = wp.media({
            // Set the title of the modal.
            title: t.data('choose'),
            library: {
                type: 'image'
            },
            button: {
                text: t.data('choose')
            },
            multiple: false,
            states: downloadable_file_states
        });


        image_frame.on('select', function () {

            var file_path = '', file_id = '',
                selection = image_frame.state().get('selection');

            selection.map(function (attachment) {

                attachment = attachment.toJSON();

                if (attachment.url) {
                    file_path = attachment.url;
                    file_id = attachment.id;

                    field_id.val( file_id );
                    image_url.val( file_path );
                    image_prev.attr('src',file_path).parent().show();

                }
            });
        });

        image_frame.open();
    });

    if( ywf_admin.is_customize_active ){

        var table = $('#yith_funds_panel_endpoints-settings .yit-admin-panel-content-wrap');

        table.css({
            'pointer-events': 'none',
            'opacity': '0.3'
        });
    }

    var discount_type = $('#yith_discount_type_discount'),
        discount_value = $('#yith_discount_value'),
        discount_type_cnt = discount_type.parents('tr'),
        discount_value_cnt = discount_value.parents('tr');

    $('#yith_discount_enable_discount').on('change', function(e){


                if( $(this).is(':checked') ) {
                    discount_type_cnt.show();
                    discount_value_cnt.show();
                }else{
                    discount_type_cnt.hide();
                    discount_value_cnt.hide();
                }
        }
    ).trigger('change');

    discount_type.on('change',function(e){

        var value = $(this).val(),
            label = discount_value_cnt.find('label'),
            other_span = $('<span class="ywf_currency_symbol"></span>');

        other_span.css('margin-left','5px' );
       if( !discount_value_cnt.find('.ywf_currency_symbol').length ){
           label.append( other_span );
       }

        if( value == 'fixed_cart' ){
            $('.ywf_currency_symbol').html('('+ywf_admin.wc_currency+')' );
        }else{
            $('.ywf_currency_symbol').html('(%)');
        }
    }).trigger('change');
});