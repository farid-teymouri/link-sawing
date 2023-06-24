<?php

/**
 * Display the settings page
 */
class link_sawing_Settings
{

	public function __construct()
	{
		add_filter('link_sawing_sections', array($this, 'add_admin_section'), 3);
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
		$admin_sections['settings'] = array(
			'name'     => __('تنظیمات', 'link-sawing'),
			'function' => array('class' => 'link_sawing_Settings', 'method' => 'output')
		);

		return $admin_sections;
	}

	/**
	 * Get the array with settings and render the HTML output
	 *
	 * @return string
	 */
	public function output()
	{
		// Get all registered post types & taxonomies
		$all_post_types = link_sawing_Helper_Functions::get_post_types_array(null, null, true);

		$all_taxonomies = link_sawing_Helper_Functions::get_taxonomies_array(false, false, true);
		$content_types  = (defined('link_sawing_PRO')) ? array('post_types' => $all_post_types, 'taxonomies' => $all_taxonomies) : array('post_types' => $all_post_types);

		$sections_and_fields = apply_filters('link_sawing_settings_fields', array(
			'general'       => array(
				'section_name' => __('تنظیمات عمومی', 'link-sawing'),
				'container'    => 'row',
				'name'         => 'general',
				'fields' => array(
					'auto_update_uris'   => array(
						'type'        => 'select',
						'label'       => __('حالت به روز رسانی آدرس ها', 'link-sawing'),
						'input_class' => '',
						'choices'     => array(0 => __('جلوگیری از به روز رسانی خودکار آدرس ها (پیشفرض)', 'link-sawing'), 1 => __('به روز رسانی خودکار آدرس ها', 'link-sawing'), 2 => __('جلوگیری از ذخیره و ساخت خودکار لینک سفارسی برای پست جدید یا دسته جدید... ', 'link-sawing')),
						'description' => sprintf('<strong>%s</strong><br />%s<br />%s', __(' پس از به روز رسانی یک نوشته، دسته و یا ...، این پلاگین می تواند به صورت خودکار لینک سفارشی را برای پیروی از فرمت پیشفرض آن را به تنظیمات اضافه کند.', 'link-sawing'), __('اگر می خواهید هربار که یک پست ، دسته،برچسب،برگه، جدیدی که ساخته میشود طبق لینک سفارشی شما عمل نکند، گزینه آخر را انتخاب کنید.', 'link-sawing'), __('ممکن است شما تنظیمات کلی را در بخش `تنظیمات آدرسها` برای هر پست یا دسته و یا... به صورت فردی تغییر دهید.', 'link-sawing'))
					),
					'force_custom_slugs' => array(
						'type'        => 'select',
						'label'       => __('حالت Slug/نامک', 'link-sawing'),
						'input_class' => 'settings-select',
						'choices'     => array(0 => __('از (Slug/نامک) طبیعی استفاده کن', 'link-sawing'), 1 => __('از عنوان برای  ایجاد (Slug/نامک) استفاده کن', 'link-sawing'), 2 => __('با توجه به والد آن (Slug/نامک) را به ارث ببر', 'link-sawing')),
						'description' => sprintf('%s<br />%s<br />%s', __('<strong>به لطف توسعه دهنده عزیز این پلاگین می تواند، از Slug های طبیعی یا عناوین به عنوان لینک سفارشی، استفاده کند.</strong>', 'link-sawing'), __('در وردپرس یک Slug طبیعی در زمان ساخت نوشته،برگه،دسته و یا ... با توجه به عنوانی که برای اولین بار تعریف شده، ساخته می شود.', 'link-sawing'), __('از گزینه دوم برای ایجاد Slug با توجه به عنوان فعلی استفاده کنید.', 'link-sawing'))
					),
					'trailing_slashes'   => array(
						'type'        => 'select',
						'label'       => __('اسلش های انتهایی', 'link-sawing'),
						'input_class' => 'settings-select',
						'choices'     => array(0 => __('از تنظیمات پیش فرض استفاده کن', 'link-sawing'), 1 => __('اضاف کردن اسلش های انتهایی', 'link-sawing'), 2 => __('حذف اسلش های انتهایی', 'link-sawing')),
						'description' => sprintf('<strong>%s</strong><br />%s<br />%s', __('از این گزینه برای کنترل کردن، تغییرات حذف و یا اضافه کردن اسلش در انتهای آدرس های مرتبط با پست ها ، برگه ها، دسته ها، برچسب ها... استفاده کنید.', 'link-sawing'), __('می توانید از این ویژگی برای اضافه کردن یا حذف اسلش ها از انتهای پیوندهای وردپرس استفاده کنید', 'link-sawing'), __('جهت مجبور کردن اسلش های انتهای آدرس ها در زمان ریدایرکت ، لطفا این مسیر را دنبال کنید <em> تنظیمات -> ریدایرکت ها -> ریدایرکت برای اسلش های انتهایی </em>', 'link-sawing'))
					),
					'partial_disable'    => array(
						'type'        => 'checkbox',
						'label'       => __('ایجاد استثنا برای انواع محتوا', 'link-sawing'),
						'choices'     => $content_types,
						'description' => __('در صورت انخاب هر گزینه ، این پلاگین تنظیمات فوق را برای آن در نظر نمی گیرد.', 'link-sawing')
					),
					'ignore_drafts'      => array(
						'type'        => 'select',
						'label'       => __('ایجاد استثنا برای پست های پیش نویس و یا حالت معلق', 'link-sawing'),
						'choices'     => array(0 => __('بدون استثنا', 'link-sawing'), 1 => __('به استثنا پست های پیش نویس', 'link-sawing'), 2 => __('به استثنا پست های پیش نویس و معلق', 'link-sawing')),
						'description' => __('در صورت فعال بودن این گزینه تنظیمات لینک سفاری برای پست های پیش نویس و یا معلق صورت نمی گیرد.', 'link-sawing')
					)
				)
			),
			'redirect'      => array(
				'section_name' => __('تنظیمات ریدایرکت ها', 'link-sawing'),
				'container'    => 'row',
				'name'         => 'general',
				'fields'       => array(
					'canonical_redirect'                         => array(
						'type'        => 'single_checkbox',
						'label'       => __('ریدایرکت مرسوم', 'link-sawing'),
						'input_class' => '',
						'description' => sprintf('%s<br />%s', __('<strong>ریدایرکت مرسوم، به وردپرس این اجازه را می دهد تا کاربر شما به شکل صحیح به لینک هدف هدایت شود.</strong>', 'link-sawing'), __('یا به عبارتی به لطف توسعه دهنده ، این گزینه قابلیت انتقال کاربر شما از لینک قدیم (original permalink) به لینک جدید (custom permalinks) را به شکل صحیح پیاده سازی می کند.', 'link-sawing'))
					),
					/*'endpoint_redirect' => array(
						'type' => 'single_checkbox',
						'label' => __('Redirect with endpoints', 'link-sawing'),
						'input_class' => '',
						'description' => sprintf('%s',
							__('<strong>Please enable this option if you would like to copy the endpoint from source URL to the target URL during the canonical redirect.</strong>', 'link-sawing')
						)
					),*/ 'old_slug_redirect' => array(
						'type'        => 'single_checkbox',
						'label'       => __('ریدایرکت Slug قدیمی', 'link-sawing'),
						'input_class' => '',
						'description' => sprintf('%s<br />%s', __('<strong>ریدایرکت از Slug قبلی به جدید ، در صورت تغییر Slug قبلی.</strong>', 'link-sawing'), __('اگر این گزینه فعال شود، در صورتی که Slug تغییر کند، به صورت خودکار کاربر از آدرس پیوند قبلی به آدرس پیوند با Slug جدید منتقل می شود.', 'link-sawing'))
					),
					'extra_redirects'                            => array(
						'type'        => 'single_checkbox',
						'label'       => __('ریدایرکت های اضافی', 'link-sawing'),
						'input_class' => '',
						'pro'         => true,
						'disabled'    => true,
						'description' => sprintf('%s<br /><strong>%s</strong>', __('لطفا این گزینه را فعال نگهدارید، در صورتی که نیاز به مدیریت ریدایرکتهای سفارشی اضافی را دارید.', 'link-sawing'), __('همچنین در صورت استفاده از پلاگین های ریدایرکت دیگری نظیر Yoast SEO Premium و یا Redirection می توانید این گزینه را ، غیرفعال کنید.', 'link-sawing'))
					),
					'setup_redirects'                            => array(
						'type'        => 'single_checkbox',
						'label'       => __('ذخیره پیوند های سفارشی قدیمی، به عنوان ریدایرکت اضافی', 'link-sawing'),
						'input_class' => '',
						'pro'         => true,
						'disabled'    => true,
						'description' => sprintf('%s<br /><strong>%s</strong>', __('در صورت فعال سازی این گزینه ریدایرکت اضافی برای نسخه قبلی پیوند بعد از تغییر آن ذخیره می شود ( برای مثل در گزینه های <em> ویرایش آدرس </em> و یا <em> باز سازی Slug و ریست کردن </em> )', 'link-sawing'), __('لطفا در نظر داشته باشید، تنها در زمانی ریدایرکت جدید ذخیره می شود،که گزینه  <em>" ریدایرکت های اضافی "</em> در بالا فعال شده باشد.', 'link-sawing'))
					),
					'trailing_slashes_redirect'                  => array(
						'type'        => 'single_checkbox',
						'label'       => __('ریدایرکت برای اسلش های انتهایی', 'link-sawing'),
						'input_class' => '',
						'description' => sprintf('%s<br /><strong>%s</strong>', __('به لطف توسعه دهنده عزیر، این افزونه می تواند تنظیمات اسلش های انتهایی در پیوندهای سفارشی را همراه با ریدایرکت اعمال کند', 'link-sawing'), __('جهت فعال سازی این گزینه، لطفا به "<em>تنظیمات عمومی -> اسلش های انتهایی </em>" رفته و انتخاب کنید وردپرس اسلش های انتهایی را حذف کند و یا اضافه کند.', 'link-sawing'))
					),
					'copy_query_redirect'                        => array(
						'type'        => 'single_checkbox',
						'label'       => __('ریدایرکت با کوئری پارامتر ها', 'link-sawing'),
						'input_class' => '',
						'description' => sprintf('%s<br />%s', __('درصورت فعال سازی این گزینه، در زمان ایجاد ریدایرکت ها ، کوئری پارامتر های موجود نیز کپی می شوند.', 'link-sawing'), __('برای مثال: <em>https://example.com/product/old-product-url/<strong>?discount-code=blackfriday</strong></em> => <em>https://example.com/new-product-url/<strong>?discount-code=blackfriday</strong></em>', 'link-sawing'))
					),
					'sslwww_redirect'                            => array(
						'type'        => 'single_checkbox',
						'label'       => __('HTTPS/WWW', 'link-sawing'),
						'input_class' => '',
						'description' => sprintf('%s<br />%s', __('<strong>برای نادیده گرفتن، SSL یا "www" در پیوندهای وردپرس این گزینه را فعال کنید.</strong>', 'link-sawing'), __('اگر با مشکل حلقه در ریدایرکت ها مواجه شدید، لطفاً آن را غیرفعال کنید.', 'link-sawing'))
					),
					'redirect'                                   => array(
						'type'        => 'select',
						'label'       => __('حالت ریدایرکت', 'link-sawing'),
						'input_class' => 'settings-select',
						'choices'     => array(0 => __('غیرفعال ( تمامی توابع ریدایرکت )', 'link-sawing'), "301" => __('301 redirect', 'link-sawing'), "302" => __('302 redirect', 'link-sawing')),
						'description' => sprintf('%s<br /><strong>%s</strong>', __('این افزونه شامل مجموعه‌ای از قلاب‌ها است که به شما اجازه می‌دهد تا توابع ریدایرکت مورد استفاده بومی وردپرس را گسترش دهید تا از خطاهای 404 جلوگیری شود.', 'link-sawing'), __('اگر نمی خواهید این افزونه به هیچ وجه عملکرد ریدایرکت دیگری را فعال کند، می توانید این ویژگی را غیرفعال کنید.', 'link-sawing'))
					)
				)
			),
			'third_parties' => array(
				'section_name' => __('افزونه های دیگر', 'link-sawing'),
				'container'    => 'row',
				'name'         => 'general',
				'fields'       => array(
					'fix_language_mismatch' => array(
						'type'         => 'select',
						'label'        => __('WPML/Polylang رفع عدم تطابق زبان', 'link-sawing'),
						'input_class'  => '',
						'choices'      => array(0 => __('غیرفعال', 'link-sawing'), 1 => __('نوع زبان صفحه درخواستی را بارگیری کنید', 'link-sawing'), 2 => __('ریدایرکت به نوع زبان صفحه درخواستی', 'link-sawing')),
						'class_exists' => array('SitePress', 'Polylang'),
						'description'  => __('افزونه ممکن است ترجمه مربوطه را بارگیری کند یا هنگامی که یک پیوند سفارشی شناسایی شد، تغییر مسیر متعارف را آغاز کند, اما کد زبان آدرس با کد زبان موارد شناسایی شده مطابقت ندارد. ', 'link-sawing')
					),
					'wpml_support'          => array(
						'type'         => 'single_checkbox',
						'label'        => __('توابع سازگار با WPML', 'link-sawing'),
						'input_class'  => '',
						'class_exists' => array('SitePress'),
						'description'  => __('اگر کد زبان موجود در پیوندهای دائمی سفارشی نادرست است، لطفاً این ویژگی را غیرفعال کنید.', 'link-sawing')
					),
					'pmxi_support'          => array(
						'type'         => 'single_checkbox',
						'label'        => __('پشتیبانی از تمامی import/export وردپرس', 'link-sawing'),
						'input_class'  => '',
						'class_exists' => array('PMXI_Plugin', 'PMXE_Plugin'),
						'description'  => __('اگر غیر فعال باشد, پیوندهای سفارشی برای تمامی موارد import شده وردپرس  <strong>ذخیره نخواهد شد</strong>.', 'link-sawing')
					),
					'um_support'            => array(
						'type'         => 'single_checkbox',
						'label'        => __('پشتیبانی از Ultimate Member', 'link-sawing'),
						'input_class'  => '',
						'class_exists' => 'UM',
						'description'  => __('اگر فعال باشد ، این افزونه قادر به اضافه کردن تمامی گزینه های اضافی Ultimate Member می باشد', 'link-sawing')
					),
					'yoast_breadcrumbs'     => array(
						'type'        => 'single_checkbox',
						'label'       => __('پشتیبانی از Breadcrumbs', 'link-sawing'),
						'input_class' => '',
						'description' => __(' در صورت فعال سازی این گزینه،ساختار HTML breadcrumbs از ساختار فعلی تقلید می کند.<br /> همگام با افزونه های : <strong>WooCommerce, Yoast SEO, Slim Seo, RankMath و SEOPress</strong> breadcrumbs.', 'link-sawing')
					),
					'primary_category'      => array(
						'type'        => 'single_checkbox',
						'label'       => __('پشتیبانی از "Primary category"', 'link-sawing'),
						'input_class' => '',
						'description' => __('در صورت فعال سازی این گزینه، از "primary category" در ساختار پیش فرض پیوندهای پست استفاده می شود.<br />همگام با افزونه های : <strong>Yoast SEO, The SEO Framework, RankMath and SEOPress</strong>.', 'link-sawing')
					),
				)
			),
			'advanced'      => array(
				'section_name' => __('تنظیمات پیشرفته', 'link-sawing'),
				'container'    => 'row',
				'name'         => 'general',
				'fields'       => array(
					'show_native_slug_field'    => array(
						'type'  => 'single_checkbox',
						'label' => __('نمایش فیلد Slug طبیعی در ویرایش آدرس ها', 'link-sawing')
					),
					'partial_disable_strict'    => array(
						'type'        => 'single_checkbox',
						'label'       => __('ایجاد استثنا برای انواع محتوا (سختگیرانه تر)', 'link-sawing'),

						'description' => __('در صورت فعال شدن این گزینه، تمامی انواع نوشته و طبقه بندی های سفارشی که  شامل ویژگی های "<strong>query_var</strong>" و "<strong>rewrite</strong>" ، تنظیم شده در حالت "<em>FALSE</em>" هستند در افزونه مستثنا می شوند ، از این رو گزینه ی "<em>تنظیمات عمومی -> ایجاد استثنا برای انواع محتوا</em>" از دسترس شما خارج می شود.', 'link-sawing')
					),
					'pagination_redirect'       => array(
						'type'        => 'single_checkbox',
						'label'       => __('ضرورت 404 در در صفحه بندی', 'link-sawing'),
						'description' => __('در صورت فعال کردن ، در پست تک صفحه ای اگر صفحه بندی موجود نباشد مقدار 404 برگردانده می شود. <br /><strong>لطفا این گزینه را غیر فعال کنید، چراکه احتمال ایجاد مشکل با صفحه بندی ، یا صفحه بندی شخصی سازی شده ممکن است.</strong>', 'link-sawing')
					),
					'disable_slug_sanitization' => array(
						'type'        => 'select',
						'label'       => __('محدودیت کاراکتر های ویژه', 'link-sawing'),
						'input_class' => 'settings-select',
						'choices'     => array(0 => __('بله، استفاده از تنظیمات طبیعی', 'link-sawing'), 1 => __('خیر، کاراکتر های ویژه (.,|_+) را در Slug نگهدار', 'link-sawing')),
						'description' => __('اگر فعال شود، کاراکتر های عددی، خط فاصله،خط تیره در پست و طبقه بندی ها به عنوان Slug قابل استفاده می شود.', 'link-sawing')
					),
					'keep_accents'              => array(
						'type'        => 'select',
						'label'       => __('تبدیل حروف لهجه دار', 'link-sawing'),
						'input_class' => 'settings-select',
						'choices'     => array(0 => __('بله، از تنظیمات طبیعی استفاده کن', 'link-sawing'), 1 => __('خیر، از حروف لهجه دار در Slug می توان استفاده کرد.', 'link-sawing')),
						'description' => __('در صورت فعال سازی، تمامی حروف لهجه دار به معادل بدون لهجه تبدیل می شوند (برای مثال : Å => A, Æ => AE, Ø => O, Ć => C).', 'link-sawing')
					),
					'edit_uris_cap'             => array(
						'type'        => 'select',
						'label'       => __('دسترسی اعضا به ویرایش آدرس‌ها', 'link-sawing'),
						'choices'     => array('edit_theme_options' => __('Administrator (edit_theme_options)', 'link-sawing'), 'publish_pages' => __('Editor (publish_pages)', 'link-sawing'), 'publish_posts' => __('Author (publish_posts)', 'link-sawing'), 'edit_posts' => __('Contributor (edit_posts)', 'link-sawing')),
						'description' => sprintf(__('فقط سطح کاربری که انتخاب شده، امکان دسترسی به ویرایشگر آدرس ها را دارد. <br /> برای اطلاعات بیشتر لیست دسترسی ها در وردپرس را <a href="%s" target="_blank"> در اینجا مطالعه نمایید</a>.', 'link-sawing'), 'https://wordpress.org/support/article/roles-and-capabilities/#capability-vs-role-table')
					),
					'auto_fix_duplicates'       => array(
						'type'        => 'select',
						'label'       => __('تعمیر خودکار خرابی آدرس ها', 'link-sawing'),
						'input_class' => 'settings-select',
						'choices'     => array(0 => __('خیر', 'link-sawing'), 1 => __('تعمیر آدرس ها به شکل جداگانه (در زمان اجرای صفحه)', 'link-sawing'), 2 => __('تعمیر انبوه لینک ها (در پس زمینه)', 'link-sawing')),
						'description' => sprintf('%s', __('برای حذف خودکار لینک های زائد و یا ریدایرکت های تکراری این گزینه را فعال کنید..', 'link-sawing'))
					)
				)
			)
		));

		return link_sawing_Admin_Functions::get_the_form($sections_and_fields, 'tabs', array('text' => __('Save settings', 'link-sawing'), 'class' => 'primary margin-top'), '', array('action' => 'link-sawing', 'name' => 'link_sawing_options'));
	}
}
