<?php

/**
 * Display the page where the slugs could be regenerated or replaced
 */
class link_sawing_Pro_Addons
{

	public function __construct()
	{
		add_action('init', array($this, 'init'), 9);
	}

	/**
	 * Register hooks used to change the Link Sawing UI
	 */
	public function init()
	{
		add_filter('link_sawing_sections', array($this, 'add_admin_section'), 5);

		// Stop Words
		add_action('admin_init', array($this, 'save_stop_words'), 9);

		add_filter('link_sawing_tools_fields', array($this, 'filter_tools_fields'), 9, 2);
		add_filter('link_sawing_settings_fields', array($this, 'filter_settings_fields'), 9);
	}

	/**
	 * Activate the fields disabled in free version
	 *
	 * @param array $fields
	 * @param string $subsection
	 *
	 * @return array
	 */
	public function filter_tools_fields($fields, $subsection)
	{
		unset($fields['content_type']['disabled']);
		unset($fields['content_type']['pro']);
		unset($fields['taxonomies']['pro']);
		unset($fields['ids']['disabled']);
		unset($fields['ids']['pro']);

		return $fields;
	}

	/**
	 * Add support for taxonomies in Bulk URI Editor & allow import from "Custom Permalinks" plugin
	 *
	 * @param array $admin_sections
	 *
	 * @return array
	 */
	public function add_admin_section($admin_sections)
	{
		// Add "Stop words" subsection for "Tools"
		$admin_sections['tools']['subsections']['stop_words']['function'] = array('class' => 'link_sawing_Pro_Addons', 'method' => 'stop_words_output');

		// Display Permalinks for all selected taxonomies
		if (!empty($admin_sections['uri_editor']['subsections'])) {
			foreach ($admin_sections['uri_editor']['subsections'] as &$subsection) {
				if (isset($subsection['pro'])) {
					$subsection['function'] = array('class' => 'link_sawing_Tax_Uri_Editor_Table', 'method' => 'display_admin_section');
					unset($subsection['html']);
				}
			}
		}

		// Add "Support" section
		// $admin_sections['support'] = array(
		// 	'name'     => __('Support', 'link-sawing'),
		// 	'function' => array('class' => 'link_sawing_Pro_Addons', 'method' => 'support_output')
		// );

		// Import support
		$admin_sections['tools']['subsections']['import']['function'] = array('class' => 'link_sawing_Pro_Addons', 'method' => 'import_output');

		return $admin_sections;
	}

	/**
	 * Filter the fields displayed in the "Settings" tab
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	public function filter_settings_fields($fields)
	{
		// Network licence key (multisite)
		$license_key     = link_sawing_Pro_Functions::get_license_key();
		$expiration_info = link_sawing_Pro_Functions::get_expiration_date();

		// 1. licence key
		// $fields['licence'] = array(
		// 	'section_name' => __('Licence', 'link-sawing'),
		// 	'container'    => 'row',
		// 	'fields'       => array(
		// 		'licence_key' => array(
		// 			'type'              => 'text',
		// 			'value'             => $license_key,
		// 			'label'             => __('Licence key', 'link-sawing'),
		// 			'after_description' => sprintf('')
		// 		)
		// 	)
		// );

		if (defined('PMP_LICENCE_KEY') || defined('PMP_LICENSE_KEY')) {
			$fields['licence']['fields']['licence_key']['readonly'] = true;
		}

		// 2. Unblock some fields
		unset($fields['redirect']['fields']['setup_redirects']['pro']);
		unset($fields['redirect']['fields']['setup_redirects']['disabled']);
		unset($fields['redirect']['fields']['extra_redirects']['pro']);
		unset($fields['redirect']['fields']['extra_redirects']['disabled']);

		return $fields;
	}

	/**
	 * Add "Stop words" subsection
	 *
	 * @return string
	 */
	public function stop_words_output()
	{
		global $link_sawing_options;

		// Fix the escaped quotes
		$words_list = (!empty($link_sawing_options['stop-words']['stop-words-list'])) ? stripslashes($link_sawing_options['stop-words']['stop-words-list']) : "";

		// Get stop-words languages
		$languages = array_merge(array('' => __('-- از لیست کلمات از پیش تعریف شده استفاده کنید --', 'link-sawing')), link_sawing_Pro_Functions::load_stop_words_languages());

		$buttons = "<table class=\"stop-words-buttons\"><tr>";
		$buttons .= sprintf("<td><a href=\"#\" class=\"clear_all_words button button-small\">%s</a></td>", __("حذف تمامی کلمات", "link-sawing"));
		$buttons .= sprintf("<td>%s<td>", link_sawing_Admin_Functions::generate_option_field("load_stop_words", array("type" => "select", "input_class" => "widefat small-select load_stop_words", "choices" => $languages)));
		$buttons .= sprintf("<td>%s</td>", get_submit_button(__('اضافه کردن کلمات از لیست', 'link-sawing'), 'button-small button-primary', 'load_stop_words_button', false));
		$buttons .= "</tr></table>";

		$fields = apply_filters('link_sawing_tools_fields', array(
			'stop-words' => array(
				'container' => 'row',
				'fields'    => array(
					'stop-words-enable' => array(
						'label'       => __('فعال سازی "توقف کلمه"', 'link-sawing'),
						'type'        => 'single_checkbox',
						'container'   => 'row',
						'input_class' => 'enable_stop_words'
					),
					'stop-words-list'   => array(
						'label'             => __('لیست "توقف کلمه"', 'link-sawing'),
						'type'              => 'textarea',
						'container'         => 'row',
						'value'             => $words_list,
						'description'       => __('برای جدا سازی کلمات از کاما استفاده کنید.', 'link-sawing'),
						'input_class'       => 'widefat stop_words',
						'after_description' => $buttons
					)
				)
			)
		), 'stop_words');

		$sidebar = '<h3>' . __('دستورالعمل ها', 'link-sawing') . '</h3>';
		$sidebar .= wpautop(__('در صورت فعال سازی، تمامی کلمات متوقف شده به صورت خودکار از لیست آدرس های پیش فرض حذف می شوند.', 'link-sawing'));
		$sidebar .= wpautop(__('تمامی کلمات قابلیت حذف و اضاه شدن به لیست را دارند ، همچنین از بین 21 زبان نیر می توانید آنها را انتخاب کنید.', 'link-sawing'));

		return link_sawing_Admin_Functions::get_the_form($fields, '', array('text' => __('ذخیره', 'link-sawing'), 'class' => 'primary margin-top'), $sidebar, array('action' => 'link-sawing', 'name' => 'save_stop_words'), true);
	}

	/**
	 * Saves the user-defined stop words list
	 */
	public function save_stop_words()
	{
		if (isset($_POST['stop-words']) && wp_verify_nonce($_POST['save_stop_words'], 'link-sawing')) {
			link_sawing_Actions::save_settings('stop-words', $_POST['stop-words']);
		}
	}



	/**
	 * Add "Support" section
	 *
	 * @return string
	 */
	public function support_output()
	{

		// return $output;
	}

	/**
	 * Return the HTML output of "Custom Redirects" panel in URI Editor
	 *
	 * @return string
	 */
	public static function display_redirect_form($element_id)
	{
		global $link_sawing_redirects, $link_sawing_options, $link_sawing_external_redirects;

		// Do not trigger if "Extra redirects" option is turned off
		if (empty($link_sawing_options['general']['redirect']) || empty($link_sawing_options['general']['extra_redirects'])) {
			return __('جهت فعال سازی این قابلیت گزینه "ریدایرکت های اضافی" را از بخش تنظیمات این افزونه فعال کنید.', 'link-sawing');
		}

		// 1. Extra redirects
		$html = "<div class=\"single-section\">";

		$html .= sprintf("<p><label for=\"auto_auri\" class=\"strong\">%s %s</label></p>", __("ریدایرکت های اضافی", "link-sawing"), link_sawing_Admin_Functions::help_tooltip(__("تمامی آدرس هایی که در پایین مشخص شدن، کاربرها را به آدرس های سفارشی بالا ریدایرگت می کنند.", "link-sawing")));

		$html .= "<table>";
		// 1A. Sample row
		$html .= sprintf("<tr class=\"sample-row\"><td>%s</td><td>%s</td></tr>", link_sawing_Admin_Functions::generate_option_field("link-sawing-redirects", array("input_class" => "widefat", "value" => "", 'extra_atts' => "data-index=\"\"", "placeholder" => __('نمونه/ آدرس-سفارشی', 'link-sawing'))), "<a href=\"#\" class=\"remove-redirect\"><span class=\"dashicons dashicons-no\"></span></a>");

		// 1B. Rows with redirects
		if (!empty($link_sawing_redirects[$element_id]) && is_array($link_sawing_redirects[$element_id])) {
			foreach ($link_sawing_redirects[$element_id] as $index => $redirect) {
				$html .= sprintf("<tr><td>%s</td><td>%s</td></tr>", link_sawing_Admin_Functions::generate_option_field("link-sawing-redirects[{$index}]", array("input_class" => "widefat", "value" => $redirect, 'extra_atts' => "data-index=\"{$index}\"")), "<a href=\"#\" class=\"remove-redirect\"><span class=\"dashicons dashicons-no\"></span></a>");
			}
		}
		$html .= "</table>";

		// 1C. Add new redirect button
		$html .= sprintf("<button type=\"button\" class=\"button button-small hide-if-no-js\" id=\"link-sawing-new-redirect\">%s</button>", __("اضافه کردن ریدایرکت جدید", "link-sawing"));

		// 1D. Description
		$html .= "<div class=\"redirects-panel-description\">";
		$html .= sprintf(wpautop(__("<strong>لظفا فقط از آدرس ها استفاده کنید!</strong><br />برای نمونه, جهت پیاده سازی ریدایرکت برای  <code>%s/old-uri</code> لطفا از <code>old-uri</code> استفاده کنید.", "link-sawing")), home_url());
		$html .= "</div>";

		$html .= "</div>";

		// 2. Extra redirects
		$html .= "<div class=\"single-section\">";

		$html .= sprintf("<p><label for=\"auto_auri\" class=\"strong\">%s %s</label></p>", __("این صفحه را به یک لینک خارجی ریدایرکت کن.", "link-sawing"), link_sawing_Admin_Functions::help_tooltip(__("اگر خالی نباشد، در زمانی که کاربر شما تلاش می کند به این لینک مراجعه کند به لینک مشخص شده(پایین) ریدایرکت می شود.", "link-sawing")));

		$external_redirect_url = (!empty($link_sawing_external_redirects[$element_id])) ? $link_sawing_external_redirects[$element_id] : "";
		$html                  .= link_sawing_Admin_Functions::generate_option_field("link-sawing-external-redirect", array("input_class" => "widefat", "value" => urldecode($external_redirect_url), "placeholder" => __("http://another-website.com/final-target-url", "link-sawing")));

		// 2B. Description
		$html .= "<div class=\"redirects-panel-description\">";
		$html .= wpautop(__("<strong>لطفا آدرسها را به شکل کامل استفاده کنید!</strong><br />برای نمونه, <code>http://another-website.com/final-target-url</code>.", "link-sawing"));
		$html .= "</div>";

		$html .= "</div>";

		return $html;
	}
}
