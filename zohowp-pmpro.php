<?php
/*
Plugin Name: ZohoWP - Paid Memberships Pro Integration
Description: 
Version: 1.0.0
Author: Mark Lagae
Text Domain: zohowp-pmpro
*/

define('ZOHOWP_PMPRO_DIR_PATH', plugin_dir_path(__FILE__));
define('ZOHOWP_PMPRO_VERSION', '1.0.0');

require_once ZOHOWP_ELEMENTOR_DIR_PATH . '/includes/class-plugin.php';
\ZohoWP\Elementor\Plugin::instance();
