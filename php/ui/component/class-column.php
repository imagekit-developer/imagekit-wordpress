<?php
/**
 * Column UI Component.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress\UI\Component;

/**
 * Column Component to render components only.
 *
 * @package ImageKitWordpress\UI
 */
class Column extends Row {

	/**
	 * Filter the Wrap parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function wrap( $struct ) {

		$struct['attributes']['class'][] = 'ik-column';
		if ( $this->setting->has_param( 'width' ) ) {
			if ( ! isset( $struct['attributes']['style'] ) ) {
				$struct['attributes']['style'] = '';
			}
			$struct['attributes']['style'] .= 'width:' . $this->setting->get_param( 'width' ) . ';';
		}

		if ( $this->setting->has_param( 'class' ) ) {
			$struct['attributes']['class'] = array_merge( $struct['attributes']['class'], $this->setting->get_param( 'class' ) );
		}

		if ( $this->setting->has_param( 'tab_id' ) ) {
			$struct['attributes']['id']      = $this->setting->get_param( 'tab_id' );
			$struct['attributes']['class'][] = 'tabbed-content';
		}

		return $struct;
	}
}
