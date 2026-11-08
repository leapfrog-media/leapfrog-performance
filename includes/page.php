<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/** Leapfrog > Leapfrog Performance: one screen with a tab per switched-on feature, plus Features. */
function admin_menu(): void
{
    add_submenu_page(MENU_SLUG, 'Leapfrog Performance', 'Leapfrog Performance', 'manage_options', PAGE, __NAMESPACE__ . '\\render_page');
}
add_action('admin_menu', __NAMESPACE__ . '\\admin_menu');

function authorise(string $action): void
{
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to change these settings.', '', ['response' => 403]);
    }
    check_admin_referer($action, '_lfp_nonce');
}

/** Back to the screen with a message: $notice is a key of notices() or errors(). */
function return_to_page(string $notice, array $args = []): void
{
    $key = isset(errors()[$notice]) ? 'lfp_error' : 'lfp_notice';
    wp_safe_redirect(page_url($args + [$key => $notice]));
    exit;
}

function notices(): array
{
    return [
        'saved' => 'Settings saved.',
        'features_saved' => 'Features saved.',
        'imported' => 'Settings imported. Check each tab, then switch off the same settings in the plugin they came from.',
        'slugs_freed' => 'Media addresses freed.',
    ];
}

function errors(): array
{
    return [
        'import_none' => 'Nothing was chosen to import.',
    ];
}

/** Tabs for the switched-on features, in order, then Features. */
function tabs(): array
{
    $tabs = [];
    foreach (features() as $id => $feature) {
        if (feature_enabled($id)) {
            $tabs[$id] = ['label' => $feature['label'], 'render' => $feature['render']];
        }
    }
    $tabs['features'] = ['label' => 'Features', 'render' => __NAMESPACE__ . '\\render_features_tab'];
    return $tabs;
}

function current_tab(): string
{
    $tabs = tabs();
    $tab = isset($_GET['tab']) && is_string($_GET['tab']) ? sanitize_key($_GET['tab']) : '';
    return isset($tabs[$tab]) ? $tab : (string) array_key_first($tabs);
}

function page_assets(string $hook): void
{
    if ($hook !== PAGE_HOOK) {
        return;
    }
    wp_enqueue_style('lfp-page', asset_url('assets/page.css'), [], asset_version('assets/page.css'));
    wp_enqueue_script('lfp-page', asset_url('assets/page.js'), [], asset_version('assets/page.js'), true);
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\page_assets');

function render_page(): void
{
    if (!current_user_can('manage_options')) { return; }
    $tabs = tabs();
    $current = current_tab();
    $notice = isset($_GET['lfp_notice']) && is_string($_GET['lfp_notice']) ? sanitize_key($_GET['lfp_notice']) : '';
    $error = isset($_GET['lfp_error']) && is_string($_GET['lfp_error']) ? sanitize_key($_GET['lfp_error']) : '';
    ?>
    <div class="wrap lfp-settings">
        <h1>Leapfrog Performance <span class="lfp-version"><?php echo esc_html(VERSION); ?></span></h1>
        <nav class="nav-tab-wrapper lfp-tabs" aria-label="Leapfrog Performance sections">
            <?php foreach ($tabs as $id => $tab) : ?>
                <a href="<?php echo esc_url(page_url(['tab' => $id])); ?>" class="nav-tab<?php echo $id === $current ? ' nav-tab-active' : ''; ?>" <?php if ($id === $current) { echo 'aria-current="page"'; } ?>><?php echo esc_html($tab['label']); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php
        if (isset(notices()[$notice])) {
            echo '<div class="notice notice-success is-dismissible lfa-notice-keep"><p>' . esc_html(notices()[$notice]) . '</p></div>';
        }
        if (isset(errors()[$error])) {
            echo '<div class="notice notice-error is-dismissible lfa-notice-keep"><p>' . esc_html(errors()[$error]) . '</p></div>';
        }
        ($tabs[$current]['render'])();
        ?>
    </div>
    <?php
}

/**
 * A settings form for one feature, drawn from its field definitions:
 * sections of fields, each a switch, number or choice with a description.
 */
function render_fields_form(string $feature, array $sections, ?callable $extra = null): void
{
    $fields = field_definitions()[$feature] ?? [];
    $values = settings($feature);
    ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="lfa-track lfp-fields-form">
        <input type="hidden" name="action" value="leapfrog_performance_save">
        <input type="hidden" name="feature" value="<?php echo esc_attr($feature); ?>">
        <?php wp_nonce_field('leapfrog_performance_save', '_lfp_nonce', false); ?>
        <?php foreach ($sections as $title => $keys) : ?>
            <h2><?php echo esc_html($title); ?></h2>
            <table class="form-table lfp-form-table" role="presentation">
                <?php foreach ($keys as $key) :
                    if (!isset($fields[$key])) { continue; }
                    $field = $fields[$key];
                    $id = 'lfp-' . $feature . '-' . $key;
                    $name = 'settings[' . $key . ']';
                    ?>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($field['label']); ?></label></th>
                        <td>
                            <?php if ($field['type'] === 'bool') : ?>
                                <input type="hidden" name="<?php echo esc_attr($name); ?>" value="0">
                                <input type="checkbox" class="lfp-toggle" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="1" <?php checked($values[$key]); ?>>
                            <?php elseif ($field['type'] === 'number') : ?>
                                <input type="number" class="small-text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $values[$key]); ?>" min="<?php echo (int) $field['min']; ?>" max="<?php echo (int) $field['max']; ?>" step="1" <?php if (!empty($field['allow_empty'])) { echo 'placeholder="' . esc_attr($field['placeholder'] ?? '') . '"'; } ?>> <?php echo esc_html($field['unit'] ?? ''); ?>
                            <?php elseif ($field['type'] === 'choice') : ?>
                                <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>">
                                    <?php foreach ($field['choices'] as $value => $label) : ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($values[$key], $value); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($field['type'] === 'list') : ?>
                                <input type="hidden" name="<?php echo esc_attr($name); ?>[]" value="">
                                <fieldset id="<?php echo esc_attr($id); ?>">
                                    <?php foreach ($field['choices'] as $value => $label) : ?>
                                        <label class="lfp-list-choice"><input type="checkbox" name="<?php echo esc_attr($name); ?>[]" value="<?php echo esc_attr($value); ?>" <?php checked(in_array($value, (array) $values[$key], true)); ?>> <?php echo esc_html($label); ?></label>
                                    <?php endforeach; ?>
                                </fieldset>
                            <?php endif; ?>
                            <?php if (!empty($field['help'])) : ?><p class="description"><?php echo esc_html($field['help']); ?></p><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endforeach; ?>
        <?php if ($extra) { $extra($values); } ?>
        <div class="lfp-save-bar"><?php submit_button('Save Changes', 'primary', 'submit', false); ?> <span class="lfa-unsaved" hidden>Unsaved changes</span></div>
    </form>
    <?php
}

function save_fields_form(): void
{
    authorise('leapfrog_performance_save');
    $feature = isset($_POST['feature']) && is_string($_POST['feature']) ? sanitize_key($_POST['feature']) : '';
    if (!isset(field_definitions()[$feature])) {
        wp_die('Unknown settings.', '', ['response' => 400]);
    }
    $posted = isset($_POST['settings']) && is_array($_POST['settings']) ? wp_unslash($_POST['settings']) : [];
    // A feature can turn its own form fields into settings (Media's size table).
    $posted = (array) apply_filters('leapfrog_performance_posted', $posted, $feature);
    save_settings($feature, $posted);
    do_action('leapfrog_performance_saved', $feature);
    return_to_page('saved', ['tab' => $feature]);
}
add_action('admin_post_leapfrog_performance_save', __NAMESPACE__ . '\\save_fields_form');
