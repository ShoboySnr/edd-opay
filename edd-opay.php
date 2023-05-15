<?php
/*
    Plugin Name:       OPay Easy Digital Downloads Payment Gateway
    Plugin URL:        https://opayweb.com
    Description:       OPay payment gateway for Easy Digital Downloads
    Version:           1.0.0
    Author:            Damilare Shobowale
    Author URI:        https://techwithdee.com
    License:           GPL-2.0+
    License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
    Text Domain:       edd-opay
    Domain Path:       /languages
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin Root File.
if ( ! defined( 'WPBROS_EDD_OPAY_PLUGIN_FILE' ) ) {
    define( 'WPBROS_EDD_OPAY_PLUGIN_FILE', __FILE__ );
}

// Plugin version.
if ( ! defined( 'WPBROS_EDD_OPAY_VERSION' ) ) {
    define( 'WPBROS_EDD_OPAY_VERSION', '1.0.0' );
}

// Plugin Folder Path.
if ( ! defined( 'WPBROS_EDD_OPAY_PLUGIN_DIR' ) ) {
    define( 'WPBROS_EDD_OPAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Plugin Folder URL.
if ( ! defined( 'WPBROS_EDD_OPAY_URL' ) ) {
    define( 'WPBROS_EDD_OPAY_URL', plugin_dir_url( __FILE__ ) );
}


function wpbros_edd_opay_loader() {
    
    // Bail if Easy Digital Downloads is not active.
    if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
        return;
    }
    
    require_once WPBROS_EDD_OPAY_PLUGIN_DIR . 'includes/functions.php';
    require_once WPBROS_EDD_OPAY_PLUGIN_DIR . 'includes/class-frontend.php';
    
    if ( is_admin() ) {
        require_once WPBROS_EDD_OPAY_PLUGIN_DIR . 'includes/class-admin.php';
    }
    
}
add_action( 'plugins_loaded', 'wpbros_edd_opay_loader', 100 );