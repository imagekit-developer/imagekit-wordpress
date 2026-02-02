<?php
/**
 * ImageKit Api wrapper
 *
 * @package ImageKitWordpress
 */
namespace ImageKitWordpress;

use ImageKitWordpress\Plugin;
use ImageKitWordpress\Utils;

/**
 * Class API
 */
class API {
	/**
	 * The imagekit credentials array.
	 *
	 * @var array
	 */
	public $credentials;


	/**
	 * The plugin version
	 *
	 * @var string
	 */
	public $plugin_version;

	/**
	 * Api Constructor
	 *
	 * @param Credentials_Manager $manager The manager instance
	 * @param string              $version The plugin version
	 */
	public function __construct( $manager, $version ) {
		$this->credentials    = $manager->get_credentials();
		$this->plugin_version = $version;

		add_action( 'imagekit_ready', array( $this, 'setup' ) );
	}

	/**
	 * Setup the API
	 *
	 * @param Plugin $plugin The plugin instance.
	 */
	public function setup( $plugin ) {
		// print_r( $plugin );
	}

	public function usage() {
		$start_date = gmdate( 'Y-m-01', current_time( 'timestamp' ) );
		$end_date   = gmdate( 'Y-m-d', current_time( 'timestamp' ) + DAY_IN_SECONDS );

		$url = add_query_arg(
			array(
				'startDate' => $start_date,
				'endDate'   => $end_date,
			),
			'https://api.imagekit.io/v1/accounts/usage'
		);

		return $this->call( $url );
	}

	public function ping() {
		$date = gmdate( 'Y-m-d', current_time( 'timestamp' ) );

		$url = add_query_arg(
			array(
				'startDate' => $date,
				'endDate'   => $date,
			),
			'https://api.imagekit.io/v1/accounts/usage'
		);

		return $this->call( $url );
	}

	private function generate_auth_header() {
		return 'Basic ' . base64_encode( $this->credentials['private_key'] . ':' );
	}


	private function call( $url, $args = array(), $method = 'get' ) {
		$args['method']                   = strtoupper( $method );
		$args['user-agent']               = 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) . ' (' . $this->plugin_version . ')';
		$args['headers']['referer']       = Utils::site_url();
		$args['headers']['Authorization'] = $this->generate_auth_header();

		if ( 'GET' !== $args['method'] ) {
			ksort( $args['body'] );
			add_action( 'http_api_curl', array( $this, 'set_data' ), 10, 3 );
			$this->pending_url = $url;
		}

		$request = wp_remote_request( $url, $args );
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		$body   = wp_remote_retrieve_body( $request );
		$result = json_decode( $body, ARRAY_A );

		if ( empty( $result ) && ! empty( $body ) ) {
			return $body; // not json.
		}
		if ( ! empty( $result['message'] ) ) {
			return new \WP_Error( $request['response']['code'], $result['message'] );
		}

		return $result;
	}

	public function imagekit_url( $file_path = null, $args = array(), $size = array(), $attachment_id = null ) {

		if ( null == $file_path ) {
			return $this->credentials['url_endpoint'];
		}
	}
}
