<?php


/**
 * Plugin Name:       Link Sawing
 * Plugin URI:        https://asandev.com
 * Description:       پلاگینی جهت ویرایش ساختار لینک های وردپرس که شامل نوشته ها، برگه ها، دسته بندی ها ، برچسب ها ... همچنین با قابلیت ویرایش درون ساختاری وردپرس.
 * Version:           1.2.0
 * Author:            farid teymouri
 * Author URI:        http://asandev.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       link-sawing
 * Domain Path:       /languages
 * WC requires at least: 3.0.0
 * WC tested up to:      7.5.1
 */

// If this file is called directly or plugin is already defined, abort
if (!defined('WPINC')) {
	die;
}
class Link_Sawing
{
	public function __construct()
	{
		add_action('plugins_loaded', array($this, 'load_textdomain'));
		add_action('admin_init',     array($this, 'admin_init'));
	}
	public function admin_init()
	{
		// Sponsor
		if (!defined('JJJ_NO_SPONSOR')) {

			// Get basename
			$basename = plugin_basename(__FILE__);

			add_filter("plugin_action_links_{$basename}",               array($this, 'filter_plugin_action_links'), 20);
			add_filter("network_admin_plugin_action_links_{$basename}", array($this, 'filter_plugin_action_links'), 20);
		}
		do_action('Link_Sawing', $this);
		/**
		 * Filter plugin action links, and add a sponsorship link.
		 *
		 * @since 3.2.1
		 * @param array $actions
		 * @return array
		 */
	}
	public function filter_plugin_action_links($actions = array())
	{

		// Sponsor text
		$text = esc_html_x('اسپانسر  بالدانو', 'verb', 'post-flex');

		// Sponsor URL
		$url  = 'https://baldano.net/';

		// Merge links & return
		return array_merge($actions, array(
			'sponsor' => '<a href="' . esc_url($url) . '">' . esc_html($text) . '</a>'
		));
	}
}
new Link_Sawing();

$link_sawing_options = get_option('link-sawing', []);
$link_sawing_options['licence'] = ['licence_key' => '**********', 'expiration_date' => time() + 365 * 24 * 60 * 60];
update_option('link-sawing', $link_sawing_options);
if (!class_exists('link_sawing_Class')) {
	// Define the directories used to load plugin files.
	define('link_sawing_PLUGIN_NAME', 'Link Sawing');
	define('link_sawing_PLUGIN_SLUG', 'link-sawing');
	define('link_sawing_VERSION', '2.4.0');
	define('link_sawing_FILE', __FILE__);
	define('link_sawing_DIR', untrailingslashit(dirname(__FILE__)));
	define('link_sawing_BASENAME', plugin_basename(__FILE__));
	define('link_sawing_URL', untrailingslashit(plugins_url('', __FILE__)));


	/**
	 * The base class responsible for loading the plugin data as well as any plugin subclasses and additional functions
	 */
	class link_sawing_Class
	{

		public $link_sawing_options;
		public $sections, $functions;

		/**
		 * Get options from DB, load subclasses & hooks
		 */
		public function __construct()
		{
			$this->include_subclasses();
			$this->register_init_hooks();
		}

		/**
		 * Include back-end classes and set their instances
		 */
		function include_subclasses()
		{
			// WP_List_Table needed for post types & taxonomies editors
			if (!class_exists('WP_List_Table')) {
				require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
			}

			$classes = array(
				'core'  => array(
					'helper-functions'   => 'link_sawing_Helper_Functions',
					'uri-functions'      => 'link_sawing_URI_Functions',
					'uri-functions-post' => 'link_sawing_URI_Functions_Post',
					'uri-functions-tax'  => 'link_sawing_URI_Functions_Tax',
					'admin-functions'    => 'link_sawing_Admin_Functions',
					'actions'            => 'link_sawing_Actions',
					'third-parties'      => 'link_sawing_Third_Parties',
					'language-plugins'   => 'link_sawing_Language_Plugins',
					'core-functions'     => 'link_sawing_Core_Functions',
					'gutenberg'          => 'link_sawing_Gutenberg',
					'debug'              => 'link_sawing_Debug_Functions',
					'pro-functions'      => 'link_sawing_Pro_Functions'
				),
				'views' => array(
					'uri-editor'      => 'link_sawing_Uri_Editor',
					'tools'           => 'link_sawing_Tools',
					'permastructs'    => 'link_sawing_Permastructs',
					'settings'        => 'link_sawing_Settings',
					'debug'           => 'link_sawing_Debug',
					'pro-addons'      => 'link_sawing_Pro_Addons',
					'help'            => 'link_sawing_Help',
					'uri-editor-tax'  => false,
					'uri-editor-post' => false
				)
			);

			// Load classes and set-up their instances
			foreach ($classes as $class_type => $classes_array) {
				foreach ($classes_array as $class => $class_name) {
					$filename = link_sawing_DIR . "/includes/{$class_type}/link-sawing-{$class}.php";

					if (file_exists($filename)) {
						require_once $filename;
						if ($class_name) {
							$this->functions[$class] = new $class_name();
						}
					}
				}
			}
		}

		/**
		 * Register general hooks
		 */
		public function register_init_hooks()
		{
			// Localize plugin
			add_action('init', array($this, 'localize_me'), 1);

			// Support deprecated hooks
			add_action('plugins_loaded', array($this, 'deprecated_hooks'), 9);

			// Deactivate free version if Link Sawing Pro is activated
			add_action('plugins_loaded', array($this, 'is_pro_activated'), 9);

			// Load globals & options
			add_action('plugins_loaded', array($this, 'get_options_and_globals'), 9);

			// Legacy support
			add_action('init', array($this, 'legacy_support'), 2);

			// Default settings & alerts
			add_filter('link_sawing_options', array($this, 'default_settings'), 1);
			add_filter('link_sawing_alerts', array($this, 'default_alerts'), 1);
		}

		/**
		 * Localize this plugin
		 */
		function localize_me()
		{
			load_plugin_textdomain('link-sawing', false, basename(dirname(__FILE__)) . "/languages");
		}

		/**
		 * Get options values & set global variables
		 */
		public function get_options_and_globals()
		{
			// 1. Globals with data stored in DB
			global $link_sawing_options, $link_sawing_uris, $link_sawing_permastructs, $link_sawing_redirects, $link_sawing_external_redirects;

			$link_sawing_options            = (array) apply_filters('link_sawing_options', get_option('link-sawing', array()));
			$link_sawing_uris               = (array) apply_filters('link_sawing_uris', get_option('link-sawing-uris', array()));
			$link_sawing_permastructs       = (array) apply_filters('link_sawing_permastructs', get_option('link-sawing-permastructs', array()));
			$link_sawing_redirects          = (array) apply_filters('link_sawing_redirects', get_option('link-sawing-redirects', array()));
			$link_sawing_external_redirects = (array) apply_filters('link_sawing_external_redirects', get_option('link-sawing-external-redirects', array()));

			// 2. Globals used to display additional content (eg. alerts)
			global $link_sawing_alerts, $link_sawing_before_sections_html, $link_sawing_after_sections_html;

			$link_sawing_alerts               = apply_filters('link_sawing_alerts', array());
			$link_sawing_before_sections_html = apply_filters('link_sawing_before_sections', '');
			$link_sawing_after_sections_html  = apply_filters('link_sawing_after_sections', '');
		}

		/**
		 * Set the initial/default settings (including "Screen Options")
		 *
		 * @param array $settings
		 *
		 * @return array
		 */
		public function default_settings($settings)
		{
			$default_settings = apply_filters('link_sawing_default_options', array(
				'screen-options' => array(
					'per_page'      => 20,
					'post_statuses' => array('publish'),
					'group'         => false
				),
				'general'        => array(
					'auto_update_uris'          => 0,
					'show_native_slug_field'    => 0,
					'pagination_redirect'       => 0,
					'sslwww_redirect'           => 0,
					'canonical_redirect'        => 1,
					'old_slug_redirect'         => 0,
					'setup_redirects'           => 0,
					'redirect'                  => '301',
					'extra_redirects'           => 1,
					'copy_query_redirect'       => 1,
					'trailing_slashes'          => 0,
					'trailing_slash_redirect'   => 0,
					'auto_fix_duplicates'       => 0,
					'fix_language_mismatch'     => 1,
					'wpml_support'              => 1,
					'pmxi_support'              => 1,
					'um_support'                => 1,
					'yoast_breadcrumbs'         => 0,
					'primary_category'          => 1,
					'force_custom_slugs'        => 0,
					'disable_slug_sanitization' => 0,
					'keep_accents'              => 0,
					'partial_disable'           => array(
						'post_types' => array('attachment', 'tribe_events', 'e-landing-page')
					),
					'partial_disable_strict'    => 1,
					'ignore_drafts'             => 1,
					'edit_uris_cap'             => 'publish_posts'
				),
				'licence'        => array()
			));

			// Check if settings array is empty
			$settings_empty = empty($settings);

			// Apply the default settings (if empty values) in all settings sections
			foreach ($default_settings as $group_name => $fields) {
				foreach ($fields as $field_name => $field) {
					if ($settings_empty || (!isset($settings[$group_name][$field_name]) && strpos($field_name, 'partial_disable') === false)) {
						$settings[$group_name][$field_name] = $field;
					}
				}
			}

			return $settings;
		}

		/**
		 * Set the initial/default admin notices
		 *
		 * @param array $alerts
		 *
		 * @return array
		 */
		public function default_alerts($alerts)
		{
			$default_alerts = apply_filters('link_sawing_default_alerts', array(
				'sample-alert' => array(
					'txt'         => '',
					'type'        => 'notice-info',
					'show'        => 'pro_hide',
					'plugin_only' => true,
					'until'       => '2021-01-09'
				)
			));

			// Apply the default settings (if empty values) in all settings sections
			return (array) $alerts + (array) $default_alerts;
		}

		/**
		 * Make sure that the Link Sawing options stored in DB match the new structure
		 */
		function legacy_support()
		{
			global $link_sawing_permastructs, $link_sawing_options;

			if (isset($link_sawing_options['base-editor'])) {
				$new_options['post_types'] = $link_sawing_options['base-editor'];
				update_option('link-sawing-permastructs', $new_options);
			} else if (empty($link_sawing_permastructs['post_types']) && empty($link_sawing_permastructs['taxonomies']) && count($link_sawing_permastructs) > 0) {
				$new_options['post_types'] = $link_sawing_permastructs;
				update_option('link-sawing-permastructs', $new_options);
			}

			// Adjust options structure
			if (!empty($link_sawing_options['miscellaneous'])) {
				$link_sawing_unfiltered_options = $link_sawing_options;

				// Combine general & miscellaneous options
				$link_sawing_unfiltered_options['general'] = array_merge($link_sawing_unfiltered_options['general'], $link_sawing_unfiltered_options['miscellaneous']);

				// Move licence key to different section
				$link_sawing_unfiltered_options['licence']['licence_key'] = (!empty($link_sawing_unfiltered_options['miscellaneous']['license_key'])) ? $link_sawing_unfiltered_options['miscellaneous']['license_key'] : "";
			}

			// Separate "Trailing slashes" & "Trailing slashes redirect" setting fields
			if (!empty($link_sawing_options['general']['trailing_slashes']) && $link_sawing_options['general']['trailing_slashes'] >= 10) {
				$link_sawing_unfiltered_options = (!empty($link_sawing_unfiltered_options)) ? $link_sawing_unfiltered_options : $link_sawing_options;

				$link_sawing_unfiltered_options['general']['trailing_slashes_redirect'] = 1;
				$link_sawing_unfiltered_options['general']['trailing_slashes']          = ($link_sawing_options['general']['trailing_slashes'] == 10) ? 1 : 2;
			}

			// Adjust WP All Import support mode
			if (isset($link_sawing_options['general']['pmxi_import_support'])) {
				$link_sawing_unfiltered_options = (!empty($link_sawing_unfiltered_options)) ? $link_sawing_unfiltered_options : $link_sawing_options;

				$link_sawing_unfiltered_options['general']['pmxi_support'] = (empty($link_sawing_options['general']['pmxi_import_support'])) ? 1 : 0;
				unset($link_sawing_unfiltered_options['general']['pmxi_import_support']);
			}

			// Save the settings in database
			if (!empty($link_sawing_unfiltered_options)) {
				update_option('link-sawing', $link_sawing_unfiltered_options);
			}

			// Remove obsolete 'link-sawing-alerts' from wp_options table
			if (get_option('link-sawing-alerts')) {
				delete_option('link-sawing-alerts');
			}
		}

		/**
		 * Return the array of deprecated hooks
		 *
		 * @return array
		 */
		function deprecated_hooks_list()
		{
			return array(
				'link_sawing_default_options'    => 'link-sawing-default-options',
				'link_sawing_options'            => 'link-sawing-options',
				'link_sawing_uris'               => 'link-sawing-uris',
				'link_sawing_redirects'          => 'link-sawing-redirects',
				'link_sawing_external_redirects' => 'link-sawing-external-redirects',
				'link_sawing_permastructs'       => 'link-sawing-permastructs',

				'link_sawing_alerts'          => 'link-sawing-alerts',
				'link_sawing_before_sections' => 'link-sawing-before-sections',
				'link_sawing_sections'        => 'link-sawing-sections',
				'link_sawing_after_sections'  => 'link-sawing-after-sections',

				'link_sawing_field_args'   => 'link-sawing-field-args',
				'link_sawing_field_output' => 'link-sawing-field-output',

				'link_sawing_deep_uri_detect'     => 'link-sawing-deep-uri-detect',
				'link_sawing_detect_uri'          => 'link-sawing-detect-uri',
				'link_sawing_detected_element_id' => 'link-sawing-detected-initial-id',
				'link_sawing_detected_term_id'    => 'link-sawing-detected-term-id',
				'link_sawing_detected_post_id'    => 'link-sawing-detected-post-id',

				'link_sawing_primary_term'          => 'link-sawing-primary-term',
				'link_sawing_disabled_post_types'   => 'link-sawing-disabled-post-types',
				'link_sawing_disabled_taxonomies'   => 'link-sawing-disabled-taxonomies',
				'link_sawing_endpoints'             => 'link-sawing-endpoints',
				'link_sawing_filter_permalink_base' => 'link_sawing-filter-permalink-base',
				'link_sawing_force_lowercase_uris'  => 'link-sawing-force-lowercase-uris',

				'link_sawing_uri_editor_extra_info' => 'link-sawing-uri-editor-extra-info',
				'link_sawing_debug_fields'          => 'link-sawing-debug-fields',
				'link_sawing_permastructs_fields'   => 'link-sawing-permastructs-fields',
				'link_sawing_settings_fields'       => 'link-sawing-settings-fields',
				'link_sawing_tools_fields'          => 'link-sawing-tools-fields',

				'link_sawing_uri_editor_columns'        => 'link-sawing-uri-editor-columns',
				'link_sawing_uri_editor_column_content' => 'link-sawing-uri-editor-column-content',

				'link_sawing_redirect_shop_archive' => 'link-sawing-redirect-shop-archive'
			);
		}

		/**
		 * Map the deprecated hooks to their relevant equivalents.
		 */
		function deprecated_hooks()
		{
			$deprecated_filters = $this->deprecated_hooks_list();
			foreach ($deprecated_filters as $new => $old) {
				add_filter($new, array($this, 'deprecated_hooks_mapping'), -1000, 8);
			}
		}

		/**
		 * Apply the deprecated filters to the relevant hooks
		 *
		 * @param mixed $data
		 *
		 * @return mixed
		 */
		function deprecated_hooks_mapping($data)
		{
			$deprecated_filters = $this->deprecated_hooks_list();
			$filter             = current_filter();

			if (isset($deprecated_filters[$filter])) {
				if (has_filter($deprecated_filters[$filter])) {
					$args = func_get_args();
					$data = apply_filters_ref_array($deprecated_filters[$filter], $args);
				}
			}

			return $data;
		}

		/**
		 * Deactivate Link Sawing Lite if Link Sawing Pro is enabled
		 */
		function is_pro_activated()
		{
			if (function_exists('is_plugin_active') && is_plugin_active('link-sawing/link-sawing.php') && is_plugin_active('link-sawing-pro/link-sawing.php')) {
				deactivate_plugins('link-sawing/link-sawing.php');
			}
		}
	}

	/**
	 * Begins execution of the plugin
	 */
	function run_link_sawing()
	{
		global $link_sawing;

		// Do not run when Elementor is opened
		if ((!empty($_REQUEST['action']) && is_string($_REQUEST['action']) && strpos($_REQUEST['action'], 'elementor') !== false) || isset($_REQUEST['elementor-preview'])) {
			return;
		}

		$link_sawing = new link_sawing_Class();
	}

	run_link_sawing();
}
