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

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Register activation hook
function image_map_hotspots_activate() {
    // Initialize plugin options if they dont exist
    if (!get_option('image_map_hotspots_data')) {
        add_option('image_map_hotspots_data', array());
    }
}
register_activation_hook(__FILE__, 'image_map_hotspots_activate');

// Enqueue admin scripts and styles
function image_map_hotspots_admin_enqueue($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'image-map-hotspots') === false) {
        return;
    }

    // Enqueue WordPress media scripts
    wp_enqueue_media();
    
    // Admin styles
    wp_enqueue_style(
        'image-map-hotspots-admin',
        plugins_url('assets/css/admin.css', __FILE__),
        array(),
        '1.0.0'
    );
    
    // Admin scripts
    wp_enqueue_script(
        'image-map-hotspots-admin',
        plugins_url('assets/js/admin.js', __FILE__),
        array('jquery', 'jquery-ui-draggable'),
        '1.0.0',
        true
    );

    // Localize script
    wp_localize_script('image-map-hotspots-admin', 'imageMapHotspots', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('image_map_hotspots_nonce')
    ));
    
    // Add strings for media uploader
    wp_localize_script('image-map-hotspots-admin', 'mappinnerAdmin', array(
        'strings' => array(
            'select_image' => __('Select Image', 'mappinner'),
            'use_image' => __('Use This Image', 'mappinner'),
            'delete_confirm' => __('Are you sure you want to delete this hotspot?', 'mappinner'),
            'save_error' => __('Error saving map.', 'mappinner')
        )
    ));
}
add_action('admin_enqueue_scripts', 'image_map_hotspots_admin_enqueue');

// Enqueue frontend scripts and styles
function image_map_hotspots_enqueue() {
    wp_enqueue_style(
        'image-map-hotspots',
        plugins_url('assets/css/image-map-hotspots.css', __FILE__),
        array(),
        '1.0.0'
    );
    
    wp_enqueue_script(
        'image-map-hotspots',
        plugins_url('assets/js/image-map-hotspots.js', __FILE__),
        array('jquery'),
        '1.0.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'image_map_hotspots_enqueue');

// Add admin menu
function image_map_hotspots_admin_menu() {
    // Main menu page
    $parent_slug = add_menu_page(
        'Image Map Hotspots', // Page title
        'Image Maps',         // Menu title
        'manage_options',     // Capability
        'image-map-hotspots', // Menu slug
        'image_map_hotspots_admin_page', // Function
        'dashicons-location-alt', // Icon
        30 // Position
    );

    // Add submenu page
    $sub_page = add_submenu_page(
        'image-map-hotspots', // Parent slug
        'Add New Image Map',  // Page title
        'Add New',           // Menu title
        'manage_options',     // Capability
        'image-map-hotspots-new', // Menu slug
        'image_map_hotspots_new_page' // Function
    );

    // Add action to load scripts
    add_action('load-' . $parent_slug, 'image_map_hotspots_admin_page_load');
    add_action('load-' . $sub_page, 'image_map_hotspots_admin_page_load');
}
add_action('admin_menu', 'image_map_hotspots_admin_menu');

// Page load action
function image_map_hotspots_admin_page_load() {
    // Add screen options and help tabs if needed
    add_action('admin_enqueue_scripts', 'image_map_hotspots_admin_enqueue');
}

// Initialize plugin
function image_map_hotspots_init() {
    // Handle form submissions
    if (isset($_POST['action']) && $_POST['action'] === 'save_image_map') {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        check_admin_referer('save_image_map', 'image_map_nonce');
        
        $map_id = isset($_POST['map_id']) ? sanitize_text_field($_POST['map_id']) : 'map_' . uniqid();
        $map_title = isset($_POST['map_title']) ? sanitize_text_field($_POST['map_title']) : '';
        $map_image_id = isset($_POST['map_image_id']) ? intval($_POST['map_image_id']) : 0;
        $hotspots = isset($_POST['hotspots_data']) ? json_decode(stripslashes($_POST['hotspots_data']), true) : array();
        
        if (!$map_title || !$map_image_id) {
            wp_redirect(add_query_arg('error', 'missing_fields', wp_get_referer()));
            exit;
        }

        $image_maps = get_option('image_map_hotspots_data', array());
        $image_maps[$map_id] = array(
            'title' => $map_title,
            'image_id' => $map_image_id,
            'hotspots' => $hotspots
        );
        
        update_option('image_map_hotspots_data', $image_maps);
        
        wp_redirect(add_query_arg('updated', 'true', admin_url('admin.php?page=image-map-hotspots')));
        exit;
    }
}
add_action('admin_init', 'image_map_hotspots_init');

// Admin main page
function image_map_hotspots_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    $image_maps = get_option('image_map_hotspots_data', array());
    include plugin_dir_path(__FILE__) . 'templates/admin-page.php';
}

// Admin new/edit page
function image_map_hotspots_new_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    $editing = false;
    $map_id = '';
    $map_title = '';
    $map_image_id = '';
    $map_image_url = '';
    $hotspots = array();
    
    if (isset($_GET['id'])) {
        $image_maps = get_option('image_map_hotspots_data', array());
        $map_id = sanitize_text_field($_GET['id']);
        
        if (isset($image_maps[$map_id])) {
            $editing = true;
            $map_data = $image_maps[$map_id];
            $map_title = $map_data['title'];
            $map_image_id = $map_data['image_id'];
            $map_image_url = wp_get_attachment_url($map_image_id);
            $hotspots = isset($map_data['hotspots']) ? $map_data['hotspots'] : array();
        }
    }
    
    include plugin_dir_path(__FILE__) . 'templates/new-map-page.php';
}

// Delete image map
function image_map_hotspots_delete() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $map_id = sanitize_text_field($_GET['id']);
        check_admin_referer('delete_image_map_' . $map_id);
        
        $image_maps = get_option('image_map_hotspots_data', array());
        if (isset($image_maps[$map_id])) {
            unset($image_maps[$map_id]);
            update_option('image_map_hotspots_data', $image_maps);
        }
        
        wp_redirect(add_query_arg('deleted', 'true', admin_url('admin.php?page=image-map-hotspots')));
        exit;
    }
}
add_action('admin_init', 'image_map_hotspots_delete');

// AJAX handler for saving maps
function image_map_hotspots_save_map() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'image_map_hotspots_nonce')) {
        error_log('Security check failed');
        
        // If this is a form submission, redirect back with error
        if (!wp_doing_ajax()) {
            wp_redirect(admin_url('admin.php?page=image-map-hotspots&error=security'));
            exit;
        }
        
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        error_log('Permission check failed');
        
        // If this is a form submission, redirect back with error
        if (!wp_doing_ajax()) {
            wp_redirect(admin_url('admin.php?page=image-map-hotspots&error=permission'));
            exit;
        }
        
        wp_send_json_error(array('message' => 'You do not have permission to perform this action'));
        return;
    }

    // Get and sanitize data
    $map_id = isset($_POST['map_id']) && !empty($_POST['map_id']) ? sanitize_text_field($_POST['map_id']) : 'map_' . substr(uniqid(), -6);
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $image_url = isset($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : '';
    $hotspots = isset($_POST['hotspots']) ? wp_unslash($_POST['hotspots']) : '[]';

    // Only log if WP_DEBUG is defined and true
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Saving map: ' . $map_id);
        error_log('Title: ' . $title);
        error_log('Image URL: ' . $image_url);
        error_log('Hotspots: ' . substr($hotspots, 0, 100) . '...');
        error_log('Hotspots length: ' . strlen($hotspots));
    }

    // Validate data
    if (empty($title) || empty($image_url)) {
        error_log('Title or image URL is empty');
        
        // If this is a form submission, redirect back with error
        if (!wp_doing_ajax()) {
            wp_redirect(admin_url('admin.php?page=image-map-hotspots&error=missing_data'));
            exit;
        }
        
        wp_send_json_error(array('message' => 'Title and image are required'));
        return;
    }

    // Get attachment ID from URL
    $image_id = attachment_url_to_postid($image_url);
    if (!$image_id) {
        error_log('Could not get attachment ID from URL: ' . $image_url);
        $image_id = 0;
    }
    
    // Remove "-scaled" from the image URL if present
    $image_url = str_replace('-scaled.', '.', $image_url);

    // Parse hotspots
    $hotspots_array = json_decode($hotspots, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON decode error: ' . json_last_error_msg());
        error_log('Hotspots JSON: ' . $hotspots);
        $hotspots_array = array();
    }
    
    error_log('Hotspots count: ' . count($hotspots_array));
    error_log('Hotspots array: ' . print_r($hotspots_array, true));

    // Save map data
    $image_maps = get_option('image_map_hotspots_data', array());
    
    // Create or update the map
    $image_maps[$map_id] = array(
        'title' => $title,
        'image_id' => $image_id,
        'image_url' => $image_url, // Store the URL as well in case the attachment ID is not found
        'hotspots' => $hotspots_array,
        'created_at' => current_time('mysql')
    );
    
    $result = update_option('image_map_hotspots_data', $image_maps);
    error_log('Update option result: ' . ($result ? 'true' : 'false'));
    
    // Verify the data was saved correctly
    $saved_data = get_option('image_map_hotspots_data');
    if (isset($saved_data[$map_id])) {
        $saved_hotspots = $saved_data[$map_id]['hotspots'];
        $hotspots_count = is_array($saved_hotspots) ? count($saved_hotspots) : 0;
        error_log('Map saved successfully with ' . $hotspots_count . ' hotspots');
        error_log('Saved hotspots: ' . print_r($saved_hotspots, true));
    } else {
        error_log('Map not found in saved data');
    }
    
    // If this is a form submission, redirect to the maps list
    if (!wp_doing_ajax()) {
        wp_redirect(admin_url('admin.php?page=image-map-hotspots&message=saved'));
        exit;
    }
    
    // Otherwise, send JSON response for AJAX
    wp_send_json_success(array(
        'message' => 'Map saved successfully',
        'map_id' => $map_id,
        'hotspots_count' => count($hotspots_array)
    ));
}
add_action('wp_ajax_mappinner_save_map', 'image_map_hotspots_save_map');

// Debug function to dump options
function image_map_hotspots_debug() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied'));
        return;
    }
    
    $image_maps = get_option('image_map_hotspots_data', array());
    wp_send_json_success(array(
        'maps' => $image_maps
    ));
}
add_action('wp_ajax_mappinner_debug', 'image_map_hotspots_debug');

// AJAX handler for getting map data
function image_map_hotspots_get_map() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'image_map_hotspots_nonce')) {
        error_log('Get map: Security check failed');
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        error_log('Get map: Permission check failed');
        wp_send_json_error(array('message' => 'You do not have permission to perform this action'));
        return;
    }

    // Get map ID
    $map_id = isset($_POST['map_id']) ? sanitize_text_field($_POST['map_id']) : '';
    if (empty($map_id)) {
        error_log('Get map: Map ID is required');
        wp_send_json_error(array('message' => 'Map ID is required'));
        return;
    }

    error_log('Getting map data for ID: ' . $map_id);

    // Get map data
    $image_maps = get_option('image_map_hotspots_data', array());
    error_log('All maps: ' . print_r(array_keys($image_maps), true));
    
    if (!isset($image_maps[$map_id])) {
        error_log('Get map: Map not found with ID: ' . $map_id);
        wp_send_json_error(array('message' => 'Map not found'));
        return;
    }

    $map_data = $image_maps[$map_id];
    error_log('Map data found: ' . print_r($map_data, true));
    
    // Check if hotspots exist
    if (!isset($map_data['hotspots']) || !is_array($map_data['hotspots'])) {
        error_log('Get map: No hotspots found or invalid format');
        $map_data['hotspots'] = array();
    } else {
        error_log('Get map: Found ' . count($map_data['hotspots']) . ' hotspots');
        error_log('Hotspots data: ' . print_r($map_data['hotspots'], true));
    }
    
    // Ensure we have a valid image URL
    $image_url = wp_get_attachment_url($map_data['image_id']);
    if (empty($image_url)) {
        error_log('Get map: Image URL is empty for ID: ' . $map_data['image_id']);
        // Try to get the URL from the map data if available
        if (isset($map_data['image_url'])) {
            $image_url = $map_data['image_url'];
            error_log('Get map: Using image URL from map data: ' . $image_url);
        }
    }
    
    $response = array(
        'title' => $map_data['title'],
        'image_url' => $image_url,
        'hotspots' => json_encode($map_data['hotspots'])
    );
    
    error_log('Get map response: ' . print_r($response, true));
    wp_send_json_success($response);
}
add_action('wp_ajax_mappinner_get_map', 'image_map_hotspots_get_map');

// AJAX handler for deleting maps
function image_map_hotspots_delete_map() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mappinner_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action'));
    }

    // Get map ID
    $map_id = isset($_POST['map_id']) ? sanitize_text_field($_POST['map_id']) : '';
    if (empty($map_id)) {
        wp_send_json_error(array('message' => 'Map ID is required'));
    }

    // Delete map data
    $image_maps = get_option('image_map_hotspots_data', array());
    if (isset($image_maps[$map_id])) {
        unset($image_maps[$map_id]);
        update_option('image_map_hotspots_data', $image_maps);
        wp_send_json_success(array('message' => 'Map deleted successfully'));
    } else {
        wp_send_json_error(array('message' => 'Map not found'));
    }
}
add_action('wp_ajax_mappinner_delete_map', 'image_map_hotspots_delete_map');

// Shortcode handler
function image_map_hotspots_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => '',
    ), $atts);
    
    if (empty($atts['id'])) {
        error_log('Shortcode: Empty ID');
        return '<p>Error: Image map ID is required.</p>';
    }
    
    $image_maps = get_option('image_map_hotspots_data', array());
    if (!isset($image_maps[$atts['id']])) {
        error_log('Shortcode: Map not found with ID: ' . $atts['id']);
        return '<p>Error: Image map not found.</p>';
    }
    
    $map_data = $image_maps[$atts['id']];
    $map_title = $map_data['title'];
    
    // Get image URL
    if (isset($map_data['image_url'])) {
        // Use stored image URL if available
        $image_url = $map_data['image_url'];
    } else {
        // Otherwise get from attachment ID
        $image_url = wp_get_attachment_url($map_data['image_id']);
        // Remove "-scaled" from the image URL if present
        $image_url = str_replace('-scaled.', '.', $image_url);
    }
    
    if (empty($image_url)) {
        error_log('Shortcode: Image URL is empty for map: ' . $atts['id']);
        return '<p>Error: Image not found.</p>';
    }
    
    $hotspots = isset($map_data['hotspots']) ? $map_data['hotspots'] : array();
    error_log('Shortcode: Found ' . count($hotspots) . ' hotspots for map: ' . $atts['id']);
    
    // Ensure frontend scripts and styles are enqueued
    wp_enqueue_style('image-map-hotspots');
    wp_enqueue_script('image-map-hotspots');
    
    // Set up variables for the template
    $map_id = 'map_' . $atts['id'];
    
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/shortcode.php';
    $output = ob_get_clean();
    
    return $output;
}
add_shortcode('image_map', 'image_map_hotspots_shortcode');
