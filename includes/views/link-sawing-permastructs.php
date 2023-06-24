<?php

/**
 * Display the page where the slugs could be regenerated or replaced
 */
class link_sawing_Permastructs
{

	public function __construct()
	{
		add_filter('link_sawing_sections', array($this, 'add_admin_section'), 2);
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
		$admin_sections['permastructs'] = array(
			'name'     => __('ساختارهای دائمی', 'link-sawing'),
			'function' => array('class' => 'link_sawing_Permastructs', 'method' => 'output')
		);

		return $admin_sections;
	}

	/**
	 * Return an array of fields that will be used to adjust the permastructure settings
	 *
	 * @return array
	 */
	public function get_fields()
	{
		$post_types = link_sawing_Helper_Functions::get_post_types_array('full');
		$taxonomies = link_sawing_Helper_Functions::get_taxonomies_array('full');

		// Display additional information in Link Sawing Lite
		if (!link_sawing_Admin_Functions::is_pro_active() && !class_exists('link_sawing_URI_Functions_Tax')) {
			$pro_text = sprintf(__('برای تغییر پیوند طبقه بندی ها نیاز به نسخه حرفه ای است.', 'link-sawing'), link_sawing_WEBSITE);
			$pro_text = sprintf('<div class="alert info">%s</div>', $pro_text);
		}

		// 1. Get fields
		$fields = array(
			'post_types' => array(
				'section_name' => __('انواع نوشته', 'link-sawing'),
				'container'    => 'row',
				'fields'       => array()
			),
			'taxonomies' => array(
				'section_name'   => __('طبقه بندی ها', 'link-sawing'),
				'container'      => 'row',
				'append_content' => (!empty($pro_text)) ? $pro_text : '',
				'fields'         => array()
			)
		);

		// 2. Add a separate section for WooCommerce content types
		if (class_exists('WooCommerce')) {
			$fields['woocommerce'] = array(
				'section_name'   => "<i class=\"woocommerce-icon woocommerce-cart\"></i> " . __('ووکامرس', 'link-sawing'),
				'container'      => 'row',
				'append_content' => (!empty($pro_text)) ? $pro_text : '',
				'fields'         => array()
			);
		}

		// 3A. Add permastructure fields for post types
		foreach ($post_types as $post_type) {
			if ($post_type['name'] == 'shop_coupon') {
				continue;
			}

			$fields["post_types"]["fields"][$post_type['name']] = array(
				'label'       => $post_type['label'],
				'container'   => 'row',
				'input_class' => 'permastruct-field',
				'post_type'   => $post_type,
				'type'        => 'permastruct'
			);
		}

		// 3B. Add permastructure fields for taxonomies
		foreach ($taxonomies as $taxonomy) {
			$taxonomy_name = $taxonomy['name'];

			// Check if taxonomy exists
			if (!taxonomy_exists($taxonomy_name)) {
				continue;
			}

			$fields["taxonomies"]["fields"][$taxonomy_name] = array(
				'label'       => $taxonomy['label'],
				'container'   => 'row',
				'input_class' => 'permastruct-field',
				'taxonomy'    => $taxonomy,
				'type'        => 'permastruct'
			);
		}

		// 4. Separate WooCommerce CPT & custom taxonomies
		if (class_exists('WooCommerce')) {
			$woocommerce_fields     = array('product' => 'post_types', 'product_tag' => 'taxonomies', 'product_cat' => 'taxonomies');
			$woocommerce_attributes = wc_get_attribute_taxonomies();

			foreach ($woocommerce_attributes as $woocommerce_attribute) {
				$woocommerce_fields["pa_{$woocommerce_attribute->attribute_name}"] = 'taxonomies';
			}

			foreach ($woocommerce_fields as $field => $field_type) {
				if (empty($fields[$field_type]["fields"][$field])) {
					continue;
				}

				$fields["woocommerce"]["fields"][$field]         = $fields[$field_type]["fields"][$field];
				$fields["woocommerce"]["fields"][$field]["name"] = "{$field_type}[{$field}]";
				unset($fields[$field_type]["fields"][$field]);
			}
		}

		return apply_filters('link_sawing_permastructs_fields', $fields);
	}

	/**
	 * Get the array with settings and render the HTML output
	 */
	public function output()
	{
		$sidebar = sprintf('<h3>%s</h3>', __('لطفا، دستور عمل را دنبال کنید', 'link-sawing'));
		$sidebar .= "<div class=\"ui compact  message\"><p>";
		$sidebar .= __('تنظیمات فعلی ساختار لینک ها، <strong>فقط بر روی نوشته ها و طبقه بندی های جدید</strong> اعمال می شود.');
		$sidebar .= '<br />';
		$sidebar .= sprintf(__('بعد از به روز رسانی تغییرات در این صفحه جهت اعمال ساختار جدید لینک ها بر روی<strong> پست ها و طبقه بندی های از قبل موجود</strong> , لطفا به ابزار "<a href="%s">بازسازی/بازنشانی</a>" مراجعه کنید.', 'link-sawing'), admin_url('tools.php?page=link-sawing&section=tools&subsection=regenerate_slugs'));
		$sidebar .= "</p></div>";
		$sidebar .= "<div class=\"ui compact message\" style=\"margin-bottom:20px;\">";
		$sidebar .= sprintf('<h4>%s</h4>', __('برچسب های ساختار ', 'link-sawing'));
		$sidebar .= wpautop(sprintf(__('تمامی <a href="%s" target="_blank">برچسب های ساختار</a> مجاز در پایین لیست شده.</br> لطفا در نظر داشته باشید ، برخی از آنها فقط برای نوع خاصی از نوشته ها یا طبقه بندی ها استفاده می شوند.', 'link-sawing'), "https://codex.wordpress.org/Using_Permalinks#Structure_Tags"));
		$sidebar .= link_sawing_Helper_Functions::get_all_structure_tags();
		$sidebar .= "</div>";
		return link_sawing_Admin_Functions::get_the_form(self::get_fields(), '', array('text' => __('ذخیره ساختار پیوندها', 'link-sawing'), 'class' => 'primary margin-top'), $sidebar, array('action' => 'link-sawing', 'name' => 'link_sawing_permastructs'));
	}
}
