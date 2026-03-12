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
	public function setup() {}

	public function rewrite_html( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		if ( '' === $this->base_url ) {
			return $html;
		}
		if ( ! $this->delivery->has_any_asset_delivery_enabled() ) {
			return $html;
		}
		if ( false === strpos( $html, '<script' ) && false === strpos( $html, '<link' ) ) {
			return $html;
		}
		$looks_like_full_document = ( false !== stripos( $html, '<!doctype' ) ) || ( false !== stripos( $html, '<html' ) );

		if ( ! $looks_like_full_document && class_exists( '\DOMDocument' ) ) {
			$rewritten = $this->rewrite_html_with_dom( $html );
			if ( is_string( $rewritten ) && '' !== $rewritten ) {
				return $rewritten;
			}
		}

		return $this->rewrite_html_with_regex( $html );
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
		if ( '' !== $path && '/' !== $path[0] ) {
			$path = '/' . $path;
		}

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
		// Relative URLs are local.
		if ( ! preg_match( '#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $url ) && 0 !== strpos( $url, '//' ) ) {
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
		if ( is_string( $path ) && '' !== $path && '/' !== $path[0] ) {
			$path = '/' . $path;
		}

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

	protected function rewrite_html_with_dom( $html ) {
		libxml_use_internal_errors( true );
		$doc    = new \DOMDocument();
		$loaded = $doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		if ( ! $loaded ) {
			libxml_clear_errors();
			return null;
		}

		$links = $doc->getElementsByTagName( 'link' );
		for ( $i = $links->length - 1; $i >= 0; $i-- ) {
			$link = $links->item( $i );
			if ( ! $link || ! $link->hasAttribute( 'href' ) ) {
				continue;
			}
			$rel = $link->hasAttribute( 'rel' ) ? strtolower( (string) $link->getAttribute( 'rel' ) ) : '';
			$as  = $link->hasAttribute( 'as' ) ? strtolower( (string) $link->getAttribute( 'as' ) ) : '';
			if ( false === strpos( $rel, 'stylesheet' ) && ! ( 'preload' === $rel && 'style' === $as ) ) {
				continue;
			}
			$old = (string) $link->getAttribute( 'href' );
			$new = $this->maybe_rewrite_asset_url( $old, 'style', '' );
			if ( is_string( $new ) && '' !== $new && $new !== $old ) {
				$link->setAttribute( 'href', $new );
			}
		}

		$scripts = $doc->getElementsByTagName( 'script' );
		for ( $i = $scripts->length - 1; $i >= 0; $i-- ) {
			$script = $scripts->item( $i );
			if ( ! $script || ! $script->hasAttribute( 'src' ) ) {
				continue;
			}
			$old = (string) $script->getAttribute( 'src' );
			$new = $this->maybe_rewrite_asset_url( $old, 'script', '' );
			if ( is_string( $new ) && '' !== $new && $new !== $old ) {
				$script->setAttribute( 'src', $new );
			}
		}

		$out = $doc->saveHTML();
		libxml_clear_errors();

		return is_string( $out ) ? $out : null;
	}

	protected function rewrite_html_with_regex( $html ) {
		$self = $this;
		$html = preg_replace_callback(
			'#<link\b[^>]*>#i',
			static function ( $m ) use ( $self ) {
				$tag = $m[0];
				if ( ! preg_match( '#\brel\s*=\s*(["\'])\s*([^"\']+)\s*\1#i', $tag, $relm ) ) {
					return $tag;
				}
				$rel = strtolower( trim( (string) $relm[2] ) );
				$as  = '';
				if ( preg_match( '#\bas\s*=\s*(["\'])\s*([^"\']+)\s*\1#i', $tag, $asm ) ) {
					$as = strtolower( trim( (string) $asm[2] ) );
				}
				if ( false === strpos( $rel, 'stylesheet' ) && ! ( 'preload' === $rel && 'style' === $as ) ) {
					return $tag;
				}
				return $self->rewrite_tag_attr( $tag, 'href', 'style' );
			},
			$html
		);

		return preg_replace_callback(
			'#<script\b[^>]*>#i',
			static function ( $m ) use ( $self ) {
				$tag = $m[0];
				return $self->rewrite_tag_attr( $tag, 'src', 'script' );
			},
			$html
		);
	}

	protected function rewrite_tag_attr( $tag, $attr, $type ) {
		$pattern = '#\b' . preg_quote( $attr, '#' ) . '\s*=\s*(["\'])\s*([^"\']+)\s*\1#i';
		return preg_replace_callback(
			$pattern,
			function ( $m ) use ( $attr, $type ) {
				$q   = $m[1];
				$url = $m[2];
				$new = $this->maybe_rewrite_asset_url( $url, $type, '' );
				if ( ! is_string( $new ) || '' === $new ) {
					return $m[0];
				}
				return $attr . '=' . $q . $new . $q;
			},
			$tag
		);
	}

	protected function maybe_rewrite_asset_url( $url, $type, $handle ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return $url;
		}
		if ( apply_filters( 'imagekit_exclude_asset_url', false, $url, $handle, $type ) ) {
			return $url;
		}
		$category = $this->classify_url( $url );
		if ( '' === $category ) {
			return $url;
		}
		$key = $category . ( 'style' === $type ? '_css' : '_js' );
		if ( ! $this->delivery->is_asset_delivery_enabled( $key ) ) {
			return $url;
		}
		return $this->rewrite_url( $url );
	}
}
