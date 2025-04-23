<?php
// Ensure WordPress admin styles and scripts are loaded
wp_enqueue_media();
wp_enqueue_style('wp-admin');
wp_enqueue_style('image-map-hotspots-admin');
wp_enqueue_script('image-map-hotspots-admin');
wp_enqueue_script('jquery-ui-dialog');
?>
<div class="wrap">
    <h1><?php echo $editing ? 'Edit' : 'Create'; ?> Image Map</h1>
    
    <div class="mappinner-editor-container">
        <div id="mappinner-editor-app" data-map-id="<?php echo esc_attr($map_id); ?>" data-nonce="<?php echo wp_create_nonce('image_map_hotspots_nonce'); ?>">
            <div class="mappinner-toolbar">
                <button type="button" class="button mappinner-select-image"><?php _e('Select Image', 'mappinner'); ?></button>
                <button type="button" class="button button-primary mappinner-save"><?php _e('Save Map', 'mappinner'); ?></button>
                <button type="button" class="button mappinner-preview"><?php _e('Preview', 'mappinner'); ?></button>
            </div>
            
            <div class="mappinner-main">
                <div class="mappinner-workspace">
                    <div class="mappinner-image-container">
                        <div class="mappinner-image-wrapper">
                            <?php if ($map_image_url) : ?>
                                <img src="<?php echo esc_url($map_image_url); ?>" alt="Map Image">
                            <?php else : ?>
                                <div class="mappinner-placeholder">
                                    <p><?php _e('No image selected. Click "Select Image" to upload or choose an image.', 'mappinner'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="mappinner-sidebar">
                    <div class="mappinner-panel">
                        <h2><?php _e('Map Settings', 'mappinner'); ?></h2>
                        <div class="mappinner-form-group">
                            <label for="mappinner-title"><?php _e('Title', 'mappinner'); ?></label>
                            <input type="text" id="mappinner-title" class="regular-text" value="<?php echo esc_attr($map_title); ?>">
                        </div>
                    </div>
                    
                    <div class="mappinner-panel">
                        <h2><?php _e('Hotspots', 'mappinner'); ?></h2>
                        <p class="description"><?php _e('Click on the image to add hotspots.', 'mappinner'); ?></p>
                        <div class="mappinner-hotspots-list">
                            <?php if (empty($hotspots)) : ?>
                                <p class="description"><?php _e('No hotspots added yet.', 'mappinner'); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mappinner-hotspots-actions" style="margin-top: 15px;">
                            <button type="button" class="button mappinner-export-hotspots"><?php _e('Export Hotspots', 'mappinner'); ?></button>
                            <button type="button" class="button mappinner-import-hotspots"><?php _e('Import Hotspots', 'mappinner'); ?></button>
                        </div>
                    </div>
                    
                    <div class="mappinner-panel">
                        <h2><?php _e('Debug', 'mappinner'); ?></h2>
                        <div class="mappinner-form-group">
                            <button type="button" id="debug-hotspots" class="button"><?php _e('Debug Hotspots', 'mappinner'); ?></button>
                            <div id="debug-output" style="margin-top: 10px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; display: none; max-height: 300px; overflow: auto;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
