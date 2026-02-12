<?php
/**
 * Plugin Name:       Easy Dark Theme for Astra
 * Plugin URI:        https://wordpress.org/plugins/easy-dark-theme-for-astra/
 * Description:       Light/dark mode for Astra with a toggle button (floating, widget, shortcode) and palette mapping to Astra Global Colors.
 * Version:           0.1.1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Jael Meire
 * Author URI:        https://jaelmeire.vercel.app/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       easy-dark-theme-for-astra
 * Domain Path:       /languages
 *
 * @package EasyDarkThemeAstra
 */

if (!defined('ABSPATH')) {
  exit; // Evita acceso directo al archivo.
}

define('EDTA_VERSION', '0.1.0'); // Versión actual del plugin.
define('EDTA_PLUGIN_FILE', __FILE__); // Archivo principal del plugin.
define('EDTA_PLUGIN_DIR', plugin_dir_path(__FILE__)); // Ruta absoluta al directorio del plugin.
define('EDTA_PLUGIN_URL', plugin_dir_url(__FILE__)); // URL base del plugin.

require_once EDTA_PLUGIN_DIR . 'includes/class-edta-plugin.php'; // Carga el núcleo del plugin.

// Inicializa y ejecuta el plugin.
function edta_run_plugin(): void {
  $plugin = new EDTA_Plugin(); // Instancia principal del plugin.
  $plugin->init(); // Registra hooks y carga componentes (admin/frontend).
} // Fin de edta_run_plugin()

// Ejecuta el plugin al cargarse WordPress.
edta_run_plugin();