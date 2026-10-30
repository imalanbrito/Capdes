<?php
namespace Capdes\Admin;

require __DIR__ . '/inc/nucleo.php';

if (usuario_actual()) {
    redirigir('index.php');
}

$error = '';
$usuario = '';
$bloqueo = minutos_bloqueo();

if (es_post()) {
    verificar_csrf();
    $usuario = mb_strtolower(trim((string) ($_POST['usuario'] ?? '')));
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    if ($bloqueo) {
        $error = "Demasiados intentos. Espera $bloqueo " . ($bloqueo === 1 ? 'minuto' : 'minutos') . ' e inténtalo de nuevo.';
    } else {
        $usuarios = usuarios();
        $datos = $usuarios[$usuario] ?? null;
        // Se verifica contra un hash aunque el usuario no exista, para no revelar qué usuarios hay.
        $hash = $datos['hash'] ?? password_hash('relleno', PASSWORD_DEFAULT);
        if ($datos && password_verify($contrasena, $hash)) {
            limpiar_fallos();
            session_regenerate_id(true);
            $_SESSION = ['usuario' => $usuario, 'inicio' => time(), 'ultimo' => time()];
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                $usuarios[$usuario]['hash'] = password_hash($contrasena, PASSWORD_DEFAULT);
            }
            $usuarios[$usuario]['ultimo_acceso'] = date('c');
            guardar_usuarios($usuarios);
            registrar('Entró al panel');
            redirigir('index.php');
        }
        anotar_fallo();
        usleep(400000);
        $bloqueo = minutos_bloqueo();
        $error = $bloqueo
            ? "Demasiados intentos. Por seguridad, espera $bloqueo minutos e inténtalo de nuevo."
            : 'El usuario o la contraseña no son correctos.';
    }
}

$sin_usuarios = !usuarios();

encabezado('Entrar');
?>
<section class="entrar">
  <img class="entrar__logo" src="../img/favicon-192.png" alt="" width="72" height="72">
  <h1>Panel de CAPDES</h1>
  <?php if ($sin_usuarios): ?>
    <p class="aviso aviso--info">Todavía no hay cuentas. Crea la primera desde la computadora del proyecto con
      <code>php herramientas/crear-usuario.php</code> y sube el sitio de nuevo.</p>
  <?php endif; ?>
  <?php if ($error): ?><p class="aviso aviso--error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="formulario" novalidate>
    <?= campo_csrf() ?>
    <label class="campo">Usuario
      <input name="usuario" autocomplete="username" autocapitalize="none" spellcheck="false" required value="<?= e($usuario) ?>" autofocus>
    </label>
    <label class="campo">Contraseña
      <input name="contrasena" type="password" autocomplete="current-password" required>
    </label>
    <button class="boton boton--principal boton--ancho" type="submit">Entrar</button>
  </form>
</section>
<?php
pie();
