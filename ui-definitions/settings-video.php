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
						'type'         => 'input',
						'slug'         => 'video_quality',
						'title'        => __( 'Video quality', 'imagekit' ),
						'tooltip_text' => __(
							'Set the quality of delivered videos (1-100). Lower values reduce file size but also reduce visual quality. Leave empty to use ImageKit defaults.',
							'imagekit'
						),
						'default'      => '',
						'suffix'       => __( '1-100', 'imagekit' ),
						'attributes'   => array(
							'type'         => 'number',
							'min'          => 1,
							'max'          => 100,
							'data-context' => 'video',
							'placeholder'  => 'Auto',
						),
					),
					array(
						'type'         => 'select',
						'slug'         => 'video_format',
						'title'        => __( 'Video format', 'imagekit' ),
						'tooltip_text' => __(
							'Convert videos to a specific format for delivery. "Auto" lets ImageKit choose the best format based on the viewer\'s browser. MP4 (H.264) has the widest compatibility. WebM (VP9) offers better compression for supported browsers.',
							'imagekit'
						),
						'default'      => '',
						'attributes'   => array(
							'data-context' => 'video',
						),
						'options'      => array(
							''     => __( 'Auto (recommended)', 'imagekit' ),
							'mp4'  => __( 'MP4 (H.264)', 'imagekit' ),
							'webm' => __( 'WebM (VP9)', 'imagekit' ),
						),
					),
					array(
						'type'               => 'toggle',
						'slug'               => 'video_thumbnail',
						'title'              => __( 'Auto-generate poster', 'imagekit' ),
						'tooltip_text'       => __(
							'Automatically generate a poster thumbnail for videos using ImageKit. This provides a preview image before the video plays, improving perceived performance.',
							'imagekit'
						),
						'description'        => __( 'Generate poster thumbnails from video frames.', 'imagekit' ),
						'default'            => 'off',
						'attributes'         => array(
							'data-context' => 'video',
						),
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
							/* translators: %1$s: opening link tag, %2$s: closing link tag */
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
