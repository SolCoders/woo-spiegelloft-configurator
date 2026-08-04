(function ($) {
	'use strict';

	function initImageUpload() {
		$(document).on('click', '.wcs-upload-image', function (e) {
			e.preventDefault();

			var targetId = $(this).data('target');
			var $input = $('#' + targetId);

			if (typeof wp === 'undefined' || !wp.media) {
				return;
			}

			var frame = wp.media({
				title: 'Select image',
				button: { text: 'Use image' },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				$input.val(attachment.url);
			});

			frame.open();
		});
	}

	function initDeleteOption() {
		$(document).on('click', '.wcs-delete-option', function () {
			if (!window.confirm(wcsAdmin.i18n.confirm)) {
				return;
			}

			var $row = $(this).closest('tr');
			var optionId = $(this).data('id');

			$.post(wcsAdmin.ajaxUrl, {
				action: 'wcs_delete_extra_option',
				nonce: wcsAdmin.nonce,
				option_id: optionId
			}).done(function (response) {
				if (response.success) {
					$row.remove();
				} else {
					window.alert(response.data && response.data.message ? response.data.message : wcsAdmin.i18n.error);
				}
			}).fail(function () {
				window.alert(wcsAdmin.i18n.error);
			});
		});
	}

	$(function () {
		initImageUpload();
		initDeleteOption();
	});
}(jQuery));
