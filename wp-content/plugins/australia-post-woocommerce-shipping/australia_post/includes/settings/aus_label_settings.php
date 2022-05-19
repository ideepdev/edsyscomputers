<style type="text/css">
	.dangerous_goods_startrack_elex td{
		vertical-align: middle;
		padding: 4px 7px;
	}
	.dangerous_goods_startrack_elex td input {
		margin-right: 4px;
	}
</style>
<?php
$this->init_settings(); 
global $woocommerce;
$wc_main_settings = array();
$customer_type    = array();

$customer_type = array('STANDARD_ADDRESS'=>'Normal Delivery Address.','PARCEL_LOCKER'=>'Australia Post Parcel Locker.','PARCEL_COLLECT'=>'Australia Post Parcel Collection location.');

$this->contracted_rates = $this->get_option('contracted_rates') == 'yes' ? true : false;

if (!$this->contracted_rates) {
	echo '<div class="error">
        <p>' . esc_html('Label Printing is only available for Contracted Account.', 'wf-shipping-auspost') . '</p>
    </div>';
}


if (isset($_POST['wf_australia_post_label_save'])) {
	$wc_main_settings = get_option('woocommerce_wf_australia_post_settings');
	if ($this->contracted_rates) {
		$wc_main_settings['dir_download']   = ( isset($_POST['wf_australia_post_dir_download']) ) ? 'yes' : '';
		$wc_main_settings['save_labels']    = ( isset($_POST['option_download_labels_auspost_elex']) ) ? 'yes' : '';
		$wc_main_settings['email_tracking'] = ( isset($_POST['wf_australia_post_email_tracking']) ) ? 'yes' : '';
		if ( 'startrack' == $this->rate_type ) {
			$wc_main_settings['starTrack_default_shipment_service'] = $_POST['starTrack_default_shipment_service'] . 'startrack';
			$wc_main_settings['label_layout_type_starTrack']        = $_POST['wf_auspost_label_layout_starTrack'];

			if (isset($_POST['enable_dangerous_goods_configuration_startrack'])) {
				$wc_main_settings['enable_dangerous_goods_configuration_startrack'] = $_POST['enable_dangerous_goods_configuration_startrack'];
			} else {
				$wc_main_settings['enable_dangerous_goods_configuration_startrack'] = '';
			}

			$wc_main_settings['dangerous_goods_descriptions'] = array();
			$dangerous_goods_un_numbers_post_request          = array();
			if (isset($_POST['dangerous_goods_un_number'])) {
				$dangerous_goods_un_numbers_post_request = $_POST['dangerous_goods_un_number'];
			}

			if (!empty($dangerous_goods_un_numbers_post_request)) :
				foreach ($dangerous_goods_un_numbers_post_request as $dangerous_goods_un_numbers_post_request_key => $dangerous_goods_un_numbers_post_request_value) {
					$wc_main_settings['dangerous_goods_descriptions'][$dangerous_goods_un_numbers_post_request_value] = array(
						'technical_name' => $_POST['dangerous_goods_technical_name'][$dangerous_goods_un_numbers_post_request_key],
						'class_division' => $_POST['dangerous_goods_class_division'][$dangerous_goods_un_numbers_post_request_key],
						'subsidiary_risk' => $_POST['dangerous_goods_subsidiary_risk'][$dangerous_goods_un_numbers_post_request_key],
						'packing_group_designator' => $_POST['dangerous_goods_packing_group_designator'][$dangerous_goods_un_numbers_post_request_key],
						'outer_packaging_type' => $_POST['dangerous_goods_outer_packaging_type'][$dangerous_goods_un_numbers_post_request_key],
						'outer_packaging_quantity' => $_POST['dangerous_goods_outer_packaging_quantity'][$dangerous_goods_un_numbers_post_request_key],
					);
				}
			endif;

		}

		$wc_main_settings['auspost_default_domestic_shipment_service']      = $_POST['auspost_default_domestic_shipment_service'];
		$wc_main_settings['auspost_default_international_shipment_service'] = $_POST['auspost_default_international_shipment_service'];
		$wc_main_settings['shipment_label_type']                            = ( isset($_POST['wf_australia_post_shipment_label_type']) ) ? 'branded' : '';
		$wc_main_settings['label_layout_type_parcel_post']                  = $_POST['wf_auspost_label_layout_parcel_post'];
		$wc_main_settings['label_layout_type_express_post']                 = $_POST['wf_auspost_label_layout_express_post'];
		$wc_main_settings['label_layout_type_international']                = $_POST['wf_auspost_label_layout_international'];
		$wc_main_settings['import_reference_number']                        = ( isset($_POST['elex_australia_post_import_reference_number']) ) ? $_POST['elex_australia_post_import_reference_number'] : false;
		$wc_main_settings['cus_type']                                       = $_POST['wf_australia_post_cus_type'];
		
		$wc_main_settings['custom_message'] = !empty($_POST['wf_australia_post_custom_message']) ? $_POST['wf_australia_post_custom_message'] : 'Your order was shipped on [DATE] via [SERVICE]. To track shipment, please follow the link of tracking ID(s) [ID]';
		$shipment_contents                  = !empty($_POST['wf_australia_post_ship_content']) ? stripslashes(sanitize_text_field($_POST['wf_australia_post_ship_content'])) : 'Shipment Contents';
		$wc_main_settings['ship_content']   = strlen($shipment_contents) > 20? substr($shipment_contents, 0, 16) . ' ...' : $shipment_contents;
		update_option('woocommerce_wf_australia_post_settings', $wc_main_settings);
	}
}

if ( !class_exists( 'WF_auspost_non_contracted_services' ) ) {
	include_once 'class_wf_auspost_non_contracted_services.php';
}

$auspost_non_contracted_services = new WF_auspost_non_contracted_services();
$services                        = $auspost_non_contracted_services->get_services();// these services are defined statically
$general_settings                = get_option('woocommerce_wf_australia_post_settings');

$parcel_label_layout_options           = array('A4-1pp', 'A4-4pp','THERMAL-LABEL-A6-1PP');
$express_label_layout_options          = array('A4-1pp', 'A4-3pp','THERMAL-LABEL-A6-1PP');
$starTrack_label_layout_options        = array('A4-1pp', 'A4-2pp', 'A4-1pp Landscape', 'A4-2pp Landscape', 'A6-1pp');
$shipment_custom_message               = ( !empty($general_settings['custom_message']) ) ? $general_settings['custom_message'] : '';
$auspost_contracted_postage_products   = get_option('auspost_contracted_postage_products');
$starTrack_contracted_postage_products = get_option('starTrack_postage_products');
$is_user_account_contract_type         = $this->general_settings['contracted_rates'];

$startrack_packaging_types = array(
	'CTN' => __('Carton', 'wf-shipping-auspost'),
	'PAL' => __('Pallet', 'wf-shipping-auspost'),
	'SAT' => __('Satchel', 'wf-shipping-auspost'),
	'BAG' => __('Bag', 'wf-shipping-auspost'),
	'ENV' => __('Envelope', 'wf-shipping-auspost'),
	'ITM' => __('Item', 'wf-shipping-auspost'),
	'JIF' => __('Jiffy Bag', 'wf-shipping-auspost'),
	'SKI' => __('Skid (up to 500kg)', 'wf-shipping-auspost'),
);

$starTrack_dangerous_goods_class_division = array(
	'1'     => __('Explosives', 'wf-shipping-auspost'),
	'2.1'   => __('Flammable Gases', 'wf-shipping-auspost'),
	'2.2'   => __('Non-flammable, Non-toxic gases', 'wf-shipping-auspost'),
	'2.3'   => __('Toxic Gases ', 'wf-shipping-auspost'),
	'3'     => __('Flammable Liquids ', 'wf-shipping-auspost'),
	'4.1'   => __('Flammable Solids', 'wf-shipping-auspost'),
	'4.2'   => __('Spontaneously Combustible', 'wf-shipping-auspost'),
	'4.3'   => __('Dangerous when wet', 'wf-shipping-auspost'),
	'5.1'   => __('Oxidizing Substances', 'wf-shipping-auspost'),
	'5.2'   => __('Organise Peroxides', 'wf-shipping-auspost'),
	'6.1'   => __('Toxic substances', 'wf-shipping-auspost'),
	'6.2'   => __('Infectious Substances', 'wf-shipping-auspost'),
	'7'     => __('Radioactive substances', 'wf-shipping-auspost'),
	'8'     => __('Corrosives', 'wf-shipping-auspost'),
	'9'     => __('Miscellaneous dangerous goods', 'wf-shipping-auspost')
);

$startrack_dangerous_goods_packing_group_designator = array(
	'I'     => __('Great Danger', 'wf-shipping-auspost'),
	'II'    => __('Medium Danger', 'wf-shipping-auspost'),
	'III'   => __('Minor Danger', 'wf-shipping-auspost'),
);

?>

<table id="auspost_label_settings_table">
	<tr valign="top" >
		<td style="width:50%;font-weight:800;">
			<label for="wf_australia_post_"><?php esc_html_e('Enable/Disable', 'wf-shipping-auspost'); ?></label>
		</td>
		<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
		
		<fieldset style="padding:3px;">
		  <input class="input-text regular-input " type="checkbox" name="wf_australia_post_email_tracking" id="wf_australia_post_email_tracking" style="" value="yes" <?php echo ( isset($general_settings['email_tracking']) && 'yes' === $general_settings['email_tracking'] ) ? 'checked' : ''; ?> placeholder="">  <?php esc_html_e('Email Tracking', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('On enabling this, Australia Post API would be able to email the tracking information to the customer.', 'wf-shipping-auspost'); ?>" ></span>
		</fieldset>
		<fieldset style="padding:3px;">
		  <input class="input-text regular-input " type="checkbox" name="wf_australia_post_dir_download" id="wf_australia_post_dir_download" style="" value="yes" <?php echo ( isset($general_settings['dir_download']) && 'yes' === $general_settings['dir_download'] ) ? 'checked' : ''; ?> placeholder="">  <?php esc_html_e('Enable Direct Download', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('By choosing this option, label and order summary will be downloaded instead of opening in a new browser window.', 'wf-shipping-auspost'); ?>" ></span>
		</fieldset>
		<fieldset style="padding:3px;">
			<input class="input-text regular-input " type="checkbox" name="option_download_labels_auspost_elex" id="option_download_labels_auspost_elex" value="yes" <?php echo ( isset($general_settings['save_labels']) && 'yes' === $general_settings['save_labels'] ) ? 'checked' : ''; ?> placeholder="">  <?php esc_html_e('Save Shipping Labels', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('By choosing this option, you can store the generated shipping label in the wp-content folder. This will help you access the labels in the future even if it is not available on the shipping carrier API.', 'wf-shipping-auspost'); ?>" ></span>
		</fieldset>
		<fieldset style="padding:3px;" id="wf_australia_post_shipment_label_type_fieldset">
			<input class="input-text regular-input " type="checkbox" name="wf_australia_post_shipment_label_type" id="wf_australia_post_shipment_label_type" style="" value="yes" <?php echo ( isset($general_settings['shipment_label_type']) && 'branded' === $general_settings['shipment_label_type'] ) ? 'checked' : ''; ?> placeholder="">  <?php esc_html_e('Show Australia Post Logo on the Shipment labels', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Using this option, you can opt to show or not to show the Australia Post logo on the Shipment labels (Available only for Australia Post).', 'wf-shipping-auspost'); ?>" ></span>
		</fieldset>
		</td>
	</tr>
	<tr valign="top" >
		<td style="width:50%;font-weight:800;">
			<label for="wf_australia_post_"><?php esc_html_e('Import reference number (IOSS)', 'wf-shipping-auspost'); ?></label>
		</td>
		<td>
			<fieldset style="padding:3px;">
				<input class="input-text regular-input " type="text" name="elex_australia_post_import_reference_number" id="elex_australia_post_import_reference_number" style="" value="<?php echo ( !empty($general_settings['import_reference_number']) ) ? esc_attr( $general_settings['import_reference_number'] ) :''; ?>" placeholder='Import Reference Number'> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Provide here Import reference number (IOSS).', 'wf-shipping-auspost'); ?>" ></span>
			</fieldset>
		</td>
	</tr>
	<tr valign="top" >
		<td style="width:50%;font-weight:800;">
			<label for="wf_australia_post_"><?php esc_html_e('Customer Address Type (AusPost eParcel)', 'wf-shipping-auspost'); ?></label><span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Select customer type (Available only for Australia Post).', 'wf-shipping-auspost'); ?>" ></span>
		</td>
		<td>
		<fieldset style="padding:3px;">
		<?php 
			$selected_doc_type = isset($general_settings['cus_type']) ? $general_settings['cus_type'] : 'STANDARD_ADDRESS';
		if (isset($customer_type) && !empty($customer_type)) {
			foreach ($customer_type as $key => $value) {
				if ($key === $selected_doc_type) {
					echo '<input class="input-text regular-input " type="radio" name="wf_australia_post_cus_type" id="wf_australia_post_cus_type" style="" value="' . esc_attr( $key ) . '" checked=true placeholder=""> ' . esc_html( $value ) . ' <br>';
				} else {
					echo '<input class="input-text regular-input " type="radio" name="wf_australia_post_cus_type" id="wf_australia_post_cus_type" style="" value="' . esc_attr( $key ) . '"  placeholder=""> ' . esc_html( $value ) . ' <br>';
				}
			}
		}
		?>
		</fieldset>
		</td>
	</tr>
	<?php 
	if ($this->contracted_rates) { 
		?>
			<tr valign="top" >
				<td style="width:50%;font-weight:800;">
					<label for="wf_australia_post_"><?php esc_html_e('Bulk Shipment', 'wf-shipping-auspost'); ?></label>
				</td>
				<td>
					<?php if ( 'startrack' == $this->rate_type) { ?>
						<fieldset style="padding:3px;">
							<?php esc_html_e('Default Shipment Service (StarTrack)', 'wf-shipping-auspost'); ?><span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Choose the default service for the shipment which will be set while generating bulk shipment label from order admin page. The default service will be applicable if there is no StarTrack service chosen during the checkout process', 'wf-shipping-auspost'); ?>"></span><br>
							<select name="starTrack_default_shipment_service" id="starTrack_default_shipment_service" style="width:200px;">
								<?php
								if ( 'yes' == $is_user_account_contract_type ) {
									if (isset($general_settings['starTrack_default_shipment_service']) && ( 'none' == $general_settings['starTrack_default_shipment_service'] )) {
										?>
										<option value="none" selected="selected"><?php esc_html_e( 'NONE', 'wf-shipping-auspost'); ?></option>
									<?php
									} else {
										?>
										<option value="none"><?php esc_html_e( 'NONE', 'wf-shipping-auspost'); ?></option>
									<?php
									}
									if (is_array($starTrack_contracted_postage_products) && !empty($starTrack_contracted_postage_products)) {
										foreach ($starTrack_contracted_postage_products as $postage_product) {
											if (isset($general_settings['starTrack_default_shipment_service'])) {
												$general_settings['starTrack_default_shipment_service'] = str_replace('startrack', '', $general_settings['starTrack_default_shipment_service']);
												if ($postage_product['product_id'] == $general_settings['starTrack_default_shipment_service']) {
													?>
													<option value="<?php echo esc_attr( $postage_product['product_id'] ); ?>" selected="selected"><?php esc_html_e( $postage_product['type'], 'wf-shipping-auspost'); ?></option>
												<?php } else { ?>
														<option value="<?php echo esc_attr( $postage_product['product_id'] ); ?>"><?php esc_html_e( $postage_product['type'], 'wf-shipping-auspost'); ?></option>
												<?php 
												}
											} else {
												?>
												<option value="<?php echo esc_attr( $postage_product['product_id'] ); ?>" selected="selected"><?php esc_html_e( $postage_product['type'], 'wf-shipping-auspost'); ?></option>
												<?php
											}
										}
									}
								}
								?>
							</select>
						</fieldset>
					<?php } ?>
					<fieldset style="padding:3px;">
						<?php esc_html_e('Default Domestic Service (AusPost)', 'wf-shipping-auspost'); ?><span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Choose the default service for domestic shipment which will be set while generating bulk shipment label from order admin page. The default service will be applicable if there is no Australia Post service chosen during the checkout process', 'wf-shipping-auspost'); ?>"></span><br>
						<select name="auspost_default_domestic_shipment_service" id="auspost_default_domestic_shipment_service" style="width:200px;">
							<?php
							if ( 'yes' == $is_user_account_contract_type ) {
								if (isset($general_settings['auspost_default_domestic_shipment_service']) && ( 'none' == $general_settings['auspost_default_domestic_shipment_service'] )) {
									?>
									<option value="none" selected="selected"><?php esc_html_e( 'NONE', 'wf-shipping-auspost'); ?></option>
								<?php
								} else {
									?>
									<option value="none"><?php esc_html_e( 'NONE', 'wf-shipping-auspost'); ?></option>
								<?php
								}
								
								foreach ($auspost_contracted_postage_products as $postage_product) {
									if (isset($postage_product['group'])) {
										if (isset($general_settings['auspost_default_domestic_shipment_service']) && ( $postage_product['product_id'] == $general_settings['auspost_default_domestic_shipment_service'] )) { 
											?>
											<option value="<?php echo esc_attr( $postage_product['product_id'] ); ?>" selected="selected"><?php esc_html_e( $postage_product['type'], 'wf-shipping-auspost'); ?></option>
								<?php } else { ?>
										<option value="<?php echo esc_attr( $postage_product['product_id'] ); ?>"><?php esc_html_e( $postage_product['type'], 'wf-shipping-auspost'); ?></option>
								<?php 
								}
									}
								}
							}
							?>
						</select>
					</fieldset>
					<fieldset style="padding:3px;">
						<?php esc_html_e('Default International Service (AusPost)', 'wf-shipping-auspost'); ?><span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Choose the default service for International shipment which will be set while generating bulk shipment label from order admin page. The default service will be applicable if there is no Australia Post service chosen during the checkout process', 'wf-shipping-auspost'); ?>"></span><br>
						<select name="auspost_default_international_shipment_service" id="auspost_default_international_shipment_service" style="width:200px;">
							<?php
							if ( 'yes' == $is_user_account_contract_type ) {
								if (isset($general_settings['auspost_default_international_shipment_service']) && ( 'none' == $general_settings['auspost_default_international_shipment_service'] )) {
									?>
									<option value="none" selected="selected"><?php esc_html_e( 'NONE', 'wf-shipping-auspost'); ?></option>
								<?php
								} else {
									?>
									<option value="none"><?php esc_html_e( 'NONE', 'wf-shipping-auspost'); ?></option>
								<?php
								}
								foreach ($auspost_contracted_postage_products as $postage_product) {
									if (!isset($postage_product['group'])) {
										if (isset($general_settings['auspost_default_international_shipment_service']) && ( $postage_product['product_id'] == $general_settings['auspost_default_international_shipment_service'] )) { 
											?>
											<option value="<?php echo esc_attr( $postage_product['product_id'] ); ?>" selected="selected"><?php esc_html_e( $postage_product['type'], 'wf-shipping-auspost'); ?></option>
								<?php } else { ?>
										<option value="<?php echo esc_attr( $postage_product['product_id'] ); ?>"><?php esc_html_e( $postage_product['type'], 'wf-shipping-auspost'); ?></option>
								<?php 
								}
									}
								}
							}
							?>
					</fieldset>
				</td>
			</tr>
 <?php 
	}
	?>
	<tr valign="top" >
		<td style="width:50%;font-weight:800;">
			<label for="wf_australia_post_"><?php esc_html_e('Documents Output Type', 'wf-shipping-auspost'); ?></label>
		</td>
		<td>
			<fieldset style="padding:3px;">
				<b>( PDF )</b><br/>
			</fieldset>
		</td>
	</tr>
	<tr valign="top" >
		<td style="width:50%;font-weight:800;">
			<label for="wf_australia_post_"><?php esc_html_e('Documents Layout', 'wf-shipping-auspost'); ?></label>
		</td>
		<td>
			<fieldset style="padding:3px;">
				<table>
					<?php
					if ( 'startrack' == $this->rate_type ) { 
						?>
							<tr>
								<td>StarTrack</td>
								<td>
									<select name="wf_auspost_label_layout_starTrack" id="wf_auspost_label_layout_starTrack" style="width:200px;">
										<?php 
										if (is_array($starTrack_label_layout_options) && !empty($starTrack_label_layout_options)) {
											foreach ($starTrack_label_layout_options as $option) {
												if (isset($general_settings['label_layout_type_starTrack'])) {
													if ($option == $general_settings['label_layout_type_starTrack']) { 
														?>
														<option value="<?php echo esc_attr( $option ); ?>" selected="selected"><?php esc_html_e( $option, 'wf-shipping-auspost'); ?></option>
											<?php } else { ?>
														<option value="<?php echo esc_attr( $option ); ?>"><?php esc_html_e( $option, 'wf-shipping-auspost'); ?></option>
											<?php 
											}
												} else { 
													?>
													<option value="<?php echo esc_attr( $option ); ?>"><?php esc_html_e( $option, 'wf-shipping-auspost'); ?></option>
										<?php 
												}
											}
										}
										?>
									</select>
								</td>
								<td><span class="woocommerce-help-tip" data-tip="pp - <?php esc_attr_e('labels per page', 'wf-shipping-auspost'); ?>"></span></td>
							</tr>
				<?php } ?>

						<tr>
							<td><?php esc_html_e('Parcel Post', 'wf-shipping-auspost'); ?></td>
							<td>
								<select name="wf_auspost_label_layout_parcel_post" id="wf_auspost_label_layout_parcel_post" style="width:200px;">
									<?php
									if (is_array($parcel_label_layout_options) && !empty($parcel_label_layout_options)) {
										foreach ($parcel_label_layout_options as $option) {
											if (isset($general_settings['label_layout_type_parcel_post'])) {
												if ($option == $general_settings['label_layout_type_parcel_post']) { 
													?>
													<option value="<?php echo esc_attr( $option ); ?>" selected="selected"><?php esc_html_e( $option, 'wf-shipping-auspost'); ?></option>
										<?php } else { ?>
													<option value="<?php echo esc_attr( $option ); ?>"><?php esc_html_e( $option, 'wf-shipping-auspost'); ?></option>
										<?php 
										}
											} else { 
												?>
												<option value="<?php echo esc_attr( $option ); ?>"><?php esc_html_e( $option, 'wf-shipping-auspost'); ?></option>
									<?php 
											}
										} 
									} 
									?>
								</select>
							</td>
							<td><span class="woocommerce-help-tip" data-tip="pp - <?php esc_attr_e('labels per page', 'wf-shipping-auspost'); ?>"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e('Express Post', 'wf-shipping-auspost'); ?></td>
							<td>
								<select name="wf_auspost_label_layout_express_post" id="wf_auspost_label_layout_express_post" style="width:200px;">
									<?php 
									if (isset($express_label_layout_options) && !empty($express_label_layout_options)) {
										foreach ($express_label_layout_options as $option) {
											if (isset($general_settings['label_layout_type_express_post'])) {
												if ($option == $general_settings['label_layout_type_express_post']) { 
													?>
													<option value="<?php echo esc_attr( $option ); ?>" selected="selected"><?php esc_html_e( $option, 'wf-shipping-auspost'); ?></option>
										<?php } else { ?>
													<option value="<?php echo esc_attr( $option ); ?>"><?php esc_html_e( $option, 'wf-shipping-auspost'); ?></option>
										<?php 
										}
											} else { 
												?>
												<option value="<?php echo esc_attr( $option ); ?>"><?php esc_html_e( $option, 'wf-shipping-auspost'); ?></option>
									<?php 
											}
										} 
									} 
									?>
								</select>
							</td>
							<td><span class="woocommerce-help-tip" data-tip="pp - <?php esc_attr_e('labels per page', 'wf-shipping-auspost'); ?>"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e('Australia Post International', 'wf-shipping-auspost'); ?></td>
							<td>
								<select name="wf_auspost_label_layout_international" id="wf_auspost_label_layout_international" style="width:200px;">
									<option value="A4-1pp"><?php esc_html_e('A4-1pp', 'wf-shipping-auspost'); ?></option>
								</select>
							</td>
							<td><span class="woocommerce-help-tip" data-tip="pp - <?php esc_attr_e('labels per page', 'wf-shipping-auspost'); ?>"></span></td>
						</tr>
				</table>
			</fieldset>
		</td>
	</tr>
	<tr valign="top" >
		<td style="width:50%;font-weight:800;">
			<label for="wf_australia_post_"><?php esc_html_e('Weight/ Dimension Unit', 'wf-shipping-auspost'); ?></label>
		</td>
		<td>
			<fieldset style="padding:3px;">
				<b>( KG/CM )</b><br/>
			</fieldset>
		</td>
	</tr>
	<tr>
	<td> </td>
	</tr>
	<tr valign="top" >
		<td style="width:50%;font-weight:800;">
			<label for="wf_australia_post_"><?php esc_html_e('Shipment Content', 'wf-shipping-auspost'); ?></label>
		</td>
		<td>
			<fieldset style="padding:3px;">
				<input class="input-text regular-input " type="text" name="wf_australia_post_ship_content" id="wf_australia_post_ship_content" style="" value="<?php echo ( !empty($general_settings['ship_content']) ) ? esc_attr( $general_settings['ship_content'] ) :''; ?>" placeholder='Shipment Contents'> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Provide here a description about the shipment content.', 'wf-shipping-auspost'); ?>" ></span>
			</fieldset>
		</td>
	</tr>
	
	<tr valign="top" >
		<td style="width:50%;font-weight:800;">
			<label for="wf_australia_post_"><?php esc_html_e('Custom Shipment Message', 'wf-shipping-auspost'); ?></label>
		</td>
		<td>
			<fieldset style="padding:3px;">
				<input class="input-text regular-input " type="text" name="wf_australia_post_custom_message" id="wf_australia_post_custom_message" style="width:800px;" value="<?php esc_attr_e($shipment_custom_message, 'wf-shipping-auspost'); ?>" placeholder="<?php esc_attr_e('Your order was shipped on [DATE] via [SERVICE]. To track shipment, please follow the link of tracking ID(s) [ID]', 'wf-shipping-auspost'); ?>"> 		
			</fieldset>
			<br/>
		</td>
	</tr>
	<?php if ( 'startrack' == $this->rate_type ) : ?>
		<tr valign="top" >
			<td style="width:50%;font-weight:800;">
				<label for="wf_australia_post_"><?php esc_html_e('Dangerous Goods (StarTrack)', 'wf-shipping-auspost'); ?></label>
			</td>
			<td>
				<fieldset style="padding:3px;">
					<?php esc_html_e('Enable', 'wf-shipping-auspost'); ?>
					<input class="input-text regular-input " type="checkbox" name="enable_dangerous_goods_configuration_startrack" id="enable_dangerous_goods_configuration_startrack" style="" value="yes" <?php echo ( isset($general_settings['enable_dangerous_goods_configuration_startrack']) &&  'yes' === $general_settings['enable_dangerous_goods_configuration_startrack'] ) ? 'checked' : ''; ?>><span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Enable this option if you are shipping dangerous goods using StarTrack.', 'wf-shipping-auspost'); ?>" ></span>
				</fieldset>
				<br/>
			</td>
		</tr>
		<tr valign="top" >
			<td style="width:50%;font-weight:800;" id="dangerous_goods_table_title_startrack_elex">
				<?php esc_html_e('Configure Dangerous Goods (StarTrack)', 'wf-shipping-auspost'); ?>
			</td>
		</tr>
		<table style="width:50%;font-weight:800;" class="dangerous_goods_table_startrack_elex widefat">
			<thead>
				<tr>
				   <th><b><?php esc_html_e('UN number', 'wf-shipping-auspost'); ?></b></th>
				   <th><b><?php esc_html_e('Technical Name', 'wf-shipping-auspost'); ?></b></th>
				   <th><b><?php esc_html_e('Class Division', 'wf-shipping-auspost'); ?></b></th>
				   <th><b><?php esc_html_e('Subsidiary Risk', 'wf-shipping-auspost'); ?></b></th>
				   <th><b><?php esc_html_e('Packing Group Designator', 'wf-shipping-auspost'); ?></b></th>
				   <th><b><?php esc_html_e('Outer Packaging Type', 'wf-shipping-auspost'); ?></b></th>
				   <th><b><?php esc_html_e('Outer Packaging Quantity', 'wf-shipping-auspost'); ?></b></th>
				   <th></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th colspan="3">
						<button id="insert_new_row_dangerous_goods" class="button button-secondary"><?php esc_html_e( 'Add', 'wf-shipping-auspost' ); ?></button>
						<button id="remove_selected_dangerous_goods" class="button button-secondary"><?php esc_html_e( 'Remove selected UN numbers', 'wf-shipping-auspost' ); ?></button>
					</th>
				</tr>
			</tfoot>
			<tbody>
			  <?php
			  $count = 1;
				if (isset($general_settings['dangerous_goods_descriptions']) && !empty($general_settings['dangerous_goods_descriptions'])) : 
					foreach ($general_settings['dangerous_goods_descriptions'] as $dangerous_goods_descriptions_key => $dangerous_goods_descriptions) {
						?>
				<tr>
					<th><input type="text" style="width: 95%; padding: 4px 7px;" name="dangerous_goods_un_number[<?php echo esc_attr( $count ); ?>]" value="<?php echo esc_attr( $dangerous_goods_descriptions_key ); ?>"></th>
					<th><input type="text" style="padding: 4px 7px;" name="dangerous_goods_technical_name[<?php echo esc_attr( $count ); ?>]" value="<?php echo esc_attr( $dangerous_goods_descriptions['technical_name'] ); ?>"></th>
					<th>
						<select name="dangerous_goods_class_division[<?php echo esc_attr( $count ); ?>]" id="dangerous_goods_class_division_startrack_auspost_elex">
							<?php 
							foreach ($starTrack_dangerous_goods_class_division as $class_division_value => $class_division_description) {
								if ($class_division_value == $dangerous_goods_descriptions['class_division']) {
									echo "<option value='" . esc_attr( $class_division_value ) . "' selected='selected'>" . esc_html( $class_division_description ) . '</option>';
								} else {
									echo "<option value='" . esc_attr( $class_division_value ) . "'>" . esc_html ( $class_division_description ) . '</option>';
								}
							}
							?>
						</select>
					</th>
					<th>
						<select name="dangerous_goods_subsidiary_risk[<?php echo esc_attr( $count ); ?>]" id="dangerous_goods_subsidiary_risk_startrack_auspost_elex">
							<?php 
							foreach ($starTrack_dangerous_goods_class_division as $class_division_value => $class_division_description) {
								if ($class_division_value == $dangerous_goods_descriptions['subsidiary_risk']) {
									echo "<option value='" . esc_attr( $class_division_value ) . "' selected='selected'>" . esc_html( $class_division_description ) . '</option>';
								} else {
									echo "<option value='" . esc_attr( $class_division_value ) . "'>" . esc_html( $class_division_description ) . '</option>';
								}
							}
							?>
						</select>
					</th>
					<th>
						<select name="dangerous_goods_packing_group_designator[<?php echo esc_attr( $count ); ?>]" id="dangerous_goods_packing_group_designator_startrack_auspost_elex">
							<?php 
							foreach ($startrack_dangerous_goods_packing_group_designator as $packing_group_designator_value => $packing_group_designator_label) {
								if ($packing_group_designator_value == $dangerous_goods_descriptions['packing_group_designator']) {
									echo "<option value='" . esc_attr( $packing_group_designator_value ) . "' selected='selected'>" . esc_html( $packing_group_designator_label ) . '</option>';
								} else {
									echo "<option value='" . esc_attr( $packing_group_designator_value ) . "'>" . esc_html( $packing_group_designator_label ) . '</option>';
								}
								
							}
							?>
						</select>
					</th>
					<th>
						<select name="dangerous_goods_outer_packaging_type[<?php echo esc_attr( $count); ?>]" id="dangerous_goods_outer_packaging_type_startrack_auspost_elex">
								<?php 
								foreach ($startrack_packaging_types as $package_type => $package_type_label) {
									if ($package_type == $dangerous_goods_descriptions['outer_packaging_type']) {
										echo "<option value='" . esc_attr( $package_type ) . "' selected='selected'>" . esc_html( $package_type_label ) . '</option>';
									} else {
										echo "<option value='" . esc_attr( $package_type ) . "'>" . esc_html( $package_type_label ) . '</option>';
									}
								} 
								?>
						</select>
					</th>
					<th><input type="text" style="width: 40%; padding: 4px 7px;" name="dangerous_goods_outer_packaging_quantity[<?php echo esc_attr( $count ); ?>]" value="<?php echo esc_attr( $dangerous_goods_descriptions['outer_packaging_quantity'] ); ?>"></th>
					<th class="button_remove_selected_un_numbers"><input type="checkbox" class="input-text regular-input"></th>
				</tr>
					<?php 
					$count++;
					} 
			endif; 
				?>
			</tbody>
		</table>
		<?php endif; ?>	
		<tr>
			<td colspan="2" style="text-align:center;">
				<input type="submit" value="<?php esc_attr_e('Save Changes', 'wf_australia_post'); ?>" class="button button-primary" name="wf_australia_post_label_save">
			</td>
		</tr>
	</table>
<script type="text/javascript">

	jQuery(document).ready(function(){
		
				jQuery('html').on('click',function(e){
					jQuery(window).off('beforeunload');
					window.onbeforeunload = null;
				});
		function showHideDangerousGoodsConfigurationTable(){
			if(jQuery('#enable_dangerous_goods_configuration_startrack').is(':checked')){
				jQuery('.dangerous_goods_table_startrack_elex').show();
				jQuery('#dangerous_goods_table_title_startrack_elex').show();
			}else{
				jQuery('.dangerous_goods_table_startrack_elex').hide();
				jQuery('#dangerous_goods_table_title_startrack_elex').hide();
			}
		};

		showHideDangerousGoodsConfigurationTable();

		jQuery('#insert_new_row_dangerous_goods').click(function(){
			var $tbody = jQuery('.dangerous_goods_table_startrack_elex').find('tbody');
			var size = $tbody.find('tr').size();
			size++;
			var html = '<tr>\
				<th><input type="text" style="width: 95%; padding: 4px 7px;" name="dangerous_goods_un_number['+  size +']" value=""></th>\
				<th><input type="text" name="dangerous_goods_technical_name['+ size +'] value=""></th>\
				<th>\
					<select name="dangerous_goods_class_division['+ size +']" id="dangerous_goods_class_division_startrack_auspost_elex">\
						<?php 
						foreach ($starTrack_dangerous_goods_class_division as $class_division_value => $class_division_description) {
							echo '<option value=' . esc_attr( $class_division_value ) . '>' . esc_html( $class_division_description ) . '</option>';
						}
						?>
					</select>\
				</th>\
				<th>\
					<select name="dangerous_goods_subsidiary_risk['+ size +']" id="dangerous_goods_subsidiary_risk_startrack_auspost_elex">\
						<?php 
						foreach ($starTrack_dangerous_goods_class_division as $class_division_value => $class_division_description) {
							echo '<option value=' . esc_attr( $class_division_value ) . '>' . esc_html( $class_division_description ) . '</option>';
						}
						?>
					</select>\
				</th>\
				<th>\
					<select name="dangerous_goods_packing_group_designator['+ size +']" id="dangerous_goods_packing_group_designator_startrack_auspost_elex">\
						<?php 
						foreach ($startrack_dangerous_goods_packing_group_designator as $packing_group_designator_value => $packing_group_designator_label) {
							echo '<option value=' . esc_attr( $packing_group_designator_value ) . '>' . esc_html( $packing_group_designator_label ) . '</option>';
							
						}
						?>
					</select>\
				</th>\
				<th>\
					<select name="dangerous_goods_outer_packaging_type['+ size +']" id="dangerous_goods_outer_packaging_type_startrack_auspost_elex">\
						<?php 
						foreach ($startrack_packaging_types as $package_type => $package_type_label) {
							echo '<option value=' . esc_attr( $package_type ) . '>' . esc_html( $package_type_label ) . '</option>';
						} 
						?>
					</select>\
				</th>\
				<th><input type="text" style="width: 40%; padding: 4px 7px;" name="dangerous_goods_outer_packaging_quantity['+ size +']" value=""></th>\
				<th class="button_remove_selected_un_numbers"><input type="checkbox" class="input-text regular-input"></th>\
			</tr>';

			$tbody.append( html );
			return false;
		});

		jQuery('#remove_selected_dangerous_goods').click(function() {
			var $tbody = jQuery('.dangerous_goods_table_startrack_elex').find('tbody');
			$tbody.find('.button_remove_selected_un_numbers input:checked').each(function() {
				jQuery(this).closest('tr').remove();
			});
			return false;
		});

		jQuery('#enable_dangerous_goods_configuration_startrack').change(function(){
			showHideDangerousGoodsConfigurationTable()
		});

	});

</script>
