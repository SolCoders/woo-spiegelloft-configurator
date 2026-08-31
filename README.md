# Woo Spiegelloft Rule Engine

## Rule Schema

Rules are stored per template in `validation_rules` and mirrored to `behavior_rules`. Existing legacy keys are still saved for compatibility, but new rules use this generic shape:

```json
{
  "rule_type": "constraint",
  "match": "all",
  "conditions": [
    {
      "source": "dimension",
      "path": "width",
      "field": "value",
      "type": "number",
      "operator": "equals",
      "value": "500"
    }
  ],
  "actions": [
    {
      "action": "set_max",
      "target_type": "dimension",
      "target": "x",
      "target_value": "x",
      "value": "{width} - 100"
    }
  ],
  "message": "",
  "error_seconds": 4,
  "restore": false
}
```

Rule types are merchant-facing categories: `required_field`, `constraint`, `availability`, `visibility`, `selection_rule`, `block`, `clear`, `range`. Conditions support selection/text operators such as `equals`, `not_equals`, `contains`, `is_empty`, `is_not_empty`, `one_of`, and numeric operators such as `greater_than`, `greater_than_or_equal`, `less_than`, `less_than_or_equal`. Boolean fields support `is_true` and `is_false`.

Numeric formulas may use `{field_key}` placeholders, arithmetic, parentheses, and `min`, `max`, `round`, `floor`, `ceil`. They are parsed by the rule engine and are not run through `eval` or `new Function`.

## Merchant Examples

Computed numeric constraint:

1. Add a rule and choose `Computed constraint`.
2. Under `When this is true`, set Source to `Dimension field`, Category/path to `width`, Data type to `Number`, Condition to `equals`, Value to `500`.
3. Under `Do this`, set Action to `Set maximum`, Target type to `Dimension field`, Target value/path to `x`, Value/formula to `{width} - 100`.

Disable a field when a numeric value is too small:

1. Add a rule and choose `Enable/disable`.
2. Set the condition to Source `Dimension field`, path `x`, Data type `Number`, Condition `less than`, Value `500`.
3. Set the action to `Disable`, Target type `Dimension field`, Target value/path `y`.

Enable a field only when two numeric values are large enough:

1. Add a rule and choose `Enable/disable`.
2. Set Match to `All rows (AND)`.
3. Add two conditions: `x` `greater than or equal` `500`, and `y` `greater than or equal` `500`.
4. Set the action to `Enable`, Target type to the correct field type, Target value/path `z`.

String or selection rule:

1. Add a rule and choose `Selection/text rule`.
2. For a category selection, set Source `Category`, choose/path the category, Data type `Selection`, Condition `equals` or `is one of`, and enter the option value. For text, use Source `Nested field`, path such as `sockets.color`, Data type `Text`, and Condition `contains`.
3. Add a visibility or availability action, for example `Show` target `z` or `Disable` target `y`.
