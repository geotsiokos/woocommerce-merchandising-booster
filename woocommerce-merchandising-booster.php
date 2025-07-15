<?php
/*
 Plugin Name: WooCommerce Dynamic Merchandising Booster
 Description: Premium WooCommerce extension for dynamic, context-aware product recommendations with advanced rules, analytics, and multi-page placement.
 Version: 2.1.1
 Author: gtsiokos
 Text Domain: wmb
 Requires Plugins: woocommerce
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins', array())))) {
	add_action('admin_notices', function() {
		echo '<div class="error"><p>WooCommerce Dynamic Merchandising Booster requires WooCommerce to be installed and active.</p></div>';
	});
		return;
}

// Define constants
define('WMB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WMB_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include plugin files
require_once WMB_PLUGIN_DIR . 'includes/admin-settings.php';
require_once WMB_PLUGIN_DIR . 'includes/frontend-renderer.php';
?>