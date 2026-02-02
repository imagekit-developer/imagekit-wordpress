<?php
/**
 * Credentials_Manager class for ImageKitWordpress.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

use ImageKitWordpress\Component\Config;
use ImageKitWordpress\Component\Setup;
use ImageKitWordpress\API;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * ImageKit credential manager class.
 *
 * Sets up the initial ImageKit connection and makes the API object available for some uses.
 */
class Credentials_Manager extends Settings_Component implements Config, Setup {
	/**
	 * Holds the plugin instance.
	 *
	 * @since   5.0.0
	 *
	 * @var     Plugin Instance of the global plugin.
	 */
	protected $plugin;

	/**
	 * Holds the imagekit API instance
	 *
	 * @since   5.0.0
	 *
	 * @var     Api
	 */
	public $api;

	/**
	 * Holds the imagekit credentials.
	 *
	 * @since   5.0.0
	 *
	 * @var     array
	 */
	private $credentials = array();

	/**
	 * Holds the ImageKit usage info.
	 *
	 * @since   5.0.0
	 *
	 * @var     array
	 */
	public $usage;

	/**
	 * Holds the meta keys for connect meta to maintain consistency.
	 */
	const META_KEYS = array(
		'usage'       => '_ik_usage',
		'last_usage'  => '_ik_last_usage',
		'version'     => '_ik_settings_version',
		'url'         => 'url_endpoint',
		'public_key'  => 'public_key',
		'private_key' => 'private_key',
		'status'      => 'ik_status',
	);

	/**
	 * Initiate the plugin resources.
	 *
	 * @param \ImageKitWordpress\Plugin $plugin Instance of the plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin        = $plugin;
		$this->settings_slug = 'dashboard';
		add_filter( 'pre_update_option_imagekit_credentials', array( $this, 'verify_connection' ) );
		add_action( 'imagekit_version_upgrade', array( $this, 'upgrade_connection' ) );
		add_filter( 'imagekit_settings_pages', array( $this, 'register_meta' ) );
		add_filter( 'imagekit_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
	}

	/**
	 * Add endpoints to the \ImageKitWordpress\REST_API::$endpoints array.
	 *
	 * @param array $endpoints Endpoints from the filter.
	 *
	 * @return array
	 */
	public function rest_endpoints( $endpoints ) {

		$endpoints['test_connection'] = array(
			'method'              => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_test_connection' ),
			'args'                => array(),
			'permission_callback' => array( 'ImageKitWordpress\REST_API', 'rest_can_connect' ),
		);
		$endpoints['save_wizard']     = array(
			'method'              => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_save_wizard' ),
			'args'                => array(),
			'permission_callback' => array( 'ImageKitWordpress\REST_API', 'rest_can_connect' ),
		);

		return $endpoints;
	}

	/**
	 * Test a connection string.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_test_connection( WP_REST_Request $request ) {
		$url_endpoint = $request->get_param( 'urlEndpoint' );
		$public_key   = $request->get_param( 'publicKey' );
		$private_key  = $request->get_param( 'privateKey' );

		$result = $this->test_connection( $url_endpoint, $public_key, $private_key );

		return rest_ensure_response( $result );
	}


	/**
	 * Save the wizard setup.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_save_wizard( WP_REST_Request $request ) {
		$url_endpoint = $request->get_param( 'urlEndpoint' );
		$public_key   = $request->get_param( 'publicKey' );
		$private_key  = $request->get_param( 'privateKey' );

		$url_endpoint_setting = $this->settings->get_setting( 'credentials.url_endpoint' );
		$url_endpoint_setting->set_pending( $url_endpoint );

		$public_key_setting = $this->settings->get_setting( 'credentials.public_key' );
		$public_key_setting->set_pending( $public_key );

		$private_key_setting = $this->settings->get_setting( 'credentials.private_key' );
		$private_key_setting->set_pending( $private_key );

		$this->settings->save();

		$this->plugin->settings->set_param( 'connected', true );

		return rest_ensure_response( $this->settings->get_value() );
	}

	/**
	 * Register meta data with the pages/settings.
	 *
	 * @param array $pages The pages array.
	 *
	 * @return array
	 */
	public function register_meta( $pages ) {

		// Add data storage.
		foreach ( self::META_KEYS as $slug => $option_name ) {
			if ( 'url' === $slug ) {
				continue; // URL already set.
			}
			$pages['credentials']['settings'][] = array(
				'slug'        => $slug,
				'option_name' => $option_name,
				'type'        => 'data',
			);
		}

		return $pages;
	}

	/**
	 * Check whether a connection was established.
	 *
	 * @return boolean
	 */
	public function is_connected() {
		$connected = $this->plugin->settings->get_param( 'connected', null );
		if ( ! is_null( $connected ) ) {
			return $connected;
		}
		$url_endpoint = $this->settings->get_value( 'credentials.url_endpoint' );
		if ( empty( $url_endpoint ) ) {
			return false;
		}

		$public_key  = $this->settings->get_value( 'credentials.public_key' );
		$private_key = $this->settings->get_value( 'credentials.private_key' );
		if ( empty( $public_key ) || empty( $private_key ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Test the connection.
	 *
	 * @param string $url_endpoint The url endpoint to test.
	 * @param string $public_key   The public key to test.
	 * @param string $private_key  The private key to test.
	 *
	 * @return array
	 */
	public function test_connection( $url_endpoint, $public_key, $private_key ) {
		$result = array(
			'ok'          => true,
			'code'        => 'connection_success',
			'message'     => null,
			'fieldErrors' => array(),
		);

		$test_url_endpoint = $this->test_url_endpoint( $url_endpoint );
		$test_public_key   = str_starts_with( $public_key, 'public_' );
		$test_private_key  = str_starts_with( $private_key, 'private_' );

		if ( ! $test_url_endpoint ) {
			$result['ok']      = false;
			$result['code']    = 'invalid_url';
			$result['message'] = __( 'Validation failed', 'imagekit' );

			$result['fieldErrors']['urlEndpoint'] = sprintf(
				__( 'Incorrect Format. Expecting: %s', 'imagekit' ),
				'https://ik.imagekit.io/imagekit_id'
			);

			return $result;
		}

		if ( ! $test_public_key ) {
			$result['ok']                       = false;
			$result['code']                     = 'invalid_public_key';
			$result['message']                  = __( 'Validation failed', 'imagekit' );
			$result['fieldErrors']['publicKey'] = sprintf(
				__( 'Incorrect Public key. Expecting %s', 'imagekit' ),
				'public_XXXXXXXXXXXXXX'
			);

			return $result;
		}

		if ( ! $test_private_key ) {
			$result['ok']                        = false;
			$result['code']                      = 'invalid_private_key';
			$result['message']                   = __( 'Validation failed', 'imagekit' );
			$result['fieldErrors']['privateKey'] = sprintf(
				__( 'Incorrect Private key. Expecting %s', 'imagekit' ),
				'private_XXXXXXXXXXXXXX'
			);

			return $result;
		}

		$this->set_credentials(
			array(
				'url_endpoint' => $url_endpoint,
				'public_key'   => $public_key,
				'private_key'  => $private_key,
			)
		);

		$test_result = $this->check_status();

		if ( is_wp_error( $test_result ) ) {
			$error        = $test_result->get_error_message();
			$result['ok'] = false;
			if ( 'your account is disabled' !== strtolower( $error ) ) {
				$result['code'] = 'connection_error';
			}
			$result['message'] = $test_result->get_error_message();
		} else {
			$this->usage_stats( true );
		}

		return $result;
	}

	/**
	 * Upgrade method for version changes.
	 *
	 * @param string $previous_version The previous version number.
	 * @param string $new_version      The New version number.
	 */
	public function upgrade_settings( $previous_version, $new_version ) {
	}

	/**
	 * Get the ImageKit credentials.
	 *
	 * @return array
	 */
	public function get_credentials() {
		return $this->credentials;
	}

	/**
	 * Get the url endpoint if set.
	 *
	 * @return string|null
	 */
	public function get_url_endpoint() {
		return ! empty( $this->credentials['url_endpoint'] ) ? $this->credentials['url_endpoint'] : null;
	}

	/**
	 * Set the config credentials from an array.
	 *
	 * @param array $data The config array data.
	 *
	 * @return array
	 */
	public function set_credentials( $data = array() ) {
		$this->credentials = array_merge( $this->credentials, $data );

		return $this->credentials;
	}

	/**
	 * Setup connection
	 *
	 * @since  0.1
	 */
	public function setup() {
		$imagekit_url_endpoint = $this->settings->get_value( 'credentials.url_endpoint' );
		$imagekit_public_key   = $this->settings->get_value( 'credentials.public_key' );
		$imagekit_private_key  = $this->settings->get_value( 'credentials.private_key' );
		if ( ! empty( $imagekit_url_endpoint ) && ! empty( $imagekit_public_key ) && ! empty( $imagekit_private_key ) ) {

			$this->set_credentials(
				array(
					'url_endpoint' => $imagekit_url_endpoint,
					'public_key'   => $imagekit_public_key,
					'private_key'  => $imagekit_private_key,
				)
			);
			$this->api = new API( $this, $this->plugin->version );
			$this->usage_stats();

			$this->plugin->settings->set_param( 'connected', $this->is_connected() );
		}
	}

	/**
	 * Gets the config of a connection.
	 */
	public function get_config() {
		$old_version = $this->settings->get_value( 'version' );
		if ( empty( $old_version ) ) {
			$old_version = '2.0.1';
		}
		if ( version_compare( $this->plugin->version, $old_version, '>' ) ) {
			/**
			 * Do action to allow upgrading of different areas.
			 *
			 * @since 2.3.1
			 *
			 * @param string $new_version The version upgrading to.
			 *
			 * @param string $old_version The version upgrading from.
			 */
			do_action( 'ImageKit_version_upgrade', $old_version, $this->plugin->version );
		}
	}


	/**
	 * Upgrade connection settings.
	 *
	 * @param string $old_version The previous version.
	 */
	public function upgrade_connection( $old_version ) {
	}

	public function usage_stats( $refresh = false ) {
		$stats = get_transient( self::META_KEYS['usage'] );
		if ( empty( $stats ) || true === $refresh ) {
			$last_usage = $this->settings->get_setting( 'last_usage' );

			$stats = $this->api->usage();
			if ( ! is_wp_error( $stats ) ) {
				$last_usage->save_value( $stats );
			} else {
				$this->log_usage_stats_issue( $stats );
				$stats = $last_usage->get_value();
			}
			set_transient( self::META_KEYS['usage'], $stats, HOUR_IN_SECONDS );
		}
		$this->usage = $stats;
	}

	public function get_usage_stat( $stat, $value_type ) {
		if ( empty( $this->usage ) || empty( $stat ) || empty( $value_type ) ) {
			return 0;
		}

		$key = $stat . ucfirst( (string) $value_type );

		if ( ! isset( $this->usage[ $key ] ) ) {
			return 0;
		}

		return $this->usage[ $key ];
	}

	private function log_usage_stats_issue( $stats ) {
		if ( ! ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) ) {
			return;
		}

		$context = array(
			'refresh' => true,
		);

		if ( is_wp_error( $stats ) ) {
			$context['type']    = 'wp_error';
			$context['code']    = $stats->get_error_code();
			$context['message'] = $stats->get_error_message();
		} elseif ( is_array( $stats ) ) {
			$context['type'] = 'array';
			$context['keys'] = implode( ',', array_keys( $stats ) );
		} elseif ( is_string( $stats ) ) {
			$context['type']   = 'string';
			$context['sample'] = substr( $stats, 0, 200 );
			$context['length'] = strlen( $stats );
		} else {
			$context['type'] = gettype( $stats );
		}

		error_log( 'ImageKit: usage stats request failed or returned unexpected response. ' . wp_json_encode( $context ) );
	}

	private function test_url_endpoint( $url_endpoint ) {
		$is_valid = false;

		if ( filter_var( $url_endpoint, FILTER_VALIDATE_URL ) ) {

			$host = parse_url( $url_endpoint, PHP_URL_HOST );

			if ( $host === 'ik.imagekit.io' ) {
				$is_valid = true;
			} elseif ( checkdnsrr( $host, 'A' ) || checkdnsrr( $host, 'AAAA' ) ) {
				$is_valid = true;
			} else {
				$is_valid = false;
			}
		}

		return $is_valid;
	}

	private function check_status() {
		$status = $this->test_ping();
		$this->settings->get_setting( 'status' )->save_value( $status );

		return $status;
	}

	private function test_ping() {
		$test      = new API( $this, $this->plugin->version );
		$this->api = $test;

		return $test->ping();
	}

	public function verify_connection( $data ) {
		$admin = $this->plugin->get_component( 'admin' );

		if ( empty( $data['url_endpoint'] ) ) {
			unset( $data[ self::META_KEYS['url'] ] );
			unset( $data[ self::META_KEYS['public_key'] ] );
			unset( $data[ self::META_KEYS['private_key'] ] );

			$this->plugin->settings->set_param( 'connected', false );

			return $data;
		}

		return $data;
	}
}
