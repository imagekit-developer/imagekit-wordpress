<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      5.0.0
 */

namespace ImageKitWordpress;

use ImageKitWordpress\Component\Assets;
use ImageKitWordpress\UI\State;
use TestClass;


final class Plugin {


	/**
	 * Holds the component instances
	 *
	 * @var     array<Admin|null> Plugin components
	 */
	public $components;

	/**
	 * Plugin slug
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Path of plguin
	 *
	 * @var string
	 */
	public $dir_path;

	/**
	 * Path of templates
	 *
	 * @var string
	 */
	public $template_path;

	/**
	 * Url of the plugin
	 *
	 * @var string
	 */
	public $dir_url;

	/**
	 * Plugin file
	 *
	 * @var string
	 */
	public $plugin_file;

	/**
	 * Settings
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Plugin constuctor
	 */
	public function __construct() {
		$this->setup_endpoints();
		spl_autoload_register( array( $this, 'autoload' ) );
		$this->register_hooks();
	}

	/**
	 * Setup the imagekit ednpoints
	 *
	 * @return void
	 */
	private function setup_endpoints() {
		if ( ! defined( 'IMAGEKIT_EML' ) ) {
			define( 'IMAGEKIT_EML', 'https://unpkg.com/imagekit-media-library-widget@%s/dist/imagekit-media-library-widget.min.js' );
		}

		if ( ! defined( 'IMAGEKIT_EML_VERSION' ) ) {
			define( 'IMAGEKIT_EML_VERSION', '2.4.1' );
		}
	}


	/**
	 * Autoload for classes that are in the same namespace as $this.
	 *
	 * @param string $class Class name.
	 *
	 * @return void
	 */
	private function autoload( $class ) {
		$namespace = explode( '\\', $class );
		$root      = array_shift( $namespace );

		$class_trait = preg_match( '/Trait$/', $class ) ? 'trait-' : 'class-';

		if ( 'ImageKitWordpress' !== $root ) {
			return;
		}

		$class_name = array_pop( $namespace );

		if ( 'trait-' === $class_trait ) {
			$class_name = str_replace( '_Trait', '', $class_name );
		}

		$namespace = trim( implode( DIRECTORY_SEPARATOR, $namespace ) );

		$directory = __DIR__ . DIRECTORY_SEPARATOR . '../php';
		if ( ! empty( $namespace ) ) {
			$directory .= DIRECTORY_SEPARATOR . strtolower( $namespace );
		}

		$file = strtolower( str_replace( '_', '-', $class_name ) );

		$file = $directory . DIRECTORY_SEPARATOR . $class_trait . $file . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Register WordPress hooks
	 */
	private function register_hooks() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_enqueue_styles' ), 11 );
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'setup' ), 100 );
		add_action( 'init', array( $this, 'register_assets' ), 200 );
		add_filter( 'plugin_row_meta', array( $this, 'force_visit_plugin_site_link' ), 10, 4 );
		add_action( 'admin_print_footer_scripts', array( $this, 'print_script_data' ), 1 );
		add_action( 'wp_print_footer_scripts', array( $this, 'print_script_data' ), 1 );

		add_action( 'imagekit_version_upgrade', array( Utils::class, 'install' ) );
	}

	/**
	 * Get page structure
	 */
	private function get_page_structure() {
		$parts = array( 'pages' => array() );

		foreach ( $parts as $slug => $part ) {
			if ( file_exists( $this->dir_path . "ui-definitions/settings-{$slug}.php" ) ) {
				$parts[ $slug ] = include $this->dir_path . "ui-definitions/settings-{$slug}.php"; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			}
		}

		$structure = array(
			'version'    => $this->version,
			'page_title' => __( 'ImageKit', 'imagekit' ),
			'menu_title' => __( 'ImageKit', 'imagekit' ),
			'capability' => Utils::user_can( 'manage_settings' ) ? 'exist' : false,
			'icon'       => 'dashicons-imagekit',
			'slug'       => $this->slug,
			'settings'   => $parts['pages'],
			'sidebar'    => include IK_PLUGIN_PATH . 'ui-definitions/settings-sidebar.php',
		);

		return $structure;
	}

	/**
	 * Setup settings
	 */
	private function setup_settings() {
		$params         = $this->get_page_structure();
		$this->settings = new Settings( $this->slug, $params );
		$components     = array_filter(
			$this->components,
			function ( $component ) {
				return $component instanceof Settings_Component;
			}
		);
		$this->init_component_settings( $components );

		$connection = $this->get_component( 'credentials_manager' )->is_connected();
		if ( false === $connection ) {
			$count      = sprintf( ' <span class="update-plugins count-%d"><span class="update-count">%d</span></span>', 1, number_format_i18n( 1 ) );
			$main_title = $this->settings->get_param( 'menu_title' ) . $count;
			$this->settings->set_param( 'menu_title', $main_title );
			$this->settings->set_param( 'connect_count', $count );
		} else {
			$this->settings->set_param( 'connected', true );
			do_action( 'imagekit_connected', $this );
		}

		do_action( 'imagekit_init_settings', $this );

		$this->components['admin']->register_page( $this->slug, $this->settings->get_params() );
		// $this->components['admin']->register_page( $this->slug, $this->settings->get_params() );
	}

		/**
		 * Init component settings objects.
		 *
		 * @param Settings_Component[] $components of components to init settings for.
		 */
	private function init_component_settings( $components ) {
		$version = get_option( Credentials_Manager::META_KEYS['version'] );
		foreach ( $components as $slug => $component ) {
			/**
			 * Component that implements Settings.
			 *
			 * @var  Component\Settings $component
			 */
			$component->init_settings( $this->settings );

			// Upgrade settings if needed.
			if ( $version < $this->version ) {
				$component->upgrade_settings( $version, $this->version );
			}
		}
		// Update settings version, if needed.
		if ( $version < $this->version ) {
			update_option( Credentials_Manager::META_KEYS['version'], $this->version );
		}
	}

	/**
	 * Get a plugin component.
	 *
	 * @param mixed $component The component.
	 *
	 * @return Admin|Credentials_Manager|REST_API|State|null
	 */
	public function get_component( $component ) {
		$return = null;
		if ( isset( $this->components[ $component ] ) ) {
			$return = $this->components[ $component ];
		}

		return $return;
	}


	/****************************
	 * Hooks
	 ****************************/

	/**
	 * plugins_loaded hook
	 *
	 * @return void
	 */
	public function plugins_loaded() {
		$this->components['admin']               = new Admin( $this );
		$this->components['state']               = new State( $this );
		$this->components['credentials_manager'] = new Credentials_Manager( $this );
		$this->components['uploader']            = new Uploader( $this );
		$this->components['media']               = new Media( $this );
		$this->components['rest_api']            = new REST_API( $this );
	}


	/**
	 * register_enqueue_styles hook
	 *
	 * @return void
	 */
	public function register_enqueue_styles() {
		wp_enqueue_style( 'imagekit' );

		$components = array_filter(
			$this->components,
			function ( $component ) {
				return $component instanceof Assets && $component->is_active();
			}
		);

		array_map(
			function ( $component ) {
				$component->enqueue_assets();
			},
			$components
		);
	}

	/**
	 * init hook
	 *
	 * @return void
	 */
	public function init() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		function locate_plugin() {
			$dir_url      = plugin_dir_url( IK_PLUGIN_ENTRYPOINT );
			$dir_path     = IK_PLUGIN_PATH;
			$dir_basename = wp_basename( IK_PLUGIN_PATH );

			return compact( 'dir_url', 'dir_path', 'dir_basename' );
		}

		$plugin              = get_plugin_data( IK_PLUGIN_ENTRYPOINT );
		$location            = locate_plugin();
		$this->slug          = ! empty( $plugin['TextDomain'] ) ? $plugin['TextDomain'] : $location['dir_basename'];
		$this->version       = $plugin['Version'];
		$this->dir_path      = $location['dir_path'];
		$this->template_path = $this->dir_path . 'php/templates/';
		$this->dir_url       = $location['dir_url'];
		$this->plugin_file   = pathinfo( dirname( IK_PLUGIN_ENTRYPOINT ), PATHINFO_BASENAME ) . '/' . wp_basename( IK_PLUGIN_ENTRYPOINT );
	}

	/**
	 * setup hook
	 *
	 * @return void
	 */
	public function setup() {
		$this->setup_settings();

		if ( $this->settings->get_param( 'connected' ) ) {
			/**
			 * Component that implements Component\Setup.
			 *
			 * @var  Component\Setup $component
			 */
			foreach ( $this->components as $key => $component ) {
				if ( ! $component instanceof Settings_Component ) {
					continue;
				}

				$component->setup();
			}
		}

		do_action( 'imagekit_ready', $this );
	}


	/**
	 * Force Visit Plugin Site Link
	 *
	 * If the plugin slug is set and the current user can install plugins, only the "View Details" link is shown.
	 * This method forces the "Visit plugin site" link to appear.
	 *
	 * @see wp-admin/includes/class-wp-plugins-list-table.php
	 *
	 * @param array  $plugin_meta An array of the plugin's metadata.
	 * @param string $plugin_file Path to the plugin file, relative to the plugins directory.
	 * @param array  $plugin_data An array of plugin data.
	 * @param string $status      Status of the plugin.
	 *
	 * @return array
	 */
	public function force_visit_plugin_site_link( $plugin_meta, $plugin_file, $plugin_data, $status ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( 'ImageKit' === $plugin_data['Name'] ) {
			$plugin_site_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $plugin_data['PluginURI'] ),
				__( 'Visit plugin site', 'imagekit' )
			);
			if ( ! in_array( $plugin_site_link, $plugin_meta, true ) ) {
				$plugin_meta[] = $plugin_site_link;
			}
		}

		return $plugin_meta;
	}

	/**
	 * Output script data if set.
	 */
	public function print_script_data() {
		if ( ! isset( $this->settings ) || ! method_exists( $this->settings, 'get_param' ) ) {
			return;
		}

		$handles = $this->settings->get_param( '@script' );

		if ( ! empty( $handles ) ) {
			foreach ( $handles as $handle => $data ) {
				$json = wp_json_encode( $data );
				wp_add_inline_script( $handle, 'var ikData = ' . $json, 'before' );
			}
		}
	}

	/**
	 * Register assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		// Register Main.
		wp_register_script( 'imagekit', $this->dir_url . 'js/imagekit.js', array( 'jquery', 'wp-util', 'wp-api-fetch' ), $this->version, true );
		wp_register_style( 'imagekit', $this->dir_url . 'css/imagekit.css', null, $this->version );

		// $components = array_filter( $this->components, array( $this, 'is_asset_component' ) );
		// array_map(
		// function ( $component ) {
		// **
		// * Component that implements Component\Assets.
		// *
		// * @var  Component\Assets $component
		// */
		// $component->register_assets();
		// },
		// $components
		// );
	}


	/**
	 * Add Script data.
	 *
	 * @param string      $slug   The slug to add.
	 * @param mixed       $value  The value to set.
	 * @param string|null $handle $the optional script handle to add data for.
	 */
	public function add_script_data( $slug, $value, $handle = null ) {
		if ( null === $handle ) {
			$handle = $this->slug;
		}
		$this->settings->set_param( '@script' . $this->settings->separator . $handle . $this->settings->separator . $slug, $value );
	}
}
