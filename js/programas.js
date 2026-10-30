// Página de Programas: filtro por tipo de programa, galería de jornadas con filtro
// por programa y visor de fotos. El HTML lo genera herramientas/menu.py; la galería
// se vuelve a armar desde datos/programas.json para que las fotos que se agreguen
// desde el panel aparezcan sin regenerar la página.
(function () {
  var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
    'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

  function escapar(texto) {
    return String(texto == null ? '' : texto)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#x27;');
  }

  function fechaLarga(iso) {
    var p = String(iso).split('-');
    return parseInt(p[2], 10) + ' de ' + MESES[parseInt(p[1], 10) - 1] + ' de ' + p[0];
  }

  // ───── Filtro de programas por tipo ─────
  var filtrosProgramas = document.querySelector('[data-programas-filtros]');
  var descripcionTipo = document.querySelector('[data-tipo-descripcion]');
  if (filtrosProgramas) {
    var botonesTipo = filtrosProgramas.querySelectorAll('[data-tipo]');
    botonesTipo.forEach(function (boton) {
      boton.addEventListener('click', function () {
        var tipo = boton.getAttribute('data-tipo');
        botonesTipo.forEach(function (b) { b.setAttribute('aria-pressed', String(b === boton)); });
        document.querySelectorAll('[data-programas-lista] .programa').forEach(function (programa) {
          programa.hidden = !!tipo && programa.getAttribute('data-tipo') !== tipo;
        });
        if (descripcionTipo) {
          descripcionTipo.textContent = boton.getAttribute('data-descripcion') ||
            descripcionTipo.getAttribute('data-original');
        }
      });
    });
  }

  // ───── Galería de jornadas ─────
  var listaGaleria = document.querySelector('[data-galeria-lista]');
  var filtrosGaleria = document.querySelector('[data-galeria-filtros]');
  var vacio = document.querySelector('[data-galeria-vacio]');
  if (!listaGaleria) return;

  function itemGaleria(j, nombres) {
    var detalle = [j.lugar, j.fecha ? fechaLarga(j.fecha) : ''].filter(Boolean).join(' · ');
    var programa = nombres[j.programa] || '';
    return '<li class="galeria__item" data-programa="' + escapar(j.programa) + '">' +
      '<button type="button" class="galeria__boton" data-galeria-abrir ' +
      'data-programa-nombre="' + escapar(programa) + '" data-titulo="' + escapar(j.titulo) + '" ' +
      'data-detalle="' + escapar(detalle) + '">' +
      '<img src="' + escapar(j.imagen) + '" alt="' + escapar(j.descripcion || j.titulo) + '" loading="lazy">' +
      '<span class="galeria__info"><span class="galeria__programa">' + escapar(programa) + '</span>' +
      '<span class="galeria__titulo">' + escapar(j.titulo) + '</span></span>' +
      '</button></li>';
  }

  function filtrar(clave) {
    var visibles = 0;
    if (filtrosGaleria) {
      filtrosGaleria.querySelectorAll('[data-programa]').forEach(function (b) {
        b.setAttribute('aria-pressed', String(b.getAttribute('data-programa') === clave));
      });
    }
    listaGaleria.querySelectorAll('.galeria__item').forEach(function (item) {
      var mostrar = !clave || item.getAttribute('data-programa') === clave;
      item.hidden = !mostrar;
      if (mostrar) visibles++;
    });
    if (vacio) vacio.hidden = visibles > 0;
  }

  function prepararFiltros() {
    if (!filtrosGaleria) return;
    filtrosGaleria.querySelectorAll('[data-programa]').forEach(function (boton) {
      boton.addEventListener('click', function () { filtrar(boton.getAttribute('data-programa')); });
    });
  }

  // Lee las jornadas actuales y rehace la galería, los filtros y los contadores de cada programa.
  fetch('datos/programas.json', { cache: 'no-cache' })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (datos) {
      var nombres = {};
      var conteo = {};
      datos.programas.forEach(function (p) { nombres[p.clave] = p.nombre; conteo[p.clave] = 0; });
      datos.jornadas.forEach(function (j) { if (j.programa in conteo) conteo[j.programa]++; });

      listaGaleria.innerHTML = datos.jornadas.filter(function (j) { return j.programa in nombres; })
        .map(function (j) { return itemGaleria(j, nombres); }).join('');

      if (filtrosGaleria) {
        filtrosGaleria.innerHTML =
          '<button type="button" class="galeria__filtro" aria-pressed="true" data-programa="">Todas</button>' +
          datos.programas.filter(function (p) { return conteo[p.clave]; }).map(function (p) {
            return '<button type="button" class="galeria__filtro" aria-pressed="false" data-programa="' +
              escapar(p.clave) + '">' + escapar(p.nombre) + '</button>';
          }).join('');
      }

      document.querySelectorAll('[data-ver-jornadas]').forEach(function (enlace) {
        var n = conteo[enlace.getAttribute('data-ver-jornadas')] || 0;
        var numero = enlace.querySelector('[data-conteo]');
        if (numero) numero.textContent = n;
        enlace.hidden = !n;
      });
    })
    .catch(function () { /* se queda la galería que trae la página */ })
    .then(prepararFiltros);

  // "Ver jornadas" en cada programa: filtra la galería y baja hasta ella.
  document.querySelectorAll('[data-ver-jornadas]').forEach(function (enlace) {
    enlace.addEventListener('click', function () {
      filtrar(enlace.getAttribute('data-ver-jornadas'));
    });
  });

  // ───── Visor de fotos ─────
  var visor = document.querySelector('[data-visor]');
  if (!visor || typeof visor.showModal !== 'function') return;

  var imagen = visor.querySelector('[data-visor-img]');
  var textoPrograma = visor.querySelector('[data-visor-programa]');
  var textoTitulo = visor.querySelector('[data-visor-titulo]');
  var textoDetalle = visor.querySelector('[data-visor-detalle]');
  var contador = visor.querySelector('[data-visor-contador]');
  var fotos = [];
  var actual = 0;
  var origen = null;

  function mostrar(indice) {
    actual = (indice + fotos.length) % fotos.length;
    var boton = fotos[actual];
    var miniatura = boton.querySelector('img');
    imagen.src = miniatura.src;
    imagen.alt = miniatura.alt;
    textoPrograma.textContent = boton.getAttribute('data-programa-nombre');
    textoTitulo.textContent = boton.getAttribute('data-titulo');
    textoDetalle.textContent = boton.getAttribute('data-detalle');
    contador.textContent = (actual + 1) + ' de ' + fotos.length;
  }

  // Delegado en la lista, porque la galería se vuelve a armar al leer los datos.
  listaGaleria.addEventListener('click', function (e) {
    var boton = e.target.closest('[data-galeria-abrir]');
    if (!boton) return;
    // Solo se navega entre las fotos visibles con el filtro actual.
    fotos = Array.prototype.filter.call(listaGaleria.querySelectorAll('[data-galeria-abrir]'), function (b) {
      return !b.closest('.galeria__item').hidden;
    });
    origen = boton;
    mostrar(fotos.indexOf(boton));
    visor.showModal();
  });

  visor.querySelector('[data-visor-cerrar]').addEventListener('click', function () { visor.close(); });
  visor.querySelector('[data-visor-anterior]').addEventListener('click', function () { mostrar(actual - 1); });
  visor.querySelector('[data-visor-siguiente]').addEventListener('click', function () { mostrar(actual + 1); });

  visor.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowLeft') mostrar(actual - 1);
    else if (e.key === 'ArrowRight') mostrar(actual + 1);
  });

  // Clic fuera de la foto (en el fondo oscuro) cierra el visor.
  visor.addEventListener('click', function (e) {
    if (e.target === visor) visor.close();
  });

  visor.addEventListener('close', function () {
    if (origen) origen.focus();
  });
})();
