<?php

/*Making compatible with PHP 7.1 later versions*/
if (version_compare(phpversion(), '7.1', '>=')) {
	ini_set('serialize_precision', -1); // Avoiding adding of unnecessary 17 decimal places resulted from json_encode
}
class wf_australia_mypost_shipping_admin {


	const API_HOST     = 'api.reachship.com';
	const API_BASE_URL = '/sandbox/v1/';

	private $european_union_countries = array('AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HU', 'HR', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK');
	/** Services called from 'services' API without options */
	private $services = array();

	public function __construct() {
		if (!class_exists('WF_ausmypost_services')) {
			include_once 'settings/class_wf_ausmypost_services.php';
		}

		add_action('wp_ajax_elex_ausmypost_add_products_extra_packages', array($this, 'elex_ausmypost_add_products_extra_packages'));
		add_action('wp_ajax_elex_ausmypost_remove_packages', array($this, 'elex_ausmypost_remove_packages'));
		add_action('wp_ajax_elex_ausmypost_get_services', array($this, 'elex_ausmypost_get_services'));

		$auspost_services_obj = new WF_ausmypost_services();
		/** Services called from 'services' API without options */
		$this->services                 = $auspost_services_obj->get_services(); // these services are defined statically
		$this->settings                 = get_option('woocommerce_wf_australia_mypost_settings');
		$this->settings_services        = $this->settings['services'];
		$this->weight_dimensions_manual = 'no';
		$this->custom_services          = isset($this->settings['services']) ? $this->settings['services'] : array();
		$this->mypost_contracted_rates  = isset($this->settings['client_account_name']) && ''!==$this->settings['client_account_name'] ? true : false;
		$this->debug                    = ( isset($this->settings['debug_mode']) && ( $this->settings['debug_mode'] == 'yes' ) ) ? true : false;
		$this->merchent_token           = isset($this->settings['client_account_name']) && ''!==$this->settings['client_account_name'] ? $this->settings['client_account_name'] : '';
		$this->client_id               	= isset($this->settings['client_id']) && ''!==$this->settings['client_id'] ? $this->settings['client_id'] : '';
		$this->client_secret          	= isset($this->settings['client_secret']) && ''!==$this->settings['client_secret'] ? $this->settings['client_secret'] : '';
		$this->access_token			   	= get_transient( 'wf_australia_mypost_access_token' );
		if ($this->access_token && ''!==$this->client_id && ''!==$this->client_secret) {
			$this->access_token = isset($this->settings['access_token']) && ''!==$this->settings['access_token'] ? $this->settings['access_token'] : '';
		} else {
			$this->access_token = $this->get_reachship_access_token();
		}
		$this->rate_type            = 'auspost';
		$this->startrack_enabled    = false;
		$multi_vendor_add_on_active = ( in_array('multi-vendor-add-on-for-thirdparty-shipping/multi-vendor-add-on-for-thirdparty-shipping.php', apply_filters('active_plugins', get_option('active_plugins'))) )? true:false;
		$this->vendor_check         = ( $multi_vendor_add_on_active && ( isset($this->settings['vendor_check']) && ( $this->settings['vendor_check'] == 'yes' ) ) ) ? true : false;

		$this->contracted_api_mode = isset($this->settings['mode_check']) ? $this->settings['mode_check'] : 'test';
		$this->contracted_rates    = isset($this->settings['contracted_rates']) && ( $this->settings['contracted_rates'] == 'yes' ) ? true : false;

		$this->is_woocommerce_composite_products_installed = ( in_array('woocommerce-composite-products/woocommerce-composite-products.php', get_option('active_plugins')) ) ? true : false;
		$this->weight_boxes                                = isset($this->settings['weight_boxes']) ? $this->settings['weight_boxes'] : array();
		$this->shipper_postcode                            = isset($this->settings['origin']) ? $this->settings['origin'] : '';
		$this->shipper_name                                = isset($this->settings['origin_name']) ? $this->settings['origin_name'] : '';
		$this->shipper_state                               = isset($this->settings['origin_state']) ? $this->settings['origin_state'] : '';
		$this->shipper_suburb                              = isset($this->settings['origin_suburb']) ? $this->settings['origin_suburb'] : '';
		$this->shipper_address                             = isset($this->settings['origin_line']) ? $this->settings['origin_line'] : '';
		$this->shipper_phone_number                        = isset($this->settings['shipper_phone_number']) ? $this->settings['shipper_phone_number'] : '';
		$this->ship_content                                = isset($this->settings['ship_content']) ? $this->settings['ship_content'] : 'Shipment Contents';
		$this->shipper_email                               = isset($this->settings['shipper_email']) ? $this->settings['shipper_email'] : '';
		$this->dir_download                                = ( isset($this->settings['dir_download']) && $this->settings['dir_download'] == 'yes' ) ? 'attachment' : 'inline';
		$this->email_tracking                              = ( isset($this->settings['email_tracking']) && $this->settings['email_tracking'] == 'yes' ) ? true : false;
		$this->cus_type                                    = isset($this->settings['cus_type']) ? $this->settings['cus_type'] : 'STANDARD_ADDRESS';
		$this->enable_label                                = ( isset($this->settings['enabled_label']) && $this->settings['enabled_label'] === 'yes' ) ? true : false;
		$this->general_settings                            = get_option('woocommerce_wf_australia_mypost_settings');
		$this->dimension_unit                              = strtolower(get_option('woocommerce_dimension_unit'));
		$this->weight_unit                                 = strtolower(get_option('woocommerce_weight_unit'));
		$this->create_shipment_error                       = get_option('wf_create_shipment_error');
		$this->boxpacking_error                            = get_option('wf_create_boxpacking_error');
		$this->create_shipment_success                     = get_option('wf_create_shipment_success');
		$this->weight_packing_process                      = !empty($this->settings['weight_packing_process']) ? $this->settings['weight_packing_process'] : 'pack_descending'; // This feature will be implementing in next version
		$this->order_package_categories_arr                = array(); // contains types of categories of packages in an order
		$this->order_desc_for_other_category_arr           = array(); // contains array of descriptions per package for international shipments
		$this->branded                                     = !empty($this->settings['shipment_label_type']) ? true : false;
		$this->pickup_check                                = !empty($this->settings['shipment_pickup_service']) ? true : false;
		$this->is_request_bulk_shipment                    = get_option('create_bulk_orders_shipment_auspost', false);
		$this->is_request_bulk_startrack_shipment          = get_option('create_bulk_orders_shipment_auspost_startrack', false);
		//For storing the shipping service, weight and dimensions overridden by the user in the metabox table
		$this->weights_in_request_array           = array();
		$this->lengths_in_request_array           = array();
		$this->widths_in_request_array            = array();
		$this->heights_in_request_array           = array();
		$this->shipment_services_in_request_array = array();
		$this->order_shipping_service             = '';
		$this->shipment_id                        = '';
		$this->order_id                           = '';
		$this->default_service                    = '';
		$this->packing_method                     = $this->settings['packing_method'];
		global $wpdb;
		$query = 'SELECT ID FROM `' . $wpdb->prefix . "posts` WHERE post_type = 'shop_order' ORDER BY `ID` DESC LIMIT 1";

		$this->new_order_id                    = $wpdb->get_results($query);
		$this->new_order_id                    = array_shift($this->new_order_id);
		$this->insurance_requested_at_checkout = false;
		$last_order_id                         = get_option('last_order_id');

		if (!empty($this->new_order_id) && ( empty($last_order_id) || $last_order_id != $this->new_order_id )) {
			$this->elex_ausmypost_update_order_meta($this->new_order_id);
			delete_option('ausmypost_extra_cover_checkout');
			delete_option('ausmypost_signature_required_checkout');
		}

		if ( is_admin() && $this->enable_label && isset($this->settings['client_account_name'])) {

			add_action('add_meta_boxes', array($this, 'wf_add_australia_mypost_metabox'));
		}

		if (isset($_GET['elex_mypost_generate_packages'])) {
			add_action('init', array($this, 'elex_mypost_generate_packages'), 10);
		}


		if (isset($_GET['wf_australiamypost_createshipment'])) {
			add_action('init', array($this, 'wf_australiamypost_createshipment'), 10);
		}

		if (isset($_GET['wf_australiamypost_viewlabel'])) {
			add_action('init', array($this, 'wf_australiamypost_viewlabel'));
		}

		if (isset($_GET['wf_australiapost_void_shipment'])) {
			add_action('init', array($this, 'wf_australiapost_void_shipment'));
		}

		if (isset($_GET['wf_australiamypost_delete_shipment'])) {
			add_action('init', array($this, 'wf_australiamypost_delete_shipment'));
		}
		
		if (isset($_GET['wf_australiamypost_create_shipment_pickup'])) {
			add_action('init', array($this, 'wf_australiamypost_create_shipment_pickup'));
		}
		if (isset($_GET['wf_australiamypost_create_shipment_order'])) {
			add_action('init', array($this, 'wf_australiamypost_create_shipment_order'));
		}
		if (isset($_GET['wf_australiamypost_create_shipment_label'])) {
			add_action('init', array($this, 'wf_australiamypost_create_shipment_label'));
		}
		add_action('load-edit.php', array($this, 'wf_auspost_bulk_order_actions')); //to handle bulk actions selected in 'shop-order' page
		add_action('admin_notices', array($this, 'wf_auspost_bulk_label_admin_notices'));

		//StarTrack
		add_action('load-edit.php', array($this, 'elex_auspost_startrack_bulk_order_actions')); //to handle bulk actions selected in 'shop-order' page
		add_action('admin_notices', array($this, 'elex_auspost_startrack_bulk_label_admin_notices'));
	}

	/**
	 * function to generate shipment packages
	 *
	 * @access public
	 * @param string woocommerce order id, boolean 
	 */
	public function elex_mypost_generate_packages( $current_order_id = '', $contains_failed_packages = false) {
		$order_id          = !empty($_GET['elex_mypost_generate_packages']) ? $_GET['elex_mypost_generate_packages'] : $current_order_id;
		$order             = new WC_Order($order_id);
		$shipment_packages = $this->elex_ausmypost_get_order_shipment_packages($order);

		if (!$contains_failed_packages) {
			wp_redirect(admin_url('/post.php?post=' . $order_id . '&action=edit'));
			exit;
		}

		return;
	}

	/**
	 * function to remove packages from a woocommerce order id
	 *
	 * @access public
	 */
	public function elex_ausmypost_remove_packages() {
		if (!isset($_POST['packagesSelected'])) {
			die();
		}

		$order_id    = $_POST['orderId'];
		$order       = wc_get_order($order_id);
		$order_items = $order->get_items();
		if (is_array($_POST['packagesSelected'])) {
			foreach ($_POST['packagesSelected'] as $product_id) {
				foreach ($order_items as $order_item_key => $order_item) {
					$order_item_data = $order_item->get_data();
					$order_item_id   = ( $order_item_data['variation_id'] != 0 ) ? $order_item_data['variation_id'] : $order_item_data['product_id'];
					if ($order_item_id == $product_id) {
						wc_delete_order_item($order_item_key);
						break;
					}
				}
			}
		}
		$this->elex_ausmypost_get_order_shipment_packages($order);
		update_option('removed_package_status_ausmypost_elex', true);
		die('done');
	}

	/**
	 * function to generate shipment packages based on the packing options selected in the settings
	 *
	 * @access public
	 * @param woocommerce order
	 * @return array shipment packages
	 */
	public function elex_ausmypost_get_order_shipment_packages( $order) {

		if ($this->packing_method == 'weight') {
			$shipment_packages = $this->weight_based_packing($order);
			
			if (!empty($shipment_packages)) {
				$shipment_packages[0]['packing_method'] = 'weight';
			}
		} elseif ($this->packing_method == 'box_packing') {
			$shipment_packages = $this->box_packing($order);
			if (!empty($shipment_packages)) {
				$shipment_packages[0]['packing_method'] = 'box_packing';
			}
		} else {
			$shipment_packages = $this->per_item_packing($order);
			if (!empty($shipment_packages)) {
				$shipment_packages[0]['packing_method'] = 'per_item';
			}
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
		if (!empty($shipment_packages)) {
			foreach ($shipment_packages as $shipment_package) {
				if ($this->weight_unit != 'kg') {
					$shipment_package['Weight']['Value'] = wc_get_weight($shipment_package['Weight']['Value'], 'kg', $from_weight_unit);
				}

				if ($this->dimension_unit != 'cm') {
					$shipment_package['Dimensions']['Length'] = wc_get_dimension($shipment_package['Dimensions']['Length'], 'cm', $from_dimension_unit);
					$shipment_package['Dimensions']['Width']  = wc_get_dimension($shipment_package['Dimensions']['Width'], 'cm', $from_dimension_unit);
					$shipment_package['Dimensions']['Height'] = wc_get_dimension($shipment_package['Dimensions']['Height'], 'cm', $from_dimension_unit);
				}
			}
		}
		update_post_meta($order_id, 'shipment_packages_ausmypost_elex', $shipment_packages);
		return $shipment_packages;
	}

	/**
	 * function to add extra packages to the current order
	 *
	 * @access public
	 */
	public function elex_ausmypost_add_products_extra_packages() {
		if (!isset($_POST['productSelected'])) {
			die();
		}

		$selected_products     = array();
		$selected_products_ids = $_POST['productSelected'];
		$order_id              = $_POST['orderId'];
		$order                 = wc_get_order($order_id);
		foreach ($selected_products_ids as $selected_products_id) {
			$selected_product = wc_get_product($selected_products_id);
			$order->add_product($selected_product);
		}

		$shipment_packages = $this->elex_ausmypost_get_order_shipment_packages($order);
		die(json_encode($shipment_packages));
	}

	/**
	 * function to add custom checkout field values as meta data for the provided order
	 *
	 * @access private
	 * @param woocommerce order id
	 */
	private function elex_ausmypost_update_order_meta( $new_order_id) {
		if (isset($this->settings['enabled']) && !empty($this->settings['enabled'])) {
			$is_extra_cover_requested = get_option('ausmypost_extra_cover_checkout');
			$is_signature_required    =  get_option('ausmypost_signature_required_checkout');
			if (!empty($is_extra_cover_requested)) {
				update_post_meta($new_order_id->ID, 'extra_cover_opted_ausmypost_elex', $is_extra_cover_requested);
			}
			if (!empty($is_signature_required)) {
				update_post_meta($new_order_id->ID, 'signature_required_opted_ausmypost_elex', $is_signature_required);
			}

			update_option('last_order_id', $new_order_id);

			return;
		}
	}

	/**
	 * function display box packingerror notices in admin page
	 *
	 * @access private
	 * @param error statements
	 */

	private function show_boxpacking_error_notice() {
		echo '
        <div class="notice notice-error is-dismissible">
            <p>' . $this->boxpacking_error . '</p>
        </div>
        ';
		delete_option('wf_create_boxpacking_error');
	}

	/**
	 * function display success notices in admin page
	 *
	 * @access private
	 * @param success statements
	 */

	private function show_success_notice() {
		echo '
        <div class="notice notice-success is-dismissible">
            <p>' . $this->create_shipment_success . '</p>
        </div>
        ';
		delete_option('wf_create_shipment_success');
	}
	public function wf_australiamypost_create_shipment_pickup() {
		$order_id = isset($_GET['wf_australiamypost_create_shipment_pickup']) ? $_GET['wf_australiamypost_create_shipment_pickup'] : '';

		$this->pickup_date     ='';
		$this->pickup_time_id  ='';
		$pickup_shipment_check = false;

		if (isset($_GET['pickup_date']) && '' !== $_GET['pickup_date'] && isset($_GET['pickup_time_id']) && '' !== $_GET['pickup_time_id'] ) {
			$this->pickup_date     = $_GET['pickup_date'];
			$this->pickup_time_id  = $_GET['pickup_time_id'];
			$pickup_shipment_check = true;
		}
		if (!empty($order_id)) {

			$user_ok = $this->wf_user_permission();
			if (!$user_ok) {
				return;
			}

			$order = $this->wf_load_order($order_id);
			if (!$order) {
				return;
			}

			if ($pickup_shipment_check) {
				$shipment_ids     = get_post_meta($order_id, 'wf_woo_australiamypost_shipmentId', true);
				$service_base_url = 'https://' . self::API_HOST . self::API_BASE_URL;
				
				if ($this->contracted_api_mode == 'live') {
					$service_base_url = str_replace('test/', '', $service_base_url);
				}

				if (!empty($shipment_ids)) {
					$this->create_pickup_for_shipment($order, $shipment_ids);
				}
	
			}

			if ($this->debug) {
				echo '<a href="' . admin_url('/post.php?post=' . $order_id . '&action=edit') . '">' . __('Back to Order', 'wf-shipping-auspost') . '</a>';
				//For the debug information to display in the page
				die();
			} else {
				wp_redirect(admin_url('/post.php?post=' . $order_id . '&action=edit'));
				exit;
			}
		}

		return;
	}
	
	public function wf_australiamypost_create_shipment_order() {
		$order_id = isset($_GET['wf_australiamypost_create_shipment_order']) ? $_GET['wf_australiamypost_create_shipment_order'] : '';

		if (!empty($order_id)) {

			$user_ok = $this->wf_user_permission();
			if (!$user_ok) {
				return;
			}

			$order = $this->wf_load_order($order_id);
			if (!$order) {
				return;
			}
			$austalia_post_pickup_number = get_post_meta($order_id, 'wf_australia_mypost_pickup', array());
			$pickup_id                   ='';
			if (isset($austalia_post_pickup_number[0])) {
				$pickup_id =array_key_first($austalia_post_pickup_number[0]);
			}

			$shipment_ids     = get_post_meta($order_id, 'wf_woo_australiamypost_shipmentId', true);
			$service_base_url = 'https://' . self::API_HOST . self::API_BASE_URL;
			if ($this->contracted_api_mode == 'live') {
				$service_base_url = str_replace('sandbox/', 'production/', $service_base_url);
			}

			$package_index = range(0 , count($shipment_ids) - 1);
			if (!empty($shipment_ids)) {
				$this->create_order_for_shipment($order, $shipment_ids, $service_base_url, $package_index);
			}

			if ($this->debug) {
				echo '<a href="' . admin_url('/post.php?post=' . $order_id . '&action=edit') . '">' . __('Back to Order', 'wf-shipping-auspost') . '</a>';
				//For the debug information to display in the page
				die();
			} else {
				wp_redirect(admin_url('/post.php?post=' . $order_id . '&action=edit'));
				exit;
			}
		}

		return;
	}
	public function wf_australiamypost_create_shipment_label() {
		$order_id = isset($_GET['wf_australiamypost_create_shipment_label']) ? $_GET['wf_australiamypost_create_shipment_label'] : '';

		if (!empty($order_id)) {

			$user_ok = $this->wf_user_permission();
			if (!$user_ok) {
				return;
			}

			$order = $this->wf_load_order($order_id);
			if (!$order) {
				return;
			}
			
			$shipment_ids     = get_post_meta($order_id, 'wf_woo_australiamypost_shipmentId', true);
			$service_base_url = 'https://' . self::API_HOST . self::API_BASE_URL;

			if ($this->contracted_api_mode == 'live') {
				$service_base_url = str_replace('sandbox/', 'production/', $service_base_url);
			}

			if (!empty($shipment_ids)) {

				$this->generate_label_package($order, $shipment_ids, $service_base_url);
			}

			if ($this->debug) {
				echo '<a href="' . admin_url('/post.php?post=' . $order_id . '&action=edit') . '">' . __('Back to Order', 'wf-shipping-auspost') . '</a>';
				//For the debug information to display in the page
				die();
			} else {
				wp_redirect(admin_url('/post.php?post=' . $order_id . '&action=edit'));
				exit;
			}
		}

		return;
	}
	public function wf_australiamypost_delete_shipment() {
		if (!class_exists('WF_Tracking_Admin_AusPost')) {
			include plugins_url(basename(plugin_dir_path(__FILE__)) . '/australia_post/includes/class-wf-tracking-admin.php', basename(__FILE__));
		}

		$tracking_admin = new WF_Tracking_Admin_AusPost();

		$order_id     = isset($_GET['wf_australiamypost_delete_shipment']) ? $_GET['wf_australiamypost_delete_shipment'] : '';
		$shipment_id  = isset($_GET['wf_shipment_id']) ? $_GET['wf_shipment_id'] : '';
		$shipment_ids = explode(',', $shipment_id);
		unset($shipment_ids[count($shipment_ids)-1]);
		
		if (!empty($order_id) && !empty($shipment_id)) {

			$user_ok = $this->wf_user_permission();
			if (!$user_ok) {
				return;
			}

			$order = $this->wf_load_order($order_id);
			if (!$order) {
				return;
			}

			$order_number = $order->get_order_number();

			$service_base_url = 'https://' . self::API_HOST . self::API_BASE_URL . 'delete-shipments';

			if ($this->contracted_api_mode == 'live') {
				$service_base_url = str_replace('sandbox/', 'production/', $service_base_url);
			}

			$rqs_headers = $this->buildHttpHeaders();
			$delete_req  = array (
				'carrier_name' => 'AUSPOST_MYPOST',
				'shipment_ids' => $shipment_ids,
			);
			$res         = wp_remote_post($service_base_url, array(
				'method' =>'POST',
				'httpversion' => '1.1',
				'timeout' => 70,
				'headers' => $rqs_headers,
				'body' => json_encode($delete_req)
			));

			if (is_wp_error($res)) {
				$error_string = $res->get_error_message();
				$this->debug('Australia Post Delete Label <br><pre>');
				$this->debug($error_string . '<br><pre>', 'error');
			} else {
				$res_body_decode = json_decode($res['body']);

				if ( !empty($res_body_decode) && 200 == $res['response']['code'] && empty($res_body_decode->failed_to_delete_shipment_ids)) {

					delete_post_meta($order_id, 'wf_woo_australiamypost_labelURI');
					delete_post_meta($order_id, 'elex_ausmypost_label_uris');
					delete_post_meta($order_id, 'elex_auspost_label_request_ids');
					delete_post_meta($order_id, 'wf_australia_mypost_order');
					delete_post_meta($order_id, 'wf_australia_mypost_pickup');
					delete_post_meta($order_id, 'wf_woo_australiamypost_labelURI');
					delete_post_meta($order_id, 'wf_woo_australiamypost_labelId');
					delete_post_meta($order_id, 'wf_ausmypost_tracking_ids');
					$order_shipment_ids = get_post_meta($order_id, 'wf_woo_australiamypost_shipmentId', true);
					if (is_array($order_shipment_ids) && !empty($order_shipment_ids)) {
						foreach ($order_shipment_ids as $order_shipment_id) {
							delete_post_meta($order_id, 'elex_ausmypost_shipping_service_' . $order_shipment_id);
						}
					}
					delete_post_meta($order_id, 'wf_woo_australiamypost_shipmentId');
	
					delete_post_meta($order_id, 'failed_create_shipment_packages_ausmypost_elex');
					delete_post_meta($order_id, 'consolidated_failed_create_shipment_packages_ausmypost_elex');
	
					delete_option('wf_create_shipment_success');
	
					do_action('elex_after_deleting_shipment', $order_id );
					$tracking_admin->delete_tracking_information($order_id); // calling tracking data delete function  
					$this->debug($res_body_decode->message . '<br><pre>'); 
				} else {
					$this->debug($res_body_decode->message . '<br><pre>', 'error');
				}
			}  
			wp_redirect(admin_url('/post.php?post=' . $order_id . '&action=edit'));  
			exit;
		}
	}

	public function wf_australiapost_void_shipment() {
		$user_ok = $this->wf_user_permission();
		if (!$user_ok) {
			return;
		}

		$void_params = explode('||', base64_decode($_GET['wf_australiapost_void_shipment']));

		if (empty($void_params) || !is_array($void_params) || count($void_params) != 2) {
			return;
		}

		$shipment_id = $void_params[0];
		$order_id    = $void_params[1];

		$service_url = $this->serviceUrl . $this->mailedBy . '/' . $this->mobo . '/shipment' . '/' . $shipment_id;
		$response    = wp_remote_post($service_url, array(
			'method' => 'DELETE',
			'timeout' => 70,
			'sslverify' => 0,
			'headers' => $this->wf_get_request_header('application/vnd.cpc.shipment-v7+xml', 'application/vnd.cpc.shipment-v7+xml')
		));

		$void_error_message = '';
		$void_success       = false;
		if (!empty($response['response']['code']) && $response['response']['code'] == '204') {
			$void_success = true;
		} elseif (!empty($response['body'])) {
			$response = $response['body'];
		} else {
			$response = '';
		}


		if ($void_success == false) {
			$void_error_message = 'void shipment failed.';
			libxml_use_internal_errors(true);
			$xml = simplexml_load_string('<root>' . preg_replace('/<\?xml.*\?>/', '', $response) . '</root>');
			if (!$xml) {
				$void_error_message .= 'Failed loading XML;';
				$void_error_message .= $response . ';';
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
			add_post_meta($order_id, 'wf_woo_australiamypost_shipment_void', $shipment_id, false);
		}

		update_post_meta($order_id, 'wf_woo_australiamypost_shipment_void_errormessage', $void_error_message);

		wp_redirect(admin_url('/post.php?post=' . $order_id . '&action=edit'));
		exit;
	}

	public function wf_load_order( $orderId) {
		if (!class_exists('WC_Order')) {
			return false;
		}
		if (!class_exists('wf_order')) {
			include_once 'class-wf-legacy.php';
		}
		return ( WC()->version < '2.7.0' ) ? new WC_Order($orderId) : new wf_order($orderId);
	}

	private function wf_user_permission() {
		// Check if user has rights to generate invoices
		$current_user = wp_get_current_user();
		$user_ok      = false;
		if ($current_user instanceof WP_User) {
			if (in_array('administrator', $current_user->roles) || in_array('shop_manager', $current_user->roles)) {
				$user_ok = true;
			}
		}
		return $user_ok;
	}

	/*function to retrieve the weight and dimensions posted by the user in the metabox table*/
	private function return_package_data_from_request( $request_element) {
		$request_element       = stripcslashes($request_element);
		$request_element       = str_replace(array('[', ']', '"'), '', $request_element);
		$request_element_array = explode(',', $request_element);

		return $request_element_array;
	}

	public function wf_australiamypost_createshipment() {
		$user_ok = $this->wf_user_permission();
		if (!$user_ok) {
			return;
		}

		$order = $this->wf_load_order($_GET['wf_australiamypost_createshipment']);
		if (!$order) {
			return;
		}

		$order_id = $this->wf_get_order_id($order);

		if (isset($_GET['shipping_service']) && !empty($_GET['shipping_service'])) {
			$this->order_shipping_service = $_GET['shipping_service'];
		}

		/* Obtaining the categories provided for packages for international shipments*/
		$order_package_categories = ( isset($_GET['category']) && !empty($_GET['category']) ) ? $_GET['category'] : '';
		if (!empty($order_package_categories)) {
			$this->order_package_categories_arr = explode(',', $order_package_categories);
		}

		$order_desc_for_other_category = ( isset($_GET['description_of_other']) && !empty($_GET['description_of_other']) ) ? $_GET['description_of_other'] : '';

		/* Obtaining description for the category OTHER for international shipments */
		if (!empty($order_desc_for_other_category)) {
			$this->order_desc_for_other_category_arr = explode(',', $order_desc_for_other_category);
		} else {
			$this->order_desc_for_other_category_arr = array('Sale');
		}

		/* Obtaining the option from the user to print or not to print AusPost logo on the Shipment labels */
		if (isset($_GET['ausmypost_logo'])) {
			if ($_GET['ausmypost_logo'] == 'yes') {
				$this->branded = true;
			} else {
				$this->branded = false;
			}
		}
		
		$this->wf_create_shipment($order);

		if ($this->debug) {
			$tracking_message_key = get_post_meta($order_id, 'tracking_message_key', true);
			$tracking_message_val = get_post_meta($order_id, 'tracking_message_value', true);
			if (is_array($tracking_message_key) && !empty($tracking_message_key[0])) {
				echo '<a href="' . admin_url('/post.php?post=' . $_GET['wf_australiamypost_createshipment'] . '&action=edit&' . $tracking_message_key[0] . '=' . $tracking_message_val[0]) . '">' . __('Back to Order', 'wf-shipping-auspost') . '</a>';
			}
			echo '<a href="' . admin_url('/post.php?post=' . $_GET['wf_australiamypost_createshipment'] . '&action=edit') . '">' . __('Back to Order', 'wf-shipping-auspost') . '</a>';
			//For the debug information to display in the page
			die();
		} else {
			wp_redirect(admin_url('/post.php?post=' . $_GET['wf_australiamypost_createshipment'] . '&action=edit'));
			exit;
		}
		
		return;
	}

	public function wf_australiamypost_viewlabel() {

		$shipment_id  = isset($_GET['shipment_id']) ? $_GET['shipment_id'] : '';
		$order_number = isset($_GET['order_number']) ? $_GET['order_number'] : '';
		$file         = ELEX_AUSPOST_LABELS . 'Order_' . $order_number . '_' . $shipment_id . '_label.pdf';
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
			$service_url =  get_post_meta($_GET['wf_australiamypost_viewlabel'], 'wf_woo_australiamypost_labelURI', true);
			$service_url = wp_remote_get($service_url);
			if (is_wp_error($service_url)) {
				$error_string = $service_url->get_error_message();
				$this->debug('Australia MyPost Business Download Label <br><pre>');
				$this->debug($error_string . '<br><pre>', 'error');
			} else {
				$upload_dir = wp_upload_dir();
				$base       = $upload_dir['basedir'];
				$path       = $base . '/elex-auspost-download-labels/';
				wp_mkdir_p($path);
				if ($_GET['order_number']) {
					$order_number = $_GET['order_number'];
					$file         = 'Australia-Post-' . $order_number . '-' . $_GET['shipment_id'] . '.pdf';
				} else {
					$file = 'Australia-Post-' . $_GET['shipment_id'] . '.pdf';
				}
				$file_path = $path . $file;
				file_put_contents($file_path, $service_url['body']);
				$path     = $file_path;
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

	public function get_content( $URL) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $URL);
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
	}

	private function wf_is_service_valid_for_country( $order, $service_code) {
		$service_valid = false;
		if ($order->get_shipping_country() == 'AU') {
			return strpos($service_code, 'AUS_') !== false;
		} else {
			return strpos($service_code, 'INTL_') !== false;
		}
		return $service_valid;
	}

	private function wf_get_shipping_service( $order, $retrive_from_order = false, $bulk = '') {
		if ($retrive_from_order == true) {
			$service_code = get_post_meta($this->wf_get_order_id($order), 'wf_woo_australiamypost_service_code', true);
			if (!empty($service_code)) {
				return $service_code;
			}
		}
		if ($bulk) {
			$shipping_methods = $order->get_shipping_methods();
			$shipping_method  = array_shift($shipping_methods);
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
		if (strpos($shipping_method['method_id'], WF_AUSTRALIA_MYPOST_ID) > 0) {
			return str_replace(WF_AUSTRALIA_MYPOST_ID . ':', '', $shipping_method['method_id']);
		} else {
			return $shipping_method['name'];
		}
	}

	private function wf_load_product( $product) {
		if (!$product) {
			return false;
		}
		return ( WC()->version < '2.7.0' ) ? $product : new wf_product($product);
	}

	/**
	 * function to get highest dimension among all the packed products in weight based packing
	 *
	 * @access public
	 */

	public function return_highest( $dimension_array) {
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
	private function weight_based_packing( $order) {
		global $woocommerce;
		if (!class_exists('Elex_Weight_Boxpack')) {
			include_once 'class-wf-weight-packing.php';
		}
		$is_request_create_shipment = get_option('request_to_create_shipment');

		$failed_shipment_order_packages = get_post_meta($order->get_id(), 'consolidated_failed_create_shipment_packages_ausmypost_elex', true);
		$order_id                       = $this->wf_get_order_id($order);
		if ($is_request_create_shipment) {
			delete_post_meta($order_id, 'failed_create_shipment_packages_ausmypost_elex');
		}
		$weight_pack = new Elex_Weight_Boxpack( $this->weight_packing_process );
		
		if ( $this->weight_boxes ) {
			
			foreach ( $this->weight_boxes as $key => $box ) {

				if (!$box['enabled']) {
					continue;
				}

				$newbox = $weight_pack->add_weight_box( $box['length'], $box['width'], $box['height'], $box['min_weight'] , $box['max_weight'] , $box['name']);
				
				if (isset($box['id'])) {
					$newbox->set_id($box['id']);
				}
				if (isset($box['name'])) {
					$newbox->set_name($box['name']);
				}
				$newbox->set_max_weight($box['max_weight']);
				
				$newbox->set_min_weight($box['min_weight']);
			}

		}

		$package_total_weight = 0;
		$insured_value        = 0;
		$insurance_array      = array(
			'Amount' => 0,
			'Currency' => get_woocommerce_currency()
		);
		$to_ship              = array();

		/* For WooCommerce Composite Products */
		if ($this->is_woocommerce_composite_products_installed) {
			$package = $this->get_composite_product_data($package);
		}

		$ctr        = 0;
		$orderItems = $order->get_items();
		$orderItems = apply_filters( 'elex_order_items', $orderItems );
		if (empty($orderItems)) {
			return;
		}
		
		foreach ($orderItems as $orderItem) {
			$data = $orderItem->get_data();
			$ctr++;
			

			$product_id = isset($data['variation_id']) && ( $data['variation_id'] != 0 ) ? $data['variation_id'] : $data['product_id'];
			$product    = wc_get_product($product_id);

			if ($product->is_virtual()) {
				// If it's is a virtual product skip it.
				$this->debug(sprintf(__('Product #%d is virtual. Skipping.', 'wf-australia-post'), $ctr));

				continue;
			}

			$product_data = array();
			$product      = wc_get_product($data['variation_id'] ? $data['variation_id'] : $data['product_id']);
			if ($data['variation_id']) {
				$product_parent_data    = $product->get_parent_data();
				$product_variation_data = $product->get_data();
				$product_data			= $product->get_data();

				$product_data['weight'] = !empty($product_variation_data['weight']) ? $product_variation_data['weight'] : $product_parent_data['weight'];
				$product_data['length'] = !empty($product_variation_data['length']) ? $product_variation_data['length'] : $product_parent_data['length'];
				$product_data['width']  =  !empty($product_variation_data['width']) ?  $product_variation_data['width']  : $product_parent_data['width'];
				$product_data['height'] = !empty($product_variation_data['height']) ? $product_variation_data['height'] : $product_parent_data['height'];
				if (!isset($product_data['price']) && empty($product_data['price'])) {
					$temp_product_data     = $product->get_data();
					$product_data['price'] = $temp_product_data['price'];
				}

			} elseif ($data['product_id']) {
				$product_data = $product->get_data();
			}

			if (empty($product_data['weight']) && empty($product_data['length']) && empty($product_data['width']) && empty($product_data['height'])) {
				update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', 'Products does not contain weights and/or dimensions');
			}

			for ($i = 1; $i <= $data['quantity']; $i++) {
				$weight_pack->add_item(wc_get_weight( $product_data['weight'], $this->weight_unit ), array( 'data'=> $product_data ), 1, $product_data['price']);
			}

			//	$weight_pack->add_item(wc_get_weight( $values['data']->get_weight(), $this->weight_unit ), array( 'data'=> $values['data'] ), $values['quantity'],$values['data']->get_price());
		}

		$weight_pack->pack(); 
		$pack      = $weight_pack->get_packages(); 
		$all_items =   array();
	
		if (is_array($pack) && !empty($pack)) {
			foreach ($pack as $packable_item => $values) {
				if ($values->unpacked === true) {
					
					update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', 'Item not packed in any box');
					return array();
				}
				$all_items[] =   $values->packed;
			}
		}
		$to_ship  = array();
		$group_id = 1;
		foreach ($pack as $package) {
			if ($package->unpacked === true) {
				$this->debug('Item not packed in any box');
			} else {
				$this->debug('Packed ' . $package->name);
			}
			$dimensions = array($package->length, $package->width, $package->height);

			sort($dimensions);
			$insurance_array        = array(
				'Amount' => round($package->value),
				'Currency' => get_woocommerce_currency()
			);
			$packed_products        =   isset($package->packed) ? $package->packed : $all_items;
			$weight_packed_products = array();

			foreach ($packed_products as $i => $packed_product) {
				
				if (isset($packed_product->product_data['data']['id']) && ''!== $packed_product->product_data['data']['id'] ) {
					$weight_packed_products[$i]['product_id'] =  $packed_product->product_data['data']['id'];
				}
				$weight_packed_products[$i]['quantity'] =  $packed_product->quantity;
				$weight_packed_products[$i]['name']     =  ( isset($packed_product->$i->product_data['data']['name']) && !empty($packed_product->$i->product_data['data']['name']) ) ? $packed_product->$i->product_data['data']['name'] : '';
			}
			$result_array               = $this->multi_dimensional_array_unique($weight_packed_products, 'product_id');
			$product_age_check_selected = '';
			$package_items              = array();
			if (is_array($result_array) && !empty($result_array)) {
				foreach ($result_array as $result_array_element) {
					$product_id        = $result_array_element['product_id'];
					$product_parent_id = '';
					$product_details   = wc_get_product($product_id);
					if (empty($product_age_check_selected)) {
						$product_age_check_selected = get_post_meta($product_id, 'age_check_auspost_elex', true);
					}

					$product_weight       = get_post_meta($result_array_element['product_id'], '_weight', true);
					$product_value        = (float) $product_details->get_price();
					$product_details_info = $product_details->get_data();
					$product_parent_id    = $product_details_info['parent_id'];
					if ($product_weight == 0 || empty($product_weight)) {
						if (!empty($product_parent_id)) {
							$product_weight = get_post_meta($product_parent_id, '_weight', true);
						}
					}

					$package_item_description = 'none';
					if (!empty($product_parent_id)) {
						$search_product_id        = get_post_meta($product_parent_id, '_wf_shipping_description', true);
						$package_item_description = ( !empty($search_product_id) && $search_product_id != 'NA' ) ? $search_product_id : $result_array_element['name'];
	
					} else {
						$search_product_id        = get_post_meta($product_id, '_wf_shipping_description', true);
						$package_item_description = ( !empty($search_product_id) && $search_product_id != 'NA' ) ? $search_product_id : $result_array_element['name'];	
					}

					$package_items[] = array(
						'description'               => ( strlen($package_item_description) > 40 ) ? substr( $this->string_clean( $package_item_description ), 0, 37) . '...' : $package_item_description,
						'quantity'                  => $result_array_element['quantity'],
						'value'                     => $product_value,
						'tariff_code'               => ( $product_parent_id != 0 ) ? get_post_meta($product_parent_id, '_wf_tariff_code', 1) : get_post_meta($result_array_element['product_id'], '_wf_tariff_code', 1),
						'country_of_origin'         => ( $product_parent_id != 0 ) ? get_post_meta($product_parent_id, '_wf_country_of_origin', 1) : get_post_meta($result_array_element['product_id'], '_wf_country_of_origin', 1),
						'weight'                    => ( $this->weight_unit != 'kg' ) ? round(wc_get_weight($product_weight, 'kg', $this->weight_unit), 2) : round($product_weight, 2),
						'export_declaration_number' => ( $product_parent_id != 0 ) ? get_post_meta($product_parent_id, '_wf_export_declaration_number', 1) : get_post_meta($result_array_element['product_id'], '_wf_export_declaration_number', 1)
					);
				}
			}
			
			$group = array(
				'GroupNumber' => $group_id,
				'Name' => $package->name,
				'GroupPackageCount' => 1,
				'Weight' => array(
					'Value' => round($package->weight, 3),
					'Units' => $this->weight_unit
				),
				'Dimensions' => array(
					'Length' => max(0.1, round($dimensions[2], 3)),
					'Width' => max(0.1, round($dimensions[1], 3)),
					'Height' => max(0.1, round($dimensions[0], 3)),
					'Units' => $this->dimension_unit
				),
				'InsuredValue' => $insurance_array,
				'Item_contents'=>$package_items,
				'packed_products' => array(),
				'package_id' => $package->id,
				'packtype' => isset($package->packtype)?$package->packtype:'BOX'
			);

			if (!empty($package->packed) && is_array($package->packed)) {
				foreach ($package->packed as $packed) {
					$group['packed_products'][] = $packed->get_meta('data');
				}
			}
			$to_ship[] = $group;

			$group_id++;
		}
			
		return $to_ship;

	}

	/**
	 * function to get an unique multidimensional array
	 *
	 * @access private
	 * @param array $array base array| string $key reference to unique
	 * @return array
	 */
	private function multi_dimensional_array_unique( $array, $key) {
		$keys_array = array();

		if (!empty($array)) {
			foreach ($array as $array_element) {
				$keys_array[] = $array_element[$key];
			}
		}

		$keys_array            = array_unique($keys_array);
		$resultant_array       = array();
		$count_resultant_array = 0;
		if (!empty($keys_array)) {
			foreach ($keys_array as $keys_array_element) {
				foreach ($array as $array_element) {
					if ($array_element[$key] == $keys_array_element) {
						if (isset($resultant_array[$count_resultant_array])) {
							$resultant_array[$count_resultant_array]['quantity']++;
						} else {
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
	private function per_item_packing( $order) {
		global $woocommerce;
		$is_request_create_shipment = get_option('request_to_create_shipment');
		$order_shipping_country     = wf_get_order_shipping_country($order);
		$domestic                   = $order_shipping_country == 'AU' ? 'yes' : 'no';
		$order_id                   = $order->get_id();

		$failed_shipment_order_packages = get_post_meta($order_id, 'consolidated_failed_create_shipment_packages_ausmypost_elex', true);

		if ($is_request_create_shipment) {
			delete_post_meta($order_id, 'consolidated_failed_create_shipment_packages_ausmypost_elex');
		}


		$requests   = array();
		$orderItems = $order->get_items();
		$orderItems = apply_filters( 'elex_order_items', $orderItems );
		
		$parcel_count = 0;
		$to_ship      = array();

		if (!empty($orderItems) && is_array($orderItems)) {
			
			if ($this->is_woocommerce_composite_products_installed) {
				$orderItems = $this->get_composite_product_items($orderItems);
			}
			// Get weight of order
			$seller_id             = false;
			$vendor_origin_address = false;
			foreach ($orderItems as $item_id => $item) {

				if (empty($failed_shipment_order_packages) || ( !empty($failed_shipment_order_packages) && in_array($parcel_count, $failed_shipment_order_packages) )) {
					$dangerous_goods_data = false;
					$item_data            = $item->get_data();
					$product_id           = isset($item_data['variation_id']) && ( $item_data['variation_id'] != 0 ) ? $item_data['variation_id'] : $item_data['product_id'];
					$product              = wc_get_product($product_id);
					
					if ($product->is_virtual()) {
						// If it's is a virtual product skip it.
						continue;
					}
								
					$product_id                 = isset($item_data['variation_id']) && ( $item_data['variation_id'] != 0 ) ? $item_data['variation_id'] : $item_data['product_id'];
					$product_age_check_selected = get_post_meta($product_id, 'age_check_auspost_elex', true);
					$product                    = wc_get_product($product_id);
					$product_ordered_quantity   = $item_data['quantity'];

					$product_data = array();
					if ($item_data['variation_id']) {
						$product_data = $product->get_data();
						if (empty($product_data['weight']) && empty($product_data['length']) && empty($product_data['width']) && empty($product_data['height'])) {
							$product_data                 = $product->get_parent_data();
							$product_data['product_id']   = $item_data['product_id'];
							$product_data['variation_id'] = $item_data['variation_id'];
						}

						$temp_product_data            = $product->get_data();
						$product_data['product_id']   = $item_data['product_id'];
						$product_data['variation_id'] = $item_data['variation_id'];
					} elseif ($item_data['product_id']) {
						$product_data = $product->get_data();
					}

					$parcel = array();
					if ($this->weight_unit != 'kg' && $this->is_request_bulk_shipment) {
						$from_weight_unit       = $this->weight_unit;
						$product_data['weight'] = wc_get_weight($product_data['weight'], 'kg', $this->weight_unit);
					} else {
						$product_data['weight'] = !empty($this->weights_in_request_array[$parcel_count]) ?  $this->weights_in_request_array[$parcel_count] : $product_data['weight'];
					}

					$parcel['weight'] = !empty($this->weights_in_request_array[$parcel_count]) ?  $this->weights_in_request_array[$parcel_count] : $product_data['weight'];
					$parcel['length'] = !empty($this->lengths_in_request_array[$parcel_count]) ?  $this->lengths_in_request_array[$parcel_count] : $product_data['length'];
					$parcel['width']  = !empty($this->widths_in_request_array[$parcel_count]) ?  $this->widths_in_request_array[$parcel_count] : $product_data['width'];
					$parcel['height'] = !empty($this->heights_in_request_array[$parcel_count]) ?  $this->heights_in_request_array[$parcel_count] : $product_data['height'];

					$parcel_volume = wc_get_dimension($parcel['length'], 'm') * wc_get_dimension($parcel['width'], 'm') * wc_get_dimension($parcel['height'], 'm');
					
					$all_eligible_postage_products = !empty($this->settings['services']) ? $this->settings['services'] : array();
					update_option('all_ausmypost_postage_products_auspost_elex', $all_eligible_postage_products);
					$serviceName = $this->wf_get_shipping_service($order, false);

					$service_method_id = $this->get_selected_shipping_service_id($all_eligible_postage_products, $serviceName, $order);
					
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
						$girth = ( round($dimensions[0]) + round($dimensions[1]) ) * 2;
					} else {
						$girth = ( round(wc_get_dimension($dimensions[0], 'cm', $from_dimension_unit)) + round(wc_get_dimension($dimensions[1], 'cm', $from_dimension_unit)) ) * 2;
					}
					if ($this->is_request_bulk_shipment) {
						$girth = ( round(wc_get_dimension($dimensions[0], 'cm', $from_dimension_unit)) + round(wc_get_dimension($dimensions[1], 'cm', $from_dimension_unit)) ) * 2;
					}


					$parcel_weight = wc_get_weight($parcel['weight'], 'kg', $this->weight_unit);
					if (!$this->startrack_enabled) {
						if ($parcel_weight > 22 || $dimensions[2] > 105) {
							$this->debug(sprintf(__('Product %d has invalid weight/dimensions. Aborting.', 'wf-shipping-auspost'), $item_id), 'error');
							return;
						}
					}

					if ($item_data['variation_id']) {
						// Allowed maximum volume of a product is 0.25 cubic meters for domestic shipments
						if ($domestic == 'yes' && $parcel_volume > 0.25) {
							update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', __('Error: Product  exceeds 0.25 cubic meters Aborting.', 'wf-shipping-auspost'));
							return;
						}

						// The girth should lie between 16cm and 140cm for international shipments
						if ($domestic == 'no' && ( $girth < 16 || $girth > 140 )) {
							update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', __('<b>Error</b>: Girth of the product  should lie in between 16cm and 140cm.', 'wf-shipping-auspost'));
							return;
						}
					} else {
						// Allowed maximum volume of a product is 0.25 cubic meters for domestic shipments
						if ($domestic == 'yes' && $parcel_volume > 0.25) {
							update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', __('Error: Product  exceeds 0.25 cubic meters Aborting.', 'wf-shipping-auspost'));
							return;
						}

						// The girth should lie between 16cm and 140cm for international shipments
						if ($domestic == 'no' && ( $girth < 16 || $girth > 140 )) {
							update_post_meta($order_id, 'wf_woo_australiapost_shipmentErrorMessage', __('<b>Error</b>: Girth of the product  should lie in between 16cm and 140cm.', 'wf-shipping-auspost'));
							return;
						}
					}

					$insurance_array = array(
						'Amount' => ceil($item_data['total']),
						'Currency' => get_woocommerce_currency()
					);

					$product_desc = '';
					if (!empty($product_data)) {
						if (isset($product_data['id']) && !empty($product_data['id'])) {
							$product_desc = get_post_meta($product_data['id'], '_wf_shipping_description', 1);
						} elseif (isset($product_data['product_id']) && !empty($product_data['product_id'])) {
							$product_desc = get_post_meta($product_data['product_id'], '_wf_shipping_description', 1);
						}

						if ($item_data['variation_id']) {

							$package_weight = get_post_meta($product_data['variation_id'], '_weight', 1);
							$package_weight = ( !empty($package_weight) ) ? $package_weight : get_post_meta($product_data['product_id'], '_weight', 1);

							$package_item_description = !empty($product_desc) ? $product_desc : ( isset($product_data['name']) && !empty($product_data['name']) ? $product_data['name'] : $product_data['title'] );
							$package_item_value       = get_post_meta($product_data['variation_id'], '_sale_price', 1);
							$package_item_value       = empty($package_item_value) ? get_post_meta($product_data['variation_id'], '_regular_price', 1) : $package_item_value;
							$package_items            = array(
								'description'               => ( strlen($package_item_description) > 40 ) ? substr( $this->string_clean( $package_item_description ), 0, 37) . '...' : $package_item_description,
								'quantity'                  => 1,
								'value'                     => $package_item_value,
								'tariff_code'               => get_post_meta($product_data['product_id'], '_wf_tariff_code', 1),
								'country_of_origin'         => get_post_meta($product_data['product_id'], '_wf_country_of_origin', 1),
								'weight'                    => ( $this->weight_unit != 'kg' ) ? round(wc_get_weight($package_weight, 'kg', $this->weight_unit), 2) : round($package_weight, 2),
								'export_declaration_number' => get_post_meta($product_data['product_id'], '_wf_export_declaration_number', 1)
							);
						} else {
							$package_item_description = !empty($product_desc) ? $product_desc : $product_data['name'];
							$package_item_value       = get_post_meta($product_data['id'], '_sale_price', 1);
							$package_item_value       = empty($package_item_value) ? get_post_meta($product_data['id'], '_regular_price', 1) : $package_item_value;

							$package_items = array(
								'description'               => ( strlen($package_item_description) > 40 ) ? substr( $this->string_clean( $package_item_description ), 0, 37) . '...' : $package_item_description,
								'quantity'                  => 1,
								'value'                     => $package_item_value,
								'tariff_code'               => get_post_meta($product_data['id'], '_wf_tariff_code', 1),
								'country_of_origin'         => get_post_meta($product_data['id'], '_wf_country_of_origin', 1),
								'weight'                    => ( $this->weight_unit != 'kg' ) ? round(wc_get_weight(get_post_meta($product_data['id'], '_weight', 1), 'kg', $this->weight_unit), 2) : round(get_post_meta($product_data['id'], '_weight', 1), 2),
								'export_declaration_number' => get_post_meta($product_data['id'], '_wf_export_declaration_number', 1)
							);
						}
					}

					$item_reference              = '';
					$product_composite_reference = get_post_meta($product_id, '_composite_title', true);
					delete_post_meta($product->get_id(), '_composite_title');

					if (isset($product_data['title']) && !empty($product_data['title'])) {
						$item_reference = $product_data['title'];
					} elseif (isset($product_data['name']) && !empty($product_data['name'])) {
						$item_reference = $product_data['name'];
					} elseif (isset($product_desc) && !empty($product_desc)) {
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
						'age_check'         => ( $product_age_check_selected ) ? $product_age_check_selected : false
					);

				   
					if ($is_request_create_shipment) {
						if (!empty($this->shipment_services_in_request_array)) {
							if (isset($this->shipment_services_in_request_array[$parcel_count])) {
								if (strpos($this->shipment_services_in_request_array[$parcel_count], 'startrack')) {
									$dangerous_goods_data[]                                  = $this->validate_dangerous_goods($product, 'StarTrack');
									$this->shipment_services_in_request_array[$parcel_count] = str_replace('startrack', '', $this->shipment_services_in_request_array[$parcel_count]);
									$group['dangerous_goods_data']                           = empty($dangerous_goods_data) ? false : $dangerous_goods_data;
									$group['shipping_service']                               = $this->shipment_services_in_request_array[$parcel_count];
									$group['startrack_service_selected']                     = 'yes';
								} else {
									$dangerous_goods_data[]        = $this->validate_dangerous_goods($product, 'Express Post');
									$group['dangerous_goods_data'] = empty($dangerous_goods_data) ? false : $dangerous_goods_data;
									$group['shipping_service']     = $this->shipment_services_in_request_array[$parcel_count];
									
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
								$group['shipping_service']           = str_replace('startrack', '', $this->shipment_services_in_request_array[$parcel_count]);
								$group['startrack_service_selected'] = 'yes';
							} else {
								$group['shipping_service']           = $this->shipment_services_in_request_array[$parcel_count];
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
	 * function to obtain shipping service type (MyPost Business)
	 *
	 * @access private
	 * @param array ausmypost contract services
	 * @param string shipping method id
	 * @return string shipping method type
	 */
	private function get_shipping_service_type( $order, $eligible_postage_products, $label_shipping_method_id) {
		$this->selected_service_type = '';
		$shipment_services           = $this->wf_get_shipping_service($order, false);
		$shipment_services_array     = $this->return_package_data_from_request($shipment_services);

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
	 *
	 * @access private
	 * @param woocommerce product
	 * @return mixed product meta
	 */
	private function validate_dangerous_goods( $product, $selected_service_type) {
		$is_dangerous_good = false;
		if (!empty($product)) {
			$product_meta_data_for_packaging = array();
			$product_id                      = $product->get_id();
			$product_weight                  = $product->get_weight();

			$dangerous_goods_description_express = array(
				'UN2910' => 'UN2910_radioactive_excepted_limited_qty',
				'UN2911' => 'UN2911_radioactive_excepted_instruments_or_articles',
				'UN3373' => 'UN3373_BioSubstance_B',
				'UN3481' => 'UN3481_Lithium_IonOrPolymer_contained_in_equipment',
				'UN3091' => 'UN3091_Lithium_MetalAndAlloy_contained_in_equipment'
			);
			$product_dangerous_good_check_meta   = 'no';
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
							$selected_un_number             = get_post_meta($product_id, '_dangerous_goods_desciption_startrack_auspost_elex', true);
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
							$product_un_number_type                     = get_post_meta($product_id, '_dangerous_goods_desciption_auspost_elex', true);
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

	private function box_packing( $order) {

		$box_packing_method = !empty($this->settings['box_packing_method']) ? $this->settings['box_packing_method'] : 'volume';


		$boxes    = array();
		$order_id = $order->get_id();

		$is_request_create_shipment = get_option('request_to_create_shipment');


		$orderItems = $order->get_items();
		$orderItems = apply_filters( 'elex_order_items', $orderItems );
		if (!empty($orderItems) && is_array($orderItems)) {

			if ($this->is_woocommerce_composite_products_installed) {
				$orderItems = $this->get_composite_product_items($orderItems);
			}

				
				
			$stored_pre_defined_boxes = get_option('ausmypost_stored_pre_defined_boxes');
			$stored_custom_boxes      = get_option('ausmypost_stored_custom_boxes');

			$stored_auspost_boxes = array_merge($stored_pre_defined_boxes, $stored_custom_boxes);
			$shipping_country     = wf_get_order_shipping_country($order);

			$all_eligible_postage_products =!empty($this->settings['services']) ? $this->settings['services'] : array();
			update_option('all_ausmypost_postage_products_auspost_elex', $all_eligible_postage_products);

			$serviceName       = $this->wf_get_shipping_service($order, false);
			$service_method_id = $this->get_selected_shipping_service_id($all_eligible_postage_products, $serviceName, $order);

			$boxes = $stored_auspost_boxes;


			$from_weight_unit = '';
			if ($this->weight_unit != 'kg') {
				$from_weight_unit = $this->weight_unit;
			}

			$from_dimension_unit = '';
			if ($this->dimension_unit != 'cm') {
				$from_dimension_unit = $this->dimension_unit;
			}

			if (count($boxes) == 0) {
				$boxpacking_error_desc = 'No boxes are available to Create shipping';
				$this->set_boxpacking_error_notices($boxpacking_error_desc);
			}

			$failed_shipment_order_packages = get_post_meta($order->get_id(), 'consolidated_failed_create_shipment_packages_ausmypost_elex', true);
			if ($is_request_create_shipment) {
				delete_post_meta($order_id, 'failed_create_shipment_packages_ausmypost_elex');
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
				$item_data  = $orderItem->get_data();
				$product_id = isset($item_data['variation_id']) && ( $item_data['variation_id'] != 0 ) ? $item_data['variation_id'] : $item_data['product_id'];
				$product    = wc_get_product($product_id);

				if ($product->is_virtual()) {
					// If it's is a virtual product skip it.
					continue;
				}

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
					update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', 'Products does not contain weights and/or dimensions');
				}
			}
			// Pack it
			$boxpack->pack();
			$packages             = $boxpack->get_packages();
			$packed               = new stdClass();
			$to_ship              = array();
			$package_items        = array();
			$parcel_count         = 0;
			$package_item         = new WC_Product();
			$package_item_data    = array();
			$package_name         = '';
			$dangerous_goods_data = array();
			if (!empty($packages) && is_array($packages)) {
				foreach ($packages as $package) {
					$shipment_service_type = '';
					if (empty($failed_shipment_order_packages) || ( !empty($failed_shipment_order_packages) && in_array($parcel_count, $failed_shipment_order_packages) )) {
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
							$packed       = $package->packed;
							$package_name = isset($package->name) && !empty($package->name) ? $package->name : '';
						}

						$packed_products_ids_array  = array();
						$product_age_check_selected = '';
						if (is_array($packed)) {
							foreach ($packed as $packed_product) {
								$packed_product_meta      = $packed_product->meta;
								$packed_product_meta_data = $packed_product_meta['data'];
								$packed_meta              = $packed_product_meta_data->get_data();
								if (!empty($packed_meta['variation_id'])) {
									$packed_products_ids_array[] = $packed_meta['variation_id'];
								} else {
									$packed_products_ids_array[] = $packed_meta['product_id'];
								}
							}
							$packed_products_ids_array_unique        = array_unique($packed_products_ids_array);
							$packed_products_ids_array_unique_values = array_count_values($packed_products_ids_array);
							$box_packed_products                     = array();

							foreach ($packed_products_ids_array_unique_values as $packed_products_ids_array_unique_values_key => $packed_products_ids_array_unique_values_elements) {
								foreach ($packed as $packed_product) {
									$packed_product_meta      = $packed_product->meta;
									$packed_product_meta_data = $packed_product_meta['data'];
									$packed_meta              = $packed_product_meta_data->get_data();
									if (( $packed_meta['variation_id'] != 0 ) && ( $packed_meta['variation_id'] === $packed_products_ids_array_unique_values_key )) {
										$packed_meta['quantity'] = $packed_products_ids_array_unique_values[$packed_products_ids_array_unique_values_key];
										$box_packed_products[]   = $packed_product;
										break;
									} elseif (( $packed_meta['product_id'] != 0 ) && ( $packed_meta['product_id'] === $packed_products_ids_array_unique_values_key )) {
										$packed_meta['quantity'] = $packed_products_ids_array_unique_values[$packed_products_ids_array_unique_values_key];
										$box_packed_products[]   = $packed_product;
										break;
									}
								}
							}

							foreach ($box_packed_products as $box_packed_product) {
								$box_packed_product_meta      = $box_packed_product->meta;
								$box_packed_product_meta_data = $box_packed_product_meta['data'];
								$packed_meta                  = $box_packed_product_meta_data->get_data();
								$product_id                   = $packed_meta['product_id'];

								if (empty($product_age_check_selected)) {
									$product_age_check_selected = get_post_meta($product_id, 'age_check_auspost_elex', true);
								}

								$product_desc = get_post_meta($packed_meta['product_id'], '_wf_shipping_description', 1);

								$package_weight = get_post_meta($packed_meta['variation_id'], '_weight', 1);

								$package_weight           = ( !empty($package_weight) ) ? $package_weight : get_post_meta($packed_meta['product_id'], '_weight', 1);
								$packed_product_temp      = ( $packed_meta['variation_id'] ) ? wc_get_product($packed_meta['variation_id']) : wc_get_product($packed_meta['product_id']);
								$dangerous_goods_data[]   = $this->validate_dangerous_goods($packed_product_temp, $shipment_service_type);
								$package_item_description = !empty($product_desc) ? $product_desc : $packed_meta['name'];
								$package_item_value       = $packed_product_temp->get_price();

								$package_items[] = array(
									'description'               => ( strlen($package_item_description) > 40 ) ? substr( $this->string_clean( $package_item_description ), 0, 37) . '...' : $package_item_description,
									'quantity'                  => $box_packed_product->packed_quantity,
									'value'                     => $package_item_value,
									'tariff_code'               => get_post_meta($packed_meta['product_id'], '_wf_tariff_code', 1),
									'country_of_origin'         => get_post_meta($packed_meta['product_id'], '_wf_country_of_origin', 1),
									'weight'                    => ( $this->weight_unit != 'kg' ) ? round(wc_get_weight($package_weight, 'kg', $this->weight_unit), 2) : round($package_weight, 2),
									'export_declaration_number' => get_post_meta($packed_meta['product_id'], '_wf_export_declaration_number', 1)
								);
							}
						} else {
							$product_desc = get_post_meta($package->product_id, '_wf_shipping_description', 1);
							if (empty($product_age_check_selected)) {
								$product_age_check_selected = get_post_meta($product_id, 'age_check_auspost_elex', true);
							}
							$packed_product_temp    = wc_get_product($package->product_id);
							$dangerous_goods_data[] = $this->validate_dangerous_goods($packed_product_temp, $shipment_service_type);
							if ($package->variation_id) {
								$package_weight     = get_post_meta($package->variation_id, '_weight', 1);
								$package_weight     = ( !empty($package_weight) ) ? $package_weight : get_post_meta($package->product_id, '_weight', 1);
								$package_item_value = get_post_meta($package->variation_id, '_sale_price', 1);
								$package_item_value = empty($package_item_value) ? get_post_meta($package->variation_id, '_regular_price', 1) : $package_item_value;
								$package_items[]    = array(
									'description'               => ( strlen($product_desc) > 40 ) ? substr( $this->string_clean( $product_desc ), 0, 37) . '...' : $product_desc,
									'quantity'                  => 1,
									'value'                     => $package_item_value,
									'tariff_code'               => get_post_meta($package->product_id, '_wf_tariff_code', 1),
									'country_of_origin'         => get_post_meta($package->product_id, '_wf_country_of_origin', 1),
									'weight'                    => ( $this->weight_unit != 'kg' ) ? round(wc_get_weight($package_weight, 'kg', $this->weight_unit), 2) : round($package_weight, 2),
									'export_declaration_number' => get_post_meta($package->product_id, '_wf_export_declaration_number', 1)
								);
							} else {
								$package_item_value = get_post_meta($package->product_id, '_sale_price', 1);
								$package_item_value = empty($package_item_value) ? get_post_meta($package->product_id, '_regular_price', 1) : $package_item_value;
								$package_items[]    = array(
									'description'               => ( strlen($product_desc) > 40 ) ? substr( $this->string_clean( $product_desc ), 0, 37) . '...' : $product_desc,
									'quantity'                  => 1,
									'value'                     => $package_item_value,
									'tariff_code'               => get_post_meta($package->product_id, '_wf_tariff_code', 1),
									'country_of_origin'         => get_post_meta($package->product_id, '_wf_country_of_origin', 1),
									'weight'                    => ( $this->weight_unit != 'kg' ) ? round(wc_get_weight(get_post_meta($package->product_id, '_weight', 1), 'kg', $this->weight_unit), 2) : round(get_post_meta($package->product_id, '_weight', 1), 2),
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

						$package_name = ( isset($package_name) && !empty($package_name) ) ? $package_name : ( ( isset($package->title) && !empty($package->title) ) ? $package->title : ( ( isset($package->name) && !empty($package->name) ) ? $package->name : '' ) );

						if (!empty($package_name)) {
							$package_name = strtok($package_name, '(');
						}

						if (!isset($package->packed) && !empty($package_name)) {
							$package_name = $package_name; //'<small> (Packed Separately)</small>'
						}
						if ($this->weight_unit != 'kg' && $this->is_request_bulk_shipment) {
							$from_weight_unit = $this->weight_unit;
							$package->weight  = wc_get_weight($package->weight, 'kg', $this->weight_unit);
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
								'Units' => ( !empty($this->lengths_in_request_array[$parcel_count]) || !empty($this->widths_in_request_array[$parcel_count]) || !empty($this->heights_in_request_array[$parcel_count]) ) ? 'cm' : $this->dimension_unit
							),
							'InsuredValue' => $insurance_array,
							'packed_products' => array(),
							'Item_contents' => $package_items,
							'pack_type' => ( $package->packtype != 'NONE' ) ? $package->packtype : 'ITM',
							'dangerous_goods_data' => empty($dangerous_goods_data) ? false : $dangerous_goods_data,
							'age_check' => ( $product_age_check_selected ) ? $product_age_check_selected : false,
						);
						if ($is_request_create_shipment) {
							if (!empty($this->shipment_services_in_request_array)) {
								if (strpos($this->shipment_services_in_request_array[$parcel_count], 'startrack')) {
									$this->shipment_services_in_request_array[$parcel_count] = str_replace('startrack', '', $this->shipment_services_in_request_array[$parcel_count]);
									$group['shipping_service']                               = $this->shipment_services_in_request_array[$parcel_count];
									$group['startrack_service_selected']                     = 'yes';
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

	/*
	 * function to set error notices on database
	 */

	private function set_error_notices( $error_desc) {
		$option_name = 'wf_create_shipment_error';
		if (get_option($option_name) !== false) {
			update_option($option_name, $error_desc);
		} else {
			$deprecated = null;
			$autoload   = 'no';
			add_option($option_name, $error_desc, $deprecated, $autoload);
		}
	}

	/**
	 * function to set box packing error notices on database
	 */

	private function set_boxpacking_error_notices( $error_desc) {
		$option_name = 'wf_create_boxpacking_error';
		if (get_option($option_name) !== false) {
			update_option($option_name, $error_desc);
		} else {
			$deprecated = null;
			$autoload   = 'no';
			add_option($option_name, $error_desc, $deprecated, $autoload);
		}
	}

	/**
	 * function to obtain shipping method id for label creation
	 *
	 * @param array auspost service types and ids
	 * @param string shipping service name selected for label creation
	 * @param object woocommerce order
	 * @return atring shipping method id
	 */
	private function get_selected_shipping_service_id( $postage_products_type_and_product_ids, $serviceName, $order) {
		$label_shipping_method_id = '';
		$postage_service_code     = '';
		$shipping_country         = wf_get_order_shipping_country($order);
		
		foreach ($postage_products_type_and_product_ids as $postage_products_type_and_product_id_key => $postage_products_type_and_product_id_value) {
			if ($serviceName == $postage_products_type_and_product_id_key) {
				$postage_service_code = $postage_products_type_and_product_id_key;
			} elseif ($serviceName == $postage_products_type_and_product_id_value['name']) {
				$postage_service_code = $postage_products_type_and_product_id_key;
			}
		}
		if (!empty($postage_service_code)) {
			$label_shipping_method_id = $postage_service_code;
			update_post_meta($this->wf_get_order_id($order), 'wf_aus_label_shipment_id', $label_shipping_method_id);
		} else {
			/* If customer has not selected any service while placing order */

			if ($shipping_country == 'AU') {
				$default_auspost_domestic_shipment_service = ( isset($this->settings['ausmypost_default_domestic_shipment_service']) && ( $this->settings['ausmypost_default_domestic_shipment_service'] != 'none' ) ) ? $this->settings['ausmypost_default_domestic_shipment_service'] : 'none';

				if ($default_auspost_domestic_shipment_service != 'none') {
					update_option('default_ausmypost_shipment_service_selected', 'yes');
					$label_shipping_method_id = $default_auspost_domestic_shipment_service;
					update_post_meta($this->wf_get_order_id($order), 'wf_aus_label_shipment_id', $label_shipping_method_id);
				} else {
					/* If the customer has not set any service as default */
					update_option('default_ausmypost_shipment_service_selected', 'no');
					$orders_with_no_default_shipment_service_ausmypost = $this->wf_get_order_id($order);
				}
			} else {
				$default_auspost_international_shipment_service = ( isset($this->settings['ausmypost_default_international_shipment_service']) && ( $this->settings['ausmypost_default_international_shipment_service'] != 'none' ) ) ? $this->settings['ausmypost_default_international_shipment_service'] : 'none';
				if ($default_auspost_international_shipment_service != 'none') {
					update_option('default_ausmypost_shipment_service_selected', 'yes');
					$label_shipping_method_id = $default_auspost_international_shipment_service;
					update_post_meta($this->wf_get_order_id($order), 'wf_aus_label_shipment_id', $label_shipping_method_id);
				} else {
					/* If the customer has not set any service as default */
					update_option('default_ausmypost_shipment_service_selected', 'no');
					$orders_with_no_default_shipment_service_ausmypost = $this->wf_get_order_id($order);
				}
			}
		
			update_post_meta($this->wf_get_order_id($order), 'wf_aus_label_shipment_id', $label_shipping_method_id);
		}

		$stored_order_ids_with_no_default_shipping_services = get_option('orders_with_no_default_shipment_service_ausmypost');
		if (!empty($orders_with_no_default_shipment_service_ausmypost)) {
			$stored_order_ids_with_no_default_shipping_services .= $orders_with_no_default_shipment_service_ausmypost;
		}
		update_option('orders_with_no_default_shipment_service_ausmypost', $stored_order_ids_with_no_default_shipping_services);

		return $label_shipping_method_id;
	}

	public function print_shipping_label( $order, $shipment_id) {

		$order_id          = $this->wf_get_order_id($order);
		$label_request_id  = get_post_meta($order_id, 'wf_woo_australiamypost_labelId' . $shipment_id, true);
		$service_label_url = 'https://' . self::API_HOST . self::API_BASE_URL . 'labels/';
		if ($this->contracted_api_mode == 'live') {
			$service_base_url = str_replace('test/', '', $service_base_url);
		}
		$label_get_url = $service_label_url . $label_request_id;
		
		$shipment_id = isset($_GET['shipment_id']) ? $_GET['shipment_id'] : $shipment_id;

		$rqs_headers = $this->buildHttpHeaders();
		
	
		$res = wp_remote_request($label_get_url, array(
			'headers' => $rqs_headers
		));
		if (is_wp_error($res)) {
			$error_string = $res->get_error_message();
			$this->set_error_notices($error_string);
			if ($this->debug) {
				update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $error_string);
			}
			return;
		}

		$response_array = isset($res['body']) ? json_decode($res['body']) : array();

		if (!empty($response_array->errors)) {
			$this->set_error_notices($response_array->errors[0]->message);
			if ($this->debug) {
				update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $response_array->errors[0]->message);
			}
			return;
		}

		if (isset($response_array->labels)) {
			$label_uri = $response_array->labels[0]->url;
			update_post_meta($order_id, 'wf_woo_australiamypost_labelURI' . $shipment_id, $label_uri);
		}
		return;
	}

	public function wf_create_shipment( $order) {
		/*Shipment label printing is only for contracted accounts*/
		if (!$this->mypost_contracted_rates) {
			return false;
		}


		$order_id           = $this->wf_get_order_id($order);
		$extra_cover_status = get_post_meta($order_id, 'extra_cover_opted_ausmypost_elex', true);
		$signature_required = get_post_meta($order_id, 'signature_required_opted_ausmypost_elex', true);
		$shipping_country   = wf_get_order_shipping_country($order);

		update_option('request_to_create_shipment', true);

		$all_auspost_postage_products = get_option('all_ausmypost_postage_products_auspost_elex');

		if (( isset($_GET['weight']) && isset($_GET['height']) && isset($_GET['width']) && isset($_GET['length']) )) {
			$this->titles_in_request_array            = $this->return_package_data_from_request($_GET['title']);
			$this->weights_in_request_array           = $this->return_package_data_from_request($_GET['weight']);
			$this->lengths_in_request_array           = $this->return_package_data_from_request($_GET['length']);
			$this->widths_in_request_array            = $this->return_package_data_from_request($_GET['width']);
			$this->heights_in_request_array           = $this->return_package_data_from_request($_GET['height']);
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
					'InsuredValue' =>array(
						'Amount' => 100
					 ),
					'shipping_service' => $this->shipment_services_in_request_array[$i]
				);
			}
		}
		$convention_shipment_service_requests_array = array();
		$failed_shipment_packages_stored            = get_post_meta($order_id, 'failed_create_shipment_packages_ausmypost_elex', true);
		$failed_shipment_packages_stored            = array();
		$service_count                              = 0;
		if (!empty($failed_shipment_packages_stored)) {
			foreach ($failed_shipment_packages_stored as $failed_package_order) {
				$convention_shipment_service_requests_array[$failed_package_order] = $this->shipment_services_in_request_array[$service_count];
				$service_count++;
			}

			$this->shipment_services_in_request_array = $convention_shipment_service_requests_array;
		}

		$service_base_url = 'https://' . self::API_HOST . self::API_BASE_URL;

		if ($this->contracted_api_mode == 'live') {
			$service_base_url = str_replace('sandbox/', 'production/', $service_base_url);
		}

		$label_uri = isset($_GET['shipment_id']) ? get_post_meta($order_id, 'wf_woo_australiamypost_labelURI' . $_GET['shipment_id'], true) : false;
		if (!$label_uri) {
			$label_request_id = isset($_GET['shipment_id']) ? get_post_meta($order_id, 'wf_woo_australiamypost_labelId' . $_GET['shipment_id'], true) : false;
			if ($label_request_id) {
				$this->print_shipping_label($order, $_GET['shipment_id']);
				delete_option('create_shipment_for_startrack');
				wp_redirect(admin_url('/post.php?post=' . $order_id . '&action=edit'));
				exit;
			}
		}

		if (!isset($_GET['shipment_id'])) {
			/* Obtaining service selected for the shipment */
			$serviceName = $this->wf_get_shipping_service($order, true);

			$label_shipping_method_id    = $this->get_selected_shipping_service_id($all_auspost_postage_products, $serviceName, $order);
			$available_shipping_services = $this->settings['services'];
			if (array_key_exists($label_shipping_method_id, $available_shipping_services)) {
				if ($available_shipping_services[$label_shipping_method_id]['extra_cover']) {
					$extra_cover_status = true;
				}
			}

			$package_requests = array();
			if ($this->packing_method == 'weight') {
				$package_requests = $this->weight_based_packing($order);
			} elseif ($this->packing_method == 'box_packing') {
				$package_requests = $this->box_packing($order);
			} else {
				$package_requests = $this->per_item_packing($order);
			}

			$package_index                                  = 0;
			$package_commercial_value                       = false;
			$desc_for_other                                 = '';
			$auspost_default_shipment_service_domestic      = isset($this->settings['ausmypost_default_domestic_shipment_service']) ? $this->settings['ausmypost_default_domestic_shipment_service'] : 'none';
			$auspost_default_shipment_service_international = isset($this->settings['ausmypost_default_international_shipment_service']) ? $this->settings['ausmypost_default_international_shipment_service'] : 'none';
			
			if (is_array($package_requests)) {
				$postage_products_service_options = $available_shipping_services;
				if ($shipping_country == 'AU') {
					if (!empty($additional_packages)) {
						$package_requests = array_merge($package_requests, $additional_packages);
					}
					$shipment_list = array();
					foreach ($package_requests as $key => $package_request) {
						$sod                                 = false;
						$auspost_services                    = $this->settings['services'];
						$line_items                          = array();
						$create_shipment_service             = '';
						$package_request['shipping_service'] = $this->shipment_services_in_request_array[$package_index];				
						if (isset($package_request['shipping_service']) && !empty($package_request['shipping_service'])) {
							$create_shipment_service = $this->get_selected_shipping_service_id($auspost_services, $package_request['shipping_service'], $order);
							if (array_key_exists($create_shipment_service, $available_shipping_services)) {
								if ($available_shipping_services[$create_shipment_service]['extra_cover']) {
									$extra_cover_status = true;
								}

							}

						}
						if ($package_request['Weight']['Units'] != 'kg') {
							$package_request['Weight']['Value'] = wc_get_weight($package_request['Weight']['Value'], 'kg', $this->weight_unit);
						}

						if (isset($package_request['Dimensions']['Units']) && $package_request['Dimensions']['Units'] != 'cm') {

							$package_request['Dimensions']['Length'] = wc_get_dimension($package_request['Dimensions']['Length'], 'cm', $package_request['Dimensions']['Units']);
							$package_request['Dimensions']['Width']  = wc_get_dimension($package_request['Dimensions']['Width'], 'cm', $package_request['Dimensions']['Units']);
							$package_request['Dimensions']['Height'] = wc_get_dimension($package_request['Dimensions']['Height'], 'cm', $package_request['Dimensions']['Units']);
						}
						if ( is_array($postage_products_service_options)  && !empty($postage_products_service_options)  && isset($postage_products_service_options[$create_shipment_service]) && isset($postage_products_service_options[$create_shipment_service]['signature_on_delivery_option']) && ''!=$postage_products_service_options[$create_shipment_service]['signature_on_delivery_option'] ) {
							$sod = true;
						} elseif (500< round($package_request['InsuredValue']['Amount'], 2)) {
							$sod = true;
						} elseif ($signature_required) {
							$sod = true;
						}
						$sending_date       = current_time('d-M-Y', 0);
						$sender_reference_1 = $this->rate_type ? 'Order #' . $order_id : 'Order #' . $order_id . '-' . $sending_date;
						if (strlen($sender_reference_1) >= 50) {
							$sender_reference_1 = substr($sender_reference_1, 0, 49);
						}
						$sender_reference_2        = $this->ship_content;
						$order->shipping_address_1 = strlen($order->shipping_address_1) > 40 ? substr( $this->string_clean( $order->shipping_address_1 ), 0, 37) . '...' : $order->shipping_address_1;
						$order->shipping_address_2 = strlen($order->shipping_address_2) > 40 ? substr( $this->string_clean( $order->shipping_address_2 ), 0, 37) . '...' : $order->shipping_address_2;
						$order_customer_note       = $order->get_customer_note();

						$line_items = array(
							'ship_from' => array (
								'name' => $this->shipper_name,
								'business_name' => $this->shipper_name,
								'address_type' => 'STANDARD_ADDRESS',
								'phone' => array (
									'raw_phone' => $this->shipper_phone_number,
								),
								'email' => $this->shipper_email,
								'address_line_1' => $this->shipper_address,
								'address_line_2' => '',
								'city_locality' => $this->shipper_suburb,
								'state_province' => $this->shipper_state,
								'postal_code' => $this->shipper_postcode,
								'country_code' => 'AU',
							),
							'ship_to' => array (
							  'name' => $order->shipping_first_name . ' ' . $order->shipping_last_name,
							  'business_name' => $order->shipping_company,
							  'address_type' => 'STANDARD_ADDRESS',
							  'phone' => array (
								'raw_phone' => $order->billing_phone,
							  ),
							  'email' => $order->billing_email,
							  'address_line_1' => $order->shipping_address_1,
							  'address_line_2' => $order->shipping_address_2,
							  'city_locality' => $order->shipping_city,
							  'state_province' => $order->shipping_state,
							  'postal_code' => $order->shipping_postcode,
							  'country_code' => $order->shipping_country,
							  'delivery_instructions' =>$order_customer_note,
							),
							'shipment_options' => array (
							  'auspost_mypost' => array (
								'shipment_reference' => uniqid(),
								'email_tracking_enabled' => $this->email_tracking,
								'sender_references' => array (
								  0 => $sender_reference_1,
								  1 => $sender_reference_2,
								),
							  ),
							),
							'items' => array (
							  0 => array (
								'description' => $package_request['Name'],
								'item_options' => array (
								  'auspost_mypost' => array (
									'contains_dangerous_goods' => false,
									'cover_amount' => $extra_cover_status ? round($package_request['InsuredValue']['Amount'], 2) : array(),
									'product_id' => $create_shipment_service,
									'signature_on_delivery' => $sod,
								  ),
								),
								'weight' => array (
								  'value' => $package_request['Weight']['Value'] < 0.01 ? 0.01 : round($package_request['Weight']['Value'], 3),
								  'unit' => 'KG',
								),
								'length' => array (
								  'value' => round($package_request['Dimensions']['Length'], 1),
								  'unit' => 'CM',
								),
								'width' => array (
								  'value' => round($package_request['Dimensions']['Width'], 1),
								  'unit' => 'CM',
								),
								'height' => array (
								  'value' => round($package_request['Dimensions']['Height'], 1),
								  'unit' => 'CM',
								),
							  ),
							),
						);
						if (in_array($order->shipping_country, $this->european_union_countries) && isset($this->settings['import_reference_number']) && $this->settings['import_reference_number'] && !empty($this->settings['import_reference_number'])) {
							$line_items['items'][0]['item_options']['auspost_mypost']['import_reference_number'] = substr($this->settings['import_reference_number'], 0, 39);
						}
						if (empty($line_items['items'][0]['item_options']['auspost_mypost']['cover_amount'])) {
							unset($line_items['items'][0]['item_options']['auspost_mypost']['cover_amount']);
						}

						$shipment_list[] = $line_items;

						$package_index++;
					}
					$shipment_id = $this->create_shipment_for_package($order, $shipment_list, $service_base_url, $package_index);

				} else {
					$shipment_list = array();
					foreach ($package_requests as $key => $package_request) {
						if (!empty($this->order_package_categories_arr[$package_index])) {
							if ($this->order_package_categories_arr[$package_index] == 'OTHER') {
								$package_commercial_value = true;
								if (!empty($this->order_desc_for_other_category_arr[$package_index])) {
									$desc_for_other = $this->order_desc_for_other_category_arr[$package_index];
								} else {
									$desc_for_other = 'Sale';
								}
							}
						}

						$create_shipment_service             = '';
						$sod                                 = false;
						$package_request['shipping_service'] = $this->shipment_services_in_request_array[$package_index];
						if (isset($package_request['shipping_service'])) {
							$create_shipment_service = $package_request['shipping_service'];
						} elseif ($this->is_request_bulk_shipment) {
							$create_shipment_service = $auspost_default_shipment_service_international;
						}
						if (isset($package_request['Dimensions']['Units']) && $package_request['Dimensions']['Units'] != 'cm') {

							$package_request['Dimensions']['Length'] = wc_get_dimension($package_request['Dimensions']['Length'], 'cm', $package_request['Dimensions']['Units']);
							$package_request['Dimensions']['Width']  = wc_get_dimension($package_request['Dimensions']['Width'], 'cm', $package_request['Dimensions']['Units']);
							$package_request['Dimensions']['Height'] = wc_get_dimension($package_request['Dimensions']['Height'], 'cm', $package_request['Dimensions']['Units']);
						}

						if ( is_array($postage_products_service_options)  && !empty($postage_products_service_options)  && isset($postage_products_service_options[$create_shipment_service]) && isset($postage_products_service_options[$create_shipment_service]['signature_on_delivery_option']) && ''!=$postage_products_service_options[$create_shipment_service]['signature_on_delivery_option'] ) {
							$sod = true;
						} elseif (500< round($package_request['InsuredValue']['Amount'], 2)) {
							$sod = true;
						} elseif ($signature_required) {
							$sod = true;
						}
						$sending_date       = current_time('d-M-Y', 0);
						$sender_reference_1 = $this->rate_type ? 'Order #' . $order_id : 'Order #' . $order_id . '-' . $sending_date;
						if (strlen($sender_reference_1) >= 50) {
							$sender_reference_1 = substr($sender_reference_1, 0, 49);
						}
						$sender_reference_2        = $this->ship_content;
						$order->shipping_address_1 = strlen($order->shipping_address_1) > 40 ? substr( $this->string_clean( $order->shipping_address_1 ), 0, 37) . '...' : $order->shipping_address_1;
						$order->shipping_address_2 = strlen($order->shipping_address_2) > 40 ? substr( $this->string_clean( $order->shipping_address_2 ), 0, 37) . '...' : $order->shipping_address_2;
						$order_customer_note       = $order->get_customer_note();

						if ($this->packing_method == 'weight' || $this->packing_method == 'box_packing') {

							if (isset( $package_request['Item_contents'])) {
								foreach ($package_request['Item_contents'] as $key =>$val) {
									$package_request['Item_contents'][$key]['cover_amount'] = round($package_request['Item_contents'][$key]['value'], 2);
									unset($package_request['Item_contents'][$key]['value']);
								}
							}
						} else {
							if (isset( $package_request['Item_contents'])) {
								$package_request['Item_contents']['cover_amount'] = round($package_request['Item_contents']['value'], 2);
								unset($package_request['Item_contents']['value']);
								$package_request['Item_contents'] = array($package_request['Item_contents']);
							}
						}

						$line_items = array(
							'ship_from' => array (
								'name' => $this->shipper_name,
								'business_name' => $this->shipper_name,
								'address_type' => 'STANDARD_ADDRESS',
								'phone' => array (
									'raw_phone' => $this->shipper_phone_number,
								),
								'email' => $this->shipper_email,
								'address_line_1' => $this->shipper_address,
								'address_line_2' => '',
								'city_locality' => $this->shipper_suburb,
								'state_province' => $this->shipper_state,
								'postal_code' => $this->shipper_postcode,
								'country_code' => 'AU',
							),
							'ship_to' => array (
							  'name' => $order->shipping_first_name . ' ' . $order->shipping_last_name,
							  'business_name' => $order->shipping_company,
							  'address_type' => 'STANDARD_ADDRESS',
							  'phone' => array (
								'raw_phone' => $order->billing_phone,
							  ),
							  'email' => $order->billing_email,
							  'address_line_1' => $order->shipping_address_1,
							  'address_line_2' => $order->shipping_address_2,
							  'city_locality' => $order->shipping_city,
							  'state_province' => $order->shipping_state,
							  'postal_code' => $order->shipping_postcode,
							  'country_code' => $order->shipping_country,
							  'delivery_instructions' =>$order_customer_note,
							),
							'shipment_options' => array (
							  'auspost_mypost' => array (
								'shipment_reference' => uniqid(),
								'email_tracking_enabled' => $this->email_tracking,
								'sender_references' => array (
								  0 => $sender_reference_1,
								  1 => $sender_reference_2,
								),
							  ),
							),
							'items' => array (
							  0 => array (
								'description' => $package_request['Name'],
								'item_options' => array (
								  'auspost_mypost' => array (
									'contains_dangerous_goods' => false,
									'cover_amount' => $extra_cover_status ? round($package_request['InsuredValue']['Amount'], 2) : array(),
									'product_id' => $create_shipment_service,
									'signature_on_delivery' => $sod,
									'commercial_value' => $package_commercial_value,
									'classification_type'   => isset($this->order_package_categories_arr[$package_index]) ? $this->order_package_categories_arr[$package_index] : 'OTHER',
									'description_of_other'  => !empty($desc_for_other) ? $desc_for_other : 'Sale',
									'item_contents' =>(array) $package_request['Item_contents'],
								  ),
								),
								'weight' => array (
								  'value' => $package_request['Weight']['Value'] < 0.01 ? 0.01 : round($package_request['Weight']['Value'], 3),
								  'unit' => 'KG',
								),
								'length' => array (
								  'value' => round($package_request['Dimensions']['Length'], 1),
								  'unit' => 'CM',
								),
								'width' => array (
								  'value' => round($package_request['Dimensions']['Width'], 1),
								  'unit' => 'CM',
								),
								'height' => array (
								  'value' => round($package_request['Dimensions']['Height'], 1),
								  'unit' => 'CM',
								),
							  ),
							),
						);

						if (in_array($order->shipping_country, $this->european_union_countries) && isset($this->settings['import_reference_number']) && $this->settings['import_reference_number'] && !empty($this->settings['import_reference_number'])) {
							$line_items['items'][0]['item_options']['auspost_mypost']['import_reference_number'] = substr($this->settings['import_reference_number'], 0, 39);
						}
						if (empty($line_items['items'][0]['item_options']['auspost_mypost']['cover_amount'])) {
							unset($line_items['items'][0]['item_options']['auspost_mypost']['cover_amount']);
						}
						$shipment_list[] = $line_items;
						$package_index++;
					}
					$shipment_id = $this->create_shipment_for_package($order, $shipment_list, $service_base_url, $package_index);
				}
			}

			update_option('tracking_request_from_create_shipment', true);
			$admin_notice = '';
			// Shipment Tracking (Auto)
			if ($admin_notice != '') {
				WF_Tracking_Admin_AusPost::display_admin_notification_message($order_id, $admin_notice);
			}
		}

		return;
	}
	public function wf_create_bulk_shipment_order_and_pickup ( $eligible_order_list, $manifest_pickup) {
		global $wpdb;
		$bulk_shipment_create = $this->wf_bulk_create_shipment($eligible_order_list);
		$bulk_label           = array();
		$bulk_pickup          = array();
		$bulk_order           = array();

		if ($bulk_shipment_create['success']) {
			if (!empty($manifest_pickup)) {
				$this->pickup_date    = $manifest_pickup['date'];
				$this->pickup_time_id = $manifest_pickup['time'];
				$bulk_pickup          = $this->create_bulk_pickup_for_shipment($eligible_order_list, $bulk_shipment_create['total_shipment_ids']);
				if ($bulk_pickup['success']) {
					$bulk_order = $this->create_bulk_order_for_shipment($eligible_order_list, $bulk_shipment_create['total_shipment_ids'], $bulk_pickup['pickup_id']);
					if ($bulk_order['success']) {
						$bulk_label = $this->bulk_generate_label_package($eligible_order_list, $bulk_shipment_create['total_shipment_ids'], $bulk_order['shipment_order_id'], $bulk_shipment_create['orders_shipments']);
						if ($bulk_label['success']) {
							$wpdb->insert($wpdb->prefix . 'elex_bulk_shipments_orders_details', 
							array(
								'shipments_order_ids' => $bulk_order['shipment_order_id'] ,
								'order_numbers' => serialize($eligible_order_list),
								'order_details' =>serialize($bulk_order['shipment_order_details']),
								'orders_pickup_id' => $bulk_pickup['pickup_id'],
								'order_pickup_details' => serialize($bulk_pickup['pickip_details']),
								'label_url' => $bulk_label['label_url'],
								'label_request_id' => $bulk_label['bulk_label_request_id'] ,
								'total_shipments_ids' => serialize($bulk_shipment_create['total_shipment_ids'])
							));

						} else {
							$wpdb->insert($wpdb->prefix . 'elex_bulk_shipments_orders_details', 
							array(
								'shipments_order_ids' => $bulk_order['shipment_order_id'] ,
								'order_details' =>serialize($bulk_order['shipment_order_details']),
								'order_numbers' => serialize($eligible_order_list),
								'orders_pickup_id' => $bulk_pickup['pickup_id'],
								'order_pickup_details' => serialize($bulk_pickup['pickip_details']),
								'label_url' => 'NA',
								'label_request_id' => 'NA',
								'total_shipments_ids' => serialize($bulk_shipment_create['total_shipment_ids'])
							) );

							return array('success' => 1,'order_number'=> isset($bulk_order['shipment_order_id'])? $bulk_order['shipment_order_id'] : '','label_url'=> isset($bulk_label['label_url']) ? $bulk_label['label_url'] : 'Not Generated' );
						}

					} else {
						return array('success' => 0, 'error' => 'Order_generation_error:' . $bulk_order['error']);
					}
				
				} else {
					return array('success' => 0, 'error' => 'Pick_up_error:' . $bulk_pickup['error']);
				}
			} else {
				$bulk_order = $this->create_bulk_order_for_shipment($eligible_order_list, $bulk_shipment_create['total_shipment_ids'], '');
				
				if ($bulk_order['success']) {
					$bulk_label = $this->bulk_generate_label_package($eligible_order_list, $bulk_shipment_create['total_shipment_ids'], $bulk_order['shipment_order_id'], $bulk_shipment_create['orders_shipments']);
					if ($bulk_label['success']) {
						$wpdb->insert($wpdb->prefix . 'elex_bulk_shipments_orders_details',
						array(
							'shipments_order_ids' => $bulk_order['shipment_order_id'] ,
							'order_numbers' => serialize($eligible_order_list),
							'order_details' =>serialize($bulk_order['shipment_order_details']),
							'orders_pickup_id' => 'NA',
							'order_pickup_details' => 'NA',
							'label_url' => $bulk_label['label_url'],
							'label_request_id' => $bulk_label['bulk_label_request_id'] ,
							'total_shipments_ids' => serialize( $bulk_shipment_create['total_shipment_ids'] )
						));
					} else {
						$wpdb->insert($wpdb->prefix . 'elex_bulk_shipments_orders_details',
						array(
							'shipments_order_ids' => $bulk_order['shipment_order_id'] ,
							'order_numbers' => serialize($eligible_order_list),
							'order_details' =>serialize($bulk_order['shipment_order_details']),
							'orders_pickup_id' => 'NA',
							'order_pickup_details' => 'NA',
							'label_url' => 'NA',
							'label_request_id' => 'NA' ,
							'total_shipments_ids' => serialize($bulk_shipment_create['total_shipment_ids'] )
						));
						return array('success' => 1,'order_number'=> isset($bulk_order['shipment_order_id'])? $bulk_order['shipment_order_id'] : '','label_url'=> isset($bulk_label['label_url']) ? $bulk_label['label_url'] : '' );
					}
				} else {
					return array('success' => 0, 'error' => 'Order_generation_error:' . $bulk_order['error']);
				}
			}
		} else {
			return array('success' => 0, 'error' => 'Shipment_error:' . $bulk_shipment_create['error']);
		}

		return array('success' => 1,'order_number'=> isset($bulk_order['shipment_order_id'])? $bulk_order['shipment_order_id'] : '','label_url'=> isset($bulk_label['label_url']) ? $bulk_label['label_url'] : '' );
	}  
	private function bulk_generate_label_package( $orders, $shipment_ids, $shipment_order_id, $orders_shipments ) {
		$service_base_url = 'https://' . self::API_HOST . self::API_BASE_URL;

		if ($this->contracted_api_mode == 'live') {
			$service_base_url = str_replace('sandbox/', 'production/', $service_base_url);
		}
		$service_label_url                  = $service_base_url . 'print-label';
		$shipping_label_layout_parcel_post  = '';
		$shipping_label_layout_express_post = '';

		if (isset($this->settings['label_layout_type_parcel_post'])) {
			$shipping_label_layout_parcel_post = $this->settings['label_layout_type_parcel_post'];
		}

		if (isset($this->settings['label_layout_type_express_post'])) {
			$shipping_label_layout_express_post = $this->settings['label_layout_type_express_post'];
		}
		/* Providing A4-1pp as default, if user has not chosen any label layout type for the parcel services */
		if (empty($shipping_label_layout_parcel_post)) {
			$shipping_label_layout_parcel_post = 'A4-1pp';
		}

		/* Providing A4-1pp as default, if user has not chosen any label layout type for the express services */
		if (empty($shipping_label_layout_express_post)) {
			$shipping_label_layout_express_post = 'A4-1pp';
		}

		$label_req = array (
			'carrier' => array (
			  'name' => 'AUSPOST_MYPOST',
			),
			'additional_options' => array (
			  'auspost_mypost' => array (
				'step_name' => 'create_labels',
			  ),
			),
			'label' => array (
			  'include_australiapost_branding' => $this->branded,
			  'layouts' => array (
				'express_post' => $shipping_label_layout_express_post,
				'parcel_post' => $shipping_label_layout_parcel_post,
			  ),
			  'shipment_ids' => $shipment_ids,
			),
		);

		$label_rqs_headers = $this->buildHttpHeaders();
		$response          = wp_remote_post($service_label_url, array(
			'method' => 'POST',
			'httpversion' => '1.1',
			'timeout' => 70,
			'headers' => $label_rqs_headers,
			'body' => json_encode($label_req)
		));

		if (is_wp_error($response)) {
			$error_string = $response->get_error_message();
			$response     = array(
				'success' => 0,
				'error' => $error_string
			);
			return $response;
		}
		$response_array = isset($response['body']) ? json_decode($response['body'], true) : array();

		if ( !empty($response_array) && 200 == $response['response']['code']) {
			$label_request_id = isset($response_array['request_id']) ? $response_array['request_id'] : '';
			$label_url 		  = isset($response_array['url']) ? $response_array['url'] : '';
			foreach ($orders as $order_id => $order_details ) {
				if (isset($label_request_id)) {
					update_post_meta($order_id, 'wf_woo_australiamypost_labelId', $label_request_id);
				}
				if (isset($label_url)) {
					update_post_meta($order_id, 'wf_woo_australiamypost_labelURI', $label_url);
					update_post_meta($order_id, 'wf_woo_australiamypost_labelId_generation_date', current_time('Y-m-d', 0)); // current_time($type, $gmt = 0) returns time for selected time zone
				}
			}

			if (isset($label_request_id) && isset($label_url)) {
		
				$label_uri = $label_url;
	
				if (isset($this->settings['save_labels']) && $this->settings['save_labels'] == 'yes') {
					$bulk_save_label = $this->elex_auspost_bulk_save_shipping_labels($orders_shipments, $label_uri, $shipment_order_id);
					if ($bulk_save_label['success']) {
						$response = array(
							'success' => 1,
							'bulk_label_request_id' => $label_request_id,
							'label_url'=>$label_uri
						);
						return $response;
					} else {
	
						$response = array(
							'success' => 0,
							'error' => $bulk_save_label['error']
						);
						return $response;
					}
				}
			}
		
		} else {

			if (!empty($response_array)) {
				if ( isset($response_array->errors) ) {
					$errors_message = $response_array->errors[0]->message;
					update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $errors_message);
					$this->update_failed_shipment_packages($order_id, $package_index );
					$this->set_error_notices($errors_message);
					if ($this->debug) {
						echo 'Error: <b>' . $errors_message . '</b><br>';
					}
					return $shipment_order_id;
				} else {
					$errors_message = $response_array->message;
					update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $errors_message);
					$this->update_failed_shipment_packages($order_id, $package_index);
					$this->set_error_notices($errors_message);
					if ($this->debug) {
						echo 'Error: <b>' . $errors_message . '</b><br>';
					}
					return $shipment_order_id;
				}
			}
		}

		 return array('success' => 1,'bulk_label_request_id' => $label_request_id,'label_url'=>$label_uri );
		 
	}
	private function elex_auspost_bulk_save_shipping_labels( $orders, $label_uri, $shipment_order_id) {
		
		$pdf_decoded =  wp_remote_get($label_uri);
		if (is_wp_error($pdf_decoded)) {
			$error_string = $pdf_decoded->get_error_message();

			$response = array(
				'success' => 0,
				'error' => $error_string
			);
			return $response;
		} else {
			$file_name = 'Order_' . $shipment_order_id . '_label.pdf';
			$path      = ELEX_AUSPOST_LABELS . $file_name;
			$pdf       = fopen($path, 'w');
			fwrite($pdf, $pdf_decoded['body']);
			fclose($pdf);

			foreach ($orders as $order_id => $order_details ) {

				foreach ($order_details as $shipment_id ) {
					update_post_meta($order_id, 'stored_label_uri_ausmypost_elex_' . $shipment_id, content_url('/ELEX_AusPost_Labels/' . 'Order_' . $shipment_order_id . '_label.pdf'));
				}
			}
		}
		$response = array(
			'success' => 1
		);
		return $response;

	}
	public function generate_label_shipment_order( $shipment_order_id) {
		global $wpdb;
		$prefix =  $wpdb->prefix;

		$row_data = "SELECT * FROM {$prefix}elex_bulk_shipments_orders_details WHERE  {$prefix}elex_bulk_shipments_orders_details.shipments_order_ids LIKE '{$shipment_order_id}'";
		$row      = $wpdb->get_results( ( $wpdb->prepare( '%1s', $row_data ) ? stripslashes( $wpdb->prepare( '%1s', $row_data ) ) : $wpdb->prepare( '%s', '' ) ), ARRAY_A);
		if (!empty($row)) {

			foreach ($row as $key => $val) {
				$orders            = unserialize($val['order_numbers']);
				$shipment_ids      = unserialize($val['total_shipments_ids']);
				$shipment_order_id = $shipment_order_id;
			}

			foreach ($orders as $order_id => $v) {

				$shipment_ids = get_post_meta($order_id, 'wf_woo_australiamypost_shipmentId', true);

				if (!empty( $shipment_ids) ) {
					foreach ($shipment_ids as $k => $v ) {
						$total_shipments_ids[$order_id][] = $v;
					}
				}
			}
			$bulk_label = $this->bulk_generate_label_package($orders, $shipment_ids, $shipment_order_id, $total_shipments_ids);
			if ($bulk_label['success']) {

				$wpdb->update( $wpdb->prefix . 'elex_bulk_shipments_orders_details', 
					array( 
						'label_url' => $bulk_label['label_url'], 
						'label_request_id' => $bulk_label['bulk_label_request_id'] 
					), 
					array( 
						'shipments_order_ids' => $shipment_order_id
					), 
					array( '%s', '%s' ), 
					array( '%s' ) 
				);

			} else {

				return array('success' => 0,'error'=>$bulk_label['error'] );
			}
		} else {
			$response = array(
				'success' => 0,
				'error' => 'No Shipment Order number Found'
			);
			return $response;
		}
		return array('success' => 1,'order_number'=> $shipment_order_id,'label_url'=> isset($bulk_label['label_url']) ? $bulk_label['label_url'] : '' );
	
	}
	private function create_bulk_order_for_shipment( $orders, $shipment_ids, $pickup_id ) {
		$service_base_url = 'https://' . self::API_HOST . self::API_BASE_URL;

		if ($this->contracted_api_mode == 'live') {
			$service_base_url = str_replace('sandbox/', 'production/', $service_base_url);
		}

		$service_shipments_url = $service_base_url . 'print-label';
		$shipment_order_id     = '';
		$order_shipment_detail = array();
		$sending_date          = current_time('d-M-Y', 0);
		$sender_reference      = 'Orders #' . $sending_date;
		if (strlen($sender_reference) >= 50) {
			$sender_reference = substr($sender_reference, 0, 49);
		}

		if (isset($pickup_id) && ''!= $pickup_id) {
			$info = array (
				'carrier' => array (
				  'name' => 'AUSPOST_MYPOST',
				),
				'additional_options' => array (
				  'auspost_mypost' => array (
					'step_name' => 'create_order_from_shipments_and_create_pickup',
				  ),
				),
				'order_and_pickup' => array (
				  'order' => array (
					'order_reference' => $sender_reference,
					'shipment_ids' => $shipment_ids
				  ),
				  'pickup' => array (
					'pickup_ids' => array(
						0 => $pickup_id
					),
				  ),
				),
			);
		} else {
			$info = array (
				'carrier' => array (
				  'name' => 'AUSPOST_MYPOST',
				),
				'additional_options' => array (
				  'auspost_mypost' => array (
					'step_name' => 'create_order_from_shipments',
				  ),
				),
				'order' => array (
				  'order_reference' => $sender_reference,
				  'shipment_ids' => $shipment_ids,
				),
			);
		}

		$rqs_headers = $this->buildHttpHeaders();
		$res         = wp_remote_post($service_shipments_url, array(
			'method' 		=> 'POST',
			'httpversion'   => '1.1',
			'timeout'       => 70,
			'headers'       => $rqs_headers,
			'body'          => json_encode($info)
		));

		if (is_wp_error($res)) {
			$error_string = $res->get_error_message();
			$response     = array(
				'success' => 0,
				'error' => $error_string
			);
			return $response;
		}

		$response_array = isset($res['body']) ? json_decode($res['body']) : array();
		if ( !empty($response_array) && 200 == $res['response']['code']) {
			
			if (isset($response_array->items[0]->errors)) {
				$response = array(
					'success' => 0,
					'error' => $response_array->items[0]->errors[0]->message
				);
				return $response;
			}

			$order_shipment_detail = $response_array;
			$shipment_order_id     = $response_array->order_id;

			foreach ($orders as $order_id => $order_details ) {
				$order_shipment_details = get_post_meta($order_id, 'wf_woo_australiamypost_shipment_order_details', true);

				if (empty($order_shipment_details)) {
					$order_shipment_details   = array();
					$order_shipment_details[] = $order_shipment_detail;
				} else {
					$order_shipment_details[] = $order_shipment_detail;
				}
				update_post_meta($order_id, 'wf_woo_australiamypost_shipment_order_details', $order_shipment_details);
				update_post_meta($order_id, 'wf_australia_mypost_order', $order_shipment_detail);

				return array('success' => 1,'shipment_order_id' =>$shipment_order_id, 'shipment_order_details'=> $order_shipment_detail );
			}		
		} else {

			if (!empty($response_array)) {
				if ( isset($response_array->errors) ) {
					$errors_message = $response_array->errors[0]->message;
					$response       = array(
						'success' => 0,
						'error' => $errors_message
					);
					return $response;
				} else {
					$errors_message = $response_array->message;
					$response       = array(
						'success' => 0,
						'error' => $errors_message
					);
					return $response;
				}
			}
		}

		return array('success' => 0,'error' =>'Order Creation error');
	}
	public function create_bulk_pickup_for_shipment( $orders, $shipment_ids) {
		
		$service_base_url = 'https://' . self::API_HOST . self::API_BASE_URL;

		if ($this->contracted_api_mode == 'live') {
			$service_base_url = str_replace('sandbox/', 'production/', $service_base_url);
		}
		$service_shipments_url = $service_base_url . 'schedule-pickup';
		$shipment_pickup_id    = '';

		$info =array (
			'carrier' => array (
			  'name' => 'AUSPOST_MYPOST',
			),
			'auspost_mypost_create_pickup' => array (
			  'from' => array (
					'name' => $this->shipper_name,
					'phone' => $this->shipper_phone_number,
					'address_line_1' => $this->shipper_address,
					'address_line_2' => $this->shipper_address,
					'city_locality' => $this->shipper_suburb,
					'state_province' => $this->shipper_state,
					'postal_code' => $this->shipper_postcode,
			  ),
			  'pickup_date' => $this->pickup_date,
			  'pickup_product_id' => $this->pickup_time_id,
			  'shipment_ids' => $shipment_ids
			),
		);

		$rqs_headers = $this->buildHttpHeaders();
		$res         = wp_remote_request($service_shipments_url, array(
			'method' => 'POST',
			'httpversion' => '1.1',
			'timeout'     => 70,
			'headers' => $rqs_headers,
			'body' => json_encode($info)
		));

		if (is_wp_error($res)) {
			$error_string = $res->get_error_message();
			$response     = array(
				'success' => 0,
				'error' => $error_string
			);
			return $response;
		}

		$response_array         = isset($res['body']) ? json_decode($res['body']) : array();
		$pickup_shipment_detail = array();

		if ( !empty($response_array) && 200 == $res['response']['code']) {

			if (!empty($response_array->errors)) {
		
				$response = array(
					'success' => 0,
					'error' => $response_array->errors[0]->message
				);
				return $response;
			}
	
			if (!empty($response_array)) {

				if (isset($response_array->items[0]->errors)) {
					$response = array(
						'success' => 0,
						'error' => $response_array->items[0]->errors[0]->message
					);
					return $response;
				}

				$shipment_pickup_id =$response_array->pickup_id;
				foreach ($response_array as $key => $val) {
					$pickup_shipment_detail[$shipment_pickup_id][$key] = $val;
				}
				$pickup_shipment_detail[$shipment_pickup_id]['pickup_date'] = $this->pickup_date;
				foreach ($orders as $order_id => $order_details ) {
					$pickup_shipment_details = get_post_meta($order_id, 'wf_woo_australiamypost_shipment_pickup_details', true);
	
					if (empty($pickup_shipment_details)) {
						$pickup_shipment_details   = array();
						$pickup_shipment_details[] = $pickup_shipment_detail;
					} else {
						$pickup_shipment_details[] = $pickup_shipment_detail;
					}
					update_post_meta($order_id, 'wf_woo_australiamypost_shipment_pickup_details', $pickup_shipment_details);
					update_post_meta($order_id, 'wf_australia_mypost_pickup', $pickup_shipment_detail);
				}
			}
		
		} else {
			if (!empty($response_array)) {
				if ( isset($response_array->errors) ) {
					$response = array(
						'success' => 0,
						'error' => $response_array->errors[0]->message
					);
					return $response;
				} else {
					$response = array(
						'success' => 0,
						'error' => $response_array->message
					);
					return $response;
				}
			}
		}

		return array('success' => 1,'pickup_id' =>$shipment_pickup_id ,'pickip_details' => 	$pickup_shipment_detail);
	}
	public function wf_bulk_create_shipment( $orders) {
		/*Shipment label printing is only for contracted accounts*/
		if (!$this->mypost_contracted_rates) {
			return false;
		}
		$total_shipment_ids            = array();
		$total_order_shipment_ids      = array();
		$total_order_shipment_packages = array();
		foreach ($orders as  $order_id => $order_details ) {
			if ( isset ( $order_details['shipments_ids'] ) && !empty($order_details['shipments_ids'])) {
				foreach ($order_details['shipments_ids'] as $k => $v ) {
					$total_shipment_ids[]                  = $v;
					$total_order_shipment_ids[$order_id][] = $v;
				}
			} else {
				$order = $this->wf_load_order($order_id);
				
				if (!$order) {
					return;
				}

				$extra_cover_status           = get_post_meta($order_id, 'extra_cover_opted_ausmypost_elex', true);
				$signature_required           = get_post_meta($order_id, 'signature_required_opted_ausmypost_elex', true);
				$shipping_country             = wf_get_order_shipping_country($order);
				$all_auspost_postage_products = !empty($this->settings['services']) ? $this->settings['services'] : array();

				update_option('request_to_create_shipment', true);


				if (( isset($_GET['weight']) && isset($_GET['height']) && isset($_GET['width']) && isset($_GET['length']) )) {
					$this->titles_in_request_array            = $this->return_package_data_from_request($_GET['title']);
					$this->weights_in_request_array           = $this->return_package_data_from_request($_GET['weight']);
					$this->lengths_in_request_array           = $this->return_package_data_from_request($_GET['length']);
					$this->widths_in_request_array            = $this->return_package_data_from_request($_GET['width']);
					$this->heights_in_request_array           = $this->return_package_data_from_request($_GET['height']);
					$this->shipment_services_in_request_array = $this->return_package_data_from_request($_GET['shipping_service']);
				} else {
					$this->titles_in_request_array = array();
				}

				$convention_shipment_service_requests_array = array();
				$failed_shipment_packages_stored            = get_post_meta($order_id, 'failed_create_shipment_packages_ausmypost_elex', true);
				$failed_shipment_packages_stored            = array();
				$service_count                              = 0;
				if (!empty($failed_shipment_packages_stored)) {
					foreach ($failed_shipment_packages_stored as $failed_package_order) {
						$convention_shipment_service_requests_array[$failed_package_order] = $this->shipment_services_in_request_array[$service_count];
						$service_count++;
					}
		
					$this->shipment_services_in_request_array = $convention_shipment_service_requests_array;
				}

				/* Obtaining service selected for the shipment */
				$serviceName = $this->wf_get_shipping_service($order, true);
	
				$label_shipping_method_id    = $this->get_selected_shipping_service_id($all_auspost_postage_products, $serviceName, $order);
				$available_shipping_services = $this->settings['services'];
				if (array_key_exists($label_shipping_method_id, $available_shipping_services)) {
					if ($available_shipping_services[$label_shipping_method_id]['extra_cover']) {
						$extra_cover_status = true;
					}
				}

				$line_items       = array();
				$package_requests = array();

				if ($this->packing_method == 'weight') {
					$package_requests = $this->weight_based_packing($order);
				} elseif ($this->packing_method == 'box_packing') {
					$package_requests = $this->box_packing($order);
				} else {
					$package_requests = $this->per_item_packing($order);
				}

				$package_index            = 0;
				$package_commercial_value = false;
				$desc_for_other           = '';
				
				if (is_array($package_requests)) {

					$postage_products_service_options = $available_shipping_services;
					if ($shipping_country == 'AU') {
	
						foreach ($package_requests as $key => $package_request) {
							$sod                     = false;
							$line_items              = array();
							$create_shipment_service = $label_shipping_method_id;		

							if ($package_request['Weight']['Units'] != 'kg') {
								$package_request['Weight']['Value'] = wc_get_weight($package_request['Weight']['Value'], 'kg', $this->weight_unit);
							}
	
							if (isset($package_request['Dimensions']['Units']) && $package_request['Dimensions']['Units'] != 'cm') {
	
								$package_request['Dimensions']['Length'] = wc_get_dimension($package_request['Dimensions']['Length'], 'cm', $package_request['Dimensions']['Units']);
								$package_request['Dimensions']['Width']  = wc_get_dimension($package_request['Dimensions']['Width'], 'cm', $package_request['Dimensions']['Units']);
								$package_request['Dimensions']['Height'] = wc_get_dimension($package_request['Dimensions']['Height'], 'cm', $package_request['Dimensions']['Units']);
							}
							if ( is_array($postage_products_service_options)  && !empty($postage_products_service_options)  && isset($postage_products_service_options[$create_shipment_service]) && isset($postage_products_service_options[$create_shipment_service]['signature_on_delivery_option']) && ''!=$postage_products_service_options[$create_shipment_service]['signature_on_delivery_option'] ) {
								$sod = true;
							} elseif (500< round($package_request['InsuredValue']['Amount'], 2)) {
								$sod = true;
							} elseif ($signature_required) {
								$sod = true;
							}
							$sending_date       = current_time('d-M-Y', 0);
							$sender_reference_1 = $this->rate_type ? 'Order #' . $order_id : 'Order #' . $order_id . '-' . $sending_date;
							if (strlen($sender_reference_1) >= 50) {
								$sender_reference_1 = substr($sender_reference_1, 0, 49);
							}
							$sender_reference_2        = $this->ship_content;
							$order->shipping_address_1 = strlen($order->shipping_address_1) > 40 ? substr( $this->string_clean( $order->shipping_address_1 ), 0, 37) . '...' : $order->shipping_address_1;
							$order->shipping_address_2 = strlen($order->shipping_address_2) > 40 ? substr( $this->string_clean( $order->shipping_address_2 ), 0, 37) . '...' : $order->shipping_address_2;
							$order_customer_note       = $order->get_customer_note();
	
							$line_items = array(
								'ship_from' => array (
									'name' => $this->shipper_name,
									'business_name' => $this->shipper_name,
									'address_type' => 'STANDARD_ADDRESS',
									'phone' => array (
										'raw_phone' => $this->shipper_phone_number,
									),
									'email' => $this->shipper_email,
									'address_line_1' => $this->shipper_address,
									'address_line_2' => '',
									'city_locality' => $this->shipper_suburb,
									'state_province' => $this->shipper_state,
									'postal_code' => $this->shipper_postcode,
									'country_code' => 'AU',
								),
								'ship_to' => array (
								  'name' => $order->shipping_first_name . ' ' . $order->shipping_last_name,
								  'business_name' => $order->shipping_company,
								  'address_type' => 'STANDARD_ADDRESS',
								  'phone' => array (
									'raw_phone' => $order->billing_phone,
								  ),
								  'email' => $order->billing_email,
								  'address_line_1' => $order->shipping_address_1,
								  'address_line_2' => $order->shipping_address_2,
								  'city_locality' => $order->shipping_city,
								  'state_province' => $order->shipping_state,
								  'postal_code' => $order->shipping_postcode,
								  'country_code' => $order->shipping_country,
								  'delivery_instructions' =>$order_customer_note,
								),
								'shipment_options' => array (
								  'auspost_mypost' => array (
									'shipment_reference' => $order_id . '_' . $package_index . '_' . uniqid(),
									'email_tracking_enabled' => $this->email_tracking,
									'sender_references' => array (
									  0 => $sender_reference_1,
									  1 => $sender_reference_2,
									),
								  ),
								),
								'items' => array (
								  0 => array (
									'description' => $package_request['Name'],
									'item_options' => array (
									  'auspost_mypost' => array (
										'contains_dangerous_goods' => false,
										'cover_amount' => $extra_cover_status ? round($package_request['InsuredValue']['Amount'], 2) : array(),
										'product_id' => $create_shipment_service,
										'signature_on_delivery' => $sod,
									  ),
									),
									'weight' => array (
									  'value' => $package_request['Weight']['Value'] < 0.01 ? 0.01 : round($package_request['Weight']['Value'], 3),
									  'unit' => 'KG',
									),
									'length' => array (
									  'value' => round($package_request['Dimensions']['Length'], 1),
									  'unit' => 'CM',
									),
									'width' => array (
									  'value' => round($package_request['Dimensions']['Width'], 1),
									  'unit' => 'CM',
									),
									'height' => array (
									  'value' => round($package_request['Dimensions']['Height'], 1),
									  'unit' => 'CM',
									),
								  ),
								),
							);
							if (in_array($order->shipping_country, $this->european_union_countries) && isset($this->settings['import_reference_number']) && $this->settings['import_reference_number'] && !empty($this->settings['import_reference_number'])) {
								$line_items['items'][0]['item_options']['auspost_mypost']['import_reference_number'] = substr($this->settings['import_reference_number'], 0, 39);
							}
							if (empty($line_items['items'][0]['item_options']['auspost_mypost']['cover_amount'])) {
								unset($line_items['items'][0]['item_options']['auspost_mypost']['cover_amount']);
							}
	
							$total_order_shipment_packages[] = $line_items;
	
							$package_index++;
						}
	
					} else {
	
						foreach ($package_requests as $key => $package_request) {
							if (!empty($this->order_package_categories_arr[$package_index])) {
								if ($this->order_package_categories_arr[$package_index] == 'OTHER') {
									$package_commercial_value = true;
									if (!empty($this->order_desc_for_other_category_arr[$package_index])) {
										$desc_for_other = $this->order_desc_for_other_category_arr[$package_index];
									} else {
										$desc_for_other = 'Sale';
									}
								}
							}
	
							$create_shipment_service = $label_shipping_method_id;
							$sod 					 = false;
							if (isset($package_request['Dimensions']['Units']) && $package_request['Dimensions']['Units'] != 'cm') {
	
								$package_request['Dimensions']['Length'] = wc_get_dimension($package_request['Dimensions']['Length'], 'cm', $package_request['Dimensions']['Units']);
								$package_request['Dimensions']['Width']  = wc_get_dimension($package_request['Dimensions']['Width'], 'cm', $package_request['Dimensions']['Units']);
								$package_request['Dimensions']['Height'] = wc_get_dimension($package_request['Dimensions']['Height'], 'cm', $package_request['Dimensions']['Units']);
							}
	
							if ( is_array($postage_products_service_options)  && !empty($postage_products_service_options)  && isset($postage_products_service_options[$create_shipment_service]) && isset($postage_products_service_options[$create_shipment_service]['signature_on_delivery_option']) && ''!=$postage_products_service_options[$create_shipment_service]['signature_on_delivery_option'] ) {
								$sod = true;
							} elseif (500< round($package_request['InsuredValue']['Amount'], 2)) {
								$sod = true;
							} elseif ($signature_required) {
								$sod = true;
							}
							$sending_date       = current_time('d-M-Y', 0);
							$sender_reference_1 = $this->rate_type ? 'Order #' . $order_id : 'Order #' . $order_id . '-' . $sending_date;
							if (strlen($sender_reference_1) >= 50) {
								$sender_reference_1 = substr($sender_reference_1, 0, 49);
							}
							$sender_reference_2        = $this->ship_content;
							$order->shipping_address_1 = strlen($order->shipping_address_1) > 40 ? substr( $this->string_clean( $order->shipping_address_1 ), 0, 37) . '...' : $order->shipping_address_1;
							$order->shipping_address_2 = strlen($order->shipping_address_2) > 40 ? substr( $this->string_clean( $order->shipping_address_2 ), 0, 37) . '...' : $order->shipping_address_2;
							$order_customer_note       = $order->get_customer_note();
						
							if ($this->packing_method == 'weight' || $this->packing_method == 'box_packing') {

								if (isset( $package_request['Item_contents'])) {
									foreach ($package_request['Item_contents'] as $key =>$val) {
										$package_request['Item_contents'][$key]['cover_amount'] = round($package_request['Item_contents'][$key]['value'], 2);
										unset($package_request['Item_contents'][$key]['value']);
									}
								}
							} else {
								if (isset( $package_request['Item_contents'])) {
									$package_request['Item_contents']['cover_amount'] = round($package_request['Item_contents']['value'], 2);
									unset($package_request['Item_contents']['value']);
									$package_request['Item_contents'] = array($package_request['Item_contents']);
								}
							}
	
							$line_items = array(
								'ship_from' => array (
									'name' => $this->shipper_name,
									'business_name' => $this->shipper_name,
									'address_type' => 'STANDARD_ADDRESS',
									'phone' => array (
										'raw_phone' => $this->shipper_phone_number,
									),
									'email' => $this->shipper_email,
									'address_line_1' => $this->shipper_address,
									'address_line_2' => '',
									'city_locality' => $this->shipper_suburb,
									'state_province' => $this->shipper_state,
									'postal_code' => $this->shipper_postcode,
									'country_code' => 'AU',
								),
								'ship_to' => array (
								  'name' => $order->shipping_first_name . ' ' . $order->shipping_last_name,
								  'business_name' => $order->shipping_company,
								  'address_type' => 'STANDARD_ADDRESS',
								  'phone' => array (
									'raw_phone' => $order->billing_phone,
								  ),
								  'email' => $order->billing_email,
								  'address_line_1' => $order->shipping_address_1,
								  'address_line_2' => $order->shipping_address_2,
								  'city_locality' => $order->shipping_city,
								  'state_province' => $order->shipping_state,
								  'postal_code' => $order->shipping_postcode,
								  'country_code' => $order->shipping_country,
								  'delivery_instructions' =>$order_customer_note,
								),
								'shipment_options' => array (
								  'auspost_mypost' => array (
									'shipment_reference' => $order_id . '_' . $package_index . '_' . uniqid(),
									'email_tracking_enabled' => $this->email_tracking,
									'sender_references' => array (
									  0 => $sender_reference_1,
									  1 => $sender_reference_2,
									),
								  ),
								),
								'items' => array (
								  0 => array (
									'description' => $package_request['Name'],
									'item_options' => array (
									  'auspost_mypost' => array (
										'contains_dangerous_goods' => false,
										'cover_amount' => $extra_cover_status ? round($package_request['InsuredValue']['Amount'], 2) : array(),
										'product_id' => $create_shipment_service,
										'signature_on_delivery' => $sod,
										'commercial_value' => $package_commercial_value,
										'classification_type'   => isset($this->order_package_categories_arr[$package_index]) ? $this->order_package_categories_arr[$package_index] : 'OTHER',
										'description_of_other'  => !empty($desc_for_other) ? $desc_for_other : 'Sale',
										'item_contents' =>(array) $package_request['Item_contents'],
									  ),
									),
									'weight' => array (
									  'value' => $package_request['Weight']['Value'] < 0.01 ? 0.01 : round($package_request['Weight']['Value'], 3),
									  'unit' => 'KG',
									),
									'length' => array (
									  'value' => round($package_request['Dimensions']['Length'], 1),
									  'unit' => 'CM',
									),
									'width' => array (
									  'value' => round($package_request['Dimensions']['Width'], 1),
									  'unit' => 'CM',
									),
									'height' => array (
									  'value' => round($package_request['Dimensions']['Height'], 1),
									  'unit' => 'CM',
									),
								  ),
								),
							);
	
							if (in_array($order->shipping_country, $this->european_union_countries) && isset($this->settings['import_reference_number']) && $this->settings['import_reference_number'] && !empty($this->settings['import_reference_number'])) {
								$line_items['items'][0]['item_options']['auspost_mypost']['import_reference_number'] = substr($this->settings['import_reference_number'], 0, 39);
							}
							if (empty($line_items['items'][0]['item_options']['auspost_mypost']['cover_amount'])) {
								unset($line_items['items'][0]['item_options']['auspost_mypost']['cover_amount']);
							}
							$total_order_shipment_packages[] = $line_items;
							$package_index++;
						}
					}
				}

			}
		}

		$total_shipments = count($total_order_shipment_packages) + count($total_shipment_ids);

		if ($total_shipments>50) {
			$response = array(
				'success' => 0,
				'error' => 'TOTAL NUMBER OF SHIPMENTS EXCEEDS 50.'
			);
			return $response;
		}

		if (!empty($total_order_shipment_packages)) {

			$create_bulk_shipment_for_package = $this->create_bulk_shipment_for_package($total_order_shipment_packages, $total_shipment_ids);
		} else {
			$response = array(
				'success' => 1,
				'total_shipment_ids' =>$total_shipment_ids,
				'orders_shipments' => $total_order_shipment_ids
			);
			return $response;
		}

		return $create_bulk_shipment_for_package;
	}
	public function create_bulk_shipment_for_package( $total_order_shipment_packages, $total_shipment_ids ) {
		$service_base_url = 'https://' . self::API_HOST . self::API_BASE_URL;

		if ($this->contracted_api_mode == 'live') {
			$service_base_url = str_replace('sandbox/', 'production/', $service_base_url);
		}

		$service_shipments_url = $service_base_url . 'print-label';
		$orders_shipments      = array();
		$info                  = array(
			'carrier' => array (
			  'name' => 'AUSPOST_MYPOST',
			),
			'additional_options' => array (
			  'auspost_mypost' => array (
				'step_name' => 'create_shipments',
			  ),
			),
			'shipments' =>$total_order_shipment_packages ,
		);

		$rqs_headers = $this->buildHttpHeaders();
		$res         = wp_remote_post($service_shipments_url, array(
			'method' => 'POST',
			'httpversion' => '1.1',
			'timeout'     => 70,
			'headers' => $rqs_headers,
			'body' => json_encode($info)
		));

		if (is_wp_error($res)) {
			$error_string = $res->get_error_message();

			$response = array(
				'success' => 0,
				'error' => $error_string
			);
			return $response;
		}

		$response_array = isset($res['body']) ? json_decode($res['body']) : array();
		if ( !empty($response_array) && 200 == $res['response']['code']) {

			if (!empty($response_array->errors)) {
		
				$response = array(
					'success' => 0,
					'error' => $response_array->errors[0]->message
				);
				return $response;
			}
	
			if (!empty($response_array)) {

				if (isset($response_array->items[0]->errors)) {
					$response = array(
						'success' => 0,
						'error' => $response_array->items[0]->errors[0]->message
					);
					return $response;
				}

				$tracking_id_cs = '';

				foreach ($response_array as $index => $shipment_content ) {
					$shipment_date = '';

					$order_id_array   = explode('_', $shipment_content->shipment_reference);
					$order_id         = $order_id_array[0];
					$order_id_index   = $order_id_array[1];
					$shipping_service = '';
					//shipments array
	
					$shipment_id   = $shipment_content->shipment_id;
					$shipment_date = substr($shipment_content->shipment_creation_date, 0, 10);
					
					$items = $shipment_content->items;
					foreach ($items as $item) {
						$tracking_id_cs  .= $item->tracking_id;
						$tracking_id_cs  .= ',';
						$shipping_service = $item->product_id;
					}
		
					if (!class_exists('WF_Tracking_Admin_AusPost')) {
						include plugins_url(basename(plugin_dir_path(__FILE__)) . '/australia_post/includes/class-wf-tracking-admin.php', basename(__FILE__));
					}

					try {
						$admin_notice = WfTrackingUtil::update_tracking_data($order_id, $tracking_id_cs, 'australia-post', WF_Tracking_Admin_AusPost::SHIPMENT_SOURCE_KEY, WF_Tracking_Admin_AusPost::SHIPMENT_RESULT_KEY, $shipment_date);
					} catch (Exception $e) {
						// Do nothing.
					}
					
					$order_shipment_ids = get_post_meta($order_id, 'wf_woo_australiamypost_shipmentId', true);
		
					if (empty($order_shipment_ids)) {
						$order_shipment_ids                  = array();
						$order_shipment_ids[$order_id_index] = $shipment_id;
					} else {
						$order_shipment_ids[$order_id_index] = $shipment_id;
					}
	
					$total_shipment_ids[]          = $shipment_id;
					$orders_shipments[$order_id][] = $shipment_id;
					update_post_meta($order_id, 'wf_ausmypost_tracking_ids', $tracking_id_cs);
					update_post_meta($order_id, 'elex_ausmypost_shipping_service_' . $shipment_id, $shipping_service);
					update_post_meta($order_id, 'wf_woo_australiamypost_shipmentId', $order_shipment_ids);
					
				}

			}
		
		} else {
			if (!empty($response_array)) {
				if ( isset($response_array->errors) ) {
					$response = array(
						'success' => 0,
						'error' => $response_array->errors[0]->message
					);
					return $response;
				} else {
					$response = array(
						'success' => 0,
						'error' => $response_array['message']
					);
					return $response;
				}
			}
		}

		return array('success' => 1,'total_shipment_ids' =>$total_shipment_ids,'orders_shipments' => $orders_shipments );
		
	}
	public function create_pickup_for_shipment( $order, $shipment_ids) {
		
		$service_base_url = 'https://' . self::API_HOST . self::API_BASE_URL;

		if ($this->contracted_api_mode == 'live') {
			$service_base_url = str_replace('test/', '', $service_base_url);
		}

		$order_id              = $this->wf_get_order_id($order);
		$service_shipments_url = $service_base_url . 'pickups';
		$shipment_pickup_id    = '';

		$info = array(
			'pickups' => array(
				array(
					'from' => array(
						'name'  => $this->shipper_name,
						'lines' => array(
							$this->shipper_address,
						),
						'suburb'    => $this->shipper_suburb,
						'state'     => $this->shipper_state,
						'postcode'  => $this->shipper_postcode,
						'phone'     => $this->shipper_phone_number,
					),
					'pickup_date' =>  $this->pickup_date,
					'product_id' =>  $this->pickup_time_id,
					'shipment_ids' => $shipment_ids
				),
			),
		);

		$this->debug('MyPost Business Pickup Request <br> <pre>');
		$this->debug(print_r(json_encode($info, JSON_PRETTY_PRINT), true));
		$this->debug('</pre>');

		$rqs_headers = $this->buildHttpHeaders();
		$res         = wp_remote_request($service_shipments_url, array(
			'method' => 'POST',
			'httpversion' => '1.1',
			'headers' => $rqs_headers,
			'body' => json_encode($info)
		));

		if (is_wp_error($res)) {
			$error_string = $res->get_error_message();
			update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $error_string);
			$this->update_failed_shipment_packages($order_id, range(0 , count($shipment_ids) - 1) );
			$this->set_error_notices($error_string);
			if ($this->debug) {
				echo 'Error: <b>' . $error_string . '</b><br>';
			}
			return $shipment_pickup_id;
		}

		if (is_wp_error($res)) {
			$error_string = $res->get_error_message();
			$this->set_error_notices($error_string);
			update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $error_string);
			$this->update_failed_shipment_packages($order_id, range(0 , count($shipment_ids) - 1));
			return $shipment_pickup_id;
		}

		$response_array = isset($res['body']) ? json_decode($res['body']) : array();

		$this->debug(' MyPost Business Pickup Response: <br><pre>');
		$this->debug(print_r(json_encode($response_array, JSON_PRETTY_PRINT), true));
		$this->debug('</pre>');
		if (!empty($response_array->errors)) {
			$this->set_error_notices($response_array->errors[0]->message);
			update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $response_array->errors[0]->message);

			return $shipment_pickup_id;
		}

		if (!empty($response_array)) {
			if (isset($response_array->items[0]->errors)) {
				$this->set_error_notices($response_array->items[0]->errors[0]->message);
				update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $response_array->errors[0]->message);
				return $shipment_pickup_id;
			}
			$pickup_shipment_detail =array();

			$shipment_pickup_id =$response_array->pickups[0]->pickup_id;
			foreach ($response_array->pickups as $key => $val) {
				$pickup_shipment_detail[$shipment_pickup_id][$key] = $val;
			}
			$pickup_shipment_detail[$shipment_pickup_id]['pickup_date'] = $this->pickup_date;
			$pickup_shipment_details                                    = get_post_meta($order_id, 'wf_woo_australiamypost_shipment_pickup_details', true);

			if (empty($pickup_shipment_details)) {
				$pickup_shipment_details   = array();
				$pickup_shipment_details[] = $pickup_shipment_detail;
			} else {
				$pickup_shipment_details[] = $pickup_shipment_detail;
			}
			update_post_meta($order_id, 'wf_woo_australiamypost_shipment_pickup_details', $pickup_shipment_details);
			update_post_meta($order_id, 'wf_australia_mypost_pickup', $pickup_shipment_detail);
		}

		return $shipment_pickup_id;
	}
	public function get_selected_service_type_by_id( $service_id) {

		$type = '';
		if ('B20' == $service_id || 'B21' == $service_id ||'BE9PB1' == $service_id ||'BE9PB2' == $service_id ||'BE9PB3' == $service_id ||'BE9PB4' == $service_id ||'BE9P50' == $service_id ||'BE9P30' == $service_id ||'BE9P10' == $service_id ||'BE9P05' == $service_id ) {
			$type = 'Express Post';
		}
		if ('B30' == $service_id || 'B31' == $service_id ||'BE1PB4' == $service_id ||'BE1PB3' == $service_id ||'BE1PB2' == $service_id ||'BE1PB1' == $service_id ||'BE1P05' == $service_id ||'BE1P10' == $service_id ||'BE1P30' == $service_id ||'BE1P50' == $service_id ) {
			$type = 'Parcel Post';
		}
		return $type;
	}
	public function check_service_is_flaterate( $service_id) {

		$type = false;
		if ('BE9PB1' == $service_id ||'BE9PB2' == $service_id ||'BE9PB3' == $service_id ||'BE9PB4' == $service_id ||'BE9P50' == $service_id ||'BE9P30' == $service_id ||'BE9P10' == $service_id ||'BE9P05' == $service_id ) {
			$type = true;
		}
		if ('BE1PB4' == $service_id ||'BE1PB3' == $service_id ||'BE1PB2' == $service_id ||'BE1PB1' == $service_id ||'BE1P05' == $service_id ||'BE1P10' == $service_id ||'BE1P30' == $service_id ||'BE1P50' == $service_id ) {
			$type = true;
		}
		return $type;
	}

	private function update_failed_shipment_packages( $order_id, $package_index) {
		$failed_shipment_packages = get_post_meta($order_id, 'failed_create_shipment_packages_ausmypost_elex', true);
		if (!empty($failed_shipment_packages)) {
			$failed_shipment_packages[] = $package_index;
		} else {
			$failed_shipment_packages   = array();
			$failed_shipment_packages[] = $package_index;
		}

		update_post_meta($order_id, 'failed_create_shipment_packages_ausmypost_elex', $failed_shipment_packages);
		return;
	}
	private function create_order_for_shipment( $order, $shipment_ids, $service_base_url, $package_index) {

		$order_id              = $this->wf_get_order_id($order);
		$service_shipments_url = $service_base_url . 'print-label';
		$shipment_order_id     = '';
		$sending_date          = current_time('d-M-Y', 0);
		$order_number          = $order->get_order_number();
		$sender_reference      = $this->rate_type ? 'Order #' . $order_number : 'Order #' . $order_number . '-' . $sending_date;
		if (strlen($sender_reference) >= 50) {
			$sender_reference = substr($sender_reference, 0, 49);
		}

		$info = array (
			'carrier' => array (
			  'name' => 'AUSPOST_MYPOST',
			),
			'additional_options' => array (
			  'auspost_mypost' => array (
				'step_name' => 'create_order_from_shipments',
			  ),
			),
			'order' => array (
			  'order_reference' => $sender_reference,
			  'shipment_ids' => $shipment_ids,
			),
		);

		$this->debug('MyPost Business Order Request <br> <pre>');
		$this->debug(print_r(json_encode($info, JSON_PRETTY_PRINT), true));
		$this->debug('</pre>');

		$rqs_headers = $this->buildHttpHeaders();
		$res         = wp_remote_post($service_shipments_url, array(
			'method' 		=> 'POST',
			'httpversion'   => '1.1',
			'timeout'       => 70,
			'headers'       => $rqs_headers,
			'body'          => json_encode($info)
		));

		if (is_wp_error($res)) {
			$error_string = $res->get_error_message();
			update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $error_string);
			$this->update_failed_shipment_packages($order_id, $package_index);
			$this->set_error_notices($error_string);
			if ($this->debug) {
				echo 'Error: <b>' . $error_string . '</b><br>';
			}
			return $shipment_order_id;
		}

		$response_array = isset($res['body']) ? json_decode($res['body']) : array();

		$this->debug(' MyPost Business Order Response: <br><pre>');
		$this->debug(print_r(json_encode($response_array, JSON_PRETTY_PRINT), true));
		$this->debug('</pre>');
		if ( !empty($response_array) && 200 == $res['response']['code']) {

			$order_shipment_detail = array();
			$order_shipment_detail = $response_array;

			$order_shipment_details = get_post_meta($order_id, 'wf_woo_australiamypost_shipment_order_details', true);

			if (empty($order_shipment_details)) {
				$order_shipment_details   = array();
				$order_shipment_details[] = $order_shipment_detail;
			} else {
				$order_shipment_details[] = $order_shipment_detail;
			}
			update_post_meta($order_id, 'wf_woo_australiamypost_shipment_order_details', $order_shipment_details);
			update_post_meta($order_id, 'wf_australia_mypost_order', $order_shipment_detail);
		
		} else {

			if (!empty($response_array)) {
				if ( isset($response_array->errors) ) {
					$errors_message = $response_array->errors[0]->message;
					update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $errors_message);
					$this->update_failed_shipment_packages($order_id, $package_index );
					$this->set_error_notices($errors_message);
					if ($this->debug) {
						echo 'Error: <b>' . $errors_message . '</b><br>';
					}
					return $shipment_order_id;
				} else {
					$errors_message = $response_array->message;
					update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $errors_message);
					$this->update_failed_shipment_packages($order_id, $package_index);
					$this->set_error_notices($errors_message);
					if ($this->debug) {
						echo 'Error: <b>' . $errors_message . '</b><br>';
					}
					return $shipment_order_id;
				}
			}
		}


		return $shipment_order_id;
	}
	private function create_shipment_for_package( $order, $line_items, $service_base_url, $package_index) {

		$order_id              = $this->wf_get_order_id($order);
		$service_shipments_url = $service_base_url . 'print-label';
		$shipment_id           = array();

		$info = array(
			'carrier' => array (
			  'name' => 'AUSPOST_MYPOST',
			),
			'additional_options' => array (
			  'auspost_mypost' => array (
				'step_name' => 'create_shipments',
			  ),
			),
			'shipments' =>$line_items ,
		);

		$this->debug(__('<b>Australia Post debug mode is on - to hide these messages, turn debug mode off in the <a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=wf_australia_mypost&subtab=general') . '">' . __('settings', 'wf-shipping-auspost') . '</a>.</b><br>', 'wf-shipping-auspost'));
		$this->debug('MyPost Business Request <br> <pre>');
		$this->debug(print_r(json_encode($info, JSON_PRETTY_PRINT), true));
		$this->debug('</pre>');

		$rqs_headers = $this->buildHttpHeaders();
		$res         = wp_remote_post($service_shipments_url, array(
			'method' => 'POST',
			'httpversion' => '1.1',
			'timeout'     => 70,
			'headers' => $rqs_headers,
			'body' => json_encode($info)
		));

		if (is_wp_error($res)) {
			$error_string = $res->get_error_message();
			update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $error_string);
			$this->update_failed_shipment_packages($order_id, range(0 , $package_index - 1));
			$this->set_error_notices($error_string);
			if ($this->debug) {
				echo 'Error: <b>' . $error_string . '</b><br>';
			}
			return $shipment_id;
		}
		$response_array = isset($res['body']) ? json_decode($res['body']) : array();
		$this->debug(' MyPost Business Response: <br><pre>');
		$this->debug(print_r(json_encode($response_array, JSON_PRETTY_PRINT), true));
		$this->debug('</pre>');
		if ( !empty($response_array) && 200 == $res['response']['code']) {

			if (!empty($response_array->errors)) {
				$this->set_error_notices($response_array->errors[0]->message);
				update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $response_array->errors[0]->message);
				
				if ($this->debug) {
					echo 'Error: <b>' . $response_array->errors[0]->message . '</b><br>';
				}
				$this->update_failed_shipment_packages($order_id, range(0 , $package_index - 1));
				
				return $shipment_id;
			}
	
			if (!empty($response_array)) {

				if (isset($response_array->items[0]->errors)) {
					$this->set_error_notices($response_array->items[0]->errors[0]->message);
					update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $response_array->errors[0]->message);
					return $shipment_id;
				}
	
				$shipment_date  = '';
				$tracking_id_cs = '';
				//shipments array
				foreach ($response_array as $key => $shipments) {
					$shipment_id   = $shipments->shipment_id;
					$shipment_date = substr($shipments->shipment_creation_date, 0, 10);
					
					$items = $shipments->items;
					foreach ($items as $item) {
						$tracking_id_cs  .= $item->tracking_id;
						$tracking_id_cs  .= ',';
						$shipping_service = $item->product_id;
					}

					if (!class_exists('WF_Tracking_Admin_AusPost')) {
						include plugins_url(basename(plugin_dir_path(__FILE__)) . '/australia_post/includes/class-wf-tracking-admin.php', basename(__FILE__));
					}
		
					try {
						$admin_notice = WfTrackingUtil::update_tracking_data($order_id, $tracking_id_cs, 'australia-post', WF_Tracking_Admin_AusPost::SHIPMENT_SOURCE_KEY, WF_Tracking_Admin_AusPost::SHIPMENT_RESULT_KEY, $shipment_date);
					} catch (Exception $e) {

						// Do nothing.
					}
		
					$order_shipment_ids = get_post_meta($order_id, 'wf_woo_australiamypost_shipmentId', true);
		
					if (empty($order_shipment_ids)) {
						$order_shipment_ids   = array();
						$order_shipment_ids[] = $shipment_id;
					} else {
						$order_shipment_ids[] = $shipment_id;
					}

					update_post_meta($order_id, 'wf_ausmypost_tracking_ids', $tracking_id_cs);
					update_post_meta($order_id, 'elex_ausmypost_shipping_service_' . $shipment_id, $shipping_service);
					update_post_meta($order_id, 'wf_woo_australiamypost_shipmentId', $order_shipment_ids);
					
					if (!empty($shipment_id)) {
						do_action('elex_after_creating_shipment', $order_id );
					} 
	
				}

			}
		
		} else {
			if (!empty($response_array)) {
				if ( isset($response_array->errors) ) {
					$errors_message = $response_array->errors[0]->message;
					update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $errors_message);
					$this->update_failed_shipment_packages($order_id, range(0 , $package_index - 1));
					$this->set_error_notices($errors_message);
					if ($this->debug) {
						echo 'Error: <b>' . $errors_message . '</b><br>';
					}
					return $shipment_id;
				} else {
					$errors_message = $response_array->message;
					update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $errors_message);
					$this->update_failed_shipment_packages($order_id, range(0 , $package_index - 1));
					$this->set_error_notices($errors_message);
					if ($this->debug) {
						echo 'Error: <b>' . $errors_message . '</b><br>';
					}
					return $shipment_id;
				}
			}
		}

		return $shipment_id;
		
	}

	private function generate_label_package( $order, $shipment_ids, $service_base_url ) {
		$service_label_url                  = $service_base_url . 'print-label';
		$shipping_label_layout_parcel_post  = '';
		$shipping_label_layout_express_post = '';
		$order_id                           = $this->wf_get_order_id($order);

		if (isset($this->settings['label_layout_type_parcel_post'])) {
			$shipping_label_layout_parcel_post = $this->settings['label_layout_type_parcel_post'];
		}

		if (isset($this->settings['label_layout_type_express_post'])) {
			$shipping_label_layout_express_post = $this->settings['label_layout_type_express_post'];
		}
		/* Providing A4-1pp as default, if user has not chosen any label layout type for the parcel services */
		if (empty($shipping_label_layout_parcel_post)) {
			$shipping_label_layout_parcel_post = 'A4-1pp';
		}

		/* Providing A4-1pp as default, if user has not chosen any label layout type for the express services */
		if (empty($shipping_label_layout_express_post)) {
			$shipping_label_layout_express_post = 'A4-1pp';
		}

		$label_req = array (
			'carrier' => array (
			  'name' => 'AUSPOST_MYPOST',
			),
			'additional_options' => array (
			  'auspost_mypost' => array (
				'step_name' => 'create_labels',
			  ),
			),
			'label' => array (
			  'include_australiapost_branding' => $this->branded,
			  'layouts' => array (
				'express_post' => $shipping_label_layout_express_post,
				'parcel_post' => $shipping_label_layout_parcel_post,
			  ),
			  'shipment_ids' => $shipment_ids,
			),
		);

		$this->debug('MyPost Business Label Request <br> <pre>');
		$this->debug(print_r(json_encode($label_req, JSON_PRETTY_PRINT), true));
		$this->debug('</pre>');
		$label_rqs_headers = $this->buildHttpHeaders();
		$response          = wp_remote_post($service_label_url, array(
			'method' => 'POST',
			'httpversion' => '1.1',
			'timeout' => 70,
			'headers' => $label_rqs_headers,
			'body' => json_encode($label_req)
		));
			
		if (is_wp_error($response)) {
			$error_string = $response->get_error_message();
			$this->set_error_notices($error_string);
			return;
		}

		$response_array = isset($response['body']) ? json_decode($response['body'], true) : array();
		$this->debug(' MyPost Business label Response: <br><pre>');
		$this->debug(print_r(json_encode($response_array, JSON_PRETTY_PRINT), true));
		$this->debug('</pre>');
		
		if ( !empty($response_array) && 200 == $response['response']['code']) {
			$label_request_id = isset($response_array['request_id']) ? $response_array['request_id'] : '';
			$label_url 		  = isset($response_array['url']) ? $response_array['url'] : '';
		
			if (isset($label_request_id)) {
				update_post_meta($order_id, 'wf_woo_australiamypost_labelId', $label_request_id);
			}
			if (isset($label_url)) {
				update_post_meta($order_id, 'wf_woo_australiamypost_labelURI', $label_url);
				update_post_meta($order_id, 'wf_woo_australiamypost_labelId_generation_date', current_time('Y-m-d', 0)); // current_time($type, $gmt = 0) returns time for selected time zone
			}
			if (isset($label_request_id) && isset($label_url)) {
				
				$label_uri = $label_url;
				if (isset($this->settings['save_labels']) && $this->settings['save_labels'] == 'yes') {
					$combined_shipment_ids = join('~', $shipment_ids);
					$this->elex_auspost_save_shipping_labels($order_id, $combined_shipment_ids, $label_uri);
				}
			}
		
		} else {

			if (!empty($response_array)) {
				if ( isset($response_array->errors) ) {
					$errors_message = $response_array->errors[0]->message;
					update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $errors_message);
					$this->update_failed_shipment_packages($order_id, $package_index );
					$this->set_error_notices($errors_message);
					if ($this->debug) {
						echo 'Error: <b>' . $errors_message . '</b><br>';
					}
					return $shipment_order_id;
				} else {
					$errors_message = $response_array->message;
					update_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', $errors_message);
					$this->update_failed_shipment_packages($order_id, $package_index);
					$this->set_error_notices($errors_message);
					if ($this->debug) {
						echo 'Error: <b>' . $errors_message . '</b><br>';
					}
					return $shipment_order_id;
				}
			}
		}

		 return $label_request_id;
	}

	public function elex_auspost_get_label_content( $URL) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $URL);
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
	}

	private function elex_auspost_save_shipping_labels( $order_id, $shipment_id, $label_uri) {
		
		$pdf_decoded =  wp_remote_get($label_uri);
		if (is_wp_error($pdf_decoded)) {
			$error_string = $pdf_decoded->get_error_message();
			$this->debug('Australia MyPost Business Save Label <br><pre>');
			$this->debug($error_string . '<br>', 'error');
		} else {

			$order_number = $this->custom_order_number($order_id);
			$file_name    = 'Order_' . $order_number . '_' . $shipment_id . '_label.pdf';
			$path         = ELEX_AUSPOST_LABELS . $file_name;
			$pdf          = fopen($path, 'w');
			fwrite($pdf, $pdf_decoded['body']);
			fclose($pdf);
			update_post_meta($order_id, 'stored_label_uri_ausmypost_elex_' . $shipment_id, content_url('/ELEX_AusPost_Labels/' . 'Order_' . $order_number . '_' . $shipment_id . '_label.pdf'));
		}
	}

	//To generate shipment labels by using auto label generate addon.
	public function wf_auspost_auto_label_generate_addon( $order_id = '') {
		if ($order_id) {
			$this->is_request_bulk_shipment = true;
			$this->debug                    = false;
			$count_requests                 = 0;
			update_option('auto_generate_label_on_auspost_elex', true);
			update_option('create_bulk_orders_shipment_auspost', true);
			$order = $this->wf_load_order($order_id);
			$this->wf_create_shipment($order);

			//Code generate labels after the shipments have been generated.
			$order_shipments_label_uris = get_post_meta($order_id, 'elex_ausmypost_label_uris', true);
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
							update_post_meta($order_id, 'wf_woo_australiamypost_labelURI' . $shipment_id, $label_uri);
						}
					}
				}
			}
			delete_option('auto_generate_label_on_auspost_elex');
		}
	}

	public function wf_auspost_bulk_order_actions() {
		$wp_list_table = _get_list_table('WP_Posts_List_Table');
		$action        = $wp_list_table->current_action();
		$sendback      = '';

		if ($action == 'create_auspost_shipment') {
			//forcefully turn off debug mode, otherwise it will die and cause to break the loop.
			$this->debug     = false;
			$label_exist_for = '';
			$failed          = array();
			if (isset($_REQUEST['post']) && !empty($_REQUEST['post'])) {
				foreach ($_REQUEST['post'] as $post_id) {
					$count_requests = 0;
					$order          = $this->wf_load_order($post_id);
					if (!$order) {
						return;
					}

					$order_id        = $this->wf_get_order_id($order);
					$shipmentIds     = get_post_meta($order_id, 'wf_woo_australiamypost_shipmentId', true);
					$failed_packages = get_post_meta($order_id, 'failed_create_shipment_packages_ausmypost_elex', true);
					if (empty($shipmentIds)) {
						$shipmentIds = get_post_meta($order_id, 'elex_auspost_startrack_shipment_ids', true);
					}

					if (!empty($shipmentIds) && empty($failed_packages)) {
						$label_exist_for .= $order_id . ', ';
						delete_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage');
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
									update_post_meta($order_id, 'wf_woo_australiamypost_labelURI' . $shipment_id, $label_uri);
								}
							}
						}
					}
				}

				delete_option('create_bulk_orders_shipment_auspost');
				$this->is_request_bulk_shipment = false;

				// Checking is default shipment service activated
				if (get_option('default_ausmypost_shipment_service_selected') == 'yes') {
					$sendback = add_query_arg(array(
						'bulk_label_auspost' => 1,
						'ids' => join(',', $_REQUEST['post']),
						'already_exist' => rtrim($label_exist_for, ', '),
						'failed' => implode(', ', $failed)
					), admin_url('edit.php?post_type=shop_order'));
				} else {
					// Obtaining orders' ids which do not have default shipping services
					$orders_ids_with_no_default_shipment_service_auspost = get_option('orders_with_no_default_shipment_service_ausmypost');
					$orders_ids_with_no_default_shipment_service_auspost = rtrim($orders_ids_with_no_default_shipment_service_auspost, ',');
					delete_option('orders_with_no_default_shipment_service_ausmypost');
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

	function elex_aus_post_generate_label( $order_id, $shipment_id) {
		//Code generate labels after the shipments have been generated.
		$service_base_url = 'https://' . self::API_HOST . self::API_BASE_URL;

		$response_array = '';

		if ($this->contracted_api_mode == 'live') {
			$service_base_url = str_replace('test/', '', $service_base_url);
		}

		$label_uri = get_post_meta($order_id, 'wf_woo_australiamypost_labelURI' . $shipment_id, true);

		if ($label_uri == '') {

			$label_request_id = get_post_meta($order_id, 'wf_woo_australiamypost_labelId' . $shipment_id, true);
			if ($label_request_id != '') {
				$service_label_url = $service_base_url . 'labels/';
				$label_get_url     = $service_label_url . $label_request_id;

				$rqs_headers = array();


				$rqs_headers = $this->buildHttpHeaders();
				
				
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

	function wf_auspost_bulk_label_admin_notices() {
		global $post_type, $pagenow;

		if (!isset($_REQUEST['ids'])) {
			return;
		}

		if ($pagenow == 'edit.php' && $post_type == 'shop_order' && isset($_REQUEST['bulk_label_auspost'])) {
			if (isset($_REQUEST['ids']) && !empty($_REQUEST['ids'])) {
				$order_ids = explode(',', $_REQUEST['ids']);
			}

			$failed_ids_str                     = '';
			$success_ids_str                    = '';
			$already_exist_arr                  = array();
			$orders_error_string                = '';
			$already_exist_custom_number_string = '';
			$failed_custom_number_str           = '';
			$success_custom_number_str          = '';
			if (isset($_REQUEST['already_exist']) && !empty($_REQUEST['already_exist'])) {
				$already_exist_arr = explode(',', $_REQUEST['already_exist']);
				foreach ($already_exist_arr as $item) {
					$already_exist_custom_number_string .= $this->custom_order_number($item) . ', ';
				}
				$already_exist_custom_number_string = rtrim($already_exist_custom_number_string, ', ');
			}

			if (!empty($order_ids)) {
				foreach ($order_ids as $key => $id) {
					$shipmentIds          = get_post_meta($id, 'wf_woo_australiamypost_shipmentId', true);
					$shipment_err_auspost = get_post_meta($id, 'wf_woo_australiamypost_shipmentErrorMessage', true);
					if (empty($shipmentIds) || !empty($shipment_err_auspost)) {
						$failed_ids_str           .= $id . ', ';
						$failed_custom_number_str .= $this->custom_order_number($id) . ', ';
						$orders_error_string      .= '<b>Order no. ' . $id . ' Error:</b> ' . $shipment_err_auspost . '<br>';
					} elseif (!in_array($id, $already_exist_arr)) {
						$success_ids_str           .= $id . ', ';
						$success_custom_number_str .= $this->custom_order_number($id) . ', ';
					}
				}
			}

			$failed_ids_str            = rtrim($failed_ids_str, ', ');
			$success_ids_str           = rtrim($success_ids_str, ', ');
			$failed_custom_number_str  = rtrim($failed_custom_number_str, ', ');
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
				$failed_shipment_service_custom_number_str  = '';
				$default_shipment_service_custom_number_arr = explode(',', $_REQUEST['failed']);
				foreach ($default_shipment_service_custom_number_arr as $id) {
					$failed_shipment_service_custom_number_str .= $this->custom_order_number($id) . ', ';
				}
				$failed_shipment_service_custom_number_str = rtrim($failed_shipment_service_custom_number_str, ', ');

				echo '<div class="notice notice-error is-dismissible"><p>' . __('Labels could not be generated for the following order IDs: ' . $failed_shipment_service_custom_number_str, 'wf-shipping-auspost') . '</p></div>';
			} elseif ($success_custom_number_str != '') {
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
				echo '<div class="error is-dismissible"><p>' . __('Create shipment is failed for following order(s) ' . $failed_custom_number_str . '<br>' . $orders_error_string, 'wf-shipping-auspost') . '</p></div>';
			}
		}
	}

	public function elex_auspost_startrack_bulk_order_actions() {
		$wp_list_table = _get_list_table('WP_Posts_List_Table');
		$action        = $wp_list_table->current_action();
		$sendback      = '';

		if ($action == 'create_auspost_startrack_shipment') {
			//forcefully turn off debug mode, otherwise it will die and cause to break the loop.
			$this->debug     = false;
			$label_exist_for = '';
			$failed          = array();
			if (isset($_REQUEST['post']) && !empty($_REQUEST['post'])) {
				foreach ($_REQUEST['post'] as $post_id) {
					$count_requests = 0;
					$order          = $this->wf_load_order($post_id);
					if (!$order) {
						return;
					}

					$order_id        = $this->wf_get_order_id($order);
					$shipmentIds     = get_post_meta($order_id, 'wf_woo_australiamypost_shipmentId', true);
					$failed_packages = get_post_meta($order_id, 'failed_create_shipment_packages_ausmypost_elex', true);
					if (empty($shipmentIds)) {
						$shipmentIds = get_post_meta($order_id, 'elex_auspost_startrack_shipment_ids', true);
					}

					if (!empty($shipmentIds) && empty($failed_packages)) {
						$label_exist_for .= $order_id . ', ';
						delete_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage');
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
								update_post_meta($order_id, 'wf_woo_australiamypost_labelURI' . $shipment_label_request_id, $label_uri);
							}
						}
					}
				}

				update_option('create_bulk_orders_shipment_auspost_startrack', false);
				$this->is_request_bulk_shipment = false;

				// Checking is default shipment service activated
				if (get_option('default_ausmypost_shipment_service_selected') == 'yes') {
					$sendback = add_query_arg(array(
						'bulk_label_startrack' => 1,
						'ids' => join(',', $_REQUEST['post']),
						'already_exist' => rtrim($label_exist_for, ', '),
						'failed' => implode(', ', $failed)
					), admin_url('edit.php?post_type=shop_order'));
				} else {
					// Obtaining orders' ids which do not have default shipping services
					$orders_ids_with_no_default_shipment_service_auspost = get_option('orders_with_no_default_shipment_service_ausmypost');
					$orders_ids_with_no_default_shipment_service_auspost = rtrim($orders_ids_with_no_default_shipment_service_auspost, ',');
					delete_option('orders_with_no_default_shipment_service_ausmypost');
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

	function elex_auspost_startrack_bulk_label_admin_notices() {
		global $post_type, $pagenow;

		if (!isset($_REQUEST['ids'])) {
			return;
		}

		if ($pagenow == 'edit.php' && $post_type == 'shop_order' && isset($_REQUEST['bulk_label_startrack'])) {
			if (isset($_REQUEST['ids']) && !empty($_REQUEST['ids'])) {
				$order_ids = explode(',', $_REQUEST['ids']);
			}

			$failed_ids_str                     = '';
			$already_exist_custom_number_string = '';
			$failed_custom_number_str           = '';
			$success_custom_number_str          = '';
			$success_ids_str                    = '';
			$already_exist_arr                  = array();
			$orders_error_string                = '';
			if (isset($_REQUEST['already_exist']) && !empty($_REQUEST['already_exist'])) {
				$already_exist_arr = explode(',', $_REQUEST['already_exist']);
				foreach ($already_exist_arr as $item) {
					$already_exist_custom_number_string .= $this->custom_order_number($item) . ', ';
				}
				$already_exist_custom_number_string = rtrim($already_exist_custom_number_string, ', ');
			}

			if (!empty($order_ids)) {
				foreach ($order_ids as $key => $id) {
					$shipmentIds          = get_post_meta($id, 'wf_woo_australiamypost_shipmentId', true);
					$shipment_err_auspost = get_post_meta($id, 'wf_woo_australiamypost_shipmentErrorMessage', true);
					if (empty($shipmentIds) || !empty($shipment_err_auspost)) {
						$failed_ids_str           .= $id . ', ';
						$failed_custom_number_str .= $this->custom_order_number($id) . ', ';
						$orders_error_string      .= '<b>Order no. ' . $id . ' Error:</b> ' . $shipment_err_auspost . '<br>';
					} elseif (!in_array($id, $already_exist_arr)) {
						$success_ids_str           .= $id . ', ';
						$success_custom_number_str .= $this->custom_order_number($id) . ', ';
					}
				}
			}

			$failed_ids_str            = rtrim($failed_ids_str, ', ');
			$success_ids_str           = rtrim($success_ids_str, ', ');
			$failed_custom_number_str  = rtrim($failed_custom_number_str, ', ');
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
				$failed_shipment_service_custom_number_str  = '';
				$default_shipment_service_custom_number_arr = explode(',', $_REQUEST['failed']);
				foreach ($default_shipment_service_custom_number_arr as $id) {
					$failed_shipment_service_custom_number_str .= $this->custom_order_number($id) . ', ';
				}
				$failed_shipment_service_custom_number_str = rtrim($failed_shipment_service_custom_number_str, ', ');

				echo '<div class="notice notice-error is-dismissible"><p>' . __('Labels could not be generated for the following order IDs: ' . $failed_shipment_service_custom_number_str, 'wf-shipping-auspost') . '</p></div>';
			} elseif ($success_custom_number_str != '') {
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
				echo '<div class="error is-dismissible"><p>' . __('Create shipment is failed for following order(s) ' . $failed_custom_number_str . '<br>' . $orders_error_string, 'wf-shipping-auspost') . '</p></div>';
			}
		}
	}

	private function wf_get_request_header( $accept, $contentType) {
		return array(
			'Content-Type' => $contentType,
			'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
			'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
			'account-number' => 1004916305
		);
	}

	private function get_postage_products_type_and_product_ids( $all_eligible_postage_products) {
		$postage_products_type_and_product_ids = array();
		if (is_array($all_eligible_postage_products) && !empty($all_eligible_postage_products)) {
			foreach ($all_eligible_postage_products as $postage_product_eligible) {
				$postage_products_type_and_product_ids[$postage_product_eligible['type']] = $postage_product_eligible['product_id'];
			}
		}
		return $postage_products_type_and_product_ids;
	}

	private function get_postage_product_data( $vendor_user_id = false) {

		if ($vendor_user_id && $this->vedor_api_key_enable) {
			$api_key                  = get_the_author_meta('vendor_elex_australia_post_api_key', $vendor_user_id);
			$api_account_no           = get_the_author_meta('vendor_elex_australia_post_account_number', $vendor_user_id);
			$api_pwd                  = get_the_author_meta('vendor_elex_australia_post_api_password', $vendor_user_id);
			$api_account_no_startrack = get_the_author_meta('vendor_elex_australia_post_startrack_account_number', $vendor_user_id);
			$api_pwd_startrack        = get_the_author_meta('vendor_elex_australia_post_startrack_api_password', $vendor_user_id);
		} else {
			if ($this->startrack_enabled) {
				$api_account_no_startrack = $this->api_account_no_startrack;
				$api_pwd_startrack        = $this->api_pwd_startrack;
				$api_key_starTrack        = $this->api_key_starTrack;
			}
			$api_account_no = isset($this->api_account_no) ? $this->api_account_no : array();
			$api_pwd        = $this->api_pwd;
			$api_key        = false;
		}
		$get_accounts_endpoint_startrack = '';

		if (!class_exists('wf_australia_post_shipping')) {
			include_once 'class-wf-australia-post-shipping.php';
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

		$postage_products                    = !empty($api_account_no) ? $shipping_cart_side->get_services($get_accounts_endpoint, $api_account_no, $api_pwd, $api_key) : array();
		$postage_products_startrack          = array();
		$postage_products_eligible_startrack = array();

		if ($get_accounts_endpoint_startrack != '') {
			$postage_products_startrack          = $shipping_cart_side->get_services($get_accounts_endpoint_startrack, $api_account_no_startrack, $api_pwd_startrack, $api_key_starTrack);
			$postage_products_eligible_startrack = json_decode($postage_products_startrack, true);
			$postage_products_eligible_startrack = isset($postage_products_eligible_startrack['postage_products']) ? $postage_products_eligible_startrack['postage_products'] : array();
		}

		$postage_products_eligible = json_decode($postage_products, true);
		$postage_products_eligible = isset($postage_products_eligible['postage_products']) ? $postage_products_eligible['postage_products'] : array();
		$service_name              = '';

		$postage_products_type_and_product_ids = array();

		if (!empty($postage_products_eligible_startrack)) {
			foreach ($postage_products_eligible_startrack as $startrack_eligible_postage_product_key => $startrack_eligible_postage_product) {
				$postage_products_eligible_startrack[$startrack_eligible_postage_product_key]['service_type'] = 'startrack';
			}
		}

		return array('auspost_eligible_postage_products' => $postage_products_eligible, 'startrack_eligible_postage_products' => $postage_products_eligible_startrack);
	}

	public function wf_add_australia_mypost_metabox() {
		global $post;

		if (!$post) {
			return;
		}

		if (!in_array($post->post_type, array('shop_order'))) {
			return;
		}

		$order = $this->wf_load_order($post->ID);
		if (!$order) {
			return;
		}

		$this->order_id = $this->wf_get_order_id($order);

		add_meta_box('wfaustraliamypost_metabox', __('MyPost Business', 'wf-shipping-auspost'), array($this, 'wf_australia_mypost_metabox_content'), 'shop_order', 'advanced', 'default');
	}

	/**
	 *  function to validate dimensions of flatbox.
	 *
	 * @access public
	 * @param mixed $check
	 * 
	 */

	public function validate_flatbox_dimensions( $product_id, $package) {

		if ( ( $product_id === 'BE1PB4' || $product_id == 'BE9PB4' ) && ( $package['length'] > 44 || $package['width'] > 27.7 || $package['height'] > 16.8 || $package['weight'] > 5 ) ) {

			return false;
		}

		if ( ( $product_id === 'BE1PB3' || $product_id === 'BE9PB3' ) && ( $package['length'] > 39 || $package['width'] > 28 || $package['height'] > 14 || $package['weight'] > 5 ) ) {

			return false;
		}

		if ( ( $product_id === 'BE1PB2' || $product_id === 'BE9PB2' ) && ( $package['length'] > 24 || $package['width'] > 19 || $package['height'] > 12 || $package['weight'] > 5 ) ) {

			return false;
		}

		if ( ( $product_id === 'BE1PB1' || $product_id === 'BE9PB1' ) && ( $package['length'] > 22 || $package['width'] > 16 || $package['height'] > 7 || $package['weight'] > 5 ) ) {

			return false;
		}

		if ( ( $product_id === 'BE9P50' || $product_id === 'BE1P50' ) && ( $package['length'] > 51 || $package['width'] > 44 || $package['weight'] > 5 ) ) {

			return false;
		}
		if ( ( $product_id === 'BE9P30' || $product_id === 'BE1P30' ) && ( $package['length'] > 40.5 || $package['width'] > 31.5 || $package['weight'] > 5 ) ) {

			return false;
		}

		if ( ( $product_id === 'BE9P10' || $product_id === 'BE1P10' ) && ( $package['length'] > 39 || $package['width'] > 27 || $package['weight'] > 5 ) ) {

			return false;
		}

		if ( ( $product_id === 'BE9P05' || $product_id === 'BE1P05' ) && ( $package['length'] > 35.5 || $package['width'] > 22.5 || $package['weight'] > 5 ) ) {

			return false;
		}

		return true;


	}
	/**
	 *  function to get eligible MyPost Business service according to package dimensions and weight.
	 *
	 * @access public
	 * @param mixed $check
	 * 
	 */
	public function get_eligible_mypost_services( $country, $package, $services) {

		$domestic_product_ids_of_exp_parcel = ['B20', 'B21', 'B30', 'B31'];
		$domestic_product_ids_of_flatbox    = ['BE9PB4', 'BE9PB3', 'BE9PB2', 'BE9PB1', 'BE9P50', 'BE9P30', 'BE9P10', 'BE9P05', 'BE1PB4', 'BE1PB3', 'BE1PB2', 'BE1PB1', 'BE1P50', 'BE1P30', 'BE1P10', 'BE1P05'];
		$internation_product_ids            = ['I63', 'I64', 'I65', 'I66', 'I67'];
		
		$eligible_postage_product = array();
		$package_items            = array();
		$from_weight_unit         = '';
		if ($this->weight_unit != 'kg') {
			$from_weight_unit = $this->weight_unit;
		}

		$from_dimension_unit = '';
		if ($this->dimension_unit != 'cm') {
			$from_dimension_unit = $this->dimension_unit;
		}
		$package_items['length'] =	$package['Dimensions']['Length'];
		$package_items['width']  =	$package['Dimensions']['Width'];
		$package_items['height'] =	$package['Dimensions']['Height'];
		$package_items['weight'] = 	$package['Weight']['Value'];
		$package_items           = array(
			'weight' => round(wc_get_weight($package['Weight']['Value'], 'kg', $from_weight_unit), 3),
			'length' => round(wc_get_dimension($package['Dimensions']['Length'], 'cm', $from_dimension_unit), 1),
			'width' => round(wc_get_dimension($package['Dimensions']['Width'], 'cm', $from_dimension_unit), 1),
			'height' => round(wc_get_dimension($package['Dimensions']['Height'], 'cm', $from_dimension_unit), 1)
		);

		if ( 'AU'=== $country ) {
			foreach ($services as $key => $val) {
				if (in_array($key, $domestic_product_ids_of_exp_parcel ) && $val['enabled'] ) {
					if (( 'B20'== $key||'B30'== $key ) && 5 >= $package_items['weight'] && 0 < $package_items['weight'] ) {
						
						$eligible_postage_product[$key] = $val;
					}
					if (( 'B21'== $key||'B31'== $key ) && 22 >= $package_items['weight'] && 5 < $package_items['weight'] ) {
						$eligible_postage_product[$key] = $val;
					}
					
					
				} elseif (in_array($key, $domestic_product_ids_of_flatbox) && $val['enabled']) {
					$check_flatbox_rates = $this->validate_flatbox_dimensions($key, $package_items);
					
					if ($check_flatbox_rates) {
						$eligible_postage_product[$key] = $val;
					}		
				}
			}	

		} else {
			foreach ($services as $key => $val) {
				if ( in_array($key, $internation_product_ids) && $val['enabled'] ) {
					
					if (( 'I63' == $key && 2 >= $package_items['weight'] && 0 < $package_items['weight'] ) || ( 'I64' == $key && 2 >= $package_items['weight'] && 0 < $package_items['weight'] )) {
						$eligible_postage_product[$key] = $val;
						
					}
					if ('I65' == $key && 20 >= $package_items['weight'] && 2 < $package_items['weight'] ) {
						$eligible_postage_product[$key] = $val;
						
					}
					if ('I66' == $key && 20 >= $package_items['weight']  && 0 < $package_items['weight'] ) {
						$eligible_postage_product[$key] = $val;
					
					}
					if ('I67' == $key && 20 >= $package_items['weight']  && 0 < $package_items['weight'] ) {
						$eligible_postage_product[$key] = $val;
						
					}
					
				}
			}	
		}

		return $eligible_postage_product;
	}

	public function wf_australia_mypost_metabox_content() {
		global $post;

		if ($this->boxpacking_error) {
			$this->show_boxpacking_error_notice();
		}

		if (!$post) {
			return;
		}

		$order = $this->wf_load_order($post->ID);
		if (!$order) {
			return;
		}

		$serviceName  = $this->wf_get_shipping_service($order, false);
		$order_id     = $this->wf_get_order_id($order);
		$order_number = $order->get_order_number();
		delete_option('request_to_create_shipment');
		delete_option('create_bulk_orders_shipment_auspost');

		$shipmentIds  = get_post_meta($order_id, 'wf_woo_australiamypost_shipmentId', false);
		$tracking_ids = get_post_meta($order_id, 'wf_ausmypost_tracking_ids', false);
		if (is_array($tracking_ids) && !empty($tracking_ids[0])) {
			$tracking_id_array = explode(',', $tracking_ids[0]);
		}

		$shipment_void_ids        = get_post_meta($order_id, 'wf_woo_australiamypost_shipment_void', true);
		$failed_shipment_packages = get_post_meta($order_id, 'failed_create_shipment_packages_ausmypost_elex', true);
		$shipping_country         = wf_get_order_shipping_country($order);

		$consolidated_failed_shipment_packages = get_post_meta($order_id, 'consolidated_failed_create_shipment_packages_ausmypost_elex', true);
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
				update_post_meta($order_id, 'consolidated_failed_create_shipment_packages_ausmypost_elex', $consolidated_failed_shipment_packages);
				delete_post_meta($order_id, 'failed_create_shipment_packages_ausmypost_elex');
			}
		}

		$manifestLink                = get_post_meta($order_id, 'wf_woo_australiapost_manifestLink', false);
		$manifestArtifactLinkList    = get_post_meta($order_id, 'wf_woo_australiapost_manifestArtifactLink', false);
		$shipmentErrorMessage        = get_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage', true);
		$manifestErrorMessage        = get_post_meta($order_id, 'wf_woo_australiapost_manifestErrorMessage', true);
		$transmitErrorMessage        = get_post_meta($order_id, 'wf_woo_australiapost_transmitErrorMessage', true);
		$shipment_void_error_message = get_post_meta($order_id, 'wf_woo_australiamypost_shipment_void_errormessage', true);

		$display_shipment_tracking_message = get_option('shipment_tracking_message');

		if (!empty($display_shipment_tracking_message)) {
			echo '<div class="notice notice-success is-dismissible">
                <p>' . $display_shipment_tracking_message . '</p>
            </div>';
			delete_option('shipment_tracking_message');
		}

		if (!empty($shipmentErrorMessage)) {
			echo '<div class="error"><p>' . sprintf(__('Shipment Error:%s', 'wf-shipping-auspost'), $shipmentErrorMessage) . '</p></div>';
			delete_post_meta($order_id, 'wf_woo_australiamypost_shipmentErrorMessage');
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
			.create_shipment {
				margin-top: -1% !important;
				margin-bottom: 2% !important;
			}
			.create_shipment_order_mypost{
				margin-top: -1% !important;
				margin-bottom: 2% !important;
			}
		</style>
		<?php
		$postage_products_eligible = ( !empty($this->settings['services']) ) ? $this->settings['services'] : array();
		if (!empty($shipmentIds)) {

			$transmit_url    	 = admin_url('/post.php?wf_australiapost_transmitshipment=' . $order_id);
			$delete_order_url 	 = admin_url('/post.php?wf_australiamypost_delete_shipment=' . $order_id . '&wf_shipment_id=');
			$shipment_pickup_url = admin_url('/post.php?wf_australiamypost_create_shipment_pickup=' . $order_id);
			$create_order_url    = admin_url('/post.php?wf_australiamypost_create_shipment_order=' . $order_id);
			$create_label_url 	 = admin_url('/post.php?wf_australiamypost_create_shipment_label=' . $order_id);
			if (is_array($shipmentIds) && !empty($shipmentIds)) {
				$shipmentIds_array = $shipmentIds;
				$shipmentIds_array = array_shift($shipmentIds_array);
				if (is_array($shipmentIds_array) && !empty($shipmentIds_array)) {
					$shipmentIds = $shipmentIds_array;
					foreach ($shipmentIds as $shipment_id) {
						$shipment_service_for_shipment_id = get_post_meta($order_id, 'elex_ausmypost_shipping_service_' . $shipment_id, true);
						$all_eligible_postage_products    = !empty($this->settings['services']) ? $this->settings['services'] : array();
						$selected_service                 = $all_eligible_postage_products[$shipment_service_for_shipment_id]['name'];
						if (empty($selected_service)) {
							$selected_service = $serviceName;
						}
						echo '<li>Shipping Service: <strong>' . $selected_service . ' (' . $shipment_service_for_shipment_id . ')</strong></li>';
						echo '<li><strong>Shipment #:</strong> ' . $shipment_id;

						if (( is_array($shipment_void_ids) && in_array($shipment_id, $shipment_void_ids) )) {
							echo '<br> This shipment ' . $shipment_id . ' is terminated.';
						}
						$delete_order_url .=  $shipment_id . ',';
						$this->shipment_id = $shipment_id;

						echo '<hr>';
					}
					$austalia_post_pickup_number = get_post_meta($order_id, 'wf_australia_mypost_pickup', array());
					$austalia_post_order_number  = get_post_meta($order_id, 'wf_australia_mypost_order', array());
					$order_shipment_number		 = '';
					$shipment_order_status_check = true;
					if (isset($austalia_post_order_number[0])) {

						echo '<li><strong>Order #:</strong> ' . $austalia_post_order_number[0]->order_id . '</li>';
						$order_shipment_number       = $austalia_post_order_number[0]->order_id;
						$shipment_order_status_check = false;

					} else {
						$shipment_order_status_check = true;
					}
					if (isset($austalia_post_pickup_number[0])) {
						foreach ($austalia_post_pickup_number[0] as $k => $v) {
							echo '<li><strong>Pickup ID #:</strong> ' . $k;
							echo '<br><strong>Pickup Date #:</strong> ' . $v['pickup_date'];
							echo '<br><strong>Pickup Start Time #:</strong> ' . $v['pickup_starttime'];
							echo '<br><strong>Pickup End Time #:</strong> ' . $v['pickup_endtime'] . '</li>';
						}
					}

					if ($shipment_order_status_check) {  
						?>
						<br>
						<a class="button tips delete_shipment" href="<?php echo $delete_order_url; ?>" data-tip="<?php _e('Delete Shipment', 'wf-shipping-auspost'); ?>"><?php _e('Delete Shipment', 'wf-shipping-auspost'); ?></a>
						<a class="button tips create_shipment_order_mypost" href="<?php echo $create_order_url; ?>" data-tip="<?php _e('Please note once the shipment order is placed, the shipment(s) cannot be deleted.', 'wf-shipping-auspost'); ?>"><?php _e('Create Shipment Order', 'wf-shipping-auspost'); ?></a>
						<?php 
					} elseif ( !$shipment_order_status_check ) {
						$shipping_label        = '';
						$combined_shipment_ids = join('~', $shipmentIds);
						if (isset($this->settings['save_labels']) && $this->settings['save_labels'] == 'yes') {
							if (file_exists(ELEX_AUSPOST_LABELS . 'Order_' . $order_number . '_' . $combined_shipment_ids . '_label.pdf')) {
								$shipping_label = content_url('/ELEX_AusPost_Labels/' . 'Order_' . $order_number . '_' . $combined_shipment_ids . '_label.pdf');
							} elseif (file_exists(ELEX_AUSPOST_LABELS . 'Order_' . $order_shipment_number . '_label.pdf')) {
								$shipping_label = content_url('/ELEX_AusPost_Labels/' . 'Order_' . $order_shipment_number . '_label.pdf');
							}
						} else {
							$shipping_label = get_post_meta($order_id, 'wf_woo_australiamypost_labelURI', true);
						}

						if (!empty($shipping_label)) {
							$download_url  = $shipping_label;
							$get_label_url = admin_url('/post.php?post=' . $order_id . '&action=edit&wf_australiamypost_viewlabel=' . $order_id . '&shipment_id=' . $combined_shipment_ids . '&order_number=' . $order_number . '');
							if ($this->settings['dir_download'] == 'yes') { 
								?>
								<a class="button button-primary tips label_buttons wf_australiamypost_viewlabel" target="_self" href="<?php echo $get_label_url; ?>" data-tip="<?php _e('Download Label', 'wf-shipping-auspost'); ?>"><?php _e('Download Label', 'wf-shipping-auspost'); ?></a>
								<?php

							} else {
								?>
								<a class="button button-primary tips label_buttons" target="_blank" href="<?php echo $download_url; ?>" data-tip="<?php _e('Print Label', 'wf-shipping-auspost'); ?>"><?php _e('Print Label', 'wf-shipping-auspost'); ?></a>
								<?php
							}
						} else {
							?>
							<br>
							<a class="button tips create_shipment_labels_mypost" href="<?php echo $create_label_url; ?>" data-tip="<?php _e('Generate Label.', 'wf-shipping-auspost'); ?>"><?php _e('Create Label', 'wf-shipping-auspost'); ?></a>
							<?php
						}


					}
				} else {
					foreach ($shipmentIds as $shipment_id) {
						echo '<li><strong>Shipment #:</strong> ' . $shipment_id;

						if (( is_array($shipment_void_ids) && in_array($shipment_id, $shipment_void_ids) )) {
							echo '<br> This shipment ' . $shipment_id . ' is terminated.';
						}
						$delete_order_url .= $shipment_id . ',';
						$this->shipment_id = $shipment_id;

						$austalia_post_pickup_number = get_post_meta($order_id, 'wf_australia_mypost_pickup', array());
						$austalia_post_order_number  = get_post_meta($order_id, 'wf_australia_mypost_order' . $shipment_id, array());
						$shipment_order_status_check = true;
						$order_shipment_number		 = '';
						if (isset($austalia_post_order_number[0])) {
							echo '<br><strong>Order #:</strong> ' . $austalia_post_order_number[0]->order_id;
							$order_shipment_number       = $austalia_post_order_number[0]->order_id;
							$shipment_order_status_check = false;
						} else {
							$shipment_order_status_check = true;
						}

						if (isset($austalia_post_pickup_number[0])) {
							foreach ($austalia_post_pickup_number[0] as $k => $v) {
								echo '<li><strong>Pickup ID #:</strong> ' . $k;
								echo '<br><strong>Pickup Date #:</strong> ' . $v['pickup_date'];
								echo '<br><strong>Pickup Start Time #:</strong> ' . $v['pickup_starttime'];
								echo '<br><strong>Pickup End Time #:</strong> ' . $v['pickup_endtime'] . '</li>';
							}
						}

						if ($shipment_order_status_check) {
							?>
							<a class="button tips" href="<?php echo $delete_order_url; ?>" data-tip="<?php _e('Delete Shipment', 'wf-shipping-auspost'); ?>"><?php _e('Delete Shipment', 'wf-shipping-auspost'); ?></a>
						
							<a class="button tips create_shipment_order_mypost" href="<?php echo $create_order_url; ?>" data-tip="<?php _e('Please note once the shipment order is placed, the shipment(s) cannot be deleted.', 'wf-shipping-auspost'); ?>"><?php _e('Create Shipment Order', 'wf-shipping-auspost'); ?></a>
							<?php 
						} elseif ( !$shipment_order_status_check ) {
							$shipping_label        = '';
							$combined_shipment_ids = join('~', $shipmentIds);
							if (isset($this->settings['save_labels']) && $this->settings['save_labels'] == 'yes') {
								if (file_exists(ELEX_AUSPOST_LABELS . 'Order_' . $order_number . '_' . $combined_shipment_ids . '_label.pdf')) {
									$shipping_label = content_url('/ELEX_AusPost_Labels/' . 'Order_' . $order_number . '_' . $combined_shipment_ids . '_label.pdf');
								} elseif (file_exists(ELEX_AUSPOST_LABELS . 'Order_' . $order_shipment_number . '_label.pdf')) {
									$shipping_label = content_url('/ELEX_AusPost_Labels/' . 'Order_' . $order_shipment_number . '_label.pdf');
								}
							} else {
								$shipping_label = get_post_meta($order_id, 'wf_woo_australiamypost_labelURI', true);
							}

							if (!empty($shipping_label)) {
								$download_url  = $shipping_label;
								$get_label_url = admin_url('/post.php?post=' . $order_id . '&action=edit&wf_australiamypost_viewlabel=' . $order_id . '&shipment_id=' . $combined_shipment_ids . '&order_number=' . $order_number . '');
								if ($this->settings['dir_download'] == 'yes') { 
									?>
									<a class="button button-primary tips label_buttons wf_australiamypost_viewlabel" target="_self" href="<?php echo $get_label_url; ?>" data-tip="<?php _e('Download Label', 'wf-shipping-auspost'); ?>"><?php _e('Download Label', 'wf-shipping-auspost'); ?></a>
									<?php
	
								} else {
									?>
									<a class="button button-primary tips label_buttons" target="_blank" href="<?php echo $download_url; ?>" data-tip="<?php _e('Print Label', 'wf-shipping-auspost'); ?>"><?php _e('Print Label', 'wf-shipping-auspost'); ?></a>
									<?php
								}
							} else {
								?>
								<br>
								<a class="button tips create_shipment_labels_mypost" href="<?php echo $create_label_url; ?>" data-tip="<?php _e('Generate Label.', 'wf-shipping-auspost'); ?>"><?php _e('Create Label', 'wf-shipping-auspost'); ?></a>
								<?php
							}
						}
					}
				} 
				?>
			<?php
			}
		}

		$failed_shipment_packages = array();

		if (empty($shipmentIds) || !empty($consolidated_failed_shipment_packages)) {
			$generate_url          = admin_url('/post.php?wf_australiamypost_createshipment=' . $order_id);
			$generate_packages_url = admin_url('/post.php?elex_mypost_generate_packages=' . $order_id);

			$shipping_data             = $order->get_shipping_methods();
			$shipping_data             = array_shift($shipping_data);
			$shipment_service_selected = '';

			if (!empty($shipping_data)) {
				$shipping_method_data      = $shipping_data->get_data();
				$shipment_service_selected = $shipping_method_data['name'];
			}
			$service_for_creating_shipment = '';

			$default_domestic_shipment_service_auspost      = ( isset($this->settings['ausmypost_default_domestic_shipment_service']) && ( $this->settings['ausmypost_default_domestic_shipment_service'] != 'none' ) ) ? $this->settings['ausmypost_default_domestic_shipment_service'] : 'none';
			$default_international_shipment_service_auspost = ( isset($this->settings['ausmypost_default_international_shipment_service']) && ( $this->settings['ausmypost_default_international_shipment_service'] != 'none' ) ) ? $this->settings['ausmypost_default_international_shipment_service'] : 'none';
			$order_items                                    = $order->get_items();

			$shipment_requests = array();

			$from_weight_unit = '';
			if ($this->weight_unit != 'kg') {
				$from_weight_unit = $this->weight_unit;
			}

			$from_dimension_unit = '';
			if ($this->dimension_unit != 'cm') {
				$from_dimension_unit = $this->dimension_unit;
			}

			$remove_package_status = get_option('removed_package_status_ausmypost_elex', false);

			if (!empty($consolidated_failed_shipment_packages) || $remove_package_status) {
				$contains_failed_packages = true;
				$this->elex_mypost_generate_packages($order_id, $contains_failed_packages);
			}
			$shipment_requests = get_post_meta($order_id, 'shipment_packages_ausmypost_elex', true);
			
			if ($remove_package_status) {
				delete_option('removed_package_status_ausmypost_elex');
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

				.shipment_table_ausmypost_elex table {
					width: 120%;
				}
			</style>
			<?php if (!empty($shipment_requests)) { ?>
				<div class="elex-auspost-refresh-services" style="margin-bottom: 20px;">
					<a class="auspost_generate_refresh_service button" id="ausmypost_generate_refresh_service_button" href="#" data-tip="<?php _e('Show Available Services/Rates', 'wf_shipping_auspost'); ?>" style="float: right;overflow: hidden;">
						<span class="dashicons dashicons-update help_tip" data-tip="<?php _e('Show Available Services/Rates', 'wf_shipping_auspost'); ?> " style="padding-top: 2px;"></span>
					</a>
					<div class="elex_ausmypost_available_services" id="elex_ausmypost_available_services" style="display: block;">
					</div>
				</div>
				<div id="shipment_table_div_auspost_elex" style="border:1px solid #ddd; overflow-x: auto;">
					<table class="shipment_table_ausmypost_elex">
						<thead align="left">
							<tr>
								<th>Item </th>
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
								<?php } ?>
							</tr>
						</thead>
						<tbody class="table_body_packages_ausmypost_elex">
							<?php
							$request_package_count = 0;
							
							foreach ($shipment_requests as $shipment_request) {
								$products_id_packed = '';
								if ($this->weight_unit != 'kg') {
									$shipment_request['Weight']['Value'] = wc_get_weight($shipment_request['Weight']['Value'], 'kg');
								}

								if ($this->dimension_unit != 'cm') {
									$shipment_request['Dimensions']['Length'] = wc_get_dimension($shipment_request['Dimensions']['Length'], 'cm');
									$shipment_request['Dimensions']['Width']  = wc_get_dimension($shipment_request['Dimensions']['Width'], 'cm');
									$shipment_request['Dimensions']['Height'] = wc_get_dimension($shipment_request['Dimensions']['Height'], 'cm');
								}
								$postage_products              = !empty($this->settings['services']) ? $this->settings['services'] : array();
								$all_eligible_postage_products =$this->get_eligible_mypost_services($shipping_country, $shipment_request, $postage_products) ;
								update_option('all_ausmypost_postage_products_auspost_elex', $all_eligible_postage_products);

								/* Obtaining service selected for the shipment */
								$serviceName = $this->wf_get_shipping_service($order, false);

								$this->label_shipping_method_id = $this->get_selected_shipping_service_id($all_eligible_postage_products, $serviceName, $order);
								
								update_post_meta($order_id, 'wf_woo_australiamypost_service_code', $this->label_shipping_method_id);
								
								if (isset($shipment_request['packed_products']) && !empty($shipment_request['packed_products'])) {
									$package_packed_products = $shipment_request['packed_products'];
									$products_packed         = array();
									if (!empty($package_packed_products) && ( count($package_packed_products) > 1 ) && !isset($package_packed_products['id'])) {
										foreach ($package_packed_products as $key => $package_packed_product) {
											if (isset($package_packed_product['id'])) {
												array_push($products_packed, $package_packed_product['id']);
											} elseif ('id' ==  $key) {
												array_push($products_packed, $package_packed_product);
											} elseif ('variation_id' == $key ) {
												array_push($products_packed, $package_packed_product);
											}
										}
									} else {
										if (isset($package_packed_products['id'])) {
											array_push($products_packed, $package_packed_products['id']);
										}
										if (isset($package_packed_products['variation_id'])) {
											array_push($products_packed, $package_packed_products['variation_id']);
										}
									}
									if (!empty($products_packed)) {
										$products_id_packed = implode(',', $products_packed);
									}
								}

								$shipment_contents = $shipment_request['Item_contents'];
								$item_info         = 'Package Contents ';
								$item_info        .= '<table style"top:-50px">';
								if (empty($shipment_contents)) {
									$item_info .= '<tr>';
									$item_info .= '<td>No Details</td>';
									$item_info .= '</tr>';
								} else {
									foreach ($shipment_contents as $shipment_content) {
										if (is_array($shipment_content) && !empty($shipment_content)) {
											$item_info .= '<tr>';
											$item_info .= '<td>' . $shipment_content['description'] . '<td> <td>Quantity - ' . $shipment_content['quantity'] . '</td> <td>value - ' . $shipment_content['value'] . '</td> <td>HSF - ' . $shipment_content['tariff_code'] . '</td> <td>Origin Country - ' . $shipment_content['country_of_origin'] . '</td>';
											$item_info .= '</tr>';
										}
									}
								}
								$item_info .= '</table>';
								?>
								<tr>
									<td align="left" style="padding:0.5%; display: none;"><input type="text" id="packed_product_ids_ausmypost_elex" size="2" value="<?php echo $products_id_packed; ?>" />&nbsp;</td>
									<td align="left" size="2" class="infotip shipment_contents" style="padding: 1%; width: 20% !important"><span class="infotiptext"><?php _e($item_info, 'wf_shipping_auspost'); ?></span><strong id="shipmentmypostPackageTitle"><?php echo $shipment_request['Name']; ?>&nbsp;</strong></td>
									<td align="left" class="shipment_description_row_columns"><input type="hidden" id="australia_mypost_package_manual_cubic_volume" name='elex_auspost_manual_cubic_volume[]' size="2" value="<?php echo ( ( isset($shipment_request['cubic_volume']) && $shipment_request['cubic_volume'] > 0 ) ? $shipment_request['cubic_volume'] : 0 ); ?>" /><input type="text" id="australia_mypost_package_manual_weight" name='elex_auspost_manual_weight[]' size="2" style="width: 100% !important" value="<?php echo round($shipment_request['Weight']['Value'], 2); ?>" />&nbsp;<?php echo 'kg'; ?></td>
									<td align="left" class="shipment_description_row_columns"><input type="text" id="australia_mypost_package_manual_length" name='elex_auspost_manual_length[]' size="2" class="shipment_row_columns_input_style" style="width: 100% !important" value="<?php echo round($shipment_request['Dimensions']['Length'], 2); ?>" />&nbsp;<?php echo 'cm'; ?></td>
									<td align="left" class="shipment_description_row_columns"><input type="text" id="australia_mypost_package_manual_width" name='elex_auspost_manual_width[]' size="2" class="shipment_row_columns_input_style" style="width: 100% !important" value="<?php echo round($shipment_request['Dimensions']['Width'], 2); ?>" />&nbsp;<?php echo 'cm'; ?></td>
									<td align="left" class="shipment_description_row_columns"><input type="text" id="australia_mypost_package_manual_height" name='elex_auspost_manual_height[]' size="2" class="shipment_row_columns_input_style" style="width: 100% !important" value="<?php echo round($shipment_request['Dimensions']['Height'], 2); ?>" />&nbsp;<?php echo 'cm'; ?></td>
									<td align="left" class="shipment_description_row_columns">
										<select class="select elex-auspost-package-service" id="australia_mypost_package_manual_service">';
											<?php 
											if (empty($all_eligible_postage_products)) {
												foreach ($this->settings['services'] as $service_code => $service) {
													if (ctype_alnum($service_code)) {
														echo '<option value="' . $service_code . '" ' . selected($selected_service, $service_code) . ' >' . $service['name'] . '</option>';
													}
												}
											} else {

												if (is_array($all_eligible_postage_products) && !empty($all_eligible_postage_products)) {
													echo "<option class='service_label' disabled>MyPost Business</option>";
													foreach ($all_eligible_postage_products as $key => $product) {
														
														$product_id = $key;
														if (ctype_alnum($product_id)) {
															if (isset($product['enabled'])) {
																if ($serviceName == $product['name'] || $this->label_shipping_method_id == $product_id ) {
																	echo "<option value='" . $product_id . "' selected>" . $product['name'] . '</option>';
																} else {
																	echo "<option value='" . $product_id . "'>" . $product['name'] . '</option>';
																}
															}
															
														}
													}
												}
											}
											echo '</select>' 
											?>
									</td>
									<?php

									if ($shipping_country != 'AU') {
										?>
										<td align="left" style="padding:0.5%" class="classification_description_column">
											<select class="australia_mypost_item_category">
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
									<td style="padding:0.5%;"><span class="dashicons dashicons-dismiss" style="cursor: pointer; padding-right: 5% !important" id="remove_package_ausmypost_elex"></span></td>
								</tr>
							<?php

								$request_package_count++;
							}
							?>
						</tbody>
					</table>
					<a class="button tips onclickdisable add_extra_mypostpackages" style="margin: 1%" data-tip="<?php _e('Add extra packages', 'wf-shipping-auspost'); ?>"><?php _e('Add Extra Packages', 'wf-shipping-auspost'); ?></a>
				</div>
				<li>
					<input type="checkbox" id="ausmypost_logo_check" value='yes' <?php echo ( ( $this->branded ) ? 'checked' : '' ); ?>><?php _e('Show Australia Post Logo on Shipment Label', 'wf-shipping-auspost'); ?>

				</li>

				<li>
					<a class="button button-primary tips onclickdisable create_shipment_mypost" href="<?php echo $generate_url; ?>" data-tip="<?php _e('Create Shipment', 'wf-shipping-auspost'); ?>"><?php _e('Create Shipment', 'wf-shipping-auspost'); ?></a>
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

				jQuery('.add_extra_mypostpackages').prop("disabled", false);
				jQuery('.australia_mypost_pickup').hide();
				var category_arr = new Array();
				var description_for_other_arr = new Array();
				var index = 0;
				jQuery('.shipment_table_ausmypost_elex').each(function() {
					if (jQuery(".australia_mypost_item_category").val() == "OTHER") {
						jQuery('.decription_of_other_row:eq(' + index + ')').show();
					} else {
						jQuery('.decription_of_other_row:eq(' + index + ')').hide();
					}
					index++;
				});

				jQuery(".australia_mypost_item_category").on('change', function(e) {
					e.preventDefault();
					var selected_option = jQuery(this).find('option:selected').html();
					var index_clicked = jQuery('.australia_mypost_item_category').index(this);
					if (selected_option == "OTHER") {
						jQuery('.decription_of_other_row:eq(' + index_clicked + ')').show();
					} else {
						jQuery('.decription_of_other_row:eq(' + index_clicked + ')').hide();
					}
				});

				jQuery("#ausmypost_pickup_check").on('change', function(e) {
					e.preventDefault();
					if(this.checked){
						jQuery('.australia_mypost_pickup').show();
					}else{
						jQuery('.australia_mypost_pickup').hide();
					}
				});
				jQuery("a.create_shipment_pickup_mypost").one("click", function() {
					jQuery(this).click(function() {
						return false;
					});

					var pickup_date = '';
					var pickup_time_id ='';
					if (jQuery('input.ausmypost_pickup_checked').is(':checked')) {
						pickup_date = jQuery("#australia_mypost_pickup_date").val();
						pickup_time_id = jQuery("#australia_mypost_pickup_service_id").val();
					}

					if (pickup_date != '' && pickup_time_id != '' ) {
						location.href = this.href +
							'&pickup_date=' + pickup_date +
							'&pickup_time_id=' + pickup_time_id;

					}

					return false;
				});

				jQuery("a.create_shipment_mypost").one("click", function() {
					jQuery(this).click(function() {
						return false;
					});
					var packageTitleArray = jQuery("strong[id='shipmentmypostPackageTitle']").map(function() {
						return jQuery(this).text();
					}).get();
					var packageTitle = JSON.stringify(packageTitleArray);
					var manual_weight_array = jQuery("input[id='australia_mypost_package_manual_weight']").map(function() {
						return jQuery(this).val();
					}).get();
					var manual_weight = JSON.stringify(manual_weight_array);
					var pickup_date = '';
					var pickup_time_id ='';
					if (jQuery('input.ausmypost_pickup_checked').is(':checked')) {
						pickup_date = jQuery("#australia_mypost_pickup_date").val();
						pickup_time_id = jQuery("#australia_mypost_pickup_service_id").val();
					}
					var manual_height_array = jQuery("input[id='australia_mypost_package_manual_height']").map(function() {
						return jQuery(this).val();
					}).get();
					var manual_height = JSON.stringify(manual_height_array);

					var manual_width_array = jQuery("input[id='australia_mypost_package_manual_width']").map(function() {
						return jQuery(this).val();
					}).get();
					var manual_width = JSON.stringify(manual_width_array);

					var manual_length_array = jQuery("input[id='australia_mypost_package_manual_length']").map(function() {
						return jQuery(this).val();
					}).get();
					var manual_length = JSON.stringify(manual_length_array);

					var shipment_services_array = jQuery("select[id='australia_mypost_package_manual_service']").map(function() {
						return jQuery(this).val();
					}).get();
					var shipment_services = JSON.stringify(shipment_services_array);

					var shipment_content = jQuery("#shipment_content").val();

					var item_category_arr = jQuery("select[class='australia_mypost_item_category']").map(function() {
						return jQuery(this).val();
					}).get();
					var item_category = item_category_arr + '';
					var description_for_other_arr = jQuery("input[class='auspost_category_other_description']").map(function() {
						return jQuery(this).val();
					}).get();
					var description_of_other_str = description_for_other_arr + '';
					var ausmypost_logo_on_label = '';
					if (jQuery('#ausmypost_logo_check').is(':checked')) {
						ausmypost_logo_on_label = 'yes';
					} else {
						ausmypost_logo_on_label = 'no';
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
							'&pickup_date=' + pickup_date +
							'&pickup_time_id=' + pickup_time_id +
							'&ausmypost_logo=' + ausmypost_logo_on_label;
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
								'&pickup_date=' + pickup_date +
								'&pickup_time_id=' + pickup_time_id +
								'&ausmypost_logo=' + ausmypost_logo_on_label;
						}
					} else {
						location.href = this.href +
							'&title=' + packageTitle +
							'&weight=' + manual_weight +
							'&length=' + manual_length +
							'&width=' + manual_width +
							'&height=' + manual_height +
							'&shipping_service=' + shipment_services +
							'&pickup_date=' + pickup_date +
							'&pickup_time_id=' + pickup_time_id +
							'&ausmypost_logo=' + ausmypost_logo_on_label;
					}

					return false;
				});

				jQuery('#addPackageLoaderImage').hide();
				var orderId = <?php echo json_encode($order_id); ?>;
				var auspostPostageProducts = <?php echo json_encode($postage_products_eligible); ?>;
				var destinationCountry = <?php echo json_encode($order->get_shipping_country()); ?>;

				jQuery(document).on('click', '#add_products_extra_packages_ausmypost_elex', function() {
					var productsSelected = jQuery('#additional_products_combobox_ausmypost_elex').val();
					jQuery('#addPackageLoaderImage').show();
					var addProductsExtraPackagesAction = jQuery.ajax({
						type: 'post',
						url: ajaxurl,
						data: {
							action: 'elex_ausmypost_add_products_extra_packages',
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
							tableExtraPackageHtml += '<td align="left" style="padding:0.5%; display: none;"><input type="text" id="packed_product_ids_ausmypost_elex" size="2" />&nbsp;</td>';
							tableExtraPackageHtml += '<td align="left" size="2" class="infotip shipment_contents" style="padding: 1%; width: 20% !important"><span class="infotiptext">' + packageValue.Name + '</span><strong>' + packageValue.Name + '&nbsp;</strong></td>';
							tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><input type="hidden" id="australia_mypost_package_manual_cubic_volume"  name = "elex_auspost_manual_cubic_volume[]" size="2"   value="<?php echo ( 0 ); ?>" /><input type="text" id="australia_mypost_package_manual_weight" class="shipment_row_columns_input_style" size="2" style="width: 60% !important" value="' + packageValue.Weight.Value + '" />  &nbsp;kg</td>';
							tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><input type="text" id="australia_mypost_package_manual_length" class="shipment_row_columns_input_style" size="2" value="' + packageValue.Dimensions.Length + '" />&nbsp;cm</td>';
							tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><input type="text" id="australia_mypost_package_manual_width" class="shipment_row_columns_input_style" size="2" value="' + packageValue.Dimensions.Width + '" />&nbsp;cm</td>';
							tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><input type="text" id="australia_mypost_package_manual_height" class="shipment_row_columns_input_style" size="2" value="' + packageValue.Dimensions.Height + '" />&nbsp;cm</td>';
							tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><select class="select elex-auspost-package-service" id="australia_mypost_package_manual_service">';
							tableExtraPackageHtml += '<option class="service_label" disabled>MyPost Business</option>';
							jQuery.each(auspostPostageProducts, function(productKey, productValue) {
								if (destinationCountry == 'AU' ) {
									tableExtraPackageHtml += '<option value="' +productKey+ '">' + productValue.name + '</option>';
								} else if (destinationCountry != 'AU') {
									tableExtraPackageHtml += '<option value="' + productKey + '">' + productValue.name + '</option>';
								}
							});

							tableExtraPackageHtml += '</select></td>';
							if (destinationCountry != 'AU') {
								tableExtraPackageHtml += '<td align="left" style="padding:0.5%" class="classification_description_column">\
									<select class="australia_mypost_item_category">\
										<option value="OTHER" selected><?php _e('OTHER', 'wf-shipping-auspost'); ?></option>\
										<option value="GIFT"><?php _e('GIFT', 'wf-shipping-auspost'); ?></option>\
										<option value="SAMPLE"><?php _e('SAMPLE', 'wf-shipping-auspost'); ?></option>\
										<option value="DOCUMENT"><?php _e('DOCUMENT', 'wf-shipping-auspost'); ?></option>\
										<option value="RETURN"><?php _e('RETURN', 'wf-shipping-auspost'); ?></option>\
									</select>';
							}
							tableExtraPackageHtml += '</td>';
							if (destinationCountry != 'AU') {
								tableExtraPackageHtml += '<div class="decription_of_other_div">\
										<td align="left" style="padding:0.5%"><input type="text" class="auspost_category_other_description" placeholder="Sale"></td>\
									</div>';
							}
							tableExtraPackageHtml += '<td style="padding:0.5%;"><span class="dashicons dashicons-dismiss" id="remove_package_ausmypost_elex" style="cursor: pointer; padding-right: 5% !important"></span></td>';
							tableExtraPackageHtml += '</tr>';
						});

						jQuery('#add_additional_my_post_products').remove();
						jQuery('.table_body_packages_ausmypost_elex tr').remove();
						jQuery('.shipment_table_ausmypost_elex').append(tableExtraPackageHtml);

					});
				});

				jQuery(document).on('click', '#remove_package_ausmypost_elex', function(e) {
					e.preventDefault();
					if (destinationCountry == 'AU') {
						jQuery(this).closest('tr').remove();
					} else {
						var removedPackageProducts = jQuery(this).closest('td').siblings().find('#packed_product_ids_ausmypost_elex').val();
						jQuery(this).closest('tr').remove();
						var removedPackageProductsArray = removedPackageProducts.split(',');
						var removePackages = jQuery.ajax({
							type: 'post',
							url: ajaxurl,
							data: {
								action: 'elex_ausmypost_remove_packages',
								packagesSelected: removedPackageProductsArray,
								orderId: orderId
							},
							dataType: 'json',
						});
					}
				});

				jQuery(document).on('click', '#cancel_add_extra_mypostpackages_auspost_elex', function() {
					jQuery('#add_additional_my_post_products').remove();
				});

				jQuery('.add_extra_mypostpackages').on('click', function(e) {
					e.preventDefault();
					if (destinationCountry == 'AU') {
						var tableExtraPackageHtml = '';
						tableExtraPackageHtml += '<tr>';
						tableExtraPackageHtml += '<td align="left" style="padding:0.5%" size="2"><strong id="shipmentmypostPackageTitle">Additional Package</strong></td>';
						tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><input type="hidden" id="australia_mypost_package_manual_cubic_volume" name = "elex_auspost_manual_cubic_volume[]" size="2"   value="<?php echo ( 0 ); ?>" /><input type="text" id="australia_mypost_package_manual_weight" class="shipment_row_columns_input_style" size="2" style="width: 60% !important" />  &nbsp;kg</td>';
						tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><input type="text" id="australia_mypost_package_manual_length" class="shipment_row_columns_input_style" size="2" />&nbsp;cm</td>';
						tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><input type="text" id="australia_mypost_package_manual_width" class="shipment_row_columns_input_style" size="2" />&nbsp;cm</td>';
						tableExtraPackageHtml += '<td align="left" class="shipment_description_row_columns"><input type="text" id="australia_mypost_package_manual_height" class="shipment_row_columns_input_style" size="2" />&nbsp;cm</td>';
						tableExtraPackageHtml += '<td class="shipment_description_row_columns"><select class="select elex-auspost-package-service" id="australia_mypost_package_manual_service">';
						tableExtraPackageHtml += '<option class="service_label" disabled>MyPost Business</option>';
						jQuery.each(auspostPostageProducts, function(productKey, productValue) {
							if (destinationCountry == 'AU') {
								tableExtraPackageHtml += '<option value="' + productKey + '">' + productValue.name + '</option>';
							} else if (destinationCountry != 'AU') {
								tableExtraPackageHtml += '<option value="' + productKey + '">' + productValue.name + '</option>';
							}
						});

						tableExtraPackageHtml += '</select></td>';
						tableExtraPackageHtml += '<td style="padding:0.5%;"><span class="dashicons dashicons-dismiss" id="remove_package_ausmypost_elex" style="cursor: pointer; padding-right: 5% !important"></span></td>';
						tableExtraPackageHtml += '</tr>';
						jQuery('.shipment_table_ausmypost_elex').append(tableExtraPackageHtml);
					} else {
						<?php
						global $wpdb;
						$query                     = 'SELECT * FROM `' . $wpdb->prefix . "posts` WHERE post_type = 'product' or post_type = 'product_variation_data' ORDER BY `ID` DESC";
						$products_on_site          = $wpdb->get_results($query);
						$query_variable            = 'SELECT * FROM `' . $wpdb->prefix . "posts` WHERE post_type = 'product_variation' or post_type = 'product_variation_data' ORDER BY `ID` DESC";
						$products_on_site_variable = $wpdb->get_results($query_variable);
						$products_on_site          = array_merge($products_on_site, $products_on_site_variable);
						?>
						var productsOnSite = <?php echo json_encode($products_on_site); ?>;

						var addExtraPackageHtml = '<table><tr id="add_additional_my_post_products">';
						addExtraPackageHtml += '<td style="width: 0.1% !important; padding-left: 1%;"><?php _e('Select Products', 'wf-shipping-auspost'); ?></td>';
						addExtraPackageHtml += '<td style="width: 0.1% !important"><select class="chosen_select" multiple="multiple" id="additional_products_combobox_ausmypost_elex" name="additional_products_auspost_elex[]">';
						jQuery.each(productsOnSite, function(productIndex, product) {
							productId = product["ID"];
							productTitle = product["post_title"];
							addExtraPackageHtml += '<option value="' + productId + '">' + productTitle + '</option>';
						});
						addExtraPackageHtml += '</select></td>';
						addExtraPackageHtml += '<td style="width: 0.1%"><a class="button tips onclickdisable" id="add_products_extra_packages_ausmypost_elex"><?php _e('Add Products', 'wf-shipping-auspost'); ?></a></td>';
						addExtraPackageHtml += '<td style="width: 2%"><a class="button tips onclickdisable" id="cancel_add_extra_mypostpackages_auspost_elex"><?php _e('Cancel', 'wf-shipping-auspost'); ?></a></td>';
						var imagePath = "<?php echo untrailingslashit(content_url('plugins/australia-post-woocommerce-shipping/images/ELEX_AusPost_loader.gif')); ?>";
						addExtraPackageHtml += '<td><img id="addPackageLoaderImage" src="<?php echo untrailingslashit(content_url('plugins/australia-post-woocommerce-shipping/australia_mypost/images/ELEX_AusPost_loader.gif')); ?>" style="width: 40%; height: 30%;" ></td>';
						addExtraPackageHtml += '</tr></table>';
						jQuery('.shipment_table_ausmypost_elex').after(addExtraPackageHtml);
						jQuery('#additional_products_combobox_ausmypost_elex').selectWoo({
							width: '100%'
						});

						jQuery('#addPackageLoaderImage').hide();
						jQuery('#add_additional_my_post_products').css("width", "2% !important");
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
				jQuery(document).on('click', '#ausmypost_generate_refresh_service_button', function(e) {
					e.preventDefault();
					var orderId = <?php echo json_encode($order_id); ?>;
					var rates_loader_img_html = `<img src=" <?php echo untrailingslashit(plugins_url()) . '/australia-post-woocommerce-shipping/australia_mypost/images/load.gif'; ?>"  align="center" style=" display:block;margin-left:auto;margin-right:auto;width:30%;" id="rates_loader_img" class="rates_loader_img">`;
					jQuery('#elex_ausmypost_available_services').html(rates_loader_img_html);
					var manual_weight_array = jQuery("input[id='australia_mypost_package_manual_weight']").map(function() {
						return jQuery(this).val();
					}).get();

					var manual_cubic_volume_array = jQuery("input[id='australia_mypost_package_manual_cubic_volume']").map(function() {
						return jQuery(this).val();
					}).get();
					var manual_height_array = jQuery("input[id='australia_mypost_package_manual_height']").map(function() {
						return jQuery(this).val();
					}).get();
					var manual_width_array = jQuery("input[id='australia_mypost_package_manual_width']").map(function() {
						return jQuery(this).val();
					}).get();
					var manual_length_array = jQuery("input[id='australia_mypost_package_manual_length']").map(function() {
						return jQuery(this).val();
					}).get();

					var elexAusmypostGetServices = jQuery.ajax({
						type: 'post',
						url: ajaxurl,
						data: {
							action: 'elex_ausmypost_get_services',
							weight: manual_weight_array,
							length: manual_length_array,
							width: manual_width_array,
							height: manual_height_array,
							cubic_volume: manual_cubic_volume_array,
							orderId: orderId,
						},
						dataType: 'json',
					});
					
					elexAusmypostGetServices.done(function(response) {
						if (response.type == 'success') {
							var data = response.data;
							var auspost_service_table_html = ` <span id="elex_ausmypost_available_services_table_title" style="font-weight:bold;">Available Service/Rates:</span>
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
							jQuery('#elex_ausmypost_available_services').html(auspost_service_table_html);
						} else if (response.type == 'error') {
							var error_message_html = `<p style="color:red;"><strong><?php _e('Something went wrong!!, Please try again later.', 'wf-shipping-auspost'); ?></strong></p>`;
							jQuery('#elex_ausmypost_available_services').html(error_message_html);
						} else {
							var error_message_html = `<p style="color:red;"><strong><?php _e('Something went wrong!!, Please try again later.', 'wf-shipping-auspost'); ?></strong></p>`;
							jQuery('#elex_ausmypost_available_services').html(error_message_html);
						}
					});
				});
			});
		</script>
	<?php
	}

		/**
	 * function to get reachship Access Token
	 *
	 * @access public
	 */

	public function get_reachship_access_token() {

		if ('' == $this->client_id && '' == $this->client_secret) {
			return;
		}
		$service_base_url = 'https://' . self::API_HOST . self::API_BASE_URL . 'oauth/token';
	
		if (isset( $this->general_settings['mode_check'] ) && 'live' === $this->general_settings['mode_check']) {
			$service_base_url = str_replace('sandbox/', 'production/', $service_base_url);
		}
	
		$rqs_headers      = array(
			'Accept' => 'application/json',
		);
		$arg              = array(
			'grant_type' => 'client_credentials',
			'client_id' => $this->client_id,
			'client_secret' => $this->client_secret,
		);
		$service_base_url = $service_base_url . '?' . http_build_query($arg);
		$res              = wp_remote_get($service_base_url, array(
			'method' => 'GET',
			'headers' => $rqs_headers,
		));
	
		$res_body_decode = array();
		if ( is_wp_error($res) ) {
			$error_string = $res->get_error_message();
			$this->debug(__($error_string, 'wf-shipping-auspost'));
		} else {
			$res_body_decode = json_decode($res['body']);
		}
		
		if ( !empty($res_body_decode) && 200 == $res['response']['code'] ) {
			$access_token = ( isset($res_body_decode->access_token) ) ? $res_body_decode->access_token : '';
			if ('' !== $access_token) {
				set_transient( 'wf_australia_mypost_access_token', $access_token, DAY_IN_SECONDS );
			}
		} else {
			if (!empty($res_body_decode)) {
	
				if ( isset($res_body_decode->errors) ) {
					$errors_message = $res_body_decode->errors[0]->message;
					$this->debug(__($errors_message, 'wf-shipping-auspost'));
				} else {
					$errors_message = $res_body_decode->message;
					$this->debug(__($errors_message, 'wf-shipping-auspost'));
				}
			}
			delete_transient('wf_australia_mypost_access_token');
		}
		return $access_token;
	}
	/**
	 * get_request_header for JSON function.
	 *
	 */
	private function buildHttpHeaders() {
		$this->access_token = get_transient( 'wf_australia_mypost_access_token' );

		if ($this->access_token) {
			$this->access_token = isset($this->general_settings['access_token']) && ''!==$this->general_settings['access_token'] ? $this->general_settings['access_token'] : '';
		} else {
			$this->access_token = $this->get_reachship_access_token();
		}

		$a_headers = array(
			'content-type' => 'application/json',
			'Authorization' => 'Bearer ' . $this->access_token,
		);
		
		return $a_headers;
	}

	/**
	 * Output a message
	 */
	public function debug( $message, $type = 'notice') {
		if ($this->debug || $type == 'error') {
			echo ( $message );
		}
	}


	private function is_refunded_item( $order, $item_id) {
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
	private function wf_get_order_id( $order) {
		global $woocommerce;
		return ( WC()->version < '2.7.0' ) ? $order->id : $order->get_id();
	}

	private function custom_order_number( $order_id) {
		$order = wc_get_order($order_id);
		if ($order) {
			return $order->get_order_number();
		}    
		return $order_id;
	}

	public function elex_ausmypost_get_services() {
		if (!isset($_POST['orderId'])) {
			die();
		}

		$order_id          = $_POST['orderId'];
		$shipment_requests = get_post_meta($order_id, 'shipment_packages_ausmypost_elex', true);
		$package           = array();
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
		$package['packtype']       = isset($shipment_requests[0]['packtype']) ? $shipment_requests[0]['packtype'] : 'ITM';
		$this->found_rates         = array();
		$shipping_services         = $this->elex_ausmypost_get_shipping_services($package, $order_id);

		$response = array(
			'type' => 'success',
			'data' => $this->found_rates
		);
		die(json_encode($response));
	}
	public function elex_ausmypost_get_shipping_services( $package, $order_id) {

		$order = wc_get_order($order_id);
		if ($this->mypost_contracted_rates) {
			if (is_array($package['items'])) {
				$count_package_requests = 0;
				$from_weight_unit       = '';
				if ($this->weight_unit != 'kg') {
					$from_weight_unit = $this->weight_unit;
				}
	
				$from_dimension_unit = '';
				if ($this->dimension_unit != 'cm') {
					$from_dimension_unit = $this->dimension_unit;
				}
				$count_package_requests         = 0;
				$rates_request_body             = array();
				$rates_request_body['shipment'] = array(
					'ship_to' => 
					array (
					'city_locality' => $order->get_shipping_city(),
					'state_province' => $order->get_shipping_state(),
					'postal_code' => $order->get_shipping_postcode(),
					'country_code' => $order->get_shipping_country(),
					),
					'ship_from' => 
					array (
					'city_locality' => $this->settings['origin_suburb'],
					'state_province' => $this->settings['origin_state'],
					'postal_code' => $this->settings['origin'],
					'country_code' => 'AU',
					)
				);
			
				foreach ($package['items'] as $key => $package_request) {
					$count_package_requests++;
					$single_packages = array();

					/** 
						 MyPost Business request will accept only kg and cm.
					*/

					$single_packages = array(
						'weight' =>array(
							'value' => round(wc_get_weight($package_request['weight'], 'kg', $from_weight_unit), 3),
							'unit' 	=> 'KG',
						),
						'length' =>array(
							'value' => round(wc_get_dimension($package_request['length'], 'cm', $from_dimension_unit), 1),
							'unit' 	=> 'CM',
						),
						'width' =>array(
							'value' => round(wc_get_dimension($package_request['width'], 'cm', $from_dimension_unit), 1),
							'unit' 	=> 'CM',
						),
						'height' =>array(
							'value' => round(wc_get_dimension($package_request['height'], 'cm', $from_dimension_unit), 1),
							'unit' 	=> 'CM',
						)
					);
					
					$rates_request_body['shipment']['packages'][] = $single_packages;
				}

				$auspost_service_rates = array();
				if (!empty($rates_request_body['shipment']['packages'])) {

					$endpoint = 'https://' . self::API_HOST . self::API_BASE_URL . 'rates';
					$headers  = $this->buildHttpHeaders();
				
					if ($this->settings['mode_check'] == 'live') {
						$endpoint = str_replace('sandbox/', 'production/', $endpoint);
					}
					$auspost_service_rates_for_package = $this->get_contracted_rates($endpoint, $rates_request_body, $headers);
					
					if (empty($auspost_service_rates_for_package)) {
						$response = array(
							'type' => 'error',
							'message' => "MyPost Business Didn't respond. Please Try Again Later"
						);
						die(json_encode($response));
					}
					if (isset($auspost_service_rates_for_package['error_message']) && !empty($auspost_service_rates_for_package['error_message'])) {
						$response = array(
							'type' => 'error',
							'message' => $auspost_service_rates_for_package['error_message']
						);
						die(json_encode($response));
					}

					if (  isset($auspost_service_rates_for_package) && !empty($auspost_service_rates_for_package)) {
						$auspost_service_rates = $auspost_service_rates_for_package;
					}
				}

				$wc_main_settings = get_option('woocommerce_wf_australia_mypost_settings');

				/*For  Mypost*/
				if (!empty($auspost_service_rates)) {
					foreach ($auspost_service_rates as  $key => $rate) {
						if (!empty($rate)) {
							$items_product_type = isset($rate->product_type) ? $rate->product_type : '';
							if (isset($wc_main_settings['include_exclude_gst']) && $wc_main_settings['include_exclude_gst'] == 'exclude') {
								$rate_include_gst = $rate->totalBaseCharge;
							} else {
								$rate_include_gst = $rate->totalChargeWithTaxes;
							}
							if (isset($rate->totalBaseCharge)) {
								$this->prepare_rate($key, $key, $items_product_type, $rate_include_gst, $rates_request_body['shipment']['packages'] );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * get_contracted_rates function
	 *
	 * @access private
	 * @param mixed $endpoint,$request,$headers
	 * @return ArrayObject
	 */

	private function get_contracted_rates( $endpoint, $request, $headers) {

		$args = array(
			'method' => 'POST',
			'httpversion' => '1.1',
			'timeout' => 70,
			'headers' => $headers,
			'body' => json_encode($request)
		);
		$res  = wp_remote_post($endpoint, $args);
		if (is_wp_error($res)) {
			$shipment_rates_result['error_message'][] = $res->get_error_message();
			return $shipment_rates_result;
		} else {
			$response_array = isset($res['body']) ? json_decode($res['body']) : array();
		}

		$shipment_rates_result = array();
		if ( !empty($response_array) && 200 == $res['response']['code'] ) {
			
			foreach ($response_array as $key => $rate) {
				if ('AUSPOST_MYPOST' === $rate->companyName ) {
					$get_product_id = explode('- ', $rate->serviceName);
					if (3 === count($get_product_id)) {
						$shipment_rates_result[$get_product_id[2]] =  $rate;
					} else {
						$shipment_rates_result[$get_product_id[1]] =  $rate;
					}
				}
			}
		} else {
			if (!empty($response_array)) {
				if ( isset($response_array->errors) ) {
					$shipment_rates_result['error_message'][] = $response_array->errors[0]->message;
					return $shipment_rates_result;
				} else {
					$shipment_rates_result['error_message'][] = $response_array->message;
					return $shipment_rates_result;
				}
			}
		}
		return $shipment_rates_result;
	}
	private function buildHttpHeadersServices( $request, $api_account_number, $api_password, $api_key = false) {
		$api_key   = $api_key? $api_key: $this->api_key;
		$a_headers = array(
			'content-type' => 'application/json',
			'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
			'Account-Number' => $api_account_number,
			'Authorization' => 'Basic ' . base64_encode($api_key . ':' . $api_password),
		);
		return $a_headers;
	}
	public function get_services( $endpoint, $account_number, $account_password) {
		$header       = '';
		$responseBody = '';

		$account_password = str_replace('&lt;', '<', $account_password);
		$account_password = str_replace('&gt;', '>', $account_password);
		$args             = array(
			'httpversion' => '1.1',
			'blocking'    => true,
			'headers'     => array(
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
				'Account-Number' => $account_number,
				'Authorization' => 'Basic ' . base64_encode($this->api_key . ':' . $account_password)
			),
		);
		$response         = wp_remote_get($endpoint, $args);
		if (is_array($response)) {
			$header       = $response['headers']; // array of http header lines
			$responseBody = $response['body']; // use the content
		}

		return $responseBody;
	}
	private function prepare_rate( $rate_code, $rate_id, $rate_name, $rate_cost, $package_request = '') {

		$rate_actual_code = $rate_code;

		if (!empty($this->custom_services[$rate_code])) {
			$this->custom_services[$rate_code] = apply_filters('wf_australia_post_rate_services', $this->custom_services, $this->custom_services[$rate_code], $rate_code, $package_request);
		}
		// Name adjustment      
		
		if (!empty($this->general_settings['services'][$rate_code]['name'])) {
			$rate_name = $this->general_settings['services'][$rate_code]['name'];
		}

		// Merging
		if (isset($this->found_rates[$rate_id])) {
			$rate_cost = $rate_cost;
			$packages  = 1 + $this->found_rates[$rate_id]['packages'];
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
			'label' => $rate_name ,
			'cost' => $rate_cost,
			'sort' => $sort,
			'packages' => $packages,
			'actual_code' => $rate_actual_code
		);
	}
	public function string_clean( $string) { 

		$string = preg_replace('/[^A-Za-z0-9\-]/', ' ', $string); // Removes special chars.
		$string = str_replace('  ', ' ', $string);
		return $string;

	}
	public function get_composite_product_items( $order_items) {
		if (!empty($order_items)) {
			$new_order_items = array();
			foreach ($order_items as $key => $order_item) {
				$product = $order_item->get_product();
				if ($product->is_type( 'composite' )) {
					if (!$product->is_virtual()) {
						$new_order_items[$key] = $order_item;
					}

				} else {
					$composite_parent = $order_item->get_meta( '_composite_parent', true );
					$composite_item   = $order_item->get_meta( '_composite_item', true );
					$composite_data   = $order_item->get_meta( '_composite_data', true );
					if ($composite_parent && $composite_item && $composite_data && isset($composite_data) && isset($composite_data[$composite_item]) &&  isset($composite_data[$composite_item]['composite_id']) ) {
						$composite_parent_id = $composite_data[$composite_item]['composite_id'];
						$product_composite   = wc_get_product($composite_parent_id); 
						if ($product_composite->is_type( 'composite' )) {
							if ($product_composite->is_virtual()) {
								$new_order_items[$key] = $order_item;
							}
			
						} else {
							$new_order_items[$key] = $order_item;
						}             
					} else {
						$new_order_items[$key] = $order_item;
					}
				}
			}
			$order_items = $new_order_items;
		}
		return $order_items;
	}
}

new wf_australia_mypost_shipping_admin();
?>
