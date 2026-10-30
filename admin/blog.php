<?php
namespace Capdes\Admin;

require __DIR__ . '/inc/nucleo.php';

$usuario = requerir_sesion();

if (es_post()) {
    verificar_csrf();
    if (($_POST['accion'] ?? '') === 'borrar') {
        $slug = (string) ($_POST['slug'] ?? '');
        $titulo = '';
        foreach (leer_dato('blog.json')['entradas'] as $en) {
            if ($en['slug'] === $slug) {
                $titulo = $en['titulo'];
            }
        }
        try {
            guardar_y_publicar('blog.json', function (array $datos) use ($slug) {
                foreach ($datos['entradas'] as $i => $en) {
                    if ($en['slug'] === $slug) {
                        array_splice($datos['entradas'], $i, 1);
                        return $datos;
                    }
                }
                throw new \RuntimeException('Ese artículo ya no existe.');
            }, 'Borró el artículo "' . $titulo . '"');
            avisar('Se borró el artículo "' . $titulo . '".');
        } catch (\RuntimeException $error) {
            avisar($error->getMessage(), 'error');
        }
    }
    redirigir('blog.php');
}

$entradas = leer_dato('blog.json')['entradas'];
usort($entradas, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));

encabezado('Blog', 'blog', $usuario);
?>
<div class="titulo-con-accion">
  <h1>Blog</h1>
  <a class="boton boton--principal" href="blog-editar.php">+ Escribir un artículo</a>
</div>
<p class="introduccion">Los artículos aparecen en el sitio del más reciente al más antiguo, según su fecha.</p>

<?php if (!$entradas): ?>
  <p class="vacio">Todavía no hay artículos.</p>
<?php else: ?>
<ul class="lista-articulos">
  <?php foreach ($entradas as $en): ?>
    <li class="articulo">
      <img class="articulo__foto" src="../<?= e($en['imagen']) ?>" alt="" loading="lazy">
      <div class="articulo__info">
        <p class="articulo__titulo"><?= e($en['titulo']) ?></p>
        <p class="articulo__meta">
          <?php if (!empty($en['borrador'])): ?><span class="etiqueta etiqueta--borrador">Borrador</span><?php else: ?><span class="etiqueta etiqueta--publicado">Publicado</span><?php endif; ?>
          <time datetime="<?= e($en['fecha']) ?>"><?= e(\Capdes\Generador\fecha_larga($en['fecha'])) ?></time>
        </p>
      </div>
      <div class="articulo__acciones">
        <a class="boton boton--chico boton--principal" href="blog-editar.php?slug=<?= urlencode($en['slug']) ?>">Editar</a>
        <?php if (empty($en['borrador'])): ?>
          <a class="boton boton--chico boton--secundario" href="../blog/<?= e($en['slug']) ?>.html" target="_blank" rel="noopener">Ver ↗</a>
        <?php endif; ?>
        <form method="post" class="en-linea" data-confirmar="¿Borrar el artículo &quot;<?= e($en['titulo']) ?>&quot;? Se quitará del sitio y no se puede deshacer desde aquí.">
          <?= campo_csrf() ?><input type="hidden" name="accion" value="borrar"><input type="hidden" name="slug" value="<?= e($en['slug']) ?>">
          <button type="submit" class="boton boton--chico boton--peligro">Borrar</button>
        </form>
      </div>
    </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
<?php
pie();
