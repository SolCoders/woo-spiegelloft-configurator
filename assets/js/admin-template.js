(function ($) {
	'use strict';

	function bindAccordion() {
		$(document).on('click', '.wcs-template-accordion .wcs-accordion-toggle', function (e) {
			e.preventDefault();
			var $panel = $(this).closest('.wcs-accordion-panel');
			var expanded = $(this).attr('aria-expanded') === 'true';
			$(this).attr('aria-expanded', expanded ? 'false' : 'true');
			$panel.toggleClass('is-open', !expanded);
		});

		$(document).on('change', '.wcs-group-toggle', function () {
			$(this).closest('.wcs-template-group-panel').toggleClass('is-enabled', $(this).is(':checked'));
		});
	}

	function reindexRules() {
		$('#wcs-rules-list .wcs-rule-row').each(function (index) {
			$(this).find('[name]').each(function () {
				var name = $(this).attr('name');
				if (!name) {
					return;
				}
				$(this).attr('name', name.replace(/wcs_validation_rules\[\d+\]/, 'wcs_validation_rules[' + index + ']'));
			});
		});
	}

	function bindRuleBuilder() {
		$('#wcs-add-rule').on('click', function (e) {
			e.preventDefault();
			var $row = $('#wcs-rules-list .wcs-rule-row').first().clone();
			$row.find('input, select').val('');
			$('#wcs-rules-list').append($row);
			reindexRules();
		});

		$(document).on('click', '.wcs-remove-rule', function (e) {
			e.preventDefault();
			var $list = $('#wcs-rules-list');
			if ($list.find('.wcs-rule-row').length <= 1) {
				$list.find('input, select').val('');
				return;
			}
			$(this).closest('.wcs-rule-row').remove();
			reindexRules();
		});
	}

	$(function () {
		bindAccordion();
		bindRuleBuilder();
	});
}(jQuery));
