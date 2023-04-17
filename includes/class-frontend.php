<?php
/**
 * Documentation URL - https://documentation.opaycheckout.com/cashier-create
 *
 */

namespace WPBROS\EDD_OPAY;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frontend {
    
    public function __construct() {
        $this->hooks();
    }
    
    
    public function hooks() {
        add_filter( 'edd_payment_gateways', array( $this, 'register_opay_gateway' ) );
        add_action( 'edd_opay_cc_form', '__return_false' );
        add_action( 'edd_gateway_opay', array( $this, 'process_payment' ) );
        add_action( 'edd_pre_process_purchase', array( $this, 'is_opay_configured' ), 1 );
        add_filter( 'edd_currencies', array( $this, 'add_currencies' ) );
        add_action( 'init', array( $this, 'process_redirect' ) );
        add_action( 'wpbros_edd_opay_redirect_verify', array( $this, 'process_redirect_payment' ) );
        add_action( 'wpbros_edd_opay_ipn_verify', array( $this, 'process_webhook_ipn' ) );
    }
    
    /**
     * @param $gateways
     *
     * @return mixed
     */
    public function register_opay_gateway( $gateways ) {
        $gateways['opay'] = array(
            'admin_label'    => __( 'OPay', 'edd-opay' ),
            'checkout_label' => __( 'OPay', 'edd-opay' ),
        );
    
        return $gateways;
    }
    
    
    /**
     * @param $purchase_data
     */
    public function process_payment( $purchase_data ) {
    
        $payment_data = array(
            'price'        => $purchase_data['price'],
            'date'         => $purchase_data['date'],
            'user_email'   => $purchase_data['user_email'],
            'purchase_key' => $purchase_data['purchase_key'],
            'currency'     => edd_get_currency(),
            'downloads'    => $purchase_data['downloads'],
            'cart_details' => $purchase_data['cart_details'],
            'user_info'    => $purchase_data['user_info'],
            'status'       => 'pending',
            'gateway'      => 'opay',
        );
        
        $cart_details = $purchase_data['cart_details'];
        
        $cart_item = $this->get_cart_item($cart_details);
    
        $payment_id = edd_insert_payment( $payment_data );
    
        if ( false === $payment_id ) {
            edd_record_gateway_error( 'Payment Error', sprintf( 'Payment creation failed before sending buyer to Opay. Payment data: %s', wp_json_encode( $payment_data ) ), $payment_id );
    
            edd_send_back_to_checkout( '?payment-mode=opay' );
        } else {
            $opay_data  = array();
            $transaction_id = 'EDD-OPAY-' . $payment_id . '-' . uniqid();
            
            $expiration_time = !empty(edd_get_option( 'edd_opay_payment_order_expiration_time' )) ? edd_get_option( 'edd_opay_payment_order_expiration_time' ) : 30; // default is 30 mins
            
            $payment_method_type = !empty(edd_get_option('edd_opay_payment_method_type')) ?  edd_get_option('edd_opay_payment_method_type') : 'BankCard';
    
            $callback_url = add_query_arg( ['edd-listener' => 'opay', 'reference' => $transaction_id ], home_url( 'index.php' ) );
            
            $return_url = add_query_arg( ['payment-mode' => 'opay']);
    
            $ip = !empty($_SERVER['REMOTE_ADDR'])?sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
    
            $opay_data['amount']    = [
               'currency'   => $payment_data['currency'],
               'total'  => $purchase_data['price'] * 100
            ];
            
            $opay_data['userInfo'] = [
                'userName'      => $payment_data['user_info']['first_name']. ' '. $payment_data['user_info']['last_name'],
                'userEmail'    => $payment_data['user_info']['email']
            ];
            $opay_data['callbackUrl']       = $callback_url;
            $opay_data['cancelUrl']         = home_url('/');
            $opay_data['returnUrl']         = $return_url;
            $opay_data['country']          = 'NG';
            $opay_data['expireAt']          = $expiration_time;
            $opay_data['payMethod']         = $payment_method_type;
            $opay_data['product']           = [
                'description' => $cart_item['name'],
				'name' => $cart_item['name'],
            ];
            $opay_data['reference']         = $transaction_id;
            $opay_data['sn']         = $transaction_id;
            $opay_data['userClientIP']         = "$ip";
    
            edd_set_payment_transaction_id( $payment_id, $transaction_id );
            
            try {
                $response = $this->http_post( $opay_data );
    
                $result = $response ?json_decode($response,true) : null;
    
                if(!$result) {
                    $default_error_message = __( 'Internal server error.', 'edd-opay' );
        
                    edd_set_error( 'opay_error', $default_error_message );
        
                    edd_send_back_to_checkout( '?payment-mode=opay' );
                }
    
                if('00000' != $result['code']) {
                    $message = $result['message'];
        
                    edd_set_error( 'opay_error', $message );
        
                    edd_send_back_to_checkout( '?payment-mode=opay' );
                }
    
                $cashierUrl = $result['data']['cashierUrl'];
    
                wp_redirect( $cashierUrl );
                
            } catch (\Exception $exception) {
                $default_error_message = __( 'Failed.', 'edd-opay' );
    
                edd_set_error( 'opay_error', $default_error_message );
    
                edd_send_back_to_checkout( '?payment-mode=opay' );
            }
            
        }
    }
    
    
    public function http_post( $opay_data ) {
        
        $is_test_mode = edd_get_option('test_mode');
        $url = 'https://liveapi.opaycheckout.com/api/v2/international/cashier/create';
        if($is_test_mode) {
            $url = 'https://sandboxapi.opaycheckout.com/api/v2/international/cashier/create';
        }
    
        $json_data = (string) json_encode($opay_data);
    
        $timestamp = time();
        $authString = 'RequestBody=' . $json_data . '&RequestTimestamp=' . $timestamp;
        $auth = $this->authenticate($authString);
        
        $merchant_id = edd_get_option('edd_opay_payment_merchant_id');
        
        $headers = [
            'Authorization' => 'Bearer '.$auth,
            'MerchantId' => $merchant_id,
            'RequestTimestamp' => $timestamp,
            'content-type' => 'application/json',
            'ClientSource' => 'EDD',
        ];
    
        $args = array(
            'body'    => $json_data,
            'headers' => $headers,
            'timeout' => '60',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking'    => true,
            'cookies'     => array(),
        );
    
        $result = wp_remote_post( $url, $args );
        
        return $result['body'];
        
    }
    
    
    public function authenticate ( $data ) {
        $secretKey = edd_get_option('edd_opay_payment_secret_key');
        return hash_hmac('sha512', $data, $secretKey);
    }
    
    
    public function get_cart_item( $cart_details ) {
        $name = '';
        $code = '';
        $quantity  = 0;
        
        if(!empty($cart_details)) {
            foreach ( $cart_details as $cart_detail ) {
                $name .= "{$cart_detail['name']} ";
                $code .= "{$cart_detail['id']} ";
                $quantity += $cart_detail['quantity'];
            }
        }
    
        return ['name' => $name, 'code' => $code, 'quantity' => $quantity];
    }
    
    
    /**
     *
     */
    public function process_redirect() {
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! isset( $_GET['edd-listener'] ) ) {
            return;
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'opay' === sanitize_text_field( $_GET['edd-listener'] ) ) {
            do_action( 'wpbros_edd_opay_redirect_verify' );
        }
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'opayipn' === sanitize_text_field( $_GET['edd-listener'] ) ) {
            do_action( 'wpbros_edd_opay_ipn_verify' );
        }
    }
    
    
    public function process_redirect_payment() {
        if(isset($_REQUEST['reference'])) {
            $is_test_mode = edd_get_option('test_mode');
            $url = 'https://liveapi.opaycheckout.com/api/v1/international/cashier/status';
            if($is_test_mode) {
                $url = 'https://testapi.opaycheckout.com/api/v1/international/cashier/status';
            }
            
            $reference = $_REQUEST['reference'];
            
            $data = [
                'country' => 'NG', // get from settings
                'reference' => $reference
            ];
    
            $json_data2 = (string) json_encode($data);
    
            $timestamp = time();
            $auth = $this->authenticate($json_data2);
    
            $merchant_id = edd_get_option('edd_opay_payment_merchant_id');
    
            $headers = [
                'Authorization' => 'Bearer '.$auth,
                'MerchantId' => $merchant_id,
                'RequestTimestamp' => $timestamp,
                'Content-Type' => 'application/json',
            ];
    
            $args = array(
                'body'    => json_encode($data),
                'headers' => $headers,
                'timeout' => '60',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking'    => true,
                'cookies'     => array(),
            );
    
            $result = wp_remote_post( $url, $args );
    
            if (200 != $result['response']['code']) {
                print_r("invalid httpstatus:{$result['response']['code']} ,response:{$result['response']},detail_error:" . $result['response']['message'], $result['response']['code']);
    
                edd_set_error( 'failed_payment', __( sprintf('Payment failed Reason: %s', "invalid httpstatus:{$result['response']['code']} ,response:{$result['response']},detail_error:" . $result['response']['message']), 'edd-opay' ) );
    
                edd_send_back_to_checkout( '?payment-mode=opay' );
            }
    
            $result = $result['body'];
    
            $result = $result ? json_decode($result,true) : null;
            
            if(!$result) {
                $default_error_message = __( 'Internal server error.', 'edd-opay' );
    
                edd_set_error( 'opay_error', $default_error_message );
    
                edd_send_back_to_checkout( '?payment-mode=opay' );
            }
    
            if('00000' != $result['code']) {
                $message = $result['message'];
        
                edd_set_error( 'opay_error', $message );
        
                edd_send_back_to_checkout( '?payment-mode=opay' );
            }
            
    
            $order_info = explode( '-', $reference );
            
            $payment_id = $order_info[2];
            
            if( $payment_id && "SUCCESSFUL" === $result['message']) {
                $payment          = new \EDD_Payment( $payment_id );
                $order_total      = edd_get_payment_amount( $payment_id );
                $currency_symbol  = edd_currency_symbol( $payment->currency );
                $data = $result['data']['amount']['total'];
                $amount_paid = $data / 100;
                $reference = $data['reference'];
                
                if( $amount_paid < $order_total ) {
                    $formatted_amount_paid = $currency_symbol . $amount_paid;
                    $formatted_order_total = $currency_symbol . $order_total;
    
                    /* Translators: 1: Amount paid 2: Order total 3: Opay transaction reference. */
                    $note = sprintf( __( 'Look into this purchase. This order is currently revoked. Reason: Amount paid is less than the total order amount. Amount Paid was %1$s while the total order amount is %2$s. Opay Transaction Reference: %3$s', 'edd-opay' ), $formatted_amount_paid, $formatted_order_total, $reference  );
    
                    $payment->status = 'revoked';
                } else {
    
                    /* Translators: 1: Opay transaction reference. */
                    $note = sprintf( __( 'Payment transaction was successful. Opay Transaction Reference: %s', 'edd-opay' ), $reference );
    
                    $payment->status = 'publish';
                }
    
                $payment->add_note( $note );
                $payment->transaction_id = $reference;
    
                $payment->save();
    
                edd_empty_cart();
    
                edd_send_to_success_page();
                
            } else {
                edd_set_error( 'failed_payment', __( 'Payment failed. Please try again.', 'edd-opay' ) );
    
                edd_send_back_to_checkout( '?payment-mode=opay' );
            }
        }
    }
    
    /**
     * Process webhook ipn
     *
     */
    public function process_webhook_ipn() {
    
        if ( ( strtoupper( $_SERVER['REQUEST_METHOD'] ) !== 'POST' ) || ! array_key_exists( 'HTTP_X_OPAY_SIGNATURE', $_SERVER ) ) {
            exit;
        }
        
        // to be completed
        $json = file_get_contents( 'php://input' );
    
        $myfile = fopen(WPBROS_EDD_OPAY_PLUGIN_DIR.'log.txt', 'w');
    
        fwrite($myfile, $json);
        
        fclose($myfile);
    }
    
    /**
     * @param $currencies
     *
     * @return array
     */
    public function add_currencies( $currencies ) {
        $currencies['NGN'] = 'Nigerian Naira (&#8358;)';
        
        return $currencies;
    }
    
    /**
     *
     */
    public function is_opay_configured() {
        $is_enabled     = edd_is_gateway_active( 'opay' );
        $chosen_gateway = edd_get_chosen_gateway();
        
        if ( 'opay' === $chosen_gateway && ( ! $is_enabled || false === wpbros_opay_edd_is_setup() ) ) {
            edd_set_error( 'opay_gateway_not_configured', __( 'OPay payment gateway is not setup.', 'edd-opay' ) );
        }
        
        if ( 'opay' === $chosen_gateway && ! in_array( strtoupper( edd_get_currency() ), array( 'GHS', 'NGN', 'USD', 'ZAR' ), true ) ) {
            edd_set_error( 'opay_gateway_invalid_currency', __( 'Currency not supported by Opay. Set the store currency to either GHS (GH&#x20b5;), NGN (&#8358), USD (&#36;) or ZAR (R)', 'edd-opay' ) );
        }
    }
}

new namespace\Frontend();