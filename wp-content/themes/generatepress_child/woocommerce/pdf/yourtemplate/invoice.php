<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<?php do_action( 'wpo_wcpdf_before_document', $this->type, $this->order ); 
$order_id = $this->order->get_id();
$purchase_number = get_field( "purchase_number", $order_id );
$user_id = $order->get_user_id();
$user_abn = get_user_meta( $user_id, 'user_abn', true );
$user_abn =  preg_replace('~.*[^\d]{0,7}(\d{2})(\d{3})[^\d]{0,7}(\d{3})[^\d]{0,7}(\d{3}).*~', '$1 $2 $3 $4', $user_abn). "\n";
$user_info = get_userdata($user_id);
$user_name = $user_info->user_login; 
?>


<table class="head container">
<style>
	.invoice{
		margin-left:-50px;
		margin-right:-50px;
	}
	.quantity, .price{
		width:20%;
	}
	.product{
		width:60%;
	}
	.skuu{
		width:20%;
	}
	table.head{
	margin-bottom:0px !important;
	}
</style>	
	<tr>
		<td class="header">
		<?php
		if( $this->has_header_logo() ) {
			$this->header_logo();
		} else {
			echo $this->get_title();
		}
		?>
		</td>
		<td class="shop-info">
			<?php do_action( 'wpo_wcpdf_before_shop_name', $this->type, $this->order ); ?>
			<div class="shop-name"><h3><?php $this->shop_name(); ?></h3></div>
			<?php do_action( 'wpo_wcpdf_after_shop_name', $this->type, $this->order ); ?>
			<?php do_action( 'wpo_wcpdf_before_shop_address', $this->type, $this->order ); ?>
			<div class="shop-address"><?php $this->shop_address(); ?></div>
			<?php do_action( 'wpo_wcpdf_after_shop_address', $this->type, $this->order ); ?>
		</td>
	</tr>
</table>

<h1 class="document-type-label">
<?php if( $this->has_header_logo() ) echo "Tax Invoice"; ?> 
<!-- 	$this->get_title() -->
</h1>

<?php do_action( 'wpo_wcpdf_after_document_label', $this->type, $this->order ); ?>

<table class="order-data-addresses">
	<tr>
		<td class="address billing-address">
			<!-- <h3><?php _e( 'Billing Address:', 'woocommerce-pdf-invoices-packing-slips' ); ?></h3> -->
			<?php do_action( 'wpo_wcpdf_before_billing_address', $this->type, $this->order ); ?>
			<?php $this->billing_address(); ?>
			<?php do_action( 'wpo_wcpdf_after_billing_address', $this->type, $this->order ); ?>
			<?php if ( isset($this->settings['display_email']) ) { ?>
			<div class="billing-email"><?php $this->billing_email(); ?></div>
			<?php } ?>
			<?php if ( isset($this->settings['display_phone']) ) { ?>
			<div class="billing-phone"><?php $this->billing_phone(); ?></div>
			<?php } ?>
		</td>
		<td class="address shipping-address">
		<?php /*if ( !empty($this->settings['display_shipping_address']) && ( $this->ships_to_different_address() || $this->settings['display_shipping_address'] == 'always' ) ) { */?> 
			<h3><?php _e( 'Ship To:', 'woocommerce-pdf-invoices-packing-slips' ); ?></h3>
			<?php do_action( 'wpo_wcpdf_before_shipping_address', $this->type, $this->order ); ?>
			<?php $this->shipping_address(); ?>
			<?php do_action( 'wpo_wcpdf_after_shipping_address', $this->type, $this->order ); ?>
 			<?php/* } */?> 
		</td>
		<td class="order-data">
			<table>
				<tr class="account-code">
					<th><?php _e( 'Account Code:', 'woocommerce-pdf-invoices-packing-slips' ); ?></th>
					<td><?php echo $user_name; ?></td>
				</tr>
				<tr class="purchase-number">
					<th><?php _e( 'PO Number:', 'woocommerce-pdf-invoices-packing-slips' ); ?></th>
					<td><?php echo $purchase_number; ?></td>
				</tr>
				<tr class="use-abn-number">
					<th><?php _e( 'Customer ABN:', 'woocommerce-pdf-invoices-packing-slips' ); ?></th>
					<td><?php echo $user_abn; ?></td>
				</tr>
				<?php do_action( 'wpo_wcpdf_before_order_data', $this->type, $this->order ); ?>
				<?php if ( isset($this->settings['display_number']) ) { ?>
				<tr class="invoice-number">
					<th><?php echo $this->get_number_title(); ?></th>
					<td><?php $this->invoice_number(); ?></td>
				</tr>
				<?php } ?>
				<?php if ( isset($this->settings['display_date']) ) { ?>
				<tr class="invoice-date">
					<th><?php echo $this->get_date_title(); ?></th>
					<td><?php $this->invoice_date(); ?></td>
				</tr>
				<?php } ?>
<!-- 				<tr class="order-number">
					<th><?php _e( 'Order Number:', 'woocommerce-pdf-invoices-packing-slips' ); ?></th>
					<td><?php $this->order_number(); ?></td>
				</tr> -->
				<tr class="order-date">
					<th><?php _e( 'Order Date:', 'woocommerce-pdf-invoices-packing-slips' ); ?></th>
					<td><?php $this->order_date(); ?></td>
				</tr>
				<tr class="payment-method">
					<th><?php _e( 'Payment Method:', 'woocommerce-pdf-invoices-packing-slips' ); ?></th>
					<td><?php $this->payment_method(); ?></td>
				</tr>
				<?php do_action( 'wpo_wcpdf_after_order_data', $this->type, $this->order ); ?>
			</table>			
		</td>
	</tr>
</table>

<?php do_action( 'wpo_wcpdf_before_order_details', $this->type, $this->order ); ?>
<?php 
$discount_line =  wc_price( $order->get_total_discount(), array( 'currency' => $order->get_currency() ) );

$nocurrency_discount_line = preg_replace( '/[^.\d]/', '', $discount_line );

 
?>
<table class="order-details">
	<thead>
		<tr>
			<th class="skuu"><?php _e('Sku', 'woocommerce-pdf-invoices-packing-slips' ); ?></th>
			<th class="product"><?php _e('Product', 'woocommerce-pdf-invoices-packing-slips' ); ?></th>
			<th class="quantity" style="text-align:center;"><?php _e('QTY', 'woocommerce-pdf-invoices-packing-slips' ); ?></th>
			<th class="quantity" style="text-align:center;"><?php _e('Unit Price', 'woocommerce-pdf-invoices-packing-slips' ); ?></th>
			<?php if($nocurrency_discount_line != 360.00){?>
			<th class="quantity" style="text-align:center;"><?php _e('Extended Price', 'woocommerce-pdf-invoices-packing-slips' ); ?></th>
			<?php } ?>
			<th class="price"><?php _e('Price', 'woocommerce-pdf-invoices-packing-slips' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php $items = $this->get_order_items();
		
// 		echo "<pre>";
// 		print_r($items);die;
		
		if( sizeof( $items ) > 0 ) : foreach( $items as $item_id => $item ) :
		
		$dataItem = $item['item']->get_data();
		
		$discountPerLine = ($dataItem['subtotal'] - $dataItem['total'])/$item['quantity'] ;
		
		
	//echo "<pre>"; print_r($item); die;
		?>
		
		
		
		<?php if(($item['order_price'] != "") && ((wc_get_order_item_meta( $item_id, 'change_bundle_order_line_item_' ) == 'no') || wc_get_order_item_meta( $item_id, 'change_bundle_order_line_item_' ) == "")){ ?>
		<tr class="<?php echo apply_filters( 'wpo_wcpdf_item_row_class', 'item-'.$item_id, $this->type, $this->order, $item_id ); ?>">
			<td class="skoo"><?php if( !empty( $item['sku'] ) ) : ?><dt class="sku"><?php _e( 'SKU:', 'woocommerce-pdf-invoices-packing-slips' ); ?></dt><dd class="sku"><?php echo $item['sku']; ?></dd><?php endif; ?></td>
			<td class="product">
				<?php $description_label = __( 'Description', 'woocommerce-pdf-invoices-packing-slips' ); // registering alternate label translation ?>
				<span class="item-name"><?php echo $item['name']; ?></span>
				<?php do_action( 'wpo_wcpdf_before_item_meta', $this->type, $item, $this->order  ); ?>
				
	<span class="item-meta"><?php echo $item['meta']; ?></span>
				
				<style>.weight{
					display:none;
					}
				</style>
<!-- 	Commented by Niyam				<dl class="meta">
	<?php $description_label = __( 'SKU', 'woocommerce-pdf-invoices-packing-slips' ); // registering alternate label translation ?>
					<?php if( !empty( $item['sku'] ) ) : ?><dt class="sku"><?php _e( 'SKU:', 'woocommerce-pdf-invoices-packing-slips' ); ?></dt><dd class="sku"><?php echo $item['sku']; ?></dd><?php endif; ?>
					<?php if( !empty( $item['weight'] ) ) : ?><dt class="weight"><?php _e( 'Weight:', 'woocommerce-pdf-invoices-packing-slips' ); ?></dt><dd class="weight"><?php echo $item['weight']; ?><?php echo get_option('woocommerce_weight_unit'); ?></dd><?php endif; ?>
				</dl> -->
				
				<?php do_action( 'wpo_wcpdf_after_item_meta', $this->type, $item, $this->order  ); ?>
			</td>
			<td class="quantity" style="text-align:center;"><?php echo $item['quantity']; ?></td>
			<td class="quantity" style="text-align:center;"><?php echo $item['ex_single_price']; ?></td>
			<?php if($nocurrency_discount_line != 360.00){?>
			<td class="quantity" style="text-align:center;">
				<?php echo "$".number_format((float)$dataItem['total']/$item['quantity'], 2, '.', ''); //echo  $dataItem['subtotal'] - $discountPerLine ; ?>
			</td>
			<?php } ?>
			<td class="price"><?php echo $item['order_price']; ?></td>
		</tr>
		<?php } ?>
		<?php endforeach; endif;//die;?>
	</tbody>
	<tfoot>
		<tr class="no-borders">
			
			<td class="no-borders" style="padding-right:60px !important;" colspan="2">
				<div class="document-notes">
					<?php do_action( 'wpo_wcpdf_before_document_notes', $this->type, $this->order ); ?>
					<?php if ( $this->get_document_notes() ) : ?>
						<h3><?php _e( 'Notes', 'woocommerce-pdf-invoices-packing-slips' ); ?></h3>
						<?php $this->document_notes(); ?>
					<?php endif; ?>
					<?php do_action( 'wpo_wcpdf_after_document_notes', $this->type, $this->order ); ?>
				</div>
				<div class="customer-notes">
					<?php do_action( 'wpo_wcpdf_before_customer_notes', $this->type, $this->order ); ?>
					<?php if ( $this->get_shipping_notes() ) : ?>
						<h3><?php _e( 'Customer Notes', 'woocommerce-pdf-invoices-packing-slips' ); ?></h3>
						<?php $this->shipping_notes(); ?>
					<?php endif; ?>
					<?php do_action( 'wpo_wcpdf_after_customer_notes', $this->type, $this->order ); ?>
				</div>				
			</td>
			<td class="no-borders" colspan="4">
				<table class="totals">
					<tfoot>
						<?php foreach( $this->get_woocommerce_totals() as $key => $total ) : ?>
						<tr class="<?php echo $key; ?>">
							<th class="description"><?php echo $total['label']; ?></th>
							<td class="price"><span class="totals-price"><?php echo $total['value']; ?></span></td>
						</tr>
						<?php endforeach; ?>
					</tfoot>
				</table>
			</td>
		</tr>
	</tfoot>
</table>

<div class="bottom-spacer"></div>

<?php do_action( 'wpo_wcpdf_after_order_details', $this->type, $this->order ); ?>

<?php if ( $this->get_footer() ): ?>
<div id="footer">
	<!-- hook available: wpo_wcpdf_before_footer -->
	<?php $this->footer(); ?>
	<!-- hook available: wpo_wcpdf_after_footer -->
</div><!-- #letter-footer -->


<?php endif; ?>

<?php do_action( 'wpo_wcpdf_after_document', $this->type, $this->order ); ?>
