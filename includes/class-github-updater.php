<?php
/**
 * GitHub Updater for Clubworx Plugin
 * Handles automatic updates from GitHub releases
 */

if (!defined('ABSPATH')) {
    exit;
}

class Clubworx_GitHub_Updater {
    
    private static $instance = null;
    
    // GitHub repository info - Uses constants from main plugin file
    private $github_username;
    private $github_repo;
    private $github_token;
    private $github_api_url = 'https://api.github.com/repos';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Get GitHub info from constants (defined in main plugin file)
        $this->github_username = defined('CLUBWORX_INTEGRATION_GITHUB_USERNAME') ? CLUBWORX_INTEGRATION_GITHUB_USERNAME : '';
        $this->github_repo = defined('CLUBWORX_INTEGRATION_GITHUB_REPO') ? CLUBWORX_INTEGRATION_GITHUB_REPO : '';
        $this->github_token = defined('CLUBWORX_INTEGRATION_GITHUB_TOKEN') ? CLUBWORX_INTEGRATION_GITHUB_TOKEN : '';
        
        // Allow override from settings (optional)
        $settings = get_option('clubworx_integration_settings', array());
        $gh = isset($settings['github']) && is_array($settings['github']) ? $settings['github'] : array();
        $gh_user = isset($gh['username']) ? $gh['username'] : (isset($settings['github_username']) ? $settings['github_username'] : '');
        $gh_repo = isset($gh['repo']) ? $gh['repo'] : (isset($settings['github_repo']) ? $settings['github_repo'] : '');
        if ($gh_user !== '') {
            $this->github_username = $gh_user;
        }
        if ($gh_repo !== '') {
            $this->github_repo = $gh_repo;
        }
        if (!empty($gh['token'])) {
            $this->github_token = $gh['token'];
        } elseif (!empty($settings['github_token'])) {
            // Backward compatibility with legacy flat settings key.
            $this->github_token = $settings['github_token'];
        }
        
        // Only hook into update system if GitHub is configured
        if (!empty($this->github_username) && !empty($this->github_repo)) {
            add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
            add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
            add_filter('upgrader_pre_download', array($this, 'upgrader_pre_download'), 10, 4);
            add_filter('upgrader_source_selection', array($this, 'upgrader_source_selection'), 10, 4);
            add_filter('upgrader_post_install', array($this, 'upgrader_post_install'), 10, 3);
            add_filter('http_request_args', array($this, 'add_github_auth_headers'), 10, 2);
        }
    }
    
    /**
     * Build common GitHub request headers.
     *
     * @return array<string,string>
     */
    private function get_github_headers() {
        $headers = array(
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress-Clubworx-Integration',
        );
        if (!empty($this->github_token)) {
            // GitHub still supports token auth for REST and codeload requests.
            $headers['Authorization'] = 'token ' . $this->github_token;
        }
        return $headers;
    }
    
    /**
     * Headers for a specific GitHub download URL.
     *
     * @param string $url
     * @return array<string,string>
     */
    private function get_download_headers($url) {
        $headers = $this->get_github_headers();
        if ($this->is_release_asset_url($url) || strpos($url, 'objects.githubusercontent.com') !== false) {
            $headers['Accept'] = 'application/octet-stream';
        }
        return $headers;
    }

    /**
     * @param string $url
     * @return bool
     */
    private function is_release_asset_url($url) {
        $pattern = '#github\.com/' . preg_quote($this->github_username . '/' . $this->github_repo, '#') . '/releases/download/#';
        return (bool) preg_match($pattern, $url);
    }

    /**
     * @param string $url
     * @return bool
     */
    private function is_github_package_url($url) {
        if (!is_string($url) || $url === '') {
            return false;
        }
        $repo_path = '/' . $this->github_username . '/' . $this->github_repo;
        return strpos($url, 'api.github.com/repos' . $repo_path) !== false
            || strpos($url, 'codeload.github.com' . $repo_path) !== false
            || strpos($url, 'github.com' . $repo_path . '/zipball') !== false
            || $this->is_release_asset_url($url)
            || strpos($url, 'objects.githubusercontent.com') !== false;
    }

    /**
     * Download GitHub packages directly so private-repo auth survives redirects.
     *
     * @param mixed $reply
     * @param string $package
     * @param WP_Upgrader $upgrader
     * @param array<string,mixed> $hook_extra
     * @return mixed
     */
    public function upgrader_pre_download($reply, $package, $upgrader, $hook_extra = null) {
        if (!empty($reply)) {
            return $reply;
        }
        if (!is_array($hook_extra) || !isset($hook_extra['plugin'])) {
            return $reply;
        }

        $plugin_slug = plugin_basename(CLUBWORX_INTEGRATION_PLUGIN_FILE);
        if ($hook_extra['plugin'] !== $plugin_slug) {
            return $reply;
        }
        if (!$this->is_github_package_url($package)) {
            return $reply;
        }

        $downloaded = $this->download_github_package($package);
        if (is_wp_error($downloaded)) {
            error_log('Clubworx GitHub Updater: Download failed - ' . $downloaded->get_error_message());
            return $downloaded;
        }

        error_log('Clubworx GitHub Updater: Downloaded package to ' . $downloaded);
        return $downloaded;
    }

    /**
     * @param string $url
     * @return string|WP_Error
     */
    private function download_github_package($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 300,
            'redirection' => 5,
            'headers' => $this->get_download_headers($url),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $snippet = is_string($body) ? substr($body, 0, 200) : '';
            return new WP_Error(
                'clubworx_github_download_failed',
                sprintf(
                    /* translators: 1: HTTP status code, 2: response snippet */
                    __('GitHub download failed (HTTP %1$s). %2$s', 'clubworx-integration'),
                    (string) $code,
                    $snippet
                )
            );
        }

        $body = wp_remote_retrieve_body($response);
        if ($body === '' || $body === false) {
            return new WP_Error(
                'clubworx_github_download_empty',
                __('GitHub download returned an empty package.', 'clubworx-integration')
            );
        }

        if (!function_exists('wp_tempnam')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $tmp = wp_tempnam($url);
        if (!$tmp) {
            return new WP_Error(
                'clubworx_github_download_temp',
                __('Could not create a temporary file for the GitHub download.', 'clubworx-integration')
            );
        }

        $written = file_put_contents($tmp, $body);
        if ($written === false || $written === 0) {
            return new WP_Error(
                'clubworx_github_download_write',
                __('Could not write the downloaded GitHub package to disk.', 'clubworx-integration')
            );
        }

        return $tmp;
    }

    /**
     * Add auth headers to GitHub API/package requests.
     *
     * @param array<string,mixed> $args
     * @param string $url
     * @return array<string,mixed>
     */
    public function add_github_auth_headers($args, $url) {
        if (empty($this->github_token) || !is_string($url) || $url === '') {
            return $args;
        }

        if (!$this->is_github_package_url($url)) {
            return $args;
        }

        if (!isset($args['headers']) || !is_array($args['headers'])) {
            $args['headers'] = array();
        }

        foreach ($this->get_download_headers($url) as $key => $value) {
            $args['headers'][$key] = $value;
        }

        return $args;
    }
    
    /**
     * Locate the directory inside an extracted package that contains clubworx-integration.php.
     *
     * @param string $source Extracted package path.
     * @return string|false
     */
    private function resolve_package_root($source, $depth = 0) {
        global $wp_filesystem;

        if (!$wp_filesystem || empty($source) || $depth > 5) {
            return false;
        }

        $source = trailingslashit(wp_normalize_path($source));
        if ($wp_filesystem->exists($source . 'clubworx-integration.php')) {
            return untrailingslashit($source);
        }

        $list = $wp_filesystem->dirlist($source);
        if (!is_array($list)) {
            return false;
        }

        foreach (array_keys($list) as $subdir) {
            if ($subdir === '.' || $subdir === '..') {
                continue;
            }
            $candidate = $source . $subdir;
            if ($wp_filesystem->exists(trailingslashit($candidate) . 'clubworx-integration.php')) {
                return untrailingslashit($candidate);
            }
            if (!empty($list[$subdir]['type']) && $list[$subdir]['type'] === 'd') {
                $nested = $this->resolve_package_root($candidate, $depth + 1);
                if ($nested !== false) {
                    return $nested;
                }
            }
        }

        return false;
    }

    /**
     * Rename extracted package folder to the installed plugin directory slug.
     *
     * @param string $package_root
     * @param string $plugin_dir
     * @return string|false
     */
    private function rename_package_to_plugin_dir($package_root, $plugin_dir) {
        global $wp_filesystem;

        if (!$wp_filesystem) {
            return false;
        }

        if (!function_exists('copy_dir')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $package_root = wp_normalize_path(untrailingslashit($package_root));
        $upgrade_base = wp_normalize_path(WP_CONTENT_DIR . '/upgrade');
        $plugins_base = wp_normalize_path(WP_PLUGIN_DIR);

        if (basename($package_root) === $plugin_dir) {
            return trailingslashit($package_root);
        }

        $parent = wp_normalize_path(dirname($package_root));
        $corrected = $parent . '/' . $plugin_dir;

        // Never delete or stage inside the live plugins directory.
        if (strpos($corrected, $plugins_base) === 0) {
            $parent = $upgrade_base;
            $corrected = $upgrade_base . '/' . $plugin_dir;
        }

        if (strpos($parent, $upgrade_base) !== 0) {
            $parent = $upgrade_base;
            $corrected = $upgrade_base . '/' . $plugin_dir;
        }

        if (!$wp_filesystem->is_dir($parent)) {
            $wp_filesystem->mkdir($parent, FS_CHMOD_DIR);
        }

        if ($wp_filesystem->exists($corrected) && strpos($corrected, $plugins_base) !== 0) {
            $wp_filesystem->delete($corrected, true);
        }

        if ($wp_filesystem->move($package_root, $corrected, true)) {
            return trailingslashit($corrected);
        }

        if (!$wp_filesystem->is_dir($corrected)) {
            $wp_filesystem->mkdir($corrected, FS_CHMOD_DIR);
        }

        $copied = copy_dir($package_root, $corrected);
        if (!is_wp_error($copied)) {
            $wp_filesystem->delete($package_root, true);
            return trailingslashit($corrected);
        }

        error_log('Clubworx GitHub Updater: copy_dir failed - ' . $copied->get_error_message());
        return false;
    }

    /**
     * Rename the GitHub folder to the installed plugin directory slug so WordPress
     * overwrites wp-content/plugins/{your-folder}/ instead of creating repo-hash/.
     */
    public function upgrader_source_selection($source, $remote_source, $upgrader, $hook_extra = null) {
        if (!isset($hook_extra['plugin'])) {
            return $source;
        }

        $plugin_slug = plugin_basename(CLUBWORX_INTEGRATION_PLUGIN_FILE);
        if ($hook_extra['plugin'] !== $plugin_slug) {
            return $source;
        }

        $plugin_dir = dirname($plugin_slug);
        $package_root = $this->resolve_package_root($source);
        if ($package_root === false) {
            error_log('Clubworx GitHub Updater: Could not find clubworx-integration.php in update package at ' . $source);
            return new WP_Error(
                'clubworx_invalid_package',
                __('The GitHub release zip does not contain clubworx-integration.php.', 'clubworx-integration')
            );
        }

        $renamed = $this->rename_package_to_plugin_dir($package_root, $plugin_dir);
        if ($renamed === false) {
            error_log('Clubworx GitHub Updater: Failed to rename package from ' . $package_root . ' to ' . $plugin_dir);
            return new WP_Error(
                'clubworx_package_rename_failed',
                __('The plugin update was downloaded but could not be prepared for installation.', 'clubworx-integration')
            );
        }

        error_log('Clubworx GitHub Updater: Prepared package at ' . $renamed);
        return $renamed;
    }

    /**
     * After install, ensure WordPress reports the correct destination folder.
     *
     * @param bool|WP_Error $response
     * @param array<string,mixed> $hook_extra
     * @param array<string,mixed> $result
     * @return bool|WP_Error|array<string,mixed>
     */
    public function upgrader_post_install($response, $hook_extra, $result) {
        if (is_wp_error($response) || !isset($hook_extra['plugin'])) {
            return $response;
        }

        $plugin_slug = plugin_basename(CLUBWORX_INTEGRATION_PLUGIN_FILE);
        if ($hook_extra['plugin'] !== $plugin_slug) {
            return $response;
        }

        $plugin_dir = dirname($plugin_slug);
        $target_dir = trailingslashit(WP_PLUGIN_DIR) . $plugin_dir;

        if (!empty($result['destination']) && is_string($result['destination'])) {
            $result['destination'] = untrailingslashit($target_dir);
            $result['destination_name'] = $plugin_dir;
        }

        // Bust plugin update + release caches so the new version shows immediately.
        $this->clear_cache();

        return $result;
    }
    
    /**
     * Check for updates from GitHub
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $plugin_slug = plugin_basename(CLUBWORX_INTEGRATION_PLUGIN_FILE);
        $current_version = CLUBWORX_INTEGRATION_VERSION;
        
        // Get latest release from GitHub
        $latest_release = $this->get_latest_release();
        
        if ($latest_release && version_compare($current_version, $latest_release['version'], '<')) {
            $plugin_data = array(
                'slug' => dirname($plugin_slug),
                'plugin' => $plugin_slug,
                'new_version' => $latest_release['version'],
                'url' => $latest_release['url'],
                'package' => $latest_release['download_url'],
            );
            
            $transient->response[$plugin_slug] = (object) $plugin_data;
        }
        
        return $transient;
    }
    
    /**
     * Clear update cache
     */
    public function clear_cache() {
        delete_transient('clubworx_github_latest_release');
        delete_site_transient('update_plugins');
        return true;
    }
    
    /**
     * Force check for updates (clears cache first)
     */
    public function force_check_updates() {
        $this->clear_cache();
        
        // Get fresh release data (force refresh)
        $latest_release = $this->get_latest_release(true);
        
        // If we got a release, also clear WordPress update cache to trigger refresh
        if ($latest_release) {
            delete_site_transient('update_plugins');
        }
        
        return $latest_release;
    }
    
    /**
     * Get latest release from GitHub
     */
    private function get_latest_release($force_refresh = false) {
        $cache_key = 'clubworx_github_latest_release';
        
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $api_url = sprintf(
            '%s/%s/%s/releases/latest',
            $this->github_api_url,
            $this->github_username,
            $this->github_repo
        );
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => $this->get_github_headers(),
        ));
        
        if (is_wp_error($response)) {
            error_log('Clubworx GitHub Updater: Failed to fetch release - ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $release = json_decode($body, true);
        
        // Log detailed error information
        if ($status_code !== 200) {
            error_log('Clubworx GitHub Updater: GitHub API returned status ' . $status_code);
            error_log('Clubworx GitHub Updater: API URL: ' . $api_url);
            if (isset($release['message'])) {
                error_log('Clubworx GitHub Updater: Error message: ' . $release['message']);
            }

            $fallback = $this->get_latest_published_release();
            if ($fallback) {
                set_transient($cache_key, $fallback, HOUR_IN_SECONDS);
                return $fallback;
            }

            return false;
        }
        
        if (empty($release) || !isset($release['tag_name'])) {
            error_log('Clubworx GitHub Updater: Release data is empty or missing tag_name');
            return false;
        }
        
        // Extract version from tag (remove 'v' prefix if present)
        $result = $this->format_release_row($release);
        
        // Cache for 1 hour
        set_transient($cache_key, $result, HOUR_IN_SECONDS);
        
        return $result;
    }

    /**
     * First published (non-draft) release from /releases when /releases/latest fails.
     *
     * @return array<string,string>|false
     */
    private function get_latest_published_release() {
        $all_releases_url = sprintf(
            '%s/%s/%s/releases',
            $this->github_api_url,
            $this->github_username,
            $this->github_repo
        );

        $all_response = wp_remote_get($all_releases_url, array(
            'timeout' => 10,
            'headers' => $this->get_github_headers(),
        ));

        if (is_wp_error($all_response) || wp_remote_retrieve_response_code($all_response) !== 200) {
            return false;
        }

        $all_releases = json_decode(wp_remote_retrieve_body($all_response), true);
        if (!is_array($all_releases) || empty($all_releases)) {
            return false;
        }

        foreach ($all_releases as $rel) {
            if (!empty($rel['draft']) || !empty($rel['prerelease'])) {
                continue;
            }
            if (empty($rel['tag_name'])) {
                continue;
            }
            return $this->format_release_row($rel);
        }

        return false;
    }

    /**
     * @param array<string,mixed> $release
     * @return array<string,string>
     */
    private function format_release_row($release) {
        $version = ltrim($release['tag_name'], 'v');
        $download_url = '';

        // Prefer an attached .zip asset when present (cleaner folder structure).
        if (!empty($release['assets']) && is_array($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (empty($asset['browser_download_url']) || strpos($asset['browser_download_url'], '.zip') === false) {
                    continue;
                }
                $download_url = $asset['browser_download_url'];
                break;
            }
        }
        if ($download_url === '' && !empty($release['zipball_url'])) {
            $download_url = $release['zipball_url'];
        }

        return array(
            'version' => $version,
            'url' => isset($release['html_url']) ? $release['html_url'] : '',
            'download_url' => $download_url,
            'release_notes' => isset($release['body']) ? $release['body'] : '',
        );
    }
    
    /**
     * Plugin information for update screen
     */
    public function plugin_info($false, $action, $args) {
        if ($action !== 'plugin_information') {
            return $false;
        }
        
        $plugin_slug = dirname(plugin_basename(CLUBWORX_INTEGRATION_PLUGIN_FILE));
        
        if ($args->slug !== $plugin_slug) {
            return $false;
        }
        
        $latest_release = $this->get_latest_release();
        
        if (!$latest_release) {
            return $false;
        }
        
        $info = new stdClass();
        $info->name = 'Clubworx';
        $info->slug = $plugin_slug;
        $info->version = $latest_release['version'];
        $info->author = 'Andy Jones';
        $info->homepage = $latest_release['url'];
        $info->download_link = $latest_release['download_url'];
        $info->sections = array(
            'description' => 'Trial class booking system with GA4 tracking, ClubWorx integration, and attribution tracking',
            'changelog' => $latest_release['release_notes']
        );
        
        return $info;
    }

}
