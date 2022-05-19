<?php
    if(!class_exists('Wf_auspost_custom_checkout_fields_class')){

        class Wf_auspost_custom_checkout_fields_class{

            public function __construct(){
                $this->settings = get_option('woocommerce_' . WF_AUSTRALIA_POST_ID . '_settings', null);
                $this->contracted_rates = $this->settings['contracted_rates'] == 'yes' ? true : false;
                $this->destination_country = '';
                add_filter( 'woocommerce_checkout_fields' ,array($this, 'wf_auspost_add_custom_checkout_fields'));
                add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'wf_auspost_cart_shipping_packages' ));
                add_action('woocommerce_checkout_update_order_meta', array($this, 'elex_auspost_checkout_update_order_meta'));
            }

            
            public function wf_auspost_add_custom_checkout_fields($fields){

                if(isset($this->settings['show_insurance_checkout_field']) && ($this->settings['show_insurance_checkout_field'] === 'yes')){
                    $fields['billing']['auspost_insurance'] = array(
                        'label' => 'Extra Cover (Australia Post)',
                        'type'  => 'checkbox',
                        'required' => 0,
                        'default'   => true,
                        'class' => array ( 'update_totals_on_change', 'form-row-wide' )
                    );
                }
                if(!$this->contracted_rates){
                    
                    if($this->destination_country != '' && $this->destination_country == 'AU'){// Do not show for international addresses
                        if(isset($this->settings['show_authority_to_leave_checkout_field']) && ($this->settings['show_authority_to_leave_checkout_field'] === 'yes')){
                            $fields['billing']['auspost_authority_to_leave'] = array(
                                'label' => 'Authority to leave (Australia Post)',
                                'type'  => 'checkbox',
                                'required' => 0,
                                'default'   => false,
                                'class' => array ( 'update_totals_on_change', 'form-row-wide' )
                            );
                        }
                    }

                    if(isset($this->settings['show_signature_required_field']) && ($this->settings['show_signature_required_field'] === 'yes')){
                    $fields['billing']['auspost_signature'] = array(
                            'label' => 'Signature Required (Australia Post)',
                            'type'  => 'checkbox',
                            'required' => 0,
                            'default'   => true,
                            'class' => array ( 'update_totals_on_change', 'form-row-wide' )
                        ); 
                    }
                }

                return $fields;
            }

            function wf_auspost_cart_shipping_packages($shipping = array())
            {
                $this->destination_country = $shipping[0]['destination']['country'];
                foreach($shipping as $key=>$val)
                {
                    $str = "";
                    if(isset($_POST['post_data']))
                    {
                        parse_str($_POST['post_data'],$str);
                    }

                    if(isset($str['auspost_insurance']))
                    {
                        $shipping[$key]['auspost_insurance'] = true;
                    }

                    if(isset($str['auspost_authority_to_leave']))
                    {
                        $shipping[$key]['auspost_authority_to_leave'] = true;
                    }

                    if(isset($str['auspost_signature']))
                    {
                        $shipping[$key]['auspost_signature'] = true;
                    }
                    
                       
                }
                return $shipping;
            }
            public function elex_auspost_checkout_update_order_meta($order_id)
            {
                if(isset($_POST['auspost_authority_to_leave'])){
                    $elex_auspost_authority_to_leave = $_POST['auspost_authority_to_leave'];
                    if ( !empty( $elex_auspost_authority_to_leave) ){   
                        add_post_meta( $order_id, 'elex_auspost_authority_to_leave','yes');
                    }
                }
                if(isset($_POST['auspost_signature'])){
                    $elex_auspost_signature = $_POST['auspost_signature'];
                    if ( !empty( $elex_auspost_signature) ){   
                        add_post_meta( $order_id, 'elex_auspost_signature','yes');
                    }
                }
                if(isset($_POST['auspost_insurance'])){
                    $elex_auspost_insurance = $_POST['auspost_insurance'];
                    if ( !empty( $elex_auspost_insurance) ){   
                        add_post_meta( $order_id, 'elex_auspost_insurance','yes');
                    }
                }
            }

        }   

    }
    new Wf_auspost_custom_checkout_fields_class();