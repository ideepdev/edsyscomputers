<?php
$this->init_settings(); 
global $woocommerce;
$wc_main_settings = array();
$this->contracted_rates = $this->get_option('contracted_rates') == 'yes' ? true : false;

function sort_pre_defined_boxes($pre_defined_boxes)
{
    $length = count($pre_defined_boxes);
    if($length <= 1){
        return $pre_defined_boxes;
    }
    else{
        $pivot = $pre_defined_boxes[0]['order'];
        $left = $right = array();
        
        for($i = 1; $i < count($pre_defined_boxes); $i++)
        {
            if($pre_defined_boxes[$i]['order'] < $pivot){
                $left[] = $pre_defined_boxes[$i];
            }
            else{
                $right[] = $pre_defined_boxes[$i];
            }
        }

        return array_merge(sort_pre_defined_boxes($left), array($pre_defined_boxes[0]), sort_pre_defined_boxes($right));
    }
}

if(isset($_POST['wf_aus_packing_save_changes_button']))
{
	$wc_main_settings = get_option('woocommerce_wf_australia_post_settings');	
	$wc_main_settings['packing_method'] = sanitize_text_field($_POST['wf_australia_post_packing_method']);
	$auspost_box_packing_error = '';
    $stored_pre_defined_boxes_auspost = array();
    $stored_custom_boxes_auspost = array();
    $stored_boxes_starTrack = array();

    $box_data = array();
	
	if($wc_main_settings['packing_method'] === 'box_packing')
	{
        if(isset($_POST['boxes_name']) && !empty($_POST['boxes_name'])){
            $box_data = $_POST['boxes_name'];
        }
		
		if(!$box_data){
			$auspost_box_packing_error = "At least one box is required for packing";
			echo'
			<div class="notice notice-error is-dismissible">
				<p>'.$auspost_box_packing_error.'</p>
			</div>
			';
		}

		$box = array();
		if($box_data){
            $box_data_size = sizeof($box_data);
            $boxes_count = $box_data_size;
			foreach ( $box_data as $key => $value) {
				$box_id = $_POST['boxes_id'][$key];

				if(!empty($_POST['boxes_name'][$key]))
				{
                    if(!empty($_POST['boxes_order'][$key])){
                        $box_order = sanitize_text_field($_POST['boxes_order'][$key]);
                    }else{
                        $boxes_count += 1;
                        $box_order = $boxes_count;
                    }
					$box_name = empty($_POST['boxes_name'][$key]) ? 'New Box' : sanitize_text_field($_POST['boxes_name'][$key]);
					$box_length = empty($_POST['boxes_outer_length'][$key]) ? 0 : sanitize_text_field($_POST['boxes_outer_length'][$key]); 
					$boxes_width = empty($_POST['boxes_outer_width'][$key]) ? 0 : sanitize_text_field($_POST['boxes_outer_width'][$key]); 
					$boxes_height = empty($_POST['boxes_outer_height'][$key]) ? 0 : sanitize_text_field($_POST['boxes_outer_height'][$key]); 
					$boxes_inner_length = empty($_POST['boxes_inner_length'][$key]) ? 0 : sanitize_text_field($_POST['boxes_inner_length'][$key]); 
					$boxes_inner_width = empty($_POST['boxes_inner_width'][$key]) ? 0 : sanitize_text_field($_POST['boxes_inner_width'][$key]); 
					$boxes_inner_height = empty($_POST['boxes_inner_height'][$key]) ? 0 : sanitize_text_field($_POST['boxes_inner_height'][$key]); 
					$boxes_box_weight = empty($_POST['boxes_box_weight'][$key]) ? 0 : sanitize_text_field($_POST['boxes_box_weight'][$key]); 
					$boxes_max_weight = empty($_POST['boxes_max_weight'][$key]) ? 0 : sanitize_text_field($_POST['boxes_max_weight'][$key]);
					$box_enabled = isset($_POST['boxes_enabled'][$key]) ? true : false; 
					$is_letter = isset($_POST['boxes_is_letter'][$key]) ? true : false; 
					$box[$key] = array(
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
                        'eligible_for' => (isset($_POST['box_eligible_for'][$key]) && !empty($_POST['box_eligible_for'][$key]))?$_POST['box_eligible_for'][$key]: '', 
					);
				}else{
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
	
	
	if($wc_main_settings['packing_method'] === 'weight'){
		if($_POST['wf_australia_post_max_weight']){
			$wc_main_settings['max_weight'] = !empty($_POST['wf_australia_post_max_weight']) ?sanitize_text_field($_POST['wf_australia_post_max_weight']) : '';
		}else{
			echo'
				<div class="notice notice-error is-dismissible">
					<p><b> Set Maximum weight</b></p>
				</div>
				';
		}
	}
	$wc_main_settings['group_shipping'] =  (isset($_POST['wf_australia_post_group_shipping'])) ? 'yes' : '';
	$wc_main_settings['box_packing_method'] =  (isset( $_POST['wf_australia_post_box_packing_method']) ) ? $_POST['wf_australia_post_box_packing_method'] : 'volume';
	
	update_option('woocommerce_wf_australia_post_settings',$wc_main_settings);

    // classifying and storing the pre-defined and custom boxes in the database
    if($wc_main_settings['packing_method'] === 'box_packing'){
        foreach($wc_main_settings['boxes'] as $box){
            if($this->rate_type == 'startrack' && $this->contracted_rates){
                $stored_boxes_starTrack[] = $box;
            }else if($box['pack_type'] == 'PD'){
                $stored_pre_defined_boxes_auspost[] = $box;
            }else if($box['pack_type'] == 'YP'){
                $stored_custom_boxes_auspost[] = $box;
            }
        }

        update_option('auspost_stored_pre_defined_boxes', $stored_pre_defined_boxes_auspost);
        update_option('auspost_stored_custom_boxes', $stored_custom_boxes_auspost);
        update_option('starTrack_stored_boxes', $stored_boxes_starTrack);
    }
}

$general_settings = get_option('woocommerce_wf_australia_post_settings');
$this->pre_defined_boxes = include('wf_auspost_predefined_boxes.php');// Retrieving the pre-defined boxes in the file wf_auspost_predefined_boxes.php 
$this->boxes = isset($general_settings['boxes']) ? $general_settings['boxes'] : array();
?>

<table>
	<tr valign="top" >
		<td style="width:35%;font-weight:800;">
			<label for="wf_australia_post_"><?php _e('Packing Options','wf-shipping-auspost') ?></label>
		</td><td scope="row" class="titledesc" style="display: block;width:100%;margin-bottom: 20px;margin-top: 3px;">
			<fieldset style="padding:3px;">
			 <label for="wf_australia_post_"><?php _e('Parcel Packing Method','wf-shipping-auspost') ?></label> <span class="woocommerce-help-tip" data-tip="<?php _e('Select the Packing method using which you want to pack your products.  Pack items individually - This option allows you to pack each item separately in a box. Hence, multiple items will go in multiple boxes. Pack into boxes with weights and dimensions - This option allows you to pack items into boxes of various sizes. Weight based packing - This option allows you to pack your products based on weight of the package.','wf-shipping-auspost') ?>"></span>	<br>
			 <select name="wf_australia_post_packing_method" class="packing_method" id="wf_australia_post_packing_method" default="per_item" onchange="show_dimensions(this.value)">
					<?php 
						$selected_packing_method = isset($general_settings['packing_method']) ? $general_settings['packing_method'] : 'per_item';
					?>
					<option value="per_item" <?php echo ($selected_packing_method === 'per_item') ? 'selected="selected"': '' ?> ><?php _e('Default: Pack items individually','wf-shipping-auspost') ?></option>
					<option value="box_packing" <?php echo ($selected_packing_method === 'box_packing') ? 'selected="selected"': '' ?> ><?php _e('Recommended: Pack into boxes with weights and dimensions','wf-shipping-auspost') ?></option>
					<option value="weight" <?php echo ($selected_packing_method === 'weight') ? 'selected="selected"': '' ?> ><?php _e('Weight based: Calculate shipping on the basis of order total weight','wf-shipping-auspost') ?></option>
				</select>
				<br/>
			<strong>Unit of weight: <?php echo get_option('woocommerce_weight_unit');?></strong><br>
			<strong id="unit_of_dimensions">Unit of dimensions: <?php echo get_option('woocommerce_dimension_unit');?></strong>
			</fieldset>
			
		</td>
	</tr>
	<tr id="elex-aus-post-group-shipping">
			<td style="width:35%;font-weight:800;">
				<label for="wf_australia_post_group_shipping"><?php _e('Group Shipping','wf-shipping-auspost') ?></label> <span class="woocommerce-help-tip" data-tip="<?php _e('Enable this option to request Australia Post to consider multiple packets in the order as one group. This may help in getting better prices from Australia Post compared to individual pricing for each packet. Please note some services may not be available if you enable this option.','wf-shipping-auspost') ?>" ></span><br>
			</td>
			<td>
				<fieldset style="padding:3px;">				 
					<input  type="checkbox" name="wf_australia_post_group_shipping"  id="wf_australia_post_group_shipping" style="" <?php echo (isset($general_settings['group_shipping']) && $general_settings['group_shipping'] == 'yes' ) ? 'checked' : ''; ?>  >
				</fieldset>
			</td>
		</tr>
	<tr>
		<tr id="elex-aus-post-box-packing-option">
			<td style="width:35%;font-weight:800; padding-bottom: 20px;">
				<label for="wf_australia_post_packing_algorithm"><?php _e('Packing Algorithm','wf-shipping-auspost') ?></label> 
			</td>
			<td style="padding-bottom: 20px;">
				<fieldset style="padding:3px;">
				 
				<select name="wf_australia_post_box_packing_method" class="box_packing_method" id="wf_australia_post_box_packing_method" default="volume" onchange="show_dimensions(this.value)">
					<?php 
						$selected_box_packing_method = isset($general_settings['box_packing_method']) ? $general_settings['box_packing_method'] : 'volume';
					?>
					<option value="volume" <?php echo ($selected_box_packing_method === 'volume') ? 'selected="selected"': '' ?> ><?php _e('Default: Volume Based Packing','wf-shipping-auspost') ?></option>
					<option value="stack" <?php echo ($selected_box_packing_method === 'stack') ? 'selected="selected"': '' ?> ><?php _e('Stack First Packing','wf-shipping-auspost') ?></option>
					
				</select>
				<br/>
				</fieldset>
			</td>
		</tr>
		<tr id="packing_options">
			<td colspan="2">
			<?php  include( 'box_packing.php') ?>
			</td>
		</tr>
		<tr id="weight_based_option">
			<td style="width:35%;font-weight:800;">
			</td><td scope="row" class="titledesc" style="display: block;width:100%;margin-bottom: 20px;margin-top: 3px;">
			<fieldset style="padding:3px;">
				 <label for="wf_australia_post_max_weight"><?php _e('Maximum Weight / Packing','wf-shipping-auspost') ?></label> <span class="woocommerce-help-tip" data-tip="<?php _e('Set a maximum weight which can be accommodated in one package. Maximum weight limit is 22Kg','wf-shipping-auspost') ?>" ></span><br>
				 <input class="input-text regular-input " type="text" name="wf_australia_post_max_weight" id="wf_australia_post_max_weight" style="" value="<?php echo (isset($general_settings['max_weight'])) ? $general_settings['max_weight'] : ''; ?>" placeholder="">
				</fieldset>
			
		</tr>
		
	<tr>
		<td colspan="2" style="text-align:right;padding-right: 10%;">
			<br/>
			<input type="submit" value="<?php _e('Save Changes','wf-shipping-auspost') ?>" class="button button-primary" name="wf_aus_packing_save_changes_button">
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