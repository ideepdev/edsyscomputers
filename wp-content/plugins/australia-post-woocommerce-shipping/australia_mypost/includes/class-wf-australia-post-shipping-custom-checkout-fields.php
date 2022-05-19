<?php
if (!class_exists('Wf_ausmypost_custom_checkout_fields_class')) {

	class Wf_ausmypost_custom_checkout_fields_class {

		public function __construct() {
			$this->settings            = get_option('woocommerce_' . WF_AUSTRALIA_MYPOST_ID . '_settings', null);
			$this->destination_country = '';
			add_filter( 'woocommerce_checkout_fields' , array($this, 'wf_auspost_add_custom_checkout_fields'));
			add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'wf_auspost_cart_shipping_packages' ));
			add_action('woocommerce_checkout_update_order_meta', array($this, 'elex_auspost_checkout_update_order_meta'));
		}

			
		public function wf_auspost_add_custom_checkout_fields( $fields) {

			if (isset($this->settings['show_insurance_checkout_field']) && ( $this->settings['show_insurance_checkout_field'] === 'yes' )) {
				$fields['billing']['ausmypost_insurance'] = array(
					'label' => 'Extra Cover (MyPost Business)',
					'type'  => 'checkbox',
					'required' => 0,
					'default'   => false,
					'class' => array ( 'update_totals_on_change', 'form-row-wide' )
				);
			}

			if (isset($this->settings['show_signature_required_field']) && ( $this->settings['show_signature_required_field'] === 'yes' )) {
				$fields['billing']['ausmypost_signature'] = array(
					'label' => 'Signature Required (MyPost Business)',
					'type'  => 'checkbox',
					'required' => 0,
					'default'   => false,
					'class' => array ( 'update_totals_on_change', 'form-row-wide' )
				); 
			}
			

			return $fields;
		}

		function wf_auspost_cart_shipping_packages( $shipping = array()) {
			$this->destination_country = $shipping[0]['destination']['country'];
			foreach ($shipping as $key=>$val) {
				$str = '';
				if (isset($_POST['post_data'])) {
					parse_str($_POST['post_data'], $str);
				}

				if (isset($str['ausmypost_insurance'])) {
					$shipping[$key]['ausmypost_insurance'] = true;
				}

				if (isset($str['ausmypost_signature'])) {
					$shipping[$key]['ausmypost_signature'] = true;
				}
					
					   
			}
			return $shipping;
		}
		public function elex_auspost_checkout_update_order_meta( $order_id) {

			if (isset($_POST['ausmypost_signature'])) {
				$elex_auspost_signature = $_POST['ausmypost_signature'];
				if ( !empty( $elex_auspost_signature) ) {   
					add_post_meta( $order_id, 'elex_ausmypost_signature', 'yes');
				}
			}
			if (isset($_POST['ausmypost_insurance'])) {
				$elex_auspost_insurance = $_POST['ausmypost_insurance'];
				if ( !empty( $elex_auspost_insurance) ) {   
					add_post_meta( $order_id, 'elex_ausmypost_insurance', 'yes');
				}
			}
		}

	}   

}
	new Wf_ausmypost_custom_checkout_fields_class();
