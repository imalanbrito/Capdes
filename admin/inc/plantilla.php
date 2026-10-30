<?php
/** Encabezado y pie comunes de las páginas del panel. */

namespace Capdes\Admin;

function version_recurso(string $archivo): string
{
    return $archivo . '?v=' . substr(md5_file(__DIR__ . '/../' . $archivo), 0, 8);
}

/** Abre la página. $seccion marca la opción activa del menú lateral. */
function encabezado(string $titulo, string $seccion = '', ?array $usuario = null): void
{
    $menu = [
        'inicio' => ['index.php', 'Inicio'],
        'blog' => ['blog.php', 'Blog'],
    ];
    if ($usuario && $usuario['rol'] === 'administrador') {
        $menu['usuarios'] = ['usuarios.php', 'Usuarios'];
    }
    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titulo) ?> · Panel CAPDES</title>
<link rel="icon" href="../img/favicon.ico" sizes="any">
<link rel="stylesheet" href="<?= e(version_recurso('admin.css')) ?>">
<script src="<?= e(version_recurso('admin.js')) ?>" defer></script>
</head>
<body class="<?= $usuario ? 'con-menu' : 'sin-menu' ?>">
<?php if ($usuario): ?>
<header class="barra">
  <a class="barra__marca" href="index.php"><img src="../img/favicon-192.png" alt="" width="36" height="36"><span>Panel <strong>CAPDES</strong></span></a>
  <button class="barra__menu" type="button" aria-expanded="false" aria-controls="lateral" data-abrir-menu>Menú</button>
</header>
<nav class="lateral" id="lateral" aria-label="Secciones del panel">
  <ul>
    <?php foreach ($menu as $clave => [$enlace, $texto]): ?>
      <li><a href="<?= $enlace ?>"<?= $clave === $seccion ? ' aria-current="page"' : '' ?>><?= e($texto) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <div class="lateral__pie">
    <a href="../index.html" target="_blank" rel="noopener">Ver el sitio ↗</a>
    <a href="cuenta.php"<?= $seccion === 'cuenta' ? ' aria-current="page"' : '' ?>>Mi cuenta <small><?= e($usuario['nombre']) ?></small></a>
    <form method="post" action="salir.php"><?= campo_csrf() ?><button type="submit" class="enlace">Salir</button></form>
  </div>
</nav>
<?php endif; ?>
<main class="contenido" id="contenido">
<?php foreach (tomar_avisos() as $aviso): ?>
  <p class="aviso aviso--<?= e($aviso['tipo']) ?>" role="<?= $aviso['tipo'] === 'error' ? 'alert' : 'status' ?>"><?= e($aviso['texto']) ?></p>
<?php endforeach;
}

function pie(): void
{
    ?>
</main>
</body>
</html>
<?php
}

/** Fecha y hora legibles, p. ej. "29 de octubre de 2026, 18:40". */
function fecha_hora(string $iso): string
{
    $t = strtotime($iso);
    return $t ? \Capdes\Generador\fecha_larga(date('Y-m-d', $t)) . ', ' . date('H:i', $t) : '';
}
