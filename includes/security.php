<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/**
 * The Security feature: hardening beside FluentAuth. FluentAuth handles log-in
 * limits, two-factor, passkeys, XML-RPC, application passwords, hiding usernames,
 * IP rules, file scans and the audit log; nothing here repeats those.
 */

function security_fields(array $fields): array
{
    $fields['security'] = [
        'file_editor' => ['type' => 'bool', 'default' => true, 'label' => 'Switch off the file editors', 'help' => 'Removes Appearance > Theme File Editor and Plugins > Plugin File Editor, so nobody (or nothing that gets into an administrator account) can change PHP files from the admin.'],
        'headers' => ['type' => 'bool', 'default' => true, 'label' => 'Add security headers', 'help' => 'Tells browsers not to guess file types, not to show the site inside other sites’ frames, and to send only the site address (not the full page address) to other sites. Bricks and the admin keep working.'],
        'hsts' => ['type' => 'bool', 'default' => false, 'label' => 'Always use HTTPS (HSTS)', 'help' => 'Tells browsers to use only https:// for this site for a year. Switch on only once the live site always works on https://, including any subdomains.'],
        'hide_version' => ['type' => 'bool', 'default' => true, 'label' => 'Hide the WordPress version', 'help' => 'Removes the version number from the page, feeds and WordPress’s own file addresses (replaced with a code that still changes when WordPress updates).'],
        'rest_users' => ['type' => 'bool', 'default' => true, 'label' => 'Hide the users list from visitors', 'help' => 'The public REST API lists every author’s username at /wp-json/wp/v2/users. Switched on, only logged-in people can see it.'],
        'author_pages' => ['type' => 'bool', 'default' => true, 'label' => 'Switch off author pages', 'help' => 'Author pages (/author/name/) and ?author=1 addresses go to the home page, so usernames can’t be found that way. Leave off if the site shows author pages.'],
        'login_errors' => ['type' => 'bool', 'default' => true, 'label' => 'Vague log-in errors', 'help' => 'A failed log-in says only that the details are wrong, not whether the username exists.'],
    ];
    return $fields;
}

function security_boot(): void
{
    add_filter('leapfrog_performance_fields', __NAMESPACE__ . '\\security_fields');
    $s = settings('security');

    if ($s['file_editor'] && !defined('DISALLOW_FILE_EDIT')) {
        // WordPress checks this when deciding who may edit files, so defining it now is enough.
        define('DISALLOW_FILE_EDIT', true);
        $GLOBALS['lfp_file_edit_ours'] = true;
    }

    if ($s['headers'] || $s['hsts']) {
        $send = static function () use ($s) {
            if (headers_sent()) { return; }
            if ($s['headers']) {
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: SAMEORIGIN');
                header('Referrer-Policy: strict-origin-when-cross-origin');
            }
            if ($s['hsts'] && is_ssl()) {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            }
        };
        add_action('send_headers', $send);
        add_action('admin_init', $send);
        add_action('login_init', $send);
    }

    if ($s['hide_version']) {
        add_filter('the_generator', '__return_empty_string');
        $hide = static function ($src) {
            global $wp_version;
            if (!is_string($src) || strpos($src, 'ver=' . $wp_version) === false) { return $src; }
            // Still changes when WordPress updates, so browsers fetch new files.
            return add_query_arg('ver', substr(md5($wp_version . wp_salt('nonce')), 0, 8), $src);
        };
        add_filter('script_loader_src', $hide, 20);
        add_filter('style_loader_src', $hide, 20);
    }

    if ($s['rest_users']) {
        add_filter('rest_endpoints', static function ($endpoints) {
            if (is_user_logged_in()) { return $endpoints; }
            foreach (array_keys($endpoints) as $route) {
                if (strpos($route, '/wp/v2/users') === 0) { unset($endpoints[$route]); }
            }
            return $endpoints;
        });
    }

    if ($s['author_pages']) {
        add_action('template_redirect', static function () {
            if (is_author() || (isset($_GET['author']) && !is_admin())) {
                wp_safe_redirect(home_url('/'), 301);
                exit;
            }
        }, 1);
        // Author links in themes and plugins go to the home page too.
        add_filter('author_link', static function () { return home_url('/'); });
    }

    if ($s['login_errors']) {
        add_filter('login_errors', static function ($message) {
            global $errors;
            $codes = $errors instanceof \WP_Error ? $errors->get_error_codes() : [];
            // Only replace "wrong username/password" messages; keep the others (lockouts, expired links).
            if (array_intersect($codes, ['invalid_username', 'invalid_email', 'incorrect_password', 'invalidcombo'])) {
                return '<strong>Error:</strong> The username, email address or password is not right. <a href="' . esc_url(wp_lostpassword_url()) . '">Lost your password?</a>';
            }
            return $message;
        });
    }
}

function render_security_tab(): void
{
    $fileEditorFixed = defined('DISALLOW_FILE_EDIT') && empty($GLOBALS['lfp_file_edit_ours']);
    echo '<p class="lfp-narrow-text">Hardening that sits beside FluentAuth. Log-in limits, two-factor, passkeys, XML-RPC, application passwords, hiding usernames, IP rules and the audit log are set in FluentAuth.</p>';
    if ($fileEditorFixed) {
        echo '<div class="notice notice-info inline"><p>wp-config.php already decides whether the file editors are on (DISALLOW_FILE_EDIT), so the first switch has no effect on this site.</p></div>';
    }
    render_fields_form('security', [
        'In the admin' => ['file_editor', 'login_errors'],
        'For visitors' => ['headers', 'hsts', 'hide_version', 'rest_users', 'author_pages'],
    ]);
}
