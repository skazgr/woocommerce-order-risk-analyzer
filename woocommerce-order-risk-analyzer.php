<?php
/*
Plugin Name: WooCommerce Order Risk Analyzer
Description: Displays risk percentage with explanation on WooCommerce orders and provides admin settings to configure risk factors.
Version: 1.0.0
Author: Marios Progoulakis
Author URI: https://github.com/skazgr
Text Domain: woocommerce-order-risk-analyzer
Domain Path: /languages
Requires PHP: 7.2
Requires Plugins: WooCommerce

License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/


if (!defined('ABSPATH')) {
    exit;
}

define('WC_ORDER_RISK_ANALYZER_PATH', plugin_dir_path(__FILE__));
define('WC_ORDER_RISK_ANALYZER_URL', plugin_dir_url(__FILE__));

require_once WC_ORDER_RISK_ANALYZER_PATH . 'includes/class-risk-calculator.php';
require_once WC_ORDER_RISK_ANALYZER_PATH . 'admin/class-risk-admin.php';

// Initialize admin
add_action('plugins_loaded', function() {
	load_plugin_textdomain('woocommerce-order-risk-analyzer', false, dirname(plugin_basename(__FILE__)) . '/languages');
    if (is_admin()) {
        WC_Order_Risk_Admin::init();
    }
});