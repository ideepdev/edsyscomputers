<style type="text/css">
			.aus_weight_boxes td, .aus_services td {
				vertical-align: middle;
				padding: 4px 7px;
			}
			.aus_services th, .aus_weight_boxes th {
				padding: 9px 7px;
			}
			.aus_weight_boxes td input {
				margin-right: 4px;
			}
			.aus_weight_boxes .check-column {
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
		<table class="aus_weight_boxes widefat" id="packing_options_weight_packing_process">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" /></th>
					<th><?php esc_html_e( 'Name', 'wf-shipping-auspost' ); ?></th>
					<th><?php esc_html_e( 'Length', 'wf-shipping-auspost' ); ?></th>
					<th><?php esc_html_e( 'Width', 'wf-shipping-auspost' ); ?></th>
					<th><?php esc_html_e( 'Height', 'wf-shipping-auspost' ); ?></th>
					<th><?php esc_html_e( 'Min Weight', 'wf-shipping-auspost' ); ?></th>
					<th><?php esc_html_e( 'Max Weight', 'wf-shipping-auspost' ); ?></th>
					<th><?php esc_html_e( 'Enabled', 'wf-shipping-auspost' ); ?></th>
					<th type="hidden"></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th colspan="3">
						<a href="#" class="plus insert button button-secondary" style="vertical-align: center;"><?php esc_html_e( 'Add Box', 'wf-shipping-auspost' ); ?></a>
						<a href="#" class=" minus remove button button-secondary"><?php esc_html_e( 'Remove selected box(es)', 'wf-shipping-auspost' ); ?></a>
					</th>
				</tr>
			</tfoot>
			<tbody id="rates">
				<?php

				$auspost_stored_custom_boxes = get_option('aus_mypost_stored_custom_boxes');
				$auspost_custom_boxes        = array();
				$boxes_to_display            = array();
				$boxes_to_display            = $auspost_stored_custom_boxes;
				$count_custom                = 0;
				if (!empty($boxes_to_display)) {
					foreach ($boxes_to_display as $key => $box) {
						?>
							<tr>
								<td class="check-column"><input type="checkbox" /></td>
								<input type="hidden" size="1" class="box_settings_tab" name="weight_boxes_id[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo esc_attr( isset($box['id'])?$box['id']:'' ); ?>" />
								<td><input type="text" size="25" class="box_settings_tab" name="weight_boxes_name[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo esc_attr( !empty($box['name'])?$box['name']:'' ); ?>" required="required"/></td>
								<td><input type="text" style="width:100%;" class="box_settings_tab" name="weight_boxes_length[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo !empty($box['length'])? esc_attr( $box['length']):'1'; ?>" required="required" /></td>
								<td><input type="text" style="width:100%;" class="box_settings_tab" name="weight_boxes_width[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo !empty($box['width'])? esc_attr( $box['width']):'1'; ?>" required="required" /></td>
								<td><input type="text" style="width:100%;" class="box_settings_tab" name="weight_boxes_height[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo !empty($box['height'])? esc_attr( $box['height']):'1'; ?>" required="required" /></td>
								<td><input type="text" style="width:100%;" class="box_settings_tab" name="weight_boxes_min_weight[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo !empty($box['min_weight']) ? esc_attr( $box['min_weight'] ) : 0; ?>" /></td>
								<td><input type="text" style="width:100%;" class="box_settings_tab" name="weight_boxes_max_weight[<?php echo esc_attr( $count_custom ); ?>]" value="<?php echo !empty($box['max_weight']) ? esc_attr( $box['max_weight'] ) : ''; ?>" required="required" /></td>
								<td><input type="checkbox" class="box_settings_tab" name="boxes_enabled[<?php echo esc_attr( $count_custom ); ?>]" <?php checked( !empty($box['enabled'])?$box['enabled']:'', true ); ?> /></td>  
							</tr> 
							<?php
						$count_custom++;
					}
				}

				?>
			</tbody>
		</table>
		<script type="text/javascript">

			jQuery(window).load(function(){
				var pack_type_options = '<?php echo esc_attr( $option_string ); ?>';
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

				jQuery('.aus_weight_boxes .insert').click( function() {
					var $tbody = jQuery('.aus_weight_boxes').find('tbody');
					var size = $tbody.find('tr').size();
					var code = '<tr class="new">\
									<td class="check-column"><input type="checkbox" /></td>\
									<input type="hidden" size="1" class="box_settings_tab" name="weight_boxes_id[' + size + ']" value ="'+size+'" />\
									<td><input type="text" size="25" class="box_settings_tab" name="weight_boxes_name[' + size + ']" required="required" /></td>\
									<td><input type="text" style="width:100%;" class="box_settings_tab" name="weight_boxes_length[' + size + ']" required="required" /></td>\
									<td><input type="text" style="width:100%;" class="box_settings_tab" name="weight_boxes_width[' + size + ']" required="required"/ ></td>\
									<td><input type="text" style="width:100%;" class="box_settings_tab" name="weight_boxes_height[' + size + ']" required="required" /></td>\
									<td><input type="text" style="width:100%;" class="box_settings_tab" name="weight_boxes_min_weight[' + size + ']" /></td>\
									<td><input type="text" style="width:100%;" class="box_settings_tab" name="weight_boxes_max_weight[' + size + ']" required="required" /></td>\
									<td><input type="checkbox" class="box_settings_tab" name="boxes_enabled[' + size + ']" /></td>\\n\
								</tr>';
					$tbody.append( code );

					return false;
				} );

				jQuery('.aus_weight_boxes .remove').click(function() {
					var $tbody = jQuery('.aus_weight_boxes').find('tbody');

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


		</script>
