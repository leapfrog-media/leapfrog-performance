<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/**
 * The Bloat feature: switch off what WordPress adds to every page and the
 * admin but the site doesn't use. Replaces FlyingPress's Bloat tab and the
 * matching ASE settings (XML-RPC is left to FluentAuth).
 *
 * Defaults are Leapfrog's standard settings for a new site. Two are off by
 * default because they can change how a page looks: block editor CSS (needed
 * wherever content was written in the block editor) and site search.
 */

function bloat_fields(array $fields): array
{
    $types = [];
    if (did_action('init')) {
        foreach (get_post_types(['public' => true], 'objects') as $type) {
            if (post_type_supports($type->name, 'comments') || in_array($type->name, ['post', 'page'], true)) { $types[$type->name] = $type->labels->name; }
        }
    }
    $flyingPress = in_array('flying-press/flying-press.php', (array) get_option('active_plugins', []), true);
    $fields['bloat'] = [
        // On the site
        'emoji' => ['type' => 'bool', 'default' => true, 'label' => 'Emoji', 'help' => 'Removes WordPress’s emoji script and styles. Emoji still show, using the visitor’s own fonts.'],
        'embeds' => ['type' => 'bool', 'default' => true, 'label' => 'Embeds (oEmbed)', 'help' => 'Removes the embed script and links, so other sites can’t show your pages as embedded cards. Bricks video and map elements still work.'],
        'feeds' => ['type' => 'bool', 'default' => true, 'label' => 'RSS feeds', 'help' => 'Removes feed links; feed addresses go to the home page. Leave feeds on if people follow the site’s news by RSS.'],
        'jquery_migrate' => ['type' => 'bool', 'default' => true, 'label' => 'jQuery Migrate', 'help' => 'Only needed by very old jQuery code. Removed on the site; the admin keeps it.'],
        'dashicons' => ['type' => 'bool', 'default' => true, 'label' => 'Dashicons for visitors', 'help' => 'WordPress’s icon font, needed only for the toolbar. Logged-in people keep it.'],
        'head_clutter' => ['type' => 'bool', 'default' => true, 'label' => 'Head clutter', 'help' => 'Removes the RSD, Windows Live Writer, shortlink, generator and REST API tags from every page’s head.'],
        'block_css' => ['type' => 'bool', 'default' => false, 'label' => 'Block editor CSS', 'help' => 'Removes WordPress’s block styles and global styles. Switch this on only if no page shows content written in the block editor; Bricks pages don’t need it.'],
        'wp_lazy_load' => ['type' => 'bool', 'default' => $flyingPress, 'label' => 'WordPress lazy loading', 'help' => 'Switches off WordPress’s own lazy loading, for sites where FlyingPress lazy loads images instead.'],
        'search' => ['type' => 'bool', 'default' => false, 'label' => 'Site search', 'help' => 'Turns off WordPress search: search addresses (?s=) go to the home page. For sites without a search box.'],
        // Comments and pings
        'comments' => ['type' => 'choice', 'default' => 'off', 'label' => 'Comments', 'choices' => ['keep' => 'Keep comments', 'off' => 'Off everywhere', 'some' => 'Off for the content types ticked below'], 'help' => 'Off closes comment forms, hides existing comments and comment feeds and, when off everywhere, removes the Comments menu.'],
        'comments_types' => ['type' => 'list', 'default' => [], 'label' => 'Comments off for', 'choices' => $types, 'help' => 'Used when Comments is set to the ticked content types.'],
        'pingbacks' => ['type' => 'bool', 'default' => true, 'label' => 'Pingbacks and trackbacks', 'help' => 'Stops WordPress sending and receiving them, including pings to your own posts.'],
        // In the admin
        'revisions' => ['type' => 'number', 'default' => 10, 'min' => 0, 'max' => 100, 'allow_empty' => true, 'placeholder' => 'No limit', 'unit' => 'per post or page', 'label' => 'Revisions kept', 'help' => 'Empty keeps every revision; 0 keeps none.'],
        'heartbeat_admin' => ['type' => 'choice', 'default' => '60', 'label' => 'Heartbeat in the admin', 'choices' => ['default' => 'WordPress default', '60' => 'Every 60 seconds', '120' => 'Every 2 minutes', 'off' => 'Off'], 'help' => 'WordPress’s background check-in with the server. Slower means less server load.'],
        'heartbeat_editor' => ['type' => 'choice', 'default' => 'default', 'label' => 'Heartbeat in the editor', 'choices' => ['default' => 'WordPress default (15 seconds)', '30' => 'Every 30 seconds', '60' => 'Every 60 seconds'], 'help' => 'It keeps autosave and “someone else is editing” warnings working, so it can’t be switched off here.'],
        'heartbeat_site' => ['type' => 'choice', 'default' => 'off', 'label' => 'Heartbeat on the site', 'choices' => ['default' => 'WordPress default', 'off' => 'Off'], 'help' => 'Only runs when a plugin starts it on the site.'],
        'autosave' => ['type' => 'number', 'default' => '', 'min' => 30, 'max' => 600, 'allow_empty' => true, 'placeholder' => '60', 'unit' => 'seconds', 'label' => 'Autosave every', 'help' => 'How often the editor saves a draft while you type. Empty uses WordPress’s 60 seconds.'],
        // Emails
        'admin_email_check' => ['type' => 'bool', 'default' => true, 'label' => 'Admin email check', 'help' => 'Stops WordPress asking administrators every six months to confirm the site’s email address.'],
        'password_admin_email' => ['type' => 'bool', 'default' => true, 'label' => 'Password change email to the administrator', 'help' => 'Stops the email the administrator gets whenever someone changes their password.'],
        'password_user_email' => ['type' => 'bool', 'default' => false, 'label' => 'Password change email to the person', 'help' => 'Stops the email people get when their own password changes. It warns them if someone else changed it, so it stays on by default.'],
        'auto_update_emails' => ['type' => 'bool', 'default' => true, 'label' => 'Auto-update emails', 'help' => 'No email after plugins, themes or WordPress update themselves. Emails about failed updates still come.'],
    ];
    return $fields;
}

function bloat_boot(): void
{
    add_filter('leapfrog_performance_fields', __NAMESPACE__ . '\\bloat_fields');
    $s = settings('bloat');

    if ($s['emoji']) {
        add_action('init', static function () {
            remove_action('wp_head', 'print_emoji_detection_script', 7);
            remove_action('admin_print_scripts', 'print_emoji_detection_script');
            remove_action('wp_print_styles', 'print_emoji_styles');
            remove_action('admin_print_styles', 'print_emoji_styles');
            remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
            remove_action('admin_enqueue_scripts', 'wp_enqueue_emoji_styles');
            remove_filter('the_content_feed', 'wp_staticize_emoji');
            remove_filter('comment_text_rss', 'wp_staticize_emoji');
            remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        });
        add_filter('tiny_mce_plugins', static function ($plugins) { return is_array($plugins) ? array_diff($plugins, ['wpemoji']) : $plugins; });
        // Only the emoji hint, not every resource hint.
        add_filter('wp_resource_hints', static function ($urls, $type) {
            return $type === 'dns-prefetch' ? array_filter($urls, static function ($url) { return !is_string($url) || strpos($url, 's.w.org/images/core/emoji') === false; }) : $urls;
        }, 10, 2);
    }

    if ($s['embeds']) {
        add_action('init', static function () {
            remove_action('wp_head', 'wp_oembed_add_discovery_links');
            remove_action('wp_head', 'wp_oembed_add_host_js');
            remove_action('rest_api_init', 'wp_oembed_register_route');
        }, 9999);
        add_action('wp_footer', static function () { wp_dequeue_script('wp-embed'); });
    }

    if ($s['feeds']) {
        add_action('init', static function () {
            remove_action('wp_head', 'feed_links', 2);
            remove_action('wp_head', 'feed_links_extra', 3);
        });
        add_action('template_redirect', static function () {
            if (is_feed()) { wp_safe_redirect(home_url('/'), 301); exit; }
        }, 1);
    }

    if ($s['jquery_migrate']) {
        add_action('wp_default_scripts', static function ($scripts) {
            if (!is_admin() && isset($scripts->registered['jquery']) && $scripts->registered['jquery']->deps) {
                $scripts->registered['jquery']->deps = array_diff($scripts->registered['jquery']->deps, ['jquery-migrate']);
            }
        });
    }

    if ($s['dashicons']) {
        add_action('wp_enqueue_scripts', static function () {
            if (!is_user_logged_in()) { wp_dequeue_style('dashicons'); wp_deregister_style('dashicons'); }
        }, 100);
    }

    if ($s['head_clutter']) {
        add_action('init', static function () {
            remove_action('wp_head', 'rsd_link');
            remove_action('wp_head', 'wlwmanifest_link');
            remove_action('wp_head', 'wp_shortlink_wp_head');
            remove_action('wp_head', 'wp_generator');
            remove_action('wp_head', 'rest_output_link_wp_head');
            remove_action('template_redirect', 'wp_shortlink_header', 11);
            remove_action('template_redirect', 'rest_output_link_header', 11);
        });
        add_filter('the_generator', '__return_empty_string');
    }

    if ($s['block_css']) {
        add_action('wp_enqueue_scripts', static function () {
            foreach (['wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles'] as $handle) { wp_dequeue_style($handle); }
        }, 100);
        add_action('init', static function () {
            remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
            remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
            remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
        });
    }

    if ($s['wp_lazy_load']) {
        add_filter('wp_lazy_loading_enabled', '__return_false');
    }

    if ($s['search']) {
        add_action('template_redirect', static function () {
            if (is_search() && !is_admin()) { wp_safe_redirect(home_url('/'), 301); exit; }
        }, 1);
        add_filter('get_search_form', '__return_empty_string');
        add_action('admin_bar_menu', static function ($bar) { if (!is_admin()) { $bar->remove_node('search'); } }, 999);
    }

    bloat_comments($s);

    if ($s['pingbacks']) {
        add_filter('xmlrpc_methods', static function ($methods) { unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']); return $methods; });
        add_filter('wp_headers', static function ($headers) { unset($headers['X-Pingback']); return $headers; });
        add_filter('pings_open', '__return_false', 20);
        add_filter('pre_option_default_ping_status', static function () { return 'closed'; });
        add_filter('pre_option_default_pingback_flag', '__return_zero');
        // Sends no pingbacks, to other sites or to your own posts.
        add_action('pre_ping', static function (&$links) { $links = []; });
    }

    if ($s['revisions'] !== '') {
        $keep = (int) $s['revisions'];
        add_filter('wp_revisions_to_keep', static function () use ($keep) { return $keep; }, 20);
    }

    bloat_heartbeat($s);

    if ($s['autosave'] !== '') {
        if (!defined('AUTOSAVE_INTERVAL')) { define('AUTOSAVE_INTERVAL', (int) $s['autosave']); }
        $seconds = (int) $s['autosave'];
        add_filter('block_editor_settings_all', static function ($settings) use ($seconds) { $settings['autosaveInterval'] = $seconds; return $settings; });
    }

    if ($s['admin_email_check']) {
        add_filter('admin_email_check_interval', '__return_zero');
    }
    if ($s['password_admin_email']) {
        // WordPress lets a plugin replace this function before it loads its own.
        require_once __DIR__ . '/pluggable-password.php';
    }
    if ($s['password_user_email']) {
        add_filter('send_password_change_email', '__return_false');
    }
    if ($s['auto_update_emails']) {
        add_filter('auto_plugin_update_send_email', '__return_false');
        add_filter('auto_theme_update_send_email', '__return_false');
        add_filter('auto_core_update_send_email', static function ($send, $type) { return $type === 'success' ? false : $send; }, 10, 2);
    }
}

/** Comments off everywhere, or for some content types. */
function bloat_comments(array $s): void
{
    if ($s['comments'] === 'keep') { return; }
    $everywhere = $s['comments'] === 'off';
    $types = $s['comments_types'];
    if (!$everywhere && !$types) { return; }
    $off = static function ($postId = null) use ($everywhere, $types): bool {
        return $everywhere || in_array(get_post_type($postId ?: get_the_ID()), $types, true);
    };
    add_action('init', static function () use ($everywhere, $types) {
        foreach ($everywhere ? get_post_types() : $types as $type) {
            if (post_type_supports($type, 'comments')) {
                remove_post_type_support($type, 'comments');
                remove_post_type_support($type, 'trackbacks');
            }
        }
    }, 100);
    add_filter('comments_open', static function ($open, $postId) use ($off) { return $off($postId) ? false : $open; }, 20, 2);
    add_filter('pings_open', static function ($open, $postId) use ($off) { return $off($postId) ? false : $open; }, 20, 2);
    add_filter('comments_array', static function ($comments, $postId) use ($off) { return $off($postId) ? [] : $comments; }, 20, 2);
    add_filter('get_comments_number', static function ($count, $postId) use ($off) { return $off($postId) ? 0 : $count; }, 20, 2);
    if (!$everywhere) { return; }
    add_filter('feed_links_show_comments_feed', '__return_false');
    add_action('admin_menu', static function () { remove_menu_page('edit-comments.php'); }, 999);
    add_action('admin_bar_menu', static function ($bar) { $bar->remove_node('comments'); }, 999);
    add_action('admin_init', static function () {
        global $pagenow;
        if ($pagenow === 'edit-comments.php') { wp_safe_redirect(admin_url()); exit; }
    });
    add_action('wp_dashboard_setup', static function () { remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal'); });
}

/** Heartbeat: slower or off in the admin and on the site; slower in the editor. */
function bloat_heartbeat(array $s): void
{
    $place = static function (): string {
        global $pagenow;
        if (!is_admin()) { return 'site'; }
        return in_array($pagenow ?? '', ['post.php', 'post-new.php'], true) ? 'editor' : 'admin';
    };
    add_filter('heartbeat_settings', static function ($settings) use ($s, $place) {
        $value = $s['heartbeat_' . $place()] ?? 'default';
        if (is_numeric($value)) { $settings['interval'] = (int) $value; }
        return $settings;
    });
    // Dequeued rather than removed: removing it breaks scripts that list it as needed.
    // The "session expired" check only works with Heartbeat, so it goes too.
    $stop = static function () use ($s, $place) {
        if (($s['heartbeat_' . $place()] ?? '') === 'off') {
            wp_dequeue_script('wp-auth-check');
            wp_dequeue_style('wp-auth-check');
            remove_action('admin_print_footer_scripts', 'wp_auth_check_html', 5);
            remove_action('wp_print_footer_scripts', 'wp_auth_check_html', 5);
            wp_dequeue_script('heartbeat');
        }
    };
    add_action('admin_enqueue_scripts', $stop, 999);
    add_action('wp_enqueue_scripts', $stop, 999);
}

function render_bloat_tab(): void
{
    echo '<p class="lfp-narrow-text">What WordPress loads on every page and in the admin that most Leapfrog sites don’t use. Switched on = removed. XML-RPC is switched off in FluentAuth.</p>';
    render_fields_form('bloat', [
        'On the site' => ['emoji', 'embeds', 'feeds', 'jquery_migrate', 'dashicons', 'head_clutter', 'block_css', 'wp_lazy_load', 'search'],
        'Comments and pings' => ['comments', 'comments_types', 'pingbacks'],
        'In the admin' => ['revisions', 'heartbeat_admin', 'heartbeat_editor', 'heartbeat_site', 'autosave'],
        'Emails' => ['admin_email_check', 'password_admin_email', 'password_user_email', 'auto_update_emails'],
    ]);
}
