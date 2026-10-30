// Blog: filtro por categoría (blog.html), botón "Copiar enlace" (páginas de artículo)
// y el enlace "Quiero suscribirme", que lleva al formulario del pie de página.
(function () {
  // ───── Filtro por categoría ─────
  var filtros = document.querySelector('[data-blog-filtros]');
  var lista = document.querySelector('[data-blog-lista]');
  var vacio = document.querySelector('[data-blog-vacio]');

  if (filtros && lista) {
    var botones = filtros.querySelectorAll('[data-filtro]');
    var tarjetas = lista.querySelectorAll('.blog__tarjeta');

    botones.forEach(function (boton) {
      boton.addEventListener('click', function () {
        var categoria = boton.getAttribute('data-filtro');
        var visibles = 0;
        botones.forEach(function (b) { b.setAttribute('aria-pressed', String(b === boton)); });
        tarjetas.forEach(function (tarjeta) {
          var mostrar = !categoria || tarjeta.getAttribute('data-categoria') === categoria;
          tarjeta.hidden = !mostrar;
          if (mostrar) visibles++;
        });
        // El artículo más reciente solo se destaca en grande cuando se ven todos.
        lista.classList.toggle('blog__lista--todas', !categoria);
        if (vacio) vacio.hidden = visibles > 0;
      });
    });
  }

  // ───── Copiar enlace del artículo ─────
  document.querySelectorAll('[data-copiar]').forEach(function (boton) {
    var texto = boton.querySelector('[data-copiar-texto]');
    var original = texto ? texto.textContent : '';
    boton.addEventListener('click', function () {
      var enlace = boton.getAttribute('data-copiar');
      var avisar = function (mensaje) {
        if (!texto) return;
        texto.textContent = mensaje;
        setTimeout(function () { texto.textContent = original; }, 2200);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(enlace).then(
          function () { avisar('¡Enlace copiado!'); },
          function () { window.prompt('Copia el enlace:', enlace); }
        );
      } else {
        window.prompt('Copia el enlace:', enlace);
      }
    });
  });

  // ───── "Quiero suscribirme" → formulario del pie ─────
  document.querySelectorAll('[data-ir-suscripcion]').forEach(function (enlace) {
    enlace.addEventListener('click', function (e) {
      var campo = document.getElementById('pie-correo');
      if (!campo) return;
      e.preventDefault();
      campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
      campo.focus({ preventScroll: true });
    });
  });
})();
