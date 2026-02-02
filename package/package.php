<?php
/**
 * Plugin Name: ImageKit Update Tester
 * Plugin URI:
 * Description: Test ImageKit Plugin Update Process for ImageKit STABLETAG. (This will deactivate itself, once activated.)
 * Version: 1.0
 * Author: ImageKit Developer
 * Author URI: https://imagekit.io
 * Text Domain: imagekit-update-tester
 * License: GPL2+
 *
 * @package ImageKitWordpress
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Alter the update plugins object.
 *
 * @param object $data plugin update data.
 *
 * @return object
 */
function ik_test_check_update( $data ) {
	$slug        = 'imagekit/imagekit.php';
	$file        = plugin_dir_path( __FILE__ ) . 'imagekit-wordpress-STABLETAG.zip';
	$version     = 'STABLETAG';
	$this_plugin = 'imagekit-update-tester-STABLETAG/imagekit-update-tester.php';
	if ( ! empty( $data->no_update ) ) {
		if ( ! empty( $data->no_update[ $slug ] ) ) {
			$data->no_update[ $slug ]->package     = $file;
			$data->no_update[ $slug ]->new_version = $version;
			$data->response[ $slug ]               = $data->no_update[ $slug ];
			unset( $data->no_update[ $slug ] );
			deactivate_plugins( $this_plugin );
		}
	}
	// Add if available.
	if ( ! empty( $data->response ) ) {
		$slug = 'imagekit/imagekit.php';
		if ( ! empty( $data->response[ $slug ] ) ) {
			$data->response[ $slug ]->package     = $file;
			$data->response[ $slug ]->new_version = $version;
			$data->response[ $slug ]              = $data->response[ $slug ];
			deactivate_plugins( $this_plugin );
		}
	}

	return $data;
}

add_filter( 'pre_set_site_transient_update_plugins', 'ik_test_check_update', 100 );

/**
 * Delete the update transient on activation.
 */
function ik_test_init_update() {
	delete_site_transient( 'update_plugins' );
}

register_activation_hook( __FILE__, 'ik_test_init_update' );
