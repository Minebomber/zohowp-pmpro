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
		// Not ideal
		// Needs:
		// users in level added to list
		// controls on how it's done
		// training class will need to change for new members, not all
		// yearly membership needs to be linked to users level change events
		// can have list of 'links' that connect level to zoho list, each with options for timing, filtering, etc
		self::register_setting('zohowp_pmpro_levels', ['type' => 'array', 'default' => []]);
		self::add_section(
			'memberships',
			__('Memberships', 'zohowp-pmpro'),
			'memberships_section'
		);

		$all_levels = pmpro_getAllLevels(true, true);
		foreach ($all_levels as $id => &$level) {
			self::add_field(
				"level_$id",
				$level->name,
				'levels_field',
				'memberships',
				['level' => $level->id]
			);
		}
	}

	public static function memberships_section()
	{
?>
		<p>
			Assign membership levels to email lists
		</p>
	<?php
	}

	public static function levels_field($args)
	{
		$options = get_option('zohowp_pmpro_levels');
		$id = $args['level'];
		$value = isset($options[$id]) ? $options[$id] : '';
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
<?php
	}
}
