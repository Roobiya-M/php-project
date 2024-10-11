<?php
require_once ("../include/const.inc.php");
require_once ("../utilities/helpers.php");

if(!($adminid = sessionValidate(VHITS_ADMIN_ROLEID)))
{
	hlog_errorlog("No session logging out ....");
	header("Location:adminlogin.php?exp=1");
}

$permission = array ();
if($_SESSION[VHITS_ADMIN_TYPE]=='SUBUSER')
{
	$permission = explode(':',$_SESSION[VHITS_ADMIN_PERMISSION]);
	hlog_errorlog("permission===>".json_encode($permission));
}

$paths = explode("/",$_SERVER['PHP_SELF']);
$filename = $paths[count($paths)-1];

$versionno = getcssnjsversion();

?>

<nav class="navbar navbar-default" role="navigation">
	<div class="container-fluid">
		<!-- Brand and toggle get grouped for better mobile display -->
		<div class="navbar-header">
			<button type="button" class="navbar-toggle" data-toggle="collapse"
				data-target="#bs-example-navbar-collapse-1">
				<span class="sr-only">Toggle navigation</span> <span
					class="icon-bar"></span> <span class="icon-bar"></span> <span
					class="icon-bar"></span>
			</button>
      <?php if($_SESSION[VHITS_ADMIN_TYPE]=='MASTER'){?>
      <a href="admindashboard.php"><img class="navbar-brand"
				src="../assets/logo.png" /></a>
      <?php } else {?>
      <a href=""><img class="navbar-brand" src="../assets/logo.png" /></a>
     <?php }?>
    </div>

		<!-- Collect the nav links, forms, and other content for toggling -->
		<div class="collapse navbar-collapse text-center"
			id="bs-example-navbar-collapse-1">
			<label style="line-height: 70px; margin: 0px; font-size: 25px">ADMIN
				Portal</label>
			<ul class="nav navbar-nav navbar-right">

		<?php if($_SESSION[VHITS_ADMIN_TYPE]=='MASTER'){?>
        <li
					<?php if($filename==="admindashboard.php"){echo "class='active'";}?>><a
					href="admindashboard">Home Page</a></li>
        <?php }?>

				<?php if((in_array('lco',$permission)) || (in_array('mso',$permission)) || ($_SESSION[VHITS_ADMIN_TYPE]=='MASTER')){?>
			 	 <li
					<?php if($filename==="adminlcolist.php" || $filename==="admindpolist.php" || $filename==="adminmsolist.php") { echo "class='active'"; } ?>>
					<a class="dropdown-toggle" data-toggle="dropdown" role="button"
					href="">Operators <b class="caret"></b></a>
					<ul class="dropdown-menu" role="menu" aria-labelledby="dLabel">
						<li><a href="adminlcolist.php">LCO</a></li>
						<li><a href="adminmsolist.php">MSO</a></li>
						<li><a href="admindpolist.php">DPO</a></li>
					</ul>
				</li>
			 	 <?php }?>


    <?php //if((in_array('lco',$permission)) || ($_SESSION[VHITS_ADMIN_TYPE]=='MASTER')){?>
      <!--  <li <?php //if($filename==="adminlcolist.php") { echo "class='active'"; } ?> >
      	<a class="dropdown-toggle" data-toggle="dropdown" role="button" href="">LCO <b class="caret"></b></a>
	        <ul class="dropdown-menu" role="menu" aria-labelledby="dLabel">
		    	<li><a href="adminlcolist.php">List LCO</a></li>
	       	</ul>
	    </li>-->
	    <?php //}?>

		 <?php //if((in_array('mso',$permission)) || ($_SESSION[VHITS_ADMIN_TYPE]=='MASTER')){?>
    <!--  <li <?php //if($filename==="adminmsolist.php") { echo "class='active'"; } ?> >
        <a class="dropdown-toggle" data-toggle="dropdown" role="button" href="">MSO <b class="caret"></b></a>
          <ul class="dropdown-menu" role="menu" aria-labelledby="dLabel">
          <li><a href="adminmsolist.php">List MSO</a></li>
          </ul>
      </li>-->
       <?php //}?>

		<?php if((in_array('bulk',$permission)) || ($_SESSION[VHITS_ADMIN_TYPE]=='MASTER')){?>
       <li
					<?php if($filename==="adminproductsubscription.php" || $filename==="adminbulkexpiryrenew.php" || $filename==="adminbulkexpiredrenew.php" || $filename==="adminbulkreconnect.php" || $filename==="adminbulkdisconnect.php" || $filename==="adminbulkaddbasepack.php" || $filename==="bulkretrack.php") { echo "class='active'"; } ?>>
					<a class="dropdown-toggle" data-toggle="dropdown" role="button"
					href="">Bulk <b class="caret"></b></a>
					<ul class="dropdown-menu" role="menu" aria-labelledby="dLabel">

						<!--<li><a href="admincreatecustomer.php">Create Subscriber</a></li>-->

						<li class="menu-item dropdown dropdown-submenu"><a href="#"
							class="dropdown-toggle" data-toggle="dropdown">Activation</a>
							<ul class="dropdown-menu">
								<li class="menu-item "><a href="adminproductsubscription.php">Add-ons</a>
								</li>
								<li class="menu-item "><a href="adminbulkaddbasepack.php">Basepack</a>
								</li>
								<li class="menu-item "><a href="adminbulkexpiryrenew.php">Expiry
										Renew</a></li>
								<li class="menu-item "><a href="adminbulkexpiredrenew.php">Expired
										Renew</a></li>
								<li><a href="admincreatecustomer.php">Create Subscriber</a></li>

							</ul></li>
						<li class="menu-item dropdown dropdown-submenu"><a href="#"
							class="dropdown-toggle" data-toggle="dropdown">Hardware</a>
							<ul class="dropdown-menu">
								<li class="menu-item "><a href="bulkretrack.php">Retrack</a></li>
								<li class="menu-item "><a href="adminbulkreconnect.php">Reconnect</a>
								</li>
								<li class="menu-item "><a href="adminbulkdisconnect.php">Disconnect</a>
								</li>
								<li class="menu-item "><a href="adminbulkcancel.php">Cancel
										Product</a></li>
								<li class="menu-item "><a href="adminhardwarereplace.php">Hardware
										Replacement</a></li>
								<li class="menu-item "><a href="adminmsoosd.php">Send OSD</a></li>
							</ul></li>
						<li class="menu-item dropdown dropdown-submenu"><a href="#"
							class="dropdown-toggle" data-toggle="dropdown">Product</a>
							<ul class="dropdown-menu">
								<li class="menu-item "><a href="bulkproductassignment.php">Assign</a>
								</li>
								<li class="menu-item "><a href="bulkproductremoval.php">Remove</a>
								</li>
							</ul></li>
						<li class="menu-item dropdown dropdown-submenu"><a href="#"
							class="dropdown-toggle" data-toggle="dropdown">Customer</a>
							<ul class="dropdown-menu">
								<li class="menu-item "><a
									href="bulkcustomerclassandtypechange.php">Change Type & Class</a>
								</li>
							</ul></li>
						<li class="menu-item dropdown dropdown-submenu"><a href="#"
							class="dropdown-toggle" data-toggle="dropdown">Finance</a>
							<ul class="dropdown-menu">
								<li class="menu-item "><a href="bulkpaybill.php">Pay Bills</a></li>
								<li><a href="bulkaddfinancialaccount.php">Financial Account</a></li>
							</ul></li>
						<li class="menu-item dropdown dropdown-submenu"><a href="#"
							class="dropdown-toggle" data-toggle="dropdown">Adjustment</a>
							<ul class="dropdown-menu">
								<li class="menu-item "><a href="bulkcreditadjustment.php">Credit</a>
								</li>
								<li class="menu-item "><a href="bulkdebitadjustment.php">Debit</a>
								</li>
							</ul></li>
					</ul>
				</li>
       <?php }?>

        <?php if((in_array('report',$permission)) || ($_SESSION[VHITS_ADMIN_TYPE]=='MASTER')){?>
        <li
					<?php if($filename==="bulkreport.php" || $filename==="adminactivationreport.php" || $filename==="adminpaymentreport.php" ||$filename==="adminactivationreport.php" || $filename==="adminlistcustomer.php" || $filename==='admindevicelist.php') { echo "class='active'"; } ?>>
					<a class="dropdown-toggle" data-toggle="dropdown" role="button"
					href="">Report <b class="caret"></b></a>
					<ul class="dropdown-menu" role="menu" aria-labelledby="dLabel">

						<!--              	<li><a href="adminpostpaidpaymentlist.php">Postpaid Payment List</a></li> -->

						<li class="menu-item dropdown dropdown-submenu"><a href="#"
							class="dropdown-toggle" data-toggle="dropdown">Core</a>
							<ul class="dropdown-menu">
								<li class="menu-item "><a href="adminlistcustomer.php">List
										Customer</a></li>
								<li class="menu-item "><a href="admindevicelist.php">List
										Devices</a></li>
								<li class="menu-item "><a href="adminconnectionlist.php">List
										Connections</a></li>
								<li class="menu-item "><a href="adminactivationreport.php">List
										Activations</a></li>
								<li class="menu-item "><a href="adminsuspensionlist.php">List
										Suspension</a></li>
								<li class="menu-item "><a
									href="adminproductassignmentreport.php">Product Assignments</a>
								</li>
								<li class="menu-item "><a href="bulkreport.php">Job Report</a></li>
								<li class="menu-item "><a href="survey.php">Survey Report</a></li>
								<li class="menu-item "><a href="admingenrereportupload.php">LCN & Genre Report</a></li>
								<!--<li class="menu-item ">
							<a href="admincustomerselectionreport.php">Customer Selection</a>
						</li>-->

							</ul></li>
						<li class="menu-item dropdown dropdown-submenu"><a href="#"
							class="dropdown-toggle" data-toggle="dropdown">Finance</a>
							<ul class="dropdown-menu">
								<li class="menu-item "><a href="adminpaymentreport.php">List
										Payments</a></li>
								<li class="menu-item "><a href="adminearlybirdpaymentreport.php">List
										Early Bird Payments</a></li>
								<li class="menu-item "><a href="payforbox.php">Pay For Box</a></li>
								<li class="menu-item "><a href=gstreport.php>GST Report </a></li>
								<li class="menu-item "><a href=adminpaybillsreport.php>Pay Bills
								</a></li>
								<!--<li class="menu-item ">
							<a href=adminrefund.php>Refund Report</a>
						</li>-->
							</ul></li>
						<!--<li class="menu-item dropdown dropdown-submenu">
					<a href="#" class="dropdown-toggle" data-toggle="dropdown">Schedule</a>
					<ul class="dropdown-menu">
						<li class="menu-item ">
							<a href=autorenew.php>List Auto Renew</a>
						</li>
						<li class="menu-item ">
							<a href="changebasepack.php">List Pre Activations</a>
						</li>

					</ul>
				</li>-->
					</ul>
				</li>
	    <?php }?>

        <?php
								/*
								 * if((in_array('report',$permission)) || ($_SESSION[VHITS_ADMIN_TYPE]=='MASTER')){?>
								 * <li <?php if($filename==="adminarchivedconnectionlist.php" || $filename==="adminarchiveddevicelist.php" || $filename==="adminarchivedactivationlist.php") { echo "class='active'"; } ?> >
								 * <a class="dropdown-toggle" data-toggle="dropdown" role="button" href="">Archived Report <b class="caret"></b></a>
								 * <ul class="dropdown-menu" role="menu" aria-labelledby="dLabel">
								 * <li><a href="adminarchivedactivationlist.php">List Archived Activations</a></li>
								 * <li><a href="adminarchiveddevicelist.php">List Archived Devices</a></li>
								 * <li><a href="adminarchivedconnectionlist.php">List Archived Connections</a></li>
								 * </ul>
								 * </li>
								 * <?php }
								 */
								?>

	  <?php if((in_array('alert',$permission)) || ($_SESSION[VHITS_ADMIN_TYPE]=='MASTER')){?>
      <li
					<?php if($filename==="createmesage.php" || $filename==="adminalertmesage.php" || $filename==="addpushnotification.php") { echo "class='active'"; } ?>>
					<a class="dropdown-toggle" data-toggle="dropdown" role="button"
					href="">Message <b class="caret"></b></a>
					<ul class="dropdown-menu" role="menu" aria-labelledby="dLabel">
						<!--<li><a href="createmesage.php">Create Mesage</a></li>-->
						<li><a href="adminalertmesage.php">List Message</a></li>
						<li><a href="addscroll.php">Add Scroll</a></li>
						<li><a href="addpushnotification.php">Add Push Notification</a></li>
						<li><a href="s3fileupload.php">Add S3 Images</a></li>
					</ul>
				</li>
      <?php }?>


		<?php if((in_array('alert',$permission)) || ($_SESSION[VHITS_ADMIN_TYPE]=='MASTER')){?>
			<li
					<?php if($filename==="setduedate.php") { echo "class='active'"; } ?>>
					<a class="dropdown-toggle" data-toggle="dropdown" role="button"
					href="">DueDate<b class="caret"></b></a>
					<ul class="dropdown-menu" role="menu" aria-labelledby="dLabel">
						<li><a href="setduedate.php">Set DueDate</a></li>
					</ul>
				</li>
			<?php }?>

		<?php if((in_array('alert',$permission)) || ($_SESSION[VHITS_ADMIN_TYPE]=='MASTER')){?>
			<li
					<?php if($filename==="addbanner.php") { echo "class='active'"; } ?>>
					<a class="dropdown-toggle" data-toggle="dropdown" role="button"
					href="">Images<b class="caret"></b></a>
					<ul class="dropdown-menu" role="menu" aria-labelledby="dLabel">
						<li><a href="addbanner.php">Banner ADs</a></li>
						<li><a href="adddownloadablefiles.php">Add Downloadable files</a></li>
						<li><a href="addimage">Channel Image Uploads</a></li>
					</ul>
					
				</li>
			<?php }?>

       <?php if((in_array('subuser',$permission)) || ($_SESSION[VHITS_ADMIN_TYPE]=='MASTER')){?>
      <li
					<?php if($filename==="addsubuser.php" || $filename==="updatesubuser.php") { echo "class='active'"; } ?>>
					<a class="dropdown-toggle" data-toggle="dropdown" role="button"
					href="">Subuser <b class="caret"></b></a>
					<ul class="dropdown-menu" role="menu" aria-labelledby="dLabel">
						<li><a href="addsubuser.php">Add Subuser</a></li>
						<li><a href="updatesubuser.php">Modify Subuser</a></li>
					</ul>
				</li>
      <?php }?>

      <?php if((in_array('bank',$permission)) || ($_SESSION[VHITS_ADMIN_TYPE]=='MASTER')){?>
        <li
					class="dropdown <?php if($filename==="adminbanks.php"){echo "active";}?>">
					<a class="dropdown-toggle" data-toggle="dropdown" role="button"
					href="#">Banks <b class="caret"></b>
				</a>
					<ul class="dropdown-menu" role="menu" aria-labelledby="dLabel">
						<li><a href="adminbanks">List Banks</a></li>
					</ul>
				</li>
        <?php }?>

        <?php if((in_array('partners',$permission)) || ($_SESSION[VHITS_ADMIN_TYPE]=='MASTER')){?>
        <li
					class="dropdown <?php if($filename==="adminpartners.php"){echo "active";}?>">
					<a class="dropdown-toggle" data-toggle="dropdown" role="button"
					href="#">Partners <b class="caret"></b>
				</a>
					<ul class="dropdown-menu" role="menu" aria-labelledby="dLabel">
						<li><a href="adminpartners">List Partners</a></li>
					</ul>
				</li>
        <?php }?>


      	<?php if((in_array('maintenance',$permission)) || ($_SESSION[VHITS_ADMIN_TYPE]=='MASTER')){?>
        	<li><a href="maintenance.php">Maintenance</a></li>
         <?php }?>

        <li><a href="#" onclick="adminlogout()">Logout</a></li>
			</ul>
		</div>
		<!-- /.navbar-collapse -->
	</div>
	<!-- /.container-fluid -->
</nav>

<div class="container-fluid">

	<div class="row text-center lco-subheader text-left" style="">
		<div class="col-lg-4 col-sm-4 col-md-4 col-xs-4 text-left"
			style="margin-left: 10px">
			<label> Welcome : </label><span> <?php if($adminid=sessionValidate(VHITS_ADMIN_ROLEID)) echo $_SESSION[VHITS_ADMIN_NAME];?></span>
		</div>
	</div>

</div>
