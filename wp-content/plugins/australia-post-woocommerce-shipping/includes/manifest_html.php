<?php
    $url = untrailingslashit(plugins_url()).'/australia-post-woocommerce-shipping/images/australiapost-6.png';
    $auspost_settings = get_option('woocommerce_wf_australia_post_settings');     

?>
<style>
    .manifest_generation_input{
        width: 100%;
    }
    .elex-aus_post_manifest_form{
        width:90%;
        min-height: 50px;
        padding:10px;
        margin-top: 5px;
        background: #FFF;
        box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.13);
        -moz-box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.13);
        -webkit-box-shadow: 0px 0px 30px rgba(0, 0, 0, 0.13);
    }
    
    .download_manifest_button{
        width: 100% !important;
    }

    .print_manifest_button{
        width: 65% !important;
    }

    .manifest_generation_input_td_print_manifest{
        width: 170px !important;
    }

    .custom_button {
        width: 120% !important,
        margin-left: 10% !important
    }

    .elex_manifest_download_icon{
        padding: 1% 3% !important;
        width: 10% !important;
    }

    .elex_manifest_delete_checkbox{
        padding: 1% 4% !important;
        width: 11% !important;
    }

    .elex_manifest_print_icon{
        padding: 1% 3% !important;
        width: 10% !important;
    }

    .elex_manifest_numbers, .elex_manifest_woocommerce_order_ids, .elex_manifest_woocommerce_order_shipment_ids{
        padding: 0% 2% !important;
        text-align: left;
        width: 14% !important; 
    }

    .elex_auspost_delete_manifests{
        margin: 1% 94.7% !important;

    }

    .elex_manifest_manifest_generated_date{
        width: 13% !important;
        padding: 0% 2% !important;
    }

    #elex_auspost_manifest_paging_nav{
        width: 16% !important;
    }

    #elex_auspost_manifests_history_table{
        border-collapse: collapse;
        width: 100%;
    }
    #elex_auspost_manifests_history_table th, td {
      text-align: left;
      padding: 8px;
    }

    #elex_auspost_manifests_history_table tr:nth-child(even){background-color: #f2f2f2}

    #elex_auspost_manifests_history_table th {
      background-color: #17202A;
      color: white;
    }
    .manifests_details_table {
        border-collapse: collapse;
        width: 60%;
    }
    .manifests_details_table th, td {
      text-align: left;
      padding: 8px;
    }

    .manifests_details_table tr:nth-child(even){background-color: #f2f2f2}

    .manifests_details_table th {
      background-color: #17202A;
      color: white;
    }

    /*Pagination*/
    .paging-nav {
        text-align: right;
        padding-top: 2px;
        margin: 5px 45% !important;
    }
    .paging-nav a{
        margin: auto 1px;
        text-decoration: none;
        display: inline-block;
        padding: 1px 7px;
        background: #91b9e6;
        color: white;
        border-radius: 3px;
    }
    .paging-nav .selected-page{
        background: #187ed5;
        font-weight: bold;
    }
    .paging-nav, #tableData {
        width: 200px;
        margin: 0 auto;
        font-family: Arial, sans-serif;
    }

    #tableData {
        margin-top: 100px;
        border-spacing: 0;
        border: 1px dotted #ccc;
    }
    #tableData th {
        background: #e5f0fb;
        text-align: left;
        border-bottom: 2px solid #91b9e6;
    }
    #tableData td {
        padding: 3px 10px;
        border-bottom: 1px dotted #ccd;
    }
    .tab-slider--nav{
    width: 100%;
    float: left;
    margin-bottom: 20px;
    }
    .tab-slider--tabs{
        display: block;
        float: left;
        margin: 0;
        padding: 0;
        list-style: none;
        position: relative;
    
        overflow: hidden;
        background: #fff;
        height: 35px;
        user-select: none; 
    }
    .tab-slider--tabs:after{
        content: "";
        width: 50%;
        background: #345F90;
        height: 100%;
        position: absolute;
        top: 0;
        left: 0;
        transition: all 250ms ease-in-out;
        
    }
    .tab-slider--tabs.slide:after{
        left: 50%;
    }
    .tab-slider--trigger {
        font-size: 12px;
        line-height: 1;
        font-weight: bold;
        color: #345F90;
        text-transform: uppercase;
        text-align: center;
        padding: 11px 20px;
        position: relative;
        z-index: 2;
        cursor: pointer;
        display: inline-block;
        transition: color 250ms ease-in-out;
        user-select: none; 
    }
    .active {
        color: #fff;
    }
    /*  tooltip start */
    .tooltip {
        position: relative;
        padding: 2px 7px;
        margin-left: 3px;
        margin-right: 4px;
        background-color: rgb(56, 56, 56);
        border-radius: 50%;
        color: #fff;
        cursor: help;
        transition: all 0.2s ease-out;
    }

    .tooltip:hover {
        box-shadow: 0 0 6px 0 black;
    }


    .tooltip::before, .tooltip::after {
        position: absolute;
        left: 50%;
        opacity: 0;
        transition: all 0.2s ease-out;
    }

    .tooltip::before {
        content: "";
        border-width: 5px 4px 0 5px;
        border-style: solid;
        border-color: rgba(56, 56, 56, 0.8) transparent;
        margin-left: -4px;
        top: -8px;
    }

    .tooltip::after {
        content: attr(data-tooltip);
        top: -8px;
        width: 150px;
        margin-left: -75px;
        padding: 5px;
        font-size: 12px;
        background-color: rgba(56, 56, 56, 0.8);
        border-radius: 4px;
        transform: translate3d(0, -100%, 0);
        pointer-events: none;
    }

    /* 4 tooltip positions */

    .tooltip[data-tooltip-position='left']::before {
        margin-left: -21px;
        top: 12px;
        transform: rotate(-90deg);
    }

    .tooltip[data-tooltip-position='left']::after {
        transform: translate3d(-65%, 40%, 0);
    }

    .tooltip[data-tooltip-position='right']::before {
        margin-left: 14px;
        top: 12px;
        transform: rotate(90deg);
    }

    .tooltip[data-tooltip-position='right']::after {
        transform: translate3d(60%, 40%, 0);
    }

    .tooltip[data-tooltip-position='bottom']::before {
        margin-left: -4px;
        top: 32px;
        transform: rotate(-180deg);
    }

    .tooltip[data-tooltip-position='bottom']::after {
        transform: translate3d(0, 186%, 0);
    }

   

    .tooltip:hover::before, .tooltip:hover::after {
        opacity: 1;
    }
     /* end  tooltip  */
</style>

<script>
    jQuery(document).ready(function(){
        jQuery('.elex-aus_post_retrive').hide();
      jQuery(".tab-slider--nav li").click(function() {
        jQuery(".tab-slider--body").hide();
        var activeTab = jQuery(this).attr("rel");
        jQuery("#"+activeTab).fadeIn();
            if(jQuery(this).attr("rel") == "tab2"){
                jQuery('.tab-slider--tabs').addClass('slide');
                jQuery('.elex-aus_post_retrive').show();
                jQuery('.elex_generate').hide();
            }else{
                jQuery('.tab-slider--tabs').removeClass('slide');
                jQuery('.elex-aus_post_retrive').hide();
                jQuery('.elex_generate').show();
            }
        jQuery(".tab-slider--nav li").removeClass("active");
        jQuery(this).addClass("active");
     });
        jQuery('.manifest_generation_type').change(function(){
            var method = jQuery('.manifest_generation_input').val();
            switch(method)
            {
                case 'order_id':
                    jQuery('.shipdate').show();
                    jQuery('.order_id').show();
                    jQuery('.shipdate').hide();
                    jQuery('#order_id_range').hide();
                    jQuery('.shipdate').prop('required', false);
                    break;
                case 'shipdate':
                    jQuery('.order_id').hide();
                    jQuery('.shipdate').show();
                    jQuery('#order_id_range').hide();
                    jQuery('.shipdate').prop('required', true);
                    jQuery('.order_id').prop('required', false);
                    break;
                case 'order_id_range':
                    jQuery('.order_id').hide();
                    jQuery('.shipdate').hide();
                    jQuery('#order_id_range').show();
                    jQuery('.shipdate').prop('required', true);
                    jQuery('.order_id').prop('required', false);
                    break;
                default:
                    jQuery('.order_id').show();
                    jQuery('.shipdate').hide();
                    jQuery('.order_id_range').hide();
                    jQuery('.order_id').prop('required', true);
                    jQuery('.shipdate').prop('required', false);
            }
        });

        var method_on_load = jQuery('.manifest_generation_input').val();

        if(method_on_load == 'order_id'){
            jQuery('.order_id').show();
            jQuery('.shipdate').hide();
            jQuery('.order_id').prop('required', true);
            jQuery('.shipdate').prop('required', false);
        }
       
    });
</script>



<div class="elex-aus_post_manifest_form">

<header class="bg-dark dk header" style="background-color: #345F90;margin-left: 0px;" > 
    <div class="navbar-header aside-md" style="height: 35px;font-size: 18px;width: 100%;padding-top: 15px;"> <b>
        <h3 style="color: white !important;margin-left: 4px;margin-top: -0.3px;"><?php _e(' Australia Post Generate Manifest', 'wf-auspost-shipping'); ?></h3> <img  src="<?php echo($url);?>" style="width:100px;float: right; margin-top: -52px;
    width: 141px;
    margin-bottom: 33px;"></img>  
    </div> 
</header> 
<div class="tab-slider--nav" style="margin-top: 23px;">
    <ul class="tab-slider--tabs">
        <li class="tab-slider--trigger active" rel="tab1"><?php _e('Create Manifest', 'wf-auspost-shipping');?></li>
        <li class="tab-slider--trigger" rel="tab2"> <?php _e('Retrive Manifest', 'wf-auspost-shipping');?></li>
    </ul>

    <br>
    <br>
    <hr></hr>
    
</div>

<form id="elex_auspost_manifest_form" method="POST" style="margin-left: 4px; margin-top: 6px;">
  <tr>
            <td></td>
            <td>
                    <label>
                    <?php echo("After the successful creation of manifest, you will receive a manifest form in your inbox ( ");?>
                    <a href="https://mail.google.com/mail">
                    <?php echo($auspost_settings['shipper_email']);?></a> 
                        <?php echo(")");?> 
                        <span class="tooltip" data-tooltip-position="right" data-tooltip="<?php _e("To change the notification Email, go to ELEX Australia Post Plugin Settings -> General Tab, then change the 'Email Address' field.", 'wf-auspost-shipping');?>">?</span>
                    </label>
                    
            </td>
        </tr>
    <table id="elex_auspost_manifest_table" >       

        <tr class="elex-aus_post_retrive">
            <td>
                <div >
                    <label style=""><?php echo("Enter the manifest/consignment numbers present in the ");?><a href="https://auspost.com.au/"><?php _e('Australia Post Dashboard','wf-auspost-shipping');?></a>.
                    </label>
                </div>
            </td>
        </tr>
        <tr class="elex-aus_post_retrive">
            <td>
                <div >
                    <h3><?php _e('Manifest Number', 'wf-auspost-shipping'); ?></h3>
                </div>
            </td>
            <td>
                <div  style="margin-left: -350px !important;">
                    <input type="text" name="retrive_id" class="manifest_retrive_input" placeholder="Eg : AP12896423"/>
                </div>
            </td>
        </tr>
        <tr class="elex-aus_post_retrive">
        
            <td>
                <div >
                    <input type="submit" value="<?php _e('Retrive', 'wf-auspost-shipping'); ?>" class="button-primary" name="retrive" data-tip="<?php _e('Retrive Manifest', 'wf-shipping-auspost'); ?>">
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="elex_generate">
                    <h3><?php _e('Choose Option', 'wf-auspost-shipping'); ?></h3>
                </div>
            </td>
            <td width="300px">
                <div class="elex_generate">
                    <select name="manifest_generation_type" class="manifest_generation_type manifest_generation_input">
                        <!--<option value="all"><?php // _e('ALL', 'wf-auspost-shipping'); ?></option> --><!-- Will provide in next version -->
                        <option value="order_id"><?php _e('Order ID', 'wf-auspost-shipping'); ?></option>
                        <option value="shipdate"><?php _e('Date of Label Generation', 'wf-auspost-shipping'); ?></option>
                        <option value="order_id_range"><?php _e('Order ID Range', 'wf-auspost-shipping'); ?></option>
                    </select>
                </div>
            </td>
            <td>
                <div class="elex_generate">
                    <a type="button" class="" data-toggle="modal" data-target="#choose-option-info-model"><?php _e('More Info','wf-auspost-shipping'); ?></a>
                </div>
            </td>
           
        </tr>
       
        <tr class="order_id" style="display: none">
            <td>
                <div class="elex_generate">
                    <h3><?php _e('Order IDs', 'wf-auspost-shipping'); ?></h3>
                </div>
            </td>
            <td width="300px">
                <div class="elex_generate">
                    <input type="text" name="order_id" placeholder="Eg: 1,2,3" class="manifest_generation_input"/>
                </div>
            </td>
            <td>
                <div class="elex_generate">
                   
                    <span class="tooltip" data-tooltip-position="right" data-tooltip=" <?php _e('Multiple orders ids should be comma-separated. Eg: 1,2,3', 'wf-auspost-shipping'); ?>">?</span>
               
                </div>
            </td>
        </tr>
        <tr id="order_id_range" style="display: none">
            <td style="padding-right: 10px;">
                <div class="elex_generate">
                    <h3><?php _e('Starting Order Id', 'wf-auspost-shipping'); ?></h3>
                </div>
            </td>
            <td style="padding-right: 10px;width: 100px; ">
                <div class="elex_generate">
                    <input type="number" placeholder="" name="starting_order_id" class=""/>
                </div>
            </td>
            <td style="padding-right: 10px;">
                <div class="elex_generate">
                    <h3><?php _e('Ending Order Id', 'wf-auspost-shipping'); ?></h3>
                </div>
            </td>
            <td style="width: 100px">
                <div class="elex_generate">
                    <input type="number" name="ending_order_id" placeholder="" class=""/>
                </div>
            </td>
           
        </tr>
        
        <tr class="shipdate" style="display: none;">
            <td>
                <div class="elex_generate">
                    <h3><?php _e('Label Generation Date', 'wf-auspost-shipping'); ?></h3>
                </div>
            </td>
            <td>
                <div class="elex_generate">
                    <input type="date" name="shipdate" class="manifest_generation_input">
                </div>
            </td>
        </tr>
        
        <tr>
            
            <td>
                <div class="elex_generate">
                    <input type="submit" value="<?php _e('Generate', 'wf-auspost-shipping'); ?>" class="button-primary" name="generate" data-tip="<?php _e('Generate Manifest', 'wf-shipping-auspost'); ?>">
                </div>
            </td>
            
        </tr>
    </table>

    <br><br>

    <!-- AusPost Order Manifest History Table -->
    <?php $manifest_history = get_option('elex_auspost_manifest_history');?>
    <?php if(!empty($manifest_history)):?>
        <table id="elex_auspost_manifests_history_table" border="1" class="table table-hover table-responsive elex_generate">
            <thead>
                <tr>
                    <th><?php _e('Manifest Number', 'wf-shipping-auspost');?></th>
                    <th><?php _e('Order Ids', 'wf-shipping-auspost');?></th>
                    <th><?php _e('Shipment Ids', 'wf-shipping-auspost');?></th>
                    <th><?php _e('Date', 'wf-shipping-auspost');?></th>
                    <th><?php _e('Download', 'wf-shipping-auspost');?></th>
                    <th><?php _e('Print', 'wf-shipping-auspost');?></th>
                    <th><?php _e('Delete', 'wf-shipping-auspost');?></th>
                </tr>
            </thead>
            <tbody>
                <?php

                    $order_summary_download_url = $order_summary_download_url_auspost = admin_url('/admin.php?page=auspost_manifest&print_manifest=yes&manifest_download_method_selected=attachment');
                    $order_summary_print_url = $order_summary_print_url_auspost = admin_url('/admin.php?page=auspost_manifest&print_manifest=yes&manifest_download_method_selected=inline');
                    $order_summary_download_url_startrack = admin_url('/admin.php?page=auspost_manifest&print_manifest=yes&manifest_download_method_selected=attachment');
                    $order_summary_print_url_startrack = admin_url('/admin.php?page=auspost_manifest&print_manifest=yes&manifest_download_method_selected=inline');

                    rsort($manifest_history);

                    foreach($manifest_history as $manifests){
                        $manifests_array = $manifests;
                        $manifests_array = array_shift($manifests_array);
                        if(is_array($manifests_array) && !empty($manifests_array)){
                            $manifest_orders_array = '';
                            $manifest_generation_date = '';
                            $startrack_order_summary_id = '';
                            $auspost_order_summary_id = '';
                            $manifest_numbers = '';
                            $manifest_numbers_array = array();
                            foreach($manifests as $manifest){
                                $order_ids_array = '';
                                $shipment_ids_string = '';
                                $manifest_numbers .= $manifest['id']."<br>";
                                $manifest_numbers_array[] = $manifest['id'];
                                $manifest_generation_date = $manifest['date'];
                                foreach($manifest['data'] as $manifest_datum_key => $manifest_datum_value){
                                    $order_ids_array .= ' '.$manifest_datum_key. ',';
                                    $shipment_ids = $manifest_datum_value['shipment_ids'];
                                    foreach($shipment_ids as $shipment_id){
                                        $shipment_ids_string .= ' '.$shipment_id.',';
                                    }
                                }
                                if($manifest['type'] == 'StarTrack'){
                                    $startrack_order_summary_id = $manifest['id'];
                                }else{
                                    $auspost_order_summary_id = $manifest['id'];
                                }
                            }

                            $manifest_numbers_delete = implode(',', $manifest_numbers_array);

                            echo "<tr>";
                            echo "<td class='elex_manifest_numbers'>".$manifest_numbers."</td>";
                            $order_summary_download_url_auspost .= '&auspost_order_id='.$auspost_order_summary_id;
                            $order_summary_print_url_auspost .= '&auspost_order_id='.$auspost_order_summary_id;
                            $order_summary_download_url_startrack .= '&startrack_order_id='.$startrack_order_summary_id;
                            $order_summary_print_url_startrack .= '&startrack_order_id='.$startrack_order_summary_id;
                            echo "<td class='elex_manifest_woocommerce_order_ids'>".rtrim($order_ids_array, ',')."</td>";
                            echo "<td class='elex_manifest_woocommerce_order_shipment_ids'>".rtrim($shipment_ids_string, ',')."</td>";
                            echo "<td class='elex_manifest_manifest_generated_date'>".$manifest_generation_date."</td>";
                            
                            if($startrack_order_summary_id != ''){
                                echo "<td class='elex_manifest_download_icon'>StarTrack<br><a class='button' target='_blank' href=".$order_summary_download_url_startrack."><i class='fa fa-download'></i></a>";
                            }else{
                                echo "<td class='elex_manifest_download_icon'>AusPost<br><a class='button' target='_blank' href=".$order_summary_download_url_auspost."><i class='fa fa-download'></i></a>";
                            }
                            echo '</td>';
                            
                            if($startrack_order_summary_id != ''){
                                echo "<td class='elex_manifest_print_icon'>StarTrack<br><a class='button' target='_blank' href=".$order_summary_print_url_startrack."><i class='fa fa-print'></i></a>";
                            }else{
                                echo "<td class='elex_manifest_print_icon'>AusPost<br><a class='button' target='_blank' href=".$order_summary_print_url_auspost."><i class='fa fa-print'></i></a>";
                            }
                            echo '</td>';
                            echo "<td class='elex_manifest_delete_checkbox'><input name='elex_auspost_delete_manifest_id[]' value='".$manifest_numbers_delete."' type='checkbox'></td>";
                            echo "</tr>";
                        }else{
                            $order_summary_download_url .= '&auspost_order_id='.$manifests['id'];
                            $order_summary_print_url .= '&auspost_order_id='.$manifests['id'];
                            echo "<tr>";
                            echo "<td class='elex_manifest_numbers'>".$manifests['id']."</td>";
                            $order_ids_array = '';
                            $shipment_ids_string = '';
                            foreach($manifests['data'] as $manifest_datum_key => $manifest_datum_value){
                                $order_ids_array .= ' '.$manifest_datum_key. ',';
                                $shipment_ids = $manifest_datum_value['shipment_ids'];
                                foreach($shipment_ids as $shipment_id){
                                    $shipment_ids_string .= ' '.$shipment_id.',';
                                }
                            }
                            $order_manifest_generated_date = isset($manifests['date'])? $manifests['date']: '';
                            echo "<td class='elex_manifest_woocommerce_order_ids'>".rtrim($order_ids_array, ',')."</td>";
                            echo "<td class='elex_manifest_woocommerce_order_shipment_ids'>".rtrim($shipment_ids_string, ',')."</td>";
                            echo "<td class='elex_manifest_manifest_generated_date'>".$order_manifest_generated_date."</td>";
                            echo "<td class='elex_manifest_download_icon'><a class='button' target='_blank' href=".$order_summary_download_url."><i class='fa fa-download'></i></a></td>";
                            echo "<td class='elex_manifest_print_icon'><a class='button' target='_blank' href=".$order_summary_print_url."><i class='fa fa-print'></i></a></td>";
                            echo "<td class='elex_manifest_delete_checkbox'><input name='elex_auspost_delete_manifest_id[]' value='".$manifests['id']."' type='checkbox'></td>";
                            echo "</tr>";
                        }
                    }
                ?>
            </tbody>
        </table>
        <input type="submit" class="button-primary elex_auspost_delete_manifests elex_generate" value="Delete" >
    <?php endif;?>
</form>
</div>
<br><br>

  <!-- Modal -->
  <div class="modal fade" id="choose-option-info-model" role="dialog">
    <div class="modal-dialog" style="margin-top: 5%;">
      <!-- Modal content-->
      <div class="modal-content">
        
        <div class="modal-body">
        <ul style="list-style: disc">
        <p> <?php _e('A Create Manifest request will be submitted to Australia Post based on the methods given below.', 'wf-auspost-shipping'); ?></p>
                        <ol>
                            <!--<li><?php //_e('“ALL” – Includes Shipment ids of recent 30 orders.', 'wf-auspost-shipping'); ?></li>--> <!-- Will provide in next version-->
                            <li><?php _e('Order ID – Generate the Manifest based on the WooCommerce Order Ids.', 'wf-auspost-shipping'); ?></li>
                            <li><?php _e('Date of Label Generation – Generate Manifest based on the date in which Label Generation is done.', 'wf-auspost-shipping'); ?></li>
                            <li><?php _e('Order ID Range – Generate Manifest based on the WooCommerce Order  Ids comes within the specific range given as input.', 'wf-auspost-shipping'); ?></li>
                        </ol>
                    </ul>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal"><?php _e('Close','wf-auspost-shipping');?></button>
        </div>
      </div>
      
    </div>
  </div>
<script type="text/javascript">
    jQuery(document).ready(function(){
        <?php if(empty($manifest_history)){?>
            jQuery('#elex_auspost_manifests_history_table').hide();
            jQuery('.elex_auspost_delete_manifests').hide();
        <?php }

        if(!empty($manifest_history) && sizeof($manifest_history) > 10){?>
            jQuery('#elex_auspost_manifests_history_table').paging({limit:10});
        <?php }else{?>
            jQuery('#elex_auspost_manifests_history_table').show();
            jQuery('#elex_auspost_manifest_paging_nav').hide();
        <?php } ?>
    });

</script>

<?php 

    /*Showing error and success notices*/
    $stored_shipping_ids_with_existing_manifests = get_option('elex_auspost_shipping_ids_with_existing_manifests');
    if($stored_shipping_ids_with_existing_manifests != ''){
        echo '<div class="error"><p>' . sprintf(__('Manifest already generated for the shipment id(s) %s', 'wf-shipping-auspost'), $stored_shipping_ids_with_existing_manifests) . '</p></div>';
        delete_option('elex_auspost_shipping_ids_with_existing_manifests');
    }

    $manifest_status = get_option('elex_auspost_manifest_generated');

    /* Showing success notice for manifest generation for provided orders */
    if($manifest_status){
        $manifest_orders_shipment_ids = get_option('elex_auspost_orders_shipments_for_generated_manifest');
        echo '<div class="notice notice-success"><p>' . sprintf(__('Manifest generated for the provided %s', 'wf-shipping-auspost'), $manifest_orders_shipment_ids) . '</p></div>';
        delete_option('elex_auspost_manifest_generated');
    }

      

    $stored_orders_with_no_shipments = get_option('elex_auspost_orders_with_no_shipments');

    /* Showing error notice for the Order ids for which the shipments are not created */
    if($stored_orders_with_no_shipments != ''){
        $orders_ids_temp = explode(',',$stored_orders_with_no_shipments);
        $stored_orders_with_no_shipments_temp = '';
        if(is_array($orders_ids_temp)){
            foreach($orders_ids_temp as $item){
                $order = wc_get_order($item);
                if($order){
                    $order_number = get_custom_order_number($order); 
                    $stored_orders_with_no_shipments_temp .= $order_number.', ';
                }else{
                    $stored_orders_with_no_shipments_temp .= $item.', ';
                }
            }
            $stored_orders_with_no_shipments_temp = rtrim($stored_orders_with_no_shipments_temp, ', ');
        }else{
            $order = wc_get_order($orders_ids_temp);
            if($order){
                $order_number = get_custom_order_number($order); 
                $stored_orders_with_no_shipments_temp .= '';
            }else{
                $stored_orders_with_no_shipments_temp .= $orders_ids_temp;
            }
            
        }
        
        echo '<div class="error"><p>' . sprintf(__('MANIFEST NOT GENERATED, NO SHIPMENTS FOUND FOR THE ORDER(S) %s', 'wf-shipping-auspost'), $stored_orders_with_no_shipments_temp) . '</p></div>';
        delete_option('elex_auspost_orders_with_no_shipments');
    }

    $stored_orders_with_no_label_ids = get_option('elex_auspost_orders_with_no_label_ids');

    /* Showing error notice for Order ids for which shipping labels are not generated */
    if($stored_orders_with_no_label_ids != ''){
        $orders_ids_temp = explode(',',$stored_orders_with_no_label_ids);
        $stored_orders_with_no_label_ids_temp = '';
        if(is_array($orders_ids_temp)){
            foreach($orders_ids_temp as $item){
                $order = wc_get_order($item);
                if($order){
                    $order_number = get_custom_order_number($order); 
                    $stored_orders_with_no_label_ids_temp .= $order_number.', ';
                }else{
                    $stored_orders_with_no_label_ids_temp .= $item.', ';
                }
            }
            $stored_orders_with_no_label_ids_temp = rtrim($stored_orders_with_no_label_ids_temp, ', ');
        }else{
            $order = wc_get_order($orders_ids_temp);
            if($order){
                $order_number = get_custom_order_number($order); 
                $stored_orders_with_no_label_ids_temp .= '';
            }else{
                $stored_orders_with_no_label_ids_temp .= $orders_ids_temp;
            }
            
        }
        echo '<div class="error"><p>' . sprintf(__('MANIFEST NOT GENERATED, NO LABELS FOUND FOR THE ORDER(S) %s', 'wf-shipping-auspost'), $stored_orders_with_no_label_ids_temp) . '</p></div>';
        delete_option('elex_auspost_orders_with_no_label_ids');
    }
    /*Showing Response in the Existing Manifest */
    $response_existing_manifests_details_array = get_option('elex_auspost_shipping_response_existing_manifests_details',true);
    if(is_array($response_existing_manifests_details_array)){
        echo '<div><p style="color:red">' . __('Manifest already generated details', 'wf-shipping-auspost') . '</p></div>';
        echo '<table border="1" class=" manifests_details_table table table-hover table-responsive">
                <thead>
                <tr>
                    <th>Manifest Number</th>
                    <th>Order Id</th>
                    <th>Shipment Id</th>
                </tr>
                </thead>
                <tbody>';
        foreach($response_existing_manifests_details_array as $temp){
            echo '<tr>';
            echo '<td>'.$temp["manifest_number"].'</td>';
            echo '<td>'.$temp["order_id"].'</td>';
            echo '<td>'.$temp["shipment_id"].'</td>';  
            echo '</tr>';
        }
        echo '</tbody>
            </table>';
        delete_option('elex_auspost_shipping_response_existing_manifests_details');
    }

    $auspost_settings = get_option('woocommerce_wf_australia_post_settings');
    $contract_api_key = $auspost_settings['api_key'];
    $contract_api_password = $auspost_settings['api_pwd'];
    $contract_api_account_number = $auspost_settings['api_account_no'];
    $contract_api_password_startrack = '';
    $contract_api_account_number_startrack = '';

    if(isset($auspost_settings['wf_australia_post_starTrack_rates_selected']) && ($auspost_settings['wf_australia_post_starTrack_rates_selected'] == true)){
        $contract_api_password_startrack = $auspost_settings['wf_australia_post_starTrack_api_pwd'];
        $contract_api_account_number_startrack = $auspost_settings['wf_australia_post_starTrack_api_account_no'];
        if (isset($auspost_settings['wf_australia_post_starTrack_api_key_enabled']) && $auspost_settings['wf_australia_post_starTrack_api_key_enabled']) {
            $api_key_starTrack = $auspost_settings['wf_australia_post_starTrack_api_key'];
        } else {
            $api_key_starTrack = $contract_api_key;
        }
    }


    $order_auspost_label_id = '';
    $manifest_download_method = (isset($auspost_settings['dir_download']) && $auspost_settings['dir_download'] =='yes') ? 'attachment' : 'inline';
    $contracted_api_mode = isset($auspost_settings['contracted_api_mode']) ? $auspost_settings['contracted_api_mode'] : 'test';

    if(isset($_POST['retrive'])){
        if($_POST['retrive'] == 'Retrive'){
            $retrive_manifest_id =$_POST['retrive_id'];
            $api_type = 'AusPost';
            $flag = false;
            $shipping_manifest_data = get_option('elex_auspost_shipping_manifest_data',true);
            if($shipping_manifest_data && !empty($shipping_manifest_data)){
                if(isset($shipping_manifest_data[$retrive_manifest_id]) && isset($shipping_manifest_data[$retrive_manifest_id]['type']) &&  !empty($shipping_manifest_data[$retrive_manifest_id]['type']) ){
                    $api_type = $shipping_manifest_data[$retrive_manifest_id]['type'];
                    $flag = true;
                }
            }
            if(!$flag){
                $manifest_history = get_option('elex_auspost_manifest_history',true);
                foreach($manifest_history as $data){
                    foreach($data as $item){
                        if($item['id'] == $retrive_manifest_id){
                            $api_type = $item['type'];
                            break 2;
                        }
                    }
                }
            }
            // Compatibility of Australia Post with Multivendor.
            if((in_array('multi-vendor-add-on-for-thirdparty-shipping/multi-vendor-add-on-for-thirdparty-shipping.php', apply_filters('active_plugins', get_option('active_plugins'))) && (isset($auspost_settings['vendor_check']) && ($auspost_settings['vendor_check'] == 'yes' )))){
                $stored_vendor_manifest_history = get_option('elex_auspost_vendor_manifest_history')?get_option('elex_auspost_vendor_manifest_history') : array();                
                if(!empty($stored_vendor_manifest_history) && isset($stored_vendor_manifest_history[$retrive_manifest_id]['seller_id']) && $stored_vendor_manifest_history[$retrive_manifest_id]['seller_id'] ){
                    $vendor_user_id = $stored_vendor_manifest_history[$retrive_manifest_id]['seller_id'];
                    $vendor_elex_australia_post_api_key = get_the_author_meta('vendor_elex_australia_post_api_key', $vendor_user_id); 
                    $vendor_elex_australia_post_account_number = get_the_author_meta( 'vendor_elex_australia_post_account_number', $vendor_user_id);
                    $vendor_elex_australia_post_api_password = get_the_author_meta( 'vendor_elex_australia_post_api_password' , $vendor_user_id);
                    elex_retrive_manifest_form($retrive_manifest_id, $vendor_elex_australia_post_api_key, $vendor_elex_australia_post_api_password, $vendor_elex_australia_post_account_number, $contracted_api_mode, $manifest_download_method = 'attachment',$auspost_settings['shipper_email']);
                }else{
                    elex_retrive_manifest_form($retrive_manifest_id, $contract_api_key, $contract_api_password, $contract_api_account_number, $contracted_api_mode, $manifest_download_method = 'attachment',$auspost_settings['shipper_email']);
                }
            }else{
                if($api_type == 'StarTrack'){
                    elex_retrive_manifest_form($retrive_manifest_id, $api_key_starTrack, $contract_api_password_startrack, $contract_api_account_number_startrack, $contracted_api_mode, $manifest_download_method = 'attachment',$auspost_settings['shipper_email']);
                }else{
                    elex_retrive_manifest_form($retrive_manifest_id, $contract_api_key, $contract_api_password, $contract_api_account_number, $contracted_api_mode, $manifest_download_method = 'attachment',$auspost_settings['shipper_email']);
               }
            }
           
        }
    }
    
    /* MANIFEST GENERATION PART */

    if(!empty($_GET) && isset($_GET['print_manifest'])){

        $previously_generated_auspost_order_id = get_option("elex_auspost_current_order_id");
        $auspost_manifest_order_id = isset($_GET['auspost_order_id'])? $_GET['auspost_order_id']: '';
        $startrack_manifest_order_id = isset($_GET['startrack_order_id'])? $_GET['startrack_order_id']: '';

        if(isset($_GET['print_manifest']) && ($_GET['print_manifest'] == 'yes')){
            if($startrack_manifest_order_id != ''){
                $auspost_contract_api_key = $api_key_starTrack;
                $auspost_contract_api_password_startrack = $contract_api_password_startrack;
                $auspost_contract_api_account_number_startrack = $contract_api_account_number_startrack;
                // Compatibility of Australia Post with Multivendor.
                if((in_array('multi-vendor-add-on-for-thirdparty-shipping/multi-vendor-add-on-for-thirdparty-shipping.php', apply_filters('active_plugins', get_option('active_plugins'))) && (isset($auspost_settings['vendor_check']) && ($auspost_settings['vendor_check'] == 'yes' )))){
                    $stored_vendor_manifest_history = get_option('elex_auspost_vendor_manifest_history')?get_option('elex_auspost_vendor_manifest_history') : array();                
                    if(!empty($stored_vendor_manifest_history) && isset($stored_vendor_manifest_history[$startrack_manifest_order_id]['seller_id']) && $stored_vendor_manifest_history[$startrack_manifest_order_id]['seller_id'] ){
                        $vendor_user_id = $stored_vendor_manifest_history[$startrack_manifest_order_id]['seller_id'];
                        $auspost_contract_api_key = get_the_author_meta('vendor_elex_australia_post_api_key', $vendor_user_id); 
                        $auspost_contract_api_account_number_startrack = get_the_author_meta( 'vendor_elex_australia_post_startrack_account_number', $vendor_user_id);
                        $auspost_contract_api_password_startrack = get_the_author_meta( 'vendor_elex_australia_post_startrack_api_password', $vendor_user_id); 
                        
                    }
                }    
                if(isset($_GET['manifest_download_method_selected']) && ($_GET['manifest_download_method_selected'] == 'attachment')){
                    elex_auspost_print_order_summary($startrack_manifest_order_id, $auspost_contract_api_key, $auspost_contract_api_password_startrack, $auspost_contract_api_account_number_startrack, $contracted_api_mode, $manifest_download_method = 'attachment');
                }else if(isset($_GET['manifest_download_method_selected']) && ($_GET['manifest_download_method_selected'] == 'inline')){
                    elex_auspost_print_order_summary($startrack_manifest_order_id, $auspost_contract_api_key, $auspost_contract_api_password_startrack, $auspost_contract_api_account_number_startrack, $contracted_api_mode, $manifest_download_method = 'inline');
                }

            }else{
                $auspost_contract_api_key = $contract_api_key;
                $auspost_contract_api_password = $contract_api_password;
                $auspost_contract_api_account_number = $contract_api_account_number;
                
                // Compatibility of Australia Post with Multivendor.
                if((in_array('multi-vendor-add-on-for-thirdparty-shipping/multi-vendor-add-on-for-thirdparty-shipping.php', apply_filters('active_plugins', get_option('active_plugins'))) && (isset($auspost_settings['vendor_check']) && ($auspost_settings['vendor_check'] == 'yes' )))){
                    $stored_vendor_manifest_history = get_option('elex_auspost_vendor_manifest_history')?get_option('elex_auspost_vendor_manifest_history') : array();                
                    
                    if(!empty($stored_vendor_manifest_history) && isset($stored_vendor_manifest_history[$auspost_manifest_order_id]['seller_id']) && $stored_vendor_manifest_history[$auspost_manifest_order_id]['seller_id'] ){
                       
                        $vendor_user_id = $stored_vendor_manifest_history[$auspost_manifest_order_id]['seller_id'];
                        $auspost_contract_api_key = get_the_author_meta('vendor_elex_australia_post_api_key', $vendor_user_id); 
                        $auspost_contract_api_account_number = get_the_author_meta( 'vendor_elex_australia_post_account_number', $vendor_user_id);
                        $auspost_contract_api_password = get_the_author_meta( 'vendor_elex_australia_post_api_password' , $vendor_user_id);                        
                    }
                } 
                if(isset($_GET['manifest_download_method_selected']) && ($_GET['manifest_download_method_selected'] == 'attachment')){
                    elex_auspost_print_order_summary($auspost_manifest_order_id, $auspost_contract_api_key, $auspost_contract_api_password, $auspost_contract_api_account_number, $contracted_api_mode, $manifest_download_method = 'attachment');
                }else if(isset($_GET['manifest_download_method_selected']) && ($_GET['manifest_download_method_selected'] == 'inline')){
                    elex_auspost_print_order_summary($auspost_manifest_order_id, $auspost_contract_api_key, $auspost_contract_api_password, $auspost_contract_api_account_number, $contracted_api_mode, $manifest_download_method = 'inline');
                }
            }
        }

        return;
    }else if(!empty($_POST)){
        global $WooCommerce;
        global $wpdb;

        $manifest_generation_type = isset($_POST['manifest_generation_type'])? $_POST['manifest_generation_type']: '';
        $orders_ids = array();
        $uneligible_orders = '';
        $eligible_orders = array();
        $manifest_ids_to_delete = array();

        if(isset($_POST['elex_auspost_delete_manifest_id'])){
            $manifest_ids_to_delete = $_POST['elex_auspost_delete_manifest_id'];
            elex_auspost_delete_manifest_data($manifest_ids_to_delete);
        }else{
            if($manifest_generation_type == 'shipdate' && !empty($_POST['shipdate'])){
                $shipdate = $_POST['shipdate'];
                $query_string_to_get_postmeta_label_generated_dates = "SELECT `post_id`,`meta_value` FROM `".$wpdb->prefix."postmeta` WHERE `meta_key` LIKE 'wf_woo_australiapost_labelId_generation_date' ";

                $posts_meta_label_generated_id = $wpdb->get_results($query_string_to_get_postmeta_label_generated_dates, OBJECT);// OBJECT - result will be an object

                foreach($posts_meta_label_generated_id as $posts_meta){
                    if($posts_meta->meta_value == $shipdate){
                        $orders_ids[] = $posts_meta->post_id;
                    }
                }

                $order_validation_results = elex_auspost_validate_orders($orders_ids);

                if(is_array($order_validation_results) && !empty($order_validation_results)){
                    $uneligible_orders = $order_validation_results['invalid_orders'];
                    $eligible_orders = $order_validation_results['valid_orders'];
                }

                if($uneligible_orders != ''){
                    update_option("elex_auspost_uneligible_orders", $uneligible_orders);
                }

            }

            if($manifest_generation_type == 'all'){

                $query = new WC_Order_Query( array(
                    'limit'     => 30,
                    'orderby'   => 'date',
                    'order'     => 'DESC',
                    'return'    => 'ids',
                ) );
                $orders_ids = $query->get_orders();
            }
            if($manifest_generation_type == 'order_id_range' && !empty($_POST['starting_order_id']) && !empty($_POST['ending_order_id'])){
                $starting_order_id= $_POST['starting_order_id'];
                $ending_order_id = $_POST['ending_order_id'];
                $query = new WC_Order_Query( array(
                    'id' => $starting_order_id."...".$ending_order_id,
                    'return'    => 'ids',
                ) );
                $orders_ids = $query->get_orders();
              

                $order_validation_results = elex_auspost_validate_orders($orders_ids);

                if(is_array($order_validation_results) && !empty($order_validation_results)){
                    $uneligible_orders = $order_validation_results['invalid_orders'];
                    $eligible_orders = $order_validation_results['valid_orders'];
                }

                if($uneligible_orders != ''){
                    update_option("elex_auspost_uneligible_orders", $uneligible_orders);
                }
            }

            if($manifest_generation_type == 'order_id' && !empty($_POST['order_id'])){
                $requested_orders_ids = $_POST['order_id'];
                $orders_ids = explode(',', $requested_orders_ids);
                

                $order_validation_results = elex_auspost_validate_orders($orders_ids);

                if(is_array($order_validation_results) && !empty($order_validation_results)){
                    $uneligible_orders = $order_validation_results['invalid_orders'];
                    $eligible_orders = $order_validation_results['valid_orders'];
                }

                if($uneligible_orders != ''){
                    update_option("elex_auspost_uneligible_orders", $uneligible_orders);
                }
            }

            $shipment_ids_array = array();
            $orders_with_no_shipment_created = '';
            $orders_with_no_label_generated = '';
            $order_shipment_ids_array = array();
            $startrack_shipment_ids = array();

            if(!empty($eligible_orders)){
                $shipment_ids_to_store = get_option('elex_aus_post_manifest_generated_already_for_these_orders');
                if($manifest_generation_type == 'order_id'){
                    $shipment_ids_to_store = false;
                }               
                foreach($eligible_orders as $order_id){
                    $order = new WC_Order($order_id);
                    $shipment_ids = get_post_meta($order_id, 'wf_woo_australiapost_shipmentId', true);
                    $order_startrack_shipment_ids = get_post_meta($order_id, 'elex_auspost_startrack_shipment_ids', true);
                    if(!empty($order_startrack_shipment_ids)){
                        foreach($order_startrack_shipment_ids as $order_startrack_shipment_id){
                            if($shipment_ids_to_store){
                                if(!in_array($order_startrack_shipment_id, $shipment_ids_to_store)){
                                    $startrack_shipment_ids[] = $order_startrack_shipment_id;
                                }
                            }else{
                               $startrack_shipment_ids[] = $order_startrack_shipment_id; 
                            }
                            
                        }
                    }

                    $shipment_ids_for_order_id = array();
                    $order_meta_data = $order->get_meta_data();
                    $filtered_startrack_shipment_ids = array();
                    $filtered_auspost_shipment_ids = array();
                    if(is_array($shipment_ids) && !empty($shipment_ids)){
                        foreach($shipment_ids as $shipment_id){
                            $shipment_label_id = get_post_meta($order_id, 'wf_woo_australiapost_labelId'.$shipment_id, true);
                            if($shipment_ids_to_store){
                                if(!in_array($shipment_id, $shipment_ids_to_store)){
                                    if(!empty($shipment_label_id)){
                                        $shipment_ids_array[] = $shipment_id;
                                        $shipment_ids_for_order_id[] = $shipment_id;
                                    }else{
                                        $orders_with_no_label_generated = $orders_with_no_label_generated. $order_id . ',';
                                    }
                                }
                            }else{
                               if(!empty($shipment_label_id)){
                                    $shipment_ids_array[] = $shipment_id;
                                    $shipment_ids_for_order_id[] = $shipment_id;
                                }else{
                                    $orders_with_no_label_generated = $orders_with_no_label_generated. $order_id . ',';
                                } 
                            }

                            
                        }
                    }else if(!empty($shipment_ids)){
                        $shipment_ids_array[] = $shipment_ids;
                        $shipment_ids_for_order_id[] = $shipment_ids;
                    }else{
                        $orders_with_no_shipment_created = $orders_with_no_shipment_created . $order_id . ',';
                    }

                    foreach($shipment_ids_for_order_id as $shipment_id_for_order_id){
                        $order_shipment_ids_array[$order_id]['shipment_ids'][] =  $shipment_id_for_order_id;   
                    }
                }

                $eligible_shipment_ids = array();

                if(!empty($shipment_ids_array)){
                    foreach($shipment_ids_array as $shipment_id){
                        $eligible_shipment_ids[] = $shipment_id;                 
                    }
                }else{
                    $orders_with_no_shipment_created = rtrim($orders_with_no_shipment_created, ',');
                    update_option("elex_auspost_orders_with_no_shipments", $orders_with_no_shipment_created);

                    if($orders_with_no_label_generated != ''){
                        $orders_with_no_label_generated = rtrim($orders_with_no_label_generated, ',');
                        update_option('elex_auspost_orders_with_no_label_ids', $orders_with_no_label_generated);
                    }
                }
                // Compatibility of Australia Post with Multivendor.
                $vendor_check = (in_array('multi-vendor-add-on-for-thirdparty-shipping/multi-vendor-add-on-for-thirdparty-shipping.php', apply_filters('active_plugins', get_option('active_plugins'))) && (isset($auspost_settings['vendor_check']) && ($auspost_settings['vendor_check'] == 'yes' ))) ? TRUE : FALSE;
                $vendor_shipment = array();
                if($vendor_check){
                    $vendor_shipment = get_option('elex_australia_post_shipment_details')? get_option('elex_australia_post_shipment_details'): array();
                    $vendor_check = (!empty($vendor_shipment))? $vendor_check :FALSE;
                }
                foreach($eligible_shipment_ids as $shipment_id){
                    if($vendor_check){
                        $seller_id = $vendor_shipment[$shipment_id]['seller_id'];
                        if(!empty($startrack_shipment_ids) && in_array($shipment_id, $startrack_shipment_ids)){                            
                            $filtered_startrack_shipment_ids[$seller_id][] = $shipment_id;
                        }else{
                            $filtered_auspost_shipment_ids[$seller_id][] = $shipment_id;
                        }             
                    }else{
                        if(!empty($startrack_shipment_ids) && in_array($shipment_id, $startrack_shipment_ids)){
                            $filtered_startrack_shipment_ids[] = $shipment_id;
                        }else{
                            $filtered_auspost_shipment_ids[] = $shipment_id;
                        }
                    }
                   
                }
                // Compatibility of Australia Post with Multivendor.
                if($vendor_check){
                    update_option('creating_new_manifests_auspost_elex', true);                   
                    
                    if(!empty($filtered_startrack_shipment_ids)){
                        foreach($filtered_startrack_shipment_ids as $vendor_user_id => $filtered_startrack_shipment_vendor_ids )
                        {
                            $vendor_elex_australia_post_api_key = get_the_author_meta('vendor_elex_australia_post_api_key', $vendor_user_id); 
                            $vendor_elex_australia_post_startrack_account_number = get_the_author_meta( 'vendor_elex_australia_post_startrack_account_number', $vendor_user_id);
                            $vendor_elex_australia_post_startrack_api_password = get_the_author_meta( 'vendor_elex_australia_post_startrack_api_password', $vendor_user_id); 
                            update_option('create_manifest_startarck_auspost_elex', true);
                            elex_auspost_generate_bulk_order_manifest($vendor_elex_australia_post_api_key, $vendor_elex_australia_post_startrack_api_password, $vendor_elex_australia_post_startrack_account_number, $filtered_startrack_shipment_vendor_ids, $contracted_api_mode, $order_shipment_ids_array, $vendor_user_id );
                            delete_option('create_manifest_startarck_auspost_elex');
                        }
                        
                    }

                    update_option("manifest_generation_in_progress_auspost_elex", true);

                    if(!empty($filtered_auspost_shipment_ids)){
                        foreach($filtered_auspost_shipment_ids as $vendor_user_id => $filtered_auspost_shipment_vendor_ids)
                        {
                            $vendor_elex_australia_post_api_key = get_the_author_meta('vendor_elex_australia_post_api_key', $vendor_user_id); 
                            $vendor_elex_australia_post_account_number = get_the_author_meta( 'vendor_elex_australia_post_account_number', $vendor_user_id);
                            $vendor_elex_australia_post_api_password = get_the_author_meta( 'vendor_elex_australia_post_api_password' , $vendor_user_id);
                            elex_auspost_generate_bulk_order_manifest($vendor_elex_australia_post_api_key, $vendor_elex_australia_post_api_password , $vendor_elex_australia_post_account_number, $filtered_auspost_shipment_vendor_ids, $contracted_api_mode, $order_shipment_ids_array, $vendor_user_id );
                        }    
                    }

                    delete_option('startrack_manifest_generated_auspost_elex');
                    delete_option('manifest_generation_in_progress_auspost_elex');
                    delete_option('creating_new_manifests_auspost_elex');
                    
                }else{
                    update_option('creating_new_manifests_auspost_elex', true);

                    if(!empty($filtered_startrack_shipment_ids)){
                        update_option('create_manifest_startarck_auspost_elex', true);
                        elex_auspost_generate_bulk_order_manifest($api_key_starTrack, $contract_api_password_startrack, $contract_api_account_number_startrack, $filtered_startrack_shipment_ids, $contracted_api_mode, $order_shipment_ids_array);
                        delete_option('create_manifest_startarck_auspost_elex');
                    }

                    update_option("manifest_generation_in_progress_auspost_elex", true);

                    if(!empty($filtered_auspost_shipment_ids)){
                        elex_auspost_generate_bulk_order_manifest($contract_api_key, $contract_api_password, $contract_api_account_number, $filtered_auspost_shipment_ids, $contracted_api_mode, $order_shipment_ids_array);
                    }

                    delete_option('startrack_manifest_generated_auspost_elex');
                    delete_option('manifest_generation_in_progress_auspost_elex');
                    delete_option('creating_new_manifests_auspost_elex');
                }

                wp_redirect(admin_url('/admin.php?page=auspost_manifest'));
                exit(); 
            }else{
                $stored_invalid_orders = get_option('elex_auspost_uneligible_orders');

                /* Showing error notice for invalid Order ids */
                if($stored_invalid_orders != ''){
                    echo '<div class="error"><p>' . sprintf(__('MANIFEST NOT GENERATED, ORDERS NOT FOUND FOR THE ORDERS ID(S) %s', 'wf-shipping-auspost'), $stored_invalid_orders) . '</p></div>';
                    delete_option('elex_auspost_uneligible_orders');
                }else{
                    echo '<div class="error"><p>' . sprintf(__('NO ORDERS FOUND', 'wf-shipping-auspost')) . '</p></div>';
                }
                
            }
        }
    }

    /* Delete manifests for provided manifest number */
    function elex_auspost_delete_manifest_data($manifest_ids_to_delete){
        $manifest_history = get_option('elex_auspost_manifest_history');
        $ids_delete_to_manifest = array();
        foreach($manifest_ids_to_delete as $manifest_ids){
            $manifest_ids_array = explode(',', $manifest_ids);
            foreach($manifest_ids_array as $manifest_id){
                $ids_delete_to_manifest[] = $manifest_id;
            }
        }
        
        foreach ($ids_delete_to_manifest as $id_delete_to) {
           foreach($manifest_history as $order_manifests_key => $order_manifests){
                foreach($order_manifests as $order_manifests_data){
                    if($order_manifests_data['id'] == $id_delete_to){
                        unset($manifest_history[$order_manifests_key]);
                        break;
                    }
                }
           }
        }

        update_option('elex_auspost_manifest_history', $manifest_history);
        wp_redirect(admin_url('/admin.php?page=auspost_manifest'));
    }

    function get_custom_order_number($order){
        $auspost_settings = get_option('woocommerce_wf_australia_post_settings');
        $auspost_custom_order_id_enable = (isset($auspost_settings['custom_order_id_enable']) && ($auspost_settings['custom_order_id_enable'] == 'yes' )) ? true : false;
        if($auspost_custom_order_id_enable){
            $order_number = $order->get_order_number();
        }else{
            $order_number = wf_get_order_id($order);
        }
        return($order_number);
    }

    function wf_get_order_id( $order ){
        global $woocommerce;
        return ( WC()->version < '2.7.0' ) ? $order->id : $order->get_id();
    }

    /* Generates order summary for provided shipment ids */
    function elex_auspost_generate_bulk_order_manifest($contract_api_key, $contract_api_password, $contract_api_account_number, $shipment_ids, $contracted_api_mode, $order_shipment_ids_array, $seller_id = FALSE){

        $service_base_url = 'https://digitalapi.auspost.com.au/test/shipping/v1/';

        $shipping_ids_with_existing_manifests = '';

        $user_ok = elex_auspost_user_permission();
        if (!$user_ok){
            return;
        }

        if ($contracted_api_mode == 'live') {
            $service_base_url = str_replace('test/', '', $service_base_url);
        }

        $shipment_ids_array = array();
        $shipment_ids_to_store = false;
        if(!$shipment_ids_to_store){
            $shipment_ids_to_store = array();
        }
        foreach($shipment_ids as $shipment_id){
            if($shipment_ids_to_store){
                if(!in_array($shipment_id,$shipment_ids_to_store)){
                    $shipment_ids_array[]['shipment_id'] = $shipment_id;
                }
                
            }else{
                $shipment_ids_array[]['shipment_id'] = $shipment_id;
            }
            
            
            
        }

        $service_order_url = $service_base_url . 'orders';

        $info = array(
            'shipments' => $shipment_ids_array,
        );

        $rqs_headers = elex_auspost_buildHttpHeaders($info, $contract_api_key, $contract_api_password, $contract_api_account_number);
       
        $res = wp_remote_post($service_order_url, array(
            'method' => 'PUT',
            'httpversion' => '1.1',
            'headers' => $rqs_headers,
            'body' => json_encode($info)
        ));
        if (is_wp_error($res)) {
            $error_string = $res->get_error_message();
            update_option("manifest_generation_error_message", $error_string);
            wp_redirect(admin_url('admin.php?page=auspost_manifest'));
            exit;
        }

        $response_array = isset($res['body']) ? json_decode($res['body']) : array();
        $order_ids_shipment_ids_for_existing_manifest = '';
        $response_existing_manifests_details_array =  array();

        if (!empty($response_array->errors)) {
            $response_error = (array)$response_array->errors;
            if(is_array($response_error) && !empty($response_error)){
                foreach($response_error as $response_error_element){
                    if($response_error_element->code = '44016' || $response_error_element->code = '44017'){
                        if(isset($response_error_element->context->shipment_id)){
                            $shipping_ids_with_existing_manifests = $shipping_ids_with_existing_manifests.','.$response_error_element->context->shipment_id . ',';
                            $response_existing_manifests_details_array [$response_error_element->context->shipment_id] = array(
                                'shipment_id' => $response_error_element->context->shipment_id,
                                'manifest_number' => $response_error_element->context->order_id,
                            );
                        }
                    }
                }
            }

            if($shipping_ids_with_existing_manifests != ''){
                $shipping_ids_with_existing_manifests = ltrim($shipping_ids_with_existing_manifests, ',');
                $shipping_ids_with_existing_manifests = rtrim($shipping_ids_with_existing_manifests, ',');
                $shipping_ids_with_existing_manifests_array = explode(',', $shipping_ids_with_existing_manifests);
                foreach ($order_shipment_ids_array as $id => $shipments) {
                    foreach($shipments['shipment_ids'] as $shipment_id){
                        foreach($shipping_ids_with_existing_manifests_array as $shipping_ids_with_existing_manifests_element){
                            if($shipment_id == $shipping_ids_with_existing_manifests_element){
                                $order_ids_shipment_ids_for_existing_manifest .= ' '.$shipment_id. ',';     
                            }
                        }
                        if(!empty($response_existing_manifests_details_array)){
                            foreach($response_existing_manifests_details_array as $key=>$item){
                                if($key == $shipment_id){
                                    $response_existing_manifests_details_array[$key]['order_id'] = $id ;
                                }
                            }
                        }
                        
                    }
                    $order_ids_shipment_ids_for_existing_manifest = rtrim($order_ids_shipment_ids_for_existing_manifest, ',');
                    $order = wc_get_order($id);
                    $order_number = get_custom_order_number($order); 
                    $order_ids_shipment_ids_for_existing_manifest .= ' of Order id '.$order_number.",";
                }
                $order_ids_shipment_ids_for_existing_manifest = rtrim($order_ids_shipment_ids_for_existing_manifest, ',');
                update_option('elex_auspost_shipping_ids_with_existing_manifests', $order_ids_shipment_ids_for_existing_manifest);
                if(!empty($response_existing_manifests_details_array)){
                    update_option('elex_auspost_shipping_response_existing_manifests_details', $response_existing_manifests_details_array);
                }
                header("Refresh:0");
            }
        }
        
        $auspost_shipping_order_id = '';

        if(isset($response_array->order->order_id))
        {
            $order_ids_shipments_pairs_for_manifest_generated = '';
            $auspost_shipping_order_id = $response_array->order->order_id;
            $request_type_startrack = get_option('create_manifest_startarck_auspost_elex', false);
            if($request_type_startrack){
                update_option('startrack_manifest_generated_auspost_elex', true);
            }

            update_option("elex_auspost_current_order_id", $auspost_shipping_order_id);
            update_option('elex_auspost_manifest_generated', true);
            $manifest_generated_date = current_time('Y-m-d', 0);
            $request_type_startrack = get_option('create_manifest_startarck_auspost_elex', false);
            $order_shipment_number_array = array();
            $shipment_ids_to_store = get_option('elex_aus_post_manifest_generated_already_for_these_orders');
            if(!$shipment_ids_to_store){
                $shipment_ids_to_store = array();
            }
            foreach ($order_shipment_ids_array as $key => $order_shipment_id) {                
                foreach($response_array->order->shipments as $item){
                    foreach($order_shipment_id['shipment_ids'] as $shipment_id ){
                        if($item->shipment_id == $shipment_id){
                            $order = new WC_Order($key);
                            $order_shipment_number_array[$order->get_order_number()] = $order_shipment_id;
                            $shipment_ids_to_store[] = $shipment_id;
                            break 2;
                        }
                    }
                    
                }
            }
            update_option('elex_aus_post_manifest_generated_already_for_these_orders',$shipment_ids_to_store);
            
            $shipping_manifest_data = get_option('elex_auspost_shipping_manifest_data', true);
            if(!is_array($shipping_manifest_data))
                $shipping_manifest_data = array();
            
            $shipping_manifest_data [$auspost_shipping_order_id] = array(
                'manifest_id' => $auspost_shipping_order_id,
                'type' => $request_type_startrack? 'StarTrack': 'AusPost',
                'date' => $manifest_generated_date,
                'data' => $order_shipment_number_array,
            );
            update_option('elex_auspost_shipping_manifest_data', $shipping_manifest_data);
            $current_manifest_data = array('id' => $auspost_shipping_order_id, 'data' => $order_shipment_number_array, 'date' => $manifest_generated_date, 'type' => $request_type_startrack? 'StarTrack': 'AusPost');
            $stored_manifest_history = get_option('elex_auspost_manifest_history');
            $auspost_settings = get_option('woocommerce_wf_australia_post_settings');
            // Compatibility of Australia Post with Multivendor.
            if((in_array('multi-vendor-add-on-for-thirdparty-shipping/multi-vendor-add-on-for-thirdparty-shipping.php', apply_filters('active_plugins', get_option('active_plugins'))) && (isset($auspost_settings['vendor_check']) && ($auspost_settings['vendor_check'] == 'yes' )))){
                $stored_vendor_manifest_history = get_option('elex_auspost_vendor_manifest_history')?get_option('elex_auspost_vendor_manifest_history') : array();
                $stored_vendor_manifest_history [$auspost_shipping_order_id] = array('id' => $auspost_shipping_order_id, 'data' => $order_shipment_number_array, 'seller_id' => $seller_id);
                update_option('elex_auspost_vendor_manifest_history', $stored_vendor_manifest_history);
            }
            $manifest_history_new = array();
            $new_manifest_generation = get_option('creating_new_manifests_auspost_elex', false);
            $startrack_manifest_generated = get_option('startrack_manifest_generated_auspost_elex', false);
            $manifest_generation_progress = get_option('manifest_generation_in_progress_auspost_elex', false);
            if(!empty($stored_manifest_history)){
                
                    $stored_manifest_history[$auspost_shipping_order_id][] =  $current_manifest_data;
                
                update_option('elex_auspost_manifest_history', $stored_manifest_history); 
            }else{
                $manifest_history_new[$auspost_shipping_order_id][] = $current_manifest_data;
                update_option('elex_auspost_manifest_history', $manifest_history_new);
            }            
            foreach ($order_shipment_ids_array as $id => $shipments) {
                foreach($shipments['shipment_ids'] as $shipment_id ){
                    $order_ids_shipments_pairs_for_manifest_generated .= 'for shipment(s) '.$shipment_id.',';
                }
                $order_ids_shipments_pairs_for_manifest_generated = rtrim($order_ids_shipments_pairs_for_manifest_generated, ',');
                $order = wc_get_order($id);
                $order_number = get_custom_order_number($order); 
                $order_ids_shipments_pairs_for_manifest_generated .= ' of Order id '.$order_number.',';
            }
            $order_ids_shipments_pairs_for_manifest_generated = rtrim($order_ids_shipments_pairs_for_manifest_generated, ',');
            update_option('elex_auspost_orders_shipments_for_generated_manifest', $order_ids_shipments_pairs_for_manifest_generated);
        }else{
            update_option('elex_auspost_manifest_generated', false);
        }
        return;   
    }

    /* Print generated order summary */
    function elex_auspost_print_order_summary($auspost_shipping_order_id, $contract_api_key, $contract_api_password, $contract_api_account_number, $contracted_api_mode, $manifest_download_method){
        if($auspost_shipping_order_id != ''){
            $service_base_url = 'https://digitalapi.auspost.com.au/test/shipping/v1/';

            $get_order_summary_url = $service_base_url.'accounts/'.$contract_api_account_number.'/orders/'.$auspost_shipping_order_id.'/summary';

            if ($contracted_api_mode == 'live') {
                $get_order_summary_url = str_replace('test/', '', $get_order_summary_url);
            }
            $rqs_headers = array(
                'Authorization' => 'Basic ' . base64_encode($contract_api_key . ':' . $contract_api_password),
                'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
                'Account-Number' => $contract_api_account_number,
            );
            $order_manifest_response = wp_remote_get($get_order_summary_url, array(
                         'headers' => $rqs_headers,
                )
            );
            $decoded_response_body = $order_manifest_response['body'];

            $file = 'Australia-Post-' . $auspost_shipping_order_id . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: '.$manifest_download_method.'; filename='.basename($file));
            ob_clean(); flush();
            print($decoded_response_body);
            exit;
        }else{
            echo '<div class="error"><p>' . sprintf(__('No Manifest found to print', 'wf-shipping-auspost')) . '</p></div>';
        }
    }

    function elex_retrive_manifest_form($auspost_shipping_order_id, $contract_api_key, $contract_api_password, $contract_api_account_number, $contracted_api_mode, $manifest_download_method,$email=''){
        if($auspost_shipping_order_id != ''){
            $service_base_url = 'https://digitalapi.auspost.com.au/test/shipping/v1/';

            $get_order_summary_url = $service_base_url.'accounts/'.$contract_api_account_number.'/orders/'.$auspost_shipping_order_id.'/summary';

            if ($contracted_api_mode == 'live') {
                $get_order_summary_url = str_replace('test/', '', $get_order_summary_url);
            }

            $rqs_headers = array(
                'Authorization' => 'Basic ' . base64_encode($contract_api_key . ':' . $contract_api_password),
                'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
                'Account-Number' => $contract_api_account_number,
            );
            $order_manifest_response = wp_remote_get($get_order_summary_url, array(
                         'headers' => $rqs_headers,
                )
            );

            if (is_wp_error($order_manifest_response)) {
                $error_string = $order_manifest_response->get_error_message();
                echo '<div class="error"><p>' . sprintf($error_string) . '</p></div>';
                exit;
            }

            $decoded_response_body = $order_manifest_response['body'];
            
            //To get woocommerce order details
            $woo_order_details = "https://digitalapi.auspost.com.au/test/shipping/v1/orders/".$auspost_shipping_order_id;

            if ($contracted_api_mode == 'live') {
                $woo_order_details = str_replace('test/', '', $woo_order_details);
            }

            $rqs_headers_details = array(
                'Authorization' => 'Basic ' . base64_encode($contract_api_key . ':' . $contract_api_password),
                'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
                'Account-Number' => $contract_api_account_number,
            );
            $order_manifest_response_details = wp_remote_get($woo_order_details, array(
                'headers' => $rqs_headers_details,
                )
            );
            if (is_wp_error($order_manifest_response_details)) {
                $error_string = $order_manifest_response_details->get_error_message();
                echo '<div class="error"><p>' . sprintf($error_string) . '</p></div>';
                exit;
            }
            $response_body = isset($order_manifest_response_details['body']) ? json_decode($order_manifest_response_details['body']) : array();
            

            if (!empty($response_body->errors)) {
                $error_string = $response_body->errors[0]->message;
                $error_string = str_replace("order","manifest", $error_string);
                echo '<div class="error"><p>' . sprintf($error_string) . '</p></div>';
                exit;
            }            
            
            foreach (json_decode($order_manifest_response_details['body']) as $key => $value) {
                $data = "<table style='border: 2px solid black;'>   <tr style='border: 2px solid black;'>
                                        <td style='border: 2px solid black;'>
                                            <label style='color: green;'> Shipment Id </label>
                                        </td>
                                        <td style='border: 2px solid black;'>
                                            <label style='color: green;'> Order Number </label>
                                        </td>
                                    </tr>";
                    foreach ($value as $keyy => $valuee) {
                        if($keyy == 'shipments'){
                            foreach ($valuee as $k => $v) {
                                
                                $data .="
                                    <tr style='border: 2px solid black;'>
                                        <td style='border: 2px solid black;'>
                                            ".$v->shipment_id."
                                        </td>
                                        <td style='border: 2px solid black;'>
                                           ".$v->customer_reference_1."
                                        </td>
                                    </tr>";
                                

                                
                            }
                        }
                    }
                    $data .= "</table>"; 
                
            }

           
            $file = 'Australia-Post-' . $auspost_shipping_order_id . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: '.$manifest_download_method.'; filename='.basename($file));
            ob_clean(); flush();
            print($decoded_response_body);
            $email_content = "Hi There!<br><br>Please find the manifest form details here!";
            $email_content .= $data;
            $email_content .= "<br><br><br>The manifest form is attached to this email.";
            $upload_dir = wp_upload_dir();
            $base = $upload_dir['basedir'];
            $path = $base . "/elex-auspost-manifest/";
            wp_mkdir_p($path);
            $file = 'Australia-Post-' . $auspost_shipping_order_id . '.pdf';
            $file_path = $path.$file;
            file_put_contents($file_path, $decoded_response_body);
            if (file_exists($file)) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="'.basename($file).'"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($file));
            }      

            add_filter( 'wp_mail_content_type','elex_manifest_email' );
           
   
            if($email){
                wp_mail( $email,'Manifest Form - ['.$auspost_shipping_order_id.']',$email_content, '', $file_path);
            }     
            unlink($file_path);
            exit;
        }else{
            echo '<div class="error"><p>' . sprintf(__('No Manifest found to print', 'wf-shipping-auspost')) . '</p></div>';
        }
        
    }

    /* Checking is the user a administrator or shop manager */
    function elex_auspost_user_permission() {
        // Check if user has rights to generate invoices
        $current_user = wp_get_current_user();
        $user_ok = false;
        if ($current_user instanceof WP_User) {
            if (in_array('stores', $current_user->roles) || 
            in_array('steve_wells', $current_user->roles) || 
            in_array('simon_grownow', $current_user->roles) || 
            in_array('administrator', $current_user->roles) || 
            in_array('shop_manager', $current_user->roles)) {
                $user_ok = true;
            }
        }
        return $user_ok;
    }

    /* Building HTTP headers */
    function elex_auspost_buildHttpHeaders($request, $contract_api_key, $contract_api_password, $contract_api_account_number) {
        $a_headers = array(
            'Authorization' => 'Basic ' . base64_encode($contract_api_key . ':' . $contract_api_password),
            'content-length' => strlen(json_encode($request)),
            'content-type' => 'application/json',
            'AUSPOST-PARTNER-ID' => 'ELEXTENSION-7752',
            'Account-Number' => $contract_api_account_number
        );
        return $a_headers;
    }

    /* Validating WooCommerce orders*/
    function elex_auspost_validate_orders($orders_ids){
        global $wpdb;
        
        $valid_orders = array();
        $invalid_orders = '';
        foreach ($orders_ids as $order_id) {

            $order_id_str = "'".$order_id."'";
            $results = $wpdb->get_results( "SELECT post_id FROM ".$wpdb->prefix."postmeta WHERE meta_key = '_order_number_formatted' AND meta_value = ".$order_id_str);
          
            if(empty($results))
            {
                $order = wc_get_order( $order_id );
                if(empty($order))
                {
                    $invalid_orders = $invalid_orders. ' ' .$order_id. ',';
                }
                else
                {
                    $valid_orders[] = $order_id;
                }
            }
            else
            {
                $valid_orders[] = $results[0]->post_id;;
            }
        }
        $output_data = array(
            'valid_orders' => $valid_orders,
            'invalid_orders' => $invalid_orders
        );

        return $output_data;
    }
    function elex_manifest_email(){
        return "text/html";
    }

?>