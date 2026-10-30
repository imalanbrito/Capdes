# Cómo editar el contenido del sitio sin tocar el HTML

Las secciones que CAPDES actualiza seguido toman su contenido de archivos de la
carpeta `datos/`. Al abrir la página, el sitio lee esos archivos y muestra lo que
tengan, así que basta con editar el archivo (y subir las fotos) para que el cambio
se vea.

| Sección                               | Archivo                 | Fotos en          |
|---------------------------------------|-------------------------|-------------------|
| Carrusel de jornadas                  | `datos/jornadas.json`   | `img/jornadas/`   |
| Nuestro impacto (cita, cifras, áreas) | `datos/impacto.json`    | `img/impacto/`    |
| Eventos y actividades comunitarias    | `datos/eventos.json`    | `img/eventos/`    |
| Donativos (PayPal, GoFundMe)          | `datos/donar.json`      | —                 |
| Nuestros aliados (logos)              | `datos/aliados.json`    | `img/aliados/`    |
| Contacto y redes (pie de página y menú, todas las páginas) | `datos/contacto.json` | — |
| Blog: listado y una página por artículo | `datos/blog.json` | `img/blog/` |
| Programas: programas, galería de jornadas y ODS | `datos/programas.json` | `img/programas/`, `img/galeria/` |

> Más adelante estos archivos se editarán desde un panel de administrador
> (`capdes.org/admin`) con formularios. Mientras tanto, se editan a mano con las
> reglas de abajo.

## Reglas generales

- **Fotos**: formato JPG, de unos 1200–1600 px de ancho y menos de 500 KB. Nombre sin
  espacios ni acentos, empezando por su número de orden: `04-jornada-de-salud.jpg`.
- **descripcion**: qué se ve en la foto. No aparece en pantalla, pero la leen los
  lectores de pantalla de personas con discapacidad visual y los buscadores.
- Los textos van entre comillas `"..."`. Cada bloque `{ ... }` va separado del
  siguiente por una coma, y después del último no lleva coma.
- Para **quitar** un elemento, borra su bloque (y la coma que lo separa). Para
  **cambiar el orden**, mueve los bloques.
- Después de editar, corre `python3 herramientas/menu.py`: integra el contenido nuevo
  en el HTML de la página (así lo ven los buscadores) y avisa si algo quedó mal escrito.

## Carrusel de jornadas — `datos/jornadas.json`

```json
{
  "imagen": "img/jornadas/06-jornada-de-salud.jpg",
  "titulo": "Jornada de salud en San Andrés",
  "descripcion": "Personal de salud toma la presión arterial a vecinas de la comunidad."
}
```

- **titulo**: el texto que se ve sobre la foto. Si lo dejas vacío (`""`), no se muestra.
- **llenar_marco** (opcional, `true`): la imagen ocupa todo el marco sin fondo
  desenfocado. Úsalo solo para diseños de 16:9, como el banner.

### Opción: tomar las fotos de una carpeta de Google Drive

Sirve para que la persona administradora solo tenga que subir fotos a Drive.

1. Crea una carpeta en Google Drive y compártela como
   **"Cualquier persona con el enlace" → Lector**.
2. Sube las fotos con nombres que empiecen con su número de orden. **El nombre del
   archivo es el título que se verá en el carrusel**:
   - `01 - Banner.jpg` → si el nombre empieza con "Banner", se muestra a todo el marco y sin título.
   - `02 - Taller de huertos familiares.jpg` → título "Taller de huertos familiares".
   - En Drive, clic derecho en la foto → *Información del archivo* → *Descripción*: ahí
     va la descripción de lo que se ve en la foto.
3. Crea una clave de API de Google Drive (una sola vez, en Google Cloud Console):
   activa la "Google Drive API", crea una **clave de API** y restríngela a
   - la API: *Google Drive API*;
   - los sitios web: `capdes.org/*` y `www.capdes.org/*`.
4. En `datos/jornadas.json` cambia:

   ```json
   "fuente": "drive",
   "drive": {
     "carpeta_id": "EL_ID_DE_LA_CARPETA",
     "api_key": "LA_CLAVE_DE_API"
   },
   ```

   El ID de la carpeta es la última parte de su enlace:
   `https://drive.google.com/drive/folders/`**`1AbCdEf...`**.

Desde ese momento el carrusel muestra lo que haya en la carpeta. Si Drive no responde,
se muestran las fotos de la lista `"jornadas"`, así que conviene dejarlas como respaldo.

> La clave de API queda visible en el código del sitio; por eso es importante
> restringirla a la Drive API y al dominio de CAPDES, como dice el paso 3.

## Nuestro impacto — `datos/impacto.json`

- **titulo** y **cita**: el título de la sección y la frase destacada.
- **cifras**: los números grandes que cuentan hacia arriba al aparecer.

  ```json
  { "numero": 150, "prefijo": "+", "texto": "participantes en nuestros programas", "icono": "personas" }
  ```

  - **numero**: solo dígitos, sin comas ni signos.
  - **prefijo**: lo que va antes del número (`"+"`), o `""` si no lleva nada.
  - **icono**: `"estrella"`, `"personas"`, `"corazon"` o `"casa"`.
- **areas**: las tarjetas con foto.

  ```json
  { "titulo": "Mujeres capacitadas", "numero": 80, "imagen": "img/impacto/03-mujeres-capacitadas.jpg", "descripcion": "..." }
  ```

  - **numero**: opcional. Con `null` la tarjeta solo muestra el título; con un número
    (p. ej. `80`), lo muestra grande y animado sobre el título, con `+` al inicio
    (para quitar el `+`, agrega `"prefijo": ""`).

## Eventos — `datos/eventos.json`

```json
{
  "dia": 18,
  "mes": "septiembre",
  "tipo": "Jornada comunitaria",
  "titulo": "Servicios comunitarios",
  "lugar": "Centro Comunitario CAPDES",
  "imagen": "img/eventos/02-servicios-comunitarios.jpg",
  "descripcion": "Personas de la comunidad sentadas en círculo conversan con un facilitador de CAPDES."
}
```

- **dia**: el número del día, sin ceros a la izquierda (`4`, no `04`).
- **mes**: el nombre completo en minúsculas (`"septiembre"`); en la tarjeta se ve como "SEP".
- **tipo** y **lugar**: opcionales; si se dejan vacíos (`""`) no se muestran.

## Donativos — `datos/donar.json`

Todos los botones "Donar" del sitio llevan a esta sección, donde se muestra una tarjeta
por cada forma de donar.

```json
{
  "plataforma": "paypal",
  "titulo": "Dona con PayPal",
  "texto": "Aporta con tarjeta de crédito o débito, o con tu cuenta de PayPal.",
  "boton": "Donar con PayPal",
  "enlace": "https://www.paypal.com/ncp/payment/9XRBAT36GXUAW"
}
```

- **plataforma**: `"paypal"` o `"gofundme"` (definen el color y el ícono de la tarjeta).
  Cualquier otro nombre usa los colores de CAPDES.
- **enlace**: la dirección completa del enlace de pago, empezando con `https://`.
- **titulo** y **texto** (arriba de la lista): el título y la frase de la sección.

## Nuestros aliados — `datos/aliados.json`

```json
{ "nombre": "UPAEP", "logo": "img/aliados/01-upaep.png", "enlace": "https://www.upaep.mx" }
```

- **logo**: PNG con fondo transparente o blanco, recortado sin márgenes sobrantes y de
  unos 200–260 px de alto.
- **nombre**: el nombre de la organización (lo leen los lectores de pantalla).
- **enlace**: opcional; si se llena, el logo lleva al sitio del aliado. Con `""` no es enlace.
- Los logos se ven en gris y toman su color al pasar el mouse; se desplazan solos y se
  detienen al pasar el mouse por encima.

## Contacto y redes — `datos/contacto.json`

Se usa en el pie de página de todas las páginas y en los íconos de redes del menú.

- **telefono**: como se quiere ver, p. ej. `"+52 221 579 2406"`. En el celular, tocarlo inicia la llamada.
- **whatsapp**: el número con lada de país, p. ej. `"+52 221 579 2406"`. Si se deja vacío (`""`),
  no aparece el botón de WhatsApp.
- **correo**: el correo de contacto. También es a donde llegan los mensajes del formulario
  "Sé parte del cambio".
- **redes**: una entrada por red, con `"red"` (`"facebook"` o `"instagram"` tienen su ícono;
  otras muestran un ícono de enlace) y `"enlace"` (la dirección completa del perfil).
- **nombre_completo**: el nombre de la asociación que se ve bajo el logo.

> El formulario "Sé parte del cambio" por ahora abre la aplicación de correo de la persona
> con un mensaje listo para enviar a CAPDES. Cuando exista el panel de administrador, las
> suscripciones se guardarán ahí directamente.

## Blog — `datos/blog.json`

Cada artículo tiene su propia página (`blog/<slug>.html`) y aparece en el listado de
`blog.html`, ordenado del más reciente al más antiguo. El más reciente se muestra destacado.

```json
{
  "slug": "jornada-de-salud-san-andres",
  "titulo": "Así vivimos la Jornada de Salud en San Andrés",
  "fecha": "2026-11-15",
  "categoria": "Eventos",
  "imagen": "img/blog/07-jornada-de-salud.jpg",
  "descripcion_imagen": "Personal de salud toma la presión arterial a vecinas de la comunidad.",
  "resumen": "Más de 80 personas recibieron atención médica gratuita en nuestra jornada de noviembre.",
  "contenido": [
    "Primer párrafo del artículo.",
    "Segundo párrafo, con una **frase en negritas** y otra *en cursiva*."
  ]
}
```

- **slug**: la última parte de la dirección del artículo. Solo minúsculas, números y guiones,
  sin acentos ni espacios, y sin repetirse. **No lo cambies después de publicar**: los
  enlaces que ya se compartieron dejarían de funcionar.
- **fecha**: en formato `AAAA-MM-DD` (año, mes y día). En la página se ve como "15 de noviembre de 2026".
- **categoria**: una de las de la lista `"categorias"` (Noticias, Eventos, Reflexiones). Los
  botones para filtrar solo muestran las categorías que tienen artículos.
- **resumen**: una o dos frases; se ve en el listado y en la vista previa al compartir en
  WhatsApp o Facebook.
- **contenido**: un texto por párrafo. Se permiten **negritas** (`**texto**`) y *cursivas* (`*texto*`).
- **imagen**: foto horizontal, de unos 1200–1600 px de ancho.

**Importante:** después de agregar, editar o quitar artículos hay que correr
`python3 herramientas/menu.py`. Ese comando crea las páginas nuevas, actualiza el listado y
borra las páginas de los artículos que ya no están en el archivo. (Cuando exista el panel de
administrador, esto lo hará el botón "Publicar".)

Al cambiar la dirección del sitio, ajusta `SITIO` en `herramientas/menu.py`: se usa en los
enlaces para compartir y en las vistas previas de redes sociales.

## Programas — `datos/programas.json`

### Galería de jornadas (lo que más se actualiza)

Para agregar una jornada, sube la foto a `img/galeria/` y agrega un bloque en `"jornadas"`:

```json
{
  "imagen": "img/galeria/08-jornada-de-salud-san-andres.jpg",
  "programa": "salud-y-bienestar",
  "titulo": "Jornada de salud en San Andrés",
  "lugar": "San Andrés Cholula, Puebla",
  "fecha": "2026-11-15",
  "descripcion": "Personal de salud toma la presión arterial a vecinas de la comunidad."
}
```

- **programa**: la `clave` de uno de los programas (por ejemplo `"salud-y-bienestar"`,
  `"educacion-y-capacitacion"`, `"empoderamiento-economico"`, `"igualdad-de-genero"`,
  `"acciones-subsidiarias"`, `"recaudacion-de-fondos"`).
- **lugar** y **fecha** son opcionales (`""`); la fecha va como `AAAA-MM-DD`.
- La galería se actualiza sola al abrir la página: muestra la foto, agrega el filtro de su
  programa y actualiza el botón "Ver jornadas (n)" de ese programa.

### Programas y tipos

- **programas**: cada uno con `clave` (minúsculas y guiones, sin cambiarla después),
  `nombre`, `tipo`, `icono`, `imagen`, `descripcion_imagen`, `descripcion` y `ods`.
  - **tipo**: la `clave` de uno de los `"tipos"` (`"asistenciales"`, `"desarrollo-social"`,
    `"sostenibilidad"`). Cada tipo es una pestaña para filtrar los programas.
  - **icono**: `"salud"`, `"educacion"`, `"brote"`, `"personas"`, `"apoyo"` o `"corazon"`.
  - **ods**: los números (del 1 al 17) de los Objetivos de Desarrollo Sostenible con los que se
    relaciona; se muestran en la tarjeta y se resaltan en la sección de la Agenda 2030.
- **intro**, **modelo**, **agenda_2030** e **imagen_portada**: textos y foto de la portada y
  de los encabezados de la página.

**Importante:** los cambios a programas, tipos, ODS o textos necesitan correr
`python3 herramientas/menu.py`. Las jornadas de la galería no lo necesitan.
