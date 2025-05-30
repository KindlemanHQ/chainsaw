<?php
/**
 * Plugin Name: Chainsaw
 * Plugin URI: https://kindleman.com.au/chainsaw
 * Description: Wordpress Logging and debug.
 * Version: 1.0.0
 * Author: Will 
 * Author URI: https://willbarker.dev
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: chainsaw-plugin
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants with uppercase names
define('CHAINSAW_VERSION', '1.0.0');
define('CHAINSAW_DIR', plugin_dir_path(__FILE__));
define('CHAINSAW_URL', plugin_dir_url(__FILE__));

class Chainsaw {
    /**
     * Initialize the plugin
     */
    public function init() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add menu item
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Load admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Include settings fields
        // require_once CHAINSAW_DIR . 'includes/settings-fields.php';
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'chainsaw_settings',    // Option group
            'chainsaw_options',     // Option name
            array(                  
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );
        
        // Register sections and fields
        $this->setup_settings_sections();
    }
    
    /**
     * Setup settings sections and fields
     */
    private function setup_settings_sections() {
        // Add API Settings section
        

        // Add API Key field
        

        // Add Debug Settings section
        add_settings_section(
            'chainsaw_debug_section',
            __('Debug Configuration', 'chainsaw-plugin'),
            array($this, 'debug_section_callback'),
            'chainsaw-settings'
        );

        add_settings_section(
            'chainsaw_api_section',
            __('API Settings', 'chainsaw-plugin'),
            array($this, 'api_section_callback'),
            'chainsaw-settings'
        );

        add_settings_field(
            'chainsaw_api_key',
            __('API Key', 'chainsaw-plugin'),
            array($this, 'api_key_field_callback'),
            'chainsaw-settings',
            'chainsaw_api_section'
        );

       
    }
    
    /**
     * API Section callback
     */
    public function api_section_callback() {
        echo '<p>' . __('Configure your API connection settings.', 'chainsaw-plugin') . '</p>';
    }
    
    /**
     * API Key field callback
     */
    public function api_key_field_callback() {
        $options = get_option('chainsaw_options');
        $api_key = isset($options['api_key']) ? esc_attr($options['api_key']) : '';
        ?>
        <input type="text" 
               id="chainsaw_api_key" 
               name="chainsaw_options[api_key]" 
               value="<?php echo $api_key; ?>" 
               class="regular-text">
        <p class="description">
            <?php _e('Enter your API key.', 'chainsaw-plugin'); ?>
        </p>
        <?php
    }


    public function debug_section_callback() {
      echo '<p>' . __('These WordPress debug settings affect how errors and logs are handled.', 'chainsaw-plugin') . '</p>';
      echo '<p>' . __('Recommended production settings: WP_DEBUG=false, WP_DEBUG_DISPLAY=false, WP_DEBUG_LOG=false', 'chainsaw-plugin') . '</p>';
      $this->debug_info_field_callback();
    }

    /**
 * Debug Info field callback
 */
public function debug_info_field_callback() {
    $debug_settings = array(
        'WP_DEBUG' => defined('WP_DEBUG') ? WP_DEBUG : false,
        'WP_DEBUG_DISPLAY' => defined('WP_DEBUG_DISPLAY') ? WP_DEBUG_DISPLAY : false,
        'WP_DEBUG_LOG' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
        'CORE_UPGRADE_SKIP_NEW_BUNDLED' => defined('CORE_UPGRADE_SKIP_NEW_BUNDLED') ? CORE_UPGRADE_SKIP_NEW_BUNDLED : false,
    );
    ?>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php _e('Setting', 'chainsaw-plugin'); ?></th>
                <th><?php _e('Current Value', 'chainsaw-plugin'); ?></th>
                <th><?php _e('Recommended', 'chainsaw-plugin'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>WP_DEBUG</code></td>
                <td><strong><?php echo $debug_settings['WP_DEBUG'] ? __('true', 'chainsaw-plugin') : __('false', 'chainsaw-plugin'); ?></strong></td>
                <td><?php _e('false on production', 'chainsaw-plugin'); ?></td>
            </tr>
            <tr>
                <td><code>WP_DEBUG_DISPLAY</code></td>
                <td><strong><?php echo $debug_settings['WP_DEBUG_DISPLAY'] ? __('true', 'chainsaw-plugin') : __('false', 'chainsaw-plugin'); ?></strong></td>
                <td><?php _e('false on production', 'chainsaw-plugin'); ?></td>
            </tr>
            <tr>
                <td><code>WP_DEBUG_LOG</code></td>
                <td><strong><?php echo $debug_settings['WP_DEBUG_LOG'] ? __('true', 'chainsaw-plugin') : __('false', 'chainsaw-plugin'); ?></strong></td>
                <td><?php _e('false on production (or true for logging)', 'chainsaw-plugin'); ?></td>
            </tr>
            <tr>
                <td><code>CORE_UPGRADE_SKIP_NEW_BUNDLED</code></td>
                <td><strong><?php echo $debug_settings['CORE_UPGRADE_SKIP_NEW_BUNDLED'] ? __('true', 'chainsaw-plugin') : __('false', 'chainsaw-plugin'); ?></strong></td>
                <td><?php _e('true to skip bundled items during update', 'chainsaw-plugin'); ?></td>
            </tr>
        </tbody>
    </table>
    
    <div class="">
        <p><?php _e('These settings are defined in your wp-config.php file. To change them:', 'chainsaw-plugin'); ?></p>
        <ol>
            <li><?php _e('Edit wp-config.php (usually in your WordPress root directory)', 'chainsaw-plugin'); ?></li>
            <li><?php _e('Look for the debug settings (search for "WP_DEBUG")', 'chainsaw-plugin'); ?></li>
            <li><?php _e('Modify the values and save the file', 'chainsaw-plugin'); ?></li>
            <li><?php _e('Refresh this page to see the updated values', 'chainsaw-plugin'); ?></li>
        </ol>
        <p><strong><?php _e('Important:', 'chainsaw-plugin'); ?></strong> <?php _e('Always back up your wp-config.php before making changes.', 'chainsaw-plugin'); ?></p>
    </div>
    <?php
}
    
    /**
     * Add admin menu item under Settings
     */
    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            __('Chainsaw Settings', 'chainsaw-plugin'),
            __('Chainsaw', 'chainsaw-plugin'),
            'manage_options',
            'chainsaw-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('chainsaw_settings');
                do_settings_sections('chainsaw-settings');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_chainsaw-settings') {
            return;
        }
        
        wp_enqueue_style(
            'chainsaw-plugin-admin',
            CHAINSAW_URL . 'assets/css/admin.css',
            array(),
            CHAINSAW_VERSION
        );
        
        wp_enqueue_script(
            'chainsaw-plugin-admin',
            CHAINSAW_URL . 'assets/js/admin.js',
            array('jquery'),
            CHAINSAW_VERSION,
            true
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field(trim($input['api_key']));
        }
        
        return $sanitized;
    }
}

// Initialize the plugin
if (class_exists('Chainsaw')) {
    $chainsaw = new Chainsaw();
    $chainsaw->init();
}