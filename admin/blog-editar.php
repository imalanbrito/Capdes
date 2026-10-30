<?php
namespace Capdes\Admin;

require __DIR__ . '/inc/nucleo.php';

$usuario = requerir_sesion();
$datos = leer_dato('blog.json');
$categorias = $datos['categorias'];

// ¿Artículo nuevo o existente?
$slug = (string) ($_GET['slug'] ?? '');
$original = null;
if ($slug !== '') {
    foreach ($datos['entradas'] as $en) {
        if ($en['slug'] === $slug) {
            $original = $en;
        }
    }
    if (!$original) {
        avisar('Ese artículo ya no existe.', 'error');
        redirigir('blog.php');
    }
}

$en = $original ?? [
    'titulo' => '',
    'fecha' => date('Y-m-d'),
    'categoria' => $categorias[0] ?? '',
    'imagen' => '',
    'descripcion_imagen' => '',
    'resumen' => '',
    'contenido' => [],
];
$texto = implode("\n\n", $en['contenido']);
$borrador = !empty($en['borrador']);
$errores = [];

/** Párrafos del texto: se separan con una línea en blanco. */
function parrafos(string $texto): array
{
    $partes = preg_split('/\R\s*\R/u', trim($texto));
    $partes = array_map(fn($p) => trim(preg_replace('/\s*\R\s*/u', ' ', $p)), $partes);
    return array_values(array_filter($partes, fn($p) => $p !== ''));
}

/** Dirección del artículo a partir del título, sin repetir una que ya exista. */
function slug_nuevo(string $titulo, array $entradas): string
{
    $base = \Capdes\Generador\slug(strtr($titulo, ['ü' => 'u', 'Ü' => 'u']));
    $base = trim(substr($base, 0, 70), '-') ?: 'articulo';
    $usados = array_column($entradas, 'slug');
    $slug = $base;
    for ($n = 2; in_array($slug, $usados, true); $n++) {
        $slug = "$base-$n";
    }
    return $slug;
}

if (es_post()) {
    verificar_csrf();
    $en['titulo'] = trim((string) ($_POST['titulo'] ?? ''));
    $en['fecha'] = (string) ($_POST['fecha'] ?? '');
    $en['categoria'] = (string) ($_POST['categoria'] ?? '');
    $en['descripcion_imagen'] = trim((string) ($_POST['descripcion_imagen'] ?? ''));
    $en['resumen'] = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['resumen'] ?? '')));
    $texto = (string) ($_POST['contenido'] ?? '');
    $en['contenido'] = parrafos($texto);
    $borrador = !empty($_POST['borrador']);

    if ($en['titulo'] === '' || mb_strlen($en['titulo']) > 150) {
        $errores['titulo'] = 'Escribe un título de máximo 150 caracteres.';
    }
    $f = \DateTime::createFromFormat('!Y-m-d', $en['fecha']);
    if (!$f || $f->format('Y-m-d') !== $en['fecha']) {
        $errores['fecha'] = 'Elige una fecha válida.';
    }
    if (!in_array($en['categoria'], $categorias, true)) {
        $errores['categoria'] = 'Elige una categoría.';
    }
    if ($en['resumen'] === '' || mb_strlen($en['resumen']) > 320) {
        $errores['resumen'] = 'Escribe un resumen de una o dos frases (máximo 320 caracteres).';
    }
    if (!$en['contenido']) {
        $errores['contenido'] = 'Escribe el texto del artículo.';
    }
    if ($en['descripcion_imagen'] === '' || mb_strlen($en['descripcion_imagen']) > 200) {
        $errores['descripcion_imagen'] = 'Describe brevemente qué se ve en la foto (máximo 200 caracteres).';
    }
    $subio_foto = ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if (!$subio_foto && $en['imagen'] === '') {
        $errores['imagen'] = 'Elige la foto principal del artículo.';
    }

    if (!$errores && $subio_foto) {
        try {
            $en['imagen'] = guardar_foto($_FILES['imagen'], 'img/blog', $en['titulo']);
        } catch (\RuntimeException $error) {
            $errores['imagen'] = $error->getMessage();
        }
    }

    if (!$errores) {
        $es_nuevo = $original === null;
        $guardado = $en;
        if ($borrador) {
            $guardado['borrador'] = true;
        } else {
            unset($guardado['borrador']);
        }
        try {
            guardar_y_publicar('blog.json', function (array $datos) use ($guardado, $es_nuevo, $slug) {
                if ($es_nuevo) {
                    $guardado = ['slug' => slug_nuevo($guardado['titulo'], $datos['entradas'])] + $guardado;
                    $datos['entradas'][] = $guardado;
                    return $datos;
                }
                foreach ($datos['entradas'] as $i => $actual) {
                    if ($actual['slug'] === $slug) {
                        $datos['entradas'][$i] = ['slug' => $slug] + $guardado;
                        return $datos;
                    }
                }
                throw new \RuntimeException('Ese artículo ya no existe; tal vez alguien más lo borró.');
            }, ($es_nuevo ? 'Creó' : 'Editó') . ' el artículo "' . $en['titulo'] . '"' . ($borrador ? ' (borrador)' : ''));
            avisar($borrador
                ? 'Se guardó el borrador "' . $en['titulo'] . '". No se verá en el sitio hasta que lo publiques.'
                : 'Se publicó "' . $en['titulo'] . '". Ya está en el sitio.');
            redirigir('blog.php');
        } catch (\RuntimeException $error) {
            avisar($error->getMessage(), 'error');
        }
    }
}

$titulo_pagina = $original ? 'Editar artículo' : 'Escribir un artículo';
encabezado($titulo_pagina, 'blog', $usuario);

function error_de(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? '<span class="campo__error" id="error-' . $campo . '">' . e($errores[$campo]) . '</span>' : '';
}

function invalido(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? ' aria-invalid="true" aria-describedby="error-' . $campo . '"' : '';
}
?>
<p class="migas"><a href="blog.php">← Blog</a></p>
<h1><?= e($titulo_pagina) ?></h1>
<?php if ($errores): ?>
  <p class="aviso aviso--error" role="alert">Revisa los campos marcados en rojo.</p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="formulario formulario--editor" novalidate>
  <?= campo_csrf() ?>
  <label class="campo">Título
    <input name="titulo" required maxlength="150" value="<?= e($en['titulo']) ?>"<?= invalido($errores, 'titulo') ?>>
    <?= error_de($errores, 'titulo') ?>
  </label>

  <div class="formulario__fila">
    <label class="campo">Fecha de publicación
      <input name="fecha" type="date" required value="<?= e($en['fecha']) ?>"<?= invalido($errores, 'fecha') ?>>
      <?= error_de($errores, 'fecha') ?>
    </label>
    <label class="campo">Categoría
      <select name="categoria"<?= invalido($errores, 'categoria') ?>>
        <?php foreach ($categorias as $c): ?>
          <option<?= $c === $en['categoria'] ? ' selected' : '' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="campo__ayuda">No se muestra en el sitio; sirve para sugerir artículos relacionados.</span>
      <?= error_de($errores, 'categoria') ?>
    </label>
  </div>

  <fieldset class="campo campo--foto">
    <legend>Foto principal</legend>
    <div class="foto">
      <img class="foto__vista" src="<?= $en['imagen'] ? '../' . e($en['imagen']) : '' ?>" alt=""<?= $en['imagen'] ? '' : ' hidden' ?> data-vista-foto>
      <div class="foto__campos">
        <label class="campo"><?= $en['imagen'] ? 'Cambiar la foto (opcional)' : 'Elegir foto' ?>
          <input name="imagen" type="file" accept="image/jpeg,image/png,image/webp" data-elegir-foto<?= invalido($errores, 'imagen') ?>>
          <span class="campo__ayuda">JPG, PNG o WEBP. Se ajusta sola al tamaño adecuado para la web.</span>
          <?= error_de($errores, 'imagen') ?>
        </label>
        <label class="campo">¿Qué se ve en la foto?
          <input name="descripcion_imagen" required maxlength="200" value="<?= e($en['descripcion_imagen']) ?>" placeholder="p. ej. Mujeres de la comunidad en un taller de huertos"<?= invalido($errores, 'descripcion_imagen') ?>>
          <span class="campo__ayuda">No aparece en pantalla: la leen las personas ciegas con su lector de pantalla y los buscadores.</span>
          <?= error_de($errores, 'descripcion_imagen') ?>
        </label>
      </div>
    </div>
  </fieldset>

  <label class="campo">Resumen
    <textarea name="resumen" rows="3" maxlength="320" required<?= invalido($errores, 'resumen') ?> data-contador="320"><?= e($en['resumen']) ?></textarea>
    <span class="campo__ayuda">Una o dos frases. Se ve en el listado del blog y al compartir en WhatsApp o Facebook.</span>
    <?= error_de($errores, 'resumen') ?>
  </label>

  <div class="campo">
    <label for="contenido">Texto del artículo</label>
    <div class="editor">
      <div class="editor__barra" role="toolbar" aria-label="Formato del texto">
        <button type="button" class="editor__boton" data-formato="**" title="Negritas"><strong>N</strong> <span>Negritas</span></button>
        <button type="button" class="editor__boton" data-formato="*" title="Cursiva"><em>C</em> <span>Cursiva</span></button>
      </div>
      <textarea id="contenido" name="contenido" rows="18" required<?= invalido($errores, 'contenido') ?>><?= e($texto) ?></textarea>
    </div>
    <span class="campo__ayuda">Deja una <strong>línea en blanco</strong> entre párrafos. Selecciona palabras y usa los botones para ponerlas en <strong>negritas</strong> o <em>cursiva</em>.</span>
    <?= error_de($errores, 'contenido') ?>
  </div>

  <label class="casilla">
    <input type="checkbox" name="borrador" value="1"<?= $borrador ? ' checked' : '' ?>>
    <span>Guardar como borrador <small>(no se verá en el sitio hasta que quites esta marca)</small></span>
  </label>

  <div class="formulario__acciones">
    <button class="boton boton--principal" type="submit" data-texto-enviando="Guardando…"><?= $original ? 'Guardar cambios' : 'Publicar artículo' ?></button>
    <a class="boton boton--secundario" href="blog.php">Cancelar</a>
  </div>
</form>
<?php
pie();
