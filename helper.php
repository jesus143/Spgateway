<?php

/**
 * Assign member to a specific membership level list
 * @param $userId
 * @param $productId
 */
function spgateway_cc_assignment_to_membership_level($userId, $productId)
{
    $wishlistMemberLevel = get_post_meta($productId, 'wishlist_level', true);
    $wlmapi = new WLMAPI();
    $wlmapi->AddUserLevels($userId, $wishlistMemberLevel);
}

/**
 * create new order based on order and billing address
 * @param $customerInfo
 * @return mixed
 */
function spgateway_createNewWpUser($order_id)
{
    $customerInfo = spgateway_get_customer_info($order_id);
    $data = [
        'first_name'=>$customerInfo['firstName'],
        'last_name'=> $customerInfo['lastName'],
        'user_email'=>$customerInfo['email'],
        'user_login' =>$customerInfo['email'],
        'user_pass' =>'wklk157#1',
        'display_name'=>$customerInfo['firstName'] . ' ' . $customerInfo['lastName']
    ];

    $userId = wp_insert_user($data);

    if(is_array($userId->errors)) {
        return false;
    } else {
        return $userId;
    }

}

function spgateway_get_customer_info($orderId)
{
    global $wpdb; // Get the global $wpdb
    $order_id = $orderId;
    $table = $wpdb->prefix . 'postmeta';
    $sql = 'SELECT * FROM `'. $table . '` WHERE post_id = '. $order_id;
    $result = $wpdb->get_results($sql);
    $user = [];

//    print "<pre>";
//        print_r($result);
//    print "</pre>";


//    exit;
    foreach($result as $res) {


        if( $res->meta_key == '_billing_uniform_numbers'){
            $user['billing_uniform_numbers'] = $res->meta_value;      // get billing phone
        }
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