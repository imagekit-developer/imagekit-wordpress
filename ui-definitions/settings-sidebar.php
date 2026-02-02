<?php
/**
 * Defines the settings structure for the sidebar.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

/**
 * Defines the settings structure for the main header.
 *
 * @package ImageKitWordpress
 */

$settings = array(
	array(
		'type'        => 'panel',
		'title'       => __( 'Current usage summary', 'imagekit' ),
		'description' => '',
		'collapsible' => 'open',
		array(
			'type'  => 'usage_stats',
			'title' => __( 'Storage', 'imagekit' ),
			'icon'  => 'storage',
			array(
				'type'        => 'usage_stat',
				'title'       => __( 'Media Library Storage', 'imagekit' ),
				'icon'        => 'media-library-storage',
				'stat'        => 'mediaLibraryStorage',
				'format_size' => true,
				'value_type'  => 'bytes',
			),
			array(
				'type'        => 'usage_stat',
				'title'       => __( 'Original Cache Storage', 'imagekit' ),
				'icon'        => 'original-cache-storage',
				'stat'        => 'originalCacheStorage',
				'format_size' => true,
				'value_type'  => 'bytes',
			),
		),
		array(
			'type'  => 'usage_stats',
			'title' => __( 'Delivery & Bandwidth', 'imagekit' ),
			'icon'  => 'delivery-and-bandwidth',
			array(
				'type'        => 'usage_stat',
				'title'       => __( 'Bandwidth', 'imagekit' ),
				'icon'        => 'bandwidth',
				'stat'        => 'bandwidth',
				'format_size' => true,
				'value_type'  => 'bytes',
			),
			array(
				'type'       => 'usage_stat',
				'title'      => __( 'Video Processing Units', 'imagekit' ),
				'icon'       => 'video-processing-units',
				'stat'       => 'videoProcessingUnits',
				'value_type' => 'count',
			),
			array(
				'type'       => 'usage_stat',
				'title'      => __( 'Extensions Units', 'imagekit' ),
				'icon'       => 'extension-units',
				'stat'       => 'extensionUnits',
				'value_type' => 'count',
			),
		),
		array(
			'type'       => 'tag',
			'element'    => 'a',
			'content'    => __( 'Goto ImageKit Dashboard', 'imagekit' ),
			'attributes' => array(
				'href'   => 'https://imagekit.io/dashboard',
				'target' => '_blank',
				'rel'    => 'noopener noreferrer',
				'class'  => array(
					'ik-link-button',
				),
			),
		),
	),
);

return apply_filters( 'imagekit_admin_sidebar', $settings );
