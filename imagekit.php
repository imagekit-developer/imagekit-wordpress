<?php
/**
 * Plugin Name: ImageKit
 * Plugin URI: https://imagekit.io/docs/integration/wordpress
 * Description: A WordPress plugin to automatically fetch your WordPress images via <a href="https://www.imagekit.io" target="_blank">ImageKit</a> for optimization and super fast delivery. <a href="https://imagekit.io/blog/how-to-optimize-images-on-wordpress-website-using-imagekit/" target="_blank">Learn more</a> from documentation.
 * Author: ImageKit
 * Author URI: https://imagekit.io
 * Version: STABLETAG
 * Text Domain: imagekit
 * Domain Path: /languages
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

if ( ! defined( constant_name: 'ABSPATH' ) ) {
	exit;
}

define( 'IK_PLUGIN_ENTRYPOINT', __FILE__ );
define( 'IK_PLUGIN_PATH', plugin_dir_path( IK_PLUGIN_ENTRYPOINT ) );


if ( ! defined( 'IK_EML_VERSION' ) ) {
	define( 'IK_VERSION', 'latest' );
}

if ( ! defined( 'IK_DEBUG' ) ) {
	define( 'IK_DEBUG', false );
}

if ( version_compare( phpversion(), '5.6', '>=' ) ) {
	require_once __DIR__ . '/instance.php';

	// register_activation_hook( IK_PLUGIN_ENTRYPOINT, array( Utils::class, 'install' ) );
} else {
	add_action( 'admin_notices', __NAMESPACE__ . '\php_version_error' );
}

/**
 * Admin notice for incompatible PHP version
 */
function php_version_error() {
	printf( '<div class="error"><p>%s</p></div>', esc_html( php_version_text() ) );
}

/**
 * String describing the minimum PHP version.
 *
 * @return string
 */
function php_version_text() {
	return __( 'ImageKit plugin error: Your version of PHP is too old to run this plugin. You must be running PHP 5.6 or higher', 'imagekit' );
}
