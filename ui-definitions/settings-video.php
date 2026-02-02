<?php

namespace ImageKitWordpress;

$settings = array(
	array(
		'type'        => 'panel',
		'title'       => __( 'Video Settings', 'imagekit' ),
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
					'slug'               => 'video_delivery',
					'title'              => __( 'Video delivery', 'imagekit' ),
					'optimisation_title' => __( 'Video delivery', 'imagekit' ),
					'tooltip_text'       => __(
						'If you turn this setting off, your videos will be delivered from WordPress.',
						'imagekit'
					),
					'description'        => __( 'Deliver videos from ImageKit.', 'imagekit' ),
					'default'            => 'on',
					'attributes'         => array(
						'data-context' => 'video',
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
						'slug'           => 'video_freeform',
						'title'          => 'Global video transformations',
						'default'        => '',
						'anchor'         => true,
						'tooltip_text'   => sprintf(
							__(
								'A set of additional transformations to apply to all videos. Specify your transformations using ImageKit URL transformation syntax. See %1$sreference%2$s for all available transformations and syntax.',
								'imagekit'
							),
							'<a href="https://imagekit.io/docs/video-transformation" target="_blank" rel="noopener noreferrer">',
							'</a>',
						),
						'link'           => array(
							'text' => __( 'See examples', 'imagekit' ),
							'href' => 'https://imagekit.io/docs/video-transformation',
						),
						'attributes'     => array(
							'data-context' => 'video',
							'placeholder'  => 'h-400,w-400',
						),
						'taxonomy_field' => array(
							'context'  => 'video',
							'priority' => 10,
						),
					),
				),
			),
		),
	),
);

return apply_filters( 'imagekit_admin_video_settings', $settings );
