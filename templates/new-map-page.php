<?php
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'mappinner'));
}

// Get map ID from URL if editing
$map_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo $map_id ? __('Edit Map', 'mappinner') : __('Add New Map', 'mappinner'); ?>
    </h1>
    <hr class="wp-header-end">

    <div id="mappinner-editor-app" data-nonce="<?php echo wp_create_nonce('mappinner_nonce'); ?>" data-map-id="<?php echo esc_attr($map_id); ?>">
        <div class="mappinner-editor-container">
            <div class="mappinner-toolbar">
                <button type="button" class="button button-primary mappinner-save">
                    <?php _e('Save Map', 'mappinner'); ?>
                </button>
                <button type="button" class="button mappinner-preview">
                    <?php _e('Preview', 'mappinner'); ?>
                </button>
                <button type="button" class="button mappinner-import-csv">
                    <?php _e('Import CSV', 'mappinner'); ?>
                </button>
            </div>

            <div class="mappinner-main">
                <div class="mappinner-workspace">
                    <div class="mappinner-image-container">
                        <div class="mappinner-image-wrapper">
                            <div class="mappinner-placeholder">
                                <p><?php _e('Select an image to get started', 'mappinner'); ?></p>
                                <button type="button" class="button button-hero mappinner-select-image">
                                    <?php _e('Select Image', 'mappinner'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mappinner-sidebar">
                    <div class="mappinner-panel">
                        <h2><?php _e('Map Settings', 'mappinner'); ?></h2>
                        <div class="mappinner-form-group">
                            <label for="mappinner-title"><?php _e('Title', 'mappinner'); ?></label>
                            <input type="text" id="mappinner-title" class="regular-text" value="" required>
                        </div>
                        <div class="mappinner-form-group">
                            <label><?php _e('Image', 'mappinner'); ?></label>
                            <button type="button" class="button mappinner-select-image">
                                <?php _e('Select Image', 'mappinner'); ?>
                            </button>
                            <div class="mappinner-image-preview"></div>
                        </div>
                    </div>

                    <div class="mappinner-panel">
                        <h2><?php _e('Hotspots', 'mappinner'); ?></h2>
                        <div class="mappinner-hotspots-list"></div>
                        <p class="description"><?php _e('Click on the image to add hotspots or use CSV import', 'mappinner'); ?></p>
                        <div class="mappinner-csv-template">
                            <h4><?php _e('CSV Format', 'mappinner'); ?></h4>
                            <p class="description"><?php _e('Download the template or create a CSV file with these columns:', 'mappinner'); ?></p>
                            <code>x,y,title,label,url,color</code>
                            <p class="description"><?php _e('Example:', 'mappinner'); ?></p>
                            <code>25,35,First Point,Point 1,https://example.com,#ff0000</code>
                            <br>
                            <a href="#" class="button button-small mappinner-download-template" style="margin-top: 10px;">
                                <?php _e('Download Template', 'mappinner'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CSV Import Dialog -->
    <div id="mappinner-csv-dialog" title="<?php _e('Import Hotspots from CSV', 'mappinner'); ?>" style="display:none;">
        <div class="mappinner-csv-upload">
            <p><?php _e('Upload a CSV file with hotspot data:', 'mappinner'); ?></p>
            <input type="file" id="mappinner-csv-file" accept=".csv" style="display: none;">
            <button type="button" class="button button-hero" id="mappinner-csv-upload-btn">
                <?php _e('Select CSV File', 'mappinner'); ?>
            </button>
            <div id="mappinner-csv-preview" style="margin-top: 20px;"></div>
        </div>
    </div>
</div>