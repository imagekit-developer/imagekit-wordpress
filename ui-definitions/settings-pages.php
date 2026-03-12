<?php

/**
 * Defines the settings structure for the sidebar.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

$settings = array(
	'credentials'    => array(
		'page_title'         => __( 'General settings', 'imagekit' ),
		'menu_title'         => __( 'General settings', 'imagekit' ),
		'disconnected_title' => __( 'Setup', 'imagekit' ),
		'priority'           => 5,
		'sidebar'            => true,
		'settings'           => array(
			array(
				'title'       => __( 'Status Overview', 'imagekit' ),
				'type'        => 'panel',
				'collapsible' => 'open',
				array(
					'type' => 'credentials',
					array(
						'slug' => \ImageKitWordpress\Credentials_Manager::META_KEYS['url'],
					),
					array(
						'slug' => \ImageKitWordpress\Credentials_Manager::META_KEYS['public_key'],
					),
					array(
						'slug' => \ImageKitWordpress\Credentials_Manager::META_KEYS['private_key'],
					),
				),
			),
		),
	),
	'image_settings' => array(
		'page_title'          => __( 'Image settings', 'imagekit' ),
		'menu_title'          => __( 'Image settings', 'imagekit' ),
		'priority'            => 5,
		'requires_connection' => true,
		'sidebar'             => true,
		'settings'            => include $this->dir_path . 'ui-definitions/settings-images.php', // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
	),
	'video_settings' => array(
		'page_title'          => __( 'Video settings', 'imagekit' ),
		'menu_title'          => __( 'Video settings', 'imagekit' ),
		'priority'            => 5,
		'requires_connection' => true,
		'sidebar'             => true,
		'settings'            => include $this->dir_path . 'ui-definitions/settings-video.php', // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
	),
	'asset_settings' => array(
		'page_title'          => __( 'Asset settings', 'imagekit' ),
		'menu_title'          => __( 'Asset settings', 'imagekit' ),
		'priority'            => 5,
		'requires_connection' => true,
		'sidebar'             => true,
		'settings'            => include $this->dir_path . 'ui-definitions/settings-assets.php', // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
	),
	'responsive'     => array(
		'page_title'          => __( 'Responsive images', 'imagekit' ),
		'menu_title'          => __( 'Responsive images', 'imagekit' ),
		'priority'            => 5,
		'requires_connection' => true,
		'sidebar'             => true,
		'settings'            => include $this->dir_path . 'ui-definitions/settings-responsive.php', // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
	),
	'help'           => array(
		'page_title' => __( 'Get help', 'imagekit' ),
		'menu_title' => __( 'Get help', 'imagekit' ),
		'priority'   => 50,
		'sidebar'    => true,
		array(
			'type'  => 'panel',
			'title' => __( 'Help Hub', 'imagekit' ),
			array(
				'type'    => 'tag',
				'element' => 'h4',
				'content' => __( 'Need help with the ImageKit plugin?', 'imagekit' ),
			),
			array(
				'type'    => 'span',
				'content' => 'Here are some ways to find support and troubleshoot issues, so you can get the right answer and information as quickly as possible. Know that we are here for you!',
			),
			array(
				'type'       => 'row',
				'attributes' => array(
					'wrap' => array(
						'class' => array(
							'help-wrap',
						),
					),
				),
				array(
					'type'       => 'column',
					'attributes' => array(
						'wrap' => array(
							'class' => array(
								'help-box',
							),
						),
					),
					array(
						'type'       => 'tag',
						'element'    => 'a',
						'attributes' => array(
							'href'   => 'https://imagekit.io/docs/integration/wordpress',
							'target' => '_blank',
							'rel'    => 'noopener noreferrer',
							'class'  => array(
								'large-button',
							),
						),
						array(
							'type'       => 'tag',
							'element'    => 'div',
							'attributes' => array(
								'class' => array(
									'help-box-header',
								),
							),
							array(
								'type'       => 'tag',
								'element'    => 'div',
								'attributes' => array(
									'class' => array(
										'help-box-header-icon',
										'dashicons-imagekit-docs',
									),
								),
							),
							array(
								'type'       => 'tag',
								'element'    => 'h4',
								'attributes' => array(
									'class' => array(
										'help-box-header-title',
									),
								),
								'content'    => __( 'Documentation', 'imagekit' ),
							),
						),
						array(
							'type'       => 'tag',
							'element'    => 'p',
							'attributes' => array(
								'class' => array( 'help-box-description' ),
							),
							'content'    => __( 'Learn more about how to use the ImageKit plugin and get the most out of the functionality.', 'imagekit' ),
						),
					),
				),
				array(
					'type'       => 'column',
					'attributes' => array(
						'wrap' => array(
							'class' => array(
								'help-box',
							),
						),
					),
					array(
						'type'       => 'tag',
						'element'    => 'a',
						'attributes' => array(
							'href'   => static function () {
								return Utils::get_support_link();
							},
							'target' => '_blank',
							'rel'    => 'noopener noreferrer',
							'class'  => array(
								'large-button',
							),
						),
						array(
							'type'       => 'tag',
							'element'    => 'div',
							'attributes' => array(
								'class' => array(
									'help-box-header',
								),
							),
							array(
								'type'       => 'tag',
								'element'    => 'div',
								'attributes' => array(
									'class' => array(
										'help-box-header-icon',
										'dashicons-imagekit-community',
									),
								),
							),
							array(
								'type'       => 'tag',
								'element'    => 'h4',
								'attributes' => array(
									'class' => array(
										'help-box-header-title',
									),
								),
								'content'    => __( 'Ask in community', 'imagekit' ),
							),
						),
						array(
							'type'       => 'tag',
							'element'    => 'p',
							'attributes' => array(
								'class' => array( 'help-box-description' ),
							),
							'content'    => __( 'Have a question or hit a snag? Post in our community forum to get help.', 'imagekit' ),
						),
					),
				),
				array(
					'type'       => 'column',
					'attributes' => array(
						'wrap' => array(
							'class' => array(
								'help-box',
							),
						),
					),
					array(
						'type'       => 'tag',
						'element'    => 'a',
						'attributes' => array(
							'href'   => static function () {
								return Utils::get_support_link();
							},
							'target' => '_blank',
							'rel'    => 'noopener noreferrer',
							'class'  => array(
								'large-button',
							),
						),
						array(
							'type'       => 'tag',
							'element'    => 'div',
							'attributes' => array(
								'class' => array(
									'help-box-header',
								),
							),
							array(
								'type'       => 'tag',
								'element'    => 'div',
								'attributes' => array(
									'class' => array(
										'help-box-header-icon',
										'dashicons-imagekit-support',
									),
								),
							),
							array(
								'type'       => 'tag',
								'element'    => 'h4',
								'attributes' => array(
									'class' => array(
										'help-box-header-title',
									),
								),
								'content'    => __( 'Contact support', 'imagekit' ),
							),
						),
						array(
							'type'       => 'tag',
							'element'    => 'p',
							'attributes' => array(
								'class' => array( 'help-box-description' ),
							),
							'content'    => __( 'Running into an issue or having trouble getting the plugin working? Open a support ticket and our team will help you out.', 'imagekit' ),
						),
					),
				),
				array(
					'type'       => 'column',
					'attributes' => array(
						'wrap' => array(
							'class' => array(
								'help-box',
							),
						),
					),
					array(
						'type'                => 'tag',
						'element'             => 'a',
						'requires_connection' => true,
						'attributes'          => array(
							'href'  => add_query_arg( 'section', 'system-report' ),
							'class' => array(
								'large-button',
							),
						),
						array(
							'type'       => 'tag',
							'element'    => 'div',
							'attributes' => array(
								'class' => array(
									'help-box-header',
								),
							),
							array(
								'type'       => 'tag',
								'element'    => 'div',
								'attributes' => array(
									'class' => array(
										'help-box-header-icon',
										'dashicons-imagekit-report',
									),
								),
							),
							array(
								'type'       => 'tag',
								'element'    => 'h4',
								'attributes' => array(
									'class' => array(
										'help-box-header-title',
									),
								),
								'content'    => __( 'System Report', 'imagekit' ),
							),
						),
						array(
							'type'       => 'tag',
							'element'    => 'p',
							'attributes' => array(
								'class' => array( 'help-box-description' ),
							),
							'content'    => __( "Generate a system report to help debug any specific issues you're having with your ImageKit media.", 'imagekit' ),
						),
					),
				),
			),
		),
		array(
			'type'  => 'panel',
			'title' => __( 'Frequently asked questions', 'imagekit' ),
			array(
				array(
					'type'        => 'panel',
					'title'       => __( 'Do I need a ImageKit account to use the ImageKit plugin and can I try it out for free?', 'imagekit' ),
					'enabled'     => static function () {
						return ! get_plugin_instance()->get_component( 'credentials_manager' )->is_connected();
					},
					'collapsible' => 'closed',
					'content'     => sprintf(
						/* translators: %1$s: opening bold tag, %2$s: opening link tag, %3$s: closing link tag, %4$s: closing bold tag */
						__( 'To use the ImageKit plugin and all the functionality that comes with it, you will need to have a ImageKit Account. %1$sIf you don\'t have an account yet, %2$ssign up%3$s now for a free ImageKit account%4$s. You\'ll start with generous usage limits and when your requirements grow, you can easily upgrade to a plan that best fits your needs.', 'imagekit' ),
						'<b>',
						'<a href="https://imagekit.io/registration/" target="_blank" rel="noopener noreferrer">',
						'</a>',
						'</b>'
					),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'I\'ve installed and set up the ImageKit plugin, what happens now?', 'imagekit' ),
					'collapsible' => 'closed',
					'content'     => __( 'If you left all the settings as default, the plugin will automatically rewrite your media URLs to deliver them through ImageKit. Your images and assets will be optimized and served via ImageKit, and you should begin seeing improvements in performance across your site.', 'imagekit' ),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'How does this plugin work?', 'imagekit' ),
					'collapsible' => 'closed',
					'content'     => __( 'This plugin rewrites your site\'s media and asset URLs to deliver them through your ImageKit endpoint. This includes images, videos, and optionally static assets like CSS and JavaScript files, so they are automatically optimized and served via ImageKit.', 'imagekit' ),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'I installed the plugin but Google PageSpeed Insights is still showing image related warnings', 'imagekit' ),
					'collapsible' => 'closed',
					'content'     => sprintf(
						__( 'This plugin automatically optimizes images and serves them in next-gen formats including WebP. However, it does not automatically resize images to match your layout.', 'imagekit' ),
						'<a href="https://make.wordpress.org/core/2015/11/10/responsive-images-in-wordpress-4-4/" target="_blank" rel="noopener noreferrer">',
						'</a>',
					),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'Do I have to manually change the old posts to optimize their images?', 'imagekit' ),
					'collapsible' => 'closed',
					'content'     => __( 'No, this plugin automatically takes care of that.', 'imagekit' ),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'Does this plugin support custom CNAME?', 'imagekit' ),
					'collapsible' => 'closed',
					'content'     => __( 'Yes, you can email developer@imagekit.io to configure a custom CNAME for your account and then specify that in the plugin settings page.', 'imagekit' ),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'Can I configure this plugin to use ImageKit for custom upload directories?', 'imagekit' ),
					'collapsible' => 'closed',
					'content'     => __( 'Yes, you can specify any number of custom directory locations on the plugin settings page.', 'imagekit' ),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'Does ImageKit support all image formats?', 'imagekit' ),
					'collapsible' => 'closed',
					'content'     => __( 'ImageKit supports all popular image formats that cover 99.99% of use cases. On the settings page, you can further configure whether to allow or disallow a particular file type to be loaded via ImageKit.', 'imagekit' ),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'I installed the plugin, but the ImageKit website analyzer is suggesting more optimization.', 'imagekit' ),
					'collapsible' => 'closed',
					'content'     => sprintf(
						__( 'This is because image dimensions are not matching the layout. We could resize using JavaScript in the frontend like other plugins, but we do not recommend it. The browser triggers the image load as soon as it sees an image URL in HTML, and intentionally delaying this while JavaScript calculates the ideal width will ultimately slow down the image load for your users.', 'imagekit' ),
						'<a href="https://make.wordpress.org/core/2015/11/10/responsive-images-in-wordpress-4-4/" target="_blank" rel="noopener noreferrer">',
						'</a>'
					),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'Are my media uploads automatically sent to ImageKit?', 'imagekit' ),
					'collapsible' => 'closed',
					'content'     => __( 'Yes. When you upload media through the WordPress Media Library, the plugin automatically offloads it to your ImageKit account. You can configure the storage mode in General Settings to keep copies in both WordPress and ImageKit, store a low-resolution version locally, keep files only in ImageKit, or only in WordPress.', 'imagekit' ),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'Can I browse and insert assets from my ImageKit account directly in WordPress?', 'imagekit' ),
					'collapsible' => 'closed',
					'content'     => __( 'Yes. The plugin adds an ImageKit tab to the WordPress media modal, allowing you to browse, search, and select assets from your ImageKit media library. Selected assets are imported into WordPress so you can insert them into your posts and pages without re-uploading.', 'imagekit' ),
				),
			),
		),
	),
	'system-report'  => array(
		'section' => 'system-report',
		'slug'    => 'system-report',
	),
	'wizard'         => array(
		'section' => 'wizard',
		'slug'    => 'wizard',
	),
	'debug'          => array(
		'section' => 'debug',
		'slug'    => 'debug',
		array(
			'type'  => 'panel',
			'title' => __( 'Debug log', 'imagekit' ),
			array(
				'type' => 'debug',
			),
		),
	),
);

return apply_filters( 'imagekit_settings_pages', $settings );
