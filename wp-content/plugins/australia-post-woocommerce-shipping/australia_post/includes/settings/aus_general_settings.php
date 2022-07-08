<?php 
global $woocommerce;
if (isset($_POST['wf_aus_genaral_save_changes_button'])) {
	$wc_main_settings                                        = get_option('woocommerce_wf_australia_post_settings');
	$wc_main_settings['api_key']                             = ( isset($_POST['wf_australia_post_api_key']) ) ? sanitize_text_field($_POST['wf_australia_post_api_key']) : '';
	$wc_main_settings['api_pwd']                             = ( isset($_POST['wf_australia_post_api_pwd']) ) ? sanitize_text_field($_POST['wf_australia_post_api_pwd']) : '';
	$wc_main_settings['api_account_no']                      = ( isset($_POST['wf_australia_post_api_account_no']) ) ? sanitize_text_field($_POST['wf_australia_post_api_account_no']) : '';
	$wc_main_settings['conversion_rate']                     = ( isset($_POST['wf_australia_post_conversion_rate']) ) ? sanitize_text_field($_POST['wf_australia_post_conversion_rate']) : '';
	$wc_main_settings['origin_name']                         = ( isset($_POST['wf_australia_post_origin_name']) ) ? sanitize_text_field($_POST['wf_australia_post_origin_name']) : '';
	$wc_main_settings['origin_suburb']                       = ( isset($_POST['wf_australia_post_origin_suburb']) ) ? sanitize_text_field($_POST['wf_australia_post_origin_suburb']) : '';
	$wc_main_settings['origin_line']                         = ( isset($_POST['wf_australia_post_origin_line']) ) ? sanitize_text_field($_POST['wf_australia_post_origin_line']) : '';
	$wc_main_settings['origin_state']                        = ( isset($_POST['wf_australia_post_origin_state']) ) ? sanitize_text_field($_POST['wf_australia_post_origin_state']) : '';
	$wc_main_settings['origin']                              = ( isset($_POST['wf_australia_post_origin']) ) ? sanitize_text_field($_POST['wf_australia_post_origin']) : '';
	$wc_main_settings['shipper_phone_number']                = ( isset($_POST['wf_australia_post_shipper_phone_number']) ) ? sanitize_text_field($_POST['wf_australia_post_shipper_phone_number']) : '';
	$wc_main_settings['shipper_email']                       = ( isset($_POST['wf_australia_post_shipper_email']) ) ? sanitize_text_field($_POST['wf_australia_post_shipper_email']) : '';
	$wc_main_settings['contracted_rates']                    = ( isset($_POST['wf_australia_post_contracted_rates']) ) ? 'yes' : '';
	$wc_main_settings['wf_australia_post_starTrack_rates']   = ( isset($_POST['wf_australia_post_starTrack_rates']) && !empty($_POST['wf_australia_post_starTrack_rates']) ) ? 'yes' : '';
	$wc_main_settings['wf_australia_post_starTrack_api_pwd'] = ( isset($_POST['wf_australia_post_starTrack_api_password']) ) ? sanitize_text_field($_POST['wf_australia_post_starTrack_api_password']) : '';
	$wc_main_settings['wf_australia_post_starTrack_api_account_no']   = ( isset($_POST['wf_australia_post_starTrack_api_account_no']) ) ? sanitize_text_field($_POST['wf_australia_post_starTrack_api_account_no']) : '';
	$wc_main_settings['wf_australia_post_starTrack_rates_selected']   = ( $wc_main_settings['wf_australia_post_starTrack_rates'] && !empty($wc_main_settings['wf_australia_post_starTrack_api_account_no']) )? true: false;
	$wc_main_settings['wf_australia_post_starTrack_api_key_selected'] = ( isset($_POST['wf_australia_post_starTrack_api_key_selected']) ) ? true : false;
	$wc_main_settings['wf_australia_post_starTrack_api_key']          = ( isset($_POST['wf_australia_post_starTrack_api_key']) ) ? ( $_POST['wf_australia_post_starTrack_api_key'] ) : false;
	$wc_main_settings['wf_australia_post_starTrack_api_key_enabled']  = ( $wc_main_settings['wf_australia_post_starTrack_api_key_selected'] && !empty($wc_main_settings['wf_australia_post_starTrack_api_key']) )? true: false;
	$wc_main_settings['enabled']                                      = ( isset($_POST['wf_australia_post_enabled']) ) ? 'yes' : '';
	$wc_main_settings['enabled_label']                                = ( isset($_POST['wf_australia_post_enabled_label']) ) ? 'yes' : '';
	$wc_main_settings['debug_mode']                                   = ( isset($_POST['wf_australia_post_debug_mode']) ) ? 'yes' : ''; 
	$wc_main_settings['vendor_check']                                 = ( isset($_POST['wf_australia_post_vendor_check']) ) ? 'yes' : '';     
	$wc_main_settings['include_exclude_gst']                          = ( isset($_POST['wf_australia_post_include_exclude_gst']) )? $_POST['wf_australia_post_include_exclude_gst'] : 'include';

	$wc_main_settings['contracted_api_mode'] = $_POST['wf_australia_post_contracted_api_mode'];
	if (in_array('elex-australia-post-for-woocommerce-bulk-printing-labels-addon/elex-australia-post-for-woocommerce-bulk-printing-labels-addon.php', get_option('active_plugins'))) {
		$wc_main_settings['addon_bulk_printing_project_key'] = ( isset($_POST['addon_bulk_printing_project_key']) ) ? sanitize_text_field($_POST['addon_bulk_printing_project_key']) : '';
		$wc_main_settings['addon_bulk_printing_secret_key']  = ( isset($_POST['addon_bulk_printing_secret_key']) ) ? sanitize_text_field($_POST['addon_bulk_printing_secret_key']) : '';
	}
	update_option('woocommerce_wf_australia_post_settings', $wc_main_settings);
}

$general_settings = get_option('woocommerce_wf_australia_post_settings');
$general_settings = empty($general_settings) ? array() : $general_settings;


?>

<img style="float:right;" src="<?php echo WF_AUSTRALIA_POST_URL . 'australia_post/includes/settings/aus.png'; ?>" width="180" height="" />
<table style="font-size:13px;">
	<tr valign="top">
		<td style="width:40%;font-weight:700;">
			<label for="wf_australia_post_contracted_api_mode"><?php esc_html_e('Account Details', 'wf-shipping-auspost'); ?> </label>
		</td>
		<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
			<fieldset style="padding:3px;">
				<input class="input-text regular-input " size="40" type="text" name="wf_australia_post_api_key" id="wf_australia_post_api_key"  value="<?php echo ( isset($general_settings['api_key']) ) ? esc_attr( $general_settings['api_key'] ) : ''; ?>" placeholder="8fd6e23e-15fd-4e87-b7a2-ba557b0ff0dd"> <label for="wf_australia_post_ac_num"><?php esc_html_e('API Key', 'wf-shipping-auspost'); ?></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('On Registering with Australia Post, you would get an  API key which you are required to fill in here. Applicable for both Contracted and Non-contracted accounts.', 'wf-shipping-auspost'); ?>"></span>
			</fieldset>
			<fieldset style="padding:3px;">
				<input class="input-text regular-input " type="checkbox" name="wf_australia_post_contracted_rates" id="wf_australia_post_contracted_rates"  <?php echo ( isset($general_settings['contracted_rates']) && 'yes' == $general_settings['contracted_rates'] ) ? 'checked' : ''; ?> > <label for="wf_australia_post_ac_num"><?php esc_html_e('Contracted Account', 'wf-shipping-auspost'); ?></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('If you have an Australia Post account, enable Contracted.', 'wf-shipping-auspost'); ?>"></span>
			</fieldset>
			<p style="font-size: 80%; padding: 0% !important; margin: 0% !important;"><?php esc_html_e('(Go to Rates & Services tab and save the settings while switching from contracted to non-contracted account and Vice versa.)', 'wf-shipping-auspost'); ?></p>
			<fieldset style="padding:3px;" id="aus_live_test">
				<?php 
				if (isset($general_settings['contracted_api_mode']) && 'live' === $general_settings['contracted_api_mode'] ) { 
					?>
				<input class="input-text regular-input " type="radio" name="wf_australia_post_contracted_api_mode"  id="wf_australia_post_contracted_api_mode"  value="test" placeholder=""> <?php esc_html_e('Test Mode', 'wf-shipping-auspost'); ?>
				<input class="input-text regular-input " type="radio"  name="wf_australia_post_contracted_api_mode" checked=true id="wf_australia_post_contracted_api_mode"  value="live" placeholder=""> <?php esc_html_e('Live Mode', 'wf-shipping-auspost'); ?>
				<?php } else { ?>
				<input class="input-text regular-input " type="radio" name="wf_australia_post_contracted_api_mode" checked=true id="wf_australia_post_contracted_api_mode"  value="test" placeholder=""> <?php esc_html_e('Test Mode', 'wf-shipping-auspost'); ?>
				<input class="input-text regular-input " type="radio" name="wf_australia_post_contracted_api_mode" id="wf_australia_post_contracted_api_mode"  value="live" placeholder=""> <?php esc_html_e('Live Mode', 'wf-shipping-auspost'); ?>
				<?php } ?>
				<br>
			</fieldset>
	
			<b style="padding:3px; margin-left: 1%" id="auspost_service_title_auspost_elex">Australia Post</b>
			<fieldset style="padding:3px;" id="aus_pass">
				<input class="input-text regular-input " type="password" name="wf_australia_post_api_pwd" id="wf_australia_post_api_pwd"  value="<?php echo ( isset($general_settings['api_pwd']) ) ? esc_attr( $general_settings['api_pwd'] ) : ''; ?>" placeholder="" required="true" novalidate> <label for="wf_australia_post_"><?php esc_html_e('API password', 'wf-shipping-auspost'); ?><font style="color:red;">*</font></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('If you have enabled Contracted, then enter the API password here.', 'wf-shipping-auspost'); ?>"></span>  
			</fieldset>
			<fieldset style="padding:3px;" id="aus_acc_num">
				<input class="input-text regular-input " type="Password" name="wf_australia_post_api_account_no" id="wf_australia_post_api_account_no"  value="<?php echo ( isset($general_settings['api_account_no']) ) ? esc_attr( $general_settings['api_account_no'] ) : ''; ?>" placeholder="**************" required="true" novalidate/>
				<label for="wf_australia_post_api_account_no"><?php esc_html_e('Account Number', 'wf-shipping-auspost'); ?><font style="color:red;">*</font></label>
				<span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Account Number of your Australia Post Account', 'wf-shipping-auspost'); ?>"></span>
			</fieldset>
			<br>
			<fieldset style="padding:3px;" id="starTrack_account_rates_select_field">
				<input class="input-text regular-input " type="checkbox" name="wf_australia_post_starTrack_rates" id="wf_australia_post_starTrack_rates"  <?php echo ( isset($general_settings['wf_australia_post_starTrack_rates']) && 'yes' == $general_settings['wf_australia_post_starTrack_rates'] ) ? 'checked' : ''; ?> > <label for="wf_australia_post_starTrack_rates"><?php esc_html_e('I have a StarTrack Account', 'wf-shipping-auspost'); ?></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('If you have a StarTrack account, enter your StarTrack account number to get StarTrack rates.', 'wf-shipping-auspost'); ?>"></span>
			</fieldset>
			<fieldset style="padding:3px;" id="starTrack_pass">
				<input class="input-text regular-input " type="password" name="wf_australia_post_starTrack_api_password" id="wf_australia_post_starTrack_api_password"  value="<?php echo ( isset($general_settings['wf_australia_post_starTrack_api_pwd']) ) ? esc_attr( $general_settings['wf_australia_post_starTrack_api_pwd'] ) : ''; ?>" placeholder=""> <label for="wf_australia_post_"><?php esc_html_e('API password', 'wf-shipping-auspost'); ?><font style="color:red;">*</font></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e( 'Enter API password if you have selected Contracted Account.', 'wf-shipping-auspost'); ?>"></span>  
			</fieldset>
			<fieldset style="padding:3px;" id="starTrack_acc_num">
				<input class="input-text regular-input " type="Password" name="wf_australia_post_starTrack_api_account_no" id="wf_australia_post_starTrack_api_account_no"  value="<?php echo ( isset($general_settings['wf_australia_post_starTrack_api_account_no']) ) ? esc_attr( $general_settings['wf_australia_post_starTrack_api_account_no'] ) : ''; ?>" placeholder="**************" />
				<label for="wf_australia_post_starTrack_api_account_no" id="wf_australia_post_starTrack_api_account_no_label"><?php esc_html_e('Account Number', 'wf-shipping-auspost'); ?><font style="color:red;">*</font></label>
				<span class="woocommerce-help-tip" id="wf_australia_post_starTrack_api_account_no_datatip" data-tip="<?php esc_attr_e('Account Number of your StarTrack Contract Account', 'wf-shipping-auspost'); ?>"></span>
			</fieldset>            
			<fieldset style="padding:3px;" class="elex_australia_post_starTrack_api_key_field">
				<input class="input-text regular-input " type="checkbox" name="wf_australia_post_starTrack_api_key_selected" id="wf_australia_post_starTrack_api_key_selected"  <?php echo ( isset($general_settings['wf_australia_post_starTrack_api_key_selected']) && $general_settings['wf_australia_post_starTrack_api_key_selected'] ) ? 'checked' : ''; ?> > <label for="wf_australia_post_starTrack_api_key_selected"><?php esc_html_e('I have a separate API Key for StarTrack', 'wf-shipping-auspost'); ?></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('If you have a separate StarTrack API, enter your StarTrack API to get StarTrack rates.', 'wf-shipping-auspost'); ?>"></span>
			</fieldset>
			<fieldset style="padding:3px;" class="elex_australia_post_starTrack_api_key_field elex_australia_post_starTrack_api_key_input_field">
				<input class="input-text regular-input " size="40" type="text" name="wf_australia_post_starTrack_api_key" id="wf_australia_post_starTrack_api_key"  value="<?php echo ( isset($general_settings['wf_australia_post_starTrack_api_key']) ) ? esc_attr( $general_settings['wf_australia_post_starTrack_api_key'] ) : ''; ?>" placeholder="8fd6e23e-15fd-4e87-b7a2-ba557b0ff0dd"> <label for="wf_australia_post_ac_num"><?php esc_html_e('StarTrack API Key', 'wf-shipping-auspost'); ?></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Enter the specific API Key of StarTrack.', 'wf-shipping-auspost'); ?>"></span>
			</fieldset>
		</td>
	</tr>
	<tr valign="top">
		<td style="width:40%;font-weight:700;">
			<label for="wf_australia_post_rates"><?php esc_html_e('Enable/Disable', 'wf-shipping-auspost'); ?></label>
		</td>
		<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
			<fieldset style="padding:3px;">
				<input class="input-text regular-input " type="checkbox" name="wf_australia_post_enabled" id="wf_australia_post_enabled" style="" value="yes" <?php echo ( !isset($general_settings['enabled']) || isset($general_settings['enabled']) && 'yes' === $general_settings['enabled'] ) ? 'checked' : ''; ?> placeholder=""> <?php esc_html_e('Enable Real time Rates', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Enable real time rates, if you want to fetch rates from Australia Post API in Cart/Checkout page.', 'wf-shipping-auspost'); ?>"></span>
			</fieldset>
			<fieldset style="padding:3px;" id="gst_include_exclude">
				<?php 
				if (isset($general_settings['include_exclude_gst']) &&  'exclude' == $general_settings['include_exclude_gst'] ) {                        
					$exclude = 'checked';
					$include = '';

				} else {
					$include = 'checked';
					$exclude = '';                        
				}
				?>
				<input class="input-text regular-input " type="radio" name="wf_australia_post_include_exclude_gst" <?php echo( esc_attr( $include ) ); ?> id="wf_australia_post_include_gst"  value="include" placeholder="" > 
				<?php esc_html_e('Show Rates Included GST', 'wf-shipping-auspost'); ?>
				<input class="input-text regular-input " <?php echo( esc_attr( $exclude ) ); ?> type="radio" name="wf_australia_post_include_exclude_gst" id="wf_australia_post_exclude_gst"  value="exclude" placeholder=""> 
				<?php esc_html_e('Show Rates Excluded GST', 'wf-shipping-auspost'); ?>
				<br>
			</fieldset>
			<fieldset style="padding:3px;" id="aus_enable_shipping_label">
				<input class="input-text regular-input " type="checkbox" name="wf_australia_post_enabled_label" id="wf_australia_post_enabled_label" style="" value="yes" <?php echo ( !isset($general_settings['enabled_label']) || isset($general_settings['enabled_label']) && 'yes' === $general_settings['enabled_label'] ) ? 'checked' : ''; ?> placeholder=""> <?php esc_html_e('Enable Shipping Label (Contracted)', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Enable Shipping label is available only if you have enabled Contracted. This option enables label creation from the order admin page. Disabling this will hide the label creating button.', 'wf-shipping-auspost'); ?>"></span>
			</fieldset>
			<fieldset style="padding:3px;">
				<input class="input-text regular-input " type="checkbox" name="wf_australia_post_debug_mode" id="wf_australia_post_debug_mode" style="" value="yes" <?php echo ( isset($general_settings['debug_mode']) && 'yes' === $general_settings['debug_mode'] ) ? 'checked' : ''; ?> placeholder=""> <?php esc_html_e('Enable Developer Mode', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Enabling Developer’s mode will let you troubleshoot the plugin. Request/Response information would be available in the Cart/Checkout page.', 'wf-shipping-auspost'); ?>"></span>
			</fieldset>
			<?php
			if (class_exists('wf_vendor_addon_setup')) {
				?>
			<fieldset style="padding:3px;">
				<input class="input-text regular-input " type="checkbox" name="wf_australia_post_vendor_check" id="wf_australia_post_vendor_check" style="" value="yes" <?php echo ( isset($general_settings['vendor_check']) && 'yes' === $general_settings['vendor_check'] ) ? 'checked' : ''; ?> placeholder=""> <?php esc_html_e('Enable Multi-Vendor ', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('You are seeing this option becuase ELEX Multi-Vendor Shipping Add-On is installed. By enabling this option, Shipper Adress set in multi-vendor plugin settings will be overriden by the below Shipper Address settings.', 'wf-shipping-auspost'); ?>"></span>
			</fieldset>
			<?php
			}
			?>
		</td>
	</tr>
	<tr valign="top">
		<td style="width:40%;font-weight:700;">
			<label for="wf_australia_post_"><?php esc_html_e('Default Currency', 'wf-shipping-auspost'); ?></label>
		</td>
		<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
			<fieldset style="padding:3px;">
				<?php 
				$selected_currency = 'AUD';
				$default_currency  = get_woocommerce_currency();
				?>
				<label for="wf_australia_post_"><?php echo '<b>' . esc_html( $selected_currency ) . ' (' . get_woocommerce_currency_symbol($selected_currency) . ')</b>'; ?></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Australian Dollars in the default currency.', 'wf-shipping-auspost'); ?>"></span><br/>
			</fieldset>
<!--    
	<?php 
	if ($selected_currency != $default_currency) {
		?>
				<fieldset style="padding:3px;">
				<label for="wf_australia_post_conversion_rate"><?php esc_html_e('Converstion Rate', 'wf-shipping-auspost'); ?></label> <span class="woocommerce-help-tip" data-tip="Enter the conversion rate from your Australia Post (AUD) currency to your store <?php echo '(' . esc_attr( $default_currency ) . ')'; ?> currency. "></span> <br/>   
				<input class="input-text regular-input " type="number" min="0" step="0.00001" name="wf_australia_post_conversion_rate" id="wf_australia_post_conversion_rate" style="" value="<?php echo ( isset($general_settings['conversion_rate']) ) ? esc_attr( $general_settings['conversion_rate'] ) : ''; ?>" placeholder=""><b> <?php echo esc_html( $default_currency ); ?></b>
				</fieldset>
				<?php
	}
	?>
	-->     
			
		</td>
	</tr>
	<tr valign="top">
		<td style="width:40%;font-weight:700;">
			<label for="wf_australia_post_"><?php esc_html_e('Shipper Address', 'wf-shipping-auspost'); ?></label>
		</td>
		<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">

			<table>
				<tr>
					<td>
						<fieldset style="padding-left:3px;">
							<label for="wf_australia_post_"><?php esc_html_e('Shipper Name', 'wf-shipping-auspost'); ?><font style="color:red;">*</font></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Name of the person responsible for shipping.', 'wf-shipping-auspost'); ?>"></span>  <br/>
							<input class="input-text regular-input " type="text" name="wf_australia_post_origin_name" id="wf_australia_post_origin_name" style="" value="<?php echo ( isset($general_settings['origin_name']) ) ? esc_attr( $general_settings['origin_name'] ) : ''; ?>" placeholder="">  
						</fieldset>
				</td>
				<td>

						<fieldset style="padding-left:3px;">

							<label for="wf_australia_post_origin_suburb"><?php esc_html_e('Suburb', 'wf-shipping-auspost'); ?><font style="color:red;">*</font></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('City of the shipper.', 'wf-shipping-auspost'); ?>"></span>   <br/>
							<input class="input-text regular-input " type="text" name="wf_australia_post_origin_suburb" id="wf_australia_post_origin_suburb" style="" value="<?php echo ( isset($general_settings['origin_suburb']) ) ? esc_attr( $general_settings['origin_suburb'] ): ''; ?>" placeholder="">
						</fieldset>
				
					</td>
					
				</tr>
				<tr>
				<td>

						<fieldset style="padding-left:3px;">

							<label for="wf_australia_post_origin_state"><?php esc_html_e('State Code', 'wf-shipping-auspost'); ?><font style="color:red;">*</font></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('State Code of the shipper.', 'wf-shipping-auspost'); ?>"></span>  <br/>
							<input class="input-text regular-input " type="text" name="wf_australia_post_origin_state" id="wf_australia_post_origin_state" style="" value="<?php echo ( isset($general_settings['origin_state']) ) ? esc_attr( $general_settings['origin_state'] ): ''; ?>" placeholder="">
						</fieldset>
				
					</td>
								
				<td>

						<fieldset style="padding-left:3px;">
							<label for="wf_australia_post_"><?php esc_html_e('Address', 'wf-shipping-auspost'); ?><font style="color:red;">*</font></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Official address of the shipper.', 'wf-shipping-auspost'); ?>"></span>   <br> 
							<input class="input-text regular-input " type="text" name="wf_australia_post_origin_line" id="wf_australia_post_origin_line" style="" value="<?php echo ( isset($general_settings['origin_line']) ) ? esc_attr( $general_settings['origin_line'] ): ''; ?>" placeholder="">  
						</fieldset>

					</td>
				</tr>
				<tr>
				
					<td>

						<fieldset style="padding-left:3px;">
							<label for="wf_australia_post_"><?php esc_html_e('Phone Number', 'wf-shipping-auspost'); ?><font style="color:red;">*</font></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Phone number of the shipper.', 'wf-shipping-auspost'); ?>"></span>  <br/>
							<input class="input-text regular-input " type="text" name="wf_australia_post_shipper_phone_number" id="wf_australia_post_shipper_phone_number" style="" value="<?php echo ( isset($general_settings['shipper_phone_number']) ) ? esc_attr( $general_settings['shipper_phone_number'] ) : ''; ?>" placeholder="">  
						</fieldset>
					</td>
					<td>
						<fieldset style="padding-left:3px;">
							<label for="wf_australia_post_"><?php esc_html_e('Email Address', 'wf-shipping-auspost'); ?></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Email address of the shipper.', 'wf-shipping-auspost'); ?>"></span> <br/>
							<input class="input-text regular-input " type="text" name="wf_australia_post_shipper_email" id="wf_australia_post_shipper_email" style="" value="<?php echo ( isset($general_settings['shipper_email']) ) ? esc_attr ( $general_settings['shipper_email'] ) : ''; ?>" placeholder="">  
						</fieldset>
					</td>
				</tr>
				<tr>
					<td>
					<fieldset style="padding-left:3px;">

						<label for="wf_australia_post_origin"><?php esc_html_e('Postal Code', 'wf-shipping-auspost'); ?><font style="color:red;">*</font></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Postal code of the shipper(Used for fetching rates and label generation).', 'wf-shipping-auspost'); ?>"></span><br/>
						<input class="input-text regular-input " type="text" name="wf_australia_post_origin" id="wf_australia_post_origin" style="" value="<?php echo ( isset($general_settings['origin']) ) ? esc_attr( $general_settings['origin'] ): ''; ?>" placeholder="">
					</fieldset>
				
					</td>

				</tr>
			</tr>
		</table>
	</td>
</tr>
<?php
if (in_array('elex-australia-post-for-woocommerce-bulk-printing-labels-addon/elex-australia-post-for-woocommerce-bulk-printing-labels-addon.php', get_option('active_plugins'))) {
	?>
	<tr>
		<td colspan="2">
			<h3><?php esc_html_e( 'ELEX Australia Post Bulk Label Printing Add-On Settings', 'wf-shipping-auspost' ); ?></h3>
			<p><?php esc_html_e( 'Get your credentials by logging in to <a href="https://developer.ilovepdf.com/login" target="_blank">iLovePDF site</a>. Free subscription allows 250 files to be downloaded per month. For higher plans checkout their <a href="https://developer.ilovepdf.com/pricing" target="_blank">pricing page</a>.', 'wf-shipping-auspost' ); ?></p>
		</td>
	</tr>
	<tr>
		<td style="width:40%;font-weight:700;">
				<label for="wf_australia_post_"><?php esc_html_e('Project Key', 'wf-shipping-auspost'); ?></label>
		</td>
		<td>
			<fieldset style="padding-left:3px;">
				<input class="input-text regular-input " type="text" name="addon_bulk_printing_project_key" id="addon_bulk_printing_project_key" style="" value="<?php echo ( isset($general_settings['addon_bulk_printing_project_key']) ) ? esc_html( $general_settings['addon_bulk_printing_project_key'] ) : ''; ?>" placeholder="" size="40">  
			</fieldset>
		</td>
	</tr>
	<tr>
		<td style="width:40%;font-weight:700;">
				<label for="wf_australia_post_"><?php esc_html_e('Secret Key', 'wf-shipping-auspost'); ?></label>
		</td>
		<td>
			<fieldset style="padding-left:3px;">
				<input class="input-text regular-input " type="text" name="addon_bulk_printing_secret_key" id="addon_bulk_printing_secret_key" style="" value="<?php echo ( isset($general_settings['addon_bulk_printing_secret_key']) ) ? esc_attr( $general_settings['addon_bulk_printing_secret_key'] ) : ''; ?>" placeholder="" size="40">  
			</fieldset>
		</td>
	</tr>
<?php
}
?>
<tr>
	<td colspan="2" style="text-align:right;">
		<button type="submit" class="button button-primary" name="wf_aus_genaral_save_changes_button"> <?php esc_html_e('Save Changes', 'wf-shipping-auspost'); ?> </button>       
	</td>
</tr>
</table>
<script type="text/javascript">
		
		jQuery(document).ready(function($){
			jQuery('html').on('click',function(e){
				jQuery(window).off('beforeunload');
				window.onbeforeunload = null;
			});
			jQuery('#wf_australia_post_contracted_rates').change(function(){
				if(jQuery('#wf_australia_post_contracted_rates').is(':checked')) {

					jQuery("#wf_australia_post_api_key").prop('required',true);
					jQuery("#wf_australia_post_api_pwd").prop('required',true);
					jQuery("#wf_australia_post_api_account_no").prop('required',true);
					jQuery('#starTrack_account_rates_select_field').show();
					jQuery('#aus_live_test').show();
					jQuery('#startrack_service_title_auspost_elex').hide();
					jQuery('#aus_pass').show();
					jQuery('#aus_acc_num').show();
					jQuery('#aus_enable_shipping_label').show();
					jQuery('#starTrack_pass').hide();
					jQuery('#starTrack_acc_num').hide();
					jQuery('#auspost_service_title_auspost_elex').show();
					if(jQuery('#wf_australia_post_starTrack_rates').is(':checked')) {
						jQuery('#starTrack_account_rates_select_field').show();
						jQuery('#aus_live_test').show();
						jQuery('#aus_enable_shipping_label').show();
						jQuery('#starTrack_pass').show();
						jQuery('#startrack_service_title_auspost_elex').show();
						jQuery('#starTrack_acc_num').show();
						jQuery("#wf_australia_post_api_pwd").prop('required',false);
						jQuery("#wf_australia_post_api_account_no").prop('required',false);
						jQuery('#auspost_service_title_auspost_elex').show();
						jQuery('.elex_australia_post_starTrack_api_key_field').show();
					}
				}else {
					jQuery("#wf_australia_post_api_key").prop('required',false);
					jQuery("#wf_australia_post_api_pwd").prop('required',false);
					jQuery("#wf_australia_post_api_account_no").prop('required',false);
					jQuery('#aus_live_test').hide();
					jQuery('#aus_pass').hide();
					jQuery('#aus_acc_num').hide();
					jQuery('#startrack_service_title_auspost_elex').hide();
					jQuery('#aus_enable_shipping_label').hide();
					jQuery('#starTrack_account_rates_select_field').hide();
					jQuery('#starTrack_pass').hide();
					jQuery('#starTrack_acc_num').hide();
					jQuery('#aus_live_test').hide();
					jQuery('#auspost_service_title_auspost_elex').hide();
					jQuery('.elex_australia_post_starTrack_api_key_field').hide();
				}
			}).change();

			jQuery('#wf_australia_post_starTrack_rates').change(function(){
				if(jQuery('#wf_australia_post_starTrack_rates').is(':checked') && jQuery('#wf_australia_post_contracted_rates').is(':checked')) {
					jQuery("#wf_australia_post_contracted_api_mode").prop('required',true);
					jQuery("#wf_australia_post_starTrack_api_password").prop('required',true);
					jQuery("#wf_australia_post_starTrack_api_account_no").prop('required',true);
					jQuery('#aus_live_test').show();
					jQuery('#startrack_service_title_auspost_elex').show();
					jQuery('#aus_enable_shipping_label').show();
					jQuery("#wf_australia_post_api_pwd").prop('required',false);
					jQuery("#wf_australia_post_api_account_no").prop('required',false);
					jQuery('#starTrack_acc_num').show();
					jQuery('#starTrack_pass').show();
					jQuery('#auspost_service_title_auspost_elex').show();
					jQuery('.elex_australia_post_starTrack_api_key_field').show();
				}else if(jQuery('#wf_australia_post_contracted_rates').is(':checked')) {
					jQuery('#aus_live_test').show();
					jQuery('#aus_pass').show();
					jQuery('#aus_enable_shipping_label').show();
					jQuery('#aus_acc_num').show();
					jQuery('#starTrack_acc_num').hide();
					jQuery('#starTrack_pass').hide();
					jQuery('#startrack_service_title_auspost_elex').hide();
					jQuery("#wf_australia_post_api_pwd").prop('required',true);
					jQuery("#wf_australia_post_api_account_no").prop('required',true);
					jQuery("#wf_australia_post_starTrack_api_password").prop('required',false);
					jQuery("#wf_australia_post_starTrack_api_account_no").prop('required',false);
					jQuery('#auspost_service_title_auspost_elex').show();
					jQuery('.elex_australia_post_starTrack_api_key_field').hide();
				}
			}).change();

			

			jQuery('#wf_australia_post_enabled').change(function(){
				if(jQuery('#wf_australia_post_enabled').is(':checked')) {
					jQuery("#gst_include_exclude").show();
				}else{
					jQuery("#gst_include_exclude").hide();
				}
			}).change();
			if(jQuery('#wf_australia_post_enabled').is(':checked')) {
				jQuery("#gst_include_exclude").show();
			}
		});
		jQuery(window).load(function(){
			jQuery('#wf_australia_post_starTrack_api_key_selected').change(function(){
				if(jQuery(this).is(':checked') && jQuery('#wf_australia_post_starTrack_rates').is(':checked') && jQuery('#wf_australia_post_contracted_rates').is(':checked')){
					jQuery('.elex_australia_post_starTrack_api_key_input_field').show();
				}else{
					jQuery('.elex_australia_post_starTrack_api_key_input_field').hide();
				}
			}).change();
		
		});

</script>
