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
		$id = $args['key'];
		$value = empty($options[$id]) ? false : $options[$id];
		$attributes = self::html_attributes([
			'name' => "zohowp_pmpro_fields[$id]",
			'value' => $value
		]);
	?>
		<select <?php echo $attributes; ?>>
			<option value=''><?php _e('Select source', 'zohowp-pmpro'); ?></option>
		</select>
	<?php
	}

	public static function levels_section()
	{
	?>
		<p><?php _e('Assign membership levels to email lists.', 'zohowp-pmpro'); ?></p>
		<script>
			jQuery(document).ready(function() {
				// select list on change, toggle details section
			});
		</script>
	<?php
	}

	public static function levels_field($args)
	{
		$options = get_option('zohowp_pmpro_levels');
		$id = $args['level'];
		$value = empty($options[$id]) ? '' : $options[$id];
		$attributes = self::html_attributes([
			'name' => "zohowp_pmpro_levels[$id]",
			'value' => $value
		]);

	?>
		<select <?php echo $attributes; ?>>
			<option value=''><?php _e('Select a list', 'zohowp-pmpro'); ?></option>
			<?php
			$lists = \ZohoWP\API\Campaigns::get_mailing_lists();
			foreach ($lists as &$list) {
			?>
				<option value='<?php echo $list['listkey']; ?>'><?php echo $list['listname']; ?></option>
			<?php
			}
			?>
		</select>
		<div id='zohowp_pmpro_level_<?php echo $id; ?>_details' style='display: none;'>
			<h4>Triggers</h4>
			<ul>
				<li>Membership level change</li>
				<li>Order added or updated</li>
			</ul>
			<h4>Allowed Actions</h4>
			<ul>
				<li>Membership level change</li>
				<li>Order added or updated</li>
			</ul>
		</div>
<?php
	}
}
