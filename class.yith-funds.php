<?php

if( !defined('ABSPATH'))
    exit;

if( !class_exists('YITH_Funds')){

    class YITH_Funds{

        /**
         * @var YITH_Funds unique instance
         */
        protected static $_instance;
        /**
         * @var YIT_Plugin_Panel_WooCommerce
         */
        protected $_panel;
        /**
         * @var string official documentation
         */
        protected $_official_documentation = '//yithemes.com/docs-plugins/yith-woocommerce-account-funds/';
        /**
         * @var string landing page
         */
        protected $_plugin_landing_url = '//yithemes.com/themes/plugins/yith-woocommerce-account-funds/';

        /**
         * @var string plugin official live demo
         */
        protected $_premium_live_demo = '//plugins.yithemes.com/yith-woocommerce-account-funds/';
        /**
         * @var string panel page
         */
        protected $_panel_page = 'yith_funds_panel';
        
        public $is_wc_2_7 ;
        /**
         * YITH_Funds constructor.
         */
        public function __construct()
        {
            
            $this->is_wc_2_7 = version_compare( WC()->version, '2.7.0', '>=' );
            
            // Load Plugin Framework
            add_action( 'plugins_loaded', array( $this, 'plugin_fw_loader' ), 15 );
            //Add action links
            add_filter( 'plugin_action_links_' . plugin_basename( YITH_FUNDS_DIR . '/' . basename( YITH_FUNDS_FILE ) ), array( $this, 'action_links' ) );
            //Add row meta
            add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 4 );

            //Add action for register and update plugin
            add_action( 'wp_loaded', array( $this, 'register_plugin_for_activation' ), 99 );
            add_action( 'admin_init', array( $this, 'register_plugin_for_updates' ) );

            //Add YITH FUNDS menu
            add_action( 'admin_menu', array( $this, 'add_menu' ), 5 );

            //add admin style and script
            add_action( 'admin_enqueue_scripts', array( $this, 'include_admin_scripts' ) );

            //add deposit column in user table
            add_action( 'manage_users_columns', array( $this, 'add_user_deposit_column') );
            add_action( 'manage_users_custom_column', array( $this, 'show_user_deposit_column' ), 10,3 ) ;
            add_action('admin_menu', array( $this, 'users_log_table' ) );

            //add custom image-select field
            add_action( 'woocommerce_admin_field_image-select' ,array( $this, 'show_woocommerce_upload_field' ) );

            //add and show fund endpoints
          
            add_action( 'init', array( $this,'add_funds_endpoints' ) ,5 );
            add_action( 'init', array( $this, 'rewrite_rules' ), 20 );
            add_filter( 'yit_panel_wc_before_update', array( $this, 'rewrite_endpoints' ),10 );
            add_filter( 'yit_panel_wc_before_reset', array( $this, 'rewrite_endpoints' ),10 );

            $make_deposit_slug = ywf_get_fund_endpoint_slug( 'ywf_make_a_deposit' );
            $history_slug = ywf_get_fund_endpoint_slug( 'ywf_view_income_expenditure_history' );

            add_action( 'woocommerce_account_'.$make_deposit_slug.'_endpoint', array( $this ,'show_make_deposit_checkout_endpoint' ) );
            add_action( 'woocommerce_account_'.$history_slug.'_endpoint', array( $this ,'show_history_endpoint' ) );
          

            add_action('wp_enqueue_scripts',array( $this,'include_frontend_scripts') );


            //Add custom gateway
            add_filter( 'woocommerce_payment_gateways', array( $this,'add_gateway_funds_class' ) );
            add_filter( 'woocommerce_is_checkout', array( $this, 'load_script_checkout'), 5 );
            add_action('widgets_init', array( $this, 'register_ywf_widgets' ) );

            //add to my-account the new endpoints
            add_action( 'woocommerce_before_my_account', array( $this, 'show_customer_funds') );
            add_action( 'woocommerce_before_my_account', array( $this, 'show_customer_make_deposit_form'), 20 );
            add_action( 'woocommerce_before_my_account', array( $this, 'show_customer_recent_history'), 30 );

            //customer email
            add_filter('woocommerce_email_classes', array($this, 'add_woocommerce_emails'));
            
            //Add custom item in YITH MY ACCOUNT MENU
            add_action('yith_myaccount_menu', array( $this, 'add_myaccount_menu' ) );
            add_filter('woocommerce_account_menu_items', array( $this, 'funds_account_menu_items' ) );
            add_filter('yit_get_myaccount_menu_icon_list', array( $this, 'funds_account_menu_icon_list' ) );
            add_filter('yit_get_myaccount_menu_icon_list_fa', array( $this, 'funds_account_menu_icon_list_fa' ) );

           
            add_action( 'init', array( $this, 'create_deposit_product' ), 15 );
            add_filter( 'product_type_selector', array( $this, 'add_product_type' ) );
            add_filter( 'product_type_options', array( $this, 'add_type_option' ) );
            

            $this->init_end_points();
            YITH_YWF_Cart_Process();
            YITH_YWF_Deposit_Fund_Checkout();
            YWF_Log();
            YITH_YWF_Order();

            add_action( 'init', array( $this, 'fund_compatibility' ), 25 );
            //add admin notices
            add_action('admin_notices', array( $this, 'show_admin_notices' ) );
        }

        /**
         * @author YITHEMES
         * @since 1.0.0
         * @return YITH_Funds unique access
         */
        public static function get_instance(){

            if( is_null( self::$_instance ) ){

                self::$_instance = new self();
            }

            return self::$_instance;
        }

        /**
         * load plugin fw
         * @author YITHEMES
         * @since 1.0.0
         */
        public function plugin_fw_loader(){

            if ( !defined( 'YIT_CORE_PLUGIN' ) ) {
                global $plugin_fw_data;
                if ( !empty( $plugin_fw_data ) ) {
                    $plugin_fw_file = array_shift( $plugin_fw_data );
                    require_once( $plugin_fw_file );
                }
            }
        }

        /**
         * add custom action links
         * @author YITHEMES
         * @since 1.0.0
         * @param $links
         * @return array
         */
        public function action_links( $links ){

            $links[] = '<a href="' . admin_url( "admin.php?page={$this->_panel_page}" ) . '">' . __( 'Settings', 'yith-woocommerce-account-funds' ) . '</a>';

            $premium_live_text =  __( 'Live demo', 'yith-woocommerce-account-funds' );

            $links[] = '<a href="' . $this->_premium_live_demo . '" target="_blank">' . $premium_live_text . '</a>';


            return $links;
        }

        /**
         * add custom plugin meta
         * @author YITHEMES
         * @since 1.0.0
         * @param $plugin_meta
         * @param $plugin_file
         * @param $plugin_data
         * @param $status
         * @return array
         */
        public function plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data, $status ){

            if( defined('YITH_FUNDS_INIT') && YITH_FUNDS_INIT === $plugin_file ){


                $plugin_meta[] = '<a href="' . $this->_official_documentation . '" target="_blank">' . __( 'Plugin documentation', 'yith-woocommerce-account-funds' ) . '</a>';
            }

            return $plugin_meta;
        }

        /**
         * add YITH Funds menu under YITH_Plugins
         * @author YITHEMES
         * @since 1.0.0
         */
        public function add_menu(){

            if( !empty( $this->_panel ) )
                return;

            $admin_tabs = apply_filters( 'yith_funds_add_tab', array(
                'general-settings' => __( 'Settings', 'yith-woocommerce-account-funds' ),
                'email-settings' => __('Deposit funds - email settings', 'yith-woocommerce-account-funds' ),
                'email-advise-settings' => __('Funds edited - email settings','yith-woocommerce-account-funds'),
                'endpoints-settings' => __('Funds endpoints', 'yith-woocommerce-account-funds'),
            ) );

            $args = array(
                'create_menu_page' => true,
                'parent_slug' => '',
                'page_title' => __( 'Account Funds', 'yith-woocommerce-account-funds' ),
                'menu_title' => __( 'Account Funds', 'yith-woocommerce-account-funds' ),
                'capability' => 'manage_options',
                'parent' => '',
                'parent_page' => 'yit_plugin_panel',
                'links' => $this->get_panel_sidebar_links(),
                'page' => $this->_panel_page,
                'admin-tabs' => $admin_tabs,
                'options-path' => YITH_FUNDS_DIR . '/plugin-options'
            );

            if( !class_exists('YIT_Plugin_Panel_WooCommerce' ) ){
                require_once( YITH_FUNDS_DIR.'plugin-fw/lib/yith-plugin-panel-wc.php');
            }

            $this->_panel = new YIT_Plugin_Panel_WooCommerce( $args );
        }

        /**
         * add link to sidebar
         * @author YITHEMES
         * @since 1.0.0
         * @return array
         */
        public function get_panel_sidebar_links(){

            return array(

                array(
                    'url' => '//yithemes.com',
                    'title' => __('Your Inspiration Themes', 'yith-woocommerce-account-funds')
                ),
                array(
                    'url' => $this->_official_documentation,
                    'title' => __('Plugin Documentation', 'yith-woocommerce-account-funds'),
                ),
                array(
                    'url' => $this->_premium_live_demo,
                    'title' => __('Live Demo', 'yith-woocommerce-account-funds')
                ),
                array(
                    'url' => '//yithemes.com/my-account/support/dashboard',
                    'title' => __('Support platform', 'yith-woocommerce-account-funds')
                )
            );
        }

        /** Register plugins for activation tab
         * @return void
         * @since    1.0.0
         * @author   Andrea Grillo <andrea.grillo@yithemes.com>
         */
        public function register_plugin_for_activation() {
            if ( !class_exists( 'YIT_Plugin_Licence') ) {
                require_once YITH_FUNDS_DIR.'plugin-fw/licence/lib/yit-licence.php';
                require_once YITH_FUNDS_DIR.'plugin-fw/licence/lib/yit-plugin-licence.php';
            }
            YIT_Plugin_Licence()->register( YITH_FUNDS_INIT, YITH_FUNDS_SECRET_KEY, YITH_FUNDS_SLUG );
        }

        /**
         * Register plugins for update tab
         *
         * @return void
         * @since    1.0.0
         * @author   Andrea Grillo <andrea.grillo@yithemes.com>
         */
        public function register_plugin_for_updates() {
            if (!class_exists('YIT_Upgrade')) {
                require_once(YITH_FUNDS_DIR.'plugin-fw/lib/yit-upgrade.php');
            }
            YIT_Upgrade()->register( YITH_FUNDS_SLUG, YITH_FUNDS_INIT );
        }

        public function add_gateway_funds_class( $methods ){

            $methods[]='WC_Gateway_YITH_Funds';

            return $methods;
        }

        /**
         * init custom endpoints
         * @author YITHEMES
         * @since 1.0.0
         */
        public function init_end_points(){

            $is_customize_active = defined('YITH_WCMAP_PREMIUM') &&  YITH_WCMAP_PREMIUM;
            $slug_make_a_deposit = $slug_view_history = '';

            if( $is_customize_active ){

                $slug_make_a_deposit = get_option( 'woocommerce_myaccount_make_a_deposit_endpoint' );
                $slug_view_history = get_option( 'woocommerce_myaccount_view_history_endpoint' );
            }

            $slug_make_a_deposit = empty( $slug_make_a_deposit ) ?  ywf_get_fund_endpoint_slug( 'ywf_make_a_deposit' ) : $slug_make_a_deposit;
            $slug_view_history = empty( $slug_view_history ) ? ywf_get_fund_endpoint_slug( 'ywf_view_income_expenditure_history' ) : $slug_view_history;


            $this->end_points = array(
                'make-a-deposit' => $slug_make_a_deposit,
                'view-history' => $slug_view_history
            );

          


        }

        /**
         * if current endpoint is make-a-deposit, load checkout scripts
         * @since 1.0.7
         */
        public function load_script_checkout( $is_checkout ){

            if( ywf_is_make_deposit() ){
                return true;
            }

            
            return $is_checkout;
        }

        /**add custom endpoints
         * @author YITHEMES
         * @since 1.0.0
         */
        public function add_funds_endpoints(){

            foreach ( $this->end_points as $key => $var ) {

                WC()->query->query_vars[$key] = $var;
            }
        }


        public function rewrite_rules(){

            $rewrite = get_option('ywf_rewrite_rule', true );

            if( $rewrite ){
                
                flush_rewrite_rules();
                update_option('ywf_rewrite_rule', false);
                
            }
        }

        /**
         * @param $old_value
         * @param $new_value
         * @param $option
         */
        public function rewrite_endpoints(){

            $is_update = ( ( !empty( $_POST['ywf_make_a_deposit'] ) && get_option('ywf_make_a_deposit')!== $_POST['ywf_make_a_deposit'] )  ||
                            ( !empty( $_POST['ywf_view_income_expenditure_history'] ) && get_option('ywf_view_income_expenditure_history')!== $_POST['ywf_view_income_expenditure_history'] ) );
            $is_reset = 'yit_panel_wc_before_reset' === current_action();

            if( ( $is_reset || $is_update )
                &&
                isset( $_GET['page'] )
                &&
                $this->_panel_page == $_GET['page']
                && isset( $_GET['tab'] )
                &&
                'endpoints-settings' == $_GET['tab']
            ) {

               update_option( 'ywf_rewrite_rule', true );
            }
        }

        /**
         * set fund endpoints content
         * @author YITHEMES
         * @since 1.0.0
         */
        public function show_funds_endpoint()
        {

            $current_endpoint = WC()->query->get_current_endpoint();
            
            global $post;

            if( $current_endpoint === 'make-a-deposit' ){

                if( !is_user_logged_in() ){
                    wp_redirect( esc_url( wc_get_page_permalink( 'myaccount' ) ) );
                    exit;
                }
                $min = get_option('yith_funds_min_value');
                $max = get_option('yith_funds_max_value');
                $max = $max!==''? 'max="'.esc_attr($max).'"' : '';

                $is_complete = apply_filters( 'ywf_is_user_complete' ,$this->check_user_profile() );

                $args = array(
                    'payment' => array(
                        'checkout' => WC()->checkout(),
                        'available_gateways' => WC()->payment_gateways()->get_available_payment_gateways(),
                        'order_button_text'  => apply_filters( 'woocommerce_order_button_text', __( 'Place order', 'yith-woocommerce-account-funds' ) ),
                    ),
                    'min' => $min,
                    'max' => $max,
                    'amount' => isset( $_REQUEST['amount'] )? $_REQUEST['amount'] : '',
                    'currency' => get_woocommerce_currency(),
                    'user_profile_complete' => $is_complete,
                    'show_wc_menu' => true

                );



                ob_start();
                wc_get_template( 'make-a-deposit.php', $args,'', YITH_FUNDS_TEMPLATE_PATH );
                $post->post_title = ywf_get_fund_endpoint_name( 'ywf_make_a_deposit' );
                $post->post_content = ob_get_contents();
                ob_end_clean();
              

            }
            elseif( $current_endpoint === 'view-history' ){

                if( !is_user_logged_in() ){

                    wp_redirect( esc_url( wc_get_page_permalink( 'myaccount' ) ) );
                    exit;
                }
                ob_start();
                wc_get_template( 'deposit-history.php', array(), '', YITH_FUNDS_TEMPLATE_PATH );
                $post->post_title = ywf_get_fund_endpoint_name( 'ywf_view_income_expenditure_history' );
                $post->post_content = ob_get_contents();
                ob_end_clean();
            }
        }


        public function show_make_deposit_checkout_endpoint( $value ){

            if( !is_user_logged_in() ){
                wp_redirect( esc_url( wc_get_page_permalink( 'myaccount' ) ) );
                exit;
            }
            $min = get_option('yith_funds_min_value');
            $max = get_option('yith_funds_max_value');
            $max = $max!==''? 'max="'.esc_attr($max).'"' : '';

            $is_complete = apply_filters( 'ywf_is_user_complete' ,$this->check_user_profile() );

            $args = array(
                'payment' => array(
                    'checkout' => WC()->checkout(),
                    'available_gateways' => WC()->payment_gateways()->get_available_payment_gateways(),
                    'order_button_text'  => apply_filters( 'woocommerce_order_button_text', __( 'Place order', 'yith-woocommerce-account-funds' ) ),
                ),
                'min' => $min,
                'max' => $max,
                'amount' => isset( $_REQUEST['amount'] )? $_REQUEST['amount'] : '',
                'currency' => get_woocommerce_currency(),
                'user_profile_complete' => $is_complete,
                'show_wc_menu' => true

            );

            wc_get_template( 'make-a-deposit.php', $args,'', YITH_FUNDS_TEMPLATE_PATH );
        }

        public function  show_history_endpoint( $value ){
            
            if( !is_user_logged_in() ){

                wp_redirect( esc_url( wc_get_page_permalink( 'myaccount' ) ) );
                exit;
            }

            wc_get_template( 'deposit-history.php', array(), '', YITH_FUNDS_TEMPLATE_PATH );

           
        }
        /**
         * check if user profile is complete
         * @author YITHEMES
         * @since 1.0.0
         * @return bool
         */
        public function check_user_profile(){

            $customer_country = yit_get_prop( WC()->customer,'billing_country' );//->get_country();
            $customer_country_fields = WC()->countries->get_address_fields( $customer_country );
            $user_id = get_current_user_id();
            /**
             * @var WP_User $user
             */
            $user = get_user_by('id', $user_id );

            foreach( $customer_country_fields as $key => $value ){

                if( isset( $value['required'] ) && $value['required'] ){

                    $current_field = $user->get( $key );

                    if( empty( $current_field ) ){
                        return false;
                    }
                }
            }

            return true;
        }

        /**
         * return endpoints content
         * @author YITHEMES
         * @since 1.0.0
         * @param $content
         * @return string
         */
        public function show_endpoints_content( $content ){

            $current_endpoint = WC()->query->get_current_endpoint();
            global $post;

            if(  $current_endpoint === 'make-a-deposit' || $current_endpoint === 'view-history' ){


                return $post->post_content;
            }
            return $content;
        }

        /**
         * add deposit column in user table
         * @author YITHEMES
         * @since 1.0.0
         * @param $columns
         * @return mixed
         */
        public function add_user_deposit_column( $columns ){

            $columns['user_deposit'] = __('Deposit','yith-woocommerce-account-funds');

            return $columns;
        }

        /**
         * show user deposit in user table
         * @author YITHEMES
         * @since 1.0.0
         * @param $value
         * @param $column_name
         * @param $user_id
         * @return string
         */
        public function show_user_deposit_column( $value, $column_name, $user_id ){

            if( 'user_deposit' === $column_name ){

                $customer  = new YITH_YWF_Customer( $user_id );
                $funds = apply_filters( 'yith_admin_user_deposit_column', $customer->get_funds() );
                $value = wc_price( $funds );

                $show_log_params = array(
                    'page' => 'ywf_users_log_table',
                    'user_id' => $user_id
                );
                $show_log_link = esc_url( add_query_arg( $show_log_params, admin_url('users.php')));
                $actions['show_log'] = sprintf('<a href="%s">%s</a>', $show_log_link,__('Show logs','yith-woocommerce-account-funds') );

                $show_log_params['action']='edit_user_funds';
                $show_log_link = esc_url( add_query_arg( $show_log_params, admin_url('users.php')));

                $actions['edit_user_funds'] = sprintf('<a href="%s">%s</a>',$show_log_link,__('Edit funds','yith-woocommerce-account-funds'));

                $value.= $this->row_actions( $actions );
            }

            return $value;
        }

        /**
         * @param $actions
         * @param bool $always_visible
         * @return string
         */
        public function row_actions( $actions, $always_visible = false ) {
            $action_count = count( $actions );
            $i = 0;

            if ( !$action_count )
                return '';

            $out = '<div class="' . ( $always_visible ? 'row-actions visible' : 'row-actions' ) . '">';
            foreach ( $actions as $action => $link ) {
                ++$i;
                ( $i == $action_count ) ? $sep = '' : $sep = ' | ';
                $out .= "<span class='$action'>$link$sep</span>";
            }
            $out .= '</div>';

            $out .= '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __( 'Show more details' ) . '</span></button>';

            return $out;
        }

        public function register_ywf_widgets(){

            require_once('includes/widgets/class.yith-ywf-make-a-deposit-widget.php');
            require_once('includes/widgets/class.yith-ywf-view-user-funds-widget.php');
            register_widget( 'YITH_YWF_Make_a_Deposit_Widget' );
            register_widget( 'YITH_YWF_View_User_Funds_Widget' );
        }

        public function show_woocommerce_upload_field( $option ){

            $option['option'] = $option;
            wc_get_template('admin/image-select.php', $option, '', YITH_FUNDS_TEMPLATE_PATH );
        }

        /**
         * include admin style and script
         * @author YITHEMES
         * @since 1.0.0
         */
        public function include_admin_scripts(){

            $suffix = ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ) ? '' : '.min';
            
            if( isset( $_GET['page'] ) && 'yith_funds_panel' === $_GET['page'] ){
                $is_customize_active = defined('YITH_WCMAP_PREMIUM') &&  YITH_WCMAP_PREMIUM;
                wp_enqueue_script('ywf_admin_script', YITH_FUNDS_ASSETS_URL.'js/ywf-admin'.$suffix.'.js', array('jquery'), YITH_FUNDS_VERSION, true );
                
                $params = array( 
                    'is_customize_active' => $is_customize_active,
                    'wc_currency' => get_woocommerce_currency_symbol()
                );
                wp_localize_script( 'ywf_admin_script', 'ywf_admin', $params );
            }

            if( isset( $_GET['post'] ) && get_post_type( $_GET['post']) === 'shop_order' ){
                wp_enqueue_script('ywf_order_admin_script', YITH_FUNDS_ASSETS_URL.'js/ywf-order-admin'.$suffix.'.js', array('jquery'), YITH_FUNDS_VERSION, true );

                $params = array(
                    'tot_av_refund_tip' => __('You cannot refund an amount greater than user\'s total funds available.','yith-woocommerce-account-funds'),
                    'error_message_refund' => __('Attention! User\'s current funds are less than the amount you are entering', 'yith-woocommerce-account-funds')
                    
                );
                wp_localize_script('ywf_order_admin_script', 'ywf_params', $params );
            }
        }

        /**
         * include style and script
         * @author YITHEMES
         * @since 1.0.0
         */
        public function include_frontend_scripts(){

            $suffix = ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ) ? '' : '.min';

            wp_enqueue_style('ywf_style', YITH_FUNDS_ASSETS_URL.'css/ywf_frontend.css', array(), YITH_FUNDS_VERSION );
            wp_enqueue_script('ywf_script', YITH_FUNDS_ASSETS_URL.'js/ywf-frontend'.$suffix.'.js', array('jquery'), YITH_FUNDS_VERSION );
        }

        public function show_customer_funds(){

            $args = array('text_align'=>'left','font_weight'=>'normal');
            $args['args'] = $args;
            wc_get_template('view-customer-fund.php', $args, '', YITH_FUNDS_TEMPLATE_PATH );




        }

        /**
         * show make a deposit form
         * @author YITHEMES
         * @since 1.0.0
         */
        public function show_customer_make_deposit_form(){

            echo do_shortcode('[yith_ywf_make_a_deposit_form]');
        }


        public function show_customer_recent_history(){

            if( YWF_Log()->count_log() > 0 ) {
                $endpoint_url = esc_url( wc_get_endpoint_url( 'view-history', '', wc_get_page_permalink( 'myaccount' ) ) );
                $title = sprintf( '<h2>%s</h2><span class="ywf_show_all_history"><a href="%s">(%s)</a></span>', __( 'Recent history', 'yith-woocoomerce-funds' ), $endpoint_url, __( 'Show all', 'yith-woocommerce-account-funds' ) );
                $query_args['limit'] = 5;
                $query_args['offset'] = 0;
                $query_args['user_id'] = get_current_user_id();

                $additional_params = array(
                    'user_log_items' => YWF_Log()->get_log( $query_args ),
                    'page_links' => false,
                    'show_filter_form' => false,
                    'show_total' => false,
                );

                $additional_params['atts'] = $additional_params;

                echo $title;
                wc_get_template( 'view-deposit-history.php', $additional_params, '', YITH_FUNDS_TEMPLATE_PATH );
            }
        }

        /**
         * add new email class
         * @author YITHEMES
         * @since 1.0.0
         * @param array $emails
         * @return array
         */
        public function add_woocommerce_emails( $emails ){

            $emails['YITH_YWF_Customer_Email'] = include( YITH_FUNDS_INC . 'emails/class.yith-ywf-customer-email.php');
            $emails['YITH_YWF_Advise_Customer_Email'] = include( YITH_FUNDS_INC.'emails/class.yith-ywf-advise-customer-email.php');

            return $emails;
        }

        public function users_log_table() {

            $page_title = __('User funds log', 'yith-woocommerce-account-funds' );

            add_users_page( $page_title , $page_title, 'read', 'ywf_users_log_table', array( $this,'yith_show_log_table' ) );
        }


        public function yith_show_log_table(){

            wc_get_template('admin/user-log-table.php', array(),'', YITH_FUNDS_TEMPLATE_PATH );
        }

        /**
         * @author YITHEMES
         * @since 1.0.0
         * @param $myaccount_url
         */
        public function add_myaccount_menu( $myaccount_url ){

            global $wp;
            if( is_user_logged_in() ){
                $slug_make_deposit = ywf_get_fund_endpoint_slug('ywf_make_a_deposit');
                $slug_view_history = ywf_get_fund_endpoint_slug('ywf_view_income_expenditure_history');
                $make_deposit_name = ywf_get_fund_endpoint_name('ywf_make_a_deposit');
                $view_history_name = ywf_get_fund_endpoint_name('ywf_view_income_expenditure_history');
                ?>
                <li>
                    <span class="fa fa-credit-card"></span>
                    <a style="display: inline-block;padding-left: 8px;" href="<?php echo wc_get_endpoint_url($slug_make_deposit, '',  $myaccount_url ) ?>" title="<?php _e( 'Make a deposit', 'yith-woocommerce-account-funds' ); ?>"<?php echo isset( $wp->query_vars['make-a-deposit'] ) ? ' class="active"' : ''; ?>><?php _e( 'Make a deposit', 'yith-woocommerce-account-funds' ) ?></a>
                </li>
                <li>
                    <a href="<?php echo wc_get_endpoint_url( $slug_view_history, '', $myaccount_url ) ?>" title="<?php _e( 'My Funds History', 'yith-woocommerce-account-funds' ); ?>" <?php echo isset( $wp->query_vars['view-history'] )  ? ' class="active"' : ''; ?> >
                        <span data-icon="&#xe443;" data-font="retinaicon-font"></span><?php _e( 'My Funds History', 'yith-woocommerce-account-funds' ); ?>
                    </a>
                </li>
<?php
            }
        }

        /**
         * add menu items in my-account menu (WC 2.6)
         * @author YITEMES
         * @since 1.0.4
         * @param $menu_items
         * @return mixed
         */
        public function funds_account_menu_items( $menu_items ){

            $slug_make_deposit = ywf_get_fund_endpoint_slug('ywf_make_a_deposit');
            $slug_view_history = ywf_get_fund_endpoint_slug('ywf_view_income_expenditure_history');
            $make_deposit_name = ywf_get_fund_endpoint_name('ywf_make_a_deposit');
            $view_history_name = ywf_get_fund_endpoint_name('ywf_view_income_expenditure_history');

            $menu_items[$slug_make_deposit] = $make_deposit_name;
            $menu_items[$slug_view_history] = $view_history_name;

            return $menu_items;
        }

        public function funds_account_menu_icon_list( $icon_list ){
            $slug_make_deposit = ywf_get_fund_endpoint_slug('ywf_make_a_deposit');
            $slug_view_history = ywf_get_fund_endpoint_slug('ywf_view_income_expenditure_history');

            $icon_list[$slug_make_deposit] = '&#xe04d;';
            $icon_list[$slug_view_history] = '&#xe055;';

            return $icon_list;

        }

        /**
         * add fontawesome icon
         * @author YITHEMES
         * @since 1.0.4
         * @param $icon_list
         * @return mixed
         */
        public function funds_account_menu_icon_list_fa( $icon_list ){
            $slug_make_deposit = ywf_get_fund_endpoint_slug('ywf_make_a_deposit');
            $slug_view_history = ywf_get_fund_endpoint_slug('ywf_view_income_expenditure_history');

            $icon_list[$slug_make_deposit] = 'fa-money';
            $icon_list[$slug_view_history] = 'fa-folder-open';

            return $icon_list;
            
        }

        /**
         * initialize compatibility class
         * @author YITHEMES
         * @since 1.0.1
         */
        public function fund_compatibility(){
            
            YITH_FUNDS_Compatibility();
        }

        public function show_admin_notices(){

            $is_customize_active = defined('YITH_WCMAP_PREMIUM') &&  YITH_WCMAP_PREMIUM;
            if( ( isset( $_GET['page'] ) && 'yith_funds_panel' == $_GET['page'] ) && ( isset( $_GET['tab'] ) && 'endpoints-settings' == $_GET['tab'] ) && $is_customize_active ){

                $message= __('Customize My Account Page is activated, you can change the YITH Account Funds Endpoints here', 'yith-woocommerce-account-funds');
                $admin_url = admin_url('admin.php');
                $args = array(
                  'page' => 'yith_wcmap_panel',
                  'tab' => 'endpoints'
                );
                $page_url = esc_url( add_query_arg( $args, $admin_url ) );
                $message = sprintf('%1$s <a href="%2$s">%2$s</a>',$message, $page_url);
             ?>

                <div class="notice notice-info" style="padding-right: 38px;position: relative;">
                    <p><?php echo $message;?></p>
                </div>

            <?php
            }
        }
        
       public function create_deposit_product(){
            
           $deposit_id = get_option( '_ywf_deposit_id', -1 );
           $deposit_product = wc_get_product( $deposit_id ) ;
           
           if( $deposit_id == -1 || !$deposit_product ){
               
               $deposit_id = wp_insert_post( array(
                        'post_title'  => __( 'YITH Deposit', 'yith-woocommerce-account-funds' ),
			            'post_type'   => 'product',
			            'post_status' => 'private',
                        'post_content' => __('This product has been created by YITH Account Funds Plugin, please not remove', 'yith-woocommerce-account-funds')
                        )
               );

               wp_set_object_terms( $deposit_id, 'ywf_deposit', 'product_type' );

               $catalog_visibility_meta = version_compare( WC()->version, '2.7.0', '>=' ) ? 'catalog_visibility' : '_visibility';
               $product = wc_get_product( $deposit_id );

               yit_save_prop( $product, '_sold_individually', 'yes' );
               yit_save_prop( $product, $catalog_visibility_meta, 'hidden' );
               yit_save_prop( $product, '_virtual', 'yes' );
               yit_save_prop( $product, '_downloadable', 'yes' );


               update_option( '_ywf_deposit_id', $deposit_id );
           }

        }

        public function add_product_type( $product_type ){

            $product_type['ywf_deposit'] = __('Deposit', 'yith-woocommerce-account-funds' );
            return $product_type;
        }

        public function add_type_option( $array ) {
            if ( isset( $array["virtual"] ) ) {
                $css_class     = $array["virtual"]["wrapper_class"];
                $add_css_class = 'show_if_ywf_deposit';
                $class         = empty( $css_class ) ? $add_css_class : $css_class .= ' ' . $add_css_class;

                $array["virtual"]["wrapper_class"] = $class;
            }
            if( isset( $array['downloadable'] ) ){
                $css_class     = $array["downloadable"]["wrapper_class"];
                $add_css_class = 'show_if_ywf_deposit';
                $class         = empty( $css_class ) ? $add_css_class : $css_class .= ' ' . $add_css_class;

                $array["downloadable"]["wrapper_class"] = $class;
            }

            return $array;
        }
    }
}

