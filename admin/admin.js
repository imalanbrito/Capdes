// Panel de administrador: menú en celular, confirmaciones, formato del texto,
// vista previa de la foto y contador de caracteres. Todo funciona también sin este archivo.
(function () {
  // ───── Menú lateral en celular ─────
  var abrir = document.querySelector('[data-abrir-menu]');
  var lateral = document.getElementById('lateral');
  if (abrir && lateral) {
    abrir.addEventListener('click', function () {
      var abierto = lateral.classList.toggle('lateral--abierto');
      abrir.setAttribute('aria-expanded', String(abierto));
    });
  }

  // ───── Confirmar antes de borrar ─────
  document.querySelectorAll('form[data-confirmar]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirmar'))) e.preventDefault();
    });
  });

  // ───── Cambiar permisos al elegir (sin botón "Guardar") ─────
  document.querySelectorAll('form[data-enviar-al-cambiar]').forEach(function (form) {
    var boton = form.querySelector('[data-sin-js]');
    if (boton) boton.hidden = true;
    form.querySelector('select').addEventListener('change', function () { form.submit(); });
  });

  // ───── Evitar doble envío y avisar que se está guardando ─────
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      var boton = form.querySelector('[data-texto-enviando]');
      if (!boton) return;
      setTimeout(function () {
        boton.disabled = true;
        boton.textContent = boton.getAttribute('data-texto-enviando');
      }, 0);
    });
  });

  // ───── Negritas y cursiva: envuelve la selección con ** o * ─────
  var texto = document.getElementById('contenido');
  document.querySelectorAll('[data-formato]').forEach(function (boton) {
    boton.addEventListener('click', function () {
      if (!texto) return;
      var marca = boton.getAttribute('data-formato');
      var inicio = texto.selectionStart;
      var fin = texto.selectionEnd;
      var elegido = texto.value.slice(inicio, fin);
      // Si ya tiene la marca, se la quita.
      var antes = texto.value.slice(inicio - marca.length, inicio);
      var despues = texto.value.slice(fin, fin + marca.length);
      if (antes === marca && despues === marca && !(marca === '*' && texto.value.slice(inicio - 2, inicio) === '**')) {
        texto.setRangeText(elegido, inicio - marca.length, fin + marca.length, 'select');
      } else {
        // Los espacios de los extremos quedan fuera de la marca: "**palabra** " y no "**palabra **".
        var inicial = elegido.match(/^\s*/)[0];
        var final = elegido.match(/\s*$/)[0];
        var centro = elegido.trim() || 'texto';
        texto.setRangeText(inicial + marca + centro + marca + final, inicio, fin, 'end');
        texto.setSelectionRange(inicio + inicial.length + marca.length, inicio + inicial.length + marca.length + centro.length);
      }
      texto.focus();
    });
  });

  // ───── Vista previa de la foto elegida ─────
  var elegir = document.querySelector('[data-elegir-foto]');
  var vista = document.querySelector('[data-vista-foto]');
  if (elegir && vista) {
    elegir.addEventListener('change', function () {
      var archivo = elegir.files && elegir.files[0];
      if (!archivo) return;
      vista.src = URL.createObjectURL(archivo);
      vista.hidden = false;
    });
  }

  // ───── Contador de caracteres ─────
  document.querySelectorAll('[data-contador]').forEach(function (campo) {
    var maximo = Number(campo.getAttribute('data-contador'));
    var contador = document.createElement('span');
    contador.className = 'campo__contador';
    contador.setAttribute('aria-live', 'polite');
    campo.insertAdjacentElement('afterend', contador);
    function actualizar() { contador.textContent = campo.value.length + ' / ' + maximo; }
    campo.addEventListener('input', actualizar);
    actualizar();
  });

  // ───── El botón dice "Guardar borrador" cuando está marcada la casilla ─────
  var casilla = document.querySelector('input[name="borrador"]');
  var guardar = document.querySelector('.formulario--editor [type="submit"]');
  if (casilla && guardar) {
    var original = guardar.textContent;
    var cambiar = function () { guardar.textContent = casilla.checked ? 'Guardar borrador' : original; };
    casilla.addEventListener('change', cambiar);
    cambiar();
  }
})();
