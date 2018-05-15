<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/*
 * Plugin Name: WooCommerce PayNearMe Gateway
 * Description: Payment gateway for PayNearMe system. Accept payments in cash on local stores.
 * Author: Rodolfo Solorzano
 * Version: 0.1
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

if(!class_exists('WC_Gateway_PayNearMe')):
    class WC_Gateway_PayNearMe extends WC_Payment_Gateway
    {
        public function __construct(){
            $this->id                 = 'paynearme';
            $this->method_title       = __('PayNearMe', 'woocommerce');
            $this->method_description = __('PayNearMe lets your company safely and easily do business with the millions of customers who prefer to pay in cash.', 'woocommerce');
            $this->has_fields         = false;

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Get settings
            $this->title              = $this->get_option( 'title' );
            $this->description        = $this->get_option( 'description' );
            $this->instructions       = $this->get_option( 'instructions' );
            $this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
            $this->enable_for_virtual = $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes' ? true : false;

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_paynearme', array( $this, 'thankyou_page' ) );

            // Customer Emails
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
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
                    'title'       => __( 'Enable PayNearMe', 'woocommerce' ),
                    'label'       => __( 'Enable PayNearMe Cash Payments', 'woocommerce' ),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => __( 'Title', 'woocommerce' ),
                    'type'        => 'text',
                    'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
                    'default'     => __( 'PayNearMe Cash Payments', 'woocommerce' ),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __( 'Description', 'woocommerce' ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your website.', 'woocommerce' ),
                    'default'     => __( 'PayNearMe lets your company safely and easily do business with the millions of customers who prefer to pay in cash.', 'woocommerce' ),
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'       => __( 'Instructions', 'woocommerce' ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank you page.', 'woocommerce' ),
                    'default'     => __( '1. Complete the order to generate your payment code.
    2. Choose a store from the on-screen list of participating payment locations, including CVS and 7-Eleven stores.
    3. Go to the store, hand your payment code to the store clerk, and tell them the amount you wish to pay.
    4. Present cash to the store clerk. Take the receipt as proof of payment. A confirmation of payment will also be sent to your phone.', 'woocommerce' ),
                    'desc_tip'    => true,
                ),
                'enable_for_methods' => array(
                    'title'             => __( 'Enable for shipping methods', 'woocommerce' ),
                    'type'              => 'multiselect',
                    'class'             => 'wc-enhanced-select',
                    'css'               => 'width: 450px;',
                    'default'           => '',
                    'description'       => __( 'If PayNearMe is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'woocommerce' ),
                    'options'           => $shipping_methods,
                    'desc_tip'          => true,
                    'custom_attributes' => array(
                        'data-placeholder' => __( 'Select shipping methods', 'woocommerce' )
                    )
                ),
                'enable_for_virtual' => array(
                    'title'             => __( 'Accept for virtual orders', 'woocommerce' ),
                    'label'             => __( 'Accept PayNearMe if the order is virtual', 'woocommerce' ),
                    'type'              => 'checkbox',
                    'default'           => 'yes'
                )
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


            // Si la confirmacion de paynearme es valida:
            // Mark as on-hold (we're awaiting the cheque)
            $order->update_status( 'on-hold', _x( 'Awaiting PayNearMe Confirmation', 'Check payment method', 'woocommerce' ) );

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            WC()->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result' 	=> 'success',
                'redirect'	=> $this->get_return_url( $order )
            );
        }

        public function thankyou_page(){

        }

        public function email_instructions( $order, $sent_to_admin, $plain_text = false ){

        }
    }
endif;