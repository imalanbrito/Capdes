// Página de Contacto: video de fondo (solo en pantallas grandes), motivo preseleccionado
// desde la dirección (?motivo=voluntariado), validación y envío del formulario.
(function () {
  // ───── Video de fondo ─────
  // En celular, con "ahorro de datos" o con "reducir movimiento" se queda la imagen fija.
  var video = document.querySelector('[data-contacto-video]');
  var conexion = navigator.connection || {};
  var usarVideo = video &&
    window.matchMedia('(min-width: 761px)').matches &&
    !window.matchMedia('(prefers-reduced-motion: reduce)').matches &&
    !conexion.saveData;

  if (usarVideo) {
    video.src = video.getAttribute('data-src');
    video.addEventListener('playing', function () { video.classList.add('contacto__video--listo'); }, { once: true });
    var intento = video.play();
    if (intento && intento.catch) intento.catch(function () { /* el navegador no permitió reproducirlo: queda la imagen */ });
  }

  // ───── Formulario ─────
  var formulario = document.querySelector('[data-contacto-formulario]');
  if (!formulario) return;

  var estado = formulario.querySelector('[data-contacto-estado]');
  var motivo = formulario.querySelector('[name="motivo"]');
  var boton = formulario.querySelector('[type="submit"]');

  // Motivo elegido desde otro botón del sitio, p. ej. "Ser voluntario".
  var elegido = (new URLSearchParams(window.location.search).get('motivo') || '').replace(/[^a-z-]/g, '');
  if (elegido && motivo.querySelector('option[value="' + elegido + '"]')) {
    motivo.value = elegido;
  }

  var MENSAJES = {
    nombre: 'Escribe tu nombre.',
    correo: 'Escribe un correo válido, por ejemplo nombre@correo.com.',
    motivo: 'Elige en qué te podemos ayudar.',
    mensaje: 'Escribe tu mensaje.'
  };

  function marcarError(campo, mensaje) {
    var contenedor = campo.closest('.campo');
    var error = contenedor.querySelector('.campo__error');
    if (!mensaje) {
      campo.removeAttribute('aria-invalid');
      if (error) error.remove();
      return;
    }
    if (!error) {
      error = document.createElement('p');
      error.className = 'campo__error';
      error.id = campo.id + '-error';
      contenedor.appendChild(error);
    }
    error.textContent = mensaje;
    campo.setAttribute('aria-invalid', 'true');
    campo.setAttribute('aria-describedby', error.id);
  }

  function validar() {
    var primero = null;
    Object.keys(MENSAJES).forEach(function (nombre) {
      var campo = formulario.querySelector('[name="' + nombre + '"]');
      var valor = campo.value.trim();
      var malo = !valor || (nombre === 'correo' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor));
      marcarError(campo, malo ? MENSAJES[nombre] : '');
      if (malo && !primero) primero = campo;
    });
    if (primero) primero.focus();
    return !primero;
  }

  // Al corregir un campo, su error desaparece.
  formulario.addEventListener('input', function (e) {
    if (e.target.getAttribute('aria-invalid')) marcarError(e.target, '');
  });

  function avisar(texto, tipo) {
    estado.textContent = texto;
    estado.className = 'formulario__estado formulario__estado--' + tipo;
  }

  // Si el envío automático no está disponible (vista previa local o falla del servidor),
  // se abre la aplicación de correo de la persona con el mensaje listo.
  function enviarPorCorreo(datos) {
    var textoMotivo = motivo.options[motivo.selectedIndex].text;
    var cuerpo = 'Nombre: ' + datos.get('nombre') + ' ' + datos.get('apellidos') + '\n' +
      'Correo: ' + datos.get('correo') + '\n' +
      'Teléfono: ' + (datos.get('telefono') || '—') + '\n' +
      'Motivo: ' + textoMotivo + '\n\n' + datos.get('mensaje');
    window.location.href = 'mailto:' + formulario.getAttribute('data-correo') +
      '?subject=' + encodeURIComponent('Mensaje desde el sitio: ' + textoMotivo) +
      '&body=' + encodeURIComponent(cuerpo);
    avisar('Se abrió tu aplicación de correo con el mensaje listo; solo envíalo.', 'info');
  }

  formulario.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!validar()) {
      avisar('Revisa los campos marcados.', 'error');
      return;
    }
    var datos = new FormData(formulario);

    if (!/^https?:$/.test(window.location.protocol)) {
      enviarPorCorreo(datos);
      return;
    }

    boton.disabled = true;
    avisar('Enviando…', 'info');
    fetch(formulario.getAttribute('action'), { method: 'POST', body: datos, headers: { Accept: 'application/json' } })
      .then(function (r) {
        return r.json().then(function (respuesta) {
          if (!r.ok || !respuesta.ok) throw new Error(respuesta.error || 'HTTP ' + r.status);
        });
      })
      .then(function () {
        formulario.reset();
        avisar('¡Gracias! Recibimos tu mensaje y te responderemos pronto.', 'exito');
      })
      .catch(function () {
        enviarPorCorreo(datos);
      })
      .then(function () {
        boton.disabled = false;
      });
  });
})();
