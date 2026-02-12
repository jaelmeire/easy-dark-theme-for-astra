<?php
if (!defined('ABSPATH')) {
  exit; // Evita acceso directo al archivo.
}

final class EDTA_Astra_Bridge {

  // Verifica si el theme Astra está activo.
  public static function is_astra_active(): bool {
    $theme = wp_get_theme();
    $stylesheet = strtolower((string) $theme->get_stylesheet()); // Slug del theme activo.
    $template = strtolower((string) $theme->get_template()); // Theme padre (si existe).
    $name = strtolower((string) $theme->get('Name')); // Nombre visible del theme.

    // Detecta Astra por stylesheet, template o nombre del theme.
    return $stylesheet === 'astra' || $template === 'astra' || str_contains($name, 'astra');
  } // Fin de EDTA_Astra_Bridge::is_astra_active()

}