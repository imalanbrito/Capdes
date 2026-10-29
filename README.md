# Réplica estática de capdes.org

Copia estática del sitio **capdes.org** (CAPDES, A.C.), capturada el **29 de octubre de 2026**
a partir de las páginas publicadas. Sirve como referencia de código, respaldo del estado actual
y base para estudiar o extraer contenido.

## Contenido

```
capdes-org-replica/
├── index.html                 (Inicio)
├── programas.html             (Programas)
├── marco-institucional.html   (Marco Institucional)
├── contacto.html              (Contacto)
├── blog.html                  (Blog)
├── quienes-somos.html         (Quienes Somos)
├── css/
│   ├── main.css               (hoja de estilos principal del sitio)
│   └── cookieconsent.css      (estilos del banner de cookies)
└── README.md
```

## Cómo verla

Abre `index.html` en cualquier navegador. Los enlaces del menú ya están reescritos para
navegar entre los archivos `.html` locales, así que puedes recorrer todo el sitio desde la carpeta.

## Qué incluye y qué no

- **HTML de las 6 páginas:** completo, con todo el texto y la estructura reales del sitio.
- **CSS:** incluido localmente (`css/`), así que el diseño se ve aunque no haya conexión.
- **Imágenes, fuentes y videos:** NO están descargados. Se cargan desde el CDN de Hostinger
  (`assets.zyrosite.com` y similares) mediante sus URLs originales. Por eso, para ver el sitio
  con sus imágenes necesitas conexión a internet. Si quieres una copia 100% offline con las
  imágenes descargadas, se puede hacer como paso adicional.
- **Funciones dinámicas** (carrusel, envío de formularios, consentimiento de cookies): pueden
  no funcionar fuera del servidor original; esta es una captura estática, no la aplicación en vivo.

## Notas importantes

- Es el HTML/CSS **ya renderizado** del sitio publicado, no el código fuente "limpio" del
  constructor. El sitio original está hecho con el **AI Website Builder de Hostinger** (sobre Astro).
- Esta copia **no se puede volver a importar** al editor visual de Hostinger. Es un snapshot de
  referencia, no un proyecto editable dentro del builder.
- Para modificar el sitio real, se sigue trabajando en el editor de Hostinger (o, si se decidiera
  migrar a código, se partiría de una copia como esta y se alojaría como sitio estático).
