<?php
/*Making compatible with PHP 7.1 later versions*/
if (version_compare(phpversion(), '7.1', '>=')) {
    ini_set('serialize_precision', -1); // Avoiding adding of unnecessary 17 decimal places resulted from json_encode
}
class Elex_Australia_Post_Funtions {

    public function __construct(){
        add_filter('woocommerce_cart_shipping_method_full_label', array($this, 'elex_aus_post_cart_shipping_method_full_label'), 10, 2);
    }
    public function elex_aus_post_cart_shipping_method_full_label($label, $method){
        $general_settings = get_option('woocommerce_wf_australia_post_settings');
        $estimated_delivery_date_enabled = isset($general_settings['aus_post_estimated_delivery_date_enabled']) ? $general_settings['aus_post_estimated_delivery_date_enabled'] :false;
        $method_meta_data = $method->get_meta_data();
        $is_elex_combined_export_tool_enable = (isset($general_settings['combined_export_tool_enable']) && $general_settings['combined_export_tool_enable'] == 'yes') ? 'yes' : false; 
        $is_elex_combined_export_tool_show_rate_separate = (isset($general_settings['combined_export_tool_show_rate_separate']) && $general_settings['combined_export_tool_show_rate_separate'] == 'yes' && $is_elex_combined_export_tool_enable == 'yes') ? 'yes' : false;
        if ( $estimated_delivery_date_enabled && $estimated_delivery_date_enabled === "yes") {
            
            $estimated_delivery_view_option = isset($general_settings['aus_post_estimated_delivery_view_option'])? $general_settings['aus_post_estimated_delivery_view_option']:false;
            if (!$estimated_delivery_view_option || $estimated_delivery_view_option == 'days') {
                if (isset($method_meta_data['aus_post_delivery_days']) && !empty($method_meta_data['aus_post_delivery_days'])) {
                    $days = $method_meta_data['aus_post_delivery_days'];
                    if (isset($days) && $days != NULL) {
                        if ($days == 1) {
                            $est_delivery_day_html = '<br /><small >' . __(' Est Delivery : Within One Day', 'wf-shipping-auspost') . '</small>';
                        } else {
                            $est_delivery_day_html = '<br /><small >' . __(' Est Delivery : Within ', 'wf-shipping-auspost') . $days . ' day(s). ' . '</small>';
                        }

                        $label .= $est_delivery_day_html;
                    }
                }
            } else {
                if (isset($method_meta_data['aus_post_delivery_date']) && !empty($method_meta_data['aus_post_delivery_date'])) {
                    $date = $method_meta_data['aus_post_delivery_date'];
                    $date = date("D jS M Y", strtotime($date));
                    if (isset($date) && $date != NULL) {
                        $est_delivery_day_html = '<br /><small >' . __(' Est Delivery : ', 'wf-shipping-auspost') . $date . '</small>';

                        $label .= $est_delivery_day_html;
                    }
                }
            }
        }
        if($is_elex_combined_export_tool_enable && $is_elex_combined_export_tool_enable == 'yes' && $is_elex_combined_export_tool_show_rate_separate && $is_elex_combined_export_tool_show_rate_separate == 'yes' && is_array($method_meta_data) && !empty($method_meta_data) && isset($method_meta_data['elex_aus_post_estimated_duties_and_taxes_value']) &&  $method_meta_data['elex_aus_post_estimated_duties_and_taxes_value'] > 0){
            $estimated_duties_and_taxes_value = round($method_meta_data['elex_aus_post_estimated_duties_and_taxes_value'], 2);
            $est_delivery_day_html = '<br /><small >' . __(' Duties and tax value : ', 'wf-shipping-auspost') . wc_price($estimated_duties_and_taxes_value) . '</small>';
            $label .= $est_delivery_day_html;
        }

        return $label;
    }

}
new Elex_Australia_Post_Funtions();