<?php
/**
 * ImageKit Uploader
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

use ImageKitWordpress\Component\Assets;
use ImageKitWordpress\Component\Setup;
use ImageKitWordpress\Settings\Setting;

/**
 * Class Uploader
 */
class Uploader implements Setup, Assets {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   5.0.0
	 *
	 * @var     Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Holds the uploader settings object.
	 *
	 * @var Setting
	 */
	public $settings;

	/**
	 * Push_Sync constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;

		add_filter( 'imagekit_settings_pages', array( $this, 'settings' ) );
	}



	function setup() {}

	function enqueue_assets() {}

	/**
	 * Is the component Active.
	 */
	public function is_active() {
		return $this->settings && $this->settings->has_param( 'is_active' );
	}

	function register_assets() {}

	/**
	 * Define the settings on the general settings page.
	 *
	 * @param array $pages The pages to add to.
	 *
	 * @return array
	 */
	public function settings( $pages ) {
		$pages['credentials']['settings'][] = array(
			array(
				'type'                => 'frame',
				'requires_connection' => true,
				array(
					'type'        => 'panel',
					'title'       => __( 'Media Library Upload Settings', 'imagekit' ),
					'option_name' => 'sync_media',
					'collapsible' => 'open',
					array(
						'type'              => 'input',
						'slug'              => 'imagekit_folder',
						'title'             => __( 'ImageKit folder path', 'imagekit' ),
						'default'           => '',
						'attributes'        => array(
							'input' => array(
								'placeholder' => __( 'e.g.: wordpress_assets/', 'imagekit' ),
							),
						),
						'tooltip_text'      => __(
							'The folder in your ImageKit account that WordPress assets are uploaded to. Leave blank to use the root of your ImageKit library.',
							'imagekit'
						),
						'sanitize_callback' => array( '\ImageKitWordpress\Media', 'sanitize_imagekit_folder' ),
					),
					array(
						'type'         => 'select',
						'slug'         => 'offload',
						'title'        => __( 'Storage', 'imagekit' ),
						'tooltip_text' => sprintf(
							// translators: the HTML for opening and closing list and its items.
							__(
								'Choose where your assets are stored.%1$s<b>ImageKit and WordPress</b>: Stores assets in both locations. Enables local WordPress delivery if the ImageKit plugin is disabled or uninstalled.%2$s<b>ImageKit and WordPress (low resolution)</b>:  Stores original assets in ImageKit and low resolution versions in WordPress. Enables low resolution local WordPress delivery if the plugin is disabled or uninstalled.%3$s<b>ImageKit only</b>: Stores assets in ImageKit only. Requires additional steps to enable backwards compatibility.%4$s%5$sLearn more%6$s',
								'imagekit'
							),
							'<ul><li class="both_full">',
							'</li><li class="both_low">',
							'</li><li class="ik">',
							'</li></ul>',
							'<a href="https://ImageKit.com/documentation/wordpress_integration#storage" target="_blank" rel="noopener noreferrer">',
							'</a>'
						),
						'default'      => 'both_full',
						'options'      => array(
							'dual_full' => __( 'ImageKit and WordPress', 'imagekit' ),
							'dual_low'  => __( 'ImageKit and WordPress (low resolution)', 'imagekit' ),
							'ik'        => __( 'ImageKit only', 'imagekit' ),
						),
					),
				),
			),
		);
		return $pages;
	}
}
