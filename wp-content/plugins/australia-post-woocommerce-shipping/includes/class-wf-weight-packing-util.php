<?php 
if(!class_exists('WeightPacketUtil')){

    class WeightPacketUtil{ 
        public function pack_items_into_weight_box($items,  $max_weight){
            $boxes      =   array();
            $max_weight =   wc_get_weight($max_weight, get_option('woocommerce_weight_unit'), 'kg');
            $unpacked   =   array();
            $value = 0;
            $count = 0;
            foreach($items as $item){
                if($item['data']['variation_id'] == 0){
                    $product = wc_get_product($item['data']['product_id']); 
                }else{
                    $product = wc_get_product($item['data']['variation_id']); 
                } 
                $item_data = $item['data'];
                $fitted         =   false;
                $item_weight    =   $item['weight'];
                foreach($boxes as $box_key  =>  $box){
                    if(($max_weight-$box['weight']) >=  $item_weight){
                        $boxes[$box_key]['weight']    = $boxes[$box_key]['weight']+$item_weight;
                        $boxes[$box_key]['items'][$count] = $item['data'];
                        $boxes[$box_key]['cost'] =   $boxes[$box_key]['cost'] + $product->get_price();
                        $fitted = true;
                        $count++;
                        
                    }
                }
                
                if(!$fitted){
                    if($item_weight <=  $max_weight){
                        $boxes[]    =   array(
                            'weight'                =>  $item_weight,
                            'items'                 =>  array($count => $item['data']),
                            'cost'                  =>  $product->get_price()
                        );
                        $count++;

                    }else{
                        $unpacked[] =   array(
                            'weight'                =>  $item_weight,
                            'items'                 =>  array($count => $item['data']),
                            'cost'                  => $product->get_price()
                        );
                        $count++;
                    }                   
                }
            }
            $result =   new WeightPackResult();
            $result->set_packed_boxes($boxes);
            $result->set_unpacked_items($unpacked);
            
            return $result;

        }
        
        public function pack_all_items_into_one_box($items){
            $boxes          =   array();
            $total_weight   =   0;
            $box_items      =   array();
            foreach($items as $item){
                $total_weight   =   ( float )$total_weight + ( float )$item['weight'];
                $box_items[] =   $item['data'];
            }
            $boxes[]    =   array(
                'weight'    =>  $total_weight,
                'items'     =>  $box_items
            );
            $result =   new WeightPackResult();
            $result->set_packed_boxes($boxes);
            return $result;
        }

        /**
         * Output a message
         */
        public function debug($message, $type = 'notice') {
            if ($this->debug) {
                if (version_compare(WOOCOMMERCE_VERSION, '2.1', '>=')) {
                    wc_add_notice($message, $type);
                } else {
                    global $woocommerce;

                    $woocommerce->add_message($message);
                }
            }
        }

    }
}