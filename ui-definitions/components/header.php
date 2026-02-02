<?php
/**
 * The Admin page header template.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

$imagekit = get_plugin_instance();
?>
<header class="ik-ui-wrap ik-page-header" id="imagekit-header">
	<span class="ik-page-header-logo">
		<img src="<?php echo esc_url( $imagekit->dir_url . '/css/images/logo.svg' ); ?>" alt="<?php esc_attr_e( "imagekit's logo", 'imagekit' ); ?>"/>
		<span class="version"><?php echo esc_html( $imagekit->version ); ?></span>
	</span>
	<p>
		<a href="<?php echo esc_url( add_query_arg( 'page', 'imagekit_help' ) ); ?>" class="ik-page-header-button">
			<?php esc_html_e( 'Need help?', 'imagekit' ); ?>
		</a>
		<a href="https://wordpress.org/support/plugin/imagekit/reviews/#new-post" target="_blank" rel="noopener noreferrer" class="ik-page-header-button">
			<?php esc_html_e( 'Rate our plugin', 'imagekit' ); ?>
		</a>
	</p>
</header>
