<?php

/**
 * Additional hooks for "Link Sawing Pro"
 */
class link_sawing_Pro_Functions
{

	public $update_checker;

	public function __construct()
	{
		define('link_sawing_PRO', true);
		$plugin_name = preg_replace('/(.*)\/([^\/]+\/[^\/]+.php)$/', '$2', link_sawing_FILE);

		// Stop words
		add_filter('link_sawing_filter_default_post_slug', array($this, 'remove_stop_words'), 9, 3);
		add_filter('link_sawing_filter_default_term_slug', array($this, 'remove_stop_words'), 9, 3);

		// Custom fields in permalinks
		add_filter('link_sawing_filter_default_post_uri', array($this, 'replace_custom_field_tags'), 9, 5);
		add_filter('link_sawing_filter_default_term_uri', array($this, 'replace_custom_field_tags'), 9, 5);

		// Link Sawing Pro Alerts
		add_filter('link_sawing_alerts', array($this, 'pro_alerts'), 9, 3);

		// Save redirects
		add_action('link_sawing_updated_post_uri', array($this, 'save_redirects'), 9, 5);
		add_action('link_sawing_updated_term_uri', array($this, 'save_redirects'), 9, 5);

		// Check for updates
		//add_action( 'plugins_loaded', array( $this, 'check_for_updates' ), 10 );
		//add_action( 'admin_init', array( $this, 'reload_license_key' ), 10 );
		add_action('wp_ajax_pm_get_exp_date', array($this, 'get_expiration_date'), 9);

		// Display License info on "Plugins" page
		add_action("after_plugin_row_{$plugin_name}", array($this, 'license_info_bar'), 10, 2);
	}

	/**
	 * Get the license key from the database, constant defined in wp-config.php file, or $_POST variable
	 *
	 * @param string $load_from_db If set to true, the function will load the license key from the database, even if it's defined in wp-config.php.
	 *
	 * @return string The license key.
	 */
	public static function get_license_key($load_from_db = false)
	{
		$link_sawing_options = get_option('link-sawing', array());

		// Key defined in wp-config.php
		if ((defined('PMP_LICENCE_KEY') || defined('PMP_LICENSE_KEY')) && empty($load_from_db)) {
			$license_key = defined('PMP_LICENCE_KEY') ? PMP_LICENCE_KEY : PMP_LICENSE_KEY;
		} // Network licence key (multisite)
		else if (is_multisite()) {
			$site_licence_key = get_site_option('link-sawing-licence-key');

			// A. Move the license key to site options
			if (!empty($site_licence_key) && !is_array($site_licence_key)) {
				$new_license_key = $site_licence_key;
			} // B. Save the new license key in the plugin settings
			else if (!empty($_POST['licence']['licence_key'])) {
				$new_license_key = $_POST['licence']['licence_key'];
			}

			if (!empty($new_license_key)) {
				$site_licence_key = array(
					'licence_key' => sanitize_text_field($new_license_key)
				);

				update_site_option('link-sawing-licence-key', $site_licence_key);
			}

			$license_key = (!empty($site_licence_key['licence_key'])) ? $site_licence_key['licence_key'] : '';
		} // Single website licence key
		else if (!empty($_POST['licence']['licence_key'])) {
			$license_key = sanitize_text_field($_POST['licence']['licence_key']);
		} else {
			$license_key = (!empty($link_sawing_options['licence']['licence_key'])) ? $link_sawing_options['licence']['licence_key'] : "";
		}

		return $license_key;
	}

	/**
	 * Load the update checker class and create an instance of it
	 */
	public function check_for_updates()
	{
		$license_key = self::get_license_key();

		// Load Plugin Update Checker by YahnisElsts
		require_once link_sawing_DIR . '/includes/vendor/plugin-update-checker/plugin-update-checker.php';

		$this->update_checker = Puc_v4_Factory::buildUpdateChecker("", link_sawing_FILE, "link-sawing-pro");

		add_filter('puc_request_info_result-link-sawing-pro', array($this, 'update_pro_info'), 99, 2);
	}

	/**
	 * Check if the license key was changed and if so, delete the cached license data and get the new license data from the server
	 */
	public function reload_license_key()
	{
		if (!empty($_POST['licence']['licence_key']) || (!empty($_REQUEST['action']) && $_REQUEST['action'] == 'pm_get_exp_date') || (!empty($_REQUEST['puc_slug']) && $_REQUEST['puc_slug'] == 'link-sawing-pro')) {
			delete_site_transient('link_sawing_active');
			$this->update_checker->requestInfo();
		} // Sync the license data saved in DB after license key was set in wp-config.php file
		else if (defined('PMP_LICENCE_KEY') || defined('PMP_LICENSE_KEY')) {
			$db_license_key = self::get_license_key(true);
			$license_key    = self::get_license_key();

			if (!empty($db_license_key) && !empty($license_key) && $db_license_key !== $license_key) {
				delete_site_transient('link_sawing_active');
				$this->update_checker->requestInfo();
			}
		}
	}

	/**
	 * Check if the cached license key needs to be changed
	 *
	 * @param stdClass $raw The raw response from the server.
	 * @param array $result The response object from the API call.
	 *
	 * @return stdClass The plugin info
	 */
	public function update_pro_info($raw, $result)
	{
		$license_key              = self::get_license_key();
		$link_sawing_active = (empty($_POST['licence']['licence_key'])) ? get_site_transient('link_sawing_active') : '';

		// A. Do not do anything - the license info was saved before
		if (!empty($license_key) && ($link_sawing_active == $license_key)) {
			return $raw;
		} // B. The license info was not removed or not downloaded before
		else if (empty($link_sawing_active) && is_array($result) && !empty($result['body']) && !empty($license_key)) {
			$plugin_info = json_decode($result['body']);

			if (is_object($plugin_info) && isset($plugin_info->version)) {
				$exp_date = (!empty($plugin_info->expiration_date) && strlen($plugin_info->expiration_date) > 6) ? strtotime($plugin_info->expiration_date) : '-';
				$websites = (!empty($plugin_info->websites)) ? $plugin_info->websites : '';

				$license_info = array(
					'licence_key'     => $license_key,
					'expiration_date' => $exp_date,
					'websites'        => $websites
				);

				if (is_multisite()) {
					update_site_option('link-sawing-licence-key', $license_info);
				} else {
					link_sawing_Actions::save_settings('licence', $license_info, false);
				}

				set_site_transient('link_sawing_active', $license_key, 12 * HOUR_IN_SECONDS);
			}
		}

		return $raw;
	}

	/**
	 * Get license expiration date
	 *
	 * @param bool $basic_check
	 * @param bool $empty_if_valid
	 * @param bool $update_available
	 *
	 * @return int|string
	 */
	public static function get_expiration_date($basic_check = false, $empty_if_valid = false, $update_available = true)
	{
		global $link_sawing_options;

		// Get expiration info & the licence key
		if (is_multisite()) {
			$site_licence_key = get_site_option('link-sawing-licence-key');

			$exp_date    = (!empty($site_licence_key['expiration_date'])) ? $site_licence_key['expiration_date'] : false;
			$license_key = (!empty($site_licence_key['licence_key'])) ? $site_licence_key['licence_key'] : "";
			$websites    = (!empty($site_licence_key['websites'])) ? $site_licence_key['websites'] : "";
		} else {
			$exp_date    = (!empty($link_sawing_options['licence']['expiration_date'])) ? $link_sawing_options['licence']['expiration_date'] : false;
			$license_key = (!empty($link_sawing_options['licence']['licence_key'])) ? $link_sawing_options['licence']['licence_key'] : "";
			$websites    = (!empty($link_sawing_options['licence']['websites'])) ? $link_sawing_options['licence']['websites'] : "";
		}

		$license_info_page = (!empty($license_key)) ? sprintf("") : "";

		// // There is no key defined
		// if (empty($license_key)) {
		// 	$settings_page_url = link_sawing_Admin_Functions::get_admin_url("&section=settings");
		// 	$expiration_info   = sprintf(__('Please paste the licence key to access all Link Sawing Pro updates & features <a href="%s" target="_blank">on this page</a>.', 'link-sawing'), $settings_page_url);
		// 	$expired           = 3;
		// } // License key is invalid
		// else if ($exp_date == '-') {
		// 	$expiration_info = __('Your Link Sawing Pro licence key is invalid!', 'link-sawing');
		// 	$expired         = 3;
		// } // Key expired
		// else if (!empty($exp_date) && $exp_date < time()) {
		// 	$expiration_info = sprintf(__('Your Link Sawing Pro licence key expired! Please renew your license key using <a href="%s" target="_blank">this link</a> to regain access to plugin updates and technical support.', 'link-sawing'), $license_info_page);
		// 	$expired         = 2;
		// } // License key is abused
		// else if (!empty($exp_date) && !empty($websites) && $update_available === false) {
		// 	$expiration_info = sprintf(__('Your Link Sawing Pro license is already in use on another website and cannot be used to request automatic update for this domain.', 'link-sawing'), $license_info_page) . " ";
		// 	$expiration_info .= sprintf(__('For further information, visit the <a href="%s" target="_blank"> License info</a> page.'), $license_info_page);
		// 	$expired         = 2;
		// } // Valid lifetime license key
		// else if (date("Y", intval($exp_date)) > 2028) {
		// 	$expiration_info = __('You own a lifetime licence key.', 'link-sawing');
		// 	$expired         = 0;
		// } // License key is valid
		// else if ($exp_date) {
		// 	// License key will expire in less than month
		// 	if ($exp_date - MONTH_IN_SECONDS < time()) {
		// 		$expiration_info = sprintf(__('Your Link Sawing Pro license key will expire on <strong>%s</strong>. Please renew it to maintain access to plugin updates and technical support!'), wp_date(get_option('date_format'), $exp_date)) . " ";
		// 		$expiration_info .= sprintf(__('For further information, visit the <a href="%s" target="_blank"> License info</a> page.'), $license_info_page);
		// 		$expired         = 1;
		// 	} // License key can be used
		// 	else {
		// 		$expiration_info = sprintf(__('Your licence key is valid until %s.<br />To prolong it please go to <a href="%s" target="_blank">this page</a> for more information.', 'link-sawing'), date(get_option('date_format'), $exp_date), $license_info_page);
		// 		$expired         = 0;
		// 	}
		// } // Expiration data could not be downloaded
		// else {
		// 	$expiration_info = __('Expiration date could not be downloaded at this moment. Please try again in a few minutes.', 'link-sawing');
		// 	$expired         = 0;
		// }

		// // Do not return any text alert
		// if ($basic_check || ($empty_if_valid && $expired == 0)) {
		// 	return $expired;
		// }

		// if (!empty($_REQUEST['action']) && $_REQUEST['action'] == 'pm_get_exp_date') {
		// 	echo $expiration_info;
		// 	die();
		// } else {
		// 	return $expiration_info;
		// }
	}

	/**
	 * Display license status in the "Plugins" table
	 *
	 * @param string $plugin_file
	 * @param array $plugin_data
	 */
	function license_info_bar($plugin_file, $plugin_data)
	{
		global $wp_list_table;

		$column_count = (!empty($wp_list_table)) ? $wp_list_table->get_column_count() : 3;
		// $update_available = (empty($plugin_data['package']) && !empty($plugin_data['update'])) ? false : true;

		$exp_info_text = self::get_expiration_date(false, true, false);
		$exp_info_code = self::get_expiration_date(true, true, false);

		if (!empty($exp_info_text) && $exp_info_code >= 1) {
			printf('<tr class="plugin-update-tr link-sawing-pro_license-info active" data-slug="%s" data-plugin="%s"><td colspan="%d" class="plugin-update colspanchange plugin_license_info_row">', $plugin_data['slug'], $plugin_file, $column_count);
			printf('<div class="update-message notice inline notice-error notice-alt">%s</div>', wpautop($exp_info_text));
			printf('</td></tr>');
		}
	}

	/**
	 * Hide "Buy Link Sawing Pro" alert
	 *
	 * @param array $alerts
	 *
	 * @return array
	 */
	function pro_alerts($alerts = array())
	{
		// Check expiration date
		$exp_info_text = self::get_expiration_date(false, true, false);
		$exp_info_code = self::get_expiration_date(true, true, false);

		if (!empty($exp_info_text) && $exp_info_code >= 2) {
			$alerts['licence_key3'] = array('txt' => $exp_info_text, 'type' => 'notice-error', 'plugin_only' => false, 'dismissed_time' => DAY_IN_SECONDS * 3);
		}

		return $alerts;
	}

	/**
	 * Get the list of languages where stop words are defined
	 */
	static function load_stop_words_languages()
	{
		return array(
			'ar' => __('Arabic', 'link-sawing'),
			'zh' => __('Chinese', 'link-sawing'),
			'da' => __('Danish', 'link-sawing'),
			'nl' => __('Dutch', 'link-sawing'),
			'en' => __('English', 'link-sawing'),
			'fi' => __('Finnish', 'link-sawing'),
			'fr' => __('French', 'link-sawing'),
			'de' => __('German', 'link-sawing'),
			'he' => __('Hebrew', 'link-sawing'),
			'hi' => __('Hindi', 'link-sawing'),
			'it' => __('Italian', 'link-sawing'),
			'ja' => __('Japanese', 'link-sawing'),
			'ko' => __('Korean', 'link-sawing'),
			'no' => __('Norwegian', 'link-sawing'),
			'fa' => __('Persian', 'link-sawing'),
			'pl' => __('Polish', 'link-sawing'),
			'pt' => __('Portuguese', 'link-sawing'),
			'ru' => __('Russian', 'link-sawing'),
			'es' => __('Spanish', 'link-sawing'),
			'sv' => __('Swedish', 'link-sawing'),
			'tr' => __('Turkish', 'link-sawing')
		);
	}

	/**
	 * Load stop words for specific language (using ISO code)
	 *
	 * @param string $iso
	 *
	 * @return array
	 */
	static function load_stop_words($iso = '')
	{
		$json_dir = link_sawing_DIR . "/includes/vendor/stopwords-json/dist/{$iso}.json";
		$json_a   = array();

		if (file_exists($json_dir)) {
			$string = file_get_contents($json_dir);
			$json_a = json_decode($string, true);
		}

		return $json_a;
	}

	/**
	 * Remove the stop words from the default custom permalinks
	 *
	 * @param string $slug The slug that is being generated.
	 * @param stdClass $object The object that the slug is being generated for.
	 * @param string $name The name of the filter.
	 *
	 * @return string The slug.
	 */
	public function remove_stop_words($slug, $object, $name)
	{
		global $link_sawing_options;

		if (!empty($link_sawing_options['stop-words']['stop-words-enable']) && !empty($link_sawing_options['stop-words']['stop-words-list'])) {
			$stop_words = explode(",", strtolower(stripslashes($link_sawing_options['stop-words']['stop-words-list'])));

			foreach ($stop_words as $stop_word) {
				$stop_word = trim($stop_word);
				$slug      = preg_replace("/([\/-]|^)({$stop_word})([\/-]|$)/", '$1$3', $slug);
			}

			// Clear the slug
			$slug = preg_replace("/(-+)/", "-", trim($slug, "-"));
			$slug = preg_replace("/(-\/-)|(\/-)|(-\/)/", "/", $slug);
		}

		return $slug;
	}

	/**
	 * Replace custom field tags in the default custom permalinks
	 *
	 * @param string $default_uri
	 * @param string $native_slug
	 * @param WP_Post|WP_Term $element
	 * @param string $slug
	 * @param string $native_uri
	 *
	 * @return string
	 */
	function replace_custom_field_tags($default_uri, $native_slug, $element, $slug, $native_uri)
	{
		// Do not affect native URIs
		if ($native_uri) {
			return $default_uri;
		}

		preg_match_all("/%__(.[^\%]+)%/", $default_uri, $custom_fields);

		if (!empty($custom_fields[1])) {
			foreach ($custom_fields[1] as $i => $custom_field) {
				// Reset custom field value
				$custom_field_value = "";

				// Check additional arguments (__custom_field.argument_value)
				if (strpos($custom_field, '.') !== false) {
					$custom_field_split = preg_split('/[\.]/', $custom_field);

					if (!empty($custom_field_split[1])) {
						$custom_field_arg = $custom_field_split[1];
						$custom_field     = $custom_field_split[0];
					}
				}

				// 1. Use WooCommerce fields (SKU)
				if (class_exists('WooCommerce') && $custom_field == 'sku' && !empty($element->ID)) {
					$product = wc_get_product($element->ID);

					$custom_field_value = (is_a($product, 'WC_Product')) ? $product->get_sku() : '';
				} // 2. Try to get value using ACF API
				else if (function_exists('get_field_object')) {
					$acf_element_id = (!empty($element->ID)) ? $element->ID : "{$element->taxonomy}_{$element->term_id}";
					$field_object   = get_field_object($custom_field, $acf_element_id);

					// A. Relationship field
					if (!empty($field_object['type']) && (in_array($field_object['type'], array('relationship', 'post_object', 'taxonomy'))) && !empty($field_object['value'])) {
						$rel_elements = $field_object['value'];

						// B1. Terms
						if ($field_object['type'] == 'taxonomy') {
							if ((is_array($rel_elements))) {
								if (is_numeric($rel_elements[0]) && !empty($field_object['taxonomy'])) {
									$rel_elements = get_terms(array('include' => $rel_elements, 'taxonomy' => $field_object['taxonomy'], 'hide_empty' => false, 'orderby' => 'term_id', 'order' => 'DESC'));
								}

								// Get the lowest term
								if (!is_wp_error($rel_elements) && !empty($rel_elements) && is_object($rel_elements[0])) {
									$rel_term = link_sawing_Helper_Functions::get_lowest_element($rel_elements[0], $rel_elements);
								}
							}
							if (!empty($rel_elements->term_id)) {
								$rel_term = $rel_elements;
							} else if (!empty($rel_elements) && is_numeric($rel_elements)) {
								$rel_term = get_term($rel_elements, $field_object['taxonomy']);
							}

							if (!empty($rel_term->term_id)) {
								$custom_field_value = (!empty($custom_field_arg) && is_numeric($custom_field_arg)) ? link_sawing_Helper_Functions::get_term_full_slug($rel_term, $rel_elements, $custom_field_arg, $native_uri) : $rel_term->slug;
							}
						} // B2. Posts
						else {
							if ((is_array($rel_elements))) {
								if (is_numeric($rel_elements[0])) {
									$rel_elements = get_posts(array('include' => $rel_elements, 'post_type' => 'any'));
								}

								// Get lowest element
								if (!is_wp_error($rel_elements) && !empty($rel_elements) && is_object($rel_elements[0])) {
									$rel_post = link_sawing_Helper_Functions::get_lowest_element($rel_elements[0], $rel_elements);
								}
							} else if (!empty($rel_elements->ID)) {
								$rel_post = $rel_elements;
							}

							if (!empty($rel_post->ID)) {
								$rel_post_object = $rel_post;
							} else if (is_numeric($rel_elements)) {
								$rel_post_object = get_post($rel_elements);
							}

							// Get the replacement slug
							if (!empty($rel_post_object->ID)) {
								$custom_field_value = link_sawing_Helper_Functions::force_custom_slugs($rel_post_object->post_name, $rel_post_object);
							}
						}
					} // C. Text field
					else {
						$custom_field_value = (!empty($field_object['value'])) ? $field_object['value'] : "";
						$custom_field_value = (!empty($custom_field_value['value'])) ? $custom_field_value['value'] : $custom_field_value;
					}
				}

				// 3. Use native method
				if (empty($custom_field_value)) {
					if (!empty($element->ID)) {
						$custom_field_value = get_post_meta($element->ID, $custom_field, true);

						// Toolset
						if (empty($custom_field_value) && (defined('TYPES_VERSION') || defined('WPCF_VERSION'))) {
							$custom_field_value = get_post_meta($element->ID, "wpcf-{$custom_field}", true);
						}
					} else if (!empty($element->term_id)) {
						$custom_field_value = get_term_meta($element->term_id, $custom_field, true);
					} else {
						$custom_field_value = "";
					}
				}

				// Allow to filter the custom field value
				$custom_field_value = apply_filters('link_sawing_custom_field_value', $custom_field_value, $custom_field, $element);

				// Make sure that custom field is a string
				if (!empty($custom_field_value) && is_string($custom_field_value)) {
					// Do not sanitize the custom field if 'no-sanitize' argument is added (eg. %__custom-field-name.no-sanitize%)
					if (empty($custom_field_arg) || strpos($custom_field_arg, 'no-sanitize') === false) {
						$custom_field_value = link_sawing_Helper_Functions::sanitize_title($custom_field_value);
					}

					$default_uri = str_replace($custom_fields[0][$i], $custom_field_value, $default_uri);
				}
			}
		}

		return $default_uri;
	}


	/**
	 * Save the custom, external redirects for specific post or term
	 *
	 * @param int|string $element_id The ID of the post (e.g. 123) or term (tax-456).
	 * @param string $new_uri The new custom permalink
	 * @param string $old_uri The previous custom permalink
	 * @param string $native_uri The native permalink
	 * @param string $default_uri The default custom permalink
	 */
	public function save_redirects($element_id, $new_uri, $old_uri, $native_uri, $default_uri)
	{
		global $link_sawing_options, $link_sawing_redirects;

		// Do not trigger if "Extra redirects" option is turned off
		if (empty($link_sawing_options['general']['redirect']) || empty($link_sawing_options['general']['extra_redirects'])) {
			return;
		}

		// Terms IDs should be prepended with prefix
		$element_id = (current_filter() == 'link_sawing_updated_term_uri') ? "tax-{$element_id}" : $element_id;

		// Make sure that $link_sawing_redirects variable is an array
		$link_sawing_redirects = (is_array($link_sawing_redirects)) ? $link_sawing_redirects : array();

		// 1A. Post/term is saved or updated
		if (isset($_POST['link-sawing-redirects']) && is_array($_POST['link-sawing-redirects'])) {
			$link_sawing_redirects[$element_id] = array_filter($_POST['link-sawing-redirects']);
			$redirects_updated                          = true;
		} // 1B. All redirects are removed
		else if (isset($_POST['link-sawing-redirects'])) {
			$link_sawing_redirects[$element_id] = array();
			$redirects_updated                          = true;
		}

		// 1C. No longer needed
		if (isset($_POST['link-sawing-redirects'])) {
			unset($_POST['link-sawing-redirects']);
		}

		// 2. Custom URI is updated
		if (get_option('page_on_front') !== $element_id && !empty($link_sawing_options['general']['setup_redirects']) && ($new_uri !== $old_uri)) {
			// Make sure that the array with redirects exists
			$link_sawing_redirects[$element_id] = (!empty($link_sawing_redirects[$element_id])) ? $link_sawing_redirects[$element_id] : array();

			// Append the old custom URI
			$link_sawing_redirects[$element_id][] = $old_uri;
			$redirects_updated                            = true;
		}

		// 3. Save the custom redirects
		if (!empty($redirects_updated) && is_array($link_sawing_redirects[$element_id])) {
			// Remove empty redirects
			$link_sawing_redirects[$element_id] = array_filter($link_sawing_redirects[$element_id]);

			// Sanitize the array with redirects
			foreach ($link_sawing_redirects[$element_id] as $i => $redirect) {
				$redirect                                         = rawurldecode($redirect);
				$redirect                                         = link_sawing_Helper_Functions::sanitize_title($redirect, true);
				$link_sawing_redirects[$element_id][$i] = $redirect;
			}

			// Reset the keys
			$link_sawing_redirects[$element_id] = array_values($link_sawing_redirects[$element_id]);

			// Remove the duplicates
			$link_sawing_redirects[$element_id] = array_unique($link_sawing_redirects[$element_id]);

			link_sawing_Actions::clear_single_element_duplicated_redirect($element_id, false, $new_uri);

			update_option('link-sawing-redirects', $link_sawing_redirects);
		}

		// 4. Save the external redirect
		if (isset($_POST['link-sawing-external-redirect'])) {
			self::save_external_redirect($_POST['link-sawing-external-redirect'], $element_id);
		}
	}

	/**
	 * Save external redirect
	 *
	 * @param string $url The full URL saved as external redirect
	 * @param string|int $element_id The ID of the post (e.g. 123) or term (tax-456).
	 */
	public static function save_external_redirect($url, $element_id)
	{
		global $link_sawing_external_redirects;

		$url = filter_var($url, FILTER_SANITIZE_URL);

		if ((empty($url) || filter_var($url, FILTER_VALIDATE_URL) === false) && !empty($link_sawing_external_redirects[$element_id]) && isset($_POST['link-sawing-external-redirect'])) {
			unset($link_sawing_external_redirects[$element_id]);
		} else {
			$link_sawing_external_redirects[$element_id] = $url;
		}

		update_option('link-sawing-external-redirects', $link_sawing_external_redirects);
	}

	/**
	 * Remove "Coupons" from hard-coded list of disabled post types
	 *
	 * @param array $post_types
	 *
	 * @return array
	 */
	public static function woocommerce_coupon_uris($post_types)
	{
		return array_diff($post_types, array('shop_coupon'));
	}

	/**
	 * Add a new tab to the WooCommerce coupon edit page
	 *
	 * @param array $tabs The tabs array.
	 *
	 * @return array The tabs array with the new tab added.
	 */

	public static function woocommerce_coupon_tabs($tabs = array())
	{
		$tabs['coupon-url'] = array(
			'label'  => __('لینک کوپن', 'link-sawing'),
			'target' => 'link-sawing-coupon-url',
			'class'  => 'link-sawing-coupon-url',
		);

		return $tabs;
	}

	/**
	 * Add a new panel to the WooCommerce coupon edit page
	 */
	public static function woocommerce_coupon_panel()
	{
		global $link_sawing_uris, $post;

		$custom_uri = (!empty($link_sawing_uris[$post->ID])) ? $link_sawing_uris[$post->ID] : "";

		$html = "<div id=\"link-sawing-coupon-url\" class=\"panel woocommerce_options_panel custom_uri_container link-sawing\">";

		// URI field
		ob_start();
		wp_nonce_field('link-sawing-coupon-uri-box', 'link-sawing-nonce');

		woocommerce_wp_text_input(array(
			'id'                => 'custom_uri',
			'label'             => __('آدرس کوپن', 'link-sawing'),
			'description'       => '<span class="duplicated_uri_alert"></span>' . __('آدرس ها در حالت case-sensitive اجرا می شوند ، به فرض مثال <strong>BLACKFRIDAY</strong> و <strong>blackfriday</strong> هردو برابر هستند.', 'link-sawing'),
			'value'             => $custom_uri,
			'custom_attributes' => array('data-element-id' => $post->ID),
			//'desc_tip' => true
		));

		$html .= ob_get_contents();
		ob_end_clean();

		// URI preview
		$html .= "<p class=\"form-field coupon-full-url hidden\">";
		$html .= sprintf("<label>%s</label>", __("آدرس کامل کوپن", "link-sawing"));
		$html .= sprintf("<code>%s/<span>%s</span></code>", trim(get_option('home'), "/"), $custom_uri);
		$html .= "</p>";

		$html .= "</div>";

		echo $html;
	}

	/**
	 * Save the custom permalink for specific coupon
	 *
	 * @param int $post_id
	 * @param WC_Coupon $coupon
	 */
	public static function woocommerce_save_coupon_uri($post_id, $coupon)
	{
		global $link_sawing_uris;

		// Verify nonce at first
		if (!isset($_POST['link-sawing-nonce']) || !wp_verify_nonce($_POST['link-sawing-nonce'], 'link-sawing-coupon-uri-box')) {
			return;
		}

		// Do not do anything if post is auto-saved
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		$old_uri = (!empty($link_sawing_uris[$post_id])) ? $link_sawing_uris[$post_id] : "";
		$new_uri = (!empty($_POST['custom_uri'])) ? $_POST['custom_uri'] : "";

		if ($old_uri != $new_uri) {
			$link_sawing_uris[$post_id] = link_sawing_Helper_Functions::sanitize_title($new_uri, true);
			update_option('link-sawing-uris', $link_sawing_uris);
		}
	}

	/**
	 * Check the URL contains a coupon code, and if it does, it adds the coupon code to the cart and redirects to the cart page
	 *
	 * @param array $query The query array.
	 *
	 * @return array The query array.
	 */
	public static function woocommerce_detect_coupon_code($query)
	{
		global $woocommerce, $pm_query;

		// Check if custom URI with coupon URL is requested
		if (!empty($query['shop_coupon']) && !empty($pm_query['id'])) {
			// Check if cart/shop page is set & redirect to it
			$shop_page_id = wc_get_page_id('shop');
			$cart_page_id = wc_get_page_id('cart');


			if (!empty($cart_page_id) && WC()->cart->get_cart_contents_count() > 0) {
				$redirect_page = $cart_page_id;
			} else if (!empty($shop_page_id)) {
				$redirect_page = $shop_page_id;
			}

			$coupon_code = get_the_title($pm_query['id']);

			// Set-up session
			if (!WC()->session->has_session()) {
				WC()->session->set_customer_session_cookie(true);
			}

			// Add the discount code
			if (!WC()->cart->has_discount($coupon_code)) {
				$woocommerce->cart->add_discount(sanitize_text_field($coupon_code));
			}

			// Do redirect
			if (!empty($redirect_page)) {
				wp_safe_redirect(get_permalink($redirect_page));
				exit();
			}
		}

		return $query;
	}
}
