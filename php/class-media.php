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

	/**
	 * @var Delivery
	 */
	public $delivery;

	/**
	 * @var Asset_Rewriter
	 */
	public $asset_rewriter;

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
		if ( $this->plugin->settings->get_param( 'connected' ) ) {

			$this->base_url = $this->plugin->get_component('credentials_manager')->api->imagekit_url();
			$this->credentials       = $this->plugin->get_component('credentials_manager')->get_credentials();
			$this->imagekit_folder = $this->settings->get_value( slugs: 'imagekit_folder' );
			$this->uploader        = $this->plugin->get_component( 'uploader' );
			$this->delivery        = $this->plugin->get_component( 'delivery' );
			$this->rewriter        = new Rewriter( $this->plugin, $this->settings, $this->base_url, $this->imagekit_folder );
			$this->rewriter->setup();

			$this->asset_rewriter = new Asset_Rewriter( $this->plugin, $this->delivery, $this->base_url );
			$this->asset_rewriter->setup();

			// Rewriter
			// $this->filter                 = new Filter( $this );

			add_action( 'print_media_templates', callback: array( $this, 'media_template' ) );
			add_action( 'wp_enqueue_media', array( $this, 'editor_assets' ) );
			add_action( 'enqueue_block_editor_assets', array( $this, 'block_editor_assets' ) );
			add_action( 'wp_ajax_imagekit-down-sync', callback: array( $this, 'down_sync_asset' ) );
			add_action( 'imagekit_download_asset', array( $this, 'maybe_copy_eml_asset_to_wordpress' ), 10, 2 );

			add_filter( 'wp_calculate_image_srcset', array( $this, 'image_srcset' ), 10, 5 );
			add_filter( 'wp_get_attachment_url', array( $this, 'attachment_url' ), 10, 2 );
			add_filter( 'wp_get_original_image_url', array( $this, 'original_attachment_url' ), 10, 2 );
			add_filter( 'image_downsize', array( $this, 'filter_downsize' ), 10, 3 );
			add_filter( 'wp_calculate_image_srcset_meta', array( $this, 'calculate_image_srcset_meta' ), 10, 4 );

			add_filter( 'imagekit_default_global_transformations_image', array( $this, 'default_image_global_transformations' ), 10 );


		}
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
		wp_enqueue_script('imagekit-media-library', $eml_url, [], IMAGEKIT_EML_VERSION, true);
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
				'fileId'            => $asset['fileId'],
			);

			if ( empty( $asset['attachment_id'] ) ) {
				$return                  = $base_return;
				$asset['attachment_id']  = $this->create_attachment( $asset, $asset['fileId'] );
				$return['attachment_id'] = $asset['attachment_id'];
			} else {
				$return              = wp_prepare_attachment_for_js( $asset['attachment_id'] );
				$return['fileId'] = $asset['fileId'];
			}

			do_action( 'imagekit_download_asset', $asset, $return );

			wp_send_json_success( $return );
		}

		return wp_send_json_error();
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

		update_attached_file( $attachment_id, $local_file );
		wp_update_post(
			array(
				'ID'   => $attachment_id,
				'guid' => esc_url_raw( $local_url ),
			)
		);

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
			'url'               => sanitize_text_field( $data['asset']['url'] ),
			'src'               => sanitize_text_field( $data['asset']['url'] ),
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
			return $this->get_imagekit_attachment_base_url( $post_id, $url );
		}
		return $url;
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
		$file_path        = $asset['url'];
		$file_name        = wp_basename( $file_path );
		$file_type        = wp_check_filetype( $file_name, null );
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


	public function default_image_global_transformations($default) {

		$config = $this->settings->get_value( 'image_settings' );

		if (!empty($config['image_global_transformation'])) {
			$parts = array_map( 'trim', explode( ',', $config['image_global_transformation'] ) );
			$parts = array_filter(
				$parts,
				static function ( $t ) {
					return is_string( $t ) && '' !== $t;
				}
			);
			$default = array_merge( $default, array_values( $parts ) );
		}

		return $default;
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
	public function image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
		if ( ! empty( $attachment_id ) ) {
			$has_local = $this->has_local_file( $attachment_id );
			if ( ! $this->delivery->is_image_delivery_enabled() && $has_local ) {
				return $sources;
			}
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

		$converted = array();
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

			$source['url']      = $this->build_imagekit_srcset_url( $largest_source_url, $attachment_id, $width, $height );
			$converted[ $key ]  = $source;
		}

		return $converted;
	}
}
