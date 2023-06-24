<?php

/**
 * A set of functions for processing and applying the custom permalink to terms.
 */
class link_sawing_URI_Functions_Tax
{

	public function __construct()
	{
		add_action('init', array($this, 'init'), 100);
		add_action('rest_api_init', array($this, 'init'));

		add_filter('term_link', array($this, 'custom_tax_permalinks'), 999, 2);

		add_action('quick_edit_custom_box', array($this, 'quick_edit_column_form'), 999, 3);
	}

	/**
	 * Allow to edit URIs from "Edit Term" admin pages (register hooks)
	 */
	public function init()
	{
		global $link_sawing_options;

		$all_taxonomies = link_sawing_Helper_Functions::get_taxonomies_array();

		// Add "URI Editor" to "Quick Edit" for all taxonomies
		foreach ($all_taxonomies as $tax => $label) {
			// Check if taxonomy is allowed
			if (link_sawing_Helper_Functions::is_taxonomy_disabled($tax)) {
				continue;
			}

			add_action("edited_{$tax}", array($this, 'update_term_uri'), 10, 2);
			add_action("create_{$tax}", array($this, 'update_term_uri'), 10, 2);
			add_action("delete_{$tax}", array($this, 'remove_term_uri'), 10, 2);

			// Check the user capabilities
			if (is_admin()) {
				$edit_uris_cap = (!empty($link_sawing_options['general']['edit_uris_cap'])) ? $link_sawing_options['general']['edit_uris_cap'] : 'publish_posts';
				if (current_user_can($edit_uris_cap)) {
					add_action("{$tax}_add_form_fields", array($this, 'edit_uri_box'), 10, 1);
					add_action("{$tax}_edit_form_fields", array($this, 'edit_uri_box'), 10, 1);
					add_filter("manage_edit-{$tax}_columns", array($this, 'quick_edit_column'));
					add_filter("manage_{$tax}_custom_column", array($this, 'quick_edit_column_content'), 10, 3);
				}
			}
		}
	}

	/**
	 * Apply the custom permalinks to the terms
	 *
	 * @param string $permalink
	 * @param WP_Term|int $term
	 *
	 * @return string
	 */
	function custom_tax_permalinks($permalink, $term)
	{
		global $link_sawing_uris, $link_sawing_options, $link_sawing_ignore_permalink_filters;

		// Do not filter permalinks in Customizer
		if (function_exists('is_customize_preview') && is_customize_preview()) {
			return $permalink;
		}

		// Do not filter in WPML String Editor
		if (!empty($_REQUEST['icl_ajx_action']) && $_REQUEST['icl_ajx_action'] == 'icl_st_save_translation') {
			return $permalink;
		}

		// Do not filter if $link_sawing_ignore_permalink_filters global is set
		if (!empty($link_sawing_ignore_permalink_filters)) {
			return $permalink;
		}

		$term = (is_numeric($term)) ? get_term($term) : $term;

		// Check if the term is allowed
		if (empty($term->term_id) || link_sawing_Helper_Functions::is_term_excluded($term)) {
			return $permalink;
		}

		// Get term id
		$term_id = $term->term_id;

		// Save the old permalink to separate variable
		$old_permalink = $permalink;

		if (isset($link_sawing_uris["tax-{$term_id}"])) {
			// Start with homepage URL
			$permalink = link_sawing_Helper_Functions::get_permalink_base($term);

			// Encode URI?
			if (!empty($link_sawing_options['general']['decode_uris'])) {
				$permalink .= rawurldecode("/{$link_sawing_uris["tax-{$term_id}"]}");
			} else {
				$permalink .= link_sawing_Helper_Functions::encode_uri("/{$link_sawing_uris["tax-{$term_id}"]}");
			}
		} else if (!empty($link_sawing_options['general']['decode_uris'])) {
			$permalink = rawurldecode($permalink);
		}

		return apply_filters('link_sawing_filter_final_term_permalink', $permalink, $term, $old_permalink);
	}

	/**
	 * Check if the provided slug is unique and then update it with SQL query.
	 *
	 * @param string $slug
	 * @param int $id
	 *
	 * @return string
	 */
	static function update_slug_by_id($slug, $id)
	{
		global $wpdb;

		// Update slug and make it unique
		$term = get_term(intval($id));
		$slug = (empty($slug)) ? get_the_title($term->name) : $slug;
		$slug = sanitize_title($slug);

		$new_slug = wp_unique_term_slug($slug, $term);
		$wpdb->query($wpdb->prepare("UPDATE {$wpdb->terms} SET slug = %s WHERE term_id = %d", $new_slug, $id));

		return $new_slug;
	}

	/**
	 * Get the currently used custom permalink (or default/empty URI)
	 *
	 * @param int $term_id
	 * @param bool $native_uri
	 * @param bool $no_fallback
	 *
	 * @return string
	 */
	public static function get_term_uri($term_id, $native_uri = false, $no_fallback = false)
	{
		global $link_sawing_uris;

		// Check if input is term object
		$term = (isset($term_id->term_id)) ? $term_id->term_id : get_term($term_id);

		if (!empty($link_sawing_uris["tax-{$term_id}"])) {
			$final_uri = $link_sawing_uris["tax-{$term_id}"];
		} else if (!$no_fallback) {
			$final_uri = self::get_default_term_uri($term->term_id, $native_uri);
		} else {
			$final_uri = '';
		}

		return $final_uri;
	}

	/**
	 * Get the default custom permalink (not overwritten by the user) or native URI (unfiltered)
	 *
	 * @param WP_Term|int $term
	 * @param bool $native_uri
	 * @param bool $check_if_disabled
	 *
	 * @return string
	 */
	public static function get_default_term_uri($term, $native_uri = false, $check_if_disabled = false)
	{
		global $link_sawing_options, $link_sawing_permastructs, $wp_taxonomies, $icl_adjust_id_url_filter_off;

		// Disable WPML adjust ID filter
		$icl_adjust_id_url_filter_off = true;

		// 1. Load all bases & term
		$term = is_object($term) ? $term : get_term($term);
		// $term_id = $term->term_id;
		$taxonomy_name   = $term->taxonomy;
		$taxonomy        = get_taxonomy($taxonomy_name);
		$term_slug       = $term->slug;
		$top_parent_slug = '';

		// 1A. Check if taxonomy is allowed
		if ($check_if_disabled && link_sawing_Helper_Functions::is_taxonomy_disabled($taxonomy)) {
			return '';
		}

		// 2A. Get the native permastructure
		$native_permastructure = link_sawing_Helper_Functions::get_default_permastruct($taxonomy_name, true);

		// 2B. Get the permastructure
		if ($native_uri || empty($link_sawing_permastructs['taxonomies'][$taxonomy_name])) {
			$permastructure = $native_permastructure;
		} else {
			$permastructure = apply_filters('link_sawing_filter_permastructure', $link_sawing_permastructs['taxonomies'][$taxonomy_name], $term);
		}

		// 2C. Set the permastructure
		$default_base = (!empty($permastructure)) ? trim($permastructure, '/') : "";

		// 3A. Check if the taxonomy has custom permastructure set
		if (empty($default_base) && !isset($link_sawing_permastructs['taxonomies'][$taxonomy_name])) {
			if ('category' == $taxonomy_name) {
				$default_uri = "?cat={$term->term_id}";
			} elseif ($taxonomy->query_var) {
				$default_uri = "?{$taxonomy->query_var}={$term_slug}";
			} else if (!empty($term_slug)) {
				$default_uri = "?taxonomy={$taxonomy_name}&term={$term_slug}";
			} else {
				$default_uri = '';
			}
		} // 3B. Use custom permastructure
		else {
			$default_uri = $default_base;

			// 3B. Get the full slug
			$term_slug        = link_sawing_Helper_Functions::remove_slashes($term_slug);
			$custom_slug      = $full_custom_slug = link_sawing_Helper_Functions::force_custom_slugs($term_slug, $term);
			$full_native_slug = $term_slug;

			// Add ancestors to hierarchical taxonomy
			if (is_taxonomy_hierarchical($taxonomy_name)) {
				$ancestors = get_ancestors($term->term_id, $taxonomy_name, 'taxonomy');

				foreach ($ancestors as $ancestor) {
					$ancestor_term = get_term($ancestor, $taxonomy_name);

					$full_native_slug = $ancestor_term->slug . '/' . $full_native_slug;
					$full_custom_slug = link_sawing_Helper_Functions::force_custom_slugs($ancestor_term->slug, $ancestor_term) . '/' . $full_custom_slug;
				}

				// Get top parent term
				if (strpos($default_uri, "%{$taxonomy_name}_top%") === false || strpos($default_uri, "%term_top%") === false) {
					$top_parent_slug = link_sawing_Helper_Functions::get_term_full_slug($term, $ancestors, 3, $native_uri);
				}
			}

			// Allow filter the default slug (only custom permalinks)
			if (!$native_uri) {
				$full_slug = apply_filters('link_sawing_filter_default_term_slug', $full_custom_slug, $term, $term->name);
			} else {
				$full_slug = $full_native_slug;
			}

			// Get the taxonomy slug
			if (!empty($wp_taxonomies[$taxonomy_name]->rewrite['slug'])) {
				$taxonomy_name_slug = $wp_taxonomies[$taxonomy_name]->rewrite['slug'];
			} else if (is_string($wp_taxonomies[$taxonomy_name]->rewrite)) {
				$taxonomy_name_slug = $wp_taxonomies[$taxonomy_name]->rewrite;
			} else {
				$taxonomy_name_slug = $taxonomy_name;
			}
			$taxonomy_name_slug = apply_filters('link_sawing_filter_taxonomy_slug', $taxonomy_name_slug, $term, $taxonomy_name);

			$slug_tags             = array("%term_name%", "%term_flat%", "%{$taxonomy_name}%", "%{$taxonomy_name}_flat%", "%term_top%", "%{$taxonomy_name}_top%", "%native_slug%", "%taxonomy%", "%term_id%");
			$slug_tags_replacement = array($full_slug, $custom_slug, $full_slug, $custom_slug, $top_parent_slug, $top_parent_slug, $full_native_slug, $taxonomy_name_slug, $term->term_id);

			// Check if any term tag is present in custom permastructure
			$do_not_append_slug = (!empty($link_sawing_options['permastructure-settings']['do_not_append_slug']['taxonomies'][$taxonomy_name])) ? true : false;
			$do_not_append_slug = apply_filters("link_sawing_do_not_append_slug", $do_not_append_slug, $taxonomy, $term);
			if (!$do_not_append_slug) {
				foreach ($slug_tags as $tag) {
					if (strpos($default_uri, $tag) !== false) {
						$do_not_append_slug = true;
						break;
					}
				}
			}

			// Replace the term tags with slugs or append the slug if no term tag is defined
			if (!empty($do_not_append_slug)) {
				$default_uri = str_replace($slug_tags, $slug_tags_replacement, $default_uri);
			} else {
				$default_uri .= "/{$full_slug}";
			}
		}

		// Enable WPML adjust ID filter
		$icl_adjust_id_url_filter_off = false;

		return apply_filters('link_sawing_filter_default_term_uri', $default_uri, $term->slug, $term, $term_slug, $native_uri);
	}

	/**
	 * Get array with all term items based on the user-selected settings in the "Bulk tools" form
	 *
	 * @return array|false
	 */
	public static function get_items()
	{
		global $wpdb, $link_sawing_options;

		// Check if taxonomies are not empty
		if (empty($_POST['taxonomies']) || !is_array($_POST['taxonomies'])) {
			return false;
		}

		$taxonomy_names_array = array_map('sanitize_key', $_POST['taxonomies']);
		$taxonomy_names       = implode("', '", $taxonomy_names_array);

		// Filter the terms by IDs
		$where = '';
		if (!empty($_POST['ids'])) {
			// Remove whitespaces and prepare array with IDs and/or ranges
			$ids = esc_sql(preg_replace('/\s*/m', '', $_POST['ids']));
			preg_match_all("/([\d]+(?:-?[\d]+)?)/x", $ids, $groups);

			// Prepare the extra ID filters
			$where .= "AND (";
			foreach ($groups[0] as $group) {
				$where .= ($group == reset($groups[0])) ? "" : " OR ";
				// A. Single number
				if (is_numeric($group)) {
					$where .= "(t.term_id = {$group})";
				} // B. Range
				else if (substr_count($group, '-')) {
					$range_edges = explode("-", $group);
					$where       .= "(t.term_id BETWEEN {$range_edges[0]} AND {$range_edges[1]})";
				}
			}
			$where .= ")";
		}

		// Get excluded items
		$excluded_terms = (array) apply_filters('link_sawing_excluded_term_ids', array());
		if (!empty($excluded_terms)) {
			$where .= sprintf(" AND t.term_id NOT IN ('%s') ", implode("', '", $excluded_terms));
		}

		// Check the auto-update mode
		// A. Allow only user-approved posts
		if (!empty($link_sawing_options["general"]["auto_update_uris"]) && $link_sawing_options["general"]["auto_update_uris"] == 2) {
			$where .= " AND meta_value IN (1, -1) ";
		} // B. Allow all posts not disabled by the user
		else {
			$where .= " AND (meta_value IS NULL OR meta_value IN (1, -1)) ";
		}

		// Get the rows before they are altered
		return $wpdb->get_results("SELECT t.slug, t.name, t.term_id, tt.taxonomy FROM {$wpdb->terms} AS t INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_id = t.term_id LEFT JOIN {$wpdb->termmeta} AS tm ON (tm.term_id = t.term_id AND tm.meta_key = 'auto_update_uri') WHERE tt.taxonomy IN ('{$taxonomy_names}') {$where}", ARRAY_A);
	}

	/**
	 * Process the custom permalinks or (native slugs) in "Find & replace" tool
	 *
	 * @param array $chunk
	 * @param string $mode
	 * @param string $old_string
	 * @param string $new_string
	 *
	 * @return array|false
	 */
	public static function find_and_replace($chunk = null, $mode = '', $old_string = '', $new_string = '')
	{
		global $link_sawing_uris;

		// Reset variables
		$updated_slugs_count = 0;
		$updated_array       = array();
		$errors              = '';

		// Get the rows before they are altered
		$terms_to_update = ($chunk) ? $chunk : self::get_items();

		// Now if the array is not empty use IDs from each subarray as a key
		if ($terms_to_update && empty($errors)) {
			foreach ($terms_to_update as $row) {
				// Prepare variables
				$this_term         = get_term($row['term_id']);
				$term_permalink_id = "tax-{$row['term_id']}";

				// Get default & native URL
				$native_uri    = self::get_default_term_uri($this_term, true);
				$default_uri   = self::get_default_term_uri($this_term);
				$old_term_name = $row['slug'];
				$old_uri       = (isset($link_sawing_uris[$term_permalink_id])) ? $link_sawing_uris[$term_permalink_id] : $native_uri;

				// Do replacement on slugs (non-REGEX)
				if (preg_match("/^\/.+\/[a-z]*$/i", $old_string)) {
					// Use $_POST['old_string'] directly here & fix double slashes problem
					$regex   = stripslashes(trim(sanitize_text_field($_POST['old_string']), "/"));
					$regex   = preg_quote($regex, '~');
					$pattern = "~{$regex}~";

					$new_term_name = ($mode == 'slugs') ? preg_replace($pattern, $new_string, $old_term_name) : $old_term_name;
					$new_uri       = ($mode != 'slugs') ? preg_replace($pattern, $new_string, $old_uri) : $old_uri;
				} else {
					$new_term_name = ($mode == 'slugs') ? str_replace($old_string, $new_string, $old_term_name) : $old_term_name; // Term slug is changed only in first mode
					$new_uri       = ($mode != 'slugs') ? str_replace($old_string, $new_string, $old_uri) : $old_uri;
				}

				// Check if native slug should be changed
				if (($mode == 'slugs') && ($old_term_name != $new_term_name)) {
					self::update_slug_by_id($new_term_name, $row['term_id']);
				}

				if (($old_uri != $new_uri) || ($old_term_name != $new_term_name)) {
					$link_sawing_uris[$term_permalink_id] = trim($new_uri, '/');
					$updated_array[]                              = array('item_title' => $row['name'], 'ID' => $row['term_id'], 'old_uri' => $old_uri, 'new_uri' => $new_uri, 'old_slug' => $old_term_name, 'new_slug' => $new_term_name, 'tax' => $this_term->taxonomy);
					$updated_slugs_count++;
				}

				do_action('link_sawing_updated_term_uri', $row['term_id'], $new_uri, $old_uri, $native_uri, $default_uri);
			}

			// Filter array before saving
			if (is_array($link_sawing_uris)) {
				$link_sawing_uris = array_filter($link_sawing_uris);
				update_option('link-sawing-uris', $link_sawing_uris);
			}

			$output = array('updated' => $updated_array, 'updated_count' => $updated_slugs_count);
		}

		return (!empty($output)) ? $output : false;
	}

	/**
	 * Process the custom permalinks or (native slugs) in "Regenerate/reset" tool
	 *
	 * @param array $chunk
	 * @param string $mode
	 *
	 * @return array|false
	 */
	static function regenerate_all_permalinks($chunk = null, $mode = '')
	{
		global $link_sawing_uris;

		// Reset variables
		$updated_slugs_count = 0;
		$updated_array       = array();
		$errors              = '';

		// Get the rows before they are altered
		$terms_to_update = ($chunk) ? $chunk : self::get_items();

		// Now if the array is not empty use IDs from each subarray as a key
		if ($terms_to_update && empty($errors)) {
			foreach ($terms_to_update as $row) {
				// Prepare variables
				$this_term         = get_term($row['term_id']);
				$term_permalink_id = "tax-{$row['term_id']}";

				// Get default & native URL
				$native_uri    = self::get_default_term_uri($this_term, true);
				$default_uri   = self::get_default_term_uri($this_term);
				$old_term_name = $row['slug'];
				$old_uri       = (isset($link_sawing_uris[$term_permalink_id])) ? $link_sawing_uris[$term_permalink_id] : '';
				$correct_slug  = ($mode == 'slugs') ? sanitize_title($row['name']) : link_sawing_Helper_Functions::sanitize_title($row['name']);

				// Process URI & slug
				$new_slug      = wp_unique_term_slug($correct_slug, $this_term);
				$new_term_name = ($mode == 'slugs') ? $new_slug : $old_term_name; // Post name is changed only in first mode

				// Prepare the new URI
				if ($mode == 'slugs') {
					$new_uri = ($old_uri) ? $old_uri : $native_uri;
				} else if ($mode == 'native') {
					$new_uri = $native_uri;
				} else {
					$new_uri = $default_uri;
				}

				// Check if native slug should be changed
				if (($mode == 'slugs') && ($old_term_name != $new_term_name)) {
					self::update_slug_by_id($new_term_name, $row['term_id']);
				}

				if (($old_uri != $new_uri) || ($old_term_name != $new_term_name)) {
					$link_sawing_uris[$term_permalink_id] = $new_uri;
					$updated_array[]                              = array('item_title' => $row['name'], 'ID' => $row['term_id'], 'old_uri' => $old_uri, 'new_uri' => $new_uri, 'old_slug' => $old_term_name, 'new_slug' => $new_term_name, 'tax' => $this_term->taxonomy);
					$updated_slugs_count++;
				}

				do_action('link_sawing_updated_term_uri', $row['term_id'], $new_uri, $old_uri, $native_uri, $default_uri);
			}

			// Filter array before saving
			if (is_array($link_sawing_uris)) {
				$link_sawing_uris = array_filter($link_sawing_uris);
				update_option('link-sawing-uris', $link_sawing_uris);
			}

			$output = array('updated' => $updated_array, 'updated_count' => $updated_slugs_count);
			wp_reset_postdata();
		}

		return (!empty($output)) ? $output : false;
	}

	/**
	 * Save the custom permalinks in "Bulk URI Editor" tool
	 *
	 * @return array|false
	 */
	static public function update_all_permalinks()
	{
		global $link_sawing_uris;

		// Setup needed variables
		$updated_slugs_count = 0;
		$updated_array       = array();

		$old_uris = $link_sawing_uris;
		$new_uris = isset($_POST['uri']) ? $_POST['uri'] : array();

		// Double check if the slugs and ids are stored in arrays
		if (!is_array($new_uris)) {
			$new_uris = explode(',', $new_uris);
		}

		if (!empty($new_uris)) {
			foreach ($new_uris as $id => $new_uri) {
				// Remove prefix from field name to obtain term id
				$term_id = filter_var(str_replace('tax-', '', $id), FILTER_SANITIZE_NUMBER_INT);

				// Prepare variables
				$this_term = get_term($term_id);

				// Get default & native URL
				$native_uri  = self::get_default_term_uri($this_term, true);
				$default_uri = self::get_default_term_uri($this_term);
				$old_uri     = isset($old_uris[$id]) ? trim($old_uris[$id], "/") : "";

				// Process new values - empty entries will be treated as default values
				$new_uri = link_sawing_Helper_Functions::sanitize_title($new_uri);
				$new_uri = (!empty($new_uri)) ? trim($new_uri, "/") : $default_uri;

				if ($new_uri != $old_uri) {
					$old_uris[$id] = $new_uri;
					$updated_array[] = array('item_title' => $this_term->name, 'ID' => $term_id, 'old_uri' => $old_uri, 'new_uri' => $new_uri, 'tax' => $this_term->taxonomy);
					$updated_slugs_count++;
				}

				do_action('link_sawing_updated_term_uri', $term_id, $new_uri, $old_uri, $native_uri, $default_uri);
			}

			// Filter array before saving & append the global
			if (is_array($link_sawing_uris)) {
				$old_uris = $link_sawing_uris = array_filter($old_uris);
				update_option('link-sawing-uris', $old_uris);
			}

			$output = array('updated' => $updated_array, 'updated_count' => $updated_slugs_count);
		}

		return (!empty($output)) ? $output : false;
	}

	/**
	 * Allow to edit URIs from "New Term" & "Edit Term" admin pages
	 *
	 * @param WP_Term $term
	 */
	public function edit_uri_box($term = '')
	{
		// Check if the term is excluded
		if (empty($term) || link_sawing_Helper_Functions::is_term_excluded($term)) {
			return;
		}

		// Stop the hook (if needed)
		if (!empty($term->taxonomy)) {
			$show_uri_editor = apply_filters("link_sawing_show_uri_editor_term", true, $term, $term->taxonomy);

			if (!$show_uri_editor) {
				return;
			}
		}

		$label       = __("آدرس فعلی", "link-sawing");
		$description = __("برای استفاده از پیوند پیش فرض پاک کردن/صرف نظر کافیست.", "link-sawing");

		// A. New term
		if (empty($term->term_id)) {
			$html = "<div class=\"form-field\">";
			$html .= sprintf("<label for=\"term_meta[uri]\">%s</label>", $label);
			$html .= "<input type=\"text\" name=\"custom_uri\" id=\"custom_uri\" value=\"\">";
			$html .= sprintf("<p class=\"description\">%s</p>", $description);
			$html .= "</div>";

			// Append nonce field
			$html .= wp_nonce_field('link-sawing-edit-uri-box', 'link-sawing-nonce', true, false);
		} // B. Edit term
		else {
			$html = "<tr id=\"link-sawing\" class=\"form-field link-sawing-edit-term link-sawing\">";
			$html .= sprintf("<th scope=\"row\"><label for=\"custom_uri\">%s</label></th>", $label);
			$html .= "<td><div>";
			$html .= link_sawing_Admin_Functions::display_uri_box($term);
			$html .= "</div></td>";
			$html .= "</tr>";
		}

		echo $html;
	}

	/**
	 * Add "Current URI" input field to "Quick Edit" form
	 *
	 * @param array $columns
	 *
	 * @return array mixed
	 */
	function quick_edit_column($columns)
	{
		return (is_array($columns)) ? array_merge($columns, array('link-sawing-col' => __('آدرس فعلی', 'link-sawing'))) : $columns;
	}

	/**
	 * Display the URI of the current term in the "Current URI" column
	 *
	 * @param string $content The column content.
	 * @param string $column_name The name of the column to display. In this case, we named our column link-sawing-col.
	 * @param int $term_id The ID of the term.
	 *
	 * @return string
	 */
	function quick_edit_column_content($content, $column_name, $term_id)
	{
		global $link_sawing_uris, $link_sawing_options;

		if ($column_name == "link-sawing-col") {
			$auto_update_val = get_term_meta($term_id, "auto_update_uri", true);
			$disabled        = (!empty($auto_update_val)) ? $auto_update_val : $link_sawing_options["general"]["auto_update_uris"];

			$uri = (!empty($link_sawing_uris["tax-{$term_id}"])) ? self::get_term_uri($term_id) : self::get_term_uri($term_id, true);

			$content = sprintf('<span class="link-sawing-col-uri" data-disabled="%s">%s</span>', intval($disabled), $uri);
		}

		return $content;
	}

	/**
	 * Display the simplified URI Editor in "Quick Edit" mode
	 *
	 * @param string $column_name
	 * @param string $post_type
	 * @param string $taxonomy
	 */
	function quick_edit_column_form($column_name, $post_type, $taxonomy = '')
	{
		if ($taxonomy && $column_name == 'link-sawing-col') {
			echo link_sawing_Admin_Functions::quick_edit_column_form();
		}
	}

	/**
	 * Update URI from "Edit Term" admin page / Set the custom permalink for new term item
	 *
	 * @param int $term_id Term ID.
	 * @param int $tt_term_id Term taxonomy ID.
	 */
	function update_term_uri($term_id, $tt_term_id)
	{
		global $link_sawing_uris, $link_sawing_options, $wp_current_filter;

		// Term ID must be defined
		if (empty($term_id)) {
			return;
		}

		// Check if term was added via "Edit Post" page
		if (!empty($wp_current_filter[0]) && strpos($wp_current_filter[0], 'wp_ajax_add') !== false && empty($_POST['custom_uri'])) {
			$force_default_uri = true;
		} else if (isset($_POST['custom_uri']) && (!isset($_POST['link-sawing-nonce']) || !wp_verify_nonce($_POST['link-sawing-nonce'], 'link-sawing-edit-uri-box'))) {
			return;
		}

		// Get the term object
		$this_term         = get_term($term_id);
		$term_permalink_id = "tax-{$term_id}";

		// Check if the term is allowed
		if (empty($this_term->taxonomy) || link_sawing_Helper_Functions::is_term_excluded($this_term)) {
			return;
		}

		// Get auto-update URI setting (if empty use global setting)
		if (!empty($_POST["auto_update_uri"])) {
			$auto_update_uri_current = intval($_POST["auto_update_uri"]);
		} else if (!empty($_POST["action"]) && $_POST['action'] == 'inline-save') {
			$auto_update_uri_current = get_term_meta($term_id, "auto_update_uri", true);
		}
		$auto_update_uri = (!empty($auto_update_uri_current)) ? $auto_update_uri_current : $link_sawing_options["general"]["auto_update_uris"];

		// Get default & native & user-submitted URIs
		$native_uri  = self::get_default_term_uri($this_term, true);
		$default_uri = self::get_default_term_uri($this_term);
		$old_uri     = (isset($link_sawing_uris[$term_permalink_id])) ? $link_sawing_uris[$term_permalink_id] : "";

		// A. Check if the URI is provided in the input field
		if (!empty($_POST['custom_uri']) && empty($force_default_uri) && empty($_POST['post_ID']) && $auto_update_uri != 1) {
			$new_uri = link_sawing_Helper_Functions::sanitize_title($_POST['custom_uri']);
		} // B. Do not overwrite a previously stored URI
		else if (!isset($_POST['custom_uri']) && !empty($old_uri) && $auto_update_uri != 1) {
			$new_uri = '';
		} // C. If the user removes the whole URI or adds a new term through "Edit Post", the default URI should be used.
		else {
			$new_uri = $default_uri;
		}

		// Save or remove "Auto-update URI" settings
		if (!empty($auto_update_uri_current)) {
			update_term_meta($term_id, "auto_update_uri", $auto_update_uri_current);
		} elseif (isset($_POST['auto_update_uri'])) {
			delete_term_meta($term_id, "auto_update_uri");
		}

		// Check if the URI should be updated
		$allow_update_uri = apply_filters("link_sawing_update_term_uri_{$this_term->taxonomy}", true, $this_term);

		// A. The update URI process is stopped by the hook above or disabled in "Auto-update" settings
		if (!$allow_update_uri || (!empty($auto_update_uri) && $auto_update_uri == 2)) {
			$uri_saved = false;
		} // B. Save the URI only if $new_uri variable is set
		else if (is_array($link_sawing_uris) && !empty($new_uri)) {
			$link_sawing_uris[$term_permalink_id] = $new_uri;
			$uri_saved                                    = update_option('link-sawing-uris', $link_sawing_uris);
		} // C. The $new_uri variable is empty
		else {
			$uri_saved = false;
		}

		do_action('link_sawing_updated_term_uri', $term_id, $new_uri, $old_uri, $native_uri, $default_uri, $single_update = true, $uri_saved);
	}

	/**
	 * Remove URI from options array after term is moved to the trash
	 *
	 * @param int $term_id
	 */
	function remove_term_uri($term_id)
	{
		global $link_sawing_uris;

		// Check if the custom permalink is assigned to this post
		if (isset($link_sawing_uris["tax-{$term_id}"])) {
			unset($link_sawing_uris["tax-{$term_id}"]);
		}

		if (is_array($link_sawing_uris)) {
			update_option('link-sawing-uris', $link_sawing_uris);
		}
	}
}
