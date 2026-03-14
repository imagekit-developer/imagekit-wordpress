<?php
/**
 * Admin
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

use ImageKitWordpress\UI\Component;
use ImageKitWordpress\Traits\Params_Trait;

/**
 * Admin Class.
 */
class Admin {
	use Params_Trait;

	/**
	 * Holds the plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Holds the pages.
	 *
	 * @var array
	 */
	protected $pages;

	/**
	 * Holds the page section.
	 *
	 * @var string
	 */
	protected $section = 'page';

	/**
	 * Holds the current page component.
	 *
	 * @var Component
	 */
	protected $component;

	/**
	 * Holds the settings.
	 *
	 * @var Settings
	 */
	protected $settings;


	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		add_action( 'imagekit_init_settings', array( $this, 'init_settings' ) );
		add_action( 'admin_init', array( $this, 'init_setting_save' ), PHP_INT_MAX );
		add_action( 'admin_menu', array( $this, 'build_menus' ) );
		add_filter( 'pre_update_option_imagekit_media_display', array( $this, 'validate_responsive_width_limits' ) );
		// add_filter( 'imagekit_api_rest_endpoints', array( $this, 'rest_endpoints' ) );
	}

	/**
	 * Init the plugin settings.
	 */
	public function init_settings() {
		$this->settings = $this->plugin->settings;
	}


	/**
	 * Register a setting page.
	 *
	 * @param string $slug   The new page slug.
	 * @param array  $params The page parameters.
	 */
	public function register_page( $slug, $params = array() ) {
		// Register the page.
		$this->pages[ $slug ] = $params;
	}

	/**
	 * Register the page.
	 */
	public function build_menus() {
		foreach ( $this->pages as $page ) {
			$this->register_admin( $page );
		}
	}

	public function init_setting_save() {
		$submission = Utils::get_sanitized_text( 'imagekit-active-slug', INPUT_POST );

		if ( ! $submission ) {
			return; // Bail.
		}

		$args = array(
			'_ik_nonce'        => array(
				'filter'  => FILTER_CALLBACK,
				'options' => 'sanitize_text_field',
			),
			'_wp_http_referer' => FILTER_SANITIZE_URL,
			$submission        => array(
				'flags' => FILTER_REQUIRE_ARRAY,
			),
		);

		$saving = filter_input_array( INPUT_POST, $args, false );

		if ( ! empty( $saving ) && ! empty( $saving[ $submission ] ) && wp_verify_nonce( $saving['_ik_nonce'], 'imagekit-settings' ) ) {
			$referer = $saving['_wp_http_referer'];

			$data = $saving[ $submission ];
			$this->save_settings( $submission, $data );
			wp_safe_redirect( $referer );
			exit;
		}
	}

	/**
	 * Register the page.
	 *
	 * @param array $page The page array to create pages.
	 */
	protected function register_admin( $page ) {
		$render_function = array( $this, 'render' );

		$page_handle = add_menu_page(
			$page['page_title'],
			$page['menu_title'],
			$page['capability'],
			$page['slug'],
			'',
			$page['icon'],
			'81.5'
		);

		$connected = $this->settings->get_param( 'connected' );

		foreach ( $page['settings'] as $slug => $sub_page ) {
			if ( empty( $sub_page ) ) {
				continue;
			}
			if ( ! empty( $sub_page['requires_connection'] ) && false === $connected ) {
				continue;
			}
			$render_slug = $page['slug'] . '_' . $slug;
			if ( ! isset( $first ) ) {
				$render_slug = $page['slug'];
				$first       = true;
			}
			if ( ! empty( $sub_page['section'] ) ) {
				$this->set_param( $sub_page['section'], $sub_page );
				continue;
			}
			$capability = ! empty( $sub_page['capability'] ) ? $sub_page['capability'] : $page['capability'];
			$page_title = ! empty( $sub_page['page_title'] ) ? $sub_page['page_title'] : $page['page_title'];
			$menu_title = ! empty( $sub_page['menu_title'] ) ? $sub_page['menu_title'] : $page_title;
			$position   = ! empty( $sub_page['position'] ) ? $sub_page['position'] : 50;
			if ( isset( $sub_page['disconnected_title'] ) && true !== $this->settings->get_param( 'connected' ) ) {
				$page_title = $sub_page['disconnected_title'];
				$menu_title = $sub_page['disconnected_title'];
			}
			$page_handle      = add_submenu_page(
				$page['slug'],
				$page_title,
				$menu_title,
				$capability,
				$render_slug,
				$render_function,
				$position
			);
			$sub_page['slug'] = $slug;
			$this->set_param( $page_handle, $sub_page );
			add_action( "load-{$page_handle}", array( $this, $page_handle ) );
		}
	}


	/**
	 * Dynamically set the active page.
	 *
	 * @param string $name      The name called (page in this case).
	 * @param array  $arguments Arguments passed to call.
	 */
	public function __call( $name, $arguments ) {

		if ( $this->has_param( $name ) ) {

			$page = $this->get_param( $name );
			$this->settings->set_param( 'active_setting', $page['slug'] );
			$section = Utils::get_sanitized_text( 'section' );
			if ( $section && $this->has_param( $section ) ) {
				$this->section = $section;
				$this->set_param( 'current_section', $this->get_param( $section ) );
			}
			$url_endpoint = $this->settings->get_value( 'credentials.url_endpoint' );
			$url_endpoint = is_string( $url_endpoint ) ? trim( $url_endpoint ) : '';
			if ( 'page' === $this->section && '' === $url_endpoint && 'help' !== $page['slug'] ) {
				$args = array(
					'page'    => $this->plugin->slug,
					'section' => 'wizard',
				);
				$url  = add_query_arg( $args, 'admin.php' );
				wp_safe_redirect( $url );
				exit;
			}
		}
	}

	/**
	 *  Render a page
	 */
	public function render() {
		wp_enqueue_script( $this->plugin->slug );
		$screen = get_current_screen();
		$page   = $this->get_param( $screen->id );
		// Check if a section page was set, and replace page structure with the section.
		if ( $this->has_param( 'current_section' ) ) {
			$page = $this->get_param( 'current_section' );
		}

		$this->set_param( 'active_slug', $page['slug'] );
		$setting = $this->init_components( $page, $screen->id );

		$this->component = $setting->get_component();
		$template        = $this->section;

		$file = $this->plugin->dir_path . 'ui-definitions/components/page.php';

		if ( file_exists( $this->plugin->dir_path . 'ui-definitions/components/' . $template . '.php' ) ) {
			// If the section has a defined template, use that instead eg. wizard.
			$file = $this->plugin->dir_path . 'ui-definitions/components/' . $template . '.php';
		}
		include $file;
	}

	/**
	 * Initialise UI components.
	 *
	 * @param array  $template The template structure.
	 * @param string $slug     The slug of the template ti init.
	 *
	 * @return Settings\Setting|null
	 */
	public function init_components( $template, $slug ) {
		$url_endpoint = $this->settings->get_value( 'credentials.url_endpoint' );
		$url_endpoint = is_string( $url_endpoint ) ? trim( $url_endpoint ) : '';
		if ( ! empty( $template['requires_connection'] ) && '' === $url_endpoint ) {
			return null;
		}
		$setting = $this->settings->add( $slug, array(), $template );
		foreach ( $template as $index => $component ) {
			// Add setting components directly.
			if ( $component instanceof Setting ) {
				$setting->add( $component );
				continue;
			}

			if ( ( ! is_array( $component ) || ! isset( $component['slug'] ) ) && ! self::filter_template( $index ) ) {
				continue;
			}

			if ( ! isset( $component['type'] ) ) {
				$component['type'] = 'frame';
			}
			$component_slug = $index;
			if ( isset( $component['slug'] ) ) {
				$component_slug = $component['slug'];
			}
			if ( ! isset( $component['setting'] ) ) {
				$component['setting'] = $this->init_components( $component, $slug . $this->settings->separator . $component_slug );
			} else {
				$setting->add( $component['setting'] );
			}
		}

		return $setting;
	}



	/**
	 * Filter out non-setting params.
	 *
	 * @param numeric-string $key The key to filter out.
	 *
	 * @return bool
	 */
	public static function filter_template( $key ) {
		return is_numeric( $key ) || 'settings' === $key;
	}

	/**
	 * Get the component.
	 *
	 * @return Component
	 */
	public function get_component() {
		return $this->component;
	}


	public function validate_responsive_width_limits( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$min = isset( $value['min_width'] ) && '' !== $value['min_width'] ? (int) $value['min_width'] : null;
		$max = isset( $value['max_width'] ) && '' !== $value['max_width'] ? (int) $value['max_width'] : null;
		if ( null !== $min && null !== $max && $min > $max ) {
			$value['min_width'] = (string) $max;
			$value['max_width'] = (string) $min;
		}
		return $value;
	}

	protected function save_settings( $submission, $data ) {
		$page    = $this->settings->get_setting( $submission );
		$errors  = array();
		$pending = false;
		foreach ( $data as $key => $value ) {
			$slug    = $submission . $page->separator . $key;
			$current = $this->settings->get_value( $slug );
			if ( $current === $value ) {
				continue;
			}
			$capture_setting = $this->settings->get_setting( $key );
			$value           = $capture_setting->get_component()->sanitize_value( $value );
			$result          = $this->settings->set_pending( $key, $value, $current );
			if ( is_wp_error( $result ) ) {
				break;
			}
			$pending = true;
		}

		if ( empty( $errors ) && true === $pending ) {
			$this->settings->save();
		}
	}
}
