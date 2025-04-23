jQuery(document).ready(function($) {
    'use strict';

    class ImageMapHotspots {
        constructor(container) {
            this.container = $(container);
            this.wrapper = this.container.find('.image-map-wrapper');
            this.image = this.wrapper.find('img');
            this.hotspots = this.wrapper.find('.hotspot');
            
            this.scale = 1;
            this.position = { x: 0, y: 0 };
            this.isDragging = false;
            this.lastMousePosition = { x: 0, y: 0 };
            
            if (this.image[0].complete) {
                this.init();
            } else {
                this.image.on('load', () => this.init());
            }
        }

        init() {
            this.createControls();
            this.bindEvents();
            this.handleTouchDevices();
            this.resetView();
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

            this.hotspots.on('click', (e) => {
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

            // Calculate new scale, min 100%, max 500%
            let newScale = this.scale * factor;
            newScale = Math.max(1, Math.min(newScale, 5));

            // Only proceed if scale actually changed
            if (newScale !== this.scale) {
                // Calculate new position to keep the point fixed
                this.position.x = pointX - (imageX * newScale);
                this.position.y = pointY - (imageY * newScale);

                // Update scale
                this.scale = newScale;
                this.controls.zoomLevel.text(Math.round(newScale * 100) + '%');

                this.updateTransform();
            }
        }

        resetView() {
            this.scale = 1;
            this.position = { x: 0, y: 0 };
            this.controls.zoomLevel.text('100%');
            this.updateTransform();
        }

        updateTransform() {
            this.wrapper.css({
                transform: `translate(${this.position.x}px, ${this.position.y}px) scale(${this.scale})`,
                '--map-scale': this.scale
            });
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
