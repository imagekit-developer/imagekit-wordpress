<?php
/**
 * Interface for setup based classes.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress\Component;

/**
 * Defines an object that requires setup.
 */
interface Setup {

	/**
	 * Setup the object.
	 */
	public function setup();
}
