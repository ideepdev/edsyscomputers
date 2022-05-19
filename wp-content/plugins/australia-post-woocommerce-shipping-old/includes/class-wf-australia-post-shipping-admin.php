<?php

/*Making compatible with PHP 7.1 later versions*/
if (version_compare(phpversion(), '7.1', '>=')) {
   ini_set('serialize_precision', -1); // Avoiding adding of unnecessary 17 decimal places resulted from json_encode
}

class wf_australia_post_shipping_admin
{

    const API_HOST = 'digitalapi.auspost.com.au';
    const API_BASE_URL = '/test/shipping/v1/';
    const API_GET_ACCOUNTS = '/shipping/v1/accounts/'; // endpoint to get account details of a contracted account
    const API_CREATE_LABEL = '/shipping/v1/labels';

    private $extra_cover_cost = array('domestic' => 1.5, 'international' => 3.5); // Extra cover costs
    private $european_union_countries = array("AT", "BE", "BG", "CY", "CZ", "DE", "DK", "EE", "ES", "FI", "FR", "GR", "HU", "HR", "IE", "IT", "LT", "LU", "LV", "MT", "NL", "PL", "PT", "RO", "SE", "SI", "SK");
    /** Services called from 'services' API without options */
    private $services = array();

    public function __construct()
    {
        if (!class_exists('WF_auspost_non_contracted_services')) {
            include_once('settings/class_wf_auspost_non_contracted_services.php');
        }

        add_action('wp_ajax_elex_auspost_add_products_extra_packages', array($this, 'elex_auspost_add_products_extra_packages'));
        add_action('wp_ajax_elex_auspost_remove_packages', array($this, 'elex_auspost_remove_packages'));
        add_action('wp_ajax_elex_auspost_get_services', array($this, 'elex_auspost_get_services'));

        $auspost_services_obj = new WF_auspost_non_contracted_services();
        /** Services called from 'services' API without options */
        $this->services = $auspost_services_obj->get_services(); // these services are defined statically
        $this->settings = get_option('woocommerce_wf_australia_post_settings');
        $this->settings_services = $this->settings['services'];
        $this->weight_dimensions_manual = 'no';
        $this->custom_services = isset($this->settings['services']) ? $this->settings['services'] : array();

        $this->username = '<lps_merchant_dev>';
        $this->password = '<LabelDev123$>';

        $this->debug    = (isset($this->settings['debug_mode']) && ($this->settings['debug_mode'] == 'yes')) ? true : false;

        $this->api_key = empty($this->settings['api_key']) ? '4f7d9c75-6bff-4d19-94cd-987cebf03e82' : $this->settings['api_key'];
        $this->api_pwd = empty($this->settings['api_pwd']) ? 'xe2b62f280f0d074428a' : $this->settings['api_pwd'];
        $this->api_pwd = str_replace('&lt;', '<', $this->api_pwd);
        $this->api_pwd = str_replace('&gt;', '>', $this->api_pwd);
        $this->api_account_no = empty($this->settings['api_account_no']) ? '1012131403' : $this->settings['api_account_no'];
        $this->rate_type = 'auspost';
        $this->startrack_enabled = false;
        $multi_vendor_add_on_active = (in_array('multi-vendor-add-on-for-thirdparty-shipping/multi-vendor-add-on-for-thirdparty-shipping.php', apply_filters('active_plugins', get_option('active_plugins'))))? TRUE:FALSE;
        $this->vendor_check = ($multi_vendor_add_on_active && (isset($this->settings['vendor_check']) && ($this->settings['vendor_check'] == 'yes'))) ? TRUE : FALSE;
        $this->vedor_api_key_enable = ($multi_vendor_add_on_active && (get_option('wc_settings_wf_vendor_addon_allow_vedor_api_key') == 'yes')) ? TRUE : FALSE;
        if ((isset($this->settings['wf_australia_post_starTrack_rates_selected']) && ($this->settings['wf_australia_post_starTrack_rates_selected'] == true))) {
            $this->api_pwd_startrack = $this->settings['wf_australia_post_starTrack_api_pwd'];
            $this->api_account_no_startrack = $this->settings['wf_australia_post_starTrack_api_account_no'];
            $this->rate_type = 'startrack';
            $this->startrack_enabled = true;
            if (isset($this->settings['wf_australia_post_starTrack_api_key_enabled']) && $this->settings['wf_australia_post_starTrack_api_key_enabled']) {
                $this->api_key_starTrack = $this->settings['wf_australia_post_starTrack_api_key'];
            } else {
                $this->api_key_starTrack = $this->api_key;
            }
        }

        $this->contracted_api_mode = isset($this->settings['contracted_api_mode']) ? $this->settings['contracted_api_mode'] : 'test';
        $this->contracted_rates = isset($this->settings['contracted_rates']) && ($this->settings['contracted_rates'] == 'yes') ? true : false;

        $this->is_woocommerce_composite_products_installed = (in_array('woocommerce-composite-products/woocommerce-composite-products.php', get_option('active_plugins'))) ? true : false;

        $this->shipper_postcode = isset($this->settings['origin']) ? $this->settings['origin'] : '';
        $this->shipper_name = isset($this->settings['origin_name']) ? $this->settings['origin_name'] : '';
        $this->shipper_state = isset($this->settings['origin_state']) ? $this->settings['origin_state'] : '';
        $this->shipper_suburb = isset($this->settings['origin_suburb']) ? $this->settings['origin_suburb'] : '';
        $this->shipper_address = isset($this->settings['origin_line']) ? $this->settings['origin_line'] : '';
        $this->shipper_phone_number = isset($this->settings['shipper_phone_number']) ? $this->settings['shipper_phone_number'] : '';
        $this->ship_content = isset($this->settings['ship_content']) ? $this->settings['ship_content'] : 'Shipment Contents';
        $this->shipper_email = isset($this->settings['shipper_email']) ? $this->settings['shipper_email'] : '';
        $this->dir_download = (isset($this->settings['dir_download']) && $this->settings['dir_download'] == 'yes') ? 'attachment' : 'inline';
        $this->email_tracking = (isset($this->settings['email_tracking']) && $this->settings['email_tracking'] == 'yes') ? true : false;
        $this->cus_type = isset($this->settings['cus_type']) ? $this->settings['cus_type'] : 'STANDARD_ADDRESS';
        $this->enable_label = (isset($this->settings['enabled_label']) && $this->settings['enabled_label'] == 'yes') ? true : false;
        $this->general_settings = get_option('woocommerce_wf_australia_post_settings');
        $this->dimension_unit = strtolower(get_option('woocommerce_dimension_unit'));
        $this->weight_unit = strtolower(get_option('woocommerce_weight_unit'));
        $this->create_shipment_error = get_option('wf_create_shipment_error');
        $this->boxpacking_error = get_option('wf_create_boxpacking_error');
        $this->create_shipment_success = get_option('wf_create_shipment_success');
        $this->weight_packing_process = !empty($this->settings['weight_packing_process']) ? $this->settings['weight_packing_process'] : 'pack_descending'; // This feature will be implementing in next version
        $this->order_package_categories_arr = array(); // contains types of categories of packages in an order
        $this->order_desc_for_other_category_arr = array(); // contains array of descriptions per package for international shipments
        $this->branded = !empty($this->settings['shipment_label_type']) ? true : false;
        $this->is_request_bulk_shipment = get_option('create_bulk_orders_shipment_auspost', false);
        $this->is_request_bulk_startrack_shipment = get_option('create_bulk_orders_shipment_auspost_startrack', false);

        //For storing the shipping service, weight and dimensions overridden by the user in the metabox table
        $this->weights_in_request_array = array();
        $this->lengths_in_request_array = array();
        $this->widths_in_request_array = array();
        $this->heights_in_request_array = array();
        $this->shipment_services_in_request_array = array();
        $this->order_shipping_service = '';
        $this->shipment_id = '';
        $this->order_id = '';
        $this->default_service = '';
        $this->packing_method = $this->settings['packing_method'];
        $this->group_shipping = isset($this->settings['group_shipping']) ? $this->settings['group_shipping'] : false;
        if ($this->group_shipping && $this->group_shipping == 'yes') {
            $this->group_shipping_enabled = true;
        }else{
            $this->group_shipping_enabled = false;
        }
        global $wpdb;
        $query = "SELECT ID FROM `" . $wpdb->prefix . "posts` WHERE post_type = 'shop_order' ORDER BY `ID` DESC LIMIT 1";

        $this->new_order_id = $wpdb->get_results($query);
        $this->new_order_id = array_shift($this->new_order_id);
        $this->insurance_requested_at_checkout = false;
        $last_order_id = get_option('last_order_id');

        if (!empty($this->new_order_id) && (empty($last_order_id) || $last_order_id != $this->new_order_id)) {
            $this->elex_auspost_update_order_meta($this->new_order_id);
            delete_option('auspost_extra_cover_checkout');
        }

        if (is_admin() && $this->enable_label && $this->contracted_rates) {
            add_action('add_meta_boxes', array($this, 'wf_add_australia_post_metabox'));
        }

        if (isset($_GET['elex_auspost_generate_packages'])) {
            add_action('init', array($this, 'elex_auspost_generate_packages'), 10);
        }


        if (isset($_GET['wf_australiapost_createshipment'])) {
            add_action('init', array($this, 'wf_australiapost_createshipment'), 10);
        }

        if (isset($_GET['wf_australiapost_viewlabel'])) {
            add_action('init', array($this, 'wf_australiapost_viewlabel'));
        }

        if (isset($_GET['wf_australiapost_void_shipment'])) {
            add_action('init', array($this, 'wf_australiapost_void_shipment'));
        }

        if (isset($_GET['wf_australiapost_delete_shipment'])) {
            add_action('init', array($this, 'wf_australiapost_delete_shipment'));
        }

        add_action('load-edit.php', array($this, 'wf_auspost_bulk_order_actions')); //to handle bulk actions selected in 'shop-order' page
        add_action('admin_notices', array($this, 'wf_auspost_bulk_label_admin_notices'));

        //StarTrack
        add_action('load-edit.php', array($this, 'elex_auspost_startrack_bulk_order_actions')); //to handle bulk actions selected in 'shop-order' page
        add_action('admin_notices', array($this, 'elex_auspost_startrack_bulk_label_admin_notices'));
    }

    /**
     * function to generate shipment packages
     * @access public
     * @param string woocommerce order id, boolean 
     */
    public function elex_auspost_generate_packages($current_order_id = '', $contains_failed_packages = false)
    {
        $order_id = !empty($_GET['elex_auspost_generate_packages']) ? $_GET['elex_auspost_generate_packages'] : $current_order_id;
        $order = new WC_Order($order_id);
        $shipment_packages = $this->elex_auspost_get_order_shipment_packages($order);

        if (!$contains_failed_packages) {
            wp_redirect(admin_url('/post.php?post=' . $order_id . '&action=edit'));
            exit;
        }

        return;
    }

    /**
     * function to remove packages from a woocommerce order id
     * @access public
     */
    public function elex_auspost_remove_packages()
    {
        if (!isset($_POST['packagesSelected']))
            die();

        $order_id = $_POST['orderId'];
        $order = wc_get_order($order_id);
        $order_items = $order->get_items();
        if (is_array($_POST['packagesSelected'])) {
            foreach ($_POST['packagesSelected'] as $product_id) {
                foreach ($order_items as $order_item_key => $order_item) {
                    $order_item_data = $order_item->get_data();
                    $order_item_id = ($order_item_data['variation_id'] != 0) ? $order_item_data['variation_id'] : $order_item_data['product_id'];
                    if ($order_item_id == $product_id) {
                        wc_delete_order_item($order_item_key);
                        break;
                    }
                }
            }
        }
        $this->elex_auspost_get_order_shipment_packages($order);
        update_option("removed_package_status_auspost_elex", true);
        die('done');
    }

    /**
     * function to generate shipment packages based on the packing options selected in the settings
     * @access public
     * @param woocommerce order
     * @return array shipment packages
     */
    public function elex_auspost_get_order_shipment_packages($order)
    {

        if ($this->packing_method == 'weight') {
            $shipment_packages = $this->weight_based_packing($order);
            $shipment_packages[0]['packing_method'] = 'weight';
        } elseif ($this->packing_method == 'box_packing') {
            $shipment_packages = $this->box_packing($order);
            $shipment_packages[0]['packing_method'] = 'box_packing';
        } else {
            $shipment_packages = $this->per_item_packing($order);
            $shipment_packages[0]['packing_method'] = 'per_item';
        }
        $order_id = $order->get_id();

        $from_weight_unit = '';
        if ($this->weight_unit != 'kg') {
            $from_weight_unit = $this->weight_unit;
        }

        $from_dimension_unit = '';
        if ($this->dimension_unit != 'cm') {
            $from_dimension_unit = $this->dimension_unit;
        }

        foreach ($shipment_packages as $shipment_package) {
            if ($this->weight_unit != 'kg') {
                $shipment_package['Weight']['Value'] = wc_get_weight($shipment_package['Weight']['Value'], 'kg', $from_weight_unit);
            }

            if ($this->dimension_unit != 'cm') {
                $shipment_package['Dimensions']['Length'] = wc_get_dimension($shipment_package['Dimensions']['Length'], 'cm', $from_dimension_unit);
                $shipment_package['Dimensions']['Width'] = wc_get_dimension($shipment_package['Dimensions']['Width'], 'cm', $from_dimension_unit);
                $shipment_package['Dimensions']['Height'] = wc_get_dimension($shipment_package['Dimensions']['Height'], 'cm', $from_dimension_unit);
            }
        }

        update_post_meta($order_id, 'shipment_packages_auspost_elex', $shipment_packages);
        return $shipment_packages;
    }

    /**
     * function to add extra packages to the current order
     * @access public
     */
    public function elex_auspost_add_products_extra_packages()
    {
        if (!isset($_POST['productSelected']))
            die();

        $selected_products = array();
        $selected_products_ids = $_POST['productSelected'];
        $order_id = $_POST['orderId'];
        $order = wc_get_order($order_id);
        foreach ($selected_products_ids as $selected_products_id) {
            $selected_product = wc_get_product($selected_products_id);
            $order->add_product($selected_product);
        }

        $shipment_packages = $this->elex_auspost_get_order_shipment_packages($order);
        die(json_encode($shipment_packages));
    }

    /**
     * function to add custom checkout field values as meta data for the provided order
     * @access private
     * @param woocommerce order id
     */
    private function elex_auspost_update_order_meta($new_order_id)
    {
        if (isset($this->settings['enabled']) && !empty($this->settings['enabled'])) {
            $is_extra_cover_requested = get_option('auspost_extra_cover_checkout');
            if (!empty($is_extra_cover_requested)) {
                update_post_meta($new_order_id->ID, 'extra_cover_opted_auspost_elex', $is_extra_cover_requested);
            }

            update_option('last_order_id', $new_order_id);

            return;
        }
    }

    /**
     * function display box packingerror notices in admin page
     * @access private
     * @param error statements
     */

    private function show_boxpacking_error_notice()
    {
        echo '
        <div class="notice notice-error is-dismissible">
            <p>' . $this->boxpacking_error . '</p>
        </div>
        ';
        delete_option('wf_create_boxpacking_error');
    }

    /**
     * function display success notices in admin page
     * @access private
     * @param success statements
     */

    private function show_success_notice()
    {
        echo '
        <div class="notice notice-success is-dismissible">
            <p>' . $this->create_shipment_success . '</p>
        </div>
        ';
        delete_option('wf_create_shipment_success');
    }

    public function wf_australiapost_delete_shipment()
    {
        if (!class_exists('WF_Tracking_Admin_AusPost')) {
            include('class-wf-tracking-admin.php');
        }

        $tracking_admin = new WF_Tracking_Admin_AusPost();

        $order_id = isset($_GET['wf_australiapost_delete_shipment']) ? $_GET['wf_australiapost_delete_shipment'] : '';
        $shipment_id = isset($_GET['wf_shipment_id']) ? $_GET['wf_shipment_id'] : '';

        if (!empty($order_id) && !empty($shipment_id)) {
            $user_ok = $this->wf_user_permission();
            if (!$user_ok)
                return;

            $order = $this->wf_load_order($order_id);
            if (!$order)
                return;

            $order_number = $order->get_order_number();
            $service_base_url = 'https://' . self::API_HOST . self::API_BASE_URL . 'shipments/' . $shipment_id;

            if ($this->contracted_api_mode == 'live') {
                $service_base_url = str_replace('test/', '', $service_base_url);
            }
            $api_type = 'auspost';
            $shipment_service_for_shipment_id = get_post_meta($order_id, 'elex_auspost_shipping_service_' . $shipment_id, true);
            $all_eligible_postage_products = get_option("all_auspost_postage_products_auspost_elex",true);
          
            foreach($all_eligible_postage_products as $eligible_postage_product){
                if($eligible_postage_product['product_id'] == $shipment_service_for_shipment_id ){
                    $api_type = isset($eligible_postage_product['service_type'])? $eligible_postage_product['service_type'] : $api_type;
                    break;
                }
            }
            // Compatibility of Australia Post with ELEX Multivendor Addon 
            $vendor_shipment = get_option('elex_australia_post_shipment_details') ? get_option('elex_australia_post_shipment_details') : array();
            if ($this->vendor_check && $this->vedor_api_key_enable && isset($vendor_shipment[$shipment_id]) && isset($vendor_shipment[$shipment_id]['seller_id'])) {
                $vendor_user_id = $vendor_shipment[$shipment_id]['seller_id'];
                $api_password = get_the_author_meta('vendor_elex_australia_post_api_password', $vendor_user_id);
                $api_account_number = get_the_author_meta('vendor_elex_australia_post_account_number', $vendor_user_id);
                if (isset($vendor_shipment[$shipment_id]['shipping_service_type']) && $vendor_shipment[$shipment_id]['shipping_service_type'] == 'StarTrack') {
                    $api_password = get_the_author_meta('vendor_elex_australia_post_startrack_api_password', $vendor_user_id);
                    $api_account_number = get_the_author_meta('vendor_elex_australia_post_startrack_account_number', $vendor_user_id);
                }
                $rqs_headers = array(
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(get_the_author_meta('vendor_elex_australia_post_api_key', $vendor_user_id) . ':' . $api_password),
                    'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
                    'Account-Number' => $api_account_number
                );
            } else {
                if($api_type == 'startrack'){
                    $api_password = $this->api_pwd_startrack;
                    $api_account_number = $this->api_account_no_startrack;
                    $api_key = $this->api_key_starTrack;
                }else{
                    $api_password = $this->api_pwd;
                    $api_account_number = $this->api_account_no;
                    $api_key = $this->api_key;
                }
                $rqs_headers = array(
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
                    'Account-Number' => $api_account_number,
                    'Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_password),
                );
            }

            $res = wp_remote_get($service_base_url, array(
                'headers' => $rqs_headers,
            ));
            if (is_wp_error($res)) {
                $error_string = $res->get_error_message();
                $this->debug('Australia Post Delete Label <br><pre>');
                $this->debug($error_string . '<br><pre>', 'error');
            } else {
                // Compatibility of Australia Post with ELEX Multivendor Addon 
                if ($this->vendor_check && isset($vendor_shipment[$shipment_id])) {
                    unset($vendor_shipment[$shipment_id]);
                    update_option('elex_australia_post_shipment_details', $vendor_shipment);
                }
                delete_post_meta($order_id, 'wf_woo_australiapost_labelURI');
                delete_post_meta($order_id, 'elex_auspost_label_uris');
                delete_post_meta($order_id, 'elex_auspost_label_request_ids');
                $order_shipment_ids = get_post_meta($order_id, 'wf_woo_australiapost_shipmentId', true);
                delete_post_meta($order_id, 'elex_auspost_startrack_shipment_ids');
                if (is_array($order_shipment_ids) && !empty($order_shipment_ids)) {
                    foreach ($order_shipment_ids as $order_shipment_id) {
                        delete_post_meta($order_id, 'elex_auspost_shipping_service_' . $order_shipment_id);
                        delete_post_meta($order_id, 'wf_woo_australiapost_labelURI' . $order_shipment_id);
                        delete_post_meta($order_id, 'wf_woo_australiapost_labelId' . $order_shipment_id);

                        if (file_exists(ELEX_AUSPOST_LABELS . 'Order_' . $order_number . '_' . $shipment_id . '_label.pdf'))
                            unlink(ELEX_AUSPOST_LABELS . 'Order_' . $order_number . '_' . $shipment_id . '_label.pdf');
                    }
                }
                delete_post_meta($order_id, 'wf_woo_australiapost_shipmentId');

                $manifest_history = get_option('elex_auspost_manifest_history');
                //Deleting manifest contains current order id 
                if (!empty($manifest_history)) {
                    foreach ($manifest_history as $manifests_key => $manifests) {
                        foreach ($manifests as $manifest_data_key => $manifest_data_value) {
                            if (!empty($manifest_data_value['data'])) {
                                foreach ($manifest_data_value['data'] as $orderid_in_manifest => $order) {
                                    if ($orderid_in_manifest == $order_id) {
                                        unset($manifest_history[$manifests_key]);
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }

                delete_post_meta($order_id, 'failed_create_shipment_packages_auspost_elex');
                delete_post_meta($order_id, 'consolidated_failed_create_shipment_packages_auspost_elex');

                update_option('elex_auspost_manifest_history', $manifest_history);

                delete_option('wf_create_shipment_success');

                do_action('elex_after_deleting_shipment', $order_id );

                $tracking_admin->delete_tracking_information($order_id); // calling tracking data delete function   
            }  
            wp_redirect(admin_url('/post.php?post=' . $order_id . '&action=edit'));  
            exit;
        }
    }

    public function wf_australiapost_void_shipment()
    {
        $user_ok = $this->wf_user_permission();
        if (!$user_ok)
            return;

        $void_params = explode('||', base64_decode($_GET['wf_australiapost_void_shipment']));

        if (empty($void_params) || !is_array($void_params) || count($void_params) != 2)
            return;

        $shipment_id = $void_params[0];
        $order_id = $void_params[1];

        $service_url = $this->serviceUrl . $this->mailedBy . '/' . $this->mobo . '/shipment' . '/' . $shipment_id;
        $response = wp_remote_post($service_url, array(
            'method' => 'DELETE',
            'timeout' => 70,
            'sslverify' => 0,
            'headers' => $this->wf_get_request_header('application/vnd.cpc.shipment-v7+xml', 'application/vnd.cpc.shipment-v7+xml')
        ));

        $void_error_message = "";
        $void_success = false;
        if (!empty($response['response']['code']) && $response['response']['code'] == "204") {
            $void_success = true;
        } elseif (!empty($response['body'])) {
            $response = $response['body'];
        } else {
            $response = '';
        }


        if ($void_success == false) {
            $void_error_message = "void shipment failed.";
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string('<root>' . preg_replace('/<\?xml.*\?>/', '', $response) . '</root>');
            if (!$xml) {
                $void_error_message .= 'Failed loading XML;';
                $void_error_message .= $response . ";";
                foreach (libxml_get_errors() as $error) {
                    $void_error_message .= $error->message;
                }
            } else {
                if ($xml->{'messages'}) {
                    $messages = $xml->{'messages'}->children('http://www.australiapost.ca/ws/messages');
                    if (is_array($messages) && !empty($messages)) {
                        foreach ($messages as $message) {
                            $void_error_message .= 'Error Code: ' . $message->code . "\n";
                            $void_error_message .= 'Error Msg: ' . $message->description . "\n\n";
                        }
                    }
                }
            }
        } elseif ($void_success == true) {
            add_post_meta($order_id, 'wf_woo_australiapost_shipment_void', $shipment_id, false);
        }

        update_post_meta($order_id, 'wf_woo_australiapost_shipment_void_errormessage', $void_error_message);

        wp_redirect(admin_url('/post.php?post=' . $order_id . '&action=edit'));
        exit;
    }

    public function wf_load_order($orderId)
    {
        if (!class_exists('WC_Order')) {
            return false;
        }
        if (!class_exists('wf_order')) {
            include_once('class-wf-legacy.php');
        }
        return (WC()->version < '2.7.0') ? new WC_Order($orderId) : new wf_order($orderId);
    }

    private function wf_user_permission()
    {
        // Check if user has rights to generate invoices
        $current_user = wp_get_current_user();
        $user_ok = false;
		
        if ($current_user instanceof WP_User) {
            if (in_array('stores', $current_user->roles) || in_array('steve_wells', $current_user->roles) || in_array('simon_grownow', $current_user->roles) || in_array('administrator', $current_user->roles) || in_array('shop_manager', $current_user->roles) || in_array('super-user', $current_user->roles)) {
                $user_ok = true;
            }
        }
        return $user_ok;
    }


    /*function to retrieve the weight and dimensions posted by the user in the metabox table*/
    private function return_package_data_from_request($request_element)
    {
        $request_element = stripcslashes($request_element);
        $request_element = str_replace(array('[', ']', '"'), '', $request_element);
        $request_element_array = explode(',', $request_element);

        return $request_element_array;
    }

    public function wf_australiapost_createshipment()
    {
        $user_ok = $this->wf_user_permission();
        if (!$user_ok)
            return;

        $order = $this->wf_load_order($_GET['wf_australiapost_createshipment']);
        if (!$order)
            return;

        $order_id = $this->wf_get_order_id($order);

        if (isset($_GET['shipping_service']) && !empty($_GET['shipping_service'])) {
            $this->order_shipping_service = $_GET['shipping_service'];
        }

        /* Obtaining the categories provided for packages for international shipments*/
        $order_package_categories = (isset($_GET['category']) && !empty($_GET['category'])) ? $_GET['category'] : '';
        if (!empty($order_package_categories)) {
            $this->order_package_categories_arr = explode(",", $order_package_categories);
        }

        $order_desc_for_other_category = (isset($_GET['description_of_other']) && !empty($_GET['description_of_other'])) ? $_GET['description_of_other'] : '';

        /* Obtaining description for the category OTHER for international shipments */
        if (!empty($order_desc_for_other_category)) {
            $this->order_desc_for_other_category_arr = explode(",", $order_desc_for_other_category);
        } else {
            $this->order_desc_for_other_category_arr = array('Sale');
        }

        /* Obtaining the option from the user to print or not to print AusPost logo on the Shipment labels */
        if (isset($_GET['auspost_logo'])) {
            if ($_GET['auspost_logo'] == 'yes') {
                $this->branded = true;
            } else {
                $this->branded = false;
            }
        }

        $this->wf_create_shipment($order);
        if (!$this->is_request_bulk_shipment) {
            if ($this->debug) {
                $tracking_message_key = get_post_meta($order_id, 'tracking_message_key', true);
                $tracking_message_val = get_post_meta($order_id, 'tracking_message_value', true);
                if (is_array($tracking_message_key) && !empty($tracking_message_key[0])) {
                    echo '<a href="' . admin_url('/post.php?post=' . $_GET['wf_australiapost_createshipment'] . '&action=edit&' . $tracking_message_key[0] . '=' . $tracking_message_val[0]) . '">' . __('Back to Order', 'wf-shipping-auspost') . '</a>';
                }
                echo '<a href="' . admin_url('/post.php?post=' . $_GET['wf_australiapost_createshipment'] . '&action=edit') . '">' . __('Back to Order', 'wf-shipping-auspost') . '</a>';
                //For the debug information to display in the page
                die();
            } else {
                wp_redirect(admin_url('/post.php?post=' . $_GET['wf_australiapost_createshipment'] . '&action=edit'));
                exit;
            }
        }
        return;
    }

    public function wf_australiapost_viewlabel()
    {

        $shipment_id = isset($_GET['shipment_id']) ? $_GET['shipment_id'] : '';
        $order_number = isset($_GET['order_number']) ? $_GET['order_number'] : '';
        $file = ELEX_AUSPOST_LABELS . 'Order_' . $order_number . '_' . $shipment_id . '_label.pdf';
        if (file_exists($file)) {
            $filename = 'Australia-Post-' . $order_number . '-' . $_GET['shipment_id'] . '.pdf';
            header('Content-Transfer-Encoding: binary');  // For Gecko browsers mainly
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($file)) . ' GMT');
            header('Accept-Ranges: bytes');  // For download resume
            header('Content-Length: ' . filesize($file));  // File size
            header('Content-Encoding: none');
            header('Content-Type: application/pdf');  // Change this mime type if the file is not PDF
            header('Content-Disposition: attachment; filename=' . $filename);  // Make the browser display the Save As dialog
            readfile($file);
        } else {
            $service_url =  get_post_meta($_GET['wf_australiapost_viewlabel'], 'wf_woo_australiapost_labelURI' . $_GET['shipment_id'], true);
            $service_url = wp_remote_get($service_url);
            if (is_wp_error($service_url)) {
                $error_string = $service_url->get_error_message();
                $this->debug('Australia Post Download Label <br><pre>');
                $this->debug($error_string . '<br><pre>', 'error');
            } else {
                $upload_dir = wp_upload_dir();
                $base = $upload_dir['basedir'];
                $path = $base . "/elex-auspost-download-labels/";
                wp_mkdir_p($path);
                if ($_GET['order_number']) {
                    $order_number = $_GET['order_number'];
                    $file = 'Australia-Post-' . $order_number . '-' . $_GET['shipment_id'] . '.pdf';
                } else {
                    $file = 'Australia-Post-' . $_GET['shipment_id'] . '.pdf';
                }
                $file_path = $path . $file;
                file_put_contents($file_path, $service_url['body']);
                $path = $file_path;
                $filename = $file;
                header('Content-Transfer-Encoding: binary');  // For Gecko browsers mainly
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT');
                header('Accept-Ranges: bytes');  // For download resume
                header('Content-Length: ' . filesize($path));  // File size
                header('Content-Encoding: none');
                header('Content-Type: application/pdf');  // Change this mime type if the file is not PDF
                header('Content-Disposition: attachment; filename=' . $filename);  // Make the browser display the Save As dialog
                readfile($path);
                unlink($file_path);
            }
        }
        exit;
    }

    public function get_content($URL)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $URL);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    private function wf_is_service_valid_for_country($order, $service_code)
    {
        $service_valid = false;
        if ($order->get_shipping_country() == 'AU') {
            return strpos($service_code, 'AUS_') !== false;
        } else {
            return strpos($service_code, 'INTL_') !== false;
        }
        return $service_valid;
    }

    private function wf_get_shipping_service($order, $retrive_from_order = false, $bulk = '')
    {
        if ($retrive_from_order == true) {
            $service_code = get_post_meta($this->wf_get_order_id($order), 'wf_woo_australiapost_service_code', true);
            if (!empty($service_code))
                return $service_code;
        }
        if ($bulk) {
            $shipping_methods = $order->get_shipping_methods();
            $shipping_method = array_shift($shipping_methods);
            return $shipping_method['name'];
        }
        if (!empty($_GET['shipping_service'])) {
            return $_GET['shipping_service'];
        }

        //TODO: Take the first shipping method. It does not work if you have item wise shipping method
        $shipping_methods = $order->get_shipping_methods();
        if (!$shipping_methods) {
            return '';
        }

        $shipping_method = array_shift($shipping_methods);
        if (strpos($shipping_method['method_id'], WF_AUSTRALIA_POST_ID) > 0) {
            return str_replace(WF_AUSTRALIA_POST_ID . ':', '', $shipping_method['method_id']);
        } else {
            return $shipping_method['name'];
        }
    }

    private function wf_load_product($product)
    {
        if (!$product) {
            return false;
        }
        return (WC()->version < '2.7.0') ? $product : new wf_product($product);
    }

    /**
     * function to get highest dimension among all the packed products in weight based packing
     * @access public
     */

    public function return_highest($dimension_array)
    {
        $dimension = 0;
        $dimension = round(max($dimension_array), 2);
        return $dimension;
    }

    /**
     * weight_based_packing function.
     *
     * @access private
     * @param mixed $order
     * @return $to_ship (packages ready to ship)
     */
    private function weight_based_packing($order)
    {
        global $woocommerce;
        if (!class_exists('WeightPack')) {
            include_once 'class-wf-weight-packing.php';
        }
        $order_id = $order->get_id();

        $is_request_create_shipment = get_option('request_to_create_shipment');

        $failed_shipment_order_packages = get_post_meta($order->get_id(), 'consolidated_failed_create_shipment_packages_auspost_elex', true);

        if ($is_request_create_shipment) {
            delete_post_meta($order_id, 'failed_create_shipment_packages_auspost_elex');
        }


        $package_total_weight = 0;
        $insured_value = 0;
        $insurance_array = array(
            'Amount' => 0,
            'Currency' => get_woocommerce_currency()
        );
        $to_ship  = array();
        $n = 0;

        $ctr = 0;

        $orderItems = $order->get_items();
        $orderItems = apply_filters( 'elex_order_items', $orderItems );
        if (empty($orderItems)) {
            return;
        }
        if ($this->is_woocommerce_composite_products_installed) {
            $orderItems = $this->get_composite_product_items($orderItems);
        }
        $from_weight_unit = '';
        if ($this->weight_unit != 'kg') {
            $from_weight_unit = $this->weight_unit;
        }

        $from_dimension_unit = '';
        if ($this->dimension_unit != 'cm') {
            $from_dimension_unit = $this->dimension_unit;
        }

        $dangerous_goods_data = false;
        // Compatibility of Australia Post with ELEX Multivendor Addon Code
        if ($this->vendor_check) {
            $order_items_data = apply_filters('elex_vendor_custom_split_shipping_packages', $orderItems);
            $weight_pack_count = 0;
            $parcel_count = 0;
            foreach ($order_items_data as $order_item_data) {
                
                $seller_id = $order_item_data['seller_id'];
                $postage_products_data = $this->get_postage_product_data($seller_id);
                $all_eligible_postage_products = array_merge($postage_products_data['auspost_eligible_postage_products'], $postage_products_data['startrack_eligible_postage_products']);
                update_option("all_auspost_postage_products_auspost_elex", $all_eligible_postage_products);

                $postage_products_type_and_product_ids = $this->get_postage_products_type_and_product_ids($all_eligible_postage_products);
                $serviceName = $this->wf_get_shipping_service($order, false);
                $service_method_id = $this->get_selected_shipping_service_id($postage_products_type_and_product_ids, $serviceName, $order);
                $weight_pack = new WeightPack($this->weight_packing_process);
                $weight_pack->set_max_weight($this->general_settings['max_weight']);
                foreach ($order_item_data['contents'] as $orderItem) {
                    $data = $orderItem->get_data();
                    $ctr++;

                    $product_id = isset($data['variation_id']) && ($data['variation_id'] != 0) ? $data['variation_id'] : $data['product_id'];
                    $product = wc_get_product($product_id);

                    if ($refd_qty = $this->is_refunded_item($order, $data['product_id'])) {
                        if ($data['quantity'] - $refd_qty <= 0) {
                            continue;
                        } else {
                            $data['quantity'] = $data['quantity'] - $refd_qty;
                        }
                    }

                    $product_data = array();
                    $product = wc_get_product($data['variation_id'] ? $data['variation_id'] : $data['product_id']);
                    if ($data['variation_id']) {
                        $product_parent_data = $product->get_parent_data();
                        $product_variation_data = $product->get_data();

                        $product_data['weight'] = !empty($product_variation_data['weight']) ? $product_variation_data['weight'] : $product_parent_data['weight'];
                        $product_data['length'] = !empty($product_variation_data['length']) ? $product_variation_data['length'] : $product_parent_data['length'];
                        $product_data['width'] =  !empty($product_variation_data['width']) ?  $product_variation_data['width']  : $product_parent_data['width'];
                        $product_data['height'] = !empty($product_variation_data['height']) ? $product_variation_data['height'] : $product_parent_data['height'];

                        if (!isset($product_data['price']) && empty($product_data['price'])) {
                            $temp_product_data = $product->get_data();
                            $product_data['price'] = $temp_product_data['price'];
                        }
                    } else if ($data['product_id']) {
                        $product_data = $product->get_data();
                    }

                    if (empty($product_data['weight']) && empty($product_data['length']) && empty($product_data['width']) && empty($product_data['height'])) {
                        update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', 'Products does not contain weights and/or dimensions');
                    }
                    $temp_data = $data;
                    $temp_data['quantity'] = 1;
                    $weight_pack->add_item($product_data['weight'], $temp_data, $data['quantity']);
                   
                }
                $pack   =   $weight_pack->pack_items();
                $errors =   $pack->get_errors();
                if (!empty($errors)) {
                    //do nothing
                    return;
                } else {
                    $boxes          =   $pack->get_packed_boxes();
                    $unpacked_items =   $pack->get_unpacked_items();
                    $parcels      =   array_merge($boxes, $unpacked_items); // merge items if unpacked are allowed
                    $packed_parcel_count  =   count($parcels);
                    // get all items to pass if item info in box is not distinguished
                    $packable_items =   $weight_pack->get_packable_items();
                    $all_items    =   array();
                    if (is_array($packable_items) && !empty($packable_items)) {
                        foreach ($packable_items as $packable_item) {
                            $all_items[]    =   $packable_item['data'];
                        }
                    }
                    //pre($packable_items);
                    $order_total = '';
                    if (isset($this->order)) {
                        $order_total = $order->get_total();
                    }

                    if (empty($parcels)) {
                        return;
                    }
                    if (is_array($parcels) && !empty($parcels)) {
                        foreach ($parcels as $parcel => $data) {
                            $packed_products = array();
                            $insurance_array = array(
                                'Amount' => 0,
                                'Currency' => get_woocommerce_currency()
                            );
                            if (empty($failed_shipment_order_packages) || (!empty($failed_shipment_order_packages) && in_array($parcel_count, $failed_shipment_order_packages))) {
                                if (($packed_parcel_count  ==  1) && isset($data['cost'])) {
                                    $insured_value  =   $data['cost'];
                                } else {
                                    if (!empty($parcel['items'])) {
                                        foreach ($parcel['items'] as $item) {
                                            $insured_value  =   (int)$insured_value + (int)$item['total'];
                                        }
                                    } else {

                                        if (isset($order_total) && $packed_parcel_count) {
                                            $insured_value  =   $data['cost'];
                                        }
                                    }
                                }
                                $package_items = array();
                                $packed_products  =   isset($data['items']) ? $data['items'] : $all_items;
                                $weight_packed_products = array();

                                foreach ($packed_products as $i => $packed_product) {
                                    if (isset($packed_products[$i]['variation_id']) && $packed_products[$i]['variation_id'] != 0) {
                                        $weight_packed_products[$i]['product_id'] =  $packed_products[$i]['variation_id'];
                                    } else {
                                        $weight_packed_products[$i]['product_id'] = $packed_products[$i]['product_id'];
                                    }
                                    $weight_packed_products[$i]['quantity'] =  $packed_products[$i]['quantity'];
                                    $weight_packed_products[$i]['name'] =  (isset($packed_products[$i]['name']) && !empty($packed_products[$i]['name'])) ? $packed_products[$i]['name'] : '';
                                }

                                $result_array = $this->multi_dimensional_array_unique($weight_packed_products, 'product_id');
                                $product_age_check_selected = '';
                                if (is_array($result_array) && !empty($result_array)) {
                                    foreach ($result_array as $result_array_element) {
                                        $product_id = $result_array_element['product_id'];
                                        $product_parent_id = '';
                                        $product_details = wc_get_product($product_id);
                                        if (empty($product_age_check_selected)) {
                                            $product_age_check_selected = get_post_meta($product_id, 'age_check_auspost_elex', true);
                                        }

                                        $product_weight = get_post_meta($result_array_element['product_id'], '_weight', true);
                                        $product_value = $product_details->get_price();
                                        $product_details_info = $product_details->get_data();
                                        $product_parent_id = $product_details_info['parent_id'];
                                        if ($product_weight == 0 || empty($product_weight)) {
                                            if (!empty($product_parent_id)) {
                                                $product_weight = get_post_meta($product_parent_id, '_weight', true);
                                            }
                                        }

                                        $search_product_id = get_post_meta($product_id, '_wf_shipping_description', true);
                                        $package_item_description = (!empty($search_product_id) && $search_product_id != 'NA') ? $search_product_id : $result_array_element['name'];


                                        $package_items[] = array(
                                            'description'               => (strlen($package_item_description) > 40) ? substr( $this->string_clean( $package_item_description ), 0, 37) . '...' : $package_item_description,
                                            'quantity'                  => $result_array_element['quantity'],
                                            'value'                     => $product_value,
                                            'tariff_code'               => ($product_parent_id != 0) ? get_post_meta($product_parent_id, '_wf_tariff_code', 1) : get_post_meta($result_array_element['product_id'], '_wf_tariff_code', 1),
                                            'country_of_origin'         => ($product_parent_id != 0) ? get_post_meta($product_parent_id, '_wf_country_of_origin', 1) : get_post_meta($result_array_element['product_id'], '_wf_country_of_origin', 1),
                                            'weight'                    => ($this->weight_unit != 'kg') ? round(wc_get_weight($product_weight, 'kg', $this->weight_unit), 2) : round($product_weight, 2),
                                            'export_declaration_number' => ($product_parent_id != 0) ? get_post_meta($product_parent_id, '_wf_export_declaration_number', 1) : get_post_meta($result_array_element['product_id'], '_wf_export_declaration_number', 1)
                                        );
                                    }
                                }

                                // Creating parcel request
                                $parcel_total_weight   = $parcel['weight'];

                                $packed_product_length  = array();
                                $packed_product_width   = array();
                                $packed_product_height  = array();
                                $dangerous_goods_data   = array();
                                $package_dangerous_goods_data = array();
                                if (!empty($packed_products) && is_array($packed_products)) {
                                    $cubic_volume = 0;
                                    foreach ($packed_products as $packed_product) {
                                        $product = wc_get_product($packed_product['variation_id'] ? $packed_product['variation_id'] : $packed_product['product_id']);
                                        if (!empty($this->shipment_services_in_request_array)) {
                                            if (strpos($this->shipment_services_in_request_array[$parcel_count], 'startrack')) {
                                                $package_dangerous_goods_data = $this->validate_dangerous_goods($product, 'StarTrack');
                                            } else {
                                                $package_dangerous_goods_data = $this->validate_dangerous_goods($product, 'Express Post');
                                            }
                                        }

                                        if (!empty($package_dangerous_goods_data)) {
                                            $dangerous_goods_data[] = $package_dangerous_goods_data;
                                        }

                                        if ($packed_product['variation_id']) {

                                            $product_parent_data = $product_data = $product->get_parent_data();
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

                                            $product_dimension = array(
                                                $product_data['length'],
                                                $product_data['width'],
                                                $product_data['height']
                                            );

                                            rsort($product_dimension);

                                            $packed_product_length[] = $product_dimension[0]; // array[] faster than array_push()
                                            $packed_product_width[] = $product_dimension[1];
                                            $packed_product_height[] = $product_dimension[2];
                                        } else if ($packed_product['product_id']) {
                                            $product_data = $product->get_data();
                                            $product_dimension = array(
                                                $product_data['length'],
                                                $product_data['width'],
                                                $product_data['height']
                                            );

                                            rsort($product_dimension);

                                            $packed_product_length[] = $product_dimension[0];
                                            $packed_product_width[] = $product_dimension[1];
                                            $packed_product_height[] = $product_dimension[2];
                                        }
                                        if ($insurance_array['Amount'] != 0) {
                                            $insurance_array['Amount'] += ($product->get_price() * $packed_product['quantity'] );
                                        } else {
                                            $insurance_array['Amount'] = ($product->get_price() * $packed_product['quantity']);
                                        }
                                        $cubic_volume = $cubic_volume + ((wc_get_dimension($product_dimension[0], 'm', $this->dimension_unit)) * (wc_get_dimension($product_dimension[1], 'm', $this->dimension_unit)) * (wc_get_dimension($product_dimension[2], 'm', $this->dimension_unit)));
                                    }
                                }

                                $dimensions = array(
                                    'length' => $this->return_highest($packed_product_length),
                                    'width' => $this->return_highest($packed_product_width),
                                    'height' => $this->return_highest($packed_product_height)
                                );
                                if ($this->weight_unit != 'kg' && $this->is_request_bulk_shipment) {
                                    $from_weight_unit = $this->weight_unit;
                                    $data['weight'] = wc_get_weight($data['weight'], 'kg', $this->weight_unit);
                                }


                                $group = array(
                                    'Name' => 'Weight Pack ' . ++$weight_pack_count,
                                    'Weight' => array(
                                        'Value' => !empty($this->weights_in_request_array[$parcel_count]) ? $this->weights_in_request_array[$parcel_count] : round($data['weight'], 3),
                                        'Units' => $this->weight_unit
                                    ),
                                    'Dimensions' => array(
                                        'Length'    => !empty($this->lengths_in_request_array[$parcel_count]) ? $this->lengths_in_request_array[$parcel_count] : round($dimensions['length'], 2),
                                        'Width'     => !empty($this->widths_in_request_array[$parcel_count]) ? $this->widths_in_request_array[$parcel_count] : round($dimensions['width'], 2),
                                        'Height'    => !empty($this->heights_in_request_array[$parcel_count]) ? $this->heights_in_request_array[$parcel_count] : round($dimensions['height'], 2),
                                        'Units'     => $this->dimension_unit
                                    ),
                                    'seller_id'  => $order_item_data['seller_id'],
                                    'origin' => $order_item_data['origin'],
                                    'InsuredValue' => $insurance_array,
                                    'packed_products' => $packed_products,
                                    'Item_contents' => $package_items,
                                    'pack_type' => 'BAG',
                                    'cubic_volume' => $cubic_volume,
                                    'dangerous_goods_data' => empty($dangerous_goods_data) ? false : $dangerous_goods_data,
                                    'age_check'     => ($product_age_check_selected) ? $product_age_check_selected : false
                                );

                               

                                if ($is_request_create_shipment) {
                                    if (!empty($this->shipment_services_in_request_array)) {
                                        if (strpos($this->shipment_services_in_request_array[$parcel_count], 'startrack')) {
                                            $this->shipment_services_in_request_array[$parcel_count] = str_replace('startrack', '', $this->shipment_services_in_request_array[$parcel_count]);
                                            $group['shipping_service'] = $this->shipment_services_in_request_array[$parcel_count];
                                            $group['startrack_service_selected'] = 'yes';
                                        } else {
                                            $group['shipping_service'] = $this->shipment_services_in_request_array[$parcel_count];
                                        }
                                    } else {
                                        $group['shipping_service'] = $service_method_id;
                                        if (empty($group['shipping_service'])) {
                                            $group['shipping_service'] = $this->default_service;
                                        }
                                       
                                    }

                                    $auto_generate_label_status = get_option('auto_generate_label_on_auspost_elex', false);
                                    if ($auto_generate_label_status) {
                                        $group['shipping_service'] = $service_method_id;
                                        if (empty($group['shipping_service'])) {
                                            $group['shipping_service'] = $this->default_service;
                                        }
                                        
                                    }
                                }

                                $parcel_count++;

                                $to_ship[] = $group;
                            }
                        }
                    }
                }
            }
            return $to_ship;
        } else {
            $postage_products_data = $this->get_postage_product_data();
            $all_eligible_postage_products = array_merge($postage_products_data['auspost_eligible_postage_products'], $postage_products_data['startrack_eligible_postage_products']);
            update_option("all_auspost_postage_products_auspost_elex", $all_eligible_postage_products);

            $postage_products_type_and_product_ids = $this->get_postage_products_type_and_product_ids($all_eligible_postage_products);
            $serviceName = $this->wf_get_shipping_service($order, false);
            $service_method_id = $this->get_selected_shipping_service_id($postage_products_type_and_product_ids, $serviceName, $order);
            $weight_pack = new WeightPack($this->weight_packing_process);
            $weight_pack->set_max_weight($this->general_settings['max_weight']);
            foreach ($orderItems as $orderItem) {
                $data = $orderItem->get_data();
                $ctr++;

                $product_id = isset($data['variation_id']) && ($data['variation_id'] != 0) ? $data['variation_id'] : $data['product_id'];
                $product = wc_get_product($product_id);

                if ($refd_qty = $this->is_refunded_item($order, $data['product_id'])) {
                    if ($data['quantity'] - $refd_qty <= 0) {
                        continue;
                    } else {
                        $data['quantity'] = $data['quantity'] - $refd_qty;
                    }
                }

                $product_data = array();
                $product = wc_get_product($data['variation_id'] ? $data['variation_id'] : $data['product_id']);
                if ($data['variation_id']) {
                    $product_parent_data = $product->get_parent_data();
                    $product_variation_data = $product->get_data();

                    $product_data['weight'] = !empty($product_variation_data['weight']) ? $product_variation_data['weight'] : $product_parent_data['weight'];
                    $product_data['length'] = !empty($product_variation_data['length']) ? $product_variation_data['length'] : $product_parent_data['length'];
                    $product_data['width'] =  !empty($product_variation_data['width']) ?  $product_variation_data['width']  : $product_parent_data['width'];
                    $product_data['height'] = !empty($product_variation_data['height']) ? $product_variation_data['height'] : $product_parent_data['height'];

                    if (!isset($product_data['price']) && empty($product_data['price'])) {
                        $temp_product_data = $product->get_data();
                        $product_data['price'] = $temp_product_data['price'];
                    }
                } else if ($data['product_id']) {
                    $product_data = $product->get_data();
                }

                if (empty($product_data['weight']) && empty($product_data['length']) && empty($product_data['width']) && empty($product_data['height'])) {
                    update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', 'Products does not contain weights and/or dimensions');
                }
                $temp_data = $data;
                $temp_data['quantity'] = 1;
                $weight_pack->add_item($product_data['weight'], $temp_data, $data['quantity']);

               
            }

            $pack   =   $weight_pack->pack_items();

            $errors =   $pack->get_errors();

            if (!empty($errors)) {
                //do nothing
                return;
            } else {
                $boxes          =   $pack->get_packed_boxes();
                $unpacked_items =   $pack->get_unpacked_items();
                $parcels      =   array_merge($boxes, $unpacked_items); // merge items if unpacked are allowed
                $parcel_count  =   count($parcels);
                // get all items to pass if item info in box is not distinguished
                $packable_items =   $weight_pack->get_packable_items();
                $all_items    =   array();
                if (is_array($packable_items) && !empty($packable_items)) {
                    foreach ($packable_items as $packable_item) {
                        $all_items[]    =   $packable_item['data'];
                    }
                }
                //pre($packable_items);
                $order_total = '';
                if (isset($this->order)) {
                    $order_total = $order->get_total();
                }

                if (empty($parcels)) {
                    return;
                }

                $weight_pack_count = 0;

                $parcel_count = 0;
                if (is_array($parcels) && !empty($parcels)) {
                    foreach ($parcels as $parcel => $data) {
                        $packed_products = array();
                        $insurance_array = array(
                            'Amount' => 0,
                            'Currency' => get_woocommerce_currency()
                        );
                        if (empty($failed_shipment_order_packages) || (!empty($failed_shipment_order_packages) && in_array($parcel_count, $failed_shipment_order_packages))) {
                            if (($parcel_count  ==  1) && isset($data['cost'])) {
                                $insured_value  =   $data['cost'];
                            } else {
                                if (!empty($parcel['items'])) {
                                    foreach ($parcel['items'] as $item) {
                                        $insured_value  =   (int)$insured_value + (int)$item['total'];
                                    }
                                } else {

                                    if (isset($order_total) && $parcel_count) {
                                        $insured_value  =   $data['cost'];
                                    }
                                }
                            }
                            $package_items = array();
                            $packed_products  =   isset($data['items']) ? $data['items'] : $all_items;
                            $weight_packed_products = array();
                            
                            foreach ($packed_products as $i => $packed_product) {
                                if (isset($packed_products[$i]['variation_id']) && $packed_products[$i]['variation_id'] != 0) {
                                    $weight_packed_products[$i]['product_id'] =  $packed_products[$i]['variation_id'];
                                } else {
                                    $weight_packed_products[$i]['product_id'] = $packed_products[$i]['product_id'];
                                }
                                $weight_packed_products[$i]['quantity'] =  $packed_products[$i]['quantity'];
                                $weight_packed_products[$i]['name'] =  (isset($packed_products[$i]['name']) && !empty($packed_products[$i]['name'])) ? $packed_products[$i]['name'] : '';
                            }
                            $result_array = $this->multi_dimensional_array_unique($weight_packed_products, 'product_id');
                            $product_age_check_selected = '';
                            if (is_array($result_array) && !empty($result_array)) {
                                foreach ($result_array as $result_array_element) {
                                    $product_id = $result_array_element['product_id'];
                                    $product_parent_id = '';
                                    $product_details = wc_get_product($product_id);
                                    if (empty($product_age_check_selected)) {
                                        $product_age_check_selected = get_post_meta($product_id, 'age_check_auspost_elex', true);
                                    }

                                    $product_weight = get_post_meta($result_array_element['product_id'], '_weight', true);
                                    $product_value = $product_details->get_price();
                                    $product_details_info = $product_details->get_data();
                                    $product_parent_id = $product_details_info['parent_id'];
                                    if ($product_weight == 0 || empty($product_weight)) {
                                        if (!empty($product_parent_id)) {
                                            $product_weight = get_post_meta($product_parent_id, '_weight', true);
                                        }
                                    }

                                    $search_product_id = get_post_meta($product_id, '_wf_shipping_description', true);
                                    $package_item_description = (!empty($search_product_id) && $search_product_id != 'NA') ? $search_product_id : $result_array_element['name'];


                                    $package_items[] = array(
                                        'description'               => (strlen($package_item_description) > 40) ? substr( $this->string_clean( $package_item_description ), 0, 37) . '...' : $package_item_description,
                                        'quantity'                  => $result_array_element['quantity'],
                                        'value'                     => $product_value,
                                        'tariff_code'               => ($product_parent_id != 0) ? get_post_meta($product_parent_id, '_wf_tariff_code', 1) : get_post_meta($result_array_element['product_id'], '_wf_tariff_code', 1),
                                        'country_of_origin'         => ($product_parent_id != 0) ? get_post_meta($product_parent_id, '_wf_country_of_origin', 1) : get_post_meta($result_array_element['product_id'], '_wf_country_of_origin', 1),
                                        'weight'                    => ($this->weight_unit != 'kg') ? round(wc_get_weight($product_weight, 'kg', $this->weight_unit), 2) : round($product_weight, 2),
                                        'export_declaration_number' => ($product_parent_id != 0) ? get_post_meta($product_parent_id, '_wf_export_declaration_number', 1) : get_post_meta($result_array_element['product_id'], '_wf_export_declaration_number', 1)
                                    );
                                }
                            }

                            // Creating parcel request
                            $parcel_total_weight   = isset($parcel['weight'])? $parcel['weight']: 0;

                            $packed_product_length  = array();
                            $packed_product_width   = array();
                            $packed_product_height  = array();
                            $dangerous_goods_data   = array();
                            $package_dangerous_goods_data = array();
                            if (!empty($packed_products) && is_array($packed_products)) {
                                $cubic_volume = 0;
                                foreach ($packed_products as $packed_product) {
                                    $product = wc_get_product($packed_product['variation_id'] ? $packed_product['variation_id'] : $packed_product['product_id']);
                                    if (!empty($this->shipment_services_in_request_array)) {
                                        if (strpos($this->shipment_services_in_request_array[$parcel_count], 'startrack')) {
                                            $package_dangerous_goods_data = $this->validate_dangerous_goods($product, 'StarTrack');
                                        } else {
                                            $package_dangerous_goods_data = $this->validate_dangerous_goods($product, 'Express Post');
                                        }
                                    }

                                    if (!empty($package_dangerous_goods_data)) {
                                        $dangerous_goods_data[] = $package_dangerous_goods_data;
                                    }

                                    if ($packed_product['variation_id']) {

                                        $product_parent_data = $product_data = $product->get_parent_data();
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

                                        $product_dimension = array(
                                            $product_data['length'],
                                            $product_data['width'],
                                            $product_data['height']
                                        );

                                        rsort($product_dimension);

                                        $packed_product_length[] = $product_dimension[0]; // array[] faster than array_push()
                                        $packed_product_width[] = $product_dimension[1];
                                        $packed_product_height[] = $product_dimension[2];
                                    } else if ($packed_product['product_id']) {
                                        $product_data = $product->get_data();
                                        $product_dimension = array(
                                            $product_data['length'],
                                            $product_data['width'],
                                            $product_data['height']
                                        );

                                        rsort($product_dimension);

                                        $packed_product_length[] = $product_dimension[0];
                                        $packed_product_width[] = $product_dimension[1];
                                        $packed_product_height[] = $product_dimension[2];
                                    }
                                    if ($insurance_array['Amount'] != 0) {
                                        $insurance_array['Amount'] += ($product->get_price() * $packed_product['quantity'] );
                                    } else {
                                        $insurance_array['Amount'] = ($product->get_price() * $packed_product['quantity']);
                                    }
                                    $cubic_volume = $cubic_volume + ((wc_get_dimension($product_dimension[0], 'm', $this->dimension_unit)) * (wc_get_dimension($product_dimension[1], 'm', $this->dimension_unit)) * (wc_get_dimension($product_dimension[2], 'm', $this->dimension_unit)));
                                }
                            }

                            $dimensions = array(
                                'length' => $this->return_highest($packed_product_length),
                                'width' => $this->return_highest($packed_product_width),
                                'height' => $this->return_highest($packed_product_height)
                            );
                            if ($this->weight_unit != 'kg' && $this->is_request_bulk_shipment) {
                                $from_weight_unit = $this->weight_unit;
                                $data['weight'] = wc_get_weight($data['weight'], 'kg', $this->weight_unit);
                            }


                            $group = array(
                                'Name' => 'Weight Pack ' . ++$weight_pack_count,
                                'Weight' => array(
                                    'Value' => !empty($this->weights_in_request_array[$parcel_count]) ? $this->weights_in_request_array[$parcel_count] : round($data['weight'], 3),
                                    'Units' => $this->weight_unit
                                ),
                                'Dimensions' => array(
                                    'Length'    => !empty($this->lengths_in_request_array[$parcel_count]) ? $this->lengths_in_request_array[$parcel_count] : round($dimensions['length'], 2),
                                    'Width'     => !empty($this->widths_in_request_array[$parcel_count]) ? $this->widths_in_request_array[$parcel_count] : round($dimensions['width'], 2),
                                    'Height'    => !empty($this->heights_in_request_array[$parcel_count]) ? $this->heights_in_request_array[$parcel_count] : round($dimensions['height'], 2),
                                    'Units'     => $this->dimension_unit
                                ),
                                'InsuredValue' => $insurance_array,
                                'packed_products' => $packed_products,
                                'Item_contents' => $package_items,
                                'pack_type' => 'BAG',
                                'cubic_volume' => $cubic_volume,
                                'dangerous_goods_data' => empty($dangerous_goods_data) ? false : $dangerous_goods_data,
                                'age_check'     => ($product_age_check_selected) ? $product_age_check_selected : false
                            );
                            if ($is_request_create_shipment) {
                                if (!empty($this->shipment_services_in_request_array)) {
                                    if (strpos($this->shipment_services_in_request_array[$parcel_count], 'startrack')) {
                                        $this->shipment_services_in_request_array[$parcel_count] = str_replace('startrack', '', $this->shipment_services_in_request_array[$parcel_count]);
                                        $group['shipping_service'] = $this->shipment_services_in_request_array[$parcel_count];
                                        $group['startrack_service_selected'] = 'yes';
                                    } else {
                                        $group['shipping_service'] = $this->shipment_services_in_request_array[$parcel_count];
                                    }
                                } else {
                                    $group['shipping_service'] = $service_method_id;
                                    if (empty($group['shipping_service'])) {
                                        $group['shipping_service'] = $this->default_service;
                                    }
                                }

                                $auto_generate_label_status = get_option('auto_generate_label_on_auspost_elex', false);
                                if ($auto_generate_label_status) {
                                    $group['shipping_service'] = $service_method_id;
                                    if (empty($group['shipping_service'])) {
                                        $group['shipping_service'] = $this->default_service;
                                    }
                                }
                            }

                            $parcel_count++;

                            $to_ship[] = $group;
                        }
                    }
                }


                return $to_ship;
            }
        }
    }

    /**
     * function to get an unique multidimensional array
     * @access private
     * @param array $array base array| string $key reference to unique
     * @return array
     */
    private function multi_dimensional_array_unique($array, $key)
    {
        $keys_array = array();

        if (!empty($array)) {
            foreach ($array as $array_element) {
                $keys_array[] = $array_element[$key];
            }
        }

        $keys_array = array_unique($keys_array);
        $resultant_array = array();
        $count_resultant_array = 0;
        if (!empty($keys_array)) {
            foreach ($keys_array as $keys_array_element) {
                foreach ($array as $array_element) {
                    if ($array_element[$key] == $keys_array_element) {
                        if(isset($resultant_array[$count_resultant_array])){
                            $resultant_array[$count_resultant_array]['quantity']++;
                        }else{
                            $resultant_array[$count_resultant_array] = $array_element;
                        }
                    }
                }
                $count_resultant_array++;
            }
        }

        return $resultant_array;
    }

    

    /**
     * per_item_packing function.
     *
     * @access private
     * @param mixed $order
     * @return void
     */
    private function per_item_packing($order)
    {
        global $woocommerce;
        $is_request_create_shipment = get_option('request_to_create_shipment');
        $order_shipping_country = wf_get_order_shipping_country($order);
        $domestic = $order_shipping_country == 'AU' ? 'yes' : 'no';
        $order_id = $order->get_id();

        $failed_shipment_order_packages = get_post_meta($order_id, 'consolidated_failed_create_shipment_packages_auspost_elex', true);

        if ($is_request_create_shipment) {
            delete_post_meta($order_id, 'consolidated_failed_create_shipment_packages_auspost_elex');
        }


        $requests = array();
        $orderItems = $order->get_items();
        $orderItems = apply_filters( 'elex_order_items', $orderItems );
        
        $parcel_count = 0;
        $to_ship = array();

        if (!empty($orderItems) && is_array($orderItems)) {
            
            if ($this->is_woocommerce_composite_products_installed) {
                $orderItems = $this->get_composite_product_items($orderItems);
            }
            // Get weight of order
            $seller_id = false;
            $vendor_origin_address = false;
            foreach ($orderItems as $item_id => $item) {

                if (empty($failed_shipment_order_packages) || (!empty($failed_shipment_order_packages) && in_array($parcel_count, $failed_shipment_order_packages))) {
                    $dangerous_goods_data = false;
                    $item_data = $item->get_data();
                    // Compatibility of Australia Post with ELEX Multivendor Addon 
                    if ($this->vendor_check) {
                        $seller_id = get_post_field('post_author', $item_data['product_id']);
                        $vendor_origin_address = apply_filters('elex_vendor_formate_origin_address', $seller_id);
                        $postage_products_data = $this->get_postage_product_data($seller_id);
                        $all_eligible_postage_products = array_merge($postage_products_data['auspost_eligible_postage_products'], $postage_products_data['startrack_eligible_postage_products']);
                        update_option("all_auspost_postage_products_auspost_elex", $all_eligible_postage_products);
                        $postage_products_type_and_product_ids = $this->get_postage_products_type_and_product_ids($all_eligible_postage_products);
                        $serviceName = $this->wf_get_shipping_service($order, false);
                        $service_method_id = $this->get_selected_shipping_service_id($postage_products_type_and_product_ids, $serviceName, $order);
                    } else {
                        $postage_products_data = $this->get_postage_product_data();
                        $all_eligible_postage_products = array_merge($postage_products_data['auspost_eligible_postage_products'], $postage_products_data['startrack_eligible_postage_products']);
                        update_option("all_auspost_postage_products_auspost_elex", $all_eligible_postage_products);

                        $postage_products_type_and_product_ids = $this->get_postage_products_type_and_product_ids($all_eligible_postage_products);
                        $serviceName = $this->wf_get_shipping_service($order, false);

                        $service_method_id = $this->get_selected_shipping_service_id($postage_products_type_and_product_ids, $serviceName, $order);
                    }

                    $product_id = isset($item_data['variation_id']) && ($item_data['variation_id'] != 0) ? $item_data['variation_id'] : $item_data['product_id'];
                    $product_age_check_selected = get_post_meta($product_id, 'age_check_auspost_elex', true);
                    $product = wc_get_product($product_id);
                    $product_ordered_quantity = $item_data['quantity'];

                    $product_data = array();
                    if ($item_data['variation_id']) {
                        $product_data = $product->get_data();
                        if (empty($product_data['weight']) && empty($product_data['length']) && empty($product_data['width']) && empty($product_data['height'])) {
                            $product_data = $product->get_parent_data();
                            $product_data['product_id'] = $item_data['product_id'];
                            $product_data['variation_id'] = $item_data['variation_id'];
                        }

                        $temp_product_data = $product->get_data();
                        $product_data['product_id'] = $item_data['product_id'];
                        $product_data['variation_id'] = $item_data['variation_id'];
                    } else if ($item_data['product_id']) {
                        $product_data = $product->get_data();
                    }

                    $parcel = array();
                    if ($this->weight_unit != 'kg' && $this->is_request_bulk_shipment) {
                        $from_weight_unit = $this->weight_unit;
                        $product_data['weight'] = wc_get_weight($product_data['weight'], 'kg', $this->weight_unit);
                    } else {
                        $product_data['weight'] = !empty($this->weights_in_request_array[$parcel_count]) ?  $this->weights_in_request_array[$parcel_count] : $product_data['weight'];
                    }

                    $parcel['weight'] = !empty($this->weights_in_request_array[$parcel_count]) ?  $this->weights_in_request_array[$parcel_count] : $product_data['weight'];
                    $parcel['length'] = !empty($this->lengths_in_request_array[$parcel_count]) ?  $this->lengths_in_request_array[$parcel_count] : $product_data['length'];
                    $parcel['width'] = !empty($this->widths_in_request_array[$parcel_count]) ?  $this->widths_in_request_array[$parcel_count] : $product_data['width'];
                    $parcel['height'] = !empty($this->heights_in_request_array[$parcel_count]) ?  $this->heights_in_request_array[$parcel_count] : $product_data['height'];

                    $parcel_volume = wc_get_dimension($parcel['length'], 'm') * wc_get_dimension($parcel['width'], 'm') * wc_get_dimension($parcel['height'], 'm');

                    $dimensions = array($parcel['length'], $parcel['width'], $parcel['height']);
                    sort($dimensions);
                    $from_weight_unit = '';
                    if ($this->weight_unit != 'kg') {
                        $from_weight_unit = $this->weight_unit;
                    }

                    $from_dimension_unit = '';
                    if ($this->dimension_unit != 'cm') {
                        $from_dimension_unit = $this->dimension_unit;
                    }

                    // Min sizes - girth minimum is 16cm
                    if ($is_request_create_shipment) {
                        $girth = (round($dimensions[0]) + round($dimensions[1])) * 2;
                    } else {
                        $girth = (round(wc_get_dimension($dimensions[0], 'cm', $from_dimension_unit)) + round(wc_get_dimension($dimensions[1], 'cm', $from_dimension_unit))) * 2;
                    }
                    if ($this->is_request_bulk_shipment) {
                        $girth = (round(wc_get_dimension($dimensions[0], 'cm', $from_dimension_unit)) + round(wc_get_dimension($dimensions[1], 'cm', $from_dimension_unit))) * 2;
                    }


                    $parcel_weight = wc_get_weight($parcel['weight'], 'kg', $this->weight_unit);
                    if (!$this->startrack_enabled) {
                        if ($parcel_weight > 22 || $dimensions[2] > 105) {
                            $this->debug(sprintf(__('Product %d has invalid weight/dimensions. Aborting. See <a href="http://auspost.com.au/personal/parcel-dimensions.html">http://auspost.com.au/personal/parcel-dimensions.html</a>', 'wf-shipping-auspost'), $item_id), 'error');
                            return;
                        }
                    }

                    // Allowed maximum volume of a product is 0.25 cubic meters for domestic shipments
                    if ($domestic == 'yes' && $parcel_volume > 0.25) {
                        $this->debug(sprintf(__('Error: Product %s exceeds 0.25 cubic meters Aborting. See <a href="http://auspost.com.au/personal/parcel-dimensions.html">http://auspost.com.au/personal/parcel-dimensions.html</a>', 'wf-shipping-auspost'), $product_data['name']), 'error');
                        return;
                    }

                    // The girth should lie between 16cm and 140cm for international shipments
                    if ($domestic == 'no' && ($girth < 16 || $girth > 140)) {
                        $this->debug(sprintf(__('<b>Error</b>: Girth of the product %s should lie in between 16cm and 140cm. See <a href="http://ausporthst.com.au/personal/parcel-dimensions.html">http://auspost.com.au/personal/parcel-dimensions.html</a>', 'wf-shipping-auspost'), $product_data['name']), 'error');
                        return;
                    }

                    $insurance_array = array(
                        'Amount' => ceil($item_data['total']),
                        'Currency' => get_woocommerce_currency()
                    );

                    $product_desc = '';
                    if (!empty($product_data)) {
                        if (isset($product_data['id']) && !empty($product_data['id'])) {
                            $product_desc = get_post_meta($product_data['id'], '_wf_shipping_description', 1);
                        } else if (isset($product_data['product_id']) && !empty($product_data['product_id'])) {
                            $product_desc = get_post_meta($product_data['product_id'], '_wf_shipping_description', 1);
                        }

                        if ($item_data['variation_id']) {

                            $package_weight = get_post_meta($product_data['variation_id'], '_weight', 1);
                            $package_weight = (!empty($package_weight)) ? $package_weight : get_post_meta($product_data['product_id'], '_weight', 1);

                            $package_item_description = !empty($product_desc) ? $product_desc : (isset($product_data['name']) && !empty($product_data['name']) ? $product_data['name'] : $product_data['title']);
                            $package_item_value = get_post_meta($product_data['variation_id'], '_sale_price', 1);
                            $package_item_value = empty($package_item_value) ? get_post_meta($product_data['variation_id'], '_regular_price', 1) : $package_item_value;
                            $package_items = array(
                                'description'               => (strlen($package_item_description) > 40) ? substr( $this->string_clean( $package_item_description ), 0, 37) . '...' : $package_item_description,
                                'quantity'                  => 1,
                                'value'                     => $package_item_value,
                                'tariff_code'               => get_post_meta($product_data['product_id'], '_wf_tariff_code', 1),
                                'country_of_origin'         => get_post_meta($product_data['product_id'], '_wf_country_of_origin', 1),
                                'weight'                    => ($this->weight_unit != 'kg') ? round(wc_get_weight($package_weight, 'kg', $this->weight_unit), 2) : round($package_weight, 2),
                                'export_declaration_number' => get_post_meta($product_data['product_id'], '_wf_export_declaration_number', 1)
                            );
                        } else {
                            $package_item_description = !empty($product_desc) ? $product_desc : $product_data['name'];
                            $package_item_value = get_post_meta($product_data['id'], '_sale_price', 1);
                            $package_item_value = empty($package_item_value) ? get_post_meta($product_data['id'], '_regular_price', 1) : $package_item_value;

                            $package_items = array(
                                'description'               => (strlen($package_item_description) > 40) ? substr( $this->string_clean( $package_item_description ), 0, 37) . '...' : $package_item_description,
                                'quantity'                  => 1,
                                'value'                     => $package_item_value,
                                'tariff_code'               => get_post_meta($product_data['id'], '_wf_tariff_code', 1),
                                'country_of_origin'         => get_post_meta($product_data['id'], '_wf_country_of_origin', 1),
                                'weight'                    => ($this->weight_unit != 'kg') ? round(wc_get_weight(get_post_meta($product_data['id'], '_weight', 1), 'kg', $this->weight_unit), 2) : round(get_post_meta($product_data['id'], '_weight', 1), 2),
                                'export_declaration_number' => get_post_meta($product_data['id'], '_wf_export_declaration_number', 1)
                            );
                        }
                    }

                    $item_reference = '';
                    $product_composite_reference = get_post_meta($product_id, '_composite_title', true);
                    delete_post_meta($product->get_id(), '_composite_title');

                    if (isset($product_data['title']) && !empty($product_data['title'])) {
                        $item_reference = $product_data['title'];
                    } else if (isset($product_data['name']) && !empty($product_data['name'])) {
                        $item_reference = $product_data['name'];
                    } else if (isset($product_desc) && !empty($product_desc)) {
                        $item_reference = $product_desc;
                    }

                    $group = array(
                        'Name' => !empty($product_composite_reference) ? $product_composite_reference : $item_reference,
                        'Weight' => array(
                            'Value' => round($product_data['weight'], 2),
                            'Units' => $this->weight_unit
                        ),
                        'Dimensions'    => array(
                            'Length'    => round($dimensions[2], 2),
                            'Width'     => round($dimensions[1], 2),
                            'Height'    => round($dimensions[0], 2),
                            'Units'     => $this->dimension_unit
                        ),
                        'seller_id'       =>  $seller_id,
                        'origin'        => $vendor_origin_address,
                        'InsuredValue'      => $insurance_array,
                        'Item_contents'     => $package_items,
                        'packed_products'   => $product_data,
                        'pack_type'         => 'ITM',
                        'package_order'     => $parcel_count,
                        'age_check'         => ($product_age_check_selected) ? $product_age_check_selected : false
                    );

                   
                    if ($is_request_create_shipment) {
                        if (!empty($this->shipment_services_in_request_array)) {
                            if (isset($this->shipment_services_in_request_array[$parcel_count])) {
                                if (strpos($this->shipment_services_in_request_array[$parcel_count], 'startrack')) {
                                    $dangerous_goods_data[] = $this->validate_dangerous_goods($product, 'StarTrack');
                                    $this->shipment_services_in_request_array[$parcel_count] = str_replace('startrack', '', $this->shipment_services_in_request_array[$parcel_count]);
                                    $group['dangerous_goods_data'] = empty($dangerous_goods_data) ? false : $dangerous_goods_data;
                                    $group['shipping_service'] = $this->shipment_services_in_request_array[$parcel_count];
                                    $group['startrack_service_selected'] = 'yes';
                                } else {
                                    $dangerous_goods_data[] = $this->validate_dangerous_goods($product, 'Express Post');
                                    $group['dangerous_goods_data'] = empty($dangerous_goods_data) ? false : $dangerous_goods_data;
                                    $group['shipping_service'] = $this->shipment_services_in_request_array[$parcel_count];
                                    
                                }
                            } else {
                                $group['shipping_service'] = $service_method_id;
                                if (empty($group['shipping_service'])) {
                                    $group['shipping_service'] = $this->default_service;
                                }
                            }
                        }

                        $auto_generate_label_status = get_option('auto_generate_label_on_auspost_elex', false);
                        if ($auto_generate_label_status) {
                            $group['shipping_service'] = $service_method_id;
                            if (empty($group['shipping_service'])) {
                                $group['shipping_service'] = $this->default_service;
                            }
                        }
                    }

                    for ($quantity = 0; $quantity < $product_ordered_quantity; $quantity++) {
                        if ($quantity != 0 && isset($this->shipment_services_in_request_array[$parcel_count])) {
                            if (strpos($this->shipment_services_in_request_array[$parcel_count], 'startrack')) {
                                $group['shipping_service'] = str_replace('startrack', '', $this->shipment_services_in_request_array[$parcel_count]);
                                $group['startrack_service_selected'] = 'yes';
                            } else {
                                $group['shipping_service'] = $this->shipment_services_in_request_array[$parcel_count];
                                $group['startrack_service_selected'] = 'no';
                            }
                            $parcel_count++;
                            $group['package_order'] = $parcel_count;
                        }
                        $to_ship[] = $group;
                    }
                }
                $parcel_count++;
            }
        }

        return $to_ship;
    }

   

    /**
     * function to obtain shipping service type (Parcel ,Express , StarTrack)
     * @access private
     * @param array auspost contract services
     * @param string shipping method id
     * @return string shipping method type
     */
    private function get_shipping_service_type($order, $eligible_postage_products, $label_shipping_method_id)
    {
        $this->selected_service_type = '';
        $shipment_services = $this->wf_get_shipping_service($order, false);
        $shipment_services_array = $this->return_package_data_from_request($shipment_services);

        if ($this->rate_type == 'startrack') {
            $this->selected_service_type = 'StarTrack';
        } else {
            /* Obtaining selected shipping service type for eg: Express, Post, International.. */
            if (is_array($eligible_postage_products) && !empty($eligible_postage_products)) {
                foreach ($eligible_postage_products as $postage_product_eligible) {
                    if ($postage_product_eligible['product_id'] == $label_shipping_method_id) {
                        if (isset($postage_product_eligible['group'])) {
                            $this->selected_service_type = $postage_product_eligible['group'];
                        }
                    }
                }
            }
        }

        return $this->selected_service_type;
    }

    /**
     * function to assign required parameters for dangerous goods based on the shipping service type
     * @access private
     * @param woocommerce product
     * @return mixed product meta
     */
    private function validate_dangerous_goods($product, $selected_service_type)
    {
        $is_dangerous_good = false;
        if (!empty($product)) {
            $product_meta_data_for_packaging = array();
            $product_id = $product->get_id();
            $product_weight = $product->get_weight();

            $dangerous_goods_description_express = array(
                'UN2910' => 'UN2910_radioactive_excepted_limited_qty',
                'UN2911' => 'UN2911_radioactive_excepted_instruments_or_articles',
                'UN3373' => 'UN3373_BioSubstance_B',
                'UN3481' => 'UN3481_Lithium_IonOrPolymer_contained_in_equipment',
                'UN3091' => 'UN3091_Lithium_MetalAndAlloy_contained_in_equipment'
            );
            $product_dangerous_good_check_meta = 'no';
            if ($product->get_type() == 'variation') {
                $product_id = $product->get_parent_id();
            }

            if ($selected_service_type == 'StarTrack') {
                if (isset($this->settings['enable_dangerous_goods_configuration_startrack']) && $this->settings['enable_dangerous_goods_configuration_startrack'] == 'yes') {
                    $product_dangerous_good_check_meta = get_post_meta($product_id, '_dangerous_goods_check_startrack_auspost_elex', true);
                }
            } else {
                $product_dangerous_good_check_meta = get_post_meta($product_id, '_dangerous_goods_check_auspost_elex', true);
            }

            if ($product_dangerous_good_check_meta == 'yes') {
                $is_dangerous_good = true;
            }

            $product_meta_data_for_packaging = array();

            if ($is_dangerous_good) {
                update_option('current_shipment_contains_dangerous_goods_auspost_elex', true);
                if (!empty($selected_service_type)) {
                    switch ($selected_service_type) {
                        case 'StarTrack':
                            $selected_un_number = get_post_meta($product_id, '_dangerous_goods_desciption_startrack_auspost_elex', true);
                            $selected_un_number_description = isset($this->settings['dangerous_goods_descriptions']) && !empty($this->settings['dangerous_goods_descriptions']) && $this->settings['dangerous_goods_descriptions'][$selected_un_number] ? $this->settings['dangerous_goods_descriptions'][$selected_un_number] : array();
                            if (!empty($selected_un_number_description) && $is_dangerous_good) {
                                $product_meta_data_for_packaging['startrack'] = array(
                                    'dangerous_goods_status' => 'yes',
                                    'un_number' => $selected_un_number,
                                    'technical_name' => $selected_un_number_description['technical_name'],
                                    'class_division' => $selected_un_number_description['class_division'],
                                    'subsidiary_risk' => $selected_un_number_description['subsidiary_risk'],
                                    'packing_group_designator' => $selected_un_number_description['packing_group_designator'],
                                    'outer_packaging_type' =>  $selected_un_number_description['outer_packaging_type'],
                                    'outer_packaging_quantity' => $selected_un_number_description['outer_packaging_quantity'],
                                    'net_weight' => $product_weight
                                );
                            }
                            break;

                        case 'Express Post':
                            $product_un_number_type = get_post_meta($product_id, '_dangerous_goods_desciption_auspost_elex', true);
                            $product_meta_data_for_packaging['express'] = array(
                                'dangerous_goods_status' => true,
                                'un_number_type' => $dangerous_goods_description_express[$product_un_number_type]
                            );
                            break;

                        default: // Parcel Post
                            $product_meta_data_for_packaging['dangerous_goods_status_parcel'] = 'yes';
                            break;
                    }

                    return $product_meta_data_for_packaging;
                }
            } else {
                update_option('current_shipment_contains_dangerous_goods_auspost_elex', false);
            }
        }

        return $is_dangerous_good;
    }


    /**
     * box_packing function.
     *
     * @access private
     * @param mixed $order
     * @return $to_ship (packages ready to ship)
     */

    private function box_packing($order)
    {

        $box_packing_method = !empty($this->settings['box_packing_method']) ? $this->settings['box_packing_method'] : 'volume';


        $boxes = array();
        $order_id = $order->get_id();

        $is_request_create_shipment = get_option('request_to_create_shipment');


        $orderItems = $order->get_items();
        $orderItems = apply_filters( 'elex_order_items', $orderItems );
        if (!empty($orderItems) && is_array($orderItems)) {

            if ($this->is_woocommerce_composite_products_installed) {
                $orderItems = $this->get_composite_product_items($orderItems);
            }

            // Compatibility of Australia Post with ELEX Multivendor Addon Code
            if ($this->vendor_check) {
                $order_items_data = apply_filters('elex_vendor_custom_split_shipping_packages', $orderItems);
                $to_ship = array();
                $parcel_count = 0;
                foreach ($order_items_data as $order_item_data) {
                    $seller_id  = $order_item_data['seller_id'];
                    $postage_products_data = $this->get_postage_product_data($seller_id);
                    $all_eligible_postage_products = array_merge($postage_products_data['auspost_eligible_postage_products'], $postage_products_data['startrack_eligible_postage_products']);
                    update_option("all_auspost_postage_products_auspost_elex", $all_eligible_postage_products);

                    $postage_products_type_and_product_ids = $this->get_postage_products_type_and_product_ids($all_eligible_postage_products);
                    $serviceName = $this->wf_get_shipping_service($order, false);
                    $service_method_id = $this->get_selected_shipping_service_id($postage_products_type_and_product_ids, $serviceName, $order);

                    $stored_pre_defined_boxes = get_option('auspost_stored_pre_defined_boxes');
                    $stored_custom_boxes = get_option('auspost_stored_custom_boxes');
                    $stored_boxes_for_packing_starTrack = get_option('starTrack_stored_boxes');

                    $stored_auspost_boxes = array_merge($stored_pre_defined_boxes, $stored_custom_boxes);
                    $shipping_country = wf_get_order_shipping_country($order);

                    if ($this->rate_type == 'startrack') {
                        $boxes = $stored_boxes_for_packing_starTrack;
                    } elseif (!empty($stored_auspost_boxes)) {
                        $boxes = $stored_auspost_boxes;
                    } else {
                        $boxes = $this->general_settings['boxes'];
                    }

                    $from_weight_unit = '';
                    if ($this->weight_unit != 'kg') {
                        $from_weight_unit = $this->weight_unit;
                    }

                    $from_dimension_unit = '';
                    if ($this->dimension_unit != 'cm') {
                        $from_dimension_unit = $this->dimension_unit;
                    }

                    if (count($boxes) == 0) {
                        $boxpacking_error_desc = "No boxes are available to Create shipping";
                        $this->set_boxpacking_error_notices($boxpacking_error_desc);
                    }

                    $failed_shipment_order_packages = get_post_meta($order->get_id(), 'consolidated_failed_create_shipment_packages_auspost_elex', true);
                    if ($is_request_create_shipment) {
                        delete_post_meta($order_id, 'failed_create_shipment_packages_auspost_elex');
                    }

                    if ($box_packing_method == 'stack') {
                        if (!class_exists('WF_Boxpack_Stack')) {
                            include_once 'class-wf-packing-stack.php';
                        }
                        $boxpack = new WF_Boxpack_Stack();
                    } else {
                        if (!class_exists('WF_Boxpack')) {
                            include_once 'class-wf-packing.php';
                        }
                        $boxpack = new WF_Boxpack();
                    }
                    // Define boxes
                    if (!empty($boxes) && is_array($boxes)) {
                        foreach ($boxes as $key => $box) {
                            if (!$box['enabled']) {
                                continue;
                            }

                            $newbox = $boxpack->add_box($box['outer_length'], $box['outer_width'], $box['outer_height'], $box['box_weight'], $box['pack_type']);
                            $newbox->set_inner_dimensions($box['inner_length'], $box['inner_width'], $box['inner_height']);
                            $newbox->set_name($box['name']);

                            if (isset($box['id'])) {
                                $newbox->set_id(current(explode(':', $box['id'])));
                            }

                            if ($box['max_weight']) {
                                $newbox->set_max_weight($box['max_weight']);
                            }
                        }
                    }
                    foreach ($order_item_data['contents'] as $orderItem) {
                        $item_data = $orderItem->get_data();
                        $product_id = isset($item_data['variation_id']) && ($item_data['variation_id'] != 0) ? $item_data['variation_id'] : $item_data['product_id'];
                        $product = wc_get_product($product_id);

                        

                        if ($refd_qty = $this->is_refunded_item($order, $product_id)) {
                            if ($item_data['quantity'] - $refd_qty <= 0) {
                                continue;
                            } else {
                                $item_data['quantity'] = $item_data['quantity'] - $refd_qty;
                            }
                        }

                        if ($product->get_length() && $product->get_width() && $product->get_height() && $product->get_weight()) {

                            $dimensions = array($product->get_length(), $product->get_width(), $product->get_height());

                            $order_item_price = $item_data['total'] / $item_data['quantity'];

                            for ($i = 0; $i < $item_data['quantity']; $i++) {
                                $boxpack->add_item(
                                    $dimensions[2],
                                    $dimensions[1],
                                    $dimensions[0],
                                    $product->get_weight(),
                                    $order_item_price,
                                    array(
                                        'data' => $orderItem
                                    )
                                );
                            }
                        } else {
                            update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', 'Products does not contain weights and/or dimensions');
                        }
                    }
                    // Pack it
                    $boxpack->pack();
                    $packages = $boxpack->get_packages();
                    $packed = new stdClass();
                    $package_items = array();

                    $package_item = new WC_Product();
                    $package_item_data = array();
                    $package_name = '';
                    $dangerous_goods_data = array();
                    if (!empty($packages) && is_array($packages)) {
                        foreach ($packages as $package) {
                            $shipment_service_type = '';
                            if (empty($failed_shipment_order_packages) || (!empty($failed_shipment_order_packages) && in_array($parcel_count, $failed_shipment_order_packages))) {
                                if (!empty($this->shipment_services_in_request_array)) {
                                    if (strpos($this->shipment_services_in_request_array[$parcel_count], 'startrack')) {
                                        $shipment_service_type = 'StarTrack';
                                    } else {
                                        $shipment_service_type = 'Express Post';
                                    }
                                }
                                $package_items = array();
                                if ($package->unpacked === true) {
                                    // $this->debug('<b><font color="red">Unpacked Item </font>'.$package_item_data["name"].'</b> <br>');
                                    // Not using now, we are displaying this data on the metabox data. But keeping it for future use.
                                    $package_item = wc_get_product($package->product_id);
                                    $package_name = $package_item->get_title();
                                } else {
                                    // $this->debug('<b><font color="green">Packed in </font>' . strtok($package->name, '('). "</b><br>");
                                }

                                if (isset($package->packed)) {
                                    $packed = $package->packed;
                                    $package_name = isset($package->name) && !empty($package->name) ? $package->name : '';
                                }

                                $packed_products_ids_array = array();
                                $product_age_check_selected = '';
                                if (is_array($packed)) {
                                    foreach ($packed as $packed_product) {
                                        $packed_product_meta = $packed_product->meta;
                                        $packed_product_meta_data = $packed_product_meta['data'];
                                        $packed_meta = $packed_product_meta_data->get_data();
                                        if (!empty($packed_meta['variation_id'])) {
                                            $packed_products_ids_array[] = $packed_meta['variation_id'];
                                        } else {
                                            $packed_products_ids_array[] = $packed_meta['product_id'];
                                        }
                                    }
                                    $packed_products_ids_array_unique = array_unique($packed_products_ids_array);
                                    $packed_products_ids_array_unique_values = array_count_values($packed_products_ids_array);
                                    $box_packed_products = array();

                                    foreach ($packed_products_ids_array_unique_values as $packed_products_ids_array_unique_values_key => $packed_products_ids_array_unique_values_elements) {
                                        foreach ($packed as $packed_product) {
                                            $packed_product_meta = $packed_product->meta;
                                            $packed_product_meta_data = $packed_product_meta['data'];
                                            $packed_meta = $packed_product_meta_data->get_data();
                                            if (($packed_meta['variation_id'] != 0) && ($packed_meta['variation_id'] === $packed_products_ids_array_unique_values_key)) {
                                                $packed_meta['quantity'] = $packed_products_ids_array_unique_values[$packed_products_ids_array_unique_values_key];
                                                $box_packed_products[] = $packed_product;
                                                break;
                                            } else if (($packed_meta['product_id'] != 0) && ($packed_meta['product_id'] === $packed_products_ids_array_unique_values_key)) {
                                                $packed_meta['quantity'] = $packed_products_ids_array_unique_values[$packed_products_ids_array_unique_values_key];
                                                $box_packed_products[] = $packed_product;
                                                break;
                                            }
                                        }
                                    }

                                    foreach ($box_packed_products as $box_packed_product) {
                                        $box_packed_product_meta = $box_packed_product->meta;
                                        $box_packed_product_meta_data = $box_packed_product_meta['data'];
                                        $packed_meta = $box_packed_product_meta_data->get_data();
                                        $product_id = $packed_meta['product_id'];

                                        if (empty($product_age_check_selected)) {
                                            $product_age_check_selected = get_post_meta($product_id, 'age_check_auspost_elex', true);
                                        }

                                        $product_desc = get_post_meta($packed_meta['product_id'], '_wf_shipping_description', 1);

                                        $package_weight = get_post_meta($packed_meta['variation_id'], '_weight', 1);

                                        $package_weight = (!empty($package_weight)) ? $package_weight : get_post_meta($packed_meta['product_id'], '_weight', 1);

                                        $packed_product_temp = ($packed_meta['variation_id']) ? wc_get_product($packed_meta['variation_id']) : wc_get_product($packed_meta['product_id']);
                                        $dangerous_goods_data[] = $this->validate_dangerous_goods($packed_product_temp, $shipment_service_type);
                                        $package_item_description = !empty($product_desc) ? $product_desc : $packed_meta['name'];
                                        $package_item_value = $packed_product_temp->get_price();
                                        $package_items[] = array(
                                            'description'               => (strlen($package_item_description) > 40) ? substr( $this->string_clean( $package_item_description ), 0, 37) . '...' : $package_item_description,
                                            'quantity'                  => $box_packed_product->packed_quantity,
                                            'value'                     => $package_item_value,
                                            'tariff_code'               => get_post_meta($packed_meta['product_id'], '_wf_tariff_code', 1),
                                            'country_of_origin'         => get_post_meta($packed_meta['product_id'], '_wf_country_of_origin', 1),
                                            'weight'                    => ($this->weight_unit != 'kg') ? round(wc_get_weight($package_weight, 'kg', $this->weight_unit), 2) : round($package_weight, 2),
                                            'export_declaration_number' => get_post_meta($packed_meta['product_id'], '_wf_export_declaration_number', 1)
                                        );
                                    }
                                } else {
                                    $product_desc = get_post_meta($package->product_id, '_wf_shipping_description', 1);
                                    if (empty($product_age_check_selected)) {
                                        $product_age_check_selected = get_post_meta($product_id, 'age_check_auspost_elex', true);
                                    }
                                    $packed_product_temp = wc_get_product($package->product_id);
                                    $dangerous_goods_data[] = $this->validate_dangerous_goods($packed_product_temp, $shipment_service_type);
                                    if ($package->variation_id) {
                                        $package_weight = get_post_meta($package->variation_id, '_weight', 1);
                                        $package_weight = (!empty($package_weight)) ? $package_weight : get_post_meta($package->product_id, '_weight', 1);
                                        $package_item_value = get_post_meta($package->variation_id, '_sale_price', 1);
                                        $package_item_value = empty($package_item_value) ? get_post_meta($package->variation_id, '_regular_price', 1) : $package_item_value;
                                        $package_items[] = array(
                                            'description'               => (strlen($product_desc) > 40) ? substr( $this->string_clean( $product_desc ), 0, 37) . '...' : $product_desc,
                                            'quantity'                  => 1,
                                            'value'                     => $package_item_value,
                                            'tariff_code'               => get_post_meta($package->product_id, '_wf_tariff_code', 1),
                                            'country_of_origin'         => get_post_meta($package->product_id, '_wf_country_of_origin', 1),
                                            'weight'                    => ($this->weight_unit != 'kg') ? round(wc_get_weight($package_weight, 'kg', $this->weight_unit), 2) : round($package_weight, 2),
                                            'export_declaration_number' => get_post_meta($package->product_id, '_wf_export_declaration_number', 1)
                                        );
                                    }else {
                                        $package_item_value = get_post_meta($package->product_id, '_sale_price', 1);
                                        $package_item_value = empty($package_item_value) ? get_post_meta($package->product_id, '_regular_price', 1) : $package_item_value;
                                        $package_items[] = array(
                                            'description'               => (strlen($product_desc) > 40) ? substr( $this->string_clean( $product_desc ), 0, 37) . '...' : $product_desc,
                                            'quantity'                  => 1,
                                            'value'                     => $package_item_value,
                                            'tariff_code'               => get_post_meta($package->product_id, '_wf_tariff_code', 1),
                                            'country_of_origin'         => get_post_meta($package->product_id, '_wf_country_of_origin', 1),
                                            'weight'                    => ($this->weight_unit != 'kg') ? round(wc_get_weight(get_post_meta($package->product_id, '_weight', 1), 'kg', $this->weight_unit), 2) : round(get_post_meta($package->product_id, '_weight', 1), 2),
                                            'export_declaration_number' => get_post_meta($package->product_id, '_wf_export_declaration_number', 1)
                                        );
                                    }
                                }

                                $dimensions = array($package->length, $package->width, $package->height);

                                sort($dimensions);

                                $insurance_array = array(
                                    'Amount' => $package->value,
                                    'Currency' => get_woocommerce_currency()
                                );

                                $package_name = (isset($package_name) && !empty($package_name)) ? $package_name : ((isset($package->title) && !empty($package->title)) ? $package->title : ((isset($package->name) && !empty($package->name)) ? $package->name : ''));

                                if (!empty($package_name)) {
                                    $package_name = strtok($package_name, '(');
                                }

                                if (!isset($package->packed) && !empty($package_name)) {
                                    $package_name = $package_name; //'<small> (Packed Separately)</small>'
                                }
                                if ($this->weight_unit != 'kg' && $this->is_request_bulk_shipment) {
                                    $from_weight_unit = $this->weight_unit;
                                    $package->weight = wc_get_weight($package->weight, 'kg', $this->weight_unit);
                                }
                                $group = array(
                                    'Name' => $package_name,
                                    'Weight' => array(
                                        'Value' => !empty($this->weights_in_request_array[$parcel_count]) ? $this->weights_in_request_array[$parcel_count] : round($package->weight, 2),
                                        'Units' => $this->weight_unit
                                    ),
                                    'Dimensions' => array(
                                        'Length' => !empty($this->lengths_in_request_array[$parcel_count]) ? $this->lengths_in_request_array[$parcel_count] : max(0.1, round($dimensions[2], 2)),
                                        'Width' => !empty($this->widths_in_request_array[$parcel_count]) ? $this->widths_in_request_array[$parcel_count] : max(0.1, round($dimensions[1], 2)),
                                        'Height' => !empty($this->heights_in_request_array[$parcel_count]) ? $this->heights_in_request_array[$parcel_count] : max(0.1, round($dimensions[0], 2)),
                                        'Units' => (!empty($this->lengths_in_request_array[$parcel_count]) || !empty($this->widths_in_request_array[$parcel_count]) || !empty($this->heights_in_request_array[$parcel_count])) ? 'cm' : $this->dimension_unit
                                    ),
                                    'seller_id'  => $order_item_data['seller_id'],
                                    'origin' => $order_item_data['origin'],
                                    'InsuredValue' => $insurance_array,
                                    'packed_products' => array(),
                                    'Item_contents' => $package_items,
                                    'pack_type' => ($package->packtype != 'NONE') ? $package->packtype : 'ITM',
                                    'dangerous_goods_data' => empty($dangerous_goods_data) ? false : $dangerous_goods_data,
                                    'age_check' => ($product_age_check_selected) ? $product_age_check_selected : false,
                                );
                                
                                if ($is_request_create_shipment) {
                                    if (!empty($this->shipment_services_in_request_array)) {
                                        if (strpos($this->shipment_services_in_request_array[$parcel_count], 'startrack')) {
                                            $this->shipment_services_in_request_array[$parcel_count] = str_replace('startrack', '', $this->shipment_services_in_request_array[$parcel_count]);
                                            $group['shipping_service'] = $this->shipment_services_in_request_array[$parcel_count];
                                            $group['startrack_service_selected'] = 'yes';
                                        } else {
                                            $group['shipping_service'] = $this->shipment_services_in_request_array[$parcel_count];
                                        }
                                    } else {
                                        $group['shipping_service'] = $service_method_id;
                                        if (empty($group['shipping_service'])) {
                                            $group['shipping_service'] = $this->default_service;
                                        }
                                    }

                                    $auto_generate_label_status = get_option('auto_generate_label_on_auspost_elex', false);
                                    if ($auto_generate_label_status) {
                                        $group['shipping_service'] = $service_method_id;
                                        if (empty($group['shipping_service'])) {
                                            $group['shipping_service'] = $this->default_service;
                                        }
                                    }
                                }

                                if (!empty($package->packed) && is_array($package->packed)) {
                                    foreach ($package->packed as $packed) {
                                        $group['packed_products'][] = $packed->get_meta('data');
                                    }
                                }

                                $to_ship[] = $group;

                                $parcel_count++;
                            }
                        }
                    }
                }
                return $to_ship;
            } else {
                $postage_products_data = $this->get_postage_product_data();
                $all_eligible_postage_products = array_merge($postage_products_data['auspost_eligible_postage_products'], $postage_products_data['startrack_eligible_postage_products']);
                update_option("all_auspost_postage_products_auspost_elex", $all_eligible_postage_products);

                $postage_products_type_and_product_ids = $this->get_postage_products_type_and_product_ids($all_eligible_postage_products);
                $serviceName = $this->wf_get_shipping_service($order, false);
                $service_method_id = $this->get_selected_shipping_service_id($postage_products_type_and_product_ids, $serviceName, $order);

                $stored_pre_defined_boxes = get_option('auspost_stored_pre_defined_boxes');
                $stored_custom_boxes = get_option('auspost_stored_custom_boxes');
                $stored_boxes_for_packing_starTrack = get_option('starTrack_stored_boxes');

                $stored_auspost_boxes = array_merge($stored_pre_defined_boxes, $stored_custom_boxes);
                $shipping_country = wf_get_order_shipping_country($order);

                if ($this->rate_type == 'startrack') {
                    $boxes = $stored_boxes_for_packing_starTrack;
                } elseif (!empty($stored_auspost_boxes)) {
                    $boxes = $stored_auspost_boxes;
                } else {
                    $boxes = $this->general_settings['boxes'];
                }

                $from_weight_unit = '';
                if ($this->weight_unit != 'kg') {
                    $from_weight_unit = $this->weight_unit;
                }

                $from_dimension_unit = '';
                if ($this->dimension_unit != 'cm') {
                    $from_dimension_unit = $this->dimension_unit;
                }

                if (count($boxes) == 0) {
                    $boxpacking_error_desc = "No boxes are available to Create shipping";
                    $this->set_boxpacking_error_notices($boxpacking_error_desc);
                }

                $failed_shipment_order_packages = get_post_meta($order->get_id(), 'consolidated_failed_create_shipment_packages_auspost_elex', true);
                if ($is_request_create_shipment) {
                    delete_post_meta($order_id, 'failed_create_shipment_packages_auspost_elex');
                }

                if ($box_packing_method == 'stack') {
                    if (!class_exists('WF_Boxpack_Stack')) {
                        include_once 'class-wf-packing-stack.php';
                    }
                    $boxpack = new WF_Boxpack_Stack();
                } else {
                    if (!class_exists('WF_Boxpack')) {
                        include_once 'class-wf-packing.php';
                    }
                    $boxpack = new WF_Boxpack();
                }
                // Define boxes
                if (!empty($boxes) && is_array($boxes)) {
                    foreach ($boxes as $key => $box) {
                        if (!$box['enabled']) {
                            continue;
                        }

                        $newbox = $boxpack->add_box($box['outer_length'], $box['outer_width'], $box['outer_height'], $box['box_weight'], $box['pack_type']);
                        $newbox->set_inner_dimensions($box['inner_length'], $box['inner_width'], $box['inner_height']);
                        $newbox->set_name($box['name']);

                        if (isset($box['id'])) {
                            $newbox->set_id(current(explode(':', $box['id'])));
                        }

                        if ($box['max_weight']) {
                            $newbox->set_max_weight($box['max_weight']);
                        }
                    }
                }
                foreach ($orderItems as $orderItem) {
                    $item_data = $orderItem->get_data();
                    $product_id = isset($item_data['variation_id']) && ($item_data['variation_id'] != 0) ? $item_data['variation_id'] : $item_data['product_id'];
                    $product = wc_get_product($product_id);


                    if ($refd_qty = $this->is_refunded_item($order, $product_id)) {
                        if ($item_data['quantity'] - $refd_qty <= 0) {
                            continue;
                        } else {
                            $item_data['quantity'] = $item_data['quantity'] - $refd_qty;
                        }
                    }

                    if ($product->get_length() && $product->get_width() && $product->get_height() && $product->get_weight()) {

                        $dimensions = array($product->get_length(), $product->get_width(), $product->get_height());

                        $order_item_price = $item_data['total'] / $item_data['quantity'];

                        for ($i = 0; $i < $item_data['quantity']; $i++) {
                            $boxpack->add_item(
                                $dimensions[2],
                                $dimensions[1],
                                $dimensions[0],
                                $product->get_weight(),
                                $order_item_price,
                                array(
                                    'data' => $orderItem
                                )
                            );
                        }
                    } else {
                        update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', 'Products does not contain weights and/or dimensions');
                    }
                }
                // Pack it
                $boxpack->pack();
                $packages = $boxpack->get_packages();
                $packed = new stdClass();
                $to_ship = array();
                $package_items = array();
                $parcel_count = 0;
                $package_item = new WC_Product();
                $package_item_data = array();
                $package_name = '';
                $dangerous_goods_data = array();
                if (!empty($packages) && is_array($packages)) {
                    foreach ($packages as $package) {
                        $shipment_service_type = '';
                        if (empty($failed_shipment_order_packages) || (!empty($failed_shipment_order_packages) && in_array($parcel_count, $failed_shipment_order_packages))) {
                            if (!empty($this->shipment_services_in_request_array)) {
                                if (strpos($this->shipment_services_in_request_array[$parcel_count], 'startrack')) {
                                    $shipment_service_type = 'StarTrack';
                                } else {
                                    $shipment_service_type = 'Express Post';
                                }
                            }
                            $package_items = array();
                            if ($package->unpacked === true) {
                                // $this->debug('<b><font color="red">Unpacked Item </font>'.$package_item_data["name"].'</b> <br>');
                                // Not using now, we are displaying this data on the metabox data. But keeping it for future use.
                                $package_item = wc_get_product($package->product_id);
                                $package_name = $package_item->get_title();
                            } else {
                                // $this->debug('<b><font color="green">Packed in </font>' . strtok($package->name, '('). "</b><br>");
                            }

                            if (isset($package->packed)) {
                                $packed = $package->packed;
                                $package_name = isset($package->name) && !empty($package->name) ? $package->name : '';
                            }

                            $packed_products_ids_array = array();
                            $product_age_check_selected = '';
                            if (is_array($packed)) {
                                foreach ($packed as $packed_product) {
                                    $packed_product_meta = $packed_product->meta;
                                    $packed_product_meta_data = $packed_product_meta['data'];
                                    $packed_meta = $packed_product_meta_data->get_data();
                                    if (!empty($packed_meta['variation_id'])) {
                                        $packed_products_ids_array[] = $packed_meta['variation_id'];
                                    } else {
                                        $packed_products_ids_array[] = $packed_meta['product_id'];
                                    }
                                }
                                $packed_products_ids_array_unique = array_unique($packed_products_ids_array);
                                $packed_products_ids_array_unique_values = array_count_values($packed_products_ids_array);
                                $box_packed_products = array();

                                foreach ($packed_products_ids_array_unique_values as $packed_products_ids_array_unique_values_key => $packed_products_ids_array_unique_values_elements) {
                                    foreach ($packed as $packed_product) {
                                        $packed_product_meta = $packed_product->meta;
                                        $packed_product_meta_data = $packed_product_meta['data'];
                                        $packed_meta = $packed_product_meta_data->get_data();
                                        if (($packed_meta['variation_id'] != 0) && ($packed_meta['variation_id'] === $packed_products_ids_array_unique_values_key)) {
                                            $packed_meta['quantity'] = $packed_products_ids_array_unique_values[$packed_products_ids_array_unique_values_key];
                                            $box_packed_products[] = $packed_product;
                                            break;
                                        } else if (($packed_meta['product_id'] != 0) && ($packed_meta['product_id'] === $packed_products_ids_array_unique_values_key)) {
                                            $packed_meta['quantity'] = $packed_products_ids_array_unique_values[$packed_products_ids_array_unique_values_key];
                                            $box_packed_products[] = $packed_product;
                                            break;
                                        }
                                    }
                                }

                                foreach ($box_packed_products as $box_packed_product) {
                                    $box_packed_product_meta = $box_packed_product->meta;
                                    $box_packed_product_meta_data = $box_packed_product_meta['data'];
                                    $packed_meta = $box_packed_product_meta_data->get_data();
                                    $product_id = $packed_meta['product_id'];

                                    if (empty($product_age_check_selected)) {
                                        $product_age_check_selected = get_post_meta($product_id, 'age_check_auspost_elex', true);
                                    }

                                    $product_desc = get_post_meta($packed_meta['product_id'], '_wf_shipping_description', 1);

                                    $package_weight = get_post_meta($packed_meta['variation_id'], '_weight', 1);

                                    $package_weight = (!empty($package_weight)) ? $package_weight : get_post_meta($packed_meta['product_id'], '_weight', 1);
                                    $packed_product_temp = ($packed_meta['variation_id']) ? wc_get_product($packed_meta['variation_id']) : wc_get_product($packed_meta['product_id']);
                                    $dangerous_goods_data[] = $this->validate_dangerous_goods($packed_product_temp, $shipment_service_type);
                                    $package_item_description = !empty($product_desc) ? $product_desc : $packed_meta['name'];
                                    $package_item_value = $packed_product_temp->get_price();

                                    $package_items[] = array(
                                        'description'               => (strlen($package_item_description) > 40) ? substr( $this->string_clean( $package_item_description ), 0, 37) . '...' : $package_item_description,
                                        'quantity'                  => $box_packed_product->packed_quantity,
                                        'value'                     => $package_item_value,
                                        'tariff_code'               => get_post_meta($packed_meta['product_id'], '_wf_tariff_code', 1),
                                        'country_of_origin'         => get_post_meta($packed_meta['product_id'], '_wf_country_of_origin', 1),
                                        'weight'                    => ($this->weight_unit != 'kg') ? round(wc_get_weight($package_weight, 'kg', $this->weight_unit), 2) : round($package_weight, 2),
                                        'export_declaration_number' => get_post_meta($packed_meta['product_id'], '_wf_export_declaration_number', 1)
                                    );
                                }
                            } else {
                                $product_desc = get_post_meta($package->product_id, '_wf_shipping_description', 1);
                                if (empty($product_age_check_selected)) {
                                    $product_age_check_selected = get_post_meta($product_id, 'age_check_auspost_elex', true);
                                }
                                $packed_product_temp = wc_get_product($package->product_id);
                                $dangerous_goods_data[] = $this->validate_dangerous_goods($packed_product_temp, $shipment_service_type);
                                if ($package->variation_id) {
                                    $package_weight = get_post_meta($package->variation_id, '_weight', 1);
                                    $package_weight = (!empty($package_weight)) ? $package_weight : get_post_meta($package->product_id, '_weight', 1);
                                    $package_item_value = get_post_meta($package->variation_id, '_sale_price', 1);
                                    $package_item_value = empty($package_item_value) ? get_post_meta($package->variation_id, '_regular_price', 1) : $package_item_value;
                                    $package_items[] = array(
                                        'description'               => (strlen($product_desc) > 40) ? substr( $this->string_clean( $product_desc ), 0, 37) . '...' : $product_desc,
                                        'quantity'                  => 1,
                                        'value'                     => $package_item_value,
                                        'tariff_code'               => get_post_meta($package->product_id, '_wf_tariff_code', 1),
                                        'country_of_origin'         => get_post_meta($package->product_id, '_wf_country_of_origin', 1),
                                        'weight'                    => ($this->weight_unit != 'kg') ? round(wc_get_weight($package_weight, 'kg', $this->weight_unit), 2) : round($package_weight, 2),
                                        'export_declaration_number' => get_post_meta($package->product_id, '_wf_export_declaration_number', 1)
                                    );
                                } else {
                                    $package_item_value = get_post_meta($package->product_id, '_sale_price', 1);
                                    $package_item_value = empty($package_item_value) ? get_post_meta($package->product_id, '_regular_price', 1) : $package_item_value;
                                    $package_items[] = array(
                                        'description'               => (strlen($product_desc) > 40) ? substr( $this->string_clean( $product_desc ), 0, 37) . '...' : $product_desc,
                                        'quantity'                  => 1,
                                        'value'                     => $package_item_value,
                                        'tariff_code'               => get_post_meta($package->product_id, '_wf_tariff_code', 1),
                                        'country_of_origin'         => get_post_meta($package->product_id, '_wf_country_of_origin', 1),
                                        'weight'                    => ($this->weight_unit != 'kg') ? round(wc_get_weight(get_post_meta($package->product_id, '_weight', 1), 'kg', $this->weight_unit), 2) : round(get_post_meta($package->product_id, '_weight', 1), 2),
                                        'export_declaration_number' => get_post_meta($package->product_id, '_wf_export_declaration_number', 1)
                                    );
                                }
                            }

                            $dimensions = array($package->length, $package->width, $package->height);

                            sort($dimensions);

                            $insurance_array = array(
                                'Amount' => $package->value,
                                'Currency' => get_woocommerce_currency()
                            );

                            $package_name = (isset($package_name) && !empty($package_name)) ? $package_name : ((isset($package->title) && !empty($package->title)) ? $package->title : ((isset($package->name) && !empty($package->name)) ? $package->name : ''));

                            if (!empty($package_name)) {
                                $package_name = strtok($package_name, '(');
                            }

                            if (!isset($package->packed) && !empty($package_name)) {
                                $package_name = $package_name; //'<small> (Packed Separately)</small>'
                            }
                            if ($this->weight_unit != 'kg' && $this->is_request_bulk_shipment) {
                                $from_weight_unit = $this->weight_unit;
                                $package->weight = wc_get_weight($package->weight, 'kg', $this->weight_unit);
                            }
                            $group = array(
                                'Name' => $package_name,
                                'Weight' => array(
                                    'Value' => !empty($this->weights_in_request_array[$parcel_count]) ? $this->weights_in_request_array[$parcel_count] : round($package->weight, 2),
                                    'Units' => $this->weight_unit
                                ),
                                'Dimensions' => array(
                                    'Length' => !empty($this->lengths_in_request_array[$parcel_count]) ? $this->lengths_in_request_array[$parcel_count] : max(0.1, round($dimensions[2], 2)),
                                    'Width' => !empty($this->widths_in_request_array[$parcel_count]) ? $this->widths_in_request_array[$parcel_count] : max(0.1, round($dimensions[1], 2)),
                                    'Height' => !empty($this->heights_in_request_array[$parcel_count]) ? $this->heights_in_request_array[$parcel_count] : max(0.1, round($dimensions[0], 2)),
                                    'Units' => (!empty($this->lengths_in_request_array[$parcel_count]) || !empty($this->widths_in_request_array[$parcel_count]) || !empty($this->heights_in_request_array[$parcel_count])) ? 'cm' : $this->dimension_unit
                                ),
                                'InsuredValue' => $insurance_array,
                                'packed_products' => array(),
                                'Item_contents' => $package_items,
                                'pack_type' => ($package->packtype != 'NONE') ? $package->packtype : 'ITM',
                                'dangerous_goods_data' => empty($dangerous_goods_data) ? false : $dangerous_goods_data,
                                'age_check' => ($product_age_check_selected) ? $product_age_check_selected : false,
                            );
                            if ($is_request_create_shipment) {
                                if (!empty($this->shipment_services_in_request_array)) {
                                    if (strpos($this->shipment_services_in_request_array[$parcel_count], 'startrack')) {
                                        $this->shipment_services_in_request_array[$parcel_count] = str_replace('startrack', '', $this->shipment_services_in_request_array[$parcel_count]);
                                        $group['shipping_service'] = $this->shipment_services_in_request_array[$parcel_count];
                                        $group['startrack_service_selected'] = 'yes';
                                    } else {
                                        $group['shipping_service'] = $this->shipment_services_in_request_array[$parcel_count];
                                    }
                                } else {
                                    $group['shipping_service'] = $service_method_id;
                                    if (empty($group['shipping_service'])) {
                                        $group['shipping_service'] = $this->default_service;
                                    }
                                }

                                $auto_generate_label_status = get_option('auto_generate_label_on_auspost_elex', false);
                                if ($auto_generate_label_status) {
                                    $group['shipping_service'] = $service_method_id;
                                    if (empty($group['shipping_service'])) {
                                        $group['shipping_service'] = $this->default_service;
                                    }
                                }
                            }

                            if (!empty($package->packed) && is_array($package->packed)) {
                                foreach ($package->packed as $packed) {
                                    $group['packed_products'][] = $packed->get_meta('data');
                                }
                            }

                            $to_ship[] = $group;

                            $parcel_count++;
                        }
                    }
                }
                return $to_ship;
            }
        }
    }

    /*
     * function to set error notices on database
     */

    private function set_error_notices($error_desc)
    {
        $option_name = 'wf_create_shipment_error';
        if (get_option($option_name) !== false) {
            update_option($option_name, $error_desc);
        } else {
            $deprecated = null;
            $autoload = 'no';
            add_option($option_name, $error_desc, $deprecated, $autoload);
        }
    }

    /**
     * function to set box packing error notices on database
     */

    private function set_boxpacking_error_notices($error_desc)
    {
        $option_name = 'wf_create_boxpacking_error';
        if (get_option($option_name) !== false) {
            update_option($option_name, $error_desc);
        } else {
            $deprecated = null;
            $autoload = 'no';
            add_option($option_name, $error_desc, $deprecated, $autoload);
        }
    }

    /**
     * function to obtain shipping method id for label creation
     * @param array auspost service types and ids
     * @param string shipping service name selected for label creation
     * @param object woocommerce order
     * @return atring shipping method id
     */
    private function get_selected_shipping_service_id($postage_products_type_and_product_ids, $serviceName, $order)
    {
        $label_shipping_method_id = '';
        $postage_service_code = '';
        $shipping_country = wf_get_order_shipping_country($order);

        foreach ($postage_products_type_and_product_ids as $postage_products_type_and_product_id_key => $postage_products_type_and_product_id_value) {
            if ($serviceName == $postage_products_type_and_product_id_key) {
                $postage_service_code = $postage_products_type_and_product_id_value;
            } else if ($serviceName == $postage_products_type_and_product_id_value) {
                $postage_service_code = $postage_products_type_and_product_id_value;
            }
        }
        if (!empty($postage_service_code)) {
            $label_shipping_method_id = $postage_service_code;
            update_post_meta($this->wf_get_order_id($order), 'wf_aus_label_shipment_id', $label_shipping_method_id);
        } else {
            /* If customer has not selected any service while placing order */
            if ($this->rate_type == 'startrack') {
                if ($shipping_country == 'AU') {
                    $default_starTrack_shipment_service = (isset($this->settings['starTrack_default_shipment_service']) && ($this->settings['starTrack_default_shipment_service'] != 'none')) ? $this->settings['starTrack_default_shipment_service'] : 'none';

                    if ($default_starTrack_shipment_service != 'none') {
                        update_option("default_auspost_shipment_service_selected", 'yes');
                        $default_starTrack_shipment_service = str_replace('startrack', '', $default_starTrack_shipment_service);
                        if (isset($postage_products_type_and_product_ids[$default_starTrack_shipment_service])) {
                            $label_shipping_method_id = $postage_products_type_and_product_ids[$default_starTrack_shipment_service];
                        }
                        update_post_meta($this->wf_get_order_id($order), 'wf_aus_label_shipment_id', $label_shipping_method_id);
                    } else {
                        /* If the customer has not set any service as default */
                        update_option("default_auspost_shipment_service_selected", 'no');
                        $orders_with_no_default_shipment_service_auspost = $this->wf_get_order_id($order);
                    }
                }
            } else {
                if ($shipping_country == 'AU') {
                    $default_auspost_domestic_shipment_service = (isset($this->settings['auspost_default_domestic_shipment_service']) && ($this->settings['auspost_default_domestic_shipment_service'] != 'none')) ? $this->settings['auspost_default_domestic_shipment_service'] : 'none';

                    if ($default_auspost_domestic_shipment_service != 'none') {
                        update_option("default_auspost_shipment_service_selected", 'yes');
                        $label_shipping_method_id = $default_auspost_domestic_shipment_service;
                        update_post_meta($this->wf_get_order_id($order), 'wf_aus_label_shipment_id', $label_shipping_method_id);
                    } else {
                        /* If the customer has not set any service as default */
                        update_option("default_auspost_shipment_service_selected", 'no');
                        $orders_with_no_default_shipment_service_auspost = $this->wf_get_order_id($order);
                    }
                } else {
                    $default_auspost_international_shipment_service = (isset($this->settings['auspost_default_international_shipment_service']) && ($this->settings['auspost_default_international_shipment_service'] != 'none')) ? $this->settings['auspost_default_international_shipment_service'] : 'none';
                    if ($default_auspost_international_shipment_service != 'none') {
                        update_option("default_auspost_shipment_service_selected", 'yes');
                        $label_shipping_method_id = $default_auspost_international_shipment_service;
                        update_post_meta($this->wf_get_order_id($order), 'wf_aus_label_shipment_id', $label_shipping_method_id);
                    } else {
                        /* If the customer has not set any service as default */
                        update_option("default_auspost_shipment_service_selected", 'no');
                        $orders_with_no_default_shipment_service_auspost = $this->wf_get_order_id($order);
                    }
                }
            }
            update_post_meta($this->wf_get_order_id($order), 'wf_aus_label_shipment_id', $label_shipping_method_id);
        }

        $stored_order_ids_with_no_default_shipping_services = get_option('orders_with_no_default_shipment_service_auspost');
        if (!empty($orders_with_no_default_shipment_service_auspost)) {
            $stored_order_ids_with_no_default_shipping_services .= $orders_with_no_default_shipment_service_auspost;
        }
        update_option('orders_with_no_default_shipment_service_auspost', $stored_order_ids_with_no_default_shipping_services);

        return $label_shipping_method_id;
    }

    public function print_shipping_label($order, $shipment_id)
    {

        $order_id = $this->wf_get_order_id($order);
        $label_request_id = get_post_meta($order_id, 'wf_woo_australiapost_labelId' . $shipment_id, true);
        $service_label_url = 'https://' . self::API_HOST . self::API_BASE_URL . 'labels/';
        $label_get_url = $service_label_url . $label_request_id;

        $api_password = $this->api_pwd;
        $api_account_number = $this->api_account_no;
        $api_key =  $this->api_key;
        $startrack_shipment_ids = get_post_meta($order_id, "elex_auspost_startrack_shipment_ids", true);
        $shipment_id = isset($_GET['shipment_id']) ? $_GET['shipment_id'] : $shipment_id;

        if ((is_array($startrack_shipment_ids) && !empty($startrack_shipment_ids)) && in_array($shipment_id, $startrack_shipment_ids)) {
            $api_password = $this->api_pwd_startrack;
            $api_account_number = $this->api_account_no_startrack;
            $api_key = $this->api_key_starTrack;
        }
        // Compatibility of Australia Post with ELEX Multivendor Addon 
        $vendor_shipment = get_option('elex_australia_post_shipment_details') ? get_option('elex_australia_post_shipment_details') : array();
        if ($this->vendor_check && $this->vedor_api_key_enable && isset($vendor_shipment[$shipment_id]) && isset($vendor_shipment[$shipment_id]['seller_id'])) {
            $vendor_user_id = $vendor_shipment[$shipment_id]['seller_id'];
            $api_password = get_the_author_meta('vendor_elex_australia_post_api_password', $vendor_user_id);
            $api_account_number = get_the_author_meta('vendor_elex_australia_post_account_number', $vendor_user_id);
            if ((is_array($startrack_shipment_ids) && !empty($startrack_shipment_ids)) && in_array($shipment_id, $startrack_shipment_ids)) {
                $api_password = get_the_author_meta('vendor_elex_australia_post_startrack_api_password', $vendor_user_id);
                $api_account_number = get_the_author_meta('vendor_elex_australia_post_startrack_account_number', $vendor_user_id);
            }
            $rqs_headers = array(
                'Authorization' => 'Basic ' . base64_encode(get_the_author_meta('vendor_elex_australia_post_api_key', $vendor_user_id) . ':' . $api_password),
                'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
                'Account-Number' => $api_account_number
            );
        } else {
            $rqs_headers = array(
                'Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_password),
                'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
                'Account-Number' => $api_account_number
            );
        }
        $res = wp_remote_request($label_get_url, array(
            'headers' => $rqs_headers
        ));
        if (is_wp_error($res)) {
            $error_string = $res->get_error_message();
            $this->set_error_notices($error_string);
            if ($this->debug) {
                update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', $error_string);
            }
            return;
        }

        $response_array = isset($res['body']) ? json_decode($res['body']) : array();

        if (!empty($response_array->errors)) {
            $this->set_error_notices($response_array->errors[0]->message);
            if ($this->debug) {
                update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', $response_array->errors[0]->message);
            }
            return;
        }

        if (isset($response_array->labels)) {
            $label_uri = $response_array->labels[0]->url;
            update_post_meta($order_id, 'wf_woo_australiapost_labelURI' . $shipment_id, $label_uri);
        }
        return;
    }

    public function wf_create_shipment($order)
    {
        /*Shipment label printing is only for contracted accounts*/
        if (!$this->contracted_rates) {
            return false;
        }


        $order_id = $this->wf_get_order_id($order);
        $extra_cover_status = get_post_meta($order_id, 'extra_cover_opted_auspost_elex', true);
        $shipping_country = wf_get_order_shipping_country($order);

        update_option("request_to_create_shipment", true);

        $all_auspost_postage_products = get_option('all_auspost_postage_products_auspost_elex');

        if (!$this->is_request_bulk_shipment && (isset($_GET['weight']) && isset($_GET['height']) && isset($_GET['width']) && isset($_GET['length']))) {
            $this->titles_in_request_array = $this->return_package_data_from_request($_GET['title']);
            $this->weights_in_request_array = $this->return_package_data_from_request($_GET['weight']);
            $this->lengths_in_request_array = $this->return_package_data_from_request($_GET['length']);
            $this->widths_in_request_array = $this->return_package_data_from_request($_GET['width']);
            $this->heights_in_request_array = $this->return_package_data_from_request($_GET['height']);
            $this->shipment_services_in_request_array = $this->return_package_data_from_request($_GET['shipping_service']);
        } else {
            $this->titles_in_request_array = array();
        }

        $additional_packages = array();
        for ($i = 0; $i < count($this->titles_in_request_array); $i++) {
            if ($this->titles_in_request_array[$i] == 'Additional Package') {
                $additional_packages[] = array(
                    'Name' => $this->titles_in_request_array[$i],
                    'Weight' => array(
                        'Value' => $this->weights_in_request_array[$i],
                        'Units' => $this->weight_unit
                    ),
                    'Dimensions' => array(
                        'Length' => $this->lengths_in_request_array[$i],
                        'Width'  => $this->widths_in_request_array[$i],
                        'Height' => $this->heights_in_request_array[$i],
                        'Units'  => $this->dimension_unit
                    ),
                    'shipping_service' => $this->shipment_services_in_request_array[$i]
                );
            }
        }
        $convention_shipment_service_requests_array = array();
        $failed_shipment_packages_stored = get_post_meta($order_id, 'failed_create_shipment_packages_auspost_elex', true);
        $failed_shipment_packages_stored = array();
        $service_count = 0;
        if (!empty($failed_shipment_packages_stored)) {
            foreach ($failed_shipment_packages_stored as $failed_package_order) {
                $convention_shipment_service_requests_array[$failed_package_order] = $this->shipment_services_in_request_array[$service_count];
                $service_count++;
            }

            $this->shipment_services_in_request_array = $convention_shipment_service_requests_array;
        }

        if ($this->is_request_bulk_shipment) {
            if ($shipping_country == 'AU') {
                $this->default_service = (isset($this->settings['auspost_default_domestic_shipment_service']) && ($this->settings['auspost_default_domestic_shipment_service'] != 'none')) ? $this->settings['auspost_default_domestic_shipment_service'] : 'none';
            } else {
                $this->default_service = (isset($this->settings['auspost_default_international_shipment_service']) && ($this->settings['auspost_default_international_shipment_service'] != 'none')) ? $this->settings['auspost_default_international_shipment_service'] : 'none';
            }
            $selected_service_customer = $this->wf_get_shipping_service($order, false, 'bulk_label');
            foreach ($this->settings['services'] as $key => $value) {
                if ($value['name'] == $selected_service_customer) {
                    $this->default_service = $key;
                }
            }
        } else if ($this->is_request_bulk_startrack_shipment) {
            if ($this->rate_type == 'startrack') {
                $this->default_service = (isset($this->settings['starTrack_default_shipment_service']) && ($this->settings['starTrack_default_shipment_service'] != 'none')) ? $this->settings['starTrack_default_shipment_service'] : 'none';;
                $this->default_service = str_replace('startrack', '', $this->default_service);
            }
            $selected_service_customer = $this->wf_get_shipping_service($order, false, 'bulk_label');

            foreach ($this->settings['startrack_services'] as $key => $value) {
                if ($value['name'] == $selected_service_customer) {
                    $this->default_service = $key;
                }
            }
        }



        $orders_with_no_default_shipment_service_auspost = '';

        $service_base_url = 'https://' . self::API_HOST . self::API_BASE_URL;

        if ($this->contracted_api_mode == 'live') {
            $service_base_url = str_replace('test/', '', $service_base_url);
        }

        $label_uri = isset($_GET['shipment_id']) ? get_post_meta($order_id, 'wf_woo_australiapost_labelURI' . $_GET['shipment_id'], true) : false;
        if (!$label_uri) {
            $label_request_id = isset($_GET['shipment_id']) ? get_post_meta($order_id, 'wf_woo_australiapost_labelId' . $_GET['shipment_id'], true) : false;
            if ($label_request_id) {
                $this->print_shipping_label($order, $_GET['shipment_id']);
                delete_option('create_shipment_for_startrack');
                wp_redirect(admin_url('/post.php?post=' . $order_id . '&action=edit'));
                exit;
            }
        }

        if (!isset($_GET['shipment_id'])) {
            $selected_service_type = '';
            /* Obtaining service selected for the shipment */
            $serviceName = $this->wf_get_shipping_service($order, true);

            /* Obtaining service overridden by the user in the meta-box */
            $postage_products_type_and_product_ids = array();
            if (is_array($all_auspost_postage_products) && !empty($all_auspost_postage_products)) {
                foreach ($all_auspost_postage_products as $all_auspost_postage_product) {
                    $postage_products_type_and_product_ids[$all_auspost_postage_product['type']] = $all_auspost_postage_product['product_id'];
                }
            }

            $label_shipping_method_id = $this->get_selected_shipping_service_id($postage_products_type_and_product_ids, $serviceName, $order);
            $available_shipping_services = array_merge($this->settings['services'], $this->settings['startrack_services']);
            if (array_key_exists($label_shipping_method_id, $available_shipping_services)) {
                if ($available_shipping_services[$label_shipping_method_id]['extra_cover']) {
                    $extra_cover_status = true;
                }
            }

            $service_name = '';

            $order_items = $order->get_items();
            $line_items = array();

            $from_weight_unit = '';
            if ($this->weight_unit != 'kg') {
                $from_weight_unit = $this->weight_unit;
            }

            $from_dimension_unit = '';
            if ($this->dimension_unit != 'cm') {
                $from_dimension_unit = $this->dimension_unit;
            }

            $package_requests = array();
            if ($this->packing_method == 'weight') {
                $package_requests = $this->weight_based_packing($order);
            } elseif ($this->packing_method == 'box_packing') {
                $package_requests = $this->box_packing($order);
            } else {
                $package_requests = $this->per_item_packing($order);
            }
            $package_index = 0;
            $package_commercial_value = 'false';
            $desc_for_other = '';
            $auspost_default_shipment_service_domestic = isset($this->settings['auspost_default_domestic_shipment_service']) ? $this->settings['auspost_default_domestic_shipment_service'] : 'none';
            $auspost_default_shipment_service_international = isset($this->settings['auspost_default_international_shipment_service']) ? $this->settings['auspost_default_international_shipment_service'] : 'none';
            $starTrack_default_shipment_service = (isset($this->settings['starTrack_default_shipment_service']) && ($this->settings['starTrack_default_shipment_service'] != 'none')) ? $this->settings['starTrack_default_shipment_service'] : 'none';

            if (is_array($package_requests)) {
                $this->is_request_bulk_startrack_shipment = get_option('create_bulk_orders_shipment_auspost_startrack', false);
                $this->is_request_bulk_shipment = get_option('create_bulk_orders_shipment_auspost', false);
                $postage_products_service_options = get_option('elex_auspost_contracted_postage_products_feature_and_options', true);
                if ($shipping_country == 'AU') {
                    if (!empty($additional_packages)) {
                        $package_requests = array_merge($package_requests, $additional_packages);
                    }
                    $line_items_with_services_same = array();
                    foreach ($package_requests as $key => $package_request) {
                        $package_dangerous_goods_data = '';
                        $all_services = $this->get_postage_product_data();
                        $startrack_services = $all_services['startrack_eligible_postage_products'];
                        $auspost_services = $all_services['auspost_eligible_postage_products'];
                        $startrack_service_ids = array();
                        foreach ($startrack_services as $startrack_service) {
                            $startrack_service_ids[] = $startrack_service['product_id'];
                        }

                        $create_shipment_service = '';
                        if (isset($package_request['shipping_service']) && !empty($package_request['shipping_service'])) {
                            $create_shipment_service = $this->get_selected_shipping_service_id($postage_products_type_and_product_ids, $package_request['shipping_service'], $order);
                            if (array_key_exists($create_shipment_service, $available_shipping_services)) {
                                if ($available_shipping_services[$create_shipment_service]['extra_cover']) {
                                    $extra_cover_status = true;
                                }

                            }
                            if (in_array($create_shipment_service, $startrack_service_ids)) {
                                $selected_service_type = 'StarTrack';
                            } else {
                                foreach ($auspost_services as $auspost_service) {
                                    if ($auspost_service['product_id'] == $create_shipment_service) {
                                        if ($auspost_service['group'] == 'Express Post') {
                                            $selected_service_type = 'Express Post';
                                            break;
                                        } else if ($auspost_service['group'] == 'Parcel Post') {
                                            $selected_service_type = 'Parcel Post';
                                            break;
                                        }
                                    }
                                }
                            }
                        } else {
                            if ($this->is_request_bulk_startrack_shipment) {
                                $selected_service_type = 'StarTrack';
                                foreach ($startrack_services as $startrack_service) {
                                    if (!empty($label_shipping_method_id) && ($startrack_service['product_id'] == $label_shipping_method_id)) {
                                        $create_shipment_service = $label_shipping_method_id;
                                        break;
                                    }
                                }

                                if (empty($create_shipment_service)) {
                                    $create_shipment_service = str_replace('startrack', '', $starTrack_default_shipment_service);
                                }
                            } else if ($this->is_request_bulk_shipment) {
                                $create_shipment_service = !empty($label_shipping_method_id) ? $label_shipping_method_id : $auspost_default_shipment_service_domestic;
                                $request_from_bulk_label_addon = get_option('request_from_bulk_label_addon_auspost_elex', false);
                                if ($request_from_bulk_label_addon) {
                                    $serviceName = $this->wf_get_shipping_service($order, false);
                                    $startrack_service_names = array();
                                    foreach ($startrack_services as $startrack_service) {
                                        $startrack_service_names[] = $startrack_service['type'];
                                    }

                                    if (in_array($serviceName, $startrack_service_names)) {
                                        foreach ($startrack_services as $startrack_service) {
                                            if ($startrack_service['type'] == $serviceName) {
                                                $create_shipment_service = $startrack_service['product_id'];
                                                $selected_service_type = 'StarTrack';
                                            }
                                        }
                                    } else {
                                        foreach ($auspost_services as $auspost_service) {
                                            if ($auspost_service['product_id'] == $create_shipment_service) {
                                                if ($auspost_service['group'] == 'Express Post') {
                                                    $selected_service_type = 'Express Post';
                                                    break;
                                                } else if ($auspost_service['group'] == 'Parcel Post') {
                                                    $selected_service_type = 'Parcel Post';
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if ($package_request['Weight']['Units'] != 'kg') {
                            //$package_request['Weight']['Value'] = wc_get_weight($package_request['Weight']['Value'], 'kg', $this->weight_unit);
                        }

                        if (isset($package_request['Dimensions']['Units']) && $package_request['Dimensions']['Units'] != 'cm') {

                            $package_request['Dimensions']['Length'] = wc_get_dimension($package_request['Dimensions']['Length'], 'cm', $package_request['Dimensions']['Units']);
                            $package_request['Dimensions']['Width'] = wc_get_dimension($package_request['Dimensions']['Width'], 'cm', $package_request['Dimensions']['Units']);
                            $package_request['Dimensions']['Height'] = wc_get_dimension($package_request['Dimensions']['Height'], 'cm', $package_request['Dimensions']['Units']);
                        }

                        if ($selected_service_type == 'StarTrack' && isset($package_request['cubic_volume']) && $package_request['cubic_volume'] > 0 && $this->packing_method == 'weight') {
                            $line_items = array(
                                'item_reference'        => $package_request['Name'],
                                'product_id'            => $create_shipment_service,
                                'weight'                => $package_request['Weight']['Value'] < 0.01 ? 0.01 : round($package_request['Weight']['Value'], 3),
                                'features'              => $extra_cover_status ? array("TRANSIT_COVER" => array("attributes" => array("cover_amount" => round($package_request['InsuredValue']['Amount'], 2)))) : '',
                                'packaging_type'        => !empty($package_request['pack_type']) ? $package_request['pack_type'] : 'ITM',
                                'cubic_volume'          => (round($package_request['cubic_volume'], 3) > 0) ? round($package_request['cubic_volume'], 3) : 0.001,
                            );
                        } else {
                            $line_items = array(
                                'item_reference'        => $package_request['Name'],
                                'product_id'            => $create_shipment_service,
                                'length'                => round($package_request['Dimensions']['Length'], 1),
                                'width'                 => round($package_request['Dimensions']['Width'], 1),
                                'height'                => round($package_request['Dimensions']['Height'], 1),
                                'weight'                => $package_request['Weight']['Value'] < 0.01 ? 0.01 : round($package_request['Weight']['Value'], 3),
                                'features'              => $extra_cover_status ? array("TRANSIT_COVER" => array("attributes" => array("cover_amount" => round($package_request['InsuredValue']['Amount'], 2)))) : '',
                                'packaging_type'        => !empty($package_request['pack_type']) ? $package_request['pack_type'] : 'ITM',
                                'safe_drop_enabled'     => False,
                                'allow_partial_delivery'=> False,
                                'authority_to_leave'    => True,
                            );
                            if( is_array($postage_products_service_options)  && !empty($postage_products_service_options)  && isset($postage_products_service_options[$create_shipment_service]) && isset($postage_products_service_options[$create_shipment_service]['options']) && is_array($postage_products_service_options[$create_shipment_service]['options']) &&  !empty($postage_products_service_options[$create_shipment_service]['options']) ){
                                $shipement_service_options = $postage_products_service_options[$create_shipment_service]['options'];
                                if($shipement_service_options['authority_to_leave_option']){
                                    if (isset($package_request['age_check']) && $package_request['age_check'] == 'yes') {
                                        $line_items['authority_to_leave'] = False;
                                    }else if( is_array($available_shipping_services) && !empty($available_shipping_services) && isset($available_shipping_services[$create_shipment_service]) && is_array($available_shipping_services[$create_shipment_service]) ){
                                        if(!( isset($available_shipping_services[$create_shipment_service]['authority_to_leave']) && $available_shipping_services[$create_shipment_service]['authority_to_leave'])){
                                            $line_items['authority_to_leave'] = False;
                                        }
                                        if(isset($available_shipping_services[$create_shipment_service]['allow_partial_delivery']) && $available_shipping_services[$create_shipment_service]['allow_partial_delivery']){
                                            $line_items['allow_partial_delivery'] = True;
                                        }
                                    }else{
                                        $line_items['authority_to_leave'] = False;
                                    }
                                }
                            }
                        }
                        
                        // Compatibility of Australia Post with ELEX Multivendor Addon 
                        if ($this->vendor_check) {
                            if (isset($package_request['seller_id'])) {
                                $line_items['seller_id'] = $package_request['seller_id'];
                            }
                            if (isset($package_request['origin'])) {
                                $line_items['origin'] = $package_request['origin'];
                            }
                        }


                        if (isset($package_request['dangerous_goods_data']['dangerous_goods_status_parcel'])) {
                            $line_items['contains_dangerous_goods'] = true;
                        }

                        if (empty($selected_service_type)) {
                            if (isset($package_request['startrack_service_selected'])) {
                                $selected_service_type = 'StarTrack';
                            } else {
                                $selected_service_type = 'Express Post';
                            }
                        }

                        
                        $current_shipment_dangerous_goods_status = get_option('current_shipment_contains_dangerous_goods_auspost_elex');

                        /* If one item belongs to dangerous goods, the transportable_by_air should set to true for all items */
                        if ($current_shipment_dangerous_goods_status) {
                            $line_items['contains_dangerous_goods'] = true;
                            $line_items['transportable_by_air'] = true;

                            if (isset($package_request['dangerous_goods_data']) && !empty($package_request['dangerous_goods_data'])) {
                                foreach ($package_request['dangerous_goods_data'] as $dangerous_goods_data) {
                                    if (isset($dangerous_goods_data['express']) && isset($dangerous_goods_data['express']['un_number_type'])) {
                                        $line_items['dangerous_goods_declaration'] = $dangerous_goods_data['express']['un_number_type'];
                                    }
                                }
                            }

                            if (!isset($line_items['dangerous_goods_declaration'])) {
                                $line_items['dangerous_goods_declaration'] = 'UN2910_radioactive_excepted_limited_qty';
                            }
                        }


                        $package_dangerous_goods_data = array();
                        if ($selected_service_type === 'StarTrack') {
                            if (isset($this->settings['enable_dangerous_goods_configuration_startrack']) && $this->settings['enable_dangerous_goods_configuration_startrack'] == 'yes') {
                                if (isset($package_request['dangerous_goods_data']) && is_array($package_request['dangerous_goods_data'])) {
                                    foreach ($package_request['dangerous_goods_data'] as $dangerous_goods_data) {
                                        $package_dangerous_goods_data = array(
                                            'un_number' =>  $dangerous_goods_data['startrack']['un_number'],
                                            'technical_name' =>  $dangerous_goods_data['startrack']['technical_name'],
                                            'class_division' =>  $dangerous_goods_data['startrack']['class_division'],
                                            'subsidiary_risk' =>  $dangerous_goods_data['startrack']['subsidiary_risk'],
                                            'packing_group_designator' =>  $dangerous_goods_data['startrack']['packing_group_designator'],
                                            'outer_packaging_type' =>  $dangerous_goods_data['startrack']['outer_packaging_type'],
                                            'outer_packaging_quantity' =>  $dangerous_goods_data['startrack']['outer_packaging_quantity'],
                                            'net_weight' =>  $dangerous_goods_data['startrack']['net_weight'] < 1 ? 1 : $dangerous_goods_data['startrack']['net_weight'],
                                        );
                                    }

                                    $line_items['contains_dangerous_goods'] = true;
                                }
                            }
                        }

                        if ($line_items['features'] == '') {
                            unset($line_items['features']);
                        }
                        if ($this->rate_type != 'startrack') {
                            unset($line_items['packaging_type']);
                        }

                        /* Grouping packages with same selected shipping service */
                        if (isset($line_items_with_services_same[$line_items['product_id']])) {
                            $line_items_array_length = sizeof($line_items_with_services_same[$line_items['product_id']]);
                            $line_items_with_services_same[$line_items['product_id']][$line_items_array_length]['line_items'] = $line_items;
                            $line_items_with_services_same[$line_items['product_id']][$line_items_array_length]['shipping_service_type'] = $selected_service_type;
                            if ($selected_service_type == 'StarTrack' && !empty($package_dangerous_goods_data)) {
                                $line_items_with_services_same[$line_items['product_id']][$line_items_array_length]['package_dangerous_goods_data'] = $package_dangerous_goods_data;
                            }
                            $line_items_with_services_same[$line_items['product_id']][$line_items_array_length]['package_index'] = $package_index;
                        } else {
                            $line_items_with_services_same[$line_items['product_id']][0]['line_items'] = $line_items;
                            $line_items_with_services_same[$line_items['product_id']][0]['package_dangerous_goods_data'] = $package_dangerous_goods_data;
                            $line_items_with_services_same[$line_items['product_id']][0]['shipping_service_type'] = $selected_service_type;
                            $line_items_with_services_same[$line_items['product_id']][0]['package_index'] = $package_index;
                        }
                        $package_index++;
                    }
                    foreach ($line_items_with_services_same as $line_items_with_services_same_key => $line_items_with_services_same_value) {
                        $shipment_line_items = array();
                        $shipment_package_dangerous_goods_data = array();
                        $shipment_package_indicies = array();
                        $shipment_line_items_startrack = array();
                        $shipment_package_dangerous_goods_data_startrack = array();
                        $shipment_package_indicies_startrack = array();
                        foreach ($line_items_with_services_same_value as $line_items_with_services_same_value_element) {
                            if (isset($this->group_shipping) && $this->group_shipping == 'yes' && isset($line_items_with_services_same_value_element['line_items']['packaging_type']) && $line_items_with_services_same_value_element['line_items']['packaging_type'] == 'ITM' && $line_items_with_services_same_value_element['line_items']['product_id'] != "FPP") {
                                $this->group_shipping_enabled = true;
                            }
                            $selected_service_type = $line_items_with_services_same_value_element['shipping_service_type'];
                            if ($line_items_with_services_same_value_element['shipping_service_type'] == 'StarTrack' && !$this->group_shipping_enabled) {
                                update_option('create_shipment_for_startrack', true);
                                $shipment_package_dangerous_goods_data_startrack = NULL;
                                $shipment_package_indicies_startrack = NULL;
                                if (isset($line_items_with_services_same_value_element['package_dangerous_goods_data']) && !empty($line_items_with_services_same_value_element['package_dangerous_goods_data'])) {
                                    $shipment_package_dangerous_goods_data_startrack = $line_items_with_services_same_value_element['package_dangerous_goods_data'];
                                }

                                if (isset($line_items_with_services_same_value_element['package_index'])) {
                                    $shipment_package_indicies_startrack = $line_items_with_services_same_value_element['package_index'];
                                }
                                $shipment_id = $this->create_shipment_for_package($order, $line_items_with_services_same_value_element['line_items'], $service_base_url, $line_items_with_services_same_key, $shipment_package_indicies_startrack, $postage_products_type_and_product_ids, $shipment_package_dangerous_goods_data_startrack, $selected_service_type);

                                if (!empty($shipment_id)) {
                                    $this->generate_label_package($order, $selected_service_type, $shipment_id, $service_base_url);
                                    delete_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage');
                                }
                                delete_option('create_shipment_for_startrack');
                            } elseif ($line_items_with_services_same_value_element['shipping_service_type'] == 'StarTrack' && $this->group_shipping_enabled) {
                                $shipment_line_items_startrack[] = $line_items_with_services_same_value_element['line_items'];
                                if (isset($line_items_with_services_same_value_element['package_dangerous_goods_data']) && !empty($line_items_with_services_same_value_element['package_dangerous_goods_data'])) {
                                    $shipment_package_dangerous_goods_data_startrack[] = $line_items_with_services_same_value_element['package_dangerous_goods_data'];
                                }

                                if (isset($line_items_with_services_same_value_element['package_index'])) {
                                    $shipment_package_indicies_startrack[] = $line_items_with_services_same_value_element['package_index'];
                                }
                            } elseif ($this->vendor_check) {
                                // Compatibility of Australia Post with ELEX Multivendor Addon 
                                $shipment_package_dangerous_goods = NULL;
                                $shipment_package_index = NULL;
                                if (isset($line_items_with_services_same_value_element['package_dangerous_goods_data']) && !empty($line_items_with_services_same_value_element['package_dangerous_goods_data'])) {
                                    $shipment_package_dangerous_goods = $line_items_with_services_same_value_element['package_dangerous_goods_data'];
                                }

                                if (isset($line_items_with_services_same_value_element['package_index'])) {
                                    $shipment_package_index = $line_items_with_services_same_value_element['package_index'];
                                }
                                $shipment_id = $this->create_shipment_for_package($order, $line_items_with_services_same_value_element['line_items'], $service_base_url, $line_items_with_services_same_key, $shipment_package_index, $postage_products_type_and_product_ids, $shipment_package_dangerous_goods, $selected_service_type);

                                if (!empty($shipment_id)) {
                                    $this->generate_label_package($order, $selected_service_type, $shipment_id, $service_base_url, $line_items_with_services_same_value_element['line_items']['seller_id']);
                                    delete_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage');
                                }
                            } else {
                                $shipment_line_items[] = $line_items_with_services_same_value_element['line_items'];
                                if (isset($line_items_with_services_same_value_element['package_dangerous_goods_data']) && !empty($line_items_with_services_same_value_element['package_dangerous_goods_data'])) {
                                    $shipment_package_dangerous_goods_data[] = $line_items_with_services_same_value_element['package_dangerous_goods_data'];
                                }

                                if (isset($line_items_with_services_same_value_element['package_index'])) {
                                    $shipment_package_indicies[] = $line_items_with_services_same_value_element['package_index'];
                                }
                            }
                        }
                        if (!empty($shipment_line_items)) {

                            $shipment_id = $this->create_shipment_for_package($order, $shipment_line_items, $service_base_url, $line_items_with_services_same_key, $shipment_package_indicies, $postage_products_type_and_product_ids, $shipment_package_dangerous_goods_data, $selected_service_type);

                            if (!empty($shipment_id)) {
                                $this->generate_label_package($order, $selected_service_type, $shipment_id, $service_base_url);
                                delete_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage');
                            }
                        }
                        if (!empty($shipment_line_items_startrack)) {
                            update_option('create_shipment_for_startrack', true);
                            $shipment_id = $this->create_shipment_for_package($order, $shipment_line_items_startrack, $service_base_url, $line_items_with_services_same_key, $shipment_package_indicies_startrack, $postage_products_type_and_product_ids, $shipment_package_dangerous_goods_data_startrack, $selected_service_type);

                            if (!empty($shipment_id)) {
                                $this->generate_label_package($order, $selected_service_type, $shipment_id, $service_base_url);
                                delete_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage');
                            }
                            delete_option('create_shipment_for_startrack');
                        }
                    }
                } else {

                    foreach ($package_requests as $key => $package_request) {
                        if (!empty($this->order_package_categories_arr[$package_index])) {
                            if ($this->order_package_categories_arr[$package_index] == "OTHER") {
                                $package_commercial_value = 'true';
                                if (!empty($this->order_desc_for_other_category_arr[$package_index])) {
                                    $desc_for_other = $this->order_desc_for_other_category_arr[$package_index];
                                } else {
                                    $desc_for_other = 'Sale';
                                }
                            }
                        }

                        $create_shipment_service = '';
                        if (isset($package_request['shipping_service'])) {
                            $create_shipment_service = $package_request['shipping_service'];
                        } else if ($this->is_request_bulk_shipment) {
                            $create_shipment_service = $auspost_default_shipment_service_international;
                        }

                        $extra_cover_amount = round($package_request['InsuredValue']['Amount'], 2);
                        if (isset($package_request['Dimensions']['Units']) && $package_request['Dimensions']['Units'] != 'cm') {

                            $package_request['Dimensions']['Length'] = wc_get_dimension($package_request['Dimensions']['Length'], 'cm', $package_request['Dimensions']['Units']);
                            $package_request['Dimensions']['Width'] = wc_get_dimension($package_request['Dimensions']['Width'], 'cm', $package_request['Dimensions']['Units']);
                            $package_request['Dimensions']['Height'] = wc_get_dimension($package_request['Dimensions']['Height'], 'cm', $package_request['Dimensions']['Units']);
                        }

                        $line_items = array(
                            'item_reference'        => $package_request['Name'],
                            'product_id'            => $create_shipment_service,
                            'length'                => round($package_request['Dimensions']['Length'], 1),
                            'width'                 => round($package_request['Dimensions']['Width'], 1),
                            'height'                => round($package_request['Dimensions']['Height'], 1),
                            'weight'                => $package_request['Weight']['Value'] < 0.01 ? 0.01 : round($package_request['Weight']['Value'], 3),
                            'commercial_value'      => $package_commercial_value,
                            'classification_type'   => isset($this->order_package_categories_arr[$package_index]) ? $this->order_package_categories_arr[$package_index] : 'OTHER',
                            'features'              => $extra_cover_status ? array("TRANSIT_COVER" => array("attributes" => array("cover_amount" => $extra_cover_amount))) : '',
                            'description_of_other'  => !empty($desc_for_other) ? $desc_for_other : 'Sale',
                            'item_contents'         => $package_request['Item_contents']
                        );
                        if( is_array($postage_products_service_options)  && !empty($postage_products_service_options)  && isset($postage_products_service_options[$create_shipment_service]) && isset($postage_products_service_options[$create_shipment_service]['options']) && is_array($postage_products_service_options[$create_shipment_service]['options']) &&  !empty($postage_products_service_options[$create_shipment_service]['options']) ){
                            $shipement_service_options = $postage_products_service_options[$create_shipment_service]['options'];
                            if($shipement_service_options['signature_on_delivery_option']  ){
                                if (isset($package_request['age_check']) && $package_request['age_check'] == 'yes') {
                                    $line_items['options'] = array("signature_on_delivery_option" => "true");
                                }else if( is_array($available_shipping_services) && !empty($available_shipping_services) && isset($available_shipping_services[$create_shipment_service]) && is_array($available_shipping_services[$create_shipment_service]) ){
                                    if( isset($available_shipping_services[$create_shipment_service]['signature_on_delivery_option']) && $available_shipping_services[$create_shipment_service]['signature_on_delivery_option'] ){
                                        $line_items['options'] = array("signature_on_delivery_option" => "true");
                                    }else{
                                        $line_items['options'] = array("signature_on_delivery_option" => false);
                                    }   
                                }else{
                                    $line_items['options'] = array("signature_on_delivery_option" => "true");
                                }
                            }
                        }
                        if ($this->vendor_check) {
                            // Compatibility of Australia Post with ELEX Multivendor Addon 
                            if (isset($package_request['seller_id'])) {
                                $line_items['seller_id'] = $package_request['seller_id'];
                            }
                            if (isset($package_request['origin'])) {
                                $line_items['origin'] = $package_request['origin'];
                            }
                        }

                        if ($line_items['features'] == '') {
                            unset($line_items['features']);
                        }

                        if ($this->rate_type != 'startrack') {
                            unset($line_items['packaging_type']);
                        }

                        $shipment_id = $this->create_shipment_for_package($order, $line_items, $service_base_url, $create_shipment_service, $package_index);

                        if (!empty($shipment_id)) {
                            if ($this->vendor_check) {
                                $this->generate_label_package($order, $selected_service_type, $shipment_id, $service_base_url, $package_request['seller_id']);
                            } else {
                                $this->generate_label_package($order, $selected_service_type, $shipment_id, $service_base_url);
                            }
                        }

                        $package_index++;
                    }
                }
            }

            update_option("tracking_request_from_create_shipment", true);
            $admin_notice = '';
            // Shipment Tracking (Auto)
            if ($admin_notice != '') {
                WF_Tracking_Admin_AusPost::display_admin_notification_message($order_id, $admin_notice);
            }
        }

        return;
    }

    private function update_failed_shipment_packages($order_id, $package_index)
    {
        $failed_shipment_packages = get_post_meta($order_id, 'failed_create_shipment_packages_auspost_elex', true);
        if (!empty($failed_shipment_packages)) {
            $failed_shipment_packages[] = $package_index;
        } else {
            $failed_shipment_packages = array();
            $failed_shipment_packages[] = $package_index;
        }

        update_post_meta($order_id, 'failed_create_shipment_packages_auspost_elex', $failed_shipment_packages);
        return;
    }

    private function create_shipment_for_package($order, $line_items, $service_base_url, $shipping_service, $package_index, $postage_products_type_and_product_ids = array(), $package_dangerous_goods_data = array(), $shipping_service_type = '')
    {
        if (empty($shipping_service))
            return;

        if ($this->vendor_check) {
            // Compatibility of Australia Post with ELEX Multivendor Addon 
            if ($line_items) {
                if (isset($line_items['seller_id']) && isset($line_items['origin'])) {
                    $vendor_address = $line_items['origin'];
                    $vendor_user_id = $line_items['seller_id'];
                    $shipper_name = $vendor_address['first_name'] . ' ' . $vendor_address['last_name'];
                    $shipper_address = $vendor_address['address_1'] . ', ' . $vendor_address['address_2'];
                    $shipper_suburb = $vendor_address['city'];
                    $shipper_postcode = $vendor_address['postcode'];
                    $shipper_email = $vendor_address['email'];
                    $shipper_phone_number = $vendor_address['phone'];
                    $shipper_state = $vendor_address['state'];
                } else {
                    $shipper_name = $this->shipper_name;
                    $shipper_address = $this->shipper_address;
                    $shipper_suburb = $this->shipper_suburb;
                    $shipper_state = $this->shipper_state;
                    $shipper_postcode = $this->shipper_postcode;
                    $shipper_email = $this->shipper_email;
                    $shipper_phone_number = $this->shipper_phone_number;
                }
                $order_id = $this->wf_get_order_id($order);
                $service_shipments_url = $service_base_url . 'shipments';
                $shipment_id = '';

                $sending_date = current_time('d-M-Y', 0);
                $order_customer_note = $order->get_customer_note();
                $order_number = $order->get_order_number();
                $sender_reference_1 = $this->rate_type ? "Order #" . $order_number : "Order #" . $order_number . "-" . $sending_date;
                if ($shipping_service_type == 'StarTrack' && strlen($sender_reference_1) >= 20) {
                    $sender_reference_1 = substr($sender_reference_1, 0, 19);
                } elseif (strlen($sender_reference_1) >= 50) {
                    $sender_reference_1 = substr($sender_reference_1, 0, 49);
                }
                if ($shipping_service_type == 'StarTrack' && strlen($this->ship_content) > 20) {
                    $this->ship_content = substr( $this->string_clean( $this->ship_content ), 0, 17) . '...';
                }
                $sender_reference_2 = $this->ship_content;
                $sender_references = array($sender_reference_1, $sender_reference_2);
                $order->shipping_address_1 = strlen($order->shipping_address_1) > 40 ? substr( $this->string_clean( $order->shipping_address_1 ), 0, 37) . '...' : $order->shipping_address_1;
                $order->shipping_address_2 = strlen($order->shipping_address_2) > 40 ? substr( $this->string_clean( $order->shipping_address_2 ), 0, 37) . '...' : $order->shipping_address_2;
                $items_data = $line_items;
                unset($items_data['seller_id']);
                unset($items_data['origin']);
                if(in_array($order->shipping_country, $this->european_union_countries) && isset($this->settings['import_reference_number']) && $this->settings['import_reference_number'] && !empty($this->settings['import_reference_number'])){
                    $line_items['import_reference_number'] = substr($this->settings['import_reference_number'], 0, 39);
                }
                $info = array(
                    'shipments' => array(
                        array(
                            'shipment_reference' => uniqid(),
                            'sender_references' => $sender_references, //customer references are deprecated since October 2018
                            'consolidate' => false,
                            'email_tracking_enabled' => $this->email_tracking,
                            'from' => array(
                                'name'  => $shipper_name,
                                'type'  => 'MERCHANT_LOCATION',
                                'lines' => array(
                                    $shipper_address,
                                ),
                                'suburb'    => $shipper_suburb,
                                'state'     => $shipper_state,
                                'postcode'  => $shipper_postcode,
                                'phone'     => $shipper_phone_number,
                                'email'     => $shipper_email,
                            ),
                            'to' => array(
                                'name'  => $order->shipping_first_name . ' ' . $order->shipping_last_name,
                                'business_name' => $order->shipping_company,
                                'lines' => array(
                                    $order->shipping_address_1,
                                    $order->shipping_address_2
                                ),
                                'suburb'    => $order->shipping_city,
                                'state'     => $order->shipping_state,
                                'country'   => $order->shipping_country,
                                'postcode'  => $order->shipping_postcode,
                                'phone'     => $order->billing_phone,
                                'email'     => $order->billing_email,
                                'delivery_instructions' => $order_customer_note
                            ),
                            'dangerous_goods' => $package_dangerous_goods_data,
                            'items' =>  $items_data,
                        ),
                    ),
                );

                if ($shipping_service_type != 'StarTrack') {
                    $info['shipments'][0]['to']['type'] = $this->cus_type;
                }

                if (empty($package_dangerous_goods_data) || (!empty($shipping_service_type) && $shipping_service_type != 'StarTrack')) {
                    unset($info['shipments'][0]['dangerous_goods']);
                }

                $this->debug(__('<b>Australia Post debug mode is on - to hide these messages, turn debug mode off in the <a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=wf_australia_post&subtab=general') . '">' . __('settings', 'wf-shipping-auspost') . '</a>.</b><br>', 'wf-shipping-auspost'));
                $this->debug('Australia Post Request <br> <pre>');
                $this->debug(print_r(json_encode($info, JSON_PRETTY_PRINT), true));
                $this->debug('</pre>');
                $request = array(
                    'seller_id'  => $vendor_user_id
                );
                $rqs_headers = $this->buildHttpHeaders($request);
                $res = wp_remote_post($service_shipments_url, array(
                    'method' => 'POST',
                    'httpversion' => '1.1',
                    'headers' => $rqs_headers,
                    'body' => json_encode($info)
                ));

                delete_option('current_shipment_contains_dangerous_goods_auspost_elex');

                if (is_wp_error($res)) {
                    $error_string = $res->get_error_message();
                    update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', $error_string);
                    $this->update_failed_shipment_packages($order_id, $package_index);
                    $this->set_error_notices($error_string);
                    if ($this->debug) {
                        echo "Error: <b>" . $error_string . "</b><br>";
                    }
                    return $shipment_id;
                }

                if (is_wp_error($res)) {
                    $error_string = $res->get_error_message();
                    $this->set_error_notices($error_string);
                    update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', $error_string);
                    if (!$this->is_request_bulk_shipment) {
                        if ($this->debug) {
                            echo "Error: <b>" . $error_string . "</b><br>";
                        }
                    }
                    $this->update_failed_shipment_packages($order_id, $package_index);
                    return $shipment_id;
                }

                $response_array = isset($res['body']) ? json_decode($res['body']) : array();
                if ($shipping_service_type == 'StarTrack') {
                    $this->debug('StarTrack (Australia Post) Response: <br><pre>');
                    $this->debug(print_r(json_encode($response_array, JSON_PRETTY_PRINT), true));
                    $this->debug('</pre>');
                } else {
                    $this->debug('Australia Post Response: <br><pre>');
                    $this->debug(print_r(json_encode($response_array, JSON_PRETTY_PRINT), true));
                    $this->debug('</pre>');
                }

                if (!empty($response_array->errors)) {
                    $this->set_error_notices($response_array->errors[0]->message);
                    update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', $shipping_service_type . $response_array->errors[0]->message);
                    if (!$this->is_request_bulk_shipment) {
                        if ($this->debug) {
                            echo "Error: <b>" . $response_array->errors[0]->message . "</b><br>";
                        }
                        $this->update_failed_shipment_packages($order_id, $package_index);
                    }
                    return $shipment_id;
                }

                if (!empty($response_array)) {
                    if (isset($response_array->items[0]->errors)) {
                        $this->set_error_notices($response_array->items[0]->errors[0]->message);
                        update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', $shipping_service_type . $response_array->errors[0]->message);
                        if (!$this->is_request_bulk_shipment) {
                            if ($this->debug) {
                                echo "Error: <b>" . $error_string . "</b><br>";
                            }
                        }
                        return $shipment_id;
                    }

                    $shipment_date = '';
                    $tracking_id_cs = '';
                    //shipments array
                    foreach ($response_array->shipments as $key => $shipments) {
                        $shipment_id = $shipments->shipment_id;
                        $shipment_date = substr($shipments->shipment_creation_date, 0, 10);
                    }

                    foreach ($response_array->shipments as $shipments) {
                        $items = $shipments->items;
                        foreach ($items as $item) {
                            $tracking_details = $item->tracking_details;
                            $tracking_id_cs .= $tracking_details->article_id;
                            $tracking_id_cs .= ',';
                        }
                    }
                    $tracking_id_cs = rtrim($tracking_id_cs, ',');

                    if (!class_exists('WF_Tracking_Admin_AusPost')) {
                        include('class-wf-tracking-admin.php');
                    }

                    $admin_notice = '';


                    try {
                        $admin_notice = WfTrackingUtil::update_tracking_data($order_id, $tracking_id_cs, 'australia-post', WF_Tracking_Admin_AusPost::SHIPMENT_SOURCE_KEY, WF_Tracking_Admin_AusPost::SHIPMENT_RESULT_KEY, $shipment_date);
                    } catch (Exception $e) {
                        $admin_notice = '';
                        // Do nothing.
                    }

                    $order_shipment_ids = get_post_meta($order_id, 'wf_woo_australiapost_shipmentId', true);
                    $order_startrack_shipment_ids = get_post_meta($order_id, 'elex_auspost_startrack_shipment_ids', true);
                    if ($shipping_service_type == 'StarTrack') {
                        if (!empty($order_startrack_shipment_ids)) {
                            $order_startrack_shipment_ids[] = $shipment_id;
                        } else {
                            $order_startrack_shipment_ids = array();
                            $order_startrack_shipment_ids[] = $shipment_id;
                        }
                        update_post_meta($order_id, 'elex_auspost_startrack_shipment_ids', $order_startrack_shipment_ids);
                    }
                    $vendor_shipment = get_option('elex_australia_post_shipment_details') ? get_option('elex_australia_post_shipment_details') : array();

                    if (empty($order_shipment_ids)) {
                        $order_shipment_ids = array();
                        $order_shipment_ids[] = $shipment_id;
                    } else {
                        $order_shipment_ids[] = $shipment_id;
                    }
                    $postage_products_data = $this->get_postage_product_data();
                    $postage_products_eligible = !empty($postage_products_data['auspost_eligible_postage_products']) ? $postage_products_data['auspost_eligible_postage_products'] : array();
                    $postage_products_eligible_startrack = !empty($postage_products_data['startrack_eligible_postage_products']) ? $postage_products_data['startrack_eligible_postage_products'] : array();
                    if (!empty($postage_products_eligible_startrack) || !empty($postage_products_eligible)) {
                        $all_eligible_postage_products = array_merge($postage_products_eligible, $postage_products_eligible_startrack);
                        $postage_products_type_and_product_ids = $this->get_postage_products_type_and_product_ids($all_eligible_postage_products);
                        $selected_service = array_search($shipping_service, $postage_products_type_and_product_ids);
                        update_post_meta($order_id, 'elex_auspost_shipping_service_name' . $shipment_id, $selected_service);
                    }
                    $vendor_shipment[$shipment_id] = array(
                        'seller_id' => $vendor_user_id,
                        'shipment_id' => $shipment_id,
                        'shipping_service_type' => $shipping_service_type,
                        'order_id' => $order_id
                    );
                    update_post_meta($order_id, 'wf_auspost_tracking_ids', $tracking_id_cs);
                    update_post_meta($order_id, 'elex_auspost_shipping_service_' . $shipment_id, $shipping_service);
                    update_post_meta($order_id, 'wf_woo_australiapost_shipmentId', $order_shipment_ids);
                    update_option('elex_australia_post_shipment_details', $vendor_shipment);
                    
                    if(!empty($shipment_id)){
                        do_action('elex_after_creating_shipment', $order_id );                        
                    }                    
                }
                return $shipment_id;
            }
        } else {
            $order_id = $this->wf_get_order_id($order);
            $service_shipments_url = $service_base_url . 'shipments';
            $shipment_id = '';

            $sending_date = current_time('d-M-Y', 0);
            $order_customer_note = $order->get_customer_note();
            $order_number = $order->get_order_number();
            $sender_reference_1 = $this->rate_type ? "Order #" . $order_number : "Order #" . $order_number . "-" . $sending_date;
            if ($shipping_service_type == 'StarTrack' && strlen($sender_reference_1) >= 20) {
                $sender_reference_1 = substr($sender_reference_1, 0, 19);
            } elseif (strlen($sender_reference_1) >= 50) {
                $sender_reference_1 = substr($sender_reference_1, 0, 49);
            }
            if ($shipping_service_type == 'StarTrack' && strlen($this->ship_content) > 20) {
                $this->ship_content = substr( $this->string_clean( $this->ship_content ), 0, 17) . '...';
            }
            $sender_reference_2 = $this->ship_content;
            $sender_references = array($sender_reference_1, $sender_reference_2);
            $order->shipping_address_1 = strlen($order->shipping_address_1) > 40 ? substr( $this->string_clean( $order->shipping_address_1 ), 0, 37) . '...' : $order->shipping_address_1;
            $order->shipping_address_2 = strlen($order->shipping_address_2) > 40 ? substr( $this->string_clean( $order->shipping_address_2 ), 0, 37) . '...' : $order->shipping_address_2;
            if(in_array($order->shipping_country, $this->european_union_countries) && isset($this->settings['import_reference_number']) && $this->settings['import_reference_number'] && !empty($this->settings['import_reference_number'])){
                $line_items['import_reference_number'] = substr($this->settings['import_reference_number'], 0, 39);
            }
            $info = array(
                'shipments' => array(
                    array(
                        'shipment_reference' => uniqid(),
                        'sender_references' => $sender_references, //customer references are deprecated since October 2018
                        'consolidate' => false,
                        'email_tracking_enabled' => $this->email_tracking,
                        'from' => array(
                            'name'  => $this->shipper_name,
                            'type'  => 'MERCHANT_LOCATION',
                            'lines' => array(
                                $this->shipper_address,
                            ),
                            'suburb'    => $this->shipper_suburb,
                            'state'     => $this->shipper_state,
                            'postcode'  => $this->shipper_postcode,
                            'phone'     => $this->shipper_phone_number,
                            'email'     => $this->shipper_email,
                        ),
                        'to' => array(
                            'name'  => $order->shipping_first_name . ' ' . $order->shipping_last_name,
                            'business_name' => $order->shipping_company,
                            'lines' => array(
                                $order->shipping_address_1,
                                $order->shipping_address_2
                            ),
                            'suburb'    => $order->shipping_city,
                            'state'     => $order->shipping_state,
                            'country'   => $order->shipping_country,
                            'postcode'  => $order->shipping_postcode,
                            'phone'     => $order->billing_phone,
                            'email'     => $order->billing_email,
                            'delivery_instructions' => $order_customer_note
                        ),
                        'dangerous_goods' => $package_dangerous_goods_data,
                        'items' =>  $line_items,
                    ),
                ),
            );
            if ($shipping_service_type != 'StarTrack') {
                $info['shipments'][0]['to']['type'] = $this->cus_type;
            }

            if (empty($package_dangerous_goods_data) || (!empty($shipping_service_type) && $shipping_service_type != 'StarTrack')) {
                unset($info['shipments'][0]['dangerous_goods']);
            }

            $this->debug(__('<b>Australia Post debug mode is on - to hide these messages, turn debug mode off in the <a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=wf_australia_post&subtab=general') . '">' . __('settings', 'wf-shipping-auspost') . '</a>.</b><br>', 'wf-shipping-auspost'));
            $this->debug('Australia Post Request <br> <pre>');
            $this->debug(print_r(json_encode($info, JSON_PRETTY_PRINT), true));
            $this->debug('</pre>');

            $rqs_headers = $this->buildHttpHeaders($info);
            $res = wp_remote_post($service_shipments_url, array(
                'method' => 'POST',
                'httpversion' => '1.1',
                'headers' => $rqs_headers,
                'body' => json_encode($info)
            ));

            delete_option('current_shipment_contains_dangerous_goods_auspost_elex');

            if (is_wp_error($res)) {
                $error_string = $res->get_error_message();
                update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', $error_string);
                $this->update_failed_shipment_packages($order_id, $package_index);
                $this->set_error_notices($error_string);
                if ($this->debug) {
                    echo "Error: <b>" . $error_string . "</b><br>";
                }
                return $shipment_id;
            }

            if (is_wp_error($res)) {
                $error_string = $res->get_error_message();
                $this->set_error_notices($error_string);
                update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', $error_string);
                if (!$this->is_request_bulk_shipment) {
                    if ($this->debug) {
                        echo "Error: <b>" . $error_string . "</b><br>";
                    }
                }
                $this->update_failed_shipment_packages($order_id, $package_index);
                return $shipment_id;
            }

            $response_array = isset($res['body']) ? json_decode($res['body']) : array();
            if ($shipping_service_type == 'StarTrack') {
                $this->debug('StarTrack (Australia Post) Response: <br><pre>');
                $this->debug(print_r(json_encode($response_array, JSON_PRETTY_PRINT), true));
                $this->debug('</pre>');
            } else {
                $this->debug('Australia Post Response: <br><pre>');
                $this->debug(print_r(json_encode($response_array, JSON_PRETTY_PRINT), true));
                $this->debug('</pre>');
            }

            if (!empty($response_array->errors)) {
                $this->set_error_notices($response_array->errors[0]->message);
                update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', $shipping_service_type . $response_array->errors[0]->message);
                if (!$this->is_request_bulk_shipment) {
                    if ($this->debug) {
                        echo "Error: <b>" . $response_array->errors[0]->message . "</b><br>";
                    }
                    $this->update_failed_shipment_packages($order_id, $package_index);
                }
                return $shipment_id;
            }

            if (!empty($response_array)) {
                if (isset($response_array->items[0]->errors)) {
                    $this->set_error_notices($response_array->items[0]->errors[0]->message);
                    update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', $shipping_service_type . $response_array->errors[0]->message);
                    if (!$this->is_request_bulk_shipment) {
                        if ($this->debug) {
                            echo "Error: <b>" . $error_string . "</b><br>";
                        }
                    }
                    return $shipment_id;
                }

                $shipment_date = '';
                $tracking_id_cs = '';
                //shipments array
                foreach ($response_array->shipments as $key => $shipments) {
                    $shipment_id = $shipments->shipment_id;
                    $shipment_date = substr($shipments->shipment_creation_date, 0, 10);
                }

                foreach ($response_array->shipments as $shipments) {
                    $items = $shipments->items;
                    foreach ($items as $item) {
                        $tracking_details = $item->tracking_details;
                        $tracking_id_cs .= $tracking_details->article_id;
                        $tracking_id_cs .= ',';
                    }
                }
                $tracking_id_cs = rtrim($tracking_id_cs, ',');

                if (!class_exists('WF_Tracking_Admin_AusPost')) {
                    include('class-wf-tracking-admin.php');
                }

                $admin_notice = '';


                try {
                    $admin_notice = WfTrackingUtil::update_tracking_data($order_id, $tracking_id_cs, 'australia-post', WF_Tracking_Admin_AusPost::SHIPMENT_SOURCE_KEY, WF_Tracking_Admin_AusPost::SHIPMENT_RESULT_KEY, $shipment_date);
                } catch (Exception $e) {
                    $admin_notice = '';
                    // Do nothing.
                }

                $order_shipment_ids = get_post_meta($order_id, 'wf_woo_australiapost_shipmentId', true);
                $order_startrack_shipment_ids = get_post_meta($order_id, 'elex_auspost_startrack_shipment_ids', true);
                if ($shipping_service_type == 'StarTrack') {
                    if (!empty($order_startrack_shipment_ids)) {
                        $order_startrack_shipment_ids[] = $shipment_id;
                    } else {
                        $order_startrack_shipment_ids = array();
                        $order_startrack_shipment_ids[] = $shipment_id;
                    }
                    update_post_meta($order_id, 'elex_auspost_startrack_shipment_ids', $order_startrack_shipment_ids);
                }

                if (empty($order_shipment_ids)) {
                    $order_shipment_ids = array();
                    $order_shipment_ids[] = $shipment_id;
                } else {
                    $order_shipment_ids[] = $shipment_id;
                }
                $postage_products_data = $this->get_postage_product_data();
                $postage_products_eligible = !empty($postage_products_data['auspost_eligible_postage_products']) ? $postage_products_data['auspost_eligible_postage_products'] : array();
                $postage_products_eligible_startrack = !empty($postage_products_data['startrack_eligible_postage_products']) ? $postage_products_data['startrack_eligible_postage_products'] : array();
                if (!empty($postage_products_eligible_startrack) || !empty($postage_products_eligible)) {
                    $all_eligible_postage_products = array_merge($postage_products_eligible, $postage_products_eligible_startrack);
                    $postage_products_type_and_product_ids = $this->get_postage_products_type_and_product_ids($all_eligible_postage_products);
                    $selected_service = array_search($shipping_service, $postage_products_type_and_product_ids);
                    update_post_meta($order_id, 'elex_auspost_shipping_service_name' . $shipment_id, $selected_service);
                }

                update_post_meta($order_id, 'wf_auspost_tracking_ids', $tracking_id_cs);
                update_post_meta($order_id, 'elex_auspost_shipping_service_' . $shipment_id, $shipping_service);
                update_post_meta($order_id, 'wf_woo_australiapost_shipmentId', $order_shipment_ids);
                
                if(!empty($shipment_id)){
                    do_action('elex_after_creating_shipment', $order_id );
                } 
            }
            return $shipment_id;
        }
    }

    private function generate_label_package($order, $selected_service_type, $shipment_id, $service_base_url, $vendor_user_id = false)
    {
        $service_label_url = $service_base_url . 'labels/';
        $shipping_label_layout_parcel_post = '';
        $shipping_label_layout_express_post = '';
        $shipping_label_layout_starTrack = '';
        $order_id = $this->wf_get_order_id($order);

        /* Providing label layout types based on the options selected by the user in the label settings */
        if (!empty($selected_service_type)) {
            if ($selected_service_type == 'Parcel Post') {
                if (isset($this->settings['label_layout_type_parcel_post']))
                    $shipping_label_layout_parcel_post = $this->settings['label_layout_type_parcel_post'];
            }
            if ($selected_service_type == 'Express Post') {
                if (isset($this->settings['label_layout_type_express_post']))
                    $shipping_label_layout_express_post = $this->settings['label_layout_type_express_post'];
            }

            if ($selected_service_type == 'StarTrack') {
                if (isset($this->settings['label_layout_type_starTrack']))
                    $shipping_label_layout_starTrack = $this->settings['label_layout_type_starTrack'];
            }
        }

        /* Providing A4-1pp as default, if user has not chosen any label layout type for the parcel services */
        if (empty($shipping_label_layout_parcel_post)) {
            $shipping_label_layout_parcel_post = 'A4-1pp';
        }

        /* Providing A4-1pp as default, if user has not chosen any label layout type for the express services */
        if (empty($shipping_label_layout_express_post)) {
            $shipping_label_layout_express_post = 'A4-1pp';
        }

        /* Providing A4-1pp as default, if user has not chosen any label layout type for the express services */
        if (empty($shipping_label_layout_starTrack)) {
            $shipping_label_layout_starTrack = 'A4-1pp';
        }

        if ($selected_service_type == 'StarTrack') {
            $label_req = array(
                'preferences' => array(
                    0 => array(
                        'type' => 'PRINT',
                        'groups' => array(
                            0 => array(
                                'group' => 'StarTrack',
                                'layout' => $shipping_label_layout_starTrack,
                                'branded' => 'false',
                                'left_offset' => 2,
                                'top_offset' => 0,
                            )
                        ),
                    ),
                ),
                'shipments' => array(
                    0 => array(
                        'shipment_id' => $shipment_id
                    )
                )
            );
        } else {
            if ($this->branded == true) {
                $label_req = array(
                    'preferences' => array(
                        0 => array(
                            'type' => 'PRINT',
                            'groups' => array(
                                0 => array(
                                    'group' => 'Parcel Post',
                                    'layout' => $shipping_label_layout_parcel_post,
                                    'branded' => 'true',
                                    'left_offset' => 2,
                                    'top_offset' => 0,
                                ),
                                1 => array(
                                    'group' => 'Express Post',
                                    'layout' => $shipping_label_layout_express_post,
                                    'branded' => 'true',
                                    'left_offset' => 2,
                                    'top_offset' => 0
                                )
                            ),
                        ),
                    ),
                    'shipments' => array(
                        0 => array(
                            'shipment_id' => $shipment_id
                        )
                    )
                );
            } else {
                $label_req = array(
                    'preferences' => array(
                        0 => array(
                            'type' => 'PRINT',
                            'groups' => array(
                                0 => array(
                                    'group' => 'Parcel Post',
                                    'layout' => $shipping_label_layout_parcel_post,
                                    'branded' => 'false',
                                    'left_offset' => 2,
                                    'top_offset' => 0,
                                ),
                                1 => array(
                                    'group' => 'Express Post',
                                    'layout' => $shipping_label_layout_express_post,
                                    'branded' => 'false',
                                    'left_offset' => 2,
                                    'top_offset' => 0
                                )
                            ),
                        ),
                    ),
                    'shipments' => array(
                        0 => array(
                            'shipment_id' => $shipment_id
                        )
                    )
                );
            }
        }
        $request = array();
        $vendor_check = false;
        if ($this->vendor_check && $this->vedor_api_key_enable && $vendor_user_id) {
            // Compatibility of Australia Post with ELEX Multivendor Addon 
            $request = array(
                'seller_id' => $vendor_user_id
            );
            $vendor_check = true;
            $vendor_elex_australia_post_api_key = get_the_author_meta('vendor_elex_australia_post_api_key', $vendor_user_id);
            $vendor_elex_australia_post_account_number = get_the_author_meta('vendor_elex_australia_post_account_number', $vendor_user_id);
            $vendor_elex_australia_post_api_password = get_the_author_meta('vendor_elex_australia_post_api_password', $vendor_user_id);
            $vendor_elex_australia_post_startrack_account_number = get_the_author_meta('vendor_elex_australia_post_startrack_account_number', $vendor_user_id);
            $vendor_elex_australia_post_startrack_api_password = get_the_author_meta('vendor_elex_australia_post_startrack_api_password', $vendor_user_id);
        }
        $label_rqs_headers = $this->buildHttpHeaders($request);
        $response = wp_remote_post($service_label_url, array(
            'method' => 'POST',
            'httpversion' => '1.1',
            'headers' => $label_rqs_headers,
            'body' => json_encode($label_req)
        ));

        if (is_wp_error($response)) {
            $error_string = $response->get_error_message();
            $this->set_error_notices($error_string);
            if (!$this->is_request_bulk_shipment) {
                if ($this->debug) {
                    echo "Error: <b>" . $error_string . "</b><br>";
                }
            }
            return;
        }

        $label_response = isset($response['body']) ? json_decode($response['body'], true) : array();
        $label_request_id = isset($label_response['labels']) ? $label_response['labels'][0]['request_id'] : '';
        $custom_message = $this->general_settings['custom_message'];
        update_post_meta($order_id, 'wf_woo_australiapost_labelId' . $shipment_id, $label_request_id);
        update_post_meta($order_id, 'wf_woo_australiapost_labelId_generation_date', current_time('Y-m-d', 0)); // current_time($type, $gmt = 0) returns time for selected time zone
        $order_shipment_label_request_ids = get_post_meta($order_id, 'elex_auspost_label_request_ids', true);
        if (empty($order_shipment_label_request_ids)) {
            $order_shipment_label_request_ids = array();
            $order_shipment_label_request_ids[] = $shipment_id;
        } else {
            $order_shipment_label_request_ids[] = $shipment_id;
        }
        update_post_meta($order_id, 'elex_auspost_label_request_ids', $order_shipment_label_request_ids);

        if ($label_request_id) {
            $label_get_url = $service_label_url . $label_request_id;

            $request_type_startrack = get_option('create_shipment_for_startrack', false);

            $api_password = $this->api_pwd;
            $api_account_number = $this->api_account_no;
            $rqs_headers = array();
            if ($vendor_check && $this->vedor_api_key_enable) {
                if ($request_type_startrack) {
                    $rqs_headers = array(
                        'Authorization' => 'Basic ' . base64_encode($vendor_elex_australia_post_api_key . ':' . $vendor_elex_australia_post_startrack_api_password),
                        'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
                        'Account-Number' => $vendor_elex_australia_post_startrack_account_number
                    );
                } else {
                    $rqs_headers = array(
                        'Authorization' => 'Basic ' . base64_encode($vendor_elex_australia_post_api_key . ':' . $vendor_elex_australia_post_api_password),
                        'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
                        'Account-Number' => $vendor_elex_australia_post_account_number
                    );
                }
            } else {
                if ($request_type_startrack) {
                    $rqs_headers = array(
                        'Authorization' => 'Basic ' . base64_encode($this->api_key_starTrack . ':' . $this->api_pwd_startrack),
                        'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
                        'Account-Number' => $this->api_account_no_startrack
                    );
                } else {
                    $rqs_headers = array(
                        'Authorization' => 'Basic ' . base64_encode($this->api_key . ':' . $this->api_pwd),
                        'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
                        'Account-Number' => $this->api_account_no
                    );
                }
            }
            $res = wp_remote_request($label_get_url, array(
                'headers' => $rqs_headers
            ));

            if (is_wp_error($res)) {
                $error_string = $res->get_error_message();
                $this->set_error_notices($error_string);
                if (!$this->is_request_bulk_shipment) {
                    if ($this->debug) {
                        echo "Error: <b>" . $error_string . "</b><br>";
                    }
                }
                return;
            }

            $response_array = isset($res['body']) ? json_decode($res['body']) : array();

            $this->debug('Australia Post Label Request  <br><pre>');
            $this->debug(print_r(json_encode($label_req, JSON_PRETTY_PRINT), true));
            $this->debug('</pre>');

            $this->debug('Australia Post Label Response <br><pre>');
            $this->debug(print_r(json_encode($response_array, JSON_PRETTY_PRINT), true));
            $this->debug('</pre>');

            if (!empty($response_array->errors)) {
                $this->set_error_notices($response_array->errors[0]->message);
                if (!$this->is_request_bulk_shipment) {
                    if ($this->debug) {
                        echo "Error: <b>" . $response_array->errors[0]->message . "</b></br>";
                    }
                }
                return;
            }

            $label_uri = isset($response_array->labels[0]->url) ? $response_array->labels[0]->url : '';

            $auspost_shipping_label = '';
            $count_requests = 0;

            if ($label_uri == '') {
                $auspost_shipping_label = $this->elex_aus_post_generate_label($order_id, $shipment_id);
                while (!empty($auspost_shipping_label) && !isset($auspost_shipping_label->labels[0]->url)) {
                    if (++$count_requests < 6) {
                        sleep(2);
                        $auspost_shipping_label = $this->elex_aus_post_generate_label($order_id, $shipment_id);
                    }
                }
                if ($auspost_shipping_label != '') {
                    $label_uri = $auspost_shipping_label->labels[0]->url;
                    update_post_meta($order_id, 'wf_woo_australiapost_labelURI' . $shipment_id, $label_uri);
                    if (isset($this->settings['save_labels']) && $this->settings['save_labels'] == 'yes') {
                        $this->elex_auspost_save_shipping_labels($order_id, $shipment_id, $label_uri);
                    }
                }
            } else {
                update_post_meta($order_id, 'wf_woo_australiapost_labelURI' . $shipment_id, $label_uri);
                if (isset($this->settings['save_labels']) && $this->settings['save_labels'] == 'yes') {
                    $this->elex_auspost_save_shipping_labels($order_id, $shipment_id, $label_uri);
                }
            }

            $order_shipment_label_uris = get_post_meta($order_id, 'elex_auspost_label_uris', true);
            if (empty($order_shipment_label_uris)) {
                $order_shipment_label_uris = array();
                $order_shipment_label_uris[] = $shipment_id;
            } else {
                $order_shipment_label_uris[] = $shipment_id;
            }
            update_post_meta($order_id, 'elex_auspost_label_uris', $order_shipment_label_uris);
        }
    }

    public function elex_auspost_get_label_content($URL)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $URL);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    private function elex_auspost_save_shipping_labels($order_id, $shipment_id, $label_uri)
    {
        $pdf_decoded =  wp_remote_get($label_uri);
        if (is_wp_error($pdf_decoded)) {
            $error_string = $pdf_decoded->get_error_message();
            $this->debug('Australia Post Save Label <br><pre>');
            $this->debug($error_string . '<br>', 'error');
        } else {

            $order_number = $this->custom_order_number($order_id);
            $file_name = 'Order_' . $order_number . '_' . $shipment_id . '_label.pdf';
            $path = ELEX_AUSPOST_LABELS . $file_name;
            $pdf = fopen($path, 'w');
            fwrite($pdf, $pdf_decoded['body']);
            fclose($pdf);
            update_post_meta($order_id, 'stored_label_uri_auspost_elex_' . $shipment_id, content_url('/ELEX_AusPost_Labels/' . 'Order_' . $order_number . '_' . $shipment_id . '_label.pdf'));
        }
    }

    //To generate shipment labels by using auto label generate addon.
    public function wf_auspost_auto_label_generate_addon($order_id = '')
    {
        if ($order_id) {
            $this->is_request_bulk_shipment = true;
            $this->debug = false;
            $count_requests = 0;
            update_option('auto_generate_label_on_auspost_elex', true);
            update_option('create_bulk_orders_shipment_auspost', true);
            $order = $this->wf_load_order($order_id);
            $this->wf_create_shipment($order);

            //Code generate labels after the shipments have been generated.
            $order_shipments_label_uris = get_post_meta($order_id, 'elex_auspost_label_uris', true);
            if (empty($order_shipments_label_uris)) {
                $order_shipments_label_request_ids = get_post_meta($order_id, 'elex_auspost_label_request_ids', true);
                if (!empty($order_shipments_label_request_ids)) {
                    foreach ($order_shipments_label_request_ids as $shipment_id) {
                        $auspost_shipping_label = $this->elex_aus_post_generate_label($order_id, $shipment_id);
                        while (!empty($auspost_shipping_label) && !isset($auspost_shipping_label->labels[0]->url)) {
                            if (++$count_requests < 6) {
                                sleep(2);
                                $auspost_shipping_label = $this->elex_aus_post_generate_label($order_id, $shipment_id);
                            } else {
                                $failed[] = $order_id;
                            }
                        }
                        if (isset($auspost_shipping_label->labels[0]->url)) {
                            $label_uri = $auspost_shipping_label->labels[0]->url;
                            update_post_meta($order_id, 'wf_woo_australiapost_labelURI' . $shipment_id, $label_uri);
                        }
                    }
                }
            }
            delete_option('auto_generate_label_on_auspost_elex');
        }
    }

    public function wf_auspost_bulk_order_actions()
    {
        $wp_list_table = _get_list_table('WP_Posts_List_Table');
        $action = $wp_list_table->current_action();
        $sendback = '';

        if ($action == 'create_auspost_shipment') {
            //forcefully turn off debug mode, otherwise it will die and cause to break the loop.
            $this->debug = false;
            $label_exist_for = '';
            $failed = array();
            if (isset($_REQUEST['post']) && !empty($_REQUEST['post'])) {
                foreach ($_REQUEST['post'] as $post_id) {
                    $count_requests = 0;
                    $order = $this->wf_load_order($post_id);
                    if (!$order)
                        return;

                    $order_id = $this->wf_get_order_id($order);
                    $shipmentIds = get_post_meta($order_id, 'wf_woo_australiapost_shipmentId', true);
                    $failed_packages = get_post_meta($order_id, 'failed_create_shipment_packages_auspost_elex', true);
                    if (empty($shipmentIds)) {
                        $shipmentIds = get_post_meta($order_id, 'elex_auspost_startrack_shipment_ids', true);
                    }

                    if (!empty($shipmentIds) && empty($failed_packages)) {
                        $label_exist_for .= $order_id . ', ';
                        delete_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage');
                    } else {
                        update_option('create_bulk_orders_shipment_auspost', true);
                        $this->is_request_bulk_shipment = true;
                        $this->wf_create_shipment($order);

                        //Code generate labels after the shipments have been generated.
                        $order_shipments_label_uris = get_post_meta($order_id, 'elex_auspost_label_request_ids', true);
                        if (!empty($order_shipments_label_uris)) {
                            foreach ($order_shipments_label_uris as $shipment_id) {
                                $auspost_shipping_label = $this->elex_aus_post_generate_label($order_id, $shipment_id);
                                while (!empty($auspost_shipping_label) && !isset($auspost_shipping_label->labels[0]->url)) {
                                    if (++$count_requests < 6) {
                                        sleep(2);
                                        $auspost_shipping_label = $this->elex_aus_post_generate_label($order_id, $shipment_id);
                                    } else {
                                        $failed[] = $order_id;
                                    }
                                }
                                if (isset($auspost_shipping_label->labels[0]->url)) {
                                    $label_uri = $auspost_shipping_label->labels[0]->url;
                                    update_post_meta($order_id, 'wf_woo_australiapost_labelURI' . $shipment_id, $label_uri);
                                }
                            }
                        }
                    }
                }

                delete_option('create_bulk_orders_shipment_auspost');
                $this->is_request_bulk_shipment = false;

                // Checking is default shipment service activated
                if (get_option('default_auspost_shipment_service_selected') == 'yes') {
                    $sendback = add_query_arg(array(
                        'bulk_label_auspost' => 1,
                        'ids' => join(',', $_REQUEST['post']),
                        'already_exist' => rtrim($label_exist_for, ', '),
                        'failed' => implode(', ', $failed)
                    ), admin_url('edit.php?post_type=shop_order'));
                } else {
                    // Obtaining orders' ids which do not have default shipping services
                    $orders_ids_with_no_default_shipment_service_auspost = get_option('orders_with_no_default_shipment_service_auspost');
                    $orders_ids_with_no_default_shipment_service_auspost = rtrim($orders_ids_with_no_default_shipment_service_auspost, ',');
                    delete_option('orders_with_no_default_shipment_service_auspost');
                    $sendback = add_query_arg(array(
                        'bulk_label_auspost' => 1,
                        'ids' => join(',', $_REQUEST['post']),
                        'already_exist' => rtrim($label_exist_for, ', '),
                        'default_shipment_service' => $orders_ids_with_no_default_shipment_service_auspost
                    ), admin_url('edit.php?post_type=shop_order'));
                }

                wp_redirect($sendback);
                exit();
            } else {
                return;
            }
        }
    }

    function elex_aus_post_generate_label($order_id, $shipment_id)
    {
        //Code generate labels after the shipments have been generated.
        $service_base_url = 'https://' . self::API_HOST . self::API_BASE_URL;

        $response_array = '';

        if ($this->contracted_api_mode == 'live') {
            $service_base_url = str_replace('test/', '', $service_base_url);
        }

        $label_uri = get_post_meta($order_id, 'wf_woo_australiapost_labelURI' . $shipment_id, true);

        if ($label_uri == '') {

            $label_request_id = get_post_meta($order_id, 'wf_woo_australiapost_labelId' . $shipment_id, true);
            if ($label_request_id != '') {
                $service_label_url = $service_base_url . 'labels/';
                $label_get_url = $service_label_url . $label_request_id;

                $request_type_startrack_bulk = get_option('create_bulk_orders_shipment_auspost_startrack', false);
                $request_type_startrack = get_option('create_shipment_for_startrack', false);
                $rqs_headers = array();

                $vendor_shipment = get_option('elex_australia_post_shipment_details') ? get_option('elex_australia_post_shipment_details') : array();
                if ($this->vendor_check && $this->vedor_api_key_enable && isset($vendor_shipment[$shipment_id]) && isset($vendor_shipment[$shipment_id]['seller_id'])) {
                    // Compatibility of Australia Post with ELEX Multivendor Addon 

                    $vendor_user_id = $vendor_shipment[$shipment_id]['seller_id'];
                    $api_password = get_the_author_meta('vendor_elex_australia_post_api_password', $vendor_user_id);
                    $api_account_number = get_the_author_meta('vendor_elex_australia_post_account_number', $vendor_user_id);
                    if (($request_type_startrack || $request_type_startrack_bulk)) {
                        $api_password = get_the_author_meta('vendor_elex_australia_post_startrack_api_password', $vendor_user_id);
                        $api_account_number = get_the_author_meta('vendor_elex_australia_post_startrack_account_number', $vendor_user_id);
                    }
                    $rqs_headers = array(
                        'Authorization' => 'Basic ' . base64_encode(get_the_author_meta('vendor_elex_australia_post_api_key', $vendor_user_id) . ':' . $api_password),
                        'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
                        'Account-Number' => $api_account_number
                    );
                } else {
                    if ($request_type_startrack || $request_type_startrack_bulk) {
                        $rqs_headers = array(
                            'Authorization' => 'Basic ' . base64_encode($this->api_key_starTrack . ':' . $this->api_pwd_startrack),
                            'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
                            'Account-Number' => $this->api_account_no_startrack
                        );
                    } else {
                        $rqs_headers = array(
                            'Authorization' => 'Basic ' . base64_encode($this->api_key . ':' . $this->api_pwd),
                            'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
                            'Account-Number' => $this->api_account_no
                        );
                    }
                }
                $res = wp_remote_request($label_get_url, array(
                    'headers' => $rqs_headers
                ));

                if (is_wp_error($res)) {
                    $error_string = $res->get_error_message();
                    $this->set_error_notices($error_string);
                    return;
                }

                $response_array = isset($res['body']) ? json_decode($res['body']) : array();

                if (!empty($response_array->errors)) {
                    $this->set_error_notices($response_array->errors[0]->message);
                    return;
                }
            }
        }
        return $response_array;
    }

    function wf_auspost_bulk_label_admin_notices()
    {
        global $post_type, $pagenow;

        if (!isset($_REQUEST['ids'])) {
            return;
        }

        if ($pagenow == 'edit.php' && $post_type == 'shop_order' && isset($_REQUEST['bulk_label_auspost'])) {
            if (isset($_REQUEST['ids']) && !empty($_REQUEST['ids'])) {
                $order_ids = explode(",", $_REQUEST['ids']);
            }

            $failed_ids_str = '';
            $success_ids_str = '';
            $already_exist_arr = array();
            $orders_error_string = '';
            $already_exist_custom_number_string = '';
            $failed_custom_number_str = '';
            $success_custom_number_str = '';
            if (isset($_REQUEST['already_exist']) && !empty($_REQUEST['already_exist'])) {
                $already_exist_arr = explode(',', $_REQUEST['already_exist']);
                foreach ($already_exist_arr as $item) {
                    $already_exist_custom_number_string .= $this->custom_order_number($item) . ', ';
                }
                $already_exist_custom_number_string = rtrim($already_exist_custom_number_string, ', ');
            }

            if (!empty($order_ids)) {
                foreach ($order_ids as $key => $id) {
                    $shipmentIds = get_post_meta($id, 'wf_woo_australiapost_shipmentId', true);
                    $shipment_err_auspost   = get_post_meta($id, 'wf_woo_australiapost_shipmentErrorMessage', true);
                    if (empty($shipmentIds) || !empty($shipment_err_auspost)) {
                        $failed_ids_str .= $id . ', ';
                        $failed_custom_number_str .= $this->custom_order_number($id) . ', ';
                        $orders_error_string .= '<b>Order no. ' . $id . ' Error:</b> ' . $shipment_err_auspost . '<br>';
                    } else if (!in_array($id, $already_exist_arr)) {
                        $success_ids_str .= $id . ', ';
                        $success_custom_number_str .= $this->custom_order_number($id) . ', ';
                    }
                }
            }

            $failed_ids_str = rtrim($failed_ids_str, ', ');
            $success_ids_str = rtrim($success_ids_str, ', ');
            $failed_custom_number_str = rtrim($failed_custom_number_str, ', ');
            $success_custom_number_str =  rtrim($success_custom_number_str, ', ');

            // Showing notices if the shipment id/s are not there to create return shipment
            if (isset($_REQUEST['no_normal_shipment']) && $_REQUEST['no_normal_shipment'] != '') {
                $message_string = 'Unable to find Shipment ids for the order(s) ' . $_REQUEST['no_normal_shipment'];
                $message_string = rtrim($message_string, ',');
                echo '<div class="notice notice-error is-dismissible"><p>' . __($message_string, 'wf-shipping-auspost') . '</p></div>';
                return;
            }

            if (isset($already_exist_custom_number_string) && $already_exist_custom_number_string != '') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Shipment already exist for following order(s) ' . $already_exist_custom_number_string, 'wf-shipping-auspost') . '</p></div>';
            }

            if (!empty($_REQUEST['failed'])) {
                $failed_shipment_service_custom_number_str = '';
                $default_shipment_service_custom_number_arr = explode(',', $_REQUEST['failed']);
                foreach ($default_shipment_service_custom_number_arr as $id) {
                    $failed_shipment_service_custom_number_str .= $this->custom_order_number($id) . ', ';
                }
                $failed_shipment_service_custom_number_str = rtrim($failed_shipment_service_custom_number_str, ', ');

                echo '<div class="notice notice-error is-dismissible"><p>' . __('Labels could not be generated for the following order IDs: ' . $failed_shipment_service_custom_number_str, 'wf-shipping-auspost') . '</p></div>';
            } else if ($success_custom_number_str != '') {
                echo '<div class="updated is-dismissible"><p>' . __('Successfully created shipment for following order(s) ' . $success_custom_number_str, 'wf-shipping-auspost') . '</p></div>';
            }
            // Showing notices if the customer has not set default shipment service
            if (isset($_REQUEST['default_shipment_service']) && !empty($_REQUEST['default_shipment_service'])) {
                if (!empty($_REQUEST['default_shipment_service'])) {
                    $default_shipment_service_custom_number_str = '';
                    $default_shipment_service_custom_number_arr = explode(',', $_REQUEST['default_shipment_service']);
                    foreach ($default_shipment_service_custom_number_arr as $id) {
                        $default_shipment_service_custom_number_str .= $this->custom_order_number($id) . ', ';
                    }
                    $default_shipment_service_custom_number_str = rtrim($default_shipment_service_custom_number_str, ', ');

                    echo '<div class="error is-dismissible"><p>' . __('Default Shipment Service is not set for order(s) ' . $default_shipment_service_custom_number_str, 'wf-shipping-auspost') . '</p></div>';
                    delete_option('default_shipment_service');
                    delete_option('orders_with_no_default_shipment_service');
                    return;
                }
            }

            if ($failed_custom_number_str != '') {
                echo '<div class="error is-dismissible"><p>' . __('Create shipment is failed for following order(s) ' . $failed_custom_number_str . "<br>" . $orders_error_string, 'wf-shipping-auspost') . '</p></div>';
            }
        }
    }

    public function elex_auspost_startrack_bulk_order_actions()
    {
        $wp_list_table = _get_list_table('WP_Posts_List_Table');
        $action = $wp_list_table->current_action();
        $sendback = '';

        if ($action == 'create_auspost_startrack_shipment') {
            //forcefully turn off debug mode, otherwise it will die and cause to break the loop.
            $this->debug = false;
            $label_exist_for = '';
            $failed = array();
            if (isset($_REQUEST['post']) && !empty($_REQUEST['post'])) {
                foreach ($_REQUEST['post'] as $post_id) {
                    $count_requests = 0;
                    $order = $this->wf_load_order($post_id);
                    if (!$order)
                        return;

                    $order_id = $this->wf_get_order_id($order);
                    $shipmentIds = get_post_meta($order_id, 'wf_woo_australiapost_shipmentId', true);
                    $failed_packages = get_post_meta($order_id, 'failed_create_shipment_packages_auspost_elex', true);
                    if (empty($shipmentIds)) {
                        $shipmentIds = get_post_meta($order_id, 'elex_auspost_startrack_shipment_ids', true);
                    }

                    if (!empty($shipmentIds) && empty($failed_packages)) {
                        $label_exist_for .= $order_id . ', ';
                        delete_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage');
                    } else {
                        update_option('create_bulk_orders_shipment_auspost_startrack', true);
                        $this->is_request_bulk_startrack_shipment = true;
                        $this->wf_create_shipment($order);

                        //Code generate labels after the shipments have been generated.
                        $order_shipments_label_request_ids = get_post_meta($order_id, 'elex_auspost_label_request_ids', true);
                        if (!empty($order_shipments_label_request_ids)) {
                            foreach ($order_shipments_label_request_ids as $shipment_label_request_id) {
                                $auspost_shipping_label = $this->elex_aus_post_generate_label($order_id, $shipment_label_request_id);
                                while (!empty($auspost_shipping_label) && !isset($auspost_shipping_label->labels[0]->url)) {
                                    if (++$count_requests < 6) {
                                        sleep(2);
                                        $auspost_shipping_label = $this->elex_aus_post_generate_label($order_id, $shipment_label_request_id);
                                    } else {
                                        $failed[] = $order_id;
                                    }
                                }
                                $label_uri = $auspost_shipping_label->labels[0]->url;
                                update_post_meta($order_id, 'wf_woo_australiapost_labelURI' . $shipment_label_request_id, $label_uri);
                            }
                        }
                    }
                }

                update_option('create_bulk_orders_shipment_auspost_startrack', false);
                $this->is_request_bulk_shipment = false;

                // Checking is default shipment service activated
                if (get_option('default_auspost_shipment_service_selected') == 'yes') {
                    $sendback = add_query_arg(array(
                        'bulk_label_startrack' => 1,
                        'ids' => join(',', $_REQUEST['post']),
                        'already_exist' => rtrim($label_exist_for, ', '),
                        'failed' => implode(', ', $failed)
                    ), admin_url('edit.php?post_type=shop_order'));
                } else {
                    // Obtaining orders' ids which do not have default shipping services
                    $orders_ids_with_no_default_shipment_service_auspost = get_option('orders_with_no_default_shipment_service_auspost');
                    $orders_ids_with_no_default_shipment_service_auspost = rtrim($orders_ids_with_no_default_shipment_service_auspost, ',');
                    delete_option('orders_with_no_default_shipment_service_auspost');
                    $sendback = add_query_arg(array(
                        'bulk_label_startrack' => 1,
                        'ids' => join(',', $_REQUEST['post']),
                        'already_exist' => rtrim($label_exist_for, ', '),
                        'default_shipment_service' => $orders_ids_with_no_default_shipment_service_auspost
                    ), admin_url('edit.php?post_type=shop_order'));
                }

                wp_redirect($sendback);
                exit();
            } else {
                return;
            }
        }
    }

    function elex_auspost_startrack_bulk_label_admin_notices()
    {
        global $post_type, $pagenow;

        if (!isset($_REQUEST['ids'])) {
            return;
        }

        if ($pagenow == 'edit.php' && $post_type == 'shop_order' && isset($_REQUEST['bulk_label_startrack'])) {
            if (isset($_REQUEST['ids']) && !empty($_REQUEST['ids'])) {
                $order_ids = explode(",", $_REQUEST['ids']);
            }

            $failed_ids_str = '';
            $already_exist_custom_number_string = '';
            $failed_custom_number_str = '';
            $success_custom_number_str = '';
            $success_ids_str = '';
            $already_exist_arr = array();
            $orders_error_string = '';
            if (isset($_REQUEST['already_exist']) && !empty($_REQUEST['already_exist'])) {
                $already_exist_arr = explode(',', $_REQUEST['already_exist']);
                foreach ($already_exist_arr as $item) {
                    $already_exist_custom_number_string .= $this->custom_order_number($item) . ', ';
                }
                $already_exist_custom_number_string = rtrim($already_exist_custom_number_string, ', ');
            }

            if (!empty($order_ids)) {
                foreach ($order_ids as $key => $id) {
                    $shipmentIds = get_post_meta($id, 'wf_woo_australiapost_shipmentId', true);
                    $shipment_err_auspost   = get_post_meta($id, 'wf_woo_australiapost_shipmentErrorMessage', true);
                    if (empty($shipmentIds) || !empty($shipment_err_auspost)) {
                        $failed_ids_str .= $id . ', ';
                        $failed_custom_number_str .= $this->custom_order_number($id) . ', ';
                        $orders_error_string .= '<b>Order no. ' . $id . ' Error:</b> ' . $shipment_err_auspost . '<br>';
                    } else if (!in_array($id, $already_exist_arr)) {
                        $success_ids_str .= $id . ', ';
                        $success_custom_number_str .= $this->custom_order_number($id) . ', ';
                    }
                }
            }

            $failed_ids_str = rtrim($failed_ids_str, ', ');
            $success_ids_str = rtrim($success_ids_str, ', ');
            $failed_custom_number_str = rtrim($failed_custom_number_str, ', ');
            $success_custom_number_str =  rtrim($success_custom_number_str, ', ');

            // Showing notices if the shipment id/s are not there to create return shipment
            if (isset($_REQUEST['no_normal_shipment']) && $_REQUEST['no_normal_shipment'] != '') {
                $message_string = 'Unable to find Shipment ids for the order(s) ' . $_REQUEST['no_normal_shipment'];
                $message_string = rtrim($message_string, ',');
                echo '<div class="notice notice-error is-dismissible"><p>' . __($message_string, 'wf-shipping-auspost') . '</p></div>';
                return;
            }

            if (isset($already_exist_custom_number_string) && $already_exist_custom_number_string != '') {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Shipment already exist for following order(s) ' . $already_exist_custom_number_string, 'wf-shipping-auspost') . '</p></div>';
            }
            if (!empty($_REQUEST['failed'])) {
                $failed_shipment_service_custom_number_str = '';
                $default_shipment_service_custom_number_arr = explode(',', $_REQUEST['failed']);
                foreach ($default_shipment_service_custom_number_arr as $id) {
                    $failed_shipment_service_custom_number_str .= $this->custom_order_number($id) . ', ';
                }
                $failed_shipment_service_custom_number_str = rtrim($failed_shipment_service_custom_number_str, ', ');

                echo '<div class="notice notice-error is-dismissible"><p>' . __('Labels could not be generated for the following order IDs: ' . $failed_shipment_service_custom_number_str, 'wf-shipping-auspost') . '</p></div>';
            } else if ($success_custom_number_str != '') {
                echo '<div class="updated is-dismissible"><p>' . __('Successfully created shipment for following order(s) ' . $success_custom_number_str, 'wf-shipping-auspost') . '</p></div>';
            }

            // Showing notices if the customer has not set default shipment service
            if (isset($_REQUEST['default_shipment_service']) && !empty($_REQUEST['default_shipment_service'])) {
                if (!empty($_REQUEST['default_shipment_service'])) {
                    $default_shipment_service_custom_number_str = '';
                    $default_shipment_service_custom_number_arr = explode(',', $_REQUEST['default_shipment_service']);
                    foreach ($default_shipment_service_custom_number_arr as $id) {
                        $default_shipment_service_custom_number_str .= $this->custom_order_number($id) . ', ';
                    }
                    $default_shipment_service_custom_number_str = rtrim($default_shipment_service_custom_number_str, ', ');

                    echo '<div class="error is-dismissible"><p>' . __('Default Shipment Service is not set for order(s) ' . $default_shipment_service_custom_number_str, 'wf-shipping-auspost') . '</p></div>';
                    delete_option('default_shipment_service');
                    delete_option('orders_with_no_default_shipment_service');
                    return;
                }
            }

            if ($failed_custom_number_str != '') {
                echo '<div class="error is-dismissible"><p>' . __('Create shipment is failed for following order(s) ' . $failed_custom_number_str . "<br>" . $orders_error_string, 'wf-shipping-auspost') . '</p></div>';
            }
        }
    }

    private function wf_get_request_header($accept, $contentType)
    {
        return array(
            'Content-Type' => $contentType,
            'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
            'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
            'account-number' => 1004916305
        );
    }

    private function get_postage_products_type_and_product_ids($all_eligible_postage_products)
    {
        $postage_products_type_and_product_ids = array();
        if (is_array($all_eligible_postage_products) && !empty($all_eligible_postage_products)) {
            foreach ($all_eligible_postage_products as $postage_product_eligible) {
                $postage_products_type_and_product_ids[$postage_product_eligible['type']] = $postage_product_eligible['product_id'];
            }
        }
        return $postage_products_type_and_product_ids;
    }

    private function get_postage_product_data($vendor_user_id = false)
    {

        if ($vendor_user_id && $this->vedor_api_key_enable) {
            $api_key = get_the_author_meta('vendor_elex_australia_post_api_key', $vendor_user_id);
            $api_account_no = get_the_author_meta('vendor_elex_australia_post_account_number', $vendor_user_id);
            $api_pwd = get_the_author_meta('vendor_elex_australia_post_api_password', $vendor_user_id);
            $api_account_no_startrack = get_the_author_meta('vendor_elex_australia_post_startrack_account_number', $vendor_user_id);
            $api_pwd_startrack = get_the_author_meta('vendor_elex_australia_post_startrack_api_password', $vendor_user_id);
        } else {
            if ($this->startrack_enabled) {
                $api_account_no_startrack = $this->api_account_no_startrack;
                $api_pwd_startrack = $this->api_pwd_startrack;
                $api_key_starTrack = $this->api_key_starTrack;
            }
            $api_account_no = isset($this->api_account_no) ? $this->api_account_no : array();
            $api_pwd = $this->api_pwd;
            $api_key = false;
        }
        $get_accounts_endpoint_startrack = '';

        if (!class_exists('wf_australia_post_shipping')) {
            include_once('class-wf-australia-post-shipping.php');
        }

        $shipping_cart_side = new wf_australia_post_shipping();

        if ($this->startrack_enabled) {
            $get_accounts_endpoint_startrack = 'https://' . self::API_HOST . self::API_BASE_URL . 'accounts/' . $api_account_no_startrack;
        }

        $get_accounts_endpoint = 'https://' . self::API_HOST . self::API_BASE_URL . 'accounts/' . $api_account_no;
        if ($this->contracted_api_mode == 'live') {
            $get_accounts_endpoint = str_replace('test/', '', $get_accounts_endpoint);
            if ($get_accounts_endpoint_startrack != '') {
                $get_accounts_endpoint_startrack = str_replace('test/', '', $get_accounts_endpoint_startrack);
            }
        }

        $postage_products = !empty($api_account_no) ? $shipping_cart_side->get_services($get_accounts_endpoint, $api_account_no, $api_pwd, $api_key) : array();
        $postage_products_startrack = array();
        $postage_products_eligible_startrack = array();

        if ($get_accounts_endpoint_startrack != '') {
            $postage_products_startrack = $shipping_cart_side->get_services($get_accounts_endpoint_startrack, $api_account_no_startrack, $api_pwd_startrack, $api_key_starTrack);
            $postage_products_eligible_startrack = json_decode($postage_products_startrack, true);
            $postage_products_eligible_startrack = isset($postage_products_eligible_startrack['postage_products']) ? $postage_products_eligible_startrack['postage_products'] : array();
        }

        $postage_products_eligible = json_decode($postage_products, true);
        $postage_products_eligible = isset($postage_products_eligible['postage_products']) ? $postage_products_eligible['postage_products'] : array();
        $service_name = '';

        $postage_products_type_and_product_ids = array();

        if (!empty($postage_products_eligible_startrack)) {
            foreach ($postage_products_eligible_startrack as $startrack_eligible_postage_product_key => $startrack_eligible_postage_product) {
                $postage_products_eligible_startrack[$startrack_eligible_postage_product_key]['service_type'] = 'startrack';
            }
        }

        return array('auspost_eligible_postage_products' => $postage_products_eligible, 'startrack_eligible_postage_products' => $postage_products_eligible_startrack);
    }

    public function wf_add_australia_post_metabox()
    {
        global $post;

        if (!$post) {
            return;
        }

        if (!in_array($post->post_type, array('shop_order')))
            return;

        $order = $this->wf_load_order($post->ID);
        if (!$order)
            return;

        $this->order_id = $this->wf_get_order_id($order);

        add_meta_box('wfaustraliapost_metabox', __('Australia Post', 'wf-shipping-auspost'), array($this, 'wf_australia_post_metabox_content'), 'shop_order', 'advanced', 'default');
    }

    public function wf_australia_post_metabox_content()
    {
        global $post;

        if ($this->boxpacking_error) {
            $this->show_boxpacking_error_notice();
        }

        if (!$post) {
            return;
        }

        $order = $this->wf_load_order($post->ID);
        if (!$order)
            return;

        $serviceName = $this->wf_get_shipping_service($order, false);
        $order_id = $this->wf_get_order_id($order);
        $order_number = $order->get_order_number();
        delete_option('request_to_create_shipment');
        delete_option('create_bulk_orders_shipment_auspost');

        $shipmentIds = get_post_meta($order_id, 'wf_woo_australiapost_shipmentId', false);
        $tracking_ids = get_post_meta($order_id, 'wf_auspost_tracking_ids', false);
        if (is_array($tracking_ids) && !empty($tracking_ids[0])) {
            $tracking_id_array = explode(",", $tracking_ids[0]);
        }

        $shipment_void_ids = get_post_meta($order_id, 'wf_woo_australiapost_shipment_void', true);
        $failed_shipment_packages = get_post_meta($order_id, 'failed_create_shipment_packages_auspost_elex', true);
        $shipping_country = wf_get_order_shipping_country($order);

        $consolidated_failed_shipment_packages = get_post_meta($order_id, 'consolidated_failed_create_shipment_packages_auspost_elex', true);
        if (!empty($consolidated_failed_shipment_packages)) {
            if (!empty($failed_shipment_packages)) {
                foreach ($failed_shipment_packages as $failed_shipment_package) {
                    if (is_array($failed_shipment_package)) {
                        foreach ($failed_shipment_package as $shipment_index) {
                            $consolidated_failed_shipment_packages[] = $shipment_index;
                        }
                    } else {
                        $consolidated_failed_shipment_packages[] = $failed_shipment_package;
                    }
                }
            }

            if (!empty($consolidated_failed_shipment_packages)) {
                update_post_meta($order_id, 'consolidated_failed_create_shipment_packages_auspost_elex', $consolidated_failed_shipment_packages);
                delete_post_meta($order_id, 'failed_create_shipment_packages_auspost_elex');
            }
        }

        $manifestLink = get_post_meta($order_id, 'wf_woo_australiapost_manifestLink', false);
        $manifestArtifactLinkList = get_post_meta($order_id, 'wf_woo_australiapost_manifestArtifactLink', false);
        $shipmentErrorMessage = get_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', true);
        $manifestErrorMessage = get_post_meta($order_id, 'wf_woo_australiapost_manifestErrorMessage', true);
        $transmitErrorMessage = get_post_meta($order_id, 'wf_woo_australiapost_transmitErrorMessage', true);
        $shipment_void_error_message = get_post_meta($order_id, 'wf_woo_australiapost_shipment_void_errormessage', true);

        $display_shipment_tracking_message = get_option('shipment_tracking_message');

        if (!empty($display_shipment_tracking_message)) {
            echo '<div class="notice notice-success is-dismissible">
                <p>' . $display_shipment_tracking_message . '</p>
            </div>';
            delete_option('shipment_tracking_message');
        }

        if (!empty($shipmentErrorMessage)) {
            echo '<div class="error"><p>' . sprintf(__('Shipment Error:%s', 'wf-shipping-auspost'), $shipmentErrorMessage) . '</p></div>';
            delete_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage');
        }

        if (!empty($shipment_void_error_message)) {
            echo '<div class="error"><p>' . sprintf(__('Void Shipment Error:%s', 'wf-shipping-auspost'), $shipment_void_error_message) . '</p></div>';
        }
        echo '<ul>';
        $selected_service = get_post_meta($this->wf_get_order_id($order), 'wf_aus_label_shipment_id', true);
?>
        <style type="text/css">
            .label_buttons {
                float: right !important;
                margin-top: -2% !important;
            }

            .delete_shipment {
                margin-top: -1% !important;
                margin-bottom: 2% !important;
            }
        </style>
        <?php
        $postage_products_data = $this->get_postage_product_data();
        $postage_products_eligible = !empty($postage_products_data['auspost_eligible_postage_products']) ? $postage_products_data['auspost_eligible_postage_products'] : array();
        $postage_products_eligible_startrack = !empty($postage_products_data['startrack_eligible_postage_products']) ? $postage_products_data['startrack_eligible_postage_products'] : array();
        if (!empty($shipmentIds)) {

            $transmit_url = admin_url('/post.php?wf_australiapost_transmitshipment=' . $order_id);
            $delete_order_url = admin_url('/post.php?wf_australiapost_delete_shipment=' . $order_id);

            if (is_array($shipmentIds) && !empty($shipmentIds)) {
                $shipmentIds_array = $shipmentIds;
                $shipmentIds_array = array_shift($shipmentIds_array);
                if (is_array($shipmentIds_array) && !empty($shipmentIds_array)) {
                    $shipmentIds = $shipmentIds_array;
                    foreach ($shipmentIds as $shipment_id) {
                        $shipment_service_for_shipment_id = get_post_meta($order_id, 'elex_auspost_shipping_service_' . $shipment_id, true);
                        $all_eligible_postage_products = get_option("all_auspost_postage_products_auspost_elex");
                        $postage_products_type_and_product_ids = $this->get_postage_products_type_and_product_ids($all_eligible_postage_products);
                        $selected_service = array_search($shipment_service_for_shipment_id, $postage_products_type_and_product_ids);
                        if (empty($selected_service)) {
                            $selected_service = $serviceName;
                        }
                        echo "<li>Shipping Service: <strong>" . $selected_service . " (" . $shipment_service_for_shipment_id . ")</strong></li>";
                        echo '<li><strong>Shipment #:</strong> ' . $shipment_id;

                        if ((is_array($shipment_void_ids) && in_array($shipment_id, $shipment_void_ids))) {
                            echo "<br> This shipment " . $shipment_id . " is terminated.";
                        }
                        $delete_order_url .= '&wf_shipment_id=' . $shipment_id;
                        $this->shipment_id = $shipment_id;
                        $austalia_post_order_number = get_post_meta($order_id, 'wf_australia_post_order' . $shipment_id, array());
                        if (isset($austalia_post_order_number[0])) {
                            echo "<br><strong>Order #:</strong> " . $austalia_post_order_number[0];
                        }
                        $packageDetailForTheshipment = get_post_meta($order_id, 'wf_woo_australiapost_packageDetails_' . $shipment_id, true);
                        $packageBoxName = get_post_meta($order_id, 'wf_woo_australiapost_boxid_' . $shipment_id, true);
                        if (!empty($packageBoxName)) {
                            echo '<strong>Box Name: ' . '</strong>' . $packageBoxName . '<br>';
                        }
                        if (!empty($packageDetailForTheshipment)) {
                            foreach ($packageDetailForTheshipment as $dimentionKey => $dimentionValue) {
                                echo '<strong>' . $dimentionKey . ': ' . '</strong>' . $dimentionValue;
                                if ($dimentionKey == 'Weight')
                                    echo ' kg';
                                else
                                    echo ' cm';
                            }
                        }

                        if (isset($this->settings['save_labels']) && $this->settings['save_labels'] == 'yes') {
                            if (file_exists(ELEX_AUSPOST_LABELS . 'Order_' . $order_number . '_' . $shipment_id . '_label.pdf')) {
                                $shipping_label = content_url('/ELEX_AusPost_Labels/' . 'Order_' . $order_number . '_' . $shipment_id . '_label.pdf');
                            }
                        } else {
                            $shipping_label = get_post_meta($order_id, 'wf_woo_australiapost_labelURI' . $shipment_id, true);
                        }

                        if (empty($shipping_label)) {
                            $this->print_shipping_label($order, $shipment_id);
                            $shipping_label = get_post_meta($order_id, 'wf_woo_australiapost_labelURI' . $shipment_id, true);
                        }

                        if (!empty($shipping_label)) {
                            $download_url = $shipping_label;
                            $get_label_url = admin_url('/post.php?post=' . $order_id . '&action=edit&wf_australiapost_viewlabel=' . $order_id . '&shipment_id=' . $shipment_id . '&order_number=' . $order_number . '');
                            if ($this->settings['dir_download'] == 'yes') { ?>
                                <a class="button button-primary tips label_buttons wf_australiapost_viewlabel" target="_self" href="<?php echo $get_label_url; ?>" data-tip="<?php _e('Download Label', 'wf-shipping-auspost'); ?>"><?php _e('Download Label', 'wf-shipping-auspost'); ?></a><?php

                                                                                                                                                                                                                                                                                            } else {
                                                                                                                                                                                                                                                                                                ?>
                                <a class="button button-primary tips label_buttons" target="_blank" href="<?php echo $download_url; ?>" data-tip="<?php _e('Print Label', 'wf-shipping-auspost'); ?>"><?php _e('Print Label', 'wf-shipping-auspost'); ?></a><?php
                                                                                                                                                                                                                                                                                            }
                                                                                                                                                                                                                                                                                        }


                                                                                                                                                                                                                                                                                        $label_request_id = get_post_meta($order_id, 'wf_woo_australiapost_labelId' . $shipment_id, true);
                                                                                                                                                                                                                                                                                        if ($label_request_id) {
                                                                                                                                                                                                                                                                                            if (empty($shipping_label)) {
                                                                                                                                                                                                                                                                                                $get_label_url = admin_url('/post.php?wf_australiapost_createshipment=' . $order_id . '&shipment_id=' . $shipment_id . '&order_number=' . $order_number);
                                                                                                                                                                                                                                                            ?>
                                <a class="button button-primary tips label_buttons" href="<?php echo $get_label_url; ?>" data-tip="<?php _e('Generate Label', 'wf-shipping-auspost'); ?>"><?php _e('Generate Label', 'wf-shipping-auspost'); ?></a>
                    <?php
                                                                                                                                                                                                                                                                                            }
                                                                                                                                                                                                                                                                                        }
                                                                                                                                                                                                                                                                                        echo '<hr>';
                                                                                                                                                                                                                                                                                    } ?>
                    <br>
                    <a class="button tips delete_shipment" href="<?php echo $delete_order_url; ?>" data-tip="<?php _e('Delete Shipment', 'wf-shipping-auspost'); ?>"><?php _e('Delete Shipment', 'wf-shipping-auspost'); ?></a>
                    <?php
                } else {
                    foreach ($shipmentIds as $shipment_id) {
                        echo '<li><strong>Shipment #:</strong> ' . $shipment_id;

                        if ((is_array($shipment_void_ids) && in_array($shipment_id, $shipment_void_ids))) {
                            echo "<br> This shipment " . $shipment_id . " is terminated.";
                        }
                        $delete_order_url .= '&wf_shipment_id=' . $shipment_id;
                        $this->shipment_id = $shipment_id;
                        $austalia_post_order_number = get_post_meta($order_id, 'wf_australia_post_order' . $shipment_id, array());
                        if (isset($austalia_post_order_number[0])) {
                            echo "<br><strong>Order #:</strong> " . $austalia_post_order_number[0];
                        }
                        echo '<hr>';
                        $packageDetailForTheshipment = get_post_meta($order_id, 'wf_woo_australiapost_packageDetails_' . $shipment_id, true);
                        $packageBoxName = get_post_meta($order_id, 'wf_woo_australiapost_boxid_' . $shipment_id, true);
                        if (!empty($packageBoxName)) {
                            echo '<strong>Box Name: ' . '</strong>' . $packageBoxName . '<br>';
                        }
                        if (!empty($packageDetailForTheshipment)) {
                            foreach ($packageDetailForTheshipment as $dimentionKey => $dimentionValue) {
                                echo '<strong>' . $dimentionKey . ': ' . '</strong>' . $dimentionValue;
                                if ($dimentionKey == 'Weight')
                                    echo ' kg<br>';
                                else
                                    echo ' cm<br>';
                            }
                            echo '<hr>';
                        }

                        if (isset($this->settings['save_labels']) && $this->settings['save_labels'] == 'yes') {
                            if (file_exists(ELEX_AUSPOST_LABELS . 'Order_' . $order_number . '_' . $shipment_id . '_label.pdf')) {
                                $shipping_label = content_url('/ELEX_AusPost_Labels/' . 'Order_' . $order_number . '_' . $shipment_id . '_label.pdf');
                            }
                        } else {
                            $shipping_label = get_post_meta($order_id, 'wf_woo_australiapost_labelURI', true);
                        }

                        if (!empty($shipping_label)) {
                            $download_url = $shipping_label;
                    ?>
                            <a class="button button-primary tips" target="_blank" href="<?php echo $download_url; ?>" data-tip="<?php _e('Print Label', 'wf-shipping-auspost'); ?>"><?php _e('Print Label', 'wf-shipping-auspost'); ?></a>
                        <?php
                        }
                        ?>
                        <a class="button tips" href="<?php echo $delete_order_url; ?>" data-tip="<?php _e('Delete Shipment', 'wf-shipping-auspost'); ?>"><?php _e('Delete Shipment', 'wf-shipping-auspost'); ?></a>

                        <?php

                        $label_request_id = get_post_meta($order_id, 'wf_woo_australiapost_labelId', true);
                        if ($label_request_id) {
                            if (empty($shipping_label)) {
                                $get_label_url = admin_url('/post.php?wf_australiapost_createshipment=' . $order_id);
                        ?>
                                <a class="button button-primary tips" href="<?php echo $get_label_url; ?>" data-tip="<?php _e('Generate Label', 'wf-shipping-auspost'); ?>"><?php _e('Generate Label', 'wf-shipping-auspost'); ?></a>
                <?php
                            }
                        }
                    }
                } ?>
            <?php
            }
        }

        $failed_shipment_packages = array();

        if (empty($shipmentIds) || !empty($consolidated_failed_shipment_packages)) {
            $generate_url = admin_url('/post.php?wf_australiapost_createshipment=' . $order_id);
            $generate_packages_url = admin_url('/post.php?elex_auspost_generate_packages=' . $order_id);

            $shipping_data = $order->get_shipping_methods();
            $shipping_data = array_shift($shipping_data);
            $shipment_service_selected = '';

            if (!empty($shipping_data)) {
                $shipping_method_data = $shipping_data->get_data();
                $shipment_service_selected = $shipping_method_data['name'];
            }
            $service_for_creating_shipment = '';

            $default_domestic_shipment_service_auspost = (isset($this->settings['auspost_default_domestic_shipment_service']) && ($this->settings['auspost_default_domestic_shipment_service'] != 'none')) ? $this->settings['auspost_default_domestic_shipment_service'] : 'none';
            $default_international_shipment_service_auspost = (isset($this->settings['auspost_default_international_shipment_service']) && ($this->settings['auspost_default_international_shipment_service'] != 'none')) ? $this->settings['auspost_default_international_shipment_service'] : 'none';
            $starTrack_default_shipment_service = (isset($this->settings['starTrack_default_shipment_service']) && ($this->settings['starTrack_default_shipment_service'] != 'none')) ? $this->settings['starTrack_default_shipment_service'] : 'none';

            $order_items = $order->get_items();

            $shipment_requests = array();

            $from_weight_unit = '';
            if ($this->weight_unit != 'kg') {
                $from_weight_unit = $this->weight_unit;
            }

            $from_dimension_unit = '';
            if ($this->dimension_unit != 'cm') {
                $from_dimension_unit = $this->dimension_unit;
            }

            $remove_package_status = get_option("removed_package_status_auspost_elex", false);

            if (!empty($consolidated_failed_shipment_packages) || $remove_package_status) {
                $contains_failed_packages = true;
                $this->elex_auspost_generate_packages($order_id, $contains_failed_packages);
            }
            $shipment_requests = get_post_meta($order_id, 'shipment_packages_auspost_elex', true);
            
            if ($remove_package_status) {
                delete_option("removed_package_status_auspost_elex");
            }
            if ($this->vendor_check) {
                $vendor_shipping_service = array();
                $line_items_shipping = $order->get_items('shipping');
                foreach ($line_items_shipping as $item) {
                    $meta_data = $item->get_meta('seller_id');
                    if ($meta_data) {
                        $vendor_shipping_service[$meta_data] = array(
                            'seller_id' => $meta_data,
                            'shipping_service' => $item->get_name()
                        );
                    }
                }
            }
            ?>
            <style>
                .infotip .infotiptext {
                    visibility: hidden;
                    width: 450px;
                    background-color: black;
                    color: #fff;
                    text-align: left;
                    border-radius: 6px;
                    padding: 5px 0;

                    position: absolute;
                    z-index: 1;
                }

                .infotip:hover .infotiptext {
                    visibility: visible;
                }

                th {
                    padding: 1%;
                }

                option {
                    color: black !important;
                    background-color: gainsboro;
                    font-weight: 600;
                }

                .service_label {
                    color: white !important;
                    background-color: black;
                }

                .shipment_contents {
                    width: 12% !important;
                    overflow: hidden;
                }

                .shipment_description_row_columns {
                    padding: 0.5%;
                    width: 20% !important;
                }

                .shipment_row_columns_input_style {
                    width: 50% !important;
                }

                .elex-auspost-refresh-services {
                    padding-bottom: 25px;
                }

                .elex-auspost-refresh-service-price {
                    margin-left: 40px;
                }

                .shipment_table_auspost_elex table {
                    width: 120%;
                }
            </style>
            <?php if (!empty($shipment_requests)) { ?>
                <div class="elex-auspost-refresh-services" style="margin-bottom: 20px;">
                    <a class="auspost_generate_refresh_service button" id="auspost_generate_refresh_service_button" href="#" data-tip="<?php _e('Show Available Services/Rates', 'wf_shipping_auspost'); ?>" style="float: right;overflow: hidden;">
                        <span class="dashicons dashicons-update help_tip" data-tip="<?php _e('Show Available Services/Rates', 'wf_shipping_auspost') ?> " style="padding-top: 2px;"></span>
                    </a>
                    <div class="elex_auspost_available_services" id="elex_auspost_available_services" style="display: block;">
                    </div>
                </div>
                <div id="shipment_table_div_auspost_elex" style="border:1px solid #ddd; overflow-x: auto;">
                    <table class="shipment_table_auspost_elex">
                        <thead align="left">
                            <tr>
                                <th>Item </th>
                                <?php if ($this->vendor_check) { ?>
                                    <th>Vendor</th>
                                <?php } ?>
                                <th>Weight</th>
                                <th>Length</th>
                                <th>Width</th>
                                <th>Height</th>
                                <th>Service</th>
                                <?php
                                if ($shipping_country != 'AU') {
                                ?>
                                    <th>Classification</th>
                                    <th>Description of OTHER</th>
                                <?php   } ?>
                            </tr>
                        </thead>
                        <tbody class="table_body_packages_auspost_elex">
                            <?php
                            $request_package_count = 0;
                            $products_id_packed = '';
                            foreach ($shipment_requests as $shipment_request) {
                                if ($this->vendor_check) {
                                    // Compatibility of Australia Post with ELEX Multivendor Addon 

                                    $seller_id = $shipment_request['seller_id'];
                                    $postage_products_data = $this->get_postage_product_data($seller_id);
                                    $postage_products_eligible = !empty($postage_products_data['auspost_eligible_postage_products']) ? $postage_products_data['auspost_eligible_postage_products'] : array();
                                    $postage_products_eligible_startrack = !empty($postage_products_data['startrack_eligible_postage_products']) ? $postage_products_data['startrack_eligible_postage_products'] : array();
                                    $all_eligible_postage_products = array_merge($postage_products_eligible, $postage_products_eligible_startrack);
                                    update_option("all_auspost_postage_products_auspost_elex", $all_eligible_postage_products);

                                    $postage_products_type_and_product_ids = $this->get_postage_products_type_and_product_ids($all_eligible_postage_products);
                                    /* Obtaining service selected for the shipment */
                                    $serviceName = $this->wf_get_shipping_service($order, false);

                                    $this->label_shipping_method_id = $this->get_selected_shipping_service_id($postage_products_type_and_product_ids, $serviceName, $order);
                                    update_post_meta($order_id, 'wf_woo_australiapost_service_code', $this->label_shipping_method_id);

                                    $this->selected_service_type = $this->get_shipping_service_type($order, $all_eligible_postage_products, $this->label_shipping_method_id);
                                } else {
                                    $postage_products_data = $this->get_postage_product_data();
                                    $postage_products_eligible = !empty($postage_products_data['auspost_eligible_postage_products']) ? $postage_products_data['auspost_eligible_postage_products'] : array();
                                    $postage_products_eligible_startrack = !empty($postage_products_data['startrack_eligible_postage_products']) ? $postage_products_data['startrack_eligible_postage_products'] : array();
                                    $all_eligible_postage_products = array_merge($postage_products_eligible, $postage_products_eligible_startrack);
                                    update_option("all_auspost_postage_products_auspost_elex", $all_eligible_postage_products);

                                    $postage_products_type_and_product_ids = $this->get_postage_products_type_and_product_ids($all_eligible_postage_products);
                                    /* Obtaining service selected for the shipment */
                                    $serviceName = $this->wf_get_shipping_service($order, false);

                                    $this->label_shipping_method_id = $this->get_selected_shipping_service_id($postage_products_type_and_product_ids, $serviceName, $order);
                                    update_post_meta($order_id, 'wf_woo_australiapost_service_code', $this->label_shipping_method_id);

                                    $this->selected_service_type = $this->get_shipping_service_type($order, $all_eligible_postage_products, $this->label_shipping_method_id);
                                }
                                if ($this->weight_unit != 'kg') {
                                    $shipment_request['Weight']['Value'] = wc_get_weight($shipment_request['Weight']['Value'], 'kg');
                                }

                                if ($this->dimension_unit != 'cm') {
                                    $shipment_request['Dimensions']['Length'] = wc_get_dimension($shipment_request['Dimensions']['Length'], 'cm');
                                    $shipment_request['Dimensions']['Width'] = wc_get_dimension($shipment_request['Dimensions']['Width'], 'cm');
                                    $shipment_request['Dimensions']['Height'] = wc_get_dimension($shipment_request['Dimensions']['Height'], 'cm');
                                }

                                if (isset($shipment_request['packed_products']) && !empty($shipment_request['packed_products'])) {
                                    $package_packed_products = $shipment_request['packed_products'];
                                    $products_packed = array();
                                    if (!empty($package_packed_products) && (count($package_packed_products) == 1)) {
                                        foreach ($package_packed_products as $package_packed_product) {
                                            array_push($products_packed, $package_packed_product['id']);
                                        }
                                    } else {
                                        if (isset($package_packed_products['id']))
                                            array_push($products_packed, $package_packed_products['id']);
                                    }
                                    if (!empty($products_packed)) $products_id_packed = implode(',', $products_packed);
                                }

                                $shipment_contents = $shipment_request['Item_contents'];
                                $item_info = 'Package Contents ';
                                $item_info .= '<table style"top:-50px">';
                                if (empty($shipment_contents)) {
                                    $item_info .= '<tr>';
                                    $item_info .= '<td>No Details</td>';
                                    $item_info .= '</tr>';
                                } else {
                                    foreach ($shipment_contents as $shipment_content) {
                                        if (is_array($shipment_content) && !empty($shipment_content)) {
                                            $item_info .= '<tr>';
                                            $item_info .= "<td>" . $shipment_content['description'] . "<td> <td>Quantity - " . $shipment_content['quantity'] . "</td> <td>value - " . $shipment_content['value'] . "</td> <td>HSF - " . $shipment_content['tariff_code'] . "</td> <td>Origin Country - " . $shipment_content['country_of_origin'] . "</td>";
                                            $item_info .= '</tr>';
                                        }
                                    }
                                }
                                $item_info .= '</table>';
                            ?>
                                <tr>
                                    <td align="left" style="padding:0.5%; display: none;"><input type="text" id="packed_product_ids_auspost_elex" size="2" value="<?php echo $products_id_packed; ?>" />&nbsp;</td>
                                    <td align="left" size="2" class="infotip shipment_contents" style="padding: 1%; width: 20% !important"><span class="infotiptext"><?php _e($item_info, 'wf_shipping_auspost') ?></span><strong id="shipmentPackageTitle"><?php echo $shipment_request['Name']; ?>&nbsp;</strong></td>
                                    <?php if ($this->vendor_check) { ?>
                                        <td align="center" class="shipment_description_row_columns">
                                            <p><?php echo (get_user_by('id', $seller_id)->first_name)  ?></p>
                                        </td>
                                    <?php } ?>
                                    <td align="left" class="shipment_description_row_columns"><input type="hidden" id="australia_post_package_manual_cubic_volume" name='elex_auspost_manual_cubic_volume[]' size="2" value="<?php echo ((isset($shipment_request['cubic_volume']) && $shipment_request['cubic_volume'] > 0) ? $shipment_request['cubic_volume'] : 0); ?>" /><input type="text" id="australia_post_package_manual_weight" name='elex_auspost_manual_weight[]' size="2" style="width: 100% !important" value="<?php echo round($shipment_request['Weight']['Value'], 2); ?>" />&nbsp;<?= 'kg'; ?></td>
                                    <td align="left" class="shipment_description_row_columns"><input type="text" id="australia_post_package_manual_length" name='elex_auspost_manual_length[]' size="2" class="shipment_row_columns_input_style" style="width: 100% !important" value="<?php echo round($shipment_request['Dimensions']['Length'], 2); ?>" />&nbsp;<?= 'cm'; ?></td>
                                    <td align="left" class="shipment_description_row_columns"><input type="text" id="australia_post_package_manual_width" name='elex_auspost_manual_width[]' size="2" class="shipment_row_columns_input_style" style="width: 100% !important" value="<?php echo round($shipment_request['Dimensions']['Width'],2); ?>" />&nbsp;<?= 'cm'; ?></td>
                                    <td align="left" class="shipment_description_row_columns"><input type="text" id="australia_post_package_manual_height" name='elex_auspost_manual_height[]' size="2" class="shipment_row_columns_input_style" style="width: 100% !important" value="<?php echo round($shipment_request['Dimensions']['Height'],2); ?>" />&nbsp;<?= 'cm'; ?></td>
                                    <td align="left" class="shipment_description_row_columns">
                                        <select class="select elex-auspost-package-service" id="australia_post_package_manual_service">';
                                            <?php if (empty($postage_products_eligible) && empty($postage_products_eligible_startrack)) {
                                                foreach ($this->settings['services'] as $service_code => $service) {
                                                    if (ctype_alnum($service_code)) {
                                                        echo '<option value="' . $service_code . '" ' . selected($selected_service, $service_code) . ' >' . $service['name'] . '</option>';
                                                    }
                                                }
                                            } else {
                                                if (is_array($all_eligible_postage_products) && !empty($all_eligible_postage_products)) {

                                                    foreach ($all_eligible_postage_products as $postage_product_eligible) {
                                                        if ($this->vendor_check && $vendor_shipping_service && isset($vendor_shipping_service[$seller_id]) && $vendor_shipping_service[$seller_id]['shipping_service'] == $postage_product_eligible['type']) {
                                                            $service_for_creating_shipment = $vendor_shipping_service[$seller_id]['shipping_service'];
                                                            break;
                                                        } elseif ($shipment_service_selected != '' && $postage_product_eligible['type'] == $shipment_service_selected) {
                                                            $service_for_creating_shipment = $shipment_service_selected;
                                                        }
                                                    }
                                                }

                                                if ($service_for_creating_shipment == '') {
                                                    if ($this->rate_type != 'startrack') {
                                                        if ($shipping_country == 'AU') {
                                                            $service_for_creating_shipment = $default_domestic_shipment_service_auspost;
                                                        } else {
                                                            $service_for_creating_shipment = $default_international_shipment_service_auspost;
                                                        }
                                                    } else {
                                                        $service_for_creating_shipment = $starTrack_default_shipment_service;
                                                    }
                                                }
                                                if (is_array($postage_products_eligible) && !empty($postage_products_eligible)) {
                                                    echo "<option class='service_label' disabled>AusPost</option>";
                                                    foreach ($postage_products_eligible as $product) {
                                                        $product_id = $product['product_id'];
                                                        if (ctype_alnum($product_id)) {
                                                            if ($shipping_country == 'AU') {
                                                                if (isset($product['group'])) {
                                                                    if ($service_for_creating_shipment == $product['type'] || $service_for_creating_shipment == $product_id ) {
                                                                        echo "<option value='" . $product_id . "' selected>" . $product['type'] . "</option>";
                                                                    } else {
                                                                        echo "<option value='" . $product_id . "'>" . $product['type'] . "</option>";
                                                                    }
                                                                }
                                                            } else if (!isset($product['group'])) {
                                                                if ($service_for_creating_shipment == $product['type'] || $service_for_creating_shipment == $product_id) {
                                                                    echo "<option value='" . $product_id . "' selected>" . $product['type'] . "</option>";
                                                                } else {
                                                                    echo "<option value='" . $product_id . "'>" . $product['type'] . "</option>";
                                                                }
                                                            }
                                                        }
                                                    }
                                                }

                                                if ($shipping_country == 'AU' && is_array($postage_products_eligible_startrack) && !empty($postage_products_eligible_startrack)) {
                                                    echo "<option class='service_label' disabled>StarTrack</option>";
                                                    foreach ($postage_products_eligible_startrack as $product) {
                                                        $product_id = $product['product_id'];
                                                        if (ctype_alnum($product_id)) {
                                                            if ($shipping_country == 'AU') {
                                                                if ($this->startrack_enabled && (isset($product['service_type']) && $product['service_type'] == 'startrack')) {
                                                                    if ($service_for_creating_shipment == $product['type'] || $service_for_creating_shipment == $product_id ) {
                                                                        echo "<option value='" . $product_id . "startrack' selected>" . $product['type'] . "</option>";
                                                                    } else {
                                                                        echo "<option value='" . $product_id . "startrack'>" . $product['type'] . "</option>";
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                            echo '</select>' ?>
                                    </td>
                                    <?php

                                    if ($shipping_country != 'AU') {
                                    ?>
                                        <td align="left" style="padding:0.5%" class="classification_description_column">
                                            <select class="australia_post_item_category">
                                                <option value="OTHER" selected><?php _e('OTHER', 'wf-shipping-auspost'); ?></option>
                                                <option value="SALE_OF_GOODS"><?php _e('SALE OF GOODS', 'wf-shipping-auspost'); ?></option>
                                                <option value="GIFT"><?php _e('GIFT', 'wf-shipping-auspost'); ?></option>
                                                <option value="SAMPLE"><?php _e('SAMPLE', 'wf-shipping-auspost'); ?></option>
                                                <option value="DOCUMENT"><?php _e('DOCUMENT', 'wf-shipping-auspost'); ?></option>
                                                <option value="RETURN"><?php _e('RETURN', 'wf-shipping-auspost'); ?></option>
                                            </select>
                                        </td>
                                        <td align="left" style="padding:0.5%"><input style="width: 100% !important" type="text" class="auspost_category_other_description" placeholder="Sale"></td>
                                    <?php } ?>
                                    <td style="padding:0.5%;"><span class="dashicons dashicons-dismiss" style="cursor: pointer; padding-right: 5% !important" id="remove_package_auspost_elex"></span></td>
                                </tr>
                            <?php

                                $request_package_count++;
                            }
                            ?>
                        </tbody>
                    </table>
                    <a class="button tips onclickdisable add_extra_packages" style="margin: 1%" data-tip="<?php _e('Add extra packages', 'wf-shipping-auspost'); ?>"><?php _e('Add Extra Packages', 'wf-shipping-auspost'); ?></a>
                </div>
                <li>
                    <input type="checkbox" id="auspost_logo_check" value='yes' <?php echo (($this->branded) ? 'checked' : ''); ?>><?php _e('Show Australia Post Logo on Shipment Label', 'wf-shipping-auspost') ?>
                </li>
                <li>
                    <a class="button button-primary tips onclickdisable create_shipment" href="<?php echo $generate_url; ?>" data-tip="<?php _e('Create Shipment', 'wf-shipping-auspost'); ?>"><?php _e('Create Shipment', 'wf-shipping-auspost'); ?></a>
                </li>
                <li>
                    <a class="button button-primary tips onclickdisable generate_packages" href="<?php echo $generate_packages_url; ?>" data-tip="<?php _e('Re-Generate Packages', 'wf-shipping-auspost'); ?>"><?php _e('Re-Generate Packages', 'wf-shipping-auspost'); ?></a>
                </li>
            <?php } else { ?>
                <li>
                    <a class="button button-primary tips onclickdisable generate_packages" href="<?php echo $generate_packages_url; ?>" data-tip="<?php _e('Generate Packages', 'wf-shipping-auspost'); ?>"><?php _e('Generate Packages', 'wf-shipping-auspost'); ?></a>
                </li>
            <?php } ?>
        <?php
        }
        echo '</ul>';
        ?>
        <script>
            jQuery(document).ready(function() {

                jQuery('.add_extra_packages').prop("disabled", false);
                var category_arr = new Array();
                var description_for_other_arr = new Array();
                var index = 0;
                jQuery('.shipment_table_auspost_elex').each(function() {
                    if (jQuery(".australia_post_item_category").val() == "OTHER") {
                        jQuery('.decription_of_other_row:eq(' + index + ')').show();
                    } else {
                        jQuery('.decription_of_other_row:eq(' + index + ')').hide();
                    }
                    index++;
                });

                jQuery(".australia_post_item_category").on('change', function(e) {
                    e.preventDefault();
                    var selected_option = jQuery(this).find('option:selected').html();
                    var index_clicked = jQuery('.australia_post_item_category').index(this);
                    if (selected_option == "OTHER") {
                        jQuery('.decription_of_other_row:eq(' + index_clicked + ')').show();
                    } else {
                        jQuery('.decription_of_other_row:eq(' + index_clicked + ')').hide();
                    }
                });

                jQuery("a.create_shipment").one("click", function() {
                    jQuery(this).click(function() {
                        return false;
                    });
                    var packageTitleArray = jQuery("strong[id='shipmentPackageTitle']").map(function() {
                        return jQuery(this).text();
                    }).get();
                    var packageTitle = JSON.stringify(packageTitleArray);
                    var manual_weight_array = jQuery("input[id='australia_post_package_manual_weight']").map(function() {
                        return jQuery(this).val();
                    }).get();
                    var manual_weight = JSON.stringify(manual_weight_array);

                    var manual_height_array = jQuery("input[id='australia_post_package_manual_height']").map(function() {
                        return jQuery(this).val();
                    }).get();
                    var manual_height = JSON.stringify(manual_height_array);

                    var manual_width_array = jQuery("input[id='australia_post_package_manual_width']").map(function() {
                        return jQuery(this).val();
                    }).get();
                    var manual_width = JSON.stringify(manual_width_array);

                    var manual_length_array = jQuery("input[id='australia_post_package_manual_length']").map(function() {
                        return jQuery(this).val();
                    }).get();
                    var manual_length = JSON.stringify(manual_length_array);

                    var shipment_services_array = jQuery("select[id='australia_post_package_manual_service']").map(function() {
                        return jQuery(this).val();
                    }).get();
                    var shipment_services = JSON.stringify(shipment_services_array);

                    var shipment_content = jQuery("#shipment_content").val();

                    var item_category_arr = jQuery("select[class='australia_post_item_category']").map(function() {
                        return jQuery(this).val();
                    }).get();
                    var item_category = item_category_arr + '';
                    var description_for_other_arr = jQuery("input[class='auspost_category_other_description']").map(function() {
                        return jQuery(this).val();
                    }).get();
                    var description_of_other_str = description_for_other_arr + '';
                    var auspost_logo_on_label = '';
                    if (jQuery('#auspost_logo_check').is(':checked')) {
                        auspost_logo_on_label = 'yes';
                    } else {
                        auspost_logo_on_label = 'no';
                    }

                    if (item_category != '') {
                        location.href = this.href +
                            '&title=' + packageTitle +
                            '&weight=' + manual_weight +
                            '&length=' + manual_length +
                            '&width=' + manual_width +
                            '&height=' + manual_height +
                            '&shipping_service=' + shipment_services +
                            '&category=' + item_category +
                            '&auspost_logo=' + auspost_logo_on_label;
                        if (description_of_other_str != '') {
                            location.href = this.href +
                                '&title=' + packageTitle +
                                '&weight=' + manual_weight +
                                '&length=' + manual_length +
                                '&width=' + manual_width +
                                '&height=' + manual_height +
                                '&shipping_service=' + shipment_services +
                                '&category=' + item_category +
                                '&description_of_other=' + description_of_other_str +
                                '&auspost_logo=' + auspost_logo_on_label;
                        }
                    } else {
                        location.href = this.href +
                            '&title=' + packageTitle +
                            '&weight=' + manual_weight +
                            '&length=' + manual_length +
                            '&width=' + manual_width +
                            '&height=' + manual_height +
                            '&shipping_service=' + shipment_services +
                            '&auspost_logo=' + auspost_logo_on_label;
                    }

                    return false;
                });

                jQuery('#addPackageLoaderImage').hide();
                var orderId = <?php echo json_encode($order_id); ?>;
                var auspostPostageProducts = <?php echo json_encode($postage_products_eligible); ?>;
                var startrackPostageProducts = <?php echo json_encode($postage_products_eligible_startrack); ?>;
                var destinationCountry = <?php echo json_encode($order->get_shipping_country()); ?>;

                jQuery(document).on('click', '#add_products_extra_packages_auspost_elex', function() {
                    var productsSelected = jQuery('#additional_products_combobox_auspost_elex').val();
                    jQuery('#addPackageLoaderImage').show();
                    var addProductsExtraPackagesAction = jQuery.ajax({
                        type: 'post',
                        url: ajaxurl,
                        data: {
                            action: 'elex_auspost_add_products_extra_packages',
                            productSelected: productsSelected,
                            orderId: orderId
                        },
                        dataType: 'json',
                    });

                    addProductsExtraPackagesAction.done(function(additionalPackages) {
                        jQuery('#addPackageLoaderImage').hide();
                        var tableExtraPackageHtml = '';

                        jQuery.each(additionalPackages, function(packageKey, packageValue) {
                            tableExtraPackageHtml += '<tr>';
                            tableExtraPackageHtml += '<td align="left" style="padding:0.5%; display: none;"><input type="text" id="packed_product_ids_auspost_elex" size="2" />&nbsp;</td>';
                            tableExtraPackageHtml += '<td align="left" size="2" class="infotip shipment_contents" style="padding: 1%; width: 20% !important"><span class="infotiptext">' + packageValue.Name + '</span><strong>' + packageValue.Name + '&nbsp;</strong></td>';
                            tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><input type="hidden" id="australia_post_package_manual_cubic_volume"  name = "elex_auspost_manual_cubic_volume[]" size="2"   value="<?php echo (0) ?>" /><input type="text" id="australia_post_package_manual_weight" class="shipment_row_columns_input_style" size="2" style="width: 60% !important" value="' + packageValue.Weight.Value + '" />  &nbsp;kg</td>';
                            tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><input type="text" id="australia_post_package_manual_length" class="shipment_row_columns_input_style" size="2" value="' + packageValue.Dimensions.Length + '" />&nbsp;cm</td>';
                            tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><input type="text" id="australia_post_package_manual_width" class="shipment_row_columns_input_style" size="2" value="' + packageValue.Dimensions.Width + '" />&nbsp;cm</td>';
                            tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><input type="text" id="australia_post_package_manual_height" class="shipment_row_columns_input_style" size="2" value="' + packageValue.Dimensions.Height + '" />&nbsp;cm</td>';
                            tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><select class="select elex-auspost-package-service" id="australia_post_package_manual_service">';
                            tableExtraPackageHtml += '<option class="service_label" disabled>AusPost</option>';
                            jQuery.each(auspostPostageProducts, function(productKey, productValue) {
                                if (destinationCountry == 'AU' && productValue.group) {
                                    tableExtraPackageHtml += '<option value="' + productValue.product_id + '">' + productValue.type + '</option>';
                                } else if (destinationCountry != 'AU' && !productValue.group) {
                                    tableExtraPackageHtml += '<option value="' + productValue.product_id + '">' + productValue.type + '</option>';
                                }
                            });
                            tableExtraPackageHtml += '<option class="service_label" disabled>StarTrack</option>';
                            jQuery.each(startrackPostageProducts, function(productKey, productValue) {
                                if (destinationCountry == 'AU') {
                                    tableExtraPackageHtml += '<option value="' + productValue.product_id + '">' + productValue.type + '</option>';
                                }
                            });
                            tableExtraPackageHtml += '</select></td>';
                            if (destinationCountry != 'AU') {
                                tableExtraPackageHtml += '<td align="left" style="padding:0.5%" class="classification_description_column">\
                                    <select class="australia_post_item_category">\
                                        <option value="OTHER" selected><?php _e("OTHER", "wf-shipping-auspost"); ?></option>\
                                        <option value="GIFT"><?php _e("GIFT", "wf-shipping-auspost"); ?></option>\
                                        <option value="SAMPLE"><?php _e("SAMPLE", "wf-shipping-auspost"); ?></option>\
                                        <option value="DOCUMENT"><?php _e("DOCUMENT", "wf-shipping-auspost"); ?></option>\
                                        <option value="RETURN"><?php _e("RETURN", "wf-shipping-auspost"); ?></option>\
                                    </select>';
                            }
                            tableExtraPackageHtml += '</td>';
                            if (destinationCountry != 'AU') {
                                tableExtraPackageHtml += '<div class="decription_of_other_div">\
                                        <td align="left" style="padding:0.5%"><input type="text" class="auspost_category_other_description" placeholder="Sale"></td>\
                                    </div>';
                            }
                            tableExtraPackageHtml += '<td style="padding:0.5%;"><span class="dashicons dashicons-dismiss" id="remove_package_auspost_elex" style="cursor: pointer; padding-right: 5% !important"></span></td>';
                            tableExtraPackageHtml += '</tr>';
                        });

                        jQuery('#add_additional_products').remove();
                        jQuery('.table_body_packages_auspost_elex tr').remove();
                        jQuery('.shipment_table_auspost_elex').append(tableExtraPackageHtml);

                    });
                });

                jQuery(document).on('click', '#remove_package_auspost_elex', function(e) {
                    e.preventDefault();
                    if (destinationCountry == 'AU') {
                        jQuery(this).closest('tr').remove();
                    } else {
                        var removedPackageProducts = jQuery(this).closest('td').siblings().find('#packed_product_ids_auspost_elex').val();
                        jQuery(this).closest('tr').remove();
                        var removedPackageProductsArray = removedPackageProducts.split(',');
                        var removePackages = jQuery.ajax({
                            type: 'post',
                            url: ajaxurl,
                            data: {
                                action: 'elex_auspost_remove_packages',
                                packagesSelected: removedPackageProductsArray,
                                orderId: orderId
                            },
                            dataType: 'json',
                        });
                    }
                });

                jQuery(document).on('click', '#cancel_add_extra_packages_auspost_elex', function() {
                    jQuery('#add_additional_products').remove();
                });

                jQuery('.add_extra_packages').on('click', function(e) {
                    e.preventDefault();
                    if (destinationCountry == 'AU') {
                        var tableExtraPackageHtml = '';
                        tableExtraPackageHtml += '<tr>';
                        tableExtraPackageHtml += '<td align="left" style="padding:0.5%" size="2"><strong id="shipmentPackageTitle">Additional Package</strong></td>';
                        tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><input type="hidden" id="australia_post_package_manual_cubic_volume" name = "elex_auspost_manual_cubic_volume[]" size="2"   value="<?php echo (0) ?>" /><input type="text" id="australia_post_package_manual_weight" class="shipment_row_columns_input_style" size="2" style="width: 60% !important" />  &nbsp;kg</td>';
                        tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><input type="text" id="australia_post_package_manual_length" class="shipment_row_columns_input_style" size="2" />&nbsp;cm</td>';
                        tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><input type="text" id="australia_post_package_manual_width" class="shipment_row_columns_input_style" size="2" />&nbsp;cm</td>';
                        tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><input type="text" id="australia_post_package_manual_height" class="shipment_row_columns_input_style" size="2" />&nbsp;cm</td>';
                        tableExtraPackageHtml += '<td class="shipment_description_row_columns"><select class="select elex-auspost-package-service" id="australia_post_package_manual_service">';
                        tableExtraPackageHtml += '<option class="service_label" disabled>AusPost</option>';
                        jQuery.each(auspostPostageProducts, function(productKey, productValue) {
                            if (destinationCountry == 'AU' && productValue.group) {
                                tableExtraPackageHtml += '<option value="' + productValue.product_id + '">' + productValue.type + '</option>';
                            } else if (destinationCountry != 'AU' && !productValue.group) {
                                tableExtraPackageHtml += '<option value="' + productValue.product_id + '">' + productValue.type + '</option>';
                            }
                        });
                        tableExtraPackageHtml += '<option class="service_label" disabled>StarTrack</option>';
                        jQuery.each(startrackPostageProducts, function(productKey, productValue) {
                            if (destinationCountry == 'AU') {
                                tableExtraPackageHtml += '<option value="' + productValue.product_id + '">' + productValue.type + '</option>';
                            }
                        });
                        tableExtraPackageHtml += '</select></td>';
                        tableExtraPackageHtml += '<td style="padding:0.5%;"><span class="dashicons dashicons-dismiss" id="remove_package_auspost_elex" style="cursor: pointer; padding-right: 5% !important"></span></td>';
                        tableExtraPackageHtml += '</tr>';
                        jQuery('.shipment_table_auspost_elex').append(tableExtraPackageHtml);
                    } else {
                        <?php
                        global $wpdb;
                        $query = "SELECT * FROM `" . $wpdb->prefix . "posts` WHERE post_type = 'product' or post_type = 'product_variation_data' ORDER BY `ID` DESC";
                        $products_on_site = $wpdb->get_results($query);
                        $query_variable = "SELECT * FROM `" . $wpdb->prefix . "posts` WHERE post_type = 'product_variation' or post_type = 'product_variation_data' ORDER BY `ID` DESC";
                        $products_on_site_variable = $wpdb->get_results($query_variable);
                        $products_on_site = array_merge($products_on_site, $products_on_site_variable);
                        ?>
                        var productsOnSite = <?php echo json_encode($products_on_site); ?>;

                        var addExtraPackageHtml = '<table><tr id="add_additional_products">';
                        addExtraPackageHtml += '<td style="width: 0.1% !important; padding-left: 1%;"><?php _e("Select Products", "wf-shipping-auspost"); ?></td>';
                        addExtraPackageHtml += '<td style="width: 0.1% !important"><select class="chosen_select" multiple="multiple" id="additional_products_combobox_auspost_elex" name="additional_products_auspost_elex[]">';
                        jQuery.each(productsOnSite, function(productIndex, product) {
                            productId = product["ID"];
                            productTitle = product["post_title"];
                            addExtraPackageHtml += '<option value="' + productId + '">' + productTitle + '</option>';
                        });
                        addExtraPackageHtml += '</select></td>';
                        addExtraPackageHtml += '<td style="width: 0.1%"><a class="button tips onclickdisable" id="add_products_extra_packages_auspost_elex"><?php _e("Add Products", "wf-shipping-auspost"); ?></a></td>';
                        addExtraPackageHtml += '<td style="width: 2%"><a class="button tips onclickdisable" id="cancel_add_extra_packages_auspost_elex"><?php _e("Cancel", "wf-shipping-auspost"); ?></a></td>';
                        var imagePath = "<?php echo untrailingslashit(content_url("plugins/australia-post-woocommerce-shipping/images/ELEX_AusPost_loader.gif")); ?>";
                        addExtraPackageHtml += '<td><img id="addPackageLoaderImage" src="<?php echo untrailingslashit(content_url("plugins/australia-post-woocommerce-shipping/images/ELEX_AusPost_loader.gif")); ?>" style="width: 40%; height: 30%;" ></td>';
                        addExtraPackageHtml += '</tr></table>';
                        jQuery('.shipment_table_auspost_elex').after(addExtraPackageHtml);
                        jQuery('#additional_products_combobox_auspost_elex').selectWoo({
                            width: '100%'
                        });

                        jQuery('#addPackageLoaderImage').hide();
                        jQuery('#add_additional_products').css("width", "2% !important");
                    }
                });

                jQuery(document).on('click', '.arrow-up-auspost-elex', function() {
                    jQuery(this).siblings('div').slideUp("slow");
                    jQuery(this).hide();
                    jQuery(this).siblings('.arrow-down-auspost-elex').show();
                });
                jQuery(document).on('click', '.arrow-down-auspost-elex', function() {
                    jQuery(this).hide();
                    jQuery(this).siblings('.arrow-up-auspost-elex').show();
                    jQuery(this).siblings('div').slideDown("slow");
                });


                jQuery(document).on('click', '.service_radio_button_auspost_elex', function() {
                    var service = jQuery(this).val();
                    jQuery(".elex-auspost-package-service").val(service);
                });
                jQuery(document).on('click', '#auspost_generate_refresh_service_button', function(e) {
                    e.preventDefault();
                    var orderId = <?php echo json_encode($order_id); ?>;
                    var rates_loader_img_html = `<img src=" <?php echo untrailingslashit(plugins_url()) . '/australia-post-woocommerce-shipping/images/load.gif'; ?>"  align="center" style=" display:block;margin-left:auto;margin-right:auto;width:30%;" id="rates_loader_img" class="rates_loader_img">`;
                    jQuery('#elex_auspost_available_services').html(rates_loader_img_html);
                    var manual_weight_array = jQuery("input[id='australia_post_package_manual_weight']").map(function() {
                        return jQuery(this).val();
                    }).get();

                    var manual_cubic_volume_array = jQuery("input[id='australia_post_package_manual_cubic_volume']").map(function() {
                        return jQuery(this).val();
                    }).get();
                    var manual_height_array = jQuery("input[id='australia_post_package_manual_height']").map(function() {
                        return jQuery(this).val();
                    }).get();
                    var manual_width_array = jQuery("input[id='australia_post_package_manual_width']").map(function() {
                        return jQuery(this).val();
                    }).get();
                    var manual_length_array = jQuery("input[id='australia_post_package_manual_length']").map(function() {
                        return jQuery(this).val();
                    }).get();

                    var elexAuspostGetServices = jQuery.ajax({
                        type: 'post',
                        url: ajaxurl,
                        data: {
                            action: 'elex_auspost_get_services',
                            weight: manual_weight_array,
                            length: manual_length_array,
                            width: manual_width_array,
                            height: manual_height_array,
                            cubic_volume: manual_cubic_volume_array,
                            orderId: orderId,
                        },
                        dataType: 'json',
                    });

                    elexAuspostGetServices.done(function(response) {
                        if (response.type == 'success') {
                            var data = response.data;
                            var auspost_service_table_html = ` <span id="elex_auspost_available_services_table_title" style="font-weight:bold;">Available Service/Rates:</span>
                                                            <span class="arrow-down-auspost-elex dashicons dashicons-arrow-down" style="display: none;"></span>
                                                            <span class="arrow-up-auspost-elex dashicons dashicons-arrow-up" style="display: inline-block;"></span>
                                                            <div class="elex-auspost-shipment-package-div" >
                                                                <table id="wf_auspost_service_select_1" class="wf-shipment-package-table" style="margin-top: 20px;box-shadow:.10px .10px 10px lightgrey; width: 100%;">
                                                                    <tbody>`;
                            jQuery.each(data, function(index, value) {
                                auspost_service_table_html += `<tr>
                                                                    <tr style="padding:10px;">
                                                                        <td></td>
                                                                    </tr>
                                                                    <td>
                                                                        <input type="radio" name="service_radio_button_auspost_elex" class="service_radio_button_auspost_elex" id="` + value.actual_code + `" style="align:right" value="` + value.actual_code + `">
                                                                    </td>
                                                                    <td>
                                                                        <small>` + value.label + `</small>
                                                                    </td>
                                                                    <td style="text-align:center">
                                                                        <small> $` + value.cost.toFixed(2) + `</small>
                                                                    </td>
                                                                </tr>`;
                            });
                            auspost_service_table_html += `</tbody></table></div>`;
                            jQuery('#elex_auspost_available_services').html(auspost_service_table_html);
                        } else if (response.type == 'error') {
                            var error_message_html = `<p style="color:red;"><strong><?php _e('Something went wrong!!, Please try again later.', 'wf-shipping-auspost'); ?></strong></p>`;
                            jQuery('#elex_auspost_available_services').html(error_message_html);
                        } else {
                            var error_message_html = `<p style="color:red;"><strong><?php _e('Something went wrong!!, Please try again later.', 'wf-shipping-auspost'); ?></strong></p>`;
                            jQuery('#elex_auspost_available_services').html(error_message_html);
                        }
                    });
                });
            });
        </script>
<?php
    }

    /**
     * get_request_header for JSON function.
     *
     */
    private function buildHttpHeaders($request)
    {

        $request_type_startrack = get_option('create_shipment_for_startrack', false);
        $bulk_request_type_startrack = get_option('create_bulk_orders_shipment_auspost_startrack', false);
        if ($this->vendor_check && $this->vedor_api_key_enable && isset($request['seller_id'])) {
            // Compatibility of Australia Post with ELEX Multivendor Addon 

            $vendor_user_id = $request['seller_id'];
            $api_password = get_the_author_meta('vendor_elex_australia_post_api_password', $vendor_user_id);
            $api_account_number = get_the_author_meta('vendor_elex_australia_post_account_number', $vendor_user_id);
            $api_key = get_the_author_meta('vendor_elex_australia_post_api_key', $vendor_user_id);
            if ($request_type_startrack || $bulk_request_type_startrack) {
                $api_password = get_the_author_meta('vendor_elex_australia_post_startrack_api_password', $vendor_user_id);
                $api_account_number = get_the_author_meta('vendor_elex_australia_post_startrack_account_number', $vendor_user_id);
            }
        } else {
            $api_password = $this->api_pwd;
            $api_account_number = $this->api_account_no;
            $api_key = $this->api_key;
            if ($request_type_startrack || $bulk_request_type_startrack) {
                $api_password = $this->api_pwd_startrack;
                $api_account_number = $this->api_account_no_startrack;
                $api_key = $this->api_key_starTrack;
            }
        }

        $a_headers = array(
            'Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_password),
            'content-type' => 'application/json',
            'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
            'Account-Number' => $api_account_number
        );
        return $a_headers;
    }

    /**
     * Output a message
     */
    public function debug($message, $type = 'notice')
    {
        if ($this->debug || $type == 'error') {
            echo ($message);
        }
    }


    private function is_refunded_item($order, $item_id)
    {
        $qty = 0;
        if ($order) {
            foreach ($order->get_refunds() as $refund) {
                foreach ($refund->get_items($item_type) as $refunded_item) {
                    if (isset($refunded_item['product_id']) && $refunded_item['product_id'] == $item_id) {
                        $qty += $refunded_item['qty'];
                    }
                }
            }
        }
        return $qty * -1;
    }
    private function wf_get_order_id($order)
    {
        global $woocommerce;
        return (WC()->version < '2.7.0') ? $order->id : $order->get_id();
    }

    private function custom_order_number($order_id)
    {
        $order = wc_get_order($order_id);
        if($order){
            return $order->get_order_number();
        }    
        return $order_id;
    }

    public function elex_auspost_get_services()
    {
        if (!isset($_POST['orderId']))
            die();

        $order_id = $_POST['orderId'];
        $shipment_requests = get_post_meta($order_id, 'shipment_packages_auspost_elex', true);

        $package = array();
        foreach ($_POST['weight'] as $key => $item) {
            $package['items'][] = array(
                'weight' => $_POST['weight'][$key],
                'length' => $_POST['length'][$key],
                'width' => $_POST['width'][$key],
                'height'  => $_POST['height'][$key],
                'cubic_volume' => $_POST['cubic_volume'][$key]
            );
        }
        $package['packing_method'] = $shipment_requests[0]['packing_method'];
        $package['pack_type'] = $shipment_requests[0]['pack_type'];
        $this->found_rates = array();
        $shipping_services = $this->elex_auspost_get_shipping_services($package, $order_id);

        $response = array(
            'type' => 'success',
            'data' => $this->found_rates
        );
        die(json_encode($response));
    }
    public function elex_auspost_get_shipping_services($package, $order_id)
    {
        $postage_products_auspost = array();
        $postage_products_startrack = array();
        $user_startrack_services =array();
        $get_accounts_endpoint_auspost = 'https://' . self::API_HOST . self::API_BASE_URL . 'accounts/' . $this->api_account_no;
        if ($this->contracted_api_mode == 'live') {
            $get_accounts_endpoint_auspost = str_replace('test/', '', $get_accounts_endpoint_auspost);
        }
        $contracted_account_details_auspost = $this->get_services($get_accounts_endpoint_auspost, $this->api_account_no,  $this->api_pwd);
        $contracted_account_details_auspost = json_decode($contracted_account_details_auspost, true);
        if (isset($contracted_account_details_auspost['postage_products']) && !empty($contracted_account_details_auspost['postage_products'])) {
            $postage_products_auspost = $contracted_account_details_auspost['postage_products'];
        }
        if(isset($this->api_account_no_startrack)){
            $get_accounts_endpoint_startrack = 'https://' . self::API_HOST . self::API_BASE_URL . 'accounts/' . $this->api_account_no_startrack;
            if ($this->contracted_api_mode == 'live') {             
                $get_accounts_endpoint_startrack = str_replace('test/', '', $get_accounts_endpoint_startrack);
            }
            $contracted_account_details_startrack = $this->get_services($get_accounts_endpoint_startrack, $this->api_account_no_startrack, $this->api_pwd_startrack, $this->api_key_starTrack);
            $contracted_account_details_startrack = json_decode($contracted_account_details_startrack, true);
            if (isset($contracted_account_details_startrack['postage_products']) && !empty($contracted_account_details_startrack['postage_products'])) {
                $postage_products_startrack = $contracted_account_details_startrack['postage_products'];
            }
            $user_startrack_services = (isset($this->general_settings['startrack_services']) && !empty($this->general_settings['startrack_services'])) ? $this->general_settings['startrack_services'] : array();
        }
       
		$settings_services = array_merge($this->general_settings['services'], $user_startrack_services);
        $order = wc_get_order($order_id);

        if (is_array($package['items'])) {
            $packing_method = $package['packing_method'];
            $pack_type = $package['pack_type'];
            $package_requests_size = count($package['items']);
            $count_package_requests = 0;
            $is_international = ($order->get_shipping_country() == 'AU') ? false : true;
            $group_shipping_enabled = ($this->general_settings['group_shipping'] && $this->general_settings['group_shipping'] == 'yes' && $packing_method == 'per_item') ? true : false;

            $from = array(
                "suburb" => $this->settings['origin_suburb'],
                "state" => $this->settings['origin_state'],
                "postcode" => $this->settings['origin']
            );

            $to = array(
                "suburb" => $order->get_shipping_city(),
                "state" => $order->get_shipping_state(),
                "postcode" => $order->get_shipping_postcode(),
                'country' => $order->get_shipping_country()
            );
            if ($group_shipping_enabled == false || $is_international) {
                foreach ($package['items'] as $key => $package_request) {
                    $count_package_requests++;
                    $startrack_service_rates = array();

                    if ($this->rate_type == 'startrack' && !$is_international) {
                        $rates = array();
                        /*For StarTrack*/

                        $service_rate_iteration = 0;
                        $startrack_service_rates_array = array();
                        foreach ($user_startrack_services as $settings_service_key => $settings_service_value) {
                            $service_rate_iteration++;
                            if ($settings_service_value['enabled']) {
                                if (isset($package_request['cubic_volume']) && $package_request['cubic_volume'] > 0 && $packing_method == 'weight') {
                                    $items_node = array(
                                        'weight' => round($package_request['weight'], 3),
                                        'cubic_volume' => (round($package_request['cubic_volume'], 3) > 0) ? round($package_request['cubic_volume'], 3) : 0.001,
                                    );
                                } else {
                                    $items_node = array(
                                        'weight' => round($package_request['weight'], 3),
                                        'length' => round($package_request['length'], 1),
                                        'width' => round($package_request['width'], 1),
                                        'height' => round($package_request['height'], 1),
                                    );
                                }
                                $items_node['product_id'] = $settings_service_key; // 'PRM',
                                $items_node['packaging_type'] = $pack_type;
                                $shipments = new stdClass();
                                $shipments->from = $from;
                                $shipments->to = $to;
                                $shipments->items = array($items_node);
                                $request_params = new stdClass();
                                $request_params->shipments = array($shipments);
                                $endpoint = 'https://' . self::API_HOST . self::API_BASE_URL . 'prices/shipments/';
                                $headers = $this->buildHttpHeadersServices($request_params, $this->api_account_no_startrack, $this->api_pwd_startrack, $this->api_key_starTrack);
                                if ($this->settings['contracted_api_mode'] == 'live') {
                                    $endpoint = str_replace('test/', '', $endpoint);
                                }

                                $startrack_service_rates_for_package = $this->get_contracted_rates($endpoint, $request_params, $headers, "startrack");

                                if (empty($startrack_service_rates_for_package)) {
                                    $response = array(
                                        'type' => 'error',
                                        'message' => "Australia Post Didn't respond. Please Try Again Later"
                                    );
                                    die(json_encode($response));
                                }
                                $startrack_service_rates_array[] = $startrack_service_rates_for_package;
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
                    }

                    if (!empty($this->api_pwd) && !empty($this->api_account_no)) {
                        /*For AusPost*/
                        $rates = array();
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

                                $items_node = array(
                                    'weight' => round($package_request['weight'], 3),
                                    'length' => round($package_request['length'], 1),
                                    'width' => round($package_request['width'], 1),
                                    'height' => round($package_request['height'], 1),
                                );
                                $items_node['product_id'] = $settings_service_key; // 'PRM',
                                $items_node['packaging_type'] = $pack_type;
                                $shipments = new stdClass();
                                $shipments->from = $from;
                                $shipments->to = $to;
                                $shipments->items = array($items_node);
                                $request_params = new stdClass();
                                $request_params->shipments = array($shipments);
                                $endpoint = 'https://' . self::API_HOST . self::API_BASE_URL . 'prices/shipments/';
                                $headers = $this->buildHttpHeadersServices($request_params, $this->api_account_no,  $this->api_pwd);
                                if ($this->settings['contracted_api_mode'] == 'live') {
                                    $endpoint = str_replace('test/', '', $endpoint);
                                }
                                $auspost_service_rates_for_package = $this->get_contracted_rates($endpoint, $request_params, $headers, "auspost");

                                if (empty($auspost_service_rates_for_package)) {
                                    $response = array(
                                        'type' => 'error',
                                        'message' => "Australia Post Didn't respond. Please Try Again Later"
                                    );
                                    die(json_encode($response));
                                }
                                if (isset($auspost_service_rates_for_package['error_message'])) {
                                } else {
                                    $auspost_service_rates_response[] = $auspost_service_rates_for_package;
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

                                    if ($wc_main_settings['include_exclude_gst'] == "exclude" && isset($rates_price->total_cost_ex_gst)) {
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

                    if (!empty($this->api_pwd) && !empty($this->api_account_no)) {
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
                                    if ($wc_main_settings['include_exclude_gst'] == "exclude") {
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
                        if (is_array($postage_products)) {
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
                                                    if (is_object($all_service_rates)) {
                                                        $all_service_rates = (array) $all_service_rates;
                                                    }                                                   

                                                    $rate['enabled'] = true;
                                                    $this->found_rates[$postage_product['product_id']]['cost'] = $rate['cost'];
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } else if ($group_shipping_enabled == true && !$is_international) {
                $package_requests_size = 1;
                $count_package_requests++;
                $group_shipping_items_array = array();
                $index = 0;
                foreach ($package['items'] as $key => $package_request) {
                    $group_shipping_items_array[$index++] = array(
                        'weight' => round($package_request['weight'], 3),
                        'length' => round($package_request['length'], 1),
                        'width' => round($package_request['width'], 1),
                        'height' => round($package_request['height'], 1),
                    );
                }

                $startrack_service_rates = array();

                if ($this->rate_type == 'startrack' && !$is_international) {
                    $rates = array();


                    $service_rate_iteration = 0;
                    $startrack_service_rates_array = array();
                    foreach ($user_startrack_services as $settings_service_key => $settings_service_value) {
                        if ($settings_service_key != 'FPP') {
                            $service_rate_iteration++;
                            if ($settings_service_value['enabled']) {
                                $group_shipping_items_array = array();
                                $index = 0;
                                foreach ($package['items'] as $key => $package_request) {
                                    $group_shipping_items_array[$index++] = array(
                                        'weight' => round($package_request['weight'], 3),
                                        'length' => round($package_request['length'], 1),
                                        'width' => round($package_request['width'], 1),
                                        'height' => round($package_request['height'], 1),
                                        'product_id' => $settings_service_key,
                                        'packaging_type' => (isset($package_request['pack_type']) && !empty($package_request['pack_type'])) ? $package_request['pack_type'] : 'ITM',
                                    );
                                }
                                $shipments = new stdClass();
                                $shipments->from = $from;
                                $shipments->to = $to;
                                $shipments->items = $group_shipping_items_array;
                                $request_params = new stdClass();
                                $request_params->shipments = array($shipments);
                                $endpoint = 'https://' . self::API_HOST . self::API_BASE_URL . 'prices/shipments/';
                                $headers = $this->buildHttpHeadersServices($request_params, $this->api_account_no_startrack, $this->api_pwd_startrack, $this->api_key_starTrack);
                                if ($this->settings['contracted_api_mode'] == 'live') {
                                    $endpoint = str_replace('test/', '', $endpoint);
                                }

                                $startrack_service_rates_for_package = $this->get_contracted_rates($endpoint, $request_params, $headers, "startrack");

                                if (empty($startrack_service_rates_for_package)) {
                                    $response = array(
                                        'type' => 'error',
                                        'message' => "Australia Post Didn't respond. Please Try Again Later"
                                    );
                                    die(json_encode($response));
                                }
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
                }

                if (!empty($this->api_pwd) && !empty($this->api_account_no)) {
                    /*For AusPost*/

                    $rates = array();


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

                            $group_shipping_items_array = array();
                            $index = 0;
                            foreach ($package['items'] as $key => $package_request) {
                                $group_shipping_items_array[$index++] = array(
                                    'weight' => round($package_request['weight'], 3),
                                    'length' => round($package_request['length'], 1),
                                    'width' => round($package_request['width'], 1),
                                    'height' => round($package_request['height'], 1),
                                    'product_id' => $settings_service_key,
                                    'packaging_type' => (isset($package_request['pack_type']) && !empty($package_request['pack_type'])) ? $package_request['pack_type'] : 'ITM',
                                );
                            }
                            $shipments = new stdClass();
                            $shipments->from = $from;
                            $shipments->to = $to;
                            $shipments->items = $group_shipping_items_array;
                            $request_params = new stdClass();
                            $request_params->shipments = array($shipments);
                            $endpoint = 'https://' . self::API_HOST . self::API_BASE_URL . 'prices/shipments/';
                            $headers = $this->buildHttpHeadersServices($request_params, $this->api_account_no,  $this->api_pwd);
                            if ($this->settings['contracted_api_mode'] == 'live') {
                                $endpoint = str_replace('test/', '', $endpoint);
                            }
                            $auspost_service_rates_for_package = $this->get_contracted_rates($endpoint, $request_params, $headers, "auspost");
                            if (empty($auspost_service_rates_for_package)) {
                                return;
                            }
                            if (isset($auspost_service_rates_for_package['error_message'])) {
                            } else {
                                $auspost_service_rates_response[] = $auspost_service_rates_for_package;
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
                    } elseif (isset($startrack_service_rates_array) && !empty($startrack_service_rates_array)) {
                        foreach ($startrack_service_rates_array as $auspost_service_rate) {
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

                                if ($wc_main_settings['include_exclude_gst'] == "exclude" && isset($rates_price->total_cost_ex_gst)) {
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

                if (!empty($this->api_pwd) && !empty($this->api_account_no)) {
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
                                if ($wc_main_settings['include_exclude_gst'] == "exclude") {
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
                $postage_products = array_merge($postage_products_auspost, $postage_products_startrack);

                if (!empty($this->found_rates)) {
                    if (is_array($postage_products)) {
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
                                                $add_extra_cover = false;
                                                if (isset($this->settings['show_insurance_checkout_field']) && (($this->settings['show_insurance_checkout_field'] === 'yes' && $this->insurance_requested_at_checkout) || ($this->settings['show_insurance_checkout_field'] == '' && isset($settings_service['extra_cover']) && $settings_service['extra_cover'] == true))) {
                                                    $add_extra_cover = true;
                                                }
                                                $extra_cover_package = $order->get_subtotal();
                                                if ($add_extra_cover) {
                                                    if ($is_international) {
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
                                                $rate['enabled'] = true;
                                                $this->found_rates[$postage_product['product_id']]['cost'] = $rate['cost'];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
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
            return array();
        }
        $response_array = isset($res['body']) ? json_decode($res['body']) : array();
        $shipment_rates_result = array();
        if ($rate_type == 'startrack') {
            /*For StarTRack*/
            if (!empty($response_array->errors)) {
                return array();
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
    private function buildHttpHeadersServices($request, $api_account_number, $api_password ,$api_key = FALSE)
    {
        $api_key = $api_key? $api_key: $this->api_key;
        $a_headers = array(
            'content-type' => 'application/json',
            'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
            'Account-Number' => $api_account_number,
            'Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_password),
        );
        return $a_headers;
    }
    public function get_services($endpoint, $account_number, $account_password)
    {
        $header = '';
        $responseBody = '';

        $account_password = str_replace('&lt;', '<', $account_password);
        $account_password = str_replace('&gt;', '>', $account_password);
        $args = array(
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => array(
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
                'Account-Number' => $account_number,
                'Authorization' => 'Basic ' . base64_encode($this->api_key . ":" . $account_password)
            ),
        );
        $response = wp_remote_get($endpoint, $args);
        if (is_array($response)) {
            $header = $response['headers']; // array of http header lines
            $responseBody = $response['body']; // use the content
        }

        return $responseBody;
    }
    private function prepare_rate($rate_code, $rate_id, $rate_name, $rate_cost, $package_request = '')
    {

        $rate_actual_code = $rate_code;

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
        $user_startrack_services = (isset($this->general_settings['startrack_services']) && !empty($this->general_settings['startrack_services'])) ? $this->general_settings['startrack_services'] : array();
        $settings_services = array_merge($this->general_settings['services'], $user_startrack_services);
        $rate_name = $settings_services[$rate_actual_code]['name'];
        if (!empty($user_startrack_services) && array_key_exists($rate_actual_code, $user_startrack_services)) {
            $rate_actual_code .= 'startrack';
        }
        if (array_key_exists($rate_id, $this->found_rates)) {

            $this->found_rates[$rate_id]['cost'] += $rate_cost;
            $this->found_rates[$rate_id]['packages'] = $packages;
        } else {
            $this->found_rates[$rate_id] = array(
                'id' => $rate_id,
                'label' => $rate_name,
                'cost' => $rate_cost,
                'sort' => $sort,
                'packages' => $packages,
                'actual_code' => $rate_actual_code
            );
        }
    }
    public function string_clean($string) { 

        $string = preg_replace('/[^A-Za-z0-9\-]/', ' ', $string); // Removes special chars.
        $string = str_replace('  ', ' ', $string);
        return $string;

    }
    public function get_composite_product_items($order_items){
        if(!empty($order_items)){
            $new_order_items = array();
            foreach($order_items as $key => $order_item){
                $product = $order_item->get_product();
                if($product->is_type( 'composite' )){
                    if(!$product->is_virtual()){
                        $new_order_items[$key] = $order_item;
                    }

                }else{
                    $composite_parent = $order_item->get_meta( '_composite_parent', true );
                    $composite_item = $order_item->get_meta( '_composite_item', true );
                    $composite_data = $order_item->get_meta( '_composite_data', true );
                    if($composite_parent && $composite_item && $composite_data && isset($composite_data) && isset($composite_data[$composite_item]) &&  isset($composite_data[$composite_item]['composite_id']) ){
                        $composite_parent_id = $composite_data[$composite_item]['composite_id'];
                        $product_composite = wc_get_product($composite_parent_id); 
                        if($product_composite->is_type( 'composite' )){
                            if($product_composite->is_virtual()){
                                $new_order_items[$key] = $order_item;
                            }
            
                        }else{
                            $new_order_items[$key] = $order_item;
                        }             
                    }else{
                        $new_order_items[$key] = $order_item;
                    }
                }
            }
            $order_items = $new_order_items;
        }
        return $order_items;
    }
}

new wf_australia_post_shipping_admin();
?>