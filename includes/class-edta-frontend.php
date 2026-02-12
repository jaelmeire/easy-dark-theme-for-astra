<?php
if (!defined('ABSPATH')) {
  exit; // Evita acceso directo al archivo.
}

final class EDTA_Frontend {
  private const OPTION_KEY = 'edta_astra_settings'; // Clave de la opción donde se guardan los ajustes del plugin.
  private const PALETTE_SIZE = 9; // Cantidad de colores esperados para mapear Astra Global Colors.

  // Registra hooks del frontend (assets, clases, script temprano, shortcodes y widgets).
  public function init(): void {
    add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']); // Encola CSS/JS del frontend.
    add_filter('body_class', [$this, 'filter_body_class']); // Ajusta clases del body para el estado del tema.
    add_action('wp_head', [$this, 'print_early_mode_script'], 0); // Aplica modo antes del render para evitar parpadeo.

    add_action('init', [$this, 'register_shortcodes']); // Registra shortcodes del plugin.
    add_action('widgets_init', [$this, 'register_widgets']); // Registra el widget del toggle.
  } // Fin de EDTA_Frontend::init()

  // Encola assets del frontend y pasa configuración al JavaScript.
  public function enqueue_assets(): void {
    $settings = $this->get_settings(); // Obtiene ajustes actuales del plugin.

    // Determina si es necesario cargar los assets del frontend.
    if (!$this->should_enqueue_frontend_assets($settings)) {
      return;
    }

    wp_enqueue_style('edta-frontend', EDTA_PLUGIN_URL . 'assets/frontend.css', [], EDTA_VERSION); // CSS principal del frontend.
    wp_enqueue_script('edta-frontend', EDTA_PLUGIN_URL . 'assets/frontend.js', [], EDTA_VERSION, true); // JS principal del frontend.

    // Inyecta configuración del plugin para consumo del script frontend.
    wp_add_inline_script('edta-frontend', 'window.EDTA_CONFIG = ' . wp_json_encode([
      'controlMode'      => (string)($settings['control_mode'] ?? 'auto'),
      'defaultMode'      => (string)($settings['default_mode'] ?? 'system'),
      'remember'         => !empty($settings['remember_mode']),
      'toggleStyle'      => (string)($settings['toggle_style'] ?? 'pill'),
      'togglePosition'   => (string)($settings['toggle_position'] ?? 'br'),
      'toggleOffsetX'    => (int)($settings['toggle_offset_x'] ?? 18),
      'toggleOffsetY'    => (int)($settings['toggle_offset_y'] ?? 18),
      'toggleVisibility' => (string)($settings['toggle_visibility'] ?? 'show_all'),
      'enableTransitions' => !empty($settings['enable_transitions']),
      'a11yReduceMotion'  => !empty($settings['a11y_reduce_motion']),
      'a11yFocusRing'     => !empty($settings['a11y_focus_ring']),
    ]) . ';', 'before');

    // Genera e inyecta CSS de paletas Astra (light/dark).
    $css = $this->build_palette_css($settings);
    if ($css !== '') {
      wp_add_inline_style('edta-frontend', $css);
    }

    // Genera e inyecta CSS personalizado del toggle.
    $custom_css = $this->build_custom_toggle_css($settings);
    if ($custom_css !== '') {
      wp_add_inline_style('edta-frontend', $custom_css);
    }

  } // Fin de EDTA_Frontend::enqueue_assets()

  // Construye CSS personalizado del toggle a partir de los ajustes guardados.
  private function build_custom_toggle_css(array $settings): string {
    $raw = $settings['custom_css'] ?? '';
    if (!is_string($raw)) return '';
    $raw = trim($raw);
    if ($raw === '') return '';

    // Si incluye llaves, se asume que es CSS completo.
    if (strpos($raw, '{') !== false) {
      return "\n/* EDTA Custom CSS */\n" . $raw . "\n";
    }

    // Si no incluye llaves, se asume lista de propiedades y se envuelve en .edta-toggle.
    return "\n/* EDTA Custom CSS */\n.edta-toggle{" . $raw . "}\n";
  } // Fin de EDTA_Frontend::build_custom_toggle_css()

  // Imprime script temprano para definir el modo antes del render y evitar parpadeo visual.
  public function print_early_mode_script(): void {
    $settings = $this->get_settings(); // Obtiene ajustes actuales.
    $control  = (string)($settings['control_mode'] ?? 'auto'); // Modo de control (auto o botón).
    $default  = (string)($settings['default_mode'] ?? 'system'); // Modo por defecto.
    $remember = !empty($settings['remember_mode']); // Indica si se recuerda el modo elegido.
    ?>
    <script>
      (function () {
        try {
          var controlMode = <?php echo wp_json_encode($control); ?>;
          var defaultMode = <?php echo wp_json_encode($default); ?>;
          var remember = <?php echo $remember ? 'true' : 'false'; ?>;
          var KEY = "edta_theme_mode";

          // Detecta preferencia del sistema operativo.
          function prefersDark() {
            return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
          }

          // Resuelve modo automático según ajustes o sistema.
          function resolveAuto() {
            if (defaultMode === "light" || defaultMode === "dark") return defaultMode;
            return prefersDark() ? "dark" : "light";
          }

          // Resuelve modo del botón usando localStorage si está habilitado.
          function resolveButton() {
            if (remember) {
              try {
                var saved = localStorage.getItem(KEY);
                if (saved === "light" || saved === "dark") return saved;
              } catch (e) {}
            }
            return resolveAuto();
          }

          // Determina el modo inicial.
          var mode = (controlMode === "button") ? resolveButton() : resolveAuto();

          var docEl = document.documentElement;
          if (docEl) {
            // Estado temprano aplicado al elemento html (antes del paint).
            docEl.classList.remove(
              "edta-theme-light","edta-theme-dark",
              "edta-pre-light","edta-pre-dark",
              "edta-init"
            );

            docEl.classList.add(mode === "dark" ? "edta-theme-dark" : "edta-theme-light");
            docEl.classList.add(mode === "dark" ? "edta-pre-dark"  : "edta-pre-light");
            docEl.classList.add("edta-init");

            // Ajusta color-scheme del navegador.
            try { docEl.style.colorScheme = (mode === "dark" ? "dark" : "light"); } catch (e) {}

          }

          // Aplica clases al body cuando esté disponible.
          function applyToBody() {
            if (!document.body) return false;
            document.body.classList.remove("edta-theme-light", "edta-theme-dark");
            document.body.classList.add(mode === "dark" ? "edta-theme-dark" : "edta-theme-light");
            return true;
          }

          if (!applyToBody()) {
            document.addEventListener("DOMContentLoaded", applyToBody, { once: true });
          }
        } catch (e) {}
      })();
    </script>
    <?php
  } // Fin de EDTA_Frontend::print_early_mode_script()

  // Genera CSS para mapear paletas light/dark a Astra Global Colors.
  private function build_palette_css(array $settings): string {
    $use_theme_light = !empty($settings['use_theme_light_palette']); // Indica si se respeta la paleta light del theme.

    // Determina el modo de paleta (free o custom).
    $palette_mode = (string)($settings['palette_mode'] ?? 'free');
    if ($palette_mode !== 'custom' && $palette_mode !== 'free') {
      $palette_mode = 'free';
    }

    // Obtiene paletas según modo seleccionado.
    if ($palette_mode === 'free') {
      $light = $this->normalize_palette($this->free_light_palette(), 'light');
      $dark  = $this->normalize_palette($this->free_dark_palette(), 'dark');
    } else {
      $light = $this->normalize_palette($settings['light_palette'] ?? [], 'light');
      $dark  = $this->normalize_palette($settings['dark_palette'] ?? [], 'dark');
    }

    $parts = [];

    // Mapea paleta dark a variables globales de Astra.
    $parts[] = "html.edta-theme-dark body{";
    for ($i = 0; $i < self::PALETTE_SIZE; $i++) {
      $parts[] = "--ast-global-color-{$i}:{$dark[$i]};";
    }
    $parts[] = "}";

    // Mapea paleta light solo si no se usa la del theme.
    if (!$use_theme_light) {
      $parts[] = "html.edta-theme-light body{";
      for ($i = 0; $i < self::PALETTE_SIZE; $i++) {
        $parts[] = "--ast-global-color-{$i}:{$light[$i]};";
      }
      $parts[] = "}";
    }

    return implode('', $parts);
  } // Fin de EDTA_Frontend::build_palette_css()

  // Obtiene los ajustes del plugin combinando valores guardados con defaults (con cache interno).
  private function get_settings(): array {
    static $cache = null;
    if (is_array($cache)) return $cache; // Retorna desde cache si ya fue calculado.

    $defaults = $this->default_settings(); // Valores por defecto.
    $value = get_option(self::OPTION_KEY); // Lee ajustes desde la base de datos.
    if (!is_array($value)) $value = [];
    $cache = wp_parse_args($value, $defaults); // Fusiona ajustes guardados con defaults.
    return $cache;
  } // Fin de EDTA_Frontend::get_settings()

  // Determina si deben cargarse los assets del frontend.
  private function should_enqueue_frontend_assets(array $settings): bool {
    // Si el modo botón está activo, siempre se requiere frontend.
    if (($settings['control_mode'] ?? 'auto') === 'button') {
      return true;
    }

    // Si el mapeo de paletas Astra está activo, también se requiere frontend.
    if (!empty($settings['palette_mode'])) {
      return true;
    }

    return false;
  } // Fin de EDTA_Frontend::should_enqueue_frontend_assets()

  // Expone los valores por defecto para uso externo (frontend o integraciones).
  public function default_settings_for_public(): array {
    return $this->default_settings();
  } // Fin de EDTA_Frontend::default_settings_for_public()

  // Define los valores por defecto del plugin (con cache interno).
  private function default_settings(): array {
    static $defaults = null;
    if (is_array($defaults)) return $defaults; // Retorna desde cache si ya fue generado.

    $defaults = [
      'control_mode' => 'auto',
      'default_mode' => 'system',
      'remember_mode' => true,
      'toggle_style' => 'pill',
      'toggle_position' => 'br',
      'toggle_offset_x' => 18,
      'toggle_offset_y' => 18,
      'toggle_visibility' => 'show_all',

      'custom_css' => '', // CSS personalizado del toggle.

      'use_theme_light_palette' => true, // Indica si se respeta la paleta light del theme.

      'palette_mode' => 'free', // Modo de paleta (free o custom).

      // Paleta light por defecto (Astra Global Colors).
      'light_palette' => [
        '#046BD2', '#045CB4', '#1E293B', '#334155',
        '#FFFFFF', '#F0F5FA', '#111111', '#D1D5DB',
        '#111111',
      ],

      // Paleta dark por defecto (Astra Global Colors).
      'dark_palette' => [
        '#8AB4F8', '#669DF6', '#E5E7EB', '#CBD5E1',
        '#0B1220', '#111827', '#F9FAFB', '#374151',
        '#F9FAFB',
      ],
    ];

    return $defaults;
  } // Fin de EDTA_Frontend::default_settings()

  // Devuelve la paleta light libre utilizada por defecto.
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
      "#111111" // Extra
    ];
  } // Fin de EDTA_Frontend::free_light_palette()

  // Devuelve la paleta dark libre utilizada por defecto.
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
      "#00010A" // Extra
    ];
  } // Fin de EDTA_Frontend::free_dark_palette()

  // Normaliza una paleta asegurando cantidad, formato HEX y valores de respaldo.
  private function normalize_palette($palette, string $which = 'dark'): array {
    $defaults = $this->default_settings();
    $fallback = ($which === 'light') ? $defaults['light_palette'] : $defaults['dark_palette']; // Paleta fallback según modo.

    if (!is_array($palette)) $palette = [];
    $palette = array_values($palette); // Reindexa el array de colores.

    $out = [];
    for ($i = 0; $i < self::PALETTE_SIZE; $i++) {
      $raw = (string)($palette[$i] ?? $fallback[$i] ?? '#000000');
      $hex = $this->normalize_hex($raw); // Normaliza valor HEX.
      $out[$i] = $hex ?: ($fallback[$i] ?? '#000000');
    }

    return $out;
  } // Fin de EDTA_Frontend::normalize_palette()

  // Filtro para clases del body (actualmente sin modificaciones).
  public function filter_body_class(array $classes): array {
    return $classes;
  } // Fin de EDTA_Frontend::filter_body_class()

  // Registra el shortcode del toggle.
  public function register_shortcodes(): void {
    add_shortcode('edta_toggle', [$this, 'shortcode_toggle']); // Shortcode [edta_toggle].
  } // Fin de EDTA_Frontend::register_shortcodes()

  // Registra el widget del toggle si la clase existe.
  public function register_widgets(): void {
    if (class_exists('EDTA_Toggle_Widget')) {
      register_widget('EDTA_Toggle_Widget');
    }
  } // Fin de EDTA_Frontend::register_widgets()

  // Renderiza el toggle vía shortcode si el modo botón está activo.
  public function shortcode_toggle($atts = []): string {
    $settings = $this->get_settings(); // Obtiene ajustes actuales.
    if ((string)($settings['control_mode'] ?? 'auto') !== 'button') {
      return '';
    }

    return self::render_inline_toggle_markup($settings);
  } // Fin de EDTA_Frontend::shortcode_toggle()

  // Renderiza el HTML del toggle inline (shortcode/widget) sin depender de JS.
  public static function render_inline_toggle_markup(array $settings): string {
    $styleRaw = (string)($settings['toggle_style'] ?? 'icon');
    $style = in_array($styleRaw, ['text', 'icon', 'pill'], true) ? $styleRaw : 'icon'; // Valida estilo permitido.

    // Define atributos SVG reutilizables para íconos inline.
    $svgAttrs = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

    // Define ícono de sol (modo claro).
    $sunSvg = '<svg class="edta-ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false" ' . $svgAttrs . '>
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

    // Define ícono de luna (modo oscuro).
    $moonSvg = '<svg class="edta-ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false" ' . $svgAttrs . '>
      <path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.8 6.8 0 0 0 9.8 9.8Z"></path>
    </svg>';

    // Renderiza el botón con data-attrs para que el JS lo detecte.
    $html  = '<button type="button" class="edta-toggle edta-toggle--inline"';
    $html .= ' data-edta="1" data-edta-inline="1" data-edta-role="header"';
    $html .= ' data-style="' . esc_attr($style) . '" data-visibility="show_all"';

    // Configuración de accesibilidad (switch).
    $html .= ' role="switch" aria-checked="false"';
    $html .= ' aria-label="' . esc_attr__('Cambiar tema', 'easy-dark-theme-for-astra') . '"';
    $html .= ' title="' . esc_attr__('Cambiar tema', 'easy-dark-theme-for-astra') . '">';

    // Renderiza ambos estados para evitar flicker y permitir que CSS/JS muestre el correcto.
    $html .= '<span class="edta-toggle__icon edta-toggle__icon--sun" aria-hidden="true">' . $sunSvg . '</span>';
    $html .= '<span class="edta-toggle__icon edta-toggle__icon--moon" aria-hidden="true">' . $moonSvg . '</span>';
    $html .= '<span class="edta-toggle__text edta-toggle__text--light" aria-hidden="true">LIGHT</span>';
    $html .= '<span class="edta-toggle__text edta-toggle__text--dark" aria-hidden="true">DARK</span>';
    $html .= '<span class="edta-toggle__knob" aria-hidden="true"></span>';

    $html .= '</button>';

    return $html;
  } // Fin de EDTA_Frontend::render_inline_toggle_markup()

  // Normaliza un color HEX (#RGB o #RRGGBB) y devuelve formato #RRGGBB en mayúsculas.
  private function normalize_hex(string $v): ?string {
    $v = trim($v); // Limpia espacios.

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
  } // Fin de EDTA_Frontend::normalize_hex()
}

if (!class_exists('EDTA_Toggle_Widget')) {

  // Widget que renderiza el toggle sincronizado con el botón flotante.
  class EDTA_Toggle_Widget extends WP_Widget {
    private const OPTION_KEY = 'edta_astra_settings'; // Clave de opción donde se guardan los ajustes.

    // Configura ID, nombre y descripción del widget.
    public function __construct() {
      parent::__construct(
        'edta_toggle_widget',
        __('EDTA — Theme Toggle', 'easy-dark-theme-for-astra'),
        ['description' => __('Botón de cambio de tema (sincronizado con el flotante).', 'easy-dark-theme-for-astra')]
      );
    } // Fin de EDTA_Toggle_Widget::__construct()

    // Renderiza el contenido del widget en el frontend.
    public function widget($args, $instance) {
      echo $args['before_widget'] ?? ''; // Renderiza wrapper inicial del widget.

      static $merged_settings = null;

      // Fusiona ajustes guardados con valores por defecto (con cache interno).
      if (!is_array($merged_settings)) {
        $settings = get_option(self::OPTION_KEY);
        if (!is_array($settings)) $settings = [];

        $frontend = new EDTA_Frontend();
        $defaults = $frontend->default_settings_for_public();

        $merged_settings = wp_parse_args($settings, $defaults);
      }

      // Renderiza el toggle solo si el modo botón está activo.
      if ((string)($merged_settings['control_mode'] ?? 'auto') === 'button') {
        echo EDTA_Frontend::render_inline_toggle_markup($merged_settings);
      }

      echo $args['after_widget'] ?? ''; // Renderiza wrapper final del widget.
    } // Fin de EDTA_Toggle_Widget::widget()

  }
}