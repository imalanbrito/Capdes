// Contador de visitas del pie de página, con el servicio gratuito Abacus
// (https://abacus.jasoncameron.dev). El HTML lo genera herramientas/generar.php.
//
// - Cuenta una visita por sesión del navegador (no una por cada página vista).
// - Solo suma en el dominio real; en local o en vistas previas solo lee el número.
(function () {
  var caja = document.querySelector('[data-contador]');
  if (!caja) return;

  var numero = caja.querySelector('.contador__numero');
  var API = 'https://abacus.jasoncameron.dev';
  var CLAVE = 'capdes-org/visitas';
  var DOMINIOS_REALES = ['capdes.org', 'www.capdes.org'];
  var MARCA_SESION = 'capdes-visita-contada';

  function yaContada() {
    try { return sessionStorage.getItem(MARCA_SESION) === '1'; } catch (e) { return false; }
  }

  function marcarContada() {
    try { sessionStorage.setItem(MARCA_SESION, '1'); } catch (e) { /* sin almacenamiento: no pasa nada */ }
  }

  function mostrar(valor) {
    var formato = new Intl.NumberFormat('es-MX');
    var reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    caja.classList.add('contador--listo');
    if (reducido || valor < 20) {
      numero.textContent = formato.format(valor);
      return;
    }
    // Sube rápido hasta el valor real.
    var inicio = null;
    var duracion = 1200;
    function paso(t) {
      if (inicio === null) inicio = t;
      var avance = Math.min((t - inicio) / duracion, 1);
      var suave = 1 - Math.pow(1 - avance, 3);
      numero.textContent = formato.format(Math.round(valor * suave));
      if (avance < 1) requestAnimationFrame(paso);
    }
    requestAnimationFrame(paso);
  }

  var sumar = DOMINIOS_REALES.indexOf(location.hostname) !== -1 && !yaContada();
  var url = API + (sumar ? '/hit/' : '/get/') + CLAVE;

  fetch(url)
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (datos) {
      if (typeof datos.value !== 'number') throw new Error('respuesta inesperada');
      if (sumar) marcarContada();
      mostrar(datos.value);
    })
    .catch(function () {
      numero.textContent = '—';
      caja.classList.add('contador--listo');
    });
})();
