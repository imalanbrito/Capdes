<?php
namespace Capdes\Admin;

require __DIR__ . '/inc/nucleo.php';

$usuario = requerir_sesion();
$blog = leer_dato('blog.json')['entradas'];
$publicados = count(array_filter($blog, fn($en) => empty($en['borrador'])));
$borradores = count($blog) - $publicados;

encabezado('Inicio', 'inicio', $usuario);
?>
<h1>Hola, <?= e(explode(' ', $usuario['nombre'])[0]) ?></h1>
<p class="introduccion">Desde aquí puedes editar el contenido del sitio sin tocar código. Cada cambio se publica al guardar.</p>

<div class="tarjetas">
  <a class="tarjeta" href="blog-editar.php">
    <span class="tarjeta__titulo">Escribir un artículo</span>
    <span class="tarjeta__texto">Publica una noticia o reflexión en el blog.</span>
  </a>
  <a class="tarjeta" href="blog.php">
    <span class="tarjeta__titulo">Blog</span>
    <span class="tarjeta__texto"><?= $publicados ?> publicado<?= $publicados === 1 ? '' : 's' ?><?= $borradores ? " · $borradores borrador" . ($borradores === 1 ? '' : 'es') : '' ?></span>
  </a>
</div>

<section class="seccion">
  <h2>Actividad reciente</h2>
  <?php $actividad = bitacora(12); ?>
  <?php if (!$actividad): ?>
    <p class="vacio">Todavía no hay actividad.</p>
  <?php else: ?>
    <ul class="actividad">
      <?php foreach ($actividad as $a): ?>
        <li><span class="actividad__quien"><?= e($a['usuario']) ?></span> <?= e($a['accion']) ?>
          <time datetime="<?= e($a['fecha']) ?>"><?= e(fecha_hora($a['fecha'])) ?></time></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php
pie();
