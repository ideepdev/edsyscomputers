<?php
global $woocommerce;
$general_settings = get_option('woocommerce_wf_australia_post_settings');
if (empty($general_settings)) 
{
    
    $wc_main_settings = array();
    $wc_main_settings['api_key'] = '';
    $wc_main_settings['api_pwd'] = '';
    $wc_main_settings['api_account_no'] = '';
    $wc_main_settings['conversion_rate'] = '';
    $wc_main_settings['origin_name'] = '';
    $wc_main_settings['origin_suburb'] = '';
    $wc_main_settings['origin_line'] =  '';
    $wc_main_settings['origin_state'] = '';
    $wc_main_settings['origin'] =  '';
    $wc_main_settings['shipper_phone_number'] =  '';
    $wc_main_settings['shipper_email'] =  '';
    $wc_main_settings['contracted_rates'] = '';
    $wc_main_settings['enabled'] =  '';
    $wc_main_settings['enabled_label'] =  '';
    $wc_main_settings['debug_mode'] =  '';
    $wc_main_settings['contracted_api_mode'] = 'live';
    $wc_main_settings['dir_download'] = '';
    $wc_main_settings['email_tracking'] = '';
    $wc_main_settings['cus_type'] = 'STANDARD_ADDRESS';
    $wc_main_settings['custom_message'] =  '';
    $wc_main_settings['ship_content'] = 'Shipment Contents';
    $wc_main_settings['packing_method'] = 'per_item';
    $wc_main_settings['boxes'] = array();
    $wc_main_settings['title'] = 'Australia POST';
    $wc_main_settings['availability'] = 'all';
    $sort = 0;
    $this->ordered_services = array();
    foreach ($this->services as $code => $values) {
        if (is_array($values))
            $name = $values['name'];
        else
            $name = $values;

        if (isset($this->custom_services[$code]) && isset($this->custom_services[$code]['order'])) {
            $sort = $this->custom_services[$code]['order'];
        }

        while (isset($this->ordered_services[$sort]))
            $sort++;

        $other_service_codes = isset($values['alternate_services']) ? $values['alternate_services'] : '';

        $this->ordered_services[$sort] = array($code, $name, $other_service_codes);
        $sort++;
    }

    ksort($this->ordered_services);
    $australia_post_services = array();
    foreach ($this->ordered_services as $value) {
        $code = $value[0];
        $name = $value[1];
        $other_service_codes = array_filter((array) $value[2]);
        $australia_post_services[$code]['order'] = isset($custom_services[$code]['order']) ? $custom_services[$code]['order'] : '';
        $australia_post_services[$code]['name'] = isset($custom_services[$code]['name']) ? $custom_services[$code]['name'] : '';
        $australia_post_services[$code]['enabled'] = false;
        if (in_array($code, array_keys($this->extra_cover))) {
            $australia_post_services[$code]['extra_cover'] =  false;
        }
        if (in_array($code, $this->delivery_confirmation)) {
            $australia_post_services[$code]['delivery_confirmation'] =  false;
        }
        $australia_post_services[$code]['adjustment'] = isset($custom_services[$code]['adjustment']) ? $custom_services[$code]['adjustment'] : '';
        $australia_post_services[$code]['adjustment_percent'] = isset($custom_services[$code]['adjustment_percent']) ? $custom_services[$code]['adjustment_percent'] : '';
    }


    $wc_main_settings['services'] = $australia_post_services;
    $wc_main_settings['offer_rates'] =  'all';
    $wc_main_settings['aus_post_estimated_delivery_date_enabled'] =  '';
    $wc_main_settings['aus_post_working_days'] =  array();
    $wc_main_settings['aus_post_cut_off_time'] =  '';
    $wc_main_settings['aus_post_estimated_delivery_lead_time'] =  0;
    $wc_main_settings['aus_post_estimated_delivery_view_option'] =  'days';
    $wc_main_settings['disable_alternate_services'] = '';
    update_option("services_saved_in_settings", false);
    update_option('woocommerce_wf_australia_post_settings', $wc_main_settings);
}
