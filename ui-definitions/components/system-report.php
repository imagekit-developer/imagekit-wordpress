<?php
/**
 * System Report page.
 *
 * @package ImageKitWordpress
 */

namespace ImageKitWordpress;

$imagekit = get_plugin_instance();
$report   = System_Report::get_report();
$text     = System_Report::get_report_text();
$back_url = admin_url( 'admin.php?page=imagekit_help' );

?>
<div class="ik-ui-wrap ik-page ik-settings ik-system-report" id="imagekit-settings-page">
	<?php require IK_PLUGIN_PATH . 'ui-definitions/components/header.php'; ?>

	<div class="ik-ui-wrap ik-row">
		<div class="ik-column ik-system-report-column">

			<div class="ik-ui-header ik-panel-heading ik-panel">
				<div class="ik-ui-title">
					<h2 class="ik-ui-title-head"><?php esc_html_e( 'System Report', 'imagekit' ); ?></h2>
				</div>
			</div>

			<div class="ik-panel ik-system-report-intro">
				<p class="ik-system-report-description">
					<?php esc_html_e( 'This report contains useful technical information for debugging. Copy and paste it when contacting support.', 'imagekit' ); ?>
				</p>
				<div class="ik-system-report-actions">
					<button type="button" class="button button-secondary" id="ik-copy-report" data-report="<?php echo esc_attr( $text ); ?>">
						<span class="dashicons dashicons-clipboard"></span>
						<?php esc_html_e( 'Copy to Clipboard', 'imagekit' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="ik-download-report" data-report="<?php echo esc_attr( $text ); ?>">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Download', 'imagekit' ); ?>
					</button>
					<a href="<?php echo esc_url( $back_url ); ?>" class="button button-secondary">
						<span class="dashicons dashicons-arrow-left-alt"></span>
						<?php esc_html_e( 'Back to Help', 'imagekit' ); ?>
					</a>
				</div>
			</div>

			<?php foreach ( $report as $section_title => $fields ) : ?>
				<div class="ik-report-section">
					<h3 class="ik-report-section-title"><?php echo esc_html( $section_title ); ?></h3>
					<table class="ik-report-table">
						<tbody>
							<?php foreach ( $fields as $label => $value ) : ?>
								<tr>
									<td class="ik-report-label"><?php echo esc_html( $label ); ?></td>
									<td class="ik-report-value"><?php echo esc_html( $value ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>

		</div>
	</div>

	<div id="ik-copy-notice" class="ik-copy-notice" hidden>
		<?php esc_html_e( 'Copied to clipboard!', 'imagekit' ); ?>
	</div>
</div>
