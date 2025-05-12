jQuery(document).ready(function($) {
    'use strict';

    class ImageMapHotspots {
        constructor(container) {
            this.container = $(container);
            this.wrapper = this.container.find('.image-map-wrapper');
            this.image = this.wrapper.find('img');
            this.imageContainer = this.wrapper.find('.image-container');
            
            this.scale = 1;
            this.initialScale = 1; // Will store the scale needed to fit image in container
            this.position = { x: 0, y: 0 };
            this.isDragging = false;
            this.lastMousePosition = { x: 0, y: 0 };
            
            // Ensure image is loaded before initializing
            console.log('Image complete:', this.image[0].complete);
            console.log('Image src:', this.image[0].src);
            
            // Force image to load if not already loaded
            if (this.image[0].complete) {
                console.log('Image already loaded, initializing...');
                setTimeout(() => this.init(), 100); // Small delay to ensure DOM is ready
            } else {
                console.log('Waiting for image to load...');
                this.image.on('load', () => {
                    console.log('Image loaded, initializing...');
                    setTimeout(() => this.init(), 100); // Small delay to ensure DOM is ready
                });
                
                // Fallback in case the load event doesn't fire
                setTimeout(() => {
                    if (!this.initialized) {
                        console.log('Fallback initialization...');
                        this.init();
                    }
                }, 1000);
            }
        }

        init() {
            if (this.initialized) return;
            this.initialized = true;
            
            console.log('Initializing image map...');
            this.createControls();
            this.bindEvents();
            this.handleTouchDevices();
            this.calculateInitialScale();
            this.resetView();
            
            // Force a redraw after a short delay
            setTimeout(() => {
                console.log('Forcing redraw...');
                this.updateTransform();
            }, 200);
        }
        
        calculateInitialScale() {
            // Get the natural dimensions of the image
            const imageWidth = this.image[0].naturalWidth;
            const imageHeight = this.image[0].naturalHeight;
            
            // Get the dimensions of the container
            const containerWidth = this.container.width();
            const containerHeight = this.container.height();
            
            // Set initialScale to 1 (100%) as requested
            this.initialScale = 1;
            
            console.log(`Image dimensions: ${imageWidth}x${imageHeight}`);
            console.log(`Container dimensions: ${containerWidth}x${containerHeight}`);
            console.log(`Initial scale: ${this.initialScale}`);
        }

        createControls() {
            const controls = $(`
                <div class="image-map-controls">
                    <button type="button" class="image-map-zoom-in" title="Zoom In">+</button>
                    <button type="button" class="image-map-zoom-out" title="Zoom Out">-</button>
                    <button type="button" class="image-map-reset" title="Reset View">↺</button>
                    <span class="image-map-zoom-level">100%</span>
                </div>
            `).appendTo(this.container);

            this.controls = {
                zoomIn: controls.find('.image-map-zoom-in'),
                zoomOut: controls.find('.image-map-zoom-out'),
                reset: controls.find('.image-map-reset'),
                zoomLevel: controls.find('.image-map-zoom-level')
            };

            this.controls.zoomIn.on('click', () => this.zoomAtPoint(1.2));
            this.controls.zoomOut.on('click', () => this.zoomAtPoint(0.8));
            this.controls.reset.on('click', () => this.resetView());
        }

        bindEvents() {
            this.container.on('mousedown', (e) => {
                if (e.button === 0 && !$(e.target).hasClass('hotspot')) {
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
                    this.container.removeClass('is-dragging');
                }
            });

            this.container.on('wheel', (e) => {
                e.preventDefault();
                
                const rect = this.container[0].getBoundingClientRect();
                const mouseX = e.clientX - rect.left;
                const mouseY = e.clientY - rect.top;

                const delta = e.originalEvent.deltaY;
                const factor = delta > 0 ? 0.9 : 1.1;
                
                this.zoomAtPoint(factor, mouseX, mouseY);
            });

            // Handle hotspot clicks
            this.container.on('click', '.hotspot', (e) => {
                e.stopPropagation();
                const url = $(e.currentTarget).data('url');
                if (url) {
                    window.location.href = url;
                }
            });
        }

        startDragging(e) {
            this.isDragging = true;
            this.container.addClass('is-dragging');
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

        zoomAtPoint(factor, x, y) {
            const rect = this.container[0].getBoundingClientRect();
            const containerWidth = rect.width;
            const containerHeight = rect.height;

            // Use center point if x,y not provided
            const pointX = x !== undefined ? x : containerWidth / 2;
            const pointY = y !== undefined ? y : containerHeight / 2;

            // Calculate the point under the mouse in image coordinates
            const imageX = (pointX - this.position.x) / this.scale;
            const imageY = (pointY - this.position.y) / this.scale;

            // Calculate new scale, min is 1 (100%), max is 10 (1000%)
            let newScale = this.scale * factor;
            newScale = Math.max(1, Math.min(newScale, 10));

            // Only proceed if scale actually changed
            if (newScale !== this.scale) {
                // Calculate new position to keep the point fixed
                this.position.x = pointX - (imageX * newScale);
                this.position.y = pointY - (imageY * newScale);

                // Update scale
                this.scale = newScale;
                
                // Display zoom percentage (100% to 1000%)
                const displayPercentage = Math.round(this.scale * 100);
                this.controls.zoomLevel.text(displayPercentage + '%');

                // Update the transform
                this.updateTransform();
            }
        }

        resetView() {
            // Reset to scale 1 (100%)
            this.scale = 1;
            
            // Position the image so that its top-left corner is at the top-left of the container
            // This ensures at least part of the image is visible
            this.position = { x: 0, y: 0 };
            
            // Update zoom level display to show 100%
            this.controls.zoomLevel.text('100%');
            
            // Log positioning information for debugging
            console.log('Image natural dimensions:', this.image[0].naturalWidth, 'x', this.image[0].naturalHeight);
            console.log('Container dimensions:', this.container.width(), 'x', this.container.height());
            console.log('Position:', this.position);
            console.log('Scale:', this.scale);
            
            // Update the transform
            this.updateTransform();
        }

        updateTransform() {
            // Log transform values for debugging
            console.log('Applying transform:', this.position.x, this.position.y, this.scale);
            
            // Apply transform to the wrapper for panning
            this.wrapper.css({
                transform: `translate(${this.position.x}px, ${this.position.y}px)`,
                '--map-scale': this.scale
            });
            
            // Apply scaling directly to the image container
            this.imageContainer.css({
                transform: `scale(${this.scale})`,
                transformOrigin: '0 0'
            });
            
            // Ensure the image is visible by checking its dimensions
            const imageWidth = this.image[0].naturalWidth * this.scale;
            const imageHeight = this.image[0].naturalHeight * this.scale;
            console.log('Scaled image dimensions:', imageWidth, 'x', imageHeight);
            
            // Make sure the image is visible
            this.image.css({
                visibility: 'visible',
                opacity: 1
            });
            
            // Force a redraw of the entire container
            this.container.css('opacity', 0.99);
            setTimeout(() => {
                this.container.css('opacity', 1);
            }, 10);
        }

        handleTouchDevices() {
            if (!('ontouchstart' in window)) return;

            let lastTouchDistance = 0;
            let touchCenter = { x: 0, y: 0 };

            this.container.on('touchstart', (e) => {
                const touches = e.originalEvent.touches;
                
                if (touches.length === 2) {
                    e.preventDefault();
                    lastTouchDistance = this.getTouchDistance(touches);
                    touchCenter = this.getTouchCenter(touches);
                } else if (touches.length === 1 && !$(e.target).hasClass('hotspot')) {
                    e.preventDefault();
                    this.startDragging(touches[0]);
                }
            });

            this.container.on('touchmove', (e) => {
                const touches = e.originalEvent.touches;
                
                if (touches.length === 2) {
                    e.preventDefault();
                    const distance = this.getTouchDistance(touches);
                    const center = this.getTouchCenter(touches);
                    const factor = distance / lastTouchDistance;
                    
                    if (factor !== 1) {
                        this.zoomAtPoint(factor, center.x, center.y);
                    }
                    
                    lastTouchDistance = distance;
                    touchCenter = center;
                } else if (touches.length === 1 && this.isDragging) {
                    e.preventDefault();
                    this.handleDrag(touches[0]);
                }
            });

            this.container.on('touchend touchcancel', () => {
                this.isDragging = false;
                this.container.removeClass('is-dragging');
            });
        }

        getTouchDistance(touches) {
            return Math.hypot(
                touches[0].clientX - touches[1].clientX,
                touches[0].clientY - touches[1].clientY
            );
        }

        getTouchCenter(touches) {
            const rect = this.container[0].getBoundingClientRect();
            return {
                x: ((touches[0].clientX + touches[1].clientX) / 2) - rect.left,
                y: ((touches[0].clientY + touches[1].clientY) / 2) - rect.top
            };
        }
    }

    // Initialize all image maps on the page
    $('.image-map-container').each((_, container) => {
        new ImageMapHotspots(container);
    });
});
