<?php
/**
 * Credentials UI Component.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress\UI\Component;

use ImageKitWordpress\UI\Component;
use ImageKitWordpress\Settings\Setting;
use function ImageKitWordpress\get_plugin_instance;

/**
 * Credentials Component.
 *
 * @package ImageKitWordpress\UI
 */
class Credentials extends Component {

	/**
	 * Holds the components build blueprint.
	 *
	 * @var string
	 */
	protected $blueprint = 'wrap|status_header|status_icon/|status_text|intro/|message/|/status_text|status_actions|reconfigure/|disconnect/|/status_actions|/status_header|credentials_section|credentials_title/|credentials_list|credential_row_id/|credential_row_public/|credential_row_private/|/credentials_list|/credentials_section|/wrap';

	/**
	 * Holder the Connect object.
	 *
	 * @var Connect
	 */
	protected $connect;

	/**
	 * The configured url endpoint.
	 *
	 * @var string
	 */
	protected $url_endpoint = '';

	/**
	 * The configured public key.
	 *
	 * @var string
	 */
	protected $public_key = '';

	/**
	 * The configured private key.
	 *
	 * @var string
	 */
	protected $private_key = '';

	/**
	 * The masked private key.
	 *
	 * @var string
	 */
	protected $masked_private_key = '';

	/**
	 * The reconfigure url.
	 *
	 * @var string
	 */
	protected $reconfigure_url = '';

	/**
	 * The user display name.
	 *
	 * @var string
	 */
	protected $user_display_name = '';

	/**
	 * Plan constructor.
	 *
	 * @param Setting $setting The parent Setting.
	 */
	public function __construct( $setting ) {
		$plugin = get_plugin_instance();

		$this->connect = $plugin->get_component( 'credentials_manager' );

		parent::__construct( $setting );
	}

	/**
	 * Setup the component.
	 */
	public function setup() {
		parent::setup();

		$credentials        = is_object( $this->connect ) && is_callable( array( $this->connect, 'get_credentials' ) ) ? (array) $this->connect->get_credentials() : array();
		$this->url_endpoint = ! empty( $credentials['url_endpoint'] ) ? (string) $credentials['url_endpoint'] : '';
		$this->public_key   = ! empty( $credentials['public_key'] ) ? (string) $credentials['public_key'] : '';
		$this->private_key  = ! empty( $credentials['private_key'] ) ? (string) $credentials['private_key'] : '';

		$this->masked_private_key = '';
		if ( ! empty( $this->private_key ) ) {
			$private_key_len = strlen( $this->private_key );
			$start_len       = min( 10, $private_key_len );
			$end_len         = min( 4, max( 0, $private_key_len - $start_len ) );

			$start = substr( $this->private_key, 0, $start_len );
			$end   = 0 < $end_len ? substr( $this->private_key, -$end_len ) : '';

			$mask_len                 = max( 4, $private_key_len - $start_len - $end_len );
			$this->masked_private_key = $start . str_repeat( '*', $mask_len ) . $end;
		}

		$plugin                = get_plugin_instance();
		$this->reconfigure_url = '';
		if ( ! empty( $plugin ) && ! empty( $plugin->slug ) ) {
			$this->reconfigure_url = self_admin_url(
				add_query_arg(
					array(
						'page'    => $plugin->slug,
						'section' => 'wizard',
					),
					'admin.php'
				)
			);
		}

		$user                    = wp_get_current_user();
		$this->user_display_name = ! empty( $user->display_name ) ? (string) $user->display_name : '';
	}

	/**
	 * Filter the connected message.
	 *
	 * @param array $struct The array structure.
	 *
	 * @return array
	 */
	protected function connect( array $struct ) {
		return $struct;
	}

	protected function wrap( array $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array( 'ik-connect-overview' );

		return $struct;
	}

	protected function status_header( array $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array( 'ik-connect-status-header' );

		return $struct;
	}

	protected function status_icon( array $struct ) {
		$struct['element']             = 'span';
		$struct['render']              = true;
		$struct['attributes']['class'] = array(
			'dashicons',
			'dashicons-yes-alt',
			'ik-connect-status-icon',
		);

		return $struct;
	}

	protected function status_text( array $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array( 'ik-connect-status-text' );

		return $struct;
	}

	protected function intro( array $struct ) {
		$struct['element'] = 'h3';
		$struct['content'] = sprintf( __( 'Hi %s', 'imagekit' ), $this->user_display_name );

		return $struct;
	}

	protected function message( array $struct ) {
		$struct['element'] = 'p';
		$struct['content'] = __( 'Your ImageKit integration is configured.', 'imagekit' );

		return $struct;
	}

	protected function status_actions( array $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array( 'ik-connect-status-actions' );

		return $struct;
	}

	protected function reconfigure( array $struct ) {
		$struct['element']             = 'a';
		$struct['content']             = __( 'Reconfigure', 'imagekit' );
		$struct['attributes']['href']  = $this->reconfigure_url;
		$struct['attributes']['class'] = array( 'button', 'button-primary', 'ik-connect-reconfigure' );

		return $struct;
	}

	protected function disconnect( array $struct ) {
		$struct['element']                    = 'button';
		$struct['content']                    = __( 'Disconnect', 'imagekit' );
		$struct['attributes']['type']         = 'submit';
		$struct['attributes']['name']         = 'credentials[url_endpoint]';
		$struct['attributes']['value']        = '';
		$struct['attributes']['data-confirm'] = __( 'Are you sure you want to disconnect from ImageKit?', 'imagekit' );
		$struct['attributes']['onclick']      = 'return confirm(this.getAttribute("data-confirm"))';
		$struct['attributes']['class']        = array( 'button', 'ik-connect-disconnect' );

		return $struct;
	}

	protected function credentials_section( array $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array( 'ik-connect-credentials' );

		return $struct;
	}

	protected function credentials_title( array $struct ) {
		$struct['element']             = 'h4';
		$struct['content']             = __( 'Configured credentials', 'imagekit' );
		$struct['attributes']['class'] = array( 'ik-connect-credentials-title' );

		return $struct;
	}

	protected function credentials_list( array $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array( 'ik-connect-credentials-list' );

		return $struct;
	}

	protected function credential_row_id( array $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array( 'ik-connect-credentials-row' );

		$label                        = $this->get_part( 'div' );
		$label['attributes']['class'] = array( 'ik-connect-credentials-label' );
		$label['content']             = __( 'ImageKit ID', 'imagekit' );

		$value                        = $this->get_part( 'code' );
		$value['attributes']['class'] = array( 'ik-connect-credentials-value' );
		$value['content']             = esc_html( $this->url_endpoint );

		$struct['children']['label'] = $label;
		$struct['children']['value'] = $value;

		return $struct;
	}

	protected function credential_row_public( array $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array( 'ik-connect-credentials-row' );

		$label                        = $this->get_part( 'div' );
		$label['attributes']['class'] = array( 'ik-connect-credentials-label' );
		$label['content']             = __( 'Public Key', 'imagekit' );

		$value                        = $this->get_part( 'code' );
		$value['attributes']['class'] = array( 'ik-connect-credentials-value' );
		$value['content']             = esc_html( $this->public_key );

		$struct['children']['label'] = $label;
		$struct['children']['value'] = $value;

		return $struct;
	}

	protected function credential_row_private( array $struct ) {
		$struct['element']             = 'div';
		$struct['attributes']['class'] = array( 'ik-connect-credentials-row' );

		$label                        = $this->get_part( 'div' );
		$label['attributes']['class'] = array( 'ik-connect-credentials-label' );
		$label['content']             = __( 'Private Key', 'imagekit' );

		$value                        = $this->get_part( 'code' );
		$value['attributes']['class'] = array( 'ik-connect-credentials-value' );
		$value['content']             = esc_html( $this->masked_private_key );

		$struct['children']['label'] = $label;
		$struct['children']['value'] = $value;

		return $struct;
	}
}
