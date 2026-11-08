<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/**
 * The Redirects feature: send old addresses to new ones (301, 302) or mark them
 * gone (410), add them one by one or import a CSV, and keep a log of addresses
 * people asked for that don't exist (404s), to spot what still needs a redirect.
 *
 * Redirects apply to addresses that don't exist on the site, so a page always
 * wins over a redirect. Sources are matched by path (no query string), ignoring
 * case and the trailing slash; "/old/*" matches everything under /old/, and a
 * "*" in the target is replaced by the matched rest.
 */

const OPTION_REDIRECTS = 'leapfrog_performance_redirects';
const OPTION_404_LOG = 'leapfrog_performance_404_log';
const LOG_LIMIT = 200;

function redirects_fields(array $fields): array
{
    $fields['redirects'] = [
        'log_404' => ['type' => 'bool', 'default' => true, 'label' => 'Keep a log of missing addresses', 'help' => 'Notes the last 200 addresses visitors asked for that don’t exist, with how often, so you can redirect them. Obvious attack probes (.php files, wp-login, .env) are skipped.'],
    ];
    return $fields;
}

function redirects_boot(): void
{
    add_filter('leapfrog_performance_fields', __NAMESPACE__ . '\\redirects_fields');
    add_action('template_redirect', __NAMESPACE__ . '\\do_redirects', 0);
    foreach (['add', 'delete', 'import', 'export', 'clear_log'] as $action) {
        add_action('admin_post_leapfrog_performance_redirects_' . $action, __NAMESPACE__ . '\\redirects_' . $action);
    }
}

function redirects(): array
{
    $saved = get_option(OPTION_REDIRECTS, []);
    return is_array($saved) ? $saved : [];
}

/** "/Old-Page/?x=1" → "/old-page" (the form sources are stored and matched in). */
function normalise_path(string $address): string
{
    $path = (string) wp_parse_url(trim($address), PHP_URL_PATH);
    $home = rtrim((string) wp_parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home !== '' && stripos($path, $home . '/') === 0) { $path = substr($path, strlen($home)); }
    $path = strtolower(rawurldecode($path));
    return '/' . trim($path, '/');
}

/** A target: "/new/", a full address, or "" for 410 Gone. */
function clean_target(string $target): ?string
{
    $target = trim($target);
    if ($target === '') { return ''; }
    if (strpos($target, '/') === 0) { return preg_match('#^/[^\s<>"\'\\\\]*$#D', $target) ? $target : null; }
    // A full address must be typed as one: http(s)://, no spaces.
    if (!preg_match('#^https?://[^\s]+$#i', $target) || !filter_var($target, FILTER_VALIDATE_URL)) { return null; }
    $url = esc_url_raw($target, ['http', 'https']);
    return $url !== '' ? $url : null;
}

function find_redirect(string $path): ?array
{
    $all = redirects();
    if (isset($all[$path])) { return $all[$path] + ['rest' => '']; }
    // Longest wildcard first: "/blog/2020/*" beats "/blog/*".
    $best = null;
    foreach ($all as $source => $redirect) {
        if (substr($source, -2) !== '/*') { continue; }
        $base = substr($source, 0, -2);
        if (($path === $base || strpos($path, $base . '/') === 0) && (!$best || strlen($base) > strlen($best['base']))) {
            $best = $redirect + ['base' => $base, 'rest' => ltrim(substr($path, strlen($base)), '/')];
        }
    }
    return $best;
}

function do_redirects(): void
{
    if (!is_404() || is_admin()) { return; }
    $path = normalise_path(isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/');
    $redirect = find_redirect($path);
    if ($redirect) {
        if ((int) $redirect['code'] === 410) {
            status_header(410);
            nocache_headers();
            return; // The theme's 404 page is shown with status 410 Gone.
        }
        $to = str_replace('*', $redirect['rest'], (string) $redirect['to']);
        $url = strpos($to, '/') === 0 ? home_url($to) : $to;
        // wp_redirect, not wp_safe_redirect: a redirect may go to another site on purpose.
        wp_redirect($url, (int) $redirect['code'] === 302 ? 302 : 301, 'Leapfrog Performance');
        exit;
    }
    if (setting('redirects', 'log_404')) { log_404($path); }
}

function log_404(string $path): void
{
    if (preg_match('#\.(php|env|ini|sql|bak|git|log|zip|tar|gz)(/|$)|^/(wp-login|wp-admin|xmlrpc|\.well-known|cgi-bin|vendor)#i', $path)) { return; }
    $log = get_option(OPTION_404_LOG, []);
    $log = is_array($log) ? $log : [];
    $entry = $log[$path] ?? ['count' => 0, 'first' => time()];
    $entry['count']++;
    $entry['last'] = time();
    $entry['referer'] = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : ($entry['referer'] ?? '');
    unset($log[$path]);
    $log[$path] = $entry;
    if (count($log) > LOG_LIMIT) { $log = array_slice($log, -LOG_LIMIT, null, true); }
    update_option(OPTION_404_LOG, $log, false);
}

/* -------------------------------------------------------------------------
 * Changing redirects
 * ---------------------------------------------------------------------- */

/** Add or replace one redirect. Returns an error message, or ''. */
function add_redirect(string $from, string $to, int $code, array &$all): string
{
    $wildcard = substr(trim($from), -1) === '*';
    $source = normalise_path(rtrim(trim($from), '*'));
    if ($wildcard) { $source = rtrim($source, '/') . '/*'; }
    if ($source === '/' || $source === '/*') { return 'The home page can’t be redirected here.'; }
    $code = in_array($code, [301, 302, 410], true) ? $code : 301;
    $target = clean_target($to);
    if ($target === null || ($target === '' && $code !== 410)) { return 'Each redirect needs a target: an address starting with / or a full web address (or choose 410 Gone).'; }
    if ($target !== '' && strpos($target, '/') === 0 && normalise_path($target) === rtrim($source, '/*')) { return 'A redirect can’t point to itself.'; }
    $all[$source] = ['to' => $code === 410 ? '' : $target, 'code' => $code, 'added' => time()];
    return '';
}

function redirects_back(array $args = []): void
{
    wp_safe_redirect(page_url($args + ['tab' => 'redirects']));
    exit;
}

function redirects_add(): void
{
    authorise('leapfrog_performance_redirects');
    $all = redirects();
    $error = add_redirect((string) wp_unslash($_POST['from'] ?? ''), (string) wp_unslash($_POST['to'] ?? ''), (int) ($_POST['code'] ?? 301), $all);
    if ($error !== '') { redirects_back(['lfp_redirect_error' => rawurlencode($error)]); }
    update_option(OPTION_REDIRECTS, $all, false);
    redirects_back(['lfp_redirect_done' => 'added']);
}

function redirects_delete(): void
{
    authorise('leapfrog_performance_redirects');
    $all = redirects();
    foreach (isset($_POST['remove']) && is_array($_POST['remove']) ? wp_unslash($_POST['remove']) : [] as $source) {
        unset($all[(string) $source]);
    }
    update_option(OPTION_REDIRECTS, $all, false);
    redirects_back(['lfp_redirect_done' => 'deleted']);
}

/** CSV: from,to[,code] per line; a header line and blank lines are skipped. */
function redirects_import(): void
{
    authorise('leapfrog_performance_redirects');
    $csv = isset($_POST['csv']) && is_string($_POST['csv']) ? wp_unslash($_POST['csv']) : '';
    $file = $_FILES['csv_file']['tmp_name'] ?? '';
    if ($file && is_uploaded_file($file) && filesize($file) <= 2097152) { $csv .= "\n" . (string) file_get_contents($file); }
    $all = redirects();
    $added = 0;
    $problems = [];
    foreach (preg_split('/\R/', $csv) as $number => $line) {
        if (trim($line) === '') { continue; }
        $cells = str_getcsv($line, ',', '"', '\\');
        $from = trim((string) ($cells[0] ?? ''));
        if ($from === '' || in_array(strtolower($from), ['from', 'source', 'old', 'old url', 'url'], true)) { continue; }
        $error = add_redirect($from, (string) ($cells[1] ?? ''), (int) ($cells[2] ?? 301), $all);
        if ($error === '') { $added++; } elseif (count($problems) < 5) { $problems[] = 'Line ' . ($number + 1) . ': ' . $error; }
    }
    update_option(OPTION_REDIRECTS, $all, false);
    redirects_back(['lfp_redirect_done' => 'imported', 'count' => $added] + ($problems ? ['lfp_redirect_error' => rawurlencode(implode(' ', $problems))] : []));
}

function redirects_export(): void
{
    authorise('leapfrog_performance_redirects');
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="redirects.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['from', 'to', 'code'], ',', '"', '\\');
    foreach (redirects() as $source => $redirect) { fputcsv($out, [$source, $redirect['to'], $redirect['code']], ',', '"', '\\'); }
    fclose($out);
    exit;
}

function redirects_clear_log(): void
{
    authorise('leapfrog_performance_redirects');
    delete_option(OPTION_404_LOG);
    redirects_back(['lfp_redirect_done' => 'cleared']);
}

/* -------------------------------------------------------------------------
 * Screen
 * ---------------------------------------------------------------------- */

function render_redirects_tab(): void
{
    $all = redirects();
    ksort($all);
    $post = admin_url('admin-post.php');
    $done = isset($_GET['lfp_redirect_done']) ? sanitize_key($_GET['lfp_redirect_done']) : '';
    $error = isset($_GET['lfp_redirect_error']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['lfp_redirect_error']))) : '';
    $messages = ['added' => 'Redirect saved.', 'deleted' => 'Redirects removed.', 'cleared' => 'Missing-address log cleared.', 'imported' => (isset($_GET['count']) ? absint($_GET['count']) : 0) . ' redirects imported.'];
    if (isset($messages[$done])) { echo '<div class="notice notice-success is-dismissible lfa-notice-keep"><p>' . esc_html($messages[$done]) . '</p></div>'; }
    if ($error !== '') { echo '<div class="notice notice-error is-dismissible lfa-notice-keep"><p>' . esc_html($error) . '</p></div>'; }
    $prefill = isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : '';
    $log = get_option(OPTION_404_LOG, []);
    $log = is_array($log) ? array_reverse($log, true) : [];
    ?>
    <p class="lfp-narrow-text">Send old addresses to new ones, for example after moving a site. Redirects only apply to addresses that don’t exist on the site, so a page always wins.</p>

    <h2>Add a redirect</h2>
    <form action="<?php echo esc_url($post); ?>" method="post" class="lfp-redirect-add">
        <input type="hidden" name="action" value="leapfrog_performance_redirects_add">
        <?php wp_nonce_field('leapfrog_performance_redirects', '_lfp_nonce'); ?>
        <label>From <input type="text" name="from" class="regular-text code" value="<?php echo esc_attr($prefill); ?>" placeholder="/old-page/ or /blog/*" required></label>
        <label>To <input type="text" name="to" class="regular-text code" placeholder="/new-page/ or /news/*"></label>
        <label>Type <select name="code"><option value="301">Moved for good (301)</option><option value="302">For now (302)</option><option value="410">Gone (410)</option></select></label>
        <?php submit_button('Add Redirect', 'primary', 'submit', false); ?>
    </form>
    <p class="description">“/old/*” sends everything under /old/; a * in the target is replaced by the rest of the address (/blog/* → /news/*). Case and a trailing slash don’t matter.</p>

    <h2>Redirects <span class="lfp-count"><?php echo (int) count($all); ?></span></h2>
    <?php if ($all) : ?>
    <form action="<?php echo esc_url($post); ?>" method="post">
        <input type="hidden" name="action" value="leapfrog_performance_redirects_delete">
        <?php wp_nonce_field('leapfrog_performance_redirects', '_lfp_nonce'); ?>
        <table class="widefat striped lfp-redirects-table">
            <thead><tr><td class="check-column"><span class="screen-reader-text">Remove</span></td><th scope="col">From</th><th scope="col">To</th><th scope="col">Type</th></tr></thead>
            <tbody>
            <?php foreach ($all as $source => $redirect) : ?>
                <tr>
                    <th scope="row" class="check-column"><input type="checkbox" name="remove[]" value="<?php echo esc_attr((string) $source); ?>" aria-label="<?php echo esc_attr('Remove ' . $source); ?>"></th>
                    <td><code><?php echo esc_html((string) $source); ?></code></td>
                    <td><?php echo $redirect['to'] === '' ? '—' : '<code>' . esc_html($redirect['to']) . '</code>'; ?></td>
                    <td><?php echo esc_html(['301' => 'Moved for good', '302' => 'For now', '410' => 'Gone'][(string) $redirect['code']] ?? (string) $redirect['code']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p><?php submit_button('Remove Ticked', 'secondary', 'submit', false); ?>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg('action', 'leapfrog_performance_redirects_export', $post), 'leapfrog_performance_redirects', '_lfp_nonce')); ?>">Export CSV</a></p>
    </form>
    <?php else : ?>
        <p class="description">No redirects yet.</p>
    <?php endif; ?>

    <details class="lfp-panel">
        <summary>Import from a CSV file</summary>
        <form action="<?php echo esc_url($post); ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="leapfrog_performance_redirects_import">
            <?php wp_nonce_field('leapfrog_performance_redirects', '_lfp_nonce'); ?>
            <p>One redirect per line: <code>from,to</code> or <code>from,to,code</code> (301, 302 or 410). A header line is skipped. Full old addresses work too (https://old-site.com/page → /page). Existing redirects with the same “from” are replaced.</p>
            <p><input type="file" name="csv_file" accept=".csv,text/csv"></p>
            <p><label for="lfp-redirect-csv">Or paste them</label><br><textarea id="lfp-redirect-csv" name="csv" rows="6" class="large-text code" placeholder="/about-us/,/about/&#10;/blog/*,/news/*&#10;/old-offer/,,410"></textarea></p>
            <?php submit_button('Import Redirects', 'secondary', 'submit', false); ?>
        </form>
    </details>

    <h2>Missing addresses <span class="lfp-count"><?php echo (int) count($log); ?></span></h2>
    <?php if ($log) : ?>
        <table class="widefat striped lfp-redirects-table">
            <thead><tr><th scope="col">Address</th><th scope="col">Times</th><th scope="col">Last</th><th scope="col">Came from</th><th scope="col"><span class="screen-reader-text">Action</span></th></tr></thead>
            <tbody>
            <?php foreach (array_slice($log, 0, 100, true) as $path => $entry) : ?>
                <tr>
                    <td><code><?php echo esc_html((string) $path); ?></code></td>
                    <td><?php echo (int) $entry['count']; ?></td>
                    <td><?php echo esc_html(human_time_diff((int) $entry['last']) . ' ago'); ?></td>
                    <td class="lfp-referer"><?php echo $entry['referer'] ? esc_html(wp_parse_url($entry['referer'], PHP_URL_HOST) . wp_parse_url($entry['referer'], PHP_URL_PATH)) : '—'; ?></td>
                    <td><a href="<?php echo esc_url(page_url(['tab' => 'redirects', 'from' => $path])); ?>">Add redirect</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <form action="<?php echo esc_url($post); ?>" method="post">
            <input type="hidden" name="action" value="leapfrog_performance_redirects_clear_log">
            <?php wp_nonce_field('leapfrog_performance_redirects', '_lfp_nonce'); ?>
            <p><?php submit_button('Clear the Log', 'secondary', 'submit', false); ?></p>
        </form>
    <?php else : ?>
        <p class="description">None logged yet.</p>
    <?php endif; ?>
    <?php render_fields_form('redirects', ['Log' => ['log_404']]);
}
