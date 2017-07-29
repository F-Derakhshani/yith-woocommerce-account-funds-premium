<?php
if (!defined('ABSPATH'))
    exit;

if ( !class_exists( 'YITH_YWF_Users_Log_Table' ) ) {
    require_once( YITH_FUNDS_INC . 'tables/class.yith-ywf-users-log-table.php' );
}

$table = new YITH_YWF_Users_Log_Table();
$message = '';

if( isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST[ 'nonce' ], basename( __FILE__ ) ) ){

    $user_id = $_POST['user_id'];
    $user = get_user_by('id', $user_id );
    $fund_user = new YITH_YWF_Customer( $user_id );
    $new_funds = $_POST['user_funds'];
    $old_funds = $fund_user->get_funds();
    $desc_op = esc_html( $_POST['admin_desc_op']);

  if( $new_funds!= $old_funds ) {
      $diff_funds = $new_funds-$old_funds;

      $fund_user->set_funds( $new_funds );

      $log_args = array( 'user_id' => $user_id, 'type_operation' => 'admin_op', 'fund_user' => $diff_funds, 'description' => $desc_op );

      $email_args = array(
          'user_id' => $user_id,
          'log_date' => date( wc_date_format(), current_time('timestamp') ),
          'before_funds' => $old_funds,
          'after_funds' => $new_funds,
          'change_reason' => $desc_op
      );

      WC()->mailer();
      do_action('ywf_send_advise_user_fund_email_notification', $email_args );
      YWF_Log()->add_log( $log_args );

      $message = __( 'User funds edited successfully', 'yith-woocommerce-account-funds' );
  }

}

 if( isset( $_GET['action'] )  && $_GET['action'] === 'edit_user_funds' && $_GET['user_id']!=='' ) {

     $user_id = $_GET['user_id'];
     $user = get_user_by('id', $user_id );
     $fund_user = new YITH_YWF_Customer( $user_id );
     $user_name =  $user->display_name ;
     $page_title = sprintf('<h2>%s %s</h2>', __('Edit funds for','yith-woocommerce-account-funds'), esc_html( $user_name ) );

     $url_args = esc_url( remove_query_arg( array('user_id','action' ) ) );

     ?>
     <div class="wrap">
         <h2><?php echo $page_title;?></h2>
         <?php if( !empty( $message ) ) : ?>
         <div id="message" class="updated below-h2"><p><?php echo $message; ?></p></div>
         <?php endif;?>
         <form method="post">
             <input type="hidden" name="nonce" value="<?php echo wp_create_nonce( basename( __FILE__ ) );?>">
             <table class="form-table">
                 <tbody>
                    <tr valign="top" class="titledesc">
                        <th scope="row"><label for="old_funds"><?php echo sprintf('%s (%s)', __('Current funds','yith-woocommerce-account-funds'), get_woocommerce_currency_symbol() );?></label></th>
                        <td class="forminp">
                            <input type="number" min="0" required step="any" name="user_funds" value="<?php echo $fund_user->get_funds();?>">
                            <input type="hidden" name="user_id" value="<?php esc_attr_e( $user_id );?>">
                            <span class="description"><?php _e('Edit user\'s funds!', 'yith-woocommerce-account-funds');?></span>
                        </td>
                    </tr>
                 <tr valign="top" class="titledesc">
                     <th scope="row"><label for="admin_desc_op"><?php _e('Description','yith-woocomerce-funds');?></label></th>
                     <td class="forminp">
                         <textarea style="width: 500px;" required name="admin_desc_op" rows="5" cols="30"></textarea>
                         <p class="description"><?php _e('Enter a brief description','yith-woocommerce-account-funds');?></p>
                     </td>
                 </tr>
                 </tbody>
             </table>
             <input type="hidden" name="action" value="<?php echo $_GET['action'];?>">
             <input type="submit" class="button-primary" value="<?php _e('Change user fund','yith-woocommerce-account-funds');?>">
             <a class="button-secondary" href="<?php echo $url_args; ?>"><?php _e( 'Return to log list', 'yith-woocommerce-account-funds' ); ?></a>
         </form>
     </div>

 <?php
 }else {

     $user_id = isset( $_GET['user_id'] ) ? $_GET['user_id'] : '';
     $user_name = '';
     if( !empty( $user_id )){
         $user = get_user_by('id', $user_id );
         $user_name =  $user->display_name ;
     }

     $page_title = sprintf('<h2>%s %s</h2>', __( 'User logs', 'yith-woocommerce-account-funds' ), esc_html( $user_name ) );
     $all_users =  get_users( array(  'blog_id' => $GLOBALS['blog_id'] ) );

     ?>

     <div class="wrap">
         <h2>
             <?php echo $page_title; ?>
         </h2>
         <div class="icon32 icon32-posts-post" id="icon-edit"><br/></div>
            <?php if( !empty( $user_id )) {

                $url_param = array(
                    'action' => 'edit_user_funds',
                    'page' => $_GET['page']
                );

                $edit_funds_url = esc_url( add_query_arg( $url_param ) );

                $edit_link = sprintf( '<a href="%s" class="button-primary">%s</a>', $edit_funds_url, __( 'Edit user funds', 'yith-woocommerce-account-funds' ) );

                echo $edit_link;
            }?>

         </h2>
         <?php
         $table->prepare_items();
         $table->views();
         ?>
         <form method="post">
             <?php
             if( $table->has_items() ) {
                 $table->display();
             } ?>
         </form>

     </div>
     <?php
 }