<?php
/**
 * Defines the settings structure for images.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

$settings = array(
	array(
		'type'        => 'panel',
		'title'       => __( 'Image Settings', 'imagekit' ),
		'anchor'      => true,
		'option_name' => 'media_display',
		array(
			'type' => 'row',
			array(
				'type'  => 'column',
				'class' => array(
					'column-min-w-50',
				),
				array(
					'type'               => 'toggle',
					'slug'               => 'image_delivery',
					'title'              => __( 'Image delivery', 'imagekit' ),
					'optimisation_title' => __( 'Image delivery', 'imagekit' ),
					'tooltip_text'       => __(
						'If you turn this setting off, your images will be delivered from WordPress.',
						'imagekit'
					),
					'description'        => __( 'Deliver images from ImageKit.', 'imagekit' ),
					'default'            => 'on',
					'attributes'         => array(
						'data-context' => 'image',
					),
				),
				array(
					'type'    => 'tag',
					'element' => 'hr',
				),
				array(
					'type' => 'group',
					array(
						'type'           => 'input',
						'slug'           => 'image_global_transformation',
						'title'          => 'Global image transformations',
						'default'        => '',
						'anchor'         => true,
						'tooltip_text'   => sprintf(
							/* translators: %1$s: opening link tag, %2$s: closing link tag */
							__(
								'A set of additional transformations to apply to all images. Specify your transformations using ImageKit URL transformation syntax. See %1$sreference%2$s for all available transformations and syntax.',
								'imagekit'
							),
							'<a href="https://imagekit.io/docs/transformations" target="_blank" rel="noopener noreferrer">',
							'</a>',
						),
						'link'           => array(
							'text' => __( 'See examples', 'imagekit' ),
							'href' => 'https://imagekit.io/docs/image-transformation',
						),
						'attributes'     => array(
							'data-context' => 'image',
							'placeholder'  => 'h-400,w-400',
						),
						'taxonomy_field' => array(
							'context'  => 'image',
							'priority' => 10,
						),
					),
				),
			),
		),
	),
);

return apply_filters( 'imagekit_admin_image_settings', $settings );
