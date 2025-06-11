<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://ncoa.com.au/
 * @since             1.0.0
 * @package           Ncoa_Ads
 *
 * @wordpress-plugin
 * Plugin Name:       NCOA Ads
 * Plugin URI:        https://ncoa.com.au/
 * Description:       Insert NCOA Display Ads on WordPress sites
 * Version:           1.1.5
 * Author:            Rohan
 * Author URI:        https://ncoa.com.au/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ncoa-ads
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
   die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('NCOA_ADS_VERSION', '1.0.9');
define('NCOA_ADS_ASSETS', plugin_dir_url(__FILE__) . 'public/assets');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-ncoa-ads-activator.php
 */
function activate_ncoa_ads() {
   require_once plugin_dir_path(__FILE__) . 'includes/class-ncoa-ads-activator.php';
   Ncoa_Ads_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-ncoa-ads-deactivator.php
 */
function deactivate_ncoa_ads() {
   require_once plugin_dir_path(__FILE__) . 'includes/class-ncoa-ads-deactivator.php';
   Ncoa_Ads_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_ncoa_ads');
register_deactivation_hook(__FILE__, 'deactivate_ncoa_ads');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-ncoa-ads.php';

function get_random_image_from_directory($directory) {
   // Ensure the directory exists
   if (!is_dir($directory)) {
      return false; // Return false if the directory doesn't exist
   }

   // Get all files in the directory
   $files = array_diff(scandir($directory), array('.', '..'));

   // Filter only image files (optional, based on extensions)
   $image_files = array_filter($files, function ($file) use ($directory) {
      $file_path = $directory . DIRECTORY_SEPARATOR . $file;
      return is_file($file_path) && preg_match('/\.(jpg|jpeg|png|gif)$/i', $file);
   });

   // If no image files are found, return false
   if (empty($image_files)) {
      return false;
   }

   // Pick a random image
   $random_file = array_rand($image_files);

   // Return the full path to the random image
   return $directory . DIRECTORY_SEPARATOR . $image_files[$random_file];
}

function display_ad($ad_type = 'accsc', $cookie_timeout = 60) {
   // Use ad type as array index to get the target URL for the ad
   $target = array(
      'accsc' => 'https://accsc.com.au/courses/?utm_source=ncoa&utm_medium=display&utm_campaign=ncoaadsplugin',
      'actac' => 'https://actac.com.au/courses/?utm_source=ncoa&utm_medium=display&utm_campaign=ncoaadsplugin',
      'acfpt' => 'https://acfpt.com.au/courses/?utm_source=ncoa&utm_medium=display&utm_campaign=ncoaadsplugin'
   );

   // Define the directory for the ad images
   $image_directory = plugin_dir_path(__FILE__) . 'public/assets/' . $ad_type;

   // Get a random image from the directory
   $random_image = get_random_image_from_directory($image_directory);

   // Fallback if no image is found
   if (!$random_image) {
      $random_image = NCOA_ADS_ASSETS . '/default-placeholder.png';
   } else {
      $random_image = plugin_dir_url(__FILE__) . 'public/assets/' . $ad_type . '/' . basename($random_image);
   }

   // Load the image, display: none to prevent flicker as it loads, will set display:block in JS
   return '
      <div id="ncoadisplay" data-time=' . $cookie_timeout . '>
         <img loading="lazy" style="display:none;" src="' . $random_image . '">
         <a id="ncoadisplay-clickarea" href="' . $target[$ad_type] . '"></a>
         <button id="ncoadisplay-close">&times;</button>
      </div>
   ';
}

function is_login_page() {
   return in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'));
}

function queue_display_ad() {
   // Runs after WordPress loaded
   // Get the ncoaads_cookie_timeout option from db, returns an array
   $ads_enabled = get_option('ncoaads_enable_plugin');
   $ads_enabled_for_logged_in_users = get_option('ncoaads_show_for_logged_in_users');
   $cookie_timeout = get_option('ncoaads_cookie_timeout');
   $ad_type = get_option('ncoaads_adtype');

   // If not WP Admin page, and ads enabled, and either not logged in or ads enabled for logged-in users, show an ad
   if (
      !is_admin() && 
      strpos($_SERVER['REQUEST_URI'], 'elementor') === false && 
      !is_login_page() && 
      $ads_enabled == 1 && 
      (!is_user_logged_in() || $ads_enabled_for_logged_in_users == 1)) {
      echo display_ad(
         $ad_type['ncoaads_field_adtype'],
         $cookie_timeout['ncoaads_field_cookie_timeout']
      );
   }
}

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

   $plugin = new Ncoa_Ads();
   $plugin->run();

   // Queue ad display function so that we can get is_logged_in after plugins loaded
   add_action('init', 'queue_display_ad');

   // Link to settings page from plugins screen
   function add_plugin_link($plugin_actions, $plugin_file) {
      $new_actions = array();
      if (basename(plugin_dir_path(__FILE__)) . 'ncoa-ads.php' === $plugin_file) {
         $new_actions['na_settings'] = sprintf(__('<a href="%s">Settings</a>', 'ncoaads'), esc_url(admin_url('options-general.php?page=ncoaads')));
      }
      return array_merge($new_actions, $plugin_actions);
   }
   add_filter('plugin_action_links', 'add_plugin_link', 10, 2);

   // Check for plugin updates
   add_action('init', 'github_plugin_updater_ncoa_ads');
   function github_plugin_updater_ncoa_ads() {
      require_once plugin_dir_path(__FILE__) . 'includes/class-ncoa-ads-updater.php';

      if ( ! defined( 'WP_GITHUB_FORCE_UPDATE' ) ) {
         define('WP_GITHUB_FORCE_UPDATE', true);
      }
      
      if (is_admin()) {
         $config = array(
            'slug' => plugin_basename(__FILE__), // this is the slug of your plugin
            'proper_folder_name' => 'ncoa-ads', // this is the name of the folder your plugin lives in
            'api_url' => 'https://api.github.com/repos/rohangardiner/ncoa-ads', // the GitHub API url of your GitHub repo
            'raw_url' => 'https://raw.github.com/rohangardiner/ncoa-ads/main', // the GitHub raw url of your GitHub repo
            'github_url' => 'https://github.com/rohangardiner/ncoa-ads', // the GitHub url of your GitHub repo
            'zip_url' => 'https://github.com/rohangardiner/ncoa-ads/zipball/main', // the zip url of the GitHub repo
            'sslverify' => true, // whether WP should check the validity of the SSL cert when getting an update, see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2 and https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/4 for details
            'requires' => '6.0', // which version of WordPress does your plugin require?
            'tested' => '6.8.1', // which version of WordPress is your plugin tested up to?
            'readme' => 'README.md', // which file to use as the readme for the version number
            'access_token' => '', // Access private repositories by authorizing under Plugins > GitHub Updates when this example plugin is installed
         );
         new WP_GitHub_Updater_Ncoa_Ads($config);
      }
   }
}
run_ncoa_ads();
