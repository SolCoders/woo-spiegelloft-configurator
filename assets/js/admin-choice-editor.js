(function ($) {
	'use strict';

	var editor = window.wcsChoiceEditor || {};
	var groups = editor.groups || {};
	var presets = editor.presets || {};
	var i18n = editor.i18n || {};
	var templateStorageKey = 'wcs_customer_field_templates';
	var clipboardStorageKey = 'wcs_customer_field_clipboard';

	function slugify(text) {
		return String(text || '')
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, '');
	}

	function updateImagePreview($input) {
		var url = $input.val();
		var $field = $input.closest('.wcs-image-picker, .wcs-image-field');
		var $square = $field.find('.wcs-image-square');
		if ($square.length) {
			$square.toggleClass('has-image', !!url).html(url ? '<img src="' + url + '" alt="">' : '<span aria-hidden="true">+</span>');
			$field.find('.wcs-remove-image').prop('hidden', !url);
			return;
		}
		var $preview = $field.find('.wcs-image-preview');
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

		$(document).on('click', '.wcs-remove-image', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $input = $(this).closest('.wcs-image-field').find('.wcs-image-url');
			$input.val('').trigger('change');
		});
	}

	function getGroupSlug() {
		return $('#wcs_extra_group').val() || $('.wcs-choice-nested').attr('data-group') || '';
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
	}

	function refreshCustomerFieldRow($row) {
		var $box = $row.children('.wcs-customer-field-box');
		var $grid = $box.children('.wcs-customer-field-grid');
		var $options = $box.children('.wcs-customer-field-options');
		var isDropdown = $grid.find('.wcs-customer-field-type').first().val() !== 'text';
		$row.toggleClass('is-dropdown', isDropdown);
		$row.addClass('has-prices');
		$options.toggle(isDropdown);
		$grid.find('.wcs-customer-field-required').closest('.wcs-customer-field-meta').show();
		$options.children('.wcs-customer-field-option').children('.wcs-customer-field-option-price').toggle(isDropdown);
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

			$field.children('.wcs-customer-field-key').each(function () {
				replaceCustomerFieldName($(this), fieldName, 'field');
			});

			$field.children('.wcs-customer-field-box').children('.wcs-customer-field-grid').find('[name]').each(function () {
				replaceCustomerFieldName($(this), fieldName, 'field');
			});

			$field.children('.wcs-customer-field-box').children('.wcs-customer-field-options').children('.wcs-customer-field-option').each(function (optionIndex) {
				var optionName = fieldName + '[options][' + optionIndex + ']';
				var $option = $(this);
				$option.children('[name]').each(function () {
					replaceCustomerFieldName($(this), optionName, 'option');
				});
				$option.children('.wcs-customer-field-option-image').find('[name]').each(function () {
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
			else if ($input.hasClass('wcs-customer-field-required')) suffix = '[required]';
		} else {
			if ($input.hasClass('wcs-customer-field-option-label')) suffix = '[label]';
			else if ($input.hasClass('wcs-image-url')) suffix = '[image]';
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
		$template.children('.wcs-customer-field-box').children('.wcs-customer-field-options').children('.wcs-customer-field-option').not(':first').remove();
		$template.find('.wcs-customer-field-option').removeClass('has-nested-fields');
		$template.find('.wcs-customer-option-position').removeClass('is-enabled');
		$template.find('.wcs-customer-option-position-fields > .wcs-customer-field-list').empty();
		refreshCustomerFieldRow($template);
		return $template;
	}

	function buildCustomerOptionRow($field) {
		var $template = $field.children('.wcs-customer-field-box').children('.wcs-customer-field-options').children('.wcs-customer-field-option').first().clone();
		$template.find('input[type="text"]').val('');
		$template.find('input[type="checkbox"]').prop('checked', false);
		$template.find('.wcs-image-url').val('').trigger('change');
		$template.find('.wcs-image-preview').empty();
		$template.find('> .wcs-customer-option-position-fields > .wcs-customer-field-list > .wcs-customer-field-row').remove();
		$template.removeClass('has-nested-fields is-enabled').find('.wcs-customer-option-position').removeClass('is-enabled');
		return $template;
	}

	function duplicateCustomerOption($option) {
		var $clone = $option.clone();
		$clone.removeClass('wcs-row-actions-active').css({
			'--wcs-row-action-x': '',
			'--wcs-row-action-y': ''
		});
		$clone.find('.wcs-row-actions-active').removeClass('wcs-row-actions-active').css({
			'--wcs-row-action-x': '',
			'--wcs-row-action-y': ''
		});
		$clone.find('.ui-sortable').removeData('customer-sortable customer-option-sortable');
		$clone.find('.wcs-image-url').each(function () {
			updateImagePreview($(this));
		});
		$option.after($clone);
		reindexCustomerFields();
		bindCustomerFieldSorting();
		bindCustomerOptionSorting();
	}

	function duplicateCustomerField($field) {
		var $clone = $field.clone();
		$clone.removeClass('wcs-row-actions-active').css({
			'--wcs-row-action-x': '',
			'--wcs-row-action-y': ''
		});
		$clone.find('.wcs-row-actions-active').removeClass('wcs-row-actions-active').css({
			'--wcs-row-action-x': '',
			'--wcs-row-action-y': ''
		});
		$clone.find('.ui-sortable').removeData('customer-sortable customer-option-sortable');
		$clone.find('.wcs-image-url').each(function () {
			updateImagePreview($(this));
		});
		$field.after($clone);
		reindexCustomerFields();
		bindCustomerFieldSorting();
		bindCustomerOptionSorting();
	}

	function getStoredTemplates() {
		try {
			var parsed = JSON.parse(window.localStorage.getItem(templateStorageKey) || '[]');
			return Array.isArray(parsed) ? parsed : [];
		} catch (e) {
			return [];
		}
	}

	function setStoredTemplates(templates) {
		window.localStorage.setItem(templateStorageKey, JSON.stringify(templates || []));
	}

	function customerFieldToData($field) {
		var data = {
			label: $field.find('> .wcs-customer-field-box > .wcs-customer-field-grid .wcs-customer-field-label').first().val() || '',
			type: $field.find('> .wcs-customer-field-box > .wcs-customer-field-grid .wcs-customer-field-type').first().val() || 'dropdown',
			placeholder: $field.find('> .wcs-customer-field-box > .wcs-customer-field-grid .wcs-customer-field-placeholder').first().val() || '',
			required: $field.find('> .wcs-customer-field-box > .wcs-customer-field-grid .wcs-customer-field-required').first().is(':checked'),
			options: []
		};

		$field.find('> .wcs-customer-field-box > .wcs-customer-field-options > .wcs-customer-field-option').each(function () {
			var $option = $(this);
			var option = {
				label: $option.children('.wcs-customer-field-option-label').val() || '',
				image: $option.children('.wcs-customer-field-option-image').find('.wcs-image-url').val() || '',
				price: $option.children('.wcs-customer-field-option-price').val() || '',
				nested_enabled: $option.children('.wcs-customer-option-position').find('.wcs-customer-option-position-toggle').is(':checked'),
				customer_fields: []
			};
			$option.find('> .wcs-customer-option-position-fields > .wcs-customer-field-list > .wcs-customer-field-row').each(function () {
				option.customer_fields.push(customerFieldToData($(this)));
			});
			data.options.push(option);
		});

		return data;
	}

	function allCustomerFieldsToData() {
		var rows = [];
		$('.wcs-customer-fields > .wcs-customer-field-list > .wcs-customer-field-row').each(function () {
			rows.push(customerFieldToData($(this)));
		});
		return rows;
	}

	function applyDataToCustomerField($field, data) {
		data = data || {};
		var $box = $field.children('.wcs-customer-field-box');
		var $grid = $box.children('.wcs-customer-field-grid');
		var $options = $box.children('.wcs-customer-field-options');
		$grid.find('.wcs-customer-field-label').first().val(data.label || '');
		$grid.find('.wcs-customer-field-type').first().val(data.type === 'text' ? 'text' : 'dropdown');
		$grid.find('.wcs-customer-field-placeholder').first().val(data.placeholder || '');
		$grid.find('.wcs-customer-field-required').first().prop('checked', !!data.required);

		$options.children('.wcs-customer-field-option').not(':first').remove();
		var options = Array.isArray(data.options) && data.options.length ? data.options : [{ label: '', image: '', price: '', nested_enabled: false, customer_fields: [] }];
		options.forEach(function (option, index) {
			var $option = index === 0 ? $options.children('.wcs-customer-field-option').first() : buildCustomerOptionRow($field);
			if (index > 0) {
				$options.append($option);
			}
			$option.children('.wcs-customer-field-option-label').val(option.label || '');
			$option.children('.wcs-customer-field-option-price').val(option.price || '');
			$option.children('.wcs-customer-field-option-image').find('.wcs-image-url').val(option.image || '').each(function () {
				updateImagePreview($(this));
			});
			$option.children('.wcs-customer-option-position').find('.wcs-customer-option-position-toggle').prop('checked', !!option.nested_enabled || (Array.isArray(option.customer_fields) && option.customer_fields.length > 0));
			$option.toggleClass('has-nested-fields', !!option.nested_enabled || (Array.isArray(option.customer_fields) && option.customer_fields.length > 0));
			$option.children('.wcs-customer-option-position').toggleClass('is-enabled', $option.hasClass('has-nested-fields'));
			var $nestedList = $option.find('> .wcs-customer-option-position-fields > .wcs-customer-field-list');
			$nestedList.empty();
			(option.customer_fields || []).forEach(function (nestedData) {
				var $nested = buildCustomerFieldRow($nestedList);
				$nestedList.append($nested);
				applyDataToCustomerField($nested, nestedData);
			});
		});
		refreshCustomerFieldRow($field);
	}

	function replaceCustomerFieldList($list, rows) {
		$list.empty();
		(rows || []).forEach(function (rowData) {
			var $row = buildCustomerFieldRow($list);
			$list.append($row);
			applyDataToCustomerField($row, rowData);
		});
		if (!$list.children('.wcs-customer-field-row').length) {
			$list.append(buildCustomerFieldRow($list));
		}
		reindexCustomerFields();
		bindCustomerFieldSorting();
		bindCustomerOptionSorting();
	}

	function openCustomerModal(title, bodyHtml) {
		var $modal = $('.wcs-customer-template-modal');
		$modal.find('.wcs-customer-template-modal__title').text(title);
		$modal.find('.wcs-customer-template-modal__body').html(bodyHtml);
		$modal.prop('hidden', false);
	}

	function closeCustomerModal() {
		$('.wcs-customer-template-modal').prop('hidden', true);
	}

	function textareaModal(title, actionClass, buttonText, value) {
		openCustomerModal(title,
			'<textarea class="wcs-customer-template-json" spellcheck="false">' + $('<div>').text(value || '').html() + '</textarea>' +
			'<div class="wcs-customer-template-actions"><button type="button" class="button button-primary ' + actionClass + '">' + buttonText + '</button></div>'
		);
	}

	function bindCustomerTemplates() {
		var activeField = null;
		var activeList = null;

		$(document).on('click', '.wcs-customer-template-close', closeCustomerModal);

		$(document).on('click', '.wcs-field-template-copy', function (e) {
			e.preventDefault();
			var json = JSON.stringify(customerFieldToData($(this).closest('.wcs-customer-field-row')), null, 2);
			window.localStorage.setItem(clipboardStorageKey, json);
			textareaModal('Copied field JSON', 'wcs-customer-template-close', 'Done', json);
		});

		$(document).on('click', '.wcs-field-template-paste', function (e) {
			e.preventDefault();
			activeField = $(this).closest('.wcs-customer-field-row');
			activeList = null;
			textareaModal('Paste field JSON', 'wcs-apply-field-json', 'Paste field', window.localStorage.getItem(clipboardStorageKey) || '');
		});

		$(document).on('click', '.wcs-apply-field-json', function (e) {
			e.preventDefault();
			try {
				var data = JSON.parse($('.wcs-customer-template-json').val() || '{}');
				if (activeList && !activeField) {
					var $row = buildCustomerFieldRow(activeList);
					activeList.append($row);
					applyDataToCustomerField($row, data);
				} else {
					applyDataToCustomerField(activeField, data);
				}
				reindexCustomerFields();
				bindCustomerFieldSorting();
				bindCustomerOptionSorting();
				closeCustomerModal();
			} catch (err) {
				window.alert('Invalid JSON.');
			}
		});

		$(document).on('click', '.wcs-field-template-save', function (e) {
			e.preventDefault();
			activeField = $(this).closest('.wcs-customer-field-row');
			activeList = null;
			openCustomerModal('Save field template',
				'<input type="text" class="wcs-customer-template-name" placeholder="Template name">' +
				'<div class="wcs-customer-template-actions"><button type="button" class="button button-primary wcs-save-field-template">Save template</button></div>'
			);
		});

		$(document).on('click', '.wcs-save-field-template', function (e) {
			e.preventDefault();
			var name = $('.wcs-customer-template-name').val() || activeField.find('.wcs-customer-field-label').first().val() || 'Untitled template';
			var templates = getStoredTemplates();
			templates.push({ id: Date.now(), name: name, data: customerFieldToData(activeField) });
			setStoredTemplates(templates);
			closeCustomerModal();
		});

		$(document).on('click', '.wcs-field-template-library', function (e) {
			e.preventDefault();
			activeField = $(this).closest('.wcs-customer-field-row');
			activeList = null;
			var html = '<div class="wcs-customer-template-grid">';
			getStoredTemplates().forEach(function (template) {
				html += '<button type="button" class="wcs-customer-template-card" data-template-id="' + template.id + '"><strong>' + $('<div>').text(template.name).html() + '</strong><span>Use template</span></button>';
			});
			html += '</div>';
			if (html === '<div class="wcs-customer-template-grid"></div>') {
				html = '<p>No saved templates yet.</p>';
			}
			openCustomerModal('Saved field templates', html);
		});

		$(document).on('click', '.wcs-customer-template-card', function (e) {
			e.preventDefault();
			var id = Number($(this).data('template-id'));
			var template = getStoredTemplates().filter(function (item) { return Number(item.id) === id; })[0];
			if (template) {
				if (activeList && !activeField) {
					var rows = Array.isArray(template.data) ? template.data : [template.data];
					rows.forEach(function (rowData) {
						var $row = buildCustomerFieldRow(activeList);
						activeList.append($row);
						applyDataToCustomerField($row, rowData);
					});
				} else {
					applyDataToCustomerField(activeField, template.data);
				}
				reindexCustomerFields();
				bindCustomerFieldSorting();
				bindCustomerOptionSorting();
				closeCustomerModal();
			}
		});

		$(document).on('click', '.wcs-customer-export', function (e) {
			e.preventDefault();
			textareaModal('Export all customer fields', 'wcs-customer-template-close', 'Done', JSON.stringify(allCustomerFieldsToData(), null, 2));
		});

		$(document).on('click', '.wcs-customer-import', function (e) {
			e.preventDefault();
			textareaModal('Import all customer fields', 'wcs-apply-all-json', 'Import all', '');
		});

		$(document).on('click', '.wcs-apply-all-json', function (e) {
			e.preventDefault();
			try {
				var rows = JSON.parse($('.wcs-customer-template-json').val() || '[]');
				if (!Array.isArray(rows)) {
					throw new Error('Expected array');
				}
				replaceCustomerFieldList($('.wcs-customer-fields > .wcs-customer-field-list'), rows);
				closeCustomerModal();
			} catch (err) {
				window.alert('Invalid JSON.');
			}
		});

		$(document).on('click', '.wcs-nested-create-field', function (e) {
			e.preventDefault();
			var $list = $(this).closest('.wcs-customer-option-position-fields').children('.wcs-customer-field-list');
			$list.append(buildCustomerFieldRow($list));
			reindexCustomerFields();
			bindCustomerFieldSorting();
			bindCustomerOptionSorting();
		});

		$(document).on('click', '.wcs-nested-save-template', function (e) {
			e.preventDefault();
			activeField = null;
			activeList = $(this).closest('.wcs-customer-field-option').children('.wcs-customer-option-position-fields').children('.wcs-customer-field-list');
			openCustomerModal('Save nested field template',
				'<input type="text" class="wcs-customer-template-name" placeholder="Template name">' +
				'<div class="wcs-customer-template-actions"><button type="button" class="button button-primary wcs-save-nested-template">Save template</button></div>'
			);
		});

		$(document).on('click', '.wcs-save-nested-template', function (e) {
			e.preventDefault();
			var rows = [];
			activeList.children('.wcs-customer-field-row').each(function () {
				rows.push(customerFieldToData($(this)));
			});
			var name = $('.wcs-customer-template-name').val() || 'Nested field template';
			var templates = getStoredTemplates();
			templates.push({ id: Date.now(), name: name, data: rows.length === 1 ? rows[0] : rows });
			setStoredTemplates(templates);
			closeCustomerModal();
		});

		$(document).on('click', '.wcs-nested-use-template', function (e) {
			e.preventDefault();
			activeField = null;
			activeList = $(this).closest('.wcs-customer-field-option').children('.wcs-customer-option-position-fields').children('.wcs-customer-field-list');
			var html = '<div class="wcs-customer-template-grid">';
			getStoredTemplates().forEach(function (template) {
				html += '<button type="button" class="wcs-customer-template-card" data-template-id="' + template.id + '"><strong>' + $('<div>').text(template.name).html() + '</strong><span>Use template</span></button>';
			});
			html += '</div>';
			if (html === '<div class="wcs-customer-template-grid"></div>') {
				html = '<p>No saved templates yet.</p>';
			}
			openCustomerModal('Saved field templates', html);
		});

		$(document).on('click', '.wcs-nested-paste-json', function (e) {
			e.preventDefault();
			activeField = null;
			activeList = $(this).closest('.wcs-customer-option-position-fields').children('.wcs-customer-field-list');
			textareaModal('Paste field JSON', 'wcs-apply-field-json', 'Paste field', window.localStorage.getItem(clipboardStorageKey) || '');
		});

		$(document).on('click', '.wcs-nested-copy-json', function (e) {
			e.preventDefault();
			var rows = [];
			$(this).closest('.wcs-customer-option-position-fields').children('.wcs-customer-field-list').children('.wcs-customer-field-row').each(function () {
				rows.push(customerFieldToData($(this)));
			});
			var json = rows.length === 1 ? JSON.stringify(rows[0], null, 2) : JSON.stringify(rows, null, 2);
			window.localStorage.setItem(clipboardStorageKey, json);
			textareaModal('Copied nested JSON', 'wcs-customer-template-close', 'Done', json);
		});
	}

	function bindCustomerFieldSorting() {
		function sizeSortPlaceholder(ui) {
			ui.placeholder.css({
				width: ui.item.outerWidth(),
				height: ui.item.outerHeight()
			});
		}

		$('.wcs-customer-field-list').each(function () {
			var $list = $(this);
			if ($list.data('customer-sortable')) {
				$list.sortable('refresh');
				return;
			}
			$list.sortable({
				connectWith: '.wcs-customer-field-list',
				items: '> .wcs-customer-field-row',
				handle: '.wcs-customer-drag-handle',
				placeholder: 'wcs-customer-field-sort-placeholder',
				forcePlaceholderSize: true,
				tolerance: 'pointer',
				start: function (event, ui) {
					ui.item.data('wcs-was-dragged', true);
					sizeSortPlaceholder(ui);
				},
				change: function (event, ui) {
					sizeSortPlaceholder(ui);
				},
				stop: function (event, ui) {
					window.setTimeout(function () {
						ui.item.removeData('wcs-was-dragged');
					}, 0);
				},
				update: reindexCustomerFields,
				receive: reindexCustomerFields
			});
			$list.data('customer-sortable', true);
		});
	}

	function bindCustomerOptionSorting() {
		function sizeSortPlaceholder(ui) {
			ui.placeholder.css({
				width: ui.item.outerWidth(),
				height: ui.item.outerHeight()
			});
		}

		$('.wcs-customer-field-options').each(function () {
			var $list = $(this);
			if ($list.data('customer-option-sortable')) {
				$list.sortable('refresh');
				return;
			}
			$list.sortable({
				connectWith: '.wcs-customer-field-options',
				items: '> .wcs-customer-field-option',
				handle: '.wcs-customer-drag-handle',
				placeholder: 'wcs-customer-option-sort-placeholder',
				forcePlaceholderSize: true,
				tolerance: 'pointer',
				start: function (event, ui) {
					ui.item.data('wcs-was-dragged', true);
					sizeSortPlaceholder(ui);
				},
				change: function (event, ui) {
					sizeSortPlaceholder(ui);
				},
				stop: function (event, ui) {
					window.setTimeout(function () {
						ui.item.removeData('wcs-was-dragged');
					}, 0);
				},
				update: reindexCustomerFields,
				receive: reindexCustomerFields
			});
			$list.data('customer-option-sortable', true);
		});
	}

	function bindCustomerFields() {
		$(document).on('click', '.wcs-customer-field-add', function (e) {
			e.preventDefault();
			var $row = $(this).closest('.wcs-customer-field-row');
			var $list = $row.closest('.wcs-customer-field-list');
			if ($row.length) {
				$row.after(buildCustomerFieldRow($list));
			} else {
				$list = $(this).closest('.wcs-customer-fields').children('.wcs-customer-field-list');
				$list.append(buildCustomerFieldRow($list));
			}
			reindexCustomerFields();
			bindCustomerFieldSorting();
			bindCustomerOptionSorting();
		});

		$(document).on('click', '.wcs-customer-field-duplicate', function (e) {
			e.preventDefault();
			duplicateCustomerField($(this).closest('.wcs-customer-field-row'));
		});

		$(document).on('click', '.wcs-customer-field-remove', function (e) {
			e.preventDefault();
			var $list = $(this).closest('.wcs-customer-field-list');
			var $rows = $list.children('.wcs-customer-field-row');
			var $row = $(this).closest('.wcs-customer-field-row');
			var $parentOption = $list.closest('.wcs-customer-field-option');
			if ($parentOption.length && $rows.length <= 1) {
				$row.remove();
				$parentOption.removeClass('has-nested-fields');
				$parentOption.children('.wcs-customer-option-position').removeClass('is-enabled');
				$parentOption.children('.wcs-customer-option-position').find('.wcs-customer-option-position-toggle').prop('checked', false);
				reindexCustomerFields();
				return;
			}
			if ($rows.length <= 1) {
				$row.find('input[type="text"]').val('');
				$row.find('input[type="checkbox"]').prop('checked', false);
				refreshCustomerFieldRow($row);
				return;
			}
			$(this).closest('.wcs-customer-field-row').remove();
			reindexCustomerFields();
		});

		$(document).on('change', '.wcs-customer-field-type', function () {
			refreshCustomerFieldRow($(this).closest('.wcs-customer-field-row'));
		});

		$(document).on('click', '.wcs-customer-option-add', function (e) {
			e.preventDefault();
			var $option = $(this).closest('.wcs-customer-field-option');
			var $toggle = $option.children('.wcs-customer-option-position').find('.wcs-customer-option-position-toggle').first();
			var $list = $option.children('.wcs-customer-option-position-fields').children('.wcs-customer-field-list');

			$toggle.prop('checked', true);
			$option.children('.wcs-customer-option-position').addClass('is-enabled');
			$option.addClass('has-nested-fields');
			$list.append(buildCustomerFieldRow($list));
			reindexCustomerFields();
			bindCustomerFieldSorting();
		});

		$(document).on('click', '.wcs-customer-option-duplicate', function (e) {
			e.preventDefault();
			duplicateCustomerOption($(this).closest('.wcs-customer-field-option'));
		});

		$(document).on('click', '.wcs-customer-option-remove', function (e) {
			e.preventDefault();
			var $field = $(this).closest('.wcs-customer-field-row');
			var $options = $field.children('.wcs-customer-field-box').children('.wcs-customer-field-options').children('.wcs-customer-field-option');
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
			$option.toggleClass('has-nested-fields', enabled).removeClass('is-nested-collapsed');
			if (enabled && !$option.find('> .wcs-customer-option-position-fields > .wcs-customer-field-list > .wcs-customer-field-row').length) {
				var $list = $option.find('> .wcs-customer-option-position-fields > .wcs-customer-field-list');
				$list.append(buildCustomerFieldRow($list));
				reindexCustomerFields();
				bindCustomerFieldSorting();
			}
		});

		$(document).on('click', '.wcs-customer-field-option > .wcs-customer-drag-handle', function (e) {
			var $option = $(this).parent('.wcs-customer-field-option');
			if ($option.data('wcs-was-dragged')) {
				return;
			}
			if (!$option.hasClass('has-nested-fields')) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			$option.toggleClass('is-nested-collapsed');
		});

		function positionRowActionRail($row, event) {
			var offset = $row.offset();
			var rowWidth = $row.outerWidth();
			var rowHeight = $row.outerHeight();
			var x = event.pageX - offset.left + 14;
			var y = event.pageY - offset.top;
			x = Math.max(12, Math.min(rowWidth - 158, x));
			y = Math.max(18, Math.min(rowHeight - 18, y));
			$row.css({
				'--wcs-row-action-x': x + 'px',
				'--wcs-row-action-y': y + 'px'
			});
		}

		$(document).on('mouseenter', '.wcs-customer-field-label, .wcs-customer-field-type, .wcs-customer-field-placeholder, .wcs-customer-field-option-label', function (e) {
			e.stopPropagation();
			var $row = $(this).closest('.wcs-customer-field-option, .wcs-customer-field-row');
			$('.wcs-row-actions-active').removeClass('wcs-row-actions-active');
			$row.addClass('wcs-row-actions-active');
			positionRowActionRail($row, e);
		});

		$(document).on('mouseleave', '.wcs-customer-field-row, .wcs-customer-field-option', function (e) {
			e.stopPropagation();
			$(this).removeClass('wcs-row-actions-active').css({
				'--wcs-row-action-x': '',
				'--wcs-row-action-y': ''
			});
		});

		$(document).on('blur', '.wcs-customer-field-label', function () {
			var $row = $(this).closest('.wcs-customer-field-row');
			var $key = $row.find('.wcs-customer-field-key');
			if (!$key.val()) {
				$key.val(slugify($(this).val()));
			}
		});

		reindexCustomerFields();
		bindCustomerTemplates();
		bindCustomerFieldSorting();
		bindCustomerOptionSorting();
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
