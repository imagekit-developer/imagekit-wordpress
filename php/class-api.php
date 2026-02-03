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

	/**
	 * Upload a local file to ImageKit.
	 *
	 * Uses the ImageKit Upload API.
	 *
	 * @param string      $file_path Absolute local file path.
	 * @param string|null $file_name Optional name override.
	 * @param string      $folder    Optional folder path (no leading slash required).
	 * @param array       $options   Upload options.
	 *
	 * @return array|\WP_Error
	 */
	public function upload_file( $file_path, $file_name = null, $folder = '', $options = array() ) {
		if ( ! is_string( $file_path ) || '' === $file_path || ! file_exists( $file_path ) ) {
			return new \WP_Error( 'imagekit_upload_missing_file', 'File not found' );
		}
		if ( ! function_exists( 'curl_init' ) ) {
			return new \WP_Error( 'imagekit_upload_no_curl', 'cURL is required to upload files' );
		}

		$file_name = is_string( $file_name ) && '' !== $file_name ? $file_name : wp_basename( $file_path );
		$folder    = is_string( $folder ) ? trim( $folder ) : '';
		$folder    = trim( $folder, '/' );

		$payload = array(
			'fileName' => $file_name,
			'file'     => curl_file_create( $file_path ),
		);
		if ( '' !== $folder ) {
			$payload['folder'] = '/' . $folder;
		}
		if ( isset( $options['useUniqueFileName'] ) ) {
			$payload['useUniqueFileName'] = $options['useUniqueFileName'] ? 'true' : 'false';
		}
		if ( isset( $options['overwriteFile'] ) ) {
			$payload['overwriteFile'] = $options['overwriteFile'] ? 'true' : 'false';
		}

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, 'https://upload.imagekit.io/api/v1/files/upload' );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Authorization: ' . $this->generate_auth_header() ) );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 120 );

		$body      = curl_exec( $ch );
		$curl_err  = curl_error( $ch );
		$http_code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		curl_close( $ch );

		if ( false === $body ) {
			return new \WP_Error( 'imagekit_upload_failed', $curl_err ? $curl_err : 'Upload request failed' );
		}

		$result = json_decode( (string) $body, true );
		if ( $http_code >= 300 ) {
			$message = is_array( $result ) && ! empty( $result['message'] ) ? (string) $result['message'] : (string) $body;
			return new \WP_Error( 'imagekit_upload_http_error', $message, array( 'status' => $http_code ) );
		}
		if ( ! is_array( $result ) ) {
			return new \WP_Error( 'imagekit_upload_bad_response', 'Upload response was not valid JSON' );
		}
		if ( ! empty( $result['message'] ) && empty( $result['fileId'] ) ) {
			return new \WP_Error( 'imagekit_upload_error', (string) $result['message'] );
		}

		return $result;
	}

	public function imagekit_url( $file_path = null, $args = array(), $size = array(), $attachment_id = null ) {

		if ( null == $file_path ) {
			return $this->credentials['url_endpoint'];
		}
	}
}
