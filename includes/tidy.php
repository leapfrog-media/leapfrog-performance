<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/**
 * The Tidy up feature: one-off clean-ups, mainly for moving an existing site
 * onto Leapfrog.
 *
 * - Image copies no longer made: switching a size off only affects new
 *   uploads, so older images keep their copies in that size. Copies that any
 *   content still points to (a Bricks image, an <img> in a post) are kept.
 * - Leftovers of removed plugins: settings and database tables that plugins
 *   leave behind when they are deleted, some loaded on every page. Only
 *   plugins in the list below are recognised, and only while none of their
 *   folders is installed.
 *
 * Nothing runs on the site; the tab only adds two admin actions.
 */

/**
 * Plugins (and themes) whose leftovers are recognised: folders = plugin
 * folders (any one installed means it isn't a leftover), themes = theme
 * folders, options = exact names, prefixes = option name starts, tables =
 * table name starts after the site's table prefix.
 */
function leftover_catalogue(): array
{
    return [
        'ase' => ['name' => 'Admin and Site Enhancements (ASE)', 'folders' => ['admin-site-enhancements', 'admin-site-enhancements-pro'], 'prefixes' => ['admin_site_enhancements', 'asenha_', 'default_term_asenha_'], 'tables' => ['asenha_']],
        'disable-media-sizes' => ['name' => 'Disable Media Sizes', 'folders' => ['disable-media-sizes'], 'prefixes' => ['disable_media_sizes']],
        'disable-media-pages' => ['name' => 'Disable Media Pages', 'folders' => ['disable-media-pages'], 'prefixes' => ['disable_media_pages']],
        'core-framework' => ['name' => 'Core Framework', 'folders' => ['core-framework'], 'prefixes' => ['core_framework_'], 'tables' => ['core_framework_']],
        'corebridge' => ['name' => 'CoreBridge for Bricks', 'folders' => ['corebridge-for-bricks'], 'prefixes' => ['corebridge_']],
        'native-admin-colours' => ['name' => 'Native Admin Colours', 'folders' => ['native-admin-colours'], 'prefixes' => ['native_admin_']],
        'adminify' => ['name' => 'WP Adminify (and its add-ons)', 'folders' => ['adminify', 'adminify-pro', 'adminify-google-pagespeed', 'adminify-quick-circle-menu'], 'prefixes' => ['pxlbsadminify_', 'jltwp_adminify_', 'wp_adminify_', 'adminify_'], 'tables' => ['adminify_']],
        'admin-bar' => ['name' => 'Admin Bar Editor and Admin Bar Pro', 'folders' => ['admin-bar', 'admin-bar-pro'], 'options' => ['admin_bar_version', 'wc_am_product_id_admin_bar_pro'], 'prefixes' => ['_jltadminbar_', 'jlt_admin_bar_', 'admin_bar_pro']],
        'rolemaster' => ['name' => 'RoleMaster Suite', 'folders' => ['rolemaster-suite'], 'prefixes' => ['rolemaster_suite_', '_rolemaster_suite_']],
        'cms-tree-page-view' => ['name' => 'CMS Tree Page View', 'folders' => ['cms-tree-page-view'], 'prefixes' => ['cms_tpv_']],
        'yellow-pencil' => ['name' => 'YellowPencil', 'folders' => ['waspthemes-yellow-pencil', 'yellow-pencil-visual-theme-customizer'], 'prefixes' => ['yp_']],
        'wp-migrate' => ['name' => 'WP Migrate', 'folders' => ['wp-migrate-db-pro', 'wp-migrate-db'], 'options' => ['wp_migrate_addon_schema'], 'prefixes' => ['wpmdb_']],
        'adbc' => ['name' => 'Advanced Database Cleaner', 'folders' => ['advanced-database-cleaner', 'advanced-database-cleaner-premium', 'advanced-database-cleaner-pro'], 'prefixes' => ['adbc_']],
        'divi' => ['name' => 'Divi Builder', 'folders' => ['divi-builder'], 'themes' => ['Divi', 'Extra'], 'options' => ['et_divi_builder_plugin'], 'prefixes' => ['et_pb_', 'et_core_', 'et_bfb_']],
        'astra' => ['name' => 'Astra', 'folders' => ['astra-sites', 'astra-addon'], 'themes' => ['astra'], 'options' => ['bsf_usage_migrated'], 'prefixes' => ['astra_']],
        'fancy-animations' => ['name' => 'Fancy Animations', 'folders' => ['fancy-animations'], 'options' => ['external_updates-fancy-animations'], 'prefixes' => ['fancy_animations']],
        'code2bricks' => ['name' => 'Code2Bricks', 'folders' => ['code2bricks'], 'prefixes' => ['code2bricks_']],
        'jooosi-fon' => ['name' => 'Jooosi Fon', 'folders' => ['jooosi-fon'], 'prefixes' => ['jooosi_fon_'], 'tables' => ['jooosi_fon_']],
        'pixflow' => ['name' => 'Pixflow', 'folders' => ['pixflow-ai', 'pixflow-design-pro'], 'prefixes' => ['pixflow_', '_pixflow_'], 'tables' => ['pixflow_']],
        'advanced-themer' => ['name' => 'Advanced Themer for Bricks', 'folders' => ['bricks-advanced-themer'], 'prefixes' => ['bricks-advanced-themer__', '_bricks-advanced-themer__', 'bricks_advanced_themer_', 'brxc_']],
        'bricksextras' => ['name' => 'BricksExtras', 'folders' => ['bricksextras'], 'prefixes' => ['bricksextras_']],
        'akismet' => ['name' => 'Akismet', 'folders' => ['akismet'], 'options' => ['wordpress_api_key'], 'prefixes' => ['akismet_']],
        'leapfrog-admin' => ['name' => 'Leapfrog Admin', 'folders' => ['leapfrog-admin'], 'prefixes' => ['leapfrog_admin_']],
        'leapfrog-framework' => ['name' => 'Leapfrog Framework', 'folders' => ['leapfrog-framework'], 'prefixes' => ['lf_framework', 'lf_theme_', 'lf_logical_sizing', 'lf_element_settings']],
    ];
}

/** Never deleted, whatever matches. */
function protected_option(string $name): bool
{
    global $wpdb;
    return in_array($name, [$wpdb->prefix . 'user_roles', 'active_plugins', 'cron', 'siteurl', 'home', 'template', 'stylesheet', 'rewrite_rules'], true)
        || strpos($name, 'leapfrog_performance') === 0;
}

function tidy_boot(): void
{
    add_action('admin_post_leapfrog_performance_tidy_sizes', __NAMESPACE__ . '\\delete_size_copies');
    add_action('admin_post_leapfrog_performance_tidy_leftovers', __NAMESPACE__ . '\\delete_leftovers');
}

/* ---------- Image copies no longer made ---------- */

/** The sizes WordPress makes now. */
function sizes_made(): array
{
    $made = array_keys(image_subsizes());
    if (feature_enabled('media') && function_exists(__NAMESPACE__ . '\\media_fields')) {
        $made = array_diff($made, array_diff((array) settings('media')['sizes_off'], ['thumbnail']));
    }
    return array_values(array_map('strval', $made));
}

function image_subsizes(): array
{
    return function_exists('wp_get_registered_image_subsizes') ? wp_get_registered_image_subsizes() : [];
}

/** File names of size copies that content points to (posts, Bricks data, settings). */
function referenced_copies(): array
{
    global $wpdb;
    $pattern = '-[0-9]+x[0-9]+\\.';
    $chunks = array_merge(
        $wpdb->get_col($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE post_type <> 'attachment' AND post_content REGEXP %s", $pattern)),
        $wpdb->get_col($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key NOT IN ('_wp_attachment_metadata', '_wp_attachment_backup_sizes') AND meta_value REGEXP %s", $pattern)),
        $wpdb->get_col($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name NOT LIKE %s AND option_value REGEXP %s", $wpdb->esc_like('_transient') . '%', $pattern))
    );
    $names = [];
    foreach ($chunks as $text) {
        if (preg_match_all('/[^\/"\'\s\\\\<>]+-\d+x\d+\.(?:jpe?g|png|gif|webp|avif|heic)/i', (string) $text, $m)) {
            foreach ($m[0] as $file) { $names[strtolower($file)] = true; }
        }
    }
    return $names;
}

/**
 * Every copy in a size that isn't made any more: [attachment id => [size => [paths]]],
 * plus totals by size. Copies that content points to, or that a size still made
 * shares, are counted as kept.
 */
function scan_size_copies(): array
{
    global $wpdb;
    $made = sizes_made();
    $registered = array_keys(image_subsizes());
    // Sizes WordPress knows by name, including ones set to 0 × 0 under Settings > Media.
    $named = array_map('strval', get_intermediate_image_sizes());
    $used = referenced_copies();
    $base = trailingslashit(wp_get_upload_dir()['basedir']);
    $rows = $wpdb->get_results("SELECT p.ID, m.meta_value FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_metadata' WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'");
    $items = [];
    $totals = [];
    foreach ($rows as $row) {
        $meta = maybe_unserialize($row->meta_value);
        if (!is_array($meta) || empty($meta['sizes']) || empty($meta['file'])) { continue; }
        $dir = dirname((string) $meta['file']);
        $dir = $dir === '.' ? '' : trailingslashit($dir);
        $keep = [basename((string) $meta['file'])];
        if (!empty($meta['original_image'])) { $keep[] = (string) $meta['original_image']; }
        foreach ($meta['sizes'] as $name => $size) {
            if (in_array((string) $name, $made, true) && !empty($size['file'])) { $keep[] = (string) $size['file']; }
        }
        foreach ($meta['sizes'] as $name => $size) {
            $name = (string) $name;
            if (in_array($name, $made, true) || empty($size['file'])) { continue; }
            $file = (string) $size['file'];
            $totals[$name] = $totals[$name] ?? ['copies' => 0, 'bytes' => 0, 'kept' => 0, 'why' => in_array($name, $registered, true) ? 'off' : (in_array($name, $named, true) ? 'zero' : 'gone')];
            if (in_array($file, $keep, true) || isset($used[strtolower($file)])) { $totals[$name]['kept']++; continue; }
            $paths = copy_files($base . $dir, $file, (array) ($size['sources'] ?? []));
            $totals[$name]['copies']++;
            foreach ($paths as $path) { $totals[$name]['bytes'] += (int) @filesize($path); }
            $items[(int) $row->ID][$name] = $paths;
        }
    }
    ksort($totals);
    return ['items' => $items, 'totals' => $totals];
}

/** The copy and its WebP/AVIF versions (WordPress's own, and ShortPixel's next to it), where they exist. */
function copy_files(string $dir, string $file, array $sources): array
{
    $names = [$file];
    foreach ($sources as $source) {
        if (is_array($source) && !empty($source['file'])) { $names[] = (string) $source['file']; }
    }
    $stem = preg_replace('/\.[^.]+$/', '', $file);
    foreach (['webp', 'avif'] as $ext) { $names[] = $stem . '.' . $ext; $names[] = $file . '.' . $ext; }
    $paths = [];
    foreach (array_unique($names) as $name) {
        if (basename($name) === $name && is_file($dir . $name)) { $paths[] = $dir . $name; }
    }
    return $paths;
}

function delete_size_copies(): void
{
    authorise('leapfrog_performance_tidy_sizes');
    @set_time_limit(120);
    $chosen = isset($_POST['sizes']) && is_array($_POST['sizes']) ? array_map('strval', wp_unslash($_POST['sizes'])) : [];
    $scan = scan_size_copies();
    $chosen = array_intersect($chosen, array_keys($scan['totals']));
    $back = static function (array $args) { wp_safe_redirect(page_url($args + ['tab' => 'tidy'])); exit; };
    if (!$chosen) { $back(['lfp_tidy' => 'none']); }
    $base = realpath(wp_get_upload_dir()['basedir']);
    $start = time();
    $copies = 0;
    $bytes = 0;
    $more = false;
    foreach ($scan['items'] as $id => $sizes) {
        if (time() - $start > 25) { $more = true; break; }
        $sizes = array_intersect_key($sizes, array_flip($chosen));
        if (!$sizes) { continue; }
        foreach ($sizes as $paths) {
            foreach ($paths as $path) {
                $real = realpath($path);
                if ($real === false || $base === false || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) { continue; }
                $bytes += (int) filesize($real);
                wp_delete_file($real);
            }
            $copies++;
        }
        $meta = wp_get_attachment_metadata($id, true);
        if (is_array($meta) && isset($meta['sizes'])) {
            $meta['sizes'] = array_diff_key((array) $meta['sizes'], $sizes);
            wp_update_attachment_metadata($id, $meta);
        }
    }
    $back(['lfp_tidy' => 'sizes', 'copies' => $copies, 'kb' => (int) round($bytes / 1024), 'more' => $more ? 1 : 0]);
}

/* ---------- Leftovers of removed plugins ---------- */

/** Recognised leftovers whose plugin isn't installed: [key => [name, options => [name => [bytes, every page]], tables => [name => [rows, bytes]]]]. */
function find_leftovers(): array
{
    global $wpdb;
    $everyPage = function_exists('wp_autoload_values_to_autoload') ? wp_autoload_values_to_autoload() : ['yes'];
    $options = $wpdb->get_results("SELECT option_name, autoload, LENGTH(option_value) AS bytes FROM {$wpdb->options}");
    $tables = $wpdb->get_results($wpdb->prepare("SELECT table_name AS name, table_rows AS rows_count, data_length + index_length AS bytes FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE %s", $wpdb->esc_like($wpdb->prefix) . '%'));
    $catalogue = leftover_catalogue();
    // Settings of themes that aren't installed any more.
    $catalogue['old-themes'] = ['name' => 'Themes no longer installed', 'folders' => [], 'options' => []];
    foreach ($options as $option) {
        if (strpos($option->option_name, 'theme_mods_') === 0) {
            $slug = substr($option->option_name, 11);
            if ($slug !== '' && !wp_get_theme($slug)->exists()) { $catalogue['old-themes']['options'][] = $option->option_name; }
        }
    }
    $found = [];
    foreach ($catalogue as $key => $entry) {
        if (still_installed($entry)) { continue; }
        $group = ['name' => $entry['name'], 'options' => [], 'tables' => []];
        foreach ($options as $option) {
            $name = (string) $option->option_name;
            if (!protected_option($name) && option_belongs($name, $entry)) {
                $group['options'][$name] = ['bytes' => (int) $option->bytes, 'every_page' => in_array($option->autoload, $everyPage, true)];
            }
        }
        foreach ($tables as $table) {
            foreach ($entry['tables'] ?? [] as $start) {
                if (strpos((string) $table->name, $wpdb->prefix . $start) === 0) {
                    $group['tables'][(string) $table->name] = ['rows' => (int) $table->rows_count, 'bytes' => (int) $table->bytes];
                }
            }
        }
        if ($group['options'] || $group['tables']) { $found[$key] = $group; }
    }
    return $found;
}

function still_installed(array $entry): bool
{
    foreach ($entry['folders'] ?? [] as $folder) {
        if (is_dir(WP_PLUGIN_DIR . '/' . $folder)) { return true; }
    }
    foreach ($entry['themes'] ?? [] as $theme) {
        if (wp_get_theme($theme)->exists()) { return true; }
    }
    return false;
}

/** The option is one of the entry's, or a transient of it. */
function option_belongs(string $name, array $entry): bool
{
    foreach (['', '_transient_', '_transient_timeout_', '_site_transient_', '_site_transient_timeout_'] as $wrap) {
        if ($wrap !== '' && strpos($name, $wrap) !== 0) { continue; }
        $bare = substr($name, strlen($wrap));
        if (in_array($bare, $entry['options'] ?? [], true)) { return true; }
        foreach ($entry['prefixes'] ?? [] as $prefix) {
            if (strpos($bare, $prefix) === 0) { return true; }
        }
    }
    return false;
}

function delete_leftovers(): void
{
    global $wpdb;
    authorise('leapfrog_performance_tidy_leftovers');
    $chosen = isset($_POST['groups']) && is_array($_POST['groups']) ? array_map('sanitize_key', wp_unslash($_POST['groups'])) : [];
    $back = static function (array $args) { wp_safe_redirect(page_url($args + ['tab' => 'tidy'])); exit; };
    $found = array_intersect_key(find_leftovers(), array_flip($chosen));
    if (!$found) { $back(['lfp_tidy' => 'none']); }
    $settings = 0;
    $tables = 0;
    foreach ($found as $group) {
        foreach (array_keys($group['options']) as $name) {
            if (delete_option($name)) { $settings++; }
        }
        foreach (array_keys($group['tables']) as $table) {
            if (preg_match('/^[A-Za-z0-9_]+$/', $table) && strpos($table, $wpdb->prefix) === 0 && $wpdb->query("DROP TABLE IF EXISTS `{$table}`") !== false) { $tables++; }
        }
    }
    $back(['lfp_tidy' => 'leftovers', 'settings' => $settings, 'tables' => $tables]);
}

/** Size of everything WordPress loads on every page from its settings. */
function every_page_bytes(): int
{
    global $wpdb;
    $values = function_exists('wp_autoload_values_to_autoload') ? wp_autoload_values_to_autoload() : ['yes'];
    $in = implode(',', array_fill(0, count($values), '%s'));
    return (int) $wpdb->get_var($wpdb->prepare("SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ($in)", $values));
}

function kb(int $bytes): string
{
    return $bytes >= 1048576 ? number_format_i18n($bytes / 1048576, 1) . ' MB' : number_format_i18n(max(1, (int) round($bytes / 1024))) . ' KB';
}

/* ---------- The tab ---------- */

function render_tidy_tab(): void
{
    $result = isset($_GET['lfp_tidy']) ? sanitize_key($_GET['lfp_tidy']) : '';
    $n = static function (string $key): int { return isset($_GET[$key]) ? absint($_GET[$key]) : 0; };
    if ($result === 'sizes') {
        $text = sprintf('%s deleted (%s).', $n('copies') === 1 ? '1 image copy' : $n('copies') . ' image copies', kb($n('kb') * 1024));
        if ($n('more')) { $text .= ' There are more: delete again to carry on.'; }
        echo '<div class="notice notice-success is-dismissible lfa-notice-keep"><p>' . esc_html($text) . '</p></div>';
    } elseif ($result === 'leftovers') {
        echo '<div class="notice notice-success is-dismissible lfa-notice-keep"><p>' . esc_html(sprintf('Deleted %s and %s.', $n('settings') === 1 ? '1 setting' : $n('settings') . ' settings', $n('tables') === 1 ? '1 table' : $n('tables') . ' tables')) . '</p></div>';
    } elseif ($result === 'none') {
        echo '<div class="notice notice-error is-dismissible lfa-notice-keep"><p>Nothing was ticked, or it has already gone.</p></div>';
    }
    echo '<p class="lfp-narrow-text">One-off clean-ups, mostly for sites moving onto Leapfrog. Take a backup first (UpdraftPlus): deleted files and settings can only come back from a backup.</p>';
    render_size_copies_section();
    render_leftovers_section();
}

function render_size_copies_section(): void
{
    $scan = scan_size_copies();
    $totals = array_filter($scan['totals'], static function ($t) { return $t['copies'] > 0; });
    $kept = array_sum(array_column($scan['totals'], 'kept'));
    ?>
    <h2>Image copies no longer made</h2>
    <p class="lfp-narrow-text">Switching an image size off only affects new uploads, so older images still have copies in that size. Copies that a page, post or Bricks template still points to are kept.</p>
    <?php if (!$totals) : ?>
        <p class="description">No spare copies<?php echo $kept ? esc_html(sprintf(' (%d still in use, kept)', $kept)) : ''; ?>.</p>
        <?php return; endif; ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="leapfrog_performance_tidy_sizes">
        <?php wp_nonce_field('leapfrog_performance_tidy_sizes', '_lfp_nonce'); ?>
        <table class="widefat striped lfp-tidy-table">
            <thead><tr><th scope="col" class="check-column"><span class="screen-reader-text">Delete</span></th><th scope="col">Size</th><th scope="col">Spare copies</th><th scope="col">Space</th><th scope="col">Kept (in use)</th></tr></thead>
            <tbody>
            <?php foreach ($totals as $name => $t) : $id = 'lfp-tidy-size-' . sanitize_html_class($name); ?>
                <tr>
                    <th scope="row" class="check-column"><input type="checkbox" id="<?php echo esc_attr($id); ?>" name="sizes[]" value="<?php echo esc_attr($name); ?>" checked></th>
                    <td><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html(size_name($name)); ?> <code><?php echo esc_html($name); ?></code></label><br><span class="description"><?php echo esc_html(['off' => 'Switched off on the Media tab', 'zero' => 'Set to 0 × 0 under Settings > Media', 'gone' => 'No longer added by WordPress, the theme or a plugin'][$t['why']]); ?></span></td>
                    <td><?php echo esc_html(number_format_i18n($t['copies'])); ?></td>
                    <td><?php echo esc_html(kb($t['bytes'])); ?></td>
                    <td><?php echo esc_html(number_format_i18n($t['kept'])); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">Their WebP and AVIF versions go too. The original uploads and every size still made aren’t touched.</p>
        <?php submit_button('Delete Ticked Copies', 'secondary', 'submit', true, ['data-lfa-confirm' => 'Delete the image copies in the ticked sizes? They can only come back from a backup.']); ?>
    </form>
    <?php
}

function size_name(string $name): string
{
    return function_exists(__NAMESPACE__ . '\\size_label') ? size_label($name) : ucwords(str_replace(['_', '-'], ' ', $name));
}

function render_leftovers_section(): void
{
    $found = find_leftovers();
    ?>
    <h2>Left behind by removed plugins</h2>
    <p class="lfp-narrow-text">Many plugins leave their settings, and sometimes database tables, behind when they are deleted. Some of those settings load on every page. Plugins are only listed here once they are deleted (not just switched off). WordPress loads <strong><?php echo esc_html(kb(every_page_bytes())); ?></strong> of settings on every page; it warns above 800 KB.</p>
    <?php if (!$found) : ?>
        <p class="description">Nothing left behind by the plugins Leapfrog recognises.</p>
        <?php return; endif; ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="leapfrog_performance_tidy_leftovers">
        <?php wp_nonce_field('leapfrog_performance_tidy_leftovers', '_lfp_nonce'); ?>
        <table class="widefat striped lfp-tidy-table">
            <thead><tr><th scope="col" class="check-column"><span class="screen-reader-text">Delete</span></th><th scope="col">Plugin</th><th scope="col">Settings</th><th scope="col">Loaded on every page</th><th scope="col">Tables</th></tr></thead>
            <tbody>
            <?php foreach ($found as $key => $group) :
                $id = 'lfp-tidy-group-' . $key;
                $bytes = array_sum(array_column($group['options'], 'bytes'));
                $every = array_sum(array_map(static function ($o) { return $o['every_page'] ? $o['bytes'] : 0; }, $group['options']));
                ?>
                <tr>
                    <th scope="row" class="check-column"><input type="checkbox" id="<?php echo esc_attr($id); ?>" name="groups[]" value="<?php echo esc_attr($key); ?>"></th>
                    <td>
                        <label for="<?php echo esc_attr($id); ?>"><strong><?php echo esc_html($group['name']); ?></strong></label>
                        <details class="lfp-tidy-details"><summary>What would be deleted</summary>
                            <code><?php echo esc_html(implode(', ', array_merge(array_keys($group['options']), array_keys($group['tables'])))); ?></code>
                        </details>
                    </td>
                    <td><?php echo $group['options'] ? esc_html(number_format_i18n(count($group['options'])) . ' (' . kb($bytes) . ')') : '—'; ?></td>
                    <td><?php echo $every ? esc_html(kb($every)) : '—'; ?></td>
                    <td><?php echo $group['tables'] ? esc_html(count($group['tables']) . ' (' . number_format_i18n(array_sum(array_column($group['tables'], 'rows'))) . ' rows)') : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php submit_button('Delete Ticked Leftovers', 'secondary', 'submit', true, ['data-lfa-confirm' => 'Delete the settings and tables of the ticked plugins? Reinstalling a plugin afterwards starts it from scratch.']); ?>
    </form>
    <?php
}
