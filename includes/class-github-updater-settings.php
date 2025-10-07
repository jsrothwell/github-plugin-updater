<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GitHub_Plugin_Updater_Settings {

    private $options_name = 'github_updater_options';

    public function __construct() {
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
            add_action( 'admin_init', array( $this, 'page_init' ) );
            // Action for the 'Scan' button redirect
            add_action( 'admin_action_github_scan_plugins', array( $this, 'scan_plugins' ) );
        }
    }

    /**
     * Add options page
     */
    public function add_plugin_page() {
        add_options_page(
            'GitHub Updater Settings',
            'GitHub Updater',
            'manage_options',
            'github-updater-settings',
            array( $this, 'create_admin_page' )
        );
    }

    // In includes/class-github-updater-settings.php

  // ... inside create_admin_page() method

          // Options page callback
          public function create_admin_page() {
              // ... (transient notice code remains the same) ...
              ?>
              <div class="wrap">
                  <h1>GitHub Plugin Updater Settings</h1>
                  <p>Configure which installed plugins should receive updates directly from GitHub.</p>

                  <form method="post" action="options.php">
                  <?php
                      settings_fields( 'github_updater_option_group' );
                      do_settings_sections( 'github-updater-settings' );
                      submit_button('Save General Settings'); // Give this button a distinct label
                  ?>
                  </form>

                  <hr/>

                  <h2>Manage Installed Plugins</h2>
                  <p>
                      <a href="<?php echo admin_url( 'admin.php?action=github_scan_plugins&_wpnonce=' . wp_create_nonce( 'github_scan_nonce' ) ); ?>" class="button button-secondary">
                          Scan for New Plugins
                      </a>
                      <span class="description">Click to check for any recently installed plugins and add them to the configuration list.</span>
                  </p>

                  <form method="post" action="options.php">
                      <?php
                          settings_fields( 'github_updater_option_group' );
                          // Directly call the method to render the table content
                          $this->plugin_map_table_callback();
                          submit_button( 'Save Plugin Configuration' );
                      ?>
                  </form>
              </div>
              <?php
          }

        ?>
        <div class="wrap">
            <h1>GitHub Plugin Updater Settings</h1>
            <p>Configure which installed plugins should receive updates directly from GitHub.</p>
            <form method="post" action="options.php">
            <?php
                settings_fields( 'github_updater_option_group' );
                do_settings_sections( 'github-updater-settings' );
                submit_button();
            ?>
            </form>

            <hr/>

            <h2>Manage Installed Plugins</h2>
            <p>
                <a href="<?php echo admin_url( 'admin.php?action=github_scan_plugins&_wpnonce=' . wp_create_nonce( 'github_scan_nonce' ) ); ?>" class="button button-secondary">
                    Scan for New Plugins
                </a>
                <span class="description">Click to check for any recently installed plugins and add them to the configuration list.</span>
            </p>

            <form method="post" action="options.php">
                <?php
                    settings_fields( 'github_updater_option_group' );
                    $this->plugin_map_table_callback();
                    submit_button( 'Save Plugin Configuration' );
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
            'github_updater_option_group',
            $this->options_name,
            array( $this, 'sanitize' )
        );

        add_settings_section(
            'github_updater_main_section',
            'GitHub API and Authentication',
            null,
            'github-updater-settings'
        );

        // GitHub Authentication Field
        add_settings_field(
            'github_access_token',
            'GitHub Personal Access Token',
            array( $this, 'github_access_token_callback' ),
            'github-updater-settings',
            'github_updater_main_section'
        );

        // In includes/class-github-updater-settings.php

        // ... inside page_init() method

                // Register and add settings
                public function page_init() {
                    register_setting(
                        'github_updater_option_group', // Option group
                        $this->options_name,          // Option name
                        array( $this, 'sanitize' )    // Sanitize callback
                    );

                    add_settings_section(
                        'github_updater_main_section', // ID
                        'GitHub API and Authentication', // Title
                        null,                          // Callback (none needed)
                        'github-updater-settings'      // Page
                    );

                    // GitHub Authentication Field (KEEP THIS)
                    add_settings_field(
                        'github_access_token',
                        'GitHub Personal Access Token',
                        array( $this, 'github_access_token_callback' ),
                        'github-updater-settings',
                        'github_updater_main_section'
                    );

                    // ----------------------------------------------------------------
                    // REMOVE THIS BLOCK (or comment it out)
                    /*
                    // Plugin Map (The table is rendered separately in create_admin_page)
                    add_settings_field(
                        'plugin_map',
                        'Plugin Map Configuration',
                        null, // <-- THIS NULL CAUSED THE FATAL ERROR
                        'github-updater-settings',
                        'github_updater_main_section'
                    );
                    */
                    // ----------------------------------------------------------------
                }

        // ...

    /**
     * Sanitizes and saves the options.
     */
    public function sanitize( $input ) {
        $new_input = get_option( $this->options_name, array() );

        // 1. Sanitize Access Token
        if ( isset( $input['github_access_token'] ) ) {
            $new_input['github_access_token'] = sanitize_text_field( $input['github_access_token'] );
        }

        // 2. Sanitize and structure the Plugin Map
        if ( isset( $input['plugin_map'] ) && is_array( $input['plugin_map'] ) ) {
            $sanitized_map = array();
            foreach ( $input['plugin_map'] as $plugin_slug => $repo_url ) {
                $plugin_slug = sanitize_file_name( $plugin_slug ); // For the file path/slug
                $repo_url = esc_url_raw( $repo_url ); // Use raw URL sanitization

                // Only save entries that have a valid-looking GitHub URL
                if ( ! empty( $repo_url ) && preg_match( '/^https:\/\/github\.com\/[\w-]+\/[\w-]+/', $repo_url ) ) {
                    // Store the 'user/repo' part only for simplicity in the updater logic
                    $path = parse_url( $repo_url, PHP_URL_PATH );
                    $repo_path = trim( $path, '/' );
                    $sanitized_map[ $plugin_slug ] = $repo_path;
                }
            }
            $new_input['plugin_map'] = $sanitized_map;
        }

        // 3. Clear Transients (Security/Performance)
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_github\_updater\_latest\_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_github\_updater\_latest\_%'" );
        add_settings_error( 'github-updater', 'transients_cleared', 'Settings saved and update cache cleared.', 'updated' );

        return $new_input;
    }

    /**
     * Renders the GitHub Access Token input field.
     */
    public function github_access_token_callback() {
        $options = get_option( $this->options_name, array() );
        $token = isset( $options['github_access_token'] ) ? esc_attr( $options['github_access_token'] ) : '';
        printf(
            '<input type="password" id="github_access_token" name="%s[github_access_token]" value="%s" size="50" autocomplete="off" />',
            $this->options_name,
            $token
        );
        echo '<p class="description">Required for private repositories and to increase API rate limits. <a href="https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/creating-a-personal-access-token" target="_blank">Generate a token here</a>.</p>';
    }

    /**
     * Renders the dynamic table for Plugin Mapping.
     */
    public function plugin_map_table_callback() {
        // Ensure plugin.php is loaded for get_plugins()
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $installed_plugins = get_plugins();
        $options = get_option( $this->options_name, array() );
        $saved_map = isset( $options['plugin_map'] ) ? (array) $options['plugin_map'] : array();

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th style="width: 40%;">Installed Plugin (Slug)</th>';
        echo '<th>GitHub Repository URL (e.g., https://github.com/user/repo)</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if ( empty( $installed_plugins ) ) {
            echo '<tr><td colspan="2">No installed plugins found.</td></tr>';
        } else {
            foreach ( $installed_plugins as $plugin_slug => $plugin_data ) {
                $repo_path = isset( $saved_map[ $plugin_slug ] ) ? $saved_map[ $plugin_slug ] : '';
                $repo_url = $repo_path ? 'https://github.com/' . $repo_path : '';
                $plugin_name = esc_html( $plugin_data['Name'] );
                $plugin_version = esc_html( $plugin_data['Version'] );

                echo '<tr>';
                // Left Column: Plugin Name and Version/Slug
                echo '<td>';
                echo "<strong>{$plugin_name}</strong> (v{$plugin_version})<br/>";
                echo "<code style='opacity: 0.7;'>{$plugin_slug}</code>";
                echo '</td>';

                // Right Column: Input Box for GitHub URL
                echo '<td>';
                printf(
                    '<input type="url" name="%s[plugin_map][%s]" value="%s" placeholder="Optional: Enter GitHub URL" class="regular-text" style="width: 100%%;" />',
                    $this->options_name,
                    esc_attr( $plugin_slug ),
                    esc_url( $repo_url )
                );
                echo '<p class="description">Leave blank to use the standard WordPress.org update process.</p>';
                echo '</td>';
                echo '</tr>';

                // Remove from the saved map list to track what's left over (i.e., removed plugins)
                if ( isset( $saved_map[ $plugin_slug ] ) ) {
                    unset( $saved_map[ $plugin_slug ] );
                }
            }

            // Display plugins that were in the config but are no longer installed (for removal/cleanup)
            foreach ( $saved_map as $plugin_slug => $repo_path ) {
                $repo_url = 'https://github.com/' . $repo_path;
                echo '<tr style="background-color: #ffeaea;">'; // Highlight removed plugins
                echo '<td>';
                echo "<strong>Uninstalled Plugin:</strong> <code style='color: red;'>{$plugin_slug}</code>";
                echo '</td>';

                echo '<td>';
                printf(
                    '<input type="url" name="%s[plugin_map][%s]" value="%s" placeholder="Plugin no longer installed. Clear this field to remove." class="regular-text" style="width: 100%%;" />',
                    $this->options_name,
                    esc_attr( $plugin_slug ),
                    esc_url( $repo_url )
                );
                echo '<p class="description" style="color: red;">Plugin not found. Clear the URL above to remove from configuration.</p>';
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    /**
     * Handles the "Scan for New Plugins" action.
     */
    public function scan_plugins() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Cheatin&#8217; huh?' );
        }

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'github_scan_nonce' ) ) {
            wp_die( 'Security check failed.' );
        }

        // The key is that `get_plugins()` automatically reflects installed plugins.
        // We just need to force the page to reload and show a message.
        set_transient( 'github_updater_scanned', true, 5 );

        // Redirect back to the settings page
        $redirect_url = admin_url( 'options-general.php?page=github-updater-settings' );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    // --- Placeholder for Optional GitHub Account Integration ---
    /*
    public function github_repo_selection_callback() {
        // This is complex and requires:
        // 1. OAuth flow to connect to the user's GitHub account (not just a token).
        // 2. AJAX calls to fetch the list of repositories for the connected account.
        // 3. Rendering a <select> dropdown instead of a text input for selection.
    }
    */
}
