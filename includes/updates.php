<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/** Updates tab: the GitHub token shared by all Leapfrog plugins, and where each plugin stands. */

(new GitHubUpdater(FILE, 'leapfrog-performance'))->register();

function save_updates(): void
{
    authorise('leapfrog_performance_updates');
    $do = isset($_POST['lfp_do']) && is_string($_POST['lfp_do']) ? sanitize_key($_POST['lfp_do']) : '';
    if ($do === 'remove_token') {
        delete_option(GitHubUpdater::OPTION_TOKEN);
        GitHubUpdater::check_all();
        return_to_page('token_removed', ['tab' => 'updates']);
    }
    if ($do === 'save_token') {
        $token = isset($_POST['github_token']) && is_string($_POST['github_token']) ? trim(wp_unslash($_POST['github_token'])) : '';
        if (!GitHubUpdater::valid_token($token)) { return_to_page('token_invalid', ['tab' => 'updates']); }
        update_option(GitHubUpdater::OPTION_TOKEN, $token, false);
        GitHubUpdater::check_all();
        return_to_page('token_saved', ['tab' => 'updates']);
    }
    GitHubUpdater::check_all();
    return_to_page('updates_checked', ['tab' => 'updates']);
}
add_action('admin_post_leapfrog_performance_updates', __NAMESPACE__ . '\\save_updates');

function render_updates_tab(): void
{
    $updaters = GitHubUpdater::all();
    ksort($updaters);
    $pending = get_site_transient('update_plugins');
    $savedToken = get_option(GitHubUpdater::OPTION_TOKEN, '') !== '';
    ?>
    <p class="lfp-narrow-text">Leapfrog plugins update from their GitHub repositories, through Dashboard &gt; Updates and the Plugins screen like any other plugin (automatic updates can be switched on there too). GitHub is asked every few hours.</p>
    <table class="widefat striped lfp-updates-table">
        <thead><tr><th scope="col">Plugin</th><th scope="col">Installed</th><th scope="col">Newest on GitHub</th><th scope="col">Status</th></tr></thead>
        <tbody>
        <?php foreach ($updaters as $updater) :
            $latest = $updater->latest();
            $waiting = is_object($pending) && isset($pending->response[$updater->basename()]);
            ?>
            <tr>
                <td><strong><?php echo esc_html($updater->name()); ?></strong><br><span class="description"><?php echo esc_html(GitHubUpdater::OWNER . '/' . $updater->repo()); ?></span></td>
                <td><?php echo esc_html($updater->installed()); ?></td>
                <td><?php echo $latest['version'] !== '' ? esc_html($latest['version']) : '—'; ?></td>
                <td>
                    <?php if ($updater->is_dev_copy()) : ?>
                        This copy is a Git working folder, so WordPress won’t update it (that would overwrite the work in it).
                    <?php elseif ($latest['error'] !== '') : ?>
                        <span class="lfp-status-problem"><?php echo esc_html($latest['error']); ?></span>
                    <?php elseif (version_compare($latest['version'], $updater->installed(), '>')) : ?>
                        <strong>Update available.</strong>
                        <?php if ($waiting && current_user_can('update_plugins')) : ?><a href="<?php echo esc_url(admin_url('update-core.php')); ?>">Update now</a><?php endif; ?>
                    <?php else : ?>
                        Up to date.
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="leapfrog_performance_updates">
        <input type="hidden" name="lfp_do" value="check">
        <?php wp_nonce_field('leapfrog_performance_updates', '_lfp_nonce'); ?>
        <p>
            <?php submit_button('Check Now', 'secondary', 'submit', false); ?>
            <span class="description">Last asked <?php echo esc_html(human_time_diff((int) (reset($updaters) ? reset($updaters)->latest()['checked'] : time()))); ?> ago.</span>
        </p>
    </form>

    <h2>GitHub token</h2>
    <?php if (GitHubUpdater::token_in_config()) : ?>
        <p>The token is set in wp-config.php (<code>LEAPFROG_GITHUB_TOKEN</code>). To change it, edit wp-config.php.</p>
    <?php else : ?>
        <p class="lfp-narrow-text">Private repositories need a GitHub token that can read them (public ones don’t). One token covers all Leapfrog plugins. Use a fine-grained token limited to the Leapfrog repositories with read-only “Contents” access, so it can’t change anything on GitHub.</p>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
            <input type="hidden" name="action" value="leapfrog_performance_updates">
            <input type="hidden" name="lfp_do" value="save_token">
            <?php wp_nonce_field('leapfrog_performance_updates', '_lfp_nonce'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="lfp-github-token">Token</label></th>
                    <td>
                        <input type="password" id="lfp-github-token" name="github_token" class="regular-text code" autocomplete="off" spellcheck="false" required placeholder="<?php echo $savedToken ? esc_attr('Saved. Paste a new one to replace it') : 'github_pat_…'; ?>">
                        <p class="description">Once saved, the token isn’t shown again.</p>
                    </td>
                </tr>
            </table>
            <p>
                <?php submit_button('Save Token', 'primary', 'submit', false); ?>
                <?php if ($savedToken) : ?>
                    <button type="submit" class="button-link button-link-delete lfp-remove-token" name="lfp_do" value="remove_token" formnovalidate data-lfa-confirm="Remove the GitHub token? Leapfrog plugins in private repositories will stop finding updates on this site.">Remove token</button>
                <?php endif; ?>
            </p>
        </form>
        <p class="description lfp-narrow-text">Better still, put the token in wp-config.php, so it isn’t kept in the database or its backups: <code>define( 'LEAPFROG_GITHUB_TOKEN', 'github_pat_…' );</code></p>
    <?php endif;
}
