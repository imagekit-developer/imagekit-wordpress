<?php
/**
 * ImageKit Uploader
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

use ImageKitWordpress\Component\Assets;
use ImageKitWordpress\Component\Setup;

/**
 * Class Uploader
 */
class Uploader extends Settings_Component implements Setup, Assets {

	const DEFAULT_LOWRES_MAX_WIDTH = 1920;
	const DEFAULT_LOWRES_QUALITY   = 75;

	/**
	 * Holds the plugin instance.
	 *
	 * @since   5.0.0
	 *
	 * @var     Plugin Instance of the global plugin.
	 */
	public $plugin;

	/**
	 * Push_Sync constructor.
	 *
	 * @param Plugin $plugin Global instance of the main plugin.
	 */
	public function __construct( Plugin $plugin ) {
		parent::__construct( $plugin );
		$this->plugin        = $plugin;
		$this->settings_slug = 'uploader';

		add_filter( 'imagekit_settings_pages', array( $this, 'settings' ) );
	}



	public function setup() {
		add_action( 'add_attachment', array( $this, 'on_add_attachment' ), 20, 1 );
		add_action( 'imagekit_offload_attachment', array( $this, 'process_offload_attachment' ), 10, 1 );
	}

	function enqueue_assets() {
		if ( isset( $this->plugin->settings ) && true === $this->plugin->settings->get_param( 'connected' ) ) {
			$data = array(
				'restUrl' => esc_url_raw( Utils::rest_url() ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			);
			wp_add_inline_script( 'imagekit', 'var imagekitApi = ' . wp_json_encode( $data ), 'before' );
		}
	}

	/**
	 * Is the component Active.
	 */
	public function is_active() {
		return true;
	}

	function register_assets() {}

	/**
	 * Intercept normal WordPress uploads.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function on_add_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( empty( $attachment_id ) ) {
			return;
		}

		// Only offload when the plugin is connected.
		if ( ! isset( $this->plugin->settings ) || true !== $this->plugin->settings->get_param( 'connected' ) ) {
			return;
		}
		if ( 'wp_high_ik_none' === $this->get_offload_mode() ) {
			return;
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$file = get_attached_file( $attachment_id );
		if ( ! is_string( $file ) || '' === $file || ! file_exists( $file ) ) {
			return;
		}

		$ik_meta = get_post_meta( $attachment_id, '_imagekit', true );
		if ( is_array( $ik_meta ) && ( ! empty( $ik_meta['_file_id'] ) || ! empty( $ik_meta['url'] ) || ! empty( $ik_meta['file_path'] ) ) ) {
			return;
		}

		$this->set_imagekit_meta_value( $attachment_id, 'offload_status', 'pending' );
		$this->set_imagekit_meta_value( $attachment_id, 'offload_updated_at', gmdate( DATE_ATOM ) );

		// Schedule offload after request ends so WP finished generating sizes/metadata.
		add_action(
			'shutdown',
			function () use ( $attachment_id ) {
				if ( wp_next_scheduled( 'imagekit_offload_attachment', array( $attachment_id ) ) ) {
					return;
				}
				wp_schedule_single_event( time() + 1, 'imagekit_offload_attachment', array( $attachment_id ) );
			},
			1
		);
	}

	/**
	 * Offload a WordPress attachment to ImageKit.
	 *
	 * Runs via WP-Cron (`imagekit_offload_attachment`).
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return bool True if processed, false otherwise.
	 */
	public function process_offload_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( empty( $attachment_id ) ) {
			return false;
		}
		if ( ! isset( $this->plugin->settings ) || true !== $this->plugin->settings->get_param( 'connected' ) ) {
			return false;
		}
		if ( 'wp_high_ik_none' === $this->get_offload_mode() ) {
			return false;
		}

		$file = get_attached_file( $attachment_id );
		if ( ! is_string( $file ) || '' === $file || ! file_exists( $file ) ) {
			$this->set_imagekit_meta_value( $attachment_id, 'offload_status', 'failed' );
			$this->set_imagekit_meta_value( $attachment_id, 'offload_error', 'Attachment file not found' );
			$this->set_imagekit_meta_value( $attachment_id, 'offload_lock', '' );
			return false;
		}

		$lock = $this->get_imagekit_meta_value( $attachment_id, 'offload_lock' );
		if ( is_string( $lock ) && '' !== $lock ) {
			return false;
		}

		$this->set_imagekit_meta_value( $attachment_id, 'offload_lock', wp_generate_uuid4() );
		$this->set_imagekit_meta_value( $attachment_id, 'offload_status', 'processing' );

		$mode   = $this->get_offload_mode();
		$folder = $this->get_imagekit_folder_for_attachment( $attachment_id, $file );
		$name   = wp_basename( $file );

		$api = $this->plugin->get_component( 'credentials_manager' );
		$api = $api && isset( $api->api ) ? $api->api : null;
		if ( ! $api || ! method_exists( $api, 'upload_file' ) ) {
			$this->set_imagekit_meta_value( $attachment_id, 'offload_status', 'failed' );
			$this->set_imagekit_meta_value( $attachment_id, 'offload_error', 'ImageKit API not available' );
			$this->set_imagekit_meta_value( $attachment_id, 'offload_lock', '' );
			return false;
		}

		$upload = $api->upload_file(
			$file,
			$name,
			$folder,
			array(
				'useUniqueFileName' => true,
			)
		);

		if ( is_wp_error( $upload ) ) {
			$this->set_imagekit_meta_value( $attachment_id, 'offload_status', 'failed' );
			$this->set_imagekit_meta_value( $attachment_id, 'offload_error', $upload->get_error_message() );
			$this->set_imagekit_meta_value( $attachment_id, 'offload_lock', '' );
			return false;
		}

		$url      = isset( $upload['url'] ) ? (string) $upload['url'] : '';
		$file_id  = isset( $upload['fileId'] ) ? (string) $upload['fileId'] : '';
		$filePath = isset( $upload['filePath'] ) ? (string) $upload['filePath'] : '';
		$width    = isset( $upload['width'] ) ? $upload['width'] : null;
		$height   = isset( $upload['height'] ) ? $upload['height'] : null;
		$mime     = isset( $upload['mime'] ) ? (string) $upload['mime'] : '';

		if ( '' === $file_id || '' === $url ) {
			$this->set_imagekit_meta_value( $attachment_id, 'offload_status', 'failed' );
			$this->set_imagekit_meta_value( $attachment_id, 'offload_error', 'Invalid upload response' );
			$this->set_imagekit_meta_value( $attachment_id, 'offload_lock', '' );
			return false;
		}

		$type = $this->infer_delivery_type( $mime );

		$this->set_imagekit_meta_value( $attachment_id, '_file_id', $file_id );
		$this->set_imagekit_meta_value( $attachment_id, 'url', esc_url_raw( $url ) );
		$this->set_imagekit_meta_value( $attachment_id, 'src', esc_url_raw( $url ) );
		if ( '' !== $filePath ) {
			$this->set_imagekit_meta_value( $attachment_id, 'file_path', ltrim( sanitize_text_field( $filePath ), '/' ) );
		}
		if ( is_numeric( $width ) ) {
			$this->set_imagekit_meta_value( $attachment_id, 'width', (int) $width );
		}
		if ( is_numeric( $height ) ) {
			$this->set_imagekit_meta_value( $attachment_id, 'height', (int) $height );
		}
		if ( '' !== $mime ) {
			$this->set_imagekit_meta_value( $attachment_id, 'mime', sanitize_text_field( $mime ) );
		}
		$this->set_imagekit_meta_value( $attachment_id, '_imagekit_delivery', $type );

		// Apply the selected storage mode.
		if ( 'wp_low_ik_high' === $mode && 0 === strpos( $type, 'image' ) ) {
			$this->convert_local_to_low_res( $attachment_id, $file );
		} elseif ( 'wp_none_ik_high' === $mode ) {
			$this->delete_local_attachment_files( $attachment_id, $file );
			wp_update_post(
				array(
					'ID'   => $attachment_id,
					'guid' => esc_url_raw( $url ),
				)
			);
			delete_post_meta( $attachment_id, '_wp_attached_file' );
			update_post_meta( $attachment_id, '_wp_attachment_metadata', array() );
		}

		$this->set_imagekit_meta_value( $attachment_id, 'offload_status', 'success' );
		$this->set_imagekit_meta_value( $attachment_id, 'offload_error', '' );
		$this->set_imagekit_meta_value( $attachment_id, 'offload_updated_at', gmdate( DATE_ATOM ) );
		$this->set_imagekit_meta_value( $attachment_id, 'offload_lock', '' );

		return true;
	}

	public function get_offload_mode() {
		$raw = $this->settings ? $this->settings->get_value( 'offload' ) : null;
		$raw = is_string( $raw ) ? $raw : '';
		$raw = trim( $raw );

		// Back-compat with earlier label values used in UI tooltips.
		if ( 'both_full' === $raw ) {
			return 'wp_high_ik_high';
		}
		if ( 'both_low' === $raw ) {
			return 'wp_low_ik_high';
		}
		if ( 'ik' === $raw ) {
			return 'wp_none_ik_high';
		}

		if ( in_array( $raw, array( 'wp_high_ik_high', 'wp_low_ik_high', 'wp_none_ik_high', 'wp_high_ik_none' ), true ) ) {
			return $raw;
		}

		return 'wp_high_ik_high';
	}

	private function infer_delivery_type( $mime ) {
		$mime = is_string( $mime ) ? $mime : '';
		$mime = strtolower( $mime );
		if ( '' === $mime || false === strpos( $mime, '/' ) ) {
			return 'application';
		}
		return strstr( $mime, '/', true );
	}

	private function get_imagekit_folder_for_attachment( $attachment_id, $local_file ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$base_folder = $this->settings ? (string) $this->settings->get_value( 'imagekit_folder' ) : '';
		$base_folder = trim( $base_folder );
		$base_folder = trim( $base_folder, '/' );

		$uploads = wp_get_upload_dir();
		$basedir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
		$basedir = rtrim( $basedir, '/' );

		$relative = '';
		if ( '' !== $basedir && 0 === strpos( $local_file, $basedir ) ) {
			$relative = ltrim( substr( $local_file, strlen( $basedir ) ), '/' );
		}
		$dir = '';
		if ( '' !== $relative ) {
			$dir = dirname( $relative );
			if ( '.' === $dir ) {
				$dir = '';
			}
		}

		$parts = array();
		if ( '' !== $base_folder ) {
			$parts[] = $base_folder;
		}
		if ( '' !== $dir ) {
			$parts[] = trim( $dir, '/' );
		}

		return implode( '/', $parts );
	}

	private function set_imagekit_meta_value( $attachment_id, $key, $value ) {
		$meta = get_post_meta( $attachment_id, '_imagekit', true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		if ( '' === $value ) {
			unset( $meta[ $key ] );
		} else {
			$meta[ $key ] = $value;
		}
		update_post_meta( $attachment_id, '_imagekit', $meta );
	}

	private function get_imagekit_meta_value( $attachment_id, $key, $default = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound
		$meta = get_post_meta( $attachment_id, '_imagekit', true );
		if ( ! is_array( $meta ) ) {
			return $default;
		}
		return $meta[ $key ] ?? $default;
	}

	private function convert_local_to_low_res( $attachment_id, $file ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		/**
		 * Filter the maximum width used when downscaling the local copy in
		 * "ImageKit and WordPress (low resolution)" mode.
		 *
		 * @since 5.0.2
		 *
		 * @param int $max_width     Default maximum width in pixels.
		 * @param int $attachment_id The attachment being processed.
		 * @param string $file       Absolute path to the local source file.
		 */
		$max_width = (int) apply_filters( 'imagekit_lowres_max_width', self::DEFAULT_LOWRES_MAX_WIDTH, $attachment_id, $file );
		$max_width = $max_width > 0 ? $max_width : self::DEFAULT_LOWRES_MAX_WIDTH;

		/**
		 * Filter the JPEG quality used when re-encoding the local low-res copy.
		 *
		 * @since 5.0.2
		 *
		 * @param int $quality       Default quality (1-100).
		 * @param int $attachment_id The attachment being processed.
		 * @param string $file       Absolute path to the local source file.
		 */
		$quality = (int) apply_filters( 'imagekit_lowres_quality', self::DEFAULT_LOWRES_QUALITY, $attachment_id, $file );
		$quality = max( 1, min( 100, $quality ) );

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return;
		}

		$size = $editor->get_size();
		if ( is_array( $size ) && ! empty( $size['width'] ) && is_numeric( $size['width'] ) ) {
			if ( (int) $size['width'] > $max_width ) {
				$resized = $editor->resize( $max_width, null, false );
				if ( is_wp_error( $resized ) ) {
					return;
				}
			}
		}

		if ( method_exists( $editor, 'set_quality' ) ) {
			$editor->set_quality( $quality );
		}
		$saved = $editor->save( $file );
		if ( is_wp_error( $saved ) ) {
			return;
		}

		// Regenerate metadata so sizes are based on the low-res original.
		$meta = wp_generate_attachment_metadata( $attachment_id, $file );
		if ( is_array( $meta ) ) {
			wp_update_attachment_metadata( $attachment_id, $meta );
		}
	}

	private function delete_local_attachment_files( $attachment_id, $file ) {
		$uploads = wp_get_upload_dir();
		$basedir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
		$basedir = rtrim( $basedir, '/' );

		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $meta ) && ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			$meta_file = isset( $meta['file'] ) && is_string( $meta['file'] ) ? $meta['file'] : '';
			$dir       = '';
			if ( '' !== $meta_file ) {
				$dir = dirname( $meta_file );
				if ( '.' === $dir ) {
					$dir = '';
				}
			}
			foreach ( $meta['sizes'] as $size ) {
				if ( ! is_array( $size ) || empty( $size['file'] ) ) {
					continue;
				}
				$path = $basedir;
				if ( '' !== $dir ) {
					$path .= '/' . $dir;
				}
				$path .= '/' . $size['file'];
				if ( is_string( $path ) && '' !== $path && file_exists( $path ) ) {
					@unlink( $path ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
				}
			}
		}

		if ( is_string( $file ) && '' !== $file && file_exists( $file ) ) {
			@unlink( $file ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
		}
	}

	/**
	 * Define the settings on the general settings page.
	 *
	 * @param array $pages The pages to add to.
	 *
	 * @return array
	 */
	public function settings( $pages ) {
		$credentials_manager = $this->plugin->get_component( 'credentials_manager' );
		$is_disabled         = true;
		if ( $credentials_manager && $credentials_manager->get_settings() ) {
			$is_disabled = true !== $credentials_manager->get_connection_state();
		} else {
			$credentials = get_option( 'imagekit_credentials', array() );
			$credentials = is_array( $credentials ) ? $credentials : array();
			$public_key  = isset( $credentials['public_key'] ) && is_string( $credentials['public_key'] ) ? trim( $credentials['public_key'] ) : '';
			$private_key = isset( $credentials['private_key'] ) && is_string( $credentials['private_key'] ) ? trim( $credentials['private_key'] ) : '';
			$url         = isset( $credentials['url_endpoint'] ) && is_string( $credentials['url_endpoint'] ) ? trim( $credentials['url_endpoint'] ) : '';
			$is_disabled = '' === $url || '' === $public_key || '' === $private_key;
		}
		$disabled_msg = __( 'Configure API Keys to enable uploads.', 'imagekit' );

		$pages['credentials']['settings'][] = array(
			array(
				'type'                => 'frame',
				'requires_connection' => true,
				array(
					'type'        => 'panel',
					'title'       => __( 'Media Library Upload Settings', 'imagekit' ),
					'option_name' => 'upload',
					'collapsible' => 'open',
					array(
						'type'              => 'input',
						'slug'              => 'imagekit_folder',
						'title'             => __( 'ImageKit folder path', 'imagekit' ),
						'disabled'          => $is_disabled,
						'description'       => $is_disabled ? $disabled_msg : null,
						'default'           => '',
						'attributes'        => array(
							'input' => array(
								'placeholder' => __( 'e.g.: wordpress_assets/', 'imagekit' ),
							),
						),
						'tooltip_text'      => __(
							'The folder in your ImageKit account that WordPress assets are uploaded to. Leave blank to use the root of your ImageKit library.',
							'imagekit'
						),
						'sanitize_callback' => array( '\ImageKitWordpress\Media', 'sanitize_imagekit_folder' ),
					),
					array(
						'type'         => 'select',
						'slug'         => 'offload',
						'title'        => __( 'Storage', 'imagekit' ),
						'disabled'     => $is_disabled,
						'description'  => $is_disabled ? $disabled_msg : null,
						'tooltip_text' => sprintf(
							// translators: the HTML for opening and closing list and its items.
							__(
								'Choose where your assets are stored.%1$s<b>ImageKit and WordPress</b>: Stores assets in both locations. Enables local WordPress delivery if the ImageKit plugin is disabled or uninstalled.%2$s<b>ImageKit and WordPress (low resolution)</b>: Stores original assets in ImageKit and low resolution versions in WordPress. Enables low resolution local WordPress delivery if the plugin is disabled or uninstalled.%3$s<b>ImageKit only</b>: Stores assets in ImageKit only. Requires additional steps to enable backwards compatibility.%4$s<b>WordPress only</b>: Stores assets in WordPress only and disables uploading new assets to ImageKit.%5$s%6$sLearn more%7$s',
								'imagekit'
							),
							'<ul><li class="both_full">',
							'</li><li class="both_low">',
							'</li><li class="ik">',
							'</li><li class="wp_only">',
							'</li></ul>',
							'<a href="https://imagekit.io/docs/integration/wordpress" target="_blank" rel="noopener noreferrer">',
							'</a>'
						),
						'default'      => 'wp_high_ik_high',
						'options'      => array(
							'wp_high_ik_high' => __( 'ImageKit and WordPress', 'imagekit' ),
							'wp_low_ik_high'  => __( 'ImageKit and WordPress (low resolution)', 'imagekit' ),
							'wp_none_ik_high' => __( 'ImageKit only', 'imagekit' ),
							'wp_high_ik_none' => __( 'WordPress only', 'imagekit' ),
						),
					),
				),
			),
		);
		return $pages;
	}
}
