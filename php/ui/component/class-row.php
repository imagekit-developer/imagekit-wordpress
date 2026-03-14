<?php
/**
 * Row UI Component.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress\UI\Component;

use ImageKitWordpress\UI\Component;

/**
 * Row Component to render components only.
 *
 * @package ImageKitWordpress\UI
 */
class Row extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|settings/|/wrap';

	/**
	 * Gets the wrap structs.
	 *
	 * @param array $struct The wrap struct.
	 *
	 * @return array
	 */
	protected function wrap( $struct ) {

		$struct['attributes']['class'][] = 'ik-row';
		if ( $this->setting->has_param( 'align' ) ) {
			$struct['attributes']['class'][] = 'align-' . $this->setting->get_param( 'align' );
		}

		return $struct;
	}
}
