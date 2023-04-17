<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @return bool
 */
function wpbros_opay_edd_is_setup() {
    
    $secret_key = trim( edd_get_option( 'edd_opay_payment_secret_key' ) );
    $public_key = trim( edd_get_option( 'edd_opay_payment_public_key' ) );
    $merchant_id = trim( edd_get_option( 'edd_opay_payment_merchant_id' ) );
    
    return ! ( empty( $public_key ) || empty( $secret_key ) || empty( $merchant_id ) );
}