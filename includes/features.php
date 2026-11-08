<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/**
 * Every switchable feature. Each one is a tab on Leapfrog > Leapfrog Performance;
 * its files are only loaded, and its boot function only runs, while it is on.
 */
function features(): array
{
    return [
        'bloat' => [
            'label' => 'Bloat',
            'description' => 'Switch off what WordPress adds to every page and the admin but your site doesn’t use: emoji, embeds, feeds, comments, Heartbeat, extra emails and more.',
            'off' => 'WordPress loads everything as usual again. Your choices are kept.',
            'files' => ['bloat.php'],
            'boot' => __NAMESPACE__ . '\\bloat_boot',
            'render' => __NAMESPACE__ . '\\render_bloat_tab',
        ],
        'media' => [
            'label' => 'Media',
            'description' => 'Media pages off, which image sizes are made and shown in the pickers, the Social size, captions and AVIF uploads.',
            'off' => 'Media pages, image sizes and captions go back to WordPress’s own. Your choices are kept.',
            'files' => ['media.php'],
            'boot' => __NAMESPACE__ . '\\media_boot',
            'render' => __NAMESPACE__ . '\\render_media_tab',
        ],
        'security' => [
            'label' => 'Security',
            'description' => 'Hardening beside FluentAuth: no file editors, security headers, hidden WordPress version and users list, author pages off, vague log-in errors.',
            'off' => 'These protections stop; FluentAuth’s carry on. Your choices are kept.',
            'files' => ['security.php'],
            'boot' => __NAMESPACE__ . '\\security_boot',
            'render' => __NAMESPACE__ . '\\render_security_tab',
        ],
        'assets' => [
            'label' => 'Load only where used',
            'description' => 'For each plugin, choose where its CSS and JavaScript load on the site: everywhere, only at chosen addresses, or nowhere.',
            'off' => 'Every plugin’s files load wherever the plugin puts them. Your rules are kept.',
            'files' => ['assets.php'],
            'boot' => __NAMESPACE__ . '\\assets_boot',
            'render' => __NAMESPACE__ . '\\render_assets_tab',
        ],
        'redirects' => [
            'label' => 'Redirects',
            'description' => 'Send old addresses to new ones (one by one or from a CSV file) and log the missing addresses visitors ask for.',
            'off' => 'No redirects happen and nothing is logged. Your redirects are kept.',
            'files' => ['redirects.php'],
            'boot' => __NAMESPACE__ . '\\redirects_boot',
            'render' => __NAMESPACE__ . '\\render_redirects_tab',
        ],
        'access' => [
            'label' => 'Site access',
            'description' => 'Maintenance mode (with a Bricks template as the page) or one shared password for previews before launch.',
            'off' => 'The site is open to everyone. Your settings are kept.',
            'files' => ['access.php'],
            'boot' => __NAMESPACE__ . '\\access_boot',
            'render' => __NAMESPACE__ . '\\render_access_tab',
        ],
        'search' => [
            'label' => 'Search engines',
            'description' => 'Edit robots.txt, and choose what WordPress’s sitemap lists (no users sitemap).',
            'off' => 'robots.txt and the sitemap are WordPress’s own again. Your settings are kept.',
            'files' => ['search-engines.php'],
            'boot' => __NAMESPACE__ . '\\search_engines_boot',
            'render' => __NAMESPACE__ . '\\render_search_tab',
        ],
        'rollback' => [
            'label' => 'Rollback',
            'description' => 'Put a plugin or theme from WordPress.org back to an earlier version.',
            'off' => 'The Rollback tab is hidden. Nothing else changes.',
            'files' => ['rollback.php'],
            'boot' => __NAMESPACE__ . '\\rollback_boot',
            'render' => __NAMESPACE__ . '\\render_rollback_tab',
        ],
    ];
}

/** Features planned for later versions, listed on the Features tab. */
function coming_features(): array
{
    return [
    ];
}

function disabled_features(): array
{
    $disabled = get_option(OPTION_DISABLED, []);
    return is_array($disabled) ? $disabled : [];
}

function feature_enabled(string $id): bool
{
    return isset(features()[$id]) && !in_array($id, disabled_features(), true);
}

function save_features(): void
{
    authorise('leapfrog_performance_features');
    $on = isset($_POST['features']) && is_array($_POST['features']) ? array_map('sanitize_key', wp_unslash($_POST['features'])) : [];
    update_option(OPTION_DISABLED, array_values(array_diff(array_keys(features()), $on)));
    return_to_page('features_saved', ['tab' => 'features']);
}
add_action('admin_post_leapfrog_performance_features', __NAMESPACE__ . '\\save_features');

function render_features_tab(): void
{
    ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="leapfrog_performance_features">
        <?php wp_nonce_field('leapfrog_performance_features', '_lfp_nonce'); ?>
        <p>Switch off anything a site doesn’t need. A feature that is off adds nothing to the site, and its tab is hidden.</p>
        <table class="widefat striped lfp-feature-table">
            <thead><tr><th scope="col" class="lfp-feature-switch">On</th><th scope="col">Feature</th><th scope="col">When it is off</th></tr></thead>
            <tbody>
            <?php foreach (features() as $id => $feature) : ?>
                <tr>
                    <td class="lfp-feature-switch"><input type="checkbox" class="lfp-toggle" id="lfp-feature-<?php echo esc_attr($id); ?>" name="features[]" value="<?php echo esc_attr($id); ?>" <?php checked(feature_enabled($id)); ?>></td>
                    <td><label for="lfp-feature-<?php echo esc_attr($id); ?>"><strong><?php echo esc_html($feature['label']); ?></strong></label><br><span class="description"><?php echo esc_html($feature['description']); ?></span></td>
                    <td class="description"><?php echo esc_html($feature['off']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php submit_button('Save Features'); ?>
    </form>
    <?php render_import_section(); ?>
    <?php if (coming_features()) : ?>
    <h2>Coming next</h2>
    <ul class="lfp-coming">
        <?php foreach (coming_features() as $version => $text) : ?>
            <li><strong><?php echo esc_html($version); ?></strong> <?php echo esc_html($text); ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif;
}
