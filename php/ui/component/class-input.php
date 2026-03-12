<?php
/**
 * Input UI Component.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress\UI\Component;

use ImageKitWordpress\UI\Component;

/**
 * Class Component
 *
 * @package ImageKitWordpress\UI
 */
class Input extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|icon/|div|label|title|link/|/title|extra_title/|/label|/div|input_row|prefix/|input/|suffix/|/input_row|description/|tooltip/|/wrap';

	/**
	 * Flag if component is a capture type.
	 *
	 * @var bool
	 */
	protected static $capture = true;

	/**
	 * Filter the wrap parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function wrap( $struct ) {

		$struct['attributes']['class'] = array(
			'ik-input',
		);

		// Add type-specific class for child components (toggle, select, etc.) but not for base input
		if ( 'input' !== $this->type ) {
			$struct['attributes']['class'][] = 'ik-input-' . $this->type;
		}

		if ( $this->setting->has_param( 'anchor' ) ) {
			$struct['attributes']['id'] = 'text-' . str_replace( '_', '-', $this->setting->get_slug() );
		}

		return $struct;
	}

	/**
	 * Filter the label parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function label( $struct ) {

		$struct['attributes']['class'][] = 'ik-input-label';
		$struct['attributes']['for']     = $this->setting->get_slug();

		return $struct;
	}

	/**
	 * Filter the link parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function link( $struct ) {
		$link = $this->setting->get_param( 'link', array() );
		if ( ! empty( $link ) ) {
			$struct['element']               = 'a';
			$struct['attributes']['class'][] = 'ik-input-label-link';
			$struct['attributes']['href']    = $link['href'];
			$struct['attributes']['target']  = '_blank';
			$struct['content']               = $link['text'];
		}

		return $struct;
	}

	/**
	 * Filter the extra_title parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function extra_title( $struct ) {
		$struct['content'] = null;
		if ( $this->setting->has_param( 'extra_title' ) ) {
			$struct['render']              = true;
			$struct['attributes']['class'] = array(
				'ik-tooltip',
			);
			$struct['content']             = $this->setting->get_param( 'extra_title' );
		}

		return $struct;
	}

	/**
	 * Filter the input_row parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function input_row( $struct ) {
		$struct['element']             = 'span';
		$struct['attributes']['class'] = array( 'ik-input-row' );

		return $struct;
	}

	/**
	 * Filter the input parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function input( $struct ) {

		$struct['element']               = 'input';
		$struct['attributes']['name']    = $this->get_name();
		$struct['attributes']['id']      = $this->get_id();
		$struct['attributes']['value']   = $this->setting->get_value();
		$struct['attributes']['class'][] = 'regular-' . $this->type;
		$struct['render']                = true;

		if ( true === $this->setting->get_param( 'disabled', false ) ) {
			$struct['attributes']['disabled'] = 'disabled';
		}

		if ( $this->setting->has_param( 'required' ) ) {
			$struct['attributes']['required'] = 'required';
		}

		if ( $this->setting->has_param( 'prefix' ) ) {
			$struct['attributes']['class'][] = 'prefixed';
		}

		if ( $this->setting->has_param( 'suffix' ) ) {
			$struct['attributes']['class'][] = 'suffixed';
			$value                           = $this->setting->get_param( 'suffix' );
			if ( false !== strpos( $value, '@value' ) ) {
				$struct['attributes']['data-suffix'][] = $this->get_id() . '_suffix';
			}
		}

		if ( $this->setting->has_param( 'placeholder' ) ) {
			$struct['attributes']['placeholder'] = $this->setting->get_param( 'placeholder' );
		}

		return $struct;
	}

	/**
	 * Filter the suffix parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function suffix( $struct ) {
		$value = null;

		if ( $this->setting->has_param( 'suffix' ) ) {
			$value = $this->setting->get_param( 'suffix' );
			if ( false !== strpos( $value, '@value' ) ) {
				$struct['attributes']['data-template'] = $value;
				$value                                 = str_replace( '@value', $this->get_value(), $value );
			}
		}
		$struct['attributes']['id'] = $this->get_id() . '_suffix';
		$struct['content']          = $value;

		return $struct;
	}

	/**
	 * Filter the description parts structure.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function description( $struct ) {

		$struct['element']               = 'label';
		$struct['attributes']['class'][] = 'description';
		if ( true === $this->setting->get_param( 'disabled', false ) ) {
			$struct['attributes']['class'][] = 'ik-disabled-message';
		}
		$struct['attributes']['for'] = $this->setting->get_slug();
		$struct['content']           = $this->setting->get_param( 'description' );

		return $struct;
	}

	/**
	 * Get the field name.
	 *
	 * @return string
	 */
	protected function get_name() {
		$parts = explode( $this->setting->separator, $this->setting->get_slug() );
		$name  = array_shift( $parts );
		if ( ! empty( $parts ) ) {
			$name .= '[' . implode( $this->setting->separator, $parts ) . ']';
		}

		return $name;
	}

	/**
	 * Get the field ID.
	 *
	 * @return string
	 */
	protected function get_id() {
		return $this->setting->get_slug();
	}

	/**
	 * Sanitize the value.
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string
	 */
	public function sanitize_value( $value ) {
		if ( 0 === strlen( $value ) && $this->setting->has_param( 'default' ) ) {
			$value = $this->setting->get_param( 'default' );
		}

		$value = sanitize_text_field( $value );

		return $this->validate_value( $value );
	}

	/**
	 * Validate the value against input attributes.
	 *
	 * Clamps numeric values to the min/max bounds defined in the input attributes.
	 *
	 * @param string $value The value to validate.
	 *
	 * @return string
	 */
	public function validate_value( $value ) {
		$attributes = $this->setting->has_param( 'attributes' ) ? $this->setting->get_param( 'attributes' ) : array();
		if ( is_array( $attributes ) && isset( $attributes['type'] ) && 'number' === $attributes['type'] && '' !== $value ) {
			$numeric = (float) $value;
			if ( isset( $attributes['min'] ) && $numeric < (float) $attributes['min'] ) {
				$value = (string) $attributes['min'];
			}
			if ( isset( $attributes['max'] ) && $numeric > (float) $attributes['max'] ) {
				$value = (string) $attributes['max'];
			}
		}

		return $value;
	}
}
