<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/**
 * The Site access feature: who sees the site.
 * - Open: everyone (normal).
 * - Maintenance: visitors see a maintenance page (a Bricks template, or a simple
 *   built-in page) with "503 Service Unavailable", which tells search engines to
 *   come back later; staff see the real site.
 * - Password: visitors enter one shared password first (for client previews);
 *   logged-in people go straight in.
 * The log-in page, the admin, AJAX, cron and the REST API are never blocked.
 */

const ACCESS_COOKIE = 'lfp_site_access';

function access_fields(array $fields): array
{
    $fields['access'] = [
        'mode' => ['type' => 'choice', 'default' => 'open', 'label' => 'The site is', 'choices' => ['open' => 'Open to everyone', 'maintenance' => 'In maintenance mode', 'password' => 'Behind a password'], 'help' => 'Maintenance shows visitors a “back soon” page; Password asks visitors for one shared password, for previews before launch.'],
        'staff' => ['type' => 'choice', 'default' => 'edit_posts', 'label' => 'Who sees the real site', 'choices' => ['manage_options' => 'Administrators', 'edit_posts' => 'Everyone who can edit content', 'read' => 'Everyone who is logged in'], 'help' => 'For maintenance mode. People with a password always see the site once they have entered it.'],
        'template' => ['type' => 'post', 'post_type' => 'bricks_template', 'none' => '— Leapfrog’s simple page —', 'default' => 0, 'label' => 'Maintenance page', 'help' => 'A Bricks template (Bricks > Templates) to show as the whole page. Without one, a simple page with the title and message below is shown.'],
        'title' => ['type' => 'text', 'default' => 'Back soon', 'label' => 'Title', 'help' => 'For the simple page, and the browser tab.'],
        'message' => ['type' => 'textarea', 'default' => 'We’re making some improvements. Please check back shortly.', 'rows' => 3, 'label' => 'Message', 'help' => 'For the simple page.'],
        'password' => ['type' => 'secret', 'default' => '', 'label' => 'Site password', 'placeholder' => 'Choose a password', 'help' => 'For “Behind a password”. Visitors stay let in on that device for 30 days; changing the password asks everyone again.'],
    ];
    return $fields;
}

function access_boot(): void
{
    add_filter('leapfrog_performance_fields', __NAMESPACE__ . '\\access_fields');
    add_filter('leapfrog_performance_posted', __NAMESPACE__ . '\\access_posted', 10, 2);
    add_action('template_redirect', __NAMESPACE__ . '\\check_site_access', -10);
    add_action('admin_bar_menu', __NAMESPACE__ . '\\access_bar_notice', 100);
    add_action('leapfrog_performance_saved', __NAMESPACE__ . '\\access_saved');
    if (setting('access', 'mode') !== 'open') {
        // While the site isn't open, no page may be cached: a page cached for someone
        // who entered the password would otherwise be served to everyone.
        add_filter('flying_press_is_cacheable', '__return_false');
        add_action('template_redirect', static function () { if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); } }, -11);
        $style = static function () { if (is_admin_bar_showing()) { echo '<style>#wpadminbar .lfp-site-access-notice>.ab-item{background:#b32d2e!important;color:#fff!important}</style>'; } };
        add_action('wp_head', $style);
        add_action('admin_head', $style);
    }
}

/** Pages cached while the site was open would still be served, so empty the page caches. */
function access_saved(string $feature): void
{
    if ($feature !== 'access') { return; }
    if (class_exists('\\FlyingPress\\Purge') && method_exists('\\FlyingPress\\Purge', 'purge_everything')) { \FlyingPress\Purge::purge_everything(); }
    if (function_exists('rocket_clean_domain')) { rocket_clean_domain(); }
    if (function_exists('w3tc_flush_all')) { w3tc_flush_all(); }
    do_action('litespeed_purge_all');
    if (function_exists('wp_cache_flush')) { wp_cache_flush(); }
}

/** The password is stored as a WordPress password hash, never as typed. */
function access_posted(array $posted, string $feature): array
{
    if ($feature === 'access' && isset($posted['password']) && $posted['password'] !== '') {
        $posted['password'] = wp_hash_password((string) $posted['password']);
    }
    return $posted;
}

function access_cookie_value(string $hash): string
{
    return hash_hmac('sha256', $hash, wp_salt('auth'));
}

function check_site_access(): void
{
    $s = settings('access');
    if ($s['mode'] === 'open' || is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || is_robots() || in_builder()) { return; }

    if ($s['mode'] === 'password') {
        if (is_user_logged_in() || $s['password'] === '') { return; }
        $cookie = isset($_COOKIE[ACCESS_COOKIE]) ? (string) $_COOKIE[ACCESS_COOKIE] : '';
        if ($cookie !== '' && hash_equals(access_cookie_value($s['password']), $cookie)) { return; }
        $wrong = false;
        if (isset($_POST['lfp_site_password']) && is_string($_POST['lfp_site_password'])) {
            if (wp_check_password(wp_unslash($_POST['lfp_site_password']), $s['password'])) {
                setcookie(ACCESS_COOKIE, access_cookie_value($s['password']), ['expires' => time() + 30 * DAY_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax']);
                wp_safe_redirect(home_url(isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/'));
                exit;
            }
            sleep(1); // Slows down guessing.
            $wrong = true;
        }
        nocache_headers();
        status_header(401);
        password_page($s, $wrong);
        exit;
    }

    // Maintenance.
    if (current_user_can($s['staff'])) { return; }
    nocache_headers();
    status_header(503);
    header('Retry-After: 3600');
    maintenance_page($s);
    exit;
}

/** A minimal full page around some body HTML, with the site's own head (for Bricks styles). */
function access_document(string $title, string $body, string $class): void
{
    ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html($title . ' – ' . get_bloginfo('name')); ?></title>
<?php wp_head(); ?>
<style>.lfp-access{min-height:100vh;display:grid;place-items:center;margin:0;padding:24px;box-sizing:border-box;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#f6f7f7;color:#1e1e1e}.lfp-access-box{max-width:420px;text-align:center}.lfp-access-box h1{font-size:28px;margin:0 0 12px}.lfp-access-box p{line-height:1.5}.lfp-access-box input{padding:10px;font-size:16px;width:100%;box-sizing:border-box;margin:8px 0;border:1px solid #8c8f94;border-radius:4px}.lfp-access-box button{padding:10px 20px;font-size:16px;border:0;border-radius:4px;background:#1e1e1e;color:#fff;cursor:pointer}.lfp-access-error{color:#b32d2e}</style>
</head>
<body <?php body_class($class); ?>>
<?php echo $body; // phpcs:ignore -- built from escaped parts or a Bricks template ?>
<?php wp_footer(); ?>
</body>
</html>
<?php
}

function maintenance_page(array $s): void
{
    $template = (int) $s['template'];
    if ($template && get_post_type($template) === 'bricks_template' && shortcode_exists('bricks_template')) {
        // Rendered before the head so Bricks can add the template's styles.
        $body = do_shortcode('[bricks_template id="' . $template . '"]');
        access_document((string) $s['title'], $body, 'lfp-maintenance');
        return;
    }
    $body = '<main class="lfp-access"><div class="lfp-access-box"><h1>' . esc_html($s['title']) . '</h1><p>' . nl2br(esc_html($s['message'])) . '</p></div></main>';
    access_document((string) $s['title'], $body, 'lfp-maintenance');
}

function password_page(array $s, bool $wrong): void
{
    $body = '<main class="lfp-access"><form class="lfp-access-box" method="post">'
        . '<h1>' . esc_html(get_bloginfo('name')) . '</h1>'
        . '<p>This site is private for now. Enter the password to see it.</p>'
        . ($wrong ? '<p class="lfp-access-error" role="alert">That password isn’t right.</p>' : '')
        . '<label class="screen-reader-text" for="lfp-site-password">Password</label>'
        . '<input type="password" id="lfp-site-password" name="lfp_site_password" autocomplete="current-password" required autofocus>'
        . '<button type="submit">See the site</button>'
        . '</form></main>';
    access_document('Private', $body, 'lfp-password');
}

/** Staff see a reminder in the toolbar while the site isn't open. */
function access_bar_notice(\WP_Admin_Bar $bar): void
{
    $mode = setting('access', 'mode');
    if ($mode === 'open' || !current_user_can('edit_posts')) { return; }
    $bar->add_node([
        'id' => 'lfp-site-access',
        'title' => $mode === 'maintenance' ? 'Maintenance mode is on' : 'Site behind a password',
        'href' => current_user_can('manage_options') ? page_url(['tab' => 'access']) : false,
        'meta' => ['class' => 'lfp-site-access-notice'],
    ]);
}

function render_access_tab(): void
{
    $s = settings('access');
    if ($s['mode'] === 'password' && $s['password'] === '') {
        echo '<div class="notice notice-warning inline"><p>No password is set yet, so the site is still open. Choose one below.</p></div>';
    }
    echo '<p class="lfp-narrow-text">Who can see the site. The log-in page and the admin always stay open. While the site isn’t open, a reminder shows in the toolbar, pages aren’t cached, and saving this tab empties FlyingPress’s page cache. A host’s own cache (such as 20i’s) may need emptying in its control panel.</p>';
    render_fields_form('access', [
        'Access' => ['mode', 'staff'],
        'Maintenance page' => ['template', 'title', 'message'],
        'Password' => ['password'],
    ]);
}
