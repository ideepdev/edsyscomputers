<?php
/**
 * GeneratePress child theme functions and definitions.
 *
 * Add your custom PHP in this file.
 * Only edit this file if you have direct access to it on your server (to fix errors if they happen).
 */

add_filter ( 'woocommerce_account_menu_items', 'silva_log_history_link', 40 );
function silva_log_history_link( $menu_links ){
 
	$menu_links = array_slice( $menu_links, 0, 5, true ) 
	+ array( 'autopilot' => 'Autopilot Configuration Download' )
	//+ array( 'credit-authority' => 'Credit Authority Request' )
	//+ array( 'warranty-return' => 'Warranty Return Request' )
	+ array_slice( $menu_links, 5, NULL, true );
 
	return $menu_links;
 
}
/*
 * Part 2. Register Permalink Endpoint
 */
add_action( 'init', 'silva_add_endpoint' );
function silva_add_endpoint() {
 
	// WP_Rewrite is my Achilles' heel, so please do not ask me for detailed explanation
	add_rewrite_endpoint( 'autopilot', EP_PAGES );
 
}
/*
 * Part 3. Content for the new page in My Account, woocommerce_account_{ENDPOINT NAME}_endpoint
 */
add_action( 'woocommerce_account_autopilot_endpoint', 'silva_my_account_endpoint_content' );
function silva_my_account_endpoint_content() {
 
	// Of course, you can print dynamic content here, one of the most useful functions here is get_current_user_id()
	$output  = "<h3>Microsoft Autopilot Configuration Download</h3>";
	$output .= "<p>Please enter your build number in the field below and click Get Download.</p>";
	$output .= "<hr/>";	
	
	$output .= "
	<form>
	<label> Build Number</label><br/>
		<input type='text' id='build-number' name='build_number' />
		<button onclick='download_file(); return false;'>Get Download</button>
	</form>";
	
	 $path = ABSPATH;
	
	if(isset($_GET['build_number']))
	{
		$output .= "<hr />";
		//$output .= "!!" . $_GET['build_number'] . "!!!";
		if(file_exists(ABSPATH . 'wp-content/autopilot/' . $_GET['build_number'] . '.csv'))
		{
			$output .= "<a download='" . $_GET['build_number'] . ".csv' href='/wp-content/autopilot/" . $_GET['build_number'] . ".csv'>Download Configuration File</a>";
		}
		else 
		{
			$output .= "<p>File does not exist.</p>";
		}
	}
	echo $output;
 
}
 
add_filter( 'woocommerce_get_endpoint_url', 'silva_hook_endpoint', 10, 4 );
function silva_hook_endpoint( $url, $endpoint, $value, $permalink ){
	if( $endpoint === 'credit-authority' ) {
		$url = "/my-account/credit-authority";
	}
	
	if( $endpoint === 'warranty-return' ) {
		$url = "/my-account/request-warranty";
	}
	return $url;
}

add_filter( 'woocommerce_cart_item_visible', 'bbloomer_hide_hidden_product_from_cart' , 10, 3 );
add_filter( 'woocommerce_widget_cart_item_visible', 'bbloomer_hide_hidden_product_from_cart', 10, 3 );
add_filter( 'woocommerce_checkout_cart_item_visible', 'bbloomer_hide_hidden_product_from_cart', 10, 3 );
add_filter( 'woocommerce_order_item_visible', 'bbloomer_hide_hidden_product_from_order_woo333', 10, 2 );
    
function bbloomer_hide_hidden_product_from_cart( $visible, $cart_item, $cart_item_key ) {
    $product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
    if ( $product->get_catalog_visibility() == 'hidden' ) {
        $visible = false;
    }
    return $visible;
}
    
function bbloomer_hide_hidden_product_from_order_woo333( $visible, $order_item ) {
    $product = $order_item->get_product();
    if ( $product->get_catalog_visibility() == 'hidden' ) {
        $visible = false;
    }
    return $visible;
}

function remove_product_editor() {
  remove_post_type_support( 'product', 'editor' );
}
add_action( 'init', 'remove_product_editor' );

function sww_add_sku_to_wc_emails( $args ) {
  
    $args['show_sku'] = true;
    return $args;
}
add_filter( 'woocommerce_email_order_items_args', 'sww_add_sku_to_wc_emails' );

add_action( 'woocommerce_single_product_summary', 'mh_output_stock_status', 16 );

function mh_output_stock_status ( ) {
    global $product;

    echo wc_get_stock_html( $product );

}

// Show/hide payment gateways
add_filter( 'woocommerce_available_payment_gateways', 'conditionally_hide_payment_gateways', 100, 1 );
function conditionally_hide_payment_gateways( $available_gateways ) {
    // 1. On Order Pay page
 if( is_wc_endpoint_url( 'order-pay' ) ) {

    }
    // 2. On Checkout page
    elseif( is_checkout() && ! is_wc_endpoint_url() ) {
        // Disable paypal
        //print_r($available_gateways);
        if( isset($available_gateways['commweb_hosted_checkout']) ) {
            unset($available_gateways['commweb_hosted_checkout']);
        }
		
		if( isset($available_gateways['commweb_direct_payment']) ) {
            unset($available_gateways['commweb_direct_payment']);
        }
    }
    return $available_gateways;
}




//Product description hook 
add_action( 'wpo_wcpdf_after_item_meta', 'wpo_wcpdf_show_product_description', 10, 3 );
function wpo_wcpdf_show_product_description ( $template_type, $item, $order ) {
    if (empty($item['product'])) return;
    $_product = $item['product']->is_type( 'variation' ) ? wc_get_product( $item['product']->get_parent_id() ) : $item['product'];
    if ( method_exists( $_product, 'get_short_description' ) && ( $item['product']->is_type('bundle')) ) {
        $description = $_product->get_short_description();
        printf('<div class="product-description">%s</div>', nl2br($description) );
    }
}

//enque javascript
function my_scripts_method() {
wp_enqueue_script(
    'custom-script',
    get_stylesheet_directory_uri() . '/js/custom_script.js',
    array( 'jquery' )
 );
}
add_action( 'wp_enqueue_scripts', 'add_font_awesome' );
//add font awesome in header
function add_font_awesome() {
wp_enqueue_script(
    'fontawesome',
    get_stylesheet_directory_uri() . '/js/fontawesome.js',
    array( )
 );
}

add_action( 'wp_enqueue_scripts', 'my_scripts_method' );
// change completed order status if order status is processing
add_action( 'woocommerce_before_thankyou', 'so_payment_complete' );
function so_payment_complete( $order_id ){
	$order = wc_get_order( $order_id );
	$transaction_id = $order->get_transaction_id();
	if($transaction_id){
		if( $order->has_status( 'processing' ) ){
			$order->update_status( 'completed' );
		}
	}

}
function cw_add_order_Payment_column_header($columns)
{
    $new_columns = array();
    foreach ($columns as $column_name => $column_info) {
        $new_columns[$column_name] = $column_info;
        if ('order_total' === $column_name) {
            $new_columns['order_Payment'] = __('Payment', 'my-textdomain');
        }
    }
    return $new_columns;
}
add_filter('manage_edit-shop_order_columns', 'cw_add_order_Payment_column_header');
function cw_add_order_profit_column_content( $column ) {
    global $post;
    if ( 'order_Payment' === $column ) {
        $order    = wc_get_order( $post->ID );
        $payment_status = get_field( "payment_status", $post->ID );
        if($payment_status == "auto"){
            $transaction_id = $order->get_transaction_id();
            $paymentMethod = $order->get_payment_method();
            $order_status = $order->get_status();
            if(($paymentMethod == 'commweb_hosted_checkout')){
                echo "Paid";
            }elseif ((($paymentMethod =='b2bking-invoice-gateway') || ($paymentMethod =='cod')) && ($order_status == 'completed')) {
                echo "Paid";
            }else{
                echo "Unpaid";
            }
        }elseif($payment_status == "manual"){
            $visible_payment_status = get_field( "visible_payment_status", $post->ID );
            echo $visible_payment_status;
        }else{
            echo "Unpaid";
        }
    }
}
add_action( 'manage_shop_order_posts_custom_column', 'cw_add_order_profit_column_content' );
//start code Adding a custom new column user name  to admin orders list by sachin
add_filter( 'manage_edit-shop_order_columns', 'custom_column_eldest_players', 20 );
function custom_column_eldest_players($columns)
{
    $reordered_columns = array();

    // Inserting columns to a specific location
    foreach( $columns as $key => $column){
        $reordered_columns[$key] = $column;
        if( $key ==  'cb' ){
            // Inserting after "Status" column
            $reordered_columns['user_login'] = __( 'Customer','theme_domain');
        }
    }
    return $reordered_columns;
}
add_action( 'manage_shop_order_posts_custom_column' , 'custom_orders_list_column_content', 20, 2 );
function custom_orders_list_column_content( $column, $post_id )
{
    if ( 'user_login' != $column ) return;

    global $the_order;

    // Get the customer id
    $user_id = $the_order->get_customer_id();

    if( ! empty($user_id) && $user_id != 0) {
        echo get_post_meta($post_id, 'user_login_id', true);
        $user_data = get_userdata( $user_id );
        //echo $user_data->user_login; // The WordPress user name
    }else{
        echo '<small>(<em>Guest</em>)</small>';
    }
        $order_id = $post_id;
    global $wpdb;

    $table_perfixed = $wpdb->prefix . 'comments';
    $results = $wpdb->get_results("
        SELECT *
        FROM $table_perfixed
        WHERE  `comment_post_ID` = $order_id
        AND  `comment_type` LIKE  'order_note' ORDER BY comment_ID DESC LIMIT 1
    ");
    foreach($results as $note){
        $position = strpos($note->comment_content,"ErrorNumber: 10");
		$xero_invoice_id = get_post_meta($post_id, '_xero_invoice_id', true);
		$xero_payment_id = get_post_meta($post_id, '_xero_payment_id', true);
		$position2 = strpos($note->comment_content,"Xero Payment created");
        if (is_numeric($position) && !$xero_invoice_id && !$xero_payment_id){
            echo '<i class="fa fa-exclamation-triangle" style="color:red;" aria-hidden="true" title="'.$note->comment_content.'"></i>';
        }
        if ($xero_invoice_id){
            echo '<i class="fa fa-exclamation-triangle" style="color:green;" aria-hidden="true" title="Invoice Created"></i>';
        }
        if ($xero_payment_id){
            echo '<i class="fa fa-credit-card" style="color:green;" aria-hidden="true" title="Payment Created"></i>';
        }
    }
        
}

function woocommerce_shop_order_search_order_total( $search_fields ) {

  $search_fields[] = 'user_login_id';

  return $search_fields;
}
add_filter( 'woocommerce_shop_order_search_fields', 'woocommerce_shop_order_search_order_total' );
function create_username_for_wc_order($post_id, $post, $update) {

     $order =  wc_get_order( $post_id );
     
     if($user_info = get_userdata($order->user_id)){
          $user_name = $user_info->user_login; 
          if(update_post_meta( $post_id, 'user_login_id', sanitize_text_field($user_name) )){
          } 
     }
}

add_action( 'wp_insert_post', 'create_username_for_wc_order', 10, 3 );
//change order number prefix like SO10001
add_filter( 'woocommerce_order_number', 'change_woocommerce_order_number' );

function change_woocommerce_order_number( $order_id ) {
    $prefix = 'SO';
    $suffix = '/TS';
    $new_order_id = $prefix . $order_id;
    return $new_order_id;
}
add_action( 'woocommerce_after_shop_loop_item_title', 'custom_after_title' );

//Niyam Code starts to bring custom order statuses outside

function custom_after_title() {
        global $product;
        if ( $product->get_sku() ) {
           echo "<p class='product-sku-cls'>SKU - ".$product->get_sku()."</p>";
        }
        if($product->is_type('bundle')){
            $post_id = $product->get_id();
            $terms = get_the_terms( $post_id, 'product_cat' );

            $cate_ides =[];
            if($terms){
                foreach($terms as $category){
                  $cate_ides[] = $category->term_id;
                }   
            }
            if ((in_array(293, $cate_ides)) || (in_array(287, $cate_ides)) || (in_array(286, $cate_ides)) || (in_array(289, $cate_ides))){
                $available_on_backorder_text = "Built to order";

            }else{
                $available_on_backorder_text =  "Available on backorder";
            }
        }else{
            $statuses = get_option( 'wc_custom_stock_statuses' );
            $status   = get_post_meta( $product->get_id(), '_stock_status', true );
            $color    = esc_attr( $statuses[ $status ]['id'] );
            $label    = esc_attr( $statuses[ $status ]['name'] );
            if ( $product->is_in_stock() ) {
                if($product->get_stock_quantity() > 9 ) {
                    $available_on_backorder_text = __( '10+ in stock Available!', 'wc-custom-stock-status' );
                } elseif($product->get_stock_quantity() > 4 ) {
                    $available_on_backorder_text = __( '5+ in stock Available!', 'wc-custom-stock-status' );
                } 
                elseif ($product->get_stock_quantity() > 0 ) {
                    $available_on_backorder_text= __( '1+ in stock Available!', 'wc-custom-stock-status' );
                }
                else {
                    $available_on_backorder_text = __( 'Available on backorder', 'wc-custom-stock-status' );
                } 
            } elseif ( ! $product->is_in_stock() ) {
                $available_on_backorder_text = 'Sold Out!';
                
            }
            if ( ! empty( $label ) ) {
                $available_on_backorder_text = '<span style="color:' . $color . '">' . $label . '</span>';
            }
        }
        echo "<p class='product-available-cls'>".$available_on_backorder_text."</p>";
}
//Niyam Code  ends 



// add searchbar in header
add_action( 'generate_after_header_content', 'change_woocommerceheader' );

function change_woocommerceheader() {
    echo "</br><div class='new-search' style='width:200px'>".do_shortcode('[fibosearch]')."</div>";
}
//add_action( 'woocommerce_admin_order_data_after_billing_address', 'woo_display_order_username', 10, 1 );

function woo_display_order_username( $order ){

    global $post;
    
    $customer_user = get_post_meta( $post->ID, '_customer_user', true );
    echo '<p><strong style="display: block;">'.__('Customer Username').':</strong> <a href="user-edit.php?user_id=' . $customer_user . '">' . get_user_meta( $customer_user, 'nickname', true ) . '</a></p>';
}
//End code Adding a custom new column user name  to admin orders list by sachin
function bundle_hide(){ ?>
<script>
jQuery(document).ready(function(){
jQuery(".wc-bundled-items .visibility_order").attr('checked', 'checked');
	 jQuery(".wc-bundled-items .visibility_product").removeAttr('checked');
	jQuery(".wc-bundled-items .visibility_cart").removeAttr('checked');
  });
	</script>}
<?php } 
 
add_action('admin_head', 'bundle_hide');


add_action( 'gform_after_submission_6', 'after_submission', 10, 2 );

function after_submission(){
	$ca_number = get_option( 'current_ca_number' );
	if($ca_number){
		$ca_number++;
		update_option( 'current_ca_number' , $ca_number );
	}else{
		add_option( 'current_ca_number' , 1 );
	}
}


function ca_number_shortcode() {
    $ca_number = get_option( 'current_ca_number' );
	return $ca_number;
}
add_shortcode('ca_number', 'ca_number_shortcode');

add_action('admin_head', 'capitalisesku');
function capitalisesku(){
?><style>
	li.select2-results__option {
		text-transform: uppercase;
		}
        .bundled_item .change_bundle_order_line_item {
        display: none;
        }
		</style>
 <?php
	if (isset($_GET['post_type']) && ($_GET['post_type'] == 'product')) {
		if (isset($_GET['product_tag']) && ($_GET['product_tag'] == 306)) {
		    echo '<style>
			.product_tag-custom-product{
			display:table-row;
			}
			</style>';
		}else{
		    echo '<style>
			.product_tag-custom-product{
			display:none;
			}
			</style>';
		}
	}
 ?>

<?php
}


//add_filter( 'init', 'user_meta' );

function user_meta() {
	$users = get_users( array( 'fields' => array( 'ID' ) ) );
	foreach ($users as $key => $user) {
			$u = new WP_User( $user->ID );
			$u->add_role( 'customer' );
	}
}

//Niyam codes, changing related products to show for tags 

add_filter( 'woocommerce_output_related_products_args', function( $args ) 
{ 
    $args = wp_parse_args( array( 'posts_per_page' => 5 ), $args );
    return $args;
});

add_filter( 'woocommerce_product_related_posts_relate_by_category', function() {
    return false;
});

//removing related products

remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );

// Niyam code ends 
// 
//start code user privileges  by sachin 
add_filter( 'woocommerce_settings_tabs_array', 'remove_woocommerce_setting_tabs', 200, 1 );
function remove_woocommerce_setting_tabs( $tabs ) {
    // Get the current user
    $user = wp_get_current_user();
    // Check if user is a shop-manager
    if ( isset( $user->roles[0] ) && $user->roles[0] == 'stores' ) {
        //unset($tabs['general']);
        unset($tabs['products']);
        unset($tabs['tax']);
        unset($tabs['checkout']);
        unset($tabs['account']);
        unset($tabs['email']);
        unset($tabs['integration']);
        unset($tabs['advanced']);
        unset($tabs['wc_sa_settings']);
        unset($tabs['wts_settings']);
    }

    return $tabs;
}
//hide/show admin menu according to role
function hide_menu() {

    if ( !is_user_logged_in() ) { return false; }
    global $current_user;

    $user_ID = get_current_user_id(); 
    $user = new WP_User( $user_ID );

    if ( !empty( $user->roles ) ) {
    foreach ( $user->roles as $role ){
      $rolename =  $role;
		}
    }
    if($rolename == 'stores'){
		remove_menu_page( 'edit.php' ); //Posts
 		remove_submenu_page( 'admin.php','wk-purchase-management' ); // Pages
 		remove_menu_page( 'vgsefe_welcome_page' ); // Pages
		remove_menu_page( 'edsys-reports' ); // Pages
 		remove_submenu_page( 'woocommerce','acf-options-multiple-customer-addresses-options' ); // Pages
		remove_submenu_page( 'woocommerce','dgwt_wcas_settings' ); // Pages
		remove_submenu_page( 'woocommerce','wc-admin' ); // Pages
		remove_submenu_page( 'wk-purchase-management','wk-purchase-management-config' ); //theme-option
		remove_menu_page( 'woocommerce-marketing' ); // Pages
		remove_menu_page( 'webappick-woo-invoice' ); // Pages
		remove_menu_page( 'warranties' );
		remove_menu_page( 'b2bking' ); // Pages
		remove_submenu_page( 'woocommerce','woocommerce_xero' ); // Pages
        remove_submenu_page( 'woocommerce','checkout_field_editor' ); // Pages
		remove_submenu_page( 'woocommerce','wpo_wcpdf_options_page' );
		remove_submenu_page( 'woocommerce','wc-addons&section=helper' );
		echo "<style>.form-field._stock_status_field {
		  display: none !important;
		}
		.form-field._stock_field {
		  display: none;
		}</style>";
	}elseif($rolename == 'steve_wells'){
		remove_menu_page( 'edit.php' ); //Posts
 		remove_submenu_page( 'admin.php','wk-purchase-management' ); // Pages
		remove_menu_page( 'edsys-reports' ); // Pages
		remove_menu_page( 'edit.php?post_type=page' ); // Pages
		remove_submenu_page( 'warranties','warranties' ); //theme-option
		//remove_submenu_page( 'warranties','warranties-bulk-update' ); // Pages
		remove_menu_page( 'warranties' );
		remove_menu_page( 'wc-admin&path=/analytics/overview' );
		remove_menu_page( 'wpseo_workouts' );
		remove_menu_page( 'webappick-woo-invoice' );
		remove_menu_page( 'woocommerce-marketing' );
		        //woocommerce
        remove_submenu_page( 'woocommerce','auspost_manifest' ); // Pages
        remove_submenu_page( 'woocommerce','woocommerce_xero' ); // Pages
        remove_submenu_page( 'woocommerce','checkout_field_editor' ); // Pages
        remove_submenu_page( 'woocommerce','dgwt_wcas_settings' ); // Pages
        remove_submenu_page( 'woocommerce','wc-reports' ); // Pages
        remove_submenu_page('woocommerce', 'wc-admin');
        remove_submenu_page('woocommerce', 'wc-admin&path=/customers');
        remove_submenu_page('woocommerce', 'acf-options-multiple-customer-addresses-options');
        remove_submenu_page( 'woocommerce','wpo_wcpdf_options_page' );
		echo "<style>.form-field._stock_status_field {
		  display: none !important;
		}
		.form-field._stock_field {
		  display: none;
		}</style>";
	}elseif($rolename == 'orders_and_products_only'){
		remove_menu_page( 'edit.php' ); //Posts
 		remove_submenu_page( 'admin.php','wk-purchase-management' ); // Pages
		remove_menu_page( 'edsys-reports' ); // Pages
		remove_menu_page( 'edit.php?post_type=page' ); // Pages
		remove_submenu_page( 'warranties','warranties' ); //theme-option
		remove_menu_page( 'warranties' );
		remove_menu_page( 'wc-admin&path=/analytics/overview' );
		remove_menu_page( 'wpseo_workouts' );
		remove_menu_page( 'webappick-woo-invoice' );
		remove_menu_page( 'woocommerce-marketing' );
		        //woocommerce
        remove_submenu_page( 'woocommerce','auspost_manifest' ); // Pages
        remove_submenu_page( 'woocommerce','woocommerce_xero' ); // Pages
        remove_submenu_page( 'woocommerce','checkout_field_editor' ); // Pages
        remove_submenu_page( 'woocommerce','dgwt_wcas_settings' ); // Pages
        remove_submenu_page( 'woocommerce','wc-reports' ); // Pages
        remove_submenu_page('woocommerce', 'wc-admin');
        remove_submenu_page('woocommerce', 'wc-admin&path=/customers');
        remove_submenu_page('woocommerce', 'acf-options-multiple-customer-addresses-options');
        remove_submenu_page( 'woocommerce','wpo_wcpdf_options_page' );
		remove_menu_page( 'b2bking' ); // Pages
		remove_menu_page( 'vgsefe_welcome_page' ); // Pages
		remove_menu_page( 'wk-purchase-management' ); // Pages
		remove_menu_page( 'edit.php?post_type=acf-field-group' );
		remove_submenu_page('woocommerce', 'wc-settings');
		remove_submenu_page( 'woocommerce','wc-addons&section=helper' );
		remove_menu_page('upload.php');
		remove_menu_page( 'export-personal-data.php' );
		remove_menu_page( 'index.php' );
	}elseif($rolename == 'inventory_and_invoices'){
		remove_menu_page( 'edit.php' ); //Posts
 		remove_submenu_page( 'admin.php','wk-purchase-management' ); // Pages
		remove_menu_page( 'edsys-reports' ); // Pages
		remove_menu_page( 'edit.php?post_type=page' ); // Pages
		remove_submenu_page( 'warranties','warranties' ); //theme-option
		remove_menu_page( 'warranties' );
		remove_menu_page( 'wc-admin&path=/analytics/overview' );
		remove_menu_page( 'wpseo_workouts' );
		remove_menu_page( 'webappick-woo-invoice' );
		remove_menu_page( 'woocommerce-marketing' );
		remove_menu_page( 'post-import' );
		        //woocommerce
        remove_submenu_page( 'woocommerce','auspost_manifest' ); // Pages
        remove_submenu_page( 'woocommerce','woocommerce_xero' ); // Pages
        remove_submenu_page( 'woocommerce','checkout_field_editor' ); // Pages
        remove_submenu_page( 'woocommerce','dgwt_wcas_settings' ); // Pages
        remove_submenu_page( 'woocommerce','wc-reports' ); // Pages
        remove_submenu_page('woocommerce', 'wc-admin');
        remove_submenu_page('woocommerce', 'wc-admin&path=/customers');
        remove_submenu_page('woocommerce', 'acf-options-multiple-customer-addresses-options');
        remove_submenu_page( 'woocommerce','wpo_wcpdf_options_page' );
		remove_menu_page( 'b2bking' ); // Pages
		remove_menu_page( 'vgsefe_welcome_page' ); // Pages
		remove_menu_page( 'wk-purchase-management' ); // Pages
		remove_menu_page( 'edit.php?post_type=acf-field-group' );
		remove_submenu_page('woocommerce', 'wc-settings');
		remove_submenu_page( 'woocommerce','wc-addons&section=helper' );
		remove_menu_page('upload.php');
		remove_menu_page( 'export-personal-data.php' );
		remove_menu_page( 'index.php' );
	}elseif($rolename == 'prouduction'){
		remove_menu_page( 'edit.php' ); //Posts
 		remove_submenu_page( 'admin.php','wk-purchase-management' ); // Pages
		remove_menu_page( 'edsys-reports' ); // Pages
		remove_menu_page( 'edit.php?post_type=page' ); // Pages
		remove_submenu_page( 'warranties','warranties' ); //theme-option
		remove_submenu_page( 'warranties','warranties-bulk-update' ); // Pages
		remove_menu_page( 'warranties' );
		remove_menu_page( 'b2bking' ); // Pages
		remove_menu_page( 'wc-admin&path=/analytics/overview' );
		remove_menu_page( 'wpseo_workouts' );
		remove_menu_page( 'webappick-woo-invoice' );
		remove_menu_page( 'woocommerce-marketing' );
		
	
 		remove_menu_page( 'vgsefe_welcome_page' ); // Pages
		remove_menu_page( 'wk-purchase-management' ); // Pages
		remove_menu_page( 'edit.php?post_type=acf-field-group' );
		
		
		        //woocommerce
        remove_submenu_page( 'woocommerce','auspost_manifest' ); // Pages
        remove_submenu_page( 'woocommerce','woocommerce_xero' ); // Pages
        remove_submenu_page( 'woocommerce','checkout_field_editor' ); // Pages
        remove_submenu_page( 'woocommerce','dgwt_wcas_settings' ); // Pages
        remove_submenu_page( 'woocommerce','wc-reports' ); // Pages
        remove_submenu_page('woocommerce', 'wc-admin');
        remove_submenu_page('woocommerce', 'wc-admin&path=/customers');
        remove_submenu_page('woocommerce', 'acf-options-multiple-customer-addresses-options');
        remove_submenu_page( 'woocommerce','wpo_wcpdf_options_page' );
		remove_submenu_page('woocommerce', 'wc-settings');
	}elseif($rolename == 'super-user'){
		remove_menu_page( 'edit.php?post_type=page' ); // Pages
		remove_submenu_page( 'warranties','warranties' ); //theme-option
		remove_menu_page( 'warranties' );
		remove_menu_page( 'wc-admin&path=/analytics/overview' );
		remove_menu_page( 'wpseo_workouts' );
		remove_menu_page( 'webappick-woo-invoice' );
		remove_menu_page( 'woocommerce-marketing' );
		        //woocommerce
	}elseif($rolename == 'simon_grownow'){
		remove_menu_page( 'edit.php' ); //Posts
 		remove_menu_page( 'vgsefe_welcome_page' ); // Pages
		remove_menu_page( 'edsys-reports' ); // Pages
		remove_menu_page( 'woocommerce-marketing' ); // Pages
		remove_menu_page( 'webappick-woo-invoice' ); // Pages
		remove_menu_page( 'warranties' );
		remove_menu_page( 'edit.php?post_type=acf-field-group' );
		echo "<style>.form-field._stock_status_field {
		  display: none !important;
		}
		.form-field._stock_field {
		  display: none;
		}</style>";
	}
	echo "<style>#postcustom{
	display:none;
	}</style>";
}
add_action('admin_head', 'hide_menu');
add_filter( 'contextual_help', 'mytheme_remove_help_tabs', 999, 3 );
function mytheme_remove_help_tabs($old_help, $screen_id, $screen){
    $screen->remove_help_tabs();
    return $old_help;
}
//start code for set po number by front-end value of reference no by sachin
add_action( 'woocommerce_order_status_processing_to_on-hold', 'is_express_delivery', 10, 2 );
add_action( 'woocommerce_order_status_pending_to_processing', 'is_express_delivery', 10, 2 );
function is_express_delivery( $order_id, $order ){
   $order_refrrence = get_post_meta($order_id, 'order_reference', true);
   if($order_refrrence){

     update_field('purchase_number', $order_refrrence, $order_id);
   }
}
//End Code for set po number by front-end value of refrence no by sachin
//add_action( 'woocommerce_email_after_order_table', 'mm_email_after_order_table', 10, 4 );
function mm_email_after_order_table( $order, $sent_to_admin, $plain_text, $email ) { 
      echo "<h2 style=\"color: #582C80\">PO Number</h2> ".get_post_meta($order->get_id(), 'purchase_number', true)."<br><br>";
}

// start code Add a custom meta field in order line item by sachin

    add_action( 'woocommerce_after_order_itemmeta', 'add_order_item_custom_field', 10, 2 );

    function add_order_item_custom_field( $item_id, $item ) {

        // Targeting line items type only

        global $wpdb;

        if( $item->get_type() !== 'line_item' ) return;
        woocommerce_wp_checkbox( 
        array( 
            'id'            => 'change_bundle_order_line_item_'.$item_id, 
            'wrapper_class' => 'show_if_simple', 
            'class'      => 'change_bundle_order_line_item select short', 
            'label'         => __('Add as a Bundle Item', 'woocommerce' ),
            'value'    => wc_get_order_item_meta( $item_id, 'change_bundle_order_line_item_' )
            )
        );

    }

    // Save the custom field value

    add_action('save_post_shop_order', 'save_order_item_custom_field_value');

    function save_order_item_custom_field_value( $post_id ){

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX )

            return $post_id;

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )

            return $post_id;

        if ( ! current_user_can( 'edit_shop_order', $post_id ) )

            return $post_id;

        $order = wc_get_order( $post_id );

        // Loop through order items

        foreach ( $order->get_items() as $item_id => $item ) {
            if( isset( $_POST['change_bundle_order_line_item_'.$item_id] ) ) {
                
                wc_update_order_item_meta( $item_id, 'change_bundle_order_line_item_', sanitize_text_field( $_POST['change_bundle_order_line_item_'.$item_id] ) );

            }else{
                wc_update_order_item_meta( $item_id, 'change_bundle_order_line_item_', 'no' );
            }

        }

    }

    add_filter( 'woocommerce_hidden_order_itemmeta', 'additional_hidden_order_itemmeta', 10, 1 );

    function additional_hidden_order_itemmeta( $args ) {
        $args[] = 'change_bundle_order_line_item_';
        return $args;

    }

// End code Add a custom field in order line item by sachin

add_action ('admin_head','hide_checkboxbundle');
function hide_checkboxbundle(){
	echo '<style>
	.bundled_item  .form-field, .bundle_item .form-field{
	display:none !important;
	}
	
	#footer-thankyou{
	display:none;
	}
#select2-order_status-result-3jf5-wc-awaiting-payment{
	display:none !important;
	}
	
	</style>';
}
///show build to order test in product page
add_action( 'woocommerce_before_add_to_cart_button', 'sachin_before_add_to_cart_btn' );
function sachin_before_add_to_cart_btn() {
        global $product;
        if($product->is_type('bundle')){
            $post_id = $product->get_id();
            $terms = get_the_terms( $post_id, 'product_cat' );

            $cate_ides =[];
            if($terms){
                foreach($terms as $category){
                  $cate_ides[] = $category->term_id;
                }   
            }
            if ((in_array(293, $cate_ides)) || (in_array(287, $cate_ides)) || (in_array(286, $cate_ides)) || (in_array(289, $cate_ides))){
                $available_on_backorder_text = "Built to order";

            }else{
                $available_on_backorder_text =  "Available on backorder";
            }
        }else{
            $statuses = get_option( 'wc_custom_stock_statuses' );
            $status   = get_post_meta( $product->get_id(), '_stock_status', true );
            $color    = esc_attr( $statuses[ $status ]['id'] );
            $label    = esc_attr( $statuses[ $status ]['name'] );
            if ( $product->is_in_stock() ) {
                if($product->get_stock_quantity() > 9 ) {
                    $available_on_backorder_text = __( '10+ in stock Available!', 'wc-custom-stock-status' );
                } elseif($product->get_stock_quantity() > 4 ) {
                    $available_on_backorder_text = __( '5+ in stock Available!', 'wc-custom-stock-status' );
                } 
                elseif ($product->get_stock_quantity() > 0 ) {
                    $available_on_backorder_text= __( '1+ in stock Available!', 'wc-custom-stock-status' );
                }
                else {
                    $available_on_backorder_text = __( 'Available on backorder', 'wc-custom-stock-status' );
                } 
            } elseif ( ! $product->is_in_stock() ) {
                $available_on_backorder_text = 'Sold Out!';
                
            }
            if ( ! empty( $label ) ) {
                $available_on_backorder_text = '<span style="color:' . $color . '">' . $label . '</span>';
            }
        }
        echo "<p class='product-available-cls'>".$available_on_backorder_text."</p>";
}

//start code for remove meta item from orderline item mail and invoice by sachin	
function wc_display_item_meta( $item, $args = array() ) {
    $strings = array();
    $html    = '';
    $args    = wp_parse_args(
        $args,
        array(
            'before'       => '<ul class="wc-item-meta"><li>',
            'after'        => '</li></ul>',
            'separator'    => '</li><li>',
            'echo'         => false,
            'autop'        => false,
            'label_before' => '<strong class="wc-item-meta-label">',
            'label_after'  => ':</strong> ',
        )
    );

    foreach ( $item->get_formatted_meta_data() as $meta_id => $meta ) {
        if(($meta->display_key == 'change_bundle_order_line_item_') || ($meta->display_key == 'Warranty')) continue;
        //echo $meta->display_key;die;
        $value     = $args['autop'] ? wp_kses_post( $meta->display_value ) : wp_kses_post( make_clickable( trim( $meta->display_value ) ) );
        $strings[] = $args['label_before'] . wp_kses_post( $meta->display_key ) . $args['label_after'] . $value;
    }

    if ( $strings ) {
        $html = $args['before'] . implode( $args['separator'], $strings ) . $args['after'];
    }

    $html = apply_filters( 'woocommerce_display_item_meta', $html, $item, $args );

    if ( $args['echo'] ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $html;
    } else {
        return $html;
    }
}
//add filter by product tag
add_action('restrict_manage_posts', 'product_tags_sorting');
function product_tags_sorting() {
    global $typenow;

    $taxonomy  = 'product_tag';

    if ( $typenow == 'product' ) {


        $selected      = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
        $info_taxonomy = get_taxonomy($taxonomy);

        wp_dropdown_categories(array(
            'show_option_all' => __("Custom Builts"),
            'taxonomy'        => $taxonomy,
            'name'            => $taxonomy,
            'orderby'         => 'name',
            'selected'        => $selected,
            'show_count'      => true,
            'hide_empty'      => true,
        ));
    };
}

add_action('parse_query', 'product_tags_sorting_query');
function product_tags_sorting_query($query) {
    global $pagenow;

    $taxonomy  = 'product_tag';

    $q_vars    = &$query->query_vars;
    if ( $pagenow == 'edit.php' && isset($q_vars['post_type']) && $q_vars['post_type'] == 'product' && isset($q_vars[$taxonomy]) && is_numeric($q_vars[$taxonomy]) && $q_vars[$taxonomy] != 0 ) {
        $term = get_term_by('id', $q_vars[$taxonomy], $taxonomy);
        $q_vars[$taxonomy] = $term->slug;
    }
}

add_filter ( 'woocommerce_account_menu_items', 'forms', 40 );
function forms( $menu_links ){
 
	$menu_links = array_slice( $menu_links, 0, 6, true ) 
	+ array( 'forms' => 'Forms' )
	+ array_slice( $menu_links, 6, NULL, true );
 
	return $menu_links;
 
}



add_action( 'init', function() {
    add_rewrite_endpoint( 'forms', EP_ROOT | EP_PAGES );
    // Repeat above line for more items ...
} );

add_action( 'woocommerce_account_forms_endpoint', function() {
   echo "
   <h2>Forms</h2>
   <ul class='form-list'>
   <li><a href='https://www.edsys.com.au/login/returns/credit_terms.asp'>Credit Authority Request</a></li>
   <li><a href='https://www.edsys.com.au/login/returns/service_form_step1.asp'>PC Service Request</a></li>
   <li><a href='https://new.edsys.com.au/support-request/'>Support Request</a></li>
   <li><a href='https://www.edsys.com.au/login/returns/return_terms.asp'>Warranty Return Request</a></li>
   <li><a href='https://www.edsys.com.au/login/returns/credit_terms.asp'>Parts Warranty Return Form</a></li>
   </ul>";
});

function admvolley(){

            echo '<link rel="stylesheet" href="https://widget.meetvolley.com/static/css/widget.css"> <script type="text/javascript" data-widget="https://api.meetvolley.com/api/widgets/public/452446e6-b8ad-4b1e-b66a-63b5d9ac0958" src="https://widget.meetvolley.com/widget.js"></script>';
?>
<style>
.bundle_availability .stock {
    display: none;
}
</style>
<?php
    }

add_action ('admin_head','admvolley');
add_action ('wp_head','admvolley');
add_filter( 'woocommerce_email_recipient_customer_quote', 'your_email_recipient_filter_function2', 10, 2);
function your_email_recipient_filter_function2($recipient, $object) {

	if ( ! is_a( $object, 'WC_Order' ) ) return $recipient;

	$recipient = $recipient . ', admin@edsys.com.au';

	return $recipient;

}
add_filter( 'woocommerce_email_recipient_customer_quote-accepted', 'your_email_recipient_filter_function3', 10, 2);
function your_email_recipient_filter_function3($recipient, $object) {

	if ( ! is_a( $object, 'WC_Order' ) ) return $recipient;

	$recipient = $recipient . ', orders@edsys.com.au';

	return $recipient;

}


//Niyam codes to bring in a col 
function wc_new_order_column( $columns ) {
    $columns['my_column'] = 'Order ID';
    return $columns;
}
add_filter( 'manage_edit-shop_order_columns', 'wc_new_order_column', 20 );

//filling data
add_action( 'manage_shop_order_posts_custom_column', 'add_column_content' );
function add_column_content( $column ) {
    global $post;
    if ( 'my_column' === $column ) {
        $order    = wc_get_order( $post->ID );
		$transaction_id = $order->get_id();
		echo $transaction_id;
}
}


//Niyam codes to bring in a col 
function wc_method_column( $columns ) {
    $columns['method'] = 'Payment Method';
    return $columns;
}
add_filter( 'manage_edit-shop_order_columns', 'wc_method_column', 20 );

add_action( 'manage_shop_order_posts_custom_column', 'add_method_content' );
function add_method_content( $column ) {
    global $post;
    if ( 'method' === $column ) {
        $order    = wc_get_order( $post->ID );
		$method = $order->get_payment_method();
		echo $method;
}
}
add_action( 'woocommerce_before_checkout_form', 'custom_before_checkout_form' );
function custom_before_checkout_form(){
    WC()->session->set('chosen_payment_method', 'payment_method_b2bking-invoice-gateway');
}
//start code Adding a custom new column PO number  to admin orders list by sachin
add_filter( 'manage_edit-shop_order_columns', 'custom_po_number_column_eldest_players', 20 );
function custom_po_number_column_eldest_players($columns)
{
    $reordered_columns = array();

    // Inserting columns to a specific location
    foreach( $columns as $key => $column){
        $reordered_columns[$key] = $column;
        if( $key ==  'user_login' ){
            // Inserting after "Status" column
            $reordered_columns['order_po_number'] = __( 'PO number','theme_domain');
        }
    }
    return $reordered_columns;
}
add_action( 'manage_shop_order_posts_custom_column' , 'custom_orders_list_po_number_column_content', 20, 2 );
function custom_orders_list_po_number_column_content( $column, $post_id )
{
    if ( 'order_po_number' != $column ) return;
        $purchase_number = get_field( "purchase_number", $post->ID );
        echo $purchase_number;
}

add_action( 'woocommerce_after_customer_login_form', 'action_login', 10, 0 ); 
function action_login(){
	echo '<div class="centre"><img src="https://new.edsys.com.au/wp-content/uploads/2021/11/XMAS_Inside_970x278.png" alt="postimg"></div>';
}

// start code for make order item value 0 functionality by sachin
add_action (
    'woocommerce_saved_order_items',
    function ($order_id, $items): void
    {
        $order = wc_get_order( $order_id );
        foreach ( $order->get_items() as $item_id => $item ) {
            $change_bundle_order = wc_get_order_item_meta( $item_id, 'change_bundle_order_line_item_' );
            if($change_bundle_order == 'yes'){
                //die("asas");
                $item->set_subtotal(0); 
                $item->set_total(0);
                $item->save();
            }
        }//die;
        $newTotal = $order->calculate_totals();
        $order->set_total($newTotal);
        $order->save();
    },
    10,
    3
);
// end code for make order item value 0 functionality by sachin
function remove_footer_admin () {
    echo "Created by The Computing Australia Group";
} 
add_filter('admin_footer_text', 'remove_footer_admin');


//update po number at the time of checkout
function get_data() {
        $order_id = $_POST['id'];
        $order_refrrence = $_POST['ponumber'];
        update_field('purchase_number', $order_refrrence, $order_id);
	    $order = wc_get_order( $order_id );
	    $order->update_status('quote-accepted', 'order_note');
}

add_action( 'wp_ajax_nopriv_get_data', 'get_data' );
add_action( 'wp_ajax_get_data', 'get_data' );

add_action( 'wp_body_open', 'wpdoc_add_custom_body_open_code' );
function wpdoc_add_custom_body_open_code() {
    $current_user = wp_get_current_user();
	$current_user = $current_user -> user_login;
	if(strlen($current_user)=== 0){
		echo "<div class='status'><a href='/my-account/'><i class='fas fa-sign-in-alt'></i>Login</a></div>";
	}   
}
add_filter( 'the_title', 'woo_personalize_order_received_title', 10, 2 );
function woo_personalize_order_received_title( $title, $id ) {
    if ( is_order_received_page() && get_the_ID() === $id ) {
        global $wp;
        // Get the order. Line 9 to 17 are present in order_received() in includes/shortcodes/class-wc-shortcode-checkout.php file
        $order_id  = apply_filters( 'woocommerce_thankyou_order_id', absint( $wp->query_vars['order-received'] ) );
        $order_key = apply_filters( 'woocommerce_thankyou_order_key', empty( $_GET['key'] ) ? '' : wc_clean( $_GET['key'] ) );
        if ( $order_id > 0 ) {
            $order = wc_get_order( $order_id );
            if ( $order->get_order_key() != $order_key ) {
                $order = false;
            }
        }
        if ( isset ( $order ) ) {
            //$title = sprintf( "You are awesome, %s!", esc_html( $order->billing_first_name ) ); // use this for WooCommerce versions older then v2.7
        $paymentMethod = $order->get_payment_method();
        $order_status = $order->get_status();
        if(($paymentMethod == 'commweb_hosted_checkout')){
            $title = sprintf( "Payment received");
        }
        }
    }
    return $title;
}
add_action( 'admin_footer_text', 'enqueue_my_script' );

function enqueue_my_script() {
  if($current_page = admin_url( "post-new.php?post_type=".$_GET["post_type"] ) == admin_url( "post-new.php?post_type=product" )){
      ?>
      <script type='text/javascript'>
        jQuery(document).ready( function(){ 
            jQuery("._manage_stock_field label").click();
            jQuery('#_backorders option[value=no]').removeAttr('selected','selected');
            jQuery('#_backorders option[value=yes]').attr('selected','selected');
        }); 
      </script>
      <?php
   }
}



// System builds

function create_posttype() {
 
    register_post_type( 'System Builds',
    // CPT Options
        array(
            'labels' => array(
                'name' => __( 'System builds' ),
                'singular_name' => __( 'System builds' )
            ),
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'System builds'),
            'show_in_rest' => true,
 
        )
    );
    add_submenu_page(
        'edit.php?post_type=systembuilds',
        __( 'Post Import', 'postimport' ),
        __( 'Post Import', 'postimport' ),
        'manage_options',
        'post-import',
        'post_import_ref_page_callback'
    );
}
// Hooking up our function to theme setup
add_action( 'init', 'create_posttype' );

function post_import_ref_page_callback() { 
    ?>
    <div class="wrap">
        <h1><?php _e( 'Post Import', 'postimport' ); ?></h1>
        <p><?php _e( 'Post Import', 'postimport' ); ?></p>
    </div>
    <table width="600">
        <tr><td colspan="2">
           Complete Record saves as CSV in file: <b style='color:red;'><a href='https://edsys.com.au/wp-content/uploads/2022/02/Demo.csv' download='systembuilds.csv'>Demo File Download</a></b>
        </td></tr>
        <form action="" method="post" enctype="multipart/form-data">
            <tr>
                <td width="20%">Select file</td>
                <td width="80%"><input type="file" name="file" id="file" /></td>
            </tr>

            <tr>
                <td>Submit</td>
                <td><input type="submit" name="submit" /></td>
            </tr>
        </form>
    </table>
    <?php

    if(isset($_FILES["file"]) && ($_SERVER["REQUEST_METHOD"] == "POST")){

        $tmpName = $_FILES['file']['tmp_name'];

        $csv_data = array_map('str_getcsv', file($tmpName));

        array_walk($csv_data , function(&$x) use ($csv_data) {
          $x = array_combine($csv_data[0], $x);
        });

        /** 
        *
        * array_shift = remove first value of array 
        * in csv file header was the first value
        * 
        */
        array_shift($csv_data);

        // Print Result Data

        foreach ($csv_data as $key => $value) {
           $title = $value['serial_number'];
           $dealer_abbr = $value['dealer_abbr'];
           $dealer_name = $value['dealer_name'];
           $warranty_type = $value['warranty_type'];
           $product_abbr = $value['product_abbr'];
           $product_name = $value['product_name'];
           $hcf_invoice = $value['invoice'];
           $hcf_build_date = $value['build_date'];
            if($title){
                global $user_ID, $wpdb;
                $query = $wpdb->prepare(
                    'SELECT ID FROM ' . $wpdb->posts . '
                    WHERE post_title = %s
                    AND post_type = \'systembuilds\'',
                    $title
                );
                $wpdb->query( $query );

                if ( $wpdb->num_rows ) {
                    $post_id = $wpdb->get_var( $query );
                }else{
                    $new = array(
                        'post_type' => 'systembuilds',
                        'post_title' => $title,
                        'post_status' => 'publish'
                    );
                    $post_id = wp_insert_post( $new );
                }
                if( $post_id ){
                    if($hcf_invoice){
                        update_post_meta( $post_id, 'hcf_invoice', $hcf_invoice );
                    }
                    if($dealer_abbr){
                        update_post_meta( $post_id, 'dealer_abbr', $dealer_abbr );
                    }
                    if($dealer_name){
                        update_post_meta( $post_id, 'dealer_name', $dealer_name );
                    }
                    if($warranty_type){
                        update_post_meta( $post_id, 'warranty_type', $warranty_type );
                    }
                    if($product_abbr){
                        update_post_meta( $post_id, 'product_abbr', $product_abbr );
                    }
                    if($product_name){
                        update_post_meta( $post_id, 'product_name', $product_name );
                    }
                    if($hcf_build_date){
                        $date = $hcf_build_date ;
                        $tempArray = explode('/',$date);
                        $newDate = $tempArray[2].'-'.$tempArray[1].'-'.$tempArray[0] ;
                        update_post_meta( $post_id, 'hcf_build_date',$newDate );
                    }

                }
            }
        } 
        wp_redirect( admin_url( "edit.php?post_type=systembuilds" ) );  
    }
}

function hcf_register_meta_boxes() {
    add_meta_box( 'hcf-1', __( 'System builds', 'hcf' ), 'hcf_display_callback', 'systembuilds' );
}
add_action( 'add_meta_boxes', 'hcf_register_meta_boxes' );

/**
 * Meta box display callback.
 *
 * @param WP_Post $post Current post object.
 */
function hcf_display_callback( $post ) {
    ?>
    <p class="meta-options hcf_field">
        <label for="hcf_invoice">Invoice</label>
        <input id="hcf_invoice" type="text" name="hcf_invoice" value="<?php echo esc_attr( get_post_meta( get_the_ID(), 'hcf_invoice', true ) ); ?>">
    </p>
    <p class="meta-options hcf_field">
        <label for="dealer_abbr">Dealer Acronym</label>
        <input id="dealer_abbr" type="text" name="dealer_abbr" value="<?php echo esc_attr( get_post_meta( get_the_ID(), 'dealer_abbr', true ) ); ?>">
    </p>
    <p class="meta-options hcf_field">
        <label for="dealer_name">Dealer</label>
        <input id="dealer_name" type="text" name="dealer_name" value="<?php echo esc_attr( get_post_meta( get_the_ID(), 'dealer_name', true ) ); ?>">
    </p>
    <p class="meta-options hcf_field">
        <label for="warranty_type">Warranty Type</label>
        <input id="warranty_type" type="text" name="warranty_type" value="<?php echo esc_attr( get_post_meta( get_the_ID(), 'warranty_type', true ) ); ?>">
    </p>
    <p class="meta-options hcf_field">
        <label for="product_abbr">Product Acronym</label>
        <input id="product_abbr" type="text" name="product_abbr" value="<?php echo esc_attr( get_post_meta( get_the_ID(), 'product_abbr', true ) ); ?>">
    </p>
    <p class="meta-options hcf_field">
        <label for="product_name">Product Name</label>
        <input id="product_name" type="text" name="product_name" value="<?php echo esc_attr( get_post_meta( get_the_ID(), 'product_name', true ) ); ?>">
    </p>

    <p class="meta-options hcf_field">
        <label for="hcf_build_date">Build Date</label>
        <input id="hcf_build_date" type="date" name="hcf_build_date" value="<?php echo esc_attr( get_post_meta( get_the_ID(), 'hcf_build_date', true ) ); ?>">
    </p>
    <?php
}

function hcf_save_meta_box( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( $parent_id = wp_is_post_revision( $post_id ) ) {
        $post_id = $parent_id;
    }
    $fields = [
        'hcf_invoice',
        'dealer_abbr',
        'dealer_name',
        'warranty_type',
        'product_abbr',
        'product_name',
        'hcf_build_date',
    ];
    foreach ( $fields as $field ) {
        if ( array_key_exists( $field, $_POST ) ) {
            update_post_meta( $post_id, $field, sanitize_text_field( $_POST[$field] ) );
        }
     }
}
add_action( 'save_post', 'hcf_save_meta_box' );

// Change the columns for the releases list screen
function change_columns( $cols ) {
  $cols = array(
  'cb'         => '<input type="checkbox" />',
  'title'      => 'Title',
  'hcf_invoice'     => 'Invoice',
  'hcf_build_date'      => 'Build Date',
  'dealer_abbr'      => 'Dealer Abbr',
  'dealer_name'      => 'Dealer Name',
  'warranty_type'      => 'Warranty Type',
  'product_abbr'      => 'Product Abbr',
  'product_name'      => 'Product Name'
  );
  return $cols;
}
function custom_columns( $column ) {
    global $post;
    if( $column == 'hcf_invoice' ) {
        $hcf_invoice = get_field('hcf_invoice');
        if( $hcf_invoice ) {
            echo $hcf_invoice;
        } else {
            echo '-';
        }
    }
    if( $column == 'hcf_build_date' ) {
        $hcf_build_date = get_field('hcf_build_date');
        if( $hcf_build_date ) {
            echo $hcf_build_date;
        } else {
            echo '-';
        }
    }
	if( $column == 'dealer_abbr' ) {
        $dealer_abbr = get_field('dealer_abbr');
        if( $dealer_abbr ) {
            echo $dealer_abbr;
        } else {
            echo '-';
        }
    }
	if( $column == 'dealer_name' ) {
        $dealer_name = get_field('dealer_name');
        if( $dealer_name ) {
            echo $dealer_name;
        } else {
            echo '-';
        }
    }
	if( $column == 'warranty_type' ) {
        $warranty_type = get_field('warranty_type');
        if( $warranty_type ) {
            echo $warranty_type;
        } else {
            echo '-';
        }
    }
	if( $column == 'product_abbr' ) {
        $product_abbr = get_field('product_abbr');
        if( $product_abbr ) {
            echo $product_abbr;
        } else {
            echo '-';
        }
    }
	if( $column == 'product_name' ) {
        $product_name = get_field('product_name');
        if( $product_name ) {
            echo $product_name;
        } else {
            echo '-';
        }
    }
}
add_action( "manage_systembuilds_posts_custom_column", "custom_columns", 10, 2 );
add_filter( "manage_systembuilds_posts_columns", "change_columns" );

function your_function() {
    global $wp;
    $current_slug = add_query_arg( array(), $wp->request );
    if($current_slug == 'pc-service-request'){
        $systembuilds = $_GET['serial_number'];
        $args = array(
          'name'        => $systembuilds,
          'post_type'   => 'systembuilds',
          'post_status' => 'publish',
          'numberposts' => 1
        );
        $my_posts = get_posts($args);
        foreach ($my_posts as $key => $my_post) {
            $post_id = $my_post->ID;
            $post_title = $my_post->post_title;
            $post_content = $my_post->post_content;
            $post_invoice = get_post_meta($post_id, 'hcf_invoice', true);
            $post_build_date = get_post_meta($post_id, 'hcf_build_date', true);
			$dealer_name = get_post_meta($post_id, 'dealer_name', true);
			$dealer_abbr = get_post_meta($post_id, 'dealer_abbr', true);
			$warranty_type = get_post_meta($post_id, 'warranty_type', true);
			$product_abbr = get_post_meta($post_id, 'product_abbr', true);
			$product_name = get_post_meta($post_id, 'product_name', true);
        }
        ?>
            <script type='text/javascript'>
                jQuery(document).ready( function(){ 
                    var serial_number = '<?php echo $_GET['serial_number'];?>';
					if(serial_number == ""){
						jQuery("#gform_10").css("display", "none");
						jQuery("#search_serial").css("display", "block");
						
					}else{
						jQuery(".build_date_inpt_text").css("display", "none");
						jQuery(".dealer_abbr_inpt_text").css("display", "none");
						jQuery(".dealer_name_inpt_text").css("display", "none");
						jQuery(".warranty_type_inpt_text").css("display", "none");
						jQuery(".product_abbr_inpt_text").css("display", "none");
						jQuery(".product_name_inpt_text").css("display", "none");
						jQuery(".invoice_inpt_text").css("display", "none");
						jQuery("#gform_10").css("display", "block");
						jQuery("#search_serial").css("display", "none");
						
						var serial_number_inpt = '<?php echo $post_title;?>';
						var build_date_inpt = '<?php echo $post_build_date;?>';
						var pc_code_inpt = '<?php echo $post_content;?>';
						var invoice_inpt = '<?php echo $post_invoice;?>';
						var dealer_abbr_inpt = '<?php echo $dealer_abbr;?>';
						var dealer_name_inpt = '<?php echo $dealer_name;?>';
						var warranty_type_inpt = '<?php echo $warranty_type;?>';
						var product_abbr_inpt = '<?php echo $product_abbr;?>';
						var product_name_inpt = '<?php echo $product_name;?>';
						jQuery(".pc_serial input").val(serial_number);
						jQuery(".invoice_inpt_text input").val(invoice_inpt);
						jQuery(".build_date_inpt_text input").val(build_date_inpt);
						jQuery(".dealer_abbr_inpt_text input").val(dealer_abbr_inpt);
						jQuery(".dealer_name_inpt_text input").val(dealer_name_inpt);
						jQuery(".warranty_type_inpt_text input").val(warranty_type_inpt);
						jQuery(".product_abbr_inpt_text input").val(product_abbr_inpt);
						jQuery(".product_name_inpt_text input").val(product_name_inpt);
						jQuery(".serial_number_inpt").text(serial_number_inpt);
						jQuery(".build_date_inpt").text(build_date_inpt);
						jQuery(".invoice_inpt").text(invoice_inpt);
						jQuery(".dealer_abbr_inpt").text(dealer_abbr_inpt);
						jQuery(".dealer_name_inpt").text(dealer_name_inpt);
						jQuery(".warranty_type_inpt").text(warranty_type_inpt);
						jQuery(".product_abbr_inpt").text(product_abbr_inpt);
						jQuery(".product_name_inpt").text(product_name_inpt);
						
					}
                }); 
            </script>
        <?php
    }
	?>
		<style type="text/css">
			div#wcmca_custom_addresses {
				display: none !important;
			}
			.u-column1.col-1.woocommerce-Address .edit {
				display: none;
			}
		</style>
	<?php
}
add_action( 'wp_footer', 'your_function', 100 );


add_action('all_admin_notices', 'product_search');
function product_search() {
	$screen = get_current_screen();
	if($screen->id=='product') {
		$homeURL = get_home_url();
		$actions = '&post_status=all&post_type=product';
		echo '
		<div class="form-wrap">
		<form action="'.$homeURL.'/wp-admin/edit.php" method="get">
			  <input type="text" placeholder="Search a product"  name="s">
			  <input type="hidden" name="post_type" value="product">
			  <button type="submit">Submit</button>
		</form>
		</div>
			<style>
	.form-wrap{text-align:right;margin-right:40px;}
	.form-wrap input{border-color:#AA035C;}
	</style>
		';
	}
}



//user search
add_action('in_admin_header', 'user_search');
function user_search() {
	$screen = get_current_screen();
	if($screen->id=='user-edit' or $screen->id=='user') {
		$homeURL = get_home_url();
		echo '
		<div class="form-wrap"><form action="'.$homeURL.'/wp-admin/users.php" method="get">
      <input type="text" placeholder="Find a user" name="s">
      <button type="submit">Submit</button>
    </form>
	</div>
	<style>
	.form-wrap{text-align:right;margin-right:40px;}
	.form-wrap input{border-color:#AA035C;}
	</style>
	';
		
	}
}

///add billing and shipping address field for new user
function custom_user_profile_fields($user){
    global $woocommerce;
    $countries_obj   = new WC_Countries();
    $countries   = $countries_obj->__get('countries');
    $default_country = $countries_obj->get_base_country();
    $default_county_states = $countries_obj->get_states( $default_country );
    ?>
        <h3>Customer billing address</h3>
        <table class="form-table">
            <tr>
                <th><label for="billing_first_name">
            First name</label></th>
                <td>
                    <input type="text" class="regular-text" name="billing_first_name" id="billing_first_name" /><br />
                </td>
            </tr>
            <tr>
                <th><label for="billing_last_name">
            Last name</label></th>
                <td>
                    <input type="text" class="regular-text" name="billing_last_name" id="billing_last_name" /><br />
                </td>
            </tr>
            <tr>
            <tr>
                <th><label for="billing_company">
            Company</label></th>
                <td>
                    <input type="text" class="regular-text" name="billing_company" id="billing_company" /><br />
                </td>
            </tr>
            <tr>
                <th><label for="billing_address_1">
            Address line 1</label></th>
                <td>
                    <input type="text" class="regular-text" name="billing_address_1" id="billing_address_1" /><br />
                </td>
            </tr>
            <tr>
                <th><label for="billing_address_2">
            Address line 2</label></th>
                <td>
                    <input type="text" class="regular-text" name="billing_address_2" id="billing_address_2" /><br />
                </td>
            </tr>
            <tr>
                <th><label for="billing_city">
            City</label></th>
                <td>
                    <input type="text" class="regular-text" name="billing_city" id="billing_city" /><br />
                </td>
            </tr>
            <tr>
                <th><label for="billing_postcode">
            Postcode / ZIP</label></th>
                <td>
                    <input type="text" class="regular-text" name="billing_postcode" id="billing_postcode" /><br />
                </td>
            </tr>
            <tr>
                <th><label for="billing_state">
            State / County</label></th>
                <td>
                    <?php 
                    woocommerce_form_field('billing_state', array(
                        'type'       => 'select',
                        'class'      => array( 'billing_state' ),
                        'placeholder'    => __('Select a State'),
                        'options'    => $default_county_states
                         )
                    );
                    ?>

                </td>
            </tr>
            <tr>
                <th><label for="billing_phone">
            Phone</label></th>
                <td>
                    <input type="text" class="regular-text" name="billing_phone" id="billing_phone" /><br />
                </td>
            </tr>
        </table>
        <h3>Customer shipping address</h3>
        <table class="form-table">
            <tr>
                <th>
                    <label for="copy_billing">
                    Copy from billing address
                    </label>
                </th>
                <td>
                    <button type="button" id="copy_billing" class="button js_copy-billing">Copy</button>
                    <p class="description"></p>
                </td>
            </tr>
            <tr>
                <th><label for="shipping_first_name">
            First name</label></th>
                <td>
                    <input type="text" class="regular-text" name="shipping_first_name" id="shipping_first_name" /><br />
                </td>
            </tr>
            <tr>
                <th><label for="shipping_last_name">
            Last name</label></th>
                <td>
                    <input type="text" class="regular-text" name="shipping_last_name" id="shipping_last_name" /><br />
                </td>
            </tr>
            <tr>
                <th><label for="shipping_company">
            Company</label></th>
                <td>
                    <input type="text" class="regular-text" name="shipping_company" id="shipping_company" /><br />
                </td>
            </tr>
            <tr>
                <th><label for="shipping_address_1">
            Address line 1</label></th>
                <td>
                    <input type="text" class="regular-text" name="shipping_address_1" id="shipping_address_1" /><br />
                </td>
            </tr>
            <tr>
                <th><label for="shipping_address_2">
            Address line 2</label></th>
                <td>
                    <input type="text" class="regular-text" name="shipping_address_2" id="shipping_address_2" /><br />
                </td>
            </tr>
            <tr>
                <th><label for="shipping_city">
            City</label></th>
                <td>
                    <input type="text" class="regular-text" name="shipping_city" id="shipping_city" /><br />
                </td>
            </tr>
            <tr>
                <th><label for="shipping_postcode">
            Postcode / ZIP</label></th>
                <td>
                    <input type="text" class="regular-text" name="shipping_postcode" id="shipping_postcode" /><br />
                </td>
            </tr>
            <tr>
                <th><label for="shipping_state">
            State / County</label></th>
                <td>
                    <?php 
                    woocommerce_form_field('shipping_state', array(
                        'type'       => 'select',
                        'class'      => array( 'shipping_state' ),
                        'placeholder'    => __('Select a State'),
                        'options'    => $default_county_states
                         )
                    );
                    ?>

                </td>
            </tr>
        </table>

        <script type="text/javascript">
            jQuery(document).on('click', '#copy_billing', function() {
                jQuery("[name='shipping_first_name']").val(jQuery("[name='billing_first_name']").val());
                jQuery("[name='shipping_last_name']").val(jQuery("[name='billing_last_name']").val());
                jQuery("[name='shipping_company']").val(jQuery("[name='billing_company']").val());
                jQuery("[name='shipping_address_1']").val(jQuery("[name='billing_address_1']").val());
                jQuery("[name='shipping_address_2']").val(jQuery("[name='billing_address_2']").val());
                jQuery("[name='shipping_city']").val(jQuery("[name='billing_city']").val());
                jQuery("[name='shipping_state']").val(jQuery("[name='billing_state']").val());
                jQuery("[name='shipping_postcode']").val(jQuery("[name='billing_postcode']").val());
                jQuery("[name='shipping_country']").val(jQuery("[name='billing_country']").val());
            });
        </script>
    <?php
}
add_action( "user_new_form", "custom_user_profile_fields" );


function save_custom_user_profile_fields($user_id){
    # again do this only if you can
    if(!current_user_can('manage_options'))
        return false;

    # save my custom field
    update_user_meta($user_id, 'billing_first_name', $_POST['billing_first_name']);
    update_user_meta($user_id, 'billing_last_name', $_POST['billing_last_name']);
    update_user_meta($user_id, 'billing_company', $_POST['billing_company']);
    update_user_meta($user_id, 'billing_address_1', $_POST['billing_address_1']);
    update_user_meta($user_id, 'billing_address_2', $_POST['billing_address_2']);
    update_user_meta($user_id, 'billing_city', $_POST['billing_city']);
    update_user_meta($user_id, 'billing_postcode', $_POST['billing_postcode']);
    update_user_meta($user_id, 'billing_country', 'AU');
    update_user_meta($user_id, 'billing_state', $_POST['billing_state']);
    update_user_meta($user_id, 'billing_phone', $_POST['billing_phone']);
    //shipping Address
    update_user_meta($user_id, 'shipping_first_name', $_POST['shipping_first_name']);
    update_user_meta($user_id, 'shipping_last_name', $_POST['shipping_last_name']);
    update_user_meta($user_id, 'shipping_company', $_POST['shipping_company']);
    update_user_meta($user_id, 'shipping_address_1', $_POST['shipping_address_1']);
    update_user_meta($user_id, 'shipping_address_2', $_POST['shipping_address_2']);
    update_user_meta($user_id, 'shipping_city', $_POST['shipping_city']);
    update_user_meta($user_id, 'shipping_postcode', $_POST['shipping_postcode']);
    update_user_meta($user_id, 'shipping_country', 'AU');
    update_user_meta($user_id, 'shipping_state', $_POST['shipping_state']);
}
add_action('user_register', 'save_custom_user_profile_fields');
add_filter( 'woocommerce_my_account_my_orders_query', 'custom_my_account_orders', 10, 1 );
function custom_my_account_orders( $args ) {
    $args['posts_per_page'] = 20;
    return $args;
}

//number of products per page

add_filter( 'loop_shop_per_page', 'bbloomer_redefine_products_per_page', 9999 );
 
function bbloomer_redefine_products_per_page( $per_page ) {
  $per_page = 20;
  return $per_page;
}
// //start code for remove update plugin notification by sachin
function remove_update_notifications( $value ) {
    if ( isset( $value ) && is_object( $value ) ) {
        unset( $value->response[ 'wt-woocommerce-sequential-order-numbers/wt-advanced-order-number.php' ] );
        unset( $value->response[ 'webappick-pdf-invoice-for-woocommerce/woo-invoice.php' ] );
        unset( $value->response[ 'woocommerce-xero/woocommerce-xero.php' ] );
        unset( $value->response[ 'australia-post-woocommerce-shipping/australia-post-woocommerce-shipping.php' ] );
        unset( $value->response[ 'woocommerce-warranty/woocommerce-warranty.php' ] );
        unset( $value->response[ 'wc-custom-stock-status-master/wc-custom-stock-status.php' ] );
        unset( $value->response[ 'wk-woocommerce-purchase-order/functions.php' ] );
        unset( $value->response[ 'woocommerce-pdf-invoices-packing-slips/woocommerce-pdf-invoices-packingslips.php' ] );
        unset( $value->response[ 'woocommerce-product-bundles/woocommerce-product-bundles.php' ] );
        //unset( $value->response[ 'woocommerce/woocommerce.php' ] );
    }
    return $value;
}
add_filter( 'site_transient_update_plugins', 'remove_update_notifications' );
//end code for remove update plugin notification by sachin
//
add_action('admin_head', 'paymentFlag');

function paymentFlag() {
  echo '<style>
    .fa-credit-card{
	color: #ff9800 !important;
    padding-left: 4px;
	}
}
  </style>';
}
add_filter('woocommerce_billing_fields', 'my_woocommerce_billing_fields');
function my_woocommerce_billing_fields($fields)
{
   $fields['billing_company']['custom_attributes'] = array('readonly'=>'readonly');
   $fields['shipping_company']['custom_attributes'] = array('readonly'=>'readonly');
        
   return $fields;
}
//start code for return account order page column by sachin
function new_orders_columns( $columns = array() ) {

    // Hide the columns
    if( isset($columns['order-total']) ) {
        // Unsets the columns which you want to hide
        unset( $columns['order-number'] );
        unset( $columns['order-date'] );
        unset( $columns['order-status'] );
        unset( $columns['order-total'] );
        unset( $columns['order-actions'] );
    }

    // Add new columns
    $columns = array(
            'purchase_number'  => __( 'PO Number', 'woocommerce' ),
            'order-date'    => __( 'Date', 'woocommerce' ),
            'order-status'  => __( 'Status', 'woocommerce' ),
            'invoice_number'  => __( 'Invoice Number', 'woocommerce' ),
            'order-total'   => __( 'Total', 'woocommerce' ),
            'order-actions' => __( 'Actions', 'woocommerce' ),

        );

    return $columns;
}
add_filter( 'woocommerce_account_orders_columns', 'new_orders_columns' );

//End code for return account order page column by sachin
//start code for change payment status paid if method is cod and status is completed  by sachin
add_action( 'woocommerce_order_status_changed', 'action_function_name_4633', 10, 4 );
function action_function_name_4633( $id, $status_transition_from, $status_transition_to, $that ){
    $order = wc_get_order( $id );
    $payment_method = $order->get_payment_method();
    if(('cod' == $payment_method) && ($status_transition_to == "completed")){
      update_field( 'visible_payment_status', 'paid', $id );
        //update_post_meta( $id, 'visible_payment_status', 'paid' );
    }
}
//End code for change payment status paid if method is cod and status is completed by sachin
//Start code for remove payment action from order edite page by sachin
add_filter( 'woocommerce_order_actions', 'woocommerce_remove_order_actions' );
function woocommerce_remove_order_actions( $order_action ) {
    unset($order_action['xero_manual_payment']);
    return $order_action;
}
//End code for remove payment action from order edite page by sachin
//start code override code for user dropdown on order edit page by sachin
function kia_display_order_data_in_admin( $order ){  ?>
    <style type="text/css">
        #wcmca_edit_order_item_container{
           display: none !important;
        }
    </style>
        <?php
        $user_string = '';
        $user_id     = '';
        if ( $order->get_user_id() ) {
            $user_id = absint( $order->get_user_id() );
            $user    = get_user_by( 'id', $user_id );
            /* translators: 1: user display name 2: user ID 3: user email */
            $user_string = sprintf(
                esc_html__( '%1$s', 'woocommerce' ),
                $user->user_login
            );
            echo '<p class="form-field form-field-wide override_function"><strong style="display: block;">'.__('Customer Name').':</strong> <a href="user-edit.php?user_id=' . $user_id . '">' . $user_string. '</a></p>';
        }
}
add_action( 'woocommerce_admin_order_data_after_order_details', 'kia_display_order_data_in_admin' );
//end code override code for user dropdown on order edit page by sachin
//start code remove item meta and warranty form order edit admin screen by sachin
add_filter('woocommerce_hidden_order_itemmeta','hidden_order_itemmeta', 50);

function hidden_order_itemmeta($args) {
  $args[] = 'Items';
  $args[] = 'Warranty';
  return $args;
}
//end code remove item meta and warranty form order edit admin screen by sachin
//Start code override for return only customer user name when we search user in order screen by sachin
add_filter('woocommerce_json_search_found_customers','my_found_customers') ;
function my_found_customers($found_customers) {
  if(!empty($found_customers)) :
    foreach($found_customers as $id => $found_customer) :
            $customer = new WC_Customer( $id );
            /* translators: 1: user display name 2: user ID 3: user email */
            $found_customers[ $id ] = sprintf(
                esc_html__( '%1$s', 'woocommerce' ),
                $customer->get_username(),
            );

    endforeach;
  endif;
  return $found_customers ; 
}

include_once("custom-scripts/script.php");