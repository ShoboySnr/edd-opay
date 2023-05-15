<?php

namespace WPBROS\EDD_OPAY;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {
    
    public function __construct() {
        $this->hooks();
    }
    
    
    public function hooks() {
        add_filter( 'edd_settings_sections_gateways', array( $this, 'settings_section' ) );
        add_filter( 'edd_settings_gateways', array( $this, 'settings' ), 1 );
        add_action( 'admin_notices', array( $this, 'test_mode_notice' ) );
        add_filter(
            'plugin_action_links_' . plugin_basename( WPBROS_EDD_OPAY_PLUGIN_FILE ),
            array(
                $this,
                'plugin_action_links',
            )
        );
    }
    
    
    /**
     * @param $sections
     *
     * @return mixed
     */
    public function settings_section( $sections ) {
        $sections['opay-settings'] = __( 'OPay', 'edd-opay' );
    
        return $sections;
    }
    
    
    public function settings( $settings ) {
        $opay_settings = array(
            array(
                'id'   => 'edd_opay_settings',
                'name' => '<strong>' . __( 'OPay Settings', 'edd-opay' ) . '</strong>',
                'desc' => __( 'Configure the gateway settings', 'edd-opay' ),
                'type' => 'header',
            ),
            array(
                'id'   => 'edd_opay_payment_merchant_id',
                'name' => __( 'Merchant ID', 'edd-opay' ),
                'desc' => __( 'The merchant id in OPay merchant dashboard.', 'edd-opay' ),
                'type' => 'text',
                'size' => 'regular'
            ),
            array(
                'id'   => 'edd_opay_payment_secret_key',
                'name' => __( 'Secret Key', 'edd-opay' ),
                'desc' => __( 'The secret key in OPay merchant dashboard.', 'edd-opay' ),
                'type' => 'text',
                'size' => 'regular'
            ),
            array(
                'id'   => 'edd_opay_payment_public_key',
                'name' => __( 'Public Key', 'edd-opay' ),
                'desc' => __( 'The public key in OPay merchant dashboard.', 'edd-opay' ),
                'type' => 'text',
                'size' => 'regular'
            ),
            array(
                'id'   => 'edd_opay_payment_method_type',
                'name' => __( 'Payment Method Type', 'edd-opay' ),
                'desc' => __( 'Select the payment method type to use.', 'edd-opay' ),
                'type' => 'select',
                'std'   => 'BankCard',
                'options' => [
                      'BankCard'    => __('Bank Card', 'edd-opay'),
                      'BankTransfer'    => __('Bank Transfer', 'edd-opay'),
                      'BankAccount'    => __('Bank Account', 'edd-opay'),
                      'BankUssd'    => __('Bank USSD', 'edd-opay'),
                      'OpayWalletNg'    => __('OPay Wallet NG', 'edd-opay'),
                      'OpayWalletNgQR'    => __('OPay Wallet NG QR', 'edd-opay'),
                      'ReferenceCode'    => __('Reference Code', 'edd-opay'),
                ]
            ),
            array(
                'id'   => 'edd_opay_payment_order_expiration_time',
                'name' => __( 'Order Expiration Time (minutes)', 'edd-opay' ),
                'desc' => __( 'For example, if you enter 30, the unpaid order will be cancelled automatically after 30 minutes.', 'edd-opay' ),
                'type' => 'number',
                'size' => 'regular',
                'min'   => 1
            ),
            array(
                'id'   => 'edd_opay_webhook_url',
                'name' => __( 'Webhook URL', 'edd-opay' ),
                'desc' => '<p><strong>Important:</strong> To avoid situations where bad network makes it impossible to verify transactions, set your webhook URL <a href="https://merchant.opaycheckout.com/account-details" target="_blank">here</a> in your Opay account to the URL below.</p>' . '<p><strong><pre>' . home_url( 'index.php?edd-listener=opayipn' ) . '</pre></strong></p>',
                'type' => 'descriptive_text'
            ),
        );
    
        if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
            $opay_settings = array( 'opay-settings' => $opay_settings );
        }
    
        return array_merge( $settings, $opay_settings );
    }
    
    
    /**
     * Test mode notice
     *
     */
    public function test_mode_notice() {
        
        if ( edd_get_option( 'test_mode' ) ) {
            
            $allowed_html = array(
                'a' => array(
                    'href' => array(),
                ),
            );
            
            $opay_settings_url = admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways' );
            
            $dashboard_notice = __( 'OPay test mode is still enabled for Easy Digital Downloads, click <a href="%s">here</a> to disable it when you want to start accepting live payment on your site.', 'edd-opay' );
            
            ?>
            <div class="error">
                <p><?php printf( wp_kses( $dashboard_notice, $allowed_html ), esc_url( $opay_settings_url ) ); ?></p>
            </div>
            <?php
        }
    }
    
    /**
     * @param $links
     *
     * @return array
     */
    public function plugin_action_links( $links ) {
        
        $opay_settings_url = admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways&section=opay-settings' );
        
        $settings_link = '<a href="' . esc_url( $opay_settings_url ) . '">' . __( 'Settings', 'edd-opay' ) . '</a>';
        
        array_unshift( $links, $settings_link );
        
        return $links;
    }
    
}

new namespace\Admin();