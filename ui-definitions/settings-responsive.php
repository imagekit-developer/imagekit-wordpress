<?php

namespace ImageKitWordpress;

$settings = array(
	array(
		'type'        => 'panel',
		'title'       => __( 'Responsive images', 'imagekit' ),
		'anchor'      => true,
		'option_name' => 'media_display',
		array(
			'type' => 'row',
			array(
				'type' => 'column',
				array(
					'type'               => 'toggle',
					'slug'               => 'enable_breakpoints',
					'title'              => __( 'Breakpoints', 'imagekit' ),
					'optimisation_title' => __( 'Responsive images', 'imagekit' ),
					'tooltip_text'       => __(
						'Automatically generate multiple sizes based on the configured breakpoints to enable your images to responsively adjust to different screen sizes.',
						'imagekit'
					),
					'description'        => __( 'Enable responsive images', 'imagekit' ),
					'default'            => 'off',
				),
				array(
					'type' => 'group',
					// 'condition' => array(
					// 'enable_breakpoints' => 'on',
					// ),
					array(
						'type'         => 'input',
						'slug'         => 'pixel_step',
						'priority'     => 9,
						'title'        => __( 'Breakpoint distance', 'imagekit' ),
						'tooltip_text' => __( 'The distance between each generated image. Adjusting this will adjust the number of images generated.', 'imagekit' ),
						'suffix'       => __( 'px', 'imagekit' ),
						'attributes'   => array(
							'type' => 'number',
							'step' => 50,
							'min'  => 50,
						),
						'default'      => 200,
					),
					array(
						'type'         => 'input',
						'slug'         => 'breakpoints',
						'title'        => __( 'Max images', 'imagekit' ),
						'tooltip_text' => __(
							'The maximum number of images to be generated. Note that generating large numbers of images will deliver a more optimal version for a wider range of screen sizes but will result in an increase in your usage. For smaller images, the responsive algorithm may determine that the ideal number is less than the value you specify.',
							'imagekit'
						),
						'suffix'       => __( 'Recommended value: 3-40', 'imagekit' ),
						'attributes'   => array(
							'type' => 'number',
							'min'  => 3,
							'max'  => 100,
						),
					),
					array(
						'type'  => 'row',
						'align' => 'end',
						array(
							'type'        => 'input',
							'slug'        => 'max_width',
							'title'       => __( 'Image width limit', 'imagekit' ),
							'extra_title' => __(
								'The minimum and maximum width of an image created as a breakpoint. Leave "max" as empty to automatically detect based on the largest registered size in WordPress.',
								'imagekit'
							),
							'prefix'      => __( 'Max', 'imagekit' ),
							'suffix'      => __( 'px', 'imagekit' ),
							'attributes'  => array(
								'type' => 'number',
								'step' => 50,
								'min'  => 0,
							),
						),
						array(
							'type'       => 'input',
							'slug'       => 'min_width',
							'prefix'     => __( 'Min', 'imagekit' ),
							'suffix'     => __( 'px', 'imagekit' ),
							'default'    => 200,
							'attributes' => array(
								'type' => 'number',
								'step' => 50,
								'min'  => 0,
							),
						),
					),
				),
			),
		),
	),
);

return apply_filters( 'imagekit_admin_responsive_settings', $settings );
