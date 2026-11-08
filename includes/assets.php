<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/**
 * The "Load only where used" feature: for each plugin, choose where its CSS
 * and JavaScript load on the site: everywhere (as now), only at chosen
 * addresses, or nowhere on the site. The admin and the Bricks builder always
 * get everything. Whole plugins are never switched off, only their files.
 *
 * Leapfrog notes which plugin files each page loads while administrators browse
 * the site (option leapfrog_performance_assets_seen), because many only load on
 * certain pages. Rules are kept in leapfrog_performance_assets.
 */

const OPTION_ASSET_RULES = 'leapfrog_performance_assets';
const OPTION_ASSETS_SEEN = 'leapfrog_performance_assets_seen';

function assets_boot(): void
{
    add_action('wp_footer', __NAMESPACE__ . '\\record_assets', PHP_INT_MAX);
    add_action('wp_print_styles', __NAMESPACE__ . '\\apply_asset_rules', 1);
    add_action('wp_print_scripts', __NAMESPACE__ . '\\apply_asset_rules', 1);
    add_action('wp_print_footer_scripts', __NAMESPACE__ . '\\apply_asset_rules', 1);
    add_action('admin_post_leapfrog_performance_assets', __NAMESPACE__ . '\\save_asset_rules');
}

function asset_rules(): array
{
    $rules = get_option(OPTION_ASSET_RULES, []);
    return is_array($rules) ? $rules : [];
}

function assets_seen(): array
{
    $seen = get_option(OPTION_ASSETS_SEEN, []);
    return is_array($seen) ? $seen : [];
}

/** The Bricks builder and its canvas always load everything. */
function in_builder(): bool
{
    return (function_exists('bricks_is_builder') && bricks_is_builder())
        || (function_exists('bricks_is_builder_iframe') && bricks_is_builder_iframe())
        || is_customize_preview();
}

/** Administrators can see a page as if no rules existed, to compare. */
function rules_paused(): bool
{
    return isset($_GET['lfp-all-assets']) && current_user_can('manage_options');
}

/** The plugin folder a file address belongs to, or ''. */
function plugin_folder(string $src): string
{
    $base = (string) wp_parse_url(plugins_url(), PHP_URL_PATH);
    $path = (string) wp_parse_url($src, PHP_URL_PATH);
    if ($base === '' || strpos($path, $base . '/') !== 0) { return ''; }
    $folder = explode('/', substr($path, strlen($base) + 1))[0];
    return sanitize_key($folder);
}

/** This page's path, without the site's own folder: "/contact/" style. */
function current_path(): string
{
    $home = rtrim((string) wp_parse_url(home_url(), PHP_URL_PATH), '/');
    $path = (string) wp_parse_url(isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/', PHP_URL_PATH);
    if ($home !== '' && strpos($path, $home) === 0) { $path = substr($path, strlen($home)); }
    return '/' . trim($path, '/');
}

/** One address per line; * matches anything; "/" is the home page. */
function path_matches(string $patterns, string $path): bool
{
    foreach (preg_split('/\R/', $patterns) as $line) {
        $line = trim($line);
        if ($line === '') { continue; }
        $pattern = '/' . trim((string) (wp_parse_url($line, PHP_URL_PATH) ?: $line), '/');
        if (preg_match('#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i', $path)) { return true; }
    }
    return false;
}

/** Remember which plugin files load on the site. Writes only when something new appears. */
function record_assets(): void
{
    if (!current_user_can('manage_options') || in_builder() || is_admin()) { return; }
    $seen = assets_seen();
    $changed = false;
    foreach (['script' => wp_scripts(), 'style' => wp_styles()] as $kind => $deps) {
        foreach (array_unique(array_merge((array) $deps->done, (array) $deps->queue)) as $handle) {
            $src = isset($deps->registered[$handle]) ? (string) $deps->registered[$handle]->src : '';
            $folder = $src !== '' ? plugin_folder($src) : '';
            if ($folder === '' || strpos($folder, 'leapfrog-') === 0) { continue; }
            if (!in_array($handle, $seen[$folder][$kind] ?? [], true)) {
                $seen[$folder][$kind][] = $handle;
                $changed = true;
            }
        }
    }
    if ($changed && count($seen) <= 200) {
        update_option(OPTION_ASSETS_SEEN, $seen, false);
    }
}

/** Leave out the files of plugins whose rule says not here. */
function apply_asset_rules(): void
{
    if (is_admin() || in_builder() || rules_paused()) { return; }
    $rules = asset_rules();
    if (!$rules) { return; }
    $seen = assets_seen();
    $path = current_path();
    foreach ($rules as $folder => $rule) {
        $mode = $rule['mode'] ?? 'everywhere';
        if ($mode === 'everywhere' || ($mode === 'only' && path_matches((string) ($rule['pages'] ?? ''), $path))) { continue; }
        foreach ((array) ($seen[$folder]['script'] ?? []) as $handle) { wp_dequeue_script($handle); }
        foreach ((array) ($seen[$folder]['style'] ?? []) as $handle) { wp_dequeue_style($handle); }
        // Files registered by this plugin but not noticed yet are caught by their address.
        foreach (['script' => wp_scripts(), 'style' => wp_styles()] as $kind => $deps) {
            foreach ((array) $deps->queue as $handle) {
                $src = isset($deps->registered[$handle]) ? (string) $deps->registered[$handle]->src : '';
                if ($src !== '' && plugin_folder($src) === $folder) {
                    $kind === 'script' ? wp_dequeue_script($handle) : wp_dequeue_style($handle);
                }
            }
        }
    }
}

function save_asset_rules(): void
{
    authorise('leapfrog_performance_assets');
    $posted = isset($_POST['rules']) && is_array($_POST['rules']) ? wp_unslash($_POST['rules']) : [];
    $rules = [];
    foreach ($posted as $folder => $rule) {
        $folder = sanitize_key((string) $folder);
        $mode = is_array($rule) && in_array($rule['mode'] ?? '', ['only', 'nowhere'], true) ? $rule['mode'] : 'everywhere';
        if ($folder === '' || $mode === 'everywhere') { continue; }
        $rules[$folder] = ['mode' => $mode, 'pages' => sanitize_textarea_field((string) ($rule['pages'] ?? ''))];
    }
    update_option(OPTION_ASSET_RULES, $rules);
    if (!empty($_POST['forget'])) {
        delete_option(OPTION_ASSETS_SEEN);
    }
    return_to_page('saved', ['tab' => 'assets']);
}

function plugin_names(): array
{
    if (!function_exists('get_plugins')) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
    $names = [];
    foreach (get_plugins() as $file => $data) {
        $names[sanitize_key(dirname($file))] = $data['Name'];
    }
    return $names;
}

function render_assets_tab(): void
{
    $seen = assets_seen();
    $rules = asset_rules();
    $names = plugin_names();
    ?>
    <p class="lfp-narrow-text">Many plugins load their CSS and JavaScript on every page, even where they aren’t used: a booking widget’s files on the blog, a slider’s files on the contact page. Choose where each plugin’s files may load on the site. The plugin itself keeps running everywhere, and the admin and the Bricks builder always get everything.</p>
    <p class="description lfp-narrow-text">Plugins appear here once Leapfrog has seen their files, so visit the site’s pages while logged in as an administrator, especially the ones that use each plugin. To compare, add <code>?lfp-all-assets=1</code> to a page address while logged in: that page then loads everything.</p>
    <?php if (!$seen) : ?>
        <div class="notice notice-info inline"><p>No plugin files seen yet. <a href="<?php echo esc_url(home_url('/')); ?>">Visit the site</a>, then come back.</p></div>
        <?php return; endif; ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="lfa-track">
        <input type="hidden" name="action" value="leapfrog_performance_assets">
        <?php wp_nonce_field('leapfrog_performance_assets', '_lfp_nonce'); ?>
        <table class="widefat striped lfp-assets-table">
            <thead><tr><th scope="col">Plugin</th><th scope="col">Files seen</th><th scope="col">Loads</th></tr></thead>
            <tbody>
            <?php ksort($seen); foreach ($seen as $folder => $files) :
                $folder = (string) $folder;
                $rule = $rules[$folder] ?? ['mode' => 'everywhere', 'pages' => ''];
                $id = 'lfp-assets-' . $folder;
                ?>
                <tr>
                    <td><label for="<?php echo esc_attr($id); ?>"><strong><?php echo esc_html($names[$folder] ?? $folder); ?></strong></label><br><code><?php echo esc_html($folder); ?></code></td>
                    <td><details><summary><?php echo esc_html(count($files['style'] ?? []) . ' CSS, ' . count($files['script'] ?? []) . ' JavaScript'); ?></summary><code class="lfp-handles"><?php echo esc_html(implode(', ', array_merge($files['style'] ?? [], $files['script'] ?? []))); ?></code></details></td>
                    <td>
                        <select id="<?php echo esc_attr($id); ?>" name="rules[<?php echo esc_attr($folder); ?>][mode]" class="lfp-assets-mode">
                            <option value="everywhere" <?php selected($rule['mode'], 'everywhere'); ?>>Everywhere (as now)</option>
                            <option value="only" <?php selected($rule['mode'], 'only'); ?>>Only at these addresses</option>
                            <option value="nowhere" <?php selected($rule['mode'], 'nowhere'); ?>>Nowhere on the site</option>
                        </select>
                        <textarea name="rules[<?php echo esc_attr($folder); ?>][pages]" rows="2" class="large-text code lfp-assets-pages" placeholder="/contact/&#10;/book/*" aria-label="<?php echo esc_attr('Addresses where ' . ($names[$folder] ?? $folder) . ' loads'); ?>" <?php if ($rule['mode'] !== 'only') { echo 'hidden'; } ?>><?php echo esc_textarea($rule['pages']); ?></textarea>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">Addresses: one per line, for example /contact/ or /book/* (the * matches anything); / is the home page.</p>
        <p><label><input type="checkbox" name="forget" value="1"> Forget the files seen so far and start the list again</label></p>
        <div class="lfp-save-bar"><?php submit_button('Save Changes', 'primary', 'submit', false); ?> <span class="lfa-unsaved" hidden>Unsaved changes</span></div>
    </form>
    <script>
    document.querySelectorAll('.lfp-assets-mode').forEach(function (select) {
        select.addEventListener('change', function () {
            select.parentElement.querySelector('.lfp-assets-pages').hidden = select.value !== 'only';
        });
    });
    </script>
    <?php
}
