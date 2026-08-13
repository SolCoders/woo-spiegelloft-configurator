(function ($) {
	'use strict';

	function money(value) {
		var formatted = Number(value || 0).toFixed(2);
		return $('.woocommerce-Price-currencySymbol').first().text() + formatted;
	}

	function escapeHtml(value) {
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function collect($wrap) {
		var selections = {};
		var total = parseFloat($wrap.data('base-price')) || 0;

		$wrap.find('.wcs-dimension-input').each(function () {
			var key = $(this).data('key');
			var value = parseFloat($(this).val());
			if (key && !isNaN(value)) {
				selections[key] = value;
			}
		});

		$wrap.find('.wcs-customer-field-input').each(function () {
			var key = $(this).data('key');
			var value = $(this).val();
			if (key && value !== '') {
				selections[key] = value;
				if ($(this).is('select')) {
					total += parseFloat($(this).find(':selected').data('price')) || 0;
				} else {
					total += parseFloat($(this).data('price')) || 0;
				}
			}
		});

		$wrap.find('.wcs-choice-select').each(function () {
			var $select = $(this);
			var group = $select.data('group');
			var value = $select.val();
			var price = parseFloat($select.find(':selected').data('price')) || 0;

			if (group && value) {
				selections[group] = value;
				total += price;
			}
		});

		$wrap.find('.wcs-position-choice').each(function () {
			var group = $(this).data('group');
			var value = $(this).val();
			if (group && value) {
				selections[group + '_position'] = value;
			}
		});

		$wrap.find('.wcs-selections-input').val(JSON.stringify(selections));
		$wrap.find('.wcs-configurator__price').text(money(total));
		renderReview($wrap, total);
	}

	function cleanOptionText(text) {
		return String(text || '').replace(/\s+\+.*/, '').replace(/\s+/g, ' ').trim();
	}

	function reviewRow(title, lines) {
		var html = '<article class="wcs-review-card"><h4>' + escapeHtml(title) + '</h4>';
		lines.forEach(function (line) {
			html += '<p>' + escapeHtml(line.label) + (line.value ? ': <strong>' + escapeHtml(line.value) + '</strong>' : '') + '</p>';
		});
		return html + '</article>';
	}

	function renderReview($wrap, total) {
		var rows = [];
		var width = $wrap.find('.wcs-dimension-input[data-key="width"]').val();
		var height = $wrap.find('.wcs-dimension-input[data-key="height"]').val();

		if (width || height) {
			rows.push(reviewRow('Size selection', [
				{ label: 'Width', value: width ? width + ' mm' : '' },
				{ label: 'Height', value: height ? height + ' mm' : '' }
			]));
		}

		$wrap.find('.wcs-choice-select').each(function () {
			var $select = $(this);
			var value = $select.val();
			if (!value) {
				return;
			}
			var title = $select.closest('.wcs-step-option-group').find('.wcs-option-heading h3, > h3').first().text();
			var optionText = cleanOptionText($select.find(':selected').text());
			var price = parseFloat($select.find(':selected').data('price')) || 0;
			var group = $select.data('group');
			var lines = [{ label: optionText, value: '' }];
			if (price) {
				lines.push({ label: 'Price', value: '+' + money(price) });
			}
			var $position = $select.closest('.wcs-step-option-group').find('.wcs-position-choice');
			if ($position.length && $position.val()) {
				lines.push({ label: $select.closest('.wcs-step-option-group').find('.wcs-position-select h3').text() || 'Position', value: cleanOptionText($position.find(':selected').text()) });
			}
			$select.closest('.wcs-step-option-group').find('.wcs-customer-field-input').each(function () {
				var $field = $(this);
				var fieldValue = $field.val();
				if (!fieldValue) {
					return;
				}
				var fieldLabel = $field.closest('.wcs-customer-field').find('.wcs-customer-field__label').first().text();
				var displayValue = $field.is('select') ? cleanOptionText($field.find(':selected').text()) : fieldValue;
				var fieldPrice = $field.is('select') ? (parseFloat($field.find(':selected').data('price')) || 0) : (parseFloat($field.data('price')) || 0);
				lines.push({ label: fieldLabel || group, value: displayValue });
				if (fieldPrice) {
					lines.push({ label: fieldLabel + ' price', value: '+' + money(fieldPrice) });
				}
			});
			rows.push(reviewRow(title, lines));
		});

		$wrap.find('.wcs-review').html(
			'<div class="wcs-review-total"><div><h3>In total</h3><p>' + escapeHtml($wrap.find('.wcs-configurator__header h2').text()) + '</p></div><strong>' + escapeHtml(money(total)) + '</strong></div>' +
			(rows.length ? rows.join('') : '<p class="wcs-review-empty">No selections yet.</p>')
		);
	}

	function parsePositions($element) {
		var raw = $element.attr('data-position-options') || '[]';
		try {
			var parsed = JSON.parse(raw);
			return Array.isArray(parsed) ? parsed : [];
		} catch (e) {
			return [];
		}
	}

	function parseCustomerFields($select) {
		var raw = $select.find(':selected').attr('data-customer-fields') || '[]';
		try {
			var parsed = JSON.parse(raw);
			return Array.isArray(parsed) ? parsed : [];
		} catch (e) {
			return [];
		}
	}

	function refreshPositionSelect($select) {
		var group = $select.data('group');
		var $target = $select.closest('.wcs-step-option-group').find('.wcs-position-select');
		var showWhen = $target.data('show-when') || '';
		var selected = $select.val() || '';
		var positions = parsePositions($target);
		var label = $target.data('position-label') || 'Position';

		if (!selected || !positions.length || (showWhen && showWhen !== selected)) {
			$target.prop('hidden', true).empty();
			return;
		}

		var html = '<h3>' + escapeHtml(label) + '</h3><label class="wcs-option-select"><select class="wcs-position-choice" data-group="' + escapeHtml(group) + '">';
		positions.forEach(function (position) {
			var value = position.value || position.label || '';
			var text = position.label || value;
			html += '<option value="' + escapeHtml(value) + '">' + escapeHtml(text) + '</option>';
		});
		html += '</select></label>';
		$target.html(html).prop('hidden', false);
		buildCustomSelect($target.find('select'));
	}

	function refreshCustomerFields($select) {
		var $group = $select.closest('.wcs-step-option-group');
		var group = $select.data('group') || '';
		var selected = $select.val() || '';
		var fields = selected ? parseCustomerFields($select) : [];
		var $target = $group.find('.wcs-customer-field-target');

		if (!$target.length) {
			return;
		}

		if (!fields.length) {
			$target.prop('hidden', true).empty();
			return;
		}

		var html = renderCustomerFields(fields, group + '.' + selected, false);

		$target.html(html).prop('hidden', !html);
		buildCustomSelect($target.find('select'));
		$target.find('.wcs-customer-field-select').each(function () {
			refreshNestedCustomerFields($(this));
		});
	}

	function renderCustomerFields(fields, baseKey, nested) {
		var html = nested ? '<div class="wcs-customer-nested-box">' : '';
		fields.forEach(function (field) {
			var key = baseKey + '.' + (field.key || '');
			var label = field.label || field.key || '';
			var placeholder = field.placeholder ? ' placeholder="' + escapeHtml(field.placeholder) + '"' : '';
			if (!field.key || !label) {
				return;
			}
			html += '<label class="wcs-customer-field"><span class="wcs-customer-field__label">' + escapeHtml(label) + '</span>';
			if (field.type === 'text') {
				html += '<input type="text" class="wcs-customer-field-input" data-key="' + escapeHtml(key) + '" data-price="' + escapeHtml(field.price_enabled ? (parseFloat(field.price) || 0) : 0) + '"' + placeholder + '>';
			} else {
				html += '<select class="wcs-customer-field-input wcs-customer-field-select" data-key="' + escapeHtml(key) + '">';
				html += '<option value="">' + escapeHtml(field.placeholder || 'Please select') + '</option>';
				(field.options || []).forEach(function (option) {
					var price = parseFloat(option.price) || 0;
					var nestedFields = option.customer_fields ? JSON.stringify(option.customer_fields || []) : '';
					html += '<option value="' + escapeHtml(option.value || '') + '" data-price="' + escapeHtml(price) + '"';
					if (nestedFields) {
						html += ' data-customer-fields="' + escapeHtml(nestedFields) + '"';
					}
					html += '>' + escapeHtml(option.label || option.value || '');
					if (price) {
						html += ' +' + escapeHtml(money(price));
					}
					html += '</option>';
				});
				html += '</select>';
			}
			html += '<div class="wcs-customer-nested-target" hidden></div>';
			html += '</label>';
		});
		html += nested ? '</div>' : '';
		return html;
	}

	function refreshNestedCustomerFields($field) {
		var $target = $field.closest('.wcs-customer-field').children('.wcs-customer-nested-target');
		var fields = parseCustomerFields($field);

		if (!$target.length) {
			return;
		}

		if (!fields.length) {
			$target.prop('hidden', true).empty();
			return;
		}

		var html = renderCustomerFields(fields, String($field.data('key') || '') + '.' + ($field.val() || ''), true);
		$target.html(html).prop('hidden', !html);
		buildCustomSelect($target.find('select'));
		$target.find('.wcs-customer-field-select').each(function () {
			refreshNestedCustomerFields($(this));
		});
	}

	function syncCustomSelect($select) {
		var $custom = $select.next('.wcs-custom-select');
		if (!$custom.length) {
			return;
		}
		var text = cleanOptionText($select.find(':selected').text()) || 'Please select';
		$custom.find('.wcs-custom-select__value').text(text);
		$custom.find('.wcs-custom-select__option').removeClass('is-selected');
		$custom.find('.wcs-custom-select__option[data-value="' + String($select.val()).replace(/"/g, '\\"') + '"]').addClass('is-selected');
	}

	function buildCustomSelect($selects) {
		$selects.each(function () {
			var $select = $(this);
			var optionsHtml = '';
			if ($select.next('.wcs-custom-select').length) {
				$select.next('.wcs-custom-select').remove();
			}
			$select.find('option').each(function () {
				var $option = $(this);
				var disabled = $option.is(':disabled') || $option.data('required-message');
				var message = $option.data('required-message') || $option.attr('title') || 'This option requires additional conditions.';
				optionsHtml += '<button type="button" class="wcs-custom-select__option' + (disabled ? ' is-disabled' : '') + '" data-value="' + escapeHtml($option.val()) + '">' +
					'<span>' + escapeHtml(cleanOptionText($option.text()) || '---') + '</span>' +
					($option.is(':selected') ? '<b aria-hidden="true">✓</b>' : '<b aria-hidden="true">✓</b>') +
					(disabled ? '<em>' + escapeHtml(message) + '</em>' : '') +
					'</button>';
			});
			$select.addClass('wcs-native-select').after(
				'<div class="wcs-custom-select">' +
				'<button type="button" class="wcs-custom-select__button"><span class="wcs-custom-select__value"></span><i aria-hidden="true"></i></button>' +
				'<div class="wcs-custom-select__menu">' + optionsHtml + '</div>' +
				'</div>'
			);
			syncCustomSelect($select);
		});
	}

	function activateStep($wrap, index, animate) {
		var $sections = $wrap.find('.wcs-configurator__section');
		var max = $sections.length - 1;
		var active = Math.max(0, Math.min(index, max));

		$sections.removeClass('is-active').eq(active).addClass('is-active');
		$wrap.data('active-step', active);
		$wrap.find('.wcs-step-back').prop('disabled', active <= 0);
		$wrap.find('.wcs-step-next').text(active === max - 1 ? 'Review' : 'Further').toggle(active < max);
		$wrap.find('.wcs-add-to-cart').toggle(active >= max);
		if (animate) {
			scrollStepTop($wrap);
		}
	}

	function scrollStepTop($wrap) {
		$wrap.find('.wcs-configurator__content').stop(true).animate({ scrollTop: 0 }, 360);
		$('html, body').stop(true).animate({ scrollTop: Math.max(0, $wrap.offset().top - 24) }, 360);
	}

	$(function () {
		$('.wcs-configurator').each(function () {
			var $wrap = $(this);

			activateStep($wrap, 0, false);
			buildCustomSelect($wrap.find('.wcs-choice-select'));
			$wrap.find('.wcs-choice-select').each(function () {
				refreshCustomerFields($(this));
			});
			collect($wrap);
		});

		$(document).on('click', '.wcs-configurator__thumb', function () {
			var $button = $(this);
			var image = $button.data('image');
			var $wrap = $button.closest('.wcs-configurator');

			$wrap.find('.wcs-configurator__thumb').removeClass('is-active');
			$button.addClass('is-active');
			$wrap.find('.wcs-configurator__image img').attr('src', image);
		});

		$(document).on('input change', '.wcs-configurator input, .wcs-configurator select', function () {
			if ($(this).hasClass('wcs-choice-select')) {
				refreshPositionSelect($(this));
				refreshCustomerFields($(this));
			}
			if ($(this).hasClass('wcs-customer-field-select')) {
				refreshNestedCustomerFields($(this));
			}
			syncCustomSelect($(this));
			collect($(this).closest('.wcs-configurator'));
		});

		$(document).on('click', '.wcs-custom-select__button', function (e) {
			e.preventDefault();
			var $custom = $(this).closest('.wcs-custom-select');
			$('.wcs-custom-select').not($custom).removeClass('is-open');
			$custom.toggleClass('is-open');
		});

		$(document).on('click', '.wcs-custom-select__option', function (e) {
			e.preventDefault();
			if ($(this).hasClass('is-disabled')) {
				return;
			}
			var $custom = $(this).closest('.wcs-custom-select');
			var $select = $custom.prev('select');
			$select.val($(this).data('value')).trigger('change');
			$custom.removeClass('is-open');
		});

		$(document).on('click', function (e) {
			if (!$(e.target).closest('.wcs-custom-select').length) {
				$('.wcs-custom-select').removeClass('is-open');
			}
		});

		$(document).on('click', '.wcs-step-next', function () {
			var $wrap = $(this).closest('.wcs-configurator');
			activateStep($wrap, ($wrap.data('active-step') || 0) + 1, true);
		});

		$(document).on('click', '.wcs-step-back', function () {
			var $wrap = $(this).closest('.wcs-configurator');
			activateStep($wrap, ($wrap.data('active-step') || 0) - 1, true);
		});
	});
}(jQuery));
