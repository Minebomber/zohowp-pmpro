<?php

namespace ZohoWP\Elementor;

if (!defined('ABSPATH') || !defined('ZOHOWP_DIR_PATH')) exit;

require_once ZOHOWP_DIR_PATH . '/includes/api/campaigns.php';

/**
 * Elementor Zoho add contact form action.
 *
 * @since 1.0.0
 */
class Subscribe_Action extends \ElementorPro\Modules\Forms\Classes\Action_Base
{
	/**
	 * Get action name.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string
	 */
	public function get_name()
	{
		return 'zohowp-subscribe';
	}

	/**
	 * Get action label.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string
	 */
	public function get_label()
	{
		return esc_html__('ZohoWP Subscribe', 'zohowp-elementor');
	}

	/**
	 * Run action.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
	 */
	public function run($record, $ajax_handler)
	{
		$settings = $record->get('form_settings');
		if (empty($settings['zohowp_subscribe_list'])) {
			$ajax_handler->add_error_message(__('ZohoWP: Missing Zoho mailing list key.', 'zohowp-elementor'));
			return;
		}

		$schema = \ZohoWP\API\Campaigns::get_all_fields();
		if ($schema === false || !is_array($schema)) {
			$ajax_handler->add_error_message(__('ZohoWP: Failed to fetch Zoho field schema.', 'zohowp-elementor'));
			return;
		}

		// Get submitted form data.
		$raw_fields = $record->get('fields');
		// Normalize form data.
		$formdata = [];
		foreach ($raw_fields as $id => $data) {
			$formdata[$id] = $data['value'];
		}

		$fieldmap = [];
		foreach ($schema as $zohofield) {
			$no = $zohofield['no'];
			$setting = $settings["zohowp_subscribe_field_$no"];
			if ($zohofield['IS_MANDATORY'] === true && empty($setting)) {
				$ajax_handler->add_error_message(__('ZohoWP: Missing required field mapping.', 'zohowp-elementor'));
				return;
			}
			if (!empty($setting)) {
				// Custom string split
				if (strpos($setting, '|') !== false) {
					list($field_id, $index) = array_map('trim', explode('|', $setting));
					if (empty($field_id) || ($index !== '0' && empty($index))) {
						$ajax_handler->add_error_message(__('ZohoWP: Invalid field ID.', 'zohowp-elementor'));
					}
					$values = preg_split('/[ ]/', $formdata[$field_id], 2);
					$index = intval($index);
					if (isset($values[$index])) {
						$value = $values[$index];
					} else {
						$value = '';
					}
				} else {
					// No split just use value
					$value = $formdata[$setting];
				}
				// Save in fieldmap
				$fieldmap[$zohofield['DISPLAY_NAME']] = $value;
			}
		}
		$result = \ZohoWP\API\Campaigns::subscribe($settings['zohowp_subscribe_list'], $fieldmap);
		if ($result['code'] !== '0') {
			$ajax_handler->add_error_message('ZohoWP: ' . $result['message'] ?? __('Submission error.', 'zohowp-elementor'));
		}
	}

	/**
	 * Register action controls.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param \Elementor\Widget_Base $widget
	 */
	public function register_settings_section($widget)
	{
		$widget->start_controls_section(
			'section_zohowp_subscribe',
			[
				'label' => esc_html__('ZohoWP Subscribe', 'zohowp-elementor'),
				'condition' => [
					'submit_actions' => $this->get_name(),
				],
			]
		);

		// Mailing list select
		$lists = \ZohoWP\API\Campaigns::get_mailing_lists();
		$list_options = ['' => 'Select'];
		if ($lists !== false && is_array($lists)) {
			foreach ($lists as $list) {
				$list_options[$list['listkey']] = $list['listname'];
			}
		}
		$widget->add_control(
			'zohowp_subscribe_list',
			[
				'label' => esc_html__('Mailing List', 'zohowp-elementor'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options' => $list_options,
			]
		);

		// Contact field mapping
		// Field introspection is not documented, fill IDs manually
		// Allow syntax id|n - Split value of field by space, get nth item
		$fields = \ZohoWP\API\Campaigns::get_all_fields();
		if ($fields !== false && is_array($fields)) {
			foreach ($fields as $field) {
				$no = $field['no'];
				$name = $field['DISPLAY_NAME'];
				$required = $field['IS_MANDATORY'];
				$widget->add_control(
					"zohowp_subscribe_field_$no",
					[
						'label' => $name . ($required ? '*' : ''),
						'type' => \Elementor\Controls_Manager::TEXT,
						'placeholder' => __('Field ID', 'zohowp-elementor'),
					]
				);
			}
		}

		$widget->end_controls_section();
	}

	/**
	 * On export.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param array $element
	 */
	public function on_export($element)
	{
		return array_filter($element, function ($key) {
			return strpos($key, 'zohowp_subscribe_') !== 0;
		}, ARRAY_FILTER_USE_KEY);
	}
}
