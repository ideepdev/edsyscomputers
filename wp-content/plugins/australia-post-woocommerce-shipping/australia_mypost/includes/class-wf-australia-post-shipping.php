<?php
/*Making compatible with PHP 7.1 later versions*/
if (version_compare(phpversion(), '7.1', '>=')) {
	ini_set('serialize_precision', -1); // Avoiding adding of unnecessary 17 decimal places resulted from json_encode
}

class wf_australia_mypost_shipping extends WC_Shipping_Method {


	const API_HOST     = 'api.reachship.com';
	const API_BASE_URL = '/sandbox/v1/';
	
	private $sod_cost         = array('domestic' => 2.95, 'international' => 5.5); // Signature on delivery charges
	private $extra_cover_cost = array('domestic' => 2.5, 'international' => 4.0); // Extra cover costs
	private $found_rates;
	private $rate_cache;

	private $services = array(); // these services are defined statically

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->id                 = WF_AUSTRALIA_MYPOST_ID;
		$this->method_title       = __('MyPost Business', 'wf-shipping-auspost');
		$this->method_description = __('', 'wf-shipping-auspost');
		if (!class_exists('WF_ausmypost_services')) {
			include_once 'settings/class_wf_ausmypost_services.php';
		}

		$auspost_services_obj = new WF_ausmypost_services();
		/** Services called from 'services' API without options */
		$this->services = $auspost_services_obj->get_services(); // these services are defined statically
		$this->init();
	}

	/**
	 * init function.
	 *
	 * @access public
	 * @return void
	 */
	private function init() {
		include_once 'data-wf-default-values.php';
		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();
		$this->general_settings        = get_option('woocommerce_wf_australia_mypost_settings');
		$this->mypost_contracted_rates = isset($this->general_settings['client_account_name']) && ''!==$this->general_settings['client_account_name'] ? true : false;
		// Define user set variables
		$this->title        = $this->get_option('title');
		$this->availability = $this->get_option('availability');
		$this->countries    = $this->get_option('countries');
		$this->origin       = $this->get_option('origin');

		$this->my_rates = $this->get_option('contracted_rates') == 'yes' ? true : false;

		wp_localize_script('elex-auspost-custom', 'elex_ausmypost_custom', array('contracted_rates' => ''));
		$this->client_id     = isset($this->general_settings['client_id']) && ''!==$this->general_settings['client_id'] ? $this->general_settings['client_id'] : '';
		$this->client_secret = isset($this->general_settings['client_secret']) && ''!==$this->general_settings['client_secret'] ? $this->general_settings['client_secret'] : '';
		$this->access_token  = get_transient( 'wf_australia_mypost_access_token' );
		if ($this->access_token && ''!==$this->client_id && ''!==$this->client_secret) {
			$this->access_token = isset($this->general_settings['access_token']) && ''!==$this->general_settings['access_token'] ? $this->general_settings['access_token'] : '';
		} else {
			$this->access_token = $this->get_reachship_access_token();
		}

		$packing_method_settings = $this->get_option('packing_method');
		$this->packing_method    = !empty($packing_method_settings) ? $packing_method_settings : 'per_item';

		$this->boxes                                       = $this->get_option('boxes');
		$this->weight_boxes                                = isset($this->general_settings['weight_boxes']) ? $this->general_settings['weight_boxes'] : array();
		$this->custom_services                             = $this->get_option('services');
		$this->offer_rates                                 = $this->get_option('offer_rates');
		$this->debug                                       = $this->get_option('debug_mode') == 'yes' ? true : false;
		$this->max_weight                                  = $this->get_option('max_weight');
		$this->weight_unit                                 = get_option('woocommerce_weight_unit');
		$this->dimension_unit                              = get_option('woocommerce_dimension_unit');
		$this->weight_packing_process                      = !empty($this->settings['weight_packing_process']) ? $this->settings['weight_packing_process'] : 'pack_descending'; // This feature will be implementing in next version
		$this->previous_rate_cost_stored                   = 0;
		$this->shipment_type                               = ''; // domestic or international
		$this->packages                                    = array();
		$this->selected_shipment_service                   = '';
		$this->insurance_requested_at_checkout             = false;
		$this->signature_requested_at_checkout             = false;
		$this->vendor_check                                = ( in_array('multi-vendor-add-on-for-thirdparty-shipping/multi-vendor-add-on-for-thirdparty-shipping.php', apply_filters('active_plugins', get_option('active_plugins'))) && ( isset($this->settings['vendor_check']) && ( $this->settings['vendor_check'] == 'yes' ) ) ) ? true : false;
		$this->vedor_api_key_enable                        = ( in_array('multi-vendor-add-on-for-thirdparty-shipping/multi-vendor-add-on-for-thirdparty-shipping.php', apply_filters('active_plugins', get_option('active_plugins'))) && ( get_option('wc_settings_wf_vendor_addon_allow_vedor_api_key') == 'yes' ) ) ? true : false;
		$this->is_woocommerce_composite_products_installed = ( in_array('woocommerce-composite-products/woocommerce-composite-products.php', get_option('active_plugins')) ) ? true : false;
		$this->is_elex_combined_export_tool_enable         = ( isset($this->general_settings['combined_export_tool_enable']) && $this->general_settings['combined_export_tool_enable'] == 'yes' ) ? 'yes' : false; 
		$this->is_elex_combined_export_tool_show_rate_separate = ( isset($this->general_settings['combined_export_tool_show_rate_separate']) && $this->general_settings['combined_export_tool_show_rate_separate'] == 'yes' && $this->is_elex_combined_export_tool_enable == 'yes' ) ? 'yes' : false;
		add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'clear_transients'));

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
	 * Output a message
	 */
	public function debug( $message, $type = 'notice') {
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
	private function environment_check() {
		global $woocommerce;

		if (get_woocommerce_currency() != 'AUD') {
			echo '<div class="error">
                <p>' . __('Australia Post requires that the currency is set to Australian Dollars.', 'wf-shipping-auspost') . '</p>
            </div>';
		} elseif ($woocommerce->countries->get_base_country() != 'AU') {
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
	public function admin_options() {
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
	public function validate_box_packing_field( $key) {
		if (!isset($_POST['boxes_outer_length'])) {
			return;
		}

		$boxes_outer_length = $_POST['boxes_outer_length'];
		$boxes_outer_width  = $_POST['boxes_outer_width'];
		$boxes_outer_height = $_POST['boxes_outer_height'];
		$boxes_inner_length = $_POST['boxes_inner_length'];
		$boxes_inner_width  = $_POST['boxes_inner_width'];
		$boxes_inner_height = $_POST['boxes_inner_height'];
		$boxes_box_weight   = $_POST['boxes_box_weight'];
		$boxes_max_weight   = $_POST['boxes_max_weight'];
		$boxes_is_letter    = isset($_POST['boxes_is_letter']) ? $_POST['boxes_is_letter'] : array();

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
					if ($outer_dimensions[0] < 4) {
						$outer_dimensions[0] = 4;
					}

					if ($outer_dimensions[1] < 5) {
						$outer_dimensions[1] = 5;
					}
				}

				if ($inner_girth < 16) {
					if ($inner_dimensions[0] < 4) {
						$inner_dimensions[0] = 4;
					}

					if ($inner_dimensions[1] < 5) {
						$inner_dimensions[1] = 5;
					}
				}

				if ($outer_dimensions[2] > 105) {
					$outer_dimensions[2] = 105;
				}

				if ($inner_dimensions[2] > 105) {
					$inner_dimensions[2] = 105;
				}

				$outer_length = $outer_dimensions[2];
				$outer_height = $outer_dimensions[0];
				$outer_width  = $outer_dimensions[1];

				$inner_length = $inner_dimensions[2];
				$inner_height = $inner_dimensions[0];
				$inner_width  = $inner_dimensions[1];

				if (empty($inner_length) || $inner_length > $outer_length) {
					$inner_length = $outer_length;
				}

				if (empty($inner_height) || $inner_height > $outer_height) {
					$inner_height = $outer_height;
				}

				if (empty($inner_width) || $inner_width > $outer_width) {
					$inner_width = $outer_width;
				}

				$weight = floatval($boxes_max_weight[$i]);

				if ($weight > 22 || empty($weight)) {
					$weight = 22;
				}

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
	public function clear_transients() {
		delete_transient('wf_australia_post_quotes');
	}

	public function generate_activate_box_html() {
		ob_start();
		$plugin_name = 'australiapost';
		include 'wf_api_manager/html/html-wf-activation-window.php';
		return ob_get_clean();
	}

	public function generate_wf_aus_tab_box_html() {

		$tab = ( !empty($_GET['subtab']) ) ? esc_attr($_GET['subtab']) : 'general';

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
			$plugin_name = 'elex-australia-post-return-label-addon';
			include_once ELEX_AUSTRALIA_POST_RETURN_LABEL_ADDON_PATH . '/includes/wf_api_manager/html/html-wf-activation-window.php';
			echo '<script>
                    jQuery(document).ready(function(){
                        jQuery(".activation_window").hide();
                        jQuery(".elex_australia_post_return_label_general_section").addClass("current");
                    });
                  </script>';
		}


		switch ($tab) {
			case 'general':
				echo '<div class="table-box table-box-main" id="general_section" style="margin-top: 0px;
                        border: 1px solid #ccc;border-top: unset !important;padding: 5px;">';
				require_once 'settings/aus_general_settings.php';
				echo '</div>';
				break;
			case 'rates':
				echo '<div class="table-box table-box-main" id="rates_section" style="margin-top: 0px;
                        border: 1px solid #ccc;border-top: unset !important;padding: 5px;">';
				require_once 'settings/aus_rates_settings.php';

				echo '</div>';
				break;
			case 'labels':
				echo '<div class="table-box table-box-main" id="labels_section" style="margin-top: 0px;
                        border: 1px solid #ccc;border-top: unset !important;padding: 5px;">';
				require_once 'settings/aus_label_settings.php';
				echo '</div>';
				break;
			case 'packing':
				echo '<div class="table-box table-box-main" id="packing_section" style="margin-top: 0px;
                        border: 1px solid #ccc;border-top: unset !important;padding: 5px;">';
				require_once 'settings/aus_packing_settings.php';
				echo '</div>';
				break;
			case 'licence':
				echo '<div class="table-box table-box-main" id="licence_section" style="margin-top: 0px;
                        border: 1px solid #ccc;border-top: unset !important;padding: 5px;"><br>';
				$plugin_name = 'australiapost';
				include_once WF_AUSTRALIA_POST_PATH . 'wf_api_manager/html/html-wf-activation-window.php';
				include 'html-wf-australia-post-addons.php';

				echo '</div>';
				break;
		}
		echo '
                </div>';
	}

	public function wf_aus_shipping_page_tabs( $current = 'general') {
		$activation_check = get_option('australiapost_activation_status');
		if (!empty($activation_check) && $activation_check === 'active') {
			$acivated_tab_html = "<small style='color:green;font-size:xx-small;'>(Activated)</small>";
		} else {
			$acivated_tab_html = "<small style='color:red;font-size:xx-small;'>(Activate)</small>";
		}

		$image = "<small style='color:green;font-size:xx-small;'>(Settings)</small>";
		$tabs  = array(
			'general'   => __('General', 'wf-shipping-auspost'),
			'rates'     => __('Rates & Services', 'wf-shipping-auspost'),
			'labels'    => __('Label & Tracking', 'wf-shipping-auspost'),
			'packing'   => __('Packaging', 'wf-shipping-auspost'),
			'licence'   => __('Licence ' . $acivated_tab_html, 'wf-shipping-auspost'),
		);
		// if (ELEX_AUSTRALIA_POST_RETURN_ADDON_STATUS) {
		// 	$tabs['return'] = __('Return Label', 'wf-shipping-auspost');
		// }
		// if (ELEX_AUS_POST_AUTO_LABEL_GENERATE_ADDON_WOOCOMMERCE_EXTENSION) {
		// 	$tabs['auto-generate-add-on'] =  __('Auto Label Generate Add-on ' . $image, 'wf-shipping-auspost');
		// }
		$html = '<h2 class="nav-tab-wrapper">';
		foreach ($tabs as $tab => $name) {
			$class = ( $tab == $current ) ? 'nav-tab-active' : '';
			$style = ( $tab == $current ) ? 'border-bottom: 1px solid transparent !important;' : '';
			$html .= '<a style="text-decoration:none !important;' . $style . '" class="nav-tab ' . $class . '" href="?page=' . wf_get_settings_url() . '&tab=shipping&section=wf_australia_mypost&subtab=' . $tab . '">' . $name . '</a>';
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
	public function init_form_fields() {
		global $woocommerce;
		if (isset($_GET['page']) && $_GET['page'] === 'wc-settings') {
			$this->form_fields = array(
				'wf_aus_tab_box_key' => array(
					'type' => 'wf_aus_tab_box'
				),
			);
			//Return Label Add-on.
			// if (ELEX_AUSTRALIA_POST_RETURN_ADDON_STATUS && ELEX_AUSTRALIA_POST_RETURN_LABEL_ADDON_PATH) {
			// 	$add_on_fields = include(ELEX_AUSTRALIA_POST_RETURN_LABEL_ADDON_PATH . '/includes/data-wf-settings.php');
			// 	if (is_array($add_on_fields)) {
			// 		$this->form_fields = array_merge($this->form_fields, $add_on_fields);
			// 	}
			// }
			//Auto Label Generate Add-on.
			// if (ELEX_AUS_POST_AUTO_LABEL_GENERATE_ADDON_WOOCOMMERCE_EXTENSION) {
			// 	$auto_add_on_fields = include(ELEX_AUS_POST_AUTO_LABEL_GENERATE_ADDON_WOOCOMMERCE_EXTENSION_PATH . '/australia_mypost/includes/data-wf-settings.php');
			// 	if (is_array($auto_add_on_fields)) {
			// 		$this->form_fields = array_merge($this->form_fields, $auto_add_on_fields);
			// 	}
			// }
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
	public function girth_validation( $item_l, $item_w, $item_h, $package_l, $package_w) {
		
		if (!$item_h) {
			$item_h = 0;
		}

		// Check max height
		if ($item_h > ( $package_w / 2 )) {
			return false;
		}

		// Girth = around the item
		$item_girth = $item_w + $item_h;

		if ($item_girth > $package_w) {
			return false;
		}

		// Girth 2 = around the item
		$item_girth = $item_l + $item_h;

		if ($item_girth > $package_l) {
			return false;
		}

		return true;
	}

	/**
	 * See if rate is satchel
	 *
	 * @return boolean
	 */
	public function is_satchel( $code) {
		return strpos($code, '_SATCHEL_') !== false;
	}

	/**
	 * See if rate is letter
	 *
	 * @return boolean
	 */
	public function is_letter( $code) {
		return strpos($code, '_LETTER_') !== false;
	}

	/**
	 * function to get highest dimension among all the packed products
	 *
	 * @access public
	 */

	public function return_highest( $dimension_array) {
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
	public function calculate_shipping( $package = array()) {
		global $woocommerce;
		$package = apply_filters('elex_aus_mypost_before_calculate_shipping', $package);
		// Checking the real time service option is activated or not
		if (!isset($this->general_settings['enabled']) || empty($this->general_settings['enabled'])) {
			return;
		}

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
			$this->insurance_requested_at_checkout = isset($str['ausmypost_insurance']) ? $str['ausmypost_insurance'] : false;
			update_option('ausmypost_extra_cover_checkout', $this->insurance_requested_at_checkout);
			
			$this->signature_requested_at_checkout = false;
			if (isset($str['ausmypost_signature'])) {
				$this->signature_requested_at_checkout = $str['ausmypost_signature'];
			} 
			update_option('ausmypost_signature_required_checkout', $this->signature_requested_at_checkout);

		} elseif (!is_shop() && !is_cart()) {

			$this->insurance_requested_at_checkout = get_option('ausmypost_extra_cover_checkout');
			$this->signature_requested_at_checkout = get_option('ausmypost_signature_required_checkout');
		}

		$this->is_international = ( $package['destination']['country'] == 'AU' ) ? false : true;
		$this->found_rates      = array();
		$this->rate_cache       = get_transient('wf_australia_post_quotes');

		$package_requests    = $this->get_package_requests($package);
		$settings_services   = $this->general_settings['services'];
		$custom_services     = array();
		$endpoint            = '';
		$extra_cover_package = 0;

		if ($this->mypost_contracted_rates) {

			if (is_array($package_requests)) {
				$package_requests_size          = count($package_requests);
				$count_package_requests         = 0;
				$rates_request_body             = array();
				$total_extra_cover              = 0;
				$total_sod_cost                 = 0;
				$rates_request_body['shipment'] = array(
					'ship_to' => 
					array (
					'city_locality' => $package['destination']['city'],
					'state_province' => $package['destination']['state'],
					'postal_code' => $package['destination']['postcode'],
					'country_code' => $package['destination']['country'],
					),
					'ship_from' => 
					array (
					'city_locality' => $this->settings['origin_suburb'],
					'state_province' => $this->settings['origin_state'],
					'postal_code' => $this->settings['origin'],
					'country_code' => 'AU',
					)
				);

				foreach ($package_requests as $key => $package_request) {
					
					$single_packages = array();
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
					if ($this->is_international) {
						if (( $extra_cover_package > 100 ) && ( $extra_cover_package <= 5000 )) {
							$extra_cover_cal = $extra_cover_package / 100;
							if ( $extra_cover_package % 100 === 0 ) {

								$extra_cover_cal_multiplier = $extra_cover_cal - 1;
								$total_extra_cover         += $this->extra_cover_cost['international']*$extra_cover_cal_multiplier; // extra cover fee for greater than 100

							} else {
								$extra_cover_cal_multiplier = intval( $extra_cover_cal );
								$total_extra_cover         += $this->extra_cover_cost['international']*$extra_cover_cal_multiplier ; // extra cover fee for greater than 100

							}
						}
					} else {
						if (( $extra_cover_package > 100 ) && ( $extra_cover_package <= 5000 )) {
							$extra_cover_cal = $extra_cover_package / 100;
							if ( $extra_cover_package % 100 === 0 ) {
								
								$extra_cover_cal_multiplier =   $extra_cover_cal - 1;
								$total_extra_cover         += $this->extra_cover_cost['domestic']*$extra_cover_cal_multiplier; // extra cover fee for greater than 100

							} else {
								$extra_cover_cal_multiplier = intval( $extra_cover_cal );
								$total_extra_cover         += $this->extra_cover_cost['domestic']*$extra_cover_cal_multiplier ; // extra cover fee for greater than 100
							}
						}
					}
					if ( $extra_cover_package > 500) {
						if ($this->is_international) {
										
							$total_sod_cost += $this->sod_cost['international'];

						} else {

							$total_sod_cost += $this->sod_cost['domestic'];
						}
					}
					/** 
						 MyPost Business request will accept only kg and cm.
					*/
					if (isset($package_request['Dimensions']['Thickness'])) {

						$single_packages = array(
							'weight' =>array(
								'value' => round(wc_get_weight($package_request['Weight']['Value'], 'kg', $from_weight_unit), 3),
								'unit' 	=> 'KG',
							),
							'length' =>array(
								'value' => round(wc_get_dimension($package_request['Dimensions']['Length'], 'cm', $from_dimension_unit), 3),
								'unit' 	=> 'CM',
							),
							'width' =>array(
								'value' => round(wc_get_dimension($package_request['Dimensions']['Width'], 'cm', $from_dimension_unit), 3),
								'unit' 	=> 'CM',
							),
							'height' =>array(
								'value' => round(wc_get_dimension($package_request['Dimensions']['Thickness'], 'cm', $from_dimension_unit), 3),
								'unit' 	=> 'CM',
							)
						);
					} else {

						$single_packages = array(
							'weight' =>array(
								'value' => round(wc_get_weight($package_request['Weight']['Value'], 'kg', $from_weight_unit), 3),
								'unit' 	=> 'KG',
							),
							'length' =>array(
								'value' => round(wc_get_dimension($package_request['Dimensions']['Length'], 'cm', $from_dimension_unit), 3),
								'unit' 	=> 'CM',
							),
							'width' =>array(
								'value' => round(wc_get_dimension($package_request['Dimensions']['Width'], 'cm', $from_dimension_unit), 3),
								'unit' 	=> 'CM',
							),
							'height' =>array(
								'value' => round(wc_get_dimension($package_request['Dimensions']['Height'], 'cm', $from_dimension_unit), 3),
								'unit' 	=> 'CM',
							)
						);
					}

					$rates_request_body['shipment']['packages'][] = $single_packages;
				}
				/*For Aus MyPost Business*/
				$this->debug(__('AusMypost Business debug is ON - to hide these messages, disable <i>debug mode</i> in settings.', 'wf-shipping-auspost'));
				$auspost_service_rates = array();
				if (!empty($rates_request_body['shipment']['packages'])) {

					$endpoint = 'https://' . self::API_HOST . self::API_BASE_URL . 'rates';
					$headers  = $this->buildHttpHeaders();
				
					if ($this->settings['mode_check'] == 'live') {
						$endpoint = str_replace('sandbox/', 'production/', $endpoint);
					}

					$this->debug('Mypost Business rate REQUEST: <pre>' . print_r($rates_request_body, true) . '</pre>');
					$auspost_service_rates_for_package = $this->get_contracted_rates($endpoint, $rates_request_body, $headers);
					
					if (empty($auspost_service_rates_for_package)) {
						return;
					}

					if (  isset($auspost_service_rates_for_package) && !empty($auspost_service_rates_for_package)) {
						$auspost_service_rates = $auspost_service_rates_for_package;
						$this->debug('Mypost Business rate RESPONSE: <pre>' . print_r($auspost_service_rates_for_package, true) . '</pre>');
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
								$this->prepare_rate($key, $key, $items_product_type, $rate_include_gst, $package_requests );
							}
						}
					}
				}

				$all_service_rates = $auspost_service_rates;
				$cheapest          = '';
				if (!empty($this->found_rates)) {

					$adjustment            = 0;
					$adjustment_percentage = 0;
					foreach ($this->found_rates as $rate) {
						$rate['enabled'] = false;
						foreach ($settings_services as $key => $settings_service) {
							if ($rate['id'] === $key ) {
								if ($settings_service['enabled'] == true) {

									if (!empty($settings_service['name'])) {
										$rate['label'] = $settings_service['name'];
									}

									$add_extra_cover = false;
									if (is_shop() || is_cart()) {

										if (isset($settings_service['extra_cover']) && ( $settings_service['extra_cover'] == true )) {
											$add_extra_cover = true;
										}
									} elseif ( ( $this->insurance_requested_at_checkout ) || ( isset($settings_service['extra_cover']) && $settings_service['extra_cover'] == true ) ) {
										$add_extra_cover = true;
									}

									if ($add_extra_cover) {
										$rate['cost'] += $total_extra_cover;
									}

									$add_signature_required = false;
									if (is_shop() || is_cart()) {

										if (isset($settings_service['signature_on_delivery_option']) && ( $settings_service['signature_on_delivery_option'] == true )) {
											$add_signature_required = true;
										}
									} elseif ( ( $this->signature_requested_at_checkout ) || ( isset($settings_service['signature_on_delivery_option']) && $settings_service['signature_on_delivery_option'] == true ) ) {
										$add_signature_required = true;
									}

									if ($add_signature_required) {
										if ($this->is_international) {
										
											$rate['cost'] += $this->sod_cost['international']*$count_package_requests;

										} else {

											$rate['cost'] += $this->sod_cost['domestic']*$count_package_requests;
										}
									} else {
										$rate['cost'] += $total_sod_cost;
									}

									if (is_object($all_service_rates)) {
										$all_service_rates = (array) $all_service_rates;
									}

									if (isset($settings_service['adjustment']) && ''!== $settings_service['adjustment'] ) {
										$adjustment = (float) $settings_service['adjustment'];
									}

									if (isset($settings_service['adjustment_percent']) && ''!== $settings_service['adjustment_percent'] ) {
										$adjustment_percentage = (float) $rate['cost'] * ( (float) $settings_service['adjustment_percent'] / 100 );
									}
									$rate['cost']   += (float) ( ( $adjustment == 0 ) ? 0 : $adjustment );
									$rate['cost']   += (float) ( ( $adjustment_percentage == 0 ) ? 0 : $adjustment_percentage );
									$rate['enabled'] = true;
	
									$this->found_rates[$rate['id']]['cost'] = $rate['cost'];
									$this->previous_rate_cost_stored        = get_option('rate_cost_' . $rate['id'] . '_auspost_elex');
									
									update_option('rate_cost_' . $rate['id'] . '_auspost_elex', $rate['cost']);

									$adjustment            = 0;
									$adjustment_percentage = 0;
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
							if (( $this->settings['enabled'] == 'yes' ) && $rate['enabled'] == true) {
								$rate['id'] = $this->id . ':' . $rate['id'];

								$this->add_rate($rate);
								if ($count_package_requests == $package_requests_size) {
									$convention_rate_id = str_replace($this->id . ':', '', $rate['id']);
									update_option('rate_cost_' . $convention_rate_id . '_auspost_elex', 0);
								}
							}
						}
					
					}
					
					if ($this->settings['enabled'] && $this->offer_rates != 'all') {
						$cheapest['id']              = $this->id . ':' . $cheapest['id'];
						$cheapest_convension_rate_id = str_replace($this->id . ':', '', $cheapest['id']);
						$this->add_rate($cheapest);
						update_option('rate_cost_' . $cheapest_convension_rate_id . '_auspost_elex', 0);
					}
				}
			
			}
			return;
		}
		// Set transient
		set_transient('wf_australia_post_quotes', $this->rate_cache, YEAR_IN_SECONDS);

		// Add rates
		if ($this->found_rates) {
			$all_services = array();
			if (isset($this->settings['services']) && !empty($this->settings['services'])) {
				$all_services = $this->settings['services'];
			}

			if ($this->offer_rates == 'all') {
				uasort($this->found_rates, array($this, 'sort_rates'));
				foreach ($this->found_rates as $key => $rate) {
					$service_name = str_replace('wf_australia_post:', '', $key);
					$actual_code  = $rate['actual_code'];
					if (!empty($all_services)) {
						foreach ($all_services as $service_key => $service) {
							if (strpos($service_name, 'REGULAR_SATCHEL')) {
								$rate['label'] = $service['name'];
							}
							if (strpos($service_name, 'EXPRESS_SATCHEL')) {
								$rate['label'] = $service['name'];
							} elseif ($service_key === $service_name) {
								if (isset($service['name']) && !empty($service['name'])) {
									$rate['label'] = $service['name'];
								}
							}

							if (isset($custom_services[$service_name])) {
								if ($this->settings['enabled'] && $custom_services[$service_name]['enabled']) {
									$this->add_rate($rate);
									$this->found_rates[$key]['cost'] = $rate['cost'];
									update_option('rate_cost_' . $rate['id'] . 'ncr', 0);
								}
							} elseif (isset($sub_services[$actual_code])) {
								if ($this->settings['enabled'] && $sub_services[$actual_code]['enabled']) {
									$this->add_rate($rate);
									$this->found_rates[$key]['cost'] = $rate['cost'];
									update_option('rate_cost_' . $rate['id'] . 'ncr', 0);
								}
							}
						}
					}
				}
			} else {

				$cheapest_rate = '';
				foreach ($this->found_rates as $key => $rate) {
					$service_name = str_replace('wf_australia_post:', '', $key);
					$actual_code  = $rate['actual_code'];
					if (isset($custom_services[$service_name])  && $custom_services[$service_name]['enabled'] && ( !$cheapest_rate || $cheapest_rate['cost'] > $rate['cost'] )) {
								$cheapest_rate = $rate;
						
					} elseif (isset($sub_services[$actual_code]) && $sub_services[$actual_code]['enabled'] && ( !$cheapest_rate || $cheapest_rate['cost'] > $rate['cost'] )) {
								$cheapest_rate = $rate;
					}
					update_option('rate_cost_' . $rate['id'] . 'ncr', 0);
					
				}

				$cheapest_rate['label'] = $this->title;

				if ($this->settings['enabled']) {
					$this->add_rate($cheapest_rate);
					//                    $this->found_rates[$key]['cost'] = $cheapest_rate['cost'];
					update_option('rate_cost_' . $cheapest_rate['id'] . 'ncr', 0);
				}
			}
		}
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
	 * prepare rate function.
	 *
	 * @access private
	 * @param mixed $rate_code
	 * @param mixed $rate_id
	 * @param mixed $rate_name
	 * @param mixed $rate_cost
	 * @return void
	 */
	private function prepare_rate( $rate_code, $rate_id, $rate_name, $rate_cost, $package_request = '') {

		$rate_actual_code = $rate_code;

		if (!empty($this->custom_services[$rate_code])) {
			$this->custom_services[$rate_code] = apply_filters('wf_australia_post_rate_services', $this->custom_services, $this->custom_services[$rate_code], $rate_code, $package_request);
		}

		// Name adjustment
		$main_service_rate_code = $rate_code;        
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
			'label' => $rate_name . ' (' . $this->title . ')',
			'cost' => $rate_cost,
			'sort' => $sort,
			'packages' => $packages,
			'actual_code' => $rate_actual_code
		);

	}

	/**
	 * sort_rates function.
	 *
	 * @access public
	 * @param mixed $a
	 * @param mixed $b
	 * @return void
	 */
	public function sort_rates( $a, $b) {
		if ($a['sort'] == $b['sort']) {
			return 0;
		}
		return ( $a['sort'] < $b['sort'] ) ? -1 : 1;
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
	 * get_request function.
	 *
	 * @access private
	 * @return void
	 */
	private function get_package_requests( $package) {
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
				break;
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
	private function get_composite_product_data( $package) {
		$package_composite_products_data = array();
		$shipping_package                = array();

		foreach ($package['contents'] as $item_id => $values) {
			if (!empty($values['data']->get_weight()) && !empty($values['data']->get_length()) && !empty($values['data']->get_width()) && !empty($values['data']->get_height())) {
				if (!empty($item_id)) {
					$shipping_package['contents'][$item_id] = $values ;
				}
			} else {
				$components_id_array       = array();
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
					$composite_product_data                   = $values['data'];
					$composite_product_id                     = $composite_product_data->get_id();
					$components_id_array['parent_product_id'] = $composite_product_id;

					$package_composite_products_data[$item_id] = $components_id_array;
				}
			}
		}

		if (!empty($package_composite_products_data)) {
			$package_composite_products_data = $this->composite_data_unique(array_shift($package_composite_products_data));
			foreach ($package_composite_products_data as $package_composite_products_datum) {
				$composite_product_id = isset($package_composite_products_datum['variation_id']) ? $package_composite_products_datum['variation_id'] : $package_composite_products_datum['product_id'];
				$composite_product    = wc_get_product($composite_product_id);
				if (!empty($composite_product_id)) {
					$shipping_package['contents'][$composite_product_id]         = $package_composite_products_datum;
					$shipping_package['contents'][$composite_product_id]['data'] = $composite_product;
				}
			}
		}
		$package['contents'] = $shipping_package['contents'];

		return $package;
	}

	private function composite_data_unique( $package_composite_products_data) {
		$composite_data_unique = array();
		foreach ($package_composite_products_data as $package_composite_products_datum_key => $package_composite_products_datum) {
			if (empty($composite_data_unique)) {
				$composite_data_unique[] = $package_composite_products_datum;
			} else {
				$found = false;
				foreach ($composite_data_unique as $composite_data_element_key => $composite_data_element) {
					if ($composite_data_element['product_id'] == $package_composite_products_datum_key) {
						$composite_data_unique['quantity'] += 1;
						$found                              = true;
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
	private function weight_only_shipping( $package) {
		global $woocommerce;
		if (!class_exists('Elex_Weight_Boxpack')) {
			include_once 'class-wf-weight-packing.php';
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

		$ctr = 0;

		foreach ($package['contents'] as $item_id => $values) {

			$ctr++;

			$skip_product = apply_filters('wf_shipping_skip_product', false, $values, $package['contents']);
			if ($skip_product) {
				continue;
			}

			if (!( $values['quantity'] > 0 && $values['data']->needs_shipping() )) {

				$this->debug(sprintf(__('Product #%d is virtual. Skipping.', 'wf-australia-post'), $ctr));

				continue;
			}

			if (!$values['data']->get_weight()) {

				$this->debug(sprintf(__('Product #%d is missing weight.', 'wf-australia-post'), $ctr), 'error');

				return;

			}

			for ($i = 1; $i <= $values['quantity']; $i++) {
				$weight_pack->add_item(wc_get_weight( $values['data']->get_weight(), $this->weight_unit ), array( 'data'=> $values['data'] ), $values['quantity'] , $values['data']->get_price());
			}

		//	$weight_pack->add_item(wc_get_weight( $values['data']->get_weight(), $this->weight_unit ), array( 'data'=> $values['data'] ), $values['quantity'],$values['data']->get_price());
		}

		$weight_pack->pack(); 
		$pack     = $weight_pack->get_packages(); 
		$to_ship  = array();
		$group_id = 1;
		foreach ($pack as $package) {
			if ($package->unpacked === true) {
				$this->debug('Item not packed in any box');
				return array();
			} else {
				$this->debug('Packed ' . $package->name);
			}
			$dimensions = array($package->length, $package->width, $package->height);

			sort($dimensions);
			$insurance_array = array(
				'Amount' => round($package->value),
				'Currency' => get_woocommerce_currency()
			);
			
			$group = array(
				'GroupNumber' => $group_id,
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
	 * per_item_shipping function
	 *
	 * @access private
	 * @param mixed $package
	 * @return void
	 */
	private function per_item_shipping( $package) {
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
			$girth = ( round(wc_get_dimension($dimensions[0], 'cm', $from_dimension_unit)) + round(wc_get_dimension($dimensions[1], 'cm', $from_dimension_unit)) ) * 2;

			$parcel_weight = wc_get_weight($parcel['weight'], 'kg', $this->weight_unit);


			// Allowed maximum volume of a product is 0.25 cubic meters for domestic shipments
			if ($domestic == 'yes' && $parcel_volume > 0.25) {
				$this->debug(sprintf(__('Product %s exceeds 0.25 cubic meters Aborting. See <a href="https://auspost.com.au/sending/check-sending-guidelines/size-weight-guidelines">https://auspost.com.au/sending/check-sending-guidelines/size-weight-guidelines</a>', 'wf-shipping-auspost'), $values['data']->get_name()), 'error');
				return;
			}

			// The girth should lie between 16cm and 140cm for international shipments
			if ($domestic == 'no' && ( $girth < 16 || $girth > 140 )) {
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
					'Length' => round($dimensions[2], 2),
					'Width' => round($dimensions[1], 2),
					'Height' => round($dimensions[0], 2),
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
	private function filter_boxes_for_satchels( $box, $box_pack) {
		$this->pre_defined_boxes = include 'settings/wf_auspost_predefined_boxes.php';
		$box_name                = $box['name'];

		if (isset($this->pre_defined_boxes[$box_name]['eligible_for']) && $this->pre_defined_boxes[$box_name]['name'] == $box_name ) {
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
	private function box_shipping( $package) {
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
		$stored_pre_defined_boxes = get_option('ausmypost_stored_pre_defined_boxes');
		$stored_custom_boxes      = get_option('ausmypost_stored_custom_boxes');
		$stored_auspost_boxes     = array();

		$stored_auspost_boxes = array_merge($stored_pre_defined_boxes, $stored_custom_boxes);
		
		$boxes_for_packing = $stored_auspost_boxes;

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

				if (isset($box['is_letter'])) {
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

		$packages         = $boxpack->get_packages();
		$not_packed_items = $boxpack->get_cannot_pack();

		$to_ship = array();

		// To show unpacked items
		if (!empty($not_packed_items) && is_array($not_packed_items)) {
			foreach ($not_packed_items as $not_packed_item) {
				$not_packed_meta_data = $not_packed_item->get_meta('data');
				$not_packed_item_data = $not_packed_meta_data->get_data();
				$not_packed_item      = $not_packed_item_data['name'];
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

				if ($package_type != 'letter') {
					sort($dimensions);
				}

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
	private function wf_load_product( $product) {
		if (!$product) {
			return false;
		}
		return ( WC()->version < '2.7.0' ) ? $product : new wf_product($product);
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

			$error_string = $res->get_error_message();
			$this->debug($error_string, 'error');
			return array();
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
					$errors_message = $response_array->errors[0]->message;
					$this->debug($errors_message, 'error');
					return array();
				} else {
					$errors_message = $response_array->message;
					$this->debug($errors_message, 'error');
					return array();
				}
			}
		}
		return $shipment_rates_result;
	}
}
new wf_australia_mypost_shipping();
