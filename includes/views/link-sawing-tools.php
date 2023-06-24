<?php

/**
 * Display the page where the slugs could be regenerated or replaced
 */
class link_sawing_Tools
{

	public function __construct()
	{
		add_filter('link_sawing_sections', array($this, 'add_admin_section'), 1);
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
		$admin_sections['tools'] = array(
			'name'        => __('ابزارها', 'link-sawing'),
			'subsections' => array(
				'duplicates'       => array(
					'name'     => __('پیوند های تکراری', 'link-sawing'),
					'function' => array('class' => 'link_sawing_Tools', 'method' => 'duplicates_output')
				),
				'find_and_replace' => array(
					'name'     => __('پیدا کردن و جایگزین کردن', 'link-sawing'),
					'function' => array('class' => 'link_sawing_Tools', 'method' => 'find_and_replace_output')
				),
				'regenerate_slugs' => array(
					'name'     => __('بازسازی/بازنشانی', 'link-sawing'),
					'function' => array('class' => 'link_sawing_Tools', 'method' => 'regenerate_slugs_output')
				),
				'stop_words'       => array(
					'name'     => __('کلمات را متوقف کنید', 'link-sawing'),
					'function' => array('class' => 'link_sawing_Admin_Functions', 'method' => 'pro_text')
				),
				'import'           => array(
					'name'     => __('پیوندهای سفارشی', 'link-sawing'),
					'function' => array('class' => 'link_sawing_Admin_Functions', 'method' => 'pro_text')
				)
			)
		);

		return $admin_sections;
	}

	/**
	 * Display a warning message before the user changes the permalinks mode to "Native slugs"
	 *
	 * @return string
	 */
	public function display_instructions()
	{
		return wpautop(__('<strong>قبل از استفاده از حالت  "<em>Native slugs</em>" توصیه می شود، از دیتابیس بکاپ گیری کنید. </strong>', 'link-sawing'));
	}

	/**
	 * Display a list of all duplicated URIs and redirects
	 *
	 * @return string
	 */
	public function duplicates_output()
	{
		// Get the duplicates & another variables
		$all_duplicates = link_sawing_Helper_Functions::get_all_duplicates();
		$home_url       = trim(get_option('home'), "/");

		$button_url = add_query_arg(array(
			'section'                      => 'tools',
			'subsection'                   => 'duplicates',
			'clear-link-sawing-uris' => 1,
			'link-sawing-nonce'      => wp_create_nonce('link-sawing')
		), link_sawing_Admin_Functions::get_admin_url());

		$html = sprintf("<h3>%s</h3>", __("لیست تمام پیوند های تکراری", "link-sawing"));
		$html .= wpautop(sprintf("<a class=\"button button-primary\" href=\"%s\">%s</a>", $button_url, __('تعمیر لینک ها و ریدایرکت های سفارشی', 'link-sawing')));

		if (!empty($all_duplicates)) {
			foreach ($all_duplicates as $uri => $duplicates) {
				$html .= "<div class=\"link-sawing postbox link-sawing-duplicate-box\">";
				$html .= "<h4 class=\"heading\"><a href=\"{$home_url}/{$uri}\" target=\"_blank\">{$home_url}/{$uri} <span class=\"dashicons dashicons-external\"></span></a></h4>";
				$html .= "<table>";

				foreach ($duplicates as $item_id) {
					$html .= "<tr>";

					// Detect duplicate type
					preg_match("/(redirect-([\d]+)_)?(?:(tax-)?([\d]*))/", $item_id, $parts);

					$is_extra_redirect = (!empty($parts[1])) ? true : false;
					$duplicate_type    = ($is_extra_redirect) ? __('ریدایرکت اضافی', 'link-sawing') : __('آدرس سفارشی', 'link-sawing');
					$detected_id       = $parts[4];
					// $detected_index = $parts[2];
					$detected_term = (!empty($parts[3])) ? true : false;
					$remove_link   = ($is_extra_redirect) ? sprintf(" <a href=\"%s\"><span class=\"dashicons dashicons-trash\"></span> %s</a>", admin_url("tools.php?page=link-sawing&section=tools&subsection=duplicates&remove-redirect={$item_id}"), __("حذف ریدایرکت")) : "";

					// Get term
					if ($detected_term && !empty($detected_id)) {
						$term = get_term($detected_id);
						if (!empty($term->name)) {
							$title      = $term->name;
							$edit_label = "<span class=\"dashicons dashicons-edit\"></span>" . __("ویرایش طبقه بندی ها", "link-sawing");
							$edit_link  = get_edit_tag_link($term->term_id, $term->taxonomy);
						} else {
							$title      = __("(حذف طبقه بندی ها)", "link-sawing");
							$edit_label = "<span class=\"dashicons dashicons-trash\"></span>" . __("حذف آدرس های خراب", "link-sawing");
							$edit_link  = admin_url("tools.php?page=link-sawing&section=tools&subsection=duplicates&remove-uri=tax-{$detected_id}");
						}
					} // Get post
					else if (!empty($detected_id)) {
						$post = get_post($detected_id);
						if (!empty($post->post_title) && post_type_exists($post->post_type)) {
							$title      = $post->post_title;
							$edit_label = "<span class=\"dashicons dashicons-edit\"></span>" . __("ویرایش نوشته", "link-sawing");
							$edit_link  = get_edit_post_link($post->ID);
						} else {
							$title      = __("(حذف نوشته)", "link-sawing");
							$edit_label = "<span class=\"dashicons dashicons-trash\"></span>" . __("حذف آدرس های خراب", "link-sawing");
							$edit_link  = admin_url("tools.php?page=link-sawing&section=tools&subsection=duplicates&remove-uri={$detected_id}");
						}
					} else {
						continue;
					}

					$html .= sprintf('<td><a href="%1$s">%2$s</a>%3$s</td><td>%4$s</td><td class="actions"><a href="%1$s">%5$s</a>%6$s</td>', $edit_link, $title, " <small>#{$detected_id}</small>", $duplicate_type, $edit_label, $remove_link);
					$html .= "</tr>";
				}
				$html .= "</table>";
				$html .= "</div>";
			}
		} else {
			$html .= sprintf("<p class=\"alert notice-success notice\">%s</p>", __('تبریک! هیج آدرس و یا ریدایرکت تکراری یافت نشد.', 'link-sawing'));
		}

		return $html;
	}

	/**
	 * Generate a form for "Tools -> Find & replace" tool
	 *
	 * @return string
	 */
	public function find_and_replace_output()
	{
		// Get all registered post types array & statuses
		$all_post_statuses_array = link_sawing_Helper_Functions::get_post_statuses();
		$all_post_types          = link_sawing_Helper_Functions::get_post_types_array();
		$all_taxonomies          = link_sawing_Helper_Functions::get_taxonomies_array();

		$fields = apply_filters('link_sawing_tools_fields', array(
			'old_string'    => array(
				'label'       => __('پیدا کردن ...', 'link-sawing'),
				'type'        => 'text',
				'container'   => 'row',
				'input_class' => 'widefat'
			),
			'new_string'    => array(
				'label'       => __('تعویض با ...', 'link-sawing'),
				'type'        => 'text',
				'container'   => 'row',
				'input_class' => 'widefat'
			),
			'mode'          => array(
				'label'     => __('حالت', 'link-sawing'),
				'type'      => 'select',
				'container' => 'row',
				'choices'   => array(
					'custom_uris' => __('آدرس های سفارشی', 'link-sawing'),
					'slugs'       => __('نامک/Slug طبیعی', 'link-sawing')
				),
			),
			'content_type'  => array(
				'label'     => __('انتخاب نواع محتوا', 'link-sawing'),
				'type'      => 'select',
				'disabled'  => true,
				'pro'       => true,
				'container' => 'row',
				'default'   => 'post_types',
				'choices'   => array(
					'post_types' => __('نوع نوشته ها', 'link-sawing'),
					'taxonomies' => __('طبقه بندی ها', 'link-sawing')
				),
			),
			'post_types'    => array(
				'label'        => __('انتخاب نواع محتوا', 'link-sawing'),
				'type'         => 'checkbox',
				'container'    => 'row',
				'default'      => array('post', 'page'),
				'choices'      => $all_post_types,
				'select_all'   => '',
				'unselect_all' => '',
			),
			'taxonomies'    => array(
				'label'           => __('انتخاب طبقه بندی ها', 'link-sawing'),
				'type'            => 'checkbox',
				'container'       => 'row',
				'container_class' => 'hidden',
				'default'         => array('category', 'post_tag'),
				'choices'         => $all_taxonomies,
				'pro'             => true,
				'select_all'      => '',
				'unselect_all'    => '',
			),
			'post_statuses' => array(
				'label'        => __('انتخاب وضعیت نوشته', 'link-sawing'),
				'type'         => 'checkbox',
				'container'    => 'row',
				'default'      => array('publish'),
				'choices'      => $all_post_statuses_array,
				'select_all'   => '',
				'unselect_all' => '',
			),
			'ids'           => array(
				'label'       => __('انتخاب با شناسه یا ID', 'link-sawing'),
				'type'        => 'text',
				'container'   => 'row',
				//'disabled' => true,
				'description' => __('برای محدود کردن فیلترهای بالا، می‌توانید شناسه‌های پست (یا رنج ها) را در اینجا تایپ کنید. برای نمونه <strong>1-8, 10, 25</strong>.', 'link-sawing'),
				//'pro' => true,
				'input_class' => 'widefat'
			)
		), 'find_and_replace');

		$sidebar = '<h3>' . __('اطلاعیه های مهم', 'link-sawing') . '</h3>';
		$sidebar .= self::display_instructions();

		return link_sawing_Admin_Functions::get_the_form($fields, 'columns-3', array('text' => __('پیدا کنید و جایگزین کنید', 'link-sawing'), 'class' => 'primary margin-top'), $sidebar, array('action' => 'link-sawing', 'name' => 'find_and_replace'), true, 'form-ajax');
	}

	/**
	 * Generate a form for "Tools -> Regenerate/reset" tool
	 *
	 * @return string
	 */
	public function regenerate_slugs_output()
	{
		// Get all registered post types array & statuses
		$all_post_statuses_array = link_sawing_Helper_Functions::get_post_statuses();
		$all_post_types          = link_sawing_Helper_Functions::get_post_types_array();
		$all_taxonomies          = link_sawing_Helper_Functions::get_taxonomies_array();

		$fields = apply_filters('link_sawing_tools_fields', array(
			'mode'          => array(
				'label'     => __('حالت', 'link-sawing'),
				'type'      => 'select',
				'container' => 'row',
				'choices'   => array(
					'custom_uris' => __('بازسازی پیوند سفارشی', 'link-sawing'),
					'slugs'       => __('بازسازی Slug طبیعی', 'link-sawing'),
					'native'      => __('از آدرس های اصلی به عنوان پیوند سفارش استفاده کن', 'link-sawing')
				),
			),
			'content_type'  => array(
				'label'     => __('انتخاب نوع محتوا', 'link-sawing'),
				'type'      => 'select',
				'disabled'  => true,
				'pro'       => true,
				'container' => 'row',
				'default'   => 'post_types',
				'choices'   => array(
					'post_types' => __('انواع نوشته', 'link-sawing'),
					'taxonomies' => __('طبقه بندی ها', 'link-sawing')
				),
			),
			'post_types'    => array(
				'label'        => __('انتخاب انواع نوشته', 'link-sawing'),
				'type'         => 'checkbox',
				'container'    => 'row',
				'default'      => array('post', 'page'),
				'choices'      => $all_post_types,
				'select_all'   => '',
				'unselect_all' => '',
			),
			'taxonomies'    => array(
				'label'           => __('انتخاب طبقه بندی ها', 'link-sawing'),
				'type'            => 'checkbox',
				'container'       => 'row',
				'container_class' => 'hidden',
				'default'         => array('category', 'post_tag'),
				'choices'         => $all_taxonomies,
				'pro'             => true,
				'select_all'      => '',
				'unselect_all'    => '',
			),
			'post_statuses' => array(
				'label'        => __('انتخاب وضعیت نوشته', 'link-sawing'),
				'type'         => 'checkbox',
				'container'    => 'row',
				'default'      => array('publish'),
				'choices'      => $all_post_statuses_array,
				'select_all'   => '',
				'unselect_all' => '',
			),
			'ids'           => array(
				'label'       => __('انتخاب شناسه ها', 'link-sawing'),
				'type'        => 'text',
				'container'   => 'row',
				//'disabled' => true,
				'description' => __('برای محدود کردن فیلترهای بالا، می‌توانید شناسه‌های پست (یا رنج ها) را در اینجا تایپ کنید. برای نمونه <strong>1-8, 10, 25</strong>.', 'link-sawing'),
				//'pro' => true,
				'input_class' => 'widefat'
			)
		), 'regenerate');

		$sidebar = '<h3>' . __('اطلاعیه های مهم', 'link-sawing') . '</h3>';
		$sidebar .= self::display_instructions();

		return link_sawing_Admin_Functions::get_the_form($fields, 'columns-3', array('text' => __('بازسازی', 'link-sawing'), 'class' => 'primary margin-top'), $sidebar, array('action' => 'link-sawing', 'name' => 'regenerate'), true, 'form-ajax');
	}
}
