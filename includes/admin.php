<?php

namespace ZohoWP\PMPro;

if (!defined('ABSPATH') || !defined('ZOHOWP_DIR_PATH')) exit;

require_once ZOHOWP_DIR_PATH . '/includes/admin/page.php';
require_once ZOHOWP_DIR_PATH . '/includes/api/campaigns.php';

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
		// mapping between zoho fields and user attributes
		self::register_setting('zohowp_pmpro_fields', ['type' => 'array', 'default' => []]);
		// level id => [ listkey, ... (actions?, on level change, on order, etc) ]
		self::register_setting('zohowp_pmpro_levels', ['type' => 'array', 'default' => []]);
		self::add_section(
			'fields',
			__('Field Mapping', 'zohowp-pmpro'),
			'fields_section'
		);
		$all_fields = \ZohoWP\API\Campaigns::get_all_fields();
		foreach ($all_fields as &$field) {
			$no = $field['no'];
			$required = $field['IS_MANDATORY'];
			$name = $field['DISPLAY_NAME'];
			$key = $field['FIELD_NAME'];
			self::add_field(
				"field_$no",
				$name . ($required ? ' (Required)' : ''),
				'fields_field',
				'fields',
				['key' => $key]
			);
		}
		self::add_section(
			'levels',
			__('Membership Levels', 'zohowp-pmpro'),
			'levels_section'
		);
		$all_levels = pmpro_getAllLevels(true, true);
		foreach ($all_levels as $id => &$level) {
			self::add_field(
				"level_$id",
				$level->name,
				'levels_field',
				'levels',
				['level' => $level->id]
			);
		}
	}

	public static function fields_section()
	{
?>
		<p><?php _e('Configure field mapping between Zoho and Paid Memberships Pro.', 'zohowp-pmpro'); ?></p>
	<?php
	}

	public static function fields_field($args)
	{
		$options = get_option('zohowp_pmpro_fields');
		$zoho_id = $args['key'];
		$selected_option = empty($options[$zoho_id]) ? '' : $options[$zoho_id];
		$attributes = self::html_attributes(['name' => "zohowp_pmpro_fields[$zoho_id]"]);
		$available_fields = self::available_user_fields();
	?>
		<select <?php echo $attributes; ?>>
			<option <?php echo self::html_attributes(['selected' => $selected_option === '', 'value' => '']) ?>><?php _e('-- Select user field --', 'zohowp-pmpro'); ?></option>
			<?php foreach ($available_fields as $source => &$data) : ?>
				<optgroup label='<?php echo $data['label']; ?>'>
					<?php foreach ($data['fields'] as $key => $title) : ?>
						<option <?php echo self::html_attributes(['value' => "$source$$key", 'selected' => ($selected_option === "$source$$key")]) ?>><?php echo $title; ?></option>
					<?php endforeach; ?>
				</optgroup>
			<?php endforeach; ?>
		</select>
	<?php
	}

	public static function levels_section()
	{
	?>
		<p><?php _e('Assign membership levels to email lists.', 'zohowp-pmpro'); ?></p>
		<script>
			jQuery(document).ready(function() {
				jQuery('.zohowp_pmpro_level_select').on('change', function() {
					const select = jQuery(this);
					const level_id = select.attr('id').split('_').slice(-1)[0].split('-')[0];
					const id = `#zohowp_pmpro_level_${level_id}-details`;
					console.log(id);
					const details = jQuery(`#zohowp_pmpro_level_${level_id}-details`);
					if (select.val() !== '') details.show();
					else details.hide();
					console.log(select, level_id, select.val());
				});
			});
		</script>
	<?php
	}

	public static function levels_field($args)
	{
		$level_id = $args['level'];
		$options = get_option('zohowp_pmpro_levels');
		$data = isset($options[$level_id]) ? $options[$level_id] : [
			'list' => '',
			'triggers' => [],
			'actions' => []
		];
		$listkey = empty($data['list']) ? '' : $data['list'];
		$triggers = empty($data['triggers']) ? [] : $data['triggers'];
		$actions = empty($data['actions']) ? [] : $data['actions'];
		$base_name = "zohowp_pmpro_levels[$level_id]";
		$base_id = "zohowp_pmpro_level_$level_id";

		$attributes = self::html_attributes([
			'class' => 'zohowp_pmpro_level_select',
			'id' => "$base_id-list",
			'name' => "{$base_name}[list]",
		]);

	?>
		<select <?php echo $attributes; ?>>
			<option <?php echo self::html_attributes(['selected' => $listkey === '', 'value' => '']); ?>><?php _e('-- Select mailing list --', 'zohowp-pmpro'); ?></option>
			<?php
			$lists = \ZohoWP\API\Campaigns::get_mailing_lists();
			foreach ($lists as &$list) {
			?>
				<option <?php echo self::html_attributes(['selected' => $listkey === $list['listkey'], 'value' => $list['listkey']]); ?>><?php echo $list['listname']; ?></option>
			<?php
			}
			?>
		</select>
		<div <?php echo self::html_attributes(['id' => "$base_id-details", 'style' => ($listkey === '' ? 'display: none;' : '')]); ?>>
			<div>
				<h4><?php _e('Subscription Triggers', 'zohowp-pmpro'); ?></h4>
				<div>
					<input <?php echo self::html_attributes(['checked' => array_search('level_change', $triggers), 'id' => "$base_id-trigger_level_change", 'name' => "{$base_name}[triggers][]", 'type' => 'checkbox', 'value' => 'level_change']); ?> />
					<label <?php echo self::html_attributes(['for' => "$base_id-trigger_level_change"]); ?>><?php _e('Membership level change', 'zohowp-pmpro'); ?></label>
				</div>
				<div>
					<input <?php echo self::html_attributes(['checked' => array_search('order_update', $triggers), 'id' => "$base_id-trigger_order_update", 'name' => "{$base_name}[triggers][]", 'type' => 'checkbox', 'value' => 'order_update']); ?> />
					<label <?php echo self::html_attributes(['for' => "$base_id-trigger_order_update"]); ?>><?php _e('Order added or updated', 'zohowp-pmpro'); ?></label>
				</div>
			</div>
			<div>
				<h4><?php _e('Allowed Actions', 'zohowp-pmpro'); ?></h4>
				<div>
					<input <?php echo self::html_attributes(['checked' => array_search('subscribe', $actions), 'id' => "$base_id-action_subscribe", 'name' => "{$base_name}[actions][]", 'type' => 'checkbox', 'value' => 'subscribe']); ?> />
					<label <?php echo self::html_attributes(['for' => "$base_id-action_subscribe"]); ?>><?php _e('Subscribe to mailing list', 'zohowp-pmpro'); ?></label>
				</div>
				<div>
					<input <?php echo self::html_attributes(['checked' => array_search('unsubscribe', $actions), 'id' => "$base_id-action_unsubscribe", 'name' => "{$base_name}[actions][]", 'type' => 'checkbox', 'value' => 'unsubscribe']); ?> />
					<label <?php echo self::html_attributes(['for' => "$base_id-action_unsubscribe"]); ?>><?php _e('Unsubscribe from mailing list', 'zohowp-pmpro'); ?></label>
				</div>
			</div>
		</div>
<?php
	}

	private static function available_user_fields()
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
}
