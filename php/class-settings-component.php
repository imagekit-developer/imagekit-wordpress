<?php
/**
 * ImageKitWordpress Settings Component Abstract.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

use ImageKitWordpress\Component;
use ImageKitWordpress\Settings as CoreSetting;

/**
 * Plugin Settings Component class.
 */
abstract class Settings_Component implements Component\Settings {

	/**
	 * Holds the settings object for this Class.
	 *
	 * @var CoreSetting
	 */
	protected $settings;

	/**
	 * Holds the settings slug.
	 *
	 * @var string
	 */
	protected $settings_slug;

	/**
	 * Holds the core plugin.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Component constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Init the settings object.
	 *
	 * @param CoreSetting $settings The setting object to init onto.
	 */
	public function init_settings( $settings ) {

		if ( ! $this->settings_slug ) {
			$class               = strtolower( get_class( $this ) );
			$this->settings_slug = substr( strrchr( $class, '\\' ), 1 );
		}

		add_action( "{$settings->get_slug()}_settings_upgrade", array( $this, 'upgrade_settings' ), 10, 2 );

		$this->settings = $settings;
	}

	/**
	 * Get the setting object.
	 *
	 * @return CoreSetting
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Upgrade method for version changes.
	 *
	 * @param string $previous_version The previous version number.
	 * @param string $new_version      The New version number.
	 */
	public function upgrade_settings( $previous_version, $new_version ) {
	}
}
