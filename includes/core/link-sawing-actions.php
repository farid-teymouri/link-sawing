<?php

/**
 * Additional hooks for "Link Sawing Pro"
 */
class link_sawing_Actions
{

	public function __construct()
	{
		add_action('admin_init', array($this, 'trigger_action'), 9);
		add_action('admin_init', array($this, 'extra_actions'));

		// Ajax-based functions
		if (is_admin()) {
			add_action('wp_ajax_pm_bulk_tools', array($this, 'ajax_bulk_tools'));
			add_action('wp_ajax_pm_save_permalink', array($this, 'ajax_save_permalink'));
			add_action('wp_ajax_pm_detect_duplicates', array($this, 'ajax_detect_duplicates'));
			add_action('wp_ajax_pm_dismissed_notice_handler', array($this, 'ajax_hide_global_notice'));
		}

		add_action('clean_permalinks_event', array($this, 'clean_permalinks_hook'));
		add_action('init', array($this, 'clean_permalinks_cronjob'));
	}

	/**
	 * Route the requests to functions that save datasets with associated callbacks
	 */
	public function trigger_action()
	{
		global $link_sawing_after_sections_html;

		// 1. Check if the form was submitted
		if (empty($_POST)) {
			return;
		}

		// 2. Do nothing if search query is not empty
		if (isset($_REQUEST['search-submit']) || isset($_REQUEST['months-filter-button'])) {
			return;
		}

		$actions_map = array(
			'uri_editor'                     => array('function' => 'update_all_permalinks', 'display_uri_table' => true),
			'link_sawing_options'      => array('function' => 'save_settings'),
			'link_sawing_permastructs' => array('function' => 'save_permastructures'),
			'import'                         => array('function' => 'import_custom_permalinks_uris'),
		);

		// 3. Find the action
		foreach ($actions_map as $action => $map) {
			if (isset($_POST[$action]) && wp_verify_nonce($_POST[$action], 'link-sawing')) {
				// Execute the function
				$output = call_user_func(array($this, $map['function']));

				// Get list of updated URIs
				if (!empty($map['display_uri_table'])) {
					$updated_slugs_count = (isset($output['updated_count']) && $output['updated_count'] > 0) ? $output['updated_count'] : false;
					$updated_slugs_array = ($updated_slugs_count) ? $output['updated'] : '';
				}

				// Trigger only one function
				break;
			}
		}

		// 4. Display the slugs table (and append the globals)
		if (isset($updated_slugs_count) && isset($updated_slugs_array)) {
			$link_sawing_after_sections_html .= link_sawing_Admin_Functions::display_updated_slugs($updated_slugs_array);
		}
	}

	/**
	 * Route the requests to the additional tools-related functions with the relevant callbacks
	 */
	public static function extra_actions()
	{
		global $link_sawing_before_sections_html;

		if (current_user_can('manage_options') && !empty($_GET['link-sawing-nonce'])) {
			// Check if the nonce field is correct
			$nonce = sanitize_key($_GET['link-sawing-nonce']);

			if (!wp_verify_nonce($nonce, 'link-sawing')) {
				$link_sawing_before_sections_html = link_sawing_Admin_Functions::get_alert_message(__('متاسفانه، شما اجازه دسترسی به حذف داده از این افزونه را ندارید!', 'link-sawing'), 'error updated_slugs');

				return;
			}

			if (isset($_GET['clear-link-sawing-uris'])) {
				self::clear_all_uris();
			} else if (isset($_GET['remove-link-sawing-settings'])) {
				$option_name = sanitize_text_field($_GET['remove-link-sawing-settings']);
				self::remove_plugin_data($option_name);
			} else if (!empty($_REQUEST['remove-uri'])) {
				$uri_key = sanitize_text_field($_REQUEST['remove-uri']);
				self::force_clear_single_element_uris_and_redirects($uri_key);
			} else if (!empty($_REQUEST['remove-redirect'])) {
				$redirect_key = sanitize_text_field($_REQUEST['remove-redirect']);
				self::force_clear_single_redirect($redirect_key);
			}
		} else if (!empty($_POST['screen-options-apply'])) {
			self::save_screen_options();
		}
	}

	/**
	 * Bulk remove obsolete custom permalinks and redirects
	 */
	public static function clear_all_uris()
	{
		global $link_sawing_uris, $link_sawing_redirects, $link_sawing_before_sections_html;

		// Check if array with custom URIs exists
		if (empty($link_sawing_uris)) {
			return;
		}

		// Count removed URIs & redirects
		$removed_uris      = 0;
		$removed_redirects = 0;

		// Get all element IDs
		$element_ids = array_merge(array_keys((array) $link_sawing_uris), array_keys((array) $link_sawing_redirects));

		// 1. Remove unused custom URI & redirects for deleted post or term
		foreach ($element_ids as $element_id) {
			$count = self::clear_single_element_uris_and_redirects($element_id, true);

			$removed_uris      = (!empty($count[0])) ? $count[0] + $removed_uris : $removed_uris;
			$removed_redirects = (!empty($count[1])) ? $count[1] + $removed_redirects : $removed_redirects;
		}

		// 2. Keep only a single redirect (make it unique)
		$removed_redirects += self::clear_redirects_array();

		// 3. Optional method to keep the permalinks unique
		if (apply_filters('link_sawing_fix_uri_duplicates', false)) {
			self::fix_uri_duplicates();
		}

		// 4. Remove items without keys
		/*if(!empty($link_sawing_uris[null])) {
			unset($link_sawing_uris[null]);
		}*/

		// Save cleared URIs & Redirects
		if ($removed_uris > 0 || $removed_redirects > 0) {
			update_option('link-sawing-uris', array_filter($link_sawing_uris));
			update_option('link-sawing-redirects', array_filter($link_sawing_redirects));

			$link_sawing_before_sections_html .= link_sawing_Admin_Functions::get_alert_message(sprintf(__('%d پیوند سفارشی و  %d ریدایرکت سفارشی حذف شد!', 'link-sawing'), $removed_uris, $removed_redirects), 'updated updated_slugs');
		} else {
			$link_sawing_before_sections_html .= link_sawing_Admin_Functions::get_alert_message(__('هیچ پیوند سفارشی یا ریدایرکت سفارشی حذف نشد!', 'link-sawing'), 'error updated_slugs');
		}
	}

	/**
	 * Remove obsolete custom permalink & redirects for specific post or term
	 *
	 * @param string|int $element_id
	 * @param bool $count_removed
	 *
	 * @return array
	 */
	public static function clear_single_element_uris_and_redirects($element_id, $count_removed = false)
	{
		global $wpdb, $link_sawing_uris, $link_sawing_redirects, $link_sawing_options;

		// Count removed URIs & redirects
		$removed_uris      = 0;
		$removed_redirects = 0;

		// Only admin users can remove the broken URIs for removed post types & taxonomies
		$check_if_admin = is_admin();

		// Check if the advanced mode is turned on
		$advanced_mode = link_sawing_Helper_Functions::is_advanced_mode_on();

		// If "Disable URI Editor to disallow Permalink changes" is set globally, the pages that follow the global settings should also be removed
		if ($advanced_mode && !empty($link_sawing_options["general"]["auto_update_uris"]) && $link_sawing_options["general"]["auto_update_uris"] == 2) {
			$strict_mode = true;
		} else {
			$strict_mode = false;
		}

		// 1. Check if element exists
		if (strpos($element_id, 'tax-') !== false) {
			$term_id   = preg_replace("/[^0-9]/", "", $element_id);
			$term_info = $wpdb->get_row($wpdb->prepare("SELECT taxonomy, meta_value FROM {$wpdb->term_taxonomy} AS t LEFT JOIN {$wpdb->termmeta} AS tm ON tm.term_id = t.term_id AND tm.meta_key = 'auto_update_uri' WHERE t.term_id = %d", $term_id));

			// Custom URIs for disabled taxonomies may only be deleted via the admin dashboard, although they will always be removed if the term no longer exists in the database
			$remove = (!empty($term_info->taxonomy)) ? link_sawing_Helper_Functions::is_taxonomy_disabled($term_info->taxonomy, $check_if_admin) : true;

			// Remove custom URIs for URIs disabled in URI Editor
			if ($strict_mode) {
				$remove = (empty($term_info->meta_value) || $term_info->meta_value == 2) ? true : $remove;
			} else {
				$remove = (!empty($term_info->meta_value) && $term_info->meta_value == 2) ? true : $remove;
			}
		} else if (is_numeric($element_id)) {
			$post_info = $wpdb->get_row($wpdb->prepare("SELECT post_type, meta_value FROM {$wpdb->posts} AS p LEFT JOIN {$wpdb->postmeta} AS pm ON pm.post_ID = p.ID AND pm.meta_key = 'auto_update_uri' WHERE ID = %d AND post_status NOT IN ('auto-draft', 'trash') AND post_type != 'nav_menu_item'", $element_id));

			// Custom URIs for disabled post types may only be deleted via the admin dashboard, although they will always be removed if the post no longer exists in the database
			$remove = (!empty($post_info->post_type)) ? link_sawing_Helper_Functions::is_post_type_disabled($post_info->post_type, $check_if_admin) : true;

			// Remove custom URIs for URIs disabled in URI Editor
			if ($strict_mode) {
				$remove = (empty($post_info->meta_value) || $post_info->meta_value == 2) ? true : $remove;
			} else {
				$remove = (!empty($post_info->meta_value) && $post_info->meta_value == 2) ? true : $remove;
			}

			// Remove custom URIs for attachments redirected with Yoast's SEO Premium
			$yoast_permalink_options = (class_exists('WPSEO_Premium')) ? get_option('wpseo_permalinks') : array();

			if (!empty($yoast_permalink_options['redirectattachment']) && $post_info->post_type == 'attachment') {
				$attachment_parent = $wpdb->get_var("SELECT post_parent FROM {$wpdb->prefix}posts WHERE ID = {$element_id} AND post_type = 'attachment'");
				if (!empty($attachment_parent)) {
					$remove = true;
				}
			}
		}

		// 2A. Remove ALL unused custom permalinks & redirects
		if (!empty($remove)) {
			// Remove URI
			if (!empty($link_sawing_uris[$element_id])) {
				$removed_uris = 1;
				unset($link_sawing_uris[$element_id]);
			}

			// Remove all custom redirects
			if (!empty($link_sawing_redirects[$element_id]) && is_array($link_sawing_redirects[$element_id])) {
				$removed_redirects = count($link_sawing_redirects[$element_id]);
				unset($link_sawing_redirects[$element_id]);
			}
		} // 2B. Check if the post/term uses the same URI for both permalink & custom redirects
		else {
			$removed_redirect  = self::clear_single_element_duplicated_redirect($element_id, false);
			$removed_redirects = (!empty($removed_redirect)) ? 1 : 0;
		}

		// Check if function should only return the counts or update
		if ($count_removed) {
			return array($removed_uris, $removed_redirects);
		} else if (!empty($removed_uris) || !empty($removed_redirects)) {
			update_option('link-sawing-uris', array_filter($link_sawing_uris));
			update_option('link-sawing-redirects', array_filter($link_sawing_redirects));
		}

		return array();
	}

	/**
	 * Remove the duplicated custom redirect if the post/term has the same URI for both custom permalink and custom redirect
	 *
	 * @param string|int $element_id
	 * @param bool $save_redirects
	 * @param string $uri
	 *
	 * @return int
	 */
	public static function clear_single_element_duplicated_redirect($element_id, $save_redirects = true, $uri = null)
	{
		global $link_sawing_uris, $link_sawing_redirects;

		$custom_uri = (empty($uri) && !empty($link_sawing_uris[$element_id])) ? $link_sawing_uris[$element_id] : $uri;

		if ($custom_uri && !empty($link_sawing_redirects[$element_id]) && in_array($custom_uri, $link_sawing_redirects[$element_id])) {
			$duplicated_redirect_id = array_search($custom_uri, $link_sawing_redirects[$element_id]);
			unset($link_sawing_redirects[$element_id][$duplicated_redirect_id]);
		}

		// Update the redirects array in the database if the duplicated redirect was unset
		if (isset($duplicated_redirect_id) && $save_redirects) {
			update_option('link-sawing-redirects', array_filter($link_sawing_redirects));
		}

		return (isset($duplicated_redirect_id)) ? 1 : 0;
	}

	/**
	 * Remove the duplicated if the same URI is used for multiple custom redirects and return the removed redirects count
	 *
	 * @param bool $save_redirects
	 *
	 * @return int
	 */
	public static function clear_redirects_array($save_redirects = false)
	{
		global $link_sawing_redirects;

		$removed_redirects = 0;

		$all_redirect_duplicates = link_sawing_Helper_Functions::get_all_duplicates();

		foreach ($all_redirect_duplicates as $single_redirect_duplicate) {
			$last_element = reset($single_redirect_duplicate);

			foreach ($single_redirect_duplicate as $redirect_key) {
				// Keep a single redirect
				if ($last_element == $redirect_key) {
					continue;
				}
				preg_match("/redirect-(\d+)_(tax-\d+|\d+)/", $redirect_key, $ids);

				if (!empty($ids[2]) && !empty($link_sawing_redirects[$ids[2]][$ids[1]])) {
					$removed_redirects++;
					unset($link_sawing_redirects[$ids[2]][$ids[1]]);
				}
			}
		}

		// Update the redirects array in the database if the duplicated redirect was unset
		if (isset($duplicated_redirect_id) && $save_redirects) {
			update_option('link-sawing-redirects', array_filter($link_sawing_redirects));
		}

		return $removed_redirects;
	}

	/**
	 * If the custom permalink is duplicated, append the index (-2, -3, etc.)
	 */
	public static function fix_uri_duplicates()
	{
		global $link_sawing_uris;

		$duplicates = array_count_values($link_sawing_uris);

		foreach ($duplicates as $uri => $count) {
			if ($count == 1) {
				continue;
			}

			$ids = array_keys($link_sawing_uris, $uri);
			foreach ($ids as $index => $id) {
				if ($index > 0) {
					$link_sawing_uris[$id] = preg_replace('/(.+?)(\.[^.]+$|$)/', '$1-' . $index . '$2', $uri);
				}
			}
		}

		update_option('link-sawing-uris', $link_sawing_uris);
	}

	/**
	 * Remove custom permalinks & custom redirects for requested post or term
	 *
	 * @param $uri_key
	 *
	 * @return bool
	 */
	public static function force_clear_single_element_uris_and_redirects($uri_key)
	{
		global $link_sawing_uris, $link_sawing_redirects, $link_sawing_before_sections_html;

		// Check if custom URI is set
		if (isset($link_sawing_uris[$uri_key])) {
			$uri = $link_sawing_uris[$uri_key];

			unset($link_sawing_uris[$uri_key]);
			update_option('link-sawing-uris', $link_sawing_uris);

			$updated = link_sawing_Admin_Functions::get_alert_message(sprintf(__('آدرس "%s" با موفقیت حذف شد!', 'link-sawing'), $uri), 'updated');
		}

		// Check if custom redirects are set
		if (isset($link_sawing_redirects[$uri_key])) {
			unset($link_sawing_redirects[$uri_key]);
			update_option('link-sawing-redirects', $link_sawing_redirects);

			$updated = link_sawing_Admin_Functions::get_alert_message(__('ریدایرکت های خراب با موفقیت حذف گردید!', 'link-sawing'), 'updated');
		}

		if (empty($updated)) {
			$link_sawing_before_sections_html .= link_sawing_Admin_Functions::get_alert_message(__('آدرس ها یا/و ریدایرکت ها یافت نشد، یا قبل تر حذف شده اند.!', 'link-sawing'), 'error');
		} else {
			// Display the alert in admin panel
			if (!empty($link_sawing_before_sections_html) && is_admin()) {
				$link_sawing_before_sections_html .= $updated;
			}
		}

		return true;
	}

	/**
	 * Remove only custom redirects for requested post or term
	 *
	 * @param string $redirect_key
	 */
	public static function force_clear_single_redirect($redirect_key)
	{
		global $link_sawing_redirects, $link_sawing_before_sections_html;

		preg_match("/redirect-(\d+)_(tax-\d+|\d+)/", $redirect_key, $ids);

		if (!empty($link_sawing_redirects[$ids[2]][$ids[1]])) {
			unset($link_sawing_redirects[$ids[2]][$ids[1]]);

			update_option('link-sawing-redirects', array_filter($link_sawing_redirects));

			$link_sawing_before_sections_html = link_sawing_Admin_Functions::get_alert_message(__('ریدایرکت با موفقیت حذف گردید!', 'link-sawing'), 'updated');
		}
	}

	/**
	 * Save "Screen Options"
	 */
	public static function save_screen_options()
	{
		check_admin_referer('screen-options-nonce', 'screenoptionnonce');

		// The values will be sanitized inside the function
		self::save_settings('screen-options', $_POST['screen-options']);
	}

	/**
	 * Save the plugin settings
	 *
	 * @param bool $field
	 * @param bool $value
	 * @param bool $display_alert
	 */
	public static function save_settings($field = false, $value = false, $display_alert = true)
	{
		global $link_sawing_options, $link_sawing_before_sections_html;

		// Info: The settings array is used also by "Screen Options"
		$new_options = $link_sawing_options;
		//$new_options = array();

		// Save only selected field/sections
		if ($field && $value) {
			$new_options[$field] = $value;
		} else {
			$post_fields = $_POST;

			foreach ($post_fields as $option_name => $option_value) {
				$new_options[$option_name] = $option_value;
			}
		}

		// Allow only white-listed option groups
		foreach ($new_options as $group => $group_options) {
			if (!in_array($group, array('licence', 'screen-options', 'general', 'permastructure-settings', 'stop-words'))) {
				unset($new_options[$group]);
			}
		}

		// Sanitize & override the global with new settings
		$new_options               = link_sawing_Helper_Functions::sanitize_array($new_options);
		$link_sawing_options = $new_options = array_filter($new_options);

		// Save the settings in database
		update_option('link-sawing', $new_options);

		// Display the message
		$link_sawing_before_sections_html .= ($display_alert) ? link_sawing_Admin_Functions::get_alert_message(__('تغییرات ذخیره شد.', 'link-sawing'), 'updated') : "";
	}

	/**
	 * Save the permastructures
	 */
	public static function save_permastructures()
	{
		global $link_sawing_permastructs;

		$permastructure_options = $permastructures = array();
		$permastructure_types   = array('post_types', 'taxonomies');

		// Split permastructures & sanitize them
		foreach ($permastructure_types as $type) {
			if (empty($_POST[$type]) || !is_array($_POST[$type])) {
				continue;
			}

			$permastructures[$type] = $_POST[$type];

			foreach ($permastructures[$type] as &$single_permastructure) {
				$single_permastructure = link_sawing_Helper_Functions::sanitize_title($single_permastructure, true, false, false);
				$single_permastructure = trim($single_permastructure, '\/ ');
			}
		}

		if (!empty($_POST['permastructure-settings'])) {
			$permastructure_options = $_POST['permastructure-settings'];
		}

		// A. Permastructures
		if (!empty($permastructures['post_types']) || !empty($permastructures['taxonomies'])) {
			// Override the global with settings
			$link_sawing_permastructs = $permastructures;

			// Save the settings in database
			update_option('link-sawing-permastructs', $permastructures);
		}

		// B. Permastructure settings
		if (!empty($permastructure_options)) {
			self::save_settings('permastructure-settings', $permastructure_options);
		}
	}

	/**
	 * Update all permalinks in "Bulk URI Editor"
	 */
	function update_all_permalinks()
	{
		// Check if posts or terms should be updated
		if (!empty($_POST['content_type']) && $_POST['content_type'] == 'taxonomies') {
			return link_sawing_URI_Functions_Tax::update_all_permalinks();
		} else {
			return link_sawing_URI_Functions_Post::update_all_permalinks();
		}
	}

	/**
	 * Remove a specific section of the plugin data stored in the database
	 *
	 * @param $field_name
	 */
	public static function remove_plugin_data($field_name)
	{
		global $link_sawing, $link_sawing_before_sections_html;

		// Make sure that the user is allowed to remove the plugin data
		if (!current_user_can('manage_options')) {
			$link_sawing_before_sections_html .= link_sawing_Admin_Functions::get_alert_message(__('متاسفانه، شما اجازه دسترسی به حذف داده از این افزونه را ندارید!', 'link-sawing'), 'error updated_slugs');
		}

		switch ($field_name) {
			case 'uris':
				$option_name = 'link-sawing-uris';
				$alert       = __('پیوند های سفارشی', 'link-sawing');
				break;
			case 'redirects':
				$option_name = 'link-sawing-redirects';
				$alert       = __('ریدایرکت های سفارشی', 'link-sawing');
				break;
			case 'external-redirects':
				$option_name = 'link-sawing-external-redirects';
				$alert       = __('ریدایرکت های اضافی', 'link-sawing');
				break;
			case 'permastructs':
				$option_name = 'link-sawing-permastructs';
				$alert       = __('تنظیمات ساختارها', 'link-sawing');
				break;
			case 'settings':
				$option_name = 'link-sawing';
				$alert       = __('تنظیمات ساختارها', 'link-sawing');
				break;
			default:
				$alert = '';
		}

		if (!empty($option_name)) {
			// Remove the option from DB
			delete_option($option_name);

			// Reload globals
			$link_sawing->get_options_and_globals();

			$alert_message                          = sprintf(__('%s حذف شد!', 'link-sawing'), $alert);
			$link_sawing_before_sections_html .= link_sawing_Admin_Functions::get_alert_message($alert_message, 'updated updated_slugs');
		}
	}

	/**
	 * Trigger bulk tools ("Regenerate & reset", "Find & replace") via AJAX
	 */
	function ajax_bulk_tools()
	{
		global $sitepress, $wpdb;

		// Define variables
		$return = array('alert' => link_sawing_Admin_Functions::get_alert_message(__('هیچ <strong>Slug</strong> به روز رسانی نشد!', 'link-sawing'), 'error updated_slugs'));

		// Get the name of the function
		if (isset($_POST['regenerate']) && wp_verify_nonce($_POST['regenerate'], 'link-sawing')) {
			$function_name = 'regenerate_all_permalinks';
		} else if (isset($_POST['find_and_replace']) && wp_verify_nonce($_POST['find_and_replace'], 'link-sawing') && !empty($_POST['old_string']) && !empty($_POST['new_string'])) {
			$function_name = 'find_and_replace';
		}

		// Get the session ID
		$uniq_id = (!empty($_POST['pm_session_id'])) ? $_POST['pm_session_id'] : '';

		// Get content type & post statuses
		if (!empty($_POST['content_type']) && $_POST['content_type'] == 'taxonomies') {
			$content_type = 'taxonomies';

			if (empty($_POST['taxonomies'])) {
				$error  = true;
				$return = array('alert' => link_sawing_Admin_Functions::get_alert_message(__('هیچ <strong> طبقه بندی ای</strong> انتخاب نشده!', 'link-sawing'), 'error updated_slugs'));
			}
		} else {
			$content_type = 'post_types';

			// Check if any post type was selected
			if (empty($_POST['post_types'])) {
				$error  = true;
				$return = array('alert' => link_sawing_Admin_Functions::get_alert_message(__('هیچ <strong>نوع نوشته ای</strong> انتخاب نشده!', 'link-sawing'), 'error updated_slugs'));
			}

			// Check post status
			if (empty($_POST['post_statuses'])) {
				$error  = true;
				$return = array('alert' => link_sawing_Admin_Functions::get_alert_message(__('هیچ <strong>وظعیت پست ای</strong> انتخاب نشده!', 'link-sawing'), 'error updated_slugs'));
			}
		}

		// Check if both strings are set for "Find and replace" tool
		if (!empty($function_name) && !empty($content_type) && empty($error)) {

			// Hotfix for WPML (start)
			if ($sitepress) {
				remove_filter('get_terms_args', array($sitepress, 'get_terms_args_filter'), 10);
				remove_filter('get_term', array($sitepress, 'get_term_adjust_id'), 1);
				remove_filter('terms_clauses', array($sitepress, 'terms_clauses'), 10);
				remove_filter('get_pages', array($sitepress, 'get_pages_adjust_ids'), 1);
			}

			// Get the mode
			$mode = isset($_POST['mode']) ? $_POST['mode'] : 'custom_uris';

			// Get the content type
			if ($content_type == 'taxonomies') {
				$class_name = 'link_sawing_URI_Functions_Tax';
			} else {
				$class_name = 'link_sawing_URI_Functions_Post';
			}

			// Get items (try to get them from transient)
			$items = get_transient("pm_{$uniq_id}");

			// Get the iteration count and chunk size
			$iteration  = isset($_POST['iteration']) ? intval($_POST['iteration']) : 1;
			$chunk_size = apply_filters('link_sawing_chunk_size', 50);

			if (empty($items) && !empty($chunk_size)) {
				$items = $class_name::get_items();

				if (!empty($items)) {
					// Count how many items need to be processed
					$total = count($items);

					// Split items array into chunks and save them to transient
					$items = array_chunk($items, $chunk_size);

					set_transient("pm_{$uniq_id}", $items, 600);

					// Check for MySQL errors
					if (!empty($wpdb->last_error)) {
						printf('%s (%sMB)', $wpdb->last_error, strlen(serialize($items)) / 1000000);
						http_response_code(500);
						die();
					}
				}
			}

			// Get homepage URL and ensure that it ends with slash
			$home_url = link_sawing_Helper_Functions::get_permalink_base() . "/";

			// Process the variables from $_POST object
			$old_string = (!empty($_POST['old_string'])) ? str_replace($home_url, '', esc_sql($_POST['old_string'])) : '';
			$new_string = (!empty($_POST['old_string'])) ? str_replace($home_url, '', esc_sql($_POST['new_string'])) : '';

			// Process only one subarray
			if (!empty($items[$iteration - 1])) {
				$chunk = $items[$iteration - 1];

				// Check how many iterations are needed
				$total_iterations = count($items);

				// Check if posts or terms should be updated
				if ($function_name == 'find_and_replace') {
					$output = $class_name::find_and_replace($chunk, $mode, $old_string, $new_string);
				} else {
					$output = $class_name::regenerate_all_permalinks($chunk, $mode);
				}

				if (!empty($output['updated_count'])) {
					$return                  = array_merge($return, (array) link_sawing_Admin_Functions::display_updated_slugs($output['updated'], true));
					$return['updated_count'] = $output['updated_count'];
				}

				// Send total number of processed items with a first chunk
				if (!empty($total) && !empty($total_iterations) && $iteration == 1) {
					$return['total'] = $total;
					$return['items'] = $items;
				}

				$return['iteration']        = $iteration;
				$return['total_iterations'] = $total_iterations;
				$return['progress']         = $chunk_size * $iteration;
				$return['chunk']            = $chunk;

				// After all chunks are processed remove the transient
				if ($iteration == $total_iterations) {
					delete_transient("pm_{$uniq_id}");
				}
			}

			// Hotfix for WPML (end)
			if ($sitepress) {
				add_filter('terms_clauses', array($sitepress, 'terms_clauses'), 10, 4);
				add_filter('get_term', array($sitepress, 'get_term_adjust_id'), 1, 1);
				add_filter('get_terms_args', array($sitepress, 'get_terms_args_filter'), 10, 2);
				add_filter('get_pages', array($sitepress, 'get_pages_adjust_ids'), 1, 2);
			}
		}

		wp_send_json($return);
		die();
	}

	/**
	 * Save permalink via AJAX
	 */
	public function ajax_save_permalink()
	{
		$element_id = (!empty($_POST['link-sawing-edit-uri-element-id'])) ? sanitize_text_field($_POST['link-sawing-edit-uri-element-id']) : '';

		if (!empty($element_id) && is_numeric($element_id)) {
			link_sawing_URI_Functions_Post::update_post_uri($element_id);

			// Reload URI Editor & clean post cache
			clean_post_cache($element_id);
			die();
		}
	}

	/**
	 * Check if URI was used before
	 *
	 * @param string $uri
	 * @param string $element_id
	 */
	function ajax_detect_duplicates($uri = null, $element_id = null)
	{
		$duplicate_alert = __("این آدرس قبل تر مورد استفاده قرار گرفته، لطفا یکی دیگر را انتخاب کنید!", "link-sawing");

		if (!empty($_REQUEST['custom_uris'])) {
			// Sanitize the array
			$custom_uris      = link_sawing_Helper_Functions::sanitize_array($_REQUEST['custom_uris']);
			$duplicates_array = array();

			// Check each URI
			foreach ($custom_uris as $element_id => $uri) {
				$duplicates_array[$element_id] = link_sawing_Helper_Functions::is_uri_duplicated($uri, $element_id) ? $duplicate_alert : 0;
			}

			// Convert the output to JSON and stop the function
			echo json_encode($duplicates_array);
		} else if (!empty($_REQUEST['custom_uri']) && !empty($_REQUEST['element_id'])) {
			$is_duplicated = link_sawing_Helper_Functions::is_uri_duplicated($uri, $element_id);

			echo ($is_duplicated) ? $duplicate_alert : 0;
		}

		die();
	}

	/**
	 * Hide global notices (AJAX)
	 */
	function ajax_hide_global_notice()
	{
		global $link_sawing_alerts;

		// Get the ID of the alert
		$alert_id = (!empty($_REQUEST['alert_id'])) ? sanitize_title($_REQUEST['alert_id']) : "";
		if (!empty($link_sawing_alerts[$alert_id])) {
			$dismissed_transient_name = sprintf('link-sawing-notice_%s', $alert_id);
			$dismissed_time           = (!empty($link_sawing_alerts[$alert_id]['dismissed_time'])) ? (int) $link_sawing_alerts[$alert_id]['dismissed_time'] : DAY_IN_SECONDS;

			set_transient($dismissed_transient_name, 1, $dismissed_time);
		}
	}

	/**
	 * Import old URIs from "Custom Permalinks" (Pro)
	 */
	function import_custom_permalinks_uris()
	{
		link_sawing_Third_Parties::import_custom_permalinks_uris();
	}

	/**
	 * Remove the duplicated custom permalinks & redirects automatically in the background
	 */
	function clean_permalinks_hook()
	{
		global $link_sawing_uris, $link_sawing_redirects;

		// Backup the custom URIs
		if (is_array($link_sawing_uris)) {
			update_option('link-sawing-uris_backup', $link_sawing_uris, false);
		}
		// Backup the custom redirects
		if (is_array($link_sawing_redirects)) {
			update_option('link-sawing-redirects_backup', $link_sawing_redirects, false);
		}

		self::clear_all_uris();
	}

	/**
	 * Schedule the function that automatically removes the custom permalinks & redirects duplicates
	 */
	function clean_permalinks_cronjob()
	{
		global $link_sawing_options;

		$event_name = 'clean_permalinks_event';

		// Set up the "Automatically remove duplicates" function that runs in background once a day
		if (!empty($link_sawing_options['general']['auto_remove_duplicates']) && $link_sawing_options['general']['auto_remove_duplicates'] == 2) {
			if (!wp_next_scheduled($event_name)) {
				wp_schedule_event(time(), 'daily', $event_name);
			}
		} else if (wp_next_scheduled($event_name)) {
			$event_timestamp = wp_next_scheduled($event_name);
			wp_unschedule_event($event_timestamp, $event_name);
		}
	}
}
