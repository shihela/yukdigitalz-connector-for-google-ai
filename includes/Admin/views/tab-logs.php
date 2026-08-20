<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! \YukDigitalz\AIConnectorGoogle\Security::verify_admin( 'manage_options' ) ) {
	wp_die( esc_html__( 'Access denied: Insufficient permissions.', 'yukdigitalz-connector-for-google-ai' ) );
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
if ( isset( $_GET['log_filter_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['log_filter_nonce'] ) ), 'yukdiconfo_log_filter' ) ) {
		wp_die( esc_html__( 'Security check failed: Invalid nonce session.', 'yukdigitalz-connector-for-google-ai' ) );
	}
}

$yukdiconfo_status_filter = isset( $_GET['log_status'] ) ? sanitize_key( wp_unslash( $_GET['log_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$yukdiconfo_model_filter  = isset( $_GET['log_model'] ) ? sanitize_text_field( wp_unslash( $_GET['log_model'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$yukdiconfo_paged         = max( 1, isset( $_GET['paged'] ) ? intval( wp_unslash( $_GET['paged'] ) ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$yukdiconfo_per_page = 20;
$yukdiconfo_offset   = ( $yukdiconfo_paged - 1 ) * $yukdiconfo_per_page;

$yukdiconfo_filters = array();
if ( ! empty( $yukdiconfo_status_filter ) ) {
	$yukdiconfo_filters['status'] = $yukdiconfo_status_filter;
}
if ( ! empty( $yukdiconfo_model_filter ) ) {
	$yukdiconfo_filters['model'] = $yukdiconfo_model_filter;
}

$yukdiconfo_total_logs  = \YukDigitalz\AIConnectorGoogle\Telemetry_Logger::get_logs_count( $yukdiconfo_filters );
$yukdiconfo_total_pages = ceil( $yukdiconfo_total_logs / $yukdiconfo_per_page );
$yukdiconfo_logs        = \YukDigitalz\AIConnectorGoogle\Telemetry_Logger::get_logs( $yukdiconfo_per_page, $yukdiconfo_offset, $yukdiconfo_filters );
$yukdiconfo_stats       = \YukDigitalz\AIConnectorGoogle\Telemetry_Logger::get_stats();
$yukdiconfo_all_models  = yukdiconfo_get_all_models();
?>

<div class="yuk-ai-card">
	<div class="yuk-ai-card-header">
		<h2><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Telemetry & Request Audit Logs', 'yukdigitalz-connector-for-google-ai' ); ?></h2>
		<div class="yuk-ai-card-actions">
			<button type="button" id="yuk-ai-btn-clear-logs" class="button button-link-delete">
				<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear All Logs', 'yukdigitalz-connector-for-google-ai' ); ?>
			</button>
		</div>
	</div>

	<div class="yuk-ai-card-body">
		<!-- Filter Bar -->
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="yuk-ai-input-with-button" style="margin-bottom: 20px; background: var(--yuk-slate-50); padding: 14px 18px; border-radius: 8px; border: 1px solid var(--yuk-slate-200);">
			<input type="hidden" name="page" value="yukdiconfo">
			<input type="hidden" name="tab" value="logs">
			<?php wp_nonce_field( 'yukdiconfo_log_filter', 'log_filter_nonce' ); ?>

			<div style="display: flex; align-items: center; gap: 8px;">
				<label for="filter-status"><strong><?php esc_html_e( 'Status:', 'yukdigitalz-connector-for-google-ai' ); ?></strong></label>
				<select name="log_status" id="filter-status">
					<option value=""><?php esc_html_e( 'All Statuses', 'yukdigitalz-connector-for-google-ai' ); ?></option>
					<option value="success" <?php selected( $yukdiconfo_status_filter, 'success' ); ?>><?php esc_html_e( 'Success (200 OK)', 'yukdigitalz-connector-for-google-ai' ); ?></option>
					<option value="error" <?php selected( $yukdiconfo_status_filter, 'error' ); ?>><?php esc_html_e( 'Errors (503/429/500)', 'yukdigitalz-connector-for-google-ai' ); ?></option>
					<option value="failover" <?php selected( $yukdiconfo_status_filter, 'failover' ); ?>><?php esc_html_e( 'Failover Triggered', 'yukdigitalz-connector-for-google-ai' ); ?></option>
				</select>
			</div>

			<div style="display: flex; align-items: center; gap: 8px; margin-left: 15px;">
				<label for="filter-model"><strong><?php esc_html_e( 'Model:', 'yukdigitalz-connector-for-google-ai' ); ?></strong></label>
				<select name="log_model" id="filter-model">
					<option value=""><?php esc_html_e( 'All Models', 'yukdigitalz-connector-for-google-ai' ); ?></option>
					<?php foreach ( $yukdiconfo_all_models as $yukdiconfo_m_id => $yukdiconfo_m_info ) : ?>
						<?php $yukdiconfo_m_label = is_array( $yukdiconfo_m_info ) && isset( $yukdiconfo_m_info['name'] ) ? $yukdiconfo_m_info['name'] : ( is_string( $yukdiconfo_m_info ) ? $yukdiconfo_m_info : $yukdiconfo_m_id ); ?>
						<option value="<?php echo esc_attr( $yukdiconfo_m_id ); ?>" <?php selected( $yukdiconfo_model_filter, $yukdiconfo_m_id ); ?>><?php echo esc_html( $yukdiconfo_m_label ); ?> (<?php echo esc_html( $yukdiconfo_m_id ); ?>)</option>
					<?php endforeach; ?>
				</select>
			</div>

			<button type="submit" class="button button-secondary"><?php esc_html_e( 'Filter Logs', 'yukdigitalz-connector-for-google-ai' ); ?></button>
			<?php if ( ! empty( $yukdiconfo_status_filter ) || ! empty( $yukdiconfo_model_filter ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=yukdiconfo&tab=logs' ) ); ?>" class="button button-link"><?php esc_html_e( 'Reset Filter', 'yukdigitalz-connector-for-google-ai' ); ?></a>
			<?php endif; ?>
		</form>

		<!-- Log Table -->
		<?php if ( empty( $yukdiconfo_logs ) ) : ?>
			<div class="yuk-ai-notice-inline yuk-ai-notice-info">
				<span class="dashicons dashicons-info-outline"></span>
				<span><?php esc_html_e( 'No telemetry log records found for the selected filter criteria.', 'yukdigitalz-connector-for-google-ai' ); ?></span>
			</div>
		<?php else : ?>
			<div class="table-responsive">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col" style="width: 140px;"><?php esc_html_e( 'Time (Local)', 'yukdigitalz-connector-for-google-ai' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Requested Model', 'yukdigitalz-connector-for-google-ai' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Resolved Model', 'yukdigitalz-connector-for-google-ai' ); ?></th>
							<th scope="col" style="width: 90px;"><?php esc_html_e( 'Status', 'yukdigitalz-connector-for-google-ai' ); ?></th>
							<th scope="col" style="width: 100px;"><?php esc_html_e( 'Failover', 'yukdigitalz-connector-for-google-ai' ); ?></th>
							<th scope="col" style="width: 90px;"><?php esc_html_e( 'Latency', 'yukdigitalz-connector-for-google-ai' ); ?></th>
							<th scope="col" style="width: 90px;"><?php esc_html_e( 'Tokens', 'yukdigitalz-connector-for-google-ai' ); ?></th>
							<th scope="col" style="width: 110px;"><?php esc_html_e( 'Source', 'yukdigitalz-connector-for-google-ai' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $yukdiconfo_logs as $yukdiconfo_row ) : ?>
							<?php
							$yukdiconfo_is_success  = ! empty( $yukdiconfo_row['is_success'] );
							$yukdiconfo_status_code = isset( $yukdiconfo_row['status_code'] ) ? intval( $yukdiconfo_row['status_code'] ) : 200;
							?>

							<tr>
								<td><code><?php echo esc_html( date_i18n( 'M j, H:i:s', strtotime( $yukdiconfo_row['timestamp'] ) ) ); ?></code></td>
								<td><code><?php echo esc_html( $yukdiconfo_row['requested_model'] ); ?></code></td>
								<td>
									<strong><code><?php echo esc_html( $yukdiconfo_row['resolved_model'] ); ?></code></strong>
								</td>
								<td>
									<span class="yuk-ai-badge-secure" style="<?php echo esc_attr( $yukdiconfo_is_success ? 'background:#ecfdf5; color:#065f46;' : 'background:#fef2f2; color:#991b1b;' ); ?>">
										<?php echo esc_html( $yukdiconfo_status_code ); ?>
									</span>
								</td>
								<td>
									<?php if ( ! empty( $yukdiconfo_row['is_failover'] ) ) : ?>
										<span class="yuk-ai-badge-secure" style="background:#fdf4ff; color:#86198f; border-color:#f5d0fe;">
											<?php esc_html_e( 'YES', 'yukdigitalz-connector-for-google-ai' ); ?> (<?php echo esc_html( $yukdiconfo_row['failover_attempts'] ); ?>)
										</span>
									<?php else : ?>
										<span style="color: var(--yuk-slate-400); font-size: 12px;"><?php esc_html_e( 'NO', 'yukdigitalz-connector-for-google-ai' ); ?></span>
									<?php endif; ?>
								</td>
								<td><code><?php echo esc_html( number_format_i18n( $yukdiconfo_row['latency_ms'] ) ); ?> ms</code></td>
								<td>
									<code><?php echo esc_html( number_format_i18n( intval( $yukdiconfo_row['prompt_tokens'] ) + intval( $yukdiconfo_row['response_tokens'] ) ) ); ?></code>
								</td>
								<td><code><?php echo esc_html( $yukdiconfo_row['client_source'] ); ?></code></td>
							</tr>
							<?php if ( ! empty( $yukdiconfo_row['error_message'] ) ) : ?>
								<tr class="yuk-ai-error-detail-row">
									<td colspan="8">
										<div class="yuk-ai-notice-inline yuk-ai-notice-warning" style="margin: 4px 0;">
											<strong><?php esc_html_e( 'Error Detail:', 'yukdigitalz-connector-for-google-ai' ); ?></strong> <?php echo esc_html( $yukdiconfo_row['error_message'] ); ?>
										</div>
									</td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Dynamic Pagination with paginate_links -->
			<?php if ( $yukdiconfo_total_pages > 1 ) : ?>
				<div class="tablenav" style="margin-top: 15px;">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php
							/* translators: %s: total number of logs */
							printf( esc_html__( '%s items', 'yukdigitalz-connector-for-google-ai' ), esc_html( number_format_i18n( $yukdiconfo_total_logs ) ) );
							?>
						</span>

						<?php
						$yukdiconfo_page_links = paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => __( '&laquo;', 'yukdigitalz-connector-for-google-ai' ),
								'next_text' => __( '&raquo;', 'yukdigitalz-connector-for-google-ai' ),
								'total'     => $yukdiconfo_total_pages,
								'current'   => $yukdiconfo_paged,
							)
						);
						echo wp_kses_post( $yukdiconfo_page_links );
						?>

					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
