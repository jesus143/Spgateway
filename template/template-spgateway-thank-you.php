<?php

/**
 * Template Name: Sendright Registration - thank you page
 */

		wp_head();

		if(!session_id()) {
			session_start();
		}
		//		print "this is the thank you page file";
		//		error_reporting(1);
		//	 	print "spgateway response";
		$orderId = (!empty($_GET['orderId'])) ? $_GET['orderId'] : null;
		$orderStatus = (!empty($_POST['Status'])) ? strtolower($_POST['Status']) : null;
		$invalidRequestUrl = get_site_url() . "/invalid-request" ;
		$url = 'http://sendright.net/user/registration/create';
//		$url = 'http://localhost/rocky/send-right-dev/user/registration/user/registration/create';
//		$url = 'http://google-calendar.hopto.org/rocky/send-right-dev/user/registration/create';
		$password = 'sendright';
		$shopUrl = get_site_url() . '/shop';
		$email = '';
		$firstName = '';
		$lastName = '';
		$sendRightLogin = 'http://www.sendright.net/login';
		$sendRightForgotPassword = 'http://www.sendright.net/password/reset';


//if(false) {
	if ($orderStatus == 'success') {
		// print "<br> this request is valid and create sendright account will executed";
	} else {
		?>
		<script>
			var invalidRequestUrl = '<?php print $invalidRequestUrl; ?>';
			document.location = invalidRequestUrl;
		</script>
		<?php
		// print "<br> this request is not valid and need to show an error or redirect to another page because no payment was made";
	}
//}


		// 		print "<pre>";
		//		print "post";
		//		print_r($_POST);
		//		print "get";
		//		print_r($_GET);
		//		print "SESSION";
		//		print_r($_SESSION);
		//		print "COCKIE";
		//		print_r($_COCKEI);
		//print "<br>checkout information<br>";
		//		 order
		//		foreach ( WC()->cart->get_cart() as $cart) {
		//			print_r($cart);
		//		}
		//print "<br> billing address<br>";
		// $billingAddress = WC()->cart->ship_to_billing_address_only();
		// get order id
//		print "order id " . $orderId;
		global $wpdb; // Get the global $wpdb
		$order_id = $orderId;
		$table = $wpdb->prefix . 'postmeta';
		$sql = 'SELECT * FROM `'. $table . '` WHERE post_id = '. $order_id;
        $result = $wpdb->get_results($sql);
        foreach($result as $res) {
			if( $res->meta_key == '_billing_email'){
				$email = $res->meta_value;      // get billing phone
			}
			if( $res->meta_key == '_shipping_first_name'){
				$firstName = $res->meta_value;   // get billing first name
			}
			if( $res->meta_key == '_shipping_last_name'){
				$lastName = $res->meta_value;   // get billing first name
			}
		}
		//		 print "<br>Email $email  | First Name:  $firstName Last Name: $lastName password $password";
		 print "</pre>";

//		$email = 'testNewEmail123@gmail.com';
		// add membership
		?>
	<?php


	?>

<html>
	<head>

		<style>
			.invoice-title h2, .invoice-title h3 {
				display: inline-block;
			}

			.table > tbody > tr > .no-line {
				border-top: none;
			}

			.table > thead > tr > .no-line {
				border-bottom: none;
			}

			.table > tbody > tr > .thick-line {
				border-top: 2px solid;
			}
		</style>


		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js" integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn" crossorigin="anonymous"></script>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>

		<script>
			$(document).ready(function(){


				$("#sendright-loader").css('display','block');
				console.log("document is ready");

				var firstName = '<?php print $firstName; ?>';
				var lastName  = '<?php print $lastName; ?>';
				var email     = '<?php print $email; ?>';
				var password  = '<?php print $password; ?>';
				var url  	  = '<?php print $url; ?>';
				var param 	  =  url + "/" + email + "/" +  firstName +  " " + lastName + "/" + password;
				console.log(" fname " + firstName +  " lname " + lastName + " email " + email + " pass" + password + " param " + param);
				var jqxhr = $.get( param, function( data ) {

//					alert("success 1" + data);
				})
				.done(function() {

//					alert("success 2" + data);
//					$("#sendriht-create-account-success").css('display','block');
				})
				.fail(function(data) {

//					alert("error" + data);
//					if(data == 'ok') {
//						alert("result is ok");
//					} else {
//						alert("result is not ok");
//					}
//					$("#sendriht-create-account-failed").css('display','block');
				})
				.always(function() {

//					alert("always");
					$("#sendright-loader").css('display','none');
					$("#sendriht-create-account-success").css('display','block');
				});
			});
		</script>
	</head>
	<body>
		<div class="container">
			<br><br>
			<div class="jumbotron">
				<h1>Success! your order is now processing..</h1><br>
				<p><a class="btn btn-primary btn-lg" href="#" role="button">View invoice..</a></p>
			</div>


			<div class="list-group" id="sendriht-create-account-success" style="display:none">
				<div class="alert alert-success">
					Congratulation, We created sendright account for you! please check information below.
				</div>

				<a href="#" class="list-group-item disabled">
					Send Right Account Information
				</a>
				<span class="list-group-item">Email: <?php print $email;  ?>  <br></span>
				<span class="list-group-item">Password:  <?php print $password;  ?>  <br></span>
				<span class="list-group-item">go to  http://www.sendright.net &nbsp; <a  href="<?php print $sendRightLogin; ?>" target="_blank"> login </a> <br></span>
			</div>


			<div class="alert alert-danger" style="display:none" id="sendriht-create-account-failed">
				Ohps, something wrong, maybe you already have account to sendright, please contact sendright administrator or try to forgot your password using <?php print $email;  ?> email address, please visit <a href="<?php print $sendRightForgotPassword; ?>"> here </a> to reset password.
			</div>

			<div id="sendright-loader" >
				Checking sendright account and create.. <br>
				<i class="fa fa-circle-o-notch fa-spin" style="font-size:24px"></i>
			</div>

			<br>
			<a href="<?php print $shopUrl; ?>">
				<button class="btn btn-default">Go back to shop</button>
			</a>

		</div>
	</body>
</html>


<?php
wp_footer();
?>


























