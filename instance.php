<?php
/**
 * Instantiates the ImageKit plugin
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

global $imagekit_plugin;

require_once __DIR__ . '/php/class-plugin.php';
require_once __DIR__ . '/php/public-functions.php';

$imagekit_plugin = new Plugin();

/**
 * ImageKit Plugin Instance
 *
 * @return Plugin
 */
function get_plugin_instance() {
	global $imagekit_plugin;

	return $imagekit_plugin;
}
