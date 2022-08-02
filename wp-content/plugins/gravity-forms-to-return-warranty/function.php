<?php
/*
Plugin Name: Gravity form to Return warranty
Description: Gravity form to Return warranty
Version: 1.0
Author: CAG.
*/

class gravityFormsToReturnWarranty{

    public $warrantyFormID = 3;

    public function __construct(){
        add_action( 'gform_after_submission_'.$this->warrantyFormID, [ $this, 'save_entry_to_return_warranty' ] , 10, 2 );
    }

    ## Get order details from system builds posts
    public function get_order_details( $invoice, $serial ){

        ## Get post
        global $wpdb;
        $table = $wpdb->prefix.'posts';
        $row = $wpdb->get_row( "SELECT * FROM $table WHERE post_title = '{$serial}' AND post_status = 'publish'" );
        if( empty($row->ID) ){
            return;
        }

        $invoiceDB   = get_post_meta( $row->ID, 'hcf_invoice', true );        
        if( $invoice != $invoiceDB ){
            return;
        }

        ## product name
        $productName = get_post_meta( $row->ID, 'product_name', true );

         ## Get product
        $product    = $wpdb->get_row( "SELECT * FROM $table WHERE post_title = '{$productName}' AND post_type = 'product'" );
        if( empty($product->ID) ){
            return;
        }
        
        ## Create order
        $order = wc_create_order();
        $order->add_product( wc_get_product( $product->ID ), 1 );
        $order->calculate_totals();

        ## Get order ID
        $order_id = $order->get_id();

        $order = wc_get_order($order_id);

        ## Get order items
        $products = $order->get_items();

        $items        = [];
        $tempProducts = [];
        foreach ( $products as $item_id=>$product) {
            $items[] = [
                'product_id' => $product->get_product_id(),
                'item_id'    => $item_id,
                'quantity'   => $product->get_quantity()
            ];

            $tempProducts['product_id'][] = $product->get_product_id();
            $tempProducts['item_id'][]    = $item_id;
            $tempProducts['quantity'][]   = $product->get_quantity();
        }

        return [
            'order_id' => $order_id,
            'products' => $tempProducts,
            'items' => $items
        ];

    }

    ## Save Gform entry to request return DB
    public function save_entry_to_return_warranty($entry, $form){

        if( function_exists('wcrw_create_warranty_request') ){

            ## Get filter argument
            $invoice = rgar( $entry, '5' );
            $serial  = rgar( $entry, '9' );

            ## BUild required postdata array
            $order_details = $this->get_order_details( $invoice, $serial );
            $postdata = [
                'products'                => $order_details['products'],
                'type'                    => 'replacement',
                'request_reasons'         => $entry['12'],
                'order_id'                => $order_details['order_id'],
                '_wp_http_referer'        => '/warranty-return-request/',
                'save_warranty_request'   => 'Send Request',
                'items'                   => $order_details['items']
            ];

            wcrw_create_warranty_request( $postdata );
        }
    }
 
}
add_action( 'plugins_loaded', function(){
	new gravityFormsToReturnWarranty();
});