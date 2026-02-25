(function ($) {
  // Helpers utilitarios del admin.
  function normalizeHex(raw) {
    if (raw == null) return null;
    let v = String(raw).trim();
    if (!v) return null;

    if (v[0] !== "#") v = "#" + v;

    const m3 = v.match(/^#([0-9a-fA-F]{3})$/);
    if (m3) {
      const s = m3[1];
      return ("#" + s[0] + s[0] + s[1] + s[1] + s[2] + s[2]).toUpperCase();
    }

    const m6 = v.match(/^#([0-9a-fA-F]{6})$/);
    if (m6) return ("#" + m6[1]).toUpperCase();

    return null;
  } // Fin de normalizeHex()

  // Actualiza el swatch visual asociado a un input de color.
  function updateSwatchForInput(inputEl) {
    const $input = $(inputEl);
    const $card = $input.closest(".edta-palette-card");
    const $swatch = $card.find(".edta-swatch");
    if (!$swatch.length) return;
    const val = String($input.val() || "").trim() || "#000000";
    $swatch.css("background", val);
  } // Fin de updateSwatchForInput()

  // Posiciona un panel flotante cerca del botón disparador, evitando overflow en viewport.
  function positionFloatingNearButton($float, $btn) {
    if (!$float || !$float.length || !$btn || !$btn.length) return;

    const rect = $btn[0].getBoundingClientRect();
    const vw = window.innerWidth || document.documentElement.clientWidth;
    const vh = window.innerHeight || document.documentElement.clientHeight;

    // Mide el panel sin mostrarlo al usuario.
    $float.css({ visibility: "hidden", display: "block", left: -9999, top: -9999 });

    const w = $float.outerWidth();
    const h = $float.outerHeight();

    let left = rect.left;
    let top = rect.bottom + 8;

    if (left + w + 12 > vw) left = Math.max(12, vw - w - 12);
    if (top + h + 12 > vh) top = Math.max(12, rect.top - h - 8);

    $float.css({
      left: Math.round(left),
      top: Math.round(top),
      visibility: "visible",
      display: "block",
    });
  } // Fin de positionFloatingNearButton()

  let $activeContainer = null; // Contenedor .wp-picker-container actualmente abierto.
  let lastPaletteMode = null; // Recuerda el modo anterior para no pisar data-edta-custom al cargar.

  // Obtiene el holder flotante asociado a un container (si existe).
  function getFloat($container) {
    const $f = $container && $container.length ? $container.data("edtaFloatHolder") : null;
    return $f && $f.length ? $f : null;
  } // Fin de getFloat()

  // Sincroniza el estado visual/a11y del picker (clases + aria-expanded).
  function setOpenState($container, open) {
    const $btn = $container.find(".wp-color-result");
    $container.toggleClass("wp-picker-active", !!open);
    if ($btn.length) {
      $btn.toggleClass("wp-picker-open", !!open);
      $btn.attr("aria-expanded", open ? "true" : "false");
    }
  } // Fin de setOpenState()

  // Cierra un container y oculta su panel flotante asociado.
  function closeContainer($container) {
    if (!$container || !$container.length) return;
    const $float = getFloat($container);
    if ($float) $float.hide();
    setOpenState($container, false);
    if ($activeContainer && $activeContainer.is($container)) $activeContainer = null;
  } // Fin de closeContainer()

  // Cierra todos los pickers abiertos excepto el indicado.
  function closeAll(except) {
    $(".wp-picker-container").each(function () {
      const $c = $(this);
      if (except && $c.is(except)) return;
      closeContainer($c);
    });
  } // Fin de closeAll()

  // Abre el panel flotante del container y lo posiciona cerca del botón.
  function openContainer($container) {
    const $float = getFloat($container);
    const $btn = $container.find(".wp-color-result");
    if (!$float || !$float.length || !$btn.length) return;

    closeAll($container);
    $activeContainer = $container;

    positionFloatingNearButton($float, $btn);
    $float.show();
    setOpenState($container, true);
  } // Fin de openContainer()

  // Cierra el panel activo al hacer click fuera del holder o del botón disparador.
  $(document)
    .off("mousedown.edtaOutsideClose")
    .on("mousedown.edtaOutsideClose", function (e) {
      if (!$activeContainer || !$activeContainer.length) return;
      const $t = $(e.target);
      if ($t.closest(".edta-floating-holder").length) return;
      if ($t.closest(".wp-color-result").length) return;
      closeContainer($activeContainer);
    });

  // Cierra todos los paneles con Escape.
  $(document)
    .off("keydown.edtaEscClose")
    .on("keydown.edtaEscClose", function (e) {
      if (e.key === "Escape") closeAll();
    });

  // Cierra el panel activo al scrollear.
  $(window)
    .off("scroll.edtaCloseOnScroll")
    .on("scroll.edtaCloseOnScroll", function () {
      if ($activeContainer && $activeContainer.length) closeContainer($activeContainer);
    });

  // Reposiciona el panel activo al cambiar tamaño de ventana.
  $(window)
    .off("resize.edtaReposition")
    .on("resize.edtaReposition", function () {
      if (!$activeContainer || !$activeContainer.length) return;
      const $float = getFloat($activeContainer);
      const $btn = $activeContainer.find(".wp-color-result");
      if ($float && $float.is(":visible") && $btn.length) {
        positionFloatingNearButton($float, $btn);
      }
    });

  // Lee el modo de paleta seleccionado (free/custom).
  function getPaletteMode() {
    const $fs = $("#edta-palette-mode");
    if (!$fs.length) return "free";
    const $checked = $fs.find('input[type="radio"]:checked');
    const v = $checked.length ? String($checked.val() || "") : "free";
    return v === "custom" ? "custom" : "free";
  } // Fin de getPaletteMode()

  // Aplica una paleta (light/dark) al UI, opcionalmente bloqueando inputs.
  function applyPaletteToUI(which, colors, lock) {
    // which: "light"|"dark"
    const gridSel = which === "light" ? "#edta-light-palette-grid" : "#edta-dark-palette-grid";
    const $grid = $(gridSel);
    if (!$grid.length) return;

    const $hexes = $grid.find("input.edta-hex-inline");
    const $inputs = $grid.find("input.edta-color-field");

    // Cierra cualquier panel flotante abierto dentro de esta grilla.
    $grid.find(".wp-picker-container").each(function () {
      closeContainer($(this));
    });

    for (let i = 0; i < 9; i++) {
      const c = normalizeHex(colors[i]) || "#000000";

      const $hex = $hexes.eq(i);
      const $inp = $inputs.eq(i);

      if ($hex.length) $hex.val(c);
      if ($inp.length) {
        $inp.val(c);
        try { $inp.wpColorPicker("color", c); } catch (e) {}
      }

      // Actualiza el swatch tomando el input real como fuente.
      if ($inp.length) updateSwatchForInput($inp);
      else if ($hex.length) updateSwatchForInput($hex);
    }

    // Bloquea/desbloquea inputs (con excepción del disable por "use theme light" que se maneja aparte).
    if (lock) {
      $hexes.prop("disabled", true);
      $inputs.prop("disabled", true);
      $inputs.each(function () {
        try { $(this).wpColorPicker("disable"); } catch (e) {}
      });
    } else {
      // En light, si está "use theme light", se queda disabled (lo maneja setLightPaletteDisabled).
      $hexes.prop("disabled", false);
      $inputs.prop("disabled", false);
      $inputs.each(function () {
        try { $(this).wpColorPicker("enable"); } catch (e) {}
      });
    }
  } // Fin de applyPaletteToUI()

  // Restaura la paleta custom desde data-edta-custom a los controles del UI.
  function restoreCustomToUI() {
    // restaura desde data-edta-custom
    ["light", "dark"].forEach((which) => {
      const gridSel = which === "light" ? "#edta-light-palette-grid" : "#edta-dark-palette-grid";
      const $grid = $(gridSel);
      if (!$grid.length) return;

      const $hexes = $grid.find("input.edta-hex-inline");
      const $inputs = $grid.find("input.edta-color-field");

      for (let i = 0; i < 9; i++) {
        const $hex = $hexes.eq(i);
        const $inp = $inputs.eq(i);

        const stored =
          normalizeHex(($inp.length ? $inp.attr("data-edta-custom") : null)) ||
          normalizeHex(($hex.length ? $hex.attr("data-edta-custom") : null)) ||
          normalizeHex(($inp.length ? $inp.val() : null)) ||
          "#000000";

        if ($hex.length) $hex.val(stored);
        if ($inp.length) {
          $inp.val(stored);
          try { $inp.wpColorPicker("color", stored); } catch (e) {}
        }

        if ($inp.length) updateSwatchForInput($inp);
        else if ($hex.length) updateSwatchForInput($hex);
      }
    });
  } // Fin de restoreCustomToUI()

  // Sincroniza los valores actuales del UI hacia data-edta-custom (solo para modo custom).
  function syncCustomFromUI() {
    // guarda lo que esté en UI como "custom" (solo cuando modo custom)
    ["light", "dark"].forEach((which) => {
      const gridSel = which === "light" ? "#edta-light-palette-grid" : "#edta-dark-palette-grid";
      const $grid = $(gridSel);
      if (!$grid.length) return;

      const $hexes = $grid.find("input.edta-hex-inline");
      const $inputs = $grid.find("input.edta-color-field");

      for (let i = 0; i < 9; i++) {
        const $hex = $hexes.eq(i);
        const $inp = $inputs.eq(i);

        const v = normalizeHex($inp.length ? $inp.val() : ($hex.length ? $hex.val() : null));
        if (!v) continue;

        if ($hex.length) $hex.attr("data-edta-custom", v);
        if ($inp.length) $inp.attr("data-edta-custom", v);
      }
    });
  } // Fin de syncCustomFromUI()

  // Aplica el modo de paleta (free/custom) al UI y bloqueos correspondientes.
  function applyPaletteModeUI() {
    const mode = getPaletteMode();
    const preset = (window.EDTA_PRESET_1 || {});
    const presetLight = Array.isArray(preset.light) ? preset.light : [];
    const presetDark = Array.isArray(preset.dark) ? preset.dark : [];

    // Actualiza UI de lock para preset/free.
    setPresetLockUI(mode === "free");

    if (mode === "free") {
      // Solo se guarda custom si viene de custom (switch en vivo).
      if (lastPaletteMode === "custom") {
        syncCustomFromUI();
      }

      applyPaletteToUI("light", presetLight, true);
      applyPaletteToUI("dark", presetDark, true);

      lastPaletteMode = mode;
      return;
    }

    // En modo custom, restaura y habilita edición.
    restoreCustomToUI();

    applyPaletteToUI("light", collectCurrentUI("light"), false);
    applyPaletteToUI("dark", collectCurrentUI("dark"), false);

    // Respeta el lock de la paleta light cuando "use theme light" está activo.
    const $checkbox = $("#edta-use-theme-light");
    if ($checkbox.length) setLightPaletteDisabled($checkbox.is(":checked"));

    lastPaletteMode = mode;
  } // Fin de applyPaletteModeUI()

  // Lee los valores actuales de la grilla (light/dark) desde los inputs del picker.
  function collectCurrentUI(which) {
    const gridSel = which === "light" ? "#edta-light-palette-grid" : "#edta-dark-palette-grid";
    const $grid = $(gridSel);
    const out = [];
    if (!$grid.length) return out;

    $grid.find("input.edta-color-field").each(function () {
      out.push(String($(this).val() || "").trim());
    });
    return out;
  } // Fin de collectCurrentUI()

  // Vincula el input HEX manual con el wpColorPicker del mismo card.
  function linkHexAndPicker($colorInput) {
    const $card = $colorInput.closest(".edta-palette-card");
    const $hex = $card.find("input.edta-hex-inline[data-edta-hex='1']").first();
    if (!$hex.length) return;

    let syncing = false; // Evita loops de sincronización hex <-> picker.

    // Setea el valor del input HEX en formato canonizado.
    function setHex(value) {
      const n = normalizeHex(value);
      if (!n) return;
      if ($hex.val() !== n) $hex.val(n);
      $hex.removeClass("edta-hex-invalid");
    } // Fin de setHex()

    // Setea el valor del color picker en formato canonizado.
    function setPicker(value) {
      const n = normalizeHex(value);
      if (!n) return;

      try {
        $colorInput.wpColorPicker("color", n);
      } catch (e) {
        $colorInput.val(n).trigger("change");
      }

      $colorInput.val(n);
      updateSwatchForInput($colorInput);
    } // Fin de setPicker()

    setHex($colorInput.val());

    // Input manual HEX -> actualiza picker + swatch.
    $hex.off(".edtaHexSync").on("input.edtaHexSync", function () {
      if (syncing) return;

      const n = normalizeHex(this.value);
      if (!n) {
        $(this).addClass("edta-hex-invalid");
        return;
      }
      $(this).removeClass("edta-hex-invalid");

      syncing = true;
      setPicker(n);
      syncing = false;

      // En modo custom, persiste el valor custom en vivo.
      if (getPaletteMode() === "custom") {
        $hex.attr("data-edta-custom", n);
        $colorInput.attr("data-edta-custom", n);
      }
    });

    // Normaliza en blur/change y persiste data-edta-custom si aplica.
    $hex.off("change.edtaHexSync").on("change.edtaHexSync", function () {
      const n = normalizeHex(this.value);
      if (!n) {
        $(this).addClass("edta-hex-invalid");
        return;
      }
      $(this).removeClass("edta-hex-invalid");
      this.value = n;

      if (getPaletteMode() === "custom") {
        $hex.attr("data-edta-custom", n);
        $colorInput.attr("data-edta-custom", n);
      }
    });

    // Picker -> actualiza HEX + swatch y persiste data-edta-custom si aplica.
    $colorInput.off(".edtaPickerToHex").on("input.edtaPickerToHex change.edtaPickerToHex", function () {
      if (syncing) return;
      const n = normalizeHex($colorInput.val());
      if (!n) return;

      syncing = true;
      setHex(n);
      updateSwatchForInput($colorInput);
      syncing = false;

      if (getPaletteMode() === "custom") {
        $hex.attr("data-edta-custom", n);
        $colorInput.attr("data-edta-custom", n);
      }
    });
  } // Fin de linkHexAndPicker()

  // Inicializa wpColorPicker y adapta su UI al modo flotante del plugin.
  function initColorPickers() {
    if (!$.fn.wpColorPicker) return;

    $(".edta-color-field").each(function () {
      const $input = $(this);

      // Evita reinicializar el mismo input.
      if ($input.data("edtaColorInit")) return;
      $input.data("edtaColorInit", true);

      $input.wpColorPicker({
        change: function (event, ui) {
          const raw = ui && ui.color ? ui.color.toString() : event.target.value;

          // Canonical: siempre HEX de 6 dígitos en uppercase cuando se puede.
          const n = normalizeHex(raw);
          const color = n || raw;

          // Mantiene el input real como fuente para serialize() / detección de cambios.
          event.target.value = color;
          $(event.target).val(color);

          updateSwatchForInput(event.target);

          const $card = $(event.target).closest(".edta-palette-card");
          const $hex = $card.find("input.edta-hex-inline[data-edta-hex='1']").first();

          if ($hex.length && n) {
            $hex.val(n);
            $hex.removeClass("edta-hex-invalid");

            // Dispara eventos para el detector (ya normalizado).
            $hex.trigger("input").trigger("change");
          }

          // Dispara eventos también en el input real del picker.
          $(event.target).trigger("input").trigger("change");

          if (getPaletteMode() === "custom" && n) {
            $(event.target).attr("data-edta-custom", n);
            if ($hex.length) $hex.attr("data-edta-custom", n);
          }
        },
        clear: function (event) {
          updateSwatchForInput(event.target);

          // Cuenta como cambio para el detector de "unsaved".
          $(event.target).trigger("input").trigger("change");
        },
        palettes: true,
      });

      const $container = $input.closest(".wp-picker-container");
      const $btn = $container.find(".wp-color-result");

      // Ajustes UI: oculta inputs nativos y usa holder flotante propio.
      $container.addClass("edta-picker-popover");
      $container.find(".wp-picker-input-wrap, .wp-picker-default").hide();

      // Fuerza creación del holder si WP todavía no lo renderizó.
      if (!$container.find(".wp-picker-holder").length && $btn.length) {
        $btn.trigger("click");
        $btn.trigger("click");
      }

      // Desacopla el holder y lo mueve al body como panel flotante.
      const $holder = $container.find(".wp-picker-holder");
      if ($holder.length) {
        const $float = $holder.detach();
        $float.addClass("edta-floating-holder").hide();
        $float.css("z-index", 999999);
        $("body").append($float);
        $container.data("edtaFloatHolder", $float);
      }

      linkHexAndPicker($input);
      updateSwatchForInput($input);

      // Toggle del panel flotante al click del botón del picker.
      $btn.off("click.edtaFloatToggle").on("click.edtaFloatToggle", function (e) {
        // Si preset está activo, no abrir panel (porque está lock).
        if (getPaletteMode() === "free") return;

        e.preventDefault();
        e.stopImmediatePropagation();

        const $float = getFloat($container);
        if (!$float || !$float.length) return;

        if ($float.is(":visible")) closeContainer($container);
        else openContainer($container);
      });

      setOpenState($container, false);
      const $floatInit = getFloat($container);
      if ($floatInit) $floatInit.hide();
    });
  } // Fin de initColorPickers()

  // Activa/desactiva el estado de "preset locked" en la UI (modo free).
  function setPresetLockUI(isLocked) {
    $("body").toggleClass("edta-preset-locked", !!isLocked);
    updatePaletteBadges();
  } // Fin de setPresetLockUI()

  // Recalcula y aplica los estados de bloqueo según el modo de paleta.
  function refreshLockStates() {
    // Bloqueo global si está en free.
    const isPreset = getPaletteMode() === "free";
    setPresetLockUI(isPreset);
  } // Fin de refreshLockStates()

  // Habilita/deshabilita la edición de la paleta Light según checkbox y/o preset lock.
  function setLightPaletteDisabled(disabled) {
    const $grid = $("#edta-light-palette-grid");
    if (!$grid.length) return;

    // Determina bloqueo real del preset (modo free es la fuente de verdad).
    const isPresetLocked = getPaletteMode() === "free" || $("body").hasClass("edta-preset-locked");

    // Disabled efectivo: preset lock siempre domina.
    const effectiveDisabled = !!disabled || !!isPresetLocked;
    $grid.toggleClass("edta-is-disabled", !!effectiveDisabled);

    // Opacidad:
    // - En preset lock, la maneja el CSS global.
    // - En custom, se usa para representar "usar colores del tema".
    if (isPresetLocked) {
      $grid.css("opacity", ""); // Limpia inline para no pelear con CSS.
    } else {
      $grid.css("opacity", disabled ? "0.6" : "1");
    }

    const $inputs = $grid.find("input.edta-light-input");
    const $hexes = $grid.find("input.edta-hex-inline.edta-hex-light");

    $inputs.prop("disabled", effectiveDisabled);
    $hexes.prop("disabled", effectiveDisabled);

    // Sincroniza estado enable/disable del wpColorPicker.
    if ($.fn.wpColorPicker) {
      $inputs.each(function () {
        try { $(this).wpColorPicker(effectiveDisabled ? "disable" : "enable"); } catch (e) {}
      });
    }

    // Hint: este texto aplica solo al checkbox (no al preset lock).
    const $hintDisabled = $("#edta-light-disabled-hint");
    const $hintEnabled = $("#edta-light-enabled-hint");
    if ($hintDisabled.length) $hintDisabled.css("display", disabled ? "block" : "none");
    if ($hintEnabled.length) $hintEnabled.css("display", disabled ? "none" : "block");

    updatePaletteBadges();
  } // Fin de setLightPaletteDisabled()

  // Ajusta la UI del admin según el modo de control seleccionado (auto/button).
  function setControlModeUI(mode) {
    const isAuto = mode === "auto";

    const $defaultMode = $("#edta-default-mode");
    const $toggleStyle = $("#edta-toggle-style");
    const $togglePos = $("#edta-toggle-position");
    const $offX = $("#edta-toggle-offset-x");
    const $offY = $("#edta-toggle-offset-y");
    if ($offX.length) $offX.prop("disabled", isAuto);
    if ($offY.length) $offY.prop("disabled", isAuto);
    const $toggleVis = $("#edta-toggle-visibility");

    // Habilita/deshabilita controles según el modo.
    if ($defaultMode.length) $defaultMode.prop("disabled", !isAuto);
    if ($toggleStyle.length) $toggleStyle.prop("disabled", isAuto);
    if ($togglePos.length) $togglePos.prop("disabled", isAuto);
    if ($toggleVis.length) $toggleVis.prop("disabled", isAuto);

    const $autoWrap = $("#edta-auto-fields");
    const $btnWrap1 = $("#edta-button-fields");
    const $btnWrap2 = $("#edta-button-fields-2");
    const $btnWrap3 = $("#edta-button-fields-3");

    // Refuerza visualmente qué sección está activa.
    if ($autoWrap.length) $autoWrap.css("opacity", isAuto ? "1" : "0.6");
    if ($btnWrap1.length) $btnWrap1.css("opacity", isAuto ? "0.6" : "1");
    if ($btnWrap2.length) $btnWrap2.css("opacity", isAuto ? "0.6" : "1");
    if ($btnWrap3.length) $btnWrap3.css("opacity", isAuto ? "0.6" : "1");
  } // Fin de setControlModeUI()

  // Habilita el botón de reset solo cuando el texto de confirmación coincide.
  function initResetGate() {
    const confirmText = (window.EDTA_I18N && window.EDTA_I18N.confirm_reset_phrase) || "Easy Dark Theme for Astra";
    const $input = $("#edta-reset-confirm");
    const $btn = $("#edta-reset-submit");
    if (!$input.length || !$btn.length) return;

    // Valida en vivo el texto ingresado.
    function update() {
      const ok = String($input.val() || "").trim() === confirmText;
      $btn.prop("disabled", !ok);
    }

    update();
    $input.on("input", update);
  } // Fin de initResetGate()

  // Inicializa los tooltips de ayuda contextual del panel admin.
  function initTooltips() {
    const tips = window.EDTA_ADMIN_TIPS || {};
    const $btns = $(".edta-help[data-edta-tip]");
    if (!$btns.length) return;

    // Nodo único del tooltip (se reutiliza para todos los botones).
    let $tip = $("#edta-tooltip");
    if (!$tip.length) {
      $tip = $('<div id="edta-tooltip" class="edta-tooltip" role="tooltip" aria-hidden="true">' +
        '<div class="edta-tooltip__content"></div>' +
      '</div>');
      $("body").append($tip);
    }

    let openFor = null;

    // Cierra el tooltip activo.
    function closeTip() {
      if (!$tip.hasClass("is-open")) return;
      $tip.removeClass("is-open").attr("aria-hidden", "true");
      openFor = null;
    } // Fin de closeTip()

    // Abre (o alterna) el tooltip para un botón específico.
    function openTip($btn) {
      const key = String($btn.attr("data-edta-tip") || "");
      const text = tips[key];
      if (!text) return;

      // Toggle si es el mismo botón.
      if (openFor && openFor.get(0) === $btn.get(0)) {
        closeTip();
        return;
      }

      $tip.find(".edta-tooltip__content").text(String(text));
      $tip.addClass("is-open").attr("aria-hidden", "false");
      openFor = $btn;

      // Posiciona el tooltip relativo al botón.
      try {
        const r = $btn.get(0).getBoundingClientRect();
        const margin = 10;
        // Por defecto, a la derecha.
        let left = Math.round(r.right + margin);
        let top = Math.round(r.top - 6);

        const tipEl = $tip.get(0);
        const tipW = tipEl.offsetWidth || 320;
        const tipH = tipEl.offsetHeight || 80;

        const vw = window.innerWidth || document.documentElement.clientWidth || 1200;
        const vh = window.innerHeight || document.documentElement.clientHeight || 800;

        if (left + tipW > vw - 8) left = Math.max(8, Math.round(r.left - tipW - margin));
        if (top + tipH > vh - 8) top = Math.max(8, Math.round(vh - tipH - 8));
        if (top < 8) top = 8;

        $tip.css({ left: left + "px", top: top + "px" });
      } catch (e) {}
    } // Fin de openTip()

    // Hover (desktop) y click (siempre).
    $btns
      .off("mouseenter.edtaTip mouseleave.edtaTip click.edtaTip")
      .on("mouseenter.edtaTip", function () {
        // Evita hover en dispositivos táctiles.
        if (window.matchMedia && window.matchMedia("(hover: none)").matches) return;
        openTip($(this));
      })
      .on("mouseleave.edtaTip", function () {
        if (window.matchMedia && window.matchMedia("(hover: none)").matches) return;
        closeTip();
      })
      .on("click.edtaTip", function (e) {
        e.preventDefault();
        e.stopPropagation();
        openTip($(this));
      });

    // Cierra al hacer click fuera, scroll o presionar Escape.
    $(document).on("click.edtaTipDoc", function () { closeTip(); });
    $(window).on("scroll.edtaTip", function () { closeTip(); });
    $(document).on("keydown.edtaTip", function (e) {
      const key = e && (e.key || e.keyCode);
      if (key === "Escape" || key === "Esc" || key === 27) closeTip();
    });
  } // Fin de initTooltips()

  // Inicializa la barra lateral de guardado (estado "unsaved" + botón Guardar).
  function initSideSavebar() {
    // Obtiene el formulario de settings (id preferido con fallback al form estándar).
    const $form = $("#edta-settings-form").length
      ? $("#edta-settings-form")
      : $('form[action="options.php"]');

    if (!$form.length) return;

    const $status = $("#edta-save-state").length ? $("#edta-save-state") : $("#edta-unsaved"); // Nodo de estado.
    const $btn = $("#edta-side-save"); // Botón "Guardar cambios" del sidebar.
    const $realSubmit = $("#edta-hidden-submit").length ? $("#edta-hidden-submit") : $("#edta-submit-main"); // Submit real del form.

    if (!$btn.length || !$realSubmit.length) return;

    let initial = ""; // Snapshot inicial para comparar cambios.
    let dirty = false; // Estado de cambios pendientes.
    let isSubmitting = false; // Evita recalcular mientras se envía.

    // Canoniza valores hex para comparación consistente.
    function canonHex(v) {
      const n = normalizeHex(v);
      return n || String(v || "").trim();
    } // Fin de canonHex()

    // Lee el valor seleccionado de un radio por sufijo de nombre.
    function getRadio(nameSuffix) {
      const $r = $form.find('input[type="radio"][name$="[' + nameSuffix + ']"]:checked');
      return $r.length ? String($r.val() || "") : "";
    } // Fin de getRadio()

    // Lee el valor de un checkbox por sufijo de nombre como "1"/"0".
    function getCheckbox(nameSuffix) {
      const $c = $form.find('input[type="checkbox"][name$="[' + nameSuffix + ']"]');
      return $c.length && $c.prop("checked") ? "1" : "0";
    } // Fin de getCheckbox()

    // Lee el valor de un select/input por id selector.
    function getSelect(id) {
      const $s = $(id);
      return $s.length ? String($s.val() || "") : "";
    } // Fin de getSelect()

    // Construye un snapshot canónico de lo que efectivamente se guarda.
    function snapshot() {
      // Lo que realmente se guarda
      const paletteMode = getRadio("palette_mode"); // free|custom
      const useThemeLight = $("#edta-use-theme-light").length ? ($("#edta-use-theme-light").prop("checked") ? "1" : "0") : "0";

      const parts = [];

      // Control
      parts.push("control_mode=" + getRadio("control_mode"));
      parts.push("default_mode=" + getSelect("#edta-default-mode"));
      parts.push("remember_mode=" + getCheckbox("remember_mode"));

      // Botón
      parts.push("toggle_style=" + getSelect("#edta-toggle-style"));
      parts.push("toggle_position=" + getSelect("#edta-toggle-position"));
      parts.push("toggle_visibility=" + getSelect("#edta-toggle-visibility"));
      parts.push("toggle_offset_x=" + getSelect("#edta-toggle-offset-x"));
      parts.push("toggle_offset_y=" + getSelect("#edta-toggle-offset-y"));

      // Animación
      parts.push("enable_transitions=" + getCheckbox("enable_transitions"));

      // Accesibilidad
      parts.push("a11y_reduce_motion=" + getCheckbox("a11y_reduce_motion"));
      parts.push("a11y_focus_ring=" + getCheckbox("a11y_focus_ring"));

      // Astra
      parts.push("palette_mode=" + paletteMode);
      parts.push("use_theme_light_palette=" + useThemeLight);

      // Paletas:
      // - Si palette_mode = free -> NO se guardan (sanitize_settings no pisa custom), así que las ignoramos
      // - Si custom pero use_theme_light = 1 -> light queda disabled/no se postea, así que también la ignoramos
      if (paletteMode === "custom") {
        if (useThemeLight !== "1") {
          $("#edta-light-palette-grid input.edta-color-field[name]").each(function () {
            parts.push(this.name + "=" + canonHex(this.value));
          });
        }

        $("#edta-dark-palette-grid input.edta-color-field[name]").each(function () {
          parts.push(this.name + "=" + canonHex(this.value));
        });
      }

      return parts.join("&");
    } // Fin de snapshot()

    // Actualiza UI y estado del botón según si hay cambios.
    function setDirty(nextDirty) {
      dirty = !!nextDirty;
      $btn.prop("disabled", !dirty);

      if ($status.length) {
        if (dirty) {
          $status.text("Cambios sin guardar").addClass("is-dirty").removeClass("is-saved");
        } else {
          $status.text("Sin cambios").removeClass("is-dirty").addClass("is-saved");
        }
      }
    } // Fin de setDirty()

    // Recalcula el estado "dirty" comparando snapshot vs initial.
    function recalcDirty() {
      if (isSubmitting) return;
      setDirty(snapshot() !== initial);
    } // Fin de recalcDirty()

    // Captura el estado inicial después de que el UI termine de inicializar (wpColorPicker, applyPaletteModeUI, etc.).
    setDirty(false);
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        initial = snapshot();
        recalcDirty();
      });
    });

    // Detecta cambios (incluye picker porque change() dispara input/change).
    $form
      .off("input.edtaDirty change.edtaDirty")
      .on("input.edtaDirty change.edtaDirty", "input, select, textarea", function () {
        recalcDirty();
      });

    // Botón lateral: dispara el submit real del form.
    $btn.off("click.edtaSideSave").on("click.edtaSideSave", function () {
      if (!dirty) return;
      $realSubmit.trigger("click");
    });

    // Estado de envío: bloquea recálculos y actualiza el texto de estado.
    $form.off("submit.edtaDirty").on("submit.edtaDirty", function () {
      isSubmitting = true;
      if ($status.length) $status.text("Guardando…").removeClass("is-dirty").addClass("is-saved");
    });
  } // Fin de initSideSavebar()

  // Activa el link del sidebar según la sección visible (scroll spy).
  function initSideScrollSpy() {
    const $links = $(".edta-side-links a[data-edta-nav]");
    if (!$links.length) return;

    const ids = $links
      .map(function () {
        return String($(this).attr("data-edta-nav") || "");
      })
      .get()
      .filter(Boolean);

    // Calcula la sección actual y marca el link activo.
    function onScroll() {
      const y = window.scrollY || document.documentElement.scrollTop || 0;
      let current = null;

      for (let i = 0; i < ids.length; i++) {
        const el = document.getElementById(ids[i]);
        if (!el) continue;

        const top = el.getBoundingClientRect().top + (window.scrollY || 0);
        if (top <= y + 130) current = ids[i];
      }

      $links.removeClass("is-active");
      if (current) {
        $links.filter('[data-edta-nav="' + current + '"]').addClass("is-active");
      }
    } // Fin de onScroll()

    $(window).off("scroll.edtaSpy").on("scroll.edtaSpy", onScroll);
    onScroll();
  } // Fin de initSideScrollSpy()

  // Actualiza los badges de estado (lock) para paletas light/dark.
  function updatePaletteBadges() {
    const $light = $("#edta-light-lock-badge");
    const $dark = $("#edta-dark-lock-badge");
    if (!$light.length && !$dark.length) return;

    const isPresetLocked = getPaletteMode() === "free" || $("body").hasClass("edta-preset-locked");
    const useThemeLight = !!$("#edta-use-theme-light").prop("checked");

    // Setea texto y estado visual del badge.
    function setBadge($el, on, text) {
      if (!$el || !$el.length) return;
      if (on) $el.text(text).addClass("is-on");
      else $el.text("").removeClass("is-on");
    } // Fin de setBadge()

    // Free preset: ambas paletas quedan bloqueadas.
    if (isPresetLocked) {
      setBadge($light, true, "Paleta bloqueada por configuración.");
      setBadge($dark, true, "Paleta bloqueada por configuración.");
      return;
    }

    // Custom: dark libre.
    setBadge($dark, false, "");

    // Custom: light depende de "usar colores del tema".
    if (useThemeLight) setBadge($light, true, "Paleta bloqueada por configuración.");
    else setBadge($light, false, "");
  } // Fin de updatePaletteBadges()

  // Inicializa el acordeón de la "Guía rápida" del sidebar.
  function initGuideAccordion() {
    const items = document.querySelectorAll(".edta-guide__item");
    if (!items.length) return;

    items.forEach(function(item){
      const btn = item.querySelector(".edta-guide__toggle");
      if (!btn) return;

      btn.addEventListener("click", function(){
        item.classList.toggle("is-open");
      });
    });
  } // Fin de initGuideAccordion()

  // Inicializa el acordeón de la guía rápida cuando el DOM está listo.
  document.addEventListener("DOMContentLoaded", initGuideAccordion);

  // Boot
  $(function () {
    
    // Toggle: deshabilita la paleta Light cuando se respetan colores del theme.
    const $checkbox = $("#edta-use-theme-light");
    if ($checkbox.length) {
      setLightPaletteDisabled($checkbox.is(":checked"));
    }

    // Selector de modo de paleta (free/custom) + lock UI asociado.
    const $pm = $("#edta-palette-mode");
    if ($pm.length) {
      lastPaletteMode = getPaletteMode();
      applyPaletteModeUI();
      refreshLockStates();
    }

    initColorPickers();

    // Maneja el estado visual/disabled de la UI según el modo de control.
    const $fs = $("#edta-control-mode");
    if ($fs.length) {
      const $radios = $fs.find('input[type="radio"][name$="[control_mode]"]');
      const getMode = () => {
        let v = "auto";
        $radios.each(function () { if (this.checked) v = this.value; });
        return v === "button" ? "button" : "auto";
      };
      setControlModeUI(getMode());
      $radios.on("change", function () { setControlModeUI(getMode()); });
    }

    // Evitar parpadeo del evento.
    if ($checkbox.length) {
      $checkbox.on("change", function () {
        setLightPaletteDisabled($checkbox.is(":checked"));
        refreshLockStates();
      });
    }

    // Evitar parpadeo del evento.
    if ($pm.length) {
      $pm.find('input[type="radio"]').on("change", function () {
        applyPaletteModeUI();
        refreshLockStates();
      });
    }

    // Inicializa la previsualización del toggle en el admin.
    (function initTogglePreview() {
      const $style = $("#edta-toggle-style");
      const $canvas = $("#edta-toggle-preview-canvas");
      const $btn = $("#edta-toggle-preview-btn");
      const $wrap = $(".edta-toggle-preview");
      if (!$style.length || !$canvas.length || !$btn.length || !$wrap.length) return;

      const icons = window.EDTA_TOGGLE_ICONS || { sun: "", moon: "" };
      let previewMode = (function () {
        const $active = $wrap.find("[data-edta-preview-mode].is-active").first();
        const v = $active.length ? String($active.attr("data-edta-preview-mode") || "light") : "light";
        return v === "dark" ? "dark" : "light";
      })();

      // Construye el contenido del botón una sola vez para evitar reparseo de SVG.
      if (!$btn.data("edtaPreviewBuilt")) {
        $btn.data("edtaPreviewBuilt", true);

        $btn.empty().append(
          '<span class="edta-toggle-preview__icon edta-toggle-preview__icon--sun" aria-hidden="true"></span>' +
          '<span class="edta-toggle-preview__icon edta-toggle-preview__icon--moon" aria-hidden="true"></span>' +
          '<span class="edta-toggle-preview__text" aria-hidden="true"></span>' +
          '<span class="edta-toggle__knob" aria-hidden="true"></span>'
        );

        const $sun = $btn.find(".edta-toggle-preview__icon--sun");
        const $moon = $btn.find(".edta-toggle-preview__icon--moon");

        // Inyecta SVG una sola vez.
        $sun.html(icons.sun || "");
        $moon.html(icons.moon || "");

        // Ajustes mínimos inline para la maqueta del preview.
        $btn.css({ display: "inline-flex", alignItems: "center", justifyContent: "center" });
        $btn.find(".edta-toggle-preview__text").css({
          fontSize: "12px",
          fontWeight: 700,
          letterSpacing: "0.08em",
          lineHeight: "1",
        });
      }

      // Renderiza el preview según estilo actual y modo (light/dark).
      function render() {
        const style = String($style.val() || "icon");
        $btn.attr("data-style", style);

        // Fondo del canvas.
        $canvas.toggleClass("is-dark", previewMode === "dark");

        const $sun = $btn.find(".edta-toggle-preview__icon--sun");
        const $moon = $btn.find(".edta-toggle-preview__icon--moon");
        const $text = $btn.find(".edta-toggle-preview__text");

        $btn.attr("aria-checked", previewMode === "dark" ? "true" : "false");

        if (style === "pill") {
          $text.hide();
          $sun.show();
          $moon.show();
          return;
        }

        if (style === "icon") {
          $text.hide();

          // Regla igual al frontend: en dark muestra sol; en light muestra luna.
          if (previewMode === "dark") {
            $sun.show();
            $moon.hide();
          } else {
            $sun.hide();
            $moon.show();
          }
        } else {
          $sun.hide();
          $moon.hide();

          $text.text(previewMode === "dark" ? "DARK" : "LIGHT").show();
        }
      } // Fin de render()

      // Cambia el modo de preview (light/dark) desde el segmented control.
      $wrap.find("[data-edta-preview-mode]")
        .off("click.edtaPreview")
        .on("click.edtaPreview", function () {
          const next = String($(this).attr("data-edta-preview-mode") || "light");
          previewMode = next === "dark" ? "dark" : "light";

          $wrap.find("[data-edta-preview-mode]").removeClass("is-active");
          $(this).addClass("is-active");
          

          render();
        });

      // Desactiva transiciones inline temporalmente para evitar lag/flicker en el preview.
      function setInlineTransitionNone($el) {
        if (!$el || !$el.length) return;
        $el.each(function () {
          const prev = this.style.transition;
          $(this).data("edtaPrevTransition", prev);
          this.style.transition = "none";
        });
      } // Fin de setInlineTransitionNone()

      // Restaura transiciones inline previamente guardadas.
      function restoreInlineTransition($el) {
        if (!$el || !$el.length) return;
        $el.each(function () {
          const prev = $(this).data("edtaPrevTransition");
          this.style.transition = prev == null ? "" : String(prev);
          $(this).removeData("edtaPrevTransition");
        });
      } // Fin de restoreInlineTransition()

      // Renderiza sin animación perceptible: aplica, fuerza reflow y restaura transiciones.
      function renderNoLag() {
        const $sun = $btn.find(".edta-toggle-preview__icon--sun");
        const $moon = $btn.find(".edta-toggle-preview__icon--moon");
        const $text = $btn.find(".edta-toggle-preview__text");
        const $knob = $btn.find(".edta-toggle__knob");

        setInlineTransitionNone($canvas);
        setInlineTransitionNone($btn);
        setInlineTransitionNone($sun);
        setInlineTransitionNone($moon);
        setInlineTransitionNone($text);
        setInlineTransitionNone($knob);

        render();

        // Fuerza reflow para aplicar el estado inmediatamente.
        void $btn[0].offsetHeight;

        requestAnimationFrame(function () {
          restoreInlineTransition($canvas);
          restoreInlineTransition($btn);
          restoreInlineTransition($sun);
          restoreInlineTransition($moon);
          restoreInlineTransition($text);
          restoreInlineTransition($knob);
          $btn.removeClass("edta-init");
        });
      } // Fin de renderNoLag()

      // Cambio de estilo del toggle: renderiza evitando flicker.
      $style
        .off("change.edtaPreview")
        .on("change.edtaPreview", function () {
          renderNoLag();
        });

      render();
    })(); // Fin de initTogglePreview()

    initResetGate();
    initTooltips();

    initSideSavebar();
    initSideScrollSpy();
    updatePaletteBadges();

  });
})(jQuery);