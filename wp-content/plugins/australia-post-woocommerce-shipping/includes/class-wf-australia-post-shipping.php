<?php
/*Making compatible with PHP 7.1 later versions*/
if (version_compare(phpversion(), '7.1', '>=')) {
    ini_set('serialize_precision', -1); // Avoiding adding of unnecessary 17 decimal places resulted from json_encode
}

class wf_australia_post_shipping extends WC_Shipping_Method
{
    const API_HOST = 'digitalapi.auspost.com.au';
    const API_BASE_URL = '/test/shipping/v1/';

    private $endpoints = array(
        'calculation'   => 'https://digitalapi.auspost.com.au/postage/{type}/{doi}/calculate.json',
        'services'      => 'https://digitalapi.auspost.com.au/postage/{type}/{doi}/service.json',
        'getAccounts'   => 'https://digitalapi.auspost.com.au/shipping/v1/accounts/'
    );

    private $sod_cost = array('domestic' => 2.95, 'international' => 5.49); // Signature on delivery charges
    private $extra_cover_cost = array('domestic' => 1.5, 'international' => 3.5); // Extra cover costs
    private $additional_extra_cover_cost = array('international' => 3.5);
    private $found_rates;
    private $rate_cache;

    private $services = array(); // these services are defined statically
    private $extra_cover = array();
    private $delivery_confirmation = array();

    /**
     * __construct function.
     *
     * @access public
     * @return void
     */
    public function __construct()
    {
        $this->id = WF_AUSTRALIA_POST_ID;
        $this->method_title = __('Australia Post', 'wf-shipping-auspost');
        $this->method_description = __('', 'wf-shipping-auspost');
        if (!class_exists('WF_auspost_non_contracted_services')) {
            include_once('settings/class_wf_auspost_non_contracted_services.php');
        }

        $auspost_services_obj = new WF_auspost_non_contracted_services();
        /** Services called from 'services' API without options */
        $this->services = $auspost_services_obj->get_services(); // these services are defined statically
        $this->extra_cover = $auspost_services_obj->get_extra_cover();
        $this->delivery_confirmation = $auspost_services_obj->get_delivery_confirmation();
        $this->non_contracted_alternate_services = $auspost_services_obj->get_non_contrcated_alternate_services();
        $this->init();
    }

    /**
     * init function.
     *
     * @access public
     * @return void
     */
    private function init()
    {
        include_once('data-wf-default-values.php');
        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
        $this->general_settings = get_option('woocommerce_wf_australia_post_settings');

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->availability = $this->get_option('availability');
        $this->countries = $this->get_option('countries');
        $this->origin = $this->get_option('origin');
        $option_api_key = $this->get_option('api_key');
        $this->api_key = empty($option_api_key) ? '8fd6e23e-15fd-4e87-b7a2-ba557b0ff0dd' : $option_api_key;

        $this->contracted_rates = $this->get_option('contracted_rates') == 'yes' ? true : false;

        wp_localize_script('elex-auspost-custom', 'elex_auspost_custom', array('contracted_rates' => ''));
        $option_contracted_api_mode = $this->get_option('contracted_api_mode');
        $this->contracted_api_mode = isset($option_contracted_api_mode) ? $option_contracted_api_mode : 'test';

        $option_api_pwd = $this->get_option('api_pwd');
        $option_api_account_no = $this->get_option('api_account_no');

        $this->api_pwd_auspost = !empty($option_api_pwd) ?  $option_api_pwd : '';
        $this->api_pwd_auspost = str_replace('&lt;', '<', $this->api_pwd_auspost);
        $this->api_pwd_auspost = str_replace('&gt;', '>', $this->api_pwd_auspost);
        $this->api_account_no_auspost = !empty($option_api_account_no) ? $option_api_account_no : '';
        $this->rate_type_auspost = true;
        $this->api_pwd_starTrack = '';
        $this->api_account_no_starTrack = '';
        $this->rate_type = '';
        $this->startrack_enabled = false;
        $this->api_key_starTrack = $this->api_key;
        if (isset($this->general_settings['wf_australia_post_starTrack_rates_selected']) && ($this->general_settings['wf_australia_post_starTrack_rates_selected'] == true)) {
            $this->api_pwd_starTrack = $this->settings['wf_australia_post_starTrack_api_pwd'];
            $this->api_account_no_starTrack = $this->settings['wf_australia_post_starTrack_api_account_no'];
            $this->rate_type = 'startrack';
            $this->startrack_enabled = true;
            if (isset($this->settings['wf_australia_post_starTrack_api_key_enabled']) && $this->settings['wf_australia_post_starTrack_api_key_enabled']) {
                $this->api_key_starTrack = $this->settings['wf_australia_post_starTrack_api_key'];
            } 
        }

        $packing_method_settings = $this->get_option('packing_method');
        $this->packing_method = !empty($packing_method_settings) ? $packing_method_settings : 'per_item';
        $this->group_shipping = $this->get_option('group_shipping');
         if ($this->group_shipping && $this->group_shipping == 'yes') {
            $this->group_shipping_enabled = true;
        }else{
            $this->group_shipping_enabled = false;
        }
        $this->boxes = $this->get_option('boxes');
        $this->custom_services = $this->get_option('services');
        $this->custom_sub_services = isset($this->general_settings['sub_services']) ? $this->general_settings['sub_services'] : array(); // alternate services
        $this->custom_startrack_services = $this->get_option('startrack_services');
        $this->offer_rates = $this->get_option('offer_rates');
        $this->estimated_delivery_date_enabled = $this->get_option('aus_post_estimated_delivery_date_enabled');
        $this->debug = $this->get_option('debug_mode') == 'yes' ? true : false;
        $this->alternate_services = $this->get_option('disable_alternate_services') == 'yes' ? false : true;
        $this->max_weight = $this->get_option('max_weight');
        $this->weight_unit = get_option('woocommerce_weight_unit');
        $this->dimension_unit = get_option('woocommerce_dimension_unit');
        $this->weight_packing_process = !empty($this->settings['weight_packing_process']) ? $this->settings['weight_packing_process'] : 'pack_descending'; // This feature will be implementing in next version
        $this->previous_rate_cost_stored = 0;
        $this->shipment_type = ''; // domestic or international
        $this->packages = array();
        $this->selected_shipment_service = '';
        $this->insurance_requested_at_checkout = false;
        $this->autthority_to_leave_requested_at_checkout = false;
        $this->signature_requested_at_checkout = false;
        $this->vendor_check = (in_array('multi-vendor-add-on-for-thirdparty-shipping/multi-vendor-add-on-for-thirdparty-shipping.php', apply_filters('active_plugins', get_option('active_plugins'))) && (isset($this->settings['vendor_check']) && ($this->settings['vendor_check'] == 'yes'))) ? TRUE : FALSE;
        $this->vedor_api_key_enable = (in_array('multi-vendor-add-on-for-thirdparty-shipping/multi-vendor-add-on-for-thirdparty-shipping.php', apply_filters('active_plugins', get_option('active_plugins'))) && (get_option('wc_settings_wf_vendor_addon_allow_vedor_api_key') == 'yes')) ? TRUE : FALSE;
        $this->is_woocommerce_composite_products_installed = (in_array('woocommerce-composite-products/woocommerce-composite-products.php', get_option('active_plugins'))) ? true : false;
        $this->is_elex_combined_export_tool_enable = (isset($this->general_settings['combined_export_tool_enable']) && $this->general_settings['combined_export_tool_enable'] == 'yes') ? 'yes' : false; 
        $this->is_elex_combined_export_tool_show_rate_separate = (isset($this->general_settings['combined_export_tool_show_rate_separate']) && $this->general_settings['combined_export_tool_show_rate_separate'] == 'yes' && $this->is_elex_combined_export_tool_enable == 'yes') ? 'yes' : false;
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'clear_transients'));
    }

    /**
     * function to get eligible postage products for a given contracted account number
     * @access private
     */

    public function get_services($endpoint, $account_number, $account_password, $api_key = NULL)
    {
        if (empty($account_number) || $account_number == NULL || empty($account_password) || $account_password == NULL) {
            return json_encode(array());
        }
        $header = '';
        $responseBody = '';
        $account_password = str_replace('&lt;', '<', $account_password);
        $account_password = str_replace('&gt;', '>', $account_password);
        $api_key = $api_key ? $api_key : $this->api_key;
        $args = array(
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => array(
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
                'Account-Number' => $account_number,
                'Authorization' => 'Basic ' . base64_encode($api_key . ":" . $account_password)
            ),
        );

        $response = wp_remote_get($endpoint, $args);
        if (is_array($response)) {
            $header = $response['headers']; // array of http header lines
            $responseBody = $response['body']; // use the content
        }
        return $responseBody;
    }

    /**
     * Output a message
     */
    public function debug($message, $type = 'notice')
    {
        if ($this->debug) {
            if (version_compare(WOOCOMMERCE_VERSION, '2.1', '>=')) {
                wc_add_notice($message, $type);
            } else {
                global $woocommerce;

                $woocommerce->add_message($message);
            }
        }
    }

    /**
     * environment_check function.
     *
     * @access public
     * @return void
     */
    private function environment_check()
    {
        global $woocommerce;

        if (get_woocommerce_currency() != "AUD") {
            echo '<div class="error">
                <p>' . __('Australia Post requires that the currency is set to Australian Dollars.', 'wf-shipping-auspost') . '</p>
            </div>';
        } elseif ($woocommerce->countries->get_base_country() != "AU") {
            echo '<div class="error">
                <p>' . __('Australia Post requires that the base country/region is set to Australia.', 'wf-shipping-auspost') . '</p>
            </div>';
        }
    }

    /**
     * admin_options function.
     *
     * @access public
     * @return void
     */
    public function admin_options()
    {
        // Check users environment supports this method
        $this->environment_check();

        // Show settings
        parent::admin_options();
    }

    /**
     * validate_box_packing_field function.
     *
     * @access public
     * @param mixed $key
     * @return void
     */
    public function validate_box_packing_field($key)
    {
        if (!isset($_POST['boxes_outer_length']))
            return;

        $boxes_outer_length = $_POST['boxes_outer_length'];
        $boxes_outer_width = $_POST['boxes_outer_width'];
        $boxes_outer_height = $_POST['boxes_outer_height'];
        $boxes_inner_length = $_POST['boxes_inner_length'];
        $boxes_inner_width = $_POST['boxes_inner_width'];
        $boxes_inner_height = $_POST['boxes_inner_height'];
        $boxes_box_weight = $_POST['boxes_box_weight'];
        $boxes_max_weight = $_POST['boxes_max_weight'];
        $boxes_is_letter = isset($_POST['boxes_is_letter']) ? $_POST['boxes_is_letter'] : array();

        $boxes = array();

        for ($i = 0; $i < sizeof($boxes_outer_length); $i++) {

            if ($boxes_outer_length[$i] && $boxes_outer_width[$i] && $boxes_outer_height[$i]) {

                $outer_dimensions = array_map('floatval', array($boxes_outer_length[$i], $boxes_outer_height[$i], $boxes_outer_width[$i]));
                $inner_dimensions = array_map('floatval', array($boxes_inner_length[$i], $boxes_inner_height[$i], $boxes_inner_width[$i]));

                sort($outer_dimensions);
                sort($inner_dimensions);

                // Min sizes - girth min is 16
                $outer_girth = $outer_dimensions[0] + $outer_dimensions[0] + $outer_dimensions[1] + $outer_dimensions[1];
                $inner_girth = $inner_dimensions[0] + $inner_dimensions[0] + $inner_dimensions[1] + $inner_dimensions[1];

                if ($outer_girth < 16) {
                    if ($outer_dimensions[0] < 4)
                        $outer_dimensions[0] = 4;

                    if ($outer_dimensions[1] < 5)
                        $outer_dimensions[1] = 5;
                }

                if ($inner_girth < 16) {
                    if ($inner_dimensions[0] < 4)
                        $inner_dimensions[0] = 4;

                    if ($inner_dimensions[1] < 5)
                        $inner_dimensions[1] = 5;
                }

                if ($outer_dimensions[2] > 105)
                    $outer_dimensions[2] = 105;

                if ($inner_dimensions[2] > 105)
                    $inner_dimensions[2] = 105;

                $outer_length = $outer_dimensions[2];
                $outer_height = $outer_dimensions[0];
                $outer_width = $outer_dimensions[1];

                $inner_length = $inner_dimensions[2];
                $inner_height = $inner_dimensions[0];
                $inner_width = $inner_dimensions[1];

                if (empty($inner_length) || $inner_length > $outer_length)
                    $inner_length = $outer_length;

                if (empty($inner_height) || $inner_height > $outer_height)
                    $inner_height = $outer_height;

                if (empty($inner_width) || $inner_width > $outer_width)
                    $inner_width = $outer_width;

                $weight = floatval($boxes_max_weight[$i]);

                if ($weight > 22 || empty($weight))
                    $weight = 22;

                $boxes[] = array(
                    'outer_length' => $outer_length,
                    'outer_width' => $outer_width,
                    'outer_height' => $outer_height,
                    'inner_length' => $inner_length,
                    'inner_width' => $inner_width,
                    'inner_height' => $inner_height,
                    'box_weight' => floatval($boxes_box_weight[$i]),
                    'max_weight' => $weight,
                    'is_letter' => isset($boxes_is_letter[$i]) ? true : false
                );
            }
        }

        return $boxes;
    }

    /**
     * clear_transients function.
     *
     * @access public
     * @return void
     */
    public function clear_transients()
    {
        delete_transient('wf_australia_post_quotes');
    }

    public function generate_activate_box_html()
    {
        ob_start();
        $plugin_name = 'australiapost';
        include('wf_api_manager/html/html-wf-activation-window.php');
        return ob_get_clean();
    }

    public function generate_wf_aus_tab_box_html()
    {

        $tab = (!empty($_GET['subtab'])) ? esc_attr($_GET['subtab']) : 'general';

        echo '
                <div class="wrap">
                    <style>
                        .woocommerce-help-tip{color:darkgray !important;}
                        <style>
                        .woocommerce-help-tip {
                            position: relative;
                            display: inline-block;
                            border-bottom: 1px dotted black;
                        }

                        .woocommerce-help-tip .tooltiptext {
                            visibility: hidden;
                            width: 120px;
                            background-color: black;
                            color: #fff;
                            text-align: center;
                            border-radius: 6px;
                            padding: 5px 0;

                            /* Position the tooltip */
                            position: absolute;
                            z-index: 1;
                        }

                        .woocommerce-help-tip:hover .tooltiptext {
                            visibility: visible;
                        }
                        </style>
                    </style>
                    <hr class="wp-header-end">';
        $this->wf_aus_shipping_page_tabs($tab);
        if ($tab != 'auto-generate-add-on') {
            echo '<script>
                jQuery(document).ready(function(){
                    jQuery(".aus_post_addon_auto_tab_field").closest("tr,h3").hide();
                    jQuery(".aus_post_addon_auto_tab_field").next("p").hide();
                    jQuery(".woocommerce-save-button").hide();
                });
            </script>';
        }
        if ($tab != 'return') {
            echo '<script>
                jQuery(document).ready(function(){
                    jQuery(".elex_australia_post_return_tab_field").closest("tr,h3").hide();
                    jQuery(".elex_australia_post_return_tab_field").next("p").hide();
                    jQuery(".woocommerce-save-button").hide();
                });
            </script>';
        }
        if ($tab == 'auto-generate-add-on' || $tab == 'return') {
            echo '<script>
                jQuery(document).ready(function(){
                    jQuery(".woocommerce-save-button").show();
                });
            </script>';
        }
        // Australia Post Return Label Licence activation 
        if ($tab == 'return') {

            echo '<div style ="padding-top: 10px;"><ul class="subsubsub" ><li><a href="#" class="elex_australia_post_return_label_general_section">General</a> | </li><li><a href="#" class="elex_australia_post_return_label_license_section">Licence </a></li></ul></div>';
            echo '</br>';
            $plugin_name = "elex-australia-post-return-label-addon";
            include_once(ELEX_AUSTRALIA_POST_RETURN_LABEL_ADDON_PATH . 'includes/wf_api_manager/html/html-wf-activation-window.php');
            echo '<script>
                    jQuery(document).ready(function(){
                        jQuery(".activation_window").hide();
                        jQuery(".elex_australia_post_return_label_general_section").addClass("current");
                    });
                  </script>';
        }


        switch ($tab) {
            case "general":
                echo '<div class="table-box table-box-main" id="general_section" style="margin-top: 0px;
                        border: 1px solid #ccc;border-top: unset !important;padding: 5px;">';
                require_once('settings/aus_general_settings.php');
                echo '</div>';
                break;
            case "rates":
                echo '<div class="table-box table-box-main" id="rates_section" style="margin-top: 0px;
                        border: 1px solid #ccc;border-top: unset !important;padding: 5px;">';
                require_once('settings/aus_rates_settings.php');

                echo '</div>';
                break;
            case "labels":
                echo '<div class="table-box table-box-main" id="labels_section" style="margin-top: 0px;
                        border: 1px solid #ccc;border-top: unset !important;padding: 5px;">';
                require_once('settings/aus_label_settings.php');
                echo '</div>';
                break;
            case "packing":
                echo '<div class="table-box table-box-main" id="packing_section" style="margin-top: 0px;
                        border: 1px solid #ccc;border-top: unset !important;padding: 5px;">';
                require_once('settings/aus_packing_settings.php');
                echo '</div>';
                break;
            case "licence":
                echo '<div class="table-box table-box-main" id="licence_section" style="margin-top: 0px;
                        border: 1px solid #ccc;border-top: unset !important;padding: 5px;"><br>';
                $plugin_name = 'australiapost';
                include_once('wf_api_manager/html/html-wf-activation-window.php');
                include('html-wf-australia-post-addons.php');

                echo '</div>';
                break;
        }
        echo '
                </div>';
    }

    public function wf_aus_shipping_page_tabs($current = 'general')
    {
        $activation_check = get_option('australiapost_activation_status');
        if (!empty($activation_check) && $activation_check === 'active') {
            $acivated_tab_html = "<small style='color:green;font-size:xx-small;'>(Activated)</small>";
        } else {
            $acivated_tab_html = "<small style='color:red;font-size:xx-small;'>(Activate)</small>";
        }

        $image = "<small style='color:green;font-size:xx-small;'>(Settings)</small>";
        $tabs = array(
            'general'   => __("General", 'wf-shipping-auspost'),
            'rates'     => __("Rates & Services", 'wf-shipping-auspost'),
            'labels'    => __("Label & Tracking", 'wf-shipping-auspost'),
            'packing'   => __("Packaging", 'wf-shipping-auspost'),
            'licence'   => __("Licence " . $acivated_tab_html, 'wf-shipping-auspost'),
        );
        if (ELEX_AUSTRALIA_POST_RETURN_ADDON_STATUS) {
            $tabs['return'] = __("Return Label", 'wf-shipping-auspost');
        }
        if (ELEX_AUS_POST_AUTO_LABEL_GENERATE_ADDON_WOOCOMMERCE_EXTENSION) {
            $tabs['auto-generate-add-on'] =  __("Auto Label Generate Add-on " . $image, 'wf-shipping-auspost');
        }
        $html = '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $tab => $name) {
            $class = ($tab == $current) ? 'nav-tab-active' : '';
            $style = ($tab == $current) ? 'border-bottom: 1px solid transparent !important;' : '';
            $html .= '<a style="text-decoration:none !important;' . $style . '" class="nav-tab ' . $class . '" href="?page=' . wf_get_settings_url() . '&tab=shipping&section=wf_australia_post&subtab=' . $tab . '">' . $name . '</a>';
        }
        $html .= '</h2>';
        echo $html;
    }

    /**
     * init_form_fields function.
     *
     * @access public
     * @return void
     */
    public function init_form_fields()
    {
        global $woocommerce;
        if (isset($_GET['page']) && $_GET['page'] === 'wc-settings') {
            $this->form_fields = array(
                'wf_aus_tab_box_key' => array(
                    'type' => 'wf_aus_tab_box'
                ),
            );
            //Return Label Add-on.
            if (ELEX_AUSTRALIA_POST_RETURN_ADDON_STATUS && ELEX_AUSTRALIA_POST_RETURN_LABEL_ADDON_PATH) {
                $add_on_fields = include(ELEX_AUSTRALIA_POST_RETURN_LABEL_ADDON_PATH . 'includes/data-wf-settings.php');
                if (is_array($add_on_fields)) {
                    $this->form_fields = array_merge($this->form_fields, $add_on_fields);
                }
            }
            //Auto Label Generate Add-on.
            if (ELEX_AUS_POST_AUTO_LABEL_GENERATE_ADDON_WOOCOMMERCE_EXTENSION) {
                $auto_add_on_fields = include(ELEX_AUS_POST_AUTO_LABEL_GENERATE_ADDON_WOOCOMMERCE_EXTENSION_PATH . 'includes/data-wf-settings.php');
                if (is_array($auto_add_on_fields)) {
                    $this->form_fields = array_merge($this->form_fields, $auto_add_on_fields);
                }
            }
        }
    }

    /**
     * Will girth fit
     *
     * @param  [type] $item_w
     * @param  [type] $item_h
     * @param  [type] $package_w
     * @param  [type] $package_h
     * @return bool
     */
    public function girth_validation($item_l, $item_w, $item_h , $package_l, $package_w)
    {
        if(!$item_h){
            $item_h = 0;
        }

        // Check max height
        if ($item_h > ($package_w / 2))
            return false;

        // Girth = around the item
        $item_girth = $item_w + $item_h;

        if ($item_girth > $package_w)
            return false;

        // Girth 2 = around the item
        $item_girth = $item_l + $item_h;

        if ($item_girth > $package_l)
            return false;

        return true;
    }

    /**
     * See if rate is satchel
     *
     * @return boolean
     */
    public function is_satchel($code)
    {
        return strpos($code, '_SATCHEL_') !== false;
    }

    /**
     * See if rate is letter
     *
     * @return boolean
     */
    public function is_letter($code)
    {
        return strpos($code, '_LETTER_') !== false;
    }

    /**
     * function to get highest dimension among all the packed products
     * @access public
     */

    public function return_highest($dimension_array)
    {
        $dimension = 0;
        $dimension = round(max($dimension_array), 2);
        return $dimension;
    }


    /**
     *  function.
     *
     * @access public
     * @param mixed $package
     * @return void
     */
    public function calculate_shipping($package = array())
    {
        global $woocommerce;
        $package = apply_filters('elex_aus_post_before_calculate_shipping', $package);
        // Checking the real time service option is activated or not
        if (!isset($this->general_settings['enabled']) || empty($this->general_settings['enabled']))
            return;

        // Checking the services are saved in the settings or not
        $is_services_saved_in_settings = get_option('services_saved_in_settings');

        if ($is_services_saved_in_settings == false) {
            return;
        }

        $str = array();

        if (isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $str);
        }

        if (!empty($str)) {
            $this->insurance_requested_at_checkout = isset($str['auspost_insurance']) ? $str['auspost_insurance'] : false;
            update_option('auspost_extra_cover_checkout', $this->insurance_requested_at_checkout);
            if (!$this->contracted_rates) {
                $this->authority_to_leave_requested_at_checkout = isset($str['auspost_authority_to_leave']) ? $str['auspost_authority_to_leave'] : 'no';
                update_option('auspost_authority_to_leave_checkout', $this->authority_to_leave_requested_at_checkout);
                $this->signature_requested_at_checkout = false;
                if (isset($str['auspost_signature'])) {
                    $this->signature_requested_at_checkout = $str['auspost_signature'];
                } else if ($this->settings['show_signature_required_field'] == 'yes') {
                    $this->signature_requested_at_checkout = 'no';
                }
                update_option('auspost_signature_required_checkout', $this->signature_requested_at_checkout);
            }
        } else if (!is_shop() && !is_cart()) {
            $this->insurance_requested_at_checkout = get_option('auspost_extra_cover_checkout');
            if (!$this->contracted_rates) {
                $this->signature_requested_at_checkout = get_option('auspost_signature_required_checkout');
            }
        }

        $this->is_international = ($package['destination']['country'] == 'AU') ? false : true;
        $this->found_rates = array();
        $this->rate_cache = get_transient('wf_australia_post_quotes');
        $headers = $this->get_request_header();
        $package_requests = $this->get_package_requests($package);
        $user_stratrack_services = (isset($this->general_settings['startrack_services']) && !empty($this->general_settings['startrack_services'])) ? $this->general_settings['startrack_services'] : array();
        $settings_services = array_merge($this->general_settings['services'], $user_stratrack_services);
        $custom_services = array();
        $package_req = array();
        $endpoint = '';
        $rates = array();
        $extra_cover_package = 0;

        // Prepare endpoints
        $letter_services_endpoint = str_replace(array('{type}', '{doi}'), array('letter', (!$this->is_international ? 'domestic' : 'international')), $this->endpoints['services']);
        $letter_calculation_endpoint = str_replace(array('{type}', '{doi}'), array('letter', (!$this->is_international ? 'domestic' : 'international')), $this->endpoints['calculation']);
        $services_endpoint = str_replace(array('{type}', '{doi}'), array('parcel', (!$this->is_international ? 'domestic' : 'international')), $this->endpoints['services']);
        $calculation_endpoint = str_replace(array('{type}', '{doi}'), array('parcel', (!$this->is_international ? 'domestic' : 'international')), $this->endpoints['calculation']);

        if ($this->contracted_rates) {
            $postage_products_auspost = array();
            $postage_products_startrack = array();
            $get_accounts_endpoint_auspost = 'https://' . self::API_HOST . self::API_BASE_URL . 'accounts/' . $this->api_account_no_auspost;
            $get_accounts_endpoint_startrack = 'https://' . self::API_HOST . self::API_BASE_URL . 'accounts/' . $this->api_account_no_starTrack;

            if ($this->contracted_api_mode == 'live') {
                $get_accounts_endpoint_auspost = str_replace('test/', '', $get_accounts_endpoint_auspost);
                $get_accounts_endpoint_startrack = str_replace('test/', '', $get_accounts_endpoint_startrack);
            }

            $contracted_account_details_auspost = $this->get_services($get_accounts_endpoint_auspost, $this->api_account_no_auspost, $this->api_pwd_auspost);
            $contracted_account_details_startrack = $this->get_services($get_accounts_endpoint_startrack, $this->api_account_no_starTrack, $this->api_pwd_starTrack, $this->api_key_starTrack);

            $contracted_account_details_auspost = json_decode($contracted_account_details_auspost, true);
            $contracted_account_details_startrack = json_decode($contracted_account_details_startrack, true);


            if (isset($contracted_account_details_auspost['postage_products']) && !empty($contracted_account_details_auspost['postage_products'])) {
                $postage_products_auspost = $contracted_account_details_auspost['postage_products'];
            }

            if (isset($contracted_account_details_startrack['postage_products']) && !empty($contracted_account_details_startrack['postage_products'])) {
                $postage_products_startrack = $contracted_account_details_startrack['postage_products'];
            }
            $from_address = false;
            $vendor_user_id = false;
            $vendor_from_address = false;
            // Compatibility of Australia Post with ELEX Multivendor Addon 
            if ($this->vendor_check) {

                $args = array(
                    'role'    => 'seller',
                    'fields' => 'ID',
                );
                $users_ids = get_users($args);
                $vendor_user_id = $package['seller_id'];
                if (in_array($vendor_user_id, $users_ids)) {

                    $from_address = $package['origin'];
                    $vendor_from_address = array(
                        "suburb" => $from_address['city'],
                        "state" => $from_address['state'],
                        "postcode" => $from_address['postcode']
                    );
                    if ($this->vedor_api_key_enable) {
                        $vendor_elex_australia_post_api_key = get_the_author_meta('vendor_elex_australia_post_api_key', $vendor_user_id);
                        $vendor_elex_australia_post_account_number = get_the_author_meta('vendor_elex_australia_post_account_number', $vendor_user_id);
                        $vendor_elex_australia_post_api_password = get_the_author_meta('vendor_elex_australia_post_api_password', $vendor_user_id);
                        $vendor_elex_australia_post_startrack_account_number = get_the_author_meta('vendor_elex_australia_post_startrack_account_number', $vendor_user_id);
                        $vendor_elex_australia_post_startrack_api_password = get_the_author_meta('vendor_elex_australia_post_startrack_api_password', $vendor_user_id);

                        $headers = $this->get_request_header($vendor_elex_australia_post_api_key);
                        if ($vendor_elex_australia_post_api_key) {
                            if ($vendor_elex_australia_post_account_number && $vendor_elex_australia_post_api_password) {
                                $get_accounts_endpoint_auspost = 'https://' . self::API_HOST . self::API_BASE_URL . 'accounts/' . $vendor_elex_australia_post_account_number;
                                if ($this->contracted_api_mode == 'live') {
                                    $get_accounts_endpoint_auspost = str_replace('test/', '', $get_accounts_endpoint_auspost);
                                }
                                $contracted_account_details_auspost = $this->get_services($get_accounts_endpoint_auspost, $vendor_elex_australia_post_account_number, $vendor_elex_australia_post_api_password, $vendor_elex_australia_post_api_key);
                                $contracted_account_details_auspost = json_decode($contracted_account_details_auspost, true);
                                if (isset($contracted_account_details_auspost['postage_products']) && !empty($contracted_account_details_auspost['postage_products'])) {
                                    $postage_products_auspost = $contracted_account_details_auspost['postage_products'];
                                }
                            }
                            if ($vendor_elex_australia_post_startrack_account_number && $vendor_elex_australia_post_startrack_api_password) {
                                $get_accounts_endpoint_startrack = 'https://' . self::API_HOST . self::API_BASE_URL . 'accounts/' . $vendor_elex_australia_post_startrack_account_number;
                                if ($this->contracted_api_mode == 'live') {
                                    $get_accounts_endpoint_startrack = str_replace('test/', '', $get_accounts_endpoint_startrack);
                                }
                                $contracted_account_details_startrack = $this->get_services($get_accounts_endpoint_startrack, $vendor_elex_australia_post_startrack_account_number, $vendor_elex_australia_post_startrack_api_password, $vendor_elex_australia_post_api_key);
                                $contracted_account_details_startrack = json_decode($contracted_account_details_startrack, true);
                                if (isset($contracted_account_details_startrack['postage_products']) && !empty($contracted_account_details_startrack['postage_products'])) {
                                    $postage_products_startrack = $contracted_account_details_startrack['postage_products'];
                                }
                            }
                        }
                    }
                }
            }

            $from_and_to = $this->get_request($package);

            $from_post_cod = str_replace(' ', '', strtoupper($this->origin));
            $to_post_cod = isset($from_and_to['to_postcode']) ? array('postcode' => $from_and_to['to_postcode']) : array('country' => $from_and_to['country_code']);
            $auspost_services_set = array();
            $startrack_services_set = array();
            if (is_array($package_requests)) {
                $package_requests_size = count($package_requests);
                $count_package_requests = 0;
                if ($this->group_shipping_enabled == false || $this->is_international) {
                    $estimated_duties_and_taxes_for_the_shipment_value_array = array();

                    foreach ($package_requests as $key => $package_request) {

                        if( $this->is_international && $this->is_elex_combined_export_tool_enable && $this->is_elex_combined_export_tool_enable == 'yes' ){
                            if(is_array($package_request) && !empty($package_request) && isset($package_request['packed_products']) && is_array($package_request['packed_products']) && !empty($package_request['packed_products']) ){
                                $estimated_duties_and_taxes_for_the_shipment_request_body = $this->estimated_duties_and_taxes_for_the_shipment_request_body($package_request['packed_products'] , $package['destination']);
                            }
                            
                        }
                        $count_package_requests++;
                        $from_weight_unit = '';
                        if ($this->weight_unit != 'kg') {
                            $from_weight_unit = $this->weight_unit;
                        }

                        $from_dimension_unit = '';
                        if ($this->dimension_unit != 'cm') {
                            $from_dimension_unit = $this->dimension_unit;
                        }


                        $extra_cover_package = $package_request['InsuredValue']['Amount'] ? $package_request['InsuredValue']['Amount'] : 0;
                        /** 
                             Australia post one for normal request that will accept only kg and cm.
                                if it is a letter - thickness we need to use different API.that will accept only gm, mm.
                         */
                        if (isset($package_request['Dimensions']['Thickness'])) {
                            $package_req = array(
                                'weight' => round(wc_get_weight($package_request['Weight']['Value'], 'g', $from_weight_unit), 3),
                                'length' => round(wc_get_dimension($package_request['Dimensions']['Length'], 'mm', $from_dimension_unit), 1),
                                'width' => round(wc_get_dimension($package_request['Dimensions']['Width'], 'mm', $from_dimension_unit), 1),
                                'height' => round(wc_get_dimension($package_request['Dimensions']['Thickness'], 'mm', $from_dimension_unit), 1),
                            );
                        } else {
                            $package_req = array(
                                'weight' => round(wc_get_weight($package_request['Weight']['Value'], 'kg', $from_weight_unit), 3),
                                'length' => round(wc_get_dimension($package_request['Dimensions']['Length'], 'cm', $from_dimension_unit), 1),
                                'width' => round(wc_get_dimension($package_request['Dimensions']['Width'], 'cm', $from_dimension_unit), 1),
                                'height' => round(wc_get_dimension($package_request['Dimensions']['Height'], 'cm', $from_dimension_unit), 1),
                            );
                        }

                        $startrack_service_rates = array();

                        if ($this->rate_type == 'startrack' && !$this->is_international) {
                            $rates = array();
                            /*For StarTrack*/
                            if ($vendor_from_address) {
                                $from = $vendor_from_address;
                            } else {
                                $from = array(
                                    "suburb" => $this->settings['origin_suburb'],
                                    "state" => $this->settings['origin_state'],
                                    "postcode" => $this->settings['origin']
                                );
                            }
                            $to = array(
                                "suburb" => $package['destination']['city'],
                                "state" => $package['destination']['state'],
                                "postcode" => $package['destination']['postcode']
                            );

                            $service_rate_iteration = 0;
                            $startrack_service_rates_array = array();
                            foreach ($this->general_settings['startrack_services'] as $settings_service_key => $settings_service_value) {
                                $service_rate_iteration++;
                                if ($settings_service_value['enabled']) {
                                    /** 
                                     Australia post one for normal request that will accept only kg and cm.
                                    if it is a letter - thickness we need to use different API.that will accept only gm, mm.
                                     */
                                    if (isset($package_request['Dimensions']['Thickness'])) {
                                        $items_node = array(
                                            'weight' => round(wc_get_weight($package_request['Weight']['Value'], 'g', $from_weight_unit), 3),
                                            'length' => round(wc_get_dimension($package_request['Dimensions']['Length'], 'mm', $from_dimension_unit), 1),
                                            'width' => round(wc_get_dimension($package_request['Dimensions']['Width'], 'mm', $from_dimension_unit), 1),
                                            'height' => round(wc_get_dimension($package_request['Dimensions']['Height'], 'mm', $from_dimension_unit), 1)
                                        );
                                    } elseif (isset($package_request['cubic_volume']) && $package_request['cubic_volume'] > 0 && $this->packing_method == 'weight') {
                                        $items_node = array(
                                            'weight' => round(wc_get_weight($package_request['Weight']['Value'], 'kg', $from_weight_unit), 3),
                                            'cubic_volume' => (round($package_request['cubic_volume'], 3) > 0) ? round($package_request['cubic_volume'], 3) : 0.001,
                                        );
                                    } else {
                                        $items_node = array(
                                            'weight' => round(wc_get_weight($package_request['Weight']['Value'], 'kg', $from_weight_unit), 3),
                                            'length' => round(wc_get_dimension($package_request['Dimensions']['Length'], 'cm', $from_dimension_unit), 1),
                                            'width' => round(wc_get_dimension($package_request['Dimensions']['Width'], 'cm', $from_dimension_unit), 1),
                                            'height' => round(wc_get_dimension($package_request['Dimensions']['Height'], 'cm', $from_dimension_unit), 1)
                                        );
                                    }
                                    $items_node['product_id'] = $settings_service_key; // 'PRM',
                                    $items_node['packaging_type'] = !empty($package_request['pack_type']) ? $package_request['pack_type'] : 'ITM';
                                    $shipments = new stdClass();
                                    $shipments->from = $from;
                                    $shipments->to = $to;
                                    $shipments->items = array($items_node);
                                    $request_params = new stdClass();
                                    $request_params->shipments = array($shipments);
                                    $endpoint = 'https://' . self::API_HOST . self::API_BASE_URL . 'prices/shipments/';
                                    if (isset($vendor_elex_australia_post_startrack_account_number) && isset($vendor_elex_australia_post_startrack_api_password)) {
                                        $headers = $this->buildHttpHeaders($request_params, $vendor_elex_australia_post_startrack_account_number, $vendor_elex_australia_post_startrack_api_password, $vendor_elex_australia_post_api_key);
                                    } else {
                                        $headers = $this->buildHttpHeaders($request_params, $this->api_account_no_starTrack, $this->api_pwd_starTrack, $this->api_key_starTrack);
                                    }
                                    if ($this->settings['contracted_api_mode'] == 'live') {
                                        $endpoint = str_replace('test/', '', $endpoint);
                                    }

                                    $this->debug(__('StarTrack (AusPost) debug is ON - to hide these messages, disable <i>debug mode</i> in settings.', 'wf-shipping-auspost'));
                                    $this->debug('StarTrack (AusPost) Contracted rate REQUEST: <pre>' . print_r($request_params, true) . '</pre>');

                                    $startrack_service_rates_for_package = $this->get_contracted_rates($endpoint, $request_params, $headers, "startrack");

                                    if (empty($startrack_service_rates_for_package)) {
                                        return;
                                    }
                                    if (isset($startrack_service_rates_for_package['error_message'])) {
                                        $this->debug('StarTrack (AusPost) Contracted rate RESPONSE: <pre style="color:red;">' . print_r($startrack_service_rates_for_package['error_message'], true) . '</pre>');
                                    } else {
                                        $startrack_service_rates_array[] = $startrack_service_rates_for_package;
                                    }
                                    
                                }
                            }

                            if ($count_package_requests == 1) {
                                $startrack_service_rates = $startrack_service_rates_array;
                                foreach ($startrack_service_rates_array as $service_rate) {
                                    $shipment_items = $service_rate['shipment_summary']['items'];
                                    $service_id = $shipment_items[0]->product_id;
                                    $startrack_services_set[] = $service_id;
                                }
                            } else {
                                foreach ($startrack_service_rates_array as $service_rate) {
                                    $shipment_items = $service_rate['shipment_summary']['items'];
                                    $service_id = $shipment_items[0]->product_id;
                                    if (in_array($service_id, $startrack_services_set)) {
                                        $startrack_service_rates[] = $service_rate;
                                    }
                                }
                            }

                            if(!empty($startrack_service_rates)){
                                $this->debug('StarTrack (AusPost) Contracted rate RESPONSE: <pre>' . print_r($startrack_service_rates, true) . '</pre>');
                            }
                        }

                        if (!empty($this->api_pwd_auspost) && !empty($this->api_account_no_auspost)) {
                            /*For AusPost*/
                            $this->debug(__('AusPost debug is ON - to hide these messages, disable <i>debug mode</i> in settings.', 'wf-shipping-auspost'));
                            $rates = array();

                            if ($vendor_from_address) {
                                $from = $vendor_from_address;
                            } else {
                                $from = array(
                                    "suburb" => $this->settings['origin_suburb'],
                                    "state" => $this->settings['origin_state'],
                                    "postcode" => $this->settings['origin']
                                );
                            }

                            $to = array(
                                "suburb" => $package['destination']['city'],
                                "state" => $package['destination']['state'],
                                "postcode" => $package['destination']['postcode'],
                                "country" => $package['destination']['country']
                            );
                            $service_rate_iteration = 0;
                            $auspost_service_rates_response = array();
                            foreach ($this->general_settings['services'] as $settings_service_key => $settings_service_value) {
                                $service_rate_iteration++;
                                $flag = FALSE;
                                $domestic_product_ids = ['7E55', '3K55', '7I55', '7J55', '7C55'];
                                $internation_product_ids = ['RPI8', 'PTI8', 'ECM8', 'AIR8', 'PTI7', 'ECD8'];
                                if ($to['country'] == 'AU' && in_array($settings_service_key, $domestic_product_ids)) {
                                    $flag = TRUE;
                                }
                                if ($to['country'] != 'AU' && in_array($settings_service_key, $internation_product_ids)) {
                                    $flag = TRUE;
                                }
                                if (!in_array($settings_service_key, $internation_product_ids) && !in_array($settings_service_key, $domestic_product_ids)) {
                                    $flag = TRUE;
                                }
                                if ($settings_service_value['enabled'] && $flag) {
                                    /** 
                                     Australia post one for normal request that will accept only kg and cm.
                                    if it is a letter - thickness we need to use different API.that will accept only gm, mm.
                                     */
                                    if (isset($package_request['Dimensions']['Thickness'])) {
                                        $items_node = array(
                                            'weight' => round(wc_get_weight($package_request['Weight']['Value'], 'g', $from_weight_unit), 3),
                                            'length' => round(wc_get_dimension($package_request['Dimensions']['Length'], 'mm', $from_dimension_unit), 1),
                                            'width' => round(wc_get_dimension($package_request['Dimensions']['Width'], 'mm', $from_dimension_unit), 1),
                                            'height' => round(wc_get_dimension($package_request['Dimensions']['Height'], 'mm', $from_dimension_unit), 1)
                                        );
                                    } else {
                                        $items_node = array(
                                            'weight' => round(wc_get_weight($package_request['Weight']['Value'], 'kg', $from_weight_unit), 3),
                                            'length' => round(wc_get_dimension($package_request['Dimensions']['Length'], 'cm', $from_dimension_unit), 1),
                                            'width' => round(wc_get_dimension($package_request['Dimensions']['Width'], 'cm', $from_dimension_unit), 1),
                                            'height' => round(wc_get_dimension($package_request['Dimensions']['Height'], 'cm', $from_dimension_unit), 1)
                                        );
                                    }
                                    $items_node['product_id'] = $settings_service_key; // 'PRM',
                                    $items_node['packaging_type'] = !empty($package_request['pack_type']) ? $package_request['pack_type'] : 'ITM';
                                    $shipments = new stdClass();
                                    $shipments->from = $from;
                                    $shipments->to = $to;
                                    $shipments->items = array($items_node);
                                    $request_params = new stdClass();
                                    $request_params->shipments = array($shipments);
                                    $endpoint = 'https://' . self::API_HOST . self::API_BASE_URL . 'prices/shipments/';
                                    if ($this->vedor_api_key_enable && isset($vendor_elex_australia_post_account_number) && isset($vendor_elex_australia_post_api_password)) {
                                        $headers = $this->buildHttpHeaders($request_params, $vendor_elex_australia_post_account_number, $vendor_elex_australia_post_api_password, $vendor_elex_australia_post_api_key);
                                    } else {
                                        $headers = $this->buildHttpHeaders($request_params, $this->api_account_no_auspost, $this->api_pwd_auspost);
                                    }

                                    if ($this->settings['contracted_api_mode'] == 'live') {
                                        $endpoint = str_replace('test/', '', $endpoint);
                                    }
                                    $this->debug('AusPost Contracted rate REQUEST: <pre>' . print_r($request_params, true) . '</pre>');
                                    $auspost_service_rates_for_package = $this->get_contracted_rates($endpoint, $request_params, $headers, "auspost");
                                    if (empty($auspost_service_rates_for_package)) {
                                        return;
                                    }
                                    if (isset($auspost_service_rates_for_package['error_message'])) {
                                        $this->debug('AusPost Contracted rate RESPONSE: <pre style="color:red;">' . print_r($auspost_service_rates_for_package['error_message'], true) . '</pre>');
                                    } else {
                                        $auspost_service_rates_response[] = $auspost_service_rates_for_package;
                                        $this->debug('AusPost Contracted rate RESPONSE: <pre>' . print_r($auspost_service_rates_for_package, true) . '</pre>');
                                    }
                                }
                            }
                            $auspost_service_rates = array();
                            if ($count_package_requests == 1) {
                                $auspost_service_rates = $auspost_service_rates_response;
                                foreach ($auspost_service_rates_response as $auspost_service_rate) {
                                    $shipment_items = $auspost_service_rate['shipment_summary']['items'];
                                    $service_id = $shipment_items[0]->product_id;
                                    $auspost_services_set[] = $service_id;
                                }
                            } elseif (isset($auspost_service_rates_response) && !empty($auspost_service_rates_response)) {
                                foreach ($auspost_service_rates_response as $auspost_service_rate) {
                                    $shipment_items = $auspost_service_rate['shipment_summary']['items'];
                                    $service_id = $shipment_items[0]->product_id;
                                    if (in_array($service_id, $auspost_services_set)) {
                                        $auspost_service_rates[] = $auspost_service_rate;
                                    }
                                }
                            } else {
                                foreach ($auspost_service_rates_response as $auspost_service_rate) {
                                    $shipment_items = $auspost_service_rate['shipment_summary']['items'];
                                    $service_id = $shipment_items[0]->product_id;
                                    if (in_array($service_id, $auspost_services_set)) {
                                        $auspost_service_rates[] = $auspost_service_rate;
                                    }
                                }
                            }
                        }
                        $wc_main_settings = get_option('woocommerce_wf_australia_post_settings');
                        if ($this->rate_type == 'startrack') {
                            /*For StarTrack*/
                            if (!empty($startrack_service_rates)) {
                                foreach ($startrack_service_rates as $rate) {


                                    if (isset($rate['shipment_summary']) && !empty($rate['shipment_summary'])) {
                                        $shipment_summary = $rate['shipment_summary'];
                                        $rates_price = $shipment_summary['prices'];
                                        $rates_items = $shipment_summary['items'][0];
                                        $items_product_type = isset($rates_items->product_type) ? $rates_items->product_type : '';
                                        if (empty($items_product_type)) {
                                            foreach ($postage_products_startrack as $postage_product) {
                                                if ($postage_product['product_id'] === $rates_items->product_id) {
                                                    $items_product_type = $postage_product['type'];
                                                }
                                            }
                                        }

                                        if (isset($wc_main_settings['include_exclude_gst']) && $wc_main_settings['include_exclude_gst'] == "exclude" && isset($rates_price->total_cost_ex_gst)) {
                                            $rate_include_gst = $rates_price->total_cost_ex_gst;
                                        } elseif (isset($rates_price->total_cost_ex_gst)) {
                                            $rate_include_gst = $rates_price->total_cost;
                                        }

                                        if (isset($rates_price->total_cost)) {
                                            $this->prepare_rate($rates_items->product_id, $rates_items->product_id, $items_product_type, $rate_include_gst, $request_params);
                                        }
                                    }
                                }
                            }
                        }

                        if (!empty($this->api_pwd_auspost) && !empty($this->api_account_no_auspost)) {
                            /*For AusPost*/
                            if (!empty($auspost_service_rates)) {
                                foreach ($auspost_service_rates as $rate) {

                                    if (isset($rate['shipment_summary']) && !empty($rate['shipment_summary'])) {
                                        $shipment_summary = $rate['shipment_summary'];
                                        $rates_price = $shipment_summary['prices'];
                                        $rates_items = $shipment_summary['items'][0];
                                        $items_product_type = isset($rates_items->product_type) ? $rates_items->product_type : '';
                                        if (empty($items_product_type)) {
                                            foreach ($postage_products_startrack as $postage_product) {
                                                if ($postage_product['product_id'] === $rates_items->product_id) {
                                                    $items_product_type = $postage_product['type'];
                                                }
                                            }
                                        }
                                        if (isset($wc_main_settings['include_exclude_gst']) && $wc_main_settings['include_exclude_gst'] == "exclude") {
                                            $rate_include_gst = $rates_price->total_cost_ex_gst;
                                        } else {
                                            $rate_include_gst = $rates_price->total_cost;
                                        }
                                        if (isset($rates_price->total_cost)) {
                                            $this->prepare_rate($rates_items->product_id, $rates_items->product_id, $items_product_type, $rate_include_gst, $request_params);
                                        }
                                    }
                                }
                            }
                        }
                        $all_service_rates = array();
                        if (empty($auspost_service_rates)) {
                            $all_service_rates = $startrack_service_rates;
                        } else if (empty($startrack_service_rates)) {
                            $all_service_rates = $auspost_service_rates;
                        } else {
                            $all_service_rates = array_merge($auspost_service_rates, $startrack_service_rates);
                        }
                        $cheapest = '';
                        $postage_products = array_merge($postage_products_auspost, $postage_products_startrack);
                        if (!empty($this->found_rates)) {
                            if ($this->estimated_delivery_date_enabled === "yes") {
                                $shipping_services_product_ids = array_keys($this->found_rates);
                                $estimated_delivery_dates = $this->estimated_delivery_date($shipping_services_product_ids, $package['destination'], $vendor_from_address, $vendor_user_id);
                                $despatch_date_difference = $this->despatch_date_difference();
                            }
                            if (is_array($postage_products)) {
                                $adjustment = 0;
                                $adjustment_percentage = 0;
                                foreach ($postage_products as $postage_product) {
                                    foreach ($this->found_rates as $rate) {
                                        $rate['enabled'] = false;
                                        if ($postage_product['product_id'] === $rate['id']) {
                                            foreach ($settings_services as $key => $settings_service) {
                                                if ($settings_service['enabled'] == true) {

                                                    if ($postage_product['product_id'] === $key) {
                                                        if (!empty($settings_service['name'])) {
                                                            $rate['label'] = $settings_service['name'];
                                                        } else {
                                                            $rate['label'] = (isset($postage_product['type']) && $postage_product['type']) ? $postage_product['type'] : $rate['label'];
                                                        }
                                                        if (isset($estimated_delivery_dates[$key]) && $estimated_delivery_dates[$key] != NULL) {
                                                            $rate['meta_data']['aus_post_delivery_days'] = $estimated_delivery_dates[$key]->calendar_days_max + $despatch_date_difference;
                                                            $rate['meta_data']['aus_post_delivery_date'] = $estimated_delivery_dates[$key]->delivery_date_max;
                                                        }
                                                        if( $this->is_international && $this->is_elex_combined_export_tool_enable && $this->is_elex_combined_export_tool_enable == 'yes' && isset($estimated_duties_and_taxes_for_the_shipment_request_body) && $rate['cost'] > 0 ){
                                                            $estimated_duties_and_taxes_for_the_shipment_request_body->shipping_value = $rate['cost'];
                                                            $estimated_duties_and_taxes_for_the_shipment_value = $this->get_estimated_duties_and_taxes_for_the_shipment($estimated_duties_and_taxes_for_the_shipment_request_body);
                                                            if($estimated_duties_and_taxes_for_the_shipment_value > 0){
                                                                if(array_key_exists($key, $estimated_duties_and_taxes_for_the_shipment_value_array)){
                                                                    $estimated_duties_and_taxes_for_the_shipment_value_array[$key] += $estimated_duties_and_taxes_for_the_shipment_value;
                                                                }else{
                                                                    $estimated_duties_and_taxes_for_the_shipment_value_array[$key] = $estimated_duties_and_taxes_for_the_shipment_value;
                                                                }
                                                                $rate['meta_data']['elex_aus_post_estimated_duties_and_taxes_value'] = $estimated_duties_and_taxes_for_the_shipment_value_array[$key];
                                                                $rate['cost'] += $estimated_duties_and_taxes_for_the_shipment_value;
                                                            }

                                                        }
                                                        $add_extra_cover = false;
                                                        if (is_shop() || is_cart()) {

                                                            if (isset($settings_service['extra_cover']) && ($settings_service['extra_cover'] == true)) {
                                                                $add_extra_cover = true;
                                                            }
                                                        } else if (isset($this->settings['show_insurance_checkout_field']) && (($this->settings['show_insurance_checkout_field'] === 'yes' && $this->insurance_requested_at_checkout) || ($this->settings['show_insurance_checkout_field'] == '' && isset($settings_service['extra_cover']) && $settings_service['extra_cover'] == true))) {
                                                            $add_extra_cover = true;
                                                        }
                                                        if ($add_extra_cover) {
                                                            if ($this->is_international) {
                                                                if (($extra_cover_package != 0) && ($extra_cover_package <= 100)) {
                                                                    $rate['cost'] += $this->extra_cover_cost['international']; // extra cover fee for less than 100
                                                                } elseif (($extra_cover_package > 100) && ($extra_cover_package <= 5000)) {
                                                                    $rate['cost'] += $this->extra_cover_cost['international'] * $extra_cover_package / 100; // extra cover fee for greater than 100

                                                                }
                                                            } else {
                                                                if (($extra_cover_package != 0) && ($extra_cover_package <= 100)) {
                                                                    $rate['cost'] += $this->extra_cover_cost['domestic']; // extra cover fee for less than 100
                                                                } elseif (($extra_cover_package > 100) && ($extra_cover_package <= 5000)) {
                                                                    $rate['cost'] += $this->extra_cover_cost['domestic'] * $extra_cover_package / 100; // extra cover fee for greater than 100

                                                                }
                                                            }
                                                        }
                                                        if (is_object($all_service_rates)) {
                                                            $all_service_rates = (array) $all_service_rates;
                                                        }

                                                        if (!empty($settings_service['adjustment'])) {
                                                            $adjustment = $settings_service['adjustment'];
                                                        }

                                                        if (!empty($settings_service['adjustment_percent'])) {
                                                            $settings_service['adjustment_percent'] = $rate['cost'] * ($settings_service['adjustment_percent'] / 100);
                                                        }

                                                        $rate['cost'] += (float) (($settings_service['adjustment'] == 0) ? 0 : $settings_service['adjustment']);
                                                        $rate['cost'] += (float) (($settings_service['adjustment_percent'] == 0) ? 0 : $settings_service['adjustment_percent']);
                                                        $rate['enabled'] = true;
                                                        $this->found_rates[$postage_product['product_id']]['cost'] = $rate['cost'];
                                                        $this->previous_rate_cost_stored = get_option("rate_cost_" . $rate['id'] . '_auspost_elex');
                                                        if (empty($this->previous_rate_cost_stored)) {
                                                            update_option("rate_cost_" . $rate['id'] . '_auspost_elex', $rate['cost']);
                                                        } else {
                                                            $rate['cost'] = $rate['cost'] + $this->previous_rate_cost_stored;
                                                            update_option("rate_cost_" . $rate['id'] . '_auspost_elex', $rate['cost']);
                                                        }
                                                    }
                                                }
                                            }
                                            if ($this->offer_rates == 'cheapest') {
                                                if (!$cheapest || $cheapest['cost'] > $rate['cost']) {
                                                    $cheapest = $rate;
                                                }
                                                $cheapest['label'] = $this->title;
                                            }

                                            if ($this->offer_rates == 'all') {
                                                if (($this->settings['enabled'] == 'yes') && $rate['enabled'] == true) {
                                                    $rate['id'] = $this->id . ':' . $rate['id'];

                                                    $this->add_rate($rate);
                                                    if ($count_package_requests == $package_requests_size) {
                                                        $convention_rate_id = str_replace($this->id . ':', '', $rate['id']);
                                                        update_option("rate_cost_" . $convention_rate_id . '_auspost_elex', 0);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            if ($this->settings['enabled'] && $this->offer_rates != 'all') {
                                $cheapest['id'] = $this->id . ':' . $cheapest['id'];
                                $cheapest_convension_rate_id = str_replace($this->id . ':', '', $cheapest['id']);
                                $this->add_rate($cheapest);
                                update_option("rate_cost_" . $cheapest_convension_rate_id . '_auspost_elex', 0);
                            }
                        }
                    }
                } else if ($this->group_shipping_enabled == true && !$this->is_international) {
                    $package_requests_size = 1;
                    $count_package_requests++;
                    $from_weight_unit = '';
                    if ($this->weight_unit != 'kg') {
                        $from_weight_unit = $this->weight_unit;
                    }

                    $from_dimension_unit = '';
                    if ($this->dimension_unit != 'cm') {
                        $from_dimension_unit = $this->dimension_unit;
                    }

                    $extra_cover_package = 0;

                    /** 
                         Australia post one for normal request that will accept only kg and cm.
                            if it is a letter - thickness we need to use different API.that will accept only gm, mm.
                     */
                    $group_shipping_items_array = array();
                    $index = 0;
                    foreach ($package_requests as $key => $package_request) {
                        if (isset($package_request['age_check']) && $package_request['age_check'] == 'yes') {
                            $add_signature_enabled = true;
                        }
                        $extra_cover_package += $package_request['InsuredValue']['Amount'] ? $package_request['InsuredValue']['Amount'] : 0;
                        if (isset($package_request['Dimensions']['Thickness'])) {
                            $group_shipping_items_array[$index++] = array(
                                'weight' => round(wc_get_weight($package_request['Weight']['Value'], 'g', $from_weight_unit), 3),
                                'length' => round(wc_get_dimension($package_request['Dimensions']['Length'], 'mm', $from_dimension_unit), 1),
                                'width' => round(wc_get_dimension($package_request['Dimensions']['Width'], 'mm', $from_dimension_unit), 1),
                                'height' => round(wc_get_dimension($package_request['Dimensions']['Thickness'], 'mm', $from_dimension_unit), 1),
                            );
                        } else {
                            $group_shipping_items_array[$index++] = array(
                                'weight' => round(wc_get_weight($package_request['Weight']['Value'], 'kg', $from_weight_unit), 3),
                                'length' => round(wc_get_dimension($package_request['Dimensions']['Length'], 'cm', $from_dimension_unit), 1),
                                'width' => round(wc_get_dimension($package_request['Dimensions']['Width'], 'cm', $from_dimension_unit), 1),
                                'height' => round(wc_get_dimension($package_request['Dimensions']['Height'], 'cm', $from_dimension_unit), 1),
                            );
                        }
                    }

                    $startrack_service_rates = array();

                    if ($this->rate_type == 'startrack' && !$this->is_international) {
                        $rates = array();
                        /*For StarTrack*/
                        if ($vendor_from_address) {
                            $from = $vendor_from_address;
                        } else {
                            $from = array(
                                "suburb" => $this->settings['origin_suburb'],
                                "state" => $this->settings['origin_state'],
                                "postcode" => $this->settings['origin']
                            );
                        }

                        $to = array(
                            "suburb" => $package['destination']['city'],
                            "state" => $package['destination']['state'],
                            "postcode" => $package['destination']['postcode']
                        );

                        $service_rate_iteration = 0;
                        $startrack_service_rates_array = array();
                        foreach ($this->general_settings['startrack_services'] as $settings_service_key => $settings_service_value) {
                            if ($settings_service_key != 'FPP') {
                                $service_rate_iteration++;
                                if ($settings_service_value['enabled']) {
                                    /** 
                                     Australia post one for normal request that will accept only kg and cm.
                                    if it is a letter - thickness we need to use different API.that will accept only gm, mm.
                                     */
                                    $group_shipping_items_array = array();
                                    $index = 0;
                                    foreach ($package_requests as $key => $package_request) {
                                        if (isset($package_request['Dimensions']['Thickness'])) {
                                            $group_shipping_items_array[$index++] = array(
                                                'weight' => round(wc_get_weight($package_request['Weight']['Value'], 'g', $from_weight_unit), 3),
                                                'length' => round(wc_get_dimension($package_request['Dimensions']['Length'], 'mm', $from_dimension_unit), 1),
                                                'width' => round(wc_get_dimension($package_request['Dimensions']['Width'], 'mm', $from_dimension_unit), 1),
                                                'height' => round(wc_get_dimension($package_request['Dimensions']['Thickness'], 'mm', $from_dimension_unit), 1),
                                                'product_id' => $settings_service_key,
                                                'packaging_type' => (isset($package_request['pack_type']) && !empty($package_request['pack_type'])) ? $package_request['pack_type'] : 'ITM',

                                            );
                                        } else {
                                            $group_shipping_items_array[$index++] = array(
                                                'weight' => round(wc_get_weight($package_request['Weight']['Value'], 'kg', $from_weight_unit), 3),
                                                'length' => round(wc_get_dimension($package_request['Dimensions']['Length'], 'cm', $from_dimension_unit), 1),
                                                'width' => round(wc_get_dimension($package_request['Dimensions']['Width'], 'cm', $from_dimension_unit), 1),
                                                'height' => round(wc_get_dimension($package_request['Dimensions']['Height'], 'cm', $from_dimension_unit), 1),
                                                'product_id' => $settings_service_key,
                                                'packaging_type' => (isset($package_request['pack_type']) && !empty($package_request['pack_type'])) ? $package_request['pack_type'] : 'ITM',
                                            );
                                        }
                                    }
                                    $shipments = new stdClass();
                                    $shipments->from = $from;
                                    $shipments->to = $to;
                                    $shipments->items = $group_shipping_items_array;
                                    $request_params = new stdClass();
                                    $request_params->shipments = array($shipments);
                                    $endpoint = 'https://' . self::API_HOST . self::API_BASE_URL . 'prices/shipments/';
                                    if (isset($vendor_elex_australia_post_startrack_account_number) && isset($vendor_elex_australia_post_startrack_api_password)) {
                                        $headers = $this->buildHttpHeaders($request_params, $vendor_elex_australia_post_startrack_account_number, $vendor_elex_australia_post_startrack_api_password, $vendor_elex_australia_post_api_key);
                                    } else {
                                        $headers = $this->buildHttpHeaders($request_params, $this->api_account_no_starTrack, $this->api_pwd_starTrack, $this->api_key_starTrack);
                                    }
                                    if ($this->settings['contracted_api_mode'] == 'live') {
                                        $endpoint = str_replace('test/', '', $endpoint);
                                    }

                                    $this->debug(__('StarTrack (AusPost) debug is ON - to hide these messages, disable <i>debug mode</i> in settings.', 'wf-shipping-auspost'));
                                    $this->debug('StarTrack (AusPost) Contracted rate REQUEST: <pre>' . print_r($request_params, true) . '</pre>');

                                    $startrack_service_rates_for_package = $this->get_contracted_rates($endpoint, $request_params, $headers, "startrack");

                                    if (empty($startrack_service_rates_for_package)) {
                                        return;
                                    }
                                    if (isset($startrack_service_rates_for_package['error_message'])) {
                                        $this->debug('StarTrack (AusPost) Contracted rate RESPONSE: <pre style="color:red;">' . print_r($startrack_service_rates_for_package['error_message'], true) . '</pre>');
                                    } else {
                                        $startrack_service_rates_array[] = $startrack_service_rates_for_package;
                                    }
                                }
                            }
                        }

                        if ($count_package_requests == 1) {
                            $startrack_service_rates = $startrack_service_rates_array;
                            foreach ($startrack_service_rates_array as $service_rate) {
                                $shipment_items = $service_rate['shipment_summary']['items'];
                                $service_id = $shipment_items[0]->product_id;
                                $startrack_services_set[] = $service_id;
                            }
                        } else {
                            foreach ($startrack_service_rates_array as $service_rate) {
                                $shipment_items = $service_rate['shipment_summary']['items'];
                                $service_id = $shipment_items[0]->product_id;
                                if (in_array($service_id, $startrack_services_set)) {
                                    $startrack_service_rates[] = $service_rate;
                                }
                            }
                        }
                        if(!empty($startrack_service_rates)){
                            $this->debug('StarTrack (AusPost) Contracted rate RESPONSE: <pre>' . print_r($startrack_service_rates, true) . '</pre>');
                        }
                        
                    }

                    if (!empty($this->api_pwd_auspost) && !empty($this->api_account_no_auspost)) {
                        /*For AusPost*/
                        $this->debug(__('AusPost debug is ON - to hide these messages, disable <i>debug mode</i> in settings.', 'wf-shipping-auspost'));
                        $rates = array();

                        if ($vendor_from_address) {
                            $from = $vendor_from_address;
                        } else {
                            $from = array(
                                "suburb" => $this->settings['origin_suburb'],
                                "state" => $this->settings['origin_state'],
                                "postcode" => $this->settings['origin']
                            );
                        }

                        $to = array(
                            "suburb" => $package['destination']['city'],
                            "state" => $package['destination']['state'],
                            "postcode" => $package['destination']['postcode'],
                            "country" => $package['destination']['country']
                        );
                        $service_rate_iteration = 0;
                        $auspost_service_rates_response = array();
                        foreach ($this->general_settings['services'] as $settings_service_key => $settings_service_value) {
                            $service_rate_iteration++;
                            $flag = FALSE;
                            $domestic_product_ids = ['7E55', '3K55', '7I55', '7J55', '7C55'];
                            $internation_product_ids = ['RPI8', 'PTI8', 'ECM8', 'AIR8', 'PTI7', 'ECD8'];
                            if ($to['country'] == 'AU' && in_array($settings_service_key, $domestic_product_ids)) {
                                $flag = TRUE;
                            }
                            if ($to['country'] != 'AU' && in_array($settings_service_key, $internation_product_ids)) {
                                $flag = TRUE;
                            }
                            if (!in_array($settings_service_key, $internation_product_ids) && !in_array($settings_service_key, $domestic_product_ids)) {
                                $flag = TRUE;
                            }
                            if ($settings_service_value['enabled'] && $flag) {
                                /** 
                                 Australia post one for normal request that will accept only kg and cm.
                                 if it is a letter - thickness we need to use different API.that will accept only gm, mm.
                                 */
                                $group_shipping_items_array = array();
                                $index = 0;
                                foreach ($package_requests as $key => $package_request) {
                                    if (isset($package_request['Dimensions']['Thickness'])) {
                                        $group_shipping_items_array[$index++] = array(
                                            'weight' => round(wc_get_weight($package_request['Weight']['Value'], 'g', $from_weight_unit), 3),
                                            'length' => round(wc_get_dimension($package_request['Dimensions']['Length'], 'mm', $from_dimension_unit), 1),
                                            'width' => round(wc_get_dimension($package_request['Dimensions']['Width'], 'mm', $from_dimension_unit), 1),
                                            'height' => round(wc_get_dimension($package_request['Dimensions']['Thickness'], 'mm', $from_dimension_unit), 1),
                                            'product_id' => $settings_service_key,
                                            'packaging_type' => (isset($package_request['pack_type']) && !empty($package_request['pack_type'])) ? $package_request['pack_type'] : 'ITM',

                                        );
                                    } else {
                                        $group_shipping_items_array[$index++] = array(
                                            'weight' => round(wc_get_weight($package_request['Weight']['Value'], 'kg', $from_weight_unit), 3),
                                            'length' => round(wc_get_dimension($package_request['Dimensions']['Length'], 'cm', $from_dimension_unit), 1),
                                            'width' => round(wc_get_dimension($package_request['Dimensions']['Width'], 'cm', $from_dimension_unit), 1),
                                            'height' => round(wc_get_dimension($package_request['Dimensions']['Height'], 'cm', $from_dimension_unit), 1),
                                            'product_id' => $settings_service_key,
                                            'packaging_type' => (isset($package_request['pack_type']) && !empty($package_request['pack_type'])) ? $package_request['pack_type'] : 'ITM',
                                        );
                                    }
                                }
                                $shipments = new stdClass();
                                $shipments->from = $from;
                                $shipments->to = $to;
                                $shipments->items = $group_shipping_items_array;
                                $request_params = new stdClass();
                                $request_params->shipments = array($shipments);
                                $endpoint = 'https://' . self::API_HOST . self::API_BASE_URL . 'prices/shipments/';
                                if ($this->vedor_api_key_enable && isset($vendor_elex_australia_post_account_number) && isset($vendor_elex_australia_post_api_password)) {
                                    $headers = $this->buildHttpHeaders($request_params, $vendor_elex_australia_post_account_number, $vendor_elex_australia_post_api_password, $vendor_elex_australia_post_api_key);
                                } else {
                                    $headers = $this->buildHttpHeaders($request_params, $this->api_account_no_auspost, $this->api_pwd_auspost);
                                }
                                if ($this->settings['contracted_api_mode'] == 'live') {
                                    $endpoint = str_replace('test/', '', $endpoint);
                                }
                                $this->debug('AusPost Contracted rate REQUEST: <pre>' . print_r($request_params, true) . '</pre>');
                                $auspost_service_rates_for_package = $this->get_contracted_rates($endpoint, $request_params, $headers, "auspost");
                                if (empty($auspost_service_rates_for_package)) {
                                    return;
                                }
                                if (isset($auspost_service_rates_for_package['error_message'])) {
                                    $this->debug('AusPost Contracted rate RESPONSE: <pre style="color:red;">' . print_r($auspost_service_rates_for_package['error_message'], true) . '</pre>');
                                } else {
                                    $auspost_service_rates_response[] = $auspost_service_rates_for_package;
                                    $this->debug('AusPost Contracted rate RESPONSE: <pre>' . print_r($auspost_service_rates_for_package, true) . '</pre>');
                                }
                            }
                        }
                        $auspost_service_rates = array();
                        if ($count_package_requests == 1) {
                            $auspost_service_rates = $auspost_service_rates_response;
                            foreach ($auspost_service_rates_response as $auspost_service_rate) {
                                $shipment_items = $auspost_service_rate['shipment_summary']['items'];
                                $service_id = $shipment_items[0]->product_id;
                                $auspost_services_set[] = $service_id;
                            }
                        } elseif (isset($auspost_service_rates_response) && !empty($auspost_service_rates_response)) {
                            foreach ($auspost_service_rates_response as $auspost_service_rate) {
                                $shipment_items = $auspost_service_rate['shipment_summary']['items'];
                                $service_id = $shipment_items[0]->product_id;
                                if (in_array($service_id, $auspost_services_set)) {
                                    $auspost_service_rates[] = $auspost_service_rate;
                                }
                            }
                        } else {
                            foreach ($auspost_service_rates_response as $auspost_service_rate) {
                                $shipment_items = $auspost_service_rate['shipment_summary']['items'];
                                $service_id = $shipment_items[0]->product_id;
                                if (in_array($service_id, $auspost_services_set)) {
                                    $auspost_service_rates[] = $auspost_service_rate;
                                }
                            }
                        }
                    }
                    $wc_main_settings = get_option('woocommerce_wf_australia_post_settings');
                    if ($this->rate_type == 'startrack') {
                        /*For StarTrack*/
                        if (!empty($startrack_service_rates)) {
                            foreach ($startrack_service_rates as $rate) {


                                if (isset($rate['shipment_summary']) && !empty($rate['shipment_summary'])) {
                                    $shipment_summary = $rate['shipment_summary'];
                                    $rates_price = $shipment_summary['prices'];
                                    $rates_items = $shipment_summary['items'][0];
                                    $items_product_type = isset($rates_items->product_type) ? $rates_items->product_type : '';
                                    if (empty($items_product_type)) {
                                        foreach ($postage_products_startrack as $postage_product) {
                                            if ($postage_product['product_id'] === $rates_items->product_id) {
                                                $items_product_type = $postage_product['type'];
                                            }
                                        }
                                    }

                                    if (isset($wc_main_settings['include_exclude_gst']) && $wc_main_settings['include_exclude_gst'] == "exclude" && isset($rates_price->total_cost_ex_gst)) {
                                        $rate_include_gst = $rates_price->total_cost_ex_gst;
                                    } elseif (isset($rates_price->total_cost_ex_gst)) {
                                        $rate_include_gst = $rates_price->total_cost;
                                    }

                                    if (isset($rates_price->total_cost)) {
                                        $this->prepare_rate($rates_items->product_id, $rates_items->product_id, $items_product_type, $rate_include_gst, $request_params);
                                    }
                                }
                            }
                        }
                    }

                    if (!empty($this->api_pwd_auspost) && !empty($this->api_account_no_auspost)) {
                        /*For AusPost*/
                        if (!empty($auspost_service_rates)) {
                            foreach ($auspost_service_rates as $rate) {

                                if (isset($rate['shipment_summary']) && !empty($rate['shipment_summary'])) {
                                    $shipment_summary = $rate['shipment_summary'];
                                    $rates_price = $shipment_summary['prices'];
                                    $rates_items = $shipment_summary['items'][0];
                                    $items_product_type = isset($rates_items->product_type) ? $rates_items->product_type : '';
                                    if (empty($items_product_type)) {
                                        foreach ($postage_products_startrack as $postage_product) {
                                            if ($postage_product['product_id'] === $rates_items->product_id) {
                                                $items_product_type = $postage_product['type'];
                                            }
                                        }
                                    }
                                    if (isset($wc_main_settings['include_exclude_gst']) && $wc_main_settings['include_exclude_gst'] == "exclude") {
                                        $rate_include_gst = $rates_price->total_cost_ex_gst;
                                    } else {
                                        $rate_include_gst = $rates_price->total_cost;
                                    }
                                    if (isset($rates_price->total_cost)) {
                                        $this->prepare_rate($rates_items->product_id, $rates_items->product_id, $items_product_type, $rate_include_gst, $request_params);
                                    }
                                }
                            }
                        }
                    }
                    $all_service_rates = array();
                    if (empty($auspost_service_rates)) {
                        $all_service_rates = $startrack_service_rates;
                    } else if (empty($startrack_service_rates)) {
                        $all_service_rates = $auspost_service_rates;
                    } else {
                        $all_service_rates = array_merge($auspost_service_rates, $startrack_service_rates);
                    }
                    $cheapest = '';
                    $postage_products = array_merge($postage_products_auspost, $postage_products_startrack);
                    if (!empty($this->found_rates)) {
                        if ($this->estimated_delivery_date_enabled === "yes") {
                            $shipping_services_product_ids = array_keys($this->found_rates);
                            $estimated_delivery_dates = $this->estimated_delivery_date($shipping_services_product_ids, $package['destination'], $vendor_from_address, $vendor_user_id);
                            $despatch_date_difference = $this->despatch_date_difference();
                        }
                        if (is_array($postage_products)) {
                            $adjustment = 0;
                            $adjustment_percentage = 0;
                            foreach ($postage_products as $postage_product) {
                                foreach ($this->found_rates as $rate) {
                                    $rate['enabled'] = false;
                                    if ($postage_product['product_id'] === $rate['id']) {
                                        foreach ($settings_services as $key => $settings_service) {
                                            if ($settings_service['enabled'] == true) {

                                                if ($postage_product['product_id'] === $key) {
                                                    if (!empty($settings_service['name'])) {
                                                        $rate['label'] = $settings_service['name'];
                                                    } else {
                                                        $rate['label'] = (isset($postage_product['type']) && $postage_product['type']) ? $postage_product['type'] : $rate['label'];
                                                    }
                                                    if (isset($estimated_delivery_dates[$key]) && $estimated_delivery_dates[$key] != NULL) {
                                                        $rate['meta_data']['aus_post_delivery_days'] = $estimated_delivery_dates[$key]->calendar_days_max + $despatch_date_difference;
                                                        $rate['meta_data']['aus_post_delivery_date'] = $estimated_delivery_dates[$key]->delivery_date_max;
                                                    }
                                                    $add_extra_cover = false;
                                                    if (is_shop() || is_cart()) {

                                                        if (isset($settings_service['extra_cover']) && ($settings_service['extra_cover'] == true)) {
                                                            $add_extra_cover = true;
                                                        }
                                                    } else if (isset($this->settings['show_insurance_checkout_field']) && (($this->settings['show_insurance_checkout_field'] === 'yes' && $this->insurance_requested_at_checkout) || ($this->settings['show_insurance_checkout_field'] == '' && isset($settings_service['extra_cover']) && $settings_service['extra_cover'] == true))) {
                                                        $add_extra_cover = true;
                                                    }
                                                    if ($add_extra_cover) {
                                                        if ($this->is_international) {
                                                            if (($extra_cover_package != 0) && ($extra_cover_package <= 100)) {
                                                                $rate['cost'] += $this->extra_cover_cost['international']; // extra cover fee for less than 100
                                                            } elseif (($extra_cover_package > 100) && ($extra_cover_package <= 5000)) {
                                                                $rate['cost'] += $this->extra_cover_cost['international'] * $extra_cover_package / 100; // extra cover fee for greater than 100

                                                            }
                                                        } else {
                                                            if (($extra_cover_package != 0) && ($extra_cover_package <= 100)) {
                                                                $rate['cost'] += $this->extra_cover_cost['domestic']; // extra cover fee for less than 100
                                                            } elseif (($extra_cover_package > 100) && ($extra_cover_package <= 5000)) {
                                                                $rate['cost'] += $this->extra_cover_cost['domestic'] * $extra_cover_package / 100; // extra cover fee for greater than 100

                                                            }
                                                        }
                                                    }                                                   

                                                    if (!empty($settings_service['adjustment'])) {
                                                        $adjustment = $settings_service['adjustment'];
                                                    }

                                                    if (!empty($settings_service['adjustment_percent'])) {
                                                        $settings_service['adjustment_percent'] = $rate['cost'] * ($settings_service['adjustment_percent'] / 100);
                                                    }
                                                    
                                                    $rate['cost'] += (float) (($settings_service['adjustment'] == 0) ? 0 : $settings_service['adjustment']);
                                                    $rate['cost'] += (float) (($settings_service['adjustment_percent'] == 0) ? 0 : $settings_service['adjustment_percent']);
                                                    $rate['enabled'] = true;
                                                    $this->found_rates[$postage_product['product_id']]['cost'] = $rate['cost'];
                                                    $this->previous_rate_cost_stored = get_option("rate_cost_" . $rate['id'] . '_auspost_elex');
                                                    if (empty($this->previous_rate_cost_stored)) {
                                                        update_option("rate_cost_" . $rate['id'] . '_auspost_elex', $rate['cost']);
                                                    } else {
                                                        $rate['cost'] = $rate['cost'] + $this->previous_rate_cost_stored;
                                                        update_option("rate_cost_" . $rate['id'] . '_auspost_elex', $rate['cost']);
                                                    }
                                                }
                                            }
                                        }
                                        if ($this->offer_rates == 'cheapest') {
                                            if (!$cheapest || $cheapest['cost'] > $rate['cost']) {
                                                $cheapest = $rate;
                                            }
                                            $cheapest['label'] = $this->title;
                                        }

                                        if ($this->offer_rates == 'all') {
                                            if (($this->settings['enabled'] == 'yes') && $rate['enabled'] == true) {
                                                $rate['id'] = $this->id . ':' . $rate['id'];

                                                $this->add_rate($rate);
                                                if ($count_package_requests == $package_requests_size) {
                                                    $convention_rate_id = str_replace($this->id . ':', '', $rate['id']);
                                                    update_option("rate_cost_" . $convention_rate_id . '_auspost_elex', 0);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if ($this->settings['enabled'] && $this->offer_rates != 'all') {
                            $cheapest['id'] = $this->id . ':' . $cheapest['id'];
                            $cheapest_convension_rate_id = str_replace($this->id . ':', '', $cheapest['id']);
                            $this->add_rate($cheapest);
                            update_option("rate_cost_" . $cheapest_convension_rate_id . '_auspost_elex', 0);
                        }
                    }
                }
            }
            return;
        } else {

            $custom_services = $this->general_settings['services'];
            $custom_service_keys = array();

            foreach ($custom_services as $custom_service_key => $custom_service_value) {
                $custom_service_keys[] = $custom_service_key;
            }

            $sub_services = isset($this->general_settings['sub_services']) ? $this->general_settings['sub_services'] : array();

            $is_letter_services_enabled = false;
            $is_satchel_services_enabled = false;

            if (is_array($sub_services) && !empty($sub_services)) {
                foreach ($sub_services as $sub_service_key => $sub_service) {
                    if (strpos($sub_service_key, '_SATCHEL_') && $sub_service['enabled']) {
                        $is_satchel_services_enabled = true;
                        break;
                    }
                }

                foreach ($sub_services as $sub_service_key => $sub_service) {
                    if (strpos($sub_service_key, '_LETTER_') && $sub_service['enabled']) {
                        $is_letter_services_enabled = true;
                        break;
                    }
                }
            }

            $this->debug(__('<b>Australia Post debug mode is on - to hide these messages, turn debug mode off in the <a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=wf_australia_post&subtab=general') . '">' . __('settings', 'wf-shipping-auspost') . '</a>.</b><br>', 'wf-shipping-auspost'));

            if ($package_requests) {
                foreach ($package_requests as $key => $package_request) {
                    $from_weight_unit = '';
                    if ($this->weight_unit != 'kg') {
                        $from_weight_unit = $this->weight_unit;
                    }

                    $from_dimension_unit = '';
                    if ($this->dimension_unit != 'cm') {
                        $from_dimension_unit = $this->dimension_unit;
                    }

                    $package_weight = wc_get_weight($package_request['Weight']['Value'], 'kg', $from_weight_unit);

                    if ($is_letter_services_enabled && $package_weight > 0.5 && $package_weight <= 5) {
                        if ($is_satchel_services_enabled) {
                            $this->debug(__('Weight of package/product is more than 500g, switching to Satchel rates', 'wf-shipping-auspost'), 'error');
                        } else {
                            $this->debug(__('Weight of package/product is more than 500g, switching to Regular rates', 'wf-shipping-auspost'), 'error');
                        }
                    } else if ($is_satchel_services_enabled && $package_weight > 5) {
                        $this->debug(__('Weight of package/product is more than 5KG, switching to Regular rates', 'wf-shipping-auspost'), 'error');
                    }

                    $extra_cover_package = $package_request['InsuredValue']['Amount'] ? $package_request['InsuredValue']['Amount'] : 0;

                    $package_req_letter = array();

                    /* If letter rates services are enabled */
                    if (isset($package_request['Dimensions']['Thickness']) || $is_letter_services_enabled) {
                        $package_req_letter = array(
                            'weight' => round(wc_get_weight($package_request['Weight']['Value'], 'g', $from_weight_unit), 3),
                            'length' => round(wc_get_dimension($package_request['Dimensions']['Length'], 'mm', $from_dimension_unit), 1),
                            'width' => round(wc_get_dimension($package_request['Dimensions']['Width'], 'mm', $from_dimension_unit), 1),
                            'thickness' => isset($package_request['Dimensions']['Thickness']) ? round(wc_get_dimension($package_request['Dimensions']['Thickness'], 'mm', $from_dimension_unit), 1) : round(wc_get_dimension($package_request['Dimensions']['Height'], 'mm', $from_dimension_unit), 1)
                        );

                        if ($package_req_letter['weight'] > 500) {
                            $this->debug('Weight of the letter exceeds maximum weight of 500g', 'error');
                        }

                        if ($package_req_letter['length'] > 260) {
                            $this->debug('Length of the letter exceeds maximum length of 260mm', 'error');
                        }

                        if ($package_req_letter['width'] > 360) {
                            $this->debug('Width of the letter exceeds maximum width of 360mm', 'error');
                        }

                        if ($package_req_letter['thickness'] > 20) {
                            $this->debug('Thickness of the letter exceeds maximum thickness of 20mm', 'error');
                        }
                    }

                    $package_req = array(
                        'weight' => round(wc_get_weight($package_request['Weight']['Value'], 'kg', $from_weight_unit), 3),
                        'length' => round(wc_get_dimension($package_request['Dimensions']['Length'], 'cm', $from_dimension_unit), 1),
                        'width' => round(wc_get_dimension($package_request['Dimensions']['Width'], 'cm', $from_dimension_unit), 1),
                        'height' => round(wc_get_dimension($package_request['Dimensions']['Height'], 'cm', $from_dimension_unit), 1)
                    );

                    $request_letter = '';

                    if ($is_letter_services_enabled && !empty($package_req_letter)) {

                        $request_letter = http_build_query(array_merge($package_req_letter, $this->get_request($package)), '', '&');
                    }

                    $request = http_build_query(array_merge($package_req, $this->get_request($package)), '', '&');
                    $rates_responses = array();
                    $response = '';
                    $rates_response_letter = '';

                    if ($is_letter_services_enabled && $request_letter != '') {
                        $rates_response_letter = $this->get_response($letter_services_endpoint, $request_letter, $headers);
                    }

                    if (!empty($rates_response_letter)) {
                        array_push($rates_responses, $rates_response_letter);
                    }

                    $response_regular = $this->get_response($services_endpoint, $request, $headers);
                    if (empty($response_regular)) {
                        return;
                    }
                    array_push($rates_responses, $response_regular);

                    if (!empty($rates_responses)) {
                        foreach ($rates_responses as $response) {
                            if (isset($response->services->service) && is_array($response->services->service)) {
                                // Loop our known services
                                foreach ($this->services as $service_code => $values) {
                                    $validation_parameters = array(
                                        "rates_response" => $response,
                                        "service_code"  => $service_code,
                                        "service_settings" => $values,
                                        "package" => $package_req,
                                        "letter_rate_status" => $is_letter_services_enabled,
                                        "satchel_rate_status" => $is_satchel_services_enabled,
                                        "auspost_services" => $custom_services,
                                        "auspost_sub_services" => $sub_services,
                                        "package_extra_cover" => $extra_cover_package
                                    );
                                    $this->validate_for_non_contracted_services($validation_parameters);
                                }
                            }
                        }
                    }
                }

                // Now do the calculation API
                $additional_package_requests = $this->get_additional_package_requests($package, $package_requests);
                if ($additional_package_requests) {
                    // Clear old
                    foreach ($additional_package_requests as $key => $package_request) {
                        $rate_code = $package_request['service_code'];

                        unset($this->found_rates[$this->id . ':' . $rate_code]);
                    }

                    // Request new
                    foreach ($additional_package_requests as $key => $additional_package_request) {

                        $request = str_replace('suboption_code_2', 'suboption_code', http_build_query(array_merge($additional_package_request, $this->get_request($package)), '', '&'));

                        if (isset($additional_package_request['thickness'])) {
                            $response = $this->get_response($letter_calculation_endpoint, $request, $headers);
                        } else {
                            $response = $this->get_response($calculation_endpoint, $request, $headers);
                        }

                        if (isset($response->postage_result) && is_object($response->postage_result)) {

                            $service = $response->postage_result;

                            $rate_code = $additional_package_request['service_code'];

                            if ($additional_package_request['option_code'] && (strstr($rate_code, 'AUS_LETTER_REGULAR_SMALL') || strstr($rate_code, 'AUS_LETTER_REGULAR_LARGE')))
                                $rate_code = 'AUS_PARCEL_REGULAR';

                            if ($additional_package_request['option_code'] && (strstr($rate_code, 'AUS_LETTER_EXPRESS_SMALL') || strstr($rate_code, 'AUS_LETTER_EXPRESS_LARGE')))
                                $rate_code = 'AUS_PARCEL_EXPRESS';

                            $rate_id = $this->id . ':' . $rate_code;

                            if (isset($this->services[$rate_code]['name']))
                                $rate_name = (string) $this->services[$rate_code]['name'];

                            $rate_cost = (float) $service->total_cost;

                            //Delivery confirmation and Extra cover option together not support by API. The solution is to add a static value $2.95 for domestic and $4.99 for international with rates.
                            if (!$this->is_international) {
                                if ($delivery_confirmation) {
                                    $rate_cost += $this->sod_cost['domestic'];
                                }
                            } else {
                                if ($delivery_confirmation) {
                                    $rate_cost += $this->sod_cost['international'];
                                }
                            }

                            $this->prepare_rate($rate_code, $rate_id, $rate_name, $rate_cost, $additional_package_request);
                        }
                    }
                }
            }
        }
        // Set transient
        set_transient('wf_australia_post_quotes', $this->rate_cache, YEAR_IN_SECONDS);

        //Ensure rates were found for all packages
        if ($this->found_rates) {
            foreach ($this->found_rates as $key => $value) {
                if (isset($value['packages'])) {
                    if ($value['packages'] < sizeof($package_requests))
                        unset($this->found_rates[$key]);
                }
            }
        }

        // Add rates
        if ($this->found_rates) {
            $all_services = array();
            if (isset($this->settings['services']) && !empty($this->settings['services'])) {
                $all_services = $this->settings['services'];
            }

            if (isset($this->settings['sub_services']) && !empty($this->settings['sub_services'])) {
                $all_services = array_merge($this->settings['services'], $this->settings['sub_services']);
            }

            if ($this->offer_rates == 'all') {
                uasort($this->found_rates, array($this, 'sort_rates'));
                foreach ($this->found_rates as $key => $rate) {
                    $service_name = str_replace("wf_australia_post:", "", $key);
                    $actual_code = $rate['actual_code'];
                    if (!empty($all_services)) {
                        foreach ($all_services as $service_key => $service) {
                            if (strpos($service_name, 'REGULAR_SATCHEL')) {
                                $rate['label'] = $service['name'];
                            }
                            if (strpos($service_name, 'EXPRESS_SATCHEL')) {
                                $rate['label'] = $service['name'];
                            } else if ($service_key === $service_name) {
                                if (isset($service['name']) && !empty($service['name'])) {
                                    $rate['label'] = $service['name'];
                                }
                            }

                            if (isset($custom_services[$service_name])) {
                                if ($this->settings['enabled'] && $custom_services[$service_name]['enabled']) {
                                    $this->add_rate($rate);
                                    $this->found_rates[$key]['cost'] = $rate['cost'];
                                    update_option("rate_cost_" . $rate['id'] . "ncr", 0);
                                }
                            } else if (isset($sub_services[$actual_code])) {
                                if ($this->settings['enabled'] && $sub_services[$actual_code]['enabled']) {
                                    $this->add_rate($rate);
                                    $this->found_rates[$key]['cost'] = $rate['cost'];
                                    update_option("rate_cost_" . $rate['id'] . "ncr", 0);
                                }
                            }
                        }
                    }
                }
            } else {

                $cheapest_rate = '';
                foreach ($this->found_rates as $key => $rate) {
                    $service_name = str_replace("wf_australia_post:", "", $key);
                    $actual_code = $rate['actual_code'];
                    if (isset($custom_services[$service_name])  && $custom_services[$service_name]['enabled'] && (!$cheapest_rate || $cheapest_rate['cost'] > $rate['cost'])){
                                $cheapest_rate = $rate;
                        
                    } else if (isset($sub_services[$actual_code]) && $sub_services[$actual_code]['enabled'] && (!$cheapest_rate || $cheapest_rate['cost'] > $rate['cost'])) {
                                $cheapest_rate = $rate;
                    }
                    update_option("rate_cost_" . $rate['id'] . "ncr", 0);
                    
                }

                $cheapest_rate['label'] = $this->title;

                if ($this->settings['enabled']) {
                    $this->add_rate($cheapest_rate);
                    //                    $this->found_rates[$key]['cost'] = $cheapest_rate['cost'];
                    update_option("rate_cost_" . $cheapest_rate['id'] . "ncr", 0);
                }
                
            }
        }
    }

    /**
     * function to validate packages for non-contracted AusPost services
     * @access private
     * @param array validation_parameters
     */
    private function validate_for_non_contracted_services($validation_parameters)
    {

        // Main service code
        foreach ($validation_parameters['rates_response']->services->service as $quote) {
            $rate_code = (string) $validation_parameters['service_code'];
            $rate_id = $this->id . ':' . $rate_code;
            $rate_name = (string) $validation_parameters['service_settings']['name'];
            $rate_cost = 0;

            $alternate_services = isset($validation_parameters['service_settings']['alternate_services']) ? $validation_parameters['service_settings']['alternate_services'] : array();
            $alternate_services_names = array();

            if (!empty($alternate_services)) {
                foreach ($alternate_services as $alternate_service_key => $alternate_service_value) {
                    $alternate_services_names[] = $alternate_service_key;
                }
            }

            $delivery_confirmation = false;

            if (in_array($quote->code, $alternate_services_names)) {

                if ($validation_parameters['letter_rate_status'] && $this->is_letter($quote->code)) {
                    // Validating letter rates
                    update_option("wf_auspost_letter_rates_obtained", true);
                    switch ($quote->code) {
                        case 'AUS_LETTER_EXPRESS_SMALL':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_LETTER_EXPRESS_SMALL']) && $validation_parameters['auspost_sub_services']['AUS_LETTER_EXPRESS_SMALL']['enabled']) {
                                $rate_code = 'AUS_LETTER_EXPRESS_SMALL';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_LETTER_EXPRESS_SMALL']['name'];
                                if ($validation_parameters['package']['length'] > 22 || $validation_parameters['package']['width'] > 11 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 22, 11)) {
                                    return;
                                }
                                break;
                            }
                        case 'AUS_LETTER_EXPRESS_MEDIUM':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_LETTER_EXPRESS_MEDIUM']) && $validation_parameters['auspost_sub_services']['AUS_LETTER_EXPRESS_MEDIUM']['enabled']) {
                                $rate_code = 'AUS_LETTER_EXPRESS_MEDIUM';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_LETTER_EXPRESS_MEDIUM']['name'];
                                if ($validation_parameters['package']['length'] >22.9 || $validation_parameters['package']['width'] >16.2 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 22.9, 16.2 )) {
                                    return;
                                }
                                break;
                            }
                        case 'AUS_LETTER_EXPRESS_LARGE':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_LETTER_EXPRESS_LARGE']) && $validation_parameters['auspost_sub_services']['AUS_LETTER_EXPRESS_LARGE']['enabled']) {
                                $rate_code = 'AUS_LETTER_EXPRESS_LARGE';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_LETTER_EXPRESS_LARGE']['name'];
                                if ($validation_parameters['package']['length'] > 35.3 || $validation_parameters['package']['width'] > 25 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 35.3, 25 )) {
                                    return;
                                }
                                break;
                            }
                        case 'AUS_LETTER_REGULAR_SMALL':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_SMALL']) && $validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_SMALL']['enabled']) {
                                $rate_code = 'AUS_LETTER_REGULAR_SMALL';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_SMALL']['name'];
                                if ($validation_parameters['package']['length'] >24 || $validation_parameters['package']['width'] > 13 || $validation_parameters['package']['height'] > 0.5 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 24,13)) {
                                    return;
                                }
                                break;
                            }
                        case 'AUS_LETTER_REGULAR_MEDIUM':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_MEDIUM']) && $validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_MEDIUM']['enabled']) {
                                $rate_code = 'AUS_LETTER_REGULAR_MEDIUM';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_MEDIUM']['name'];
                                if ($validation_parameters['package']['length'] > 24 || $validation_parameters['package']['width'] > 13 || $validation_parameters['package']['height'] > 0.5 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 24, 13)) {
                                    return;
                                }
                                break;
                            }
                        case 'AUS_LETTER_REGULAR_LARGE':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_LARGE']) && $validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_LARGE']['enabled']) {
                                $rate_code = 'AUS_LETTER_REGULAR_LARGE';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_LARGE']['name'];
                                if ($validation_parameters['package']['length'] > 24 || $validation_parameters['package']['width'] > 13 || $validation_parameters['package']['height'] > 0.5 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 24, 13)) {
                                    return;
                                }
                                break;
                            }
                        case 'AUS_LETTER_REGULAR_LARGE_125':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_LARGE_125']) && $validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_LARGE_125']['enabled']) {
                                $rate_code = 'AUS_LETTER_REGULAR_LARGE_125';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_LARGE_125']['name'];
                                if ($validation_parameters['package']['length'] > 36 || $validation_parameters['package']['width'] > 26 || $validation_parameters['package']['height'] > 2 || $validation_parameters['package']['weight'] > 0.125 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 36, 26)) {
                                    return;
                                }
                                break;
                            }
                        case 'AUS_LETTER_REGULAR_LARGE_250':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_LARGE_250']) && $validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_LARGE_250']['enabled']) {
                                $rate_code = 'AUS_LETTER_REGULAR_LARGE_250';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_LARGE_250']['name'];
                                if ($validation_parameters['package']['length'] > 36 || $validation_parameters['package']['width'] > 26 || $validation_parameters['package']['height'] > 2 || $validation_parameters['package']['weight'] > 0.25 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 36, 26)) {
                                    return;
                                }
                                break;
                            }
                        case 'AUS_LETTER_REGULAR_LARGE_500':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_LARGE_500']) && $validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_LARGE_500']['enabled']) {
                                $rate_code = 'AUS_LETTER_REGULAR_LARGE_500';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_LETTER_REGULAR_LARGE_500']['name'];
                                if ($validation_parameters['package']['length'] > 36 || $validation_parameters['package']['width'] > 26 || $validation_parameters['package']['height'] > 2 || $validation_parameters['package']['weight'] > 0.5 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 36, 26)) {
                                    return;
                                }
                                break;
                            }
                        case 'INT_LETTER_AIR_SMALL_ENVELOPE':
                            if (isset($validation_parameters['auspost_sub_services']['INT_LETTER_AIR_SMALL_ENVELOPE']) && $validation_parameters['auspost_sub_services']['INT_LETTER_AIR_SMALL_ENVELOPE']['enabled']) {
                                $rate_code = 'INT_LETTER_AIR_SMALL_ENVELOPE';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['INT_LETTER_AIR_SMALL_ENVELOPE']['name'];
                                if ($validation_parameters['package']['length'] > 22 || $validation_parameters['package']['width'] > 11 || $validation_parameters['package']['weight'] > 0.05 || $validation_parameters['package']['height'] > 0.5 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 22, 11)) {
                                    return;
                                }
                                break;
                            }
                        case 'INT_LETTER_AIR_LARGE_ENVELOPE':
                            if (isset($validation_parameters['auspost_sub_services']['INT_LETTER_AIR_LARGE_ENVELOPE']) && $validation_parameters['auspost_sub_services']['INT_LETTER_AIR_LARGE_ENVELOPE']['enabled']) {
                                $rate_code = 'INT_LETTER_AIR_LARGE_ENVELOPE';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['INT_LETTER_AIR_LARGE_ENVELOPE']['name'];
                                if ($validation_parameters['package']['length'] >32.4  || $validation_parameters['package']['width'] > 22.9 || $validation_parameters['package']['weight'] > 0.25 || $validation_parameters['package']['height'] > 2 ||  !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 32.4, 22.9)) {
                                    return;
                                }
                                break;
                            }
                        case 'INT_LETTER_AIR_OWN_PACKAGING_MEDIUM':
                            if (isset($validation_parameters['auspost_sub_services']['INT_LETTER_AIR_OWN_PACKAGING_MEDIUM']) && $validation_parameters['auspost_sub_services']['INT_LETTER_AIR_OWN_PACKAGING_MEDIUM']['enabled']) {
                                $rate_code = 'INT_LETTER_AIR_OWN_PACKAGING_MEDIUM';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['INT_LETTER_AIR_OWN_PACKAGING_MEDIUM']['name'];
                                if ($validation_parameters['package']['length'] > 36 || $validation_parameters['package']['width'] > 26 || $validation_parameters['package']['height'] > 2 || $validation_parameters['package']['weight'] > 0.25 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 36, 26)) {
                                    return;
                                }
                                break;
                            }
                        case 'INT_LETTER_AIR_OWN_PACKAGING_HEAVY':
                            if (isset($validation_parameters['auspost_sub_services']['INT_LETTER_AIR_OWN_PACKAGING_HEAVY']) && $validation_parameters['auspost_sub_services']['INT_LETTER_AIR_OWN_PACKAGING_HEAVY']['enabled']) {
                                $rate_code = 'INT_LETTER_AIR_OWN_PACKAGING_HEAVY';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['INT_LETTER_AIR_OWN_PACKAGING_HEAVY']['name'];
                                if ($validation_parameters['package']['length'] > 36 || $validation_parameters['package']['width'] > 26 || $validation_parameters['package']['height'] > 2 || $validation_parameters['package']['weight'] > 0.5 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 36, 26)) {
                                    return;
                                }
                                break;
                            }
                    }

                    if (isset($this->custom_services[$rate_code]['delivery_confirmation']) && !empty($this->custom_services[$rate_code]['delivery_confirmation'])) {
                        $delivery_confirmation = true;
                    }
                } else if ($validation_parameters['satchel_rate_status'] && $this->is_satchel($quote->code)) {
                    // Validating satchel rates
                    update_option("wf_auspost_satchel_rates_obtained", true);
                    switch ($quote->code) {
                        case 'AUS_PARCEL_REGULAR_SATCHEL_500G':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_PARCEL_REGULAR_SATCHEL_500G']) && $validation_parameters['auspost_sub_services']['AUS_PARCEL_REGULAR_SATCHEL_500G']['enabled']) {
                                $rate_code = 'AUS_PARCEL_REGULAR_SATCHEL_500G';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_PARCEL_REGULAR_SATCHEL_500G']['name'];

                                if ($validation_parameters['package']['length'] > 35 || $validation_parameters['package']['width'] > 22 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 35, 22)) {
                                    return;
                                }
                                break;
                            }
                        case 'AUS_PARCEL_EXPRESS_SATCHEL_SMALL':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_PARCEL_EXPRESS_SATCHEL_SMALL']) && $validation_parameters['auspost_sub_services']['AUS_PARCEL_EXPRESS_SATCHEL_SMALL']['enabled']) {
                                $rate_code = 'AUS_PARCEL_EXPRESS_SATCHEL_SMALL';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_PARCEL_EXPRESS_SATCHEL_SMALL']['name'];
                                if ($validation_parameters['package']['length'] > 35 || $validation_parameters['package']['width'] > 22 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 35, 22)) {
                                    return;
                                }
                                break;
                            }
                        case 'AUS_PARCEL_REGULAR_SATCHEL_1KG':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_PARCEL_REGULAR_SATCHEL_1KG']) && $validation_parameters['auspost_sub_services']['AUS_PARCEL_REGULAR_SATCHEL_1KG']['enabled']) {
                                $rate_code = 'AUS_PARCEL_REGULAR_SATCHEL_1KG';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_PARCEL_REGULAR_SATCHEL_1KG']['name'];
                                if ($validation_parameters['package']['length'] > 38.5 || $validation_parameters['package']['width'] > 26.5 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 38.5, 26.5)) {
                                    return;
                                }
                                break;
                            }
                        case 'AUS_PARCEL_EXPRESS_SATCHEL_MEDIUM':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_PARCEL_EXPRESS_SATCHEL_MEDIUM']) && $validation_parameters['auspost_sub_services']['AUS_PARCEL_EXPRESS_SATCHEL_MEDIUM']['enabled']) {
                                $rate_code = 'AUS_PARCEL_EXPRESS_SATCHEL_MEDIUM';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_PARCEL_EXPRESS_SATCHEL_MEDIUM']['name'];
                                if ($validation_parameters['package']['length'] > 38.5 || $validation_parameters['package']['width'] > 26.5 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 38.5, 26.5)) {
                                    return;
                                }
                                break;
                            }
                        case 'AUS_PARCEL_REGULAR_SATCHEL_3KG':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_PARCEL_REGULAR_SATCHEL_3KG']) && $validation_parameters['auspost_sub_services']['AUS_PARCEL_REGULAR_SATCHEL_3KG']['enabled']) {
                                $rate_code = 'AUS_PARCEL_REGULAR_SATCHEL_3KG';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_PARCEL_REGULAR_SATCHEL_3KG']['name'];
                                if ($validation_parameters['package']['length'] > 40 || $validation_parameters['package']['width'] > 31 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 40, 31)) {
                                    return;
                                }
                                break;
                            }
                        case 'AUS_PARCEL_EXPRESS_SATCHEL_LARGE':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_PARCEL_EXPRESS_SATCHEL_LARGE']) && $validation_parameters['auspost_sub_services']['AUS_PARCEL_EXPRESS_SATCHEL_LARGE']['enabled']) {
                                $rate_code = 'AUS_PARCEL_EXPRESS_SATCHEL_LARGE';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_PARCEL_EXPRESS_SATCHEL_LARGE']['name'];
                                if ($validation_parameters['package']['length'] > 40 || $validation_parameters['package']['width'] > 31 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 40, 31)) {
                                    return;
                                }
                                break;
                            }
                        case 'AUS_PARCEL_REGULAR_SATCHEL_5KG':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_PARCEL_REGULAR_SATCHEL_5KG']) && $validation_parameters['auspost_sub_services']['AUS_PARCEL_REGULAR_SATCHEL_5KG']['enabled']) {
                                $rate_code = 'AUS_PARCEL_REGULAR_SATCHEL_5KG';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_PARCEL_REGULAR_SATCHEL_5KG']['name'];
                                if ($validation_parameters['package']['length'] > 51 || $validation_parameters['package']['width'] > 43 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 51, 43)) {
                                    return;
                                }
                                break;
                            }
                        case 'AUS_PARCEL_EXPRESS_SATCHEL_EXTRA_LARGE':
                            if (isset($validation_parameters['auspost_sub_services']['AUS_PARCEL_EXPRESS_SATCHEL_EXTRA_LARGE']) && $validation_parameters['auspost_sub_services']['AUS_PARCEL_EXPRESS_SATCHEL_EXTRA_LARGE']['enabled']) {
                                $rate_code = 'AUS_PARCEL_EXPRESS_SATCHEL_EXTRA_LARGE';
                                $rate_id = $this->id . ':' . $rate_code;
                                $rate_name = (string) $validation_parameters['auspost_sub_services']['AUS_PARCEL_EXPRESS_SATCHEL_EXTRA_LARGE']['name'];
                                if ($validation_parameters['package']['length'] > 51 || $validation_parameters['package']['width'] > 43 || !$this->girth_validation($validation_parameters['package']['length'], $validation_parameters['package']['width'], $validation_parameters['package']['height'], 51, 43)) {
                                    return;
                                }
                                break;
                            }
                    }

                    if (isset($this->custom_services[$rate_code]['delivery_confirmation']) && !empty($this->custom_services[$rate_code]['delivery_confirmation'])) {
                        $delivery_confirmation = true;
                    }
                } else {
                    update_option('wf_auspost_satchel_rates_obtained', false);
                    update_option('wf_auspost_letter_rates_obtained', false);
                }

                $is_satchel = get_option('wf_auspost_satchel_rates_obtained');
                $is_letter = get_option('wf_auspost_letter_rates_obtained');

                if (($is_satchel == true) || ($is_letter == true)) {
                    if (isset($validation_parameters['auspost_sub_services'][$quote->code]) && $validation_parameters['auspost_sub_services'][$quote->code]['enabled']) {
                        $rate_cost = $quote->price;
                    }
                }
            } else if ($validation_parameters['service_code'] == $quote->code) {
                if (isset($this->custom_services[$rate_code]['delivery_confirmation']) && !empty($this->custom_services[$rate_code]['delivery_confirmation'])) {
                    $delivery_confirmation = true;
                }
                $rate_cost = $quote->price;
            }

            //Obtain the main service to which a sub service belongs to
            $main_service = isset($validation_parameters['auspost_sub_services'][$rate_code]['main_service']) ? $validation_parameters['auspost_sub_services'][$rate_code]['main_service'] : '';

            if (isset($validation_parameters['auspost_services'][$rate_code])) {
                $custom_services_rate_code = array_search($validation_parameters['auspost_services'][$rate_code], $validation_parameters['auspost_services']);
            }

            if ($rate_cost) {
                $shipping_rate_cost_parameters = array(
                    'auspost_services' => $validation_parameters['auspost_services'],
                    'auspost_sub_services' => $validation_parameters['auspost_sub_services'],
                    'shipping_rate_id' => $rate_id,
                    'shipping_rate_code' => $rate_code,
                    'shipping_rate_cost' => $rate_cost,
                    'extra_cover' => $validation_parameters['package_extra_cover']
                );
                $rate_cost = $this->get_shipping_rate_cost($shipping_rate_cost_parameters);
                $this->prepare_rate($rate_code, $rate_id, $rate_name, $rate_cost, $validation_parameters['package']);
            }
        }
        return;
    }

    /**
     * function adds post response shipping costs
     * @access private
     * @param array shipping_rate_cost_parameters
     * @return float rate_cost 
     */
    private function get_shipping_rate_cost($shipping_rate_cost_parameters)
    {
        $add_extra_cover = false;
        $add_authority_to_leave = false;
        $add_signature = false;
        $adjustment = 0;
        $adjustment_percentage = 0;

        $rate_code = $shipping_rate_cost_parameters['shipping_rate_code'];
        $rate_cost = $shipping_rate_cost_parameters['shipping_rate_cost'];

        // User wants extra cover
        if (isset($shipping_rate_cost_parameters['auspost_services'][$rate_code]) || isset($shipping_rate_cost_parameters['auspost_sub_services'][$rate_code])) {
            if (is_shop() || is_cart()) {
                if ((isset($shipping_rate_cost_parameters['auspost_services'][$rate_code]) && $shipping_rate_cost_parameters['auspost_services'][$rate_code]['extra_cover'] == true) || (!empty($main_service) && $shipping_rate_cost_parameters['auspost_sub_services'][$main_service]['extra_cover'] == true)) {
                    $add_extra_cover = true;
                }
            } else {
                // call from checkout page
                if (isset($this->settings['show_insurance_checkout_field']) && $this->settings['show_insurance_checkout_field'] == 'yes') {
                    if ($this->insurance_requested_at_checkout) {
                        $add_extra_cover = true;
                    }
                } else if ((isset($shipping_rate_cost_parameters['auspost_services'][$rate_code]) && $shipping_rate_cost_parameters['auspost_services'][$rate_code]['extra_cover'] == true) || (!empty($main_service) && isset($shipping_rate_cost_parameters['auspost_services'][$main_service]) && $shipping_rate_cost_parameters['auspost_services'][$main_service]['extra_cover'] == true)) {
                    $add_extra_cover = true;
                }
            }
            if ($add_extra_cover == true) {
                if ($this->is_international) {
                    if (($shipping_rate_cost_parameters['extra_cover'] != 0) && ($shipping_rate_cost_parameters['extra_cover'] <= 100)) {
                        $rate_cost += $this->extra_cover_cost['international']; // extra cover fee for less than 100
                    } elseif (($shipping_rate_cost_parameters['extra_cover'] > 100) && ($shipping_rate_cost_parameters['extra_cover'] <= 5000)) {
                        $rate_cost += $this->extra_cover_cost['international'] * $shipping_rate_cost_parameters['extra_cover'] / 100; // extra cover fee for greater than 100

                    }
                } else {
                    if (($shipping_rate_cost_parameters['extra_cover'] != 0) && ($shipping_rate_cost_parameters['extra_cover'] <= 100)) {
                        $rate_cost += $this->extra_cover_cost['domestic']; // extra cover fee for less than 100
                    } elseif (($shipping_rate_cost_parameters['extra_cover'] > 100) && ($shipping_rate_cost_parameters['extra_cover'] <= 5000)) {
                        $rate_cost += $this->extra_cover_cost['domestic'] * $shipping_rate_cost_parameters['extra_cover'] / 100; // extra cover fee for greater than 100

                    }
                }
            }

            if (is_shop() || is_cart()) {
                if (isset($shipping_rate_cost_parameters['auspost_services'][$rate_code]) && $shipping_rate_cost_parameters['auspost_services'][$rate_code]['delivery_confirmation'] == true) {
                    $add_signature = true;
                } else if (!empty($main_service) && isset($shipping_rate_cost_parameters['auspost_sub_services'][$main_service]['delivery_confirmation']) && ($shipping_rate_cost_parameters['auspost_sub_services'][$main_service]['delivery_confirmation'] == true)) {
                    $add_signature = true;
                }
            } else if (isset($this->settings['show_signature_required_field']) && $this->settings['show_signature_required_field'] === 'yes') {
                if ($this->signature_requested_at_checkout) {
                    $add_signature = true;
                }
            } else if (((isset($shipping_rate_cost_parameters['auspost_services'][$rate_code]) && in_array($rate_code, $this->delivery_confirmation)) || (!empty($main_service) && in_array($main_service, $this->delivery_confirmation)))) {
                if ((isset($shipping_rate_cost_parameters['auspost_services'][$rate_code]['delivery_confirmation']) && $shipping_rate_cost_parameters['auspost_services'][$rate_code]['delivery_confirmation'] == true) || (!empty($main_service) && isset($shipping_rate_cost_parameters['auspost_services'][$main_service]) && $shipping_rate_cost_parameters['auspost_services'][$main_service]['delivery_confirmation'] == true)) {
                    $add_signature = true;
                }
            }

            // User wants SOD
            if ($add_signature) {
                if (!$this->is_international) {
                    $rate_cost += $this->sod_cost['domestic'];
                } else {
                    $rate_cost += $this->sod_cost['international'];
                }
            }

            if (!empty($shipping_rate_cost_parameters['auspost_services'][$rate_code]['adjustment'])) {
                $adjustment = $shipping_rate_cost_parameters['auspost_services'][$rate_code]['adjustment'];
            } else if (!empty($main_service) && isset($shipping_rate_cost_parameters['auspost_services'][$main_service]) && $shipping_rate_cost_parameters['auspost_services'][$main_service]['adjustment']) {
                $adjustment = $shipping_rate_cost_parameters['auspost_services'][$main_service]['adjustment'];
            }

            if (!empty($shipping_rate_cost_parameters['auspost_services'][$rate_code]['adjustment_percent'])) {
                $adjustment_percentage = $rate_cost * ($shipping_rate_cost_parameters['auspost_services'][$rate_code]['adjustment_percent'] / 100);
            } else if (!empty($main_service) && isset($shipping_rate_cost_parameters['auspost_services'][$main_service]) && $shipping_rate_cost_parameters['auspost_services'][$main_service]['adjustment_percent']) {
                $adjustment_percentage = $rate_cost * ($shipping_rate_cost_parameters['auspost_services'][$main_service]['adjustment_percent'] / 100);
            }

            $rate_cost += $adjustment + $adjustment_percentage;

            if (!$this->is_international) {
                $this->previous_rate_cost_stored = 0;
                $convention_rate_name = '';

                // Satchel rates will return if the products/packages are of different satchel weights. 
                // We are providing a convention rate name for the satchel rates
                if (strpos($rate_code, '_REGULAR_SATCHEL_')) {
                    $convention_rate_name = $this->id . ':' . 'REGULAR_SATCHEL';
                    $this->previous_rate_cost_stored = get_option("rate_cost_" . $convention_rate_name . "ncr");
                } else if (strpos($rate_code, '_EXPRESS_SATCHEL_')) {
                    $convention_rate_name = $this->id . ':' . 'EXPRESS_SATCHEL';
                    $this->previous_rate_cost_stored = get_option("rate_cost_" . $convention_rate_name . "ncr");
                } else {
                    $this->previous_rate_cost_stored = get_option("rate_cost_" . $shipping_rate_cost_parameters['shipping_rate_id'] . "ncr");
                }

                if (!empty($convention_rate_name)) {
                    if ($this->previous_rate_cost_stored == 0) {
                        update_option("rate_cost_" . $convention_rate_name . "ncr", $rate_cost);
                    } else {
                        $rate_cost = $rate_cost + $this->previous_rate_cost_stored;
                        update_option("rate_cost_" . $convention_rate_name . "ncr", $rate_cost);
                    }
                } else {
                    if ($this->previous_rate_cost_stored == 0) {
                        update_option("rate_cost_" . $shipping_rate_cost_parameters['shipping_rate_id'] . "ncr", $rate_cost);
                    } else {
                        $rate_cost = $rate_cost + $this->previous_rate_cost_stored;
                        update_option("rate_cost_" . $shipping_rate_cost_parameters['shipping_rate_id'] . "ncr", $rate_cost);
                    }
                }
            } else {
                $this->previous_rate_cost_stored = get_option("rate_cost_" . $shipping_rate_cost_parameters['shipping_rate_id'] . "ncr");
                if ($this->previous_rate_cost_stored == 0) {
                    update_option("rate_cost_" . $shipping_rate_cost_parameters['shipping_rate_id'] . "ncr", $rate_cost);
                } else {
                    $rate_cost = $rate_cost + $this->previous_rate_cost_stored;
                    update_option("rate_cost_" . $shipping_rate_cost_parameters['shipping_rate_id'] . "ncr", $rate_cost);
                }
            }
        } else {
            $this->debug("Save services in Settings", 'error');
        }
        return $rate_cost;
    }

    /**
     * prepare rate function.
     *
     * @access private
     * @param mixed $rate_code
     * @param mixed $rate_id
     * @param mixed $rate_name
     * @param mixed $rate_cost
     * @return void
     */
    private function prepare_rate($rate_code, $rate_id, $rate_name, $rate_cost, $package_request = '')
    {

        $rate_actual_code = $rate_code;

        if (!empty($this->custom_services[$rate_code])) {
            $this->custom_services[$rate_code] = apply_filters('wf_australia_post_rate_services', $this->custom_services, $this->custom_services[$rate_code], $rate_code, $package_request);
        } else if (!empty($this->custom_sub_services[$rate_code])) {
            $this->custom_sub_services[$rate_code] = apply_filters('wf_australia_post_rate_services', $this->custom_sub_services, $this->custom_sub_services[$rate_code], $rate_code, $package_request);
        }
        // Name adjustment
        // Satchel rates will return if the products/packages are of different satchel weights. 
        // We are providing common rate name and common rate code for the satchel rates
        $main_service_rate_code = $rate_code;        
        if (!empty($this->general_settings['services'][$rate_code]['name'])) {
            $rate_name = $this->general_settings['services'][$rate_code]['name'];
        } else if (!empty($this->general_settings['sub_services'][$rate_code]['name'])) {
            $rate_name = $this->general_settings['sub_services'][$rate_code]['name'];
            $main_service_rate_code = $this->general_settings['sub_services'][$rate_code]['main_service'];
        }

        if (strpos($rate_code, '_REGULAR_SATCHEL_')) {
            $rate_id = $this->id . ':' . 'REGULAR_SATCHEL';
        } else if (strpos($rate_code, '_EXPRESS_SATCHEL_')) {
            $rate_id = $this->id . ':' . 'EXPRESS_SATCHEL';
        }
        
		// Cost adjustment %
        if (!empty($this->custom_services[$main_service_rate_code]['adjustment_percent']))
            $rate_cost = $rate_cost + ($rate_cost * (floatval($this->custom_services[$main_service_rate_code]['adjustment_percent']) / 100));

        // Cost adjustment
        if (!empty($this->custom_services[$main_service_rate_code]['adjustment']))
            $rate_cost = $rate_cost + floatval($this->custom_services[$main_service_rate_code]['adjustment']);

        // Enabled check
        if ((isset($this->custom_services[$rate_code]) && isset($this->custom_services[$rate_code]['enabled']) && empty($this->custom_services[$rate_code]['enabled'])) || (isset($this->custom_sub_services[$rate_code]) && isset($this->custom_sub_services[$rate_code]['enabled']) && empty($this->custom_sub_services[$rate_code]['enabled'])))
            return;

        // Merging
        if (isset($this->found_rates[$rate_id])) {
            $rate_cost = $rate_cost;
            $packages = 1 + $this->found_rates[$rate_id]['packages'];
        } else {
            $packages = 1;
        }

        // Sort
        if (isset($this->custom_services[$rate_code]['order'])) {
            $sort = $this->custom_services[$rate_code]['order'];
        } else {
            $sort = 999;
        }

        $this->found_rates[$rate_id] = array(
            'id' => $rate_id,
            'label' => $rate_name . ' (' . $this->title . ')',
            'cost' => $rate_cost,
            'sort' => $sort,
            'packages' => $packages,
            'actual_code' => $rate_actual_code
        );
    }

    /**
     * get_response function.
     *
     * @access private
     * @param mixed $endpoint
     * @param mixed $request
     * @return void
     */
    private function get_response($endpoint, $request, $headers)
    {
        global $woocommerce;
        $response = array();

        $rate_response = wp_remote_get(
            $endpoint . '?' . $request,
            array(
                'timeout' => 70,
                'sslverify' => 0,
                'headers' => $headers
            )
        );

        if (is_wp_error($rate_response)) {
            $error_string = $rate_response->get_error_message();
            $this->debug($error_string, 'error');
            return array();
        } else {
            $response = json_decode($rate_response['body']);
            if(isset($response->services) && isset($response->services->service) && is_object($response->services->service)){
                $response->service_obj = $response->services->service;
                $response->services->service = array();
                $response->services->service[0] = $response->service_obj;
            }
        }

        $this->debug('Australia Post REQUEST: <pre>' . print_r(htmlspecialchars($request), true) . '</pre>');
        $this->debug('Australia Post RESPONSE: <pre>' . print_r($response, true) . '</pre>');

        return $response;
    }

    /**
     * sort_rates function.
     *
     * @access public
     * @param mixed $a
     * @param mixed $b
     * @return void
     */
    public function sort_rates($a, $b)
    {
        if ($a['sort'] == $b['sort'])
            return 0;
        return ($a['sort'] < $b['sort']) ? -1 : 1;
    }

    /**
     * get_request_header for JSON function.
     *
     */
    private function buildHttpHeaders($request, $api_account_number, $api_password, $api_key = false)
    {
        $api_key = $api_key ? $api_key : $this->api_key;
        $a_headers = array(
            'content-type' => 'application/json',
            'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
            'Account-Number' => $api_account_number,
            'Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_password),
        );
        return $a_headers;
    }

    /**
     * get_request_header function.
     *
     * @access private
     * @return array
     */
    private function get_request_header($api_key = false)
    {
        $api_key = $api_key ? $api_key : $this->api_key;
        return array(
            'AUTH-KEY' => $api_key
        );
    }

    /**
     * get_request function.
     *
     * @access private
     * @param mixed $package
     * @return void
     */
    private function get_request($package)
    {

        $request = array();
        $from_postcode = $this->origin;
        if ($this->vendor_check) {
            if( isset($package['origin']) && isset($package['origin']['postcode']) && !empty($package['origin']['postcode'])){
                $from_postcode = $package['origin']['postcode'];
            }elseif(isset($package['seller_id']) && !empty($package['seller_id'])){
                $seller_id = $package['seller_id'];
                $vendor_origin_address = apply_filters('elex_vendor_formate_origin_address', $seller_id);
                if(isset($vendor_origin_address['postcode']) && !empty($vendor_origin_address['postcode']))
                    $from_postcode = $vendor_origin_address['postcode'];

            }
        }
        $request['from_postcode'] = str_replace(' ', '', strtoupper($from_postcode));

        switch ($package['destination']['country']) {
            case "AU":
                $request['to_postcode'] = str_replace(' ', '', strtoupper($package['destination']['postcode']));
                break;
            default:
                $request['country_code'] = $package['destination']['country'];
                break;
        }

        return $request;
    }

    /**
     * get_request function.
     *
     * @access private
     * @return void
     */
    private function get_package_requests($package)
    {
        $requests = array();
        // Choose selected packing
        switch ($this->packing_method) {
            case 'weight':
                $requests = $this->weight_only_shipping($package);
                break;
            case 'box_packing':
                $requests = $this->box_shipping($package);
                break;
            case 'per_item':
            default:
                $requests = $this->per_item_shipping($package);
                if ($this->group_shipping && $this->group_shipping == 'yes') {
                    $this->group_shipping_enabled = true;
                }
                break;
        }
        return $requests;
    }

    /**
     * Get additonal requests.
     *
     * @param array $package
     * @param array $regular_requests
     * @return array
     */
    private function get_additional_package_requests($package, $regular_requests)
    {
        $requests = array();
        if ($package['destination']['country'] == 'AU' || isset($request['thickness']))
            return array();

        // Special requests + extra cover + registered post
        if ($regular_requests) {
            foreach ($regular_requests as $request) {
                $validation_parameters = array(
                    'request' => $request,
                    'package' => $package,
                );
                $requests = $this->validate_additional_package_requests($validation_parameters);
            }
        }

        return $requests;
    }

    /**
     * function to validate additional package requests
     * @access private
     * @param array validation_parameters
     * @return array requests
     */
    private function validate_additional_package_requests($validation_parameters)
    {
        $requests = array();
        foreach ($this->services as $code => $service) {

            if (empty($this->custom_services[$code]['enabled'])) {
                continue;
            }

            $extra_cover = false;
            $create_request = false;

            if (!empty($this->custom_services[$code]['extra_cover']) && isset($validation_parameters['request']['extra_cover'])) {
                $extra_cover = true;
            }

            switch ($code) {

                case "INT_PARCEL_SEA_OWN_PACKAGING":
                case "INT_PARCEL_AIR_OWN_PACKAGING":

                    if ($validation_parameters['package']['destination']['country'] == 'AU' || isset($validation_parameters['request']['thickness'])) {
                        return;
                    }

                    $validation_parameters['request']['service_code'] = $code;

                    if ($extra_cover && $validation_parameters['request']['extra_cover'] < $this->extra_cover[$code]) {
                        $validation_parameters['request']['option_code'] = 'INTL_SERVICE_OPTION_EXTRA_COVER';
                        $create_request = true;
                    }

                    break;
            }

            if ($create_request) {
                $requests[] = $validation_parameters['request'];
            }
        }
        return $requests;
    }

    /**
     * function to return composite data of a WC_Composite_Product as a wooCommerce packages array
     * For an assembled Composite product we are taking product's weight and dimensions
     * For a non-assembled Composite product, algorithm takes the components and sends as individual packages 
     *
     * @access private
     * @param mixed woocommerce shipping packges $packages
     * @return mixed woocommerce shipping packges $packages
     */
    private function get_composite_product_data($package)
    {
        $package_composite_products_data = array();
        $shipping_package = array();

        foreach ($package['contents'] as $item_id => $values) {
            if (!empty($values['data']->get_weight()) && !empty($values['data']->get_length()) && !empty($values['data']->get_width()) && !empty($values['data']->get_height())) {
                if (!empty($item_id)) {
                    $shipping_package['contents'][$item_id] = $values ;
                }
            } else {
                $components_id_array = array();
                $components_id_array_index = 0;
                if (isset($values['composite_data'])) {
                    $composite_data = $values['composite_data'];
                    foreach ($composite_data as $composite_datum) {
                        if (!empty($components_id_array) && array_key_exists($composite_datum['product_id'], $components_id_array)) {
                            $components_id_array[$composite_datum['product_id']] += 1;
                        } else {
                            $components_id_array[$components_id_array_index]['product_id'] = $composite_datum['product_id'];
                            if (isset($composite_datum['variation_id'])) {
                                $components_id_array[$components_id_array_index]['variation_id'] = $composite_datum['variation_id'];
                            }
                            $components_id_array[$components_id_array_index]['quantity'] = $composite_datum['quantity'];
                        }
                        $components_id_array_index++;
                    }
                    $composite_product_data = $values['data'];
                    $composite_product_id = $composite_product_data->get_id();
                    $components_id_array['parent_product_id'] = $composite_product_id;

                    $package_composite_products_data[$item_id] = $components_id_array;
                }
            }
        }

        if (!empty($package_composite_products_data)) {
            $package_composite_products_data = $this->composite_data_unique(array_shift($package_composite_products_data));
            foreach ($package_composite_products_data as $package_composite_products_datum) {
                $composite_product_id = isset($package_composite_products_datum['variation_id']) ? $package_composite_products_datum['variation_id'] : $package_composite_products_datum['product_id'];
                $composite_product = wc_get_product($composite_product_id);
                if (!empty($composite_product_id)) {
                    $shipping_package['contents'][$composite_product_id] = $package_composite_products_datum;
                    $shipping_package['contents'][$composite_product_id]['data'] = $composite_product;
                }
            }
        }
        $package['contents'] = $shipping_package['contents'];

        return $package;
    }

    private function composite_data_unique($package_composite_products_data)
    {
        $composite_data_unique = array();
        foreach ($package_composite_products_data as $package_composite_products_datum_key => $package_composite_products_datum) {
            if (empty($composite_data_unique)) {
                $composite_data_unique[] = $package_composite_products_datum;
            } else {
                $found = false;
                foreach ($composite_data_unique as $composite_data_element_key => $composite_data_element) {
                    if ($composite_data_element['product_id'] == $package_composite_products_datum_key) {
                        $composite_data_unique['quantity'] += 1;
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $composite_data_unique[] = $package_composite_products_datum;
                }
            }
        }
        return $composite_data_unique;
    }

    /**
     * weight_only_shipping function.
     *
     * @access private
     * @param mixed $package
     * @return void
     */
    private function weight_only_shipping($package)
    {
        global $woocommerce;
        if (!class_exists('WeightPack')) {
            include_once 'class-wf-weight-packing.php';
        }
        $weight_pack = new WeightPack($this->weight_packing_process);
        $weight_pack->set_max_weight($this->max_weight);

        $package_total_weight = 0;
        $insured_value = 0;
        $insurance_array = array(
            'Amount' => 0,
            'Currency' => get_woocommerce_currency()
        );
        $to_ship = array();

        /* For WooCommerce Composite Products */
        if ($this->is_woocommerce_composite_products_installed) {
            $package = $this->get_composite_product_data($package);
        }

        $ctr = 0;

        foreach ($package['contents'] as $item_id => $values) {

            $ctr++;
            $product_quantity = 0;
            $product_quantity = $values['quantity'];
            $item_data = $values['data'];

            $product_data = array();
            $product = wc_get_product($values['variation_id'] ? $values['variation_id'] : $values['product_id']);

            if ($values['variation_id']) {

                $product_parent_data = $product->get_parent_data();
                $product_variation_data = $product->get_data();

                if (empty($product_variation_data['weight'])) {
                    $product_data['weight'] = $product_parent_data['weight'];
                } else {
                    $product_data['weight'] = $product_variation_data['weight'];
                }

                if (empty($product_variation_data['length'])) {
                    $product_data['length'] = $product_parent_data['length'];
                } else {
                    $product_data['length'] = $product_variation_data['length'];
                }

                if (empty($product_variation_data['width'])) {
                    $product_data['width'] = $product_parent_data['width'];
                } else {
                    $product_data['width'] = $product_variation_data['width'];
                }

                if (empty($product_variation_data['height'])) {
                    $product_data['height'] = $product_parent_data['height'];
                } else {
                    $product_data['height'] = $product_variation_data['height'];
                }
            } else if (!empty($product)) {
                $product_data = $product->get_data();
            }

            $product_weight = wc_get_weight($product_data['weight'], 'kg', $this->weight_unit);
            if (!$this->startrack_enabled) {
                if ($product_weight > 22) {
                    $this->debug(sprintf(__('Product %d has invalid weight/dimensions. Aborting. See <a href="https://auspost.com.au/sending/check-sending-guidelines/size-weight-guidelines">https://auspost.com.au/sending/check-sending-guidelines/size-weight-guidelines</a>', 'wf-shipping-auspost'), $item_id), 'error');
                    return;
                }
            }

            $skip_product = apply_filters('wf_shipping_skip_product', false, $values, $package['contents']);
            if ($skip_product) {
                continue;
            }

            if (!($values['quantity'] > 0 && $values['data']->needs_shipping())) {
                $this->debug(sprintf(__('Product #%d is virtual. Skipping.', 'wf-australia-post'), $ctr));
                continue;
            }

            if (!$product_data['weight']) {
                $this->debug(sprintf(__('Product #%d is missing weight.', 'wf-australia-post'), $ctr), 'error');
                return;
            }

            $weight_pack->add_item($product_data['weight'], $values, $values['quantity']);
        }

        $pack = $weight_pack->pack_items();
        $errors = $pack->get_errors();

        if (!empty($errors)) {
            //do nothing
            return;
        } else {
            $boxes = $pack->get_packed_boxes();
            $unpacked_items = $pack->get_unpacked_items();

            $parcels = array_merge($boxes, $unpacked_items); // merge items if unpacked are allowed
            $parcel_count = count($parcels);
            // get all items to pass if item info in box is not distinguished
            $packable_items = $weight_pack->get_packable_items();
            $all_items = array();
            if (is_array($packable_items)) {
                foreach ($packable_items as $packable_item) {
                    $all_items[] = $packable_item['data'];
                }
            }

            foreach ($parcels as $parcel) {
                $packed_products = array();
                if (!empty($parcel['items'])) {
                    foreach ($parcel['items'] as $item) {
                        // $insured_value = $insured_value + $item->get_price();
                        $item_data = $item['data'];
                        if ($item['variation_id']) {
                            $item_meta_data = $item_data->get_parent_data();
                            if (empty($item_meta_data['weight']) && empty($item_meta_data['length']) && empty($item_meta_data['width']) && empty($item_meta_data['height'])) {
                                $item_meta_data = $item_data->get_data();
                            }
                        } else {
                            $item_meta_data = $item_data->get_data();
                        }

                        if (!isset($item_meta_data['price']) && empty($item_meta_data['price'])) {
                            $item_meta_data = $item_data->get_data();
                        }
                        $insured_value = $insured_value + $item_meta_data['price'];
                    }
                } else {
                    if (isset($order_total) && $parcel_count) {
                        $insured_value = $order_total / $parcel_count;
                    }
                }

                $packed_products = isset($parcel['items']) ? $parcel['items'] : $all_items;
                // Creating parcel request
                $parcel_total_weight = $parcel['weight'];

                $packed_product_length = array();
                $packed_product_width = array();
                $packed_product_height = array();
                $insurance_array['Amount'] = $insured_value;
                $product_age_check_selected = '';
                $cubic_volume = 0;
                foreach ($packed_products as $packed_product) {

                    $product = wc_get_product($packed_product['variation_id'] ? $packed_product['variation_id'] : $packed_product['product_id']);
                    if ($packed_product['variation_id']) {
                        $product_data = $product->get_data();
                        if (empty($product_data['length']) && empty($product_data['width']) && empty($product_data['height'])) {
                            $product_data = $product->get_parent_data();
                        }

                        if (empty($product_age_check_selected)) {
                            $product_age_check_selected = get_post_meta($packed_product['variation_id'], 'age_check_auspost_elex', true);
                        }

                        $product_dimension = array(
                            $product_data['length'],
                            $product_data['width'],
                            $product_data['height']
                        );

                        rsort($product_dimension);

                        array_push($packed_product_length, $product_dimension[0]);
                        array_push($packed_product_width, $product_dimension[1]);
                        array_push($packed_product_height, $product_dimension[2]);
                    } else if ($packed_product['product_id']) {
                        $product_data = $product->get_data();
                        $product_dimension = array(
                            $product_data['length'],
                            $product_data['width'],
                            $product_data['height']
                        );

                        if (empty($product_age_check_selected)) {
                            $product_age_check_selected = get_post_meta($packed_product['product_id'], 'age_check_auspost_elex', true);
                        }

                        rsort($product_dimension);

                        array_push($packed_product_length, $product_dimension[0]);
                        array_push($packed_product_width, $product_dimension[1]);
                        array_push($packed_product_height, $product_dimension[2]);
                    }
                    $cubic_volume = $cubic_volume + ((wc_get_dimension($product_dimension[0], 'm', $this->dimension_unit)) * (wc_get_dimension($product_dimension[1], 'm', $this->dimension_unit)) * (wc_get_dimension($product_dimension[2], 'm', $this->dimension_unit)));
                }
                
                $dimensions = array(
                    'length' => $this->return_highest($packed_product_length),
                    'width' => $this->return_highest($packed_product_width),
                    'height' => $this->return_highest($packed_product_height)
                );
                
                $group = array(
                    'Weight' => array(
                        'Value' => round($parcel['weight'], 3),
                        'Units' => $this->weight_unit
                    ),
                    'Dimensions' => array(
                        'Length' => round($dimensions['length'],2),
                        'Width' => round($dimensions['width'],2),
                        'Height' => round($dimensions['height'],2),
                        'Units' => $this->dimension_unit
                    ),
                    'InsuredValue' => $insurance_array,
                    'packed_products' => $packed_products,
                    'pack_type' => 'BAG',
                    'cubic_volume' => $cubic_volume,
                    'age_check' => $product_age_check_selected ? $product_age_check_selected : ''
                );

                $to_ship[] = $group;
            }
            return $to_ship;
        }
    }

    /**
     * per_item_shipping function
     *
     * @access private
     * @param mixed $package
     * @return void
     */
    private function per_item_shipping($package)
    {
        global $woocommerce;

        $domestic = $package['destination']['country'] == 'AU' ? 'yes' : 'no';

        $requests = array();

        /* For WooCommerce Composite Products */
        if ($this->is_woocommerce_composite_products_installed) {
            $package = $this->get_composite_product_data($package);
        }

        // Get weight of order
        foreach ($package['contents'] as $item_id => $values) {
            $values['data'] = $this->wf_load_product($values['data']);
            if (!$values['data']->needs_shipping()) {
                $this->debug(sprintf(__('Product #%d is virtual. Skipping.', 'wf-shipping-auspost'), $item_id));
                continue;
            }

            if (!$values['data']->get_weight() || !$values['data']->get_length() || !$values['data']->get_height() || !$values['data']->get_width()) {
                $this->debug(sprintf(__('Product #%d is missing weight/dimensions. Aborting.', 'wf-shipping-auspost'), $item_id));
                return;
            }

            $product_ordered_quantity = $values['quantity'];

            $product_age_check_selected = get_post_meta($item_id, 'age_check_auspost_elex', true);

            $parcel = array();

            $parcel['weight'] = $values['data']->get_weight();

            $dimensions = array($values['data']->get_length(), $values['data']->get_width(), $values['data']->get_height());

            $parcel_volume = wc_get_dimension($values['data']->get_length(), 'm') * wc_get_dimension($values['data']->get_width(), 'm') * wc_get_dimension($values['data']->get_height(), 'm');

            sort($dimensions);

            $from_dimension_unit = '';
            if ($this->dimension_unit != 'cm') {
                $from_dimension_unit = $this->dimension_unit;
            }

            // Min sizes - girth minimum is 16cm
            $girth = (round(wc_get_dimension($dimensions[0], 'cm', $from_dimension_unit)) + round(wc_get_dimension($dimensions[1], 'cm', $from_dimension_unit))) * 2;

            $parcel_weight = wc_get_weight($parcel['weight'], 'kg', $this->weight_unit);
            if (!$this->startrack_enabled) {
                if ($parcel_weight > 22 || (wc_get_dimension($dimensions[2], 'cm', $from_dimension_unit)) > 105) {
                    $this->debug(sprintf(__('Product %d has invalid weight/dimensions. Aborting. See <a href="https://auspost.com.au/sending/check-sending-guidelines/size-weight-guidelines">https://auspost.com.au/sending/check-sending-guidelines/size-weight-guidelines</a>', 'wf-shipping-auspost'), $item_id), 'error');
                    return;
                }
            }

            // Allowed maximum volume of a product is 0.25 cubic meters for domestic shipments
            if ($domestic == 'yes' && $parcel_volume > 0.25) {
                $this->debug(sprintf(__('Product %s exceeds 0.25 cubic meters Aborting. See <a href="https://auspost.com.au/sending/check-sending-guidelines/size-weight-guidelines">https://auspost.com.au/sending/check-sending-guidelines/size-weight-guidelines</a>', 'wf-shipping-auspost'), $values['data']->get_name()), 'error');
                return;
            }

            // The girth should lie between 16cm and 140cm for international shipments
            if ($domestic == 'no' && ($girth < 16 || $girth > 140)) {
                $this->debug(sprintf(__('Girth of the product %s should lie in between 16cm and 140cm. See <a href="http://ausporthst.com.au/personal/parcel-dimensions.html">https://auspost.com.au/sending/check-sending-guidelines/size-weight-guidelines</a>', 'wf-shipping-auspost'), $values['data']->get_name()), 'error');
                return;
            }

            $insurance_array = array(
                'Amount' => ceil($values['data']->get_price()),
                'Currency' => get_woocommerce_currency()
            );

            $group = array(
                'Weight' => array(
                    'Value' => round($parcel['weight'], 3),
                    'Units' => $this->weight_unit
                ),
                'Dimensions' => array(
                    'Length' => round($dimensions[2],2),
                    'Width' => round($dimensions[1],2),
                    'Height' => round($dimensions[0],2),
                    'Units' => $this->dimension_unit
                ),
                'InsuredValue'      => $insurance_array,
                'pack_type'         => 'ITM',
                'packed_products'   => array($values['data']),
                'age_check'         => $product_age_check_selected ? $product_age_check_selected : ''
            );

            for ($quantity = 0; $quantity < $product_ordered_quantity; $quantity++) {
                $to_ship[] = $group;
            }
        }

        return $to_ship;
    }

    /**
     *   Function to filter boxes for satchels
     *   Using this function we adding domestic satchels for domestic shipments and 
     *   international satchels for international shipments
     */
    private function filter_boxes_for_satchels($box, $box_pack)
    {
        $this->pre_defined_boxes = include('settings/wf_auspost_predefined_boxes.php');
        $box_name = $box['name'];

        if (isset($this->pre_defined_boxes[$box_name]['eligible_for']) && $this->pre_defined_boxes[$box_name]['name'] == $box_name && (!$this->contracted_rates)) {
            if ($this->shipment_type == 'Domestic') { // If shipment type is domestic
                if ($this->pre_defined_boxes[$box_name]['eligible_for'] == 'Domestic') { // Adding satchels which are eligible for domestic shipment
                    return $box_pack->add_box($box['outer_length'], $box['outer_width'], $box['outer_height'], $box['box_weight'], '', $box['box_type']);
                }
            } else {
                if ($this->pre_defined_boxes[$box_name]['eligible_for'] == 'International') { // Adding satchels which are eligible for international shipment
                    return $box_pack->add_box($box['outer_length'], $box['outer_width'], $box['outer_height'], $box['box_weight'], '', $box['box_type']);
                }
            }
        } else {
            return $box_pack->add_box($box['outer_length'], $box['outer_width'], $box['outer_height'], $box['box_weight'], $box['pack_type'], $box['box_type']);
        }
    }

    /**
     * box_shipping function.
     *
     * @access private
     * @param mixed $package
     * @return void
     */
    private function box_shipping($package)
    {
        $box_packing_method = !empty($this->get_option('box_packing_method')) ? $this->get_option('box_packing_method') : 'volume';

        if ($box_packing_method == 'stack') {
            if (!class_exists('WF_Boxpack_Stack')) {
                include_once 'class-wf-packing-stack.php';
            }
            $boxpack = new WF_Boxpack_Stack();
            $this->debug(__('Packed based on Stack First', 'wf-australia-post'));
        } else {
            if (!class_exists('WF_Boxpack')) {
                include_once 'class-wf-packing.php';
            }
            $boxpack = new WF_Boxpack();
            $this->debug(__('Packed based on Volume Based', 'wf-australia-post'));
        }


        /* For WooCommerce Composite Products */
        if ($this->is_woocommerce_composite_products_installed) {
            $package = $this->get_composite_product_data($package);
        }

        if ($package['destination']['country'] == 'AU') {
            $this->shipment_type = 'Domestic';
        } else {
            $this->shipment_type = 'International';
        }

        $boxes_for_packing = array();


        // Retrieving the stored pre-defined and custom boxes from the database
        $stored_pre_defined_boxes = get_option('auspost_stored_pre_defined_boxes');
        $stored_custom_boxes = get_option('auspost_stored_custom_boxes');
        $stored_boxes_for_packing_starTrack = get_option('starTrack_stored_boxes');
        $stored_auspost_boxes = array();

        if ($this->contracted_rates) {
            // Retrieving the stored custom boxes from the database
            $stored_auspost_boxes = $stored_custom_boxes;
        } else {
            // Retrieving the stored pre-defined from the database
            $stored_pre_defined_boxes = get_option('auspost_stored_pre_defined_boxes');
            //Merging both pre-defined and custom boxes for the non-contracted accounts
            $stored_auspost_boxes = array_merge($stored_pre_defined_boxes, $stored_custom_boxes);
        }

        if ($this->contracted_rates && $this->rate_type == 'startrack') {
            $boxes_for_packing = $stored_boxes_for_packing_starTrack;
        } else if (!empty($stored_auspost_boxes)) {
            $boxes_for_packing = $stored_auspost_boxes;
        }

        // Define boxes
        foreach ($boxes_for_packing as $key => $box) {
            if (!$box['enabled']) {
                continue;
            }

            $box['box_type'] = '';

            // Defining box type
            if ($box['is_letter']) {
                $box['box_type'] = 'letter';
            }

            $newbox = $this->filter_boxes_for_satchels($box, $boxpack);

            if ($newbox != null) {
                $newbox->set_name($box['name']);
                $newbox->set_inner_dimensions($box['inner_length'], $box['inner_width'], $box['inner_height']);

                if (isset($box['id'])) {
                    $newbox->set_id(current(explode(':', $box['id'])));
                }

                if ($box['max_weight']) {
                    $newbox->set_max_weight($box['max_weight']);
                }

                if ($box['is_letter']) {
                    $newbox->set_boxtype('letter');
                }
            }
        }

        // Add items
        foreach ($package['contents'] as $item_id => $values) {
            if (!$values['data']->needs_shipping()) {
                $this->debug(sprintf(__('Product # is virtual. Skipping.', 'wf-australia-post'), $item_id), 'error');
                continue;
            }

            $skip_product = apply_filters('wf_shipping_skip_product', false, $values, $package['contents']);
            if ($skip_product) {
                continue;
            }

            if (wf_get_product_length($values['data']) && wf_get_product_height($values['data']) && wf_get_product_width($values['data']) && wf_get_product_weight($values['data'])) {

                $dimensions = array(wf_get_product_length($values['data']), wf_get_product_width($values['data']), wf_get_product_height($values['data']));

                for ($i = 0; $i < $values['quantity']; $i++) {
                    $boxpack->add_item(
                        $dimensions[0],
                        $dimensions[1],
                        $dimensions[2],
                        $values['data']->get_weight(),
                        $values['data']->get_price(),
                        array(
                            'data' => $values['data']
                        )
                    );
                }
            } else {
                $this->debug(sprintf(__('Product #%s is missing dimensions. Aborting.', 'wf-shipping'), $item_id), 'error');
                return;
            }
        }

        // Pack it
        $boxpack->pack();

        $packages = $boxpack->get_packages();
        $not_packed_items = $boxpack->get_cannot_pack();

        $to_ship = array();

        // To show unpacked items
        if (!empty($not_packed_items) && is_array($not_packed_items)) {
            foreach ($not_packed_items as $not_packed_item) {
                $not_packed_meta_data = $not_packed_item->get_meta('data');
                $not_packed_item_data = $not_packed_meta_data->get_data();
                $not_packed_item = $not_packed_item_data['name'];
                $this->debug($not_packed_item . ' not packed in any box', 'notice');
            }
        }

        $product_age_check_selected = '';

        foreach ($packages as $package) {
            if (!empty($package)) {
                $packed_product_name = '';

                if (isset($package->packed) && !empty($package->packed)) {
                    $this->debug('Packed in ' . strtok($package->name, '('), 'notice');
                }

                if (isset($package->packed) && !empty($package->packed)) {
                    foreach ($package->packed as $package_element) {
                        $package_element_meta = $package_element->meta;
                        $package_element_data = $package_element_meta['data'];
                        if (empty($product_age_check_selected)) {
                            $product_age_check_selected = get_post_meta($package_element_data->get_id(), 'age_check_auspost_elex', true);
                        }
                    }
                }

                $dimensions = array($package->length, $package->width, $package->height);

                //Retrieving package type
                $package_type = !empty($package->boxtype) ? $package->boxtype : '';

                if ($package_type != 'letter')
                    sort($dimensions);

                $insurance_array = array(
                    'Amount' => round($package->value),
                    'Currency' => get_woocommerce_currency()
                );

                if ($package_type == 'letter') {
                    $group = array(
                        'Name' => !empty($package->name) ? $package->name : '', // Adding box name
                        'Weight' => array(
                            'Value' => round($package->weight, 2),
                            'Units' => $this->weight_unit
                        ),
                        'Dimensions' => array(
                            'Length' => max(0.1, round($dimensions[0], 2)),
                            'Width' => max(0.1, round($dimensions[1], 2)),
                            'Thickness' => max(0.1, round($dimensions[2], 2)),
                            'Units' => $this->dimension_unit
                        ),
                        'InsuredValue' => $insurance_array,
                        'packed_products' => array(),
                        'pack_type' => 'ENV'
                    );
                } else {
                    $group = array(
                        'Name' => !empty($package->name) ? $package->name : '', // Adding box name
                        'Weight' => array(
                            'Value' => round($package->weight, 2),
                            'Units' => $this->weight_unit
                        ),
                        'Dimensions' => array(
                            'Length' => max(0.1, round($dimensions[2], 2)),
                            'Width' => max(0.1, round($dimensions[1], 2)),
                            'Height' => max(0.1, round($dimensions[0], 2)),
                            'Units' => $this->dimension_unit
                        ),
                        'InsuredValue' => $insurance_array,
                        'packed_products' => array(),
                        'pack_type' => $package->packtype != 'NONE' ? $package->packtype : 'ITM',
                        'age_check' => $product_age_check_selected ? $product_age_check_selected : ''
                    );
                }

                if (!empty($package->packed) && is_array($package->packed)) {
                    foreach ($package->packed as $packed) {
                        $group['packed_products'][] = $packed->get_meta('data');
                    }
                }

                $to_ship[] = $group;
            }
        }

        return $to_ship;
    }

    /**
     * function to return product data
     */
    private function wf_load_product($product)
    {
        if (!$product) {
            return false;
        }
        return (WC()->version < '2.7.0') ? $product : new wf_product($product);
    }

    private function get_contracted_rates($endpoint, $request, $headers, $rate_type = 'auspost')
    {
        global $woocommerce;
        $args = array(
            'method' => 'POST',
            'httpversion' => '1.1',
            'headers' => $headers,
            'body' => json_encode($request)
        );
        $res = wp_remote_post($endpoint, $args);
        if (is_wp_error($res)) {
            $error_string = $res->get_error_message();
            $this->debug($error_string, 'error');
            return array();
        }
        $response_array = isset($res['body']) ? json_decode($res['body']) : array();
        $shipment_rates_result = array();
        if ($rate_type == 'startrack') {
            /*For StarTRack*/
            if (!empty($response_array->errors)) {
                $shipment_rates_result['error_message'] = $response_array->errors[0]->message;
                return $shipment_rates_result;
            }
            if (!empty($response_array)) {

                if (isset($response_array->shipments)) {
                    $shipment_rates_result['shipment_summary'] = array(
                        "prices"    => $response_array->shipments[0]->shipment_summary,
                        "items"     => $response_array->shipments[0]->items,
                        "options"   => $response_array->shipments[0]->options
                    );
                }
                return $shipment_rates_result;
            } else {
                return array();
            }
        } else {
            /*For AusPost*/
            if (!empty($response_array->errors)) {
                $shipment_rates_result['error_message'] = $response_array->errors[0]->message;
                return $shipment_rates_result;
            }
            if (!empty($response_array)) {
                if (isset($response_array->shipments)) {
                    $shipment_rates_result['shipment_summary'] = array(
                        "prices"    => $response_array->shipments[0]->shipment_summary,
                        "items"     => $response_array->shipments[0]->items,
                        "options"   => $response_array->shipments[0]->options
                    );
                }
                return $shipment_rates_result;
            } else {
                return array();
            }
        }
    }
    private function estimated_delivery_date($shipping_services_product_ids, $package_destination, $vendor_from_address = false, $vendor_user_id = false)
    {
        if ($vendor_from_address) {
            $from = $vendor_from_address;
        } else {
            $from = array(
                "suburb" => $this->settings['origin_suburb'],
                "state" => $this->settings['origin_state'],
                "postcode" => $this->settings['origin']
            );
        }


        $to = array(
            "suburb" => $package_destination['city'],
            "state" => $package_destination['state'],
            "postcode" => $package_destination['postcode'],
            "country" => $package_destination['country']
        );
        $startrack_product_ids = array();
        $australia_post_products_id = array();
        foreach ($shipping_services_product_ids as $shipping_services_product_id) {
            if (in_array($shipping_services_product_id, array_keys($this->general_settings['startrack_services']))) {
                array_push($startrack_product_ids, $shipping_services_product_id);
            } else {
                array_push($australia_post_products_id, $shipping_services_product_id);
            }
        }
        $request = new stdClass();
        $request->from = $from;
        $request->to = $to;
        $account_number = '';
        $account_password = '';
        $despatch_date_difference = $this->despatch_date_difference();
        $request->despatch_date = date("Y-m-d", strtotime("+" . $despatch_date_difference . " day"));
        $shipment_estimated_delivery_date_result = array();
        if ($vendor_user_id && $this->vedor_api_key_enable) {
            $api_key = get_the_author_meta('vendor_elex_australia_post_api_key', $vendor_user_id);
            $account_number = get_the_author_meta('vendor_elex_australia_post_account_number', $vendor_user_id);
            $account_password = get_the_author_meta('vendor_elex_australia_post_api_password', $vendor_user_id);
        } else {
            $account_number = isset($this->api_account_no_auspost) ? $this->api_account_no_auspost : '';
            $account_password =  isset($this->api_pwd_auspost) ? $this->api_pwd_auspost : '';
            $api_key = false;
        }
        if (!empty($account_number) && !empty($account_password) && !empty($australia_post_products_id)) {

            $request->product_ids =  $australia_post_products_id;
            $headers = $this->buildHttpHeaders('', $account_number, $account_password, $api_key);
            $this->debug('AusPost Estimated Delivery Date REQUEST : <pre>' . print_r($request, true) . '</pre>');
            $res = $this->aus_post_estimated_delivery_request($headers, $request);
            if (is_wp_error($res)) {
                $error_string = $res->get_error_message();
                $this->debug($error_string, 'error');
            } else {
                $response_array = isset($res['body']) ? json_decode($res['body']) : array();
                if (isset($response_array->errors)) {
                    $this->debug('AusPost Estimated Delivery Date RESPONSE : <pre style="color:red;">' . print_r($response_array->errors[0]->message, true) . '</pre>');
                }
                if (isset($response_array->estimated_delivery_dates)) {
                    $this->debug('AusPost Estimated Delivery Date RESPONSE: <pre>' . print_r($response_array, true) . '</pre>');
                    foreach ($response_array->estimated_delivery_dates as $item) {
                        $shipment_estimated_delivery_date_result[$item->product_id] = $item;
                    }
                }
            }
        }
        if ($vendor_user_id && $this->vedor_api_key_enable) {
            $api_key = get_the_author_meta('vendor_elex_australia_post_api_key', $vendor_user_id);
            $account_number = get_the_author_meta('vendor_elex_australia_post_startrack_account_number', $vendor_user_id);
            $account_password = get_the_author_meta('vendor_elex_australia_post_startrack_api_password', $vendor_user_id);
        } else {
            $account_number = isset($this->api_account_no_starTrack) ? $this->api_account_no_starTrack : '';
            $account_password =  isset($this->api_pwd_starTrack) ? $this->api_pwd_starTrack : '';
            $api_key = $this->api_key_starTrack;
        }
        if (!empty($account_number) && !empty($this->$account_password) && !empty($startrack_product_ids)) {
            $request->product_ids =  $startrack_product_ids;
            $headers = $this->buildHttpHeaders('', $account_number, $account_password, $api_key);
            $this->debug('StarTrack (AusPost) Estimated Delivery Date REQUEST : <pre>' . print_r($request, true) . '</pre>');
            $res = $this->aus_post_estimated_delivery_request($headers, $request);
            if (is_wp_error($res)) {
                $error_string = $res->get_error_message();
                $this->debug($error_string, 'error');
            } else {
                $response_array = isset($res['body']) ? json_decode($res['body']) : array();
                if (isset($response_array->errors)) {

                    $this->debug('StarTrack (AusPost) Estimated Delivery Date RESPONSE : <pre style="color:red;">' . print_r($response_array->errors[0]->message, true) . '</pre>');
                }
                if (isset($response_array->estimated_delivery_dates)) {
                    $this->debug('AusPost Estimated Delivery Date RESPONSE: <pre>' . print_r($response_array, true) . '</pre>');
                    foreach ($response_array->estimated_delivery_dates as $item) {
                        $shipment_estimated_delivery_date_result[$item->product_id] = $item;
                    }
                }
            }
        }
        if (!empty($shipment_estimated_delivery_date_result)) {
            return $shipment_estimated_delivery_date_result;
        }
        return false;
    }
    private function aus_post_estimated_delivery_request($headers, $request)
    {
        $endpoint = 'https://' . self::API_HOST . self::API_BASE_URL . 'estimated_time_arrival';
        if ($this->settings['contracted_api_mode'] == 'live') {
            $endpoint = str_replace('test/', '', $endpoint);
        }
        $args = array(
            'method' => 'POST',
            'httpversion' => '1.1',
            'headers' => $headers,
            'body' => json_encode($request)
        );
        return (wp_remote_post($endpoint, $args));
    }
    private function despatch_date_difference()
    {
        $despatch_date_difference = 0;
        $despatch_date = date("Y-m-d", current_time('timestamp'));
        $cut_off_time = $this->get_option('aus_post_cut_off_time');
        $working_day = $this->get_option('aus_post_working_days');
        $estimated_delivery_lead_time = $this->get_option('aus_post_estimated_delivery_lead_time');
        if ($estimated_delivery_lead_time && $estimated_delivery_lead_time >= 1) {
            $despatch_date_difference +=  $estimated_delivery_lead_time;
            $despatch_date = date("Y-m-d", strtotime("+" . $estimated_delivery_lead_time . " day"));
        }
        if (!empty($working_day) &&  !(isset($working_day[6]))) {
            if ($cut_off_time != NULL && $cut_off_time <= date("H:i", current_time('timestamp'))) {
                $despatch_date = date("Y-m-d", strtotime($despatch_date . ' +1 day'));
                $despatch_date_difference++;
            }
            while (!in_array(date('D', strtotime($despatch_date)), $working_day)) {
                $despatch_date = date("Y-m-d", strtotime($despatch_date . "+1 day"));
                $despatch_date_difference++;
            }
        } elseif ($cut_off_time != NULL && $cut_off_time <= date("H:i", current_time('timestamp'))) {
            $despatch_date_difference++;
        }
        return ($despatch_date_difference);
    }
    public function get_estimated_duties_and_taxes_for_the_shipment($request_body = false){
        $estimated_duties_and_taxes = 0;
        if( $request_body ){

            $endpoint = 'https://' . self::API_HOST . self::API_BASE_URL . 'export-tools';
            $endpoint = str_replace('test/', '', $endpoint);

            $account_number = isset($this->api_account_no_auspost) ? $this->api_account_no_auspost : '';
            $account_password =  isset($this->api_pwd_auspost) ? $this->api_pwd_auspost : '';
            $api_key = isset($this->api_key) ? $this->api_key : '';

            $headers = $this->buildHttpHeaders('', $account_number, $account_password, $api_key);             
            $args = array(
                'method' => 'POST',
                'httpversion' => '1.1',
                'headers' => $headers,
                'body' => json_encode($request_body)
            );
            $this->debug('Combined Export Tool REQUEST : <pre>' . print_r($request_body, true) . '</pre>');
            
            $response = wp_remote_post($endpoint, $args);

            if (is_wp_error($response)) {
                $error_string = $response->get_error_message();
                $this->debug($error_string, 'error');
            } else {
                $response_array = isset($response['body']) ? json_decode($response['body']) : array();
                
                if (isset($response_array->errors)) {
                    $this->debug('Combined Export Tool RESPONSE : <pre style="color:red;">' . print_r($response_array->errors[0]->message, true) . '</pre>');
                }else {
                    $this->debug('Combined Export Tool RESPONSE: <pre>' . print_r($response_array, true) . '</pre>');
                    if (isset($response_array->result) && isset($response_array->result->landed_cost)){
                        $total_duty = 0;
                        $total_tax = 0;
                        $landed_cost = $response_array->result->landed_cost;
                        if(isset($landed_cost->total_duty) && isset($landed_cost->total_duty->source_value) && $landed_cost->total_duty->source_value > 0 ){
                            $total_duty = $landed_cost->total_duty->source_value;
                        }
                        if(isset($landed_cost->total_tax) && isset($landed_cost->total_tax->source_value) && $landed_cost->total_tax->source_value > 0 ){
                            $total_tax  = $landed_cost->total_tax->source_value;
                        } 
                        $estimated_duties_and_taxes = $total_duty + $total_tax;
                    }
                }

            }
        }
        return $estimated_duties_and_taxes;
    }
    public function estimated_duties_and_taxes_for_the_shipment_request_body($products, $destination){
        $items = array();       
        foreach($products as $key=> $product){
            
            if(is_array($product) && isset($product['data'])){
                $product_data = $product['data'];
            }else{
                $product_data = $product;
            }
            $product_weight = $product_data->get_weight();          
            $price = $product_data->get_price();

            if ($product_data->get_type() == 'variation') {
                $product_parent_id = $product_data->get_parent_id();
                $commodity_uuid = get_post_meta( $product_parent_id,'_elex_aus_post_commodity_uuid', true);
                $originating_country = get_post_meta( $product_parent_id , '_wf_country_of_origin', true);
            }else{
                $product_id = $product_data->get_id();
                $commodity_uuid = get_post_meta( $product_id,'_elex_aus_post_commodity_uuid', true);
                $originating_country = get_post_meta($product_id , '_wf_country_of_origin', true);
            }
            
            $item = new stdClass();
            $item->commodity_uuid = $commodity_uuid;
            $item->originating_country = strtoupper($originating_country);
            $item->unit_value = round($price, 2);
            $item->quantity = 1 ;
            $item->weight_or_volume = round(wc_get_weight( $product_weight , 'g' )); 

            $items[] = $item;
        }
        $request_body = new stdClass();
        $request_body->destination_country = strtoupper($destination['country']);
        $request_body->shipping_value= 0 ;
        $request_body->postcode = $destination['postcode'];
        $request_body->products=  $items ;
        return $request_body;
    }
}
new wf_australia_post_shipping();
