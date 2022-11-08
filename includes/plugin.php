<?php

namespace ZohoWP\PMPro;

if (!defined('ABSPATH') || !defined('ZOHOWP_DIR_PATH')) exit;

require_once ZOHOWP_DIR_PATH . '/includes/loader.php';
require_once ZOHOWP_PMPRO_DIR_PATH . '/includes/admin.php';
require_once ZOHOWP_DIR_PATH . '/includes/api/campaigns.php';

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
		self::add_filter('zohowp_pmpro_user_field_value', 'strip_email_plus', 10, 3);
		self::add_action('pmpro_added_order', 'pmpro_added_order', 15);
		self::add_action('pmpro_after_checkout', 'pmpro_after_checkout');
		self::add_action('pmpro_after_change_membership_level', 'pmpro_after_change_membership_level', 15, 2);
		self::add_action('pmpro_checkout_before_change_membership_level', 'pmpro_checkout_before_change_membership_level');
		self::add_action('pmpro_updated_order', 'pmpro_updated_order', 15);
	}

	/**
	 * Method runs on init hook
	 * Loads plugin textdomain
	 */
	public static function init()
	{
		load_plugin_textdomain('zohowp-pmpro', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	/**
	 * Method runs on zohowp_admin_classes hook
	 * Adds admin page to ZohoWP menu
	 */
	public static function admin_classes($classes)
	{
		$classes[] = Admin::class;
		return $classes;
	}

	/**
	 * Method runs on pmpro_checkout_before_change_membership_level hook
	 * Actions removed to fire after checkout is complete instead
	 */
	public static function pmpro_checkout_before_change_membership_level()
	{
		self::remove_action('pmpro_after_change_membership_level', 'pmpro_after_change_membership_level', 15, 2);
		self::remove_action('pmpro_added_order', 'pmpro_added_order', 15);
		self::remove_action('pmpro_updated_order', 'pmpro_updated_order', 15);
	}

	/**
	 * Method runs on pmpro_added_order hook
	 * Updates user mailing list subscriptions
	 * @param object $order MemberOrder object
	 */
	public static function pmpro_added_order($order) {
		self::update_user_subscriptions($order->user_id, 'order_add');
	}

	/**
	 * Method runs on pmpro_updated_order hook
	 * Updates user mailing list subscriptions
	 * @param object $order MemberOrder object
	 */
	public static function pmpro_updated_order($order) {
		self::update_user_subscriptions($order->user_id, 'order_update');
	}

	/**
	 * Method runs on pmpro_after_change_membership_level hook
	 * Updates user mailing list subscriptions
	 * @param number $level_id Level ID (unused)
	 * @param number $user_id User ID
	 */
	public static function pmpro_after_change_membership_level($level_id, $user_id)
	{
		self::update_user_subscriptions($user_id, 'level_change');
	}

	/**
	 * Method runs on pmpro_after_checkout hook
	 * Updates user mailing list subscriptions
	 * @param number $user_id User ID
	 */
	public static function pmpro_after_checkout($user_id)
	{
		self::update_user_subscriptions($user_id, 'level_change');
	}

	/**
	 * Updates user mailing list subscriptions
	 * @param number $user_id User ID
	 */
	private static function update_user_subscriptions($user_id, $trigger)
	{
		$options = get_option('zohowp_pmpro_levels');
		$user_levels = pmpro_getMembershipLevelsForUser($user_id);
		$user_level_ids = [];
		$subscribe_lists = [];
		$unsubscribe_lists = [];
		// Get lists to subscribe to
		foreach ($user_levels as $level) {
			$user_level_ids[] = $level->id;
			if (self::can_trigger_action($level->id, $trigger, 'subscribe', $options)) {
				$subscribe_lists[] = $options[$level->id]['list'];
			}
		}
		// Get lists to unsubscribe from
		global $wpdb;
		$sql_query = $wpdb->prepare("SELECT DISTINCT(membership_id) FROM $wpdb->pmpro_memberships_users WHERE user_id = %d AND membership_id NOT IN(%s) AND status IN('admin_changed', 'admin_cancelled', 'cancelled', 'changed', 'expired', 'inactive') AND modified > NOW() - INTERVAL 15 MINUTE ", $user_id, implode(',', $user_level_ids));
		$levels_unsubscribing_from = $wpdb->get_col($sql_query);
		foreach ($levels_unsubscribing_from as $unsub_level_id) {
			if (self::can_trigger_action($unsub_level_id, 'level_change', 'unsubscribe', $options)) {
				$unsubscribe_lists[] = $options[$unsub_level_id]['list'];
			}
		}
		// Filter for uniques and duplicates
		$subscribe_lists = array_unique($subscribe_lists);
		$unsubscribe_lists = array_unique($unsubscribe_lists);
		$unsubscribe_lists = array_diff($unsubscribe_lists, $subscribe_lists);
		// Subscribe / unsubscribe
		$user_data = self::contact_info_for_user($user_id);
		foreach ($subscribe_lists as $list) {
			if (!empty($list))
				error_log(print_r(\ZohoWP\API\Campaigns::subscribe($list, $user_data), true));
		}
		foreach ($unsubscribe_lists as $list) {
			if (!empty($list))
				error_log(print_r(\ZohoWP\API\Campaigns::unsubscribe($list, $user_data), true));
		}
	}

	// Check if options allow for action
	private static function can_trigger_action($level_id, $trigger, $action, $options)
	{
		return (!empty($level_id) &&
			!empty($options[$level_id]) &&
			!empty($options[$level_id]['list']) &&
			!empty($options[$level_id]['triggers']) &&
			!empty($options[$level_id]['actions']) &&
			in_array($trigger, $options[$level_id]['triggers']) &&
			in_array($action, $options[$level_id]['actions'])
		);
	}

	// Available mappings for user data
	// Use filter to extend
	// Callbacks can be registered for additional groups/fields
	public static function available_user_fields()
	{
		return apply_filters(
			'zohowp_pmpro_available_user_fields',
			[
				'user' => [
					'label' => __('User Fields', 'zohowp-pmpro'),
					'fields' => [
						'user_login'		=> __('User Login', 'zohowp-pmpro'),
						'user_nicename'		=> __('User Nicename', 'zohowp-pmpro'),
						'user_email'		=> __('User Email', 'zohowp-pmpro'),
						'user_url'			=> __('User URL', 'zohowp-pmpro'),
						'user_registered'	=> __('User Registered', 'zohowp-pmpro'),
						'display_name'		=> __('Display Name', 'zohowp-pmpro'),
					]
				],
				'user_meta' => [
					'label' => __('User Meta Fields', 'zohowp-pmpro'),
					'fields' => [
						'nickname'			=> __('User Nickname', 'zohowp-pmpro'),
						'first_name'		=> __('User First Name', 'zohowp-pmpro'),
						'last_name'			=> __('User Last Name', 'zohowp-pmpro'),
						'description'		=> __('User Description', 'zohowp-pmpro'),
						'pmpro_bfirstname'	=> __('Billing First Name', 'zohowp-pmpro'),
						'pmpro_blastname'	=> __('Billing First Name', 'zohowp-pmpro'),
						'pmpro_bemail'		=> __('Billing Email', 'zohowp-pmpro'),
						'pmpro_bphone'		=> __('Billing Phone', 'zohowp-pmpro'),
						'pmpro_baddress1'	=> __('Billing Address 1', 'zohowp-pmpro'),
						'pmpro_baddress2'	=> __('Billing Address 2', 'zohowp-pmpro'),
						'pmpro_bcity'		=> __('Billing City', 'zohowp-pmpro'),
						'pmpro_bstate'		=> __('Billing State', 'zohowp-pmpro'),
						'pmpro_bzipcode'	=> __('Billing Zip Code', 'zohowp-pmpro'),
						'pmpro_bcountry'	=> __('Billing Country', 'zohowp-pmpro'),
					]
				],
			]
		);
	}

	// Create array of user data for Zoho subscription
	private static function contact_info_for_user($user_id)
	{
		$options = get_option('zohowp_pmpro_fields');
		$all_fields = \ZohoWP\API\Campaigns::get_all_fields();
		$groups = apply_filters('zohowp_pmpro_user_field_groups', [
			'user' => get_user_by('id', $user_id),
			'user_meta' => array_map(function ($a) {
				return $a[0];
			}, get_user_meta($user_id))
		]);
		$result = [];
		foreach ($all_fields as &$zoho_field) {
			if (empty($options[$zoho_field['FIELD_NAME']]))
				continue;
			$mapping = $options[$zoho_field['FIELD_NAME']];
			list($group, $key) = explode('$', $mapping);
			$data = &$groups[$group];
			if (is_object($data)) {
				$value = $data->$key;
			} elseif (is_array($data)) {
				$value = $data[$key];
			} else {
				$value = '';
			}
			$value = apply_filters('zohowp_pmpro_user_field_value', $value, $group, $key);
			$result[$zoho_field['DISPLAY_NAME']] = $value;
		}
		return $result;
	}

	public static function strip_email_plus($value, $group, $key)
	{
		if ($group === 'user' && $key === 'user_email') {
			$value = preg_replace('/\+.*@/', '@', $value);
		}
		return $value;
	}
}
