<?php
/**
 * The Admin page template.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

$plugin = get_plugin_instance();
?>
<div class="ik-ui-wrap ik-page ik-settings" id="imagekit-settings-page">
	<?php require IK_PLUGIN_PATH . 'ui-definitions/components/header.php'; ?>
	<?php require IK_PLUGIN_PATH . 'ui-definitions/components/settings.php'; ?>
</div>
