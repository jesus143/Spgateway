<?php

//print "<h1> This is helper</h1>";



function spgateway_createNewWpUser($data)
{
   return wp_insert_user($data);
}

function spgateway_get_customer_info($orderId)
{
    global $wpdb; // Get the global $wpdb
    $order_id = $orderId;
    $table = $wpdb->prefix . 'postmeta';
    $sql = 'SELECT * FROM `'. $table . '` WHERE post_id = '. $order_id;
    $result = $wpdb->get_results($sql);
    $user = [];
    foreach($result as $res) {
        if( $res->meta_key == '_billing_email'){
            $user['email'] = $res->meta_value;      // get billing phone
        }
        if( $res->meta_key == '_shipping_first_name'){
            $user['firstName'] = $res->meta_value;   // get billing first name
        }
        if( $res->meta_key == '_shipping_last_name'){
            $user['lastName'] = $res->meta_value;   // get billing first name
        }
    }
    return $user;
}

function spgateway_set_return_url($data)
{
    $orderId          = $data['orderId'];
    $itemName         = $data['itemName'];
    $sendRightKeyWord = $data['sendRightKeyWord'];

    foreach($itemName as $key => $value) {
        $name = $value['name'];
        $name = str_replace(" ", "", $name);
        $name = strtolower($name);
        $productId = $value['product_id'];
        //                print " name $name product id " . $productId;
        if(strpos($name, $sendRightKeyWord) > -1) {
            $spgateway_args['ReturnURL'] = get_site_url() . '/thank-you?orderId='.$orderId;
        }
    }
    return $spgateway_args['ReturnURL'];
}