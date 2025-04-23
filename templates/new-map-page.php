<?php
// Ensure WordPress admin styles and scripts are loaded
wp_enqueue_media();
wp_enqueue_style('wp-admin');
wp_enqueue_style('image-map-hotspots-admin');
wp_enqueue_script('image-map-hotspots-admin');
?>
<div class="wrap">
    <h1><?php echo $editing ? 'Edit' : 'Create'; ?> Image Map</h1>
    
    <form method="post" action="" id="image-map-form">
        <?php wp_nonce_field('save_image_map', 'image_map_nonce'); ?>
        <input type="hidden" name="action" value="save_image_map">
        <?php if ($editing) : ?>
            <input type="hidden" name="map_id" value="<?php echo esc_attr($map_id); ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label for="map-title">Map Title</label>
            <input type="text" id="map-title" name="map_title" class="form-control" value="<?php echo esc_attr($map_title); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="map-image">Map Image</label>
            <div class="media-upload-container">
                <input type="hidden" id="map-image-id" name="map_image_id" value="<?php echo esc_attr($map_image_id); ?>">
                <div id="map-image-preview">
                    <?php if ($map_image_url) : ?>
                        <img src="<?php echo esc_url($map_image_url); ?>" alt="Map Image">
                    <?php else : ?>
                        <p>No image selected</p>
                    <?php endif; ?>
                </div>
                <button type="button" id="upload-image-button" class="button"><?php echo $map_image_url ? 'Change Image' : 'Select Image'; ?></button>
                <?php if ($map_image_url) : ?>
                    <button type="button" id="remove-image-button" class="button">Remove Image</button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($map_image_url) : ?>
            <div class="img-map-container">
                <div class="zoom-controls">
                    <button type="button" id="zoom-in" title="Zoom In">+</button>
                    <button type="button" id="zoom-out" title="Zoom Out">-</button>
                    <button type="button" id="zoom-reset" title="Reset Zoom">Reset</button>
                    <span id="zoom-level">100%</span>
                </div>
                
                <div class="img-map-wrapper">
                    <img src="<?php echo esc_url($map_image_url); ?>" alt="Map Image" class="img-map-img">
                </div>
                
                <div class="hotspots-container">
                    <h3>Hotspots</h3>
                    <p>Click on the image to add hotspots. You can also edit or remove existing hotspots.</p>
                    <ul id="hotspots-list">
                        <?php if ($hotspots) : ?>
                            <?php foreach ($hotspots as $hotspot) : ?>
                                <li class="hotspot-item" data-hotspot-id="<?php echo esc_attr($hotspot['id']); ?>" data-x="<?php echo esc_attr($hotspot['x']); ?>" data-y="<?php echo esc_attr($hotspot['y']); ?>">
                                    <input type="text" class="hotspot-title" value="<?php echo esc_attr($hotspot['title']); ?>">
                                    <span class="hotspot-coords">X: <?php echo round($hotspot['x']); ?>, Y: <?php echo round($hotspot['y']); ?></span>
                                    <button type="button" class="remove-hotspot">Remove</button>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <input type="hidden" id="hotspots-data" name="hotspots_data" value="<?php echo esc_attr(json_encode($hotspots)); ?>">
            </div>
        <?php endif; ?>
        
        <div class="form-group">
            <button type="submit" class="submit-button">Save Image Map</button>
        </div>
    </form>
</div>

<script>
    jQuery(document).ready(function($) {
        // Media uploader
        var mediaUploader = null;
        
        $('#upload-image-button').on('click', function(e) {
            e.preventDefault();
            
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            
            mediaUploader = wp.media({
                title: 'Select Map Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });
            
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#map-image-id').val(attachment.id);
                $('#map-image-preview').html('<img src="' + attachment.url + '" alt="Map Image">');
                $('#remove-image-button').show();
                
                location.reload();
            });
            
            mediaUploader.open();
        });
        
        $('#remove-image-button').on('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to remove this image?')) {
                $('#map-image-id').val('');
                $('#map-image-preview').html('<p>No image selected</p>');
                $(this).hide();
                location.reload();
            }
        });
    });
</script>