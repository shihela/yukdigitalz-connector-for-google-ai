<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$yukdiconfo_current_tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$yukdiconfo_settings     = \YukDigitalz\AIConnectorGoogle\Settings::get_instance();
$yukdiconfo_active_model = $yukdiconfo_settings->get_effective_model();
$yukdiconfo_gemini_key   = $yukdiconfo_settings->get_gemini_api_key();
$yukdiconfo_is_key_set   = ! empty( $yukdiconfo_gemini_key );
?>

<div class="wrap yuk-ai-wrap">
	<!-- Hidden H1 & wp-header-end for WordPress notice placement isolation -->
	<h1 class="wp-heading-inline" style="display:none;"><?php esc_html_e( 'YukDigitalz Connector for Google AI', 'yukdigitalz-connector-for-google-ai' ); ?></h1>
	<hr class="wp-header-end" style="display:none;">

	<!-- Top Hero Header & Branding -->
	<div class="yuk-ai-header">
		<div class="yuk-ai-brand">
			<div class="yuk-ai-logo-badge">
				<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M12 2L14.5 9.5L22 12L14.5 14.5L12 22L9.5 14.5L2 12L9.5 9.5L12 2Z" fill="currentColor"/>
				</svg>
			</div>
			<div class="yuk-ai-brand-info">
				<h2 style="color: #ffffff !important; font-size: 22px !important; font-weight: 700 !important; margin: 0 !important; padding: 0 !important; display: inline-flex !important; align-items: center !important; gap: 10px !important;">
					<?php esc_html_e( 'YukDigitalz Connector for Google AI', 'yukdigitalz-connector-for-google-ai' ); ?>
					<span class="yuk-ai-version-tag">v1.0.0</span>
				</h2>
				<p class="yuk-ai-tagline" style="color: #94a3b8 !important; margin: 6px 0 0 0 !important;"><?php esc_html_e( 'Google AI Studio & Gemini API Engine with Intelligent Auto-Failover & Key Rotation', 'yukdigitalz-connector-for-google-ai' ); ?></p>
			</div>
		</div>

		<div class="yuk-ai-status-pill-group">
			<div class="yuk-ai-pill <?php echo esc_attr( $yukdiconfo_is_key_set ? 'yuk-ai-pill-active' : 'yuk-ai-pill-warning' ); ?>">
				<span class="yuk-ai-dot"></span>
				<span>
					<?php
					if ( $yukdiconfo_is_key_set ) {
						esc_html_e( 'Gemini API Connected', 'yukdigitalz-connector-for-google-ai' );
					} else {
						esc_html_e( 'API Key Required', 'yukdigitalz-connector-for-google-ai' );
					}
					?>
				</span>
			</div>
			<div class="yuk-ai-pill yuk-ai-pill-model">
				<span class="dashicons dashicons-admin-generic"></span>
				<span><?php echo esc_html( $yukdiconfo_active_model ); ?></span>
			</div>
		</div>
	</div>

	<!-- Main Navigation Tabs -->
	<h2 class="nav-tab-wrapper yuk-ai-nav-tabs">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=yukdiconfo&tab=overview' ) ); ?>" class="nav-tab <?php echo esc_attr( 'overview' === $yukdiconfo_current_tab ? 'nav-tab-active' : '' ); ?>">
			<span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Overview & Status', 'yukdigitalz-connector-for-google-ai' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=yukdiconfo&tab=providers' ) ); ?>" class="nav-tab <?php echo esc_attr( 'providers' === $yukdiconfo_current_tab ? 'nav-tab-active' : '' ); ?>">
			<span class="dashicons dashicons-cloud"></span> <?php esc_html_e( 'Gemini API & Models', 'yukdigitalz-connector-for-google-ai' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=yukdiconfo&tab=failover' ) ); ?>" class="nav-tab <?php echo esc_attr( 'failover' === $yukdiconfo_current_tab ? 'nav-tab-active' : '' ); ?>">
			<span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Auto-Failover & Cooldown', 'yukdigitalz-connector-for-google-ai' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=yukdiconfo&tab=logs' ) ); ?>" class="nav-tab <?php echo esc_attr( 'logs' === $yukdiconfo_current_tab ? 'nav-tab-active' : '' ); ?>">
			<span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Telemetry & Logs', 'yukdigitalz-connector-for-google-ai' ); ?>
		</a>
	</h2>

	<div class="yuk-ai-tab-content-wrapper">
