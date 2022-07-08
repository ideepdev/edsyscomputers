<?php $this->contracted_rates = $this->get_option('contracted_rates') == 'yes' ? true : false; ?>
<tr valign="top" style="border-style: solid;">
	<td style="width:30%;font-weight:800;">
		<label for="wf_australia_post_"><?php esc_html_e('Method Config', 'wf-shipping-auspost'); ?></label>
	</td>
	<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
		<label for="wf_australia_post_title"><?php esc_html_e('Method Title / Availability', 'wf-shipping-auspost'); ?></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Provide the service name which would be reflected in the Cart/Checkout page if the cheapest rate has been enabled.', 'wf-shipping-auspost'); ?>"></span>
		<fieldset style="padding:3px;">
			<input class="input-text regular-input " type="text" name="wf_australia_post_title" id="wf_australia_post_title" style="" value="<?php echo ( isset( $general_settings['title'] ) ) ? esc_attr( $general_settings['title'] ) : esc_attr( 'Australia Post', 'wf-shipping-auspost' ); ?>" placeholder=""> 
		</fieldset>
		<fieldset style="padding:3px;">
			<?php 
			if (isset($general_settings['availability']) && 'specific' === $general_settings['availability'] ) { 
				?>
			<input class="input-text regular-input " type="radio" name="wf_australia_post_availability"  id="wf_australia_post_availability1" value="all" placeholder=""> <?php esc_html_e('Supports All Countries', 'wf-shipping-auspost'); ?>
			<input class="input-text regular-input " type="radio"  name="wf_australia_post_availability" checked=true id="wf_australia_post_availability2"  value="specific" placeholder=""> <?php esc_html_e('Supports Specific Countries', 'wf-shipping-auspost'); ?>
			<?php } else { ?>
			<input class="input-text regular-input " type="radio" name="wf_australia_post_availability" checked=true id="wf_australia_post_availability1"  value="all" placeholder=""> <?php esc_html_e('Supports All Countries', 'wf-shipping-auspost'); ?>
			<input class="input-text regular-input " type="radio" name="wf_australia_post_availability" id="wf_australia_post_availability2"  value="specific" placeholder=""> <?php esc_html_e('Supports Specific Countries', 'wf-shipping-auspost'); ?>
			<?php } ?>
		</fieldset>
		<fieldset style="padding:3px;" id="aus_specific">
			<label for="wf_australia_post_countries"><?php esc_html_e('Specific Countries', 'wf-shipping-auspost'); ?></label> <span class="woocommerce-help-tip" data-tip="<?php esc_html_e('You can select the shipping method to be available for all countries or selective countries.', 'wf-shipping-auspost'); ?>"></span><br/>

			<select class="chosen_select" multiple="true" name="wf_australia_post_countries[]" >
				<?php 
				$woocommerce_countries = $woocommerce->countries->get_countries();
				$selected_country      =  ( isset($general_settings['countries']) && !empty($general_settings['countries']) ) ? $general_settings['countries'] : array($woocommerce->countries->get_base_country());

				
				foreach ($woocommerce_countries as $key => $value) {
					if (in_array($key, $selected_country)) {
						echo '<option value="' . esc_attr( $key ) . '" selected>' . esc_html( $value ) . '</option>';
					}
					echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $value ) . '</option>';
				}
				?>
			</select>
		</fieldset>
	</td>
</tr>
<tr valign="top" id="service_options" >
	<td class="forminp"  colspan="2" style="padding-left:0px">
		<strong><?php esc_html_e( 'Australia Post Services', 'wf-shipping-auspost' ); ?></strong><br><br>
		<style>
			#tiptip_content
			{
				max-width:unset !important;
			}

			.australia_post_services {
				margin-bottom: 2%;
			}
		</style>
		<table class="australia_post_services widefat">
			<thead>
				<th class="sort">&nbsp;</th>
				<th style="text-align:center; padding: 10px; width:45%;"><?php esc_html_e( 'Service', 'wf-shipping-auspost' ); ?></th>
				<?php 
				if (!$this->contracted_rates) { 
					?>
							<th style="text-align:center; padding: 10px; width:45%;"><?php esc_html_e( 'Satchel/Letter Service', 'wf-shipping-auspost' ); ?></th>
						<?php   
				}
				?>
				<th style="text-align:center; padding: 10px; width:5%;"><?php esc_html_e( 'Enable', 'wf-shipping-auspost' ); ?></th>
				<th><?php esc_html_e( 'Name', 'wf-shipping-auspost' ); ?></th>
				<th style="text-align:center; padding: 10px; width:5%;"><?php esc_html_e( 'Extra Cover', 'wf-shipping-auspost' ); ?></th>
				<?php 
				if ($this->contracted_rates) { 
					?>
							<th style="text-align:center; padding: 10px; width:5%;"><?php esc_html_e( 'Signature on Delivery', 'wf-shipping-auspost' ); ?></th>
							<th style="text-align:center; padding: 10px; width:5%;"><?php esc_html_e( 'Authority to leave', 'wf-shipping-auspost' ); ?></th>
							<th style="text-align:center; padding: 10px; width:5%;"><?php esc_html_e( 'Allow partial delivery', 'wf-shipping-auspost' ); ?></th>
						<?php   
				} else {
					?>
							<th style="text-align:center; padding: 10px; width:5%;"><?php esc_html_e( 'Signature / Registered', 'wf-shipping-auspost' ); ?></th>
						<?php
				}
				?>
				<th><?php echo sprintf( __( 'Adjustment (%s)', 'wf-shipping-auspost' ), get_woocommerce_currency_symbol() ); ?></th>
				<th><?php esc_html_e( 'Adjustment (%)', 'wf-shipping-auspost' ); ?></th>
			</thead>
			<tbody>
				<?php
					 

					$settings                                    = get_option('woocommerce_wf_australia_post_settings');
					$contracted_account_details                  = '';
					$extra_cover                                 = array();
					$signature_on_delivery                       = array();
					$available_authority_to_leave_services_array = array();
					$international_services_array                = array();
					$postage_products                            = array();
					$get_accounts_endpoint                       = '';

				if ($this->contracted_rates) {
					if (!empty($this->api_account_no_auspost) && !empty($this->api_pwd_auspost)) {
						$contracted_account_details = '';
						$get_accounts_endpoint      = 'https://' . self::API_HOST . self::API_BASE_URL . 'accounts/' . $this->api_account_no_auspost;

						if ( 'live' == $this->contracted_api_mode ) {
							$get_accounts_endpoint = str_replace('test/', '', $get_accounts_endpoint);
						}

						$contracted_account_details = $this->get_services($get_accounts_endpoint, $this->api_account_no_auspost, $this->api_pwd_auspost);
						$contracted_account_details = json_decode($contracted_account_details, true);
						

						if (!empty($contracted_account_details)) {
							if (isset($contracted_account_details['postage_products']) && !empty($contracted_account_details['postage_products'])) {
								$postage_products = $contracted_account_details['postage_products'];
								update_option('auspost_contracted_postage_products', $postage_products);
								if (is_array($postage_products)) {
									$postage_products_service_options = array();
									foreach ($postage_products as $postage_product) {
										if (isset($postage_product['features']['TRANSIT_COVER'])) {
												$extra_cover[$postage_product['product_id']] = $postage_product['features']['TRANSIT_COVER']['attributes']['maximum_cover'];
												$postage_products_service_options[$postage_product['product_id']]['transit_cover']['available']     = true;
												$postage_products_service_options[$postage_product['product_id']]['transit_cover']['maximum_cover'] = $postage_product['features']['TRANSIT_COVER']['attributes']['maximum_cover']?? 0; 
												$postage_products_service_options[$postage_product['product_id']]['transit_cover']['rate']          = $postage_product['features']['TRANSIT_COVER']['attributes']['rate']?? 0; 
										}
										if (isset($postage_product['options']) && isset($postage_product['options']['authority_to_leave_option']) && true === $postage_product['options']['authority_to_leave_option'] ) {
												$available_authority_to_leave_services_array[$postage_product['product_id']] = $postage_product['type'];
										}
										if (isset($postage_product['options']) && isset($postage_product['options']['signature_on_delivery_option']) && true === $postage_product['options']['signature_on_delivery_option'] ) {
											$signature_on_delivery[$postage_product['product_id']] = $postage_product['type'];
										}
										if (!isset($postage_product['group'])) {
											$international_services_array[$postage_product['product_id']] = $postage_product['type'];
												
										}
										$postage_products_service_options[$postage_product['product_id']]['options']     = $postage_product['options']?? false;
										$postage_products_service_options[$postage_product['product_id']]['is_domestic'] = isset($postage_product['group'])? 'yes':false; 
									}
									update_option('elex_auspost_contracted_postage_products_feature_and_options', $postage_products_service_options);
								}  
							} elseif ($contracted_account_details['errors']) {
								$auspost_customer_account_error = $contracted_account_details['errors'];
								update_option('auspost_customer_account_error', $auspost_customer_account_error['0']['name'] . ' : ' . $auspost_customer_account_error['0']['message']);
							}
						} else {
							echo '<div class="error">
                                    <p>' . esc_html('No Australia Post( E-parcel & Startrack) Services returned at this point of time. Please check your internet or check the credentials you have entered.', 'wf-shipping-auspost') . '</p>
                                </div>';
						}
					}
				} else {
					$postage_products      = $this->services;
					$extra_cover           = $this->extra_cover;
					$signature_on_delivery = $this->delivery_confirmation;
				}
					
					$name                   = '';
					$product_id             = '';
					$sort                   = 0;
					$this->ordered_services = array();
				if (is_array($postage_products) && !empty($postage_products)) {
					foreach ( $postage_products as $code => $values ) {

						if ( is_array( $values ) ) {
							if (isset($values['name'])) {
								$name = $values['name'];
							} else {
								$name       = $values['type'];
								$product_id = $values['product_id'];                                
							}
						} else {
							$name = $values;
						}

						if ( isset( $this->custom_services[ $code ] ) && isset( $this->custom_services[ $code ]['order'] ) ) {
							$sort = $this->custom_services[ $code ]['order'];
						}

						while ( isset( $this->ordered_services[ $sort ] ) ) {
							$sort++;
						}

						$other_service_codes = isset( $values['alternate_services'] ) ? $values['alternate_services'] : '';

						if (isset($values['name'])) {
							$this->ordered_services[ $sort ] = array( $code, $name, $other_service_codes );
						} else {
							$this->ordered_services[ $sort ] = array( $product_id, $name, $other_service_codes );
						}

						$sort++;
					}
				}

					ksort( $this->ordered_services );
					
				if (is_array($this->ordered_services) && !empty($this->ordered_services)) {
					foreach ( $this->ordered_services as $value ) {
						$code                = $value[0];
						$name                = $value[1];
						$other_service_codes = array_filter( (array) $value[2] );
						?>
							<tr>
								<td class="sort"><input type="hidden" class="order" name="australia_post_service[<?php echo esc_attr( $code ); ?>][order]" value="<?php echo isset( $this->custom_services[ $code ]['order'] ) ? esc_attr( $this->custom_services[ $code ]['order'] ) : ''; ?>" /></td>
								<td style="text-align:left;">
								<?php
									echo '<strong>' . esc_html( $name ) . '</strong>';
								?>
											
								</td>
								<?php 
								if (!$this->contracted_rates) { 
									?>
										<td>
											<ul>
												<?php foreach ($other_service_codes as $other_service_code_key => $other_service_code_value) { ?>
													<li>
														<input type="checkbox" name="auspost_sub_services[<?php echo esc_attr( $other_service_code_key ); ?>][enabled]" value="yes" <?php echo ( isset($settings['sub_services'][$other_service_code_key]['enabled']) && true == $settings['sub_services'][$other_service_code_key]['enabled'] ) ? 'checked' : ''; ?>/>
														<input type="text" name="subservices_name[<?php echo esc_attr( $other_service_code_key ); ?>]" value="<?php echo !empty($settings['sub_services'][$other_service_code_key]['name'])? esc_attr( $settings['sub_services'][$other_service_code_key]['name'] ): ''; ?>" placeholder="<?php echo str_replace('_', ' ', str_replace( $code . '_', '', $other_service_code_value['name'] )); ?>">
													</li>
										<?php } ?>
											</ul>
										</td>
							<?php 
								}
								?>
				
								<td style="text-align:center"><input type="checkbox" name="australia_post_service[<?php echo esc_attr( $code ); ?>][enabled]" <?php checked( ( ! isset( $this->custom_services[ $code ]['enabled'] ) || ! empty( $this->custom_services[ $code ]['enabled'] ) ), true ); ?> /></td>
								<td><input type="text" name="australia_post_service[<?php echo esc_attr( $code ); ?>][name]" placeholder="<?php echo esc_attr( $name ); ?> (<?php echo esc_attr( $this->title ); ?>)" value="<?php echo ( isset( $this->custom_services[ $code ]['name'] ) && !empty( $this->custom_services[ $code ]['name'] ) ) ? esc_attr( $this->custom_services[ $code ]['name'] ) : esc_attr( $name ); ?>" size="50" /></td>
								<td style="text-align:center">
								<?php 
								if ( in_array( $code, array_keys( $extra_cover ) ) ) : 
									?>
											<input type="checkbox" name="australia_post_service[<?php echo esc_attr( $code ); ?>][extra_cover]" <?php checked( ( ! isset( $this->custom_services[ $code ]['extra_cover'] ) || ! empty( $this->custom_services[ $code ]['extra_cover'] ) ), true ); ?> />
										<?php endif; ?>
								</td>
								<?php 
								if ($this->contracted_rates) {
									if ( in_array( $name, $available_authority_to_leave_services_array ) ) { 
										?>
												<td style="text-align:center">
														
												</td>
												<td style="text-align:center">
													<input type="checkbox" name="australia_post_service[<?php echo esc_attr( $code ); ?>][authority_to_leave]" <?php checked( ( ! isset( $this->custom_services[ $code ]['authority_to_leave'] ) || ! empty( $this->custom_services[ $code ]['authority_to_leave'] ) ), true ); ?> />
												</td>
												<td style="text-align:center">
													<input type="checkbox" name="australia_post_service[<?php echo esc_attr( $code ); ?>][allow_partial_delivery]" <?php checked( ( ! isset( $this->custom_services[ $code ]['allow_partial_delivery'] ) || ! empty( $this->custom_services[ $code ]['allow_partial_delivery'] ) ), true ); ?> />
												</td>
											<?php
									} elseif (in_array( $name, $international_services_array ) ) {
										if (in_array( $name, $signature_on_delivery )) {
											?>
														<td style="text-align:center">
															<input type="checkbox" name="australia_post_service[<?php echo esc_attr( $code ); ?>][signature_on_delivery_option]" <?php checked( ( ! isset( $this->custom_services[ $code ]['signature_on_delivery_option'] ) || ! empty( $this->custom_services[ $code ]['signature_on_delivery_option'] ) ), true ); ?> />
														</td>
												<?php
										} else {
											?>
														<td style="text-align:center">
														
														</td>
											<?php
										}
										?>
													<td style="text-align:center">
														
													</td>
													<td style="text-align:center">
														
													</td>
											<?php
									} else {
										?>
												<td style="text-align:center">
														
												</td>
												<td style="text-align:center">
													<input type="checkbox" name="australia_post_service[<?php echo esc_attr( $code ); ?>][authority_to_leave]"  checked disabled="true" style="pointer-events: none;"/>
												</td>
												<td style="text-align:center">
													<input type="checkbox" name="australia_post_service[<?php echo esc_attr( $code ); ?>][allow_partial_delivery]" checked  disabled="true" style="pointer-events: none;"/>
												</td>
											<?php 
									}
								} elseif ( in_array( $code, $signature_on_delivery ) ) {
									?>
											<td style="text-align:center">
												<input type="checkbox" name="australia_post_service[<?php echo esc_attr( $code ); ?>][delivery_confirmation]" <?php checked( ( ! isset( $this->custom_services[ $code ]['delivery_confirmation'] ) || ! empty( $this->custom_services[ $code ]['delivery_confirmation'] ) ), true ); ?> />
											</td>
										<?php 
								} else {
									?>
											<td style="text-align:center">
											   
											</td>
									<?php 
								}
								?>
								 
								
								<td><input type="text" name="australia_post_service[<?php echo esc_attr( $code ); ?>][adjustment]" placeholder="N/A" value="<?php echo isset( $this->custom_services[ $code ]['adjustment'] ) ? esc_attr( $this->custom_services[ $code ]['adjustment'] ) : ''; ?>" size="4" /></td>
								<td><input type="text" name="australia_post_service[<?php echo esc_attr( $code ); ?>][adjustment_percent]" placeholder="N/A" value="<?php echo isset( $this->custom_services[ $code ]['adjustment_percent'] ) ? esc_attr( $this->custom_services[ $code ]['adjustment_percent'] ) : ''; ?>" size="4" /></td>
							</tr>
							<?php
					}
				}
				?>
			</tbody>
		</table>
	</td>
</tr>
<!-- StarTrack Services table -->
<?php if ($this->contracted_rates && isset($this->settings['wf_australia_post_starTrack_rates']) && 'yes' == $this->settings['wf_australia_post_starTrack_rates'] ) : ?>
<tr valign="top">
	<td style="width:30%;font-weight:400;">
		<label for="wf_australia_post_"><?php esc_html_e('Method Config', 'wf-shipping-auspost'); ?></label>
	</td>
	<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
		<label for="wf_starTrack_title"><?php esc_html_e('Method Title / Availability', 'wf-shipping-auspost'); ?></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Provide the service name which would be reflected in the Cart/Checkout page if the cheapest rate has been enabled.', 'wf-shipping-auspost'); ?>"></span>
		<?php
		if (isset($this->general_settings['wf_australia_post_starTrack_rates']) && 'yes' == $this->general_settings['wf_australia_post_starTrack_rates'] ) { 
			?>
				<fieldset style="padding:3px;">
					<input class="input-text regular-input " type="text" name="wf_australia_startrack_post_title" id="wf_australia_startrack_post_title" style="" value="<?php echo ( isset($general_settings['elex_stratrack_title']) ) ? esc_attr( $general_settings['elex_stratrack_title'] ) : esc_attr( 'StarTrack', 'wf-shipping-auspost' ); ?>" placeholder=""> 
				</fieldset>
	<?php } ?>
		
	</td>
</tr>
<tr>
	<td class="forminp"  colspan="2" style="padding-left:0px">
		<strong style="margin-top: 10%"><?php esc_html_e( 'StarTrack Services', 'wf-shipping-auspost' ); ?></strong><br><br>
		<style>
			#tiptip_content
			{
				max-width:unset !important;
			}
		</style>
		<table class="australia_post_services widefat">
			<thead>
				<th class="sort">&nbsp;</th>
				<th style="text-align:center; padding: 10px; width:45%;"><?php esc_html_e( 'Service', 'wf-shipping-auspost' ); ?></th>
				<?php 
				if (!$this->contracted_rates) { 
					?>
						<th style="text-align:center; padding: 10px; width:45%;"><?php esc_html_e( 'Satchel/Letter Service', 'wf-shipping-auspost' ); ?></th>
			<?php 
				}
				?>
				<th style="text-align:center; padding: 10px; width:5%;"><?php esc_html_e( 'Enable', 'wf-shipping-auspost' ); ?></th>
				<th><?php esc_html_e( 'Name', 'wf-shipping-auspost' ); ?></th>
				<th style="text-align:center; padding: 10px; width:5%;"><?php esc_html_e( 'Extra Cover', 'wf-shipping-auspost' ); ?></th>
				<th style="text-align:center; padding: 10px; width:5%;"><?php esc_html_e( 'Signature / Registered', 'wf-shipping-auspost' ); ?></th>
				<th><?php echo sprintf( __( 'Adjustment (%s)', 'wf-shipping-auspost' ), get_woocommerce_currency_symbol() ); ?></th>
				<th><?php esc_html_e( 'Adjustment (%)', 'wf-shipping-auspost' ); ?></th>
			</thead>
			<tbody>
				<?php

					
					$settings                        = get_option('woocommerce_wf_australia_post_settings');
					$contracted_account_details      = '';
					$extra_cover                     = array();
					$signature_on_delivery           = array();
					$postage_products                = array();
					$get_accounts_endpoint           = '';
					$signature_on_delivery_startrack = array();

				if ($this->contracted_rates) {
					$get_accounts_endpoint = 'https://' . self::API_HOST . self::API_BASE_URL . 'accounts/' . $this->api_account_no_starTrack;

					if ( 'live' == $this->contracted_api_mode ) {
						$get_accounts_endpoint = str_replace('test/', '', $get_accounts_endpoint);
					}

					if (!empty($this->api_account_no_starTrack)) {
						$contracted_account_details = $this->get_services($get_accounts_endpoint, $this->api_account_no_starTrack, $this->api_pwd_starTrack, $this->api_key_starTrack);
						$contracted_account_details = json_decode($contracted_account_details, true);

						if (!empty($contracted_account_details)) {
							if (isset($contracted_account_details['postage_products']) && !empty($contracted_account_details['postage_products'])) {
								$postage_products = $contracted_account_details['postage_products'];
								if (is_array($postage_products)) {
									if (isset($this->general_settings['wf_australia_post_starTrack_rates_selected']) && ( true == $this->general_settings['wf_australia_post_starTrack_rates_selected'] )) {
										update_option('starTrack_postage_products', $postage_products);
									}

									foreach ($postage_products as $postage_product) {
										if (isset($postage_product['features']['TRANSIT_COVER'])) {
											$extra_cover[$postage_product['product_id']] = $postage_product['features']['TRANSIT_COVER']['attributes']['maximum_cover'];
										}

										if (isset($postage_product['options'])) {
											if ( true === $postage_product['options']['signature_on_delivery_option'] ) {
												$signature_on_delivery_startrack[$postage_product['product_id']] = $postage_product['type'];
											}
										}
									}
								}  
							} elseif ($contracted_account_details['errors']) {
								$auspost_customer_account_error = $contracted_account_details['errors'];
								update_option('auspost_customer_account_error', $auspost_customer_account_error['0']['name'] . ' : ' . $auspost_customer_account_error['0']['message']);
							}
						} else {
							echo '<div class="error">
                                    <p>' . esc_html('No Services at this point of time. Please check your internet.', 'wf-shipping-auspost') . '</p>
                                </div>';
						}
					}
				}
					
					$name                   = '';
					$product_id             = '';
					$sort                   = 0;
					$this->ordered_services = array();

				if (is_array($postage_products) && !empty($postage_products)) {
					foreach ( $postage_products as $code => $values ) {

						if ( is_array( $values ) ) {
							if (isset($values['name'])) {
								$name = $values['name'];
							} else {
								$name       = $values['type'];
								$product_id = $values['product_id'];                                
							}
						} else {
							$name = $values;
						}

						if ( isset( $this->custom_startrack_services[ $code ] ) && isset( $this->custom_startrack_services[ $code ]['order'] ) ) {
							$sort = $this->custom_startrack_services[ $code ]['order'];
						}

						while ( isset( $this->ordered_services[ $sort ] ) ) {
							$sort++;
						}

						$other_service_codes = isset( $values['alternate_services'] ) ? $values['alternate_services'] : '';

						if (isset($values['name'])) {
							$this->ordered_services[ $sort ] = array( $code, $name, $other_service_codes );
						} else {
							$this->ordered_services[ $sort ] = array( $product_id, $name, $other_service_codes );
						}

						$sort++;
					}
				}

					ksort( $this->ordered_services );


				if (is_array($this->ordered_services) && !empty($this->ordered_services)) {
					foreach ( $this->ordered_services as $value ) {
						$code                = $value[0];
						$name                = $value[1];
						$other_service_codes = array_filter( (array) $value[2] );
						?>
							<tr>
								<td class="sort"><input type="hidden" class="order" name="startrack_service[<?php echo esc_attr( $code ); ?>][order]" value="<?php echo isset( $this->custom_startrack_services[ $code ]['order'] ) ? esc_attr( $this->custom_startrack_services[ $code ]['order'] ): esc_attr( $name ); ?>" /></td>
								<td style="text-align:left;">
								<?php
									echo '<strong>' . esc_html( $name ) . '</strong>';
								?>
											
								</td>
								<td style="text-align:center"><input type="checkbox" name="startrack_service[<?php echo esc_attr( $code ); ?>][enabled]" <?php checked( ( ! isset( $this->custom_startrack_services[ $code ]['enabled'] ) || ! empty( $this->custom_startrack_services[ $code ]['enabled'] ) ), true ); ?> /></td>
								<td><input type="text" name="startrack_service[<?php echo esc_attr( $code ); ?>][name]" placeholder="<?php echo esc_attr( $name ); ?> (<?php echo esc_attr( $this->title ); ?>)" value="<?php echo ( isset( $this->custom_startrack_services[ $code ]['name'] ) && !empty( $this->custom_startrack_services[ $code ]['name'] ) ) ? esc_attr( $this->custom_startrack_services[ $code ]['name'] ) : esc_attr( $name ); ?>" size="50" /></td>
								<td style="text-align:center">
									<?php if ( in_array( $code, array_keys( $extra_cover ) ) ) : ?>
										<input type="checkbox" name="startrack_service[<?php echo esc_attr( $code ); ?>][extra_cover]" <?php checked( ( ! isset( $this->custom_startrack_services[ $code ]['extra_cover'] ) || ! empty( $this->custom_startrack_services[ $code ]['extra_cover'] ) ), true ); ?> />
									<?php endif; ?>
								</td>
								<td style="text-align:center">
									<?php 
									if ($this->contracted_rates) {
										if ( in_array( $name, $signature_on_delivery_startrack ) ) : 
											?>
											<input type="checkbox" name="startrack_service[<?php echo esc_attr( $code ); ?>][delivery_confirmation]" <?php checked( ( ! isset( $this->custom_startrack_services[ $code ]['delivery_confirmation'] ) || ! empty( $this->custom_startrack_services[ $code ]['delivery_confirmation'] ) ), true ); ?> />
										<?php 
										endif; 
									} elseif ( in_array( $code, $signature_on_delivery_startrack ) ) {
										?>
											<input type="checkbox" name="startrack_service[<?php echo esc_attr( $code ); ?>][delivery_confirmation]" <?php checked( ( ! isset( $this->custom_startrack_services[ $code ]['delivery_confirmation'] ) || ! empty( $this->custom_startrack_services[ $code ]['delivery_confirmation'] ) ), true ); ?> />
									<?php } ?> 
								</td>
								<td><input type="text" name="startrack_service[<?php echo esc_attr( $code ); ?>][adjustment]" placeholder="N/A" value="<?php echo isset( $this->custom_startrack_services[ $code ]['adjustment'] ) ? esc_attr( $this->custom_startrack_services[ $code ]['adjustment'] ) : ''; ?>" size="4" /></td>
								<td><input type="text" name="startrack_service[<?php echo esc_attr( $code ); ?>][adjustment_percent]" placeholder="N/A" value="<?php echo isset( $this->custom_startrack_services[ $code ]['adjustment_percent'] ) ? esc_attr( $this->custom_startrack_services[ $code ]['adjustment_percent'] ) : ''; ?>" size="4" /></td>
							</tr>
							<?php
					}
				}
				?>
			</tbody>
		</table>
	</td>
</tr>
<?php endif; ?>

<script type="text/javascript">

	jQuery(window).load(function(){
		
		jQuery('#wf_australia_post_availability1').change(function(){
			jQuery('#aus_specific').hide();
		}).change();

		jQuery('#wf_australia_post_availability2').change(function(){
			if(jQuery('#wf_australia_post_availability2').is(':checked')) {
				jQuery('#aus_specific').show();
			}else
			{
				jQuery('#aus_specific').hide();
			}
		}).change();


	});
	jQuery(document).ready(function(){
		
		jQuery('html').on('click',function(e){
			jQuery(window).off('beforeunload');
			window.onbeforeunload = null;
		});
	});

</script>
		
