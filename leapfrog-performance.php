<?php
/**
 * Plugin Name:       Leapfrog Performance
 * Plugin URI:        https://github.com/leapfrog-media/leapfrog-performance
 * Description:       The Leapfrog Media performance and hardening plugin: removes what WordPress loads but your site doesn't use holds the media settings and adds hardening beside FluentAuth and loads plugin files only where they are used, plus redirects, site access, search engine settings and rollback. Leapfrog > Leapfrog Performance.
 * Version:           0.5.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Leapfrog Media
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       leapfrog-performance
 */

namespace Leapfrog\Performance;

if (!defined('ABSPATH')) {
    exit;
}

const VERSION = '0.5.0';
const FILE = __FILE__;

/** Settings screen: Leapfrog > Leapfrog Performance (admin.php?page=leapfrog-performance). */
const PAGE = 'leapfrog-performance';
const PAGE_HOOK = 'leapfrog_page_leapfrog-performance';

/** All settings, by feature: ['bloat' => [...], ...]. */
const OPTION_SETTINGS = 'leapfrog_performance';
const OPTION_DISABLED = 'leapfrog_performance_disabled_features';
const OPTION_VERSION = 'leapfrog_performance_version';

require_once __DIR__ . '/includes/menu.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/features.php';
require_once __DIR__ . '/includes/import.php';

// A feature that is switched off is not loaded at all.
foreach (features() as $id => $feature) {
    if (feature_enabled($id)) {
        foreach ($feature['files'] as $file) {
            require_once __DIR__ . '/includes/' . $file;
        }
        ($feature['boot'])();
    }
}

register_activation_hook(__FILE__, __NAMESPACE__ . '\\activate');

function page_url(array $args = []): string
{
    return add_query_arg($args, admin_url('admin.php?page=' . PAGE));
}

function asset_url(string $path): string
{
    return plugins_url($path, FILE);
}

/** File modification time, so browsers reload assets after an update. */
function asset_version(string $path): string
{
    return (string) filemtime(__DIR__ . '/' . $path);
}
