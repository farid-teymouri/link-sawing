<?php

/**
 * Display the page where the slugs could be regenerated or replaced
 */
class link_sawing_Debug
{

	public function __construct()
	{
		add_filter('link_sawing_sections', array($this, 'add_debug_section'), 4);
	}

	/**
	 * Add a new section to the Link Sawing UI
	 *
	 * @param array $admin_sections
	 *
	 * @return array
	 */
	public function add_debug_section($admin_sections)
	{
		// $admin_sections['debug'] = array(
		// 	'name'     => __('Debug', 'link-sawing'),
		// 	'function' => array('class' => 'link_sawing_Debug', 'method' => 'output')
		// );

		return $admin_sections;
	}

	/**
	 * Get a URL pointing to the "Debug" tab in Link Sawing UI
	 *
	 * @param string $field
	 *
	 * @return string
	 */
	public function get_remove_settings_url($field = '')
	{
		return add_query_arg(array(
			'section'                           => 'debug',
			'remove-link-sawing-settings' => $field,
			'link-sawing-nonce'           => wp_create_nonce('link-sawing')
		), link_sawing_Admin_Functions::get_admin_url());
	}

	/**
	 * Define and display HTML output of a new section with the "Debug" data
	 *
	 * @return string
	 */
	public function output()
	{
		global $link_sawing_options, $link_sawing_uris, $link_sawing_permastructs, $link_sawing_redirects, $link_sawing_external_redirects, $wpdb;

		// Count permalinks and calculate the size of array
		if (is_array($link_sawing_uris)) {
			$link_sawing_uris_size  = $wpdb->get_var("SELECT CHAR_LENGTH(option_value) FROM {$wpdb->options} WHERE option_name = 'link-sawing-uris'");
			$link_sawing_uris_size  = (is_numeric(($link_sawing_uris_size))) ? round($link_sawing_uris_size / 1024, 2) . __('kb', 'link-sawing') : '-';
			$link_sawing_uris_count = count($link_sawing_uris);

			$link_sawing_uris_stats = sprintf(__('شمارش پیوند های سفارشی : <strong>%s</strong> | Custom permalinks array size in DB: <strong>%s</strong>', 'link-sawing'), $link_sawing_uris_count, $link_sawing_uris_size);
		} else {
			$link_sawing_uris_stats = __('آرایه مربوط به پیوند های سفارشی در دیتابیش ذخیره نمی شود.', 'link-sawing');
		}

		$sections_and_fields = apply_filters('link_sawing_debug_fields', array(
			'debug-data' => array(
				'section_name' => __('اطلاعات دیباگ', 'link-sawing'),
				'fields'       => array(
					'uris'               => array(
						'type'        => 'textarea',
						'description' => sprintf('%s<br />%s<br /><strong><a class="pm-confirm-action" href="%s">%s</a></strong>', __('لیست آدرس هایی که با این افزونه تولید شده.', 'link-sawing'), $link_sawing_uris_stats, $this->get_remove_settings_url('uris'), __('حذف تمامی پیوند های سفارشی', 'link-sawing')),
						'label'       => __('آرایه با آدرس ها', 'link-sawing'),
						'input_class' => 'short-textarea widefat',
						'value'       => ($link_sawing_uris) ? print_r($link_sawing_uris, true) : ''
					),
					'custom-redirects'   => array(
						'type'        => 'textarea',
						'description' => sprintf('%s<br /><strong><a class="pm-confirm-action" href="%s">%s</a></strong>', __('لیست تمامی ریدایرکت های تولید شده توسط این افزونه.', 'link-sawing'), $this->get_remove_settings_url('redirects'), __('حذف تمامی ریدایرکت های سفارشی', 'link-sawing')),
						'label'       => __('آرایه با ریدایرکت ها', 'link-sawing'),
						'input_class' => 'short-textarea widefat',
						'value'       => ($link_sawing_redirects) ? print_r($link_sawing_redirects, true) : ''
					),
					'external-redirects' => array(
						'type'        => 'textarea',
						'description' => sprintf('%s<br /><strong><a class="pm-confirm-action" href="%s">%s</a></strong>', __('لیست تمامی ریدایرکت های خارجی تنظیم شده با این افزونه.', 'link-sawing'), $this->get_remove_settings_url('external-redirects'), __('حذف تمامی ریدایرکت های خارجی', 'link-sawing')),
						'label'       => __('آرایه با ریدایرکت های خارجی', 'link-sawing'),
						'input_class' => 'short-textarea widefat',
						'value'       => ($link_sawing_external_redirects) ? print_r(array_filter($link_sawing_external_redirects), true) : ''
					),
					'permastructs'       => array(
						'type'        => 'textarea',
						'description' => sprintf('%s<br /><strong><a class="pm-confirm-action" href="%s">%s</a></strong>', __('لیست تمامی ساختار لینک هایی که با این افزونه تولید شده..', 'link-sawing'), $this->get_remove_settings_url('permastructs'), __('حذف تنظیمات تمامی ساختار لینک ها', 'link-sawing')),
						'label'       => __('آرایه با ساختار لینک ها', 'link-sawing'),
						'input_class' => 'short-textarea widefat',
						'value'       => ($link_sawing_permastructs) ? print_r($link_sawing_permastructs, true) : ''
					),
					'settings'           => array(
						'type'        => 'textarea',
						'description' => sprintf('%s<br /><strong><a class="pm-confirm-action" href="%s">%s</a></strong>', __('لیست تمامی تنظیمات افزونه', 'link-sawing'), $this->get_remove_settings_url('settings'), __('حذف تمامی تنظیمات افزونه', 'link-sawing')),
						'label'       => __('آرایه با تنظیمات استفاده شده در پلاگین.', 'link-sawing'),
						'input_class' => 'short-textarea widefat',
						'value'       => print_r($link_sawing_options, true)
					)
				)
			)
		));

		// Now get the HTML output
		$output = '';
		foreach ($sections_and_fields as $section_id => $section) {
			$output .= (isset($section['section_name'])) ? "<h3>{$section['section_name']}</h3>" : "";
			$output .= (isset($section['description'])) ? "<p class=\"description\">{$section['description']}</p>" : "";
			$output .= "<table class=\"form-table fixed-table\">";

			// Loop through all fields assigned to this section
			foreach ($section['fields'] as $field_id => $field) {
				$field_name         = "{$section_id}[$field_id]";
				$field['container'] = 'row';

				$output .= link_sawing_Admin_Functions::generate_option_field($field_name, $field);
			}

			// End the section
			$output .= "</table>";
		}

		// return $output;
	}
}
