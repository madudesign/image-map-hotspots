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

// Enqueue admin scripts and styles
function image_map_hotspots_admin_enqueue() {
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'image-map-hotspots') !== false) {
        wp_enqueue_style(
            'image-map-hotspots-admin',
            plugins_url('assets/css/admin.css', __FILE__),
            array(),
            '1.0.0'
        );
        
        wp_enqueue_script(
            'image-map-hotspots-admin',
            plugins_url('assets/js/admin.js', __FILE__),
            array('jquery'),
            '1.0.0',
            true
        );
    }
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
    add_menu_page(
        'Image Map Hotspots',
        'Image Maps',
        'manage_options',
        'image-map-hotspots',
        'image_map_hotspots_admin_page',
        'dashicons-location-alt'
    );
    
    add_submenu_page(
        'image-map-hotspots',
        'Add New Image Map',
        'Add New',
        'manage_options',
        'image-map-hotspots-new',
        'image_map_hotspots_new_page'
    );
}
add_action('admin_menu', 'image_map_hotspots_admin_menu');

// Admin main page
function image_map_hotspots_admin_page() {
    $image_maps = get_option('image_map_hotspots_data', array());
    include plugin_dir_path(__FILE__) . 'templates/admin-page.php';
}

// Admin new/edit page
function image_map_hotspots_new_page() {
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

// Save image map
function image_map_hotspots_save() {
    if (!isset($_POST['image_map_nonce']) || !wp_verify_nonce($_POST['image_map_nonce'], 'save_image_map')) {
        wp_die('Invalid nonce');
    }
    
    $map_id = isset($_POST['map_id']) ? sanitize_text_field($_POST['map_id']) : uniqid('map_');
    $map_title = sanitize_text_field($_POST['map_title']);
    $map_image_id = intval($_POST['map_image_id']);
    $hotspots = json_decode(stripslashes($_POST['hotspots_data']), true);
    
    $image_maps = get_option('image_map_hotspots_data', array());
    $image_maps[$map_id] = array(
        'title' => $map_title,
        'image_id' => $map_image_id,
        'hotspots' => $hotspots
    );
    
    update_option('image_map_hotspots_data', $image_maps);
    
    wp_redirect(admin_url('admin.php?page=image-map-hotspots'));
    exit;
}
add_action('admin_post_save_image_map', 'image_map_hotspots_save');

// Shortcode handler
function image_map_hotspots_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => ''
    ), $atts);
    
    if (empty($atts['id'])) {
        return '';
    }
    
    $image_maps = get_option('image_map_hotspots_data', array());
    if (!isset($image_maps[$atts['id']])) {
        return '';
    }
    
    $map_data = $image_maps[$atts['id']];
    $map_title = $map_data['title'];
    $image_url = wp_get_attachment_url($map_data['image_id']);
    $hotspots = isset($map_data['hotspots']) ? $map_data['hotspots'] : array();
    
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/shortcode.php';
    return ob_get_clean();
}
add_shortcode('image_map', 'image_map_hotspots_shortcode');