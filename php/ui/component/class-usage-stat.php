<?php

namespace ImageKitWordpress\UI\Component;

use ImageKitWordpress\UI\Component;
use function ImageKitWordpress\get_plugin_instance;

class Usage_Stat extends Component {

	/**
	 * Holds the connect instance.
	 *
	 * @var \ImageKitWordpress\Credentials_Manager
	 */
	protected $connect;

	/**
	 * Holds the used_raw total.
	 *
	 * @var string
	 */
	protected $used_raw;

	/**
	 * Holds the used total.
	 *
	 * @var string
	 */
	protected $used;

	/**
	 * Holds the used_text total.
	 *
	 * @var string
	 */
	protected $used_text;

	protected $blueprint = 'wrap|row|icon/|meta|label/|description/|/meta|value/|/row|/wrap';

	/**
	 * Setup the component.
	 */
	public function setup() {
		parent::setup();
		$this->set_stats();
		$used = $this->used_raw;
		if ( $this->setting->get_param( 'format_size' ) ) {
			$used       = empty( $used ) ? 0 : $used;
			$this->used = size_format( $used, 1 );
		} else {
			$this->used = number_format_i18n( $used );
		}

		$this->set_texts();
	}



	/**
	 * Set the usage stats.
	 */
	protected function set_stats() {
		$this->connect  = get_plugin_instance()->get_component( 'credentials_manager' );
		$this->used_raw = $this->connect->get_usage_stat( $this->setting->get_param( 'stat' ), $this->setting->get_param( 'value_type' ) );
	}

	/**
	 * Set the end texts.
	 */
	protected function set_texts() {
		$this->used_text = sprintf( '%s used', $this->used );
	}

	protected function wrap( $struct ) {
		$struct['element']               = 'div';
		$struct['attributes']['class'][] = 'ik-usage-stat';

		return $struct;
	}

	protected function row( $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array( 'ik-usage-stat-row' );

		return $struct;
	}

	protected function icon( $struct ) {
		$icon_class                      = $this->setting->get_param( 'icon', 'dashicons-database' );
		$struct                          = parent::dashicon( $struct, 'dashicons-imagekit-' . $icon_class );
		$struct['render']                = true;
		$struct['attributes']['class'][] = 'ik-usage-stat-icon';

		return $struct;
	}

	protected function meta( $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array( 'ik-usage-stat-meta' );

		return $struct;
	}

	protected function label( $struct ) {
		$struct['element']             = 'span';
		$struct['attributes']['class'] = array( 'ik-usage-stat-label' );
		$struct['content']             = $this->setting->get_param( 'title', '' );

		return $struct;
	}

	protected function description( $struct ) {
		$struct['element'] = null;
		$description_text  = $this->setting->get_param( 'description', '' );
		if ( ! empty( $description_text ) ) {
			$struct['element']             = 'div';
			$struct['attributes']['class'] = array( 'ik-usage-stat-description' );
			$struct['content']             = $description_text;
		}

		return $struct;
	}

	protected function value( $struct ) {
		$struct['element']             = 'span';
		$struct['attributes']['class'] = array( 'ik-usage-stat-value' );
		$struct['content']             = $this->used_text;

		return $struct;
	}
}
