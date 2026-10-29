"""Genera el menú de navegación y lo inserta en todas las páginas.

La estructura del menú vive solo aquí. Para cambiarla, edita MENU y corre:

    python3 herramientas/menu.py

El script también:
- crea anclas legibles (p. ej. programas.html#ejes) antes de las secciones de Hostinger,
- enlaza css/renovacion.css y js/menu.js en cada página,
- pone a los enlaces de CSS/JS propios un ?v= según su contenido, para que el
  navegador no use una copia vieja en caché.
"""

import hashlib
import html
import re
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent

DONAR_URL = "https://gofund.me/73c07af98"

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
        ("Ejes estratégicos", "programas.html#ejes"),
        ("Programas de intervención", "programas.html#programas-intervencion"),
        ("Jornadas realizadas", "index.html#eventos"),
    ]),
    ("Marco institucional", "marco-institucional.html", [
        ("Identidad institucional", "marco-institucional.html#identidad"),
        ("Planeación estratégica", "marco-institucional.html#planeacion"),
        ("Marco normativo y políticas", "marco-institucional.html#marco-normativo"),
        ("Informes y rendición de cuentas", "marco-institucional.html#informes"),
    ]),
    ("Aliados", "index.html#aliados", [
        ("Aliados estratégicos", "index.html#aliados"),
        ("Donar", DONAR_URL),
    ]),
    ("Contacto", "contacto.html", [
        ("Escríbenos", "contacto.html#formulario"),
        ("Voluntariado y servicio social", "contacto.html#formulario"),
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
    "index.html": [("eventos", "zXDtLi"), ("aliados", "zwZ-vA")],
    "quienes-somos.html": [("acerca", "zYUchx"), ("mision-vision-valores", "zB7aPl")],
    "programas.html": [("ejes", "zfOqnu"), ("programas-intervencion", "z6R8fD")],
    "marco-institucional.html": [
        ("identidad", "zVT-Di"),
        ("planeacion", "ztjK4x"),
        ("marco-normativo", "zVi5id"),
        ("informes", "znZ5W_"),
    ],
    "contacto.html": [("formulario", "zXR5Ei"), ("ubicacion", "eOBFmV")],
}

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


def es_externo(url):
    return url.startswith("http")


def atributos_enlace(url, pagina):
    attrs = f'href="{html.escape(url)}"'
    if es_externo(url):
        attrs += ' target="_blank" rel="noopener"'
    elif url == pagina:
        attrs += ' aria-current="page"'
    return attrs


def slug(texto):
    return re.sub(r"[^a-z0-9]+", "-", texto.lower().translate(str.maketrans("áéíóúñ", "aeioun"))).strip("-")


def redes(clase):
    return (f'<div class="{clase}">'
            f'<a href="https://www.facebook.com/capdes.ac" target="_blank" rel="noopener" '
            f'aria-label="Facebook de CAPDES">{ICONO_FACEBOOK}</a>'
            f'<a href="https://www.instagram.com/capdes.ac" target="_blank" rel="noopener" '
            f'aria-label="Instagram de CAPDES">{ICONO_INSTAGRAM}</a></div>')


def generar_items(pagina, sufijo):
    items = []
    for texto, url, subs in MENU:
        id_sub = f"submenu-{slug(texto)}-{sufijo}"
        activo = ACTIVA.get(pagina) == texto
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
        f'<a class="menu__logo" href="index.html" aria-label="CAPDES, A.C. – Inicio">'
        f'<img src="{LOGO}" alt="CAPDES, A.C." width="264" height="143"></a>'
        f'<div class="menu__pestanas"><div class="menu__pestanas-interior">'
        f'<nav class="menu__nav" aria-label="Menú principal">'
        f'<ul class="menu__lista">{generar_items(pagina, "barra")}</ul>'
        f'</nav>'
        f'</div></div>'
        f'<div class="menu__acciones">'
        f'<a class="menu__donar" href="{DONAR_URL}" target="_blank" rel="noopener">{ICONO_CORAZON}<span>Donar</span></a>'
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


def version(ruta):
    return hashlib.md5((RAIZ / ruta).read_bytes()).hexdigest()[:8]


def enlazar_recursos(s):
    v_css = version("css/renovacion.css")
    v_js = version("js/menu.js")
    v_anim = version("js/animaciones.js")

    css = f'<link rel="stylesheet" href="css/renovacion.css?v={v_css}">'
    if "css/renovacion.css" in s:
        s = re.sub(r'<link rel="stylesheet" href="css/renovacion\.css(\?v=[^"]*)?">', css, s)
    else:
        s = s.replace('<link rel="stylesheet" href="css/main.css">',
                      '<link rel="stylesheet" href="css/main.css">' + css, 1)

    s = re.sub(r'<script src="js/animaciones\.js(\?v=[^"]*)?"></script>',
               f'<script src="js/animaciones.js?v={v_anim}"></script>', s)

    js = f'<script src="js/menu.js?v={v_js}"></script>'
    if "js/menu.js" in s:
        s = re.sub(r'<script src="js/menu\.js(\?v=[^"]*)?"></script>', js, s)
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
    s = enlazar_recursos(s)
    ruta.write_text(s, encoding="utf-8")
    print(f"✓ {pagina}")


if __name__ == "__main__":
    for pagina in ACTIVA:
        aplicar(pagina)
