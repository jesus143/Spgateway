<?php

// print "<H1> tESTING PLUGIN </H1>";
/**
 * spgateway Payment Gateway
 * Plugin URI: http://www.spgateway.com/
 * Description: spgateway 收款模組
 * Version: 1.0.0
 * Author URI: http://www.spgateway.com/
 * Author: 智付通 spgateway
 * Plugin Name:   spgateway Development
 * @class 		spgateway
 * @extends		WC_Payment_Gateway
 * @version
 * @author 	Pya2go Libby
 * @author 	Pya2go Chael
 * @author  Spgateway Geoff
 */


require_once(ABSPATH . "/wp-includes/user.php");
require_once(ABSPATH . "/wp-includes/pluggable.php.");
require_once( "helper.php" );



add_action('plugins_loaded', 'spgateway_gateway_init', 0);
add_action('phpmailer_init', 'spg_mailtrap');


function spg_mailtrap($phpmailer) {
     $phpmailer->isSMTP();
     $phpmailer->Host = 'mailtrap.io';
     $phpmailer->SMTPAuth = true;
     $phpmailer->Port = 2525;
     $phpmailer->Username = 'a2b98b4b2ea114';
     $phpmailer->Password = '793807e9810e6f';
}

function spgateway_gateway_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_spgateway extends WC_Payment_Gateway {

        /**
         * Constructor for the gateway.
         *
         * @access public
         * @return void
         */
        public function __construct() {
            // Check ExpireDate is validate or not
            if(isset($_POST['woocommerce_spgateway_ExpireDate']) && (!preg_match('/^\d*$/', $_POST['woocommerce_spgateway_ExpireDate']) || $_POST['woocommerce_spgateway_ExpireDate'] < 1 || $_POST['woocommerce_spgateway_ExpireDate'] > 180)){
              $_POST['woocommerce_spgateway_ExpireDate'] = 7;
            }

            $this->id = 'spgateway';
            $this->icon = apply_filters('woocommerce_spgateway_icon', plugins_url('icon/spgateway.png', __FILE__));
            $this->has_fields = false;
            $this->method_title = __('spgateway', 'woocommerce');

            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            // Define user set variables
            $this->title = $this->settings['title'];
            $this->LangType = $this->settings['LangType'];
            $this->description = $this->settings['description'];
            $this->MerchantID = trim($this->settings['MerchantID']);
            $this->HashKey = trim($this->settings['HashKey']);
            $this->HashIV = trim($this->settings['HashIV']);
            $this->ExpireDate = $this->settings['ExpireDate'];
            $this->TestMode = $this->settings['TestMode'];
            $this->notify_url = add_query_arg('wc-api', 'WC_spgateway', home_url('/')) . '&callback=return';

            // Test Mode
            if ($this->TestMode == 'yes') {
                $this->gateway = "https://ccore.spgateway.com/MPG/mpg_gateway"; //測試網址
            } else {
                $this->gateway = "https://core.spgateway.com/MPG/mpg_gateway"; //正式網址
            }

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'receive_response')); //api_"class名稱(小寫)"
            add_action('woocommerce_checkout_update_order_meta', array($this, 'electronic_invoice_fields_update_order_meta'));
        }

        /**
         * Initialise Gateway Settings Form Fields
         *
         * @access public
         * @return void
         * 後台欄位設置
         */
        function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('啟用/關閉', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('啟動 Spgateway 收款模組', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('標題', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('客戶在結帳時所看到的標題', 'woocommerce'),
                    'default' => __('spgateway', 'woocommerce')
                ),
                'LangType' => array(
                    'title' => __('支付頁語系', 'woocommerce'),
                    'type' => 'select',
                    'options' => array(
                        'en' => 'En',
                        'zh-tw' => '中文'
                    )
                ),
                'description' => array(
                    'title' => __('客戶訊息', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('', 'woocommerce'),
                    'default' => __('透過 Spgateway 付款。<br>會連結到 Spgateway 頁面。', 'woocommerce')
                ),
                'MerchantID' => array(
                    'title' => __('Merchant ID', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('請填入您Spgateway商店代號', 'woocommerce')
                ),
                'HashKey' => array(
                    'title' => __('Hash Key', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('請填入您Spgateway的HashKey', 'woocommerce')
                ),
                'HashIV' => array(
                    'title' => __('Hash IV', 'woocommerce'),
                    'type' => 'text',
                    'description' => __("請填入您Spgateway的HashIV", 'woocommerce')
                ),
                'ExpireDate' => array(
                    'title' => __('繳費有效期限(天)', 'woocommerce'),
                    'type' => 'text',
                    'description' => __("請設定繳費有效期限(1~180天), 預設為7天", 'woocommerce'),
                    'default' => 7
                ),
                'TestMode' => array(
                    'title' => __('測試模組', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('啟動測試模組', 'woocommerce'),
                    'default' => 'yes'
                )
            );
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         *
         * @access public
         * @return void
         */
        public function admin_options() {

            ?>
            <h3><?php _e('智付通 spgateway 收款模組', 'woocommerce'); ?></h3>
            <p><?php _e('此模組可以讓您使用智付通的spgateway收款功能', 'woocommerce'); ?></p>
            <table class="form-table">
                <?php
                // Generate the HTML For the settings form.
                $this->generate_settings_html();
                ?>
                <script>
                  var invalidate = function(){
                        jQuery(this).css('border-color', 'red');
                        jQuery('#'+this.id+'_error_msg').show();
                        jQuery('input[type="submit"]').prop('disabled', 'disabled');
                      },
                      validate = function(){
                        jQuery(this).css('border-color', '');
                        jQuery('#'+this.id+'_error_msg').hide();
                        jQuery('input[type="submit"]').prop('disabled', '');
                      }

                  jQuery('#woocommerce_spgateway_ExpireDate')
                    .bind('keypress', function(e){
                      if(e.charCode < 48 || e.charCode > 57){
                        return false;
                      }
                    })
                    .bind('blur', function(e){
                      if(!this.value){
                        validate.call(this);
                      }
                    });

                  jQuery('#woocommerce_spgateway_ExpireDate')
                    .bind('input', function(e){
                      if(!this.value){
                        validate.call(this);
                        return false;
                      }

                      if(this.value < 1 || this.value > 180){
                        invalidate.call(this);
                      } else {
                        validate.call(this);
                      }
                    })
                    .bind('blur', function(e){
                      if(!this.value){
                        this.value = 7;
                        validate.call(this);
                      }
                    })
                    .after('<span style="display: none;color: red;" id="woocommerce_spgateway_ExpireDate_error_msg">請輸入範圍內1~180的數字</span>')
                </script>
            </table><!--/.form-table-->
            <?php
        }

        /**
         * Get spgateway Args for passing to spgateway
         *
         * @access public
         * @param mixed $order
         * @return array
         *
         * MPG參數格式
         */
        function get_spgateway_args($order) {

            global $woocommerce;

            $merchantid = $this->MerchantID; //商店代號
            $respondtype = "String"; //回傳格式
            $timestamp = time(); //時間戳記
            $version = "1.1"; //串接版本
            $order_id = $order->id;
            $amt = $order->get_total(); //訂單總金額
            $logintype = "0"; //0:不需登入智付通會員，1:須登入智付通會員
            //商品資訊
            $item_name = $order->get_items();
            $item_cnt = 1;
            $itemdesc = "";
            foreach ($item_name as $item_value) {
                if ($item_cnt != count($item_name)) {
                    $itemdesc .= $item_value['name'] . " × " . $item_value['qty'] . "，";
                } elseif ($item_cnt == count($item_name)) {
                    $itemdesc .= $item_value['name'] . " × " . $item_value['qty'];
                }

                //支付寶、財富通參數
                $spgateway_args_1["Count"] = $item_cnt;
                $spgateway_args_1["Pid$item_cnt"] = $item_value['product_id'];
                $spgateway_args_1["Title$item_cnt"] = $item_value['name'];
                $spgateway_args_1["Desc$item_cnt"] = $item_value['name'];
                $spgateway_args_1["Price$item_cnt"] = $item_value['line_subtotal'] / $item_value['qty'];
                $spgateway_args_1["Qty$item_cnt"] = $item_value['qty'];

                $item_cnt++;
            }

            //CheckValue 串接
            $check_arr = array('MerchantID' => $merchantid, 'TimeStamp' => $timestamp, 'MerchantOrderNo' => $order_id, 'Version' => $version, 'Amt' => $amt);
            //按陣列的key做升幕排序
            ksort($check_arr);
            //排序後排列組合成網址列格式
            $check_merstr = http_build_query($check_arr, '', '&');
            $checkvalue_str = "HashKey=" . $this->HashKey . "&" . $check_merstr . "&HashIV=" . $this->HashIV;
            $CheckValue = strtoupper(hash("sha256", $checkvalue_str));

            $buyer_name = $order->billing_last_name . $order->billing_first_name;
            $total_fee = $order->order_total;
            $tel = $order->billing_phone;
            $spgateway_args_2 = array(
                "MerchantID" => $merchantid,
                "RespondType" => $respondtype,
                "CheckValue" => $CheckValue,
                "TimeStamp" => $timestamp,
                "Version" => $version,
                "MerchantOrderNo" => $order_id,
                "Amt" => $amt,
                "ItemDesc" => $itemdesc,
                "ExpireDate" => date('Ymd', time()+intval($this->ExpireDate)*24*60*60),
                "Email" => $order->billing_email,
                "LoginType" => $logintype,
                "NotifyURL" => $this->notify_url, //幕後
                "ReturnURL" => $this->get_return_url($order), //幕前(線上)
                "ClientBackURL" => $this->get_return_url($order), //取消交易
                "CustomerURL" => $this->get_return_url($order), //幕前(線下)
                "Receiver" => $buyer_name, //支付寶、財富通參數
                "Tel1" => $tel, //支付寶、財富通參數
                "Tel2" => $tel, //支付寶、財富通參數
                "LangType" => $this->LangType,
                'CREDIT' => true,
                'UNIONPAY' => false,
                'WEBATM' => false,
                'VACC'=>false,
                'CVS'=>false,
                'BARCODE'=>false
            );

            $spgateway_args = array_merge($spgateway_args_1, $spgateway_args_2);
            $spgateway_args = apply_filters('woocommerce_spgateway_args', $spgateway_args);
            return $spgateway_args;
        }
        /**
         * Generate the spgateway button link (POST method)
         *
         * @access public
         * @param mixed $order_id
         * @return string
         */
        function generate_spgateway_form($order_id) {

            global $woocommerce;
            $order = new WC_Order($order_id);
            $spgateway_args = $this->get_spgateway_args($order);
            $item_name = $order->get_items();
            $sendRightKeyWord = 'sendright';
            $name = '';
            $items = $order->get_product_from_item( $item_name );
            //             $_product = wc_get_product(  66 );
            //            foreach($item_name as $key => $value) {
            //                print " test " . $value['product_id'];
            //            }
            // get setup return url for sendright
            $spgateway_args['ReturnURL'] = spgateway_set_return_url(['itemName'=>$item_name, 'sendRightKeyWord'=>$sendRightKeyWord, 'orderId'=>$order_id]);

            // create user's account
            $customerInfo = spgateway_get_customer_info($order_id);
            $status = spgateway_createNewWpUser( [
                'first_name'=>$customerInfo['firstName'],
                'last_name'=> $customerInfo['lastName'],
                'user_email'=>$customerInfo['email'],
                'user_login' =>$customerInfo['email'],
                'display_name'=>$customerInfo['firstName'] . ' ' . $customerInfo['lastName']
            ]);


//            $pa_koostis_value = get_post_meta($product->id);


             // make filter to detect if this is sendright product then if so, we need to redirect to thank you page
             // for sendright registration
             // $spgateway_args['ReturnURL'] = get_site_url() . '/thank-you?orderId='.$order_id;
//                         print "<pre>";
             // print "product title " . $spgateway_args['Title1'];
             // print "spgateway arg";
            //                         print_r($_product);
            //                         print_r($item_nam);
                                     print_r($spgateway_args);
            //                         print_r($order);
//                                     print "</pre>";
            //                         exit;
//                         exit;
            $spgateway_gateway = $this->gateway;
            $spgateway_args_array = array();
            foreach ($spgateway_args as $key => $value) {
                $spgateway_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
            }


            // create users account




            return '<form id="spgateway" name="spgateway" action=" ' . $spgateway_gateway . ' " method="post" target="_top">' . implode('', $spgateway_args_array) . '
				<input type="submit" class="button-alt" id="submit_spgateway_payment_form" value="' . __('前往 spgateway 支付頁面', 'spgateway') . '" />
				</form>' . "<script>setTimeout(\"document.forms['spgateway'].submit();\",\"3000\")</script>";
        }
        /**
         * Output for the order received page.
         *
         * @access public
         * @return void
         */
        function thankyou_page() { 

            // PRINT "<PRE>"; 
            // print "post";
            // print_r($_POST);  
            // print "get";
            // print_r($_GET);  
            // print "session";
            // print_r($_SESSION);   
            // PRINT "</PRE>";  
            // if(wp_mail("mrjesuserwinsuarez@gmail.com", "test", "test")) {
            //     print "invoice sent to email";
            // } else {
            //     print "invoice not sent to email";
            // }  
            // print "This is the thank you page"; 
            // print "<script> Thank you page loaded</script>";



            // exit;
            if(isset($_REQUEST['order-received']) && isset($_REQUEST['key']) && preg_match('/^wc_order_/', $_REQUEST['key']) && isset($_REQUEST['page_id'])){
              $order = new WC_Order($_REQUEST['order-received']);
            }

            if (isset($_REQUEST['PaymentType']) && ($_REQUEST['PaymentType'] == "CREDIT" || $_REQUEST['PaymentType'] == "WEBATM")) {
                if (in_array($_REQUEST['Status'], array('SUCCESS', 'CUSTOM'))) {
                    echo "交易成功<br>";
                } else {
                    isset($order) && $order->remove_order_items();
                    isset($order) && $order->update_status('failed');
                    echo "交易失敗，請重新填單<br>錯誤代碼：" . $_REQUEST['Status'] . "<br>錯誤訊息：" . $_REQUEST['Message'];
                }
            } else if (isset($_REQUEST['PaymentType']) && ($_REQUEST['PaymentType'] == "VACC")) {
                if ($_REQUEST['BankCode'] != "" && $_REQUEST['CodeNo'] != "") {
                    echo "付款方式：ATM<br>";
                    echo "取號成功<br>";
                    echo "銀行代碼：" . $_REQUEST['BankCode'] . "<br>";
                    echo "繳費代碼：" . $_REQUEST['CodeNo'] . "<br>";
                } else {
                    isset($order) && $order->remove_order_items();
                    isset($order) && $order->update_status('failed');
                    echo "交易失敗，請重新填單<br>錯誤代碼：" . $_REQUEST['Status'] . "<br>錯誤訊息：" . $_REQUEST['Message'];
                }
            } else if (isset($_REQUEST['PaymentType']) && ($_REQUEST['PaymentType'] == "CVS")) {
                if ($_REQUEST['CodeNo'] != "") {
                    echo "付款方式：超商代碼<br>";
                    echo "取號成功<br>";
                    echo "繳費代碼：" . $_REQUEST['CodeNo'] . "<br>";
                } else {
                    isset($order) && $order->remove_order_items();
                    isset($order) && $order->update_status('failed');
                    echo "交易失敗，請重新填單<br>錯誤代碼：" . $_REQUEST['Status'] . "<br>錯誤訊息：" . $_REQUEST['Message'];
                }
            } else if (isset($_REQUEST['PaymentType']) && ($_REQUEST['PaymentType'] == "BARCODE")) {
                if ($_REQUEST['Barcode_1'] != "" || $_REQUEST['Barcode_2'] != "" || $_REQUEST['Barcode_3'] != "") {
                    echo "付款方式：條碼<br>";
                    echo "取號成功<br>";
                    echo "請前往信箱列印繳費單<br>";
                } else {
                    isset($order) && $order->remove_order_items();
                    isset($order) && $order->update_status('failed');
                    echo "交易失敗，請重新填單<br>錯誤代碼：" . $_REQUEST['Status'] . "<br>錯誤訊息：" . $_REQUEST['Message'];
                }
            } else if (isset($_REQUEST['PaymentType']) && ($_REQUEST['PaymentType'] == "ALIPAY" || $_REQUEST['PaymentType'] == "TENPAY")) {
                if (in_array($_REQUEST['Status'], array('SUCCESS', 'CUSTOM'))) {
                    echo "交易成功<br>";
                    if ($_REQUEST['ChannelID'] == "ALIPAY") {
                        echo "跨境通路類型：支付寶<br>";
                    } else if ($_REQUEST['ChannelID'] == "TENPAY") {
                        echo "跨境通路類型：財富通<br>";
                    }
                    echo "跨境通路交易序號：" . $_REQUEST['ChannelNO'] . "<br>";
                } else {
                    isset($order) && $order->remove_order_items();
                    isset($order) && $order->update_status('failed');
                    echo "交易失敗，請重新填單<br>錯誤代碼：" . $_REQUEST['Status'] . "<br>錯誤訊息：" . $_REQUEST['Message'];
                }
            } else if ($_REQUEST['Status'] == 'CUSTOM') {
                echo "付款方式：{$_REQUEST['PaymentType']}<br>";
            } else if ($_REQUEST['Status'] == "" && $_REQUEST['Message'] == "") {
                // isset($order) && $order->cancel_order();
                echo "交易取消<br>";
            } else {
                isset($order) && $order->cancel_order();
                echo "交易失敗，請重新填單<br>錯誤代碼：" . $_REQUEST['Status'] . "<br>錯誤訊息：" . $_REQUEST['Message'];
            }

            print "<h1> Send Invoice to customer<h1>"; 
            print "<h1> Display design for thank you page</h1>";

//            exit;
        }

        function addpadding($string, $blocksize = 32) {
            $len = strlen($string);
            $pad = $blocksize - ($len % $blocksize);
            $string .= str_repeat(chr($pad), $pad);
            return $string;
        }

        function curl_work($url = "", $parameter = "") {
            $curl_options = array(
                CURLOPT_URL => $url,
                CURLOPT_HEADER => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT => "Google Bot",
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => FALSE,
                CURLOPT_SSL_VERIFYHOST => FALSE,
                CURLOPT_POST => "1",
                CURLOPT_POSTFIELDS => $parameter
            );
            $ch = curl_init();
            curl_setopt_array($ch, $curl_options);
            $result = curl_exec($ch);
            $retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_errno($ch);
            curl_close($ch);

            $return_info = array(
                "url" => $url,
                "sent_parameter" => $parameter,
                "http_status" => $retcode,
                "curl_error_no" => $curl_error,
                "web_info" => $result
            );
            return $return_info;
        }

        function receive_response() {  //接收回傳參數驗證
            $re_MerchantOrderNo = trim($_REQUEST['MerchantOrderNo']);
            $re_MerchantID = $_REQUEST['MerchantID'];
            $re_Status = $_REQUEST['Status'];
            $re_TradeNo = $_REQUEST['TradeNo'];
            $re_CheckCode = $_REQUEST['CheckCode'];
            $re_Amt = $_REQUEST['Amt'];

            $order = new WC_Order($re_MerchantOrderNo);
            $Amt = $order->get_total();

            //CheckCode 串接
            $code_arr = array('MerchantID' => $this->MerchantID, 'TradeNo' => $re_TradeNo, 'MerchantOrderNo' => $re_MerchantOrderNo, 'Amt' => $Amt);
            //按陣列的key做升幕排序
            ksort($code_arr);
            //排序後排列組合成網址列格式
            $code_merstr = http_build_query($code_arr, '', '&');
            $checkcode_str = "HashIV=" . $this->HashIV . "&" . $code_merstr . "&HashKey=" . $this->HashKey;
            $CheckCode = strtoupper(hash("sha256", $checkcode_str));

            //檢查回傳狀態是否為成功
            if (in_array($re_Status, array('SUCCESS', 'CUSTOM'))) {
                //檢查CheckCode是否跟自己組的一樣
                if ($CheckCode == $re_CheckCode) {
                    //檢查金額是否一樣
                    if ($Amt == $re_Amt) {
                        //全部確認過後，修改訂單狀態(處理中，並寄通知信)
                        $order->payment_complete();
                        $msg = "訂單修改成功";
                    } else {
                        $msg = "金額不一致";
                    }
                } else {
                    $msg = "checkcode碼錯誤";
                }
            } else if ($re_Status == "CUSTOM") {
                //檢查CheckCode是否跟自己組的一樣
                if ($CheckCode == $re_CheckCode) {
                    //檢查金額是否一樣
                    if ($Amt == $re_Amt) {
                        $msg = "訂單處理成功";
                    } else {
                        $msg = "金額不一致";
                    }
                } else {
                    $msg = "checkcode碼錯誤";
                }
            } else {
                $msg = "訂單處理失敗";
            }

            if (isset($_GET['callback'])) {
                echo $msg;
                exit; //一定要有離開，才會被正常執行
            }
        }



        /**
         * Output for the order received page.
         *
         * @access public
         * @return void
         */
        function receipt_page($order) {
            echo '<p>' . __('3秒後會自動跳轉到spgateway支付頁面，或者按下方按鈕直接前往<br>', 'spgateway') . '</p>';
            echo $this->generate_spgateway_form($order);
        }

        /**
         * Process the payment and return the result
         *
         * @access public
         * @param int $order_id
         * @return array
         */
        function process_payment($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);

            // Empty awaiting payment session
            unset($_SESSION['order_awaiting_payment']);
            //$this->receipt_page($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        /**
         * Payment form on checkout page
         *
         * @access public
         * @return void
         */
        function payment_fields() {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }

        function check_spgateway_response() {
            echo "ok";
        }
    }

    /**
     * Add the gateway to WooCommerce
     *
     * @access public
     * @param array $methods
     * @package		WooCommerce/Classes/Payment
     * @return array
     */
    function add_spgateway_gateway($methods) {
        $methods[] = 'WC_spgateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_spgateway_gateway');
}
?>
