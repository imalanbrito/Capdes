<?php
/**
 * Crea (o reemplaza la contraseña de) una cuenta del panel de administrador.
 * Se usa desde la terminal, en la carpeta del proyecto:
 *
 *     php herramientas/crear-usuario.php
 *
 * Así la primera cuenta de administrador no depende de un formulario público.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['SCRIPT_NAME'] = '/admin/index.php';
ob_start();
require __DIR__ . '/../admin/inc/nucleo.php';
ob_end_clean();

use function Capdes\Admin\{usuarios, guardar_usuarios, usuario_valido, problema_contrasena};
use const Capdes\Admin\ROLES;

function preguntar(string $texto, bool $oculto = false): string
{
    echo $texto;
    if ($oculto && DIRECTORY_SEPARATOR === '/') {
        system('stty -echo');
        $respuesta = trim((string) fgets(STDIN));
        system('stty echo');
        echo PHP_EOL;
        return $respuesta;
    }
    return trim((string) fgets(STDIN));
}

echo "Nueva cuenta del panel de CAPDES\n\n";
do {
    $usuario = mb_strtolower(preguntar('Usuario (p. ej. alan.perez): '));
} while (!usuario_valido($usuario) && print("  Usa de 3 a 30 letras minúsculas, números, puntos o guiones.\n"));

$usuarios = usuarios();
if (isset($usuarios[$usuario])) {
    echo "  Ya existe; se le asignará una contraseña nueva.\n";
    $nombre = $usuarios[$usuario]['nombre'];
    $rol = $usuarios[$usuario]['rol'];
} else {
    do {
        $nombre = preguntar('Nombre completo: ');
    } while ($nombre === '');
    $rol = preguntar('¿Administrador? (s/n) [s]: ');
    $rol = in_array(mb_strtolower($rol), ['', 's', 'si', 'sí'], true) ? 'administrador' : 'editor';
}

do {
    $contrasena = preguntar('Contraseña: ', true);
    $problema = problema_contrasena($contrasena);
    if ($problema) {
        echo "  $problema\n";
        continue;
    }
    if (preguntar('Repite la contraseña: ', true) !== $contrasena) {
        echo "  No coinciden.\n";
        $problema = 'no coinciden';
    }
} while ($problema);

$usuarios[$usuario] = [
    'nombre' => $nombre,
    'rol' => $rol,
    'hash' => password_hash($contrasena, PASSWORD_DEFAULT),
    'creado' => $usuarios[$usuario]['creado'] ?? date('c'),
] + ($usuarios[$usuario] ?? []);
guardar_usuarios($usuarios);

echo "\n✓ Listo: $nombre ($usuario) · " . ROLES[$rol] . "\n";
echo "  Se guardó en privado/usuarios.php. Súbelo junto con el sitio.\n";
