// Reemplaza el runtime de Hostinger que no viene en la copia estática:
// los elementos con .transition arrancan ocultos (opacity: 0) en main.css y
// solo aparecen cuando tienen data-animation-state="active".
(function () {
  var selector =
    '.transition:not(.transition--root-hidden),' +
    '.transition--root-hidden [data-animation-role]';

  function activar(el) {
    if (el.getAttribute('data-animation-role') === 'image') {
      var img = el.tagName === 'IMG' ? el : el.querySelector('img');
      if (!img || img.complete) {
        el.classList.add('loaded');
      } else {
        img.addEventListener('load', function () { el.classList.add('loaded'); }, { once: true });
        img.addEventListener('error', function () { el.classList.add('loaded'); }, { once: true });
      }
    }
    el.setAttribute('data-animation-state', 'active');
  }

  var observador = 'IntersectionObserver' in window
    ? new IntersectionObserver(function (entradas) {
      entradas.forEach(function (entrada) {
        if (entrada.isIntersecting) {
          activar(entrada.target);
          observador.unobserve(entrada.target);
        }
      });
    }, { rootMargin: '0px 0px -5% 0px' })
    : null;

  // Observa los elementos animados dentro de las raíces dadas. Otros scripts la
  // usan cuando agregan contenido después de cargar la página (p. ej. js/inicio.js).
  function animar(raices) {
    Array.prototype.forEach.call(raices, function (raiz) {
      raiz.querySelectorAll(selector).forEach(function (el) {
        if (el.getAttribute('data-animation-state') === 'active') return;
        if (observador) observador.observe(el);
        else activar(el);
      });
    });
  }

  window.capdesAnimar = animar;
  animar([document]);
})();
