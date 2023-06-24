<?php

/**
 * Functions used to create, edit and remove custom permalinks
 */
class link_sawing_URI_Functions
{

	public function __construct()
	{
	}

	/**
	 * Get the custom permalink's array key for specific post or term
	 *
	 * @param int|string $element_id
	 * @param bool $is_tax
	 *
	 * @return int|string
	 */
	public static function get_single_uri_key($element_id, $is_tax = false)
	{
		// Check if the element ID is numeric
		if (empty($element_id) || !is_numeric($element_id)) {
			return '';
		}

		if ($is_tax) {
			$element_id = "tax-{$element_id}";
		}

		return $element_id;
	}

	/**
	 * Get the single custom permalink
	 *
	 * @param WP_Post|WP_Term|int $element
	 * @param bool $native_uri
	 * @param bool $no_fallback
	 */
	public static function get_single_uri($element, $native_uri = false, $no_fallback = false, $is_tax = false)
	{
		if (!empty($element->term_id)) {
			$element_id = $element->term_id;
			$is_term    = true;
		} else if (!empty($element->ID)) {
			$element_id = $element->ID;
			$is_term    = false;
		} else if (is_numeric($element)) {
			$element_id = $element;
			$is_term    = $is_tax;
		} else {
			return '';
		}

		if ($is_term) {
			$final_uri = (class_exists('link_sawing_URI_Functions_Tax')) ? link_sawing_URI_Functions_Tax::get_term_uri($element_id, $native_uri, $no_fallback) : '';
		} else {
			$final_uri = link_sawing_URI_Functions_Post::get_post_uri($element_id, $native_uri, $no_fallback);
		}

		return $final_uri;
	}

	/**
	 * Save single custom permalink to the custom permalinks array
	 *
	 * @param int|string $element
	 * @param string $element_uri
	 * @param bool $is_tax
	 * @param bool $db_save
	 */
	public static function save_single_uri($element, $element_uri = null, $is_tax = false, $db_save = false)
	{
		global $link_sawing_uris;

		// Get the element key
		$element_key = self::get_single_uri_key($element, $is_tax);

		// Save the custom permalink if the URI is not empty
		if (!empty($element_key) && !empty($element_uri)) {
			$link_sawing_uris[$element_key] = link_sawing_Helper_Functions::sanitize_title($element_uri, true);

			if ($db_save) {
				self::save_all_uris($link_sawing_uris);
			}
		}
	}

	/**
	 * Remove single custom permalink from the custom permalinks array
	 *
	 * @param int|string $element
	 * @param bool $is_tax
	 * @param bool $db_save
	 */
	public static function remove_single_uri($element, $is_tax = false, $db_save = false)
	{
		global $link_sawing_uris;

		// Get the element key
		$element_key = self::get_single_uri_key($element, $is_tax);

		// Check if the custom permalink is assigned to this post
		if (!empty($element_key) && isset($link_sawing_uris[$element_key])) {
			unset($link_sawing_uris[$element_key]);
		}

		if ($db_save) {
			self::save_all_uris($link_sawing_uris);
		}
	}

	/**
	 * Save the array with custom permalinks
	 *
	 * @param array $updated_uris
	 */
	public static function save_all_uris($updated_uris = null)
	{
		if (is_null($updated_uris)) {
			global $link_sawing_uris;
			$updated_uris = $link_sawing_uris;
		}

		if (is_array($updated_uris) && !empty($updated_uris)) {
			update_option('link-sawing-uris', $updated_uris);
		}
	}
}
