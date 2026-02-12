<?php
if (!defined('ABSPATH')) {
  exit; // Evita acceso directo al archivo.
}

require_once EDTA_PLUGIN_DIR . 'includes/class-edta-admin.php'; // Carga lógica del panel admin.
require_once EDTA_PLUGIN_DIR . 'includes/class-edta-frontend.php'; // Carga lógica del frontend.
require_once EDTA_PLUGIN_DIR . 'includes/class-edta-astra-bridge.php'; // Carga verificador del theme Astra.

final class EDTA_Plugin {

  // Inicializa el plugin y registra sus componentes principales.
  public function init(): void {
    // Inicializa admin solo en el panel de WordPress.
    if (is_admin()) {
      (new EDTA_Admin())->init();
    }

    // Inicializa frontend únicamente si Astra está activo.
    if (EDTA_Astra_Bridge::is_astra_active()) {
      (new EDTA_Frontend())->init();
    }
  } // Fin de EDTA_Plugin::init()

}