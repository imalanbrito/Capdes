// Marco institucional: el índice "En esta página" resalta la sección que se está leyendo.
(function () {
  var indice = document.querySelector('[data-marco-indice]');
  if (!indice || !('IntersectionObserver' in window)) return;

  var enlaces = {};
  indice.querySelectorAll('a[href^="#"]').forEach(function (a) {
    enlaces[a.getAttribute('href').slice(1)] = a;
  });

  var visibles = {};
  var observador = new IntersectionObserver(function (entradas) {
    entradas.forEach(function (e) { visibles[e.target.id] = e.isIntersecting; });
    // La primera sección visible (en el orden de la página) es la actual.
    var actual = Object.keys(enlaces).filter(function (id) { return visibles[id]; })[0];
    if (!actual) return;
    Object.keys(enlaces).forEach(function (id) {
      if (id === actual) enlaces[id].setAttribute('aria-current', 'true');
      else enlaces[id].removeAttribute('aria-current');
    });
  }, { rootMargin: '-120px 0px -55% 0px' });

  Object.keys(enlaces).forEach(function (id) {
    var seccion = document.getElementById(id);
    if (seccion) observador.observe(seccion);
  });
})();
