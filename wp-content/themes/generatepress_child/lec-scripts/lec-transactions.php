<?php 
// WP_List_Table is not loaded automatically so we need to load it in our application
if( ! class_exists( 'WP_List_Table' ) ) {
  require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
* Create a new table class that will extend the WP_List_Table
*/
class Lec_List_Table extends WP_List_Table
{
  /**
   * Prepare the items for the table to process
   *
   * @return Void
   */
  public function prepare_items()
  {
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$data = $this->table_data();
		usort( $data, array( &$this, 'sort_data' ) );

		$perPage = 10;
		$currentPage = $this->get_pagenum();
		$totalItems = count($data);

		$this->set_pagination_args( array(
			'total_items' => $totalItems,
			'per_page'    => $perPage
		) );

		$data = array_slice($data,(($currentPage-1)*$perPage),$perPage);

		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items = $data;
  }

  /**
   * Override the parent columns method. Defines the columns to use in your listing table
   *
   * @return Array
   */
  public function get_columns()
  {
		$columns = array(
			'order_number'             => 'Order Number',
			'po_number'             => 'LEC Number',
			'order_date'            => 'Order Date',
			'invoice_number'        => 'Invoice Number',
			'total_price'           => 'Total Price'
		);
		return $columns;
  }

  /**
   * Define which columns are hidden
   *
   * @return Array
   */
  public function get_hidden_columns()
  {
    return array();
  }

  /**
   * Define the sortable columns
   *
   * @return Array
   */
  public function get_sortable_columns()
  {
    return array('title' => array('title', false));
  }

  /**
   * Get the table data
   *
   * @return Array
   */
  private function table_data()
  {
		$data = array();

		// if(isset($_POST["user_id"]) && ($_SERVER["REQUEST_METHOD"] == "POST")){
		if( isset( $_GET["user_id"] ) && isset( $_GET['filter-lec'] ) ){
			$user_ids = $_GET["user_id"];
			$initial_date = $_GET["date_from"];
			$final_date = $_GET["date_to"];
    	//Download link
			$plugin_dir   = wp_upload_dir();
			//print_r($plugin_dir['path']);

			## Download csv file
			$FileName_csv = 'lec_report.csv';
			$file_csv = fopen($FileName_csv,"w");

			## Download xls file
			$FileName_xls = 'lec_report.xls';
			$file_xls = fopen($FileName_xls,"w");

			$row = $results;
			//HeadingsArray
			$HeadingsArray=array();
			$HeadingsArray[]='Order Number';
			$HeadingsArray[]='LEC Number';
			$HeadingsArray[]='Order Date';
			$HeadingsArray[]='Invoice Number'; 
			$HeadingsArray[]='Total Price';

			fputcsv($file_csv,$HeadingsArray);
			fputcsv($file_xls,$HeadingsArray);

			foreach ($user_ids as $key => $user_id) {

				$shop_order = wc_get_orders( [
					'type'        => 'shop_order',
					'limit'       => - 1,
					'customer_id' => $user_id,
					'date_created'=> $initial_date .'...'. $final_date 
				] );
				if ( $shop_order ){

					foreach ( $shop_order as $order ) {

						$valuesArray=array();
						$order_billing_company = $order->get_billing_company();
						$user_info = get_userdata($order->user_id);
						$order_user_login = $user_info->user_login;
						$order_billing_phone = $order->get_billing_phone();
						$order_billing_email = $order->get_billing_email();
						$order_id = $order->get_id();
						$order_purchase_number = get_post_meta($order_id, 'purchase_number', true);
						$order_order_number = $order->get_order_number();
						$order_so_order_number = str_replace("SO", "", $order_order_number);
						$order_date =date( "d/m/Y" ,strtotime($order->get_date_created()) );
						$order_date = empty( $order->get_date_created() ) ? '' : date( "d/m/Y" ,strtotime($order->get_date_created()) );
						$order_invoice = wcpdf_get_document( 'invoice', $order );
						if ( $order_invoice && $order_invoice->exists() ) {
							$order_invoice_number = $order_invoice->get_number();
							$order_invoice_date = empty( $order_invoice->get_date() ) ? '' : date( "d/m/Y" ,strtotime($order_invoice->get_date()) );
						}else{
							$order_invoice_number = "";
							$order_invoice_date = "";
						}

						$order_delivery_address = $order->get_shipping_first_name()." ".$order->get_shipping_last_name().", ".$order->get_shipping_company().", ".$order->get_shipping_address_1().", "."\n".$order->get_shipping_address_2().$order->get_shipping_city().", ".$order->get_shipping_state().$order->get_shipping_postcode().", ".$order->get_shipping_country();
						$order_billing_postcode = $order->get_billing_postcode();
						$order_total = $order->get_total();
						$order_completed_dates = empty( $order->get_date_completed() ) ? '' : date( "d/m/Y" ,strtotime($order->get_date_completed()) );
							## Push data into files
							$valuesArray =array(
								$order_order_number,
								$order_user_login,
								$order_date,
								$order_invoice_number,
								$order_total
							);
							if($order_invoice_number){
								fputcsv($file_csv,$valuesArray);
								fputcsv($file_xls,$valuesArray);

								$data[] = array(
									'order_number'             => "<a target='_blank' href='".$order->get_checkout_order_received_url()."'>".$order_order_number."</a>",
									'po_number'             => $order_user_login,
									'order_date'            => $order_date,
									'invoice_number'        => $order_invoice_number,
									'total_price'           => '$'.$order_total
								);							
							}
					}
				} ?>
			<?php
			}
		}	
		return $data;
  }

  /**
   * Define what data to show on each column of the table
   *
   * @param  Array $item        Data
   * @param  String $column_name - Current column name
   *
   * @return Mixed
   */
  public function column_default( $item, $column_name )
  {
		switch( $column_name ) {
			case 'order_number':
			case 'po_number':
			case 'order_date':
			case 'invoice_number':
			case 'total_price':
				return $item[ $column_name ];

			default:
				return print_r( $item, true ) ;
		}
  }

  /**
   * Allows you to sort the data by the variables set in the $_GET
   *
   * @return Mixed
   */
  private function sort_data( $a, $b )
  {
		// Set defaults
		$orderby = 'title';
		$order = 'asc';

		// If orderby is set, use this as the sort column
		if(!empty($_GET['orderby']))
		{
			$orderby = $_GET['orderby'];
		}

		// If order is set use this as the order
		if(!empty($_GET['order']))
		{
			$order = $_GET['order'];
		}
		$result = strcmp( $a[$orderby], $b[$orderby] );

		if($order === 'asc')
		{
			return $result;
		}

		return -$result;
  }
}
?>