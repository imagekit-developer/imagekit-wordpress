<?php
/**
 * Settings template.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

$imagekit    = get_plugin_instance();
$admin       = $imagekit->get_component( 'admin' );
$component   = $admin->get_component();
$connected   = $imagekit->settings->get_param( 'connected' );
$active_slug = $admin->get_param( 'active_slug' );
?>
<form method="post" novalidate="novalidate">
	<div class="ik-ui-wrap ik-row">
		<?php wp_nonce_field( 'imagekit-settings', '_ik_nonce' ); ?>
		<input type="hidden" name="imagekit-active-slug" value="<?php echo esc_attr( $active_slug ); ?>"/>
		<div class="ik-column <?php echo esc_html( 'column-' . $active_slug ); ?>">
			<?php
			$component->render( true );
			?>
		</div>
		<?php if ( ! empty( $connected ) && ! empty( $page['sidebar'] ) ) : ?>
			<div class="ik-column ik-ui-sidebar">
				<?php
				$def     = $imagekit->settings->get_param( 'sidebar' );
				$sidebar = $this->init_components( $def, 'sidebar' );
				$sidebar->get_component()->render( true );
				?>
			</div>
		<?php endif; ?>
	</div>
</form>
