// Pie de página (todas las páginas). El HTML lo genera herramientas/menu.py; aquí se
// vuelven a leer los datos de contacto de datos/contacto.json para que los cambios
// hechos desde el panel se vean sin regenerar el HTML, y se maneja el formulario.
(function () {
  var pie = document.querySelector('.pie');
  if (!pie) return;

  var ICONOS_REDES = {
    facebook: '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" ' +
      'd="M24 12.0726C24 5.44354 18.629 0.0725708 12 0.0725708C5.37097 0.0725708 0 5.44354 0 ' +
      '12.0726C0 18.0619 4.38823 23.0264 10.125 23.9274V15.5414H7.07661V12.0726H10.125V9.4287C10.125 ' +
      '6.42144 11.9153 4.76031 14.6574 4.76031C15.9706 4.76031 17.3439 4.99451 17.3439 ' +
      '4.99451V7.94612H15.8303C14.34 7.94612 13.875 8.87128 13.875 9.82015V12.0726H17.2031L16.6708 ' +
      '15.5414H13.875V23.9274C19.6118 23.0264 24 18.0619 24 12.0726Z"/></svg>',
    instagram: '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" ' +
      'd="M12.0027 5.84808C8.59743 5.84808 5.85075 8.59477 5.85075 12C5.85075 15.4053 8.59743 ' +
      '18.1519 12.0027 18.1519C15.4079 18.1519 18.1546 15.4053 18.1546 12C18.1546 8.59477 15.4079 ' +
      '5.84808 12.0027 5.84808ZM12.0027 15.9996C9.80212 15.9996 8.00312 14.2059 8.00312 12C8.00312 ' +
      '9.7941 9.79677 8.00046 12.0027 8.00046C14.2086 8.00046 16.0022 9.7941 16.0022 12C16.0022 ' +
      '14.2059 14.2032 15.9996 12.0027 15.9996ZM19.8412 5.59644C19.8412 6.39421 19.1987 7.03135 ' +
      '18.4062 7.03135C17.6085 7.03135 16.9713 6.38885 16.9713 5.59644C16.9713 4.80402 17.6138 ' +
      '4.16153 18.4062 4.16153C19.1987 4.16153 19.8412 4.80402 19.8412 5.59644ZM23.9157 ' +
      '7.05277C23.8247 5.13063 23.3856 3.42801 21.9775 2.02522C20.5747 0.622429 18.8721 0.183388 ' +
      '16.9499 0.0870135C14.9689 -0.0254238 9.03112 -0.0254238 7.05008 0.0870135C5.1333 0.178034 ' +
      '3.43068 0.617075 2.02253 2.01986C0.614389 3.42265 0.180703 5.12527 0.0843279 7.04742C-0.0281093 ' +
      '9.02845 -0.0281093 14.9662 0.0843279 16.9472C0.175349 18.8694 0.614389 20.572 2.02253 ' +
      '21.9748C3.43068 23.3776 5.12794 23.8166 7.05008 23.913C9.03112 24.0254 14.9689 24.0254 ' +
      '16.9499 23.913C18.8721 23.822 20.5747 23.3829 21.9775 21.9748C23.3803 20.572 23.8193 18.8694 ' +
      '23.9157 16.9472C24.0281 14.9662 24.0281 9.03381 23.9157 7.05277ZM21.3564 19.0728C20.9388 ' +
      '20.1223 20.1303 20.9307 19.0755 21.3537C17.496 21.9802 13.7481 21.8356 12.0027 ' +
      '21.8356C10.2572 21.8356 6.50396 21.9748 4.92984 21.3537C3.88042 20.9361 3.07195 20.1276 ' +
      '2.64897 19.0728C2.02253 17.4934 2.16709 13.7455 2.16709 12C2.16709 10.2546 2.02789 6.50129 ' +
      '2.64897 4.92717C3.06659 3.87776 3.87507 3.06928 4.92984 2.6463C6.50931 2.01986 10.2572 ' +
      '2.16443 12.0027 2.16443C13.7481 2.16443 17.5014 2.02522 19.0755 2.6463C20.1249 3.06392 ' +
      '20.9334 3.8724 21.3564 4.92717C21.9828 6.50665 21.8383 10.2546 21.8383 12C21.8383 13.7455 ' +
      '21.9828 17.4987 21.3564 19.0728Z"/></svg>'
  };

  var ICONO_ENLACE = '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="none" ' +
    'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
    '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>' +
    '<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';

  var ICONO_TELEFONO = '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="none" ' +
    'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
    '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2' +
    ' 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3' +
    'a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>';

  var ICONO_CORREO = '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="none" ' +
    'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
    '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>';

  var ICONO_MENSAJE = '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="none" ' +
    'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
    '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.6 8.6 0 0 1-3.8-.9L3 21l1.9-5.2A8.4 8.4 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5z"/></svg>';

  function escapar(texto) {
    return String(texto == null ? '' : texto)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#x27;');
  }

  function enlacesRedes(redes) {
    return redes.map(function (r) {
      var red = String(r.red).trim().toLowerCase();
      var nombre = red.charAt(0).toUpperCase() + red.slice(1);
      return '<a href="' + escapar(r.enlace) + '" target="_blank" rel="noopener" aria-label="' +
        escapar(nombre) + ' de CAPDES">' + (ICONOS_REDES[red] || ICONO_ENLACE) + '</a>';
    }).join('');
  }

  function listaContacto(c) {
    var items = [];
    if (c.telefono) {
      items.push('<li><a href="tel:' + String(c.telefono).replace(/[^\d+]/g, '') + '">' +
        '<span class="pie__icono">' + ICONO_TELEFONO + '</span><span>' + escapar(c.telefono) + '</span></a></li>');
    }
    if (c.whatsapp) {
      items.push('<li><a href="https://wa.me/' + String(c.whatsapp).replace(/\D/g, '') +
        '" target="_blank" rel="noopener"><span class="pie__icono">' + ICONO_MENSAJE +
        '</span><span>WhatsApp</span></a></li>');
    }
    if (c.correo) {
      items.push('<li><a href="mailto:' + escapar(c.correo) + '"><span class="pie__icono">' + ICONO_CORREO +
        '</span><span>' + escapar(c.correo) + '</span></a></li>');
    }
    return items.join('');
  }

  var correo = 'contacto@capdes.org';

  // Las páginas en subcarpetas (p. ej. blog/) indican cómo llegar a la raíz con data-raiz="../".
  var raiz = document.documentElement.getAttribute('data-raiz') || '';

  fetch(raiz + 'datos/contacto.json', { cache: 'no-cache' })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (c) {
      var nombre = pie.querySelector('[data-pie-nombre]');
      var redes = pie.querySelector('[data-pie-redes]');
      var contacto = pie.querySelector('[data-pie-contacto]');
      if (nombre) nombre.textContent = c.nombre_completo;
      if (redes) redes.innerHTML = enlacesRedes(c.redes);
      if (contacto) contacto.innerHTML = listaContacto(c);
      if (c.correo) correo = c.correo;
    })
    .catch(function () { /* se queda el contenido que trae la página */ });

  var anio = pie.querySelector('[data-pie-anio]');
  if (anio) anio.textContent = new Date().getFullYear();

  // Mientras no exista el panel de administración (que guardará las suscripciones),
  // el formulario abre el correo de la persona con un mensaje listo para CAPDES.
  var formulario = pie.querySelector('[data-pie-formulario]');
  var aviso = pie.querySelector('[data-pie-aviso]');
  if (formulario) {
    formulario.addEventListener('submit', function (e) {
      e.preventDefault();
      var campo = formulario.querySelector('input[type="email"]');
      var asunto = 'Quiero recibir noticias de CAPDES';
      var cuerpo = 'Hola, me gustaría recibir información sobre sus jornadas, talleres y eventos.\n\n' +
        'Mi correo: ' + campo.value;
      window.location.href = 'mailto:' + correo + '?subject=' + encodeURIComponent(asunto) +
        '&body=' + encodeURIComponent(cuerpo);
      if (aviso) aviso.textContent = 'Se abrió tu aplicación de correo con el mensaje listo; solo envíalo.';
    });
  }
})();
