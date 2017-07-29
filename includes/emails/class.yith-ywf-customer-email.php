<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

if ( !class_exists( 'YITH_YWF_Customer_Email' ) ) {

    class YITH_YWF_Customer_Email extends WC_Email
    {

        /**
         * @var string email content
         */
        protected $email_content;

        public function __construct()
        {
            $this->id = 'yith_user_funds_email';
            $this->customer_email = true;
            $this->title = __( 'Funds email', 'yith-woocommerce-account-funds' );
            $this->description = __( 'This is the email sent to customers by the admin through YITH WooCommerce Funds', 'yith-woocommerce-account-funds' );

            $this->heading = get_option( 'ywf_user_sender_name' );
            $this->subject = get_option( 'ywf_mail_subject' );

            $this->template_html = 'emails/email-user-funds.php';
            $this->template_plain = 'emails/plain/email-user-funds.php';


            // Triggers for this email
            add_action( 'ywf_send_user_fund_email_notification', array( $this, 'trigger' ), 10,1 );
            parent::__construct();




        }

        /**
         * send email
         * @author YITHEMES
         * @since 1.0.0
         * @param $user_id
         */
        public function trigger( $user_id )
        {

            $email_is_sent = get_user_meta( $user_id, '_user_mail_send', true );

           
            if (  empty( $email_is_sent ) || 'no' === $email_is_sent ) {

                $user = get_user_by( 'id', $user_id );
                $this->object = $user;

                $user_email = $user->user_email;
                $user_name = $user->display_name;

                //$user_funds_limit = get_option('ywf_email_limit');

                $customer = new YITH_YWF_Customer( $user_id );
                $this->recipient = $user_email;
                $email_content =  get_option('ywf_mail_content') ;

                $email_content = str_replace('{site_title}', $this->get_blogname(), $email_content );
                $email_content = str_replace('{customer_name}', $user_name ,$email_content );
                $email_content = str_replace('{customer_email}', $user_email ,$email_content );

                $order_id = ywf_get_user_currency( $user_id );
                $currency = get_post_meta( $order_id, '_order_currency', true );
                
                $funds = apply_filters( 'yith_fund_into_customer_email', $customer->get_funds() , $currency );
                $email_content = str_replace('{user_funds}', wc_price( $funds, array('currency' => $currency ) ) ,$email_content );

                $endpoint = ywf_get_fund_endpoint_slug( 'ywf_make_a_deposit' );
                $url = esc_url( wc_get_page_permalink('myaccount').$endpoint );

                $link = sprintf('<a href="%1$s" target="_blank">%1$s</a>', $url );
                $email_content = str_replace('{button_charging}', $link ,$email_content );

                $this->email_content = nl2br( $email_content );

                if( ! $this->is_enabled() || ! $this->get_recipient() ){
                    return;
                }

               $send = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(),$this->get_headers(),'' );

                $meta_value = $send ? 'yes' : 'no';

                update_user_meta( $user_id, '_user_mail_send', $meta_value );

            }
        }

        public function get_email_type(){

            return get_option( 'ywf_mail_type' );
        }
        /**
         * get content type
         * @author YITHEMES
         * @since 1.0.0
         * @return string
         */
        public function get_content_type()
        {
            $type = get_option( 'ywf_mail_type' );

            switch ( $type ) {
                case 'html' :
                    return 'text/html';
                default :
                    return 'text/plain';
            }
        }

        /**
         * Get HTML content for the mail
         *
         * @return string HTML content of the mail
         * @since  1.0
         * @author YITHEMES
         */
        public function  get_content_html()
        {
            return wc_get_template_html( $this->template_html, array(
                    'email_heading' => $this->get_heading(),
                    'email_content' => $this->email_content,
                    'sent_to_admin' => false,
                    'plain_text' => false,
                    'email' => $this
                ),YITH_FUNDS_TEMPLATE_PATH,YITH_FUNDS_TEMPLATE_PATH

            );
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
         * Get plain content for the mail
         *
         * @return string plain content of the mail
         * @since  1.0
         * @author YITHEMES
         */
        public function  get_content_plain()
        {
            return wc_get_template_html( $this->template_plain, array(
                    'email_heading' => $this->get_heading(),
                    'email_content' => $this->email_content,
                    'sent_to_admin' => false,
                    'plain_text' => true,
                    'email' => $this
                ),YITH_FUNDS_TEMPLATE_PATH,YITH_FUNDS_TEMPLATE_PATH
            );
        }

        /**
         * check if this email is enabled
         * @author YITHEMES
         * @since 1.0.0
         * @return bool
         */
        public function is_enabled()
        {
            $enabled = get_option( 'ywf_mail_enabled' );

            return $enabled === 'yes';
        }

        /**
         * Admin Panel Options Processing - Saves the options to the DB
         *
         * @since   1.0.0
         * @return  boolean|null
         * @author  Alberto Ruggiero
         */
        public function process_admin_options() {

            woocommerce_update_options( $this->form_fields['email-settings'] );
        }

        /**
         * Setup email settings screen.
         *
         * @since   1.0.0
         * @return  string
         * @author  Alberto Ruggiero
         */
        public function admin_options() {
            ?>
            <table class="form-table">
                <?php woocommerce_admin_fields( $this->form_fields['email-settings'] ); ?>
            </table>

            <?php if ( current_user_can( 'edit_themes' ) && ( ! empty( $this->template_html ) || ! empty( $this->template_plain ) ) ) { ?>
                <div id="template">
                    <?php
                    $templates = array(
                        'template_html'  => __( 'HTML template', 'woocommerce' ),
                        'template_plain' => __( 'Plain text template', 'woocommerce' )
                    );

                    foreach ( $templates as $template_type => $title ) :
                        $template = $this->get_template( $template_type );

                        if ( empty( $template ) ) {
                            continue;
                        }

                        $local_file    = $this->get_theme_template_file( $template );
                        $core_file     = YITH_FUNDS_TEMPLATE_PATH . $template;
                        $template_file = apply_filters( 'woocommerce_locate_core_template', $core_file, $template,  YITH_FUNDS_TEMPLATE_PATH );
                        $template_dir  = apply_filters( 'woocommerce_template_directory', 'woocommerce', $template );
                        ?>
                        <div class="template <?php echo $template_type; ?>">

                            <h4><?php echo wp_kses_post( $title ); ?></h4>

                            <?php if ( file_exists( $local_file ) ) { ?>

                                <p>
                                    <a href="#" class="button toggle_editor"></a>

                                    <?php if ( is_writable( $local_file ) ) : ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( remove_query_arg( array( 'move_template', 'saved' ), add_query_arg( 'delete_template', $template_type ) ), 'woocommerce_email_template_nonce', '_wc_email_nonce' ) ); ?>" class="delete_template button"><?php _e( 'Delete template file', 'woocommerce' ); ?></a>
                                    <?php endif; ?>

                                    <?php printf( __( 'This template has been overridden by your theme and can be found in: <code>%s</code>.', 'woocommerce' ), trailingslashit( basename( get_stylesheet_directory() ) ) . $template_dir . '/' . $template ); ?>
                                </p>

                                <div class="editor" style="display:none">
                                    <textarea class="code" cols="25" rows="20" <?php if ( ! is_writable( $local_file ) ) : ?>readonly="readonly" disabled="disabled"<?php else : ?>data-name="<?php echo $template_type . '_code'; ?>"<?php endif; ?>><?php echo file_get_contents( $local_file ); ?></textarea>
                                </div>

                            <?php } elseif ( file_exists( $template_file ) ) { ?>

                                <p>
                                    <a href="#" class="button toggle_editor"></a>

                                    <?php if ( ( is_dir( get_stylesheet_directory() . '/' . $template_dir . '/emails/' ) && is_writable( get_stylesheet_directory() . '/' . $template_dir . '/emails/' ) ) || is_writable( get_stylesheet_directory() ) ) { ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( remove_query_arg( array( 'delete_template', 'saved' ), add_query_arg( 'move_template', $template_type ) ), 'woocommerce_email_template_nonce', '_wc_email_nonce' ) ); ?>" class="button"><?php _e( 'Copy file to theme', 'woocommerce' ); ?></a>
                                    <?php } ?>

                                    <?php printf( __( 'To override and edit this email template copy <code>%s</code> into your theme folder: <code>%s</code>.', 'woocommerce' ), plugin_basename( $template_file ) , trailingslashit( basename( get_stylesheet_directory() ) ) . $template_dir . '/' . $template ); ?>
                                </p>

                                <div class="editor" style="display:none">
                                    <textarea class="code" readonly="readonly" disabled="disabled" cols="25" rows="20"><?php echo file_get_contents( $template_file ); ?></textarea>
                                </div>

                            <?php } else { ?>

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
        public function init_form_fields() {
            $this->form_fields = include( YITH_FUNDS_DIR . '/plugin-options/email-settings-options.php' );
        }

    }
}
return new YITH_YWF_Customer_Email();