<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get map data if ID is provided
if (!empty($atts['id'])) {
    $image_maps = get_option('image_map_hotspots_data', array());
    
    if (isset($image_maps[$atts['id']])) {
        $map_data = $image_maps[$atts['id']];
        $atts['image'] = wp_get_attachment_url($map_data['image_id']);
        $atts['hotspots'] = json_encode($map_data['hotspots']);
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
wp_enqueue_style('image-map-hotspots');
wp_enqueue_script('image-map-hotspots');
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
