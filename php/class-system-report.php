<?php
/**
 * System Report
 *
 * Gathers environment and plugin information for debugging purposes.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

class System_Report {

	/**
	 * Get the full system report as structured sections.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_report() {
		return array(
			'WordPress'          => self::get_wordpress_info(),
			'ImageKit Plugin'    => self::get_plugin_info(),
			'ImageKit Settings'  => self::get_settings_info(),
			'ImageKit Usage'     => self::get_usage_info(),
			'Media Library'      => self::get_media_info(),
			'Server'             => self::get_server_info(),
			'PHP'                => self::get_php_info(),
			'Database'           => self::get_database_info(),
			'WordPress Constants' => self::get_constants_info(),
			'Filesystem'         => self::get_filesystem_info(),
			'Active Theme'       => self::get_theme_info(),
			'Active Plugins'     => self::get_active_plugins(),
			'Must-Use Plugins'   => self::get_mu_plugins(),
		);
	}

	/**
	 * Get the report formatted as plain text.
	 *
	 * @return string
	 */
	public static function get_report_text() {
		$report   = self::get_report();
		$sections = array();

		foreach ( $report as $title => $fields ) {
			$lines = array( "### {$title}" );
			foreach ( $fields as $label => $value ) {
				$lines[] = "{$label}: {$value}";
			}
			$sections[] = implode( "\n", $lines );
		}

		return implode( "\n\n", $sections );
	}

	/**
	 * WordPress environment info.
	 *
	 * @return array<string, string>
	 */
	protected static function get_wordpress_info() {
		global $wp_version;

		$info = array(
			'Version'         => $wp_version,
			'Site URL'        => get_site_url(),
			'Home URL'        => get_home_url(),
			'Multisite'       => is_multisite() ? 'Yes' : 'No',
			'Permalink'       => get_option( 'permalink_structure' ) ?: 'Plain',
			'Language'        => get_locale(),
			'Memory Limit'    => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : 'Not set',
			'Debug Mode'      => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'Enabled' : 'Disabled',
			'Debug Log'       => ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ? 'Enabled' : 'Disabled',
			'Cron'            => ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? 'Disabled' : 'Enabled',
			'Timezone'        => wp_timezone_string(),
			'Object Cache'    => wp_using_ext_object_cache() ? 'External' : 'Default',
			'ABSPATH'         => ABSPATH,
		);

		return $info;
	}

	/**
	 * ImageKit plugin info.
	 *
	 * @return array<string, string>
	 */
	protected static function get_plugin_info() {
		$plugin = get_plugin_instance();
		$info   = array(
			'Version'   => $plugin->version ?? 'Unknown',
			'Slug'      => $plugin->slug ?? 'Unknown',
			'Dir Path'  => $plugin->dir_path ?? 'Unknown',
			'Connected' => 'No',
		);

		if ( isset( $plugin->settings ) && method_exists( $plugin->settings, 'get_param' ) ) {
			$connected = $plugin->settings->get_param( 'connected' );
			if ( true === $connected ) {
				$info['Connected'] = 'Yes';
			} elseif ( 'partial' === $connected ) {
				$info['Connected'] = 'Partial';
			} else {
				$info['Connected'] = 'No';
			}
		}

		$manager = $plugin->get_component( 'credentials_manager' );
		if ( $manager && method_exists( $manager, 'get_credentials' ) ) {
			$credentials = (array) $manager->get_credentials();
			$info['URL Endpoint'] = ! empty( $credentials['url_endpoint'] ) ? $credentials['url_endpoint'] : '(not set)';
			// Mask the keys for security.
			$info['Public Key']  = ! empty( $credentials['public_key'] ) ? self::mask_key( $credentials['public_key'] ) : '(not set)';
			$info['Private Key'] = ! empty( $credentials['private_key'] ) ? self::mask_key( $credentials['private_key'] ) : '(not set)';
		}

		return $info;
	}

	/**
	 * ImageKit plugin settings.
	 *
	 * @return array<string, string>
	 */
	protected static function get_settings_info() {
		$info = array();

		$media_display = get_option( 'imagekit_media_display' );
		if ( is_array( $media_display ) ) {
			foreach ( $media_display as $key => $value ) {
				$label          = ucwords( str_replace( '_', ' ', $key ) );
				$info[ $label ] = ( '' === $value ) ? '(empty)' : $value;
			}
		} else {
			$info['Media Display'] = '(not configured)';
		}

		$upload = get_option( 'imagekit_upload' );
		if ( is_array( $upload ) ) {
			$info['Upload Folder'] = ! empty( $upload['imagekit_folder'] ) ? $upload['imagekit_folder'] : '(root)';
			$info['Storage Mode']  = ! empty( $upload['offload'] ) ? $upload['offload'] : '(not set)';
		}

		return $info;
	}

	/**
	 * Server environment info.
	 *
	 * @return array<string, string>
	 */
	protected static function get_server_info() {
		$server_software = ! empty( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown';

		$info = array(
			'Software'      => $server_software,
			'HTTPS'         => is_ssl() ? 'Yes' : 'No',
			'Max Input Vars' => ini_get( 'max_input_vars' ) ?: 'Unknown',
			'Max Upload Size' => size_format( wp_max_upload_size() ),
			'Max Post Size'  => ini_get( 'post_max_size' ) ?: 'Unknown',
			'Max Exec Time'  => ini_get( 'max_execution_time' ) . 's',
			'cURL Version'   => function_exists( 'curl_version' ) ? curl_version()['version'] : 'Not available',
			'cURL SSL'       => function_exists( 'curl_version' ) ? ( curl_version()['ssl_version'] ?? 'N/A' ) : 'Not available',
		);

		return $info;
	}

	/**
	 * PHP environment info.
	 *
	 * @return array<string, string>
	 */
	protected static function get_php_info() {
		$info = array(
			'Version'          => phpversion(),
			'SAPI'             => php_sapi_name(),
			'Memory Limit'     => ini_get( 'memory_limit' ) ?: 'Unknown',
			'GD Library'       => extension_loaded( 'gd' ) ? 'Available' : 'Not available',
			'Imagick'          => extension_loaded( 'imagick' ) ? 'Available' : 'Not available',
			'Exif'             => extension_loaded( 'exif' ) ? 'Available' : 'Not available',
			'mbstring'         => extension_loaded( 'mbstring' ) ? 'Available' : 'Not available',
			'OpenSSL'          => extension_loaded( 'openssl' ) ? OPENSSL_VERSION_TEXT : 'Not available',
		);

		return $info;
	}

	/**
	 * Database info.
	 *
	 * @return array<string, string>
	 */
	protected static function get_database_info() {
		global $wpdb;

		$info = array(
			'Extension' => 'Unknown',
			'Server'    => 'Unknown',
			'Client'    => 'Unknown',
			'Prefix'    => $wpdb->prefix,
		);

		if ( method_exists( $wpdb, 'db_version' ) ) {
			$info['Server'] = $wpdb->db_version();
		}

		if ( method_exists( $wpdb, 'db_server_info' ) ) {
			$info['Server Info'] = $wpdb->db_server_info();
		}

		if ( defined( 'DB_CHARSET' ) ) {
			$info['Charset'] = DB_CHARSET;
		}

		if ( defined( 'DB_COLLATE' ) && DB_COLLATE ) {
			$info['Collation'] = DB_COLLATE;
		}

		return $info;
	}

	/**
	 * Active theme info.
	 *
	 * @return array<string, string>
	 */
	protected static function get_theme_info() {
		$theme = wp_get_theme();
		$info  = array(
			'Name'         => $theme->get( 'Name' ),
			'Version'      => $theme->get( 'Version' ),
			'Author'       => $theme->get( 'Author' ),
			'Template'     => $theme->get_template(),
			'Is Child'     => is_child_theme() ? 'Yes' : 'No',
		);

		if ( is_child_theme() ) {
			$parent               = wp_get_theme( $theme->get_template() );
			$info['Parent Theme'] = $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' );
		}

		return $info;
	}

	/**
	 * Active plugins list.
	 *
	 * @return array<string, string>
	 */
	protected static function get_active_plugins() {
		$plugins = array();

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active  = get_option( 'active_plugins', array() );
		$all     = get_plugins();

		foreach ( $active as $plugin_file ) {
			if ( isset( $all[ $plugin_file ] ) ) {
				$data = $all[ $plugin_file ];
				$name = $data['Name'];
				$plugins[ $name ] = $data['Version'];
			}
		}

		if ( empty( $plugins ) ) {
			$plugins['(none)'] = '';
		}

		return $plugins;
	}

	/**
	 * Must-use plugins list.
	 *
	 * @return array<string, string>
	 */
	protected static function get_mu_plugins() {
		$plugins = array();

		if ( ! function_exists( 'get_mu_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$mu_plugins = get_mu_plugins();
		foreach ( $mu_plugins as $plugin_file => $data ) {
			$name             = $data['Name'];
			$plugins[ $name ] = $data['Version'];
		}

		if ( empty( $plugins ) ) {
			$plugins['(none)'] = '';
		}

		return $plugins;
	}

	/**
	 * ImageKit usage stats from DB.
	 *
	 * @return array<string, string>
	 */
	protected static function get_usage_info() {
		$usage = get_option( 'imagekit_last_usage' );
		if ( ! is_array( $usage ) || empty( $usage ) ) {
			return array( 'Usage Data' => '(not available)' );
		}

		$info = array();
		foreach ( $usage as $key => $value ) {
			$label = ucwords( str_replace( '_', ' ', $key ) );
			if ( is_numeric( $value ) && $value > 1024 ) {
				$info[ $label ] = size_format( (int) $value );
			} else {
				$info[ $label ] = (string) $value;
			}
		}

		return $info;
	}

	/**
	 * Media library info.
	 *
	 * @return array<string, string>
	 */
	protected static function get_media_info() {
		global $wpdb;

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" );
		$images = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'" );
		$videos = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'video/%'" );

		$editor = _wp_image_editor_choose();
		$editor_name = $editor ? ( new \ReflectionClass( $editor ) )->getShortName() : 'None';

		$info = array(
			'Total Attachments' => number_format_i18n( $total ),
			'Images'            => number_format_i18n( $images ),
			'Videos'            => number_format_i18n( $videos ),
			'Other'             => number_format_i18n( $total - $images - $videos ),
			'Image Editor'      => $editor_name,
			'Max Upload Size'   => size_format( wp_max_upload_size() ),
		);

		return $info;
	}

	/**
	 * WordPress constants.
	 *
	 * @return array<string, string>
	 */
	protected static function get_constants_info() {
		$constants = array(
			'WP_CONTENT_DIR' => defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '(not defined)',
			'WP_CONTENT_URL' => defined( 'WP_CONTENT_URL' ) ? WP_CONTENT_URL : '(not defined)',
			'UPLOADS'        => defined( 'UPLOADS' ) ? UPLOADS : '(not defined)',
			'CONCATENATE_SCRIPTS' => defined( 'CONCATENATE_SCRIPTS' ) ? ( CONCATENATE_SCRIPTS ? 'true' : 'false' ) : '(not defined)',
			'COMPRESS_SCRIPTS'    => defined( 'COMPRESS_SCRIPTS' ) ? ( COMPRESS_SCRIPTS ? 'true' : 'false' ) : '(not defined)',
			'WP_AUTO_UPDATE_CORE' => defined( 'WP_AUTO_UPDATE_CORE' ) ? (string) WP_AUTO_UPDATE_CORE : '(not defined)',
			'DISALLOW_FILE_EDIT'  => defined( 'DISALLOW_FILE_EDIT' ) ? ( DISALLOW_FILE_EDIT ? 'true' : 'false' ) : '(not defined)',
		);

		return $constants;
	}

	/**
	 * Filesystem writable checks.
	 *
	 * @return array<string, string>
	 */
	protected static function get_filesystem_info() {
		$upload_dir = wp_upload_dir();

		$info = array(
			'Filesystem Method' => get_filesystem_method(),
			'Upload Dir'        => $upload_dir['basedir'],
			'Upload URL'        => $upload_dir['baseurl'],
			'Upload Writable'   => wp_is_writable( $upload_dir['basedir'] ) ? 'Yes' : 'No',
			'Content Writable'  => wp_is_writable( WP_CONTENT_DIR ) ? 'Yes' : 'No',
			'Plugin Dir Writable' => wp_is_writable( WP_PLUGIN_DIR ) ? 'Yes' : 'No',
		);

		return $info;
	}

	/**
	 * Mask a key, showing only the first 10 and last 4 characters.
	 *
	 * @param string $key The key to mask.
	 *
	 * @return string
	 */
	protected static function mask_key( $key ) {
		$len = strlen( $key );
		if ( $len <= 14 ) {
			return str_repeat( '*', $len );
		}

		return substr( $key, 0, 10 ) . str_repeat( '*', $len - 14 ) . substr( $key, -4 );
	}
}
