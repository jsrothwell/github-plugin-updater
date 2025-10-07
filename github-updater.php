<?php
/**
 * Plugin Name: GitHub Plugin Updater
 * Plugin URI: https://example.com/
 * Description: Enables automatic updates for specified plugins from their GitHub repositories.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com/
 * License: GPL2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include the settings page file.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-github-updater-settings.php';

// Instantiate the settings page.
if ( is_admin() ) {
    new GitHub_Plugin_Updater_Settings();
}

// ====================================================================
// GitHub Plugin Updater Class (Simplified for the update checks)
// ====================================================================

class GitHub_Plugin_Updater {

    private $plugin_file;
    private $github_repo; // Format: 'user/repo'
    private $access_token;

    public function __construct( $plugin_file, $github_repo, $access_token = '' ) {
        $this->plugin_file = $plugin_file;
        $this->github_repo = $github_repo;
        $this->access_token = $access_token;

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
    }

    /**
     * Checks for updates from GitHub using Transient Caching.
     */
    public function check_for_updates( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

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
                // Log error here or handle rate limits.
                return $transient;
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body );

            if ( ! isset( $data->tag_name ) || ! isset( $data->zipball_url ) ) {
                return $transient;
            }

            $cached_response = array(
                'version' => ltrim( $data->tag_name, 'v' ), // Strip 'v' prefix if present
                'package' => $data->zipball_url,
            );

            // Cache for 1 hour (3600 seconds) to respect GitHub API limits.
            set_transient( $transient_key, $cached_response, HOUR_IN_SECONDS );
        }

        $latest_version = $cached_response['version'];
        $current_version = get_file_data( WP_PLUGIN_DIR . '/' . $this->plugin_file, array( 'Version' => 'Version' ) )['Version'];

        if ( version_compare( $latest_version, $current_version, '>' ) ) {
            $update = new stdClass();
            $update->slug = dirname( $this->plugin_file );
            $update->new_version = $latest_version;
            $update->url = "https://github.com/{$this->github_repo}";
            // Note: The package URL often requires a token if the repo is private.
            $update->package = $cached_response['package'];

            $transient->response[ $this->plugin_file ] = $update;
        }

        return $transient;
    }

    // You would also need to update the 'plugins_api_call' function
    // to use the access token and transient caching for plugin details.
}

// ... (Previous code remains the same until the Initialization section)

// --- INITIALIZATION ---
// Load saved settings and instantiate the updater(s).
$github_settings = get_option( 'github_updater_options', array() );
$access_token = isset( $github_settings['github_access_token'] ) ? $github_settings['github_access_token'] : '';
$plugin_map = isset( $github_settings['plugin_map'] ) ? (array) $github_settings['plugin_map'] : array();

// $plugin_map is now an associative array: ['plugin-slug/main-file.php' => 'user/repo']

foreach ( $plugin_map as $plugin_file => $repo ) {
    // Basic validation: Check if file looks like a plugin file and repo looks like a repo path
    if ( preg_match( '/^[\w-]+\/[\w-]+\.php$/', $plugin_file ) && preg_match( '/^[\w-]+\/[\w-]+$/', $repo ) ) {
        // Instantiate a new updater object for each configured plugin
        new GitHub_Plugin_Updater( $plugin_file, $repo, $access_token );
    }
}
