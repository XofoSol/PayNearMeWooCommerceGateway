<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/*
 * Plugin Name: WooCommerce PayNearMe Gateway
 * Description: Payment gateway for PayNearMe system. Accept payments in cash on local stores.
 * Author: Rodolfo Solorzano
 * Version: 0.1
 * @extends WC_Payment_Gateway
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

add_filter( 'woocommerce_payment_gateways', 'add_paynearme_gateway_class' );
function add_paynearme_gateway_class( $methods ) {
    $methods[] = 'WC_Gateway_PayNearMe'; 
    return $methods;
}

add_action('plugins_loaded', 'init_paynearme_gateway_class');
function init_paynearme_gateway_class(){
    if(!class_exists('WC_Gateway_PayNearMe')):
        class WC_Gateway_PayNearMe extends WC_Payment_Gateway
        {
            const PRODUCTION_URL = 'https://pro.paynearme.com/api/';
            const SANDBOX_URL = 'https://api.paynearme-sandbox.com/api/';
            
            public function __construct(){
                $this->id                 = 'paynearme';
                $this->method_title       = __('PayNearMe', 'woocommerce-gateway-paynearme');
                $this->method_description = __('PayNearMe lets your company safely and easily do business with the millions of customers who prefer to pay in cash.', 'woocommerce-gateway-paynearme');
                $this->has_fields         = false;
                $this->supports             = array(
                    'subscriptions',
                    'products',
                    'subscription_cancellation',
                    'subscription_reactivation',
                    'subscription_suspension'
                );
    
                // Load the settings
                $this->init_form_fields();
                $this->init_settings();
    
                // Get settings
                $this->title              = $this->get_option( 'title' );
                $this->description        = $this->get_option( 'description' );
                $this->instructions       = $this->get_option( 'instructions' );
                $this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
                $this->enable_for_virtual = $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes' ? true : false;
                $this->site_identifier    = $this->get_option( 'site_identifier' );
                $this->secret_key         = $this->get_option( 'secret_key' );
                $this->testmode           = 'yes' === $this->get_option( 'testmode' );
                $this->sandbox_site_identifier    = $this->get_option( 'sandbox_site_identifier' );
                $this->sandbox_secret_key         = $this->get_option( 'sandbox_secret_key' );
    
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                add_action( 'woocommerce_thankyou_paynearme', array( $this, 'thankyou_page' ) );
    
                // Customer Emails
                add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

                add_action( 'woocommerce_api_wc_gateway_paynearme', array( $this, 'payment_confirmation' ) );
            }
    
            public function init_form_fields() {
                $shipping_methods = array();
        
                if ( is_admin() ) {
                    foreach ( WC()->shipping()->load_shipping_methods() as $method ) {
                        $shipping_methods[ $method->id ] = $method->get_method_title();
                    }
                }
        
                $this->form_fields = array(
                    'enabled' => array(
                        'title'       => __( 'Enable PayNearMe', 'woocommerce-gateway-paynearme' ),
                        'label'       => __( 'Enable PayNearMe Cash Payments', 'woocommerce-gateway-paynearme' ),
                        'type'        => 'checkbox',
                        'description' => '',
                        'default'     => 'no'
                    ),
                    'title' => array(
                        'title'       => __( 'Title', 'woocommerce-gateway-paynearme' ),
                        'type'        => 'text',
                        'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce-gateway-paynearme' ),
                        'default'     => __( 'Cash (PayNearMe)', 'woocommerce-gateway-paynearme' ),
                        'desc_tip'    => true,
                    ),
                    'description' => array(
                        'title'       => __( 'Description', 'woocommerce-gateway-paynearme' ),
                        'type'        => 'textarea',
                        'description' => __( 'Payment method description that the customer will see on your website.', 'woocommerce-gateway-paynearme' ),
                        'default'     => __( 'PayNearMe is an easy way to use cash for making online purchases, paying bills and more. You can pay by scanning a barcode in one of 28,000 stores near you. Once the payment code is scanned by the store cashier, simply hand your payment in cash to the cashier and you will receive a receipt as proof of payment. The payment process in store takes less than 30 seconds.', 'woocommerce-gateway-paynearme' ),
                        'desc_tip'    => true,
                    ),
                    'instructions' => array(
                        'title'       => __( 'Instructions', 'woocommerce-gateway-paynearme' ),
                        'type'        => 'textarea',
                        'description' => __( 'Instructions that will be added to the thank you page.', 'woocommerce-gateway-paynearme' ),
                        'default'     => __( "1. Choose a store from the below list of participating payment locations, including CVS and 7-Eleven stores.\r\n".
                                             "2. Go to the store, hand your payment code to the store clerk, and tell them the amount you wish to pay.\r\n".
                                             "3. Present cash to the store clerk. Take the receipt as proof of payment. A confirmation of payment will also be sent to your phone.', 'woocommerce-gateway-paynearme" ),
                        'desc_tip'    => true,
                    ),
                    'enable_for_methods' => array(
                        'title'             => __( 'Enable for shipping methods', 'woocommerce-gateway-paynearme' ),
                        'type'              => 'multiselect',
                        'class'             => 'wc-enhanced-select',
                        'css'               => 'width: 450px;',
                        'default'           => '',
                        'description'       => __( 'If PayNearMe is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'woocommerce-gateway-paynearme' ),
                        'options'           => $shipping_methods,
                        'desc_tip'          => true,
                        'custom_attributes' => array(
                            'data-placeholder' => __( 'Select shipping methods', 'woocommerce-gateway-paynearme' )
                        )
                    ),
                    'enable_for_virtual' => array(
                        'title'             => __( 'Accept for virtual orders', 'woocommerce-gateway-paynearme' ),
                        'label'             => __( 'Accept PayNearMe if the order is virtual', 'woocommerce-gateway-paynearme' ),
                        'type'              => 'checkbox',
                        'default'           => 'yes'
                    ),
                    'site_identifier' => array(
                        'title'     => 'Site Identifier',
                        'type'      => 'text',
                        'default'   => 'xxxxxxxx',
                        'desc_tip'  => 'API Key'
                    ),
                    'secret_key'    => array(
                        'title'     => 'Secret Key',
                        'type'      => 'text',
                        'default'   => 'xxxxxxxx',
                        'desc_tip'  => 'API Secret'
                    ),
                    'testmode' => array(
                        'title'       => __( 'Test mode', 'woocommerce-gateway-paynearme' ),
                        'label'       => __( 'Enable Test Mode', 'woocommerce-gateway-paynearme' ),
                        'type'        => 'checkbox',
                        'description' => __( 'Place the payment gateway in test mode.', 'woocommerce-gateway-paynearme' ),
                        'default'     => 'yes',
                        'desc_tip'    => true,
                    ),
                    'sandbox_site_identifier' => array(
                        'title'     => 'Sandbox Site Identifier',
                        'type'      => 'text',
                        'default'   => 'xxxxxxxx',
                        'desc_tip'  => 'API Key'
                    ),
                    'sandbox_secret_key'    => array(
                        'title'     => 'Sandbox Secret Key',
                        'type'      => 'text',
                        'default'   => 'xxxxxxxx',
                        'desc_tip'  => 'API Secret'
                    ),
               );
            }
    
            public function is_available() {
                $order          = null;
                $needs_shipping = false;
        
                // Test if shipping is needed first
                if ( WC()->cart && WC()->cart->needs_shipping() ) {
                    $needs_shipping = true;
                } elseif ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
                    $order_id = absint( get_query_var( 'order-pay' ) );
                    $order    = wc_get_order( $order_id );
        
                    // Test if order needs shipping.
                    if ( 0 < sizeof( $order->get_items() ) ) {
                        foreach ( $order->get_items() as $item ) {
                            $_product = $order->get_product_from_item( $item );
                            if ( $_product && $_product->needs_shipping() ) {
                                $needs_shipping = true;
                                break;
                            }
                        }
                    }
                }
        
                $needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );
        
                // Virtual order, with virtual disabled
                if ( ! $this->enable_for_virtual && ! $needs_shipping ) {
                    return false;
                }
        
                // Check methods
                if ( ! empty( $this->enable_for_methods ) && $needs_shipping ) {
        
                    // Only apply if all packages are being shipped via chosen methods, or order is virtual
                    $chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );
        
                    if ( isset( $chosen_shipping_methods_session ) ) {
                        $chosen_shipping_methods = array_unique( $chosen_shipping_methods_session );
                    } else {
                        $chosen_shipping_methods = array();
                    }
        
                    $check_method = false;
        
                    if ( is_object( $order ) ) {
                        if ( $order->shipping_method ) {
                            $check_method = $order->shipping_method;
                        }
        
                    } elseif ( empty( $chosen_shipping_methods ) || sizeof( $chosen_shipping_methods ) > 1 ) {
                        $check_method = false;
                    } elseif ( sizeof( $chosen_shipping_methods ) == 1 ) {
                        $check_method = $chosen_shipping_methods[0];
                    }
        
                    if ( ! $check_method ) {
                        return false;
                    }
        
                    $found = false;
        
                    foreach ( $this->enable_for_methods as $method_id ) {
                        if ( strpos( $check_method, $method_id ) === 0 ) {
                            $found = true;
                            break;
                        }
                    }
        
                    if ( ! $found ) {
                        return false;
                    }
                }
        
                return parent::is_available();
            }
    
            public function process_payment( $order_id ){
                $order = wc_get_order( $order_id );
    
                // TODO: Enviar informacion de pago a PayNearMe, recibir url de seleccion de tienda.
                include_once( 'includes/paynearme-lib/request.php' );

                if($this->testmode){
                    $siteId = $this->sandbox_site_identifier;
                    $secretKey = $this->sandbox_secret_key;
                }else{
                    $siteId = $this->site_identifier;
                    $secretKey = $this->secret_key;
                }
                
                $req = new PaynearmeRequest( $siteId, $secretKey, '2.0' );

                $req->addParam( 'order_amount', $order->order_total );
                $req->addParam( 'order_currency', 'USD' );
                $req->addParam( 'order_type', 'exact' );
                $req->addParam( 'site_customer_identifier', $order->get_user_id() );
                $req->addParam( 'site_identifier', $siteId );
                $req->addParam( 'site_order_identifier', $order->get_order_number() );
                $req->addParam( 'version', '2.0' );
                $req->addParam( 'timestamp', time() );
    
                if($this->testmode){
                    $url = self::SANDBOX_URL . 'create_order?' . $req->queryString();
                }else{
                    $url = self::PRODUCTION_URL . 'create_order?' . $req->queryString();
                }

                
    
                $xml_response = file_get_contents( $url );
                $object_response = simplexml_load_string( $xml_response );
    
                if ( 'ok' == $this->xml_attribute( $object_response, 'status' ) ) {
                    $order->update_status( apply_filters( 'woocommerce_paynearme_process_payment_order_status', 'on-hold', $order ), __( 'Awaiting PayNearMe Confirmation.', 'woocommerce-gateway-paynearme' ) );
                    $order->reduce_order_stock();
                    WC()->cart->empty_cart();
                    update_post_meta($order->id, 'paynearme_tracking_url', trim($this->xml_attribute($object_response->order, 'order_tracking_url')));
                    update_post_meta($order->id, 'pnm_order_identifier', trim($this->xml_attribute($object_response->order, 'pnm_order_identifier')));
                    
                    return array(
                        'result' 	=> 'success',
                        'redirect'	=> $this->get_return_url( $order )
                    );
                }
                if ( 'error' == $this->xml_attribute( $object_response, 'status' ) ) {
                    $error = $this->xml_attribute( $object_response->errors->error, 'description' );
                    // Transaction was not succesful
                    wc_add_notice( 'PayNearMe error: '.$url, 'error' );
                    WC()->session->set( 'refresh_totals', true );
                    return;
                }
            }
    
            public function thankyou_page(){
                if ( $this->instructions ) {
                    echo wpautop( wptexturize( $this->instructions ) );
                }
            }
    
            public function email_instructions( $order, $sent_to_admin, $plain_text = false ){
                if ( $this->instructions && ! $sent_to_admin && 'paynearme' === $order->payment_method ) {
                    echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
                    echo 'Please follow this link to choose a payment location: <a href="'.get_post_meta($order->id, 'paynearme_tracking_url', true).'">Payment Locations</a>';
                }
            }

            public function payment_confirmation(){
                require_once( 'includes/paynearme-lib/confirmation-callback-handler.php' );
                global $wpdb;
                $url_params = $_GET;
                if($this->testmode){
                    $siteId = $this->sandbox_site_identifier;
                    $secretKey = $this->sandbox_secret_key;
                }else{
                    $siteId = $this->site_identifier;
                    $secretKey = $this->secret_key;
                }
                if ( isset( $url_params['status'] ) ) {
                    $paynearme_callback = new ConfirmationCallbackHandler( $secretKey, $url_params );
                    if(empty($url_params['site_order_identifier'])){
                        $order_id = $wpdb->get_var(sprintf('SELECT post_id FROM wp_postmeta WHERE meta_key = "%s" AND meta_value = "%s"', 'pnm_order_identifier', filter_var($url_params['pnm_order_identifier'], FILTER_SANITIZE_STRING)));
                    }else{
                        $order_id = $url_params['site_order_identifier'];
                    }
                    if ( 'payment' == $url_params['status'] && ! empty( $order_id ) && $paynearme_callback->is_valid_signature() ) {
                        
                        $order = wc_get_order( $order_id );
                        $order->payment_complete($url_params['pnm_payment_identifier']);
                        
                        if ( class_exists( 'WC_Subscriptions_Order' ) ) {
                            WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
                        }
                        $order->add_order_note( 'Payment received by the retailer. Order completed' );
                        update_post_meta($order_id, 'paynearme_payment_datetime', date("Y-m-d H:i:s"));
                    }

                    if ( 'decline' == $url_params['status'] && ! empty( $order_id ) && $paynearme_callback->is_valid_signature() ) {
                        $order = wc_get_order( $order_id );
                        $order->update_status('failed');
                        if ( class_exists( 'WC_Subscriptions_Order' ) ) {
                            WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order );
                        }
                    }


                    $request_to_paynearme = $paynearme_callback->handleRequest();
                    echo $request_to_paynearme;

                }            
                exit;
            }

            private function xml_attribute( $xml_object, $attribute ) {
                if ( isset( $xml_object[$attribute] ) ) {
                    return (string) $xml_object[$attribute];
                }
            }
        }
    endif;
}


add_action( 'woocommerce_thankyou', 'show_paynearme_tracking_url', 20 );
add_action( 'woocommerce_view_order', 'show_paynearme_tracking_url', 20 );
function show_paynearme_tracking_url($order_id){
    $trackingUrl = get_post_meta($order_id, 'paynearme_tracking_url', true);
    if(!empty($trackingUrl)){
        echo '<iframe src="'.$trackingUrl.'"></iframe>';
        echo '<style> iframe { width: 100%; max-height: 600px; height: 800px; border: none; margin: 20px; }</style>';
    }
}




 