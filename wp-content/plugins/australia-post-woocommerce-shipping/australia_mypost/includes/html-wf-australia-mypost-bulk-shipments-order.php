<?php

if (!class_exists('Wf_Ausmypost_Bulk_Shipments_Orders_Pickup_Class')) {

	class Wf_Ausmypost_Bulk_Shipments_Orders_Pickup_Class {

		public function __construct() {
			add_action('admin_menu', array($this,'elex_register_ausmypost_bulk_shipments_order_menu'));
			add_action('wp_ajax_elex_bulk_orders_table_data', array($this,'elex_ausmypost_bulk_orders_table_data_callback'));
			$this->elex_ausmypost_bulk_orders_table_init();
		}

		/* Ajax function for datatables */
		public function elex_ausmypost_bulk_orders_table_data_callback() {

			global $wpdb;
			$request          = $_GET;
			$offset           = $request['start']*$request['length'];
			$total_page_items = $request['length'];
			$prefix           =  $wpdb->prefix;
			$search_val       = $request['search']['value'];
			$total_rows       = "SELECT id FROM {$prefix}elex_bulk_shipments_orders_details";
			$total_rows       = $wpdb->get_results( ( $wpdb->prepare( '%1s', $total_rows ) ? stripslashes( $wpdb->prepare( '%1s', $total_rows ) ) : $wpdb->prepare( '%s', '' ) ), ARRAY_A);
			$data             = array();
			
			if (!empty($search_val)) {
				$search_query = "SELECT * FROM {$prefix}elex_bulk_shipments_orders_details WHERE  {$prefix}elex_bulk_shipments_orders_details.shipments_order_ids LIKE '%{$search_val}%' ORDER BY id DESC LIMIT  {$offset} , {$total_page_items}";
			} else {
				$search_query = "SELECT * FROM {$prefix}elex_bulk_shipments_orders_details ORDER BY id DESC LIMIT  {$offset} , {$total_page_items}";
			}
			
			$resulted_rows = $wpdb->get_results( ( $wpdb->prepare( '%1s', $search_query ) ? stripslashes( $wpdb->prepare( '%1s', $search_query ) ) : $wpdb->prepare( '%s', '' ) ), ARRAY_A );

			$total_data = count($resulted_rows);
			if (!empty($resulted_rows)) {
				foreach ($resulted_rows as $key=> $row_data) {
					$nestedData         = array();
					$order_details      = unserialize($row_data['order_details']);
					$order_number_lists = unserialize($row_data['order_numbers']);
					$order_ids 			= array_keys($order_number_lists);
					$orders_id_links	= array();
					foreach ($order_ids as $id ) {
						$orders_id_links[] = '<a href="' . admin_url('/post.php?post=' . $id . '&action=edit') . '" target="_blank" >' . $id . '</a>';
					}
					$order_numbers = join(', ', $orders_id_links);
					$order_date    = $order_details->order_creation_date;
					$pickup_id     = $row_data['orders_pickup_id'];
					$label_url     = $row_data['label_url'];

					$nestedData[]     = $row_data['shipments_order_ids'];
						$nestedData[] = date('Y-m-d H:i:s', strtotime($order_date));
						$nestedData[] = $order_numbers;
					
					if ('NA' !== $pickup_id) {
						$pickup_detail     = unserialize($row_data['order_pickup_details']);
						$pickup_start_time = $pickup_detail[$pickup_id]['pickup_starttime'];
						$pickup_end_time   = $pickup_detail[$pickup_id]['pickup_endtime'];
						$pickup_date       = $pickup_detail[$pickup_id]['pickup_date'];
						$nestedData[]      = $pickup_id;
						$nestedData[]      = 'Pickup Date:' . $pickup_date . '<br> Between: ' . $pickup_start_time . ' - ' . $pickup_end_time;
					} else {
						$nestedData[] = 'Not Generated';
						$nestedData[] = 'Not Generated';
					}
					if ('NA' !== $label_url) {
						$nestedData[] = '<center><a href="' . $label_url . '" target="_blank" ><i class="fa fa-download"></a></center>';
					} else {
						$nestedData[] = 'Not Generated';
					}

					$data[] = $nestedData;
				}
				$json_data = array(
					'draw' => intval($request['draw']),
					'recordsTotal' => count($total_rows),
					'recordsFiltered' => intval($total_data),
					'data' => $data
					);
					echo json_encode($json_data);				  
			} else {
				$json_data = array(
					'data' => array()
					);
				echo json_encode($json_data);
			}
			wp_die();
		}

		/* Mypost Business Bulk Shipments and Order Generation  Functions */
		public function elex_register_ausmypost_bulk_shipments_order_menu() {
			
			add_submenu_page('woocommerce', 'MyPost Business Bulk Shipments', 'MyPost Business Bulk Shipments', 'manage_woocommerce', 'elex_mypost_bulk_shipment_order', array($this,'elex_ausmypost_html_bulk_shipments_order_pickup'));
		}

		/* Create Table */
		public function elex_ausmypost_bulk_orders_table_init() {
			global $wpdb;
			$charsets = '';
			if ( $wpdb->has_cap( 'collation' ) ) {
				$charsets = $wpdb->get_charset_collate();
			}

			$wpdb->query(
				'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'elex_bulk_shipments_orders_details' . '(
                    `id`                             int(11) NOT NULL AUTO_INCREMENT,
                    `shipments_order_ids`            varchar(400) NOT NULL,
                    `order_details`					 longtext NOT NULL,
                    `order_numbers`					 longtext NOT NULL,
                    `orders_pickup_id`            	 varchar(400) NOT NULL,
                    `order_pickup_details`			 longtext NOT NULL,
                    `label_url`                      varchar(400) NOT NULL,
                    `label_request_id`				 varchar(400) NOT NULL,
                    `total_shipments_ids`			 longtext NOT NULL,
                     PRIMARY KEY ( `id` )' .
				") $charsets AUTO_INCREMENT = 1"
			);
		}

		/* Frontend for Bulk process */
		public function elex_ausmypost_html_bulk_shipments_order_pickup() {
			$url                = untrailingslashit(plugins_url()) . '/australia-post-woocommerce-shipping/australia_mypost/images/australiapost-6.png';
			$ausmypost_settings = get_option('woocommerce_wf_australia_mypost_settings');
			$pick_enable_check  = ( isset($ausmypost_settings['shipment_pickup_service']) && 'yes'=== $ausmypost_settings['shipment_pickup_service'] ) ? true : false;
 
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
				#elex_mypost_bulk_order_details_wrapper.dataTables_wrapper .dataTables_length select {
					line-height:2;
					display: inline-block;
					width: 50px;
					vertical-align: middle;
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
					width: 60%;
					background: #345F90;
					height: 100%;
					position: absolute;
					top: 0;
					left: 0;
					transition: all 250ms ease-in-out;
					
				}
				.tab-slider--tabs.slide:after{
					left: 60%;
					width: 40%;
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
	
					jQuery('#elex_mypost_bulk_order_details').dataTable({
						"processing": true,
						"serverSide": true,
						"ordering"  : false,
						"ajax": elex_ausmypost_custom.ajax_url
					});
					jQuery('.australia_mypost_pickup').hide();
					jQuery('#elex_mypost_generate_label').hide();
					jQuery('#elex_mypost_bulk_order_details').parents('div.dataTables_wrapper').first().hide();
					jQuery(".tab-slider--nav li").click(function() {
						jQuery(".tab-slider--body").hide();
						var activeTab = jQuery(this).attr("rel");
						jQuery("#"+activeTab).fadeIn();
							if(jQuery(this).attr("rel") == "tab2"){
								jQuery('.tab-slider--tabs').addClass('slide');
								jQuery('#elex_mypost_generate_label').show();
								jQuery('#elex_mypost_bulk_order_details').parents('div.dataTables_wrapper').first().show();
								jQuery('#elex_auspost_manifest_table').hide();
								jQuery('#elex_mypost_bulk_order_info').hide();								
							}else{
								jQuery('.tab-slider--tabs').removeClass('slide');
								jQuery('#elex_auspost_manifest_table').show();
								jQuery('#elex_mypost_bulk_order_info').show();
								jQuery('#elex_mypost_generate_label').hide();
								jQuery('#elex_mypost_bulk_order_details').parents('div.dataTables_wrapper').first().hide();

							}
						jQuery(".tab-slider--nav li").removeClass("active");
						jQuery(this).addClass("active");
					});
					jQuery('.manifest_generation_type').change(function(){
						var method = jQuery('.manifest_generation_input').val();
						switch(method)
						{
							case 'order_id':
								jQuery('.order_id').show();
								jQuery('#order_date_range').hide();
								break;
							case 'order_date_range':
								jQuery('.order_id').hide();
								jQuery('#order_date_range').show();
								jQuery('.order_id').prop('required', false);
								break;
							default:
								jQuery('.order_id').show();
								jQuery('.order_date_range').hide();
								jQuery('.order_id').prop('required', true);
						}
					});
	
					jQuery('#start_date_range').change(function(){
						var start = jQuery('#start_date_range').val();
						jQuery('#end_date_range').attr('min', start);
	
					});
	
					jQuery('#end_date_range').change(function(){
						var end = jQuery('#end_date_range').val();
						jQuery('#start_date_range').attr('max', end);
	
					});
	
					jQuery("#ausmypost_pickup_check").on('change', function(e) {
						e.preventDefault();
						if(this.checked){
							jQuery('.australia_mypost_pickup').show();
						}else{
							jQuery('.australia_mypost_pickup').hide();
						}
					});
	
					var method_on_load = jQuery('.manifest_generation_input').val();
	
					if(method_on_load == 'order_id'){
						jQuery('.order_id').show();
						jQuery('.order_id').prop('required', true);
					}
				});
			</script>
	
			<div class="elex-aus_post_manifest_form">
	
			<header class="bg-dark dk header" style="background-color: #345F90;margin-left: 0px;" > 
				<div class="navbar-header aside-md" style="height: 35px;font-size: 18px;width: 100%;padding-top: 15px;"> <b>
					<h3 style="color: white !important;margin-left: 4px;margin-top: -0.3px;"><?php _e('MyPost Business Bulk Shipments Generation and Pickup Process', 'wf-auspost-shipping'); ?></h3> 
					<img  src="<?php echo( $url ); ?>" style="width:100px;float: right; margin-top: -52px;width: 141px;margin-bottom: 33px;"></img>  
				</div> 
			</header> 
			<div class="tab-slider--nav" >
				<ul class="tab-slider--tabs">
					<li class="tab-slider--trigger active" rel="tab1"><?php _e('Bulk Shipment and Pickup Generation', 'wf-auspost-shipping'); ?></li>
					<li class="tab-slider--trigger" rel="tab2"> <?php _e('Shipment Order List', 'wf-auspost-shipping'); ?></li>
				</ul>
	
				<br>
				<br>
				<hr></hr>
				
			</div>
	
			<form id="elex_auspost_manifest_form" method="POST" style="margin-left: 4px; margin-top: 6px;">
 
				<table id="elex_auspost_manifest_table" >       
	
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
									<option value="order_date_range"><?php _e('Order Date Range', 'wf-auspost-shipping'); ?></option>
								</select>
							</div>
						</td>
						<td>
							<div class="elex_generate">
								<a type="button" class="" data-toggle="modal" data-target="#choose-option-info-model"><?php _e('More Info', 'wf-auspost-shipping'); ?></a>
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
							
								<span class="tooltip" data-tooltip-position="right" data-tooltip=" <?php _e('Multiple order ids should be comma-separated. Eg: 1,2,3', 'wf-auspost-shipping'); ?>">?</span>
						
							</div>
						</td>
					</tr>
					<tr id="order_date_range" style="display: none">
						<td style="padding-right: 10px;">
							<div class="elex_generate">
								<h3><?php _e('Starting Date', 'wf-auspost-shipping'); ?></h3>
							</div>
						</td>
						<td style="padding-right: 10px;width: 100px; ">
							<div class="elex_generate">
								<input type="date" id="start_date_range" placeholder="" name="starting_order_date" class=""/>
							</div>
						</td>
						<td style="padding-right: 10px;">
							<div class="elex_generate">
								<h3><?php _e('Ending Date', 'wf-auspost-shipping'); ?></h3>
							</div>
						</td>
						<td style="width: 100px">
							<div class="elex_generate">
								<input type="date" id="end_date_range" name="ending_order_date" placeholder="" class=""/>
							</div>
						</td>
					
					</tr>

					<?php if ($pick_enable_check) { ?>
							<tr>
								<td style="padding-right: 10px;">
									<div class="elex_generate">
										<h3><?php _e('Add Pickup Service', 'wf-auspost-shipping'); ?></h3>
									</div>
								</td>
								<td>
									<div class="elex_generate">
										<input type="checkbox" id="ausmypost_pickup_check" name ="ausmypost_pickup_check" class= 'ausmypost_pickup_checked' value='yes'> <span class="tooltip" data-tooltip-position="right" data-tooltip=" <?php _e('Please note pickups can be ordered on the same day if prior to 1pm or next business day.If a scheduled pick-up falls on a public holiday or weekend, it will be collected on the next business day.', 'wf-auspost-shipping'); ?>">?</span>
									</div>       
								</td>
							</tr>
							<tr class="australia_mypost_pickup" >
								<td style="padding-right: 10px;">
									<div class="elex_generate">
										<h3><?php _e('Pickup Date', 'wf-auspost-shipping'); ?></h3>
									</div>
								</td>
								<td>
									<div class="elex_generate">
									<input  type='date' name = 'australia_mypost_pickup_date' min="<?php esc_html_e( wp_date('Y-m-d') ); ?>" value="<?php esc_html_e( wp_date('Y-m-d') ); ?>" id='australia_mypost_pickup_date' required><span class="tooltip" data-tooltip-position="right" data-tooltip=" <?php _e('Select a pickup date. Please note it should be a business day.', 'wf-auspost-shipping'); ?>">?</span>
									</div>       
								</td>
							</tr>
							<tr class="australia_mypost_pickup">
								<td style="padding-right: 10px;">
									<div class="elex_generate">
										<h3><?php _e('Pickup Time', 'wf-auspost-shipping'); ?></h3>
									</div>
								</td>
								<td>
									<div class="elex_generate">
										<select id='australia_mypost_pickup_service_id' name ='australia_mypost_pickup_service_id'>
											<option value="PU1" selected><?php _e('Same Day Pick up 9am - 4pm (Cut off time is 1pm.)', 'wf-shipping-auspost'); ?></option>
											<option value="PU3"><?php _e('Next Business Day 8am  - 12 noon (Cut off time 11.50 pm.)', 'wf-shipping-auspost'); ?></option>
											<option value="PU4"><?php _e('Next Business Day 9am  - 1pm (Cut off time 11.50 pm.)', 'wf-shipping-auspost'); ?></option>
											<option value="PU5"><?php _e('Next Business Day 10am - 2pm (Cut off time 11.50 pm.)', 'wf-shipping-auspost'); ?></option>
											<option value="PU6"><?php _e('Next Business Day 11am - 3pm (Cut off time 11.50 pm.)', 'wf-shipping-auspost'); ?></option>
											<option value="PU7"><?php _e('Next Business Day 12pm - 4pm (Cut off time 11.50 pm.)', 'wf-shipping-auspost'); ?></option>
										</select>
									</div>       
								</td>
							</tr>
					<?php } ?>

					<tr>
						
						<td>
							<div class="elex_generate">
								<input type="submit" value="<?php _e('Generate Shipments', 'wf-auspost-shipping'); ?>" class="button-primary" name="generate_shipment_order" data-tip="<?php _e('Generate Order with Shipment', 'wf-shipping-auspost'); ?>">
							</div>
						</td>
						
					</tr>
				</table>

				<table id ="elex_mypost_generate_label">
					<tr class="elex-aus_post_retrive">
						<td>
							<div>
								<input type="text" name="order_shipments_id" class="manifest_retrive_input" placeholder="Eg : TB00000000" />
								<span class="tooltip" data-tooltip-position="right" data-tooltip=" <?php _e('Enter Shipment Order Number to generate label(s)', 'wf-auspost-shipping'); ?>">?</span>
							</div>
						</td>
						<td>
							<div>
								<input type="submit" value="<?php _e('Generate Label', 'wf-auspost-shipping'); ?>" class="button-primary" name="generate_label" data-tip="<?php _e('Generate Label', 'wf-shipping-auspost'); ?>">
							</div>
						</td>
					</tr>
				</table>
				<br><hr>
				<table id="elex_mypost_bulk_order_details"  style="width:100%" class="cell-border hover stripe" > 
					<thead> 
						<tr> 
							<th> <?php _e('Shipment Order Number', 'wf-shipping-auspost'); ?></th>
							<th> <?php _e('Shipment Order Date', 'wf-shipping-auspost'); ?></th>
							<th> <?php _e('WooCommerce Order Id(s)', 'wf-shipping-auspost'); ?></th>
							<th> <?php _e('Pickup Id', 'wf-shipping-auspost'); ?></th>
							<th> <?php _e('Pickup Date', 'wf-shipping-auspost'); ?></th>
							<th><?php _e('Label', 'wf-shipping-auspost'); ?></th>
						</tr> 
					</thead> 
				</table>
				<small id='elex_mypost_bulk_order_info' class="description"><?php esc_html_e( 'Bulk Shipment will generate the shipment based on the Packaging Option selected on the Settings page. By default, the Australia Post service selected by the customer during the checkout will be automatically chosen. If it is unavailable, default services configured in the Label and Tracking settings will be automatically picked for printing the label.', 'wf-shipping-auspost' ); ?></small><br>
				<small id='elex_mypost_bulk_order_info' class="description"><?php esc_html_e( 'You may print labels from individual WooCommerce order pages for more flexibility in choosing the service and changing the package dimensions.', 'wf-shipping-auspost' ); ?></small>
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
					<p> <?php _e('A Bulk order request will be submitted to MyPost Business based on following filters:', 'wf-auspost-shipping'); ?></p>
									<ol>
										<!--<li><?php //_e('“ALL” – Includes Shipment ids of recent 30 orders.', 'wf-auspost-shipping'); ?></li>--> <!-- Will provide in next version-->
										<li><?php _e('Order ID – Specify order ids, separated by comma.', 'wf-auspost-shipping'); ?></li>
										<li><?php _e('Date Range – Specify a date range.', 'wf-auspost-shipping'); ?></li>
									</ol>
									<p><?php _e('<i>Note* </i> At one time, the maximum limit for shipment generation and pickup is 50. Based on the packaging method selected, for a single order, more then one shipment IDs can be generated.', 'wf-auspost-shipping'); ?></p>
								</ul>
					</div>
					<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal"><?php _e('Close', 'wf-auspost-shipping'); ?></button>
					</div>
				</div>
				
				</div>
			</div>
	
			<?php 
	
			$manifest_status = get_option('elex_ausmypost_order_generated');
			
			/* Showing success notice for Order  generation for provided Shipments */
			if (!empty($manifest_status)) {
				if ($manifest_status['success']) {
					if (isset($manifest_status['label_url'])) {
						echo '<div class="notice notice-success"><p>' . __('Order Generated: ', 'wf-shipping-auspost') . $manifest_status['order_number'] . '    <a href="' . $manifest_status['label_url'] . '" target="_blank" >' . __('[Download Label]', 'wf-shipping-auspost') . '</a></p></div>';
						
					} else {
						echo '<div class="notice notice-success"><p>' . __('Order Generated: ', 'wf-shipping-auspost') . $manifest_status['order_number'] . '</p></div>';
					}
					delete_option('elex_ausmypost_order_generated');
				}
			
			}
	
			/*Showing  Existing Shipments Orders */
			$shipment_order_generated =  get_option('elex_ausmypost_exists_orders');
			if (!empty($shipment_order_generated) && is_array($shipment_order_generated)) {
				$exits_order_list = implode(',', $shipment_order_generated);
				echo '<div class="error"><p>' . __('SHIPMENT ORDERS ALREADY GENERATED, ORDERS ID(S)' . $exits_order_list, 'wf-shipping-auspost') . '</p></div>';
				delete_option('elex_ausmypost_exists_orders');
			}

			if (!empty($_POST)) {
				
				/** Generate Label with Shipment Order ID */
				if (isset($_POST['generate_label']) && 'Generate Label' == $_POST['generate_label'] ) {
					if (isset($_POST['order_shipments_id'])) {

						$generte_label_id_order =isset( $_POST['order_shipments_id'])?  $_POST['order_shipments_id']: '';

						if ('' !== $generte_label_id_order) {
							$response = $this->elex_ausmypost_generate_label_order($generte_label_id_order);
							if ($response['success']) {
								update_option('elex_ausmypost_order_generated', $response);
							} else {
								echo '<div class="error"><p>' . $response['error'] . '</p></div>';
								return;
							}
						} else {
							echo '<div class="error"><p>' . __('Please Enter a Valid Shipment Order Number.', 'wf-shipping-auspost') . '</p></div>';
						}
					
					} else {
						echo '<div class="error"><p>' . __('Please Enter a Valid Shipment Order Number.', 'wf-shipping-auspost') . '</p></div>';
					}
					wp_redirect(admin_url('/admin.php?page=elex_mypost_bulk_shipment_order'));
				}
				/* BULK SHIPMENT GENERATION PART */
				if (isset($_POST['generate_shipment_order']) && 'Generate Shipments' == $_POST['generate_shipment_order']) {
					$manifest_generation_type = isset($_POST['manifest_generation_type'])? $_POST['manifest_generation_type']: '';
					$manifest_pickup_checkbox = ( isset($_POST['ausmypost_pickup_check'])&& 'yes' == $_POST['ausmypost_pickup_check'] )? true : false;
					$manifest_pickup          = array();
					$orders_ids               = array();
					$uneligible_orders        = '';
					$eligible_orders          = array();
					if ($manifest_pickup_checkbox) {
						$manifest_pickup['date'] = isset($_POST['australia_mypost_pickup_date'])? $_POST['australia_mypost_pickup_date']: '';
						$manifest_pickup['time'] = isset($_POST['australia_mypost_pickup_service_id'])? $_POST['australia_mypost_pickup_service_id']: '';
					}
	
					if ($manifest_generation_type == 'order_date_range' && !empty($_POST['starting_order_date']) && !empty($_POST['ending_order_date'])) {
						$starting_order_date = $_POST['starting_order_date'];
						$ending_order_date   = $_POST['ending_order_date'];
						$query               = new WC_Order_Query( array(
							'date_created' => $starting_order_date . '...' . $ending_order_date,
							'return'    => 'ids',
						) );
						$orders_ids          = $query->get_orders();
							
						$order_validation_results = $this->elex_ausmypost_validate_orders($orders_ids);
						if (is_array($order_validation_results) && !empty($order_validation_results)) {
							$uneligible_orders = $order_validation_results['invalid_orders'];
							$eligible_orders   = $order_validation_results['valid_orders'];
						}
	
						if ($uneligible_orders != '') {
							update_option('elex_ausmypost_uneligible_orders', $uneligible_orders);
						}
					}
	
					if ($manifest_generation_type == 'order_id' && !empty($_POST['order_id'])) {
						$requested_orders_ids = $_POST['order_id'];
						$orders_ids           = explode(',', $requested_orders_ids);
						
	
						$order_validation_results = $this->elex_ausmypost_validate_orders($orders_ids);
	
						if (is_array($order_validation_results) && !empty($order_validation_results)) {
							$uneligible_orders = $order_validation_results['invalid_orders'];
							$eligible_orders   = $order_validation_results['valid_orders'];
						}
	
						if ($uneligible_orders != '') {
							update_option('elex_ausmypost_uneligible_orders', $uneligible_orders);
						}
					}
	
					$shipment_order_not_generated = array();
					$shipment_order_generated     = array();
					$total_shipments_ids          = array();
					if (!empty($eligible_orders)) {
	
						foreach ($eligible_orders as $order_id) {
	
							$shipment_order_ids = get_post_meta($order_id, 'wf_australia_mypost_order');
							if (empty($shipment_order_ids)) {
								$shipment_order_not_generated[ $order_id ]['order_number'] = $order_id;
							} else {
								$shipment_order_generated[] = $order_id;
							}
							$shipment_ids = get_post_meta($order_id, 'wf_woo_australiamypost_shipmentId', true);
	
							if (!empty( $shipment_ids) && isset( $shipment_order_not_generated[$order_id] ) ) {
								$shipment_order_not_generated[ $order_id ]['shipments_ids'] = $shipment_ids;
								foreach ($shipment_ids as $k => $v ) {
									$total_shipments_ids[] = $v;
								}
							}
						}
						if (count($total_shipments_ids) > 50) {
							echo '<div class="error"><p>' . __('TOTAL NUMBER OF SHIPMENTS EXCEEDS 50', 'wf-shipping-auspost') . '</p></div>';
							return;
						}
						update_option('elex_ausmypost_exists_orders', $shipment_order_generated);
	
						if (!empty($shipment_order_not_generated)) {
	
							$bulk_shipments_order = $this->elex_ausmypost_generate_bulk_shipment_order_pickup($shipment_order_not_generated, $manifest_pickup);
							if ($bulk_shipments_order['success']) {
								update_option('elex_ausmypost_order_generated', $bulk_shipments_order);
							} else {
								echo '<div class="error"><p>' . $bulk_shipments_order['error'] . '</p></div>';
								return;
							}
						}
	
						wp_redirect(admin_url('/admin.php?page=elex_mypost_bulk_shipment_order'));
						exit(); 
					} else {
						$stored_invalid_orders = get_option('elex_ausmypost_uneligible_orders');
	
						/* Showing error notice for invalid Order ids */
						if ($stored_invalid_orders != '') {
							echo '<div class="error"><p>' . sprintf(__('MANIFEST NOT GENERATED, ORDERS NOT FOUND FOR THE ORDERS ID(S) %s', 'wf-shipping-auspost'), $stored_invalid_orders) . '</p></div>';
							delete_option('elex_ausmypost_uneligible_orders');
						} else {
							echo '<div class="error"><p>' . sprintf(__('NO ORDERS FOUND', 'wf-shipping-auspost')) . '</p></div>';
						}
					}
	
				}
			}
		}
		/** Generate Label URL based on Shipment Order Number  */
		public function elex_ausmypost_generate_label_order( $generte_label_id_order) {
			if (!class_exists('wf_australia_mypost_shipping_admin')) {
				include plugins_url(basename(plugin_dir_path(__FILE__)) . 'australia_mypost/includes/class-wf-australia-mypost-shipping-admin.php', basename(__FILE__));
			}
			$label_generate = new wf_australia_mypost_shipping_admin();
			$label          = $label_generate->generate_label_shipment_order($generte_label_id_order);

			if ($label['success']) {

				return $label;

			} else {

				return array('success' => 0, 'error' => $label['error']);
			}
			return;  
		}
		/* Generates order summary for provided shipment ids */
		public function elex_ausmypost_generate_bulk_shipment_order_pickup( $shipment_order_not_generated, $manifest_pickup) {
			
			if (!class_exists('wf_australia_mypost_shipping_admin')) {
				include plugins_url(basename(plugin_dir_path(__FILE__)) . 'australia_mypost/includes/class-wf-australia-mypost-shipping-admin.php', basename(__FILE__));
			}

			$bulk_shipment  = new wf_australia_mypost_shipping_admin();
			$bulk_shipments = $bulk_shipment->wf_create_bulk_shipment_order_and_pickup($shipment_order_not_generated, $manifest_pickup);

			if ($bulk_shipments['success']) {

				return $bulk_shipments;

			} else {

				return array('success' => 0, 'error' => $bulk_shipments['error']);
			}
			return;   
		}
		/* Validating WooCommerce orders*/
		public function elex_ausmypost_validate_orders( $orders_ids) {
			global $wpdb;
			
			$valid_orders   = array();
			$invalid_orders = '';
			foreach ($orders_ids as $order_id) {

				$order_id_str = "'" . $order_id . "'";
				$results      = $wpdb->get_results( 'SELECT post_id FROM ' . $wpdb->prefix . "postmeta WHERE meta_key = '_order_number_formatted' AND meta_value = " . $order_id_str);
				
				if (empty($results)) {
					$order = wc_get_order( $order_id );
					if (empty($order)) {
						$invalid_orders = $invalid_orders . ' ' . $order_id . ',';
					} else {
						$valid_orders[] = $order_id;
					}
				} else {
					$valid_orders[] = $results[0]->post_id;

				}
			}
			$output_data = array(
				'valid_orders' => $valid_orders,
				'invalid_orders' => $invalid_orders
			);

			return $output_data;
		}
	}    
}

 new wf_ausmypost_bulk_shipments_orders_pickup_class();
?>
