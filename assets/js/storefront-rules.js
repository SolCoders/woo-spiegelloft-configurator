(function ($) {
	'use strict';

	function splitArgs(value) {
		var args = [];
		var depth = 0;
		var start = 0;
		String(value || '').split('').forEach(function (char, index) {
			if (char === '(') {
				depth += 1;
			} else if (char === ')') {
				depth -= 1;
			} else if (char === ',' && depth === 0) {
				args.push(value.slice(start, index).trim());
				start = index + 1;
			}
		});
		args.push(String(value || '').slice(start).trim());
		return args;
	}

	function stripOuterParens(value) {
		var text = String(value || '').trim();
		var depth = 0;
		if (text.charAt(0) !== '(' || text.charAt(text.length - 1) !== ')') {
			return text;
		}
		for (var i = 0; i < text.length; i += 1) {
			if (text.charAt(i) === '(') {
				depth += 1;
			} else if (text.charAt(i) === ')') {
				depth -= 1;
				if (depth === 0 && i < text.length - 1) {
					return text;
				}
			}
		}
		return text.slice(1, -1).trim();
	}

	function splitTopLevel(value, operators) {
		var text = String(value || '').trim();
		var depth = 0;
		for (var i = text.length - 1; i >= 0; i -= 1) {
			var char = text.charAt(i);
			if (char === ')') {
				depth += 1;
			} else if (char === '(') {
				depth -= 1;
			} else if (depth === 0 && operators.indexOf(char) !== -1 && i > 0) {
				return [text.slice(0, i).trim(), char, text.slice(i + 1).trim()];
			}
		}
		return null;
	}

	function parseFormula(formula, values) {
		var text = stripOuterParens(String(formula || '').replace(/\{([^}]+)\}/g, function (match, key) {
			var value = parseFloat(values[String(key).trim()]);
			return isNaN(value) ? '0' : String(value);
		}));
		var fn = text.match(/^(min|max|round|floor|ceil)\((.*)\)$/);
		var parts;

		if (text === '') {
			return null;
		}
		if (/^-?\d+(\.\d+)?$/.test(text)) {
			return parseFloat(text);
		}
		if (fn) {
			parts = splitArgs(fn[2]).map(function (arg) {
				return parseFormula(arg, values);
			}).filter(function (value) {
				return value !== null && !isNaN(value);
			});
			if (!parts.length) {
				return null;
			}
			if (fn[1] === 'min') {
				return Math.min.apply(Math, parts);
			}
			if (fn[1] === 'max') {
				return Math.max.apply(Math, parts);
			}
			if (fn[1] === 'round') {
				return Math.round(parts[0]);
			}
			if (fn[1] === 'floor') {
				return Math.floor(parts[0]);
			}
			return Math.ceil(parts[0]);
		}
		parts = splitTopLevel(text, ['+', '-']);
		if (!parts) {
			parts = splitTopLevel(text, ['*', '/']);
		}
		if (parts) {
			var left = parseFormula(parts[0], values);
			var right = parseFormula(parts[2], values);
			if (left === null || right === null || (parts[1] === '/' && right === 0)) {
				return null;
			}
			return parts[1] === '+' ? left + right : parts[1] === '-' ? left - right : parts[1] === '*' ? left * right : left / right;
		}
		return null;
	}

	function parseRules($wrap) {
		try {
			var rules = JSON.parse($wrap.attr('data-rules') || '[]');
			return Array.isArray(rules) ? rules : [];
		} catch (e) {
			return [];
		}
	}

	function normalizeRule(rule) {
		var conditions = Array.isArray(rule.conditions) ? rule.conditions : [];
		var actions = Array.isArray(rule.actions) ? rule.actions : [];
		var when = rule.when || {};
		var key = rule.when_path || Object.keys(when)[0] || '';

		if (!conditions.length && key) {
			conditions = [{
				source: rule.when_source || 'category',
				path: key,
				field: rule.when_field || 'value',
				type: rule.when_source === 'dimension' ? 'number' : 'selection',
				operator: rule.when_operator || 'equals',
				value: when[key] || ''
			}];
		}
		if (!actions.length) {
			actions = [{
				action: rule.then || 'require',
				target_type: rule.target_type || 'category',
				target: rule.target || '',
				target_value: rule.target_value || '',
				value: rule.max || '',
				min: rule.min || '',
				max: rule.max || ''
			}];
		}
		return $.extend({}, rule, { conditions: conditions, actions: actions, match: rule.match === 'any' ? 'any' : 'all' });
	}

	function fieldValues($wrap) {
		var values = {};
		$wrap.find('.wcs-dimension-input, .wcs-choice-select, .wcs-position-choice, .wcs-customer-field-input').each(function () {
			var $field = $(this);
			var key = $field.data('key') || $field.data('group');
			if (key) {
				values[String(key)] = $field.val();
				if (String(key).indexOf('side_') === 0) {
					values[String(key).replace(/^side_/, '')] = $field.val();
				}
			}
		});
		return values;
	}

	function findField($wrap, path) {
		var escaped = String(path || '').replace(/"/g, '\\"');
		var $field = $wrap.find('[data-key="' + escaped + '"], [data-group="' + escaped + '"]').first();
		if ($field.length) {
			return $field;
		}
		var normalized = String(path || '').trim().toLowerCase();
		if (!normalized) {
			return $field;
		}
		return $wrap.find('.wcs-step-option-group').filter(function () {
			return String($(this).find('.wcs-option-heading h3, > h3').first().text() || '').trim().toLowerCase() === normalized;
		}).find('.wcs-choice-select').first();
	}

	function isEmpty(value) {
		return value === null || value === undefined || value === '' || value === 'none' || value === '---';
	}

	function compare(actual, expected, operator, type) {
		var list = String(expected || '').split(',').map(function (item) { return item.trim(); }).filter(Boolean);
		var a = type === 'number' ? parseFloat(actual) : String(actual || '');
		var e = type === 'number' ? parseFloat(expected) : String(expected || '');

		if (operator === 'selected' || operator === 'is_not_empty') {
			return !isEmpty(actual);
		}
		if (operator === 'empty' || operator === 'is_empty') {
			return isEmpty(actual);
		}
		if (operator === 'is_true') {
			return actual === true || actual === '1' || actual === 'true' || actual === 'yes';
		}
		if (operator === 'is_false') {
			return isEmpty(actual) || actual === false || actual === '0' || actual === 'false' || actual === 'no';
		}
		if (operator === 'one_of' || operator === 'not_one_of') {
			var hit = list.indexOf(String(actual || '')) !== -1;
			return operator === 'one_of' ? hit : !hit;
		}
		if (operator === 'contains' || operator === 'not_contains') {
			var contains = a.toLowerCase().indexOf(e.toLowerCase()) !== -1;
			return operator === 'contains' ? contains : !contains;
		}
		if (['greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal'].indexOf(operator) !== -1) {
			if (isNaN(a) || isNaN(e)) {
				return false;
			}
			return operator === 'greater_than' ? a > e : operator === 'greater_than_or_equal' ? a >= e : operator === 'less_than' ? a < e : a <= e;
		}
		return operator === 'not_equals' ? String(actual || '') !== String(expected || '') : String(actual || '') === String(expected || '');
	}

	function setDisabled($field, disabled) {
		var $group = $field.closest('.wcs-step-option-group, .wcs-customer-field, .wcs-size-side-row, .wcs-size-box label');
		$field.prop('disabled', disabled);
		$field.next('.wcs-custom-select').find('button').toggleClass('is-disabled', disabled);
		$field.next('.wcs-image-choice-list').find('button').toggleClass('is-disabled', disabled);
		$group.toggleClass('is-wcs-rule-disabled', disabled).attr('aria-disabled', disabled ? 'true' : 'false');
		if (disabled) {
			$group.find('.wcs-customer-field-input, .wcs-position-choice').val('');
			$group.find('.wcs-image-choice-card').removeClass('is-selected').attr('aria-checked', 'false');
			$group.find('.wcs-custom-select__option').removeClass('is-selected');
			$group.find('.wcs-custom-select__value').text('Please select');
			$group.find('.wcs-customer-nested-target').prop('hidden', true).empty();
			$group.find('.wcs-customer-nested-box').remove();
			$group.find('.wcs-customer-field').removeClass('is-empty');
			$group.find('.wcs-customer-field__error').prop('hidden', true);
		}
	}

	function setVisible($field, visible) {
		$field.closest('.wcs-step-option-group, .wcs-customer-field, .wcs-size-side-row, .wcs-size-box label').prop('hidden', !visible).toggle(visible);
		if (!visible) {
			$field.val('').triggerHandler('change');
		}
	}

	function executeAction($wrap, action, matched, values) {
		var name = action.action || action.then || '';
		var target = ['require_value', 'disallow_value'].indexOf(name) !== -1 ? (action.target || '') : (action.target || action.target_value || '');
		var $field = findField($wrap, target);
		var value = action.value || action.max || '';
		var computed = String(value).indexOf('{') !== -1 || /^[\d\s+\-*/().,a-z]+$/i.test(String(value)) ? parseFormula(value, values) : null;

		if (!$field.length) {
			return;
		}
		if (name === 'disable' || name === 'disable_option' || name === 'enable') {
			setDisabled($field, name === 'enable' ? !matched : matched);
		} else if (name === 'show' || name === 'hide') {
			setVisible($field, name === 'show' ? matched : !matched);
		} else if (name === 'clear' && matched) {
			$field.val('').triggerHandler('change');
		} else if ((name === 'set_max' || name === 'validate_range') && matched && computed !== null) {
			$field.attr('max', computed);
			if (parseFloat($field.val()) > computed) {
				$field.val(computed).triggerHandler('change');
			}
		} else if (name === 'set_min' && matched && computed !== null) {
			$field.attr('min', computed);
			if (parseFloat($field.val()) < computed) {
				$field.val(computed).triggerHandler('change');
			}
		}
	}

	function RuleEngine($wrap) {
		this.$wrap = $wrap;
		this.rules = parseRules($wrap).map(normalizeRule);
		this.dependents = {};
		this.rules.forEach(function (rule, index) {
			rule.conditions.forEach(function (condition) {
				var key = condition.path || '';
				if (!key) {
					return;
				}
				this.dependents[key] = this.dependents[key] || [];
				this.dependents[key].push(index);
			}, this);
		}, this);
	}

	RuleEngine.prototype.evaluate = function (changedKey) {
		var values = fieldValues(this.$wrap);
		var changedKeys = changedKey ? [String(changedKey)] : [];
		if (changedKey && String(changedKey).indexOf('side_') === 0) {
			changedKeys.push(String(changedKey).replace(/^side_/, ''));
		}
		var indexes = [];
		changedKeys.forEach(function (key) {
			if (this.dependents[key]) {
				indexes = indexes.concat(this.dependents[key]);
			}
		}, this);
		if (!indexes.length) {
			indexes = this.rules.map(function (rule, index) { return index; });
		}
		var seen = {};
		indexes.forEach(function (index) {
			if (seen[index]) {
				return;
			}
			seen[index] = true;
			var rule = this.rules[index];
			var results = rule.conditions.map(function (condition) {
				var actual = values[condition.path];
				return compare(actual, condition.value, condition.operator, condition.type);
			});
			var matched = rule.match === 'any' ? results.indexOf(true) !== -1 : results.indexOf(false) === -1;
			rule.actions.forEach(function (action) {
				executeAction(this.$wrap, action, matched, values);
			}, this);
		}, this);
	};

	window.WCSRuleEngine = RuleEngine;
}(jQuery));
