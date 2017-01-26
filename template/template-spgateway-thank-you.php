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

		if($orderStatus == 'success') {
			print "<br> this request is valid and create sendright account will executed";
		} else {
			 ?>
					<script>
						document.location = 'not-valid-request';
					</script>
				<?php
//			print "<br> this request is not valid and need to show an error or redirect to another page because no payment was made";
		}

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



		$email 	   = '';
		$firstName = '';
		$lastName  = '';

		//print "<br> billing address<br>";
		// $billingAddress = WC()->cart->ship_to_billing_address_only();
		// get order id
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
		$password = 'sendright';
//		 print "<br>Email $email  | First Name:  $firstName Last Name: $lastName password $password";
		 print "</pre>";
		?>
	<?php


	$url = 'http://localhost/rocky/send-right-dev/user/registration/create';
//	$url = 'http://sendright.net/user/registration/create';


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
				console.log("document is ready");

				var firstName = '<?php print $firstName; ?>';
				var lastName  = '<?php print $lastName; ?>';
				var email     = '<?php print $email; ?>';
				var password  = '<?php print $password; ?>';
				var url  	  = '<?php print $url; ?>';
				var param 	  =  url + "/" + email + "/" +  firstName +  "" + lastName + "/" + password;
				console.log(" fname " + firstName +  " lname " + lastName + " email " + email + " pass" + password + " param " + param);
				$.get( param, function( data ) {
					//				$("#spgateway-sendright-status").html("Successfully created account in sendright.net");
					//				if(data == 'ok') {
					//					$("#spgateway-sendright-status").html("Successfully created account in sendright.net");
					//				} else {
					//					$("#spgateway-sendright-status").html("Account no created in sendright.net this may because of conflict.");
					//				}
				});
			});
		</script>
	</head>
	<body>
		<div class="container">


			<div class="jumbotron">
				<h1>Hello, world!</h1>
				<p>...</p>
				<p><a class="btn btn-primary btn-lg" href="#" role="button">Learn more</a></p>
			</div>
			<div class="alert alert-success">
				Thank you, your oder is processing now.<br>
			</div>
			We created senright account for you <br>
			Below is your login information: <br>
			  Username:  <?php print $email;  ?>  <br>

			 Password:   <?php print $password;  ?>  <br>

		</div>
	</body>
</html>


<?php
wp_footer();
?>