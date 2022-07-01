<?php
/**
 * Order details table shown in emails.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/email-order-details.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 3.7.0
 */

defined( 'ABSPATH' ) || exit;

$text_align = is_rtl() ? 'right' : 'left';

// do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>

<h2>
	<?php
	if ( $sent_to_admin ) {
		$before = '<a class="link" href="' . esc_url( $order->get_edit_order_url() ) . '">';
		$after  = '</a>';
	} else {
		$before = '';
		$after  = '';
	}
	/* translators: %s: Order ID. */
	$order_id = $order->get_id();
	$purchase_number = get_post_meta($order_id, 'purchase_number', true);
	$order_status  = $order->get_status();
	$order_number = $order->get_order_number();
	$qo_number = str_replace("SO", "", $order_number);
	if($order_status == "quote"){
		?>
		<p><?php 
			$user_info = get_userdata($order->user_id);
			$user_name = $user_info->user_login;
			printf( esc_html__( 'Hi %s,', 'woocommerce' ), esc_html( $user_name ) ); ?></p>
		<p><?php esc_html_e( 'You have received a new Quotation from EDsys Computers.', 'woocommerce' ); ?></p>
		<p><?php esc_html_e( 'Please visit your account to view this quote.', 'woocommerce' ); ?></p>
		<?php	
		echo wp_kses_post( $before . sprintf( __( '[QO Number #%s]', 'woocommerce' ) . $after . ' (<time datetime="%s">%s</time>)',$qo_number , $order->get_date_created()->format( 'c' ), wc_format_datetime( $order->get_date_created() ) ) );
	}elseif($order_status == "quote-accepted"){
		?>
		<p><?php 
			$user_info = get_userdata($order->user_id);
			$user_name = $user_info->user_login;
			printf( esc_html__( 'Hi %s,', 'woocommerce' ), esc_html( $user_name ) ); ?></p>
		<p><?php esc_html_e( 'Thank you for accepting the Quote.', 'woocommerce' ); ?></p>
		<p><?php esc_html_e( 'Please keep this number as a reference to your order.', 'woocommerce' ); ?></p>
		<?php	
		echo wp_kses_post( $before . sprintf( __( '[PO Number #%s]', 'woocommerce' ) . $after . ' (<time datetime="%s">%s</time>)',$purchase_number , $order->get_date_created()->format( 'c' ), wc_format_datetime( $order->get_date_created() ) ) );
	}else{
		echo wp_kses_post( $before . sprintf( __( '[PO Number #%s]', 'woocommerce' ) . $after . ' (<time datetime="%s">%s</time>)',$purchase_number , $order->get_date_created()->format( 'c' ), wc_format_datetime( $order->get_date_created() ) ) );	
	}
	
	?>
</h2>

<div style="margin-bottom: 40px;">
	<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
		<thead>
			<tr>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Quantity', 'woocommerce' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Price', 'woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			echo wc_get_email_order_items( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$order,
				array(
					'show_sku'      => $sent_to_admin,
					'show_image'    => false,
					'image_size'    => array( 32, 32 ),
					'plain_text'    => $plain_text,
					'sent_to_admin' => $sent_to_admin,
				)
			);
			?>
		</tbody>
		<tfoot>
			<?php
			$item_totals = $order->get_order_item_totals();

			if ( $item_totals ) {
				$i = 0;
				foreach ( $item_totals as $total ) {
					$i++;
					?>
					<tr>
						<th class="td" scope="row" colspan="2" style="text-align:<?php echo esc_attr( $text_align ); ?>; <?php echo ( 1 === $i ) ? 'border-top-width: 4px;' : ''; ?>"><?php echo wp_kses_post( $total['label'] ); ?></th>
						<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; <?php echo ( 1 === $i ) ? 'border-top-width: 4px;' : ''; ?>"><?php echo wp_kses_post( $total['value'] ); ?></td>
					</tr>
					<?php
				}
			}
			if ( $order->get_customer_note() ) {
				?>
				<tr>
					<th class="td" scope="row" colspan="2" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Note:', 'woocommerce' ); ?></th>
					<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php echo wp_kses_post( nl2br( wptexturize( $order->get_customer_note() ) ) ); ?></td>
				</tr>
				<?php
			}
			?>
		</tfoot>
	</table>
</div>

<?php do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>
