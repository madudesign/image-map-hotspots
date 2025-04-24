=== Image Map Hotspots ===
Contributors: madu
Tags: image map, hotspots, interactive images
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Add interactive hotspots to your images with customizable tooltips and links.

== Description ==

Image Map Hotspots allows you to create interactive images by adding clickable hotspots with tooltips and links. Perfect for creating interactive diagrams, product showcases, or educational content.

Features:

* Easy to use shortcode system
* Responsive design
* Customizable hotspot colors
* Tooltips on hover
* Mobile-friendly
* Click to navigate
* Smooth animations

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/image-map-hotspots` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the shortcode `[image_map]` with appropriate parameters to display your interactive image

== Usage ==

Basic usage:

`[image_map image="https://example.com/image.jpg" hotspots='[{"x":25,"y":35,"title":"Point 1","label":"First Point","url":"https://example.com","color":"#ff0000"}]']`

Parameters:

* image: URL of the image to use
* hotspots: JSON array of hotspot data including:
  * x: horizontal position (0-100)
  * y: vertical position (0-100)
  * title: hotspot title
  * label: tooltip text
  * url: link URL
  * color: hotspot color (hex code)

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release
