<?php
/**
 * Plugin Name: GitHub Plugin Updater
 * Plugin URI: https://github.com/jsrothwell/github-plugin-updater
 * Description: Enables automatic updates for specific hardcoded plugins from GitHub.
 * Version: 1.0.0
 * Author: Jamieson Rothwell
 * Author URI: https://github.com/jsrothwell/
 * License: GPL2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ====================================================================
// CONFIGURATION: Define the exact plugins and their GitHub repositories
// Format: 'plugin-folder/main-file.php' => 'github-user/repo-name'
// ====================================================================

$github_plugin_map = array(
    // NOTE: You MUST verify these plugin slugs match your local installation paths.
    // Assuming the main plugin file name is the same as the folder name.

    'yt-ux-suite/yt-ux-suite.php'                 => 'jsrothwell/yt-ux-suite',
    'youtube-comment-sync/youtube-comment-sync.php' => 'jsrothwell/youtube-comment-sync',
    'github-plugin-updater/github-plugin-updater.php' => 'jsrothwell/github-plugin-updater', // This plugin itself
    'YouTube-to-WordPress-Auto-Importer/YouTube-to-WordPress-Auto-Importer.php' => 'jsrothwell/YouTube-to-WordPress-Auto-Importer',
    'youtube-engagement-suite/youtube-engagement-suite.php' => 'jsrothwell/youtube-engagement-suite',
    'video-seo-pro/video-seo-pro.php'             => 'jsrothwell/video-seo-pro',
);

// Define the GitHub Updater Class.
class GitHub_Plugin_Updater {

    private $plugin_file;
    private $github_repo; // Format: 'user/repo'
    private $access_token = ''; // Keeping this optional for private repos

    /**
     * Constructor
     * @param string $plugin_file The path to the plugin file (e.g., 'my-plugin/my-plugin.php')
     * @param string $github_repo The repository path (e.g., 'user/repo')
     */
    public function __construct( $plugin_file, $github_repo ) {
        $this->plugin_file = $plugin_file;
        $this->github_repo = $github_repo;

        // Hook into the WordPress update check
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
        add_filter( 'plugins_api', array( $this, 'plugins_api_call' ), 10, 3 );
    }

    /**
     * Checks for updates from GitHub using Transient Caching.
     */
    public function check_for_updates( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        // --- Transient Caching ---
        $transient_key = 'github_updater_latest_' . md5( $this->github_repo );
        $cached_response = get_transient( $transient_key );

        if ( false === $cached_response ) {
            $api_url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
            $headers = array( 'Accept' => 'application/vnd.github.v3+json' );

            if ( ! empty( $this->access_token ) ) {
                $headers['Authorization'] = 'token ' . $this->access_token;
            }

            $response = wp_remote_get( $api_url, array( 'headers' => $headers ) );

            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
                // If API fails, return the original transient and let it expire normally
                return $transient;
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body );

            if ( ! isset( $data->tag_name ) || ! isset( $data->zipball_url ) ) {
                return $transient;
            }

            $cached_response = array(
                'version' => ltrim( $data->tag_name, 'v' ), // Strip 'v' prefix
                'package' => $data->zipball_url,
            );

            // Cache for 1 hour (3600 seconds) to respect GitHub API limits.
            set_transient( $transient_key, $cached_response, HOUR_IN_SECONDS );
        }

        // --- Version Comparison ---
        $latest_version = $cached_response['version'];
        $plugin_path = WP_PLUGIN_DIR . '/' . $this->plugin_file;

        if ( ! file_exists( $plugin_path ) ) {
            // Plugin file not found, skip checking
            return $transient;
        }

        $current_version = get_file_data( $plugin_path, array( 'Version' => 'Version' ) )['Version'];

        if ( version_compare( $latest_version, $current_version, '>' ) ) {
            // Update available! Construct the update data.
            $update = new stdClass();
            $update->slug = dirname( $this->plugin_file );
            $update->new_version = $latest_version;
            $update->url = "https://github.com/{$this->github_repo}";
            $update->package = $cached_response['package'];

            $transient->response[ $this->plugin_file ] = $update;
        }

        return $transient;
    }

    /**
     * Fetches basic detailed information for the plugin (e.g., for the "View details" link).
     */
    public function plugins_api_call( $result, $action, $args ) {
        if ( $action !== 'plugin_information' || ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_file ) ) {
            return $result;
        }

        // --- PLACEHOLDER: Ideally, fetch the full readme/changelog from GitHub ---
        // For simplicity, we just inject metadata here.
        $result = new stdClass();
        $result->name = 'Plugin: ' . dirname( $this->plugin_file );
        $result->slug = dirname( $this->plugin_file );
        $result->version = $this->get_latest_version();
        $result->author = 'jsrothwell';
        $result->homepage = "https://github.com/{$this->github_repo}";
        $result->sections = array(
            'description' => 'This plugin is updated directly from the GitHub repository: ' . $this->github_repo,
            'changelog'   => 'Check the repository releases page for the official changelog.',
        );

        return $result;
    }

    /**
     * Helper to get the latest version from cache/API for plugins_api_call
     */
    private function get_latest_version() {
        $transient_key = 'github_updater_latest_' . md5( $this->github_repo );
        $cached_response = get_transient( $transient_key );

        if ( $cached_response ) {
            return $cached_response['version'];
        }

        // Run the update check logic (without modifying the transient) to fetch the data
        $this->check_for_updates( (object) array( 'checked' => array() ) );

        $cached_response = get_transient( $transient_key );
        return $cached_response ? $cached_response['version'] : 'N/A';
    }
}

// --- INITIALIZATION: Instantiate the updater for each configured plugin ---
foreach ( $github_plugin_map as $plugin_file => $repo ) {
    // We don't need token handling here since the list is hardcoded and public.
    new GitHub_Plugin_Updater( $plugin_file, $repo );
}
