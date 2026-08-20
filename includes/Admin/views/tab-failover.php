<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$yukdiconfo_settings          = \YukDigitalz\AIConnectorGoogle\Settings::get_instance();
$yukdiconfo_enable_failover   = $yukdiconfo_settings->get( 'enable_failover', true );
$yukdiconfo_enable_auto_retry = $yukdiconfo_settings->get( 'enable_auto_retry', true );
$yukdiconfo_fallback_models   = $yukdiconfo_settings->get( 'fallback_models', array() );
$yukdiconfo_all_models        = yukdiconfo_get_all_models();
?>

<form id="yuk-ai-settings-form-failover">
	<input type="hidden" name="form_type" value="failover">
	<input type="hidden" name="failover_submitted" value="1">

	<!-- Failover Master Policy Card -->
	<div class="yuk-ai-card">
		<div class="yuk-ai-card-header">
			<h2><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Auto-Failover & Retry Policy', 'yukdigitalz-connector-for-google-ai' ); ?></h2>
		</div>
		<div class="yuk-ai-card-body">
			<p class="description" style="margin-bottom: 20px;">
				<?php esc_html_e( 'When Google AI Studio returns Error 503 (Overloaded) or Error 429 (Rate Limit), this engine intercepts the failure and reroutes the request through your designated fallback hierarchy instantly.', 'yukdigitalz-connector-for-google-ai' ); ?>
			</p>

			<!-- Master Switches Grid -->
			<div class="yuk-ai-grid yuk-ai-grid-2col" style="margin-bottom: 20px;">
				<div class="yuk-ai-checkbox-item">
					<div style="display: flex; justify-content: space-between; align-items: center;">
						<strong><?php esc_html_e( 'Enable Auto-Failover Router', 'yukdigitalz-connector-for-google-ai' ); ?></strong>
						<label class="yuk-ai-switch">
							<input type="checkbox" name="enable_failover" value="1" <?php checked( $yukdiconfo_enable_failover ); ?>>
							<span class="yuk-ai-slider"></span>
						</label>
					</div>
					<p><?php esc_html_e( 'Automatically reroute requests to fallback models when primary model fails.', 'yukdigitalz-connector-for-google-ai' ); ?></p>
				</div>

				<div class="yuk-ai-checkbox-item">
					<div style="display: flex; justify-content: space-between; align-items: center;">
						<strong><?php esc_html_e( 'Enable Third-Party Interceptor', 'yukdigitalz-connector-for-google-ai' ); ?></strong>
						<label class="yuk-ai-switch">
							<input type="checkbox" name="enable_third_party_interceptor" value="1" <?php checked( $yukdiconfo_settings->get( 'enable_third_party_interceptor', true ) ); ?>>
							<span class="yuk-ai-slider"></span>
						</label>
					</div>
					<p><?php esc_html_e( 'Intercept external AI requests (AI Chatbots, WordPress Connectors, 3rd party plugins) & reroute via Failover.', 'yukdigitalz-connector-for-google-ai' ); ?></p>

				</div>

				<div class="yuk-ai-checkbox-item">
					<div style="display: flex; justify-content: space-between; align-items: center;">
						<strong><?php esc_html_e( 'Enable Exponential Backoff Retry', 'yukdigitalz-connector-for-google-ai' ); ?></strong>
						<label class="yuk-ai-switch">
							<input type="checkbox" name="enable_auto_retry" value="1" <?php checked( $yukdiconfo_enable_auto_retry ); ?>>
							<span class="yuk-ai-slider"></span>
						</label>
					</div>
					<p><?php esc_html_e( 'Attempt short retry delays before switching to the next fallback model.', 'yukdigitalz-connector-for-google-ai' ); ?></p>
				</div>

				<div class="yuk-ai-checkbox-item">
					<div style="display: flex; justify-content: space-between; align-items: center;">
						<strong><?php esc_html_e( 'Enable Circuit Breaker Protection', 'yukdigitalz-connector-for-google-ai' ); ?></strong>
						<label class="yuk-ai-switch">
							<input type="checkbox" name="enable_circuit_breaker" value="1" <?php checked( $yukdiconfo_settings->get( 'enable_circuit_breaker', true ) ); ?>>
							<span class="yuk-ai-slider"></span>
						</label>
					</div>
					<p><?php esc_html_e( 'Put overloaded models (503/429) on transient cooldown to skip failed attempts instantly.', 'yukdigitalz-connector-for-google-ai' ); ?></p>
				</div>
			</div>


			<!-- Error Triggers -->
			<div class="yuk-ai-form-group">
				<label><strong><?php esc_html_e( 'Trigger Failover On:', 'yukdigitalz-connector-for-google-ai' ); ?></strong></label>
				<div class="yuk-ai-grid yuk-ai-grid-2col" style="margin-top: 10px;">
					<label class="yuk-ai-checkbox-item">
						<input type="checkbox" name="failover_on_503" value="1" <?php checked( $yukdiconfo_settings->get( 'failover_on_503', true ) ); ?>>
						<strong><?php esc_html_e( 'HTTP 503 (Service Unavailable / High Demand)', 'yukdigitalz-connector-for-google-ai' ); ?></strong>
						<p><?php esc_html_e( 'Triggered when Google AI servers report capacity limits.', 'yukdigitalz-connector-for-google-ai' ); ?></p>
					</label>

					<label class="yuk-ai-checkbox-item">
						<input type="checkbox" name="failover_on_429" value="1" <?php checked( $yukdiconfo_settings->get( 'failover_on_429', true ) ); ?>>
						<strong><?php esc_html_e( 'HTTP 429 (Rate Limit / Quota Exceeded)', 'yukdigitalz-connector-for-google-ai' ); ?></strong>
						<p><?php esc_html_e( 'Triggered when free tier or per-minute API key limits are reached.', 'yukdigitalz-connector-for-google-ai' ); ?></p>
					</label>

					<label class="yuk-ai-checkbox-item">
						<input type="checkbox" name="failover_on_500" value="1" <?php checked( $yukdiconfo_settings->get( 'failover_on_500', true ) ); ?>>
						<strong><?php esc_html_e( 'HTTP 500 (Internal Server Error)', 'yukdigitalz-connector-for-google-ai' ); ?></strong>
						<p><?php esc_html_e( 'Triggered on transient internal API failures.', 'yukdigitalz-connector-for-google-ai' ); ?></p>
					</label>

					<label class="yuk-ai-checkbox-item">
						<input type="checkbox" name="failover_on_timeout" value="1" <?php checked( $yukdiconfo_settings->get( 'failover_on_timeout', true ) ); ?>>
						<strong><?php esc_html_e( 'cURL Request Timeout', 'yukdigitalz-connector-for-google-ai' ); ?></strong>
						<p><?php esc_html_e( 'Triggered if Google API takes longer than configured timeout.', 'yukdigitalz-connector-for-google-ai' ); ?></p>
					</label>
				</div>
			</div>
		</div>
	</div>

	<!-- Fallback Hierarchy Ordering Card -->
	<div class="yuk-ai-card">
		<div class="yuk-ai-card-header">
			<h2><span class="dashicons dashicons-sort"></span> <?php esc_html_e( 'Fallback Model Hierarchy Ordering', 'yukdigitalz-connector-for-google-ai' ); ?></h2>
		</div>
		<div class="yuk-ai-card-body">
			<p class="description" style="margin-bottom: 15px;">
				<?php esc_html_e( 'Define the sequence of backup models to attempt if the primary model is unresponsive. The router will try each model from top to bottom before returning an error.', 'yukdigitalz-connector-for-google-ai' ); ?>
			</p>

			<div id="yuk-ai-fallback-list" class="yuk-ai-sortable-list">
				<?php if ( ! empty( $yukdiconfo_fallback_models ) && is_array( $yukdiconfo_fallback_models ) ) : ?>
					<?php foreach ( $yukdiconfo_fallback_models as $yukdiconfo_f_idx => $yukdiconfo_f_model ) : ?>
						<div class="yuk-ai-fallback-row">
							<span class="yuk-ai-drag-handle dashicons dashicons-menu"></span>
							<span class="yuk-ai-fallback-priority">#<?php echo esc_html( $yukdiconfo_f_idx + 1 ); ?></span>
							<select name="fallback_models[]" class="yuk-ai-select-lg" style="flex-grow: 1;">
								<?php foreach ( $yukdiconfo_all_models as $yukdiconfo_m_id => $yukdiconfo_m_info ) : ?>
									<?php $yukdiconfo_m_label = is_array( $yukdiconfo_m_info ) && isset( $yukdiconfo_m_info['name'] ) ? $yukdiconfo_m_info['name'] : ( is_string( $yukdiconfo_m_info ) ? $yukdiconfo_m_info : $yukdiconfo_m_id ); ?>
									<option value="<?php echo esc_attr( $yukdiconfo_m_id ); ?>" <?php selected( $yukdiconfo_f_model, $yukdiconfo_m_id ); ?>><?php echo esc_html( $yukdiconfo_m_label ); ?> (<?php echo esc_html( $yukdiconfo_m_id ); ?>)</option>
								<?php endforeach; ?>
							</select>
							<button type="button" class="button button-link-delete yuk-ai-btn-remove-fallback">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="yuk-ai-fallback-row">
						<span class="yuk-ai-drag-handle dashicons dashicons-menu"></span>
						<span class="yuk-ai-fallback-priority">#1</span>
						<select name="fallback_models[]" class="yuk-ai-select-lg" style="flex-grow: 1;">
							<?php foreach ( $yukdiconfo_all_models as $yukdiconfo_m_id => $yukdiconfo_m_info ) : ?>
								<?php $yukdiconfo_m_label = is_array( $yukdiconfo_m_info ) && isset( $yukdiconfo_m_info['name'] ) ? $yukdiconfo_m_info['name'] : ( is_string( $yukdiconfo_m_info ) ? $yukdiconfo_m_info : $yukdiconfo_m_id ); ?>
								<option value="<?php echo esc_attr( $yukdiconfo_m_id ); ?>" <?php selected( 'gemini-3.7-flash', $yukdiconfo_m_id ); ?>><?php echo esc_html( $yukdiconfo_m_label ); ?> (<?php echo esc_html( $yukdiconfo_m_id ); ?>)</option>
							<?php endforeach; ?>
						</select>
							<button type="button" class="button button-link-delete yuk-ai-btn-remove-fallback">
								<span class="dashicons dashicons-trash"></span>
							</button>
					</div>
				<?php endif; ?>

			</div>

			<button type="button" id="yuk-ai-btn-add-fallback" class="button button-secondary" style="margin-top: 15px;">
				<span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e( 'Add Fallback Model Level', 'yukdigitalz-connector-for-google-ai' ); ?>
			</button>
		</div>
	</div>

	<!-- Circuit Breaker & Cooldown Tuning -->
	<div class="yuk-ai-card">
		<div class="yuk-ai-card-header">
			<h2><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Circuit Breaker Cooldown & Performance Tuning', 'yukdigitalz-connector-for-google-ai' ); ?></h2>
		</div>
		<div class="yuk-ai-card-body">
			<div class="yuk-ai-grid yuk-ai-grid-2col">
				<div class="yuk-ai-form-group">
					<label for="cooldown_duration_sec"><strong><?php esc_html_e( 'Model Cooldown Duration (seconds)', 'yukdigitalz-connector-for-google-ai' ); ?></strong></label>
					<input type="number" name="cooldown_duration_sec" id="cooldown_duration_sec" class="regular-text" value="<?php echo esc_attr( $yukdiconfo_settings->get( 'cooldown_duration_sec', 120 ) ); ?>" min="10" max="1800">
					<p class="description"><?php esc_html_e( 'Default: 120 seconds. When a model returns Error 503, it is marked "cooling down" in transients to skip ping delays for subsequent requests.', 'yukdigitalz-connector-for-google-ai' ); ?></p>
				</div>

				<div class="yuk-ai-form-group">
					<label for="request_timeout_sec"><strong><?php esc_html_e( 'HTTP Request Timeout (seconds)', 'yukdigitalz-connector-for-google-ai' ); ?></strong></label>
					<input type="number" name="request_timeout_sec" id="request_timeout_sec" class="regular-text" value="<?php echo esc_attr( $yukdiconfo_settings->get( 'request_timeout_sec', 30 ) ); ?>" min="5" max="120">
					<p class="description"><?php esc_html_e( 'Default: 30 seconds. Maximum waiting time for Google AI Studio response before failing over.', 'yukdigitalz-connector-for-google-ai' ); ?></p>
				</div>
			</div>
		</div>
	</div>

	<!-- Save Button -->
	<div class="yuk-ai-input-with-button" style="margin-top: 20px;">
		<button type="submit" class="button button-primary button-hero" id="yuk-ai-btn-save-failover">
			<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Failover Policy', 'yukdigitalz-connector-for-google-ai' ); ?>
		</button>
		<span class="spinner" id="yuk-ai-save-failover-spinner"></span>
	</div>
</form>
