jQuery(document).ready(function($) {
    'use strict';

    class ImageMapEditor {
        constructor(container) {
            this.container = $(container);
            this.imageContainer = $('.mappinner-image-container');
            this.wrapper = $('.mappinner-image-wrapper');
            this.titleInput = $('#mappinner-title');
            this.mapId = this.container.data('map-id');
            this.nonce = this.container.data('nonce');
            this.hotspots = [];
            this.scale = 1;
            this.position = { x: 0, y: 0 };
            this.isDragging = false;
            this.isHotspotDragging = false;
            this.isPreviewMode = false;
            
            this.init();
        }

        init() {
            this.createControls();
            this.bindEvents();
            this.loadMapData();
        }

        createControls() {
            const controls = $(`
                <div class="mappinner-controls">
                    <button type="button" class="mappinner-zoom-in" title="Zoom In">+</button>
                    <button type="button" class="mappinner-zoom-out" title="Zoom Out">-</button>
                    <button type="button" class="mappinner-reset" title="Reset View">↺</button>
                    <span class="mappinner-zoom-level">100%</span>
                </div>
            `).prependTo(this.imageContainer);

            this.controls = {
                zoomIn: controls.find('.mappinner-zoom-in'),
                zoomOut: controls.find('.mappinner-zoom-out'),
                reset: controls.find('.mappinner-reset'),
                zoomLevel: controls.find('.mappinner-zoom-level')
            };

            this.controls.zoomIn.on('click', () => this.zoom(1.2));
            this.controls.zoomOut.on('click', () => this.zoom(0.8));
            this.controls.reset.on('click', () => this.resetView());
        }

        bindEvents() {
            // Image selection
            $('.mappinner-select-image').on('click', () => this.openMediaUploader());

            // Save map
            $('.mappinner-save').on('click', () => this.saveMap());

            // Preview toggle
            $('.mappinner-preview').on('click', () => this.togglePreview());
            
            // Export hotspots
            $('.mappinner-export-hotspots').on('click', () => this.exportHotspots());
            
            // Import hotspots
            $('.mappinner-import-hotspots').on('click', () => this.importHotspots());
            
            // Debug hotspots
            $('#debug-hotspots').on('click', () => this.debugHotspots());

            // Image container events
            this.imageContainer.on('mousedown', (e) => {
                if (e.button === 0 && (e.altKey || !this.isEditing)) {
                    e.preventDefault();
                    this.startDragging(e);
                }
            });

            $(document).on('mousemove', (e) => {
                if (this.isDragging) {
                    e.preventDefault();
                    this.handleDrag(e);
                }
            });

            $(document).on('mouseup', () => {
                if (this.isDragging) {
                    this.isDragging = false;
                    this.imageContainer.removeClass('is-dragging');
                }
            });

            // Handle clicks for adding hotspots
            this.wrapper.on('click', (e) => {
                if (this.isPreviewMode || this.isDragging || e.altKey || this.isHotspotDragging || $(e.target).closest('.mappinner-hotspot').length) return;
                
                const rect = this.wrapper[0].getBoundingClientRect();
                const x = ((e.clientX - rect.left) / rect.width) * 100;
                const y = ((e.clientY - rect.top) / rect.height) * 100;
                
                if (x >= 0 && x <= 100 && y >= 0 && y <= 100) {
                    this.addHotspot(x, y);
                }
            });

            // Handle zoom with mouse wheel
            this.imageContainer.on('wheel', (e) => {
                e.preventDefault();
                
                const rect = this.imageContainer[0].getBoundingClientRect();
                const mouseX = e.clientX - rect.left;
                const mouseY = e.clientY - rect.top;

                // Calculate the point under the mouse in image coordinates
                const pointX = (mouseX - this.position.x) / this.scale;
                const pointY = (mouseY - this.position.y) / this.scale;

                // Calculate new scale
                const delta = e.originalEvent.deltaY;
                const factor = delta > 0 ? 0.9 : 1.1;
                const newScale = Math.min(Math.max(this.scale * factor, 1), 5); // Min 100%, Max 500%

                // Calculate new position to keep the point under the mouse fixed
                this.position.x = mouseX - (pointX * newScale);
                this.position.y = mouseY - (pointY * newScale);

                // Update scale
                this.scale = newScale;
                this.controls.zoomLevel.text(Math.round(newScale * 100) + '%');

                this.updateTransform();
            });

            // Handle window resize
            $(window).on('resize', () => {
                if (this.wrapper.find('img').length) {
                    this.resetView();
                }
            });
        }

        loadMapData() {
            if (!this.mapId) return;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mappinner_get_map',
                    nonce: this.nonce,
                    map_id: this.mapId
                },
                dataType: 'json',
                success: (response) => {
                    if (response && response.success && response.data) {
                        this.titleInput.val(response.data.title);
                        this.updateImage(response.data.image_url);
                        
                        try {
                            if (response.data.hotspots) {
                                const hotspots = JSON.parse(response.data.hotspots);
                                
                                if (Array.isArray(hotspots) && hotspots.length > 0) {
                                    this.hotspots = hotspots;
                                    this.renderHotspots();
                                }
                            }
                        } catch (e) {
                            console.error('Error parsing hotspots:', e);
                        }
                    }
                }
            });
        }

        openMediaUploader() {
            const uploader = wp.media({
                title: mappinnerAdmin.strings.select_image,
                button: { text: mappinnerAdmin.strings.use_image },
                multiple: false
            });

            uploader.on('select', () => {
                const attachment = uploader.state().get('selection').first().toJSON();
                // Get the URL and remove "-scaled" if present
                let imageUrl = attachment.url;
                imageUrl = imageUrl.replace('-scaled.', '.');
                this.updateImage(imageUrl);
            });

            uploader.open();
        }

        updateImage(url) {
            // Remove placeholder if it exists
            this.wrapper.find('.mappinner-placeholder').remove();
            
            // Clear wrapper and add new image
            this.wrapper.empty();
            
            // Create image container
            const container = $('<div class="image-container"></div>');
            
            const img = $('<img>', {
                src: url,
                alt: 'Map'
            });
            
            container.append(img);
            this.wrapper.append(container);
            
            img.on('load', () => {
                this.resetView();
                this.renderHotspots();
            });
        }

        resetView() {
            this.scale = 1;
            this.position = { x: 0, y: 0 };
            this.controls.zoomLevel.text('100%');
            this.updateTransform();
        }

        updateTransform() {
            // Apply transform to the wrapper for panning
            this.wrapper.css({
                transform: `translate(${this.position.x}px, ${this.position.y}px)`
            });
            
            // Apply scaling directly to the image container
            this.wrapper.find('.image-container').css({
                transform: `scale(${this.scale})`,
                transformOrigin: '0 0'
            });
        }

        zoom(factor) {
            const rect = this.imageContainer[0].getBoundingClientRect();
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;

            // Calculate the point under the center in image coordinates
            const pointX = (centerX - this.position.x) / this.scale;
            const pointY = (centerY - this.position.y) / this.scale;

            // Calculate new scale
            const newScale = Math.min(Math.max(this.scale * factor, 1), 5); // Min 100%, Max 500%

            // Calculate new position to keep the center point fixed
            this.position.x = centerX - (pointX * newScale);
            this.position.y = centerY - (pointY * newScale);

            // Update scale
            this.scale = newScale;
            this.controls.zoomLevel.text(Math.round(newScale * 100) + '%');

            this.updateTransform();
        }

        addHotspot(x, y) {
            const hotspot = {
                id: 'hotspot_' + Date.now(),
                x: x,
                y: y,
                title: '',
                label: '',
                blogUrl: '',
                color: '#4f46e5',
                active: true,
                order: this.hotspots.length
            };

            this.hotspots.push(hotspot);
            this.renderHotspots();
            this.editHotspot(hotspot.id);
        }

        renderHotspots() {
            $('.mappinner-hotspot').remove();
            
            const container = this.wrapper.find('.image-container');
            
            this.hotspots.forEach((hotspot, index) => {
                if (!hotspot.active) return;

                const element = $(`
                    <div class="mappinner-hotspot" data-id="${hotspot.id}">
                        ${index + 1}
                        ${hotspot.label ? `<span class="mappinner-hotspot-label">${hotspot.label}</span>` : ''}
                    </div>
                `).css({
                    left: hotspot.x + '%',
                    top: hotspot.y + '%',
                    backgroundColor: hotspot.color
                });

                container.append(element);
                this.makeHotspotDraggable(element);
            });

            this.updateHotspotsList();
        }

        makeHotspotDraggable(element) {
            let startX, startY;
            let isDragging = false;

            element.on('mousedown', (e) => {
                if (e.button !== 0) return;
                e.preventDefault();
                e.stopPropagation();

                isDragging = true;
                this.isHotspotDragging = true;
                element.addClass('is-dragging');

                const container = this.wrapper.find('.image-container');
                const rect = container[0].getBoundingClientRect();
                startX = e.clientX - rect.left;
                startY = e.clientY - rect.top;

                const moveHandler = (e) => {
                    if (!isDragging) return;

                    const container = this.wrapper.find('.image-container');
                    const rect = container[0].getBoundingClientRect();
                    const x = ((e.clientX - rect.left) / rect.width) * 100;
                    const y = ((e.clientY - rect.top) / rect.height) * 100;

                    // Constrain to image boundaries
                    const constrainedX = Math.max(0, Math.min(100, x));
                    const constrainedY = Math.max(0, Math.min(100, y));

                    const hotspot = this.hotspots.find(h => h.id === element.data('id'));
                    if (hotspot) {
                        hotspot.x = constrainedX;
                        hotspot.y = constrainedY;
                        element.css({
                            left: constrainedX + '%',
                            top: constrainedY + '%'
                        });
                    }
                };

                const upHandler = () => {
                    isDragging = false;
                    this.isHotspotDragging = false;
                    element.removeClass('is-dragging');
                    $(document).off('mousemove', moveHandler);
                    $(document).off('mouseup', upHandler);
                };

                $(document).on('mousemove', moveHandler);
                $(document).on('mouseup', upHandler);
            });

            element.on('click', (e) => {
                if (isDragging) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                this.editHotspot(element.data('id'));
            });
        }

        updateHotspotsList() {
            const list = $('.mappinner-hotspots-list');
            list.empty();

            if (this.hotspots.length === 0) {
                list.append('<p class="description">No hotspots added yet.</p>');
                return;
            }

            this.hotspots.forEach((hotspot, index) => {
                const item = $(`
                    <div class="mappinner-hotspot-item">
                        <div class="mappinner-hotspot-title">
                            ${hotspot.title || `Hotspot ${index + 1}`}
                        </div>
                        <div class="mappinner-hotspot-actions">
                            <button type="button" class="button button-small edit-hotspot">Edit</button>
                            <button type="button" class="button button-small button-link-delete delete-hotspot">Delete</button>
                        </div>
                    </div>
                `);

                item.find('.edit-hotspot').on('click', () => this.editHotspot(hotspot.id));
                item.find('.delete-hotspot').on('click', () => this.deleteHotspot(hotspot.id));

                list.append(item);
            });
        }

        editHotspot(id) {
            const hotspot = this.hotspots.find(h => h.id === id);
            if (!hotspot) return;

            const dialog = $(`
                <div class="mappinner-dialog" title="Edit Hotspot">
                    <div class="mappinner-form-group">
                        <label>Title</label>
                        <input type="text" class="regular-text hotspot-title" value="${hotspot.title}">
                    </div>
                    <div class="mappinner-form-group">
                        <label>Label</label>
                        <input type="text" class="regular-text hotspot-label" value="${hotspot.label}">
                    </div>
                    <div class="mappinner-form-group">
                        <label>URL</label>
                        <input type="url" class="regular-text hotspot-url" value="${hotspot.blogUrl}">
                    </div>
                    <div class="mappinner-form-group">
                        <label>Color</label>
                        <input type="color" class="hotspot-color" value="${hotspot.color}">
                    </div>
                </div>
            `).dialog({
                modal: true,
                width: 400,
                buttons: {
                    Save: () => {
                        hotspot.title = dialog.find('.hotspot-title').val();
                        hotspot.label = dialog.find('.hotspot-label').val();
                        hotspot.blogUrl = dialog.find('.hotspot-url').val();
                        hotspot.color = dialog.find('.hotspot-color').val();
                        this.renderHotspots();
                        dialog.dialog('close');
                    },
                    Cancel: () => {
                        dialog.dialog('close');
                    }
                },
                close: () => {
                    dialog.remove();
                }
            });
        }

        deleteHotspot(id) {
            if (!confirm(mappinnerAdmin.strings.delete_confirm)) return;
            
            this.hotspots = this.hotspots.filter(h => h.id !== id);
            this.renderHotspots();
        }

        saveMap() {
            // Show loading indicator
            const $saveButton = $('.mappinner-save');
            const originalText = $saveButton.text();
            $saveButton.text('Saving...').prop('disabled', true);
            
            // Check if we have an image
            if (!this.wrapper.find('img').length) {
                alert('Please select an image first.');
                $saveButton.text(originalText).prop('disabled', false);
                return;
            }
            
            // Check if we have a title
            if (!this.titleInput.val().trim()) {
                alert('Please enter a title for the map.');
                $saveButton.text(originalText).prop('disabled', false);
                return;
            }
            
            // Prepare data
            const data = {
                action: 'mappinner_save_map',
                nonce: this.nonce,
                map_id: this.mapId || '',
                title: this.titleInput.val(),
                image_url: this.wrapper.find('img').attr('src'),
                hotspots: JSON.stringify(this.hotspots)
            };
            
            // Make AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                dataType: 'json',
                success: (response) => {
                    if (response && response.success) {
                        // Update map ID if this is a new map
                        if (!this.mapId && response.data && response.data.map_id) {
                            this.mapId = response.data.map_id;
                            this.container.attr('data-map-id', this.mapId);
                        }
                        
                        // Show success message
                        const hotspotsCount = this.hotspots.length;
                        const message = `Map saved successfully with ${hotspotsCount} hotspots.\n\nWould you like to continue editing this map?`;
                        
                        if (confirm(message)) {
                            // Stay on the page and refresh
                            window.location.href = `admin.php?page=image-map-hotspots-new&id=${this.mapId}`;
                        } else {
                            // Redirect to maps list
                            window.location.href = 'admin.php?page=image-map-hotspots&message=saved';
                        }
                    } else {
                        // Show error message
                        const errorMsg = response && response.data && response.data.message 
                            ? response.data.message 
                            : 'Unknown error occurred while saving.';
                        
                        alert('Error: ' + errorMsg);
                        $saveButton.text(originalText).prop('disabled', false);
                    }
                },
                error: (xhr, status, error) => {
                    alert('Error saving map: ' + error);
                    $saveButton.text(originalText).prop('disabled', false);
                }
            });
        }

        togglePreview() {
            this.isPreviewMode = !this.isPreviewMode;
            this.imageContainer.toggleClass('preview-mode', this.isPreviewMode);
            $('.mappinner-preview').text(this.isPreviewMode ? 'Edit' : 'Preview');
        }

        startDragging(e) {
            this.isDragging = true;
            this.imageContainer.addClass('is-dragging');
            this.lastMousePosition = {
                x: e.clientX,
                y: e.clientY
            };
        }

        handleDrag(e) {
            if (!this.isDragging) return;

            const dx = e.clientX - this.lastMousePosition.x;
            const dy = e.clientY - this.lastMousePosition.y;

            this.position.x += dx;
            this.position.y += dy;

            this.lastMousePosition = {
                x: e.clientX,
                y: e.clientY
            };

            this.updateTransform();
        }
        
        debugHotspots() {
            const $output = $('#debug-output');
            $output.empty().show();
            
            let html = '<h4>Current Hotspots:</h4>';
            
            if (this.hotspots.length === 0) {
                html += '<p>No hotspots in memory.</p>';
            } else {
                html += `<p>Total hotspots in memory: ${this.hotspots.length}</p>`;
                html += '<ul>';
                this.hotspots.forEach((hotspot, index) => {
                    html += `<li>Hotspot ${index + 1}:<br>`;
                    html += `ID: ${hotspot.id}<br>`;
                    html += `Position: x=${hotspot.x}, y=${hotspot.y}<br>`;
                    html += `Title: ${hotspot.title || '(empty)'}<br>`;
                    html += `Label: ${hotspot.label || '(empty)'}<br>`;
                    html += `URL: ${hotspot.blogUrl || '(empty)'}<br>`;
                    html += `Color: ${hotspot.color}<br>`;
                    html += `Active: ${hotspot.active ? 'Yes' : 'No'}</li>`;
                });
                html += '</ul>';
            }
            
            // Add map data
            html += '<h4>Map Data:</h4>';
            html += `<p>Map ID: ${this.mapId || '(new map)'}</p>`;
            html += `<p>Title: ${this.titleInput.val() || '(empty)'}</p>`;
            html += `<p>Image URL: ${this.wrapper.find('img').attr('src') || '(no image)'}</p>`;
            
            // Add debug info for AJAX
            if (this.mapId) {
                html += '<button type="button" id="load-map-data" class="button">Load Map Data from Database</button>';
                html += '<div id="server-data" style="margin-top: 10px;"></div>';
            }
            
            $output.html(html);
            
            // Add event handler for the load button
            $('#load-map-data').on('click', () => {
                const $serverData = $('#server-data');
                $serverData.html('<p>Loading data from server...</p>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mappinner_debug',
                        nonce: this.nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            const maps = response.data.maps;
                            let serverHtml = '<h4>Server Data:</h4>';
                            
                            if (!maps[this.mapId]) {
                                serverHtml += `<p>No data found for map ID: ${this.mapId}</p>`;
                            } else {
                                const map = maps[this.mapId];
                                const hotspotsCount = map.hotspots ? map.hotspots.length : 0;
                                
                                serverHtml += `<p>Map found with ${hotspotsCount} hotspots</p>`;
                                
                                if (hotspotsCount > 0) {
                                    serverHtml += '<ul>';
                                    map.hotspots.forEach((hotspot, index) => {
                                        serverHtml += `<li>Hotspot ${index + 1}: x=${hotspot.x}, y=${hotspot.y}, title="${hotspot.title || ''}"</li>`;
                                    });
                                    serverHtml += '</ul>';
                                }
                            }
                            
                            $serverData.html(serverHtml);
                        } else {
                            $serverData.html(`<p class="error">Error: ${response.data.message}</p>`);
                        }
                    },
                    error: (xhr, status, error) => {
                        $serverData.html(`<p class="error">Error: ${error}</p>`);
                    }
                });
            });
        }
        
        exportHotspots() {
            if (this.hotspots.length === 0) {
                alert('No hotspots to export.');
                return;
            }
            
            // Convert hotspots to CSV format
            let csv = 'x,y,title,label,url,color\n';
            
            this.hotspots.forEach(hotspot => {
                if (!hotspot.active) return;
                
                const x = Math.round(hotspot.x * 100) / 100;
                const y = Math.round(hotspot.y * 100) / 100;
                const title = hotspot.title || '';
                const label = hotspot.label || '';
                const url = hotspot.blogUrl || '';
                const color = hotspot.color || '#4f46e5';
                
                csv += `${x},${y},"${title.replace(/"/g, '""')}","${label.replace(/"/g, '""')}","${url}","${color}"\n`;
            });
            
            // Create a download link
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = 'hotspots.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }
        
        importHotspots() {
            // Create a file input element
            const fileInput = $('<input type="file" accept=".csv" style="display:none">');
            $('body').append(fileInput);
            
            fileInput.on('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;
                
                const reader = new FileReader();
                reader.onload = (event) => {
                    const content = event.target.result;
                    this.parseImportedHotspots(content);
                };
                reader.readAsText(file);
                
                // Remove the file input
                fileInput.remove();
            });
            
            // Trigger the file input click
            fileInput.click();
        }
        
        parseImportedHotspots(content) {
            // Split the content into lines
            const lines = content.split('\n');
            if (lines.length < 2) {
                alert('Invalid CSV format.');
                return;
            }
            
            // Detect separator (comma or semicolon)
            const firstLine = lines[0].toLowerCase();
            const separator = firstLine.includes(';') ? ';' : ',';
            
            // Check the header
            const header = firstLine;
            if (!header.includes('x') && !header.includes('y')) {
                alert('CSV must have at least x and y columns.');
                return;
            }
            
            // Parse the header to find column indices
            const columns = header.split(separator);
            const xIndex = columns.indexOf('x');
            const yIndex = columns.indexOf('y');
            const titleIndex = columns.indexOf('title');
            const labelIndex = columns.indexOf('label');
            const urlIndex = columns.indexOf('url');
            const colorIndex = columns.indexOf('color');
            
            console.log('Detected separator:', separator);
            console.log('Columns:', columns);
            console.log('Indices:', { xIndex, yIndex, titleIndex, labelIndex, urlIndex, colorIndex });
            
            // Parse the data lines
            const newHotspots = [];
            
            for (let i = 1; i < lines.length; i++) {
                const line = lines[i].trim();
                if (!line) continue;
                
                // Split by the detected separator
                const values = line.split(separator);
                console.log(`Line ${i} values:`, values);
                
                if (values.length <= Math.max(xIndex, yIndex)) {
                    console.log(`Skipping line ${i}: not enough values`);
                    continue;
                }
                
                let x = parseFloat(values[xIndex]);
                let y = parseFloat(values[yIndex]);
                
                if (isNaN(x) || isNaN(y) || x < 0 || y < 0) {
                    console.log(`Skipping line ${i}: invalid coordinates`);
                    continue;
                }
                
                // Scale coordinates to fit within 0-100 range if they're outside
                if (x > 100 || y > 100) {
                    console.log(`Scaling coordinates for line ${i}: (${x}, ${y})`);
                    
                    // Find max values in the CSV to use for scaling
                    let maxX = 100;
                    let maxY = 100;
                    
                    // Scan all lines to find maximum values
                    for (let j = 1; j < lines.length; j++) {
                        const scanLine = lines[j].trim();
                        if (!scanLine) continue;
                        
                        const scanValues = scanLine.split(separator);
                        if (scanValues.length <= Math.max(xIndex, yIndex)) continue;
                        
                        const scanX = parseFloat(scanValues[xIndex]);
                        const scanY = parseFloat(scanValues[yIndex]);
                        
                        if (!isNaN(scanX) && scanX > maxX) maxX = scanX;
                        if (!isNaN(scanY) && scanY > maxY) maxY = scanY;
                    }
                    
                    console.log(`Max coordinates found: (${maxX}, ${maxY})`);
                    
                    // Scale coordinates
                    if (maxX > 100) x = (x / maxX) * 100;
                    if (maxY > 100) y = (y / maxY) * 100;
                    
                    console.log(`Scaled coordinates: (${x}, ${y})`);
                }
                
                const hotspot = {
                    id: 'hotspot_' + Date.now() + '_' + i,
                    x: x,
                    y: y,
                    title: titleIndex >= 0 && values.length > titleIndex ? values[titleIndex] : '',
                    label: labelIndex >= 0 && values.length > labelIndex ? values[labelIndex] : '',
                    blogUrl: urlIndex >= 0 && values.length > urlIndex ? values[urlIndex] : '',
                    color: colorIndex >= 0 && values.length > colorIndex ? values[colorIndex] : '#4f46e5',
                    active: true,
                    order: newHotspots.length
                };
                
                console.log(`Adding hotspot from line ${i}:`, hotspot);
                newHotspots.push(hotspot);
            }
            
            if (newHotspots.length === 0) {
                alert('No valid hotspots found in the CSV file.');
                return;
            }
            
            // Confirm import
            if (confirm(`Import ${newHotspots.length} hotspots? This will replace any existing hotspots.`)) {
                this.hotspots = newHotspots;
                this.renderHotspots();
            }
        }
    }

    // Initialize the editor if we're on the editor page
    if ($('#mappinner-editor-app').length) {
        new ImageMapEditor('#mappinner-editor-app');
    }
});
