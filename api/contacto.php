<?php
// Recibe el formulario de la página de Contacto y lo envía por correo a CAPDES.
// Funciona en el hosting de Hostinger (PHP con la función mail()). Responde en JSON;
// si algo falla, js/contacto.js abre el correo de la persona con el mensaje listo.

header('Content-Type: application/json; charset=utf-8');

function responder($codigo, $datos)
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['ok' => false, 'error' => 'Método no permitido.']);
}

// Campo trampa: las personas no lo ven; si viene lleno es un robot. Se responde "ok" sin enviar nada.
if (!empty($_POST['sitio'])) {
    responder(200, ['ok' => true]);
}

$contacto = json_decode((string) @file_get_contents(__DIR__ . '/../datos/contacto.json'), true);
$destino = $contacto['correo'] ?? 'contacto@capdes.org';
$motivos = [];
foreach ($contacto['motivos'] ?? [] as $m) {
    $motivos[$m['clave']] = $m['texto'];
}

// Texto de una sola línea (sin saltos, que podrían usarse para alterar el correo) y con largo máximo.
function linea($valor, $maximo)
{
    $valor = trim(preg_replace('/[\r\n\t]+/', ' ', (string) $valor));
    return function_exists('mb_substr') ? mb_substr($valor, 0, $maximo) : substr($valor, 0, $maximo);
}

$nombre = linea($_POST['nombre'] ?? '', 80);
$apellidos = linea($_POST['apellidos'] ?? '', 80);
$correo = linea($_POST['correo'] ?? '', 120);
$telefono = linea($_POST['telefono'] ?? '', 30);
$motivo = linea($_POST['motivo'] ?? '', 40);
$mensaje = trim((string) ($_POST['mensaje'] ?? ''));
$mensaje = function_exists('mb_substr') ? mb_substr($mensaje, 0, 4000) : substr($mensaje, 0, 4000);

$errores = [];
if ($nombre === '') {
    $errores[] = 'nombre';
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'correo';
}
if (!isset($motivos[$motivo])) {
    $errores[] = 'motivo';
}
if ($mensaje === '') {
    $errores[] = 'mensaje';
}
if ($errores) {
    responder(422, ['ok' => false, 'error' => 'Faltan datos o no son válidos.', 'campos' => $errores]);
}

// Límite sencillo: un mensaje cada 30 segundos por dirección IP.
$marca = sys_get_temp_dir() . '/capdes_contacto_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
if (is_file($marca) && time() - filemtime($marca) < 30) {
    responder(429, ['ok' => false, 'error' => 'Espera unos segundos antes de enviar otro mensaje.']);
}
@touch($marca);

$asunto = 'Nuevo mensaje desde capdes.org: ' . $motivos[$motivo];
$cuerpo = "Nombre: $nombre $apellidos\n"
    . "Correo: $correo\n"
    . 'Teléfono: ' . ($telefono !== '' ? $telefono : '—') . "\n"
    . 'Motivo: ' . $motivos[$motivo] . "\n\n"
    . "Mensaje:\n$mensaje\n";

$cabeceras = implode("\r\n", [
    'From: Sitio web de CAPDES <no-responder@capdes.org>',
    'Reply-To: ' . $correo,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
]);

$enviado = mail($destino, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, $cabeceras);

if (!$enviado) {
    responder(500, ['ok' => false, 'error' => 'No se pudo enviar el mensaje.']);
}
responder(200, ['ok' => true]);
