<?php
/**
 * Plugin Name: Chainsaw
 * Plugin URI: https://kindleman.com.au/chainsaw
 * Description: Wordpress Logging and debug.
 * Version: 1.0.0
 * Author: Kindleman 
 * Author URI: https://kindleman.com.au
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

        

        add_action('init', array($this, 'maybe_cleanup_debug_log'), 9999);

         add_action('admin_notices', array($this, 'admin_notices'));

    // Handle WP_DEBUG_LOG toggle submissions
    add_action('admin_post_chainsaw_toggle_wp_debug_log', array($this, 'handle_toggle_wp_debug_log'));
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
    
   


    // Add Debug Settings section
    add_settings_section(
        'chainsaw_debug_section',
        __('Debug Configuration', 'chainsaw-plugin'),
        array($this, 'debug_section_callback'),
        'chainsaw-settings'
    );

    // Add debug.log size field
    add_settings_field(
        'debug_log_max_size',
        __('Max debug.log Size', 'chainsaw-plugin'),
        array($this, 'debug_log_size_callback'),
        'chainsaw-settings',
        'chainsaw_debug_section'
    );

    // Add API Settings section
    // add_settings_section(
    //     'chainsaw_api_section',
    //     __('API Settings', 'chainsaw-plugin'),
    //     array($this, 'api_section_callback'),
    //     'chainsaw-settings'
    // );

    // Add API Key field
    add_settings_field(
        'chainsaw_api_key',
        __('API Key', 'chainsaw-plugin'),
        array($this, 'api_key_field_callback'),
        'chainsaw-settings',
        'chainsaw_api_section'
    );

    // Add Developer Settings section
    add_settings_section(
        'chainsaw_developer_section',
        __('Developer Settings', 'chainsaw-plugin'),
        array($this, 'developer_section_callback'),
        'chainsaw-settings'
    );

    // Add Developer Email field
    add_settings_field(
        'developer_email',
        __('Developer Email', 'chainsaw-plugin'),
        array($this, 'developer_email_callback'),
        'chainsaw-settings',
        'chainsaw_developer_section'
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
    
    // debug_info_field_callback now shown at top of settings page
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
    <h2 style="margin-top:0;"><?php _e('Debug Configuration', 'chainsaw-plugin'); ?></h2>
    <div class="chainsaw-debug-info-desc" style="margin-bottom:10px;">
        <p><?php _e('These settings are defined in your wp-config.php file. To change them:', 'chainsaw-plugin'); ?></p>
        <ol>
            <li><?php _e('Edit wp-config.php (usually in your WordPress root directory)', 'chainsaw-plugin'); ?></li>
            <li><?php _e('Look for the debug settings (search for "WP_DEBUG")', 'chainsaw-plugin'); ?></li>
            <li><?php _e('Modify the values and save the file', 'chainsaw-plugin'); ?></li>
            <li><?php _e('Refresh this page to see the updated values', 'chainsaw-plugin'); ?></li>
        </ol>
        <p><strong><?php _e('Important:', 'chainsaw-plugin'); ?></strong> <?php _e('Always back up your wp-config.php before making changes.', 'chainsaw-plugin'); ?></p>
    </div>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php _e('Setting', 'chainsaw-plugin'); ?></th>
                <th><?php _e('Current Value', 'chainsaw-plugin'); ?></th>
                <th><?php _e('Recommended', 'chainsaw-plugin'); ?></th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>WP_DEBUG</code></td>
                <td><strong><?php echo $debug_settings['WP_DEBUG'] ? __('true', 'chainsaw-plugin') : __('false', 'chainsaw-plugin'); ?></strong></td>
                <td><?php _e('false on production unless you need output to debug.log', 'chainsaw-plugin'); ?></td>
                <td></td>
            </tr>
            <tr>
                <td><code>WP_DEBUG_DISPLAY</code></td>
                <td><strong><?php echo $debug_settings['WP_DEBUG_DISPLAY'] ? __('true', 'chainsaw-plugin') : __('false', 'chainsaw-plugin'); ?></strong></td>
                <td><?php _e('false on production', 'chainsaw-plugin'); ?></td>
                <td></td>
            </tr>
            <tr>
                <td><code>WP_DEBUG_LOG</code></td>
                <td><strong><?php echo $debug_settings['WP_DEBUG_LOG'] ? __('true', 'chainsaw-plugin') : __('false', 'chainsaw-plugin'); ?></strong></td>
                <td><?php _e('false on production (or true for logging)', 'chainsaw-plugin'); ?></td>
                <td>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <?php wp_nonce_field('chainsaw_toggle_wp_debug_log'); ?>
                        <input type="hidden" name="action" value="chainsaw_toggle_wp_debug_log" />
                        <input type="hidden" name="desired_state" value="<?php echo $debug_settings['WP_DEBUG_LOG'] ? '0' : '1'; ?>" />
                        <button type="submit" class="button button-<?php echo $debug_settings['WP_DEBUG_LOG'] ? 'secondary' : 'primary'; ?>">
                            <?php echo $debug_settings['WP_DEBUG_LOG'] ? __('Disable Logging', 'chainsaw-plugin') : __('Enable Logging', 'chainsaw-plugin'); ?>
                        </button>
                    </form>
                </td>
            </tr>
            <tr>
                <td><code>CORE_UPGRADE_SKIP_NEW_BUNDLED</code></td>
                <td><strong><?php echo $debug_settings['CORE_UPGRADE_SKIP_NEW_BUNDLED'] ? __('true', 'chainsaw-plugin') : __('false', 'chainsaw-plugin'); ?></strong></td>
                <td><?php _e('true to skip adding yearly wp themes during update', 'chainsaw-plugin'); ?></td>
            </tr>
        </tbody>
    </table>
    <p>These WordPress debug settings affect how errors and logs are handled.</p>
    <p>Recommended production settings: WP_DEBUG=false, WP_DEBUG_DISPLAY=false, WP_DEBUG_LOG=false</p>
    
    <!-- Explanatory text moved above table -->
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
        <?php settings_errors(); ?>

        <?php $this->debug_info_field_callback(); ?>

        <form action="options.php" method="post">
            <?php
            settings_fields('chainsaw_settings');
            do_settings_sections('chainsaw-settings');
            submit_button('Save Settings');
            ?>
        </form>

        <?php $this->debug_log_section_callback(); ?>
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
      
      // Sanitize API key
      if (isset($input['api_key'])) {
          $sanitized['api_key'] = sanitize_text_field(trim($input['api_key']));
      }
      
      // Sanitize debug log size
      if (isset($input['debug_log_max_size'])) {
          $allowed_sizes = array('1kb', '1mb', '1gb');
          $sanitized['debug_log_max_size'] = in_array($input['debug_log_max_size'], $allowed_sizes) 
              ? $input['debug_log_max_size'] 
              : '1mb';
      }
      
      // Sanitize developer email
      if (isset($input['developer_email'])) {
          $sanitized['developer_email'] = sanitize_email(trim($input['developer_email']));
      }
      
      return $sanitized;
  }

    /**
     * Debug Log Size field callback
     */
    public function debug_log_size_callback() {
        $options = get_option('chainsaw_options');
        $current_size = isset($options['debug_log_max_size']) ? $options['debug_log_max_size'] : '1mb';
        ?>
        <select id="debug_log_max_size" name="chainsaw_options[debug_log_max_size]" class="regular-text">
            <option value="1kb" <?php selected($current_size, '1kb'); ?>><?php _e('1 KB', 'chainsaw-plugin'); ?></option>
            <option value="1mb" <?php selected($current_size, '1mb'); ?>><?php _e('1 MB', 'chainsaw-plugin'); ?></option>
            <option value="1gb" <?php selected($current_size, '1gb'); ?>><?php _e('1 GB', 'chainsaw-plugin'); ?></option>
        </select>
        <p class="description">
            <?php _e('Maximum allowed size for debug.log file before rotation.', 'chainsaw-plugin'); ?>
        </p>
        <?php
    }
    /**
     * Developer Section callback
     */
    public function developer_section_callback() {
        echo '<p>' . __('Configure developer notifications and alerts.', 'chainsaw-plugin') . '</p>';
    }

    /**
   * Developer Email field callback
   */
  public function developer_email_callback() {
      $options = get_option('chainsaw_options');
      $email = isset($options['developer_email']) ? esc_attr($options['developer_email']) : '';
      ?>
      <input type="email" 
            id="developer_email" 
            name="chainsaw_options[developer_email]" 
            value="<?php echo $email; ?>" 
            class="regular-text">
      <p class="description">
          <?php _e('Email address for debug notifications and alerts.', 'chainsaw-plugin'); ?>
      </p>
      <?php
  }


  /**
 * Debug Log Section callback
 */
/**
 * Debug Log Section callback
 */
public function debug_log_section_callback() {
    // Remove from settings sections registration
    // and make it a standalone display function
    ?>
    <h2><?php _e('Debug Log Latest 100 Lines', 'chainsaw-plugin'); ?></h2>    
    
            <?php $this->display_debug_log(); ?>
    
    <p>
            <a href="<?php echo esc_url(add_query_arg('refreshdebuglog', '1')); ?>" 
               class="button button-secondary">
                <?php _e('Refresh Log', 'chainsaw-plugin'); ?>
            </a>
            <span class="spinner" style="float: none; display: none;"></span>
        </p>
    <?php
}

/**
 * Display the debug.log contents
 */
/**
 * Display the debug.log contents with file size info
 */
private function display_debug_log() {
    $log_file = WP_CONTENT_DIR . '/debug.log';
    
    if (!file_exists($log_file)) {
        echo '<div class="notice notice-warning inline"><p>';
        _e('debug.log file not found.', 'chainsaw-plugin');
        echo '</p></div>';
        return;
    }
    
    if (!is_readable($log_file)) {
        echo '<div class="notice notice-error inline"><p>';
        _e('debug.log file is not readable.', 'chainsaw-plugin');
        echo '</p></div>';
        return;
    }

    // Get file size information
    $file_size = filesize($log_file);
    $file_size_human = size_format($file_size);
    $max_size = $this->get_current_max_size();
    $max_size_human = size_format($max_size);
    
    // Show file size info
    printf(
        __('Current size: %1$s | Max size: %2$s', 'chainsaw-plugin'),
        '<strong>' . esc_html($file_size_human) . '</strong>',
        '<strong>' . esc_html($max_size_human) . '</strong>'
    );
    if ($file_size > ($max_size * 0.9)) {
        $percent = round(($file_size / $max_size) * 100);
        echo '<div class="notice notice-warning inline"><p>';
        printf(
            __('Warning: debug.log is %s%% of maximum size!', 'chainsaw-plugin'),
            $percent
        );
        echo '</p></div>';
    }
    echo '<div class="debug-log-info">';
    
    
    // Show warning if approaching limit
    
    echo '</div>';
    ?><div class="debug-log-viewer">        
        <div class="debug-log-content">
    <?php
    // Get last 100 lines
    $lines = $this->tail_file($log_file, 100);
    
    if (empty($lines)) {
        echo '<div class="notice notice-success inline"><p>';
        _e('debug.log is empty.', 'chainsaw-plugin');
        echo '</p></div>';
        return;
    }
    
    echo '<pre>' . esc_html(implode("\n", $lines)) . '</pre>';
    ?>
    </div></div>
    <?php
}

/**
 * Get current max size setting in bytes
 */
private function get_current_max_size() {
    $options = get_option('chainsaw_options');
    return isset($options['debug_log_max_size']) 
        ? $this->convert_size_to_bytes($options['debug_log_max_size'])
        : 1073741824; // Default 1GB
}

/**
 * Get last N lines of a file
 */
private function tail_file($filepath, $lines = 100) {
    // Open file
    $f = @fopen($filepath, 'rb');
    if ($f === false) return false;

    // Sets buffer size
    $buffer = 256;
    
    // Jump to last character
    fseek($f, -1, SEEK_END);
    
    // Read it and adjust line number if necessary
    // (Otherwise the result would be wrong if file doesn't end with a blank line)
    if (fread($f, 1) != "\n") $lines -= 1;
    
    // Start reading
    $output = '';
    $chunk = '';
    
    // While we would like more
    while (ftell($f) > 0 && $lines >= 0) {
        // Figure out how far back we should jump
        $seek = min(ftell($f), $buffer);
        
        // Do the jump (backwards, relative to where we are)
        fseek($f, -$seek, SEEK_CUR);
        
        // Read a chunk and prepend it to our output
        $output = ($chunk = fread($f, $seek)) . $output;
        
        // Jump back to where we started reading
        fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);
        
        // Decrease our line counter
        $lines -= substr_count($chunk, "\n");
    }
    
    // While we have too many lines
    // (Because of buffer size we might have read too many)
    while ($lines++ < 0) {
        // Find first newline and remove all text before that
        $output = substr($output, strpos($output, "\n") + 1);
    }
    
    // Close file and return
    fclose($f);
    return explode("\n", $output);
}

/**
     * Auto-cleanup debug.log when it gets too large
     */
    public function maybe_cleanup_debug_log() {
    
    if (rand(1, 10) > 1) return;

    $debug_log = WP_CONTENT_DIR . '/debug.log';
    $max_size = $this->get_current_max_size();

    if (!file_exists($debug_log)) return;

    $filesize = filesize($debug_log);
    if ($filesize <= $max_size) return;

    // Get last 100 lines
    $tail = $this->get_last_log_lines($debug_log, 100);

    // Store cleanup info before deleting
    $cleanup_info = array(
        'time' => current_time('mysql'),
        'size' => $filesize,
        'last_lines' => $tail
    );
    update_option('chainsaw_last_cleanup', $cleanup_info);

    // Delete the file
    unlink($debug_log);

    // Send email notification
    $this->send_log_cleanup_notification($filesize, $tail);
}

/**
 * Show admin notice when log was recently cleared
 */
public function admin_notices() {
    $cleanup_info = get_option('chainsaw_last_cleanup');
    if (!$cleanup_info || (time() - strtotime($cleanup_info['time']) > 3600)) {
        return;
    }
    
    $size = size_format($cleanup_info['size']);
    ?>
    <div class="notice notice-warning is-dismissible">
        <p>
            <?php printf(
                __('Chainsaw: debug.log was automatically cleared at %1$s. The file had reached %2$s in size.', 'chainsaw-plugin'),
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($cleanup_info['time'])),
                $size
            ); ?>
        </p>
        <details style="margin-top:10px;">
            <summary><?php _e('Show last 10 lines', 'chainsaw-plugin'); ?></summary>
            <pre style="background:#f6f7f7;padding:10px;overflow:auto;"><?php 
                echo esc_html(implode("\n", array_slice(explode("\n", $cleanup_info['last_lines']), -10)));
            ?></pre>
        </details>
    </div>
    <?php
    // Clear the transient after displaying
    delete_option('chainsaw_last_cleanup');
}

    /**
     * Get last N lines from log file efficiently
     */
    private function get_last_log_lines($filepath, $lines = 100) {
        $handle = fopen($filepath, 'rb');
        if (!$handle) return '';
        
        fseek($handle, max(0, filesize($filepath) - 20000)); // Jump ~20KB from end
        $content = stream_get_contents($handle);
        fclose($handle);
        
        $all_lines = explode("\n", $content);
        return implode("\n", array_slice($all_lines, -$lines));
    }

    /**
     * Send email notification about log cleanup
     */
    private function send_log_cleanup_notification($filesize, $last_lines) {
        $email = isset($this->options['developer_email']) 
            ? $this->options['developer_email']
            : get_option('admin_email');
        
        wp_mail(
            $email,
            '🚨 debug.log DELETED on ' . site_url(),
            "File exceeded maximum size (" . size_format($filesize) . ") and was auto-removed.\n\n" .
            "Last 100 lines:\n====================\n" . $last_lines . "\n====================\n" .
            "Action taken: " . date('Y-m-d H:i:s') . "\n" .
            "Next: Fix logging issues or disable WP_DEBUG_LOG."
        );
    }

    /**
     * Convert human-readable size to bytes
     */
    private function convert_size_to_bytes($size_str) {
        switch ($size_str) {
            case '1kb': return 1024;
            case '1mb': return 1048576;
            case '1gb': return 1073741824;
            default: return 1073741824; // Default 1GB
        }
    }

    /**
     * Handle WP_DEBUG_LOG toggle submission
     */
    public function handle_toggle_wp_debug_log() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'chainsaw-plugin'));
        }
        check_admin_referer('chainsaw_toggle_wp_debug_log');

        $desired = isset($_POST['desired_state']) && $_POST['desired_state'] === '1';
        $result = $this->update_wp_config_constant('WP_DEBUG_LOG', $desired ? 'true' : 'false');

        if ($result === true) {
            add_settings_error(
                'chainsaw_wp_debug_log',
                'chainsaw_wp_debug_log_updated',
                sprintf(__('WP_DEBUG_LOG set to %s.', 'chainsaw-plugin'), $desired ? 'true' : 'false'),
                'updated'
            );
        } else {
            add_settings_error(
                'chainsaw_wp_debug_log',
                'chainsaw_wp_debug_log_failed',
                sprintf(__('Failed to update WP_DEBUG_LOG: %s', 'chainsaw-plugin'), $result),
                'error'
            );
        }

        wp_redirect(add_query_arg(array('page' => 'chainsaw-settings'), admin_url('options-general.php')));
        exit;
    }

    /**
     * Update or insert a constant definition in wp-config.php
     * Returns true on success or error string on failure
     */
    private function update_wp_config_constant($constant, $raw_value) {
        // Try ABSPATH, then parent directory
        $config_path = ABSPATH . 'wp-config.php';
        if (!file_exists($config_path)) {
            $config_path = dirname(ABSPATH) . '/wp-config.php';
            if (!file_exists($config_path)) {
                return __('wp-config.php not found.', 'chainsaw-plugin');
            }
        }
        if (!is_writable($config_path)) {
            return __('wp-config.php not writable.', 'chainsaw-plugin');
        }

        $contents = file_get_contents($config_path);
        if ($contents === false) {
            return __('Could not read wp-config.php.', 'chainsaw-plugin');
        }

        // Backup first
        $backup_path = $config_path . '.chainsaw-backup-' . date('Ymd-His');
        if (@file_put_contents($backup_path, $contents) === false) {
            return __('Failed to create backup.', 'chainsaw-plugin');
        }

        // Improved regex: allow whitespace, comments, and both quote types
        $pattern = '/define\s*\(\s*["\\\']' . preg_quote($constant, '/') . '["\\\']\s*,\s*(true|false|\d+|\'[^\']*\'|"[^"]*")\s*\)\s*;/i';
        $replacement = "define('" . $constant . "', " . $raw_value . ");";
        $updated = false;

        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, $replacement, $contents, 1, $count);
            $updated = $count > 0;
        } else {
            // Insert before the line that says /* That's all, stop editing! */ if present
            $marker = "/* That's all, stop editing!";
            $insertion = "\n// Added by Chainsaw plugin on " . date('c') . "\n" . $replacement . "\n";
            $pos = strpos($contents, $marker);
            if ($pos !== false) {
                $contents = substr($contents, 0, $pos) . $insertion . substr($contents, $pos);
                $updated = true;
            } else {
                $contents .= $insertion;
                $updated = true;
            }
        }

        if ($updated) {
            if (@file_put_contents($config_path, $contents) === false) {
                return __('Failed writing wp-config.php.', 'chainsaw-plugin');
            }
            return true;
        }
        return __('No changes made.', 'chainsaw-plugin');
    }

}

// Initialize the plugin
if (class_exists('Chainsaw')) {
    $chainsaw = new Chainsaw();
    $chainsaw->init();
}