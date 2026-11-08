<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/**
 * The Scheduled tasks feature. WordPress runs its timed jobs (scheduled posts,
 * update checks, FluentCRM and LatePoint emails, backups) by starting them
 * during visitors' page loads. A timer on the host is steadier and keeps that
 * work off page loads.
 *
 * Each run of wp-cron.php is recorded as coming from the host (a command-line
 * run, or a request that isn't WordPress calling itself) or from a page load.
 * With "host timer only" switched on, page loads stop starting tasks, but only
 * while the host timer has been seen in the last two hours; otherwise page
 * loads take over again, so tasks never stop for good.
 */

const OPTION_CRON_SEEN = 'leapfrog_performance_cron_seen';
const CRON_HOST_GRACE = 2 * HOUR_IN_SECONDS;

function cron_fields(array $fields): array
{
    $fields['cron'] = [
        'host_timer' => ['type' => 'bool', 'default' => false, 'label' => 'Run tasks from the host’s timer only', 'help' => 'Page loads stop starting scheduled tasks. Switch on once the host’s timer is set up (below). If the timer stops for two hours, page loads take over again until it is back.'],
    ];
    return $fields;
}

function cron_boot(): void
{
    add_filter('leapfrog_performance_fields', __NAMESPACE__ . '\\cron_fields');
    if (wp_doing_cron()) { record_cron_run(); }
    if (settings('cron')['host_timer'] && !defined('DISABLE_WP_CRON') && host_timer_working()) {
        define('DISABLE_WP_CRON', true);
        define('LEAPFROG_PERFORMANCE_DISABLED_WP_CRON', true);
    }
}

/** When tasks last ran: ['host' => time, 'page' => time]. */
function cron_seen(): array
{
    $seen = get_option(OPTION_CRON_SEEN, []);
    return is_array($seen) ? $seen + ['host' => 0, 'page' => 0] : ['host' => 0, 'page' => 0];
}

function host_timer_working(): bool
{
    return cron_seen()['host'] > time() - CRON_HOST_GRACE;
}

/** Called while wp-cron.php runs. Written at most every two minutes. */
function record_cron_run(): void
{
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    // WordPress starts tasks by calling itself, with its own name as the browser.
    $source = (PHP_SAPI === 'cli' || strpos($agent, 'WordPress/') !== 0) ? 'host' : 'page';
    $seen = cron_seen();
    if ($seen[$source] > time() - 120) { return; }
    $seen[$source] = time();
    update_option(OPTION_CRON_SEEN, $seen, true);
}

/** Scheduled tasks that should have run more than ten minutes ago. */
function late_tasks(): int
{
    $late = 0;
    foreach ((array) _get_cron_array() as $time => $hooks) {
        if ((int) $time < time() - 10 * MINUTE_IN_SECONDS) { $late += count((array) $hooks); }
    }
    return $late;
}

function time_ago(int $time): string
{
    return $time ? sprintf('%s ago', human_time_diff($time)) : 'not yet seen';
}

function render_cron_tab(): void
{
    $seen = cron_seen();
    $host = host_timer_working();
    $config = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !defined('LEAPFROG_PERFORMANCE_DISABLED_WP_CRON');
    $on = settings('cron')['host_timer'];
    $late = late_tasks();
    $url = site_url('wp-cron.php?doing_wp_cron');

    if ($config && !$host && $late) {
        $status = ['error', 'wp-config.php stops page loads starting tasks, but the host’s timer hasn’t run in the last two hours, so scheduled tasks aren’t running. Set up the timer below.'];
    } elseif ($config && !$host) {
        $status = ['info', 'wp-config.php stops page loads starting tasks. Tasks are on time, so something this page can’t see runs them.'];
    } elseif ($on && !$host) {
        $status = ['warning', $seen['host'] ? 'The host’s timer stopped (last seen ' . time_ago($seen['host']) . '), so page loads are starting tasks again for now. Check the timer below.' : 'Waiting for the host’s timer: until it has run once, page loads keep starting tasks. Set it up below.'];
    } elseif ($host && ($on || $config)) {
        $status = ['success', 'Tasks run from the host’s timer (last run ' . time_ago($seen['host']) . '). Page loads don’t start them.'];
    } elseif ($host) {
        $status = ['info', 'The host’s timer is running (last run ' . time_ago($seen['host']) . '). Switch on “Run tasks from the host’s timer only” so page loads stop starting tasks too.'];
    } else {
        $status = ['info', 'Page loads start scheduled tasks. That works, but a timer on the host is steadier and keeps the work off visitors’ page loads.'];
    }
    ?>
    <p class="lfp-narrow-text">WordPress’s scheduled tasks (scheduled posts, update checks, FluentCRM and LatePoint emails, backups) normally start during visitors’ page loads. A timer on the host runs them every few minutes instead.</p>
    <div class="notice notice-<?php echo esc_attr($status[0]); ?> inline lfa-notice-keep lfp-narrow-text"><p><?php echo esc_html($status[1]); ?></p></div>
    <table class="widefat striped lfp-cron-table">
        <tbody>
            <tr><th scope="row">Last run by the host’s timer</th><td><?php echo esc_html(time_ago($seen['host'])); ?></td></tr>
            <tr><th scope="row">Last run started by a page load</th><td><?php echo esc_html(time_ago($seen['page'])); ?></td></tr>
            <tr><th scope="row">Tasks running late</th><td><?php echo $late ? '<span class="lfp-status-problem">' . esc_html($late === 1 ? '1 task is' : $late . ' tasks are') . ' more than 10 minutes late</span>' : 'None'; ?></td></tr>
            <tr><th scope="row">wp-config.php</th><td><?php echo $config ? 'Stops page loads starting tasks (DISABLE_WP_CRON)' : 'No setting'; ?></td></tr>
        </tbody>
    </table>
    <?php if ($config) : ?>
        <p class="description lfp-narrow-text">wp-config.php already stops page loads starting tasks, so the switch below has no effect.</p>
    <?php endif; ?>
    <?php render_fields_form('cron', ['Timer' => ['host_timer']]); ?>
    <h2>Setting up the host’s timer</h2>
    <div class="lfp-narrow-text">
        <p>On 20i: <strong>My20i &gt; Manage Hosting &gt; Scheduled Tasks</strong>, add a task that runs <strong>every 5 minutes</strong> with this command:</p>
        <p><input type="text" class="large-text code" readonly value="<?php echo esc_attr('wget -q -O /dev/null "' . $url . '"'); ?>" onclick="this.select()" aria-label="Command for the host’s timer"></p>
        <p class="description">Other hosts work the same way: their cron or scheduled tasks screen, the same command. Once it has run, this page shows the time and you can switch on the setting above.</p>
    </div>
    <?php
}
