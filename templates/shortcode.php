<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get map data if ID is provided
if (!empty($atts['id'])) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mappinner_maps';
    $map = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $atts['id']));
    
    if ($map) {
        $atts['image'] = $map->image_url;
        $atts['hotspots'] = $map->hotspots;
    }
}

// Ensure we have an image
if (empty($atts['image'])) {
    return '';
}

// Parse hotspots
$hotspots = json_decode(stripslashes($atts['hotspots']), true);
if (!is_array($hotspots)) {
    $hotspots = array();
}

// Generate unique ID
$map_id = 'map_' . ((!empty($atts['id'])) ? $atts['id'] : uniqid());

// Enqueue required styles and scripts
wp_enqueue_style('mappinner');
wp_enqueue_script('mappinner');
?>

<div class="image-map-container" id="<?php echo esc_attr($map_id); ?>">
    <div class="image-map-wrapper">
        <img src="<?php echo esc_url($atts['image']); ?>" alt="Interactive Map" />
        <?php foreach ($hotspots as $index => $hotspot): ?>
            <?php if (isset($hotspot['active']) && $hotspot['active']): ?>
                <?php
                // Get the URL from either 'url' or 'blogUrl' field
                $url = isset($hotspot['blogUrl']) ? $hotspot['blogUrl'] : (isset($hotspot['url']) ? $hotspot['url'] : '');
                ?>
                <div 
                    class="hotspot"
                    style="left: <?php echo esc_attr($hotspot['x']); ?>%; top: <?php echo esc_attr($hotspot['y']); ?>%; background-color: <?php echo esc_attr($hotspot['color']); ?>;"
                    data-title="<?php echo esc_attr($hotspot['title']); ?>"
                    <?php if (!empty($url)): ?>
                    data-url="<?php echo esc_url($url); ?>"
                    <?php endif; ?>
                >
                    <div class="hotspot-inner"><?php echo esc_html($index + 1); ?></div>
                    <?php if (!empty($hotspot['label'])): ?>
                        <span class="hotspot-label"><?php echo wp_kses_post($hotspot['label']); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>