<?php
if (!defined('ABSPATH')) {
  exit; // Evita acceso directo al archivo.
}

final class EDTA_Admin {

  public const OPTION_KEY = 'edta_astra_settings'; // Clave donde se almacenan los ajustes del plugin.
  private const EDTA_PALETTE_SIZE = 9; // Cantidad de colores usados para Astra Global Colors.

  private const RESET_CONFIRM_TEXT = 'Easy Dark Theme for Astra'; // Texto requerido para confirmar el reset.

  private const SVG_ATTRS = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'; // Atributos SVG reutilizables.

  // Devuelve textos de ayuda para cada opción del panel admin.
  private function get_tooltips(): array {
    return [
      'control_mode' => 'Define cómo se elige el tema. Automático usa la preferencia del sistema; Botón permite que el usuario cambie manualmente.',
      'default_mode' => 'Solo aplica si el Modo de control está en Automático. Permite forzar Claro u Oscuro, o respetar la preferencia del sistema.',
      'remember_mode' => 'En modo Botón, recuerda el tema elegido por el usuario para mantenerlo entre visitas.',
      'toggle_style' => 'Define la apariencia del switch de cambio de tema.',
      'toggle_position' => 'Ubicación del botón flotante en pantalla. El espaciado Horizontal/Vertical siempre es positivo y se aplica automáticamente según la posición elegida (arriba/abajo e izquierda/derecha).',
      'toggle_visibility' => 'Permite ocultar el botón según el dispositivo (mobile/desktop) o esconderlo siempre.',
      'enable_transitions' => 'Aplica una transición breve SOLO durante el cambio entre temas. No queda activa permanentemente.',
      'palette_mode' => 'Free Preset Palette es la paleta gratuita del plugin y no puede modificarse. Para editar colores, seleccioná Custom Palette.',
      'use_theme_light_palette' => 'Si está activo, el tema Claro no usará los colores del plugin. En su lugar, se usarán los colores definidos por Astra.',
      'global_colors' => 'Mapea tu paleta a las variables de Astra (--ast-global-color-0..8). Esto afecta el diseño del theme cuando cambia el tema.',
      'export_settings' => 'Genera un archivo JSON con tu configuración actual para guardarla o moverla a otro sitio.',
      'import_settings' => 'Carga un archivo JSON exportado previamente y aplica esa configuración en este sitio.',
      'reset' => 'Restaura la configuración del plugin a valores por defecto. Requiere confirmación para evitar resets accidentales.',
      'custom_css' => 'CSS adicional aplicado al switch. Si pegás reglas completas (con llaves), se inyecta tal cual. Si pegás solo propiedades (ej: "border-radius: 999px;"), se envuelve automáticamente en .edta-toggle{...}.',
      'accessibility' => 'Opciones para mejorar accesibilidad (respeto de reduced-motion, foco visible, etc.). Recomendado dejarlo activado.',
    ];
  } // Fin de EDTA_Admin::get_tooltips()

  // Renderiza botón de ayuda asociado a un tooltip del panel.
  private function help_button(string $key): string {
    $html = '<button type="button" class="edta-help" data-edta-tip="' . esc_attr($key) . '" aria-label="' . esc_attr__('Información', 'easy-dark-theme-for-astra') . '">i</button>';

    return wp_kses($html, [
      'button' => [
        'type' => true,
        'class' => true,
        'data-edta-tip' => true,
        'aria-label' => true,
      ],
    ]);
  } // Fin de EDTA_Admin::help_button()

  // Devuelve SVG del ícono de sol (modo claro).
  private function icon_sun_svg(): string {
    return '<svg class="edta-ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false" ' . self::SVG_ATTRS . '>
      <circle cx="12" cy="12" r="4"></circle>
      <path d="M12 2v2"></path>
      <path d="M12 20v2"></path>
      <path d="M4.93 4.93l1.41 1.41"></path>
      <path d="M17.66 17.66l1.41 1.41"></path>
      <path d="M2 12h2"></path>
      <path d="M20 12h2"></path>
      <path d="M4.93 19.07l1.41-1.41"></path>
      <path d="M17.66 6.34l1.41-1.41"></path>
    </svg>';
  } // Fin de EDTA_Admin::icon_sun_svg()

  // Devuelve SVG del ícono de luna (modo oscuro).
  private function icon_moon_svg(): string {
    return '<svg class="edta-ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false" ' . self::SVG_ATTRS . '>
      <path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.8 6.8 0 0 0 9.8 9.8Z"></path>
    </svg>';
  } // Fin de EDTA_Admin::icon_moon_svg()

  // Renderiza ícono de paleta dividido en dos colores.
  private function icon_palette_split_svg(string $a, string $b): string {
    return '<svg class="edta-ico edta-ico-palette" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <defs>
        <clipPath id="edtaClipA">
          <circle cx="12" cy="12" r="9"></circle>
        </clipPath>
      </defs>

      <g clip-path="url(#edtaClipA)">
        <polygon points="3,3 21,3 3,21" fill="' . esc_attr($a) . '"></polygon>
        <polygon points="21,21 21,3 3,21" fill="' . esc_attr($b) . '"></polygon>
      </g>

      <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"></circle>
      <path d="M6 18L18 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
    </svg>';
  } // Fin de EDTA_Admin::icon_palette_split_svg()

  // Devuelve ícono de paleta free (azul/blanco).
  private function icon_palette_free_svg(): string {
    return $this->icon_palette_split_svg('#046BD2', '#FFFFFF');
  } // Fin de EDTA_Admin::icon_palette_free_svg()

  // Devuelve ícono de paleta custom (negro/blanco).
  private function icon_palette_custom_svg(): string {
    return $this->icon_palette_split_svg('#111111', '#FFFFFF');
  } // Fin de EDTA_Admin::icon_palette_custom_svg()

  // Inicializa hooks del panel admin.
  public function init(): void {
    add_filter('admin_body_class', [$this, 'admin_body_class']); // Aplica clases iniciales.

    add_action('admin_menu', [$this, 'register_menu']); // Registra menú del plugin.
    add_action('admin_init', [$this, 'register_settings']); // Registra settings.
    add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']); // Encola assets del admin.
    add_action('admin_notices', [$this, 'maybe_show_astra_notice']); // Muestra aviso si Astra no está activo.

    add_action('admin_post_edta_export_settings', [$this, 'handle_export_settings']); // Handler exportar ajustes.
    add_action('admin_post_edta_import_settings', [$this, 'handle_import_settings']); // Handler importar ajustes.
    add_action('admin_post_edta_reset_settings',  [$this, 'handle_reset_settings']);  // Handler resetear ajustes.
  } // Fin de EDTA_Admin::init()

  public function admin_body_class(string $classes): string {
    // Solo en la pantalla del plugin.
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || empty($screen->id)) return $classes;

    // Ajustá este check al screen id real de la página.
    if (strpos($screen->id, 'edta') === false) return $classes;

    $opts = get_option(self::OPTION_KEY, []);
    $palette_mode = isset($opts['palette_mode']) ? (string) $opts['palette_mode'] : 'free';

    if ($palette_mode === 'free') {
      $classes .= ' edta-preset-locked';
    }

    return $classes;
  } // Fin de EDTA_Admin::admin_body_class()

  // Registra el menú principal del plugin en el admin.
  public function register_menu(): void {
    add_menu_page(
      __('Easy Dark Theme for Astra', 'easy-dark-theme-for-astra'),
      __('Easy Dark Theme for Astra', 'easy-dark-theme-for-astra'),
      'manage_options',
      'edta-settings',
      [$this, 'render_page'],
      'dashicons-lightbulb',
      61
    );
  } // Fin de EDTA_Admin::register_menu()

  // Registra los ajustes del plugin y su callback de sanitización.
  public function register_settings(): void {
    register_setting(
      'edta_settings_group',
      self::OPTION_KEY,
      [
        'type'              => 'array',
        'sanitize_callback' => [$this, 'sanitize_settings'], // Sanitiza valores antes de guardar.
        'default'           => $this->default_settings(),
      ]
    );
  } // Fin de EDTA_Admin::register_settings()

  // Encola CSS/JS del panel admin y pasa datos al JavaScript.
  public function enqueue_assets(string $hook): void {
    if ($hook !== 'toplevel_page_edta-settings') return; // Solo carga assets en la página del plugin.

    wp_enqueue_style(
      'edta-admin',
      EDTA_PLUGIN_URL . 'assets/admin.css',
      [],
      EDTA_VERSION
    );

    // Encola color picker nativo de WordPress.
    wp_enqueue_script('wp-color-picker');
    wp_enqueue_style('wp-color-picker');

    wp_enqueue_script(
      'edta-admin',
      EDTA_PLUGIN_URL . 'assets/admin.js',
      ['jquery', 'wp-color-picker'],
      EDTA_VERSION,
      true
    );

    // Pasa textos localizados al JS del admin.
    wp_localize_script('edta-admin', 'EDTA_I18N', [
      'confirm_reset_phrase' => esc_html__('Easy Dark Theme for Astra', 'easy-dark-theme-for-astra'),
      'unsaved' => esc_html__('Cambios sin guardar', 'easy-dark-theme-for-astra'),
      'no_changes' => esc_html__('Sin cambios', 'easy-dark-theme-for-astra'),
    ]);

    // Expone paletas free para previews del admin.
    wp_add_inline_script('edta-admin', 'window.EDTA_PRESET_1 = ' . wp_json_encode([
      'light' => $this->free_light_palette(),
      'dark' => $this->free_dark_palette(),
    ]) . ';', 'before');
  
    // Expone tooltips al JS del admin.
    wp_add_inline_script(
      'edta-admin',
      'window.EDTA_ADMIN_TIPS = ' . wp_json_encode($this->get_tooltips()) . ';',
      'before'
    );

    // Expone SVGs del toggle para previews del admin.
    wp_add_inline_script('edta-admin', 'window.EDTA_TOGGLE_ICONS = ' . wp_json_encode([
      'sun' => $this->icon_sun_svg(),
      'moon' => $this->icon_moon_svg(),
    ]) . ';', 'before');
  } // Fin de EDTA_Admin::enqueue_assets()

  // Renderiza la página de configuración del plugin en el admin.
  public function render_page(): void {
    // Verifica permisos antes de renderizar.
    if (!current_user_can('manage_options')) return;

    $settings = get_option(self::OPTION_KEY, $this->default_settings()); // Ajustes actuales guardados.
    $defaults = $this->default_settings(); // Defaults para fallback.

    // Lee mensajes y errores desde la URL.
    $msg = isset($_GET['edta_msg']) ? sanitize_text_field((string) $_GET['edta_msg']) : '';
    $err = isset($_GET['edta_err']) ? sanitize_text_field((string) $_GET['edta_err']) : '';

    // Tooltips: se exponen en enqueue_assets() vía wp_add_inline_script().

    // Renderiza contenedor principal del admin.
    echo '<div class="wrap edta-admin-wrap">';
    echo '<h1>' . esc_html__('Easy Dark Theme for Astra', 'easy-dark-theme-for-astra') . '</h1>';

    // Renderiza notices según parámetros de URL.
    if ($msg) {
      $text = '';
      if ($msg === 'import_ok')  $text = __('Configuración importada correctamente.', 'easy-dark-theme-for-astra');
      if ($msg === 'reset_ok')   $text = __('Configuración restablecida a valores por defecto.', 'easy-dark-theme-for-astra');
      if ($msg === 'export_ok')  $text = __('Exportación generada.', 'easy-dark-theme-for-astra');
      if ($text) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($text) . '</p></div>';
    }

    if ($err) {
      $text = '';
      if ($err === 'import_bad')        $text = __('No se pudo importar: el archivo JSON es inválido o no contiene settings.', 'easy-dark-theme-for-astra');
      if ($err === 'import_upload')     $text = __('No se pudo importar: error de subida del archivo.', 'easy-dark-theme-for-astra');
      if ($err === 'import_cap')        $text = __('No autorizado.', 'easy-dark-theme-for-astra');
      if ($err === 'reset_confirm')     $text = __('Confirmación incorrecta. No se aplicó el reset.', 'easy-dark-theme-for-astra');
      if ($err === 'reset_cap')         $text = __('No autorizado.', 'easy-dark-theme-for-astra');
      if ($text) echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($text) . '</p></div>';
    }

    // Renderiza layout: main + sidebar.
    echo '<div class="edta-admin">';
    echo '  <div class="edta-admin__main">';

    // Renderiza formulario principal de settings.
    echo '<form id="edta-settings-form" method="post" action="options.php">';
    settings_fields('edta_settings_group'); // Nonce y campos ocultos del Settings API.
    do_settings_sections('edta_settings_group');

    // Renderiza card: Control.
    $control_mode = $settings['control_mode'] ?? 'auto';

    echo '<section class="edta-card" id="edta-sec-control">';
    echo '  <div class="edta-card__header"><h2 class="edta-card__title">' . esc_html__('Control', 'easy-dark-theme-for-astra') . '</h2></div>';
    echo '  <div class="edta-card__body">';

    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr><th scope="row">' . esc_html__('Modo de control', 'easy-dark-theme-for-astra') . ' ' . $this->help_button('control_mode') . '</th><td>';
    echo '<fieldset id="edta-control-mode">';
    echo '<label style="margin-right:14px;"><input type="radio" name="' . esc_attr(self::OPTION_KEY) . '[control_mode]" value="auto" ' . checked($control_mode, 'auto', false) . '> ' . esc_html__('Auto (sistema)', 'easy-dark-theme-for-astra') . '</label>';
    echo '<label><input type="radio" name="' . esc_attr(self::OPTION_KEY) . '[control_mode]" value="button" ' . checked($control_mode, 'button', false) . '> ' . esc_html__('Botón', 'easy-dark-theme-for-astra') . '</label>';
    echo '</fieldset>';
    echo '</td></tr>';

    echo '<tr id="edta-auto-fields"><th scope="row">' . esc_html__('Modo por defecto', 'easy-dark-theme-for-astra') . ' ' . $this->help_button('default_mode') . '</th><td>';
    $default_mode = $settings['default_mode'] ?? 'system';
    echo '<select id="edta-default-mode" name="' . esc_attr(self::OPTION_KEY) . '[default_mode]">';
    foreach (['system' => 'Sistema', 'light' => 'Claro', 'dark' => 'Oscuro'] as $k => $label) {
      echo '<option value="' . esc_attr($k) . '" ' . selected($default_mode, $k, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';

    $remember = !empty($settings['remember_mode']);
    echo '<tr><th scope="row">' . esc_html__('Recordar preferencia', 'easy-dark-theme-for-astra') . ' ' . $this->help_button('remember_mode') . '</th><td>';
    echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[remember_mode]" value="1" ' . checked($remember, true, false) . '> ' . esc_html__('Guardar el modo elegido en el navegador (solo modo botón).', 'easy-dark-theme-for-astra') . '</label>';
    echo '</td></tr>';

    echo '<tr id="edta-button-fields"><th scope="row">' . esc_html__('Estilo del botón', 'easy-dark-theme-for-astra') . ' ' . $this->help_button('toggle_style') . '</th><td>';

    $toggle_style = $settings['toggle_style'] ?? 'icon';

    echo '<select id="edta-toggle-style" name="' . esc_attr(self::OPTION_KEY) . '[toggle_style]">';
    echo '<option value="icon" ' . selected($toggle_style, 'icon', false) . '>Icon</option>';
    echo '<option value="text" ' . selected($toggle_style, 'text', false) . '>Text</option>';
    echo '<option value="pill" ' . selected($toggle_style, 'pill', false) . '>Pill</option>';
    echo '</select>';

    // Renderiza previsualización del toggle.
    echo '<div class="edta-toggle-preview" data-preview-style="' . esc_attr($toggle_style) . '">';
      echo '<div class="edta-toggle-preview__toolbar">';
        echo '<span class="edta-toggle-preview__label">' . esc_html__('Previsualización', 'easy-dark-theme-for-astra') . '</span>';
        echo '<div class="edta-toggle-preview__segmented" role="group" aria-label="' . esc_attr__('Modo', 'easy-dark-theme-for-astra') . '">';
          echo '<button type="button" class="edta-preview-mode is-active" data-edta-preview-mode="light">' . esc_html__('Light', 'easy-dark-theme-for-astra') . '</button>';
          echo '<button type="button" class="edta-preview-mode" data-edta-preview-mode="dark">' . esc_html__('Dark', 'easy-dark-theme-for-astra') . '</button>';
        echo '</div>';
      echo '</div>';

      echo '<div class="edta-toggle-preview__canvas" id="edta-toggle-preview-canvas">';
        echo '<button type="button" class="edta-toggle edta-toggle--preview" id="edta-toggle-preview-btn" data-style="' . esc_attr($toggle_style) . '" aria-label="' . esc_attr__('Vista previa', 'easy-dark-theme-for-astra') . '"></button>';
      echo '</div>';
    echo '</div>';

    echo '</td></tr>';

    echo '<tr id="edta-button-fields-2"><th scope="row">' . esc_html__('Posición del botón', 'easy-dark-theme-for-astra') . ' ' . $this->help_button('toggle_position') . '</th><td>';

    $toggle_pos = $settings['toggle_position'] ?? 'br';
    $off_x = isset($settings['toggle_offset_x']) ? (int) $settings['toggle_offset_x'] : 18;
    $off_y = isset($settings['toggle_offset_y']) ? (int) $settings['toggle_offset_y'] : 18;

    echo '<div class="edta-pos-controls">';

    // Renderiza controles de posición y offsets.
    echo '<div class="edta-pos-field">';
      echo '<label class="edta-pos-label" for="edta-toggle-position">' . esc_html__('Posición', 'easy-dark-theme-for-astra') . '</label>';
      echo '<select id="edta-toggle-position" name="' . esc_attr(self::OPTION_KEY) . '[toggle_position]">';
      foreach (['br' => 'Bottom Right', 'bl' => 'Bottom Left', 'tr' => 'Top Right', 'tl' => 'Top Left'] as $k => $label) {
        echo '<option value="' . esc_attr($k) . '" ' . selected($toggle_pos, $k, false) . '>' . esc_html($label) . '</option>';
      }
      echo '</select>';
      echo '</div>';

      echo '<div class="edta-pos-field">';
        echo '<label class="edta-pos-label" for="edta-toggle-offset-x">' . esc_html__('Espaciado horizontal', 'easy-dark-theme-for-astra') . '</label>';
        echo '<div class="edta-pos-input">';
          echo '<input id="edta-toggle-offset-x" class="edta-pos-number" type="number" min="0" step="1" '
            . 'name="' . esc_attr(self::OPTION_KEY) . '[toggle_offset_x]" value="' . esc_attr($off_x) . '" />';
          echo '<span class="edta-pos-unit">px</span>';
        echo '</div>';
      echo '</div>';

      echo '<div class="edta-pos-field">';
        echo '<label class="edta-pos-label" for="edta-toggle-offset-y">' . esc_html__('Espaciado vertical', 'easy-dark-theme-for-astra') . '</label>';
        echo '<div class="edta-pos-input">';
          echo '<input id="edta-toggle-offset-y" class="edta-pos-number" type="number" min="0" step="1" '
            . 'name="' . esc_attr(self::OPTION_KEY) . '[toggle_offset_y]" value="' . esc_attr($off_y) . '" />';
          echo '<span class="edta-pos-unit">px</span>';
        echo '</div>';
      echo '</div>';

    echo '</div>';

    echo '</td></tr>';

    echo '<tr id="edta-button-fields-3"><th scope="row">' . esc_html__('Visibilidad del botón', 'easy-dark-theme-for-astra') . ' ' . $this->help_button('toggle_visibility') . '</th><td>';
    $toggle_vis = $settings['toggle_visibility'] ?? 'show_all';
    echo '<select id="edta-toggle-visibility" name="' . esc_attr(self::OPTION_KEY) . '[toggle_visibility]">';
    foreach (['show_all' => 'Mostrar siempre', 'hide_mobile' => 'Ocultar en mobile', 'hide_desktop' => 'Ocultar en desktop', 'hide_both' => 'Ocultar siempre'] as $k => $label) {
      echo '<option value="' . esc_attr($k) . '" ' . selected($toggle_vis, $k, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';

    echo '</tbody></table>';

    echo '  </div>';
    echo '</section>';

    // Renderiza card: Animación.
    echo '<section class="edta-card" id="edta-sec-animation">';
    echo '  <div class="edta-card__header"><h2 class="edta-card__title">Animación</h2></div>';
    echo '  <div class="edta-card__body">';

    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr>';
    echo '  <th scope="row">Transición ' . wp_kses( $this->help_button('enable_transitions'), array( 'button' => array( 'type' => true, 'class' => true, 'data-edta-tip' => true, 'aria-label' => true ) ) ) . '</th>';
    echo '  <td>';
    echo '    <label>';
    echo '      <input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[enable_transitions]" value="1" ' . checked(!empty($settings['enable_transitions']), true, false) . ' />';
    echo '      Activar transición al cambiar tema';
    echo '    </label>';
    echo '  </td>';
    echo '</tr>';
    echo '</tbody></table>';

    echo '  </div>';
    echo '</section>';

    // Renderiza card: CSS personalizado.
    echo '<section class="edta-card" id="edta-sec-custom-css">';
    echo '  <div class="edta-card__header"><h2 class="edta-card__title">' . esc_html__('CSS personalizado', 'easy-dark-theme-for-astra') . '</h2></div>';
    echo '  <div class="edta-card__body">';

    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr>';
    echo '  <th scope="row">' . esc_html__('CSS para el switch', 'easy-dark-theme-for-astra') . ' ' . $this->help_button('custom_css') . '</th>';
    echo '  <td>';

    $custom_css = is_string($settings['custom_css'] ?? null) ? (string)$settings['custom_css'] : '';
    echo '    <textarea id="edta-custom-css" name="' . esc_attr(self::OPTION_KEY) . '[custom_css]" rows="7" spellcheck="false" placeholder="Ej 1 (propiedades):&#10;border-radius: 999px;&#10;box-shadow: 0 6px 22px rgba(0,0,0,.18);&#10;&#10;Ej 2 (reglas completas):&#10;.edta-toggle { border-radius: 999px; }&#10;.edta-toggle .edta-toggle__knob { opacity: .92; }">' . esc_textarea($custom_css) . '</textarea>';
    echo '    <p class="description">Se inyecta en el frontend y aplica sobre el switch (<code>.edta-toggle</code>).</p>';

    echo '  </td>';
    echo '</tr>';
    echo '</tbody></table>';

    echo '  </div>';
    echo '</section>';

    // Renderiza card: Astra Global Colors.
    $palette_mode = (string)($settings['palette_mode'] ?? 'free');
    if ($palette_mode !== 'free' && $palette_mode !== 'custom') $palette_mode = 'free';

    echo '<section class="edta-card" id="edta-sec-astra">';
    echo '  <div class="edta-card__header"><h2 class="edta-card__title">' . esc_html__('Astra Global Colors', 'easy-dark-theme-for-astra') . '</h2></div>';
    echo '  <div class="edta-card__body">';

    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr><th scope="row">' . esc_html__('Paleta', 'easy-dark-theme-for-astra') . ' ' . $this->help_button('palette_mode') . '</th><td>';
    echo '<fieldset id="edta-palette-mode">';

    echo '<label class="edta-palette-choice" style="margin-right:14px;">'
      . '<input type="radio" name="' . esc_attr(self::OPTION_KEY) . '[palette_mode]" value="free" ' . checked($palette_mode, 'free', false) . '>'
      . $this->icon_palette_free_svg()
      . '<span>' . esc_html__('Free Preset Palette', 'easy-dark-theme-for-astra') . '</span>'
      . '</label>';

    echo '<label class="edta-palette-choice">'
      . '<input type="radio" name="' . esc_attr(self::OPTION_KEY) . '[palette_mode]" value="custom" ' . checked($palette_mode, 'custom', false) . '>'
      . $this->icon_palette_custom_svg()
      . '<span>' . esc_html__('Custom Palette', 'easy-dark-theme-for-astra') . '</span>'
      . '</label>';

    echo '</fieldset>';
    echo '</td></tr>';

    // Renderiza opción para respetar paleta light del theme.
    $use_theme_light = !empty($settings['use_theme_light_palette']);
    echo '<tr><th scope="row">' . esc_html__('Usar colores del tema (Claro)', 'easy-dark-theme-for-astra') . ' ' . $this->help_button('use_theme_light_palette') . '</th><td>';
    echo '<label><input id="edta-use-theme-light" type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[use_theme_light_palette]" value="1" ' . checked($use_theme_light, true, false) . '> ' . esc_html__('Respetar colores del tema en modo Claro (no aplicar paleta Claro del plugin).', 'easy-dark-theme-for-astra') . '</label>';
    echo '</td></tr>';

    $custom_light = is_array($settings['light_palette'] ?? null) ? $settings['light_palette'] : $defaults['light_palette'];
    $custom_dark = is_array($settings['dark_palette'] ?? null) ? $settings['dark_palette'] : $defaults['dark_palette'];

    $is_free = ($palette_mode === 'free');

    $free_light = $this->free_light_palette();
    $free_dark  = $this->free_dark_palette();

    $display_light = $is_free ? $free_light : $custom_light;
    $display_dark  = $is_free ? $free_dark  : $custom_dark;

    $disabled_light = $use_theme_light;

    // Renderiza label de sección de paletas.
    echo '<tr>';
    echo '  <th scope="row">' . esc_html__('Colores globales', 'easy-dark-theme-for-astra') . ' ' . $this->help_button('global_colors') . '</th>';
    echo '  <td></td>';
    echo '</tr>';

    // Renderiza grillas de paletas (light/dark).
    echo '<tr class="edta-row-palettes">';
    echo '  <td colspan="2">';

    echo '<div class="edta-palettes-2col">';

    echo '<div class="edta-palettes-col">';

    echo '<div class="edta-palette-head">';
      echo '<h3 class="edta-palettes-title edta-palettes-title--light">' .
        $this->icon_sun_svg() .
        '<span>' . esc_html__('Claro', 'easy-dark-theme-for-astra') . '</span>' .
      '</h3>';
      echo '<span id="edta-light-lock-badge" class="edta-palette-status" aria-live="polite"></span>';
    echo '</div>';

    $disabled_light = $use_theme_light || $is_free;
    echo '<div id="edta-light-palette-grid" data-edta-disabled="' . esc_attr($disabled_light ? '1' : '0') . '">';

    // Renderiza inputs de paleta light.
    for ($i = 0; $i < self::EDTA_PALETTE_SIZE; $i++) {
      $val = $display_light[$i] ?? '#000000';
      $custom_val = $custom_light[$i] ?? $val;
      $role = $this->astra_role_for_index($i);
      $field_name = self::OPTION_KEY . '[light_palette][' . $i . ']';

      echo '<div class="edta-palette-card" style="border:1px solid #dcdcde;border-radius:8px;overflow:hidden;background:#fff;">';
      echo '<div class="edta-swatch" style="height:42px;background:' . esc_attr($val) . ';"></div>';
      echo '<div style="padding:10px;">';

      echo '<div style="display:flex;flex-direction:column;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:6px;">';
      echo '  <div style="font-weight:600;">' . esc_html($role) . '</div>';
      echo '  <div style="font-size:12px;color:#646970;"><code>--ast-global-color-' . (int) $i . '</code></div>';
      echo '</div>';

      echo '<input type="text" class="edta-hex-inline edta-hex-light" ' . ($disabled_light ? 'disabled' : '') .
        ' value="' . esc_attr($val) . '" data-edta-hex="1" data-edta-custom="' . esc_attr($custom_val) . '" style="width:120px;margin:0 0 8px 0;" />';

      echo '<input class="edta-color-field edta-light-input wp-color-picker" type="text" ' . ($disabled_light ? 'disabled' : '') .
        ' name="' . esc_attr($field_name) . '"' .
        ' value="' . esc_attr($val) . '"' .
        ' data-default-color="' . esc_attr($val) . '"' .
        ' data-edta-custom="' . esc_attr($custom_val) . '"' .
        ' style="width:120px;" />';

      echo '</div></div>';
    }

    echo '</div>'; // #edta-light-palette-grid

    // Renderiza hint cuando la paleta light está deshabilitada.
    echo '<p id="edta-light-disabled-hint" style="margin-top:10px;display:' . ($disabled_light ? 'block' : 'none') . ';"><em>' . esc_html__('La paleta Claro está desactivada porque “Usar colores del tema” está activo.', 'easy-dark-theme-for-astra') . '</em></p>';

    echo '</div>'; // .edta-palettes-col

    echo '<div class="edta-palettes-col">';

    echo '<div class="edta-palette-head">';
      echo '<h3 class="edta-palettes-title edta-palettes-title--dark">' .
        $this->icon_moon_svg() .
        '<span>' . esc_html__('Oscuro', 'easy-dark-theme-for-astra') . '</span>' .
      '</h3>';
      echo '<span id="edta-dark-lock-badge" class="edta-palette-status" aria-live="polite"></span>';
    echo '</div>';

    echo '<div id="edta-dark-palette-grid">';

    // Renderiza inputs de paleta dark.
    for ($i = 0; $i < self::EDTA_PALETTE_SIZE; $i++) {
      $val = $display_dark[$i] ?? '#000000';
      $custom_val = $custom_dark[$i] ?? $val;
      $role = $this->astra_role_for_index($i);
      $field_name = self::OPTION_KEY . '[dark_palette][' . $i . ']';

      echo '<div class="edta-palette-card" style="border:1px solid #dcdcde;border-radius:8px;overflow:hidden;background:#fff;">';
      echo '<div class="edta-swatch" style="height:42px;background:' . esc_attr($val) . ';"></div>';
      echo '<div style="padding:10px;">';

      echo '<div style="display:flex;flex-direction:column;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:6px;">';
      echo '  <div style="font-weight:600;">' . esc_html($role) . '</div>';
      echo '  <div style="font-size:12px;color:#646970;"><code>--ast-global-color-' . (int) $i . '</code></div>';
      echo '</div>';

      echo '<input type="text" class="edta-hex-inline edta-hex-dark" value="' . esc_attr($val) . '" data-edta-hex="1" data-edta-custom="' . esc_attr($custom_val) . '" style="width:120px;margin:0 0 8px 0;" />';

      echo '<input class="edta-color-field edta-dark-input wp-color-picker" type="text"' .
        ' name="' . esc_attr($field_name) . '"' .
        ' value="' . esc_attr($val) . '"' .
        ' data-default-color="' . esc_attr($val) . '"' .
        ' data-edta-custom="' . esc_attr($custom_val) . '"' .
        ' style="width:120px;" />';

      echo '</div></div>';
    }

    echo '</div>'; // #edta-dark-palette-grid

    echo '</div>'; // .edta-palettes-col

    echo '</div>'; // .edta-palettes-2col

    echo '  </td>';
    echo '</tr>';

    echo '</tbody></table>';

    echo '  </div>';
    echo '</section>';

    // Renderiza card: Accesibilidad.
    echo '<section class="edta-card" id="edta-sec-accessibility">';
    echo '  <div class="edta-card__header"><h2 class="edta-card__title">' . esc_html__('Accesibilidad', 'easy-dark-theme-for-astra') . '</h2></div>';
    echo '  <div class="edta-card__body">';

    echo '<p class="edta-muted" style="margin-top:0;">' . esc_html__('Estas opciones ayudan a que el switch sea más cómodo para usuarios con sensibilidad al movimiento o navegación por teclado.', 'easy-dark-theme-for-astra') . '</p>';

    echo '<table class="form-table" role="presentation"><tbody>';

    // Renderiza opción: reduced motion.
    $a11y_reduce_motion = !empty($settings['a11y_reduce_motion']);
    echo '<tr>';
    echo '  <th scope="row">' . esc_html__('Respetar “reduced motion”', 'easy-dark-theme-for-astra') . ' ' . $this->help_button('accessibility') . '</th>';
    echo '  <td>';
    echo '    <label>';
    echo '      <input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[a11y_reduce_motion]" value="1" ' . checked($a11y_reduce_motion, true, false) . ' />';
    echo '      ' . esc_html__('Si el sistema pide reducir animaciones, el plugin evita transiciones al cambiar tema.', 'easy-dark-theme-for-astra');
    echo '    </label>';
    echo '  </td>';
    echo '</tr>';

    // Renderiza opción: foco visible.
    $a11y_focus_ring = !empty($settings['a11y_focus_ring']);
    echo '<tr>';
    echo '  <th scope="row">' . esc_html__('Foco visible (teclado)', 'easy-dark-theme-for-astra') . '</th>';
    echo '  <td>';
    echo '    <label>';
    echo '      <input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[a11y_focus_ring]" value="1" ' . checked($a11y_focus_ring, true, false) . ' />';
    echo '      ' . esc_html__('Mejora el contorno de foco del switch cuando se navega con Tab.', 'easy-dark-theme-for-astra');
    echo '    </label>';
    echo '  </td>';
    echo '</tr>';

    echo '</tbody></table>';
    echo '  </div>';
    echo '</section>';

    // Renderiza submit oculto para disparo desde el sidebar.
    echo '<input type="submit" id="edta-hidden-submit" style="display:none" value="' . esc_attr__('Guardar cambios', 'easy-dark-theme-for-astra') . '">';

    echo '</form>';

    // Renderiza card: Herramientas.
    echo '<section class="edta-card" id="edta-sec-tools" style="margin-top:16px;">';
    echo '  <div class="edta-card__header"><h2 class="edta-card__title">' . esc_html__('Herramientas', 'easy-dark-theme-for-astra') . '</h2></div>';
    echo '  <div class="edta-card__body">';

    echo '<h3>' . esc_html__('Exportar configuración', 'easy-dark-theme-for-astra') . ' ' . $this->help_button('export_settings') . '</h3>';
    echo '<p>' . esc_html__('Descarga un JSON con todos los ajustes. Nota: se exporta solo la paleta personalizada.', 'easy-dark-theme-for-astra') . '</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="edta_export_settings">';
    wp_nonce_field('edta_export_settings', 'edta_nonce'); // Nonce para exportación.
    submit_button(__('Exportar JSON', 'easy-dark-theme-for-astra'), 'secondary', 'submit', false, ['title' => __('Exporta un archivo JSON con la configuración.', 'easy-dark-theme-for-astra')]);
    echo '</form>';

    echo '<h3 style="margin-top:18px;">' . esc_html__('Importar configuración', 'easy-dark-theme-for-astra') . ' ' . $this->help_button('import_settings') . '</h3>';
    echo '<p>' . esc_html__('Subí un JSON exportado previamente. La importación aplica como “Paleta personalizada”.', 'easy-dark-theme-for-astra') . '</p>';
    echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="edta_import_settings">';
    wp_nonce_field('edta_import_settings', 'edta_nonce'); // Nonce para importación.
    echo '<input type="file" name="edta_import_file" accept="application/json" required style="display:block;margin-bottom:10px;">';
    submit_button(__('Importar JSON','easy-dark-theme-for-astra'),'secondary', ['title' => __('Importa un archivo JSON con la configuración.', 'easy-dark-theme-for-astra')]);
    echo '</form>';

    echo '<h3 style="margin-top:18px;">' . esc_html__('Restablecer a valores por defecto', 'easy-dark-theme-for-astra') . ' ' . $this->help_button('reset') . '</h3>';
    echo '<p>' . esc_html__('Esto reemplaza TODOS los ajustes por los valores por defecto del plugin.', 'easy-dark-theme-for-astra') . '</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="edta_reset_settings">';
    wp_nonce_field('edta_reset_settings', 'edta_nonce'); // Nonce para reset.
    echo '<p style="margin:0 0 6px 0;"><strong>' . esc_html__('Para confirmar, escribí:', 'easy-dark-theme-for-astra') . ' </strong><code>' . esc_html(self::RESET_CONFIRM_TEXT) . '</code></p>';
    echo '<input id="edta-reset-confirm" type="text" name="edta_confirm_name" value="" style="width:320px;" placeholder="' . esc_attr(self::RESET_CONFIRM_TEXT) . '" autocomplete="off">';
    submit_button(__('Restablecer ahora', 'easy-dark-theme-for-astra'), 'delete', 'edta_reset_submit', false, ['id' => 'edta-reset-submit'], ['title' => __('Reinicia la configuración del plugin a los valores por defecto.', 'easy-dark-theme-for-astra')]);
    echo '</form>';

    echo '  </div>';
    echo '</section>';

    echo '  </div>'; // .edta-admin__main

    // Renderiza sidebar con navegación y guardado.
    echo '<aside class="edta-admin__side">';
    echo '  <div class="edta-card edta-card--side">';
    echo '    <div class="edta-card__header"><h2 class="edta-card__title">' . esc_html__('Atajos', 'easy-dark-theme-for-astra') . '</h2></div>';
    echo '    <div class="edta-card__body">';
    echo '      <ul class="edta-side-links edta-side-links--icons">';
    echo '        <li><a href="#edta-sec-control" data-edta-nav="edta-sec-control">'
                  . '<svg class="edta-side-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v3M12 18v3M4.22 5.22l2.12 2.12M17.66 16.66l2.12 2.12M3 12h3M18 12h3M5.22 19.78l2.12-2.12M16.66 7.34l2.12-2.12"/><circle cx="12" cy="12" r="4"/></svg>'
                  . '<span>' . esc_html__('Control', 'easy-dark-theme-for-astra') . '</span>'
                . '</a></li>';
    echo '        <li><a href="#edta-sec-animation" data-edta-nav="edta-sec-animation">'
                  . '<svg class="edta-side-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h4l3-8 4 16 3-8h4"/></svg>'
                  . '<span>' . esc_html__('Animación', 'easy-dark-theme-for-astra') . '</span>'
                . '</a></li>';
    echo '        <li><a href="#edta-sec-custom-css" data-edta-nav="edta-sec-custom-css">'
                  . '<svg class="edta-side-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 9l-3 3 3 3"/><path d="M16 9l3 3-3 3"/><path d="M14 7l-4 10"/></svg>'
                  . '<span>' . esc_html__('CSS personalizado', 'easy-dark-theme-for-astra') . '</span>'
                . '</a></li>';
    echo '        <li><a href="#edta-sec-astra" data-edta-nav="edta-sec-astra">'
                  . '<svg class="edta-side-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l2.5 6H21l-5 4 2 7-6-4-6 4 2-7-5-4h6.5z"/></svg>'
                  . '<span>' . esc_html__('Astra Global Colors', 'easy-dark-theme-for-astra') . '</span>'
                . '</a></li>';
    echo '        <li><a href="#edta-sec-accessibility" data-edta-nav="edta-sec-accessibility">'
                  . '<svg class="edta-side-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a2 2 0 1 0 0 4a2 2 0 0 0 0-4z"/><path d="M4 7h16"/><path d="M12 7v15"/><path d="M7 11l5-3 5 3"/><path d="M7 22l5-7 5 7"/></svg>'
                  . '<span>' . esc_html__('Accesibilidad', 'easy-dark-theme-for-astra') . '</span>'
                . '</a></li>';
    echo '        <li><a href="#edta-sec-tools" data-edta-nav="edta-sec-tools">'
                  . '<svg class="edta-side-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5.6 5.6l-6.1 6.1 2 2 6.1-6.1a4 4 0 0 0 5.6-5.6l-2.3 2.3-2.3-2.3z"/></svg>'
                  . '<span>' . esc_html__('Herramientas', 'easy-dark-theme-for-astra') . '</span>'
                  . '</a></li>';
    echo '      </ul>';
    echo '      <p class="edta-side-hint">' .
                  esc_html__('Usá el icono', 'easy-dark-theme-for-astra') . ' ' .
                  '<span class="edta-help edta-help--static" aria-hidden="true">i</span>' .
                  ' ' . esc_html__('para ver ayuda contextual.', 'easy-dark-theme-for-astra') .
                '</p>';
    echo '      <div class="edta-side-savebar" role="region" aria-label="' . esc_attr__('Guardado', 'easy-dark-theme-for-astra') . '">';
    echo '        <div class="edta-side-savebar__status">';
    echo '          <span id="edta-unsaved" class="edta-unsaved" aria-live="polite">' . esc_html__('Sin cambios', 'easy-dark-theme-for-astra') . '</span>';
    echo '        </div>';
    echo '        <button type="button" class="button button-primary edta-side-save" id="edta-side-save" disabled aria-disabled="true">'
      . esc_html__('Guardar cambios', 'easy-dark-theme-for-astra') .
    '</button>';
    echo '      </div>';
    echo '    </div>';

    echo '  </div>';

    // Renderiza sidebar: guía rápida.
    echo '  <div class="edta-card edta-card--side" id="edta-side-guide">';
    echo '    <div class="edta-card__header"><h2 class="edta-card__title">' . esc_html__('Guía rápida', 'easy-dark-theme-for-astra') . '</h2></div>';
    echo '    <div class="edta-card__body">';

    echo '      <div class="edta-guide">';

    echo '        <div class="edta-guide__item">
                  <button type="button" class="edta-guide__toggle">Uso del Shortcode</button>
                  <div class="edta-guide__content">
                    <p>Puedes insertar el switch manualmente usando el shortcode:</p>
                    <code>[edta_toggle]</code>
                    <p>Puedes colocarlo en páginas, entradas, headers personalizados o constructores visuales.</p>
                  </div>
                </div>';

    echo '        <div class="edta-guide__item">
                  <button type="button" class="edta-guide__toggle">Uso como Widget</button>
                  <div class="edta-guide__content">
                    <p>Ve a <strong>Apariencia → Widgets</strong> y añade el widget <em>Easy Dark Theme Toggle</em> en el área deseada.</p>
                    <p>Ideal para footer, sidebar o header.</p>
                  </div>
                </div>';

    echo '        <div class="edta-guide__item">
                  <button type="button" class="edta-guide__toggle">Cómo funcionan las paletas</button>
                  <div class="edta-guide__content">
                    <p>El plugin no detecta automáticamente los colores de tu sitio.</p>
                    <p>Debes configurar primero los colores base en <strong>Astra → Personalizar → Global Colors</strong>.</p>
                    <p>Luego el plugin reemplaza esos valores utilizando las paletas Light/Dark configuradas aquí.</p>
                  </div>
                </div>';

    echo '      </div>'; // .edta-guide

    echo '    </div>'; // .edta-card__body
    echo '  </div>';   // .edta-card

    echo '</aside>';

    echo '</div>'; // .edta-admin

    echo '</div>'; // .wrap
  } // Fin de EDTA_Admin::render_page()

  // Exporta los ajustes del plugin como archivo JSON.
  public function handle_export_settings(): void {
    // Verifica permisos del usuario.
    if (!current_user_can('manage_options')) {
      wp_safe_redirect(add_query_arg(['page' => 'edta-settings', 'edta_err' => 'export_cap'], admin_url('admin.php')));
      exit;
    }

    // Valida nonce del formulario.
    check_admin_referer('edta_export_settings', 'edta_nonce');

    // Obtiene y sanitiza los ajustes actuales.
    $settings = get_option(self::OPTION_KEY, $this->default_settings());
    $settings = $this->sanitize_settings(is_array($settings) ? $settings : []);

    // Construye payload de exportación.
    $payload = [
      'plugin'      => 'easy-dark-theme-for-astra',
      'version'     => defined('EDTA_VERSION') ? EDTA_VERSION : null,
      'exported_at' => gmdate('c'),
      'settings'    => $settings,
    ];

    $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    // Fuerza descarga del archivo JSON.
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=edta-settings-' . gmdate('Ymd-His') . '.json');
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON export download, not HTML output.
    echo $json ? $json : '{}';
    exit;
  } // Fin de EDTA_Admin::handle_export_settings()

  // Importa ajustes del plugin desde un archivo JSON.
  public function handle_import_settings(): void {
    // Verifica permisos del usuario.
    if (!current_user_can('manage_options')) {
      wp_safe_redirect(add_query_arg(['page' => 'edta-settings', 'edta_err' => 'import_cap'], admin_url('admin.php')));
      exit;
    }

    // Valida nonce del formulario.
    check_admin_referer('edta_import_settings', 'edta_nonce');

    // Valida archivo subido.
    if (empty($_FILES['edta_import_file']) || !is_array($_FILES['edta_import_file'])) {
      wp_safe_redirect(add_query_arg(['page' => 'edta-settings', 'edta_err' => 'import_upload'], admin_url('admin.php')));
      exit;
    }

    $f = $_FILES['edta_import_file'];
    if (!empty($f['error'])) {
      wp_safe_redirect(add_query_arg(['page' => 'edta-settings', 'edta_err' => 'import_upload'], admin_url('admin.php')));
      exit;
    }

    // Limita tamaño del archivo importado.
    $max_bytes = 512 * 1024; // 512KB
    $size = isset($f['size']) ? (int) $f['size'] : 0;
    if ($size <= 0 || $size > $max_bytes) {
      wp_safe_redirect(add_query_arg(['page' => 'edta-settings', 'edta_err' => 'import_upload'], admin_url('admin.php')));
      exit;
    }

    // Valida path temporal del archivo.
    $path = $f['tmp_name'] ?? '';
    if (!$path || !is_readable($path)) {
      wp_safe_redirect(add_query_arg(['page' => 'edta-settings', 'edta_err' => 'import_upload'], admin_url('admin.php')));
      exit;
    }

    // Lee contenido del archivo.
    $raw = file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
      wp_safe_redirect(add_query_arg(['page' => 'edta-settings', 'edta_err' => 'import_bad'], admin_url('admin.php')));
      exit;
    }

    // Decodifica JSON.
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
      wp_safe_redirect(add_query_arg(['page' => 'edta-settings', 'edta_err' => 'import_bad'], admin_url('admin.php')));
      exit;
    }

    // Extrae ajustes desde payload o raíz.
    $settings = $decoded['settings'] ?? $decoded;
    if (!is_array($settings)) {
      wp_safe_redirect(add_query_arg(['page' => 'edta-settings', 'edta_err' => 'import_bad'], admin_url('admin.php')));
      exit;
    }

    // Sanitiza y guarda ajustes importados.
    $san = $this->sanitize_settings($settings);
    update_option(self::OPTION_KEY, $san);

    wp_safe_redirect(add_query_arg(['page' => 'edta-settings', 'edta_msg' => 'import_ok'], admin_url('admin.php')));
    exit;
  } // Fin de EDTA_Admin::handle_import_settings()

  // Restaura los ajustes del plugin a valores por defecto.
  public function handle_reset_settings(): void {
    // Verifica permisos del usuario.
    if (!current_user_can('manage_options')) {
      wp_safe_redirect(add_query_arg(['page' => 'edta-settings', 'edta_err' => 'reset_cap'], admin_url('admin.php')));
      exit;
    }

    // Valida nonce del formulario.
    check_admin_referer('edta_reset_settings', 'edta_nonce');

    // Valida texto de confirmación manual.
    $typed = isset($_POST['edta_confirm_name']) ? trim((string) wp_unslash($_POST['edta_confirm_name'])) : '';
    if ($typed !== self::RESET_CONFIRM_TEXT) {
      wp_safe_redirect(add_query_arg(['page' => 'edta-settings', 'edta_err' => 'reset_confirm'], admin_url('admin.php')));
      exit;
    }

    // Reconstruye valores por defecto forzando paleta free.
    $defaults = $this->default_settings();
    $defaults['palette_mode']  = 'free';
    $defaults['light_palette'] = $this->free_light_palette();
    $defaults['dark_palette']  = $this->free_dark_palette();

    // Elimina opción existente para evitar residuos o merges.
    delete_option(self::OPTION_KEY);

    // Guarda nuevamente los defaults como opción limpia.
    add_option(self::OPTION_KEY, $defaults);

    // Limpia cache por si existe object cache persistente.
    wp_cache_delete(self::OPTION_KEY, 'options');

    wp_safe_redirect(add_query_arg(['page' => 'edta-settings', 'edta_msg' => 'reset_ok'], admin_url('admin.php')));
    exit;
  } // Fin de EDTA_Admin::handle_reset_settings()

  // Devuelve los valores por defecto del plugin (con cache interno).
  public function default_settings(): array {
    static $defaults = null;
    if (is_array($defaults)) return $defaults; // Retorna desde cache si ya fue generado.

    $defaults = [
      'control_mode' => 'auto',
      'default_mode' => 'system',
      'remember_mode' => true,

      'toggle_style' => 'icon',
      'toggle_position' => 'br',
      'toggle_visibility' => 'show_all',
      'toggle_offset_x' => 18,
      'toggle_offset_y' => 18,

      'enable_transitions' => false,

      'palette_mode' => 'free',
      'use_theme_light_palette' => true,

      'light_palette' => $this->free_light_palette(),
      'dark_palette' => $this->free_dark_palette(),

      'a11y_reduce_motion' => true,
      'a11y_focus_ring' => true,

      'custom_css' => '', // CSS personalizado del toggle.
    ];

    return $defaults;
  } // Fin de EDTA_Admin::default_settings()

  // Sanitiza y valida los ajustes antes de guardarlos en la base de datos.
  public function sanitize_settings($input): array {
    $defaults = $this->default_settings(); // Defaults usados como fallback.
    $out = [];

    // Valida modo de control.
    $allowed_control = ['auto', 'button'];
    $cm = is_string($input['control_mode'] ?? '') ? $input['control_mode'] : $defaults['control_mode'];
    $out['control_mode'] = in_array($cm, $allowed_control, true) ? $cm : $defaults['control_mode'];

    // Valida modo por defecto.
    $allowed_modes = ['system', 'light', 'dark'];
    $mode = is_string($input['default_mode'] ?? '') ? $input['default_mode'] : $defaults['default_mode'];
    $out['default_mode'] = in_array($mode, $allowed_modes, true) ? $mode : $defaults['default_mode'];

    $out['remember_mode'] = !empty($input['remember_mode']); // Persiste el modo elegido (en modo botón).

    // Valida estilo del toggle.
    $allowed_styles = ['text', 'icon', 'pill'];
    $style = is_string($input['toggle_style'] ?? '') ? $input['toggle_style'] : $defaults['toggle_style'];
    $out['toggle_style'] = in_array($style, $allowed_styles, true) ? $style : $defaults['toggle_style'];

    // Valida posición del toggle flotante.
    $allowed_pos = ['br', 'bl', 'tr', 'tl'];
    $pos = is_string($input['toggle_position'] ?? '') ? $input['toggle_position'] : $defaults['toggle_position'];
    $out['toggle_position'] = in_array($pos, $allowed_pos, true) ? $pos : $defaults['toggle_position'];

    // Sanitiza offsets numéricos del toggle.
    $ox = isset($input['toggle_offset_x']) ? absint($input['toggle_offset_x']) : (int) $defaults['toggle_offset_x'];
    $oy = isset($input['toggle_offset_y']) ? absint($input['toggle_offset_y']) : (int) $defaults['toggle_offset_y'];

    // Limita offsets a un rango razonable.
    $ox = max(0, min(300, $ox));
    $oy = max(0, min(300, $oy));

    $out['toggle_offset_x'] = $ox;
    $out['toggle_offset_y'] = $oy;

    // Valida visibilidad del toggle por dispositivo.
    $allowed_vis = ['show_all', 'hide_mobile', 'hide_desktop', 'hide_both'];
    $vis = is_string($input['toggle_visibility'] ?? '') ? $input['toggle_visibility'] : $defaults['toggle_visibility'];
    $out['toggle_visibility'] = in_array($vis, $allowed_vis, true) ? $vis : $defaults['toggle_visibility'];

    $out['enable_transitions'] = !empty($input['enable_transitions']); // Habilita transición breve al cambiar tema.

    // Sanitiza CSS personalizado del usuario.
    $raw_css = is_string($input['custom_css'] ?? '') ? $input['custom_css'] : '';
    $raw_css = wp_kses($raw_css, []);      // Evita HTML.
    $raw_css = trim($raw_css);
    $raw_css = str_ireplace(['</style', '</script'], '', $raw_css);
    $out['custom_css'] = $raw_css;

    // Sanitiza opciones de accesibilidad.
    $out['a11y_reduce_motion'] = !empty($input['a11y_reduce_motion']);
    $out['a11y_focus_ring']    = !empty($input['a11y_focus_ring']);

    // Valida modo de paleta.
    $pm = is_string($input['palette_mode'] ?? '') ? $input['palette_mode'] : $defaults['palette_mode'];
    $out['palette_mode'] = ($pm === 'custom' || $pm === 'free') ? $pm : $defaults['palette_mode'];

    $out['use_theme_light_palette'] = !empty($input['use_theme_light_palette']); // Respeta paleta light del theme si está activo.

    // Sanitiza paletas según el modo seleccionado.
    if ($out['palette_mode'] === 'custom') {
      $out['light_palette'] = $this->sanitize_palette_array($input['light_palette'] ?? null, $defaults['light_palette']);
      $out['dark_palette']  = $this->sanitize_palette_array($input['dark_palette'] ?? null, $defaults['dark_palette']);
    } else {
      // En modo free se conserva la paleta custom existente (no se pisa al guardar).
      $existing = get_option(self::OPTION_KEY, $defaults);
      $existing_light = is_array($existing['light_palette'] ?? null) ? $existing['light_palette'] : $defaults['light_palette'];
      $existing_dark  = is_array($existing['dark_palette'] ?? null) ? $existing['dark_palette'] : $defaults['dark_palette'];

      $out['light_palette'] = $this->sanitize_palette_array($existing_light, $defaults['light_palette']);
      $out['dark_palette']  = $this->sanitize_palette_array($existing_dark, $defaults['dark_palette']);
    }

    return wp_parse_args($out, $defaults); // Completa cualquier clave faltante con defaults.
  } // Fin de EDTA_Admin::sanitize_settings()

  // Sanitiza un array de paleta asegurando tamaño y formato HEX válido.
  private function sanitize_palette_array($value, array $fallback): array {
    if (!is_array($value)) return $fallback; // Fallback si el valor no es un array.

    $value = array_values($value); // Reindexa el array de colores.
    $out = [];

    // Normaliza cada color y completa con fallback si falta o es inválido.
    for ($i = 0; $i < self::EDTA_PALETTE_SIZE; $i++) {
      $raw = $value[$i] ?? $fallback[$i] ?? '#000000';
      $hex = $this->normalize_hex($raw); // Normaliza a #RRGGBB.
      $out[$i] = $hex ?: ($fallback[$i] ?? '#000000');
    }

    return $out;
  } // Fin de EDTA_Admin::sanitize_palette_array()

  // Normaliza un valor HEX (#RGB o #RRGGBB) y devuelve #RRGGBB en mayúsculas.
  private function normalize_hex($value): ?string {
    if (!is_string($value)) return null;
    $v = trim($value); // Limpia espacios.

    // Convierte formato corto (#RGB) a formato largo (#RRGGBB).
    if (preg_match('/^#([0-9a-fA-F]{3})$/', $v, $m)) {
      $r = $m[1][0];
      $g = $m[1][1];
      $b = $m[1][2];
      return strtoupper('#' . $r . $r . $g . $g . $b . $b);
    }

    // Valida formato largo (#RRGGBB).
    if (preg_match('/^#([0-9a-fA-F]{6})$/', $v)) {
      return strtoupper($v);
    }

    return null;
  } // Fin de EDTA_Admin::normalize_hex()

  // Devuelve nombre descriptivo del color Astra según su índice.
  private function astra_role_for_index(int $i): string {
    $roles = [
      'Brand',
      'Alt Brand',
      'Encabezado',
      'Texto',
      'Principal',
      'Secondary',
      'Borde',
      'Subtle BG',
      'Extra',
    ];
    return $roles[$i] ?? ('Color ' . $i);
  } // Fin de EDTA_Admin::astra_role_for_index()

  // Devuelve la paleta light libre utilizada por defecto en el admin.
  private function free_light_palette(): array {
    return [
      "#046BD2", // Brand
      "#045CB4", // Alt Brand
      "#1E293B", // Enabezado
      "#334155", // Texto
      "#FFFFFF", // Principal
      "#F0F5FA", // Secondary
      "#111111", // Borde
      "#D1D5DB", // Subtle BG
      "#111111", // Extra
    ];
  } // Fin de EDTA_Admin::free_light_palette()

  // Devuelve la paleta dark libre utilizada por defecto en el admin.
  private function free_dark_palette(): array {
    return [
      "#2C82F9", // Brand
      "#7799FF", // Alt Brand
      "#E5E7EC", // Enabezado
      "#CBD5E1", // Texto
      "#1C1C1C", // Principal
      "#2B2B2B", // Secondary
      "#5B5B5B", // Borde
      "#4C4C4C", // Subtle BG
      "#00010A", // Extra
    ];
  } // Fin de EDTA_Admin::free_dark_palette()

  // Muestra aviso en el admin si Astra no está activo.
  public function maybe_show_astra_notice(): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'toplevel_page_edta-settings') return; // Solo en la página del plugin.

    // Advierte al usuario si Astra no está activo.
    if (!EDTA_Astra_Bridge::is_astra_active()) {
      echo '<div class="notice notice-warning"><p>';
      echo esc_html__('Este plugin está diseñado para Astra. Activá Astra para usar las variables --ast-global-color-*.', 'easy-dark-theme-for-astra');
      echo '</p></div>';
    }
  } // Fin de EDTA_Admin::maybe_show_astra_notice()

}