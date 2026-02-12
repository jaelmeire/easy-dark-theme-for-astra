<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
  exit; // Evita ejecución directa fuera del flujo de desinstalación de WordPress.
}

$option_key = 'edta_astra_settings'; // Clave de la opción donde se guardan los ajustes del plugin.

if (is_multisite()) {
  // En multisite elimina la opción en todos los sitios de la red.
  $sites = get_sites(['fields' => 'ids']);
  if (is_array($sites)) {
    foreach ($sites as $blog_id) {
      switch_to_blog((int) $blog_id); // Cambia temporalmente al sitio actual.
      delete_option($option_key); // Borra los ajustes del plugin en el sitio.
      restore_current_blog(); // Restaura el sitio original.
    }
  }
} else {
  // En instalación simple elimina directamente la opción.
  delete_option($option_key);
}