<?php
/**
 * Asset Rewriter.
 *
 * Rewrites enqueued WordPress asset URLs (CSS/JS) from theme, plugin,
 * and core paths to ImageKit URLs based on per-category delivery toggles.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress\Media;

use ImageKitWordpress\Delivery;
use ImageKitWordpress\Plugin;

class Asset_Rewriter {

	/**
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * @var Delivery
	 */
	protected $delivery;

	/**
	 * @var string ImageKit URL endpoint (e.g. https://ik.imagekit.io/your_id).
	 */
	protected $base_url;

	/**
	 * @var string The site URL used to identify local assets.
	 */
	protected $site_url;

	/**
	 * @var string Path component of the site URL for relative matching.
	 */
	protected $site_path;

	/**
	 * @param Plugin   $plugin   The plugin instance.
	 * @param Delivery $delivery The delivery component.
	 * @param string   $base_url The ImageKit URL endpoint.
	 */
	public function __construct( Plugin $plugin, Delivery $delivery, $base_url ) {
		$this->plugin   = $plugin;
		$this->delivery = $delivery;
		$this->base_url = is_string( $base_url ) ? rtrim( $base_url, '/' ) : '';
		$this->site_url = rtrim( site_url(), '/' );

		$parsed = wp_parse_url( $this->site_url, PHP_URL_PATH );
		$this->site_path = is_string( $parsed ) ? rtrim( $parsed, '/' ) : '';
	}

	/**
	 * Register WordPress hooks.
	 */
	public function setup() {
		if ( '' === $this->base_url ) {
			return;
		}
		if ( ! $this->delivery->has_any_asset_delivery_enabled() ) {
			return;
		}

		add_filter( 'style_loader_src', array( $this, 'rewrite_style_src' ), 20, 2 );
		add_filter( 'script_loader_src', array( $this, 'rewrite_script_src' ), 20, 2 );
	}

	/**
	 * Rewrite a stylesheet URL if its category is enabled.
	 *
	 * @param string $src    The source URL.
	 * @param string $handle The stylesheet handle.
	 *
	 * @return string
	 */
	public function rewrite_style_src( $src, $handle ) {
		if ( ! is_string( $src ) || '' === $src ) {
			return $src;
		}

		if ( apply_filters( 'imagekit_exclude_asset_url', false, $src, $handle, 'style' ) ) {
			return $src;
		}

		$category = $this->classify_url( $src );
		if ( '' === $category ) {
			return $src;
		}

		$css_key = $category . '_css';
		if ( ! $this->delivery->is_asset_delivery_enabled( $css_key ) ) {
			return $src;
		}

		return $this->rewrite_url( $src );
	}

	/**
	 * Rewrite a script URL if its category is enabled.
	 *
	 * @param string $src    The source URL.
	 * @param string $handle The script handle.
	 *
	 * @return string
	 */
	public function rewrite_script_src( $src, $handle ) {
		if ( ! is_string( $src ) || '' === $src ) {
			return $src;
		}

		if ( apply_filters( 'imagekit_exclude_asset_url', false, $src, $handle, 'script' ) ) {
			return $src;
		}

		$category = $this->classify_url( $src );
		if ( '' === $category ) {
			return $src;
		}

		$js_key = $category . '_js';
		if ( ! $this->delivery->is_asset_delivery_enabled( $js_key ) ) {
			return $src;
		}

		return $this->rewrite_url( $src );
	}

	/**
	 * Classify a URL into one of: 'theme', 'plugin', 'wp_core', or '' (unknown/external).
	 *
	 * @param string $url The asset URL.
	 *
	 * @return string
	 */
	protected function classify_url( $url ) {
		if ( ! $this->is_local_url( $url ) ) {
			return '';
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		$path = is_string( $path ) ? $path : '';

		// Strip the site sub-directory prefix if present.
		if ( '' !== $this->site_path && 0 === strpos( $path, $this->site_path ) ) {
			$path = substr( $path, strlen( $this->site_path ) );
		}

		if ( false !== strpos( $path, '/wp-content/themes/' ) ) {
			return 'theme';
		}
		if ( false !== strpos( $path, '/wp-content/plugins/' ) ) {
			return 'plugin';
		}
		if ( false !== strpos( $path, '/wp-includes/' ) || false !== strpos( $path, '/wp-admin/' ) ) {
			return 'wp_core';
		}

		return '';
	}

	/**
	 * Check if the URL belongs to the current site.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	protected function is_local_url( $url ) {
		// Protocol-relative or relative URLs are local.
		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			return true;
		}
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );
		$site_host = wp_parse_url( $this->site_url, PHP_URL_HOST );

		if ( ! is_string( $url_host ) || ! is_string( $site_host ) ) {
			return false;
		}

		return strtolower( $url_host ) === strtolower( $site_host );
	}

	/**
	 * Rewrite a local WordPress URL to an ImageKit proxy URL.
	 *
	 * Uses the ImageKit web-proxy pattern:
	 * {imagekit_base_url}/{site_origin_path}
	 *
	 * @param string $url The original local URL.
	 *
	 * @return string
	 */
	protected function rewrite_url( $url ) {
		$parsed = wp_parse_url( $url );
		if ( ! is_array( $parsed ) || empty( $parsed['path'] ) ) {
			return $url;
		}

		$path = $parsed['path'];

		// Strip site sub-directory prefix so the ImageKit path starts at the WP root.
		if ( '' !== $this->site_path && 0 === strpos( $path, $this->site_path ) ) {
			$path = substr( $path, strlen( $this->site_path ) );
		}

		$new_url = $this->base_url . $path;

		// Preserve query string (version params etc.).
		if ( ! empty( $parsed['query'] ) ) {
			$new_url .= '?' . $parsed['query'];
		}

		return $new_url;
	}
}
