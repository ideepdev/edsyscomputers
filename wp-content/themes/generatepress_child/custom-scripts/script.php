<?php

//End code for return only customer user name when we search user in order screen by sachin
//get transaction report from backend
class customScripts{

	## Class constructor
	public function __construct(){
		add_action( 'admin_menu', [ $this, 'transaction_report_admin_menu' ] );
	}

	## Create admin menu page 
	public function transaction_report_admin_menu() {
		global $team_page;
		add_menu_page( __( ' ', 'transaction-report' ), __( 'Government Report', 'transaction-report' ), 'edit_posts', 'transaction-report', [ $this, 'transaction_report_admin_page' ], 'dashicons-format-aside', 8 ) ;
	}

	## Menu page callback
	public function transaction_report_admin_page(){
		include_once("transactions.php");
		$tableObject = new Transaction_List_Table();
		$tableObject->prepare_items();
		$args = array(
			'exclude' => array(1),
		);
			$users = get_users( $args );
		?>
			<div class="wrap">
				<div class="main-content">
					<div id="icon-users" class="icon32"></div>
					<!-- You only need this form and the form-basic.css -->  
					<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
					<script src="https://cdn.rawgit.com/harvesthq/chosen/gh-pages/chosen.jquery.min.js"></script>
					<link href="https://cdn.rawgit.com/harvesthq/chosen/gh-pages/chosen.min.css" rel="stylesheet"/>
					<form action="" method="get" class="select-range">  
						<div class="form-title-row">  
								<h1>Government Report</h1>  
						</div>   
						<div class="form-row">  
							<div>  
								<span>User</span>  
								<select data-placeholder="Begin typing a name to filter..." multiple class="chosen-select" name="user_id[]">
									<option>Select User</option>
								<?php 
									foreach( $users as $user ){
										?>               
											<option value="<?php echo $user->ID ?>" <?php echo ((isset($_GET['user_id']) && in_array($user->ID, $_GET['user_id']) ? 'selected' : '')); ?> ><?php echo $user->user_login; ?></option>
										<?php
									}
								?> 
								</select>  
							</div> 
							<div>  
								<span>Date Form</span>  
								<input type="date" name="date_from" value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : ''; ?>">
							</div> 
							<div>  
								<span>Date To</span>  
								<input type="date" name="date_to" value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : ''; ?>">
							</div> 
						</div>     
						<div class="form-row">  
							<div style="width: 10%;">  
								<button class="button button-primary button-large" name="filter-transaction" type="submit">Get Orders</button> 
								<input type="hidden" name="page" value="<?php echo $_GET['page']; ?>"> 
							</div> 
						</div>  
					</form> 
					<script type="text/javascript">
						$(".chosen-select").chosen({
							no_results_text: "Oops, nothing found!"
						});
					</script>
					<br>
					<br>
					<?php if( isset( $_GET["user_id"] ) && isset( $_GET['filter-transaction'] ) ){ ?>
					<table class="wp-list-table widefat fixed striped table-view-list toplevel_page_transaction-report" style="margin-top:40px;">
						<tr>
							<td style="width: 70%;">
								Complete Record Download file:
							</td>
							<td>
								<?php 
									$FileName_csv = 'edsys_report.csv';
									$admin_url = admin_url($FileName_csv); 
								?>
								<a class="export_csv" href='<?php echo $FileName_csv; ?>'download='<?php echo $FileName_csv; ?>'>
									Export to CSV Report
								</a>
							</td>
							<td>
								<?php 
									$FileName_xls = 'edsys_report.xls';
									$admin_url = admin_url($FileName_xls); 
								?>
								<a href='<?php echo $FileName_xls; ?>' download='<?php echo $FileName_xls; ?>'>
									Export to Excel Report
								</a>
							</td>
						</tr>
					</table>
					<?php } ?>
					<br>
					<div class="scrollable-table">
						<?php 
							$tableObject->display();
						?>
					</div>
				</div>
			</div>
			<style>
				.tablenav.top{
					display:none;
				}
				.select-range input{
					width: 300px;
    			padding: 5px;
				}
				.select-range button{
					float: left;
					padding: 5px !important;
					width: 100%;
					/* margin-top: -3px !important; */
				}
				.select-range .form-row > div{
					float:left;
					width:30%;
				}
				.select-range .form-row > div span{
					font-weight:bold;
					font-size:12px;
				}
				.select-range .form-row .chosen-container{
					width: 330px !important;
    			padding: 5px !important;
				}
				.scrollable-table{
					overflow:scroll;
					width:100%;
				}
				.scrollable-table table{
					width: 4279px;
				}
				.scrollable-table table th{
					white-space: nowrap;
				}
				input[type="date"]{
					text-transform: uppercase;
				}
				.tablenav.bottom{
					position: absolute;
					right: 25px;
					padding-top: 20px;
				}
			</style>
		<?php
	}
}
new customScripts();
?>