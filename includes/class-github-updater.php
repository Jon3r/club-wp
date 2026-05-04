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
        
        // Only hook into update system if GitHub is configured
        if (!empty($this->github_username) && !empty($this->github_repo)) {
            add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
            add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
            add_filter('upgrader_source_selection', array($this, 'upgrader_source_selection'), 10, 4);
        }
    }
    
    /**
     * Rename the GitHub folder (repo-name-version) to the correct plugin slug
     * This ensures WordPress overwrites the existing plugin folder
     */
    public function upgrader_source_selection($source, $remote_source, $upgrader, $hook_extra = null) {
        global $wp_filesystem;
        
        // Ensure $hook_extra is available (might be null in some WP versions or contexts)
        if (!isset($hook_extra['plugin']) && !isset($hook_extra['theme'])) {
            return $source;
        }
        
        // Identify if this update is for our plugin
        $plugin_slug = plugin_basename(CLUBWORX_INTEGRATION_PLUGIN_FILE); // pjja-booking/clubworx-integration.php
        $plugin_dir = dirname($plugin_slug); // pjja-booking
        
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $plugin_slug) {
            // New destination path with correct slug
            $new_source = trailingslashit($remote_source) . $plugin_dir;
            
            // If the source is already correct, do nothing
            if (trailingslashit($source) === trailingslashit($new_source)) {
                return $source;
            }
            
            // Rename the folder
            if ($wp_filesystem->move($source, $new_source)) {
                return trailingslashit($new_source);
            }
        }
        
        return $source;
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
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress-Clubworx-Integration'
            )
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
            
            // If 404, try checking all releases (might be a draft)
            if ($status_code === 404) {
                $all_releases_url = sprintf(
                    '%s/%s/%s/releases',
                    $this->github_api_url,
                    $this->github_username,
                    $this->github_repo
                );
                
                $all_response = wp_remote_get($all_releases_url, array(
                    'timeout' => 10,
                    'headers' => array(
                        'Accept' => 'application/vnd.github.v3+json',
                        'User-Agent' => 'WordPress-Clubworx-Integration'
                    )
                ));
                
                if (!is_wp_error($all_response) && wp_remote_retrieve_response_code($all_response) === 200) {
                    $all_releases = json_decode(wp_remote_retrieve_body($all_response), true);
                    if (is_array($all_releases) && !empty($all_releases)) {
                        error_log('Clubworx GitHub Updater: Found ' . count($all_releases) . ' releases, but /latest returned 404. This might mean the latest release is a draft.');
                        // Check if there's a draft release
                        foreach ($all_releases as $rel) {
                            if (isset($rel['draft']) && $rel['draft'] === true) {
                                error_log('Clubworx GitHub Updater: Found draft release: ' . (isset($rel['tag_name']) ? $rel['tag_name'] : 'unknown'));
                            }
                        }
                    }
                }
            }
            
            return false;
        }
        
        if (empty($release) || !isset($release['tag_name'])) {
            error_log('Clubworx GitHub Updater: Release data is empty or missing tag_name');
            return false;
        }
        
        // Extract version from tag (remove 'v' prefix if present)
        $version = ltrim($release['tag_name'], 'v');
        
        // Find zipball URL
        $download_url = '';
        if (isset($release['zipball_url'])) {
            $download_url = $release['zipball_url'];
        } elseif (isset($release['assets']) && is_array($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (isset($asset['browser_download_url']) && strpos($asset['browser_download_url'], '.zip') !== false) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }
        
        $result = array(
            'version' => $version,
            'url' => isset($release['html_url']) ? $release['html_url'] : '',
            'download_url' => $download_url,
            'release_notes' => isset($release['body']) ? $release['body'] : '',
        );
        
        // Cache for 1 hour
        set_transient($cache_key, $result, HOUR_IN_SECONDS);
        
        return $result;
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
