(function ($) {
	'use strict';

	function money(value) {
		var formatted = Number(value || 0).toFixed(2);
		return $('.woocommerce-Price-currencySymbol').first().text() + formatted;
	}

	function escapeHtml(value) {
		return String(value === null || value === undefined ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function collect($wrap) {
		if (!$wrap.data('pricing')) {
			var payload = $wrap.find('.wcs-pricing-payload').text();
			if (payload) {
				var parsed = JSON.parse(payload);
				$wrap.data('pricing', parsed.pricing).data('pricingFields', parsed.fields).data('priceDecimals', parsed.decimals);
			}
		}
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
			if (this.disabled || $(this).closest('[hidden], .is-wcs-rule-disabled').length) {
				return;
			}
			var key = $(this).data('key');
			var value = $(this).val();
			if (key && String(value || '').trim() !== '' && !inputIsPlaceholder($(this))) {
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
			if ($select.prop('disabled') || $select.closest('[hidden], .is-wcs-rule-disabled').length) {
				return;
			}
			var group = $select.data('group');
			var value = $select.val();
			var price = parseFloat($select.find(':selected').data('price')) || 0;

			if (group && value && !inputIsPlaceholder($select)) {
				selections[group] = value;
				total += price;
			}
		});

		$wrap.find('.wcs-position-choice').each(function () {
			if (this.disabled || $(this).closest('[hidden], .is-wcs-rule-disabled').length) {
				return;
			}
			var group = $(this).data('group');
			var value = $(this).val();
			if (group && value) {
				selections[group + '_position'] = value;
			}
		});

		var pricing = $wrap.data('pricing');
		if (pricing && window.WCSPricing) {
			total += window.WCSPricing.calculate(pricing, $wrap.data('pricingFields') || [], selections, parseFloat($wrap.data('base-price')) || 0).total;
			var precision = Math.pow(10, Math.max(2, Number($wrap.data('priceDecimals')) || 2));
			total = Math.round((total + 1e-9) * precision) / precision;
		}
		$wrap.find('.wcs-selections-input').val(JSON.stringify(selections));
		setPrice($wrap, total);
		renderReview($wrap, total);
	}

	function inputIsPlaceholder($input) {
		return $input.is('select') && ['none', '---'].indexOf(String($input.val() || '').trim().toLowerCase()) !== -1;
	}

	function validationMessage(input) {
		if (input.validity.valueMissing) { return 'Bitte füllen Sie dieses Feld aus.'; }
		if (input.validity.rangeUnderflow) { return 'Bitte geben Sie einen Wert von mindestens ' + input.min + ' ein.'; }
		if (input.validity.rangeOverflow) { return 'Bitte geben Sie einen Wert von höchstens ' + input.max + ' ein.'; }
		if (input.validity.stepMismatch) { return 'Bitte geben Sie einen Wert in Schritten von ' + input.step + ' ein.'; }
		return 'Bitte geben Sie einen gültigen Wert ein.';
	}

	function cleanOptionText($option) {
		var label = $option.attr('data-option-label');
		return String(label === undefined ? $option.text() : label).replace(/\s+/g, ' ').trim();
	}

	function optionDisplayPrice(price) {
		var amount = parseFloat(price) || 0;
		return amount ? '+ ' + money(amount) : '';
	}

	function setPrice($wrap, total) {
		var $prices = $wrap.find('.wcs-configurator__price, .wcs-configurator__footer-price');
		var previous = parseFloat($wrap.data('current-total'));
		var start = isNaN(previous) ? total : previous;
		var duration = 1200;
		var started = null;
		var frame = $wrap.data('priceFrame');

		$wrap.data('current-total', total);
		if (frame) {
			window.cancelAnimationFrame(frame);
		}
		if (start === total) {
			$prices.text(money(total));
			$prices.removeClass('is-counting');
			$wrap.removeData('priceFrame');
			return;
		}

		$prices.addClass('is-counting');
		function tick(timestamp) {
			if (started === null) {
				started = timestamp;
			}
			var progress = Math.min((timestamp - started) / duration, 1);
			var eased = 1 - Math.pow(1 - progress, 3);
			var value = start + ((total - start) * eased);
			$prices.text(money(value));
			if (progress < 1) {
				$wrap.data('priceFrame', window.requestAnimationFrame(tick));
			} else {
				$prices.text(money(total));
				$prices.removeClass('is-counting');
				$wrap.removeData('priceFrame');
			}
		}

		$wrap.data('priceFrame', window.requestAnimationFrame(tick));
	}

	function rangeMessage($input, min, max) {
		var label = $input.data('label') || $input.closest('label').find('> span:first-child').clone().children().remove().end().text() || 'dieses Maß';
		var unit = $input.hasClass('wcs-dimension-input') ? ' mm' : '';
		label = String(label).trim();
		if (isNaN(min)) { return 'Bitte geben Sie für ' + label + ' einen Wert von höchstens ' + max + unit + ' ein.'; }
		if (isNaN(max)) { return 'Bitte geben Sie für ' + label + ' einen Wert von mindestens ' + min + unit + ' ein.'; }
		return 'Bitte geben Sie für ' + label + ' einen Wert zwischen ' + min + ' und ' + max + unit + ' ein.';
	}

	function schedulePopupHide($error, errorSeconds) {
		window.clearTimeout($error.data('wcsPopupTimer'));
		var seconds = Number(errorSeconds);
		var delay = errorSeconds !== undefined && isFinite(seconds) && seconds >= 0 ? seconds * 1000 : 3200;
		if (delay > 0) {
			$error.data('wcsPopupTimer', window.setTimeout(function () {
				$error.prop('hidden', true);
			}, Math.min(delay, 2147483647)));
		}
	}

	function showDimensionRangeError($input, message, errorSeconds) {
		var $control = $input.closest('.wcs-size-control');
		var $error = $control.find('.wcs-range-popup').first();

		if (!$error.length) {
			$error = $('<span class="wcs-range-popup" role="alert"></span>');
			$control.append($error);
		}

		$error.attr('data-wcs-popup-type', 'range').text(message).prop('hidden', false);
		$input.data('wcsRangeValue', String($input.val()));
		positionFieldPopup($error);
		schedulePopupHide($error, errorSeconds);
	}

	function hideFieldPopup($field, type) {
		$field.find('> .wcs-range-popup, > .wcs-size-control > .wcs-range-popup, > .wcs-custom-select > .wcs-range-popup').filter(function () {
			return !type || $(this).attr('data-wcs-popup-type') === type;
		}).each(function () {
			window.clearTimeout($(this).data('wcsPopupTimer'));
			$(this).prop('hidden', true);
		});
	}

	function positionFieldPopup($error) {
		var anchor = $error.parent()[0];
		if (!anchor || $error.prop('hidden')) {
			return;
		}
		$error.removeClass('wcs-range-popup--below wcs-range-popup--scrollable').css({ left: '', right: '', maxWidth: '', maxHeight: '' });
		var bounds = { top: 8, left: 8, bottom: window.innerHeight - 8, right: window.innerWidth - 8 };
		$error.parents().each(function () {
			if (this === document.body || this === document.documentElement) { return; }
			var style = window.getComputedStyle(this);
			var rect = this.getBoundingClientRect();
			if (/auto|scroll|hidden|clip/.test(style.overflowY)) {
				bounds.top = Math.max(bounds.top, rect.top + this.clientTop + 4);
				bounds.bottom = Math.min(bounds.bottom, rect.top + this.clientTop + this.clientHeight - 4);
			}
			if (/auto|scroll|hidden|clip/.test(style.overflowX)) {
				bounds.left = Math.max(bounds.left, rect.left + this.clientLeft + 4);
				bounds.right = Math.min(bounds.right, rect.left + this.clientLeft + this.clientWidth - 4);
			}
		});
		var field = anchor.getBoundingClientRect();
		if (field.bottom < bounds.top || field.top > bounds.bottom || bounds.right <= bounds.left) {
			return;
		}
		$error.css('maxWidth', Math.max(0, Math.min(320, bounds.right - bounds.left)) + 'px');
		var rect = $error[0].getBoundingClientRect();
		var left = Math.max(bounds.left, Math.min(rect.left, bounds.right - rect.width));
		if (Math.abs(left - rect.left) > 1) {
			$error.css({ left: (left - field.left + anchor.scrollLeft - anchor.clientLeft) + 'px', right: 'auto' });
		}
		var above = Math.max(0, field.top - bounds.top - 8);
		var below = Math.max(0, bounds.bottom - field.bottom - 8);
		var openBelow = rect.height > above && below > above;
		$error.toggleClass('wcs-range-popup--below', openBelow);
		var available = openBelow ? below : above;
		if (available > 0 && rect.height > available) {
			$error.addClass('wcs-range-popup--scrollable').css('maxHeight', available + 'px');
		}
	}

	var popupPositionFrame;
	function schedulePopupPosition() {
		if (popupPositionFrame) { return; }
		popupPositionFrame = window.requestAnimationFrame(function () {
			popupPositionFrame = null;
			$('.wcs-configurator .wcs-range-popup:visible').each(function () {
				positionFieldPopup($(this));
			});
		});
	}

	function showFieldPopup($field, message, type) {
		var $anchor = $field.children('.wcs-size-control, .wcs-custom-select').first();
		var $error;

		if (!$anchor.length) {
			$anchor = $field;
		}
		$anchor.addClass('wcs-popup-anchor');

		$error = $anchor.children('.wcs-range-popup').first();
		if (!$error.length) {
			$error = $('<span class="wcs-range-popup" role="alert"></span>');
			$anchor.append($error);
		}

		$error.attr('data-wcs-popup-type', type || 'field').text(message).prop('hidden', false);
		positionFieldPopup($error);
		schedulePopupHide($error);
	}

	function scrollToFirstIssue($wrap) {
		var $issue = $wrap.find('.is-empty, [aria-invalid="true"]').first();
		if (!$issue.length) {
			return;
		}
		var index = $wrap.find('.wcs-configurator__section').index($issue.closest('.wcs-configurator__section'));
		if (index >= 0 && $wrap.data('layout') !== 'compact_dropdown') {
			activateStep($wrap, index, false);
		}
		$issue[0].scrollIntoView({ block: 'center', behavior: 'smooth' });
		$issue.find('input:not(.wcs-native-select), .wcs-custom-select__button, select:not(.wcs-native-select)').addBack('input').first().trigger('focus');
	}

	function showCartMessages($wrap, messages) {
		var text = (messages || []).filter(Boolean).join('\n');
		if (text) {
			showFieldPopup($wrap.find('.wcs-configurator__footer'), text);
		}
	}

	function clampDimensionInput($input, showError) {
		var min = parseFloat($input.attr('min'));
		min = Math.max(0, isNaN(min) ? 0 : min);
		var max = parseFloat($input.attr('max'));
		var value = parseFloat($input.val());
		var clamped = value;

		if (isNaN(value)) {
			if ($input.hasClass('wcs-customer-number-input') && $input.val() === '') {
				$input.val(min);
			}
			return;
		}
		if (!isNaN(min)) {
			clamped = Math.max(min, clamped);
		}
		if (!isNaN(max)) {
			clamped = Math.min(max, clamped);
		}
		clamped = Math.max(min, clamped);
		if (clamped !== value) {
			$input.val(clamped);
			if (showError) {
				var boundary = value > clamped ? 'max' : 'min';
				var message = $input.data('wcs-rule-' + boundary + '-message');
				if (message === undefined) { message = $input.data('wcs-rule-range-message'); }
				showDimensionRangeError($input, message || rangeMessage($input, min, max), $input.data('wcs-rule-' + boundary + '-seconds'));
			}
		}
	}

	function reviewRow(title, lines) {
		var html = '<article class="wcs-review-card"><h4>' + escapeHtml(title) + '</h4>';
		lines.forEach(function (line) {
			html += '<p><span>' + escapeHtml(line.label) + (line.value ? ': <strong>' + escapeHtml(line.value) + '</strong>' : '') + '</span>' + (line.price ? '<strong class="wcs-review-line-price">' + escapeHtml(line.price) + '</strong>' : '') + '</p>';
		});
		return html + '</article>';
	}

	function renderReview($wrap, total) {
		var rows = [];
		var width = $wrap.find('.wcs-dimension-input[data-key="width"]').val();
		var height = $wrap.find('.wcs-dimension-input[data-key="height"]').val();
		var sideLines = [];

		$wrap.find('.wcs-dimension-input').each(function () {
			var key = String($(this).data('key') || '');
			var value = $(this).val();
			if (key.indexOf('side_') === 0 && value !== '') {
				sideLines.push({
					label: $(this).data('label') || key.replace(/^side_/, '').replace(/_/g, ' '),
					value: value + ' mm'
				});
			}
		});

		if (width || height) {
			rows.push(reviewRow('Maßauswahl', [
				{ label: 'Breite', value: width ? width + ' mm' : '' },
				{ label: 'Höhe', value: height ? height + ' mm' : '' }
			]));
		} else if (sideLines.length) {
			rows.push(reviewRow('Maßauswahl', sideLines));
		}

		$wrap.find('.wcs-choice-select').each(function () {
			var $select = $(this);
			if ($select.closest('.is-wcs-rule-disabled').length) {
				return;
			}
			var value = $select.val();
			if (!value) {
				return;
			}
			var title = $select.closest('.wcs-step-option-group').find('.wcs-option-heading h3, > h3').first().text();
			var optionText = cleanOptionText($select.find(':selected'));
			var price = parseFloat($select.find(':selected').data('price')) || 0;
			var group = $select.data('group');
			var lines = [{ label: optionText, value: '', price: price ? '+' + money(price) : '' }];
			var $position = $select.closest('.wcs-step-option-group').find('.wcs-position-choice');
			if ($position.length && $position.val()) {
				lines.push({ label: $select.closest('.wcs-step-option-group').find('.wcs-position-select h3').text() || 'Position', value: cleanOptionText($position.find(':selected')) });
			}
			$select.closest('.wcs-step-option-group').find('.wcs-customer-field-input').each(function () {
				var $field = $(this);
				var fieldValue = $field.val();
				if (!fieldValue) {
					return;
				}
				var fieldLabel = $field.closest('.wcs-customer-field').find('.wcs-customer-field__label').first().text();
				var displayValue = $field.is('select') ? cleanOptionText($field.find(':selected')) : fieldValue;
				var fieldPrice = $field.is('select') ? (parseFloat($field.find(':selected').data('price')) || 0) : (parseFloat($field.data('price')) || 0);
				lines.push({ label: fieldLabel || group, value: displayValue, price: fieldPrice ? '+' + money(fieldPrice) : '' });
			});
			rows.push(reviewRow(title, lines));
		});

		$wrap.find('.wcs-review').html(
			'<div class="wcs-review-total"><div><h3>Gesamtpreis</h3><p>' + escapeHtml($wrap.find('.wcs-configurator__header h2').text()) + '</p></div><strong>' + escapeHtml(money(total)) + '</strong></div>' +
			(rows.length ? rows.join('') : '<p class="wcs-review-empty">Noch keine Auswahl getroffen.</p>')
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

	function removeNestedRowsCascade($scope, $rows) {
		$rows.each(function () {
			$(this).find('.wcs-customer-field-input').each(function () {
				var childKey = String($(this).data('key') || '');
				var escapedChild = childKey.replace(/"/g, '\\"');
				var $childRows;

				if (!childKey) {
					return;
				}

				$childRows = $scope.find('[data-wcs-nested-parent="' + escapedChild + '"]');
				removeNestedRowsCascade($scope, $childRows);
			});
		});
		$rows.remove();
	}

	function clearNestedCustomerFields($field) {
		if ($field.closest('.wcs-configurator').data('layout') === 'compact_dropdown') {
			var escapedParent = String($field.data('key') || '').replace(/"/g, '\\"');
			var $scope = $field.closest('.wcs-configurator');
			var $rows = $field.closest('.wcs-customer-field').nextAll('[data-wcs-nested-parent="' + escapedParent + '"]')
				.add($field.closest('.wcs-customer-field-target').nextAll('[data-wcs-nested-parent="' + escapedParent + '"]'))
				.add($field.closest('.wcs-step-option-group').nextAll('[data-wcs-nested-parent="' + escapedParent + '"]'));
			removeNestedRowsCascade($scope, $rows);
		}
		getNestedTarget($field).prop('hidden', true).empty();
	}

	function getNestedTarget($field) {
		var key = String($field.data('key') || '');
		var $local = $field.closest('.wcs-customer-field').find('.wcs-customer-nested-target').first();

		if ($local.length) {
			return $local;
		}

		return $field.closest('.wcs-step-option-group').find('.wcs-customer-nested-target').filter(function () {
			return String($(this).data('parent-key') || '') === key;
		}).first();
	}

	function positionNestedTarget($field) {
		var $target = getNestedTarget($field);
		var $choices = $field.next('.wcs-image-choice-list');
		var $currentBox = $field.closest('.wcs-customer-nested-box');

		if (!$target.length) {
			return;
		}

		if ($currentBox.length) {
			$target.insertAfter($currentBox);
			return;
		}

		if ($choices.length) {
			$target.insertAfter($choices);
			return;
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
		html += '<option value="">' + escapeHtml($select.closest('.wcs-configurator').attr('data-none-label') || 'Keine Auswahl') + '</option>';
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
		var compactDropdown = $group.closest('.wcs-configurator').data('layout') === 'compact_dropdown';
		var skipFirstLabel = $target.data('skip-first-label') === 1 || $target.data('skip-first-label') === '1';

		if (!$target.length) {
			return;
		}

		$group.children('[data-wcs-dependent-parent="' + String(group).replace(/"/g, '\\"') + '"]').remove();
		$group.children('[data-wcs-nested-parent]').remove();

		if (!fields.length) {
			$target.prop('hidden', true).empty();
			return;
		}

		var html = renderCustomerFields(fields, group + '.' + selected, false, skipFirstLabel);

		if (compactDropdown && skipFirstLabel) {
			var $rows = $(html).attr('data-wcs-dependent-parent', group);
			$rows.first().addClass('wcs-dependent-inline-field');
			$rows.slice(1).addClass('wcs-dependent-row');
			$target.prop('hidden', true).empty().after($rows);
			buildCustomSelect($rows.find('select'));
			$rows.find('.wcs-customer-field-select').each(function () {
				refreshNestedCustomerFields($(this));
			});
			validateCustomerFields($rows);
			return;
		}

		$target.html(html).prop('hidden', !html);
		buildCustomSelect($target.find('select'));
		$target.find('.wcs-customer-field-select').each(function () {
			refreshNestedCustomerFields($(this));
		});
		validateCustomerFields($target);
	}

	function renderCustomerFields(fields, baseKey, nested, skipFirstLabel) {
		var visibleFields = (fields || []).filter(function (field) {
			return field && field.key && (field.label || field.key);
		});
		var textOnly = visibleFields.length > 1 && visibleFields.every(function (field) {
			return field.type === 'text';
		});
		var html = nested ? '<div class="wcs-customer-nested-box' + (textOnly ? ' wcs-customer-nested-box--inline' : '') + '">' : '';
		fields.forEach(function (field, index) {
			var key = baseKey + '.' + (field.key || '');
			var label = field.label || field.key || '';
			var placeholder = field.placeholder ? ' placeholder="' + escapeHtml(field.placeholder) + '"' : '';
			var required = !!field.required;
			var requiredAttr = required ? ' aria-required="true" data-required="1"' : '';
			var behaviorAttr = '';
			var requiredClass = required ? ' is-required is-empty' : '';
			if (!field.key || !label) {
				return;
			}
			if (field.read_only) {
				behaviorAttr += ' readonly';
			}
			if (field.disabled) {
				behaviorAttr += ' disabled';
			}
			html += '<div class="wcs-customer-field' + (nested ? ' wcs-customer-field--nested' : '') + (field.type === 'text' ? ' wcs-customer-field--text' : field.type === 'number' ? ' wcs-customer-field--number' : ' wcs-customer-field--choices') + requiredClass + '" data-required-label="' + escapeHtml(label) + '">';
			if (!(skipFirstLabel && index === 0)) {
				html += '<label class="wcs-customer-field__label" for="wcs-field-' + escapeHtml(key) + '">' + escapeHtml(label) + '</label>';
			}
			if (field.type === 'text') {
				html += '<input id="wcs-field-' + escapeHtml(key) + '" type="text" class="wcs-customer-field-input" data-key="' + escapeHtml(key) + '" data-price="' + escapeHtml(field.price_enabled ? (parseFloat(field.price) || 0) : 0) + '"' + placeholder + requiredAttr + behaviorAttr + '>';
			} else if (field.type === 'number') {
				var minimum = Math.max(0, parseFloat(field.min) || 0);
				var defaultNumberValue = minimum;
				var buttonAttr = field.disabled ? ' disabled aria-disabled="true"' : '';
				html += '<span class="wcs-size-control wcs-customer-number-control' + (field.read_only ? ' is-readonly' : '') + (field.disabled ? ' is-disabled' : '') + '">';
				if (!field.read_only) {
					html += '<button type="button" class="wcs-size-step wcs-size-step--minus" data-step="-1" aria-label="' + escapeHtml('Wert verringern') + '"' + buttonAttr + '>-</button>';
				}
				html += '<input id="wcs-field-' + escapeHtml(key) + '" type="number" class="wcs-customer-field-input wcs-customer-number-input" data-key="' + escapeHtml(key) + '" data-label="' + escapeHtml(label) + '" data-price="' + escapeHtml(field.price_enabled ? (parseFloat(field.price) || 0) : 0) + '"';
				html += ' min="' + escapeHtml(minimum) + '"';
				if (field.max !== undefined && field.max !== '') {
					html += ' max="' + escapeHtml(field.max) + '"';
				}
				if (field.step !== undefined && field.step !== '') {
					html += ' step="' + escapeHtml(field.step) + '"';
				}
				html += ' value="' + escapeHtml(defaultNumberValue) + '"' + placeholder + requiredAttr + behaviorAttr + '>';
				if (!field.read_only) {
					html += '<button type="button" class="wcs-size-step wcs-size-step--plus" data-step="1" aria-label="' + escapeHtml('Wert erhöhen') + '"' + buttonAttr + '>+</button>';
				}
				html += '</span>';
			} else {
				html += '<select id="wcs-field-' + escapeHtml(key) + '" class="wcs-customer-field-input wcs-customer-field-select" data-key="' + escapeHtml(key) + '"' + requiredAttr + (field.disabled ? ' disabled' : '') + '>';
				var preselected = (field.options || []).filter(function (option) { return option.preselected; })[0];
				if (!preselected) {
					html += '<option value="">Keine Auswahl</option>';
				}
				(field.options || []).forEach(function (option) {
					var price = parseFloat(option.price) || 0;
					var nestedFields = option.customer_fields ? JSON.stringify(option.customer_fields || []) : '';
					html += '<option value="' + escapeHtml(option.value || '') + '" data-option-label="' + escapeHtml(option.label || option.value || '') + '" data-price="' + escapeHtml(price) + '"' + (option === preselected ? ' selected' : '');
					if (option.image) {
						html += ' data-image="' + escapeHtml(option.image) + '"';
					}
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
			if (required) {
				html += '<p class="wcs-customer-field__error" hidden>' + escapeHtml(label) + ' darf nicht leer sein.</p>';
			}
			html += '<div class="wcs-customer-nested-target" data-parent-key="' + escapeHtml(key) + '" hidden></div>';
			html += '</div>';
		});
		html += nested ? '</div>' : '';
		return html;
	}

	function refreshNestedCustomerFields($field) {
		var $target = getNestedTarget($field);
		var selected = $field.val() || '';
		var fields = selected ? parseCustomerFields($field) : [];
		var compactDropdown = $field.closest('.wcs-configurator').data('layout') === 'compact_dropdown';

		if (!$target.length) {
			return;
		}

		clearNestedCustomerFields($field);

		if (!fields.length) {
			return;
		}

		var parentKey = String($field.data('key') || '');
		var html = renderCustomerFields(fields, parentKey + '.' + selected, !compactDropdown, false);
		if (compactDropdown) {
			var $rows = $(html).attr('data-wcs-nested-parent', parentKey);
			var $anchor = $field.closest('.wcs-customer-field');
			var $optionGroup = $anchor.closest('.wcs-step-option-group');
			var $fieldTarget = $anchor.closest('.wcs-customer-field-target');
			if ($optionGroup.length && $anchor.is('.wcs-dependent-inline-field')) {
				$anchor = $optionGroup;
			} else if ($fieldTarget.length && !$anchor.is('[data-wcs-nested-parent]')) {
				$anchor = $fieldTarget;
			}
			$anchor.after($rows);
			buildCustomSelect($rows.find('select'));
			$rows.find('.wcs-customer-field-select').each(function () {
				refreshNestedCustomerFields($(this));
			});
			validateCustomerFields($rows);
			return;
		}

		$target.html(html).prop('hidden', !html);
		positionNestedTarget($field);
		buildCustomSelect($target.find('select'));
		$target.find('.wcs-customer-field-select').each(function () {
			refreshNestedCustomerFields($(this));
		});
		validateCustomerFields($target);
	}

	function syncCustomSelect($select) {
		var $custom = $select.next('.wcs-custom-select');
		if (!$custom.length) {
			return;
		}
		var text = cleanOptionText($select.find(':selected')) || 'Keine Auswahl';
		var price = optionDisplayPrice($select.find(':selected').data('price'));
		$custom.find('.wcs-custom-select__selected-price').text(price).prop('hidden', !price);
		$custom.find('.wcs-custom-select__value').text(text);
		$custom.find('.wcs-custom-select__option').removeClass('is-selected');
		$custom.find('.wcs-custom-select__option[data-value="' + String($select.val()).replace(/"/g, '\\"') + '"]').addClass('is-selected');
	}

	function syncImageChoices($select) {
		var $choices = $select.next('.wcs-image-choice-list');
		if (!$choices.length) {
			return;
		}
		var value = String($select.val() || '');
		$choices.find('.wcs-image-choice-card').removeClass('is-selected').attr('aria-checked', 'false');
		$choices.find('.wcs-image-choice-card[data-value="' + value.replace(/"/g, '\\"') + '"]').addClass('is-selected').attr('aria-checked', 'true');
		positionNestedTarget($select);
	}

	function buildImageChoiceSelect($select) {
		var isSubChoice = $select.closest('.wcs-customer-nested-box').length > 0;
		var html = '<div class="wcs-image-choice-list ' + (isSubChoice ? 'wcs-image-choice-list--sub' : 'wcs-image-choice-list--main') + '" role="radiogroup">';
		$select.find('option').each(function () {
			var $option = $(this);
			var value = String($option.val() || '');
			var image = $option.data('image') || '';
			var label = cleanOptionText($option) || '---';
			var price = optionDisplayPrice($option.data('price'));
			var disabled = $option.is(':disabled') || $option.data('required-message');
			if (!value) {
				return;
			}
			html += '<button type="button" class="wcs-image-choice-card' + (disabled ? ' is-disabled' : '') + '" data-value="' + escapeHtml(value) + '" role="radio" aria-checked="false"' + (disabled ? ' disabled' : '') + '>';
			html += '<span class="wcs-image-choice-card__media">' + (image ? '<img src="' + escapeHtml(image) + '" alt="">' : '') + '</span>';
			html += '<span class="wcs-image-choice-card__radio" aria-hidden="true"></span>';
			html += '<span class="wcs-image-choice-card__body"><strong>' + escapeHtml(label) + '</strong>' + (price ? '<em>' + escapeHtml(price) + '</em>' : '') + '</span>';
			html += '</button>';
		});
		html += '</div>';
		$select.addClass('wcs-native-select').after(html);
		syncImageChoices($select);
	}

	function validateCustomerFields($scope, showErrors) {
		var hasErrors = false;
		var $scope = $scope || $('.wcs-configurator');
		$scope.find('.wcs-step-option-group').each(function () {
			var $group = $(this);
			var $select = $group.find('> .wcs-option-select .wcs-choice-select').first();
			var required = $group.hasClass('is-required') || $group.data('required') === 1 || $group.data('required') === '1' || $select.data('required') === 1 || $select.data('required') === '1' || $select.data('wcs-rule-required') === '1' || $select.prop('required');
			var empty;
			var label;
			var message;

			if (!required || $group.is('[hidden]') || $group.closest('[hidden], .is-wcs-rule-disabled').length || $select.prop('disabled')) {
				return;
			}

			empty = !$select.val();
			label = $group.find('.wcs-option-heading h3, > h3').first().text() || 'Dieses Feld';
			message = $group.data('wcs-rule-message') || label + ' darf nicht leer sein.';
			$group.toggleClass('is-empty', empty);
			if (empty) {
				hasErrors = true;
				if (showErrors) {
					showFieldPopup($group, message, 'required');
				}
			} else {
				hideFieldPopup($group, 'required');
			}
		});
		$scope.find('.wcs-customer-field.is-required').each(function () {
			var $field = $(this);
			var $input = $field.children('.wcs-customer-field-input, .wcs-size-control').find('.wcs-customer-field-input').add($field.children('.wcs-customer-field-input')).first();
			var empty = !$input.val();
			var label = $field.data('required-label') || 'Dieses Feld';
			var message = $field.data('wcs-rule-message') || label + ' darf nicht leer sein.';
			if ($field.is('[hidden]') || $field.closest('[hidden], .is-wcs-rule-disabled').length || $input.prop('disabled')) {
				$field.removeClass('is-empty');
				hideFieldPopup($field);
				return;
			}
			$field.toggleClass('is-empty', empty);
			$field.find('> .wcs-customer-field__error').prop('hidden', true);
			if (empty) {
				hasErrors = true;
				if (showErrors) {
					showFieldPopup($field, message, 'required');
				}
			} else {
				hideFieldPopup($field, 'required');
			}
		});
		$scope.find('.wcs-customer-nested-box').each(function () {
			$(this).toggleClass('has-required-empty', $(this).find('.wcs-customer-field.is-required.is-empty').length > 0);
		});
		return !hasErrors;
	}

	function buildCustomSelect($selects) {
		$selects.each(function () {
			var $select = $(this);
			var compactDropdown = $select.closest('.wcs-configurator').data('layout') === 'compact_dropdown';
			var optionsHtml = '';
			$select.find('option[value=""]').text($select.closest('.wcs-configurator').attr('data-none-label') || 'Keine Auswahl');
			if ($select.next('.wcs-custom-select').length) {
				$select.next('.wcs-custom-select').remove();
			}
			if ($select.next('.wcs-image-choice-list').length) {
				$select.next('.wcs-image-choice-list').remove();
			}
			var hasImages = $select.find('option').filter(function () {
				return !!($(this).data('image') || '');
			}).length > 0;
			if (hasImages && !compactDropdown && !$select.closest('.wcs-option-select--hidden-parent').length) {
				buildImageChoiceSelect($select);
				return;
			}
			$select.find('option').each(function () {
				var $option = $(this);
				var disabled = $option.is(':disabled') || $option.data('required-message');
				var message = $option.data('required-message') || $option.attr('title') || 'Für diese Option müssen weitere Bedingungen erfüllt sein.';
				var image = $option.data('image') || '';
				var price = optionDisplayPrice($option.data('price'));
				optionsHtml += '<button type="button" class="wcs-custom-select__option' + (image ? ' wcs-custom-select__option--has-image' : ' wcs-custom-select__option--no-image') + (disabled ? ' is-disabled' : '') + '" data-value="' + escapeHtml($option.val()) + '">' +
					(image ? '<img src="' + escapeHtml(image) + '" alt="">' : '') +
					'<span>' + escapeHtml(cleanOptionText($option) || '---') + '</span>' +
					(price ? '<small class="wcs-custom-select__price">' + escapeHtml(price) + '</small>' : '') +
					'</button>';
			});
			$select.addClass('wcs-native-select').after(
				'<div class="wcs-custom-select">' +
				'<small class="wcs-custom-select__selected-price" hidden></small>' +
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

		if ($wrap.data('layout') === 'compact_dropdown') {
			$sections.addClass('is-active');
			$wrap.data('active-step', 0);
			$wrap.find('.wcs-configurator__step-label, .wcs-step-back, .wcs-step-next, .wcs-configurator__footer-total').prop('hidden', true);
			$wrap.find('.wcs-add-to-cart').prop('hidden', false);
			return;
		}

		$sections.removeClass('is-active').eq(active).addClass('is-active');
		$wrap.data('active-step', active);
		$wrap.find('.wcs-configurator__step-label').text('SCHRITT ' + (active + 1) + ' VON ' + String(max + 1).padStart(2, '0'));
		$wrap.find('.wcs-configurator__progress span').css('width', (((active + 1) / (max + 1)) * 100) + '%');
		$wrap.find('.wcs-step-back').prop('disabled', active <= 0);
		$wrap.find('.wcs-step-next').text('Weiter').prop('hidden', active >= max);
		$wrap.find('.wcs-add-to-cart').prop('hidden', active < max);
		if (animate) {
			scrollStepTop($wrap);
		}
	}

	function scrollStepTop($wrap) {
		$wrap.find('.wcs-configurator__content').stop(true).animate({ scrollTop: 0 }, 360);
		$('html, body').stop(true).animate({ scrollTop: Math.max(0, $wrap.offset().top - 24) }, 360);
	}

	function scrollToNextOption($source) {
		var $group = $source.closest('.wcs-step-option-group');
		var $wrap = $source.closest('.wcs-configurator');
		var $content = $wrap.find('.wcs-configurator__content');
		var $target = $group.find('.wcs-customer-field-target:not([hidden]), .wcs-customer-nested-target:not([hidden])').last();

		if (!$target.length) {
			$target = $group.nextAll('.wcs-step-option-group').first();
		}

		if (!$target.length || !$content.length) {
			return;
		}

		$content.stop(true).animate({
			scrollTop: $content.scrollTop() + $target.position().top - 12
		}, 320);
	}

	$(function () {
		$(window).on('resize.wcsPopup scroll.wcsPopup', schedulePopupPosition);
		$(document).on('wcs:range-invalid', '.wcs-configurator input', function (event, violation) {
			showDimensionRangeError($(this), violation.message || rangeMessage($(this), parseFloat(this.min), parseFloat(this.max)), violation.seconds);
		});
		$('.wcs-configurator').each(function () {
			var $wrap = $(this);
			// Handle native constraints through the same popup as server validation.
			this.noValidate = true;
			$wrap.find('.wcs-configurator__content').on('scroll.wcsPopup', schedulePopupPosition);

			activateStep($wrap, 0, false);
			buildCustomSelect($wrap.find('.wcs-choice-select'));
			$wrap.find('.wcs-choice-select').each(function () {
				refreshCustomerFields($(this));
			});
			if (window.WCSRuleEngine) {
				$wrap.data('wcsRuleEngine', new window.WCSRuleEngine($wrap));
				$wrap.data('wcsRuleEngine').evaluate();
			}
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

		$(document).on('input change', '.wcs-configurator input, .wcs-configurator select', function (e) {
			var numeric = $(this).hasClass('wcs-dimension-input') || $(this).hasClass('wcs-customer-number-input');
			if (e.type === 'input' || !numeric || String($(this).val()) !== $(this).data('wcsRangeValue')) {
				hideFieldPopup($(this).closest('.wcs-size-control, .wcs-customer-field'));
			}
			if ($(this).hasClass('wcs-dimension-input') || $(this).hasClass('wcs-customer-number-input')) {
				var $dimension = $(this);
				var $dimensionWrap = $dimension.closest('.wcs-configurator');
				window.clearTimeout($dimension.data('wcsClampTimer'));
				if (e.type === 'input') {
					if (parseFloat($dimension.val()) < 0) {
						clampDimensionInput($dimension, true);
						collect($dimensionWrap);
						return;
					}
					$dimension.data('wcsClampTimer', window.setTimeout(function () {
						clampDimensionInput($dimension, true);
						var engine = $dimensionWrap.data('wcsRuleEngine');
						if (engine) {
							engine.evaluate($dimension.data('key'));
						}
						collect($dimensionWrap);
					}, 650));
					return;
				}
				clampDimensionInput($dimension, true);
			}
			if ($(this).hasClass('wcs-choice-select')) {
				refreshPositionSelect($(this));
				refreshCustomerFields($(this));
			}
			if ($(this).hasClass('wcs-customer-field-select')) {
				refreshNestedCustomerFields($(this));
			}
			syncCustomSelect($(this));
			syncImageChoices($(this));
			validateCustomerFields($(this).closest('.wcs-configurator'), false);
			var engine = $(this).closest('.wcs-configurator').data('wcsRuleEngine');
			if (engine) {
				engine.evaluate($(this).data('key') || $(this).data('group'));
			}
			collect($(this).closest('.wcs-configurator'));
		});

		$(document).on('submit', 'form.wcs-configurator', function (e) {
			e.preventDefault();
			var $wrap = $(this);
			if ($wrap.data('wcsSubmitting')) {
				return;
			}
			var valid = validateCustomerFields($wrap, true);
			$wrap.find('input, select, textarea').each(function () {
				var $input = $(this);
				$input.removeAttr('aria-invalid');
				if (this.disabled || $input.closest('[hidden], .is-wcs-rule-disabled').length || this.type === 'hidden') {
					return;
				}
				var missingDimension = $input.hasClass('wcs-dimension-input') && !$input.val();
				if (missingDimension || !this.checkValidity()) {
					valid = false;
					$input.attr('aria-invalid', 'true');
					var message = $input.data('wcs-rule-range-message') || (missingDimension ? 'Bitte geben Sie ein Maß ein.' : validationMessage(this));
					if ($input.hasClass('wcs-dimension-input')) {
						showDimensionRangeError($input, message);
					} else {
						showFieldPopup($input.closest('.wcs-customer-field, .wcs-step-option-group, .wcs-position-select'), message);
					}
				}
			});
			if (!valid) {
				scrollToFirstIssue($wrap);
				return;
			}
			collect($wrap);
			var $button = $wrap.find('.wcs-add-to-cart');
			$wrap.data('wcsSubmitting', true).attr('aria-busy', 'true');
			$button.prop('disabled', true).addClass('loading');
			hideFieldPopup($wrap.find('.wcs-configurator__footer'));
			$.ajax({
				url: $wrap.find('.wcs-cart-endpoint').val(),
				method: 'POST',
				dataType: 'json',
				data: $wrap.serialize(),
				timeout: 30000
			}).done(function (response) {
				if (!response || !response.success) {
					showCartMessages($wrap, response && response.data && response.data.messages || ['Die Konfiguration konnte nicht hinzugefügt werden. Bitte versuchen Sie es erneut.']);
					return;
				}
				var data = response.data;
				showCartMessages($wrap, data.messages);
				$.each(data.fragments || {}, function (selector, html) {
					$(selector).replaceWith(html);
				});
				$(document.body).trigger('added_to_cart', [data.fragments, data.cart_hash, $button]);
				$button.addClass('added');
				if (data.redirect && !(data.messages || []).length) {
					window.location.assign(data.redirect);
				}
			}).fail(function (xhr) {
				showCartMessages($wrap, xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.messages || ['Die Anfrage konnte nicht abgeschlossen werden. Bitte prüfen Sie den Warenkorb, bevor Sie es erneut versuchen.']);
			}).always(function () {
				$wrap.removeData('wcsSubmitting').removeAttr('aria-busy');
				$button.prop('disabled', false).removeClass('loading');
			});
		});

		$(document).on('click', '.wcs-size-step', function (e) {
			e.preventDefault();
			var $button = $(this);
			var $input = $button.siblings('.wcs-dimension-input, .wcs-customer-number-input').first();
			var direction = parseFloat($button.data('step')) || 0;
			var inputStep = parseFloat($input.attr('step'));
			var step = isNaN(inputStep) || inputStep <= 0 ? 1 : inputStep;
			var precision = Math.max(
				String(step).split('.')[1] ? String(step).split('.')[1].length : 0,
				String($input.val()).split('.')[1] ? String($input.val()).split('.')[1].length : 0
			);
			var min = parseFloat($input.attr('min'));
			min = Math.max(0, isNaN(min) ? 0 : min);
			var max = parseFloat($input.attr('max'));
			var value = parseFloat($input.val());

			if (!$input.length || !direction || $button.prop('disabled') || $input.prop('disabled') || $input.prop('readonly')) {
				return;
			}
			if ($input.hasClass('wcs-dimension-input') && inputStep > 0) {
				$input[0].stepUp(direction);
				$input.trigger('change');
				return;
			}

			value = isNaN(value) ? (isNaN(min) ? 0 : min) : value;
			value += direction * step;
			if (!isNaN(min)) {
				value = Math.max(min, value);
			}
			if (!isNaN(max)) {
				value = Math.min(max, value);
			}
			value = Math.max(min, value);

			value = Number(value.toFixed(Math.min(precision, 6)));
			$input.val(value).trigger('change');
		});

		$(document).on('click', '.wcs-custom-select__button', function (e) {
			e.preventDefault();
			var $custom = $(this).closest('.wcs-custom-select');
			$('.wcs-custom-select').not($custom).removeClass('is-open');
			$custom.toggleClass('is-open');
		});

		$(document).on('click', '.wcs-custom-select__option', function (e) {
			e.preventDefault();
			if ($(this).hasClass('is-disabled') || $(this).closest('.wcs-step-option-group, .wcs-customer-field').hasClass('is-wcs-rule-disabled')) {
				return;
			}
			var $custom = $(this).closest('.wcs-custom-select');
			var $select = $custom.prev('select');
			$select.val($(this).data('value')).trigger('change');
			$custom.removeClass('is-open');
			scrollToNextOption($select);
		});

		$(document).on('click', '.wcs-image-choice-card', function (e) {
			e.preventDefault();
			if ($(this).hasClass('is-disabled') || $(this).closest('.wcs-step-option-group, .wcs-customer-field').hasClass('is-wcs-rule-disabled')) {
				return;
			}
			var $list = $(this).closest('.wcs-image-choice-list');
			var $select = $list.prev('select');
			$select.val($(this).data('value')).trigger('change');
			scrollToNextOption($select);
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
