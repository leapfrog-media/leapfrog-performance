<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/**
 * The Media feature: media pages off (and the page addresses they hold freed),
 * which image sizes WordPress makes and which appear in the Bricks and media
 * pickers, the 1200×630 Social size, captions and AVIF uploads.
 * Replaces Disable Media Pages, Disable Media Sizes, pl-social-image-size,
 * pl-hide-captions and the "Bricks Image Size Options" snippet.
 */

/** Media addresses already freed look like this (a random UUID). */
const FREED_SLUG = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

function media_fields(array $fields): array
{
    $fields['media'] = [
        'media_pages' => ['type' => 'bool', 'default' => true, 'label' => 'Switch off media pages', 'help' => 'Every uploaded file has its own page (example.com/photo-name/) that nobody needs. Switched on, those pages show “not found”, links to them go to the file itself, and new uploads stop taking page addresses.'],
        'social_size' => ['type' => 'bool', 'default' => true, 'label' => 'Add the Social image size', 'help' => 'Adds a 1200 × 630 cropped size for sharing images (Open Graph), shown as “Social” in the pickers.'],
        'big_image_off' => ['type' => 'bool', 'default' => false, 'label' => 'No big image copies', 'help' => 'Stops WordPress making a scaled “-scaled” copy of uploads over 2560 pixels. Use it when ShortPixel resizes large uploads instead.'],
        'hide_captions' => ['type' => 'bool', 'default' => false, 'label' => 'Hide image captions on the site', 'help' => 'Hides captions everywhere on the site (blocks, galleries, Bricks images). Captions are still stored and shown in the editor.'],
        'avif' => ['type' => 'bool', 'default' => true, 'label' => 'Allow AVIF uploads', 'help' => 'Lets AVIF images be uploaded to the Media Library.'],
        'sizes_off' => ['type' => 'list', 'default' => [], 'label' => 'Sizes not made', 'choices' => []],
        'picker_hidden' => ['type' => 'list', 'default' => [], 'label' => 'Sizes hidden from pickers', 'choices' => []],
    ];
    return $fields;
}

function media_boot(): void
{
    add_filter('leapfrog_performance_fields', __NAMESPACE__ . '\\media_fields');
    add_filter('leapfrog_performance_posted', __NAMESPACE__ . '\\media_posted', 10, 2);
    add_action('admin_post_leapfrog_performance_free_slugs', __NAMESPACE__ . '\\free_media_slugs');
    $s = settings('media');

    if ($s['media_pages']) {
        add_action('template_redirect', static function () {
            if (is_attachment()) {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                nocache_headers();
                // Otherwise WordPress "guesses" the media item for this not-found address and redirects to it.
                add_filter('redirect_canonical', '__return_false');
            }
        }, 0);
        add_filter('attachment_link', static function ($link, $id) { return wp_get_attachment_url($id) ?: $link; }, 10, 2);
        // New uploads get a random address, so "about" stays free for a page.
        add_filter('wp_unique_post_slug', static function ($slug, $id, $status, $type) {
            return $type === 'attachment' && !preg_match(FREED_SLUG, (string) $slug) ? wp_generate_uuid4() : $slug;
        }, 10, 4);
    }

    if ($s['social_size']) {
        add_action('after_setup_theme', static function () { add_image_size('social', 1200, 630, true); }, 20);
    }

    $off = array_diff($s['sizes_off'], ['thumbnail']);
    if ($off) {
        add_filter('intermediate_image_sizes_advanced', static function ($sizes) use ($off) {
            return array_diff_key((array) $sizes, array_flip($off));
        });
    }
    if ($s['big_image_off']) {
        add_filter('big_image_size_threshold', '__return_false');
    }

    // The size pickers in Bricks and the media dialogs. Runs after Bricks adds its sizes.
    $hidden = $s['picker_hidden'];
    $social = $s['social_size'];
    add_filter('image_size_names_choose', static function ($names) use ($hidden, $social) {
        if ($social) { $names['social'] = 'Social'; }
        return array_diff_key((array) $names, array_flip($hidden));
    }, 101);
    add_filter('bricks/builder/image_size_options', static function ($sizes) use ($hidden) {
        return array_diff_key((array) $sizes, array_flip(array_diff($hidden, ['full'])));
    }, 101);

    if ($s['hide_captions']) {
        add_action('wp_head', static function () {
            echo "<style id=\"lfp-hide-captions\">figcaption,.wp-element-caption,.wp-caption-text,.gallery-caption,.blocks-gallery-caption{display:none!important}</style>\n";
        }, 99);
    }

    if ($s['avif']) {
        add_filter('upload_mimes', static function ($mimes) { $mimes['avif'] = 'image/avif'; return $mimes; });
    }
}

/** Every image size, with its dimensions: ['name' => ['width', 'height', 'crop']]. */
function image_sizes(): array
{
    return function_exists('wp_get_registered_image_subsizes') ? wp_get_registered_image_subsizes() : [];
}

function size_label(string $name): string
{
    $labels = ['thumbnail' => 'Thumbnail', 'medium' => 'Medium', 'medium_large' => 'Medium large', 'large' => 'Large', '1536x1536' => '2× medium large', '2048x2048' => '2× large', 'social' => 'Social'];
    return $labels[$name] ?? ucwords(str_replace(['_', '-'], ' ', $name));
}

/** The size table posts the sizes that are on; settings keep the ones that are off. */
function media_posted(array $posted, string $feature): array
{
    if ($feature !== 'media' || !isset($posted['sizes_made'])) { return $posted; }
    $names = array_keys(image_sizes());
    $made = array_filter((array) $posted['sizes_made']);
    $shown = array_filter((array) ($posted['pickers_shown'] ?? []));
    $posted['sizes_off'] = array_values(array_diff($names, $made, ['thumbnail']));
    $posted['picker_hidden'] = array_values(array_diff(array_merge($names, ['full']), $shown));
    unset($posted['sizes_made'], $posted['pickers_shown']);
    return $posted;
}

function render_size_table(array $values): void
{
    $sizes = image_sizes();
    ?>
    <h2>Image sizes</h2>
    <p class="description lfp-narrow-text">WordPress makes a copy of every uploaded image in each size that is on. Fewer sizes means fewer files; Bricks picks from the sizes that exist. Changes apply to new uploads; images already uploaded keep their files. Thumbnail stays on because the Media Library uses it.</p>
    <input type="hidden" name="settings[sizes_made][]" value="">
    <input type="hidden" name="settings[pickers_shown][]" value="">
    <table class="widefat striped lfp-size-table">
        <thead><tr><th scope="col">Size</th><th scope="col">Dimensions</th><th scope="col">Made</th><th scope="col">In the pickers</th></tr></thead>
        <tbody>
        <tr>
            <td>Full (the original)</td><td>As uploaded</td><td>—</td>
            <td><input type="checkbox" class="lfp-toggle" name="settings[pickers_shown][]" value="full" <?php checked(!in_array('full', $values['picker_hidden'], true)); ?> aria-label="Show Full in the pickers"></td>
        </tr>
        <?php foreach ($sizes as $name => $size) :
            $name = (string) $name;
            $dims = ((int) $size['width'] ?: 'any') . ' × ' . ((int) $size['height'] ?: 'any') . (!empty($size['crop']) ? ', cropped' : '');
            ?>
            <tr>
                <td><?php echo esc_html(size_label($name)); ?> <code><?php echo esc_html($name); ?></code></td>
                <td><?php echo esc_html($dims); ?></td>
                <td><input type="checkbox" class="lfp-toggle" name="settings[sizes_made][]" value="<?php echo esc_attr($name); ?>" <?php checked($name === 'thumbnail' || !in_array($name, $values['sizes_off'], true)); ?> <?php disabled($name === 'thumbnail'); ?> aria-label="<?php echo esc_attr('Make ' . size_label($name)); ?>"><?php if ($name === 'thumbnail') : ?><input type="hidden" name="settings[sizes_made][]" value="thumbnail"><?php endif; ?></td>
                <td><input type="checkbox" class="lfp-toggle" name="settings[pickers_shown][]" value="<?php echo esc_attr($name); ?>" <?php checked(!in_array($name, $values['picker_hidden'], true)); ?> aria-label="<?php echo esc_attr('Show ' . size_label($name) . ' in the pickers'); ?>"></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/** Media items whose address is still a word (not yet freed). */
function unfreed_media_count(): int
{
    global $wpdb;
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_name NOT REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'");
}

/** Give up to 500 existing media items a random address, so page addresses are free. */
function free_media_slugs(): void
{
    global $wpdb;
    authorise('leapfrog_performance_free_slugs');
    $ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_name NOT REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$' LIMIT 500");
    foreach ($ids as $id) {
        $wpdb->update($wpdb->posts, ['post_name' => wp_generate_uuid4()], ['ID' => (int) $id]);
        clean_post_cache((int) $id);
    }
    return_to_page('slugs_freed', ['tab' => 'media']);
}

function render_media_tab(): void
{
    echo '<p class="lfp-narrow-text">Media settings for the whole site. Compression, WebP and AVIF conversion and resizing large uploads are ShortPixel’s job.</p>';
    render_fields_form('media', [
        'Media pages' => ['media_pages'],
        'Uploads' => ['social_size', 'big_image_off', 'avif'],
        'On the site' => ['hide_captions'],
    ], __NAMESPACE__ . '\\render_size_table');
    if (!settings('media')['media_pages']) { return; }
    $left = unfreed_media_count();
    ?>
    <h2>Free up media addresses</h2>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="lfp-narrow-text">
        <input type="hidden" name="action" value="leapfrog_performance_free_slugs">
        <?php wp_nonce_field('leapfrog_performance_free_slugs', '_lfp_nonce'); ?>
        <?php if ($left) : ?>
            <p><?php echo esc_html($left === 1 ? '1 media item uploaded before now still holds a page address' : $left . ' media items uploaded before now still hold page addresses'); ?> (for example, an image called “about” stops a page using /about/). Freeing gives each a random address; the files and where they are used don’t change. Up to 500 at a time.</p>
            <?php submit_button('Free Up Addresses', 'secondary', 'submit', false); ?>
        <?php else : ?>
            <p class="description">Every media item has a random address, so none hold page addresses.</p>
        <?php endif; ?>
    </form>
    <?php
}
