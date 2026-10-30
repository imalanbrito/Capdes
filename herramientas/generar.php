<?php
/**
 * Genera las partes del sitio que dependen de los archivos de datos/ y las inserta en las páginas.
 *
 * Lo usan dos:
 * - el panel de administrador (admin/), que lo corre al presionar "Publicar":
 *       require __DIR__ . '/../herramientas/generar.php';
 *       $registro = \Capdes\Generador\generar_todo();
 * - quien edita el sitio a mano, desde la carpeta del proyecto:
 *       php herramientas/generar.php
 *
 * Arma el menú, el pie de página con el contador de visitas, el inicio (carrusel, impacto,
 * eventos, donativos, aliados), el blog (listado y una página por artículo), Programas,
 * Quiénes somos, Marco institucional, Contacto y el Aviso de privacidad. También pone a los
 * enlaces de CSS/JS propios un ?v= según su contenido, para que el navegador no use una copia
 * vieja en caché.
 *
 * Cada bloque generado va entre marcas <!-- nombre:inicio --> y <!-- nombre:fin --> dentro de
 * la página; lo que esté fuera de las marcas no se toca.
 */

namespace Capdes\Generador;

use RuntimeException;

const SITIO = 'https://capdes.org/';

// Los botones "Donar" llevan a la sección de donativos del inicio (PayPal y GoFundMe,
// configurables en datos/donar.json).
const DONAR_URL = 'index.html#donar';

// [texto, enlace, [[texto, enlace], ...]]. Contacto siempre al final.
const MENU = [
    ['Inicio', 'index.html', [
        ['Nuestro blog', 'blog.html'],
        ['Eventos y actividades', 'index.html#eventos'],
    ]],
    ['Quiénes somos', 'quienes-somos.html', [
        ['Acerca de nosotros', 'quienes-somos.html#acerca'],
        ['Misión, visión y valores', 'quienes-somos.html#mision-vision-valores'],
        ['Noticias y eventos', 'blog.html'],
    ]],
    ['Programas', 'programas.html', [
        ['Nuestros programas', 'programas.html#programas'],
        ['Jornadas realizadas', 'programas.html#jornadas'],
        ['Agenda 2030', 'programas.html#ods'],
    ]],
    ['Marco institucional', 'marco-institucional.html', [
        ['Identidad institucional', 'marco-institucional.html#identidad'],
        ['Planeación estratégica', 'marco-institucional.html#planeacion'],
        ['Marco normativo y políticas', 'marco-institucional.html#marco-normativo'],
        ['Informes y rendición de cuentas', 'marco-institucional.html#informes'],
    ]],
    ['Contacto', 'contacto.html', [
        ['Escríbenos', 'contacto.html#formulario'],
        ['Voluntariado y servicio social', 'contacto.html?motivo=voluntariado#formulario'],
        ['Ubicación', 'contacto.html#ubicacion'],
    ]],
];

// Pestaña principal que se marca como activa en cada página.
const ACTIVA = [
    'index.html' => 'Inicio',
    'blog.html' => 'Inicio',
    'quienes-somos.html' => 'Quiénes somos',
    'programas.html' => 'Programas',
    'marco-institucional.html' => 'Marco institucional',
    'contacto.html' => 'Contacto',
];

// página => [[ancla legible, id de la sección de Hostinger]]
const ANCLAS = [
    'index.html' => [['eventos', 'zXDtLi'], ['donar', 'zwZ-vA'], ['aliados', 'zzHakD']],
];

// Scripts propios que solo carga una página (además de menu.js, contador.js y pie.js).
const JS_POR_PAGINA = [
    'index.html' => ['inicio'],
    'blog.html' => ['inicio', 'blog'],
    'programas.html' => ['programas'],
    'marco-institucional.html' => ['marco'],
    'contacto.html' => ['contacto'],
];

const LOGO = 'https://assets.zyrosite.com/cdn-cgi/image/format=auto,w=400,fit=crop/'
    . 'AQEyaZVx1MiRWvDV/logocapdesa.c-yfb19wu0yFQLv0Rh.png';

// Marca de las páginas que se generan completas (artículos del blog, aviso de privacidad):
// solo esas se pueden borrar cuando ya no están en los datos.
const GENERADOR = '<meta name="generator" content="herramientas/generar.php">';
const GENERADORES_ANTERIORES = ['<meta name="generator" content="herramientas/menu.py">'];

const FUENTES = '<link href="https://cdn.zyrosite.com/u1/google-fonts/font-faces?family=Outfit:wght@400;600&amp;'
    . 'family=Poppins:wght@400;500;700&amp;display=swap" rel="stylesheet" referrerpolicy="no-referrer">';

const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
    'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

// Los 17 Objetivos de Desarrollo Sostenible, con su color oficial.
const ODS = [
    ['Fin de la pobreza', '#e5243b'], ['Hambre cero', '#dda63a'], ['Salud y bienestar', '#4c9f38'],
    ['Educación de calidad', '#c5192d'], ['Igualdad de género', '#ff3a21'],
    ['Agua limpia y saneamiento', '#26bde2'], ['Energía asequible y no contaminante', '#fcc30b'],
    ['Trabajo decente y crecimiento económico', '#a21942'], ['Industria, innovación e infraestructura', '#fd6925'],
    ['Reducción de las desigualdades', '#dd1367'], ['Ciudades y comunidades sostenibles', '#fd9d24'],
    ['Producción y consumo responsables', '#bf8b2e'], ['Acción por el clima', '#3f7e44'],
    ['Vida submarina', '#0a97d9'], ['Vida de ecosistemas terrestres', '#56c02b'],
    ['Paz, justicia e instituciones sólidas', '#00689d'], ['Alianzas para lograr los objetivos', '#19486a'],
];

// ───────────── Íconos ─────────────

const ICONO_FLECHA = '<svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true">'
    . '<path d="M2.5 4.5 6 8l3.5-3.5" fill="none" stroke="currentColor" '
    . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

const ICONO_CORAZON = '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">'
    . '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 '
    . '7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" '
    . 'fill="currentColor"/></svg>';

const ICONO_FACEBOOK = '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" '
    . 'd="M24 12.0726C24 5.44354 18.629 0.0725708 12 0.0725708C5.37097 0.0725708 0 5.44354 0 '
    . '12.0726C0 18.0619 4.38823 23.0264 10.125 23.9274V15.5414H7.07661V12.0726H10.125V9.4287C10.125 '
    . '6.42144 11.9153 4.76031 14.6574 4.76031C15.9706 4.76031 17.3439 4.99451 17.3439 '
    . '4.99451V7.94612H15.8303C14.34 7.94612 13.875 8.87128 13.875 9.82015V12.0726H17.2031L16.6708 '
    . '15.5414H13.875V23.9274C19.6118 23.0264 24 18.0619 24 12.0726Z"/></svg>';

const ICONO_INSTAGRAM = '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" '
    . 'd="M12.0027 5.84808C8.59743 5.84808 5.85075 8.59477 5.85075 12C5.85075 15.4053 8.59743 '
    . '18.1519 12.0027 18.1519C15.4079 18.1519 18.1546 15.4053 18.1546 12C18.1546 8.59477 15.4079 '
    . '5.84808 12.0027 5.84808ZM12.0027 15.9996C9.80212 15.9996 8.00312 14.2059 8.00312 12C8.00312 '
    . '9.7941 9.79677 8.00046 12.0027 8.00046C14.2086 8.00046 16.0022 9.7941 16.0022 12C16.0022 '
    . '14.2059 14.2032 15.9996 12.0027 15.9996ZM19.8412 5.59644C19.8412 6.39421 19.1987 7.03135 '
    . '18.4062 7.03135C17.6085 7.03135 16.9713 6.38885 16.9713 5.59644C16.9713 4.80402 17.6138 '
    . '4.16153 18.4062 4.16153C19.1987 4.16153 19.8412 4.80402 19.8412 5.59644ZM23.9157 '
    . '7.05277C23.8247 5.13063 23.3856 3.42801 21.9775 2.02522C20.5747 0.622429 18.8721 0.183388 '
    . '16.9499 0.0870135C14.9689 -0.0254238 9.03112 -0.0254238 7.05008 0.0870135C5.1333 0.178034 '
    . '3.43068 0.617075 2.02253 2.01986C0.614389 3.42265 0.180703 5.12527 0.0843279 7.04742C-0.0281093 '
    . '9.02845 -0.0281093 14.9662 0.0843279 16.9472C0.175349 18.8694 0.614389 20.572 2.02253 '
    . '21.9748C3.43068 23.3776 5.12794 23.8166 7.05008 23.913C9.03112 24.0254 14.9689 24.0254 '
    . '16.9499 23.913C18.8721 23.822 20.5747 23.3829 21.9775 21.9748C23.3803 20.572 23.8193 18.8694 '
    . '23.9157 16.9472C24.0281 14.9662 24.0281 9.03381 23.9157 7.05277ZM21.3564 19.0728C20.9388 '
    . '20.1223 20.1303 20.9307 19.0755 21.3537C17.496 21.9802 13.7481 21.8356 12.0027 '
    . '21.8356C10.2572 21.8356 6.50396 21.9748 4.92984 21.3537C3.88042 20.9361 3.07195 20.1276 '
    . '2.64897 19.0728C2.02253 17.4934 2.16709 13.7455 2.16709 12C2.16709 10.2546 2.02789 6.50129 '
    . '2.64897 4.92717C3.06659 3.87776 3.87507 3.06928 4.92984 2.6463C6.50931 2.01986 10.2572 '
    . '2.16443 12.0027 2.16443C13.7481 2.16443 17.5014 2.02522 19.0755 2.6463C20.1249 3.06392 '
    . '20.9334 3.8724 21.3564 4.92717C21.9828 6.50665 21.8383 10.2546 21.8383 12C21.8383 13.7455 '
    . '21.9828 17.4987 21.3564 19.0728Z"/></svg>';

const ICONO_OJO = '<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';

const ICONO_ENLACE = '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>'
    . '<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';

const ICONOS_REDES = ['facebook' => ICONO_FACEBOOK, 'instagram' => ICONO_INSTAGRAM];

const ICONO_TELEFONO = '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2'
    . ' 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3'
    . 'a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>';
const ICONO_CORREO = '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
    . '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>';
const ICONO_MENSAJE = '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.6 8.6 0 0 1-3.8-.9L3 21l1.9-5.2A8.4 8.4 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5z"/></svg>';
const ICONO_ARRIBA = '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M12 19V5M6 11l6-6 6 6"/></svg>';

const ICONO_UBICACION = '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/>'
    . '<circle cx="12" cy="9.5" r="2.5"/></svg>';
const ICONO_UBICACION_GRANDE = '<svg width="40" height="40" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/>'
    . '<circle cx="12" cy="9.5" r="2.5"/></svg>';

// Íconos disponibles para las cifras de "Nuestro impacto" (campo "icono" en datos/impacto.json).
const ICONOS_IMPACTO = [
    'estrella' => '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
    'personas' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>'
        . '<path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'corazon' => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78'
        . 'l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
    'casa' => '<path d="M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
];

const ICONOS_DONAR = [
    'paypal' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>'
        . '<path d="M6 15h4"/>',
    'gofundme' => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78'
        . 'l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
];

const ICONO_SALIR = '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M7 17 17 7M8 7h9v9"/></svg>';

// Íconos disponibles para los programas (campo "icono" en datos/programas.json).
const ICONOS_PROGRAMA = [
    'salud' => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23'
        . 'l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/><path d="M3.5 12h4l1.5-3 3 6 1.5-3h4"/>',
    'educacion' => '<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/>'
        . '<path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/>',
    'brote' => '<path d="M7 20h10"/><path d="M12 20v-8"/><path d="M12 12C12 8 9 5 4 5c0 4 3 7 8 7z"/>'
        . '<path d="M12 12c0-3 2-6 7-6 0 4-3 6-7 6z"/>',
    'personas' => ICONOS_IMPACTO['personas'],
    'apoyo' => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
    'corazon' => ICONOS_IMPACTO['corazon'],
];

const ICONO_CERRAR = '<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>';
const ICONO_ANTERIOR = '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>';
const ICONO_SIGUIENTE = '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>';

const TRAZO_QUIENES = '<svg width="26" height="26" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
const ICONOS_QUIENES = [
    'diana' => TRAZO_QUIENES . '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5"/></svg>',
    'ojo' => TRAZO_QUIENES . '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>',
    'corazon' => TRAZO_QUIENES . '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/></svg>',
    'escudo' => TRAZO_QUIENES . '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
    'personas' => TRAZO_QUIENES . '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>'
        . '<path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/></svg>',
];

const ICONO_DOCUMENTO = '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>'
    . '<path d="M8 13h8M8 17h5"/></svg>';
const ICONO_DESCARGAR = '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>';
const ICONO_VER = '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';

const ICONO_WHATSAPP = ICONO_MENSAJE;
const ICONO_COPIAR = '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
    . '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
const ICONO_FLECHA_DERECHA = '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
    . 'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M5 12h14M13 6l6 6-6 6"/></svg>';

// ───────────── Utilidades ─────────────

/** Carpeta raíz del sitio (la que contiene index.html). */
function raiz_sitio(): string
{
    return dirname(__DIR__);
}

/** Escapa texto para HTML (comillas incluidas). */
function e($texto): string
{
    return str_replace(['&', '<', '>', '"', "'"], ['&amp;', '&lt;', '&gt;', '&quot;', '&#x27;'], (string) $texto);
}

/** Valor de un campo, o $defecto si el campo no existe. */
function campo(array $datos, string $clave, $defecto = null)
{
    return array_key_exists($clave, $datos) ? $datos[$clave] : $defecto;
}

/** ¿El campo existe y tiene algo? (texto vacío, 0, null o lista vacía cuentan como "no"). */
function lleno(array $datos, string $clave): bool
{
    return !empty($datos[$clave]);
}

function leer_datos(string $nombre): array
{
    $ruta = raiz_sitio() . '/datos/' . $nombre;
    $texto = @file_get_contents($ruta);
    if ($texto === false) {
        throw new RuntimeException("No encontré el archivo datos/$nombre");
    }
    $datos = json_decode($texto, true);
    if (!is_array($datos)) {
        throw new RuntimeException("datos/$nombre tiene un error de formato: " . json_last_error_msg());
    }
    return $datos;
}

function es_externo(string $url): bool
{
    return strncmp($url, 'http', 4) === 0;
}

/** Prefijo para llegar a la raíz del sitio desde la página (p. ej. "../" en blog/x.html). */
function raiz(string $pagina): string
{
    return str_repeat('../', substr_count($pagina, '/'));
}

function pestana_activa(string $pagina): ?string
{
    if (strncmp($pagina, 'blog/', 5) === 0) {
        return ACTIVA['blog.html'];
    }
    return ACTIVA[$pagina] ?? null;
}

function atributos_enlace(string $url, string $pagina): string
{
    $destino = es_externo($url) ? $url : raiz($pagina) . $url;
    $attrs = 'href="' . e($destino) . '"';
    if (es_externo($url)) {
        $attrs .= ' target="_blank" rel="noopener"';
    } elseif ($url === $pagina) {
        $attrs .= ' aria-current="page"';
    }
    return $attrs;
}

function slug(string $texto): string
{
    $t = strtr(mb_strtolower($texto, 'UTF-8'), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $t), '-');
}

function retraso(int $i): string
{
    return sprintf('--user-animation-delay:%.2fs;', 0.1 + 0.12 * $i);
}

function fecha_larga(string $iso): string
{
    [$anio, $mes, $dia] = array_map('intval', explode('-', $iso));
    return "$dia de " . MESES[$mes - 1] . " de $anio";
}

/** Reemplaza lo que hay entre dos marcas (incluidas) por $nuevo. */
function reemplazar_entre(string $s, string $inicio, string $fin, string $nuevo, string $pagina): string
{
    $a = strpos($s, $inicio);
    $b = $a === false ? false : strpos($s, $fin, $a + strlen($inicio));
    if ($a === false || $b === false) {
        throw new RuntimeException("$pagina: falta el bloque $inicio … $fin");
    }
    return substr($s, 0, $a) . $nuevo . substr($s, $b + strlen($fin));
}

function marcas(string $nombre): array
{
    return ["<!-- $nombre:inicio -->", "<!-- $nombre:fin -->"];
}

/** Reemplaza un bloque completo (con sus marcas) por $bloque, que ya trae las marcas. */
function reemplazar_seccion(string $s, string $nombre, string $bloque, string $pagina): string
{
    [$inicio, $fin] = marcas($nombre);
    return reemplazar_entre($s, $inicio, $fin, $bloque, $pagina);
}

/** Reemplaza solo el contenido de un bloque, conservando sus marcas. */
function reemplazar_bloque(string $s, string $nombre, string $contenido, string $pagina): string
{
    [$inicio, $fin] = marcas($nombre);
    return reemplazar_entre($s, $inicio, $fin, $inicio . $contenido . $fin, $pagina);
}

function poner_titulo(string $s, string $titulo): string
{
    return preg_replace('~<title>[^<]*</title>~', '<title>' . $titulo . '</title>', $s, 1);
}

/** Codifica para URL igual que urllib.parse.quote de Python (deja "/" sin codificar). */
function codificar_url(string $texto): string
{
    return str_replace('%2F', '/', rawurlencode($texto));
}

/** JSON con espacios tras ":" y "," (el formato de los datos estructurados de los artículos). */
function json_legible($valor): string
{
    if (is_array($valor)) {
        $partes = [];
        foreach ($valor as $clave => $v) {
            $partes[] = json_legible((string) $clave) . ': ' . json_legible($v);
        }
        return '{' . implode(', ', $partes) . '}';
    }
    return json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function version(string $ruta): string
{
    return substr(md5_file(raiz_sitio() . '/' . $ruta), 0, 8);
}

// ───────────── Menú ─────────────

function enlaces_redes(): string
{
    // El mismo HTML que arma js/pie.js.
    $enlaces = '';
    foreach (leer_datos('contacto.json')['redes'] as $r) {
        $red = mb_strtolower(trim($r['red']), 'UTF-8');
        $nombre = mb_strtoupper(mb_substr($red, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($red, 1, null, 'UTF-8');
        $enlaces .= '<a href="' . e($r['enlace']) . '" target="_blank" rel="noopener" '
            . 'aria-label="' . e($nombre) . ' de CAPDES">' . (ICONOS_REDES[$red] ?? ICONO_ENLACE) . '</a>';
    }
    return $enlaces;
}

function redes(string $clase): string
{
    return '<div class="' . $clase . '">' . enlaces_redes() . '</div>';
}

function generar_items(string $pagina, string $sufijo): string
{
    $items = '';
    foreach (MENU as [$texto, $url, $subs]) {
        $id_sub = 'submenu-' . slug($texto) . '-' . $sufijo;
        $activo = pestana_activa($pagina) === $texto;
        $clase = $activo ? 'menu__item menu__item--activo' : 'menu__item';
        $enlace_attrs = atributos_enlace($url, $pagina);
        if ($activo && strpos($enlace_attrs, 'aria-current') === false) {
            $enlace_attrs .= ' aria-current="true"';
        }
        $sub_items = '';
        foreach ($subs as [$s_texto, $s_url]) {
            $sub_items .= '<li><a ' . atributos_enlace($s_url, $pagina) . '>' . e($s_texto) . '</a></li>';
        }
        $items .= '<li class="' . $clase . '">'
            . '<div class="menu__fila">'
            . '<a class="menu__enlace" ' . $enlace_attrs . '>' . e($texto) . '</a>'
            . '<button class="menu__abrir" type="button" aria-expanded="false" aria-controls="' . $id_sub . '" '
            . 'aria-label="Mostrar opciones de ' . e($texto) . '">' . ICONO_FLECHA . '</button>'
            . '</div>'
            . '<ul class="menu__sub" id="' . $id_sub . '">' . $sub_items . '</ul>'
            . '</li>';
    }
    return $items;
}

function generar_menu(string $pagina): string
{
    // El menú tiene dos versiones de la misma lista: las pestañas de la barra
    // (header completo, arriba de la página) y el panel de la hamburguesa
    // (header compacto, al hacer scroll o en celular). js/menu.js alterna entre ambas.
    [$inicio, $fin] = marcas('menu');
    return $inicio
        . '<header class="menu" data-menu>'
        . '<div class="menu__barra">'
        . '<a class="menu__logo" href="' . raiz($pagina) . 'index.html" aria-label="CAPDES, A.C. – Inicio">'
        . '<img src="' . LOGO . '" alt="CAPDES, A.C." width="264" height="143"></a>'
        . '<div class="menu__pestanas"><div class="menu__pestanas-interior">'
        . '<nav class="menu__nav" aria-label="Menú principal">'
        . '<ul class="menu__lista">' . generar_items($pagina, 'barra') . '</ul>'
        . '</nav>'
        . '</div></div>'
        . '<div class="menu__acciones">'
        . '<a class="menu__donar" ' . atributos_enlace(DONAR_URL, $pagina) . '>' . ICONO_CORAZON . '<span>Donar</span></a>'
        . redes('menu__redes')
        . '<button class="menu__hamburguesa" type="button" aria-expanded="false" aria-controls="menu-panel" '
        . 'aria-label="Abrir menú"><span></span><span></span><span></span></button>'
        . '</div>'
        . '</div>'
        . '<nav class="menu__panel" id="menu-panel" aria-label="Menú principal">'
        . '<ul class="menu__lista">' . generar_items($pagina, 'panel') . '</ul>'
        . redes('menu__redes menu__redes--panel')
        . '</nav>'
        . '</header>'
        . $fin;
}

// ───────────── Pie de página ─────────────

function generar_contador(): string
{
    return '<p class="contador" data-contador aria-live="polite">'
        . '<span class="contador__icono">' . ICONO_OJO . '</span>'
        . '<span class="contador__numero">…</span>'
        . '<span class="contador__texto">visitas a nuestro sitio</span>'
        . '</p>';
}

function lista_contacto(): string
{
    // El mismo HTML que arma js/pie.js.
    $c = leer_datos('contacto.json');
    $items = '';
    if (lleno($c, 'telefono')) {
        $tel = preg_replace('/[^\d+]/', '', $c['telefono']);
        $items .= '<li><a href="tel:' . $tel . '"><span class="pie__icono">' . ICONO_TELEFONO . '</span>'
            . '<span>' . e($c['telefono']) . '</span></a></li>';
    }
    if (lleno($c, 'whatsapp')) {
        $wa = preg_replace('/\D/', '', $c['whatsapp']);
        $items .= '<li><a href="https://wa.me/' . $wa . '" target="_blank" rel="noopener">'
            . '<span class="pie__icono">' . ICONO_MENSAJE . '</span><span>WhatsApp</span></a></li>';
    }
    if (lleno($c, 'correo')) {
        $items .= '<li><a href="mailto:' . e($c['correo']) . '"><span class="pie__icono">' . ICONO_CORREO . '</span>'
            . '<span>' . e($c['correo']) . '</span></a></li>';
    }
    return $items;
}

function aviso_publicado(): bool
{
    // El aviso de privacidad (y sus enlaces) solo existe cuando "publicar" es true.
    return (bool) campo(leer_datos('aviso-privacidad.json'), 'publicar', false);
}

function generar_pie(string $pagina): string
{
    $c = leer_datos('contacto.json');
    $r = raiz($pagina);
    [$inicio, $fin] = marcas('pie');
    return $inicio
        . '<footer class="pie">'
        . '<div class="pie__contenido">'
        . '<div class="pie__marca">'
        . '<a class="pie__logo" href="' . $r . 'index.html" aria-label="CAPDES, A.C. – Inicio">'
        . '<img src="' . LOGO . '" alt="CAPDES, A.C." width="264" height="143" loading="lazy"></a>'
        . '<p class="pie__nombre" data-pie-nombre>' . e($c['nombre_completo']) . '</p>'
        . '<div class="pie__redes" data-pie-redes>' . enlaces_redes() . '</div>'
        . generar_contador()
        . '</div>'
        . '<div class="pie__columna">'
        . '<h2 class="pie__titulo">Contáctanos</h2><ul class="pie__contacto" data-pie-contacto>' . lista_contacto() . '</ul>'
        . '</div>'
        . '<div class="pie__columna">'
        . '<h2 class="pie__titulo">Sé parte del cambio</h2>'
        . '<p class="pie__texto">Déjanos tu correo y te contamos sobre nuestras jornadas, talleres y eventos.</p>'
        . '<form class="pie__formulario" data-pie-formulario>'
        . '<label class="solo-lectores" for="pie-correo">Tu correo electrónico</label>'
        . '<input id="pie-correo" type="email" name="correo" placeholder="tu@correo.com" autocomplete="email" required>'
        . '<button type="submit">Quiero recibir noticias</button>'
        . '</form>'
        . '<p class="pie__aviso" data-pie-aviso role="status"></p>'
        . (aviso_publicado() ? '<p class="pie__privacidad">Al suscribirte aceptas nuestro '
            . '<a href="' . $r . 'aviso-de-privacidad.html">aviso de privacidad</a>.</p>' : '')
        . '</div>'
        . '</div>'
        . '<div class="pie__base">'
        . '<p>© <span data-pie-anio>' . date('Y') . '</span> CAPDES, A.C. Todos los derechos reservados.</p>'
        . '<nav class="pie__legal" aria-label="Información legal">'
        . (aviso_publicado() ? '<a ' . atributos_enlace('aviso-de-privacidad.html', $pagina) . '>Aviso de privacidad</a>' : '')
        . '<a ' . atributos_enlace('marco-institucional.html', $pagina) . '>Transparencia</a>'
        . '</nav>'
        . '<a class="pie__arriba" href="#">Volver arriba' . ICONO_ARRIBA . '</a>'
        . '</div>'
        . '</footer>'
        . $fin;
}

// ───────────── Inicio ─────────────
// Cada función arma el mismo HTML que su equivalente en js/inicio.js.

function generar_slides(): string
{
    $slides = '';
    foreach (leer_datos('jornadas.json')['jornadas'] as $i => $j) {
        $clases = 'jornadas__slide';
        if (lleno($j, 'llenar_marco')) {
            $clases .= ' jornadas__slide--llena';
        }
        if ($i === 0) {
            $clases .= ' jornadas__slide--activa';
        }
        $carga = $i === 0 ? '' : ' loading="lazy"';
        $src = e($j['imagen']);
        $alt = e(campo($j, 'descripcion') ?: (campo($j, 'titulo') ?: ''));
        $partes = '';
        if (!lleno($j, 'llenar_marco')) {
            $partes .= '<img class="jornadas__fondo" src="' . $src . '" alt="" aria-hidden="true"' . $carga . '>';
        }
        $partes .= '<img class="jornadas__foto" src="' . $src . '" alt="' . $alt . '"' . $carga . '>';
        if (lleno($j, 'titulo')) {
            $partes .= '<figcaption class="jornadas__titulo">' . e($j['titulo']) . '</figcaption>';
        }
        $slides .= '<figure class="' . $clases . '">' . $partes . '</figure>';
    }
    [$inicio, $fin] = marcas('jornadas-slides');
    return $inicio . $slides . $fin;
}

function icono_impacto(?string $nombre): string
{
    $trazo = ICONOS_IMPACTO[$nombre ?? ''] ?? ICONOS_IMPACTO['estrella'];
    return '<svg width="28" height="28" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $trazo . '</svg>';
}

function generar_eventos(): string
{
    $tarjetas = '';
    foreach (leer_datos('eventos.json')['eventos'] as $i => $ev) {
        $mes = mb_strtolower(trim($ev['mes']), 'UTF-8');
        if (!in_array($mes, MESES, true)) {
            throw new RuntimeException('datos/eventos.json: mes "' . $ev['mes'] . '" no válido (usa el nombre completo, p. ej. "septiembre")');
        }
        $tarjetas .= '<li class="eventos__tarjeta transition transition--slide" style="' . retraso($i) . '">'
            . '<div class="eventos__foto"><img src="' . e($ev['imagen']) . '" '
            . 'alt="' . e(campo($ev, 'descripcion') ?: $ev['titulo']) . '" loading="lazy">'
            . '<p class="eventos__fecha"><span class="eventos__dia">' . sprintf('%02d', (int) $ev['dia']) . '</span>'
            . '<span class="eventos__mes"><abbr title="' . $mes . '">' . mb_strtoupper(mb_substr($mes, 0, 3, 'UTF-8'), 'UTF-8') . '</abbr></span></p></div>'
            . '<div class="eventos__cuerpo">'
            . (lleno($ev, 'tipo') ? '<p class="eventos__tipo">' . e($ev['tipo']) . '</p>' : '')
            . '<h3 class="eventos__nombre">' . e($ev['titulo']) . '</h3>'
            . (lleno($ev, 'lugar') ? '<p class="eventos__lugar">' . ICONO_UBICACION . '<span>' . e($ev['lugar']) . '</span></p>' : '')
            . '</div></li>';
    }
    return $tarjetas;
}

function generar_impacto(): array
{
    $datos = leer_datos('impacto.json');
    $cifras = '';
    foreach ($datos['cifras'] as $i => $c) {
        $prefijo = e(campo($c, 'prefijo', ''));
        $numero = (int) $c['numero'];
        $cifras .= '<li class="impacto__cifra transition transition--slide" style="' . retraso($i) . '">'
            . '<span class="impacto__icono">' . icono_impacto(campo($c, 'icono')) . '</span>'
            . '<span class="impacto__dato"><strong class="impacto__numero" data-contar="' . $numero . '" '
            . 'data-prefijo="' . $prefijo . '">' . $prefijo . $numero . '</strong>'
            . '<span class="impacto__etiqueta">' . e($c['texto']) . '</span></span>'
            . '</li>';
    }
    $areas = '';
    foreach ($datos['areas'] as $i => $a) {
        $numero = '';
        if (campo($a, 'numero') !== null) {
            $prefijo = e(campo($a, 'prefijo', '+'));
            $n = (int) $a['numero'];
            $numero = '<strong class="impacto__area-numero" data-contar="' . $n . '" '
                . 'data-prefijo="' . $prefijo . '">' . $prefijo . $n . '</strong>';
        }
        $areas .= '<li class="impacto__area transition transition--slide" style="' . retraso($i) . '">'
            . '<img src="' . e($a['imagen']) . '" alt="' . e(campo($a, 'descripcion') ?: $a['titulo']) . '" loading="lazy">'
            . '<span class="impacto__area-titulo">' . $numero . e($a['titulo']) . '</span>'
            . '</li>';
    }
    return [
        'impacto-titulo' => e($datos['titulo']),
        'impacto-cita' => e($datos['cita']),
        'impacto-cifras' => $cifras,
        'impacto-areas' => $areas,
    ];
}

/** Nombre de plataforma seguro para usar en una clase CSS. */
function plataforma(?string $nombre): string
{
    return preg_replace('/[^a-z0-9-]/', '', mb_strtolower($nombre ?? '', 'UTF-8')) ?: 'otra';
}

function generar_donar(): array
{
    $datos = leer_datos('donar.json');
    $opciones = '';
    foreach ($datos['opciones'] as $i => $o) {
        $clave = plataforma(campo($o, 'plataforma'));
        $trazo = ICONOS_DONAR[$clave] ?? ICONOS_DONAR['gofundme'];
        $opciones .= '<li class="donar__opcion donar__opcion--' . $clave . ' transition transition--slide" style="' . retraso($i) . '">'
            . '<span class="donar__icono"><svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" '
            . 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . $trazo . '</svg></span>'
            . '<h3 class="donar__nombre">' . e($o['titulo']) . '</h3>'
            . '<p class="donar__texto">' . e($o['texto']) . '</p>'
            . '<a class="donar__boton" href="' . e($o['enlace']) . '" target="_blank" rel="noopener">'
            . e($o['boton']) . ICONO_SALIR
            . '<span class="solo-lectores"> (se abre en otra pestaña)</span></a>'
            . '</li>';
    }
    return [
        'donar-titulo' => e($datos['titulo']),
        'donar-texto' => e($datos['texto']),
        'donar-opciones' => $opciones,
    ];
}

function generar_aliados(): array
{
    $datos = leer_datos('aliados.json');
    $lista = function (bool $copia) use ($datos): string {
        $items = '';
        foreach ($datos['aliados'] as $a) {
            $logo = '<img src="' . e($a['logo']) . '" alt="' . ($copia ? '' : e($a['nombre'])) . '" loading="lazy">';
            if (lleno($a, 'enlace')) {
                $extra = $copia ? ' tabindex="-1"' : '';
                $logo = '<a href="' . e($a['enlace']) . '" target="_blank" rel="noopener"' . $extra . '>' . $logo . '</a>';
            }
            $items .= '<li class="aliados__logo">' . $logo . '</li>';
        }
        $oculto = $copia ? ' aria-hidden="true"' : '';
        return '<ul class="aliados__lista"' . $oculto . '>' . $items . '</ul>';
    };
    // La segunda lista es una copia para que el desplazamiento sea continuo.
    return [
        'aliados-titulo' => e($datos['titulo']),
        'aliados-lista' => $lista(false) . $lista(true),
    ];
}

function insertar_inicio(string $s, string $pagina): string
{
    if ($pagina !== 'index.html') {
        return $s;
    }
    $s = reemplazar_seccion($s, 'jornadas-slides', generar_slides(), $pagina);
    $s = reemplazar_bloque($s, 'eventos-lista', generar_eventos(), $pagina);
    foreach ([generar_impacto(), generar_donar(), generar_aliados()] as $bloques) {
        foreach ($bloques as $nombre => $contenido) {
            $s = reemplazar_bloque($s, $nombre, $contenido, $pagina);
        }
    }
    return $s;
}

// ───────────── Programas (datos/programas.json) ─────────────

function leer_programas(): array
{
    $datos = leer_datos('programas.json');
    $tipos = array_column($datos['tipos'], 'clave');
    $claves = [];
    foreach ($datos['programas'] as $pr) {
        if (!in_array($pr['tipo'], $tipos, true)) {
            throw new RuntimeException('datos/programas.json: el programa "' . $pr['nombre'] . '" tiene un tipo que no existe: "' . $pr['tipo'] . '"');
        }
        foreach (campo($pr, 'ods', []) as $n) {
            if ((int) $n < 1 || (int) $n > 17) {
                throw new RuntimeException('datos/programas.json: "' . $pr['nombre'] . '" tiene el ODS ' . $n . '; deben ser del 1 al 17');
            }
        }
        $claves[] = $pr['clave'];
    }
    foreach ($datos['jornadas'] as $j) {
        if (!in_array($j['programa'], $claves, true)) {
            throw new RuntimeException('datos/programas.json: la jornada "' . $j['titulo'] . '" usa un programa que no existe: "' . $j['programa'] . '"');
        }
        if (lleno($j, 'fecha') && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $j['fecha'])) {
            throw new RuntimeException('datos/programas.json: fecha "' . $j['fecha'] . '" no válida (usa AAAA-MM-DD o déjala vacía)');
        }
    }
    return $datos;
}

function icono_programa(?string $nombre): string
{
    $trazo = ICONOS_PROGRAMA[$nombre ?? ''] ?? ICONOS_PROGRAMA['corazon'];
    return '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $trazo . '</svg>';
}

function item_galeria(array $j, array $nombres): string
{
    // Foto de la galería de jornadas (el mismo HTML que arma js/programas.js).
    $fecha = lleno($j, 'fecha') ? fecha_larga($j['fecha']) : '';
    $detalle = implode(' · ', array_filter([campo($j, 'lugar', ''), $fecha], 'strlen'));
    $programa = $nombres[$j['programa']] ?? '';
    return '<li class="galeria__item" data-programa="' . e($j['programa']) . '">'
        . '<button type="button" class="galeria__boton" data-galeria-abrir '
        . 'data-programa-nombre="' . e($programa) . '" data-titulo="' . e($j['titulo']) . '" '
        . 'data-detalle="' . e($detalle) . '">'
        . '<img src="' . e($j['imagen']) . '" alt="' . e(campo($j, 'descripcion') ?: $j['titulo']) . '" loading="lazy">'
        . '<span class="galeria__info"><span class="galeria__programa">' . e($programa) . '</span>'
        . '<span class="galeria__titulo">' . e($j['titulo']) . '</span></span>'
        . '</button></li>';
}

function generar_programas(string $pagina): string
{
    $datos = leer_programas();
    $tipos = array_column($datos['tipos'], null, 'clave');
    $nombres = array_column($datos['programas'], 'nombre', 'clave');
    $conteo = [];
    foreach ($nombres as $c => $_) {
        $conteo[$c] = count(array_filter($datos['jornadas'], fn($j) => $j['programa'] === $c));
    }
    $ods_capdes = [];
    foreach ($datos['programas'] as $pr) {
        foreach (campo($pr, 'ods', []) as $n) {
            $ods_capdes[(int) $n] = true;
        }
    }

    $pestanas = '<button type="button" class="programas__filtro" aria-pressed="true" data-tipo="">Todos</button>';
    foreach ($datos['tipos'] as $t) {
        $usado = false;
        foreach ($datos['programas'] as $pr) {
            $usado = $usado || $pr['tipo'] === $t['clave'];
        }
        if ($usado) {
            $pestanas .= '<button type="button" class="programas__filtro" aria-pressed="false" data-tipo="' . e($t['clave']) . '" '
                . 'data-descripcion="' . e($t['descripcion']) . '">' . e($t['nombre']) . '</button>';
        }
    }

    $tarjetas = '';
    foreach ($datos['programas'] as $pr) {
        $chips = '';
        foreach (campo($pr, 'ods', []) as $x) {
            $n = (int) $x;
            [$nombre_ods, $color] = ODS[$n - 1];
            $chips .= '<li class="ods-chip" style="--ods:' . $color . '" title="ODS ' . $n . ': ' . e($nombre_ods) . '">'
                . '<span class="solo-lectores">ODS </span>' . $n . '<span class="solo-lectores">: ' . e($nombre_ods) . '</span></li>';
        }
        $n_jornadas = $conteo[$pr['clave']];
        $tarjetas .= '<li class="programa transition transition--slide" data-tipo="' . e($pr['tipo']) . '" id="programa-' . $pr['clave'] . '">'
            . '<article class="programa__tarjeta">'
            . '<div class="programa__foto"><img src="' . e($pr['imagen']) . '" '
            . 'alt="' . e(campo($pr, 'descripcion_imagen') ?: '') . '" loading="lazy">'
            . '<span class="programa__icono">' . icono_programa(campo($pr, 'icono')) . '</span></div>'
            . '<div class="programa__cuerpo">'
            . '<p class="programa__tipo">' . e($tipos[$pr['tipo']]['nombre']) . '</p>'
            . '<h3 class="programa__nombre">' . e($pr['nombre']) . '</h3>'
            . '<p class="programa__texto">' . e($pr['descripcion']) . '</p>'
            . ($chips !== '' ? '<ul class="programa__ods" aria-label="Objetivos de Desarrollo Sostenible">' . $chips . '</ul>' : '')
            . '<a class="programa__boton" href="#jornadas" data-ver-jornadas="' . e($pr['clave']) . '"'
            . ($n_jornadas ? '' : ' hidden') . '><span>Ver jornadas (<span data-conteo>' . $n_jornadas . '</span>)</span>' . ICONO_FLECHA_DERECHA . '</a>'
            . '</div></article></li>';
    }

    $filtros_galeria = '<button type="button" class="galeria__filtro" aria-pressed="true" data-programa="">Todas</button>';
    foreach ($nombres as $c => $nombre) {
        if ($conteo[$c]) {
            $filtros_galeria .= '<button type="button" class="galeria__filtro" aria-pressed="false" data-programa="' . e($c) . '">'
                . e($nombre) . '</button>';
        }
    }
    $fotos = '';
    foreach ($datos['jornadas'] as $j) {
        $fotos .= item_galeria($j, $nombres);
    }

    $ods = '';
    foreach (ODS as $i => [$nombre]) {
        $n = $i + 1;
        $ods .= '<li class="ods__item' . (isset($ods_capdes[$n]) ? ' ods__item--capdes' : '') . '">'
            . '<img src="img/ods/ods-' . sprintf('%02d', $n) . '.png" alt="ODS ' . $n . ': ' . e($nombre) . '" loading="lazy" width="240" height="240">'
            . '<span class="ods__nombre">' . e($nombre) . '</span></li>';
    }

    [$inicio, $fin] = marcas('programas');
    return $inicio
        . '<section class="portada portada--pagina">'
        . '<div class="portada__fondo" aria-hidden="true"><img src="' . e($datos['imagen_portada']) . '" alt=""></div>'
        . '<div class="portada__contenido">'
        . '<p class="portada__antetitulo transition transition--fade">• Programas</p>'
        . '<h1 class="portada__titulo transition transition--slide" style="--user-animation-delay:0.1s;">Nuestros <span>programas</span></h1>'
        . '<p class="portada__intro transition transition--slide" style="--user-animation-delay:0.2s;">' . e($datos['intro']) . '</p>'
        . '<div class="eventos__botones portada__botones transition transition--fade" style="--user-animation-delay:0.3s;">'
        . '<a class="eventos__boton eventos__boton--principal" href="#programas">Conoce los programas' . ICONO_FLECHA_DERECHA . '</a>'
        . '<a class="eventos__boton eventos__boton--secundario" href="#jornadas">Ver jornadas realizadas</a>'
        . '</div></div></section>'

        . '<section class="programas" id="programas" aria-labelledby="programas-titulo">'
        . '<div class="programas__contenido">'
        . '<header class="programas__encabezado">'
        . '<p class="programas__antetitulo">• Modelos de intervención integral</p>'
        . '<h2 class="programas__titulo" id="programas-titulo">Programas de intervención social</h2>'
        . '<p class="programas__intro" data-tipo-descripcion data-original="' . e($datos['modelo']) . '">' . e($datos['modelo']) . '</p>'
        . '</header>'
        . '<div class="programas__filtros" role="group" aria-label="Filtrar programas por tipo" data-programas-filtros>' . $pestanas . '</div>'
        . '<ul class="programas__lista" data-programas-lista>' . $tarjetas . '</ul>'
        . '</div></section>'

        . '<section class="galeria" id="jornadas" aria-labelledby="galeria-titulo">'
        . '<div class="galeria__contenido">'
        . '<header class="programas__encabezado">'
        . '<p class="programas__antetitulo">• Galería</p>'
        . '<h2 class="programas__titulo" id="galeria-titulo">Jornadas realizadas</h2>'
        . '<p class="programas__intro">Así se ve el trabajo de nuestros programas en las comunidades. Elige un programa para ver sus jornadas.</p>'
        . '</header>'
        . '<div class="galeria__filtros" role="group" aria-label="Filtrar jornadas por programa" data-galeria-filtros>' . $filtros_galeria . '</div>'
        . '<ul class="galeria__lista" data-galeria-lista>' . $fotos . '</ul>'
        . '<p class="galeria__vacio" data-galeria-vacio hidden>Pronto compartiremos las jornadas de este programa.</p>'
        . '</div></section>'

        . '<dialog class="visor" data-visor aria-label="Foto de la jornada">'
        . '<figure class="visor__figura"><img data-visor-img src="" alt="">'
        . '<figcaption class="visor__pie"><span class="visor__programa" data-visor-programa></span>'
        . '<span class="visor__titulo" data-visor-titulo></span><span class="visor__detalle" data-visor-detalle></span>'
        . '<span class="visor__contador" data-visor-contador></span></figcaption></figure>'
        . '<button type="button" class="visor__boton visor__boton--cerrar" data-visor-cerrar aria-label="Cerrar">' . ICONO_CERRAR . '</button>'
        . '<button type="button" class="visor__boton visor__boton--anterior" data-visor-anterior aria-label="Foto anterior">' . ICONO_ANTERIOR . '</button>'
        . '<button type="button" class="visor__boton visor__boton--siguiente" data-visor-siguiente aria-label="Foto siguiente">' . ICONO_SIGUIENTE . '</button>'
        . '</dialog>'

        . '<section class="ods" id="ods" aria-labelledby="ods-titulo">'
        . '<div class="ods__contenido">'
        . '<header class="programas__encabezado">'
        . '<p class="programas__antetitulo">• Agenda 2030</p>'
        . '<h2 class="programas__titulo" id="ods-titulo">Alineados con los Objetivos de Desarrollo Sostenible</h2>'
        . '<p class="programas__intro">' . e($datos['agenda_2030']) . ' Resaltamos los objetivos en los que trabajan nuestros programas.</p>'
        . '</header>'
        . '<ul class="ods__lista">' . $ods . '</ul>'
        . '</div></section>'

        . '<section class="suscribir apoyar" aria-labelledby="apoyar-titulo">'
        . '<div class="suscribir__tarjeta">'
        . '<div><h2 class="suscribir__titulo" id="apoyar-titulo">¿Quieres apoyar un programa?</h2>'
        . '<p class="suscribir__texto">Dona, súmate como voluntario o construyamos juntos una alianza.</p></div>'
        . '<div class="apoyar__botones">'
        . '<a class="suscribir__boton" href="index.html#donar">' . ICONO_CORAZON . 'Donar</a>'
        . '<a class="suscribir__boton suscribir__boton--linea" href="contacto.html?motivo=voluntariado#formulario">Ser voluntario</a>'
        . '<a class="suscribir__boton suscribir__boton--linea" href="contacto.html?motivo=alianza#formulario">Hacer una alianza</a>'
        . '</div></div></section>'
        . $fin;
}

function insertar_programas(string $s, string $pagina): string
{
    if ($pagina !== 'programas.html') {
        return $s;
    }
    $s = poner_titulo($s, 'Nuestros programas | CAPDES, A.C.');
    return reemplazar_seccion($s, 'programas', generar_programas($pagina), $pagina);
}

// ───────────── Quiénes somos (datos/quienes-somos.json) ─────────────

function icono_quienes(?string $nombre): string
{
    return ICONOS_QUIENES[$nombre ?? ''] ?? ICONOS_QUIENES['corazon'];
}

function generar_quienes(string $pagina): string
{
    $q = leer_datos('quienes-somos.json');
    $nombre = leer_datos('contacto.json')['nombre_completo'];
    $a = $q['acerca'];

    $principio = function (string $clave, string $etiqueta, string $titulo, string $icono, int $i) use ($q): string {
        $d = $q[$clave];
        return '<article class="principio transition transition--slide" style="' . retraso($i) . '">'
            . '<span class="principio__icono">' . icono_quienes($icono) . '</span>'
            . '<p class="principio__etiqueta">' . $etiqueta . '</p>'
            . '<h3 class="principio__titulo">' . $titulo . '</h3>'
            . '<p class="principio__texto">' . e($d['texto']) . '</p>'
            . '<img class="principio__foto" src="' . e($d['imagen']) . '" '
            . 'alt="' . e(campo($d, 'descripcion_imagen') ?: '') . '" loading="lazy">'
            . '</article>';
    };

    $valores = '';
    foreach ($q['valores'] as $i => $v) {
        $valores .= '<li class="valor transition transition--slide" style="' . retraso($i) . '">'
            . '<span class="valor__icono">' . icono_quienes(campo($v, 'icono')) . '</span>'
            . '<h3 class="valor__titulo">' . e($v['titulo']) . '</h3>'
            . '<p class="valor__texto">' . e($v['texto']) . '</p></li>';
    }

    [$inicio, $fin] = marcas('quienes');
    return $inicio
        . '<section class="quienes acerca" id="acerca">'
        . '<div class="quienes__contenido">'
        . '<h1 class="acerca__titulo transition transition--slide">Acerca de nosotros</h1>'
        . '<div class="acerca__columnas transition transition--slide" style="--user-animation-delay:0.1s;">'
        . '<div><h2 class="acerca__subtitulo">CAPDES, A.C. — ' . e($nombre) . '</h2>'
        . '<p>' . e($a['quienes_somos']) . '</p></div>'
        . '<div><h2 class="acerca__subtitulo">¿Qué hacemos?</h2>'
        . '<p>' . e($a['que_hacemos']) . '</p></div>'
        . '</div>'
        . '<img class="acerca__foto transition transition--scale" style="--user-animation-delay:0.2s;" '
        . 'src="' . e($a['imagen']) . '" alt="' . e(campo($a, 'descripcion_imagen') ?: '') . '">'
        . '</div></section>'
        . '<section class="quienes principios" id="mision-vision-valores" aria-labelledby="principios-titulo">'
        . '<div class="quienes__contenido">'
        . '<header class="principios__encabezado">'
        . '<p class="principios__antetitulo">Principios institucionales</p>'
        . '<h2 class="principios__titulo" id="principios-titulo">Misión, Visión y Valores de CAPDES</h2>'
        . '<p class="principios__intro">' . e($q['principios_intro']) . '</p>'
        . '</header>'
        . '<div class="principios__rejilla">'
        . $principio('mision', 'Propósito social', 'Nuestra Misión', 'diana', 0)
        . $principio('vision', 'Futuro deseado', 'Nuestra Visión', 'ojo', 1)
        . '</div>'
        . '<ul class="valores">' . $valores . '</ul>'
        . '</div></section>'
        . $fin;
}

function insertar_quienes(string $s, string $pagina): string
{
    if ($pagina !== 'quienes-somos.html') {
        return $s;
    }
    $s = poner_titulo($s, 'Quiénes somos | CAPDES, A.C.');
    return reemplazar_seccion($s, 'quienes', generar_quienes($pagina), $pagina);
}

// ───────────── Contacto (datos/contacto.json) ─────────────

function canales_contacto(array $c): string
{
    // Tarjetas de contacto directo de la página de Contacto.
    $canales = [];
    if (lleno($c, 'telefono')) {
        $tel = preg_replace('/[^\d+]/', '', $c['telefono']);
        $canales[] = ['Llámanos', $c['telefono'], "tel:$tel", ICONO_TELEFONO, false];
    }
    if (lleno($c, 'whatsapp')) {
        $canales[] = ['WhatsApp', 'Escríbenos por WhatsApp', 'https://wa.me/' . preg_replace('/[^0-9]/', '', $c['whatsapp']),
            ICONO_MENSAJE, true];
    }
    if (lleno($c, 'correo')) {
        $canales[] = ['Correo', $c['correo'], 'mailto:' . $c['correo'], ICONO_CORREO, false];
    }
    $tarjetas = '';
    foreach ($canales as [$tipo, $dato, $url, $icono, $externo]) {
        $tarjetas .= '<li><a class="canal" href="' . e($url) . '"'
            . ($externo ? ' target="_blank" rel="noopener"' : '')
            . '><span class="canal__icono">' . $icono . '</span><span class="canal__texto">'
            . '<span class="canal__tipo">' . e($tipo) . '</span><span class="canal__dato">' . e($dato) . '</span>'
            . '</span></a></li>';
    }
    return $tarjetas;
}

function generar_contacto(string $pagina): string
{
    $c = leer_datos('contacto.json');
    $opciones = '<option value="" disabled selected>Elige una opción</option>';
    foreach ($c['motivos'] as $m) {
        $opciones .= '<option value="' . e($m['clave']) . '">' . e($m['texto']) . '</option>';
    }
    if (lleno($c, 'mapa_url')) {
        $mapa = '<iframe class="ubicacion__mapa" src="' . e($c['mapa_url']) . '" title="Mapa de ubicaciones de CAPDES" '
            . 'loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>';
    } else {
        $mapa = '<div class="ubicacion__mapa ubicacion__mapa--pendiente">' . ICONO_UBICACION_GRANDE
            . '<p class="ubicacion__pendiente-titulo">Mapa de ubicaciones</p></div>';
    }
    [$inicio, $fin] = marcas('contacto');
    return $inicio
        . '<section class="contacto">'
        . '<div class="contacto__fondo" aria-hidden="true">'
        . '<img class="contacto__poster" src="img/contacto/fondo-contacto.jpg" alt="">'
        // El video solo se carga en pantallas grandes (js/contacto.js); en celular queda la imagen.
        . '<video class="contacto__video" muted loop playsinline preload="none" '
        . 'data-src="img/contacto/fondo-contacto.mp4" data-contacto-video></video>'
        . '</div>'
        . '<div class="contacto__contenido">'
        . '<header class="contacto__encabezado">'
        . '<p class="portada__antetitulo">• Contacto</p>'
        . '<h1 class="contacto__titulo">Contáctanos</h1>'
        . '<p class="contacto__intro">Si deseas que CAPDES, A.C. llegue a tu comunidad, escríbenos. También puedes '
        . 'sumarte como voluntario, proponernos una alianza o resolver cualquier duda.</p>'
        . '</header>'
        . '<div class="contacto__rejilla">'
        . '<div class="contacto__canales">'
        . '<h2 class="contacto__subtitulo">Habla con nosotros</h2>'
        . '<ul class="canales">' . canales_contacto($c) . '</ul>'
        . '<div class="contacto__redes"><p>Síguenos</p><div class="pie__redes">' . enlaces_redes() . '</div></div>'
        . '</div>'
        . '<form class="formulario" id="formulario" action="api/contacto.php" method="post" novalidate '
        . 'data-contacto-formulario data-correo="' . e($c['correo']) . '">'
        . '<h2 class="contacto__subtitulo formulario__titulo">Envíanos un mensaje</h2>'
        . '<p class="formulario__nota">Los campos con <span aria-hidden="true">*</span><span class="solo-lectores">asterisco</span> son obligatorios.</p>'
        . '<div class="formulario__fila">'
        . '<div class="campo"><label for="c-nombre">Nombre(s) <span class="campo__obligatorio" aria-hidden="true">*</span></label>'
        . '<input id="c-nombre" name="nombre" type="text" autocomplete="given-name" required maxlength="80"></div>'
        . '<div class="campo"><label for="c-apellidos">Apellidos</label>'
        . '<input id="c-apellidos" name="apellidos" type="text" autocomplete="family-name" maxlength="80"></div>'
        . '</div>'
        . '<div class="formulario__fila">'
        . '<div class="campo"><label for="c-correo">Correo electrónico <span class="campo__obligatorio" aria-hidden="true">*</span></label>'
        . '<input id="c-correo" name="correo" type="email" autocomplete="email" required maxlength="120"></div>'
        . '<div class="campo"><label for="c-telefono">Teléfono o WhatsApp</label>'
        . '<input id="c-telefono" name="telefono" type="tel" autocomplete="tel" maxlength="30"></div>'
        . '</div>'
        . '<div class="campo"><label for="c-motivo">¿En qué te podemos ayudar? <span class="campo__obligatorio" aria-hidden="true">*</span></label>'
        . '<select id="c-motivo" name="motivo" required>' . $opciones . '</select></div>'
        . '<div class="campo"><label for="c-mensaje">Mensaje <span class="campo__obligatorio" aria-hidden="true">*</span></label>'
        . '<textarea id="c-mensaje" name="mensaje" rows="5" required maxlength="4000" '
        . 'placeholder="Cuéntanos sobre tu comunidad, tu idea o tu duda."></textarea></div>'
        // Campo trampa contra spam: las personas no lo ven; los robots suelen llenarlo.
        . '<div class="formulario__trampa" aria-hidden="true"><label for="c-sitio">No llenes este campo</label>'
        . '<input id="c-sitio" name="sitio" type="text" tabindex="-1" autocomplete="off"></div>'
        . '<button class="formulario__enviar" type="submit">Enviar mensaje' . ICONO_FLECHA_DERECHA . '</button>'
        . '<p class="formulario__estado" role="status" data-contacto-estado></p>'
        . '</form>'
        . '</div></div></section>'
        . '<section class="ubicacion" id="ubicacion" aria-labelledby="ubicacion-titulo">'
        . '<div class="ubicacion__contenido">'
        . '<div class="ubicacion__texto">'
        . '<p class="programas__antetitulo">• Ubicación</p>'
        . '<h2 class="programas__titulo" id="ubicacion-titulo">Dónde trabajamos</h2>'
        . '<p>Nuestra organización se encuentra en el corazón de la comunidad, trabajando para empoderar a las '
        . 'poblaciones vulnerables y reducir las desigualdades sociales.</p>'
        . '<p class="ubicacion__lugar">' . ICONO_UBICACION . e(campo($c, 'ubicacion', '')) . '</p>'
        . '</div>'
        . $mapa
        . '</div></section>'
        . $fin;
}

function insertar_contacto(string $s, string $pagina): string
{
    if ($pagina !== 'contacto.html') {
        return $s;
    }
    $s = poner_titulo($s, 'Contacto | CAPDES, A.C.');
    return reemplazar_seccion($s, 'contacto', generar_contacto($pagina), $pagina);
}

// ───────────── Marco institucional (datos/marco.json) y Aviso de privacidad ─────────────

/** Formato y peso de un documento del sitio, p. ej. "PDF · 347 KB". */
function peso_archivo(string $ruta): string
{
    $archivo = raiz_sitio() . '/' . $ruta;
    if (!is_file($archivo)) {
        throw new RuntimeException('datos/marco.json: no existe el archivo "' . $ruta . '"');
    }
    $kb = filesize($archivo) / 1024;
    $tamano = $kb >= 1024
        ? str_replace('.', ',', sprintf('%.1f MB', $kb / 1024))
        : (int) round($kb, 0, PHP_ROUND_HALF_EVEN) . ' KB';
    return strtoupper(pathinfo($archivo, PATHINFO_EXTENSION)) . ' · ' . $tamano;
}

function documento_marco(array $d): string
{
    $meta = [];
    if (lleno($d, 'clave')) {
        $meta[] = 'Clave ' . e($d['clave']);
    }
    if (lleno($d, 'vigente_desde')) {
        $meta[] = 'Vigente desde el ' . fecha_larga($d['vigente_desde']);
    }
    if (lleno($d, 'archivo')) {
        $meta[] = peso_archivo($d['archivo']);
        $nombre = e($d['titulo']);
        $acciones = '<div class="documento__acciones">'
            . '<a class="documento__boton" href="' . e($d['archivo']) . '" target="_blank" rel="noopener">'
            . ICONO_VER . 'Ver<span class="solo-lectores"> ' . $nombre . ' (se abre en otra pestaña)</span></a>'
            . '<a class="documento__boton documento__boton--principal" href="' . e($d['archivo']) . '" download>'
            . ICONO_DESCARGAR . 'Descargar<span class="solo-lectores"> ' . $nombre . '</span></a>'
            . '</div>';
        $clase = 'documento';
    } else {
        $acciones = '<p class="documento__estado">Próximamente</p>';
        $clase = 'documento documento--pendiente';
    }
    return '<li class="' . $clase . '">'
        . '<span class="documento__icono">' . ICONO_DOCUMENTO . '</span>'
        . '<div class="documento__info">'
        . '<h3 class="documento__titulo">' . e($d['titulo']) . '</h3>'
        . ($meta ? '<p class="documento__meta">' . implode(' · ', $meta) . '</p>' : '')
        . '<p class="documento__texto">' . e($d['descripcion']) . '</p>'
        . '</div>' . $acciones . '</li>';
}

function generar_marco(string $pagina): string
{
    $datos = leer_datos('marco.json');
    $ficha = '';
    foreach ($datos['ficha'] as $f) {
        $ficha .= '<div class="ficha__fila"><dt>' . e($f['campo']) . '</dt>'
            . ($f['valor'] ? '<dd>' . e($f['valor']) . '</dd>' : '<dd class="ficha__pendiente">Por confirmar</dd>')
            . '</div>';
    }
    // La ficha legal solo se muestra cuando "mostrar_ficha" es true (sus datos los confirma la clienta).
    $con_ficha = (bool) campo($datos, 'mostrar_ficha', false);
    $indice = $con_ficha ? '<li><a href="#ficha">Ficha institucional</a></li>' : '';
    $grupos = '';
    $total = 0;
    foreach ($datos['grupos'] as $g) {
        $indice .= '<li><a href="#' . $g['clave'] . '">' . e($g['titulo']) . '</a></li>';
        $docs = '';
        foreach ($g['documentos'] as $d) {
            $docs .= documento_marco($d);
            $total += lleno($d, 'archivo') ? 1 : 0;
        }
        $grupos .= '<section class="marco__seccion" id="' . $g['clave'] . '" aria-labelledby="titulo-' . $g['clave'] . '">'
            . '<h2 class="marco__subtitulo" id="titulo-' . $g['clave'] . '">' . e($g['titulo']) . '</h2>'
            . '<ul class="documentos">' . $docs . '</ul>'
            . '</section>';
    }
    $indice .= '<li><a href="#solicitudes">Solicitudes de información</a></li>';
    $correo = null;
    foreach ($datos['ficha'] as $f) {
        if ($correo === null && strpos(mb_strtolower($f['campo'], 'UTF-8'), 'correo') !== false && $f['valor']) {
            $correo = $f['valor'];
        }
    }
    $correo = $correo ?? leer_datos('contacto.json')['correo'];
    $intro = '';
    foreach ($datos['intro'] as $t) {
        $intro .= '<p class="marco__intro">' . e($t) . '</p>';
    }
    [$inicio, $fin] = marcas('marco');
    return $inicio
        . '<div class="marco">'
        . '<header class="marco__portada"><div class="marco__portada-contenido">'
        . '<p class="marco__antetitulo">Transparencia y rendición de cuentas</p>'
        . '<h1 class="marco__titulo">Marco institucional</h1>'
        . $intro
        . '<div class="marco__acciones">'
        . '<a class="marco__boton marco__boton--principal" href="' . e($datos['carpeta']) . '" download>'
        . ICONO_DESCARGAR . '<span>Descargar carpeta institucional completa'
        . '<small>' . peso_archivo($datos['carpeta']) . ' · ' . $total . ' documentos</small></span></a>'
        . (aviso_publicado() ? '<a class="marco__boton" href="aviso-de-privacidad.html">Aviso de privacidad</a>' : '')
        . '</div>'
        . '<p class="marco__actualizado">Última actualización: <time datetime="' . $datos['actualizado'] . '">'
        . fecha_larga($datos['actualizado']) . '</time></p>'
        . '</div></header>'
        . '<div class="marco__cuerpo">'
        . '<aside class="marco__indice"><nav aria-label="Contenido de esta página" data-marco-indice>'
        . '<p class="marco__indice-titulo">En esta página</p><ol>' . $indice . '</ol></nav></aside>'
        . '<div class="marco__contenido">'
        . ($con_ficha ? '<section class="marco__seccion" id="ficha" aria-labelledby="titulo-ficha">'
            . '<h2 class="marco__subtitulo" id="titulo-ficha">Ficha institucional</h2>'
            . '<dl class="ficha">' . $ficha . '</dl></section>' : '')
        . $grupos
        . '<section class="marco__seccion marco__solicitudes" id="solicitudes" aria-labelledby="titulo-solicitudes">'
        . '<h2 class="marco__subtitulo" id="titulo-solicitudes">Solicitudes de información</h2>'
        . '<p>' . e($datos['contacto']) . '</p>'
        . '<a class="marco__correo" href="mailto:' . e($correo) . '">' . ICONO_CORREO . e($correo) . '</a>'
        . '</section>'
        . '</div></div></div>'
        . $fin;
}

function insertar_marco(string $s, string $pagina): string
{
    if ($pagina !== 'marco-institucional.html') {
        return $s;
    }
    $s = poner_titulo($s, 'Marco institucional | CAPDES, A.C.');
    return reemplazar_seccion($s, 'marco', generar_marco($pagina), $pagina);
}

function es_generada(string $ruta): bool
{
    $texto = (string) @file_get_contents($ruta);
    foreach (array_merge([GENERADOR], GENERADORES_ANTERIORES) as $marca) {
        if (strpos($texto, $marca) !== false) {
            return true;
        }
    }
    return false;
}

function generar_aviso(array &$registro): void
{
    $datos = leer_datos('aviso-privacidad.json');
    $pagina = 'aviso-de-privacidad.html';
    $ruta = raiz_sitio() . '/' . $pagina;
    if (!campo($datos, 'publicar', false)) {
        if (is_file($ruta) && es_generada($ruta)) {
            unlink($ruta);
            $registro[] = "✗ $pagina (no publicado: \"publicar\" es false en datos/aviso-privacidad.json)";
        }
        return;
    }
    $secciones = '';
    foreach ($datos['secciones'] as $i => $sec) {
        $partes = '<h2>' . ($i + 1) . '. ' . e($sec['titulo']) . '</h2>';
        foreach (campo($sec, 'parrafos', []) as $t) {
            $partes .= '<p>' . e($t) . '</p>';
        }
        if (lleno($sec, 'lista')) {
            $partes .= '<ul>' . implode('', array_map(fn($t) => '<li>' . e($t) . '</li>', $sec['lista'])) . '</ul>';
        }
        foreach (campo($sec, 'parrafos_finales', []) as $t) {
            $partes .= '<p>' . e($t) . '</p>';
        }
        $secciones .= '<section class="legal__seccion">' . $partes . '</section>';
    }
    $revision = lleno($datos, 'en_revision')
        ? '<p class="legal__revision" role="note"><strong>Borrador en revisión.</strong> Este aviso está '
            . 'pendiente de revisión legal y de completar los datos del responsable; no es la versión definitiva.</p>'
        : '';
    $cuerpo = '<main class="entrada legal">'
        . '<article class="entrada__articulo">'
        . '<header class="entrada__encabezado">'
        . '<nav class="entrada__migas" aria-label="Ruta de navegación"><a href="index.html">Inicio</a>'
        . '<span aria-hidden="true">/</span><a href="marco-institucional.html">Marco institucional</a></nav>'
        . '<h1 class="entrada__titulo">Aviso de privacidad</h1>'
        . '<p class="entrada__meta">Última actualización: <time datetime="' . $datos['actualizado'] . '">'
        . fecha_larga($datos['actualizado']) . '</time></p>'
        . '</header>'
        . $revision
        . '<div class="legal__contenido">' . $secciones . '</div>'
        . '<p class="legal__relacionado">Consulta también nuestra '
        . '<a href="documentos/polpdpys-008-politica-proteccion-de-datos.pdf" target="_blank" rel="noopener">'
        . 'Política de Protección de Datos Personales y Salvaguarda</a> en el '
        . '<a href="marco-institucional.html#marco-normativo">Marco institucional</a>.</p>'
        . '</article></main>';
    escribir($ruta, pagina_limpia($pagina, 'Aviso de privacidad | CAPDES, A.C.',
        'Cómo CAPDES, A.C. trata y protege los datos personales que recibe a través de este sitio.',
        $cuerpo, [], '', 'pagina-entrada'));
    $registro[] = "✓ $pagina";
}

// ───────────── Blog (datos/blog.json) ─────────────
// El listado va en blog.html y cada artículo en su propia página, blog/<slug>.html,
// para que tenga su enlace para compartir y Google lo indexe por separado.

function leer_blog(): array
{
    $datos = leer_datos('blog.json');
    $vistos = [];
    foreach ($datos['entradas'] as $en) {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $en['slug'])) {
            throw new RuntimeException('datos/blog.json: slug "' . $en['slug'] . '" no válido (solo minúsculas, números y guiones)');
        }
        if (isset($vistos[$en['slug']])) {
            throw new RuntimeException('datos/blog.json: el slug "' . $en['slug'] . '" está repetido');
        }
        $vistos[$en['slug']] = true;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $en['fecha'])) {
            throw new RuntimeException('datos/blog.json: fecha "' . $en['fecha'] . '" no válida (usa AAAA-MM-DD)');
        }
    }
    // Más recientes primero (usort conserva el orden de las que tienen la misma fecha).
    usort($datos['entradas'], fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
    return $datos;
}

function minutos_lectura(array $entrada): int
{
    $palabras = 0;
    foreach ($entrada['contenido'] as $p) {
        $texto = trim(str_replace('*', '', $p));
        $palabras += $texto === '' ? 0 : count(preg_split('/(*UCP)\s+/u', $texto));
    }
    return max(1, (int) ceil($palabras / 200));
}

/** Párrafo con **negritas** y *cursivas* (lo único que se permite en datos/blog.json). */
function texto_con_formato(string $parrafo): string
{
    $t = e($parrafo);
    $t = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $t);
    return preg_replace('/\*(.+?)\*/u', '<em>$1</em>', $t);
}

function tarjeta_blog(array $en, string $pagina, string $nivel = 'h2', bool $destacada = false): string
{
    $r = raiz($pagina);
    $clase = $destacada ? 'blog__tarjeta blog__tarjeta--destacada' : 'blog__tarjeta';
    $carga = $destacada ? '' : ' loading="lazy"';
    return '<li class="' . $clase . '" data-categoria="' . e($en['categoria']) . '">'
        . '<a class="blog__enlace" href="' . $r . 'blog/' . $en['slug'] . '.html">'
        . '<span class="blog__portada"><img src="' . $r . e($en['imagen']) . '" '
        . 'alt="' . e(campo($en, 'descripcion_imagen') ?: '') . '"' . $carga . '></span>'
        . '<span class="blog__cuerpo">'
        . '<span class="blog__meta"><span class="blog__categoria">' . e($en['categoria']) . '</span>'
        . '<time datetime="' . $en['fecha'] . '">' . fecha_larga($en['fecha']) . '</time></span>'
        . "<$nivel class=\"blog__nombre\">" . e($en['titulo']) . "</$nivel>"
        . '<span class="blog__resumen">' . e($en['resumen']) . '</span>'
        . '<span class="blog__leer">Leer artículo' . ICONO_FLECHA_DERECHA . '</span>'
        . '</span></a></li>';
}

function generar_listado_blog(string $pagina): string
{
    $datos = leer_blog();
    $entradas = $datos['entradas'];
    $filtros = '<button type="button" class="blog__filtro" aria-pressed="true" data-filtro="">Todas</button>';
    foreach ($datos['categorias'] as $c) {
        if (in_array($c, array_column($entradas, 'categoria'), true)) {
            $filtros .= '<button type="button" class="blog__filtro" aria-pressed="false" data-filtro="' . e($c) . '">'
                . e($c) . '</button>';
        }
    }
    $tarjetas = '';
    foreach ($entradas as $i => $en) {
        $tarjetas .= tarjeta_blog($en, $pagina, 'h2', $i === 0);
    }
    [$inicio, $fin] = marcas('blog');
    return $inicio
        . '<section id="zapxkx" class="blog" aria-labelledby="blog-titulo">'
        . '<div class="blog__contenido">'
        . '<header class="blog__encabezado">'
        . '<h1 class="blog__titulo" id="blog-titulo">Nuestro blog</h1>'
        . '</header>'
        . '<div class="blog__filtros" role="group" aria-label="Filtrar artículos por categoría" data-blog-filtros>' . $filtros . '</div>'
        . '<ul class="blog__lista blog__lista--todas" data-blog-lista>' . $tarjetas . '</ul>'
        . '<p class="blog__vacio" data-blog-vacio hidden>Aún no hay publicaciones en esta categoría.</p>'
        . '</div>'
        . '</section>'
        . '<section class="eventos eventos--blog" aria-labelledby="blog-eventos-titulo">'
        . '<div class="eventos__contenido">'
        . '<header class="eventos__encabezado">'
        . '<p class="eventos__antetitulo">• Agenda</p>'
        . '<h2 class="eventos__titulo" id="blog-eventos-titulo">Próximos <span>eventos</span></h2>'
        . '</header>'
        . '<ul class="eventos__lista" data-eventos>' . generar_eventos() . '</ul>'
        . '</div>'
        . '</section>'
        . '<section class="suscribir" aria-labelledby="suscribir-titulo">'
        . '<div class="suscribir__tarjeta">'
        . '<div><h2 class="suscribir__titulo" id="suscribir-titulo">Recibe nuestras noticias</h2>'
        . '<p class="suscribir__texto">Déjanos tu correo y te avisamos de nuevas publicaciones, jornadas y eventos.</p></div>'
        . '<a class="suscribir__boton" href="#pie-correo" data-ir-suscripcion>Quiero suscribirme' . ICONO_FLECHA_DERECHA . '</a>'
        . '</div>'
        . '</section>'
        . $fin;
}

function insertar_blog(string $s, string $pagina): string
{
    if ($pagina !== 'blog.html') {
        return $s;
    }
    $s = poner_titulo($s, 'Nuestro blog | CAPDES, A.C.');
    return reemplazar_seccion($s, 'blog', generar_listado_blog($pagina), $pagina);
}

function relacionadas(array $entrada, array $entradas, int $cuantas = 3): array
{
    $misma = [];
    $resto = [];
    foreach ($entradas as $en) {
        if ($en['slug'] === $entrada['slug']) {
            continue;
        }
        if ($en['categoria'] === $entrada['categoria']) {
            $misma[] = $en;
        } else {
            $resto[] = $en;
        }
    }
    return array_slice(array_merge($misma, $resto), 0, $cuantas);
}

/** Página completa con menú y pie, sin el código de Hostinger (artículos, aviso de privacidad). */
function pagina_limpia(string $pagina, string $titulo, string $descripcion, string $cuerpo,
                       array $scripts = [], string $head_extra = '', string $clase_body = ''): string
{
    $r = raiz($pagina);
    $css = $r . 'css/renovacion.css?v=' . version('css/renovacion.css');
    $js = '';
    foreach (array_merge(['animaciones', 'menu', 'contador', 'pie'], $scripts) as $n) {
        $js .= '<script src="' . $r . 'js/' . $n . '.js?v=' . version("js/$n.js") . '"></script>';
    }
    $clase = $clase_body !== '' ? ' class="' . $clase_body . '"' : '';
    return "<!doctype html>\n" . '<html lang="es" data-raiz="' . $r . '"><head>'
        . '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . GENERADOR
        . '<title>' . $titulo . '</title>'
        . '<meta name="description" content="' . $descripcion . '">'
        . '<link rel="canonical" href="' . SITIO . $pagina . '">'
        . '<link rel="icon" href="' . $r . 'img/favicon.ico" sizes="any">'
        . '<link rel="icon" type="image/png" sizes="32x32" href="' . $r . 'img/favicon-32.png">'
        . '<link rel="apple-touch-icon" href="' . $r . 'img/apple-touch-icon.png">'
        . FUENTES
        . '<link rel="stylesheet" href="' . $r . 'css/main.css"><link rel="stylesheet" href="' . $css . '">'
        . $head_extra
        . '</head><body' . $clase . '>'
        . '<div class="top-blocks">' . generar_menu($pagina) . '</div>'
        . $cuerpo
        . generar_pie($pagina)
        . $js
        . "</body></html>\n";
}

function generar_pagina_entrada(array $en, array $entradas): string
{
    $pagina = 'blog/' . $en['slug'] . '.html';
    $r = raiz($pagina);
    $url = SITIO . $pagina;
    $imagen_abs = SITIO . $en['imagen'];
    $titulo = e($en['titulo']);
    $resumen = e($en['resumen']);
    $texto_compartir = codificar_url($en['titulo'] . ' ' . $url);
    $datos_ld = json_legible([
        '@context' => 'https://schema.org', '@type' => 'BlogPosting',
        'headline' => $en['titulo'], 'description' => $en['resumen'], 'image' => $imagen_abs,
        'datePublished' => $en['fecha'], 'mainEntityOfPage' => $url,
        'author' => ['@type' => 'Organization', 'name' => 'CAPDES, A.C.'],
        'publisher' => ['@type' => 'Organization', 'name' => 'CAPDES, A.C.'],
    ]);
    $contenido = '';
    foreach ($en['contenido'] as $p) {
        $contenido .= '<p>' . texto_con_formato($p) . '</p>';
    }
    $sugeridas = '';
    foreach (relacionadas($en, $entradas) as $x) {
        $sugeridas .= tarjeta_blog($x, $pagina, 'h3');
    }
    $head_extra = '<meta property="og:type" content="article"><meta property="og:site_name" content="CAPDES, A.C.">'
        . '<meta property="og:title" content="' . $titulo . '"><meta property="og:description" content="' . $resumen . '">'
        . '<meta property="og:url" content="' . $url . '"><meta property="og:image" content="' . $imagen_abs . '">'
        . '<meta property="article:published_time" content="' . $en['fecha'] . '">'
        . '<meta name="twitter:card" content="summary_large_image">'
        . '<script type="application/ld+json">' . $datos_ld . '</script>';
    $cuerpo = '<main class="entrada">'
        . '<article class="entrada__articulo">'
        . '<header class="entrada__encabezado">'
        . '<nav class="entrada__migas" aria-label="Ruta de navegación"><a href="' . $r . 'index.html">Inicio</a>'
        . '<span aria-hidden="true">/</span><a href="' . $r . 'blog.html">Blog</a>'
        . '<span aria-hidden="true">/</span><span>' . e($en['categoria']) . '</span></nav>'
        . '<p class="entrada__categoria">' . e($en['categoria']) . '</p>'
        . '<h1 class="entrada__titulo">' . $titulo . '</h1>'
        . '<p class="entrada__meta"><time datetime="' . $en['fecha'] . '">' . fecha_larga($en['fecha']) . '</time>'
        . '<span aria-hidden="true">·</span><span>' . minutos_lectura($en) . ' min de lectura</span></p>'
        . '</header>'
        . '<figure class="entrada__portada"><img src="' . $r . e($en['imagen']) . '" '
        . 'alt="' . e(campo($en, 'descripcion_imagen') ?: '') . '"></figure>'
        . '<div class="entrada__contenido">' . $contenido . '</div>'
        . '<div class="entrada__compartir">'
        . '<p class="entrada__compartir-titulo">Comparte este artículo</p>'
        . '<div class="entrada__botones">'
        . '<a class="entrada__boton entrada__boton--whatsapp" href="https://wa.me/?text=' . $texto_compartir . '" '
        . 'target="_blank" rel="noopener">' . ICONO_WHATSAPP . '<span>WhatsApp</span></a>'
        . '<a class="entrada__boton entrada__boton--facebook" href="https://www.facebook.com/sharer/sharer.php?u=' . codificar_url($url) . '" '
        . 'target="_blank" rel="noopener">' . ICONO_FACEBOOK . '<span>Facebook</span></a>'
        . '<button class="entrada__boton entrada__boton--copiar" type="button" data-copiar="' . $url . '">'
        . ICONO_COPIAR . '<span data-copiar-texto>Copiar enlace</span></button>'
        . '</div></div>'
        . '<aside class="entrada__invitacion" aria-labelledby="invitacion-titulo">'
        . '<h2 class="entrada__invitacion-titulo" id="invitacion-titulo">¿Quieres ser parte del cambio?</h2>'
        . '<p>Tu apoyo hace posibles nuestras jornadas, talleres y programas en las comunidades.</p>'
        . '<div class="entrada__invitacion-botones">'
        . '<a class="entrada__cta entrada__cta--donar" href="' . $r . 'index.html#donar">' . ICONO_CORAZON . 'Donar</a>'
        . '<a class="entrada__cta entrada__cta--voluntario" href="' . $r . 'contacto.html?motivo=voluntariado#formulario">Quiero ser voluntario</a>'
        . '</div></aside>'
        . '</article>'
        . ($sugeridas !== '' ? '<section class="entrada__relacionadas" aria-labelledby="relacionadas-titulo">'
            . '<h2 class="entrada__relacionadas-titulo" id="relacionadas-titulo">Sigue leyendo</h2>'
            . '<ul class="blog__lista">' . $sugeridas . '</ul></section>' : '')
        . '</main>';
    return pagina_limpia($pagina, $titulo . ' | Blog de CAPDES, A.C.', $resumen, $cuerpo,
        ['blog'], $head_extra, 'pagina-entrada');
}

function generar_entradas(array &$registro): void
{
    $datos = leer_blog();
    $carpeta = raiz_sitio() . '/blog';
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0755, true);
    }
    $vigentes = [];
    foreach ($datos['entradas'] as $en) {
        $nombre = $en['slug'] . '.html';
        escribir("$carpeta/$nombre", generar_pagina_entrada($en, $datos['entradas']));
        $vigentes[$nombre] = true;
        $registro[] = "✓ blog/$nombre";
    }
    // Borra las páginas de artículos que ya no están en datos/blog.json (solo las generadas aquí).
    foreach (glob("$carpeta/*.html") ?: [] as $ruta) {
        if (!isset($vigentes[basename($ruta)]) && es_generada($ruta)) {
            unlink($ruta);
            $registro[] = '✗ blog/' . basename($ruta) . ' (ya no está en datos/blog.json)';
        }
    }
}

// ───────────── Páginas ─────────────

function enlazar_recursos(string $s, string $pagina): string
{
    $s = preg_replace('~<link rel="stylesheet" href="css/renovacion\.css(\?v=[^"]*)?">~',
        '<link rel="stylesheet" href="css/renovacion.css?v=' . version('css/renovacion.css') . '">', $s);
    foreach (array_merge(['animaciones', 'menu', 'contador', 'pie'], JS_POR_PAGINA[$pagina] ?? []) as $nombre) {
        $js = '<script src="js/' . $nombre . '.js?v=' . version("js/$nombre.js") . '"></script>';
        if (strpos($s, '<script src="js/' . $nombre . '.js') !== false) {
            $s = preg_replace('~<script src="js/' . $nombre . '\.js(\?v=[^"]*)?"></script>~', $js, $s);
        } else {
            $s = preg_replace('~</body></html>~', $js . '</body></html>', $s, 1);
        }
    }
    return $s;
}

function insertar_anclas(string $s, string $pagina): string
{
    foreach (ANCLAS[$pagina] ?? [] as [$ancla, $seccion]) {
        if (strpos($s, 'id="' . $ancla . '"') !== false) {
            continue;
        }
        $marca = '<section id="' . $seccion . '"';
        $p = strpos($s, $marca);
        if ($p === false) {
            throw new RuntimeException("$pagina: no encontré la sección $seccion para el ancla #$ancla");
        }
        $s = substr_replace($s, '<span id="' . $ancla . '" class="ancla"></span>', $p, 0);
    }
    return $s;
}

/** Escribe el archivo de forma segura: primero a un temporal y luego lo reemplaza. */
function escribir(string $ruta, string $contenido): void
{
    $temporal = $ruta . '.tmp-' . getmypid();
    if (file_put_contents($temporal, $contenido) === false || !rename($temporal, $ruta)) {
        @unlink($temporal);
        throw new RuntimeException('No pude escribir ' . substr($ruta, strlen(raiz_sitio()) + 1));
    }
}

function aplicar(string $pagina): string
{
    $ruta = raiz_sitio() . '/' . $pagina;
    $s = @file_get_contents($ruta);
    if ($s === false) {
        throw new RuntimeException("No encontré la página $pagina");
    }
    $s = reemplazar_seccion($s, 'menu', generar_menu($pagina), $pagina);
    $s = insertar_anclas($s, $pagina);
    $s = reemplazar_seccion($s, 'pie', generar_pie($pagina), $pagina);
    $s = insertar_inicio($s, $pagina);
    $s = insertar_blog($s, $pagina);
    $s = insertar_programas($s, $pagina);
    $s = insertar_marco($s, $pagina);
    $s = insertar_contacto($s, $pagina);
    $s = insertar_quienes($s, $pagina);
    $s = enlazar_recursos($s, $pagina);
    return $s;
}

/**
 * Regenera todo el sitio. Primero arma todas las páginas en memoria y solo si ninguna
 * falló las escribe, para que un error en los datos no deje el sitio a medias.
 * Devuelve la lista de lo que hizo; si algo está mal lanza RuntimeException con el motivo.
 */
function generar_todo(): array
{
    $paginas = [];
    foreach (array_keys(ACTIVA) as $pagina) {
        $paginas[$pagina] = aplicar($pagina);
    }
    leer_blog(); // valida el blog antes de escribir nada
    $registro = [];
    foreach ($paginas as $pagina => $contenido) {
        escribir(raiz_sitio() . '/' . $pagina, $contenido);
        $registro[] = "✓ $pagina";
    }
    generar_entradas($registro);
    generar_aviso($registro);
    return $registro;
}

// Desde la terminal: php herramientas/generar.php
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        foreach (generar_todo() as $linea) {
            echo $linea, PHP_EOL;
        }
    } catch (RuntimeException $error) {
        fwrite(STDERR, 'Error: ' . $error->getMessage() . PHP_EOL);
        exit(1);
    }
}
