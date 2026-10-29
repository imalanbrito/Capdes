// Reemplaza el runtime de Hostinger que no viene en la copia estática:
// los elementos con .transition arrancan ocultos (opacity: 0) en main.css y
// solo aparecen cuando tienen data-animation-state="active".
(function () {
  var selector =
    '.transition:not(.transition--root-hidden),' +
    '.transition--root-hidden [data-animation-role]';
  var elementos = document.querySelectorAll(selector);

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

  if (!('IntersectionObserver' in window)) {
    elementos.forEach(activar);
    return;
  }

  var observador = new IntersectionObserver(function (entradas) {
    entradas.forEach(function (entrada) {
      if (entrada.isIntersecting) {
        activar(entrada.target);
        observador.unobserve(entrada.target);
      }
    });
  }, { rootMargin: '0px 0px -5% 0px' });

  elementos.forEach(function (el) { observador.observe(el); });
})();
