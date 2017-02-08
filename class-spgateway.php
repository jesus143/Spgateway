<?php
if(!session_id()) {
    session_start();
}
// print "<H1> tESTING PLUGIN </H1>";
/**
 * spgateway Payment Gateway
 * Plugin URI: http://www.spgateway.com/
 * Description: spgateway 收款模組
 * Version: 1.0.0
 * Author URI: http://www.spgateway.com/
 * Author: 智付通 spgateway
 * Plugin Name:   Spgateway Credit Card Payment
 * @class 		spgateway
 * @extends		WC_Payment_Gateway
 * @version
 * @author 	Pya2go Libby
 * @author 	Pya2go Chael
 * @author  Spgateway Geoff
 */



/**
 * Requirements:
 * This plugin require a template for the response, currently template located at
 * spgateway-manage-response plugin name and you can just copy the template file there and add to theme folder
 * current active then u need to create a page name "spgateway payment response" and url link should like this
 * http://demo4.iamrockylin.com/spgateway-payment-response/ after the product purchase here spgateway will redirect
 */


require_once(ABSPATH . "/wp-includes/user.php");
require_once(ABSPATH . "/wp-includes/pluggable.php");
require_once(ABSPATH . "/wp-content/plugins/Spgateway/helper.php" );



add_action('plugins_loaded', 'spgateway_gateway_init', 0);


function spgateway_gateway_init() {




    if (!class_exists('WC_Payment_Gateway')) {
        return;
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



    class WC_spgateway extends WC_Payment_Gateway {

        protected $productId = 0;

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

            $this->id = 'spgateway_credit_card';
            $this->icon = apply_filters('woocommerce_spgateway_icon', plugins_url('icon/spgateway.png', __FILE__));
            $this->has_fields = false;
            $this->method_title = __('Spgateway Credit Card', 'woocommerce');

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
                    'default' => __('Spgateway Credit Card', 'woocommerce')
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


        protected function print_installment_pay($productId)
        {

            $actual_link = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
            $installmentForm = '';
            $installment = $this->spgateway_get_installments($productId);
            // $installmentForm = "<b>Select card installment type</b><br>";
            // $installmentForm .= '<form name="spgateway_installment_form"  action="'.$actual_link.'" method="post" >';

            if(!empty($installment)) {
                foreach ($installment as $key => $value) {

                    $display_name = '';
                    if ($value == 'default') {
                        $value = 'pay full';

                    } else {
                        $display_name = ' monthly pay';
                    }

                    if ($key == 0) {
                        $installmentForm .= '<input type="radio" name="spgateway_cc_installment_pay" value="' . $value . '" checked/> ' . $value . $display_name . '<br>';
                    } else {
                        $installmentForm .= '<input type="radio" name="spgateway_cc_installment_pay" value="' . $value . '" /> ' . $value . $display_name . '<br>';
                    }
                }
                // $installmentForm  .= '<br><input type="submit" value="Choose" name="spgateway_cc_submit"/>';
                // $installmentForm .= '</form>';
            }
            return $installmentForm;
        }


        protected function submit_choose_installment_pay()
        {
            $installment = $_POST;
            $spgateway_args = [];

            if(isset($installment['spgateway_cc_submit'])) {
//                print_r( $installment);
                if ($installment['spgateway_cc_installment_pay'] != 'pay full') {
                    // full pay

                    return $installment['spgateway_cc_installment_pay'];

                }else {
                    return false;
                }
            }
        }

        protected function spgateway_get_installments($productId)
        {
           return get_post_meta($productId, 'credit_installments', true );

//            print_r($key_1_value);
                        // Check if the custom field has a value.
            //            if ( ! empty( $key_1_value ) ) {
            //                return unserialize($key_1_value);
            //            }
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




            //            print "<pre>";
            //            print "post";
            //            print_r($_POST);
            //            print "session";
            //            print_r($_SESSION);
            //            print "cokie";
            //            print_r($_COOKIE);
            //            exit;
            error_reporting(0);
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
            //            $spgateway_args['ReturnURL'] = spgateway_set_return_url(['itemName'=>$item_name, 'sendRightKeyWord'=>$sendRightKeyWord, 'orderId'=>$order_id]);
            $spgateway_args['ReturnURL'] = '';
            // create user's account



            // Create new wp user if not exist
            spgateway_createNewWpUser($order_id);

            // Assign member to a wishlist membership level
            spgateway_cc_assignment_to_membership_level(get_user_by( 'email', spgateway_get_customer_info($order_id)['email'] )->data->ID, $spgateway_args['Pid1'] );


            // add to




            //            $pa_koostis_value = get_post_meta($product->id);


             // make filter to detect if this is sendright product then if so, we need to redirect to thank you page
             // for sendright registration
             // $spgateway_args['ReturnURL'] = get_site_url() . '/thank-you?orderId='.$order_id;
             //  print "<pre>";
             // print "product title " . $spgateway_args['Title1'];
             // print "spgateway arg";
            //                         print_r($_product);
            //                         print_r($item_nam);
            $_SESSION['spgateway_args'] = $spgateway_args;


            // print_r($_POST);
//            print "<pre>";
//            print_r($spgateway_args);
//            print "</pre>";
//            exit;







            //            exit;

            //            if(!empty($this->spgateway_get_installments($spgateway_args['Pid1'])) and count($_POST) < 1) {
            //                //
            //                print "<b>Please select your installment plan.</b><br>";
            //                //                print "create form ";
            //
            //
            //                print $this->print_installment_pay($spgateway_args['Pid1']);
            //                $installmentOption = $this->submit_choose_installment_pay();
            //                //                print "installment flag " .   $installmentOption;
            //                if (!empty($installmentOption)) {
            //                    $spgateway_args['InstFlag'] = $installmentOption;
            //                }
            //                return false;
            //            } else {
            //
            //                $spgateway_args['InstFlag'] = $this->submit_choose_installment_pay();
            //                if(!empty($spgateway_args['InstFlag'])) {
            //                    $spgateway_args['CREDIT'] = 0;
            //                }
            //                //                print "no create form";
            //                //                $spgateway_args['InstFlag'] = '';
            //            }



            $installment = (!empty($_SESSION['spgateway_payment_gateway_installment_choice'])) ? $_SESSION['spgateway_payment_gateway_installment_choice'] : null;
            if($installment != 'pay full' and !empty($installment)) {
                $spgateway_args['InstFlag'] = $installment;
                $spgateway_args['CREDIT'] = 0;
            }




                //            print_r( $spgateway_args);
                //            exit ;
            $spgateway_gateway = $this->gateway;
            $spgateway_args_array = array();
            foreach ($spgateway_args as $key => $value) {
                $spgateway_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
            }

            return '<form id="spgateway" name="spgateway" action=" ' . $spgateway_gateway . ' " method="post" target="_top">' . implode('', $spgateway_args_array) . '
  				    <input type="submit" class="button-alt" id="submit_spgateway_payment_form" value="' . __('前往 spgateway 支付頁面', 'spgateway') . '" />
  				    </form>' . "<script>setTimeout(\"document.forms['spgateway'].submit();\",\"100\")</script>";
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

            //            print "<h1> Send Invoice to customer<h1>";
            //            print "<h1> Display design for thank you page</h1>";
            //           exit;
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
//            echo '<p>' . __('Please select <br>', 'spgateway') . '</p>';
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
         public function payment_fields() {

             // call global variable from woocomerece
             global $woocommerce;

             // call content of the current cart
             $items = $woocommerce->cart->get_cart();

             // print desccription of the payment gateway
             if ($this->description) {
                 echo wpautop(wptexturize($this->description));
             }

             // now lets display the html fields, this is the radio buttons
             foreach($items as $item => $values) {
                 $productId = $values['product_id'];
                 $installmentOptions = $this->print_installment_pay($productId);
                 break;
             }
             print $installmentOptions;

         }


        function check_spgateway_response() {
            echo "ok";
        }
    }
}


// so this is the code to manipulate of all of your post request
// in check out page in woocomerce
// just paste this code anywhere in your plugin or functions.php
// and customize the post data
// add in session so that you can use it anywhere of the site
add_action('woocommerce_checkout_process', 'spgateway_checkout_field_process');

function spgateway_checkout_field_process() {

    // pass the post variable into a session so that we can use it in another page
    $_SESSION['spgateway_payment_gateway_installment_choice'] = $_POST['spgateway_cc_installment_pay'];

    /// show an error if something wrong in the field, lets put the session so that we can see the data in the post
    //    if(!$_POST['spgateway_cc_installment_pay']) {
    //        wc_add_notice(__('Please select installment plan!'), 'error');
    //    }

}


