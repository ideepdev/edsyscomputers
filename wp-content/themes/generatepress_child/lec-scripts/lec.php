<?php

//End code for return only customer user name when we search user in order screen by sachin
//get lec report from backend
class lecScripts{

	## Class constructor
	public function __construct(){
		add_action( 'admin_menu', [ $this, 'lec_report_admin_menu' ], 11 );
	}

	## Create admin menu page 
	public function lec_report_admin_menu() {
		global $team_page;
		add_submenu_page(
		    'edsys-reports', // Third party plugin Slug 
		    'LEC Report', 
		    'LEC Report', 
		    'delete_plugins', 
		    'lec-report', 
		    [ $this, 'lec_report_admin_page' ]
		);

	}

	## Menu page callback
	public function lec_report_admin_page(){
		include_once("lec-transactions.php");
		$tableObject = new Lec_List_Table();
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
								<h1>LEC Report</h1>  
						</div>   
						<div class="form-row">  
							<div>  
								<span>User</span>  
								<select data-placeholder="Begin typing a name to filter..." multiple class="chosen-select" name="user_id[]">
								<?php
								    $user_ids = array(149,6843,223,199,273,6917,104,6883,222,201,221,219,6821,257,312,179,6918); 
									foreach( $user_ids as $user ){
										$user_details = get_userdata($user);
										$username = $user_details->user_login;
										if($username){
											?>               
												<option value="<?php echo $user ?>" <?php echo ((isset($_GET['user_id']) && in_array($user, $_GET['user_id']) ? 'selected' : '')); ?> ><?php echo $username; ?></option>
											<?php
										}
									}
								?> 
								</select>
								  <button type="button" class="chosen-toggle deselect" style="width: auto;">Deselect all</button>
								  <button type="button" class="chosen-toggle select" style="width: auto;">Select all</button> 
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
								<button class="button button-primary button-large" name="filter-lec" type="submit">Get Orders</button> 
								<input type="hidden" name="page" value="<?php echo $_GET['page']; ?>"> 
							</div> 
						</div>  
					</form> 

					<script type="text/javascript">
						$(".chosen-select").chosen({
							no_results_text: "Oops, nothing found!"
						});
						$('.chosen-toggle').each(function(index) {
						console.log(index);
						    $(this).on('click', function(){
						    console.log($(this).parent().find('option').text());
						         $(this).parent().find('option').prop('selected', $(this).hasClass('select')).parent().trigger('chosen:updated');
						    });
						});
					</script>
					<br>
					<br>
					<?php if( isset( $_GET["user_id"] ) && isset( $_GET['filter-lec'] ) ){ ?>
					<table class="wp-list-table widefat fixed striped table-view-list toplevel_page_lec-report" style="margin-top:40px;">
						<tr>
							<td style="width: 70%;">
								Complete Record Download file:
							</td>
							<td>
								<?php 
									$FileName_csv = 'lec_report.csv';
									$admin_url = admin_url($FileName_csv); 
								?>
								<a class="export_csv" href='<?php echo $FileName_csv; ?>'download='<?php echo $FileName_csv; ?>'>
									Export to CSV Report
								</a>
							</td>
							<td>
								<?php 
									$FileName_xls = 'lec_report.xls';
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
				button.chosen-toggle {
				    float: right !important;
				    margin-right: 11px;
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
				/*.scrollable-table{
					overflow:scroll;
					width:100%;
				}*/
				/*.scrollable-table table{
					width: 4279px;
				}*/
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
new lecScripts();
?>