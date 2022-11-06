<?php

namespace ZohoWP\PMPro;

if (!defined('ABSPATH') || !defined('ZOHOWP_DIR_PATH')) exit;

require_once ZOHOWP_DIR_PATH . '/includes/loader.php';
require_once ZOHOWP_PMPRO_DIR_PATH . '/includes/admin.php';

class Plugin
{
	use \ZohoWP\Loader;

	private static $_instance = null;
	public static function instance()
	{
		if (is_null(self::$_instance))
			self::$_instance = new self();
		return self::$_instance;
	}

	private function __construct()
	{
		self::add_action('init', 'init');
		self::add_filter('zohowp_admin_classes', 'admin_classes');
	}

	public static function init()
	{
		load_plugin_textdomain('zohowp-pmpro', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	public static function admin_classes($classes) {
		$classes[] = Admin::class;
		return $classes;
	}
}
