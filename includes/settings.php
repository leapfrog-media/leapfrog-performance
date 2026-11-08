<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/**
 * Settings for every feature live in one option, by feature. Each feature
 * declares its fields (type, default) once; defaults are Leapfrog's standard
 * settings, written on activation so a new site starts with them.
 */

/** Field definitions, by feature. Features add theirs through this filter. */
function field_definitions(): array
{
    return apply_filters('leapfrog_performance_fields', []);
}

function defaults(string $feature): array
{
    $values = [];
    foreach (field_definitions()[$feature] ?? [] as $key => $field) {
        $values[$key] = $field['default'];
    }
    return $values;
}

function settings(string $feature): array
{
    $all = get_option(OPTION_SETTINGS, []);
    $saved = is_array($all) && is_array($all[$feature] ?? null) ? $all[$feature] : [];
    $result = defaults($feature);
    foreach (field_definitions()[$feature] ?? [] as $key => $field) {
        if (array_key_exists($key, $saved)) {
            $clean = clean_value($saved[$key], $field);
            if ($clean !== null) { $result[$key] = $clean; }
        }
    }
    return $result;
}

function setting(string $feature, string $key)
{
    return settings($feature)[$key] ?? null;
}

/** A value of the field's type, or null when it isn't valid. */
function clean_value($value, array $field)
{
    switch ($field['type']) {
        case 'bool':
            return (bool) $value;
        case 'number':
            if ($value === '' || $value === null) { return $field['allow_empty'] ?? false ? '' : null; }
            if (!is_numeric($value)) { return null; }
            return max($field['min'], min($field['max'], (int) $value));
        case 'choice':
            return is_string($value) && isset($field['choices'][$value]) ? $value : null;
        case 'list':
            return is_array($value) ? array_values(array_filter(array_map('sanitize_key', $value))) : null;
    }
    return null;
}

/** Save posted values for a feature's fields (missing tick boxes count as off). */
function save_settings(string $feature, array $posted): void
{
    $all = get_option(OPTION_SETTINGS, []);
    $all = is_array($all) ? $all : [];
    $values = settings($feature);
    foreach (field_definitions()[$feature] ?? [] as $key => $field) {
        $raw = $posted[$key] ?? ($field['type'] === 'bool' ? false : ($field['type'] === 'list' ? [] : null));
        $clean = clean_value($raw, $field);
        if ($clean !== null) { $values[$key] = $clean; }
    }
    $all[$feature] = $values;
    update_option(OPTION_SETTINGS, $all);
}

/** Replace some of a feature's values (used by the importer). */
function update_settings(string $feature, array $values): void
{
    $all = get_option(OPTION_SETTINGS, []);
    $all = is_array($all) ? $all : [];
    $all[$feature] = array_merge(settings($feature), $values);
    update_option(OPTION_SETTINGS, $all);
}

/**
 * On activation, a site with no Leapfrog Performance settings yet gets the
 * standard settings written in full, so later changes to the defaults don't
 * change a site that is already set up.
 */
function activate(): void
{
    require_once __DIR__ . '/bloat.php';
    add_filter('leapfrog_performance_fields', __NAMESPACE__ . '\\bloat_fields');
    if (get_option(OPTION_SETTINGS, false) === false) {
        $all = [];
        foreach (array_keys(field_definitions()) as $feature) {
            $all[$feature] = defaults($feature);
        }
        add_option(OPTION_SETTINGS, $all);
    }
    update_option(OPTION_VERSION, VERSION);
}
