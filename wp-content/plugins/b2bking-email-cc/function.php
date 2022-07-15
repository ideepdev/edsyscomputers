<?php
/*
Plugin Name: B2BKing Email CC
Description: Add CC to email B2BKing
Version: 1.0
Author: CAG.
*/

/* Add CC to email b2bking */
class addCCEmailTob2bking{

    public function __construct(){
        add_action( 'woocommerce_settings_api_form_fields_b2bking_new_message_email', [ $this, 'add_my_custom_email_setting' ], 10, 1 );
    }

    public function add_my_custom_email_setting($form_fields){
        $form_fields['b2bking_cc_email'] = [
                                        'title'       => 'Add CC',
                                        'description' => 'This field will accept the cc email address.',
                                        'type'        => 'text',
                                        'default'     => 'sales@edsys.com.au'
                                    ];
    
        return $form_fields;
    }
 
    public function b2bking_new_message_email_cc_email_headers( $headers, $email_id, $order ) { 
        
        if ( 'b2bking_new_message_email' == $email_id ) {

            $conversationid = 0;

            $title = sanitize_text_field($_POST['title']);
            $args = array(
                'post_title'     => $title, 
                'post_type'      => 'b2bking_conversation',
                'post_status'    => 'publish', 
                'order'          => 'DESC',
                'posts_per_page' => 1
            );
    
            $result = new WP_Query( $args );
            if ( $result-> have_posts() ){
                while ( $result->have_posts() ){
                    $result->the_post();
                    $conversationid = get_the_ID();
                } 
            }

            $quote = 'no';
            $requester = get_post_meta($conversationid, 'b2bking_quote_requester', true);
            if (!empty($requester)){

                // check if it has only 1 message = the first message in the quote
                $msgnr = get_post_meta($conversationid, 'b2bking_conversation_messages_number', true);

                if (intval($msgnr) === 1){
                    $quote = 'yes';
                } else {
                    // if guest there are messages
                    if (intval($msgnr) === 2 && strpos($requester, '@') !== false) {
                        $quote = 'yes';
                    }
                }
            }

	        if ($quote === 'yes'){
                $options = get_option( 'woocommerce_b2bking_new_message_email_settings' );
                $cc_email = $options['b2bking_cc_email'];
                $headers .= 'Cc: '.$cc_email.'' . "\r\n";
            }

        }

        return $headers;
    }

}
add_action( 'plugins_loaded', function(){
	new addCCEmailTob2bking();
});