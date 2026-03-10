<?php
/**
 * Defines the settings structure for asset delivery.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

$settings = array(
	array(
		'type'        => 'panel',
		'title'       => __( 'Asset Settings', 'imagekit' ),
		'anchor'      => true,
		'option_name' => 'asset_delivery',
		array(
			'type'    => 'tag',
			'element' => 'p',
			'content' => __( 'Control which WordPress assets are delivered via ImageKit. This covers theme, plugin, and WordPress core CSS and JavaScript files enqueued through the standard WordPress asset system.', 'imagekit' ),
		),
		array(
			'type'    => 'tag',
			'element' => 'hr',
		),
		array(
			'type' => 'row',
			array(
				'type'  => 'column',
				'class' => array(
					'column-min-w-50',
				),
				array(
					'type'    => 'tag',
					'element' => 'h4',
					'content' => __( 'Theme Assets', 'imagekit' ),
				),
				array(
					'type'               => 'toggle',
					'slug'               => 'theme_css',
					'title'              => __( 'Theme CSS', 'imagekit' ),
					'optimisation_title' => __( 'Theme CSS delivery', 'imagekit' ),
					'tooltip_text'       => __(
						'Deliver theme stylesheets (.css) from ImageKit.',
						'imagekit'
					),
					'description'        => __( 'Deliver theme CSS files from ImageKit.', 'imagekit' ),
					'default'            => 'on',
					'attributes'         => array(
						'data-context' => 'asset',
					),
				),
				array(
					'type'               => 'toggle',
					'slug'               => 'theme_js',
					'title'              => __( 'Theme JS', 'imagekit' ),
					'optimisation_title' => __( 'Theme JS delivery', 'imagekit' ),
					'tooltip_text'       => __(
						'Deliver theme JavaScript files (.js) from ImageKit.',
						'imagekit'
					),
					'description'        => __( 'Deliver theme JS files from ImageKit.', 'imagekit' ),
					'default'            => 'on',
					'attributes'         => array(
						'data-context' => 'asset',
					),
				),
			),
		),
		array(
			'type'    => 'tag',
			'element' => 'hr',
		),
		array(
			'type' => 'row',
			array(
				'type'  => 'column',
				'class' => array(
					'column-min-w-50',
				),
				array(
					'type'    => 'tag',
					'element' => 'h4',
					'content' => __( 'Plugin Assets', 'imagekit' ),
				),
				array(
					'type'               => 'toggle',
					'slug'               => 'plugin_css',
					'title'              => __( 'Plugin CSS', 'imagekit' ),
					'optimisation_title' => __( 'Plugin CSS delivery', 'imagekit' ),
					'tooltip_text'       => __(
						'Deliver plugin stylesheets (.css) from ImageKit.',
						'imagekit'
					),
					'description'        => __( 'Deliver plugin CSS files from ImageKit.', 'imagekit' ),
					'default'            => 'on',
					'attributes'         => array(
						'data-context' => 'asset',
					),
				),
				array(
					'type'               => 'toggle',
					'slug'               => 'plugin_js',
					'title'              => __( 'Plugin JS', 'imagekit' ),
					'optimisation_title' => __( 'Plugin JS delivery', 'imagekit' ),
					'tooltip_text'       => __(
						'Deliver plugin JavaScript files (.js) from ImageKit.',
						'imagekit'
					),
					'description'        => __( 'Deliver plugin JS files from ImageKit.', 'imagekit' ),
					'default'            => 'on',
					'attributes'         => array(
						'data-context' => 'asset',
					),
				),
			),
		),
		array(
			'type'    => 'tag',
			'element' => 'hr',
		),
		array(
			'type' => 'row',
			array(
				'type'  => 'column',
				'class' => array(
					'column-min-w-50',
				),
				array(
					'type'    => 'tag',
					'element' => 'h4',
					'content' => __( 'WordPress Core Assets', 'imagekit' ),
				),
				array(
					'type'               => 'toggle',
					'slug'               => 'wp_core_css',
					'title'              => __( 'Core CSS', 'imagekit' ),
					'optimisation_title' => __( 'WP core CSS delivery', 'imagekit' ),
					'tooltip_text'       => __(
						'Deliver WordPress core stylesheets (.css) from ImageKit.',
						'imagekit'
					),
					'description'        => __( 'Deliver WordPress core CSS files from ImageKit.', 'imagekit' ),
					'default'            => 'on',
					'attributes'         => array(
						'data-context' => 'asset',
					),
				),
				array(
					'type'               => 'toggle',
					'slug'               => 'wp_core_js',
					'title'              => __( 'Core JS', 'imagekit' ),
					'optimisation_title' => __( 'WP core JS delivery', 'imagekit' ),
					'tooltip_text'       => __(
						'Deliver WordPress core JavaScript files (.js) from ImageKit.',
						'imagekit'
					),
					'description'        => __( 'Deliver WordPress core JS files from ImageKit.', 'imagekit' ),
					'default'            => 'on',
					'attributes'         => array(
						'data-context' => 'asset',
					),
				),
			),
		),
	),
);

return apply_filters( 'imagekit_admin_asset_settings', $settings );
