<?php
/**
 * Plugin Name: MapPinner - Interactive Image Hotspots
 * Plugin URI: https://example.com/mappinner
 * Description: Create interactive image maps with customizable hotspots, tooltips, and links.
 * Version: 0.4.0
 * Author: Madu
 * Author URI: https://example.com
 * Text Domain: mappinner
 * Domain Path: /languages
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MAPPINNER_VERSION', '1.0.0');
define('MAPPINNER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAPPINNER_PLUGIN_URL', plugin_dir_url(__FILE__));

class MapPinner {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize plugin
        add_action('init', array($this, 'init'));
        
        // Add menu and admin pages
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        
        // Register shortcode
        add_shortcode('image_map', array($this, 'render_shortcode'));
        
        // Register AJAX handlers
        add_action('wp_ajax_mappinner_save_map', array($this, 'ajax_save_map'));
        add_action('wp_ajax_mappinner_get_map', array($this, 'ajax_get_map'));
        add_action('wp_ajax_mappinner_delete_map', array($this, 'ajax_delete_map'));
        add_action('wp_ajax_mappinner_get_maps', array($this, 'ajax_get_maps'));
    }

    public function init() {
        load_plugin_textdomain('mappinner', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public static function activate() {
        // Add capabilities
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('manage_options');
        }
        
        // Create database tables
        self::create_tables();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    private static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mappinner_maps';
        
        // Check if table exists first
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // Create the table
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                title varchar(255) NOT NULL,
                image_url text NOT NULL,
                hotspots longtext,
                created_at datetime DEFAULT NULL,
                updated_at datetime DEFAULT NULL,
                PRIMARY KEY  (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            // Try to create the table
            if (!function_exists('dbDelta')) {
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            }
            
            dbDelta($sql);
            
            // Verify table was created
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                // If dbDelta failed, try direct query
                $wpdb->query($sql);
            }
            
            // Add timestamp triggers if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                // Add trigger for created_at
                $wpdb->query("
                    CREATE TRIGGER IF NOT EXISTS {$table_name}_created 
                    BEFORE INSERT ON {$table_name}
                    FOR EACH ROW
                    SET NEW.created_at = NOW(), NEW.updated_at = NOW()
                ");
                
                // Add trigger for updated_at
                $wpdb->query("
                    CREATE TRIGGER IF NOT EXISTS {$table_name}_updated 
                    BEFORE UPDATE ON {$table_name}
                    FOR EACH ROW
                    SET NEW.updated_at = NOW()
                ");
            }
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Image Maps', 'mappinner'),
            __('Image Maps', 'mappinner'),
            'manage_options',
            'mappinner',
            array($this, 'render_admin_page'),
            'dashicons-location-alt'
        );

        add_submenu_page(
            'mappinner',
            __('All Maps', 'mappinner'),
            __('All Maps', 'mappinner'),
            'manage_options',
            'mappinner',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'mappinner',
            __('Add New Map', 'mappinner'),
            __('Add New', 'mappinner'),
            'manage_options',
            'mappinner-new',
            array($this, 'render_new_map_page')
        );
    }

    public function admin_enqueue_scripts($hook) {
        if (!strpos($hook, 'mappinner')) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-droppable');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');

        wp_enqueue_style(
            'mappinner-admin',
            MAPPINNER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MAPPINNER_VERSION
        );

        wp_enqueue_script(
            'mappinner-admin',
            MAPPINNER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-core', 'jquery-ui-draggable', 'jquery-ui-dialog'),
            MAPPINNER_VERSION,
            true
        );

        wp_localize_script('mappinner-admin', 'mappinnerAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mappinner_nonce'),
            'strings' => array(
                'select_image' => __('Select Image', 'mappinner'),
                'use_image' => __('Use this image', 'mappinner'),
                'save_error' => __('Failed to save map', 'mappinner'),
                'delete_confirm' => __('Are you sure you want to delete this hotspot?', 'mappinner')
            )
        ));
    }

    public function frontend_enqueue_scripts() {
        wp_enqueue_style(
            'mappinner',
            MAPPINNER_PLUGIN_URL . 'assets/css/image-map-hotspots.css',
            array(),
            MAPPINNER_VERSION
        );

        wp_enqueue_script(
            'mappinner',
            MAPPINNER_PLUGIN_URL . 'assets/js/image-map-hotspots.js',
            array('jquery'),
            MAPPINNER_VERSION,
            true
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        include MAPPINNER_PLUGIN_DIR . 'templates/admin-page.php';
    }

    public function render_new_map_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        include MAPPINNER_PLUGIN_DIR . 'templates/new-map-page.php';
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
            'image' => '',
            'hotspots' => '[]'
        ), $atts, 'image_map');

        ob_start();
        include MAPPINNER_PLUGIN_DIR . 'templates/shortcode.php';
        return ob_get_clean();
    }

    public function ajax_save_map() {
        check_ajax_referer('mappinner_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'mappinner')));
        }

        $map_id = isset($_POST['map_id']) ? intval($_POST['map_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $image_url = isset($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : '';
        $hotspots = isset($_POST['hotspots']) ? stripslashes($_POST['hotspots']) : '[]';

        if (empty($title) || empty($image_url)) {
            wp_send_json_error(array('message' => __('Title and image are required.', 'mappinner')));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mappinner_maps';
        
        $data = array(
            'title' => $title,
            'image_url' => $image_url,
            'hotspots' => $hotspots
        );
        
        $format = array('%s', '%s', '%s');

        if ($map_id > 0) {
            // Update existing map
            $result = $wpdb->update($table_name, $data, array('id' => $map_id), $format, array('%d'));
        } else {
            // Insert new map
            $result = $wpdb->insert($table_name, $data, $format);
            $map_id = $wpdb->insert_id;
        }

        if ($result === false) {
            wp_send_json_error(array(
                'message' => __('Database error: ', 'mappinner') . $wpdb->last_error
            ));
        }

        wp_send_json_success(array(
            'map_id' => $map_id,
            'message' => __('Map saved successfully.', 'mappinner')
        ));
    }

    public function ajax_get_map() {
        check_ajax_referer('mappinner_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'mappinner')));
        }

        $map_id = isset($_POST['map_id']) ? intval($_POST['map_id']) : 0;
        if (!$map_id) {
            wp_send_json_error(array('message' => __('Invalid map ID.', 'mappinner')));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mappinner_maps';
        $map = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $map_id));

        if (!$map) {
            wp_send_json_error(array('message' => __('Map not found.', 'mappinner')));
        }

        wp_send_json_success($map);
    }

    public function ajax_delete_map() {
        check_ajax_referer('mappinner_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'mappinner')));
        }

        $map_id = isset($_POST['map_id']) ? intval($_POST['map_id']) : 0;
        if (!$map_id) {
            wp_send_json_error(array('message' => __('Invalid map ID.', 'mappinner')));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mappinner_maps';
        $result = $wpdb->delete($table_name, array('id' => $map_id), array('%d'));

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to delete map.', 'mappinner')));
        }

        wp_send_json_success(array('message' => __('Map deleted successfully.', 'mappinner')));
    }

    public function ajax_get_maps() {
        check_ajax_referer('mappinner_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'mappinner')));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mappinner_maps';
        $maps = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

        wp_send_json_success($maps);
    }
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('MapPinner', 'activate'));
register_deactivation_hook(__FILE__, array('MapPinner', 'deactivate'));

// Initialize the plugin
function mappinner_init() {
    return MapPinner::get_instance();
}
add_action('plugins_loaded', 'mappinner_init');