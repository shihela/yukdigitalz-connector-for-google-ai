<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$yukdiconfo_settings        = \YukDigitalz\AIConnectorGoogle\Settings::get_instance();
$yukdiconfo_gemini_key      = $yukdiconfo_settings->get_gemini_api_key();
$yukdiconfo_backup_keys     = $yukdiconfo_settings->get( 'gemini_backup_keys', array() );
$yukdiconfo_primary_model   = $yukdiconfo_settings->get( 'primary_model', 'gemini-2.5-flash' );
$yukdiconfo_custom_model_id = $yukdiconfo_settings->get( 'custom_model_id', '' );
$yukdiconfo_dynamic_models  = $yukdiconfo_settings->get_dynamic_models();
$yukdiconfo_last_fetched    = $yukdiconfo_settings->get( 'models_last_fetched', 0 );
?>

<form id="yuk-ai-settings-form-providers">
	<input type="hidden" name="form_type" value="providers">

	<!-- Section 1: API Keys & Rotation Pool -->
	<div class="yuk-ai-card">
		<div class="yuk-ai-card-header">
			<h2><span class="dashicons dashicons-key"></span> <?php esc_html_e( 'Google AI Studio API Keys', 'yukdigitalz-connector-for-google-ai' ); ?></h2>
		</div>
		<div class="yuk-ai-card-body">
			<div class="yuk-ai-notice-inline yuk-ai-notice-info">
				<span class="dashicons dashicons-lock"></span>
				<div>
					<strong><?php esc_html_e( 'Enterprise Encryption Active', 'yukdigitalz-connector-for-google-ai' ); ?></strong>
					<p><?php esc_html_e( 'API Keys are encrypted with OpenSSL AES-256-CBC prior to database storage. You can also define YUKDICONFO_API_KEY in wp-config.php.', 'yukdigitalz-connector-for-google-ai' ); ?></p>
				</div>
			</div>

			<div class="yuk-ai-providers-accordion">
				<div class="yuk-ai-provider-box <?php echo esc_attr( ! empty( $yukdiconfo_gemini_key ) ? 'yuk-ai-box-connected' : '' ); ?>">
					<div class="yuk-ai-provider-box-header">
						<div class="yuk-ai-provider-title">
							<span class="dashicons dashicons-admin-network"></span>
							<span><?php esc_html_e( 'Primary Gemini API Key', 'yukdigitalz-connector-for-google-ai' ); ?></span>
						</div>
						<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer" class="button button-link">
							Google AI Studio <span class="dashicons dashicons-external"></span>
						</a>
					</div>
					<div class="yuk-ai-provider-box-body">
						<div class="yuk-ai-input-with-button">
							<input type="password" name="gemini_api_key" id="gemini_api_key" class="regular-text yuk-ai-secret-input yuk-ai-input-code" value="<?php echo esc_attr( $yukdiconfo_settings->get_masked_key() ); ?>" placeholder="AIzaSy..." autocomplete="off">
							<button type="button" class="button yuk-ai-btn-toggle-mask" data-target="gemini_api_key">
								<span class="dashicons dashicons-visibility"></span>
							</button>
							<button type="button" id="yuk-ai-btn-test-key" class="button button-secondary">
								<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Test API Key', 'yukdigitalz-connector-for-google-ai' ); ?>
							</button>
						</div>
					</div>
				</div>

				<div class="yuk-ai-provider-box">
					<div class="yuk-ai-provider-box-header">
						<div class="yuk-ai-provider-title">
							<span class="dashicons dashicons-update"></span>
							<span><?php esc_html_e( 'Backup Keys (Quota Rotation Pool)', 'yukdigitalz-connector-for-google-ai' ); ?></span>
						</div>
					</div>
					<div class="yuk-ai-provider-box-body">
						<p class="description" style="margin-bottom: 12px;">
							<?php esc_html_e( 'When 429 quota/rate limit errors occur, the failover router automatically rotates to the next available API key in this pool.', 'yukdigitalz-connector-for-google-ai' ); ?>
						</p>

						<div id="yuk-ai-backup-keys-list" class="yuk-ai-sortable-list">
							<?php if ( ! empty( $yukdiconfo_backup_keys ) && is_array( $yukdiconfo_backup_keys ) ) : ?>
								<?php foreach ( $yukdiconfo_backup_keys as $yukdiconfo_idx => $yukdiconfo_bk_enc ) : ?>
									<?php
									$yukdiconfo_raw_key = is_string( $yukdiconfo_bk_enc ) ? \YukDigitalz\AIConnectorGoogle\Security::decrypt( $yukdiconfo_bk_enc ) : ( is_scalar( $yukdiconfo_bk_enc ) ? (string) $yukdiconfo_bk_enc : '' );
									$yukdiconfo_masked  = \YukDigitalz\AIConnectorGoogle\Security::mask_api_key( $yukdiconfo_raw_key );
									?>
									<div class="yuk-ai-fallback-row">
										<span class="yuk-ai-drag-handle dashicons dashicons-menu"></span>
										<input type="password" name="gemini_backup_keys[]" class="regular-text yuk-ai-secret-input yuk-ai-input-code" value="<?php echo esc_attr( $yukdiconfo_masked ); ?>" placeholder="AIzaSy..." autocomplete="off">
										<button type="button" class="button button-link-delete yuk-ai-btn-remove-key">
											<span class="dashicons dashicons-trash"></span>
										</button>
									</div>
								<?php endforeach; ?>
							<?php else : ?>
								<div class="yuk-ai-fallback-row">
									<span class="yuk-ai-drag-handle dashicons dashicons-menu"></span>
									<input type="password" name="gemini_backup_keys[]" class="regular-text yuk-ai-secret-input yuk-ai-input-code" value="" placeholder="AIzaSy..." autocomplete="off">
									<button type="button" class="button button-link-delete yuk-ai-btn-remove-key">
										<span class="dashicons dashicons-trash"></span>
									</button>
								</div>
							<?php endif; ?>
						</div>

						<button type="button" id="yuk-ai-btn-add-backup-key" class="button button-secondary" style="margin-top: 12px;">
							<span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e( 'Add Another Backup Key', 'yukdigitalz-connector-for-google-ai' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Section 2: Model Configuration & Interactive Model Cards -->
	<div class="yuk-ai-card">
		<div class="yuk-ai-card-header">
			<h2><span class="dashicons dashicons-cloud"></span> <?php esc_html_e( 'Google Gemini AI Models', 'yukdigitalz-connector-for-google-ai' ); ?></h2>
			<div class="yuk-ai-card-actions">
				<button type="button" id="yuk-ai-btn-fetch-models" class="button button-secondary">
					<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Fetch Latest Models from Google', 'yukdigitalz-connector-for-google-ai' ); ?>
				</button>
			</div>
		</div>
		<div class="yuk-ai-card-body">
			<?php if ( ! empty( $yukdiconfo_last_fetched ) ) : ?>
				<div class="yuk-ai-notice-inline yuk-ai-notice-info">
					<span class="dashicons dashicons-calendar-alt"></span>
					<?php
					printf(
						/* translators: 1: time ago string, 2: number of models */
						esc_html__( 'Last synced with Google AI Studio: %1$s ago (%2$d models available).', 'yukdigitalz-connector-for-google-ai' ),
						esc_html( human_time_diff( $yukdiconfo_last_fetched, time() ) ),
						count( $yukdiconfo_dynamic_models )
					);
					?>

				</div>
			<?php endif; ?>

			<p class="description" style="margin-bottom: 15px;">
				<?php esc_html_e( 'Select the primary model for all WordPress AI generations:', 'yukdigitalz-connector-for-google-ai' ); ?>
			</p>

			<!-- Interactive Model Cards Grid -->
			<div class="yuk-ai-models-grid">
				<?php
				$yukdiconfo_known_models = yukdiconfo_get_all_models();
				foreach ( $yukdiconfo_known_models as $yukdiconfo_m_id => $yukdiconfo_m_info ) :
					$yukdiconfo_m_name    = is_array( $yukdiconfo_m_info ) && isset( $yukdiconfo_m_info['name'] ) ? $yukdiconfo_m_info['name'] : ( is_string( $yukdiconfo_m_info ) ? $yukdiconfo_m_info : $yukdiconfo_m_id );
					$yukdiconfo_is_active = ( $yukdiconfo_m_id === $yukdiconfo_primary_model );
					$yukdiconfo_is_flash  = ( strpos( $yukdiconfo_m_id, 'flash' ) !== false );
					?>

					<label class="yuk-ai-model-card <?php echo esc_attr( $yukdiconfo_is_active ? 'yuk-ai-model-card-active' : '' ); ?>">
						<div class="yuk-ai-model-card-top">
							<span class="yuk-ai-model-pill"><?php echo esc_html( $yukdiconfo_is_flash ? 'FAST / LIGHTWEIGHT' : 'HIGH REASONING' ); ?></span>
							<?php if ( $yukdiconfo_is_active ) : ?>
								<span class="yuk-ai-speed-tag"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'ACTIVE', 'yukdigitalz-connector-for-google-ai' ); ?></span>
							<?php endif; ?>
						</div>

						<div style="display: flex; align-items: flex-start; gap: 10px; margin-top: 6px;">
							<input type="radio" name="primary_model" value="<?php echo esc_attr( $yukdiconfo_m_id ); ?>" <?php checked( $yukdiconfo_primary_model, $yukdiconfo_m_id ); ?> style="margin-top: 3px;">
							<div>
								<div class="yuk-ai-model-card-title"><?php echo esc_html( $yukdiconfo_m_name ); ?></div>
								<div class="yuk-ai-model-card-id"><code><?php echo esc_html( $yukdiconfo_m_id ); ?></code></div>
							</div>
						</div>
					</label>
				<?php endforeach; ?>
			</div>

			<!-- Custom Model ID Field -->
			<div class="yuk-ai-form-group" style="margin-top: 25px;">
				<label for="custom_model_id"><strong><?php esc_html_e( 'Custom / Fine-Tuned Model ID', 'yukdigitalz-connector-for-google-ai' ); ?></strong></label>
				<input type="text" name="custom_model_id" id="custom_model_id" class="large-text yuk-ai-input-code" value="<?php echo esc_attr( $yukdiconfo_custom_model_id ); ?>" placeholder="e.g. tunedModels/my-custom-gemini-123">
				<p class="description"><?php esc_html_e( 'If you have a fine-tuned model or custom deployment ID from Google Vertex AI / AI Studio, enter it here to override standard models.', 'yukdigitalz-connector-for-google-ai' ); ?></p>
			</div>
		</div>
	</div>

	<!-- Save Button -->
	<div class="yuk-ai-input-with-button" style="margin-top: 20px;">
		<button type="submit" class="button button-primary button-hero" id="yuk-ai-btn-save-providers">
			<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Gemini Settings', 'yukdigitalz-connector-for-google-ai' ); ?>
		</button>
		<span class="spinner" id="yuk-ai-save-spinner"></span>
	</div>
</form>
