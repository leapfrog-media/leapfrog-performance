<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/**
 * Import settings from ASE (Admin and Site Enhancements) and FlyingPress, for
 * moving an existing site such as Praxis. The Features tab lists what each
 * would change; nothing is saved until the ticked rows are imported. Only
 * features that exist so far (Bloat in 0.1) are covered; later milestones add
 * their own rows.
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
 * Proposed Bloat values: [key => ['ase' => value|null, 'flyingpress' => value|null, 'value' => merged]].
 * A setting switched off in either plugin is switched off here.
 */
function import_proposals(): array
{
    $ase = ase_settings();
    $fp = flying_press_settings();
    $rows = [];
    // Only what the other plugin actually does: an option it has switched off
    // says nothing about what this site wants, so it never undoes a Leapfrog setting.
    $add = static function (string $key, string $source, $value) use (&$rows) {
        if ($value === null || $value === false || $value === '' || $value === 'keep') { return; }
        $rows[$key][$source] = $value;
    };
    if ($ase !== null) {
        $flag = static function (string $name) use ($ase) { return !empty($ase[$name]); };
        $add('emoji', 'ase', $flag('disable_emoji_support'));
        $add('feeds', 'ase', $flag('disable_feeds'));
        $add('jquery_migrate', 'ase', $flag('disable_jquery_migrate'));
        $add('head_clutter', 'ase', $flag('disable_smaller_components'));
        $add('wp_lazy_load', 'ase', $flag('disable_lazy_load'));
        $add('revisions', 'ase', $flag('enable_revisions_control') ? (int) ($ase['revisions_max_number'] ?? 10) : '');
        $add('heartbeat_admin', 'ase', ase_heartbeat($ase, 'admin_pages', ['default', '60', '120', 'off']));
        $add('heartbeat_editor', 'ase', ase_heartbeat($ase, 'post_edit', ['default', '30', '60']));
        $add('heartbeat_site', 'ase', ase_heartbeat($ase, 'frontend', ['default', 'off']));
        $add('admin_email_check', 'ase', $flag('disable_site_admin_email_verification_screen'));
        $add('password_admin_email', 'ase', $flag('disable_admin_email_notification_after_password_change'));
        $add('password_user_email', 'ase', $flag('disable_user_email_notification_after_password_change'));
        if ($flag('disable_comments')) {
            $type = (string) ($ase['disable_comments_type'] ?? 'all');
            $for = array_keys(array_filter((array) ($ase['disable_comments_for'] ?? [])));
            if ($type === 'only-on') {
                $add('comments', 'ase', $for ? 'some' : 'keep');
                $add('comments_types', 'ase', $for);
            } elseif ($type === 'except-on') {
                $all = array_keys(get_post_types(['public' => true]));
                $add('comments', 'ase', 'some');
                $add('comments_types', 'ase', array_values(array_diff($all, $for)));
            } else {
                $add('comments', 'ase', 'off');
            }
        }
    }
    if ($fp !== null) {
        $on = static function (string $name) use ($fp) { return !empty($fp[$name]); };
        $add('block_css', 'flyingpress', $on('bloat_disable_block_css'));
        $add('dashicons', 'flyingpress', $on('bloat_disable_dashicons'));
        $add('emoji', 'flyingpress', $on('bloat_disable_emojis'));
        $add('jquery_migrate', 'flyingpress', $on('bloat_disable_jquery_migrate'));
        $add('feeds', 'flyingpress', $on('bloat_disable_rss_feed'));
        $add('embeds', 'flyingpress', $on('bloat_disable_oembeds'));
        $add('revisions', 'flyingpress', $on('bloat_post_revisions_control') ? 3 : null);
        $add('heartbeat_admin', 'flyingpress', $on('bloat_heartbeat_control') ? '60' : null);
        $add('heartbeat_editor', 'flyingpress', $on('bloat_heartbeat_control') ? '60' : null);
    }
    foreach ($rows as $key => &$row) {
        $values = array_values(array_filter([$row['ase'] ?? null, $row['flyingpress'] ?? null], static function ($v) { return $v !== null; }));
        // Removed in either plugin means removed here; other values: ASE's, else FlyingPress's.
        $row['value'] = $values[0];
    }
    unset($row);
    return $rows;
}

function import_label($value, array $field): string
{
    if ($field['type'] === 'bool') { return $value ? 'Removed' : 'Kept'; }
    if ($field['type'] === 'choice') { return (string) ($field['choices'][$value] ?? $value); }
    if ($field['type'] === 'list') { return $value ? implode(', ', (array) $value) : 'none'; }
    if ($field['type'] === 'number') { return $value === '' ? ($field['placeholder'] ?? 'not set') : $value . ' ' . ($field['unit'] ?? ''); }
    return (string) $value;
}

function render_import_section(): void
{
    $ase = ase_settings() !== null;
    $fp = flying_press_settings() !== null;
    echo '<h2>Import settings</h2>';
    if (!$ase && !$fp) {
        echo '<p class="description">When ASE (Admin and Site Enhancements) or FlyingPress is installed, their matching settings can be imported here.</p>';
        return;
    }
    if (!feature_enabled('bloat')) {
        echo '<p class="description">Switch on Bloat above to import its settings.</p>';
        return;
    }
    $fields = field_definitions()['bloat'] ?? [];
    $current = settings('bloat');
    $rows = import_proposals();
    ?>
    <?php if (!$rows) : ?><p class="description">Nothing to import: <?php echo esc_html(implode(' and ', array_filter([$ase ? 'ASE' : '', $fp ? 'FlyingPress' : '']))); ?> doesn’t remove anything that Leapfrog’s Bloat tab covers.</p><?php return; endif; ?>
    <p class="lfp-narrow-text">Settings found in <?php echo esc_html(implode(' and ', array_filter([$ase ? 'ASE' : '', $fp ? 'FlyingPress' : '']))); ?> that match Leapfrog’s Bloat tab. Ticked rows are imported; nothing changes until you click Import. Afterwards, switch the same settings off in <?php echo $ase && $fp ? 'those plugins' : 'that plugin'; ?> so they aren’t done twice.<?php if ($fp && !empty(flying_press_settings()['bloat_disable_xml_rpc'])) { echo ' FlyingPress switches XML-RPC off: switch it off in FluentAuth instead.'; } ?></p>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="leapfrog_performance_import">
        <?php wp_nonce_field('leapfrog_performance_import', '_lfp_nonce'); ?>
        <table class="widefat striped lfp-import-table">
            <thead><tr><th scope="col" class="check-column"><span class="screen-reader-text">Import</span></th><th scope="col">Setting</th><?php if ($ase) : ?><th scope="col">ASE</th><?php endif; ?><?php if ($fp) : ?><th scope="col">FlyingPress</th><?php endif; ?><th scope="col">Now</th><th scope="col">After import</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $key => $row) :
                if (!isset($fields[$key])) { continue; }
                $same = $row['value'] === $current[$key];
                ?>
                <tr<?php echo $same ? ' class="lfp-import-same"' : ''; ?>>
                    <th scope="row" class="check-column"><input type="checkbox" name="import[]" value="<?php echo esc_attr($key); ?>" <?php checked(!$same); ?> <?php disabled($same); ?> aria-label="<?php echo esc_attr('Import ' . $fields[$key]['label']); ?>"></th>
                    <td><?php echo esc_html($fields[$key]['label']); ?></td>
                    <?php if ($ase) : ?><td><?php echo array_key_exists('ase', $row) ? esc_html(import_label($row['ase'], $fields[$key])) : '—'; ?></td><?php endif; ?>
                    <?php if ($fp) : ?><td><?php echo array_key_exists('flyingpress', $row) ? esc_html(import_label($row['flyingpress'], $fields[$key])) : '—'; ?></td><?php endif; ?>
                    <td><?php echo esc_html(import_label($current[$key], $fields[$key])); ?></td>
                    <td><strong><?php echo esc_html(import_label($row['value'], $fields[$key])); ?></strong><?php echo $same ? ' <span class="description">(already)</span>' : ''; ?></td>
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
    $chosen = isset($_POST['import']) && is_array($_POST['import']) ? array_map('sanitize_key', wp_unslash($_POST['import'])) : [];
    $rows = import_proposals();
    $values = [];
    foreach ($chosen as $key) {
        if (isset($rows[$key])) { $values[$key] = $rows[$key]['value']; }
    }
    if (!$values) {
        return_to_page('import_none', ['tab' => 'features']);
    }
    update_settings('bloat', $values);
    return_to_page('imported', ['tab' => 'bloat']);
}
add_action('admin_post_leapfrog_performance_import', __NAMESPACE__ . '\\import_settings');
