/**
 * YukDigitalz Connector for Google AI Admin Scripts
 * Interactive AJAX controls, password mask toggler, dynamic Gemini model fetcher,
 * sortable fallbacks, XSS safe DOM rendering, & key rotation handlers.
 */

(function ($) {
	'use strict';

	$(document).ready(function () {
		const data = window.yukdiconfoAdminData || {};

		// Helper: Safe HTML Escaping (XSS Prevention)
		function escapeHtml(str) {
			if (str === null || str === undefined) {
				return '';
			}
			return $('<div>').text(String(str)).html();
		}

		// Helper: Toast notification
		function showToast(message, isError) {
			$('.yuk-ai-toast').stop(true, true).remove();

			const $toast = $('<div class="yuk-ai-toast"></div>')
				.text(message)
				.css({
					position: 'fixed',
					bottom: '30px',
					right: '30px',
					background: isError ? '#ef4444' : '#10b981',
					color: '#fff',
					padding: '12px 20px',
					borderRadius: '8px',
					boxShadow: '0 10px 15px -3px rgba(0,0,0,0.25)',
					zIndex: 999999,
					fontWeight: '600',
					fontSize: '14px',
					display: 'none',
				});

			$('body').append($toast);
			$toast.fadeIn(200).delay(3200).fadeOut(300, function () {
				$(this).remove();
			});
		}

		// 1. Password Visibility Eye Icon Toggle (.yuk-ai-btn-toggle-mask)
		$(document).on('click', '.yuk-ai-btn-toggle-mask', function (e) {
			e.preventDefault();
			const targetId = $(this).data('target');
			const $input = targetId ? $('#' + targetId) : $(this).siblings('input[type="password"], input[type="text"]');
			const $icon = $(this).find('.dashicons');

			if ($input.length) {
				if ($input.attr('type') === 'password') {
					$input.attr('type', 'text');
					$icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
				} else {
					$input.attr('type', 'password');
					$icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
				}
			}
		});

		// 2. Reindex Fallback Priorities & Sortable Initialization
		function reindexFallbackPriorities() {
			$('#yuk-ai-fallback-list .yuk-ai-fallback-row').each(function (idx) {
				$(this).find('.yuk-ai-fallback-priority').text('#' + (idx + 1));
			});
		}

		if ($.fn.sortable && $('#yuk-ai-fallback-list').length) {
			$('#yuk-ai-fallback-list').sortable({
				handle: '.yuk-ai-drag-handle',
				axis: 'y',
				cursor: 'move',
				placeholder: 'yuk-ai-sortable-placeholder',
				update: function () {
					reindexFallbackPriorities();
				}
			});
		}

		// 3. Add & Remove Backup API Keys (#yuk-ai-btn-add-backup-key)
		$(document).on('click', '#yuk-ai-btn-add-backup-key', function (e) {
			e.preventDefault();
			const rowHtml = `
				<div class="yuk-ai-fallback-row">
					<span class="yuk-ai-drag-handle dashicons dashicons-menu"></span>
					<input type="password" name="gemini_backup_keys[]" class="regular-text yuk-ai-secret-input yuk-ai-input-code" value="" placeholder="AIzaSy..." autocomplete="off">
					<button type="button" class="button button-link-delete yuk-ai-btn-remove-key">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>
			`;
			$('#yuk-ai-backup-keys-list').append(rowHtml);
		});

		$(document).on('click', '.yuk-ai-btn-remove-key', function (e) {
			e.preventDefault();
			const $list = $('#yuk-ai-backup-keys-list');
			if ($list.find('.yuk-ai-fallback-row').length > 1) {
				$(this).closest('.yuk-ai-fallback-row').remove();
			} else {
				$(this).closest('.yuk-ai-fallback-row').find('input').val('');
			}
		});

		// 4. Test Google Gemini API Key (#yuk-ai-btn-test-key)
		$(document).on('click', '#yuk-ai-btn-test-key', function (e) {
			e.preventDefault();
			const $btn = $(this);
			const apiKey = $('#gemini_api_key').val() || '';

			$btn.prop('disabled', true);
			const originalHtml = $btn.html();
			$btn.html('<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span> ' + (escapeHtml(data.strings ? data.strings.testing : '') || 'Testing...'));

			$.ajax({
				url: data.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'yukdiconfo_test_key',
					nonce: data.nonce,
					provider: 'gemini',
					api_key: apiKey,
				},
				success: function (res) {
					$btn.prop('disabled', false).html(originalHtml);
					if (res.success) {
						showToast(res.data.message || 'Google Gemini API Connection Valid & Active!');
					} else {
						showToast(res.data.message || 'Failed to verify Google Gemini API Key', true);
					}
				},
				error: function (xhr) {
					$btn.prop('disabled', false).html(originalHtml);
					let errMsg = 'Failed to connect to WordPress server.';
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errMsg = xhr.responseJSON.data.message;
					}
					showToast(errMsg, true);
				}
			});
		});

		// 5. Fetch Dynamic Models from Google AI API (#yuk-ai-btn-fetch-models)
		$(document).on('click', '#yuk-ai-btn-fetch-models', function (e) {
			e.preventDefault();
			const $btn = $(this);
			const apiKey = $('#gemini_api_key').val() || '';

			$btn.prop('disabled', true);
			const originalHtml = $btn.html();
			$btn.html('<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span> ' + (escapeHtml(data.strings ? data.strings.fetching : '') || 'Fetching...'));

			$.ajax({
				url: data.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'yukdiconfo_fetch_models',
					nonce: data.nonce,
					provider: 'gemini',
					api_key: apiKey,
				},
				success: function (res) {
					$btn.prop('disabled', false).html(originalHtml);
					if (res.success) {
						showToast(res.data.message);
						setTimeout(function () {
							window.location.reload();
						}, 1200);
					} else {
						showToast('Failed to fetch models: ' + (res.data.message || 'Unknown error'), true);
					}
				},
				error: function (xhr) {
					$btn.prop('disabled', false).html(originalHtml);
					let errMsg = 'Failed to connect to server.';
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errMsg = xhr.responseJSON.data.message;
					}
					showToast('Error: ' + errMsg, true);
				}
			});
		});

		// 6. Interactive Model Card Radio Selection
		$(document).on('click', '.yuk-ai-model-card', function () {
			$('.yuk-ai-model-card').removeClass('yuk-ai-model-card-active');
			$(this).addClass('yuk-ai-model-card-active');
			$(this).find('input[type="radio"]').prop('checked', true).trigger('change');
		});

		// 7. Add & Remove Fallback Hierarchy Level (#yuk-ai-btn-add-fallback)
		$(document).on('click', '#yuk-ai-btn-add-fallback', function (e) {
			e.preventDefault();
			const $list = $('#yuk-ai-fallback-list');
			const count = $list.find('.yuk-ai-fallback-row').length + 1;
			const $firstSelect = $list.find('.yuk-ai-fallback-row:first select');

			let optionsHtml = '';
			if ($firstSelect.length) {
				optionsHtml = $firstSelect.html();
			} else {
				optionsHtml = '<option value="gemini-2.5-flash">Gemini 2.5 Flash (gemini-2.5-flash)</option><option value="gemini-3.7-flash">Gemini 3.7 Flash (gemini-3.7-flash)</option>';
			}

			const rowHtml = `
				<div class="yuk-ai-fallback-row">
					<span class="yuk-ai-drag-handle dashicons dashicons-menu"></span>
					<span class="yuk-ai-fallback-priority">#${count}</span>
					<select name="fallback_models[]" class="yuk-ai-select-lg" style="flex-grow: 1;">
						${optionsHtml}
					</select>
					<button type="button" class="button button-link-delete yuk-ai-btn-remove-fallback">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</div>
			`;

			$list.append(rowHtml);
			reindexFallbackPriorities();
		});

		$(document).on('click', '.yuk-ai-btn-remove-fallback', function (e) {
			e.preventDefault();
			const $list = $('#yuk-ai-fallback-list');
			if ($list.find('.yuk-ai-fallback-row').length > 1) {
				$(this).closest('.yuk-ai-fallback-row').remove();
				reindexFallbackPriorities();
			}
		});

		// 8. Save Settings AJAX Forms (#yuk-ai-settings-form-providers & #yuk-ai-settings-form-failover)
		$(document).on('submit', '#yuk-ai-settings-form-providers, #yuk-ai-settings-form-failover', function (e) {
			e.preventDefault();
			const $form = $(this);
			const $btn = $form.find('button[type="submit"]');
			const $spinner = $form.find('.spinner');

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');

			const formData = $form.serializeArray();
			formData.push({ name: 'action', value: 'yukdiconfo_save_settings' });
			formData.push({ name: 'nonce', value: data.nonce });

			$.ajax({
				url: data.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: formData,
				success: function (res) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					if (res.success) {
						showToast(data.strings ? data.strings.saved : 'Settings saved successfully!');
					} else {
						showToast(res.data.message || 'Save failed', true);
					}
				},
				error: function () {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					showToast('Connection error. Could not save settings.', true);
				}
			});
		});

		// 9. Clear Telemetry Audit Logs (#yuk-ai-btn-clear-logs)
		$(document).on('click', '#yuk-ai-btn-clear-logs', function (e) {
			e.preventDefault();
			if (!confirm(data.strings ? data.strings.confirmClear : 'Clear all telemetry logs?')) {
				return;
			}

			const $btn = $(this);
			$btn.prop('disabled', true);

			$.ajax({
				url: data.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'yukdiconfo_clear_logs',
					nonce: data.nonce,
				},
				success: function (res) {
					if (res.success) {
						showToast(res.data.message || 'Logs cleared');
						setTimeout(function () { window.location.reload(); }, 800);
					} else {
						showToast(res.data.message || 'Failed to clear logs', true);
						$btn.prop('disabled', false);
					}
				},
				error: function () {
					showToast('Failed to clear logs.', true);
					$btn.prop('disabled', false);
				}
			});
		});

		// 10. Live Connection Playground Runner (#yuk-ai-btn-quick-test)
		$(document).on('click', '#yuk-ai-btn-quick-test', function (e) {
			e.preventDefault();
			const $btn = $(this);
			const prompt = $('#yuk-ai-playground-prompt').val() || '';
			const $resultBox = $('#yuk-ai-playground-result');
			const $modelBadge = $('#yuk-ai-res-model-badge');
			const $latencyBadge = $('#yuk-ai-res-latency-badge');
			const $resText = $('#yuk-ai-res-text');
			const $spinner = $('#yuk-ai-playground-spinner');

			if (!prompt.trim()) {
				showToast('Please enter a prompt first.', true);
				return;
			}

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');

			$.ajax({
				url: data.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'yukdiconfo_playground_generate',
					nonce: data.nonce,
					prompt: prompt,
				},
				success: function (res) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					if (res.success) {
						$modelBadge.text('Model: ' + (res.data.resolved_model || 'gemini-3.7-flash'));
						$latencyBadge.text((res.data.total_latency_ms || 0) + ' ms');
						$resText.text(res.data.text || '(No text returned)');
						$resultBox.fadeIn();
						showToast('Gemini Response Received!');

						if (res.data.stats) {
							$('.yuk-ai-metric-card:eq(0) .yuk-ai-metric-value').text(res.data.stats.total_requests);
							$('.yuk-ai-metric-card:eq(1) .yuk-ai-metric-value').text(res.data.stats.success_rate + '%');
							$('.yuk-ai-metric-card:eq(2) .yuk-ai-metric-value').text(res.data.stats.failover_count);
							$('.yuk-ai-metric-card:eq(3) .yuk-ai-metric-value').html(res.data.stats.avg_latency_ms + ' <small>ms</small>');
						}
					} else {
						showToast('Test failed: ' + (res.data.message || 'Unknown error'), true);
					}
				},
				error: function (xhr) {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
					let errMsg = 'Server connection error occurred.';
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errMsg = xhr.responseJSON.data.message;
					}
					showToast('Error: ' + errMsg, true);
				}
			});
		});
	});
})(jQuery);
