<?php
/**
 * Interface for config based classes.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress\Component;

/**
 * Sets up methods used if this class has configs.
 */
interface Config {

	/**
	 * Retrive config from class.
	 */
	public function get_config();
}
