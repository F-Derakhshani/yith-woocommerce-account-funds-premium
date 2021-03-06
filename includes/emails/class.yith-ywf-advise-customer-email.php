<?php
if( !defined( 'ABSPATH' ) ) {
    exit;
}
if( !class_exists( 'YITH_YWF_Advise_Customer_Email' ) ) {

    class YITH_YWF_Advise_Customer_Email extends WC_Email
    {

        public function __construct()
        {
            $this->id = 'yith_advise_user_funds_email';
            $this->customer_email = true;
            $this->title = __( 'Customer funds note', 'yith-woocommerce-account-funds' );
            $this->description = __( 'This email is sent to customers when the administrator changes the amount of their available funds', 'yith-woocommerce-account-funds' );

            $this->subject = get_option( 'ywf_mail_admin_change_fund_subject' );
            $this->heading = get_option( 'ywf_user_change_fund_sender_name' );

            $this->template_html = 'emails/email-advise-user-funds.php';
            $this->template_plain = 'emails/plain/email-advise-user-funds.php';

            add_action( 'ywf_send_advise_user_fund_email_notification', array( $this, 'trigger' ) ,10 ,1 );
            parent::__construct();
        }


        public function trigger( $args )
        {

           if( empty( $args ) ){
               return;
           } 
            /**@var WP_User $user*/
            $user = get_user_by('id', $args['user_id'] );
            $this->recipient = $user->user_email;
            
            $this->find['username'] = '{customer_name}';
            $this->find['user_email'] = '{customer_email}';
            $this->find['site_title'] = '{site_title}';
            $this->find['log_date'] = '{log_date}';
            $this->find['change_reason'] = '{change_reason}';
            $this->find['before_funds'] = '{before_funds}';
            $this->find['after_funds'] = '{after_funds}';

            $order_id =ywf_get_user_currency( $args['user_id'] );
            $order = wc_get_order( $order_id );
            $currency = yit_get_prop( $order, '_order_currency' );
            
            $this->replace['username'] = $user->display_name;
            $this->replace['user_email'] = $user->user_email;
            $this->replace['site_title'] = $this->get_blogname();
            $this->replace['log_date'] = $args['log_date'];
            $this->replace['change_reason'] = $args['change_reason'];
            $before_funds = apply_filters( 'yith_fund_into_customer_email' , $args['before_funds'], $currency );
            $after_funds = apply_filters( 'yith_fund_into_customer_email' , $args['after_funds'], $currency );
            $this->replace['before_funds'] = wc_price( $before_funds, array( 'currency' => $currency ) );
            $this->replace['after_funds'] = wc_price( $after_funds, array( 'currency' => $currency ) );

            if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
                return;
            }


            $this->send( $this->get_recipient(), $this->get_subject(), $this->format_string( $this->get_content() ), $this->get_headers(), $this->get_attachments() );
        }

        public function get_content_html()
        {
            return wc_get_template_html( $this->template_html, array(
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text' => false,
                'email' => $this,
            ),
                YITH_FUNDS_TEMPLATE_PATH, YITH_FUNDS_TEMPLATE_PATH );
        }

        public function get_content_plain()
        {
            return wc_get_template_html( $this->template_plain, array(
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text' => true,
                'email' => $this,
            ),
                YITH_FUNDS_TEMPLATE_PATH, YITH_FUNDS_TEMPLATE_PATH );
        }

       
        /**
         * get_headers function.
         *
         * @access public
         * @return string
         */
        public function get_headers()
        {

            $headers = "Content-Type: " . $this->get_content_type() . "\r\n";

            return apply_filters('woocommerce_email_headers', $headers, $this->id, $this->object);
        }

        /**
         * check if this email is enabled
         * @author YITHEMES
         * @since 1.0.0
         * @return bool
         */
        public function is_enabled()
        {
            $enabled = get_option( 'ywf_mail_admin_change_fund_enabled' );

            return $enabled === 'yes';
        }

        /**
         * Admin Panel Options Processing - Saves the options to the DB
         *
         * @since   1.0.0
         * @return  boolean|null
         * @author  Alberto Ruggiero
         */
        public function process_admin_options()
        {

            woocommerce_update_options( $this->form_fields['email-advise-settings'] );
        }

        /**
         * Setup email settings screen.
         *
         * @since   1.0.0
         * @return  string
         * @author  Alberto Ruggiero
         */
        public function admin_options()
        {
            ?>
            <table class="form-table">
                <?php woocommerce_admin_fields( $this->form_fields['email-advise-settings'] ); ?>
            </table>

            <?php if( current_user_can( 'edit_themes' ) && ( !empty( $this->template_html ) || !empty( $this->template_plain ) ) ) { ?>
            <div id="template">
                <?php
                $templates = array(
                    'template_html' => __( 'HTML template', 'woocommerce' ),
                    'template_plain' => __( 'Plain text template', 'woocommerce' )
                );

                foreach ( $templates as $template_type => $title ) :
                    $template = $this->get_template( $template_type );

                    if( empty( $template ) ) {
                        continue;
                    }

                    $local_file = $this->get_theme_template_file( $template );
                    $core_file = YITH_FUNDS_TEMPLATE_PATH . $template;
                    $template_file = apply_filters( 'woocommerce_locate_core_template', $core_file, $template, YITH_FUNDS_TEMPLATE_PATH );
                    $template_dir = apply_filters( 'woocommerce_template_directory', 'woocommerce', $template );
                    ?>
                    <div class="template <?php echo $template_type; ?>">

                        <h4><?php echo wp_kses_post( $title ); ?></h4>

                        <?php if( file_exists( $local_file ) ) { ?>

                            <p>
                                <a href="#" class="button toggle_editor"></a>

                                <?php if( is_writable( $local_file ) ) : ?>
                                    <a href="<?php echo esc_url( wp_nonce_url( remove_query_arg( array( 'move_template', 'saved' ), add_query_arg( 'delete_template', $template_type ) ), 'woocommerce_email_template_nonce', '_wc_email_nonce' ) ); ?>"
                                       class="delete_template button"><?php _e( 'Delete template file', 'woocommerce' ); ?></a>
                                <?php endif; ?>

                                <?php printf( __( 'This template has been overridden by your theme and can be found in: <code>%s</code>.', 'woocommerce' ), trailingslashit( basename( get_stylesheet_directory() ) ) . $template_dir . '/' . $template ); ?>
                            </p>

                            <div class="editor" style="display:none">
                                <textarea class="code" cols="25" rows="20"
                                          <?php if( !is_writable( $local_file ) ) : ?>readonly="readonly"
                                          disabled="disabled"
                                          <?php else : ?>data-name="<?php echo $template_type . '_code'; ?>"<?php endif; ?>><?php echo file_get_contents( $local_file ); ?></textarea>
                            </div>

                        <?php }
                        elseif( file_exists( $template_file ) ) { ?>

                            <p>
                                <a href="#" class="button toggle_editor"></a>

                                <?php if( ( is_dir( get_stylesheet_directory() . '/' . $template_dir . '/emails/' ) && is_writable( get_stylesheet_directory() . '/' . $template_dir . '/emails/' ) ) || is_writable( get_stylesheet_directory() ) ) { ?>
                                    <a href="<?php echo esc_url( wp_nonce_url( remove_query_arg( array( 'delete_template', 'saved' ), add_query_arg( 'move_template', $template_type ) ), 'woocommerce_email_template_nonce', '_wc_email_nonce' ) ); ?>"
                                       class="button"><?php _e( 'Copy file to theme', 'woocommerce' ); ?></a>
                                <?php } ?>

                                <?php printf( __( 'To override and edit this email template copy <code>%s</code> into your theme folder: <code>%s</code>.', 'woocommerce' ), plugin_basename( $template_file ), trailingslashit( basename( get_stylesheet_directory() ) ) . $template_dir . '/' . $template ); ?>
                            </p>

                            <div class="editor" style="display:none">
                                <textarea class="code" readonly="readonly" disabled="disabled" cols="25"
                                          rows="20"><?php echo file_get_contents( $template_file ); ?></textarea>
                            </div>

                        <?php }
                        else { ?>

                            <p><?php _e( 'File was not found.', 'woocommerce' ); ?></p>

                        <?php } ?>

                    </div>
                    <?php
                endforeach;
                ?>
            </div>
            <?php
            wc_enqueue_js( "
				jQuery( 'select.email_type' ).change( function() {

					var val = jQuery( this ).val();

					jQuery( '.template_plain, .template_html' ).show();

					if ( val != 'multipart' && val != 'html' ) {
						jQuery('.template_html').hide();
					}

					if ( val != 'multipart' && val != 'plain' ) {
						jQuery('.template_plain').hide();
					}

				}).change();

				var view = '" . esc_js( __( 'View template', 'woocommerce' ) ) . "';
				var hide = '" . esc_js( __( 'Hide template', 'woocommerce' ) ) . "';

				jQuery( 'a.toggle_editor' ).text( view ).toggle( function() {
					jQuery( this ).text( hide ).closest(' .template' ).find( '.editor' ).slideToggle();
					return false;
				}, function() {
					jQuery( this ).text( view ).closest( '.template' ).find( '.editor' ).slideToggle();
					return false;
				} );

				jQuery( 'a.delete_template' ).click( function() {
					if ( window.confirm('" . esc_js( __( 'Are you sure you want to delete this template file?', 'woocommerce' ) ) . "') ) {
						return true;
					}

					return false;
				});

				jQuery( '.editor textarea' ).change( function() {
					var name = jQuery( this ).attr( 'data-name' );

					if ( name ) {
						jQuery( this ).attr( 'name', name );
					}
				});
			" );
        }
        }

        /**
         * Initialise Settings Form Fields
         *
         * @since   1.0.0
         * @return  void
         * @author  Alberto Ruggiero
         */
        public function init_form_fields()
        {
            $this->form_fields = include( YITH_FUNDS_DIR . '/plugin-options/email-advise-settings-options.php' );
        }

        public function get_email_type()
        {

            return get_option( 'ywf_mail_admin_change_fund_type' );
        }

        /**
         * get content type
         * @author YITHEMES
         * @since 1.0.0
         * @return string
         */
        public function get_content_type()
        {
            $type = get_option( 'ywf_mail_admin_change_fund_type' );

            switch ( $type ) {
                case 'html' :
                    return 'text/html';
                default :
                    return 'text/plain';
            }
        }
    }
}
return new YITH_YWF_Advise_Customer_Email();