<?php
/*
Plugin Name: Mobile Money Payments Integration
Plugin URI:  https://www.nextelsystems.com/cloud-services/mobile-money-payments-integration/
Description: Enables businesses to receive Mobile Money Payments.
Version:     1.1.1
Author:      Nextel Systems® Ltd 
Author URI:  https://www.nextelsystems.com/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: www.nextelsystems.com
Domain Path: /languages
License URI: http://www.gnu.org/licenses

Copyright 2020 Nextel Systems (email : team@nextelsystems.com)
Mobile Money Payments Integration is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
Mobile Money Payments Integration is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with Mobile Money Payments Integration. If not, see (http://link to your plugin license).

*/


/**
 * WC_Gateway_NextelYoPayments Payment Gateway.
 *
 * Provides a Mobile Money Payment Gateway.
 *
 * @class 		WC_Gateway_NextelYoPayments
 * @extends		WC_Payment_Gateway
 * @version		1.1.1
 * @package		WooCommerce/Classes/Payment
 * @author 		WooThemes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit("ABSPATH not defined.");
}

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', 
	apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	//echo ("You need WooCommerce Installed to use this plugin.");
	return;
}

// Make sure WooCommerce is active
if ( ! function_exists("curl_init") ) {
	echo ("You need curl library installed and enabled to use this plugin.");
	return;
}



/*
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
*/
function wc_nextelyopayments_add_to_gateways( $methods ) {
	$methods[] = 'WC_Gateway_NextelYoPayments'; 
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'wc_nextelyopayments_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_nextelyopayments_gateway_plugin_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nextelyopayments' ) . '">' . __( 'Configure', 'wc-gateway-nextelyopayments' ) . '</a>'
	);
	return array_merge( $plugin_links, $links );
}


add_action( 'plugins_loaded', 'wc_nextelyopayments_gateway_init', 11 );


function wc_nextelyopayments_gateway_init() {

	/**
	 * WC_Gateway_NextelYoPayments Class.
	 */
	class WC_Gateway_NextelYoPayments extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
			$this->id                 = 'nextelyopayments';
			$this->icon =  plugin_dir_url( __FILE__ ).'assets/nextelyp_logo.png';
			$this->has_fields         = true;
			$this->method_title       = __( 'Mobile Money Payments', 'woocommerce' );
			$this->method_description = __( 'Accept Mobile Money payments (MTN MoMo & Airtel Money supported) on your WordPress/WooCommerce e-commerce website.', 'woocommerce' );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables.
			$this->title          = $this->get_option( 'nextelyp_title' );
			$this->description    = $this->get_option( 'nextelyp_description' );

			/*
			$this->api_username  = $this->get_option( 'nextelyp_api_username' );
			$this->api_password  = $this->get_option( 'nextelyp_api_password' );
			*/

			/*
			* Frank decided to get rid of test fields
			*
			$this->api_username_test = $this->get_option( 'nextelyp_api_username_test' );
			$this->api_password_test = $this->get_option( 'nextelyp_api_password_test' );
			*/

			$this->api_username_pay1 = $this->get_option( 'nextelyp_api_username_pay1' );
			$this->api_password_pay1 = $this->get_option( 'nextelyp_api_password_pay1' );

			//Now always should be set to false as we got rid of test fields
			$this->use_test = "no";//$this->get_option( 'nextelyp_use_test');
			
			
			/*
			* Frank suggested that only pay1 credentials are needed. Therefore, 
			* make the system to initiate all requests to Pay1 system.
			*/
			$this->use_mtn_on_pay1 = "yes";//$this->get_option( 'nextelyp_use_mtn_on_pay1' );
			$this->use_airtel_on_pay1 = "yes";//$this->get_option( 'nextelyp_use_airtel_on_pay1' );
			

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, 
				array( $this, 'process_admin_options' ) );

			/*echo "<pre>";
			print_r($this->form_fields);
			echo "</pre>";*/
		}


		/**
		 * Initialise Gateway Settings Form Fields.
		 */
		public function init_form_fields() 
		{
			$this->form_fields = apply_filters( 'wc_nextelyopayments_form_fields', array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Mobile Money Payments', 'woocommerce' ),
					'description' => __( 'This enables Mobile Money Payments which allows customers to make payment using Mobile Money.', 'woocommerce' ),
					'desc_tip'    => true,
					'default'     => 'yes'
				),

				'nextelyp_title' => array(
					'title'       => __( 'Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Mobile Money Payments', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'nextelyp_description' => array(
					'title'       => __( 'Description', 'woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Pay using your Mobile Phone on MTN Mobile Money or Airtel Money.', 'woocommerce' )
				),

				'nextelyp_api_username_pay1' => array(
					'title'       => __( 'API Username', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Obtain your API login credentials from Nextel Systems® Ltd', 'woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'nextelyp_api_password_pay1' => array(
					'title'       => __( 'API Password', 'woocommerce' ),
					'type'        => 'password',
					'description' => __( 'Obtain your API login credentials from Nextel Systems® Ltd', 'woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
				),

				/*
				'nextelyp_api_username_test' => array(
					'title'       => __( 'Test API Username', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Get your Test API credentials from Yo! Payments Sandbox System.', 'woocommerce' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'nextelyp_api_password_test' => array(
					'title'       => __( 'Test API Password', 'woocommerce' ),
					'type'        => 'text',
					'description' => __('Get your Test API credentials from Yo! Payments Sandbox System.', 'woocommerce'),
					'default'     => '',
					'desc_tip'    => true,
				),

				'nextelyp_use_test' => array(
					'title'       => __( 'Use Test System', 'woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable Test Environment', 'woocommerce' ),
					'default'     => 'no',
					'desc_tip'    => true,
					'description' => __( 'Check this if you want to use test environment.', 'woocommerce' ),
				),
				*/

				/*
				'nextelyp_use_mtn_on_pay1' => array(
					'title'       => __( 'Use Pay1 for MTN Mobile Money', 'woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Use Pay1 for MTN Mobile Money', 'woocommerce' ),
					'default'     => 'no',
					'desc_tip'    => true,
					'description' => __( 'Check this if you are to receive MTN Mobile Money payents from Pay1 live system. If you have got MTN Collections code and it was mapped to Yo! Payments, this should be checked.', 'woocommerce' ),
				),
				'nextelyp_use_airtel_on_pay1' => array(
					'title'       => __( 'Use Pay1 for Airtel Money', 'woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Use Pay1 for Airtel Mobile Money', 'woocommerce' ),
					'default'     => 'no',
					'desc_tip'    => true,
					'description' => __( 'Check this if you are to receive Airtel Money payents from Pay1 live system. If you have got an Airtel Money Collections account and it was mapped to Yo! Payments, this should be checked.', 'woocommerce' ),
				),
				*/
			));
			//include('include/settings-yopayments.php');
			 	
		}

		public function payment_fields()
		{
			echo "<label for='nextelyp_form_fields_phone'><strong>Mobile Money No (MTN/Airtel): </strong></label>
			<input type='text' name='nextelyp_form_fields_phone' id='nextelyp_form_fields_phone' size='35' /><br/>
			<span style='font-size:11px;'>Put in your number in this format: 256776123456</span>";
		}

		private function validatePhone($phone)
		{	
			if (!preg_match("/\d{1,3}\d{3}\d{6}$/", $phone))
				return false;

			return true;
		}

		public function validate_fields()
		{
			try {
				$phone = "";
				if (function_exists("sanitize_text_field")) {
					$phone = sanitize_text_field($_POST['nextelyp_form_fields_phone']);
				}
				if (!$this->validatePhone($phone)) {
					$e = "Invalid Phone number {$phone}. Correct format should be (CCCNNXXXXXXX) e.g 256772123456.";
					$e = $e;
					wc_add_notice( __('Payment error: ', 'woothemes') . $e, 'error' );
					return false;
				}
			} catch (Exception $ex) {
				$e = "Error: ".$ex->getMessage()."</br/>"
				."File: ".$ex->getFile()."<br/>"
				."Line: ".$ex->getLine();
				wc_add_notice( __('Payment error:', 'woothemes') . $e, 'error' );
			}

			return true;
		}

		public function process_payment($order_id)
		{
			try {
				global $woocommerce;

				$order = new WC_Order( $order_id );

				//Ensure we are using accepted currency.
				$currency = get_woocommerce_currency_symbol();
				if (!in_array(strtoupper($currency), array("UGX","USHS", "UGSHS", "SHS")) ) {
					wc_add_notice( 
						__('Payment error:', 'woothemes') 
						." Invalid currency {$currency}. Nextel Yo! Payments currently accepts UGX.", 
						'error' 
					);
					return;
				}

				$payment = $this->submit_payment($order);

				//header(':', true, 500);

				if (strcmp($payment['status'], "SUCCEEDED")==0) {

					//updated the order
					$order->update_status('completed', 
						__('Payment Completed, Transaction ID: '.$payment['transaction_id'],
							'woothemes'));

					// Payment complete
					$order->payment_complete();

					// Reduce stock levels
					$order->reduce_order_stock();

					// Remove cart
					$woocommerce->cart->empty_cart();

					// Return thank you page redirect
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
					);
				} else if (strcmp($payment['status'], 'UNDETERMINED')==0) {
					//updated the order
					$order->update_status('processing', 
						__('Payment Undetermined, Transaction ID: '.$payment['transaction_id'],
							'woothemes'));
					wc_add_notice( 
						__('Payment undetermined: Please do not try again', 'woothemes') . $payment['message'], 
						'notice' );
					return;

				} else if (strcmp($payment['status'], "FAILED")==0) {
					wc_add_notice( __('Payment error:', 'woothemes') . $payment['message'], 'error' );
					return;

				} else if (strcmp($payment['status'], "ERROR")==0) {
					wc_add_notice( __('Payment error:', 'woothemes') . $payment['message'], 'error' );
					return;
				} else {
					wc_add_notice( __('Payment error:', 'woothemes') . $payment['message'], 'error' );
					return;
				}

			} catch (Exception $ex) {
				$e = "Error: ".$ex->getMessage()."</br/>"
				."File: ".$ex->getFile()."<br/>"
				."Line: ".$ex->getLine();
				wc_add_notice( __('Payment error:', 'woothemes') . $e, 'error' );
				return;
			}
		}

		private function generateUniqueCode($len=5, $prefix="C", $type="mixed")
	    {
	        $string = "";
	        $chars = "346789ABCEFGHJKMNPQRTUVWXY";
	        
	        $start = mt_rand(mt_rand(0, 219), mt_rand(5031, 10294));
	        $string = "";
	        for($c = $start; $c < $start+2913; $c++) {
	            $string .= $chars[((mt_rand(mt_rand(2, 2390), mt_rand(4493, 10944)))%26)];
	        }
	        return $prefix.substr($string, mt_rand(9, 20), $len);
	    }


		/*
		* getCredentials Returns an array of YoPayments credentials
		* 
		* @Param $phone String: This is the phone number of the payer. e.g 256783086794
		* 
		* return Assoc Array [api_username, api_password]
		*/
	    private function getCredentials($phone)
	    {
	    	if (strcmp($this->use_test, "yes")==0) {
	    		$credentials['api_username'] = $this->api_username_test;
		    	$credentials['api_password'] = $this->api_password_test;
		    	return $credentials;
	    	}	

			//First get the phone number
			$get_network = $this->determinNetworkBasedOnPhone($phone);
			if (!is_null($get_network) && strcmp($get_network, "MTN MM") == 0) {
				if (strcmp($this->use_mtn_on_pay1, "yes")==0) {
					$credentials['api_username'] = $this->api_username_pay1;
					$credentials['api_password'] = $this->api_password_pay1;
					return $credentials;
				} else {
					$credentials['api_username'] = $this->api_username;
	    			$credentials['api_password'] = $this->api_password;
					return $credentials;
				}
			}

			if (!is_null($get_network) && strcmp($get_network, "AIRTEL MONEY") == 0) {
				if (strcmp($this->use_airtel_on_pay1, "yes")==0) {
					$credentials['api_username'] = $this->api_username_pay1;
					$credentials['api_password'] = $this->api_password_pay1;
					return $credentials;
				} else {
					$credentials['api_username'] = $this->api_username;
	    			$credentials['api_password'] = $this->api_password;
					return $credentials;
				}
			}

			$credentials['api_username'] = $this->api_username;
			$credentials['api_password'] = $this->api_password;
			
		    return $credentials;
	    }

	    private function getTotalAmount()
	    {
	    	return (float) WC()->cart->total;
	    }

		public function submit_payment($orderObj)
		{
			try {
				if (!class_exists("YoPaymentAPI")) {
					require_once __DIR__."/include/YoPaymentsAPI.php";
				}
				
		    	$amount = $this->getTotalAmount();
		    	$narrative = $orderObj->billing_email." ".$orderObj->billing_phone." ".$orderObj->billing_phone;
				$phone = "";
				if (function_exists("sanitize_text_field")) {
					$phone = sanitize_text_field($_POST['nextelyp_form_fields_phone']);
				}
				$external_ref = $orderObj->id;

				if (strcmp($this->use_test, "yes")==0) {
					$mode = "test";
					$url = $this->getUrl($phone);
				}
				else {
					$mode = "live";

					//If MTN is to be used on the public system, then check phone to get the url
					$url = $this->getUrl($phone);
					
				}

		        //Now try submitting a Yo! Payments request
		        set_time_limit(0);
		        $action = new YoPaymentAPI($this->getCredentials($phone), $mode, $url);

		        $details['NonBlocking'] = 'TRUE'; 
		        $details["Account"] = $phone;
		        $details["Amount"] = $amount;
		        $details['Narrative'] = $narrative;
		        $details['ExternalReference'] = $external_ref;
		        $details['ProviderReferenceText'] = $narrative;

		        $res =  $action->depositFunds($details);

		        if (!is_array($res)) {
		        	return array(
		                    "status"=>"ERROR",
		                    "message"=>$action->error,
		                );
		        }

			    if (isset($res['TransactionStatus'])) {
			        if ($res['TransactionStatus']== "SUCCEEDED") {
		                return array(
		                    "status"=>"SUCCEEDED",
		                    "message"=>"Payment received successfully. Click Ok to continue with the download.",
		                    'transaction_id'=>$res['TransactionReference'],
		                );
		            } else if ($res['TransactionStatus']== "FAILED") {
		                return array(
		                    "status"=>"FAILED",
		                    "message"=>"Payment failed. Please try again. See more: "
			                    .$res['StatusMessage']." ".$res['ErrorMessage'],
		                );
		                
		            } else if ($res['TransactionStatus']== "PENDING") {
		                $transaction_id = $res['TransactionReference'];
		                $details_['TransactionReference'] = $transaction_id;
		                $details_['PrivateTransactionReference'] = $details['ExternalReference'];
		                //Try checking for status of payment.
		                $check_time = 0;
		                while (1) {
		                	//Check if maximum number of retries has been reached.
		                	if ($check_time >= 4) {
		                		return array(
			                            "status"=>"UNDETERMINED",
			                            "message"=>"Undetermined payment.",
			                            'transaction_id'=>$transaction_id,
			                        );
		                	}

			                sleep(5);
			                $check =  $action->followUpTransaction($details_);
			                if (isset($check['TransactionStatus'])) {
			                    if ($check['TransactionStatus']== "SUCCEEDED") {
			                        return array(
			                            "status"=>"SUCCEEDED",
			                            "message"=>"Payment received successfully",
			                            'transaction_id'=>$transaction_id,
			                        );
			                    } else if ($check['TransactionStatus']== "FAILED") {
			                        return array(
			                            "status"=>"FAILED",
			                            "message"=>"Payment failed. Please try again. See more below: "
			                            .$res['StatusMessage']." ".$res['ErrorMessage'],
			                        );
			                    } else if ($check['TransactionStatus'] == "INDETERMINATE") {
			                        return array(
			                            "status"=>"UNDETERMINED",
			                            "message"=>"Undetermined payment."
			                        );
			                    }
			                } else {
			                	return array(
			                            "status"=>"UNDETERMINED",
			                            "message"=>"Undetermined payment.",
			                        );
			                }
			                $check_time += 1;
			            }
		            } else {
		                return array(
		                    "status"=>"ERROR",
		                    "message"=>$res['StatusMessage']." ".$res['ErrorMessage'],
		                );
		            }
			    } else {
			    	return array(
		                "status"=>"ERROR",
		                "message"=>$res['StatusMessage']." ".$res['ErrorMessage'],
		            );
			    }
			} catch (Exception $ex) {
				$e = "Error: ".$ex->getMessage()."</br/>"
				."File: ".$ex->getFile()."<br/>"
				."Line: ".$ex->getLine();
				wc_add_notice( __('Payment error:', 'woothemes') . $e, 'error' );
			}
		}

		/*
		* determinUrlBasedOnPhone gives you the URL based on the prefix 
		* of the phone numbers. For example MTN 256783xxx, Airtel 256701...
		* 
		* @Param String $phone - 	This is phone number the user is to use for transaction.
		*
		* 
		* Returns String - AIRTEL MONEY | MTN MM or NULL If network not recognized
		*  
		*/
		private function determinNetworkBasedOnPhone($phone)
		{
			if (preg_match("/(25678|25677)/i", $phone))
				return "MTN MM";
			else if (preg_match("/(25675|25670)/i", $phone))
				return "AIRTEL MONEY";
			else
				return null;
		}

		/*
		* determinUrlBasedOnPhone gives you the URL based on the prefix 
		* of the phone numbers. For example MTN 256783xxx, Airtel 256701...
		* 
		* @Param String $phone - 	This is phone number the user is to use for transaction.
		* @Param 	Array [pay1]: 	This is the URL to Pay1 system.
		* 			Array [public]:	This is the URL to Yo! Payments Public system.
		* 
		* Returns String - URL or null if the prefix doesn't belong to supported network.
		*  
		*/
		private function determinUrlBasedOnPhone($phone, $urls)
		{
			if (preg_match("/(25678|25677)/i", $phone))
				return $urls['public'];
			else if (preg_match("/(25675|25670)/i", $phone))
				return $urls['pay1'];
			else
				return null;
		}

		/*
		* Gets URL based on the network determined.
		* 
		* @Param $phone String: This is the phone number of the payer.
		* 
		* Returns String 
		*/
		private function getUrl($phone = "")
		{
			if (strcmp($this->use_test, "yes")==0)  {
				return "https://sandbox.yo.co.ug/services/yopaymentsdev/task.php";
			} else {
				$get_network = $this->determinNetworkBasedOnPhone($phone);
				if (!is_null($get_network) 
					&& strcmp($get_network, "MTN MM") == 0
					&& strcmp($this->use_mtn_on_pay1, "yes")==0) {
					return "https://pay1.yo.co.ug/ybs/task.php";
				}

				if (!is_null($get_network) 
					&& strcmp($get_network, "AIRTEL MONEY") == 0
					&& strcmp($this->use_airtel_on_pay1, "yes")==0) {
					return "https://pay1.yo.co.ug/ybs/task.php";
				}

				return "https://paymentsapi1.yo.co.ug/ybs/task.php";
			}
		}
	}

}





?>