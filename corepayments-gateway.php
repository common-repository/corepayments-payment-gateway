<?php
/*
 * Plugin Name: CorePayments Payment Gateway
 * Plugin URI: CorePayments
 * Description: Take credit card payments on your store.
 * Author: CorePayments
 * Version: 1.1.8
 * Author: CoreCommerce
 * Author URI: https://www.corecommerce.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

/*
CorePayments Payment Gateway is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

CorePayments Payment Gateway is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with CorePayments Payment Gateway. If not, see https://www.gnu.org/licenses/gpl-3.0.html.

*/
define( 'COREGATEWAY_URL', 'https://api.mypaymentnow.com' );

add_filter('woocommerce_payment_gateways', 'corepayments_add_gateway_class');

function corepayments_add_gateway_class($gateways) {
    $gateways[] = 'WC_CorePayments_Gateway';
    return $gateways;
}

add_action('plugins_loaded', 'corepayments_init_gateway_class');

function corepayments_init_gateway_class() {

    class WC_CorePayments_Gateway extends WC_Payment_Gateway {
        public $mode = '';
        public $private_key = '';
        public $token_key = '';
        public $processor_id = '';
        public $transaction_type = '';
        public $saved_cards = '';
        public $coregateway_url = '';
        public $type = '';
        public $cg_name = '';
        public function __construct() {
            $this->id = 'corepayments';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = 'CoreGateway';
            $this->method_description = 'Take Payments Online Easily with CorePayments';
            $this->supports = array(
                'products',
                'refunds',
                'tokenization',
                'add_payment_method',
            );

            $this->init_form_fields();
            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->mode = $this->get_option('mode');
            $this->enabled = $this->get_option('enabled');
            $this->private_key = $this->get_option('private_key');
            $this->token_key = $this->get_option('token_key');
            $this->processor_id = $this->get_option('processor_id');
            $this->transaction_type = $this->get_option('transaction_type');
            $this->saved_cards = $this->get_option('saved_cards');

            if ($this->mode === 'sandbox') {
                $this->coregateway_url = 'https://api.coregateway.link';
            } else {
                $this->coregateway_url = 'https://api.mypaymentnow.com';
            }

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            add_action( 'woocommerce_order_status_processing', array( $this, 'action_woocommerce_order_status_changed' ) );
            add_action( 'woocommerce_order_status_completed', array( $this, 'action_woocommerce_order_status_changed' ) );
            add_action( 'woocommerce_order_status_cancelled', array( $this, 'process_refund' ) );
            add_action( 'woocommerce_order_status_refunded', array( $this, 'process_refund' ) );

        }


        /**
         * Admin Panel on form submission
         */
        public function process_admin_options() {

            $this->init_settings();

            $post_data = $this->get_post_data();

            
            
            foreach ($this->get_form_fields() as $key => $field) {
                if ('title' !== $this->get_field_type($field)) {
                    try {
                        //echo "<br> $key = $field";
                        $this->settings[$key] = $this->get_field_value($key, $field, $post_data);
                        //echo " =>".$this->settings[$key];
                    } catch (Exception $e) {
                        $this->add_error($e->getMessage());
                    }
                }
            }
            
            update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');
            

            if ($this->settings['mode'] === 'sandbox') {
                $this->coregateway_url = 'https://api.coregateway.link';
            } else {
                $this->coregateway_url = 'https://api.mypaymentnow.com';
            }
            $args = array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    "Authorization" => " Bearer ".$this->settings['token_key'] ,
                ),
                'timeout'     => 30,
            );
            //print_r($this->coregateway_url);exit;
            $processorsResponse = wp_remote_get($this->coregateway_url.'/processors', $args);
            //echo "<pre>";print_r($processorsResponse);echo "</pre>";exit;
            if (!is_wp_error($processorsResponse)) {

                $responseArray = json_decode($processorsResponse['body'], true);
                $processorData = $this->getCreditProcessor($responseArray);
                if (!empty($processorData)) {
                        $this->settings['processor_id'] = $processorData['id'];
                        $this->settings['supports'] = $processorData['supports'];
                        $this->settings['cg_name'] = $processorData['name'];
                    update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');
                }
            } else {
                wc_add_notice('Please try again.', 'error');
            }
        }

        private function getCreditProcessor($processors) {
            if (!empty($processors['data'])) {
                foreach ($processors['data'] as $key => $val) {
                    if (strtolower($val['supports']) == 'credit') {
                        return $val;
                    }
                }
            }
            return false;
        }

        /**
         * Admin Panel : Manage Payment option fields
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable CorePayments Gateway',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'Credit Card',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Pay with your credit card via our super-cool payment gateway.',
                ),
                'mode' => array(
                    'title' => 'Mode',
                    'type' => 'select',
                    'description' => 'In Sandbox Mode, you will need a specific Sandbox API Key. Please reach out to CoreGateway to obtain your Sandbox Key.',
                    'options'     => array(
                        'sandbox'  => 'Sandbox',
                        'live' => 'Live',
                    ),
                ),

                'token_key' => array(
                    'title' => 'API token',
                    'type' => 'text'
                ),
                'transaction_type' => array(
                    'title' => 'Transaction Type',
                    'type'        => 'select',
                    'options'     => array(
                                                'auth_n_cap'  => 'Capture',
                                                'cap_only' => 'Auth Only',
                                        ),
                ),
                'saved_cards' => array(
                    'title'       => __( 'Card on File', 'woocommerce-gateway-corePayments' ),
                    'label'       => __( 'Enable Payment via Card on File', 'woocommerce-gateway-corePayments' ),
                    'type'        => 'checkbox',
                    'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on CorePayments servers, not on your store.', 'woocommerce-gateway-corePayments' ),
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
            );
        }


        /**
         * Front End Form setup
         */
        public function payment_fields() {

            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }
            ?>
            <fieldset id="wc-<?php echo esc_attr($this->id);?>-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">
           <?php
            do_action('woocommerce_credit_card_form_start', $this->id);
            ?>
            <div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
                <input id="corepayments_ccNo" name="corepayments_ccNo" maxlength="16" type="text" autocomplete="off">
                </div>
                <div class="form-row form-row-first">
                        <label>Expiry Date <span class="required">*</span></label>
                        <input id="corepayments_expdate" name="corepayments_expdate" maxlength="5" type="text" autocomplete="off" placeholder="MM / YY">
                </div>
                <div class="form-row form-row-last">
                        <label>Card Code (CVC) <span class="required">*</span></label>
                        <input id="corepayments_cvv" name="corepayments_cvv"  maxlength="4" type="password" autocomplete="off" placeholder="CVC">
                </div>
                <div class="clear"></div>
            <?php
            if ( is_checkout() && $this->saved_cards && $this->saved_cards === 'yes') {
                $this->saved_payment_methods();
            }
            if ( apply_filters( 'wc_corePayments_display_save_payment_method_checkbox', is_checkout() && $this->saved_cards && $this->saved_cards === 'yes' ) && ! is_add_payment_method_page() && ! isset( $_GET['change_payment_method'] ) ) { // wpcs: csrf ok.
                $this->save_payment_method_checkbox();
            }

            do_action('woocommerce_credit_card_form_end', $this->id);
            ?>
            <div class="clear"></div></fieldset>
            <?php
        }

        public function payment_scripts() {

            if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
                return;
            }
            if ('no' === $this->enabled) {
                return;
            }
            if (empty($this->private_key) || empty($this->token_key)) {
                return;
            }
            wp_localize_script('woocommerce_corepayments', 'corepayments_params', array(
                'publishableKey' => $this->token_key
            ));
            wp_enqueue_script('woocommerce_corepayments');
        }

        /**
         * Validate Frontend Form
         */
        public function validate_fields() {

            if (isset($_POST['wc-corepayments-new-payment-method']) && sanitize_text_field($_POST['wc-corepayments-new-payment-method'] )) {

            } else {
                $iserror = true;
                if (empty(sanitize_text_field($_POST['corepayments_ccNo']))) {
                    wc_add_notice('Card Number is required!', 'error');
                    $iserror = false;
                }
                if (empty(sanitize_text_field($_POST['corepayments_expdate']))) {
                    wc_add_notice('Expiry Date is required!', 'error');
                    $iserror = false;
                }
                if (empty(sanitize_text_field($_POST['corepayments_cvv']))) {
                    wc_add_notice('Card Code (CVC) is required!', 'error');
                    $iserror = false;
                }
                return $iserror;
            }
            return true;
        }

        /**
         * Curl Post function
         */
        function curlpost($curl = '',$curlParams = array()){

            $headers = $curlParams["headers"];
            $data = $curlParams["data"];
            $method = $curlParams["method"];
            $response = wp_safe_remote_post( $curl, array(
                'method'      => $method,
                'timeout'     => 75,
                'headers'     => $headers,
                'body'        => $data,
                'sslverify'   => false,
                )
            );

            return $response;
        }

        /**
         * On submission of card details
         */
        public function process_payment($order_id) {
            $this->type = 'credit';
            $order = wc_get_order($order_id);
            $data = $this->createParameters($order);

            $headers = array(
                "Accept" => "application/json",
                "Content-type" => "application/json",
                "Authorization" => "Bearer " . $this->token_key,
            );

            $curl = $this->coregateway_url.'/transactions';


            $response = wp_safe_remote_post($curl, array(
                'method' => 'POST',
                'timeout' => 45,
                'headers' => $headers,
                'blocking'    => true,
                'httpversion' => '1.1',
                'sslverify' => false,
                'body' => $data)
            );

            //Added for debugging purpose
            if ($_COOKIE['coreGatewayTest']) {
                mail('oncall@sumeffect.com', 'coregateway request', print_r($data, true));
                mail('oncall@sumeffect.com', 'coregateway response', print_r($response, true));
            }

            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body( $response );
                $body = json_decode($body, true);
                if (strtolower($body['data']['status']) == 'captured' || strtolower($body['data']['status']) == 'authorized') {
                    if (strtolower($body['data']['status']) == 'captured') {
                        $order->add_order_note('Hey, your order is paid! Thank you!', true);
                        $order->payment_complete($body['data']['id']);
                        $order->reduce_order_stock();
                    } else {
                        $order->set_transaction_id($body['data']['id']);
                        $order->add_order_note('Hey, your order is authorized! Thank you!', true);
                        $order->update_status('on-hold');
                    }

                    if ( sanitize_text_field($_POST['wc-corepayments-new-payment-method']) ) {
                        $this->createCoreCustomer($order);
                    }

                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );

                } else {
                    if (count($body['errors']) > 0) {
                        foreach ($body['errors'] as $error) {
                            if (is_array($error)) {
                                foreach ($error as $level2Error) { 
                                    wc_add_notice($level2Error, 'error');
                                }
                            } else {
                                wc_add_notice($error, 'error');
                            }
                        }
                    } elseif (array_key_exists('message', $body) && !empty($body['message'])) {
                        wc_add_notice($body['message'], 'error');
                    } else {
                        wc_add_notice('Please try again.', 'error');
                    }
                    return;
                }
            } else {
                wc_add_notice('Connection error.', 'error');
                return;
            }
        }



        public function createParameters($order) {
            $this->init_settings();
            $this->processor_id = $this->get_option('processor_id');
            $this->type = 'credit';
            $this->cg_name = $this->get_option('cg_name');
            $this->transaction_type = $this->get_option('transaction_type');
            $params = array();
            $params['amount'] = $order->get_total() * 100;
            $params['description'] = $order->get_customer_note();
            $params['customFields'] = array();
            $creditCardonfile = false;
            if (isset($_POST['wc-corepayments-payment-token']) && !empty(sanitize_text_field($_POST['wc-corepayments-payment-token']))) {

                $corepayments_payment_token = sanitize_text_field($_POST['wc-corepayments-payment-token']);
                if ($corepayments_payment_token != 'new' && $corepayments_payment_token > 0) {
                    $creditCardonfile = true;
                }
            }
            if (isset($_POST['wc-corepayments-new-payment-method']) && sanitize_text_field($_POST['wc-corepayments-new-payment-method']) ) {
                $creditCardonfile = false;
            }
            if ($creditCardonfile) {
                $wc_token_id = wc_clean( sanitize_text_field($_POST[ 'wc-corepayments-payment-token' ] ));
                            $wc_token    = WC_Payment_Tokens::get( $wc_token_id );
                $creditCardExtra = [];
                $creditCardExtra['id'] =$wc_token->get_token();
                $creditCardExtra['customerId'] = get_user_option( '_coregateway_customerId', $order->get_user_id() );
                $creditCard = [];
                $creditCard['method'] = $this->transaction_type == "auth_n_cap" ? "capture" : "auth";
                $paymentMethod = $creditCardExtra;
                $paymentMethod['creditCard'] = $creditCard;
                $params['paymentMethod'] = $paymentMethod;
            } else {
                $paymentMethod = [];
                $paymentMethod['processorId'] = $this->processor_id;
                $paymentMethod['type'] = 'credit';
                $paymentMethod['nickname'] = $this->cg_name;
                $expMonth = $expYear = 0;

                $corepayments_expdate = sanitize_text_field($_POST['corepayments_expdate']);
                if ($corepayments_expdate && strpos($corepayments_expdate, '/')) {
                    $dateyear = explode('/', $corepayments_expdate);
                    $dt = DateTime::createFromFormat('m', sanitize_text_field($dateyear[0]));
                    $expMonth = $dt->format('m');
                    ;
                    $dt = DateTime::createFromFormat('m', sanitize_text_field($dateyear[0]));
                    $expYear = $dt->format('Y');
                }
                $creditCard = [];
                $creditCard['cardNumber'] = sanitize_text_field($_POST['corepayments_ccNo']) ?: "";
                $creditCard['expMonth'] = $expMonth;
                $creditCard['expYear'] = $expYear;
                $creditCard['method'] =  $this->transaction_type == "auth_n_cap" ? "capture" : "auth";
                $creditCard['cvv'] = ($_POST['corepayments_cvv']) ?: "";

                $paymentMethod['creditCard'] = $creditCard;
                $params['paymentMethod'] = $paymentMethod;
            }
            $billingAddress = [];
            $billingAddress['company'] = $order->get_billing_company();
            $billingAddress['firstName'] = $order->get_billing_first_name();
            $billingAddress['lastName'] = $order->get_billing_last_name();
            $billingAddress['email'] = $order->get_billing_email();
            $billingAddress['address1'] = $order->get_billing_address_1();
            $billingAddress['address2'] =  $order->get_billing_address_2();
            $billingAddress['city'] = $order->get_billing_city();
            $billingAddress['state'] = $order->get_billing_state();
            $billingAddress['zip'] = $order->get_billing_postcode();
            $billingAddress['country'] = $order->get_billing_country();
            $billingAddress['phone'] = $order->get_billing_phone();
            $billingAddress['fax'] = '';
            $customerDetails['billingAddress'] = $billingAddress;
            /* second Block end */
            $customerDetails['separateShipping'] = true;
            /* third Block start */
            $shippingAddress = [];
            $shippingAddress['company'] = $order->get_shipping_company() ?: $order->get_billing_company();
            $shippingAddress['firstName'] = $order->get_shipping_first_name() ?: $order->get_billing_first_name();
            $shippingAddress['lastName'] = $order->get_shipping_last_name() ?: $order->get_billing_last_name();
            $shippingAddress['email'] = $order->get_billing_email();
            $shippingAddress['address1'] = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
            $shippingAddress['address2'] = $order->get_shipping_address_2() ?: $order->get_billing_address_2();
            $shippingAddress['city'] = $order->get_shipping_city() ?: $order->get_billing_city();
            $shippingAddress['state'] = $order->get_shipping_state() ?: $order->get_billing_state();
            $shippingAddress['zip'] = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
            $shippingAddress['country'] = $order->get_shipping_country() ?: $order->get_billing_country();
            $shippingAddress['phone'] = "";
            $shippingAddress['fax'] = "";
            $customerDetails['shippingAddress'] = $billingAddress;
            /* third Block end */
            $params['customer'] = $customerDetails;
            return wp_json_encode($params);
        }

        function action_woocommerce_order_status_changed( $id ) {
            $order = wc_get_order( $id );
            $this->init_settings();
            $this->token_key = $this->get_option('token_key');

            $headers = array(
                "Accept" => "application/json",
                "Content-type" => "application/json",
                "Authorization" => "Bearer " . $this->token_key,
            );
            $url = $this->coregateway_url."/transactions/".$order->get_transaction_id(); //transactionid
            $params['capture'] = true;
            $data = json_encode($params);

            $curlParams["headers"] = $headers;
            $curlParams["data"] = $data;
            $curlParams["method"] =  "PATCH";
            $response = $this->curlpost($url,$curlParams);

            if (!is_wp_error($response)) {
                $response = wp_remote_retrieve_body( $response );
                $body = json_decode($response, true);
                if (strtolower($body['data']['status']) == 'captured') {
                    $order->payment_complete($body['data']['id']);
                    $order->reduce_order_stock();
                    $order->add_order_note('Hey, your order is paid! Thank you!', true);
                } else {
                    return;
                }
            } else {
                return;
            }
        }
        public function can_refund_order( $order ) {
            $hascred = false;
            $hascred = $this->get_option( 'token_key' ) && $this->get_option( 'processor_id' );
            return $order && $order->get_transaction_id() && $hascred;
        }

        public function process_refund( $order_id, $amount = null, $reason = '' ) {
            $order = wc_get_order( $order_id );

            if ( ! $this->can_refund_order( $order ) ) {
                return new WP_Error( 'error', __( 'Refund failed.', 'woocommerce' ) );
            }

            if("cancelled" == $order->get_status()){
                return new WP_Error( 'error', __( 'You cannot refund for a cancelled order.', 'woocommerce' ) );                
            } 

            $this->init_settings();
            $this->processor_id = $this->get_option('processor_id');
            $this->type = 'credit';
            $this->cg_name = $this->get_option('cg_name');
            $this->transaction_type = $this->get_option('transaction_type');

            $this->init_settings();
            $this->token_key = $this->get_option('token_key');
            $url = $this->coregateway_url."/transactions/" . $order->get_transaction_id();
           
            $headers = array(
                "Accept" => "application/json",
                "Content-type" => "application/json",
                "Authorization" => "Bearer " . $this->token_key,
            );

            $curlParams["headers"] = $headers;
            $curlParams["method"] =  "DELETE";
            if($this->transaction_type != "auth_n_cap") {
                $params['data']['amount'] = $order->get_total() * 100;
                $curlParams["data"] = json_encode($params);
                $url = $this->coregateway_url."/transactions/" . $order->get_transaction_id()."?amount=".$params['data']['amount'];
            }
            $response = $this->curlpost($url,$curlParams);

            return true;
        }

        public function createCoreCustomer($order) {

            $params = array();
            /* first Block start */
             $paymentMethod = [];
            $paymentMethod['processorId'] = $this->processor_id;
            $paymentMethod['type'] = 'credit';
            $paymentMethod['nickname'] = $this->cg_name;

            $expMonth = $expYear = 0;

            $corepayments_expdate = sanitize_text_field($_POST['corepayments_expdate']);
            if ($corepayments_expdate && strpos($corepayments_expdate, '/')) {
                $dateyear = explode('/', $corepayments_expdate);
                $dt = DateTime::createFromFormat('m', sanitize_text_field($dateyear[0]));
                $expMonth = $dt->format('m');
                ;
                $dt = DateTime::createFromFormat('m', sanitize_text_field($dateyear[0]));
                $expYear = $dt->format('Y');
            }
            $creditCard = [];
            $creditCard['cardNumber'] = sanitize_text_field($_POST['corepayments_ccNo']) ?: "";
            $creditCard['expMonth'] = $expMonth;
            $creditCard['expYear'] = $expYear;
            $creditCard['method'] =  $this->transaction_type == "auth_n_cap" ? "capture" : "auth";

            $creditCard['cvv'] = ($_POST['corepayments_cvv']) ?: "";
            $paymentMethod['creditCard'] = $creditCard;
            $params['paymentMethod'] = $paymentMethod;
            /* first Block end */

            /* second Block start */
            $billingAddress = [];
            $billingAddress['company'] = $order->get_billing_company();
            $billingAddress['firstName'] = $order->get_billing_first_name();
            $billingAddress['lastName'] = $order->get_billing_last_name();
            $billingAddress['email'] = $order->get_billing_email();
            $billingAddress['address1'] = $order->get_billing_address_1();
            $billingAddress['address2'] =  $order->get_billing_address_2();
            $billingAddress['city'] = $order->get_billing_city();
            $billingAddress['state'] = $order->get_billing_state();
            $billingAddress['zip'] = $order->get_billing_postcode();
            $billingAddress['country'] = $order->get_billing_country();
            $billingAddress['phone'] = $order->get_billing_phone();
            $billingAddress['fax'] = '';
            $customerDetails['billingAddress'] = $billingAddress;
            /* second Block end */
            $customerDetails['separateShipping'] = true;
            /* third Block start */
            $shippingAddress = [];
            $shippingAddress['company'] = $order->get_shipping_company() ?: $order->get_billing_company();
            $shippingAddress['firstName'] = $order->get_shipping_first_name() ?: $order->get_billing_first_name();
            $shippingAddress['lastName'] = $order->get_shipping_last_name() ?: $order->get_billing_last_name();
            $shippingAddress['email'] = $order->get_billing_email();
            $shippingAddress['address1'] = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
            $shippingAddress['address2'] = $order->get_shipping_address_2() ?: $order->get_billing_address_2();
            $shippingAddress['city'] = $order->get_shipping_city() ?: $order->get_billing_city();
            $shippingAddress['state'] = $order->get_shipping_state() ?: $order->get_billing_state();
            $shippingAddress['zip'] = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
            $shippingAddress['country'] = $order->get_shipping_country() ?: $order->get_billing_country();
            $shippingAddress['phone'] = "";
            $shippingAddress['fax'] = "";
            $customerDetails['shippingAddress'] = $billingAddress;

            $params = $customerDetails;
            $params["inVault"] = false;
            $params["externalId"] = get_bloginfo( 'name' );
            $params["transactionId"] = $order->get_transaction_id();
            $paymentMethod = [];
            $creditCardExtra['processorId'] = $this->processor_id;
            $creditCardExtra['type'] = 'credit';
            $creditCardExtra['nickname'] = $this->cg_name;
            $creditCard = [];
            $expMonth = $expYear = 0;

            $corepayments_expdate = sanitize_text_field($_POST['corepayments_expdate']);
            if ($corepayments_expdate && strpos($corepayments_expdate, '/')) {
                $dateyear = explode('/', $corepayments_expdate);
                $dt = DateTime::createFromFormat('m', sanitize_text_field($dateyear[0]));
                $expMonth = $dt->format('m');
                ;
                $dt = DateTime::createFromFormat('m', sanitize_text_field($dateyear[0]));
                $expYear = $dt->format('Y');
            }
            $creditCard = [];
            $creditCard['cardNumber'] = sanitize_text_field($_POST['corepayments_ccNo']) ?: "";
            $creditCard['expMonth'] = $expMonth;
            $creditCard['expYear'] = $expYear;
            $creditCard['method'] =  $this->transaction_type == "auth_n_cap" ? "capture" : "auth";
            $creditCard['cvv'] = ($_POST['corepayments_cvv']) ?: "";
            $paymentMethod = $creditCardExtra;
            $paymentMethod['creditCard'] = $creditCard;
            $params['paymentMethod'] = $paymentMethod;
            /* third Block end */
            $json = json_encode($params);
            $headers = array(
                "Accept" => "application/json",
                "Content-type" => "application/json",
                "Authorization" => "Bearer " . $this->token_key,
            );

            $url = $this->coregateway_url.'/customers';
            $curlParams = array();
            $curlParams["headers"] = $headers;
            $curlParams["data"] = $json;
            $curlParams["method"] =  "POST";
            $customerResponse = $this->curlpost($url,$curlParams);


            if (!is_wp_error($customerResponse)) {
                $response = wp_remote_retrieve_body( $customerResponse );
                $body = json_decode($response, true);
                if (isset($body['data']['id']) && !empty($body['data']['id']) ) {
                    $creditCard = [];
                    $creditCard['processorId'] = $this->processor_id;
                    $creditCard['type'] = 'credit';
                    $creditCard['nickname'] = $this->cg_name;
                    $creditCard['creditCard']['cardNumber'] = sanitize_text_field($_POST['corepayments_ccNo']) ?: "";
                    $creditCard['creditCard']['expMonth'] = $expMonth;
                    $creditCard['creditCard']['expYear'] = $expYear;
                    update_user_option( $order->get_user_id(), '_coregateway_customerId', $body['data']['id'], false );
                    $json = json_encode($creditCard);
                    $url = $this->coregateway_url."/customers/".$body['data']['id']."/payment-methods";


                    $curlParams = array();
                    $curlParams["headers"] = $headers;
                    $curlParams["data"] = $json;
                    $curlParams["method"] =  "POST";
                    $responseNew = $this->curlpost($url,$curlParams);
                    $paymentMethodResponse = wp_remote_retrieve_body( $responseNew );
                    $paymentMethodResponseDecode = json_decode($paymentMethodResponse, true);
                    if (!is_wp_error($paymentMethodResponseDecode)) {
                        if (isset($paymentMethodResponseDecode['data']['id']) && !empty($paymentMethodResponseDecode['data']['id']) ) {
                            if ( $order->get_user_id() && class_exists( 'WC_Payment_Token_CC' ) ) {
                                $wc_token = new WC_Payment_Token_CC();
                                $wc_token->set_token( $paymentMethodResponseDecode['data']['id'] );
                                $wc_token->set_gateway_id( 'corepayments' );
                                $wc_token->set_card_type( 'cc' );
                                $wc_token->set_last4( substr(sanitize_text_field($_POST['corepayments_ccNo']), -4) );
                                $wc_token->set_expiry_month( $expMonth );
                                $wc_token->set_expiry_year( $expYear );
                                $wc_token->set_user_id( $order->get_user_id() );
                                            $wc_token->save();
                            }
                        }
                    }
                }
            }
        }

    }

}
