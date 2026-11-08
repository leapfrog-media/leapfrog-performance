<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/**
 * The shared "Leapfrog" admin menu.
 *
 * Every Leapfrog plugin carries a copy of this logic (Leapfrog Admin has it in
 * includes/menu.php, Leapfrog Framework in src/Admin/LeapfrogMenu.php), so the menu works with any one of them installed:
 * whichever plugin runs first adds the top-level link, each plugin adds its own
 * sub-item, and the sub-items are put in a fixed order. Keep the copies in step.
 */
const MENU_SLUG = 'leapfrog';
// Admin first: clicking Leapfrog opens it with the sub-items expanded beneath.
// (Framework is a full-screen editor without the sidebar, so it can't come first.)
const MENU_ORDER = ['leapfrog-admin' => 1, 'leapfrog-framework' => 2, 'leapfrog-header' => 3, 'leapfrog-performance' => 4];

function leapfrog_menu(): void
{
    global $admin_page_hooks;
    if (!empty($admin_page_hooks[MENU_SLUG])) {
        return;
    }
    // Not translated: WordPress builds sub-page hook names from this title.
    $hook = add_menu_page('Leapfrog', 'Leapfrog', 'manage_options', MENU_SLUG, __NAMESPACE__ . '\\leapfrog_menu_page', leapfrog_menu_icon(), 59);
    add_action('load-' . $hook, __NAMESPACE__ . '\\leapfrog_menu_redirect');
}
add_action('admin_menu', __NAMESPACE__ . '\\leapfrog_menu', 9);

/** Drop WordPress's automatic "Leapfrog" sub-item and put the plugins in order. */
function leapfrog_menu_order(): void
{
    global $submenu;
    if (!isset($submenu[MENU_SLUG])) {
        return;
    }
    $items = [];
    foreach (array_values($submenu[MENU_SLUG]) as $index => $item) {
        if ($item[2] !== MENU_SLUG) {
            $items[] = [MENU_ORDER[$item[2]] ?? 50, $index, $item];
        }
    }
    sort($items);
    $submenu[MENU_SLUG] = array_column($items, 2);
    if (!$submenu[MENU_SLUG]) {
        remove_menu_page(MENU_SLUG);
    }
}
add_action('admin_menu', __NAMESPACE__ . '\\leapfrog_menu_order', 999);

/** The top-level link goes to the first sub-item; this catches old or typed links. */
function leapfrog_menu_redirect(): void
{
    global $submenu, $_wp_real_parent_file;
    // WordPress moves the sub-items under the first one's slug once the menu is built.
    $items = $submenu[MENU_SLUG] ?? $submenu[$_wp_real_parent_file[MENU_SLUG] ?? ''] ?? [];
    $first = reset($items);
    if ($first && $first[2] !== MENU_SLUG) {
        wp_safe_redirect(admin_url('admin.php?page=' . $first[2]));
        exit;
    }
}

function leapfrog_menu_page(): void
{
    echo '<div class="wrap"><h1>Leapfrog</h1><p>No Leapfrog screens are available to you.</p></div>';
}

/**
 * The Leapfrog Media mark (outline) as a menu icon. WordPress recolours SVG menu
 * icons to match the colour scheme, so it is drawn in one colour, with a square
 * viewBox so the tall mark sits centred.
 */
function leapfrog_menu_icon(): string
{
    static $icon = null;
    if ($icon === null) {
        $svg = (string) @file_get_contents(dirname(__DIR__) . '/assets/brand/leapfrog-mark.svg');
        $icon = preg_match('/<path[^>]*\sd="([^"]+)"/', $svg, $m)
            ? 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="-189.5 0 801 801"><path fill="black" d="' . $m[1] . '"/></svg>')
            : 'dashicons-admin-generic';
    }
    return $icon;
}
