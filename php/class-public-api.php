<?php
/**
 * Public API for theme and plugin developers.
 *
 * Provides stable template tags and helper functions for generating
 * ImageKit URLs, responsive <img> tags, and <picture> elements.
 *
 * @package ImageKitWordpress
 * @since   5.1.0
 */

namespace ImageKitWordpress;

class Public_API {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Cached URL endpoint (no trailing slash).
	 *
	 * @var string
	 */
	protected $url_endpoint = '';

	/**
	 * Cached ImageKit folder path.
	 *
	 * @var string
	 */
	protected $imagekit_folder = '';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin The plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		add_action( 'imagekit_ready', array( $this, 'init' ) );
	}

	/**
	 * Initialise cached values once the plugin is ready.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function init( $plugin ) {
		$url_endpoint = $this->plugin->settings ? $this->plugin->settings->get_value( 'credentials.url_endpoint' ) : '';
		$this->url_endpoint = is_string( $url_endpoint ) ? rtrim( trim( $url_endpoint ), '/' ) : '';

		$folder = $this->plugin->settings ? $this->plugin->settings->get_value( 'upload.imagekit_folder' ) : '';
		if ( ! is_string( $folder ) || '' === trim( $folder ) ) {
			// Fall back to the media component's cached value.
			$media = $this->plugin->get_component( 'media' );
			if ( $media && isset( $media->imagekit_folder ) ) {
				$folder = (string) $media->imagekit_folder;
			}
		}
		$this->imagekit_folder = is_string( $folder ) ? trim( $folder, '/' ) : '';
	}

	/**
	 * Get the configured ImageKit URL endpoint.
	 *
	 * @return string The URL endpoint or empty string if not configured.
	 */
	public function get_url_endpoint() {
		return $this->url_endpoint;
	}

	/**
	 * Build an ImageKit URL for a given attachment or path.
	 *
	 * @param int|string $source     Attachment ID or a relative/absolute file path.
	 * @param array      $transforms Array of ImageKit transform strings, e.g. ['w-400', 'h-300', 'fo-auto'].
	 *
	 * @return string The ImageKit URL, or empty string on failure.
	 */
	public function url( $source, $transforms = array() ) {
		if ( '' === $this->url_endpoint ) {
			return is_int( $source ) ? (string) wp_get_attachment_url( $source ) : (string) $source;
		}

		$path = '';
		if ( is_int( $source ) || ( is_string( $source ) && ctype_digit( $source ) ) ) {
			$path = $this->get_imagekit_path_for_attachment( (int) $source );
		} else {
			$path = $this->normalise_path( (string) $source );
		}

		if ( '' === $path ) {
			return '';
		}

		$url = $this->url_endpoint . '/' . ltrim( $path, '/' );

		return $this->append_transforms( $url, $transforms );
	}

	/**
	 * Build a responsive <img> tag with srcset and sizes.
	 *
	 * @param int   $attachment_id WordPress attachment ID.
	 * @param array $options {
	 *     Optional. Configuration options.
	 *
	 *     @type array  $transforms  Global transforms applied to every source.
	 *     @type array  $sizes       Array of [ 'viewport' => '100vw', 'media' => '(max-width: 640px)' ] entries.
	 *                               If empty, generates a default sizes attribute.
	 *     @type array  $widths      Explicit list of widths for srcset, e.g. [320, 640, 1024, 1920].
	 *                               If empty, auto-generates from plugin responsive settings.
	 *     @type string $class       CSS class(es) for the img tag.
	 *     @type string $alt         Alt text. Falls back to attachment alt meta.
	 *     @type string $loading     Loading attribute: 'lazy' (default), 'eager', or 'auto'.
	 * }
	 *
	 * @return string HTML <img> tag, or empty string on failure.
	 */
	public function responsive_image( $attachment_id, $options = array() ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 || '' === $this->url_endpoint ) {
			return '';
		}

		$image_meta = $this->get_image_dimensions( $attachment_id );
		if ( empty( $image_meta['width'] ) || empty( $image_meta['height'] ) ) {
			return '';
		}

		$full_w     = (int) $image_meta['width'];
		$full_h     = (int) $image_meta['height'];
		$base_path  = $this->get_imagekit_path_for_attachment( $attachment_id );
		if ( '' === $base_path ) {
			return '';
		}
		$base_url   = $this->url_endpoint . '/' . ltrim( $base_path, '/' );
		$transforms = isset( $options['transforms'] ) && is_array( $options['transforms'] ) ? $options['transforms'] : array();
		$widths     = isset( $options['widths'] ) && is_array( $options['widths'] ) ? $options['widths'] : array();
		$loading    = isset( $options['loading'] ) ? (string) $options['loading'] : 'lazy';
		$class      = isset( $options['class'] ) ? (string) $options['class'] : '';
		$alt        = isset( $options['alt'] ) ? (string) $options['alt'] : (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		if ( empty( $widths ) ) {
			$widths = $this->get_auto_widths( $full_w );
		}

		// Build srcset.
		$srcset_parts = array();
		foreach ( $widths as $w ) {
			$w = (int) $w;
			if ( $w <= 0 || $w > $full_w ) {
				continue;
			}
			$h      = (int) round( $full_h * ( $w / $full_w ) );
			$tr     = array_merge( $transforms, array( 'w-' . $w, 'h-' . $h ) );
			$src    = $this->append_transforms( $base_url, $tr );
			$srcset_parts[] = esc_url( $src ) . ' ' . $w . 'w';
		}
		// Add full-size.
		$full_tr  = array_merge( $transforms, array( 'w-' . $full_w, 'h-' . $full_h ) );
		$full_src = $this->append_transforms( $base_url, $full_tr );
		$srcset_parts[] = esc_url( $full_src ) . ' ' . $full_w . 'w';
		$srcset_parts   = array_unique( $srcset_parts );

		// Build sizes attribute.
		$sizes_attr = $this->build_sizes_attr( $options, $full_w );

		// Default src (largest).
		$default_src = $this->append_transforms( $base_url, $transforms );

		$attrs  = '';
		$attrs .= ' src="' . esc_url( $default_src ) . '"';
		$attrs .= ' srcset="' . esc_attr( implode( ', ', $srcset_parts ) ) . '"';
		$attrs .= ' sizes="' . esc_attr( $sizes_attr ) . '"';
		$attrs .= ' width="' . esc_attr( (string) $full_w ) . '"';
		$attrs .= ' height="' . esc_attr( (string) $full_h ) . '"';
		$attrs .= ' alt="' . esc_attr( $alt ) . '"';
		if ( '' !== $loading ) {
			$attrs .= ' loading="' . esc_attr( $loading ) . '"';
		}
		if ( '' !== $class ) {
			$attrs .= ' class="' . esc_attr( $class ) . '"';
		}

		return '<img' . $attrs . ' />';
	}

	/**
	 * Build a <picture> element with per-breakpoint sources.
	 *
	 * Each breakpoint can specify its own aspect ratio, crop mode, and transforms,
	 * allowing different crops for different screen sizes.
	 *
	 * @param int   $attachment_id WordPress attachment ID.
	 * @param array $breakpoints {
	 *     Array of breakpoint definitions. Each entry is an associative array:
	 *
	 *     @type string $media      Media query, e.g. '(max-width: 640px)'. Omit for the fallback <img>.
	 *     @type int    $width      Target width for this breakpoint.
	 *     @type int    $height     Target height (optional; computed from ratio if omitted).
	 *     @type string $ratio      Aspect ratio, e.g. '1:1', '16:9', '4:3'. Overrides height.
	 *     @type string $crop       ImageKit crop mode, e.g. 'maintain_ratio', 'force', 'at_max'. Default: 'maintain_ratio'.
	 *     @type array  $transforms Additional transforms for this breakpoint.
	 * }
	 * @param array $options {
	 *     Optional global options.
	 *
	 *     @type array  $transforms Global transforms applied to all sources.
	 *     @type string $class      CSS class for the fallback <img>.
	 *     @type string $alt        Alt text.
	 *     @type string $loading    'lazy' (default), 'eager', or 'auto'.
	 * }
	 *
	 * @return string HTML <picture> element, or empty string on failure.
	 */
	public function picture( $attachment_id, $breakpoints = array(), $options = array() ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 || '' === $this->url_endpoint ) {
			return '';
		}

		$image_meta = $this->get_image_dimensions( $attachment_id );
		if ( empty( $image_meta['width'] ) || empty( $image_meta['height'] ) ) {
			return '';
		}

		$full_w     = (int) $image_meta['width'];
		$full_h     = (int) $image_meta['height'];
		$base_path  = $this->get_imagekit_path_for_attachment( $attachment_id );
		if ( '' === $base_path ) {
			return '';
		}
		$base_url        = $this->url_endpoint . '/' . ltrim( $base_path, '/' );
		$global_tr       = isset( $options['transforms'] ) && is_array( $options['transforms'] ) ? $options['transforms'] : array();
		$class           = isset( $options['class'] ) ? (string) $options['class'] : '';
		$alt             = isset( $options['alt'] ) ? (string) $options['alt'] : (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$loading         = isset( $options['loading'] ) ? (string) $options['loading'] : 'lazy';

		if ( empty( $breakpoints ) ) {
			// Fallback: return a simple responsive img.
			return $this->responsive_image( $attachment_id, $options );
		}

		$sources  = array();
		$fallback = null;

		foreach ( $breakpoints as $bp ) {
			$media  = isset( $bp['media'] ) ? (string) $bp['media'] : '';
			$width  = isset( $bp['width'] ) ? (int) $bp['width'] : $full_w;
			$height = isset( $bp['height'] ) ? (int) $bp['height'] : 0;
			$ratio  = isset( $bp['ratio'] ) ? (string) $bp['ratio'] : '';
			$crop   = isset( $bp['crop'] ) ? (string) $bp['crop'] : 'maintain_ratio';
			$bp_tr  = isset( $bp['transforms'] ) && is_array( $bp['transforms'] ) ? $bp['transforms'] : array();

			// Compute height from ratio if provided.
			if ( '' !== $ratio && $width > 0 ) {
				$height = $this->height_from_ratio( $width, $ratio );
			}
			if ( $height <= 0 && $width > 0 ) {
				$height = (int) round( $full_h * ( $width / $full_w ) );
			}

			$tr = array_merge( $global_tr, $bp_tr );
			$tr[] = 'w-' . $width;
			$tr[] = 'h-' . $height;
			if ( '' !== $crop && 'maintain_ratio' !== $crop ) {
				$tr[] = 'c-' . $crop;
			}
			if ( '' !== $ratio ) {
				$tr[] = 'ar-' . str_replace( ':', '-', $ratio );
			}

			$src = $this->append_transforms( $base_url, $tr );

			if ( '' === $media ) {
				$fallback = array(
					'src'    => $src,
					'width'  => $width,
					'height' => $height,
				);
			} else {
				$sources[] = array(
					'media'  => $media,
					'srcset' => $src,
					'width'  => $width,
					'height' => $height,
				);
			}
		}

		// If no explicit fallback, use full-size.
		if ( null === $fallback ) {
			$fallback = array(
				'src'    => $this->append_transforms( $base_url, $global_tr ),
				'width'  => $full_w,
				'height' => $full_h,
			);
		}

		// Build HTML.
		$html = '<picture>';
		foreach ( $sources as $source ) {
			$html .= sprintf(
				'<source media="%s" srcset="%s" width="%d" height="%d" />',
				esc_attr( $source['media'] ),
				esc_url( $source['srcset'] ),
				$source['width'],
				$source['height']
			);
		}

		$img_attrs  = '';
		$img_attrs .= ' src="' . esc_url( $fallback['src'] ) . '"';
		$img_attrs .= ' width="' . esc_attr( (string) $fallback['width'] ) . '"';
		$img_attrs .= ' height="' . esc_attr( (string) $fallback['height'] ) . '"';
		$img_attrs .= ' alt="' . esc_attr( $alt ) . '"';
		if ( '' !== $loading ) {
			$img_attrs .= ' loading="' . esc_attr( $loading ) . '"';
		}
		if ( '' !== $class ) {
			$img_attrs .= ' class="' . esc_attr( $class ) . '"';
		}
		$html .= '<img' . $img_attrs . ' />';
		$html .= '</picture>';

		return $html;
	}

	/**
	 * Build an optimised <video> tag with ImageKit transforms.
	 *
	 * @param int|string $source     Attachment ID or video file path.
	 * @param array      $options {
	 *     Optional configuration.
	 *
	 *     @type array  $transforms  ImageKit video transforms, e.g. ['w-1280', 'h-720', 'q-80'].
	 *     @type string $poster      Poster image attachment ID or URL. If int, generates an IK URL.
	 *     @type array  $poster_transforms Transforms for the poster image.
	 *     @type bool   $autoplay    Default false.
	 *     @type bool   $muted       Default false.
	 *     @type bool   $loop        Default false.
	 *     @type bool   $controls    Default true.
	 *     @type bool   $playsinline Default true.
	 *     @type string $preload     'auto', 'metadata' (default), or 'none'.
	 *     @type string $class       CSS class(es).
	 *     @type string $width       Width attribute.
	 *     @type string $height      Height attribute.
	 * }
	 *
	 * @return string HTML <video> tag or empty string.
	 */
	public function video( $source, $options = array() ) {
		if ( '' === $this->url_endpoint ) {
			return '';
		}

		$transforms = isset( $options['transforms'] ) && is_array( $options['transforms'] ) ? $options['transforms'] : array();

		// Apply global video transforms from settings.
		$global_video_tr = $this->get_global_video_transforms();
		$transforms      = array_merge( $global_video_tr, $transforms );

		$video_url = $this->url( $source, $transforms );
		if ( '' === $video_url ) {
			return '';
		}

		$autoplay    = ! empty( $options['autoplay'] );
		$muted       = ! empty( $options['muted'] );
		$loop        = ! empty( $options['loop'] );
		$controls    = isset( $options['controls'] ) ? (bool) $options['controls'] : true;
		$playsinline = isset( $options['playsinline'] ) ? (bool) $options['playsinline'] : true;
		$preload     = isset( $options['preload'] ) ? (string) $options['preload'] : 'metadata';
		$class       = isset( $options['class'] ) ? (string) $options['class'] : '';
		$width       = isset( $options['width'] ) ? (string) $options['width'] : '';
		$height      = isset( $options['height'] ) ? (string) $options['height'] : '';

		// Poster.
		$poster_url = '';
		if ( isset( $options['poster'] ) ) {
			$poster          = $options['poster'];
			$poster_tr       = isset( $options['poster_transforms'] ) && is_array( $options['poster_transforms'] ) ? $options['poster_transforms'] : array();
			if ( is_int( $poster ) || ( is_string( $poster ) && ctype_digit( $poster ) ) ) {
				$poster_url = $this->url( (int) $poster, $poster_tr );
			} elseif ( is_string( $poster ) && '' !== $poster ) {
				$poster_url = $poster;
			}
		}

		$attrs = '';
		if ( '' !== $poster_url ) {
			$attrs .= ' poster="' . esc_url( $poster_url ) . '"';
		}
		if ( $controls ) {
			$attrs .= ' controls';
		}
		if ( $autoplay ) {
			$attrs .= ' autoplay';
		}
		if ( $muted ) {
			$attrs .= ' muted';
		}
		if ( $loop ) {
			$attrs .= ' loop';
		}
		if ( $playsinline ) {
			$attrs .= ' playsinline';
		}
		$attrs .= ' preload="' . esc_attr( $preload ) . '"';
		if ( '' !== $class ) {
			$attrs .= ' class="' . esc_attr( $class ) . '"';
		}
		if ( '' !== $width ) {
			$attrs .= ' width="' . esc_attr( $width ) . '"';
		}
		if ( '' !== $height ) {
			$attrs .= ' height="' . esc_attr( $height ) . '"';
		}

		return '<video' . $attrs . '><source src="' . esc_url( $video_url ) . '" type="video/mp4" /></video>';
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Get the ImageKit-relative path for an attachment.
	 *
	 * Checks _imagekit meta first (offloaded files), then falls back to
	 * the local uploads-relative path prefixed with the IK folder.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return string Relative path suitable for appending to the URL endpoint.
	 */
	protected function get_imagekit_path_for_attachment( $attachment_id ) {
		// Try stored ImageKit URL first.
		$ik_meta = get_post_meta( $attachment_id, '_imagekit', true );
		if ( is_array( $ik_meta ) ) {
			// If we have a full IK URL, extract just the path component after the endpoint.
			if ( ! empty( $ik_meta['url'] ) && is_string( $ik_meta['url'] ) ) {
				$ik_url = $ik_meta['url'];
				$ep     = $this->url_endpoint . '/';
				if ( 0 === strpos( $ik_url, $ep ) ) {
					return substr( $ik_url, strlen( $ep ) );
				}
				$path = (string) wp_parse_url( $ik_url, PHP_URL_PATH );
				return ltrim( $path, '/' );
			}
			if ( ! empty( $ik_meta['file_path'] ) && is_string( $ik_meta['file_path'] ) ) {
				return ltrim( $ik_meta['file_path'], '/' );
			}
		}

		// Fall back to local file → IK folder + uploads-relative path.
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $meta ) || empty( $meta['file'] ) ) {
			$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
			if ( ! is_string( $attached_file ) || '' === $attached_file ) {
				return '';
			}
			$relative = ltrim( $attached_file, '/' );
		} else {
			$relative = $meta['file'];
		}

		$folder = $this->imagekit_folder;
		if ( '' !== $folder ) {
			return $folder . '/' . ltrim( $relative, '/' );
		}

		return ltrim( $relative, '/' );
	}

	/**
	 * Normalise a path string for use in an ImageKit URL.
	 *
	 * Strips the local uploads base URL if present and prepends the IK folder.
	 *
	 * @param string $path Relative path or full local URL.
	 *
	 * @return string
	 */
	protected function normalise_path( $path ) {
		// Strip local uploads base URL.
		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['baseurl'] ) && 0 === strpos( $path, $uploads['baseurl'] ) ) {
			$path = ltrim( substr( $path, strlen( $uploads['baseurl'] ) ), '/' );
		}

		// Strip uploads marker.
		$marker = 'wp-content/uploads/';
		$pos    = strpos( $path, $marker );
		if ( false !== $pos ) {
			$path = substr( $path, $pos + strlen( $marker ) );
		}

		$path = ltrim( $path, '/' );
		if ( '' === $path ) {
			return '';
		}

		$folder = $this->imagekit_folder;
		if ( '' !== $folder ) {
			return $folder . '/' . $path;
		}

		return $path;
	}

	/**
	 * Append ImageKit transforms to a URL as a `tr` query parameter.
	 *
	 * @param string $url        Base URL.
	 * @param array  $transforms Array of transform strings.
	 *
	 * @return string
	 */
	protected function append_transforms( $url, $transforms ) {
		$transforms = is_array( $transforms ) ? array_filter( array_map( 'trim', $transforms ) ) : array();
		if ( empty( $transforms ) ) {
			return $url;
		}

		// Merge with any existing tr param.
		$existing_q = wp_parse_url( $url, PHP_URL_QUERY );
		if ( is_string( $existing_q ) && '' !== $existing_q ) {
			parse_str( $existing_q, $args );
			if ( ! empty( $args['tr'] ) && is_string( $args['tr'] ) ) {
				$existing = array_filter( array_map( 'trim', explode( ',', $args['tr'] ) ) );
				$transforms = array_merge( $existing, $transforms );
			}
		}

		$transforms = array_values( array_unique( $transforms ) );

		return add_query_arg( 'tr', implode( ',', $transforms ), $url );
	}

	/**
	 * Get image dimensions from attachment metadata or _imagekit meta.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array{width: int, height: int}
	 */
	protected function get_image_dimensions( $attachment_id ) {
		$meta = wp_get_attachment_metadata( $attachment_id );
		$w    = is_array( $meta ) && ! empty( $meta['width'] ) ? (int) $meta['width'] : 0;
		$h    = is_array( $meta ) && ! empty( $meta['height'] ) ? (int) $meta['height'] : 0;

		if ( $w <= 0 || $h <= 0 ) {
			$ik_meta = get_post_meta( $attachment_id, '_imagekit', true );
			if ( is_array( $ik_meta ) ) {
				if ( $w <= 0 && ! empty( $ik_meta['width'] ) && is_numeric( $ik_meta['width'] ) ) {
					$w = (int) $ik_meta['width'];
				}
				if ( $h <= 0 && ! empty( $ik_meta['height'] ) && is_numeric( $ik_meta['height'] ) ) {
					$h = (int) $ik_meta['height'];
				}
			}
		}

		return array( 'width' => $w, 'height' => $h );
	}

	/**
	 * Compute height from an aspect ratio string and width.
	 *
	 * @param int    $width Width.
	 * @param string $ratio Ratio string, e.g. '16:9', '4:3', '1:1'.
	 *
	 * @return int
	 */
	protected function height_from_ratio( $width, $ratio ) {
		$parts = explode( ':', $ratio );
		if ( 2 !== count( $parts ) ) {
			return 0;
		}
		$rw = (float) trim( $parts[0] );
		$rh = (float) trim( $parts[1] );
		if ( $rw <= 0 ) {
			return 0;
		}
		return (int) round( $width * ( $rh / $rw ) );
	}

	/**
	 * Auto-generate srcset widths from plugin responsive settings.
	 *
	 * @param int $full_width The full image width.
	 *
	 * @return int[]
	 */
	protected function get_auto_widths( $full_width ) {
		$config = $this->plugin->settings ? $this->plugin->settings->get_value( 'media_display' ) : array();
		if ( ! is_array( $config ) ) {
			$config = array();
		}

		$pixel_step = ! empty( $config['pixel_step'] ) && is_numeric( $config['pixel_step'] ) ? (int) $config['pixel_step'] : 150;
		$max_images = ! empty( $config['breakpoints'] ) && is_numeric( $config['breakpoints'] ) ? (int) $config['breakpoints'] : 15;
		$max_width  = ! empty( $config['max_width'] ) && is_numeric( $config['max_width'] ) ? (int) $config['max_width'] : 3840;
		$min_width  = isset( $config['min_width'] ) && is_numeric( $config['min_width'] ) ? (int) $config['min_width'] : 32;

		$pixel_step = max( 50, $pixel_step );
		$max_width  = min( $max_width, $full_width );
		$min_width  = max( 1, $min_width );

		$widths = array();
		for ( $w = $min_width; $w <= $max_width; $w += $pixel_step ) {
			$widths[] = $w;
			if ( count( $widths ) >= $max_images ) {
				break;
			}
		}

		return $widths;
	}

	/**
	 * Build a sizes attribute from options.
	 *
	 * Supports both pixel-based and percentage-based size definitions.
	 *
	 * @param array $options    Options array potentially containing 'sizes'.
	 * @param int   $full_width Full image width for the default entry.
	 *
	 * @return string
	 */
	protected function build_sizes_attr( $options, $full_width ) {
		if ( ! empty( $options['sizes'] ) && is_array( $options['sizes'] ) ) {
			$parts = array();
			foreach ( $options['sizes'] as $size ) {
				if ( ! is_array( $size ) ) {
					continue;
				}
				$viewport = isset( $size['viewport'] ) ? (string) $size['viewport'] : '100vw';
				if ( ! empty( $size['media'] ) ) {
					$parts[] = (string) $size['media'] . ' ' . $viewport;
				} else {
					$parts[] = $viewport;
				}
			}
			if ( ! empty( $parts ) ) {
				return implode( ', ', $parts );
			}
		}

		// Check for plugin-level size presets.
		$config = $this->plugin->settings ? $this->plugin->settings->get_value( 'media_display' ) : array();
		if ( is_array( $config ) && ! empty( $config['sizes_mode'] ) && 'viewport' === $config['sizes_mode'] ) {
			return $this->build_viewport_sizes( $config, $full_width );
		}

		return sprintf( '(max-width: %1$dpx) 100vw, %1$dpx', $full_width );
	}

	/**
	 * Build viewport-percentage based sizes attribute from settings.
	 *
	 * @param array $config     The media_display config.
	 * @param int   $full_width Full image width.
	 *
	 * @return string
	 */
	protected function build_viewport_sizes( $config, $full_width ) {
		$presets = array(
			array( 'key' => 'size_s', 'media' => '(max-width: 640px)',  'default' => '100vw' ),
			array( 'key' => 'size_m', 'media' => '(max-width: 1024px)', 'default' => '75vw' ),
			array( 'key' => 'size_l', 'media' => '(max-width: 1440px)', 'default' => '50vw' ),
		);

		$parts = array();
		foreach ( $presets as $preset ) {
			$value = ! empty( $config[ $preset['key'] ] ) ? (string) $config[ $preset['key'] ] : $preset['default'];
			$parts[] = $preset['media'] . ' ' . $value;
		}

		$xl = ! empty( $config['size_xl'] ) ? (string) $config['size_xl'] : $full_width . 'px';
		$parts[] = $xl;

		return implode( ', ', $parts );
	}

	/**
	 * Get global video transforms from plugin settings.
	 *
	 * @return array
	 */
	protected function get_global_video_transforms() {
		$config = $this->plugin->settings ? $this->plugin->settings->get_value( 'media_display' ) : null;
		if ( ! is_array( $config ) ) {
			return array();
		}

		$transforms = array();

		// Freeform global video transforms.
		if ( ! empty( $config['video_freeform'] ) && is_string( $config['video_freeform'] ) ) {
			$parts = array_filter( array_map( 'trim', explode( ',', $config['video_freeform'] ) ) );
			$transforms = array_merge( $transforms, $parts );
		}

		// Structured video settings.
		if ( ! empty( $config['video_quality'] ) && is_numeric( $config['video_quality'] ) ) {
			$transforms[] = 'q-' . (int) $config['video_quality'];
		}
		if ( ! empty( $config['video_format'] ) && is_string( $config['video_format'] ) && '' !== $config['video_format'] ) {
			$transforms[] = 'f-' . $config['video_format'];
		}

		return $transforms;
	}
}
