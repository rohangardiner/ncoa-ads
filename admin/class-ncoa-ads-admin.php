<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://https://ncoa.com.au/
 * @since      1.0.0
 *
 * @package    Ncoa_Ads
 * @subpackage Ncoa_Ads/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Ncoa_Ads
 * @subpackage Ncoa_Ads/admin
 * @author     Rohan <rgardiner@actac.com.au>
 */
class Ncoa_Ads_Admin {

   /**
    * The ID of this plugin.
    *
    * @since    1.0.0
    * @access   private
    * @var      string    $plugin_name    The ID of this plugin.
    */
   private $plugin_name;

   /**
    * The version of this plugin.
    *
    * @since    1.0.0
    * @access   private
    * @var      string    $version    The current version of this plugin.
    */
   private $version;

   /**
    * Initialize the class and set its properties.
    *
    * @since    1.0.0
    * @param      string    $plugin_name       The name of this plugin.
    * @param      string    $version    The version of this plugin.
    */
   public function __construct($plugin_name, $version) {

      $this->plugin_name = $plugin_name;
      $this->version = $version;
   }

   /**
    * Register the stylesheets for the admin area.
    *
    * @since    1.0.0
    */
   public function enqueue_styles() {

      /**
       * This function is provided for demonstration purposes only.
       *
       * An instance of this class should be passed to the run() function
       * defined in Ncoa_Ads_Loader as all of the hooks are defined
       * in that particular class.
       *
       * The Ncoa_Ads_Loader will then create the relationship
       * between the defined hooks and the functions defined in this
       * class.
       */

      wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/ncoa-ads-admin.css', array(), $this->version, 'all');
   }

   /**
    * Register the JavaScript for the admin area.
    *
    * @since    1.0.0
    */
   public function enqueue_scripts() {

      /**
       * This function is provided for demonstration purposes only.
       *
       * An instance of this class should be passed to the run() function
       * defined in Ncoa_Ads_Loader as all of the hooks are defined
       * in that particular class.
       *
       * The Ncoa_Ads_Loader will then create the relationship
       * between the defined hooks and the functions defined in this
       * class.
       */

      wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/ncoa-ads-admin.js', array('jquery'), $this->version, false);
   }
}

// Plugin Options page
function ncoaads_settings_init() {
   // Register a new setting for "ncoaads" page.
   register_setting('ncoaads', 'ncoaads_adtype');
   register_setting('ncoaads', 'ncoaads_cookie_timeout');

   // Register a new section in the "ncoaads" page.
   add_settings_section(
      'ncoaads_section_developers',
      __('NCOA Ads Settings', 'ncoaads'),
      'ncoaads_section_developers_callback',
      'ncoaads'
   );

   // Register a new field in the "ncoaads_section_developers" section, inside the "ncoaads" page.
   add_settings_field(
      'ncoaads_field_adtype', // As of WP 4.6 this value is used only internally.
      // Use $args' label_for to populate the id inside the callback.
      __('Ad Type', 'ncoaads'),
      'ncoaads_field_adtype_cb',
      'ncoaads',
      'ncoaads_section_developers',
      array(
         'label_for'         => 'ncoaads_field_adtype',
         'class'             => 'ncoaads_row',
         'ncoaads_custom_data' => 'custom',
      )
   );

   add_settings_field(
      'ncoaads_field_cookie_timeout', // As of WP 4.6 this value is used only internally.
      // Use $args' label_for to populate the id inside the callback.
      __('Cookie Timeout', 'ncoaads'),
      'ncoaads_field_cookie_timeout_cb',
      'ncoaads',
      'ncoaads_section_developers',
      array(
         'label_for'         => 'ncoaads_field_cookie_timeout',
         'class'             => 'ncoaads_row',
         'ncoaads_custom_data' => 'custom',
      )
   );
}

/**
 * Register our ncoaads_settings_init to the admin_init action hook.
 */
add_action('admin_init', 'ncoaads_settings_init');


/**
 * Custom option and settings:
 *  - callback functions
 */


/**
 * Developers section callback function.
 *
 * @param array $args  The settings array, defining title, id, callback.
 */
function ncoaads_section_developers_callback($args) {
?>
   <p id="<?php echo esc_attr($args['id']); ?>"><?php esc_html_e('Configure NCOA display ads', 'ncoaads'); ?></p>
<?php
}

/**
 * Ad Type field callbakc function.
 *
 * WordPress has magic interaction with the following keys: label_for, class.
 * - the "label_for" key value is used for the "for" attribute of the <label>.
 * - the "class" key value is used for the "class" attribute of the <tr> containing the field.
 * Note: you can add custom key value pairs to be used inside your callbacks.
 *
 * @param array $args
 */
function ncoaads_field_adtype_cb($args) {
   // Get the value of the setting we've registered with register_setting()
   $options = get_option('ncoaads_adtype');
?>
   <select
      id="<?php echo esc_attr($args['label_for']); ?>"
      data-custom="<?php echo esc_attr($args['ncoaads_custom_data']); ?>"
      name="ncoaads_adtype[<?php echo esc_attr($args['label_for']); ?>]">

      <option value="actac" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], 'actac', false)) : (''); ?>>
         <?php esc_html_e('ACTAC', 'ncoaads'); ?>
      </option>

      <option value="accsc" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], 'accsc', false)) : (''); ?>>
         <?php esc_html_e('ACCSC', 'ncoaads'); ?>
      </option>

      <option value="acfpt" <?php echo isset($options[$args['label_for']]) ? (selected($options[$args['label_for']], 'acfpt', false)) : (''); ?>>
         <?php esc_html_e('ACFPT', 'ncoaads'); ?>
      </option>

   </select>
   <p class="description">
      <?php esc_html_e('Choose the college to be advertised.', 'ncoaads'); ?>
   </p>
<?php
}

function ncoaads_field_cookie_timeout_cb($args) {
   // Get the value of the setting we've registered with register_setting()
   $options = get_option('ncoaads_cookie_timeout');
   $value = isset($options[$args['label_for']]) ? esc_attr($options[$args['label_for']]) : ''; // Default to empty if not set
?>
   <input
      type="number"
      id="<?php echo esc_attr($args['label_for']); ?>"
      name="ncoaads_cookie_timeout[<?php echo esc_attr($args['label_for']); ?>]"
      value="<?php echo $value; ?>"
      min="0"
      step="1"
      placeholder="Enter minutes" />
   <p class="description">
      <?php esc_html_e('Enter the number of minutes to hide ads after the user clicks close.', 'ncoaads'); ?>
   </p>
   <p class="description">
      12 hours : 720 minutes<br>
      24 hours : 1440 minutes
   </p>
<?php
}

/**
 * Add the top level menu page.
 */
function ncoaads_options_page() {
   // Add a subpage under Settings which shows the content of: ncoaads_options_page_html()
   add_options_page(
      __('NCOA Ads Settings', 'ncoaads'),
      __('NCOA Ads Settings', 'ncoaads'),
      'manage_options',
      'ncoaads',
      'ncoaads_options_page_html'
   );
}


/**
 * Register our ncoaads_options_page to the admin_menu action hook.
 */
add_action('admin_menu', 'ncoaads_options_page');


/**
 * Top level menu callback function
 */
function ncoaads_options_page_html() {
   // check user capabilities
   if (! current_user_can('manage_options')) {
      return;
   }

   // add error/update messages

   // check if the user have submitted the settings
   // WordPress will add the "settings-updated" $_GET parameter to the url
   if (isset($_GET['settings-updated'])) {
      // add settings saved message with the class of "updated"
      add_settings_error('ncoaads_messages', 'ncoaads_message', __('Settings Saved', 'ncoaads'), 'updated');
   }

   // show error/update messages
   settings_errors('ncoaads_messages');
?>
   <div class="wrap">
      <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
      <form action="options.php" method="post">
         <?php
         // output security fields for the registered setting "ncoaads"
         settings_fields('ncoaads');
         // output setting sections and their fields
         // (sections are registered for "ncoaads", each field is registered to a specific section)
         do_settings_sections('ncoaads');
         // output save settings button
         submit_button('Save Settings');
         ?>
      </form>
   </div>
<?php
}
