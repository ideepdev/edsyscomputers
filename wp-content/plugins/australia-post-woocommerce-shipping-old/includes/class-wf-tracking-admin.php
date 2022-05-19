<?php

class WF_Tracking_Admin_AusPost
{
    const SHIPPING_METHOD_DISPLAY   = "Tracking";
    const TRACKING_TITLE_DISPLAY    = "AusPost Shipment Tracking";

    const TRACK_SHIPMENT_KEY        = "wf_auspost_shipment"; //Note: If this key is getting changed, do the same change in JS code below.
    const SHIPMENT_SOURCE_KEY       = "wf_auspost_shipment_source";
    const SHIPMENT_RESULT_KEY       = "wf_auspost_shipment_result";
    const TRACKING_MESSAGE_KEY      = "wfausposttrackingmsg";
    const TRACKING_METABOX_KEY      = "WF_Tracking_Metabox_AusPost";

    private function wf_init() {
        if ( ! class_exists( 'WfTrackingFactory' ) )
            include_once ( 'track/class-wf-tracking-factory.php' );
        if ( ! class_exists( 'WfTrackingUtil' ) )
            include_once ( 'track/class-wf-tracking-util.php' );

        // Sorted tracking data.
        $this->tracking_data    = WfTrackingUtil::load_tracking_data( true );
    }

    function __construct(){
        $this->wf_init();

        if ( is_admin() ) { 
            add_action( 'add_meta_boxes', array( $this, 'wf_add_tracking_metabox' ), 15 );
            add_action('admin_notices', array( $this, 'wf_admin_notice'), 15);

            if ( isset( $_GET[self::TRACK_SHIPMENT_KEY] ) ) {
                add_action( 'init', array( $this, 'wf_display_admin_track_shipment' ), 15 );
            }
        }
        

        // Shipment Tracking - Customer Order Details Page.
        add_action( 'woocommerce_view_order', array( $this, 'wf_display_tracking_info_for_customer' ), 6 );
        add_action( 'woocommerce_view_order', array( $this, 'wf_display_tracking_api_info_for_customer' ), 20 );
        add_action( 'woocommerce_email_order_meta', array( $this, 'wf_add_tracking_info_to_email'), 20 );
    }

    function wf_add_tracking_info_to_email( $order, $sent_to_admin = false, $plain_text = false ) {
        $order_id = ( WC()->version < '2.7.0' ) ? $order->id : $order->get_id(); 
        $shipment_result_array  = get_post_meta( $order_id , self::SHIPMENT_RESULT_KEY, true );

        if( !empty( $shipment_result_array ) ) {
            echo '<h3>'.__( 'Shipping Detail', 'wf-shipping-auspost' ).'</h3>';
            $shipment_source_data   = $this->get_shipment_source_data( $order_id );
            $order_notice   = WfTrackingUtil::get_shipment_display_message ( $shipment_result_array, $shipment_source_data );
            echo '<p>'.$order_notice.'</p></br>';
        }
    }
 
    public function wf_display_tracking_info_for_customer( $order_id ) {
        
        $shipment_result_array  = get_post_meta( $order_id , self::SHIPMENT_RESULT_KEY, true );

        if( !empty( $shipment_result_array ) ) {
            // Note: There is a bug in wc_add_notice which gives inconstancy while displaying messages.
            // Uncomment after it gets resolved.
            // $this->display_notice_message( $order_notice );
            $shipment_source_data   = $this->get_shipment_source_data( $order_id );
            $order_notice   = WfTrackingUtil::get_shipment_display_message ( $shipment_result_array, $shipment_source_data );
            echo $order_notice;
        }
    }

    public function wf_display_tracking_api_info_for_customer( $order_id ) {
        
        $turn_off_api = get_option( WfTrackingUtil::TRACKING_SETTINGS_TAB_KEY.WfTrackingUtil::TRACKING_TURN_OFF_API_KEY );
            
        if( $turn_off_api == 'yes' ) {
            return;
        }
        
        $shipment_result_array  = get_post_meta( $order_id , self::SHIPMENT_RESULT_KEY, true );

        if( !empty( $shipment_result_array ) ) {
            if( !empty( $shipment_result_array['tracking_info_api'] ) ) {
                $this->display_api_message_table( $shipment_result_array['tracking_info_api'] );
            }
        }
    }

    function display_api_message_table ( $tracking_info_api_array ) {
        
        echo '<h3>'.__( self::TRACKING_TITLE_DISPLAY, 'wf-shipping-auspost' ).'</h3>';
        echo '<table class="shop_table wooforce_tracking_details">
            <thead>
                <tr>
                    <th class="product-name">'.__( 'Shipment ID', 'wf-shipping-auspost' ).'<br/>('.__( 'Follow link for detailed status.', 'wf-shipping-auspost' ).')</th>
                    <th class="product-total">'.__( 'Status', 'wf-shipping-auspost' ).'</th>
                </tr>
            </thead>
            <tfoot>';

        foreach ( $tracking_info_api_array as $tracking_info_api ) {
            echo '<tr>';
            echo '<th scope="row">'.'<a href="'.$tracking_info_api['tracking_link'].''.$tracking_info_api['tracking_id'].'" target="_blank">'.$tracking_info_api['tracking_id'].'</a></th>';
            
            if( '' == $tracking_info_api['api_tracking_status'] ) {
                $message = __( 'Unable to update real time status at this point of time. Please follow the link on shipment id to check status.', 'wf-shipping-auspost' );
            }
            else {
                $message = $tracking_info_api['api_tracking_status'];
            }
            echo '<td><span>'.__( $message, 'wf-shipping-auspost' ).'</span></td>';
            echo '</tr>';
        }
        echo '</tfoot>
        </table>';
    }

    function display_notice_message( $message, $type = 'notice' ) {
        
        if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
            wc_add_notice( $message, $type );
        } else {
            global $woocommerce;
            $woocommerce->add_message( $message );
        }
    }

    function wf_admin_notice(){
        global $pagenow;
        global $post;
        
        if( !isset( $_GET[ self::TRACKING_MESSAGE_KEY ] ) && empty( $_GET[ self::TRACKING_MESSAGE_KEY ] ) ) {
            return;
        }

        $wftrackingmsg = $_GET[ self::TRACKING_MESSAGE_KEY ];

        switch ( $wftrackingmsg ) {
            case "0":
                echo '<div class="error"><p>'.self::SHIPPING_METHOD_DISPLAY.': '.__( 'Sorry, Unable to proceed.', 'wf-shipping-auspost' ).'</p></div>';
                break;
            case "4":
                echo '<div class="error"><p>'.self::SHIPPING_METHOD_DISPLAY.': '.__( 'Unable to track the shipment. Please cross check shipment id or try after some time.', 'wf-shipping-auspost' ).'</p></div>';
                break;
            case "5":
                $wftrackingmsg = get_post_meta( $post->ID, self::TRACKING_MESSAGE_KEY, true);
                if( '' != trim( $wftrackingmsg )) {
                    echo '<div class="updated"><p>'.__( $wftrackingmsg, 'wf-shipping-auspost' ).'</p></div>';
                }
                break;
            case "6":
                echo '<div class="updated"><p>'.__( 'Tracking is unset.', 'wf-shipping-auspost' ).'</p></div>';
                break;
            case "7":
                echo '<div class="updated"><p>'.__( 'Tracking Data is reset to default.', 'wf-shipping-auspost' ).'</p></div>';
                break;
            default:
                break;
        }
    }

    function wf_add_tracking_metabox() {
        global $post;
        if ( !$post ) return;
        
        if ( ! in_array( $post->post_type, array('shop_order') ) ) return;

        $order = $this->wf_load_order( $post->ID );
        if ( !$order ) return; 

        // Shipping method is available. 
        add_meta_box( self::TRACKING_METABOX_KEY, __( self::TRACKING_TITLE_DISPLAY, 'wf-shipping-auspost' ), array( $this, 'wf_tracking_metabox_content' ), 'shop_order', 'side', 'default' );
    }

    function get_shipment_source_data( $post_id ) {
        $shipment_source_data   = get_post_meta( $post_id, self::SHIPMENT_SOURCE_KEY, true );
        
        if ( empty( $shipment_source_data ) || !is_array( $shipment_source_data ) ) {
            $shipment_source_data   = array();
            $shipment_source_data['shipment_id_cs']     = '';
            $shipment_source_data['shipping_service']   = '';
            $shipment_source_data['order_date']         = '';
        }
        return $shipment_source_data;
    }
    
    function wf_tracking_metabox_content(){
        global $post;
        $shipmentId     = '';
        
        $order          = $this->wf_load_order( $post->ID );
        $tracking_url   = admin_url( '/?post='.( $post->ID ) );
        
        $shipment_source_data   = $this->get_shipment_source_data( $post->ID );

    ?>
        <ul class="order_actions submitbox">
            <li id="actions" class="wide">
                <select name="shipping_service_auspost" id="shipping_service_auspost">
    <?php
                echo "<option value=''>".__( 'None', 'wf-shipping-auspost' )."</option>";
                //foreach ( $this->tracking_data as $key => $details ) {
                    //echo '<option value='.$key.' '.selected($shipment_source_data['shipping_service'], $key).' >'.__( $details[ "name" ], 'wf-shipping-auspost' ).'</option>';
                    echo '<option value='.'australia-post'.' '.selected($shipment_source_data['shipping_service'], 'australia-post').' >'.__( 'Australia Post', 'wf-shipping-auspost' ).'</option>';
                //}
    ?>
                </select><br>
                <strong><?php _e( 'Enter Tracking IDs', 'wf-shipping-auspost' ) ?></strong>
                <img class="help_tip" style="float:none;" data-tip="<?php _e( 'Comma separated, in case of multiple shipment ids for this order.', 'wf-shipping-auspost' ); ?>" src="<?php echo WC()->plugin_url();?>/assets/images/help.png" height="16" width="16" /><br>
                <textarea id="tracking_auspost_shipment_ids" class="input-text" type="text" name="tracking_auspost_shipment_ids" ><?php echo $shipment_source_data['shipment_id_cs']; ?></textarea><br>
                <strong>Shipment Date</strong>
                <img class="help_tip" style="float:none;" data-tip="<?php _e( 'This field is Optional.', 'wf-shipping-auspost' ); ?>" src="<?php echo WC()->plugin_url();?>/assets/images/help.png" height="16" width="16" /><br>
                <input type="text" id="order_date_auspost" class="date-picker" value="<?php echo $shipment_source_data['order_date']; ?>"></p>
            </li>
            <li id="" class="wide">
                <a class="button button-primary woocommerce_shipment_auspost_tracking tips" href="<?php echo $tracking_url; ?>" data-tip="<?php _e( 'Save/Show Tracking Info', 'wf-shipping-auspost' ); ?>"><?php _e('Save/Show Tracking Info', 'wf-shipping-auspost'); ?></a>
            </li>
        </ul>
        <script>
            jQuery(document).ready(function($) {
                $( "date-picker" ).datepicker();
            });
            
            jQuery("a.woocommerce_shipment_auspost_tracking").on("click", function() {
               location.href = this.href + '&wf_auspost_shipment=' + jQuery('#tracking_auspost_shipment_ids').val().replace(/ /g,'')+'&shipping_service='+ jQuery( "#shipping_service_auspost" ).val()+'&order_date='+ jQuery( "#order_date_auspost" ).val();
               return false;
            });
        </script>
    <?php
    }

    function wf_display_admin_track_shipment($post_id = '',$shipment_id_cs = '', $shipping_service = '', $order_date = '') {
        delete_option('tracking_request_from_create_shipment');

        if( !$this->wf_user_check() && !ELEX_AUS_POST_AUTO_LABEL_GENERATE_ADDON_WOOCOMMERCE_EXTENSION ) {
            _e( "You don't have admin privileges to view this page.", 'wf-shipping-auspost' );
            exit;
        }

        if(($post_id == '') && ($shipment_id_cs == '') && ($shipping_service == '')){
            $post_id            = isset( $_GET['post'] ) ? $_GET['post'] : '';
            $shipment_id_cs     = isset( $_GET[ self::TRACK_SHIPMENT_KEY ] ) ? $_GET[ self::TRACK_SHIPMENT_KEY ] : '';
            $shipping_service   = isset( $_GET[ 'shipping_service' ] ) ? $_GET[ 'shipping_service' ] : '';
            $order_date         = isset( $_GET[ 'order_date' ] ) ? $_GET[ 'order_date' ] : '';
        }
        
        // Setting up custom message option.
        $auspost_settings   = get_option( 'woocommerce_'.WF_AUSTRALIA_POST_ID.'_settings', null );

        if( !empty( $auspost_settings['custom_message'] ) ) {
            update_option( WfTrackingUtil::TRACKING_SETTINGS_TAB_KEY.WfTrackingUtil::TRACKING_MESSAGE_KEY, $auspost_settings['custom_message'] );
        }

        $shipment_source_data   = WfTrackingUtil::prepare_shipment_source_data( $post_id, $shipment_id_cs, $shipping_service, $order_date );
        update_option("shipment source data", $shipment_source_data);

        $shipment_result        = $this->get_shipment_info( $post_id, $shipment_source_data );

        if ( $shipment_result != null && is_object( $shipment_result ) ) {
            $shipment_result_array = WfTrackingUtil::convert_shipment_result_obj_to_array ( $shipment_result );
            
            update_post_meta( $post_id, self::SHIPMENT_RESULT_KEY, $shipment_result_array );
            $admin_notice = WfTrackingUtil::get_shipment_display_message ( $shipment_result_array, $shipment_source_data );
        }
        else {
            $admin_notice = __( 'Unable to update tracking info.', 'wf-shipping-auspost' );
            update_post_meta( $post_id, self::SHIPMENT_RESULT_KEY, '' );
        }

        self::display_admin_notification_message( $post_id, $admin_notice );

        return;
    }

    public static function display_admin_notification_message( $post_id, $admin_notice ) {
        $wftrackingmsg = 5;
        update_post_meta( $post_id, self::TRACKING_MESSAGE_KEY, $admin_notice );
        update_post_meta($post_id, 'tracking_message_key', self::TRACKING_MESSAGE_KEY);
        update_post_meta($post_id, 'tracking_message_value', $wftrackingmsg);
        $tracking_request_type_create_shipment = get_option('tracking_request_from_create_shipment');
        if(!$tracking_request_type_create_shipment || empty($tracking_request_type_create_shipment)){
            wp_redirect( admin_url( '/post.php?post='.$post_id.'&action=edit&'.self::TRACKING_MESSAGE_KEY.'='.$wftrackingmsg ) );
            exit;
       }
       return;
    }

    function get_shipment_info( $post_id, $shipment_source_data ) {

        if( empty( $post_id ) ) {
            $wftrackingmsg = 0;
            wp_redirect( admin_url( '/post.php?post='.$post_id.'&action=edit&'.self::TRACKING_MESSAGE_KEY.'='.$wftrackingmsg ) );
            exit;
        }
        
        if( $shipment_source_data['shipping_service'] == '' ) {
            update_post_meta( $post_id, self::SHIPMENT_SOURCE_KEY, $shipment_source_data );
            update_post_meta( $post_id, self::SHIPMENT_RESULT_KEY, '' );

            $wftrackingmsg = 6;
            wp_redirect( admin_url( '/post.php?post='.$post_id.'&action=edit&'.self::TRACKING_MESSAGE_KEY.'='.$wftrackingmsg ) );
            exit;
        }
        
        update_post_meta( $post_id, self::SHIPMENT_SOURCE_KEY, $shipment_source_data );
        
        try {
            $shipment_result    = WfTrackingUtil::get_shipment_result( $shipment_source_data );
        }catch( Exception $e ) {
            $wftrackingmsg = 0;
            wp_redirect( admin_url( '/post.php?post='.$post_id.'&action=edit&'.self::TRACKING_MESSAGE_KEY.'='.$wftrackingmsg ) );
            exit;
        }

        return $shipment_result;
    }

    /*
    * Function remove tracking data when the shipment is removed
    */
    function delete_tracking_information($post_id){
        $shipment_source_data = $this->get_shipment_source_data( $post_id );
        delete_post_meta($post_id, self::SHIPMENT_SOURCE_KEY, $shipment_source_data);
    }

    function wf_load_order( $orderId ){
        if ( !class_exists( 'WC_Order' ) ) {
            return false;
        }
        return new WC_Order( $orderId );      
    }

    function wf_user_check() {
        if ( is_admin() && !ELEX_AUS_POST_AUTO_LABEL_GENERATE_ADDON_WOOCOMMERCE_EXTENSION ) {
            return true;
        }
        return false;
    }
}

new WF_Tracking_Admin_AusPost();

?>
