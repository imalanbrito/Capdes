<?php
namespace Capdes\Admin;

require __DIR__ . '/inc/nucleo.php';

$usuario = requerir_sesion();
$errores = [];

if (es_post()) {
    verificar_csrf();
    $actual = (string) ($_POST['actual'] ?? '');
    $nueva = (string) ($_POST['nueva'] ?? '');
    $repetida = (string) ($_POST['repetida'] ?? '');
    if (!password_verify($actual, $usuario['hash'])) {
        $errores['actual'] = 'La contraseña actual no es correcta.';
    }
    if ($problema = problema_contrasena($nueva)) {
        $errores['nueva'] = $problema;
    } elseif ($nueva !== $repetida) {
        $errores['repetida'] = 'Las dos contraseñas nuevas no coinciden.';
    }
    if (!$errores) {
        con_candado('usuarios', function () use ($usuario, $nueva) {
            $usuarios = usuarios();
            $usuarios[$usuario['usuario']]['hash'] = password_hash($nueva, PASSWORD_DEFAULT);
            guardar_usuarios($usuarios);
        });
        session_regenerate_id(true);
        registrar('Cambió su contraseña');
        avisar('Tu contraseña se cambió.');
        redirigir('cuenta.php');
    }
}

encabezado('Mi cuenta', 'cuenta', $usuario);
?>
<h1>Mi cuenta</h1>
<dl class="datos">
  <div><dt>Nombre</dt><dd><?= e($usuario['nombre']) ?></dd></div>
  <div><dt>Usuario</dt><dd><?= e($usuario['usuario']) ?></dd></div>
  <div><dt>Permisos</dt><dd><?= e(ROLES[$usuario['rol']] ?? $usuario['rol']) ?></dd></div>
</dl>

<section class="seccion">
  <h2>Cambiar contraseña</h2>
  <form method="post" class="formulario formulario--angosto" novalidate>
    <?= campo_csrf() ?>
    <label class="campo">Contraseña actual
      <input name="actual" type="password" autocomplete="current-password" required>
      <?php if (isset($errores['actual'])): ?><span class="campo__error"><?= e($errores['actual']) ?></span><?php endif; ?>
    </label>
    <label class="campo">Contraseña nueva
      <input name="nueva" type="password" autocomplete="new-password" minlength="<?= LARGO_MINIMO_CONTRASENA ?>" required>
      <span class="campo__ayuda">Al menos <?= LARGO_MINIMO_CONTRASENA ?> caracteres. Una frase fácil de recordar funciona bien.</span>
      <?php if (isset($errores['nueva'])): ?><span class="campo__error"><?= e($errores['nueva']) ?></span><?php endif; ?>
    </label>
    <label class="campo">Repite la contraseña nueva
      <input name="repetida" type="password" autocomplete="new-password" required>
      <?php if (isset($errores['repetida'])): ?><span class="campo__error"><?= e($errores['repetida']) ?></span><?php endif; ?>
    </label>
    <button class="boton boton--principal" type="submit">Cambiar contraseña</button>
  </form>
</section>
<?php
pie();
