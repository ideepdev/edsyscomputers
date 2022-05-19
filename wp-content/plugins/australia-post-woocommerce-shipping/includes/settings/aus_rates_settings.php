<?php
$this->init_settings();
global $woocommerce;
$wc_main_settings = array();
$posted_services = array();
$this->contracted_rates = $this->get_option('contracted_rates') == 'yes' ? true : false;
if(isset($_POST['wf_aus_rates_save_changes_button'])) {
    $wc_main_settings = get_option('woocommerce_wf_australia_post_settings');
    $wc_main_settings['title'] = (isset($_POST['wf_australia_post_title'])) ? sanitize_text_field($_POST['wf_australia_post_title']) : ((isset($this->settings['wf_australia_post_starTrack_rates']) && ($this->settings['wf_australia_post_starTrack_rates'] == 'yes')) ? __('StarTrack', 'wf-shipping-auspost') :   __('Australia Post', 'wf-shipping-auspost'));
    $wc_main_settings['elex_stratrack_title'] = (isset($_POST['wf_australia_startrack_post_title'])) ? sanitize_text_field($_POST['wf_australia_startrack_post_title']) :  __('StarTrack', 'wf-shipping-auspost');
    $wc_main_settings['availability'] = (isset($_POST['wf_australia_post_availability']) && $_POST['wf_australia_post_availability'] === 'all') ? 'all' : 'specific';
    
    $services         = array();
    $sub_services     = array();
    $startrack_services = array();
    $custom_sub_services = $this->non_contracted_alternate_services;

    if (isset($_POST['australia_post_service'])) {
        $posted_services  = $_POST['australia_post_service'];

        if (is_array($posted_services) && !empty($posted_services)) {
            foreach ($posted_services as $code => $settings) {

                $services[$code] = array(
                    'name'                      => wc_clean($settings['name']),
                    'order'                     => wc_clean($settings['order']),
                    'enabled'                   => isset($settings['enabled']) ? true : false,
                    'adjustment'                => wc_clean($settings['adjustment']),
                    'adjustment_percent'        => str_replace('%', '', wc_clean($settings['adjustment_percent'])),
                    'extra_cover'               => isset($settings['extra_cover']) ? true : false,
                    'delivery_confirmation'     => isset($settings['delivery_confirmation']) ? true : false,
                    'authority_to_leave'        => isset($settings['authority_to_leave']) ? true : false,
                    'allow_partial_delivery'    => isset($settings['allow_partial_delivery']) ? true : false,
                    'signature_on_delivery_option' => isset($settings['signature_on_delivery_option']) ? true : false,
                );
            }
        }
    }

    if (isset($_POST['startrack_service'])) {
        $posted_services  = $_POST['startrack_service'];

        if (is_array($posted_services) && !empty($posted_services)) {
            foreach ($posted_services as $code => $settings) {

                $startrack_services[$code] = array(
                    'name'                  => wc_clean($settings['name']),
                    'order'                 => wc_clean($settings['order']),
                    'enabled'               => isset($settings['enabled']) ? true : false,
                    'adjustment'            => wc_clean($settings['adjustment']),
                    'adjustment_percent'    => str_replace('%', '', wc_clean($settings['adjustment_percent'])),
                    'extra_cover'           => isset($settings['extra_cover']) ? true : false,
                    'delivery_confirmation' => isset($settings['delivery_confirmation']) ? true : false,
                );
            }
        }
    }

    if (isset($_POST['auspost_sub_services'])) {
        $sub_services_in_request  = isset($_POST['auspost_sub_services']) ? $_POST['auspost_sub_services'] : array();
        $subservices_name = $_POST['subservices_name'];

        foreach ($custom_sub_services as $custom_sub_service_key => $custom_sub_service_value) {
            $sub_services[$custom_sub_service_key] = array(
                'name' => !empty($subservices_name[$custom_sub_service_key]) ? $subservices_name[$custom_sub_service_key] : $custom_sub_service_value['name'],
                'enabled' => isset($sub_services_in_request[$custom_sub_service_key]) ? true : false,
                'main_service' => $custom_sub_service_value['main_service']
            );
        }
    }

    $wc_main_settings['services'] = $services;
    $wc_main_settings['sub_services'] = $sub_services;
    $wc_main_settings['startrack_services'] = $startrack_services;
    $wc_main_settings['offer_rates'] = (isset($_POST['wf_australia_post_offer_rates'])) ? 'cheapest' : 'all';
    $wc_main_settings['aus_post_estimated_delivery_date_enabled'] = (isset($_POST['wf_australia_post_estimated_delivery_date_enabled'])) ? 'yes' : '';
    $wc_main_settings['aus_post_working_days'] = (isset($_POST['wf_australia_post_working_days'])) ? $_POST['wf_australia_post_working_days'] : array() ;
    $wc_main_settings['aus_post_cut_off_time'] = (isset($_POST['wf_australia_post_cut_off_time'])) ? $_POST['wf_australia_post_cut_off_time'] : '';
    $wc_main_settings['aus_post_estimated_delivery_view_option'] = (isset($_POST['wf_australia_post_estimated_delivery_view_option'])) ? $_POST['wf_australia_post_estimated_delivery_view_option'] : 'days';
    $wc_main_settings['aus_post_estimated_delivery_lead_time'] = (isset($_POST['wf_australia_post_estimated_delivery_lead_time'])) ? $_POST['wf_australia_post_estimated_delivery_lead_time'] : 0;
    $wc_main_settings['show_insurance_checkout_field'] = (isset($_POST['wf_australia_post_show_insurance_checkout_field'])) ? 'yes' : '';
    $wc_main_settings['show_authority_to_leave_checkout_field'] = (isset($_POST['wf_australia_post_show_authority_to_leave_checkout_field'])) ? 'yes' : '';
    $wc_main_settings['show_signature_required_field'] = (isset($_POST['wf_australia_post_show_signature_required_field'])) ? 'yes' : '';
    $wc_main_settings['combined_export_tool_enable'] = (isset($_POST['elex_australia_post_combined_export_tool_enable'])) ? 'yes' : ''; 
    $wc_main_settings['combined_export_tool_show_rate_separate'] = (isset($_POST['elex_australia_post_combined_export_tool_show_rate_separate']) && $wc_main_settings['combined_export_tool_enable'] == 'yes') ? 'yes' : '';

    if ($wc_main_settings['availability'] === 'specific') {
        $wc_main_settings['countries'] = isset($_POST['wf_australia_post_countries']) ? $_POST['wf_australia_post_countries'] : '';
    }

    update_option("services_saved_in_settings", true);

    update_option('woocommerce_wf_australia_post_settings', $wc_main_settings);
}

$general_settings = get_option('woocommerce_wf_australia_post_settings');
$this->custom_services = isset($general_settings['services']) ? $general_settings['services'] : $this->settings['services'];
$this->custom_startrack_services = isset($general_settings['startrack_services']) ? $general_settings['startrack_services'] : $this->settings['startrack_services'];
$auspost_customer_account_error_on_rates_settings = get_option('auspost_customer_account_error');
if ($this->rate_type == 'startrack') {
    $this->api_pwd = $this->settings['wf_australia_post_starTrack_api_pwd'];
    $this->api_account_no = $this->settings['wf_australia_post_starTrack_api_account_no'];
} else if (empty($this->settings['wf_australia_post_starTrack_api_account_no'])) {
    update_option("insufficient_authentication_data", 'yes');
}

if (!empty($auspost_customer_account_error_on_rates_settings)) {
    echo '<div class="error"><p>' . __($auspost_customer_account_error_on_rates_settings, 'wf-shipping-auspost') . '</p></div>';
    delete_option('auspost_customer_account_error');
}
?>

<table style="width:100%;table-layout:fixed; ">
    <tr valign="top">
        <td style="width:30%;font-weight:800;">
            <label for="wf_australia_post_offer_rates"><?php _e('Show/Hide', 'wf-shipping-auspost') ?></label>
        </td>
        <td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
            <fieldset style="padding:3px;">
                <input class="input-text regular-input " type="checkbox" name="wf_australia_post_offer_rates" id="wf_australia_post_offer_rates" style="" value="yes" <?php echo (isset($general_settings['offer_rates']) && $general_settings['offer_rates'] === 'cheapest') ? 'checked' : ''; ?> placeholder=""> <?php _e('Show Cheapest Rates Only', 'wf-shipping-auspost') ?> <span class="woocommerce-help-tip" data-tip="<?php _e('On enabling this, the cheapest rate will be shown in the cart/checkout page.', 'wf-shipping-auspost') ?>"></span>
            </fieldset>            
        </td>
    </tr>
    <tr valign="top">
        <td style="width:30%;font-weight:800;">
            <label for="wf_australia_post_delivery_time_title"><?php _e('Estimated Delivery Date Settings', 'wf-shipping-auspost') ?></label>
        </td>
        <td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">            
            <fieldset style="padding:3px;">
                <input class="input-text regular-input" type="checkbox" name="wf_australia_post_estimated_delivery_date_enabled" id="wf_australia_post_estimated_delivery_date_enabled" value="yes" <?php echo (isset($general_settings['aus_post_estimated_delivery_date_enabled']) && $general_settings['aus_post_estimated_delivery_date_enabled'] === 'yes') ? 'checked' : ''; ?> placeholder=""> <?php _e('Enable', 'wf-shipping-auspost') ?> 
                <span class="woocommerce-help-tip" data-tip="<?php _e('On enabling this, the estimated delivery date will be shown in the cart/checkout page.', 'wf-shipping-auspost') ?>"></span>
            </fieldset>
                       
            <fieldset style="padding:3px;" class="australila_post_estimated_delivery">
                        <?php $aus_post_working_days = isset($general_settings['aus_post_working_days'])? $general_settings['aus_post_working_days'] : array() ; ?>
                        <?php _e('Working Days','wf-shipping-auspost') ?>
                        <span class="woocommerce-help-tip australila_post_estimated_delivery" data-tip="<?php _e('Configure the regular working days. The estimated delivery date will be calculated based on this. The shipment is supposed to happen only on working days.','wf-shipping-auspost') ?>"></span></br>
                        <select class="chosen_select"  style="width: 50%;"  name="wf_australia_post_working_days[]" multiple="multiple">
                            <option value="Sun" <?php echo (in_array('Sun',$aus_post_working_days))? 'selected':'' ?> ><?php _e('Sunday', 'wf-shipping-auspost') ?></option>
                            <option value="Mon" <?php echo (in_array('Mon',$aus_post_working_days))? 'selected':'' ?> ><?php _e('Monday', 'wf-shipping-auspost') ?></option>
                            <option value="Tue" <?php echo (in_array('Tue',$aus_post_working_days))? 'selected':'' ?> ><?php _e('Tuesday', 'wf-shipping-auspost') ?></option>
                            <option value="Wed" <?php echo (in_array('Wed',$aus_post_working_days))? 'selected':'' ?> ><?php _e('Wednesday', 'wf-shipping-auspost') ?></option>
                            <option value="Thu" <?php echo (in_array('Thu',$aus_post_working_days))? 'selected':'' ?> ><?php _e('Thursday', 'wf-shipping-auspost') ?></option>
                            <option value="Fri" <?php echo (in_array('Fri',$aus_post_working_days))? 'selected':'' ?> ><?php _e('Friday', 'wf-shipping-auspost') ?></option>
                            <option value="Sat" <?php echo (in_array('Sat',$aus_post_working_days))? 'selected':'' ?> ><?php _e('Saturday', 'wf-shipping-auspost') ?></option> 
                        </select>
            </fieldset>            
            <label for="wf_australia_cut_off_time_lable" class="australila_post_estimated_delivery">
            <fieldset style="padding:3px;" class="australila_post_estimated_delivery">
                <?php _e('Cut-off Time','wf-shipping-auspost') ?> <span class="woocommerce-help-tip australila_post_estimated_delivery" data-tip="<?php _e('Configure the cut-off time for your shipment. The orders placed after the cut-off time will be shipped on the next working day. The estimated delivery date will be displayed based on this.','wf-shipping-auspost') ?>"></span> </br>
                <input class="rates_tab_field" type="time" name="wf_australia_post_cut_off_time" id="wf_australia_post_cut_off_time"  value="<?php echo (isset($general_settings['aus_post_cut_off_time'])) ? $general_settings['aus_post_cut_off_time'] : ''; ?>" placeholder="Cut-off_time"> 
            </fieldset>
            <fieldset style="padding:3px;" class="australila_post_estimated_delivery">
                <?php _e('Lead Time','wf-shipping-auspost') ?> <span class="woocommerce-help-tip australila_post_estimated_delivery" data-tip="<?php _e('Add the number of days before you can initiate the delivery process.','wf-shipping-auspost') ?>"></span> </br>
                <input class="" type="number" name="wf_australia_post_estimated_delivery_lead_time" id="wf_australia_post_estimated_delivery_lead_time"  value="<?php echo (isset($general_settings['aus_post_estimated_delivery_lead_time'])) ? $general_settings['aus_post_estimated_delivery_lead_time'] : ''; ?>" style="width:70px;" > 
            </fieldset>
            <fieldset style="padding:3px;" class="australila_post_estimated_delivery">
                <?php _e('View Option','wf-shipping-auspost') ?> <span class="woocommerce-help-tip australila_post_estimated_delivery" data-tip="<?php _e("Choose the format to display estimated delivery date - 'Days' or 'Date'",'wf-shipping-auspost') ?>"></span> </br>
                <div style="padding-top:5px ;">
                <input class="" type="radio" name="wf_australia_post_estimated_delivery_view_option" id="wf_australia_post_estimated_delivery_view_option_days"  value="days" <?php echo ((isset($general_settings['aus_post_estimated_delivery_view_option']) && $general_settings['aus_post_estimated_delivery_view_option'] == 'days') || (!isset($general_settings['aus_post_estimated_delivery_view_option']))   ) ? 'checked' : ''; ?> >
                <label for="Days" style="padding-right:5px ;">Days</label>
                <input class="" type="radio" name="wf_australia_post_estimated_delivery_view_option" id="wf_australia_post_estimated_delivery_view_option_date"  value="date" <?php echo (isset($general_settings['aus_post_estimated_delivery_view_option']) && $general_settings['aus_post_estimated_delivery_view_option'] == 'date' ) ? 'checked' : ''; ?> >  
                <label for="Date">Date</label>
                </div>
            </fieldset>
        </td>
    </tr>
    <tr valign="top">
        <td style="width:30%;font-weight:800;">
            <label for="wf_australia_post_show_insurance_checkout_field"><?php _e('Enable Checkout fields', 'wf-shipping-auspost') ?></label>
        </td>
        <td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
            <fieldset style="padding:3px;">
                <input class="input-text regular-input " type="checkbox" name="wf_australia_post_show_insurance_checkout_field" id="wf_australia_post_show_insurance_checkout_field" style="" value="yes" <?php echo (isset($general_settings['show_insurance_checkout_field']) && $general_settings['show_insurance_checkout_field'] === 'yes') ? 'checked' : ''; ?> placeholder=""> <?php _e('Extra Cover', 'wf-shipping-auspost') ?> <span class="woocommerce-help-tip" data-tip="<?php _e('Enable this field to let customers choose Extra Cover option', 'wf-shipping-auspost') ?>"></span>
            </fieldset>
            <?php 
                if(!$this->contracted_rates){
                    ?>    
                        <fieldset style="padding:3px;">
                            <input class="input-text regular-input " type="checkbox" name="wf_australia_post_show_authority_to_leave_checkout_field" id="wf_australia_post_show_authority_to_leave_checkout_field" style="" value="yes" <?php echo (isset($general_settings['show_authority_to_leave_checkout_field']) && $general_settings['show_authority_to_leave_checkout_field'] === 'yes') ? 'checked' : ''; ?> placeholder=""> <?php _e('Authority To Leave', 'wf-shipping-auspost') ?> <span class="woocommerce-help-tip" data-tip="<?php _e('Enable this field to let customers choose whether they want to apply Authority To Leave option', 'wf-shipping-auspost') ?>"></span>
                        </fieldset>
                        <fieldset style="padding:3px;">
                            <input class="input-text regular-input " type="checkbox" name="wf_australia_post_show_signature_required_field" id="wf_australia_post_show_signature_required_field" style="" value="yes" <?php echo (isset($general_settings['show_signature_required_field']) && $general_settings['show_signature_required_field'] === 'yes') ? 'checked' : ''; ?> placeholder=""> <?php _e('Signature Required', 'wf-shipping-auspost') ?> <span class="woocommerce-help-tip" data-tip="<?php _e('Enable this field to let customers choose Signature Required on delivery.', 'wf-shipping-auspost') ?>"></span>
                        </fieldset>
                    <?php
                }
            ?>
        </td>
    </tr>
    <tr valign="top">
        <td style="width:30%;font-weight:800;">
            <label for="elex_australia_post_combined_export_tool"><?php _e('Combined Export Tool', 'wf-shipping-auspost') ?></label>
        </td>
        <td scope="row" class="titledesc" style="display: block;margin-bottom: 20px;margin-top: 3px;">
            <fieldset style="padding:3px;">
                <input class="input-text regular-input " type="checkbox" name="elex_australia_post_combined_export_tool_enable" id="elex_australia_post_combined_export_tool_enable" style="" value="yes" <?php echo (isset($general_settings['combined_export_tool_enable']) && $general_settings['combined_export_tool_enable'] === 'yes') ? 'checked' : ''; ?> > <?php _e('Enable', 'wf-shipping-auspost') ?> <span class="woocommerce-help-tip" data-tip="<?php _e('Enable AusPost Combined Export tool that retrieves estimated duties and taxes for the shipment.', 'wf-shipping-auspost') ?>"></span>
            </fieldset>
        </td>
        <td scope="row" class="titledesc elex_australia_post_combined_export_tool_show_rate_separate_td" style="display: block;margin-bottom: 20px;margin-top: 3px;">
            <fieldset style="padding:3px;">
                <input class="input-text regular-input " type="checkbox" name="elex_australia_post_combined_export_tool_show_rate_separate" id="elex_australia_post_combined_export_tool_show_rate_separate" style="" value="yes" <?php echo (isset($general_settings['combined_export_tool_show_rate_separate']) && $general_settings['combined_export_tool_show_rate_separate'] === 'yes') ? 'checked' : ''; ?> > <?php _e('Show rate', 'wf-shipping-auspost') ?> <span class="woocommerce-help-tip" data-tip="<?php _e('Enable this checkbox to show the breakdown of estimated duties and taxes on Cart and Checkout pages.', 'wf-shipping-auspost') ?>"></span>
            </fieldset>
        </td>
    </tr>
    <tr valign="top">
        <td colspan="2">
            <?php
            include_once('wf_html_services.php');
            ?>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="text-align:right;padding-right: 10%;">
            <br />
            <input type="submit" value="<?php _e('Save Changes', 'wf-shipping-auspost') ?>" class="button button-primary" name="wf_aus_rates_save_changes_button">
        </td>
    </tr>
</table>
<script type="text/javascript">
	jQuery(document).ready(function( $ ) {
			jQuery('html').on('click',function(e){
				jQuery(window).off('beforeunload');
				window.onbeforeunload = null;
			});
    });
</script>