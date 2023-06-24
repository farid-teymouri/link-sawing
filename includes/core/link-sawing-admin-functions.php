<?php

/**
 * Additional functions related to WordPress Admin Dashboard UI
 */
class link_sawing_Admin_Functions
{

	public $menu_name, $sections, $active_section, $active_subsection;

	public function __construct()
	{
		add_action('admin_menu', array($this, 'add_menu_page'));
		add_action('admin_init', array($this, 'init'));
		add_action('admin_bar_menu', array($this, 'fix_customize_url'), 41);

		add_action('admin_notices', array($this, 'display_plugin_notices'));
		add_action('admin_notices', array($this, 'display_global_notices'));

		add_filter('default_hidden_columns', array($this, 'quick_edit_hide_column'), 99);
	}

	/**
	 * Hooks that should be triggered with "admin_init"
	 */
	public function init()
	{
		// Additional links in "Plugins" page
		add_filter("plugin_action_links_" . link_sawing_BASENAME, array($this, "plugins_page_links"));
		add_filter("plugin_row_meta", array($this, "plugins_page_meta"), 10, 2);

		// Detect current section
		$this->sections = apply_filters('link_sawing_sections', array());
		$this->get_current_section();
	}

	/**
	 * Use the native URL for "Customize" button in the admin bar
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	public function fix_customize_url($wp_admin_bar)
	{
		global $link_sawing_ignore_permalink_filters;

		$object    = get_queried_object();
		$customize = $wp_admin_bar->get_node('customize');

		if (empty($customize->href)) {
			return;
		}

		$link_sawing_ignore_permalink_filters = true;
		if (!empty($object->ID)) {
			$new_url = get_permalink($object->ID);
		}
		$link_sawing_ignore_permalink_filters = false;

		if (!empty($new_url)) {
			$new_url       = urlencode_deep($new_url);
			$customize_url = preg_replace('/url=([^&]+)/', "url={$new_url}", $customize->href);

			$wp_admin_bar->add_node(array(
				'id'   => 'customize',
				'href' => $customize_url,
			));
		}
	}

	/**
	 * Get current section of Link Sawing admin panel
	 */
	public function get_current_section()
	{
		global $active_section, $active_subsection, $current_admin_tax;

		// 1. Get current section
		if (isset($_GET['page']) && $_GET['page'] == link_sawing_PLUGIN_SLUG) {
			if (isset($_POST['section'])) {
				$this->active_section = sanitize_title_with_dashes($_POST['section']);
			} else if (isset($_GET['section'])) {
				$this->active_section = sanitize_title_with_dashes($_GET['section']);
			} else {
				$sections_names       = array_keys($this->sections);
				$this->active_section = $sections_names[0];
			}
		}

		// 2. Get current subsection
		if ($this->active_section && isset($this->sections[$this->active_section]['subsections'])) {
			if (isset($_POST['subsection'])) {
				$this->active_subsection = sanitize_title_with_dashes($_POST['subsection']);
			} else if (isset($_GET['subsection'])) {
				$this->active_subsection = sanitize_title_with_dashes($_GET['subsection']);
			} else {
				$subsections_names       = array_keys($this->sections[$this->active_section]['subsections']);
				$this->active_subsection = $subsections_names[0];
			}
		}

		// 3. Check if current admin page is related to taxonomies
		if (!empty($this->active_subsection) && substr($this->active_subsection, 0, 4) == 'tax_') {
			$current_admin_tax = substr($this->active_subsection, 4, strlen($this->active_subsection));
		} else {
			$current_admin_tax = false;
		}

		// Set globals
		$active_section    = $this->active_section;
		$active_subsection = $this->active_subsection;
	}

	/**
	 * Add "Tools -> Link Sawing" to the admin sidebar menu
	 */
	public function add_menu_page()
	{
		$this->menu_name = add_management_page(__('Link Sawing', 'link-sawing'), __('Link Sawing', 'link-sawing'), 'manage_options', link_sawing_PLUGIN_SLUG, array($this, 'display_section'));

		add_action('admin_init', array($this, 'enqueue_styles'));
		add_action('admin_init', array($this, 'enqueue_scripts'));
	}

	/**
	 * Register the CSS files for the plugin's dashboard
	 */
	public function enqueue_styles()
	{
		wp_enqueue_style('link-sawing-plugins', link_sawing_URL . '/out/link-sawing-plugins.css', array(), link_sawing_VERSION);
		wp_enqueue_style('link-sawing', link_sawing_URL . '/out/link-sawing-admin.css', array('link-sawing-plugins'), link_sawing_VERSION);
	}

	/**
	 * Register the JavaScript files for the plugin's dashboard.
	 */
	public function enqueue_scripts()
	{
		wp_enqueue_script('link-sawing-plugins', link_sawing_URL . '/out/link-sawing-plugins.js', array('jquery',), link_sawing_VERSION);
		wp_enqueue_script('link-sawing', link_sawing_URL . '/out/link-sawing-admin.js', array('jquery', 'link-sawing-plugins'), link_sawing_VERSION);

		wp_localize_script('link-sawing', 'link_sawing', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'url'      => link_sawing_URL,
			'confirm'  => __('آیا مطمعن هستید؟ این عمل قابل لغو نیست!', 'link-sawing'),
			'spinners' => admin_url('images')
		));
	}

	/**
	 * Get the URL of the plugin's dashboard
	 *
	 * @param string $append
	 *
	 * @return string
	 */
	public static function get_admin_url($append = '')
	{
		//return menu_page_url(link_sawing_PLUGIN_SLUG, false) . $append;
		$admin_page = sprintf("tools.php?page=%s", link_sawing_PLUGIN_SLUG . $append);

		return admin_url($admin_page);
	}

	/**
	 * Add shortcut links for Link Sawing on "Plugins" page
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public function plugins_page_links($links)
	{
		$new_links = array(
			sprintf('<a href="%s">%s</a>', $this->get_admin_url(), __('ویرایش آدرس ها', 'link-sawing')),
			sprintf('<a href="%s">%s</a>', $this->get_admin_url('&section=settings'), __('تنظیمات', 'link-sawing')),
		);

		return array_merge($links, $new_links);
	}

	/**
	 * Add shortcut meta links for Link Sawing on "Plugins" page
	 *
	 * @param array $links
	 * @param string $file
	 *
	 * @return array
	 */
	public function plugins_page_meta($links, $file)
	{
		if ($file == link_sawing_BASENAME) {

			$new_links = array();
			// if (!defined('link_sawing_PRO')) {
			// 	$new_links['upgrade'] = sprintf('<a href="%s?utm_source=plugin_admin_page" target="_blank"><strong>%s</strong></a>', link_sawing_WEBSITE, __('Buy Link Sawing Pro', 'link-sawing'));
			// }

			$links = array_merge($links, $new_links);
		}

		return $links;
	}

	/**
	 * Generate the option field
	 *
	 * @param $input_name
	 * @param $args
	 *
	 * @return string
	 */
	static public function generate_option_field($input_name, $args)
	{
		global $link_sawing_options, $link_sawing_permastructs;

		// Reset $fields variables
		$fields = '';

		// Allow to filter the $args
		$args = apply_filters('link_sawing_field_args', $args, $input_name);

		$field_type     = (isset($args['type'])) ? $args['type'] : 'text';
		$default        = (isset($args['default'])) ? $args['default'] : '';
		$label          = (isset($args['label'])) ? $args['label'] : '';
		$rows           = (isset($args['rows'])) ? "rows=\"{$args['rows']}\"" : "rows=\"5\"";
		$description    = (isset($args['before_description'])) ? $args['before_description'] : "";
		$description    .= (isset($args['description'])) ? "<p class=\"field-description description\">{$args['description']}</p>" : "";
		$description    .= (isset($args['after_description'])) ? $args['after_description'] : "";
		$description    .= (isset($args['pro'])) ? sprintf("<p class=\"field-description description alert info\">%s</p>", (self::pro_text(true))) : "";
		$append_content = (isset($args['append_content'])) ? "{$args['append_content']}" : "";

		// Input attributes
		$input_atts = (isset($args['input_class'])) ? "class='{$args['input_class']}'" : '';
		$input_atts .= (isset($args['readonly'])) ? " readonly='readonly'" : '';
		$input_atts .= (isset($args['disabled'])) ? " disabled='disabled'" : '';
		$input_atts .= (isset($args['placeholder'])) ? " placeholder='{$args['placeholder']}'" : '';
		$input_atts .= (isset($args['extra_atts'])) ? " {$args['extra_atts']}" : '';

		// Display the field if the related class exists
		if (!empty($args['class_exists'])) {
			$related_classes       = (array) $args['class_exists'];
			$related_classes_exist = 0;

			foreach ($related_classes as $related_class) {
				if (class_exists($related_class)) {
					$related_classes_exist = 1;
					break;
				}
			}

			// Do not display if the related class it not found
			if (empty($related_classes_exist)) {
				$field_type = $args['container_class'] = 'hidden';
			}
		}

		// Check the container classes
		$container_class = (isset($args['container_class'])) ? " class=\"{$args['container_class']} field-container\"" : " class=\"field-container\"";

		// Get the field value (if it is not set in $args)
		if (isset($args['value']) && !empty($args['value'])) {
			$value = $args['value'];
		} else {
			// Extract the section and field name from $input_name
			preg_match('/([^\[]+)(?:\[([^\[]+)\])(?:\[([^\[]+)\])?/', $input_name, $field_section_and_name);

			if ($field_section_and_name) {
				$section_name = $field_section_and_name[1];
				$field_name   = $field_section_and_name[2];

				if (!empty($field_section_and_name[3])) {
					$subsection_name = $field_section_and_name[3];
					$value           = (isset($link_sawing_options[$section_name][$field_name][$subsection_name])) ? $link_sawing_options[$section_name][$field_name][$subsection_name] : $default;
				} else {
					$value = (isset($link_sawing_options[$section_name][$field_name])) ? $link_sawing_options[$section_name][$field_name] : $default;
				}
			} else {
				$value = (isset($link_sawing_options[$input_name])) ? $link_sawing_options[$input_name] : $default;
			}
		}

		switch ($field_type) {
			case 'checkbox':
				$fields .= '<div class="checkboxes">';
				foreach ($args['choices'] as $choice_value => $choice) {
					$input_template = "<label for='%s[]'><input type='checkbox' %s value='%s' name='%s[]' %s /> %s</label>";

					if (empty($choice['label']) && is_array($choice)) {
						if (in_array($choice_value, array('post_types', 'taxonomies'))) {
							$group_labels = array('post_types' => __('انواع نوشته', 'link-sawing'), 'taxonomies' => __('طبقه بندی ها', 'link-sawing'));
							$fields       .= sprintf('<p>%s</p>', $group_labels[$choice_value]);
						}

						foreach ($choice as $sub_choice_value => $sub_choice) {
							$label = (!empty($sub_choice['label'])) ? $sub_choice['label'] : $sub_choice;
							$atts  = (!empty($value[$choice_value]) && in_array($sub_choice_value, $value[$choice_value])) ? "checked='checked'" : "";
							$atts  .= (!empty($sub_choice['atts'])) ? " {$sub_choice['atts']}" : "";

							$fields .= sprintf($input_template, $input_name, $input_atts, $sub_choice_value, "{$input_name}[{$choice_value}]", $atts, $label);
						}
					} else {

						$label = (!empty($choice['label'])) ? $choice['label'] : $choice;
						$atts  = (is_array($value) && in_array($choice_value, $value)) ? "checked='checked'" : "";
						$atts  .= (!empty($choice['atts'])) ? " {$choice['atts']}" : "";

						$fields .= sprintf($input_template, $input_name, $input_atts, $choice_value, $input_name, $atts, $label);
					}
				}
				$fields .= '</div>';

				// Add helper checkboxes for bulk actions
				if (isset($args['select_all']) || isset($args['unselect_all'])) {
					$select_all_label   = (!empty($args['select_all'])) ? $args['select_all'] : __('انتخاب همه', 'link-sawing');
					$unselect_all_label = (!empty($args['unselect_all'])) ? $args['unselect_all'] : __('لغو انتخاب همه', 'link-sawing');

					$fields .= "<p class=\"checkbox_actions extra-links\">";
					$fields .= (isset($args['select_all'])) ? "<a href=\"#\" class=\"select_all\">{$select_all_label}</a>&nbsp;" : "";
					$fields .= (isset($args['unselect_all'])) ? "<a href=\"#\" class=\"unselect_all\">{$unselect_all_label}</a>" : "";
					$fields .= "</p>";
				}
				break;

			case 'single_checkbox':
				$fields .= '<div class="single_checkbox">';
				if (is_array($value)) {
					$input_key = preg_replace('/(.*)(?:\[([^\[]+)\])$/', '$2', $input_name);
					$checked   = (!empty($value[$input_key])) ? "checked='checked'" : "";
				} else {
					$checked = ($value == 1) ? "checked='checked'" : "";
				}
				$checkbox_label = (isset($args['checkbox_label'])) ? $args['checkbox_label'] : '';

				$fields .= "<input type='hidden' {$input_atts} value='0' name='{$input_name}' />";
				$fields .= "<label for='{$input_name}'><input type='checkbox' {$input_atts} value='1' name='{$input_name}' {$checked} /> {$checkbox_label}</label>";
				$fields .= '</div>';
				break;

			case 'radio':
				$fields .= '<div class="radios">';
				foreach ($args['choices'] as $choice_value => $choice) {
					$label = (is_array($choice)) ? $choice['label'] : $choice;
					$atts  = ($choice_value == $value) ? "checked='checked'" : "";
					$atts  .= (!empty($choice['atts'])) ? " {$choice['atts']}" : "";

					$fields .= "<label for='{$input_name}[]'><input type='radio' {$input_atts} value='{$choice_value}' name='{$input_name}[]' {$atts} /> {$label}</label>";
				}
				$fields .= '</div>';
				break;

			case 'select':
				$fields .= '<span class="select">';
				$fields .= "<select name='{$input_name}' {$input_atts}>";
				foreach ($args['choices'] as $choice_value => $choice) {
					$label = (is_array($choice)) ? $choice['label'] : $choice;
					$atts  = ($choice_value == $value) ? "selected='selected'" : "";
					$atts  .= (!empty($choice['atts'])) ? " {$choice['atts']}" : "";

					if ($choice == '---') {
						$fields .= "<option disabled=\"disabled\">------------------</option>";
					} else {
						$fields .= "<option value='{$choice_value}' {$atts}>{$label}</option>";
					}
				}
				$fields .= '</select>';
				$fields .= '</span>';
				break;

			case 'number':
				$fields .= "<input type='number' {$input_atts} value='{$value}' name='{$input_name}' />";
				break;

			case 'hidden':
				$fields .= "<input type='hidden' {$input_atts} value='{$value}' name='{$input_name}' />";
				break;

			case 'textarea':
				$fields .= "<textarea {$input_atts} name='{$input_name}' {$rows}>{$value}</textarea>";
				break;

			case 'pre':
				$fields .= "<pre {$input_atts}>{$value}</pre>";
				break;

			case 'info':
				$fields .= "<div {$input_atts}>{$value}</div>";
				break;

			case 'clearfix':
				return "<div class=\"clearfix\"></div>";

			case 'permastruct':
				$siteurl = link_sawing_Helper_Functions::get_permalink_base();

				if (!empty($args['post_type'])) {
					$type         = $args['post_type'];
					$type_name    = $type['name'];
					$content_type = 'post_types';

					$permastructures = (!empty($link_sawing_permastructs['post_types'])) ? $link_sawing_permastructs['post_types'] : array();
				} else if (!empty($args['taxonomy'])) {
					$type         = $args['taxonomy'];
					$type_name    = $type['name'];
					$content_type = "taxonomies";

					$permastructures = (!empty($link_sawing_permastructs['taxonomies'])) ? $link_sawing_permastructs['taxonomies'] : array();
				} else {
					break;
				}

				// Get permastructures
				$default_permastruct = trim(link_sawing_Helper_Functions::get_default_permastruct($type_name), "/");
				$current_permastruct = isset($permastructures[$type_name]) ? $permastructures[$type_name] : $default_permastruct;

				// Append extra attributes
				$input_atts .= " data-default=\"{$default_permastruct}\"";
				$input_atts .= " placeholder=\"{$default_permastruct}\"";
				$input_atts .= (!class_exists('link_sawing_URI_Functions_Tax') && !empty($args['taxonomy'])) ? " disabled=\"disabled\"" : "";

				$fields .= "<div class=\"all-permastruct-container\">";

				// 1. Default permastructure
				$fields .= "<div class=\"permastruct-container\">";
				$fields .= "<span><code>{$siteurl}/</code></span>";
				$fields .= "<span><input type='text' {$input_atts} value='{$current_permastruct}' name='{$input_name}'/></span>";
				$fields .= "</div>";

				$fields .= "<div class=\"permastruct-toggle\">";

				// 2A. Permastructure for each language
				$languages = link_sawing_Language_Plugins::get_all_languages(true);
				if ($languages) {
					$fields .= sprintf("<h4>%s</h4><p class=\"permastruct-instruction\">%s</p>", __("ترجمه های ساختار", "link-sawing"), __("اگر می‌خواهید ساختارهای دائمی را ترجمه کنید و ساختار پیوند دائمی متفاوتی را برای هر زبان تنظیم کنید، لطفاً فیلدهای زیر را پر کنید. در غیر این صورت، مجموعه ساختار دائمی برای زبان پیش‌فرض (به قسمت بالا مراجعه کنید) اعمال خواهد شد.", "link-sawing"));

					foreach ($languages as $lang => $name) {
						$current_lang_permastruct = isset($permastructures["{$type_name}_{$lang}"]) ? $permastructures["{$type_name}_{$lang}"] : '';
						$lang_siteurl             = link_sawing_Language_Plugins::prepend_lang_prefix($siteurl, '', $lang);

						$fields .= "<label>{$name}</label>";
						$fields .= "<div class=\"permastruct-container\">";
						$fields .= "<span><code>{$lang_siteurl}/</code></span>";
						$fields .= sprintf("<span><input type='text' %s value='%s' name='%s'/></span>", $input_atts, $current_lang_permastruct, str_replace("]", "_{$lang}]", $input_name));
						$fields .= "</div>";
					}
				}

				// 2B. Restore default permalinks
				$fields .= sprintf("<p class=\"default-permastruct-row columns-container\"><span class=\"column-2_4\"><strong>%s:</strong> %s</span><span class=\"column-2_4\"><a href=\"#\" class=\"restore-default\"><span class=\"dashicons dashicons-image-rotate\"></span> %s</a></span></p>", __("ساختار پیش فرض ", "link-sawing"), esc_html($default_permastruct), __("بازیابی ساختار پیش فرض ", "link-sawing"));

				// 2B. Do not auto-append slug field
				$fields .= sprintf("<h4>%s</h4><div class=\"settings-container\">%s</div>", __("تنظیمات ساختار", "link-sawing"), self::generate_option_field("permastructure-settings[do_not_append_slug][$content_type][{$type_name}]", array('type' => 'single_checkbox', 'checkbox_label' => __("به طور خودکار Slug را اضافه نکنید.", "link-sawing"))));

				$fields .= "</div>";

				// 3. Show toggle button
				$fields .= sprintf("<p class=\"permastruct-toggle-button\"><a href=\"#\"><span class=\"dashicons dashicons-admin-settings\"></span> %s</a></p>", __("نمایش تنظیمات اضافی", "link-sawing"));

				$fields .= "</div>";

				break;

			default:
				$fields .= "<input type='text' {$input_atts} value='{$value}' name='{$input_name}'/>";
		}

		// Get the final HTML output
		if (isset($args['container']) && $args['container'] == 'tools') {
			$html = "<div{$container_class}>";
			$html .= "<h4>{$label}</h4>";
			$html .= "<div class='{$input_name}-container'>{$fields}</div>";
			$html .= $description;
			$html .= $append_content;
			$html .= "</div>";
		} else if (isset($args['container']) && $args['container'] == 'row') {
			$html = sprintf("<tr id=\"%s\" data-field=\"%s\" %s>", esc_attr(preg_replace('/(?:.*\[)(.*)(?:\].*)/', '$1', $input_name)), $input_name, $container_class);
			$html .= sprintf("<th><label class='ui right pointing large label' style=\"padding: 2rem 1rem; line-height: 20px;\"  for=\"%s\">%s</label></th>", $input_name, $args['label']);
			$html .= sprintf("<td><fieldset class='ui placeholder black segment' style=\"max-width: 1024px;\">%s%s</fieldset></td>", $fields, $description);
			$html .= "</tr>";
			$html .= ($append_content) ? "<tr class=\"appended-row\"><td colspan=\"2\">{$append_content}</td></tr>" : "";
		} else if (isset($args['container']) && $args['container'] == 'screen-options') {
			$html = "<fieldset data-field=\"{$input_name}\" {$container_class}><legend>{$args['label']}</legend>";
			$html .= "<div class=\"field-content\">{$fields}{$description}</div>";
			$html .= ($append_content) ? "<div class=\"appended-row\">{$append_content}</div>" : "";
			$html .= "</fieldset>";
		} else {
			$html = $fields . $append_content;
		}

		return apply_filters('link_sawing_field_output', $html);
	}

	/**
	 * Display hidden field to indicate posts or taxonomies admin sections
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	static public function section_type_field($type = 'post')
	{
		return self::generate_option_field('content_type', array('value' => $type, 'type' => 'hidden'));
	}

	/**
	 * Display the HTML output of form for provided fields array
	 *
	 * @param array $fields
	 * @param string $container
	 * @param array $button
	 * @param string $sidebar
	 * @param array $nonce
	 * @param bool $wrap
	 * @param string $form_class
	 *
	 * @return string
	 */
	static public function get_the_form($fields = array(), $container = '', $button = array(), $sidebar = '', $nonce = array(), $wrap = false, $form_class = '')
	{
		// 1. Check if the content will be displayed in columns and button details
		switch ($container) {
			case 'columns-3':
				$wrapper_class     = 'columns-container';
				$form_column_class = 'column column-2_3';
				$sidebar_class     = 'column column-1_3';
				break;

			case 'tabs':
				$wrapper_class = 'form settings-tabs';
				$sidebar_class = $form_column_class = '';
				break;

				// there will be more cases in the future ...
			default:
				$sidebar_class = 'sidebar';
				$wrapper_class = $form_column_class = '';
		}

		// 2. Process the array with button and nonce field settings
		$button_text       = (!empty($button['text'])) ? $button['text'] : '';
		$button_class      = (!empty($button['class'])) ? $button['class'] : '';
		$button_attributes = (!empty($button['attributes'])) ? $button['attributes'] : '';
		$nonce_action      = (!empty($nonce['action'])) ? $nonce['action'] : '';
		$nonce_name        = (!empty($nonce['name'])) ? $nonce['name'] : '';
		$form_classes      = (!empty($form_class)) ? $form_class : '';

		// 3. Now get the HTML output (start section row container)
		$html = ($wrapper_class) ? "<div class=\"{$wrapper_class}\">" : '';

		// 4. Display settings tabs
		if ($container == 'tabs') {
			// Get active section
			$active_tab = (!empty($_POST['pm_active_tab'])) ? $_POST['pm_active_tab'] : key(array_slice($fields, 0, 1, true));

			$html .= "<div class=\"ui secondary pointing menu subsubsub \" style=\"overflow: initial;margin-top: 30px;margin-bottom: 25px;\">";
			foreach ($fields as $tab_name => $tab) {
				$active_class = ($active_tab === $tab_name) ? 'current active blue' : '';
				if ($tab_name == "general") $inner =  "تنظیمات عمومی";
				if ($tab_name == "redirect") $inner =  "ریدایرکت ها";
				if ($tab_name == "third_parties") $inner =  "همگام با افزونه‌های دیگر";
				if ($tab_name == "advanced") $inner =  "تنظیمات پیشرفته";
				if ($tab_name == "licence") $inner =  "لایسنس";
				$html         .= sprintf("<a href=\"%s\" class=\"%s item\" data-tab=\"%s\">$inner</a>", "#pm_tab_{$tab_name}", $active_class, $tab_name, $tab['section_name']);
			}
			$html .= "</div>";
		}
		// if ($container == 'tabs') {
		// 	// Get active section
		// 	$active_tab = (!empty($_POST['pm_active_tab'])) ? $_POST['pm_active_tab'] : key(array_slice($fields, 0, 1, true));

		// 	$html .= "<div class=\"ui secondary pointing menu\" style=\"margin-top:30px;margin-bottom:25px;\">";
		// 	foreach ($fields as $tab_name => $tab) {
		// 		$active_class = ($active_tab === $tab_name) ? 'active blue current' : '';
		// 		echo $tab_name . ',';
		// 		if ($tab_name == "general") $inner =  "تنظیمات عمومی";
		// 		if ($tab_name == "redirect") $inner =  "ریدایرکت ها";
		// 		if ($tab_name == "third_parties") $inner =  "";
		// 		if ($tab_name == "advanced") $inner =  "";
		// 		if ($tab_name == "licence") $inner =  "";
		// 		$html         .= sprintf("<a href=\"%s\" class=\"item %s\" data-tab=\"%s\">%s</a>", "#pm_tab_{$tab_name}", $active_class, $tab_name, $tab['section_name']);
		// 	}
		// 	$html .= "</div>";
		// }

		// 5. Display some notes
		if ($sidebar_class && $sidebar) {
			$html .= "<div class=\"{$sidebar_class}\">";
			$html .= "<div class=\"section-notes\">";
			$html .= $sidebar;
			$html .= "</div>";
			$html .= "</div>";
		}

		// 6. Start fields' section
		$html .= ($form_column_class) ? "<div class=\"{$form_column_class}\">" : "";
		$html .= "<form method=\"POST\" class=\"{$form_classes}\">";
		$html .= ($wrap) ? "<table class=\"form-table\">" : "";

		// 7. Loop through all fields assigned to this section
		foreach ($fields as $field_name => $field) {
			$tab_name   = (isset($field['fields'])) ? $field_name : '';
			$field_name = (!empty($field['name'])) ? $field['name'] : $field_name;

			// A. Display table row
			if (isset($field['container']) && $field['container'] == 'row') {
				$row_output = "";

				// Loop through all fields assigned to this section
				if (isset($field['fields'])) {
					foreach ($field['fields'] as $section_field_id => $section_field) {
						$section_field_name         = (!empty($section_field['name'])) ? $section_field['name'] : "{$field_name}[$section_field_id]";
						$section_field['container'] = 'row';

						$row_output .= self::generate_option_field($section_field_name, $section_field);
					}
				} else {
					$row_output .= self::generate_option_field($field_name, $field);
				}

				if (isset($field['section_name'])) {
					if ($container == 'tabs') {
						$is_active_tab = (!empty($active_tab) && $active_tab == $tab_name) ? 'class="active-tab"' : '';

						$html .= "<div id=\"pm_{$tab_name}\" data-tab=\"{$tab_name}\" {$is_active_tab}>";
					}

					$html .= "<h3>{$field['section_name']}</h3>";
					$html .= (isset($field['append_content'])) ? $field['append_content'] : "";
					$html .= (isset($field['description'])) ? "<p class=\"description\">{$field['description']}</p>" : "";
					$html .= "<table class=\"form-table\" data-field=\"{$field_name}\">{$row_output}</table>";
					$html .= ($container == 'tabs') ? "</div>" : "";
				} else {
					$html .= $row_output;
				}
			} // B. Display single field
			else {
				$html .= self::generate_option_field($field_name, $field);
			}
		}

		$html .= ($wrap) ? "</table>" : "";

		// 8. Add a hidden field with section name for settings page
		if ($container == 'tabs' && !empty($active_tab)) {
			$html .= self::generate_option_field('pm_active_tab', array('value' => $active_tab, 'type' => 'hidden', 'readonly' => true));
		}

		// 9. End the fields' section + add button & nonce fields
		if ($nonce_action && $nonce_name) {
			$html .= wp_nonce_field($nonce_action, $nonce_name);
			$html .= self::generate_option_field('pm_session_id', array('value' => uniqid(), 'type' => 'hidden'));
		}
		$html .= ($button_text) ? get_submit_button($button_text, $button_class, '', false, $button_attributes) : "";
		$html .= '</form>';
		$html .= ($form_column_class) ? "</div>" : "";

		// 10. End the section row container
		$html .= ($wrapper_class) ? "</div>" : "";

		return $html;
	}

	/**
	 * Display the plugin sections
	 */
	public function display_section()
	{
		global $link_sawing_after_sections_html;
		$html = '<link rel="stylesheet" href="' . plugin_dir_url(__FILE__) . '/semantic-ui/semantic.rtl.min.css">';





		$html .= "<div id=\"link-sawing\" class=\"wrap ui segment\" style='max-width:1300px;margin:5rem auto;'>";
		$html .= sprintf("<h2 id=\"plugin-name-heading\">
		 <a href=\"https://www.linkedin.com/in/farid-teymouri/\" class=\"author-link\" target=\"_blank\">توسعه دهنده این افزونه را دنبال کنید.</a></h2>",);

		// Display the tab navigation
		$html .= "<div id=\"link-sawing-tab-nav\" class=\"ui four item menu \" style=\"max-width: 900px;\">";
		foreach ($this->sections as $section_name => $section_properties) {
			$active_class = ($this->active_section === $section_name) ? 'active blue nav-tab-active nav-tab' : 'nav-tab';
			$section_url  = $this->get_admin_url("&section={$section_name}");

			$html .= sprintf("<a href=\"%s\" class=\"item %s section_%s\" style=\"box-shadow: none;border: 0;margin: 0;\">%s</a>", $section_url, $active_class, $section_name, $section_properties['name']);
		}

		// Upgrade to Pro version

		$html .= "</div>";

		// Now display the active section
		$html                 .= "<div id=\"link-sawing-sections\">";
		$active_section_array = (isset($this->sections[$this->active_section])) ? $this->sections[$this->active_section] : "";

		// Display additional navigation for subsections
		if (isset($this->sections[$this->active_section]['subsections'])) {
			// $html .= "<ul class=\"subsubsub\"> ";

			$html .= "<div class=\"ui secondary pointing menu\" style=\"margin-top: 30px;margin-bottom: 25px;\">";
			foreach ($this->sections[$this->active_section]['subsections'] as $subsection_name => $subsection) {
				$active_class   = ($this->active_subsection === $subsection_name) ? 'current' : '';
				$subsection_url = $this->get_admin_url("&section={$this->active_section}&subsection={$subsection_name}");
				if ($subsection_name == "post") $inner =  'نوشته ها';
				if ($subsection_name == "page") $inner =  "برگه ها";
				if ($subsection_name == "attachment") $inner =  "مدیا و گرافیک";
				if ($subsection_name  == "tax_category") $inner =  "دسته بندی ها";
				if ($subsection_name == "tax_post_tag") $inner =  "برچسب ها";
				if ($subsection_name == "duplicates") $inner =  " پیوند های تکراری یا Duplicates";
				if ($subsection_name == "find_and_replace") $inner =  "پیدا کن و جایگزین کن";
				if ($subsection_name == "regenerate_slugs") $inner =  "بازسازی slug و ریست کردن";
				if ($subsection_name == "stop_words") $inner =  "کلمات را فیلتر کنید";
				if ($subsection_name == "import") $inner =  "";

				$acl = $active_class == "current" ? "active blue" : "";
				if ($subsection_name != "import") $html .= "<a href=\"{$subsection_url}\" class=\"item {$acl}\">{$inner}</a>";
			}
			$html .= "</div>";
		}

		// A. Execute the function assigned to the subsection
		if (isset($active_section_array['subsections'][$this->active_subsection]['function'])) {
			$class_name     = $active_section_array['subsections'][$this->active_subsection]['function']['class'];
			$section_object = new $class_name();

			$section_content = call_user_func(array($section_object, $active_section_array['subsections'][$this->active_subsection]['function']['method']));
		} // B. Execute the function assigned to the section
		else if (isset($active_section_array['function'])) {
			$class_name     = $active_section_array['function']['class'];
			$section_object = new $class_name();

			$section_content = call_user_func(array($section_object, $active_section_array['function']['method']));
		} // C. Display the raw HTMl output of subsection
		else if (isset($active_section_array['subsections'][$this->active_subsection]['html'])) {
			$section_content = (isset($active_section_array['subsections'][$this->active_subsection]['html'])) ? $active_section_array['subsections'][$this->active_subsection]['html'] : "";
		} // D. Try to display the raw HTMl output of section
		else {
			$section_content = (isset($active_section_array['html'])) ? $active_section_array['html'] : "";
		}

		$html .= "<div class=\"single-section\" data-section=\"{$this->active_section}\" id=\"{$this->active_section}\">{$section_content}</div>";
		$html .= "</div>";

		// Display alerts and another content if needed and close .wrap container
		$html .= $link_sawing_after_sections_html;
		$html .= "</div>";
		// $html .= '<script
		// src="https://cdn.rtlcss.com/semantic-ui/2.4.1/semantic.min.js"
		// integrity="sha384-6urqf2sgCGDfIXcoxTUOVIoQV+jFr/Zuc4O2wCRS6Rnd8w0OJ17C4Oo3PuXu8ZtF"
		// crossorigin="anonymous"></script>';
		echo $html;
	}

	/**
	 * Display the array or HTML table with updated slugs after one of the actions is triggered
	 *
	 * @param array $updated_array
	 * @param bool $return_array
	 * @param bool $display_full_table
	 *
	 * @return array|string
	 */
	static function display_updated_slugs($updated_array, $return_array = false, $display_full_table = true)
	{
		global $link_sawing_before_sections_html, $adjust_id_url_filter_off;

		$updated_slugs_count = 0;
		$html                = $main_content = $alert = "";

		// Disable "Adjust IDs for multilingual functionality" in WPML to make sure that the correct URLs are displayed in the results table
		$adjust_id_url_filter_off = true;

		if (is_array($updated_array)) {
			// Check if slugs should be displayed
			$first_slug = reset($updated_array);
			$show_slugs = (!empty($_POST['mode']) && $_POST['mode'] == 'slugs') ? true : false;

			$header_footer = '<tr>';
			$header_footer .= sprintf('<th class="column-primary">%s</th>', __('عنوان', 'link-sawing'));
			if ($show_slugs) {
				$header_footer .= (isset($first_slug['old_slug'])) ? sprintf('<th>%s</th>', __('نامک/Slug قدیمی', 'link-sawing')) : '';
				$header_footer .= (isset($first_slug['new_slug'])) ? sprintf('<th>%s</th>', __('نامک/Slug جدید', 'link-sawing')) : '';
			} else {
				$header_footer .= sprintf('<th>%s</th>', __('آدرس قدیمی', 'link-sawing'));
				$header_footer .= sprintf('<th>%s</th>', __('آدرس جدید', 'link-sawing'));
			}
			$header_footer .= '</tr>';

			$screen_reader_button = sprintf('<button type="button" class="toggle-row"><span class="screen-reader-text">%s</span></button>', __('نمایش بیشتر جزئیات', 'link-sawing'));

			foreach ($updated_array as $row) {
				// Odd/even class
				$updated_slugs_count++;
				$alternate_class = ($updated_slugs_count % 2 == 1) ? ' class="alternate"' : '';

				// Taxonomy
				if (!empty($row['tax'])) {
					$term_link = get_term_link(intval($row['ID']), $row['tax']);
					$permalink = (is_wp_error($term_link)) ? "-" : $term_link;
				} else {
					$permalink = get_permalink($row['ID']);
				}

				// Decode permalink
				$permalink = rawurldecode(rawurldecode($permalink));

				$main_content .= sprintf('<tr data-id="%s" %s>', $row['ID'], $alternate_class);
				$main_content .= sprintf('<td class="row-title column-primary" data-colname="%s">%s<a target="_blank" href="%s"><span class="small">%s</span></a> %s</td>', __('عنوان', 'link-sawing'), sanitize_text_field($row['item_title']), $permalink, $permalink, $screen_reader_button);

				if ($show_slugs) {
					$main_content .= (isset($row['old_slug'])) ? sprintf('<td data-colname="%s">%s</td>', __('نامک/Slug قدیمی', 'link-sawing'), rawurldecode($row['old_slug'])) : "";
					$main_content .= (isset($row['new_slug'])) ? sprintf('<td data-colname="%s">%s</td>', __('نامک/Slug جدید', 'link-sawing'), rawurldecode($row['new_slug'])) : "";
				} else {
					$main_content .= sprintf('<td data-colname="%s">%s</td>', __('آدرس قدیمی', 'link-sawing'), rawurldecode($row['old_uri']));
					$main_content .= sprintf('<td data-colname="%s">%s</td>', __('آدرس جدید', 'link-sawing'), rawurldecode($row['new_uri']));
				}
				$main_content .= '</tr>';
			}

			// Merge header, footer and content
			if ($display_full_table) {
				$html = sprintf('<h3 id="updated-list">%s</h3>', __('لیست موارد به روز رسانی شده', 'link-sawing'));
				$html .= '<table class="widefat wp-list-table updated-slugs-table">';
				$html .= sprintf('<thead>%s</thead><tbody>%s</tbody><tfoot>%s</tfoot>', $header_footer, $main_content, $header_footer);
			} else {
				$html = $main_content;
			}

			$html .= '</table>';
		}

		// 3. Display the alert
		if (isset($updated_slugs_count)) {
			if ($updated_slugs_count > 0) {
				$alert_content = sprintf(_n('<strong class="updated_count">%d</strong> slug was updated!', '<strong class="updated_count">%d</strong> slugs were updated!', $updated_slugs_count, 'link-sawing'), $updated_slugs_count) . ' ';
				$alert_content .= sprintf(__('جهت ورود به Slug های به روز رسانی شده در <a %s> اینجا کلیک</a> کنید.', 'link-sawing'), "href=\"#updated-list\"");

				$alert = self::get_alert_message($alert_content, 'updated updated_slugs');
			} else {
				$alert = self::get_alert_message(__('هیچ <strong>Slug</strong> ای به روز رسانی نشد', 'link-sawing'), 'error updated_slugs');
			}
		}

		if ($return_array) {
			return array(
				'html'  => $html,
				'alert' => $alert
			);
		} else {
			$link_sawing_before_sections_html .= $alert;

			return $html;
		}
	}

	/**
	 * Check if URI Editor should be displayed for current user
	 *
	 * @return bool
	 */
	public static function current_user_can_edit_uris()
	{
		global $link_sawing_options;

		$edit_uris_cap = (!empty($link_sawing_options['general']['edit_uris_cap'])) ? $link_sawing_options['general']['edit_uris_cap'] : 'publish_posts';

		return current_user_can($edit_uris_cap);
	}

	/**
	 * "Quick Edit" Box
	 *
	 * @return string
	 */
	public static function quick_edit_column_form()
	{
		// Check the user capabilities
		if (self::current_user_can_edit_uris() === false) {
			return '';
		}

		$html = self::generate_option_field('link-sawing-quick-edit', array('value' => true, 'type' => 'hidden'));
		$html .= '<fieldset class="inline-edit-permalink">';
		$html .= sprintf("<legend class=\"inline-edit-legend\">%s</legend>", __("Link Sawing", "link-sawing"));

		$html .= '<div class="inline-edit-col">';
		$html .= sprintf("<label class=\"inline-edit-group\"><span class=\"title\">%s</span><span class=\"input-text-wrap\">%s</span></label>", __("آدرس فعلی", "link-sawing"), self::generate_option_field("custom_uri", array("input_class" => "custom_uri", "value" => '')));
		$html .= "</div>";

		$html .= "</fieldset>";

		// Append nonce field & element ID
		$html .= self::generate_option_field("link-sawing-edit-uri-element-id", array("type" => "hidden", "input_class" => "link-sawing-edit-uri-element-id", "value" => ""));
		$html .= wp_nonce_field('link-sawing-edit-uri-box', 'link-sawing-nonce', true, false);

		return $html;
	}

	/**
	 * Hide "Custom URI" column
	 *
	 * @param array $hidden
	 *
	 * @return array
	 */
	function quick_edit_hide_column($hidden)
	{
		$hidden[] = 'link-sawing-col';

		return $hidden;
	}

	/**
	 * Get the HTML output of URI Editor
	 *
	 * @param WP_Post|WP_Term $element
	 * @param bool $gutenberg
	 *
	 * @return string
	 */
	public static function display_uri_box($element, $gutenberg = false)
	{
		global $link_sawing_options;

		// Check the user capabilities
		if (self::current_user_can_edit_uris() === false) {
			return '';
		}

		if (!empty($element->ID)) {
			$id                = $element_id = $element->ID;
			$native_slug       = $element->post_name;
			$is_draft          = (!empty($element->post_status) && (in_array($element->post_status, array('draft', 'auto-draft')))) ? true : false;
			$is_draft_excluded = link_sawing_Helper_Functions::is_draft_excluded($element);
			$is_front_page     = link_sawing_Helper_Functions::is_front_page($id);

			$auto_update_val = get_post_meta($id, "auto_update_uri", true);

			// Get URIs
			$uri         = link_sawing_URI_Functions_Post::get_post_uri($id, true, $is_draft);
			$default_uri = link_sawing_URI_Functions_Post::get_default_post_uri($id);
			$native_uri  = link_sawing_URI_Functions_Post::get_default_post_uri($id, true);
		} else if (class_exists('link_sawing_URI_Functions_Tax')) {
			$id          = $element->term_id;
			$element_id  = "tax-{$id}";
			$native_slug = $element->slug;

			$auto_update_val = get_term_meta($id, "auto_update_uri", true);

			// Get URIs
			$uri         = link_sawing_URI_Functions_Tax::get_term_uri($element->term_id, true);
			$default_uri = link_sawing_URI_Functions_Tax::get_default_term_uri($element->term_id);
			$native_uri  = link_sawing_URI_Functions_Tax::get_default_term_uri($element->term_id, true);
		} else {
			return '';
		}
		//<a href="%s" target="_blank">تنظیمات</a>
		//("<strong>به استثنا پست های پیش نویس</strong>")
		// If the draft is excluded do not display the contents of URI Editor
		if (!empty($is_draft_excluded)) {
			$alert = sprintf(__('جهت ویرایش به پیوند سفارشی به بخش  <a href="%s" target="_blank">تنظیمات</a> رفته و با انتخاب گزینه ("<strong>به استثنا پست های پیش نویس و معلق</strong>") قابلیت ویرایش پیوند را فعال کنید، در غیر این صورت وضعیت این نوشته شرایط ویرایش را محدود کرده.', 'link-sawing'), self::get_admin_url('&section=settings'));

			$html = (!$gutenberg) ? "<div class=\"link-sawing-edit-uri-box\">" : "<div class=\"link-sawing-gutenberg link-sawing-edit-uri-box\">";
			$html .= sprintf('<p class="uri_locked">%s</p>', $alert);
			$html .= "</div>";
		} else {
			// Auto-update settings
			$auto_update_def_val = $link_sawing_options["general"]["auto_update_uris"];

			if ($auto_update_def_val == 1) {
				$auto_update_def_label = __("به روز رسانی خودکار \"آدرس فعلی\"", "link-sawing");
			} else if ($auto_update_def_val == 2) {
				$auto_update_def_label = __("از انجام ذخیره/ساختن پیوند سفارشی خودداری کنید.", "link-sawing");
			} else {
				$auto_update_def_label = __("جلوگیری از به روز رسانی خودکار \"آدرس فعلی\"", "link-sawing");
			}

			$auto_update_choices = array(
				0   => array("label" => sprintf(__("استفاده از تنظیمات عمومی [%s]", "link-sawing"), $auto_update_def_label), "atts" => "data-readonly=\"{$auto_update_def_val}\""),
				10  => '---',
				-1 => array("label" => __("جلوگیری از به روز رسانی خودکار \"آدرس فعلی\"", "link-sawing"), "atts" => "data-readonly=\"0\""),
				-2 => array("label" => __("جلوگیری از به روز رسانی خودکار \"آدرس فعلی\" و  کناره گیری از ابزار \"بازنشانی/بازیابی\"", "link-sawing"), "atts" => "data-readonly=\"0\""),
				1   => array("label" => __("به روز رسانی خودکار \"آدرس فعلی\"", "link-sawing"), "atts" => "data-readonly=\"1\""),
				11  => '---',
				2   => array("label" => __("محدودیت ساخت/ذخیره لینک سفارشی (امکان ایجاد تغییرات در URI را ممنوع کنید)", "link-sawing"), "atts" => "data-readonly=\"2\""),
			);

			// Decode default URI
			$default_uri = rawurldecode($default_uri);

			// Start HTML output
			// 1. Button
			if (!$gutenberg) {
				$html = sprintf("<span><button type=\"button\" class=\"button button-small hide-if-no-js\" id=\"link-sawing-toggle\">%s</button></span>", __("Link Sawing", "link-sawing"));

				$html .= "<div id=\"link-sawing\" class=\"postbox link-sawing-edit-uri-box\" style=\"display: none;\">";

				// 2. The heading
				$html .= "<a class=\"close-button\"><span class=\"screen-reader-text\">" . __("بستن : ", "link-sawing") . __("Link Sawing", "link-sawing") . "</span><span class=\"close-icon\" aria-hidden=\"false\"></span></a>";
				$html .= sprintf("<h2><span>%s</span></h2>", __("Link Sawing", "link-sawing"));

				// 3. The fields container [start]
				$html .= "<div class=\"inside\">";
			} else {
				$html = "<div class=\"link-sawing-gutenberg link-sawing-edit-uri-box\">";
			}

			// 4. Custom URI
			if (!empty($is_front_page)) {
				$custom_uri_field = self::generate_option_field("custom_uri", array("type" => "hidden", "extra_atts" => "data-default=\"{$default_uri}\" data-element-id=\"{$element_id}\"", "input_class" => "widefat custom_uri", "value" => rawurldecode($uri)));
				$custom_uri_field .= __("امکان ویرایش آدرس سفارشی در صفحه اول میسر نیست.", "link-sawing");
			} else {
				$custom_uri_field = self::generate_option_field("custom_uri", array("extra_atts" => "data-default=\"{$default_uri}\" data-element-id=\"{$element_id}\"", "input_class" => "widefat custom_uri", "value" => rawurldecode($uri)));
				$custom_uri_field .= sprintf('<p class="uri_locked hidden">%s %s</p>', '<span class="dashicons dashicons-lock"></span>', __('لینک بالا فقط قابل خواندن است، برای ویرایش آن "<strong>حالت به روز رسانی آدرس ها</strong> را به <em> جلوگیری از به روز رسانی خودکار آدرس ها </em> تغییر دهید."', 'link-sawing'));
			}
			$html .= sprintf("<div class=\"custom_uri_container\"><p><label for=\"custom_uri\" class=\"strong\">%s</label></p><span>%s</span><span class=\"duplicated_uri_alert\"></span></div>", __("آدرس فعلی", "link-sawing"), $custom_uri_field);

			// 5. Auto-update URI
			if (empty($is_front_page)) {
				if (!empty($auto_update_choices)) {
					$html .= sprintf("<div><p><label for=\"auto_auri\" class=\"strong\">%s %s</label></p><span>%s</span></div>", __("حالت به روز رسانی آدرس ها", "link-sawing"), self::help_tooltip(__("اگر 'حالت به روز رسانی خودکار' برای آدرس ها فعال باشد، بعد از ذخیره یا به روز رسانی نوشته فیلد مورد نظر به صورت خودکار به 'آدرس پیش فرض' تغییر می کند (در پایین قابل مشاهده است) ", "link-sawing")), self::generate_option_field("auto_update_uri", array("type" => "select", "input_class" => "widefat auto_update", "value" => $auto_update_val, "choices" => $auto_update_choices)));
				}
			}

			// 6. Native slug
			if (!empty($element->ID) && !empty($link_sawing_options["general"]["show_native_slug_field"])) {
				$native_slug_field = self::generate_option_field("native_slug", array("extra_atts" => "data-default=\"{$native_slug}\" data-element-id=\"{$element_id}\"", "input_class" => "widefat native_slug", "value" => rawurldecode($native_slug)));

				$html .= sprintf("<div class=\"native_slug_container\"><p><label for=\"native_slug\" class=\"strong\">%s %s</label></p><span>%s</span></div>", __("نامک/Slug طبیعی", "link-sawing"), self::help_tooltip(__("اگر این افزونه غیر فعال باشد ، به طور پیش فرس Slug ها به شکل طبیعی در پیوند ها استفاده می شوند.", "link-sawing")), $native_slug_field);
			}

			if (empty($is_front_page)) {
				// 7. Default URI
				$html .= sprintf("<div class=\"default-permalink-row columns-container\"><span class=\"column-3_4\"><strong>%s:</strong> %s</span><span class=\"column-1_4\"><a href=\"#\" class=\"restore-default\"><span class=\"dashicons dashicons-image-rotate\"></span> %s</a></span></div>", __("ساختار پیش فرض ", "link-sawing"), esc_html($default_uri), __("بازیابی ساختار پیش فرض", "link-sawing"));

				// 8. Native URI info
				if (!empty($link_sawing_options['general']['redirect']) && !(!empty($element->post_status) && in_array($element->post_status, array('auto-draft', 'trash', 'draft')))) {
					$native_permalink = trim(link_sawing_Helper_Functions::get_permalink_base($element), "/") . "/";
					$native_permalink .= $native_uri;

					$html .= sprintf("<div class=\"default-permalink-row columns-container\"><span><strong>%s</strong> <a href=\"%s\">%s</a></span></div>", __("فعال کردن به روز رسانی خودکار برای آدرس های طبیعی :", "link-sawing"), $native_permalink, rawurldecode($native_uri));
				}
			}

			// 9. Custom redirects
			$html .= ($element->ID) ? self::display_redirect_panel($id) : self::display_redirect_panel("tax-{$id}");

			// 10. Extra save button for Gutenberg
			if ($gutenberg) {
				$html .= sprintf("<div class=\"default-permalink-row save-row columns-container hidden\"><div><a href=\"#\" class=\"button button-primary\" id=\"link-sawing-save-button\">%s</a></div></div>", __("ذخیره پیوند", "link-sawing"));
			} else {
				$html .= "</div>";
			}

			$html .= "</div>";
		}

		// 11. Append nonce field, element ID & native slug
		$html .= self::generate_option_field("link-sawing-edit-uri-element-slug", array("type" => "hidden", "value" => $native_slug));
		$html .= self::generate_option_field("link-sawing-edit-uri-element-id", array("type" => "hidden", "value" => $element_id));
		$html .= wp_nonce_field('link-sawing-edit-uri-box', 'link-sawing-nonce', true, false);

		return $html;
	}

	/**
	 * Get the HTML output of the redirect panel
	 *
	 * @param string|int $element_id
	 *
	 * @return string
	 */
	public static function display_redirect_panel($element_id)
	{
		// Heading
		$html = "<div class=\"link-sawing redirects-row redirects-panel columns-container\">";
		$html .= sprintf("<div><a class=\"button\" href=\"#\" id=\"toggle-redirect-panel\">%s</a></div>", __("مدیریت ریدایرکت ها", "link-sawing"));

		$html .= "<div id=\"redirect-panel-inside\">";
		if (class_exists('link_sawing_Pro_Addons')) {
			$html .= link_sawing_Pro_Addons::display_redirect_form($element_id);
		} else {
			$html .= self::pro_text(true);
		}
		$html .= "</div>";
		$html .= "</div>";

		return $html;
	}

	/**
	 * Get the HTML output of error/info message
	 *
	 * @param string $alert_content
	 * @param string $alert_type
	 * @param bool $dismissible
	 * @param bool $id
	 *
	 * @return string
	 */
	public static function get_alert_message($alert_content = "", $alert_type = "", $dismissible = true, $id = false)
	{
		// Ignore empty messages (just in case)
		if (empty($alert_content) || empty($alert_type)) {
			return "";
		}

		$class    = ($dismissible) ? "is-dismissible" : "";
		$alert_id = ($id) ? " data-alert_id=\"{$id}\"" : "";

		return sprintf("<div class=\"{$alert_type} link-sawing-notice notice {$class}\"{$alert_id}> %s</div>", wpautop($alert_content));
	}

	/**
	 * Get the HTML output of help tooltip
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	static function help_tooltip($text = '')
	{
		return " <a href=\"#\" title=\"{$text}\" class=\"help_tooltip\"><span class=\"dashicons dashicons-editor-help\"></span></a>";
	}

	/**
	 * Display global notices (throughout wp-admin dashboard)
	 */
	function display_global_notices()
	{
		global $link_sawing_alerts, $active_section;

		$html = "";
		if (!empty($link_sawing_alerts) && is_array($link_sawing_alerts)) {
			foreach ($link_sawing_alerts as $alert_id => $alert) {
				$dismissed_transient_name = sprintf('link-sawing-notice_%s', sanitize_title($alert_id));
				$dismissed                = get_transient($dismissed_transient_name);

				// Check if alert was dismissed
				if (empty($dismissed)) {
					// Display the notice only on the plugin pages
					if (empty($active_section) && !empty($alert['plugin_only'])) {
						continue;
					}

					// Check if the notice did not expire
					if (isset($alert['until']) && (time() > strtotime($alert['until']))) {
						continue;
					}

					$html .= self::get_alert_message($alert['txt'], $alert['type'], true, $alert_id);
				}
			}
		}

		echo $html;
	}

	/**
	 * Display notices generated by Link Sawing tools
	 */
	function display_plugin_notices()
	{
		global $link_sawing_before_sections_html;

		echo $link_sawing_before_sections_html;
	}

	/**
	 * Check if Link Sawing Pro is active
	 *
	 * @return bool
	 */
	public static function is_pro_active()
	{
		if (defined('link_sawing_PRO') && class_exists('link_sawing_Pro_Functions')) {
			// Check if license is active
			$exp_date = link_sawing_Pro_Functions::get_expiration_date(true);

			$is_pro = ($exp_date > 2) ? false : true;
		} else {
			$is_pro = false;
		}

		return $is_pro;
	}

	/**
	 * Display the license expiration date (in Pro version) or information about the premium functionality
	 *
	 * @param string $text_only
	 *
	 * @return string
	 */
	static function pro_text($text_only = false)
	{
		if (class_exists('link_sawing_Pro_Functions')) {
			$text = link_sawing_Pro_Functions::get_expiration_date(false, true);
		} else {
			$text = sprintf(__('این تابع فقط در نسخه ای حرفه ای این افزونه قابل ارائه است', 'link-sawing'), link_sawing_WEBSITE);
		}

		return ($text_only) ? $text : sprintf("<div class=\"alert info\"> %s</div>", wpautop($text, 'alert'));
	}
}
