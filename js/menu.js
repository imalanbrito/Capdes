// Menú principal: header completo arriba de la página y compacto al hacer scroll,
// submenús, panel de la hamburguesa y teclado. El HTML lo genera herramientas/generar.php.
(function () {
  var menu = document.querySelector('[data-menu]');
  if (!menu) return;

  var hamburguesa = menu.querySelector('.menu__hamburguesa');
  var botones = menu.querySelectorAll('.menu__abrir');
  // En celular no caben las pestañas: el header se queda siempre compacto.
  var celular = window.matchMedia('(max-width: 919px)');

  // Umbrales distintos para compactar y para volver a expandir, así el header
  // no parpadea si el scroll se queda justo en el límite.
  var COMPACTAR_EN = 48;
  var EXPANDIR_EN = 16;

  function cerrarSubmenus(excepto) {
    botones.forEach(function (boton) {
      var item = boton.closest('.menu__item');
      if (item === excepto) return;
      item.classList.remove('menu__item--abierto');
      boton.setAttribute('aria-expanded', 'false');
    });
  }

  function abrirPanel(abrir) {
    menu.classList.toggle('menu--abierto', abrir);
    hamburguesa.setAttribute('aria-expanded', String(abrir));
    hamburguesa.setAttribute('aria-label', abrir ? 'Cerrar menú' : 'Abrir menú');
    document.documentElement.classList.toggle('menu-sin-scroll', abrir && celular.matches);
    if (!abrir) cerrarSubmenus();
  }

  function actualizarEstado() {
    var y = window.scrollY;
    var compacto = menu.classList.contains('menu--compacto');
    if (celular.matches) {
      compacto = true;
    } else if (!compacto && y > COMPACTAR_EN) {
      compacto = true;
    } else if (compacto && y < EXPANDIR_EN) {
      compacto = false;
    }

    if (compacto !== menu.classList.contains('menu--compacto')) {
      menu.classList.toggle('menu--compacto', compacto);
      cerrarSubmenus();
      // Si el header vuelve a estar completo, el panel de la hamburguesa ya no aplica.
      if (!compacto) abrirPanel(false);
    }
    menu.classList.toggle('menu--scroll', y > 8);
  }

  botones.forEach(function (boton) {
    boton.addEventListener('click', function () {
      var item = boton.closest('.menu__item');
      var abrir = !item.classList.contains('menu__item--abierto');
      cerrarSubmenus(item);
      item.classList.toggle('menu__item--abierto', abrir);
      boton.setAttribute('aria-expanded', String(abrir));
    });
  });

  hamburguesa.addEventListener('click', function () {
    abrirPanel(!menu.classList.contains('menu--abierto'));
  });

  // Al elegir un enlace del panel se cierra (útil para anclas de la misma página).
  menu.querySelectorAll('.menu__panel a').forEach(function (enlace) {
    enlace.addEventListener('click', function () {
      abrirPanel(false);
    });
  });

  document.addEventListener('click', function (e) {
    if (menu.contains(e.target)) return;
    cerrarSubmenus();
    abrirPanel(false);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var abierto = menu.querySelector('.menu__item--abierto .menu__abrir');
    cerrarSubmenus();
    if (menu.classList.contains('menu--abierto')) {
      abrirPanel(false);
      hamburguesa.focus();
    } else if (abierto) {
      abierto.focus();
    }
  });

  var pendiente = false;
  window.addEventListener('scroll', function () {
    if (pendiente) return;
    pendiente = true;
    requestAnimationFrame(function () {
      pendiente = false;
      actualizarEstado();
    });
  }, { passive: true });

  celular.addEventListener('change', function () {
    abrirPanel(false);
    actualizarEstado();
  });

  // Estado inicial sin animación (p. ej. al recargar a media página).
  menu.classList.add('menu--sin-transicion');
  actualizarEstado();
  requestAnimationFrame(function () {
    requestAnimationFrame(function () {
      menu.classList.remove('menu--sin-transicion');
    });
  });
})();
