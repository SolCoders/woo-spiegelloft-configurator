# Woo Spiegelloft Rule Engine

## Mirror layout import and export

On **Mirror Builder → Mirror Layouts**, use **Import Layout** beside Add New, or a layout's **Export JSON** row action. Each layout editor also has an **Import / Export** panel. Export reads persisted settings, so save changes first. New, unsaved layouts cannot be exported.

Importing updates an existing template with the same name, ignoring capitalization and repeated whitespace while preserving punctuation and accents. If several published or draft templates match, choose the target before anything changes. If none matches, a new template is created. The success notice distinguishes creation from update and links to the template.

Updates replace the template configuration, including dimensions, pricing, options, nested fields, defaults, steps, and rules. The destination template ID, slug, publication status, and product assignments remain unchanged. Existing options are matched within that template by group and slug, falling back to group and title. New options are added and omitted options are detached, not deleted. Shared options are copied before changing them so other templates remain unaffected. Repeated imports reuse the same template and private option IDs.

The standalone JSON includes dimensions, enabled choices, prices, nested customer fields, required flags, ordering, steps, restrictions, legacy settings, group definitions, and position settings. All images, products, orders, and unrelated site settings are excluded. Both sites require this plugin and WooCommerce.

### Portable package version 2

The transfer package is separate from the storefront REST configuration. Its top-level structure is:

```json
{
  "format": "wcs-mirror-layout",
  "version": 2,
  "template": { "title": "Mirror layout", "status": "publish", "data": {} },
  "options": [],
  "groups": {},
  "positions": {}
}
```

This illustrates the envelope; `template.data` in real exports contains the complete persisted layout settings. Option records contain their source `id`, `title`, `slug`, `group`, and supported option metadata. Source IDs are references only: import maps them to reused or newly created destination IDs throughout option selections, ordering, step keys, synthetic groups, rule paths, legacy rule-map keys, and formulas. Semantic option values and legacy identifiers remain intact. Newly created templates receive unique slugs; updates retain the destination slug.

There is no `media` section. Export clears image values recursively and never reads image files or downloads source URLs. When updating, existing destination images are preserved for matching options, nested fields, dropdown values, positions, and image-field definitions. Stable field keys and option values preserve those associations when rows are reordered. Removed fields are not restored. New options and unmatched image fields start blank and need manual uploads. Import never downloads images or creates Media Library attachments.

Version 1 packages are still accepted, including files without a `media` section. Any supplied media data, source image URLs, and `wcs-media:` references are ignored; they cannot replace destination images. For AI conversions, use version 2, omit `media`, and leave image values empty.

Legacy groups with implicit “all options” selections export their current effective options explicitly. Imported group/position definitions are template-local snapshots, and copied options are not added to shared taxonomy memberships. This prevents imports from changing existing layouts. Imported option editors retain their group field definitions through option-local snapshots.

Older flat-editor layouts may have saved choice order/steps without an option selection map. These layouts resolve the checked catalog choices even when those choices have no category. The editor, exporter, and storefront share this resolution. New editor saves mark selections explicitly, so unchecking every choice remains empty. Files previously exported without their customer option data must be exported again from the source site after updating the plugin; importing the old empty file cannot reconstruct missing options.

### Limits and validation

The JSON limit is **50 MiB**, and nesting is limited to **32 levels** with **100,000 data items**. PHP upload, request, memory, and execution limits also apply; the import form shows the effective upload limit.

The package, name matches, option matches, and edit permissions are checked before content changes. Imports of the same template name are serialized. On a handled failure, existing template/option snapshots are restored and newly created records are deleted; restoration failures are reported. Duplicate-name uploads expire after 15 minutes and are tied to the uploading user. Process termination or a server timeout cannot guarantee rollback; ensure the server can process the package within its request limits.

Admin actions require `manage_woocommerce`, a valid action nonce, and appropriate post capabilities. Media upload permission is not required for layout import. JSON files are read directly from the verified PHP upload and are not stored in the Media Library. New transfer services live in `includes/transfer/`; HTTP authorization and presentation live in the dedicated admin controller and templates.

### Development checks

Run the disposable integration checks **on a development site only**:

```shell
php tests/layout-transfer-integration.php /absolute/path/to/wp-load.php
php tests/layout-update-integration.php /absolute/path/to/wp-load.php
```

The runner creates its own layouts, options, product, and taxonomy term and removes those fixtures in `finally`. It checks round trips, ID remapping, image-field clearing, zero media requests/attachments, legacy packages, isolation, repeated imports, editor saves, assigned-product configuration, invalid packages, permissions, and rollback. It does not replace browser checks of the upload/download flow.

For a live browser smoke test on **localhost only**, with Node.js 22+, PHP, and Chromium installed:

```shell
node tests/layout-transfer-browser.mjs /absolute/path/to/wp-load.php /absolute/path/to/chrome
```

This uses disposable fixtures and a short-lived administrator session, checks the real listing/editor/upload/download/storefront flow, then deletes its fixtures and revokes that session. Authentication values are not printed. A screenshot is retained in the system temporary directory.

Check the transfer subsystem against WordPress Coding Standards with `phpcs --standard=phpcs-transfer.xml`. This scoped ruleset declares WooCommerce's `manage_woocommerce` capability and does not reformat unrelated existing plugin code.

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

### Dimension pricing and shipping

Open **Configure pricing** in a mirror template's Size limits card. Each size field has its own cumulative threshold rules and preview. **Apply rules** updates the editor; save/update the template to persist changes. Cancel or Escape discards modal edits. At least one size field must remain, and field keys must be unique.

Each size field also has a **Step** setting (default `1`). It sets the frontend number input's increment and the +/− buttons, alongside Min and Max. Positive decimal steps are supported. Steps are included in layout JSON transfers; older layouts without a step use `1`.

Templates without saved pricing use the default threshold table. Every reached threshold adds its surcharge. An explicitly empty rule list adds nothing. The configured item price includes the product price, dimension surcharges, selected extras and shipping. The admin shipping preview uses a manually entered test base price; this test value is not saved or used for customer charges.

Shipping defaults on for one or two size fields. One field acts as a diameter for both shipping dimensions but contributes its dimension surcharge once. Two fields use their order in Size limits. Templates with more than two fields have no shipping or markup. Turning **Enable shipping** off also removes both.

The default base shipping group adds €36 plus a fixed 33% of the WooCommerce product price when either measurement exceeds 400 mm. The large-size group adds €34 once when either orientation exceeds 1200 × 700 mm. Alternative condition rows within a fee group are OR alternatives; matching fee groups add together. Thresholds use strict `>` for shipping and inclusive `>=` for dimension pricing. Existing cart lines with stored prices retain those prices. Pricing travels with settings-only layout JSON exports.

Local checks: `php tests/pricing-integration.php /path/to/wp-load.php` and `node tests/pricing-browser.mjs /path/to/wp-load.php /path/to/chrome`. The browser test uses disposable localhost fixtures and restores the test administrator's persistent cart. Set `WCS_TEST_PHP` to the PHP executable if it is not on Node's PATH.
