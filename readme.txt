=== Easy Dark Theme for Astra ===
Contributors: Jael Meire
Tags: astra, dark mode, light mode, theme toggle, color scheme, global colors
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Easy light/dark mode for Astra with a toggle button and optional palette mapping to Astra Global Colors.

== Description ==

Easy Dark Theme for Astra adds a light/dark mode switcher designed specifically for Astra themes.

Main features:
* Toggle control mode:
  * Auto (system preference) using prefers-color-scheme
  * Button mode (user switch)
  * Optional “Remember preference” (persists user choice)
* Toggle placement options (floating):
  * Bottom-right, bottom-left, top-right, top-left
  * Horizontal and vertical spacing (positive values, automatically applied based on position)
* Toggle visibility options:
  * Always show, hide on mobile, hide on desktop, always hide
* Toggle style options:
  * Icon or Text
  * Pill style:
    * Capsule switch with sun/moon icons and animated knob
* Optional theme transition:
  * Smooth transition applied only while switching between themes
* Multiple toggle outputs:
  * Floating button (auto-injected)
  * Widget
  * Shortcode: [edta_toggle]
  * Admin preview with live style switching
* Optional Astra Global Colors mapping:
  * Free Preset Palette (included, not editable)
  * Custom Palette (fully editable)
  * Dark palette always applied in dark mode
  * Light palette can be skipped to respect the theme’s own light colors (recommended)
* Accessibility options:
  * Reduced motion support (respects prefers-reduced-motion)
  * Optional focus ring improvements for keyboard navigation
* Tools:
  * Export settings to JSON
  * Import settings from JSON
  * Reset settings to defaults (with confirmation)
* Admin UX improvements:
  * Reduced flicker on initial load (palette + lock state handling)
  * Improved state synchronization for preset/custom palettes
  * Improved save-state detection in sidebar

Notes:
* This plugin is intended for Astra themes. If Astra is not active, the plugin’s frontend output is disabled. The admin page remains available.

Plugin page:
https://wordpress.org/plugins/easy-dark-theme-for-astra/

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/ or install it via WordPress Plugins.
2. Activate "Easy Dark Theme for Astra".
3. Go to WP Admin → Easy Dark Theme to configure:
   * Control mode (Auto / Button)
   * Default mode (System / Light / Dark)
   * Button style/position/visibility
   * Theme transition
   * Accessibility options
   * Astra Global Colors palette options
   * Tools (export/import/reset)

== Frequently Asked Questions ==

= Does it work without Astra? =
No. The plugin is designed for Astra and the frontend is disabled if Astra is not active. The palette mapping relies on Astra Global Colors (--ast-global-color-*).

= What classes does the plugin apply? =
The plugin applies:
* body.edta-theme-dark
* body.edta-theme-light
* html.edta-pre-dark
* html.edta-pre-light

In Auto mode, the initial state is set early to reduce flicker (pre-classes are applied before first paint).

= Can I add the toggle inside my header/menu? =
Yes. Use the shortcode:
[edta_toggle]
Or add the widget in Appearance → Widgets.

= Does the toggle sync between browser tabs? =
Yes (in Button mode with “Remember preference” enabled), using localStorage storage events.

= Will it break if localStorage is blocked? =
No. The plugin uses safe fallbacks and continues working without persistence.

= Can I export or import plugin settings? =
Yes. All plugin settings can be exported and imported from the admin panel, allowing easy migration between sites.

= Does the plugin detect my site colors automatically? =
No. You should set your site colors using Astra Global Colors (Customizer). The plugin then replaces those global color values with the configured light/dark palettes.

== Screenshots ==

1. Control + Animation sections (complete view)
2. Astra Global Colors (Free Preset Palette selected)
3. Astra Global Colors (Custom Palette selected)
4. Accessibility + Tools sections

== Changelog ==

= 0.1.1 =
* Updated plugin URL to official WordPress.org page.
* Improved admin UI initialization to reduce palette flicker.
* Improved preset/custom palette state handling.
* Improved palette lock behavior consistency.
* Minor admin UX refinements and code cleanup.

= 0.1.0 =
* Initial release:
  * Auto/system mode and Button mode
  * Floating toggle + shortcode + widget
  * Icon/Text/Pill styles, position and visibility options
  * Horizontal/vertical spacing based on position
  * Optional theme transition on switch
  * Free Preset Palette + Custom Palette
  * Astra Global Colors palette mapping
  * Export/Import settings (JSON) and Reset tools
  * Cross-tab sync, fallbacks, and accessibility improvements

== Upgrade Notice ==

= 0.1.1 =
Minor improvements and admin UX refinements.