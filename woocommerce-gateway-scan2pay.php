<?php

/**
 * Plugin Name: Scan2Pay for WooCommerce
 * Plugin URI: https://scan2paynow.com/
 * Description: A smarter and secure way to collect payments from your website or mobile apps. Your customers scans a QR code to pay you without revealing their cards online. Other options include Pay With ID and Pay with Debit Card for those who prefer this method. 
 * Author: Samuel Adah
 * Author URI: https://alertco.net/
 * Version: 2.5.6
 * Text Domain: scan2pay-woo
 * License: GPLv2 or later
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}


define( 'WC_SCAN2PAY_VERSION', '2.5.6' );

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}




/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function scan2pay_gateway_plugin_links( $links ) {

    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=scan2pay' ) . '">' . __( 'Configure', 'scan2pay-woo' ) . '</a>'
    );

    return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'scan2pay_gateway_plugin_links' );

add_action('plugins_loaded', 'init_scan2pay', 0);

function init_scan2pay() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    class woocommerce_scan2pay extends WC_Payment_Gateway {

        public function __construct() { 
            global $woocommerce;

            $this->id           = 'scan2pay';
            $this->method_title = __('Scan2Pay', 'scan2pay-woo');
            $this->icon         = plugins_url( 'scan2pay.png', __FILE__ );
            $this->has_fields   = false;
            $this->notify_url   = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'woocommerce_scan2pay', home_url( '/' ) ) );

            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            // Define user set variables
            $this->scan2payurl          = $this->settings['scan2payurl'];
            $this->title                = $this->settings['title'];
            $this->description          = $this->settings['description'];
            $this->merchantid           = $this->settings['merchantid'];
            $this->pkey                 = $this->settings['pkey'];
            $this->skey                 = $this->settings['skey']; 
            $this->transactionDate      = date('Y-m-d H:i:s O');
            $this->woo_version          = $this->get_woo_version();

            // Actions
            add_action('init', array(&$this, 'successful_request'));
            add_action('woocommerce_api_woocommerce_scan2pay', array( &$this, 'successful_request' ));
            add_action('woocommerce_receipt_scan2pay', array(&$this, 'receipt_page'));
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ));
            }



 
        } 

           /**
         * Initialise Gateway Settings Form Fields
         */
            function init_form_fields() {
                $this->form_fields = array(
                'enabled' => array(
                                'title' => __( 'Enable/Disable:', 'scan2pay-woo' ), 
                                'type' => 'checkbox', 
                                'label' => __( 'Enable Scan2Pay', 'scan2pay-woo' ), 
                                'default' => 'yes'
                            ), 
                'scan2payurl' => array(
                                'title' => __( 'Live Parameters:', 'scan2pay-woo' ), 
                                'type' => 'checkbox', 
                                'label' => __( 'Live', 'scan2pay-woo' ), 
                                'default' => 'yes'
                            ), 
                'title' => array(
                                'title' => __( 'Title:', 'scan2pay-woo' ), 
                                'type' => 'text', 
                                'description' => __( 'The title which the user sees during checkout.', 'scan2pay-woo' ), 
                                'default' => __( 'Scan2Pay On-line Payment Gateway', 'scan2pay-woo' )
                            ),
                'description' => array(
                                'title' => __( 'Description:', 'scan2pay-woo' ), 
                                'type' => 'textarea', 
                                'description' => __( 'Description which the user sees during checkout.', 'scan2pay-woo' ), 
                                'default' => __('Pay securely through Scan2Pay Secure Servers.', 'scan2pay-woo')
                            ),
                'merchantid' => array(
                                'title' => __( 'Merchant ID:', 'scan2pay-woo' ), 
                                'type' => 'text', 
                                'description' => __( 'Please enter your Merchant ID as provided by Scan2Pay. Check your <a target=\'_blank\' href=\'https://dashboard.scan2paynow.com/merchants\'>Scan2Pay account</a>', 'scan2pay-woo' ), 
                                'default' => ''
                            ),
                'pkey' => array(
                                'title' => __( 'Merchant Public Key (pkey):', 'scan2pay-woo' ), 
                                'type' => 'text', 
                                'description' => __( 'Please enter your Merchant pkey as provided by Scan2Pay. Check your <a target=\'_blank\' href=\'https://dashboard.scan2paynow.com/merchants\'>Scan2Pay account</a>', 'scan2pay-woo' ), 
                                'default' => ''
                            ),
                 'skey' => array(
                                'title' => __( 'Merchant Secret Key (pkey):', 'scan2pay-woo' ), 
                                'type' => 'text', 
                                'description' => __( 'Please enter your Merchant skey as provided by Scan2Pay. Check your <a target=\'_blank\' href=\'https://dashboard.scan2paynow.com/merchants\'>Scan2Pay account</a>', 'scan2pay-woo' ), 
                                'default' => ''
                            )
                );

            }


            


        /**
         * Display Scan2Pay payment icon.
        */
            public function get_icon() {

                    $icon = '<img src="' . esc_url( plugins_url( 'images/logo_scan2pay_gateway.png', __FILE__ ) ) . '" alt="cards" />';

                    return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );

            }


        public function admin_options() {
            ?>
            <h3>Scan2Pay Payment Gateway</h3>
            <p><?php _e('Scan2Pay works by sending the user to Scan2Pay to enter their payment information.', 'scan2pay-woo'); ?></p>

            <table class="form-table">
            <?php
                // Generate the HTML For the settings form.
                $this->generate_settings_html();
            ?>
            </table><!--/.form-table-->
            <?php
        } // End admin_options()

        /**
         * There are no payment fields, but we want to show the description if set.
         **/
        function payment_fields() {
            if ($this->description) echo wpautop(wptexturize($this->description));
        }

        /**
         * Generate the button link
         **/
        public function generate_scan2pay_form( $order_id ) {
            global $woocommerce;


            $order = new WC_Order( $order_id ); 

            if ($this->scan2payurl == "yes"){
                $scan2pay_adr = "https://scan2paynow.com/widget/wp/";
            }else{
                $scan2pay_adr = "https://scan2paynow.com/widget/wp/demo/";
            }



            $scan2pay_args = array(
                'pkey'              => $this->pkey,
                'skey'              => $this->skey,
                'merchantid'        => $this->merchantid,
                'invno'             => $order->id,
                'amount'            => $order->order_total,
                'desc'              => $order_id,
                'postURL'           => $this->notify_url,
                'tel'               => $order->billing_phone,
                'email'             => $order->billing_email,
                'fname'             => $order->billing_first_name,
                'lname'             => $order->billing_last_name,
                'param'             => 'WC|V1'
            );

            $scan2pay_args_array = array();


            foreach ($scan2pay_args as $key => $value) {
                $scan2pay_args_array[] = '<input type="hidden" name="'.$key.'" value="'. $value .'" /><br>';
            }

            wc_enqueue_js('
                jQuery(function(){
                            jQuery("body").block(
                                { 
                                    message: "<div style=\"text-align:left\"><img src=\"' . esc_url( plugins_url( 'images/plswait.gif', __FILE__ ) ) . '\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" /><br>'.__('Thank you for your order. We are now redirecting you to Scan2Pay to make payment.', 'scan2pay-woo').'</div>", 
                                    overlayCSS: 
                                    { 
                                        background: "#fff", 
                                        opacity: 0.5 
                                    },
                                    css: { 
                                        padding:        18, 
                                        textAlign:      "center", 
                                        color:          "#555", 
                                        border:         "2px solid #aaa", 
                                        backgroundColor:"#fff", 
                                        cursor:         "wait",
                                        lineHeight:     "30px"
                                    } 
                                });
                            jQuery("#submit_scan2pay_payment_form").click();
                        });
            ');

        return '<form action="'.$scan2pay_adr.'" method="post">
                    ' . implode('', $scan2pay_args_array) . '
                    <input type="submit" class="button-alt" id="submit_scan2pay_payment_form" value="'.__('Pay with Scan2Pay', 'scan2pay-woo').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'scan2pay-woo').'</a>
                </form>';           

        }

        /**
         * Process the payment and return the result
         **/
        function process_payment( $order_id ) {

            $order = new WC_Order( $order_id );

            if($this->woo_version >= 2.1){
                $redirect = $order->get_checkout_payment_url( true );           
            }else if( $this->woo_version < 2.1 ){
                $redirect = add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))));
            }else{
                $redirect = add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))));
            }

            return array(
                'result'    => 'success',
                'redirect'  => $redirect
            );

        }

        /**
         * receipt_page
         **/
        function receipt_page( $order ) {

            echo '<p>'.__('Please click the button below to pay with Scan2Pay.', 'scan2pay-woo').'</p>';

            echo $this->generate_scan2pay_form( $order );

        }

        /**
         * Server callback was valid, process callback (update order as passed/failed etc).
         **/
        function successful_request($scan2pay_response) {
            global $woocommerce;

            if (isset($_GET['wc-api']) && $_GET['wc-api'] == 'woocommerce_scan2pay') {
                /** need to trim from result **/
                $Url_result = sanitize_text_field($_GET['result']);
                $ref = sanitize_text_field($_GET['ref']);
                
                $amount_paid = sanitize_text_field($_GET['amount']);
                
                $order = new WC_Order( (int) substr($Url_result,7,20) );
                 
                $tranID = (int)substr($Url_result,1,6);

                if (substr($Url_result,0,1) == '0'){
                    $r_status = 0;
                }else{
                $r_status = 33;
                }

                
                $order = new WC_Order( (int) $_GET['invno'] );
                //$r_status = (int) $_POST['result'];

                $order_total = $order->get_total(); 

                if ($r_status == '0' ){


                    if ($amount_paid == $order_total) {


                // Get an instance of the WC_Order object
 
                    $order->payment_complete();

                    $order->add_order_note('Scan2Pay Payment was SUCCESSFUL '.'<br>Reference ID is '  . $ref);

                     
                    $order->reduce_order_stock();
                    // Empty cart
                     $woocommerce->cart->empty_cart();

                    wp_redirect( $this->get_return_url($order) ); exit;



                    }else{

                     

                        $order->update_status( 'on-hold', '' );

                        $order->add_order_note('Thank you for shopping with us.%1$sYour payment transaction was successful, but the amount paid is not the same as the total order amount.%2$sYour order is currently on hold.%3$sKindly contact us for more information regarding your order and payment status. '.'<br>Reference ID is '  . $ref);

                       $failed_notice = __( '<strong>Look into this order</strong>. This order is currently on hold. Reason: Amount paid is less than the total order amount. <strong>Scan2Pay Transaction Reference:</strong> ' .$ref, 'scan2pay-woo' );

                       $order->update_status( 'failed', $order_notice );

                       wc_add_notice( $failed_notice, 'error' );

                       //wp_redirect($order->get_cancel_order_url()); 
                       wp_redirect( $this->get_return_url($order) ); exit;




                  }
                     


                }else{

                    $order->update_status('failed', sprintf(__('Scan2Pay Payment Failed. Your account was not activated. Please visit <a href=\'https://scan2paynow.com/activate\'>https://scan2paynow.com/activate</a> to activae your Scan2pay account', 'scan2pay-woo') ) );

                  
                   $failed_notice = __( 'Payment failed. Please try again.', 'scan2pay-woo' );

                   $order->update_status( 'failed', $order_notice );

                   wc_add_notice( $failed_notice, 'error' );

                   wp_redirect($order->get_cancel_order_url()); 

                    exit;

                }
            }               
        }


        

        function get_woo_version() {

            // If get_plugins() isn't available, require it
            if ( ! function_exists( 'get_plugins' ) )
                require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

            // Create the plugins folder and file variables
            $plugin_folder = get_plugins( '/woocommerce' );
            $plugin_file = 'woocommerce.php';

            // If the plugin version number is set, return it 
            if ( isset( $plugin_folder[$plugin_file]['Version'] ) ) {
                return $plugin_folder[$plugin_file]['Version'];

            } else {
                // Otherwise return null
                return NULL;
            }
        }
    }
}

/**
 * Add the gateway to WooCommerce
 **/
function add_scan2pay( $methods ) {
    $methods[] = 'woocommerce_scan2pay'; return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_scan2pay' );
