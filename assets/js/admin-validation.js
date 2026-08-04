(function ($) {
	'use strict';

	$(function () {
		$('#wcs_template_rules').on('blur', function () {
			var $el = $(this);
			try {
				var parsed = JSON.parse($el.val());
				$el.val(JSON.stringify(parsed, null, 2));
				$el.removeClass('wcs-invalid');
			} catch (e) {
				$el.addClass('wcs-invalid');
			}
		});
	});
}(jQuery));
