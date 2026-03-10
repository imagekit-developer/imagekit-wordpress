<?php

namespace ImageKitWordpress;

use ImageKitWordpress\Component\Setup;

class Delivery extends Settings_Component implements Setup {

	public function setup() {
	}

	/**
	 * Check whether responsive breakpoints are enabled.
	 *
	 * @return bool
	 */
	public function is_breakpoints_enabled() {
		$config = $this->settings ? $this->settings->get_value( 'media_display' ) : null;
		if ( ! is_array( $config ) ) {
			return false;
		}
		return ! empty( $config['enable_breakpoints'] ) && 'on' === $config['enable_breakpoints'];
	}

	public function is_image_delivery_enabled() {
		$config = $this->settings ? $this->settings->get_value( 'media_display' ) : null;
		if ( ! is_array( $config ) ) {
			return true;
		}
		if ( ! empty( $config['image_delivery'] ) && 'off' === $config['image_delivery'] ) {
			return false;
		}
		return true;
	}

	/**
	 * Get the asset delivery configuration array.
	 *
	 * @return array
	 */
	protected function get_asset_delivery_config() {
		$config = $this->settings ? $this->settings->get_value( 'asset_delivery' ) : null;
		return is_array( $config ) ? $config : array();
	}

	/**
	 * Check whether a specific asset delivery toggle is enabled.
	 *
	 * @param string $key The toggle key (e.g. 'theme_css', 'plugin_js').
	 *
	 * @return bool
	 */
	public function is_asset_delivery_enabled( $key ) {
		$config = $this->get_asset_delivery_config();
		return ! empty( $config[ $key ] ) && 'on' === $config[ $key ];
	}

	/**
	 * Check whether theme CSS delivery is enabled.
	 *
	 * @return bool
	 */
	public function is_theme_css_delivery_enabled() {
		return $this->is_asset_delivery_enabled( 'theme_css' );
	}

	/**
	 * Check whether theme JS delivery is enabled.
	 *
	 * @return bool
	 */
	public function is_theme_js_delivery_enabled() {
		return $this->is_asset_delivery_enabled( 'theme_js' );
	}

	/**
	 * Check whether plugin CSS delivery is enabled.
	 *
	 * @return bool
	 */
	public function is_plugin_css_delivery_enabled() {
		return $this->is_asset_delivery_enabled( 'plugin_css' );
	}

	/**
	 * Check whether plugin JS delivery is enabled.
	 *
	 * @return bool
	 */
	public function is_plugin_js_delivery_enabled() {
		return $this->is_asset_delivery_enabled( 'plugin_js' );
	}

	/**
	 * Check whether WP core CSS delivery is enabled.
	 *
	 * @return bool
	 */
	public function is_wp_core_css_delivery_enabled() {
		return $this->is_asset_delivery_enabled( 'wp_core_css' );
	}

	/**
	 * Check whether WP core JS delivery is enabled.
	 *
	 * @return bool
	 */
	public function is_wp_core_js_delivery_enabled() {
		return $this->is_asset_delivery_enabled( 'wp_core_js' );
	}

	/**
	 * Check whether any asset delivery toggle is enabled.
	 *
	 * @return bool
	 */
	public function has_any_asset_delivery_enabled() {
		$keys = array( 'theme_css', 'theme_js', 'plugin_css', 'plugin_js', 'wp_core_css', 'wp_core_js' );
		foreach ( $keys as $key ) {
			if ( $this->is_asset_delivery_enabled( $key ) ) {
				return true;
			}
		}
		return false;
	}
}