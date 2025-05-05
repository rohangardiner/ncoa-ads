<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://https://ncoa.com.au/
 * @since             1.0.0
 * @package           Ncoa_Ads
 *
 * @wordpress-plugin
 * Plugin Name:       NCOA Ads
 * Plugin URI:        https://https://ncoa.com.au/
 * Description:       Insert NCOA Display Ads on WordPress sites
 * Version:           1.0.0
 * Author:            Rohan
 * Author URI:        https://https://ncoa.com.au//
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ncoa-ads
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'NCOA_ADS_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-ncoa-ads-activator.php
 */
function activate_ncoa_ads() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ncoa-ads-activator.php';
	Ncoa_Ads_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-ncoa-ads-deactivator.php
 */
function deactivate_ncoa_ads() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ncoa-ads-deactivator.php';
	Ncoa_Ads_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_ncoa_ads' );
register_deactivation_hook( __FILE__, 'deactivate_ncoa_ads' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-ncoa-ads.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_ncoa_ads() {

   // Define update method from GitHub repo
   if (is_admin()) {
      define('GH_REQUEST_URI', 'https://api.github.com/repos/%s/%s/releases');
      define('GHPU_USERNAME', 'YOUR_GITHUB_USERNAME');
      define('GHPU_REPOSITORY', 'YOUR_GITHUB_REPOSITORY_NAME');
      define('GHPU_AUTH_TOKEN', 'YOUR_GITHUB_ACCESS_TOKEN');
  
      include_once plugin_dir_path(__FILE__) . '/GhPluginUpdater.php';
  
      $updater = new GhPluginUpdater(__FILE__);
      $updater->init();
  }

	$plugin = new Ncoa_Ads();
	$plugin->run();

}
run_ncoa_ads();
