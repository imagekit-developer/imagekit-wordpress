<?php
/**
 * Tag UI Component.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress\UI\Component;

use ImageKitWordpress\UI\Component;

/**
 * Tag Component to render components only.
 *
 * @package ImageKitWordpress\UI
 */
class Tag extends Component {
	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'tag_wrap|settings/|/tag_wrap';

	/**
	 * Filter the title parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function tag_wrap( $struct ) {

		$struct['element']    = $this->setting->get_param( 'element', null );
		$struct['attributes'] = $this->setting->get_param( 'attributes', array() );
		$struct['render']     = true;
		if ( $this->setting->has_param( 'content' ) ) {
			$struct['content'] = $this->setting->get_param( 'content' );
		}

		return $struct;
	}
}
