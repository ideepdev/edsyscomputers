<tr valign="top" style="border-style: solid;">
	<td style="width:30%;font-weight:800;">
		<label for="wf_australia_post_"><?php esc_html_e('Method Config', 'wf-shipping-auspost'); ?></label>
	</td>
	<td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
		<label for="wf_australia_post_title"><?php esc_html_e('Method Title / Availability', 'wf-shipping-auspost'); ?></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Provide the service name which would be reflected in the Cart/Checkout page if the cheapest rate has been enabled.', 'wf-shipping-auspost'); ?>"></span>
		<fieldset style="padding:3px;">
			<input class="input-text regular-input " type="text" name="wf_australia_post_title" id="wf_australia_post_title" style="" value="<?php echo ( isset( $general_settings['title'] ) && ''!==$general_settings['title'] ) ? esc_attr( $general_settings['title'] ) : esc_attr( 'MyPost Business', 'wf-shipping-auspost' ); ?>" placeholder=""> 
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
		<strong><?php esc_html_e( 'MyPost Business Services', 'wf-shipping-auspost' ); ?></strong><br><br>
		<style>
			#tiptip_content
			{
				max-width:unset !important;
			}

			.australia_post_services {
				margin-bottom: 2%;
			}
		</style>
		<?php if (isset($general_settings['client_account_name'] ) && '' !== $general_settings['client_account_name']) { ?>
		
				<table class="australia_post_services widefat">
					<thead>
						<th class="sort">&nbsp;</th>
						<th style="text-align:center; padding: 10px; width:45%;"><?php esc_html_e( 'Service', 'wf-shipping-auspost' ); ?></th>
						<th style="text-align:center; padding: 10px; width:5%;"><?php esc_html_e( 'Enable', 'wf-shipping-auspost' ); ?></th>
						<th style="text-align:center; padding: 10px; width:5%;"><?php esc_html_e( 'Name', 'wf-shipping-auspost' ); ?></th>
						<th style="text-align:center; padding: 10px; width:5%;"><?php esc_html_e( 'Extra Cover', 'wf-shipping-auspost' ); ?></th>
						<th style="text-align:center; padding: 10px; width:5%;"><?php esc_html_e( 'Signature on Delivery', 'wf-shipping-auspost' ); ?></th>
						<th><?php echo sprintf( __( 'Adjustment (%s)', 'wf-shipping-auspost' ), get_woocommerce_currency_symbol() ); ?></th>
						<th><?php esc_html_e( 'Adjustment (%)', 'wf-shipping-auspost' ); ?></th>
					</thead>
					<tbody>
						<?php

							$settings                                    = get_option('woocommerce_wf_australia_mypost_settings');
							$contracted_account_details                  = '';
							$available_authority_to_leave_services_array = array();
							$international_services_array                = array();
							$postage_products                            = array();
							$get_accounts_endpoint                       = '';

							$postage_products = $this->services;

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
										<td style="text-align:center"><input type="checkbox" name="australia_post_service[<?php echo esc_attr( $code ); ?>][enabled]" <?php checked( ( ! isset( $this->custom_services[ $code ]['enabled'] ) || ! empty( $this->custom_services[ $code ]['enabled'] ) ), true ); ?> /></td>
										<td><input type="text" name="australia_post_service[<?php echo esc_attr( $code ); ?>][name]" placeholder="<?php echo esc_attr( $name ); ?> (<?php echo esc_attr( $this->title ); ?>)" value="<?php echo ( isset( $this->custom_services[ $code ]['name'] ) && !empty( $this->custom_services[ $code ]['name'] ) ) ? esc_attr( $this->custom_services[ $code ]['name'] ) : esc_attr( $name ); ?>" size="50" /></td>
										<td style="text-align:center">

											<input type="checkbox" name="australia_post_service[<?php echo esc_attr( $code ); ?>][extra_cover]" <?php checked( ( ! isset( $this->custom_services[ $code ]['extra_cover'] ) || ! empty( $this->custom_services[ $code ]['extra_cover'] ) ), true ); ?> />
									
										</td>
										<td style="text-align:center">
											<input type="checkbox" name="australia_post_service[<?php echo esc_attr( $code ); ?>][signature_on_delivery_option]" <?php checked( ( ! isset( $this->custom_services[ $code ]['signature_on_delivery_option'] ) || ! empty( $this->custom_services[ $code ]['signature_on_delivery_option'] ) ), true ); ?> />
										</td>

										<td><input type="text" name="australia_post_service[<?php echo esc_attr( $code ); ?>][adjustment]" placeholder="N/A" value="<?php echo isset( $this->custom_services[ $code ]['adjustment'] ) ? esc_attr( $this->custom_services[ $code ]['adjustment'] ) : ''; ?>" size="4" /></td>
										<td><input type="text" name="australia_post_service[<?php echo esc_attr( $code ); ?>][adjustment_percent]" placeholder="N/A" value="<?php echo isset( $this->custom_services[ $code ]['adjustment_percent'] ) ? esc_attr( $this->custom_services[ $code ]['adjustment_percent'] ) : ''; ?>" size="4" /></td>
									</tr>
									<?php
							}
						}
						?>
					</tbody>
				</table>
		<?php 
		} else {

				echo '<div class="error">
				<p>' . esc_html('Message: We Observed, You have not set up MyPost Business Account on Reachship Yet!', 'wf-shipping-auspost') . '</p>
				</div>';
		} 
		?>
	</td>
</tr>


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
		
