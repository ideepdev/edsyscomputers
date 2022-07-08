<?php
$this->init_settings(); 
global $woocommerce;
$wc_main_settings       = array();
$this->contracted_rates = $this->get_option('contracted_rates') == 'yes' ? true : false;
$weight_type            =  array('pack_descending'=>__('Pack heavier items first', 'wf-shipping-auspost'),'pack_ascending'=>__('Pack lighter items first', 'wf-shipping-auspost'));

function sort_pre_defined_boxes( $pre_defined_boxes) {
	$length = count($pre_defined_boxes);
	if ($length <= 1) {
		return $pre_defined_boxes;
	} else {
		$pivot = $pre_defined_boxes[0]['order'];
		$left  = array();
		$right = array();
		
		for ($i = 1; $i < count($pre_defined_boxes); $i++) {
			if ($pre_defined_boxes[$i]['order'] < $pivot) {
				$left[] = $pre_defined_boxes[$i];
			} else {
				$right[] = $pre_defined_boxes[$i];
			}
		}

		return array_merge(sort_pre_defined_boxes($left), array($pre_defined_boxes[0]), sort_pre_defined_boxes($right));
	}
}

if (isset($_POST['wf_aus_packing_save_changes_button'])) {
	$wc_main_settings                   = get_option('woocommerce_wf_australia_post_settings');	
	$wc_main_settings['packing_method'] = sanitize_text_field($_POST['wf_australia_post_packing_method']);
	$auspost_box_packing_error          = '';
	$stored_pre_defined_boxes_auspost   = array();
	$stored_custom_boxes_auspost        = array();
	$stored_boxes_starTrack             = array();

	$box_data = array();
	
	if ( 'box_packing' === $wc_main_settings['packing_method'] ) {
		if (isset($_POST['boxes_name']) && !empty($_POST['boxes_name'])) {
			$box_data = $_POST['boxes_name'];
		}
		
		if (!$box_data) {
			$auspost_box_packing_error = 'At least one box is required for packing';
			echo'
			<div class="notice notice-error is-dismissible">
				<p>' . esc_attr( $auspost_box_packing_error  ) . '</p>
			</div>
			';
		}

		$box = array();
		if ($box_data) {
			$box_data_size = sizeof($box_data);
			$boxes_count   = $box_data_size;
			foreach ( $box_data as $key => $value) {
				$box_id = $_POST['boxes_id'][$key];

				if (!empty($_POST['boxes_name'][$key])) {
					if (!empty($_POST['boxes_order'][$key])) {
						$box_order = sanitize_text_field($_POST['boxes_order'][$key]);
					} else {
						$boxes_count += 1;
						$box_order    = $boxes_count;
					}
					$box_name           = empty($_POST['boxes_name'][$key]) ? 'New Box' : sanitize_text_field($_POST['boxes_name'][$key]);
					$box_length         = empty($_POST['boxes_outer_length'][$key]) ? 0 : sanitize_text_field($_POST['boxes_outer_length'][$key]); 
					$boxes_width        = empty($_POST['boxes_outer_width'][$key]) ? 0 : sanitize_text_field($_POST['boxes_outer_width'][$key]); 
					$boxes_height       = empty($_POST['boxes_outer_height'][$key]) ? 0 : sanitize_text_field($_POST['boxes_outer_height'][$key]); 
					$boxes_inner_length = empty($_POST['boxes_inner_length'][$key]) ? 0 : sanitize_text_field($_POST['boxes_inner_length'][$key]); 
					$boxes_inner_width  = empty($_POST['boxes_inner_width'][$key]) ? 0 : sanitize_text_field($_POST['boxes_inner_width'][$key]); 
					$boxes_inner_height = empty($_POST['boxes_inner_height'][$key]) ? 0 : sanitize_text_field($_POST['boxes_inner_height'][$key]); 
					$boxes_box_weight   = empty($_POST['boxes_box_weight'][$key]) ? 0 : sanitize_text_field($_POST['boxes_box_weight'][$key]); 
					$boxes_max_weight   = empty($_POST['boxes_max_weight'][$key]) ? 0 : sanitize_text_field($_POST['boxes_max_weight'][$key]);
					$box_enabled        = isset($_POST['boxes_enabled'][$key]) ? true : false; 
					$is_letter          = isset($_POST['boxes_is_letter'][$key]) ? true : false; 
					$box[$key]          = array(
						'order' => $box_order,
						'id' => $box_id,
						'name' => $box_name,
						'outer_length' => $box_length,
						'outer_width' => $boxes_width,
						'outer_height' => $boxes_height,
						'inner_length' => $boxes_inner_length,
						'inner_width' => $boxes_inner_width,
						'inner_height' => $boxes_inner_height,
						'box_weight' => $boxes_box_weight,
						'max_weight' => $boxes_max_weight,
						'enabled' => $box_enabled,
						'is_letter' => $is_letter,
						'pack_type' => $_POST['boxes_pack_type'][$key],
						'eligible_for' => ( isset($_POST['box_eligible_for'][$key]) && !empty($_POST['box_eligible_for'][$key]) )?$_POST['box_eligible_for'][$key]: '', 
					);
				} else {
					echo'
					<div class="notice notice-error is-dismissible">
						<p><b>Enter box properties</b></p>
					</div>
					';
				}
				$boxes_count++;
			}
		}
	
	   $wc_main_settings['boxes'] = $box;
	}
	
	
	if ( 'weight' === $wc_main_settings['packing_method'] ) {
		$weight_box_data                            = isset($_POST['weight_boxes_name']) && !empty( $_POST['weight_boxes_name'] ) ? $_POST['weight_boxes_name'] : array();
		$wc_main_settings['weight_packing_process'] = sanitize_text_field($_POST['wf_post_shipping_weight_packing_process']);
		$weight_box                                 = array();

		if ( isset($_POST['weight_boxes_name']) && !empty( $_POST['weight_boxes_name'] ) ) {

			$wh_box_name =  $_POST['weight_boxes_name'] ;
			foreach ($wh_box_name as $box_key => $size ) {
				$wh_box_id = $_POST['weight_boxes_id'][$box_key];

				$wh_box_name          = empty($_POST['weight_boxes_name'][$box_key]) ? 'New Box' : sanitize_text_field($_POST['weight_boxes_name'][$box_key]);
				$wh_box_length        = empty($_POST['weight_boxes_length'][$box_key]) ? 0 : sanitize_text_field($_POST['weight_boxes_length'][$box_key]); 
				$wh_boxes_width       = empty($_POST['weight_boxes_width'][$box_key]) ? 0 : sanitize_text_field($_POST['weight_boxes_width'][$box_key]); 
				$wh_boxes_height      = empty($_POST['weight_boxes_height'][$box_key]) ? 0 : sanitize_text_field($_POST['weight_boxes_height'][$box_key]); 
				$wh_boxes_box_weight  = empty($_POST['weight_boxes_min_weight'][$box_key]) ? 0 : sanitize_text_field($_POST['weight_boxes_min_weight'][$box_key]); 
				$wh_boxes_max_weight  = empty($_POST['weight_boxes_max_weight'][$box_key]) ? 0 : sanitize_text_field($_POST['weight_boxes_max_weight'][$box_key]);
				$wh_box_enabled       = isset($_POST['boxes_enabled'][$box_key]) ? true : false; 
				$weight_box[$box_key] = array(
						'id' => $wh_box_id,
						'name' => $wh_box_name,
						'length' => $wh_box_length,
						'width' => $wh_boxes_width,
						'height' => $wh_boxes_height,
						'min_weight' => $wh_boxes_box_weight,
						'max_weight' => $wh_boxes_max_weight,
						'enabled' => $wh_box_enabled,
						);
			}
			$wc_main_settings['weight_boxes'] = $weight_box;

		} else {
			$wc_main_settings['weight_boxes'] = array();
		}

	}
	$wc_main_settings['group_shipping']     =  ( isset($_POST['wf_australia_post_group_shipping']) ) ? 'yes' : '';
	$wc_main_settings['box_packing_method'] =  ( isset( $_POST['wf_australia_post_box_packing_method']) ) ? $_POST['wf_australia_post_box_packing_method'] : 'volume';
	
	update_option('woocommerce_wf_australia_post_settings', $wc_main_settings);

	// classifying and storing the pre-defined and custom boxes in the database
	if ( 'box_packing' === $wc_main_settings['packing_method'] ) {
		foreach ($wc_main_settings['boxes'] as $box) {
			if ( 'startrack' == $this->rate_type && $this->contracted_rates) {
				$stored_boxes_starTrack[] = $box;
			} elseif ( 'PD' == $box['pack_type'] ) {
				$stored_pre_defined_boxes_auspost[] = $box;
			} elseif ( 'YP' == $box['pack_type'] ) {
				$stored_custom_boxes_auspost[] = $box;
			}
		}

		update_option('auspost_stored_pre_defined_boxes', $stored_pre_defined_boxes_auspost);
		update_option('auspost_stored_custom_boxes', $stored_custom_boxes_auspost);
		update_option('starTrack_stored_boxes', $stored_boxes_starTrack);
	}
	// classifying weight-based boxws
	if ( 'weight' === $wc_main_settings['packing_method'] ) {
		foreach ($wc_main_settings['weight_boxes'] as $box) {
			$stored_custom_boxes_auspost[] = $box;
		}
		update_option( 'aus_post_stored_custom_boxes', $stored_custom_boxes_auspost );
	}
}

$general_settings        = get_option('woocommerce_wf_australia_post_settings');
$this->pre_defined_boxes = include 'wf_auspost_predefined_boxes.php';// Retrieving the pre-defined boxes in the file wf_auspost_predefined_boxes.php 
$this->boxes             = isset($general_settings['boxes']) ? $general_settings['boxes'] : array();
$this->weight_boxes      = isset($general_settings['weight_boxes']) ? $general_settings['weight_boxes'] : array();
?>

<table>
	<tr valign="top" >
		<td style="width:35%;font-weight:800;">
			<label for="wf_australia_post_"><?php esc_html_e('Packing Options', 'wf-shipping-auspost'); ?></label>
		</td><td scope="row" class="titledesc" style="display: block;width:100%;margin-bottom: 20px;margin-top: 3px;">
			<fieldset style="padding:3px;">
			 <label for="wf_australia_post_"><?php esc_html_e('Parcel Packing Method', 'wf-shipping-auspost'); ?></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Select the Packing method using which you want to pack your products.  Pack items individually - This option allows you to pack each item separately in a box. Hence, multiple items will go in multiple boxes. Pack into boxes with weights and dimensions - This option allows you to pack items into boxes of various sizes. Weight based packing - This option allows you to pack your products based on weight of the package.', 'wf-shipping-auspost'); ?>"></span>	<br>
			 <select name="wf_australia_post_packing_method" class="packing_method" id="wf_australia_post_packing_method" default="per_item" onchange="show_dimensions(this.value)">
					<?php 
						$selected_packing_method = isset($general_settings['packing_method']) ? $general_settings['packing_method'] : 'per_item';
					?>
					<option value="per_item" <?php echo ( 'per_item' === $selected_packing_method ) ? 'selected="selected"': ''; ?> ><?php esc_html_e('Default: Pack items individually', 'wf-shipping-auspost'); ?></option>
					<option value="box_packing" <?php echo ( 'box_packing' === $selected_packing_method ) ? 'selected="selected"': ''; ?> ><?php esc_html_e('Recommended: Pack into boxes with weights and dimensions', 'wf-shipping-auspost'); ?></option>
					<option value="weight" <?php echo ( 'weight' === $selected_packing_method ) ? 'selected="selected"': ''; ?> ><?php esc_html_e('Weight based: Calculate shipping on the basis of order total weight', 'wf-shipping-auspost'); ?></option>
				</select>
				<br/>
			<strong>Unit of weight: <?php echo get_option('woocommerce_weight_unit'); ?></strong><br>
			<strong id="unit_of_dimensions">Unit of dimensions: <?php echo get_option('woocommerce_dimension_unit'); ?></strong>
			</fieldset>
			
		</td>
	</tr>
	<tr id="elex-aus-post-group-shipping">
			<td style="width:35%;font-weight:800;">
				<label for="wf_australia_post_group_shipping"><?php esc_html_e('Group Shipping', 'wf-shipping-auspost'); ?></label> <span class="woocommerce-help-tip" data-tip="<?php esc_attr_e('Enable this option to request Australia Post to consider multiple packets in the order as one group. This may help in getting better prices from Australia Post compared to individual pricing for each packet. Please note some services may not be available if you enable this option.', 'wf-shipping-auspost'); ?>" ></span><br>
			</td>
			<td>
				<fieldset style="padding:3px;">				 
					<input  type="checkbox" name="wf_australia_post_group_shipping"  id="wf_australia_post_group_shipping" style="" <?php echo ( isset($general_settings['group_shipping']) && 'yes' == $general_settings['group_shipping'] ) ? 'checked' : ''; ?>  >
				</fieldset>
			</td>
		</tr>
	<tr>
		<tr id="elex-aus-post-box-packing-option">
			<td style="width:35%;font-weight:800; padding-bottom: 20px;">
				<label for="wf_australia_post_packing_algorithm"><?php esc_html_e('Packing Algorithm', 'wf-shipping-auspost'); ?></label> 
			</td>
			<td style="padding-bottom: 20px;">
				<fieldset style="padding:3px;">
				 
				<select name="wf_australia_post_box_packing_method" class="box_packing_method" id="wf_australia_post_box_packing_method" default="volume" onchange="show_dimensions(this.value)">
					<?php 
						$selected_box_packing_method = isset($general_settings['box_packing_method']) ? $general_settings['box_packing_method'] : 'volume';
					?>
					<option value="volume" <?php echo ( 'volume' === $selected_box_packing_method ) ? 'selected="selected"': ''; ?> ><?php esc_html_e('Default: Volume Based Packing', 'wf-shipping-auspost'); ?></option>
					<option value="stack" <?php echo ( 'stack' === $selected_box_packing_method ) ? 'selected="selected"': ''; ?> ><?php esc_html_e('Stack First Packing', 'wf-shipping-auspost'); ?></option>
					
				</select>
				<br/>
				</fieldset>
			</td>
		</tr>
		<tr id="packing_options">
			<td colspan="2">
			<?php require  'box_packing.php'; ?>
			</td>
		</tr>
		<tr id="weight_based_option">
			<td colspan="2">
				<?php require  'weight_box_packing.php'; ?>
				<fieldset id="packing_options_weight_packing_process_type" style="padding:3px;">
				<?php 
					$slected_weight_type = isset($general_settings['weight_packing_process']) ? $general_settings['weight_packing_process'] : 'pack_descending';
				foreach ($weight_type as $key => $value) {
					if ($key === $slected_weight_type) {
						echo '<input class="input-text regular-input " type="radio" name="wf_post_shipping_weight_packing_process" id="wf_post_shipping_weight_packing_process" style="" value="' . $key . '" checked="true" placeholder=""> ' . $value . ' ';
					} else {
						echo '<input class="input-text regular-input " type="radio" name="wf_post_shipping_weight_packing_process" id="wf_post_shipping_weight_packing_process" style="" value="' . $key . '"  placeholder=""> ' . $value . ' ';
					}
				}
				?>
				</fieldset>
			</td>

		</tr>
		
	<tr>
		<td colspan="2" style="text-align:right;padding-right: 10%;">
			<br/>
			<input type="submit" value="<?php esc_attr_e('Save Changes', 'wf-shipping-auspost'); ?>" class="button button-primary" name="wf_aus_packing_save_changes_button">
		</td>
	</tr>
</table>
<script type="text/javascript">
	jQuery(document).ready(function( $ ) {
			jQuery('html').on('click',function(e){
				jQuery(window).off('beforeunload');
				window.onbeforeunload = null;
			});
		if(jQuery('#wf_australia_post_packing_method').val() == 'box_packing'){
			jQuery('#unit_of_dimensions').show();
		}else{
			jQuery('#unit_of_dimensions').hide();
		}
		jQuery('#wf_australia_post_packing_method').on('change',function(){
			if(jQuery('#wf_australia_post_packing_method').val() == 'per_item'){
				jQuery('#elex-aus-post-group-shipping').show();
			}else{
				jQuery('#elex-aus-post-group-shipping').show();
			}
		}).change();
		

	});
	function show_dimensions(value){
		var parcel_packing_method = value;
		if(parcel_packing_method == 'box_packing'){
			jQuery('#unit_of_dimensions').show();
		}else{
			jQuery('#unit_of_dimensions').hide();
		}
	}
</script>
