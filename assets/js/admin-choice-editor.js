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

	function reindexPositionRows() {
		$('.wcs-position-row').each(function (index) {
			$(this).find('[name]').each(function () {
				$(this).attr('name', $(this).attr('name').replace(/wcs_position_options\[\d+\]/, 'wcs_position_options[' + index + ']'));
			});
		});
	}

	function buildPositionRow() {
		var $template = $('.wcs-position-row').first().clone();
		$template.find('input').val('');
		return $template;
	}

	function bindPositionOptions() {
		$(document).on('change', '.wcs-position-toggle input', function () {
			$('.wcs-position-fields').prop('hidden', !$(this).is(':checked'));
		});

		$(document).on('click', '.wcs-position-add', function (e) {
			e.preventDefault();
			$(this).closest('.wcs-position-options').append(buildPositionRow());
			reindexPositionRows();
		});

		$(document).on('click', '.wcs-position-remove', function (e) {
			e.preventDefault();
			var $rows = $('.wcs-position-row');
			if ($rows.length <= 1) {
				$(this).closest('.wcs-position-row').find('input').val('');
				return;
			}
			$(this).closest('.wcs-position-row').remove();
			reindexPositionRows();
		});

		$(document).on('blur', '.wcs-position-row input[name$="[label]"]', function () {
			var $row = $(this).closest('.wcs-position-row');
			var $value = $row.find('input[name$="[value]"]');
			if (!$value.val()) {
				$value.val(slugify($(this).val()));
			}
		});
	}

	$(function () {
		if (!$('.wcs-choice-details').length) {
			return;
		}
		bindMediaUpload();
		bindRepeaters();
		bindAccordion();
		bindCategoryChange();
		bindPositionOptions();
		toggleNestedSections();
	});
}(jQuery));
