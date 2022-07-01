<?php
/**
 * Admin End Functions
 *
 * @package Purchase Order WooCommerce
 * @since 1.0.0
 */

namespace WkPurchaseOrder\Includes\Admin;

use WkPurchaseOrder\Templates\Admin;
use WkPurchaseOrder\Helper;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WCPO_Admin_Functions' ) ) {
	/**
	 * Admin functions class
	 */
	class WCPO_Admin_Functions {
		/**
		 * Template handler
		 *
		 * @var $template_handler
		 */
		protected $template_handler;

		/**
		 * Admin Functions Construct
		 *
		 * @return void
		 */
		public function __construct() {
			$this->template_handler = new Admin\WCPO_Admin_Templates();
		}

		/**
		 * Register Option settings
		 *
		 * @return void
		 */
		public function wcpo_register_settings() {
			register_setting( 'wcpo-settings-group', 'wcpo_status' );
			register_setting( 'wcpo-settings-group', 'wcpo_procurement_type' );
			register_setting( 'wcpo-settings-group', 'wcpo_order_prefix' );
		}

		/**
		 * Admin menu callback
		 */
		public function wcpo_admin_menu() {
			add_menu_page( esc_html__( 'Test ', 'wk-purchase-order' ), esc_html__( 'Test', 'wk-purchase-order' ), 'manage_options', 'wk-purchase-management', array( $this, 'wcpo_suppliers_menu' ), 'dashicons-clipboard', 59 );

			$quotations = add_submenu_page( 'wk-purchase-management', esc_html__( 'Create Purchase Order', 'wk-purchase-order' ), esc_html__( 'Create Purchase Order', 'wk-purchase-order' ), 'manage_options', 'wk-purchase-management', array( $this, 'wcpo_quotation_menu' ) );

			$po = add_submenu_page( 'wk-purchase-management', esc_html__( 'Purchase Order', 'wk-purchase-order' ), esc_html__( 'Purchase Order', 'wk-purchase-order' ), 'manage_options', 'wk-purchase-order', array( $this, 'wcpo_purchase_order_menu' ) );

			add_submenu_page( 'wk-purchase-management', esc_html__( 'Incoming Shipments', 'wk-purchase-order' ), esc_html__( 'Incoming Shipments', 'wk-purchase-order' ), 'manage_options', 'wk-incoming-shipments', array( $this, 'wcpo_incoming_shipment_menu' ) );

			$suppliers = add_submenu_page( 'wk-purchase-management', esc_html__( 'Purchase Management', 'wk-purchase-order' ), esc_html__( 'Manage Suppliers', 'wk-purchase-order' ), 'manage_options', 'wk-suppliers-management', array( $this, 'wcpo_suppliers_menu' ) );

			add_submenu_page( 'wk-purchase-management', esc_html__( 'Configuration', 'wk-purchase-order' ), esc_html__( 'Configuration', 'wk-purchase-order' ), 'manage_options', 'wk-purchase-management-config', array( $this, 'wcpo_purchase_order_configuration' ) );

			do_action( 'wcpo_admin_menu_action' );

			add_action( "load-{$po}", array( $this, 'wcpo_po_screen_option' ) );
			add_action( "load-{$suppliers}", array( $this, 'wcpo_suppliers_screen_option' ) );
			add_action( "load-{$quotations}", array( $this, 'wcpo_quotations_screen_option' ) );
		}

		/**
		 * Supplier list screen option
		 *
		 * @return void
		 */
		public function wcpo_po_screen_option() {
			$option = 'per_page';
			$args   = array(
				'label'   => esc_html__( 'PO per page', 'wk-purchase-order' ),
				'default' => 10,
				'option'  => 'po_per_page',
			);

			add_screen_option( $option, $args );
		}

		/**
		 * Supplier list screen option
		 *
		 * @return void
		 */
		public function wcpo_suppliers_screen_option() {
			$option = 'per_page';
			$args   = array(
				'label'   => esc_html__( 'Suppliers per page', 'wk-purchase-order' ),
				'default' => 10,
				'option'  => 'suppliers_per_page',
			);

			add_screen_option( $option, $args );
		}

		/**
		 * Quotation list screen option
		 *
		 * @return void
		 */
		public function wcpo_quotations_screen_option() {
			$option = 'per_page';
			$args   = array(
				'label'   => esc_html__( 'Quotations per page', 'wk-purchase-order' ),
				'default' => 10,
				'option'  => 'quotations_per_page',
			);

			add_screen_option( $option, $args );
		}

		/**
		 * Screen
		 *
		 * @param string  $status Status.
		 * @param string  $option Option Name.
		 * @param integer $value Option Value.
		 * @return $value
		 */
		public function wcpo_set_screen( $status, $option, $value ) {
			$options = [ 'suppliers_per_page', 'quotations_per_page', 'po_per_page' ];
			if ( in_array( $option, $options, true ) ) {
				return $value;
			}
			return $status;
		}

		/**
		 * Purchase Order Suppliers
		 *
		 * @return void
		 */
		public function wcpo_suppliers_menu() {
			if ( isset( $_GET['page'] ) && 'wk-suppliers-management' === $_GET['page'] ) { // WPCS: CSRF ok; WPCS: input var ok.
				if ( isset( $_GET['action'] ) && 'add' === $_GET['action'] ) { // WPCS: CSRF ok; WPCS: input var ok.
					$this->template_handler->wcpo_add_supplier_html();
				} elseif ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['supplier-id'] ) && ! empty( $_GET['supplier-id'] ) ) { // WPCS: CSRF ok; WPCS: input var ok.
					$supplier_id = intval( sanitize_key( $_GET['supplier-id'] ) ); // WPCS: CSRF ok; WPCS: input var ok.
					$this->template_handler->wcpo_add_supplier_html( $supplier_id );
				} elseif ( isset( $_GET['action'] ) && 'manage' === $_GET['action'] && isset( $_GET['supplier-id'] ) && ! empty( $_GET['supplier-id'] ) ) { // WPCS: CSRF ok; WPCS: input var ok.
					$this->template_handler->wcpo_manage_supplier_html();
				} else {
					$this->template_handler->wcpo_supplier_list_html();
				}
			}
		}

		/**
		 * Quotation Management
		 *
		 * @return void
		 */
		public function wcpo_quotation_menu() {
			if ( isset( $_GET['page'] ) && 'wk-purchase-management' === $_GET['page'] ) { // WPCS: CSRF ok; WPCS: input var ok.
				if ( isset( $_GET['action'] ) && 'add' === $_GET['action'] ) { // WPCS: CSRF ok; WPCS: input var ok.
					$this->template_handler->wcpo_add_quotation_html();
				} elseif ( isset( $_GET['action'] ) && 'manage' === $_GET['action'] && isset( $_GET['quotation-id'] ) && ! empty( $_GET['quotation-id'] ) ) { // WPCS: CSRF ok; WPCS: input var ok.
					$quotation_id = intval( sanitize_key( $_GET['quotation-id'] ) ); // WPCS: CSRF ok; WPCS: input var ok.
					$this->template_handler->wcpo_edit_quotation_html( $quotation_id );
				} else {
					$this->template_handler->wcpo_quotation_html();
				}
			}
		}

		/**
		 * Purchase order menu callback
		 *
		 * @return void
		 */
		public function wcpo_purchase_order_menu() {
			if ( isset( $_GET['page'] ) && 'wk-purchase-order' === $_GET['page'] ) { // WPCS: CSRF ok; WPCS: input var ok.
				if ( isset( $_GET['action'] ) && 'manage' === $_GET['action'] && isset( $_GET['po-id'] ) && ! empty( $_GET['po-id'] ) ) { // WPCS: CSRF ok; WPCS: input var ok.
					$po_id = intval( sanitize_key( $_GET['po-id'] ) ); // WPCS: CSRF ok; WPCS: input var ok.
					$this->template_handler->wcpo_view_purchase_order( $po_id );
				} else {
					$this->template_handler->wcpo_purchase_order_html();
				}
			}
		}

		/**
		 * Incoming shipments Menu
		 *
		 * @return void
		 */
		public function wcpo_incoming_shipment_menu() {
			if ( isset( $_GET['page'] ) && 'wk-incoming-shipments' === $_GET['page'] ) { // WPCS: CSRF ok; WPCS: input var ok.
				if ( isset( $_GET['action'] ) && 'manage' === $_GET['action'] && isset( $_GET['shipment-id'] ) && ! empty( $_GET['shipment-id'] ) ) { // WPCS: CSRF ok; WPCS: input var ok.
					$shipment_id = intval( sanitize_key( $_GET['shipment-id'] ) ); // WPCS: CSRF ok; WPCS: input var ok.
					$this->template_handler->wcpo_manage_incoming_shipment( $shipment_id );
				} else {
					$this->template_handler->wcpo_incoming_shipment_html();
				}
			}
		}

		/**
		 * Purchase Order Configuration
		 *
		 * @return void
		 */
		public function wcpo_purchase_order_configuration() {
			$this->template_handler->wcpo_configuration_html();
		}

		/**
		 * Admin end scripts
		 *
		 * @return void
		 */
		public function wcpo_admin_scripts() {
			wp_enqueue_style( 'wcpo-style', WCPO_PLUGIN_URL . 'assets/css/admin.css', array(), WCPO_SCRIPT_VERSION );
			wp_enqueue_script( 'wcpo-script', WCPO_PLUGIN_URL . 'assets/js/admin.js', array( 'select2' ), WCPO_SCRIPT_VERSION );

			$ajax_obj = array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'ajaxNonce' => wp_create_nonce( 'wcpo-nonce' ),
			);

			wp_localize_script( 'wcpo-script', 'wcpoObj', array(
				'ajax' => $ajax_obj,
				'statusKeywords'    => [
					'success'       => esc_html__( 'Success !', 'wk-purchase-order' ),
					'error'         => esc_html__( 'Error !', 'wk-purchase-order' ),
				],
				'confirmMsg'        => esc_html__( 'Are you sure, want to delete the supplier from this product ? It may harm quotation/purchase order data.', 'wk-purchase-order' ),
				'commonConfirmMsg'  => esc_html__( 'Are you sure ?', 'wk-purchase-order' ),
			) );
		}

		/**
		 * Set screen ids
		 *
		 * @param array $ids IDs.
		 * @return array
		 */
		public function wcpo_set_wc_screen_ids( $ids ) {
			array_push( $ids, 'toplevel_page_wk-purchase-management', 'purchase-management_page_wk-purchase-management-config', 'purchase-management_page_wk-suppliers-management' );
			return $ids;
		}

		/**
		 * Create supplier callback
		 *
		 * @param array $data Data Array.
		 * @return void
		 */
		public function wcpo_create_supplier( $data ) {
			if ( $data ) {
				global $wpdb;
				$table = $wpdb->prefix . 'wcpo_suppliers';

				$name      = sanitize_text_field( wp_unslash( $data['supplier_name'] ) );
				$email     = sanitize_text_field( wp_unslash( $data['supplier_email'] ) );
				$company   = sanitize_text_field( wp_unslash( $data['supplier_company'] ) );
				$vat       = sanitize_text_field( wp_unslash( $data['supplier_vat'] ) );
				$website   = sanitize_text_field( wp_unslash( $data['supplier_website'] ) );
				$gender    = sanitize_text_field( wp_unslash( $data['supplier_gender'] ) );
				$address_1 = sanitize_text_field( wp_unslash( $data['supplier_address_1'] ) );
				$address_2 = sanitize_text_field( wp_unslash( $data['supplier_address_2'] ) );
				$city      = sanitize_text_field( wp_unslash( $data['supplier_city'] ) );
				$state     = sanitize_text_field( wp_unslash( $data['supplier_state'] ) );
				$country   = sanitize_text_field( wp_unslash( $data['supplier_country'] ) );
				$zip       = sanitize_text_field( wp_unslash( $data['supplier_zip'] ) );
				$telephone = sanitize_text_field( wp_unslash( $data['supplier_telephone'] ) );
				$supplier_id = sanitize_text_field( wp_unslash( $data['supplier_id'] ) );

				if ( ! $name || ! $email || ! $company || ! $vat || ! $website || ! $gender || ! $address_1 || ! $address_2 || ! $city || ! $state || ! $country || ! $zip || ! $telephone ) {
					?>
					<div class='notice notice-error is-dismissible'>
						<p><?php esc_html_e( 'Fill all required fields.', 'wk-purchase-order' ); ?></p>
					</div>
					<?php
				} else {
					if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
						?>
						<div class='notice notice-error is-dismissible'>
							<p><?php esc_html_e( 'Enter valid email.', 'wk-purchase-order' ); ?></p>
						</div>
						<?php
					} elseif ( ! filter_var( $website, FILTER_VALIDATE_URL ) ) {
						?>
						<div class='notice notice-error is-dismissible'>
							<p><?php esc_html_e( 'Enter valid website.', 'wk-purchase-order' ); ?></p>
						</div>
						<?php
					} else {
						$email_query = $wpdb->get_var( $wpdb->prepare( "SELECT count(*) from $table where email = %s", $email ) ); // WPCS: cache ok; WPCS: db call ok; WPCS: unprepared SQL ok.

						$email_query = intval( $email_query );

						if ( 0 === $email_query ) {
							$sql = $wpdb->insert(
								$table,
								array(
									'name'        => $name,
									'email'       => $email,
									'company'     => $company,
									'vat'         => $vat,
									'website'     => $website,
									'gender'      => $gender,
									'street_1'    => $address_1,
									'street_2'    => $address_2,
									'city'        => $city,
									'state'       => $state,
									'country'     => $country,
									'postal_code' => $zip,
									'telephone'   => $telephone,
								),
								array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
							);

							if ( $sql ) {
								$new_supplier_id = $wpdb->insert_id;
								wp_safe_redirect( admin_url( 'admin.php?page=wk-suppliers-management&action=manage&created=1&supplier-id=' . esc_attr( $new_supplier_id ) ) );
								exit;
							} else {
								?>
								<div class='notice notice-error is-dismissible'>
									<p><?php esc_html_e( 'Error creating supplier, try again.', 'wk-purchase-order' ); ?></p>
								</div>
								<?php
							}
						} else {
							if ( $supplier_id ) {
								$sql = $wpdb->update(
									$table,
									array(
										'name'        => $name,
										'email'       => $email,
										'company'     => $company,
										'vat'         => $vat,
										'website'     => $website,
										'gender'      => $gender,
										'street_1'    => $address_1,
										'street_2'    => $address_2,
										'city'        => $city,
										'state'       => $state,
										'country'     => $country,
										'postal_code' => $zip,
										'telephone'   => $telephone,
									),
									array(
										'id'          => $supplier_id,
									),
									array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
									array( '%d' )
								);
								if ( $sql ) {
									wp_safe_redirect( admin_url( 'admin.php?page=wk-suppliers-management&action=manage&updated=1&supplier-id=' . esc_attr( $supplier_id ) ) );
									exit;
								} else {
									?>
									<div class='notice notice-error is-dismissible'>
										<p><?php esc_html_e( 'Error updating supplier, try again.', 'wk-purchase-order' ); ?></p>
									</div>
									<?php
								}
							} else {
								?>
								<div class='notice notice-error is-dismissible'>
									<p><?php esc_html_e( 'Email already exists.', 'wk-purchase-order' ); ?></p>
								</div>
								<?php
							}
						}
					}
				}
			}
		}

		/**
		 * Product edit custom tabs
		 *
		 * @param array $default_tabs tabs.
		 * @return array
		 */
		public function wcpo_product_edit_tab( $default_tabs ) {
			global $post;
			$tabs = array();
			$product = wc_get_product( $post->ID );
			$screen = get_current_screen();

			if ( empty( $screen->action ) ) {
				if ( in_array( $product->get_type(), [ 'simple', 'variable' ], true ) ) {
					$tabs = array(
						'wcpo_supplier' => array(
							'label'     => esc_html__( 'Suppliers', 'wk-purchase-order' ),
							'target'    => 'wcpo_product_suppliers',
							'priority'  => 60,
							'class'     => array( 'hide_if_virtual', 'hide_if_grouped', 'hide_if_external', 'hide_if_downloadable' ),
						),
					);
					$default_tabs = array_merge( $default_tabs, $tabs );
				}
				if ( 'simple' === $product->get_type() ) {
					$tabs = array(
						'wcpo_price'    => array(
							'label'     => esc_html__( 'Prices', 'wk-purchase-order' ),
							'target'    => 'wcpo_product_prices',
							'priority'  => 60,
							'class'     => array( 'hide_if_virtual', 'hide_if_grouped', 'hide_if_external', 'hide_if_downloadable' ),
						),
					);
					$default_tabs = array_merge( $default_tabs, $tabs );
				}
			}

			return $default_tabs;
		}

		/**
		 * Product prices tab data
		 *
		 * @return void
		 */
		public function wcpo_product_prices_tab_data() {
			$this->template_handler->wcpo_product_prices_tab_html();
		}

		/**
		 * Suppliers add template in variations
		 *
		 * @param string $loop Loop.
		 * @param array  $variation_data Data.
		 * @param array  $variation Variation.
		 * @return void
		 */
		public function wcpo_variation_settings_fields( $loop, $variation_data, $variation ) {
			$this->template_handler->wcpo_variation_prices_tab_html( $loop, $variation_data, $variation );
		}

		/**
		 * Save product supplier data
		 *
		 * @param int $product_id id.
		 * @return void
		 */
		public function wcpo_save_product_supplier_data( $product_id ) {
			if ( $product_id ) {
				$cost = isset( $_POST['_wcpo_cost'] ) ? sanitize_text_field( wp_unslash( $_POST['_wcpo_cost'] ) ) : ''; // WPCS: CSRF ok; WPCS: input var ok; WPCS: XSS ok; WPCS: sanitization ok.
				$method = isset( $_POST['_wcpo_ptype'] ) ? $_POST['_wcpo_ptype'] : ''; // WPCS: CSRF ok; WPCS: input var ok; WPCS: XSS ok; WPCS: sanitization ok.

				update_post_meta( $product_id, '_wcpo_cost', $cost );
				update_post_meta( $product_id, '_procurement_method', $method );

				$ps_table = 'wcpo_product_suppliers';
				$helper = new Helper\WCPO_Supplier_Data();
				$ps_table_exists = $helper->wcpo_table_exists( $ps_table );

				if ( $ps_table_exists ) {
					$query = $helper->wcpo_add_product_suppliers_data( $_POST, $product_id ); // WPCS: CSRF ok; WPCS: input var ok.
					if ( $query ) {
						foreach ( $query['error'] as $key => $value ) {
							?>
							<div class='notice notice-<?php echo esc_attr( $value['type'] ); ?> is-dismissible'>
								<p><?php echo esc_attr( $value['message'] ); ?></p>
							</div>
							<?php
						}
					}
				} else {
					?>
					<div class='notice notice-success is-dismissible'>
						<p><?php esc_html_e( 'Product suppliers table doesn\'t exist.', 'wk-purchase-order' ); ?></p>
					</div>
					<?php
				}
			}
		}

		/**
		 * Save variations cost data
		 *
		 * @param int $variation_id id.
		 * @param int $i Key.
		 * @return void
		 */
		public function wcpo_save_variation_supplier_data( $variation_id, $i ) {
			if ( isset( $_POST['variable_post_id'] ) && ! empty( $_POST['variable_post_id'] ) ) { // WPCS: CSRF ok; WPCS: input var ok; WPCS: XSS ok; WPCS: sanitization ok.
				$variation_ids = array_map( 'sanitize_key', $_POST['variable_post_id'] ); // WPCS: CSRF ok; WPCS: input var ok; WPCS: XSS ok.

				foreach ( $variation_ids as $key => $value ) {
					if ( isset( $_POST['_wcpo_cost'][ $key ] ) && isset( $_POST['_wcpo_ptype'][ $key ] ) ) { // WPCS: CSRF ok; WPCS: input var ok; WPCS: XSS ok; WPCS: sanitization ok.
						update_post_meta( $value, '_wcpo_cost', $_POST['_wcpo_cost'][ $key ] ); // WPCS: CSRF ok; WPCS: input var ok; WPCS: XSS ok; WPCS: sanitization ok.
						update_post_meta( $value, '_procurement_method', $_POST['_wcpo_ptype'][ $key ] ); // WPCS: CSRF ok; WPCS: input var ok; WPCS: XSS ok; WPCS: sanitization ok.
					}
				}
			}
		}

		/**
		 * Traverse items array
		 *
		 * @param int $item item.
		 * @param int $key key.
		 * @return array
		 */
		public function wcpo_traverse_items_array( $item, $key ) {
			if ( $item < 0 ) {
				$item = 0;
			}
			$cost = get_post_meta( $key, '_wcpo_cost', true );
			return [
				'id'   => $key,
				'qty'  => $item,
				'cost' => $item ? ( $cost * $item ) : 0,
			];
		}

		/**
		 * Save quotation callback
		 *
		 * @param array $data Data.
		 * @return void
		 */
		public function wcpo_save_quotation( $data ) {
			if ( $data ) {
				$quotation_id = '';
				$source = sanitize_text_field( wp_unslash( $data['wcpo_quotation_source'] ) );
				$supplier = sanitize_text_field( wp_unslash( $data['wcpo_quptation_supplier'] ) );
				$items = isset( $data['wcpo_item_qty'] ) ? array_map( 'sanitize_text_field', wp_unslash( $data['wcpo_item_qty'] ) ) : '';

				if ( ! $supplier || ! $items ) {
					?>
					<div class='notice notice-error is-dismissible'>
						<p><?php esc_html_e( 'Fill all required fields.', 'wk-purchase-order' ); ?></p>
					</div>
					<?php
				} else {
					$quotation_table = 'wcpo_quotations';
					$helper = new Helper\WCPO_Supplier_Data();
					$table_exists = $helper->wcpo_table_exists( $quotation_table );
					$items = array_map( array( $this, 'wcpo_traverse_items_array' ), $items, array_keys( $items ) );
					$items_total = array_sum( array_column( $items, 'cost' ) );

					if ( $table_exists ) {
						$status = 'new';
						$source = $source ? $source : esc_html__( 'MANUAL', 'wk-purchase-order' );
						$quotation_id = $helper->wcpo_save_quotation_data( $source, $supplier, $items, $status, $items_total );

						if ( $quotation_id ) {
							wp_safe_redirect( admin_url( 'admin.php?page=wk-purchase-management&created=1' ) );
							exit;
						} else {
							?>
							<div class='notice notice-error is-dismissible'>
								<p><?php esc_html_e( 'Error in creating quotation.', 'wk-purchase-order' ); ?></p>
							</div>
							<?php
						}
					} else {
						?>
						<div class='notice notice-error is-dismissible'>
							<p><?php esc_html_e( 'Quotation table doesn\'t exist.', 'wk-purchase-order' ); ?></p>
						</div>
						<?php
					}
				}
			}
		}

		/**
		 * Traverse items array
		 *
		 * @param int   $item Item.
		 * @param int   $key key.
		 * @param array $items Item.
		 * @return $item
		 */
		public function wcpo_traverse_updated_items( $item, $key, $items ) {
			if ( $item < 0 ) {
				$item = 0;
			}

			$cost = $items['qty'] ? ( $items['cost'] / $items['qty'] ) : get_post_meta( $key, '_wcpo_cost', true );

			return [
				'id'   => $key,
				'qty'  => intval( $item ),
				'cost' => $item ? ( $cost * $item ) : 0,
			];
		}

		/**
		 * Filter Items Array
		 *
		 * @param array $item Items.
		 * @return $item
		 */
		public function wcpo_filter_updated_items( $item ) {
			if ( NULL === $item['id'] ) {
				return '';
			} else {
				return $item;
			}
		}

		/**
		 * Update quotation callback
		 *
		 * @param array $data Data.
		 * @return void
		 */
		public function wcpo_update_quotation( $data ) {
			if ( $data ) {
				$items = isset( $data['wcpo_items'] ) ? array_map( 'sanitize_text_field', wp_unslash( $data['wcpo_items'] ) ) : '';
				if ( ! $items ) {
					?>
					<div class='notice notice-error is-dismissible'>
						<p><?php esc_html_e( 'Quantity is required field.', 'wk-purchase-order' ); ?></p>
					</div>
					<?php
				} else {
					$quotation_table = 'wcpo_quotations';
					$quotation_id = isset( $data['quotation_id'] ) ? sanitize_key( $data['quotation_id'] ) : ''; // WPCS: input var ok.

					if ( $quotation_id ) {
						$helper = new Helper\WCPO_Supplier_Data();
						$table_exists = $helper->wcpo_table_exists( $quotation_table );
						$quotation = $helper->wcpo_get_quotation_data( $quotation_id );
						$quotation = isset( $quotation[0] ) ? $quotation[0] : '';

						$items = array_map( array( $this, 'wcpo_traverse_updated_items' ), $items, array_keys( $items ), maybe_unserialize( $quotation->items ) );
						$items = array_filter( $items, array( $this, 'wcpo_filter_updated_items' ) );
						$items_total = array_sum( array_column( $items, 'cost' ) );

						if ( $table_exists ) {
							$result = $helper->wcpo_update_quotation_data( $items, $items_total, $quotation_id );

							if ( $result ) {
								wp_safe_redirect( admin_url( 'admin.php?page=wk-purchase-management&action=manage&quotation-id=' . esc_attr( $quotation_id ) . '&success=true' ) );
								exit;
							} else {
								?>
								<div class='notice notice-success is-dismissible'>
									<p><?php esc_html_e( 'Error in updating quotation.', 'wk-purchase-order' ); ?></p>
								</div>
								<?php
							}
						} else {
							?>
							<div class='notice notice-error is-dismissible'>
								<p><?php esc_html_e( 'Quotation table doesn\'t exist.', 'wk-purchase-order' ); ?></p>
							</div>
							<?php
						}
					} else {
						?>
						<div class='notice notice-success is-dismissible'>
							<p><?php esc_html_e( 'Quotation ID not found!', 'wk-purchase-order' ); ?></p>
						</div>
						<?php
					}
				}
			}
		}

		/**
		 * Submit quotation comment
		 *
		 * @param Array $data Data.
		 * @return void
		 */
		public function wcpo_submit_quotation_comment( $data ) {
			$comment = isset( $data['wcpo_quotation_comment'] ) ? sanitize_text_field( wp_unslash( $data['wcpo_quotation_comment'] ) ) : '';
			$quotation_id = isset( $data['quotation_id'] ) ? sanitize_key( $data['quotation_id'] ) : '';
			$supplier_id = isset( $data['supplier_id'] ) ? sanitize_key( $data['supplier_id'] ) : '';
			$status = isset( $data['wcpo_status'] ) ? sanitize_text_field( wp_unslash( $data['wcpo_status'] ) ) : '';
			$prefix = isset( $data['wcpo_prefix'] ) ? sanitize_text_field( wp_unslash( $data['wcpo_prefix'] ) ) : '';

			if ( ! $comment || ! $quotation_id || ! $supplier_id ) {
				?>
				<div class='notice notice-error is-dismissible'>
					<p><?php esc_html_e( 'Fill comment field.', 'wk-purchase-order' ); ?></p>
				</div>
				<?php
			} else {
				if ( strlen( $comment ) <= 500 ) {
					$table = 'wcpo_quotation_comments';
					$helper = new Helper\WCPO_Supplier_Data();
					$table_exists = $helper->wcpo_table_exists( $table );

					if ( $table_exists ) {
						$type = ( isset( $_GET['page'] ) && 'wk-purchase-order' === $_GET['page'] ) ? 'purchase_order' : 'quotation'; // WPCS: CSRF ok; WPCS: input var ok.
						$type = ( isset( $_GET['page'] ) && 'wk-incoming-shipments' === $_GET['page'] ) ? 'incoming_shipments' : $type; // WPCS: CSRF ok; WPCS: input var ok.
						$comment_query = $helper->wcpo_add_quotation_comment( $quotation_id, $supplier_id, $comment, $status, $type );

						if ( $comment_query ) {
							if ( isset( $data['is_customer_notified'] ) && 1 === intval( $data['is_customer_notified'] ) ) {
								$comment_data = [];
								$supplier = $helper->wcpo_get_supplier( $supplier_id );
								$title = ucwords( str_replace( '_', ' ', $type ) );
								$comment_data = [
									'id'          => $quotation_id,
									'name'        => $supplier->name,
									'email'       => $supplier->email,
									'order_title' => $title,
									'comment'     => $comment,
									'prefix'      => $prefix,
								];

								do_action( 'wcpo_send_quotation_comment', $comment_data );
							}
							?>
							<div class='notice notice-success is-dismissible'>
								<p><?php esc_html_e( 'Comment added successfully.', 'wk-purchase-order' ); ?></p>
							</div>
							<?php
						} else {
							?>
							<div class='notice notice-error is-dismissible'>
								<p><?php esc_html_e( 'Error in submitting comment.', 'wk-purchase-order' ); ?></p>
							</div>
							<?php
						}
					} else {
						?>
						<div class='notice notice-success is-dismissible'>
							<p><?php esc_html_e( 'Comments table doesn\'t exist.', 'wk-purchase-order' ); ?></p>
						</div>
						<?php
					}
				} else {
					?>
					<div class='notice notice-error is-dismissible'>
						<p><?php esc_html_e( 'Comment exceeds word limit[500].', 'wk-purchase-order' ); ?></p>
					</div>
					<?php
				}
			}
		}
	}
}
