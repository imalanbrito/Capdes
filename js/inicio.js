// Página de inicio: carrusel de jornadas (con zoom al entrar en pantalla) y
// frase con parallax que se revela palabra por palabra al hacer scroll.
(function () {
  var reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function limitar(valor) {
    return Math.min(Math.max(valor, 0), 1);
  }

  // ───── Efectos ligados al scroll ─────
  // Cada sección recibe variables CSS entre 0 y 1 según su posición en pantalla;
  // css/renovacion.css las usa para el zoom, el parallax y el revelado.
  var entradas = document.querySelectorAll('[data-entrada]');
  var frases = document.querySelectorAll('[data-frase]');

  function actualizar() {
    var alto = window.innerHeight;

    entradas.forEach(function (el) {
      var caja = el.getBoundingClientRect();
      if (caja.bottom < 0 || caja.top > alto) return;
      // 0 cuando la sección asoma por abajo; 1 cuando su borde superior llega al 25 % de la pantalla.
      el.style.setProperty('--entrada', limitar((alto - caja.top) / (alto * 0.75)).toFixed(3));
    });

    frases.forEach(function (el) {
      var caja = el.getBoundingClientRect();
      if (caja.bottom < 0 || caja.top > alto) return;
      // Parallax: 0 al entrar por abajo, 1 al salir por arriba.
      el.style.setProperty('--p', limitar((alto - caja.top) / (alto + caja.height)).toFixed(3));
      // Revelado: completo cuando el texto llega a un poco arriba del centro.
      var texto = el.querySelector('.frase__texto').getBoundingClientRect();
      el.style.setProperty('--r', limitar((alto * 0.92 - texto.top) / (alto * 0.45)).toFixed(3));
    });
  }

  if (reducido) {
    entradas.forEach(function (el) { el.style.setProperty('--entrada', '1'); });
    frases.forEach(function (el) {
      el.style.setProperty('--p', '0.5');
      el.style.setProperty('--r', '1');
    });
  } else {
    var pendiente = false;
    var pedir = function () {
      if (pendiente) return;
      pendiente = true;
      requestAnimationFrame(function () {
        pendiente = false;
        actualizar();
      });
    };
    window.addEventListener('scroll', pedir, { passive: true });
    window.addEventListener('resize', pedir);
    actualizar();
  }

  // ───── Contenido editable: "Nuestro impacto" y eventos ─────
  // La página ya trae este contenido (herramientas/menu.py), pero aquí se vuelve
  // a leer de datos/impacto.json y datos/eventos.json para que los cambios hechos
  // desde el panel se vean sin regenerar el HTML. Si la lectura falla, se queda lo que trae la página.
  var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
    'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

  var ICONOS_IMPACTO = {
    estrella: '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
    personas: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>' +
      '<path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    corazon: '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78' +
      'l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
    casa: '<path d="M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>'
  };

  var ICONO_UBICACION = '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" ' +
    'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
    '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/>' +
    '<circle cx="12" cy="9.5" r="2.5"/></svg>';

  function escapar(texto) {
    return String(texto == null ? '' : texto)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#x27;');
  }

  function retraso(i) {
    return '--user-animation-delay:' + (0.1 + 0.12 * i).toFixed(2) + 's;';
  }

  function leerDatos(archivo) {
    return fetch('datos/' + archivo, { cache: 'no-cache' }).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  function pintarEventos(datos) {
    var lista = document.querySelector('[data-eventos]');
    if (!lista) return;
    lista.innerHTML = datos.eventos.map(function (e, i) {
      var mes = String(e.mes).trim().toLowerCase();
      if (MESES.indexOf(mes) === -1) throw new Error('mes no válido: ' + e.mes);
      return '<li class="eventos__tarjeta transition transition--slide" style="' + retraso(i) + '">' +
        '<div class="eventos__foto"><img src="' + escapar(e.imagen) + '" alt="' +
        escapar(e.descripcion || e.titulo) + '" loading="lazy">' +
        '<p class="eventos__fecha"><span class="eventos__dia">' + ('0' + parseInt(e.dia, 10)).slice(-2) + '</span>' +
        '<span class="eventos__mes"><abbr title="' + mes + '">' + mes.slice(0, 3).toUpperCase() + '</abbr></span></p></div>' +
        '<div class="eventos__cuerpo">' +
        (e.tipo ? '<p class="eventos__tipo">' + escapar(e.tipo) + '</p>' : '') +
        '<h3 class="eventos__nombre">' + escapar(e.titulo) + '</h3>' +
        (e.lugar ? '<p class="eventos__lugar">' + ICONO_UBICACION + '<span>' + escapar(e.lugar) + '</span></p>' : '') +
        '</div></li>';
    }).join('');
  }

  function pintarImpacto(datos) {
    var titulo = document.querySelector('[data-impacto-titulo]');
    var cita = document.querySelector('[data-impacto-cita]');
    var listaCifras = document.querySelector('[data-impacto-cifras]');
    var listaAreas = document.querySelector('[data-impacto-areas]');
    if (titulo) titulo.textContent = datos.titulo;
    if (cita) cita.textContent = datos.cita;

    if (listaCifras) {
      listaCifras.innerHTML = datos.cifras.map(function (c, i) {
        var trazo = ICONOS_IMPACTO[c.icono] || ICONOS_IMPACTO.estrella;
        var prefijo = escapar(c.prefijo || '');
        var numero = parseInt(c.numero, 10);
        return '<li class="impacto__cifra transition transition--slide" style="' + retraso(i) + '">' +
          '<span class="impacto__icono"><svg width="28" height="28" viewBox="0 0 24 24" aria-hidden="true" ' +
          'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
          trazo + '</svg></span>' +
          '<span class="impacto__dato"><strong class="impacto__numero" data-contar="' + numero +
          '" data-prefijo="' + prefijo + '">' + prefijo + numero + '</strong>' +
          '<span class="impacto__etiqueta">' + escapar(c.texto) + '</span></span></li>';
      }).join('');
    }

    if (listaAreas) {
      listaAreas.innerHTML = datos.areas.map(function (a, i) {
        var numero = '';
        if (a.numero !== null && a.numero !== undefined && a.numero !== '') {
          var prefijo = escapar(a.prefijo === undefined ? '+' : a.prefijo);
          var valor = parseInt(a.numero, 10);
          numero = '<strong class="impacto__area-numero" data-contar="' + valor + '" data-prefijo="' +
            prefijo + '">' + prefijo + valor + '</strong>';
        }
        return '<li class="impacto__area transition transition--slide" style="' + retraso(i) + '">' +
          '<img src="' + escapar(a.imagen) + '" alt="' + escapar(a.descripcion || a.titulo) + '" loading="lazy">' +
          '<span class="impacto__area-titulo">' + numero + escapar(a.titulo) + '</span></li>';
      }).join('');
    }
  }

  var ICONOS_DONAR = {
    paypal: '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h4"/>',
    gofundme: ICONOS_IMPACTO.corazon
  };

  var ICONO_SALIR = '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" ' +
    'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' +
    '<path d="M7 17 17 7M8 7h9v9"/></svg>';

  function plataforma(nombre) {
    return String(nombre || '').toLowerCase().replace(/[^a-z0-9-]/g, '') || 'otra';
  }

  function pintarDonar(datos) {
    var titulo = document.querySelector('[data-donar-titulo]');
    var texto = document.querySelector('[data-donar-texto]');
    var lista = document.querySelector('[data-donar-opciones]');
    if (titulo) titulo.textContent = datos.titulo;
    if (texto) texto.textContent = datos.texto;
    if (!lista) return;
    lista.innerHTML = datos.opciones.map(function (o, i) {
      var clave = plataforma(o.plataforma);
      var trazo = ICONOS_DONAR[clave] || ICONOS_DONAR.gofundme;
      return '<li class="donar__opcion donar__opcion--' + clave + ' transition transition--slide" style="' +
        retraso(i) + '">' +
        '<span class="donar__icono"><svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" ' +
        'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
        trazo + '</svg></span>' +
        '<h3 class="donar__nombre">' + escapar(o.titulo) + '</h3>' +
        '<p class="donar__texto">' + escapar(o.texto) + '</p>' +
        '<a class="donar__boton" href="' + escapar(o.enlace) + '" target="_blank" rel="noopener">' +
        escapar(o.boton) + ICONO_SALIR +
        '<span class="solo-lectores"> (se abre en otra pestaña)</span></a>' +
        '</li>';
    }).join('');
  }

  function pintarAliados(datos) {
    var titulo = document.querySelector('[data-aliados-titulo]');
    var carril = document.querySelector('[data-aliados]');
    if (titulo) titulo.textContent = datos.titulo;
    if (!carril) return;
    // La segunda lista es una copia para que el desplazamiento sea continuo.
    function lista(copia) {
      var items = datos.aliados.map(function (a) {
        var logo = '<img src="' + escapar(a.logo) + '" alt="' + (copia ? '' : escapar(a.nombre)) + '" loading="lazy">';
        if (a.enlace) {
          logo = '<a href="' + escapar(a.enlace) + '" target="_blank" rel="noopener"' +
            (copia ? ' tabindex="-1"' : '') + '>' + logo + '</a>';
        }
        return '<li class="aliados__logo">' + logo + '</li>';
      }).join('');
      return '<ul class="aliados__lista"' + (copia ? ' aria-hidden="true"' : '') + '>' + items + '</ul>';
    }
    carril.innerHTML = lista(false) + lista(true);
  }

  function cargarSeccion(archivo, pintar) {
    return leerDatos(archivo)
      .then(function (datos) {
        // Se arma aparte para no dejar la sección a medias si los datos traen un error.
        pintar(datos);
      })
      .catch(function () { /* se queda el contenido que trae la página */ });
  }

  var secciones = [
    ['[data-impacto-cifras]', 'impacto.json', pintarImpacto],
    ['[data-eventos]', 'eventos.json', pintarEventos],
    ['[data-donar-opciones]', 'donar.json', pintarDonar],
    ['[data-aliados]', 'aliados.json', pintarAliados]
  ];
  Promise.all(secciones.map(function (s) {
    return document.querySelector(s[0]) ? cargarSeccion(s[1], s[2]) : null;
  })).then(function () {
    // Las tarjetas nuevas usan las animaciones de entrada de js/animaciones.js.
    if (window.capdesAnimar) window.capdesAnimar(document.querySelectorAll('.impacto, .eventos, .donar, .aliados'));
    prepararCifras();
  });

  // ───── Cifras que cuentan hacia arriba al aparecer ─────
  function prepararCifras() {
    if (reducido || !('IntersectionObserver' in window)) return;
    var observadorCifras = new IntersectionObserver(function (entradasObservadas) {
      entradasObservadas.forEach(function (entrada) {
        if (!entrada.isIntersecting) return;
        observadorCifras.unobserve(entrada.target);
        contar(entrada.target);
      });
    }, { threshold: 0.6 });
    document.querySelectorAll('[data-contar]').forEach(function (el) {
      el.textContent = (el.dataset.prefijo || '') + '0';
      observadorCifras.observe(el);
    });
  }

  function contar(el) {
    var meta = Number(el.dataset.contar);
    var prefijo = el.dataset.prefijo || '';
    var inicio = null;
    var duracion = 1600;
    function paso(t) {
      if (inicio === null) inicio = t;
      var avance = Math.min((t - inicio) / duracion, 1);
      el.textContent = prefijo + Math.round(meta * (1 - Math.pow(1 - avance, 3)));
      if (avance < 1) requestAnimationFrame(paso);
    }
    requestAnimationFrame(paso);
  }

  // ───── Carrusel de jornadas ─────
  var carrusel = document.querySelector('[data-carrusel]');
  if (!carrusel) return;

  // Las fotos salen de datos/jornadas.json: de su lista "jornadas" o, si "fuente"
  // es "drive", de una carpeta pública de Google Drive. La página ya trae las
  // diapositivas del archivo (herramientas/menu.py); si la lectura falla, se quedan esas.
  cargarJornadas()
    .then(function (jornadas) {
      if (jornadas && jornadas.length) pintarSlides(jornadas);
    })
    .catch(function () { /* se quedan las diapositivas que trae la página */ })
    .then(function () { montarCarrusel(carrusel); });

  function cargarJornadas() {
    return fetch('datos/jornadas.json', { cache: 'no-cache' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (datos) {
        var drive = datos.drive || {};
        if (datos.fuente === 'drive' && drive.carpeta_id && drive.api_key) {
          return cargarDesdeDrive(drive.carpeta_id, drive.api_key);
        }
        return datos.jornadas;
      });
  }

  // Lista las imágenes de la carpeta de Drive (debe estar compartida como "Cualquier
  // persona con el enlace"). Se ordenan por nombre de archivo; el título es el nombre
  // sin número ni extensión ("02 - Taller de huertos.jpg" → "Taller de huertos") y la
  // descripción del archivo en Drive se usa como texto alternativo.
  function cargarDesdeDrive(carpetaId, apiKey) {
    var url = 'https://www.googleapis.com/drive/v3/files' +
      '?q=' + encodeURIComponent("'" + carpetaId + "' in parents and mimeType contains 'image/' and trashed = false") +
      '&orderBy=name&pageSize=50&fields=' + encodeURIComponent('files(id,name,description)') +
      '&key=' + encodeURIComponent(apiKey);
    return fetch(url)
      .then(function (r) {
        if (!r.ok) throw new Error('Drive HTTP ' + r.status);
        return r.json();
      })
      .then(function (datos) {
        return (datos.files || []).map(function (archivo) {
          var titulo = archivo.name
            .replace(/\.[^.]+$/, '')
            .replace(/^\d+\s*[-_.]?\s*/, '')
            .trim();
          var llenar = /^banner\b/i.test(titulo);
          return {
            imagen: 'https://lh3.googleusercontent.com/d/' + archivo.id + '=w1600',
            titulo: llenar ? '' : titulo,
            descripcion: archivo.description || titulo,
            llenar_marco: llenar
          };
        });
      });
  }

  function pintarSlides(jornadas) {
    var pista = carrusel.querySelector('.jornadas__pista');
    var fragmento = document.createDocumentFragment();
    jornadas.forEach(function (j, i) {
      var figura = document.createElement('figure');
      figura.className = 'jornadas__slide' +
        (j.llenar_marco ? ' jornadas__slide--llena' : '') +
        (i === 0 ? ' jornadas__slide--activa' : '');
      if (!j.llenar_marco) figura.appendChild(imagen('jornadas__fondo', j.imagen, '', i));
      figura.appendChild(imagen('jornadas__foto', j.imagen, j.descripcion || j.titulo || '', i));
      if (j.titulo) {
        var titulo = document.createElement('figcaption');
        titulo.className = 'jornadas__titulo';
        titulo.textContent = j.titulo;
        figura.appendChild(titulo);
      }
      fragmento.appendChild(figura);
    });
    pista.replaceChildren(fragmento);
  }

  function imagen(clase, src, alt, indice) {
    var img = document.createElement('img');
    img.className = clase;
    img.src = src;
    img.alt = alt;
    if (!alt) img.setAttribute('aria-hidden', 'true');
    if (indice > 0) img.loading = 'lazy';
    return img;
  }

  function montarCarrusel(carrusel) {
    var slides = Array.prototype.slice.call(carrusel.querySelectorAll('.jornadas__slide'));
    var contenedorPuntos = carrusel.querySelector('.jornadas__puntos');
    var botonPausa = carrusel.querySelector('.jornadas__pausa');
    var INTERVALO = 6000;
    var actual = 0;
    var temporizador = null;
    var pausadoPorUsuario = reducido;
    var enPantalla = true;
    var interactuando = false;

    var puntos = slides.map(function (slide, i) {
      var titulo = slide.querySelector('figcaption');
      var punto = document.createElement('button');
      punto.type = 'button';
      punto.className = 'jornadas__punto';
      punto.setAttribute('aria-label', 'Jornada ' + (i + 1) + ' de ' + slides.length +
        (titulo ? ': ' + titulo.textContent : ''));
      punto.addEventListener('click', function () {
        ir(i);
        reiniciar();
      });
      contenedorPuntos.appendChild(punto);

      slide.setAttribute('aria-roledescription', 'diapositiva');
      slide.setAttribute('aria-label', (i + 1) + ' de ' + slides.length);
      return punto;
    });

    function ir(indice) {
      actual = (indice + slides.length) % slides.length;
      slides.forEach(function (slide, i) {
        var activa = i === actual;
        slide.classList.toggle('jornadas__slide--activa', activa);
        slide.setAttribute('aria-hidden', String(!activa));
        // Las imágenes de las diapositivas que no se ven se cargan hasta acercarse su turno.
        if (activa || i === (actual + 1) % slides.length) {
          slide.querySelectorAll('img[loading="lazy"]').forEach(function (img) { img.loading = 'eager'; });
        }
      });
      puntos.forEach(function (punto, i) {
        if (i === actual) punto.setAttribute('aria-current', 'true');
        else punto.removeAttribute('aria-current');
      });
    }

    function detener() {
      clearInterval(temporizador);
      temporizador = null;
    }

    function reiniciar() {
      detener();
      if (slides.length < 2 || pausadoPorUsuario || !enPantalla || interactuando || document.hidden) return;
      temporizador = setInterval(function () { ir(actual + 1); }, INTERVALO);
    }

    function actualizarBotonPausa() {
      carrusel.classList.toggle('jornadas__carrusel--pausado', pausadoPorUsuario);
      botonPausa.setAttribute('aria-pressed', String(pausadoPorUsuario));
      botonPausa.setAttribute('aria-label', pausadoPorUsuario ? 'Reanudar carrusel' : 'Pausar carrusel');
    }

    carrusel.querySelector('.jornadas__flecha--anterior').addEventListener('click', function () {
      ir(actual - 1);
      reiniciar();
    });
    carrusel.querySelector('.jornadas__flecha--siguiente').addEventListener('click', function () {
      ir(actual + 1);
      reiniciar();
    });

    botonPausa.addEventListener('click', function () {
      pausadoPorUsuario = !pausadoPorUsuario;
      actualizarBotonPausa();
      reiniciar();
    });

    // Se detiene mientras el mouse o el foco del teclado están en el carrusel.
    ['mouseenter', 'focusin'].forEach(function (evento) {
      carrusel.addEventListener(evento, function () {
        interactuando = true;
        detener();
      });
    });
    carrusel.addEventListener('mouseleave', function () {
      interactuando = false;
      reiniciar();
    });
    carrusel.addEventListener('focusout', function (e) {
      if (carrusel.contains(e.relatedTarget)) return;
      interactuando = false;
      reiniciar();
    });

    carrusel.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') ir(actual - 1);
      else if (e.key === 'ArrowRight') ir(actual + 1);
    });

    // Deslizar con el dedo en pantallas táctiles.
    var inicioX = null;
    carrusel.addEventListener('pointerdown', function (e) {
      if (e.pointerType !== 'mouse') inicioX = e.clientX;
    });
    carrusel.addEventListener('pointerup', function (e) {
      if (inicioX === null) return;
      var distancia = e.clientX - inicioX;
      inicioX = null;
      if (Math.abs(distancia) < 40) return;
      ir(actual + (distancia < 0 ? 1 : -1));
      reiniciar();
    });

    // Solo avanza solo cuando está en pantalla y la pestaña está visible.
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entradasObservadas) {
        enPantalla = entradasObservadas[0].isIntersecting;
        reiniciar();
      }, { threshold: 0.35 }).observe(carrusel);
    }
    document.addEventListener('visibilitychange', reiniciar);

    ir(0);
    actualizarBotonPausa();
    reiniciar();
  }
})();
