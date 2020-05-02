<?php
/**
 * Wechatpay Gateway
 *
 * @package     Give
 * @subpackage  Gateways
 * @copyright   Copyright (c) 2016, GiveWP
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wechatpay Gateway does not need a CC form, so remove it.
 *
 * @since 1.0
 * @return void
 */
add_action( 'give_wechatpay_cc_form', '__return_false' );

//add_action( 'give_cc_form', '__return_false' );


/**
 * Processes the donation data and uses the wechat Payment gateway to record
 * the donation in the Donation History
 *
 * @since 1.0
 *
 * @param array $purchase_data Donation Data
 *
 * @return void
 */
function give_wechat_payment( $purchase_data ) {

	if ( ! wp_verify_nonce( $purchase_data['gateway_nonce'], 'give-gateway' ) ) {
		wp_die( esc_html__( 'We\'re unable to recognize your session. Please refresh the screen to try again; otherwise contact your website administrator for assistance.', 'give' ), esc_html__( 'Error', 'give' ), array( 'response' => 403 ) );
	}
	
	

		function sendRequest($data, $url)
		{
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_TIMEOUT, 60);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-length:'.strlen($data)));
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_REFERER, $_SERVER['SERVER_NAME']);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLINFO_HEADER_OUT, true);
			$resp = curl_exec($ch);
			curl_close($ch);
			return $resp;
		};

		class Security
		{
			public static function encrypt($input, $key)
			{
				return base64_encode(openssl_encrypt($input, 'aes-128-ecb', $key, OPENSSL_RAW_DATA));
			}

			public static function decrypt($sStr, $sKey)
			{
				return openssl_decrypt($sStr, 'aes-128-ecb', $sKey);
			}
		}
		
	// Create payment_data array
	$payment_data = array(
		'price'           => $purchase_data['price'],
		'give_form_title' => $purchase_data['post_data']['give-form-title'],
		'give_form_id'    => intval( $purchase_data['post_data']['give-form-id'] ),
		'give_price_id'   => isset( $purchase_data['post_data']['give-price-id'] ) ? $purchase_data['post_data']['give-price-id'] : '',
		'date'            => $purchase_data['date'],
		'user_email'      => $purchase_data['user_email'],
		'purchase_key'    => $purchase_data['purchase_key'],
		'currency'        => give_get_currency( $purchase_data['post_data']['give-form-id'], $purchase_data ),
		'user_info'       => $purchase_data['user_info'],
		'status'          => 'pending',
		
	);
	
	// Record the pending payment, get a payment->ID or false
	   $payment = give_insert_payment( $payment_data );
	   $newamount = $payment_data['price']*100;
	   $payCurrency = give_get_currency( $purchase_data['post_data']['give-form-id'], $purchase_data );
	  // echo "<script>console.log('Debug Donation amount: " .$payment_data['price']. "' );</script>";
	  
	  function send_email_receipt($email, $fName, $lName, $donDate, $currency, $donAmount, $paymethod, $paymentID ){
		    $to = $email;
			$subject = "Donation Receipt";

			$message = "
			<html>
				<head>
					<title>HTML email</title>
				</head>
				<body>
				    <p>Dear $fName,</p>
				    <p>Thank you for your donation. Your generosity is appreciated! Here are the details of your donation:<p/>
					<table>
						
						<tr><td>Donor: $fName $lName</td></tr>
						
						<tr><td>Donation: 棋心协力献爱心</td></tr>
						
						<tr><td>Donation Date: $donDate</td></tr>
						
						<tr><td>Amount: $currency $donAmount</td></tr>
						
						<tr><td>Payment Method: $paymethod</td></tr>
						
						<tr><td>Payment ID: $paymentID</td></tr>

					</table>
				</body>
			</html>
			";

			// Always set content-type when sending HTML email
			$headers = "MIME-Version: 1.0" . "\r\n";
			$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

			// More headers
			$headers .= 'From: Toronto Xiangqi Association<itcitechcomputer@gmail.com> ' . "\r\n";
			

			mail($to,$subject,$message,$headers);
		  
	  }
	  
    try {
		$data_array = array();
		//$data_array['amount'] = "{$payment_data['price']}";
		$data_array['amount'] = "{$newamount}";
		$data_array['biz_type'] = 'WECHATPAY';
		//$data_array['operator_id'] = '0000020136'; //using your 10-digital operator number provided by OTTPAY;
		  $data_array['operator_id'] = '0000013849';
		$data_array['order_id'] = "$payment";
		//$data_array['order_id'] = "TEST".date("YmdHis"); //'TEST20171130175453';
		$data_array['call_back_url'] = "http://storefronttest.itecht.ca/donations/donation-for-covid-19-in-canada-2/?payment-mode=wechatpay"; //using your call back url;
		$temp_data_array = $data_array;
		ksort($temp_data_array);
		$data_str = implode(array_values($temp_data_array));
		$data_md5 = strtoupper(md5($data_str));
		//$user_key = 'AD068EF13AD0C736'; //using your Sign Key provided by OTTPAY;
		  $user_key = 'FB75D80052D33621';
		$aesKeyStr = strtoupper(substr(md5($data_md5.$user_key), 8, 16));
		$data_json = json_encode($data_array);

		$encrypted_data = Security::encrypt($data_json, $aesKeyStr);

		$params_array = array();
		$params_array['action'] = 'ACTIVEPAY';
		$params_array['version'] = '1.0';
		//$params_array['merchant_id'] = 'ON00005457'; //using your Merchant ID provided by OTTPAY;
		  $params_array['merchant_id'] = 'ON00003823'; //using your Merchant ID provided by OTTPAY;

		$params_array['data'] = $encrypted_data;
		$params_array['md5'] = $data_md5;
		$params_json = json_encode($params_array, JSON_UNESCAPED_UNICODE);

		$resp_data = sendRequest($params_json, 'https://frontapi.ottpay.com:443/processV2');

		$resp_arr = (array) json_decode($resp_data, true);
		$aesKeyStr = strtoupper(substr(md5($resp_arr['md5'].$user_key), 8, 16));

		$decrypted_data = Security::decrypt($resp_arr['data'], $aesKeyStr);

		$return_data_arr = (array) json_decode($decrypted_data, true);
		$qrCode_url = $return_data_arr['code_url'];

		//echo 'response qrCode_url = '.$qrCode_url;	
		

?>

	    <p style="margin-left: 645px; padding-top: 20px">使用“微信扫一扫”扫描二维码支付</p>	
		<script   src="<?php echo GIVE_PLUGIN_URL?>assets/dist/js/qrcode.js"  ></script>
           
    	<div id="WxQRCode" style="width:200px;height:200px; margin-left: 653px; padding-top: 30px" ></div>
		<p id = "invalidqr" style="margin-left: 620px; padding-top: 20px"></p>
       

		
	    <script type="text/javascript" >
		
            var paymentID = <?php echo $payment ?>	
			var paystatus = "failed";
			
			function ajaxcall(str = paymentID){
				//alert("come here");
				var xhr = new XMLHttpRequest();
				xhr.onreadystatechange = function() {
					if (this.readyState == 4 && this.status == 200){
						        console.log("response text:"+this.responseText);
								
								 if(xhr.responseText=='success'){
									console.log("payment succeed");
                                    location.href = 'http://storefronttest.itecht.ca/donation-confirmation/';
									
									<?php
							         send_email_receipt($purchase_data['user_email'], $purchase_data['user_info']['first_name'], $purchase_data['user_info']['last_name'], $purchase_data['date'],  $payCurrency, $payment_data['price'], $data_array['biz_type'], $payment);
							        ?>

				                     return; 

								 } 
								 
								// setTimeout(ajaxcall(str = paymentID), 2000);
								 
								if(xhr.responseText=='init'){
									console.log("Please scan for payment");
							       // setTimeout(ajaxcall(str = paymentID), 2000);

								}
								
								if(xhr.responseText=='notpay'){
									console.log("二维码已经失效，请刷新页面后再扫码");
							        var invalidqr = document.getElementById('invalidqr').innerHTML = "二维码已经失效，请刷新页面后再扫码";
									
									return;

								}
								
								setTimeout(function(){ajaxcall( paymentID);},3000);
						       
				    }	
						
				}
					
					xhr.open("GET", "<?php echo GIVE_PLUGIN_URL?>includes/gateways/wechatpay-status-query.php?q=" + str, true);

					xhr.send();
					
			}
			
			
			 var qrcode = new QRCode(document.getElementById("WxQRCode"), {width : 200,height : 200});	   
				 <?php if(!empty($qrCode_url)){?>
					 qrcode.makeCode("<?php print $qrCode_url?>");
					 setTimeout(function(){ajaxcall( paymentID);},3000); 
			     <?php }?>
				 
				
	   </script>
		
		  
<?php
	}catch (Exception $e) {
?>
			  <ul class="donation-error">
        			<li><?php echo $e->getMessage();?></li>
        	  </ul>
<?php 
	}	
	echo "<script>console.log('Debug payment status: " .$payment. "' );</script>";
	/*
	$paystatusPhpVar = "";
		
	echo "<script>console.log('Debug payment status: " .$paystatusPhpVar. "' );</script>";

	
		if ( $paystatusPhpVar=='success' ) {
			give_update_payment_status( $payment, 'publish' );
			
			give_send_to_success_page();
			
		}else {
			give_record_gateway_error(
				esc_html__( 'Payment Error', 'give' ),
				sprintf(
					//------ translators: %s: payment data ---------------------
					esc_html__( 'The payment creation failed while processing a manual (free or test) donation. Payment data: %s', 'give' ),
					json_encode( $payment_data )
				),
				$payment
			);
			// If errors are present, send the user back to the donation page so they can be corrected
			give_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['give-gateway'] );
		}
	*/
	
	

}

add_action( 'give_gateway_wechatpay', 'give_wechat_payment' );
