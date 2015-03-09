<?php
/*
Plugin Name: WooCommerce SCB Payment Gateway Lite
Plugin URI: http://www.marketingbear.com/
Description: Extends WooCommerce with the Marketing Bear SCB Payment Gateway.
Version: 1.0
Author: Marketing Bear / Jan Rohweder
Author URI: http://www.marketingbear.com/

    Copyright: © 2013-2015 Marketing Bear.
*/
    if ( ! defined( 'ABSPATH' ) )
        exit;

// Add custom style

function scb_css() {
wp_register_style('scb_css', plugins_url('css/style.css',__FILE__ ));
wp_enqueue_style('scb_css');
}
add_action( 'wp_enqueue_scripts','scb_css');

function scb_admin_css() {
wp_register_style('scb_admin_css', plugins_url('css/admin-style.css',__FILE__ ));
wp_enqueue_style('scb_admin_css');
}
add_action( 'admin_init','scb_admin_css');
            // Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'woocommerce_scb_action_links' );
function woocommerce_scb_action_links( $links ) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_marketingbear_scb' ) . '">' . __( 'Settings', 'marketingbear' ) . '</a>',
    );
 
    // Merge our new link with the default ones
    return array_merge( $plugin_links, $links );    
}

add_action('plugins_loaded', 'woocommerce_marketingbear_scb_init', 0);

/**
* Before we check the response URL we need to inform WordPress about the new query
**/

function woocommerce_marketingbear_scb_init() {

	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

    /**
     * Gateway class
     */
    class wc_MarketingBear_SCB extends WC_Payment_Gateway {
        public function __construct(){

            // Go wild in here
            $this -> id           = 'scb';
            $this -> method_title = __('SCB - Siam Commercial Bank Lite', 'marketingbear');
            $this -> icon         =  plugins_url( 'images/SCB.jpg' , __FILE__ );
           // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = __( "SCB Payment Gateway Lite Plug-in for WooCommerce", 'marketingbear' );
 
        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = __( "SCB", 'marketingbear' );
 
        // Bool. Can be set to true if you want payment fields to show on the checkout 
        // if doing a direct integration, which we are doing in this case
        $this->has_fields = false;
 
        // Supports the default credit card form
        // $this->supports = array( 'default_credit_card_form' );
 
        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();
 
        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        // $this->title = $this->get_option( 'title' );
        $this->init_settings();
         
        // Turn these settings into variables we can use
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }

            $this -> title            = $this -> settings['title'];
            $this -> description      = $this -> settings['description'];
            $this->notify_url = str_replace( 'https:', 'http:', home_url( '/wc-api/wc_MarketingBear_SCB' )  );

            $this -> msg['message'] = "";
            $this -> msg['class']   = "";
            
            //add_action('init', array(&$this, 'check_scb_response'));
            //update for woocommerce >2.0
            add_action( 'woocommerce_api_wc_marketingbear_scb', array($this, 'check_scb_response' ) );

            add_action('valid-scb-request', array($this, 'successful_request'));
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
            add_action('woocommerce_receipt_scb', array($this, 'receipt_page'));
            add_action('woocommerce_thankyou_scb',array($this, 'thankyou_page_ac'));
        }
        
      function init_form_fields(){

            $this -> form_fields = array(
                'enabled' => array(
                'title'     => __( 'Enable / Disable', 'woocommerce-scb' ),
                'label'     => __( 'Enable this payment gateway', 'woocommerce-scb' ),
                'type'      => 'checkbox',
                'default'   => 'no',
            ),
            'title' => array(
                'title'     => __( 'Title', 'woocommerce-scb' ),
                'type'      => 'text',
                'desc_tip'  => __( 'Payment title the customer will see during the checkout process.', 'woocommerce-scb' ),
                'default'   => __( 'Siam Commercial Bank', 'woocommerce-scb' ),
            ),
            'description' => array(
                'title'     => __( 'Description', 'woocommerce-scb' ),
                'type'      => 'textarea',
                'desc_tip'  => __( 'Payment description the customer will see during the checkout process.', 'woocommerce-scb' ),
                'default'   => __( 'Pay securely using your credit card.', 'woocommerce-scb' ),
                'css'       => 'max-width:350px;'
            ),
            'merchant_id' => array(
                'title'     => __( 'SCB Merchant ID', 'woocommerce-scb' ),
                'type'      => 'text',
                'desc_tip'  => __( 'This is the Merchant ID SCB provided you with.', 'woocommerce-scb' ),
                'default'   => __( 'Please fill in your SCB Merchant ID.', 'woocommerce-scb' ),

            ),
            'terminal_id' => array(
                'title'     => __( 'SCB Terminal ID', 'woocommerce-scb' ),
                'type'      => 'text',
                'desc_tip'  => __( 'This is the Terminal ID SCB provided you with.', 'woocommerce-scb' ),
                'default'   => __( 'Please fill in your SCB Terminal ID.', 'woocommerce-scb' ),
            ),
                'environment' => array(
                    'title'     => __( 'SCB Test Mode', 'marketingbear' ),
                'label'     => __( 'Enable Test Mode', 'marketingbear' ),
                'type'      => 'checkbox',
                'description' => __( 'Place the SCB payment gateway in test mode.', 'marketingbear' ),
                'default'   => 'no'
            ),
                );
                
}
 /* Start return thankyou page code */
        function thankyou_page_ac($order_id)
        { 
        	global $woocommerce;
            $order = new WC_Order( $order_id );
            
            
            if( $order->status =='pending' ){
            if($_GET['response'] == 'approved')
            {
            	if( $order->has_downloadable_item() ){
					$order->update_status('completed', __( 'SCB Succesful Payment. Order is completed.', 'woocommerce' ));                
                	$order->add_order_note( __('Successful Payment Via SCB.', 'woothemes') );
			    	//Add customer order note
			 		$order->add_order_note('Payment Received.<br />Your order is completed.');				
					echo '<span style="color:#248F24;">Your payment was succesful. Thank you very much, your payment is now completed.</br></br></span>';				
				}
				else {
					$order->update_status('processing', __( 'SCB Succesful Payment. Please process the order.', 'woocommerce' ));                
                	$order->add_order_note( __('Successful Payment Via SCB.', 'woothemes') );
			    	//Add customer order note
			 		$order->add_order_note('Payment Received.<br />Your order is currently being processed.<br />We will be shipping your order to you soon.');				
			 		
					echo '<span style="color:#248F24;">Your payment was succesful. Thank you very much, we will process your order now.</br></br></span>';	
				}
			// Reduce stock levels
					$order->reduce_order_stock();
				
            }
            else if ($_GET['response'] == 'declined')
            {
           			$order->update_status('failed', __( 'SCB Declined Payment. Please contact customer.', 'woocommerce' ));                
			    	//Add customer order note
			 		$order->add_order_note('Payment Error.<br />Your payment was declined and your order failed.<br />Please try again or contact us and we will help you with the additional steps.<br />Our apologies for any inconvenience this may cause.', 1);
			 	echo '<span style="color:#CC0000;">Your payment was declined. It is currently on-hold. Please contact us.</br></br></span>';        		
            }
             else if ($_GET['response'] == 'error')
            {
					$order->update_status('failed', __( 'SCB Payment Error. Please contact customer.', 'woocommerce' ));                
			    	//Add customer order note
			 		$order->add_order_note('Payment Error.<br />Your payment was not successful. Your order failed.<br />Please try again or contact us and we will help you with the additional steps.<br />Our apologies for any inconvenience this may cause.', 1);
			 	echo '<span style="color:#CC0000;">Your payment failed. It is currently on-hold. Please contact us.</br></br></span>';
            }
              WC()->cart->empty_cart();
			}
		else if( $order->status =='failed' ){
			    if($_GET['response'] == 'approved'){
			    if( $order->has_downloadable_item() ){
					$order->update_status('on-hold', __( 'Attention! Possible hack attempt, please check first that you have received the fund.', 'woocommerce' ));                
                	$order->add_order_note( __('Possible Successful Payment Via SCB.', 'woothemes') );
			    	//Add customer order note
			 		$order->add_order_note('Payment Received.<br />However, your order is currently on-hold and needs to be checked manually.<br />Thank you for your patience.');				
			 		
					echo '<span style="color:#E68A2E;">Thank you for your payment. However, it requires manual verification by our team. For now your order is on-hold. We will update you shortly. Thank you for your understanding and patience.</br></br></span>';	
							
				}
				else {
					$order->update_status('on-hold', __( 'Attention! Possible hack attempt, please check first that you have received the funds.', 'woocommerce' ));                
                	$order->add_order_note( __('Possible Successful Payment Via SCB.', 'woothemes') );
			    	//Add customer order note
			 		$order->add_order_note('Thank you for your payment. However, it requires manual verification by our team. For now your order is on-hold. We will update you shortly. Thank you for your understanding and patience.');				
			 		
					echo '<span style="color:#E68A2E;">Thank you for your payment. However, it requires manual verification by our team. For now your order is on-hold. We will update you shortly. Thank you for your understanding and patience.</br></br></span>';	
				}
			// Reduce stock levels
					$order->reduce_order_stock();
				}
				
				else {
				echo '<span style="color:#CC0000;">We have already received your order. If your previous order was declined or an error appeared, please contact us.</br></br></span>';
				WC()->cart->empty_cart();
            }
		}
		else if( $order->status =='processing' ){
				echo '<span style="color:#248F24;">We have received your order already and are working hard to process it. We will get back to you soon. Thank you.</br></br></span>';	
		}			
		else {
			echo '<span style="color:#CC0000;">We have already received your order. If your previous order was declined or an error appeared, please contact us.</br></br></span>';
			WC()->cart->empty_cart();
            }
         }
        
/* End return thankyou page code */
        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
        public function admin_options(){
            echo '<h3>'.__('SCB - Siam Commercial Bank - WooCommerce Payment Gateway Lite', 'marketingbear').'</h3>';
            echo '<a href="https://marketingbear.com/product/woocommerce-scb-payment-gateway/" target="_blank"><img src="'.plugins_url('images/scb-woocommerce.png', __FILE__ ).'" width="30%"></a>';
            echo '<p><a href="https://marketingbear.com/product/woocommerce-scb-payment-gateway/" target="_blank"><span style="font-weight: bold;">UPGRADE NOW TO PRO VERSION:</span> Fully translatable, fully customizable and 100% compatible with all themes.</a></p>';
            echo '<table class="form-table">';
            $this -> generate_settings_html();
            echo '</table>';

        }
        /**
         *  There are no payment fields for SCB, but we want to show the description if set.
         **/
        function payment_fields(){
            if($this -> description) echo wpautop(wptexturize($this -> description));
        }
        
        /**
         * Generate SCB button link
         **/
       public function generate_scb_form($order_id){
            global $woocommerce;
            $order = new WC_Order($order_id);
            //Get todays date //
        	$newdate = date("YmdHis");
            $environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';
 
        	// Decide which URL to post to
        	$live_url = ( "FALSE" == $environment ) 
                           ? 'https://nsips.scb.co.th/NSIPSWeb/NsipsMessageAction.do'
                           : 'https://nsips-test.scb.co.th:443/NSIPSWeb/NsipsMessageAction.do';
                           
            $order_id = $order_id.'_'.date("ymds");
            $scb_args = array(
            "mid"            		=> $this->merchant_id,
            "terminal"             	=> $this->terminal_id,
            "version"             	=> "",
            "command"             	=> "CRAUTH",
			"ref_no"             	=> $order_id,
			"ref_date"             	=> $newdate,
			"service_id"			=> "11",
			"cust_id"               => $order->user_id,
			"cur_abbr"				=> "THB",
            "amount"              	=> $order->order_total,
            "backURL"				=> $this->get_return_url( $order )
                );

			$paramsJoined = array();


			foreach($scb_args as $param => $value) {
 			$paramsJoined[] = "$param=$value";
			}
			$merchant_data   = implode('&', $paramsJoined);
			
			$new_live_url = $live_url .'?'. $merchant_data;

wc_enqueue_js( '
    $.blockUI({
        message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to SCB - Siam Commercial Bank to make payment.', 'woocommerce' ) ) . '",
        baseZ: 99999,
        overlayCSS:
        {
            background: "#fff",
            opacity: 0.6
        },
        css: {
            padding:        "20px",
            zindex:         "9999999",
            textAlign:      "center",
            color:          "#555",
            border:         "3px solid #aaa",
            backgroundColor:"#fff",
            cursor:         "wait",
            lineHeight:     "24px",
        }
    });
jQuery("#submit_scb_payment_form").click();
' );

$form = '<form action="' . esc_url( $new_live_url ) . '" method="post" id="scb_payment_form" target="_top">
<!-- Button Fallback -->
<div class="payment_buttons">
<input type="submit" class="button alt" id="submit_scb_payment_form" value="' . __( 'Pay with Credit Card via SCB', 'woocommerce' ) . '" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'woocommerce' ) . '</a>
</div>
<script type="text/javascript">
jQuery(".payment_buttons").hide();
</script>
</form>';
return $form;

}
/**
* Process the payment and return the result
**/
function process_payment($order_id){
$order = new WC_Order($order_id);
return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ));
}

/**
         * Receipt Page
         **/
        function receipt_page($order){

            echo '<p>'.__('Thank you for your order, please click the button below to pay with CCAvenue.', 'marketingbear').'</p>';
            echo $this -> generate_scb_form ( $order );
        }
        
        

}

			            
    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_marketingbear_scb_gateway($methods) {
        $methods[] = 'wc_MarketingBear_SCB';
        return $methods;
    }
    
    
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_marketingbear_scb_gateway' );
}
