=== GitHub Plugin Updater ===
Contributors: (your-wordpress-username)
Tags: github, updater, plugin, update
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin is a proof-of-concept to demonstrate updating other WordPress plugins directly from their public GitHub repositories.

== Description ==

This plugin utilizes WordPress transients and the `plugins_api` filter to integrate with the core update checking process.

**NOTE:** This is a developer utility and requires further development for robust production use, especially for handling GitHub API limits and private repositories.

== Installation ==

1. Upload the `github-updater` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Edit the `github-updater.php` file to configure the plugins you wish to track with their respective GitHub URLs.
