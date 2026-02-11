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
										'dashicons-imagekit-forum',
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
								'content'    => __( 'Ask in support forum', 'imagekit' ),
							),
						),
						array(
							'type'       => 'tag',
							'element'    => 'p',
							'attributes' => array(
								'class' => array( 'help-box-description' ),
							),
							'content'    => __( 'Have a question or hit a snag? Post in our support forum to get help from the community.', 'imagekit' ),
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
										'dashicons-imagekit-system-report',
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
					// 'enabled'     => static function () {
					// return ! get_plugin_instance()->get_component( 'credentials_manager' )->is_connected();
					// },
					'collapsible' => 'closed',
					'content'     => sprintf(
						__( 'To use the ImageKit plugin and all the functionality that comes with it, you will need to have a ImageKit Account. %1$sIf you don\'t have an account yet, %2$ssign up%3$s now for a free ImageKit account%4$s. You\'ll start with generous usage limits and when your requirements grow, you can easily upgrade to a plan that best fits your needs.', 'imagekit' ),
						'<b>',
						'<a href="https://imagekit.io/registration/" target="_blank" rel="noopener noreferrer">',
						'</a>',
						'</b>'
					),
				),
				array(
					'type'        => 'panel',
					'title'       => __( 'I\'ve installed the ImageKit plugin, what happens now?', 'imagekit' ),
					'collapsible' => 'closed',
					'content'     => __( 'If you left all the settings as default, all your current media will begin syncing with ImageKit. Once syncing is complete, your media will be optimized and delivered using ImageKit URLs and you should begin seeing improvements in performance across your site.', 'imagekit' ),
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
