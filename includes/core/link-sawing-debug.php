<?php

/**
 * Additional debug functions for "Link Sawing Pro"
 */
class link_sawing_Debug_Functions
{

	public function __construct()
	{
		add_action('init', array($this, 'debug_data'), 99);
	}

	/**
	 * Map the debug functions to specific hooks
	 */
	public function debug_data()
	{
		add_filter('link_sawing_filter_query', array($this, 'debug_query'), 9, 5);
		add_filter('link_sawing_filter_redirect', array($this, 'debug_redirect'), 9, 3);
		add_filter('wp_redirect', array($this, 'debug_wp_redirect'), 9, 2);

		self::debug_custom_redirects();
		self::debug_custom_fields();
	}

	/**
	 * Debug the WordPress query filtered in the link_sawing_Core_Functions::detect_post(); function
	 *
	 * @param array $query
	 * @param array $old_query
	 * @param array $uri_parts
	 * @param array $pm_query
	 * @param string $content_type
	 *
	 * @return array
	 */
	public function debug_query($query, $old_query = null, $uri_parts = null, $pm_query = null, $content_type = null)
	{
		global $link_sawing;

		if (isset($_REQUEST['debug_url'])) {
			$debug_info['uri_parts']      = $uri_parts;
			$debug_info['old_query_vars'] = $old_query;
			$debug_info['new_query_vars'] = $query;
			$debug_info['pm_query']       = (!empty($pm_query['id'])) ? $pm_query['id'] : "-";
			$debug_info['content_type']   = (!empty($content_type)) ? $content_type : "-";

			// License key info
			if (class_exists('link_sawing_Pro_Functions')) {
				$license_key = $link_sawing->functions['pro-functions']->get_license_key();

				// Mask the license key
				$debug_info['license_key'] = preg_replace('/([^-]+)-([^-]+)-([^-]+)-([^-]+)$/', '***-***-$3', $license_key);
			}

			// Plugin version
			$debug_info['version'] = link_sawing_VERSION;

			self::display_debug_data($debug_info);
		}

		return $query;
	}

	/**
	 * Debug the redirect controlled by link_sawing_Core_Functions::new_uri_redirect_and_404();
	 *
	 * @param string $correct_permalink
	 * @param string $redirect_type
	 * @param mixed $queried_object
	 *
	 * @return string
	 */
	public function debug_redirect($correct_permalink, $redirect_type, $queried_object)
	{
		global $wp_query;

		if (isset($_REQUEST['debug_redirect'])) {
			$debug_info['query_vars']     = $wp_query->query_vars;
			$debug_info['redirect_url']   = (!empty($correct_permalink)) ? $correct_permalink : '-';
			$debug_info['redirect_type']  = (!empty($redirect_type)) ? $redirect_type : "-";
			$debug_info['queried_object'] = (!empty($queried_object)) ? $queried_object : "-";

			self::display_debug_data($debug_info);
		}

		return $correct_permalink;
	}

	/**
	 * Debug wp_redirect() function used in 3rd party plugins
	 *
	 * @param string $url
	 * @param string $status
	 *
	 * @return string
	 */
	public function debug_wp_redirect($url, $status)
	{
		if (isset($_GET['debug_wp_redirect'])) {
			$debug_info['url']       = $url;
			$debug_info['status']    = $status;
			$debug_info['backtrace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

			self::display_debug_data($debug_info);
		}

		return $url;
	}

	/**
	 * Display the list of native & custom redirects
	 */
	public function debug_custom_redirects()
	{
		global $link_sawing_uris, $link_sawing_redirects, $link_sawing_ignore_permalink_filters;

		if (isset($_GET['debug_custom_redirects']) && current_user_can('manage_options')) {
			$home_url       = link_sawing_Helper_Functions::get_permalink_base();
			$csv = array();

			if (!empty($link_sawing_uris)) {
				$link_sawing_ignore_permalink_filters = true;

				// Native redirects
				foreach ($link_sawing_uris as $element_id => $uri) {
					if (is_numeric($element_id)) {
						$original_permalink = user_trailingslashit(get_permalink($element_id));
					} else {
						$term_id = preg_replace("/[^0-9]/", "", $element_id);
						$term    = get_term($term_id);

						if (empty($term->taxonomy)) {
							continue;
						}

						$original_permalink = user_trailingslashit(get_term_link($term->term_id, $term->taxonomy));
					}

					$custom_permalink   = user_trailingslashit($home_url . "/" . $uri);

					if ($original_permalink == $custom_permalink && $original_permalink !== '/') {
						continue;
					}

					$csv[$element_id] = array(
						'type' => 'native_redirect',
						'from' => $original_permalink,
						'to'   => $custom_permalink
					);
				}
			}

			// Custom redirects
			if ($link_sawing_redirects) {
				foreach ($link_sawing_redirects as $element_id => $redirects) {
					if (empty($link_sawing_uris[$element_id])) {
						continue;
					}
					$custom_permalink = user_trailingslashit($home_url . "/" . $link_sawing_uris[$element_id]);

					if (is_array($redirects)) {
						$redirects       = array_values($redirects);
						// $redirects_count = count( $redirects );

						foreach ($redirects as $index => $redirect) {
							$redirect_url = user_trailingslashit($home_url . "/" . $redirect);

							$csv["extra-redirect-{$index}-{$element_id}"] = array(
								'type' => 'extra_redirect',
								'from' => $redirect_url,
								'to'   => $custom_permalink
							);
						}
					}
				}
			}

			echo self::output_csv($csv);
			die();
		}
	}

	/**
	 * Display the list of all custom fields assigned to specific post
	 */
	public static function debug_custom_fields()
	{
		global $pagenow;

		if (!isset($_GET['debug_custom_fields'])) {
			return;
		}

		if ($pagenow == 'post.php' && isset($_GET['post'])) {
			$post_id       = intval($_GET['post']);
			$custom_fields = get_post_meta($post_id);
		}

		if ($pagenow == 'term.php' && isset($_GET['tag_ID'])) {
			$term_id       = intval($_GET['tag_ID']);

			$custom_fields = get_term_meta($term_id);
		}

		if (isset($custom_fields)) {
			self::display_debug_data($custom_fields);
		}
	}

	/**
	 * A helper function used to display the debug data in various functions
	 *
	 * @param mixed $debug_info
	 */
	public static function display_debug_data($debug_info)
	{
		$debug_txt = print_r($debug_info, true);
		$debug_txt = sprintf("<pre style=\"display:block;\">%s</pre>", esc_html($debug_txt));

		wp_die($debug_txt);
	}

	/**
	 * Generate a CSV file from array
	 *
	 * @param array $array
	 * @param string $filename
	 */
	public static function output_csv($array, $filename = 'debug.csv')
	{
		if (count($array) == 0) {
			return null;
		}

		// Disable caching
		$now = gmdate("D, d M Y H:i:s");
		header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
		header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
		header("Last-Modified: {$now} GMT");

		// Force download
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header('Content-Type: text/csv');

		// Disposition / encoding on response body
		header("Content-Disposition: attachment;filename={$filename}");
		header("Content-Transfer-Encoding: binary");

		ob_start();

		$df = fopen("php://output", 'w');

		fputcsv($df, array_keys(reset($array)));
		foreach ($array as $row) {
			fputcsv($df, $row);
		}
		fclose($df);

		echo ob_get_clean();
		die();
	}
}
