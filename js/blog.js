// Blog: botón "Copiar enlace" (páginas de artículo) y el enlace "Quiero suscribirme",
// que lleva al formulario del pie de página.
(function () {
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
