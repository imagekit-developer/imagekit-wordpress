<?php
/**
 * Base HTML UI Component.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress\UI\Component;

use ImageKitWordpress\UI\Component;

/**
 * HTML Component to render components only.
 *
 * @package ImageKitWordpress\UI
 */
class HTML extends Component {

	/**
	 * Filter the title parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function title( $struct ) {

		$struct['element'] = 'h4';

		return $struct;
	}
}
