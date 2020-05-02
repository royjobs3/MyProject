<?php
	
	//require_once("../payments/functions.php");
	//require_once("../forms/functions.php");
   
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

    function is_paid($out_trad_no){
        $data_array = array();
        $data_array['order_id'] = $out_trad_no; //'TEST20171130175453';
        $temp_data_array = $data_array;
        ksort($temp_data_array);
        reset($temp_data_array);
        $data_str = implode(array_values($temp_data_array));
        $data_md5 = strtoupper(md5($data_str));
        //$user_key = 'AD068EF13AD0C736'; //using your Sign Key provided by OTTPAY;
		  $user_key = 'FB75D80052D33621';
        $aesKeyStr = strtoupper(substr(md5($data_md5.$user_key),8,16));
        $data_json = json_encode($data_array);
        $nonce = random_bytes(24);
        $encrypted_data = Security::encrypt($data_json, $aesKeyStr);
        	
        $params_array = array();
        $params_array['action'] = 'STATUS_QUERY';
        $params_array['version'] = '1.0';
        //$params_array['merchant_id'] = 'ON00005457'; //using your Merchant ID provided by OTTPAY;
		  $params_array['merchant_id'] = 'ON00003823';
        $params_array['data'] = $encrypted_data;
        $params_array['md5'] = $data_md5;
        $params_json = json_encode($params_array, JSON_UNESCAPED_UNICODE);
        
		
		$resp_data = sendRequest($params_json, 'https://frontapi.ottpay.com:443/processV2');
		
		// $resp_data = http_post('https://frontapi.ottpay.com:443/processV2',$params_json,false,$ch);
        
        $resp_arr = (array) json_decode($resp_data, true);
        
		/*
        if($resp_arr['rsp_code']!=='SUCCESS'){
			//throw new Exception(print_r($resp_arr,true));
            $results = print_r($resp_arr,true);
		   return $results;
        }
		*/
        	
        $aesKeyStr = strtoupper(substr(md5($resp_arr['md5'].$user_key),8,16));
        $decrypted_data = Security::decrypt($resp_arr['data'], $aesKeyStr);
        	
        $return_data_arr = (array) json_decode($decrypted_data, true);
		 //$results = print_r($return_data_arr,true);
		 //  return $results;
		 return $return_data_arr['order_status'];
		 //return $return_data_arr['order_status']=='success';
    }
	
	
// get the q parameter from URL
   
   $q = $_REQUEST["q"];
   
   $intPayID  = (int)$q;
   
   $paymentstatus = is_paid($q);
   	
	//echo $paymentstatus;
  
   //echo "<script>console.log('Debug payment status: " .$paymentstatus. "' );</script>";
	
	
		if ( $paymentstatus =='success' ) {
			require_once("../../../../../wp-load.php");
			 $my_post = array(
				  'ID'            => $intPayID,
				  'post_status'   => 'publish',
			  );
			 
			// Update the post into the database
			  wp_update_post( $my_post );
			  
	        //require_once("../forms/functions.php");
			//give_update_payment_status( $payment, 'publish' );
			
			//give_send_to_success_page();
			
			echo 'success';
			
		}else {
			
			echo $paymentstatus;
			/*
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
			*/
		}


?>