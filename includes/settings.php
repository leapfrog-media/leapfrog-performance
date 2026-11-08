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
        case 'text':
            return is_scalar($value) ? sanitize_text_field((string) $value) : null;
        case 'textarea':
            return is_scalar($value) ? sanitize_textarea_field((string) $value) : null;
        case 'post':
            return is_numeric($value) ? absint($value) : null;
        case 'secret':
            // Stored as a hash by the feature; the form never shows it.
            return is_string($value) ? $value : null;
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
        // A secret left empty keeps the saved one.
        if ($field['type'] === 'secret' && ($posted[$key] ?? '') === '') { continue; }
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
    // The plugin file has just run, so every switched-on feature's fields are known.
    if (get_option(OPTION_SETTINGS, false) === false) {
        $all = [];
        foreach (array_keys(field_definitions()) as $feature) {
            $all[$feature] = defaults($feature);
        }
        add_option(OPTION_SETTINGS, $all);
    }
    update_option(OPTION_VERSION, VERSION);
}
