<?php

/**
 * Display Bulk URI Editor
 */
class link_sawing_Uri_Editor
{
	public $this_section = 'uri_editor';

	public function __construct()
	{
		add_filter('link_sawing_sections', array($this, 'add_admin_section'), 0);
		add_filter('screen_settings', array($this, 'screen_options'), 99, 2);
	}

	/**
	 * Add a new section to the Link Sawing UI
	 *
	 * @param array $admin_sections
	 *
	 * @return array
	 */
	public function add_admin_section($admin_sections)
	{
		$admin_sections[$this->this_section] = array(
			'name' => __('ویرایش آدرس ها', 'link-sawing')
		);

		// Display separate section for each post type
		$post_types = link_sawing_Helper_Functions::get_post_types_array('full');
		foreach ($post_types as $post_type_name => $post_type) {
			// Check if post type exists
			if (!post_type_exists($post_type_name)) {
				continue;
			}

			$icon = (class_exists('WooCommerce') && $post_type_name == 'product') ? "<i class=\"woocommerce-icon woocommerce-cart\"></i>" : "";

			$admin_sections[$this->this_section]['subsections'][$post_type_name] = array(
				'name'     => "{$icon} {$post_type['label']}",
				'function' => array('class' => 'link_sawing_URI_Editor_Post', 'method' => 'display_admin_section')
			);
		}

		// Link Sawing Pro: Display separate section for each taxonomy
		$taxonomies = link_sawing_Helper_Functions::get_taxonomies_array('full');
		foreach ($taxonomies as $taxonomy_name => $taxonomy) {
			// Check if taxonomy exists
			if (!taxonomy_exists($taxonomy_name)) {
				continue;
			}

			// Get the icon
			$icon = (class_exists('WooCommerce') && in_array($taxonomy_name, array('product_tag', 'product_cat'))) ? "<i class=\"woocommerce-icon woocommerce-cart\"></i>" : "<i class=\"dashicons dashicons-tag\"></i>";

			$admin_sections[$this->this_section]['subsections']["tax_{$taxonomy_name}"] = array(
				'name' => "{$icon} {$taxonomy['label']}",
				'html' => link_sawing_Admin_Functions::pro_text(),
				'pro'  => true
			);
		}

		// A little dirty hack to move wooCommerce product & taxonomies to the end of array
		if (class_exists('WooCommerce')) {
			foreach (array('product', 'tax_product_tag', 'tax_product_cat') as $section_name) {
				if (empty($admin_sections[$this->this_section]['subsections'][$section_name])) {
					continue;
				}
				$section = $admin_sections[$this->this_section]['subsections'][$section_name];
				unset($admin_sections[$this->this_section]['subsections'][$section_name]);
				$admin_sections[$this->this_section]['subsections'][$section_name] = $section;
			}
		}

		return $admin_sections;
	}

	/**
	 * Display "Screen options"
	 *
	 * @param string $html
	 * @param string $screen
	 *
	 * @return string
	 */
	public function screen_options($html, $screen)
	{
		global $active_section;

		// Display the screen options only in "Permalink Editor"
		if ($active_section != $this->this_section) {
			return $html;
		}

		$button = get_submit_button(__('اعمال', 'link-sawing'), 'primary', 'screen-options-apply', false);
		$html   = "<fieldset class=\"link-sawing-screen-options\">";

		$screen_options = array(
			'per_page'      => array(
				'type'        => 'number',
				'label'       => __('هر صفحه', 'link-sawing'),
				'input_class' => 'settings-select'
			),
			'post_statuses' => array(
				'type'         => 'checkbox',
				'label'        => __('وضعیت های نوشته', 'link-sawing'),
				'choices'      => get_post_statuses(),
				'select_all'   => '',
				'unselect_all' => '',
			)
		);

		foreach ($screen_options as $field_name => $field_args) {
			$field_args['container'] = 'screen-options';
			$html                    .= link_sawing_Admin_Functions::generate_option_field("screen-options[{$field_name}]", $field_args);
		}

		$html .= sprintf("</fieldset>%s", $button);

		return $html;
	}
}
