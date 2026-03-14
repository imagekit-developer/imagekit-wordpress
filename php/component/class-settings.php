<?php
/**
 * Interface for settings based classes.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress\Component;

use ImageKitWordpress\Settings\Setting;

/**
 * Defines an object that requires settings.
 */
interface Settings {

	/**
	 * Init Settings Object.
	 *
	 * @param Setting $setting The core setting.
	 */
	public function init_settings( $setting );
}
