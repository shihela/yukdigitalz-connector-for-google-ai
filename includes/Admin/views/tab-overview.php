<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$yukdiconfo_settings        = \YukDigitalz\AIConnectorGoogle\Settings::get_instance();
$yukdiconfo_stats           = \YukDigitalz\AIConnectorGoogle\Telemetry_Logger::get_stats();
$yukdiconfo_active_model    = $yukdiconfo_settings->get_effective_model();
$yukdiconfo_gemini_key      = $yukdiconfo_settings->get_gemini_api_key();
$yukdiconfo_backup_keys     = $yukdiconfo_settings->get( 'gemini_backup_keys', array() );
$yukdiconfo_dynamic_models  = $yukdiconfo_settings->get_dynamic_models();
$yukdiconfo_custom_model_id = $yukdiconfo_settings->get( 'custom_model_id', '' );
?>

<!-- Metric Cards Grid -->
<div class="yuk-ai-grid yuk-ai-grid-metrics">
	<!-- Metric 1: Total Requests -->
	<div class="yuk-ai-card yuk-ai-metric-card">
		<div class="yuk-ai-metric-icon yuk-ai-icon-blue">
			<span class="dashicons dashicons-rest-api"></span>
		</div>
		<div class="yuk-ai-metric-data">
			<div class="yuk-ai-metric-value"><?php echo esc_html( number_format_i18n( $yukdiconfo_stats['total_requests'] ) ); ?></div>
			<div class="yuk-ai-metric-label"><?php esc_html_e( 'Total Google AI Requests', 'yukdigitalz-connector-for-google-ai' ); ?></div>
		</div>
	</div>

	<!-- Metric 2: Success Rate -->
	<div class="yuk-ai-card yuk-ai-metric-card">
		<div class="yuk-ai-metric-icon yuk-ai-icon-green">
			<span class="dashicons dashicons-yes-alt"></span>
		</div>
		<div class="yuk-ai-metric-data">
			<div class="yuk-ai-metric-value"><?php echo esc_html( $yukdiconfo_stats['success_rate'] ); ?>%</div>
			<div class="yuk-ai-metric-label"><?php esc_html_e( 'Success Rate', 'yukdigitalz-connector-for-google-ai' ); ?></div>
		</div>
	</div>

	<!-- Metric 3: Failovers Recovered -->
	<div class="yuk-ai-card yuk-ai-metric-card">
		<div class="yuk-ai-metric-icon yuk-ai-icon-purple">
			<span class="dashicons dashicons-shield"></span>
		</div>
		<div class="yuk-ai-metric-data">
			<div class="yuk-ai-metric-value"><?php echo esc_html( number_format_i18n( $yukdiconfo_stats['failover_count'] ) ); ?></div>
			<div class="yuk-ai-metric-label"><?php esc_html_e( 'Auto-Failovers Recovered', 'yukdigitalz-connector-for-google-ai' ); ?></div>
		</div>
	</div>

	<!-- Metric 4: Avg Latency -->
	<div class="yuk-ai-card yuk-ai-metric-card">
		<div class="yuk-ai-metric-icon yuk-ai-icon-orange">
			<span class="dashicons dashicons-performance"></span>
		</div>
		<div class="yuk-ai-metric-data">
			<div class="yuk-ai-metric-value"><?php echo esc_html( number_format_i18n( $yukdiconfo_stats['avg_latency_ms'] ) ); ?> <small>ms</small></div>
			<div class="yuk-ai-metric-label"><?php esc_html_e( 'Avg Response Time', 'yukdigitalz-connector-for-google-ai' ); ?></div>
		</div>
	</div>
</div>

<!-- Main Overview Grid: Left column system status, right column quick test -->
<div class="yuk-ai-grid yuk-ai-grid-2col">
	<!-- Left Column: System Status Checklist & Pipeline -->
	<div class="yuk-ai-card">
		<div class="yuk-ai-card-header">
			<h2><span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'System Health & Connection Matrix', 'yukdigitalz-connector-for-google-ai' ); ?></h2>
		</div>
		<div class="yuk-ai-card-body">
			<ul class="yuk-ai-health-list">
				<!-- Item 1: Gemini API Key -->
				<li>
					<span class="yuk-ai-health-icon <?php echo esc_attr( ! empty( $yukdiconfo_gemini_key ) ? 'yuk-ai-health-ok' : 'yuk-ai-health-bad' ); ?>">
						<span class="dashicons <?php echo esc_attr( ! empty( $yukdiconfo_gemini_key ) ? 'dashicons-yes' : 'dashicons-no' ); ?>"></span>
					</span>
					<div class="yuk-ai-health-content">
						<strong><?php esc_html_e( 'Primary Google AI Studio API Key', 'yukdigitalz-connector-for-google-ai' ); ?></strong>
						<p>
							<?php
							if ( ! empty( $yukdiconfo_gemini_key ) ) {
								esc_html_e( 'Configured & Encrypted via OpenSSL AES-256-CBC.', 'yukdigitalz-connector-for-google-ai' );
							} else {
								esc_html_e( 'Missing Primary API Key. Please add your key in the Gemini API tab.', 'yukdigitalz-connector-for-google-ai' );
							}
							?>
						</p>
					</div>
				</li>

				<!-- Item 2: Backup Key Rotation -->
				<li>
					<span class="yuk-ai-health-icon <?php echo esc_attr( ! empty( $yukdiconfo_backup_keys ) ? 'yuk-ai-health-ok' : 'yuk-ai-health-bad' ); ?>">
						<span class="dashicons <?php echo esc_attr( ! empty( $yukdiconfo_backup_keys ) ? 'dashicons-yes' : 'dashicons-info' ); ?>"></span>
					</span>
					<div class="yuk-ai-health-content">
						<strong><?php esc_html_e( 'API Key Rotation Pool', 'yukdigitalz-connector-for-google-ai' ); ?></strong>
						<p>
							<?php
							if ( ! empty( $yukdiconfo_backup_keys ) ) {
								printf(
									/* translators: %d: number of backup keys */
									esc_html__( '%d secondary API keys configured for rate-limit failover.', 'yukdigitalz-connector-for-google-ai' ),
									count( $yukdiconfo_backup_keys )
								);
							} else {
								esc_html_e( 'Single API key mode active. Add backup keys to enable 429 quota rotation.', 'yukdigitalz-connector-for-google-ai' );
							}
							?>
						</p>
					</div>
				</li>

				<!-- Item 3: Auto-Failover Router -->
				<li>
					<span class="yuk-ai-health-icon <?php echo esc_attr( $yukdiconfo_settings->get( 'enable_failover', true ) ? 'yuk-ai-health-ok' : 'yuk-ai-health-bad' ); ?>">
						<span class="dashicons <?php echo esc_attr( $yukdiconfo_settings->get( 'enable_failover', true ) ? 'dashicons-shield' : 'dashicons-warning' ); ?>"></span>
					</span>
					<div class="yuk-ai-health-content">
						<strong><?php esc_html_e( 'Automatic 503 / 429 Failover Router', 'yukdigitalz-connector-for-google-ai' ); ?></strong>
						<p>
							<?php
							if ( $yukdiconfo_settings->get( 'enable_failover', true ) ) {
								esc_html_e( 'Enabled: Intercepts high-demand 503 & rate-limit 429 errors seamlessly.', 'yukdigitalz-connector-for-google-ai' );
							} else {
								esc_html_e( 'Disabled: Failover router is off. API errors will pass through directly.', 'yukdigitalz-connector-for-google-ai' );
							}
							?>
						</p>
					</div>
				</li>

				<!-- Item 4: Active Model Selection -->
				<li>
					<span class="yuk-ai-health-icon yuk-ai-health-ok">
						<span class="dashicons dashicons-yes"></span>
					</span>
					<div class="yuk-ai-health-content">
						<strong><?php esc_html_e( 'Dynamic Gemini Model Selector', 'yukdigitalz-connector-for-google-ai' ); ?></strong>
						<p>
							<?php
							/* translators: %s: active model name */
							printf( esc_html__( 'Currently active primary model: %s', 'yukdigitalz-connector-for-google-ai' ), '<code>' . esc_html( $yukdiconfo_active_model ) . '</code>' );
							?>
						</p>
					</div>
				</li>
			</ul>

			<!-- Pipeline Diagram Card -->
			<div class="yuk-ai-pipeline-diagram">
				<div class="yuk-ai-pipeline-step yuk-ai-step-primary">
					<div class="yuk-ai-step-badge"><?php esc_html_e( 'Primary Target Model', 'yukdigitalz-connector-for-google-ai' ); ?></div>
					<div class="yuk-ai-step-name"><code><?php echo esc_html( $yukdiconfo_active_model ); ?></code></div>
					<div class="yuk-ai-step-status"><?php esc_html_e( 'Standard Execution Path', 'yukdigitalz-connector-for-google-ai' ); ?></div>
				</div>

				<div class="yuk-ai-pipeline-arrow">
					<span class="dashicons dashicons-arrow-down-alt2"></span>
					<span class="yuk-ai-failover-trigger-badge"><?php esc_html_e( 'If 503 Overload / 429 Rate Limit Detected', 'yukdigitalz-connector-for-google-ai' ); ?></span>
				</div>

				<div class="yuk-ai-pipeline-step yuk-ai-step-backup">
					<div class="yuk-ai-step-badge"><?php esc_html_e( 'Failover Hierarchy & Key Pool', 'yukdigitalz-connector-for-google-ai' ); ?></div>
					<div class="yuk-ai-step-name"><?php esc_html_e( 'Automatic Key Rotation & Model Fallback', 'yukdigitalz-connector-for-google-ai' ); ?></div>
					<div class="yuk-ai-step-status"><?php esc_html_e( 'Circuit Breaker Transient Protection Active', 'yukdigitalz-connector-for-google-ai' ); ?></div>
				</div>
			</div>
		</div>
	</div>

	<!-- Right Column: Quick Playground Test Box -->
	<div class="yuk-ai-card">
		<div class="yuk-ai-card-header">
			<h2><span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e( 'Live Connection Tester & Playground', 'yukdigitalz-connector-for-google-ai' ); ?></h2>
		</div>
		<div class="yuk-ai-card-body">
			<p class="description">
				<?php esc_html_e( 'Test your Google Gemini connection in real-time. This prompt will execute through the active failover engine to verify API key validity and model response.', 'yukdigitalz-connector-for-google-ai' ); ?>
			</p>

			<div class="yuk-ai-form-group">
				<label for="yuk-ai-playground-prompt"><strong><?php esc_html_e( 'Test Prompt:', 'yukdigitalz-connector-for-google-ai' ); ?></strong></label>
				<textarea id="yuk-ai-playground-prompt" class="large-text" rows="4" placeholder="<?php esc_attr_e( 'Type a test prompt here...', 'yukdigitalz-connector-for-google-ai' ); ?>"><?php esc_html_e( 'Hello Gemini! Please reply in 1 sentence confirming our WordPress connection is online.', 'yukdigitalz-connector-for-google-ai' ); ?></textarea>
			</div>

			<div class="yuk-ai-input-with-button" style="margin-top: 15px;">
				<button type="button" id="yuk-ai-btn-quick-test" class="button button-primary button-hero">
					<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Run Live Test Prompt', 'yukdigitalz-connector-for-google-ai' ); ?>
				</button>
				<span class="spinner" id="yuk-ai-playground-spinner"></span>
			</div>

			<!-- Response Result Box -->
			<div id="yuk-ai-playground-result" class="yuk-ai-notice-inline yuk-ai-notice-info" style="display: none; margin-top: 20px; flex-direction: column;">
				<div style="display: flex; justify-content: space-between; width: 100%; align-items: center; border-bottom: 1px solid rgba(0,0,0,0.08); padding-bottom: 8px; margin-bottom: 10px;">
					<strong id="yuk-ai-res-model-badge"></strong>
					<span class="yuk-ai-badge-secure" id="yuk-ai-res-latency-badge"></span>
				</div>
				<div id="yuk-ai-res-text" style="line-height: 1.6; font-size: 13px;"></div>
			</div>
		</div>
	</div>
</div>
