	<strong><?php esc_html_e( 'Box Sizes', 'wf-shipping-auspost' ); ?></strong><br><br>
		<style type="text/css">
			.aus_boxes td, .aus_services td {
				vertical-align: middle;
				padding: 4px 7px;
			}
			.aus_services th, .aus_boxes th {
				padding: 9px 7px;
			}
			.aus_boxes td input {
				margin-right: 4px;
			}
			.aus_boxes .check-column {
				vertical-align: middle;
				text-align: left;
				padding: 0 7px;
			}
			.aus_services th.sort {
				width: 16px;
				padding: 0 16px;
			}
			.aus_services td.sort {
				cursor: move;
				width: 16px;
				padding: 0 16px;
				cursor: move;
				background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAAHUlEQVQYV2O8f//+fwY8gJGgAny6QXKETRgEVgAAXxAVsa5Xr3QAAAAASUVORK5CYII=) no-repeat center;
			}
		</style>
				<?php
				$pack_type = array(
					'PD' => __('Pre-Defined', 'wf-shipping-auspost'),
					'YP' => __('Your Pack', 'wf-shipping-auspost'),
				);

				$box_eligible_for = array(
					'DO' => __('Domestic', 'wf-shipping-auspost'),
					'IN' => __('International', 'wf-shipping-auspost'),
				);

				$option_string = '';
				if (is_array($pack_type) && !empty($pack_type)) {
					foreach ($pack_type as $k => $v) {
						$selected       = ( 'YP' == $k )? 'selected' : '';
						$option_string .='<option value="' . $k . '" ' . $selected . '>' . $v . '</option>';
					}
				}

				$auspost_box_packing_error = '';

				if ( '' != $auspost_box_packing_error ) {
					$error       = $auspost_box_packing_error;
					$option_name = 'wf_auspost_boxpacking_error';
					if ( get_option( $option_name ) !== false ) {
						update_option( $option_name, $error_desc );
					} else {
						$deprecated = null;
						$autoload   = 'no';
						add_option( $option_name, $error_desc, $deprecated, $autoload );
					}
				}
				
				?>
		<table class="aus_boxes widefat">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" /></th>
					<th><?php esc_html_e( 'Name', 'wf-shipping-auspost' ); ?></th>
					<th><?php esc_html_e( 'Outer Length', 'wf-shipping-auspost' ); ?></th>
					<th><?php esc_html_e( 'Outer Width', 'wf-shipping-auspost' ); ?></th>
					<th><?php esc_html_e( 'Outer Height', 'wf-shipping-auspost' ); ?></th>
					<th><?php esc_html_e( 'Inner Length', 'wf-shipping-auspost' ); ?></th>
					<th><?php esc_html_e( 'Inner Width', 'wf-shipping-auspost' ); ?></th>
					<th><?php esc_html_e( 'Inner Height', 'wf-shipping-auspost' ); ?></th>
					<th><?php esc_html_e( 'Box Weight', 'wf-shipping-auspost' ); ?></th>
					<th><?php esc_html_e( 'Max Weight', 'wf-shipping-auspost' ); ?></th>
					<th><?php esc_html_e( 'Enabled', 'wf-shipping-auspost' ); ?></th>
					<th><?php esc_html_e( 'Package Type', 'wf-shipping-auspost' ); ?></th>
					<th type="hidden"></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th colspan="3">
						<a href="#" class="plus insert button button-secondary" style="vertical-align: center;"><?php esc_html_e( 'Add Box', 'wf-shipping-auspost' ); ?></a>
						<a href="#" class=" minus remove button button-secondary"><?php esc_html_e( 'Remove selected box(es)', 'wf-shipping-auspost' ); ?></a>
					</th>
					<th colspan="9">
						<small class="description"><?php esc_html_e( 'Preloaded the Dimension and Weight in unit Centimetre and Kilogram respectively. If you have selected unit as Inches and Pound please convert it accordingly.', 'wf-shipping-auspost' ); ?></small>
					</th>
				</tr>
				<tr>
					<th colspan="12">
						<small class="description"><?php esc_html_e( ' Length of a box should not exceed 105 cm. For Domestic shipments the volume of a box should not exceed 0.25 cubic meters and weight should not exceed 22 kg. For International shipments the girth of the box should lie between 16 cm and 140 cm and weight should not exceed 20 kg.', 'wf-shipping-auspost' ); ?></small>
					</th>
				</tr>
				<tr>
					<th colspan="12">
						<small class="description">
							<?php _e('EP - Express Post &nbsp;&nbsp;&nbsp;&nbsp; PP - Parcel Post &nbsp;&nbsp;&nbsp;&nbsp; IE - International Express &nbsp;&nbsp;&nbsp;&nbsp; IS - International Standard &nbsp;&nbsp;&nbsp;&nbsp; IE - International Economy &nbsp;&nbsp;&nbsp;&nbsp; RPI - Regitered Post International &nbsp;&nbsp;&nbsp;&nbsp; R - Regular &nbsp;&nbsp;&nbsp;&nbsp; WF - Window Face &nbsp;&nbsp;&nbsp;&nbsp; RP - Registered Post', 'wf-shipping-auspost'); ?>
						</small>
					</th>
				</tr>

			</tfoot>
			<tbody id="rates">
				<?php
				$count_pre_defined                  = 0;
				$ausmypost_stored_pre_defined_boxes = get_option('ausmypost_stored_pre_defined_boxes');
				$ausmypost_stored_custom_boxes      = get_option('ausmypost_stored_custom_boxes');

				$auspost_custom_boxes = array();
				$boxes_to_display     = array();
				if (empty($ausmypost_stored_pre_defined_boxes)) {
					$ausmypost_stored_pre_defined_boxes =  $this->pre_defined_boxes;
				} else {
					if (!empty($ausmypost_stored_custom_boxeses)) {
						foreach ($ausmypost_stored_custom_boxeses as $ausmypost_stored_custom_boxes) {
							if (isset($ausmypost_stored_custom_boxes['order']) && !empty($ausmypost_stored_custom_boxes['order'])) {
								$ausmypost_stored_pre_defined_boxes[] = $ausmypost_stored_custom_boxes;                    
							} else {
								$auspost_custom_boxes[] = $ausmypost_stored_custom_boxes;
							}
						}
					}
					//Sorting the boxes
					$ausmypost_stored_pre_defined_boxes = sort_pre_defined_boxes($ausmypost_stored_pre_defined_boxes);
				}

				$boxes_to_display = $ausmypost_stored_custom_boxes;

				// Displaying pre-defined boxes first
				$count_pre_defined = 0;

				if (!empty($ausmypost_stored_pre_defined_boxes)) {
					foreach ($ausmypost_stored_pre_defined_boxes as $key => $box) { 
						$box_name = $box['name']; 
						?>
						<tr>
							<td class="check-column"><input type="checkbox" /></td>
							<input type="hidden" size="1" name="boxes_id[<?php echo esc_attr( $count_pre_defined ); ?>]" value="<?php echo esc_attr( !empty($box['id'])?$box['id']:'' ); ?>" />
							<input type="hidden" size="1" name="boxes_order[<?php echo esc_attr( $count_pre_defined ); ?>]" value="<?php echo esc_attr( !empty($box['order'])?$box['order']:'' ); ?>" />
							<td><input type="text" size="25" name="boxes_name[<?php echo esc_attr( $count_pre_defined ); ?>]" value="<?php echo esc_attr( !empty($box['name'])?$box['name']:'' ); ?>" required="required" /></td>
							<td><input type="text" style="width:100%;" name="boxes_outer_length[<?php echo esc_attr( $count_pre_defined ); ?>]" value="<?php echo !empty($box['outer_length'])? esc_attr( $box['outer_length']):''; ?>" required="required" /></td>
							<td><input type="text" style="width:100%;" name="boxes_outer_width[<?php echo esc_attr( $count_pre_defined ); ?>]" value="<?php echo !empty($box['outer_width'])? esc_attr( $box['outer_width']):''; ?>" required="required" /></td>
							<td><input type="text" style="width:100%;" name="boxes_outer_height[<?php echo esc_attr( $count_pre_defined ); ?>]" value="<?php echo !empty($box['outer_height'])? esc_attr( $box['outer_height']):''; ?>" required="required" /></td>
						
							<td><input type="text" style="width:100%;" name="boxes_inner_length[<?php echo esc_attr( $count_pre_defined ); ?>]" value="<?php echo !empty($box['inner_length']) ? esc_attr( $box['inner_length'] ) : ''; ?>" required="required" /></td>
							<td><input type="text" style="width:100%;" name="boxes_inner_width[<?php echo esc_attr( $count_pre_defined ); ?>]" value="<?php echo !empty($box['inner_width']) ? esc_attr( $box['inner_width'] ) : ''; ?>" required="required" /></td>
							<td><input type="text" style="width:100%;" name="boxes_inner_height[<?php echo esc_attr( $count_pre_defined ); ?>]" value="<?php echo !empty($box['inner_height']) ? esc_attr( $box['inner_height'] ) : ''; ?>" required="required" /></td>
							
							<td><input type="text" style="width:100%;" name="boxes_box_weight[<?php echo esc_attr( $count_pre_defined ); ?>]" value="<?php echo !empty($box['box_weight']) ? esc_attr( $box['box_weight'] ) : 0; ?>" /></td>
							<td><input type="text" style="width:100%;" name="boxes_max_weight[<?php echo esc_attr( $count_pre_defined ); ?>]" value="<?php echo !empty($box['max_weight']) ? esc_attr( $box['max_weight'] ) : ''; ?>" required="required" /></td>
							<td><input type="checkbox" name="boxes_enabled[<?php echo esc_attr( $count_pre_defined ); ?>]" <?php checked( !empty($box['enabled'])?$box['enabled']:'', true ); ?> /></td>
							<?php 
						
							if ( 'PD' == $box['pack_type'] ) {
								?>
									<td>
										<select name="boxes_pack_type[<?php echo esc_attr( $count_pre_defined ); ?>]">
										<?php foreach ($pack_type as $k => $v) { ?>
											<option value="<?php echo esc_attr( $k ); ?>" 
																		<?php 
																		if ( $k == ( isset($box['pack_type']) ? $box['pack_type'] : '' ) ) :
																			?>
													selected="selected"<?php endif; ?>><?php echo esc_attr( $v ); ?> </option>
										<?php } ?>
										</select>
									</td>
								<?php
							} else {
								?>
									<td>
										<select name="boxes_pack_type[<?php echo esc_attr( $count_pre_defined ); ?>]" >
										<?php foreach ($pack_type as $k => $v) { ?>
											<option value="<?php echo  esc_attr( $k ); ?>" 
																		<?php 
																		if ( $k == ( isset($box['pack_type']) ? $box['pack_type'] : '' ) ) :
																			?>
													selected="selected"<?php endif; ?>><?php echo esc_attr( $v ); ?> </option>
										<?php } ?>
										</select>
									</td>
								<?php
							}
							

							?>
							<td>
								<input type="hidden" name="box_eligible_for[<?php echo esc_attr( $count_pre_defined ); ?>]" value="<?php echo ( isset($box['eligible_for']) && !empty($box['eligible_for']) )?  esc_attr( $box['eligible_for'] ): ''; ?>">
							</td>

						</tr> 
						<?php
						$count_pre_defined++;
					}
				}  
				// Displaying custom boxes first
				 $count_custom = $count_pre_defined;
				//$count_custom = 0;
				if (!empty($boxes_to_display)) {
					foreach ($boxes_to_display as $key => $box) {
						if (( 'YP' == $box['pack_type'] )) {
							$box_name = $box['name'];
							?>
							<tr>
								<td class="check-column"><input type="checkbox" /></td>
								<input type="hidden" size="1" class="box_settings_tab" name="boxes_id[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo esc_attr( !empty($box['id'])?$box['id']:'' ); ?>" />
								<input type="hidden" size="1" class="box_settings_tab" name="boxes_order[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo esc_attr( !empty($box['order'])?$box['order']:$count_custom ); ?>" />
								<td><input type="text" size="25" class="box_settings_tab" name="boxes_name[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo esc_attr( !empty($box['name'])?$box['name']:'' ); ?>" required="required"/></td>
								<td><input type="text" style="width:100%;" class="box_settings_tab" name="boxes_outer_length[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo !empty($box['outer_length'])? esc_attr( $box['outer_length']):'1'; ?>" required="required" /></td>
								<td><input type="text" style="width:100%;" class="box_settings_tab" name="boxes_outer_width[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo !empty($box['outer_width'])? esc_attr( $box['outer_width']):'1'; ?>" required="required" /></td>
								<td><input type="text" style="width:100%;" class="box_settings_tab" name="boxes_outer_height[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo !empty($box['outer_height'])? esc_attr( $box['outer_height']):'1'; ?>" required="required" /></td>
								<td><input type="text" style="width:100%;" class="box_settings_tab" name="boxes_inner_length[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo !empty($box['inner_length']) ? esc_attr( $box['inner_length'] ) : '1'; ?>" required="required" /></td>
								<td><input type="text" style="width:100%;" class="box_settings_tab" name="boxes_inner_width[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo !empty($box['inner_width']) ? esc_attr( $box['inner_width'] ) : '1'; ?>" required="required" /></td>
								<td><input type="text" style="width:100%;" class="box_settings_tab" name="boxes_inner_height[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo !empty($box['inner_height']) ? esc_attr( $box['inner_height'] ) : '1'; ?>" required="required" /></td>
								<td><input type="text" style="width:100%;" class="box_settings_tab" name="boxes_box_weight[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo !empty($box['box_weight']) ? esc_attr( $box['box_weight'] ) : 0; ?>" /></td>
								<td><input type="text" style="width:100%;" class="box_settings_tab" name="boxes_max_weight[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo !empty($box['max_weight']) ? esc_attr( $box['max_weight'] ) : ''; ?>" required="required" /></td>
								<td><input type="checkbox" class="box_settings_tab" name="boxes_enabled[<?php echo esc_attr( $count_custom ); ?>]" <?php checked( !empty($box['enabled'])?$box['enabled']:'', true ); ?> /></td>
								<?php 
							
								if ( 'PD' == $box['pack_type'] ) {
									?>
											<td>
												<select name="boxes_pack_type[<?php echo esc_attr( $count_custom ); ?>]">
											<?php foreach ($pack_type as $k => $v) { ?>
													<option value="<?php echo esc_attr( $k ); ?>" 
																				<?php 
																				if ( $k == ( isset($box['pack_type']) ? $box['pack_type'] : '' ) ) :
																					?>
															selected="selected"<?php endif; ?>><?php echo esc_attr( $v ); ?> </option>
												<?php } ?>
												</select>
											</td>
										<?php
								} else {
									?>
											<td>
												<select name="boxes_pack_type[<?php echo esc_attr( $count_custom ); ?>]" >
											<?php foreach ($pack_type as $k => $v) { ?>
													<option value="<?php echo esc_attr( $k ); ?>" 
																				<?php 
																				if ( $k == ( isset($box['pack_type']) ? $box['pack_type'] : '' ) ) :
																					?>
															selected="selected"<?php endif; ?>><?php echo esc_attr( $v ); ?> </option>
												<?php
											}
											?>
												</select>
											</td>
										<?php
								}
								 
								?>
									
							</tr> 
							<?php
						}
						$count_custom++;
					}
				}
				
				?>
			</tbody>
		</table>
		<script type="text/javascript">

			jQuery(window).load(function(){
				var pack_type_options = '<?php echo $option_string; ?>';
				console.log(pack_type_options);
				var packing_options_for_contracted = '<option value="YP"selected="selected" >Your Pack</option>';
				jQuery('#wf_aus_shipping_packing_method').change(function(){

					if ( jQuery(this).val() == 'box_packing' )
					{
						jQuery('#packing_options').show();
						jQuery('#packing_options_shp_pack_type').hide();
						jQuery('#packing_options_weight_packing_process').hide();
					}
					else if(jQuery(this).val() == 'per_item')
					{
						jQuery('#packing_options_shp_pack_type').show();
						jQuery('#packing_options_weight_packing_process').hide();
						jQuery('#packing_options').hide();
					}
					else
					{
						jQuery('#packing_options_shp_pack_type').hide();
						jQuery('#packing_options_weight_packing_process').show();
						jQuery('#packing_options').hide();
					}

				}).change();

				jQuery('.aus_boxes .insert').click( function() {
					var $tbody = jQuery('.aus_boxes').find('tbody');
					var size = $tbody.find('tr').size();
					var code = '<tr class="new">\
									<td class="check-column"><input type="checkbox" /></td>\
									<input type="hidden" size="1" class="box_settings_tab" name="boxes_id[' + size + ']" />\
									<input type="hidden" size="1" class="box_settings_tab" name="boxes_order[' + size + '] "/>\
									<td><input type="text" size="25" class="box_settings_tab" name="boxes_name[' + size + ']" required="required" /></td>\
									<td><input type="text" style="width:100%;" class="box_settings_tab" name="boxes_outer_length[' + size + ']" required="required" /></td>\
									<td><input type="text" style="width:100%;" class="box_settings_tab" name="boxes_outer_width[' + size + ']" required="required"/ ></td>\
									<td><input type="text" style="width:100%;" class="box_settings_tab" name="boxes_outer_height[' + size + ']" required="required" /></td>\
									<td><input type="text" style="width:100%;" class="box_settings_tab" name="boxes_inner_length[' + size + ']" required="required" /></td>\
									<td><input type="text" style="width:100%;" class="box_settings_tab" name="boxes_inner_width[' + size + ']" required="required" /></td>\
									<td><input type="text" style="width:100%;" class="box_settings_tab" name="boxes_inner_height[' + size + ']" required="required" /></td>\
									<td><input type="text" style="width:100%;" class="box_settings_tab" name="boxes_box_weight[' + size + ']" /></td>\
									<td><input type="text" style="width:100%;" class="box_settings_tab" name="boxes_max_weight[' + size + ']" required="required" /></td>\
									<td><input type="checkbox" class="box_settings_tab" name="boxes_enabled[' + size + ']" /></td>\\n\
									<td><select name="boxes_pack_type[' + size + ']" >' + pack_type_options + '</select></td>\
								</tr>';

					$tbody.append( code );

					return false;
				} );

				jQuery('.aus_boxes .remove').click(function() {
					var $tbody = jQuery('.aus_boxes').find('tbody');

					$tbody.find('.check-column input:checked').each(function() {
						jQuery(this).closest('tr').remove();
					});

					return false;
				});

				// Ordering
				jQuery('.aus_services tbody').sortable({
					items:'tr',
					cursor:'move',
					axis:'y',
					handle: '.sort',
					scrollSensitivity:40,
					forcePlaceholderSize: true,
					helper: 'clone',
					opacity: 0.65,
					placeholder: 'wc-metabox-sortable-placeholder',
					start:function(event,ui){
						ui.item.css('baclbsround-color','#f6f6f6');
					},
					stop:function(event,ui){
						ui.item.removeAttr('style');
						aus_services_row_indexes();
					}
				});

				function aus_services_row_indexes() {
					jQuery('.aus_services tbody tr').each(function(index, el){
						jQuery('input.order', el).val( parseInt( jQuery(el).index('.aus_services tr') ) );
					});
				};

			});

			jQuery(document).ready(function($){
				jQuery('html').on('click',function(e){
					jQuery(window).off('beforeunload');
					window.onbeforeunload = null;
				});
				jQuery("#wf_australia_post_api_pwd").validate({ 
					ignore: ':hidden',
				});

				jQuery("#wf_australia_post_api_account_no").validate({ 
					ignore: ':hidden',
				});
			});

		</script>
