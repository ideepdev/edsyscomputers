jQuery(document).ready(function(){
	
	//Toggle packing methods
	wf_load_packing_method_options();
	jQuery('.packing_method').change(function(){
		wf_load_packing_method_options();
	});
	
	
	// Advance settings tab
	jQuery('.wf_settings_heading_tab').next('table').hide();
	jQuery('.wf_settings_heading_tab').click(function(event){
		event.stopImmediatePropagation();
		jQuery(this).next('table').toggle();
	});
	jQuery('#elex_australia_post_combined_export_tool_enable').on('change',function(e){
		if(jQuery(this).prop('checked') == true){
			jQuery('.elex_australia_post_combined_export_tool_show_rate_separate_td').show();
		}else{
			jQuery('.elex_australia_post_combined_export_tool_show_rate_separate_td').hide();
		}
	}).change();
});

function wf_load_packing_method_options(){
	pack_method	=	jQuery('.packing_method').val();
	jQuery('#packing_options').hide();
	jQuery('#weight_based_option').closest('tr').hide();
	jQuery('#elex-aus-post-box-packing-option').closest('tr').hide();
	
	switch(pack_method){
		case 'per_item':
		default:
			break;
			
		case 'box_packing':
			jQuery('#elex-aus-post-box-packing-option').closest('tr').show();
			jQuery('#packing_options').show();	
			break;
			
		case 'weight':
			jQuery('#weight_based_option').closest('tr').show();
			break;
	}
}
