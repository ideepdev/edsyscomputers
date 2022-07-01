<?php
/**
 * Orders
 *
 * Shows orders on the account page.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/orders.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerceTemplates
 * @version 3.7.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_orders', $has_orders ); ?>

<?php if ( $has_orders ) : 

$customer_orders_total_query = get_posts( array(
	'numberposts' => - 1,
	'meta_key'    => '_customer_user',
	'meta_value'  => get_current_user_id(),
	'post_type'   => array( 'shop_order' ),
	'post_status' => array('wc-completed','wc-quote-on-hold','wc-quote-accepted','wc-awaiting-payment','wc-processing','wc-pending-shipping','wc-pending')
) );

$total = 0;
foreach ( $customer_orders_total_query as $customer_orders_total_value ) {
	$order_total_value = wc_get_order( $customer_orders_total_value );
	$total += $order_total_value->get_total();
}

$customer_orders_total_query1 = get_posts( array(
	'numberposts' => - 1,
	'meta_key'    => '_customer_user',
	'meta_value'  => get_current_user_id(),
	'post_type'   => array( 'shop_order' ),
	'post_status' => array('wc-awaiting-payment')
) );
 
$total1 = 0;
foreach ( $customer_orders_total_query1 as $customer_orders_total_value1 ) {
	$order_total_value1 = wc_get_order( $customer_orders_total_value1 );
	$total1 += $order_total_value1->get_total();
}

$searchText = "";
if( isset($_POST['search-orders']) ){
	$searchText  = stripslashes($_POST['search-id']);
	$search      = strtolower( $searchText );
	$temp_orders = [];
	$temp_o      = [];
	foreach ( $customer_orders->orders as $order ) {
		$items          = $order->get_items();
		$temp_sku       = [];
		$temp_name      = [];
		$partial_search = [];
		foreach ( $items as $item ) {
			$product_id   = $item->get_product_id();
			$product      = get_product( $product_id );
			$product_name = strtolower( $product->get_name() );
			$temp_sku[]   = strtolower( $product->get_sku() );
			if( str_contains( $product_name, $search ) ){
				$partial_search[] = $order->get_id();
			}
			$temp_name[] = strtolower( $product->get_name() );
		}

		if( 
			!in_array( $search, $temp_sku ) &&
			empty( $partial_search )
			// !in_array( $search, $temp_name )
		){
			continue;
		}

		$temp_orders[] = $order;
	}

	$customer_orders->orders = $temp_orders;
}

?>
	<form action="" method="post">
		<div class="input-group" style="float:left;width:59%;">
			<div class="form-outline">
				<input type="search" id="form1" class="form-control" placeholder="Enter product name or sku" name="search-id" value='<?php echo $searchText; ?>' style="width: 90%;">
				<button type="submit" class="btn" name="search-orders">
					<i class="fas fa-search" aria-hidden="true"></i>
				</button>
			</div>
		</div>
	</form>
	
	<!-- <p style="float: right; font-size: 25px;"> 
		<span style="font-weight:bold">Total Balance:</span> $ <?php //echo number_format((float)$total, 2, '.', '');?><br><span style="font-weight:bold">Open Invoice Balance:</span> $ <?php //echo number_format((float)$total1, 2, '.', '');?>
	</p> -->
	<p style="float: right; font-size: 25px;">
		<span style="font-weight:bold">Outstanding Invoice Balance:</span> $ <?php echo number_format((float)$total1, 2, '.', '');?>
	</p>
	<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">

		<thead>
			<tr>
				<?php foreach ( wc_get_account_orders_columns() as $column_id => $column_name ){ ?>
					<th class="woocommerce-orders-table__header woocommerce-orders-table__header-<?php echo esc_attr( $column_id ); ?>">
						<span class="nobr"><?php echo esc_html( $column_name ); ?></span>
					</th>
				<?php } ?>
			</tr>
		</thead>

		<tbody>
			<?php
			foreach ( $customer_orders->orders as $customer_order ) {
				$order      = wc_get_order( $customer_order ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$item_count = $order->get_item_count() - $order->get_item_count_refunded();
				$order_id = $order->get_id();
                $purchase_number = get_field( "purchase_number", $order_id );
                $order_status = $order->get_status();
				$order_number = $order->get_order_number();
				$qo_number = str_replace("SO", "QO", $order_number);
                if($order_status == 'quote'){
                    $purchase_number = $qo_number;
                }else{
                	$purchase_number = 'PO'.$purchase_number;
                }
	            $invoice = wcpdf_get_document( 'invoice', $order );
				if ( $invoice && $invoice->exists() ) {
					$invoice_number = $invoice->get_number();
				}else{
					$invoice_number = "";
				}
				?>
				<tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $order->get_status() ); ?> order">
					<?php foreach ( wc_get_account_orders_columns() as $column_id => $column_name ) : ?>
					
						<td class="<?php echo $column_id." ".$column_name."_".$order->get_status();?> woocommerce-orders-table__cell woocommerce-orders-table__cell-<?php echo esc_attr( $column_id ); ?>" data-title="<?php echo esc_attr( $column_name ); ?>">
							<?php if ( has_action( 'woocommerce_my_account_my_orders_column_' . $column_id ) ) : ?>
								<?php do_action( 'woocommerce_my_account_my_orders_column_' . $column_id, $order ); ?>

							<?php elseif ( 'purchase_number' === $column_id ) : ?>
								<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
									<?php echo esc_html( _x( '#', 'hash before order number', 'woocommerce' ) . $purchase_number ); ?>
								</a>
							
							<?php elseif ( 'order-date' === $column_id ) : ?>
								<time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></time>

							<?php elseif ( 'order-status' === $column_id ) : ?>
							<?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
							<?php elseif ( 'invoice_number' == $column_id ) : ?>
							<?php echo esc_html( _x( '', 'hash before order number', 'woocommerce' ) . $invoice_number ); ?>
							<?php elseif ( 'order-total' === $column_id ) : ?>
								<?php
								/* translators: 1: formatted order total 2: total order items */
								echo wp_kses_post( sprintf( _n( '%1$s for %2$s item', '%1$s for %2$s items', $item_count, 'woocommerce' ), $order->get_formatted_order_total(), $item_count ) );
								?>

							<?php elseif ( 'order-actions' === $column_id ) : ?>
								<?php
								$actions = wc_get_account_orders_actions( $order );

								if ( ! empty( $actions ) ) {
									foreach ( $actions as $key => $action ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
										echo '<a href="' . esc_url( $action['url'] ) . '" class="woocommerce-button button ' . sanitize_html_class( $key ) . '">' . esc_html( $action['name'] ) . '</a>';
									}
								}
								?>
							<?php
								if($order_status == 'quote'){
								  ?>
							        <div class="accept-po">
										<input type="text" name="po_number" placeholder="Enter PO Number" class="po_number_<?php echo $order_id;?> po_number" >
									<input class="acp-button" type="submit" value="Accept" onclick="test(<?php echo $order_id;?>)" >
							</div>	
								  <?php 
								}
								?>
							<?php endif; ?>
						</td>
					    
					<?php endforeach; ?>
				</tr>
				<?php
			}
			?>
		</tbody>
	</table>

	<?php do_action( 'woocommerce_before_account_orders_pagination' ); ?>

	<?php if ( 1 < $customer_orders->max_num_pages ) : ?>
		<div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination">
			<?php if ( 1 !== $current_page ) : ?>
				<a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'woocommerce' ); ?></a>
			<?php endif; ?>
			<?php if ( intval( $customer_orders->max_num_pages ) !== $current_page ) : ?>
				<a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'woocommerce' ); ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

<?php else : ?>
	<div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info woocommerce-info">
		<a class="woocommerce-Button button" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>"><?php esc_html_e( 'Browse products', 'woocommerce' ); ?></a>
		<?php esc_html_e( 'No order has been made yet.', 'woocommerce' ); ?>
	</div>
<?php endif; ?>
<style type="text/css">
	.Total_cancelled{
		text-decoration: line-through;
	}
	.woocommerce-button.button.prompt_mark_custom_status_quote-accepted {
		display: none;
	}
</style>
<script type="text/javascript">
	function test(par1){
        if((par1 !="")){
        	var po_number = jQuery( ".po_number_"+par1 ).val();
        	if (po_number) {
				jQuery.ajax({
				    type: "post",
				    dataType: "json",
				    url: "<?php echo home_url();?>/wp-admin/admin-ajax.php", //this is wordpress ajax file which is already avaiable in wordpress
				    data: {
				        action:'get_data', //this value is first parameter of add_action,
				        id: par1,
				        ponumber: po_number
				    },
				    success: function(msg){
				    	alert("PO Number is updated");
				        location.reload();
				    }
				});		
        	}else{
        		alert("PO Number is empty");
        	}

        }else{
        	alert("PO Number not updated");
        }
	}
</script>
<?php do_action( 'woocommerce_after_account_orders', $has_orders ); 