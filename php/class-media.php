<?php

namespace ImageKitWordpress;

use ImageKitWordpress\Component\Setup;
use ImageKitWordpress\Media\Asset_Rewriter;
use ImageKitWordpress\Media\Rewriter;

class Media extends Settings_Component implements Setup {

	/**
	 * Holds the plugin instance.
	 *
	 * @since   5.0.0
	 *
	 * @var     Plugin Instance of the global plugin.
	 */
	public $plugin;

	public $base_url;

	public $imagekit_folder;

	public $rewriter;

	public $uploader;

	/**
	 * @var Delivery
	 */
	public $delivery;

	/**
	 * @var Asset_Rewriter
	 */
	public $asset_rewriter;

	/**
	 * In-memory map of local attachment URLs to their ImageKit URLs.
	 * Populated by WP hooks during rendering, used by the buffer rewriter.
	 *
	 * @var array<string, string>
	 */
	protected $url_map = array();

	/**
	 * @var string Cached site URL for local URL matching.
	 */
	protected $site_url = '';

	/**
	 * @var string Cached site URL host for local URL matching.
	 */
	protected $site_host = '';

	/**
	 * @var string Path component of the site URL (for subdirectory installs).
	 */
	protected $site_path = '';

	/**
	 * @var array|null Cached upload dir info.
	 */
	protected $upload_dir = null;

	// /**
	// * Credentials.
	// *
	// * @var array.
	// */
	// public $credentials;

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	public function setup() {
		$url_endpoint = $this->settings ? $this->settings->get_value( 'credentials.url_endpoint' ) : '';
		$url_endpoint = is_string( $url_endpoint ) ? trim( $url_endpoint ) : '';
		if ( '' === $url_endpoint ) {
			return;
		}

		$this->base_url        = rtrim( $url_endpoint, '/' );
		$this->imagekit_folder = $this->settings->get_value( 'imagekit_folder' );
		$this->delivery        = $this->plugin->get_component( 'delivery' );
		$this->rewriter        = new Rewriter( $this->plugin, $this->settings, $this->base_url, $this->imagekit_folder );
		$this->asset_rewriter  = new Asset_Rewriter( $this->plugin, $this->delivery, $this->base_url );

		// Cache site info for the buffer rewriter (avoids DB calls during rewrite).
		$this->site_url   = rtrim( site_url(), '/' );
		$this->site_host  = (string) wp_parse_url( $this->site_url, PHP_URL_HOST );
		$parsed_path      = wp_parse_url( $this->site_url, PHP_URL_PATH );
		$this->site_path  = is_string( $parsed_path ) ? rtrim( $parsed_path, '/' ) : '';
		$this->upload_dir = wp_get_upload_dir();

		add_action( 'template_redirect', array( $this, 'maybe_start_output_buffer_rewrite' ), 0 );

		// The rest of Media features require a full connection (public/private keys).
		if ( true !== $this->plugin->settings->get_param( 'connected' ) ) {
			return;
		}

		$this->credentials = $this->plugin->get_component( 'credentials_manager' )->get_credentials();
		$this->uploader    = $this->plugin->get_component( 'uploader' );

		add_action( 'print_media_templates', array( $this, 'media_template' ) );
		add_action( 'wp_enqueue_media', array( $this, 'editor_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'block_editor_assets' ) );
		add_action( 'wp_ajax_imagekit-down-sync', array( $this, 'down_sync_asset' ) );
		add_action( 'imagekit_download_asset', array( $this, 'maybe_copy_eml_asset_to_wordpress' ), 10, 2 );
		add_filter( 'imagekit_api_rest_endpoints', array( $this, 'rest_endpoints' ) );

		add_filter( 'wp_calculate_image_srcset', array( $this, 'image_srcset' ), 10, 5 );
		add_filter( 'wp_get_attachment_url', array( $this, 'attachment_url' ), 10, 2 );
		add_filter( 'wp_get_original_image_url', array( $this, 'original_attachment_url' ), 10, 2 );
		add_filter( 'image_downsize', array( $this, 'filter_downsize' ), 10, 3 );
		add_filter( 'wp_calculate_image_srcset_meta', array( $this, 'calculate_image_srcset_meta' ), 10, 4 );
		add_filter( 'wp_content_img_tag', array( $this, 'maybe_add_imagekit_srcset' ), 20, 3 );

		add_filter( 'imagekit_default_global_transformations_image', array( $this, 'default_image_global_transformations' ), 10 );
	}

	public function maybe_start_output_buffer_rewrite() {
		if ( ! $this->should_output_buffer_rewrite() ) {
			return;
		}
		ob_start( array( $this, 'rewrite_output_buffer' ) );
	}

	protected function should_output_buffer_rewrite() {
		if ( is_admin() ) {
			return false;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return false;
		}
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return false;
		}
		return true;
	}

	public function rewrite_output_buffer( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		if ( false === strpos( $html, '<' ) ) {
			return $html;
		}

		return $this->rewrite_all_local_urls( $html );
	}

	/**
	 * Rewrite all local WordPress URLs in the HTML to ImageKit URLs.
	 *
	 * Single comprehensive pass that covers every context:
	 * tag attributes, inline CSS url(), srcset, etc.
	 *
	 * @param string $html The full HTML output.
	 *
	 * @return string
	 */
	protected function rewrite_all_local_urls( $html ) {
		if ( '' === $this->base_url || '' === $this->site_host ) {
			return $html;
		}

		$extensions = $this->get_rewritable_extensions();
		if ( empty( $extensions ) ) {
			return $html;
		}

		$ext_pattern = implode( '|', array_map( 'preg_quote', $extensions ) );

		// Build regex for the site host (escaped for use in regex).
		$host_re = preg_quote( $this->site_host, '#' );

		// Step 1: Rewrite srcset/data-srcset attributes first (they have special comma-separated format).
		$html = $this->rewrite_srcset_attributes( $html, $ext_pattern );

		// Step 2: Rewrite absolute local URLs (https?://site_host/path.ext).
		$abs_pattern = '#(?<=["\'\s(,])(https?://' . $host_re . '(?::[0-9]+)?/[^\s"\'<>)?\#]+\.(?:' . $ext_pattern . ')(?:\?[^\s"\'<>)]*)?)(?=["\'\s)<>,])#i';

		$self = $this;
		$html = preg_replace_callback(
			$abs_pattern,
			static function ( $m ) use ( $self ) {
				return $self->maybe_rewrite_url( $m[1] );
			},
			$html
		);

		// Step 3: Rewrite root-relative local URLs (/wp-content/..., /wp-includes/...).
		$rel_prefixes = array( '/wp-content/', '/wp-includes/' );
		if ( '' !== $this->site_path ) {
			$rel_prefixes[] = $this->site_path . '/wp-content/';
			$rel_prefixes[] = $this->site_path . '/wp-includes/';
		}
		$rel_prefix_re = implode(
			'|',
			array_map(
				function ( $p ) {
					return preg_quote( $p, '#' );
				},
				$rel_prefixes
			)
		);

		$rel_pattern = '#(?<=["\'\s(,])(' . '(?:' . $rel_prefix_re . ')' . '[^\s"\'<>)?\#]+\.(?:' . $ext_pattern . ')(?:\?[^\s"\'<>)]*)?)(?=["\'\s)<>,])#i';

		$html = preg_replace_callback(
			$rel_pattern,
			static function ( $m ) use ( $self ) {
				$path = $m[1];
				return $self->maybe_rewrite_relative_url( $path );
			},
			$html
		);

		// Step 4: Rewrite CSS url() values inside <style> blocks and style attributes.
		$html = $this->rewrite_css_url_values( $html, $ext_pattern );

		return $html;
	}

	/**
	 * Get the list of file extensions eligible for rewriting.
	 *
	 * @return array
	 */
	protected function get_rewritable_extensions() {
		$defaults = array(
			// Images.
			'jpg',
			'jpeg',
			'png',
			'gif',
			'webp',
			'svg',
			'ico',
			'bmp',
			'avif',
			'tiff',
			// Fonts.
			'woff',
			'woff2',
			'ttf',
			'eot',
			'otf',
			// CSS / JS.
			'css',
			'js',
			// Media.
			'mp4',
			'webm',
			'ogg',
			'mp3',
			'wav',
			'flac',
			// Other.
			'pdf',
			'map',
		);

		$extensions = apply_filters( 'imagekit_rewritable_extensions', $defaults );

		return is_array( $extensions ) ? $extensions : $defaults;
	}

	/**
	 * Determine if a URL should be rewritten, and return the rewritten URL or original.
	 *
	 * @param string $url Absolute local URL.
	 *
	 * @return string
	 */
	public function maybe_rewrite_url( $url ) {
		// Skip if already an ImageKit URL.
		if ( $this->is_imagekit_url( $url ) ) {
			return $url;
		}

		if ( apply_filters( 'imagekit_exclude_url', false, $url ) ) {
			return $url;
		}

		// Check URL map first (synced uploads).
		if ( isset( $this->url_map[ $url ] ) ) {
			return $this->url_map[ $url ];
		}

		// Only rewrite local URLs.
		$url_host = wp_parse_url( $url, PHP_URL_HOST );
		if ( is_string( $url_host ) && strtolower( $url_host ) !== strtolower( $this->site_host ) ) {
			return $url;
		}

		// Strip query string for classification, but preserve it for the rewrite.
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( '' === $path ) {
			return $url;
		}

		// Check delivery toggles.
		if ( ! $this->is_path_delivery_enabled( $path ) ) {
			return $url;
		}

		return $this->proxy_url( $url );
	}

	/**
	 * Rewrite a root-relative path to an ImageKit proxy URL.
	 *
	 * @param string $path Root-relative path (e.g. /wp-content/themes/...).
	 *
	 * @return string
	 */
	public function maybe_rewrite_relative_url( $path ) {
		// Build absolute URL for filter/map checks.
		$abs_url = $this->site_url . $path;

		if ( apply_filters( 'imagekit_exclude_url', false, $abs_url ) ) {
			return $path;
		}

		if ( isset( $this->url_map[ $abs_url ] ) ) {
			return $this->url_map[ $abs_url ];
		}

		if ( ! $this->is_path_delivery_enabled( $path ) ) {
			return $path;
		}

		// Strip site subdirectory prefix if present.
		$clean_path = $path;
		if ( '' !== $this->site_path && 0 === strpos( $clean_path, $this->site_path ) ) {
			$clean_path = substr( $clean_path, strlen( $this->site_path ) );
		}

		return $this->base_url . $clean_path;
	}

	/**
	 * Proxy an absolute local URL through ImageKit.
	 *
	 * @param string $url The original absolute URL.
	 *
	 * @return string
	 */
	protected function proxy_url( $url ) {
		$parsed = wp_parse_url( $url );
		if ( ! is_array( $parsed ) || empty( $parsed['path'] ) ) {
			return $url;
		}

		$path = $parsed['path'];

		// Strip site subdirectory prefix.
		if ( '' !== $this->site_path && 0 === strpos( $path, $this->site_path ) ) {
			$path = substr( $path, strlen( $this->site_path ) );
		}

		$new_url = $this->base_url . $path;

		if ( ! empty( $parsed['query'] ) ) {
			$new_url .= '?' . $parsed['query'];
		}

		return $new_url;
	}

	/**
	 * Check if delivery is enabled for a given URL path based on its classification.
	 *
	 * @param string $path The URL path.
	 *
	 * @return bool
	 */
	protected function is_path_delivery_enabled( $path ) {
		// Normalize: strip site subdirectory prefix for classification.
		$clean = $path;
		if ( '' !== $this->site_path && 0 === strpos( $clean, $this->site_path ) ) {
			$clean = substr( $clean, strlen( $this->site_path ) );
		}

		$ext = strtolower( pathinfo( $clean, PATHINFO_EXTENSION ) );

		// Uploads (images/video/other media).
		if ( false !== strpos( $clean, '/wp-content/uploads/' ) ) {
			$image_exts = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp', 'avif', 'tiff' );
			$video_exts = array( 'mp4', 'webm', 'ogg' );
			if ( in_array( $ext, $image_exts, true ) ) {
				return $this->delivery->is_image_delivery_enabled();
			}
			if ( in_array( $ext, $video_exts, true ) ) {
				return $this->delivery->is_video_delivery_enabled();
			}
			// Other uploads (audio, PDF, etc.) — deliver if image delivery is on.
			return $this->delivery->is_image_delivery_enabled();
		}

		// Theme assets.
		if ( false !== strpos( $clean, '/wp-content/themes/' ) ) {
			if ( 'css' === $ext ) {
				return $this->delivery->is_asset_delivery_enabled( 'theme_css' );
			}
			if ( 'js' === $ext ) {
				return $this->delivery->is_asset_delivery_enabled( 'theme_js' );
			}
			// Fonts, images inside themes — deliver if theme CSS is on.
			return $this->delivery->is_asset_delivery_enabled( 'theme_css' );
		}

		// Plugin assets.
		if ( false !== strpos( $clean, '/wp-content/plugins/' ) ) {
			if ( 'css' === $ext ) {
				return $this->delivery->is_asset_delivery_enabled( 'plugin_css' );
			}
			if ( 'js' === $ext ) {
				return $this->delivery->is_asset_delivery_enabled( 'plugin_js' );
			}
			return $this->delivery->is_asset_delivery_enabled( 'plugin_css' );
		}

		// WP core assets.
		if ( false !== strpos( $clean, '/wp-includes/' ) || false !== strpos( $clean, '/wp-admin/' ) ) {
			if ( 'css' === $ext ) {
				return $this->delivery->is_asset_delivery_enabled( 'wp_core_css' );
			}
			if ( 'js' === $ext ) {
				return $this->delivery->is_asset_delivery_enabled( 'wp_core_js' );
			}
			return $this->delivery->is_asset_delivery_enabled( 'wp_core_css' );
		}

		// Unclassified paths (e.g. /wp-content/fonts/) — deliver if any asset delivery is on.
		return $this->delivery->has_any_asset_delivery_enabled();
	}

	/**
	 * Rewrite srcset and data-srcset attributes in the HTML.
	 *
	 * @param string $html         The HTML.
	 * @param string $ext_pattern  Regex alternation of allowed extensions.
	 *
	 * @return string
	 */
	protected function rewrite_srcset_attributes( $html, $ext_pattern ) {
		$self = $this;
		return preg_replace_callback(
			'#\b(srcset|data-srcset)\s*=\s*(["\'])([^"\']+)\2#i',
			static function ( $m ) use ( $self, $ext_pattern ) {
				$attr    = $m[1];
				$quote   = $m[2];
				$srcset  = $m[3];
				$parts   = array_map( 'trim', explode( ',', $srcset ) );
				$changed = false;
				$out     = array();
				foreach ( $parts as $part ) {
					if ( '' === $part ) {
						continue;
					}
					$tokens = preg_split( '#\s+#', $part, 2 );
					if ( ! is_array( $tokens ) || empty( $tokens[0] ) ) {
						$out[] = $part;
						continue;
					}
					$url  = $tokens[0];
					$desc = isset( $tokens[1] ) ? ' ' . trim( $tokens[1] ) : '';
					// Check if this URL has a rewritable extension.
					if ( preg_match( '#\.(?:' . $ext_pattern . ')(?:\?|$)#i', $url ) ) {
						if ( preg_match( '#^https?://#i', $url ) ) {
							$new = $self->maybe_rewrite_url( $url );
						} else {
							$new = $self->maybe_rewrite_relative_url( $url );
						}
						if ( $new !== $url ) {
							$changed = true;
							$url     = $new;
						}
					}
					$out[] = $url . $desc;
				}
				if ( ! $changed ) {
					return $m[0];
				}
				return $attr . '=' . $quote . implode( ', ', $out ) . $quote;
			},
			$html
		);
	}

	/**
	 * Rewrite url() values inside <style> blocks and style="" attributes.
	 *
	 * @param string $html         The HTML.
	 * @param string $ext_pattern  Regex alternation of allowed extensions.
	 *
	 * @return string
	 */
	protected function rewrite_css_url_values( $html, $ext_pattern ) {
		$self = $this;

		// Rewrite inside <style>...</style> blocks.
		$html = preg_replace_callback(
			'#(<style\b[^>]*>)(.*?)(</style>)#si',
			static function ( $m ) use ( $self, $ext_pattern ) {
				$css = $self->rewrite_css_urls_in_string( $m[2], $ext_pattern );
				return $m[1] . $css . $m[3];
			},
			$html
		);

		// Rewrite inside style="..." attributes.
		$html = preg_replace_callback(
			'#\bstyle\s*=\s*(["\'])(.*?)\1#si',
			static function ( $m ) use ( $self, $ext_pattern ) {
				$css = $self->rewrite_css_urls_in_string( $m[2], $ext_pattern );
				if ( $css === $m[2] ) {
					return $m[0];
				}
				return 'style=' . $m[1] . $css . $m[1];
			},
			$html
		);

		return $html;
	}

	/**
	 * Rewrite url() values in a CSS string.
	 *
	 * @param string $css          The CSS content.
	 * @param string $ext_pattern  Regex alternation of allowed extensions.
	 *
	 * @return string
	 */
	public function rewrite_css_urls_in_string( $css, $ext_pattern ) {
		$self = $this;
		return preg_replace_callback(
			'#url\(\s*([\'"]?)\s*((?:https?://[^\s)\'\"]+|/[^\s)\'\"]+))\s*\1\s*\)#i',
			static function ( $m ) use ( $self, $ext_pattern ) {
				$quote = $m[1];
				$url   = $m[2];
				// Check extension.
				if ( ! preg_match( '#\.(?:' . $ext_pattern . ')(?:\?|$)#i', $url ) ) {
					return $m[0];
				}
				// Absolute URL.
				if ( preg_match( '#^https?://#i', $url ) ) {
					$new = $self->maybe_rewrite_url( $url );
				} else {
					// Relative URL.
					$new = $self->maybe_rewrite_relative_url( $url );
				}
				if ( $new === $url ) {
					return $m[0];
				}
				return 'url(' . $quote . $new . $quote . ')';
			},
			$css
		);
	}

	/**
	 * Sanitize the ImageKit folder path setting.
	 *
	 * @param mixed $value Raw folder path.
	 *
	 * @return string
	 */
	public static function sanitize_imagekit_folder( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		$value = str_replace( '\\', '/', $value );
		$value = preg_replace( '#/+#', '/', $value );
		$value = trim( (string) $value, "/ \t\n\r\0\x0B" );
		// Keep only safe path characters.
		$value = preg_replace( '#[^A-Za-z0-9_\-./]#', '', (string) $value );
		$value = trim( (string) $value, '/' );
		if ( '' === $value ) {
			return '';
		}
		return $value . '/';
	}

	public function media_template() {
		?>
		<script type="text/html" id="tmpl-imagekit-eml">
			<div id="imagekit-eml-{{ data.controller.cid }}" class="ik-eml-widget-wrapper"></div>
		</script>
		<?php
	}

	public function editor_assets() {
		$this->plugin->register_assets();

		$max_files = apply_filters( 'imagekit_max_files_import', 20 );

		$eml_url = sprintf( IMAGEKIT_EML, IMAGEKIT_EML_VERSION );

		wp_enqueue_script( 'imagekit-media-modal', $this->plugin->dir_url . '/js/media-modal.js', array(), $this->plugin->version, true );
		// wp_enqueue_script( 'imagekit-media-library', $this->plugin->dir_url . '/js/eml.js', array(), $this->plugin->version, true );
		wp_enqueue_script( 'imagekit-media-library', $eml_url, array(), IMAGEKIT_EML_VERSION, true );
		wp_enqueue_style( 'imagekit' );

		$params = array(
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'widgetConfig' => array(
				'view'             => 'inline',
				'container'        => '#ik-eml-widget .ik-eml-widget-wrapper',
				'className'        => 'imagekit-media-library-widget',
				'dimensions'       => array(
					'height' => '100%',
					'width'  => '100%',
				),
				'renderOpenButton' => false,
				'mlSettings'       => array(
					'maxFiles' => $max_files,
					'toolbar'  => array(
						'showCloseButton' => false,
					),
				),
			),
		);

		wp_add_inline_script( 'imagekit-media-library', 'var IKML = ' . wp_json_encode( $params ) . ';', 'after' );
	}

	public function block_editor_assets() {
		$asset_file = $this->plugin->dir_path . 'js/block-editor.asset.php';
		$asset      = file_exists( $asset_file ) ? include $asset_file : array(
			'dependencies' => array(),
			'version'      => $this->plugin->version,
		);

		wp_enqueue_script(
			'imagekit-block-editor',
			$this->plugin->dir_url . 'js/block-editor.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}

	public function down_sync_asset() {
		$nonce = Utils::get_sanitized_text( 'nonce', INPUT_POST );
		if ( wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			$asset = $this->get_asset_payload();

			$base_return = array(
				'fetch'         => Utils::rest_url( REST_API::BASE . '/asset' ),
				'uploading'     => true,
				'src'           => $asset['src'],
				'url'           => $asset['url'],
				'filename'      => $asset['name'] ?? wp_basename( $asset['src'] ),
				'attachment_id' => $asset['attachment_id'],
				'fileId'        => $asset['fileId'],
			);

			if ( empty( $asset['attachment_id'] ) ) {
				$return                  = $base_return;
				$asset['attachment_id']  = $this->create_attachment( $asset, $asset['fileId'] );
				$return['attachment_id'] = $asset['attachment_id'];
			} else {
				$return           = wp_prepare_attachment_for_js( $asset['attachment_id'] );
				$return['fileId'] = $asset['fileId'];
			}

			do_action( 'imagekit_download_asset', $asset, $return );

			wp_send_json_success( $return );
		}

		return wp_send_json_error();
	}

	/**
	 * Register REST API endpoints for Media.
	 *
	 * @param array $endpoints Endpoints from the filter.
	 *
	 * @return array
	 */
	public function rest_endpoints( $endpoints ) {
		$endpoints['asset'] = array(
			'method'              => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'rest_asset' ),
			'args'                => array(),
			'permission_callback' => array( 'ImageKitWordpress\REST_API', 'rest_can_connect' ),
		);

		return $endpoints;
	}

	/**
	 * REST callback to return attachment data for a synced asset.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_asset( \WP_REST_Request $request ) {
		$attachment_id = absint( $request->get_param( 'attachment_id' ) );
		if ( empty( $attachment_id ) || ! get_post( $attachment_id ) ) {
			return new \WP_Error( 'invalid_attachment', __( 'Invalid attachment ID.', 'imagekit' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( wp_prepare_attachment_for_js( $attachment_id ) );
	}

	/**
	 * Copy an EML-imported ImageKit asset to WordPress filesystem when allowed.
	 *
	 * @param array $asset  Asset payload.
	 * @param array $return Response payload.
	 */
	public function maybe_copy_eml_asset_to_wordpress( $asset, $return ) {
		$mode = $this->get_offload_mode();
		if ( 'wp_none_ik_high' === $mode ) {
			return;
		}

		$attachment_id = 0;
		if ( is_array( $return ) && ! empty( $return['attachment_id'] ) ) {
			$attachment_id = absint( $return['attachment_id'] );
		} elseif ( is_array( $asset ) && ! empty( $asset['attachment_id'] ) ) {
			$attachment_id = absint( $asset['attachment_id'] );
		}
		if ( empty( $attachment_id ) ) {
			return;
		}
		if ( $this->has_local_file( $attachment_id ) ) {
			return;
		}
		if ( ! is_array( $asset ) || empty( $asset['src'] ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$url      = (string) $asset['src'];
		$name     = ! empty( $asset['name'] ) ? (string) $asset['name'] : wp_basename( $url );
		$tmp_file = download_url( $url );
		if ( is_wp_error( $tmp_file ) ) {
			$this->update_post_meta( $attachment_id, 'downsync_status', 'failed' );
			$this->update_post_meta( $attachment_id, 'downsync_error', $tmp_file->get_error_message() );
			return;
		}

		$file_array = array(
			'name'     => $name,
			'tmp_name' => $tmp_file,
		);
		$overrides  = array(
			'test_form' => false,
		);

		$sideload = wp_handle_sideload( $file_array, $overrides );
		if ( ! is_array( $sideload ) || ! empty( $sideload['error'] ) ) {
			@unlink( $tmp_file ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
			$this->update_post_meta( $attachment_id, 'downsync_status', 'failed' );
			$this->update_post_meta( $attachment_id, 'downsync_error', is_array( $sideload ) ? (string) $sideload['error'] : 'Sideload failed' );
			return;
		}

		$local_file = (string) $sideload['file'];
		$local_url  = (string) $sideload['url'];

		// Fix post_mime_type if it was empty (e.g. from imports with query-string URLs).
		$post_obj = get_post( $attachment_id );
		$update   = array(
			'ID'   => $attachment_id,
			'guid' => esc_url_raw( $local_url ),
		);
		if ( $post_obj && empty( $post_obj->post_mime_type ) ) {
			$sideload_type = ! empty( $sideload['type'] ) ? $sideload['type'] : '';
			if ( '' === $sideload_type ) {
				$detected      = wp_check_filetype( $local_file );
				$sideload_type = ! empty( $detected['type'] ) ? $detected['type'] : '';
			}
			if ( '' !== $sideload_type ) {
				$update['post_mime_type'] = $sideload_type;
			}
		}

		update_attached_file( $attachment_id, $local_file );
		wp_update_post( $update );

		// For low-res mode, store a low-res version in WordPress for images.
		if ( 'wp_low_ik_high' === $mode ) {
			$this->convert_local_to_low_res_if_image( $attachment_id, $local_file );
		}

		$meta = wp_generate_attachment_metadata( $attachment_id, $local_file );
		if ( is_array( $meta ) ) {
			wp_update_attachment_metadata( $attachment_id, $meta );
		}

		$this->update_post_meta( $attachment_id, 'downsync_status', 'success' );
		$this->update_post_meta( $attachment_id, 'downsync_error', '' );
	}

	private function convert_local_to_low_res_if_image( $attachment_id, $file ) {
		$mime = get_post_mime_type( $attachment_id );
		if ( ! is_string( $mime ) || 0 !== strpos( $mime, 'image/' ) ) {
			return;
		}
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return;
		}
		$size = $editor->get_size();
		if ( is_array( $size ) && ! empty( $size['width'] ) && is_numeric( $size['width'] ) ) {
			if ( (int) $size['width'] > \ImageKitWordpress\Uploader::DEFAULT_LOWRES_MAX_WIDTH ) {
				$editor->resize( \ImageKitWordpress\Uploader::DEFAULT_LOWRES_MAX_WIDTH, null, false );
			}
		}
		if ( method_exists( $editor, 'set_quality' ) ) {
			$editor->set_quality( \ImageKitWordpress\Uploader::DEFAULT_LOWRES_QUALITY );
		}
		$editor->save( $file );
	}

	private function get_asset_payload() {
		$args = array(
			'asset' => array(
				'flags' => FILTER_REQUIRE_ARRAY,
			),
		);

		$data = filter_input_array( INPUT_POST, $args );

		$asset = array(
			'type'              => sanitize_text_field( $data['asset']['type'] ),
			'name'              => sanitize_text_field( $data['asset']['name'] ),
			'createdAt'         => Utils::sanitize_date_string( $data['asset']['createdAt'] ),
			'updatedAt'         => Utils::sanitize_date_string( $data['asset']['updatedAt'] ),
			'fileId'            => sanitize_text_field( $data['asset']['fileId'] ),
			'tags'              => is_array( $data['asset']['tags'] ) ? array_map( 'sanitize_text_field', $data['asset']['tags'] ) : array(),
			'AITags'            => is_array( $data['asset']['AITags'] ) ? array_map( 'sanitize_text_field', $data['asset']['AITags'] ) : array(),
			'versionInfo'       => array(
				'id'   => sanitize_text_field( $data['asset']['versionInfo']['id'] ),
				'name' => sanitize_text_field( $data['asset']['versionInfo']['name'] ),
			),
			'embeddedMetadata'  => is_array( $data['asset']['embeddedMetadata'] ) ? array_map( 'sanitize_text_field', $data['asset']['embeddedMetadata'] ) : array(),
			'isPublished'       => rest_sanitize_boolean( $data['asset']['isPublished'] ),
			'customCoordinates' => sanitize_text_field( $data['asset']['customCoordinates'] ),
			'customMetadata'    => is_array( $data['asset']['customMetadata'] ) ? array_map( 'sanitize_text_field', $data['asset']['customMetadata'] ) : array(),
			'isPrivateFile'     => rest_sanitize_boolean( $data['asset']['isPrivateFile'] ),
			'url'               => esc_url_raw( $data['asset']['url'] ),
			'src'               => esc_url_raw( $data['asset']['url'] ),
			'fileType'          => sanitize_text_field( $data['asset']['fileType'] ),
			'filePath'          => sanitize_text_field( $data['asset']['filePath'] ),
			'height'            => sanitize_text_field( $data['asset']['height'] ),
			'width'             => sanitize_text_field( $data['asset']['width'] ),
			'size'              => sanitize_text_field( $data['asset']['size'] ),
			'hasAlpha'          => rest_sanitize_boolean( $data['asset']['hasAlpha'] ),
			'mime'              => sanitize_text_field( $data['asset']['mime'] ),
			'description'       => sanitize_text_field( $data['asset']['description'] ),
		);

		$asset['sync_key'] = $asset['fileId'];

		if ( ! $this->is_file_compatible( $asset['url'] ) ) {
			$asset['url'] = $this->generate_thumbnial( $asset['url'] );
		}

		$asset['attachment_id'] = $this->get_id_from_sync_key( $asset['sync_key'] );

		$asset = apply_filters( 'imagekit_asset_payload', $asset, $data );

		return $asset;
	}

	private function is_file_compatible( $file ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$original_file = $file;

		if ( $this->is_imagekit_url( $file ) ) {
			$file = Utils::download_fragment( $file );
		}

		if ( file_is_displayable_image( $file ) ) {
			return true;
		}
		$types        = $this->get_compatible_media_types();
		$file         = wp_parse_url( $original_file, PHP_URL_PATH );
		$filename     = Utils::pathinfo( $file, PATHINFO_BASENAME );
		$mime         = wp_check_filetype( $filename );
		$type         = strstr( $mime['type'], '/', true );
		$conversions  = $this->get_convertible_extensions();
		$convertibles = array_keys( $conversions );

		return in_array( $type, $types, true ) && ! in_array( $mime['ext'], $convertibles, true );
	}

	private function is_imagekit_url( $url ) {
		if ( ! filter_var( utf8_uri_encode( $url ), FILTER_VALIDATE_URL ) ) {
			return false;
		}
		$test_parts = wp_parse_url( $url );
		$cld_url    = wp_parse_url( $this->base_url, PHP_URL_HOST );

		return isset( $test_parts['path'] ) && false !== strpos( $test_parts['host'], $cld_url );
	}

	/**
	 * Get an array of compatible media types that are used by ImageKit.
	 *
	 * @return array
	 */
	public function get_compatible_media_types() {

		$media_types = array(
			'image',
			'video',
			'audio',
			'application',
			'text',
			'document',
			'archive',
			'spreadsheet',
			'interactive',
		);

		/**
		 * Filter the default ImageKit Media Types.
		 *
		 * @param array $types The default media types array.
		 *
		 * @return array
		 */
		return apply_filters( 'imagekit_media_types', $media_types );
	}

	/**
	 * Get convertible extensions and converted file types.
	 *
	 * @return array
	 */
	public function get_convertible_extensions() {

		// Add preferred formats in future.
		$base_types = array(
			'psd'  => 'jpg',
			'ai'   => 'jpg',
			'eps'  => 'jpg',
			'ps'   => 'jpg',
			'ept'  => 'jpg',
			'eps3' => 'jpg',
			'indd' => 'jpg',
			'webp' => 'gif',
			'bmp'  => 'jpg',
			'flif' => 'jpg',
			'gltf' => 'jpg',
			'heif' => 'jpg',
			'heic' => 'jpg',
			'ico'  => 'png',
			'svg'  => 'png',
			'tga'  => 'jpg',
			'tiff' => 'jpg',
			'tif'  => 'jpg',
		);

		/**
		 * Filter the base types for conversion.
		 *
		 * @param array $base_types The base conversion types array.
		 */
		return apply_filters( 'imagekit_convert_media_types', $base_types );
	}

	/**
	 * Generate thumbnail.
	 *
	 * @param string $fileurl The file url.
	 *
	 * @return string|null
	 */
	public function generate_thumbnial( $fileurl ) {

		$parsed = wp_parse_url( $fileurl );

		if ( empty( $parsed['path'] ) ) {
			return $fileurl;
		}

		// Append thumbnail suffix to path
		$parsed['path'] = trailingslashit( $parsed['path'] ) . 'ik-thumbnail.jpg';

		// Rebuild URL
		$new_url  = '';
		$new_url .= isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '';
		$new_url .= $parsed['host'] ?? '';
		$new_url .= $parsed['path'];

		if ( ! empty( $parsed['query'] ) ) {
			$new_url .= '?' . $parsed['query'];
		}

		return $new_url;
	}


	/**
	 * Attempt to get an attachment_id from a sync key.
	 *
	 * @param string $sync_key Key for matching a post_id.
	 * @param bool   $all      Flag to return all found ID's.
	 *
	 * @return int|array|false The attachment id or id's, or false if not found.
	 */
	public function get_id_from_sync_key( $sync_key, $all = false ) {

		$meta_query = array(
			array(
				'key'     => '_' . md5( $sync_key ),
				'compare' => 'EXISTS',
			),
		);
		$query_args = array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'fields'      => 'ids',
			'meta_query'  => $meta_query, // phpcs:ignore
		);

		$query         = new \WP_Query( $query_args );
		$ids           = $query->get_posts();
		$attachment_id = $ids;

		if ( ! empty( $ids ) && false === $all ) {
			// Essentially we should only have a single so use the first.
			$attachment_id = array_shift( $ids );
		}

		return $attachment_id;
	}

	private function is_imagekit_attachment( $attachment_id ) {
		if ( empty( $attachment_id ) ) {
			return false;
		}
		$ik_meta = $this->get_post_meta( $attachment_id );
		return ( ! empty( $ik_meta['_file_id'] ) || ! empty( $ik_meta['_imagekit_delivery'] ) || ! empty( $ik_meta['url'] ) || ! empty( $ik_meta['file_path'] ) );
	}

	private function get_imagekit_attachment_base_url( $attachment_id, $fallback = null ) {
		if ( ! $this->is_imagekit_attachment( $attachment_id ) ) {
			return $fallback;
		}

		$meta_url = $this->get_post_meta( $attachment_id, 'url', true );
		if ( is_string( $meta_url ) && '' !== $meta_url ) {
			return $meta_url;
		}

		$guid = get_post_field( 'guid', $attachment_id );
		if ( is_string( $guid ) && '' !== $guid && $this->is_imagekit_url( $guid ) ) {
			return $guid;
		}

		$file_path = $this->get_post_meta( $attachment_id, 'file_path', true );
		if ( is_string( $file_path ) && '' !== $file_path && ! empty( $this->base_url ) ) {
			return rtrim( (string) $this->base_url, '/' ) . '/' . ltrim( $file_path, '/' );
		}

		return $fallback;
	}

	private function parse_requested_size( $size ) {
		$width  = null;
		$height = null;
		if ( is_array( $size ) ) {
			if ( ! empty( $size[0] ) && is_numeric( $size[0] ) ) {
				$width = (int) $size[0];
			}
			if ( ! empty( $size[1] ) && is_numeric( $size[1] ) ) {
				$height = (int) $size[1];
			}
			return array( $width, $height );
		}

		if ( ! is_string( $size ) || '' === $size || 'full' === $size ) {
			return array( $width, $height );
		}

		$additional = function_exists( 'wp_get_additional_image_sizes' ) ? wp_get_additional_image_sizes() : array();
		if ( is_array( $additional ) && isset( $additional[ $size ] ) ) {
			$width  = ! empty( $additional[ $size ]['width'] ) ? (int) $additional[ $size ]['width'] : null;
			$height = ! empty( $additional[ $size ]['height'] ) ? (int) $additional[ $size ]['height'] : null;
			return array( $width, $height );
		}

		$w = get_option( $size . '_size_w' );
		$h = get_option( $size . '_size_h' );
		if ( is_numeric( $w ) && (int) $w > 0 ) {
			$width = (int) $w;
		}
		if ( is_numeric( $h ) && (int) $h > 0 ) {
			$height = (int) $h;
		}

		return array( $width, $height );
	}

	private function has_local_file( $attachment_id ) {
		$path = get_attached_file( $attachment_id );
		return is_string( $path ) && '' !== $path && file_exists( $path );
	}

	private function get_offload_mode() {
		if ( isset( $this->uploader ) && $this->uploader && method_exists( $this->uploader, 'get_offload_mode' ) ) {
			return (string) $this->uploader->get_offload_mode();
		}
		$raw = $this->settings ? $this->settings->get_value( 'offload' ) : '';
		$raw = is_string( $raw ) ? trim( $raw ) : '';
		if ( 'both_full' === $raw ) {
			return 'wp_high_ik_high';
		}
		if ( 'both_low' === $raw ) {
			return 'wp_low_ik_high';
		}
		if ( 'ik' === $raw ) {
			return 'wp_none_ik_high';
		}
		return $raw;
	}

	public function attachment_url( $url, $post_id ) {
		if ( empty( $post_id ) || ! $this->is_imagekit_attachment( $post_id ) ) {
			return $url;
		}
		$has_local = $this->has_local_file( $post_id );
		if ( $this->delivery->is_image_delivery_enabled() || ! $has_local ) {
			$ik_url = $this->get_imagekit_attachment_base_url( $post_id, $url );
			// Store mapping so the buffer rewriter can resolve synced uploads without DB.
			if ( is_string( $ik_url ) && $ik_url !== $url ) {
				$this->url_map[ $url ] = $ik_url;
				$this->populate_sized_variant_url_map( $post_id, $url, $ik_url );
			}
			return $ik_url;
		}
		return $url;
	}

	/**
	 * Ensure the URL map is populated for a given attachment.
	 *
	 * Computes the local base URL from upload_dir + attachment metadata,
	 * resolves the ImageKit base URL, and populates url_map with the
	 * full-size and all sized variant mappings.
	 *
	 * Safe to call multiple times — skips if the base URL is already mapped.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	private function ensure_attachment_url_map( $attachment_id ) {
		if ( ! $this->is_imagekit_attachment( $attachment_id ) ) {
			return;
		}

		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $meta ) || empty( $meta['file'] ) ) {
			return;
		}

		// Build the local base URL for this attachment.
		$base_local_url = rtrim( $this->upload_dir['baseurl'], '/' ) . '/' . $meta['file'];

		// Skip if already populated.
		if ( isset( $this->url_map[ $base_local_url ] ) ) {
			return;
		}

		$ik_url = $this->get_imagekit_attachment_base_url( $attachment_id, null );
		if ( ! is_string( $ik_url ) || '' === $ik_url ) {
			return;
		}

		$this->url_map[ $base_local_url ] = $ik_url;
		$this->populate_sized_variant_url_map( $attachment_id, $base_local_url, $ik_url );
	}

	/**
	 * Populate the URL map with sized variant URLs for an attachment.
	 *
	 * WordPress stores sized variants (e.g. image-1024x576.jpg) in attachment
	 * metadata. Post content often hardcodes these variant URLs. This method
	 * maps each local variant URL to its ImageKit equivalent with transforms,
	 * so the buffer rewriter can resolve them without DB calls.
	 *
	 * @param int    $post_id Attachment ID.
	 * @param string $base_local_url  Original local URL of the full-size image.
	 * @param string $base_ik_url     ImageKit URL of the full-size image.
	 */
	private function populate_sized_variant_url_map( $post_id, $base_local_url, $base_ik_url ) {
		$meta = wp_get_attachment_metadata( $post_id );
		if ( ! is_array( $meta ) || empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
			return;
		}

		$base_dir = dirname( $base_local_url );

		foreach ( $meta['sizes'] as $size_data ) {
			if ( empty( $size_data['file'] ) ) {
				continue;
			}
			$local_variant_url = $base_dir . '/' . $size_data['file'];

			// Skip if already mapped.
			if ( isset( $this->url_map[ $local_variant_url ] ) ) {
				continue;
			}

			$width  = ! empty( $size_data['width'] ) ? (int) $size_data['width'] : null;
			$height = ! empty( $size_data['height'] ) ? (int) $size_data['height'] : null;

			$ik_variant_url = $this->build_imagekit_srcset_url( $base_ik_url, $post_id, $width, $height );
			if ( is_string( $ik_variant_url ) && '' !== $ik_variant_url ) {
				$this->url_map[ $local_variant_url ] = $ik_variant_url;
			}
		}
	}

	public function original_attachment_url( $url, $attachment_id ) {
		if ( empty( $attachment_id ) || ! $this->is_imagekit_attachment( $attachment_id ) ) {
			return $url;
		}
		$has_local = $this->has_local_file( $attachment_id );
		if ( $this->delivery->is_image_delivery_enabled() || ! $has_local ) {
			return $this->get_imagekit_attachment_base_url( $attachment_id, $url );
		}
		return $url;
	}

	public function filter_downsize( $downsize, $id, $size ) {
		if ( empty( $id ) || ! $this->is_imagekit_attachment( $id ) ) {
			return $downsize;
		}
		$has_local = $this->has_local_file( $id );
		if ( ! $this->delivery->is_image_delivery_enabled() && $has_local ) {
			return $downsize;
		}

		$base = $this->get_imagekit_attachment_base_url( $id, null );
		if ( empty( $base ) ) {
			return $downsize;
		}

		list( $width, $height ) = $this->parse_requested_size( $size );

		if ( null === $width || $width <= 0 ) {
			$meta_width = $this->get_post_meta( $id, 'width', true );
			if ( is_numeric( $meta_width ) && (int) $meta_width > 0 ) {
				$width = (int) $meta_width;
			}
		}
		if ( null === $height || $height <= 0 ) {
			$meta_height = $this->get_post_meta( $id, 'height', true );
			if ( is_numeric( $meta_height ) && (int) $meta_height > 0 ) {
				$height = (int) $meta_height;
			}
		}

		$is_intermediate = true;
		if ( is_string( $size ) && 'full' === $size ) {
			$is_intermediate = false;
		}
		if ( null === $width && null === $height ) {
			$is_intermediate = false;
		}

		$url = $is_intermediate
			? $this->build_imagekit_srcset_url( $base, $id, $width, $height )
			: $this->build_imagekit_srcset_url( $base, $id, null, null );
		return array( $url, (int) ( $width ?? 0 ), (int) ( $height ?? 0 ), $is_intermediate );
	}

	/**
	 * Add srcset and sizes attributes for ImageKit images in post content.
	 *
	 * WordPress's wp_calculate_image_srcset() fails for images whose src is an
	 * ImageKit URL because it can't match the URL against the local upload base.
	 * This filter runs after WordPress's srcset attempt and fills the gap.
	 *
	 * @param string $filtered_image Full img tag.
	 * @param string $context        Filter context (e.g. 'the_content').
	 * @param int    $attachment_id  Attachment ID.
	 *
	 * @return string Modified img tag with srcset/sizes if applicable.
	 */
	public function maybe_add_imagekit_srcset( $filtered_image, $context, $attachment_id ) {
		// Skip if srcset already present.
		if ( false !== strpos( $filtered_image, ' srcset=' ) ) {
			return $filtered_image;
		}

		if ( empty( $attachment_id ) || ! $this->is_imagekit_attachment( $attachment_id ) ) {
			return $filtered_image;
		}

		$has_local = $this->has_local_file( $attachment_id );
		if ( ! $this->delivery->is_image_delivery_enabled() && $has_local ) {
			return $filtered_image;
		}

		$ik_base = $this->get_imagekit_attachment_base_url( $attachment_id, null );
		if ( ! is_string( $ik_base ) || '' === $ik_base ) {
			return $filtered_image;
		}

		// Populate url_map for this attachment.
		$this->ensure_attachment_url_map( $attachment_id );

		$image_meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $image_meta ) || empty( $image_meta['width'] ) ) {
			// Fall back to _imagekit meta for width/height.
			$ik_width  = $this->get_post_meta( $attachment_id, 'width', true );
			$ik_height = $this->get_post_meta( $attachment_id, 'height', true );
			if ( ! is_array( $image_meta ) ) {
				$image_meta = array();
			}
			if ( is_numeric( $ik_width ) && (int) $ik_width > 0 ) {
				$image_meta['width'] = (int) $ik_width;
			}
			if ( is_numeric( $ik_height ) && (int) $ik_height > 0 ) {
				$image_meta['height'] = (int) $ik_height;
			}
		}

		if ( empty( $image_meta['width'] ) || empty( $image_meta['height'] ) ) {
			return $filtered_image;
		}

		$full_width  = (int) $image_meta['width'];
		$full_height = (int) $image_meta['height'];

		// Determine displayed width from the img tag.
		$display_width = $full_width;
		if ( preg_match( '/ width=["\']([0-9]+)["\']/', $filtered_image, $m ) ) {
			$display_width = (int) $m[1];
		}

		// Build srcset sources using widths from the responsive images settings.
		$srcset_widths  = $this->get_srcset_widths_from_settings( $full_width );
		$srcset_entries = array();
		foreach ( $srcset_widths as $w ) {
			if ( $w >= $full_width ) {
				continue;
			}
			$h   = (int) round( $full_height * ( $w / $full_width ) );
			$url = $this->build_imagekit_srcset_url( $ik_base, $attachment_id, $w, $h );
			if ( is_string( $url ) && '' !== $url ) {
				$srcset_entries[] = $url . ' ' . $w . 'w';
			}
		}
		// Add the full-size image.
		$full_url = $this->build_imagekit_srcset_url( $ik_base, $attachment_id, $full_width, $full_height );
		if ( is_string( $full_url ) && '' !== $full_url ) {
			$srcset_entries[] = $full_url . ' ' . $full_width . 'w';
		}

		if ( empty( $srcset_entries ) ) {
			return $filtered_image;
		}

		$srcset_attr = implode( ', ', $srcset_entries );
		$sizes_attr  = sprintf( '(max-width: %1$dpx) 100vw, %1$dpx', $display_width );

		// Insert srcset and sizes before the closing /> or >.
		$filtered_image = preg_replace(
			'/\/?>\s*$/',
			sprintf( ' srcset="%s" sizes="%s" />', esc_attr( $srcset_attr ), esc_attr( $sizes_attr ) ),
			$filtered_image
		);

		return $filtered_image;
	}

	public function calculate_image_srcset_meta( $image_meta, $size_array, $image_src, $attachment_id = 0 ) {
		if ( empty( $attachment_id ) || ! $this->is_imagekit_attachment( $attachment_id ) ) {
			return $image_meta;
		}
		$has_local = $this->has_local_file( $attachment_id );
		if ( ! $this->delivery->is_image_delivery_enabled() && $has_local ) {
			return $image_meta;
		}

		if ( ! is_array( $image_meta ) ) {
			$image_meta = array();
		}

		if ( empty( $image_meta['width'] ) ) {
			$meta_width = $this->get_post_meta( $attachment_id, 'width', true );
			if ( is_numeric( $meta_width ) && (int) $meta_width > 0 ) {
				$image_meta['width'] = (int) $meta_width;
			}
		}
		if ( empty( $image_meta['height'] ) ) {
			$meta_height = $this->get_post_meta( $attachment_id, 'height', true );
			if ( is_numeric( $meta_height ) && (int) $meta_height > 0 ) {
				$image_meta['height'] = (int) $meta_height;
			}
		}

		if ( empty( $image_meta['file'] ) && is_string( $image_src ) && '' !== $image_src ) {
			$path = wp_parse_url( $image_src, PHP_URL_PATH );
			if ( is_string( $path ) && '' !== $path ) {
				$image_meta['file'] = ltrim( $path, '/' );
			}
		}

		return $image_meta;
	}

	/**
	 * Create a new attachment post item.
	 *
	 * @param array  $asset     The asset array data.
	 * @param string $id  The file Id.
	 *
	 * @return int|WP_Error
	 */
	private function create_attachment( $asset, $fileId ) {

		// Create an attachment post.
		$file_path = $asset['url'];
		// Strip query string before extracting filename (e.g. image.jpg?updatedAt=123).
		$clean_url = preg_replace( '/\?.*$/', '', $file_path );
		$file_name = wp_basename( $clean_url );
		$file_type = wp_check_filetype( $file_name, null );
		// Fall back to the MIME type from the asset payload if detection failed.
		if ( empty( $file_type['type'] ) && ! empty( $asset['mime'] ) ) {
			$file_type['type'] = sanitize_mime_type( $asset['mime'] );
		}
		$attachment_title = sanitize_file_name( Utils::pathinfo( $file_name, PATHINFO_FILENAME ) );
		$post_args        = array(
			'post_mime_type' => $file_type['type'],
			'post_title'     => $attachment_title,
			'guid'           => ! empty( $asset['url'] ) ? esc_url_raw( $asset['url'] ) : '',
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		if ( ! empty( $asset['description'] ) ) {
			$post_args['post_content'] = wp_strip_all_tags( $asset['description'] );
		}

		$attachment_id = wp_insert_attachment( $post_args, false );

		$sync_key = $asset['sync_key'];

		$this->update_post_meta( $attachment_id, '_file_id', $fileId );
		if ( ! empty( $asset['url'] ) ) {
			$this->update_post_meta( $attachment_id, 'url', esc_url_raw( $asset['url'] ) );
		}
		if ( ! empty( $asset['src'] ) ) {
			$this->update_post_meta( $attachment_id, 'src', esc_url_raw( $asset['src'] ) );
		}
		if ( ! empty( $asset['filePath'] ) ) {
			$this->update_post_meta( $attachment_id, 'file_path', sanitize_text_field( $asset['filePath'] ) );
		}
		if ( ! empty( $asset['width'] ) && is_numeric( $asset['width'] ) ) {
			$this->update_post_meta( $attachment_id, 'width', (int) $asset['width'] );
		}
		if ( ! empty( $asset['height'] ) && is_numeric( $asset['height'] ) ) {
			$this->update_post_meta( $attachment_id, 'height', (int) $asset['height'] );
		}
		if ( ! empty( $asset['mime'] ) ) {
			$this->update_post_meta( $attachment_id, 'mime', sanitize_text_field( $asset['mime'] ) );
		}

		update_post_meta( $attachment_id, '_' . md5( $sync_key ), true );

		$this->update_post_meta( $attachment_id, '_imagekit_delivery', $asset['type'] );

		if ( ! empty( $asset['description'] ) ) {
			$alt_text = wp_strip_all_tags( $asset['description'] );
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		}

		return $attachment_id;
	}

	/**
	 * Get ImagEKit related Post meta.
	 *
	 * @param int    $post_id The attachment ID.
	 * @param string $key     The meta key to get.
	 * @param bool   $single  If single or not.
	 * @param mixed  $default The default value if empty.
	 *
	 * @return mixed
	 */
	public function get_post_meta( $post_id, $key = '', $single = false, $default = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound

		$meta = get_post_meta( $post_id, '_imagekit', true );
		if ( '' !== $key ) {
			$meta = isset( $meta[ $key ] ) ? $meta[ $key ] : $default;
		}

		return $single ? $meta : (array) $meta;
	}


	/**
	 * Update imagekit metadata.
	 *
	 * @param int          $post_id The attachment ID.
	 * @param string       $key     The meta key to get.
	 * @param string|array $data    $the meta data to update.
	 *
	 * @return bool
	 */
	public function update_post_meta( $post_id, $key, $data ) {

		$meta = $this->get_post_meta( $post_id );
		if ( ! isset( $meta[ $key ] ) ) {
			$meta[ $key ] = '';
		}

		if ( $meta[ $key ] !== $data ) {
			$meta[ $key ] = $data;
		}

		return update_post_meta( $post_id, '_imagekit', $meta );
	}

	/**
	 * Get a resource type based on file.
	 *
	 * @param string $file The file to get type for.
	 *
	 * @return string
	 */
	public function get_file_type( $file ) {
		$file = wp_parse_url( $file, PHP_URL_PATH );
		$file = Utils::pathinfo( $file, PATHINFO_BASENAME );
		$mime = wp_check_filetype( $file );

		return strstr( $mime['type'], '/', true );
	}

	/**
	 * Get a resource type based on attachment_id.
	 *
	 * @param \WP_Post|int $attachment_id The attachment ID or object.
	 *
	 * @return string
	 */
	public function get_media_type( $attachment_id ) {
		return $this->get_file_type( get_attached_file( $attachment_id ) );
	}


	public function default_image_global_transformations( $default ) {

		$config = $this->settings->get_value( 'image_settings' );

		if ( ! empty( $config['image_global_transformation'] ) ) {
			$parts   = array_map( 'trim', explode( ',', $config['image_global_transformation'] ) );
			$parts   = array_filter(
				$parts,
				static function ( $t ) {
					return is_string( $t ) && '' !== $t;
				}
			);
			$default = array_merge( $default, array_values( $parts ) );
		}

		return $default;
	}

	/**
	 * Generate srcset widths from the responsive images settings.
	 *
	 * Uses pixel_step, min_width, max_width, and breakpoints (max count)
	 * from the media_display settings to compute widths dynamically.
	 *
	 * @param int $full_width The full image width (upper bound).
	 *
	 * @return int[]
	 */
	private function get_srcset_widths_from_settings( $full_width ) {
		$config = $this->plugin->settings->get_value( 'media_display' );
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

	private function normalize_breakpoint_widths( $breakpoints_meta ) {
		$widths = array();
		if ( empty( $breakpoints_meta ) || ! is_array( $breakpoints_meta ) ) {
			return $widths;
		}

		foreach ( $breakpoints_meta as $bp ) {
			if ( is_numeric( $bp ) ) {
				$widths[] = (int) $bp;
				continue;
			}
			if ( is_array( $bp ) && isset( $bp['width'] ) && is_numeric( $bp['width'] ) ) {
				$widths[] = (int) $bp['width'];
			}
		}

		$widths = array_filter(
			$widths,
			static function ( $w ) {
				return is_int( $w ) && $w > 0;
			}
		);
		$widths = array_values( array_unique( $widths ) );
		sort( $widths, SORT_NUMERIC );

		return $widths;
	}

	private function get_imagekit_relative_path_from_url( $url ) {
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

		return wp_basename( $path );
	}

	private function build_imagekit_srcset_url( $url, $attachment_id = null, $width = null, $height = null ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return $url;
		}

		if ( $this->is_imagekit_url( $url ) ) {
			$imagekit_url = $url;
		} else {
			// For synced attachments, use the saved ImageKit URL from meta.
			// The imagekit_folder may have changed since the image was uploaded.
			$saved_base = ! empty( $attachment_id ) ? $this->get_imagekit_attachment_base_url( $attachment_id, null ) : null;
			if ( is_string( $saved_base ) && '' !== $saved_base ) {
				$imagekit_url = $saved_base;
			} else {
				$relative = $this->get_imagekit_relative_path_from_url( $url );
				if ( '' === $relative && ! empty( $attachment_id ) ) {
					$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
					if ( is_string( $attached_file ) && '' !== $attached_file ) {
						$relative = ltrim( $attached_file, '/' );
					}
				}

				$folder = isset( $this->imagekit_folder ) ? (string) $this->imagekit_folder : '';
				$folder = trim( $folder, '/' );
				$path   = '';
				if ( '' !== $folder && '' !== $relative ) {
					$path = $folder . '/' . ltrim( $relative, '/' );
				} elseif ( '' !== $folder ) {
					$path = $folder;
				} else {
					$path = ltrim( $relative, '/' );
				}

				$imagekit_url = rtrim( (string) $this->base_url, '/' ) . '/' . ltrim( $path, '/' );
			}
		}

		$existing_tr = '';
		$existing_q  = wp_parse_url( $imagekit_url, PHP_URL_QUERY );
		if ( is_string( $existing_q ) && '' !== $existing_q ) {
			parse_str( $existing_q, $existing_query_args );
			if ( is_array( $existing_query_args ) && ! empty( $existing_query_args['tr'] ) && is_string( $existing_query_args['tr'] ) ) {
				$existing_tr = $existing_query_args['tr'];
			}
		}

		$transforms = apply_filters( 'imagekit_default_global_transformations_image', array() );
		$transforms = is_array( $transforms ) ? array_map( 'trim', $transforms ) : array();
		$transforms = array_filter(
			$transforms,
			static function ( $t ) {
				return is_string( $t ) && '' !== $t;
			}
		);
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

		if ( null !== $width && is_numeric( $width ) && (int) $width > 0 ) {
			$transforms[] = 'w-' . (int) $width;
		}
		if ( null !== $height && is_numeric( $height ) && (int) $height > 0 ) {
			$transforms[] = 'h-' . (int) $height;
		}

		$transforms = array_values( array_unique( $transforms ) );
		if ( ! empty( $transforms ) ) {
			$imagekit_url = add_query_arg( 'tr', implode( ',', $transforms ), $imagekit_url );
		}

		return $imagekit_url;
	}

	/**
	 * Get the responsive breakpoints for the image.
	 *
	 * @param array  $sources       The original sources array.
	 * @param array  $size_array    The size array.
	 * @param string $image_src     The original image source.
	 * @param array  $image_meta    The image meta array.
	 * @param int    $attachment_id The attachment id.
	 *
	 * @return array Altered or same sources array.
	 */
	public function image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		if ( ! empty( $attachment_id ) ) {
			$has_local = $this->has_local_file( $attachment_id );
			if ( ! $this->delivery->is_image_delivery_enabled() && $has_local ) {
				return $sources;
			}
			// For local-only images (not uploaded to ImageKit), generate
			// settings-based proxy srcset URLs with ImageKit transforms.
			if ( ! $this->is_imagekit_attachment( $attachment_id ) ) {
				if ( ! is_array( $image_meta ) || empty( $image_meta['file'] ) || empty( $image_meta['width'] ) || empty( $image_meta['height'] ) ) {
					return $sources;
				}
				$full_w     = (int) $image_meta['width'];
				$full_h     = (int) $image_meta['height'];
				$local_url  = rtrim( $this->upload_dir['baseurl'], '/' ) . '/' . $image_meta['file'];
				$proxy_base = $this->proxy_url( $local_url );

				$widths      = $this->get_srcset_widths_from_settings( $full_w );
				$new_sources = array();
				foreach ( $widths as $w ) {
					if ( $w >= $full_w ) {
						continue;
					}
					$h   = (int) round( $full_h * ( $w / $full_w ) );
					$url = $this->build_imagekit_srcset_url( $proxy_base, null, $w, $h );
					if ( is_string( $url ) && '' !== $url ) {
						$new_sources[ $w ] = array(
							'url'        => $url,
							'descriptor' => 'w',
							'value'      => $w,
						);
					}
				}
				// Add the full-size image.
				$full_url = $this->build_imagekit_srcset_url( $proxy_base, null, $full_w, $full_h );
				if ( is_string( $full_url ) && '' !== $full_url ) {
					$new_sources[ $full_w ] = array(
						'url'        => $full_url,
						'descriptor' => 'w',
						'value'      => $full_w,
					);
				}
				ksort( $new_sources, SORT_NUMERIC );
				return ! empty( $new_sources ) ? $new_sources : $sources;
			}
			// Populate url_map for this attachment so the buffer rewriter can
			// resolve hardcoded sized-variant URLs in post content.
			$this->ensure_attachment_url_map( $attachment_id );
		}

		$breakpoints_enabled = $this->delivery->is_breakpoints_enabled();

		$ratio_matches = false;
		if (
			is_array( $size_array )
			&& ! empty( $size_array[0] )
			&& ! empty( $size_array[1] )
			&& is_array( $image_meta )
			&& ! empty( $image_meta['width'] )
			&& ! empty( $image_meta['height'] )
		) {
			$requested_ratio = (float) $size_array[0] / (float) $size_array[1];
			$actual_ratio    = (float) $image_meta['width'] / (float) $image_meta['height'];
			$ratio_matches   = abs( $requested_ratio - $actual_ratio ) <= 0.01;
		}

		$breakpoints_meta = null;
		if ( is_array( $image_meta ) ) {
			if ( isset( $image_meta['imagekit_breakpoints'] ) ) {
				$breakpoints_meta = $image_meta['imagekit_breakpoints'];
			} elseif ( isset( $image_meta['breakpoints'] ) ) {
				$breakpoints_meta = $image_meta['breakpoints'];
			}
		}
		if ( null === $breakpoints_meta && ! empty( $attachment_id ) ) {
			$ik_meta = $this->get_post_meta( $attachment_id );
			if ( isset( $ik_meta['breakpoints'] ) ) {
				$breakpoints_meta = $ik_meta['breakpoints'];
			}
		}

		if ( $breakpoints_enabled && $ratio_matches ) {
			$widths = $this->normalize_breakpoint_widths( $breakpoints_meta );
			if ( ! empty( $widths ) ) {
				$breakpoint_sources = array();
				foreach ( $widths as $w ) {
					$breakpoint_sources[ $w ] = array(
						'url'        => $this->build_imagekit_srcset_url( $image_src, $attachment_id, $w ),
						'descriptor' => 'w',
						'value'      => $w,
					);
				}
				ksort( $breakpoint_sources, SORT_NUMERIC );
				return $breakpoint_sources;
			}
		}

		if ( empty( $sources ) || ! is_array( $sources ) ) {
			return $sources;
		}

		$converted          = array();
		$largest_source_url = '';
		$largest_width      = 0;
		foreach ( $sources as $key => $source ) {
			if ( ! is_array( $source ) || empty( $source['url'] ) ) {
				continue;
			}
			$descriptor = $source['descriptor'] ?? 'w';
			$value      = $source['value'] ?? $key;
			$width      = 0;
			if ( 'w' === $descriptor && is_numeric( $value ) ) {
				$width = (int) $value;
			} elseif ( 'x' === $descriptor && is_numeric( $value ) && is_array( $size_array ) && ! empty( $size_array[0] ) ) {
				$width = (int) round( (float) $size_array[0] * (float) $value );
			}
			if ( $width > $largest_width ) {
				$largest_width      = $width;
				$largest_source_url = (string) $source['url'];
			}
		}
		if ( '' === $largest_source_url ) {
			$largest_source_url = $image_src;
		}

		$aspect_ratio = null;
		if ( is_array( $image_meta ) && ! empty( $image_meta['width'] ) && ! empty( $image_meta['height'] ) ) {
			$aspect_ratio = (float) $image_meta['height'] / (float) $image_meta['width'];
		}

		foreach ( $sources as $key => $source ) {
			if ( ! is_array( $source ) || empty( $source['url'] ) ) {
				$converted[ $key ] = $source;
				continue;
			}

			$descriptor = $source['descriptor'] ?? 'w';
			$value      = $source['value'] ?? $key;
			$width      = null;
			$height     = null;

			if ( 'w' === $descriptor && is_numeric( $value ) ) {
				$width = (int) $value;
			} elseif ( 'x' === $descriptor && is_numeric( $value ) && is_array( $size_array ) && ! empty( $size_array[0] ) ) {
				$width = (int) round( (float) $size_array[0] * (float) $value );
			}

			if ( null !== $width && null !== $aspect_ratio ) {
				$height = (int) round( (float) $width * (float) $aspect_ratio );
			}

			$source['url']     = $this->build_imagekit_srcset_url( $largest_source_url, $attachment_id, $width, $height );
			$converted[ $key ] = $source;
		}

		return $converted;
	}
}
