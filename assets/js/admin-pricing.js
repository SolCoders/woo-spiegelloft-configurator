(function ($) {
	'use strict';
	$(function () {
		var $settings = $('#wcs-pricing-settings');
		if (!$settings.length) return;
		var saved = JSON.parse($settings.val()), defaults = JSON.parse($('#wcs-pricing-defaults').text());
		var defaultRules = Object.values(defaults.dimensions)[0] || [];
		var shipping = copy(saved.shipping), counter = 0, fields = [], draft, active = 0, opener;
		var $modal = $('<div class="wcs-pricing-overlay" hidden><section class="wcs-pricing-dialog" role="dialog" aria-modal="true" aria-labelledby="wcs-pricing-title">' +
			'<header><h2 id="wcs-pricing-title">Dimension pricing</h2><button type="button" class="wcs-pricing-close" aria-label="Close pricing">×</button></header>' +
			'<div class="wcs-pricing-tabs" role="tablist" aria-label="Pricing fields"></div><div class="wcs-pricing-body"></div>' +
			'<footer><button type="button" class="button wcs-pricing-cancel">Cancel</button><button type="button" class="button wcs-pricing-apply">Apply rules</button></footer></section></div>').appendTo('body');
		function copy(v) { return JSON.parse(JSON.stringify(v)); }
		function esc(v) { return $('<span>').text(String(v)).html().replace(/"/g, '&quot;'); }
		function key(v) { return 'side_' + String(v).toLowerCase().trim().replace(/[^a-z0-9_\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-+|-+$/g, '').replace(/-/g, '_'); }
		function amount(v) { return '€' + Number(v || 0).toFixed(2); }
		function number(value, name, label, step) {
			if (step === '0.01') value = Number(value).toFixed(2);
			return '<input type="number" min="0" step="' + (step || 'any') + '" required data-prop="' + name + '" aria-label="' + esc(label) + '" value="' + esc(value) + '">';
		}
		function buttons(add, remove, label) {
			return '<button type="button" class="button" data-action="' + add + '" aria-label="Add ' + label + '">+</button>' +
				'<button type="button" class="button" data-action="' + remove + '" aria-label="Remove ' + label + '">−</button>';
		}
		function refresh() {
			fields = [];
			$('.wcs-side-row').each(function () {
				var $row = $(this), label = String($row.find('.wcs-side-label').val() || '').trim();
				var fieldKey = key($row.find('.wcs-side-key').val() || label);
				if (!$row.data('pricingIdentity')) {
					$row.data('pricingIdentity', ++counter);
					$row.data('pricingRules', copy(saved.dimensions[fieldKey] || defaultRules));
				}
				fields.push({ row: $row, id: $row.data('pricingIdentity'), label: label || 'Size ' + (fields.length + 1), selection_key: fieldKey });
			});
			$('.wcs-side-remove').prop('disabled', fields.length <= 1);
			sync();
		}
		function sync() {
			var dimensions = {};
			fields.forEach(function (f) { dimensions[f.selection_key] = copy(f.row.data('pricingRules')); });
			$settings.val(JSON.stringify({ version: 1, dimensions: dimensions, shipping: shipping }));
		}
		function show(event) {
			refresh();
			opener = event && event.currentTarget ? event.currentTarget : document.activeElement;
			draft = { rules: {}, shipping: copy(shipping), previews: {}, values: fields.map(function (f) { return Number(f.row.find('input[name$="[min]"]').val()) || 0; }), basePrice: 100 };
			fields.forEach(function (f) { draft.rules[f.id] = copy(f.row.data('pricingRules')); draft.previews[f.id] = Number(f.row.find('input[name$="[min]"]').val()) || 0; });
			active = 0;
			$modal.prop('hidden', false);
			$('body').addClass('wcs-pricing-open');
			render();
			$modal.find('[role="tab"]').first().trigger('focus');
		}
		function close() {
			$modal.prop('hidden', true);
			$('body').removeClass('wcs-pricing-open');
			if (opener) opener.focus();
		}
		function render() {
			$modal.find('.wcs-pricing-dialog').toggleClass('is-shipping', active === fields.length);
			var tabs = fields.map(function (f) { return f.label; }).concat('Shipping');
			$modal.find('.wcs-pricing-tabs').html(tabs.map(function (label, i) {
				return '<button type="button" role="tab" id="wcs-pricing-tab-' + i + '" aria-controls="wcs-pricing-panel" aria-selected="' + (active === i) + '" tabindex="' + (active === i ? 0 : -1) + '" data-tab="' + i + '">' + esc(label) + '</button>';
			}).join(''));
			var html = '<div role="tabpanel" id="wcs-pricing-panel" aria-labelledby="wcs-pricing-tab-' + active + '">';
			if (active < fields.length) {
				var field = fields[active], rules = draft.rules[field.id];
				html += '<p>Each rule adds a surcharge once ' + esc(field.label) + ' reaches its threshold.</p><div class="wcs-pricing-rulebox"><div class="wcs-pricing-dimension-head"><strong>' + esc(field.label) + ' from (mm)</strong><strong>Add to price (€)</strong><span></span></div><div class="wcs-pricing-rule-scroll">';
				rules.forEach(function (rule, i) {
					html += '<div class="wcs-pricing-dimension-row" data-rule="' + i + '">' + number(rule.from, 'from', field.label + ' from (mm)') + number(rule.amount, 'amount', 'Add to price (€)', '0.01') + '<span class="wcs-pricing-actions">' + buttons('add-rule', 'remove-rule', 'pricing rule') + '</span></div>';
				});
				html += '</div>' + (!rules.length ? '<button type="button" class="button" data-action="add-rule">Add rule</button>' : '') + '</div>';
				html += '<div class="wcs-pricing-preview"><strong>Preview</strong>' + number(draft.previews[field.id], 'preview', 'Preview ' + field.label) + '<span>mm</span><strong class="wcs-pricing-result" aria-live="polite"></strong></div>';
			} else {
				var unavailable = fields.length > 2;
				html += '<p><label><input type="checkbox" data-prop="enabled"' + (draft.shipping.enabled ? ' checked' : '') + (unavailable ? ' disabled' : '') + '> Enable shipping</label></p>';
				html += unavailable ? '<p>Shipping and its 33% markup are inactive for templates with more than two size fields. Saved rules are retained.</p>' : '<p>Matching fees add together. The base shipping rule adds 33% of the product base price once.</p>';
				html += '<fieldset class="wcs-pricing-shipping-fields"' + (unavailable || !draft.shipping.enabled ? ' disabled' : '') + '>';
				draft.shipping.groups.forEach(function (group, gi) {
					var title = group.id === 'base' ? 'Base shipping' : group.id === 'large' ? 'Large-size shipping' : 'Shipping fee ' + (gi + 1);
					html += '<div class="wcs-pricing-rulebox wcs-pricing-group" data-group="' + gi + '"><div class="wcs-pricing-group-heading"><div class="wcs-pricing-group-title"><strong>' + esc(title) + '</strong>' + (group.id === 'base' ? '<small>+ 33% of product base price</small>' : '') + '</div><label>Fee (€)' + number(group.amount, 'fee', 'Shipping fee (€)', '0.01') + '</label><button type="button" class="button wcs-pricing-remove-fee" data-action="remove-group" aria-label="Remove shipping fee">Remove fee</button></div><div class="wcs-pricing-group-conditions">';
					group.alternatives.forEach(function (c, ci) {
						html += (ci ? '<div class="wcs-pricing-or"><span>OR</span></div>' : '') + '<div class="wcs-pricing-condition" data-condition="' + ci + '"><label>' + esc(fields[0].label) + ' exceeds (mm)' + number(c.first, 'first', fields[0].label + ' exceeds (mm)') + '</label><label>Match<select data-prop="match" aria-label="Match"><option' + (c.match === 'OR' ? ' selected' : '') + '>OR</option><option' + (c.match === 'AND' ? ' selected' : '') + '>AND</option></select></label><label>' + esc(fields[1] ? fields[1].label : fields[0].label + ' (same diameter)') + ' exceeds (mm)' + number(c.second, 'second', 'Second dimension exceeds (mm)') + '</label><span class="wcs-pricing-actions">' + buttons('add-condition', 'remove-condition', 'alternative condition') + '</span></div>';
					});
					if (!group.alternatives.length) html += '<button type="button" class="button" data-action="add-condition">Add condition</button>';
					html += '</div></div>';
				});
				html += '<button type="button" class="button" data-action="add-group">Add shipping fee</button></fieldset>';
				html += '<div class="wcs-pricing-preview wcs-pricing-shipping-preview"><div class="wcs-pricing-group-heading"><strong>Shipping preview</strong><span class="wcs-pricing-matches" aria-live="polite"></span></div><div class="wcs-pricing-preview-fields">';
				fields.forEach(function (f, i) { html += '<label>' + esc(f.label) + '<span class="wcs-pricing-unit">' + number(draft.values[i], 'value-' + i, f.label + ' preview') + '<span>mm</span></span></label>'; });
				html += '<label>Test base price (€)' + number(draft.basePrice, 'base-price', 'Test base price (€)', '0.01') + '</label></div><div class="wcs-pricing-preview-summary"><span>Shipping total</span><strong class="wcs-pricing-result" aria-live="polite"></strong></div></div>';
			}
			$modal.find('.wcs-pricing-body').html(html + '</div>');
			preview();
		}
		function preview() {
			if (active < fields.length) {
				var f = fields[active];
				$modal.find('.wcs-pricing-result').text(amount(WCSPricing.dimension(draft.previews[f.id], draft.rules[f.id])) + ' surcharge');
			} else {
				var result = WCSPricing.shipping(draft.values, draft.basePrice, draft.shipping);
				$modal.find('.wcs-pricing-result').text(amount(result.total) + ' shipping');
				$modal.find('.wcs-pricing-matches').text(result.matched + ' fee groups matched');
			}
		}
		$modal.on('input change', '[data-prop]', function () {
			var $input = $(this), prop = $input.data('prop'), v = this.type === 'checkbox' ? this.checked : this.tagName === 'SELECT' ? this.value : Number(this.value);
			if (active < fields.length) {
				if (prop === 'preview') draft.previews[fields[active].id] = v;
				else draft.rules[fields[active].id][$input.closest('[data-rule]').data('rule')][prop] = v;
			} else if (prop === 'enabled') { draft.shipping.enabled = v; render(); }
			else if (prop === 'base-price') draft.basePrice = v;
			else if (prop.indexOf('value-') === 0) draft.values[Number(prop.slice(6))] = v;
			else {
				var group = draft.shipping.groups[$input.closest('[data-group]').data('group')];
				if (prop === 'fee') group.amount = v;
				else group.alternatives[$input.closest('[data-condition]').data('condition')][prop] = v;
			}
			preview();
		});
		function valid() {
			var bad = $modal.find('input, select').toArray().find(function (el) { return !el.checkValidity(); });
			if (bad) { bad.reportValidity(); return false; }
			return true;
		}
		$modal.on('click', '[data-tab]', function () { if (!valid()) return; active = Number($(this).data('tab')); render(); $modal.find('[aria-selected="true"]').trigger('focus'); });
		$modal.on('click', '[data-action]', function () {
			if (!valid()) return;
			var $button = $(this), action = $button.data('action'), gi = $button.closest('[data-group]').data('group'), ci = $button.closest('[data-condition]').data('condition');
			if (action === 'add-rule' || action === 'remove-rule') {
				var rules = draft.rules[fields[active].id], ri = $button.closest('[data-rule]').data('rule');
				if (action === 'remove-rule') rules.splice(ri, 1);
				else rules.splice(ri === undefined ? 0 : ri + 1, 0, { from: ri === undefined ? 401 : Number(rules[ri].from) + 50, amount: 0 });
			} else if (action === 'add-group') draft.shipping.groups.push({ id: 'fee-' + Date.now() + '-' + (++counter), amount: 0, alternatives: [{ first: 400, second: 400, match: 'OR' }] });
			else if (action === 'remove-group') draft.shipping.groups.splice(gi, 1);
			else if (action === 'add-condition') draft.shipping.groups[gi].alternatives.splice(ci === undefined ? 0 : ci + 1, 0, { first: 400, second: 400, match: 'AND' });
			else if (action === 'remove-condition') draft.shipping.groups[gi].alternatives.splice(ci, 1);
			render();
			$modal.find('[data-action="' + action + '"]').first().trigger('focus');
		});
		$modal.on('click', '.wcs-pricing-apply', function () {
			if (!valid()) return;
			fields.forEach(function (f) { f.row.data('pricingRules', copy(draft.rules[f.id])); });
			shipping = copy(draft.shipping); sync(); close();
		});
		$modal.on('click', '.wcs-pricing-close, .wcs-pricing-cancel', close);
		$modal.on('keydown', function (event) {
			if (event.key === 'Escape') { event.preventDefault(); close(); return; }
			if ($(event.target).is('[role="tab"]') && ['ArrowLeft', 'ArrowRight', 'Home', 'End'].indexOf(event.key) >= 0) {
				event.preventDefault(); if (!valid()) return;
				active = event.key === 'Home' ? 0 : event.key === 'End' ? fields.length : (active + (event.key === 'ArrowRight' ? 1 : fields.length)) % (fields.length + 1);
				render(); $modal.find('[aria-selected="true"]').trigger('focus');
			}
			if (event.key === 'Tab') {
				var $focusable = $modal.find('button, input, select, [tabindex]').filter(':visible').filter(function () { return !this.matches(':disabled') && this.tabIndex >= 0; });
				var first = $focusable[0], last = $focusable[$focusable.length - 1];
				if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
				else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
			}
		});
		$(document).on('click', '.wcs-configure-pricing', show).on('wcs:measurements-changed', refresh).on('input change blur', '.wcs-side-label, .wcs-side-key', refresh);
		$('#post').on('submit', function (event) {
			refresh();
			var seen = {}, bad = null;
			fields.forEach(function (f) {
				var input = f.row.find('.wcs-side-key')[0], label = f.row.find('.wcs-side-label')[0];
				input.setCustomValidity(seen[f.selection_key] ? 'Use a unique size field key.' : '');
				label.setCustomValidity(label.value.trim() ? '' : 'Enter a size field label.');
				seen[f.selection_key] = true;
				if (!input.checkValidity() || !label.checkValidity()) bad = bad || (!input.checkValidity() ? input : label);
			});
			if (bad) { event.preventDefault(); bad.reportValidity(); }
		});
		$(document).on('input', '.wcs-side-key, .wcs-side-label', function () {
			$('.wcs-side-key, .wcs-side-label').each(function () { this.setCustomValidity(''); });
		});
		refresh();
	});
})(jQuery);
