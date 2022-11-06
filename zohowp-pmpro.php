<?php
/*
Plugin Name: ZohoWP - Paid Memberships Pro Integration
Description: Provides a Paid Memberships Pro integration for ZohoWP, adding options to add users to mailing lists.
Version: 1.0.0
Author: Mark Lagae
Text Domain: zohowp-pmpro
*/

define('ZOHOWP_PMPRO_DIR_PATH', plugin_dir_path(__FILE__));
define('ZOHOWP_PMPRO_VERSION', '1.0.0');

require_once ZOHOWP_PMPRO_DIR_PATH . '/includes/class-plugin.php';
\ZohoWP\PMPro\Plugin::instance();
