(function ($) {
	'use strict';

	function validateRulesJson() {
		var $editor = $('#wcs_template_rules');
		if (!$editor.length) {
			return;
		}

		$editor.closest('form').on('submit', function () {
			try {
				JSON.parse($editor.val());
				$editor.removeClass('wcs-invalid');
			} catch (e) {
				$editor.addClass('wcs-invalid');
				window.alert('Validation rules must be valid JSON.');
				return false;
			}
		});
	}

	$(function () {
		validateRulesJson();
	});
}(jQuery));
