<?php
namespace Capdes\Admin;

require __DIR__ . '/inc/nucleo.php';

$yo = requerir_sesion('administrador');
$errores = [];
$nuevo = ['usuario' => '', 'nombre' => '', 'rol' => 'editor'];

function administradores(array $usuarios): int
{
    return count(array_filter($usuarios, fn($u) => $u['rol'] === 'administrador'));
}

if (es_post()) {
    verificar_csrf();
    $accion = (string) ($_POST['accion'] ?? '');
    $objetivo = (string) ($_POST['usuario'] ?? '');

    if ($accion === 'crear') {
        $nuevo = [
            'usuario' => mb_strtolower(trim((string) ($_POST['usuario'] ?? ''))),
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'rol' => (string) ($_POST['rol'] ?? 'editor'),
        ];
        $contrasena = (string) ($_POST['contrasena'] ?? '');
        if (!usuario_valido($nuevo['usuario'])) {
            $errores['usuario'] = 'Usa de 3 a 30 letras minúsculas, números, puntos o guiones, sin espacios ni acentos.';
        } elseif (isset(usuarios()[$nuevo['usuario']])) {
            $errores['usuario'] = 'Ya existe una cuenta con ese usuario.';
        }
        if ($nuevo['nombre'] === '' || mb_strlen($nuevo['nombre']) > 80) {
            $errores['nombre'] = 'Escribe el nombre de la persona.';
        }
        if (!isset(ROLES[$nuevo['rol']])) {
            $errores['rol'] = 'Elige los permisos.';
        }
        if ($problema = problema_contrasena($contrasena)) {
            $errores['contrasena'] = $problema;
        }
        if (!$errores) {
            con_candado('usuarios', function () use ($nuevo, $contrasena) {
                $usuarios = usuarios();
                $usuarios[$nuevo['usuario']] = [
                    'nombre' => $nuevo['nombre'],
                    'rol' => $nuevo['rol'],
                    'hash' => password_hash($contrasena, PASSWORD_DEFAULT),
                    'creado' => date('c'),
                ];
                guardar_usuarios($usuarios);
            });
            registrar('Creó la cuenta de ' . $nuevo['nombre'] . ' (' . ROLES[$nuevo['rol']] . ')');
            avisar('Se creó la cuenta de ' . $nuevo['nombre'] . '. Compártele su usuario y contraseña en persona o por un medio privado.');
            redirigir('usuarios.php');
        }
    } else {
        $resultado = con_candado('usuarios', function () use ($accion, $objetivo, $yo) {
            $usuarios = usuarios();
            if (!isset($usuarios[$objetivo])) {
                return ['error', 'Esa cuenta ya no existe.'];
            }
            $nombre = $usuarios[$objetivo]['nombre'];
            switch ($accion) {
                case 'rol':
                    $rol = (string) ($_POST['rol'] ?? '');
                    if (!isset(ROLES[$rol])) {
                        return ['error', 'Permisos no válidos.'];
                    }
                    if ($usuarios[$objetivo]['rol'] === 'administrador' && $rol !== 'administrador' && administradores($usuarios) === 1) {
                        return ['error', 'Debe quedar al menos un administrador.'];
                    }
                    $usuarios[$objetivo]['rol'] = $rol;
                    guardar_usuarios($usuarios);
                    return ['exito', "Ahora $nombre tiene permisos de " . ROLES[$rol] . '.', "Cambió los permisos de $nombre a " . ROLES[$rol]];
                case 'contrasena':
                    $contrasena = (string) ($_POST['contrasena'] ?? '');
                    if ($problema = problema_contrasena($contrasena)) {
                        return ['error', $problema];
                    }
                    $usuarios[$objetivo]['hash'] = password_hash($contrasena, PASSWORD_DEFAULT);
                    guardar_usuarios($usuarios);
                    return ['exito', "Se asignó una contraseña nueva a $nombre.", "Asignó una contraseña nueva a $nombre"];
                case 'borrar':
                    if ($objetivo === $yo['usuario']) {
                        return ['error', 'No puedes borrar tu propia cuenta.'];
                    }
                    if ($usuarios[$objetivo]['rol'] === 'administrador' && administradores($usuarios) === 1) {
                        return ['error', 'Debe quedar al menos un administrador.'];
                    }
                    unset($usuarios[$objetivo]);
                    guardar_usuarios($usuarios);
                    return ['exito', "Se borró la cuenta de $nombre.", "Borró la cuenta de $nombre"];
            }
            return ['error', 'Acción no válida.'];
        });
        if (isset($resultado[2])) {
            registrar($resultado[2]);
        }
        avisar($resultado[1], $resultado[0]);
        redirigir('usuarios.php');
    }
}

$usuarios = usuarios();
encabezado('Usuarios', 'usuarios', $yo);
?>
<h1>Usuarios</h1>
<p class="introduccion">Los <strong>editores</strong> pueden cambiar el contenido del sitio. Los <strong>administradores</strong>, además, crean y quitan cuentas.</p>

<div class="tabla-envoltura">
<table class="tabla">
  <thead><tr><th>Nombre</th><th>Usuario</th><th>Permisos</th><th>Último acceso</th><th><span class="solo-lectores">Acciones</span></th></tr></thead>
  <tbody>
  <?php foreach ($usuarios as $clave => $u): ?>
    <tr>
      <td><?= e($u['nombre']) ?><?= $clave === $yo['usuario'] ? ' <span class="etiqueta">Tú</span>' : '' ?></td>
      <td><?= e($clave) ?></td>
      <td>
        <form method="post" class="en-linea" data-enviar-al-cambiar>
          <?= campo_csrf() ?><input type="hidden" name="accion" value="rol"><input type="hidden" name="usuario" value="<?= e($clave) ?>">
          <label class="solo-lectores" for="rol-<?= e($clave) ?>">Permisos de <?= e($u['nombre']) ?></label>
          <select id="rol-<?= e($clave) ?>" name="rol">
            <?php foreach (ROLES as $valor => $texto): ?>
              <option value="<?= $valor ?>"<?= $u['rol'] === $valor ? ' selected' : '' ?>><?= $texto ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="boton boton--chico" data-sin-js>Guardar</button>
        </form>
      </td>
      <td><?= isset($u['ultimo_acceso']) ? e(fecha_hora($u['ultimo_acceso'])) : '<span class="tenue">Nunca</span>' ?></td>
      <td class="tabla__acciones">
        <details class="desplegable">
          <summary class="boton boton--chico boton--secundario">Nueva contraseña</summary>
          <form method="post" class="desplegable__panel">
            <?= campo_csrf() ?><input type="hidden" name="accion" value="contrasena"><input type="hidden" name="usuario" value="<?= e($clave) ?>">
            <label class="campo">Contraseña nueva para <?= e($u['nombre']) ?>
              <input name="contrasena" type="text" autocomplete="off" minlength="<?= LARGO_MINIMO_CONTRASENA ?>" required>
            </label>
            <button type="submit" class="boton boton--chico boton--principal">Asignar</button>
          </form>
        </details>
        <?php if ($clave !== $yo['usuario']): ?>
          <form method="post" class="en-linea" data-confirmar="¿Borrar la cuenta de <?= e($u['nombre']) ?>? Ya no podrá entrar al panel.">
            <?= campo_csrf() ?><input type="hidden" name="accion" value="borrar"><input type="hidden" name="usuario" value="<?= e($clave) ?>">
            <button type="submit" class="boton boton--chico boton--peligro">Borrar</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<section class="seccion">
  <h2>Agregar una cuenta</h2>
  <form method="post" class="formulario formulario--angosto" novalidate>
    <?= campo_csrf() ?><input type="hidden" name="accion" value="crear">
    <label class="campo">Nombre de la persona
      <input name="nombre" required maxlength="80" value="<?= e($nuevo['nombre']) ?>">
      <?php if (isset($errores['nombre'])): ?><span class="campo__error"><?= e($errores['nombre']) ?></span><?php endif; ?>
    </label>
    <label class="campo">Usuario para entrar
      <input name="usuario" required autocapitalize="none" spellcheck="false" maxlength="30" value="<?= e($nuevo['usuario']) ?>" placeholder="p. ej. maria.lopez">
      <span class="campo__ayuda">Minúsculas, números, puntos o guiones.</span>
      <?php if (isset($errores['usuario'])): ?><span class="campo__error"><?= e($errores['usuario']) ?></span><?php endif; ?>
    </label>
    <label class="campo">Permisos
      <select name="rol">
        <?php foreach (ROLES as $valor => $texto): ?>
          <option value="<?= $valor ?>"<?= $nuevo['rol'] === $valor ? ' selected' : '' ?>><?= $texto ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="campo">Contraseña inicial
      <input name="contrasena" type="text" autocomplete="off" minlength="<?= LARGO_MINIMO_CONTRASENA ?>" required>
      <span class="campo__ayuda">Al menos <?= LARGO_MINIMO_CONTRASENA ?> caracteres. La persona puede cambiarla después en "Mi cuenta".</span>
      <?php if (isset($errores['contrasena'])): ?><span class="campo__error"><?= e($errores['contrasena']) ?></span><?php endif; ?>
    </label>
    <button class="boton boton--principal" type="submit">Crear cuenta</button>
  </form>
</section>
<?php
pie();
