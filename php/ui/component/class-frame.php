<?php
/**
 * Frame UI Component.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress\UI\Component;

use ImageKitWordpress\UI\Component;

/**
 * Frame Component to render components only.
 *
 * @package ImageKitWordpress\UI
 */
class Frame extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'settings';
}
