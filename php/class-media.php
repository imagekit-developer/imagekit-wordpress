<?php

namespace ImageKitWordpress;

use ImageKitWordpress\Component\Setup;

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

	// /**
	// * Credentials.
	// *
	// * @var array.
	// */
	// public $credentials;

	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		// add_action( 'init', array( $this, 'init_hook' ) );
	}

	public function setup() {
		if ( $this->plugin->settings->get_param( 'connected' ) ) {

			$this->base_url = $this->plugin->components['credentials_manager']->api->imagekit_url();

			add_action( 'print_media_templates', callback: array( $this, 'media_template' ) );
			add_action( 'wp_enqueue_media', array( $this, 'editor_assets' ) );
			add_action( 'wp_ajax_imagekit-down-sync', callback: array( $this, 'down_sync_asset' ) );

		}
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
		wp_enqueue_script( 'imagekit-media-library', $this->plugin->dir_url . '/js/eml.js', array(), $this->plugin->version, true );
		// wp_enqueue_script('imagekit-media-library', $eml_url, [], IMAGEKIT_EML_VERSION, true);
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
				'id'            => $asset['fileId'],
			);


			if ( empty( $asset['attachment_id'] ) ) {
				$return = $base_return;
				$asset['attachment_id']  = $this->create_attachment( $asset, $asset['fileId'] );
				$return['attachment_id'] = $asset['attachment_id'];
			} else {
				if ( ! empty( $asset['description'] ) ) {
					$alt_text = wp_strip_all_tags( $asset['meta']['alt'] );
					// foreach ( $asset['instances'] as $id ) {
					// 	update_post_meta( $id, '_wp_attachment_image_alt', $alt_text );
					// }
				}
			}

			print_r( $return );

		}

		return wp_send_json_error();
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
			'description'       => sanitize_text_field($data['asset']['description']),
		);

		$asset['sync_key'] = $asset['fileId'];

		if ( ! $this->is_file_compatible( $asset['url'] ) ) {
			$asset['url'] = $this->generate_thumbnial( $asset['url'] );
		}

		$asset['attachment_id'] = $this->get_id_from_sync_key( $asset['sync_key'] );
		// $asset['instances']     = Relationship::get_ids_by_public_id( $asset['public_id'] );

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
			'post_content'   => $asset['description'] ?? "",
			'post_status'    => 'inherit',
		);

		// Capture the Caption Text.
		if ( ! empty( $asset['meta']['caption'] ) ) {
			$post_args['post_excerpt'] = wp_strip_all_tags( $asset['meta']['caption'] );
		}

		// Disable Upload_Sync to avoid sync loop.
		add_filter( 'imagekit_upload_sync_enabled', '__return_false' );
		// Create the attachment.
		$attachment_id = wp_insert_attachment( $post_args, false );

		$sync_key = $asset['sync_key'];
		// Capture id. Use core update_post_meta since this attachment data doesnt exist yet.
		$this->update_post_meta( $attachment_id, '_file_id', $fileId );

		// Capture version number.
		$this->update_post_meta( $attachment_id, '_version', $asset['versionInfo']['id'] );

		// Create a trackable key in post meta to allow getting the attachment id from URL with transformations.
		update_post_meta( $attachment_id, '_' . md5( $sync_key ), true );

		// Create a trackable key in post meta to allow getting the attachment id from URL.
		update_post_meta( $attachment_id, '_' . md5( 'base_' . $fileId ), true );

		// capture the delivery type.
		$this->update_post_meta( $attachment_id, '_imagekit_delivery', $asset['type'] );
		// Capture the ALT Text.
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
}