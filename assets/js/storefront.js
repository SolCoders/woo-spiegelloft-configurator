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
	}

	function activateStep($wrap, index) {
		var $sections = $wrap.find('.wcs-configurator__section');
		var max = $sections.length - 1;
		var active = Math.max(0, Math.min(index, max));

		$sections.removeClass('is-active').eq(active).addClass('is-active');
		$wrap.data('active-step', active);
		$wrap.find('.wcs-step-back').prop('disabled', active <= 0);
		$wrap.find('.wcs-step-next').text(active >= max ? 'Review' : 'Further');
	}

	$(function () {
		$('.wcs-configurator').each(function () {
			var $wrap = $(this);

			activateStep($wrap, 0);
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
			}
			collect($(this).closest('.wcs-configurator'));
		});

		$(document).on('click', '.wcs-step-next', function () {
			var $wrap = $(this).closest('.wcs-configurator');
			activateStep($wrap, ($wrap.data('active-step') || 0) + 1);
		});

		$(document).on('click', '.wcs-step-back', function () {
			var $wrap = $(this).closest('.wcs-configurator');
			activateStep($wrap, ($wrap.data('active-step') || 0) - 1);
		});
	});
}(jQuery));
