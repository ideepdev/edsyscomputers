<?php
$this->init_settings();
global $woocommerce;
$wc_main_settings = array();
$posted_services  = array();
if (isset($_POST['wf_aus_rates_save_changes_button'])) {
	$wc_main_settings                 = get_option('woocommerce_wf_australia_mypost_settings');
	$wc_main_settings['title']        = ( isset($_POST['wf_australia_post_title']) ) ? sanitize_text_field($_POST['wf_australia_post_title']) : __('MyPost Business', 'wf-shipping-auspost');
	$wc_main_settings['availability'] = ( isset($_POST['wf_australia_post_availability']) && 'all' === $_POST['wf_australia_post_availability'] ) ? 'all' : 'specific';
	
	$services = array();
	
	if (isset($_POST['australia_post_service'])) {
		$posted_services = $_POST['australia_post_service'];

		if (is_array($posted_services) && !empty($posted_services)) {
			foreach ($posted_services as $code => $settings) {

				$services[$code] = array(
					'name'                      => wc_clean($settings['name']),
					'order'                     => wc_clean($settings['order']),
					'enabled'                   => isset($settings['enabled']) ? true : false,
					'adjustment'                => wc_clean($settings['adjustment']),
					'adjustment_percent'        => str_replace('%', '', wc_clean($settings['adjustment_percent'])),
					'extra_cover'               => isset($settings['extra_cover']) ? true : false,
					'delivery_confirmation'     => isset($settings['delivery_confirmation']) ? true : false,
					'signature_on_delivery_option' => isset($settings['signature_on_delivery_option']) ? true : false,
				);
			}
		}
	}

	$wc_main_settings['services']                      = $services;
	$wc_main_settings['offer_rates']                   = ( isset($_POST['wf_australia_post_offer_rates']) ) ? 'cheapest' : 'all';
	$wc_main_settings['show_insurance_checkout_field'] = ( isset($_POST['wf_australia_post_show_insurance_checkout_field']) ) ? 'yes' : '';
	$wc_main_settings['show_signature_required_field'] = ( isset($_POST['wf_australia_post_show_signature_required_field']) ) ? 'yes' : '';
	if ( 'specific' === $wc_main_settings['availability'] ) {
		$wc_main_settings['countries'] = isset($_POST['wf_australia_post_countries']) ? $_POST['wf_australia_post_countries'] : '';
	}

	update_option('services_saved_in_settings', true);

	update_option('woocommerce_wf_australia_mypost_settings', $wc_main_settings);
}

$general_settings      = get_option('woocommerce_wf_australia_mypost_settings');
$this->custom_services = isset($general_settings['services']) ? $general_settings['services'] : $this->settings['services'];

?>

<table style="width:100%;table-layout:fixed; ">
	<tr valign="top">
		<td style="width:30%;font-weight:800;">
			<label for="wf_australia_post_offer_rates"><?php esc_html_e('Show/Hide', 'wf-shipping-auspost'); ?></label>
		</td>
		<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
			<fieldset style="padding:3px;">
				<input class="input-text regular-input " type="checkbox" name="wf_australia_post_offer_rates" id="wf_australia_post_offer_rates" style="" value="yes" <?php echo ( isset($general_settings['offer_rates']) && 'cheapest' === $general_settings['offer_rates'] ) ? 'checked' : ''; ?> placeholder=""> <?php esc_html_e('Show Cheapest Rates Only', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_html_e('On enabling this, the cheapest rate will be shown in the cart/checkout page.', 'wf-shipping-auspost'); ?>"></span>
			</fieldset>            
		</td>
	</tr>

	<tr valign="top">
		<td style="width:30%;font-weight:800;">
			<label for="wf_australia_post_show_insurance_checkout_field"><?php esc_html_e('Enable Checkout fields', 'wf-shipping-auspost'); ?></label>
		</td>
		<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
			<fieldset style="padding:3px;">
				<input class="input-text regular-input " type="checkbox" name="wf_australia_post_show_insurance_checkout_field" id="wf_australia_post_show_insurance_checkout_field" style="" value="yes" <?php echo ( isset($general_settings['show_insurance_checkout_field']) && 'yes' === $general_settings['show_insurance_checkout_field'] ) ? 'checked' : ''; ?> placeholder=""> <?php esc_html_e('Extra Cover', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Enable this field to let customers choose Extra Cover option', 'wf-shipping-auspost'); ?>"></span>
			</fieldset>			
			<fieldset style="padding:3px;">
							<input class="input-text regular-input " type="checkbox" name="wf_australia_post_show_signature_required_field" id="wf_australia_post_show_signature_required_field" style="" value="yes" <?php echo ( isset($general_settings['show_signature_required_field']) && 'yes' === $general_settings['show_signature_required_field'] ) ? 'checked' : ''; ?> placeholder=""> <?php esc_html_e('Signature Required', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Enable this field to let customers choose Signature Required on delivery.', 'wf-shipping-auspost'); ?>"></span>
			</fieldset>
			
		</td>
	</tr>
	<tr valign="top">
		<td colspan="2">
			<?php
			require_once 'wf_html_services.php';
			?>
		</td>
	</tr>
	<tr>
		<td colspan="2" style="text-align:right;padding-right: 10%;">
			<br />
			<input type="submit" value="<?php esc_attr_e('Save Changes', 'wf-shipping-auspost'); ?>" class="button button-primary" name="wf_aus_rates_save_changes_button">
		</td>
	</tr>
</table>
<script type="text/javascript">
	jQuery(document).ready(function( $ ) {
			jQuery('html').on('click',function(e){
				jQuery(window).off('beforeunload');
				window.onbeforeunload = null;
			});
	});
</script>
