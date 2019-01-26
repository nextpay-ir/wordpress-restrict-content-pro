<?php

function RCP_NextPay_AdminMenu()
{
        add_menu_page("RCP نکست پی", "RCP  نکست پی", 'manage_options', 'rcpnextpay', 'RCP_NextPay_Dashboard', plugins_url("icon.ico", __FILE__));
}

add_action('admin_menu', 'RCP_NextPay_AdminMenu');

function RCP_NextPay_GET_CURL($addres)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $addres);
	curl_setopt($ch, CURLOPT_POST, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$nnn = curl_exec($ch);
	return $nnn;
}

function RCP_nextpay_Dashboard()
{
//   global $wpdb;
  $contentpage= RCP_NextPay_GET_CURL('https://.nextpay.ir');
  echo $contentpage;
}


if (!defined('ABSPATH')) exit;
/****************************************************************************************/
/****************************************************************************************/
if( !defined( 'NextPay_SESSION_COOKIE' ) )
	define( 'NextPay_SESSION_COOKIE', '_NEXTPAY_session' );
if ( !class_exists( 'Recursive_ArrayAccess' ) ) {
	class Recursive_ArrayAccess implements ArrayAccess {
		protected $container = array();
		protected $dirty = false;
		protected function __construct( $data = array() ) {
			foreach ( $data as $key => $value ) {
				$this[ $key ] = $value;
			}
		}
		public function __clone() {
			foreach ( $this->container as $key => $value ) {
				if ( $value instanceof self ) {
					$this[ $key ] = clone $value;
				}
			}
		}
		public function toArray() {
			$data = $this->container;
			foreach ( $data as $key => $value ) {
				if ( $value instanceof self ) {
					$data[ $key ] = $value->toArray();
				}
			}
			return $data;
		}
		public function offsetExists( $offset ) {
			return isset( $this->container[ $offset ]) ;
		}
		public function offsetGet( $offset ) {
			return isset( $this->container[ $offset ] ) ? $this->container[ $offset ] : null;
		}
		public function offsetSet( $offset, $data ) {
			if ( is_array( $data ) ) {
				$data = new self( $data );
			}
			if ( $offset === null ) { 
				$this->container[] = $data;
			}
			else {
				$this->container[ $offset ] = $data;
			}
			$this->dirty = true;
		}
		public function offsetUnset( $offset ) {
			unset( $this->container[ $offset ] );
			$this->dirty = true;
		}
	}
}
if ( !class_exists( 'NextPay_Session' ) ) {
	final class NextPay_Session extends Recursive_ArrayAccess implements Iterator, Countable {
		protected $session_id;
		protected $expires;
		protected $exp_variant;
		private static $instance = false;
		public static function get_instance() {
			if ( !self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}
		protected function __construct() {
			if ( isset( $_COOKIE[NextPay_SESSION_COOKIE] ) ) {
				$cookie = stripslashes( $_COOKIE[NextPay_SESSION_COOKIE] );
				$cookie_crumbs = explode( '||', $cookie );
				$this->session_id = $cookie_crumbs[0];
				$this->expires = $cookie_crumbs[1];
				$this->exp_variant = $cookie_crumbs[2];
				if ( time() > $this->exp_variant ) {
					$this->set_expiration();
					delete_option( "_NEXTPAY_session_expires_{$this->session_id}" );
					add_option( "_NEXTPAY_session_expires_{$this->session_id}", $this->expires, '', 'no' );
				}
			} 
			else {
				$this->session_id = $this->generate_id();
				$this->set_expiration();
			}
			$this->read_data();
			$this->set_cookie();
		}
		protected function set_expiration() {
			$this->exp_variant = time() + (int) apply_filters( 'NEXTPAY_session_expiration_variant', 24 * 60 );
			$this->expires = time() + (int) apply_filters( 'NEXTPAY_session_expiration', 30 * 60 );
		}
		protected function set_cookie(){
			if( !headers_sent() )
				setcookie(NextPay_SESSION_COOKIE,$this->session_id.'||'.$this->expires.'||'.$this->exp_variant,$this->expires,COOKIEPATH,COOKIE_DOMAIN );
		}
		protected function generate_id() {
			require_once( ABSPATH . 'wp-includes/class-phpass.php');
			$hasher = new PasswordHash( 8, false );
			return md5( $hasher->get_random_bytes( 32 ) );
		}
		protected function read_data() {
			$this->container = get_option( "_NEXTPAY_session_{$this->session_id}", array() );
			return $this->container;
		}
		public function write_data() {
			$option_key = "_NEXTPAY_session_{$this->session_id}";
			if ( $this->dirty ) {
				if ( false === get_option( $option_key ) ) {
					add_option( "_NEXTPAY_session_{$this->session_id}", $this->container, '', 'no' );
					add_option( "_NEXTPAY_session_expires_{$this->session_id}", $this->expires, '', 'no' );
				} 
				else {
					delete_option( "_NEXTPAY_session_{$this->session_id}" );
					add_option( "_NEXTPAY_session_{$this->session_id}", $this->container, '', 'no' );
				}
			}
		}
		public function json_out() {
			return json_encode( $this->container );
		}
		public function json_in( $data ) {
			$array = json_decode( $data );
			if ( is_array( $array ) ) {
				$this->container = $array;
				return true;
			}
			return false;
		}
		public function regenerate_id( $delete_old = false ) {
			if ( $delete_old ) {
				delete_option( "_NEXTPAY_session_{$this->session_id}" );
			}
			$this->session_id = $this->generate_id();
			$this->set_cookie();
		}
		public function session_started() {
			return !!self::$instance;
		}
		public function cache_expiration() {
			return $this->expires;
		}
		public function reset() {
			$this->container = array();
		}
		public function current() {
			return current( $this->container );
		}
		public function key() {
			return key( $this->container );
		}
		public function next() {
			next( $this->container );
		}
		public function rewind() {
			reset( $this->container );
		}
		public function valid() {
			return $this->offsetExists( $this->key() );
		}
		public function count() {
			return count( $this->container );
		}
	}
	function NEXTPAY_session_cache_expire() {
		$NEXTPAY_session = NextPay_Session::get_instance();
		return $NEXTPAY_session->cache_expiration();
	}
	function NEXTPAY_session_commit() {
		NEXTPAY_session_write_close();
	}
	function NEXTPAY_session_decode( $data ) {
		$NEXTPAY_session = NextPay_Session::get_instance();
		return $NEXTPAY_session->json_in( $data );
	}
	function NEXTPAY_session_encode() {
		$NEXTPAY_session = NextPay_Session::get_instance();
		return $NEXTPAY_session->json_out();
	}
	function NEXTPAY_session_regenerate_id( $delete_old_session = false ) {
		$NEXTPAY_session = NextPay_Session::get_instance();
		$NEXTPAY_session->regenerate_id( $delete_old_session );
		return true;
	}
	function NEXTPAY_session_start() {
		$NEXTPAY_session = NextPay_Session::get_instance();
		do_action( 'NEXTPAY_session_start' );
		return $NEXTPAY_session->session_started();
	}
	add_action( 'plugins_loaded', 'NEXTPAY_session_start' );
	function NEXTPAY_session_status() {
		$NEXTPAY_session = NextPay_Session::get_instance();
		if ( $NEXTPAY_session->session_started() ) {
			return PHP_SESSION_ACTIVE;
		}
		return PHP_SESSION_NONE;
	}
	function NEXTPAY_session_unset() {
		$NEXTPAY_session = NextPay_Session::get_instance();
		$NEXTPAY_session->reset();
	}
	function NEXTPAY_session_write_close() {
		$NEXTPAY_session = NextPay_Session::get_instance();
		$NEXTPAY_session->write_data();
		do_action( 'NEXTPAY_session_commit' );
	}
	add_action( 'shutdown', 'NEXTPAY_session_write_close' );
	function NEXTPAY_session_cleanup() {
		global $wpdb;
		if ( defined( 'NextPay_SETUP_CONFIG' ) ) {
			return;
		}
		if ( ! defined( 'NextPay_INSTALLING' ) ) {
			$expiration_keys = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE '_NEXTPAY_session_expires_%'" );
			$now = time();
			$expired_sessions = array();
			foreach( $expiration_keys as $expiration ) {
				if ( $now > intval( $expiration->option_value ) ) {
					$session_id = substr( $expiration->option_name, 20 );
					$expired_sessions[] = $expiration->option_name;
					$expired_sessions[] = "_NEXTPAY_session_$session_id";
				}
			}
			if ( ! empty( $expired_sessions ) ) {
				$option_names = implode( "','", $expired_sessions );
				$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name IN ('$option_names')" );
			}
		}
		do_action( 'NEXTPAY_session_cleanup' );
	}
	add_action( 'NEXTPAY_session_garbage_collection', 'NEXTPAY_session_cleanup' );
	function NEXTPAY_session_register_garbage_collection() {
		if ( !wp_next_scheduled( 'NEXTPAY_session_garbage_collection' ) ) {
			wp_schedule_event( time(), 'hourly', 'NEXTPAY_session_garbage_collection' );
		}
	}
	add_action( 'wp', 'NEXTPAY_session_register_garbage_collection' );
}


/********************************************************************************************/
/********************************************************************************************/


if (!class_exists('RCP_nextpay') ) {
	class RCP_nextpay {
	
		public function __construct() {
			add_action('init', array($this, 'nextpay_Verify'));
			add_action('rcp_payments_settings', array($this, 'nextpay_Setting'));
			add_action('rcp_gateway_nextpay', array($this, 'nextpay_Request'));
			add_filter('rcp_payment_gateways', array($this, 'nextpay_Register'));
			if ( !function_exists('RCP_IRAN_Currencies_By_NEXTPAY') && !function_exists('RCP_IRAN_Currencies') )
				add_filter('rcp_currencies', array($this, 'RCP_IRAN_Currencies'));
		}

		public function RCP_IRAN_Currencies( $currencies ) {
			unset($currencies['RIAL']);
			$currencies['ریال'] = __('ریال', 'rcp_nextpay');
			return $currencies;
		}
				
		public function nextpay_Register($gateways) {
			global $rcp_options;
			
			if( version_compare( RCP_PLUGIN_VERSION, '2.1.0', '<' ) ) {
				$gateways['nextpay'] = isset($rcp_options['nextpay_name']) ? $rcp_options['nextpay_name'] : __( 'نکست پی', 'rcp_nextpay');
			}
			else {
				$gateways['nextpay'] = array(
					'label' => isset($rcp_options['nextpay_name']) ? $rcp_options['nextpay_name'] : __( 'نکست پی', 'rcp_nextpay'),
					'admin_label' => isset($rcp_options['nextpay_name']) ? $rcp_options['nextpay_name'] : __( 'نکست پی', 'rcp_nextpay'),
				);
			}
			return $gateways;
		}

		public function nextpay_Setting($rcp_options) {
		echo '	
			<hr/>
			<table class="form-table">'; ?>
				<?php do_action( 'RCP_nextpay_before_settings', $rcp_options ); ?>
				<?php echo '<tr valign="top">
					<th colspan=2><h3>'; ?> <?php _e( 'تنظیمات نکست پی', 'rcp_nextpay' ); ?><?php echo '</h3></th>
				</tr>				
				<tr valign="top">
					<th>
						<label for="rcp_settings[api_key]">'; ?><?php _e( 'کلید مجوزدهی نکست پی', 'rcp_nextpay' ); ?><?php echo '</label>
					</th>
					<td>
						<input class="regular-text" id="rcp_settings[api_key]" style="width: 300px;" name="rcp_settings[api_key]" value="'; ?><?php if( isset( $rcp_options['api_key'] ) ) { echo $rcp_options['api_key']; } ?><?php echo '"/>
					</td>
				</tr>					
				<tr valign="top">
					<th>
						<label for="rcp_settings[nextpay_query_name]">'; ?><?php _e( 'نام لاتین درگاه', 'rcp_nextpay' ); ?><?php echo '</label>
					</th>
					<td>
						<input class="regular-text" id="rcp_settings[nextpay_query_name]" style="width: 300px;" name="rcp_settings[nextpay_query_name]" value="'; ?><?php echo isset($rcp_options['nextpay_query_name']) ? $rcp_options['nextpay_query_name'] : 'nextpay'; ?><?php echo '"/>
						<div class="description">'; ?><?php _e( 'این نام در هنگام بازگشت از بانک در آدرس بازگشت از بانک نمایان خواهد شد . از به کاربردن حروف زائد و فاصله جدا خودداری نمایید .', 'rcp_nextpay' ); ?><?php echo '</div>
					</td>
				</tr>
				<tr valign="top">
					<th>
						<label for="rcp_settings[nextpay_name]">'; ?><?php _e( 'نام نمایشی درگاه', 'rcp_nextpay' ); ?><?php echo '</label>
					</th>
					<td>
						<input class="regular-text" id="rcp_settings[nextpay_name]" style="width: 300px;" name="rcp_settings[nextpay_name]" value="'; ?><?php echo isset($rcp_options['nextpay_name']) ? $rcp_options['nextpay_name'] : __( 'نکست پی', 'rcp_nextpay'); ?><?php echo '"/>
					</td>
				</tr>
				<tr valign="top">
					<th>
						<label>'; ?><?php _e( 'تذکر ', 'rcp_nextpay' ); ?><?php echo '</label>
					</th>
					<td>
						<div class="description">'; ?><?php _e( 'از سربرگ مربوط به ثبت نام در تنظیمات افزونه حتما یک برگه برای بازگشت از بانک انتخاب نمایید . ترجیحا نام برگه را لاتین قرار دهید .<br/> نیازی به قرار دادن شورت کد خاصی در برگه نیست و میتواند برگه ی خالی باشد .', 'rcp_nextpay' ); ?><?php echo '</div>
					</td>
				</tr>'; ?>
				<?php do_action( 'RCP_nextpay_after_settings', $rcp_options ); ?>
			<?php echo '</table>';
		}
		
		public function nextpay_Request($subscription_data) {
			
			global $rcp_options;
			ob_start();
			$query = isset($rcp_options['nextpay_query_name']) ? $rcp_options['nextpay_query_name'] : 'nextpay';
			$amount = str_replace( ',', '', $subscription_data['price']);

			$nextpay_payment_data = array(
				'user_id'             => $subscription_data['user_id'],
				'subscription_name'     => $subscription_data['subscription_name'],
				'subscription_key'	 => $subscription_data['key'],
				'amount'           => $amount
			);			
			
			$NEXTPAY_session = NextPay_Session::get_instance();
			@session_start();
			$NEXTPAY_session['nextpay_payment_data'] = $nextpay_payment_data;
			$_SESSION["nextpay_payment_data"] = $nextpay_payment_data;	
			
			//Action For nextpay or RCP Developers...
			do_action( 'RCP_Before_Sending_to_nextpay', $subscription_data );	
		
			if ($rcp_options['currency'] == 'ریال' || $rcp_options['currency'] == 'RIAL' || $rcp_options['currency'] == 'ریال ایران' || $rcp_options['currency'] == 'Iranian Rial (&#65020;)')
				$amount = $amount/10;
			
			//Start of nextpay
			$api_key = isset($rcp_options['api_key']) ? $rcp_options['api_key'] : '';
			$Price = intval($amount);
			$callback_uri =  add_query_arg('gateway', $query, $subscription_data['return_url']);
			$ResNumber = $subscription_data['key'];
			$Paymenter = $subscription_data['user_name'];
			$Email = $subscription_data['user_email'];
			$Description = sprintf(__('خرید اشتراک %s برای کاربر %s', 'rcp_nextpay'), $subscription_data['subscription_name'],$subscription_data['user_name']);
			$Mobile = '-';			
			//Filter For nextpay or RCP Developers...
			$Description = apply_filters( 'RCP_nextpay_Description', $Description, $subscription_data );
			$Mobile = apply_filters( 'RCP_Mobile', $Mobile, $subscription_data );
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
				wp_die( sprintf(__('متاسفانه پرداخت به دلیل خطای زیر امکان پذیر نمی باشد . <br/><b> %s </b>', 'nextpay'), $this->Fault_Get($result->code)) );
				exit();
			    }
			}catch (Exception $e) { echo 'Error'. $e->getMessage();  }
			exit;
		}
		
		public function nextpay_Verify() {
		
			global $rcp_options;
			
			if (!isset($_GET['gateway']))
				return;
			
			if ( !class_exists('RCP_Payments') )
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
			
			
			global $rcp_options, $wpdb, $rcp_payments_db_name;
			@session_start();
			$NEXTPAY_session = NextPay_Session::get_instance();
			if (isset($NEXTPAY_session['nextpay_payment_data']))
				$nextpay_payment_data = $NEXTPAY_session['nextpay_payment_data'];
			else 
				$nextpay_payment_data = isset($_SESSION["nextpay_payment_data"]) ? $_SESSION["nextpay_payment_data"] : '';
			
			$query = isset($rcp_options['nextpay_query_name']) ? $rcp_options['nextpay_query_name'] : 'nextpay';
			
			if ($trans_id && $nextpay_payment_data)
			{
				$user_id 			= $nextpay_payment_data['user_id'];
				$subscription_name 	= $nextpay_payment_data['subscription_name'];
				$subscription_key 	= $nextpay_payment_data['subscription_key'];
				$amount 			= $nextpay_payment_data['amount'];
				$subscription_id    = rcp_get_subscription_id( $user_id );
				$user_data          = get_userdata( $user_id );
				$payment_method =  isset($rcp_options['nextpay_name']) ? $rcp_options['nextpay_name'] : __( 'نکست پی', 'rcp_nextpay');
				if( ! $user_data || ! $subscription_id || ! rcp_get_subscription_details( $subscription_id ) )
					return;
				$new_payment = 1;
				if( $wpdb->get_results( $wpdb->prepare("SELECT id FROM " . $rcp_payments_db_name . " WHERE `subscription_key`='%s' AND `payment_type`='%s';", $subscription_key, $payment_method ) ) )
					$new_payment = 0;

				unset($GLOBALS['nextpay_new']);
				$GLOBALS['nextpay_new'] = $new_payment;
				global $new;
				$new = $new_payment;

				if ($new_payment == 1) {
					
					//Start of NextPay
					$api_key = isset($rcp_options['api_key']) ? $rcp_options['api_key'] : '';
					$amount = intval($amount);
					if ($rcp_options['currency'] == 'ریال' || $rcp_options['currency'] == 'RIAL' || $rcp_options['currency'] == 'ریال ایران' || $rcp_options['currency'] == 'Iranian Rial (&#65020;)')
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
					
						//Action For nextpay or RCP Developers...
						do_action( 'RCP_nextpay_Insert_Payment', $payment_data, $user_id );
					
						$rcp_payments = new RCP_Payments();
						$rcp_payments->insert( $payment_data );
					
					
						rcp_set_status( $user_id, 'active' );
						
						if( version_compare( RCP_PLUGIN_VERSION, '2.1.0', '<' ) ) {
							rcp_email_subscription_status( $user_id, 'active' );
							if( ! isset( $rcp_options['disable_new_user_notices'] ) )
								wp_new_user_notification( $user_id );
						}
						
						
						update_user_meta( $user_id, 'rcp_payment_profile_id', $user_id );
						
						update_user_meta( $user_id, 'rcp_signup_method', 'live' );
						//rcp_recurring is just for paypal or ipn gateway
						update_user_meta( $user_id, 'rcp_recurring', 'no' ); 
					
						$subscription = rcp_get_subscription_details( rcp_get_subscription_id( $user_id ) );
						$member_new_expiration = date( 'Y-m-d H:i:s', strtotime( '+' . $subscription->duration . ' ' . $subscription->duration_unit . ' 23:59:59' ) );
						rcp_set_expiration_date( $user_id, $member_new_expiration );	
						delete_user_meta( $user_id, '_rcp_expired_email_sent' );
									
						$log_data = array(
							'post_title'    => __( 'تایید پرداخت', 'rcp_nextpay' ),
							'post_content'  =>  __( 'پرداخت با موفقیت انجام شد . کد تراکنش : ', 'rcp_nextpay' ).$transaction_id.__( ' .  روش پرداخت : ', 'rcp_nextpay' ).$payment_method,
							'post_parent'   => 0,
							'log_type'      => 'gateway_error'
						);

						$log_meta = array(
							'user_subscription' => $subscription_name,
							'user_id'           => $user_id
						);
						
						$log_entry = WP_Logging::insert_log( $log_data, $log_meta );

						//Action For nextpay or RCP Developers...
						do_action( 'RCP_nextpay_Completed', $user_id );				
					}	

					
					
					if ($payment_status == 'cancelled')
					{
					
						$log_data = array(
							'post_title'    => __( 'انصراف از پرداخت', 'rcp_nextpay' ),
							'post_content'  =>  __( 'تراکنش به دلیل انصراف کاربر از پرداخت ، ناتمام باقی ماند .', 'rcp_nextpay' ).__( ' روش پرداخت : ', 'rcp_nextpay' ).$payment_method,
							'post_parent'   => 0,
							'log_type'      => 'gateway_error'
						);

						$log_meta = array(
							'user_subscription' => $subscription_name,
							'user_id'           => $user_id
						);
						
						$log_entry = WP_Logging::insert_log( $log_data, $log_meta );
					
						//Action For nextpay or RCP Developers...
						do_action( 'RCP_nextpay_Cancelled', $user_id );	

					}	
					
					if ($payment_status == 'failed') 
					{
									
						$log_data = array(
							'post_title'    => __( 'خطا در پرداخت', 'rcp_nextpay' ),
							'post_content'  =>  __( 'تراکنش به دلیل خطای رو به رو ناموفق باقی ماند :', 'rcp_nextpay' ).$this->Fault_Get($fault).__( ' روش پرداخت : ', 'rcp_nextpay' ).$payment_method,
							'post_parent'   => 0,
							'log_type'      => 'gateway_error'
						);

						$log_meta = array(
							'user_subscription' => $subscription_name,
							'user_id'           => $user_id
						);
						
						$log_entry = WP_Logging::insert_log( $log_data, $log_meta );
					
						//Action For nextpay or RCP Developers...
						do_action( 'RCP_nextpay_Failed', $user_id );	
					
					}
			
				}
				add_filter( 'the_content', array($this,  'nextpay_Content_After_Return') );
			}
		}
		 
		
		public function nextpay_Content_After_Return( $content ) { 
			
			global $nextpay_transaction, $new;
			
			$NEXTPAY_session = NextPay_Session::get_instance();
			@session_start();
			
			$new_payment = isset($GLOBALS['nextpay_new']) ? $GLOBALS['nextpay_new'] : $new;
			
			$payment_status = isset($GLOBALS['nextpay_payment_status']) ? $GLOBALS['nextpay_payment_status'] : $nextpay_transaction['nextpay_payment_status'];
			$transaction_id = isset($GLOBALS['nextpay_transaction_id']) ? $GLOBALS['nextpay_transaction_id'] : $nextpay_transaction['nextpay_transaction_id'];
			$fault = isset($GLOBALS['nextpay_fault']) ? $this->Fault_Get($GLOBALS['nextpay_fault']) : $this->Fault_Get($nextpay_transaction['nextpay_fault']);
			
			if ($new_payment == 1) 
			{
			
				$nextpay_data = array(
					'payment_status'             => $payment_status,
					'transaction_id'     => $transaction_id,
					'fault'     => $fault
				);
				
				$NEXTPAY_session['nextpay_data'] = $nextpay_data;
				$_SESSION["nextpay_data"] = $nextpay_data;	
			
			}
			else
			{
				if (isset($NEXTPAY_session['nextpay_data']))
					$nextpay_payment_data = $NEXTPAY_session['nextpay_data'];
				else 
					$nextpay_payment_data = isset($_SESSION["nextpay_data"]) ? $_SESSION["nextpay_data"] : '';
			
				$payment_status = isset($nextpay_payment_data['payment_status']) ? $nextpay_payment_data['payment_status'] : '';
				$transaction_id = isset($nextpay_payment_data['transaction_id']) ? $nextpay_payment_data['transaction_id'] : '';
				$fault = isset($nextpay_payment_data['fault']) ? $this->Fault_Get($nextpay_payment_data['fault']) : '';
			}
			
			$message = '';
			
			if ($payment_status == 'completed') {
				$message = '<br/>'.__( 'پرداخت با موفقیت انجام شد .', 'rcp_nextpay' ).'<p>کد تراکنش : </p><p style="color:green;">'.$transaction_id.'</p><br/>';
			}
			
			if ($payment_status == 'cancelled') {
				$message = '<br/>'.__( 'تراکنش به دلیل انصراف شما نا تمام باقی ماند .', 'rcp_nextpay' );
			}
			
			if ($payment_status == 'failed') {
				$message = '<br/>'.__( 'تراکنش به دلیل خطای زیر ناموفق باقی ماند :', 'rcp_nextpay' ).'<br/>'.$fault.'<br/>';
			}
			
			return $content.$message;
		}
		
		private static function Fault_Send($error) {
			$message = " ";
			switch($error)
			{
				case "-1" :
					$message = "api ارسالی تعریف نشده است";
				break;
				case "-2" :
					$message = "مبلغ تراکنش کمتر از 1000 ریال است";
				break;
				case "-3" :
					$message = "مسیر برگشت وجود ندارد";
				break;
										
				case "-4" :
					$message = "درگاه تعریف نشده است";
				break;	
			}
			return $message;
		}
		private static function Fault_Get($error) {
			 $response = "";
			include_once("nextpay_payment.php");
			$nextpay = new Nextpay_Payment();
			
			$response	=  __( $nextpay->code_error($error), 'rcp_nextpay' );
			
			return $response;
		}
		
	}
}
new RCP_nextpay();
if ( !function_exists('change_cancelled_to_pending_By_NEXTPAY')) {	
	add_action( 'rcp_set_status', 'change_cancelled_to_pending_By_NEXTPAY', 10, 2 );
	function change_cancelled_to_pending_By_NEXTPAY( $status, $user_id ) {
		if( 'cancelled' == $status )
			rcp_set_status( $user_id, 'expired' );
			return true;
	}
}
?>
