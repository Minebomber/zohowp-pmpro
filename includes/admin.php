<?php

namespace ZohoWP\PMPro;

if (!defined('ABSPATH') || !defined('ZOHOWP_DIR_PATH')) exit;

require_once ZOHOWP_DIR_PATH . '/includes/admin/page.php';

class Admin extends \ZohoWP\Admin\Page
{
	protected const SLUG = 'zohowp-pmpro';

	public static function admin_menu()
	{
		self::add_submenu_page(
			__('Paid Memberships Pro Integration', 'zoho-wp'),
			__('PMPro Integration', 'zoho-wp'),
		);
	}

	public static function admin_init()
	{
	}
}
