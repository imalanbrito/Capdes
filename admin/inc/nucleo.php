<?php
/**
 * Núcleo del panel de administrador: sesión, seguridad, usuarios, respaldos y publicación.
 * Cada página del panel empieza con:  require __DIR__ . '/inc/nucleo.php';
 */

namespace Capdes\Admin;

use RuntimeException;

const RAIZ = __DIR__ . '/../..';
const PRIVADO = RAIZ . '/privado';

const MINUTOS_INACTIVIDAD = 30;     // la sesión se cierra tras este tiempo sin usar el panel
const HORAS_SESION = 8;             // y, en cualquier caso, después de estas horas
const INTENTOS_PERMITIDOS = 5;      // contraseñas incorrectas antes del bloqueo
const MINUTOS_BLOQUEO = 15;
const RESPALDOS_POR_ARCHIVO = 50;
const LARGO_MINIMO_CONTRASENA = 10;

const ROLES = [
    'administrador' => 'Administrador',
    'editor' => 'Editor',
];

require_once RAIZ . '/herramientas/generar.php';
require_once __DIR__ . '/plantilla.php';

date_default_timezone_set('America/Mexico_City');

// ───────────── Encabezados de seguridad ─────────────

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self'; "
    . "script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
header('Cache-Control: no-store');

// ───────────── Sesión ─────────────

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('capdes_panel');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/') . '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Strict',
]);
ini_set('session.use_strict_mode', '1');
session_start();

// ───────────── Utilidades ─────────────

/** Escapa texto para HTML. */
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirigir(string $destino): void
{
    header('Location: ' . $destino);
    exit;
}

/** Mensaje que se muestra una sola vez en la siguiente página ("Guardado ✓"). */
function avisar(string $texto, string $tipo = 'exito'): void
{
    $_SESSION['avisos'][] = ['texto' => $texto, 'tipo' => $tipo];
}

function tomar_avisos(): array
{
    $avisos = $_SESSION['avisos'] ?? [];
    unset($_SESSION['avisos']);
    return $avisos;
}

function es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function ip_cliente(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
}

// ───────────── Protección contra formularios falsos (CSRF) ─────────────

function token_csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(token_csrf()) . '">';
}

/** Todo formulario que cambia algo debe pasar por aquí. */
function verificar_csrf(): void
{
    $enviado = (string) ($_POST['csrf'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $enviado)) {
        http_response_code(400);
        exit('La página caducó. Vuelve atrás, recarga la página e inténtalo de nuevo.');
    }
}

// ───────────── Archivos privados ─────────────
// Los datos privados (usuarios, intentos, bitácora) viven en privado/, que el servidor no
// entrega (privado/.htaccess). Además son archivos .php que empiezan con un "exit": aunque
// el .htaccess fallara, quien los abriera desde el navegador no vería nada.

const CANDADO_PHP = "<?php http_response_code(404); exit; ?>\n";

function ruta_privada(string $nombre): string
{
    if (!is_dir(PRIVADO)) {
        mkdir(PRIVADO, 0750, true);
    }
    return PRIVADO . '/' . $nombre;
}

function leer_privado(string $nombre, $defecto = [])
{
    $ruta = ruta_privada($nombre);
    if (!is_file($ruta)) {
        return $defecto;
    }
    $texto = (string) file_get_contents($ruta);
    if (strncmp($texto, CANDADO_PHP, strlen(CANDADO_PHP)) === 0) {
        $texto = substr($texto, strlen(CANDADO_PHP));
    }
    $datos = json_decode($texto, true);
    return is_array($datos) ? $datos : $defecto;
}

function escribir_archivo(string $ruta, string $contenido): void
{
    $temporal = $ruta . '.tmp-' . bin2hex(random_bytes(4));
    if (file_put_contents($temporal, $contenido, LOCK_EX) === false || !rename($temporal, $ruta)) {
        @unlink($temporal);
        throw new RuntimeException('No se pudo guardar ' . basename($ruta) . '. Revisa los permisos de la carpeta.');
    }
}

function escribir_privado(string $nombre, $datos): void
{
    escribir_archivo(ruta_privada($nombre),
        CANDADO_PHP . json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/** Ejecuta $tarea con un candado, para que dos personas no guarden lo mismo a la vez. */
function con_candado(string $nombre, callable $tarea)
{
    $candado = fopen(ruta_privada($nombre . '.lock'), 'c');
    flock($candado, LOCK_EX);
    try {
        return $tarea();
    } finally {
        flock($candado, LOCK_UN);
        fclose($candado);
    }
}

// ───────────── Bitácora ─────────────

function registrar(string $accion): void
{
    con_candado('bitacora', function () use ($accion) {
        $bitacora = leer_privado('bitacora.php');
        $bitacora[] = [
            'fecha' => date('c'),
            'usuario' => $_SESSION['usuario'] ?? '—',
            'accion' => $accion,
        ];
        escribir_privado('bitacora.php', array_slice($bitacora, -500));
    });
}

function bitacora(int $cuantos = 10): array
{
    return array_reverse(array_slice(leer_privado('bitacora.php'), -$cuantos));
}

// ───────────── Usuarios ─────────────

function usuarios(): array
{
    return leer_privado('usuarios.php');
}

function guardar_usuarios(array $usuarios): void
{
    ksort($usuarios);
    escribir_privado('usuarios.php', $usuarios);
}

function usuario_valido(string $usuario): bool
{
    return (bool) preg_match('/^[a-z0-9._-]{3,30}$/', $usuario);
}

/** Devuelve el motivo si la contraseña no sirve, o null si está bien. */
function problema_contrasena(string $contrasena): ?string
{
    if (mb_strlen($contrasena) < LARGO_MINIMO_CONTRASENA) {
        return 'La contraseña debe tener al menos ' . LARGO_MINIMO_CONTRASENA . ' caracteres.';
    }
    if (mb_strlen($contrasena) > 200) {
        return 'La contraseña es demasiado larga.';
    }
    return null;
}

function usuario_actual(): ?array
{
    $usuario = $_SESSION['usuario'] ?? null;
    if ($usuario === null) {
        return null;
    }
    $usuarios = usuarios();
    return isset($usuarios[$usuario]) ? ['usuario' => $usuario] + $usuarios[$usuario] : null;
}

function es_administrador(): bool
{
    return (usuario_actual()['rol'] ?? '') === 'administrador';
}

/** Cierra la sesión si caducó o si el usuario ya no existe; si no hay sesión, manda a entrar. */
function requerir_sesion(?string $rol = null): array
{
    $ahora = time();
    $usuario = usuario_actual();
    $caducada = isset($_SESSION['ultimo'])
        && ($ahora - $_SESSION['ultimo'] > MINUTOS_INACTIVIDAD * 60 || $ahora - $_SESSION['inicio'] > HORAS_SESION * 3600);
    if ($usuario === null || $caducada) {
        $habia_sesion = isset($_SESSION['usuario']);
        cerrar_sesion();
        session_start();
        if ($habia_sesion) {
            avisar('Tu sesión se cerró por inactividad. Vuelve a entrar.', 'info');
        }
        redirigir('entrar.php');
    }
    $_SESSION['ultimo'] = $ahora;
    if ($rol !== null && $usuario['rol'] !== $rol) {
        http_response_code(403);
        avisar('Esa sección solo la puede usar un administrador.', 'error');
        redirigir('index.php');
    }
    return $usuario;
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600] + array_intersect_key($p, array_flip(['path', 'domain', 'secure', 'httponly', 'samesite'])));
    session_destroy();
}

// ───────────── Intentos de entrada (bloqueo temporal) ─────────────

function clave_intentos(): string
{
    return hash('sha256', ip_cliente());
}

/** Minutos que faltan para poder intentar otra vez (0 si no hay bloqueo). */
function minutos_bloqueo(): int
{
    $registro = leer_privado('intentos.php')[clave_intentos()] ?? null;
    if (!$registro || ($registro['hasta'] ?? 0) <= time()) {
        return 0;
    }
    return (int) ceil(($registro['hasta'] - time()) / 60);
}

function anotar_fallo(): void
{
    con_candado('intentos', function () {
        $intentos = leer_privado('intentos.php');
        $ahora = time();
        // Olvida registros viejos.
        $intentos = array_filter($intentos, fn($r) => ($r['ultimo'] ?? 0) > $ahora - 86400);
        $r = $intentos[clave_intentos()] ?? ['fallos' => 0, 'hasta' => 0];
        if (($r['hasta'] ?? 0) && $r['hasta'] <= $ahora) {
            $r = ['fallos' => 0, 'hasta' => 0];
        }
        $r['fallos']++;
        $r['ultimo'] = $ahora;
        if ($r['fallos'] >= INTENTOS_PERMITIDOS) {
            $r['hasta'] = $ahora + MINUTOS_BLOQUEO * 60;
        }
        $intentos[clave_intentos()] = $r;
        escribir_privado('intentos.php', $intentos);
    });
}

function limpiar_fallos(): void
{
    con_candado('intentos', function () {
        $intentos = leer_privado('intentos.php');
        unset($intentos[clave_intentos()]);
        escribir_privado('intentos.php', $intentos);
    });
}

// ───────────── Datos del sitio y publicación ─────────────

function leer_dato(string $nombre): array
{
    return \Capdes\Generador\leer_datos($nombre);
}

/**
 * Cambia un archivo de datos/ y vuelve a generar el sitio.
 * Antes guarda un respaldo; si la generación falla, deja el archivo como estaba.
 * $cambiar recibe los datos actuales y devuelve los nuevos.
 */
function guardar_y_publicar(string $nombre, callable $cambiar, string $accion): array
{
    return con_candado('publicar', function () use ($nombre, $cambiar, $accion) {
        $ruta = RAIZ . '/datos/' . $nombre;
        $anterior = (string) file_get_contents($ruta);
        $nuevos = $cambiar(leer_dato($nombre));
        respaldar($nombre, $anterior);
        escribir_archivo($ruta, json_encode($nuevos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        try {
            $registro = \Capdes\Generador\generar_todo();
        } catch (\Throwable $error) {
            escribir_archivo($ruta, $anterior);
            \Capdes\Generador\generar_todo();
            throw new RuntimeException('No se publicó el cambio: ' . $error->getMessage());
        }
        registrar($accion);
        return $registro;
    });
}

function respaldar(string $nombre, string $contenido): void
{
    $carpeta = ruta_privada('respaldos/' . pathinfo($nombre, PATHINFO_FILENAME));
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0750, true);
    }
    $usuario = preg_replace('/[^a-z0-9._-]/', '', $_SESSION['usuario'] ?? 'sistema');
    // Fecha con microsegundos: dos guardados en el mismo segundo no se pisan.
    $momento = \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true)))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    file_put_contents($carpeta . '/' . $momento->format('Y-m-d_H-i-s-u') . '_' . $usuario . '.json', $contenido);
    $todos = glob($carpeta . '/*.json') ?: [];
    sort($todos);
    foreach (array_slice($todos, 0, max(0, count($todos) - RESPALDOS_POR_ARCHIVO)) as $viejo) {
        unlink($viejo);
    }
}

// ───────────── Fotos ─────────────

/**
 * Valida una foto subida, la gira según la cámara, la reduce a $ancho_maximo y la guarda
 * como JPG en $carpeta (relativa a la raíz del sitio). Devuelve la ruta para los datos.
 */
function guardar_foto(array $archivo, string $carpeta, string $nombre_base, int $ancho_maximo = 1600): string
{
    $errores = [
        UPLOAD_ERR_INI_SIZE => 'La foto pesa demasiado para el servidor.',
        UPLOAD_ERR_FORM_SIZE => 'La foto pesa demasiado.',
        UPLOAD_ERR_PARTIAL => 'La foto no terminó de subirse. Inténtalo de nuevo.',
        UPLOAD_ERR_NO_FILE => 'No elegiste ninguna foto.',
    ];
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException($errores[$archivo['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'No se pudo subir la foto.');
    }
    if (!is_uploaded_file($archivo['tmp_name']) && PHP_SAPI !== 'cli') {
        throw new RuntimeException('No se pudo subir la foto.');
    }
    if ($archivo['size'] > 20 * 1024 * 1024) {
        throw new RuntimeException('La foto pesa más de 20 MB. Elige una más ligera.');
    }
    $info = @getimagesize($archivo['tmp_name']);
    $tipos = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    if (!$info || !isset($tipos[$info[2]])) {
        throw new RuntimeException('El archivo no es una foto válida. Usa JPG, PNG o WEBP.');
    }
    if ($info[0] * $info[1] > 50_000_000) {
        throw new RuntimeException('La foto tiene demasiada resolución.');
    }
    @ini_set('memory_limit', '512M');
    $original = @$tipos[$info[2]]($archivo['tmp_name']);
    if (!$original) {
        throw new RuntimeException('No se pudo leer la foto. Prueba con otra o guárdala de nuevo como JPG.');
    }

    // Las fotos de celular traen la orientación aparte; se aplica para que no salgan de lado.
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $orientacion = @exif_read_data($archivo['tmp_name'])['Orientation'] ?? 1;
        $giro = [3 => 180, 6 => -90, 8 => 90][$orientacion] ?? 0;
        if ($giro) {
            $original = imagerotate($original, $giro, 0);
        }
    }

    $ancho = imagesx($original);
    $alto = imagesy($original);
    $escala = min(1, $ancho_maximo / $ancho);
    $nuevo_ancho = (int) round($ancho * $escala);
    $nuevo_alto = (int) round($alto * $escala);
    $final = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
    // Fondo blanco por si la foto tiene transparencias (PNG).
    imagefill($final, 0, 0, imagecolorallocate($final, 255, 255, 255));
    imagecopyresampled($final, $original, 0, 0, 0, 0, $nuevo_ancho, $nuevo_alto, $ancho, $alto);
    imageinterlace($final, true);

    $nombre_base = \Capdes\Generador\slug($nombre_base) ?: 'foto';
    $nombre_base = substr($nombre_base, 0, 60);
    $relativa = trim($carpeta, '/') . '/' . date('Ymd') . '-' . $nombre_base . '-' . bin2hex(random_bytes(3)) . '.jpg';
    $destino = RAIZ . '/' . $relativa;
    if (!is_dir(dirname($destino))) {
        mkdir(dirname($destino), 0755, true);
    }
    if (!imagejpeg($final, $destino, 82)) {
        throw new RuntimeException('No se pudo guardar la foto en el servidor.');
    }
    return $relativa;
}
