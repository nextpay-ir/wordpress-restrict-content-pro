<?php
/*
Plugin Name: Restrict Content Pro  درگاه پرداخت نکست پی برای 
Version: 1.0.0
Description: درگاه پرداخت <a href="https://nextpay.ir/" target="_blank"> نکست پی </a> برای افزونه Restrict Content Pro
Plugin URI: https://nextpay.ir
Author: نکست پی
Author URI: https://nextpay.ir
License: GPL 2
*/
if (!defined('ABSPATH')) exit;
require_once('NextpayStd_Session.php');
if (!class_exists('NextPay') ) {
	class NextPay {
	
		public function __construct() {
			add_action('init', array($this, 'NextPay_Verify_By_NextpayStd'));
			add_action('payments_settings', array($this, 'NextPay_Setting_By_NextpayStd'));
			add_action('gateway_NextPay', array($this, 'NextPay_Request_By_NextpayStd'));
			add_filter('payment_gateways', array($this, 'NextPay_Register_By_NextpayStd'));
			if (!function_exists('IRAN_Currencies_By_NextpayStd') && !function_exists('IRAN_Currencies'))
				add_filter('currencies', array($this, 'IRAN_Currencies_By_NextpayStd'));
		}

		public function IRAN_Currencies_By_NextpayStd( $currencies ) {
			unset($currencies['RIAL']);
			$currencies['تومان'] = __('تومان', 'nextpay');
			$currencies['ریال'] = __('ریال', 'nextpay');
			return $currencies;
		}
				
		public function NextPay_Register_By_NextpayStd($gateways) {
			global $options;
			
			if( version_compare( PLUGIN_VERSION, '2.1.0', '<' ) ) {
				$gateways['NextPay'] = isset($options['nextpay_name']) ? $options['nextpay_name'] : __( 'نکست پی', 'nextpay');
			}
			else {
				$gateways['NextPay'] = array(
					'label' => isset($options['nextpay_name']) ? $options['nextpay_name'] : __( 'نکست پی', 'nextpay'),
					'admin_label' => isset($options['nextpay_name']) ? $options['nextpay_name'] : __( 'نکست پی', 'nextpay'),
				);
			}
			
			return $gateways;
		}

		public function NextPay_Setting_By_NextpayStd($options) {
		?>	
			<hr/>
			<table class="form-table">
				<?php do_action( 'NextPay_before_settings', $options ); ?>
				<tr valign="top">
					<th colspan=2><h3><?php _e( 'تنظیمات نکست پی', 'nextpay' ); ?></h3></th>
				</tr>				
				<tr valign="top">
					<th>
						<label for="settings[nextpay_api_key]"><?php _e( 'کلید مجوزدهی نکست پی', 'nextpay' ); ?></label>
					</th>
					<td>
						<input class="regular-text" id="settings[nextpay_api_key]" style="width: 300px;" name="settings[nextpay_api_key]" value="<?php if( isset( $options['nextpay_api_key'] ) ) { echo $options['nextpay_api_key']; } ?>"/>
					</td>
				</tr>				
				<tr valign="top">
					<th>
						<label for="settings[nextpay_query_name]"><?php _e( 'نام لاتین درگاه', 'nextpay' ); ?></label>
					</th>
					<td>
						<input class="regular-text" id="settings[nextpay_query_name]" style="width: 300px;" name="settings[nextpay_query_name]" value="<?php echo isset($options['nextpay_query_name']) ? $options['nextpay_query_name'] : 'NextPay'; ?>"/>
						<div class="description"><?php _e( 'این نام در هنگام بازگشت از بانک در آدرس بازگشت از بانک نمایان خواهد شد . از به کاربردن حروف زائد و فاصله جدا خودداری نمایید . این نام باید با نام سایر درگاه ها متفاوت باشد .', 'nextpay' ); ?></div>
					</td>
				</tr>
				<tr valign="top">
					<th>
						<label for="settings[nextpay_name]"><?php _e( 'نام نمایشی درگاه', 'nextpay' ); ?></label>
					</th>
					<td>
						<input class="regular-text" id="settings[nextpay_name]" style="width: 300px;" name="settings[nextpay_name]" value="<?php echo isset($options['nextpay_name']) ? $options['nextpay_name'] : __( 'نکست پی', 'nextpay'); ?>"/>
					</td>
				</tr>
				<tr valign="top">
					<th>
						<label><?php _e( 'تذکر ', 'nextpay' ); ?></label>
					</th>
					<td>
						<div class="description"><?php _e( 'از سربرگ مربوط به ثبت نام در تنظیمات افزونه حتما یک برگه برای بازگشت از بانک انتخاب نمایید . ترجیحا نام برگه را لاتین قرار دهید .<br/> نیازی به قرار دادن شورت کد خاصی در برگه نیست و میتواند برگه ی خالی باشد .', 'nextpay' ); ?></div>
					</td>
				</tr>
				<?php do_action( 'NextPay_after_settings', $options ); ?>
			</table>
			<?php
		}
		
		public function NextPay_Request_By_NextpayStd($subscription_data) {
			
			$new_subscription_id = get_user_meta( $subscription_data['user_id'], 'subscription_level' , true );
			if ( !empty( $new_subscription_id )) {
				update_user_meta( $subscription_data['user_id'], 'subscription_level_new', $new_subscription_id );
			}
			
			$old_subscription_id = get_user_meta( $subscription_data['user_id'], 'subscription_level_old' , true );
			update_user_meta( $subscription_data['user_id'], 'subscription_level', $old_subscription_id );
			
			global $options;
			ob_start();
			$query = isset($options['nextpay_query_name']) ? $options['nextpay_query_name'] : 'NextPay';
			$amount = str_replace( ',', '', $subscription_data['price']); 

			$nextpay_payment_data = array(
				'user_id'             => $subscription_data['user_id'],
				'subscription_name'     => $subscription_data['subscription_name'],
				'subscription_key'	 => $subscription_data['key'],
				'amount'           => $amount
			);			
			
			$NextpayStd_session = Nextpay_Session::get_instance();
			@session_start();
			$NextpayStd_session['nextpay_payment_data'] = $nextpay_payment_data;
			$_SESSION["nextpay_payment_data"] = $nextpay_payment_data;	
			
			//Action For NextPay or RCP Developers...
			do_action( 'Before_Sending_to_NextPay', $subscription_data );	
		
			if ($options['currency'] == 'ریال' || $options['currency'] == 'RIAL' || $options['currency'] == 'ریال ایران' || $options['currency'] == 'Iranian Rial (&#65020;)')
				$amount = $amount/10;
			
			//Start of NextPay
			$api_key = isset($options['nextpay_api_key']) ? $options['nextpay_api_key'] : '';
			$amount = intval($amount);
			$callback_uri =  add_query_arg('gateway', $query, $subscription_data['return_url']);
			$order_id = time();
			
			include_once("nextpay_payment.php");
			
			$parameters = array(
			    'api_key' 	=> $api_key,
			    'amount' 		=> $amount,
			    'callback_uri' 	=> $callback_uri,
			    'order_id' 		=> $order_id
			);

			try {
			    $nextpay = new Nextpay_Payment($parameters);
			    $nextpay->setDefaultVerify(Type_Verify::SoapClient);
			    $result = $nextpay->token();
			    if(intval($result->code) == -1){
				$nextpay->send($result->trans_id);
			    }
			    else
			    {
				wp_die( sprintf(__('متاسفانه پرداخت به دلیل خطای زیر امکان پذیر نمی باشد . <br/><b> %s </b>', 'nextpay'), $this->Fault($result->code)) );
				exit();
			    }
			}catch (Exception $e) { echo 'Error'. $e->getMessage();  }
			exit;
		}
		
		public function NextPay_Verify_By_NextpayStd() {
			
			if (!isset($_GET['gateway']))
				return;
			
			if ( !class_exists('Payments') )
				return;
			
			$trans_id = isset($_POST['trans_id']) ? $_POST['trans_id'] : False;
			$order_id = isset($_POST['order_id']) ? $_POST['order_id'] : False;
			
			if ( !$trans_id || !$order_id)
				return;
			
			include_once("nextpay_payment.php");
			$nextpay = new Nextpay_Payment();
			
			if (!is_string($trans_id) || (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $trans_id) !== 1)) {
			    $message = ' شماره خطا: -34 <br />';
			    $message .='<br>'.$nextpay->code_error(intval(-34));
			    echo $message;
			    exit();
			}
			
			global $options, $wpdb, $payments_db_name;
			@session_start();
			$NextpayStd_session = Nextpay_Session::get_instance();
			if (isset($NextpayStd_session['nextpay_payment_data']))
				$nextpay_payment_data = $NextpayStd_session['nextpay_payment_data'];
			else 
				$nextpay_payment_data = isset($_SESSION["nextpay_payment_data"]) ? $_SESSION["nextpay_payment_data"] : '';
			
			$query = isset($options['nextpay_query_name']) ? $options['nextpay_query_name'] : 'NextPay';
						
			if 	( ($_GET['gateway'] == $query) && $nextpay_payment_data )
			{
				
				$user_id 			= $nextpay_payment_data['user_id'];
				$user_id			= intval($user_id);
				$subscription_name 	= $nextpay_payment_data['subscription_name'];
				$subscription_key 	= $nextpay_payment_data['subscription_key'];
				$amount 			= $nextpay_payment_data['amount'];
				
				/*
				$subscription_price = intval(number_format( (float) get_subscription_price( get_subscription_id( $user_id ) ), 2)) ;
				*/
				
				
				$payment_method =  isset($options['nextpay_name']) ? $options['nextpay_name'] : __( 'نکست پی', 'nextpay');
				

				
				$new_payment = 1;
				if( $wpdb->get_results( $wpdb->prepare("SELECT id FROM " . $payments_db_name . " WHERE `subscription_key`='%s' AND `payment_type`='%s';", $subscription_key, $payment_method ) ) )
					$new_payment = 0;

				unset($GLOBALS['nextpay_new']);
				$GLOBALS['nextpay_new'] = $new_payment;
				global $new;
				$new = $new_payment;
				
				if ($new_payment == 1) {
				
					//Start of NextPay
					$api_key = isset($options['nextpay_api_key']) ? $options['nextpay_api_key'] : '';
					$amount = intval($amount);
					if ($options['currency'] == 'ریال' || $options['currency'] == 'RIAL' || $options['currency'] == 'ریال ایران' || $options['currency'] == 'Iranian Rial (&#65020;)')
						$amount = $amount/10;
					
					
					$parameters = array
					(
					    'api_key'	=> $api_key,
					    'order_id'	=> $order_id,
					    'trans_id' 	=> $trans_id,
					    'amount'	=> $amount,
					);
					try {
					    $result = $nextpay->verify_request($parameters);
					    if( $result < 0 ) {
						$payment_status = 'failed';
						$fault = $result;
						$transaction_id = 0;
						/*$message ='<br>پرداخت موفق نبوده است';
						$message .='<br>شماره تراکنش : <span>' . $trans_id .'</span><br>';
						$message = ' شماره خطا: ' . $result . ' <br />';
						$message .='<br>'.$nextpay->code_error(intval($result));
						echo $message;
						exit();*/
					    } elseif ($result==0) {
						$payment_status = 'completed';
						$fault = 0;
						$transaction_id = $trans_id;
						/*$message ='<br>پرداخت موفق است';
						$message .='<br>شماره تراکنش : <span>' . $trans_id .'</span><br>';
						$message .='<br>شماره پیگیری : <span>' . $order_id .'</span><br>';
						$message .='<br>مبلغ : ' . $amount .'<br>';
						echo $message;
						exit();*/
					    }else{
						$payment_status = 'cancelled';
						$fault = 0;
						$transaction_id = 0;
						/*$message ='<br>پرداخت موفق نبوده است';
						$message .='<br>شماره تراکنش : ' . $trans_id .'<br>';
						echo $message;
						exit();*/
					    }
					}catch (Exception $e) { echo 'Error'. $e->getMessage();  }				
				
					unset($GLOBALS['nextpay_payment_status']);
					unset($GLOBALS['nextpay_transaction_id']);
					unset($GLOBALS['nextpay_fault']);
					unset($GLOBALS['nextpay_subscription_key']);
					$GLOBALS['nextpay_payment_status'] = $payment_status;
					$GLOBALS['nextpay_transaction_id'] = $transaction_id;
					$GLOBALS['nextpay_subscription_key'] = $subscription_key;
					$GLOBALS['nextpay_fault'] = $fault;
					global $nextpay_transaction;
					$nextpay_transaction = array();
					$nextpay_transaction['nextpay_payment_status'] = $payment_status;
					$nextpay_transaction['nextpay_transaction_id'] = $transaction_id;
					$nextpay_transaction['nextpay_subscription_key'] = $subscription_key;
					$nextpay_transaction['nextpay_fault'] = $fault;
				
		
					if ($payment_status == 'completed') 
					{
				
						$payment_data = array(
							'date'             => date('Y-m-d g:i:s'),
							'subscription'     => $subscription_name,
							'payment_type'     => $payment_method,
							'subscription_key' => $subscription_key,
							'amount'           => $amount,
							'user_id'          => $user_id,
							'transaction_id'   => $transaction_id
						);
					
						//Action For NextPay or RCP Developers...
						do_action( 'NextPay_Insert_Payment', $payment_data, $user_id );
					
						$payments = new Payments();
						$payments->insert( $payment_data );
					
					
						$new_subscription_id = get_user_meta( $user_id, 'subscription_level_new' , true );
						if ( !empty( $new_subscription_id )) {
							update_user_meta( $user_id, 'subscription_level', $new_subscription_id );
						}
						set_status( $user_id, 'active' );
					
						
						if( version_compare( PLUGIN_VERSION, '1.0.0', '<' ) ) {
							email_subscription_status( $user_id, 'active' );
							if( ! isset( $options['disable_new_user_notices'] ) )
								wp_new_user_notification( $user_id );
						}
					
						update_user_meta( $user_id, 'payment_profile_id', $user_id );
					
						update_user_meta( $user_id, 'signup_method', 'live' );
						//recurring is just for paypal or ipn gateway
						update_user_meta( $user_id, 'recurring', 'no' ); 
					
						$subscription = get_subscription_details( get_subscription_id( $user_id ) );
						$member_new_expiration = date( 'Y-m-d H:i:s', strtotime( '+' . $subscription->duration . ' ' . $subscription->duration_unit . ' 23:59:59' ) );
						set_expiration_date( $user_id, $member_new_expiration );	
						delete_user_meta( $user_id, '_expired_email_sent' );
									
						$log_data = array(
							'post_title'    => __( 'تایید پرداخت', 'nextpay' ),
							'post_content'  =>  __( 'پرداخت با موفقیت انجام شد . کد تراکنش : ', 'nextpay' ).$transaction_id.__( ' .  روش پرداخت : ', 'nextpay' ).$payment_method,
							'post_parent'   => 0,
							'log_type'      => 'gateway_error'
						);

						$log_meta = array(
							'user_subscription' => $subscription_name,
							'user_id'           => $user_id
						);
						
						$log_entry = WP_Logging::insert_log( $log_data, $log_meta );
				

						//Action For NextPay or RCP Developers...
						do_action( 'NextPay_Completed', $user_id );				
					}	
					
					
					if ($payment_status == 'cancelled')
					{
					
						$log_data = array(
							'post_title'    => __( 'انصراف از پرداخت', 'nextpay' ),
							'post_content'  =>  __( 'تراکنش به دلیل انصراف کاربر از پرداخت ، ناتمام باقی ماند .', 'nextpay' ).__( ' روش پرداخت : ', 'nextpay' ).$payment_method,
							'post_parent'   => 0,
							'log_type'      => 'gateway_error'
						);

						$log_meta = array(
							'user_subscription' => $subscription_name,
							'user_id'           => $user_id
						);
						
						$log_entry = WP_Logging::insert_log( $log_data, $log_meta );
					
						//Action For NextPay or RCP Developers...
						do_action( 'NextPay_Cancelled', $user_id );	

					}	
					
					if ($payment_status == 'failed') 
					{
									
						$log_data = array(
							'post_title'    => __( 'خطا در پرداخت', 'nextpay' ),
							'post_content'  =>  __( 'تراکنش به دلیل خطای رو به رو ناموفق باقی ماند :', 'nextpay' ).$this->Fault($fault).__( ' روش پرداخت : ', 'nextpay' ).$payment_method,
							'post_parent'   => 0,
							'log_type'      => 'gateway_error'
						);

						$log_meta = array(
							'user_subscription' => $subscription_name,
							'user_id'           => $user_id
						);
						
						$log_entry = WP_Logging::insert_log( $log_data, $log_meta );
					
						//Action For NextPay or RCP Developers...
						do_action( 'NextPay_Failed', $user_id );	
					
					}
			
				}
				add_filter( 'the_content', array($this,  'NextPay_Content_After_Return_By_NextpayStd') );
				//session_destroy();	
			}
		}
		 
		
		public function NextPay_Content_After_Return_By_NextpayStd( $content ) { 
			
			global $nextpay_transaction, $new;
			
			$NextpayStd_session = Nextpay_Session::get_instance();
			@session_start();
			
			$new_payment = isset($GLOBALS['nextpay_new']) ? $GLOBALS['nextpay_new'] : $new;
			
			$payment_status = isset($GLOBALS['nextpay_payment_status']) ? $GLOBALS['nextpay_payment_status'] : $nextpay_transaction['nextpay_payment_status'];
			$transaction_id = isset($GLOBALS['nextpay_transaction_id']) ? $GLOBALS['nextpay_transaction_id'] : $nextpay_transaction['nextpay_transaction_id'];
			$fault = isset($GLOBALS['nextpay_fault']) ? $this->Fault($GLOBALS['nextpay_fault']) : $this->Fault($nextpay_transaction['nextpay_fault']);
			
			if ($new_payment == 1) 
			{
			
				$nextpay_data = array(
					'payment_status'             => $payment_status,
					'transaction_id'     => $transaction_id,
					'fault'     => $fault
				);
				
				$NextpayStd_session['nextpay_data'] = $nextpay_data;
				$_SESSION["nextpay_data"] = $nextpay_data;	
			
			}
			else
			{
				if (isset($NextpayStd_session['nextpay_data']))
					$nextpay_payment_data = $NextpayStd_session['nextpay_data'];
				else 
					$nextpay_payment_data = isset($_SESSION["nextpay_data"]) ? $_SESSION["nextpay_data"] : '';
			
				$payment_status = isset($nextpay_payment_data['payment_status']) ? $nextpay_payment_data['payment_status'] : '';
				$transaction_id = isset($nextpay_payment_data['transaction_id']) ? $nextpay_payment_data['transaction_id'] : '';
				$fault = isset($nextpay_payment_data['fault']) ? $this->Fault($nextpay_payment_data['fault']) : '';
			}
			
			$message = '';
			
			if ($payment_status == 'completed') {
				$message = '<br/>'.__( 'پرداخت با موفقیت انجام شد . کد تراکنش : ', 'nextpay' ).$transaction_id.'<br/>';
			}
			
			if ($payment_status == 'cancelled') {
				$message = '<br/>'.__( 'تراکنش به دلیل انصراف شما نا تمام باقی ماند .', 'nextpay' );
			}
			
			if ($payment_status == 'failed') {
				$message = '<br/>'.__( 'تراکنش به دلیل خطای زیر ناموفق باقی باند :', 'nextpay' ).'<br/>'.$fault.'<br/>';
			}
			
			return $content.$message;
		}
		
		private static function Fault($error) {
		
		
			include_once("nextpay_payment.php");
			$nextpay = new Nextpay_Payment();
			
			$response	=  __( $nextpay->code_error($error), 'nextpay' );
			
			return $response;
		}
		
	}
}
new NextPay();
if ( !function_exists('change_cancelled_to_pending_By_NextpayStd')) {	
	add_action( 'set_status', 'change_cancelled_to_pending_By_NextpayStd', 10, 2 );
	function change_cancelled_to_pending_By_NextpayStd( $status, $user_id ) {
		if( 'cancelled' == $status )
			set_status( $user_id, 'expired' );
			return true;
	}
}

if ( !function_exists('User_Registration_Data_By_NextpayStd') && !function_exists('User_Registration_Data') ) {
	add_filter('user_registration_data', 'User_Registration_Data_By_NextpayStd' );	
	function User_Registration_Data_By_NextpayStd( $user ) {
		$old_subscription_id = get_user_meta( $user['id'], 'subscription_level' , true );
		if ( !empty( $old_subscription_id )) {
			update_user_meta( $user['id'], 'subscription_level_old', $old_subscription_id );
		}
					
		$user_info = get_userdata($user['id']);
		$old_user_role = implode(', ', $user_info->roles);
		if ( !empty( $old_user_role )) {
			update_user_meta( $user['id'], 'user_role_old', $old_user_role );
		}
	
		return $user;
	}
}
?>