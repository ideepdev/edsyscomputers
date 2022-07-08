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
$general_settings = get_option('woocommerce_wf_australia_mypost_settings');

$this->authorized = ( isset($general_settings['client_account_name'] ) && '' !== $general_settings['client_account_name'] )? true : false;

if (!$this->authorized) {
	echo '<div class="error">
        <p>' . esc_html('Label Printing is only available for Contracted Account.', 'wf-shipping-auspost') . '</p>
    </div>';
}


if (isset($_POST['wf_australia_mypost_label_save'])) {
	$wc_main_settings = get_option('woocommerce_wf_australia_mypost_settings');
	if ($this->authorized) {
		$wc_main_settings['dir_download']                                     = ( isset($_POST['wf_australia_post_dir_download']) ) ? 'yes' : '';
		$wc_main_settings['ausmypost_default_domestic_shipment_service']      = $_POST['ausmypost_default_domestic_shipment_service'];
		$wc_main_settings['ausmypost_default_international_shipment_service'] = $_POST['ausmypost_default_international_shipment_service'];
		$wc_main_settings['save_labels']                                      = ( isset($_POST['option_download_labels_auspost_elex']) ) ? 'yes' : '';
		$wc_main_settings['email_tracking']                                   = ( isset($_POST['wf_australia_post_email_tracking']) ) ? 'yes' : '';
		$wc_main_settings['shipment_label_type']                              = ( isset($_POST['wf_australia_post_shipment_label_type']) ) ? 'branded' : '';
		$wc_main_settings['shipment_pickup_service']                          = ( isset($_POST['wf_australia_mypost_pickup_service']) ) ? 'yes' : '';
		$wc_main_settings['label_layout_type_parcel_post']                    = $_POST['wf_auspost_label_layout_parcel_post'];
		$wc_main_settings['label_layout_type_express_post']                   = $_POST['wf_auspost_label_layout_express_post'];
		$wc_main_settings['label_layout_type_international']                  = $_POST['wf_auspost_label_layout_international'];
		$wc_main_settings['import_reference_number']                          = ( isset($_POST['elex_australia_post_import_reference_number']) ) ? $_POST['elex_australia_post_import_reference_number'] : false;
		$wc_main_settings['custom_message']                                   = !empty($_POST['wf_australia_post_custom_message']) ? $_POST['wf_australia_post_custom_message'] : 'Your order was shipped on [DATE] via [SERVICE]. To track shipment, please follow the link of tracking ID(s) [ID]';
		$shipment_contents                = !empty($_POST['wf_australia_post_ship_content']) ? stripslashes(sanitize_text_field($_POST['wf_australia_post_ship_content'])) : 'Shipment Contents';
		$wc_main_settings['ship_content'] = strlen($shipment_contents) > 20? substr($shipment_contents, 0, 16) . ' ...' : $shipment_contents;
		update_option('woocommerce_wf_australia_mypost_settings', $wc_main_settings);
	}
}

if (!class_exists('WF_ausmypost_services')) {
	include_once 'settings/class_wf_ausmypost_services.php';
}
$general_settings             = get_option('woocommerce_wf_australia_mypost_settings');
$ausmypost_services           = new WF_ausmypost_services();
$services                     = $ausmypost_services->get_services();// these services are defined statically
$parcel_label_layout_options  = array('A4-1pp', 'A4-4pp','THERMAL-LABEL-A6-1PP');
$express_label_layout_options = array('A4-1pp','THERMAL-LABEL-A6-1PP');
$shipment_custom_message      = ( !empty($general_settings['custom_message']) ) ? $general_settings['custom_message'] : '';
$bulk_shipments_url           = admin_url('admin.php?page=elex_mypost_bulk_shipment_order');
?>

<table id="auspost_label_settings_table">
	<tr valign="top" >
		<td style="width:50%;font-weight:800;">
			<label for="wf_australia_post_"><?php esc_html_e('Enable/Disable', 'wf-shipping-auspost'); ?></label>
		</td>
		<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
		
		<fieldset style="padding:3px;">
		  <input class="input-text regular-input " type="checkbox" name="wf_australia_post_email_tracking" id="wf_australia_post_email_tracking" style="" value="yes" <?php echo ( isset($general_settings['email_tracking']) && 'yes' === $general_settings['email_tracking'] ) ? 'checked' : ''; ?> placeholder="">  <?php esc_html_e('Email Tracking', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Enable this option to let MyPost Business API email the tracking information to the customer.', 'wf-shipping-auspost'); ?>" ></span>
		</fieldset>
		<fieldset style="padding:3px;">
		  <input class="input-text regular-input " type="checkbox" name="wf_australia_post_dir_download" id="wf_australia_post_dir_download" style="" value="yes" <?php echo ( isset($general_settings['dir_download']) && 'yes' === $general_settings['dir_download'] ) ? 'checked' : ''; ?> placeholder="">  <?php esc_html_e('Enable Direct Download', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('By choosing this option, label will be downloaded instead of opening in a new browser window.', 'wf-shipping-auspost'); ?>" ></span>
		</fieldset>
		<fieldset style="padding:3px;">
			<input class="input-text regular-input " type="checkbox" name="option_download_labels_auspost_elex" id="option_download_labels_auspost_elex" value="yes" <?php echo ( isset($general_settings['save_labels']) && 'yes' === $general_settings['save_labels'] ) ? 'checked' : ''; ?> placeholder="">  <?php esc_html_e('Save Shipping Labels', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('By choosing this option, you can store the generated shipping label in the wp-content folder. This will help you access the labels in the future even if it is not available on the shipping carrier API.', 'wf-shipping-auspost'); ?>" ></span>
		</fieldset>
		<fieldset style="padding:3px;" id="wf_australia_post_shipment_label_type_fieldset">
			<input class="input-text regular-input " type="checkbox" name="wf_australia_post_shipment_label_type" id="wf_australia_post_shipment_label_type" style="" value="yes" <?php echo ( isset($general_settings['shipment_label_type']) && 'branded' === $general_settings['shipment_label_type'] ) ? 'checked' : ''; ?> placeholder="">  <?php esc_html_e('Show Australia Post Logo on the Shipment labels', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Using this option, you can opt to show or not to show the  MyPost Business logo on the Shipment labels.', 'wf-shipping-auspost'); ?>" ></span>
		</fieldset>
		<fieldset style="padding:3px;" id="wf_australia_mypost_pickup_service_fieldset">
			<input class="input-text regular-input " type="checkbox" name="wf_australia_mypost_pickup_service" id="wf_australia_mypost_pickup_service" style="" value="yes" <?php echo ( isset($general_settings['shipment_pickup_service']) && 'yes' === $general_settings['shipment_pickup_service'] ) ? 'checked' : ''; ?> placeholder="">  <?php esc_html_e('Enable Pickup service option', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Enable this option to show the option for MyPost Business Pickup service on the MyPost Business Bulk Shipments Generation <a href= "' . $bulk_shipments_url . '">page</a>. Please note, the pickup service is available only with the bulk shipment generation process only, and not with individual orders.', 'wf-shipping-auspost'); ?>" ></span>
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
	<?php 
	if ($this->authorized) { 
		?>
			<tr valign="top" >
				<td style="width:50%;font-weight:800;">
					<label for="wf_australia_post_"><?php esc_html_e('Default Shipment', 'wf-shipping-auspost'); ?></label>
				</td>
				<td>
					<fieldset style="padding:3px;">
						<?php esc_html_e('Default Domestic Service (MyPost Business)', 'wf-shipping-auspost'); ?><span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('The default service will be applicable if there is no MyPost Business service chosen during the checkout process', 'wf-shipping-auspost'); ?>"></span><br>
						<select name="ausmypost_default_domestic_shipment_service" id="ausmypost_default_domestic_shipment_service" style="width:200px;">
							<?php
							if (isset($general_settings['ausmypost_default_domestic_shipment_service']) && ( 'none' == $general_settings['ausmypost_default_domestic_shipment_service'] )) {
								?>
									<option value="none" selected="selected"><?php esc_html_e( 'NONE', 'wf-shipping-auspost'); ?></option>
								<?php
							} else {
								?>
									<option value="none"><?php esc_html_e( 'NONE', 'wf-shipping-auspost'); ?></option>
								<?php
							}
								
							foreach ($services as $postage_product => $value) {
								if ('domestic' == $value['eligible_for']) {
									if (isset($general_settings['ausmypost_default_domestic_shipment_service']) && ( $postage_product == $general_settings['ausmypost_default_domestic_shipment_service'] )) { 
										?>
												<option value="<?php echo esc_attr( $postage_product ); ?>" selected="selected"><?php esc_html_e( $value['name'], 'wf-shipping-auspost'); ?></option>
											<?php 
									} else {
										?>
											<option value="<?php echo esc_attr( $postage_product ); ?>"><?php esc_html_e( $value['name'], 'wf-shipping-auspost'); ?></option>
											<?php 
									}	
								}	
							}
							
							?>
						</select>
					</fieldset>
					<fieldset style="padding:3px;">
						<?php esc_html_e('Default International Service (MyPost Business)', 'wf-shipping-auspost'); ?><span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('The default service will be applicable if there is no MyPost Business service chosen during the checkout process', 'wf-shipping-auspost'); ?>"></span><br>
						<select name="ausmypost_default_international_shipment_service" id="ausmypost_default_international_shipment_service" style="width:200px;">
							<?php
							if (isset($general_settings['ausmypost_default_international_shipment_service']) && ( 'none' == $general_settings['ausmypost_default_international_shipment_service'] )) {
								?>
									<option value="none" selected="selected"><?php esc_html_e( 'NONE', 'wf-shipping-auspost'); ?></option>
									<?php
							} else {
								?>
									<option value="none"><?php esc_html_e( 'NONE', 'wf-shipping-auspost'); ?></option>
									<?php
							}
							foreach ($services as $postage_product => $value) {
								if ('international' == $value['eligible_for']) {
									if (isset($general_settings['ausmypost_default_international_shipment_service']) && ( $postage_product == $general_settings['ausmypost_default_international_shipment_service'] )) { 
										?>
											<option value="<?php echo esc_attr( $postage_product ); ?>" selected="selected"><?php esc_html_e(  $value['name'], 'wf-shipping-auspost'); ?></option>
											<?php
									} else { 
										?>
											<option value="<?php echo esc_attr( $postage_product ); ?>"><?php esc_html_e(  $value['name'], 'wf-shipping-auspost'); ?></option>
											<?php 
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
							<td><?php esc_html_e('MyPost Business International', 'wf-shipping-auspost'); ?></td>
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
		<tr>
			<td colspan="2" style="text-align:center;">
				<input type="submit" value="<?php esc_attr_e('Save Changes', 'wf_australia_post'); ?>" class="button button-primary" name="wf_australia_mypost_label_save">
			</td>
		</tr>
	</table>
<script type="text/javascript">

	jQuery(document).ready(function(){
		
				jQuery('html').on('click',function(e){
					jQuery(window).off('beforeunload');
					window.onbeforeunload = null;
				});

	});

</script>
