<?php

namespace ImageKitWordpress\UI\Component;

use ImageKitWordpress\UI\Component;

class Usage_Stats extends Component {
	protected $blueprint = 'wrap|header|icon/|title/|/header|list|settings/|/list|/wrap';

	protected function wrap( $struct ) {
		$struct['element']               = 'div';
		$struct['attributes']['class'][] = 'ik-usage-stats';

		return $struct;
	}

	protected function header( $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array( 'ik-usage-stats-header' );

		return $struct;
	}

	protected function icon( $struct ) {
		$icon_class                      = $this->setting->get_param( 'icon', 'dashicons-database' );
		$struct                          = parent::dashicon( $struct, 'dashicons-imagekit-' . $icon_class );
		$struct['render']                = true;
		$struct['attributes']['class'][] = 'ik-usage-stats-icon';

		return $struct;
	}

	protected function title( $struct ) {
		$struct['element']             = 'h4';
		$struct['attributes']['class'] = array( 'ik-usage-stats-title' );
		$struct['content']             = $this->setting->get_param( 'title', '' );

		return $struct;
	}

	protected function list( $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array( 'ik-usage-stats-list' );

		return $struct;
	}
}
