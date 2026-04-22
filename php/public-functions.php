<?php
/**
 * Public template tag functions for theme and plugin developers.
 *
 * These are the stable, documented entry points for external code.
 * They delegate to the Public_API class and are safe to call at any time
 * (they return sensible defaults when the plugin is not yet initialised).
 *
 * @package ImageKitWordpress
 * @since   5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the ImageKit URL endpoint configured in the plugin settings.
 *
 * Example:
 *   $endpoint = imagekit_get_url_endpoint();
 *   // => 'https://ik.imagekit.io/your_id'
 *
 * @return string The URL endpoint or empty string if not configured.
 */
function imagekit_get_url_endpoint() {
	$api = _imagekit_get_public_api();
	return $api ? $api->get_url_endpoint() : '';
}

/**
 * Build an ImageKit URL for an attachment or file path.
 *
 * Example:
 *   // From attachment ID:
 *   echo imagekit_url( 123, [ 'w-800', 'h-600', 'fo-auto' ] );
 *
 *   // From path:
 *   echo imagekit_url( '2024/01/photo.jpg', [ 'w-400' ] );
 *
 * @param int|string $source     Attachment ID or file path.
 * @param array      $transforms ImageKit transform strings.
 *
 * @return string ImageKit URL or empty string.
 */
function imagekit_url( $source, $transforms = array() ) {
	$api = _imagekit_get_public_api();
	return $api ? $api->url( $source, $transforms ) : '';
}

/**
 * Generate a responsive <img> tag with srcset and sizes via ImageKit.
 *
 * Example:
 *   echo imagekit_responsive_image( 123, [
 *       'widths'     => [ 320, 640, 1024, 1920 ],
 *       'transforms' => [ 'fo-auto', 'q-80' ],
 *       'class'      => 'hero-image',
 *       'sizes'      => [
 *           [ 'media' => '(max-width: 640px)', 'viewport' => '100vw' ],
 *           [ 'media' => '(max-width: 1024px)', 'viewport' => '75vw' ],
 *           [ 'viewport' => '50vw' ],
 *       ],
 *   ] );
 *
 * @param int   $attachment_id WordPress attachment ID.
 * @param array $options       See Public_API::responsive_image() for options.
 *
 * @return string HTML <img> tag or empty string.
 */
function imagekit_responsive_image( $attachment_id, $options = array() ) {
	$api = _imagekit_get_public_api();
	return $api ? $api->responsive_image( $attachment_id, $options ) : '';
}

/**
 * Generate a <picture> element with per-breakpoint art direction.
 *
 * Supports different aspect ratios, crop modes, and transforms per breakpoint,
 * enabling complex responsive layouts with ratio cropping.
 *
 * Example:
 *   echo imagekit_picture( 123, [
 *       [ 'media' => '(max-width: 640px)',  'width' => 640,  'ratio' => '1:1' ],
 *       [ 'media' => '(max-width: 1024px)', 'width' => 1024, 'ratio' => '4:3' ],
 *       [ 'width' => 1920, 'ratio' => '16:9' ],  // Fallback <img> (no media query).
 *   ], [
 *       'transforms' => [ 'fo-auto', 'q-80' ],
 *       'class'      => 'hero-picture',
 *       'alt'        => 'Hero banner',
 *   ] );
 *
 * @param int   $attachment_id WordPress attachment ID.
 * @param array $breakpoints   Array of breakpoint definitions.
 * @param array $options        Global options (transforms, class, alt, loading).
 *
 * @return string HTML <picture> element or empty string.
 */
function imagekit_picture( $attachment_id, $breakpoints = array(), $options = array() ) {
	$api = _imagekit_get_public_api();
	return $api ? $api->picture( $attachment_id, $breakpoints, $options ) : '';
}

/**
 * Generate an optimised <video> tag delivered through ImageKit.
 *
 * Example:
 *   echo imagekit_video( 456, [
 *       'transforms' => [ 'w-1280', 'q-80' ],
 *       'poster'     => 123,
 *       'autoplay'   => true,
 *       'muted'      => true,
 *       'loop'       => true,
 *   ] );
 *
 * @param int|string $source  Attachment ID or video file path.
 * @param array      $options See Public_API::video() for options.
 *
 * @return string HTML <video> tag or empty string.
 */
function imagekit_video( $source, $options = array() ) {
	$api = _imagekit_get_public_api();
	return $api ? $api->video( $source, $options ) : '';
}

/**
 * Internal: retrieve the Public_API singleton from the plugin instance.
 *
 * @access private
 * @return \ImageKitWordpress\Public_API|null
 */
function _imagekit_get_public_api() {
	if ( ! function_exists( 'ImageKitWordpress\get_plugin_instance' ) ) {
		return null;
	}
	$plugin = \ImageKitWordpress\get_plugin_instance();
	if ( ! $plugin || ! isset( $plugin->components['public_api'] ) ) {
		return null;
	}
	return $plugin->components['public_api'];
}
