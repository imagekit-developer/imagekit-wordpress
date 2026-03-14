<?php
/**
 * Utility functions
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

use DateTime;
use Exception;
use ImageKitWordpress\Settings\Setting;

class Utils {

	/**
	 * Holds a list of temp files to be purged.
	 *
	 * @var array
	 */
	public static $file_fragments = array();

	/**
	 * Get the rest URL.
	 *
	 * @param string $path   The path to be appended to the rest URL.
	 * @param string $scheme The scheme to give the rest URL context. Accepts 'http', 'https', or 'relative'.
	 *
	 * @return string
	 */
	public static function rest_url( $path = '', $scheme = null ) {
		$rest_url = rest_url( $path, $scheme );

		/**
		 * Filter the rest url.
		 *
		 * @hook imagekit_rest_url
		 * @since 3.2.2
		 *
		 * @param $rest_url {string} The rest url.
		 * @param $path     {string} The path to be appended to the rest URL.
		 * @param $scheme   {string} The scheme to give the rest URL context. Accepts 'http', 'https', or 'relative'.
		 *
		 * @return {string}
		 */
		return apply_filters( 'imagekit_rest_url', $rest_url, $path, $scheme );
	}

	public static function install( $previous_version = null, $new_version = null ) {
	}

	/**
	 * Check if the current user can perform a task.
	 *
	 * @param string $task       The task to check.
	 * @param string $capability The default capability.
	 * @param string $context    The context for the task.
	 * @param mixed  ...$args    Optional further parameters.
	 *
	 * @return bool
	 */
	public static function user_can( $task, $capability = 'manage_options', $context = '', ...$args ) {
		$capability = apply_filters( "imagekit_task_capability_{$task}", $capability, $context, ...$args );
		$capability = apply_filters( 'imagekit_task_capability', $capability, $task, $context, ...$args );
		return current_user_can( $capability, ...$args );
	}

	/**
	 * Get a sanitized input text field.
	 *
	 * @param string $var_name The value to get.
	 * @param int    $type     The type to get.
	 *
	 * @return mixed
	 */
	public static function get_sanitized_text( $var_name, $type = INPUT_GET ) {
		return filter_input( $type, $var_name, FILTER_CALLBACK, array( 'options' => 'sanitize_text_field' ) );
	}


	/**
	 * Gets the active child setting.
	 *
	 * @return Setting
	 */
	public static function get_active_setting() {
		$settings = get_plugin_instance()->settings;
		$active   = null;
		if ( $settings->has_param( 'active_setting' ) ) {
			$active = $settings->get_setting( $settings->get_param( 'active_setting' ) );
		}

		return $active;
	}

	/**
	 * Gets support link
	 *
	 * @return string
	 */
	public static function get_support_link() {
		return 'https://community.imagekit.io';
	}


	/**
	 * Get the site URL.
	 *
	 * @param string $path   The path to be appended to the site URL.
	 * @param string $scheme The scheme to give the site URL context. Accepts 'http', 'https', or 'relative'.
	 *
	 * @return string
	 */
	public static function site_url( $path = '', $scheme = null ) {
		$blog_id = null;
		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();
		}
		$site_url = get_site_url( $blog_id, $path, $scheme );

		/**
		 * Filter the site URL.
		 *
		 * @hook imagekit_site_url
		 * @since 3.2.2
		 *
		 * @param $site_url {string} The site URL.
		 * @param $path     {string} The path to be appended to the site URL.
		 * @param $scheme   {string} The scheme to give the site URL context. Accepts 'http', 'https', or 'relative'.
		 *
		 * @return {string}
		 */
		return apply_filters( 'imagekit_site_url', $site_url, $path, $scheme );
	}

	public static function sanitize_date_string( $str ) {
		try {
			$dt = new DateTime( $str );
			return $dt->format( DateTime::ATOM );
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Download a fragment of a file URL to a temp file and return the file URI.
	 *
	 * @param string $url  The URL to download.
	 * @param int    $size The size of the fragment to download.
	 *
	 * @return string|false
	 */
	public static function download_fragment( $url, $size = 1048576 ) {

		$temp_file = wp_tempnam( basename( $url ) );
		$pointer   = fopen( $temp_file, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$file      = false;
		if ( $pointer ) {
			// Prep to purge.
			$index = count( self::$file_fragments );
			if ( empty( $index ) ) {
				add_action( 'shutdown', array( __CLASS__, 'purge_fragments' ) );
			}
			self::$file_fragments[ $index ] = array(
				'pointer' => $pointer,
				'file'    => $temp_file,
			);
			// Get the metadata of the stream.
			$data = stream_get_meta_data( $pointer );
			// Stream the content to the temp file.
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'             => 300, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
					'stream'              => true,
					'filename'            => $data['uri'],
					'limit_response_size' => $size,
				)
			);
			if ( ! is_wp_error( $response ) ) {
				$file = $data['uri'];
			} else {
				// Clean up if there was an error.
				self::purge_fragment( $index );
			}
		}

		return $file;
	}

	/**
	 * Purge fragment temp files on shutdown.
	 */
	public static function purge_fragments() {
		foreach ( array_keys( self::$file_fragments ) as $index ) {
			self::purge_fragment( $index );
		}
	}

	/**
	 * Purge a fragment temp file.
	 *
	 * @param int $index The index of the fragment to purge.
	 */
	public static function purge_fragment( $index ) {
		if ( isset( self::$file_fragments[ $index ] ) ) {
			fclose( self::$file_fragments[ $index ]['pointer'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
			unlink( self::$file_fragments[ $index ]['file'] ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
		}
	}

	/**
	 * Returns information about a file path by normalizing the locale.
	 *
	 * @param string $path  The path to be parsed.
	 * @param int    $flags Specifies a specific element to be returned.
	 *                      Defaults to 15 which stands for PATHINFO_ALL.
	 *
	 * @return array|string|string[]
	 */
	public static function pathinfo( $path, $flags = 15 ) {

		/**
		 * Approach based on wp_basename.
		 *
		 * @see wp-includes/formatting.php
		 */
		$path = str_replace( array( '%2F', '%5C' ), '/', urlencode( $path ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode

		$pathinfo = pathinfo( $path, $flags );

		return is_array( $pathinfo ) ? array_map( 'urldecode', $pathinfo ) : urldecode( $pathinfo );
	}


	/**
	 * Check if we are in WordPress ajax.
	 *
	 * @return bool
	 */
	public static function is_frontend_ajax() {
		$referer    = wp_get_referer();
		$admin_base = admin_url();
		$is_admin   = $referer ? 0 === strpos( $referer, $admin_base ) : false;
		// Check if this is a frontend ajax request.
		$is_frontend_ajax = ! $is_admin && defined( 'DOING_AJAX' ) && DOING_AJAX;
		// If it's not an obvious WP ajax request, check if it's a custom frontend ajax request.
		if ( ! $is_frontend_ajax && ! $is_admin ) {
			// Catch the content type of the $_SERVER['CONTENT_TYPE'] variable.
			$type             = filter_input( INPUT_SERVER, 'CONTENT_TYPE', FILTER_CALLBACK, array( 'options' => 'sanitize_text_field' ) );
			$is_frontend_ajax = $type && false !== strpos( $type, 'json' );
		}

		return $is_frontend_ajax;
	}

	/**
	 * Check if this is an admin request, but not an ajax one.
	 *
	 * @return bool
	 */
	public static function is_admin() {
		return is_admin() && ! self::is_frontend_ajax();
	}
}
