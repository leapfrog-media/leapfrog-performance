<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/**
 * Import settings from the plugins Leapfrog Performance replaces, for moving an
 * existing site such as Praxis: ASE (Admin and Site Enhancements), FlyingPress,
 * Disable Media Pages, Disable Media Sizes and the Praxis mu-plugins. The
 * Features tab lists what each would change; nothing is saved until the ticked
 * rows are imported.
 *
 * Only what another plugin actually does is offered: an option it has switched
 * off says nothing about what this site wants, so an import never undoes a
 * Leapfrog setting.
 */

function ase_settings(): ?array
{
    $ase = get_option('admin_site_enhancements', null);
    return is_array($ase) ? $ase : null;
}

function flying_press_settings(): ?array
{
    $config = get_option('FLYING_PRESS_CONFIG', null);
    return is_array($config) ? $config : null;
}

function plugin_active(string $file): bool
{
    return in_array($file, (array) get_option('active_plugins', []), true);
}

/** ASE's Heartbeat setting for one place, as a Leapfrog choice. */
function ase_heartbeat(array $ase, string $place, array $allowed): ?string
{
    if (empty($ase['enable_heartbeat_control'])) { return null; }
    $mode = (string) ($ase['heartbeat_control_for_' . $place] ?? 'default');
    if ($mode === 'disable') { return in_array('off', $allowed, true) ? 'off' : null; }
    if ($mode !== 'modify') { return 'default'; }
    $seconds = (int) ($ase['heartbeat_interval_for_' . $place] ?? 60);
    // The nearest interval Leapfrog offers.
    $numbers = array_values(array_filter($allowed, 'is_numeric'));
    usort($numbers, static function ($a, $b) use ($seconds) { return abs($a - $seconds) <=> abs($b - $seconds); });
    return $numbers ? (string) $numbers[0] : null;
}

/**
 * Proposed values: ['feature.key' => ['feature', 'key', 'from' => [source => value], 'value' => chosen]].
 * The first source with a value wins (ASE, then FlyingPress, then the others).
 */
function import_proposals(): array
{
    $rows = [];
    $add = static function (string $feature, string $key, string $source, $value) use (&$rows) {
        if ($value === null || $value === false || $value === '' || $value === 'keep' || $value === []) { return; }
        if (!feature_enabled($feature)) { return; }
        $id = $feature . '.' . $key;
        $rows[$id]['feature'] = $feature;
        $rows[$id]['key'] = $key;
        $rows[$id]['from'][$source] = $value;
    };

    $ase = ase_settings();
    if ($ase !== null) {
        $flag = static function (string $name) use ($ase) { return !empty($ase[$name]); };
        $add('bloat', 'emoji', 'ASE', $flag('disable_emoji_support'));
        $add('bloat', 'feeds', 'ASE', $flag('disable_feeds'));
        $add('bloat', 'jquery_migrate', 'ASE', $flag('disable_jquery_migrate'));
        $add('bloat', 'head_clutter', 'ASE', $flag('disable_smaller_components'));
        $add('bloat', 'wp_lazy_load', 'ASE', $flag('disable_lazy_load'));
        $add('bloat', 'revisions', 'ASE', $flag('enable_revisions_control') ? (int) ($ase['revisions_max_number'] ?? 10) : null);
        $add('bloat', 'heartbeat_admin', 'ASE', ase_heartbeat($ase, 'admin_pages', ['default', '60', '120', 'off']));
        $add('bloat', 'heartbeat_editor', 'ASE', ase_heartbeat($ase, 'post_edit', ['default', '30', '60']));
        $add('bloat', 'heartbeat_site', 'ASE', ase_heartbeat($ase, 'frontend', ['default', 'off']));
        $add('bloat', 'admin_email_check', 'ASE', $flag('disable_site_admin_email_verification_screen'));
        $add('bloat', 'password_admin_email', 'ASE', $flag('disable_admin_email_notification_after_password_change'));
        $add('bloat', 'password_user_email', 'ASE', $flag('disable_user_email_notification_after_password_change'));
        if ($flag('disable_comments')) {
            $type = (string) ($ase['disable_comments_type'] ?? 'all');
            $for = array_keys(array_filter((array) ($ase['disable_comments_for'] ?? [])));
            if ($type === 'only-on') {
                $add('bloat', 'comments', 'ASE', $for ? 'some' : null);
                $add('bloat', 'comments_types', 'ASE', $for);
            } elseif ($type === 'except-on') {
                $add('bloat', 'comments', 'ASE', 'some');
                $add('bloat', 'comments_types', 'ASE', array_values(array_diff(array_keys(get_post_types(['public' => true])), $for)));
            } else {
                $add('bloat', 'comments', 'ASE', 'off');
            }
        }
        $add('media', 'avif', 'ASE', $flag('enable_avif_upload'));
        $add('media', 'sizes_off', 'ASE', array_keys(array_filter((array) ($ase['disabled_image_sizes'] ?? []))));
    }

    $fp = flying_press_settings();
    if ($fp !== null) {
        $on = static function (string $name) use ($fp) { return !empty($fp[$name]); };
        $add('bloat', 'block_css', 'FlyingPress', $on('bloat_disable_block_css'));
        $add('bloat', 'dashicons', 'FlyingPress', $on('bloat_disable_dashicons'));
        $add('bloat', 'emoji', 'FlyingPress', $on('bloat_disable_emojis'));
        $add('bloat', 'jquery_migrate', 'FlyingPress', $on('bloat_disable_jquery_migrate'));
        $add('bloat', 'feeds', 'FlyingPress', $on('bloat_disable_rss_feed'));
        $add('bloat', 'embeds', 'FlyingPress', $on('bloat_disable_oembeds'));
        $add('bloat', 'revisions', 'FlyingPress', $on('bloat_post_revisions_control') ? 3 : null);
        $add('bloat', 'heartbeat_admin', 'FlyingPress', $on('bloat_heartbeat_control') ? '60' : null);
        $add('bloat', 'heartbeat_editor', 'FlyingPress', $on('bloat_heartbeat_control') ? '60' : null);
    }

    if (plugin_active('disable-media-pages/disable-media-pages.php')) {
        $add('media', 'media_pages', 'Disable Media Pages', true);
    }
    $dms = get_option('disable_media_sizes_options', null);
    if (is_array($dms) && plugin_active('disable-media-sizes/disable-media-sizes.php')) {
        $off = [];
        foreach ($dms as $name => $value) {
            if ($value && strpos($name, 'disable-size-') === 0 && $name !== 'disable-size-big') {
                $size = substr($name, 13); // after "disable-size-"
                $off[] = $size === 'medium-large' ? 'medium_large' : $size;
            }
        }
        $add('media', 'sizes_off', 'Disable Media Sizes', $off);
        $add('media', 'big_image_off', 'Disable Media Sizes', !empty($dms['disable-size-big']));
    }
    $mu = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : '';
    if ($mu !== '' && file_exists($mu . '/pl-social-image-size.php')) {
        $add('media', 'social_size', 'pl-social-image-size', true);
    }
    if ($mu !== '' && file_exists($mu . '/pl-hide-captions.php')) {
        $add('media', 'hide_captions', 'pl-hide-captions', true);
    }

    foreach ($rows as &$row) {
        $row['value'] = reset($row['from']);
    }
    unset($row);
    return $rows;
}

function import_label($value, array $field, string $feature = ''): string
{
    // Bloat switches remove things; other switches turn something on.
    if ($field['type'] === 'bool') { return $feature === 'bloat' ? ($value ? 'Removed' : 'Kept') : ($value ? 'On' : 'Off'); }
    if ($field['type'] === 'choice') { return (string) ($field['choices'][$value] ?? $value); }
    if ($field['type'] === 'list') { return $value ? implode(', ', (array) $value) : 'none'; }
    if ($field['type'] === 'number') { return $value === '' ? ($field['placeholder'] ?? 'not set') : $value . ' ' . ($field['unit'] ?? ''); }
    return (string) $value;
}

function render_import_section(): void
{
    echo '<h2>Import settings</h2>';
    $rows = import_proposals();
    if (!$rows) {
        echo '<p class="description">Nothing to import. Settings are offered here from ASE (Admin and Site Enhancements), FlyingPress, Disable Media Pages, Disable Media Sizes and the Praxis mu-plugins when they are on this site and do something Leapfrog’s tabs cover.</p>';
        return;
    }
    $definitions = field_definitions();
    $sources = [];
    foreach ($rows as $row) { $sources += array_flip(array_keys($row['from'])); }
    ?>
    <p class="lfp-narrow-text">Found in <?php echo esc_html(implode(', ', array_keys($sources))); ?>. Ticked rows are imported; nothing changes until you click Import. Afterwards, switch the same settings off in those plugins (or remove the mu-plugins) so they aren’t done twice.<?php if (!empty(flying_press_settings()['bloat_disable_xml_rpc'])) { echo ' FlyingPress switches XML-RPC off: switch it off in FluentAuth instead.'; } ?><?php if (ase_settings() !== null) { echo ' ASE’s “Bricks Image Size Options” snippet, if used, is replaced by the “In the pickers” column on the Media tab: set it there.'; } ?></p>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="leapfrog_performance_import">
        <?php wp_nonce_field('leapfrog_performance_import', '_lfp_nonce'); ?>
        <table class="widefat striped lfp-import-table">
            <thead><tr><th scope="col" class="check-column"><span class="screen-reader-text">Import</span></th><th scope="col">Setting</th><th scope="col">From</th><th scope="col">Now</th><th scope="col">After import</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $id => $row) :
                $field = $definitions[$row['feature']][$row['key']] ?? null;
                if (!$field) { continue; }
                $current = settings($row['feature'])[$row['key']];
                $same = $row['value'] === $current || ($field['type'] === 'list' && !array_diff((array) $row['value'], (array) $current) && !array_diff((array) $current, (array) $row['value']));
                $from = [];
                foreach ($row['from'] as $source => $value) { $from[] = $source . ': ' . import_label($value, $field, $row['feature']); }
                ?>
                <tr<?php echo $same ? ' class="lfp-import-same"' : ''; ?>>
                    <th scope="row" class="check-column"><input type="checkbox" name="import[]" value="<?php echo esc_attr($id); ?>" <?php checked(!$same); ?> <?php disabled($same); ?> aria-label="<?php echo esc_attr('Import ' . $field['label']); ?>"></th>
                    <td><?php echo esc_html(features()[$row['feature']]['label'] . ': ' . $field['label']); ?></td>
                    <td><?php echo esc_html(implode('; ', $from)); ?></td>
                    <td><?php echo esc_html(import_label($current, $field, $row['feature'])); ?></td>
                    <td><strong><?php echo esc_html(import_label($row['value'], $field, $row['feature'])); ?></strong><?php echo $same ? ' <span class="description">(already)</span>' : ''; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php submit_button('Import Ticked Settings', 'secondary'); ?>
    </form>
    <?php
}

function import_settings(): void
{
    authorise('leapfrog_performance_import');
    $chosen = isset($_POST['import']) && is_array($_POST['import']) ? array_map('sanitize_text_field', wp_unslash($_POST['import'])) : [];
    $rows = import_proposals();
    $byFeature = [];
    foreach ($chosen as $id) {
        if (isset($rows[$id])) { $byFeature[$rows[$id]['feature']][$rows[$id]['key']] = $rows[$id]['value']; }
    }
    if (!$byFeature) {
        return_to_page('import_none', ['tab' => 'features']);
    }
    foreach ($byFeature as $feature => $values) {
        update_settings($feature, $values);
    }
    return_to_page('imported', ['tab' => (string) array_key_first($byFeature)]);
}
add_action('admin_post_leapfrog_performance_import', __NAMESPACE__ . '\\import_settings');
