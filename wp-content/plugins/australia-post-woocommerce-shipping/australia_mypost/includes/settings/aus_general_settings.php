<?php 
global $woocommerce;

if (isset($_POST['wf_aus_genaral_save_changes_button'])) {
	$wc_main_settings                         = get_option('woocommerce_wf_australia_mypost_settings');
	$wc_main_settings['client_id']       	  = ( isset($_POST['wf_australia_mypost_client_id']) ) ? sanitize_text_field($_POST['wf_australia_mypost_client_id']) : '';
	$wc_main_settings['client_secret']        = ( isset($_POST['wf_australia_mypost_client_secret']) ) ? sanitize_text_field($_POST['wf_australia_mypost_client_secret']) : '';
	$wc_main_settings['conversion_rate']      = ( isset($_POST['wf_australia_post_conversion_rate']) ) ? sanitize_text_field($_POST['wf_australia_post_conversion_rate']) : '';
	$wc_main_settings['origin_name']          = ( isset($_POST['wf_australia_post_origin_name']) ) ? sanitize_text_field($_POST['wf_australia_post_origin_name']) : '';
	$wc_main_settings['origin_suburb']        = ( isset($_POST['wf_australia_post_origin_suburb']) ) ? sanitize_text_field($_POST['wf_australia_post_origin_suburb']) : '';
	$wc_main_settings['origin_line']          = ( isset($_POST['wf_australia_post_origin_line']) ) ? sanitize_text_field($_POST['wf_australia_post_origin_line']) : '';
	$wc_main_settings['origin_state']         = ( isset($_POST['wf_australia_post_origin_state']) ) ? sanitize_text_field($_POST['wf_australia_post_origin_state']) : '';
	$wc_main_settings['origin']               = ( isset($_POST['wf_australia_post_origin']) ) ? sanitize_text_field($_POST['wf_australia_post_origin']) : '';
	$wc_main_settings['shipper_phone_number'] = ( isset($_POST['wf_australia_post_shipper_phone_number']) ) ? sanitize_text_field($_POST['wf_australia_post_shipper_phone_number']) : '';
	$wc_main_settings['shipper_email']        = ( isset($_POST['wf_australia_post_shipper_email']) ) ? sanitize_text_field($_POST['wf_australia_post_shipper_email']) : '';
	$wc_main_settings['enabled']              = ( isset($_POST['wf_australia_post_enabled']) ) ? 'yes' : '';
	$wc_main_settings['enabled_label']        = ( isset($_POST['wf_australia_post_enabled_label']) ) ? 'yes' : '';
	$wc_main_settings['debug_mode']           = ( isset($_POST['wf_australia_post_debug_mode']) ) ? 'yes' : ''; 
	$wc_main_settings['include_exclude_gst']  = ( isset($_POST['wf_australia_post_include_exclude_gst']) )? $_POST['wf_australia_post_include_exclude_gst'] : 'include';
	if (in_array('elex-australia-post-for-woocommerce-bulk-printing-labels-addon/elex-australia-post-for-woocommerce-bulk-printing-labels-addon.php', get_option('active_plugins'))) {
		$wc_main_settings['addon_bulk_printing_project_key'] = ( isset($_POST['addon_bulk_printing_project_key']) ) ? sanitize_text_field($_POST['addon_bulk_printing_project_key']) : '';
		$wc_main_settings['addon_bulk_printing_secret_key']  = ( isset($_POST['addon_bulk_printing_secret_key']) ) ? sanitize_text_field($_POST['addon_bulk_printing_secret_key']) : '';
	}
	update_option('woocommerce_wf_australia_mypost_settings', $wc_main_settings);
}

$general_settings = get_option('woocommerce_wf_australia_mypost_settings');
$general_settings = empty($general_settings) ? array() : $general_settings;

if (isset($_POST['wf_australia_mypost_client_connect'])) {

	$client_id                    = ( isset($_POST['wf_australia_mypost_client_id']) ) ? sanitize_text_field($_POST['wf_australia_mypost_client_id']) : '';
	$client_secret                = ( isset($_POST['wf_australia_mypost_client_secret']) ) ? sanitize_text_field($_POST['wf_australia_mypost_client_secret']) : '';
	$service_base_url             = 'https://' . self::API_HOST . self::API_BASE_URL . 'oauth/token';
	$service_get_carrier_base_url = 'https://' . self::API_HOST . self::API_BASE_URL . 'get-carrier-credentials-summary';

	if (isset( $general_settings['mode_check'] ) && 'live' === $general_settings['mode_check']) {
		$service_base_url             = str_replace('sandbox/', 'production/', $service_base_url);
		$service_get_carrier_base_url = str_replace('sandbox/', 'production/', $service_get_carrier_base_url);
	}

	$rqs_headers      = array(
		'Accept' => 'application/json',
	);
	$arg              = array(
		'grant_type' => 'client_credentials',
		'client_id' => $client_id,
		'client_secret' => $client_secret,
	);
	$service_base_url = $service_base_url . '?' . http_build_query($arg);
	$res              = wp_remote_get($service_base_url, array(
		'method' => 'GET',
		'headers' => $rqs_headers,
	));

	$res_body_decode = array();
	if ( is_wp_error($res) ) {
		$error_string = $res->get_error_message();
		echo '<div class="error">
		<p>' . esc_html('Message:' . $error_string, 'wf-shipping-auspost') . '</p>
		</div>';
	} else {
		$res_body_decode = json_decode($res['body']);
	}
	$general_settings = get_option('woocommerce_wf_australia_mypost_settings');
	
	if ( !empty($res_body_decode) && 200 == $res['response']['code'] ) {
		$access_token = ( isset($res_body_decode->access_token) ) ? $res_body_decode->access_token : '';
		if ('' !== $access_token) {
			set_transient( 'wf_australia_mypost_access_token', $access_token, DAY_IN_SECONDS );
			$general_settings['access_token']  = $access_token;
			$general_settings['client_id']     = $client_id;
			$general_settings['client_secret'] = $client_secret;
			/** Get Carrier Credentials Summary */
			$rqs_headers = array(
				'Accept' => 'application/json',
				'Authorization' => 'Bearer ' . $general_settings['access_token']
			);

			$res = wp_remote_get($service_get_carrier_base_url, array(
				'method' => 'GET',
				'headers' => $rqs_headers,
			));

			$res_body_decode = array();

			if ( is_wp_error($res) ) {
				$error_string = $res->get_error_message();
				echo '<div class="error">
				<p>' . esc_html('Message:' . $error_string, 'wf-shipping-auspost') . '</p>
				</div>';
			} else {
				$res_body_decode = json_decode($res['body']);
			}
			if ( !empty($res_body_decode) && 200 == $res['response']['code']) {
				
				foreach ($res_body_decode as $key => $account_details) {

					if ( 'AUSPOST_MYPOST' == $account_details->carrier_id && $account_details->is_primary ) {
						$general_settings['client_account_name'] = $account_details->account_name;
					}
				}
			
			} else {
				if (!empty($res_body_decode)) {
					if ( isset($res_body_decode->errors) ) {
						$errors_message = $res_body_decode->errors[0]->message;
						echo '<div class="error">
						<p>' . esc_html( $errors_message, 'wf-shipping-auspost') . '</p>
						</div>';
					} else {
						$errors_message = $res_body_decode->message;
						echo '<div class="error">
						<p>' . esc_html( $errors_message, 'wf-shipping-auspost') . '</p>
						</div>';
					}
				}
				$general_settings['client_account_name'] = '';
			}
		}
	} else {
		if (!empty($res_body_decode)) {

			if ( isset($res_body_decode->errors) ) {
				$errors_message = $res_body_decode->errors[0]->message;
				echo '<div class="error">
				<p>' . esc_html( $errors_message, 'wf-shipping-auspost') . '</p>
				</div>';
			} else {
				$errors_message = $res_body_decode->message;
				echo '<div class="error">
				<p>' . esc_html( $errors_message, 'wf-shipping-auspost') . '</p>
				</div>';
			}
		}

		$general_settings['client_id']     = '';
		$general_settings['client_secret'] = '';
		set_transient( 'wf_australia_mypost_access_token', '', DAY_IN_SECONDS );
		$general_settings['access_token']        = '';
		$general_settings['client_account_name'] = '';
	}
	update_option('woocommerce_wf_australia_mypost_settings', $general_settings);
}
if (isset($_POST['wf_australia_mypost_client_disconnect'])) {

	$general_settings['client_id']     = '';
	$general_settings['client_secret'] = '';
	set_transient( 'wf_australia_mypost_access_token', '', DAY_IN_SECONDS );
	$general_settings['access_token']        = '';
	$general_settings['client_account_name'] = '';
	update_option('woocommerce_wf_australia_mypost_settings', $general_settings);
}
?>

<img style="float:right;" src="<?php echo WF_AUSTRALIA_POST_URL . 'australia_mypost/includes/settings/aus.png'; ?>" width="180" height="" />
<table style="font-size:13px">
	
	<tr valign="top">
		<td colspan="2" style="width:35%;font-weight:850;">
			<label for="wf_australia_mypost_api_mode"><?php esc_html_e('Connect MyPost Business via ReachShip', 'wf-shipping-auspost'); ?> </label><span class="woocommerce-help-tip" data-tip="<?php echo esc_attr('You can connect to MyPost Business through ReachShip. ReachShip is a SaaS-based platform to get shipping rates and print shipment labels from popular shipping carriers. To learn more about ReachShip <a href="https://reachship.com/" target="_blank" >Click here</a>', 'wf-shipping-auspost'); ?>"></span>
		</td>
	</tr>

	<tr valign="top">
		<td style="width:35%;font-weight:700;">
			<label for="wf_australia_mypost_client_id"><?php esc_html_e('ReachShip Client Id', 'wf-shipping-auspost'); ?> </label>
		</td>
		<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
			<fieldset style="padding:3px;">
				<input class="input-text regular-input " size="35" type="text" name="wf_australia_mypost_client_id" id="wf_australia_mypost_client_id"  value="<?php echo ( isset($general_settings['client_id']) ) ? esc_attr( $general_settings['client_id'] ) : ''; ?>" placeholder="Please your Client ID"> 
			</fieldset>
		</td>
	</tr>
	<tr valign="top">
		<td style="width:35%;font-weight:700;">
			<label for="wf_australia_mypost_client_secret"><?php esc_html_e('ReachShip Client Secret', 'wf-shipping-auspost'); ?> </label>
		</td>
		<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
			<fieldset style="padding:3px;">
				<input class="input-text regular-input " size="35" type="password" name="wf_australia_mypost_client_secret" id="wf_australia_mypost_client_secret"  value="<?php echo ( isset($general_settings['client_secret']) ) ? esc_attr( $general_settings['client_secret'] ) : ''; ?>" placeholder="Please your Client Secret"> 
				<?php if ('' == $general_settings['access_token']) { ?>
					<label for="wf_australia_post_ac_num"><?php echo esc_html('Check', 'wf-shipping-auspost') . ' <a href="https://ship.reachship.com/subscriber/api-settings" target="_blank" >' . esc_html('ReachShip - My Account Section', 'wf-shipping-auspost') . '</a> ' . esc_html('for API Credentials', 'wf-shipping-auspost'); ?></label>
				<?php } ?>
				</fieldset>
			<?php if ( isset($general_settings['access_token']) && ''!==$general_settings['access_token'] ) { ?>
				<fieldset style="padding:3px;">
				<input class="button button-primary" type="submit" name="wf_australia_mypost_client_disconnect" id="wf_australia_mypost_client_disconnect"  value="Disconnect"> 
				</fieldset>
			<?php } else { ?>
				<fieldset style="padding:3px;">
				<input class="button button-primary" type="submit" name="wf_australia_mypost_client_connect" id="wf_australia_mypost_client_connect"  value="Connect"> 
				</fieldset>
			<?php } ?>
		</td>
	</tr>
	<?php if ( '' == $general_settings['access_token'] ) { ?>
		<tr valign="top">
			<td style="width:35%;font-weight:700;">
				<label for="wf_australia_mypost_add_account"><?php esc_html_e('Don`t have ReachShip Account?', 'wf-shipping-auspost'); ?> </label>
			</td>
			<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">

				<fieldset style="padding:3px;">
					<a class="button input-text regular-input"  href ="https://ship.reachship.com/signup" target="_blank"><?php esc_html_e('Create ReachShip Account', 'wf-shipping-auspost'); ?></a> <label for="wf_australia_post_ac_num"><?php echo esc_html('Refer', 'wf-shipping-auspost') . ' <a href=" https://reachship.com/knowledge-base/create-your-reachship-account/"  target="_blank">' . esc_html('Step By Step Instructions', 'wf-shipping-auspost') . '</a> ' . esc_html('on how to set up.', 'wf-shipping-auspost'); ?></label>
				</fieldset>
			</td>
		</tr>
	<?php } ?>					
		<?php 
		if ('' !== $general_settings['client_id'] && '' !== $general_settings['client_secret']) {
			if ( '' == $general_settings['client_account_name'] ) { 
				?>
				<tr valign="top">
					<td colspan="2" style="font-weight:700;">
						<label for="wf_australia_mypost_add_carrier"><?php esc_html_e('We Observed, You have not set up MyPost Business Account on Reachship Yet!', 'wf-shipping-auspost'); ?> </label>
					</td>
				</tr>
				<tr valign="top" >
					<td style="width:35%;font-weight:700;">
						<a class="button input-text regular-input button-primary" href ="https://ship.reachship.com/subscriber/shipping-settings" target="_blank"><?php esc_html_e('Set up MyPost Business Account Now', 'wf-shipping-auspost'); ?></a>
					</td>
				</tr>
			<?php 
			} else { 
				?>
			<tr valign="top">
				<td scope="row" style="width:35%;font-weight:700;">
					<label for="wf_australia_mypost_carrier_merchant_token"><?php esc_html_e('Connected Account Name', 'wf-shipping-auspost'); ?> </label>
				</td>
				<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
					<label for="wf_australia_mypost_carrier_merchant_token"><?php esc_html_e($general_settings['client_account_name'], 'wf-shipping-auspost'); ?> </label>
				</td>
			</tr>
			<?php 
			}
		}
		?>

	<tr valign="top">
		<td style="width:35%;font-weight:850;padding-top: 10px;">
			<label for="wf_australia_mypost_api_mode"><?php esc_html_e('MyPost Business Settings', 'wf-shipping-auspost'); ?> </label>
		</td>
	</tr>
	<tr valign="top">
		<td style="width:35%;font-weight:700; padding-top: 10px;">
			<label for="wf_australia_post_rates"><?php esc_html_e('Enable/Disable', 'wf-shipping-auspost'); ?></label>
		</td>
		<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px; padding-top: 10px;">
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
				<input class="input-text regular-input " type="checkbox" name="wf_australia_post_enabled_label" id="wf_australia_post_enabled_label" style="" value="yes" <?php echo ( !isset($general_settings['enabled_label']) || isset($general_settings['enabled_label']) && 'yes' === $general_settings['enabled_label'] ) ? 'checked' : ''; ?> placeholder=""> <?php esc_html_e('Enable Shipping Label ', 'wf-shipping-auspost'); ?> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Enabling this checkbox will display an option to create shipping labels from MyPost business on the order admin page. Keep it unchecked to hide the label creation button. Please note this will work only if the merchant token is authorized.', 'wf-shipping-auspost'); ?>"></span>
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
		<td style="width:35%;font-weight:700;">
			<label for="wf_australia_post_"><?php esc_html_e('Default Currency', 'wf-shipping-auspost'); ?></label>
		</td>
		<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
			<fieldset style="padding:3px;">
				<?php 
				$selected_currency = 'AUD';
				$default_currency  = get_woocommerce_currency();
				?>
				<label for="wf_australia_post_"><?php echo '<b>' . esc_html( $selected_currency ) . ' (' . get_woocommerce_currency_symbol($selected_currency) . ')</b>'; ?></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Please note, Australian Dollars should be the default currency to be able to access these services.', 'wf-shipping-auspost'); ?>"></span><br/>
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
		<td style="width:35%;font-weight:700;">
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

		
		});

</script>
