<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/**
 * The Search engines feature: edit robots.txt, and control WordPress's own
 * sitemap (/wp-sitemap.xml): which content types and categories appear, and
 * no users sitemap (it lists usernames).
 */

function search_engines_fields(array $fields): array
{
    $types = [];
    $taxonomies = [];
    if (did_action('init')) {
        foreach (get_post_types(['public' => true], 'objects') as $type) {
            if ($type->name !== 'attachment') { $types[$type->name] = $type->labels->name; }
        }
        foreach (get_taxonomies(['public' => true], 'objects') as $taxonomy) {
            if ($taxonomy->name !== 'post_format') { $taxonomies[$taxonomy->name] = $taxonomy->labels->name; }
        }
    }
    $fields['search'] = [
        'robots_txt' => ['type' => 'textarea', 'default' => '', 'rows' => 8, 'code' => true, 'label' => 'robots.txt', 'placeholder' => "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: " . home_url('/wp-sitemap.xml'), 'help' => 'What search engines are asked to skip. Leave empty for WordPress’s own (shown faded). It is ignored if a robots.txt file exists in the site’s folder.'],
        'sitemap' => ['type' => 'bool', 'default' => true, 'label' => 'WordPress sitemap', 'help' => 'WordPress’s own sitemap at /wp-sitemap.xml, which search engines use to find pages. Switch off only if an SEO plugin makes its own.'],
        'sitemap_types_off' => ['type' => 'list', 'default' => [], 'choices' => $types, 'label' => 'Leave out of the sitemap', 'help' => 'Content types search engines shouldn’t be pointed to.'],
        'sitemap_tax_off' => ['type' => 'list', 'default' => [], 'choices' => $taxonomies, 'label' => 'Leave these lists out', 'help' => 'Category and tag pages search engines shouldn’t be pointed to.'],
        'sitemap_users' => ['type' => 'bool', 'default' => false, 'label' => 'Users sitemap', 'help' => 'WordPress also lists author pages, which shows usernames. Off by default.'],
    ];
    return $fields;
}

function search_engines_boot(): void
{
    add_filter('leapfrog_performance_fields', __NAMESPACE__ . '\\search_engines_fields');
    $s = settings('search');

    if (trim($s['robots_txt']) !== '') {
        $text = $s['robots_txt'];
        add_filter('robots_txt', static function ($output, $public) use ($text) {
            // "Discourage search engines" (Settings > Reading) still wins.
            return $public ? $text . "\n" : $output;
        }, 99, 2);
    }

    if (!$s['sitemap']) {
        add_filter('wp_sitemaps_enabled', '__return_false');
        return;
    }
    if ($s['sitemap_types_off']) {
        $off = $s['sitemap_types_off'];
        add_filter('wp_sitemaps_post_types', static function ($types) use ($off) { return array_diff_key($types, array_flip($off)); });
    }
    if ($s['sitemap_tax_off']) {
        $off = $s['sitemap_tax_off'];
        add_filter('wp_sitemaps_taxonomies', static function ($taxonomies) use ($off) { return array_diff_key($taxonomies, array_flip($off)); });
    }
    if (!$s['sitemap_users']) {
        add_filter('wp_sitemaps_add_provider', static function ($provider, $name) { return $name === 'users' ? false : $provider; }, 10, 2);
    }
}

function render_search_tab(): void
{
    if (file_exists(ABSPATH . 'robots.txt')) {
        echo '<div class="notice notice-warning inline"><p>A robots.txt file exists in the site’s folder, so the robots.txt text below isn’t used. Delete that file to use it.</p></div>';
    }
    if (!get_option('blog_public')) {
        echo '<div class="notice notice-info inline"><p>Settings > Reading is set to discourage search engines, so robots.txt asks them to skip the whole site and WordPress’s sitemap is off. That setting wins over this tab.</p></div>';
    }
    echo '<p class="lfp-narrow-text">What search engines are told about the site. <a href="' . esc_url(home_url('/robots.txt')) . '" target="_blank" rel="noopener">See robots.txt</a> · <a href="' . esc_url(home_url('/wp-sitemap.xml')) . '" target="_blank" rel="noopener">See the sitemap</a></p>';
    render_fields_form('search', [
        'robots.txt' => ['robots_txt'],
        'Sitemap' => ['sitemap', 'sitemap_types_off', 'sitemap_tax_off', 'sitemap_users'],
    ]);
}
