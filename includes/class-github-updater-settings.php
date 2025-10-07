<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GitHub_Plugin_Updater_Settings {

    public function __construct() {
        // Only run settings logic in the admin area
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
            add_action( 'admin_init', array( $this, 'page_init' ) );
        }
    }

    /**
     * Add options page
     */
    public function add_plugin_page() {
        add_options_page(
            'GitHub Updater Settings', // page_title
            'GitHub Updater',          // menu_title
            'manage_options',          // capability
            'github-updater-settings', // menu_slug
            array( $this, 'create_admin_page' ) // function
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1>GitHub Plugin Updater Settings</h1>
            <p>Configure the plugins to be updated directly from their GitHub repositories.</p>
            <form method="post" action="options.php">
            <?php
                // This function adds the hidden fields for security (nonce) and options
                settings_fields( 'github_updater_option_group' );
                do_settings_sections( 'github-updater-settings' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init() {
        register_setting(
            'github_updater_option_group', // Option group
            'github_updater_options',      // Option name
            array( $this, 'sanitize' )     // Sanitize callback
        );

        add_settings_section(
            'github_updater_main_section', // ID
            'Configuration',               // Title
            null,                          // Callback (none needed)
            'github-updater-settings'      // Page
        );

        // Access Token Field
        add_settings_field(
            'github_access_token',                      // ID
            'GitHub Personal Access Token',             // Title
            array( $this, 'github_access_token_callback' ), // Callback
            'github-updater-settings',                  // Page
            'github_updater_main_section'               // Section
        );

        // Plugin Mapping Field
        add_settings_field(
            'plugin_map',                               // ID
            'Plugin Map (File Path|User/Repo)',         // Title
            array( $this, 'plugin_map_callback' ),      // Callback
            'github-updater-settings',                  // Page
            'github_updater_main_section'               // Section
        );
    }

    /**
     * Sanitize each setting field as needed. (SECURITY)
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input ) {
        $new_input = array();

        // Sanitize Access Token: Remove all tags, keep only basic alphanumeric/symbol characters
        if ( isset( $input['github_access_token'] ) ) {
            $new_input['github_access_token'] = sanitize_text_field( $input['github_access_token'] );
        }

        // Sanitize Plugin Map: Use a function for multi-line textarea input
        if ( isset( $input['plugin_map'] ) ) {
            // Note: We use sanitize_textarea_field but the main plugin logic does extra validation.
            $new_input['plugin_map'] = sanitize_textarea_field( $input['plugin_map'] );
        }

        // Clear Transients when settings are saved to force an immediate update check
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_github\_updater\_latest\_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_github\_updater\_latest\_%'" );
        add_settings_error( 'github-updater', 'transients_cleared', 'Settings saved and update cache cleared.', 'updated' );

        return $new_input;
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function github_access_token_callback() {
        $options = get_option( 'github_updater_options' );
        $token = isset( $options['github_access_token'] ) ? esc_attr( $options['github_access_token'] ) : '';
        printf(
            '<input type="password" id="github_access_token" name="github_updater_options[github_access_token]" value="%s" size="50" />',
            $token
        );
        echo '<p class="description">Required for private repositories and to increase API rate limits. <a href="https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/creating-a-personal-access-token" target="_blank">Generate a token here</a>.</p>';
    }

    /**
     * Get the settings option array and print the map textarea
     */
    public function plugin_map_callback() {
        $options = get_option( 'github_updater_options' );
        $map = isset( $options['plugin_map'] ) ? esc_textarea( $options['plugin_map'] ) : '';
        printf(
            '<textarea id="plugin_map" name="github_updater_options[plugin_map]" rows="10" cols="70">%s</textarea>',
            $map
        );
        echo '<p class="description">Enter one plugin per line in the format: <code>plugin-folder/main-file.php|github-user/repo-name</code></p>';
        echo '<p class="description">Example: <code>my-cool-plugin/my-cool-plugin.php|joedev/cool-repo</code></p>';
    }
}
