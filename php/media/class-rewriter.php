<?php
/**
 * Media Rewriter.
 *
 * Rewrites WordPress uploads image URLs in rendered HTML (<img>, <source>)
 * to ImageKit URLs when Image delivery is enabled.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress\Media;

use ImageKitWordpress\Plugin;

class Rewriter {
	/**
	 * @var string
	 */
	protected $direction = 'to_ik';
	/**
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * @var \ImageKitWordpress\Settings
	 */
	protected $settings;

	/**
	 * @var string
	 */
	protected $base_url;

	/**
	 * @var string
	 */
	protected $imagekit_folder;

	public function __construct( Plugin $plugin, $settings, $base_url, $imagekit_folder ) {
		$this->plugin         = $plugin;
		$this->settings       = $settings;
		$this->base_url       = is_string( $base_url ) ? (string) $base_url : '';
		$this->imagekit_folder = is_string( $imagekit_folder ) ? (string) $imagekit_folder : '';
	}

	public function setup() {
		add_filter( 'the_content', array( $this, 'rewrite_html' ), 20 );
		add_filter( 'widget_text', array( $this, 'rewrite_html' ), 20 );
		add_filter( 'widget_text_content', array( $this, 'rewrite_html' ), 20 );
		add_filter( 'render_block', array( $this, 'rewrite_block' ), 20, 2 );
	}

	public function rewrite_block( $block_content, $block ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $this->rewrite_html( $block_content );
	}

	public function rewrite_html( $html ) {
		if ( ! $this->is_connected() ) {
			return $html;
		}
		$this->direction = $this->get_direction();
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		if ( false === strpos( $html, '<img' ) && false === strpos( $html, '<source' ) ) {
			return $html;
		}

		if ( class_exists( '\\DOMDocument' ) ) {
			$rewritten = $this->rewrite_with_dom( $html );
			if ( is_string( $rewritten ) && '' !== $rewritten ) {
				return $rewritten;
			}
		}

		return $this->rewrite_with_regex( $html );
	}

	protected function is_connected() {
		if ( empty( $this->plugin->settings ) || ! $this->plugin->settings->get_param( 'connected' ) ) {
			return false;
		}

		return true;
	}

	protected function get_direction() {
		if ( $this->is_image_delivery_enabled() ) {
			return 'to_ik';
		}
		return 'to_wp';
	}

	protected function is_image_delivery_enabled() {
		$config = $this->settings ? $this->settings->get_value( 'media_display' ) : null;
		if ( ! is_array( $config ) ) {
			return true;
		}
		return empty( $config['image_delivery'] ) || 'on' === $config['image_delivery'];
	}

	protected function get_offload_mode() {
		$offload = $this->settings ? $this->settings->get_value( 'offload' ) : null;
		$offload = is_string( $offload ) ? trim( $offload ) : '';
		// Back-compat with earlier label values used in UI tooltips.
		if ( 'both_full' === $offload ) {
			$offload = 'wp_high_ik_high';
		} elseif ( 'both_low' === $offload ) {
			$offload = 'wp_low_ik_high';
		} elseif ( 'ik' === $offload ) {
			$offload = 'wp_none_ik_high';
		}
		return $offload;
	}

	protected function rewrite_with_dom( $html ) {
		libxml_use_internal_errors( true );
		$doc = new \DOMDocument();
		// Load as fragment, avoid adding html/body.
		$loaded = $doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		if ( ! $loaded ) {
			libxml_clear_errors();
			return null;
		}

		$imgs = $doc->getElementsByTagName( 'img' );
		for ( $i = $imgs->length - 1; $i >= 0; $i-- ) {
			$img = $imgs->item( $i );
			if ( ! $img ) {
				continue;
			}
			$this->rewrite_element_attrs( $img, array( 'src', 'data-src' ), array( 'srcset', 'data-srcset' ) );
		}

		$sources = $doc->getElementsByTagName( 'source' );
		for ( $i = $sources->length - 1; $i >= 0; $i-- ) {
			$source = $sources->item( $i );
			if ( ! $source ) {
				continue;
			}
			$this->rewrite_element_attrs( $source, array(), array( 'srcset', 'data-srcset' ) );
		}

		$out = $doc->saveHTML();
		libxml_clear_errors();

		return is_string( $out ) ? $out : null;
	}

	protected function rewrite_element_attrs( $el, $url_attrs, $srcset_attrs ) {
		foreach ( $url_attrs as $attr ) {
			if ( ! $el->hasAttribute( $attr ) ) {
				continue;
			}
			$old = (string) $el->getAttribute( $attr );
			$new = $this->rewrite_url( $old );
			if ( is_string( $new ) && '' !== $new && $new !== $old ) {
				$el->setAttribute( $attr, $new );
			}
		}

		foreach ( $srcset_attrs as $attr ) {
			if ( ! $el->hasAttribute( $attr ) ) {
				continue;
			}
			$old = (string) $el->getAttribute( $attr );
			$new = $this->rewrite_srcset( $old );
			if ( is_string( $new ) && '' !== $new && $new !== $old ) {
				$el->setAttribute( $attr, $new );
			}
		}
	}

	protected function rewrite_with_regex( $html ) {
		$self = $this;
		$html = preg_replace_callback(
			'#<img\b[^>]*>#i',
			static function ( $m ) use ( $self ) {
				$tag = $m[0];
				$tag = $self->rewrite_tag_attr( $tag, 'src' );
				$tag = $self->rewrite_tag_attr( $tag, 'data-src' );
				$tag = $self->rewrite_tag_srcset_attr( $tag, 'srcset' );
				$tag = $self->rewrite_tag_srcset_attr( $tag, 'data-srcset' );
				return $tag;
			},
			$html
		);

		return preg_replace_callback(
			'#<source\b[^>]*>#i',
			static function ( $m ) use ( $self ) {
				$tag = $m[0];
				$tag = $self->rewrite_tag_srcset_attr( $tag, 'srcset' );
				$tag = $self->rewrite_tag_srcset_attr( $tag, 'data-srcset' );
				return $tag;
			},
			$html
		);
	}

	protected function rewrite_tag_attr( $tag, $attr ) {
		$pattern = '#\b' . preg_quote( $attr, '#' ) . '\s*=\s*(["\"])\s*([^"\"]+)\s*\1#i';
		return preg_replace_callback(
			$pattern,
			function ( $m ) use ( $attr ) {
				$q   = $m[1];
				$url = $m[2];
				$new = $this->rewrite_url( $url );
				if ( ! is_string( $new ) || '' === $new ) {
					return $m[0];
				}
				return $attr . '=' . $q . $new . $q;
			},
			$tag
		);
	}

	protected function rewrite_tag_srcset_attr( $tag, $attr ) {
		$pattern = '#\b' . preg_quote( $attr, '#' ) . '\s*=\s*(["\"])\s*([^"\"]+)\s*\1#i';
		return preg_replace_callback(
			$pattern,
			function ( $m ) use ( $attr ) {
				$q      = $m[1];
				$srcset = $m[2];
				$new    = $this->rewrite_srcset( $srcset );
				if ( ! is_string( $new ) || '' === $new ) {
					return $m[0];
				}
				return $attr . '=' . $q . $new . $q;
			},
			$tag
		);
	}

	protected function rewrite_srcset( $srcset ) {
		if ( ! is_string( $srcset ) || '' === $srcset ) {
			return $srcset;
		}
		$parts = array_map( 'trim', explode( ',', $srcset ) );
		$out   = array();
		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}
			$tokens = preg_split( '#\s+#', $part, 2 );
			if ( ! is_array( $tokens ) || empty( $tokens[0] ) ) {
				$out[] = $part;
				continue;
			}
			$url = $tokens[0];
			$desc = isset( $tokens[1] ) ? trim( $tokens[1] ) : '';
			$new = $this->rewrite_url( $url );
			if ( ! is_string( $new ) || '' === $new ) {
				$new = $url;
			}
			$out[] = '' !== $desc ? ( $new . ' ' . $desc ) : $new;
		}
		return implode( ', ', $out );
	}

	protected function rewrite_url( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return $url;
		}
		if ( 'to_wp' === $this->direction ) {
			return $this->rewrite_imagekit_url_to_wp_if_local( $url );
		}
		return $this->rewrite_wp_url_to_imagekit( $url );
	}

	protected function rewrite_wp_url_to_imagekit( $url ) {
		$base = trim( (string) $this->base_url );
		if ( '' === $base ) {
			return $url;
		}
		if ( $this->is_imagekit_url( $url ) ) {
			return $this->apply_transforms( $url, array() );
		}

		$relative = $this->get_uploads_relative_path_from_url( $url );
		if ( '' === $relative ) {
			return $url;
		}

		$extra_tr = array();
		$relative = $this->strip_wp_size_suffix( $relative, $extra_tr );

		$folder = trim( (string) $this->imagekit_folder );
		$folder = trim( $folder, '/' );
		$path   = '';
		if ( '' !== $folder ) {
			$path = $folder . '/' . ltrim( $relative, '/' );
		} else {
			$path = ltrim( $relative, '/' );
		}

		$new = rtrim( $base, '/' ) . '/' . ltrim( $path, '/' );
		return $this->apply_transforms( $new, $extra_tr );
	}

	protected function rewrite_imagekit_url_to_wp_if_local( $url ) {
		if ( ! $this->is_imagekit_url( $url ) ) {
			return $url;
		}
		$relative = $this->get_uploads_relative_path_from_imagekit_url( $url );
		if ( '' === $relative ) {
			return $url;
		}

		$uploads = wp_get_upload_dir();
		$basedir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
		$baseurl = isset( $uploads['baseurl'] ) ? (string) $uploads['baseurl'] : '';
		$basedir = rtrim( $basedir, '/' );
		$baseurl = rtrim( $baseurl, '/' );

		$relative_clean = ltrim( $relative, '/' );
		$local_file     = $basedir . '/' . $relative_clean;
		if ( '' !== $basedir && file_exists( $local_file ) ) {
			return $baseurl . '/' . $relative_clean;
		}

		// Try to map ImageKit resized variants to WP intermediate files.
		$w = null;
		$h = null;
		$q = wp_parse_url( $url, PHP_URL_QUERY );
		if ( is_string( $q ) && '' !== $q ) {
			parse_str( $q, $query_args );
			if ( is_array( $query_args ) && ! empty( $query_args['tr'] ) && is_string( $query_args['tr'] ) ) {
				$trs = array_map( 'trim', explode( ',', $query_args['tr'] ) );
				foreach ( $trs as $t ) {
					if ( preg_match( '/^w-([0-9]+)$/', $t, $m ) ) {
						$w = (int) $m[1];
					}
					if ( preg_match( '/^h-([0-9]+)$/', $t, $m ) ) {
						$h = (int) $m[1];
					}
				}
			}
		}
		if ( is_int( $w ) && $w > 0 && is_int( $h ) && $h > 0 ) {
			$dir      = dirname( $relative_clean );
			$filename = basename( $relative_clean );
			$ext      = pathinfo( $filename, PATHINFO_EXTENSION );
			$base     = $ext ? substr( $filename, 0, -1 * ( strlen( $ext ) + 1 ) ) : $filename;
			$sized    = $base . '-' . $w . 'x' . $h . ( $ext ? ( '.' . $ext ) : '' );
			$sized_rel = ( '.' === $dir ) ? $sized : ( rtrim( $dir, '/' ) . '/' . $sized );
			$sized_file = $basedir . '/' . ltrim( $sized_rel, '/' );
			if ( '' !== $basedir && file_exists( $sized_file ) ) {
				return $baseurl . '/' . ltrim( $sized_rel, '/' );
			}
		}

		return $url;
	}

	protected function strip_wp_size_suffix( $relative, &$extra_tr ) {
		$extra_tr = array();
		$relative = is_string( $relative ) ? $relative : '';
		if ( '' === $relative ) {
			return $relative;
		}
		$dir      = dirname( $relative );
		$filename = basename( $relative );
		$ext      = pathinfo( $filename, PATHINFO_EXTENSION );
		$name     = $ext ? substr( $filename, 0, -1 * ( strlen( $ext ) + 1 ) ) : $filename;
		if ( preg_match( '/^(.*)-([0-9]+)x([0-9]+)$/', $name, $m ) ) {
			$w        = (int) $m[2];
			$h        = (int) $m[3];
			$base     = (string) $m[1];
			$filename = $base . ( $ext ? ( '.' . $ext ) : '' );
			if ( $w > 0 ) {
				$extra_tr[] = 'w-' . $w;
			}
			if ( $h > 0 ) {
				$extra_tr[] = 'h-' . $h;
			}
		}
		if ( '.' === $dir ) {
			return $filename;
		}
		return rtrim( $dir, '/' ) . '/' . $filename;
	}

	protected function get_uploads_relative_path_from_imagekit_url( $url ) {
		$target_path = wp_parse_url( $url, PHP_URL_PATH );
		$target_path = is_string( $target_path ) ? $target_path : '';
		$base_path   = wp_parse_url( $this->base_url, PHP_URL_PATH );
		$base_path   = is_string( $base_path ) ? $base_path : '';
		if ( '' === $target_path || '' === $base_path ) {
			return '';
		}
		if ( 0 === strpos( $target_path, $base_path ) ) {
			$target_path = substr( $target_path, strlen( $base_path ) );
		}
		$target_path = ltrim( (string) $target_path, '/' );

		$folder = trim( (string) $this->imagekit_folder );
		$folder = trim( $folder, '/' );
		if ( '' !== $folder ) {
			$prefix = $folder . '/';
			if ( 0 === strpos( $target_path, $prefix ) ) {
				$target_path = substr( $target_path, strlen( $prefix ) );
			}
		}

		// Drop the filename if it doesn't look like an uploads path.
		return ltrim( (string) $target_path, '/' );
	}

	protected function is_imagekit_url( $url ) {
		if ( ! filter_var( utf8_uri_encode( $url ), FILTER_VALIDATE_URL ) ) {
			return false;
		}
		$test_parts = wp_parse_url( $url );
		$base_host  = wp_parse_url( $this->base_url, PHP_URL_HOST );

		return isset( $test_parts['host'] ) && is_string( $base_host ) && '' !== $base_host && false !== strpos( (string) $test_parts['host'], (string) $base_host );
	}

	protected function get_uploads_relative_path_from_url( $url ) {
		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['baseurl'] ) && 0 === strpos( $url, $uploads['baseurl'] ) ) {
			$relative = substr( $url, strlen( $uploads['baseurl'] ) );
			return ltrim( (string) $relative, '/' );
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		$path = is_string( $path ) ? ltrim( $path, '/' ) : '';
		if ( '' === $path ) {
			return '';
		}

		$uploads_marker = 'wp-content/uploads/';
		$pos            = strpos( $path, $uploads_marker );
		if ( false !== $pos ) {
			return substr( $path, $pos + strlen( $uploads_marker ) );
		}

		return '';
	}

	protected function apply_transforms( $url, $extra_transforms ) {
		$transforms = apply_filters( 'imagekit_default_global_transformations_image', array() );
		$transforms = is_array( $transforms ) ? array_map( 'trim', $transforms ) : array();
		$transforms = array_filter(
			$transforms,
			static function ( $t ) {
				return is_string( $t ) && '' !== $t;
			}
		);
		$extra_transforms = is_array( $extra_transforms ) ? array_map( 'trim', $extra_transforms ) : array();
		$extra_transforms = array_filter(
			$extra_transforms,
			static function ( $t ) {
				return is_string( $t ) && '' !== $t;
			}
		);
		$transforms = array_merge( $extra_transforms, $transforms );

		$existing_tr = '';
		$existing_q  = wp_parse_url( $url, PHP_URL_QUERY );
		if ( is_string( $existing_q ) && '' !== $existing_q ) {
			parse_str( $existing_q, $existing_query_args );
			if ( is_array( $existing_query_args ) && ! empty( $existing_query_args['tr'] ) && is_string( $existing_query_args['tr'] ) ) {
				$existing_tr = $existing_query_args['tr'];
			}
		}

		if ( '' !== $existing_tr ) {
			$existing_parts = array_map( 'trim', explode( ',', $existing_tr ) );
			$existing_parts = array_filter(
				$existing_parts,
				static function ( $t ) {
					return is_string( $t ) && '' !== $t;
				}
			);
			$transforms     = array_merge( $existing_parts, $transforms );
		}

		$transforms = array_values( array_unique( $transforms ) );
		if ( empty( $transforms ) ) {
			return $url;
		}
		return add_query_arg( 'tr', implode( ',', $transforms ), $url );
	}
}
