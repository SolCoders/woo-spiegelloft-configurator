(function (root) {
	'use strict';
	function dimension(value, rules) {
		if (value === null || value === '' || !Number.isFinite(Number(value))) return 0;
		return (rules || []).reduce(function (sum, rule) { return sum + (Number(value) >= rule.from ? Number(rule.amount) : 0); }, 0);
	}
	function shipping(values, basePrice, settings) {
		var total = 0, matched = 0, markedUp = false;
		if (!settings || !settings.enabled || !values.length || values.length > 2 || values.some(function (v) { return v === null || v === '' || !Number.isFinite(Number(v)); })) return { total: 0, matched: 0 };
		var first = Number(values[0]), second = Number(values.length === 1 ? values[0] : values[1]);
		(settings.groups || []).forEach(function (group) {
			if (!(group.alternatives || []).some(function (c) {
				var a = first > c.first, b = second > c.second;
				return c.match === 'OR' ? a || b : a && b;
			})) return;
			total += Number(group.amount);
			if (group.id === 'base' && !markedUp) { total += basePrice * 0.33; markedUp = true; }
			matched++;
		});
		return { total: total, matched: matched };
	}
	function calculate(settings, fields, selections, basePrice) {
		var subtotal = 0, values = [];
		fields.forEach(function (field) {
			var key = field.selection_key, value = selections[key];
			values.push(value === undefined ? null : value);
			subtotal += dimension(value === undefined ? null : value, settings.dimensions[key]);
		});
		var freight = shipping(values, Number(basePrice) || 0, settings.shipping);
		return { dimensions: subtotal, shipping: freight.total, total: subtotal + freight.total };
	}
	root.WCSPricing = { dimension: dimension, shipping: shipping, calculate: calculate };
})(typeof window !== 'undefined' ? window : globalThis);
