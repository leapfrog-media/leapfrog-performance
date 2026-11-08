<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/**
 * The Rollback feature: put a plugin or theme from WordPress.org back to an
 * earlier version, for when an update breaks something. WordPress's own
 * updater does the work (maintenance mode, file replacement, activation stays
 * as it was), fed the older version's download address. Plugins and themes
 * from elsewhere (Bricks, premium plugins) aren't listed: WordPress.org has no
 * earlier versions of them.
 */

function rollback_boot(): void
{
    add_action('admin_post_leapfrog_performance_rollback', __NAMESPACE__ . '\\do_rollback');
}

/** Earlier versions on WordPress.org: [version => download URL], newest first. */
function wporg_versions(string $kind, string $slug): array
{
    $key = 'lfp_versions_' . $kind . '_' . md5($slug);
    $cached = get_transient($key);
    if (is_array($cached)) { return $cached; }
    if ($kind === 'plugin') {
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        $info = plugins_api('plugin_information', ['slug' => $slug, 'fields' => ['versions' => true, 'sections' => false]]);
    } else {
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        $info = themes_api('theme_information', ['slug' => $slug, 'fields' => ['versions' => true, 'sections' => false]]);
    }
    $versions = (!is_wp_error($info) && !empty($info->versions)) ? (array) $info->versions : [];
    unset($versions['trunk']);
    uksort($versions, static function ($a, $b) { return version_compare((string) $b, (string) $a); });
    $versions = array_slice($versions, 0, 30, true);
    set_transient($key, $versions, 6 * HOUR_IN_SECONDS);
    return $versions;
}

/** The plugins and themes that could be from WordPress.org (folder name = slug). */
function rollback_candidates(): array
{
    if (!function_exists('get_plugins')) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
    $items = [];
    foreach (get_plugins() as $file => $data) {
        $slug = dirname($file);
        if ($slug === '.' || strpos($slug, 'leapfrog-') === 0) { continue; }
        $items['plugin:' . $file] = ['kind' => 'plugin', 'id' => $file, 'slug' => $slug, 'name' => $data['Name'], 'version' => $data['Version']];
    }
    foreach (wp_get_themes() as $stylesheet => $theme) {
        $items['theme:' . $stylesheet] = ['kind' => 'theme', 'id' => $stylesheet, 'slug' => $stylesheet, 'name' => $theme->get('Name') . ' (theme)', 'version' => $theme->get('Version')];
    }
    return $items;
}

function do_rollback(): void
{
    authorise('leapfrog_performance_rollback');
    $choice = isset($_POST['item']) && is_string($_POST['item']) ? wp_unslash($_POST['item']) : '';
    $version = isset($_POST['version']) && is_string($_POST['version']) ? sanitize_text_field(wp_unslash($_POST['version'])) : '';
    $items = rollback_candidates();
    $back = static function (array $args) use ($choice) { wp_safe_redirect(page_url($args + ['tab' => 'rollback', 'item' => $choice])); exit; };
    if (!isset($items[$choice])) { $back(['lfp_rollback' => 'missing']); }
    $item = $items[$choice];
    if (!current_user_can($item['kind'] === 'plugin' ? 'update_plugins' : 'update_themes')) { $back(['lfp_rollback' => 'denied']); }
    $versions = wporg_versions($item['kind'], $item['slug']);
    if (!isset($versions[$version])) { $back(['lfp_rollback' => 'version']); }
    $package = (string) $versions[$version];

    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    // WordPress's updater installs whatever the update list says, so point it at the older version.
    if ($item['kind'] === 'plugin') {
        $updates = get_site_transient('update_plugins') ?: new \stdClass();
        $updates->response = (array) ($updates->response ?? []);
        $updates->response[$item['id']] = (object) ['slug' => $item['slug'], 'plugin' => $item['id'], 'new_version' => $version, 'package' => $package, 'url' => ''];
        set_site_transient('update_plugins', $updates);
        $wasActive = is_plugin_active($item['id']);
        $result = (new \Plugin_Upgrader(new \Automatic_Upgrader_Skin()))->upgrade($item['id']);
        wp_clean_plugins_cache(true);
        // The updater switches the plugin off while it works; WordPress's own update screen
        // switches it back on afterwards, so do the same.
        if ($result === true && $wasActive && !is_plugin_active($item['id'])) {
            $activated = activate_plugin($item['id']);
            if (is_wp_error($activated)) { $back(['lfp_rollback' => 'inactive', 'to' => $version]); }
        }
    } else {
        $updates = get_site_transient('update_themes') ?: new \stdClass();
        $updates->response = (array) ($updates->response ?? []);
        $updates->response[$item['id']] = ['theme' => $item['id'], 'new_version' => $version, 'package' => $package, 'url' => ''];
        set_site_transient('update_themes', $updates);
        $result = (new \Theme_Upgrader(new \Automatic_Upgrader_Skin()))->upgrade($item['id']);
        wp_clean_themes_cache(true);
    }
    if ($result === true) {
        $back(['lfp_rollback' => 'done', 'to' => $version]);
    }
    $back(['lfp_rollback' => 'failed']);
}

function render_rollback_tab(): void
{
    $items = rollback_candidates();
    $chosen = isset($_GET['item']) && is_string($_GET['item']) ? wp_unslash($_GET['item']) : '';
    $result = isset($_GET['lfp_rollback']) ? sanitize_key($_GET['lfp_rollback']) : '';
    $messages = [
        'done' => ['success', 'Rolled back to version ' . (isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : '') . '. Check the site; WordPress will offer the newer version again under Updates.'],
        'failed' => ['error', 'The rollback didn’t finish (the download or the file copy failed). The plugin or theme is as it was, or WordPress’s updater shows what happened under Dashboard > Updates.'],
        'inactive' => ['warning', 'Rolled back to version ' . (isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : '') . ', but it couldn’t be switched back on. Activate it under Plugins.'],
        'version' => ['error', 'That version isn’t available.'],
        'missing' => ['error', 'Choose a plugin or theme.'],
        'denied' => ['error', 'You aren’t allowed to update that.'],
    ];
    if (isset($messages[$result])) { echo '<div class="notice notice-' . esc_attr($messages[$result][0]) . ' is-dismissible lfa-notice-keep"><p>' . esc_html($messages[$result][1]) . '</p></div>'; }
    ?>
    <p class="lfp-narrow-text">Put a plugin or theme from WordPress.org back to an earlier version when an update breaks something. Take a backup first (UpdraftPlus). Plugins and themes from elsewhere, such as Bricks or premium plugins, can’t be rolled back here: use their own download pages.</p>
    <form method="get" class="lfp-rollback-pick">
        <input type="hidden" name="page" value="<?php echo esc_attr(PAGE); ?>">
        <input type="hidden" name="tab" value="rollback">
        <label for="lfp-rollback-item"><strong>Plugin or theme</strong></label><br>
        <select id="lfp-rollback-item" name="item" onchange="this.form.submit()">
            <option value="">Choose…</option>
            <?php foreach ($items as $key => $item) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($chosen, $key); ?>><?php echo esc_html($item['name'] . ' ' . $item['version']); ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button class="button">Show versions</button></noscript>
    </form>
    <?php
    if (!isset($items[$chosen])) { return; }
    $item = $items[$chosen];
    $versions = array_filter(wporg_versions($item['kind'], $item['slug']), static function ($url, $version) use ($item) {
        return version_compare((string) $version, (string) $item['version'], '<');
    }, ARRAY_FILTER_USE_BOTH);
    if (!$versions) {
        echo '<p class="description">No earlier versions of ' . esc_html($item['name']) . ' on WordPress.org.</p>';
        return;
    }
    ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="lfp-rollback-run">
        <input type="hidden" name="action" value="leapfrog_performance_rollback">
        <input type="hidden" name="item" value="<?php echo esc_attr($chosen); ?>">
        <?php wp_nonce_field('leapfrog_performance_rollback', '_lfp_nonce'); ?>
        <p><label for="lfp-rollback-version"><strong>Version to go back to</strong></label><br>
            <select id="lfp-rollback-version" name="version">
                <?php foreach (array_keys($versions) as $version) : ?><option value="<?php echo esc_attr((string) $version); ?>"><?php echo esc_html((string) $version); ?></option><?php endforeach; ?>
            </select>
            <span class="description">Installed now: <?php echo esc_html($item['version']); ?></span></p>
        <?php submit_button('Roll Back', 'primary', 'submit', false, ['data-lfa-confirm' => 'Replace ' . $item['name'] . ' ' . $item['version'] . ' with the chosen earlier version?']); ?>
    </form>
    <?php
}
