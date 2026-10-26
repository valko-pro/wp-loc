<?php
/** Run with active ACF: wp eval-file wp-content/plugins/wp-loc/tests/acf-readonly.php */
if (!defined('WP_CLI') || !WP_CLI) { exit; }
if (!class_exists('WP_LOC_ACF') || !function_exists('acf_render_field_wrap')) { WP_CLI::error('Active WP LOC and ACF are required.'); }

// Read-only runtime fixture: no stored fields/posts, no HTTP, no save actions.
$writes = 0;
add_filter('query', static function ($sql) use (&$writes) {
    if (!preg_match('/^\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i', $sql)) {
        $writes++;
        throw new RuntimeException('Regression check blocked a database write.');
    }
    return $sql;
}, PHP_INT_MAX);
add_filter('pre_http_request', static function () { throw new RuntimeException('Regression check blocked HTTP.'); }, PHP_INT_MAX);
// Admin notification persistence is unrelated to field rendering.
remove_all_actions('shutdown');
require_once ABSPATH . 'wp-admin/includes/screen.php';
set_current_screen('post');
$integration = (new ReflectionClass(WP_LOC_ACF::class))->newInstanceWithoutConstructor();
$assertions = 0;
$check = static function ($condition, $message) use (&$assertions) {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};
$field = static function ($type, array $extra = []) {
    return acf_get_valid_field(array_merge([
        'key' => 'field_wp_loc_readonly_regression_' . $type,
        'name' => 'wp_loc_readonly_regression_' . $type,
        'label' => 'Readonly regression', 'type' => $type,
        'translation_mode' => 'shared', 'choices' => ['0' => 'Zero', 'h2' => 'H2', 'h3' => 'H3'],
    ], $extra));
};
$render = static function (array $field) {
    $level = ob_get_level(); ob_start();
    try { acf_render_field_wrap($field); return ob_get_clean(); }
    catch (Throwable $error) { while (ob_get_level() > $level) { ob_end_clean(); } throw $error; }
};
$inputs = static function ($html, $type) {
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    return (new DOMXPath($doc))->query('//input[@type="' . $type . '"]');
};

try {
    // options_{language} exercises the actual translated-options readonly branch
    // without creating a translated post or changing any existing option.
    $_GET = ['post' => 'options_regression']; $_POST = [];
    $checkbox = $integration->handle_load_field($field('checkbox'));
    $check(is_array($checkbox['disabled']), 'Checkbox disabled must be a per-choice array, not scalar 1.');
    $check($checkbox['disabled'] === ['0', 'h2', 'h3'], 'All checkbox choices, including numeric zero, are disabled.');
    $checkbox['value'] = ['0', 'h3'];
    $checkbox_html = $render($checkbox);
    $checkbox_inputs = $inputs($checkbox_html, 'checkbox');
    $check($checkbox_inputs->length === 3, 'Actual ACF renders three checkbox choices without fatal.');
    $checked = 0;
    foreach ($checkbox_inputs as $input) {
        $check($input->hasAttribute('disabled'), 'Every checkbox choice stays disabled.');
        $checked += $input->hasAttribute('checked') ? 1 : 0;
    }
    $check($checked === 2, 'Readonly rendering preserves current selections.');
    $check($checkbox['readonly'] === 1 && str_contains($checkbox['wrapper']['class'], 'acf-disabled'), 'Existing readonly flags/class are preserved.');

    $nested = $integration->handle_load_field($field('checkbox', ['choices' => ['Group' => ['a' => 'A', 'Nested' => ['b' => 'B']], 'c' => 'C']]));
    $check($nested['disabled'] === ['a', 'b', 'c'], 'Nested optgroups disable leaf values, not group labels.');
    $nested['value'] = ['a', 'custom']; $nested['allow_custom'] = 1;
    $prepared = acf_prepare_field($nested);
    $check(in_array('custom', $prepared['disabled'], true), 'Custom values loaded after load_field enter disabled choices at prepare time.');
    $nested_html = $render($nested);
    $check(str_contains($nested_html, 'custom'), 'Current custom value remains visible.');
    foreach ($inputs($nested_html, 'checkbox') as $input) {
        if (in_array($input->getAttribute('value'), ['a', 'b', 'c'], true)) {
            $check($input->hasAttribute('disabled'), 'Nested known choices remain disabled.');
        }
    }

    $radio = $integration->handle_load_field($field('radio', ['other_choice' => 1]));
    $radio['value'] = 'custom';
    $prepared = acf_prepare_field($radio);
    $check(in_array('other', $prepared['disabled'], true) && in_array('custom', $prepared['disabled'], true), 'Radio Other and current custom value use array disabled contract.');
    $radio_inputs = $inputs($render($radio), 'radio');
    $check($radio_inputs->length === 4, 'Actual radio renders normal and Other choices.');
    foreach ($radio_inputs as $input) { $check($input->hasAttribute('disabled'), 'Every radio choice remains disabled.'); }

    $select = $integration->handle_load_field($field('select', ['value' => 'h2']));
    $check($select['disabled'] === 1, 'Select keeps its scalar disabled contract.');
    $select_html = $render($select);
    $doc = new DOMDocument(); @$doc->loadHTML($select_html);
    $check((new DOMXPath($doc))->query('//select[@disabled]')->length === 1, 'Actual ACF select remains disabled.');
    $check($integration->handle_load_field($field('button_group'))['disabled'] === 1, 'Other field contracts are unchanged.');

    $base = $field('checkbox', ['disabled' => ['h3']]);
    $_GET = ['post' => 'options'];
    $original = $integration->handle_load_field($base);
    $check($original['disabled'] === ['h3'] && empty($original['readonly']), 'Source options retain their pre-existing disabled choices.');
    $check(empty($original['_wp_loc_readonly_choices']), 'Source fields never receive the readonly-choice marker.');
    $_GET = ['post' => 'options_regression'];
    foreach (['none', 'copy_once', 'translatable'] as $mode) {
        $editable = $integration->handle_load_field($field('checkbox', ['translation_mode' => $mode, 'disabled' => ['h3']]));
        $check($editable['disabled'] === ['h3'] && empty($editable['readonly']), 'Non-shared mode remains editable: ' . $mode);
    }
    $check($integration->prepare_readonly_choice_field(false) === false, 'Earlier prepare filters may still hide a field.');
    $check($integration->prepare_readonly_choice_field($base) === $base, 'Unmarked fields are not changed by the new prepare filter.');
    $GLOBALS['current_screen'] = null;
    $check($integration->handle_load_field($base) === $base, 'Frontend load_field is unchanged.');
    $check($writes === 0, 'No DB writes attempted.');
    echo wp_json_encode(['passed' => $assertions, 'acf' => acf_get_setting('version'), 'db_writes' => $writes, 'mode' => 'Actual WP LOC load/prepare filters and ACF rendering; no stored fixtures or HTTP']) . "\n";
} catch (Throwable $error) {
    WP_CLI::error(get_class($error) . ': ' . $error->getMessage());
}
