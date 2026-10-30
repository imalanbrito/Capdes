"""Genera las partes compartidas (menú de navegación y pie de página con el contador
de visitas) y las inserta en todas las páginas.

La estructura del menú vive solo aquí. Para cambiarla, edita MENU y corre:

    python3 herramientas/menu.py

El script también:
- crea anclas legibles (p. ej. programas.html#ejes) antes de las secciones de Hostinger,
- enlaza css/renovacion.css y js/menu.js en cada página,
- arma las diapositivas del carrusel de jornadas (inicio) desde datos/jornadas.json,
- arma la página de Programas desde datos/programas.json,
- arma la página de Contacto (datos/contacto.json),
- arma el Marco institucional (datos/marco.json) y el Aviso de privacidad (datos/aviso-privacidad.json),
- arma el blog desde datos/blog.json: el listado (blog.html) y una página por artículo (blog/<slug>.html),
- arma "Nuestro impacto", los eventos, los donativos y los aliados (inicio) desde
  datos/impacto.json, datos/eventos.json, datos/donar.json y datos/aliados.json,
- pone a los enlaces de CSS/JS propios un ?v= según su contenido, para que el
  navegador no use una copia vieja en caché.
"""

import hashlib
import math
from urllib.parse import quote
from datetime import date
import html
import json
import re
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent

# Los botones "Donar" llevan a la sección de donativos del inicio (PayPal y GoFundMe,
# configurables en datos/donar.json).
DONAR_URL = "index.html#donar"

# (texto, enlace, [(texto, enlace), ...]). Contacto siempre al final.
MENU = [
    ("Inicio", "index.html", [
        ("Nuestro blog", "blog.html"),
        ("Eventos y actividades", "index.html#eventos"),
    ]),
    ("Quiénes somos", "quienes-somos.html", [
        ("Acerca de nosotros", "quienes-somos.html#acerca"),
        ("Misión, visión y valores", "quienes-somos.html#mision-vision-valores"),
        ("Noticias y eventos", "blog.html"),
    ]),
    ("Programas", "programas.html", [
        ("Nuestros programas", "programas.html#programas"),
        ("Jornadas realizadas", "programas.html#jornadas"),
        ("Agenda 2030", "programas.html#ods"),
    ]),
    ("Marco institucional", "marco-institucional.html", [
        ("Identidad institucional", "marco-institucional.html#identidad"),
        ("Planeación estratégica", "marco-institucional.html#planeacion"),
        ("Marco normativo y políticas", "marco-institucional.html#marco-normativo"),
        ("Informes y rendición de cuentas", "marco-institucional.html#informes"),
    ]),
    ("Contacto", "contacto.html", [
        ("Escríbenos", "contacto.html#formulario"),
        ("Voluntariado y servicio social", "contacto.html?motivo=voluntariado#formulario"),
        ("Ubicación", "contacto.html#ubicacion"),
    ]),
]

# Pestaña principal que se marca como activa en cada página.
ACTIVA = {
    "index.html": "Inicio",
    "blog.html": "Inicio",
    "quienes-somos.html": "Quiénes somos",
    "programas.html": "Programas",
    "marco-institucional.html": "Marco institucional",
    "contacto.html": "Contacto",
}

# página -> [(ancla legible, id de la sección de Hostinger)]
ANCLAS = {
    "index.html": [("eventos", "zXDtLi"), ("donar", "zwZ-vA"), ("aliados", "zzHakD")],
    "quienes-somos.html": [("acerca", "zYUchx"), ("mision-vision-valores", "zB7aPl")],
}

# Scripts propios que solo carga una página (además de menu.js y contador.js).
JS_POR_PAGINA = {
    "index.html": ["inicio"],
    "blog.html": ["inicio", "blog"],
    "programas.html": ["programas"],
    "marco-institucional.html": ["marco"],
    "contacto.html": ["contacto"],
}

# Dirección pública del sitio: se usa en los enlaces para compartir y en las vistas previas.
SITIO = "https://capdes.org/"

LOGO = ("https://assets.zyrosite.com/cdn-cgi/image/format=auto,w=400,fit=crop/"
        "AQEyaZVx1MiRWvDV/logocapdesa.c-yfb19wu0yFQLv0Rh.png")

ICONO_FLECHA = ('<svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true">'
                '<path d="M2.5 4.5 6 8l3.5-3.5" fill="none" stroke="currentColor" '
                'stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>')

ICONO_CORAZON = ('<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">'
                 '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 '
                 '7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" '
                 'fill="currentColor"/></svg>')

ICONO_FACEBOOK = ('<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" '
                  'd="M24 12.0726C24 5.44354 18.629 0.0725708 12 0.0725708C5.37097 0.0725708 0 5.44354 0 '
                  '12.0726C0 18.0619 4.38823 23.0264 10.125 23.9274V15.5414H7.07661V12.0726H10.125V9.4287C10.125 '
                  '6.42144 11.9153 4.76031 14.6574 4.76031C15.9706 4.76031 17.3439 4.99451 17.3439 '
                  '4.99451V7.94612H15.8303C14.34 7.94612 13.875 8.87128 13.875 9.82015V12.0726H17.2031L16.6708 '
                  '15.5414H13.875V23.9274C19.6118 23.0264 24 18.0619 24 12.0726Z"/></svg>')

ICONO_INSTAGRAM = ('<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" '
                   'd="M12.0027 5.84808C8.59743 5.84808 5.85075 8.59477 5.85075 12C5.85075 15.4053 8.59743 '
                   '18.1519 12.0027 18.1519C15.4079 18.1519 18.1546 15.4053 18.1546 12C18.1546 8.59477 15.4079 '
                   '5.84808 12.0027 5.84808ZM12.0027 15.9996C9.80212 15.9996 8.00312 14.2059 8.00312 12C8.00312 '
                   '9.7941 9.79677 8.00046 12.0027 8.00046C14.2086 8.00046 16.0022 9.7941 16.0022 12C16.0022 '
                   '14.2059 14.2032 15.9996 12.0027 15.9996ZM19.8412 5.59644C19.8412 6.39421 19.1987 7.03135 '
                   '18.4062 7.03135C17.6085 7.03135 16.9713 6.38885 16.9713 5.59644C16.9713 4.80402 17.6138 '
                   '4.16153 18.4062 4.16153C19.1987 4.16153 19.8412 4.80402 19.8412 5.59644ZM23.9157 '
                   '7.05277C23.8247 5.13063 23.3856 3.42801 21.9775 2.02522C20.5747 0.622429 18.8721 0.183388 '
                   '16.9499 0.0870135C14.9689 -0.0254238 9.03112 -0.0254238 7.05008 0.0870135C5.1333 0.178034 '
                   '3.43068 0.617075 2.02253 2.01986C0.614389 3.42265 0.180703 5.12527 0.0843279 7.04742C-0.0281093 '
                   '9.02845 -0.0281093 14.9662 0.0843279 16.9472C0.175349 18.8694 0.614389 20.572 2.02253 '
                   '21.9748C3.43068 23.3776 5.12794 23.8166 7.05008 23.913C9.03112 24.0254 14.9689 24.0254 '
                   '16.9499 23.913C18.8721 23.822 20.5747 23.3829 21.9775 21.9748C23.3803 20.572 23.8193 18.8694 '
                   '23.9157 16.9472C24.0281 14.9662 24.0281 9.03381 23.9157 7.05277ZM21.3564 19.0728C20.9388 '
                   '20.1223 20.1303 20.9307 19.0755 21.3537C17.496 21.9802 13.7481 21.8356 12.0027 '
                   '21.8356C10.2572 21.8356 6.50396 21.9748 4.92984 21.3537C3.88042 20.9361 3.07195 20.1276 '
                   '2.64897 19.0728C2.02253 17.4934 2.16709 13.7455 2.16709 12C2.16709 10.2546 2.02789 6.50129 '
                   '2.64897 4.92717C3.06659 3.87776 3.87507 3.06928 4.92984 2.6463C6.50931 2.01986 10.2572 '
                   '2.16443 12.0027 2.16443C13.7481 2.16443 17.5014 2.02522 19.0755 2.6463C20.1249 3.06392 '
                   '20.9334 3.8724 21.3564 4.92717C21.9828 6.50665 21.8383 10.2546 21.8383 12C21.8383 13.7455 '
                   '21.9828 17.4987 21.3564 19.0728Z"/></svg>')

INICIO_MARCA = "<!-- menu:inicio -->"
FIN_MARCA = "<!-- menu:fin -->"

PIE_INICIO = "<!-- pie:inicio -->"
PIE_FIN = "<!-- pie:fin -->"

ICONO_OJO = ('<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
             'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
             '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>')


def es_externo(url):
    return url.startswith("http")


def raiz(pagina):
    """Prefijo para llegar a la raíz del sitio desde la página (p. ej. "../" en blog/x.html)."""
    return "../" * pagina.count("/")


def pestana_activa(pagina):
    if pagina.startswith("blog/"):
        return ACTIVA["blog.html"]
    return ACTIVA.get(pagina)


def atributos_enlace(url, pagina):
    destino = url if es_externo(url) else raiz(pagina) + url
    attrs = f'href="{html.escape(destino)}"'
    if es_externo(url):
        attrs += ' target="_blank" rel="noopener"'
    elif url == pagina:
        attrs += ' aria-current="page"'
    return attrs


def slug(texto):
    return re.sub(r"[^a-z0-9]+", "-", texto.lower().translate(str.maketrans("áéíóúñ", "aeioun"))).strip("-")


ICONOS_REDES = {"facebook": ICONO_FACEBOOK, "instagram": ICONO_INSTAGRAM}

ICONO_ENLACE = ('<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
                'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>'
                '<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>')


def enlaces_redes():
    """Enlaces a redes sociales según datos/contacto.json (el mismo HTML que arma js/pie.js)."""
    enlaces = []
    for r in leer_datos("contacto.json")["redes"]:
        red = r["red"].strip().lower()
        nombre = red.capitalize()
        enlaces.append(f'<a href="{html.escape(r["enlace"])}" target="_blank" rel="noopener" '
                       f'aria-label="{html.escape(nombre)} de CAPDES">{ICONOS_REDES.get(red, ICONO_ENLACE)}</a>')
    return "".join(enlaces)


def redes(clase):
    return f'<div class="{clase}">{enlaces_redes()}</div>'


def generar_items(pagina, sufijo):
    items = []
    for texto, url, subs in MENU:
        id_sub = f"submenu-{slug(texto)}-{sufijo}"
        activo = pestana_activa(pagina) == texto
        clase = "menu__item menu__item--activo" if activo else "menu__item"
        enlace_attrs = atributos_enlace(url, pagina)
        if activo and 'aria-current' not in enlace_attrs:
            enlace_attrs += ' aria-current="true"'
        sub_items = "".join(
            f'<li><a {atributos_enlace(s_url, pagina)}>{html.escape(s_texto)}</a></li>'
            for s_texto, s_url in subs
        )
        items.append(
            f'<li class="{clase}">'
            f'<div class="menu__fila">'
            f'<a class="menu__enlace" {enlace_attrs}>{html.escape(texto)}</a>'
            f'<button class="menu__abrir" type="button" aria-expanded="false" aria-controls="{id_sub}" '
            f'aria-label="Mostrar opciones de {html.escape(texto)}">{ICONO_FLECHA}</button>'
            f'</div>'
            f'<ul class="menu__sub" id="{id_sub}">{sub_items}</ul>'
            f'</li>'
        )
    return "".join(items)


def generar_menu(pagina):
    # El menú tiene dos versiones de la misma lista: las pestañas de la barra
    # (header completo, arriba de la página) y el panel de la hamburguesa
    # (header compacto, al hacer scroll o en celular). js/menu.js alterna entre ambas.
    return (
        f'{INICIO_MARCA}'
        f'<header class="menu" data-menu>'
        f'<div class="menu__barra">'
        f'<a class="menu__logo" href="{raiz(pagina)}index.html" aria-label="CAPDES, A.C. – Inicio">'
        f'<img src="{LOGO}" alt="CAPDES, A.C." width="264" height="143"></a>'
        f'<div class="menu__pestanas"><div class="menu__pestanas-interior">'
        f'<nav class="menu__nav" aria-label="Menú principal">'
        f'<ul class="menu__lista">{generar_items(pagina, "barra")}</ul>'
        f'</nav>'
        f'</div></div>'
        f'<div class="menu__acciones">'
        f'<a class="menu__donar" {atributos_enlace(DONAR_URL, pagina)}>{ICONO_CORAZON}<span>Donar</span></a>'
        f'{redes("menu__redes")}'
        f'<button class="menu__hamburguesa" type="button" aria-expanded="false" aria-controls="menu-panel" '
        f'aria-label="Abrir menú"><span></span><span></span><span></span></button>'
        f'</div>'
        f'</div>'
        f'<nav class="menu__panel" id="menu-panel" aria-label="Menú principal">'
        f'<ul class="menu__lista">{generar_items(pagina, "panel")}</ul>'
        f'{redes("menu__redes menu__redes--panel")}'
        f'</nav>'
        f'</header>'
        f'{FIN_MARCA}'
    )


ICONO_TELEFONO = ('<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
                  'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                  '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2'
                  ' 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3'
                  'a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>')
ICONO_CORREO = ('<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
                'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>')
ICONO_MENSAJE = ('<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
                 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                 '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.6 8.6 0 0 1-3.8-.9L3 21l1.9-5.2A8.4 8.4 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5z"/></svg>')
ICONO_ARRIBA = ('<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
                'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
                '<path d="M12 19V5M6 11l6-6 6 6"/></svg>')


def generar_contador():
    return (f'<p class="contador" data-contador aria-live="polite">'
            f'<span class="contador__icono">{ICONO_OJO}</span>'
            f'<span class="contador__numero">…</span>'
            f'<span class="contador__texto">visitas a nuestro sitio</span>'
            f'</p>')


def lista_contacto():
    """Lista de contacto del pie según datos/contacto.json (el mismo HTML que arma js/pie.js)."""
    c = leer_datos("contacto.json")
    items = []
    if c.get("telefono"):
        tel = re.sub(r"[^\d+]", "", c["telefono"])
        items.append(f'<li><a href="tel:{tel}"><span class="pie__icono">{ICONO_TELEFONO}</span>'
                     f'<span>{html.escape(c["telefono"])}</span></a></li>')
    if c.get("whatsapp"):
        wa = re.sub(r"\D", "", c["whatsapp"])
        items.append(f'<li><a href="https://wa.me/{wa}" target="_blank" rel="noopener">'
                     f'<span class="pie__icono">{ICONO_MENSAJE}</span><span>WhatsApp</span></a></li>')
    if c.get("correo"):
        items.append(f'<li><a href="mailto:{html.escape(c["correo"])}"><span class="pie__icono">{ICONO_CORREO}</span>'
                     f'<span>{html.escape(c["correo"])}</span></a></li>')
    return "".join(items)


def generar_pie(pagina):
    c = leer_datos("contacto.json")
    return (
        f'{PIE_INICIO}'
        f'<footer class="pie">'
        f'<div class="pie__contenido">'
        f'<div class="pie__marca">'
        f'<a class="pie__logo" href="{raiz(pagina)}index.html" aria-label="CAPDES, A.C. – Inicio">'
        f'<img src="{LOGO}" alt="CAPDES, A.C." width="264" height="143" loading="lazy"></a>'
        f'<p class="pie__nombre" data-pie-nombre>{html.escape(c["nombre_completo"])}</p>'
        f'<div class="pie__redes" data-pie-redes>{enlaces_redes()}</div>'
        f'{generar_contador()}'
        f'</div>'
        f'<div class="pie__columna">'
        f'<h2 class="pie__titulo">Contáctanos</h2><ul class="pie__contacto" data-pie-contacto>{lista_contacto()}</ul>'
        f'</div>'
        f'<div class="pie__columna">'
        f'<h2 class="pie__titulo">Sé parte del cambio</h2>'
        f'<p class="pie__texto">Déjanos tu correo y te contamos sobre nuestras jornadas, talleres y eventos.</p>'
        f'<form class="pie__formulario" data-pie-formulario>'
        f'<label class="solo-lectores" for="pie-correo">Tu correo electrónico</label>'
        f'<input id="pie-correo" type="email" name="correo" placeholder="tu@correo.com" autocomplete="email" required>'
        f'<button type="submit">Quiero recibir noticias</button>'
        f'</form>'
        f'<p class="pie__aviso" data-pie-aviso role="status"></p>'
        + (f'<p class="pie__privacidad">Al suscribirte aceptas nuestro '
           f'<a href="{raiz(pagina)}aviso-de-privacidad.html">aviso de privacidad</a>.</p>' if aviso_publicado() else "")
        + f'</div>'
        f'</div>'
        f'<div class="pie__base">'
        f'<p>© <span data-pie-anio>{date.today().year}</span> CAPDES, A.C. Todos los derechos reservados.</p>'
        f'<nav class="pie__legal" aria-label="Información legal">'
        + (f'<a {atributos_enlace("aviso-de-privacidad.html", pagina)}>Aviso de privacidad</a>' if aviso_publicado() else "")
        + f'<a {atributos_enlace("marco-institucional.html", pagina)}>Transparencia</a>'
        f'</nav>'
        f'<a class="pie__arriba" href="#">Volver arriba{ICONO_ARRIBA}</a>'
        f'</div>'
        f'</footer>'
        f'{PIE_FIN}'
    )


def insertar_pie(s, pagina):
    pie = generar_pie(pagina)
    if PIE_INICIO in s:
        return re.sub(re.escape(PIE_INICIO) + ".*?" + re.escape(PIE_FIN), lambda _: pie, s, count=1, flags=re.S)
    # Primera vez: reemplaza la sección del pie de Hostinger ("Síguenos en nuestras redes…").
    s, n = re.subn(r'<section id="zSiG-O".*?</section>', lambda _: pie, s, count=1, flags=re.S)
    if n != 1:
        raise SystemExit(f"{pagina}: no encontré la sección del pie")
    return s


SLIDES_INICIO = "<!-- jornadas-slides:inicio -->"
SLIDES_FIN = "<!-- jornadas-slides:fin -->"


def generar_slides():
    """Diapositivas del carrusel según datos/jornadas.json (el mismo HTML que arma js/inicio.js)."""
    datos = json.loads((RAIZ / "datos/jornadas.json").read_text(encoding="utf-8"))
    slides = []
    for i, j in enumerate(datos["jornadas"]):
        clases = "jornadas__slide"
        if j.get("llenar_marco"):
            clases += " jornadas__slide--llena"
        if i == 0:
            clases += " jornadas__slide--activa"
        carga = "" if i == 0 else ' loading="lazy"'
        src = html.escape(j["imagen"])
        alt = html.escape(j.get("descripcion") or j.get("titulo") or "")
        partes = []
        if not j.get("llenar_marco"):
            partes.append(f'<img class="jornadas__fondo" src="{src}" alt="" aria-hidden="true"{carga}>')
        partes.append(f'<img class="jornadas__foto" src="{src}" alt="{alt}"{carga}>')
        if j.get("titulo"):
            partes.append(f'<figcaption class="jornadas__titulo">{html.escape(j["titulo"])}</figcaption>')
        slides.append(f'<figure class="{clases}">{"".join(partes)}</figure>')
    return SLIDES_INICIO + "".join(slides) + SLIDES_FIN


def insertar_slides(s, pagina):
    if pagina != "index.html":
        return s
    slides = generar_slides()
    if SLIDES_INICIO in s:
        return re.sub(re.escape(SLIDES_INICIO) + ".*?" + re.escape(SLIDES_FIN), lambda _: slides, s, count=1, flags=re.S)
    # Primera vez: reemplaza las diapositivas escritas a mano dentro de la pista.
    s, n = re.subn(r'(<div class="jornadas__pista">).*?(</div></div><button class="jornadas__flecha)',
                   lambda m: m.group(1) + slides + m.group(2), s, count=1, flags=re.S)
    if n != 1:
        raise SystemExit(f"{pagina}: no encontré la pista del carrusel de jornadas")
    return s


MESES = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio",
         "agosto", "septiembre", "octubre", "noviembre", "diciembre"]

ICONO_UBICACION = ('<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
                   'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                   '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/>'
                   '<circle cx="12" cy="9.5" r="2.5"/></svg>')

# Íconos disponibles para las cifras de "Nuestro impacto" (campo "icono" en datos/impacto.json).
ICONOS_IMPACTO = {
    "estrella": '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
    "personas": ('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>'
                 '<path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'),
    "corazon": ('<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78'
                'l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>'),
    "casa": '<path d="M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
}


def leer_datos(nombre):
    return json.loads((RAIZ / "datos" / nombre).read_text(encoding="utf-8"))


def icono_impacto(nombre):
    trazo = ICONOS_IMPACTO.get(nombre, ICONOS_IMPACTO["estrella"])
    return ('<svg width="28" height="28" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" '
            f'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{trazo}</svg>')


def retraso(i):
    return f"--user-animation-delay:{0.1 + 0.12 * i:.2f}s;"


# Cada función arma el mismo HTML que su equivalente en js/inicio.js.

def generar_eventos():
    tarjetas = []
    for i, e in enumerate(leer_datos("eventos.json")["eventos"]):
        mes = e["mes"].strip().lower()
        if mes not in MESES:
            raise SystemExit(f'datos/eventos.json: mes "{e["mes"]}" no válido (usa el nombre completo, p. ej. "septiembre")')
        tarjetas.append(
            f'<li class="eventos__tarjeta transition transition--slide" style="{retraso(i)}">'
            f'<div class="eventos__foto"><img src="{html.escape(e["imagen"])}" '
            f'alt="{html.escape(e.get("descripcion") or e["titulo"])}" loading="lazy">'
            f'<p class="eventos__fecha"><span class="eventos__dia">{int(e["dia"]):02d}</span>'
            f'<span class="eventos__mes"><abbr title="{mes}">{mes[:3].upper()}</abbr></span></p></div>'
            f'<div class="eventos__cuerpo">'
            + (f'<p class="eventos__tipo">{html.escape(e["tipo"])}</p>' if e.get("tipo") else "")
            + f'<h3 class="eventos__nombre">{html.escape(e["titulo"])}</h3>'
            + (f'<p class="eventos__lugar">{ICONO_UBICACION}<span>{html.escape(e["lugar"])}</span></p>'
               if e.get("lugar") else "")
            + '</div></li>'
        )
    return "".join(tarjetas)


def generar_impacto():
    datos = leer_datos("impacto.json")
    cifras = "".join(
        f'<li class="impacto__cifra transition transition--slide" style="{retraso(i)}">'
        f'<span class="impacto__icono">{icono_impacto(c.get("icono"))}</span>'
        f'<span class="impacto__dato"><strong class="impacto__numero" data-contar="{int(c["numero"])}" '
        f'data-prefijo="{html.escape(c.get("prefijo", ""))}">{html.escape(c.get("prefijo", ""))}{int(c["numero"])}</strong>'
        f'<span class="impacto__etiqueta">{html.escape(c["texto"])}</span></span>'
        f'</li>'
        for i, c in enumerate(datos["cifras"])
    )
    areas = []
    for i, a in enumerate(datos["areas"]):
        numero = ""
        if a.get("numero") is not None:
            prefijo = html.escape(a.get("prefijo", "+"))
            numero = (f'<strong class="impacto__area-numero" data-contar="{int(a["numero"])}" '
                      f'data-prefijo="{prefijo}">{prefijo}{int(a["numero"])}</strong>')
        areas.append(
            f'<li class="impacto__area transition transition--slide" style="{retraso(i)}">'
            f'<img src="{html.escape(a["imagen"])}" alt="{html.escape(a.get("descripcion") or a["titulo"])}" loading="lazy">'
            f'<span class="impacto__area-titulo">{numero}{html.escape(a["titulo"])}</span>'
            f'</li>'
        )
    return {
        "impacto-titulo": html.escape(datos["titulo"]),
        "impacto-cita": html.escape(datos["cita"]),
        "impacto-cifras": cifras,
        "impacto-areas": "".join(areas),
    }


ICONOS_DONAR = {
    "paypal": ('<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>'
               '<path d="M6 15h4"/>'),
    "gofundme": ('<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78'
                 'l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>'),
}

ICONO_SALIR = ('<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
               'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
               '<path d="M7 17 17 7M8 7h9v9"/></svg>')


def plataforma(nombre):
    """Nombre de plataforma seguro para usar en una clase CSS."""
    return re.sub(r"[^a-z0-9-]", "", (nombre or "").lower()) or "otra"


def generar_donar():
    datos = leer_datos("donar.json")
    opciones = []
    for i, o in enumerate(datos["opciones"]):
        clave = plataforma(o.get("plataforma"))
        trazo = ICONOS_DONAR.get(clave, ICONOS_DONAR["gofundme"])
        opciones.append(
            f'<li class="donar__opcion donar__opcion--{clave} transition transition--slide" style="{retraso(i)}">'
            f'<span class="donar__icono"><svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" '
            f'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            f'{trazo}</svg></span>'
            f'<h3 class="donar__nombre">{html.escape(o["titulo"])}</h3>'
            f'<p class="donar__texto">{html.escape(o["texto"])}</p>'
            f'<a class="donar__boton" href="{html.escape(o["enlace"])}" target="_blank" rel="noopener">'
            f'{html.escape(o["boton"])}{ICONO_SALIR}'
            f'<span class="solo-lectores"> (se abre en otra pestaña)</span></a>'
            f'</li>'
        )
    return {
        "donar-titulo": html.escape(datos["titulo"]),
        "donar-texto": html.escape(datos["texto"]),
        "donar-opciones": "".join(opciones),
    }


def generar_aliados():
    datos = leer_datos("aliados.json")

    def lista(copia):
        items = []
        for a in datos["aliados"]:
            logo = (f'<img src="{html.escape(a["logo"])}" alt="{"" if copia else html.escape(a["nombre"])}" '
                    f'loading="lazy">')
            if a.get("enlace"):
                extra = ' tabindex="-1"' if copia else ""
                logo = f'<a href="{html.escape(a["enlace"])}" target="_blank" rel="noopener"{extra}>{logo}</a>'
            items.append(f'<li class="aliados__logo">{logo}</li>')
        oculto = ' aria-hidden="true"' if copia else ""
        return f'<ul class="aliados__lista"{oculto}>{"".join(items)}</ul>'

    # La segunda lista es una copia para que el desplazamiento sea continuo.
    return {
        "aliados-titulo": html.escape(datos["titulo"]),
        "aliados-lista": lista(False) + lista(True),
    }


def reemplazar_bloque(s, nombre, contenido, pagina):
    inicio, fin = f"<!-- {nombre}:inicio -->", f"<!-- {nombre}:fin -->"
    if inicio not in s:
        raise SystemExit(f"{pagina}: falta el bloque <!-- {nombre} -->")
    return re.sub(re.escape(inicio) + ".*?" + re.escape(fin),
                  lambda _: inicio + contenido + fin, s, count=1, flags=re.S)


def insertar_contenido_inicio(s, pagina):
    if pagina != "index.html":
        return s
    s = reemplazar_bloque(s, "eventos-lista", generar_eventos(), pagina)
    for bloques in (generar_impacto(), generar_donar(), generar_aliados()):
        for nombre, contenido in bloques.items():
            s = reemplazar_bloque(s, nombre, contenido, pagina)
    return s



# ───────────── Programas (datos/programas.json) ─────────────

PROGRAMAS_INICIO = "<!-- programas:inicio -->"
PROGRAMAS_FIN = "<!-- programas:fin -->"

# Los 17 Objetivos de Desarrollo Sostenible, con su color oficial.
ODS = [
    ("Fin de la pobreza", "#e5243b"), ("Hambre cero", "#dda63a"), ("Salud y bienestar", "#4c9f38"),
    ("Educación de calidad", "#c5192d"), ("Igualdad de género", "#ff3a21"),
    ("Agua limpia y saneamiento", "#26bde2"), ("Energía asequible y no contaminante", "#fcc30b"),
    ("Trabajo decente y crecimiento económico", "#a21942"), ("Industria, innovación e infraestructura", "#fd6925"),
    ("Reducción de las desigualdades", "#dd1367"), ("Ciudades y comunidades sostenibles", "#fd9d24"),
    ("Producción y consumo responsables", "#bf8b2e"), ("Acción por el clima", "#3f7e44"),
    ("Vida submarina", "#0a97d9"), ("Vida de ecosistemas terrestres", "#56c02b"),
    ("Paz, justicia e instituciones sólidas", "#00689d"), ("Alianzas para lograr los objetivos", "#19486a"),
]

# Íconos disponibles para los programas (campo "icono" en datos/programas.json).
ICONOS_PROGRAMA = {
    "salud": ('<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23'
              'l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"/><path d="M3.5 12h4l1.5-3 3 6 1.5-3h4"/>'),
    "educacion": ('<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/>'
                  '<path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/>'),
    "brote": ('<path d="M7 20h10"/><path d="M12 20v-8"/><path d="M12 12C12 8 9 5 4 5c0 4 3 7 8 7z"/>'
              '<path d="M12 12c0-3 2-6 7-6 0 4-3 6-7 6z"/>'),
    "personas": ICONOS_IMPACTO["personas"],
    "apoyo": '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
    "corazon": ICONOS_IMPACTO["corazon"],
}

ICONO_CERRAR = ('<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
                'stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>')
ICONO_ANTERIOR = ('<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
                  'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>')
ICONO_SIGUIENTE = ('<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
                   'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>')


def leer_programas():
    datos = leer_datos("programas.json")
    tipos = {t["clave"] for t in datos["tipos"]}
    claves = set()
    for pr in datos["programas"]:
        if pr["tipo"] not in tipos:
            raise SystemExit(f'datos/programas.json: el programa "{pr["nombre"]}" tiene un tipo que no existe: "{pr["tipo"]}"')
        for n in pr.get("ods", []):
            if not 1 <= int(n) <= 17:
                raise SystemExit(f'datos/programas.json: "{pr["nombre"]}" tiene el ODS {n}; deben ser del 1 al 17')
        claves.add(pr["clave"])
    for j in datos["jornadas"]:
        if j["programa"] not in claves:
            raise SystemExit(f'datos/programas.json: la jornada "{j["titulo"]}" usa un programa que no existe: "{j["programa"]}"')
        if j.get("fecha") and not re.fullmatch(r"\d{4}-\d{2}-\d{2}", j["fecha"]):
            raise SystemExit(f'datos/programas.json: fecha "{j["fecha"]}" no válida (usa AAAA-MM-DD o déjala vacía)')
    return datos


def icono_programa(nombre):
    trazo = ICONOS_PROGRAMA.get(nombre, ICONOS_PROGRAMA["corazon"])
    return ('<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" '
            f'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{trazo}</svg>')


def item_galeria(j, nombres):
    """Foto de la galería de jornadas (el mismo HTML que arma js/programas.js)."""
    fecha = fecha_larga(j["fecha"]) if j.get("fecha") else ""
    detalle = " · ".join(x for x in (j.get("lugar", ""), fecha) if x)
    programa = nombres.get(j["programa"], "")
    return (
        f'<li class="galeria__item" data-programa="{html.escape(j["programa"])}">'
        f'<button type="button" class="galeria__boton" data-galeria-abrir '
        f'data-programa-nombre="{html.escape(programa)}" data-titulo="{html.escape(j["titulo"])}" '
        f'data-detalle="{html.escape(detalle)}">'
        f'<img src="{html.escape(j["imagen"])}" alt="{html.escape(j.get("descripcion") or j["titulo"])}" loading="lazy">'
        f'<span class="galeria__info"><span class="galeria__programa">{html.escape(programa)}</span>'
        f'<span class="galeria__titulo">{html.escape(j["titulo"])}</span></span>'
        f'</button></li>'
    )


def generar_programas(pagina):
    datos = leer_programas()
    tipos = {t["clave"]: t for t in datos["tipos"]}
    nombres = {pr["clave"]: pr["nombre"] for pr in datos["programas"]}
    conteo = {c: sum(1 for j in datos["jornadas"] if j["programa"] == c) for c in nombres}
    ods_capdes = sorted({int(n) for pr in datos["programas"] for n in pr.get("ods", [])})

    pestanas = '<button type="button" class="programas__filtro" aria-pressed="true" data-tipo="">Todos</button>' + "".join(
        f'<button type="button" class="programas__filtro" aria-pressed="false" data-tipo="{html.escape(t["clave"])}" '
        f'data-descripcion="{html.escape(t["descripcion"])}">{html.escape(t["nombre"])}</button>'
        for t in datos["tipos"] if any(pr["tipo"] == t["clave"] for pr in datos["programas"]))

    tarjetas = []
    for pr in datos["programas"]:
        chips = "".join(
            f'<li class="ods-chip" style="--ods:{ODS[n - 1][1]}" title="ODS {n}: {html.escape(ODS[n - 1][0])}">'
            f'<span class="solo-lectores">ODS </span>{n}<span class="solo-lectores">: {html.escape(ODS[n - 1][0])}</span></li>'
            for n in (int(x) for x in pr.get("ods", [])))
        n_jornadas = conteo[pr["clave"]]
        tarjetas.append(
            f'<li class="programa transition transition--slide" data-tipo="{html.escape(pr["tipo"])}" id="programa-{pr["clave"]}">'
            f'<article class="programa__tarjeta">'
            f'<div class="programa__foto"><img src="{html.escape(pr["imagen"])}" '
            f'alt="{html.escape(pr.get("descripcion_imagen") or "")}" loading="lazy">'
            f'<span class="programa__icono">{icono_programa(pr.get("icono"))}</span></div>'
            f'<div class="programa__cuerpo">'
            f'<p class="programa__tipo">{html.escape(tipos[pr["tipo"]]["nombre"])}</p>'
            f'<h3 class="programa__nombre">{html.escape(pr["nombre"])}</h3>'
            f'<p class="programa__texto">{html.escape(pr["descripcion"])}</p>'
            + (f'<ul class="programa__ods" aria-label="Objetivos de Desarrollo Sostenible">{chips}</ul>' if chips else "")
            + f'<a class="programa__boton" href="#jornadas" data-ver-jornadas="{html.escape(pr["clave"])}"'
            f'{"" if n_jornadas else " hidden"}><span>Ver jornadas (<span data-conteo>{n_jornadas}</span>)</span>{ICONO_FLECHA_DERECHA}</a>'
            f'</div></article></li>'
        )

    filtros_galeria = '<button type="button" class="galeria__filtro" aria-pressed="true" data-programa="">Todas</button>' + "".join(
        f'<button type="button" class="galeria__filtro" aria-pressed="false" data-programa="{html.escape(c)}">'
        f'{html.escape(nombres[c])}</button>' for c in nombres if conteo[c])
    fotos = "".join(item_galeria(j, nombres) for j in datos["jornadas"])

    ods = "".join(
        f'<li class="ods__item{" ods__item--capdes" if n in ods_capdes else ""}">'
        f'<img src="img/ods/ods-{n:02d}.png" alt="ODS {n}: {html.escape(nombre)}" loading="lazy" width="240" height="240">'
        f'<span class="ods__nombre">{html.escape(nombre)}</span></li>'
        for n, (nombre, _) in enumerate(ODS, start=1))

    return (
        f'{PROGRAMAS_INICIO}'
        f'<section class="portada portada--pagina">'
        f'<div class="portada__fondo" aria-hidden="true"><img src="{html.escape(datos["imagen_portada"])}" alt=""></div>'
        f'<div class="portada__contenido">'
        f'<p class="portada__antetitulo transition transition--fade">• Programas</p>'
        f'<h1 class="portada__titulo transition transition--slide" style="--user-animation-delay:0.1s;">Nuestros <span>programas</span></h1>'
        f'<p class="portada__intro transition transition--slide" style="--user-animation-delay:0.2s;">{html.escape(datos["intro"])}</p>'
        f'<div class="eventos__botones portada__botones transition transition--fade" style="--user-animation-delay:0.3s;">'
        f'<a class="eventos__boton eventos__boton--principal" href="#programas">Conoce los programas{ICONO_FLECHA_DERECHA}</a>'
        f'<a class="eventos__boton eventos__boton--secundario" href="#jornadas">Ver jornadas realizadas</a>'
        f'</div></div></section>'

        f'<section class="programas" id="programas" aria-labelledby="programas-titulo">'
        f'<div class="programas__contenido">'
        f'<header class="programas__encabezado">'
        f'<p class="programas__antetitulo">• Modelos de intervención integral</p>'
        f'<h2 class="programas__titulo" id="programas-titulo">Programas de intervención social</h2>'
        f'<p class="programas__intro" data-tipo-descripcion data-original="{html.escape(datos["modelo"])}">{html.escape(datos["modelo"])}</p>'
        f'</header>'
        f'<div class="programas__filtros" role="group" aria-label="Filtrar programas por tipo" data-programas-filtros>{pestanas}</div>'
        f'<ul class="programas__lista" data-programas-lista>{"".join(tarjetas)}</ul>'
        f'</div></section>'

        f'<section class="galeria" id="jornadas" aria-labelledby="galeria-titulo">'
        f'<div class="galeria__contenido">'
        f'<header class="programas__encabezado">'
        f'<p class="programas__antetitulo">• Galería</p>'
        f'<h2 class="programas__titulo" id="galeria-titulo">Jornadas realizadas</h2>'
        f'<p class="programas__intro">Así se ve el trabajo de nuestros programas en las comunidades. Elige un programa para ver sus jornadas.</p>'
        f'</header>'
        f'<div class="galeria__filtros" role="group" aria-label="Filtrar jornadas por programa" data-galeria-filtros>{filtros_galeria}</div>'
        f'<ul class="galeria__lista" data-galeria-lista>{fotos}</ul>'
        f'<p class="galeria__vacio" data-galeria-vacio hidden>Pronto compartiremos las jornadas de este programa.</p>'
        f'</div></section>'

        f'<dialog class="visor" data-visor aria-label="Foto de la jornada">'
        f'<figure class="visor__figura"><img data-visor-img src="" alt="">'
        f'<figcaption class="visor__pie"><span class="visor__programa" data-visor-programa></span>'
        f'<span class="visor__titulo" data-visor-titulo></span><span class="visor__detalle" data-visor-detalle></span>'
        f'<span class="visor__contador" data-visor-contador></span></figcaption></figure>'
        f'<button type="button" class="visor__boton visor__boton--cerrar" data-visor-cerrar aria-label="Cerrar">{ICONO_CERRAR}</button>'
        f'<button type="button" class="visor__boton visor__boton--anterior" data-visor-anterior aria-label="Foto anterior">{ICONO_ANTERIOR}</button>'
        f'<button type="button" class="visor__boton visor__boton--siguiente" data-visor-siguiente aria-label="Foto siguiente">{ICONO_SIGUIENTE}</button>'
        f'</dialog>'

        f'<section class="ods" id="ods" aria-labelledby="ods-titulo">'
        f'<div class="ods__contenido">'
        f'<header class="programas__encabezado">'
        f'<p class="programas__antetitulo">• Agenda 2030</p>'
        f'<h2 class="programas__titulo" id="ods-titulo">Alineados con los Objetivos de Desarrollo Sostenible</h2>'
        f'<p class="programas__intro">{html.escape(datos["agenda_2030"])} Resaltamos los objetivos en los que trabajan nuestros programas.</p>'
        f'</header>'
        f'<ul class="ods__lista">{ods}</ul>'
        f'</div></section>'

        f'<section class="suscribir apoyar" aria-labelledby="apoyar-titulo">'
        f'<div class="suscribir__tarjeta">'
        f'<div><h2 class="suscribir__titulo" id="apoyar-titulo">¿Quieres apoyar un programa?</h2>'
        f'<p class="suscribir__texto">Dona, súmate como voluntario o construyamos juntos una alianza.</p></div>'
        f'<div class="apoyar__botones">'
        f'<a class="suscribir__boton" href="index.html#donar">{ICONO_CORAZON}Donar</a>'
        f'<a class="suscribir__boton suscribir__boton--linea" href="contacto.html?motivo=voluntariado#formulario">Ser voluntario</a>'
        f'<a class="suscribir__boton suscribir__boton--linea" href="contacto.html?motivo=alianza#formulario">Hacer una alianza</a>'
        f'</div></div></section>'
        f'{PROGRAMAS_FIN}'
    )


def insertar_programas(s, pagina):
    if pagina != "programas.html":
        return s
    bloque = generar_programas(pagina)
    s = re.sub(r"<title>[^<]*</title>", "<title>Nuestros programas | CAPDES, A.C.</title>", s, count=1)
    if PROGRAMAS_INICIO in s:
        return re.sub(re.escape(PROGRAMAS_INICIO) + ".*?" + re.escape(PROGRAMAS_FIN), lambda _: bloque, s, count=1, flags=re.S)
    # Primera vez: reemplaza las cuatro secciones de Hostinger (Agenda 2030, ejes y programas).
    inicio = s.find('<section id="zrc4nQ"')
    ultima = s.find('<section id="zBcjnR"')
    if inicio == -1 or ultima == -1:
        raise SystemExit("programas.html: no encontré las secciones originales")
    fin = s.index("</section>", ultima) + len("</section>")
    return s[:inicio] + bloque + s[fin:]


# ───────────── Contacto (datos/contacto.json) ─────────────

CONTACTO_INICIO = "<!-- contacto:inicio -->"
CONTACTO_FIN = "<!-- contacto:fin -->"

ICONO_UBICACION_GRANDE = ('<svg width="40" height="40" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
                          'stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'
                          '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/>'
                          '<circle cx="12" cy="9.5" r="2.5"/></svg>')


def canales_contacto(c):
    """Tarjetas de contacto directo de la página de Contacto."""
    canales = []
    if c.get("telefono"):
        tel = re.sub(r"[^\d+]", "", c["telefono"])
        canales.append(("Llámanos", c["telefono"], f"tel:{tel}", ICONO_TELEFONO, False))
    if c.get("whatsapp"):
        canales.append(("WhatsApp", "Escríbenos por WhatsApp", f'https://wa.me/{re.sub(r"[^0-9]", "", c["whatsapp"])}',
                        ICONO_MENSAJE, True))
    if c.get("correo"):
        canales.append(("Correo", c["correo"], f'mailto:{c["correo"]}', ICONO_CORREO, False))
    tarjetas = "".join(
        f'<li><a class="canal" href="{html.escape(url)}"'
        + (' target="_blank" rel="noopener"' if externo else "")
        + f'><span class="canal__icono">{icono}</span><span class="canal__texto">'
        f'<span class="canal__tipo">{html.escape(tipo)}</span><span class="canal__dato">{html.escape(dato)}</span>'
        f'</span></a></li>'
        for tipo, dato, url, icono, externo in canales)
    return tarjetas


def generar_contacto(pagina):
    c = leer_datos("contacto.json")
    opciones = '<option value="" disabled selected>Elige una opción</option>' + "".join(
        f'<option value="{html.escape(m["clave"])}">{html.escape(m["texto"])}</option>' for m in c["motivos"])
    if c.get("mapa_url"):
        mapa = (f'<iframe class="ubicacion__mapa" src="{html.escape(c["mapa_url"])}" title="Mapa de ubicaciones de CAPDES" '
                f'loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>')
    else:
        mapa = (f'<div class="ubicacion__mapa ubicacion__mapa--pendiente">{ICONO_UBICACION_GRANDE}'
                f'<p class="ubicacion__pendiente-titulo">Mapa de ubicaciones</p></div>')
    return (
        f'{CONTACTO_INICIO}'
        f'<section class="contacto">'
        f'<div class="contacto__fondo" aria-hidden="true">'
        f'<img class="contacto__poster" src="img/contacto/fondo-contacto.jpg" alt="">'
        # El video solo se carga en pantallas grandes (js/contacto.js); en celular queda la imagen.
        f'<video class="contacto__video" muted loop playsinline preload="none" '
        f'data-src="img/contacto/fondo-contacto.mp4" data-contacto-video></video>'
        f'</div>'
        f'<div class="contacto__contenido">'
        f'<header class="contacto__encabezado">'
        f'<p class="portada__antetitulo">• Contacto</p>'
        f'<h1 class="contacto__titulo">Contáctanos</h1>'
        f'<p class="contacto__intro">Si deseas que CAPDES, A.C. llegue a tu comunidad, escríbenos. También puedes '
        f'sumarte como voluntario, proponernos una alianza o resolver cualquier duda.</p>'
        f'</header>'
        f'<div class="contacto__rejilla">'
        f'<div class="contacto__canales">'
        f'<h2 class="contacto__subtitulo">Habla con nosotros</h2>'
        f'<ul class="canales">{canales_contacto(c)}</ul>'
        f'<div class="contacto__redes"><p>Síguenos</p><div class="pie__redes">{enlaces_redes()}</div></div>'
        f'</div>'
        f'<form class="formulario" id="formulario" action="api/contacto.php" method="post" novalidate '
        f'data-contacto-formulario data-correo="{html.escape(c["correo"])}">'
        f'<h2 class="contacto__subtitulo formulario__titulo">Envíanos un mensaje</h2>'
        f'<p class="formulario__nota">Los campos con <span aria-hidden="true">*</span><span class="solo-lectores">asterisco</span> son obligatorios.</p>'
        f'<div class="formulario__fila">'
        f'<div class="campo"><label for="c-nombre">Nombre(s) <span class="campo__obligatorio" aria-hidden="true">*</span></label>'
        f'<input id="c-nombre" name="nombre" type="text" autocomplete="given-name" required maxlength="80"></div>'
        f'<div class="campo"><label for="c-apellidos">Apellidos</label>'
        f'<input id="c-apellidos" name="apellidos" type="text" autocomplete="family-name" maxlength="80"></div>'
        f'</div>'
        f'<div class="formulario__fila">'
        f'<div class="campo"><label for="c-correo">Correo electrónico <span class="campo__obligatorio" aria-hidden="true">*</span></label>'
        f'<input id="c-correo" name="correo" type="email" autocomplete="email" required maxlength="120"></div>'
        f'<div class="campo"><label for="c-telefono">Teléfono o WhatsApp</label>'
        f'<input id="c-telefono" name="telefono" type="tel" autocomplete="tel" maxlength="30"></div>'
        f'</div>'
        f'<div class="campo"><label for="c-motivo">¿En qué te podemos ayudar? <span class="campo__obligatorio" aria-hidden="true">*</span></label>'
        f'<select id="c-motivo" name="motivo" required>{opciones}</select></div>'
        f'<div class="campo"><label for="c-mensaje">Mensaje <span class="campo__obligatorio" aria-hidden="true">*</span></label>'
        f'<textarea id="c-mensaje" name="mensaje" rows="5" required maxlength="4000" '
        f'placeholder="Cuéntanos sobre tu comunidad, tu idea o tu duda."></textarea></div>'
        # Campo trampa contra spam: las personas no lo ven; los robots suelen llenarlo.
        f'<div class="formulario__trampa" aria-hidden="true"><label for="c-sitio">No llenes este campo</label>'
        f'<input id="c-sitio" name="sitio" type="text" tabindex="-1" autocomplete="off"></div>'
        f'<button class="formulario__enviar" type="submit">Enviar mensaje{ICONO_FLECHA_DERECHA}</button>'
        f'<p class="formulario__estado" role="status" data-contacto-estado></p>'
        f'</form>'
        f'</div></div></section>'
        f'<section class="ubicacion" id="ubicacion" aria-labelledby="ubicacion-titulo">'
        f'<div class="ubicacion__contenido">'
        f'<div class="ubicacion__texto">'
        f'<p class="programas__antetitulo">• Ubicación</p>'
        f'<h2 class="programas__titulo" id="ubicacion-titulo">Dónde trabajamos</h2>'
        f'<p>Nuestra organización se encuentra en el corazón de la comunidad, trabajando para empoderar a las '
        f'poblaciones vulnerables y reducir las desigualdades sociales.</p>'
        f'<p class="ubicacion__lugar">{ICONO_UBICACION}{html.escape(c.get("ubicacion", ""))}</p>'
        f'</div>'
        f'{mapa}'
        f'</div></section>'
        f'{CONTACTO_FIN}'
    )


def insertar_contacto(s, pagina):
    if pagina != "contacto.html":
        return s
    bloque = generar_contacto(pagina)
    s = re.sub(r"<title>[^<]*</title>", "<title>Contacto | CAPDES, A.C.</title>", s, count=1)
    if CONTACTO_INICIO in s:
        return re.sub(re.escape(CONTACTO_INICIO) + ".*?" + re.escape(CONTACTO_FIN), lambda _: bloque, s, count=1, flags=re.S)
    # Primera vez: reemplaza el formulario y la ubicación de Hostinger (con sus anclas).
    inicio = s.find('<span id="formulario" class="ancla">')
    if inicio == -1:
        inicio = s.find('<section id="zXR5Ei"')
    ultima = s.find('<section id="eOBFmV"')
    if inicio == -1 or ultima == -1:
        raise SystemExit("contacto.html: no encontré las secciones originales")
    fin = s.index("</section>", ultima) + len("</section>")
    return s[:inicio] + bloque + s[fin:]


# ───────────── Marco institucional (datos/marco.json) y Aviso de privacidad ─────────────

MARCO_INICIO = "<!-- marco:inicio -->"
MARCO_FIN = "<!-- marco:fin -->"

ICONO_DOCUMENTO = ('<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
                   'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                   '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>'
                   '<path d="M8 13h8M8 17h5"/></svg>')
ICONO_DESCARGAR = ('<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
                   'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
                   '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>')
ICONO_VER = ('<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
             'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
             '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>')


def peso_archivo(ruta):
    """Formato y peso de un documento del sitio, p. ej. "PDF · 347 KB"."""
    archivo = RAIZ / ruta
    if not archivo.exists():
        raise SystemExit(f'datos/marco.json: no existe el archivo "{ruta}"')
    kb = archivo.stat().st_size / 1024
    tamano = f"{kb / 1024:.1f} MB".replace(".", ",") if kb >= 1024 else f"{round(kb)} KB"
    return f'{archivo.suffix.lstrip(".").upper()} · {tamano}'


def documento_marco(d):
    meta = []
    if d.get("clave"):
        meta.append(f'Clave {html.escape(d["clave"])}')
    if d.get("vigente_desde"):
        meta.append(f'Vigente desde el {fecha_larga(d["vigente_desde"])}')
    if d.get("archivo"):
        meta.append(peso_archivo(d["archivo"]))
        nombre = html.escape(d["titulo"])
        acciones = (
            f'<div class="documento__acciones">'
            f'<a class="documento__boton" href="{html.escape(d["archivo"])}" target="_blank" rel="noopener">'
            f'{ICONO_VER}Ver<span class="solo-lectores"> {nombre} (se abre en otra pestaña)</span></a>'
            f'<a class="documento__boton documento__boton--principal" href="{html.escape(d["archivo"])}" download>'
            f'{ICONO_DESCARGAR}Descargar<span class="solo-lectores"> {nombre}</span></a>'
            f'</div>'
        )
        clase = "documento"
    else:
        acciones = '<p class="documento__estado">Próximamente</p>'
        clase = "documento documento--pendiente"
    return (
        f'<li class="{clase}">'
        f'<span class="documento__icono">{ICONO_DOCUMENTO}</span>'
        f'<div class="documento__info">'
        f'<h3 class="documento__titulo">{html.escape(d["titulo"])}</h3>'
        + (f'<p class="documento__meta">{" · ".join(meta)}</p>' if meta else "")
        + f'<p class="documento__texto">{html.escape(d["descripcion"])}</p>'
        f'</div>{acciones}</li>'
    )


def generar_marco(pagina):
    datos = leer_datos("marco.json")
    ficha = "".join(
        f'<div class="ficha__fila"><dt>{html.escape(f["campo"])}</dt>'
        + (f'<dd>{html.escape(f["valor"])}</dd>' if f["valor"] else '<dd class="ficha__pendiente">Por confirmar</dd>')
        + '</div>'
        for f in datos["ficha"])
    # La ficha legal solo se muestra cuando "mostrar_ficha" es true (sus datos los confirma la clienta).
    con_ficha = datos.get("mostrar_ficha", False)
    indice = ('<li><a href="#ficha">Ficha institucional</a></li>' if con_ficha else "") + "".join(
        f'<li><a href="#{g["clave"]}">{html.escape(g["titulo"])}</a></li>' for g in datos["grupos"]
    ) + '<li><a href="#solicitudes">Solicitudes de información</a></li>'
    grupos = "".join(
        f'<section class="marco__seccion" id="{g["clave"]}" aria-labelledby="titulo-{g["clave"]}">'
        f'<h2 class="marco__subtitulo" id="titulo-{g["clave"]}">{html.escape(g["titulo"])}</h2>'
        f'<ul class="documentos">{"".join(documento_marco(d) for d in g["documentos"])}</ul>'
        f'</section>'
        for g in datos["grupos"])
    total = sum(1 for g in datos["grupos"] for d in g["documentos"] if d.get("archivo"))
    correo = next((f["valor"] for f in datos["ficha"] if "correo" in f["campo"].lower() and f["valor"]),
                  leer_datos("contacto.json")["correo"])
    return (
        f'{MARCO_INICIO}'
        f'<div class="marco">'
        f'<header class="marco__portada"><div class="marco__portada-contenido">'
        f'<p class="marco__antetitulo">Transparencia y rendición de cuentas</p>'
        f'<h1 class="marco__titulo">Marco institucional</h1>'
        + "".join(f'<p class="marco__intro">{html.escape(t)}</p>' for t in datos["intro"]) +
        f'<div class="marco__acciones">'
        f'<a class="marco__boton marco__boton--principal" href="{html.escape(datos["carpeta"])}" download>'
        f'{ICONO_DESCARGAR}<span>Descargar carpeta institucional completa'
        f'<small>{peso_archivo(datos["carpeta"])} · {total} documentos</small></span></a>'
        + (f'<a class="marco__boton" href="aviso-de-privacidad.html">Aviso de privacidad</a>' if aviso_publicado() else "")
        + f'</div>'
        f'<p class="marco__actualizado">Última actualización: <time datetime="{datos["actualizado"]}">'
        f'{fecha_larga(datos["actualizado"])}</time></p>'
        f'</div></header>'
        f'<div class="marco__cuerpo">'
        f'<aside class="marco__indice"><nav aria-label="Contenido de esta página" data-marco-indice>'
        f'<p class="marco__indice-titulo">En esta página</p><ol>{indice}</ol></nav></aside>'
        f'<div class="marco__contenido">'
        + (f'<section class="marco__seccion" id="ficha" aria-labelledby="titulo-ficha">'
           f'<h2 class="marco__subtitulo" id="titulo-ficha">Ficha institucional</h2>'
           f'<dl class="ficha">{ficha}</dl></section>' if con_ficha else "")
        + f'{grupos}'
        f'<section class="marco__seccion marco__solicitudes" id="solicitudes" aria-labelledby="titulo-solicitudes">'
        f'<h2 class="marco__subtitulo" id="titulo-solicitudes">Solicitudes de información</h2>'
        f'<p>{html.escape(datos["contacto"])}</p>'
        f'<a class="marco__correo" href="mailto:{html.escape(correo)}">{ICONO_CORREO}{html.escape(correo)}</a>'
        f'</section>'
        f'</div></div></div>'
        f'{MARCO_FIN}'
    )


def insertar_marco(s, pagina):
    if pagina != "marco-institucional.html":
        return s
    bloque = generar_marco(pagina)
    s = re.sub(r"<title>[^<]*</title>", "<title>Marco institucional | CAPDES, A.C.</title>", s, count=1)
    if MARCO_INICIO in s:
        return re.sub(re.escape(MARCO_INICIO) + ".*?" + re.escape(MARCO_FIN), lambda _: bloque, s, count=1, flags=re.S)
    # Primera vez: reemplaza las secciones de Hostinger, desde la introducción hasta el contacto final.
    inicio = s.find('<section id="ai-WXDGTR"')
    ultima = s.find('<section id="zXz15i"')
    if inicio == -1 or ultima == -1:
        raise SystemExit("marco-institucional.html: no encontré las secciones originales")
    fin = s.index("</section>", ultima) + len("</section>")
    return s[:inicio] + bloque + s[fin:]


def aviso_publicado():
    """El aviso de privacidad (y sus enlaces) solo existe cuando "publicar" es true."""
    return leer_datos("aviso-privacidad.json").get("publicar", False)


def generar_aviso():
    datos = leer_datos("aviso-privacidad.json")
    pagina = "aviso-de-privacidad.html"
    if not datos.get("publicar", False):
        ruta = RAIZ / pagina
        if ruta.exists() and GENERADOR in ruta.read_text(encoding="utf-8"):
            ruta.unlink()
            print(f"✗ {pagina} (no publicado: \"publicar\" es false en datos/aviso-privacidad.json)")
        return
    secciones = []
    for i, sec in enumerate(datos["secciones"], start=1):
        partes = [f'<h2>{i}. {html.escape(sec["titulo"])}</h2>']
        partes += [f'<p>{html.escape(t)}</p>' for t in sec.get("parrafos", [])]
        if sec.get("lista"):
            partes.append("<ul>" + "".join(f"<li>{html.escape(t)}</li>" for t in sec["lista"]) + "</ul>")
        partes += [f'<p>{html.escape(t)}</p>' for t in sec.get("parrafos_finales", [])]
        secciones.append(f'<section class="legal__seccion">{"".join(partes)}</section>')
    revision = ('<p class="legal__revision" role="note"><strong>Borrador en revisión.</strong> Este aviso está '
                'pendiente de revisión legal y de completar los datos del responsable; no es la versión definitiva.</p>'
                if datos.get("en_revision") else "")
    cuerpo = (
        f'<main class="entrada legal">'
        f'<article class="entrada__articulo">'
        f'<header class="entrada__encabezado">'
        f'<nav class="entrada__migas" aria-label="Ruta de navegación"><a href="index.html">Inicio</a>'
        f'<span aria-hidden="true">/</span><a href="marco-institucional.html">Marco institucional</a></nav>'
        f'<h1 class="entrada__titulo">Aviso de privacidad</h1>'
        f'<p class="entrada__meta">Última actualización: <time datetime="{datos["actualizado"]}">'
        f'{fecha_larga(datos["actualizado"])}</time></p>'
        f'</header>'
        f'{revision}'
        f'<div class="legal__contenido">{"".join(secciones)}</div>'
        f'<p class="legal__relacionado">Consulta también nuestra '
        f'<a href="documentos/polpdpys-008-politica-proteccion-de-datos.pdf" target="_blank" rel="noopener">'
        f'Política de Protección de Datos Personales y Salvaguarda</a> en el '
        f'<a href="marco-institucional.html#marco-normativo">Marco institucional</a>.</p>'
        f'</article></main>'
    )
    (RAIZ / pagina).write_text(
        pagina_limpia(pagina, "Aviso de privacidad | CAPDES, A.C.",
                      "Cómo CAPDES, A.C. trata y protege los datos personales que recibe a través de este sitio.",
                      cuerpo, clase_body="pagina-entrada"),
        encoding="utf-8")
    print(f"✓ {pagina}")


# ───────────── Blog (datos/blog.json) ─────────────
# El listado va en blog.html y cada artículo en su propia página, blog/<slug>.html,
# para que tenga su enlace para compartir y Google lo indexe por separado.

BLOG_INICIO = "<!-- blog:inicio -->"
BLOG_FIN = "<!-- blog:fin -->"
GENERADOR = '<meta name="generator" content="herramientas/menu.py">'

ICONO_WHATSAPP = ICONO_MENSAJE
ICONO_COPIAR = ('<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
                'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>')
ICONO_FLECHA_DERECHA = ('<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" '
                        'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
                        '<path d="M5 12h14M13 6l6 6-6 6"/></svg>')
FUENTES = ('<link href="https://cdn.zyrosite.com/u1/google-fonts/font-faces?family=Outfit:wght@400;600&amp;'
           'family=Poppins:wght@400;500;700&amp;display=swap" rel="stylesheet" referrerpolicy="no-referrer">')


def leer_blog():
    datos = leer_datos("blog.json")
    vistos = set()
    for e in datos["entradas"]:
        if not re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", e["slug"]):
            raise SystemExit(f'datos/blog.json: slug "{e["slug"]}" no válido (solo minúsculas, números y guiones)')
        if e["slug"] in vistos:
            raise SystemExit(f'datos/blog.json: el slug "{e["slug"]}" está repetido')
        vistos.add(e["slug"])
        if not re.fullmatch(r"\d{4}-\d{2}-\d{2}", e["fecha"]):
            raise SystemExit(f'datos/blog.json: fecha "{e["fecha"]}" no válida (usa AAAA-MM-DD)')
    # Más recientes primero.
    datos["entradas"].sort(key=lambda e: e["fecha"], reverse=True)
    return datos


def fecha_larga(iso):
    anio, mes, dia = (int(x) for x in iso.split("-"))
    return f"{dia} de {MESES[mes - 1]} de {anio}"


def minutos_lectura(entrada):
    palabras = sum(len(re.sub(r"\*+", "", p).split()) for p in entrada["contenido"])
    return max(1, math.ceil(palabras / 200))


def texto_con_formato(parrafo):
    """Párrafo con **negritas** y *cursivas* (lo único que se permite en datos/blog.json)."""
    t = html.escape(parrafo)
    t = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", t)
    return re.sub(r"\*(.+?)\*", r"<em>\1</em>", t)


def tarjeta_blog(e, pagina, nivel="h2", destacada=False):
    r = raiz(pagina)
    clase = "blog__tarjeta blog__tarjeta--destacada" if destacada else "blog__tarjeta"
    carga = "" if destacada else ' loading="lazy"'
    return (
        f'<li class="{clase}" data-categoria="{html.escape(e["categoria"])}">'
        f'<a class="blog__enlace" href="{r}blog/{e["slug"]}.html">'
        f'<span class="blog__portada"><img src="{r}{html.escape(e["imagen"])}" '
        f'alt="{html.escape(e.get("descripcion_imagen") or "")}"{carga}></span>'
        f'<span class="blog__cuerpo">'
        f'<span class="blog__meta"><span class="blog__categoria">{html.escape(e["categoria"])}</span>'
        f'<time datetime="{e["fecha"]}">{fecha_larga(e["fecha"])}</time></span>'
        f'<{nivel} class="blog__nombre">{html.escape(e["titulo"])}</{nivel}>'
        f'<span class="blog__resumen">{html.escape(e["resumen"])}</span>'
        f'<span class="blog__leer">Leer artículo{ICONO_FLECHA_DERECHA}</span>'
        f'</span></a></li>'
    )


def generar_listado_blog(pagina):
    datos = leer_blog()
    entradas = datos["entradas"]
    usadas = [c for c in datos["categorias"] if any(e["categoria"] == c for e in entradas)]
    filtros = '<button type="button" class="blog__filtro" aria-pressed="true" data-filtro="">Todas</button>' + "".join(
        f'<button type="button" class="blog__filtro" aria-pressed="false" data-filtro="{html.escape(c)}">'
        f'{html.escape(c)}</button>' for c in usadas)
    tarjetas = "".join(tarjeta_blog(e, pagina, destacada=(i == 0)) for i, e in enumerate(entradas))
    return (
        f'{BLOG_INICIO}'
        f'<section id="zapxkx" class="blog" aria-labelledby="blog-titulo">'
        f'<div class="blog__contenido">'
        f'<header class="blog__encabezado">'
        f'<h1 class="blog__titulo" id="blog-titulo">Nuestro blog</h1>'
        f'</header>'
        f'<div class="blog__filtros" role="group" aria-label="Filtrar artículos por categoría" data-blog-filtros>{filtros}</div>'
        f'<ul class="blog__lista blog__lista--todas" data-blog-lista>{tarjetas}</ul>'
        f'<p class="blog__vacio" data-blog-vacio hidden>Aún no hay publicaciones en esta categoría.</p>'
        f'</div>'
        f'</section>'
        f'<section class="eventos eventos--blog" aria-labelledby="blog-eventos-titulo">'
        f'<div class="eventos__contenido">'
        f'<header class="eventos__encabezado">'
        f'<p class="eventos__antetitulo">• Agenda</p>'
        f'<h2 class="eventos__titulo" id="blog-eventos-titulo">Próximos <span>eventos</span></h2>'
        f'</header>'
        f'<ul class="eventos__lista" data-eventos>{generar_eventos()}</ul>'
        f'</div>'
        f'</section>'
        f'<section class="suscribir" aria-labelledby="suscribir-titulo">'
        f'<div class="suscribir__tarjeta">'
        f'<div><h2 class="suscribir__titulo" id="suscribir-titulo">Recibe nuestras noticias</h2>'
        f'<p class="suscribir__texto">Déjanos tu correo y te avisamos de nuevas publicaciones, jornadas y eventos.</p></div>'
        f'<a class="suscribir__boton" href="#pie-correo" data-ir-suscripcion>Quiero suscribirme{ICONO_FLECHA_DERECHA}</a>'
        f'</div>'
        f'</section>'
        f'{BLOG_FIN}'
    )


def insertar_blog(s, pagina):
    if pagina != "blog.html":
        return s
    listado = generar_listado_blog(pagina)
    s = re.sub(r"<title>[^<]*</title>", "<title>Nuestro blog | CAPDES, A.C.</title>", s, count=1)
    if BLOG_INICIO in s:
        return re.sub(re.escape(BLOG_INICIO) + ".*?" + re.escape(BLOG_FIN), lambda _: listado, s, count=1, flags=re.S)
    s, n = re.subn(r'<section id="zapxkx".*?</section>', lambda _: listado, s, count=1, flags=re.S)
    if n != 1:
        raise SystemExit("blog.html: no encontré la sección del blog")
    return s


def relacionadas(entrada, entradas, cuantas=3):
    otras = [e for e in entradas if e["slug"] != entrada["slug"]]
    misma = [e for e in otras if e["categoria"] == entrada["categoria"]]
    resto = [e for e in otras if e["categoria"] != entrada["categoria"]]
    return (misma + resto)[:cuantas]


def pagina_limpia(pagina, titulo, descripcion, cuerpo, scripts=(), head_extra="", clase_body=""):
    """Página completa con menú y pie, sin el código de Hostinger (artículos, aviso de privacidad)."""
    r = raiz(pagina)
    css = f'{r}css/renovacion.css?v={version("css/renovacion.css")}'
    js = "".join(
        f'<script src="{r}js/{n}.js?v={version(f"js/{n}.js")}"></script>'
        for n in ("animaciones", "menu", "contador", "pie") + tuple(scripts))
    clase = f' class="{clase_body}"' if clase_body else ""
    return (
        f'<!doctype html>\n<html lang="es" data-raiz="{r}"><head>'
        f'<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        f'{GENERADOR}'
        f'<title>{titulo}</title>'
        f'<meta name="description" content="{descripcion}">'
        f'<link rel="canonical" href="{SITIO}{pagina}">'
        f'<link rel="icon" href="{r}img/favicon.ico" sizes="any">'
        f'<link rel="icon" type="image/png" sizes="32x32" href="{r}img/favicon-32.png">'
        f'<link rel="apple-touch-icon" href="{r}img/apple-touch-icon.png">'
        f'{FUENTES}'
        f'<link rel="stylesheet" href="{r}css/main.css"><link rel="stylesheet" href="{css}">'
        f'{head_extra}'
        f'</head><body{clase}>'
        f'<div class="top-blocks">{generar_menu(pagina)}</div>'
        f'{cuerpo}'
        f'{generar_pie(pagina)}'
        f'{js}'
        f'</body></html>\n'
    )


def generar_pagina_entrada(e, entradas):
    pagina = f'blog/{e["slug"]}.html'
    r = raiz(pagina)
    url = f'{SITIO}{pagina}'
    imagen_abs = f'{SITIO}{e["imagen"]}'
    titulo = html.escape(e["titulo"])
    resumen = html.escape(e["resumen"])
    texto_compartir = quote(f'{e["titulo"]} {url}')
    datos_ld = json.dumps({
        "@context": "https://schema.org", "@type": "BlogPosting",
        "headline": e["titulo"], "description": e["resumen"], "image": imagen_abs,
        "datePublished": e["fecha"], "mainEntityOfPage": url,
        "author": {"@type": "Organization", "name": "CAPDES, A.C."},
        "publisher": {"@type": "Organization", "name": "CAPDES, A.C."},
    }, ensure_ascii=False)
    contenido = "".join(f"<p>{texto_con_formato(p)}</p>" for p in e["contenido"])
    sugeridas = "".join(tarjeta_blog(x, pagina, nivel="h3") for x in relacionadas(e, entradas))
    head_extra = (
        f'<meta property="og:type" content="article"><meta property="og:site_name" content="CAPDES, A.C.">'
        f'<meta property="og:title" content="{titulo}"><meta property="og:description" content="{resumen}">'
        f'<meta property="og:url" content="{url}"><meta property="og:image" content="{imagen_abs}">'
        f'<meta property="article:published_time" content="{e["fecha"]}">'
        f'<meta name="twitter:card" content="summary_large_image">'
        f'<script type="application/ld+json">{datos_ld}</script>'
    )
    cuerpo = (
        f'<main class="entrada">'
        f'<article class="entrada__articulo">'
        f'<header class="entrada__encabezado">'
        f'<nav class="entrada__migas" aria-label="Ruta de navegación"><a href="{r}index.html">Inicio</a>'
        f'<span aria-hidden="true">/</span><a href="{r}blog.html">Blog</a>'
        f'<span aria-hidden="true">/</span><span>{html.escape(e["categoria"])}</span></nav>'
        f'<p class="entrada__categoria">{html.escape(e["categoria"])}</p>'
        f'<h1 class="entrada__titulo">{titulo}</h1>'
        f'<p class="entrada__meta"><time datetime="{e["fecha"]}">{fecha_larga(e["fecha"])}</time>'
        f'<span aria-hidden="true">·</span><span>{minutos_lectura(e)} min de lectura</span></p>'
        f'</header>'
        f'<figure class="entrada__portada"><img src="{r}{html.escape(e["imagen"])}" '
        f'alt="{html.escape(e.get("descripcion_imagen") or "")}"></figure>'
        f'<div class="entrada__contenido">{contenido}</div>'
        f'<div class="entrada__compartir">'
        f'<p class="entrada__compartir-titulo">Comparte este artículo</p>'
        f'<div class="entrada__botones">'
        f'<a class="entrada__boton entrada__boton--whatsapp" href="https://wa.me/?text={texto_compartir}" '
        f'target="_blank" rel="noopener">{ICONO_WHATSAPP}<span>WhatsApp</span></a>'
        f'<a class="entrada__boton entrada__boton--facebook" href="https://www.facebook.com/sharer/sharer.php?u={quote(url)}" '
        f'target="_blank" rel="noopener">{ICONO_FACEBOOK}<span>Facebook</span></a>'
        f'<button class="entrada__boton entrada__boton--copiar" type="button" data-copiar="{url}">'
        f'{ICONO_COPIAR}<span data-copiar-texto>Copiar enlace</span></button>'
        f'</div></div>'
        f'<aside class="entrada__invitacion" aria-labelledby="invitacion-titulo">'
        f'<h2 class="entrada__invitacion-titulo" id="invitacion-titulo">¿Quieres ser parte del cambio?</h2>'
        f'<p>Tu apoyo hace posibles nuestras jornadas, talleres y programas en las comunidades.</p>'
        f'<div class="entrada__invitacion-botones">'
        f'<a class="entrada__cta entrada__cta--donar" href="{r}index.html#donar">{ICONO_CORAZON}Donar</a>'
        f'<a class="entrada__cta entrada__cta--voluntario" href="{r}contacto.html?motivo=voluntariado#formulario">Quiero ser voluntario</a>'
        f'</div></aside>'
        f'</article>'
        + (f'<section class="entrada__relacionadas" aria-labelledby="relacionadas-titulo">'
           f'<h2 class="entrada__relacionadas-titulo" id="relacionadas-titulo">Sigue leyendo</h2>'
           f'<ul class="blog__lista">{sugeridas}</ul></section>' if sugeridas else "")
        + '</main>'
    )
    return pagina_limpia(pagina, f'{titulo} | Blog de CAPDES, A.C.', resumen, cuerpo,
                         scripts=("blog",), head_extra=head_extra, clase_body="pagina-entrada")


def generar_entradas():
    datos = leer_blog()
    carpeta = RAIZ / "blog"
    carpeta.mkdir(exist_ok=True)
    vigentes = set()
    for e in datos["entradas"]:
        ruta = carpeta / f'{e["slug"]}.html'
        ruta.write_text(generar_pagina_entrada(e, datos["entradas"]), encoding="utf-8")
        vigentes.add(ruta.name)
        print(f"✓ blog/{ruta.name}")
    # Borra las páginas de artículos que ya no están en datos/blog.json (solo las generadas aquí).
    for ruta in carpeta.glob("*.html"):
        if ruta.name not in vigentes and GENERADOR in ruta.read_text(encoding="utf-8"):
            ruta.unlink()
            print(f"✗ blog/{ruta.name} (ya no está en datos/blog.json)")


def version(ruta):
    return hashlib.md5((RAIZ / ruta).read_bytes()).hexdigest()[:8]


def enlazar_recursos(s, pagina):
    v_css = version("css/renovacion.css")
    v_anim = version("js/animaciones.js")

    css = f'<link rel="stylesheet" href="css/renovacion.css?v={v_css}">'
    if '<link rel="stylesheet" href="css/renovacion.css' in s:
        s = re.sub(r'<link rel="stylesheet" href="css/renovacion\.css(\?v=[^"]*)?">', css, s)
    else:
        s = s.replace('<link rel="stylesheet" href="css/main.css">',
                      '<link rel="stylesheet" href="css/main.css">' + css, 1)

    s = re.sub(r'<script src="js/animaciones\.js(\?v=[^"]*)?"></script>',
               f'<script src="js/animaciones.js?v={v_anim}"></script>', s)

    for nombre in ["menu", "contador", "pie"] + JS_POR_PAGINA.get(pagina, []):
        js = f'<script src="js/{nombre}.js?v={version(f"js/{nombre}.js")}"></script>'
        if f'<script src="js/{nombre}.js' in s:
            s = re.sub(rf'<script src="js/{nombre}\.js(\?v=[^"]*)?"></script>', js, s)
        else:
            s = s.replace("</body></html>", js + "</body></html>", 1)
    return s


def insertar_anclas(s, pagina):
    for ancla, seccion in ANCLAS.get(pagina, []):
        if f'id="{ancla}"' in s:
            continue
        marca = f'<section id="{seccion}"'
        if marca not in s:
            raise SystemExit(f"{pagina}: no encontré la sección {seccion} para el ancla #{ancla}")
        s = s.replace(marca, f'<span id="{ancla}" class="ancla"></span>{marca}', 1)
    return s


def aplicar(pagina):
    ruta = RAIZ / pagina
    s = ruta.read_text(encoding="utf-8")
    menu = generar_menu(pagina)

    if INICIO_MARCA in s:
        s = re.sub(re.escape(INICIO_MARCA) + ".*?" + re.escape(FIN_MARCA), lambda _: menu, s, count=1, flags=re.S)
    else:
        # Primera vez: reemplaza el header original de Hostinger.
        s, n = re.subn(r"<header class=\"block-header\".*?</header>", lambda _: menu, s, count=1, flags=re.S)
        if n != 1:
            raise SystemExit(f"{pagina}: no encontré el header original")

    s = insertar_anclas(s, pagina)
    s = insertar_pie(s, pagina)
    s = insertar_slides(s, pagina)
    s = insertar_contenido_inicio(s, pagina)
    s = insertar_blog(s, pagina)
    s = insertar_programas(s, pagina)
    s = insertar_marco(s, pagina)
    s = insertar_contacto(s, pagina)
    s = enlazar_recursos(s, pagina)
    ruta.write_text(s, encoding="utf-8")
    print(f"✓ {pagina}")


if __name__ == "__main__":
    for pagina in ACTIVA:
        aplicar(pagina)
    generar_entradas()
    generar_aviso()
