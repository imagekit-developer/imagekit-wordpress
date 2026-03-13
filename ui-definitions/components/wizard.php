<?php
/**
 * Wizard page.
 *
 * @package ImageKitWordpress
 */
namespace ImageKitWordpress;

$imagekit = get_plugin_instance();
$admin    = $imagekit->get_component( 'admin' );
$url      = self_admin_url( add_query_arg( 'page', $imagekit->slug, 'admin.php' ) );


// Export settings.
$data = array(
	'testURL'   => Utils::rest_url( REST_API::BASE . '/test_connection' ),
	'saveURL'   => Utils::rest_url( REST_API::BASE . '/save_wizard' ),
	'saveNonce' => wp_create_nonce( 'wp_rest' ),
);

$credentials = array();
if ( $imagekit && method_exists( $imagekit, 'get_component' ) ) {
	$manager = $imagekit->get_component( 'credentials_manager' );
	if ( $manager && method_exists( $manager, 'get_credentials' ) ) {
		$credentials = (array) $manager->get_credentials();
	}
}

$data['prefill'] = array(
	'urlEndpoint' => ! empty( $credentials['url_endpoint'] ) ? (string) $credentials['url_endpoint'] : '',
	'publicKey'   => ! empty( $credentials['public_key'] ) ? (string) $credentials['public_key'] : '',
	'privateKey'  => ! empty( $credentials['private_key'] ) ? (string) $credentials['private_key'] : '',
);

$imagekit->add_script_data( 'wizard', $data );

?>

<div class="wrap ik-ui-wrap ik-wizard" id="imagekit-settings-page">
	<div class="ik-wizard-brand">
		<img src="<?php echo esc_url( $imagekit->dir_url . 'images/logo.svg' ); ?>" width="160" alt="<?php esc_attr_e( 'ImageKit', 'imagekit' ); ?>" />
	</div>

	<div class="ik-wizard-welcome">
		<span class="ik-wizard-welcome-icon" aria-hidden="true">
			<img class="ik-wizard-welcome-icon-img" src="<?php echo esc_url( 'https://ik.imagekit.io/ikmedia/see-you-soon.svg' ); ?>" alt="" />
		</span>
		<div class="ik-wizard-welcome-content">
			<h2 class="ik-wizard-welcome-title">
				<?php
				$current_user = wp_get_current_user();
				$display_name = ! empty( $current_user->display_name ) ? $current_user->display_name : __( 'there', 'imagekit' );
				printf(
					/* translators: %s: user display name */
					esc_html__( 'Welcome aboard, %s', 'imagekit' ),
					esc_html( $display_name )
				);
				?>
			</h2>
			<p class="ik-wizard-welcome-subtitle">
				<?php esc_html_e( 'Help us get you started with a few quick details.', 'imagekit' ); ?>
			</p>
		</div>
	</div>

	<div class="ik-panel ik-wizard-card">
		<div class="ik-wizard-step"><?php esc_html_e( '1/3', 'imagekit' ); ?></div>

		<div class="ik-wizard-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Wizard steps', 'imagekit' ); ?>">
			<button type="button" class="ik-wizard-tab is-active" id="ik-wizard-tab-1" data-step="1" role="tab" aria-selected="true" aria-controls="ik-wizard-panel-1">
				<span class="ik-wizard-tab-count">1</span>
				<?php esc_html_e( 'Welcome', 'imagekit' ); ?>
			</button>
			<button type="button" class="ik-wizard-tab" id="ik-wizard-tab-2" data-step="2" role="tab" aria-selected="false" aria-controls="ik-wizard-panel-2">
				<span class="ik-wizard-tab-count">2</span>
				<?php esc_html_e( 'Setup Credentials', 'imagekit' ); ?>
			</button>
			<button type="button" class="ik-wizard-tab" id="ik-wizard-tab-3" data-step="3" role="tab" aria-selected="false" aria-controls="ik-wizard-panel-3">
				<span class="ik-wizard-tab-count">3</span>
				<?php esc_html_e( 'Finish', 'imagekit' ); ?>
			</button>
		</div>

		<form class="ik-wizard-form" method="post" novalidate="novalidate">
			<?php wp_nonce_field( 'imagekit-wizard', '_ik_wizard_nonce' ); ?>

			<div class="ik-wizard-panels">
				<section class="ik-wizard-panel is-active" id="ik-wizard-panel-1" data-step="1" role="tabpanel" tabindex="0" aria-labelledby="ik-wizard-tab-1">
					<div class="ik-wizard-field">
						<h3 class="ik-wizard-intro">
							<?php esc_html_e( 'Make your WordPress site faster!', 'imagekit' ); ?>
						</h3>
						<p class="ik-wizard-copy-text">
							<?php esc_html_e( 'Images and videos are slowing down your site. ImageKit fixes this automatically with no code changes or migration. Just connect and go.', 'imagekit' ); ?>
						</p>
						<ul class="ik-wizard-copy-list">
							<li><?php esc_html_e( 'Works instantly with all your existing images and videos.', 'imagekit' ); ?></li>
							<li><?php esc_html_e( 'Automatically delivers modern formats like WebP, AVIF, WebM etc.', 'imagekit' ); ?></li>
							<li><?php esc_html_e( 'Optimizes every image and video during delivery via fast global CDN.', 'imagekit' ); ?></li>
							<li><?php esc_html_e( 'Your original WordPress files stay safe and untouched.', 'imagekit' ); ?></li>
						</ul>
						<p class="ik-wizard-copy-text">
							<?php esc_html_e( 'Connect your ImageKit account to get started.', 'imagekit' ); ?>
						</p>
					</div>
				</section>

				<section class="ik-wizard-panel" id="ik-wizard-panel-2" data-step="2" role="tabpanel" tabindex="0" aria-labelledby="ik-wizard-tab-2" hidden="hidden">
					<div class="ik-wizard-field">
						<h3 class="ik-wizard-intro">
							<?php esc_html_e( 'Connect your WordPress site to ImageKit', 'imagekit' ); ?>
						</h3>
					</div>

					<div class="ik-wizard-field">
						<div class="ik-wizard-note">
							<p class="ik-wizard-copy-text">
								<?php esc_html_e( 'First, add your WordPress site as an origin in your ImageKit dashboard:', 'imagekit' ); ?>
							</p>
							<ol class="ik-wizard-steps">
								<li>
									<?php
									echo wp_kses_post(
										sprintf(
											/* translators: %s: URL to ImageKit external storage dashboard */
											__( 'Open the <a href="%s" target="_blank" rel="noopener noreferrer">External storage section</a> in your ImageKit dashboard and click <strong>Add new</strong>.', 'imagekit' ),
											esc_url( 'https://imagekit.io/dashboard/external-storage' )
										)
									);
									?>
								</li>
								<li>
									<?php
									echo wp_kses_post(
										sprintf(
											/* translators: %s: URL to web server origin documentation */
											__( 'Select <a href="%s" target="_blank" rel="noopener noreferrer"><strong>Web Folder</strong></a> as the origin type.', 'imagekit' ),
											esc_url( 'https://imagekit.io/docs/integration/web-server' )
										)
									);
									?>
								</li>
								<li><?php esc_html_e( 'Enter a name for this origin (e.g., "My WordPress Site").', 'imagekit' ); ?></li>
								<li>
									<?php
									echo wp_kses_post(
										sprintf(
											/* translators: %s: WordPress site URL */
											__( 'Set the <strong>Base URL</strong> to your WordPress site URL: <code>%s</code>', 'imagekit' ),
											esc_html( home_url( '/' ) )
										)
									);
									?>
								</li>
								<li><?php esc_html_e( 'Leave other settings at default and click Submit.', 'imagekit' ); ?></li>
							</ol>
							<p class="ik-wizard-copy-text">
								<?php
								echo wp_kses_post(
									sprintf(
										/* translators: %s: URL to advanced options documentation */
										__( 'Need to configure advanced options? <a href="%s" target="_blank" rel="noopener noreferrer">See the documentation</a>.', 'imagekit' ),
										esc_url( 'https://imagekit.io/docs/integration/web-server#advanced-options-for-web-server-origin' )
									)
								);
								?>
							</p>
						</div>
					</div>

					<div class="ik-wizard-divider" role="separator" aria-hidden="true"></div>

					<div class="ik-wizard-field">
						<div class="ik-wizard-connect-form">
								<p class="ik-wizard-error" data-wizard-error="step2" role="alert" hidden="hidden">
									<?php esc_html_e( 'Please fill out the URL endpoint to continue.', 'imagekit' ); ?>
								</p>
								<div class="ik-wizard-input-group">
									<label class="ik-wizard-input-label" for="ik_wizard_url_endpoint">
										<?php esc_html_e( 'URL endpoint', 'imagekit' ); ?>
									</label>
									<input
										id="ik_wizard_url_endpoint"
										name="ik_wizard_url_endpoint"
										type="url"
										class="ik-wizard-input"
										placeholder="https://ik.imagekit.io/your_url_endpoint"
										autocomplete="url"
									/>
									<p class="ik-wizard-field-error" data-wizard-field-error="urlEndpoint" role="alert" hidden="hidden"></p>
								</div>

								<div class="ik-wizard-input-group">
									<label class="ik-wizard-input-label" for="ik_wizard_public_key">
										<?php esc_html_e( 'Public key', 'imagekit' ); ?>
									</label>
									<input
										id="ik_wizard_public_key"
										name="ik_wizard_public_key"
										type="text"
										class="ik-wizard-input"
										placeholder="your_public_key"
										autocomplete="off"
									/>
									<p class="ik-wizard-field-error" data-wizard-field-error="publicKey" role="alert" hidden="hidden"></p>
								</div>

								<div class="ik-wizard-input-group">
									<label class="ik-wizard-input-label" for="ik_wizard_private_key">
										<?php esc_html_e( 'Private key', 'imagekit' ); ?>
									</label>
									<input
										id="ik_wizard_private_key"
										name="ik_wizard_private_key"
										type="password"
										class="ik-wizard-input"
										placeholder="your_private_key"
										autocomplete="new-password"
									/>
									<p class="ik-wizard-field-error" data-wizard-field-error="privateKey" role="alert" hidden="hidden"></p>
								</div>

								<p class="ik-wizard-copy-text">
									<?php
									echo wp_kses_post(
										sprintf(
											/* translators: %s: URL to ImageKit developer dashboard */
											__( 'Find your credentials in the <a href="%s" target="_blank" rel="noopener noreferrer">Developer section</a> of your ImageKit dashboard. Copy the <strong>URL endpoint</strong>, <strong>Public key</strong>, and <strong>Private key</strong> and paste them above.', 'imagekit' ),
											esc_url( 'https://imagekit.io/dashboard/developer/api-keys' )
										)
									);
									?>
								</p>
						</div>
					</div>
				</section>

				<section class="ik-wizard-panel" id="ik-wizard-panel-3" data-step="3" role="tabpanel" tabindex="0" aria-labelledby="ik-wizard-tab-3" hidden="hidden">
					<div class="ik-wizard-field">
						<h3 class="ik-wizard-intro">
							<?php esc_html_e( 'Recommended settings', 'imagekit' ); ?>
						</h3>
						<p class="ik-wizard-copy-text">
							<?php esc_html_e( 'Choose what you\'d like to deliver through ImageKit. You can always change these later in the plugin settings.', 'imagekit' ); ?>
						</p>
					</div>

					<div class="ik-wizard-toggles">
						<label class="ik-wizard-toggle" for="ik_wizard_deliver_images">
							<div class="ik-wizard-toggle-info">
								<span class="ik-wizard-toggle-title"><?php esc_html_e( 'Images', 'imagekit' ); ?></span>
								<span class="ik-wizard-toggle-desc"><?php esc_html_e( 'Optimize and deliver images via ImageKit CDN with automatic format conversion.', 'imagekit' ); ?></span>
							</div>
							<span class="ik-wizard-toggle-switch">
								<input type="checkbox" id="ik_wizard_deliver_images" name="ik_wizard_deliver_images" value="on" checked />
								<span class="ik-wizard-toggle-slider"></span>
							</span>
						</label>

						<label class="ik-wizard-toggle" for="ik_wizard_deliver_videos">
							<div class="ik-wizard-toggle-info">
								<span class="ik-wizard-toggle-title"><?php esc_html_e( 'Videos', 'imagekit' ); ?></span>
								<span class="ik-wizard-toggle-desc"><?php esc_html_e( 'Deliver videos through ImageKit for faster playback and adaptive streaming.', 'imagekit' ); ?></span>
							</div>
							<span class="ik-wizard-toggle-switch">
								<input type="checkbox" id="ik_wizard_deliver_videos" name="ik_wizard_deliver_videos" value="on" checked />
								<span class="ik-wizard-toggle-slider"></span>
							</span>
						</label>

						<label class="ik-wizard-toggle" for="ik_wizard_deliver_assets">
							<div class="ik-wizard-toggle-info">
								<span class="ik-wizard-toggle-title"><?php esc_html_e( 'Assets', 'imagekit' ); ?></span>
								<span class="ik-wizard-toggle-desc"><?php esc_html_e( 'Deliver theme, plugin, and WordPress core CSS & JS files via ImageKit.', 'imagekit' ); ?></span>
							</div>
							<span class="ik-wizard-toggle-switch">
								<input type="checkbox" id="ik_wizard_deliver_assets" name="ik_wizard_deliver_assets" value="on" checked />
								<span class="ik-wizard-toggle-slider"></span>
							</span>
						</label>
					</div>
				</section>

				<section class="ik-wizard-panel" id="ik-wizard-panel-4" data-step="4" role="tabpanel" tabindex="0" aria-label="<?php esc_attr_e( 'Success', 'imagekit' ); ?>" hidden="hidden">
					<div class="ik-wizard-field">
						<h3 class="ik-wizard-intro">
							<?php esc_html_e( 'All set! Your site is now optimized with ImageKit', 'imagekit' ); ?>
						</h3>
						<p class="ik-wizard-copy-text">
							<?php esc_html_e( 'You have successfully set up the ImageKit plugin for your site. Your images and videos will now be automatically optimized and delivered via ImageKit\'s fast global CDN. To further configure the plugin, explore the various settings available in the plugin dashboard.', 'imagekit' ); ?>
						</p>
					</div>
				</section>
			</div>

			<div class="ik-wizard-actions">
				<button type="button" class="button ik-wizard-back" data-wizard-action="back" hidden="hidden">
					<?php esc_html_e( 'Back', 'imagekit' ); ?>
				</button>
				<button type="button" class="button button-primary ik-wizard-next" data-wizard-action="next">
					<?php esc_html_e( 'Next', 'imagekit' ); ?>
				</button>
				<a href="<?php echo esc_url( $url ); ?>" class="button button-primary ik-wizard-finish" data-wizard-action="finish" hidden="hidden">
					<?php esc_html_e( 'Goto plugin dashboard', 'imagekit' ); ?>
				</a>
			</div>
		</form>
	</div>
</div>
