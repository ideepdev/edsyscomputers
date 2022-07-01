<?php
/**
 * Cross-sells
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cross-sells.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

defined( 'ABSPATH' ) || exit;

if ( $cross_sells ) : ?>

<style>	

.all-items {
		margin:30px 0 10px;
		display: flex;
		flex-wrap: wrap;		
		align-items: center;
		width: 100%;
	}

.woocommerce-paragraph p {
	margin: 0;
}

.image-content {
		width: 15%;
		padding: 10px;
}


.woocommerce-paragraph {
	width: 50%;
	padding: 0 10px;
}

.item-price {
	width: 15%;
	padding: 0 10px;
}

.item-btn {
	width: 20%;
	padding: 0 10px;
}


.image-content img {
	width: 80px;
}

.units {
	color: green;
}

.item-code {
	font-size: 12px;
}

.units p {
	margin: 0;
}


.item-price p {
	color:   #222222;
	font-weight: 700;	
}


.all-items .warranty_info {
	display: none;
}


.item-btn a {  
  padding: 10px !important;
  text-align: center;
}

.product-heading h2 {
	font-size: 18px !important;
}

.woocommerce-paragraph p a {
	text-decoration: none;
}

@media only screen and (max-width: 768px) and (min-width: 320px) {
	.image-content {
		width: 100%;		
}

.image-content img {
		width: 100%;		
}


.woocommerce-paragraph {
	width: 100%;	
}

.item-price {
	width: 100%;	
}

.item-btn {
	width: 100%;	
}


}
</style>

	<div class="cross-sells product-heading">
		<?php
		$heading = apply_filters( 'woocommerce_product_cross_sells_products_heading', __( 'You may be interested in&hellip;', 'woocommerce' ) );

		if ( $heading ) :
			?>
			<h2><?php echo esc_html( $heading ); ?></h2>
		<?php endif; ?>

		<?php woocommerce_product_loop_start(); ?>
			<?php foreach ( $cross_sells as $cross_sell ) : ?>

				<?php
					global $product;
					global $woocommerce;
					$currency = get_woocommerce_currency_symbol();

					
					$crosssellProduct = wc_get_product( $cross_sell->get_id());

					$link =  get_permalink( $cross_sell->get_id() );

					$post_object = get_post( $cross_sell->get_id() );

					setup_postdata( $GLOBALS['post'] =& $post_object );
				?>
				<div class="all-items">					
						<div class="image-content">
							<a href="<?php echo $link;?>"><?php echo woocommerce_get_product_thumbnail();?></a>
						</div>
						<div class="woocommerce-paragraph">
							<p><a href="<?php echo $link;?>"><?php echo $crosssellProduct->name;?></a></p>
							<span class="units">
								<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>						
							</span>
							<div class="item-code">SKU: <?php echo $crosssellProduct->sku;?> </div>
						</div>
						<div class="item-price">
							<p>
								<?php 
								   echo wc_price( round($crosssellProduct->price, 1) );
								?>
							</p>
						</div>
						<div class="item-btn">
							<?php do_action( 'woocommerce_after_shop_loop_item' );?>
						</div>					
				</div>
			<?php endforeach; ?>

		<?php woocommerce_product_loop_end(); ?>

	</div>	

	<?php
endif;

wp_reset_postdata();
