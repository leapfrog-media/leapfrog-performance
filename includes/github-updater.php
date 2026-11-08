<?php
namespace Leapfrog\Performance;

if (!defined('ABSPATH')) { exit; }

/**
 * Updates from GitHub, shared by every Leapfrog plugin. Each plugin carries a
 * copy of this class that is identical apart from the namespace line:
 * Leapfrog Admin and Leapfrog Performance in includes/github-updater.php,
 * Leapfrog Framework in src/Admin/GitHubUpdater.php. Keep the copies in step.
 *
 * How it works:
 * - The plugin header's "Update URI: https://github.com/leapfrog-media/<repo>"
 *   tells WordPress not to ask WordPress.org; it asks the
 *   update_plugins_github.com filter instead (offer_update below).
 * - The Version in the plugin file on the main branch is the newest version, so
 *   pushing a commit that raises it is the release (no tags). Only finished
 *   versions go to main.
 * - WordPress's own updater installs GitHub's zip of that exact commit; the
 *   zip's folder is renamed to the plugin's folder on the way in.
 * - A private repo needs a GitHub token that can read it (a public one works
 *   without, within GitHub's hourly limit per server). One token serves every
 *   Leapfrog plugin: the LEAPFROG_GITHUB_TOKEN constant in wp-config.php, or
 *   the leapfrog_github_token option. It is only ever sent to api.github.com,
 *   for this repo.
 * - A copy that is a Git working folder (has a .git folder) is never updated,
 *   so a development checkout can't be overwritten.
 */
final class GitHubUpdater
{
    public const OWNER = 'leapfrog-media';
    public const OPTION_TOKEN = 'leapfrog_github_token';
    public const BRANCH = 'main';
    private const CACHE_HOURS = 6;

    /** @var string */
    private $file;
    /** @var string */
    private $repo;

    public function __construct(string $file, string $repo)
    {
        $this->file = $file;
        $this->repo = $repo;
    }

    public function register(): void
    {
        // Every Leapfrog plugin's updater, so one settings screen can list them all.
        $GLOBALS['leapfrog_github_updaters'][$this->repo] = $this;
        add_filter('update_plugins_github.com', [$this, 'offer_update'], 10, 3);
        add_filter('http_request_args', [$this, 'add_token'], 10, 2);
        add_filter('upgrader_source_selection', [$this, 'fix_folder'], 10, 4);
        add_filter('plugins_api', [$this, 'details'], 20, 3);
    }

    /** @return object[] Every registered Leapfrog updater, by repo. */
    public static function all(): array
    {
        return isset($GLOBALS['leapfrog_github_updaters']) && is_array($GLOBALS['leapfrog_github_updaters']) ? $GLOBALS['leapfrog_github_updaters'] : [];
    }

    public static function token(): string
    {
        if (defined('LEAPFROG_GITHUB_TOKEN') && is_string(LEAPFROG_GITHUB_TOKEN) && LEAPFROG_GITHUB_TOKEN !== '') { return LEAPFROG_GITHUB_TOKEN; }
        return (string) get_option(self::OPTION_TOKEN, '');
    }

    public static function token_in_config(): bool
    {
        return defined('LEAPFROG_GITHUB_TOKEN') && is_string(LEAPFROG_GITHUB_TOKEN) && LEAPFROG_GITHUB_TOKEN !== '';
    }

    /** GitHub tokens are letters, numbers and underscores. */
    public static function valid_token(string $token): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]{20,255}$/D', $token);
    }

    /** Forget every cached answer from GitHub and ask again (all Leapfrog plugins). */
    public static function check_all(): void
    {
        foreach (self::all() as $updater) { $updater->latest(true); }
        delete_site_transient('update_plugins');
        wp_update_plugins();
    }

    public function repo(): string
    {
        return $this->repo;
    }

    public function basename(): string
    {
        return plugin_basename($this->file);
    }

    public function name(): string
    {
        return (string) (get_file_data($this->file, ['Name' => 'Plugin Name'])['Name'] ?: $this->repo);
    }

    public function installed(): string
    {
        return (string) get_file_data($this->file, ['Version' => 'Version'])['Version'];
    }

    public function is_dev_copy(): bool
    {
        return file_exists(dirname($this->file) . '/.git');
    }

    public function repo_url(): string
    {
        return 'https://github.com/' . self::OWNER . '/' . $this->repo;
    }

    private function api_url(string $path = ''): string
    {
        return 'https://api.github.com/repos/' . self::OWNER . '/' . $this->repo . '/' . ltrim($path, '/');
    }

    private static function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . self::token(),
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
    }

    /**
     * The newest version on GitHub (the main branch), cached for a few hours.
     * `ref` is the commit it came from, so the download is exactly that version.
     *
     * @return array{version:string,ref:string,package:string,error:string,checked:int}
     */
    public function latest(bool $refresh = false): array
    {
        $key = 'leapfrog_github_' . $this->repo;
        $cached = get_site_transient($key);
        if (!$refresh && is_array($cached) && isset($cached['version'], $cached['ref'], $cached['package'], $cached['error'], $cached['checked'])) { return $cached; }

        $result = ['version' => '', 'ref' => '', 'package' => '', 'error' => '', 'checked' => time()];
        // Public repos work without a token; private ones need one (add_token adds it).
        $response = wp_remote_get($this->api_url('commits/' . self::BRANCH), ['timeout' => 10, 'headers' => ['Accept' => 'application/vnd.github+json']]);
        $result['error'] = $this->problem($response);
        if ($result['error'] === '') {
            $commit = json_decode((string) wp_remote_retrieve_body($response), true);
            $sha = is_array($commit) && isset($commit['sha']) && is_string($commit['sha']) && preg_match('/^[0-9a-f]{40}$/D', $commit['sha']) ? $commit['sha'] : '';
            if ($sha === '') {
                $result['error'] = 'GitHub’s answer couldn’t be read.';
            } else {
                $response = wp_remote_get($this->api_url('contents/' . rawurlencode(basename($this->file)) . '?ref=' . $sha), ['timeout' => 10, 'headers' => ['Accept' => 'application/vnd.github.raw+json']]);
                $result['error'] = $this->problem($response);
                // The same "Version:" line WordPress reads from the plugin header.
                if ($result['error'] === '' && preg_match('/^[ \t\/*#@]*Version:[ \t]*(\d+\.\d+\.\d+)[ \t]*$/mi', substr((string) wp_remote_retrieve_body($response), 0, 8192), $m)) {
                    $result['version'] = $m[1];
                    $result['ref'] = $sha;
                    $result['package'] = $this->api_url('zipball/' . $sha);
                } elseif ($result['error'] === '') {
                    $result['error'] = 'The plugin file on GitHub has no version number (like 1.2.3).';
                }
            }
        }
        // Problems are asked about again sooner than answers.
        set_site_transient($key, $result, $result['error'] !== '' ? HOUR_IN_SECONDS : self::CACHE_HOURS * HOUR_IN_SECONDS);
        return $result;
    }

    /** What went wrong with a request to GitHub, in plain words; '' when it worked. */
    private function problem($response): string
    {
        if (is_wp_error($response)) { return 'GitHub couldn’t be reached (' . $response->get_error_message() . ').'; }
        $code = (int) wp_remote_retrieve_response_code($response);
        $hasToken = self::token() !== '';
        if ($code === 200) { return ''; }
        if ($code === 401) { return 'GitHub refused the token: it may be mistyped, expired or withdrawn.'; }
        if (($code === 403 || $code === 429) && wp_remote_retrieve_header($response, 'x-ratelimit-remaining') === '0') {
            return $hasToken
                ? 'GitHub’s limit on checks was reached. It will be asked again within the hour.'
                : 'GitHub’s limit on checks without a token was reached (servers shared with other sites use it up faster). It will be asked again within the hour; a token avoids this.';
        }
        if ($code === 403 || $code === 404 || $code === 422) {
            return $hasToken
                ? 'The token can’t read ' . self::OWNER . '/' . $this->repo . ' (or it has no ' . self::BRANCH . ' branch). Give the token access to this repository.'
                : 'GitHub can’t find ' . self::OWNER . '/' . $this->repo . ' (or its ' . self::BRANCH . ' branch). If the repository is private, add a GitHub token.';
        }
        return 'GitHub answered with an error (' . $code . ').';
    }

    /** WordPress asks here, because of the plugin's Update URI. */
    public function offer_update($update, $pluginData, $pluginFile)
    {
        if ($pluginFile !== $this->basename() || $this->is_dev_copy()) { return $update; }
        $latest = $this->latest();
        if ($latest['version'] === '') { return $update; }
        $icon = plugins_url('assets/brand/leapfrog-mark.svg', $this->file);
        return [
            'slug' => dirname($this->basename()),
            'version' => $latest['version'],
            'package' => $latest['package'],
            'url' => $this->repo_url(),
            'tested' => get_bloginfo('version'),
            'icons' => ['svg' => $icon, '1x' => $icon],
        ];
    }

    /** The token goes with requests to this repo on GitHub's API, and nowhere else. */
    public function add_token($args, $url)
    {
        if (is_string($url) && strpos($url, $this->api_url()) === 0 && self::token() !== '') {
            // A request's own Accept header (e.g. for raw files) wins.
            $args['headers'] = array_merge(self::headers(), isset($args['headers']) && is_array($args['headers']) ? $args['headers'] : []);
        }
        return $args;
    }

    /** GitHub's zip holds a folder like "leapfrog-media-leapfrog-admin-1a2b3c4"; WordPress needs the plugin's own folder name. */
    public function fix_folder($source, $remoteSource, $upgrader, $extra = [])
    {
        global $wp_filesystem;
        if (!is_array($extra) || ($extra['plugin'] ?? '') !== $this->basename() || !is_string($source) || !$wp_filesystem) { return $source; }
        if (!$wp_filesystem->exists(trailingslashit($source) . basename($this->file))) {
            return new \WP_Error('leapfrog_github_package', 'The download from GitHub doesn’t contain ' . $this->name() . '.');
        }
        $wanted = trailingslashit($remoteSource) . dirname($this->basename());
        if (untrailingslashit($source) === $wanted) { return $source; }
        if (!$wp_filesystem->move(untrailingslashit($source), $wanted, true)) {
            return new \WP_Error('leapfrog_github_folder', 'The download from GitHub couldn’t be unpacked.');
        }
        return trailingslashit($wanted);
    }

    /** "View details" on the Plugins and Updates screens. */
    public function details($result, $action, $args)
    {
        if ($action !== 'plugin_information' || !is_object($args) || ($args->slug ?? '') !== dirname($this->basename())) { return $result; }
        $data = get_file_data($this->file, ['Name' => 'Plugin Name', 'Description' => 'Description', 'Author' => 'Author', 'RequiresWP' => 'Requires at least', 'RequiresPHP' => 'Requires PHP']);
        $latest = $this->latest();
        // The changelog of the newest version, read from GitHub; the installed one if GitHub can't be read.
        $markdown = '';
        if ($latest['ref'] !== '') {
            $response = wp_remote_get($this->api_url('contents/CHANGELOG.md?ref=' . $latest['ref']), ['timeout' => 10, 'headers' => ['Accept' => 'application/vnd.github.raw+json']]);
            if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) { $markdown = (string) wp_remote_retrieve_body($response); }
        }
        if ($markdown === '' && file_exists(dirname($this->file) . '/CHANGELOG.md')) { $markdown = (string) file_get_contents(dirname($this->file) . '/CHANGELOG.md'); }
        $changelog = self::markdown_to_html($markdown);
        return (object) [
            'name' => $data['Name'],
            'slug' => dirname($this->basename()),
            'version' => $latest['version'] !== '' ? $latest['version'] : $this->installed(),
            'author' => esc_html($data['Author']),
            'homepage' => $this->repo_url(),
            'requires' => $data['RequiresWP'],
            'requires_php' => $data['RequiresPHP'],
            'last_updated' => '',
            'sections' => [
                'description' => '<p>' . esc_html($data['Description']) . '</p><p>Updates come from the private GitHub repository ' . esc_html(self::OWNER . '/' . $this->repo) . '.</p>',
                'changelog' => $changelog !== '' ? $changelog : '<p>See CHANGELOG.md in the GitHub repository.</p>',
            ],
        ];
    }

    /** Just enough Markdown for a changelog: headings, bullets, bold and code. */
    private static function markdown_to_html(string $markdown): string
    {
        $html = '';
        $inList = false;
        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            $text = esc_html(trim($line));
            $text = preg_replace(['/\*\*(.+?)\*\*/', '/`(.+?)`/'], ['<strong>$1</strong>', '<code>$1</code>'], $text);
            $isItem = (bool) preg_match('/^[-*] /', trim($line));
            if ($inList && !$isItem) { $html .= '</ul>'; $inList = false; }
            if (preg_match('/^# /', trim($line))) {
                continue; // The title ("# Changelog") is already on screen.
            } elseif (preg_match('/^#{2,} /', trim($line))) {
                $html .= '<h4>' . preg_replace('/^#+\s*/', '', $text) . '</h4>';
            } elseif ($isItem) {
                if (!$inList) { $html .= '<ul>'; $inList = true; }
                $html .= '<li>' . substr($text, 2) . '</li>';
            } elseif ($text !== '') {
                $html .= '<p>' . $text . '</p>';
            }
        }
        return $inList ? $html . '</ul>' : $html;
    }
}
