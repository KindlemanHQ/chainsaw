<?php
/**
 * Plugin Name: Chainsaw
 * Plugin URI: https://kindleman.com.au/chainsaw
 * Description: Wordpress Logging and debug.
 * Version: 1.0.1
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
define('CHAINSAW_VERSION', '1.0.2');
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
        add_action('admin_post_chainsaw_toggle_wp_debug_log', array($this, 'handle_toggle_wp_debug_log'));

        // Plugin activity tracking hooks
        add_action('activated_plugin', array($this, 'log_plugin_activated'), 10, 2);
        add_action('deactivated_plugin', array($this, 'log_plugin_deactivated'), 10, 2);
        add_action('deleted_plugin', array($this, 'log_plugin_deleted'), 10, 2);
        add_action('upgrader_process_complete', array($this, 'log_plugin_installed'), 10, 2);

        // Ensure activity table exists on upgrade
        if (get_option('chainsaw_db_version') !== CHAINSAW_VERSION) {
            self::create_activity_table();
            update_option('chainsaw_db_version', CHAINSAW_VERSION);
        }
    }

    /**
     * Create the plugin activity log table
     */
    public static function create_activity_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'chainsaw_plugin_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            action varchar(20) NOT NULL,
            plugin_slug varchar(255) NOT NULL,
            plugin_name varchar(255) NOT NULL DEFAULT '',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    private function log_plugin_event($action, $plugin_file) {
        global $wpdb;

        $plugin_data = array();
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        if (file_exists($plugin_path)) {
            $plugin_data = get_plugin_data($plugin_path, false, false);
        }

        $wpdb->insert(
            $wpdb->prefix . 'chainsaw_plugin_log',
            array(
                'action'      => $action,
                'plugin_slug' => sanitize_text_field($plugin_file),
                'plugin_name' => isset($plugin_data['Name']) ? sanitize_text_field($plugin_data['Name']) : basename(dirname($plugin_file)),
                'user_id'     => get_current_user_id(),
                'created_at'  => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );
    }

    public function log_plugin_activated($plugin, $network_wide) {
        $this->log_plugin_event('activated', $plugin);
    }

    public function log_plugin_deactivated($plugin, $network_wide) {
        $this->log_plugin_event('deactivated', $plugin);
    }

    public function log_plugin_deleted($plugin, $deleted) {
        if ($deleted) {
            $this->log_plugin_event('deleted', $plugin);
        }
    }

    public function log_plugin_installed($upgrader, $options) {
        if (!isset($options['type']) || !isset($options['action'])) {
            return;
        }

        if ($options['type'] === 'core' && $options['action'] === 'update') {
            global $wp_version;
            $this->log_plugin_event('core_updated', 'wordpress-core');
            return;
        }

        if ($options['type'] !== 'plugin') {
            return;
        }

        if ($options['action'] === 'install') {
            $plugin_slug = isset($upgrader->result['destination_name']) ? $upgrader->result['destination_name'] : '';
            if ($plugin_slug) {
                $this->log_plugin_event('installed', $plugin_slug);
            }
        } elseif ($options['action'] === 'update') {
            if (isset($options['plugins']) && is_array($options['plugins'])) {
                foreach ($options['plugins'] as $plugin_file) {
                    $this->log_plugin_event('updated', $plugin_file);
                }
            } elseif (isset($options['plugin'])) {
                $this->log_plugin_event('updated', $options['plugin']);
            }
        }
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
        // add_settings_section(
        //     'chainsaw_api_section',
        //     __('API Settings', 'chainsaw-plugin'),
        //     array($this, 'api_section_callback'),
        //     'chainsaw-settings'
        // );

        // Add API Key field
        // add_settings_field(
        //     'chainsaw_api_key',
        //     __('API Key', 'chainsaw-plugin'),
        //     array($this, 'api_key_field_callback'),
        //     'chainsaw-settings',
        //     'chainsaw_api_section'
        // );

        // Add Developer Settings section
        add_settings_section(
            'chainsaw_developer_section',
            __('Developer Settings', 'chainsaw-plugin'),
            array($this, 'developer_section_callback'),
            'chainsaw-settings'
        );

         // Add debug.log size field
        add_settings_field(
            'debug_log_max_size',
            __('Max debug.log Size', 'chainsaw-plugin'),
            array($this, 'debug_log_size_callback'),
            'chainsaw-settings',
            'chainsaw_developer_section'
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


    
    // /**
    //  * API Section callback
    //  */
    // public function api_section_callback() {
    //     echo '<p>' . __('Configure your API connection settings.', 'chainsaw-plugin') . '</p>';
    // }



    public function debug_section() {
      echo '<p>' . __('These WordPress debug settings affect how errors and logs are handled.', 'chainsaw-plugin') . '</p>';
      echo '<p>' . __('Recommended production settings: WP_DEBUG=false, WP_DEBUG_DISPLAY=false, WP_DEBUG_LOG=false', 'chainsaw-plugin') . '</p>';
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

    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';
    ?>
    <div class="wrap">
        <h1>Chainsaw</h1>
        <nav class="nav-tab-wrapper">
            <a href="<?php echo esc_url(add_query_arg('tab', 'settings', remove_query_arg('paged'))); ?>" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
            <a href="<?php echo esc_url(add_query_arg('tab', 'activity', remove_query_arg('paged'))); ?>" class="nav-tab <?php echo $active_tab === 'activity' ? 'nav-tab-active' : ''; ?>">Activity</a>
        </nav>

        <?php if ($active_tab === 'settings') : ?>
            <?php $this->debug_section() ?>
            <form action="options.php" method="post">
                <?php
                settings_fields('chainsaw_settings');
                do_settings_sections('chainsaw-settings');
                submit_button('Save Settings');
                ?>
            </form>
            <?php $this->debug_log_section_callback(); ?>
        <?php else : ?>
            <?php $this->render_activity_log(); ?>
        <?php endif; ?>
    </div>
    <?php
}

    private function render_activity_log() {
        global $wpdb;
        $table = $wpdb->prefix . 'chainsaw_plugin_log';

        // Check if the table exists yet
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            echo '<div class="notice notice-warning"><p>';
            _e('Activity log table has not been created yet. Deactivate and reactivate Chainsaw to create it.', 'chainsaw-plugin');
            echo '</p></div>';
            return;
        }

        $per_page = 30;
        $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        $action_labels = array(
            'activated'   => '🟢 Activated',
            'deactivated' => '🔴 Deactivated',
            'installed'   => '📥 Installed',
            'deleted'      => '🗑️ Deleted',
            'updated'      => '🔄 Updated',
            'core_updated' => '⬆️ Core Updated',
        );

        ?>
        <h2><?php _e('Plugin Activity Log', 'chainsaw-plugin'); ?></h2>

        <?php if (empty($rows)) : ?>
            <p><?php _e('No plugin activity recorded yet.', 'chainsaw-plugin'); ?></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'chainsaw-plugin'); ?></th>
                        <th><?php _e('Action', 'chainsaw-plugin'); ?></th>
                        <th><?php _e('Plugin', 'chainsaw-plugin'); ?></th>
                        <th><?php _e('User', 'chainsaw-plugin'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) :
                        $user = get_userdata($row->user_id);
                        $display_name = $user ? esc_html($user->display_name) : __('Unknown', 'chainsaw-plugin');
                        $label = isset($action_labels[$row->action]) ? $action_labels[$row->action] : esc_html($row->action);
                    ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row->created_at))); ?></td>
                            <td><?php echo $label; ?></td>
                            <td>
                                <strong><?php echo esc_html($row->plugin_name); ?></strong>
                                <br><small><?php echo esc_html($row->plugin_slug); ?></small>
                            </td>
                            <td><?php echo $display_name; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            $total_pages = ceil($total / $per_page);
            if ($total_pages > 1) {
                $page_links = paginate_links(array(
                    'base'    => add_query_arg('paged', '%#%'),
                    'format'  => '',
                    'current' => $current_page,
                    'total'   => $total_pages,
                ));
                if ($page_links) {
                    echo '<div class="tablenav"><div class="tablenav-pages">' . $page_links . '</div></div>';
                }
            }
            ?>
        <?php endif; ?>
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
    // Show debug.log cleanup notice
    $cleanup_info = get_option('chainsaw_last_cleanup');
    if ($cleanup_info && (time() - strtotime($cleanup_info['time']) <= 3600)) {
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

    // Show WP_DEBUG_LOG toggle notice if cookie is set
    if (!empty($_COOKIE['chainsaw_notice'])) {
        $notice = sanitize_text_field($_COOKIE['chainsaw_notice']);
        $type = !empty($_COOKIE['chainsaw_notice_type']) ? sanitize_text_field($_COOKIE['chainsaw_notice_type']) : 'updated';
        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
            <p><?php echo esc_html($notice); ?></p>
        </div>
        <?php
        // Clear cookies after displaying
        setcookie('chainsaw_notice', '', time() - 3600, '/');
        setcookie('chainsaw_notice_type', '', time() - 3600, '/');
        // Also unset from $_COOKIE for immediate effect
        unset($_COOKIE['chainsaw_notice']);
        unset($_COOKIE['chainsaw_notice_type']);
    }
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

     public function handle_toggle_wp_debug_log() {
            echo "whyyyyy?";
        // Error logging for debugging
        if (!current_user_can('manage_options')) {
            error_log('Chainsaw: insufficient permissions');
            wp_die(__('Insufficient permissions.', 'chainsaw-plugin'));
        }
        if (!isset($_POST['desired_state'])) {
            error_log('Chainsaw: desired_state not set');
            wp_die(__('Invalid request.', 'chainsaw-plugin'));
        }
    
       
        // Nonce check (returns void, dies on failure)
        check_admin_referer('chainsaw_toggle_wp_debug_log');

        $desired = $_POST['desired_state'] === '1';
        $result = $this->update_wp_config_constant('WP_DEBUG_LOG', $desired ? 'true' : 'false');
        if ($result === true) {
            $notice = sprintf(__('WP_DEBUG_LOG set to %s. If you do not see the change below, please refresh the page.', 'chainsaw-plugin'), $desired ? 'true' : 'false');
            $type = 'success';
        } else {
            error_log('Chainsaw: update_wp_config_constant failed: ' . print_r($result, true));
            $notice = sprintf(__('Failed to update WP_DEBUG_LOG: %s', 'chainsaw-plugin'), $result);
            $type = 'error';
        }
        // Set session cookie for notice
        setcookie('chainsaw_notice', $notice, 0, '/');
        setcookie('chainsaw_notice_type', $type, 0, '/');
        // Redirect to clean settings page
        if (!headers_sent()) {
            wp_redirect(add_query_arg(array('page' => 'chainsaw-settings'), admin_url('options-general.php')));
            exit;
        } else {
            error_log('Chainsaw: headers already sent before redirect');
            echo '<script>window.location = "' . esc_url(add_query_arg(array('page' => 'chainsaw-settings'), admin_url('options-general.php'))) . '";</script>';
            exit;
        }
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

// Activation hook — create DB table
register_activation_hook(__FILE__, array('Chainsaw', 'create_activity_table'));

// Initialize the plugin
if (class_exists('Chainsaw')) {
    $chainsaw = new Chainsaw();
    $chainsaw->init();
}