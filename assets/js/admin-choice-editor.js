(function ($) {
	'use strict';

	var editor = window.wcsChoiceEditor || {};
	var groups = editor.groups || {};
	var presets = editor.presets || {};
	var i18n = editor.i18n || {};

	function slugify(text) {
		return String(text || '')
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, '');
	}

	function updateImagePreview($input) {
		var url = $input.val();
		var $preview = $input.closest('.wcs-image-picker, .wcs-image-field').find('.wcs-image-preview');
		if (!$preview.length) {
			$preview = $('<div class="wcs-image-preview"></div>').insertAfter($input);
		}
		if (url) {
			$preview.html('<img src="' + url + '" alt="">');
		} else {
			$preview.empty();
		}
	}

	function bindMediaUpload() {
		$(document).on('click', '.wcs-upload-image', function (e) {
			e.preventDefault();
			var $button = $(this);
			var $input = $button.siblings('.wcs-image-url').length
				? $button.siblings('.wcs-image-url')
				: $button.closest('.wcs-image-field').find('.wcs-image-url');

			var frame = wp.media({
				title: i18n.selectImage || 'Select image',
				button: { text: i18n.selectImage || 'Select image' },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				$input.val(attachment.url).trigger('change');
				updateImagePreview($input);
			});

			frame.open();
		});

		$(document).on('change', '.wcs-image-url', function () {
			updateImagePreview($(this));
		});
	}

	function getGroupSlug() {
		return $('#wcs_extra_group').val() || '';
	}

	function toggleNestedSections() {
		var slug = getGroupSlug();
		var group = groups[slug] || {};
		var optional = group.optional_fields || {};
		var $wrap = $('.wcs-choice-nested');

		$wrap.attr('data-group', slug);

		$('.wcs-nested-section').each(function () {
			var key = $(this).data('field-key');
			if (optional[key]) {
				$(this).show();
			} else {
				$(this).hide();
			}
		});

		if (!slug || $.isEmptyObject(optional)) {
			$('.wcs-nested-empty').show();
			$('.wcs-nested-accordion').hide();
		} else {
			$('.wcs-nested-empty').hide();
			$('.wcs-nested-accordion').show();
		}
	}

	function reindexRepeater($repeater) {
		var baseName = $repeater.data('name');
		$repeater.find('.wcs-repeater-row').each(function (index) {
			$(this).find('[name]').each(function () {
				var name = $(this).attr('name');
				if (!name) {
					return;
				}
				var suffix = name.replace(/^[^\[]+\[\d+\]/, '');
				$(this).attr('name', baseName + '[' + index + ']' + suffix);
			});
		});
	}

	function buildRepeaterRowHtml($repeater, rowData) {
		rowData = rowData || {};
		var $template = $repeater.find('.wcs-repeater-row').first().clone();
		$template.find('input, textarea, select').each(function () {
			var $el = $(this);
			var classes = $el.attr('class') || '';
			var val = '';

			if (classes.indexOf('wcs-image-url') !== -1 && rowData.image) {
				val = rowData.image;
			} else if ($el.attr('name') && $el.attr('name').indexOf('[title]') !== -1 && rowData.title) {
				val = rowData.title;
			} else if ($el.attr('name') && $el.attr('name').indexOf('[name]') !== -1 && rowData.name) {
				val = rowData.name;
			} else if ($el.attr('name') && $el.attr('name').indexOf('[value]') !== -1 && rowData.value) {
				val = rowData.value;
			} else if ($el.attr('name') && $el.attr('name').indexOf('[price]') !== -1 && rowData.price !== undefined) {
				val = rowData.price;
			} else if ($el.attr('name') && $el.attr('name').indexOf('[id]') !== -1 && rowData.id !== undefined) {
				val = rowData.id;
			} else if ($el.attr('type') === 'checkbox') {
				$el.prop('checked', false);
				return;
			}

			$el.val(val);
		});
		$template.find('.wcs-image-preview').empty();
		if (rowData.image) {
			$template.find('.wcs-image-preview').html('<img src="' + rowData.image + '" alt="">');
		}
		return $template;
	}

	function bindRepeaters() {
		$(document).on('click', '.wcs-add-repeater-row', function (e) {
			e.preventDefault();
			var $section = $(this).closest('.wcs-nested-section');
			var $repeater = $section.find('.wcs-repeater');
			var $row = buildRepeaterRowHtml($repeater, {});
			$repeater.append($row);
			reindexRepeater($repeater);
		});

		$(document).on('click', '.wcs-remove-repeater-row', function (e) {
			e.preventDefault();
			var $repeater = $(this).closest('.wcs-repeater');
			if ($repeater.find('.wcs-repeater-row').length <= 1) {
				$(this).closest('.wcs-repeater-row').find('input, textarea').val('');
				$(this).closest('.wcs-repeater-row').find('.wcs-image-preview').empty();
				return;
			}
			$(this).closest('.wcs-repeater-row').remove();
			reindexRepeater($repeater);
		});

		$(document).on('click', '.wcs-use-preset', function (e) {
			e.preventDefault();
			var presetKey = $(this).data('preset');
			var rows = presets[presetKey] || [];
			var $repeater = $(this).closest('.wcs-nested-section').find('.wcs-repeater');

			$repeater.empty();
			if (!rows.length) {
				$repeater.append(buildRepeaterRowHtml($repeater, {}));
			} else {
				rows.forEach(function (row) {
					$repeater.append(buildRepeaterRowHtml($repeater, row));
				});
			}
			reindexRepeater($repeater);
		});

		$(document).on('click', '.wcs-add-no-thanks', function (e) {
			e.preventDefault();
			var $repeater = $(this).closest('.wcs-nested-section').find('.wcs-repeater');
			var noImg = 'https://cdn.shopify.com/s/files/1/0947/1511/7909/files/no-feature-selected.png';
			$repeater.append(buildRepeaterRowHtml($repeater, {
				id: 1,
				name: '---',
				value: '---',
				price: 0,
				image: noImg
			}));
			reindexRepeater($repeater);
		});

		$('.wcs-repeater').each(function () {
			var $repeater = $(this);
			if (!$repeater.data('sortable')) {
				$repeater.sortable({
					handle: '.wcs-repeater-handle',
					items: '.wcs-repeater-row',
					update: function () {
						reindexRepeater($repeater);
					}
				});
				$repeater.data('sortable', true);
			}
		});
	}

	function bindAccordion() {
		$(document).on('click', '.wcs-accordion-toggle', function (e) {
			e.preventDefault();
			var $panel = $(this).closest('.wcs-accordion-panel');
			var expanded = $(this).attr('aria-expanded') === 'true';
			$(this).attr('aria-expanded', expanded ? 'false' : 'true');
			$panel.toggleClass('is-open', !expanded);
		});
	}

	function bindCategoryChange() {
		$('#wcs_extra_group').on('change', toggleNestedSections);
		$('#wcs_option_name').on('blur', function () {
			var $value = $('#wcs_option_value');
			if (!$value.val()) {
				$value.val(slugify($(this).val()));
			}
		});
	}

	function refreshCustomerFieldRow($row) {
		var $grid = $row.children('.wcs-customer-field-grid');
		var $options = $row.children('.wcs-customer-field-options');
		var isDropdown = $grid.find('.wcs-customer-field-type').first().val() !== 'text';
		var prices = $grid.find('.wcs-customer-field-price-toggle input').first().is(':checked');
		$row.toggleClass('is-dropdown', isDropdown);
		$row.toggleClass('has-prices', prices);
		$options.toggle(isDropdown);
		$grid.find('.wcs-customer-field-price').toggle(!isDropdown && prices);
		$options.children('.wcs-customer-field-option').children('.wcs-customer-field-option-price').toggle(isDropdown && prices);
		$options.children('.wcs-customer-field-option').children('.wcs-customer-option-position').each(function () {
			$(this).toggleClass('is-enabled', $(this).children('.wcs-customer-option-position-switch').find('.wcs-customer-option-position-toggle').is(':checked'));
		});
	}

	function reindexCustomerFields() {
		reindexCustomerFieldList($('.wcs-customer-fields > .wcs-customer-field-list'), 'wcs_customer_fields');
	}

	function reindexCustomerFieldList($list, baseName) {
		$list.children('.wcs-customer-field-row').each(function (fieldIndex) {
			var fieldName = baseName + '[' + fieldIndex + ']';
			var $field = $(this).attr('data-field-index', fieldIndex);

			$field.children('.wcs-customer-field-grid').find('[name]').each(function () {
				replaceCustomerFieldName($(this), fieldName, 'field');
			});

			$field.children('.wcs-customer-field-options').children('.wcs-customer-field-option').each(function (optionIndex) {
				var optionName = fieldName + '[options][' + optionIndex + ']';
				var $option = $(this);
				$option.children('[name]').each(function () {
					replaceCustomerFieldName($(this), optionName, 'option');
				});
				$option.children('.wcs-customer-option-position').find('.wcs-customer-option-position-toggle').each(function () {
					replaceCustomerFieldName($(this), optionName, 'option');
				});
				reindexCustomerFieldList($option.find('> .wcs-customer-option-position-fields > .wcs-customer-field-list'), optionName + '[customer_fields]');
			});
			refreshCustomerFieldRow($field);
		});
	}

	function replaceCustomerFieldName($input, baseName, scope) {
		var suffix = '';
		if (scope === 'field') {
			if ($input.hasClass('wcs-customer-field-label')) suffix = '[label]';
			else if ($input.hasClass('wcs-customer-field-type')) suffix = '[type]';
			else if ($input.hasClass('wcs-customer-field-key')) suffix = '[key]';
			else if ($input.hasClass('wcs-customer-field-placeholder')) suffix = '[placeholder]';
			else if ($input.hasClass('wcs-customer-field-price')) suffix = '[price]';
			else if ($input.closest('.wcs-customer-field-price-toggle').length) suffix = '[price_enabled]';
			else suffix = '[required]';
		} else {
			if ($input.hasClass('wcs-customer-field-option-label')) suffix = '[label]';
			else if ($input.hasClass('wcs-customer-field-option-value')) suffix = '[value]';
			else if ($input.hasClass('wcs-customer-field-option-price')) suffix = '[price]';
			else suffix = '[nested_enabled]';
		}
		$input.attr('name', baseName + suffix);
	}

	function buildCustomerFieldRow($list) {
		var $source = $list && $list.children('.wcs-customer-field-row').length
			? $list.children('.wcs-customer-field-row').first()
			: $('.wcs-customer-field-row').first();
		var $template = $source.clone();
		$template.find('input[type="text"]').val('');
		$template.find('input[type="checkbox"]').prop('checked', false);
		$template.children('.wcs-customer-field-options').children('.wcs-customer-field-option').not(':first').remove();
		$template.find('.wcs-customer-field-option').removeClass('has-nested-fields');
		$template.find('.wcs-customer-option-position').removeClass('is-enabled');
		$template.find('.wcs-customer-option-position-fields > .wcs-customer-field-list').empty();
		refreshCustomerFieldRow($template);
		return $template;
	}

	function buildCustomerOptionRow($field) {
		var $template = $field.children('.wcs-customer-field-options').children('.wcs-customer-field-option').first().clone();
		$template.find('input[type="text"]').val('');
		$template.find('input[type="checkbox"]').prop('checked', false);
		$template.find('> .wcs-customer-option-position-fields > .wcs-customer-field-list > .wcs-customer-field-row').remove();
		$template.removeClass('has-nested-fields is-enabled').find('.wcs-customer-option-position').removeClass('is-enabled');
		return $template;
	}

	function bindCustomerFields() {
		$(document).on('click', '.wcs-customer-field-add', function (e) {
			e.preventDefault();
			var $list = $(this).closest('.wcs-customer-field-list');
			$list.append(buildCustomerFieldRow($list));
			reindexCustomerFields();
		});

		$(document).on('click', '.wcs-customer-field-remove', function (e) {
			e.preventDefault();
			var $list = $(this).closest('.wcs-customer-field-list');
			var $rows = $list.children('.wcs-customer-field-row');
			if ($rows.length <= 1) {
				var $row = $(this).closest('.wcs-customer-field-row');
				$row.find('input[type="text"]').val('');
				$row.find('input[type="checkbox"]').prop('checked', false);
				refreshCustomerFieldRow($row);
				return;
			}
			$(this).closest('.wcs-customer-field-row').remove();
			reindexCustomerFields();
		});

		$(document).on('change', '.wcs-customer-field-type, .wcs-customer-field-price-toggle input', function () {
			refreshCustomerFieldRow($(this).closest('.wcs-customer-field-row'));
		});

		$(document).on('click', '.wcs-customer-option-add', function (e) {
			e.preventDefault();
			var $field = $(this).closest('.wcs-customer-field-row');
			$field.children('.wcs-customer-field-options').append(buildCustomerOptionRow($field));
			reindexCustomerFields();
		});

		$(document).on('click', '.wcs-customer-option-remove', function (e) {
			e.preventDefault();
			var $field = $(this).closest('.wcs-customer-field-row');
			var $options = $field.children('.wcs-customer-field-options').children('.wcs-customer-field-option');
			if ($options.length <= 1) {
				$(this).closest('.wcs-customer-field-option').find('input').val('');
				return;
			}
			$(this).closest('.wcs-customer-field-option').remove();
			reindexCustomerFields();
		});

		$(document).on('change', '.wcs-customer-option-position-toggle', function () {
			var $position = $(this).closest('.wcs-customer-option-position');
			var $option = $(this).closest('.wcs-customer-field-option');
			var enabled = $(this).is(':checked');
			$position.toggleClass('is-enabled', enabled);
			$option.toggleClass('has-nested-fields', enabled);
			if (enabled && !$option.find('> .wcs-customer-option-position-fields > .wcs-customer-field-list > .wcs-customer-field-row').length) {
				var $list = $option.find('> .wcs-customer-option-position-fields > .wcs-customer-field-list');
				$list.append(buildCustomerFieldRow($list));
				reindexCustomerFields();
			}
		});

		$(document).on('blur', '.wcs-customer-field-label', function () {
			var $row = $(this).closest('.wcs-customer-field-row');
			var $key = $row.find('.wcs-customer-field-key');
			if (!$key.val()) {
				$key.val(slugify($(this).val()));
			}
		});

		$(document).on('blur', '.wcs-customer-field-option-label', function () {
			var $row = $(this).closest('.wcs-customer-field-option');
			var $value = $row.find('.wcs-customer-field-option-value');
			if (!$value.val()) {
				$value.val(slugify($(this).val()));
			}
		});

		reindexCustomerFields();
	}

	$(function () {
		if (!$('.wcs-choice-details').length) {
			return;
		}
		bindMediaUpload();
		bindRepeaters();
		bindAccordion();
		bindCategoryChange();
		bindCustomerFields();
		toggleNestedSections();
	});
}(jQuery));
