(function () {
  const KEY = "edta_theme_mode"; // Key de localStorage para persistir el modo.

  // Helpers de compatibilidad para manejo de clases (classList fallback).
  function hasClass(el, cls) {
    if (!el) return false;
    if (el.classList) return el.classList.contains(cls);
    return (" " + (el.className || "") + " ").indexOf(" " + cls + " ") !== -1;
  } // Fin de hasClass()

  // Agrega una clase al elemento (con fallback sin classList).
  function addClass(el, cls) {
    if (!el) return;
    if (el.classList) el.classList.add(cls);
    else if (!hasClass(el, cls)) el.className = ((el.className || "") + " " + cls).trim();
  } // Fin de addClass()

  // Remueve una clase del elemento (con fallback sin classList).
  function removeClass(el, cls) {
    if (!el) return;
    if (el.classList) el.classList.remove(cls);
    else el.className = (" " + (el.className || "") + " ").replace(" " + cls + " ", " ").trim();
  } // Fin de removeClass()

  // Itera una NodeList/HTMLCollection sin depender de forEach nativo.
  function forEachNode(list, fn) {
    if (!list) return;
    for (let i = 0; i < list.length; i++) fn(list[i], i);
  } // Fin de forEachNode()

  // Verifica si localStorage está disponible (modo privado / restricciones).
  function canUseStorage() {
    try {
      const s = window.localStorage;
      if (!s) return false;
      const k = "__edta_test__";
      s.setItem(k, "1");
      s.removeItem(k);
      return true;
    } catch (e) {
      return false;
    }
  } // Fin de canUseStorage()

  const STORAGE_OK = canUseStorage(); // Cachea disponibilidad de storage.

  // Obtiene un valor de localStorage de forma segura.
  function safeGet(key) {
    if (!STORAGE_OK) return null;
    try {
      return window.localStorage.getItem(key);
    } catch (e) {
      return null;
    }
  } // Fin de safeGet()

  // Guarda un valor en localStorage de forma segura.
  function safeSet(key, val) {
    if (!STORAGE_OK) return false;
    try {
      window.localStorage.setItem(key, val);
      return true;
    } catch (e) {
      return false;
    }
  } // Fin de safeSet()

  // Dispara un evento con el modo aplicado para integraciones externas.
  function dispatchModeEvent(mode) {
    // CustomEvent fallback (IE/old)
    try {
      document.dispatchEvent(new CustomEvent("edta:mode", { detail: { mode } }));
    } catch (e) {
      try {
        const ev = document.createEvent("CustomEvent");
        ev.initCustomEvent("edta:mode", true, true, { mode: mode });
        document.dispatchEvent(ev);
      } catch (e2) {
        // si ni eso existe, no hacemos nada
      }
    }
  } // Fin de dispatchModeEvent()

  // Devuelve true si el sistema prefiere dark mode.
  function prefersDark() {
    try {
      return !!(window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches);
    } catch (e) {
      return false;
    }
  } // Fin de prefersDark()

  // Ajusta la propiedad CSS color-scheme para inputs/scrollbars nativos.
  function applyColorScheme(mode) {
    const root = document.documentElement;
    if (!root) return;
    root.style.colorScheme = mode === "dark" ? "dark" : "light";
  } // Fin de applyColorScheme()

  // Aplica el modo (light/dark) actualizando clases en html/body.
  function setMode(mode) {
    const doc = document.documentElement;

    // Aplica siempre en <html> como fuente de verdad.
    if (doc) {
      if (mode === "dark") {
        addClass(doc, "edta-theme-dark");
        removeClass(doc, "edta-theme-light");
      } else {
        addClass(doc, "edta-theme-light");
        removeClass(doc, "edta-theme-dark");
      }
      removeClass(doc, "edta-pre-light");
      removeClass(doc, "edta-pre-dark");
      addClass(doc, mode === "dark" ? "edta-pre-dark" : "edta-pre-light");
    }

    // Mantiene clases en <body> por compatibilidad con CSS/themes existentes.
    if (document.body) {
      if (mode === "dark") {
        addClass(document.body, "edta-theme-dark");
        removeClass(document.body, "edta-theme-light");
      } else {
        addClass(document.body, "edta-theme-light");
        removeClass(document.body, "edta-theme-dark");
      }
    }

    applyColorScheme(mode);
  } // Fin de setMode()

  // Obtiene el modo actual a partir de clases en html/body.
  function getCurrentMode() {
    const doc = document.documentElement;
    if (doc && hasClass(doc, "edta-theme-dark")) return "dark";
    if (document.body && hasClass(document.body, "edta-theme-dark")) return "dark";
    return "light";
  } // Fin de getCurrentMode()

  // Resuelve el modo cuando el control es automático (preferencia sistema o override).
  function resolveAutoMode(config) {
    const def = config.defaultMode || "system";
    if (def === "light" || def === "dark") return def;
    return prefersDark() ? "dark" : "light";
  } // Fin de resolveAutoMode()

  // Resuelve el modo inicial cuando el control es por botón (con remember opcional).
  function resolveButtonMode(cfg) {
    const def = (cfg && cfg.defaultMode) || "system";

    if (def === "system") {
      // Si remember está activo, preferimos lo persistido.
      if (cfg && cfg.remember) {
        const stored = safeGet(KEY);
        if (stored === "light" || stored === "dark") return stored;
      }
      return prefersDark() ? "dark" : "light";
    }

    if (def === "dark") return "dark";
    return "light";
  } // Fin de resolveButtonMode()
  
  // Atributos SVG compartidos por los iconos del toggle.
  const SVG_ATTRS = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

  // SVG inline del icono sol (modo claro).
  const ICON_SUN = `
    <svg class="edta-ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false" ${SVG_ATTRS}>
      <circle cx="12" cy="12" r="4"></circle>
      <path d="M12 2v2"></path>
      <path d="M12 20v2"></path>
      <path d="M4.93 4.93l1.41 1.41"></path>
      <path d="M17.66 17.66l1.41 1.41"></path>
      <path d="M2 12h2"></path>
      <path d="M20 12h2"></path>
      <path d="M4.93 19.07l1.41-1.41"></path>
      <path d="M17.66 6.34l1.41-1.41"></path>
    </svg>
  `;

  // SVG inline del icono luna (modo oscuro).
  const ICON_MOON = `
    <svg class="edta-ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false" ${SVG_ATTRS}>
      <path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.8 6.8 0 0 0 9.8 9.8Z"></path>
    </svg>
  `;

  // Aplica posición del toggle vía data-attribute.
  function applyPosition(el, pos) {
    el.dataset.position = pos || "br";
  } // Fin de applyPosition()

  // Aplica estilo visual del toggle (icon/text/pill).
  function applyStyle(el, style) {
    el.dataset.style = style || "icon";
  } // Fin de applyStyle()

  // Aplica offsets X/Y como variables CSS.
  function applyOffsets(el, cfg) {
    if (!el) return;

    var x = parseInt(cfg && cfg.toggleOffsetX, 10);
    var y = parseInt(cfg && cfg.toggleOffsetY, 10);

    if (!isFinite(x) || x < 0) x = 18;
    if (!isFinite(y) || y < 0) y = 18;

    el.style.setProperty("--edta-offset-x", x + "px");
    el.style.setProperty("--edta-offset-y", y + "px");
  } // Fin de applyOffsets()

  // Aplica visibilidad del toggle (mobile/desktop/siempre).
  function applyVisibility(el, visibility) {
    el.dataset.visibility = visibility || "show_all";
  } // Fin de applyVisibility()

  // Normaliza estilo recibido desde config (incluye legacy).
  function normalizeStyleFromConfig(raw) {
    const s = String(raw || "").toLowerCase();
    // supported: icon | text | pill
    // legacy
    if (s === "minimal") return "text";
    if (s === "text" || s === "icon" || s === "pill") return s;
    return "icon";
  } // Fin de normalizeStyleFromConfig()

  // Garantiza que el toggle tenga su markup interno.
  function ensureToggleMarkup(el) {
    if (!el) return;
    if (el.querySelector(".edta-toggle__knob")) return;

    // IMPORTANTE: no usar insertAdjacentHTML + luego innerHTML (eso borra el knob).
    el.innerHTML =
      '<span class="edta-toggle__knob" aria-hidden="true"></span>' +
      '<span class="edta-toggle__icon edta-toggle__icon--sun" aria-hidden="true">' + ICON_SUN + "</span>" +
      '<span class="edta-toggle__icon edta-toggle__icon--moon" aria-hidden="true">' + ICON_MOON + "</span>" +
      '<span class="edta-toggle__text edta-toggle__text--light" aria-hidden="true">LIGHT</span>' +
      '<span class="edta-toggle__text edta-toggle__text--dark" aria-hidden="true">DARK</span>';
  } // Fin de ensureToggleMarkup()

  // Muestra u oculta un sub-elemento del toggle.
  function setShown(el, selector, show) {
    const node = el.querySelector(selector);
    if (!node) return;
    node.style.display = show ? "" : "none";
  } // Fin de setShown()
  
  // Actualiza el contenido/estado visual y atributos a11y del botón según modo y estilo.
  function updateButtonContent(el, cfg, mode) {
    if (!el) return;

    ensureToggleMarkup(el);

    const currentMode = mode || getCurrentMode();
    const styleRaw = String(el.getAttribute("data-style") || cfg.toggleStyle || "icon").toLowerCase();
    const style = (styleRaw === "text" || styleRaw === "pill") ? styleRaw : "icon";

    const $sun = el.querySelector(".edta-toggle__icon--sun");
    const $moon = el.querySelector(".edta-toggle__icon--moon");
    const $textLight = el.querySelector(".edta-toggle__text--light");
    const $textDark = el.querySelector(".edta-toggle__text--dark");
    const $knob = el.querySelector(".edta-toggle__knob");

    // Accesibilidad: configura el botón como switch.
    el.setAttribute("type", "button");
    el.setAttribute("role", "switch");
    el.setAttribute("aria-checked", currentMode === "dark" ? "true" : "false");

    const nextLabel = currentMode === "dark" ? "Cambiar a modo claro" : "Cambiar a modo oscuro";
    el.setAttribute("aria-label", nextLabel);
    el.setAttribute("title", nextLabel);

    // Oculta knob por defecto (solo se usa en estilo pill).
    if ($knob) $knob.style.display = "none";

    if (style === "pill") {
      // Pill: ambos íconos visibles; el knob indica el estado con CSS.
      if ($textLight) $textLight.style.display = "none";
      if ($textDark) $textDark.style.display = "none";
      if ($sun) $sun.style.display = "";
      if ($moon) $moon.style.display = "";
      if ($knob) $knob.style.display = "";

      // Evita salto visual al inicializar en dark: desactiva transición solo una vez.
      if ($knob && !el.dataset.edtaKnobInit) {
        el.dataset.edtaKnobInit = "1";

        const prev = $knob.style.transition;
        $knob.style.transition = "none";

        // Fuerza reflow para aplicar el transform correcto sin animar.
        void $knob.offsetHeight;

        requestAnimationFrame(() => {
          $knob.style.transition = prev || "";
        });
      }

      return;
    }

    const isDark = currentMode === "dark";

    if (style === "text") {
      // Muestra el texto correspondiente y oculta iconos.
      setShown(el, ".edta-toggle__text--light", !isDark);
      setShown(el, ".edta-toggle__text--dark", isDark);

      setShown(el, ".edta-toggle__icon--sun", false);
      setShown(el, ".edta-toggle__icon--moon", false);
      return;
    }

    // style === "icon": en dark muestra sol; en light muestra luna.
    setShown(el, ".edta-toggle__icon--sun", isDark);
    setShown(el, ".edta-toggle__icon--moon", !isDark);

    // Oculta textos.
    setShown(el, ".edta-toggle__text--light", false);
    setShown(el, ".edta-toggle__text--dark", false);
  } // Fin de updateButtonContent()

  // Asocia comportamiento de toggle a un botón (evita doble bind).
  function bindToggle(el, config, controlMode) {
    if (!el || el.__edtaBound) return;
    el.__edtaBound = true;

    // Inicializa estilo desde config si no viene en data-attrs.
    if (!el.dataset.style) {
      applyStyle(el, normalizeStyleFromConfig(config.toggleStyle));
    }

    // Aplica posición/visibilidad/offsets solo si no es inline.
    if (!el.dataset.edtaInline) {
      applyPosition(el, config.togglePosition);
      applyVisibility(el, config.toggleVisibility);
      applyOffsets(el, config);
    }

    updateButtonContent(el, config, getCurrentMode());

    // Handler click: alterna el modo solo cuando controlMode es "button".
    el.addEventListener("click", function () {
      if (controlMode !== "button") return;
      const next = getCurrentMode() === "dark" ? "light" : "dark";
      window.EDTA.setMode(next, true);
    });
  } // Fin de bindToggle()

  // Actualiza todos los toggles presentes en el DOM.
  function updateAllToggles(cfg) {
    const buttons = document.querySelectorAll('.edta-toggle[data-edta="1"]');
    forEachNode(buttons, function (btn) {
      updateButtonContent(btn, cfg, getCurrentMode());
    });
  } // Fin de updateAllToggles()

  // Crea el toggle flotante (si la visibilidad lo permite).
  function createFloatingToggle(config) {
    const vis = config.toggleVisibility || "show_all";
    if (vis === "hide_both") return null;

    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "edta-toggle";
    btn.setAttribute("data-edta", "1");
    btn.setAttribute("data-edta-role", "floating");

    applyPosition(btn, config.togglePosition);
    applyOffsets(btn, config);
    applyStyle(btn, normalizeStyleFromConfig(config.toggleStyle));
    applyVisibility(btn, vis);

    updateButtonContent(btn, config, getCurrentMode());

    return btn;
  } // Fin de createFloatingToggle()

  // Inicializa el frontend cuando el DOM está listo.
  document.addEventListener("DOMContentLoaded", function () {
    // Lee configuración inyectada por PHP con fallback seguro.
    const config = window.EDTA_CONFIG || {
      controlMode: "auto",
      defaultMode: "system",
      remember: true,
      toggleStyle: "icon",
      togglePosition: "br",
      toggleVisibility: "show_all",
      enableTransitions: false,
    };

    // Accesibilidad: respeta reduced motion (desactiva transiciones).
    try {
      const reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      if (reduce && config && config.a11yReduceMotion) {
        config.enableTransitions = false;
      }
    } catch (e) {}

    // Accesibilidad: foco visible (clase en html).
    try {
      const root = document.documentElement;
      if (root && config && config.a11yFocusRing) root.classList.add("edta-a11y-focus");
      else if (root) root.classList.remove("edta-a11y-focus");
    } catch (e) {}

    const controlMode = config.controlMode === "button" ? "button" : "auto"; // Normaliza modo de control.

    window.EDTA = window.EDTA || {}; // Namespace público.

    var switchingTimer = null; // Timer para la clase de transición temporal.

    // Activa una clase temporal durante el cambio de tema para transiciones controladas.
    function startSwitchingClass() {
      try {
        var root = document.documentElement;
        if (!root) return;

        // Evita acumulación de timers.
        if (switchingTimer) {
          clearTimeout(switchingTimer);
          switchingTimer = null;
        }

        addClass(root, "edta-switching");

        switchingTimer = setTimeout(function () {
          try { removeClass(root, "edta-switching"); } catch (e) {}
        }, 300); // 250-350ms ok (fijo)
      } catch (e) {}
    } // Fin de startSwitchingClass()

    // API pública: setea el modo y sincroniza UI/eventos.
      window.EDTA.setMode = function (mode, shouldRemember) {
      if (mode !== "light" && mode !== "dark") return;

      var current = getCurrentMode();
      if (current === mode) return; // no activar switching si ya está

      // Aplica transición temporal solo si está habilitada.
      if (config && config.enableTransitions) {
        startSwitchingClass();
      }

      setMode(mode);

      // Persistencia segura (solo en modo botón y cuando corresponde).
      if (controlMode === "button" && config.remember && shouldRemember) {
        safeSet(KEY, mode);
      }

      updateAllToggles(config);
      dispatchModeEvent(mode);
    };

    // API pública: retorna el modo actual.
    window.EDTA.getMode = function () {
      return getCurrentMode();
    };

    // Detecta si el body ya trae clases de modo (por script temprano o theme).
    const bodyHasMode =
      document.body &&
      (document.body.classList.contains("edta-theme-dark") ||
        document.body.classList.contains("edta-theme-light"));

    if (controlMode === "auto") {
      // Inicializa modo en automático si no fue aplicado previamente.
      if (!bodyHasMode) {
        setMode(resolveAutoMode(config));
      } else {
        applyColorScheme(getCurrentMode());
      }

      // Escucha cambios del sistema solo cuando defaultMode es "system".
      if ((config.defaultMode || "system") === "system") {
        const mql = window.matchMedia
          ? window.matchMedia("(prefers-color-scheme: dark)")
          : null;

        if (mql) {
          const onChange = function () {
            window.EDTA.setMode(prefersDark() ? "dark" : "light", false);
          };

          // Modern
          if (mql.addEventListener) mql.addEventListener("change", onChange);
          // Old (Safari/old Chrome)
          else if (mql.addListener) mql.addListener(onChange);
        }
      }

    } else {
      // Inicializa modo en botón (con preferencia persistida si aplica).
      if (!bodyHasMode) {
        setMode(resolveButtonMode(config));
      } else {
        applyColorScheme(getCurrentMode());
      }
    }

    // Bindea toggles existentes (shortcode/widget/inline).
    const existing = document.querySelectorAll('.edta-toggle[data-edta="1"]');
    forEachNode(existing, function (btn) {
      bindToggle(btn, config, controlMode);
    });

    updateAllToggles(config);

    // Crea toggle flotante solo cuando el control es por botón.
    if (controlMode === "button") {
      const floating = createFloatingToggle(config);
      if (floating) {
        document.body.appendChild(floating);
        bindToggle(floating, config, controlMode);
        updateAllToggles(config);
        requestAnimationFrame(function () {
          try { document.documentElement.classList.remove("edta-init"); } catch (e) {}
        });
      }
    }

    // Sincroniza cambios entre pestañas cuando remember está activo.
    if (controlMode === "button" && config.remember && window.addEventListener) {
      window.addEventListener("storage", function (e) {
        try {
          if (!e) return;
          if (e.key !== KEY) return;

          const v = e.newValue;
          if (v !== "light" && v !== "dark") return;

          // Evita loops: en storage event no re-escribimos storage.
          const current = getCurrentMode();
          if (current === v) return;

          window.EDTA.setMode(v, false);
        } catch (err) {
          // No romper
        }
      });
    }

  });
})(); 
