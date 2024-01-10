<?php
/*
Plugin Name: ImageKit
Description: A WordPress plugin to automatically fetch your WordPress images via <a href="https://www.imagekit.io" target="_blank">ImageKit</a> for optimization and super fast delivery. <a href="https://imagekit.io/blog/how-to-optimize-images-on-wordpress-website-using-imagekit/" target="_blank">Learn more</a> from documentation.
Author: ImageKit
Author URI: https://imagekit.io
Version: 4.1.3
*/

// Variables
$imagekit_options = get_option('imagekit_settings');

if (!defined('ABSPATH')) {
  exit;
}

if (!defined('IK_PLUGIN_PATH')) {
  define('IK_PLUGIN_PATH', __DIR__);
}

if (!defined('IK_PLUGIN_ENTRYPOINT')) {
  define('IK_PLUGIN_ENTRYPOINT', __FILE__);
}

if (!defined(('IK_DEBUG'))) {
  define('IK_DEBUG', false);
}

add_action('template_redirect', function () {

  global $imagekit_options;

  if (isset($imagekit_options['imagekit_id'])) {
    $imagekitId = $imagekit_options['imagekit_id'];
  }

  if (isset($imagekit_options['imagekit_url_endpoint'])) {
    $imagekitUrlEndpoint = $imagekit_options['imagekit_url_endpoint'];
  }

  if (empty($imagekitId) && empty($imagekitUrlEndpoint)) {
    return;
  }

  // load class
  require_once __DIR__ . '/includes/ImageKitReWriter.php';
  require_once __DIR__ . '/includes/ImageKitHelper.php';

  // get url of cdn & site
  if (!empty($imagekitId)) {
    $cdn_url = "https://ik.imagekit.io/" . $imagekitId;
  }

  if (!empty($imagekit_options["cname"])) {
    $cdn_url = $imagekit_options["cname"];
  }

  if (!empty($imagekitUrlEndpoint)) {
    $cdn_url = $imagekitUrlEndpoint;
  }

  $cdn_url = ensure_valid_url($cdn_url);
  if (empty($cdn_url)) {
    return;
  }

  $site_url = get_home_url();

  // instantiate class
  $imageKit = new ImageKitReWriter($cdn_url, $site_url, $imagekit_options);
  ob_start(array(&$imageKit,
    'replace_all_links'
  ));
});

include ('includes/setting.php');

// Settings
function imagekit_plugin_admin_links($links, $file) {
  static $my_plugin;
  if (!$my_plugin) {
    $my_plugin = plugin_basename(__FILE__);
  }
  if ($file == $my_plugin) {
    $settings_link = '<a href="options-general.php?page=imagekit-setting">Settings</a>';
    array_unshift($links, $settings_link);
  }
  return $links;
}

function ensure_valid_url($url) {

  $parsed_url = parse_url($url);

  $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '//';
  $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
  $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
  $user = isset($parsed_url['user']) ? $parsed_url['user'] : '';
  $pass = isset($parsed_url['pass']) ? ':' . $parsed_url['pass'] : '';
  $pass = ($user || $pass) ? "$pass@" : '';
  $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
  $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
  $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

  $result = "$scheme$user$pass$host$port$path$query$fragment";

  if ($result) return substr($result, -1) == "/" ? $result : $result . '/';

  return NULL;
}

add_filter('plugin_action_links', 'imagekit_plugin_admin_links', 10, 2);
?>
